<?php
/* ═══════════════════════════════════════════════════════════════════════
   PLANTILLA · Copia este archivo a  includes/sheets_config.php  y completa
   los valores. SIN este archivo (o con la URL vacía), el sistema funciona
   igual que siempre y simplemente NO envía nada a Google Sheets.

   Pasos:
     1) Crea una Google Sheet.
     2) Extensiones → Apps Script → pega el script de  docs/google-apps-script.gs
        y pon el mismo TOKEN que defines aquí abajo.
     3) Implementar → Nueva implementación → tipo "App web"
            · Ejecutar como: yo
            · Quién tiene acceso: cualquier persona con el enlace
        Copia la URL que termina en  /exec  → pégala en SHEETS_WEBAPP_URL.
     4) (Opcional) Copia el enlace de la hoja → SHEETS_SHEET_URL (para el botón
        "Abrir Google Sheet").
   ═══════════════════════════════════════════════════════════════════════ */

define('SHEETS_WEBAPP_URL', '');  // p. ej. https://script.google.com/macros/s/AKfy.../exec
define('SHEETS_TOKEN',      '');  // un secreto largo; DEBE coincidir con el TOKEN del Apps Script
define('SHEETS_SHEET_URL',  '');  // (opcional) enlace para abrir la hoja desde el sistema
define('DRIVE_FOLDER_ID',   '');  // (opcional) ID de la carpeta de Drive para evidencias de incidencias
