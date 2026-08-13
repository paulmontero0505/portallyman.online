<?php
require_once('../includes/auth.php');
require_once('../includes/db.php');
require_once('../includes/incidencias_catalogo.php');
require_report();

// Catálogos → JS (única fuente de verdad, definida en includes/incidencias_catalogo.php)
$JS_PUNTOS   = inc_puntos_competencia();   // punto => competencia
$JS_IMPACTOS = inc_impactos();             // clave => [label,color]
$JS_TURNOS   = inc_turnos();               // clave => label
$COORDINADOR = $_SESSION['user_name'] ?? '';
$ES_ADMINISTRADOR = is_admin();

// Zonas de trabajo activas (tabla ubicaciones). El formulario ofrece exactamente
// las que save_incidencia.php acepta.
$zona_ubicaciones = inc_zonas(true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Incidencias · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <link rel="stylesheet" href="../css/estiba.css">

  <style>
    /* ════════════════════════════════════════════════════════════════
       INCIDENCIAS · REGISTRO + TABLA (prefijo .inc-*)
       PREMIUM EMERALD SHIFT COMMAND THEME
    ════════════════════════════════════════════════════════════════ */
    .inc-wrap {
      --co-navy: #005c3d; --co-navy-700: #00875A; --co-red: #dc2626;
      --co-deck: #f5f8f7; --co-line: rgba(0, 135, 90, 0.16); --co-line-bold: rgba(0, 135, 90, 0.3);
      --co-ink: #111827; --co-mute: #4b5563; --co-faint: #9ca3af;
      --mono: ui-monospace, "SFMono-Regular", Consolas, monospace;
      display: flex; flex-direction: column; gap: 20px;
      font-family: 'DM Sans', system-ui, -apple-system, sans-serif; color: var(--co-ink);
    }
    .inc-wrap *, .inc-wrap *::before, .inc-wrap *::after { box-sizing: border-box; }

    /* ── HERO BANNER ── */
    .inc-hero {
      background: linear-gradient(135deg, #004d33 0%, #00875A 100%) !important;
      color: #fff; border-radius: 20px; padding: 26px 32px;
      display: flex; align-items: center; justify-content: space-between; gap: 24px;
      border: 1px solid rgba(255, 255, 255, 0.15) !important;
      box-shadow: 0 12px 36px rgba(0, 135, 90, 0.16) !important;
      position: relative; overflow: hidden;
    }
    .inc-hero::after {
      content: ''; position: absolute; right: -50px; top: -50px; width: 280px; height: 280px;
      background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0) 70%);
      pointer-events: none;
    }
    .inc-hero h1 { margin: 8px 0 6px; font-size: 24px; font-weight: 800; letter-spacing: -.02em; line-height: 1.2; }
    .inc-hero p  { margin: 0; color: rgba(255,255,255,.9); font-size: 13.5px; max-width: 620px; line-height: 1.5; }
    .inc-hero .tag {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 5px 12px; border-radius: 999px;
      background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25);
      font-size: 10.5px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
      color: #ffffff !important; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .inc-btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 18px; border-radius: 12px; border: 1px solid rgba(0, 135, 90, 0.3);
      background: #fff; cursor: pointer; text-decoration: none;
      font-family: inherit; font-size: 13px; font-weight: 600; color: #00875A;
      transition: all .16s ease;
    }
    .inc-btn:hover { border-color: var(--co-navy-700); color: var(--co-navy); background: rgba(0, 135, 90, 0.05); }
    body .inc-btn.primary,
    .inc-btn.primary {
      background: #ffffff !important;
      color: #005c3d !important;
      border: 1px solid rgba(255,255,255,0.8) !important;
      font-weight: 800 !important;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15) !important;
      letter-spacing: 0.01em;
      padding: 12px 22px;
      border-radius: 14px;
    }
    body .inc-btn.primary:hover,
    .inc-btn.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 28px rgba(0, 0, 0, 0.22) !important;
      background: #f4fdf9 !important;
    }
    .inc-btn.primary:active { transform: translateY(0); }
    .inc-btn.ghost-light { background: rgba(255,255,255,.16); color: #fff; border-color: rgba(255,255,255,.28); backdrop-filter: blur(4px); }
    .inc-btn.ghost-light:hover { background: rgba(255,255,255,.26); transform: translateY(-2px); }
    .inc-btn svg { width: 16px; height: 16px; flex-shrink: 0; }

    /* ── KPIS ── */
    .inc-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }
    .inc-kpi {
      background: #fff; border: 1px solid var(--co-line); border-radius: 18px;
      padding: 18px 22px; position: relative; overflow: hidden;
      box-shadow: 0 4px 18px rgba(0,0,0,.025) !important;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .inc-kpi:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 28px rgba(0, 135, 90, 0.08) !important;
    }
    .inc-kpi::before {
      content: ''; position: absolute; top: 0; left: 0; width: 5px; height: 100%;
    }
    .inc-kpi:nth-child(1)::before { background: var(--co-navy-700); }
    .inc-kpi:nth-child(2)::before { background: var(--co-red); }
    .inc-kpi:nth-child(3)::before { background: #d97706; }
    .inc-kpi:nth-child(4)::before { background: #3b82f6; }
    .inc-kpi .lbl { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--co-mute); }
    .inc-kpi:nth-child(1) .val { color: var(--co-navy-700); }
    .inc-kpi:nth-child(2) .val { color: var(--co-red); }
    .inc-kpi:nth-child(3) .val { color: #d97706; }
    .inc-kpi:nth-child(4) .val { color: #3b82f6; }
    .inc-kpi .val { font-size: 26px; font-weight: 800; margin-top: 6px; letter-spacing: -0.02em; }

    /* Aviso de alcance: los KPIs de arriba dejan de ser el total global cuando
       se filtra por un coordinador a cargo. */
    .inc-kpi-scope {
      display: none; align-items: center; gap: 8px; margin-top: -4px;
      padding: 8px 14px; border-radius: 10px;
      background: rgba(59, 130, 246, 0.06); border: 1px solid rgba(59, 130, 246, 0.22);
      font-size: 12.5px; font-weight: 600; color: #1d4ed8;
    }
    .inc-kpi-scope.on { display: flex; }
    .inc-kpi-scope svg { width: 15px; height: 15px; flex-shrink: 0; }
    .inc-kpi-scope b { font-weight: 800; }
    .inc-kpi-scope button {
      margin-left: auto; border: 0; background: transparent; cursor: pointer;
      font: inherit; font-size: 12px; font-weight: 700; color: #1d4ed8;
      text-decoration: underline; padding: 0;
    }

    /* ── TOOLBAR DE BÚSQUEDA Y FILTROS ── */
    .inc-toolbar {
      display: flex; flex-direction: column; gap: 14px;
      background: #fff; border: 1px solid var(--co-line); border-radius: 18px; padding: 16px 20px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.025) !important;
    }
    .inc-toolbar-top { display: flex; gap: 12px; align-items: center; width: 100%; }
    .inc-search {
      flex: 1; display: flex; align-items: center; gap: 10px;
      background: #f8faf9; border: 1.5px solid rgba(0, 135, 90, 0.18); border-radius: 12px; padding: 10px 16px;
      transition: all 0.18s ease;
    }
    .inc-search:focus-within {
      border-color: var(--co-navy-700); background: #fff;
      box-shadow: 0 0 0 4px rgba(0, 135, 90, 0.12);
    }
    .inc-search input {
      flex: 1; border: 0; outline: 0; background: transparent; font: inherit; font-size: 14px; color: var(--co-ink);
    }
    .inc-search input::placeholder { color: var(--co-faint); }
    .inc-search svg { width: 17px; height: 17px; color: var(--co-mute); flex-shrink: 0; }
    .inc-toolbar-filters {
      display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
      padding-top: 12px; border-top: 1px dashed rgba(0, 135, 90, 0.16);
    }
    .inc-filter-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .inc-filter-lbl { font-size: 11.5px; font-weight: 700; color: var(--co-navy); letter-spacing: 0.03em; text-transform: uppercase; }
    .inc-filter-sep { width: 1px; height: 26px; background: rgba(0, 135, 90, 0.2); }
    .inc-filter { display: flex; gap: 4px; background: #f1f5f4; border-radius: 10px; padding: 4px; flex-wrap: wrap; border: 1px solid #e2e8f0; }
    .inc-filter button {
      padding: 6px 14px; border: 0; background: transparent; border-radius: 7px;
      font: inherit; font-size: 12px; font-weight: 600; color: var(--co-mute); cursor: pointer;
      transition: all 0.15s ease;
    }
    .inc-filter button:hover { color: var(--co-navy); }
    .inc-filter button.active {
      background: #fff; color: var(--co-navy-700); font-weight: 700;
      box-shadow: 0 2px 6px rgba(0,0,0,.08); border: 1px solid rgba(0, 135, 90, 0.25);
    }
    .inc-filter--decl button[data-fd="completo"].active  { color: #00875A; border-color: rgba(0, 135, 90, 0.4); }
    .inc-filter--decl button[data-fd="pendiente"].active { color: #d97706; border-color: rgba(217, 119, 6, 0.4); }

    /* Filtro por Coordinador Tallyman a cargo (desplegable, no segmentado:
       la lista crece con los usuarios que tengan rol Coordinador). */
    .inc-filter-select {
      display: flex; align-items: center; gap: 8px;
      background: #f1f5f4; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0 10px 0 12px;
      transition: all 0.18s ease;
    }
    .inc-filter-select svg { width: 15px; height: 15px; color: var(--co-mute); flex-shrink: 0; }
    .inc-filter-select select {
      border: 0; outline: 0; background: transparent; font: inherit;
      font-size: 12px; font-weight: 600; color: var(--co-mute); cursor: pointer;
      padding: 8px 2px; max-width: 240px;
    }
    .inc-filter-select.on {
      background: #fff; border-color: rgba(0, 135, 90, 0.4);
      box-shadow: 0 2px 6px rgba(0,0,0,.08);
    }
    .inc-filter-select.on svg, .inc-filter-select.on select { color: var(--co-navy-700); font-weight: 700; }

    /* Chip del coordinador a cargo en la tabla */
    .inc-coord-chip {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 3px 12px 3px 3px; border-radius: 999px; max-width: 200px;
      background: rgba(59, 130, 246, 0.06); border: 1px solid rgba(59, 130, 246, 0.2);
    }
    .inc-coord-chip .ini {
      width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: #fff; display: grid; place-items: center; font-size: 9.5px; font-weight: 800;
    }
    .inc-coord-chip .nm {
      font-size: 12px; font-weight: 600; color: #1d4ed8;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .inc-coord-none { font-size: 12px; color: var(--co-faint); font-style: italic; }

    /* ── TABLA DE INCIDENCIAS ── */
    .inc-table-wrap {
      background: #fff; border: 1px solid var(--co-line); border-radius: 18px;
      overflow-x: auto; overflow-y: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.025) !important;
    }
    .inc-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; text-align: left; }
    .inc-table thead tr { background: #f8faf9; }
    .inc-table th {
      padding: 14px 16px; font-size: 11px; letter-spacing: .06em; text-transform: uppercase;
      color: var(--co-navy); font-weight: 800; border-bottom: 1.5px solid var(--co-line);
      white-space: nowrap; background: #f8faf9;
    }
    .inc-table tbody tr { transition: background .15s ease; }
    .inc-table tbody tr:hover { background: #f4f9f7; }
    .inc-table td {
      padding: 14px 16px; vertical-align: middle; border-bottom: 1px solid rgba(0, 135, 90, 0.08);
      color: var(--co-ink);
    }
    .inc-table tbody tr:last-child td { border-bottom: none; }

    /* Columna Colaborador fija a la izquierda (evita traslape de letras y pérdida visual al scroll) */
    .inc-table th.th-sticky-left,
    .inc-table td.td-sticky-left {
      position: sticky; left: 0; z-index: 5;
      background: #fff;
    }
    .inc-table th.th-sticky-left {
      background: #f8faf9 !important; z-index: 6;
      box-shadow: 4px 0 10px rgba(0, 0, 0, 0.04);
    }
    .inc-table td.td-sticky-left {
      box-shadow: 4px 0 10px rgba(0, 0, 0, 0.04);
    }
    .inc-table tbody tr:hover td.td-sticky-left {
      background: #f4f9f7;
    }

    .inc-colab-cell { display: flex; align-items: center; gap: 12px; }
    .inc-colab-avatar {
      width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
      background: linear-gradient(135deg, rgba(0,135,90,.14), rgba(0,135,90,.26));
      color: var(--co-navy); font-size: 12px; font-weight: 800;
      display: grid; place-items: center; border: 1px solid rgba(0,135,90,.2);
    }
    .inc-name { font-weight: 700; color: var(--co-ink); font-size: 13.5px; }
    .inc-sub  { font-size: 11.5px; color: var(--co-faint); }

    .inc-badge {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 4px 10px; border-radius: 999px;
      font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
      color: #fff; box-shadow: 0 2px 6px rgba(0,0,0,.1);
    }
    .inc-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: rgba(255,255,255,.9); }
    .inc-turno-chip {
      display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 6px;
      font-size: 11px; font-weight: 700; background: rgba(0, 135, 90, 0.08); color: var(--co-navy);
    }

    .inc-estado {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 4px 10px; border-radius: 999px;
      font-size: 10.5px; font-weight: 700; letter-spacing: .04em;
    }
    .inc-estado .dot { width: 6px; height: 6px; border-radius: 50%; }
    .inc-estado.ok   { background: rgba(0, 135, 90, 0.12); color: #00875A; }
    .inc-estado.ok .dot   { background: #00875A; }
    .inc-estado.pend { background: rgba(217, 119, 6, 0.12); color: #b45309; }
    .inc-estado.pend .dot { background: #d97706; }

    .inc-dias { font-variant-numeric: tabular-nums; font-weight: 600; color: var(--co-ink); }
    .inc-dias.pend { color: #b45309; }
    .inc-dias small { color: var(--co-faint); font-weight: 600; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }

    .inc-act-btn {
      padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(0, 135, 90, 0.25);
      background: rgba(0, 135, 90, 0.05); cursor: pointer; font: inherit; font-size: 12px; font-weight: 600; color: #00875A;
      transition: all .14s ease;
    }
    .inc-act-btn:hover { border-color: var(--co-navy-700); background: #00875A; color: #ffffff; box-shadow: 0 2px 8px rgba(0,135,90,.2); }
    .inc-act-btn.danger { color: var(--co-red); border-color: rgba(239, 68, 68, 0.25); background: rgba(239, 68, 68, 0.05); }
    .inc-act-btn.danger:hover { border-color: var(--co-red); background: var(--co-red); color: #ffffff; box-shadow: 0 2px 8px rgba(220,38,38,.2); }
    .inc-cell-actions { display: flex; gap: 6px; align-items: center; justify-content: flex-end; }

    /* ── MODALES ── */
    .inc-modal-back {
      position: fixed; inset: 0; background: rgba(0, 0, 0, 0.35);
      display: grid; place-items: center; z-index: 995;
      opacity: 0; pointer-events: none; transition: opacity .2s ease;
      backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px);
    }
    .inc-modal-back.open { opacity: 1; pointer-events: auto; }
    .inc-modal {
      background: #fff; border-radius: 20px; width: 620px; max-width: 94vw;
      box-shadow: 0 24px 64px rgba(0, 135, 90, 0.16);
      transform: translateY(14px) scale(.97); transition: transform .24s cubic-bezier(.25,.46,.45,.94);
      max-height: 92vh; display: flex; flex-direction: column; overflow: hidden;
      border: 1px solid var(--co-line);
    }
    .inc-modal-back.open .inc-modal { transform: translateY(0) scale(1); }
    .inc-modal-head {
      padding: 20px 24px 16px; border-bottom: 1px solid rgba(0, 135, 90, 0.1);
      display: flex; align-items: center; justify-content: space-between;
    }
    .inc-modal-head h3 { margin: 0; font-size: 18px; font-weight: 700; color: var(--co-ink); }
    .inc-modal-head .sub { font-size: 12.5px; color: var(--co-mute); margin-top: 3px; }
    .inc-modal-close {
      width: 34px; height: 34px; border-radius: 9px; border: 1px solid #d1d5db;
      background: #fff; cursor: pointer; display: grid; place-items: center; color: var(--co-mute); transition: all .15s;
    }
    .inc-modal-close:hover { color: var(--co-red); border-color: var(--co-red); background: #fef2f2; }
    .inc-modal-body { padding: 20px 24px; display: flex; flex-direction: column; gap: 14px; overflow-y: auto; flex: 1; background: #ffffff; }
    .inc-modal-foot { padding: 16px 24px; border-top: 1px solid rgba(0, 135, 90, 0.1); display: flex; justify-content: flex-end; gap: 10px; background: #ffffff; }
    .inc-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .inc-field { display: flex; flex-direction: column; gap: 6px; }
    .inc-field label { font-size: 11px; font-weight: 700; color: #374151; letter-spacing: .05em; text-transform: uppercase; }
    .inc-field input, .inc-field select, .inc-field textarea {
      font: inherit; font-size: 14px; color: #111827;
      background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 10px;
      padding: 10px 12px; outline: 0; transition: border-color .16s, box-shadow .16s;
    }
    .inc-field input::placeholder, .inc-field textarea::placeholder { color: #94a3b8; }
    .inc-field select option { color: #111827; background: #ffffff; }
    .inc-field textarea { resize: vertical; min-height: 76px; }
    .inc-field input:focus, .inc-field select:focus, .inc-field textarea:focus {
      border-color: #00875A; background: #fff; box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.15);
    }
    .inc-field input[readonly] { background: #f3f4f6; color: #4b5563; cursor: default; border-color: #e5e7eb; }
    .inc-colsel { position: relative; }
    .inc-colsel-panel {
      display: none; position: fixed; z-index: 9000;
      min-width: 320px; max-width: 420px;
      background: #fff; border: 1px solid rgba(0, 135, 90, 0.25); border-radius: 12px;
      box-shadow: 0 16px 40px rgba(0,0,0,.12); max-height: 240px; overflow-y: auto;
      padding: 6px;
    }
    .inc-colsel-panel.open { display: block; }
    .inc-colsel-item {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px; border-radius: 8px; cursor: pointer; transition: background .12s;
    }
    .inc-colsel-item:hover { background: rgba(0, 135, 90, 0.06); }
    .inc-colsel-avatar {
      width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
      background: rgba(0, 135, 90, 0.1); color: var(--co-navy); font-size: 11px; font-weight: 800;
      display: flex; align-items: center; justify-content: center; letter-spacing: -.3px;
    }
    .inc-colsel-info { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
    .inc-colsel-nm { font-size: 13.5px; font-weight: 600; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .inc-colsel-cd { font-size: 11px; color: #4b5563; }
    .inc-colsel-empty { padding: 14px; font-size: 12.5px; color: #4b5563; text-align: center; }

    .inc-drop {
      border: 2px dashed rgba(0, 135, 90, 0.28); border-radius: 14px; padding: 20px; text-align: center;
      cursor: pointer; color: #4b5563; transition: all .16s; background: #f9fafb; font-size: 13px;
    }
    .inc-drop:hover { border-color: var(--co-navy-700); background: rgba(0, 135, 90, 0.03); color: var(--co-navy-700); }
    .inc-file-info {
      display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 6px;
      font-size: 12px; color: var(--co-ink);
    }
    .inc-file-info a { color: var(--co-navy-700); font-weight: 600; text-decoration: none; }
    .inc-file-info a:hover { text-decoration: underline; }
    .inc-file-info .rm { color: var(--co-red); cursor: pointer; font-weight: 600; background: none; border: 0; font: inherit; }
    .inc-thumb { max-width: 100%; max-height: 240px; border-radius: 10px; border: 1px solid var(--co-line); }

    /* ── Vista detalle ── */
    .inc-modal--view .inc-modal-head { display: none; }
    .inc-modal--view .inc-modal-body { padding: 0; overflow: hidden; display: block; }
    .inc-modal--view #incViewEdit { background: linear-gradient(135deg,#00875A,#005c3d); border-color: transparent; }
    .inc-modal--view #incViewEdit:hover { background: linear-gradient(135deg,#00b377,#00875A); }

    .inc-view-layout { display: grid; grid-template-columns: 110px 1fr; max-height: 74vh; }
    .inc-view-sidebar {
      background: linear-gradient(160deg,#005c3d 0%,#00875A 100%);
      padding: 20px 12px; color: #fff;
      display: flex; flex-direction: column; gap: 14px; align-items: center; overflow-y: auto;
    }
    .inc-view-sidebar .iv-badge {
      display: inline-flex; align-items: center; gap: 4px; color: #fff;
      font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 20px;
      letter-spacing: .3px; white-space: nowrap; max-width: 100%;
    }
    .inc-view-sidebar .iv-divider { width: 100%; border: none; border-top: 1px solid rgba(255,255,255,.2); margin: 0; }
    .iv-stat { text-align: center; width: 100%; }
    .iv-stat-k { font-size: 8.5px; opacity: .8; text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 2px; }
    .iv-stat-v { font-size: 11.5px; font-weight: 700; }
    .inc-view-sidebar .iv-coord { text-align: center; margin-top: auto; padding-top: 12px; border-top: 1px solid rgba(255,255,255,.2); width: 100%; }
    .inc-view-sidebar .iv-coord .iv-stat-k { opacity: .8; }
    .inc-view-sidebar .iv-coord .iv-stat-v { font-size: 10.5px; font-weight: 600; }

    .inc-view-main { padding: 20px 18px; display: flex; flex-direction: column; gap: 12px; overflow-y: auto; background: #fff; }
    .inc-view-name { font-size: 15px; font-weight: 800; color: #005c3d; line-height: 1.3; margin: 0; }
    .inc-view-cargo { font-size: 11.5px; color: var(--co-mute); margin-top: 3px; font-weight: 500; }
    .inc-view-divider { border: none; border-top: 1px solid var(--co-line); margin: 2px 0; }
    .inc-view-field { display: flex; flex-direction: column; gap: 3px; }
    .iv-k { font-size: 9.5px; color: var(--co-mute); text-transform: uppercase; letter-spacing: .5px; font-weight: 600; display: block; }
    .iv-v { font-size: 13.5px; font-weight: 700; color: #111827; }
    .inc-view-field--blue { border-left: 3px solid #3b82f6; padding-left: 10px; }
    .inc-view-field--purple { border-left: 3px solid #7c3aed; padding-left: 10px; }
    .inc-view-detalle { background: #f9fafb; border: 1px solid var(--co-line); border-radius: 10px; padding: 12px 14px; }
    .inc-view-detalle .iv-v { font-size: 13.5px; color: #4b5563; font-weight: 400; line-height: 1.5; }
    .inc-medida-card { border: 1px solid rgba(0,135,90,.22); border-radius: 12px; background: linear-gradient(135deg,#f5fcf8,#fff); padding: 14px; }
    .inc-medida-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
    .inc-medida-title { margin:0; color:#005c3d; font-size:13px; font-weight:800; }
    .inc-medida-help { margin:3px 0 0; color:var(--co-mute); font-size:11.5px; line-height:1.4; }
    .inc-medida-status { display:inline-flex; align-items:center; border-radius:999px; padding:5px 9px; background:#e7f8ee; color:#087443; font-size:10px; font-weight:800; white-space:nowrap; }
    .inc-medida-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:12px; }
    .inc-medida-control { border:1px solid #d9e8e0; border-radius:9px; padding:10px; background:#fff; }
    .inc-medida-control label { color:#27364d; font-size:11.5px; font-weight:700; display:block; }
    .inc-medida-control select { width:100%; margin-top:7px; border:1px solid #bfd9cb; border-radius:7px; padding:7px 8px; color:#172033; font:inherit; font-size:12px; background:#fff; }
    .inc-medida-preview { margin-top:8px; color:#b42318; font-size:11.5px; font-weight:800; }
    .inc-medida-summary { margin-top:11px; padding:9px 10px; border-radius:8px; background:#f5f7f6; color:#405066; font-size:12px; font-weight:600; }
    .inc-medida-save { margin-top:11px; width:100%; border:0; border-radius:8px; padding:9px 12px; background:#007a50; color:#fff; font:inherit; font-size:12px; font-weight:800; cursor:pointer; }
    .inc-medida-save:hover { background:#005c3d; }
    .inc-medida-save:disabled { opacity:.6; cursor:wait; }
    @media (max-width:640px) { .inc-medida-grid { grid-template-columns:1fr; } }
    .inc-view-attachments { display: flex; gap: 8px; flex-wrap: wrap; }
    .inc-view-attach {
      flex: 1; border-radius: 8px; padding: 9px 6px; text-align: center;
      font-size: 11.5px; font-weight: 600; text-decoration: none; display: block; transition: filter .15s;
    }
    .inc-view-attach:hover { filter: brightness(.93); }
    .inc-view-attach--foto { background: rgba(0, 135, 90, 0.08); color: #00875A; }
    .inc-view-attach--foto:hover { background: rgba(0, 135, 90, 0.12); }
    .inc-view-attach--decl { background: #f5f3ff; color: #6d28d9; }
    .inc-view-attach--drive { background: #eaf1fb; color: #1a56db; }
    .inc-view-attach--drive:hover { background: #dbe7fa; }

    .inc-toast {
      position: fixed; bottom: 26px; right: 26px; z-index: 999;
      background: #111827; color: #fff; padding: 14px 20px; border-radius: 12px;
      font-size: 13.5px; font-weight: 600; box-shadow: 0 10px 30px rgba(0,0,0,.15);
      transform: translateY(120%); opacity: 0; transition: all .25s ease;
      border: 1px solid rgba(255,255,255,0.15);
    }
    .inc-toast.show { transform: translateY(0); opacity: 1; }
    .inc-toast.is-error { background: #dc2626; border-color: #ef4444; }

    .content { padding: 24px 28px 60px; overflow-y: auto; }

    /* Formulario de creación con rail izquierdo */
    #incModalBack, #incViewBack {
      --co-navy: #005c3d; --co-navy-700: #00875A; --co-navy-900: #001226; --co-red: #dc2626;
      --co-deck: #f5f8f7; --co-line: rgba(0, 135, 90, 0.18); --co-line-bold: rgba(0, 135, 90, 0.3);
      --co-ink: #111827; --co-mute: #4b5563; --co-faint: #9ca3af;
      --mono: ui-monospace, "SFMono-Regular", Consolas, monospace;
      font-family: 'DM Sans', system-ui, -apple-system, sans-serif;
    }
    .inc-modal.inc-create {
      width: 900px; max-width: 96vw; padding: 0;
      flex-direction: row; align-items: stretch; max-height: 92vh;
    }
    .inc-create *, .inc-create *::before, .inc-create *::after { box-sizing: border-box; }

    .inc-rail {
      position: relative; flex: 0 0 270px; width: 270px; color: var(--co-ink);
      padding: 24px 22px; overflow: hidden;
      display: flex; flex-direction: column; gap: 22px;
      background: #f5f8f7; border-right: 1px solid var(--co-line);
    }
    .inc-rail > * { position: relative; z-index: 1; }
    .inc-rail-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .inc-rail-kicker { font-family: var(--mono); font-size: 9.5px; letter-spacing: .22em; color: var(--co-mute); }
    .inc-rail-folio {
      font-family: var(--mono); font-size: 10px; letter-spacing: .06em;
      padding: 3px 9px; border-radius: 999px; background: rgba(0, 135, 90, 0.1);
      border: 1px solid rgba(0, 135, 90, 0.22); color: var(--co-navy); white-space: nowrap;
    }
    .inc-rail-lbl {
      display: block; font-family: var(--mono); font-size: 9px; letter-spacing: .18em;
      text-transform: uppercase; color: var(--co-faint); margin-bottom: 8px;
    }
    .inc-rail-id { display: flex; align-items: center; gap: 12px; }
    .inc-rail-avatar {
      width: 48px; height: 48px; border-radius: 14px; flex-shrink: 0;
      background: linear-gradient(135deg, #00875A 0%, #00b377 100%); color: #ffffff;
      display: grid; place-items: center; font-size: 16px; font-weight: 700;
      box-shadow: 0 4px 12px rgba(0, 135, 90, 0.25);
    }
    .inc-rail-name  { font-size: 15px; font-weight: 700; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; color: var(--co-ink); }
    .inc-rail-cargo { font-size: 11.5px; color: var(--co-mute); margin-top: 3px; }
    .inc-rail-gauge { display: flex; gap: 5px; }
    .inc-rail-gauge span {
      flex: 1; height: 8px; border-radius: 3px; background: rgba(0, 135, 90, 0.08);
      transition: background .3s ease, box-shadow .3s ease;
    }
    .inc-rail-sevname { margin-top: 10px; font-size: 13px; font-weight: 700; letter-spacing: .05em; color: var(--co-mute); transition: color .25s; }
    .inc-rail-comp-val { font-size: 13px; font-weight: 600; line-height: 1.4; color: var(--co-navy); padding-left: 11px; border-left: 2px solid #00875A; }
    .inc-rail-foot { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--co-line); }
    .inc-rail-coord { font-size: 13.5px; font-weight: 600; color: var(--co-ink); }

    .inc-form { flex: 1; min-width: 0; display: flex; flex-direction: column; background: #fff; }
    .inc-form-head {
      padding: 20px 24px 16px; border-bottom: 1px solid var(--co-line);
      display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    }
    .inc-form-head h3 { margin: 0; font-size: 18px; font-weight: 700; color: var(--co-ink); letter-spacing: -.01em; }
    .inc-form-head .sub { font-size: 12.5px; color: var(--co-mute); margin-top: 3px; }
    .inc-form-body { padding: 6px 24px 18px; overflow-y: auto; flex: 1; }

    .inc-sec { padding: 16px 0; border-bottom: 1px dashed var(--co-line); }
    .inc-sec:last-child { border-bottom: 0; }
    .inc-sec-head { display: flex; align-items: center; gap: 9px; margin-bottom: 12px; }
    .inc-sec-num {
      font-family: var(--mono); font-size: 10px; font-weight: 700; color: var(--co-navy);
      background: var(--co-deck); border: 1px solid rgba(0, 135, 90, 0.25);
      padding: 2px 6px; border-radius: 6px; letter-spacing: .05em;
    }
    .inc-sec-head > span:last-child { font-size: 11.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--co-mute); }

    .inc-sev-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
    .inc-sev-opt {
      position: relative; cursor: pointer; border: 1.5px solid var(--co-line); background: #fff;
      border-radius: 11px; padding: 10px 6px 9px; text-align: center; transition: transform .16s, border-color .16s, box-shadow .16s; font: inherit;
    }
    .inc-sev-opt:hover { transform: translateY(-2px); border-color: var(--co-faint); }
    .inc-sev-swatch { width: 100%; height: 6px; border-radius: 3px; margin-bottom: 8px; opacity: .35; transition: opacity .16s; }
    .inc-sev-opt .inc-sev-name { font-size: 11px; font-weight: 700; color: var(--co-mute); }
    .inc-sev-opt.active { border-color: transparent; box-shadow: 0 0 0 2px var(--sev), 0 4px 12px var(--sev); transform: translateY(-2px); }
    .inc-sev-opt.active .inc-sev-swatch { opacity: 1; }
    .inc-sev-opt.active .inc-sev-name { color: var(--co-ink); }

    .inc-turno-toggle { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; background: #f3f4f6; border-radius: 10px; padding: 4px; border: 1px solid #e5e7eb; }
    .inc-turno-toggle button {
      border: 0; background: transparent; border-radius: 8px; padding: 9px; cursor: pointer;
      font: inherit; font-size: 13px; font-weight: 600; color: var(--co-mute);
      display: flex; align-items: center; justify-content: center; gap: 7px; transition: all .15s;
    }
    .inc-turno-toggle button svg { width: 15px; height: 15px; }
    .inc-turno-toggle button:hover { color: var(--co-navy); }
    .inc-turno-toggle button.active { background: #fff; color: var(--co-navy-700); box-shadow: 0 1px 4px rgba(0,0,0,.08); border: 1px solid rgba(0, 135, 90, 0.2); }
    .inc-turno-toggle button[data-turno="noche"].active { background: #111827; color: #ffffff; border-color: #111827; }

    .inc-create .inc-drop {
      display: flex; align-items: center; gap: 12px; text-align: left; padding: 14px;
    }
    .inc-create .inc-drop svg { flex-shrink: 0; opacity: .8; }
    .inc-create .inc-drop small { color: var(--co-faint); font-size: 10.5px; }

    .inc-form-foot { padding: 16px 24px; border-top: 1px solid var(--co-line); display: flex; justify-content: flex-end; gap: 10px; }

    @keyframes incSecIn { from { opacity: 0; transform: translateY(9px); } to { opacity: 1; transform: none; } }
    @keyframes incRailIn { from { opacity: 0; transform: translateX(-14px); } to { opacity: 1; transform: none; } }
    .inc-modal-back.open .inc-rail { animation: incRailIn .45s both cubic-bezier(.22,.61,.36,1); }
    .inc-modal-back.open .inc-form-body section { animation: incSecIn .4s both cubic-bezier(.22,.61,.36,1); }
    .inc-modal-back.open .inc-form-body section:nth-of-type(1){ animation-delay: .05s; }
    .inc-modal-back.open .inc-form-body section:nth-of-type(2){ animation-delay: .10s; }
    .inc-modal-back.open .inc-form-body section:nth-of-type(3){ animation-delay: .15s; }
    .inc-modal-back.open .inc-form-body section:nth-of-type(4){ animation-delay: .20s; }
    .inc-modal-back.open .inc-form-body section:nth-of-type(5){ animation-delay: .25s; }
    .inc-modal-back.open .inc-form-body section:nth-of-type(6){ animation-delay: .30s; }
    .inc-modal-back.open .inc-form-body section:nth-of-type(7){ animation-delay: .35s; }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
      .inc-hero { flex-direction: column; align-items: flex-start; gap: 18px; }
      .inc-hero > div:last-child { width: 100%; justify-content: flex-start; }
      .inc-toolbar-filters { flex-direction: column; align-items: flex-start; gap: 12px; }
      .inc-filter-sep { display: none; }
    }
    @media (max-width: 760px) {
      .inc-modal.inc-create { flex-direction: column; width: 96vw; }
      .inc-rail { flex: 0 0 auto; width: 100%; border-right: none; border-bottom: 1px solid var(--co-line); }
      .inc-rail-foot { margin-top: 14px; }
      .inc-sev-grid { grid-template-columns: repeat(5, 1fr); gap: 5px; }
      .inc-kpis { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
      .inc-modal, .inc-modal.inc-create {
        width: calc(100vw - 12px) !important; max-width: 100% !important; height: 95dvh !important; max-height: 95dvh !important;
        border-radius: 16px !important; display: flex !important; flex-direction: column !important; overflow: hidden !important;
      }
      .inc-modal.inc-create .inc-rail { display:none !important; }
      .inc-modal.inc-create .inc-form { flex: 1 1 0 !important; min-height: 0 !important; display: flex !important; flex-direction: column !important; overflow: hidden !important; }
      .inc-form-body { flex: 1 1 0 !important; min-height: 0 !important; overflow-y: auto !important; overscroll-behavior: contain !important; padding: 14px !important; }
      .inc-form-head { padding: 14px !important; }
      .inc-form-head h3 { font-size: 16px !important; }
      .inc-form-foot { padding:12px 14px !important; gap:8px !important; }
      .inc-form-foot .inc-btn { flex:1 1 0; justify-content:center; min-height:44px; padding:10px 12px !important; }
      .inc-row2 { grid-template-columns: 1fr !important; }
      .inc-sec  { padding: 12px 0 !important; }
      .inc-field input, .inc-field select, .inc-field textarea { font-size: 15px !important; padding: 11px 12px !important; }
      .inc-colsel-panel { min-width:0 !important; max-width:none !important; }
      .inc-sev-grid { grid-template-columns: repeat(5, 1fr); gap: 4px; }
      .inc-sev-opt { padding: 8px 4px !important; }
      .inc-kpis { grid-template-columns: 1fr !important; }

      /* Tabla de incidencias en mobile: la columna "Colaborador" ya NO queda
         fija (sticky). Se desplaza junto con el resto de la tabla al hacer
         scroll lateral, para que no tape ni "congele" el nombre. */
      .inc-table th.th-sticky-left,
      .inc-table td.td-sticky-left {
        position: static !important; box-shadow: none !important; z-index: auto !important;
      }
      .inc-table th, .inc-table td { padding: 11px 12px !important; }
      .inc-table .inc-colab-avatar { display: none !important; }
      .inc-table td.td-sticky-left .inc-name,
      .inc-table td.td-sticky-left .inc-sub { max-width: 150px !important; }
      .inc-table td.td-sticky-left .inc-name { font-size: 12.5px !important; }
      .inc-table td.td-sticky-left .inc-sub  { font-size: 10.5px !important; }
    }
    @media (max-width: 390px) {
      .inc-rail { display: none !important; }
    }
    @media (prefers-reduced-motion: reduce) {
      .inc-modal-back.open .inc-rail, .inc-modal-back.open .inc-form-body section { animation: none; }
    }
  </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="shell">
  <?php $sb_base = '..'; include('../includes/sidebar.php'); ?>

  <div class="main-area">
    <?php include('../includes/header.php'); ?>

    <main class="content">
      <div class="inc-wrap">

        <!-- HERO -->
        <section class="inc-hero">
          <div>
            <span class="tag">CONTROL DE CAMPO · INCIDENCIAS</span>
            <h1>Registro y bitácora de incidencias</h1>
            <p>Monitoreo y seguimiento de puntos a mejorar del personal con clasificación por nivel de impacto, competencia afectada, turno operativo y evidencias adjuntas.</p>
          </div>
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;justify-content:flex-end;z-index:2">
            <a class="inc-btn ghost-light" href="https://drive.google.com/drive/folders/1FBEfM1g2ztDO-aID4d7XRylow4hlD8Oi" target="_blank" rel="noopener" title="Abrir la carpeta de Drive con las evidencias">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
              Ver documentos
            </a>
            <button class="inc-btn primary" id="btnNew">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Registrar incidencia
            </button>
          </div>
        </section>

        <!-- KPIS -->
        <section class="inc-kpis">
          <div class="inc-kpi"><div class="lbl">Total registradas</div><div class="val" id="kpiTotal">0</div></div>
          <div class="inc-kpi"><div class="lbl">Impacto Crítico</div><div class="val" id="kpiCritico">0</div></div>
          <div class="inc-kpi"><div class="lbl">Impacto Alto</div><div class="val" id="kpiAlto">0</div></div>
          <div class="inc-kpi"><div class="lbl">Registradas este mes</div><div class="val" id="kpiMes">0</div></div>
        </section>

        <!-- Alcance de los KPIs cuando se filtra por coordinador a cargo -->
        <div class="inc-kpi-scope" id="incKpiScope">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span>Indicadores del equipo a cargo de <b id="incKpiScopeName">—</b></span>
          <button type="button" id="incKpiScopeClear">Ver todos</button>
        </div>

        <!-- TOOLBAR DE BÚSQUEDA Y FILTROS -->
        <div class="inc-toolbar">
          <div class="inc-toolbar-top">
            <div class="inc-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input id="incSearch" type="text" placeholder="Buscar por colaborador, punto a mejorar, zona de trabajo, coordinador…">
            </div>
          </div>
          <div class="inc-toolbar-filters">
            <div class="inc-filter-group">
              <span class="inc-filter-lbl">Nivel de Impacto:</span>
              <div class="inc-filter" id="incFilter" title="Filtrar por impacto">
                <button class="active" data-f="todos">Todos</button>
                <button data-f="minimo">Mínimo</button>
                <button data-f="bajo">Bajo</button>
                <button data-f="moderado">Moderado</button>
                <button data-f="alto">Alto</button>
                <button data-f="critico">Crítico</button>
              </div>
            </div>
            <div class="inc-filter-sep"></div>
            <div class="inc-filter-group">
              <span class="inc-filter-lbl">Coordinador a cargo:</span>
              <div class="inc-filter-select" id="incFilterCoordWrap" title="Ver solo las incidencias de los colaboradores a cargo de un coordinador">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <select id="incFilterCoord">
                  <option value="todos">Todos</option>
                  <option value="sin">Sin coordinador</option>
                </select>
              </div>
            </div>
            <div class="inc-filter-sep"></div>
            <div class="inc-filter-group">
              <span class="inc-filter-lbl">Hoja de Declaración:</span>
              <div class="inc-filter inc-filter--decl" id="incFilterDecl" title="Filtrar por estado de la declaración">
                <button class="active" data-fd="todos">Todos</button>
                <button data-fd="completo">Completo</button>
                <button data-fd="pendiente">Pendiente</button>
              </div>
            </div>
          </div>
        </div>

        <!-- TABLA -->
        <div class="inc-table-wrap">
          <table class="inc-table">
            <thead>
              <tr>
                <th class="th-sticky-left">Colaborador</th>
                <th>Coord. a cargo</th>
                <th>Evaluación / Competencia</th>
                <th>Impacto</th>
                <th>Zona / Turno</th>
                <th>Fecha</th>
                <th>Registró</th>
                <th>Declaración / Demora</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody id="incTbody">
              <tr><td colspan="9" style="text-align:center;padding:48px;color:var(--co-faint);font-size:14px;font-weight:500">Cargando incidencias…</td></tr>
            </tbody>
          </table>
        </div>

      </div>
    </main>
  </div>
</div>

<!-- MODAL: registrar / editar -->
<div class="inc-modal-back" id="incModalBack">
  <div class="inc-modal inc-create">

    <!-- RAIL IZQUIERDO · dossier / vista previa en vivo -->
    <aside class="inc-rail">
      <div class="inc-rail-top">
        <span class="inc-rail-kicker">PARTE · INCIDENCIA</span>
        <span class="inc-rail-folio" id="railFolio">NUEVO</span>
      </div>

      <div class="inc-rail-id">
        <div class="inc-rail-avatar" id="railAvatar">—</div>
        <div style="min-width:0">
          <div class="inc-rail-name" id="railName">Sin colaborador</div>
          <div class="inc-rail-cargo" id="railCargo">Selecciona un colaborador</div>
        </div>
      </div>

      <div>
        <span class="inc-rail-lbl">Nivel de impacto</span>
        <div class="inc-rail-gauge" id="railGauge">
          <span></span><span></span><span></span><span></span><span></span>
        </div>
        <div class="inc-rail-sevname" id="railSevName">— sin definir —</div>
      </div>

      <div>
        <span class="inc-rail-lbl">Competencia afectada</span>
        <div class="inc-rail-comp-val" id="railComp">Se asigna según el punto</div>
      </div>

      <div class="inc-rail-foot">
        <span class="inc-rail-lbl">Coordinador que reporta</span>
        <div class="inc-rail-coord" id="railCoord"><?= htmlspecialchars($COORDINADOR) ?></div>
      </div>
    </aside>

    <!-- COLUMNA DERECHA · formulario -->
    <div class="inc-form">
      <div class="inc-form-head">
        <div>
          <h3 id="incModalTitle">Registrar incidencia</h3>
          <div class="sub">Completa el parte. La competencia se asigna sola según el punto.</div>
        </div>
        <button class="inc-modal-close" id="incModalClose">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <div class="inc-form-body">
        <input type="hidden" id="im-id">
        <input type="hidden" id="im-impacto">
        <input type="hidden" id="im-turno">

        <section class="inc-sec">
          <div class="inc-sec-head"><span class="inc-sec-num">01</span><span>Colaborador</span></div>
          <div class="inc-row2">
            <div class="inc-field">
              <label>Nombre</label>
              <div class="inc-colsel" id="im-colaborador-wrap">
                <input type="text" id="im-colaborador-search" autocomplete="off" placeholder="Buscar por nombre o código…">
                <div class="inc-colsel-panel" id="im-colaborador-panel"></div>
                <select id="im-colaborador" style="display:none"><option value="">Cargando…</option></select>
              </div>
            </div>
            <div class="inc-field">
              <label>Cargo</label>
              <input id="im-cargo" type="text" readonly placeholder="—">
            </div>
          </div>
        </section>

        <section class="inc-sec">
          <div class="inc-sec-head"><span class="inc-sec-num">02</span><span>Evaluación</span></div>
          <div class="inc-row2">
            <div class="inc-field">
              <label>Punto a mejorar</label>
              <select id="im-punto"><option value="">Selecciona…</option></select>
            </div>
            <div class="inc-field">
              <label>Competencia afectada</label>
              <input id="im-competencia" type="text" readonly placeholder="—">
            </div>
          </div>
        </section>

        <section class="inc-sec">
          <div class="inc-sec-head"><span class="inc-sec-num">03</span><span>Severidad del impacto</span></div>
          <div class="inc-sev-grid" id="sevGrid"></div>
        </section>

        <section class="inc-sec">
          <div class="inc-sec-head"><span class="inc-sec-num">04</span><span>Contexto del turno</span></div>
          <div class="inc-row2">
            <div class="inc-field">
              <label>Turno</label>
              <div class="inc-turno-toggle" id="turnoToggle">
                <button type="button" data-turno="dia">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                  Día
                </button>
                <button type="button" data-turno="noche">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                  Noche
                </button>
              </div>
            </div>
            <div class="inc-field">
              <label>Fecha</label>
              <input id="im-fecha" type="date">
            </div>
          </div>
        </section>

        <section class="inc-sec">
          <div class="inc-sec-head"><span class="inc-sec-num">05</span><span>Zona de trabajo</span></div>
          <div class="inc-field">
            <select id="im-zona"><option value="">Selecciona…</option></select>
          </div>
        </section>

        <section class="inc-sec">
          <div class="inc-sec-head"><span class="inc-sec-num">06</span><span>Detalle</span></div>
          <div class="inc-field">
            <textarea id="im-detalle" placeholder="Describe la incidencia…" maxlength="2000"></textarea>
          </div>
        </section>

        <section class="inc-sec">
          <div class="inc-sec-head"><span class="inc-sec-num">07</span><span>Evidencias</span></div>
          <div class="inc-row2">
            <div class="inc-field">
              <label>Foto</label>
              <div class="inc-drop" id="dropFoto">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/></svg>
                <span>Subir imagen<br><small>JPG · PNG · WebP · máx 8MB</small></span>
              </div>
              <input type="file" id="im-foto" accept=".jpg,.jpeg,.png,.webp" style="display:none">
              <div class="inc-file-info" id="infoFoto" style="display:none"></div>
            </div>
            <div class="inc-field">
              <label>Hoja de declaración</label>
              <div class="inc-drop" id="dropDecl">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
                <span>Subir archivo<br><small>PDF · JPG · PNG · máx 8MB</small></span>
              </div>
              <input type="file" id="im-declaracion" accept=".pdf,.jpg,.jpeg,.png" style="display:none">
              <div class="inc-file-info" id="infoDecl" style="display:none"></div>
            </div>
          </div>
        </section>
      </div>

      <div class="inc-form-foot">
        <button class="inc-btn" id="incModalCancel">Cancelar</button>
        <button class="inc-btn primary" id="incModalSave">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Guardar parte
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: ver detalle -->
<div class="inc-modal-back" id="incViewBack">
  <div class="inc-modal inc-modal--view">
    <!-- head oculto por CSS; mantiene los IDs para los event listeners -->
    <div class="inc-modal-head" style="display:none">
      <div><h3>Detalle de incidencia</h3><div class="sub" id="incViewSub">—</div></div>
      <button class="inc-modal-close" id="incViewClose"></button>
    </div>
    <div class="inc-modal-body" id="incViewBody"></div>
    <div class="inc-modal-foot">
      <button class="inc-btn" id="incViewCloseBtn">Cerrar</button>
      <button class="inc-btn primary" id="incViewEdit">Editar</button>
    </div>
  </div>
</div>

<div class="inc-toast" id="incToast">—</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  // ── Catálogos desde PHP (fuente de verdad) ──
  const PUNTOS   = <?= json_encode($JS_PUNTOS, JSON_UNESCAPED_UNICODE) ?>;   // {punto: competencia}
  const IMPACTOS = <?= json_encode($JS_IMPACTOS, JSON_UNESCAPED_UNICODE) ?>; // {clave:{label,color}}
  const TURNOS   = <?= json_encode($JS_TURNOS, JSON_UNESCAPED_UNICODE) ?>;   // {clave: label}
  const COORD    = <?= json_encode($COORDINADOR, JSON_UNESCAPED_UNICODE) ?>;
  const SEV_ORDER = Object.keys(IMPACTOS);
  const BASE     = '..';
  const ES_ADMINISTRADOR = <?= $ES_ADMINISTRADOR ? 'true' : 'false' ?>;
  const UBIC_ZONA = <?= json_encode($zona_ubicaciones, JSON_UNESCAPED_UNICODE) ?>;
  const GROUPS_UBIC = [
    { test: /^muelle/i,                             label: 'Muelles',  color: '#0f4c81' },
    { test: /^balanza/i,                            label: 'Balanzas', color: '#0d9488' },
    { test: /^(gate|depot|administrativo|sombra)/i, label: 'Otros',    color: '#b45309' },
    { test: /./,                                    label: 'Yard',     color: '#7c3aed' },
  ];
  function eselGroupOf(v, groups) { return groups.find(g => g.test.test(v)) || null; }
  function enhanceZona(sel) {
    if (!sel || sel.dataset.eselReady) return;
    sel.dataset.eselReady = '1';
    const CARET = '<svg class="op-input-caret" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;width:16px;height:16px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
    const root = document.createElement('div');
    root.className = 'esel'; root.style.cssText = 'position:relative;width:100%';
    sel.parentNode.insertBefore(root, sel);
    root.appendChild(sel);
    sel.classList.add('esel-native');
    sel.setAttribute('tabindex', '-1'); sel.setAttribute('aria-hidden', 'true');
    const trigger = document.createElement('button');
    trigger.type = 'button'; trigger.className = 'esel-trigger';
    trigger.innerHTML = '<span class="esel-val"></span><span class="esel-arrow">▾</span>';
    root.appendChild(trigger);
    const valBox = trigger.querySelector('.esel-val');
    let panel = null, onScroll = null;
    const desc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    function colorFor(v) { return (eselGroupOf(v, GROUPS_UBIC) || {}).color || null; }
    function updateTrigger() {
      const o = sel.options[sel.selectedIndex];
      if (!o || !o.value) { valBox.innerHTML = '<span class="esel-ph">Selecciona…</span>'; return; }
      const c = colorFor(o.value);
      valBox.innerHTML = (c ? `<span class="esel-dot" style="background:${c}"></span>` : '') + `<span>${esc(o.textContent)}</span>`;
    }
    function buildPanel() {
      panel = document.createElement('div'); panel.className = 'esel-panel';
      const opts = Array.from(sel.options).filter(o => o.value);
      let html = '';
      for (const g of GROUPS_UBIC) {
        const items = opts.filter(o => eselGroupOf(o.value, GROUPS_UBIC) === g);
        if (!items.length) continue;
        html += `<div class="esel-group"><span class="esel-dot" style="background:${g.color}"></span>${esc(g.label)}</div>`;
        html += items.map(o => {
          const isSel = o.value === sel.value;
          const c = colorFor(o.value);
          const accent = isSel && c ? ` style="border-left-color:${c}"` : '';
          return `<div class="esel-opt${isSel ? ' sel' : ''}" role="option" data-val="${esc(o.value)}"${accent}>`
            + `<span class="esel-opt-main"><span>${esc(o.textContent)}</span></span>`
            + `<span class="esel-check"${c ? ` style="color:${c}"` : ''}>✓</span></div>`;
        }).join('');
      }
      panel.innerHTML = html;
      document.body.appendChild(panel);
      panel.addEventListener('mousedown', e => {
        const it = e.target.closest('.esel-opt'); if (!it) return;
        e.preventDefault(); sel.value = it.dataset.val; close();
      });
    }
    function position() {
      const r = trigger.getBoundingClientRect(), vh = window.innerHeight, vw = window.innerWidth, gap = 5;
      let left = r.left; if (left + r.width > vw - 8) left = Math.max(8, vw - r.width - 8);
      panel.style.left = left + 'px'; panel.style.width = r.width + 'px';
      const below = vh - r.bottom - 10, above = r.top - 10;
      if (below >= 150 || below >= above) {
        panel.style.top = (r.bottom + gap) + 'px'; panel.style.bottom = 'auto';
        panel.style.maxHeight = Math.min(400, below + 10 - gap) + 'px';
      } else {
        panel.style.bottom = (vh - r.top + gap) + 'px'; panel.style.top = 'auto';
        panel.style.maxHeight = Math.min(400, above + 10 - gap) + 'px';
      }
      panel.scrollTop = 0;
    }
    function open() {
      if (panel) return; buildPanel(); position();
      requestAnimationFrame(() => panel && panel.classList.add('open'));
      trigger.classList.add('open');
      onScroll = e => { if (panel && !panel.contains(e.target)) close(); };
      document.addEventListener('mousedown', onOutside, true);
      window.addEventListener('scroll', onScroll, true);
      window.addEventListener('resize', close, true);
    }
    function close() {
      if (!panel) return;
      const p = panel; panel = null;
      p.classList.remove('open'); trigger.classList.remove('open');
      setTimeout(() => p.remove(), 150);
      document.removeEventListener('mousedown', onOutside, true);
      if (onScroll) { window.removeEventListener('scroll', onScroll, true); onScroll = null; }
      window.removeEventListener('resize', close, true);
    }
    function onOutside(e) { if (panel && !panel.contains(e.target) && !root.contains(e.target)) close(); }
    trigger.addEventListener('click', () => panel ? close() : open());
    Object.defineProperty(sel, 'value', { configurable: true,
      get() { return desc.get.call(this); },
      set(v) { desc.set.call(this, v); updateTrigger(); }
    });
    new MutationObserver(updateTrigger).observe(sel, { childList: true });
    updateTrigger();
  }

  let incidencias = [];
  let colaboradores = [];
  let coordinadores = [];     // usuarios con rol Coordinador (para el filtro)
  let query = '';
  let filtro = 'todos';
  let filtroDecl = 'todos';   // todos | completo | pendiente
  let filtroCoord = 'todos';  // todos | sin | id del coordinador a cargo
  let editingId = null;
  // Rutas de adjuntos en el formulario (se actualizan al subir/quitar).
  let formFoto = null, formDecl = null;

  function toast(msg, type) {
    const t = $('incToast');
    t.textContent = msg;
    t.className = 'inc-toast show' + (type === 'error' ? ' is-error' : '');
    setTimeout(() => t.classList.remove('show'), 3000);
  }
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g,
      c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  }
  function impactoBadge(key) {
    const m = IMPACTOS[key];
    if (!m) return esc(key || '—');
    return `<span class="inc-badge" style="background:${m.color}"><span class="dot"></span>${esc(m.label)}</span>`;
  }
  const EVADES = {
    minimo: [2], bajo: [2], moderado: [4], alto: [6], critico: [8],
  };
  function puntosEvades(impacto) { return (EVADES[impacto] || [0])[0] || 0; }
  function textoSancion(tipo) {
    return tipo === 'suspension' ? 'Suspensión' : tipo === 'amonestacion_escrita' ? 'Amonestación escrita' : '';
  }
  function textoActo(i) {
    const descuento = Number(i.descuento_puntos || 0);
    const sancion = textoSancion(i.sancion_disciplinaria);
    if (descuento && sancion) return `Descuento -${descuento} pts + ${sancion}`;
    if (descuento) return `Descuento -${descuento} pts`;
    return sancion || 'Sin medida aplicada';
  }
  function medidaHtml(i) {
    const tieneMedida = Number(i.descuento_puntos || 0) > 0 || !!textoSancion(i.sancion_disciplinaria);
    const resumen = tieneMedida
      ? `${textoActo(i)}${i.medida_aplicada_por ? ` · Aplicado por ${esc(i.medida_aplicada_por)}` : ''}`
      : 'Sin descuento de puntos ni sanción disciplinaria registrados.';
    if (!ES_ADMINISTRADOR) {
      return `<section class="inc-medida-card"><div class="inc-medida-head"><div><h4 class="inc-medida-title">Decisión administrativa</h4><p class="inc-medida-help">Resultado registrado para esta incidencia.</p></div><span class="inc-medida-status">${tieneMedida ? 'MEDIDA APLICADA' : 'SIN MEDIDA'}</span></div><div class="inc-medida-summary">${resumen}</div></section>`;
    }
    const descuento = Number(i.descuento_puntos || 0) > 0;
    const impacto = IMPACTOS[i.impacto] || { label: i.impacto || '—' };
    return `<section class="inc-medida-card">
      <div class="inc-medida-head"><div><h4 class="inc-medida-title">Decisión administrativa · EVADES</h4><p class="inc-medida-help">El descuento se define por el impacto registrado y afecta la competencia indicada en esta incidencia.</p></div><span class="inc-medida-status">ADMINISTRADOR</span></div>
      <div class="inc-medida-grid">
        <div class="inc-medida-control"><label for="medidaDescuento">¿Aplica descuento de puntos?</label><select id="medidaDescuento"><option value="no" ${descuento ? '' : 'selected'}>No · 0 puntos</option><option value="si" ${descuento ? 'selected' : ''}>Sí · aplicar descuento EVADES</option></select><div class="inc-medida-preview" id="medidaPreview">${descuento ? `Descuento EVADES: -${puntosEvades(i.impacto)} puntos` : 'Descuento: 0 puntos'}</div><div class="inc-medida-summary">Impacto: <strong>${esc(impacto.label)}</strong><br>Competencia afectada: <strong>${esc(i.competencia || '—')}</strong></div></div>
        <div class="inc-medida-control"><label for="medidaSancion">Sanción disciplinaria</label><select id="medidaSancion"><option value="sin_sancion" ${i.sancion_disciplinaria === 'sin_sancion' || !i.sancion_disciplinaria ? 'selected' : ''}>No aplica</option><option value="amonestacion_escrita" ${i.sancion_disciplinaria === 'amonestacion_escrita' ? 'selected' : ''}>Amonestación escrita</option><option value="suspension" ${i.sancion_disciplinaria === 'suspension' ? 'selected' : ''}>Suspensión</option></select><div id="sancionDetails" style="margin-top:9px;display:${textoSancion(i.sancion_disciplinaria) ? 'grid' : 'none'};grid-template-columns:1fr 1fr;gap:7px"><label>Desde<input id="medidaInicio" type="date" value="${esc(i.fecha || '')}" style="width:100%;margin-top:4px"></label><label>Hasta<input id="medidaFin" type="date" value="${esc(i.fecha || '')}" style="width:100%;margin-top:4px"></label><label style="grid-column:1/-1">Evidencia de la sanción<input id="medidaEvidencia" type="file" accept=".pdf,image/jpeg,image/png" style="width:100%;margin-top:4px"></label></div><div class="inc-medida-summary">${tieneMedida ? resumen : 'Aún no hay una decisión administrativa guardada.'}</div></div>
      </div><button type="button" class="inc-medida-save" id="medidaGuardar">Guardar decisión administrativa</button>
    </section>`;
  }
  function wireMedida(i) {
    if (!ES_ADMINISTRADOR) return;
    const aplica = $('medidaDescuento'), preview = $('medidaPreview'), guardarBtn = $('medidaGuardar');
    if (!aplica || !preview || !guardarBtn) return;
    aplica.addEventListener('change', () => { preview.textContent = aplica.value === 'si' ? `Descuento EVADES: -${puntosEvades(i.impacto)} puntos` : 'Descuento: 0 puntos'; });
    let evidenciaPath = '';
    $('medidaSancion').addEventListener('change', e => { $('sancionDetails').style.display = e.target.value === 'sin_sancion' ? 'none' : 'grid'; });
    $('medidaEvidencia').addEventListener('change', async e => { const file = e.target.files[0]; if (!file) return; const fd = new FormData(); fd.append('file', file); try { const r = await fetch(`${BASE}/api/upload_sancion_evidencia.php`, { method: 'POST', body: fd }); const d = await r.json(); if (!d.success) throw Error(d.error); evidenciaPath = d.path; toast('Evidencia adjuntada'); } catch (err) { toast(err.message || 'No se pudo adjuntar la evidencia', 'error'); } });
    guardarBtn.addEventListener('click', async () => {
      guardarBtn.disabled = true; guardarBtn.textContent = 'Guardando decisión…';
      try {
        const res = await fetch(`${BASE}/api/save_incidencia_medida.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: i.id, aplica_descuento: aplica.value === 'si', sancion_disciplinaria: $('medidaSancion').value, fecha_inicio: $('medidaInicio').value, fecha_fin: $('medidaFin').value, evidencia_path: evidenciaPath }) });
        const data = await res.json(); if (!data.success) throw new Error(data.error || 'No se pudo guardar la decisión.');
        Object.assign(i, data.data || {}); render(); toast('Decisión administrativa guardada'); openView(i.id);
      } catch (e) { toast(e.message || 'Error de red', 'error'); guardarBtn.disabled = false; guardarBtn.textContent = 'Guardar decisión administrativa'; }
    });
  }
  function turnoLabel(key) { return TURNOS[key] || key || '—'; }
  function fmtFecha(f) {
    if (!f) return '—';
    const [y,m,d] = String(f).split('-');
    return d ? `${d}/${m}/${y}` : f;
  }

  // ── Estado de la Hoja de Declaración ──
  // COMPLETO = ya tiene declaración adjunta (local o en Drive); si no, PENDIENTE.
  function declCompleta(i) { return !!(i.declaracion_path || i.declaracion_drive_url); }
  function estadoBadge(i) {
    const ok = declCompleta(i);
    return `<span class="inc-estado ${ok ? 'ok' : 'pend'}"><span class="dot"></span>${ok ? 'COMPLETO' : 'PENDIENTE'}</span>`;
  }
  // Parsea un timestamp "YYYY-MM-DD HH:MM:SS" a Date local.
  function parseTs(s) {
    if (!s) return null;
    const d = new Date(String(s).replace(' ', 'T'));
    return isNaN(d.getTime()) ? null : d;
  }
  // Días calendario completos entre dos fechas (b - a).
  function diffDias(a, b) {
    const da = new Date(a.getFullYear(), a.getMonth(), a.getDate());
    const db = new Date(b.getFullYear(), b.getMonth(), b.getDate());
    return Math.round((db - da) / 86400000);
  }
  function plural(n) { return n === 1 ? 'día' : 'días'; }
  // Días que tardó en subirse la declaración desde que se creó el registro.
  // COMPLETO → días entre creación y subida. PENDIENTE → días transcurridos hasta hoy.
  function diasDeclaracion(i) {
    const creado = parseTs(i.created_at);
    if (!creado) return '—';
    if (declCompleta(i)) {
      const subido = parseTs(i.declaracion_uploaded_at) || parseTs(i.updated_at);
      if (!subido) return '—';
      const n = Math.max(0, diffDias(creado, subido));
      return `<span class="inc-dias">${n} ${plural(n)}</span>`;
    }
    const n = Math.max(0, diffDias(creado, new Date()));
    return `<span class="inc-dias pend">${n} ${plural(n)} <small>en curso</small></span>`;
  }
  // Misma lógica que diasDeclaracion pero como texto plano (para la vista detalle).
  function diasDeclaracionTexto(i) {
    const creado = parseTs(i.created_at);
    if (!creado) return '—';
    if (declCompleta(i)) {
      const subido = parseTs(i.declaracion_uploaded_at) || parseTs(i.updated_at);
      if (!subido) return '—';
      const n = Math.max(0, diffDias(creado, subido));
      return `${n} ${plural(n)}`;
    }
    const n = Math.max(0, diffDias(creado, new Date()));
    return `${n} ${plural(n)} (en curso)`;
  }

  // ─── Poblar selects estáticos (una vez) ───
  function fillSelect(el, entries, placeholder) {
    el.innerHTML = `<option value="">${placeholder}</option>` +
      entries.map(([v, label]) => `<option value="${esc(v)}">${esc(label)}</option>`).join('');
  }
  function initSelects() {
    fillSelect($('im-punto'), Object.keys(PUNTOS).map(p => [p, p]), 'Selecciona…');
    fillSelect($('im-zona'),  UBIC_ZONA.map(z => [z, z]), 'Selecciona…');
    enhanceZona($('im-zona'));
    buildSev();
  }

  function initials(n) {
    const w = String(n || '').trim().split(/\s+/);
    return ((w[0]?.[0] ?? '') + (w[1]?.[0] ?? '')).toUpperCase() || '—';
  }

  // Selector segmentado de severidad (reemplaza el <select> de impacto).
  function buildSev() {
    $('sevGrid').innerHTML = Object.entries(IMPACTOS).map(([k, v]) =>
      `<button type="button" class="inc-sev-opt" data-impacto="${esc(k)}" style="--sev:${v.color}">
         <span class="inc-sev-swatch" style="background:${v.color}"></span>
         <span class="inc-sev-name">${esc(v.label)}</span>
       </button>`).join('');
  }

  // Fija el impacto: valor oculto + estado de botones + gauge del rail.
  function setImpacto(key) {
    $('im-impacto').value = key || '';
    document.querySelectorAll('#sevGrid .inc-sev-opt').forEach(b =>
      b.classList.toggle('active', b.dataset.impacto === key));
    const idx   = SEV_ORDER.indexOf(key);
    const color = (IMPACTOS[key] || {}).color || 'rgba(255,255,255,.12)';
    $('railGauge').querySelectorAll('span').forEach((s, i) => {
      const on = idx >= 0 && i <= idx;
      s.style.background = on ? color : 'rgba(255,255,255,.12)';
      s.style.boxShadow  = on ? `0 0 8px ${color}` : 'none';
    });
    const m = IMPACTOS[key];
    $('railSevName').textContent = m ? m.label.toUpperCase() : '— sin definir —';
    $('railSevName').style.color = m ? m.color : 'rgba(255,255,255,.55)';
  }

  // Fija el turno: valor oculto + estado del toggle.
  function setTurno(key) {
    $('im-turno').value = key || '';
    document.querySelectorAll('#turnoToggle button').forEach(b =>
      b.classList.toggle('active', b.dataset.turno === key));
  }

  // ─── Alcance por coordinador a cargo ───
  // El filtro de coordinador acota la POBLACIÓN de incidencias: los KPIs se
  // calculan sobre ese subconjunto. Impacto, declaración y búsqueda son lentes
  // sobre la tabla y no mueven los KPIs (si lo hicieran, filtrar por "Alto"
  // dejaría el KPI de Crítico siempre en cero).
  function alcanceCoord() {
    if (filtroCoord === 'todos') return incidencias;
    if (filtroCoord === 'sin')   return incidencias.filter(i => !i.coord_cargo_id);
    return incidencias.filter(i => Number(i.coord_cargo_id) === Number(filtroCoord));
  }

  // ─── KPIs ───
  function renderKpis() {
    const base = alcanceCoord();
    $('kpiTotal').textContent   = base.length;
    $('kpiCritico').textContent = base.filter(i => i.impacto === 'critico').length;
    $('kpiAlto').textContent    = base.filter(i => i.impacto === 'alto').length;
    const now = new Date();
    const ym = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0');
    $('kpiMes').textContent = base.filter(i => String(i.fecha || '').startsWith(ym)).length;

    // Aviso de alcance: deja claro que los números ya no son el total global.
    const scope = $('incKpiScope');
    scope.classList.toggle('on', filtroCoord !== 'todos');
    if (filtroCoord !== 'todos') {
      const c = coordinadores.find(x => String(x.id) === String(filtroCoord));
      $('incKpiScopeName').textContent =
        filtroCoord === 'sin' ? 'colaboradores sin coordinador asignado'
                              : (c ? c.nombre : 'coordinador seleccionado');
    }
  }

  // ─── Tabla ───
  function render() {
    const q = query.trim().toLowerCase();
    const list = alcanceCoord().filter(i => {
      if (filtro !== 'todos' && i.impacto !== filtro) return false;
      if (filtroDecl === 'completo'  && !declCompleta(i)) return false;
      if (filtroDecl === 'pendiente' &&  declCompleta(i)) return false;
      if (!q) return true;
      return [i.colaborador_nombre, i.colaborador_cargo, i.punto_mejorar, i.competencia,
              i.coordinador, i.coord_cargo_nombre, i.zona_trabajo, i.detalle]
        .some(v => String(v ?? '').toLowerCase().includes(q));
    });
    const tb = $('incTbody');
    tb.innerHTML = '';
    if (!list.length) {
      tb.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:48px;color:var(--co-faint);font-size:14px;font-weight:500">Sin incidencias registradas o coincidentes con los filtros.</td></tr>`;
      return;
    }
    list.forEach(i => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="td-sticky-left">
          <div class="inc-colab-cell">
            <div class="inc-colab-avatar">${esc(initials(i.colaborador_nombre))}</div>
            <div style="min-width:0">
              <div class="inc-name" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:190px" title="${esc(i.colaborador_nombre)}">${esc(i.colaborador_nombre)}</div>
              <div class="inc-sub" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:190px" title="${esc(i.colaborador_cargo || '')}">${esc(i.colaborador_cargo || '—')}</div>
            </div>
          </div>
        </td>
        <td>${i.coord_cargo_nombre
              ? `<span class="inc-coord-chip" title="${esc(i.coord_cargo_nombre)}"><span class="ini">${esc(initials(i.coord_cargo_nombre))}</span><span class="nm">${esc(i.coord_cargo_nombre)}</span></span>`
              : '<span class="inc-coord-none">Sin asignar</span>'}</td>
        <td style="max-width:250px;white-space:normal">
          <div class="inc-name" style="color:var(--co-ink);line-height:1.3">${esc(i.punto_mejorar)}</div>
          <div class="inc-sub" style="color:var(--co-mute);margin-top:3px;font-weight:500;line-height:1.3">${esc(i.competencia)}</div>
        </td>
        <td>${impactoBadge(i.impacto)}</td>
        <td>
          <div class="inc-name" style="font-size:13px">${esc(i.zona_trabajo || '—')}</div>
          <div style="margin-top:4px"><span class="inc-turno-chip">${esc(turnoLabel(i.turno))}</span></div>
        </td>
        <td style="font-variant-numeric:tabular-nums;color:var(--co-ink);font-weight:600">${fmtFecha(i.fecha)}</td>
        <td>
          <div class="inc-name" style="font-size:13px;color:var(--co-mute);max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${esc(i.coordinador || '')}">${esc(i.coordinador || '—')}</div>
        </td>
        <td>
          <div style="display:flex;flex-direction:column;gap:5px;align-items:flex-start">
            ${estadoBadge(i)}
            <div style="font-size:11px;padding-left:2px">${diasDeclaracion(i)}</div>
          </div>
        </td>
        <td style="text-align:right">
          <div class="inc-cell-actions">
            <button class="inc-act-btn" data-action="view" data-id="${i.id}" title="Ver detalle">Ver</button>
            <button class="inc-act-btn" data-action="edit" data-id="${i.id}" title="Editar incidencia">Editar</button>
            <button class="inc-act-btn danger" data-action="del" data-id="${i.id}" title="Eliminar incidencia">Eliminar</button>
          </div>
        </td>`;
      tb.append(tr);
    });
  }

  async function cargarColaboradores() {
    const res = await fetch(`${BASE}/api/get_colaboradores.php`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Error colaboradores');
    colaboradores = (data.data || []).filter(c => c.activo === 1);
    const sel = $('im-colaborador');
    sel.innerHTML = `<option value="">Selecciona…</option>` +
      colaboradores.map(c => `<option value="${c.id}">${esc(c.nombre)} (${esc(c.codigo || '')})</option>`).join('');
    wireColaboradorSearch();
  }

  function wireColaboradorSearch() {
    const inp   = $('im-colaborador-search');
    const panel = $('im-colaborador-panel');
    const sel   = $('im-colaborador');
    if (!inp || inp._wired) return;
    inp._wired = true;

    function positionPanel() {
      const r = inp.getBoundingClientRect();
      const spaceBelow = window.innerHeight - r.bottom - 8;
      const spaceAbove = r.top - 8;
      const margin = 8;
      const width = Math.min(Math.max(r.width, 320), window.innerWidth - margin * 2);
      panel.style.left  = Math.max(margin, Math.min(r.left, window.innerWidth - width - margin)) + 'px';
      panel.style.width = width + 'px';
      if (spaceBelow >= 150 || spaceBelow >= spaceAbove) {
        panel.style.top    = (r.bottom + 4) + 'px';
        panel.style.bottom = 'auto';
        panel.style.maxHeight = Math.min(240, spaceBelow) + 'px';
      } else {
        panel.style.bottom = (window.innerHeight - r.top + 4) + 'px';
        panel.style.top    = 'auto';
        panel.style.maxHeight = Math.min(240, spaceAbove) + 'px';
      }
    }

    // Mover el panel a document.body para evitar que transform del modal rompa position:fixed
    document.body.appendChild(panel);

    function buildItems(q) {
      const lq = q.toLowerCase().trim();
      const base = lq
        ? colaboradores.filter(c => (c.nombre + ' ' + (c.codigo || '')).toLowerCase().includes(lq))
        : colaboradores;
      return [...base].sort((a, b) => (a.nombre || '').localeCompare(b.nombre || '', 'es'));
    }

    function renderPanel(q) {
      const items = buildItems(q);
      if (!items.length) {
        panel.innerHTML = '<div class="inc-colsel-empty">Sin resultados</div>';
      } else {
        panel.innerHTML = items.slice(0, 80).map(c => {
          const parts = (c.nombre || '').trim().split(' ');
          const initials = (parts[0]?.[0] || '') + (parts[1]?.[0] || '');
          return `<div class="inc-colsel-item" data-id="${c.id}">
            <div class="inc-colsel-avatar">${esc(initials.toUpperCase())}</div>
            <div class="inc-colsel-info">
              <span class="inc-colsel-nm">${esc(c.nombre)}</span>
              <span class="inc-colsel-cd">${esc(c.codigo || '')}${c.funcion_principal ? ' · ' + esc(c.funcion_principal) : ''}</span>
            </div>
          </div>`;
        }).join('');
      }
      positionPanel();
      panel.classList.add('open');
    }

    function closePanel() { panel.classList.remove('open'); }

    function selectColaborador(id) {
      const c = colaboradores.find(x => String(x.id) === String(id));
      sel.value = id;
      inp.value = c ? c.nombre + ' (' + (c.codigo || '') + ')' : '';
      closePanel();
      syncCargo();
    }

    inp.addEventListener('focus', () => renderPanel(inp.value));
    inp.addEventListener('input', () => renderPanel(inp.value));
    panel.addEventListener('mousedown', e => {
      const it = e.target.closest('.inc-colsel-item');
      if (!it) return;
      e.preventDefault();
      selectColaborador(it.dataset.id);
    });
    document.addEventListener('mousedown', e => {
      const wrap = $('im-colaborador-wrap');
      if (wrap && !wrap.contains(e.target) && !panel.contains(e.target)) closePanel();
    }, true);

    // exponer para openModal
    inp._select = selectColaborador;
  }

  // Coordinadores Tallyman: mismo catálogo que usa Colaboradores (usuarios con
  // rol Coordinador). Sólo alimenta el filtro; el nombre que se muestra en cada
  // fila viene resuelto desde el endpoint.
  async function cargarCoordinadores() {
    const res = await fetch(`${BASE}/api/get_coordinadores.php`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Error coordinadores');
    coordinadores = data.data || [];
    $('incFilterCoord').innerHTML =
      '<option value="todos">Todos</option>'
      + coordinadores.map(c => `<option value="${c.id}">${esc(c.nombre)}</option>`).join('')
      + '<option value="sin">Sin coordinador</option>';
    $('incFilterCoord').value = filtroCoord;
  }

  function setFiltroCoord(v) {
    filtroCoord = v;
    $('incFilterCoord').value = v;
    $('incFilterCoordWrap').classList.toggle('on', v !== 'todos');
    renderKpis(); render();
  }

  async function cargarIncidencias() {
    const res = await fetch(`${BASE}/api/get_incidencias.php`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.success) { toast(data.error || 'Error al cargar', 'error'); return; }
    incidencias = data.data || [];
    renderKpis(); render();
  }

  // ════════════════ MODAL REGISTRAR / EDITAR ════════════════
  function setFileInfo(which, path) {
    const info = which === 'foto' ? $('infoFoto') : $('infoDecl');
    if (!path) { info.style.display = 'none'; info.innerHTML = ''; return; }
    const name = path.split('/').pop();
    info.style.display = 'flex';
    info.innerHTML = `<a href="${BASE}/${esc(path)}" target="_blank" rel="noopener">${esc(name)}</a>
                      <button type="button" class="rm" data-rm="${which}">Quitar</button>`;
  }

  function openModal(id) {
    editingId = id ? Number(id) : null;
    const i = editingId ? incidencias.find(x => Number(x.id) === editingId) : null;
    $('incModalTitle').textContent = i ? 'Editar incidencia' : 'Registrar incidencia';
    $('railFolio').textContent = i ? ('N° ' + String(i.id).padStart(4, '0')) : 'NUEVO';
    $('railCoord').textContent = (i && i.coordinador) ? i.coordinador : COORD;
    $('im-id').value          = i ? i.id : '';
    $('im-colaborador').value = i ? (i.colaborador_id ?? '') : '';
    const _srch = $('im-colaborador-search');
    if (_srch) {
      if (i && i.colaborador_id) {
        const _c = colaboradores.find(x => Number(x.id) === Number(i.colaborador_id));
        _srch.value = _c ? _c.nombre + ' (' + (_c.codigo || '') + ')' : '';
      } else { _srch.value = ''; }
    }
    $('im-punto').value       = i ? (i.punto_mejorar ?? '') : '';
    $('im-fecha').value       = i ? (i.fecha ?? '') : new Date().toISOString().slice(0,10);
    $('im-zona').value        = i ? (i.zona_trabajo ?? '') : '';
    $('im-detalle').value     = i ? (i.detalle ?? '') : '';
    setImpacto(i ? (i.impacto || '') : '');
    setTurno(i ? (i.turno || '') : '');
    syncCargo();
    syncCompetencia();
    formFoto = i ? (i.foto_path || null) : null;
    formDecl = i ? (i.declaracion_path || null) : null;
    setFileInfo('foto', formFoto);
    setFileInfo('decl', formDecl);
    $('im-foto').value = ''; $('im-declaracion').value = '';
    $('incModalBack').classList.add('open');
  }
  function closeModal() { $('incModalBack').classList.remove('open'); editingId = null; }

  function syncCargo() {
    const id = Number($('im-colaborador').value || 0);
    const c = colaboradores.find(x => Number(x.id) === id);
    $('im-cargo').value         = c ? (c.funcion_principal || '') : '';
    $('railName').textContent   = c ? c.nombre : 'Sin colaborador';
    $('railCargo').textContent  = c ? (c.funcion_principal || '—') : 'Selecciona un colaborador';
    $('railAvatar').textContent = c ? initials(c.nombre) : '—';
  }
  function syncCompetencia() {
    const p = $('im-punto').value;
    const comp = PUNTOS[p] || '';
    $('im-competencia').value = comp;
    $('railComp').textContent = comp || 'Se asigna según el punto';
  }

  async function subirArchivo(file, tipo) {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('tipo', tipo);
    const res = await fetch(`${BASE}/api/upload_incidencia_file.php`, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Error al subir');
    return data.path;
  }

  async function handleFile(which, file) {
    if (!file) return;
    try {
      toast('Subiendo archivo…');
      const path = await subirArchivo(file, which === 'foto' ? 'foto' : 'declaracion');
      if (which === 'foto') { formFoto = path; setFileInfo('foto', path); }
      else { formDecl = path; setFileInfo('decl', path); }
      toast('Archivo subido');
    } catch (e) {
      toast(e.message || 'Error al subir archivo', 'error');
    }
  }

  async function guardar() {
    const payload = {
      id:               Number($('im-id').value || 0),
      colaborador_id:   Number($('im-colaborador').value || 0),
      punto_mejorar:    $('im-punto').value,
      impacto:          $('im-impacto').value,
      turno:            $('im-turno').value,
      fecha:            $('im-fecha').value,
      zona_trabajo:     $('im-zona').value,
      detalle:          $('im-detalle').value.trim(),
      foto_path:        formFoto || '',
      declaracion_path: formDecl || '',
    };
    if (!payload.colaborador_id) { toast('Selecciona un colaborador', 'error'); return; }
    if (!payload.punto_mejorar)  { toast('Selecciona el punto a mejorar', 'error'); return; }
    if (!payload.impacto)        { toast('Selecciona el impacto', 'error'); return; }
    if (!payload.turno)          { toast('Selecciona el turno', 'error'); return; }
    if (!payload.fecha)          { toast('Selecciona la fecha', 'error'); return; }
    if (!payload.zona_trabajo)   { toast('Selecciona la zona de trabajo', 'error'); return; }

    const btn = $('incModalSave');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res = await fetch(`${BASE}/api/save_incidencia.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (data.success) {
        toast(payload.id ? 'Incidencia actualizada' : 'Incidencia registrada');
        closeModal();
        cargarIncidencias();
      } else {
        toast(data.error || 'Error al guardar', 'error');
      }
    } catch (e) {
      toast('Error de red', 'error');
    } finally {
      btn.disabled = false; btn.textContent = 'Guardar';
    }
  }

  async function eliminar(id) {
    const i = incidencias.find(x => Number(x.id) === Number(id));
    if (!i) return;
    if (!confirm(`¿Eliminar la incidencia de "${i.colaborador_nombre}" (${i.punto_mejorar})?\nSe borrarán también sus archivos adjuntos.`)) return;
    try {
      const res = await fetch(`${BASE}/api/delete_incidencia.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id) }),
      });
      const data = await res.json();
      if (data.success) { toast('Incidencia eliminada'); cargarIncidencias(); }
      else { toast(data.error || 'Error al eliminar', 'error'); }
    } catch (e) { toast('Error de red', 'error'); }
  }

  // ════════════════ VISTA DETALLE ════════════════
  function openView(id) {
    const i = incidencias.find(x => Number(x.id) === Number(id));
    if (!i) return;
    const m = IMPACTOS[i.impacto] || { label: i.impacto || '—', color: '#64748b' };

    let attachHtml = '';
    if (i.foto_path || i.declaracion_path || i.foto_drive_url || i.declaracion_drive_url) {
      let links = '';
      if (i.foto_path) {
        links += `<a href="${BASE}/${esc(i.foto_path)}" target="_blank" rel="noopener" class="inc-view-attach inc-view-attach--foto">📷 Ver foto</a>`;
      }
      if (i.foto_drive_url) {
        links += `<a href="${esc(i.foto_drive_url)}" target="_blank" rel="noopener" class="inc-view-attach inc-view-attach--drive">☁ Foto en Drive</a>`;
      }
      if (i.declaracion_path) {
        links += `<a href="${BASE}/${esc(i.declaracion_path)}" target="_blank" rel="noopener" class="inc-view-attach inc-view-attach--decl">📄 Declaración</a>`;
      }
      if (i.declaracion_drive_url) {
        links += `<a href="${esc(i.declaracion_drive_url)}" target="_blank" rel="noopener" class="inc-view-attach inc-view-attach--drive">☁ Declaración en Drive</a>`;
      }
      attachHtml = `<div class="inc-view-attachments">${links}</div>`;
    }
    if (i.foto_path) {
      attachHtml += `<a href="${BASE}/${esc(i.foto_path)}" target="_blank" rel="noopener"><img class="inc-thumb" src="${BASE}/${esc(i.foto_path)}" alt="foto"></a>`;
    }

    $('incViewBody').innerHTML = `
      <div class="inc-view-layout">
        <div class="inc-view-sidebar">
          <span class="iv-badge" style="background:${esc(m.color)}">● ${esc(m.label)}</span>
          <hr class="iv-divider">
          <div class="iv-stat">
            <span class="iv-stat-k">Turno</span>
            <span class="iv-stat-v">${esc(turnoLabel(i.turno))}</span>
          </div>
          <div class="iv-stat">
            <span class="iv-stat-k">Fecha</span>
            <span class="iv-stat-v">${esc(fmtFecha(i.fecha))}</span>
          </div>
          <div class="iv-stat">
            <span class="iv-stat-k">Zona</span>
            <span class="iv-stat-v">${esc(i.zona_trabajo || '—')}</span>
          </div>
          <div class="iv-stat">
            <span class="iv-stat-k">Declaración</span>
            <span class="iv-stat-v">${declCompleta(i) ? 'Completo' : 'Pendiente'}</span>
          </div>
          <div class="iv-stat">
            <span class="iv-stat-k">Días de demora</span>
            <span class="iv-stat-v">${diasDeclaracionTexto(i)}</span>
          </div>
          <div class="iv-coord">
            <span class="iv-stat-k">Coord. a cargo</span>
            <span class="iv-stat-v">${esc(i.coord_cargo_nombre || 'Sin asignar')}</span>
          </div>
          <div class="iv-coord">
            <span class="iv-stat-k">Registró</span>
            <span class="iv-stat-v">${esc(i.coordinador || '—')}</span>
          </div>
        </div>
        <div class="inc-view-main">
          <div>
            <p class="inc-view-name">${esc(i.colaborador_nombre)}</p>
            <p class="inc-view-cargo">${esc(i.colaborador_cargo || '—')}</p>
          </div>
          <hr class="inc-view-divider">
          <div class="inc-view-field inc-view-field--blue">
            <span class="iv-k">Punto a mejorar</span>
            <span class="iv-v">${esc(i.punto_mejorar)}</span>
          </div>
          <div class="inc-view-field inc-view-field--purple">
            <span class="iv-k">Competencia afectada</span>
            <span class="iv-v">${esc(i.competencia)}</span>
          </div>
           <div class="inc-view-detalle">
             <span class="iv-k">Detalle</span>
             <span class="iv-v">${esc(i.detalle || '—')}</span>
           </div>
           ${medidaHtml(i)}
           ${attachHtml}
        </div>
      </div>`;

    $('incViewSub').textContent = `${i.colaborador_nombre} · ${fmtFecha(i.fecha)}`;
    $('incViewEdit').dataset.id = i.id;
    wireMedida(i);
    $('incViewBack').classList.add('open');
  }
  function closeView() { $('incViewBack').classList.remove('open'); }

  // ─── Eventos ───
  $('btnNew').addEventListener('click', () => openModal(null));
  $('incSearch').addEventListener('input', e => { query = e.target.value; render(); });
  $('incFilter').addEventListener('click', e => {
    const b = e.target.closest('button[data-f]'); if (!b) return;
    filtro = b.dataset.f;
    $('incFilter').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
    render();
  });
  $('incFilterCoord').addEventListener('change', e => setFiltroCoord(e.target.value));
  $('incKpiScopeClear').addEventListener('click', () => setFiltroCoord('todos'));
  $('incFilterDecl').addEventListener('click', e => {
    const b = e.target.closest('button[data-fd]'); if (!b) return;
    filtroDecl = b.dataset.fd;
    $('incFilterDecl').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
    render();
  });
  $('incTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action]'); if (!b) return;
    if (b.dataset.action === 'view') openView(b.dataset.id);
    if (b.dataset.action === 'edit') openModal(b.dataset.id);
    if (b.dataset.action === 'del')  eliminar(b.dataset.id);
  });

  $('im-colaborador').addEventListener('change', syncCargo);
  $('im-punto').addEventListener('change', syncCompetencia);
  $('sevGrid').addEventListener('click', e => {
    const b = e.target.closest('[data-impacto]'); if (!b) return;
    setImpacto(b.dataset.impacto);
  });
  $('turnoToggle').addEventListener('click', e => {
    const b = e.target.closest('[data-turno]'); if (!b) return;
    setTurno(b.dataset.turno);
  });

  // Adjuntos
  $('dropFoto').addEventListener('click', () => $('im-foto').click());
  $('dropDecl').addEventListener('click', () => $('im-declaracion').click());
  $('im-foto').addEventListener('change', e => handleFile('foto', e.target.files[0]));
  $('im-declaracion').addEventListener('change', e => handleFile('decl', e.target.files[0]));
  document.addEventListener('click', e => {
    const rm = e.target.closest('[data-rm]'); if (!rm) return;
    if (rm.dataset.rm === 'foto') { formFoto = null; setFileInfo('foto', null); $('im-foto').value=''; }
    else { formDecl = null; setFileInfo('decl', null); $('im-declaracion').value=''; }
  });

  $('incModalClose').addEventListener('click', closeModal);
  $('incModalCancel').addEventListener('click', closeModal);
  $('incModalSave').addEventListener('click', guardar);
  $('incModalBack').addEventListener('click', e => { if (e.target === $('incModalBack')) closeModal(); });

  $('incViewClose').addEventListener('click', closeView);
  $('incViewCloseBtn').addEventListener('click', closeView);
  $('incViewBack').addEventListener('click', e => { if (e.target === $('incViewBack')) closeView(); });
  $('incViewEdit').addEventListener('click', e => { closeView(); openModal(e.currentTarget.dataset.id); });

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if ($('incModalBack').classList.contains('open')) closeModal();
    if ($('incViewBack').classList.contains('open')) closeView();
  });

  // ─── Init ───
  initSelects();
  (async () => {
    try { await cargarColaboradores(); }
    catch (e) { toast('No se pudieron cargar los colaboradores', 'error'); }
    try { await cargarCoordinadores(); }
    catch (e) { toast('No se pudo cargar el filtro de coordinadores', 'error'); }
    cargarIncidencias();
  })();
})();
</script>

</body>
</html>
