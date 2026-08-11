-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 016 · Módulo Evaluación de Desempeño
-- Ficha de evaluación de desempeño (procedimientos operativos, seguridad/EPP,
-- relaciones interpersonales) con escala 1-5 por criterio.
-- Catálogo de criterios fijo en código: includes/evaluacion_desempeno_catalogo.php
-- El checklist completo (categoría + item + puntaje + observación) y los
-- comentarios por categoría se persisten como JSON.
-- Ejecutar con: mysql -uroot portally_system < sql/016_evaluacion_desempeno.sql
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- ════════════════════════════════════════════════════════════════════

USE portally_system;

CREATE TABLE IF NOT EXISTS evaluacion_desempeno (
  id                      INT(11)      NOT NULL AUTO_INCREMENT,
  colaborador_id          INT(11)      NULL,                  -- FK colaboradores; SET NULL si se borra
  colaborador_nombre      VARCHAR(150) NOT NULL,               -- copia congelada al registrar
  colaborador_cargo       VARCHAR(60)  NULL,
  actividad               VARCHAR(150) NULL,
  turno                   ENUM('dia','noche') NOT NULL,
  fecha                   DATE         NOT NULL,
  evaluador               VARCHAR(100) NOT NULL,               -- nombre de la sesión que evalúa
  evaluador_id            INT(11)      NULL,
  criterios               TEXT         NOT NULL,               -- JSON: [{categoria,item,puntaje,observaciones}]
  comentarios_categoria   TEXT         NULL,                   -- JSON: {A:texto, B:texto, C:texto}
  puntaje_total           INT(11)      NOT NULL DEFAULT 0,      -- suma de puntajes (para listado/KPIs)
  comentarios_generales   TEXT         NULL,
  created_at              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ed_fecha (fecha),
  KEY ix_ed_colaborador (colaborador_id),
  CONSTRAINT fk_ed_colaborador FOREIGN KEY (colaborador_id)
     REFERENCES colaboradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
