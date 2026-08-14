import { asyncHandler } from '../../utils/asyncHandler.js';
import { ApiError } from '../../utils/ApiError.js';
import { TallymanModel } from './tallyman.model.js';
import { NavesModel } from '../operaciones/naves.model.js';
import { parseRegistro } from './tallyman.validator.js';
import { calcularTotales } from './tallyman.totales.js';

const esFechaISO = (s) => /^\d{4}-\d{2}-\d{2}$/.test(s);

// Formatea una fecha JS a DATETIME de MySQL ('YYYY-MM-DD HH:MM:SS', hora local).
function toMysqlDateTime(d) {
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ` +
         `${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
}

// Clave de `datos_adicionales` donde se refleja el planned (total operativo) según
// el tipo de nave, para que Operaciones lo muestre con la unidad correcta:
//   · Ro-Ro      → "vehiculos"   · Containera → "teus"   · resto → "cantidad_total" (TM)
const PLANNED_KEY_POR_TIPO = { 'ro-ro': 'vehiculos', 'containera': 'teus', 'portacontenedores': 'teus' };
function plannedKeyPorTipo(tipoNombre) {
  return PLANNED_KEY_POR_TIPO[String(tipoNombre || '').trim().toLowerCase()] || 'cantidad_total';
}

// Sincroniza la nave de Operaciones con el estado del registro de muelle:
//   · Inicio    → ETB/ATB = fecha operativa, estado = En Operaciones (+ planned).
//   · Culminado → ETD/ATD = fecha operativa, estado = Finalizada.
// La fecha operativa viene de `opFecha` (fecha del turno + hora, 'YYYY-MM-DD HH:MM:SS');
// si no llega, se usa el momento actual. Solo aplica a BERTH con nave real (nave_id).
async function sincronizarNaveBerth(reg, opFecha) {
  if (!reg || reg.ubicacion_tipo !== 'BERTH' || reg.nave_id == null) return;
  const ts = (typeof opFecha === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(opFecha))
    ? opFecha
    : toMysqlDateTime(new Date());
  if (reg.status_act === 'Inicio') {
    // El atraque se refleja en `eta` (columna que el formulario de Operaciones usa
    // para "ETB/ATB") y en `etb` por consistencia con la vista/Gantt (etb || eta).
    await NavesModel.actualizarOperativo(reg.nave_id, { eta: ts, etb: ts, estado: 'En Operaciones' });
    if (reg.planned != null) {
      await NavesModel.mergeDatos(reg.nave_id, { [plannedKeyPorTipo(reg.tipo_nave)]: Number(reg.planned) });
    }
  } else if (reg.status_act === 'Culminado') {
    await NavesModel.actualizarOperativo(reg.nave_id, { etd: ts, estado: 'Finalizada' });
  }
}

// Genera/actualiza automáticamente la actividad de PATIO "Big Bags Dispatch" para
// las naves tipo Cementero, a partir del DESPACHO INDIRECTO (carga a camión) que se
// registró en muelle. Reglas:
//   · Solo BERTH con nave real cuyo tipo de nave sea "Cementero".
//   · El despacho indirecto se guarda en la columna `directa` del registro de muelle.
//   · planned del patio = planned del registro de muelle; executed = despacho indirecto.
//   · Un despacho por nave y turno: si ya existe se actualiza (no se duplica al editar).
//   · Status: "Inicio" la primera vez (sin registros previos o ciclo Culminado),
//     "En Proceso" si ya hay un registro abierto de esa nave+actividad.
async function sincronizarDespachoCementero(berthReg) {
  if (!berthReg || berthReg.ubicacion_tipo !== 'BERTH' || berthReg.nave_id == null) return;

  // Disparadores del despacho a patio:
  //  · Actividad "Cement Big Bags Loading/Discharge" (nuevo criterio por actividad).
  //  · Tipo de nave "Cementero" (legado, se mantiene como estaba).
  const actNombre = String(berthReg.actividad || '').trim().toLowerCase();
  const esCementBigBags = actNombre.indexOf('cement big bags loading') === 0;
  const esTipoCementero = String(berthReg.tipo_nave || '').trim().toLowerCase() === 'cementero';
  if (!esCementBigBags && !esTipoCementero) return;

  // Actividad de patio destino: la actividad Cement Big Bags genera "Cement Big Bags
  // Dispatch"; el tipo de nave Cementero (legado) sigue generando "Big Bags Dispatch".
  const dispatchNombre = esCementBigBags
    ? 'Cement Big Bags Dispatch (Despacho de Cemento en Big Bags)'
    : 'Big Bags Dispatch (Despacho de Big Bags)';
  const dispatchActId = await TallymanModel.actividadIdPorNombre(dispatchNombre);
  if (!dispatchActId) return; // catálogo sin esa actividad → no se genera nada

  const despacho = berthReg.directa != null ? Number(berthReg.directa) : 0;
  const planned = berthReg.planned != null ? Number(berthReg.planned) : null;
  const existenteId = await TallymanModel.buscarRegistroTurno({
    fecha_turno: berthReg.fecha_turno, turno: berthReg.turno,
    ubicacion_tipo: 'YARD', nave_id: berthReg.nave_id, actividad_id: dispatchActId,
  });

  // ── Caso Cement Big Bags (por actividad): contenedor de patio ──
  // El patio nace con executed = 0 (lo llena el tallyman) y su planned es lo que resta
  // por despachar = planned de muelle − acumulado de despacho indirecto (directa).
  if (esCementBigBags) {
    const sumaDirecta = await TallymanModel.sumaDirectaMuelle({
      nave_id: berthReg.nave_id, actividad_id: berthReg.actividad_id,
    });
    const plannedPatio = planned != null ? Math.max(planned - sumaDirecta, 0) : null;
    if (existenteId) {
      // Solo se actualiza el planned; no se pisa el executed que ingresó el operador.
      if (plannedPatio != null) {
        await TallymanModel.propagarPlannedYard({
          nave_id: berthReg.nave_id, actividad_id: dispatchActId, planned: plannedPatio,
        });
      }
    } else {
      const ultimo = await TallymanModel.ultimoStatus({ nave_id: berthReg.nave_id, actividad_id: dispatchActId });
      const status = (!ultimo || ultimo === 'Culminado') ? 'Inicio' : 'En Proceso';
      await TallymanModel.crearRegistro({
        fecha_turno: berthReg.fecha_turno, turno: berthReg.turno,
        ubicacion_tipo: 'YARD', ubicacion: 'Yard',
        nave_id: berthReg.nave_id, nave_patio: null,
        actividad_id: dispatchActId, estado_pos: 'ACTIVE', status_act: status,
        planned: plannedPatio, executed: 0,
        details: 'Generado automáticamente desde muelle (despacho indirecto · Cement Big Bags).',
        registrado_por: berthReg.registrado_por,
      });
    }
    return;
  }

  // ── Caso Cementero legado (por tipo de nave): executed = despacho directo ──
  if (despacho > 0) {
    if (existenteId) {
      await TallymanModel.actualizarEjecutadoPlanned(existenteId, {
        executed: despacho, planned, nave_id: berthReg.nave_id, actividad_id: dispatchActId,
      });
    } else {
      const ultimo = await TallymanModel.ultimoStatus({ nave_id: berthReg.nave_id, actividad_id: dispatchActId });
      const status = (!ultimo || ultimo === 'Culminado') ? 'Inicio' : 'En Proceso';
      await TallymanModel.crearRegistro({
        fecha_turno: berthReg.fecha_turno, turno: berthReg.turno,
        ubicacion_tipo: 'YARD', ubicacion: 'Yard',
        nave_id: berthReg.nave_id, nave_patio: null,
        actividad_id: dispatchActId, estado_pos: 'ACTIVE', status_act: status,
        planned, executed: despacho,
        details: 'Generado automáticamente desde muelle (descarga directa).',
        registrado_por: berthReg.registrado_por,
      });
    }
  } else if (existenteId) {
    // En edición se quitó el despacho: dejar el patio autogenerado en 0 (no se borra).
    await TallymanModel.actualizarEjecutadoPlanned(existenteId, {
      executed: 0, planned, nave_id: berthReg.nave_id, actividad_id: dispatchActId,
    });
  }
}

// Mapa: tipo de nave (en minúsculas) → actividad de PATIO que se autogenera a partir
// de la DESCARGA INTERNA (descarga a piso) registrada en muelle. Para añadir un nuevo
// tipo basta con agregar su entrada aquí (la actividad debe existir en el catálogo).
const DISPATCH_INTERNA_POR_TIPO = {
  'carga general': 'General Cargo Dispatch (Despacho de Carga General)',
  'granelera': 'Corn Dispatch (Despacho de Maíz)',
  // Ro-Ro: el "Tránsito Aduanero" se registra en la descarga interna del muelle y
  // genera automáticamente la actividad de patio "Car Dispatch" (executed = 0).
  'ro-ro': 'Car Dispatch (Despacho de Vehículos)',
};

// Genera automáticamente la actividad de PATIO de despacho para las naves cuyo tipo
// esté en DISPATCH_INTERNA_POR_TIPO, a partir de la DESCARGA INTERNA del muelle. Reglas:
//   · Solo BERTH con nave real cuyo tipo de nave esté mapeado.
//   · La descarga interna se guarda en la columna `interna` del registro de muelle.
//   · planned del patio = acumulado de descarga interna entre turnos. Se deja en NULL
//     para que `obtenerRegistro`/`listarPorTurno` lo calculen en vivo como
//     SUM(interna) de los registros BERTH de esa nave (refleja todos los turnos).
//   · executed = 0: el registro nace como contenedor; el despacho real del patio lo
//     ingresa el operador manualmente. NO se sobrescribe si el registro ya existe.
//   · Status: "Inicio" la primera vez (sin registros previos o ciclo Culminado),
//     "En Proceso" si ya hay un registro abierto de esa nave+actividad.
//   · Un despacho por nave y turno: si ya existe, se respeta (no se duplica ni se pisa).
async function sincronizarDespachoInterna(berthReg) {
  if (!berthReg || berthReg.ubicacion_tipo !== 'BERTH' || berthReg.nave_id == null) return;
  // La actividad "Cement Big Bags Loading/Discharge" es estilo Cementero: su interna es
  // avance de muelle (a piso) y su directa genera el patio; no lleva despacho por interna.
  if (String(berthReg.actividad || '').trim().toLowerCase().indexOf('cement big bags loading') === 0) return;
  const tipo = String(berthReg.tipo_nave || '').trim().toLowerCase();
  const actNombre = DISPATCH_INTERNA_POR_TIPO[tipo];
  if (!actNombre) return; // tipo de nave sin despacho de patio autogenerado

  const interna = berthReg.interna != null ? Number(berthReg.interna) : 0;
  if (!(interna > 0)) return; // sin descarga interna → no se genera nada

  const dispatchActId = await TallymanModel.actividadIdPorNombre(actNombre);
  if (!dispatchActId) return; // catálogo sin esa actividad → no se genera nada

  const existenteId = await TallymanModel.buscarRegistroTurno({
    fecha_turno: berthReg.fecha_turno, turno: berthReg.turno,
    ubicacion_tipo: 'YARD', nave_id: berthReg.nave_id, actividad_id: dispatchActId,
  });
  if (existenteId) return; // ya existe el contenedor de patio del turno → se respeta

  const ultimo = await TallymanModel.ultimoStatus({ nave_id: berthReg.nave_id, actividad_id: dispatchActId });
  const status = (!ultimo || ultimo === 'Culminado') ? 'Inicio' : 'En Proceso';
  await TallymanModel.crearRegistro({
    fecha_turno: berthReg.fecha_turno, turno: berthReg.turno,
    ubicacion_tipo: 'YARD', ubicacion: 'Yard',
    nave_id: berthReg.nave_id, nave_patio: null,
    actividad_id: dispatchActId, estado_pos: 'ACTIVE', status_act: status,
    planned: null, executed: 0,
    details: 'Generado automáticamente desde muelle (descarga interna).',
    registrado_por: berthReg.registrado_por,
  });
}

// Calcula acumulado y pendiente de un registro a partir del executed previo.
function conResumen(reg, prev) {
  const planned = reg.planned != null ? Number(reg.planned) : null;
  const accumulated = prev + Number(reg.executed);
  const pending = planned != null ? Math.max(planned - accumulated, 0) : null;
  const porcentaje = planned && planned > 0
    ? Math.min(Math.round((accumulated / planned) * 1000) / 10, 100)
    : null;
  return { ...reg, accumulated, pending, porcentaje };
}

// Obtiene el executed previo usando el método adecuado según ubicacion_tipo.
async function prevPara(r) {
  if (r.ubicacion_tipo === 'YARD') {
    return TallymanModel.executedPrevioYard({
      nave_id: r.nave_id, nave_patio: r.nave_patio, actividad_id: r.actividad_id,
      fecha_turno: r.fecha_turno, turno: r.turno,
    });
  }
  return TallymanModel.executedPrevio({
    nave_id: r.nave_id,
    fecha_turno: r.fecha_turno, turno: r.turno,
  });
}

// Convierte una lista de registros crudos en registros con accumulated/pending/%.
async function resumenLista(regs) {
  const out = [];
  for (const r of regs) {
    const prev = await prevPara(r);
    out.push(conResumen(r, prev));
  }
  return out;
}

// GET /tallyman/actividades
export const listarActividades = asyncHandler(async (_req, res) => {
  const data = await TallymanModel.listarActividades();
  res.json({ success: true, count: data.length, data });
});

// GET /tallyman/registros?fecha=&turno=
export const listarRegistros = asyncHandler(async (req, res) => {
  const fecha = String(req.query.fecha ?? '').trim();
  const turno = String(req.query.turno ?? '').trim();
  if (!esFechaISO(fecha) || !turno) {
    throw new ApiError(400, 'fecha (YYYY-MM-DD) y turno son obligatorios.');
  }
  const regs = await TallymanModel.listarPorTurno(fecha, turno);
  const out = await resumenLista(regs);
  res.json({ success: true, count: out.length, data: out });
});

// GET /tallyman/registros-rango?desde=&hasta=   (solo Administrador)
// Sin desde/hasta → todo el histórico. Para revisión transversal de turnos.
export const listarRegistrosRango = asyncHandler(async (req, res) => {
  const desde = String(req.query.desde ?? '').trim();
  const hasta = String(req.query.hasta ?? '').trim();
  if (desde && !esFechaISO(desde)) throw new ApiError(400, 'desde inválida (YYYY-MM-DD).');
  if (hasta && !esFechaISO(hasta)) throw new ApiError(400, 'hasta inválida (YYYY-MM-DD).');
  const regs = await TallymanModel.listarPorRango(desde || null, hasta || null);
  const out = await resumenLista(regs);
  res.json({ success: true, count: out.length, data: out });
});

// POST /tallyman/registros   (Coordinador)
export const crearRegistro = asyncHandler(async (req, res) => {
  const r = parseRegistro(req.body);
  if (!(await TallymanModel.actividadExiste(r.actividad_id))) {
    throw new ApiError(400, 'La actividad no existe o está inactiva.');
  }
  const creado = await TallymanModel.crearRegistro({ ...r, registrado_por: req.user.name });
  await sincronizarNaveBerth(creado, req.body.op_fecha);
  await sincronizarDespachoCementero(creado);
  await sincronizarDespachoInterna(creado);
  const prev = await prevPara(creado);
  res.status(201).json({ success: true, data: conResumen(creado, prev) });
});

// PUT /tallyman/registros/:id
export const editarRegistro = asyncHandler(async (req, res) => {
  const id = Number(req.params.id);
  if (!Number.isInteger(id) || id <= 0) throw new ApiError(400, 'ID inválido.');
  const existe = await TallymanModel.obtenerRegistro(id);
  if (!existe) throw new ApiError(404, 'Registro no encontrado.');
  const r = parseRegistro(req.body);
  if (!(await TallymanModel.actividadExiste(r.actividad_id))) {
    throw new ApiError(400, 'La actividad no existe o está inactiva.');
  }
  const upd = await TallymanModel.editarRegistro(id, r);
  await sincronizarNaveBerth(upd, req.body.op_fecha);
  await sincronizarDespachoCementero(upd);
  await sincronizarDespachoInterna(upd);
  const prev = await prevPara(upd);
  res.json({ success: true, data: conResumen(upd, prev) });
});

// DELETE /tallyman/registros/:id
export const eliminarRegistro = asyncHandler(async (req, res) => {
  const id = Number(req.params.id);
  if (!Number.isInteger(id) || id <= 0) throw new ApiError(400, 'ID inválido.');
  const existe = await TallymanModel.obtenerRegistro(id);
  if (!existe) throw new ApiError(404, 'Registro no encontrado.');
  await TallymanModel.eliminarRegistro(id);
  res.json({ success: true });
});

// POST /tallyman/incidencias   (Coordinador)
export const guardarIncidencia = asyncHandler(async (req, res) => {
  const fecha_turno = String(req.body.fecha_turno ?? '').trim();
  const turno = String(req.body.turno ?? '').trim();
  if (!esFechaISO(fecha_turno) || !turno) {
    throw new ApiError(400, 'fecha_turno (YYYY-MM-DD) y turno son obligatorios.');
  }
  const hubo = !!req.body.hubo;
  const detalle = String(req.body.detalle ?? '').trim() || null;
  if (hubo && !detalle) throw new ApiError(400, 'Si hubo incidente, el detalle es obligatorio.');
  const data = await TallymanModel.guardarIncidencia({
    fecha_turno, turno, hubo, detalle, registrado_por: req.user.name,
  });
  res.status(201).json({ success: true, data });
});

// GET /tallyman/incidencias?fecha=&turno=
export const obtenerIncidencia = asyncHandler(async (req, res) => {
  const fecha = String(req.query.fecha ?? '').trim();
  const turno = String(req.query.turno ?? '').trim();
  if (!esFechaISO(fecha) || !turno) {
    throw new ApiError(400, 'fecha (YYYY-MM-DD) y turno son obligatorios.');
  }
  const data = await TallymanModel.obtenerIncidencia(fecha, turno);
  res.json({ success: true, data });
});

// GET /tallyman/estado-sugerido?nave_id=&actividad_id=
// Sugiere el status del próximo registro según el historial de esa nave+actividad:
//   · sin registros, o el último fue "Culminado"  → "Inicio" (empieza un ciclo nuevo)
//   · existe un registro abierto (Inicio/En Proceso) → "En Proceso"
// "Culminado" siempre lo elige manualmente quien registra (cierra el ciclo).
export const estadoSugerido = asyncHandler(async (req, res) => {
  const naveId = req.query.nave_id ? Number(req.query.nave_id) : null;
  const navePatio = req.query.nave_patio ? String(req.query.nave_patio).trim() : null;
  const actividadId = Number(req.query.actividad_id);
  const ubicacion = req.query.ubicacion ? String(req.query.ubicacion).trim() : null;
  const fechaTurno = req.query.fecha_turno ? String(req.query.fecha_turno).trim() : null;
  const turno = req.query.turno ? String(req.query.turno).trim() : null;
  if (!Number.isInteger(actividadId) || actividadId <= 0) {
    throw new ApiError(400, 'actividad_id es obligatorio.');
  }
  const ultimo = navePatio
    ? await TallymanModel.ultimoStatus({ nave_patio: navePatio, actividad_id: actividadId })
    : await TallymanModel.ultimoStatusBerth(naveId);
  const status = (!ultimo || ultimo === 'Culminado') ? 'Inicio' : 'En Proceso';
  // Acumulado previo (executed de turnos anteriores, excluyendo el turno actual) para
  // mostrar el avance del turno anterior y la proyección al ingresar datos.
  let acumulado = 0;
  if (fechaTurno && turno) {
    try {
      if (navePatio) {
        acumulado = await TallymanModel.executedPrevioYard({ nave_id: naveId, nave_patio: navePatio, actividad_id: actividadId, fecha_turno: fechaTurno, turno });
      } else if (naveId) {
        acumulado = await TallymanModel.executedPrevio({ nave_id: naveId, fecha_turno: fechaTurno, turno });
      }
    } catch (e) { acumulado = 0; }
  }
  res.json({ success: true, data: { status, ultimo: ultimo || null, acumulado } });
});

// GET /tallyman/naves-patio
// Devuelve naves con descarga interna acumulada (planned sugerido para patio) +
// nombres de texto activos del formulario YARD que aún no son Culminado.
export const navesPatioHandler = asyncHandler(async (_req, res) => {
  const naves = await TallymanModel.navesConInterna();
  const nombres_patio = await TallymanModel.nombresPatioActivos();
  res.json({ success: true, data: { naves, nombres_patio } });
});

// GET /tallyman/relevo?fecha=&turno=  → payload completo para la vista de relevo
export const relevoTurno = asyncHandler(async (req, res) => {
  const fecha = String(req.query.fecha ?? '').trim();
  const turno = String(req.query.turno ?? '').trim();
  if (!esFechaISO(fecha) || !turno) {
    throw new ApiError(400, 'fecha (YYYY-MM-DD) y turno son obligatorios.');
  }
  const registros = await resumenLista(await TallymanModel.listarPorTurno(fecha, turno));
  const incidencia = await TallymanModel.obtenerIncidencia(fecha, turno);
  const totales = calcularTotales(registros);
  // coordinadores: tomados del primer registro que los tenga (se guardan por registro)
  const coord = registros.find((r) => r.coord_entrante || r.coord_saliente) || {};
  res.json({
    success: true,
    data: {
      fecha, turno,
      coord_entrante: coord.coord_entrante || null,
      coord_saliente: coord.coord_saliente || null,
      registros, incidencia, totales,
    },
  });
});
