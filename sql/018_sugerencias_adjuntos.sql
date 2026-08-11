-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 018 · Adjuntos de Sugerencias Tallyman
-- Hasta 3 archivos por sugerencia (imagen, PDF, documento o video),
-- cada uno menor a 4 MB. Los archivos se suben a Google Drive mediante
-- un Web App de Apps Script (ver apps-script/SugerenciasDrive.gs), que
-- los coloca en la subcarpeta correspondiente al canal y los nombra con
-- la fecha y hora de subida.
--
-- ANONIMATO: esta tabla NO guarda identidad alguna. Se vincula solo a
-- sugerencias_tallyman.id, cuya identidad ya es NULL cuando el canal es
-- 'observacion'. El nombre del archivo es solo fecha/hora, nunca el DNI
-- ni el nombre del colaborador.
--
-- Ejecutar con: mysql -uroot portally_system < sql/018_sugerencias_adjuntos.sql
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- ════════════════════════════════════════════════════════════════════

USE portally_system;

CREATE TABLE IF NOT EXISTS sugerencias_adjuntos (
  id             INT(11)      NOT NULL AUTO_INCREMENT,
  sugerencia_id  INT(11)      NOT NULL,
  nombre_archivo VARCHAR(150) NOT NULL,           -- 2026-07-09_14-32-05.pdf
  mime           VARCHAR(120) NOT NULL,
  peso_bytes     INT UNSIGNED NOT NULL,
  drive_file_id  VARCHAR(120) NULL,               -- id devuelto por Apps Script
  drive_url      VARCHAR(255) NULL,               -- enlace de vista en Drive
  ruta_local     VARCHAR(255) NULL,               -- copia local si Drive falló
  estado         ENUM('subido','pendiente','error') NOT NULL DEFAULT 'pendiente',
  error_msg      VARCHAR(255) NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sga_sugerencia (sugerencia_id),
  KEY ix_sga_estado (estado),
  CONSTRAINT fk_sga_sugerencia FOREIGN KEY (sugerencia_id)
     REFERENCES sugerencias_tallyman(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
