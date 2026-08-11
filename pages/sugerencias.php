<?php
require_once('../includes/auth.php');
require_once('../includes/sugerencias_catalogo.php');
require_admin();

$JS_CANALES     = sg_canales();
$CANAL_PUNTUABLE= sg_canal_puntuable();
$PUNTAJE_MIN    = sg_puntaje_min();
$PUNTAJE_MAX    = sg_puntaje_max();
$JS_IMPACTOS    = sg_impactos();
$JS_BANDAS      = sg_viabilidad_bandas();
$JS_DECISIONES  = sg_decisiones();
$JS_ENC_SECCIONES = sg_encuesta_secciones();
$FORM_URL       = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
                 . ($_SERVER['HTTP_HOST'] ?? 'portallyman.online') . '/sugerencias.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sugerencias Tallyman · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <link rel="stylesheet" href="../css/estiba.css">

  <style>
    .sg-wrap, .sg-modal-back {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0, 135, 90, 0.18); --co-line-bold:rgba(0, 135, 90, 0.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      --mono: ui-monospace, Consolas, monospace;
    }
    .sg-wrap {
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .sg-modal-back { font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink); }
    .sg-wrap *, .sg-wrap *::before, .sg-wrap *::after { box-sizing:border-box; }

    .sg-hero {
      background: linear-gradient(135deg, #005c3d 0%, #00875A 100%) !important;
      color:#fff; border-radius:20px; padding:22px 28px;
      display:flex; align-items:center; justify-content:space-between; gap:18px;
      border: 1px solid rgba(0, 135, 90, 0.2) !important;
      box-shadow: 0 8px 32px rgba(0, 135, 90, 0.08) !important;
    }
    .sg-hero h1 { margin:6px 0 4px; font-size:22px; font-weight:700; letter-spacing:-.01em; }
    .sg-hero p  { margin:0; color:rgba(255,255,255,.85); font-size:13px; max-width:560px; }
    .sg-hero .tag {
      display:inline-flex; align-items:center; gap:8px;
      padding:5px 11px; border-radius:999px;
      background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18);
      font-size:11px; font-weight:600; letter-spacing:.12em; text-transform:uppercase;
      color: #ffffff !important;
    }

    .sg-btn {
      display:inline-flex; align-items:center; gap:7px;
      padding:9px 16px; border-radius:10px; border:1px solid rgba(0, 135, 90, 0.3);
      background:#fff; cursor:pointer; text-decoration:none;
      font-family:inherit; font-size:13px; font-weight:600; color:#00875A;
      transition:all .15s;
    }
    .sg-btn:hover { border-color:var(--co-navy-700); color:var(--co-navy); background: rgba(0, 135, 90, 0.05); }
    .sg-btn.ghost-light { background:rgba(255,255,255,.15); color:#fff; border-color:rgba(255,255,255,.25); }
    .sg-btn.ghost-light:hover { background:rgba(255,255,255,.25); }
    body .sg-btn.primary, .sg-btn.primary {
      background: linear-gradient(135deg, #00875A 0%, #005c3d 100%) !important;
      color: #fff !important; border: none !important; font-weight: 700 !important;
      box-shadow: 0 4px 18px rgba(0, 135, 90, 0.2) !important; letter-spacing: 0.02em;
      padding: 11px 20px; border-radius: 12px;
    }
    body .sg-btn.primary:hover, .sg-btn.primary:hover {
      transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0, 135, 90, 0.35) !important; filter: brightness(1.08);
    }
    .sg-btn svg { width:14px; height:14px; }

    .sg-kpis { display:flex; gap:10px; flex-wrap:wrap; }
    .sg-kpi {
      flex:1; min-width:120px;
      background:#fff; border:1px solid var(--co-line); border-radius:14px;
      padding:14px 18px;
      box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;
    }
    .sg-kpi .lbl { font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--co-mute); }
    .sg-kpi:nth-child(1) .val { color:var(--co-navy-700); }
    .sg-kpi:nth-child(2) .val { color:#7c3aed; }
    .sg-kpi:nth-child(3) .val { color:#d97706; }
    .sg-kpi:nth-child(4) .val { color:#12B76A; }
    .sg-kpi:nth-child(5) .val { color:#3b82f6; }
    .sg-kpi .val { font-size:22px; font-weight:700; margin-top:4px; }

    .sg-kpi-scope {
      display:none; align-items:center; gap:8px;
      background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px;
      padding:8px 12px; font-size:12px; color:#92400E;
    }
    .sg-kpi-scope.on { display:flex; }
    .sg-kpi-scope button {
      margin-left:auto; border:0; background:transparent; cursor:pointer;
      font:inherit; font-size:12px; font-weight:700; color:#92400E; text-decoration:underline;
    }

    .sg-toolbar {
      display:flex; gap:10px; align-items:center; flex-wrap:wrap;
      background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:10px 12px;
      box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;
    }
    .sg-search {
      flex:1; min-width:220px; display:flex; align-items:center; gap:8px;
      background:var(--co-deck); border:1px solid rgba(0, 135, 90, 0.15); border-radius:10px; padding:8px 12px;
    }
    .sg-search:focus-within { border-color:var(--co-navy-700); background:#fff; box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.15); }
    .sg-search input { flex:1; border:0; outline:0; background:transparent; font:inherit; font-size:13.5px; color:var(--co-ink); }
    .sg-search input::placeholder { color: var(--co-faint); opacity: 0.9; }
    .sg-search svg { width:15px; height:15px; color:var(--co-mute); }
    .sg-filter { display:flex; gap:4px; background:#f3f4f6; border-radius:10px; padding:3px; flex-wrap:wrap; border: 1px solid #e5e7eb; }
    .sg-filter button {
      padding:6px 12px; border:0; background:transparent; border-radius:7px;
      font:inherit; font-size:12px; font-weight:600; color:var(--co-mute); cursor:pointer;
    }
    .sg-filter button.active { background:#fff; color:var(--co-navy-700); box-shadow:0 1px 3px rgba(0,0,0,.06); border: 1px solid rgba(0, 135, 90, 0.2); }

    .sg-select-wrap { display:flex; align-items:center; gap:6px; background:#fff;
      border:1px solid rgba(0, 135, 90, 0.15); border-radius:10px; padding:4px 8px; }
    .sg-select-wrap.on { border-color:var(--co-navy-700); box-shadow:0 0 0 2px rgba(0, 135, 90, 0.12); }
    .sg-select-wrap label { font-size:10px; font-weight:700; letter-spacing:.08em;
      text-transform:uppercase; color:var(--co-mute); }
    .sg-select-wrap select { border:0; outline:0; background:transparent; font:inherit;
      font-size:12.5px; font-weight:600; color:var(--co-ink); cursor:pointer; max-width:170px; }

    .sg-table-wrap { background:#fff; border:1px solid var(--co-line); border-radius:14px; overflow:auto; box-shadow: 0 4px 16px rgba(0,0,0,.02) !important; }
    .sg-table { width:100%; border-collapse:collapse; font-size:13px; }
    .sg-table thead tr { background: rgba(0, 135, 90, 0.04) !important; border-bottom:1px solid var(--co-line); }
    .sg-table th {
      padding:11px 14px; text-align:left; white-space:nowrap;
      font-size:10.5px; letter-spacing:.1em; text-transform:uppercase;
      color:var(--co-navy); font-weight:700;
    }
    .sg-table tbody tr { border-bottom:1px solid rgba(0, 135, 90, 0.06); transition:background .12s; }
    .sg-table tbody tr:last-child { border-bottom:0; }
    .sg-table tbody tr:hover { background: rgba(0, 135, 90, 0.02); }
    .sg-table td { padding:11px 14px; vertical-align:middle; color: var(--co-ink) !important; }
    .sg-name { font-weight:600; color:var(--co-ink); }
    .sg-sub  { font-size:11px; color:var(--co-faint); }
    .sg-detalle-preview { max-width:340px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--co-mute); }

    .sg-badge {
      display:inline-flex; align-items:center; gap:5px;
      padding:3px 9px; border-radius:999px;
      font-size:10.5px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
      color:#fff;
    }
    .sg-badge .dot { width:6px; height:6px; border-radius:50%; background:rgba(255,255,255,.85); }
    .sg-anon-chip {
      display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:6px;
      font-size:11px; font-weight:600; background:#EFF6FF; color:#1d4ed8;
    }
    .sg-score-chip {
      display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px;
      font-size:12px; font-weight:800; background:rgba(0, 135, 90, 0.08); color:var(--co-navy-700);
    }

    .sg-coord-chip { display:inline-flex; align-items:center; gap:6px; max-width:170px; }
    .sg-coord-chip .ini {
      width:22px; height:22px; flex:0 0 22px; border-radius:50%;
      background:rgba(0, 135, 90, 0.12); color:var(--co-navy-700);
      display:grid; place-items:center; font-size:9.5px; font-weight:800; letter-spacing:.02em;
    }
    .sg-coord-chip .nm { font-size:12.5px; font-weight:600; color:var(--co-ink);
      overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .sg-coord-none { font-size:12px; color:var(--co-faint); }
    .sg-coord-na { display:inline-flex; align-items:center; gap:5px; font-size:11.5px;
      font-weight:600; color:#1d4ed8; background:#EFF6FF; padding:3px 8px; border-radius:6px; }

    .sg-calif { display:flex; flex-direction:column; gap:4px; align-items:flex-start; }
    .sg-pill {
      display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:999px;
      font-size:11px; font-weight:700; white-space:nowrap;
    }
    .sg-pill .dot { width:6px; height:6px; border-radius:50%; background:currentColor; flex:0 0 6px; }
    .sg-pill .num { font-weight:800; font-variant-numeric:tabular-nums; }

    .sg-dec {
      display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:8px;
      font-size:10.5px; font-weight:800; letter-spacing:.05em; text-transform:uppercase;
      white-space:nowrap; color:#fff;
    }
    .sg-dec.muted { background:transparent !important; color:var(--co-faint); font-weight:600;
      text-transform:none; letter-spacing:0; padding-left:0; }

    .sg-act-btn {
      padding:5px 10px; border-radius:7px; border:1px solid rgba(0, 135, 90, 0.25);
      background:rgba(0, 135, 90, 0.05); cursor:pointer; font:inherit; font-size:12px; font-weight:600; color:#00875A;
      transition:all .12s;
    }
    .sg-act-btn:hover { border-color:var(--co-navy-700); color:#ffffff; background:#00875A; }
    .sg-act-btn.danger { color: var(--co-red); border-color: rgba(239, 68, 68, 0.25); background: rgba(239, 68, 68, 0.05); }
    .sg-act-btn.danger:hover { border-color:var(--co-red); color:#ffffff; background: var(--co-red); }
    .sg-cell-actions { display:flex; gap:6px; align-items:center; justify-content:flex-end; }

    .sg-modal-back {
      position:fixed; inset:0; background:rgba(0, 0, 0, 0.3);
      display:grid; place-items:center; z-index:995;
      opacity:0; pointer-events:none; transition:opacity .2s;
      backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    }
    .sg-modal-back.open { opacity:1; pointer-events:auto; }
    .sg-modal {
      background:#fff; border-radius:18px; width:560px; max-width:94vw;
      box-shadow: 0 24px 64px rgba(0, 135, 90, 0.12);
      transform:translateY(12px) scale(.97); transition:transform .22s cubic-bezier(.25,.46,.45,.94);
      max-height:92vh; display:flex; flex-direction:column; overflow:hidden;
      border: 1px solid var(--co-line);
    }
    .sg-modal-back.open .sg-modal { transform:translateY(0) scale(1); }
    .sg-modal-head {
      padding:18px 20px 14px; border-bottom:1px solid rgba(0, 135, 90, 0.08);
      display:flex; align-items:center; justify-content:space-between;
    }
    .sg-modal-head h3 { margin:0; font-size:17px; font-weight:700; color: var(--co-ink); }
    .sg-modal-head .sub { font-size:12px; color:var(--co-mute); margin-top:2px; }
    .sg-modal-close {
      width:32px; height:32px; border-radius:8px; border:1px solid #d1d5db;
      background:#fff; cursor:pointer; display:grid; place-items:center; color:var(--co-mute);
    }
    .sg-modal-close:hover { color:var(--co-red); border-color:var(--co-red); }
    .sg-modal-body { padding:18px 20px; display:flex; flex-direction:column; gap:14px; overflow-y:auto; flex:1; background: #ffffff; }
    .sg-modal-foot { padding:14px 20px; border-top:1px solid rgba(0, 135, 90, 0.08); display:flex; justify-content:flex-end; gap:8px; background: #ffffff; }
    .sg-field { display:flex; flex-direction:column; gap:5px; }
    .sg-field label { font-size:11px; font-weight:700; color:#374151; letter-spacing:.05em; text-transform:uppercase; }
    .sg-field input, .sg-field textarea {
      font:inherit; font-size:13.5px; color:#111827;
      background:#ffffff; border:1.5px solid #cbd5e1; border-radius:8px;
      padding:9px 11px; outline:0; transition:border-color .15s, box-shadow .15s;
    }
    .sg-field textarea { resize:vertical; min-height:60px; }
    .sg-field input:focus, .sg-field textarea:focus {
      border-color:#00875A; background:#fff; box-shadow:0 0 0 3px rgba(0, 135, 90, 0.15);
    }
    .sg-view-detalle { background:#f9fafb; border:1px solid var(--co-line); border-radius:10px; padding:12px 14px; font-size:13.5px; color:#374151; line-height:1.6; white-space:pre-wrap; }

    .sg-adj-lista { display:flex; flex-direction:column; gap:6px; }
    .sg-adj-item { display:flex; flex-direction:column; gap:2px; text-decoration:none;
      border:1px solid var(--co-line); border-radius:9px; padding:9px 12px; background:#fff; transition:all .15s; }
    a.sg-adj-item:hover { border-color:#00875A; background:#F0FDF4; }
    .sg-adj-item.pendiente { background:#FFFBEB; border-color:#FDE68A; }
    .sg-adj-nombre { font-size:12.5px; font-weight:600; color:#111827; word-break:break-all; }
    .sg-adj-peso { font-size:11px; color:#6B7280; }
    .sg-adj-item.pendiente .sg-adj-peso { color:#B45309; }
    .sg-puntaje-box { background:var(--co-deck, #f5f8f7); border:1px solid var(--co-line, rgba(0,135,90,.18)); border-radius:12px; padding:14px; display:flex; flex-direction:column; gap:10px; }
    .sg-puntaje-grid { display:grid; grid-template-columns:repeat(10,1fr); gap:5px; }
    .sg-puntaje-grid button {
      height:32px; border:1.5px solid var(--co-line, rgba(0,135,90,.18)); background:#fff; border-radius:7px;
      font:inherit; font-size:12.5px; font-weight:700; color:var(--co-mute, #4b5563); cursor:pointer; transition:all .13s;
    }
    .sg-puntaje-grid button:hover { border-color:#00875A; color:#00875A; }
    .sg-puntaje-grid button.active { background:#00875A; border-color:#00875A; color:#fff; }
    .sg-puntaje-saved { font-size:11.5px; color:var(--co-mute, #4b5563); }
    .sg-wa-box { border:1px solid rgba(0,135,90,.22); border-radius:12px; padding:14px; background:#fff; display:flex; flex-direction:column; gap:10px; }
    .sg-wa-head { display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .sg-wa-title { font-size:13px; font-weight:700; color:#075e54; }
    .sg-wa-meta, .sg-wa-status { font-size:11.5px; color:var(--co-mute); }
    .sg-wa-status.is-disabled { color:#b45309; }
    .sg-wa-box textarea { font:inherit; font-size:13px; border:1.5px solid #cbd5e1; border-radius:8px; padding:9px 11px; min-height:76px; resize:vertical; outline:0; }
    .sg-wa-box textarea:focus { border-color:#00875A; box-shadow:0 0 0 3px rgba(0,135,90,.15); }
    .sg-wa-actions { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }

    .sg-impacto-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; }
    .sg-impacto-grid button {
      height:34px; border:1.5px solid var(--co-line, rgba(0,135,90,.18)); background:#fff; border-radius:8px;
      font:inherit; font-size:12.5px; font-weight:700; color:var(--co-mute, #4b5563); cursor:pointer; transition:all .13s;
    }
    .sg-impacto-grid button:hover { filter:brightness(.97); }
    .sg-impacto-grid button.active { color:#fff; }

    .sg-calif-sub { font-size:11px; font-weight:700; letter-spacing:.05em;
      text-transform:uppercase; color:#374151; margin-top:2px; }
    .sg-banda-live { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; }
    .sg-banda-live .dot { width:8px; height:8px; border-radius:50%; background:currentColor; }

    .sg-dec-preview {
      border-radius:10px; padding:11px 13px; display:flex; flex-direction:column; gap:3px;
      border:1.5px solid transparent; background:#fff;
    }
    .sg-dec-preview .tit { font-size:13px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
    .sg-dec-preview .glosa { font-size:11.5px; color:var(--co-mute, #4b5563); }

    .sg-toast {
      position:fixed; bottom:24px; right:24px; z-index:999;
      background:#111827; color:#fff; padding:12px 18px; border-radius:10px;
      font-size:13px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,.1);
      transform:translateY(120%); opacity:0; transition:all .25s;
      border: 1px solid rgba(255,255,255,0.1);
    }
    .sg-toast.show { transform:translateY(0); opacity:1; }
    .sg-toast.is-error { background:#dc2626; border-color: #ef4444; }

    .content { padding:24px 28px 60px; overflow-y:auto; }

    @media (max-width: 600px) {
      .sg-puntaje-grid { grid-template-columns:repeat(5,1fr); }
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
      <div class="sg-wrap">

        <section class="sg-hero">
          <div>
            <span class="tag">ADMINISTRACIÓN · SUGERENCIAS TALLYMAN</span>
            <h1>Sugerencias Tallyman</h1>
            <p>Buzón de observaciones (anónimas), consultas, solicitudes, propuestas de mejora y encuestas enviadas por los colaboradores desde su DNI.</p>
          </div>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
            <button class="sg-btn ghost-light" id="btnCopyLink" title="Copiar el enlace del formulario público">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
              Copiar enlace del formulario
            </button>
            <button class="sg-btn ghost-light" id="btnExportXlsx" title="Descargar las sugerencias visibles en Excel">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Exportar a Excel
            </button>
            <button class="sg-btn ghost-light" id="btnExportPdf" title="Generar un PDF con las sugerencias visibles">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="9" y1="15" x2="15" y2="15"/><line x1="9" y1="11" x2="13" y2="11"/></svg>
              Exportar a PDF
            </button>
          </div>
        </section>

        <section class="sg-kpis">
          <div class="sg-kpi"><div class="lbl">Total</div><div class="val" id="kpiTotal">0</div></div>
          <div class="sg-kpi"><div class="lbl">Observaciones</div><div class="val" id="kpiObs">0</div></div>
          <div class="sg-kpi"><div class="lbl">Calificación pendiente</div><div class="val" id="kpiSinCalificar">0</div></div>
          <div class="sg-kpi"><div class="lbl">Quick wins</div><div class="val" id="kpiQuickWins">0</div></div>
          <div class="sg-kpi"><div class="lbl">Este mes</div><div class="val" id="kpiMes">0</div></div>
        </section>

        <div class="sg-kpi-scope" id="sgKpiScope">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span>Indicadores del equipo a cargo de <strong id="sgKpiScopeName">—</strong></span>
          <button type="button" id="sgKpiScopeReset">Ver todos</button>
        </div>

        <div class="sg-toolbar">
          <div class="sg-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="sgSearch" type="text" placeholder="Buscar por colaborador, coordinador o detalle…">
          </div>
          <div class="sg-filter" id="sgFilter">
            <button class="active" data-f="todos">Todos</button>
            <?php foreach ($JS_CANALES as $key => $c): ?>
              <button data-f="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($c['label']) ?></button>
            <?php endforeach; ?>
          </div>

          <div class="sg-select-wrap" id="sgCoordWrap">
            <label for="sgFilterCoord">Coordinador</label>
            <select id="sgFilterCoord" title="Acota los indicadores y la tabla al equipo de un coordinador"></select>
          </div>

          <div class="sg-select-wrap" id="sgImpactoWrap">
            <label for="sgFilterImpacto">Impacto</label>
            <select id="sgFilterImpacto" title="Sólo aplica a propuestas de mejora; el resto de canales se oculta">
              <option value="todos">Todos</option>
              <?php foreach ($JS_IMPACTOS as $key => $i): ?>
                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($i['label']) ?></option>
              <?php endforeach; ?>
              <option value="sin">Sin definir</option>
            </select>
          </div>

          <div class="sg-select-wrap" id="sgViabWrap">
            <label for="sgFilterViab">Viabilidad</label>
            <select id="sgFilterViab" title="Sólo aplica a propuestas de mejora; el resto de canales se oculta">
              <option value="todos">Todas</option>
              <?php $desde = (int)$PUNTAJE_MIN; foreach ($JS_BANDAS as $b): ?>
                <option value="<?= htmlspecialchars($b['key']) ?>"><?= htmlspecialchars($b['label']) ?> (<?= $desde ?>-<?= (int)$b['max'] ?>)</option>
                <?php $desde = (int)$b['max'] + 1; ?>
              <?php endforeach; ?>
              <option value="sin">Sin calificar</option>
            </select>
          </div>
        </div>

        <div class="sg-table-wrap">
          <table class="sg-table">
            <thead>
              <tr>
                <th>Canal</th>
                <th>Colaborador</th>
                <th>Coord. a cargo</th>
                <th>Detalle</th>
                <th>Calificación</th>
                <th>Decisión</th>
                <th>Fecha</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody id="sgTbody">
              <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--co-faint)">Cargando…</td></tr>
            </tbody>
          </table>
        </div>

      </div>
    </main>
  </div>
</div>

<div class="sg-modal-back" id="sgViewBack">
  <div class="sg-modal">
    <div class="sg-modal-head">
      <div><h3 id="sgViewTitle">Detalle</h3><div class="sub" id="sgViewSub">—</div></div>
      <button class="sg-modal-close" id="sgViewClose">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="sg-modal-body" id="sgViewBody"></div>
    <div class="sg-modal-foot">
      <button class="sg-btn" id="sgViewCloseBtn">Cerrar</button>
    </div>
  </div>
</div>

<div class="sg-toast" id="sgToast">—</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  const CANALES    = <?= json_encode($JS_CANALES, JSON_UNESCAPED_UNICODE) ?>;
  const PUNTUABLE  = <?= json_encode($CANAL_PUNTUABLE) ?>;
  const P_MIN      = <?= (int)$PUNTAJE_MIN ?>;
  const P_MAX      = <?= (int)$PUNTAJE_MAX ?>;
  const IMPACTOS   = <?= json_encode($JS_IMPACTOS, JSON_UNESCAPED_UNICODE) ?>;
  const BANDAS     = <?= json_encode($JS_BANDAS, JSON_UNESCAPED_UNICODE) ?>;
  const DECISIONES = <?= json_encode($JS_DECISIONES, JSON_UNESCAPED_UNICODE) ?>;
  const ENC_SECCIONES = <?= json_encode($JS_ENC_SECCIONES, JSON_UNESCAPED_UNICODE) ?>;
  const FORM_URL   = <?= json_encode($FORM_URL) ?>;
  const BASE       = '..';

  const CANAL_COLOR = { observacion: '#3b82f6', consulta: '#d97706', solicitud: '#7c3aed', propuesta: '#00875A', encuesta: '#0891b2' };

  function encuestaParse(s) {
    if (s.canal !== 'encuesta') return null;
    try {
      const r = JSON.parse(s.detalle);
      return (r && typeof r === 'object') ? r : null;
    } catch (e) { return null; }
  }
  function encuestaResumenTexto(s) {
    const r = encuestaParse(s);
    if (!r) return s.detalle;
    const partes = Object.entries(r).map(([clave, sec]) => {
      const label = ENC_SECCIONES[clave]?.label || clave;
      const items = sec.items || [];
      const bits = [...items];
      if (sec.otros) bits.push('Otros: ' + sec.otros);
      return bits.length ? `${label} (${bits.length}): ${bits.join(', ')}` : null;
    }).filter(Boolean);
    return partes.join(' · ') || 'Sin marcas';
  }
  function encuestaHtml(s) {
    const r = encuestaParse(s);
    if (!r) return `<div class="sg-view-detalle">${esc(s.detalle)}</div>`;
    const secciones = Object.entries(r).map(([clave, sec]) => {
      const label = ENC_SECCIONES[clave]?.label || clave;
      const items = sec.items || [];
      return `<div style="margin-bottom:10px">
        <div class="sg-calif-sub" style="margin-top:0">${esc(label)}</div>
        ${items.length ? `<ul style="margin:4px 0 0 18px;padding:0;font-size:13px;color:#374151">
            ${items.map(it => `<li>${esc(it)}</li>`).join('')}
          </ul>` : ''}
        ${sec.otros ? `<div style="margin-top:4px;font-size:12.5px;color:var(--co-mute)"><strong>Otros:</strong> ${esc(sec.otros)}</div>` : ''}
      </div>`;
    }).join('');
    return `<div class="sg-view-detalle" style="white-space:normal">${secciones || 'Sin marcas.'}</div>`;
  }
  function detalleTexto(s) { return s.canal === 'encuesta' ? encuestaResumenTexto(s) : s.detalle; }

  let sugerencias  = [];
  let coordinadores = [];
  let query = '';
  let filtro       = 'todos';
  let filtroCoord  = 'todos';
  let filtroImpacto= 'todos';
  let filtroViab   = 'todos';
  let currentViewId = null;

  function banda(viab) {
    if (viab === null || viab === undefined || viab === '') return null;
    return BANDAS.find(b => Number(viab) <= Number(b.max)) || null;
  }

  function decisionKey(s) {
    if (s.canal !== PUNTUABLE) return null;
    const b = banda(s.viabilidad);
    if (!b)              return 'sin_calif';
    if (!s.impacto)      return 'sin_impacto';
    if (b.key === 'no_viable') return 'descartar';
    const viable = b.key === 'viable';
    if (s.impacto === 'alto')   return viable ? 'quick_win' : 'apuesta';
    if (s.impacto === 'medio')  return viable ? 'hacer'     : 'evaluar';
    if (s.impacto === 'minimo') return 'opcional';
    return 'sin_impacto';
  }

  function iniciales(nombre) {
    return String(nombre || '').trim().split(/\s+/).slice(0, 2)
      .map(p => p.charAt(0).toUpperCase()).join('') || '?';
  }

  function toast(msg, type) {
    const t = $('sgToast');
    t.textContent = msg;
    t.className = 'sg-toast show' + (type === 'error' ? ' is-error' : '');
    setTimeout(() => t.classList.remove('show'), 3000);
  }
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g,
      c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  }
  function fmtFechaHora(f) {
    if (!f) return '—';
    const d = new Date(String(f).replace(' ', 'T'));
    if (isNaN(d)) return f;
    const p = n => String(n).padStart(2, '0');
    return `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
  }
  function canalBadge(key) {
    const c = CANALES[key];
    if (!c) return esc(key || '—');
    const color = CANAL_COLOR[key] || '#64748b';
    return `<span class="sg-badge" style="background:${color}"><span class="dot"></span>${esc(c.label)}</span>`;
  }

  function coordCell(s) {
    if (!s.colaborador_id) return `<span class="sg-coord-na">🔒 No aplica</span>`;
    if (!s.coord_cargo_nombre) return `<span class="sg-coord-none">Sin asignar</span>`;
    return `<span class="sg-coord-chip" title="${esc(s.coord_cargo_nombre)}">
              <span class="ini">${esc(iniciales(s.coord_cargo_nombre))}</span>
              <span class="nm">${esc(s.coord_cargo_nombre)}</span>
            </span>`;
  }

  function califCell(s) {
    if (s.canal !== PUNTUABLE) return `<span class="sg-sub">—</span>`;
    const b = banda(s.viabilidad);
    const pViab = b
      ? `<span class="sg-pill" style="color:${b.color};background:${b.color}1A">
           <span class="num">${s.viabilidad}/${P_MAX}</span>${esc(b.label)}</span>`
      : `<span class="sg-sub">Sin calificar</span>`;
    const i = IMPACTOS[s.impacto];
    const pImp = i
      ? `<span class="sg-pill" style="color:${i.color};background:${i.color}1A">
           <span class="dot"></span>${esc(i.label)}</span>`
      : `<span class="sg-sub">Impacto sin definir</span>`;
    return `<div class="sg-calif">${pViab}${pImp}</div>`;
  }

  function decCell(s) {
    const k = decisionKey(s);
    if (!k) return `<span class="sg-sub">—</span>`;
    const d = DECISIONES[k];
    if (!d) return `<span class="sg-sub">—</span>`;
    if (k === 'sin_calif' || k === 'sin_impacto') {
      return `<span class="sg-dec muted" title="${esc(d.glosa)}">${esc(d.label)}</span>`;
    }
    return `<span class="sg-dec" style="background:${d.color}" title="${esc(d.glosa)}">${d.icono} ${esc(d.label)}</span>`;
  }

  function alcanceCoord() {
    if (filtroCoord === 'todos') return sugerencias;
    if (filtroCoord === 'sin')   return sugerencias.filter(s => s.colaborador_id && !s.coord_cargo_id);
    return sugerencias.filter(s => Number(s.coord_cargo_id) === Number(filtroCoord));
  }

  function renderKpis() {
    const base = alcanceCoord();
    $('kpiTotal').textContent = base.length;
    $('kpiObs').textContent = base.filter(s => s.canal === 'observacion').length;
    $('kpiSinCalificar').textContent = base.filter(s =>
      s.canal === PUNTUABLE && (s.viabilidad == null || !s.impacto)).length;
    $('kpiQuickWins').textContent = base.filter(s => decisionKey(s) === 'quick_win').length;
    const now = new Date();
    const ym = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0');
    $('kpiMes').textContent = base.filter(s => String(s.created_at || '').startsWith(ym)).length;

    $('sgKpiScope').classList.toggle('on', filtroCoord !== 'todos');
    if (filtroCoord !== 'todos') {
      const c = coordinadores.find(x => String(x.id) === String(filtroCoord));
      $('sgKpiScopeName').textContent =
        filtroCoord === 'sin' ? 'colaboradores sin coordinador asignado'
                              : (c ? c.nombre : 'coordinador seleccionado');
    }
  }

  function listaVisible() {
    const q = query.trim().toLowerCase();
    return alcanceCoord().filter(s => {
      if (filtro !== 'todos' && s.canal !== filtro) return false;

      if (filtroImpacto !== 'todos') {
        if (s.canal !== PUNTUABLE) return false;
        if (filtroImpacto === 'sin') { if (s.impacto) return false; }
        else if (s.impacto !== filtroImpacto) return false;
      }
      if (filtroViab !== 'todos') {
        if (s.canal !== PUNTUABLE) return false;
        const b = banda(s.viabilidad);
        if (filtroViab === 'sin') { if (b) return false; }
        else if (!b || b.key !== filtroViab) return false;
      }

      if (!q) return true;
      return [s.colaborador_nombre, s.colaborador_cargo, s.coord_cargo_nombre, s.detalle]
        .some(v => String(v ?? '').toLowerCase().includes(q));
    });
  }

  function render() {
    const list = listaVisible();
    const tb = $('sgTbody');
    tb.innerHTML = '';
    if (!list.length) {
      tb.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--co-faint)">Sin registros.</td></tr>`;
      return;
    }
    list.forEach(s => {
      const tr = document.createElement('tr');
      const esAnon = !s.colaborador_nombre;
      const colCell = esAnon
        ? `<span class="sg-anon-chip">🔒 Anónimo</span>`
        : `<div class="sg-name">${esc(s.colaborador_nombre)}</div><div class="sg-sub">${esc(s.colaborador_cargo || '')}</div>`;
      tr.innerHTML = `
        <td>${canalBadge(s.canal)}</td>
        <td>${colCell}</td>
        <td>${coordCell(s)}</td>
        <td><div class="sg-detalle-preview" title="${esc(detalleTexto(s))}">${esc(detalleTexto(s))}</div></td>
        <td>${califCell(s)}</td>
        <td>${decCell(s)}</td>
        <td>${fmtFechaHora(s.created_at)}</td>
        <td>
          <div class="sg-cell-actions">
            <button class="sg-act-btn" data-action="view" data-id="${s.id}">Ver</button>
            <button class="sg-act-btn danger" data-action="del" data-id="${s.id}">Eliminar</button>
          </div>
        </td>`;
      tb.append(tr);
    });
  }

  async function cargarCoordinadores() {
    try {
      const res = await fetch(`${BASE}/api/get_coordinadores.php`, { cache: 'no-store' });
      const data = await res.json();
      coordinadores = data.success ? (data.data || []) : [];
    } catch (e) {
      coordinadores = [];
    }
    $('sgFilterCoord').innerHTML =
      '<option value="todos">Todos</option>'
      + coordinadores.map(c => `<option value="${c.id}">${esc(c.nombre)}</option>`).join('')
      + '<option value="sin">Sin asignar</option>';
    $('sgFilterCoord').value = filtroCoord;
  }

  async function cargar() {
    const res = await fetch(`${BASE}/api/get_sugerencias.php`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.success) { toast(data.error || 'Error al cargar', 'error'); return; }
    sugerencias = data.data || [];
    renderKpis(); render();
  }

  async function eliminar(id) {
    if (!confirm('¿Eliminar este registro? Esta acción no se puede deshacer.')) return;
    try {
      const res = await fetch(`${BASE}/api/delete_sugerencia.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id) }),
      });
      const data = await res.json();
      if (data.success) { toast('Registro eliminado'); cargar(); }
      else { toast(data.error || 'Error al eliminar', 'error'); }
    } catch (e) { toast('Error de red', 'error'); }
  }

  function openView(id) {
    const s = sugerencias.find(x => Number(x.id) === Number(id));
    if (!s) return;
    currentViewId = s.id;
    const esAnon = !s.colaborador_nombre;

    $('sgViewTitle').textContent = CANALES[s.canal] ? CANALES[s.canal].label : s.canal;
    $('sgViewSub').textContent = fmtFechaHora(s.created_at);

    let html = `
      <div class="sg-field">
        <label>Colaborador</label>
        <div>${esAnon ? '<span class="sg-anon-chip">🔒 Registro anónimo — identidad no disponible</span>' : esc(s.colaborador_nombre) + (s.colaborador_cargo ? ' · ' + esc(s.colaborador_cargo) : '')}</div>
      </div>
      <div class="sg-field">
        <label>Coordinador a cargo</label>
        <div>${coordCell(s)}</div>
      </div>
      <div class="sg-field">
        <label>Detalle</label>
        ${s.canal === 'encuesta' ? encuestaHtml(s) : `<div class="sg-view-detalle">${esc(s.detalle)}</div>`}
      </div>`;

    const adj = s.adjuntos || [];
    if (adj.length) {
      html += `
      <div class="sg-field">
        <label>Adjuntos (${adj.length})</label>
        <div class="sg-adj-lista">
          ${adj.map(a => {
            const peso = a.peso_bytes < 1048576
              ? (a.peso_bytes / 1024).toFixed(0) + ' KB'
              : (a.peso_bytes / 1048576).toFixed(1) + ' MB';
            return a.estado === 'subido' && a.drive_url
              ? `<a class="sg-adj-item" href="${esc(a.drive_url)}" target="_blank" rel="noopener">
                   <span class="sg-adj-nombre">${esc(a.nombre_archivo)}</span>
                   <span class="sg-adj-peso">${peso} · abrir en Drive ↗</span>
                 </a>`
              : `<div class="sg-adj-item pendiente">
                   <span class="sg-adj-nombre">${esc(a.nombre_archivo)}</span>
                   <span class="sg-adj-peso">${peso} · ${a.estado === 'pendiente' ? 'pendiente de subir a Drive' : 'error al guardar'}</span>
                 </div>`;
          }).join('')}
        </div>
      </div>`;
    }

    if (s.canal === PUNTUABLE) {
      const numeros = [];
      for (let i = P_MIN; i <= P_MAX; i++) numeros.push(i);
      const calificada = s.viabilidad != null || s.impacto;
      html += `
        <div class="sg-puntaje-box">
          <div class="sg-calif-sub" style="margin-top:0">Viabilidad (${P_MIN}-${P_MAX}) · ¿se puede hacer?</div>
          <div class="sg-puntaje-grid" id="sgViabGrid">
            ${numeros.map(n => `<button type="button" data-p="${n}" class="${s.viabilidad === n ? 'active' : ''}">${n}</button>`).join('')}
          </div>
          <div id="sgBandaLive"></div>

          <div class="sg-calif-sub">Impacto esperado · ¿cuánto aporta?</div>
          <div class="sg-impacto-grid" id="sgImpactoGrid">
            ${Object.entries(IMPACTOS).map(([k, i]) =>
              `<button type="button" data-i="${k}" class="${s.impacto === k ? 'active' : ''}"
                       data-color="${i.color}">${esc(i.label)}</button>`).join('')}
          </div>

          <div class="sg-dec-preview" id="sgDecPreview"></div>

          <textarea id="sgPuntajeComentario" placeholder="Comentario (opcional)…" maxlength="255">${esc(s.puntaje_comentario || '')}</textarea>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
            <span class="sg-puntaje-saved" id="sgPuntajeSaved">${calificada ? ('Calificado por ' + esc(s.puntaje_por || '—') + ' · ' + fmtFechaHora(s.puntaje_at)) : 'Aún sin calificar'}</span>
            <button class="sg-btn primary" id="sgGuardarCalif">Guardar calificación</button>
          </div>
        </div>`;
    }

    const telefonoDisponible = /^\+51 9\d{8}$/.test(String(s.colaborador_celular || ''));
    if (!esAnon) {
      const estadoEnvio = s.respuesta_whatsapp_at
        ? `Última respuesta: ${fmtFechaHora(s.respuesta_whatsapp_at)}.`
        : 'Aún no se ha enviado una respuesta.';
      html += `
        <div class="sg-wa-box">
          <div class="sg-wa-head"><span class="sg-wa-title">Respuesta por WhatsApp</span><span class="sg-wa-meta">${telefonoDisponible ? esc(s.colaborador_celular) : 'Celular no disponible'}</span></div>
          <textarea id="sgWhatsappRespuesta" maxlength="800" placeholder="Escribe la respuesta para el colaborador..." ${telefonoDisponible ? '' : 'disabled'}>${esc(s.respuesta_whatsapp || '')}</textarea>
          <div class="sg-wa-actions"><span class="sg-wa-status ${telefonoDisponible ? '' : 'is-disabled'}">${telefonoDisponible ? estadoEnvio : 'Registra un celular con formato +51 9XXXXXXXX para enviar.'}</span><button class="sg-btn primary" id="sgEnviarWhatsapp" ${telefonoDisponible ? '' : 'disabled'}>Enviar WhatsApp</button></div>
        </div>`;
    }

    $('sgViewBody').innerHTML = html;

    if (s.canal === PUNTUABLE) {
      let selViab = s.viabilidad ?? null;
      let selImp  = s.impacto || null;

      function pintar() {
        const b = banda(selViab);
        $('sgBandaLive').innerHTML = b
          ? `<span class="sg-banda-live" style="color:${b.color}"><span class="dot"></span>${esc(b.label)}</span>`
          : `<span class="sg-puntaje-saved">Sin viabilidad definida</span>`;

        const d = DECISIONES[decisionKey({ canal: PUNTUABLE, viabilidad: selViab, impacto: selImp })];
        const box = $('sgDecPreview');
        box.style.background   = d.color + '14';
        box.style.borderColor  = d.color + '4D';
        box.innerHTML = `<span class="tit" style="color:${d.color}">${d.icono} ${esc(d.label)}</span>
                         <span class="glosa">${esc(d.glosa)}</span>`;
      }

      $('sgViabGrid').addEventListener('click', e => {
        const b = e.target.closest('button[data-p]'); if (!b) return;
        const n = Number(b.dataset.p);
        selViab = (selViab === n) ? null : n;
        $('sgViabGrid').querySelectorAll('button')
          .forEach(x => x.classList.toggle('active', selViab !== null && Number(x.dataset.p) === selViab));
        pintar();
      });

      $('sgImpactoGrid').addEventListener('click', e => {
        const b = e.target.closest('button[data-i]'); if (!b) return;
        selImp = (selImp === b.dataset.i) ? null : b.dataset.i;
        $('sgImpactoGrid').querySelectorAll('button').forEach(x => {
          const on = x.dataset.i === selImp;
          x.classList.toggle('active', on);
          x.style.background  = on ? x.dataset.color : '';
          x.style.borderColor = on ? x.dataset.color : '';
        });
        pintar();
      });

      $('sgImpactoGrid').querySelectorAll('button.active').forEach(x => {
        x.style.background = x.dataset.color; x.style.borderColor = x.dataset.color;
      });
      pintar();

      $('sgGuardarCalif').addEventListener('click', async () => {
        try {
          const res = await fetch(`${BASE}/api/save_sugerencia_calificacion.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              id: s.id, viabilidad: selViab, impacto: selImp,
              comentario: $('sgPuntajeComentario').value.trim(),
            }),
          });
          const data = await res.json();
          if (data.success) { toast('Calificación guardada'); await cargar(); openView(s.id); }
          else { toast(data.error || 'Error al guardar', 'error'); }
        } catch (e) { toast('Error de red', 'error'); }
      });
    }

    if (!esAnon && telefonoDisponible) {
      $('sgEnviarWhatsapp').addEventListener('click', async () => {
        const respuesta = $('sgWhatsappRespuesta').value.trim();
        if (respuesta.length < 3) { toast('Escribe una respuesta de al menos 3 caracteres', 'error'); $('sgWhatsappRespuesta').focus(); return; }
        const btn = $('sgEnviarWhatsapp'); const original = btn.textContent;
        btn.disabled = true; btn.textContent = 'Enviando...';
        try {
          const res = await fetch(`${BASE}/api/send_sugerencia_whatsapp.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: s.id, respuesta }),
          });
          const data = await res.json();
          if (data.success) { toast('Respuesta enviada por WhatsApp'); await cargar(); openView(s.id); }
          else toast(data.error || 'No se pudo enviar el mensaje', 'error');
        } catch (e) { toast('Error de red al enviar WhatsApp', 'error'); }
        finally { btn.disabled = false; btn.textContent = original; }
      });
    }

    $('sgViewBack').classList.add('open');
  }
  function closeView() { $('sgViewBack').classList.remove('open'); currentViewId = null; }

  $('sgSearch').addEventListener('input', e => { query = e.target.value; render(); });
  $('sgFilter').addEventListener('click', e => {
    const b = e.target.closest('button[data-f]'); if (!b) return;
    filtro = b.dataset.f;
    $('sgFilter').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
    render();
  });

  function setFiltroCoord(v) {
    filtroCoord = v;
    $('sgFilterCoord').value = v;
    $('sgCoordWrap').classList.toggle('on', v !== 'todos');
    renderKpis(); render();
  }
  $('sgFilterCoord').addEventListener('change', e => setFiltroCoord(e.target.value));
  $('sgKpiScopeReset').addEventListener('click', () => setFiltroCoord('todos'));

  $('sgFilterImpacto').addEventListener('change', e => {
    filtroImpacto = e.target.value;
    $('sgImpactoWrap').classList.toggle('on', filtroImpacto !== 'todos');
    render();
  });
  $('sgFilterViab').addEventListener('change', e => {
    filtroViab = e.target.value;
    $('sgViabWrap').classList.toggle('on', filtroViab !== 'todos');
    render();
  });
  $('sgTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action]'); if (!b) return;
    if (b.dataset.action === 'view') openView(b.dataset.id);
    if (b.dataset.action === 'del')  eliminar(b.dataset.id);
  });
  $('sgViewClose').addEventListener('click', closeView);
  $('sgViewCloseBtn').addEventListener('click', closeView);
  $('sgViewBack').addEventListener('click', e => { if (e.target === $('sgViewBack')) closeView(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && $('sgViewBack').classList.contains('open')) closeView(); });

  $('btnCopyLink').addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(FORM_URL);
      toast('Enlace copiado: ' + FORM_URL);
    } catch (e) {
      toast(FORM_URL);
    }
  });

  function descripcionFiltro() {
    const partes = [];
    partes.push(filtro === 'todos' ? 'Todos los canales' : (CANALES[filtro]?.label || filtro));
    if (filtroCoord !== 'todos') {
      const c = coordinadores.find(x => String(x.id) === String(filtroCoord));
      partes.push('coordinador: ' + (filtroCoord === 'sin' ? 'sin asignar' : (c ? c.nombre : filtroCoord)));
    }
    if (filtroImpacto !== 'todos') {
      partes.push('impacto: ' + (filtroImpacto === 'sin' ? 'sin definir' : (IMPACTOS[filtroImpacto]?.label || filtroImpacto)));
    }
    if (filtroViab !== 'todos') {
      const b = BANDAS.find(x => x.key === filtroViab);
      partes.push('viabilidad: ' + (filtroViab === 'sin' ? 'sin calificar' : (b ? b.label : filtroViab)));
    }
    if (query.trim()) partes.push(`búsqueda: "${query.trim()}"`);
    return partes.join(' · ');
  }

  function nombreColaborador(s) {
    if (!s.colaborador_nombre) return 'Anónimo';
    return s.colaborador_nombre + (s.colaborador_cargo ? ' · ' + s.colaborador_cargo : '');
  }

  function coordTexto(s) {
    if (!s.colaborador_id) return 'No aplica (anónimo)';
    return s.coord_cargo_nombre || 'Sin asignar';
  }
  function viabTexto(s) {
    if (s.canal !== PUNTUABLE) return '';
    const b = banda(s.viabilidad);
    return b ? `${s.viabilidad}/${P_MAX} ${b.label}` : 'Sin calificar';
  }
  function impactoTexto(s) {
    if (s.canal !== PUNTUABLE) return '';
    return IMPACTOS[s.impacto]?.label || 'Sin definir';
  }
  function decisionTexto(s) {
    const k = decisionKey(s);
    return k ? (DECISIONES[k]?.label || '') : '';
  }

  function ahoraLocal() {
    const d = new Date();
    const p = n => String(n).padStart(2, '0');
    return {
      fecha: `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`,
      texto: `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`,
    };
  }

  $('btnExportPdf').addEventListener('click', () => {
    const list = listaVisible();
    if (!list.length) { toast('No hay registros para exportar', 'error'); return; }

    const filas = list.map(s => {
      const adj = s.adjuntos || [];
      const adjTxt = adj.length
        ? adj.map(a => esc(a.nombre_archivo)).join('<br>')
        : '<span class="vacio">—</span>';
      const vacio = t => t ? esc(t) : '<span class="vacio">—</span>';
      const dec = decisionTexto(s);
      return `<tr>
        <td class="nowrap">${fmtFechaHora(s.created_at)}</td>
        <td class="nowrap">${esc(CANALES[s.canal]?.label || s.canal)}</td>
        <td>${esc(nombreColaborador(s))}</td>
        <td class="nowrap">${esc(coordTexto(s))}</td>
        <td class="detalle">${esc(detalleTexto(s))}</td>
        <td class="adj">${adjTxt}</td>
        <td class="nowrap">${vacio(viabTexto(s))}</td>
        <td class="nowrap">${vacio(impactoTexto(s))}</td>
        <td class="nowrap">${dec ? `<strong>${esc(dec)}</strong>` : '<span class="vacio">—</span>'}</td>
      </tr>`;
    }).join('');

    const generado = ahoraLocal().texto;
    const doc = `<!doctype html><html lang="es"><head><meta charset="utf-8">
      <title>Sugerencias Tallyman</title>
      <style>
        @page { size: A4 landscape; margin: 14mm; }
        * { box-sizing:border-box; }
        body { font-family:'DM Sans', system-ui, sans-serif; color:#111827; margin:0; font-size:11px; }
        header { border-bottom:3px solid #00875A; padding-bottom:10px; margin-bottom:14px; }
        h1 { margin:0 0 3px; font-size:19px; color:#00875A; letter-spacing:-.01em; }
        .sub { font-size:11px; color:#4b5563; }
        .meta { margin-top:6px; font-size:10.5px; color:#6b7280; }
        table { width:100%; border-collapse:collapse; }
        th { background:#00875A; color:#fff; font-size:9.5px; text-transform:uppercase;
             letter-spacing:.05em; text-align:left; padding:7px 8px; }
        td { padding:7px 8px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
        tr:nth-child(even) td { background:#f8fafc; }
        .nowrap { white-space:nowrap; }
        .detalle { max-width:250px; }
        .adj { font-size:9.5px; color:#4b5563; max-width:110px; word-break:break-all; }
        .vacio { color:#9ca3af; }
        tr { break-inside:avoid; }
        thead { display:table-header-group; }
        footer { margin-top:14px; font-size:9.5px; color:#9ca3af; text-align:center; }
      </style></head><body>
      <header>
        <h1>Sugerencias Tallyman</h1>
        <div class="sub">Buzón de observaciones, consultas, solicitudes, propuestas de mejora y encuestas</div>
        <div class="meta">${esc(descripcionFiltro())} &nbsp;·&nbsp; ${list.length} registro(s) &nbsp;·&nbsp; Generado el ${generado}</div>
      </header>
      <table>
        <thead><tr>
          <th>Fecha</th><th>Canal</th><th>Colaborador</th><th>Coord. a cargo</th>
          <th>Detalle</th><th>Adjuntos</th><th>Viabilidad</th><th>Impacto</th><th>Decisión</th>
        </tr></thead>
        <tbody>${filas}</tbody>
      </table>
      <footer>Las observaciones son anónimas por diseño: su autor no se registra.</footer>
      <script>
        window.addEventListener('load', function () { window.focus(); window.print(); });
      <\/script>
    </body></html>`;

    const w = window.open('', '_blank');
    if (!w) { toast('Permite las ventanas emergentes para exportar el PDF', 'error'); return; }
    w.document.write(doc);
    w.document.close();
    toast(`${list.length} registro(s) · elige "Guardar como PDF"`);
  });

  $('btnExportXlsx').addEventListener('click', () => {
    const list = listaVisible();
    if (!list.length) { toast('No hay registros para exportar', 'error'); return; }

    const cel = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const cabecera = ['Fecha', 'Canal', 'Colaborador', 'Cargo', 'Coord. a cargo', 'Detalle',
                      'Adjuntos', 'Viabilidad', 'Impacto', 'Decisión', 'Calificado por'];
    const filas = list.map(s => [
      fmtFechaHora(s.created_at),
      CANALES[s.canal]?.label || s.canal,
      s.colaborador_nombre || 'Anónimo',
      s.colaborador_cargo || '',
      coordTexto(s),
      detalleTexto(s),
      (s.adjuntos || []).map(a => a.nombre_archivo).join(' | '),
      viabTexto(s),
      impactoTexto(s),
      decisionTexto(s),
      s.puntaje_por || '',
    ]);

    const csv = '﻿' + [cabecera, ...filas].map(f => f.map(cel).join(';')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = `sugerencias_${ahoraLocal().fecha}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    toast(`${list.length} registro(s) exportado(s)`);
  });

  cargarCoordinadores().then(cargar);
})();
</script>

</body>
</html>
