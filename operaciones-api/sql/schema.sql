-- ============================================================
-- Sistema Portuario · Módulo de Operaciones · Fase 1
-- MySQL 8.x / MariaDB · InnoDB · utf8mb4
-- ============================================================
CREATE DATABASE IF NOT EXISTS operaciones
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE operaciones;

-- ---------- Catálogo de tipos de nave (escalable: agregar tipo = INSERT, no ALTER) ----------
CREATE TABLE IF NOT EXISTS tipos_nave (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre  VARCHAR(60)  NOT NULL,
  activo  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tipos_nave_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tipos_nave (nombre) VALUES
  ('Containera'), ('Granelera'), ('Tanquero'),
  ('Ro-Ro'), ('Portacontenedores'), ('Carga General')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ---------- Naves ----------
CREATE TABLE IF NOT EXISTS naves (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(120) NOT NULL,
  tipo_nave_id  INT UNSIGNED NOT NULL,
  eta           DATETIME     NULL,                      -- Estimated Time of Arrival
  etb           DATETIME     NULL,                      -- Estimated Time of Berthing
  etd           DATETIME     NULL,                      -- Estimated Time of Departure
  estado        ENUM('Programada','En Puerto','Zarpada','Cancelada')
                NOT NULL DEFAULT 'Programada',
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_naves_tipo
    FOREIGN KEY (tipo_nave_id) REFERENCES tipos_nave (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  KEY idx_naves_estado (estado),                        -- filtro de listado
  KEY idx_naves_eta    (eta),                           -- orden/búsqueda por arribo
  KEY idx_naves_nombre (nombre)                         -- búsqueda por nombre
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Avances / bitácora de la nave (1 a N) ----------
CREATE TABLE IF NOT EXISTS avances_nave (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nave_id             INT UNSIGNED NOT NULL,
  descripcion_avance  TEXT         NOT NULL,
  registrado_por      VARCHAR(100) NOT NULL,            -- identidad simulada del Coordinador (Fase 1)
  fecha_registro      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_avances_nave
    FOREIGN KEY (nave_id) REFERENCES naves (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  KEY idx_avances_nave_fecha (nave_id, fecha_registro)  -- índice compuesto: historial por nave
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
