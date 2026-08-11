import { asyncHandler } from '../../utils/asyncHandler.js';
import { ApiError } from '../../utils/ApiError.js';
import { NavesModel } from './naves.model.js';
import { CamposModel } from './campos.model.js';
import { validarDatos } from './campos.validator.js';

const ESTADOS = ['Programada', 'En Puerto', 'En Operaciones', 'Finalizada'];
const TURNOS = ['Mañana', 'Noche'];

// Normaliza y valida un reporte de turno. Las TM por flujo son opcionales,
// pero el reporte debe traer al menos un flujo con cantidad o una observación.
function parseReporte(body) {
  const fecha_turno = body.fecha_turno?.trim() || null;
  if (fecha_turno && !/^\d{4}-\d{2}-\d{2}$/.test(fecha_turno)) {
    throw new ApiError(400, 'fecha_turno debe ser YYYY-MM-DD.');
  }
  const turno = body.turno?.trim() || null;
  if (turno && !TURNOS.includes(turno)) {
    throw new ApiError(400, `Turno inválido. Use: ${TURNOS.join(' o ')}.`);
  }

  // Cada flujo: número >= 0 o null.
  const num = (v, etq) => {
    if (v === undefined || v === null || v === '') return null;
    const n = Number(v);
    if (!Number.isFinite(n) || n < 0) throw new ApiError(400, `${etq} debe ser un número ≥ 0.`);
    return n;
  };
  const directa_tm = num(body.directa_tm, 'Descarga directa');
  const indirecta_tm = num(body.indirecta_tm, 'Descarga indirecta');
  const despacho_tm = num(body.despacho_tm, 'Despacho de almacén');

  const observaciones = body.observaciones?.trim() || body.descripcion_avance?.trim() || null;

  const hayCifra = [directa_tm, indirecta_tm, despacho_tm].some((x) => x !== null && x > 0);
  if (!hayCifra && !observaciones) {
    throw new ApiError(400, 'Registra al menos una cantidad movida o una observación.');
  }

  return { fecha_turno, turno, directa_tm, indirecta_tm, despacho_tm, descripcion_avance: observaciones };
}

// Construye el resumen de control: avance de descarga = directa + indirecta,
// medido contra la cantidad total declarada en la nave. El despacho de almacén
// se reporta aparte (no suma al avance del barco).
function construirResumen(nave, acum) {
  const datos = nave.datos_adicionales || {};
  const total = Number(datos.cantidad_total) || 0;
  const movido = acum.directa + acum.indirecta;
  const saldo = total > 0 ? Math.max(total - movido, 0) : 0;
  const porcentaje = total > 0 ? Math.min(Math.round((movido / total) * 1000) / 10, 100) : null;
  return {
    total,                       // TM totales declaradas
    directa: acum.directa,
    indirecta: acum.indirecta,
    despacho: acum.despacho,     // indicador propio (no entra al %)
    movido,                      // directa + indirecta
    saldo,
    porcentaje,                  // null si no hay total declarado
    reportes: acum.reportes,
  };
}

// Normaliza y valida actividad_id (opcional). Devuelve un id válido o null.
async function resolverActividad(actividad_id) {
  if (actividad_id === undefined || actividad_id === null || actividad_id === '') return null;
  const id = Number(actividad_id);
  if (!Number.isInteger(id) || id <= 0) throw new ApiError(400, 'actividad_id inválido.');
  if (!(await NavesModel.actividadExiste(id))) {
    throw new ApiError(400, 'La actividad no existe o está inactiva.');
  }
  return id;
}

// POST /api/operaciones/naves   (Administrador, Supervisor)
export const crearNave = asyncHandler(async (req, res) => {
  const { nombre, muelle, tipo_nave_id, actividad_id, eta, etb, etd, estado, datos_adicionales } = req.body;

  if (!nombre?.trim()) throw new ApiError(400, 'El nombre de la nave es obligatorio.');
  if (!tipo_nave_id) throw new ApiError(400, 'El tipo_nave_id es obligatorio.');
  if (estado && !ESTADOS.includes(estado)) {
    throw new ApiError(400, `Estado inválido. Use: ${ESTADOS.join(', ')}.`);
  }
  if (!(await NavesModel.tipoExiste(tipo_nave_id))) {
    throw new ApiError(400, 'El tipo de nave no existe o está inactivo.');
  }
  const actId = await resolverActividad(actividad_id);

  // Datos adicionales opcionales al crear: se aceptan como JSON libre.
  // La estructura del operativo (p.ej. TEUs y tipo de operación del containero, o
  // las bodegas dinámicas del granelero) la define el front por tipo de nave.
  // La validación estricta contra el catálogo de campos ocurre al "enviar el
  // formulario" en PUT /datos.
  let datos = null;
  if (datos_adicionales !== undefined && datos_adicionales !== null) {
    if (typeof datos_adicionales !== 'object' || Array.isArray(datos_adicionales)) {
      throw new ApiError(400, 'datos_adicionales debe ser un objeto.');
    }
    datos = datos_adicionales;
  }

  const nave = await NavesModel.crear({
    nombre: nombre.trim(), muelle: muelle?.trim() || null,
    tipo_nave_id, actividad_id: actId, eta, etb, etd, estado, datos_adicionales: datos,
  });
  res.status(201).json({ success: true, data: nave });
});

// PUT /api/operaciones/naves/:id   (Administrador, Supervisor)
// Edita los datos base de la nave. No modifica datos_adicionales (van por PUT /datos).
export const actualizarNave = asyncHandler(async (req, res) => {
  const naveId = Number(req.params.id);
  if (!Number.isInteger(naveId) || naveId <= 0) throw new ApiError(400, 'ID de nave inválido.');

  const existente = await NavesModel.obtenerPorId(naveId);
  if (!existente) throw new ApiError(404, 'Nave no encontrada.');

  const { nombre, muelle, tipo_nave_id, actividad_id, eta, etb, etd, estado } = req.body;
  if (!nombre?.trim()) throw new ApiError(400, 'El nombre de la nave es obligatorio.');
  if (!tipo_nave_id) throw new ApiError(400, 'El tipo_nave_id es obligatorio.');
  if (estado && !ESTADOS.includes(estado)) {
    throw new ApiError(400, `Estado inválido. Use: ${ESTADOS.join(', ')}.`);
  }
  if (!(await NavesModel.tipoExiste(tipo_nave_id))) {
    throw new ApiError(400, 'El tipo de nave no existe o está inactivo.');
  }
  const actId = await resolverActividad(actividad_id);

  const nave = await NavesModel.actualizar(naveId, {
    nombre: nombre.trim(), muelle: muelle?.trim() || null,
    tipo_nave_id, actividad_id: actId, eta, etb, etd, estado: estado || existente.estado,
  });
  res.json({ success: true, data: nave });
});

// PUT /api/operaciones/naves/:id/tipo   (Administrador, Supervisor, Coordinador)
// Actualiza SOLO el tipo de nave. Pensado para que el registro tallyman de muelle
// propague a Operaciones el "TIPO DE NAVE" que se ajuste desde la actividad, sin
// requerir el formulario completo de edición de nave.
export const actualizarTipoNave = asyncHandler(async (req, res) => {
  const naveId = Number(req.params.id);
  if (!Number.isInteger(naveId) || naveId <= 0) throw new ApiError(400, 'ID de nave inválido.');

  const existente = await NavesModel.obtenerPorId(naveId);
  if (!existente) throw new ApiError(404, 'Nave no encontrada.');

  const tipoNaveId = Number(req.body?.tipo_nave_id);
  if (!Number.isInteger(tipoNaveId) || tipoNaveId <= 0) {
    throw new ApiError(400, 'tipo_nave_id es obligatorio.');
  }
  if (!(await NavesModel.tipoExiste(tipoNaveId))) {
    throw new ApiError(400, 'El tipo de nave no existe o está inactivo.');
  }

  const nave = await NavesModel.actualizarTipo(naveId, tipoNaveId);
  res.json({ success: true, data: nave });
});

// PUT /api/operaciones/naves/:id/muelle-actividad   (Administrador, Supervisor, Coordinador)
// Actualiza SOLO el muelle y/o la actividad de la nave, sin requerir el formulario
// completo. Pensado para que el registro tallyman de muelle propague a Operaciones
// el muelle/actividad que el coordinador ajuste al editar. Cada campo es opcional:
// si no viene en el body, no se toca.
export const actualizarMuelleActividadNave = asyncHandler(async (req, res) => {
  const naveId = Number(req.params.id);
  if (!Number.isInteger(naveId) || naveId <= 0) throw new ApiError(400, 'ID de nave inválido.');

  const existente = await NavesModel.obtenerPorId(naveId);
  if (!existente) throw new ApiError(404, 'Nave no encontrada.');

  const cambios = {};
  if (Object.prototype.hasOwnProperty.call(req.body ?? {}, 'muelle')) {
    const m = req.body.muelle;
    cambios.muelle = (m === null || m === undefined || String(m).trim() === '') ? null : String(m).trim();
  }
  if (Object.prototype.hasOwnProperty.call(req.body ?? {}, 'actividad_id')) {
    cambios.actividad_id = await resolverActividad(req.body.actividad_id);
  }
  if (Object.keys(cambios).length === 0) {
    throw new ApiError(400, 'Nada que actualizar: envía muelle y/o actividad_id.');
  }

  const nave = await NavesModel.actualizarMuelleActividad(naveId, cambios);
  res.json({ success: true, data: nave });
});

// DELETE /api/operaciones/naves/:id   (Administrador, Supervisor)
export const eliminarNave = asyncHandler(async (req, res) => {
  const naveId = Number(req.params.id);
  if (!Number.isInteger(naveId) || naveId <= 0) throw new ApiError(400, 'ID de nave inválido.');
  const eliminada = await NavesModel.eliminar(naveId);
  if (!eliminada) throw new ApiError(404, 'Nave no encontrada.');
  res.json({ success: true });
});

// GET /api/operaciones/naves?estado=...   (activas/programadas por defecto)
export const listarNaves = asyncHandler(async (req, res) => {
  const { estado } = req.query;
  if (estado && !ESTADOS.includes(estado)) {
    throw new ApiError(400, `Estado inválido. Use: ${ESTADOS.join(', ')}.`);
  }
  const naves = await NavesModel.listar({ estado });
  res.json({ success: true, count: naves.length, data: naves });
});

// GET /api/operaciones/tipos-nave  → catálogo de tipos activos (para selects del front)
export const listarTipos = asyncHandler(async (_req, res) => {
  const tipos = await NavesModel.listarTipos();
  res.json({ success: true, count: tipos.length, data: tipos });
});

// POST /api/operaciones/naves/:id/avances   (Coordinador) — reporte de turno
export const registrarAvance = asyncHandler(async (req, res) => {
  const naveId = Number(req.params.id);
  if (!Number.isInteger(naveId) || naveId <= 0) throw new ApiError(400, 'ID de nave inválido.');

  const nave = await NavesModel.obtenerPorId(naveId);
  if (!nave) throw new ApiError(404, 'Nave no encontrada.');

  const reporte = parseReporte(req.body);
  const avance = await NavesModel.agregarAvance(naveId, {
    ...reporte,
    registrado_por: req.user.name, // identidad simulada (Fase 1)
  });
  res.status(201).json({ success: true, data: avance });
});

// PUT /api/operaciones/naves/:id/avances/:avId   (Administrador, Supervisor)
export const editarAvance = asyncHandler(async (req, res) => {
  const naveId = Number(req.params.id);
  const avId = Number(req.params.avId);
  if (!Number.isInteger(naveId) || naveId <= 0) throw new ApiError(400, 'ID de nave inválido.');
  if (!Number.isInteger(avId) || avId <= 0) throw new ApiError(400, 'ID de avance inválido.');

  const avance = await NavesModel.obtenerAvance(avId);
  if (!avance || avance.nave_id !== naveId) throw new ApiError(404, 'Avance no encontrado.');

  const reporte = parseReporte(req.body);
  const actualizado = await NavesModel.editarAvance(avId, reporte);
  res.json({ success: true, data: actualizado });
});

// DELETE /api/operaciones/naves/:id/avances/:avId   (Administrador, Supervisor)
export const eliminarAvance = asyncHandler(async (req, res) => {
  const naveId = Number(req.params.id);
  const avId = Number(req.params.avId);
  if (!Number.isInteger(naveId) || naveId <= 0) throw new ApiError(400, 'ID de nave inválido.');
  if (!Number.isInteger(avId) || avId <= 0) throw new ApiError(400, 'ID de avance inválido.');

  const avance = await NavesModel.obtenerAvance(avId);
  if (!avance || avance.nave_id !== naveId) throw new ApiError(404, 'Avance no encontrado.');

  await NavesModel.eliminarAvance(avId);
  res.json({ success: true });
});

// GET /api/operaciones/naves/:id/historial   (+ resumen de control)
export const historialNave = asyncHandler(async (req, res) => {
  const naveId = Number(req.params.id);
  if (!Number.isInteger(naveId) || naveId <= 0) throw new ApiError(400, 'ID de nave inválido.');

  const nave = await NavesModel.obtenerPorId(naveId);
  if (!nave) throw new ApiError(404, 'Nave no encontrada.');

  const avances = await NavesModel.listarAvances(naveId);
  const resumen = construirResumen(nave, await NavesModel.resumenAvances(naveId));
  res.json({ success: true, data: { nave, avances, resumen } });
});

// GET /api/operaciones/naves/:id  → nave (incl. datos_adicionales) + campos de su tipo
export const obtenerNave = asyncHandler(async (req, res) => {
  const naveId = Number(req.params.id);
  if (!Number.isInteger(naveId) || naveId <= 0) throw new ApiError(400, 'ID de nave inválido.');
  const nave = await NavesModel.obtenerPorId(naveId);
  if (!nave) throw new ApiError(404, 'Nave no encontrada.');
  const campos = await CamposModel.listarPorTipo(nave.tipo_nave_id);
  res.json({ success: true, data: { nave, campos } });
});

// PUT /api/operaciones/naves/:id/datos  (Admin/Supervisor) — reemplaza datos, exige requeridos
export const actualizarDatos = asyncHandler(async (req, res) => {
  const naveId = Number(req.params.id);
  if (!Number.isInteger(naveId) || naveId <= 0) throw new ApiError(400, 'ID de nave inválido.');
  const nave = await NavesModel.obtenerPorId(naveId);
  if (!nave) throw new ApiError(404, 'Nave no encontrada.');
  const campos = await CamposModel.listarPorTipo(nave.tipo_nave_id);
  const datos = validarDatos(campos, req.body?.datos_adicionales ?? {}, { requireAll: true });
  const actualizada = await NavesModel.setDatos(naveId, datos);
  res.json({ success: true, data: actualizada });
});
