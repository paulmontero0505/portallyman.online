import { pool } from '../../config/db.js';
import { parseJsonColumn } from '../../utils/json.js';

// Columnas de nave (con el tipo y la actividad resueltos por JOIN a sus catálogos).
const COLS = `n.id, n.nombre, n.muelle, n.tipo_nave_id, t.nombre AS tipo_nave,
              n.actividad_id, ta.nombre AS actividad,
              n.eta, n.etb, n.etd, n.estado, n.datos_adicionales, n.created_at, n.updated_at`;

// `datos_adicionales` es columna JSON → se normaliza a objeto en lectura.
function mapNave(row) {
  if (!row) return null;
  return { ...row, datos_adicionales: parseJsonColumn(row.datos_adicionales) };
}

// `bodegas` (detalle de TM por bodega) es columna JSON → se normaliza en lectura.
function mapAvance(row) {
  if (!row) return null;
  return { ...row, bodegas: parseJsonColumn(row.bodegas) };
}

// Acceso a datos del módulo. Todo el SQL vive aquí (consultas parametrizadas).
export const NavesModel = {
  async tipoExiste(tipoNaveId) {
    const [rows] = await pool.query(
      'SELECT id FROM tipos_nave WHERE id = ? AND activo = 1 LIMIT 1',
      [tipoNaveId],
    );
    return rows.length > 0;
  },

  async listarTipos() {
    const [rows] = await pool.query(
      'SELECT id, nombre FROM tipos_nave WHERE activo = 1 ORDER BY nombre',
    );
    return rows;
  },

  // Valida que la actividad (catálogo tallyman) exista y esté activa.
  async actividadExiste(actividadId) {
    const [rows] = await pool.query(
      'SELECT id FROM tallyman_actividades WHERE id = ? AND activo = 1 LIMIT 1',
      [actividadId],
    );
    return rows.length > 0;
  },

  async crear({ nombre, muelle, tipo_nave_id, actividad_id, eta, etb, etd, estado, datos_adicionales }) {
    const [r] = await pool.query(
      `INSERT INTO naves (nombre, muelle, tipo_nave_id, actividad_id, eta, etb, etd, estado, datos_adicionales)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        nombre, muelle ?? null, tipo_nave_id, actividad_id ?? null,
        eta ?? null, etb ?? null, etd ?? null, estado || 'Programada',
        datos_adicionales ? JSON.stringify(datos_adicionales) : null,
      ],
    );
    return this.obtenerPorId(r.insertId);
  },

  // Actualiza los campos base de la nave (no toca datos_adicionales,
  // que se editan aparte vía setDatos).
  async actualizar(id, { nombre, muelle, tipo_nave_id, actividad_id, eta, etb, etd, estado }) {
    await pool.query(
      `UPDATE naves
          SET nombre = ?, muelle = ?, tipo_nave_id = ?, actividad_id = ?, eta = ?, etb = ?, etd = ?, estado = ?
        WHERE id = ?`,
      [
        nombre, muelle ?? null, tipo_nave_id, actividad_id ?? null,
        eta ?? null, etb ?? null, etd ?? null, estado || 'Programada', id,
      ],
    );
    if (muelle) {
      await pool.query(
        `UPDATE tallyman_registros SET ubicacion = ? WHERE nave_id = ? AND ubicacion_tipo = 'BERTH'`,
        [muelle, id],
      );
    }
    return this.obtenerPorId(id);
  },

  async obtenerPorId(id) {
    const [rows] = await pool.query(
      `SELECT ${COLS}
         FROM naves n
         JOIN tipos_nave t ON t.id = n.tipo_nave_id
         LEFT JOIN tallyman_actividades ta ON ta.id = n.actividad_id
        WHERE n.id = ?`,
      [id],
    );
    return mapNave(rows[0]);
  },

  async listar({ estado } = {}) {
    const params = [];
    let where;
    if (estado) {
      where = 'WHERE n.estado = ?';
      params.push(estado);
    } else {
      where = "WHERE n.estado IN ('Programada','En Puerto','En Operaciones')"; // por defecto: activas/en operación
    }
    const [rows] = await pool.query(
      `SELECT ${COLS}
         FROM naves n
         JOIN tipos_nave t ON t.id = n.tipo_nave_id
         LEFT JOIN tallyman_actividades ta ON ta.id = n.actividad_id
         ${where}
        ORDER BY (n.eta IS NULL), n.eta ASC, n.id DESC`,
      params,
    );
    return rows.map(mapNave);
  },

  async setDatos(naveId, datos) {
    await pool.query(
      'UPDATE naves SET datos_adicionales = ? WHERE id = ?',
      [datos ? JSON.stringify(datos) : null, naveId],
    );
    return this.obtenerPorId(naveId);
  },

  // Actualiza solo los campos operativos provistos (etb/etd/estado) sin tocar
  // nombre/tipo/actividad/eta. Usado por la sincronización desde el registro
  // tallyman de muelle (Inicio → etb/En Puerto, Culminado → etd/Finalizada).
  async actualizarOperativo(naveId, { etb, etd, estado } = {}) {
    const sets = [];
    const params = [];
    if (etb !== undefined)    { sets.push('etb = ?');    params.push(etb); }
    if (etd !== undefined)    { sets.push('etd = ?');    params.push(etd); }
    if (estado !== undefined) { sets.push('estado = ?'); params.push(estado); }
    if (!sets.length) return this.obtenerPorId(naveId);
    params.push(naveId);
    await pool.query(`UPDATE naves SET ${sets.join(', ')} WHERE id = ?`, params);
    return this.obtenerPorId(naveId);
  },

  // Mezcla `patch` dentro de datos_adicionales (preserva las demás claves del
  // operativo). Usado para reflejar el planned (cantidad_total) en la nave.
  async mergeDatos(naveId, patch) {
    const nave = await this.obtenerPorId(naveId);
    if (!nave) return null;
    const datos = { ...(nave.datos_adicionales || {}), ...patch };
    return this.setDatos(naveId, datos);
  },

  // Elimina la nave. Sus avances caen por la FK ON DELETE CASCADE.
  // Devuelve true si existía (se borró alguna fila).
  async eliminar(id) {
    const [r] = await pool.query('DELETE FROM naves WHERE id = ?', [id]);
    return r.affectedRows > 0;
  },

  // ── Avances / reportes de turno ──
  async agregarAvance(naveId, a) {
    const [r] = await pool.query(
      `INSERT INTO avances_nave
         (nave_id, fecha_turno, turno, directa_tm, indirecta_tm, despacho_tm, bodegas, descripcion_avance, registrado_por)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        naveId, a.fecha_turno ?? null, a.turno ?? null,
        a.directa_tm ?? null, a.indirecta_tm ?? null, a.despacho_tm ?? null,
        a.bodegas ? JSON.stringify(a.bodegas) : null,
        a.descripcion_avance ?? null, a.registrado_por,
      ],
    );
    return this.obtenerAvance(r.insertId);
  },

  async obtenerAvance(id) {
    const [rows] = await pool.query('SELECT * FROM avances_nave WHERE id = ?', [id]);
    return mapAvance(rows[0]);
  },

  async editarAvance(id, a) {
    await pool.query(
      `UPDATE avances_nave
          SET fecha_turno = ?, turno = ?, directa_tm = ?, indirecta_tm = ?,
              despacho_tm = ?, bodegas = ?, descripcion_avance = ?
        WHERE id = ?`,
      [
        a.fecha_turno ?? null, a.turno ?? null,
        a.directa_tm ?? null, a.indirecta_tm ?? null, a.despacho_tm ?? null,
        a.bodegas ? JSON.stringify(a.bodegas) : null,
        a.descripcion_avance ?? null, id,
      ],
    );
    return this.obtenerAvance(id);
  },

  async eliminarAvance(id) {
    await pool.query('DELETE FROM avances_nave WHERE id = ?', [id]);
  },

  async listarAvances(naveId) {
    const [rows] = await pool.query(
      `SELECT id, nave_id, fecha_turno, turno, directa_tm, indirecta_tm, despacho_tm,
              bodegas, descripcion_avance, registrado_por, fecha_registro
         FROM avances_nave
        WHERE nave_id = ?
        ORDER BY fecha_turno ASC, (turno = 'Noche'), fecha_registro ASC, id ASC`,
      [naveId],
    );
    return rows.map(mapAvance);
  },

  // Acumulados por flujo de una nave (suma de todos sus turnos).
  // `bodegas_tm` suma las TM del detalle por bodega (cuando se reportó así).
  async resumenAvances(naveId) {
    const [rows] = await pool.query(
      `SELECT
          COALESCE(SUM(directa_tm), 0)   AS directa,
          COALESCE(SUM(indirecta_tm), 0) AS indirecta,
          COALESCE(SUM(despacho_tm), 0)  AS despacho,
          COUNT(*)                       AS reportes
         FROM avances_nave
        WHERE nave_id = ?`,
      [naveId],
    );
    // TM por bodega: se suma recorriendo el JSON de cada reporte que lo tenga.
    const [bRows] = await pool.query(
      `SELECT bodegas FROM avances_nave WHERE nave_id = ? AND bodegas IS NOT NULL`,
      [naveId],
    );
    let bodegasTm = 0;
    for (const br of bRows) {
      const arr = parseJsonColumn(br.bodegas);
      if (Array.isArray(arr)) {
        for (const b of arr) bodegasTm += Number(b?.tm) || 0;
      }
    }
    const r = rows[0] || {};
    return {
      directa: Number(r.directa) || 0,
      indirecta: Number(r.indirecta) || 0,
      despacho: Number(r.despacho) || 0,
      bodegas_tm: bodegasTm,
      reportes: Number(r.reportes) || 0,
    };
  },
};
