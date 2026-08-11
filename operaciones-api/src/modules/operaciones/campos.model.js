import { pool } from '../../config/db.js';
import { parseJsonColumn } from '../../utils/json.js';

// `opciones` es columna JSON → se normaliza a array en lectura (compat. MariaDB).
function mapCampo(row) {
  if (!row) return null;
  return { ...row, opciones: parseJsonColumn(row.opciones) };
}

// Acceso a datos del catálogo de campos por tipo de nave.
export const CamposModel = {
  async listarPorTipo(tipoNaveId, { soloActivos = true } = {}) {
    const [rows] = await pool.query(
      `SELECT id, tipo_nave_id, clave, etiqueta, tipo_dato, requerido, opciones, orden, activo
         FROM campos_tipo_nave
        WHERE tipo_nave_id = ? ${soloActivos ? 'AND activo = 1' : ''}
        ORDER BY orden, id`,
      [tipoNaveId],
    );
    return rows.map(mapCampo);
  },

  async obtenerPorId(id) {
    const [rows] = await pool.query('SELECT * FROM campos_tipo_nave WHERE id = ?', [id]);
    return mapCampo(rows[0]);
  },

  async crear({ tipo_nave_id, clave, etiqueta, tipo_dato, requerido, opciones, orden }) {
    const [r] = await pool.query(
      `INSERT INTO campos_tipo_nave (tipo_nave_id, clave, etiqueta, tipo_dato, requerido, opciones, orden)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [
        tipo_nave_id, clave, etiqueta, tipo_dato, requerido ? 1 : 0,
        opciones ? JSON.stringify(opciones) : null, orden ?? 0,
      ],
    );
    return this.obtenerPorId(r.insertId);
  },

  async actualizar(id, { etiqueta, tipo_dato, requerido, opciones, orden, activo }) {
    await pool.query(
      `UPDATE campos_tipo_nave
          SET etiqueta = ?, tipo_dato = ?, requerido = ?, opciones = ?, orden = ?, activo = ?
        WHERE id = ?`,
      [
        etiqueta, tipo_dato, requerido ? 1 : 0,
        opciones ? JSON.stringify(opciones) : null, orden ?? 0, activo ? 1 : 0, id,
      ],
    );
    return this.obtenerPorId(id);
  },

  async desactivar(id) {
    await pool.query('UPDATE campos_tipo_nave SET activo = 0 WHERE id = ?', [id]);
  },
};
