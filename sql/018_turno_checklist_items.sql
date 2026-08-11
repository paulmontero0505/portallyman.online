-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 018 · Checklist de tallyman por colaborador en turno
-- Ítems libres (item + comentario) registrados sobre un turno_personal,
-- p.ej. Ítem: "Tarja" · Comentario: "Del 50001 al 50150".
-- Ejecutar con: mysql -uroot estiba_turno < sql/018_turno_checklist_items.sql
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- ════════════════════════════════════════════════════════════════════

USE estiba_turno;

CREATE TABLE IF NOT EXISTS turno_checklist_items (
  id                INT(11)     NOT NULL AUTO_INCREMENT,
  turno_personal_id INT(11)     NOT NULL,
  item              VARCHAR(120) NOT NULL,
  comentario        TEXT        NULL,
  created_at        TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_cli_tp (turno_personal_id),
  CONSTRAINT fk_cli_tp FOREIGN KEY (turno_personal_id) REFERENCES turno_personal(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
