ALTER TABLE usuarios
  MODIFY rol ENUM('Administrador','Supervisor','Coordinador','Soporte','Operador')
  NOT NULL DEFAULT 'Coordinador';

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'usuarios'
     AND COLUMN_NAME  = 'soporte_de_id'
);
SET @ddl := IF(@col = 0,
  'ALTER TABLE usuarios ADD COLUMN soporte_de_id INT(11) NULL AFTER rol',
  'SELECT "soporte_de_id ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ix := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'usuarios'
     AND INDEX_NAME   = 'ix_usr_soporte_de'
);
SET @ddl := IF(@ix = 0,
  'ALTER TABLE usuarios ADD KEY ix_usr_soporte_de (soporte_de_id)',
  'SELECT "ix_usr_soporte_de ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA    = DATABASE()
     AND TABLE_NAME      = 'usuarios'
     AND CONSTRAINT_NAME = 'fk_usr_soporte_de'
);
SET @ddl := IF(@fk = 0,
  'ALTER TABLE usuarios
     ADD CONSTRAINT fk_usr_soporte_de FOREIGN KEY (soporte_de_id)
         REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT "fk_usr_soporte_de ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS tareas (
  id                 INT(11)      NOT NULL AUTO_INCREMENT,

  lote_id            INT(11)      NULL,

  titulo             VARCHAR(180) NOT NULL,
  descripcion        TEXT         NULL,
  prioridad          ENUM('baja','media','alta') NOT NULL DEFAULT 'media',

  asignado_id        INT(11)      NULL,
  asignado_nombre    VARCHAR(100) NOT NULL,
  asignado_rol       ENUM('Coordinador','Soporte') NOT NULL,
  coordinador_ref_id     INT(11)      NULL,
  coordinador_ref_nombre VARCHAR(100) NULL,

  fecha_limite       DATETIME     NOT NULL,
  fecha_limite_2     DATETIME     NULL,
  prorroga_motivo    VARCHAR(255) NULL,
  prorroga_por       VARCHAR(100) NULL,
  prorroga_por_id    INT(11)      NULL,
  prorroga_at        TIMESTAMP    NULL,

  estado             ENUM('pendiente','entregada','observada','aprobada','rechazada')
                     NOT NULL DEFAULT 'pendiente',
  entrega_comentario TEXT         NULL,
  enviado_at         TIMESTAMP    NULL,
  plazo_al_enviar    DATETIME     NULL,
  entregas_count     INT(11)      NOT NULL DEFAULT 0,

  nota               TINYINT      NULL,
  comentario_admin   TEXT         NULL,
  revisado_por       VARCHAR(100) NULL,
  revisado_por_id    INT(11)      NULL,
  revisado_at        TIMESTAMP    NULL,

  creado_por         VARCHAR(100) NOT NULL,
  creado_por_id      INT(11)      NULL,
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY ix_tar_asignado (asignado_id),
  KEY ix_tar_estado   (estado),
  KEY ix_tar_fecha    (fecha_limite),
  KEY ix_tar_lote     (lote_id),
  CONSTRAINT fk_tar_asignado FOREIGN KEY (asignado_id)
     REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'tareas'
     AND COLUMN_NAME  = 'coordinador_ref_nombre'
);
SET @ddl := IF(@col = 0,
  'ALTER TABLE tareas ADD COLUMN coordinador_ref_nombre VARCHAR(100) NULL AFTER coordinador_ref_id',
  'SELECT "coordinador_ref_nombre ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS tareas_adjuntos (
  id             INT(11)      NOT NULL AUTO_INCREMENT,
  tarea_id       INT(11)      NOT NULL,
  nombre_archivo VARCHAR(180) NOT NULL,
  mime           VARCHAR(120) NOT NULL,
  peso_bytes     INT UNSIGNED NOT NULL,
  drive_file_id  VARCHAR(120) NULL,
  drive_url      VARCHAR(512) NULL,
  ruta_local     VARCHAR(255) NULL,
  estado         ENUM('subido','pendiente','error') NOT NULL DEFAULT 'pendiente',
  error_msg      VARCHAR(255) NULL,
  origen         ENUM('admin','asignado') NOT NULL DEFAULT 'asignado',
  entrega_nro    INT(11)      NOT NULL DEFAULT 1,
  subido_por     VARCHAR(100) NULL,
  subido_por_id  INT(11)      NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_tard_tarea (tarea_id),
  CONSTRAINT fk_tard_tarea FOREIGN KEY (tarea_id)
     REFERENCES tareas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tareas_historial (
  id             INT(11) NOT NULL AUTO_INCREMENT,
  tarea_id       INT(11) NOT NULL,
  accion         ENUM('creada','editada','enviada','observada','aprobada',
                      'rechazada','prorroga','prorroga_retirada',
                      'adjunto','adjunto_borrado') NOT NULL,
  usuario_id     INT(11)      NULL,
  usuario_nombre VARCHAR(100) NULL,
  usuario_rol    VARCHAR(20)  NULL,
  detalle        TEXT         NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_tarh_tarea (tarea_id),
  CONSTRAINT fk_tarh_tarea FOREIGN KEY (tarea_id)
     REFERENCES tareas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

