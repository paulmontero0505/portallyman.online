-- ============================================================
-- Operaciones · Fase 2 — Campos dinámicos por tipo de nave
-- Se aplica una vez sobre la BD de la Fase 1.
-- ============================================================
USE operaciones;

-- Catálogo de definiciones de campo por tipo (gestionado por el Administrador).
CREATE TABLE IF NOT EXISTS campos_tipo_nave (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo_nave_id  INT UNSIGNED NOT NULL,
  clave         VARCHAR(50)  NOT NULL,             -- nombre máquina, ej. 'teus'
  etiqueta      VARCHAR(100) NOT NULL,             -- label visible, ej. 'TEUs'
  tipo_dato     ENUM('texto','numero','fecha','booleano','seleccion')
                NOT NULL DEFAULT 'texto',
  requerido     TINYINT(1)   NOT NULL DEFAULT 0,
  opciones      JSON         NULL,                 -- solo 'seleccion': ["A","B"]
  orden         INT          NOT NULL DEFAULT 0,
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_campos_tipo_nave
    FOREIGN KEY (tipo_nave_id) REFERENCES tipos_nave (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  UNIQUE KEY uq_campo_tipo_clave (tipo_nave_id, clave),
  KEY idx_campos_tipo (tipo_nave_id, activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valores por nave (objeto clave→valor, validado en la app contra el catálogo).
ALTER TABLE naves ADD COLUMN datos_adicionales JSON NULL AFTER estado;
