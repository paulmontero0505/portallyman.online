<?php
require_once('../includes/auth.php');
require_once('../includes/evades_catalogo.php');
require_report();

$JS_COMPETENCIAS = evades_competencias();
foreach ($JS_COMPETENCIAS as $clave => &$metaCompetencia) {
    $metaCompetencia['automatizada'] = evades_tiene_automatizacion($clave);
}
unset($metaCompetencia);
$EVALUADOR = $_SESSION['user_name'] ?? '';
$USER_ROL  = $_SESSION['user_rol'] ?? '';
$USER_ID   = (int)($_SESSION['user_id'] ?? 0);

$logo_path = __DIR__ . '/../logo/logo.png';
$LOGO_B64  = is_file($logo_path) ? base64_encode(file_get_contents($logo_path)) : '';

$anioActual = (int)date('Y');
$PERIODOS = [];
foreach ([$anioActual - 1, $anioActual] as $anio) {
    foreach ([1, 2, 3, 4] as $t) {
        $PERIODOS[] = "$anio-T$t";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EVADES · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <link rel="stylesheet" href="../css/estiba.css">

  <style>
    .ev-wrap {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0, 135, 90, 0.18); --co-line-bold:rgba(0, 135, 90, 0.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      --mono: ui-monospace, Consolas, monospace;
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .ev-wrap *, .ev-wrap *::before, .ev-wrap *::after { box-sizing:border-box; }

    .ev-hero {
      background: linear-gradient(135deg, #005c3d 0%, #00875A 100%) !important;
      color:#fff; border-radius:20px; padding:22px 28px;
      display:flex; align-items:center; justify-content:space-between; gap:18px;
      border: 1px solid rgba(0, 135, 90, 0.2) !important;
      box-shadow: 0 8px 32px rgba(0, 135, 90, 0.08) !important;
    }
    .ev-hero h1 { margin:6px 0 4px; font-size:22px; font-weight:700; letter-spacing:-.01em; }
    .ev-hero p  { margin:0; color:rgba(255,255,255,.85); font-size:13px; max-width:560px; }
    .ev-hero .tag {
      display:inline-flex; align-items:center; gap:8px;
      padding:5px 11px; border-radius:999px;
      background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18);
      font-size:11px; font-weight:600; letter-spacing:.12em; text-transform:uppercase;
      color: #ffffff !important;
    }

    .ev-btn {
      display:inline-flex; align-items:center; gap:7px;
      padding:9px 16px; border-radius:10px; border:1px solid rgba(0, 135, 90, 0.3);
      background:#fff; cursor:pointer; text-decoration:none;
      font-family:inherit; font-size:13px; font-weight:600; color:#00875A;
      transition:all .15s;
    }
    .ev-btn:hover { border-color:var(--co-navy-700); color:var(--co-navy); background: rgba(0, 135, 90, 0.05); }
    body .ev-btn.primary, .ev-btn.primary {
      background: linear-gradient(135deg, #00875A 0%, #005c3d 100%) !important;
      color: #fff !important; border: none !important; font-weight: 700 !important;
      box-shadow: 0 4px 18px rgba(0, 135, 90, 0.2) !important;
      letter-spacing: 0.02em; padding: 11px 20px; border-radius: 12px;
    }
    body .ev-btn.primary:hover, .ev-btn.primary:hover {
      transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0, 135, 90, 0.35) !important; filter: brightness(1.08);
    }
    .ev-btn svg { width:14px; height:14px; }

    .ev-kpis { display:flex; gap:10px; flex-wrap:wrap; }
    .ev-kpi {
      flex:1; min-width:120px; background:#fff; border:1px solid var(--co-line); border-radius:14px;
      padding:14px 18px; box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;
    }
    .ev-kpi .lbl { font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--co-mute); }
    .ev-kpi:nth-child(1) .val { color:var(--co-navy-700); }
    .ev-kpi:nth-child(2) .val { color:#3b82f6; }
    .ev-kpi:nth-child(3) .val { color:#12B76A; }
    .ev-kpi:nth-child(4) .val { color:#d97706; }
    .ev-kpi .val { font-size:22px; font-weight:700; margin-top:4px; }

    .ev-toolbar {
      display:flex; gap:10px; align-items:center; flex-wrap:wrap;
      background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:10px 12px;
      box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;
    }
    .ev-search {
      flex:1; min-width:220px; display:flex; align-items:center; gap:8px;
      background:var(--co-deck); border:1px solid rgba(0, 135, 90, 0.15); border-radius:10px; padding:8px 12px;
    }
    .ev-search:focus-within { border-color:var(--co-navy-700); background:#fff; box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.15); }
    .ev-search input { flex:1; border:0; outline:0; background:transparent; font:inherit; font-size:13.5px; color:var(--co-ink); }
    .ev-search svg { width:15px; height:15px; color:var(--co-mute); }
    .ev-filter { display:flex; gap:4px; background:#f3f4f6; border-radius:10px; padding:3px; flex-wrap:wrap; border: 1px solid #e5e7eb; }
    .ev-filter select {
      padding:6px 10px; border:0; background:transparent; border-radius:7px;
      font:inherit; font-size:12px; font-weight:600; color:var(--co-mute); cursor:pointer;
    }

    .ev-table-wrap { background:#fff; border:1px solid var(--co-line); border-radius:14px; overflow:auto; box-shadow: 0 4px 16px rgba(0,0,0,.02) !important; }
    .ev-table { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
    .ev-table thead tr { background: rgba(0, 135, 90, 0.04) !important; border-bottom:1px solid var(--co-line); }
    .ev-table th { padding:11px 14px; text-align:left; font-size:10.5px; letter-spacing:.1em; text-transform:uppercase; color:var(--co-navy); font-weight:700; }
    .ev-table tbody tr { border-bottom:1px solid rgba(0, 135, 90, 0.06); transition:background .12s; }
    .ev-table tbody tr:hover { background: rgba(0, 135, 90, 0.02); }
    .ev-table td { padding:11px 14px; vertical-align:middle; color: var(--co-ink) !important; }
    .ev-name { font-weight:600; color:var(--co-ink); }
    .ev-sub  { font-size:11px; color:var(--co-faint); }

    .ev-badge {
      display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px;
      font-size:10.5px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:#fff;
    }
    .ev-var { font-size:11.5px; font-weight:700; }
    .ev-var.up { color:#12B76A; }
    .ev-var.down { color:#dc2626; }
    .ev-var.flat { color:var(--co-mute); }

    .ev-act-btn {
      padding:5px 10px; border-radius:7px; border:1px solid rgba(0, 135, 90, 0.25);
      background:rgba(0, 135, 90, 0.05); cursor:pointer; font:inherit; font-size:12px; font-weight:600; color:#00875A;
    }
    .ev-act-btn:hover { border-color:var(--co-navy-700); color:#ffffff; background:#00875A; }
    .ev-act-btn.danger { color: var(--co-red); border-color: rgba(239, 68, 68, 0.25); background: rgba(239, 68, 68, 0.05); }
    .ev-act-btn.danger:hover { border-color:var(--co-red); color:#ffffff; background: var(--co-red); }
    .ev-cell-actions { display:flex; gap:6px; align-items:center; justify-content:flex-end; }

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

    .ev-toast {
      position:fixed; bottom:24px; right:24px; z-index:999;
      background:#111827; color:#fff; padding:12px 18px; border-radius:10px;
      font-size:13px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,.1);
      transform:translateY(120%); opacity:0; transition:all .25s;
    }
    .ev-toast.show { transform:translateY(0); opacity:1; }
    .ev-toast.is-error { background:#dc2626; }

    .content { padding:24px 28px 60px; overflow-y:auto; }

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
      padding:9px 11px; outline:0; transition:border-color .15s, box-shadow .15s, background .15s;
    }
    .ed-field input::placeholder, .ed-field textarea::placeholder { color:#94a3b8; }
    .ed-field select {
      appearance:none; -webkit-appearance:none; -moz-appearance:none;
      padding-right:34px; cursor:pointer;
      background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%234b5563' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
      background-repeat:no-repeat; background-position:right 10px center; background-size:15px;
    }
    .ed-field select option { color:#111827; background:#ffffff; }
    .ed-field textarea { resize:vertical; min-height:70px; }
    .ed-field input:hover, .ed-field select:hover, .ed-field textarea:hover { border-color:#94d6bb; }
    .ed-field input:focus, .ed-field select:focus, .ed-field textarea:focus {
      border-color:#00875A; background:#fff; box-shadow:0 0 0 3px rgba(0, 135, 90, 0.15);
    }
    .ed-field select:focus {
      background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2300875A' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
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

    /* botones del pie del formulario */
    .ed-btn {
      display:inline-flex; align-items:center; gap:7px;
      padding:10px 18px; border-radius:10px; border:1.5px solid #d1d5db;
      background:#fff; cursor:pointer; text-decoration:none;
      font-family:inherit; font-size:13px; font-weight:600; color:var(--co-mute);
      transition:all .15s;
    }
    .ed-btn:hover { border-color:var(--co-navy-700); color:var(--co-navy); background:rgba(0, 135, 90, 0.05); }
    .ed-btn:disabled { opacity:.55; cursor:not-allowed; }
    .ed-btn.primary {
      background:linear-gradient(135deg, #00875A 0%, #005c3d 100%);
      color:#fff; border:none; font-weight:700;
      box-shadow:0 4px 18px rgba(0, 135, 90, 0.2);
      letter-spacing:.02em; padding:11px 20px;
    }
    .ed-btn.primary:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0, 135, 90, 0.35); filter:brightness(1.08); }
    .ed-btn.primary:disabled { transform:none; filter:none; box-shadow:0 4px 18px rgba(0, 135, 90, 0.2); }

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
    /* EVADES por bloques · expediente operativo */
    :root { --ev-emerald-700:#006b49;--ev-emerald-600:#00875a;--ev-emerald-050:#e8f5ef;--ev-ink:#0f2940;--ev-muted:#64748b;--ev-amber:#f5a524; }
    .ev-status { display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border-radius:999px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.055em;white-space:nowrap; }
    .ev-status::before { content:'';width:7px;height:7px;border-radius:50%;background:currentColor; }
    .ev-status.generado { color:#42627a;background:#edf3f7; }.ev-status.revisado { color:var(--ev-emerald-700);background:var(--ev-emerald-050); }
    .ev-status.modificado { color:#915d05;background:#fff4d8; }.ev-status.cerrado { color:#fff;background:var(--ev-emerald-700); }
    .ev-progress { min-width:130px; }.ev-progress-label { display:flex;justify-content:space-between;gap:8px;font-size:11px;color:var(--ev-muted);margin-bottom:6px; }
    .ev-progress-track { height:6px;border-radius:99px;background:#e7edf1;overflow:hidden; }.ev-progress-track span { display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--ev-emerald-600),#30b981); }
    .ev-block-route { display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin:2px 0 12px; }
    .ev-route-step { padding:8px 4px 6px;text-align:center;font-size:9px;font-weight:800;letter-spacing:.045em;text-transform:uppercase;color:rgba(255,255,255,.5);border-top:2px solid rgba(255,255,255,.2); }
    .ev-route-step.done { color:#fff;border-color:#65d6ad; }.ev-route-step.current { color:#fff;border-color:#ffd166; }
    .ev-roster { min-height:0;overflow:auto;display:flex;flex-direction:column;gap:5px;margin:2px -5px 0;padding:0 5px; }
    .ev-roster-btn { width:100%;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.055);color:#fff;border-radius:10px;padding:9px 10px;text-align:left;cursor:pointer;transition:.15s; }
    .ev-roster-btn:hover,.ev-roster-btn.active { background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.35); }.ev-roster-btn strong { display:block;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .ev-roster-btn span { display:flex;justify-content:space-between;margin-top:3px;font-size:9px;color:rgba(255,255,255,.65); }
    .ev-create-back { z-index:1200; }.ev-create-card { width:min(650px,94vw);max-height:90vh;overflow:auto;background:#fff;border-radius:20px;box-shadow:0 28px 80px rgba(15,41,64,.26);transform:translateY(18px) scale(.98);transition:.22s; }
    .ed-modal-back.open .ev-create-card { transform:none; }.ev-create-head { padding:22px 24px 18px;background:linear-gradient(135deg,var(--ev-emerald-700),var(--ev-emerald-600));color:#fff;display:flex;justify-content:space-between;gap:20px; }
    .ev-create-head h3 { margin:3px 0 4px;font-size:21px; }.ev-create-head p { margin:0;color:rgba(255,255,255,.75);font-size:12px; }.ev-create-body { padding:22px 24px; }
    .ev-preview { margin-top:18px;border:1px solid #dce8e2;border-radius:14px;background:#f8fbfa;overflow:hidden; }.ev-preview-head { padding:13px 15px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e4eeea;font-size:12px;color:var(--ev-muted); }
    .ev-preview-count { font-size:22px;font-weight:850;color:var(--ev-emerald-700); }.ev-preview-list { max-height:210px;overflow:auto;padding:7px; }.ev-preview-person { padding:9px 10px;border-radius:8px;display:flex;justify-content:space-between;gap:10px;font-size:12px; }
    .ev-preview-person:nth-child(even) { background:#edf6f2; }.ev-create-foot { padding:15px 24px;border-top:1px solid #e7ecea;display:flex;justify-content:flex-end;gap:9px; }
    .ev-roster-mobile { display:none;width:100%;margin-top:10px;border:1px solid #d6e2dc;border-radius:9px;padding:9px;background:#fff;color:var(--ev-ink); }
    .ev-coverage-metrics { display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:12px 14px;background:#fff;border-bottom:1px solid #e4eeea; }
    .ev-coverage-metric { padding:10px;border:1px solid #e1ebe6;border-radius:11px;background:#f8fbfa; }.ev-coverage-metric b { display:block;font-size:19px;color:var(--ev-ink); }.ev-coverage-metric span { color:var(--ev-muted);font-size:10px;font-weight:750;text-transform:uppercase;letter-spacing:.04em; }
    .ev-coverage-metric.good b { color:var(--ev-emerald-700); }.ev-coverage-metric.warn b { color:#9a6700; }.ev-coverage-metric.missing b { color:#b42318; }
    .ev-preview-person { align-items:center; }.ev-preview-person-main { min-width:0; }.ev-preview-person-side { text-align:right;white-space:nowrap; }.ev-preview-person-side strong { color:var(--ev-emerald-700);font-size:13px; }.ev-mini-coverage { display:flex;gap:4px;justify-content:flex-end;margin-top:4px; }.ev-mini-coverage i { width:22px;height:4px;border-radius:9px;background:#e2e8f0; }.ev-mini-coverage i.good { background:#19a974; }.ev-mini-coverage i.warn { background:#f5a524; }.ev-mini-coverage i.missing { background:#e05a47; }
    .ev-workspace-tabs { display:flex;gap:6px;padding:8px 18px;border-bottom:1px solid #e6ece9;background:#f8fbfa; }.ev-workspace-tab { border:0;background:transparent;color:var(--ev-muted);font:700 11px/1 'DM Sans',sans-serif;padding:9px 12px;border-radius:9px;cursor:pointer; }.ev-workspace-tab.active { color:var(--ev-emerald-700);background:#fff;box-shadow:0 1px 5px rgba(15,41,64,.1); }
    .ev-section-intro { display:flex;justify-content:space-between;gap:12px;align-items:center;margin:-2px 0 12px;color:var(--ev-muted);font-size:11px; }.ev-coverage-badge { display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:999px;font-size:9px;font-weight:850;letter-spacing:.04em;text-transform:uppercase; }.ev-coverage-badge.suficiente { color:#08734f;background:#e2f5ed; }.ev-coverage-badge.parcial { color:#8a5b00;background:#fff3d6; }.ev-coverage-badge.sin_fuente { color:#a52a20;background:#fdebe8; }
    .ed-crit-row.ev-competency-card { display:grid;grid-template-columns:minmax(220px,1fr) 92px 92px 76px;gap:10px;padding:15px;margin-bottom:10px;border:1px solid #dfe9e4;border-radius:14px;background:#fff;box-shadow:0 4px 16px rgba(15,41,64,.045); }
    .ev-competency-top { display:flex;align-items:flex-start;justify-content:space-between;gap:10px; }.ev-competency-copy { color:var(--ev-muted);font-size:11px;line-height:1.45;margin-top:4px; }.ev-score-formula { display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin:9px 0 7px;font-size:10px;color:var(--ev-muted); }.ev-score-formula b { min-width:28px;padding:4px 6px;border-radius:7px;text-align:center;background:#edf4f1;color:var(--ev-ink); }.ev-score-formula b.positive { color:#08734f;background:#e2f5ed; }.ev-score-formula b.negative { color:#b42318;background:#fdebe8; }.ev-score-formula b.result { color:#fff;background:var(--ev-emerald-700); }
    .ev-engine-why { padding:8px 10px;border-left:3px solid #78c7a9;background:#f2faf6;border-radius:0 8px 8px 0;color:#355469;font-size:10.5px;line-height:1.45; }.ev-evidence-list { margin-top:7px;display:flex;flex-direction:column;gap:5px; }.ev-evidence-item { display:flex;gap:7px;align-items:flex-start;color:#526578;font-size:10.5px;line-height:1.4; }.ev-evidence-item::before { content:'';width:6px;height:6px;border-radius:50%;margin-top:4px;flex:0 0 auto;background:#78c7a9; }.ev-evidence-item.cross::before { background:#f5a524; }
    .ev-appreciation-panel { padding:16px;border:1px solid #d9e7e1;border-radius:14px;background:linear-gradient(145deg,#f7fbf9,#eef8f3); }.ev-appreciation-head { display:flex;justify-content:space-between;gap:12px;margin-bottom:12px; }.ev-appreciation-head h4 { margin:0;color:var(--ev-ink);font-size:14px; }.ev-appreciation-head p { margin:3px 0 0;color:var(--ev-muted);font-size:11px; }.ev-appreciation-grid { display:grid;grid-template-columns:1.1fr .8fr .8fr;gap:9px; }.ev-appreciation-list { display:flex;flex-direction:column;gap:6px;margin-top:12px; }.ev-appreciation-item { padding:9px 10px;background:#fff;border:1px solid #deebe5;border-radius:9px;font-size:10.5px;color:#4b6070; }.ev-appreciation-item b { color:var(--ev-ink); }
    .ev-view-report-head { padding:22px 24px;background:linear-gradient(135deg,var(--ev-emerald-700),var(--ev-emerald-600));color:#fff;display:flex;justify-content:space-between;gap:16px;align-items:flex-start; }.ev-view-report-head h3 { margin:4px 0;font-size:20px; }.ev-view-report-head p { margin:0;color:rgba(255,255,255,.76);font-size:11px; }.ev-report-score { min-width:92px;text-align:center;padding:10px;border:1px solid rgba(255,255,255,.25);border-radius:13px;background:rgba(255,255,255,.1); }.ev-report-score b { display:block;font-size:28px; }.ev-report-score span { font-size:9px;text-transform:uppercase;letter-spacing:.08em; }.ev-report-timeline { display:flex;gap:7px;flex-wrap:wrap;margin-top:8px; }.ev-report-event { padding:7px 9px;border-radius:8px;background:#f3f7f5;color:#526578;font-size:10px; }.ev-report-event b { color:var(--ev-emerald-700);text-transform:uppercase; }
    @media (max-width:760px) { .ev-block-route { grid-template-columns:repeat(2,1fr); }.ed-modal.ed-create .ev-roster { max-height:180px; }.ev-hide-mobile { display:none; }.ev-roster-mobile { display:block;flex:1 0 100%; }.ed-form-head { flex-wrap:wrap; }.ev-coverage-metrics { grid-template-columns:repeat(2,1fr); }.ed-crit-row.ev-competency-card { grid-template-columns:1fr 1fr; }.ed-crit-row.ev-competency-card > div:first-child { grid-column:1/-1; }.ev-appreciation-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="shell">
  <?php $sb_base = '..'; include('../includes/sidebar.php'); ?>

  <div class="main-area">
    <?php include('../includes/header.php'); ?>

    <main class="content">
      <div class="ev-wrap">

        <!-- HERO -->
        <section class="ev-hero">
          <div>
            <span class="tag">EVALUACIÓN TRIMESTRAL · POR COORDINADOR Y PUESTO</span>
            <h1>EVADES</h1>
            <p>Genera la nómina completa, revisa las diez competencias con evidencia automática y cierra el trimestre como un solo expediente.</p>
          </div>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
            <button class="ev-btn primary" id="btnNew">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Generar bloque
            </button>
          </div>
        </section>

        <!-- KPIS -->
        <section class="ev-kpis">
          <div class="ev-kpi"><div class="lbl">Bloques</div><div class="val" id="kpiTotal">0</div></div>
          <div class="ev-kpi"><div class="lbl">Personas</div><div class="val" id="kpiProm">0</div></div>
          <div class="ev-kpi"><div class="lbl">Promedio general</div><div class="val" id="kpiPct">0</div></div>
          <div class="ev-kpi"><div class="lbl">Bloques cerrados</div><div class="val" id="kpiVar">0</div></div>
        </section>

        <!-- TOOLBAR -->
        <div class="ev-toolbar">
          <div class="ev-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="evSearch" type="text" placeholder="Buscar por puesto o coordinador…">
          </div>
          <div class="ev-filter">
            <select id="evFiltroPeriodo">
              <option value="">Todos los períodos</option>
              <?php foreach ($PERIODOS as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ev-filter">
            <select id="evFiltroClas">
              <option value="">Todos los estados</option>
              <option value="generado">Generado</option><option value="revisado">Revisado</option>
              <option value="modificado">Modificado</option><option value="cerrado">Cerrado</option>
            </select>
          </div>
        </div>

        <!-- TABLA -->
        <div class="ev-table-wrap">
          <table class="ev-table">
            <thead>
              <tr>
                <th>Puesto</th>
                <th>Coordinador</th>
                <th>Período</th>
                <th>Estado</th>
                <th>Avance</th>
                <th>Promedio</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody id="evTbody">
              <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--co-faint)">Cargando…</td></tr>
            </tbody>
          </table>
        </div>

      </div>
    </main>
  </div>
</div>

<div class="ed-modal-back" id="evModalBack">
  <div class="ed-modal ed-create" style="width:1040px">
    <aside class="ed-rail">
      <div class="ed-rail-top">
        <span class="ed-rail-kicker">FICHA · EVADES</span>
        <span class="ed-rail-folio" id="evRailFolio">NUEVO</span>
      </div>
      <div class="ed-rail-id">
        <div class="ed-rail-avatar" id="evRailAvatar">—</div>
        <div style="min-width:0">
          <div class="ed-rail-name" id="evRailName">Sin evaluado</div>
          <div class="ed-rail-cargo" id="evRailCargo">Selecciona colaborador y período</div>
        </div>
      </div>
      <div>
        <span class="ed-rail-lbl">Puntaje total</span>
        <div class="ed-rail-count" id="evRailCount">0</div>
        <div class="ed-rail-max">de 100 puntos</div>
      </div>
      <div id="evBlockContext" style="display:none">
        <span class="ed-rail-lbl">Ruta del bloque</span>
        <div class="ev-block-route" id="evBlockRoute"></div>
        <span class="ed-rail-lbl">Nómina congelada</span>
      </div>
      <div class="ev-roster" id="evBlockRoster" style="display:none"></div>
      <div class="ed-rail-foot">
        <span class="ed-rail-lbl">Evaluador que reporta</span>
        <div class="ed-rail-eval"><?= htmlspecialchars($EVALUADOR) ?></div>
      </div>
    </aside>

    <div class="ed-form">
      <div class="ed-form-head">
        <div>
          <h3 id="evModalTitle">Nueva evaluación EVADES</h3>
          <div class="sub">Elige colaborador y trimestre, calcula las sugerencias y ajusta lo necesario.</div>
        </div>
        <button class="ed-modal-close" id="evModalClose">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <select class="ev-roster-mobile" id="evBlockRosterMobile" aria-label="Colaborador del bloque"></select>
      </div>

      <div class="ev-workspace-tabs" id="evWorkspaceTabs" style="display:none">
        <button class="ev-workspace-tab active" type="button" data-ev-tab="competencias">Competencias y evidencia</button>
        <button class="ev-workspace-tab" type="button" data-ev-tab="feedback">Retroalimentación</button>
      </div>

      <div class="ed-form-body">
        <input type="hidden" id="evm-id">

        <section class="ed-sec" id="evSecIdentity">
          <div class="ed-sec-head"><span class="ed-sec-num">01</span><span>Colaborador y período</span></div>
          <div class="ed-row2">
            <div class="ed-field">
              <label>Asistente de Estiba</label>
              <select id="evm-colaborador"><option value="">Selecciona…</option></select>
            </div>
            <div class="ed-field">
              <label>Trimestre</label>
              <select id="evm-periodo"><option value="">Selecciona…</option></select>
            </div>
          </div>
          <div class="ed-row2" style="margin-top:10px">
            <div class="ed-field">
              <label>Fecha de evaluación</label>
              <input id="evm-fecha" type="date">
            </div>
            <div class="ed-field" style="justify-content:flex-end">
              <button class="ev-btn primary" id="evBtnCalcular" type="button" style="align-self:flex-end">Calcular sugerencias</button>
            </div>
          </div>
        </section>

        <section class="ed-sec" id="evSecCompetencias" style="display:none">
          <div class="ed-sec-head"><span class="ed-sec-num">02</span><span>Sección A · Competencias Conductuales</span></div>
          <div class="ev-section-intro"><span>El puntaje parte de 6. El motor propone aumentos o descuentos y explica la evidencia usada.</span><span>Máximo +4</span></div>
          <div id="evCompA"></div>
        </section>
        <section class="ed-sec" id="evSecCompetenciasB" style="display:none">
          <div class="ed-sec-head"><span class="ed-sec-num">03</span><span>Sección B · Competencias Operativas</span></div>
          <div id="evCompB"></div>
          <div class="ed-resumen-total" style="margin-top:10px">
            <span>Puntaje total</span>
            <span id="evTotalScore">0 / 100</span>
          </div>
        </section>

        <section class="ed-sec" id="evAppreciationPanel" style="display:none">
          <div class="ev-appreciation-panel">
            <div class="ev-appreciation-head"><div><h4>Apreciación documentada</h4><p>Úsala cuando exista una observación del coordinador que no esté en las fuentes automáticas.</p></div><span class="ev-coverage-badge parcial">Auditable</span></div>
            <div class="ev-appreciation-grid">
              <div class="ed-field"><label>Competencia</label><select id="evAppCompetencia"></select></div>
              <div class="ed-field"><label>Dirección</label><select id="evAppDireccion"><option value="positiva">Aumento</option><option value="negativa">Descuento</option></select></div>
              <div class="ed-field"><label>Nivel / impacto</label><select id="evAppValor"><option value="2">+2 · evidencia buena</option><option value="4">+4 · evidencia excelente</option></select></div>
            </div>
            <div class="ed-field" style="margin-top:9px"><label>Descripción obligatoria</label><textarea id="evAppDescripcion" maxlength="1000" placeholder="Describe el hecho observado, cuándo ocurrió y cómo afectó el desempeño."></textarea></div>
            <div style="display:flex;justify-content:flex-end;margin-top:9px"><button class="ed-btn" type="button" id="evAppSave">Agregar evidencia</button></div>
            <div class="ev-appreciation-list" id="evAppList"></div>
          </div>
        </section>

        <section class="ed-sec" id="evSecFeedback" style="display:none">
          <div class="ed-sec-head"><span class="ed-sec-num">04</span><span>Retroalimentación y plan de acción</span></div>
          <div class="ed-field" style="margin-bottom:10px">
            <label>Fortalezas observadas</label>
            <textarea id="evm-fortalezas" maxlength="2000"></textarea>
          </div>
          <div class="ed-field" style="margin-bottom:10px">
            <label>Aspectos a mejorar</label>
            <textarea id="evm-aspectos" maxlength="2000"></textarea>
          </div>
          <div class="ed-field">
            <label>Plan de acción para el próximo trimestre</label>
            <textarea id="evm-plan" maxlength="2000"></textarea>
          </div>
        </section>
      </div>

      <div class="ed-form-foot">
        <button class="ed-btn" id="evModalCancel">Cancelar</button>
        <button class="ed-btn" id="evBlockPrev" style="display:none">Anterior</button>
        <button class="ed-btn" id="evBlockNext" style="display:none">Siguiente</button>
        <button class="ed-btn" id="evBlockReport" style="display:none">Ver informe</button>
        <button class="ed-btn" id="evBlockPdf" style="display:none">PDF actual</button>
        <button class="ed-btn" id="evBlockClose" style="display:none">Cerrar bloque</button>
        <button class="ed-btn primary" id="evModalSave" style="display:none">Guardar evaluación</button>
      </div>
    </div>
  </div>
</div>

<div class="ed-modal-back ev-create-back" id="evBlockCreateBack">
  <div class="ev-create-card">
    <div class="ev-create-head">
      <div><span class="ed-rail-kicker">NUEVO EXPEDIENTE</span><h3>Generar bloque EVADES</h3><p>Selecciona puesto y trimestre. La nómina quedará congelada al generar.</p></div>
      <button class="ed-modal-close" id="evBlockCreateX" style="color:#fff;border-color:rgba(255,255,255,.35)">×</button>
    </div>
    <div class="ev-create-body">
      <div class="ed-row2">
        <div class="ed-field"><label>Puesto</label><select id="evBlockPuesto"><option value="">Selecciona…</option><option value="ASISTENTE DE ESTIBA">Asistente de Estiba</option><option value="ANALISTA DE TROUBLE DESK">Analista de Trouble Desk</option></select></div>
        <div class="ed-field"><label>Trimestre</label><select id="evBlockPeriodo"><option value="">Selecciona…</option></select></div>
      </div>
      <div class="ed-field" id="evBlockCoordField" style="margin-top:12px;display:none"><label>Coordinador responsable</label><select id="evBlockCoordinador"><option value="">Selecciona…</option></select></div>
      <div class="ev-preview">
        <div class="ev-preview-head"><span>Personal que se incluirá</span><strong class="ev-preview-count" id="evBlockPreviewCount">—</strong></div>
        <div class="ev-coverage-metrics" id="evCoverageMetrics" style="display:none"></div>
        <div class="ev-preview-list" id="evBlockPreviewList"><div class="ev-sub" style="padding:14px">Selecciona los datos para consultar la nómina.</div></div>
      </div>
    </div>
    <div class="ev-create-foot"><button class="ed-btn" id="evBlockCreateCancel">Cancelar</button><button class="ed-btn primary" id="evBlockGenerate" disabled>Generar evaluaciones</button></div>
  </div>
</div>

<div class="ed-modal-back" id="evViewBack">
  <div class="ed-modal ed-modal--view">
    <div class="ed-modal-head" style="display:none">
      <div><h3>Detalle EVADES</h3><div class="sub" id="evViewSub">—</div></div>
      <button class="ed-modal-close" id="evViewClose"></button>
    </div>
    <div class="ed-modal-body" id="evViewBody"></div>
    <div class="ed-modal-foot">
      <button class="ed-btn" id="evViewCloseBtn">Cerrar</button>
      <button class="ed-btn" id="evViewPdf">Exportar PDF</button>
      <button class="ed-btn primary" id="evViewEdit">Editar</button>
    </div>
  </div>
</div>

<div class="ev-toast" id="evToast">—</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.0/jspdf.plugin.autotable.min.js"></script>
<script src="../assets/js/evades-bloques-model.js"></script>
<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  // ── Catálogos desde PHP (fuente de verdad) ──
  const COMPETENCIAS = <?= json_encode($JS_COMPETENCIAS, JSON_UNESCAPED_UNICODE) ?>;
  const COMP_KEYS = Object.keys(COMPETENCIAS);
  const EVALUADOR = <?= json_encode($EVALUADOR, JSON_UNESCAPED_UNICODE) ?>;
  const USER_ROL  = <?= json_encode($USER_ROL, JSON_UNESCAPED_UNICODE) ?>;
  const USER_ID   = <?= json_encode($USER_ID) ?>;
  const PERIODOS  = <?= json_encode($PERIODOS, JSON_UNESCAPED_UNICODE) ?>;
  const LOGO_B64 = <?= $LOGO_B64 ? json_encode('data:image/png;base64,' . $LOGO_B64) : 'null' ?>;
  const BASE = '..';

  let evaluaciones = []; // detalle individual; se conserva para PDF histórico
  let bloques = [];
  let colaboradores = [];
  let currentBlock = null;
  let workspaceState = null;
  let query = '';
  let filtroPeriodo = '';
  let filtroClas = '';

  function toast(msg, type) {
    const t = $('evToast');
    t.textContent = msg;
    t.className = 'ev-toast show' + (type === 'error' ? ' is-error' : '');
    setTimeout(() => t.classList.remove('show'), 3000);
  }
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  }
  function claseBadge(clas) {
    if (clas === 'Sobresaliente') return '#12B76A';
    if (clas === 'Sobre lo esperado') return '#3b82f6';
    if (clas === 'En lo esperado') return '#eab308';
    return '#dc2626';
  }

  function renderKpis() {
    $('kpiTotal').textContent = bloques.length;
    const personas = bloques.reduce((s, b) => s + Number(b.total_colaboradores || 0), 0);
    $('kpiProm').textContent = personas;
    const ponderado = bloques.reduce((s, b) => s + Number(b.promedio || 0) * Number(b.evaluaciones_total || 0), 0);
    const evaluados = bloques.reduce((s, b) => s + Number(b.evaluaciones_total || 0), 0);
    $('kpiPct').textContent = evaluados ? (ponderado / evaluados).toFixed(1) : '0';
    $('kpiVar').textContent = bloques.filter(b => b.estado === 'cerrado').length;
  }

  function render() {
    const q = query.trim().toLowerCase();
    const list = bloques.filter(e => {
      if (filtroPeriodo && e.periodo !== filtroPeriodo) return false;
      if (filtroClas && e.estado !== filtroClas) return false;
      if (!q) return true;
      return [e.puesto, e.coordinador_nombre].some(v => String(v ?? '').toLowerCase().includes(q));
    });
    const tb = $('evTbody');
    tb.innerHTML = '';
    if (!list.length) {
      tb.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--co-faint)">No hay bloques para estos filtros.</td></tr>`;
      return;
    }
    list.forEach(e => {
      const total = Number(e.total_colaboradores || 0);
      const completas = Number(e.completas || 0);
      const pct = total ? Math.round(completas / total * 100) : 0;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><div class="ev-name">${esc(e.puesto)}</div><div class="ev-sub">${total} personas en nómina</div></td>
        <td>${esc(e.coordinador_nombre)}</td>
        <td>${esc(e.periodo)}</td>
        <td><span class="ev-status ${esc(e.estado)}">${esc(e.estado)}</span></td>
        <td><div class="ev-progress"><div class="ev-progress-label"><span>${completas}/${total} completas</span><strong>${pct}%</strong></div><div class="ev-progress-track"><span style="width:${pct}%"></span></div></div></td>
        <td><strong>${Number(e.promedio || 0).toFixed(1)}</strong><div class="ev-sub">de 100</div></td>
        <td>
          <div class="ev-cell-actions">
            <button class="ev-act-btn" data-action="open-block" data-id="${e.id}">${e.estado === 'cerrado' ? 'Consultar' : 'Abrir bloque'}</button>
          </div>
        </td>`;
      tb.append(tr);
    });
  }

  async function cargarEvaluaciones() {
    const res = await fetch(`${BASE}/api/get_evades_bloques.php`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.success) { toast(data.error || 'Error al cargar', 'error'); return; }
    bloques = data.data || [];
    renderKpis(); render();
  }

  // Se carga aquí (aunque este task todavía no la usa en el render) porque
  // el selector de colaborador del modal "Nueva evaluación" (Task 10) la
  // necesita ya poblada al abrir.
  async function cargarColaboradores() {
    const res = await fetch(`${BASE}/api/get_colaboradores.php`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Error colaboradores');
    colaboradores = (data.data || []).filter(c =>
      c.activo === 1 && String(c.funcion_principal || '').trim().toUpperCase() === 'ASISTENTE DE ESTIBA'
      && (USER_ROL !== 'Coordinador' || Number(c.coordinador_id) === USER_ID)
    );
  }

  $('evSearch').addEventListener('input', e => { query = e.target.value; render(); });
  $('evFiltroPeriodo').addEventListener('change', e => { filtroPeriodo = e.target.value; render(); });
  $('evFiltroClas').addEventListener('change', e => { filtroClas = e.target.value; render(); });
  $('evTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action]'); if (!b) return;
    if (b.dataset.action === 'open-block') openBlock(b.dataset.id);
  });

  async function eliminar(id) {
    const ev = evaluaciones.find(x => Number(x.id) === Number(id));
    if (!ev) return;
    if (!confirm(`¿Eliminar la evaluación EVADES de "${ev.colaborador_nombre}" (${ev.periodo})?`)) return;
    try {
      const res = await fetch(`${BASE}/api/delete_evades.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id) }),
      });
      const data = await res.json();
      if (data.success) { toast('Evaluación eliminada'); cargarEvaluaciones(); }
      else { toast(data.error || 'Error al eliminar', 'error'); }
    } catch (e) { toast('Error de red', 'error'); }
  }

  cargarEvaluaciones();

  // ════════════════ GENERACIÓN Y APERTURA DE BLOQUES ════════════════
  let blockPreview = null;
  async function cargarCoordinadoresBloque() {
    if (USER_ROL === 'Coordinador') return;
    const res = await fetch(`${BASE}/api/get_coordinadores.php`, { cache:'no-store' });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'No se pudieron cargar coordinadores');
    $('evBlockCoordField').style.display = '';
    $('evBlockCoordinador').innerHTML = '<option value="">Selecciona…</option>' +
      (data.data || []).map(c => `<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
  }
  function openBlockCreate() {
    blockPreview = null;
    $('evBlockPuesto').value = '';
    $('evBlockPeriodo').innerHTML = '<option value="">Selecciona…</option>' + PERIODOS.map(p => `<option value="${p}">${p}</option>`).join('');
    $('evBlockCoordinador').value = '';
    $('evBlockPreviewCount').textContent = '—';
    $('evCoverageMetrics').style.display = 'none';
    $('evBlockPreviewList').innerHTML = '<div class="ev-sub" style="padding:14px">Selecciona los datos para consultar la nómina.</div>';
    $('evBlockGenerate').disabled = true;
    $('evBlockCreateBack').classList.add('open');
    cargarCoordinadoresBloque().catch(e => toast(e.message, 'error'));
  }
  function closeBlockCreate() { $('evBlockCreateBack').classList.remove('open'); }
  function renderCoveragePreview(data) {
    const summary = EvadesBloquesModel.coverageSummary(data.cobertura);
    $('evCoverageMetrics').style.display = '';
    $('evCoverageMetrics').innerHTML = `
      <div class="ev-coverage-metric"><b>${data.total_colaboradores}</b><span>Personas</span></div>
      <div class="ev-coverage-metric good"><b>${summary.sufficient}</b><span>Cobertura suficiente</span></div>
      <div class="ev-coverage-metric warn"><b>${summary.partial}</b><span>Cobertura parcial</span></div>
      <div class="ev-coverage-metric missing"><b>${summary.missing}</b><span>Sin fuente</span></div>`;
    $('evBlockPreviewList').innerHTML = data.colaboradores.length
      ? data.colaboradores.map(c => {
          const cv = c.cobertura || {};
          return `<div class="ev-preview-person"><div class="ev-preview-person-main"><strong>${esc(c.nombre)}</strong><br><small>${esc(c.codigo || 'Sin código')} · ${esc(c.funcion_principal)}</small></div><div class="ev-preview-person-side"><strong>${Number(c.puntaje_estimado || 60)}/100</strong><div class="ev-mini-coverage" title="${Number(cv.suficiente || 0)} suficientes · ${Number(cv.parcial || 0)} parciales · ${Number(cv.sin_fuente || 0)} sin fuente"><i class="good"></i><i class="warn"></i><i class="missing"></i></div></div></div>`;
        }).join('')
      : '<div class="ev-sub" style="padding:14px">No hay personal activo asignado a este coordinador y puesto.</div>';
  }
  async function previewBlock() {
    const puesto = $('evBlockPuesto').value;
    const periodo = $('evBlockPeriodo').value;
    const coordinador_id = USER_ROL === 'Coordinador' ? 0 : Number($('evBlockCoordinador').value || 0);
    if (!puesto || !periodo || (USER_ROL !== 'Coordinador' && !coordinador_id)) return;
    $('evBlockPreviewCount').textContent = '…';
    try {
      const res = await fetch(`${BASE}/api/preview_evades_bloque.php`, { method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ puesto,periodo,coordinador_id }) });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'No se pudo consultar la nómina');
      blockPreview = data;
      $('evBlockPreviewCount').textContent = data.total_colaboradores;
      renderCoveragePreview(data);
      if (data.bloque_existente_id) $('evBlockPreviewList').insertAdjacentHTML('afterbegin', '<div class="ev-sub" style="padding:12px;color:#a15c00;background:#fff4d8">Este bloque ya fue generado. Ábrelo desde la lista.</div>');
      $('evBlockGenerate').disabled = !data.total_colaboradores || Boolean(data.bloque_existente_id);
    } catch (e) {
      blockPreview = null; $('evBlockPreviewCount').textContent = '—'; $('evCoverageMetrics').style.display = 'none'; $('evBlockGenerate').disabled = true; toast(e.message, 'error');
    }
  }
  async function generateBlock() {
    if (!blockPreview) return;
    const btn = $('evBlockGenerate'); btn.disabled = true; btn.textContent = 'Calculando bloque…';
    try {
      const payload = { puesto:blockPreview.puesto,periodo:blockPreview.periodo,coordinador_id:blockPreview.coordinador.id };
      const res = await fetch(`${BASE}/api/generar_evades_bloque.php`, { method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload) });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'No se pudo generar el bloque');
      closeBlockCreate(); await cargarEvaluaciones(); toast(`Bloque generado con ${data.data.total_colaboradores} evaluaciones`); openBlock(data.data.id);
    } catch (e) { toast(e.message, 'error'); }
    finally { btn.textContent = 'Generar evaluaciones'; btn.disabled = false; }
  }
  function renderBlockRail() {
    if (!currentBlock) return;
    const estados = ['generado','revisado','modificado','cerrado'];
    const actual = estados.indexOf(currentBlock.estado);
    $('evBlockRoute').innerHTML = estados.map((s,i) => `<div class="ev-route-step ${i < actual ? 'done' : ''} ${i === actual ? 'current' : ''}">${s}</div>`).join('');
    $('evBlockRoster').innerHTML = (currentBlock.evaluaciones || []).map(ev => `<button class="ev-roster-btn ${workspaceState && Number(workspaceState.selectedId) === Number(ev.id) ? 'active' : ''}" data-eval-id="${ev.id}"><strong>${esc(ev.colaborador_nombre)}</strong><span><em>${ev.completa ? 'Completa' : 'Pendiente'}</em><b>${ev.puntaje_total}/100</b></span></button>`).join('');
    $('evBlockRosterMobile').innerHTML = (currentBlock.evaluaciones || []).map(ev => `<option value="${ev.id}" ${workspaceState && Number(workspaceState.selectedId) === Number(ev.id) ? 'selected' : ''}>${esc(ev.colaborador_nombre)} · ${ev.puntaje_total}/100</option>`).join('');
    $('evBlockContext').style.display = '';
    $('evBlockRoster').style.display = '';
    $('evBlockClose').style.display = currentBlock.estado === 'cerrado' ? 'none' : '';
    $('evBlockPdf').style.display = '';
    $('evBlockReport').style.display = '';
    $('evBlockPrev').style.display = ''; $('evBlockNext').style.display = '';
    const selectedIndex = (currentBlock.evaluaciones || []).findIndex(ev => workspaceState && Number(ev.id) === Number(workspaceState.selectedId));
    $('evBlockPrev').disabled = selectedIndex <= 0;
    $('evBlockNext').disabled = selectedIndex < 0 || selectedIndex >= currentBlock.evaluaciones.length - 1;
  }
  async function loadBlockDetail(id) {
    const res = await fetch(`${BASE}/api/get_evades_bloques.php?id=${Number(id)}`, { cache:'no-store' });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'No se pudo cargar el bloque');
    currentBlock = data.data;
    return currentBlock;
  }
  async function openBlock(id) {
    try {
      const opened = await fetch(`${BASE}/api/abrir_evades_bloque.php`, { method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ id:Number(id) }) });
      const openedData = await opened.json();
      if (!openedData.success) throw new Error(openedData.error || 'No se pudo abrir el bloque');
      await loadBlockDetail(id);
      workspaceState = EvadesBloquesModel.createWorkspace(currentBlock);
      if (!currentBlock.evaluaciones.length) throw new Error('El bloque no contiene evaluaciones');
      await openEdit(workspaceState.selectedId, true);
    } catch (e) { toast(e.message, 'error'); }
  }
  async function selectBlockEvaluation(id) {
    if (!workspaceState) return;
    const next = EvadesBloquesModel.selectEvaluation(workspaceState, Number(id));
    if (next.needsConfirmation && !confirm('Hay cambios sin guardar. ¿Descartarlos y cambiar de persona?')) return;
    workspaceState = next.needsConfirmation ? EvadesBloquesModel.confirmSelection(next) : next;
    await openEdit(workspaceState.selectedId, true);
  }
  $('evBlockRoster').addEventListener('click', e => { const b=e.target.closest('[data-eval-id]'); if (b) selectBlockEvaluation(b.dataset.evalId); });
  $('evBlockRosterMobile').addEventListener('change', e => selectBlockEvaluation(e.target.value));
  $('evBlockPuesto').addEventListener('change', previewBlock); $('evBlockPeriodo').addEventListener('change', previewBlock); $('evBlockCoordinador').addEventListener('change', previewBlock);
  $('evBlockCreateX').addEventListener('click', closeBlockCreate); $('evBlockCreateCancel').addEventListener('click', closeBlockCreate); $('evBlockGenerate').addEventListener('click', generateBlock);
  $('evBlockCreateBack').addEventListener('click', e => { if (e.target === $('evBlockCreateBack')) closeBlockCreate(); });

  // ════════════════ MODAL: nueva evaluación ════════════════
  let sugerenciaActual = null; // respuesta cruda de calcular_evades.php
  let filasEstado = {}; // competencia_key -> {incremento_final, descuento_final, motivo_ajuste}
  let editingId = null;
  let currentEvaluation = null;

  function poblarSelectColaborador() {
    const sel = $('evm-colaborador');
    sel.innerHTML = '<option value="">Selecciona…</option>' +
      colaboradores.map(c => `<option value="${c.id}">${esc(c.nombre)} (${esc(c.codigo || '')})</option>`).join('');
  }
  function poblarSelectPeriodo() {
    const sel = $('evm-periodo');
    sel.innerHTML = '<option value="">Selecciona…</option>' +
      PERIODOS.map(p => `<option value="${p}">${p}</option>`).join('');
  }

  function openModal(isBlock = false) {
    if (!isBlock) { currentBlock = null; workspaceState = null; }
    document.querySelectorAll('#evModalBack .ed-form-body select,#evModalBack .ed-form-body input,#evModalBack .ed-form-body textarea').forEach(el => { el.disabled = false; });
    editingId = null;
    $('evModalTitle').textContent = 'Nueva evaluación EVADES';
    $('evModalCancel').textContent = 'Cancelar';
    $('evRailFolio').textContent = 'NUEVO';
    $('evm-id').value = '';
    $('evm-colaborador').value = '';
    $('evm-periodo').value = '';
    $('evm-fecha').value = new Date().toISOString().slice(0, 10);
    $('evm-fortalezas').value = '';
    $('evm-aspectos').value = '';
    $('evm-plan').value = '';
    $('evRailName').textContent = 'Sin evaluado';
    $('evRailCargo').textContent = 'Selecciona colaborador y período';
    $('evRailAvatar').textContent = '—';
    $('evRailCount').textContent = '0';
    $('evm-colaborador').disabled = false;
    $('evm-periodo').disabled = false;
    $('evSecCompetencias').style.display = 'none';
    $('evSecCompetenciasB').style.display = 'none';
    $('evAppreciationPanel').style.display = 'none';
    $('evSecFeedback').style.display = 'none';
    $('evWorkspaceTabs').style.display = 'none';
    $('evModalSave').style.display = 'none';
    sugerenciaActual = null;
    currentEvaluation = null;
    filasEstado = {};
    poblarSelectColaborador();
    poblarSelectPeriodo();
    $('evSecIdentity').style.display = isBlock ? 'none' : '';
    if (!isBlock) {
      $('evBlockContext').style.display = 'none'; $('evBlockRoster').style.display = 'none';
      $('evBlockClose').style.display = 'none'; $('evBlockPdf').style.display = 'none';
      $('evBlockReport').style.display = 'none';
      $('evBlockPrev').style.display = 'none'; $('evBlockNext').style.display = 'none'; $('evBlockRosterMobile').style.display = 'none';
    } else {
      $('evBlockRosterMobile').style.display = '';
    }
    $('evModalBack').classList.add('open');
  }
  function closeModal() {
    if (workspaceState && workspaceState.dirty && !confirm('Hay cambios sin guardar. ¿Cerrar y descartarlos?')) return;
    $('evModalBack').classList.remove('open');
  }

  async function calcularSugerencias() {
    const colaboradorId = Number($('evm-colaborador').value || 0);
    const periodo = $('evm-periodo').value;
    if (!colaboradorId) { toast('Selecciona el colaborador', 'error'); return; }
    if (!periodo) { toast('Selecciona el trimestre', 'error'); return; }

    const btn = $('evBtnCalcular');
    btn.disabled = true; btn.textContent = 'Calculando…';
    try {
      const res = await fetch(`${BASE}/api/calcular_evades.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ colaborador_id: colaboradorId, periodo }),
      });
      let data;
      try { data = await res.json(); }
      catch (e) { toast(`Error del servidor (HTTP ${res.status})`, 'error'); return; }
      if (!data.success) { toast(data.error || 'No se pudo calcular', 'error'); return; }
      sugerenciaActual = data;
      filasEstado = {};
      (data.competencias || []).forEach(f => {
        filasEstado[f.competencia_key] = {
          incremento_final: f.auto_incremento ?? 0,
          descuento_final: f.auto_descuento ?? 0,
          motivo_ajuste: '',
        };
      });
      $('evRailName').textContent = data.colaborador.nombre;
      $('evRailCargo').textContent = data.colaborador.cargo || '—';
      $('evRailAvatar').textContent = (data.colaborador.nombre || '').trim().split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase();
      renderCompetencias();
      $('evSecCompetencias').style.display = '';
      $('evSecCompetenciasB').style.display = '';
      $('evSecFeedback').style.display = '';
      $('evWorkspaceTabs').style.display = '';
      setWorkspaceTab('competencias');
      $('evModalSave').style.display = '';
      // Bloqueados desde aquí: las sugerencias, la evidencia y los ajustes
      // ya calculados corresponden a ESTE colaborador/período. Cambiarlos
      // ahora dejaría el formulario mostrando datos de una combinación
      // distinta a la que realmente se guardaría.
      $('evm-colaborador').disabled = true;
      $('evm-periodo').disabled = true;
    } catch (e) {
      toast('Error de red', 'error');
    } finally {
      btn.disabled = false; btn.textContent = 'Calcular sugerencias';
    }
  }

  const INCREMENTOS = [0, 2, 4];
  const DESCUENTOS = [0, 2, 4, 6, 8, 10];

  function labelEvidencia(ev) {
    return EvadesBloquesModel.evidenceLabel(ev);
  }

  // true si la competencia TIENE catálogo de automatización (reconocimiento
  // y/o incidencia), sin importar si esta fila trajo evidencia este
  // trimestre. Debe leerse del catálogo estático (COMPETENCIAS), no de si
  // f.auto_incremento/auto_descuento vinieron null — null también significa
  // "tiene catálogo pero no hubo evidencia este trimestre", que igual exige
  // motivo si el coordinador se aparta del 0 implícito (así valida el
  // servidor en api/save_evades.php).
  function evadesTieneAuto(competenciaKey) {
    const meta = COMPETENCIAS[competenciaKey];
    if (!meta) return false;
    return meta.automatizada === true;
  }

  function filaHtml(f) {
    const est = filasEstado[f.competencia_key];
    const tieneAuto = evadesTieneAuto(f.competencia_key);
    const autoIncremento = f.auto_incremento_actual ?? f.auto_incremento ?? 0;
    const autoDescuento = f.auto_descuento_actual ?? f.auto_descuento ?? 0;
    const esSugerido = tieneAuto && est.incremento_final === autoIncremento && est.descuento_final === autoDescuento;
    const formula = EvadesBloquesModel.scoreFormula(est.incremento_final, est.descuento_final);
    const evidencias = f.evidencia_actual || f.evidencia || [];
    const evidenciaHtml = evidencias.map(e => `<div class="ev-evidence-item ${e.es_cruce ? 'cross' : ''}">${esc(labelEvidencia(e))}</div>`).join('') || '<div class="ev-evidence-item">No se encontraron registros durante el trimestre.</div>';
    const cobertura = f.cobertura || (evidencias.length ? 'suficiente' : (tieneAuto ? 'parcial' : 'sin_fuente'));
    const resumen = f.resumen_calculo || (tieneAuto ? 'Sin evidencia suficiente; conserva la base 6.' : 'Requiere apreciación documentada del coordinador.');

    return `
      <div class="ed-crit-row ev-competency-card" data-key="${esc(f.competencia_key)}">
        <div>
          <div class="ev-competency-top"><span class="ed-crit-item">${esc(f.label)}</span><span class="ev-coverage-badge ${esc(cobertura)}">${esc(cobertura.replace('_', ' '))}</span></div>
          <div class="ev-score-formula"><span>Base</span><b>6</b><span>+</span><b class="positive">${formula.increment}</b><span>−</span><b class="negative">${formula.discount}</b><span>=</span><b class="result">${formula.final}</b></div>
          <div class="ev-engine-why"><strong>Por qué:</strong> ${esc(resumen)}</div>
          <div class="ev-evidence-list">${evidenciaHtml}</div>
        </div>
        <div class="ed-field">
          <label>Incremento</label>
          <select class="ev-inc" data-key="${esc(f.competencia_key)}">
            ${INCREMENTOS.map(v => `<option value="${v}" ${est.incremento_final === v ? 'selected' : ''}>+${v}</option>`).join('')}
          </select>
        </div>
        <div class="ed-field">
          <label>Descuento</label>
          <select class="ev-desc" data-key="${esc(f.competencia_key)}">
            ${DESCUENTOS.map(v => `<option value="${v}" ${est.descuento_final === v ? 'selected' : ''}>-${v}</option>`).join('')}
          </select>
        </div>
        <div class="ed-field">
          <label>Final</label>
          <div class="ed-resumen-score" id="finalScore-${esc(f.competencia_key)}">${formula.final}</div>
        </div>
        ${(!esSugerido && tieneAuto) ? `
        <div class="ed-field" style="grid-column:1 / -1">
          <label>Motivo del ajuste (obligatorio)</label>
          <textarea class="ev-motivo" data-key="${esc(f.competencia_key)}" placeholder="Explica por qué se aparta de la sugerencia…">${esc(est.motivo_ajuste || '')}</textarea>
        </div>` : ''}
      </div>`;
  }

  function renderCompetencias() {
    if (!sugerenciaActual) return;
    const filas = sugerenciaActual.competencias;
    const a = filas.filter(f => f.tipo === 'conductual');
    const b = filas.filter(f => f.tipo === 'operativa');
    $('evCompA').innerHTML = a.map(filaHtml).join('');
    $('evCompB').innerHTML = b.map(filaHtml).join('');
    actualizarTotales();
  }

  function setWorkspaceTab(tab) {
    const feedback = tab === 'feedback';
    $('evSecCompetencias').style.display = feedback ? 'none' : '';
    $('evSecCompetenciasB').style.display = feedback ? 'none' : '';
    $('evAppreciationPanel').style.display = !feedback && currentBlock && currentBlock.estado !== 'cerrado' ? '' : 'none';
    $('evSecFeedback').style.display = feedback ? '' : 'none';
    document.querySelectorAll('[data-ev-tab]').forEach(button => button.classList.toggle('active', button.dataset.evTab === tab));
  }

  $('evWorkspaceTabs').addEventListener('click', e => {
    const button = e.target.closest('[data-ev-tab]');
    if (button) setWorkspaceTab(button.dataset.evTab);
  });

  function actualizarTotales() {
    if (!sugerenciaActual) return;
    let total = 0;
    sugerenciaActual.competencias.forEach(f => {
      const est = filasEstado[f.competencia_key];
      const puntajeFinal = Math.max(0, Math.min(10, 6 + est.incremento_final - est.descuento_final));
      total += puntajeFinal;
      const el = $('finalScore-' + f.competencia_key);
      if (el) el.textContent = puntajeFinal;
    });
    $('evTotalScore').textContent = `${total} / 100`;
    $('evRailCount').textContent = total;
  }

  document.addEventListener('change', e => {
    const sel = e.target.closest('.ev-inc, .ev-desc');
    if (!sel) return;
    const key = sel.dataset.key;
    if (!filasEstado[key]) return;
    if (sel.classList.contains('ev-inc')) filasEstado[key].incremento_final = Number(sel.value);
    if (sel.classList.contains('ev-desc')) filasEstado[key].descuento_final = Number(sel.value);
    renderCompetencias();
  });
  document.addEventListener('input', e => {
    const ta = e.target.closest('.ev-motivo');
    if (!ta) return;
    const key = ta.dataset.key;
    if (filasEstado[key]) filasEstado[key].motivo_ajuste = ta.value;
  });

  function renderAppreciations(rows) {
    $('evAppList').innerHTML = (rows || []).length
      ? rows.map(row => `<div class="ev-appreciation-item"><b>${esc(COMPETENCIAS[row.competencia_key]?.label || row.competencia_key)}</b> · ${row.direccion === 'positiva' ? `+${row.nivel}` : `impacto ${esc(row.impacto)}`}<br>${esc(row.descripcion)}</div>`).join('')
      : '<div class="ev-sub">Todavía no hay apreciaciones documentadas para esta persona.</div>';
  }

  function renderAppreciationValueOptions() {
    const negativa = $('evAppDireccion').value === 'negativa';
    $('evAppValor').innerHTML = negativa
      ? '<option value="minimo">Mínimo</option><option value="bajo">Bajo</option><option value="moderado">Moderado</option><option value="alto">Alto</option><option value="critico">Crítico</option>'
      : '<option value="2">+2 · evidencia buena</option><option value="4">+4 · evidencia excelente</option>';
  }

  async function saveAppreciation() {
    if (!currentBlock || !editingId || currentBlock.estado === 'cerrado') return;
    const direction = $('evAppDireccion').value;
    const description = $('evAppDescripcion').value.trim();
    if (!description) { toast('Describe la evidencia observada', 'error'); return; }
    const payload = {
      evaluacion_id: editingId,
      competencia_key: $('evAppCompetencia').value,
      direccion: direction,
      descripcion: description,
    };
    if (direction === 'positiva') payload.nivel = Number($('evAppValor').value);
    else payload.impacto = $('evAppValor').value;
    const button = $('evAppSave'); button.disabled = true; button.textContent = 'Agregando…';
    try {
      const response = await fetch(`${BASE}/api/save_evades_apreciacion.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
      const data = await response.json();
      if (!data.success) throw new Error(data.error || 'No se pudo guardar la apreciación');
      const blockId = currentBlock.id;
      await loadBlockDetail(blockId);
      workspaceState = EvadesBloquesModel.createWorkspace(currentBlock);
      workspaceState.selectedId = editingId;
      await openEdit(editingId, true);
      const suggestion = sugerenciaActual.competencias.find(row => row.competencia_key === payload.competencia_key);
      if (suggestion && filasEstado[payload.competencia_key]) {
        filasEstado[payload.competencia_key].incremento_final = suggestion.auto_incremento_actual ?? suggestion.auto_incremento ?? 0;
        filasEstado[payload.competencia_key].descuento_final = suggestion.auto_descuento_actual ?? suggestion.auto_descuento ?? 0;
        renderCompetencias();
        workspaceState = EvadesBloquesModel.markDirty(workspaceState);
      }
      $('evAppDescripcion').value = '';
      toast('Apreciación registrada. Revisa el nuevo cálculo y guarda la ficha.');
    } catch (error) { toast(error.message, 'error'); }
    finally { button.disabled = false; button.textContent = 'Agregar evidencia'; }
  }

  $('evAppDireccion').addEventListener('change', renderAppreciationValueOptions);
  $('evAppSave').addEventListener('click', saveAppreciation);

  $('btnNew').addEventListener('click', openBlockCreate);
  $('evModalClose').addEventListener('click', closeModal);
  $('evModalCancel').addEventListener('click', closeModal);
  $('evModalBack').addEventListener('click', e => { if (e.target === $('evModalBack')) closeModal(); });
  $('evBtnCalcular').addEventListener('click', calcularSugerencias);

  async function guardar() {
    if (!sugerenciaActual) { toast('Calcula las sugerencias antes de guardar', 'error'); return; }
    const competencias = Object.keys(filasEstado).map(key => ({
      competencia_key: key,
      incremento_final: filasEstado[key].incremento_final,
      descuento_final: filasEstado[key].descuento_final,
      motivo_ajuste: filasEstado[key].motivo_ajuste || '',
    }));
    const payload = {
      id: editingId || 0,
      colaborador_id: Number($('evm-colaborador').value || 0),
      periodo: $('evm-periodo').value,
      fecha_evaluacion: $('evm-fecha').value,
      competencias,
      fortalezas: $('evm-fortalezas').value.trim(),
      aspectos_mejora: $('evm-aspectos').value.trim(),
      plan_accion: $('evm-plan').value.trim(),
    };
    if (currentBlock) { payload.bloque_id = currentBlock.id; payload.version = currentBlock.version; }
    const btn = $('evModalSave');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res = await fetch(`${BASE}/api/save_evades.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (data.success) {
        if (currentBlock) {
          const blockId = currentBlock.id, selectedId = editingId;
          await loadBlockDetail(blockId);
          workspaceState = EvadesBloquesModel.createWorkspace(currentBlock);
          workspaceState.selectedId = selectedId;
          await openEdit(selectedId, true);
          await cargarEvaluaciones();
          toast('Cambios guardados en el bloque');
        } else {
          toast(editingId ? 'Evaluación actualizada' : 'Evaluación registrada');
          closeModal(); cargarEvaluaciones();
        }
      } else {
        toast(data.error || 'Error al guardar', 'error');
      }
    } catch (e) {
      toast('Error de red', 'error');
    } finally {
      btn.disabled = false; btn.textContent = 'Guardar evaluación';
    }
  }
  $('evModalSave').addEventListener('click', guardar);
  $('evModalBack').addEventListener('input', e => {
    if (currentBlock && workspaceState && e.target.closest('.ed-form-body')) workspaceState = EvadesBloquesModel.markDirty(workspaceState);
  });
  $('evModalBack').addEventListener('change', e => {
    if (currentBlock && workspaceState && e.target.closest('.ed-form-body')) workspaceState = EvadesBloquesModel.markDirty(workspaceState);
  });
  $('evBlockPdf').addEventListener('click', () => { if (editingId) exportarPDF(editingId); });
  $('evBlockReport').addEventListener('click', () => { if (editingId) openView(editingId); });
  $('evBlockPrev').addEventListener('click', () => {
    if (!currentBlock) return; const i=currentBlock.evaluaciones.findIndex(ev=>Number(ev.id)===Number(editingId)); if (i>0) selectBlockEvaluation(currentBlock.evaluaciones[i-1].id);
  });
  $('evBlockNext').addEventListener('click', () => {
    if (!currentBlock) return; const i=currentBlock.evaluaciones.findIndex(ev=>Number(ev.id)===Number(editingId)); if (i>=0 && i<currentBlock.evaluaciones.length-1) selectBlockEvaluation(currentBlock.evaluaciones[i+1].id);
  });
  $('evBlockClose').addEventListener('click', async () => {
    if (!currentBlock || currentBlock.estado === 'cerrado') return;
    if (workspaceState && workspaceState.dirty) { toast('Guarda los cambios pendientes antes de cerrar', 'error'); return; }
    if (!confirm('¿Cerrar todo el bloque? Después no podrá modificarse.')) return;
    const btn = $('evBlockClose'); btn.disabled = true; btn.textContent = 'Cerrando…';
    try {
      const res = await fetch(`${BASE}/api/cerrar_evades_bloque.php`, { method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ id:currentBlock.id,version:currentBlock.version }) });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'No se pudo cerrar el bloque');
      const selectedId = editingId;
      await loadBlockDetail(currentBlock.id);
      workspaceState = EvadesBloquesModel.createWorkspace(currentBlock); workspaceState.selectedId = selectedId;
      await openEdit(selectedId, true); await cargarEvaluaciones(); toast('Bloque cerrado. La evaluación quedó en solo lectura.');
    } catch (e) { toast(e.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Cerrar bloque'; }
  });

  async function openEdit(id, fromBlock = false) {
    openModal(fromBlock);
    editingId = Number(id);
    $('evModalTitle').textContent = 'Editar evaluación EVADES';
    $('evRailFolio').textContent = 'N° ' + String(id).padStart(4, '0');

    let data;
    try {
      const res = await fetch(`${BASE}/api/get_evades.php?id=${Number(id)}`, { cache: 'no-store' });
      data = await res.json();
    } catch (e) {
      toast('No se pudo cargar la evaluación', 'error');
      closeModal();
      return;
    }
    if (!data.success) { toast(data.error || 'No se pudo cargar', 'error'); closeModal(); return; }
    const ev = data.data;
    currentEvaluation = ev;

    // Igual que en calcularSugerencias(): las 10 filas cargadas son de este
    // colaborador/período puntual, cambiar el select los desincronizaría.
    $('evm-colaborador').disabled = true;
    $('evm-periodo').disabled = true;

    $('evm-id').value = ev.id;
    $('evm-colaborador').value = ev.colaborador_id ?? '';
    $('evm-periodo').value = ev.periodo;
    $('evm-fecha').value = ev.fecha_evaluacion;
    $('evm-fortalezas').value = ev.fortalezas || '';
    $('evm-aspectos').value = ev.aspectos_mejora || '';
    $('evm-plan').value = ev.plan_accion || '';
    $('evRailName').textContent = ev.colaborador_nombre;
    $('evRailCargo').textContent = ev.colaborador_cargo || '—';
    $('evRailAvatar').textContent = (ev.colaborador_nombre || '').trim().split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase();

    sugerenciaActual = {
      colaborador: { id: ev.colaborador_id, nombre: ev.colaborador_nombre, cargo: ev.colaborador_cargo },
      periodo: ev.periodo,
      competencias: ev.competencias.map(c => ({
        competencia_key: c.competencia_key,
        label: COMPETENCIAS[c.competencia_key]?.label || c.competencia_key,
        tipo: c.tipo,
        base: c.base,
        auto_incremento: c.auto_incremento,
        auto_descuento: c.auto_descuento,
        auto_incremento_actual: c.auto_incremento_actual,
        auto_descuento_actual: c.auto_descuento_actual,
        cobertura: c.cobertura,
        regla: c.regla,
        resumen_calculo: c.resumen_calculo,
        evidencia: c.evidencia,
        evidencia_actual: c.evidencia_actual,
      })),
    };
    filasEstado = {};
    ev.competencias.forEach(c => {
      filasEstado[c.competencia_key] = {
        incremento_final: c.incremento_final,
        descuento_final: c.descuento_final,
        motivo_ajuste: c.motivo_ajuste || '',
      };
    });
    renderCompetencias();
    $('evSecCompetencias').style.display = '';
    $('evSecCompetenciasB').style.display = '';
    $('evSecFeedback').style.display = '';
    $('evWorkspaceTabs').style.display = '';
    $('evAppCompetencia').innerHTML = COMP_KEYS.map(key => `<option value="${esc(key)}">${esc(COMPETENCIAS[key].label)}</option>`).join('');
    renderAppreciations(ev.apreciaciones || []);
    setWorkspaceTab('competencias');
    $('evModalSave').style.display = '';
    if (fromBlock && currentBlock) {
      $('evModalTitle').textContent = `${currentBlock.puesto} · ${currentBlock.periodo}`;
      $('evRailFolio').textContent = currentBlock.estado.toUpperCase();
      renderBlockRail();
      const locked = currentBlock.estado === 'cerrado';
      document.querySelectorAll('#evModalBack .ed-form-body select,#evModalBack .ed-form-body input,#evModalBack .ed-form-body textarea').forEach(el => { el.disabled = locked; });
      $('evModalSave').style.display = locked ? 'none' : '';
      $('evModalCancel').textContent = 'Cerrar vista';
    }
  }

  $('evTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action="edit"]'); if (!b) return;
    openEdit(b.dataset.id);
  });

  async function openView(id) {
    let data;
    try {
      const res = await fetch(`${BASE}/api/get_evades.php?id=${Number(id)}`, { cache: 'no-store' });
      data = await res.json();
    } catch (e) {
      toast('No se pudo cargar la evaluación', 'error');
      return;
    }
    if (!data.success) { toast(data.error || 'No se pudo cargar', 'error'); return; }
    const ev = data.data;

    const filaVista = (c) => {
      const meta = COMPETENCIAS[c.competencia_key] || { label: c.competencia_key };
      const evid = (c.evidencia || []).map(e => `<div class="ev-sub">• ${esc(labelEvidencia(e))}</div>`).join('') || '<div class="ev-sub">Sin evidencia automática.</div>';
      return `
        <div class="ed-view-crit-row">
          <div>
            <span class="ed-crit-item">${esc(meta.label)}</span>
            ${evid}
            ${c.motivo_ajuste ? `<div class="ev-sub"><b>Motivo del ajuste:</b> ${esc(c.motivo_ajuste)}</div>` : ''}
          </div>
          <div class="ed-resumen-score">${c.puntaje_final} / 10</div>
        </div>`;
    };
    const seccionA = ev.competencias.filter(c => c.tipo === 'conductual').map(filaVista).join('');
    const seccionB = ev.competencias.filter(c => c.tipo === 'operativa').map(filaVista).join('');

    const timeline = currentBlock && Array.isArray(currentBlock.historial_estados)
      ? currentBlock.historial_estados.map(item => `<div class="ev-report-event"><b>${esc(item.estado_nuevo)}</b> · ${esc(item.contexto || '')}<br>${esc(item.created_at || '')}</div>`).join('')
      : '<div class="ev-report-event">Sin historial de bloque disponible.</div>';
    $('evViewBody').innerHTML = `
      <div class="ev-view-report-head">
        <div><span class="ed-rail-kicker">INFORME INDIVIDUAL · ${esc(ev.periodo)}</span><h3>${esc(ev.colaborador_nombre)}</h3><p>${esc(ev.colaborador_cargo || '—')} · Coordinador: ${esc(ev.coordinador_nombre)}</p></div>
        <div class="ev-report-score"><b>${ev.puntaje_total}</b><span>${esc(ev.clasificacion)}</span></div>
      </div>
      <div class="ed-view-layout" style="grid-template-columns:150px 1fr">
        <div class="ed-view-sidebar">
          <div class="ed-rail-count">${ev.puntaje_total}</div>
          <span class="ed-rail-lbl" style="color:rgba(255,255,255,.7)">de 100</span>
          <hr class="iv-divider">
          <div class="iv-stat"><span class="iv-stat-k">Período</span><span class="iv-stat-v">${esc(ev.periodo)}</span></div>
          <div class="iv-stat"><span class="iv-stat-k">Clasificación</span><span class="iv-stat-v">${esc(ev.clasificacion)}</span></div>
          <div class="iv-stat"><span class="iv-stat-k">Variación</span><span class="iv-stat-v">${ev.variacion_pct == null ? '—' : (ev.variacion_pct >= 0 ? '+' : '') + ev.variacion_pct + '%'}</span></div>
          <div class="iv-eval"><span class="iv-stat-k">Coordinador</span><span class="iv-stat-v">${esc(ev.coordinador_nombre)}</span></div>
        </div>
        <div class="ed-view-main">
          <div>
            <p class="ed-view-name">${esc(ev.colaborador_nombre)}</p>
            <p class="ed-view-cargo">${esc(ev.colaborador_cargo || '—')}</p>
          </div>
          <hr class="ed-view-divider">
          <div class="ed-view-cat">SECCIÓN A · COMPETENCIAS CONDUCTUALES</div>
          <div class="ed-view-crit-list">${seccionA}</div>
          <div class="ed-view-cat">SECCIÓN B · COMPETENCIAS OPERATIVAS</div>
          <div class="ed-view-crit-list">${seccionB}</div>
          <div class="ed-view-notes"><span class="iv-k">Fortalezas observadas</span><span class="iv-v">${esc(ev.fortalezas || '—')}</span></div>
          <div class="ed-view-notes"><span class="iv-k">Aspectos a mejorar</span><span class="iv-v">${esc(ev.aspectos_mejora || '—')}</span></div>
          <div class="ed-view-notes"><span class="iv-k">Plan de acción</span><span class="iv-v">${esc(ev.plan_accion || '—')}</span></div>
          <div class="ed-view-notes"><span class="iv-k">Historial del expediente</span><div class="ev-report-timeline">${timeline}</div></div>
        </div>
      </div>`;

    $('evViewSub').textContent = `${ev.colaborador_nombre} · ${ev.periodo}`;
    $('evViewEdit').dataset.id = ev.id;
    $('evViewEdit').dataset.block = currentBlock ? '1' : '0';
    $('evViewEdit').style.display = currentBlock && currentBlock.estado === 'cerrado' ? 'none' : '';
    $('evViewPdf').dataset.id = ev.id;
    $('evViewBack').classList.add('open');
  }
  function closeView() { $('evViewBack').classList.remove('open'); }

  $('evTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action="view"]'); if (!b) return;
    openView(b.dataset.id);
  });
  $('evViewClose').addEventListener('click', closeView);
  $('evViewCloseBtn').addEventListener('click', closeView);
  $('evViewBack').addEventListener('click', e => { if (e.target === $('evViewBack')) closeView(); });
  $('evViewEdit').addEventListener('click', e => { const fromBlock = e.currentTarget.dataset.block === '1'; closeView(); openEdit(e.currentTarget.dataset.id, fromBlock); });

  const EV_NUM = {
    dominio_solido: 1, autonomia: 2, organizacion_tiempo: 3, adaptabilidad: 4, productividad: 5,
    comunicacion_colaboracion: 6, iniciativa_compromiso: 7, disciplina_profesional: 8, eficiencia: 9, seguridad_trabajo: 10,
  };
  const EV_DESC_B = {
    dominio_solido: '(errores: pedeteo, balanzas, USR, CDR, PS)',
    comunicacion_colaboracion: '(continuidad operativa: relevos, radios, info de turno)',
    iniciativa_compromiso: '(proyección operativa, sentido de urgencia)',
    disciplina_profesional: '(responsabilidad en funciones, asistencia a capacitaciones)',
    seguridad_trabajo: '(EPPs, orden y limpieza, cuidado de equipos)',
  };
  function evNivelLabel(f) {
    if (f <= 2) return 'Insatisfactorio';
    if (f <= 4) return 'Bajo';
    if (f <= 6) return 'Aceptable';
    if (f <= 8) return 'Bueno';
    return 'Excelente';
  }
  function evResumenEvidencia(c) {
    if (c.motivo_ajuste) return c.motivo_ajuste;
    const ev = c.evidencia || [];
    if (!ev.length) return '';
    return ev.map(e => {
      if (e.tipo === 'reconocimiento') return `Reconocimiento ${e.fecha} (${e.impacto})`;
      if (e.tipo === 'incidencia') return `Incidencia ${e.fecha} (${e.impacto})`;
      if (e.tipo === 'bono_evaluacion_diaria') return `Bono autonomía: ${e.n} eval., prom ${e.promedio}`;
      return '';
    }).filter(Boolean).join('; ');
  }
  function evFechaCorta(fecha) {
    if (!fecha) return '';
    const [y, m, d] = fecha.split('-');
    if (!y || !m || !d) return fecha;
    return `${d}/${m}/${y.slice(2)}`;
  }
  function evListaIncidencias(c) {
    const items = (c.evidencia || []).filter(e => e.tipo === 'incidencia');
    if (!items.length) return '';
    return items.map(e => {
      const texto = [e.punto_mejorar, e.detalle].map(t => (t || '').trim()).filter(Boolean).join(' — ');
      return `${evFechaCorta(e.fecha)} ${texto}`.trim();
    }).join('\n');
  }

  async function exportarPDF(id) {
    if (!window.jspdf) { toast('No se pudo cargar el generador de PDF', 'error'); return; }

    let data;
    try {
      const res = await fetch(`${BASE}/api/get_evades.php?id=${Number(id)}`, { cache: 'no-store' });
      data = await res.json();
    } catch (e) {
      toast('No se pudo cargar la evaluación', 'error');
      return;
    }
    if (!data.success) { toast(data.error || 'No se pudo cargar', 'error'); return; }
    const ev = data.data;

    try {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'portrait' });
      const W = doc.internal.pageSize.getWidth();
      const M = 34;
      const CW = W - M * 2;
      let y = 30;

      // Paleta idéntica al archivo "formato evades.xlsx"
      const C_TITLE = [31, 78, 120];     // 1F4E78
      const C_SECTION = [46, 117, 182];  // 2E75B6
      const C_LABEL = [217, 225, 242];   // D9E1F2
      const C_ROW_A = [226, 239, 218];   // E2EFDA
      const C_ROW_B = [252, 228, 214];   // FCE4D6
      const C_INIT = [255, 242, 204];    // FFF2CC
      const C_NIVEL = [242, 242, 242];   // F2F2F2
      const C_SUB = [189, 215, 238];     // BDD7EE
      const C_TEXT = [17, 24, 39];

      const bar = (text, h, fill, textColor, size, align) => {
        doc.setFillColor(...fill); doc.rect(M, y, CW, h, 'F');
        doc.setFont('helvetica', 'bold'); doc.setFontSize(size); doc.setTextColor(...textColor);
        if (align === 'center') doc.text(text, M + CW / 2, y + h / 2 + size * 0.35, { align: 'center' });
        else doc.text(text, M + 8, y + h / 2 + size * 0.35);
        y += h;
      };

      bar('EVALUACIÓN DE DESEMPEÑO INDIVIDUAL — TALLY 2026', 24, C_TITLE, [255, 255, 255], 12.5, 'center');
      y += 6;

      // ─── DATOS DEL EVALUADO ───
      bar('DATOS DEL EVALUADO', 16, C_SECTION, [255, 255, 255], 9.5);
      doc.autoTable({
        startY: y, theme: 'grid', margin: { left: M, right: M }, tableWidth: CW,
        styles: { fontSize: 8, cellPadding: 4, textColor: C_TEXT, lineColor: [190, 190, 190], lineWidth: 0.4 },
        columnStyles: {
          0: { fontStyle: 'bold', fillColor: C_LABEL, cellWidth: CW * 0.17 },
          1: { cellWidth: CW * 0.33 },
          2: { fontStyle: 'bold', fillColor: C_LABEL, cellWidth: CW * 0.17 },
          3: { cellWidth: CW * 0.33 },
        },
        body: [
          ['Nombre completo', ev.colaborador_nombre || '—', 'Período evaluado', ev.periodo || '—'],
          ['Código', ev.colaborador_codigo || '—', 'Evaluador', ev.coordinador_nombre || '—'],
          ['Cargo', ev.colaborador_cargo || 'Asistente de Estiba', 'Fecha de evaluación', ev.fecha_evaluacion || '—'],
          ['DNI', ev.colaborador_dni || '—', 'Fecha de ingreso', '—'],
        ],
      });
      y = doc.lastAutoTable.finalY + 8;

      const anchos = { n: 0.06, comp: 0.24, ini: 0.08, inc: 0.11, red: 0.09, fin: 0.08, niv: 0.11, com: 0.23 };
      const colW = k => CW * anchos[k];
      const headCommon = { fillColor: C_LABEL, textColor: C_TEXT, fontStyle: 'bold', halign: 'center', fontSize: 7.5 };

      const filaCompetencia = (c, rowFill, extra) => {
        const meta = COMPETENCIAS[c.competencia_key] || { label: c.competencia_key };
        const nivel = evNivelLabel(c.puntaje_final);
        return [
          { content: EV_NUM[c.competencia_key] ?? '', styles: { fillColor: rowFill, fontStyle: 'bold', halign: 'center' } },
          { content: meta.label + (extra ? `  ${extra}` : ''), styles: { fillColor: rowFill } },
          { content: c.base, styles: { fillColor: C_INIT, fontStyle: 'bold', halign: 'center' } },
          { content: c.incremento_final ? `+${c.incremento_final}` : '', styles: { halign: 'center' } },
          { content: c.descuento_final ? `-${c.descuento_final}` : '', styles: { halign: 'center' } },
          { content: c.puntaje_final, styles: { halign: 'center', fontStyle: 'bold' } },
          { content: nivel, styles: { fillColor: C_NIVEL, halign: 'center' } },
        ];
      };

      const secA = ev.competencias.filter(c => c.tipo === 'conductual');
      const secB = ev.competencias.filter(c => c.tipo === 'operativa');
      const subtotal = arr => arr.reduce((s, c) => s + c.puntaje_final, 0);

      // ─── SECCIÓN A · COMPETENCIAS CONDUCTUALES ───
      bar('SECCIÓN A – COMPETENCIAS CONDUCTUALES', 16, C_SECTION, [255, 255, 255], 9.5);
      doc.autoTable({
        startY: y, theme: 'grid', margin: { left: M, right: M }, tableWidth: CW,
        styles: { fontSize: 7.5, cellPadding: 3.5, textColor: C_TEXT, lineColor: [190, 190, 190], lineWidth: 0.4 },
        head: [[
          { content: 'N°', styles: headCommon }, { content: 'Competencia', styles: headCommon },
          { content: 'Puntaje inicial', styles: headCommon }, { content: 'Incremento de puntos', styles: headCommon },
          { content: 'Reducción de puntos', styles: headCommon }, { content: 'Puntaje final', styles: headCommon },
          { content: 'Nivel final', styles: headCommon }, { content: 'Comentario / evidencia observada', styles: headCommon },
        ]],
        body: secA.map(c => [...filaCompetencia(c, C_ROW_A), { content: evResumenEvidencia(c), styles: { fontSize: 6.8 } }]),
        foot: [[
          { content: 'SUBTOTAL SECCIÓN A', colSpan: 2, styles: { fillColor: C_SUB, fontStyle: 'bold' } },
          { content: subtotal(secA), colSpan: 4, styles: { fillColor: C_SUB, fontStyle: 'bold', halign: 'center' } },
          { content: '/ 50', colSpan: 2, styles: { fillColor: C_SUB, fontStyle: 'bold', halign: 'center' } },
        ]],
        columnStyles: {
          0: { cellWidth: colW('n') }, 1: { cellWidth: colW('comp') }, 2: { cellWidth: colW('ini') },
          3: { cellWidth: colW('inc') }, 4: { cellWidth: colW('red') }, 5: { cellWidth: colW('fin') },
          6: { cellWidth: colW('niv') }, 7: { cellWidth: colW('com') },
        },
      });
      y = doc.lastAutoTable.finalY + 8;

      // ─── SECCIÓN B · COMPETENCIAS OPERATIVAS ───
      bar('SECCIÓN B – COMPETENCIAS OPERATIVAS (MATRIZ FRECUENCIA – IMPACTO)', 16, C_SECTION, [255, 255, 255], 9);
      doc.autoTable({
        startY: y, theme: 'grid', margin: { left: M, right: M }, tableWidth: CW,
        styles: { fontSize: 7.5, cellPadding: 3.5, textColor: C_TEXT, lineColor: [190, 190, 190], lineWidth: 0.4 },
        head: [[
          { content: 'N°', styles: headCommon }, { content: 'Competencia – punto a mejorar', styles: headCommon },
          { content: 'Puntaje inicial', styles: headCommon }, { content: 'Incremento de puntos', styles: headCommon },
          { content: 'Reducción de puntos', styles: headCommon }, { content: 'Puntaje final', styles: headCommon },
          { content: 'Nivel final', styles: headCommon }, { content: 'Incidentes considerados', styles: headCommon },
        ]],
        body: secB.map(c => [...filaCompetencia(c, C_ROW_B, EV_DESC_B[c.competencia_key]), { content: evListaIncidencias(c), styles: { halign: 'left', fontSize: 6.8 } }]),
        foot: [[
          { content: 'SUBTOTAL SECCIÓN B', colSpan: 2, styles: { fillColor: C_SUB, fontStyle: 'bold' } },
          { content: subtotal(secB), colSpan: 4, styles: { fillColor: C_SUB, fontStyle: 'bold', halign: 'center' } },
          { content: '/ 50', colSpan: 2, styles: { fillColor: C_SUB, fontStyle: 'bold', halign: 'center' } },
        ]],
        columnStyles: {
          0: { cellWidth: colW('n') }, 1: { cellWidth: colW('comp') }, 2: { cellWidth: colW('ini') },
          3: { cellWidth: colW('inc') }, 4: { cellWidth: colW('red') }, 5: { cellWidth: colW('fin') },
          6: { cellWidth: colW('niv') }, 7: { cellWidth: colW('com') },
        },
      });
      y = doc.lastAutoTable.finalY + 8;

      // ─── PUNTAJE TOTAL / CLASIFICACIÓN (con Puntaje anterior y Variación como cuadros propios) ───
      const leftW = CW * 0.66, rightW = CW - leftW - 6;
      doc.setFillColor(...C_TITLE); doc.rect(M, y, leftW, 22, 'F');
      doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(255, 255, 255);
      doc.text('PUNTAJE TOTAL', M + 8, y + 15);
      doc.text(`${ev.puntaje_total} / 100`, M + leftW - 8, y + 15, { align: 'right' });

      doc.setFillColor(...C_LABEL); doc.rect(M + leftW + 6, y, rightW, 22, 'F');
      doc.setFont('helvetica', 'bold'); doc.setFontSize(6.8); doc.setTextColor(...C_TEXT);
      doc.text('PUNTAJE ANTERIOR', M + leftW + 10, y + 8.5);
      doc.setFont('helvetica', 'bold'); doc.setFontSize(9.5);
      doc.text(ev.puntaje_anterior !== null ? String(ev.puntaje_anterior) : '—', M + leftW + rightW - 6, y + 18, { align: 'right' });
      y += 22 + 4;

      doc.setFillColor(...C_SECTION); doc.rect(M, y, leftW, 18, 'F');
      doc.setFont('helvetica', 'bold'); doc.setFontSize(9.5); doc.setTextColor(255, 255, 255);
      doc.text(`CLASIFICACIÓN:  ${ev.clasificacion}`, M + 8, y + 12.5);

      doc.setFillColor(...C_LABEL); doc.rect(M + leftW + 6, y, rightW, 18, 'F');
      doc.setFont('helvetica', 'bold'); doc.setFontSize(6.8); doc.setTextColor(...C_TEXT);
      doc.text('VARIACIÓN', M + leftW + 10, y + 7.5);
      doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5);
      doc.text(ev.variacion_pct !== null ? `${ev.variacion_pct}%` : '—', M + leftW + rightW - 6, y + 15, { align: 'right' });
      y += 18 + 12;

      // ─── RETROALIMENTACIÓN Y PLAN DE ACCIÓN ───
      bar('RETROALIMENTACIÓN Y PLAN DE ACCIÓN', 16, C_SECTION, [255, 255, 255], 9.5);
      y += 6;

      const bloqueTexto = (titulo, texto) => {
        doc.setFillColor(...C_LABEL); doc.rect(M, y, CW, 13, 'F');
        doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(...C_TEXT);
        doc.text(titulo, M + 6, y + 9.5);
        y += 13;
        const lines = doc.splitTextToSize(texto || '—', CW - 12);
        const boxH = Math.max(30, lines.length * 10 + 8);
        doc.setDrawColor(190, 190, 190); doc.rect(M, y, CW, boxH);
        doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(55, 65, 81);
        doc.text(lines, M + 6, y + 11);
        y += boxH + 8;
      };
      bloqueTexto('Fortalezas observadas', ev.fortalezas);
      bloqueTexto('Aspectos a mejorar', ev.aspectos_mejora);
      bloqueTexto('Plan de acción para próximo trimestre', ev.plan_accion);

      // ─── FIRMAS ───
      const firmaW = (CW - 10) / 2;
      doc.setFillColor(...C_LABEL); doc.rect(M, y, firmaW, 13, 'F'); doc.rect(M + firmaW + 10, y, firmaW, 13, 'F');
      doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(...C_TEXT);
      doc.text('Firma del evaluador', M + 6, y + 9.5);
      doc.text('Firma del evaluado', M + firmaW + 16, y + 9.5);
      y += 13;
      doc.setDrawColor(190, 190, 190);
      doc.rect(M, y, firmaW, 40); doc.rect(M + firmaW + 10, y, firmaW, 40);

      const safeName = (ev.colaborador_nombre || 'evades').replace(/[^a-z0-9]+/gi, '_');
      doc.save(`evades_${ev.periodo}_${safeName}.pdf`);
    } catch (e) {
      toast('No se pudo generar el PDF', 'error');
    }
  }

  $('evTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action="pdf"]'); if (!b) return;
    exportarPDF(b.dataset.id);
  });
  $('evViewPdf').addEventListener('click', e => exportarPDF(e.currentTarget.dataset.id));

  window.__evades = { $, esc, toast, COMPETENCIAS, COMP_KEYS, get colaboradores() { return colaboradores; }, get evaluaciones() { return evaluaciones; }, cargarEvaluaciones, BASE, EVALUADOR };
})();
</script>

</body>
</html>
