import { ApiError } from '../../utils/ApiError.js';

export const TIPOS = ['texto', 'numero', 'fecha', 'booleano', 'seleccion'];

function esFechaISO(v) {
  if (typeof v !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(v)) return false;
  const d = new Date(v + 'T00:00:00');
  return !Number.isNaN(d.getTime()) && v === d.toISOString().slice(0, 10);
}

// Valida y normaliza `datos` contra las definiciones activas `campos`.
// requireAll: exige los campos `requerido`. Devuelve el objeto normalizado a guardar.
export function validarDatos(campos, datos, { requireAll = false } = {}) {
  const obj = datos && typeof datos === 'object' && !Array.isArray(datos) ? datos : {};
  const porClave = new Map(campos.map((c) => [c.clave, c]));

  for (const k of Object.keys(obj)) {
    if (!porClave.has(k)) throw new ApiError(400, `Campo desconocido: ${k}`);
  }

  const out = {};
  for (const c of campos) {
    const tiene = Object.prototype.hasOwnProperty.call(obj, c.clave);
    const v = obj[c.clave];
    const vacio = !tiene || v === null || v === undefined || v === '';

    if (vacio) {
      if (requireAll && c.requerido) throw new ApiError(400, `Campo requerido: ${c.clave}`);
      continue;
    }

    switch (c.tipo_dato) {
      case 'texto':
        out[c.clave] = String(v);
        break;
      case 'numero': {
        const n = Number(v);
        if (!Number.isFinite(n)) throw new ApiError(400, `Campo '${c.clave}' debe ser numérico.`);
        out[c.clave] = n;
        break;
      }
      case 'fecha':
        if (!esFechaISO(v)) throw new ApiError(400, `Campo '${c.clave}' debe ser fecha YYYY-MM-DD.`);
        out[c.clave] = v;
        break;
      case 'booleano':
        if (v === true || v === 1 || v === '1' || v === 'true') out[c.clave] = true;
        else if (v === false || v === 0 || v === '0' || v === 'false') out[c.clave] = false;
        else throw new ApiError(400, `Campo '${c.clave}' debe ser booleano.`);
        break;
      case 'seleccion': {
        const ops = Array.isArray(c.opciones) ? c.opciones : [];
        if (!ops.includes(v)) throw new ApiError(400, `Campo '${c.clave}': valor no permitido.`);
        out[c.clave] = v;
        break;
      }
      default:
        throw new ApiError(400, `Tipo de dato inválido en '${c.clave}'.`);
    }
  }
  return out;
}
