// Envuelve controladores async y enruta cualquier rechazo al errorHandler.
export const asyncHandler = (fn) => (req, res, next) =>
  Promise.resolve(fn(req, res, next)).catch(next);
