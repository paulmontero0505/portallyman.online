-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 026 · Calificación de propuestas: viabilidad + impacto
-- ────────────────────────────────────────────────────────────────────
-- El módulo guardaba un único `puntaje` 1-10 cuya escala mezclaba dos
-- dimensiones distintas:
--     1-4  no viable, sin impacto
--     5-8  impacto mínimo, viable
--     9-10 impacto alto, viable
-- Colapsadas en un solo eje, no había número capaz de describir una
-- propuesta de ALTO IMPACTO que hoy NO ES VIABLE. Se separan:
--
--   · viabilidad TINYINT 1-10           → ¿se puede hacer?
--   · impacto    ENUM(minimo/medio/alto) → ¿cuánto aporta si se hace?
--
-- Son INDEPENDIENTES: viabilidad=2 + impacto='alto' es un registro válido
-- y esperado. No hay CHECK ni regla que ate uno al otro.
--
-- El cuadrante de decisión (Quick win / Apuesta / Descartar…) se DERIVA
-- en la UI a partir de ambos y no se persiste: cambiar los umbrales más
-- adelante no obliga a migrar ni un registro.
--
-- MIGRACIÓN DE DATOS: el valor de `puntaje` se conserva tal cual y pasa a
-- leerse como viabilidad; `impacto` queda NULL («Pendiente de impacto»).
-- No se infiere el impacto desde la escala vieja: un 7 puesto pensando
-- «viable, impacto medio» quedaría marcado como Mínimo, y esa data
-- incorrecta sería indistinguible de la correcta. Un NULL visible es
-- honesto y accionable.
--
-- Idempotente: sólo aplica cada DDL si aún no está aplicado.
-- Se ejecuta sobre la base YA seleccionada (en phpMyAdmin: elige la base
-- antes de importar). No lleva "USE" para funcionar en local y en el
-- servidor sin importar el nombre de la base.
-- ════════════════════════════════════════════════════════════════════

-- ─── puntaje → viabilidad ───────────────────────────────────────────
-- Se renombra en vez de conservar el nombre: si `puntaje` sobreviviera,
-- cualquier consulta o export viejo seguiría leyendo el número con el
-- significado anterior sin que nada avise. El rename hace que el cambio
-- de semántica rompa ruidosamente lo que no se actualizó.
SET @tiene_puntaje := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'sugerencias_tallyman'
     AND COLUMN_NAME  = 'puntaje'
);
SET @ddl := IF(@tiene_puntaje = 1,
  'ALTER TABLE sugerencias_tallyman CHANGE COLUMN puntaje viabilidad TINYINT NULL',
  'SELECT "puntaje ya fue renombrado a viabilidad" AS info');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── Nueva columna impacto ──────────────────────────────────────────
-- NULL-able a propósito: sin eso la migración obligaría a inventar un
-- valor para las propuestas ya calificadas con la escala vieja.
SET @tiene_impacto := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'sugerencias_tallyman'
     AND COLUMN_NAME  = 'impacto'
);
SET @ddl := IF(@tiene_impacto = 0,
  'ALTER TABLE sugerencias_tallyman
     ADD COLUMN impacto ENUM(''minimo'',''medio'',''alto'') NULL AFTER viabilidad',
  'SELECT "impacto ya existe" AS info');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Nota: puntaje_comentario, puntaje_por y puntaje_at NO se renombran.
-- Describen el ACTO de calificar (quién, cuándo, con qué nota al margen),
-- no el número. Lo que se renombra es lo que cambió de significado.

-- Verificación
-- SHOW COLUMNS FROM sugerencias_tallyman;
-- SELECT id, canal, viabilidad, impacto, puntaje_por
--   FROM sugerencias_tallyman WHERE canal = 'propuesta';
