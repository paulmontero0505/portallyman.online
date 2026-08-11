<?php
require_once('../includes/auth.php');
require_once('../includes/db.php');
require_once('../includes/reconocimientos_catalogo.php');
require_report();

// Catálogos → JS (única fuente de verdad, definida en includes/reconocimientos_catalogo.php)
$JS_COMPETENCIAS = rec_competencia_concepto();   // competencia => concepto
$JS_DIPLOMAS     = rec_competencia_diploma();     // competencia => texto elaborado del diploma
$JS_ESTADOS      = rec_estados();                // clave => [label,color]
$JS_TURNOS       = inc_turnos();                 // clave => label
$COORDINADOR     = $_SESSION['user_name'] ?? '';
$EMPRESA         = 'COSCO SHIPPING PORTS CHANCAY PERÚ S.A.';
$CIUDAD          = 'Lima';

// Zonas de trabajo activas (tabla ubicaciones). El formulario ofrece exactamente
// las que save_reconocimiento.php acepta.
$zona_ubicaciones = inc_zonas(true);

// Logo embebido para el diploma (logo/logo.png).
$logo_path = __DIR__ . '/../logo/logo.png';
$LOGO_B64  = is_file($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reconocimiento Tally · Estiba Shift Command Deck</title>
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
       INCIDENCIAS · registro + tabla (prefijo .inc-*)
       PREMIUM LIGHT EMERALD THEME
    ════════════════════════════════════════════════════════════════ */
    .inc-wrap {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0, 135, 90, 0.18); --co-line-bold:rgba(0, 135, 90, 0.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      --mono: ui-monospace, Consolas, monospace;
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .inc-wrap *, .inc-wrap *::before, .inc-wrap *::after { box-sizing:border-box; }

    .inc-hero {
      background: linear-gradient(135deg, #005c3d 0%, #00875A 100%) !important;
      color:#fff; border-radius:20px; padding:22px 28px;
      display:flex; align-items:center; justify-content:space-between; gap:18px;
      border: 1px solid rgba(0, 135, 90, 0.2) !important;
      box-shadow: 0 8px 32px rgba(0, 135, 90, 0.08) !important;
    }
    .inc-hero h1 { margin:6px 0 4px; font-size:22px; font-weight:700; letter-spacing:-.01em; }
    .inc-hero p  { margin:0; color:rgba(255,255,255,.85); font-size:13px; max-width:560px; }
    .inc-hero .tag {
      display:inline-flex; align-items:center; gap:8px;
      padding:5px 11px; border-radius:999px;
      background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18);
      font-size:11px; font-weight:600; letter-spacing:.12em; text-transform:uppercase;
      color: #ffffff !important;
    }

    .inc-btn {
      display:inline-flex; align-items:center; gap:7px;
      padding:9px 16px; border-radius:10px; border:1px solid rgba(0, 135, 90, 0.3);
      background:#fff; cursor:pointer; text-decoration:none;
      font-family:inherit; font-size:13px; font-weight:600; color:#00875A;
      transition:all .15s;
    }
    .inc-btn:hover { border-color:var(--co-navy-700); color:var(--co-navy); background: rgba(0, 135, 90, 0.05); }
    body .inc-btn.primary,
    .inc-btn.primary {
      background: linear-gradient(135deg, #00875A 0%, #005c3d 100%) !important;
      color: #fff !important;
      border: none !important;
      font-weight: 700 !important;
      box-shadow: 0 4px 18px rgba(0, 135, 90, 0.2) !important;
      letter-spacing: 0.02em;
      padding: 11px 20px;
      border-radius: 12px;
    }
    body .inc-btn.primary:hover,
    .inc-btn.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(0, 135, 90, 0.35) !important;
      filter: brightness(1.08);
    }
    .inc-btn.primary:active { transform: translateY(0); }
    .inc-btn.ghost-light { background:rgba(255,255,255,.15); color:#fff; border-color:rgba(255,255,255,.25); }
    .inc-btn.ghost-light:hover { background:rgba(255,255,255,.25); }
    .inc-btn svg { width:14px; height:14px; }

    .inc-kpis { display:flex; gap:10px; flex-wrap:wrap; }
    .inc-kpi {
      flex:1; min-width:120px;
      background:#fff; border:1px solid var(--co-line); border-radius:14px;
      padding:14px 18px;
      box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;
    }
    .inc-kpi .lbl { font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--co-mute); }
    .inc-kpi:nth-child(1) .val { color:var(--co-navy-700); }
    .inc-kpi:nth-child(2) .val { color:#F59E0B; }
    .inc-kpi:nth-child(3) .val { color:#12B76A; }
    .inc-kpi:nth-child(4) .val { color:#3b82f6; }
    .inc-kpi .val { font-size:22px; font-weight:700; margin-top:4px; }

    .inc-toolbar {
      display:flex; gap:10px; align-items:center; flex-wrap:wrap;
      background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:10px 12px;
      box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;
    }
    .inc-search {
      flex:1; min-width:220px; display:flex; align-items:center; gap:8px;
      background:var(--co-deck); border:1px solid rgba(0, 135, 90, 0.15); border-radius:10px; padding:8px 12px;
    }
    .inc-search:focus-within { border-color:var(--co-navy-700); background:#fff; box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.15); }
    .inc-search input { flex:1; border:0; outline:0; background:transparent; font:inherit; font-size:13.5px; color:var(--co-ink); }
    .inc-search input::placeholder { color: var(--co-faint); opacity: 0.9; }
    .inc-search svg { width:15px; height:15px; color:var(--co-mute); }
    .inc-filter { display:flex; gap:4px; background:#f3f4f6; border-radius:10px; padding:3px; flex-wrap:wrap; border: 1px solid #e5e7eb; }
    .inc-filter button {
      padding:6px 12px; border:0; background:transparent; border-radius:7px;
      font:inherit; font-size:12px; font-weight:600; color:var(--co-mute); cursor:pointer;
    }
    .inc-filter button.active { background:#fff; color:var(--co-navy-700); box-shadow:0 1px 3px rgba(0,0,0,.06); border: 1px solid rgba(0, 135, 90, 0.2); }

    .inc-table-wrap { background:#fff; border:1px solid var(--co-line); border-radius:14px; overflow:auto; box-shadow: 0 4px 16px rgba(0,0,0,.02) !important; }
    .inc-table { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
    .inc-table thead tr { background: rgba(0, 135, 90, 0.04) !important; border-bottom:1px solid var(--co-line); }
    .inc-table th {
      padding:11px 14px; text-align:left;
      font-size:10.5px; letter-spacing:.1em; text-transform:uppercase;
      color:var(--co-navy); font-weight:700;
    }
    .inc-table tbody tr { border-bottom:1px solid rgba(0, 135, 90, 0.06); transition:background .12s; }
    .inc-table tbody tr:last-child { border-bottom:0; }
    .inc-table tbody tr:hover { background: rgba(0, 135, 90, 0.02); }
    .inc-table td { padding:11px 14px; vertical-align:middle; color: var(--co-ink) !important; }
    .inc-name { font-weight:600; color:var(--co-ink); }
    .inc-sub  { font-size:11px; color:var(--co-faint); }

    .inc-badge {
      display:inline-flex; align-items:center; gap:5px;
      padding:3px 9px; border-radius:999px;
      font-size:10.5px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
      color:#fff;
    }
    .inc-badge .dot { width:6px; height:6px; border-radius:50%; background:rgba(255,255,255,.85); }
    .inc-turno-chip {
      display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:6px;
      font-size:11px; font-weight:600; background:rgba(0, 135, 90, 0.06); color:var(--co-navy);
    }

    .inc-act-btn {
      padding:5px 10px; border-radius:7px; border:1px solid rgba(0, 135, 90, 0.25);
      background:rgba(0, 135, 90, 0.05); cursor:pointer; font:inherit; font-size:12px; font-weight:600; color:#00875A;
      transition:all .12s;
    }
    .inc-act-btn:hover { border-color:var(--co-navy-700); color:var(--co-navy-700); background:#00875A; color:#ffffff; }
    .inc-act-btn.danger { color: var(--co-red); border-color: rgba(239, 68, 68, 0.25); background: rgba(239, 68, 68, 0.05); }
    .inc-act-btn.danger:hover { border-color:var(--co-red); color:#ffffff; background: var(--co-red); }
    .inc-cell-actions { display:flex; gap:6px; align-items:center; justify-content:flex-end; }

    /* ── Modales ── */
    .inc-modal-back {
      position:fixed; inset:0; background:rgba(0, 0, 0, 0.3);
      display:grid; place-items:center; z-index:995;
      opacity:0; pointer-events:none; transition:opacity .2s;
      backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    }
    .inc-modal-back.open { opacity:1; pointer-events:auto; }
    .inc-modal {
      background:#fff; border-radius:18px; width:620px; max-width:94vw;
      box-shadow: 0 24px 64px rgba(0, 135, 90, 0.12);
      transform:translateY(12px) scale(.97); transition:transform .22s cubic-bezier(.25,.46,.45,.94);
      max-height:92vh; display:flex; flex-direction:column; overflow:hidden;
      border: 1px solid var(--co-line);
    }
    .inc-modal-back.open .inc-modal { transform:translateY(0) scale(1); }
    .inc-modal-head {
      padding:18px 20px 14px; border-bottom:1px solid rgba(0, 135, 90, 0.08);
      display:flex; align-items:center; justify-content:space-between;
    }
    .inc-modal-head h3 { margin:0; font-size:17px; font-weight:700; color: var(--co-ink); }
    .inc-modal-head .sub { font-size:12px; color:var(--co-mute); margin-top:2px; }
    .inc-modal-close {
      width:32px; height:32px; border-radius:8px; border:1px solid #d1d5db;
      background:#fff; cursor:pointer; display:grid; place-items:center; color:var(--co-mute);
    }
    .inc-modal-close:hover { color:var(--co-red); border-color:var(--co-red); }
    .inc-modal-body { padding:18px 20px; display:flex; flex-direction:column; gap:12px; overflow-y:auto; flex:1; background: #ffffff; }
    .inc-modal-foot { padding:14px 20px; border-top:1px solid rgba(0, 135, 90, 0.08); display:flex; justify-content:flex-end; gap:8px; background: #ffffff; }
    .inc-row2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .inc-field { display:flex; flex-direction:column; gap:5px; }
    .inc-field label {
      font-size:11px; font-weight:700; color:#374151; letter-spacing:.05em; text-transform:uppercase;
    }
    .inc-field input, .inc-field select, .inc-field textarea {
      font:inherit; font-size:13.5px; color:#111827;
      background:#ffffff; border:1.5px solid #cbd5e1; border-radius:8px;
      padding:9px 11px; outline:0; transition:border-color .15s, box-shadow .15s;
    }
    .inc-field input::placeholder, .inc-field textarea::placeholder { color:#94a3b8; }
    .inc-field select option { color:#111827; background:#ffffff; }
    .inc-field textarea { resize:vertical; min-height:70px; }
    .inc-field input:focus, .inc-field select:focus, .inc-field textarea:focus {
      border-color:#00875A; background:#fff; box-shadow:0 0 0 3px rgba(0, 135, 90, 0.15);
    }
    .inc-field input[readonly] { background:#f3f4f6; color:#4b5563; cursor:default; border-color: #e5e7eb; }
    .inc-colsel { position:relative; }
    .inc-colsel-panel {
      display:none; position:fixed; z-index:9000;
      min-width:320px; max-width:420px;
      background:#fff; border:1px solid rgba(0, 135, 90, 0.25); border-radius:11px;
      box-shadow:0 16px 40px rgba(0,0,0,.08); max-height:240px; overflow-y:auto;
      padding:5px;
    }
    .inc-colsel-panel.open { display:block; }
    .inc-colsel-item {
      display:flex; align-items:center; gap:10px;
      padding:9px 11px; border-radius:8px; cursor:pointer;
    }
    .inc-colsel-item:hover { background:rgba(0, 135, 90, 0.05); }
    .inc-colsel-avatar {
      width:30px; height:30px; border-radius:8px; flex-shrink:0;
      background:rgba(0, 135, 90, 0.08); color:var(--co-navy); font-size:10px; font-weight:800;
      display:flex; align-items:center; justify-content:center; letter-spacing:-.3px;
    }
    .inc-colsel-info { display:flex; flex-direction:column; gap:1px; min-width:0; }
    .inc-colsel-nm { font-size:13px; font-weight:600; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .inc-colsel-cd { font-size:11px; color:#4b5563; }
    .inc-colsel-empty { padding:12px; font-size:12px; color:#4b5563; text-align:center; }

    .inc-drop {
      border:2px dashed rgba(0, 135, 90, 0.25); border-radius:12px; padding:18px; text-align:center;
      cursor:pointer; color:#4b5563; transition:all .15s; background:#f9fafb; font-size:12.5px;
    }
    .inc-drop:hover { border-color:var(--co-navy-700); background:rgba(0, 135, 90, 0.02); color:var(--co-navy-700); }
    .inc-file-info {
      display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:6px;
      font-size:12px; color:var(--co-ink);
    }
    .inc-file-info a { color:var(--co-navy-700); font-weight:600; text-decoration:none; }
    .inc-file-info a:hover { text-decoration: underline; }
    .inc-file-info .rm { color:var(--co-red); cursor:pointer; font-weight:600; background:none; border:0; font:inherit; }
    .inc-thumb { max-width:100%; max-height:240px; border-radius:10px; border:1px solid var(--co-line); }

    /* ── Vista detalle C2 ── */
    .inc-modal--view .inc-modal-head { display:none; }
    .inc-modal--view .inc-modal-body { padding:0; overflow:hidden; display:block; }
    .inc-modal--view #incViewEdit { background:linear-gradient(135deg,#00875A,#005c3d); border-color:transparent; }
    .inc-modal--view #incViewEdit:hover { background:linear-gradient(135deg,#00b377,#00875A); }

    .inc-view-layout { display:grid; grid-template-columns:105px 1fr; max-height:72vh; }
    .inc-view-sidebar {
      background:linear-gradient(160deg,#005c3d 0%,#00875A 100%);
      padding:20px 12px; color:#fff;
      display:flex; flex-direction:column; gap:12px; align-items:center; overflow-y:auto;
    }
    .inc-view-sidebar .iv-badge {
      display:inline-flex; align-items:center; gap:4px; color:#fff;
      font-size:10px; font-weight:800; padding:4px 10px; border-radius:20px;
      letter-spacing:.3px; white-space:nowrap; max-width:100%;
    }
    .inc-view-sidebar .iv-divider { width:100%; border:none; border-top:1px solid rgba(255,255,255,.2); margin:0; }
    .iv-stat { text-align:center; width:100%; }
    .iv-stat-k { font-size:8px; opacity:.75; text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:2px; }
    .iv-stat-v { font-size:11px; font-weight:700; }
    .inc-view-sidebar .iv-coord { text-align:center; margin-top:auto; padding-top:12px; border-top:1px solid rgba(255,255,255,.2); width:100%; }
    .inc-view-sidebar .iv-coord .iv-stat-k { opacity:.75; }
    .inc-view-sidebar .iv-coord .iv-stat-v { font-size:10px; font-weight:600; }

    .inc-view-main { padding:18px 16px; display:flex; flex-direction:column; gap:10px; overflow-y:auto; background:#fff; }
    .inc-view-name { font-size:14px; font-weight:800; color:#005c3d; line-height:1.3; margin:0; }
    .inc-view-cargo { font-size:11px; color:var(--co-mute); margin-top:3px; font-weight:500; }
    .inc-view-divider { border:none; border-top:1px solid var(--co-line); margin:2px 0; }
    .inc-view-field { display:flex; flex-direction:column; gap:2px; }
    .iv-k { font-size:9px; color:var(--co-mute); text-transform:uppercase; letter-spacing:.5px; font-weight:600; display:block; }
    .iv-v { font-size:13px; font-weight:700; color:#111827; }
    .inc-view-field--blue { border-left:3px solid #3b82f6; padding-left:10px; }
    .inc-view-field--purple { border-left:3px solid #7c3aed; padding-left:10px; }
    .inc-view-detalle { background:#f9fafb; border:1px solid var(--co-line); border-radius:8px; padding:10px 12px; }
    .inc-view-detalle .iv-v { font-size:13px; color:#4b5563; font-weight:400; line-height:1.5; }
    .inc-view-attachments { display:flex; gap:8px; }
    .inc-view-attach {
      flex:1; border-radius:8px; padding:9px 6px; text-align:center;
      font-size:11px; font-weight:600; text-decoration:none; display:block; transition:filter .15s;
    }
    .inc-view-attach:hover { filter:brightness(.93); }
    .inc-view-attach--foto { background:rgba(0, 135, 90, 0.06); color:#00875A; }
    .inc-view-attach--foto:hover { background:rgba(0, 135, 90, 0.1); }
    .inc-view-attach--decl { background:#f5f3ff; color:#6d28d9; }
    .inc-view-attach--drive { background:#eaf1fb; color:#1a56db; }
    .inc-view-attach--drive:hover { background:#dbe7fa; }
    .inc-view-attachments { flex-wrap:wrap; }
    .inc-thumb { max-width:100%; border-radius:8px; border:1px solid var(--co-line); margin-top:6px; }

    .inc-toast {
      position:fixed; bottom:24px; right:24px; z-index:999;
      background:#111827; color:#fff; padding:12px 18px; border-radius:10px;
      font-size:13px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,.1);
      transform:translateY(120%); opacity:0; transition:all .25s;
      border: 1px solid rgba(255,255,255,0.1);
    }
    .inc-toast.show { transform:translateY(0); opacity:1; }
    .inc-toast.is-error { background:#dc2626; border-color: #ef4444; }

    .content { padding:24px 28px 60px; overflow-y:auto; }

    /* ════════════════════════════════════════════════════════════════
       FORMULARIO DE INCIDENCIA · rediseño "parte / command console"
       (los modales viven fuera de .inc-wrap → redeclaramos las vars aquí)
    ════════════════════════════════════════════════════════════════ */
    #incModalBack, #incViewBack {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-navy-900:#001226; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0, 135, 90, 0.18); --co-line-bold:rgba(0, 135, 90, 0.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      --mono: ui-monospace, "SFMono-Regular", Consolas, monospace;
      font-family:'DM Sans', system-ui, sans-serif;
    }

    /* Modal de creación: dos columnas, más ancho, sin padding propio */
    .inc-modal.inc-create {
      width:880px; max-width:96vw; padding:0;
      flex-direction:row; align-items:stretch; max-height:92vh;
    }
    .inc-create *, .inc-create *::before, .inc-create *::after { box-sizing:border-box; }

    /* ── Rail izquierdo (dossier) ── */
    .inc-rail {
      position:relative; flex:0 0 264px; width:264px; color:var(--co-ink);
      padding:24px 22px; overflow:hidden;
      display:flex; flex-direction:column; gap:22px;
      background:#f5f8f7; border-right:1px solid var(--co-line);
    }
    .inc-rail::before { display:none; }
    .inc-rail > * { position:relative; z-index:1; }
    .inc-rail-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .inc-rail-kicker { font-family:var(--mono); font-size:9.5px; letter-spacing:.22em; color:var(--co-mute); }
    .inc-rail-folio {
      font-family:var(--mono); font-size:10px; letter-spacing:.06em;
      padding:3px 9px; border-radius:999px; background:rgba(0, 135, 90, 0.08);
      border:1px solid rgba(0, 135, 90, 0.2); color:var(--co-navy); white-space:nowrap;
    }
    .inc-rail-lbl {
      display:block; font-family:var(--mono); font-size:9px; letter-spacing:.18em;
      text-transform:uppercase; color:var(--co-faint); margin-bottom:8px;
    }
    .inc-rail-id { display:flex; align-items:center; gap:12px; }
    .inc-rail-avatar {
      width:46px; height:46px; border-radius:13px; flex-shrink:0;
      background: linear-gradient(135deg, #00875A 0%, #00b377 100%); color:#ffffff;
      display:grid; place-items:center; font-size:16px; font-weight:700;
      box-shadow: 0 2px 8px -4px rgba(0, 135, 90, 0.2);
    }
    .inc-rail-name  { font-size:15px; font-weight:700; line-height:1.2; overflow:hidden; text-overflow:ellipsis; color:var(--co-ink); }
    .inc-rail-cargo { font-size:11.5px; color:var(--co-mute); margin-top:2px; }
    .inc-rail-gauge { display:flex; gap:5px; }
    .inc-rail-gauge span {
      flex:1; height:8px; border-radius:3px; background:rgba(0, 135, 90, 0.08);
      transition:background .3s ease, box-shadow .3s ease;
    }
    .inc-rail-sevname { margin-top:10px; font-size:13px; font-weight:700; letter-spacing:.05em; color:var(--co-mute); transition:color .25s; }
    .inc-rail-comp-val { font-size:13px; font-weight:600; line-height:1.4; color:var(--co-navy); padding-left:11px; border-left:2px solid #00875A; }
    .inc-rail-foot { margin-top:auto; padding-top:16px; border-top:1px solid var(--co-line); }
    .inc-rail-coord { font-size:13.5px; font-weight:600; color:var(--co-ink); }

    /* ── Columna derecha (form) ── */
    .inc-form { flex:1; min-width:0; display:flex; flex-direction:column; background:#fff; }
    .inc-form-head {
      padding:20px 24px 16px; border-bottom:1px solid var(--co-line);
      display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
    }
    .inc-form-head h3 { margin:0; font-size:18px; font-weight:700; color:var(--co-ink); letter-spacing:-.01em; }
    .inc-form-head .sub { font-size:12px; color:var(--co-mute); margin-top:3px; }
    .inc-form-body { padding:6px 24px 18px; overflow-y:auto; flex:1; }

    .inc-sec { padding:15px 0; border-bottom:1px dashed var(--co-line); }
    .inc-sec:last-child { border-bottom:0; }
    .inc-sec-head { display:flex; align-items:center; gap:9px; margin-bottom:12px; }
    .inc-sec-num {
      font-family:var(--mono); font-size:10px; font-weight:700; color:var(--co-navy);
      background:var(--co-deck); border:1px solid rgba(0, 135, 90, 0.25);
      padding:2px 6px; border-radius:6px; letter-spacing:.05em;
    }
    .inc-sec-head > span:last-child { font-size:11.5px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--co-mute); }

    /* severidad: selector segmentado */
    .inc-sev-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
    .inc-sev-opt {
      position:relative; cursor:pointer; border:1.5px solid var(--co-line); background:#fff;
      border-radius:11px; padding:10px 6px 9px; text-align:center; transition:transform .16s, border-color .16s, box-shadow .16s; font:inherit;
    }
    .inc-sev-opt:hover { transform:translateY(-2px); border-color:var(--co-faint); }
    .inc-sev-swatch { width:100%; height:6px; border-radius:3px; margin-bottom:8px; opacity:.35; transition:opacity .16s; }
    .inc-sev-opt .inc-sev-name { font-size:11px; font-weight:700; color:var(--co-mute); }
    .inc-sev-opt.active { border-color:transparent; box-shadow:0 0 0 2px var(--sev), 0 4px 12px var(--sev); transform:translateY(-2px); }
    .inc-sev-opt.active .inc-sev-swatch { opacity:1; }
    .inc-sev-opt.active .inc-sev-name { color:var(--co-ink); }

    /* turno: toggle día/noche */
    .inc-turno-toggle { display:grid; grid-template-columns:1fr 1fr; gap:6px; background:#f3f4f6; border-radius:10px; padding:4px; border: 1px solid #e5e7eb; }
    .inc-turno-toggle button {
      border:0; background:transparent; border-radius:8px; padding:9px; cursor:pointer;
      font:inherit; font-size:13px; font-weight:600; color:var(--co-mute);
      display:flex; align-items:center; justify-content:center; gap:7px; transition:all .15s;
    }
    .inc-turno-toggle button svg { width:15px; height:15px; }
    .inc-turno-toggle button:hover { color:var(--co-navy); }
    .inc-turno-toggle button.active { background:#fff; color:var(--co-navy-700); box-shadow:0 1px 4px rgba(0,0,0,.08); border: 1px solid rgba(0, 135, 90, 0.2); }
    .inc-turno-toggle button[data-turno="noche"].active { background:#111827; color:#ffffff; border-color: #111827; }

    /* drop zones más expresivas */
    .inc-create .inc-drop {
      display:flex; align-items:center; gap:11px; text-align:left; padding:13px 14px;
    }
    .inc-create .inc-drop svg { flex-shrink:0; opacity:.75; }
    .inc-create .inc-drop small { color:var(--co-faint); font-size:10.5px; }

    .inc-form-foot { padding:14px 24px; border-top:1px solid var(--co-line); display:flex; justify-content:flex-end; gap:8px; }

    /* entrada escalonada al abrir */
    @keyframes incSecIn { from { opacity:0; transform:translateY(9px); } to { opacity:1; transform:none; } }
    @keyframes incRailIn { from { opacity:0; transform:translateX(-14px); } to { opacity:1; transform:none; } }
    .inc-modal-back.open .inc-rail { animation:incRailIn .5s both cubic-bezier(.22,.61,.36,1); }
    .inc-modal-back.open .inc-form-body section { animation:incSecIn .45s both cubic-bezier(.22,.61,.36,1); }
    .inc-modal-back.open .inc-form-body section:nth-of-type(1){ animation-delay:.06s; }
    .inc-modal-back.open .inc-form-body section:nth-of-type(2){ animation-delay:.11s; }
    .inc-modal-back.open .inc-form-body section:nth-of-type(3){ animation-delay:.16s; }
    .inc-modal-back.open .inc-form-body section:nth-of-type(4){ animation-delay:.21s; }
    .inc-modal-back.open .inc-form-body section:nth-of-type(5){ animation-delay:.26s; }
    .inc-modal-back.open .inc-form-body section:nth-of-type(6){ animation-delay:.31s; }
    .inc-modal-back.open .inc-form-body section:nth-of-type(7){ animation-delay:.36s; }

    @media (max-width:760px) {
      .inc-modal.inc-create { flex-direction:column; width:96vw; }
      .inc-rail { flex:0 0 auto; width:100%; }
      .inc-rail-foot { margin-top:14px; }
      .inc-sev-grid { grid-template-columns:repeat(3,1fr); gap:5px; }
    }

    /* ══════════════════════════════
       MOBILE · Incidencias
       ══════════════════════════════ */
    @media (max-width: 600px) {

      /* Modal: full screen con scroll propio */
      .inc-modal, .inc-modal.inc-create {
        width: calc(100vw - 12px) !important;
        max-width: 100% !important;
        height: 95dvh !important;
        max-height: 95dvh !important;
        border-radius: 14px !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
      }

      /* Rail: altura natural, sin scroll */
      .inc-modal.inc-create .inc-rail {
        flex: 0 0 auto !important;
        overflow: visible !important;
      }

      /* Form: ocupa el espacio restante y scrollea */
      .inc-modal.inc-create .inc-form {
        flex: 1 1 0 !important;
        min-height: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
      }

      /* Body del form: scroll vertical activado */
      .inc-form-body {
        flex: 1 1 0 !important;
        min-height: 0 !important;
        overflow-y: auto !important;
        overscroll-behavior: contain !important;
      }

      /* Rail: compacto sin tanto espacio */
      .inc-rail { padding: 14px 16px !important; gap: 8px !important; }
      .inc-rail-name  { font-size: 14px !important; }
      .inc-rail-cargo { font-size: 11px !important; }
      .inc-rail-lbl   { font-size: 8px !important; margin-bottom: 2px !important; }
      .inc-rail-id    { gap: 8px !important; }
      .inc-rail-coord { font-size: 12px !important; }
      .inc-rail-competencia { font-size: 12px !important; }

      /* Form body */
      .inc-form-body  { padding: 12px 14px 16px !important; }
      .inc-form-head  { padding: 14px 14px 10px !important; }
      .inc-form-head h3 { font-size: 16px !important; }
      .inc-form-foot  { padding: 12px 14px !important; }

      /* ─── Campos: 1 columna siempre ─── */
      .inc-row2 { grid-template-columns: 1fr !important; }
      .inc-sec  { padding: 12px 0 !important; }

      /* Labels más oscuros y legibles */
      .inc-field label {
        color: #111827 !important;
        font-size: 10.5px !important;
      }

      /* Inputs más grandes para touch */
      .inc-field input,
      .inc-field select,
      .inc-field textarea {
        font-size: 15px !important;
        padding: 11px 12px !important;
        color: #111827 !important;
        background: #fff !important;
        border: 1.5px solid #cbd5e1 !important;
      }
      .inc-field input:focus,
      .inc-field select:focus,
      .inc-field textarea:focus {
        border-color: #00875A !important;
        box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.15) !important;
      }

      /* Search de colaborador: ancho completo */
      .inc-colsel-panel { min-width: 90vw !important; max-width: 94vw !important; }

      /* Sección números */
      .inc-sec-num {
        width: 26px; height: 26px; font-size: 11px;
      }

      /* Nivel de impacto: 3 opciones en fila */
      .inc-sev-grid { grid-template-columns: repeat(3,1fr); gap:4px; }
      .inc-sev-btn  { padding: 8px 4px !important; font-size: 11px !important; }

      /* Botones de acción */
      .inc-form-foot .inc-act-btn { padding: 10px 14px !important; font-size: 13px !important; }
    }

    @media (max-width: 390px) {
      .inc-rail { display: none !important; } /* Oculta rail en pantallas muy pequeñas */
      .inc-form { width: 100% !important; }
    }

    @media (prefers-reduced-motion:reduce) {
      .inc-modal-back.open .inc-rail,
      .inc-modal-back.open .inc-form-body section { animation:none; }
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
            <span class="tag">CONTROL DE CAMPO · RECONOCIMIENTO TALLY</span>
            <h1>Reconocimiento Tally</h1>
            <p>Bitácora de aspectos destacados del personal. Cada reconocimiento queda asociado a un colaborador, la competencia reconocida, su nivel de impacto, turno y zona de trabajo.</p>
          </div>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
            <button class="inc-btn primary" id="btnNew">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Registrar reconocimiento
            </button>
          </div>
        </section>

        <!-- KPIS -->
        <section class="inc-kpis">
          <div class="inc-kpi"><div class="lbl">Total</div><div class="val" id="kpiTotal">0</div></div>
          <div class="inc-kpi"><div class="lbl">Aprobados</div><div class="val" id="kpiAprob">0</div></div>
          <div class="inc-kpi"><div class="lbl">Pendientes</div><div class="val" id="kpiPend">0</div></div>
          <div class="inc-kpi"><div class="lbl">Este mes</div><div class="val" id="kpiMes">0</div></div>
        </section>

        <!-- TOOLBAR -->
        <div class="inc-toolbar">
          <div class="inc-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="incSearch" type="text" placeholder="Buscar por colaborador, competencia, zona, coordinador…">
          </div>
          <div class="inc-filter" id="incFilter">
            <button class="active" data-f="todos">Todos</button>
            <button data-f="pendiente">Pendientes</button>
            <button data-f="aprobado">Aprobados</button>
            <button data-f="rechazado">Rechazados</button>
          </div>
        </div>

        <!-- TABLA -->
        <div class="inc-table-wrap">
          <table class="inc-table">
            <thead>
              <tr>
                <th>Colaborador</th>
                <th>Competencia</th>
                <th>Concepto</th>
                <th>Estado</th>
                <th>Coordinador</th>
                <th>Turno</th>
                <th>Fecha</th>
                <th>Zona</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody id="incTbody">
              <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--co-faint)">Cargando…</td></tr>
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
        <span class="inc-rail-kicker">PARTE · RECONOCIMIENTO</span>
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
        <span class="inc-rail-lbl">Competencia reconocida</span>
        <div class="inc-rail-comp-val" id="railComp">Se asigna según la competencia</div>
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
          <h3 id="incModalTitle">Registrar reconocimiento</h3>
          <div class="sub">Completa el parte. El concepto se asigna solo según la competencia.</div>
        </div>
        <button class="inc-modal-close" id="incModalClose">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <div class="inc-form-body">
        <input type="hidden" id="im-id">
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
              <label>Competencia</label>
              <select id="im-competencia"><option value="">Selecciona…</option></select>
            </div>
            <div class="inc-field">
              <label>Concepto</label>
              <input id="im-concepto" type="text" readonly placeholder="—">
            </div>
          </div>
        </section>

        <section class="inc-sec">
          <div class="inc-sec-head"><span class="inc-sec-num">03</span><span>Contexto del turno</span></div>
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
          <div class="inc-sec-head"><span class="inc-sec-num">04</span><span>Zona de trabajo</span></div>
          <div class="inc-field">
            <select id="im-zona"><option value="">Selecciona…</option></select>
          </div>
        </section>

        <section class="inc-sec">
          <div class="inc-sec-head"><span class="inc-sec-num">05</span><span>Comentarios del coordinador</span></div>
          <div class="inc-field">
            <textarea id="im-detalle" placeholder="Justifica el reconocimiento: qué hizo y por qué se reconoce…" maxlength="2000"></textarea>
          </div>
        </section>

        <section class="inc-sec">
          <div class="inc-sec-head"><span class="inc-sec-num">06</span><span>Evidencia</span></div>
          <div class="inc-field">
            <label>Foto</label>
            <div class="inc-drop" id="dropFoto">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/></svg>
              <span>Subir imagen<br><small>JPG · PNG · WebP · máx 8MB</small></span>
            </div>
            <input type="file" id="im-foto" accept=".jpg,.jpeg,.png,.webp" style="display:none">
            <div class="inc-file-info" id="infoFoto" style="display:none"></div>
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
    <div class="inc-modal-foot" style="justify-content:space-between">
      <div style="display:flex;gap:8px">
        <button class="inc-act-btn danger" id="incViewReject">Rechazar</button>
        <button class="inc-act-btn" id="incViewApprove" style="border-color:#12B76A;color:#12B76A">Aprobar</button>
      </div>
      <div style="display:flex;gap:8px">
        <button class="inc-btn" id="incViewCloseBtn">Cerrar</button>
        <button class="inc-btn" id="incViewEdit">Editar</button>
        <button class="inc-btn primary" id="incViewDiploma">Descargar diploma</button>
      </div>
    </div>
  </div>
</div>

<div class="inc-toast" id="incToast">—</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  // ── Catálogos desde PHP (fuente de verdad) ──
  const COMPETENCIAS = <?= json_encode($JS_COMPETENCIAS, JSON_UNESCAPED_UNICODE) ?>; // {competencia: concepto}
  const DIPLOMAS = <?= json_encode($JS_DIPLOMAS, JSON_UNESCAPED_UNICODE) ?>; // {competencia: texto elaborado}
  const ESTADOS  = <?= json_encode($JS_ESTADOS, JSON_UNESCAPED_UNICODE) ?>; // {clave:{label,color}}
  const TURNOS   = <?= json_encode($JS_TURNOS, JSON_UNESCAPED_UNICODE) ?>;   // {clave: label}
  const COORD    = <?= json_encode($COORDINADOR, JSON_UNESCAPED_UNICODE) ?>;
  const LOGO     = <?= json_encode($LOGO_B64) ?>;
  const EMPRESA  = <?= json_encode($EMPRESA, JSON_UNESCAPED_UNICODE) ?>;
  const CIUDAD   = <?= json_encode($CIUDAD, JSON_UNESCAPED_UNICODE) ?>;
  const BASE     = '..';
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
  let query = '';
  let filtro = 'todos';
  let editingId = null;
  // Ruta de la foto en el formulario (se actualiza al subir/quitar).
  let formFoto = null;

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
  function estadoBadge(key) {
    const m = ESTADOS[key];
    if (!m) return esc(key || '—');
    return `<span class="inc-badge" style="background:${m.color}"><span class="dot"></span>${esc(m.label)}</span>`;
  }
  function turnoLabel(key) { return TURNOS[key] || key || '—'; }
  function fmtFecha(f) {
    if (!f) return '—';
    const [y,m,d] = String(f).split('-');
    return d ? `${d}/${m}/${y}` : f;
  }
  const MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  function fmtFechaLarga(f) {
    if (!f) return '';
    const [y,m,d] = String(f).split('-').map(Number);
    if (!y || !m || !d) return String(f);
    const mes = (MESES[m-1] || '').replace(/^./, c => c.toUpperCase());
    return `${d} de ${mes} de ${y}`;
  }

  // ─── Poblar selects estáticos (una vez) ───
  function fillSelect(el, entries, placeholder) {
    el.innerHTML = `<option value="">${placeholder}</option>` +
      entries.map(([v, label]) => `<option value="${esc(v)}">${esc(label)}</option>`).join('');
  }
  function initSelects() {
    fillSelect($('im-competencia'), Object.keys(COMPETENCIAS).map(c => [c, c]), 'Selecciona…');
    fillSelect($('im-zona'),  UBIC_ZONA.map(z => [z, z]), 'Selecciona…');
    enhanceZona($('im-zona'));
  }

  function initials(n) {
    const w = String(n || '').trim().split(/\s+/);
    return ((w[0]?.[0] ?? '') + (w[1]?.[0] ?? '')).toUpperCase() || '—';
  }

  // Fija el turno: valor oculto + estado del toggle.
  function setTurno(key) {
    $('im-turno').value = key || '';
    document.querySelectorAll('#turnoToggle button').forEach(b =>
      b.classList.toggle('active', b.dataset.turno === key));
  }

  // ─── KPIs ───
  function renderKpis() {
    $('kpiTotal').textContent = incidencias.length;
    $('kpiAprob').textContent = incidencias.filter(i => i.estado === 'aprobado').length;
    $('kpiPend').textContent  = incidencias.filter(i => i.estado === 'pendiente').length;
    const now = new Date();
    const ym = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0');
    $('kpiMes').textContent = incidencias.filter(i => String(i.fecha || '').startsWith(ym)).length;
  }

  // ─── Tabla ───
  function render() {
    const q = query.trim().toLowerCase();
    const list = incidencias.filter(i => {
      if (filtro !== 'todos' && (i.estado || 'pendiente') !== filtro) return false;
      if (!q) return true;
      return [i.colaborador_nombre, i.colaborador_cargo, i.competencia, i.concepto,
              i.coordinador, i.zona_trabajo, i.detalle]
        .some(v => String(v ?? '').toLowerCase().includes(q));
    });
    const tb = $('incTbody');
    tb.innerHTML = '';
    if (!list.length) {
      tb.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:32px;color:var(--co-faint)">Sin reconocimientos.</td></tr>`;
      return;
    }
    list.forEach(i => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><div class="inc-name">${esc(i.colaborador_nombre)}</div><div class="inc-sub">${esc(i.colaborador_cargo || '')}</div></td>
        <td>${esc(i.competencia)}</td>
        <td><span class="inc-sub" style="font-size:12.5px;color:var(--co-ink)">${esc(i.concepto)}</span></td>
        <td>${estadoBadge(i.estado)}</td>
        <td>${esc(i.coordinador)}</td>
        <td><span class="inc-turno-chip">${esc(turnoLabel(i.turno))}</span></td>
        <td>${fmtFecha(i.fecha)}</td>
        <td>${esc(i.zona_trabajo)}</td>
        <td>
          <div class="inc-cell-actions">
            <button class="inc-act-btn" data-action="view" data-id="${i.id}">Ver</button>
            ${i.estado === 'aprobado' ? `<button class="inc-act-btn" data-action="diploma" data-id="${i.id}">Diploma</button>` : ''}
            <button class="inc-act-btn" data-action="edit" data-id="${i.id}">Editar</button>
            <button class="inc-act-btn danger" data-action="del" data-id="${i.id}">Eliminar</button>
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

  async function cargarIncidencias() {
    const res = await fetch(`${BASE}/api/get_reconocimientos.php`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.success) { toast(data.error || 'Error al cargar', 'error'); return; }
    incidencias = data.data || [];
    renderKpis(); render();
  }

  // ════════════════ MODAL REGISTRAR / EDITAR ════════════════
  function setFileInfo(which, path) {
    const info = $('infoFoto');
    if (!path) { info.style.display = 'none'; info.innerHTML = ''; return; }
    const name = path.split('/').pop();
    info.style.display = 'flex';
    info.innerHTML = `<a href="${BASE}/${esc(path)}" target="_blank" rel="noopener">${esc(name)}</a>
                      <button type="button" class="rm" data-rm="foto">Quitar</button>`;
  }

  function openModal(id) {
    editingId = id ? Number(id) : null;
    const i = editingId ? incidencias.find(x => Number(x.id) === editingId) : null;
    $('incModalTitle').textContent = i ? 'Editar reconocimiento' : 'Registrar reconocimiento';
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
    $('im-competencia').value = i ? (i.competencia ?? '') : '';
    $('im-fecha').value       = i ? (i.fecha ?? '') : new Date().toISOString().slice(0,10);
    $('im-zona').value        = i ? (i.zona_trabajo ?? '') : '';
    $('im-detalle').value     = i ? (i.detalle ?? '') : '';
    setTurno(i ? (i.turno || '') : '');
    syncCargo();
    syncConcepto();
    formFoto = i ? (i.foto_path || null) : null;
    setFileInfo('foto', formFoto);
    $('im-foto').value = '';
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
  function syncConcepto() {
    const c = $('im-competencia').value;
    const concepto = COMPETENCIAS[c] || '';
    $('im-concepto').value    = concepto;
    $('railComp').textContent = concepto || 'Se asigna según la competencia';
  }

  async function subirArchivo(file) {
    const fd = new FormData();
    fd.append('file', file);
    const res = await fetch(`${BASE}/api/upload_reconocimiento_file.php`, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Error al subir');
    return data.path;
  }

  async function handleFile(which, file) {
    if (!file) return;
    try {
      toast('Subiendo archivo…');
      const path = await subirArchivo(file);
      formFoto = path; setFileInfo('foto', path);
      toast('Archivo subido');
    } catch (e) {
      toast(e.message || 'Error al subir archivo', 'error');
    }
  }

  async function guardar() {
    const payload = {
      id:             Number($('im-id').value || 0),
      colaborador_id: Number($('im-colaborador').value || 0),
      competencia:    $('im-competencia').value,
      turno:          $('im-turno').value,
      fecha:          $('im-fecha').value,
      zona_trabajo:   $('im-zona').value,
      detalle:        $('im-detalle').value.trim(),
      foto_path:      formFoto || '',
    };
    if (!payload.colaborador_id) { toast('Selecciona un colaborador', 'error'); return; }
    if (!payload.competencia)    { toast('Selecciona la competencia', 'error'); return; }
    if (!payload.turno)          { toast('Selecciona el turno', 'error'); return; }
    if (!payload.fecha)          { toast('Selecciona la fecha', 'error'); return; }
    if (!payload.zona_trabajo)   { toast('Selecciona la zona de trabajo', 'error'); return; }

    const btn = $('incModalSave');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res = await fetch(`${BASE}/api/save_reconocimiento.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (data.success) {
        toast(payload.id ? 'Reconocimiento actualizado' : 'Reconocimiento registrado');
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
    if (!confirm(`¿Eliminar el reconocimiento de "${i.colaborador_nombre}" (${i.competencia})?\nSe borrará también su foto adjunta.`)) return;
    try {
      const res = await fetch(`${BASE}/api/delete_reconocimiento.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id) }),
      });
      const data = await res.json();
      if (data.success) { toast('Reconocimiento eliminado'); cargarIncidencias(); }
      else { toast(data.error || 'Error al eliminar', 'error'); }
    } catch (e) { toast('Error de red', 'error'); }
  }

  // ════════════════ VISTA DETALLE ════════════════
  function openView(id) {
    const i = incidencias.find(x => Number(x.id) === Number(id));
    if (!i) return;
    const estado = i.estado || 'pendiente';
    const m = ESTADOS[estado] || { label: estado, color: '#64748b' };

    let attachHtml = '';
    if (i.foto_path || i.foto_drive_url) {
      let links = '';
      if (i.foto_path) {
        links += `<a href="${BASE}/${esc(i.foto_path)}" target="_blank" rel="noopener" class="inc-view-attach inc-view-attach--foto">📷 Ver foto</a>`;
      }
      if (i.foto_drive_url) {
        links += `<a href="${esc(i.foto_drive_url)}" target="_blank" rel="noopener" class="inc-view-attach inc-view-attach--drive">☁ Foto en Drive</a>`;
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
          <div class="iv-coord">
            <span class="iv-stat-k">Coordinador</span>
            <span class="iv-stat-v">${esc(i.coordinador || '—')}</span>
          </div>
          ${i.supervisor ? `<div class="iv-coord" style="margin-top:0;border-top:none;padding-top:6px">
            <span class="iv-stat-k">Supervisor</span>
            <span class="iv-stat-v">${esc(i.supervisor)}</span>
          </div>` : ''}
        </div>
        <div class="inc-view-main">
          <div>
            <p class="inc-view-name">${esc(i.colaborador_nombre)}</p>
            <p class="inc-view-cargo">${esc(i.colaborador_cargo || '—')}</p>
          </div>
          <hr class="inc-view-divider">
          <div class="inc-view-field inc-view-field--blue">
            <span class="iv-k">Competencia</span>
            <span class="iv-v">${esc(i.competencia)}</span>
          </div>
          <div class="inc-view-field inc-view-field--purple">
            <span class="iv-k">Concepto</span>
            <span class="iv-v">${esc(i.concepto)}</span>
          </div>
          <div class="inc-view-detalle">
            <span class="iv-k">Comentarios del coordinador</span>
            <span class="iv-v">${esc(i.detalle || '—')}</span>
          </div>
          ${attachHtml}
        </div>
      </div>`;

    $('incViewSub').textContent = `${i.colaborador_nombre} · ${fmtFecha(i.fecha)}`;
    // Botones de estado / diploma según el estado actual.
    ['incViewEdit', 'incViewApprove', 'incViewReject', 'incViewDiploma'].forEach(bid => $(bid).dataset.id = i.id);
    $('incViewApprove').style.display = estado !== 'aprobado' ? '' : 'none';
    $('incViewReject').style.display  = estado !== 'rechazado' ? '' : 'none';
    $('incViewDiploma').style.display = estado === 'aprobado' ? '' : 'none';
    $('incViewBack').classList.add('open');
  }
  function closeView() { $('incViewBack').classList.remove('open'); }

  // ─── Aprobar / Rechazar ───
  async function cambiarEstado(id, estado) {
    const i = incidencias.find(x => Number(x.id) === Number(id));
    if (!i) return;
    if (estado === 'aprobado' && !confirm(`¿Aprobar el reconocimiento de "${i.colaborador_nombre}"?\nQuedarás registrado como supervisor que firma el diploma.`)) return;
    if (estado === 'rechazado' && !confirm(`¿Rechazar el reconocimiento de "${i.colaborador_nombre}"?`)) return;
    try {
      const res = await fetch(`${BASE}/api/set_reconocimiento_estado.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id), estado }),
      });
      const data = await res.json();
      if (!data.success) { toast(data.error || 'No se pudo actualizar', 'error'); return; }
      toast(estado === 'aprobado' ? 'Reconocimiento aprobado' : estado === 'rechazado' ? 'Reconocimiento rechazado' : 'Vuelto a pendiente');
      await cargarIncidencias();
      // Refresca el detalle abierto con los datos nuevos.
      if ($('incViewBack').classList.contains('open')) openView(id);
    } catch (e) { toast('Error de red', 'error'); }
  }

  // ─── Diploma (PDF imprimible) ───
  function generarDiploma(id) {
    const i = incidencias.find(x => Number(x.id) === Number(id));
    if (!i) return;
    if ((i.estado || 'pendiente') !== 'aprobado') { toast('El reconocimiento debe estar aprobado', 'error'); return; }

    const cuerpo = DIPLOMAS[i.competencia] ||
      ('Este certificado se otorga en reconocimiento a: ' + (i.concepto || '') + '.');

    const doc = `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Certificado de Reconocimiento — ${esc(i.colaborador_nombre)}</title>
      <style>
        @page { size:A4 landscape; margin:0; }
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:Georgia,'Times New Roman',serif;color:#1f2937;
             print-color-adjust:exact;-webkit-print-color-adjust:exact;}
        .hoja{width:297mm;height:210mm;position:relative;
              background:#ffffff;padding:11mm;}
        /* Marco ornamental: verde grueso + filetes dorados */
        .b1{position:absolute;inset:8mm;border:3px solid #1E7A46;}
        .b2{position:absolute;inset:9.6mm;border:1px solid #C9A227;}
        .b3{position:absolute;inset:11mm;border:1px solid #C9A227;}
        /* Rombos dorados en las esquinas internas */
        .cnr{position:absolute;width:5mm;height:5mm;background:#C9A227;transform:rotate(45deg);z-index:2;}
        .cnr.tl{top:9.4mm;left:9.4mm;} .cnr.tr{top:9.4mm;right:9.4mm;}
        .cnr.bl{bottom:9.4mm;left:9.4mm;} .cnr.br{bottom:9.4mm;right:9.4mm;}
        .in{position:absolute;inset:14mm;display:flex;flex-direction:column;align-items:center;text-align:center;}
        .head{width:100%;display:flex;align-items:flex-start;justify-content:space-between;}
        .head .brand{display:flex;flex-direction:column;align-items:center;flex:1;}
        .logo{max-height:20mm;margin-bottom:2mm;}
        .empresa{font-size:9.5px;letter-spacing:.04em;color:#4b5563;font-family:Arial,sans-serif;font-weight:700;}
        .lugarfecha{position:absolute;top:0;right:0;font-size:12px;color:#374151;font-family:Arial,sans-serif;}
        .kicker{margin-top:5mm;font-size:12px;letter-spacing:.34em;text-transform:uppercase;color:#6b7280;
                font-family:Arial,sans-serif;font-weight:700;}
        .title{font-size:40px;font-weight:700;color:#1f2937;margin:1mm 0 3mm;letter-spacing:.01em;}
        .lead{font-size:15px;color:#374151;max-width:215mm;line-height:1.55;font-family:Arial,sans-serif;}
        .comp{font-size:38px;font-weight:800;color:#1E7A46;margin:5mm 0 4mm;text-transform:uppercase;letter-spacing:.03em;}
        .cuerpo{font-size:14.5px;color:#374151;max-width:210mm;line-height:1.65;font-family:Arial,sans-serif;}
        .nombre{font-size:30px;font-weight:800;color:#111827;margin:6mm 0 2mm;letter-spacing:.02em;font-family:Arial,sans-serif;}
        .nombre-ln{width:150mm;border-bottom:1.5px solid #C9A227;margin-bottom:2mm;}
        .agr-k{margin-top:5mm;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#6b7280;
               font-family:Arial,sans-serif;font-weight:700;}
        .agr-v{margin-top:1.5mm;font-size:14.5px;color:#374151;max-width:200mm;line-height:1.55;
               font-style:italic;font-family:Arial,sans-serif;}
        .firmas{position:absolute;left:0;right:0;bottom:2mm;display:flex;justify-content:center;gap:46mm;}
        .firma{min-width:72mm;text-align:center;}
        .firma .nm{font-size:14px;font-weight:700;color:#111827;padding-bottom:2mm;font-family:Arial,sans-serif;}
        .firma .ln{border-top:1.4px solid #374151;padding-top:2mm;font-size:10.5px;letter-spacing:.08em;
                   text-transform:uppercase;color:#4b5563;font-weight:700;font-family:Arial,sans-serif;}
      </style></head><body>
      <div class="hoja">
        <div class="b1"></div><div class="b2"></div><div class="b3"></div>
        <div class="cnr tl"></div><div class="cnr tr"></div><div class="cnr bl"></div><div class="cnr br"></div>
        <div class="in">
          <div class="head">
            <div class="lugarfecha">${esc(CIUDAD)}, ${esc(fmtFechaLarga(i.fecha))}</div>
            <div class="brand">
              ${LOGO ? `<img class="logo" src="${LOGO}">` : ''}
              <div class="empresa">${esc(EMPRESA)}</div>
            </div>
          </div>
          <div class="kicker">¡Gracias por tu apoyo!</div>
          <div class="title">Reconocimiento Tallyman</div>
          <div class="lead">El Equipo de Operaciones exalta y reconoce su labor y dedicación inquebrantable,
            especialmente por su desempeño sobresaliente en la competencia fundamental de:</div>
          <div class="comp">${esc(i.competencia)}</div>
          <div class="cuerpo">${esc(cuerpo)}</div>
          <div class="nombre-ln" style="margin-top:6mm"></div>
          <div class="nombre" style="margin-top:0">${esc(i.colaborador_nombre)}</div>
          ${i.detalle ? `<div class="agr-k">Comentarios de agradecimiento de tu coordinador:</div>
          <div class="agr-v">${esc(i.detalle)}</div>` : ''}
          <div class="firmas">
            <div class="firma">
              <div class="nm">${esc(i.coordinador || '')}</div>
              <div class="ln">Coordinador Tallyman</div>
            </div>
            <div class="firma">
              <div class="nm">${esc(i.supervisor || '')}</div>
              <div class="ln">Supervisor Tallyman</div>
            </div>
          </div>
        </div>
      </div>
      <script>window.addEventListener('load',function(){window.focus();window.print();});<\/script>
    </body></html>`;

    const w = window.open('', '_blank');
    if (!w) { toast('Permite las ventanas emergentes para el diploma', 'error'); return; }
    w.document.write(doc); w.document.close();
  }

  // ─── Eventos ───
  $('btnNew').addEventListener('click', () => openModal(null));
  $('incSearch').addEventListener('input', e => { query = e.target.value; render(); });
  $('incFilter').addEventListener('click', e => {
    const b = e.target.closest('button[data-f]'); if (!b) return;
    filtro = b.dataset.f;
    $('incFilter').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
    render();
  });
  $('incTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action]'); if (!b) return;
    if (b.dataset.action === 'view')    openView(b.dataset.id);
    if (b.dataset.action === 'edit')    openModal(b.dataset.id);
    if (b.dataset.action === 'del')     eliminar(b.dataset.id);
    if (b.dataset.action === 'diploma') generarDiploma(b.dataset.id);
  });

  $('im-colaborador').addEventListener('change', syncCargo);
  $('im-competencia').addEventListener('change', syncConcepto);
  $('turnoToggle').addEventListener('click', e => {
    const b = e.target.closest('[data-turno]'); if (!b) return;
    setTurno(b.dataset.turno);
  });

  // Adjunto (solo foto)
  $('dropFoto').addEventListener('click', () => $('im-foto').click());
  $('im-foto').addEventListener('change', e => handleFile('foto', e.target.files[0]));
  document.addEventListener('click', e => {
    const rm = e.target.closest('[data-rm]'); if (!rm) return;
    formFoto = null; setFileInfo('foto', null); $('im-foto').value = '';
  });

  $('incModalClose').addEventListener('click', closeModal);
  $('incModalCancel').addEventListener('click', closeModal);
  $('incModalSave').addEventListener('click', guardar);
  $('incModalBack').addEventListener('click', e => { if (e.target === $('incModalBack')) closeModal(); });

  $('incViewClose').addEventListener('click', closeView);
  $('incViewCloseBtn').addEventListener('click', closeView);
  $('incViewBack').addEventListener('click', e => { if (e.target === $('incViewBack')) closeView(); });
  $('incViewEdit').addEventListener('click', e => { closeView(); openModal(e.currentTarget.dataset.id); });
  $('incViewApprove').addEventListener('click', e => cambiarEstado(e.currentTarget.dataset.id, 'aprobado'));
  $('incViewReject').addEventListener('click', e => cambiarEstado(e.currentTarget.dataset.id, 'rechazado'));
  $('incViewDiploma').addEventListener('click', e => generarDiploma(e.currentTarget.dataset.id));

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
    cargarIncidencias();
  })();
})();
</script>

</body>
</html>
