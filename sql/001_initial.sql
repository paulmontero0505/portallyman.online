-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · Schema inicial
-- BD independiente del sistema padre.
-- Crear con: mysql -uroot < 001_initial.sql
-- ════════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS estiba_turno
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE estiba_turno;

-- ─── usuarios ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
  id              INT(11)      NOT NULL AUTO_INCREMENT,
  email           VARCHAR(100) NOT NULL,
  password        VARCHAR(255) NOT NULL,            -- bcrypt hash
  nombre          VARCHAR(100) NOT NULL,
  rol             ENUM('Administrador','Operador') NOT NULL DEFAULT 'Operador',
  estado          ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  ultimo_acceso   DATETIME     NULL DEFAULT NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── seed admin inicial ──────────────────────────────────────────────
-- Password: admin123 (bcrypt)
-- ⚠ Cámbiala desde la UI después del primer login.
INSERT INTO usuarios (email, password, nombre, rol, estado)
VALUES (
  'admin@estiba.local',
  '$2y$10$4/O0Q/dmg0UGeI1qG3/q3OX7Mw11aM91RDZLffUOPRARKMLUsnn2K',
  'Administrador',
  'Administrador',
  'Activo'
)
ON DUPLICATE KEY UPDATE id = id;
