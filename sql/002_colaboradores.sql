-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 002 · Tabla colaboradores
-- Catálogo maestro de personal. Reemplaza el seed hardcoded
-- de js/data-source.js (array `plantilla`).
-- Ejecutar con: mysql -uroot estiba_turno < sql/002_colaboradores.sql
-- ════════════════════════════════════════════════════════════════════

USE estiba_turno;

CREATE TABLE IF NOT EXISTS colaboradores (
  id                 INT(11)      NOT NULL AUTO_INCREMENT,
  codigo             VARCHAR(20)  NULL,
  nombre             VARCHAR(150) NOT NULL,
  dni                VARCHAR(8)   NOT NULL,
  funcion_principal  VARCHAR(60)  NOT NULL,
  cuadrilla          VARCHAR(20)  NOT NULL,
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dni (dni),
  UNIQUE KEY uq_codigo (codigo),
  KEY ix_cuadrilla (cuadrilla),
  KEY ix_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nota: el trigger fue eliminado porque la generación de `codigo` se hace en
-- PHP (endpoints save_colaborador.php e import_colaboradores.php).
-- Razón: en MariaDB, no hay forma robusta de auto-asignar
-- `codigo = ST-<lpad(id,3)>` desde un trigger que funcione correctamente
-- para single-row e implícitos multi-row INSERTs
-- (AFTER INSERT → error 1442; BEFORE INSERT + information_schema → desync
-- en multi-row porque AUTO_INCREMENT se cachea a nivel statement).
-- El patrón PHP es: INSERT, leer mysqli_insert_id(), UPDATE codigo en la
-- misma transacción.
-- Mantenemos el DROP TRIGGER IF EXISTS para que el script sea idempotente
-- en instalaciones donde el trigger fue creado en una versión anterior.
DROP TRIGGER IF EXISTS trg_colaboradores_codigo;

-- Seed: los 12 colaboradores actuales de js/data-source.js.
-- codigo explícito para preservar los IDs ST-001..ST-012 que ya
-- referencian otros lugares (personal en data-source.js).
INSERT IGNORE INTO colaboradores (codigo, nombre, dni, funcion_principal, cuadrilla, activo) VALUES
('ST-001', 'Juan Pérez Quispe',        '45123678', 'Winchero',     'A', 1),
('ST-002', 'Carlos Mendoza Lévano',    '47893201', 'Estibador',    'A', 1),
('ST-003', 'Luis Ramírez Saldaña',     '41234567', 'Señalero',     'A', 1),
('ST-004', 'Pedro Huamán Castro',      '43456789', 'Tractorista',  'A', 1),
('ST-005', 'Miguel Ángel Torres',      '46789012', 'Capataz',      'A', 1),
('ST-006', 'Jorge Salazar Núñez',      '48901234', 'Lashing',      'A', 1),
('ST-007', 'Andrés Cárdenas Yupanqui', '44567890', 'Apoyo Bodega', 'A', 1),
('ST-008', 'Fernando Quiroz Bravo',    '42345678', 'Estibador',    'A', 1),
('ST-009', 'Ricardo Villanueva Poma',  '49012345', 'Winchero',     'B', 1),
('ST-010', 'Héctor Zapata Quispe',     '45678901', 'Estibador',    'B', 1),
('ST-011', 'Óscar Llerena Matos',      '43210987', 'Señalero',     'B', 1),
('ST-012', 'Raúl Condori Flores',      '47654321', 'Tractorista',  'B', 0);

-- Asegura que AUTO_INCREMENT arranca después del seed.
ALTER TABLE colaboradores AUTO_INCREMENT = 13;
