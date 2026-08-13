<?php
require_once('../includes/auth.php');
require_once('../includes/db.php');
require_once('../includes/asistencias_catalogo.php');
require_report();

// Catálogos → JS
$JS_TIPOS  = aso_tipos_reunion();          // clave => label
$JS_TURNOS = inc_turnos();                 // clave => label
$COORDINADOR = $_SESSION['user_name'] ?? '';

// Zonas activas (tabla ubicaciones), igual que Incidencias.
$zona_ubicaciones = inc_zonas(true);

// Logo embebido para el PDF de asistencia.
$logo_path = __DIR__ . '/../logo/logo.png';
$LOGO_B64  = is_file($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';

// Límite de evidencias (mismo que Sugerencias/Drive).
require_once('../includes/drive_config.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Charlas · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <link rel="stylesheet" href="../css/estiba.css">

  <style>
    /* ════════════ ASISTENCIAS PRE-OPERATIVAS (prefijo .aso-*) ════════════ */
    .aso-wrap {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0,135,90,.18); --co-line-bold:rgba(0,135,90,.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .aso-wrap *, .aso-wrap *::before, .aso-wrap *::after { box-sizing:border-box; }

    .aso-hero {
      background:linear-gradient(135deg,#005c3d 0%,#00875A 100%);
      color:#fff; border-radius:20px; padding:22px 28px;
      display:flex; align-items:center; justify-content:space-between; gap:18px;
      box-shadow:0 8px 32px rgba(0,135,90,.08);
    }
    .aso-hero h1 { margin:6px 0 4px; font-size:22px; font-weight:700; letter-spacing:-.01em; }
    .aso-hero p  { margin:0; color:rgba(255,255,255,.85); font-size:13px; max-width:560px; }
    .aso-hero .tag { display:inline-flex; align-items:center; padding:5px 11px; border-radius:999px;
      background:rgba(255,255,255,.15); font-size:11px; font-weight:700; letter-spacing:.06em; }

    .aso-btn { display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:10px;
      border:1px solid rgba(0,135,90,.3); background:#fff; cursor:pointer; text-decoration:none;
      font-family:inherit; font-size:13px; font-weight:600; color:#00875A; transition:all .15s; }
    .aso-btn svg { width:15px; height:15px; }
    .aso-btn:hover { background:rgba(0,135,90,.05); }
    .aso-btn.ghost-light { background:rgba(255,255,255,.15); color:#fff; border-color:rgba(255,255,255,.25); }
    .aso-btn.ghost-light:hover { background:rgba(255,255,255,.25); }
    .aso-btn.primary { background:linear-gradient(135deg,#00875A 0%,#005c3d 100%); color:#fff;
      border:none; font-weight:700; box-shadow:0 4px 18px rgba(0,135,90,.2); }
    .aso-btn.primary:hover { filter:brightness(1.08); transform:translateY(-1px); }
    .aso-btn.danger { color:#dc2626; border-color:rgba(220,38,38,.3); }
    .aso-btn.danger:hover { background:rgba(220,38,38,.06); }
    .aso-btn:disabled { opacity:.55; cursor:not-allowed; transform:none; filter:none; }

    .aso-kpis { display:flex; gap:10px; flex-wrap:wrap; }
    .aso-kpi { flex:1; min-width:150px; background:#fff; border:1px solid var(--co-line);
      border-radius:14px; padding:16px 18px; }
    .aso-kpi .lbl { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--co-mute); }
    .aso-kpi .val { font-size:30px; font-weight:800; color:var(--co-navy-700); margin-top:4px; }

    .aso-toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap;
      background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:12px 14px; }
    .aso-search { flex:1; min-width:220px; display:flex; align-items:center; gap:8px;
      background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:10px; padding:9px 12px; }
    .aso-search svg { width:16px; height:16px; color:var(--co-faint); flex-shrink:0; }
    .aso-search input { flex:1; border:none; background:none; outline:none; font:inherit; font-size:13px; color:var(--co-ink); }
    .aso-filter { display:flex; gap:4px; flex-wrap:wrap; }
    .aso-filter button { padding:8px 12px; border:1px solid var(--co-line); background:#fff; border-radius:9px;
      font:inherit; font-size:12.5px; font-weight:600; color:var(--co-mute); cursor:pointer; }
    .aso-filter button.active { background:#00875A; border-color:#00875A; color:#fff; }
    .aso-filter-lbl { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
      color:var(--co-mute); align-self:center; }
    .aso-filter--punt button[data-p="ontime"].active  { background:#059669; border-color:#059669; }
    .aso-filter--punt button[data-p="offtime"].active { background:#dc2626; border-color:#dc2626; }

    /* Puntualidad de la charla: se compara la hora contra el límite del turno. */
    .aso-punt { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px;
      font-size:10.5px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; white-space:nowrap; }
    .aso-punt .dot { width:6px; height:6px; border-radius:50%; }
    .aso-punt.on  { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.3); color:#059669; }
    .aso-punt.on .dot  { background:#10b981; }
    .aso-punt.off { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); color:#dc2626; }
    .aso-punt.off .dot { background:#ef4444; }

    .aso-table-card { background:#fff; border:1px solid var(--co-line); border-radius:14px; overflow:hidden; }
    .aso-table { width:100%; border-collapse:collapse; }
    .aso-table thead th { background:#f8fafc; text-align:left; font-size:10.5px; text-transform:uppercase;
      letter-spacing:.05em; color:var(--co-mute); padding:12px 14px; border-bottom:1px solid var(--co-line); white-space:nowrap; }
    .aso-table tbody td { padding:12px 14px; border-bottom:1px solid #f1f5f9; font-size:13px; vertical-align:middle; }
    .aso-table tbody tr:hover { background:#f8fafc; }
    .aso-tema { font-weight:700; color:var(--co-ink); }
    .aso-sub { font-size:11.5px; color:var(--co-mute); }
    .aso-chip { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px;
      font-size:11px; font-weight:700; background:rgba(0,135,90,.1); color:#00875A; }
    .aso-chip.turno-noche { background:#1e293b; color:#fff; }
    .aso-count { display:inline-flex; align-items:center; justify-content:center; min-width:26px; height:22px;
      padding:0 7px; border-radius:7px; background:#eef2f7; font-size:12px; font-weight:800; color:#334155; }
    .aso-act { display:flex; gap:6px; justify-content:flex-end; }
    .aso-act button { padding:6px 11px; border-radius:8px; border:1px solid var(--co-line); background:#fff;
      font:inherit; font-size:12px; font-weight:600; color:var(--co-mute); cursor:pointer; }
    .aso-act button:hover { border-color:#00875A; color:#00875A; }
    .aso-act button.danger:hover { border-color:#dc2626; color:#dc2626; }

    /* ── Modal ── */
    .aso-modal-back { position:fixed; inset:0; background:rgba(15,23,42,.5); backdrop-filter:blur(2px);
      display:none; align-items:center; justify-content:center; z-index:200; padding:20px;
      font-family:'DM Sans', system-ui, sans-serif; }
    .aso-modal-back.open { display:flex; }
    .aso-modal { background:#fff; border-radius:18px; width:100%; max-width:760px; max-height:92vh;
      display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 70px rgba(0,0,0,.25); }
    .aso-modal-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
      padding:20px 24px; border-bottom:1px solid var(--co-line); }
    .aso-modal-head h2 { margin:0; font-size:18px; font-weight:800; color:var(--co-ink); }
    .aso-modal-head .sub { font-size:12.5px; color:var(--co-mute); margin-top:2px; }
    .aso-modal-close { border:none; background:#f1f5f9; width:34px; height:34px; border-radius:9px;
      font-size:20px; color:#64748b; cursor:pointer; flex-shrink:0; }
    .aso-modal-close:hover { background:#e2e8f0; }
    .aso-modal-body { padding:20px 24px; overflow-y:auto; display:flex; flex-direction:column; gap:16px; }
    .aso-modal-foot { display:flex; align-items:center; justify-content:space-between; gap:10px;
      padding:16px 24px; border-top:1px solid var(--co-line); background:#f8fafc; }

    .aso-step { font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8;
      display:flex; align-items:center; gap:8px; margin-bottom:2px; }
    .aso-step .n { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px;
      border-radius:6px; background:#eef2f7; color:#475569; font-size:10.5px; }
    .aso-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .aso-field { display:flex; flex-direction:column; gap:5px; }
    .aso-field.full { grid-column:1 / -1; }
    .aso-field label { font-size:11.5px; font-weight:700; color:#475569; }
    .aso-field input[type=text], .aso-field input[type=date], .aso-field input[type=time],
    .aso-field select, .aso-field textarea {
      width:100%; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:10px;
      font:inherit; font-size:13px; color:var(--co-ink); background:#fff; outline:none; }
    .aso-field textarea { min-height:60px; resize:vertical; }
    .aso-field input:focus, .aso-field select:focus, .aso-field textarea:focus {
      border-color:#00875A; box-shadow:0 0 0 3px rgba(0,135,90,.12); }

    .aso-turno-toggle { display:flex; gap:6px; }
    .aso-turno-toggle button { flex:1; padding:10px; border:1.5px solid #e2e8f0; background:#fff; border-radius:10px;
      font:inherit; font-size:13px; font-weight:700; color:var(--co-mute); cursor:pointer;
      display:inline-flex; align-items:center; justify-content:center; gap:7px; }
    .aso-turno-toggle button.active[data-t="dia"] { background:#fef9c3; border-color:#eab308; color:#854d0e; }
    .aso-turno-toggle button.active[data-t="noche"] { background:#1e293b; border-color:#1e293b; color:#fff; }

    /* Tipo de reunión (chips estilo checkbox) */
    .aso-tipos { display:flex; flex-wrap:wrap; gap:6px; }
    .aso-tipos button { padding:8px 12px; border:1.5px solid #e2e8f0; background:#fff; border-radius:9px;
      font:inherit; font-size:12px; font-weight:600; color:var(--co-mute); cursor:pointer; }
    .aso-tipos button.active { background:#00875A; border-color:#00875A; color:#fff; }

    /* Selector de participantes */
    .aso-picker { border:1.5px solid #e2e8f0; border-radius:12px; overflow:hidden; }
    .aso-picker-search { display:flex; align-items:center; gap:8px; padding:10px 12px; border-bottom:1px solid #eef2f7; }
    .aso-picker-search svg { width:15px; height:15px; color:var(--co-faint); }
    .aso-picker-search input { flex:1; border:none; outline:none; font:inherit; font-size:13px; }
    .aso-picker-list { max-height:200px; overflow-y:auto; }
    .aso-opt { display:flex; align-items:center; gap:10px; padding:9px 12px; cursor:pointer; border-bottom:1px solid #f5f7fa; }
    .aso-opt:hover { background:#f8fafc; }
    .aso-opt input { width:16px; height:16px; accent-color:#00875A; }
    .aso-opt .nm { font-size:13px; font-weight:600; color:var(--co-ink); }
    .aso-opt .mt { font-size:11px; color:var(--co-mute); }
    .aso-opt.sel { background:#f0fdf4; }
    .aso-opt.blocked { opacity:.6; cursor:not-allowed; background:#fafbfc; }
    .aso-opt.blocked:hover { background:#fafbfc; }
    .aso-opt.blocked .nm { color:var(--co-mute); }
    .aso-part-conflict { flex-shrink:0; font-size:10px; font-weight:700; color:#b45309;
      background:#fef3c7; border:1px solid #fde68a; border-radius:999px; padding:3px 9px; white-space:nowrap; }

    .aso-selected { display:flex; flex-direction:column; gap:6px; }
    .aso-selhead { font-size:12px; font-weight:700; color:#475569; display:flex; align-items:center; gap:8px; }
    .aso-part { display:flex; align-items:center; gap:10px; border:1px solid #e5e7eb; border-radius:10px; padding:9px 11px; }
    .aso-part .info { flex:1; min-width:0; }
    .aso-part .nm { font-size:13px; font-weight:600; color:var(--co-ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .aso-part .mt { font-size:11px; color:var(--co-mute); }
    .aso-part button { border:1px solid var(--co-line); background:#fff; border-radius:8px; padding:6px 10px;
      font:inherit; font-size:12px; font-weight:600; color:#00875A; cursor:pointer; }
    .aso-part button.rm { color:#dc2626; border-color:rgba(220,38,38,.25); }

    /* Estado de asistencia por participante: Asistió (A) · Falta (F) · Tardanza (T) */
    .aso-estado-toggle { display:flex; gap:4px; flex-shrink:0; }
    .aso-estado-toggle button { width:30px; height:28px; border:1.5px solid var(--co-line); background:#fff;
      border-radius:7px; font:inherit; font-size:12px; font-weight:800; color:var(--co-mute); cursor:pointer; }
    .aso-estado-toggle button.on[data-e="asistio"]  { background:#00875A; border-color:#00875A; color:#fff; }
    .aso-estado-toggle button.on[data-e="falta"]    { background:#dc2626; border-color:#dc2626; color:#fff; }
    .aso-estado-toggle button.on[data-e="tardanza"] { background:#d97706; border-color:#d97706; color:#fff; }
    .aso-estado-badge { font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px; }
    .aso-estado-badge.asistio  { background:rgba(0,135,90,.12); color:#00875A; }
    .aso-estado-badge.falta    { background:rgba(220,38,38,.12); color:#dc2626; }
    .aso-estado-badge.tardanza { background:rgba(217,119,6,.12); color:#d97706; }

    /* Evidencias */
    .aso-ev-drop { display:flex; align-items:center; justify-content:center; gap:8px; cursor:pointer;
      border:1.5px dashed #c7d2dd; border-radius:11px; padding:12px; background:#fbfcfd;
      font-size:12.5px; font-weight:700; color:var(--co-mute); }
    .aso-ev-drop svg { width:17px; height:17px; flex-shrink:0; }
    .aso-ev-drop:hover { border-color:#00875A; color:#00875A; background:#f0fdf4; }
    .aso-ev-drop.lleno { opacity:.5; cursor:not-allowed; }
    .aso-ev-item { display:flex; align-items:center; gap:9px; border:1px solid #e5e7eb; border-radius:9px; padding:8px 10px; }
    .aso-ev-item .nm { flex:1; min-width:0; font-size:12px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .aso-ev-item .mt { font-size:10.5px; color:#98a2b3; }
    .aso-ev-item.pend { background:#fffbeb; border-color:#fde68a; }
    .aso-ev-item .rm { border:none; background:none; color:#b0b8c4; font-size:17px; cursor:pointer; }
    .aso-ev-item .rm:hover { color:#dc2626; }
    .aso-hint { font-size:11px; color:#98a2b3; }

    .aso-empty { text-align:center; padding:40px; color:var(--co-faint); }

    /* Vista detalle */
    .aso-view-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .aso-view-field label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; }
    .aso-view-field div { font-size:13.5px; color:var(--co-ink); margin-top:2px; }
    .aso-view-part { display:flex; align-items:center; gap:10px; padding:7px 0; border-bottom:1px solid #f1f5f9; }
    .aso-view-part img { height:34px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; }
    .aso-view-part .fno { font-size:11px; color:#b45309; }

    /* Galería de evidencias en la vista */
    .aso-ev-gallery { display:grid; grid-template-columns:repeat(auto-fill,minmax(132px,1fr)); gap:10px; }
    .aso-ev-card { border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; text-decoration:none; color:inherit; background:#fff; display:flex; flex-direction:column; }
    .aso-ev-card:hover { border-color:#00875A; box-shadow:0 2px 8px rgba(0,135,90,.12); }
    .aso-ev-card .thumb { height:98px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .aso-ev-card .thumb img { width:100%; height:100%; object-fit:cover; }
    .aso-ev-card .thumb svg { width:34px; height:34px; color:#94a3b8; }
    .aso-ev-card .cap { padding:7px 9px; }
    .aso-ev-card .cap .nm { font-size:11.5px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .aso-ev-card .cap .mt { font-size:10px; color:#94a3b8; }
    .aso-ev-card.pend { background:#fffbeb; border-color:#fde68a; }
    .aso-ev-card.pend .cap .mt { color:#b45309; }

    .aso-toast { position:fixed; bottom:24px; right:24px; z-index:400; background:#111827; color:#fff;
      padding:12px 18px; border-radius:10px; font-size:13px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,.2);
      opacity:0; transform:translateY(10px); transition:all .2s; pointer-events:none; }
    .aso-toast.show { opacity:1; transform:translateY(0); }
    .aso-toast.error { background:#dc2626; }

    @media (max-width:640px){
      .aso-modal-back { padding:6px; align-items:center; }
      .aso-modal { width:calc(100vw - 12px); max-width:none; height:95dvh; max-height:95dvh; border-radius:14px; }
      .aso-modal-head { padding:15px 16px 12px; }.aso-modal-head h2 { font-size:16px; }.aso-modal-head .sub { font-size:12px; }
      .aso-modal-body { min-height:0; flex:1; padding:14px 16px; gap:14px; overscroll-behavior:contain; }
      .aso-modal-foot { padding:12px 16px; align-items:stretch; flex-direction:column; }
      .aso-modal-foot > div { width:100%; }.aso-modal-foot > div .aso-btn { flex:1; justify-content:center; min-height:44px; }
      .aso-grid, .aso-view-grid { grid-template-columns:1fr; }
      .aso-tipos { gap:5px; }.aso-tipos button { flex:1 1 142px; min-height:40px; padding:8px 10px; }
      .aso-picker-list { max-height:175px; }.aso-opt { padding:10px; }.aso-opt .nm { font-size:12px; }
      .aso-part { align-items:flex-start; flex-wrap:wrap; }.aso-part .info { flex-basis:calc(100% - 34px); }.aso-estado-toggle { margin-left:auto; }.aso-part .rm { min-height:34px; }
      .aso-hint { line-height:1.35; }
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
      <div class="aso-wrap">

        <!-- HERO -->
        <section class="aso-hero">
          <div>
            <span class="tag">CONTROL DE CAMPO · CHARLAS</span>
            <h1>Charlas</h1>
            <p>Registra las charlas y reuniones pre-operativas: elige los tallys participantes, marca su asistencia (asistió, falta o tardanza), adjunta evidencias y exporta el registro de asistencia en PDF.</p>
          </div>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
            <button class="aso-btn primary" id="btnNew">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Registrar asistencia
            </button>
          </div>
        </section>

        <!-- KPIS -->
        <section class="aso-kpis">
          <div class="aso-kpi"><div class="lbl">Reuniones</div><div class="val" id="kpiTotal">0</div></div>
          <div class="aso-kpi"><div class="lbl">Participaciones</div><div class="val" id="kpiPart">0</div></div>
          <div class="aso-kpi"><div class="lbl">Este mes</div><div class="val" id="kpiMes">0</div></div>
          <div class="aso-kpi"><div class="lbl">Hoy</div><div class="val" id="kpiHoy">0</div></div>
        </section>

        <!-- TOOLBAR -->
        <div class="aso-toolbar">
          <div class="aso-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="asoSearch" type="text" placeholder="Buscar por tema, capacitador, zona…">
          </div>
          <div class="aso-filter" id="asoFilter">
            <button class="active" data-f="todos">Todos</button>
            <button data-f="dia">Día</button>
            <button data-f="noche">Noche</button>
          </div>
          <span class="aso-filter-lbl">Puntualidad</span>
          <div class="aso-filter aso-filter--punt" id="asoFilterPunt"
               title="Día hasta las 06:45 · Noche hasta las 18:45">
            <button class="active" data-p="todos">Todas</button>
            <button data-p="ontime">On time</button>
            <button data-p="offtime">Off time</button>
          </div>
        </div>

        <!-- TABLA -->
        <div class="aso-table-card">
          <table class="aso-table">
            <thead>
              <tr>
                <th>Tema / Actividad</th><th>Tipo</th><th>Capacitador</th>
                <th>Turno</th><th>Fecha</th><th>Puntualidad</th><th>Zona</th>
                <th style="text-align:center">Participantes</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody id="asoTbody">
              <tr><td colspan="9" class="aso-empty">Cargando…</td></tr>
            </tbody>
          </table>
        </div>

      </div>
    </main>
  </div>
</div>

<!-- ══════════ MODAL REGISTRO ══════════ -->
<div class="aso-modal-back" id="asoModalBack">
  <div class="aso-modal">
    <div class="aso-modal-head">
      <div>
        <h2 id="asoModalTitle">Registrar asistencia pre-operativa</h2>
        <div class="sub">Los datos de la charla se aplican a todos los participantes.</div>
      </div>
      <button class="aso-modal-close" id="asoModalClose">×</button>
    </div>

    <div class="aso-modal-body">
      <input type="hidden" id="im-id" value="">

      <!-- 01 · Datos de la reunión -->
      <div>
        <div class="aso-step"><span class="n">01</span> Datos de la reunión</div>
        <div class="aso-grid">
          <div class="aso-field full">
            <label>Tema / Actividad</label>
            <input type="text" id="im-tema" maxlength="150" placeholder="Ej. Charla Pre Operativa">
          </div>
          <div class="aso-field full">
            <label>Tipo de reunión</label>
            <div class="aso-tipos" id="im-tipos"></div>
          </div>
          <div class="aso-field">
            <label>Capacitador</label>
            <input type="text" id="im-capacitador" maxlength="120" placeholder="Nombre del capacitador">
          </div>
          <div class="aso-field">
            <label>Lugar</label>
            <input type="text" id="im-lugar" maxlength="120" placeholder="Ej. Muelle 3 / Sala">
          </div>
        </div>
      </div>

      <!-- 02 · Contexto -->
      <div>
        <div class="aso-step"><span class="n">02</span> Contexto del turno</div>
        <div class="aso-grid">
          <div class="aso-field">
            <label>Turno</label>
            <div class="aso-turno-toggle" id="im-turno">
              <button type="button" data-t="dia">☀ Día</button>
              <button type="button" data-t="noche">🌙 Noche</button>
            </div>
          </div>
          <div class="aso-field">
            <label>Zona de trabajo</label>
            <select id="im-zona"><option value="">Selecciona…</option></select>
          </div>
          <div class="aso-field">
            <label>Fecha</label>
            <input type="date" id="im-fecha">
          </div>
          <div class="aso-field">
            <label>Hora</label>
            <input type="time" id="im-hora">
          </div>
        </div>
      </div>

      <!-- 03 · Participantes -->
      <div>
        <div class="aso-step"><span class="n">03</span> Participantes (tallys)</div>
        <div class="aso-picker">
          <div class="aso-picker-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="im-part-search" placeholder="Buscar tally por nombre, código o DNI…">
          </div>
          <div class="aso-picker-list" id="im-part-list"></div>
        </div>
        <div class="aso-selected" id="im-selected" style="margin-top:10px"></div>
      </div>

      <!-- 04 · Observaciones -->
      <div class="aso-field full">
        <div class="aso-step" style="margin-bottom:6px"><span class="n">04</span> Observaciones (opcional)</div>
        <textarea id="im-obs" maxlength="1000" placeholder="Notas adicionales de la reunión…"></textarea>
      </div>

      <!-- 05 · Evidencias -->
      <div>
        <div class="aso-step"><span class="n">05</span> Evidencias (opcional)</div>
        <input type="file" id="im-ev-input" multiple hidden
               accept="<?= htmlspecialchars(sg_accept_attr(), ENT_QUOTES) ?>">
        <div class="aso-ev-drop" id="im-ev-drop">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <span id="im-ev-txt">Subir foto, video o PDF</span>
        </div>
        <div class="aso-hint" style="text-align:center;margin-top:5px">Máx. <?= SG_MAX_ARCHIVOS ?> archivos · menos de 4 MB cada uno · se guardan en Google Drive</div>
        <div class="aso-selected" id="im-ev-list" style="margin-top:8px"></div>
      </div>
    </div>

    <div class="aso-modal-foot">
      <span class="aso-hint" id="im-foot-hint">Selecciona al menos un participante.</span>
      <div style="display:flex;gap:10px">
        <button class="aso-btn" id="im-cancel">Cancelar</button>
        <button class="aso-btn primary" id="im-save">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════ MODAL VER ══════════ -->
<div class="aso-modal-back" id="asoViewBack">
  <div class="aso-modal">
    <div class="aso-modal-head">
      <div>
        <h2 id="asoViewTitle">Asistencia</h2>
        <div class="sub" id="asoViewSub">—</div>
      </div>
      <button class="aso-modal-close" id="asoViewClose">×</button>
    </div>
    <div class="aso-modal-body" id="asoViewBody"></div>
    <div class="aso-modal-foot">
      <button class="aso-btn danger" id="asoViewDelete">Eliminar</button>
      <div style="display:flex;gap:10px">
        <button class="aso-btn" id="asoViewCloseBtn">Cerrar</button>
        <button class="aso-btn" id="asoViewEdit">Editar</button>
        <button class="aso-btn primary" id="asoViewPdf">Exportar PDF</button>
      </div>
    </div>
  </div>
</div>

<div class="aso-toast" id="asoToast"></div>

<script>
(function () {
  'use strict';
  const $ = id => document.getElementById(id);
  const BASE = '..';

  const TIPOS   = <?= json_encode($JS_TIPOS, JSON_UNESCAPED_UNICODE) ?>;   // {clave:label}
  const TURNOS  = <?= json_encode($JS_TURNOS, JSON_UNESCAPED_UNICODE) ?>;
  const ZONAS   = <?= json_encode($zona_ubicaciones, JSON_UNESCAPED_UNICODE) ?>;
  const LOGO    = <?= json_encode($LOGO_B64) ?>;
  const USUARIO = <?= json_encode($COORDINADOR) ?>;   // nombre de la sesión → capacitador por defecto
  const MAX_EV  = <?= SG_MAX_ARCHIVOS ?>;
  const MAX_BYTES = <?= SG_MAX_BYTES ?>;

  let asistencias = [];
  let colaboradores = [];
  let turnoActual = null;   // { turnoId, jornada:'D'|'N', fecha } del turno vigente
  let query = '', filtro = 'todos', filtroPunt = 'todos';

  // ─── Puntualidad de la charla pre-operativa ───
  // Solo las ventanas definidas se clasifican como Off time. Una marcación
  // posterior vuelve a contar como On time para el reporte operativo.
  const VENTANA_OFF_TIME = { dia: ['06:45', '07:30'], noche: ['18:45', '19:30'] };

  // 'ontime' | 'offtime' | null (sin hora registrada o turno desconocido).
  function puntualidad(a) {
    const ventana = VENTANA_OFF_TIME[a.turno];
    const hora = fmtHora(a.hora);            // "HH:MM"
    if (!ventana || !hora) return null;
    return hora >= ventana[0] && hora <= ventana[1] ? 'offtime' : 'ontime';
  }
  function puntBadge(a) {
    const p = puntualidad(a);
    if (!p) return '';
    return p === 'ontime'
      ? '<span class="aso-punt on"><span class="dot"></span>On time</span>'
      : '<span class="aso-punt off"><span class="dot"></span>Off time</span>';
  }

  // Estado del formulario
  let seleccion = new Map();   // colaborador_id -> { id, nombre, dni, cargo, estado: 'asistio'|'falta'|'tardanza' }
  let evidencias = [];         // metadatos ya subidos
  let turnoSel = 'dia';
  let tipoSel = 'charla_preoperativa';

  const ESTADOS = { asistio: 'A', falta: 'F', tardanza: 'T' };
  const ESTADO_LABEL = { asistio: 'Asistió', falta: 'Falta', tardanza: 'Tardanza' };

  function toast(msg, kind) {
    const t = $('asoToast'); t.textContent = msg; t.className = 'aso-toast show' + (kind === 'error' ? ' error' : '');
    setTimeout(() => t.classList.remove('show'), 3000);
  }
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  }
  function pad(n){ return String(n).padStart(2,'0'); }
  function hoyLocal(){ const d=new Date(); return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`; }
  function horaLocal(){ const d=new Date(); return `${pad(d.getHours())}:${pad(d.getMinutes())}`; }
  function fmtFecha(f){ if(!f) return '—'; const p=String(f).split('-'); return p.length===3 ? `${p[2]}/${p[1]}/${p[0]}` : f; }
  function fmtHora(h){ return h ? String(h).slice(0,5) : ''; }
  function fmtPeso(b){ return b<1048576 ? (b/1024).toFixed(0)+' KB' : (b/1048576).toFixed(1)+' MB'; }

  // Tarjeta de evidencia: miniatura para imágenes, ícono para video/PDF. Abre en Drive.
  function evCard(e) {
    const mime = e.mime || '';
    const isImg = /^image\//.test(mime), isVid = /^video\//.test(mime);
    const icon = isVid
      ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M10 9l5 3-5 3z"/></svg>'
      : (mime === 'application/pdf'
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>');
    // Para imágenes en Drive, la miniatura pública; si no carga, cae al ícono.
    const thumb = (isImg && e.drive_file_id)
      ? `<img src="https://drive.google.com/thumbnail?id=${esc(e.drive_file_id)}&sz=w320" alt=""
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
         <span style="display:none;align-items:center;justify-content:center;width:100%;height:100%">${icon}</span>`
      : icon;
    const pend = e.estado !== 'subido';
    const inner = `<div class="thumb">${thumb}</div>
      <div class="cap"><div class="nm">${esc(e.nombre_archivo)}</div>
        <div class="mt">${fmtPeso(e.peso_bytes)}${pend ? ' · pendiente' : ' · abrir ↗'}</div></div>`;
    return (!pend && e.drive_url)
      ? `<a class="aso-ev-card" href="${esc(e.drive_url)}" target="_blank" rel="noopener">${inner}</a>`
      : `<div class="aso-ev-card pend">${inner}</div>`;
  }

  // ─── Carga ───
  async function cargar() {
    try {
      // El turno vigente se resuelve primero: su id permite marcar en el listado
      // de participantes a quienes trabajaron un turno adyacente (no seleccionables).
      const rt = await fetch(`${BASE}/api/get_turno_actual.php`, { cache:'no-store' })
        .then(r=>r.json()).catch(()=>({}));
      if (rt && rt.success) {
        turnoActual = { turnoId: rt.turnoId, jornada: rt.turnoJornada, fecha: rt.turnoFecha };
      }
      const colabUrl = `${BASE}/api/get_colaboradores.php` +
        (turnoActual && turnoActual.turnoId ? `?turnoId=${turnoActual.turnoId}` : '');
      const [ra, rc] = await Promise.all([
        fetch(`${BASE}/api/get_asistencias.php`, { cache:'no-store' }).then(r=>r.json()),
        fetch(colabUrl, { cache:'no-store' }).then(r=>r.json()),
      ]);
      if (ra.success) asistencias = ra.data || [];
      if (rc.success) colaboradores = (rc.data || []).filter(c => c.activo);
      renderKpis(); render();
    } catch (e) { toast('Error al cargar datos', 'error'); }
  }

  function renderKpis() {
    $('kpiTotal').textContent = asistencias.length;
    $('kpiPart').textContent  = asistencias.reduce((n,a)=> n + (a.participantes?.length||0), 0);
    const mes = hoyLocal().slice(0,7), hoy = hoyLocal();
    $('kpiMes').textContent = asistencias.filter(a => String(a.fecha).slice(0,7) === mes).length;
    $('kpiHoy').textContent = asistencias.filter(a => String(a.fecha) === hoy).length;
  }

  function listaVisible() {
    const q = query.trim().toLowerCase();
    return asistencias.filter(a => {
      if (filtro !== 'todos' && a.turno !== filtro) return false;
      if (filtroPunt !== 'todos' && puntualidad(a) !== filtroPunt) return false;
      if (!q) return true;
      return [a.tema, a.capacitador, a.zona_trabajo, a.lugar, TIPOS[a.tipo_reunion]]
        .some(v => String(v ?? '').toLowerCase().includes(q));
    });
  }

  function render() {
    const list = listaVisible();
    const tb = $('asoTbody');
    if (!list.length) { tb.innerHTML = `<tr><td colspan="9" class="aso-empty">Sin registros.</td></tr>`; return; }
    tb.innerHTML = list.map(a => `
      <tr>
        <td><div class="aso-tema">${esc(a.tema)}</div>${a.lugar ? `<div class="aso-sub">${esc(a.lugar)}</div>` : ''}</td>
        <td><span class="aso-chip">${esc(TIPOS[a.tipo_reunion] || a.tipo_reunion)}</span></td>
        <td>${esc(a.capacitador)}</td>
        <td><span class="aso-chip ${a.turno==='noche'?'turno-noche':''}">${esc(TURNOS[a.turno] || a.turno)}</span></td>
        <td>${fmtFecha(a.fecha)}${a.hora ? ' · '+fmtHora(a.hora) : ''}</td>
        <td>${puntBadge(a) || '<span class="aso-sub">—</span>'}</td>
        <td>${esc(a.zona_trabajo || '—')}</td>
        <td style="text-align:center"><span class="aso-count">${a.participantes?.length || 0}</span></td>
        <td><div class="aso-act">
          <button data-act="view" data-id="${a.id}">Ver</button>
          <button data-act="edit" data-id="${a.id}">Editar</button>
          <button class="danger" data-act="del" data-id="${a.id}">Eliminar</button>
        </div></td>
      </tr>`).join('');
  }

  $('asoTbody').addEventListener('click', e => {
    const b = e.target.closest('button[data-act]'); if (!b) return;
    const id = Number(b.dataset.id);
    if (b.dataset.act === 'view') openView(id);
    if (b.dataset.act === 'edit') openEdit(id);
    if (b.dataset.act === 'del')  eliminar(id);
  });
  $('asoSearch').addEventListener('input', e => { query = e.target.value; render(); });
  $('asoFilter').addEventListener('click', e => {
    const b = e.target.closest('button[data-f]'); if (!b) return;
    filtro = b.dataset.f;
    $('asoFilter').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
    render();
  });
  $('asoFilterPunt').addEventListener('click', e => {
    const b = e.target.closest('button[data-p]'); if (!b) return;
    filtroPunt = b.dataset.p;
    $('asoFilterPunt').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
    render();
  });

  // ─── Modal registro ───
  function buildTipos() {
    $('im-tipos').innerHTML = Object.entries(TIPOS).map(([k,v]) =>
      `<button type="button" data-tipo="${k}">${esc(v)}</button>`).join('');
  }
  function buildZonas() {
    $('im-zona').innerHTML = '<option value="">Selecciona…</option>' +
      ZONAS.map(z => `<option value="${esc(z)}">${esc(z)}</option>`).join('');
  }
  function setTipo(k){ tipoSel = k; $('im-tipos').querySelectorAll('button').forEach(b => b.classList.toggle('active', b.dataset.tipo === k)); }
  function setTurno(t){ turnoSel = t; $('im-turno').querySelectorAll('button').forEach(b => b.classList.toggle('active', b.dataset.t === t)); }

  $('im-tipos').addEventListener('click', e => { const b = e.target.closest('button'); if (b) setTipo(b.dataset.tipo); });
  $('im-turno').addEventListener('click', e => { const b = e.target.closest('button'); if (b) { setTurno(b.dataset.t); refrescarConflictos(); } });
  $('im-fecha').addEventListener('change', refrescarConflictos);

  // Recalcula qué tallys trabajaron un turno adyacente según el turno (Día/Noche)
  // y la fecha elegidos, y refresca el listado para bloquearlos.
  let conflictoReqId = 0;
  async function refrescarConflictos() {
    const fecha = $('im-fecha').value;
    if (!fecha) return;
    const jornada = turnoSel === 'noche' ? 'N' : 'D';
    const reqId = ++conflictoReqId;
    try {
      const j = await fetch(`${BASE}/api/get_colaboradores.php?fecha=${encodeURIComponent(fecha)}&jornada=${jornada}`,
        { cache:'no-store' }).then(r=>r.json());
      if (reqId !== conflictoReqId) return;         // llegó una respuesta más nueva
      if (j.success) { colaboradores = (j.data || []).filter(c => c.activo); renderPicker(); }
    } catch (e) { /* silencioso: se conserva el listado actual */ }
  }

  function renderPicker() {
    const q = ($('im-part-search').value || '').trim().toLowerCase();
    const list = colaboradores.filter(c => !q ||
      [c.nombre, c.codigo, c.dni].some(v => String(v ?? '').toLowerCase().includes(q))).slice(0, 60);
    $('im-part-list').innerHTML = list.map(c => {
      const on = seleccion.has(Number(c.id));
      // Turno adyacente: no seleccionable (salvo que ya venga seleccionado de una edición).
      const blocked = !!c.conflicto && !on;
      return `<label class="aso-opt ${on?'sel':''} ${blocked?'blocked':''}"
          title="${blocked?'Ya trabajó el turno adyacente · no puede seleccionarse':''}">
        <input type="checkbox" data-cid="${c.id}" ${on?'checked':''} ${blocked?'disabled':''}>
        <div style="flex:1;min-width:0">
          <div class="nm">${esc(c.nombre)}</div>
          <div class="mt">${esc(c.funcion_principal || '')}${c.dni ? ' · DNI '+esc(c.dni) : ''}${c.codigo ? ' · '+esc(c.codigo) : ''}</div>
        </div>
        ${blocked?'<span class="aso-part-conflict">Turno adyacente</span>':''}
      </label>`;
    }).join('') || `<div class="aso-empty" style="padding:18px">Sin resultados.</div>`;
  }
  $('im-part-search').addEventListener('input', renderPicker);
  $('im-part-list').addEventListener('change', e => {
    const cb = e.target.closest('input[data-cid]'); if (!cb) return;
    const cid = Number(cb.dataset.cid);
    const c = colaboradores.find(x => Number(x.id) === cid);
    if (cb.checked && c && c.conflicto && !seleccion.has(cid)) {
      // No se puede sumar a un tally que trabajó el turno adyacente.
      cb.checked = false;
      toast('Trabajó el turno adyacente · no se puede seleccionar', 'error');
      return;
    }
    if (cb.checked && c) {
      seleccion.set(cid, { id: cid, nombre: c.nombre, dni: c.dni || '', cargo: c.funcion_principal || '', estado: 'asistio' });
    } else {
      seleccion.delete(cid);
    }
    renderSelected(); renderPicker();
  });

  function renderSelected() {
    const arr = [...seleccion.values()];
    const cont = $('im-selected');
    if (!arr.length) { cont.innerHTML = `<div class="aso-hint">Aún no hay participantes seleccionados.</div>`; updateFootHint(); return; }
    cont.innerHTML = `<div class="aso-selhead">Seleccionados <span class="aso-count">${arr.length}</span></div>` +
      arr.map(p => `
        <div class="aso-part">
          <div class="info"><div class="nm">${esc(p.nombre)}</div><div class="mt">${esc(p.cargo)}${p.dni?' · DNI '+esc(p.dni):''}</div></div>
          <div class="aso-estado-toggle" data-part="${p.id}">
            ${Object.entries(ESTADOS).map(([k,letra]) =>
              `<button type="button" data-e="${k}" class="${p.estado===k?'on':''}" title="${ESTADO_LABEL[k]}">${letra}</button>`).join('')}
          </div>
          <button class="rm" data-rm="${p.id}">Quitar</button>
        </div>`).join('');
    updateFootHint();
  }
  function updateFootHint() {
    const n = seleccion.size;
    if (n === 0) { $('im-foot-hint').textContent = 'Selecciona al menos un participante.'; return; }
    const faltas = [...seleccion.values()].filter(p => p.estado === 'falta').length;
    const tardanzas = [...seleccion.values()].filter(p => p.estado === 'tardanza').length;
    let msg = `${n} participante(s)`;
    if (faltas) msg += ` · ${faltas} falta(s)`;
    if (tardanzas) msg += ` · ${tardanzas} tardanza(s)`;
    $('im-foot-hint').textContent = msg;
  }
  $('im-selected').addEventListener('click', e => {
    const eb = e.target.closest('button[data-e]');
    const r  = e.target.closest('button[data-rm]');
    if (eb) {
      const cid = Number(eb.closest('[data-part]').dataset.part);
      const p = seleccion.get(cid);
      if (p) { p.estado = eb.dataset.e; renderSelected(); }
    }
    if (r) { seleccion.delete(Number(r.dataset.rm)); renderSelected(); renderPicker(); }
  });


  // ─── Evidencias ───
  $('im-ev-drop').addEventListener('click', () => { if (evidencias.length < MAX_EV) $('im-ev-input').click(); });
  $('im-ev-input').addEventListener('change', async () => {
    for (const f of $('im-ev-input').files) {
      if (evidencias.length >= MAX_EV) { toast(`Máximo ${MAX_EV} archivos`, 'error'); break; }
      if (f.size > MAX_BYTES) { toast(`"${f.name}" supera los 4 MB`, 'error'); continue; }
      await subirEvidencia(f);
    }
    $('im-ev-input').value = '';
  });
  async function subirEvidencia(file) {
    $('im-ev-txt').textContent = 'Subiendo…';
    try {
      const fd = new FormData(); fd.append('file', file, file.name);
      const r = await fetch(`${BASE}/api/upload_asistencia_file.php`, { method:'POST', body: fd });
      const j = await r.json();
      if (!j.success) { toast(j.error || 'No se pudo subir', 'error'); return; }
      evidencias.push(j);
      if (j.aviso) toast(j.aviso, 'error');
      renderEvidencias();
    } catch (e) { toast('Error de red al subir', 'error'); }
    finally { $('im-ev-txt').textContent = evidencias.length >= MAX_EV ? `Máximo ${MAX_EV} alcanzado` : 'Subir foto, video o PDF'; }
  }
  function renderEvidencias() {
    const cont = $('im-ev-list');
    cont.innerHTML = evidencias.map((e,i) => `
      <div class="aso-ev-item ${e.estado!=='subido'?'pend':''}">
        <div style="flex:1;min-width:0">
          <div class="nm">${esc(e.nombre)}</div>
          <div class="mt">${fmtPeso(e.peso_bytes)}${e.estado==='subido'?' · en Drive':' · pendiente'}</div>
        </div>
        <button class="rm" data-ev="${i}">×</button>
      </div>`).join('');
    $('im-ev-drop').classList.toggle('lleno', evidencias.length >= MAX_EV);
  }
  $('im-ev-list').addEventListener('click', e => {
    const b = e.target.closest('button[data-ev]'); if (!b) return;
    evidencias.splice(Number(b.dataset.ev), 1); renderEvidencias();
  });

  // ─── Abrir / cerrar modal ───
  function resetForm() {
    seleccion = new Map(); evidencias = [];
    $('im-id').value = ''; $('im-tema').value = '';
    $('im-capacitador').value = USUARIO;          // usuario en sesión por defecto
    $('im-lugar').value = 'OFICINA - SALA';        // lugar por defecto
    $('im-hora').value = horaLocal();              // hora actual automática
    $('im-obs').value = '';
    $('im-part-search').value = '';
    // Turno y fecha se marcan automáticamente según el turno vigente del usuario,
    // para evitar registrar la charla en el turno equivocado.
    const turnoDef = turnoActual && turnoActual.jornada === 'N' ? 'noche' : 'dia';
    $('im-fecha').value = (turnoActual && turnoActual.fecha) ? turnoActual.fecha : hoyLocal();
    setTipo('charla_preoperativa'); setTurno(turnoDef); $('im-zona').value = '';
    renderPicker(); renderSelected(); renderEvidencias();
    refrescarConflictos();
  }
  function openNew() {
    $('asoModalTitle').textContent = 'Registrar asistencia pre-operativa';
    resetForm();
    $('asoModalBack').classList.add('open');
  }
  async function openEdit(id) {
    const j = await fetch(`${BASE}/api/get_asistencias.php?id=${id}`, { cache:'no-store' }).then(r=>r.json());
    const a = j.success && j.data && j.data[0];
    if (!a) { toast('No se pudo cargar', 'error'); return; }
    $('asoModalTitle').textContent = 'Editar asistencia';
    resetForm();
    $('im-id').value = a.id; $('im-tema').value = a.tema; $('im-capacitador').value = a.capacitador;
    $('im-lugar').value = a.lugar || ''; $('im-hora').value = fmtHora(a.hora); $('im-obs').value = a.observaciones || '';
    $('im-fecha').value = a.fecha; setTipo(a.tipo_reunion); setTurno(a.turno); $('im-zona').value = a.zona_trabajo || '';
    refrescarConflictos();
    (a.participantes || []).forEach(p => {
      if (p.colaborador_id) seleccion.set(Number(p.colaborador_id), {
        id: Number(p.colaborador_id), nombre: p.colaborador_nombre, dni: p.colaborador_dni || '',
        cargo: p.colaborador_cargo || '', estado: p.estado || 'asistio' });
    });
    evidencias = (a.evidencias || []).map(e => ({
      nombre: e.nombre_archivo, mime: e.mime, peso_bytes: e.peso_bytes,
      drive_file_id: e.drive_file_id || null, drive_url: e.drive_url || null, ruta_local: null, estado: e.estado }));
    renderPicker(); renderSelected(); renderEvidencias();
    $('asoModalBack').classList.add('open');
  }
  function closeModal(){ $('asoModalBack').classList.remove('open'); }
  $('btnNew').addEventListener('click', openNew);
  $('asoModalClose').addEventListener('click', closeModal);
  $('im-cancel').addEventListener('click', closeModal);

  $('im-save').addEventListener('click', async () => {
    const payload = {
      id: Number($('im-id').value) || 0,
      tema: $('im-tema').value.trim(),
      tipo_reunion: tipoSel,
      lugar: $('im-lugar').value.trim(),
      capacitador: $('im-capacitador').value.trim(),
      turno: turnoSel,
      fecha: $('im-fecha').value,
      hora: $('im-hora').value,
      zona_trabajo: $('im-zona').value,
      observaciones: $('im-obs').value.trim(),
      participantes: [...seleccion.values()].map(p => ({ colaborador_id: p.id, estado: p.estado || 'asistio' })),
      evidencias: evidencias,
    };
    if (!payload.tema) { toast('Indica el tema o actividad', 'error'); return; }
    if (!payload.capacitador) { toast('Indica el capacitador', 'error'); return; }
    if (!payload.participantes.length) { toast('Selecciona al menos un participante', 'error'); return; }

    $('im-save').disabled = true;
    try {
      const r = await fetch(`${BASE}/api/save_asistencia.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
      const j = await r.json();
      if (!j.success) { toast(j.error || 'No se pudo guardar', 'error'); return; }
      const ts = j.turnoSync || {};
      let msg = 'Asistencia guardada';
      if (ts.agregados) msg += ` · ${ts.agregados} agregado(s) al turno`;
      if (ts.omitidos)  msg += ` · ${ts.omitidos} no agregado(s) (turno adyacente)`;
      toast(msg);
      closeModal();
      await cargar();
    } catch (e) { toast('Error de red', 'error'); }
    finally { $('im-save').disabled = false; }
  });

  // ─── Ver ───
  let viewId = null;
  function openView(id) {
    const a = asistencias.find(x => x.id === id); if (!a) return;
    viewId = id;
    $('asoViewTitle').textContent = a.tema;
    $('asoViewSub').textContent = `${TIPOS[a.tipo_reunion]||a.tipo_reunion} · ${fmtFecha(a.fecha)}${a.hora?' '+fmtHora(a.hora):''}`;
    const parts = a.participantes || [];
    $('asoViewBody').innerHTML = `
      <div class="aso-view-grid">
        <div class="aso-view-field"><label>Capacitador</label><div>${esc(a.capacitador)}</div></div>
        <div class="aso-view-field"><label>Turno</label><div>${esc(TURNOS[a.turno]||a.turno)}</div></div>
        <div class="aso-view-field"><label>Hora / Puntualidad</label><div style="display:flex;align-items:center;gap:8px">${a.hora ? esc(fmtHora(a.hora)) : '—'}${puntBadge(a)}</div></div>
        <div class="aso-view-field"><label>Lugar</label><div>${esc(a.lugar||'—')}</div></div>
        <div class="aso-view-field"><label>Zona</label><div>${esc(a.zona_trabajo||'—')}</div></div>
        <div class="aso-view-field"><label>Coordinador</label><div>${esc(a.coordinador||'—')}</div></div>
        <div class="aso-view-field"><label>Registrado</label><div>${esc(a.created_at||'—')}</div></div>
      </div>
      ${a.observaciones ? `<div class="aso-view-field"><label>Observaciones</label><div>${esc(a.observaciones)}</div></div>` : ''}
      <div>
        <div class="aso-step"><span class="n">👥</span> Participantes (${parts.length})</div>
        ${parts.map((p,i) => `
          <div class="aso-view-part">
            <div style="width:22px;color:#94a3b8;font-size:12px">${i+1}</div>
            <div style="flex:1"><div class="nm" style="font-weight:600">${esc(p.colaborador_nombre)}</div>
              <div class="mt" style="font-size:11px;color:#6b7280">${esc(p.colaborador_cargo||'')}${p.colaborador_dni?' · DNI '+esc(p.colaborador_dni):''}</div></div>
            <span class="aso-estado-badge ${p.estado||'asistio'}">${ESTADO_LABEL[p.estado] || 'Asistió'}</span>
          </div>`).join('')}
      </div>
      ${(a.evidencias||[]).length ? `
      <div>
        <div class="aso-step"><span class="n">📎</span> Evidencias (${a.evidencias.length})</div>
        <div class="aso-ev-gallery">${a.evidencias.map(evCard).join('')}</div>
      </div>` : ''}`;
    $('asoViewBack').classList.add('open');
  }
  function closeView(){ $('asoViewBack').classList.remove('open'); viewId = null; }
  $('asoViewClose').addEventListener('click', closeView);
  $('asoViewCloseBtn').addEventListener('click', closeView);
  $('asoViewEdit').addEventListener('click', () => { const id = viewId; closeView(); openEdit(id); });
  $('asoViewDelete').addEventListener('click', () => { const id = viewId; closeView(); eliminar(id); });
  $('asoViewPdf').addEventListener('click', () => exportarPdf(viewId));

  async function eliminar(id) {
    if (!confirm('¿Eliminar esta asistencia? Se borran también sus participantes.')) return;
    try {
      const j = await fetch(`${BASE}/api/delete_asistencia.php`, {
        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id }) }).then(r=>r.json());
      if (!j.success) { toast(j.error || 'No se pudo eliminar', 'error'); return; }
      toast('Asistencia eliminada'); await cargar();
    } catch (e) { toast('Error de red', 'error'); }
  }

  // ─── Exportar PDF (formato Registro de Asistencia) ───
  async function exportarPdf(id) {
    const j = await fetch(`${BASE}/api/get_asistencias.php?id=${id}`, { cache:'no-store' }).then(r=>r.json());
    const a = j.success && j.data && j.data[0];
    if (!a) { toast('No se pudo cargar para exportar', 'error'); return; }

    const tiposChk = Object.entries(TIPOS).map(([k,v]) => {
      const on = String(k) === String(a.tipo_reunion);
      return `<div class="chk"><span class="box ${on?'on':''}">${on?'X':''}</span>${esc(v)}</div>`;
    }).join('');

    const parts = a.participantes || [];
    const filas = parts.map((p,i) => {
      const est = p.estado || 'asistio';
      return `
      <tr>
        <td class="c">${i+1}</td>
        <td>${esc(p.colaborador_nombre)}</td>
        <td class="c">${esc(p.colaborador_dni||'')}</td>
        <td class="c">${fmtFecha(a.fecha)}</td>
        <td class="c">${a.hora?fmtHora(a.hora):''}</td>
        <td>${esc(a.capacitador)}</td>
        <td>${esc(a.tema)}</td>
        <td class="c estado ${est}">${ESTADOS[est] || 'A'}</td>
      </tr>`;
    }).join('');
    const resumen = `Asistieron: ${parts.filter(p=>(p.estado||'asistio')==='asistio').length}`
      + ` · Faltas: ${parts.filter(p=>p.estado==='falta').length}`
      + ` · Tardanzas: ${parts.filter(p=>p.estado==='tardanza').length}`;

    const doc = `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Registro de Asistencia</title>
      <style>
        @page { size:A4 portrait; margin:12mm; }
        *{box-sizing:border-box;} body{font-family:'DM Sans',Arial,sans-serif;color:#111;font-size:10px;margin:0;print-color-adjust:exact;-webkit-print-color-adjust:exact;}
        /* La hoja ocupa exactamente el área imprimible de A4 (297mm menos 24mm de márgenes) para evitar saltos de página y bordes cortados. */
        .hoja{border:1.5px solid #333;display:flex;flex-direction:column;height:268mm;max-height:268mm;overflow:hidden;box-sizing:border-box;}
        .cab{display:flex;border-bottom:1.5px solid #333;flex-shrink:0;}
        .cab .logo{width:150px;border-right:1px solid #333;padding:8px;display:flex;align-items:center;justify-content:center;}
        .cab .logo img{max-width:100%;max-height:52px;}
        .cab .tit{flex:1;border-right:1px solid #333;padding:8px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;font-weight:800;font-size:11px;}
        .cab .rev{width:170px;font-size:9px;}
        .cab .rev div{display:flex;border-bottom:1px solid #333;}
        .cab .rev div:last-child{border-bottom:none;}
        .cab .rev span{padding:3px 5px;} .cab .rev .k{width:78px;border-right:1px solid #333;font-weight:700;}
        .sub{padding:5px 8px;font-weight:800;font-size:11px;border-bottom:1.5px solid #333;letter-spacing:.03em;flex-shrink:0;}
        .meta{border-bottom:1.5px solid #333;flex-shrink:0;}
        .meta .row{display:flex;border-bottom:1px solid #ccc;padding:5px 8px;gap:6px;}
        .meta .k{font-weight:800;width:60px;}
        .tipos{display:grid;grid-template-columns:repeat(4,1fr);column-gap:14px;row-gap:2px;padding:6px 10px;}
        .tipos .chk{display:flex;align-items:center;gap:6px;font-size:9.5px;padding:1px 0;}
        .tipos .box{display:inline-flex;align-items:center;justify-content:center;width:12px;height:12px;border:1.2px solid #333;font-size:10px;font-weight:800;line-height:1;}
        .tipos .box.on{background:#111;color:#fff;}
        table{width:100%;border-collapse:collapse;flex-shrink:0;}
        th{background:#e8e8e8;border:1px solid #333;padding:4px 3px;font-size:8.5px;text-transform:uppercase;}
        td{border:1px solid #333;padding:3px 4px;height:26px;vertical-align:middle;}
        td.c{text-align:center;}
        td.estado{width:46px;font-weight:800;font-size:12px;}
        td.estado.asistio{color:#00875A;} td.estado.falta{color:#dc2626;} td.estado.tardanza{color:#d97706;}
        /* Espaciador que empuja observaciones y firmas al pie de la página. */
        .fill{flex:1 1 auto;min-height:10px;}
        .obs{padding:10px 12px;border-top:1.5px solid #333;font-size:10px;min-height:65px;line-height:1.6;flex-shrink:0;}
        .foot{display:flex;border-top:1.5px solid #333;height:110px;min-height:110px;flex-shrink:0;}
        .foot .foot-col{flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;padding:12px 12px 10px;border-right:1px solid #333;text-align:center;font-size:10px;}
        .foot .foot-col:last-child{border-right:none;}
        .foot .sign-line{width:calc(100% - 44px);max-width:260px;border-top:1.5px solid #333;margin-bottom:6px;flex-shrink:0;}
        .foot .sign-label{width:100%;font-size:10px;line-height:1.3;font-weight:500;color:#111;padding:0 10px;}
      </style></head><body>
      <div class="hoja">
        <div class="cab">
          <div class="logo">${LOGO ? `<img src="${LOGO}">` : 'COSCO'}</div>
          <div class="tit">FORMATO DE REGISTRO DE<br>ASISTENCIA</div>
          <div class="rev">
            <div><span class="k">Revisión</span><span>1</span></div>
            <div><span class="k">Fecha Elab.</span><span>${fmtFecha(a.fecha)}</span></div>
            <div><span class="k">Página</span><span>1 de 1</span></div>
          </div>
        </div>
        <div class="sub">REGISTRO DE ASISTENCIA</div>
        <div class="meta">
          <div class="row"><span class="k">TEMA:</span><span>${esc(a.tema)}</span></div>
          <div class="row"><span class="k">LUGAR:</span><span>${esc(a.lugar||'')}</span></div>
          <div class="row"><span class="k">TURNO:</span><span>${esc(TURNOS[a.turno]||a.turno)}${a.zona_trabajo?' · Zona: '+esc(a.zona_trabajo):''}</span></div>
          <div class="tipos">${tiposChk}</div>
        </div>
        <table>
          <thead><tr>
            <th style="width:26px">N°</th><th>Apellidos y Nombres</th><th style="width:66px">DNI</th>
            <th style="width:58px">Fecha</th><th style="width:44px">Hora</th><th style="width:90px">Capacitador</th>
            <th>Actividad</th><th style="width:46px">A/F/T</th>
          </tr></thead>
          <tbody>${filas}</tbody>
        </table>
        <div class="fill"></div>
        <div class="obs"><b>OBSERVACIONES:</b> ${a.observaciones ? esc(a.observaciones) : ''}<br><b>RESUMEN:</b> ${esc(resumen)} <span style="color:#6b7280">(A = Asistió · F = Falta · T = Tardanza)</span></div>
        <div class="foot">
          <div class="foot-col">
            <div class="sign-line"></div>
            <div class="sign-label">Coordinador<br>${esc(a.coordinador||'')}</div>
          </div>
          <div class="foot-col">
            <div class="sign-line"></div>
            <div class="sign-label">Encargado del Centro de Operaciones</div>
          </div>
        </div>
      </div>
      <script>window.addEventListener('load',function(){window.focus();window.print();});<\/script>
    </body></html>`;

    const w = window.open('', '_blank');
    if (!w) { toast('Permite las ventanas emergentes para exportar', 'error'); return; }
    w.document.write(doc); w.document.close();
    toast(`${(a.participantes||[]).length} participante(s) · elige "Guardar como PDF"`);
  }

  // Cierre por backdrop / Esc
  [['asoModalBack',closeModal],['asoViewBack',closeView]].forEach(([bk,fn]) => {
    $(bk).addEventListener('click', e => { if (e.target === $(bk)) fn(); });
  });
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if ($('asoModalBack').classList.contains('open')) closeModal();
    else if ($('asoViewBack').classList.contains('open')) closeView();
  });

  // ─── Init ───
  buildTipos(); buildZonas();
  cargar();
})();
</script>

</body>
</html>
