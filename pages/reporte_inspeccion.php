<?php
require_once('../includes/auth.php');
require_once('../includes/reporte_inspeccion_catalogo.php');
require_once('../includes/drive_config.php');   // SG_MAX_ARCHIVOS, SG_MAX_BYTES, sg_accept_attr()
require_report();

// Catálogos → JS (única fuente de verdad, definida en includes/reporte_inspeccion_catalogo.php)
$JS_CRITERIOS = ri_criterios();              // [item, item, ...]
$JS_ESTADOS   = ri_estados();                // clave => label
$JS_ACCIONES  = ri_acciones_correctivas();   // clave => label (acción correctiva)
$AREA_FIJA    = ri_area_involucrada();       // "Operaciones"
$INSPECTOR    = $_SESSION['user_name'] ?? '';

// Ubicaciones activas para zona de trabajo (desde tabla ubicaciones)
require_once('../includes/db.php');
$zona_ubicaciones = [];
$r_ub = mysqli_query($conn, "SELECT nombre FROM ubicaciones WHERE activo=1 ORDER BY orden, nombre");
while ($row = mysqli_fetch_assoc($r_ub)) $zona_ubicaciones[] = $row['nombre'];

// Logo embebido (base64) para el encabezado del PDF exportado en el navegador.
$logo_path = __DIR__ . '/../logo/logo.png';
$LOGO_B64  = is_file($logo_path) ? base64_encode(file_get_contents($logo_path)) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reporte de Inspección · Estiba Shift Command Deck</title>
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
       REPORTE DE INSPECCIÓN · registro + tabla (prefijo .ri-*)
       Mismo lenguaje visual que Incidencias (PREMIUM LIGHT EMERALD THEME)
    ════════════════════════════════════════════════════════════════ */
    .ri-wrap {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0, 135, 90, 0.18); --co-line-bold:rgba(0, 135, 90, 0.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      --mono: ui-monospace, Consolas, monospace;
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .ri-wrap *, .ri-wrap *::before, .ri-wrap *::after { box-sizing:border-box; }

    .ri-hero {
      background: linear-gradient(135deg, #005c3d 0%, #00875A 100%) !important;
      color:#fff; border-radius:20px; padding:22px 28px;
      display:flex; align-items:center; justify-content:space-between; gap:18px;
      border: 1px solid rgba(0, 135, 90, 0.2) !important;
      box-shadow: 0 8px 32px rgba(0, 135, 90, 0.08) !important;
    }
    .ri-hero h1 { margin:6px 0 4px; font-size:22px; font-weight:700; letter-spacing:-.01em; }
    .ri-hero p  { margin:0; color:rgba(255,255,255,.85); font-size:13px; max-width:560px; }
    .ri-hero .tag {
      display:inline-flex; align-items:center; gap:8px;
      padding:5px 11px; border-radius:999px;
      background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18);
      font-size:11px; font-weight:600; letter-spacing:.12em; text-transform:uppercase;
      color: #ffffff !important;
    }

    .ri-btn {
      display:inline-flex; align-items:center; gap:7px;
      padding:9px 16px; border-radius:10px; border:1px solid rgba(0, 135, 90, 0.3);
      background:#fff; cursor:pointer; text-decoration:none;
      font-family:inherit; font-size:13px; font-weight:600; color:#00875A;
      transition:all .15s;
    }
    .ri-btn:hover { border-color:var(--co-navy-700); color:var(--co-navy); background: rgba(0, 135, 90, 0.05); }
    body .ri-btn.primary,
    .ri-btn.primary {
      background: linear-gradient(135deg, #00875A 0%, #005c3d 100%) !important;
      color: #fff !important;
      border: none !important;
      font-weight: 700 !important;
      box-shadow: 0 4px 18px rgba(0, 135, 90, 0.2) !important;
      letter-spacing: 0.02em;
      padding: 11px 20px;
      border-radius: 12px;
    }
    body .ri-btn.primary:hover,
    .ri-btn.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(0, 135, 90, 0.35) !important;
      filter: brightness(1.08);
    }
    .ri-btn.primary:active { transform: translateY(0); }
    .ri-btn svg { width:14px; height:14px; }

    .ri-kpis { display:flex; gap:10px; flex-wrap:wrap; }
    .ri-kpi {
      flex:1; min-width:120px;
      background:#fff; border:1px solid var(--co-line); border-radius:14px;
      padding:14px 18px;
      box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;
    }
    .ri-kpi .lbl { font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--co-mute); }
    .ri-kpi:nth-child(1) .val { color:var(--co-navy-700); }
    .ri-kpi:nth-child(2) .val { color:var(--co-red); }
    .ri-kpi:nth-child(3) .val { color:#12B76A; }
    .ri-kpi:nth-child(4) .val { color:#3b82f6; }
    .ri-kpi .val { font-size:22px; font-weight:700; margin-top:4px; }

    .ri-toolbar {
      display:flex; gap:10px; align-items:center; flex-wrap:wrap;
      background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:10px 12px;
      box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;
    }
    .ri-search {
      flex:1; min-width:220px; display:flex; align-items:center; gap:8px;
      background:var(--co-deck); border:1px solid rgba(0, 135, 90, 0.15); border-radius:10px; padding:8px 12px;
    }
    .ri-search:focus-within { border-color:var(--co-navy-700); background:#fff; box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.15); }
    .ri-search input { flex:1; border:0; outline:0; background:transparent; font:inherit; font-size:13.5px; color:var(--co-ink); }
    .ri-search input::placeholder { color: var(--co-faint); opacity: 0.9; }
    .ri-search svg { width:15px; height:15px; color:var(--co-mute); }
    .ri-filter { display:flex; gap:4px; background:#f3f4f6; border-radius:10px; padding:3px; flex-wrap:wrap; border: 1px solid #e5e7eb; }
    .ri-filter button {
      padding:6px 12px; border:0; background:transparent; border-radius:7px;
      font:inherit; font-size:12px; font-weight:600; color:var(--co-mute); cursor:pointer;
    }
    .ri-filter button.active { background:#fff; color:var(--co-navy-700); box-shadow:0 1px 3px rgba(0,0,0,.06); border: 1px solid rgba(0, 135, 90, 0.2); }

    .ri-table-wrap { background:#fff; border:1px solid var(--co-line); border-radius:14px; overflow:auto; box-shadow: 0 4px 16px rgba(0,0,0,.02) !important; }
    .ri-table { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap; }
    .ri-table thead tr { background: rgba(0, 135, 90, 0.04) !important; border-bottom:1px solid var(--co-line); }
    .ri-table th {
      padding:11px 14px; text-align:left;
      font-size:10.5px; letter-spacing:.1em; text-transform:uppercase;
      color:var(--co-navy); font-weight:700;
    }
    .ri-table tbody tr { border-bottom:1px solid rgba(0, 135, 90, 0.06); transition:background .12s; }
    .ri-table tbody tr:last-child { border-bottom:0; }
    .ri-table tbody tr:hover { background: rgba(0, 135, 90, 0.02); }
    .ri-table td { padding:11px 14px; vertical-align:middle; color: var(--co-ink) !important; }
    .ri-name { font-weight:600; color:var(--co-ink); }
    .ri-sub  { font-size:11px; color:var(--co-faint); }

    .ri-badge {
      display:inline-flex; align-items:center; gap:5px;
      padding:3px 9px; border-radius:999px;
      font-size:10.5px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
      color:#fff;
    }
    .ri-badge .dot { width:6px; height:6px; border-radius:50%; background:rgba(255,255,255,.85); }
    .ri-area-chip {
      display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:6px;
      font-size:11px; font-weight:600; background:rgba(0, 135, 90, 0.06); color:var(--co-navy);
    }

    .ri-act-btn {
      padding:5px 10px; border-radius:7px; border:1px solid rgba(0, 135, 90, 0.25);
      background:rgba(0, 135, 90, 0.05); cursor:pointer; font:inherit; font-size:12px; font-weight:600; color:#00875A;
      transition:all .12s;
    }
    .ri-act-btn:hover { border-color:var(--co-navy-700); color:#ffffff; background:#00875A; }
    .ri-act-btn.danger { color: var(--co-red); border-color: rgba(239, 68, 68, 0.25); background: rgba(239, 68, 68, 0.05); }
    .ri-act-btn.danger:hover { border-color:var(--co-red); color:#ffffff; background: var(--co-red); }
    /* Botón de acción correctiva: cambia de aspecto según esté atendido o no. */
    .ri-act-btn.accion { border-color: rgba(217,119,6,.35); color:#d97706; background: rgba(217,119,6,.07); }
    .ri-act-btn.accion:hover { background:#d97706; border-color:#d97706; color:#fff; }
    .ri-act-btn.accion.done { border-color: rgba(5,150,105,.35); color:#059669; background: rgba(5,150,105,.08); }
    .ri-act-btn.accion.done:hover { background:#059669; border-color:#059669; color:#fff; }

    /* ── ACCIÓN CORRECTIVA (respuesta a un reporte ya registrado) ── */
    .ri-ac-head-icon {
      width:42px; height:42px; border-radius:12px; flex-shrink:0;
      background:linear-gradient(135deg,#00875A 0%,#00b377 100%); color:#fff;
      display:grid; place-items:center; box-shadow:0 4px 14px -4px rgba(0,135,90,.45);
    }
    .ri-ac-head-icon svg { width:20px; height:20px; }
    .ri-ac-head-id { display:flex; align-items:center; gap:12px; }
    .ri-ac-status {
      display:inline-flex; align-items:center; gap:4px; margin-left:8px;
      padding:2px 8px; border-radius:999px; font-size:9.5px; font-weight:800;
      letter-spacing:.06em; text-transform:uppercase; vertical-align:middle;
      position:relative; top:-1px;
    }
    .ri-ac-status:empty { display:none; }
    .ri-ac-status .dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
    .ri-ac-status.pend { background:rgba(217,119,6,.1); color:#d97706; border:1px solid rgba(217,119,6,.28); }
    .ri-ac-status.done { background:rgba(5,150,105,.1); color:#059669; border:1px solid rgba(5,150,105,.28); }

    .ri-ac-peticion {
      position:relative; overflow:hidden;
      background:linear-gradient(180deg,#f8faf9 0%,#f2f7f5 100%);
      border:1px solid var(--co-line); border-radius:14px; padding:16px 18px 16px 20px;
    }
    .ri-ac-peticion::before {
      content:''; position:absolute; left:0; top:0; bottom:0; width:4px;
      background:linear-gradient(180deg,#00875A,#00b377);
    }
    .ri-ac-peticion-top { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }
    .ri-ac-peticion h4 { margin:0; font-size:11px; font-weight:800; letter-spacing:.08em;
      text-transform:uppercase; color:var(--co-mute); }
    .ri-ac-folio {
      font-family:var(--mono); font-size:10.5px; font-weight:700; color:var(--co-navy);
      background:rgba(0, 135, 90, 0.08); border:1px solid rgba(0, 135, 90, 0.2);
      padding:2px 9px; border-radius:999px; white-space:nowrap;
    }
    .ri-ac-meta-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:10px 16px; }
    .ri-ac-meta-item { display:flex; align-items:flex-start; gap:7px; min-width:0; }
    .ri-ac-meta-item svg { width:14px; height:14px; color:#00875A; flex-shrink:0; margin-top:2px; }
    .ri-ac-meta-txt { min-width:0; flex:1 1 auto; }
    .ri-ac-meta-item .k { display:block; font-size:9px; font-weight:700; letter-spacing:.07em;
      text-transform:uppercase; color:var(--co-faint); }
    .ri-ac-meta-item .v { display:block; font-size:12.5px; font-weight:600; color:var(--co-ink);
      line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ri-ac-meta-item .v[title] { cursor:help; }
    /* Sin margen inferior: el espaciado lo pone .ri-ac-note, y así no queda
       hueco colgando cuando el reporte no trae medidas ni recomendaciones. */
    .ri-ac-note {
      margin-top:10px; font-size:12.5px; color:var(--co-ink); line-height:1.5; white-space:pre-wrap;
      background:rgba(255,255,255,.7); border:1px solid var(--co-line); border-radius:10px; padding:10px 12px;
    }
    .ri-ac-note b { display:flex; align-items:center; gap:6px; font-size:10px; text-transform:uppercase;
      letter-spacing:.06em; color:var(--co-mute); margin-bottom:4px; font-weight:800; }
    .ri-ac-note b svg { width:13px; height:13px; }
    .ri-ac-note.warn { border-left:3px solid #d97706; }
    .ri-ac-note.warn b { color:#d97706; }
    .ri-ac-note.tip { border-left:3px solid #3b82f6; }
    .ri-ac-note.tip b { color:#3b82f6; }

    .ri-ac-opts { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:10px; }
    .ri-ac-opt {
      position:relative; display:flex; align-items:center; gap:11px; padding:11px 14px 11px 12px;
      cursor:pointer; border:1.5px solid #e2e8f0; border-radius:12px; background:#fff;
      transition:all .18s cubic-bezier(.22,.61,.36,1);
    }
    .ri-ac-opt:hover { border-color:rgba(0,135,90,.45); background:rgba(0,135,90,.03); transform:translateY(-1px);
      box-shadow:0 6px 16px -10px rgba(0,135,90,.4); }
    .ri-ac-opt input { position:absolute; opacity:0; width:1px; height:1px; pointer-events:none; }
    .ri-ac-opt-ico {
      width:30px; height:30px; border-radius:9px; flex-shrink:0; display:grid; place-items:center;
      background:var(--co-deck); color:#00875A; border:1px solid rgba(0, 135, 90, 0.2); transition:all .18s;
    }
    .ri-ac-opt-ico svg { width:15px; height:15px; }
    .ri-ac-opt .lbl { font-size:12.5px; font-weight:600; color:var(--co-ink); line-height:1.3; flex:1; }
    .ri-ac-opt .chk {
      width:19px; height:19px; border-radius:6px; border:1.5px solid #cbd5e1; flex-shrink:0;
      display:grid; place-items:center; transition:all .15s; background:#fff;
    }
    .ri-ac-opt .chk svg { width:11px; height:11px; opacity:0; transform:scale(.5); transition:all .15s; color:#fff; }
    .ri-ac-opt.on { border-color:#00875A; background:rgba(0,135,90,.05); box-shadow:0 2px 10px -4px rgba(0,135,90,.3); }
    .ri-ac-opt.on .ri-ac-opt-ico { background:#00875A; color:#fff; border-color:#00875A; }
    .ri-ac-opt.on .chk { background:#00875A; border-color:#00875A; }
    .ri-ac-opt.on .chk svg { opacity:1; transform:scale(1); }

    .ri-ac-comentario-wrap { display:flex; flex-direction:column; gap:4px; }
    .ri-ac-counter { align-self:flex-end; font-size:10.5px; font-weight:600; color:var(--co-faint); }

    .ri-ac-drop {
      display:flex; flex-direction:column; align-items:center; justify-content:center; gap:9px; padding:22px 16px;
      border:2px dashed rgba(0, 135, 90, 0.3); border-radius:14px;
      background:linear-gradient(180deg,#f8faf9,#f2f7f5); cursor:pointer; transition:all .18s ease;
    }
    .ri-ac-drop:hover { border-color:#00875A; background:rgba(0,135,90,.06); }
    .ri-ac-drop.lleno { opacity:.55; cursor:not-allowed; }
    .ri-ac-drop-ico {
      width:38px; height:38px; border-radius:11px; display:grid; place-items:center;
      background:#fff; border:1px solid rgba(0, 135, 90, 0.25); color:#00875A; transition:all .18s;
    }
    .ri-ac-drop:hover .ri-ac-drop-ico { background:#00875A; color:#fff; transform:translateY(-2px); }
    .ri-ac-drop svg { width:19px; height:19px; }
    .ri-ac-drop span#ac-ev-txt { font-size:13px; font-weight:700; color:var(--co-navy-700); text-align:center; }
    .ri-ac-hint { font-size:11.5px; color:var(--co-faint); text-align:center; margin-top:6px; }
    .ri-ac-ev {
      display:flex; align-items:center; gap:12px; padding:10px 12px; margin-top:8px;
      border:1px solid var(--co-line); border-radius:12px; background:#fff; transition:all .15s;
    }
    .ri-ac-ev:hover { box-shadow:0 6px 16px -10px rgba(0,0,0,.2); }
    .ri-ac-ev.pend { border-color:rgba(217,119,6,.35); background:rgba(217,119,6,.05); }
    .ri-ac-ev-ico {
      width:36px; height:36px; border-radius:10px; flex-shrink:0; display:grid; place-items:center;
      background:var(--co-deck); color:#00875A; border:1px solid rgba(0, 135, 90, 0.18);
    }
    .ri-ac-ev-ico svg { width:17px; height:17px; }
    .ri-ac-ev-ico.pdf { color:#dc2626; background:rgba(220,38,38,.06); border-color:rgba(220,38,38,.18); }
    .ri-ac-ev-ico.img { color:#3b82f6; background:rgba(59,130,246,.06); border-color:rgba(59,130,246,.18); }
    .ri-ac-ev-ico.doc { color:#7c3aed; background:rgba(124,58,237,.06); border-color:rgba(124,58,237,.18); }
    .ri-ac-ev .nm { font-size:12.5px; font-weight:600; color:var(--co-ink);
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block; }
    .ri-ac-ev a.nm { text-decoration:none; }
    .ri-ac-ev a.nm:hover { color:#00875A; }
    .ri-ac-ev .mt { display:flex; align-items:center; gap:7px; margin-top:3px; }
    .ri-ac-ev .wt { font-size:11px; color:var(--co-mute); }
    .ri-ac-ev .st {
      display:inline-flex; align-items:center; gap:4px; font-size:9.5px; font-weight:800;
      text-transform:uppercase; letter-spacing:.04em; padding:1px 7px; border-radius:999px;
    }
    .ri-ac-ev .st.ok { background:rgba(5,150,105,.1); color:#059669; }
    .ri-ac-ev .st.pend { background:rgba(217,119,6,.12); color:#d97706; }
    .ri-ac-ev .rm {
      border:0; background:transparent; color:var(--co-faint); font-size:18px; flex-shrink:0;
      font-weight:700; cursor:pointer; line-height:1; padding:4px 6px; border-radius:6px; transition:all .12s;
    }
    .ri-ac-ev .rm:hover { color:#fff; background:var(--co-red); }
    .ri-ac-firma { display:inline-flex; align-items:center; gap:6px; font-size:11.5px; color:var(--co-mute); font-weight:600; }
    .ri-ac-firma svg { width:13px; height:13px; color:#059669; flex-shrink:0; }
    .ri-cell-actions { display:flex; gap:6px; align-items:center; justify-content:flex-end; }
    #riAccionBack .ri-modal { width:680px; }
    @media (max-width:600px) {
      .ri-ac-meta-grid { grid-template-columns:repeat(2, 1fr) !important; }
      .ri-ac-opts { grid-template-columns:1fr !important; }
    }

    /* ── Modales ── */
    .ri-modal-back {
      position:fixed; inset:0; background:rgba(0, 0, 0, 0.3);
      display:grid; place-items:center; z-index:995;
      opacity:0; pointer-events:none; transition:opacity .2s;
      backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    }
    .ri-modal-back.open { opacity:1; pointer-events:auto; }
    .ri-modal {
      background:#fff; border-radius:18px; width:620px; max-width:94vw;
      box-shadow: 0 24px 64px rgba(0, 135, 90, 0.12);
      transform:translateY(12px) scale(.97); transition:transform .22s cubic-bezier(.25,.46,.45,.94);
      max-height:92vh; display:flex; flex-direction:column; overflow:hidden;
      border: 1px solid var(--co-line);
    }
    .ri-modal-back.open .ri-modal { transform:translateY(0) scale(1); }
    .ri-modal-head {
      padding:18px 20px 14px; border-bottom:1px solid rgba(0, 135, 90, 0.08);
      display:flex; align-items:center; justify-content:space-between;
    }
    .ri-modal-head h3 { margin:0; font-size:17px; font-weight:700; color: var(--co-ink); }
    .ri-modal-head .sub { font-size:12px; color:var(--co-mute); margin-top:2px; }
    .ri-modal-close {
      width:32px; height:32px; border-radius:8px; border:1px solid #d1d5db;
      background:#fff; cursor:pointer; display:grid; place-items:center; color:var(--co-mute);
    }
    .ri-modal-close:hover { color:var(--co-red); border-color:var(--co-red); }
    .ri-modal-body { padding:18px 20px; display:flex; flex-direction:column; gap:12px; overflow-y:auto; flex:1; background: #ffffff; }
    .ri-modal-foot { padding:14px 20px; border-top:1px solid rgba(0, 135, 90, 0.08); display:flex; justify-content:flex-end; gap:8px; background: #ffffff; }
    .ri-field { display:flex; flex-direction:column; gap:5px; }
    .ri-field label {
      font-size:11px; font-weight:700; color:#374151; letter-spacing:.05em; text-transform:uppercase;
    }
    .ri-field input, .ri-field select, .ri-field textarea {
      font:inherit; font-size:13.5px; color:#111827;
      background:#ffffff; border:1.5px solid #cbd5e1; border-radius:8px;
      padding:9px 11px; outline:0; transition:border-color .15s, box-shadow .15s;
    }
    .ri-field input::placeholder, .ri-field textarea::placeholder { color:#94a3b8; }
    .ri-field select option { color:#111827; background:#ffffff; }
    .ri-field textarea { resize:vertical; min-height:70px; }
    .ri-field input:focus, .ri-field select:focus, .ri-field textarea:focus {
      border-color:#00875A; background:#fff; box-shadow:0 0 0 3px rgba(0, 135, 90, 0.15);
    }
    .ri-field input[readonly] { background:#f3f4f6; color:#4b5563; cursor:default; border-color: #e5e7eb; }
    .ri-colsel { position:relative; }
    .ri-colsel-panel {
      display:none; position:fixed; z-index:9000;
      min-width:320px; max-width:420px;
      background:#fff; border:1px solid rgba(0, 135, 90, 0.25); border-radius:11px;
      box-shadow:0 16px 40px rgba(0,0,0,.08); max-height:240px; overflow-y:auto;
      padding:5px;
    }
    .ri-colsel-panel.open { display:block; }
    .ri-colsel-item {
      display:flex; align-items:center; gap:10px;
      padding:9px 11px; border-radius:8px; cursor:pointer;
    }
    .ri-colsel-item:hover { background:rgba(0, 135, 90, 0.05); }
    .ri-colsel-avatar {
      width:30px; height:30px; border-radius:8px; flex-shrink:0;
      background:rgba(0, 135, 90, 0.08); color:var(--co-navy); font-size:10px; font-weight:800;
      display:flex; align-items:center; justify-content:center; letter-spacing:-.3px;
    }
    .ri-colsel-info { display:flex; flex-direction:column; gap:1px; min-width:0; }
    .ri-colsel-nm { font-size:13px; font-weight:600; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ri-colsel-cd { font-size:11px; color:#4b5563; }
    .ri-colsel-empty { padding:12px; font-size:12px; color:#4b5563; text-align:center; }

    .ri-toast {
      position:fixed; bottom:24px; right:24px; z-index:999;
      background:#111827; color:#fff; padding:12px 18px; border-radius:10px;
      font-size:13px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,.1);
      transform:translateY(120%); opacity:0; transition:all .25s;
      border: 1px solid rgba(255,255,255,0.1);
    }
    .ri-toast.show { transform:translateY(0); opacity:1; }
    .ri-toast.is-error { background:#dc2626; border-color: #ef4444; }

    .content { padding:24px 28px 60px; overflow-y:auto; }

    /* ════════════════════════════════════════════════════════════════
       FORMULARIO DE INSPECCIÓN · rediseño "parte / command console"
    ════════════════════════════════════════════════════════════════ */
    #riModalBack, #riViewBack {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-navy-900:#001226; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0, 135, 90, 0.18); --co-line-bold:rgba(0, 135, 90, 0.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      --mono: ui-monospace, "SFMono-Regular", Consolas, monospace;
      font-family:'DM Sans', system-ui, sans-serif;
    }

    .ri-modal.ri-create {
      width:920px; max-width:96vw; padding:0;
      flex-direction:row; align-items:stretch; max-height:92vh;
    }
    .ri-create *, .ri-create *::before, .ri-create *::after { box-sizing:border-box; }

    .ri-rail {
      position:relative; flex:0 0 264px; width:264px; color:var(--co-ink);
      padding:24px 22px; overflow:hidden;
      display:flex; flex-direction:column; gap:22px;
      background:#f5f8f7; border-right:1px solid var(--co-line);
    }
    .ri-rail > * { position:relative; z-index:1; }
    .ri-rail-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .ri-rail-kicker { font-family:var(--mono); font-size:9.5px; letter-spacing:.22em; color:var(--co-mute); }
    .ri-rail-folio {
      font-family:var(--mono); font-size:10px; letter-spacing:.06em;
      padding:3px 9px; border-radius:999px; background:rgba(0, 135, 90, 0.08);
      border:1px solid rgba(0, 135, 90, 0.2); color:var(--co-navy); white-space:nowrap;
    }
    .ri-rail-lbl {
      display:block; font-family:var(--mono); font-size:9px; letter-spacing:.18em;
      text-transform:uppercase; color:var(--co-faint); margin-bottom:8px;
    }
    .ri-rail-id { display:flex; align-items:center; gap:12px; }
    .ri-rail-avatar {
      width:46px; height:46px; border-radius:13px; flex-shrink:0;
      background: linear-gradient(135deg, #00875A 0%, #00b377 100%); color:#ffffff;
      display:grid; place-items:center; font-size:16px; font-weight:700;
      box-shadow: 0 2px 8px -4px rgba(0, 135, 90, 0.2);
    }
    .ri-rail-name  { font-size:15px; font-weight:700; line-height:1.2; overflow:hidden; text-overflow:ellipsis; color:var(--co-ink); }
    .ri-rail-cargo { font-size:11.5px; color:var(--co-mute); margin-top:2px; }
    .ri-rail-area {
      display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:700;
      color:var(--co-navy); background:rgba(0, 135, 90, 0.08); border:1px solid rgba(0, 135, 90, 0.2);
      border-radius:999px; padding:4px 11px; width:fit-content;
    }
    .ri-rail-count { font-size:26px; font-weight:800; color:var(--co-navy-700); line-height:1; }
    .ri-rail-count.has-issues { color:var(--co-red); }
    .ri-rail-foot { margin-top:auto; padding-top:16px; border-top:1px solid var(--co-line); }
    .ri-rail-insp { font-size:13.5px; font-weight:600; color:var(--co-ink); }

    .ri-form { flex:1; min-width:0; display:flex; flex-direction:column; background:#fff; }
    .ri-form-head {
      padding:20px 24px 16px; border-bottom:1px solid var(--co-line);
      display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
    }
    .ri-form-head h3 { margin:0; font-size:18px; font-weight:700; color:var(--co-ink); letter-spacing:-.01em; }
    .ri-form-head .sub { font-size:12px; color:var(--co-mute); margin-top:3px; }
    .ri-form-body { padding:6px 24px 18px; overflow-y:auto; flex:1; }

    .ri-sec { padding:15px 0; border-bottom:1px dashed var(--co-line); }
    .ri-sec:last-child { border-bottom:0; }
    .ri-sec-head { display:flex; align-items:center; gap:9px; margin-bottom:12px; }
    .ri-sec-num {
      font-family:var(--mono); font-size:10px; font-weight:700; color:var(--co-navy);
      background:var(--co-deck); border:1px solid rgba(0, 135, 90, 0.25);
      padding:2px 6px; border-radius:6px; letter-spacing:.05em;
    }
    .ri-sec-head > span:last-child { font-size:11.5px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--co-mute); }
    .ri-row2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

    /* checklist de criterios */
    .ri-checklist { display:flex; flex-direction:column; gap:8px; }
    .ri-crit-row {
      display:grid; grid-template-columns:1fr auto; gap:10px; align-items:center;
      background:var(--co-deck); border:1px solid var(--co-line); border-radius:10px; padding:10px 12px;
    }
    .ri-crit-item { font-size:13px; font-weight:600; color:var(--co-ink); }
    .ri-crit-obs {
      grid-column:1 / -1; font:inherit; font-size:12.5px; color:#111827;
      background:#fff; border:1.5px solid #cbd5e1; border-radius:7px; padding:7px 9px; outline:0;
    }
    .ri-crit-obs:focus { border-color:#00875A; box-shadow:0 0 0 3px rgba(0, 135, 90, 0.15); }
    .ri-crit-toggle { display:flex; gap:5px; }
    .ri-crit-toggle button {
      border:1.5px solid var(--co-line); background:#fff; border-radius:7px; padding:6px 10px;
      font:inherit; font-size:11.5px; font-weight:700; color:var(--co-mute); cursor:pointer; transition:all .13s;
      white-space:nowrap;
    }
    .ri-crit-toggle button[data-estado="conforme"].active { background:#12B76A; border-color:#12B76A; color:#fff; }
    .ri-crit-toggle button[data-estado="no_conforme"].active { background:var(--co-red); border-color:var(--co-red); color:#fff; }

    .ri-form-foot { padding:14px 24px; border-top:1px solid var(--co-line); display:flex; justify-content:flex-end; gap:8px; }

    @keyframes riSecIn { from { opacity:0; transform:translateY(9px); } to { opacity:1; transform:none; } }
    @keyframes riRailIn { from { opacity:0; transform:translateX(-14px); } to { opacity:1; transform:none; } }
    .ri-modal-back.open .ri-rail { animation:riRailIn .5s both cubic-bezier(.22,.61,.36,1); }
    .ri-modal-back.open .ri-form-body section { animation:riSecIn .45s both cubic-bezier(.22,.61,.36,1); }
    .ri-modal-back.open .ri-form-body section:nth-of-type(1){ animation-delay:.06s; }
    .ri-modal-back.open .ri-form-body section:nth-of-type(2){ animation-delay:.11s; }
    .ri-modal-back.open .ri-form-body section:nth-of-type(3){ animation-delay:.16s; }
    .ri-modal-back.open .ri-form-body section:nth-of-type(4){ animation-delay:.21s; }
    .ri-modal-back.open .ri-form-body section:nth-of-type(5){ animation-delay:.26s; }

    /* ── Vista detalle ── */
    .ri-modal--view .ri-modal-head { display:none; }
    .ri-modal--view .ri-modal-body { padding:0; overflow:hidden; display:block; }
    .ri-modal--view #riViewEdit { background:linear-gradient(135deg,#00875A,#005c3d); border-color:transparent; }
    .ri-modal--view #riViewEdit:hover { background:linear-gradient(135deg,#00b377,#00875A); }
    .ri-view-layout { display:grid; grid-template-columns:105px 1fr; max-height:72vh; }
    .ri-view-sidebar {
      background:linear-gradient(160deg,#005c3d 0%,#00875A 100%);
      padding:20px 12px; color:#fff;
      display:flex; flex-direction:column; gap:12px; align-items:center; overflow-y:auto;
    }
    .ri-view-sidebar .iv-divider { width:100%; border:none; border-top:1px solid rgba(255,255,255,.2); margin:0; }
    .iv-stat { text-align:center; width:100%; }
    .iv-stat-k { font-size:8px; opacity:.75; text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:2px; }
    .iv-stat-v { font-size:11px; font-weight:700; }
    .ri-view-sidebar .iv-insp { text-align:center; margin-top:auto; padding-top:12px; border-top:1px solid rgba(255,255,255,.2); width:100%; }
    .ri-view-sidebar .iv-insp .iv-stat-k { opacity:.75; }
    .ri-view-sidebar .iv-insp .iv-stat-v { font-size:10px; font-weight:600; }
    .ri-view-main { padding:18px 16px; display:flex; flex-direction:column; gap:10px; overflow-y:auto; background:#fff; }
    .ri-view-name { font-size:14px; font-weight:800; color:#005c3d; line-height:1.3; margin:0; }
    .ri-view-cargo { font-size:11px; color:var(--co-mute); margin-top:3px; font-weight:500; }
    .ri-view-divider { border:none; border-top:1px solid var(--co-line); margin:2px 0; }
    .ri-view-table { width:100%; border-collapse:collapse; font-size:12.5px; }
    .ri-view-table th { text-align:left; padding:6px 8px; font-size:10px; letter-spacing:.06em; text-transform:uppercase; color:var(--co-mute); border-bottom:1px solid var(--co-line); }
    .ri-view-table td { padding:7px 8px; border-bottom:1px solid rgba(0, 135, 90, 0.06); vertical-align:top; }
    .ri-view-notes { background:#f9fafb; border:1px solid var(--co-line); border-radius:8px; padding:10px 12px; }
    .ri-view-notes .iv-k { font-size:9px; color:var(--co-mute); text-transform:uppercase; letter-spacing:.5px; font-weight:600; display:block; margin-bottom:3px; }
    .ri-view-notes .iv-v { font-size:13px; color:#4b5563; font-weight:400; line-height:1.5; }

    @media (max-width:760px) {
      .ri-modal.ri-create { flex-direction:column; width:96vw; }
      .ri-rail { flex:0 0 auto; width:100%; }
      .ri-rail-foot { margin-top:14px; }
    }
    @media (max-width: 600px) {
      .ri-modal, .ri-modal.ri-create {
        width: calc(100vw - 12px) !important; max-width: 100% !important;
        height: 95dvh !important; max-height: 95dvh !important; border-radius: 14px !important;
        display: flex !important; flex-direction: column !important; overflow: hidden !important;
      }
      .ri-modal.ri-create .ri-rail { flex: 0 0 auto !important; overflow: visible !important; padding: 14px 16px !important; gap: 8px !important; }
      .ri-modal.ri-create .ri-form { flex: 1 1 0 !important; min-height: 0 !important; display: flex !important; flex-direction: column !important; overflow: hidden !important; }
      .ri-form-body { flex: 1 1 0 !important; min-height: 0 !important; overflow-y: auto !important; overscroll-behavior: contain !important; padding: 12px 14px 16px !important; }
      .ri-form-head { padding: 14px 14px 10px !important; }
      .ri-form-head h3 { font-size: 16px !important; }
      .ri-form-foot { padding: 12px 14px !important; }
      .ri-row2 { grid-template-columns: 1fr !important; }
      .ri-sec { padding: 12px 0 !important; }
      .ri-field label { color: #111827 !important; font-size: 10.5px !important; }
      .ri-field input, .ri-field select, .ri-field textarea {
        font-size: 15px !important; padding: 11px 12px !important; color: #111827 !important;
        background: #fff !important; border: 1.5px solid #cbd5e1 !important;
      }
      .ri-colsel-panel { min-width: 90vw !important; max-width: 94vw !important; }
      .ri-crit-toggle button { padding: 8px 8px !important; font-size: 11px !important; }
    }
    @media (max-width: 390px) { .ri-rail { display: none !important; } .ri-form { width: 100% !important; } }
    @media (prefers-reduced-motion:reduce) {
      .ri-modal-back.open .ri-rail, .ri-modal-back.open .ri-form-body section { animation:none; }
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
      <div class="ri-wrap">

        <!-- HERO -->
        <section class="ri-hero">
          <div>
            <span class="tag">CONTROL DE CAMPO · REPORTE DE INSPECCIÓN</span>
            <h1>Reporte de Inspección de Seguridad</h1>
            <p>Checklist de campo (señalización, iluminación, orden y limpieza, EPPs, condiciones inseguras y ergonomía) asociado al tally involucrado y a la zona inspeccionada.</p>
          </div>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
            <button class="ri-btn" id="btnExportExcel" style="background:#fff; border-color:#00875A; color:#00875A;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
              Exportar Excel
            </button>
            <button class="ri-btn primary" id="btnNew">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Registrar inspección
            </button>
          </div>
        </section>

        <!-- KPIS -->
        <section class="ri-kpis">
          <div class="ri-kpi"><div class="lbl">Total</div><div class="val" id="kpiTotal">0</div></div>
          <div class="ri-kpi"><div class="lbl">No conformidades</div><div class="val" id="kpiNoConf">0</div></div>
          <div class="ri-kpi"><div class="lbl">Conformidad</div><div class="val" id="kpiPct">0%</div></div>
          <div class="ri-kpi"><div class="lbl">Este mes</div><div class="val" id="kpiMes">0</div></div>
        </section>

        <!-- TOOLBAR -->
        <div class="ri-toolbar">
          <div class="ri-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="riSearch" type="text" placeholder="Buscar por tally, zona, inspector…">
          </div>
          <div class="ri-filter" id="riFilter">
            <button class="active" data-f="todos">Todos</button>
            <button data-f="conforme">Conformes</button>
            <button data-f="hallazgos">Con hallazgos</button>
          </div>
        </div>

        <!-- TABLA -->
        <div class="ri-table-wrap">
          <table class="ri-table">
            <thead>
              <tr>
                <th>Tally involucrado</th>
                <th>Zona</th>
                <th>Área involucrada</th>
                <th>Inspector</th>
                <th>Fecha</th>
                <th>No conformidades</th>
                <th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody id="riTbody">
              <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--co-faint)">Cargando…</td></tr>
            </tbody>
          </table>
        </div>

      </div>
    </main>
  </div>
</div>

<!-- MODAL: registrar / editar -->
<div class="ri-modal-back" id="riModalBack">
  <div class="ri-modal ri-create">

    <!-- RAIL IZQUIERDO -->
    <aside class="ri-rail">
      <div class="ri-rail-top">
        <span class="ri-rail-kicker">PARTE · INSPECCIÓN</span>
        <span class="ri-rail-folio" id="railFolio">NUEVO</span>
      </div>

      <div class="ri-rail-id">
        <div class="ri-rail-avatar" id="railAvatar">—</div>
        <div style="min-width:0">
          <div class="ri-rail-name" id="railName">Sin tally</div>
          <div class="ri-rail-cargo" id="railCargo">Selecciona el personal tally</div>
        </div>
      </div>

      <div>
        <span class="ri-rail-lbl">Área involucrada</span>
        <span class="ri-rail-area"><?= htmlspecialchars($AREA_FIJA) ?></span>
      </div>

      <div>
        <span class="ri-rail-lbl">No conformidades detectadas</span>
        <div class="ri-rail-count" id="railCount">0</div>
      </div>

      <div class="ri-rail-foot">
        <span class="ri-rail-lbl">Inspector que reporta</span>
        <div class="ri-rail-insp" id="railInsp"><?= htmlspecialchars($INSPECTOR) ?></div>
      </div>
    </aside>

    <!-- COLUMNA DERECHA · formulario -->
    <div class="ri-form">
      <div class="ri-form-head">
        <div>
          <h3 id="riModalTitle">Registrar inspección</h3>
          <div class="sub">Completa el checklist. El área involucrada queda fija en "<?= htmlspecialchars($AREA_FIJA) ?>".</div>
        </div>
        <button class="ri-modal-close" id="riModalClose">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <div class="ri-form-body">
        <input type="hidden" id="im-id">

        <section class="ri-sec">
          <div class="ri-sec-head"><span class="ri-sec-num">01</span><span>Personal tally involucrado</span></div>
          <div class="ri-row2">
            <div class="ri-field">
              <label>Nombre</label>
              <div class="ri-colsel" id="im-tally-wrap">
                <input type="text" id="im-tally-search" autocomplete="off" placeholder="Buscar por nombre o código…">
                <div class="ri-colsel-panel" id="im-tally-panel"></div>
                <select id="im-tally" style="display:none"><option value="">Cargando…</option></select>
              </div>
            </div>
            <div class="ri-field">
              <label>Cargo</label>
              <input id="im-cargo" type="text" readonly placeholder="—">
            </div>
          </div>
        </section>

        <section class="ri-sec">
          <div class="ri-sec-head"><span class="ri-sec-num">02</span><span>Zona de trabajo y fecha</span></div>
          <div class="ri-row2">
            <div class="ri-field">
              <label>Zona</label>
              <select id="im-zona"><option value="">Selecciona…</option></select>
            </div>
            <div class="ri-field">
              <label>Fecha</label>
              <input id="im-fecha" type="date">
            </div>
          </div>
        </section>

        <section class="ri-sec">
          <div class="ri-sec-head"><span class="ri-sec-num">03</span><span>Criterios de inspección</span></div>
          <div class="ri-checklist" id="riChecklist"></div>
        </section>

        <section class="ri-sec">
          <div class="ri-sec-head"><span class="ri-sec-num">04</span><span>Medidas a tomar</span></div>
          <div class="ri-field">
            <textarea id="im-medidas" placeholder="Describe las medidas a tomar…" maxlength="2000"></textarea>
          </div>
        </section>

        <section class="ri-sec">
          <div class="ri-sec-head"><span class="ri-sec-num">05</span><span>Recomendaciones</span></div>
          <div class="ri-field">
            <textarea id="im-recomendaciones" placeholder="Recomendaciones finales…" maxlength="2000"></textarea>
          </div>
        </section>
      </div>

      <div class="ri-form-foot">
        <button class="ri-btn" id="riModalCancel">Cancelar</button>
        <button class="ri-btn primary" id="riModalSave">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Guardar reporte
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: ver detalle -->
<div class="ri-modal-back" id="riViewBack">
  <div class="ri-modal ri-modal--view">
    <div class="ri-modal-head" style="display:none">
      <div><h3>Detalle de inspección</h3><div class="sub" id="riViewSub">—</div></div>
      <button class="ri-modal-close" id="riViewClose"></button>
    </div>
    <div class="ri-modal-body" id="riViewBody"></div>
    <div class="ri-modal-foot">
      <button class="ri-btn" id="riViewCloseBtn">Cerrar</button>
      <button class="ri-btn" id="riViewPdf">Exportar PDF</button>
      <button class="ri-btn primary" id="riViewEdit">Editar</button>
    </div>
  </div>
</div>

<!-- MODAL: acción correctiva (respuesta a un reporte ya registrado) -->
<div class="ri-modal-back" id="riAccionBack">
  <div class="ri-modal">
    <div class="ri-modal-head">
      <div class="ri-ac-head-id">
        <div class="ri-ac-head-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
        </div>
        <div>
          <h3><span id="riAccionTitle">Acción correctiva</span><span class="ri-ac-status" id="acStatus"></span></h3>
          <div class="sub" id="riAccionSub">—</div>
        </div>
      </div>
      <button class="ri-modal-close" id="riAccionClose"></button>
    </div>
    <div class="ri-modal-body">
      <input type="hidden" id="ac-reporte-id">

      <!-- La petición: el reporte al que se responde, solo lectura -->
      <section class="ri-sec">
        <div class="ri-ac-peticion" id="acPeticion"></div>
      </section>

      <section class="ri-sec">
        <div class="ri-sec-head"><span class="ri-sec-num">01</span><span>Acciones aplicadas</span></div>
        <div class="ri-ac-opts" id="acOpciones"></div>
      </section>

      <section class="ri-sec">
        <div class="ri-sec-head"><span class="ri-sec-num">02</span><span>Comentario / observación</span></div>
        <div class="ri-field">
          <div class="ri-ac-comentario-wrap">
            <textarea id="ac-comentario" maxlength="2000"
                      placeholder="Describe qué se hizo, con quién se coordinó, resultado…"></textarea>
            <span class="ri-ac-counter" id="acComentarioCount">0/2000</span>
          </div>
        </div>
      </section>

      <section class="ri-sec">
        <div class="ri-sec-head"><span class="ri-sec-num">03</span><span>Adjuntar evidencia</span></div>
        <input type="file" id="ac-ev-input" multiple hidden
               accept="<?= htmlspecialchars(sg_accept_attr(), ENT_QUOTES) ?>">
        <div class="ri-ac-drop" id="ac-ev-drop">
          <div class="ri-ac-drop-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          </div>
          <span id="ac-ev-txt">Subir foto, PDF o documento</span>
        </div>
        <div class="ri-ac-hint">Máx. <?= SG_MAX_ARCHIVOS ?> archivos · menos de 4 MB cada uno · se guardan en Google Drive</div>
        <div id="ac-ev-list"></div>
      </section>
    </div>
    <div class="ri-modal-foot">
      <span class="ri-ac-firma" id="acFirma"></span>
      <button class="ri-btn" id="riAccionCancel">Cancelar</button>
      <button class="ri-btn primary" id="riAccionSave">Guardar acción</button>
    </div>
  </div>
</div>

<div class="ri-toast" id="riToast">—</div>

<script src="../js/vendor/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.0/jspdf.plugin.autotable.min.js"></script>
<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  // ── Catálogos desde PHP (fuente de verdad) ──
  const CRITERIOS = <?= json_encode($JS_CRITERIOS, JSON_UNESCAPED_UNICODE) ?>; // [item, ...]
  const ESTADOS    = <?= json_encode($JS_ESTADOS, JSON_UNESCAPED_UNICODE) ?>;   // {clave: label}
  const ACCIONES   = <?= json_encode($JS_ACCIONES, JSON_UNESCAPED_UNICODE) ?>;  // {clave: label}
  const AREA_FIJA  = <?= json_encode($AREA_FIJA, JSON_UNESCAPED_UNICODE) ?>;
  const INSPECTOR  = <?= json_encode($INSPECTOR, JSON_UNESCAPED_UNICODE) ?>;
  const BASE       = '..';
  const UBIC_ZONA  = <?= json_encode($zona_ubicaciones, JSON_UNESCAPED_UNICODE) ?>;
  const LOGO_B64   = <?= $LOGO_B64 ? json_encode('data:image/png;base64,' . $LOGO_B64) : 'null' ?>;
  const MAX_EV     = <?= SG_MAX_ARCHIVOS ?>;
  const MAX_BYTES  = <?= SG_MAX_BYTES ?>;

  let reportes = [];
  let colaboradores = [];
  let query = '';
  let filtro = 'todos';
  let editingId = null;
  let accionEvidencias = [];   // evidencias ya subidas de la acción correctiva en curso

  function toast(msg, type) {
    const t = $('riToast');
    t.textContent = msg;
    t.className = 'ri-toast show' + (type === 'error' ? ' is-error' : '');
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
  function noConformesDe(rep) {
    return (rep.criterios || []).filter(c => c.estado === 'no_conforme').length;
  }
  function evaluadosDe(rep) {
    return (rep.criterios || []).filter(c => c.estado === 'conforme' || c.estado === 'no_conforme').length;
  }

  function fillSelect(el, entries, placeholder) {
    el.innerHTML = `<option value="">${placeholder}</option>` +
      entries.map(([v, label]) => `<option value="${esc(v)}">${esc(label)}</option>`).join('');
  }
  function initSelects() {
    fillSelect($('im-zona'), UBIC_ZONA.map(z => [z, z]), 'Selecciona…');
  }

  // ─── Checklist: se reconstruye cada vez que se abre el modal ───
  function buildChecklist(valores) {
    const map = {};
    (valores || []).forEach(c => { map[c.item] = c; });
    $('riChecklist').innerHTML = CRITERIOS.map(item => {
      const c = map[item] || {};
      const estado = c.estado || '';
      const obs = c.observaciones || '';
      return `
        <div class="ri-crit-row" data-item="${esc(item)}">
          <span class="ri-crit-item">${esc(item)}</span>
          <div class="ri-crit-toggle">
            ${Object.entries(ESTADOS).map(([k, label]) =>
              `<button type="button" data-estado="${esc(k)}" class="${estado === k ? 'active' : ''}">${esc(label)}</button>`
            ).join('')}
          </div>
          <input type="text" class="ri-crit-obs" placeholder="Observaciones (opcional)" value="${esc(obs)}">
        </div>`;
    }).join('');
    updateRailCount();
  }
  function readChecklist() {
    return Array.from($('riChecklist').querySelectorAll('.ri-crit-row')).map(row => {
      const active = row.querySelector('.ri-crit-toggle button.active');
      return {
        item: row.dataset.item,
        estado: active ? active.dataset.estado : '',
        observaciones: row.querySelector('.ri-crit-obs').value.trim(),
      };
    });
  }
  function updateRailCount() {
    const n = readChecklist().filter(c => c.estado === 'no_conforme').length;
    const el = $('railCount');
    el.textContent = n;
    el.classList.toggle('has-issues', n > 0);
  }

  // ─── KPIs ───
  function renderKpis() {
    $('kpiTotal').textContent = reportes.length;
    const totalNoConf = reportes.reduce((s, r) => s + noConformesDe(r), 0);
    $('kpiNoConf').textContent = totalNoConf;
    const totalEval = reportes.reduce((s, r) => s + evaluadosDe(r), 0);
    const totalConf = totalEval - totalNoConf;
    $('kpiPct').textContent = totalEval ? Math.round((totalConf / totalEval) * 100) + '%' : '—';
    const now = new Date();
    const ym = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0');
    $('kpiMes').textContent = reportes.filter(r => String(r.fecha || '').startsWith(ym)).length;
  }

  // ─── Tabla ───
  function render() {
    const q = query.trim().toLowerCase();
    const list = reportes.filter(r => {
      const n = noConformesDe(r);
      if (filtro === 'conforme' && n > 0) return false;
      if (filtro === 'hallazgos' && n === 0) return false;
      if (!q) return true;
      return [r.tally_nombre, r.tally_cargo, r.zona_trabajo, r.area_involucrada, r.inspector]
        .some(v => String(v ?? '').toLowerCase().includes(q));
    });
    const tb = $('riTbody');
    tb.innerHTML = '';
    if (!list.length) {
      tb.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--co-faint)">Sin reportes.</td></tr>`;
      return;
    }
    list.forEach(r => {
      const n = noConformesDe(r);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><div class="ri-name">${esc(r.tally_nombre)}</div><div class="ri-sub">${esc(r.tally_cargo || '')}</div></td>
        <td>${esc(r.zona_trabajo)}</td>
        <td><span class="ri-area-chip">${esc(r.area_involucrada)}</span></td>
        <td>${esc(r.inspector)}</td>
        <td>${fmtFecha(r.fecha)}</td>
        <td>${n > 0
          ? `<span class="ri-badge" style="background:#dc2626"><span class="dot"></span>${n}</span>`
          : `<span class="ri-badge" style="background:#12B76A"><span class="dot"></span>0</span>`}</td>
        <td>
          <div class="ri-cell-actions">
            <button class="ri-act-btn accion ${r.accion_fecha ? 'done' : ''}" data-action="accion" data-id="${r.id}"
                    title="${r.accion_fecha ? 'Acción correctiva registrada · click para editarla' : 'Registrar la acción correctiva de este reporte'}">
              ${r.accion_fecha ? '✓ Acción' : 'Acción correctiva'}
            </button>
            <button class="ri-act-btn" data-action="view" data-id="${r.id}">Ver</button>
            <button class="ri-act-btn" data-action="edit" data-id="${r.id}">Editar</button>
            <button class="ri-act-btn" data-action="pdf" data-id="${r.id}">PDF</button>
            <button class="ri-act-btn danger" data-action="del" data-id="${r.id}">Eliminar</button>
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
    const sel = $('im-tally');
    sel.innerHTML = `<option value="">Selecciona…</option>` +
      colaboradores.map(c => `<option value="${c.id}">${esc(c.nombre)} (${esc(c.codigo || '')})</option>`).join('');
    wireTallySearch();
  }

  function wireTallySearch() {
    const inp   = $('im-tally-search');
    const panel = $('im-tally-panel');
    const sel   = $('im-tally');
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
        panel.innerHTML = '<div class="ri-colsel-empty">Sin resultados</div>';
      } else {
        panel.innerHTML = items.slice(0, 80).map(c => {
          const parts = (c.nombre || '').trim().split(' ');
          const ini = (parts[0]?.[0] || '') + (parts[1]?.[0] || '');
          return `<div class="ri-colsel-item" data-id="${c.id}">
            <div class="ri-colsel-avatar">${esc(ini.toUpperCase())}</div>
            <div class="ri-colsel-info">
              <span class="ri-colsel-nm">${esc(c.nombre)}</span>
              <span class="ri-colsel-cd">${esc(c.codigo || '')}${c.funcion_principal ? ' · ' + esc(c.funcion_principal) : ''}</span>
            </div>
          </div>`;
        }).join('');
      }
      positionPanel();
      panel.classList.add('open');
    }

    function closePanel() { panel.classList.remove('open'); }

    function selectTally(id) {
      const c = colaboradores.find(x => String(x.id) === String(id));
      sel.value = id;
      inp.value = c ? c.nombre + ' (' + (c.codigo || '') + ')' : '';
      closePanel();
      syncCargo();
    }

    inp.addEventListener('focus', () => renderPanel(inp.value));
    inp.addEventListener('input', () => renderPanel(inp.value));
    panel.addEventListener('mousedown', e => {
      const it = e.target.closest('.ri-colsel-item');
      if (!it) return;
      e.preventDefault();
      selectTally(it.dataset.id);
    });
    document.addEventListener('mousedown', e => {
      const wrap = $('im-tally-wrap');
      if (wrap && !wrap.contains(e.target) && !panel.contains(e.target)) closePanel();
    }, true);
  }

  async function cargarReportes() {
    const res = await fetch(`${BASE}/api/get_reporte_inspeccion.php`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.success) { toast(data.error || 'Error al cargar', 'error'); return; }
    reportes = data.data || [];
    renderKpis(); render();
  }

  // ════════════════ ACCIÓN CORRECTIVA ════════════════
  // El reporte de inspección es la petición; esto es la respuesta que el
  // coordinador registra después, desde la columna Acciones. Es una por
  // reporte: si ya existe, se abre para editarla.

  function fmtPeso(b) { return b < 1048576 ? (b/1024).toFixed(0)+' KB' : (b/1048576).toFixed(1)+' MB'; }

  // Íconos por ítem — puramente decorativos, ayudan a escanear la lista rápido.
  const ICO_META = {
    tally:     '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    zona:      '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    fecha:     '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    inspector: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
  };
  const ICO_OPT = {
    correccion_inmediata: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
    charla:               '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
    epp:                  '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    senalizacion:         '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
    limpieza:             '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
    detencion:            '<polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/>',
    reubicacion:          '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    coordinacion:         '<path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 1 1 0 10h-2"/><line x1="8" y1="12" x2="16" y2="12"/>',
    hse:                  '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><line x1="4.93" y1="4.93" x2="9.17" y2="9.17"/><line x1="14.83" y1="14.83" x2="19.07" y2="19.07"/><line x1="14.83" y1="9.17" x2="19.07" y2="4.93"/><line x1="4.93" y1="19.07" x2="9.17" y2="14.83"/>',
    capacitacion:         '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
    seguimiento:          '<circle cx="12" cy="12" r="3"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>',
    otros:                '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
  };
  const ICO_OPT_FALLBACK = '<path d="M20 6 9 17l-5-5"/>';
  function svgIco(paths) {
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${paths}</svg>`;
  }
  function fileIco(nombre) {
    const ext = String(nombre || '').split('.').pop().toLowerCase();
    if (['jpg','jpeg','png','gif','webp','heic'].includes(ext)) {
      return { cls: 'img', svg: svgIco('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>') };
    }
    if (ext === 'pdf') {
      return { cls: 'pdf', svg: svgIco('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>') };
    }
    if (['doc','docx','xls','xlsx','ppt','pptx'].includes(ext)) {
      return { cls: 'doc', svg: svgIco('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>') };
    }
    return { cls: '', svg: svgIco('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>') };
  }

  /** Resumen de la acción correctiva para la vista de detalle. */
  function accionResumen(r) {
    if (!r.accion_fecha) return 'Pendiente · aún no se ha registrado.';
    const opts = (r.accion_opciones || []).map(k => ACCIONES[k] || k);
    const evs  = (r.accion_evidencias || []).length;
    const partes = [];
    if (opts.length)          partes.push(opts.map(esc).join(' · '));
    if (r.accion_comentario)  partes.push(esc(r.accion_comentario));
    partes.push(`${evs} evidencia(s) · registrada por ${esc(r.accion_por || '—')} el ${esc(r.accion_fecha)}`);
    return partes.join('\n');
  }

  // Bloque superior del modal: qué se detectó en el reporte (solo lectura).
  function renderPeticion(r) {
    const nota = (titulo, txt, cls, ico) => txt
      ? `<div class="ri-ac-note ${cls}"><b>${svgIco(ico)}${titulo}</b>${esc(txt)}</div>` : '';
    const metaItem = (icoKey, label, valor) => {
      const v = esc(valor);
      return `<div class="ri-ac-meta-item">${svgIco(ICO_META[icoKey])}<div class="ri-ac-meta-txt"><span class="k">${label}</span><span class="v" title="${v}">${v}</span></div></div>`;
    };
    $('acPeticion').innerHTML = `
      <div class="ri-ac-peticion-top">
        <h4>La petición</h4>
        <span class="ri-ac-folio">N° ${String(r.id).padStart(4,'0')}</span>
      </div>
      <div class="ri-ac-meta-grid">
        ${metaItem('tally', 'Tally', r.tally_nombre)}
        ${metaItem('zona', 'Zona', r.zona_trabajo)}
        ${metaItem('fecha', 'Fecha', fmtFecha(r.fecha))}
        ${metaItem('inspector', 'Inspector', r.inspector || '—')}
      </div>
      ${nota('Medidas a tomar', r.medidas_tomar, 'warn', '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>')}
      ${nota('Recomendaciones', r.recomendaciones, 'tip', '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/>')}`;
  }

  function renderOpciones(marcadas) {
    const set = new Set(marcadas || []);
    $('acOpciones').innerHTML = Object.entries(ACCIONES).map(([k, label]) => `
      <label class="ri-ac-opt ${set.has(k) ? 'on' : ''}">
        <input type="checkbox" value="${esc(k)}" ${set.has(k) ? 'checked' : ''}>
        <span class="ri-ac-opt-ico">${svgIco(ICO_OPT[k] || ICO_OPT_FALLBACK)}</span>
        <span class="lbl">${esc(label)}</span>
        <span class="chk"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
      </label>`).join('');
  }

  function renderAccionEvidencias() {
    $('ac-ev-list').innerHTML = accionEvidencias.map((e, i) => {
      const nombre = esc(e.nombre || e.nombre_archivo || '');
      const subido = e.estado === 'subido';
      const ico = fileIco(e.nombre || e.nombre_archivo);
      const titulo = e.drive_url
        ? `<a class="nm" href="${esc(e.drive_url)}" target="_blank" rel="noopener">${nombre} ↗</a>`
        : `<div class="nm">${nombre}</div>`;
      return `
        <div class="ri-ac-ev ${subido ? '' : 'pend'}">
          <div class="ri-ac-ev-ico ${ico.cls}">${ico.svg}</div>
          <div style="flex:1;min-width:0">
            ${titulo}
            <div class="mt">
              <span class="wt">${fmtPeso(e.peso_bytes || 0)}</span>
              <span class="st ${subido ? 'ok' : 'pend'}">${subido ? 'En Drive' : 'Pendiente'}</span>
            </div>
          </div>
          <button class="rm" data-ev="${i}" title="Quitar">×</button>
        </div>`;
    }).join('');
    $('ac-ev-drop').classList.toggle('lleno', accionEvidencias.length >= MAX_EV);
    $('ac-ev-txt').textContent = accionEvidencias.length >= MAX_EV
      ? `Máximo ${MAX_EV} archivos alcanzado` : 'Subir foto, PDF o documento';
  }

  function openAccion(id) {
    const r = reportes.find(x => Number(x.id) === Number(id));
    if (!r) return;
    const yaTiene = !!r.accion_fecha;
    $('ac-reporte-id').value  = r.id;
    $('riAccionTitle').textContent = yaTiene ? 'Editar acción correctiva' : 'Registrar acción correctiva';
    $('riAccionSub').textContent   = `${r.tally_nombre} · ${r.zona_trabajo}`;
    $('acStatus').innerHTML = yaTiene
      ? '<span class="dot"></span>Registrada'
      : '<span class="dot"></span>Pendiente';
    $('acStatus').className = 'ri-ac-status ' + (yaTiene ? 'done' : 'pend');
    $('ac-comentario').value  = r.accion_comentario || '';
    $('acComentarioCount').textContent = `${$('ac-comentario').value.length}/2000`;
    $('acFirma').innerHTML = yaTiene
      ? `${svgIco('<path d="M20 6 9 17l-5-5"/>')}Registrada por ${esc(r.accion_por || '—')} · ${esc(r.accion_fecha)}`
      : '';
    accionEvidencias = (r.accion_evidencias || []).map(e => ({ ...e, nombre: e.nombre_archivo }));
    renderPeticion(r);
    renderOpciones(r.accion_opciones);
    renderAccionEvidencias();
    $('riAccionBack').classList.add('open');
  }
  function closeAccion() {
    $('riAccionBack').classList.remove('open');
    accionEvidencias = [];
  }

  async function subirEvidenciaAccion(file) {
    $('ac-ev-txt').textContent = 'Subiendo…';
    try {
      const fd = new FormData(); fd.append('file', file, file.name);
      const res = await fetch(`${BASE}/api/upload_reporte_inspeccion_file.php`, { method: 'POST', body: fd });
      const j = await res.json();
      if (!j.success) { toast(j.error || 'No se pudo subir el archivo', 'error'); return; }
      accionEvidencias.push(j);
      if (j.aviso) toast(j.aviso, 'error');   // se guardó en local: conviene saberlo
    } catch (e) {
      toast('Error de red al subir el archivo', 'error');
    } finally {
      renderAccionEvidencias();
    }
  }

  async function guardarAccion() {
    const opciones = Array.from($('acOpciones').querySelectorAll('input:checked')).map(i => i.value);
    const comentario = $('ac-comentario').value.trim();
    if (!opciones.length && !comentario && !accionEvidencias.length) {
      toast('Marca al menos una acción, escribe un comentario o adjunta una evidencia', 'error'); return;
    }
    const btn = $('riAccionSave');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res = await fetch(`${BASE}/api/save_accion_correctiva.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          reporte_id: Number($('ac-reporte-id').value || 0),
          opciones, comentario,
          evidencias: accionEvidencias,
        }),
      });
      const data = await res.json();
      if (data.success) { toast('Acción correctiva guardada'); closeAccion(); cargarReportes(); }
      else { toast(data.error || 'Error al guardar', 'error'); }
    } catch (e) {
      toast('Error de red', 'error');
    } finally {
      btn.disabled = false; btn.textContent = 'Guardar acción';
    }
  }

  // ── Eventos de la acción correctiva ──
  $('riAccionClose').addEventListener('click', closeAccion);
  $('riAccionCancel').addEventListener('click', closeAccion);
  $('riAccionSave').addEventListener('click', guardarAccion);
  $('riAccionBack').addEventListener('click', e => { if (e.target === $('riAccionBack')) closeAccion(); });
  $('acOpciones').addEventListener('change', e => {
    const chk = e.target.closest('input[type=checkbox]'); if (!chk) return;
    chk.closest('.ri-ac-opt').classList.toggle('on', chk.checked);
  });
  $('ac-comentario').addEventListener('input', () => {
    $('acComentarioCount').textContent = `${$('ac-comentario').value.length}/2000`;
  });
  $('ac-ev-drop').addEventListener('click', () => {
    if (accionEvidencias.length >= MAX_EV) { toast(`Máximo ${MAX_EV} archivos`, 'error'); return; }
    $('ac-ev-input').click();
  });
  $('ac-ev-input').addEventListener('change', async () => {
    for (const f of $('ac-ev-input').files) {
      if (accionEvidencias.length >= MAX_EV) { toast(`Máximo ${MAX_EV} archivos`, 'error'); break; }
      if (f.size > MAX_BYTES) { toast(`"${f.name}" supera los 4 MB`, 'error'); continue; }
      await subirEvidenciaAccion(f);
    }
    $('ac-ev-input').value = '';
  });
  $('ac-ev-list').addEventListener('click', e => {
    const b = e.target.closest('button[data-ev]'); if (!b) return;
    accionEvidencias.splice(Number(b.dataset.ev), 1);
    renderAccionEvidencias();
  });

  // ════════════════ MODAL REGISTRAR / EDITAR ════════════════
  function openModal(id) {
    editingId = id ? Number(id) : null;
    const r = editingId ? reportes.find(x => Number(x.id) === editingId) : null;
    $('riModalTitle').textContent = r ? 'Editar inspección' : 'Registrar inspección';
    $('railFolio').textContent = r ? ('N° ' + String(r.id).padStart(4, '0')) : 'NUEVO';
    $('railInsp').textContent = (r && r.inspector) ? r.inspector : INSPECTOR;
    $('im-id').value        = r ? r.id : '';
    $('im-tally').value     = r ? (r.tally_id ?? '') : '';
    const _srch = $('im-tally-search');
    if (_srch) {
      if (r && r.tally_id) {
        const _c = colaboradores.find(x => Number(x.id) === Number(r.tally_id));
        _srch.value = _c ? _c.nombre + ' (' + (_c.codigo || '') + ')' : '';
      } else { _srch.value = ''; }
    }
    $('im-zona').value      = r ? (r.zona_trabajo ?? '') : '';
    $('im-fecha').value     = r ? (r.fecha ?? '') : new Date().toISOString().slice(0,10);
    $('im-medidas').value   = r ? (r.medidas_tomar ?? '') : '';
    $('im-recomendaciones').value = r ? (r.recomendaciones ?? '') : '';
    buildChecklist(r ? r.criterios : []);
    syncCargo();
    $('riModalBack').classList.add('open');
  }
  function closeModal() { $('riModalBack').classList.remove('open'); editingId = null; }

  function syncCargo() {
    const id = Number($('im-tally').value || 0);
    const c = colaboradores.find(x => Number(x.id) === id);
    $('im-cargo').value        = c ? (c.funcion_principal || '') : '';
    $('railName').textContent  = c ? c.nombre : 'Sin tally';
    $('railCargo').textContent = c ? (c.funcion_principal || '—') : 'Selecciona el personal tally';
    $('railAvatar').textContent= c ? initials(c.nombre) : '—';
  }

  async function guardar() {
    const payload = {
      id:               Number($('im-id').value || 0),
      tally_id:         Number($('im-tally').value || 0),
      zona_trabajo:     $('im-zona').value,
      fecha:            $('im-fecha').value,
      criterios:        readChecklist(),
      medidas_tomar:    $('im-medidas').value.trim(),
      recomendaciones:  $('im-recomendaciones').value.trim(),
    };
    if (!payload.tally_id)     { toast('Selecciona el personal tally involucrado', 'error'); return; }
    if (!payload.zona_trabajo) { toast('Selecciona la zona de trabajo', 'error'); return; }
    if (!payload.fecha)        { toast('Selecciona la fecha', 'error'); return; }

    const btn = $('riModalSave');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res = await fetch(`${BASE}/api/save_reporte_inspeccion.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (data.success) {
        toast(payload.id ? 'Reporte actualizado' : 'Reporte registrado');
        closeModal();
        cargarReportes();
      } else {
        toast(data.error || 'Error al guardar', 'error');
      }
    } catch (e) {
      toast('Error de red', 'error');
    } finally {
      btn.disabled = false; btn.textContent = 'Guardar reporte';
    }
  }

  async function eliminar(id) {
    const r = reportes.find(x => Number(x.id) === Number(id));
    if (!r) return;
    if (!confirm(`¿Eliminar el reporte de inspección de "${r.tally_nombre}" (${r.zona_trabajo})?`)) return;
    try {
      const res = await fetch(`${BASE}/api/delete_reporte_inspeccion.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: Number(id) }),
      });
      const data = await res.json();
      if (data.success) { toast('Reporte eliminado'); cargarReportes(); }
      else { toast(data.error || 'Error al eliminar', 'error'); }
    } catch (e) { toast('Error de red', 'error'); }
  }

  // ════════════════ VISTA DETALLE ════════════════
  function openView(id) {
    const r = reportes.find(x => Number(x.id) === Number(id));
    if (!r) return;
    const n = noConformesDe(r);

    const rows = (r.criterios || []).map(c => {
      const label = ESTADOS[c.estado] || '—';
      const color = c.estado === 'no_conforme' ? '#dc2626' : (c.estado === 'conforme' ? '#12B76A' : '#9ca3af');
      return `<tr>
        <td>${esc(c.item)}</td>
        <td><span class="ri-badge" style="background:${color}">${esc(label)}</span></td>
        <td>${esc(c.observaciones || '—')}</td>
      </tr>`;
    }).join('');

    $('riViewBody').innerHTML = `
      <div class="ri-view-layout">
        <div class="ri-view-sidebar">
          <div class="ri-rail-count${n > 0 ? ' has-issues' : ''}" style="color:${n > 0 ? '#fecaca' : '#fff'}">${n}</div>
          <span class="ri-rail-lbl" style="color:rgba(255,255,255,.7)">No conformidades</span>
          <hr class="iv-divider">
          <div class="iv-stat">
            <span class="iv-stat-k">Área</span>
            <span class="iv-stat-v">${esc(r.area_involucrada)}</span>
          </div>
          <div class="iv-stat">
            <span class="iv-stat-k">Zona</span>
            <span class="iv-stat-v">${esc(r.zona_trabajo || '—')}</span>
          </div>
          <div class="iv-stat">
            <span class="iv-stat-k">Fecha</span>
            <span class="iv-stat-v">${esc(fmtFecha(r.fecha))}</span>
          </div>
          <div class="iv-insp">
            <span class="iv-stat-k">Inspector</span>
            <span class="iv-stat-v">${esc(r.inspector || '—')}</span>
          </div>
        </div>
        <div class="ri-view-main">
          <div>
            <p class="ri-view-name">${esc(r.tally_nombre)}</p>
            <p class="ri-view-cargo">${esc(r.tally_cargo || '—')}</p>
          </div>
          <hr class="ri-view-divider">
          <table class="ri-view-table">
            <thead><tr><th>Ítem</th><th>Estado</th><th>Observaciones</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
          <div class="ri-view-notes">
            <span class="iv-k">Medidas a tomar</span>
            <span class="iv-v">${esc(r.medidas_tomar || '—')}</span>
          </div>
          <div class="ri-view-notes">
            <span class="iv-k">Recomendaciones</span>
            <span class="iv-v">${esc(r.recomendaciones || '—')}</span>
          </div>
          <div class="ri-view-notes">
            <span class="iv-k">Acción correctiva</span>
            <span class="iv-v">${accionResumen(r)}</span>
          </div>
        </div>
      </div>`;

    $('riViewSub').textContent = `${r.tally_nombre} · ${fmtFecha(r.fecha)}`;
    $('riViewEdit').dataset.id = r.id;
    $('riViewPdf').dataset.id = r.id;
    $('riViewBack').classList.add('open');
  }
  function closeView() { $('riViewBack').classList.remove('open'); }

  // ════════════════ EXPORTAR PDF (cliente, jsPDF + autoTable) ════════════════
  function exportarPDF(id) {
    const r = reportes.find(x => Number(x.id) === Number(id));
    if (!r) return;
    if (!window.jspdf) { toast('No se pudo cargar el generador de PDF', 'error'); return; }
    try {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'portrait' });
      const W = doc.internal.pageSize.getWidth();
      const H = doc.internal.pageSize.getHeight();
      const NAVY = [15, 76, 129]; // azul marino corporativo
      const M = 40;
      let y = 40;

      // ── Encabezado: logo (proporción real, sin deformar) + nombre ──
      const LOGO_W = 66, LOGO_H = 40;
      if (LOGO_B64) { try { doc.addImage(LOGO_B64, 'PNG', M, y, LOGO_W, LOGO_H); } catch (e) {} }
      doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.setTextColor(17, 24, 39);
      doc.text('Tallyman Control', M + LOGO_W + 14, y + 16);
      doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(75, 85, 99);
      doc.text('Shift Command · Control de Campo', M + LOGO_W + 14, y + 30);
      y += 56;

      // ── Barra de título ──
      doc.setFillColor(...NAVY);
      doc.rect(M, y, W - M * 2, 26, 'F');
      doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.setTextColor(255, 255, 255);
      doc.text('REPORTE DE INSPECCIÓN DE SEGURIDAD', W / 2, y + 17, { align: 'center' });
      y += 26;

      // ── Datos del reporte ──
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
          ['Inspector', r.inspector || '—', 'Fecha', fmtFecha(r.fecha)],
          ['Zona', r.zona_trabajo || '—', 'Área involucrada', r.area_involucrada || '—'],
          ['Personal Tally involucrado', { content: r.tally_nombre + (r.tally_cargo ? ' · ' + r.tally_cargo : ''), colSpan: 3 }],
        ],
      });
      y = doc.lastAutoTable.finalY + 14;

      // ── Tabla de criterios ──
      doc.autoTable({
        startY: y,
        head: [['Ítem a Inspeccionar', 'Estado', 'Observaciones']],
        body: (r.criterios || []).map(c => [
          c.item,
          c.estado === 'no_conforme' ? '✗ No conforme' : (c.estado === 'conforme' ? '✓ Conforme' : '—'),
          c.observaciones || '—',
        ]),
        theme: 'grid',
        margin: { left: M, right: M },
        styles: { fontSize: 9, cellPadding: 6 },
        headStyles: { fillColor: NAVY, textColor: 255, fontStyle: 'bold' },
        columnStyles: { 1: { cellWidth: 85 } },
        didParseCell: (data) => {
          if (data.section === 'body' && data.column.index === 1) {
            const v = String(data.cell.raw);
            if (v.includes('No conforme')) data.cell.styles.textColor = [220, 38, 38];
            else if (v.includes('Conforme')) data.cell.styles.textColor = [18, 183, 106];
          }
        },
      });
      y = doc.lastAutoTable.finalY + 12;

      // ── Medidas a tomar / Recomendaciones ──
      const boxW = W - M * 2;
      function addNoteBox(label, text) {
        if (y > H - 130) { doc.addPage(); y = 40; }
        const lines = doc.splitTextToSize(text || '—', boxW - 128);
        const h = Math.max(24, lines.length * 12 + 10);
        doc.setFillColor(219, 234, 254);
        doc.rect(M, y, 110, h, 'F');
        doc.setDrawColor(203, 213, 225);
        doc.rect(M, y, 110, h);
        doc.rect(M + 110, y, boxW - 110, h);
        doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor(17, 24, 39);
        doc.text(label, M + 6, y + 14);
        doc.setFont('helvetica', 'normal'); doc.setTextColor(55, 65, 81);
        doc.text(lines, M + 118, y + 14);
        y += h + 6;
      }
      addNoteBox('Medidas a tomar', r.medidas_tomar);
      addNoteBox('Recomendaciones', r.recomendaciones);

      // ── Firma ──
      const FIRMA_H = 130;
      if (y > H - FIRMA_H - 20) { doc.addPage(); y = 40; }
      y += 24;
      doc.setDrawColor(203, 213, 225);
      doc.rect(M, y, boxW, FIRMA_H);
      const lineY = y + FIRMA_H - 34;
      doc.setDrawColor(107, 114, 128);
      doc.line(W / 2 - 110, lineY, W / 2 + 110, lineY);
      doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(17, 24, 39);
      doc.text('Firma del Inspector', W / 2, lineY + 16, { align: 'center' });
      doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(75, 85, 99);
      doc.text(r.inspector || '—', W / 2, lineY + 30, { align: 'center' });

      const safeName = (r.tally_nombre || 'reporte').replace(/[^a-z0-9]+/gi, '_');
      doc.save(`inspeccion_${r.fecha}_${safeName}.pdf`);
    } catch (e) {
      toast('No se pudo generar el PDF', 'error');
    }
  }

  // ════════════════ EXPORTAR GENERAL A EXCEL ════════════════
  function exportarGeneralExcel() {
    if (typeof XLSX === 'undefined') { toast('Librería de Excel no disponible', 'error'); return; }
    if (!reportes.length) { toast('No hay reportes para exportar', 'error'); return; }
    
    // Preparar los datos basados en el filtro actual
    const q = query.trim().toLowerCase();
    const list = reportes.filter(r => {
      const n = noConformesDe(r);
      if (filtro === 'conforme' && n > 0) return false;
      if (filtro === 'hallazgos' && n === 0) return false;
      if (!q) return true;
      return [r.tally_nombre, r.tally_cargo, r.zona_trabajo, r.area_involucrada, r.inspector]
        .some(v => String(v ?? '').toLowerCase().includes(q));
    });

    if (!list.length) { toast('No hay reportes que coincidan con los filtros actuales', 'error'); return; }

    const dataResumen = [];
    const dataDetalle = [];

    list.forEach(r => {
      const evalCounts = evaluadosDe(r);
      const noConf = noConformesDe(r);
      const conf = evalCounts - noConf;
      const pct = evalCounts > 0 ? Math.round((conf / evalCounts) * 100) + '%' : 'N/A';

      // Resumen
      dataResumen.push({
        'ID Reporte': r.id,
        'Tally Involucrado': r.tally_nombre,
        'Cargo': r.tally_cargo || '',
        'Zona de Trabajo': r.zona_trabajo || '',
        'Área Involucrada': r.area_involucrada || '',
        'Inspector': r.inspector || '',
        'Fecha': fmtFecha(r.fecha),
        'Criterios Evaluados': evalCounts,
        'Conformes': conf,
        'No Conformes': noConf,
        '% Conformidad': pct,
        'Medidas a tomar': r.medidas_tomar || '',
        'Recomendaciones': r.recomendaciones || ''
      });
      
      // Detalle
      (r.criterios || []).forEach(c => {
        dataDetalle.push({
          'ID Reporte': r.id,
          'Tally Involucrado': r.tally_nombre,
          'Zona de Trabajo': r.zona_trabajo || '',
          'Fecha': fmtFecha(r.fecha),
          'Ítem Evaluado': c.item,
          'Estado': c.estado === 'no_conforme' ? 'No conforme' : (c.estado === 'conforme' ? 'Conforme' : 'No evaluado'),
          'Observaciones': c.observaciones || ''
        });
      });
    });

    const wb = XLSX.utils.book_new();
    const wsResumen = XLSX.utils.json_to_sheet(dataResumen);
    const wsDetalle = XLSX.utils.json_to_sheet(dataDetalle);

    // Auto-ajustar columnas básicas
    const colsResumen = [
      {wch: 12}, {wch: 35}, {wch: 25}, {wch: 20}, {wch: 20}, {wch: 30}, {wch: 12},
      {wch: 18}, {wch: 12}, {wch: 15}, {wch: 15}, {wch: 40}, {wch: 40}
    ];
    wsResumen['!cols'] = colsResumen;
    
    const colsDetalle = [
      {wch: 12}, {wch: 35}, {wch: 20}, {wch: 12}, {wch: 45}, {wch: 15}, {wch: 40}
    ];
    wsDetalle['!cols'] = colsDetalle;

    XLSX.utils.book_append_sheet(wb, wsResumen, "Resumen");
    XLSX.utils.book_append_sheet(wb, wsDetalle, "Detalles por Criterio");

    const now = new Date();
    const ymd = now.toISOString().slice(0,10);
    XLSX.writeFile(wb, `Reporte_Inspecciones_${ymd}.xlsx`);
    toast('Excel generado correctamente');
  }

  // ─── Eventos ───
  $('btnNew').addEventListener('click', () => openModal(null));
  if ($('btnExportExcel')) $('btnExportExcel').addEventListener('click', exportarGeneralExcel);
  $('riSearch').addEventListener('input', e => { query = e.target.value; render(); });
  $('riFilter').addEventListener('click', e => {
    const b = e.target.closest('button[data-f]'); if (!b) return;
    filtro = b.dataset.f;
    $('riFilter').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
    render();
  });
  $('riTbody').addEventListener('click', e => {
    const b = e.target.closest('[data-action]'); if (!b) return;
    if (b.dataset.action === 'accion') openAccion(b.dataset.id);
    if (b.dataset.action === 'view') openView(b.dataset.id);
    if (b.dataset.action === 'edit') openModal(b.dataset.id);
    if (b.dataset.action === 'pdf')  exportarPDF(b.dataset.id);
    if (b.dataset.action === 'del')  eliminar(b.dataset.id);
  });

  $('im-tally').addEventListener('change', syncCargo);
  $('riChecklist').addEventListener('click', e => {
    const b = e.target.closest('.ri-crit-toggle button'); if (!b) return;
    const row = b.closest('.ri-crit-row');
    row.querySelectorAll('.ri-crit-toggle button').forEach(x => x.classList.toggle('active', x === b));
    updateRailCount();
  });

  $('riModalClose').addEventListener('click', closeModal);
  $('riModalCancel').addEventListener('click', closeModal);
  $('riModalSave').addEventListener('click', guardar);
  $('riModalBack').addEventListener('click', e => { if (e.target === $('riModalBack')) closeModal(); });

  $('riViewClose').addEventListener('click', closeView);
  $('riViewCloseBtn').addEventListener('click', closeView);
  $('riViewBack').addEventListener('click', e => { if (e.target === $('riViewBack')) closeView(); });
  $('riViewEdit').addEventListener('click', e => { closeView(); openModal(e.currentTarget.dataset.id); });
  $('riViewPdf').addEventListener('click', e => exportarPDF(e.currentTarget.dataset.id));

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if ($('riModalBack').classList.contains('open')) closeModal();
    if ($('riViewBack').classList.contains('open')) closeView();
    if ($('riAccionBack').classList.contains('open')) closeAccion();
  });

  // ─── Init ───
  initSelects();
  (async () => {
    try { await cargarColaboradores(); }
    catch (e) { toast('No se pudieron cargar los colaboradores', 'error'); }
    cargarReportes();
  })();
})();
</script>

</body>
</html>
