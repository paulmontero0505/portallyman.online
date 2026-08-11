<?php
require_once('includes/auth.php');
require_once('includes/sheets.php');
require_login();
$sheetUrl = defined('SHEETS_SHEET_URL') ? SHEETS_SHEET_URL : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Turno Actual · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="img/logo.jpg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="css/header.css?v=<?= filemtime(__DIR__.'/css/header.css') ?>">
  <link rel="stylesheet" href="css/sidebar.css?v=<?= filemtime(__DIR__.'/css/sidebar.css') ?>">
  <link rel="stylesheet" href="css/layout.css?v=<?= filemtime(__DIR__.'/css/layout.css') ?>">
  <link rel="stylesheet" href="css/ui.css?v=<?= filemtime(__DIR__.'/css/ui.css') ?>">
  <link rel="stylesheet" href="css/estiba.css?v=<?= filemtime(__DIR__.'/css/estiba.css') ?>">

  <style>
    /* Reset extra + override: el contenido scrollea dentro de .content */
    .content { padding: 24px 28px 60px; overflow-y: auto; }

    /* ── Botón "Añadir personal" en la toolbar ── */
    .est-add-btn { white-space: nowrap; }
    .est-add-btn svg { width: 16px; height: 16px; }

    /* ── Selector de turno (fecha + jornada) en el hero ── */
    .est-turno-switch {
      display: inline-flex; align-items: center; gap: 8px; margin-top: 10px;
      padding: 4px 6px; border-radius: 10px;
      background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.22);
    }
    .est-turno-cap {
      font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
      color: rgba(255, 255, 255, 0.75); padding-left: 4px;
    }
    .est-turno-date, .est-turno-jsel {
      border: 1px solid rgba(255, 255, 255, 0.30); background: rgba(0,0,0,.15); color: #fff;
      font: inherit; font-size: 12.5px; font-weight: 600; border-radius: 7px; padding: 5px 8px; outline: none;
      cursor: pointer;
    }
    .est-turno-date::-webkit-calendar-picker-indicator { cursor: pointer; filter: invert(1); }
    .est-turno-jsel { padding-right: 6px; }
    .est-turno-jsel option { background: #005c3d; color: #fff; }

    /* ── MOBILE: turno switch compacto ── */
    @media (max-width: 768px) {
      .est-turno-switch {
        display: flex; flex-wrap: wrap; gap: 6px; width: 100%; box-sizing: border-box;
      }
      .est-turno-cap { display: none; }
      .est-turno-date { flex: 1 1 120px; font-size: 13px; min-width: 0; }
      .est-turno-jsel { flex: 1 1 140px; font-size: 13px; min-width: 0; max-width: 100%; }
      .est-turno-cerrar { flex: 0 0 auto; font-size: 11px; padding: 5px 9px; }
    }

    .est-turno-fecha {
      font-size: 12px; font-weight: 600; color: rgba(255, 255, 255, 0.80); letter-spacing: .02em;
    }

    /* Selector de jornada dentro del modal (fondo claro) */
    .est-add-jornada { display: flex; align-items: center; gap: 12px; }
    .est-add-jornada > label {
      font-size: 10.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--co-mute);
    }
    .est-add-jsel {
      border: 1px solid #cbd5e1; background: #f8fafc; color: #1e293b;
      font: inherit; font-size: 13.5px; font-weight: 600; border-radius: 8px; padding: 7px 10px; outline: none; cursor: pointer;
    }
    .est-add-jsel:focus { border-color: #00875A; }
    .est-add-jornada .est-turno-fecha { color: #1e293b; }

    /* Estado del turno + cerrar/validar */
    .est-turno-estado {
      font-size: 10px; font-weight: 800; letter-spacing: .1em; color: #fff;
      background: #ef4444; padding: 4px 9px; border-radius: 6px;
      box-shadow: 0 2px 6px rgba(239, 68, 68, 0.2);
    }
    .est-turno-cerrar {
      border: 1px solid rgba(239,68,68,.30); background: rgba(239,68,68,.12); color: #ef4444;
      font: inherit; font-size: 11.5px; font-weight: 700; border-radius: 7px; padding: 5px 11px; cursor: pointer;
    }
    .est-turno-cerrar:hover { background: rgba(239,68,68,.18); }

    /* Modal ingreso de turno */
    .est-ingreso-fecha { font-size: 13.5px; color: #475569; margin-bottom: 4px; }
    .est-ingreso-fecha strong { color: #002b5c; }
    .est-ingreso-jornadas { display: flex; flex-direction: column; gap: 8px; }
    .est-ingreso-jornadas button {
      display: flex; align-items: center; justify-content: space-between; gap: 10px;
      border: 1px solid #cbd5e1; background: #fff; color: #0b1f3a; cursor: pointer;
      font: inherit; font-size: 15px; font-weight: 700; border-radius: 11px; padding: 14px 16px;
      transition: background .12s, border-color .12s;
    }
    .est-ingreso-jornadas button:hover { background: #f0f6ff; border-color: #002b5c; }
    .est-ingreso-jornadas button .hrs { font-size: 12.5px; font-weight: 600; color: #64748b; }

    /* Acciones / movimientos del turno (panel lateral) */
    #sideAcciones .est-acc-item {
      display: flex; gap: 8px; padding: 7px 0; border-bottom: 1px solid #eef2f7; font-size: 12px;
    }
    #sideAcciones .est-acc-item:last-child { border-bottom: 0; }
    #sideAcciones .est-acc-hora { color: #94a3b8; font-variant-numeric: tabular-nums; min-width: 38px; }
    #sideAcciones .est-acc-txt { color: #334155; }
    #sideAcciones .est-acc-txt b { color: #0b1f3a; }
    .est-empty-acc { color: #94a3b8; font-size: 12px; padding: 6px 0; }

    /* ── Estado vacío del turno ── */
    .est-empty-turno {
      display: flex; flex-direction: column; align-items: center; gap: 8px;
      padding: 56px 24px; text-align: center;
    }
    .est-empty-turno .est-empty-title { font-size: 17px; font-weight: 700; color: #0b1f3a; }
    .est-empty-turno .est-empty-sub   { font-size: 13.5px; color: #64748b; max-width: 420px; margin-bottom: 8px; }

    /* ── Botón peligro (quitar del turno) ── */
    .est-btn-danger {
      margin-top: 6px; width: 100%; justify-content: center;
      color: #b42318; border-color: #fecdca; background: #fef3f2;
    }
    .est-btn-danger:hover { background: #fee4e2; border-color: #fda29b; }
    .est-btn-danger svg { width: 15px; height: 15px; }

    /* ── Tarjetas del drawer: diferencian "cambio de puesto" vs "evento" ── */
    .est-card {
      position: relative; overflow: hidden;
      border: 1px solid var(--co-line); border-radius: 14px;
      background: #fff; padding: 16px 16px 16px 18px;
    }
    .est-card::before {           /* franja de acento al costado */
      content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
      background: var(--accent, var(--co-navy));
    }
    .est-card + .est-card { margin-top: 2px; }
    .est-card--cambio { --accent: #00875A; --accent-solid: #00875A; }
    .est-card--evento { --accent: #F79009; --accent-solid: #B54708; }
    .est-card--log    { --accent: #cbd5e1; --accent-solid: #64748b; }

    .est-card-head { display: flex; align-items: flex-start; gap: 11px; margin-bottom: 14px; }
    .est-card-ic {
      width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
      display: grid; place-items: center; color: #fff;
      background: var(--accent-solid, var(--co-navy));
    }
    .est-card-ic svg { width: 18px; height: 18px; }
    .est-card .est-card-head h3 {
      margin: 0; font-size: 12.5px; letter-spacing: .07em; text-transform: uppercase;
      color: var(--co-ink); font-weight: 700;
    }
    .est-card-sub { margin: 3px 0 0; font-size: 11.5px; color: var(--co-mute); line-height: 1.4; }
    /* El botón "Añadir a bitácora" toma el ámbar para reforzar la diferencia */
    .est-card--evento .est-btn.primary { background: var(--accent-solid); border-color: var(--accent-solid); }
    .est-card--evento .est-btn.primary:hover { filter: brightness(.92); background: var(--accent-solid); border-color: var(--accent-solid); }

    /* ── Modal: añadir personal ── */
    .est-modal-back {
      position: fixed; inset: 0; background: rgba(2, 14, 33, .45);
      opacity: 0; visibility: hidden; transition: opacity .2s; z-index: 992;
    }
    .est-modal-back.open { opacity: 1; visibility: visible; }
    .est-modal {
      position: fixed; top: 50%; left: 50%; transform: translate(-50%, -46%);
      width: min(560px, calc(100vw - 32px)); max-height: min(82vh, 720px);
      background: #fff; border-radius: 18px; box-shadow: 0 24px 60px rgba(2, 14, 33, .28);
      display: flex; flex-direction: column; overflow: hidden;
      opacity: 0; visibility: hidden; transition: opacity .2s, transform .2s; z-index: 993;
      font-family: 'DM Sans', system-ui, sans-serif;
    }
    .est-modal.open { opacity: 1; visibility: visible; transform: translate(-50%, -50%); }
    .est-modal-head {
      display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
      padding: 18px 20px; border-bottom: 1px solid #e2e8f0;
    }
    .est-modal-head h2 { margin: 0; font-size: 17px; font-weight: 700; color: #005c3d; }
    .est-modal-sub { font-size: 12.5px; color: #64748b; }
    .est-modal-body { padding: 16px 20px 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; }
    .est-modal-search { width: 100%; }

    .est-add-list { display: flex; flex-direction: column; gap: 6px; max-height: 320px; overflow-y: auto; }
    .est-add-row {
      display: flex; align-items: center; gap: 11px;
      padding: 9px 11px; border: 1px solid #e2e8f0; border-radius: 11px; cursor: pointer;
      transition: background .12s, border-color .12s;
    }
    .est-add-row:hover { background: #f8fafc; }
    .est-add-row.sel { border-color: #00875A; background: #e6f3ed; }
    .est-add-row .est-card-icon { width: 34px; height: 34px; flex-shrink: 0; background: #e6f3ed; color: #00875A; }
    .est-add-row .col { display: flex; flex-direction: column; min-width: 0; }
    .est-add-row .name { font-weight: 600; font-size: 13.5px; color: #0b1f3a; }
    .est-add-row .meta { font-size: 11.5px; color: #64748b; }
    .est-add-check { margin-left: auto; color: #00875A; font-weight: 700; }

    .est-modal-sm { width: min(420px, calc(100vw - 32px)); }
    .est-rango-hint { font-size: 12.5px; color: #64748b; min-height: 18px; }
    .est-rango-hint.err { color: #b42318; }

    .est-add-form { border-top: 1px solid #e2e8f0; padding-top: 14px; display: flex; flex-direction: column; gap: 12px; }
    .est-add-selected { font-size: 13px; color: #64748b; }
    .est-add-selected strong { color: #005c3d; }

    /* ════════════════════════════════════════════════════════════════
       HIGH SPECIFICITY BUTTON STYLES (PREMIUM LIGHT & EMERALD GREEN)
    ════════════════════════════════════════════════════════════════ */

    /* 1. Primary Buttons: "Añadir personal" & "+ Añadir personal al turno" */
    body .est-wrap .est-btn.primary,
    body .est-modal .est-btn.primary,
    body .est-drawer .est-btn.primary,
    body .est-wrap #estEmptyAdd,
    body .est-modal .est-add-footer button.primary {
      background: linear-gradient(135deg, #00875A 0%, #005c3d 100%) !important;
      border: none !important;
      color: #ffffff !important;
      font-weight: 600 !important;
      border-radius: 10px !important;
      box-shadow: 0 4px 15px rgba(0, 135, 90, 0.2) !important;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 7px !important;
      cursor: pointer !important;
    }
    body .est-wrap .est-btn.primary:hover,
    body .est-modal .est-btn.primary:hover,
    body .est-drawer .est-btn.primary:hover,
    body .est-wrap #estEmptyAdd:hover,
    body .est-modal .est-add-footer button.primary:hover {
      transform: translateY(-1.5px) !important;
      box-shadow: 0 6px 20px rgba(0, 135, 90, 0.35) !important;
      opacity: 0.95 !important;
    }
    body .est-wrap .est-btn.primary:active,
    body .est-modal .est-btn.primary:active,
    body .est-drawer .est-btn.primary:active,
    body .est-wrap #estEmptyAdd:active,
    body .est-modal .est-add-footer button.primary:active {
      transform: translateY(0.5px) !important;
    }

    /* 2. Refrigerio Masivo Button (Emerald Green) */
    body .est-wrap #estRefMasivoBtn,
    body .est-modal #refMasivoGuardar {
      background: linear-gradient(135deg, #00875A 0%, #005c3d 100%) !important;
      border: none !important;
      color: #ffffff !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      box-shadow: 0 4px 12px rgba(0, 135, 90, 0.2) !important;
      transition: all 0.2s ease !important;
    }
    body .est-wrap #estRefMasivoBtn:hover,
    body .est-modal #refMasivoGuardar:hover {
      transform: translateY(-1px) !important;
      box-shadow: 0 6px 15px rgba(0, 135, 90, 0.35) !important;
    }
    body .est-wrap #estRefMasivoBtn:active,
    body .est-modal #refMasivoGuardar:active {
      transform: translateY(0.5px) !important;
    }

    /* 3. Export PDF Button (Red) */
    body .est-wrap #estExportPDFBtn,
    body .est-wrap #rangoGenerar {
      background: rgba(220, 38, 38, 0.08) !important;
      border: 1px solid rgba(220, 38, 38, 0.3) !important;
      color: #dc2626 !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      box-shadow: 0 2px 10px rgba(220, 38, 38, 0.05) !important;
      transition: all 0.2s ease !important;
    }
    body .est-wrap #estExportPDFBtn:hover,
    body .est-wrap #rangoGenerar:hover {
      background: #dc2626 !important;
      color: #ffffff !important;
      box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25) !important;
      transform: translateY(-1px) !important;
    }
    body .est-wrap #estExportPDFBtn:active,
    body .est-wrap #rangoGenerar:active {
      transform: translateY(0.5px) !important;
    }

    /* 4. Google Sheets Button (Green) */
    body .est-wrap a.est-btn[href*="docs.google.com"],
    body .est-wrap a.est-btn[href*="sheet"] {
      background: rgba(16, 185, 129, 0.08) !important;
      border: 1px solid rgba(16, 185, 129, 0.3) !important;
      color: #059669 !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      text-decoration: none !important;
      box-shadow: 0 2px 10px rgba(16, 185, 129, 0.05) !important;
      transition: all 0.2s ease !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 7px !important;
    }
    body .est-wrap a.est-btn[href*="docs.google.com"]:hover,
    body .est-wrap a.est-btn[href*="sheet"]:hover {
      background: #059669 !important;
      color: #ffffff !important;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25) !important;
      transform: translateY(-1px) !important;
    }
    body .est-wrap a.est-btn[href*="docs.google.com"]:active,
    body .est-wrap a.est-btn[href*="sheet"]:active {
      transform: translateY(0.5px) !important;
    }

    /* 5. Sync Button & Excel & Rango Button (Teal/Emerald Green) */
    body .est-wrap #estSyncSheetBtn,
    body .est-wrap #estExportXLSBtn,
    body .est-wrap #estReporteRangoBtn {
      background: rgba(0, 135, 90, 0.06) !important;
      border: 1px solid rgba(0, 135, 90, 0.25) !important;
      color: #00875A !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      box-shadow: 0 2px 10px rgba(0, 135, 90, 0.05) !important;
      transition: all 0.2s ease !important;
    }
    body .est-wrap #estSyncSheetBtn:hover,
    body .est-wrap #estExportXLSBtn:hover,
    body .est-wrap #estReporteRangoBtn:hover {
      background: #00875A !important;
      color: #ffffff !important;
      box-shadow: 0 4px 12px rgba(0, 135, 90, 0.25) !important;
      transform: translateY(-1px) !important;
    }
    body .est-wrap #estSyncSheetBtn:active,
    body .est-wrap #estExportXLSBtn:active,
    body .est-wrap #estReporteRangoBtn:active {
      transform: translateY(0.5px) !important;
    }

    /* 6. Cancel / Secondary Modal Buttons (Clean light design) */
    body .est-modal .est-btn:not(.primary):not(#refMasivoGuardar),
    body .est-drawer .est-btn:not(.primary):not(.est-btn-danger) {
      background: #f3f4f6 !important;
      border: 1px solid #d1d5db !important;
      color: #4b5563 !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      transition: all 0.2s ease !important;
    }
    body .est-modal .est-btn:not(.primary):not(#refMasivoGuardar):hover,
    body .est-drawer .est-btn:not(.primary):not(.est-btn-danger):hover {
      background: #e5e7eb !important;
      border-color: #9ca3af !important;
      color: #1f2937 !important;
    }

    /* 7. Quitar del turno Button in Drawer (Red outline & hover danger solid) */
    body .est-drawer .est-btn-danger,
    body .est-wrap .est-btn-danger {
      background: rgba(220, 38, 38, 0.06) !important;
      border: 1px solid rgba(220, 38, 38, 0.25) !important;
      color: #dc2626 !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      transition: all 0.2s ease !important;
    }
    body .est-drawer .est-btn-danger:hover,
    body .est-wrap .est-btn-danger:hover {
      background: #dc2626 !important;
      border-color: #dc2626 !important;
      color: #ffffff !important;
      box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25) !important;
      transform: translateY(-1px) !important;
    }
    body .est-drawer .est-btn-danger:active,
    body .est-wrap .est-btn-danger:active {
      transform: translateY(0.5px) !important;
    }

    /* Style improvements for "Añadir personal al turno" modal search box & toolbar */
    body .est-modal .est-search {
      background-color: #f8fafc !important;
      border: 1.5px solid #e2e8f0 !important;
      border-radius: 10px !important;
      padding: 9px 12px !important;
      transition: all 0.2s ease !important;
      box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05) !important;
    }
    body .est-modal .est-search:focus-within {
      background-color: #ffffff !important;
      border-color: #00875A !important;
      box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.12) !important;
    }
    body .est-modal #addSearch {
      color: #0f172a !important;
      font-size: 13.5px !important;
      font-family: inherit !important;
      font-weight: 500 !important;
    }
    body .est-modal #addSearch::placeholder {
      color: #94a3b8 !important;
    }
    body .est-modal .est-search svg {
      color: #64748b !important;
      transition: color 0.2s ease !important;
    }
    body .est-modal .est-search:focus-within svg {
      color: #00875A !important;
    }
    body .est-modal .est-add-loc select {
      background-color: #f8fafc !important;
      border: 1.5px solid #e2e8f0 !important;
      color: #0f172a !important;
      border-radius: 10px !important;
      padding: 9px 12px !important;
      font-family: inherit !important;
      font-weight: 500 !important;
      font-size: 13.5px !important;
      min-width: 140px !important;
      outline: none !important;
      transition: all 0.2s ease !important;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
    }
    body .est-modal .est-add-loc select:focus {
      background-color: #ffffff !important;
      border-color: #00875A !important;
      box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.12) !important;
    }
    body .est-modal .est-add-selbar {
      background-color: #f8fafc !important;
      border-top: 1px solid #e2e8f0 !important;
      border-bottom: 1px solid #e2e8f0 !important;
      padding: 10px 20px !important;
      font-size: 13px !important;
      font-weight: 600 !important;
      color: #475569 !important;
    }
    body .est-modal .est-add-list {
      padding: 12px 20px !important;
      gap: 8px !important;
      scrollbar-width: thin !important;
      scrollbar-color: #cbd5e1 transparent !important;
    }
    body .est-modal .est-add-row {
      border: 1px solid #f1f5f9 !important;
      border-radius: 12px !important;
      padding: 10px 14px !important;
      transition: all 0.2s ease !important;
      background-color: #ffffff !important;
    }
    body .est-modal .est-add-row:hover:not(.blocked) {
      background-color: #f8fafc !important;
      border-color: #e2e8f0 !important;
      transform: translateX(2px) !important;
    }
    body .est-modal .est-add-row.sel {
      background-color: #e6f3ed !important;
      border-color: #a3d9c9 !important;
    }
    body .est-modal .est-add-row .name {
      color: #0f172a !important;
      font-size: 14px !important;
    }
    body .est-modal .est-add-row .meta {
      color: #64748b !important;
    }
    body .est-modal .est-add-row .est-card-icon {
      background: #e6f3ed !important;
      color: #00875A !important;
      border-radius: 8px !important;
      width: 36px !important;
      height: 36px !important;
      display: grid !important;
      place-items: center !important;
    }
    body .est-modal .est-add-chk {
      width: 20px !important;
      height: 20px !important;
      border-radius: 6px !important;
      border: 1.5px solid #cbd5e1 !important;
      background-color: #ffffff !important;
      display: grid !important;
      place-items: center !important;
      transition: all 0.2s ease !important;
    }
    body .est-modal .est-add-row.sel .est-add-chk {
      background-color: #00875A !important;
      border-color: #00875A !important;
    }
    body .est-modal .est-add-row.sel .est-add-chk svg {
      stroke: #ffffff !important;
    }
    body .est-modal .est-add-footer button.primary {
      background: linear-gradient(135deg, #00875A 0%, #005c3d 100%) !important;
      border: none !important;
      color: #ffffff !important;
      font-weight: 600 !important;
      border-radius: 10px !important;
      padding: 12px 20px !important;
      box-shadow: 0 4px 12px rgba(0, 135, 90, 0.2) !important;
      transition: all 0.25s ease !important;
      cursor: pointer !important;
    }
    body .est-modal .est-add-footer button.primary:hover:not(:disabled) {
      transform: translateY(-1.5px) !important;
      box-shadow: 0 6px 18px rgba(0, 135, 90, 0.3) !important;
      opacity: 0.95 !important;
    }
    body .est-modal .est-add-footer button.primary:active:not(:disabled) {
      transform: translateY(0.5px) !important;
    }
    body .est-modal .est-add-footer button.primary:disabled {
      background: #e2e8f0 !important;
      color: #94a3b8 !important;
      box-shadow: none !important;
      cursor: not-allowed !important;
      transform: none !important;
    }

    /* Fix styles for Timeline and Empty states inside the white Drawer */
    body .est-drawer .est-empty {
      background-color: #f8fafc !important;
      border: 1.5px dashed #cbd5e1 !important;
      color: #64748b !important;
      border-radius: 12px !important;
      font-weight: 500 !important;
    }
    body .est-drawer .est-timeline {
      border-left-color: #e2e8f0 !important;
    }
    body .est-drawer .est-event {
      background-color: #f8fafc !important;
      border: 1px solid #e2e8f0 !important;
      border-left: 3.5px solid #cbd5e1 !important;
      border-radius: 12px !important;
      color: #0f172a !important;
    }
    body .est-drawer .est-event[data-tipo="refrigerio"] {
      border-left-color: #fbbf24 !important;
    }
    body .est-drawer .est-event[data-tipo="traslado"] {
      border-left-color: #0ea5e9 !important;
    }
    body .est-drawer .est-event[data-tipo="permiso"] {
      border-left-color: #ef4444 !important;
    }
    body .est-drawer .est-event-tipo {
      color: #0f172a !important;
      font-weight: 700 !important;
    }
    body .est-drawer .est-event-time {
      color: #475569 !important;
      font-weight: 600 !important;
    }
    body .est-drawer .est-event-obs {
      color: #475569 !important;
    }
    body .est-drawer .est-event::before {
      background-color: #ffffff !important;
      border-color: #cbd5e1 !important;
    }
    body .est-drawer .est-event[data-tipo="refrigerio"]::before {
      border-color: #fbbf24 !important;
    }
    body .est-drawer .est-event[data-tipo="traslado"]::before {
      border-color: #0ea5e9 !important;
    }
    body .est-drawer .est-event[data-tipo="permiso"]::before {
      border-color: #ef4444 !important;
    }
  </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="shell">
  <?php
    $sb_base = '.'; // index.php está en la raíz
    include('includes/sidebar.php');
  ?>

  <div class="main-area">
    <?php include('includes/header.php'); ?>

    <main class="content">

      <!-- ════════════════════════════════════════════════════════════════════
           CONTENEDOR RAÍZ DEL MÓDULO
      ════════════════════════════════════════════════════════════════════ -->
      <section class="est-wrap" id="moduloEstibaContenedor">

        <!-- HERO -->
        <header class="est-hero">
          <div>
            <span class="tag"><span class="pulse"></span> Turno en curso · <span id="estTurnoLabel">—</span></span>
            <div class="est-turno-switch" id="estTurnoSwitch">
              <span class="est-turno-cap">Turno</span>
              <input type="date" id="estTurnoFechaInput" class="est-turno-date">
              <select id="estTurnoJornadaSel" class="est-turno-jsel" title="Jornada"></select>
              <span class="est-turno-estado" id="estTurnoEstado" style="display:none">CERRADO</span>
              <button type="button" class="est-turno-cerrar" id="estCerrarBtn" style="display:none">Cerrar turno</button>
            </div>
            <h1>Shift Command Deck · Estiba</h1>
            <p>Asignación, monitoreo y bitácora cronológica del personal de estiba en turno. Estado del personal en tiempo real, eventos registrados manualmente, exportación lista para parte de turno.</p>
            <a href="registro.php" target="_blank" rel="noopener" id="estSelfRegLink"
               style="display:inline-flex;align-items:center;gap:8px;margin-top:12px;padding:10px 16px;border-radius:10px;
                      background:rgba(255,255,255,0.14);border:1px solid rgba(255,255,255,0.28);color:#fff;
                      font-size:13px;font-weight:700;text-decoration:none;backdrop-filter:blur(6px);transition:background .2s"
               onmouseover="this.style.background='rgba(255,255,255,0.24)'"
               onmouseout="this.style.background='rgba(255,255,255,0.14)'"
               title="Página pública para que los colaboradores marquen su ingreso y refrigerio">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
              Auto-registro de personal
            </a>
          </div>
          <div class="est-hero-kpis">
            <div class="est-kpi"><div class="lbl">En turno</div><div class="val" id="kpiTotal">0</div></div>
            <div class="est-kpi kpi-wn"><div class="lbl">Refrigerio</div><div class="val" id="kpiRefri">0</div></div>
            <div class="est-kpi kpi-er"><div class="lbl">Inactivos</div><div class="val" id="kpiInc">0</div></div>
            <div class="est-kpi kpi-tr"><div class="lbl">Traslados</div><div class="val" id="kpiTraslados">0</div></div>
            <div class="est-kpi kpi-pm"><div class="lbl">Permisos</div><div class="val" id="kpiPermisos">0</div></div>
            <div class="est-kpi kpi-al" id="kpiAlertWrap"><div class="lbl">Alertas tiempo</div><div class="val" id="kpiAlertas">0</div></div>
          </div>
        </header>

        <!-- PANEL: TURNO ACTUAL -->
        <div class="est-panel" id="panelTurno">

          <div class="est-layout-2col" id="estLayout2col">
            <div class="est-layout-main">

              <!-- RESUMEN VISUAL · 3 mini-charts -->
              <div class="est-resumen-row" id="estResumen"></div>

              <!-- TOOLBAR -->
              <div class="est-toolbar">
                <div class="est-search">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                  <input id="estSearch" type="text" placeholder="Buscar por nombre, función o ubicación…">
                </div>
                <button class="est-btn primary est-add-btn" id="estAddBtn" title="Colocar un colaborador del catálogo en este turno">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                  Añadir personal
                </button>
                <button class="est-btn" id="estRefMasivoBtn" title="Registrar refrigerio a todo el personal activo" style="background:#00875A;border-color:#00875A;color:#fff;gap:6px">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
                  Refrigerio masivo
                </button>
                <button class="est-btn" id="estRefCierreBtn" title="Poner hora fin a los refrigerios abiertos en lote" style="background:#fff;border-color:#d97706;color:#b45309;gap:6px">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
                  Cerrar refrigerio
                </button>
                <button class="est-btn est-btn-danger" id="estQuitarMasivoBtn" title="Quitar personal del turno en lote" style="gap:6px">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                  Quitar personal
                </button>
                <div class="est-filter" id="estFilter">
                  <button class="active" data-f="todos">Todos</button>
                  <button data-f="activo">Activos</button>
                  <button data-f="refrigerio">Refrigerio</button>
                  <button data-f="traslado">Traslados</button>
                  <button data-f="permiso">Permisos</button>
                  <button data-f="inactivo">Inactivos</button>
                </div>

                <div class="est-tool-group">
                  <button class="est-tool-trigger" id="estGroupBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    <span>Agrupar: <strong id="estGroupLabel">Ninguno</strong></span>
                  </button>
                  <div class="est-tool-menu" id="estGroupMenu">
                    <button data-grp="none" class="sel">Sin agrupar</button>
                    <button data-grp="funcion">Por función</button>
                    <button data-grp="ubicacion">Por ubicación</button>
                    <button data-grp="estado">Por estado</button>
                  </div>
                </div>

                <div class="est-tool-group">
                  <button class="est-tool-trigger" id="estSortBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5h10"/><path d="M11 9h7"/><path d="M11 13h4"/><polyline points="3 17 6 20 9 17"/><line x1="6" y1="20" x2="6" y2="4"/></svg>
                    <span>Ordenar: <strong id="estSortLabel">Nombre</strong></span>
                  </button>
                  <div class="est-tool-menu" id="estSortMenu">
                    <button data-srt="nombre" class="sel">Nombre A→Z</button>
                    <button data-srt="alerta">Alerta primero</button>
                    <button data-srt="eventos">Más eventos</button>
                    <button data-srt="estado">Por estado</button>
                  </div>
                </div>

                <div class="est-view-toggle" id="estViewToggle">
                  <button data-view="grid" class="active" title="Vista tarjetas">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                  </button>
                  <button data-view="list" title="Vista lista">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                  </button>
                </div>

                <div class="est-export-split">
                  <button class="est-btn primary" id="estExportPDFBtn" title="Generar parte de turno en PDF">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 13h6"/><path d="M9 17h4"/></svg>
                    PDF
                  </button>
                  <!-- Exportación a Excel OCULTA (se conserva el código; los datos viven en Google Sheets). -->
                  <button class="est-btn" id="estExportXLSBtn" style="display:none" title="Excel del turno actual: cierre, indicadores, conteo y bitácora">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="19"/><line x1="15" y1="13" x2="9" y2="19"/></svg>
                    Excel
                  </button>
                  <button class="est-btn" id="estReporteRangoBtn" style="display:none" title="Reporte consolidado de varios días/turnos">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Reporte rango
                  </button>
                  <?php if ($sheetUrl !== ''): ?>
                  <a class="est-btn" href="<?= htmlspecialchars($sheetUrl) ?>" target="_blank" rel="noopener" title="Abrir la Google Sheet con los datos en vivo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
                    Google Sheet
                  </a>
                  <?php endif; ?>
                  <?php if (is_admin()): ?>
                  <button class="est-btn" id="estSyncSheetBtn" title="Enviar todos los datos existentes a Google Sheets (sincronización inicial)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    Sincronizar
                  </button>
                  <?php endif; ?>
                </div>
              </div>

              <!-- GRID (vista tarjetas) -->
              <div class="est-grid" id="estGrid"></div>

              <!-- LISTA (vista compacta) -->
              <div class="est-list-wrap" id="estListWrap" style="display:none">
                <table class="est-list">
                  <thead>
                    <tr>
                      <th>Colaborador</th>
                      <th>Función</th>
                      <th>Ubicación</th>
                      <th>Estado</th>
                      <th class="num">Traslados</th>
                      <th class="num">Refrigerios</th>
                      <th class="num">Permisos</th>
                      <th class="num">Min. estado</th>
                      <th>Alerta</th>
                    </tr>
                  </thead>
                  <tbody id="estListBody"></tbody>
                </table>
              </div>

            </div>

          </div>

        </div>

        <!-- DRAWER -->
        <div class="est-drawer-back" id="estDrawerBack"></div>
        <aside class="est-drawer" id="estDrawer" aria-hidden="true">
          <div class="est-drawer-head">
            <div>
              <h2 id="dwName">—</h2>
              <span class="id" id="dwId">—</span>
            </div>
            <button class="est-drawer-close" id="dwClose" aria-label="Cerrar">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="est-drawer-body">
            <div id="dwFotoWrap" style="display:none;margin-bottom:14px">
              <div style="font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#64748b;margin-bottom:6px">Foto de asistencia · auto-registro</div>
              <a id="dwFotoLink" href="#" target="_blank" rel="noopener">
                <img id="dwFoto" alt="Foto de asistencia" style="width:100%;max-height:240px;object-fit:cover;border-radius:12px;border:1px solid #e2e8f0;display:block">
              </a>
            </div>
            <div class="est-drawer-alert" id="dwAlert" style="display:none">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              <span id="dwAlertText">—</span>
            </div>

            <!-- ① CAMBIO DE PUESTO / ESTADO · modifica la asignación del colaborador -->
            <div class="est-drawer-section est-card est-card--cambio">
              <div class="est-card-head">
                <span class="est-card-ic">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                </span>
                <div>
                  <h3>Cambio de puesto / estado</h3>
                  <p class="est-card-sub">Reasigna función, ubicación o estado del colaborador. Queda en la auditoría del turno.</p>
                </div>
              </div>
              <div class="est-row2">
                <div class="est-field">
                  <label>Función</label>
                  <select id="dwFuncion"></select>
                </div>
                <div class="est-field">
                  <label>Ubicación</label>
                  <select id="dwUbicacion"></select>
                </div>
              </div>
              <!-- Nave a la que atiende. Se habilita sólo si la ubicación es un
                   muelle; en cualquier otra posición no aplica. -->
              <div class="est-field" id="dwNaveWrap">
                <label>Nave</label>
                <select id="dwNave"></select>
              </div>
              <div class="est-field">
                <label>Horario / Jornada</label>
                <select id="dwHorario"></select>
              </div>
              <div class="est-row2">
                <div class="est-field">
                  <label>Estado</label>
                  <select id="dwEstado">
                    <option value="traslado">Traslado</option>
                    <option value="refrigerio">Refrigerio</option>
                    <option value="permiso">Permiso</option>
                  </select>
                </div>
                <div class="est-field">
                  <label>Radio</label>
                  <select id="dwRadio">
                    <option value="0">Sin radio</option>
                    <option value="1">Con radio</option>
                  </select>
                </div>
              </div>
              <!-- campos hora/motivo: visibles solo cuando estado ≠ activo -->
              <div id="dwHoraWrap" style="display:none">
                <div class="est-row2" style="margin-top:8px">
                  <div class="est-field">
                    <label>Hora inicio</label>
                    <input type="time" id="evIni">
                  </div>
                  <div class="est-field">
                    <label>Hora fin</label>
                    <input type="time" id="evFin">
                  </div>
                </div>
                <div class="est-field" style="margin-top:8px">
                  <label>Observación (motivo)</label>
                  <select id="evMotivo"></select>
                </div>
                <div class="est-field" id="evOtrosWrap" style="display:none">
                  <label>Detalle</label>
                  <textarea id="evObs" placeholder="Especifica la observación"></textarea>
                </div>
              </div>
              <button class="est-btn primary" id="dwRegistrar">Registrar cambio</button>
              <button class="est-btn est-btn-danger" id="dwRemove" title="Sacar a este colaborador del turno">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                Quitar del turno
              </button>
            </div>

            <!-- ② CHECKLIST TALLYMAN · ítems libres (item + comentario) -->
            <div class="est-drawer-section est-card est-card--checklist" id="dwChecklistCard" style="display:none">
              <div class="est-card-head">
                <span class="est-card-ic">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </span>
                <div>
                  <h3>Checklist tallyman</h3>
                  <p class="est-card-sub">Registra ítems de actividad (ej. Tarja) con su comentario.</p>
                </div>
              </div>
              <div class="est-row2">
                <div class="est-field">
                  <label>Ítem</label>
                  <input type="text" id="dwCheckItem" placeholder="Ej. Tarja" maxlength="120">
                </div>
                <div class="est-field">
                  <label>Comentario</label>
                  <input type="text" id="dwCheckComentario" placeholder="Ej. Del 50001 al 50150" maxlength="500">
                </div>
              </div>
              <button class="est-btn primary" id="dwCheckAdd">Agregar ítem</button>
              <div id="dwChecklist"></div>
            </div>

            <div class="est-drawer-section est-card est-card--log">
              <div class="est-card-head">
                <span class="est-card-ic">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                </span>
                <div>
                  <h3>Bitácora cronológica</h3>
                  <p class="est-card-sub">Eventos registrados de este colaborador durante el turno.</p>
                </div>
              </div>
              <div id="dwTimeline"></div>
            </div>

          </div>
        </aside>

        <!-- MODAL · Refrigerio masivo -->
        <div class="est-modal-back" id="refMasivoBack"></div>
        <aside class="est-modal" id="refMasivoModal" aria-hidden="true" style="max-width:420px">
          <div class="est-modal-head">
            <div>
              <h2>Refrigerio masivo</h2>
              <span class="est-modal-sub">Registra el refrigerio a todo el personal activo del turno.</span>
            </div>
            <button class="est-modal-close" id="refMasivoClose">×</button>
          </div>
          <div style="padding:18px 20px;display:flex;flex-direction:column;gap:14px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="est-field">
                <label>Hora inicio</label>
                <input type="time" id="rmIni">
              </div>
              <div class="est-field">
                <label>Hora fin</label>
                <input type="time" id="rmFin">
              </div>
            </div>
            <div class="est-field">
              <label>Observación (motivo)</label>
              <select id="rmMotivo">
                <option value="Almuerzo">Almuerzo</option>
                <option value="Cena">Cena</option>
                <option value="Rancho frio">Rancho frio</option>
              </select>
            </div>
            <!-- Filtros de la lista (ubicación / función) -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="est-field">
                <label>Filtrar por ubicación</label>
                <select id="rmFiltroUbic"><option value="">Todas</option></select>
              </div>
              <div class="est-field">
                <label>Filtrar por función</label>
                <select id="rmFiltroFunc"><option value="">Todas</option></select>
              </div>
            </div>
            <!-- Lista de personal activo para seleccionar -->
            <div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <label style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em">Personal activo</label>
                <div style="display:flex;gap:8px">
                  <button type="button" id="rmSelAll" style="font-size:11px;font-weight:700;color:#0f4c81;background:none;border:none;cursor:pointer;padding:0">Todos</button>
                  <span style="color:#cbd5e1">·</span>
                  <button type="button" id="rmSelNone" style="font-size:11px;font-weight:700;color:#64748b;background:none;border:none;cursor:pointer;padding:0">Ninguno</button>
                </div>
              </div>
              <div id="rmLista" style="max-height:220px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc"></div>
            </div>
            <div id="rmInfo" style="font-size:12px;color:#6b7a8d;padding:7px 10px;background:#fff8ed;border-radius:8px;border:1px solid #fed7aa"></div>
          </div>
          <div style="padding:0 20px 18px;display:flex;gap:10px">
            <button class="est-btn" id="refMasivoCancel" style="flex:1">Cancelar</button>
            <button class="est-btn primary" id="refMasivoGuardar" style="flex:2;background:#00875A;border-color:#00875A">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
              Registrar refrigerio
            </button>
          </div>
        </aside>

        <!-- MODAL · Cierre de refrigerio masivo (poner hora fin) -->
        <div class="est-modal-back" id="rcBack"></div>
        <aside class="est-modal" id="rcModal" aria-hidden="true" style="max-width:420px">
          <div class="est-modal-head">
            <div>
              <h2>Cierre de refrigerio</h2>
              <span class="est-modal-sub">Pon la hora fin a los refrigerios que están abiertos.</span>
            </div>
            <button class="est-modal-close" id="rcClose">×</button>
          </div>
          <div style="padding:18px 20px;display:flex;flex-direction:column;gap:14px">
            <div class="est-field">
              <label>Hora fin</label>
              <input type="time" id="rcFin">
            </div>
            <!-- Filtros de la lista (ubicación / función) -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="est-field">
                <label>Filtrar por ubicación</label>
                <select id="rcFiltroUbic"><option value="">Todas</option></select>
              </div>
              <div class="est-field">
                <label>Filtrar por función</label>
                <select id="rcFiltroFunc"><option value="">Todas</option></select>
              </div>
            </div>
            <!-- Lista de personal con refrigerio abierto -->
            <div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <label style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em">Refrigerios abiertos</label>
                <div style="display:flex;gap:8px">
                  <button type="button" id="rcSelAll" style="font-size:11px;font-weight:700;color:#0f4c81;background:none;border:none;cursor:pointer;padding:0">Todos</button>
                  <span style="color:#cbd5e1">·</span>
                  <button type="button" id="rcSelNone" style="font-size:11px;font-weight:700;color:#64748b;background:none;border:none;cursor:pointer;padding:0">Ninguno</button>
                </div>
              </div>
              <div id="rcLista" style="max-height:220px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc"></div>
            </div>
            <div id="rcInfo" style="font-size:12px;color:#6b7a8d;padding:7px 10px;background:#fff8ed;border-radius:8px;border:1px solid #fed7aa"></div>
          </div>
          <div style="padding:0 20px 18px;display:flex;gap:10px">
            <button class="est-btn" id="rcCancel" style="flex:1">Cancelar</button>
            <button class="est-btn primary" id="rcGuardar" style="flex:2;background:#d97706;border-color:#d97706">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
              Cerrar refrigerio
            </button>
          </div>
        </aside>

        <!-- MODAL · Quitar personal del turno (masivo) -->
        <div class="est-modal-back" id="qmBack"></div>
        <aside class="est-modal" id="qmModal" aria-hidden="true" style="max-width:420px">
          <div class="est-modal-head">
            <div>
              <h2>Quitar personal del turno</h2>
              <span class="est-modal-sub">Selecciona los colaboradores a retirar. Se eliminará también su bitácora.</span>
            </div>
            <button class="est-modal-close" id="qmClose">×</button>
          </div>
          <div style="padding:18px 20px;display:flex;flex-direction:column;gap:14px">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <label style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em">Personal en turno</label>
              <div style="display:flex;gap:8px">
                <button type="button" id="qmSelAll" style="font-size:11px;font-weight:700;color:#ef4444;background:none;border:none;cursor:pointer;padding:0">Todos</button>
                <span style="color:#cbd5e1">·</span>
                <button type="button" id="qmSelNone" style="font-size:11px;font-weight:700;color:#64748b;background:none;border:none;cursor:pointer;padding:0">Ninguno</button>
              </div>
            </div>
            <div id="qmLista" style="max-height:300px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc"></div>
            <div id="qmInfo" style="font-size:12px;color:#6b7a8d;padding:7px 10px;background:#fff1f2;border-radius:8px;border:1px solid #fecdd3"></div>
          </div>
          <div style="padding:0 20px 18px;display:flex;gap:10px">
            <button class="est-btn" id="qmCancel" style="flex:1">Cancelar</button>
            <button class="est-btn est-btn-danger" id="qmConfirm" style="flex:2" disabled>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
              Quitar del turno
            </button>
          </div>
        </aside>

        <!-- MODAL · Añadir personal al turno (masivo) -->
        <div class="est-modal-back" id="addBack"></div>
        <aside class="est-modal est-modal--bulk" id="addModal" aria-hidden="true">
          <div class="est-modal-head">
            <div>
              <h2>Añadir personal al turno</h2>
              <span class="est-modal-sub">Selecciona personas y asigna una ubicación en bloque.</span>
            </div>
            <button class="est-drawer-close" id="addClose" aria-label="Cerrar">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div class="est-add-toolbar">
            <div class="est-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input id="addSearch" type="text" placeholder="Buscar por nombre, código, función o team…">
            </div>
            <div class="est-add-loc">
              <label>Ubicación</label>
              <select id="addUbicacion"></select>
            </div>
            <!-- Sólo se habilita cuando la ubicación es un muelle: el resto de
                 posiciones (Gate, Balanza, Administrativo…) no atienden nave. -->
            <div class="est-add-loc" id="addNaveWrap">
              <label>Nave</label>
              <select id="addNave"></select>
            </div>
          </div>
          <div class="est-add-horario">
            <label>Jornada de turno</label>
            <select id="addHorario"></select>
          </div>

          <div class="est-add-selbar">
            <span id="addSelCount">Ninguno seleccionado</span>
            <button class="est-add-selall" id="addSelAll" type="button">Todos</button>
            <button class="est-add-selclear" id="addSelClear" type="button">Limpiar</button>
          </div>

          <div class="est-add-list" id="addList"></div>

          <div class="est-add-footer">
            <button class="est-btn primary" id="addConfirm" type="button" disabled>+ Añadir al turno</button>
          </div>
        </aside>

        <!-- MODAL · Reporte consolidado por rango de fechas -->
        <div class="est-modal-back" id="rangoBack"></div>
        <aside class="est-modal est-modal-sm" id="rangoModal" aria-hidden="true">
          <div class="est-modal-head">
            <div>
              <h2>Reporte por rango de fechas</h2>
              <span class="est-modal-sub">Consolida los turnos guardados entre dos fechas (semana o más).</span>
            </div>
            <button class="est-drawer-close" id="rangoClose" aria-label="Cerrar">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="est-modal-body">
            <div class="est-row2">
              <div class="est-field"><label>Desde</label><input type="date" id="rangoDesde"></div>
              <div class="est-field"><label>Hasta</label><input type="date" id="rangoHasta"></div>
            </div>
            <div class="est-rango-hint" id="rangoHint"></div>
            <button class="est-btn primary" id="rangoGenerar">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Generar Excel consolidado
            </button>
          </div>
        </aside>

        <!-- MODAL · Ingreso de turno (Coordinador / Supervisor) -->
        <div class="est-modal-back" id="ingresoBack"></div>
        <aside class="est-modal est-modal-sm" id="ingresoModal" aria-hidden="true">
          <div class="est-modal-head">
            <div>
              <h2>Ingreso de turno</h2>
              <span class="est-modal-sub">Indica en qué turno ingresas. La fecha la toma el sistema.</span>
            </div>
          </div>
          <div class="est-modal-body">
            <div class="est-ingreso-fecha">Día de trabajo: <strong id="ingresoFecha">—</strong></div>
            <div class="est-ingreso-jornadas" id="ingresoJornadas"></div>
          </div>
        </aside>

        <div class="est-toast" id="estToast">—</div>

      </section>

    </main>
  </div>
</div>

<!-- Librerías de exportación -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.0/jspdf.plugin.autotable.min.js"></script>

<script>
  window.__ESTIBA_USER = {
    rol:    <?= json_encode($_SESSION['user_rol'] ?? null) ?>,
    nombre: <?= json_encode($_SESSION['user_name'] ?? null) ?>
  };
</script>
<script src="js/data-source.js?v=20260701a"></script>
<script src="js/estiba.js?v=20260719a"></script>

<script>
// Sincronización inicial a Google Sheets (solo admin; el botón solo existe para admin).
(function () {
  var btn = document.getElementById('estSyncSheetBtn');
  if (!btn) return;
  btn.addEventListener('click', async function () {
    if (!confirm('¿Enviar todos los datos existentes a Google Sheets? Esto reescribe las pestañas con el contenido actual.')) return;
    var original = btn.innerHTML;
    btn.disabled = true; btn.textContent = 'Sincronizando…';
    try {
      var res = await fetch('api/sheets_sync_all.php', { method: 'POST', cache: 'no-store' });
      var data = await res.json();
      if (data.success) {
        var det = Object.entries(data.resumen || {}).map(function (e) {
          var v = e[1] || {}; return (v.ok ? '✓' : '✗') + ' ' + e[0] + ': ' + (v.filas != null ? v.filas : v);
        }).join('\n');
        if (data.allOk === false) {
          alert('⚠ Algunas pestañas NO se enviaron a Google Sheets.\n\n' + det +
                '\n\nCausa típica: el Web App no está publicado con acceso "Cualquier usuario".\n' +
                'Revisa la implementación del Apps Script y logs/sheets.log.');
        } else {
          alert('Sincronización completa.\n\n' + det);
        }
      } else {
        alert('No se pudo sincronizar: ' + (data.error || 'error desconocido'));
      }
    } catch (e) {
      alert('Error de red durante la sincronización.');
    } finally {
      btn.disabled = false; btn.innerHTML = original;
    }
  });
})();
</script>

</body>
</html>
