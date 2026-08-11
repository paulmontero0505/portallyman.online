-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 017 · Módulo Sugerencias Tallyman
-- Buzón de Observaciones (anónimo), Consultas, Solicitudes y Propuestas
-- de mejora, enviado por los colaboradores desde sugerencias.php (público,
-- identificación por DNI, sin login). Solo lo administra el rol
-- Administrador (pages/sugerencias.php).
-- Catálogo de canales fijo en código: includes/sugerencias_catalogo.php
-- Para el canal "observacion" NUNCA se persiste la identidad del
-- colaborador (colaborador_id/nombre/cargo quedan NULL) para garantizar
-- el anonimato.
-- Ejecutar con: mysql -uroot portally_system < sql/017_sugerencias_tallyman.sql
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- ════════════════════════════════════════════════════════════════════

USE portally_system;

CREATE TABLE IF NOT EXISTS sugerencias_tallyman (
  id                  INT(11)      NOT NULL AUTO_INCREMENT,
  canal               ENUM('observacion','consulta','solicitud','propuesta') NOT NULL,
  colaborador_id      INT(11)      NULL,                  -- NULL siempre si canal='observacion'
  colaborador_nombre  VARCHAR(150) NULL,                   -- copia congelada; NULL si es anónimo
  colaborador_cargo   VARCHAR(60)  NULL,
  detalle             TEXT         NOT NULL,
  puntaje             TINYINT      NULL,                  -- 1-10; solo aplica a canal='propuesta'
  puntaje_comentario  VARCHAR(255) NULL,
  puntaje_por         VARCHAR(100) NULL,                   -- administrador que calificó
  puntaje_at          TIMESTAMP    NULL,
  created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sg_canal (canal),
  KEY ix_sg_fecha (created_at),
  KEY ix_sg_colaborador (colaborador_id),
  CONSTRAINT fk_sg_colaborador FOREIGN KEY (colaborador_id)
     REFERENCES colaboradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
