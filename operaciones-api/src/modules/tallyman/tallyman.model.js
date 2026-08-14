import { pool } from '../../config/db.js';

export const TallymanModel = {
  // Catálogo de actividades activas.
  async listarActividades() {
    const [rows] = await pool.query(
      'SELECT id, nombre FROM tallyman_actividades WHERE activo = 1 ORDER BY orden, nombre',
    );
    return rows;
  },

  async actividadExiste(id) {
    const [rows] = await pool.query(
      'SELECT id FROM tallyman_actividades WHERE id = ? AND activo = 1 LIMIT 1',
      [id],
    );
    return rows.length > 0;
  },

  // Id de una actividad por nombre exacto (activa). Usado para resolver
  // "Big Bags Dispatch (Despacho de Big Bags)" al generar la actividad de patio del cementero.
  async actividadIdPorNombre(nombre) {
    const [rows] = await pool.query(
      'SELECT id FROM tallyman_actividades WHERE nombre = ? AND activo = 1 LIMIT 1',
      [nombre],
    );
    return rows.length ? rows[0].id : null;
  },

  // Busca un registro del MISMO turno para una combinación nave+actividad+ubicación.
  // Se usa para hacer upsert de la actividad de patio autogenerada (un despacho por
  // nave y turno) sin duplicarla al editar el registro de muelle.
  async buscarRegistroTurno({ fecha_turno, turno, ubicacion_tipo, nave_id, actividad_id }) {
    const [rows] = await pool.query(
      `SELECT id FROM tallyman_registros
        WHERE fecha_turno = ? AND turno = ? AND ubicacion_tipo = ?
          AND (nave_id <=> ?) AND actividad_id = ?
        ORDER BY id DESC LIMIT 1`,
      [fecha_turno, turno, ubicacion_tipo, nave_id ?? null, actividad_id],
    );
    return rows.length ? rows[0].id : null;
  },

  // Actualiza solo executed/planned de un registro (patio autogenerado del cementero),
  // propagando el planned al resto de turnos de esa nave+actividad YARD.
  async actualizarEjecutadoPlanned(id, { executed, planned, nave_id, actividad_id }) {
    await pool.query(
      'UPDATE tallyman_registros SET executed = ?, planned = ? WHERE id = ?',
      [executed, planned ?? null, id],
    );
    if (planned != null) await this.propagarPlannedYard({ nave_id, actividad_id, planned });
    return this.obtenerRegistro(id);
  },

  // Suma de executed de turnos ANTERIORES (estrictamente) para una nave en muelle.
  // El ciclo acompaña a la nave aunque cambie de Berth o se corrija su actividad.
  async executedPrevio({ nave_id, fecha_turno, turno }) {
    const [rows] = await pool.query(
      `SELECT COALESCE(SUM(executed), 0) AS prev
         FROM tallyman_registros
         WHERE ubicacion_tipo = 'BERTH' AND (nave_id <=> ?)
           AND (fecha_turno < ? OR (fecha_turno = ? AND turno <> ?))`,
      [nave_id ?? null, fecha_turno, fecha_turno, turno],
    );
    return Number(rows[0]?.prev) || 0;
  },

  // Status del ÚLTIMO registro de una nave+actividad (el más reciente por id).
  // Acepta nave_id (BERTH / YARD real) o nave_patio (texto patio "Otros").
  async ultimoStatus({ nave_id, actividad_id, nave_patio }) {
    if (nave_patio) {
      const [rows] = await pool.query(
        `SELECT status_act
           FROM tallyman_registros
          WHERE actividad_id = ? AND nave_patio = ?
          ORDER BY id DESC LIMIT 1`,
        [actividad_id, nave_patio],
      );
      return rows.length ? rows[0].status_act : null;
    }
    const [rows] = await pool.query(
      `SELECT status_act
         FROM tallyman_registros
        WHERE actividad_id = ? AND (nave_id <=> ?)
        ORDER BY id DESC
        LIMIT 1`,
      [actividad_id, nave_id ?? null],
    );
    return rows.length ? rows[0].status_act : null;
  },

  async ultimoStatusBerth(nave_id) {
    const [rows] = await pool.query(
      `SELECT status_act FROM tallyman_registros
        WHERE ubicacion_tipo = 'BERTH' AND (nave_id <=> ?)
        ORDER BY id DESC LIMIT 1`,
      [nave_id ?? null],
    );
    return rows.length ? rows[0].status_act : null;
  },

  async ultimoStatusYard({ nave_id, nave_patio }) {
    const where = nave_patio ? 'nave_patio = ?' : '(nave_id <=> ?)';
    const value = nave_patio || nave_id || null;
    const [rows] = await pool.query(
      `SELECT status_act FROM tallyman_registros
        WHERE ubicacion_tipo = 'YARD' AND ${where}
        ORDER BY id DESC LIMIT 1`,
      [value],
    );
    return rows.length ? rows[0].status_act : null;
  },

  // Naves que tienen registros de descarga interna (BERTH), con la suma acumulada
  // de `interna` como planned_patio sugerido para el formulario de Patio.
  // También devuelve actividad_id_sugerida y planned_sugerido del último registro
  // YARD abierto (no Culminado) para que el formulario los autocomplete.
  async navesConInterna() {
    const [rows] = await pool.query(
      `SELECT n.id, n.nombre,
              COALESCE(SUM(tr.interna), 0) AS planned_patio,
              ult.actividad_id AS actividad_id_sugerida,
              ult.planned     AS planned_sugerido
         FROM naves n
         JOIN tallyman_registros tr ON tr.nave_id = n.id AND tr.interna > 0
          LEFT JOIN tallyman_registros ult
                 ON ult.id = (
                      SELECT MAX(id) FROM tallyman_registros
                       WHERE ubicacion_tipo = 'YARD'
                         AND nave_id = n.id
                    )
         GROUP BY n.id, n.nombre, ult.id, ult.actividad_id, ult.planned, ult.status_act
        HAVING ult.status_act IS NULL OR ult.status_act <> 'Culminado'
         ORDER BY n.nombre`,
    );
    return rows;
  },

  // Nombres de texto activos en patio (Otros). Un nombre permanece en la lista
  // solo mientras tenga algún ciclo (nave_patio + actividad) cuyo ÚLTIMO registro
  // NO esté Culminado. Así, al cerrar (Culminado) la última actividad de una nave
  // de patio, su nombre deja de aparecer aunque existan registros previos abiertos
  // de ciclos ya cerrados. Devuelve nombre + datos del último registro abierto para
  // que el formulario autocomplete actividad y planned.
  async nombresPatioActivos() {
    const [rows] = await pool.query(
      `SELECT tr.nave_patio AS nombre,
              tr.actividad_id AS actividad_id_sugerida,
              tr.planned AS planned_sugerido
         FROM tallyman_registros tr
        WHERE tr.nave_patio IS NOT NULL AND tr.nave_patio <> ''
          AND tr.id = (SELECT MAX(m.id) FROM tallyman_registros m
                        WHERE m.nave_patio = tr.nave_patio)
          AND tr.status_act <> 'Culminado'
        ORDER BY tr.nave_patio`,
    );
    return rows;
  },

  // Acumulado de executed para registros YARD con la misma nave o nombre de patio.
  async executedPrevioYard({ nave_id, nave_patio, fecha_turno, turno }) {
    if (nave_id != null) {
      const [rows] = await pool.query(
        `SELECT COALESCE(SUM(executed), 0) AS prev
           FROM tallyman_registros
           WHERE nave_id = ? AND ubicacion_tipo = 'YARD'
             AND (fecha_turno < ? OR (fecha_turno = ? AND turno <> ?))`,
        [nave_id, fecha_turno, fecha_turno, turno],
      );
      return Number(rows[0]?.prev) || 0;
    }
    if (nave_patio) {
      const [rows] = await pool.query(
        `SELECT COALESCE(SUM(executed), 0) AS prev
           FROM tallyman_registros
           WHERE nave_patio = ? AND ubicacion_tipo = 'YARD'
             AND (fecha_turno < ? OR (fecha_turno = ? AND turno <> ?))`,
        [nave_patio, fecha_turno, fecha_turno, turno],
      );
      return Number(rows[0]?.prev) || 0;
    }
    return 0;
  },

  // Propaga el planned (acumulado de patio) editado a TODOS los registros YARD
  // que coincidan en nave (real o de texto) + actividad, para que el acumulado
  // sea consistente entre turnos. Solo aplica si hay un planned definido.
  async propagarPlannedYard({ nave_id, nave_patio, actividad_id, planned }) {
    if (planned == null) return;
    if (nave_id != null) {
      await pool.query(
        `UPDATE tallyman_registros SET planned = ?
          WHERE ubicacion_tipo = 'YARD' AND actividad_id = ? AND nave_id = ?`,
        [planned, actividad_id, nave_id],
      );
    } else if (nave_patio) {
      await pool.query(
        `UPDATE tallyman_registros SET planned = ?
          WHERE ubicacion_tipo = 'YARD' AND actividad_id = ? AND nave_patio = ?`,
        [planned, actividad_id, nave_patio],
      );
    }
  },

  // Suma de la DESCARGA DIRECTA (despacho indirecto) de todos los turnos de una
  // nave + actividad de muelle (BERTH). Se usa para el planned del patio Cement Big Bags.
  async sumaDirectaMuelle({ nave_id, actividad_id }) {
    const [rows] = await pool.query(
      `SELECT COALESCE(SUM(directa), 0) AS s
         FROM tallyman_registros
        WHERE ubicacion_tipo = 'BERTH' AND actividad_id = ? AND (nave_id <=> ?)`,
      [actividad_id, nave_id ?? null],
    );
    return Number(rows[0]?.s) || 0;
  },

  async crearRegistro(r) {
    const [res] = await pool.query(
      `INSERT INTO tallyman_registros
         (fecha_turno, turno, ubicacion_tipo, ubicacion, nave_id, nave_patio, actividad_id,
          estado_pos, status_act, planned, executed, directa, interna, productivity, details,
          cargo_type, bl, producto, unidades, tons, ubic_codigo,
          coord_entrante, coord_saliente, registrado_por)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        r.fecha_turno, r.turno, r.ubicacion_tipo, r.ubicacion, r.nave_id ?? null, r.nave_patio ?? null,
        r.actividad_id, r.estado_pos, r.status_act, r.planned ?? null, r.executed,
        r.directa ?? null, r.interna ?? null,
        r.productivity ?? null, r.details ?? null,
        r.cargo_type ?? null, r.bl ?? null, r.producto ?? null,
        r.unidades ?? null, r.tons ?? null, r.ubic_codigo ?? null,
        r.coord_entrante ?? null, r.coord_saliente ?? null, r.registrado_por,
      ],
    );
    if (r.ubicacion_tipo === 'YARD') await this.propagarPlannedYard(r);
    return this.obtenerRegistro(res.insertId);
  },

  async obtenerRegistro(id) {
    const [rows] = await pool.query(
      `SELECT r.*, a.nombre AS actividad, n.nombre AS nave, n.tipo_nave_id, tn.nombre AS tipo_nave,
              CASE WHEN r.ubicacion_tipo = 'YARD' AND r.nave_id IS NOT NULL AND r.planned IS NULL
                   THEN COALESCE((SELECT SUM(tr2.interna)
                                    FROM tallyman_registros tr2
                                   WHERE tr2.nave_id = r.nave_id
                                     AND tr2.ubicacion_tipo = 'BERTH'
                                     AND tr2.interna > 0), 0)
                   ELSE r.planned
              END AS planned
         FROM tallyman_registros r
         JOIN tallyman_actividades a ON a.id = r.actividad_id
         LEFT JOIN naves n ON n.id = r.nave_id
         LEFT JOIN tipos_nave tn ON tn.id = n.tipo_nave_id
        WHERE r.id = ?`,
      [id],
    );
    return rows[0] || null;
  },

  async listarPorTurno(fecha_turno, turno) {
    const [rows] = await pool.query(
      `SELECT r.*, a.nombre AS actividad, n.nombre AS nave, n.tipo_nave_id, tn.nombre AS tipo_nave,
              CASE WHEN r.ubicacion_tipo = 'YARD' AND r.nave_id IS NOT NULL AND r.planned IS NULL
                   THEN COALESCE((SELECT SUM(tr2.interna)
                                    FROM tallyman_registros tr2
                                   WHERE tr2.nave_id = r.nave_id
                                     AND tr2.ubicacion_tipo = 'BERTH'
                                     AND tr2.interna > 0), 0)
                   ELSE r.planned
              END AS planned
         FROM tallyman_registros r
         JOIN tallyman_actividades a ON a.id = r.actividad_id
         LEFT JOIN naves n ON n.id = r.nave_id
         LEFT JOIN tipos_nave tn ON tn.id = n.tipo_nave_id
        WHERE r.fecha_turno = ? AND r.turno = ?
        ORDER BY r.ubicacion_tipo, r.ubicacion, r.id`,
      [fecha_turno, turno],
    );
    return rows;
  },

  // Lista registros en un rango de fechas (ambos límites opcionales).
  // Sin límites → todo el histórico. Solo lectura/histórico (Administrador).
  async listarPorRango(desde, hasta) {
    const where = [];
    const params = [];
    if (desde) { where.push('r.fecha_turno >= ?'); params.push(desde); }
    if (hasta) { where.push('r.fecha_turno <= ?'); params.push(hasta); }
    const cond = where.length ? 'WHERE ' + where.join(' AND ') : '';
    const [rows] = await pool.query(
      `SELECT r.*, a.nombre AS actividad, n.nombre AS nave, n.tipo_nave_id, tn.nombre AS tipo_nave,
              CASE WHEN r.ubicacion_tipo = 'YARD' AND r.nave_id IS NOT NULL AND r.planned IS NULL
                   THEN COALESCE((SELECT SUM(tr2.interna)
                                    FROM tallyman_registros tr2
                                   WHERE tr2.nave_id = r.nave_id
                                     AND tr2.ubicacion_tipo = 'BERTH'
                                     AND tr2.interna > 0), 0)
                   ELSE r.planned
              END AS planned
         FROM tallyman_registros r
         JOIN tallyman_actividades a ON a.id = r.actividad_id
         LEFT JOIN naves n ON n.id = r.nave_id
         LEFT JOIN tipos_nave tn ON tn.id = n.tipo_nave_id
         ${cond}
        ORDER BY r.fecha_turno DESC, r.turno, r.ubicacion_tipo, r.ubicacion, r.id`,
      params,
    );
    return rows;
  },

  async editarRegistro(id, r) {
    await pool.query(
      `UPDATE tallyman_registros
          SET ubicacion_tipo = ?, ubicacion = ?, nave_id = ?, nave_patio = ?, actividad_id = ?,
              estado_pos = ?, status_act = ?, planned = ?, executed = ?,
              directa = ?, interna = ?, productivity = ?, details = ?, cargo_type = ?, bl = ?, producto = ?,
              unidades = ?, tons = ?, ubic_codigo = ?, coord_entrante = ?, coord_saliente = ?
        WHERE id = ?`,
      [
        r.ubicacion_tipo, r.ubicacion, r.nave_id ?? null, r.nave_patio ?? null, r.actividad_id,
        r.estado_pos, r.status_act, r.planned ?? null, r.executed,
        r.directa ?? null, r.interna ?? null, r.productivity ?? null, r.details ?? null, r.cargo_type ?? null,
        r.bl ?? null, r.producto ?? null, r.unidades ?? null, r.tons ?? null,
        r.ubic_codigo ?? null, r.coord_entrante ?? null, r.coord_saliente ?? null, id,
      ],
    );
    if (r.ubicacion_tipo === 'YARD') await this.propagarPlannedYard(r);
    return this.obtenerRegistro(id);
  },

  async eliminarRegistro(id) {
    await pool.query('DELETE FROM tallyman_registros WHERE id = ?', [id]);
  },

  // Incidencia del turno: upsert lógico (una por fecha+turno; reemplaza la previa).
  async guardarIncidencia({ fecha_turno, turno, hubo, detalle, registrado_por }) {
    await pool.query(
      'DELETE FROM tallyman_incidencias WHERE fecha_turno = ? AND turno = ?',
      [fecha_turno, turno],
    );
    const [res] = await pool.query(
      `INSERT INTO tallyman_incidencias (fecha_turno, turno, hubo, detalle, registrado_por)
       VALUES (?, ?, ?, ?, ?)`,
      [fecha_turno, turno, hubo ? 1 : 0, detalle ?? null, registrado_por],
    );
    const [rows] = await pool.query('SELECT * FROM tallyman_incidencias WHERE id = ?', [res.insertId]);
    return rows[0] || null;
  },

  async obtenerIncidencia(fecha_turno, turno) {
    const [rows] = await pool.query(
      'SELECT * FROM tallyman_incidencias WHERE fecha_turno = ? AND turno = ? LIMIT 1',
      [fecha_turno, turno],
    );
    return rows[0] || null;
  },
};
