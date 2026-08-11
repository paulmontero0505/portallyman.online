// Error con código HTTP, para que el errorHandler responda con el status correcto.
export class ApiError extends Error {
  constructor(status, message) {
    super(message);
    this.status = status;
  }
}
