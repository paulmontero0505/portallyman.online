import mysql from 'mysql2/promise';

// Pool de conexiones. Lee la config de process.env (cargada por dotenv en server.js).
export const pool = mysql.createPool({
  host: process.env.DB_HOST || '127.0.0.1',
  port: Number(process.env.DB_PORT) || 3306,
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'operaciones',
  connectionLimit: Number(process.env.DB_CONNECTION_LIMIT) || 10,
  dateStrings: true, // DATETIME/TIMESTAMP como 'YYYY-MM-DD HH:MM:SS' (sin sorpresas de zona horaria)
});

// Falla rápido al arrancar si la BD no responde.
export async function assertDbConnection() {
  const conn = await pool.getConnection();
  try {
    await conn.ping();
  } finally {
    conn.release();
  }
}
