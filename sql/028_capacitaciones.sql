-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 028 · Módulo de Capacitaciones
-- ────────────────────────────────────────────────────────────────────
-- Ciclo completo de una capacitación:
--   programada ──(coordinador)──▶ por_validar ──(administrador)──▶ realizada
--                                                                  no_realizada
--
-- · El coordinador la PROGRAMA (título + fecha + hora), luego entra y
--   carga los temas, el material y la asistencia de toda la plantilla.
-- · El administrador valida si se realizó o no, y deja un comentario.
--
-- No sustituye a `asistencias_preoperativas` («Charlas»), que sigue
-- siendo el acta retrospectiva de la charla pre-operativa diaria.
--
-- Adjuntos: se suben a Google Drive con el mismo mecanismo que las
-- evidencias de Charlas (includes/drive_uploader.php); aquí solo se
-- persisten los metadatos + respaldo local si Drive falló.
--
-- Se ejecuta sobre la base YA seleccionada (en phpMyAdmin: elige la base
-- antes de importar). No lleva "USE" para funcionar en local y en el
-- servidor sin importar el nombre de la base.
-- Idempotente (CREATE TABLE IF NOT EXISTS + comprobación de columnas).
-- ════════════════════════════════════════════════════════════════════

-- ── La capacitación ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS capacitaciones (
  id                INT(11)      NOT NULL AUTO_INCREMENT,
  titulo            VARCHAR(180) NOT NULL,
  fecha             DATE         NOT NULL,
  hora              TIME         NOT NULL,
  duracion_min      INT(11)      NULL,               -- duración estimada en minutos
  lugar             VARCHAR(120) NULL,
  expositor         VARCHAR(120) NULL,
  observaciones     TEXT         NULL,               -- notas del coordinador

  estado            ENUM('programada','por_validar','realizada','no_realizada')
                    NOT NULL DEFAULT 'programada',

  -- Nº de colaboradores activos EN EL MOMENTO de enviar a validación.
  -- Sin este sello, dar de alta un colaborador nuevo bajaría el % de
  -- asistencia de todas las capacitaciones ya cerradas. NULL mientras
  -- está programada: ahí el total se cuenta en vivo, que es lo correcto.
  total_plantilla   INT(11)      NULL,

  coordinador       VARCHAR(100) NOT NULL,           -- nombre de la sesión que la crea
  coordinador_id    INT(11)      NULL,               -- user_id de la sesión
  enviado_at        TIMESTAMP    NULL,

  validado_por      VARCHAR(100) NULL,
  validado_por_id   INT(11)      NULL,
  validado_at       TIMESTAMP    NULL,
  comentario_admin  TEXT         NULL,               -- puntos a mejorar / felicitación

  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_cap_fecha (fecha),
  KEY ix_cap_estado (estado),
  KEY ix_cap_coordinador (coordinador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Temas desarrollados (filas ordenadas, no un TEXT corrido) ───────
-- Se guardan como tabla para poder contarlos en el listado, numerarlos
-- en la vista del administrador y exportarlos.
CREATE TABLE IF NOT EXISTS capacitaciones_temas (
  id               INT(11)      NOT NULL AUTO_INCREMENT,
  capacitacion_id  INT(11)      NOT NULL,
  orden            INT(11)      NOT NULL DEFAULT 0,  -- el orden de exposición es información
  titulo           VARCHAR(200) NOT NULL,
  descripcion      TEXT         NULL,
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_capt_capacitacion (capacitacion_id),
  CONSTRAINT fk_capt_capacitacion FOREIGN KEY (capacitacion_id)
     REFERENCES capacitaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Asistencia ──────────────────────────────────────────────────────
-- IMPORTANTE: aquí SOLO viven los colaboradores MARCADOS.
-- «Sin marcar» no es un valor del ENUM, es la ausencia de fila. Así el
-- estado por defecto no puede ser «asistió» por accidente: un coordinador
-- que no abre la pestaña no genera un 100 % de asistencia falso.
-- La UNIQUE hace que el guardado sea idempotente (INSERT … ON DUPLICATE).
CREATE TABLE IF NOT EXISTS capacitaciones_asistentes (
  id                    INT(11)      NOT NULL AUTO_INCREMENT,
  capacitacion_id       INT(11)      NOT NULL,
  colaborador_id        INT(11)      NULL,           -- FK; SET NULL si se borra el colaborador
  colaborador_nombre    VARCHAR(150) NOT NULL,       -- copias congeladas al marcar
  colaborador_dni       VARCHAR(8)   NULL,
  colaborador_cargo     VARCHAR(60)  NULL,
  colaborador_cuadrilla VARCHAR(20)  NULL,
  estado                ENUM('asistio','tardanza','falta') NOT NULL,
  created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_capa_cap_colab (capacitacion_id, colaborador_id),
  KEY ix_capa_capacitacion (capacitacion_id),
  KEY ix_capa_colaborador (colaborador_id),
  CONSTRAINT fk_capa_capacitacion FOREIGN KEY (capacitacion_id)
     REFERENCES capacitaciones(id) ON DELETE CASCADE,
  CONSTRAINT fk_capa_colaborador FOREIGN KEY (colaborador_id)
     REFERENCES colaboradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Material y evidencias (Drive, con respaldo local) ───────────────
CREATE TABLE IF NOT EXISTS capacitaciones_adjuntos (
  id               INT(11)      NOT NULL AUTO_INCREMENT,
  capacitacion_id  INT(11)      NOT NULL,
  nombre_archivo   VARCHAR(180) NOT NULL,
  mime             VARCHAR(120) NOT NULL,
  peso_bytes       INT UNSIGNED NOT NULL,
  drive_file_id    VARCHAR(120) NULL,
  drive_url        VARCHAR(512) NULL,
  ruta_local       VARCHAR(255) NULL,                -- respaldo si Drive falló
  estado           ENUM('subido','pendiente','error') NOT NULL DEFAULT 'pendiente',
  error_msg        VARCHAR(255) NULL,
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_capd_capacitacion (capacitacion_id),
  CONSTRAINT fk_capd_capacitacion FOREIGN KEY (capacitacion_id)
     REFERENCES capacitaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Verificación ────────────────────────────────────────────────────
-- SHOW TABLES LIKE 'capacitaciones%';
-- SELECT c.id, c.titulo, c.estado,
--        (SELECT COUNT(*) FROM capacitaciones_temas       t WHERE t.capacitacion_id = c.id) AS temas,
--        (SELECT COUNT(*) FROM capacitaciones_asistentes  a WHERE a.capacitacion_id = c.id) AS marcados
--   FROM capacitaciones c ORDER BY c.fecha DESC;
