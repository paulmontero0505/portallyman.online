<?php
require_once('../includes/auth.php');
require_once('../includes/evaluacion_desempeno_catalogo.php');
require_report();

// Catálogos → JS (única fuente de verdad, definida en includes/evaluacion_desempeno_catalogo.php)
$JS_ESCALA    = ed_escala();      // {1:'Deficiente', ... 5:'Sobresaliente'}
$JS_CRITERIOS = ed_criterios();   // {A:{label,items:[...]}, B:..., C:...}
$JS_TURNOS    = ed_turnos();      // {dia:'Día', noche:'Noche'}
$EVALUADOR    = $_SESSION['user_name'] ?? '';

// Logo embebido (base64) para el encabezado del PDF exportado en el navegador.
$logo_path = __DIR__ . '/../logo/logo.png';
$LOGO_B64  = is_file($logo_path) ? base64_encode(file_get_contents($logo_path)) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Evaluación Diaria · Estiba Shift Command Deck</title>
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
       EVALUACIÓN DE DESEMPEÑO · registro + tabla (prefijo .ed-*)
       Mismo lenguaje visual que Reporte de Inspección (PREMIUM LIGHT EMERALD THEME)
    ════════════════════════════════════════════════════════════════ */
    .ed-wrap {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0, 135, 90, 0.18); --co-line-bold:rgba(0, 135, 90, 0.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      --mono: ui-monospace, Consolas, monospace;
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .ed-wrap *, .ed-wrap *::before, .ed-wrap *::after { box-sizing:border-box; }

    .ed-hero {
      background: linear-gradient(135deg, #005c3d 0%, #00875A 100%) !important;
      color:#fff; border-radius:20px; padding:22px 28px;
      display:flex; align-items:center; justify-content:space-between; gap:18px;
      border: 1px solid rgba(0, 135, 90, 0.2) !important;
      box-shadow: 0 8px 32px rgba(0, 135, 90, 0.08) !important;
    }
    .ed-hero h1 { margin:6px 0 4px; font-size:22px; font-weight:700; letter-spacing:-.01em; }
    .ed-hero p  { margin:0; color:rgba(255,255,255,.85); font-size:13px; max-width:560px; }
    .ed-hero .tag {
      display:inline-flex; align-items:center; gap:8px;
      padding:5px 11px; border-radius:999px;
      background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18);
      font-size:11px; font-weight:600; letter-spacing:.12em; text-transform:uppercase;
      color: #ffffff !important;
    }

    .ed-btn {
      display:inline-flex; align-items:center; gap:7px;
      padding:9px 16px; border-radius:10px; border:1px solid rgba(0, 135, 90, 0.3);
      background:#fff; cursor:pointer; text-decoration:none;
      font-family:inherit; font-size:13px; font-weight:600; color:#00875A;
      transition:all .15s;
    }
    .ed-btn:hover { border-color:var(--co-navy-700); color:var(--co-navy); background: rgba(0, 135, 90, 0.05); }
    body .ed-btn.primary,
    .ed-btn.primary {
      background: linear-gradient(135deg, #00875A 0%, #005c3d 100%) !important;
      color: #fff !important;
      border: none !important;
      font-weight: 700 !important;
      box-shadow: 0 4px 18px rgba(0, 135, 90, 0.2) !important;
      letter-spacing: 0.02em;
      padding: 11px 20px;
      border-radius: 12px;
    }
    body .ed-btn.primary:hover,
    .ed-btn.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(0, 135, 90, 0.35) !important;
      filter: brightness(1.08);
    }
    .ed-btn.primary:active { transform: translateY(0); }
    .ed-btn svg { width:14px; height:14px; }

    .ed-kpis { display:flex; gap:10px; flex-wrap:wrap; }
    .ed-kpi {
      flex:1; min-width:120px;
      background:#fff; border:1px solid var(--co-line); border-radius:14px;
      padding:14px 18px;
      box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;
    }
    .ed-kpi .lbl { font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--co-mute); }
    .ed-kpi:nth-child(1) .val { color:var(--co-navy-700); }
    .ed-kpi:nth-child(2) .val { color:#3b82f6; }
    .ed-kpi:nth-child(3) .val { color:#12B76A; }
    .ed-kpi:nth-child(4) .val { color:#d97706; }
    .ed-kpi .val { font-size:22px; font-weight:700; margin-top:4px; }

    .ed-toolbar {
      display:flex; gap:10px; align-items:center; flex-wrap:wrap;
      background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:10px 12px;
      box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;
    }
    .ed-search {
      flex:1; min-width:220px; display:flex; align-items:center; gap:8px;
      background:var(--co-deck); border:1px solid rgba(0, 135, 90, 0.15); border-radius:10px; padding:8px 12px;
    }
    .ed-search:focus-within { border-color:var(--co-navy-700); background:#fff; box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.15); }
    .ed-search input { flex:1; border:0; outline:0; background:transparent; font:inherit; font-size:13.5px; color:var(--co-ink); }
    .ed-search input::placeholder { color: var(--co-faint); opacity: 0.9; }
    .ed-search svg { width:15px; height:15px; color:var(--co-mute); }
    .ed-filter { display:flex; gap:4px; background:#f3f4f6; border-radius:10px; padding:3px; flex-wrap:wrap; border: 1px solid #e5e7eb; }
    .ed-filter button {
      padding:6px 12px; border:0; background:transparent; border-radius:7px;
      font:inherit; font-size:12px; font-weight:600; color:var(--co-mute); cursor:pointer;
    }
    .ed-filter button.active { background:#fff; color:var(--co-navy-700); box-shadow:0 1px 3px rgba(0,0,0,.06); border: 1px solid rgba(0, 135, 90, 0.2); }

    .ed-table-wrap { background:#fff; border:1px solid var(--co-line); border-radius:14px; overflow:auto; box-shadow: 0 4px 16px rgba(0,0,0,.02) !important; }
    .ed-table { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
    .ed-table thead tr { background: rgba(0, 135, 90, 0.04) !important; border-bottom:1px solid var(--co-line); }
    .ed-table th {
      padding:11px 14px; text-align:left;
      font-size:10.5px; letter-spacing:.1em; text-transform:uppercase;
      color:var(--co-navy); font-weight:700;
    }
    .ed-table tbody tr { border-bottom:1px solid rgba(0, 135, 90, 0.06); transition:background .12s; }
    .ed-table tbody tr:last-child { border-bottom:0; }
    .ed-table tbody tr:hover { background: rgba(0, 135, 90, 0.02); }
    .ed-table td { padding:11px 14px; vertical-align:middle; color: var(--co-ink) !important; }
    .ed-name { font-weight:600; color:var(--co-ink); }
    .ed-sub  { font-size:11px; color:var(--co-faint); }

    .ed-badge {
      display:inline-flex; align-items:center; gap:5px;
      padding:3px 9px; border-radius:999px;
      font-size:10.5px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
      color:#fff;
    }
    .ed-badge .dot { width:6px; height:6px; border-radius:50%; background:rgba(255,255,255,.85); }
    .ed-turno-chip {
      display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:6px;
      font-size:11px; font-weight:600; background:rgba(0, 135, 90, 0.06); color:var(--co-navy);
    }

    .ed-act-btn {
      padding:5px 10px; border-radius:7px; border:1px solid rgba(0, 135, 90, 0.25);
      background:rgba(0, 135, 90, 0.05); cursor:pointer; font:inherit; font-size:12px; font-weight:600; color:#00875A;
      transition:all .12s;
    }
    .ed-act-btn:hover { border-color:var(--co-navy-700); color:#ffffff; background:#00875A; }
    .ed-act-btn.danger { color: var(--co-red); border-color: rgba(239, 68, 68, 0.25); background: rgba(239, 68, 68, 0.05); }
    .ed-act-btn.danger:hover { border-color:var(--co-red); color:#ffffff; background: var(--co-red); }
    .ed-cell-actions { display:flex; gap:6px; align-items:center; justify-content:flex-end; }

    /* ── Modales ── */
    .ed-modal-back {
      position:fixed; inset:0; background:rgba(0, 0, 0, 0.3);
      display:grid; place-items:center; z-index:995;
      opacity:0; pointer-events:none; transition:opacity .2s;
      backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    }
    .ed-modal-back.open { opacity:1; pointer-events:auto; }
    .ed-modal {
      background:#fff; border-radius:18px; width:620px; max-width:94vw;
      box-shadow: 0 24px 64px rgba(0, 135, 90, 0.12);
      transform:translateY(12px) scale(.97); transition:transform .22s cubic-bezier(.25,.46,.45,.94);
      max-height:92vh; display:flex; flex-direction:column; overflow:hidden;
      border: 1px solid var(--co-line);
    }
    .ed-modal-back.open .ed-modal { transform:translateY(0) scale(1); }
    .ed-modal-head {
      padding:18px 20px 14px; border-bottom:1px solid rgba(0, 135, 90, 0.08);
      display:flex; align-items:center; justify-content:space-between;
    }
    .ed-modal-head h3 { margin:0; font-size:17px; font-weight:700; color: var(--co-ink); }
    .ed-modal-head .sub { font-size:12px; color:var(--co-mute); margin-top:2px; }
    .ed-modal-close {
      width:32px; height:32px; border-radius:8px; border:1px solid #d1d5db;
      background:#fff; cursor:pointer; display:grid; place-items:center; color:var(--co-mute);
    }
    .ed-modal-close:hover { color:var(--co-red); border-color:var(--co-red); }
    .ed-modal-body { padding:18px 20px; display:flex; flex-direction:column; gap:12px; overflow-y:auto; flex:1; background: #ffffff; }
    .ed-modal-foot { padding:14px 20px; border-top:1px solid rgba(0, 135, 90, 0.08); display:flex; justify-content:flex-end; gap:8px; background: #ffffff; }
    .ed-field { display:flex; flex-direction:column; gap:5px; }
    .ed-field label {
      font-size:11px; font-weight:700; color:#374151; letter-spacing:.05em; text-transform:uppercase;
    }
    .ed-field input, .ed-field select, .ed-field textarea {
      font:inherit; font-size:13.5px; color:#111827;
      background:#ffffff; border:1.5px solid #cbd5e1; border-radius:8px;
      padding:9px 11px; outline:0; transition:border-color .15s, box-shadow .15s;
    }
    .ed-field input::placeholder, .ed-field textarea::placeholder { color:#94a3b8; }
    .ed-field select option { color:#111827; background:#ffffff; }
    .ed-field textarea { resize:vertical; min-height:70px; }
    .ed-field input:focus, .ed-field select:focus, .ed-field textarea:focus {
      border-color:#00875A; background:#fff; box-shadow:0 0 0 3px rgba(0, 135, 90, 0.15);
    }
    .ed-field input[readonly] { background:#f3f4f6; color:#4b5563; cursor:default; border-color: #e5e7eb; }
    .ed-colsel { position:relative; }
    .ed-colsel-panel {
      display:none; position:fixed; z-index:9000;
      min-width:320px; max-width:420px;
      background:#fff; border:1px solid rgba(0, 135, 90, 0.25); border-radius:11px;
      box-shadow:0 16px 40px rgba(0,0,0,.08); max-height:240px; overflow-y:auto;
      padding:5px;
    }
    .ed-colsel-panel.open { display:block; }
    .ed-colsel-item {
      display:flex; align-items:center; gap:10px;
      padding:9px 11px; border-radius:8px; cursor:pointer;
    }
    .ed-colsel-item:hover { background:rgba(0, 135, 90, 0.05); }
    .ed-colsel-avatar {
      width:30px; height:30px; border-radius:8px; flex-shrink:0;
      background:rgba(0, 135, 90, 0.08); color:var(--co-navy); font-size:10px; font-weight:800;
      display:flex; align-items:center; justify-content:center; letter-spacing:-.3px;
    }
    .ed-colsel-info { display:flex; flex-direction:column; gap:1px; min-width:0; }
    .ed-colsel-nm { font-size:13px; font-weight:600; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ed-colsel-cd { font-size:11px; color:#4b5563; }
    .ed-colsel-empty { padding:12px; font-size:12px; color:#4b5563; text-align:center; }

    .ed-toast {
      position:fixed; bottom:24px; right:24px; z-index:999;
      background:#111827; color:#fff; padding:12px 18px; border-radius:10px;
      font-size:13px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,.1);
      transform:translateY(120%); opacity:0; transition:all .25s;
      border: 1px solid rgba(255,255,255,0.1);
    }
    .ed-toast.show { transform:translateY(0); opacity:1; }
    .ed-toast.is-error { background:#dc2626; border-color: #ef4444; }

    .content { padding:24px 28px 60px; overflow-y:auto; }

    /* ════════════════════════════════════════════════════════════════
       FORMULARIO DE EVALUACIÓN · rediseño "parte / command console"
    ════════════════════════════════════════════════════════════════ */
    #edModalBack, #edViewBack {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-navy-900:#001226; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0, 135, 90, 0.18); --co-line-bold:rgba(0, 135, 90, 0.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      --mono: ui-monospace, "SFMono-Regular", Consolas, monospace;
      font-family:'DM Sans', system-ui, sans-serif;
    }

    .ed-modal.ed-create {
      width:960px; max-width:96vw; padding:0;
      flex-direction:row; align-items:stretch; max-height:92vh;
    }
    .ed-create *, .ed-create *::before, .ed-create *::after { box-sizing:border-box; }

    .ed-rail {
      position:relative; flex:0 0 264px; width:264px; color:var(--co-ink);
      padding:24px 22px; overflow:hidden;
      display:flex; flex-direction:column; gap:22px;
      background:#f5f8f7; border-right:1px solid var(--co-line);
    }
    .ed-rail > * { position:relative; z-index:1; }
    .ed-rail-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .ed-rail-kicker { font-family:var(--mono); font-size:9.5px; letter-spacing:.22em; color:var(--co-mute); }
    .ed-rail-folio {
      font-family:var(--mono); font-size:10px; letter-spacing:.06em;
      padding:3px 9px; border-radius:999px; background:rgba(0, 135, 90, 0.08);
      border:1px solid rgba(0, 135, 90, 0.2); color:var(--co-navy); white-space:nowrap;
    }
    .ed-rail-lbl {
      display:block; font-family:var(--mono); font-size:9px; letter-spacing:.18em;
      text-transform:uppercase; color:var(--co-faint); margin-bottom:8px;
    }
    .ed-rail-id { display:flex; align-items:center; gap:12px; }
    .ed-rail-avatar {
      width:46px; height:46px; border-radius:13px; flex-shrink:0;
      background: linear-gradient(135deg, #00875A 0%, #00b377 100%); color:#ffffff;
      display:grid; place-items:center; font-size:16px; font-weight:700;
      box-shadow: 0 2px 8px -4px rgba(0, 135, 90, 0.2);
    }
    .ed-rail-name  { font-size:15px; font-weight:700; line-height:1.2; overflow:hidden; text-overflow:ellipsis; color:var(--co-ink); }
    .ed-rail-cargo { font-size:11.5px; color:var(--co-mute); margin-top:2px; }
    .ed-rail-count { font-size:26px; font-weight:800; color:var(--co-navy-700); line-height:1; }
    .ed-rail-count.low { color:var(--co-red); }
    .ed-rail-max { font-size:12px; color:var(--co-faint); font-weight:600; }
    .ed-rail-foot { margin-top:auto; padding-top:16px; border-top:1px solid var(--co-line); }
    .ed-rail-eval { font-size:13.5px; font-weight:600; color:var(--co-ink); }

    .ed-form { flex:1; min-width:0; display:flex; flex-direction:column; background:#fff; }
    .ed-form-head {
      padding:20px 24px 16px; border-bottom:1px solid var(--co-line);
      display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
    }
    .ed-form-head h3 { margin:0; font-size:18px; font-weight:700; color:var(--co-ink); letter-spacing:-.01em; }
    .ed-form-head .sub { font-size:12px; color:var(--co-mute); margin-top:3px; }
    .ed-form-body { padding:6px 24px 18px; overflow-y:auto; flex:1; }

    .ed-sec { padding:15px 0; border-bottom:1px dashed var(--co-line); }
    .ed-sec:last-child { border-bottom:0; }
    .ed-sec-head { display:flex; align-items:center; gap:9px; margin-bottom:12px; }
    .ed-sec-num {
      font-family:var(--mono); font-size:10px; font-weight:700; color:var(--co-navy);
      background:var(--co-deck); border:1px solid rgba(0, 135, 90, 0.25);
      padding:2px 6px; border-radius:6px; letter-spacing:.05em;
    }
    .ed-sec-head > span:last-child { font-size:11.5px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--co-mute); }
    .ed-row2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

    /* escala de calificación (banda informativa) */
    .ed-escala-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; }
    .ed-escala-opt {
      border:1.5px solid var(--co-line); border-radius:10px; padding:8px 6px; text-align:center; background:#fff;
    }
    .ed-escala-opt b { display:block; font-size:14px; color:var(--co-navy); }
    .ed-escala-opt span { font-size:10px; color:var(--co-mute); font-weight:600; }

    /* turno: toggle día/noche */
    .ed-turno-toggle { display:grid; grid-template-columns:1fr 1fr; gap:6px; background:#f3f4f6; border-radius:10px; padding:4px; border: 1px solid #e5e7eb; }
    .ed-turno-toggle button {
      border:0; background:transparent; border-radius:8px; padding:9px; cursor:pointer;
      font:inherit; font-size:13px; font-weight:600; color:var(--co-mute);
      display:flex; align-items:center; justify-content:center; gap:7px; transition:all .15s;
    }
    .ed-turno-toggle button svg { width:15px; height:15px; }
    .ed-turno-toggle button:hover { color:var(--co-navy); }
    .ed-turno-toggle button.active { background:#fff; color:var(--co-navy-700); box-shadow:0 1px 4px rgba(0,0,0,.08); border: 1px solid rgba(0, 135, 90, 0.2); }
    .ed-turno-toggle button[data-turno="noche"].active { background:#111827; color:#ffffff; border-color: #111827; }

    /* checklist de criterios agrupado por categoría */
    .ed-cat-group { display:flex; flex-direction:column; gap:8px; margin-bottom:14px; }
    .ed-cat-title {
      background: var(--co-navy); color:#fff; font-size:11.5px; font-weight:700;
      letter-spacing:.03em; padding:7px 11px; border-radius:8px;
    }
    .ed-crit-row {
      display:grid; grid-template-columns:1fr auto; gap:10px; align-items:center;
      background:var(--co-deck); border:1px solid var(--co-line); border-radius:10px; padding:10px 12px;
    }
    .ed-crit-item { font-size:12.5px; font-weight:600; color:var(--co-ink); line-height:1.35; }
    .ed-crit-obs {
      grid-column:1 / -1; font:inherit; font-size:12.5px; color:#111827;
      background:#fff; border:1.5px solid #cbd5e1; border-radius:7px; padding:7px 9px; outline:0;
    }
    .ed-crit-obs:focus { border-color:#00875A; box-shadow:0 0 0 3px rgba(0, 135, 90, 0.15); }
    .ed-score-toggle { display:flex; gap:4px; }
    .ed-score-toggle button {
      width:28px; height:28px; border:1.5px solid var(--co-line); background:#fff; border-radius:7px;
      font:inherit; font-size:12px; font-weight:700; color:var(--co-mute); cursor:pointer; transition:all .13s;
    }
    .ed-score-toggle button[data-p="1"].active { background:#dc2626; border-color:#dc2626; color:#fff; }
    .ed-score-toggle button[data-p="2"].active { background:#f97316; border-color:#f97316; color:#fff; }
    .ed-score-toggle button[data-p="3"].active { background:#eab308; border-color:#eab308; color:#fff; }
    .ed-score-toggle button[data-p="4"].active { background:#84cc16; border-color:#84cc16; color:#fff; }
    .ed-score-toggle button[data-p="5"].active { background:#12B76A; border-color:#12B76A; color:#fff; }

    /* resumen de puntuación */
    .ed-resumen-row {
      display:grid; grid-template-columns:170px 90px 1fr; gap:10px; align-items:start;
      background:var(--co-deck); border:1px solid var(--co-line); border-radius:10px; padding:10px 12px; margin-bottom:8px;
    }
    .ed-resumen-cat { font-size:12.5px; font-weight:700; color:var(--co-ink); }
    .ed-resumen-score { font-size:16px; font-weight:800; color:var(--co-navy-700); text-align:center; }
    .ed-resumen-max { font-size:10px; color:var(--co-faint); font-weight:600; text-align:center; }
    .ed-resumen-obs {
      font:inherit; font-size:12.5px; color:#111827; background:#fff; border:1.5px solid #cbd5e1;
      border-radius:7px; padding:7px 9px; outline:0; resize:vertical; min-height:40px;
    }
    .ed-resumen-obs:focus { border-color:#00875A; box-shadow:0 0 0 3px rgba(0, 135, 90, 0.15); }
    .ed-resumen-total {
      display:flex; align-items:center; justify-content:space-between;
      background:var(--co-navy); color:#fff; border-radius:10px; padding:12px 16px; font-weight:800; font-size:15px;
    }

    .ed-form-foot { padding:14px 24px; border-top:1px solid var(--co-line); display:flex; justify-content:flex-end; gap:8px; }

    @keyframes edSecIn { from { opacity:0; transform:translateY(9px); } to { opacity:1; transform:none; } }
    @keyframes edRailIn { from { opacity:0; transform:translateX(-14px); } to { opacity:1; transform:none; } }
    .ed-modal-back.open .ed-rail { animation:edRailIn .5s both cubic-bezier(.22,.61,.36,1); }
    .ed-modal-back.open .ed-form-body section { animation:edSecIn .45s both cubic-bezier(.22,.61,.36,1); }
    .ed-modal-back.open .ed-form-body section:nth-of-type(1){ animation-delay:.06s; }
    .ed-modal-back.open .ed-form-body section:nth-of-type(2){ animation-delay:.11s; }
    .ed-modal-back.open .ed-form-body section:nth-of-type(3){ animation-delay:.16s; }
    .ed-modal-back.open .ed-form-body section:nth-of-type(4){ animation-delay:.21s; }
    .ed-modal-back.open .ed-form-body section:nth-of-type(5){ animation-delay:.26s; }
    .ed-modal-back.open .ed-form-body section:nth-of-type(6){ animation-delay:.31s; }

    /* ── Vista detalle ── */
    .ed-modal--view .ed-modal-head { display:none; }
    .ed-modal--view .ed-modal-body { padding:0; overflow:hidden; display:block; }
    .ed-modal--view #edViewEdit { background:linear-gradient(135deg,#00875A,#005c3d); border-color:transparent; }
    .ed-modal--view #edViewEdit:hover { background:linear-gradient(135deg,#00b377,#00875A); }
    .ed-view-layout { display:grid; grid-template-columns:105px 1fr; height:72vh; overflow:hidden; }
    .ed-view-sidebar {
      background:linear-gradient(160deg,#005c3d 0%,#00875A 100%);
      padding:20px 12px; color:#fff;
      display:flex; flex-direction:column; gap:12px; align-items:center; overflow-y:auto; min-height:0;
    }
    .ed-view-sidebar .iv-divider { width:100%; border:none; border-top:1px solid rgba(255,255,255,.2); margin:0; }
    .iv-stat { text-align:center; width:100%; }
    .iv-stat-k { font-size:8px; opacity:.75; text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:2px; }
    .iv-stat-v { font-size:11px; font-weight:700; }
    .ed-view-sidebar .iv-eval { text-align:center; margin-top:auto; padding-top:12px; border-top:1px solid rgba(255,255,255,.2); width:100%; }
    .ed-view-sidebar .iv-eval .iv-stat-k { opacity:.75; }
    .ed-view-sidebar .iv-eval .iv-stat-v { font-size:10px; font-weight:600; }
    .ed-view-main { padding:18px 16px; display:flex; flex-direction:column; gap:10px; overflow-y:auto; min-height:0; background:#fff; }
    .ed-view-name { font-size:14px; font-weight:800; color:#005c3d; line-height:1.3; margin:0; }
    .ed-view-cargo { font-size:11px; color:var(--co-mute); margin-top:3px; font-weight:500; }
    .ed-view-divider { border:none; border-top:1px solid var(--co-line); margin:2px 0; }
    .ed-view-cat { background:var(--co-navy); color:#fff; font-size:11px; font-weight:700; padding:6px 10px; border-radius:7px; margin-top:4px; }
    .ed-view-table { width:100%; border-collapse:collapse; font-size:12.5px; }
    .ed-view-table th { text-align:left; padding:6px 8px; font-size:10px; letter-spacing:.06em; text-transform:uppercase; color:var(--co-mute); border-bottom:1px solid var(--co-line); }
    .ed-view-table td { padding:7px 8px; border-bottom:1px solid rgba(0, 135, 90, 0.06); vertical-align:top; }
    .ed-view-crit-list { display:flex; flex-direction:column; gap:8px; margin:2px 0 4px; }
    .ed-view-crit-row {
      display:grid; grid-template-columns:1fr auto; gap:10px; align-items:center;
      background:var(--co-deck); border:1px solid var(--co-line); border-radius:10px; padding:10px 12px;
    }
    .ed-view-crit-row .ed-crit-item { font-size:12.5px; font-weight:600; color:var(--co-ink); line-height:1.35; }
    .ed-view-crit-row .ed-score-toggle { display:flex; gap:4px; }
    .ed-view-crit-row .ed-score-toggle button {
      width:26px; height:26px; border:1.5px solid var(--co-line); background:#fff; border-radius:7px;
      font:inherit; font-size:11.5px; font-weight:700; color:var(--co-mute); cursor:default; pointer-events:none;
    }
    .ed-view-crit-row .ed-score-toggle button[data-p="1"].active { background:#dc2626; border-color:#dc2626; color:#fff; }
    .ed-view-crit-row .ed-score-toggle button[data-p="2"].active { background:#f97316; border-color:#f97316; color:#fff; }
    .ed-view-crit-row .ed-score-toggle button[data-p="3"].active { background:#eab308; border-color:#eab308; color:#fff; }
    .ed-view-crit-row .ed-score-toggle button[data-p="4"].active { background:#84cc16; border-color:#84cc16; color:#fff; }
    .ed-view-crit-row .ed-score-toggle button[data-p="5"].active { background:#12B76A; border-color:#12B76A; color:#fff; }
    .ed-view-crit-obs { grid-column:1 / -1; font-size:12px; color:var(--co-mute); line-height:1.4; }
    .ed-view-crit-obs.is-empty { color:var(--co-faint); font-style:italic; }
    .ed-view-notes { background:#f9fafb; border:1px solid var(--co-line); border-radius:8px; padding:10px 12px; }
    .ed-view-notes .iv-k { font-size:9px; color:var(--co-mute); text-transform:uppercase; letter-spacing:.5px; font-weight:600; display:block; margin-bottom:3px; }
    .ed-view-notes .iv-v { font-size:13px; color:#4b5563; font-weight:400; line-height:1.5; }

    @media (max-width:760px) {
      .ed-modal.ed-create { flex-direction:column; width:96vw; }
      .ed-rail { flex:0 0 auto; width:100%; }
      .ed-rail-foot { margin-top:14px; }
    }
    @media (max-width: 600px) {
      .ed-modal, .ed-modal.ed-create {
        width: calc(100vw - 12px) !important; max-width: 100% !important;
        height: 95dvh !important; max-height: 95dvh !important; border-radius: 14px !important;
        display: flex !important; flex-direction: column !important; overflow: hidden !important;
      }
      .ed-modal.ed-create .ed-rail { flex: 0 0 auto !important; overflow: visible !important; padding: 14px 16px !important; gap: 8px !important; }
      .ed-modal.ed-create .ed-form { flex: 1 1 0 !important; min-height: 0 !important; display: flex !important; flex-direction: column !important; overflow: hidden !important; }
      .ed-form-body { flex: 1 1 0 !important; min-height: 0 !important; overflow-y: auto !important; overscroll-behavior: contain !important; padding: 12px 14px 16px !important; }
      .ed-form-head { padding: 14px 14px 10px !important; }
      .ed-form-head h3 { font-size: 16px !important; }
      .ed-form-foot { padding: 12px 14px !important; }
      .ed-row2 { grid-template-columns: 1fr !important; }
      .ed-sec { padding: 12px 0 !important; }
      .ed-field label { color: #111827 !important; font-size: 10.5px !important; }
      .ed-field input, .ed-field select, .ed-field textarea {
        font-size: 15px !important; padding: 11px 12px !important; color: #111827 !important;
        background: #fff !important; border: 1.5px solid #cbd5e1 !important;
      }
      .ed-colsel-panel { min-width: 90vw !important; max-width: 94vw !important; }
      .ed-escala-grid { grid-template-columns: repeat(5,1fr); gap:4px; }
      .ed-resumen-row { grid-template-columns: 1fr; }
    }
    @media (max-width: 390px) { .ed-rail { display: none !important; } .ed-form { width: 100% !important; } }
    @media (prefers-reduced-motion:reduce) {
      .ed-modal-back.open .ed-rail, .ed-modal-back.open .ed-form-body section { animation:none; }
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
      <div class="ed-wrap">

        <!-- HERO -->
        <section class="ed-hero">
          <div>
            <span class="tag">CONTROL DE CAMPO · EVALUACIÓN DIARIA</span>
            <h1>Evaluación Diaria</h1>
            <p>Ficha de evaluación (procedimientos operativos, seguridad/EPP y relaciones interpersonales) con escala de calificación del 1 al 5 por criterio.</p>
          </div>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
            <button class="ed-btn primary" id="btnNew">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Registrar evaluación
            </button>
          </div>
        </section>

        <!-- KPIS -->
        <section class="ed-kpis">
          <div class="ed-kpi"><div class="lbl">Total</div><div class="val" id="kpiTotal">0</div></div>
          <div class="ed-kpi"><div class="lbl">Promedio</div><div class="val" id="kpiProm">0</div></div>
          <div class="ed-kpi"><div class="lbl">% Óptimo</div><div class="val" id="kpiPct">0%</div></div>
          <div class="ed-kpi"><div class="lbl">Este mes</div><div class="val" id="kpiMes">0</div></div>
        </section>

        <!-- TOOLBAR -->
        <div class="ed-toolbar">
          <div class="ed-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="edSearch" type="text" placeholder="Buscar por evaluado, actividad, evaluador…">
          </div>
          <div class="ed-filter" id="edFilter">
            <button class="active" data-f="todos">Todos</button>
            <button data-f="alto">Alto desempeño</button>
            <button data-f="bajo">Con puntajes bajos</button>
          </div>
        </div>

        <!-- TABLA -->
        <div class="ed-table-wrap">
          <table class="ed-table">
            <thead>
              <tr>
                <th>Evaluado</th>
                <th>Actividad</th>
                <th>Turno</th>
                <th>Evaluador</th>
                <th>Fecha</th>
                <th>Puntaje total</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody id="edTbody">
              <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--co-faint)">Cargando…</td></tr>
            </tbody>
          </table>
        </div>

      </div>
    </main>
  </div>
</div>

<!-- MODAL: registrar / editar -->
<div class="ed-modal-back" id="edModalBack">
  <div class="ed-modal ed-create">

    <!-- RAIL IZQUIERDO -->
    <aside class="ed-rail">
      <div class="ed-rail-top">
        <span class="ed-rail-kicker">FICHA · EVALUACIÓN</span>
        <span class="ed-rail-folio" id="railFolio">NUEVO</span>
      </div>

      <div class="ed-rail-id">
        <div class="ed-rail-avatar" id="railAvatar">—</div>
        <div style="min-width:0">
          <div class="ed-rail-name" id="railName">Sin evaluado</div>
          <div class="ed-rail-cargo" id="railCargo">Selecciona el colaborador evaluado</div>
        </div>
      </div>

      <div>
        <span class="ed-rail-lbl">Puntaje total</span>
        <div class="ed-rail-count" id="railCount">0</div>
        <div class="ed-rail-max" id="railMax">de 0 puntos posibles</div>
      </div>

      <div class="ed-rail-foot">
        <span class="ed-rail-lbl">Evaluador que reporta</span>
        <div class="ed-rail-eval" id="railEval"><?= htmlspecialchars($EVALUADOR) ?></div>
      </div>
    </aside>

    <!-- COLUMNA DERECHA · formulario -->
    <div class="ed-form">
      <div class="ed-form-head">
        <div>
          <h3 id="edModalTitle">Registrar evaluación</h3>
          <div class="sub">Completa el checklist. El puntaje se calcula automáticamente.</div>
        </div>
        <button class="ed-modal-close" id="edModalClose">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <div class="ed-form-body">
        <input type="hidden" id="im-id">
        <input type="hidden" id="im-turno">

        <section class="ed-sec">
          <div class="ed-sec-head"><span class="ed-sec-num">01</span><span>Colaborador evaluado</span></div>
          <div class="ed-row2">
            <div class="ed-field">
              <label>Apellidos y Nombres</label>
              <div class="ed-colsel" id="im-eval-wrap">
                <input type="text" id="im-eval-search" autocomplete="off" placeholder="Buscar por nombre o código…">
                <div class="ed-colsel-panel" id="im-eval-panel"></div>
                <select id="im-eval" style="display:none"><option value="">Cargando…</option></select>
              </div>
            </div>
            <div class="ed-field">
              <label>Cargo</label>
              <input id="im-cargo" type="text" readonly placeholder="—">
            </div>
          </div>
        </section>

        <section class="ed-sec">
          <div class="ed-sec-head"><span class="ed-sec-num">02</span><span>Actividad, turno y fecha</span></div>
          <div class="ed-row2">
            <div class="ed-field">
              <label>Actividad</label>
              <input id="im-actividad" type="text" placeholder="Actividad evaluada…" maxlength="150">
            </div>
            <div class="ed-field">
              <label>Fecha</label>
              <input id="im-fecha" type="date">
            </div>
          </div>
          <div class="ed-field" style="margin-top:10px">
            <label>Turno</label>
            <div class="ed-turno-toggle" id="turnoToggle">
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
        </section>

        <section class="ed-sec">
          <div class="ed-sec-head"><span class="ed-sec-num">03</span><span>Escala de calificación</span></div>
          <div class="ed-escala-grid" id="edEscalaGrid"></div>
        </section>

        <section class="ed-sec">
          <div class="ed-sec-head"><span class="ed-sec-num">04</span><span>Criterios de evaluación</span></div>
          <div id="edChecklist"></div>
        </section>

        <section class="ed-sec">
          <div class="ed-sec-head"><span class="ed-sec-num">05</span><span>Resumen de puntuación</span></div>
          <div id="edResumen"></div>
          <div class="ed-resumen-total">
            <span>Puntaje total</span>
            <span id="edTotalScore">0 / 0</span>
          </div>
        </section>

        <section class="ed-sec">
          <div class="ed-sec-head"><span class="ed-sec-num">06</span><span>Comentarios generales del evaluador</span></div>
          <div class="ed-field">
            <textarea id="im-comentarios" placeholder="Comentarios generales…" maxlength="2000"></textarea>
          </div>
        </section>
      </div>

      <div class="ed-form-foot">
        <button class="ed-btn" id="edModalCancel">Cancelar</button>
        <button class="ed-btn primary" id="edModalSave">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Guardar evaluación
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: ver detalle -->
<div class="ed-modal-back" id="edViewBack">
  <div class="ed-modal ed-modal--view">
    <div class="ed-modal-head" style="display:none">
      <div><h3>Detalle de evaluación</h3><div class="sub" id="edViewSub">—</div></div>
      <button class="ed-modal-close" id="edViewClose"></button>
    </div>
    <div class="ed-modal-body" id="edViewBody"></div>
    <div class="ed-modal-foot">
      <button class="ed-btn" id="edViewCloseBtn">Cerrar</button>
      <button class="ed-btn" id="edViewPdf">Exportar PDF</button>
      <button class="ed-btn primary" id="edViewEdit">Editar</button>
    </div>
  </div>
</div>

<div class="ed-toast" id="edToast">—</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.0/jspdf.plugin.autotable.min.js"></script>
<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  // ── Catálogos desde PHP (fuente de verdad) ──
  const ESCALA    = <?= json_encode($JS_ESCALA, JSON_UNESCAPED_UNICODE) ?>;    // {1:'Deficiente', ...}
  const CRITERIOS = <?= json_encode($JS_CRITERIOS, JSON_UNESCAPED_UNICODE) ?>; // {A:{label,items:[...]}, ...}
  const TURNOS    = <?= json_encode($JS_TURNOS, JSON_UNESCAPED_UNICODE) ?>;    // {dia:'Día', noche:'Noche'}
  const EVALUADOR = <?= json_encode($EVALUADOR, JSON_UNESCAPED_UNICODE) ?>;
  const BASE      = '..';
  const LOGO_B64  = <?= $LOGO_B64 ? json_encode('data:image/png;base64,' . $LOGO_B64) : 'null' ?>;
  const CAT_KEYS  = Object.keys(CRITERIOS);
  const MAX_ITEM  = Math.max(...Object.keys(ESCALA).map(Number));
  const MAX_TOTAL = CAT_KEYS.reduce((s, k) => s + CRITERIOS[k].items.length, 0) * MAX_ITEM;

  let evaluaciones = [];
  let colaboradores = [];
  let query = '';
  let filtro = 'todos';
  let editingId = null;

  function toast(msg, type) {
    const t = $('edToast');
    t.textContent = msg;
    t.className = 'ed-toast show' + (type === 'error' ? ' is-error' : '');
    setTimeout(() => t.classList.remove('show'), 3000);
  }
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g,
      c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  }
  function fmtFecha(f) {
    if (!f) return '—';
    const [y,m,d] = String(f).split('-');
    return d ? `${d}/${m}/${y}` : f;
  }
  function initials(n) {
    const w = String(n || '').trim().split(/\s+/);
    return ((w[0]?.[0] ?? '') + (w[1]?.[0] ?? '')).toUpperCase() || '—';
  }
  function turnoLabel(k) { return TURNOS[k] || k || '—'; }
  function bajosDe(ev) {
    return (ev.criterios || []).filter(c => c.puntaje != null && c.puntaje <= 2).length;
  }

  function initEscalaGrid() {
    $('edEscalaGrid').innerHTML = Object.entries(ESCALA).map(([k, label]) =>
      `<div class="ed-escala-opt"><b>${esc(k)}</b><span>${esc(label)}</span></div>`).join('');
  }

  // ─── Checklist agrupado por categoría: se reconstruye al abrir el modal ───
  function buildChecklist(valores) {
    const map = {};
    (valores || []).forEach(c => { map[c.categoria + '|' + c.item] = c; });
    $('edChecklist').innerHTML = CAT_KEYS.map(catKey => {
      const cat = CRITERIOS[catKey];
      const rows = cat.items.map(item => {
        const c = map[catKey + '|' + item] || {};
        const puntaje = c.puntaje ?? '';
        const obs = c.observaciones || '';
        return `
          <div class="ed-crit-row" data-cat="${esc(catKey)}" data-item="${esc(item)}">
            <span class="ed-crit-item">${esc(item)}</span>
            <div class="ed-score-toggle">
              ${Object.keys(ESCALA).map(p =>
                `<button type="button" data-p="${p}" class="${String(puntaje) === p ? 'active' : ''}" title="${esc(ESCALA[p])}">${p}</button>`
              ).join('')}
            </div>
            <input type="text" class="ed-crit-obs" placeholder="Observaciones (opcional)" value="${esc(obs)}">
          </div>`;
      }).join('');
      return `<div class="ed-cat-group"><div class="ed-cat-title">${esc(catKey)}. ${esc(cat.label)}</div>${rows}</div>`;
    }).join('');
    updateResumen();
  }
  function readChecklist() {
    return Array.from($('edChecklist').querySelectorAll('.ed-crit-row')).map(row => {
      const active = row.querySelector('.ed-score-toggle button.active');
      return {
        categoria: row.dataset.cat,
        item: row.dataset.item,
        puntaje: active ? Number(active.dataset.p) : null,
        observaciones: row.querySelector('.ed-crit-obs').value.trim(),
      };
    });
  }
  function scoresPorCategoria(criterios) {
    const out = {};
    CAT_KEYS.forEach(k => { out[k] = { score: 0, max: CRITERIOS[k].items.length * MAX_ITEM }; });
    (criterios || []).forEach(c => {
      if (c.puntaje != null && out[c.categoria]) out[c.categoria].score += Number(c.puntaje);
    });
    return out;
  }

  // ─── Resumen de puntuación: se reconstruye junto con el checklist ───
  function buildResumen(comentarios) {
    const com = comentarios || {};
    $('edResumen').innerHTML = CAT_KEYS.map(k => {
      const cat = CRITERIOS[k];
      const max = cat.items.length * MAX_ITEM;
      return `
        <div class="ed-resumen-row" data-cat="${esc(k)}">
          <div class="ed-resumen-cat">${esc(k)}. ${esc(cat.label)}</div>
          <div>
            <div class="ed-resumen-score" id="resScore-${esc(k)}">0</div>
            <div class="ed-resumen-max">de ${max}</div>
          </div>
          <textarea class="ed-resumen-obs" data-cat-comment="${esc(k)}" placeholder="Comentarios del evaluador…">${esc(com[k] || '')}</textarea>
        </div>`;
    }).join('');
    updateResumen();
  }
  function updateResumen() {
    const criterios = readChecklist();
    const scores = scoresPorCategoria(criterios);
    let total = 0;
    CAT_KEYS.forEach(k => {
      const el = $('resScore-' + k);
      if (el) el.textContent = scores[k].score;
      total += scores[k].score;
    });
    $('edTotalScore').textContent = `${total} / ${MAX_TOTAL}`;
    const railEl = $('railCount');
    if (railEl) {
      railEl.textContent = total;
      railEl.classList.toggle('low', MAX_TOTAL > 0 && total < MAX_TOTAL * 0.6);
    }
    $('railMax').textContent = `de ${MAX_TOTAL} puntos posibles`;
  }
  function readComentariosCategoria() {
    const out = {};
    $('edResumen').querySelectorAll('[data-cat-comment]').forEach(el => {
      out[el.dataset.catComment] = el.value.trim();
    });
    return out;
  }

  // ─── KPIs ───
  function totalDe(ev) {
    if (typeof ev.puntaje_total === 'number') return ev.puntaje_total;
    return (ev.criterios || []).reduce((s, c) => s + (c.puntaje || 0), 0);
  }
  function renderKpis() {
    $('kpiTotal').textContent = evaluaciones.length;
    const n = evaluaciones.length;
    const sumTotal = evaluaciones.reduce((s, ev) => s + totalDe(ev), 0);
    $('kpiProm').textContent = n ? (sumTotal / n).toFixed(1) : '0';
    let optimo = 0, evaluados = 0;
    evaluaciones.forEach(ev => (ev.criterios || []).forEach(c => {
      if (c.puntaje != null) { evaluados++; if (c.puntaje >= 4) optimo++; }
    }));
    $('kpiPct').textContent = evaluados ? Math.round((optimo / evaluados) * 100) + '%' : '—';
    const now = new Date();
    const ym = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0');
    $('kpiMes').textContent = evaluaciones.filter(ev => String(ev.fecha || '').startsWith(ym)).length;
  }

  // ─── Tabla ───
  function render() {
    const q = query.trim().toLowerCase();
    const list = evaluaciones.filter(ev => {
      const bajos = bajosDe(ev);
      const total = totalDe(ev);
      if (filtro === 'alto' && (MAX_TOTAL === 0 || total < MAX_TOTAL * 0.8)) return false;
      if (filtro === 'bajo' && bajos === 0) return false;
      if (!q) return true;
      return [ev.colaborador_nombre, ev.colaborador_cargo, ev.actividad, ev.evaluador]
        .some(v => String(v ?? '').toLowerCase().includes(q));
    });
    const tb = $('edTbody');
    tb.innerHTML = '';
    if (!list.length) {
      tb.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--co-faint)">Sin evaluaciones.</td></tr>`;
      return;
    }
    list.forEach(ev => {
      const total = totalDe(ev);
      const pct = MAX_TOTAL ? total / MAX_TOTAL : 0;
      const color = pct >= 0.8 ? '#12B76A' : (pct >= 0.6 ? '#eab308' : '#dc2626');
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><div class="ed-name">${esc(ev.colaborador_nombre)}</div><div class="ed-sub">${esc(ev.colaborador_cargo || '')}</div></td>
        <td>${esc(ev.actividad || '—')}</td>
        <td><span class="ed-turno-chip">${esc(turnoLabel(ev.turno))}</span></td>
        <td>${esc(ev.evaluador)}</td>
        <td>${fmtFecha(ev.fecha)}</td>
        <td><span class="ed-badge" style="background:${color}"><span class="dot"></span>${total} / ${MAX_TOTAL}</span></td>
        <td>
          <div class="ed-cell-actions">
            <button class="ed-act-btn" data-action="view" data-id="${ev.id}">Ver</button>
            <button class="ed-act-btn" data-action="edit" data-id="${ev.id}">Editar</button>
            <button class="ed-act-btn" data-action="pdf" data-id="${ev.id}">PDF</button>
            <button class="ed-act-btn danger" data-action="del" data-id="${ev.id}">Eliminar</button>
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
    const sel = $('im-eval');
    sel.innerHTML = `<option value="">Selecciona…</option>` +
      colaboradores.map(c => `<option value="${c.id}">${esc(c.nombre)} (${esc(c.codigo || '')})</option>`).join('');
    wireEvalSearch();
  }

  function wireEvalSearch() {
    const inp   = $('im-eval-search');
    const panel = $('im-eval-panel');
    const sel   = $('im-eval');
    if (!inp || inp._wired) return;
    inp._wired = true;

    function positionPanel() {
      const r = inp.getBoundingClientRect();
      const spaceBelow = window.innerHeight - r.bottom - 8;
      const spaceAbove = r.top - 8;
      panel.style.left  = r.left + 'px';
      panel.style.width = Math.max(320, r.width) + 'px';
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
        panel.innerHTML = '<div class="ed-colsel-empty">Sin resultados</div>';
      } else {
        panel.innerHTML = items.slice(0, 80).map(c => {
          const parts = (c.nombre || '').trim().split(' ');
          const ini = (parts[0]?.[0] || '') + (parts[1]?.[0] || '');
          return `<div class="ed-colsel-item" data-id="${c.id}">
            <div class="ed-colsel-avatar">${esc(ini.toUpperCase())}</div>
            <div class="ed-colsel-info">
              <span class="ed-colsel-nm">${esc(c.nombre)}</span>
              <span class="ed-colsel-cd">${esc(c.codigo || '')}${c.funcion_principal ? ' · ' + esc(c.funcion_principal) : ''}</span>
            </div>
          </div>`;
        }).join('');
      }
      positionPanel();
      panel.classList.add('open');
    }

    function closePanel() { panel.classList.remove('open'); }

    function selectEval(id) {
      const c = colaboradores.find(x => String(x.id) === String(id));
      sel.value = id;
      inp.value = c ? c.nombre + ' (' + (c.codigo || '') + ')' : '';
      closePanel();
      syncCargo();
    }

    inp.addEventListener('focus', () => renderPanel(inp.value));
    inp.addEventListener('input', () => renderPanel(inp.value));
    panel.addEventListener('mousedown', e => {
      const it = e.target.closest('.ed-colsel-item');
      if (!it) return;
      e.preventDefault();
      selectEval(it.dataset.id);
    });
    document.addEventListener('mousedown', e => {
      const wrap = $('im-eval-wrap');
      if (wrap && !wrap.contains(e.target) && !panel.contains(e.target)) closePanel();
    }, true);
  }

  async function cargarEvaluaciones() {
    const res = await fetch(`${BASE}/api/get_evaluacion_desempeno.php`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.success) { toast(data.error || 'Error al cargar', 'error'); return; }
    evaluaciones = data.data || [];
    renderKpis(); render();
  }

  // ════════════════ MODAL REGISTRAR / EDITAR ════════════════
  function setTurno(key) {
    $('im-turno').value = key || '';
    document.querySelectorAll('#turnoToggle button').forEach(b =>
      b.classList.toggle('active', b.dataset.turno === key));
  }

  function openModal(id) {
    editingId = id ? Number(id) : null;
    const ev = editingId ? evaluaciones.find(x => Number(x.id) === editingId) : null;
    $('edModalTitle').textContent = ev ? 'Editar evaluación' : 'Registrar evaluación';
    $('railFolio').textContent = ev ? ('N° ' + String(ev.id).padStart(4, '0')) : 'NUEVO';
    $('railEval').textContent = (ev && ev.evaluador) ? ev.evaluador : EVALUADOR;
    $('im-id').value       = ev ? ev.id : '';
    $('im-eval').value     = ev ? (ev.colaborador_id ?? '') : '';
    const _srch = $('im-eval-search');
    if (_srch) {
      if (ev && ev.colaborador_id) {
        const _c = colaboradores.find(x => Number(x.id) === Number(ev.colaborador_id));
        _srch.value = _c ? _c.nombre + ' (' + (_c.codigo || '') + ')' : '';
      } else { _srch.value = ''; }
    }
    $('im-actividad').value    = ev ? (ev.actividad ?? '') : '';
    $('im-fecha').value        = ev ? (ev.fecha ?? '') : new Date().toISOString().slice(0,10);
    $('im-comentarios').value  = ev ? (ev.comentarios_generales ?? '') : '';
    setTurno(ev ? (ev.turno || '') : '');
    buildChecklist(ev ? ev.criterios : []);
    buildResumen(ev ? ev.comentarios_categoria : {});
    syncCargo();
    $('edModalBack').classList.add('open');
  }
  function closeModal() { $('edModalBack').classList.remove('open'); editingId = null; }

  function syncCargo() {
    const id = Number($('im-eval').value || 0);
    const c = colaboradores.find(x => Number(x.id) === id);
    $('im-cargo').value        = c ? (c.funcion_principal || '') : '';
    $('railName').textContent  = c ? c.nombre : 'Sin evaluado';
    $('railCargo').textContent = c ? (c.funcion_principal || '—') : 'Selecciona el colaborador evaluado';
    $('railAvatar').textContent= c ? initials(c.nombre) : '—';
  }

  async function guardar() {
    const payload = {
      id:                     Number($('im-id').value || 0),
      colaborador_id:         Number($('im-eval').value || 0),
      actividad:              $('im-actividad').value.trim(),
      turno:                  $('im-turno').value,
      fecha:                  $('im-fecha').value,
      criterios:              readChecklist(),
      comentarios_categoria:  readComentariosCategoria(),
      comentarios_generales:  $('im-comentarios').value.trim(),
    };
    if (!payload.colaborador_id) { toast('Selecciona el colaborador evaluado', 'error'); return; }
    if (!payload.turno)          { toast('Selecciona el turno', 'error'); return; }
    if (!payload.fecha)          { toast('Selecciona la fecha', 'error'); return; }

    const btn = $('edModalSave');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res = await fetch(`${BASE}/api/save_evaluacion_desempeno.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (data.success) {
        toast(payload.id ? 'Evaluación actualizada' : 'Evaluación registrada');
        closeModal();
        cargarEvaluaciones();
      } else {
        toast(data.error || 'Error al guardar', 'error');
      }
    } catch (e) {
      toast('Error de red', 'error');
    } finally {
      btn.disabled = false; btn.textContent = 'Guardar evaluación';
    }
  }

  async function eliminar(id) {
    const ev = evaluaciones.find(x => Number(x.id) === Number(id));
    if (!ev) return;
    if (!confirm(`¿Eliminar la evaluación de "${ev.colaborador_nombre}" (${fmtFecha(ev.fecha)})?`)) return;
    try {
      const res = await fetch(`${BASE}/api/delete_evaluacion_desempeno.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id) }),
      });
      const data = await res.json();
      if (data.success) { toast('Evaluación eliminada'); cargarEvaluaciones(); }
      else { toast(data.error || 'Error al eliminar', 'error'); }
    } catch (e) { toast('Error de red', 'error'); }
  }

  // ════════════════ VISTA DETALLE ════════════════
  function openView(id) {
    const ev = evaluaciones.find(x => Number(x.id) === Number(id));
    if (!ev) return;
    const total = totalDe(ev);
    const scores = scoresPorCategoria(ev.criterios);
    const com = ev.comentarios_categoria || {};

    const catBlocks = CAT_KEYS.map(k => {
      const cat = CRITERIOS[k];
      const rows = (ev.criterios || []).filter(c => c.categoria === k).map(c => {
        const obs = c.observaciones || '';
        return `
          <div class="ed-view-crit-row">
            <span class="ed-crit-item">${esc(c.item)}</span>
            <div class="ed-score-toggle">
              ${Object.keys(ESCALA).map(p =>
                `<button type="button" tabindex="-1" class="${String(c.puntaje) === p ? 'active' : ''}" title="${esc(ESCALA[p])}">${p}</button>`
              ).join('')}
            </div>
            <div class="ed-view-crit-obs${obs ? '' : ' is-empty'}">${obs ? esc(obs) : 'Sin observaciones'}</div>
          </div>`;
      }).join('');
      return `
        <div class="ed-view-cat">${esc(k)}. ${esc(cat.label)} — ${scores[k] ? scores[k].score : 0} / ${cat.items.length * MAX_ITEM}</div>
        <div class="ed-view-crit-list">${rows}</div>
        ${com[k] ? `<div class="ed-view-notes"><span class="iv-k">Comentario del evaluador (${esc(k)})</span><span class="iv-v">${esc(com[k])}</span></div>` : ''}
      `;
    }).join('');

    $('edViewBody').innerHTML = `
      <div class="ed-view-layout">
        <div class="ed-view-sidebar">
          <div class="ed-rail-count">${total}</div>
          <span class="ed-rail-lbl" style="color:rgba(255,255,255,.7)">de ${MAX_TOTAL}</span>
          <hr class="iv-divider">
          <div class="iv-stat">
            <span class="iv-stat-k">Actividad</span>
            <span class="iv-stat-v">${esc(ev.actividad || '—')}</span>
          </div>
          <div class="iv-stat">
            <span class="iv-stat-k">Turno</span>
            <span class="iv-stat-v">${esc(turnoLabel(ev.turno))}</span>
          </div>
          <div class="iv-stat">
            <span class="iv-stat-k">Fecha</span>
            <span class="iv-stat-v">${esc(fmtFecha(ev.fecha))}</span>
          </div>
          <div class="iv-eval">
            <span class="iv-stat-k">Evaluador</span>
            <span class="iv-stat-v">${esc(ev.evaluador || '—')}</span>
          </div>
        </div>
        <div class="ed-view-main">
          <div>
            <p class="ed-view-name">${esc(ev.colaborador_nombre)}</p>
            <p class="ed-view-cargo">${esc(ev.colaborador_cargo || '—')}</p>
          </div>
          <hr class="ed-view-divider">
          ${catBlocks}
          <div class="ed-view-notes">
            <span class="iv-k">Comentarios generales del evaluador</span>
            <span class="iv-v">${esc(ev.comentarios_generales || '—')}</span>
          </div>
        </div>
      </div>`;

    $('edViewSub').textContent = `${ev.colaborador_nombre} · ${fmtFecha(ev.fecha)}`;
    $('edViewEdit').dataset.id = ev.id;
    $('edViewPdf').dataset.id = ev.id;
    $('edViewBack').classList.add('open');
  }
  function closeView() { $('edViewBack').classList.remove('open'); }

  // ════════════════ EXPORTAR PDF (cliente, jsPDF + autoTable) ════════════════
  function exportarPDF(id) {
    const ev = evaluaciones.find(x => Number(x.id) === Number(id));
    if (!ev) return;
    if (!window.jspdf) { toast('No se pudo cargar el generador de PDF', 'error'); return; }
    try {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'portrait' });
      const W = doc.internal.pageSize.getWidth();
      const H = doc.internal.pageSize.getHeight();
      const NAVY = [15, 76, 129]; // azul marino corporativo
      const M = 40;
      let y = 40;

      // ── Encabezado: logo + banner institucional (junto al logo) ──
      const LOGO_W = 66, LOGO_H = 40, BANNER_H = 46;
      const bannerX = M + LOGO_W + 12;
      const bannerW = W - M - bannerX;
      doc.setFillColor(...NAVY);
      doc.rect(bannerX, y, bannerW, BANNER_H, 'F');
      doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(255, 255, 255);
      doc.text('COSCO SHIPPING PORTS CHANCAY PERU S.A.', bannerX + bannerW / 2, y + 15, { align: 'center' });
      doc.setFont('helvetica', 'bold'); doc.setFontSize(9.5);
      doc.text('FORMATO DE EVALUACIÓN DE DESEMPEÑO', bannerX + bannerW / 2, y + 27, { align: 'center' });
      doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5);
      doc.text('Cargo: Tallyman | Área: Operaciones', bannerX + bannerW / 2, y + 38, { align: 'center' });
      if (LOGO_B64) { try { doc.addImage(LOGO_B64, 'PNG', M, y + (BANNER_H - LOGO_H) / 2, LOGO_W, LOGO_H); } catch (e) {} }
      y += BANNER_H + 14;

      // ── Barra de título ──
      doc.setFillColor(...NAVY);
      doc.rect(M, y, W - M * 2, 22, 'F');
      doc.setFont('helvetica', 'bold'); doc.setFontSize(10.5); doc.setTextColor(255, 255, 255);
      doc.text('I. DATOS DE IDENTIFICACIÓN', M + 8, y + 15);
      y += 22;

      // ── I. Datos de identificación ──
      doc.autoTable({
        startY: y,
        theme: 'grid',
        margin: { left: M, right: M },
        styles: { fontSize: 9, cellPadding: 5, textColor: [17, 24, 39] },
        columnStyles: {
          0: { fontStyle: 'bold', fillColor: [219, 234, 254], cellWidth: 110 },
          2: { fontStyle: 'bold', fillColor: [219, 234, 254], cellWidth: 110 },
        },
        body: [
          ['Apellidos y Nombres', { content: ev.colaborador_nombre + (ev.colaborador_cargo ? ' · ' + ev.colaborador_cargo : ''), colSpan: 3 }],
          ['Actividad', ev.actividad || '—', 'Turno', turnoLabel(ev.turno)],
          ['Evaluado por', ev.evaluador || '—', 'Fecha', fmtFecha(ev.fecha)],
        ],
      });
      y = doc.lastAutoTable.finalY + 12;

      // ── II. Escala de calificación ──
      doc.autoTable({
        startY: y,
        head: [Object.keys(ESCALA)],
        body: [Object.values(ESCALA)],
        theme: 'grid',
        margin: { left: M, right: M },
        styles: { fontSize: 8, cellPadding: 5, halign: 'center' },
        headStyles: { fillColor: NAVY, textColor: 255, fontStyle: 'bold' },
        bodyStyles: { fillColor: [219, 234, 254], textColor: [17, 24, 39], fontStyle: 'bold' },
      });
      y = doc.lastAutoTable.finalY + 14;

      // ── III. Criterios de evaluación (agrupados por categoría) ──
      const body = [];
      CAT_KEYS.forEach(k => {
        const cat = CRITERIOS[k];
        body.push([{ content: `${k}. ${cat.label}`, colSpan: 3, styles: { fillColor: NAVY, textColor: 255, fontStyle: 'bold' } }]);
        (ev.criterios || []).filter(c => c.categoria === k).forEach(c => {
          body.push([
            c.item,
            c.puntaje != null ? `${c.puntaje} - ${ESCALA[c.puntaje] || ''}` : '—',
            c.observaciones || '—',
          ]);
        });
      });
      doc.autoTable({
        startY: y,
        head: [['Criterio de Evaluación', 'Puntaje', 'Observaciones']],
        body,
        theme: 'grid',
        showHead: 'firstPage',
        margin: { left: M, right: M },
        styles: { fontSize: 9.5, cellPadding: 14, minCellHeight: 34, valign: 'middle' },
        headStyles: { fillColor: NAVY, textColor: 255, fontStyle: 'bold', minCellHeight: 22 },
        columnStyles: { 1: { cellWidth: 90 } },
        didParseCell: (data) => {
          if (data.section === 'body' && data.column.index === 1 && !data.cell.raw.toString().includes('.')) {
            const v = String(data.cell.raw);
            if (v.startsWith('1') || v.startsWith('2')) data.cell.styles.textColor = [220, 38, 38];
            else if (v.startsWith('3')) data.cell.styles.textColor = [217, 119, 6];
            else if (v.startsWith('4') || v.startsWith('5')) data.cell.styles.textColor = [18, 183, 106];
          }
        },
      });
      y = doc.lastAutoTable.finalY + 14;

      // ── IV. Resumen de puntuación ──
      const scores = scoresPorCategoria(ev.criterios);
      const com = ev.comentarios_categoria || {};
      const total = totalDe(ev);
      if (y > H - 140) { doc.addPage(); y = 40; }
      doc.autoTable({
        startY: y,
        head: [['Resumen de Puntuación', 'Puntaje Obtenido', 'Comentarios del Evaluador']],
        body: [
          ...CAT_KEYS.map(k => [`${k}. ${CRITERIOS[k].label}`, `${scores[k].score} / ${scores[k].max}`, com[k] || '—']),
          [{ content: 'PUNTAJE TOTAL', styles: { fontStyle: 'bold', fillColor: [219, 234, 254] } },
           { content: `${total} / ${MAX_TOTAL}`, styles: { fontStyle: 'bold', fillColor: [219, 234, 254] } },
           { content: '', styles: { fillColor: [219, 234, 254] } }],
        ],
        theme: 'grid',
        margin: { left: M, right: M },
        styles: { fontSize: 8.5, cellPadding: 8, valign: 'top' },
        headStyles: { fillColor: NAVY, textColor: 255, fontStyle: 'bold' },
        columnStyles: { 1: { cellWidth: 65, halign: 'center' }, 2: { cellWidth: 220 } },
      });
      y = doc.lastAutoTable.finalY + 14;

      // ── V. Comentarios generales del evaluador ──
      if (y > H - 130) { doc.addPage(); y = 40; }
      doc.setFillColor(...NAVY);
      doc.rect(M, y, W - M * 2, 20, 'F');
      doc.setFont('helvetica', 'bold'); doc.setFontSize(9.5); doc.setTextColor(255, 255, 255);
      doc.text('COMENTARIOS GENERALES DEL EVALUADOR', M + 6, y + 14);
      y += 20;
      const lines = doc.splitTextToSize(ev.comentarios_generales || '—', W - M * 2 - 16);
      const boxH = Math.max(50, lines.length * 12 + 16);
      doc.setDrawColor(203, 213, 225);
      doc.rect(M, y, W - M * 2, boxH);
      doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(55, 65, 81);
      doc.text(lines, M + 8, y + 16);
      y += boxH + 24;

      // ── VI. Firmas (Evaluador / Evaluado) ──
      const FIRMA_H = 90;
      const boxW = W - M * 2;
      if (y > H - FIRMA_H - 90) { doc.addPage(); y = 40; }
      const gap = 12;
      const colW = (boxW - gap) / 2;
      const headH = 20;

      // Columna izquierda: Firma del Evaluador
      doc.setFillColor(219, 234, 254);
      doc.rect(M, y, colW, headH, 'F');
      doc.setDrawColor(203, 213, 225);
      doc.rect(M, y, colW, headH);
      doc.rect(M, y, colW, FIRMA_H);
      doc.setFont('helvetica', 'bold'); doc.setFontSize(9.5); doc.setTextColor(17, 24, 39);
      doc.text('VI. FIRMAS EVALUADOR', M + colW / 2, y + 14, { align: 'center' });

      // Columna derecha: Firma del Evaluado
      const rightX = M + colW + gap;
      doc.setFillColor(219, 234, 254);
      doc.rect(rightX, y, colW, headH, 'F');
      doc.setDrawColor(203, 213, 225);
      doc.rect(rightX, y, colW, headH);
      doc.rect(rightX, y, colW, FIRMA_H);
      doc.setFont('helvetica', 'bold'); doc.setFontSize(9.5); doc.setTextColor(17, 24, 39);
      doc.text('VI. FIRMAS EVALUADO', rightX + colW / 2, y + 14, { align: 'center' });

      y += FIRMA_H + 16;

      // ── Notas ──
      if (y > H - 70) { doc.addPage(); y = 40; }
      doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(17, 24, 39);
      doc.text('Notas:', M, y);
      doc.setLineWidth(0.5);
      doc.line(M, y + 2, M + 32, y + 2);
      y += 14;
      const notas = [
        'La evaluación se realiza durante un turno completo, auditando varias veces el puesto del trabajo o actividad a la que fue asignada el personal.',
        'La evaluación requiere de la presencia del coordinador.',
      ];
      doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor(55, 65, 81);
      notas.forEach(n => {
        const lines = doc.splitTextToSize(n, boxW - 14);
        doc.text('•', M, y);
        doc.text(lines, M + 12, y);
        y += lines.length * 11 + 4;
      });

      const safeName = (ev.colaborador_nombre || 'evaluacion').replace(/[^a-z0-9]+/gi, '_');
      doc.save(`evaluacion_${ev.fecha}_${safeName}.pdf`);
    } catch (e) {
      toast('No se pudo generar el PDF', 'error');
    }
  }

  // ─── Eventos ───
  $('btnNew').addEventListener('click', () => openModal(null));
  $('edSearch').addEventListener('input', e => { query = e.target.value; render(); });
  $('edFilter').addEventListener('click', e => {
    const b = e.target.closest('button[data-f]'); if (!b) return;
    filtro = b.dataset.f;
    $('edFilter').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
    render();
  });
  $('edTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action]'); if (!b) return;
    if (b.dataset.action === 'view') openView(b.dataset.id);
    if (b.dataset.action === 'edit') openModal(b.dataset.id);
    if (b.dataset.action === 'pdf')  exportarPDF(b.dataset.id);
    if (b.dataset.action === 'del')  eliminar(b.dataset.id);
  });

  $('im-eval').addEventListener('change', syncCargo);
  $('turnoToggle').addEventListener('click', e => {
    const b = e.target.closest('[data-turno]'); if (!b) return;
    setTurno(b.dataset.turno);
  });
  $('edChecklist').addEventListener('click', e => {
    const b = e.target.closest('.ed-score-toggle button'); if (!b) return;
    const row = b.closest('.ed-crit-row');
    row.querySelectorAll('.ed-score-toggle button').forEach(x => x.classList.toggle('active', x === b));
    updateResumen();
  });

  $('edModalClose').addEventListener('click', closeModal);
  $('edModalCancel').addEventListener('click', closeModal);
  $('edModalSave').addEventListener('click', guardar);
  $('edModalBack').addEventListener('click', e => { if (e.target === $('edModalBack')) closeModal(); });

  $('edViewClose').addEventListener('click', closeView);
  $('edViewCloseBtn').addEventListener('click', closeView);
  $('edViewBack').addEventListener('click', e => { if (e.target === $('edViewBack')) closeView(); });
  $('edViewEdit').addEventListener('click', e => { closeView(); openModal(e.currentTarget.dataset.id); });
  $('edViewPdf').addEventListener('click', e => exportarPDF(e.currentTarget.dataset.id));

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if ($('edModalBack').classList.contains('open')) closeModal();
    if ($('edViewBack').classList.contains('open')) closeView();
  });

  // ─── Init ───
  initEscalaGrid();
  (async () => {
    try { await cargarColaboradores(); }
    catch (e) { toast('No se pudieron cargar los colaboradores', 'error'); }
    cargarEvaluaciones();
  })();
})();
</script>

</body>
</html>
