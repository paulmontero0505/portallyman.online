import { asyncHandler } from '../../utils/asyncHandler.js';
import { ApiError } from '../../utils/ApiError.js';
import { CamposModel } from './campos.model.js';
import { NavesModel } from './naves.model.js';
import { TIPOS } from './campos.validator.js';

const CLAVE_RE = /^[a-z][a-z0-9_]*$/;

async function assertTipoExiste(tipoId) {
  if (!(await NavesModel.tipoExiste(tipoId))) throw new ApiError(404, 'Tipo de nave no encontrado.');
}

function validarDefinicion({ clave, etiqueta, tipo_dato, opciones }, { claveRequerida }) {
  if (claveRequerida && (!clave || !CLAVE_RE.test(clave))) {
    throw new ApiError(400, "La clave es obligatoria y debe ser [a-z][a-z0-9_]* (ej. 'teus').");
  }
  if (!etiqueta?.trim()) throw new ApiError(400, 'La etiqueta es obligatoria.');
  if (!TIPOS.includes(tipo_dato)) throw new ApiError(400, `tipo_dato inválido. Use: ${TIPOS.join(', ')}.`);
  if (tipo_dato === 'seleccion' && (!Array.isArray(opciones) || opciones.length === 0)) {
    throw new ApiError(400, "tipo_dato 'seleccion' requiere 'opciones' (array no vacío).");
  }
}

// GET /tipos-nave/:tipoId/campos
export const listarCampos = asyncHandler(async (req, res) => {
  const tipoId = Number(req.params.tipoId);
  await assertTipoExiste(tipoId);
  const campos = await CamposModel.listarPorTipo(tipoId);
  res.json({ success: true, count: campos.length, data: campos });
});

// POST /tipos-nave/:tipoId/campos   (Administrador)
export const crearCampo = asyncHandler(async (req, res) => {
  const tipoId = Number(req.params.tipoId);
  await assertTipoExiste(tipoId);
  const { clave, etiqueta, tipo_dato, requerido, opciones, orden } = req.body;
  validarDefinicion({ clave, etiqueta, tipo_dato, opciones }, { claveRequerida: true });
  try {
    const campo = await CamposModel.crear({ tipo_nave_id: tipoId, clave, etiqueta, tipo_dato, requerido, opciones, orden });
    res.status(201).json({ success: true, data: campo });
  } catch (e) {
    if (e.code === 'ER_DUP_ENTRY') throw new ApiError(409, `Ya existe un campo con clave '${clave}' en este tipo.`);
    throw e;
  }
});

// PUT /tipos-nave/:tipoId/campos/:campoId   (Administrador)
export const actualizarCampo = asyncHandler(async (req, res) => {
  const tipoId = Number(req.params.tipoId);
  const campoId = Number(req.params.campoId);
  const campo = await CamposModel.obtenerPorId(campoId);
  if (!campo || campo.tipo_nave_id !== tipoId) throw new ApiError(404, 'Campo no encontrado.');
  const { etiqueta, tipo_dato, requerido, opciones, orden, activo } = req.body;
  validarDefinicion({ etiqueta, tipo_dato, opciones }, { claveRequerida: false });
  const upd = await CamposModel.actualizar(campoId, {
    etiqueta,
    tipo_dato,
    requerido,
    opciones,
    orden,
    activo: activo === undefined ? campo.activo : activo,
  });
  res.json({ success: true, data: upd });
});

// DELETE /tipos-nave/:tipoId/campos/:campoId   (Administrador) — soft delete
export const desactivarCampo = asyncHandler(async (req, res) => {
  const tipoId = Number(req.params.tipoId);
  const campoId = Number(req.params.campoId);
  const campo = await CamposModel.obtenerPorId(campoId);
  if (!campo || campo.tipo_nave_id !== tipoId) throw new ApiError(404, 'Campo no encontrado.');
  await CamposModel.desactivar(campoId);
  res.json({ success: true });
});
