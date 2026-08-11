-- PORTALLY SYSTEM · 026 · Respuestas de sugerencias por WhatsApp
-- Ejecutar después de 025_colaboradores_celular_peru.sql.

USE portally_system;

ALTER TABLE sugerencias_tallyman
  ADD COLUMN respuesta_whatsapp TEXT NULL AFTER puntaje_at,
  ADD COLUMN respuesta_whatsapp_por VARCHAR(100) NULL AFTER respuesta_whatsapp,
  ADD COLUMN respuesta_whatsapp_at TIMESTAMP NULL AFTER respuesta_whatsapp_por,
  ADD COLUMN respuesta_whatsapp_message_id VARCHAR(120) NULL AFTER respuesta_whatsapp_at;
