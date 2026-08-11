import { ApiError } from '../../utils/ApiError.js';

const UBIC_TIPOS = ['BERTH', 'YARD'];
const ESTADOS_POS = ['ACTIVE', 'INACTIVE', 'FINISH'];
const STATUS_ACT = ['Inicio', 'En Proceso', 'Culminado'];

// número >= 0 o null (acepta strings numéricos). Lanza si es inválido.
function numOpt(v, etq) {
  if (v === undefined || v === null || v === '') return null;
  const n = Number(v);
  if (!Number.isFinite(n) || n < 0) throw new ApiError(400, `${etq} debe ser un número ≥ 0.`);
  return n;
}

// entero positivo o null (ids opcionales como nave_id).
function entOpt(v) {
  if (v === undefined || v === null || v === '') return null;
  const n = Number(v);
  if (!Number.isInteger(n) || n <= 0) return null;
  return n;
}

// texto recortado o null.
function strOpt(v) {
  const s = String(v ?? '').trim();
  return s === '' ? null : s;
}

// Valida y normaliza el cuerpo de un registro de actividad tallyman.
export function parseRegistro(body) {
  const fecha_turno = String(body.fecha_turno ?? '').trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha_turno)) {
    throw new ApiError(400, 'fecha_turno debe ser YYYY-MM-DD.');
  }
  const turno = String(body.turno ?? '').trim();
  if (!turno) throw new ApiError(400, 'turno es obligatorio.');

  const ubicacion_tipo = String(body.ubicacion_tipo ?? '').trim().toUpperCase();
  if (!UBIC_TIPOS.includes(ubicacion_tipo)) {
    throw new ApiError(400, `ubicacion_tipo inválido. Use: ${UBIC_TIPOS.join(' o ')}.`);
  }
  const ubicacion = String(body.ubicacion ?? '').trim();
  if (!ubicacion) throw new ApiError(400, 'ubicacion es obligatoria.');

  const actividad_id = Number(body.actividad_id);
  if (!Number.isInteger(actividad_id) || actividad_id <= 0) {
    throw new ApiError(400, 'actividad_id inválido.');
  }

  const estado_pos = String(body.estado_pos ?? 'ACTIVE').trim().toUpperCase();
  if (!ESTADOS_POS.includes(estado_pos)) {
    throw new ApiError(400, `estado_pos inválido. Use: ${ESTADOS_POS.join(', ')}.`);
  }
  const status_act = String(body.status_act ?? 'Inicio').trim();
  if (!STATUS_ACT.includes(status_act)) {
    throw new ApiError(400, `status_act inválido. Use: ${STATUS_ACT.join(', ')}.`);
  }

  const executed = numOpt(body.executed, 'Executed');
  if (executed === null) throw new ApiError(400, 'Executed es obligatorio.');

  const planned = numOpt(body.planned, 'Planned');
  // En muelle, al iniciar una nave el planned es obligatorio: define el total
  // contra el que se mide el avance y se refleja en la nave de Operaciones.
  if (ubicacion_tipo === 'BERTH' && status_act === 'Inicio' && (planned === null || planned <= 0)) {
    throw new ApiError(400, 'El Planned es obligatorio cuando el status es Inicio.');
  }

  return {
    fecha_turno,
    turno,
    ubicacion_tipo,
    ubicacion,
    nave_id: entOpt(body.nave_id),
    nave_patio: strOpt(body.nave_patio),
    actividad_id,
    estado_pos,
    status_act,
    planned,
    executed,
    directa: numOpt(body.directa, 'Descarga directa'),
    interna: numOpt(body.interna, 'Descarga interna'),
    productivity: numOpt(body.productivity, 'Productivity'),
    details: strOpt(body.details),
    // Manifiesto de carga (Yard) — opcionales.
    cargo_type: strOpt(body.cargo_type),
    bl: strOpt(body.bl),
    producto: strOpt(body.producto),
    unidades: numOpt(body.unidades, 'Unidades'),
    tons: numOpt(body.tons, 'Toneladas'),
    ubic_codigo: strOpt(body.ubic_codigo),
    coord_entrante: strOpt(body.coord_entrante),
    coord_saliente: strOpt(body.coord_saliente),
  };
}
