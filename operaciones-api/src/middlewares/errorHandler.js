export function notFound(_req, res) {
  res.status(404).json({ success: false, error: 'Recurso no encontrado.' });
}

// Los 4 argumentos (incluido _next) son obligatorios: así Express reconoce esta
// función como middleware de manejo de errores.
export function errorHandler(err, _req, res, _next) {
  const status = err.status || 500;
  if (status >= 500) console.error(err);
  res.status(status).json({ success: false, error: err.message || 'Error interno del servidor.' });
}
