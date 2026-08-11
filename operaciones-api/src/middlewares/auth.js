import { ApiError } from '../utils/ApiError.js';

// FASE 1: autenticación SIMULADA. La identidad llega por headers
// (x-user-id, x-user-role, x-user-name). En producción se reemplaza por
// verificación real de sesión/JWT — sin tocar los controladores.
// El nombre puede venir URL-encoded (el proxy PHP lo codifica para no romper
// headers con acentos/espacios). Se decodifica de forma segura.
function safeDecode(v) {
  if (!v) return null;
  try { return decodeURIComponent(v); } catch { return v; }
}

export function simulatedAuth(req, _res, next) {
  req.user = {
    id: req.header('x-user-id') || null,
    role: req.header('x-user-role') || null,
    name: safeDecode(req.header('x-user-name')) || req.header('x-user-id') || 'desconocido',
  };
  next();
}

// Autoriza solo si el rol del usuario está en la lista permitida.
export function requireRole(...roles) {
  return (req, _res, next) => {
    if (!req.user?.role) return next(new ApiError(401, 'No autenticado.'));
    if (!roles.includes(req.user.role)) {
      return next(new ApiError(403, `Acceso denegado. Requiere rol: ${roles.join(' o ')}.`));
    }
    next();
  };
}
