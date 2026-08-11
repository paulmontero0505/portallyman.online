<?php
require_once('../includes/auth.php');
require_once('../includes/db.php');
require_once('../includes/capacitaciones_catalogo.php');
require_report();

// Catálogos → JS (fuente única de verdad; ver includes/capacitaciones_catalogo.php)
$JS_ESTADOS    = cap_estados();
$JS_ASISTENCIA = cap_asistencia();

$ES_ADMIN  = is_admin();
$USER_ID   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$USER_NAME = $_SESSION['user_name'] ?? '';
$USER_ROL  = $_SESSION['user_rol']  ?? '';

// Coordinadores, para el filtro del listado.
$COORDS = [];
$rc = mysqli_query($conn, "SELECT id, nombre FROM usuarios WHERE rol='Coordinador' ORDER BY nombre ASC");
if ($rc) while ($r = mysqli_fetch_assoc($rc)) $COORDS[] = ['id' => (int)$r['id'], 'nombre' => $r['nombre']];

// Logo embebido para el PDF.
$logo_path = __DIR__ . '/../logo/logo.png';
$LOGO_B64  = is_file($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Capacitaciones · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <link rel="stylesheet" href="../css/estiba.css">

  <style>
    /* ════════════════ CAPACITACIONES (prefijo .cap-*) ════════════════ */
    .cap-wrap {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0,135,90,.18); --co-line-bold:rgba(0,135,90,.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      --ok:#047857; --ok-bg:rgba(4,120,87,.10);
      --wn:#d97706; --wn-bg:rgba(217,119,6,.10);
      --er:#dc2626; --er-bg:rgba(220,38,38,.10);
      --sl:#475569; --sl-bg:rgba(100,116,139,.12);
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .cap-wrap *, .cap-wrap *::before, .cap-wrap *::after { box-sizing:border-box; }

    .cap-hero {
      background:linear-gradient(135deg,#005c3d 0%,#00875A 100%);
      color:#fff; border-radius:20px; padding:22px 28px;
      display:flex; align-items:center; justify-content:space-between; gap:18px;
      box-shadow:0 8px 32px rgba(0,135,90,.08);
    }
    .cap-hero h1 { margin:6px 0 4px; font-size:22px; font-weight:700; letter-spacing:-.01em; }
    .cap-hero p  { margin:0; color:rgba(255,255,255,.85); font-size:13px; max-width:600px; }
    .cap-hero .tag { display:inline-flex; align-items:center; padding:5px 11px; border-radius:999px;
      background:rgba(255,255,255,.15); font-size:11px; font-weight:700; letter-spacing:.06em; }

    .cap-btn { display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:10px;
      border:1px solid rgba(0,135,90,.3); background:#fff; cursor:pointer; text-decoration:none;
      font-family:inherit; font-size:13px; font-weight:600; color:#00875A; transition:all .15s; }
    .cap-btn svg { width:15px; height:15px; }
    .cap-btn:hover { background:rgba(0,135,90,.05); }
    .cap-btn.ghost-light { background:rgba(255,255,255,.15); color:#fff; border-color:rgba(255,255,255,.25); }
    .cap-btn.ghost-light:hover { background:rgba(255,255,255,.25); }
    .cap-btn.primary { background:linear-gradient(135deg,#00875A 0%,#005c3d 100%); color:#fff;
      border:none; font-weight:700; box-shadow:0 4px 18px rgba(0,135,90,.2); }
    .cap-btn.primary:hover { filter:brightness(1.08); transform:translateY(-1px); }
    .cap-btn.ghost { border-color:#e2e8f0; color:var(--co-mute); }
    .cap-btn.ghost:hover { background:#f8fafc; }
    .cap-btn.danger { color:#dc2626; border-color:rgba(220,38,38,.3); }
    .cap-btn.danger:hover { background:rgba(220,38,38,.06); }
    .cap-btn:disabled { opacity:.5; cursor:not-allowed; transform:none; filter:none; }

    .cap-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(155px,1fr)); gap:10px; }
    .cap-kpi { background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:15px 17px; }
    .cap-kpi .lbl { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--co-mute); }
    .cap-kpi .val { font-size:29px; font-weight:800; color:var(--co-navy-700); margin-top:3px; line-height:1.05; }
    .cap-kpi .val.sl { color:var(--sl); } .cap-kpi .val.wn { color:var(--wn); } .cap-kpi .val.er { color:var(--er); }
    .cap-kpi .foot { font-size:11px; color:var(--co-faint); margin-top:3px; min-height:15px; }

    .cap-toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap;
      background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:11px 13px; }
    .cap-search { flex:1; min-width:210px; display:flex; align-items:center; gap:8px;
      background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:10px; padding:8px 11px; }
    .cap-search svg { width:15px; height:15px; color:var(--co-faint); flex-shrink:0; }
    .cap-search input { flex:1; border:none; background:none; outline:none; font:inherit; font-size:13px; color:var(--co-ink); }
    .cap-filter { display:flex; gap:4px; flex-wrap:wrap; }
    .cap-filter button { padding:7px 11px; border:1px solid var(--co-line); background:#fff; border-radius:9px;
      font:inherit; font-size:12.5px; font-weight:600; color:var(--co-mute); cursor:pointer; }
    .cap-filter button.active { background:#00875A; border-color:#00875A; color:#fff; }
    .cap-sel { padding:8px 11px; border:1.5px solid #e2e8f0; border-radius:10px; font:inherit;
      font-size:12.5px; font-weight:600; color:var(--co-mute); background:#fff; outline:none; }
    .cap-sel.on { border-color:#00875A; color:#00875A; }

    .cap-table-card { background:#fff; border:1px solid var(--co-line); border-radius:14px; overflow:hidden;
      box-shadow:0 4px 18px rgba(0,135,90,.04); }
    .cap-table-scroll { overflow-x:auto; }
    .cap-table { width:100%; border-collapse:collapse; min-width:980px; table-layout:fixed; }
    /* Anchos explícitos: sin esto el navegador ajusta cada columna al
       contenido más largo de TODA la tabla, así que el ancho de cada
       celda cambia de fila a fila y nada queda alineado ("encuadre"
       roto que reportó el usuario). Con table-layout:fixed el grid es
       estable siempre, y el contenido que no cabe se trunca con "…". */
    .cap-table col.c1 { width:23%; } .cap-table col.c2 { width:12%; }
    .cap-table col.c3 { width:6.5%; } .cap-table col.c4 { width:6.5%; }
    .cap-table col.c5 { width:14%; } .cap-table col.c6 { width:11%; }
    .cap-table col.c7 { width:13%; } .cap-table col.c8 { width:14%; }
    .cap-table thead th { background:linear-gradient(180deg,#f8fafc,#f3f7f5); text-align:left; font-size:10.5px; text-transform:uppercase;
      letter-spacing:.06em; color:var(--co-mute); font-weight:700; padding:12px 13px; border-bottom:1px solid var(--co-line);
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cap-table tbody td { padding:12px 13px; border-bottom:1px solid #f1f5f9; font-size:13px; vertical-align:middle;
      overflow:hidden; }
    .cap-table tbody tr { cursor:pointer; transition:background .12s; }
    .cap-table tbody tr:last-child td { border-bottom:0; }
    .cap-table tbody tr:hover { background:rgba(0,135,90,.035); }
    /* El acento de hover va en el primer <td>, no en el <tr>: un ::before
       generado directo sobre <tr> hace que Chrome le añada una celda
       anónima invisible como "columna 0", corriendo una posición a la
       derecha TODAS las celdas reales y descuadrando toda la tabla. */
    .cap-table tbody td:first-child { position:relative; }
    .cap-table tbody td:first-child::before {
      content:''; position:absolute; left:0; top:0; bottom:0; width:3px;
      background:#00875A; transform:scaleY(0); transition:transform .15s;
    }
    .cap-table tbody tr:hover td:first-child::before { transform:scaleY(1); }
    .cap-tt { font-weight:700; color:var(--co-ink); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cap-sub { font-size:11.5px; color:var(--co-mute); margin-top:1px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cap-sub.venc { color:#dc2626; font-weight:700; }
    .cap-empty { text-align:center; padding:48px 20px; color:var(--co-faint); }
    .cap-empty svg { width:34px; height:34px; color:#cbd5e1; margin-bottom:8px; }

    .cap-st { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:999px;
      font-size:10.5px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; white-space:nowrap;
      border:1px solid transparent; }
    .cap-st .dot { width:6px; height:6px; border-radius:50%; }
    .cap-pill { display:inline-flex; align-items:center; gap:6px; padding:3px 9px 3px 3px; border-radius:999px;
      font-size:11px; font-weight:700; background:rgba(0,135,90,.08); color:#00875A; max-width:100%; }
    .cap-pill-av { width:19px; height:19px; border-radius:50%; flex-shrink:0; background:#00875A; color:#fff;
      font-size:8.5px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; letter-spacing:-.3px; }
    .cap-pill-tx { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cap-cnt { display:inline-flex; align-items:center; justify-content:center; min-width:26px; height:22px;
      padding:0 7px; border-radius:7px; background:#eef2f7; font-size:12px; font-weight:800; color:#334155; }
    .cap-cnt.warn { background:#fef3c7; color:#92400e; }

    .cap-bar { width:92px; height:7px; border-radius:999px; background:#e7ecf3; overflow:hidden; }
    .cap-bar i { display:block; height:100%; border-radius:999px; background:linear-gradient(90deg,#00875A,#00b377); transition:width .3s ease; }
    .cap-bar.low i { background:linear-gradient(90deg,#d97706,#f59e0b); }
    .cap-bar.crit i { background:linear-gradient(90deg,#dc2626,#ef4444); }
    .cap-barwrap { display:flex; align-items:center; gap:8px; }
    .cap-barwrap b { font-size:12px; font-weight:800; color:var(--sl); white-space:nowrap; }

    .cap-act { display:flex; gap:6px; justify-content:flex-end; flex-wrap:wrap; }
    .cap-act button { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border-radius:8px;
      border:1px solid var(--co-line); background:#fff;
      font:inherit; font-size:12px; font-weight:600; color:var(--co-mute); cursor:pointer; transition:all .12s; }
    .cap-act button svg { width:13px; height:13px; }
    .cap-act button:hover { border-color:#00875A; color:#00875A; background:rgba(0,135,90,.05); }
    .cap-act button.danger:hover { border-color:#dc2626; color:#dc2626; background:rgba(220,38,38,.05); }

    /* ── Modal ── */
    .cap-modal-back { position:fixed; inset:0; background:rgba(15,23,42,.5); backdrop-filter:blur(2px);
      display:none; align-items:center; justify-content:center; z-index:200; padding:20px;
      font-family:'DM Sans', system-ui, sans-serif; }
    .cap-modal-back.open { display:flex; }
    .cap-modal { background:#fff; border-radius:18px; width:100%; max-width:640px; max-height:92vh;
      display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 70px rgba(0,0,0,.25); }
    .cap-modal.wide { max-width:900px; }
    .cap-modal-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
      padding:18px 22px; border-bottom:1px solid var(--co-line); }
    .cap-modal-head h2 { margin:0; font-size:18px; font-weight:800; color:var(--co-ink); }
    .cap-modal-head .sub { font-size:12.5px; color:var(--co-mute); margin-top:2px; }
    .cap-modal-close { border:none; background:#f1f5f9; width:32px; height:32px; border-radius:9px;
      font-size:19px; line-height:1; color:#64748b; cursor:pointer; flex-shrink:0; }
    .cap-modal-close:hover { background:#e2e8f0; }
    .cap-modal-body { padding:18px 22px; overflow-y:auto; display:flex; flex-direction:column; gap:15px; }
    .cap-modal-foot { display:flex; align-items:center; justify-content:space-between; gap:10px;
      padding:14px 22px; border-top:1px solid var(--co-line); background:#f8fafc; flex-wrap:wrap; }
    .cap-modal-foot .right { display:flex; gap:8px; flex-wrap:wrap; }
    .cap-foot-note { font-size:12px; color:var(--co-faint); }

    /* ── Pestañas ── */
    .cap-tabs { display:flex; gap:2px; padding:0 22px; border-bottom:1px solid var(--co-line); background:#fff; flex-wrap:wrap; }
    .cap-tab { padding:11px 15px; font-family:inherit; font-size:13px; font-weight:600; color:var(--co-mute);
      border:0; border-bottom:2.5px solid transparent; background:none; cursor:pointer;
      display:inline-flex; align-items:center; gap:8px; }
    .cap-tab:hover:not(:disabled) { color:var(--co-ink); }
    .cap-tab.on { color:#00875A; border-bottom-color:#00875A; font-weight:700; }
    .cap-tab:disabled { opacity:.4; cursor:not-allowed; }
    .cap-tab .badge { font-size:10.5px; font-weight:800; background:#eef2f7; color:#475569; border-radius:6px; padding:1px 6px; }
    .cap-tab.on .badge { background:rgba(0,135,90,.1); color:#00875A; }
    .cap-tab .badge.warn { background:var(--wn-bg); color:var(--wn); }
    .cap-pane { display:none; flex-direction:column; gap:15px; }
    .cap-pane.on { display:flex; }

    /* ── Campos ── */
    .cap-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .cap-f { display:flex; flex-direction:column; gap:5px; }
    .cap-f.full { grid-column:1 / -1; }
    .cap-f label { font-size:11.5px; font-weight:700; color:#475569; }
    .cap-f input, .cap-f select, .cap-f textarea {
      width:100%; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:10px;
      font:inherit; font-size:13px; color:var(--co-ink); background:#fff; outline:none; }
    .cap-f textarea { min-height:64px; resize:vertical; }
    .cap-f input:focus, .cap-f select:focus, .cap-f textarea:focus {
      border-color:#00875A; box-shadow:0 0 0 3px rgba(0,135,90,.12); }
    .cap-f input:disabled, .cap-f select:disabled, .cap-f textarea:disabled { background:#f8fafc; color:var(--co-mute); cursor:not-allowed; }
    .cap-f .hint { font-size:11px; color:var(--co-faint); }
    .cap-req { color:#dc2626; }

    .cap-step { font-size:10.5px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--co-faint);
      display:flex; align-items:center; gap:8px; margin:2px 0 -4px; }
    .cap-step .n { display:inline-flex; align-items:center; justify-content:center; width:19px; height:19px;
      border-radius:6px; background:#eef2f7; color:#475569; font-size:10.5px; }

    /* ── Temas ── */
    .cap-temas { display:flex; flex-direction:column; gap:7px; }
    .cap-tema { display:flex; align-items:flex-start; gap:9px; border:1.5px solid #e5e7eb;
      border-radius:11px; padding:9px 11px; background:#fff; }
    .cap-tema .num { flex-shrink:0; width:22px; height:22px; border-radius:7px; background:rgba(0,135,90,.1);
      color:#00875A; font-size:11.5px; font-weight:800; display:inline-flex; align-items:center;
      justify-content:center; margin-top:2px; }
    .cap-tema .cols { flex:1; display:flex; flex-direction:column; gap:5px; min-width:0; }
    .cap-tema input, .cap-tema textarea { border:none; outline:none; font:inherit; font-family:inherit;
      padding:0; width:100%; background:none; }
    .cap-tema .ttl { font-size:13.5px; font-weight:700; color:var(--co-ink); }
    .cap-tema .dsc { font-size:12px; color:var(--co-mute); min-height:18px; resize:vertical; }
    .cap-tema .del { flex-shrink:0; border:none; background:none; color:var(--co-faint); cursor:pointer;
      font-size:16px; line-height:1; padding:2px 4px; }
    .cap-tema .del:hover { color:#dc2626; }
    .cap-addtema { align-self:flex-start; border:1.5px dashed #cbd5e1; background:none; border-radius:10px;
      padding:8px 13px; font:inherit; font-family:inherit; font-size:12.5px; font-weight:600;
      color:var(--co-mute); cursor:pointer; }
    .cap-addtema:hover { border-color:#00875A; color:#00875A; }

    /* ── Adjuntos ── */
    .cap-drop { border:1.5px dashed #cbd5e1; border-radius:12px; padding:16px; text-align:center;
      background:#f8fafc; cursor:pointer; }
    .cap-drop.hot { border-color:#00875A; background:rgba(0,135,90,.05); }
    .cap-drop .big { font-size:13px; font-weight:700; color:#475569; }
    .cap-drop .sm { font-size:11.5px; color:var(--co-faint); margin-top:3px; }
    .cap-files { display:flex; flex-direction:column; gap:6px; }
    .cap-file { display:flex; align-items:center; gap:10px; border:1px solid #e5e7eb; border-radius:10px; padding:8px 11px; }
    .cap-file .ic { width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center;
      justify-content:center; font-size:9.5px; font-weight:800; color:#fff; flex-shrink:0; background:#64748b; }
    .cap-file .nm { flex:1; font-size:12.5px; font-weight:600; color:var(--co-ink); min-width:0;
      overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cap-file .nm a { color:inherit; text-decoration:none; }
    .cap-file .nm a:hover { text-decoration:underline; }
    .cap-file .mt { font-size:11px; color:var(--co-faint); white-space:nowrap; }
    .cap-file .drv { font-size:10px; font-weight:700; color:#00875A; background:rgba(0,135,90,.1);
      border-radius:999px; padding:2px 8px; white-space:nowrap; }
    .cap-file .drv.pend { color:var(--wn); background:var(--wn-bg); }
    .cap-file .drv.err  { color:var(--er); background:var(--er-bg); }
    .cap-file .x { border:none; background:none; color:var(--co-faint); cursor:pointer; font-size:16px; line-height:1; }
    .cap-file .x:hover { color:#dc2626; }

    /* ── Asistencia ── */
    .cap-metrics { display:flex; gap:9px; flex-wrap:wrap; }
    .cap-metric { flex:1; min-width:105px; background:#fff; border:1px solid var(--co-line);
      border-radius:11px; padding:10px 12px; }
    .cap-metric .m1 { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--co-mute); }
    .cap-metric .m2 { font-size:21px; font-weight:800; margin-top:2px; }

    .cap-asbar { display:flex; align-items:center; gap:9px; flex-wrap:wrap; background:#f8fafc;
      border:1px solid var(--co-line); border-radius:12px; padding:10px 12px; }
    .cap-asbar .grow { flex:1; min-width:150px; background:#fff; }
    .cap-mass { display:flex; gap:5px; }
    .cap-mass button { padding:6px 10px; border:1px solid var(--co-line); background:#fff; border-radius:8px;
      font:inherit; font-family:inherit; font-size:11.5px; font-weight:700; color:var(--co-mute); cursor:pointer; }
    .cap-mass button:hover { border-color:#00875A; color:#00875A; }

    .cap-roster { border:1.5px solid #e5e7eb; border-radius:12px; overflow:hidden; max-height:360px; overflow-y:auto; }
    .cap-rgrp { background:#f8fafc; padding:6px 12px; font-size:10.5px; font-weight:800; letter-spacing:.06em;
      text-transform:uppercase; color:var(--co-mute); border-bottom:1px solid #eef2f7; position:sticky; top:0; z-index:1; }
    .cap-rrow { display:flex; align-items:center; gap:10px; padding:8px 12px; border-bottom:1px solid #f5f7fa; }
    .cap-rrow:hover { background:#fbfdfc; }
    .cap-rrow.sinmarcar { background:#fffdf5; }
    .cap-rrow.historico { opacity:.7; }
    .cap-av { width:29px; height:29px; border-radius:9px; background:#eef2f7; color:#475569; font-size:11px;
      font-weight:800; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
    .cap-rrow .who { flex:1; min-width:0; }
    .cap-rrow .nm { font-size:13px; font-weight:600; color:var(--co-ink); white-space:nowrap;
      overflow:hidden; text-overflow:ellipsis; }
    .cap-rrow .mt { font-size:11px; color:var(--co-mute); }
    .cap-aft { display:flex; gap:3px; flex-shrink:0; }
    .cap-aft button { width:31px; height:29px; border:1.5px solid #e5e7eb; background:#fff; border-radius:8px;
      font:inherit; font-family:inherit; font-size:12px; font-weight:800; color:var(--co-faint); cursor:pointer; }
    .cap-aft button:hover:not(:disabled) { border-color:#cbd5e1; }
    .cap-aft button:disabled { cursor:not-allowed; }
    .cap-aft button.on[data-v="asistio"]  { background:var(--ok-bg); border-color:var(--ok); color:var(--ok); }
    .cap-aft button.on[data-v="tardanza"] { background:var(--wn-bg); border-color:var(--wn); color:var(--wn); }
    .cap-aft button.on[data-v="falta"]    { background:var(--er-bg); border-color:var(--er); color:var(--er); }

    /* ── Validación ── */
    .cap-verdict { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .cap-vopt { border:2px solid #e5e7eb; border-radius:14px; padding:14px; cursor:pointer; background:#fff;
      text-align:left; font-family:inherit; }
    .cap-vopt .vt { font-size:14px; font-weight:800; display:flex; align-items:center; gap:8px; color:var(--co-ink); }
    .cap-vopt .vd { font-size:12px; color:var(--co-mute); margin-top:5px; line-height:1.45; }
    .cap-vopt .ico { width:26px; height:26px; border-radius:8px; display:inline-flex; align-items:center;
      justify-content:center; font-size:14px; color:#fff; }
    .cap-vopt.ok .ico { background:var(--ok); } .cap-vopt.no .ico { background:var(--er); }
    .cap-vopt.ok.on { border-color:var(--ok); background:var(--ok-bg); }
    .cap-vopt.no.on { border-color:var(--er); background:var(--er-bg); }
    .cap-vopt:disabled { opacity:.55; cursor:not-allowed; }

    .cap-ro { background:#f8fafc; border:1px solid var(--co-line); border-radius:12px; padding:13px 15px; }
    .cap-ro .rt { font-size:10.5px; font-weight:800; letter-spacing:.06em; text-transform:uppercase;
      color:var(--co-mute); margin-bottom:7px; }
    .cap-ro ol { margin:0; padding-left:19px; font-size:13px; line-height:1.65; }
    .cap-ro ol span { color:var(--co-mute); font-size:12px; display:block; }
    .cap-ro .vacio { font-size:12.5px; color:var(--co-faint); }

    .cap-cmt { display:flex; gap:10px; border:1px solid var(--co-line); border-radius:12px; padding:11px 13px; background:#fff; }
    .cap-cmt .cb { flex:1; min-width:0; }
    .cap-cmt .ch { font-size:12px; font-weight:700; color:var(--co-ink); }
    .cap-cmt .ch span { font-weight:400; color:var(--co-faint); font-size:11.5px; }
    .cap-cmt .cx { font-size:12.5px; color:#475569; margin-top:4px; line-height:1.5; white-space:pre-wrap; }

    .cap-note { background:#fffbeb; border:1px solid #fde68a; border-radius:11px; padding:10px 13px;
      font-size:12.5px; color:#92400e; line-height:1.5; }
    .cap-note b { font-weight:800; }
    .cap-note.info { background:#eff6ff; border-color:#bfdbfe; color:#1e40af; }

    /* Los modales viven fuera de .cap-wrap, así que no heredan sus variables:
       sin esto, --ok/--wn/--er/--sl caían a inválido y varios estados
       (asistencia marcada, veredicto de validación, adjuntos con error)
       se pintaban sin color. */
    #capBackNew, #capBackDet {
      --ok:#047857; --ok-bg:rgba(4,120,87,.10);
      --wn:#d97706; --wn-bg:rgba(217,119,6,.10);
      --er:#dc2626; --er-bg:rgba(220,38,38,.10);
      --sl:#475569; --sl-bg:rgba(100,116,139,.12);
    }

    /* ── Modal "Nueva capacitación" (rediseño) ── */
    #capBackNew.cap-modal-back {
      display:flex; opacity:0; pointer-events:none; transition:opacity .2s;
    }
    #capBackNew.cap-modal-back.open { opacity:1; pointer-events:auto; }
    #capBackNew .cap-modal {
      transform:translateY(14px) scale(.97); transition:transform .22s cubic-bezier(.25,.46,.45,.94);
      border-radius:20px; box-shadow:0 24px 64px rgba(0,135,90,.14);
    }
    #capBackNew.cap-modal-back.open .cap-modal { transform:translateY(0) scale(1); }

    #capBackNew .cap-modal-head { align-items:center; padding:20px 24px 16px; }
    .cap-new-head { display:flex; align-items:center; gap:13px; min-width:0; }
    .cap-new-ico {
      width:44px; height:44px; border-radius:13px; flex-shrink:0;
      background:linear-gradient(135deg,#00875A 0%,#00b377 100%); color:#fff;
      display:grid; place-items:center; box-shadow:0 4px 14px -4px rgba(0,135,90,.45);
    }
    .cap-new-ico svg { width:21px; height:21px; }
    #capBackNew .cap-modal-body { padding:20px 24px; gap:18px; }

    #capBackNew .cap-f.ico label { display:flex; align-items:center; gap:5px; }
    #capBackNew .cap-fw { position:relative; }
    #capBackNew .cap-fw svg.fic {
      position:absolute; left:12px; top:50%; transform:translateY(-50%);
      width:15px; height:15px; color:#00875A; pointer-events:none;
    }
    #capBackNew .cap-fw input, #capBackNew .cap-fw select { padding-left:36px; }
    #capBackNew .cap-fw select {
      appearance:none; -webkit-appearance:none; padding-right:32px;
      background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
      background-repeat:no-repeat; background-position:right 10px center; background-size:16px; cursor:pointer;
    }
    #capBackNew .cap-f input, #capBackNew .cap-f select {
      border-radius:11px; transition:border-color .15s, box-shadow .15s;
    }
    #capBackNew .cap-exp-otro { margin-top:7px; }

    #capBackNew .cap-note.info {
      display:flex; align-items:flex-start; gap:10px;
      background:linear-gradient(180deg,#eff6ff,#f5f9ff); border-color:#bfdbfe;
    }
    #capBackNew .cap-note.info svg { width:16px; height:16px; flex-shrink:0; margin-top:1px; color:#2563eb; }

    #capBackNew .cap-modal-foot { padding:16px 24px; }
    .cap-new-foot { display:flex; align-items:center; gap:8px; }
    .cap-new-foot-av {
      width:24px; height:24px; border-radius:7px; flex-shrink:0;
      background:rgba(0,135,90,.1); color:#00875A; font-size:10px; font-weight:800;
      display:grid; place-items:center; letter-spacing:-.3px;
    }

    /* ── Modal "Detalle" (rediseño) ── */
    #capBackDet.cap-modal-back {
      display:flex; opacity:0; pointer-events:none; transition:opacity .2s;
    }
    #capBackDet.cap-modal-back.open { opacity:1; pointer-events:auto; }
    #capBackDet .cap-modal {
      transform:translateY(14px) scale(.97); transition:transform .22s cubic-bezier(.25,.46,.45,.94);
      border-radius:20px; box-shadow:0 24px 64px rgba(0,135,90,.14);
    }
    #capBackDet.cap-modal-back.open .cap-modal { transform:translateY(0) scale(1); }

    #capBackDet .cap-modal-head { padding:20px 24px 16px; }
    .cap-det-head { display:flex; align-items:center; gap:13px; min-width:0; }
    .cap-det-ico {
      width:44px; height:44px; border-radius:13px; flex-shrink:0;
      background:linear-gradient(135deg,#00875A 0%,#00b377 100%); color:#fff;
      display:grid; place-items:center; box-shadow:0 4px 14px -4px rgba(0,135,90,.45);
    }
    .cap-det-ico svg { width:21px; height:21px; }
    #capBackDet .cap-modal-head h2 { font-size:17px; }

    #capBackDet .cap-tabs { padding:0 24px; gap:4px; }
    #capBackDet .cap-tab {
      display:inline-flex; align-items:center; gap:7px; border-radius:10px 10px 0 0;
      transition:color .12s, background .12s;
    }
    #capBackDet .cap-tab svg { width:14px; height:14px; flex-shrink:0; }
    #capBackDet .cap-tab:hover:not(:disabled) { background:rgba(0,135,90,.04); }
    #capBackDet .cap-tab.on { background:rgba(0,135,90,.06); border-bottom-width:2.5px; }

    #capBackDet .cap-modal-body { padding:20px 24px; gap:18px; }
    #capBackDet .cap-modal-foot { padding:16px 24px; }

    /* ── Pestaña Contenido ── */
    #capBackDet .cap-step {
      display:flex; align-items:center; gap:9px; margin:4px 0 2px;
    }
    #capBackDet .cap-step::after { content:''; flex:1; height:1px; background:var(--co-line); }
    #capBackDet .cap-step .n {
      width:21px; height:21px; border-radius:7px; background:rgba(0,135,90,.1); color:#00875A;
    }
    #capBackDet .cap-f.ico label { display:flex; align-items:center; gap:5px; }
    #capBackDet .cap-fw { position:relative; }
    #capBackDet .cap-fw svg.fic {
      position:absolute; left:12px; top:50%; transform:translateY(-50%);
      width:15px; height:15px; color:#00875A; pointer-events:none;
    }
    #capBackDet .cap-fw input, #capBackDet .cap-fw select { padding-left:36px; }
    #capBackDet .cap-fw select {
      appearance:none; -webkit-appearance:none; padding-right:32px;
      background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
      background-repeat:no-repeat; background-position:right 10px center; background-size:16px; cursor:pointer;
    }
    #capBackDet .cap-f input, #capBackDet .cap-f select, #capBackDet .cap-f textarea {
      border-radius:11px; transition:border-color .15s, box-shadow .15s;
    }
    #capBackDet .cap-exp-otro { margin-top:7px; }

    #capBackDet .cap-tema {
      border-radius:13px; border-color:#e5e7eb; transition:border-color .15s, box-shadow .15s;
      padding:11px 13px;
    }
    #capBackDet .cap-tema:focus-within { border-color:rgba(0,135,90,.4); box-shadow:0 0 0 3px rgba(0,135,90,.08); }
    #capBackDet .cap-tema .num { width:24px; height:24px; border-radius:8px; }
    #capBackDet .cap-tema .del {
      width:24px; height:24px; border-radius:7px; display:grid; place-items:center;
      font-size:15px; transition:all .12s;
    }
    #capBackDet .cap-tema .del:hover { background:rgba(220,38,38,.08); }

    #capBackDet .cap-drop {
      border-radius:14px; padding:20px 16px; display:flex; flex-direction:column;
      align-items:center; gap:8px; transition:all .15s;
      background:linear-gradient(180deg,#f8faf9,#f2f7f5);
    }
    #capBackDet .cap-drop-ico {
      width:36px; height:36px; border-radius:10px; display:grid; place-items:center;
      background:#fff; border:1px solid rgba(0,135,90,.25); color:#00875A; transition:all .18s;
    }
    #capBackDet .cap-drop-ico svg { width:18px; height:18px; }
    #capBackDet .cap-drop:hover .cap-drop-ico { background:#00875A; color:#fff; transform:translateY(-2px); }

    #capBackDet .cap-file {
      border-radius:11px; padding:9px 12px; transition:box-shadow .15s;
    }
    #capBackDet .cap-file:hover { box-shadow:0 4px 14px -8px rgba(0,0,0,.18); }
    #capBackDet .cap-file .ic { border-radius:9px; box-shadow:0 2px 6px -2px rgba(0,0,0,.25); }
    #capBackDet .cap-file .x {
      width:24px; height:24px; border-radius:7px; display:grid; place-items:center; transition:all .12s;
    }
    #capBackDet .cap-file .x:hover { background:rgba(220,38,38,.08); }

    /* ── Pestaña Asistencia ──
       Antes esto era un borde de 3px casi invisible: el color no
       comunicaba nada si no te fijabas. Ahora el ícono, el fondo Y el
       número comparten el mismo tono — la tarjeta entera es la señal. */
    #capBackDet .cap-metric {
      display:flex; align-items:center; gap:11px;
      border-radius:13px; border-color:transparent;
      background:var(--mc-bg,#f8fafc); padding:11px 14px;
    }
    #capBackDet .cap-metric-ico {
      width:34px; height:34px; border-radius:10px; flex-shrink:0;
      background:var(--mc,#64748b); color:#fff; display:grid; place-items:center;
      box-shadow:0 3px 8px -3px var(--mc,#64748b);
    }
    #capBackDet .cap-metric-ico svg { width:16px; height:16px; }
    #capBackDet .cap-metric-txt { min-width:0; }
    #capBackDet .cap-metric .m2 { color:var(--mc,#334155); }
    #capBackDet .cap-metric:nth-child(1) { --mc:#047857; --mc-bg:rgba(4,120,87,.07); }
    #capBackDet .cap-metric:nth-child(2) { --mc:#d97706; --mc-bg:rgba(217,119,6,.07); }
    #capBackDet .cap-metric:nth-child(3) { --mc:#dc2626; --mc-bg:rgba(220,38,38,.07); }
    #capBackDet .cap-metric:nth-child(4) { --mc:#64748b; --mc-bg:rgba(100,116,139,.07); }

    #capBackDet .cap-asbar { border-radius:13px; }
    #capBackDet .cap-mass button { border-radius:9px; transition:all .12s; }

    #capBackDet .cap-roster { border-radius:13px; border-color:var(--co-line); box-shadow:0 4px 16px rgba(0,0,0,.02); }
    #capBackDet .cap-rgrp { font-weight:800; color:var(--co-navy-700,#00875A); background:#f5f8f7; }
    #capBackDet .cap-rrow { transition:background .12s; padding:9px 13px; }
    #capBackDet .cap-rrow:hover { background:rgba(0,135,90,.03); }
    #capBackDet .cap-rrow.sinmarcar { background:#fffcf2; border-left:2px solid #f59e0b; }
    #capBackDet .cap-av { border-radius:10px; background:linear-gradient(135deg,#eef2f7,#e4e9f0); }
    #capBackDet .cap-aft { gap:4px; }
    #capBackDet .cap-aft button { border-radius:9px; transition:all .12s; }
    #capBackDet .cap-aft button.on { box-shadow:0 2px 6px -2px rgba(0,0,0,.15); }

    #capBackDet .cap-note {
      display:flex; align-items:flex-start; gap:10px; border-radius:13px;
    }
    #capBackDet .cap-note svg { width:16px; height:16px; flex-shrink:0; margin-top:1px; }
    #capBackDet .cap-note:not(.info) svg { color:#d97706; }
    #capBackDet .cap-note.info svg { color:#2563eb; }

    /* ── Pestaña Validación ── */
    #capBackDet .cap-ro {
      border-radius:14px; position:relative; overflow:hidden; padding:15px 17px 15px 19px;
    }
    #capBackDet .cap-ro::before {
      content:''; position:absolute; left:0; top:0; bottom:0; width:4px;
      background:linear-gradient(180deg,#00875A,#00b377);
    }
    #capBackDet .cap-ro .rt { display:flex; align-items:center; gap:7px; }
    #capBackDet .cap-ro .rt svg { width:13px; height:13px; }
    #capBackDet .cap-ro ol { padding-left:21px; }
    #capBackDet .cap-ro ol li::marker { color:#00875A; font-weight:800; }

    #capBackDet .cap-vopt { border-radius:15px; transition:all .18s cubic-bezier(.22,.61,.36,1); }
    #capBackDet .cap-vopt:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 8px 20px -10px rgba(0,0,0,.18); }
    #capBackDet .cap-vopt .ico { border-radius:9px; font-weight:800; }
    #capBackDet .cap-vopt.ok.on { box-shadow:0 4px 16px -6px rgba(4,120,87,.3); }
    #capBackDet .cap-vopt.no.on { box-shadow:0 4px 16px -6px rgba(220,38,38,.3); }

    #capBackDet .cap-cmt { border-radius:13px; position:relative; }
    #capBackDet .cap-cmt .cap-av { background:linear-gradient(135deg,#eef2f7,#e4e9f0); border-radius:10px; }
    #capBackDet #vHistorial { position:relative; }
    #capBackDet #vHistorial .cap-cmt:not(:last-child)::after {
      content:''; position:absolute; left:29px; top:100%; width:1.5px; height:8px; background:var(--co-line);
    }

    .cap-toast { position:fixed; right:20px; bottom:20px; z-index:400; display:flex; flex-direction:column; gap:8px; }
    .cap-toast div { background:#0f172a; color:#fff; padding:11px 16px; border-radius:11px; font-size:13px;
      font-family:'DM Sans',system-ui,sans-serif; box-shadow:0 12px 32px rgba(0,0,0,.25); max-width:340px; }
    .cap-toast div.err { background:#dc2626; }
    .cap-toast div.ok  { background:#047857; }

    @media (max-width:760px) {
      .cap-grid, .cap-verdict { grid-template-columns:1fr; }
      .cap-hero { flex-direction:column; align-items:flex-start; }
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
      <div class="cap-wrap">

        <!-- HERO -->
        <section class="cap-hero">
          <div>
            <span class="tag">CONTROL DE CAMPO · CAPACITACIONES</span>
            <h1>Capacitaciones</h1>
            <p>Programa la capacitación, desarrolla sus temas, adjunta el material y marca la asistencia de toda la plantilla. El administrador valida si se realizó y deja su comentario.</p>
          </div>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
            <button class="cap-btn ghost-light" id="btnExcel">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Excel
            </button>
            <button class="cap-btn ghost-light" id="btnPdf">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
              PDF
            </button>
            <button class="cap-btn primary" id="btnNew">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Nueva capacitación
            </button>
          </div>
        </section>

        <!-- KPIS -->
        <section class="cap-kpis">
          <div class="cap-kpi"><div class="lbl">Programadas</div><div class="val sl" id="kpiProg">0</div><div class="foot" id="kpiProgFoot"></div></div>
          <div class="cap-kpi"><div class="lbl">Por validar</div><div class="val wn" id="kpiVal">0</div><div class="foot">esperan al administrador</div></div>
          <div class="cap-kpi"><div class="lbl">Realizadas · mes</div><div class="val" id="kpiReal">0</div><div class="foot" id="kpiRealFoot"></div></div>
          <div class="cap-kpi"><div class="lbl">No realizadas</div><div class="val er" id="kpiNo">0</div><div class="foot">con motivo registrado</div></div>
          <div class="cap-kpi"><div class="lbl">Asistencia media</div><div class="val" id="kpiAsis">—</div><div class="foot" id="kpiAsisFoot"></div></div>
        </section>

        <!-- TOOLBAR -->
        <div class="cap-toolbar">
          <div class="cap-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="capSearch" type="text" placeholder="Buscar por título, tema, expositor o lugar…">
          </div>
          <div class="cap-filter" id="capFilterEstado">
            <button class="active" data-e="todos">Todas</button>
            <?php foreach ($JS_ESTADOS as $k => $v): ?>
              <button data-e="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v['label']) ?></button>
            <?php endforeach; ?>
          </div>
          <select class="cap-sel" id="capFilterCoord">
            <option value="">Todos los coordinadores</option>
            <?php foreach ($COORDS as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="cap-sel" id="capFilterMes">
            <option value="">Todos los meses</option>
          </select>
        </div>

        <!-- TABLA -->
        <div class="cap-table-card">
          <div class="cap-table-scroll">
            <table class="cap-table">
              <colgroup>
                <col class="c1"><col class="c2"><col class="c3"><col class="c4">
                <col class="c5"><col class="c6"><col class="c7"><col class="c8">
              </colgroup>
              <thead>
                <tr>
                  <th>Capacitación</th><th>Fecha y hora</th>
                  <th style="text-align:center">Temas</th><th style="text-align:center">Adj.</th>
                  <th>Asistencia</th><th>Estado</th><th>Coordinador</th>
                  <th style="text-align:right">Acciones</th>
                </tr>
              </thead>
              <tbody id="capTbody">
                <tr><td colspan="8" class="cap-empty">Cargando…</td></tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>

<!-- ══════════ MODAL · CREAR / EDITAR CABECERA ══════════ -->
<div class="cap-modal-back" id="capBackNew">
  <div class="cap-modal">
    <div class="cap-modal-head">
      <div class="cap-new-head">
        <div class="cap-new-ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 3 7l9 5 9-5-9-5z"/><path d="M3 12l9 5 9-5"/><path d="M3 7v10l9 5 9-5V7"/></svg>
        </div>
        <div>
          <h2 id="newTitle">Nueva capacitación</h2>
          <div class="sub" id="newSub">Quedará como <b>Programada</b> hasta que la envíes a validación</div>
        </div>
      </div>
      <button class="cap-modal-close" data-close="capBackNew">&times;</button>
    </div>
    <div class="cap-modal-body">
      <input type="hidden" id="fId" value="0">
      <div class="cap-grid">
        <div class="cap-f full ico">
          <label>Título de la capacitación <span class="cap-req">*</span></label>
          <div class="cap-fw">
            <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21 12 17 5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
            <input type="text" id="fTitulo" maxlength="180" placeholder="Ej. Uso correcto de arnés y línea de vida">
          </div>
          <span class="hint">Es lo que verá el administrador en su bandeja. Sé específico.</span>
        </div>
        <div class="cap-f ico">
          <label>Fecha <span class="cap-req">*</span></label>
          <div class="cap-fw">
            <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <input type="date" id="fFecha">
          </div>
        </div>
        <div class="cap-f ico">
          <label>Hora <span class="cap-req">*</span></label>
          <div class="cap-fw">
            <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <input type="time" id="fHora">
          </div>
        </div>
        <div class="cap-f ico">
          <label>Duración estimada (min)</label>
          <div class="cap-fw">
            <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 1.5"/><path d="M9 1h6"/></svg>
            <input type="number" id="fDuracion" min="1" max="1440" placeholder="45">
          </div>
        </div>
        <div class="cap-f ico">
          <label>Lugar</label>
          <div class="cap-fw">
            <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <input type="text" id="fLugar" maxlength="120" placeholder="Muelle 2">
          </div>
        </div>
        <div class="cap-f full ico">
          <label>Expositor / capacitador</label>
          <div class="cap-fw">
            <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <select id="fExpositor">
              <option value="">Selecciona al coordinador…</option>
              <?php foreach ($COORDS as $c): ?>
                <option value="<?= htmlspecialchars($c['nombre']) ?>"><?= htmlspecialchars($c['nombre']) ?></option>
              <?php endforeach; ?>
              <option value="__otro__">Otro / capacitador externo…</option>
            </select>
          </div>
          <div class="cap-fw cap-exp-otro" style="display:none">
            <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
            <input type="text" id="fExpositorOtro" maxlength="120" placeholder="Nombre del capacitador externo">
          </div>
        </div>
      </div>
      <div class="cap-note info">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <span>Los temas, el material y la asistencia se cargan <b>entrando</b> a la capacitación.
        Pedirlo todo aquí obligaría a declarar al programar cosas que solo se saben después.</span>
      </div>
    </div>
    <div class="cap-modal-foot">
      <span class="cap-foot-note cap-new-foot" id="newFootNote">
        <span class="cap-new-foot-av"><?= htmlspecialchars(mb_strtoupper(mb_substr($USER_NAME, 0, 1))) ?></span>
        Se registrará a tu nombre · <?= htmlspecialchars($USER_NAME) ?>
      </span>
      <div class="right">
        <button class="cap-btn ghost" data-close="capBackNew">Cancelar</button>
        <button class="cap-btn primary" id="btnSaveNew">Crear capacitación</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════ MODAL · DETALLE (3 pestañas) ══════════ -->
<div class="cap-modal-back" id="capBackDet">
  <div class="cap-modal wide">
    <div class="cap-modal-head">
      <div class="cap-det-head">
        <div class="cap-det-ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/></svg>
        </div>
        <div style="min-width:0">
          <h2 id="detTitulo">—</h2>
          <div class="sub" id="detSub">—</div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
        <span id="detEstado"></span>
        <button class="cap-modal-close" data-close="capBackDet">&times;</button>
      </div>
    </div>

    <div class="cap-tabs">
      <button class="cap-tab on" data-tab="cont">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Contenido <span class="badge" id="tabContBadge">0</span>
      </button>
      <button class="cap-tab" data-tab="asis">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Asistencia <span class="badge" id="tabAsisBadge">0/0</span>
      </button>
      <button class="cap-tab" data-tab="vali" id="tabVali">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
        Validación
      </button>
    </div>

    <div class="cap-modal-body">

      <!-- ── PESTAÑA CONTENIDO ── -->
      <div class="cap-pane on" data-pane="cont">
        <div class="cap-step"><span class="n">1</span> Datos de la capacitación</div>
        <div class="cap-grid">
          <div class="cap-f full ico">
            <label>Título</label>
            <div class="cap-fw">
              <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21 12 17 5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
              <input type="text" id="dTitulo" maxlength="180">
            </div>
          </div>
          <div class="cap-f ico">
            <label>Fecha</label>
            <div class="cap-fw">
              <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <input type="date" id="dFecha">
            </div>
          </div>
          <div class="cap-f ico">
            <label>Hora</label>
            <div class="cap-fw">
              <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <input type="time" id="dHora">
            </div>
          </div>
          <div class="cap-f ico">
            <label>Duración (min)</label>
            <div class="cap-fw">
              <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 1.5"/><path d="M9 1h6"/></svg>
              <input type="number" id="dDuracion" min="1" max="1440">
            </div>
          </div>
          <div class="cap-f ico">
            <label>Lugar</label>
            <div class="cap-fw">
              <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <input type="text" id="dLugar" maxlength="120">
            </div>
          </div>
          <div class="cap-f full ico">
            <label>Expositor / capacitador</label>
            <div class="cap-fw">
              <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <select id="dExpositor">
                <option value="">Selecciona al coordinador…</option>
                <?php foreach ($COORDS as $c): ?>
                  <option value="<?= htmlspecialchars($c['nombre']) ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
                <option value="__otro__">Otro / capacitador externo…</option>
              </select>
            </div>
            <div class="cap-fw cap-exp-otro" style="display:none">
              <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
              <input type="text" id="dExpositorOtro" maxlength="120" placeholder="Nombre del capacitador externo">
            </div>
          </div>
        </div>

        <div class="cap-step"><span class="n">2</span> Temas desarrollados</div>
        <div class="cap-temas" id="dTemas"></div>
        <button class="cap-addtema" id="btnAddTema">+ Agregar tema</button>

        <div class="cap-step"><span class="n">3</span> Material y evidencias</div>
        <div class="cap-drop" id="dDrop">
          <div class="cap-drop-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          </div>
          <div class="big">Arrastra archivos aquí o haz clic para seleccionar</div>
          <div class="sm">Imágenes, PDF, Word, Excel, PowerPoint y video · máx. 4 MB por archivo · hasta <?= (int)cap_max_adjuntos() ?> archivos</div>
        </div>
        <input type="file" id="dFileInput" multiple accept="<?= htmlspecialchars(sg_accept_attr()) ?>" style="display:none">
        <div class="cap-files" id="dFiles"></div>

        <div class="cap-step"><span class="n">4</span> Observaciones del coordinador</div>
        <div class="cap-f">
          <textarea id="dObs" placeholder="Contexto, incidencias durante la sesión, compromisos asumidos…"></textarea>
        </div>
      </div>

      <!-- ── PESTAÑA ASISTENCIA ── -->
      <div class="cap-pane" data-pane="asis">
        <div class="cap-metrics">
          <div class="cap-metric">
            <div class="cap-metric-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
            <div class="cap-metric-txt"><div class="m1">Asistieron</div><div class="m2" id="mAsis">0</div></div>
          </div>
          <div class="cap-metric">
            <div class="cap-metric-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            <div class="cap-metric-txt"><div class="m1">Tardanza</div><div class="m2" id="mTard">0</div></div>
          </div>
          <div class="cap-metric">
            <div class="cap-metric-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
            <div class="cap-metric-txt"><div class="m1">Faltaron</div><div class="m2" id="mFalt">0</div></div>
          </div>
          <div class="cap-metric">
            <div class="cap-metric-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <div class="cap-metric-txt"><div class="m1">Sin marcar</div><div class="m2" id="mSin">0</div></div>
          </div>
        </div>

        <div class="cap-asbar">
          <div class="cap-search grow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="aSearch" placeholder="Buscar colaborador…">
          </div>
          <select class="cap-sel" id="aCuadrilla"><option value="">Todas las cuadrillas</option></select>
          <select class="cap-sel" id="aCoord"><option value="">Todos los coordinadores</option></select>
          <div class="cap-mass" id="aMass">
            <button data-m="asistio">Marcar todos A</button>
            <button data-m="limpiar">Limpiar</button>
          </div>
        </div>

        <div class="cap-roster" id="aRoster"></div>

        <div class="cap-note">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <span><b>Nadie viene marcado como «asistió».</b> Las filas sin marcar salen en ámbar y se cuentan aparte.
          Con el estado por defecto en «A», un coordinador que no abre esta pestaña generaría un 100&nbsp;% de
          asistencia falso, y el administrador estaría validando un dato que nadie miró.</span>
        </div>
      </div>

      <!-- ── PESTAÑA VALIDACIÓN ── -->
      <div class="cap-pane" data-pane="vali">
        <div class="cap-ro">
          <div class="rt"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Lo que declaró el coordinador</div>
          <div id="vTemas"></div>
          <div class="cap-metrics" style="margin-top:12px">
            <div class="cap-metric">
              <div class="cap-metric-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
              <div class="cap-metric-txt"><div class="m1">Asistieron</div><div class="m2" id="vAsis">0</div></div>
            </div>
            <div class="cap-metric">
              <div class="cap-metric-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
              <div class="cap-metric-txt"><div class="m1">Tardanza</div><div class="m2" id="vTard">0</div></div>
            </div>
            <div class="cap-metric">
              <div class="cap-metric-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
              <div class="cap-metric-txt"><div class="m1">Faltaron</div><div class="m2" id="vFalt">0</div></div>
            </div>
            <div class="cap-metric">
              <div class="cap-metric-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05 12.25 20.24a5.5 5.5 0 0 1-7.78-7.78l9.19-9.19a3.67 3.67 0 0 1 5.19 5.19l-9.2 9.19a1.83 1.83 0 0 1-2.6-2.6l8.49-8.48"/></svg></div>
              <div class="cap-metric-txt"><div class="m1">Adjuntos</div><div class="m2" id="vAdj">0</div></div>
            </div>
          </div>
        </div>

        <div id="vFormWrap">
          <div class="cap-step"><span class="n">1</span> ¿Se realizó la capacitación?</div>
          <div class="cap-verdict" style="margin-top:10px">
            <button class="cap-vopt ok" data-v="realizada">
              <div class="vt"><span class="ico">&#10003;</span> Sí, se realizó</div>
              <div class="vd">Ocurrió y el registro refleja lo que pasó. Cuenta para el cumplimiento del mes.</div>
            </button>
            <button class="cap-vopt no" data-v="no_realizada">
              <div class="vt"><span class="ico">&times;</span> No se realizó</div>
              <div class="vd">No ocurrió, o lo registrado no la respalda. El registro se conserva con su motivo.</div>
            </button>
          </div>

          <div class="cap-step" style="margin-top:16px"><span class="n">2</span> Comentario del administrador</div>
          <div class="cap-f" style="margin-top:8px">
            <textarea id="vComentario" style="min-height:90px" maxlength="4000"
              placeholder="Puntos a mejorar, felicitación, compromisos…"></textarea>
            <span class="hint">Visible para el coordinador. Obligatorio si marcas «No se realizó».</span>
          </div>
        </div>

        <div id="vAviso"></div>

        <div class="cap-step"><span class="n">&#9679;</span> Historial</div>
        <div id="vHistorial" style="display:flex;flex-direction:column;gap:8px"></div>
      </div>

    </div>

    <div class="cap-modal-foot">
      <span class="cap-foot-note" id="detFootNote"></span>
      <div class="right" id="detActions"></div>
    </div>
  </div>
</div>

<div class="cap-toast" id="capToast"></div>

<script>
/* ═══════════════════════════════════════════════════════════════════════
   CAPACITACIONES · lógica de página
   Ciclo:  programada ──(coordinador)──▶ por_validar ──(admin)──▶ realizada
                                                                 no_realizada
   ═══════════════════════════════════════════════════════════════════════ */
const ESTADOS    = <?= json_encode($JS_ESTADOS, JSON_UNESCAPED_UNICODE) ?>;
const ASISTENCIA = <?= json_encode($JS_ASISTENCIA, JSON_UNESCAPED_UNICODE) ?>;
const ES_ADMIN   = <?= $ES_ADMIN ? 'true' : 'false' ?>;
const USER_ID    = <?= (int)$USER_ID ?>;
const USER_ROL   = <?= json_encode($USER_ROL) ?>;
const LOGO_B64   = <?= json_encode($LOGO_B64) ?>;
const MAX_BYTES  = <?= (int)cap_max_bytes() ?>;

let CAPS       = [];      // capacitaciones cargadas
let PLANTILLA  = [];      // colaboradores del detalle abierto
let MARCAS     = {};      // colaborador_id => 'asistio'|'tardanza'|'falta'  (buffer en memoria)
let TEMAS      = [];      // [{titulo, descripcion}]                          (buffer en memoria)
let DET        = null;    // capacitación abierta
let VEREDICTO  = null;    // 'realizada' | 'no_realizada' elegido por el admin
let plantillaActiva = 0;

const $  = s => document.querySelector(s);
const $$ = s => Array.from(document.querySelectorAll(s));
const esc = s => String(s ?? '').replace(/[&<>"']/g, c =>
  ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function toast(msg, tipo) {
  const box = document.createElement('div');
  if (tipo) box.className = tipo;
  box.textContent = msg;
  $('#capToast').appendChild(box);
  setTimeout(() => box.remove(), 4200);
}

/* ── Helpers de formato ─────────────────────────────────────────────── */
function fFecha(d)  { if (!d) return '—'; const p = String(d).split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }
function fHora(h)   { return h ? String(h).slice(0, 5) : '—'; }
function fStamp(ts) {
  if (!ts) return '';
  const s = String(ts).replace(' ', 'T');
  const d = new Date(s);
  if (isNaN(d)) return ts;
  const p = n => String(n).padStart(2, '0');
  return p(d.getDate()) + '/' + p(d.getMonth() + 1) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
}
function fPeso(b) {
  b = Number(b) || 0;
  if (b < 1024) return b + ' B';
  if (b < 1024 * 1024) return Math.round(b / 1024) + ' KB';
  return (b / 1024 / 1024).toFixed(1) + ' MB';
}
function iniciales(n) {
  const p = String(n || '').trim().split(/\s+/);
  return ((p[0] || '')[0] || '?').toUpperCase() + ((p[1] || '')[0] || '').toUpperCase();
}

/* ── Expositor: selector de coordinador + "otro" para invitados externos ──
   La columna sigue siendo texto libre en BD (compatibilidad con lo ya
   registrado); esto solo restringe la UI a elegir un coordinador salvo
   que se declare explícitamente un capacitador externo. */
function setExpositor(selId, otroId, valor) {
  const sel = $(selId), otro = $(otroId), wrap = otro.closest('.cap-exp-otro');
  const val = valor || '';
  const esConocido = val && Array.from(sel.options).some(o => o.value === val);
  if (esConocido) { sel.value = val; wrap.style.display = 'none'; otro.value = ''; }
  else if (val)    { sel.value = '__otro__'; wrap.style.display = ''; otro.value = val; }
  else              { sel.value = ''; wrap.style.display = 'none'; otro.value = ''; }
}
function getExpositor(selId, otroId) {
  const sel = $(selId);
  return sel.value === '__otro__' ? $(otroId).value.trim() : sel.value;
}
['#fExpositor', '#dExpositor'].forEach(s => $(s).addEventListener('change', function () {
  const otro = $(s + 'Otro');
  otro.closest('.cap-exp-otro').style.display = this.value === '__otro__' ? '' : 'none';
  if (this.value === '__otro__') otro.focus();
}));
function chipEstado(k) {
  const e = ESTADOS[k]; if (!e) return esc(k);
  const borde = /^#[0-9a-f]{6}$/i.test(e.color) ? e.color + '4d' : e.color;   // ~30% opacidad
  return '<span class="cap-st" style="background:' + e.bg + ';color:' + e.color + ';border-color:' + borde + '">' +
         '<span class="dot" style="background:' + e.color + '"></span>' + esc(e.label) + '</span>';
}

/** Espejo en cliente de cap_puede_editar(): el servidor manda igual. */
function puedeEditar(c) {
  if (!c || c.estado !== 'programada') return false;
  if (USER_ROL === 'Administrador' || USER_ROL === 'Supervisor') return true;
  return USER_ROL === 'Coordinador' && Number(c.coordinador_id) === USER_ID;
}
function puedeEliminar(c) { return ES_ADMIN || puedeEditar(c); }

/* ── Carga ──────────────────────────────────────────────────────────── */
async function cargar() {
  try {
    const r = await fetch('../api/get_capacitaciones.php');
    const j = await r.json();
    if (!j.success) { toast(j.error || 'No se pudo cargar.', 'err'); return; }
    CAPS = j.data || [];
    plantillaActiva = j.plantillaActiva || 0;
    llenarMeses();
    render();
  } catch (e) {
    toast('Error de red al cargar las capacitaciones.', 'err');
  }
}

function llenarMeses() {
  const sel = $('#capFilterMes');
  const actual = sel.value;
  const meses = [...new Set(CAPS.map(c => String(c.fecha).slice(0, 7)))].sort().reverse();
  const nom = ['enero','febrero','marzo','abril','mayo','junio','julio',
               'agosto','septiembre','octubre','noviembre','diciembre'];
  sel.innerHTML = '<option value="">Todos los meses</option>' + meses.map(m => {
    const [y, mm] = m.split('-');
    const label = nom[parseInt(mm, 10) - 1] + ' ' + y;
    return '<option value="' + m + '">' + label.charAt(0).toUpperCase() + label.slice(1) + '</option>';
  }).join('');
  if (meses.includes(actual)) sel.value = actual;
}

/* ── Filtros · única fuente para tabla, Excel y PDF ─────────────────── */
function listaVisible() {
  const q      = ($('#capSearch').value || '').trim().toLowerCase();
  const estado = $('#capFilterEstado').querySelector('button.active').dataset.e;
  const coord  = $('#capFilterCoord').value;
  const mes    = $('#capFilterMes').value;

  return CAPS.filter(c => {
    if (estado !== 'todos' && c.estado !== estado) return false;
    if (coord && String(c.coordinador_id) !== coord) return false;
    if (mes && String(c.fecha).slice(0, 7) !== mes) return false;
    if (q) {
      const heno = [c.titulo, c.expositor, c.lugar, c.coordinador,
                    (c.temas || []).map(t => t.titulo + ' ' + (t.descripcion || '')).join(' ')]
                   .join(' ').toLowerCase();
      if (!heno.includes(q)) return false;
    }
    return true;
  });
}

function render() {
  const lista = listaVisible();
  const tb = $('#capTbody');

  if (!lista.length) {
    tb.innerHTML = '<tr><td colspan="8" class="cap-empty">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11H3v10h6z"/><path d="M15 3H9v18h6z"/><path d="M21 7h-6v14h6z"/></svg><br>' +
      (CAPS.length ? 'Ningún registro coincide con los filtros.' : 'Todavía no hay capacitaciones registradas.') +
      '</td></tr>';
  } else {
    const hoy = new Date().toISOString().slice(0, 10);
    tb.innerHTML = lista.map(c => {
      const total   = c.plantilla || 0;
      const pct     = total ? Math.round(c.marcados * 100 / total) : 0;
      const tier    = pct < 50 ? ' crit' : (pct < 80 ? ' low' : '');
      const barra   = total
        ? '<div class="cap-barwrap"><div class="cap-bar' + tier + '">' +
          '<i style="width:' + pct + '%"></i></div><b>' + c.marcados + '/' + total + '</b></div>'
        : '<span style="font-size:12px;color:#9ca3af">—</span>';
      const nTemas  = (c.temas || []).length;
      const nAdj    = (c.adjuntos || []).length;
      const dur     = c.duracion_min ? ' · ' + c.duracion_min + ' min' : '';
      const sub     = [c.expositor ? 'Expositor: ' + c.expositor : '', c.lugar].filter(Boolean).join(' · ');
      const vencida = c.estado === 'programada' && c.fecha < hoy;

      return '<tr data-id="' + c.id + '">' +
        '<td><div class="cap-tt" title="' + esc(c.titulo) + '">' + esc(c.titulo) + '</div>' +
            (sub ? '<div class="cap-sub" title="' + esc(sub) + '">' + esc(sub) + '</div>' : '') + '</td>' +
        '<td>' + fFecha(c.fecha) + '<div class="cap-sub' + (vencida ? ' venc' : '') + '">' +
            (vencida ? '⚠ Vencida · ' : '') + fHora(c.hora) + dur + '</div></td>' +
        '<td style="text-align:center"><span class="cap-cnt' + (nTemas ? '' : ' warn') + '">' + nTemas + '</span></td>' +
        '<td style="text-align:center"><span class="cap-cnt">' + nAdj + '</span></td>' +
        '<td>' + barra + '</td>' +
        '<td>' + chipEstado(c.estado) + '</td>' +
        '<td><span class="cap-pill" title="' + esc(c.coordinador) + '"><span class="cap-pill-av">' + esc(iniciales(c.coordinador)) + '</span>' +
            '<span class="cap-pill-tx">' + esc(c.coordinador) + '</span></span></td>' +
        '<td><div class="cap-act">' +
          '<button data-act="abrir"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>Abrir</button>' +
          (puedeEliminar(c) ? '<button class="danger" data-act="borrar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>Eliminar</button>' : '') +
        '</div></td></tr>';
    }).join('');
  }
  renderKpis();
}

function renderKpis() {
  const mesActual = new Date().toISOString().slice(0, 7);
  const prog = CAPS.filter(c => c.estado === 'programada');
  const hoy  = new Date().toISOString().slice(0, 10);
  const vencidas = prog.filter(c => c.fecha < hoy).length;

  $('#kpiProg').textContent = prog.length;
  $('#kpiProgFoot').textContent = vencidas ? vencidas + ' vencida' + (vencidas === 1 ? '' : 's') + ' sin enviar' : '';
  $('#kpiVal').textContent  = CAPS.filter(c => c.estado === 'por_validar').length;

  const realMes = CAPS.filter(c => c.estado === 'realizada' && String(c.fecha).slice(0, 7) === mesActual);
  $('#kpiReal').textContent = realMes.length;
  $('#kpiRealFoot').textContent = 'mes en curso';
  $('#kpiNo').textContent   = CAPS.filter(c => c.estado === 'no_realizada').length;

  // Asistencia media: solo sobre las realizadas, que son las únicas donde el
  // dato está validado. Incluir las programadas mezclaría cifras a medio marcar.
  const reales = CAPS.filter(c => c.estado === 'realizada' && c.plantilla > 0);
  if (!reales.length) {
    $('#kpiAsis').textContent = '—';
    $('#kpiAsisFoot').textContent = 'sin capacitaciones validadas';
  } else {
    const suma = reales.reduce((a, c) => a + ((c.asistieron + c.tardanzas) / c.plantilla), 0);
    $('#kpiAsis').innerHTML = Math.round(suma * 100 / reales.length) + '<span style="font-size:17px">%</span>';
    $('#kpiAsisFoot').textContent = 'sobre ' + reales.length + ' capacitación' + (reales.length === 1 ? '' : 'es') + ' validada' + (reales.length === 1 ? '' : 's');
  }
}

/* ── Modal de creación / edición de cabecera ────────────────────────── */
function abrirModal(id)  { $('#' + id).classList.add('open'); }
function cerrarModal(id) { $('#' + id).classList.remove('open'); }

$('#btnNew').addEventListener('click', () => {
  $('#fId').value = '0';
  $('#fTitulo').value = ''; $('#fDuracion').value = '';
  $('#fLugar').value = '';  setExpositor('#fExpositor', '#fExpositorOtro', '');
  const ahora = new Date();
  $('#fFecha').value = ahora.toISOString().slice(0, 10);
  $('#fHora').value  = String(ahora.getHours()).padStart(2, '0') + ':00';
  $('#newTitle').textContent = 'Nueva capacitación';
  $('#btnSaveNew').textContent = 'Crear capacitación';
  abrirModal('capBackNew');
  $('#fTitulo').focus();
});

$('#btnSaveNew').addEventListener('click', async () => {
  const payload = {
    id:           parseInt($('#fId').value, 10) || 0,
    titulo:       $('#fTitulo').value.trim(),
    fecha:        $('#fFecha').value,
    hora:         $('#fHora').value,
    duracion_min: $('#fDuracion').value,
    lugar:        $('#fLugar').value.trim(),
    expositor:    getExpositor('#fExpositor', '#fExpositorOtro')
  };
  if (!payload.titulo) { toast('Indica el título de la capacitación.', 'err'); return; }
  if (!payload.fecha || !payload.hora) { toast('Indica la fecha y la hora.', 'err'); return; }

  const btn = $('#btnSaveNew'); btn.disabled = true;
  try {
    const r = await fetch('../api/save_capacitacion.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
    });
    const j = await r.json();
    if (!j.success) { toast(j.error, 'err'); return; }
    cerrarModal('capBackNew');
    toast('Capacitación creada. Entra para cargar temas y asistencia.', 'ok');
    await cargar();
    abrirDetalle(j.id);
  } catch (e) { toast('Error de red al guardar.', 'err'); }
  finally { btn.disabled = false; }
});

/* ── Tabla · acciones ───────────────────────────────────────────────── */
$('#capTbody').addEventListener('click', async ev => {
  const tr = ev.target.closest('tr[data-id]'); if (!tr) return;
  const id = parseInt(tr.dataset.id, 10);
  const act = ev.target.closest('button')?.dataset.act;

  if (act === 'borrar') {
    ev.stopPropagation();
    const c = CAPS.find(x => x.id === id);
    if (!confirm('¿Eliminar «' + c.titulo + '»?\n\nSe borrarán también sus temas, la asistencia marcada y los adjuntos.')) return;
    try {
      const r = await fetch('../api/delete_capacitacion.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id})
      });
      const j = await r.json();
      if (!j.success) { toast(j.error, 'err'); return; }
      toast('Capacitación eliminada.', 'ok');
      cargar();
    } catch (e) { toast('Error de red al eliminar.', 'err'); }
    return;
  }
  abrirDetalle(id);
});

['#capSearch'].forEach(s => $(s).addEventListener('input', render));
['#capFilterCoord', '#capFilterMes'].forEach(s => $(s).addEventListener('change', function () {
  this.classList.toggle('on', !!this.value); render();
}));
$('#capFilterEstado').addEventListener('click', ev => {
  const b = ev.target.closest('button'); if (!b) return;
  $$('#capFilterEstado button').forEach(x => x.classList.remove('active'));
  b.classList.add('active'); render();
});

/* ═══════════ DETALLE ═══════════ */
async function abrirDetalle(id) {
  DET = CAPS.find(c => c.id === id);
  if (!DET) { toast('No se encontró la capacitación.', 'err'); return; }

  // Estado limpio: si no se reinicia, el detalle hereda la plantilla, las
  // marcas y los filtros de la capacitación abierta antes, y los contadores
  // mienten hasta que termina la petición de la plantilla nueva.
  VEREDICTO = null;
  PLANTILLA = [];
  MARCAS    = {};
  TEMAS = (DET.temas || []).map(t => ({titulo: t.titulo, descripcion: t.descripcion || ''}));
  ['#aSearch', '#aCuadrilla', '#aCoord'].forEach(s => {
    $(s).value = ''; $(s).classList.remove('on');
  });
  $('#vComentario').value = DET.comentario_admin || '';

  $('#detTitulo').textContent = DET.titulo;
  $('#detSub').textContent = [fFecha(DET.fecha), fHora(DET.hora),
      DET.duracion_min ? DET.duracion_min + ' min' : '', DET.lugar,
      DET.expositor ? 'Expositor: ' + DET.expositor : ''].filter(Boolean).join(' · ');
  $('#detEstado').innerHTML = chipEstado(DET.estado);

  const editable = puedeEditar(DET);
  $('#dTitulo').value    = DET.titulo;
  $('#dFecha').value     = DET.fecha;
  $('#dHora').value      = fHora(DET.hora);
  $('#dDuracion').value  = DET.duracion_min || '';
  $('#dLugar').value     = DET.lugar || '';
  setExpositor('#dExpositor', '#dExpositorOtro', DET.expositor || '');
  $('#dObs').value       = DET.observaciones || '';
  ['#dTitulo','#dFecha','#dHora','#dDuracion','#dLugar','#dExpositor','#dExpositorOtro','#dObs']
    .forEach(s => $(s).disabled = !editable);
  $('#btnAddTema').style.display = editable ? '' : 'none';
  $('#dDrop').style.display      = editable ? '' : 'none';

  renderTemas();
  renderAdjuntos();
  renderValidacion();
  renderAcciones();
  cambiarTab('cont');
  abrirModal('capBackDet');

  await cargarPlantilla(id);
}

/* ── Temas (buffer en memoria hasta pulsar Guardar) ─────────────────── */
function renderTemas() {
  const editable = puedeEditar(DET);
  const cont = $('#dTemas');
  if (!TEMAS.length) {
    cont.innerHTML = '<div style="font-size:12.5px;color:#9ca3af;padding:4px 2px">' +
      (editable ? 'Sin temas todavía. Agrega al menos uno para poder enviar a validación.' : 'Sin temas registrados.') +
      '</div>';
  } else {
    cont.innerHTML = TEMAS.map((t, i) =>
      '<div class="cap-tema" data-i="' + i + '">' +
        '<span class="num">' + (i + 1) + '</span>' +
        '<div class="cols">' +
          '<input class="ttl" data-k="titulo" maxlength="200" placeholder="Título del tema" value="' + esc(t.titulo) + '"' + (editable ? '' : ' disabled') + '>' +
          '<textarea class="dsc" data-k="descripcion" rows="1" placeholder="Descripción (opcional)"' + (editable ? '' : ' disabled') + '>' + esc(t.descripcion) + '</textarea>' +
        '</div>' +
        (editable ? '<button class="del" title="Quitar tema">&times;</button>' : '') +
      '</div>').join('');
  }
  $('#tabContBadge').textContent = TEMAS.length;
  renderAcciones();   // el botón «Enviar» depende de que haya ≥1 tema
}

$('#btnAddTema').addEventListener('click', () => {
  TEMAS.push({titulo: '', descripcion: ''});
  renderTemas();
  const inputs = $$('#dTemas .ttl');
  if (inputs.length) inputs[inputs.length - 1].focus();
});

$('#dTemas').addEventListener('input', ev => {
  const fila = ev.target.closest('.cap-tema'); if (!fila) return;
  const i = parseInt(fila.dataset.i, 10);
  const k = ev.target.dataset.k;
  if (TEMAS[i] && k) TEMAS[i][k] = ev.target.value;
  // Sin esto, escribir el primer tema no habilita «Enviar a validación»
  // hasta que algo más redibuje el pie.
  if (k === 'titulo') renderAcciones();
});
$('#dTemas').addEventListener('click', ev => {
  const btn = ev.target.closest('.del'); if (!btn) return;
  const i = parseInt(btn.closest('.cap-tema').dataset.i, 10);
  TEMAS.splice(i, 1);
  renderTemas();
});

/* ── Adjuntos (subida inmediata contra Drive) ───────────────────────── */
function iconoExt(nombre) {
  const e = String(nombre).split('.').pop().toLowerCase();
  const col = {pdf:'#dc2626', doc:'#2563eb', docx:'#2563eb', xls:'#16a34a', xlsx:'#16a34a',
               ppt:'#ea580c', pptx:'#ea580c', jpg:'#7c3aed', jpeg:'#7c3aed', png:'#7c3aed',
               webp:'#7c3aed', gif:'#7c3aed', heic:'#7c3aed', mp4:'#0891b2', mov:'#0891b2', webm:'#0891b2'};
  return {txt: e.slice(0, 4).toUpperCase(), color: col[e] || '#64748b'};
}

function renderAdjuntos() {
  const editable = puedeEditar(DET);
  const adj = DET.adjuntos || [];
  $('#dFiles').innerHTML = adj.length ? adj.map(a => {
    const ic = iconoExt(a.nombre_archivo);
    const badge = a.estado === 'subido'    ? '<span class="drv">En Drive</span>'
                : a.estado === 'pendiente' ? '<span class="drv pend">Solo local</span>'
                :                            '<span class="drv err">Error</span>';
    const nombre = a.drive_url
      ? '<a href="' + esc(a.drive_url) + '" target="_blank" rel="noopener">' + esc(a.nombre_archivo) + '</a>'
      : esc(a.nombre_archivo);
    return '<div class="cap-file" data-adj="' + a.id + '">' +
      '<span class="ic" style="background:' + ic.color + '">' + ic.txt + '</span>' +
      '<span class="nm">' + nombre + '</span>' +
      '<span class="mt">' + fPeso(a.peso_bytes) + '</span>' + badge +
      (editable ? '<button class="x" title="Quitar">&times;</button>' : '') +
      '</div>';
  }).join('') : '<div style="font-size:12.5px;color:#9ca3af;padding:2px">Sin material adjunto.</div>';
}

$('#dDrop').addEventListener('click', () => $('#dFileInput').click());
$('#dDrop').addEventListener('dragover', ev => { ev.preventDefault(); $('#dDrop').classList.add('hot'); });
$('#dDrop').addEventListener('dragleave', () => $('#dDrop').classList.remove('hot'));
$('#dDrop').addEventListener('drop', ev => {
  ev.preventDefault(); $('#dDrop').classList.remove('hot');
  subirArchivos(ev.dataTransfer.files);
});
$('#dFileInput').addEventListener('change', ev => { subirArchivos(ev.target.files); ev.target.value = ''; });

async function subirArchivos(files) {
  if (!DET || !puedeEditar(DET)) return;
  for (const f of Array.from(files || [])) {
    if (f.size > MAX_BYTES) { toast('«' + f.name + '» supera los 4 MB.', 'err'); continue; }
    const fd = new FormData();
    fd.append('id', DET.id);
    fd.append('file', f);
    toast('Subiendo ' + f.name + '…');
    try {
      const r = await fetch('../api/upload_capacitacion_file.php', {method: 'POST', body: fd});
      const j = await r.json();
      if (!j.success) { toast(j.error, 'err'); continue; }
      DET.adjuntos = DET.adjuntos || [];
      DET.adjuntos.push(j.adjunto);
      renderAdjuntos(); renderValidacion();
      if (j.aviso) toast(j.aviso, 'err'); else toast('«' + j.adjunto.nombre_archivo + '» subido.', 'ok');
    } catch (e) { toast('Error de red al subir ' + f.name + '.', 'err'); }
  }
}

$('#dFiles').addEventListener('click', async ev => {
  const btn = ev.target.closest('.x'); if (!btn) return;
  const id = parseInt(btn.closest('.cap-file').dataset.adj, 10);
  if (!confirm('¿Quitar este archivo de la capacitación?')) return;
  try {
    const r = await fetch('../api/delete_capacitacion_adjunto.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id})
    });
    const j = await r.json();
    if (!j.success) { toast(j.error, 'err'); return; }
    DET.adjuntos = (DET.adjuntos || []).filter(a => a.id !== id);
    renderAdjuntos(); renderValidacion();
    toast(j.aviso || 'Archivo quitado.', 'ok');
  } catch (e) { toast('Error de red.', 'err'); }
});

/* ── Asistencia ─────────────────────────────────────────────────────── */
async function cargarPlantilla(id) {
  $('#aRoster').innerHTML = '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px">Cargando plantilla…</div>';
  try {
    const r = await fetch('../api/get_capacitacion_plantilla.php?id=' + id);
    const j = await r.json();
    if (!j.success) { toast(j.error, 'err'); return; }
    PLANTILLA = j.data || [];
    MARCAS = {};
    PLANTILLA.forEach(p => { if (p.marca) MARCAS[p.id] = p.marca; });
    llenarFiltrosRoster();
    renderRoster();   // arrastra renderContadores() y renderAcciones()
  } catch (e) { toast('Error de red al cargar la plantilla.', 'err'); }
}

function llenarFiltrosRoster() {
  const cuad = [...new Set(PLANTILLA.map(p => p.cuadrilla).filter(Boolean))].sort();
  $('#aCuadrilla').innerHTML = '<option value="">Todas las cuadrillas</option>' +
    cuad.map(c => '<option value="' + esc(c) + '">Cuadrilla ' + esc(c) + '</option>').join('');
  const coords = [];
  PLANTILLA.forEach(p => {
    if (p.coordinador_id && !coords.some(c => c.id === p.coordinador_id))
      coords.push({id: p.coordinador_id, nombre: p.coordinador_nombre});
  });
  $('#aCoord').innerHTML = '<option value="">Todos los coordinadores</option>' +
    coords.map(c => '<option value="' + c.id + '">' + esc(c.nombre) + '</option>').join('');
}

function rosterVisible() {
  const q    = ($('#aSearch').value || '').trim().toLowerCase();
  const cuad = $('#aCuadrilla').value;
  const coor = $('#aCoord').value;
  return PLANTILLA.filter(p => {
    if (cuad && p.cuadrilla !== cuad) return false;
    if (coor && String(p.coordinador_id) !== coor) return false;
    if (q && !((p.nombre + ' ' + (p.codigo || '') + ' ' + (p.cargo || '')).toLowerCase().includes(q))) return false;
    return true;
  });
}

function renderRoster() {
  const editable = puedeEditar(DET);
  const lista = rosterVisible();
  const cont = $('#aRoster');

  if (!lista.length) {
    cont.innerHTML = '<div style="padding:22px;text-align:center;color:#9ca3af;font-size:13px">Ningún colaborador coincide.</div>';
  } else {
    let html = '', grupo = null;
    lista.forEach(p => {
      const g = p.historico ? 'Ya no activos' : ('Cuadrilla ' + (p.cuadrilla || '—'));
      if (g !== grupo) {
        grupo = g;
        const n = lista.filter(x => (x.historico ? 'Ya no activos' : ('Cuadrilla ' + (x.cuadrilla || '—'))) === g).length;
        html += '<div class="cap-rgrp">' + esc(g) + ' · ' + n + '</div>';
      }
      const marca = MARCAS[p.id] || null;
      html += '<div class="cap-rrow' + (marca ? '' : ' sinmarcar') + (p.historico ? ' historico' : '') + '" data-c="' + p.id + '">' +
        '<span class="cap-av">' + esc(iniciales(p.nombre)) + '</span>' +
        '<div class="who"><div class="nm">' + esc(p.nombre) + '</div>' +
        '<div class="mt">' + esc(p.cargo || '—') + (p.codigo ? ' · ' + esc(p.codigo) : '') + '</div></div>' +
        '<div class="cap-aft">' +
          Object.keys(ASISTENCIA).map(k =>
            '<button data-v="' + k + '"' + (marca === k ? ' class="on"' : '') +
            (editable ? '' : ' disabled') + ' title="' + esc(ASISTENCIA[k].label) + '">' +
            ASISTENCIA[k].abrev + '</button>').join('') +
        '</div></div>';
    });
    cont.innerHTML = html;
  }
  renderContadores();
  renderAcciones();   // marcar/desmarcar cambia si se puede enviar a validación
}

function renderContadores() {
  // El denominador son los ACTIVOS. Los históricos (dados de baja tras marcar)
  // se muestran pero no cuentan como plantilla pendiente.
  const activos = PLANTILLA.filter(p => !p.historico);
  let a = 0, t = 0, f = 0;
  activos.forEach(p => {
    const m = MARCAS[p.id];
    if (m === 'asistio') a++; else if (m === 'tardanza') t++; else if (m === 'falta') f++;
  });
  const sin = activos.length - (a + t + f);
  $('#mAsis').textContent = a; $('#mTard').textContent = t;
  $('#mFalt').textContent = f; $('#mSin').textContent  = sin;

  const badge = $('#tabAsisBadge');
  badge.textContent = (a + t + f) + '/' + activos.length;
  badge.classList.toggle('warn', sin > 0);
  return {a, t, f, sin, total: activos.length};
}

$('#aRoster').addEventListener('click', ev => {
  const btn = ev.target.closest('button[data-v]'); if (!btn || btn.disabled) return;
  const cid = parseInt(btn.closest('.cap-rrow').dataset.c, 10);
  const val = btn.dataset.v;
  if (MARCAS[cid] === val) delete MARCAS[cid];   // volver a pulsar desmarca
  else MARCAS[cid] = val;
  renderRoster();
});

['#aSearch'].forEach(s => $(s).addEventListener('input', renderRoster));
['#aCuadrilla', '#aCoord'].forEach(s => $(s).addEventListener('change', function () {
  this.classList.toggle('on', !!this.value); renderRoster();
}));

$('#aMass').addEventListener('click', ev => {
  const b = ev.target.closest('button'); if (!b || !puedeEditar(DET)) return;
  const visibles = rosterVisible().filter(p => !p.historico);
  if (b.dataset.m === 'limpiar') visibles.forEach(p => delete MARCAS[p.id]);
  else visibles.forEach(p => MARCAS[p.id] = 'asistio');
  renderRoster();
});

/* ── Validación ─────────────────────────────────────────────────────── */
function renderValidacion() {
  const c = DET;
  const temas = TEMAS.filter(t => (t.titulo || '').trim());
  $('#vTemas').innerHTML = temas.length
    ? '<ol>' + temas.map(t => '<li>' + esc(t.titulo) +
        (t.descripcion ? '<span>' + esc(t.descripcion) + '</span>' : '') + '</li>').join('') + '</ol>'
    : '<div class="vacio">El coordinador no registró temas.</div>';

  $('#vAsis').textContent = c.asistieron || 0;
  $('#vTard').textContent = c.tardanzas  || 0;
  $('#vFalt').textContent = c.faltas     || 0;
  $('#vAdj').textContent  = (c.adjuntos || []).length;

  const puedeValidar = ES_ADMIN && c.estado === 'por_validar';
  $('#vFormWrap').style.display = (c.estado === 'por_validar') ? '' : 'none';
  $$('#vFormWrap .cap-vopt').forEach(b => {
    b.disabled = !puedeValidar;
    b.classList.toggle('on', VEREDICTO === b.dataset.v);
  });
  $('#vComentario').disabled = !puedeValidar;

  // Aviso contextual: cada rol necesita saber qué se espera de él aquí.
  const svgInfo = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
  const svgClock = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
  const svgCheck = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
  let aviso = '';
  if (c.estado === 'programada') {
    aviso = '<div class="cap-note info">' + svgInfo + '<span>Todavía no hay nada que validar. El coordinador debe enviarla primero.</span></div>';
  } else if (c.estado === 'por_validar' && !ES_ADMIN) {
    aviso = '<div class="cap-note">' + svgClock + '<span>Enviada el ' + fStamp(c.enviado_at) + '. Esperando la validación del administrador.</span></div>';
  } else if (c.estado === 'realizada' || c.estado === 'no_realizada') {
    const e = ESTADOS[c.estado];
    aviso = '<div class="cap-note" style="background:' + e.bg + ';border-color:' + e.color +
            ';color:' + e.color + '">' + svgCheck + '<span><b>' + esc(e.label) + '</b> · validada por ' +
            esc(c.validado_por || '—') + ' el ' + fStamp(c.validado_at) + '</span></div>';
  }
  $('#vAviso').innerHTML = aviso;

  // Historial del ciclo.
  const eventos = [];
  eventos.push({who: c.coordinador, rol: 'Coordinador', when: c.created_at, txt: 'Programó la capacitación.'});
  if (c.enviado_at)  eventos.push({who: c.coordinador, rol: 'Coordinador', when: c.enviado_at, txt: 'Envió la capacitación a validación.'});
  if (c.validado_at) eventos.push({who: c.validado_por, rol: 'Administrador', when: c.validado_at,
      txt: 'Validó como «' + (ESTADOS[c.estado]?.label || c.estado) + '».' + (c.comentario_admin ? '\n\n' + c.comentario_admin : '')});

  $('#vHistorial').innerHTML = eventos.map(e =>
    '<div class="cap-cmt"><span class="cap-av">' + esc(iniciales(e.who)) + '</span>' +
    '<div class="cb"><div class="ch">' + esc(e.who || '—') + ' · ' + esc(e.rol) +
    ' <span>' + fStamp(e.when) + '</span></div>' +
    '<div class="cx">' + esc(e.txt) + '</div></div></div>').join('');

  $('#tabVali').disabled = (c.estado === 'programada');
}

$('#vFormWrap').addEventListener('click', ev => {
  const b = ev.target.closest('.cap-vopt'); if (!b || b.disabled) return;
  VEREDICTO = (VEREDICTO === b.dataset.v) ? null : b.dataset.v;
  $$('#vFormWrap .cap-vopt').forEach(x => x.classList.toggle('on', VEREDICTO === x.dataset.v));
  renderAcciones();
});

/* ── Pestañas ───────────────────────────────────────────────────────── */
function cambiarTab(tab) {
  $$('.cap-tab').forEach(t => t.classList.toggle('on', t.dataset.tab === tab));
  $$('.cap-pane').forEach(p => p.classList.toggle('on', p.dataset.pane === tab));
}
$$('.cap-tab').forEach(t => t.addEventListener('click', () => { if (!t.disabled) cambiarTab(t.dataset.tab); }));

/* ── Acciones del pie del detalle ───────────────────────────────────── */
function renderAcciones() {
  const c = DET, cont = $('#detActions'), nota = $('#detFootNote');
  let html = '', txt = '';

  if (puedeEditar(c)) {
    const cnt = PLANTILLA.length ? renderContadores() : {sin: null};
    const temasOk = TEMAS.filter(t => (t.titulo || '').trim()).length > 0;
    const listo   = temasOk && cnt.sin === 0;
    txt = !temasOk ? 'Agrega al menos un tema para poder enviar.'
        : cnt.sin ? 'Faltan ' + cnt.sin + ' colaborador' + (cnt.sin === 1 ? '' : 'es') + ' por marcar.'
        : 'Listo para enviar a validación.';
    html = '<button class="cap-btn ghost" id="btnGuardar">Guardar cambios</button>' +
           '<button class="cap-btn primary" id="btnEnviar"' + (listo ? '' : ' disabled') + '>Enviar a validación</button>';
  } else if (ES_ADMIN && c.estado === 'por_validar') {
    txt = 'Al validar, el coordinador ya no podrá editar.';
    html = '<button class="cap-btn ghost" data-close="capBackDet">Cancelar</button>' +
           '<button class="cap-btn primary" id="btnValidar"' + (VEREDICTO ? '' : ' disabled') + '>Validar capacitación</button>';
  } else {
    txt = c.estado === 'por_validar' ? 'En validación · solo lectura'
        : (c.estado === 'programada' ? 'Solo su coordinador puede editarla' : 'Capacitación cerrada · solo lectura');
    html = '<button class="cap-btn ghost" data-close="capBackDet">Cerrar</button>';
  }

  nota.textContent = txt;
  cont.innerHTML = html;

  const bg = $('#btnGuardar'); if (bg) bg.addEventListener('click', () => guardarDetalle(false));
  const be = $('#btnEnviar');  if (be) be.addEventListener('click', enviarAValidacion);
  const bv = $('#btnValidar'); if (bv) bv.addEventListener('click', validar);
}

/** Persiste cabecera + temas + asistencia. Devuelve true si todo fue bien. */
async function guardarDetalle(silencioso) {
  if (!puedeEditar(DET)) return false;
  const payload = {
    id:            DET.id,
    titulo:        $('#dTitulo').value.trim(),
    fecha:         $('#dFecha').value,
    hora:          $('#dHora').value,
    duracion_min:  $('#dDuracion').value,
    lugar:         $('#dLugar').value.trim(),
    expositor:     getExpositor('#dExpositor', '#dExpositorOtro'),
    observaciones: $('#dObs').value.trim(),
    temas:         TEMAS.filter(t => (t.titulo || '').trim())
                        .map(t => ({titulo: t.titulo.trim(), descripcion: (t.descripcion || '').trim()}))
  };
  if (!payload.titulo) { toast('El título no puede quedar vacío.', 'err'); return false; }
  if (!payload.fecha || !payload.hora) { toast('Indica la fecha y la hora.', 'err'); return false; }

  try {
    const r1 = await fetch('../api/save_capacitacion.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
    });
    const j1 = await r1.json();
    if (!j1.success) { toast(j1.error, 'err'); return false; }

    const marcas = Object.keys(MARCAS).map(cid => ({colaborador_id: parseInt(cid, 10), estado: MARCAS[cid]}));
    const r2 = await fetch('../api/save_capacitacion_asistencia.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({id: DET.id, marcas})
    });
    const j2 = await r2.json();
    if (!j2.success) { toast(j2.error, 'err'); return false; }

    if (!silencioso) toast('Cambios guardados.', 'ok');
    return true;
  } catch (e) { toast('Error de red al guardar.', 'err'); return false; }
}

async function enviarAValidacion() {
  if (!confirm('¿Enviar «' + DET.titulo + '» a validación?\n\nDespués de enviarla ya no podrás editar el contenido ni la asistencia.')) return;
  const btn = $('#btnEnviar'); btn.disabled = true;
  // Se guarda antes de enviar: el servidor valida contra lo persistido, no
  // contra lo que hay en pantalla.
  if (!await guardarDetalle(true)) { btn.disabled = false; return; }
  try {
    const r = await fetch('../api/enviar_capacitacion.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: DET.id})
    });
    const j = await r.json();
    if (!j.success) { toast(j.error, 'err'); btn.disabled = false; return; }
    toast('Enviada a validación.', 'ok');
    cerrarModal('capBackDet');
    cargar();
  } catch (e) { toast('Error de red al enviar.', 'err'); btn.disabled = false; }
}

async function validar() {
  const comentario = $('#vComentario').value.trim();
  if (!VEREDICTO) { toast('Indica si se realizó o no.', 'err'); return; }
  if (VEREDICTO === 'no_realizada' && !comentario) {
    toast('Explica en el comentario por qué no se realizó.', 'err');
    $('#vComentario').focus(); return;
  }
  const btn = $('#btnValidar'); btn.disabled = true;
  try {
    const r = await fetch('../api/validar_capacitacion.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({id: DET.id, resultado: VEREDICTO, comentario})
    });
    const j = await r.json();
    if (!j.success) { toast(j.error, 'err'); btn.disabled = false; return; }
    toast('Capacitación validada como «' + (ESTADOS[VEREDICTO]?.label || VEREDICTO) + '».', 'ok');
    cerrarModal('capBackDet');
    cargar();
  } catch (e) { toast('Error de red al validar.', 'err'); btn.disabled = false; }
}

/* ── Cierre de modales ──────────────────────────────────────────────── */
document.addEventListener('click', ev => {
  const b = ev.target.closest('[data-close]'); if (b) cerrarModal(b.dataset.close);
});
$$('.cap-modal-back').forEach(back => back.addEventListener('click', ev => {
  if (ev.target === back) back.classList.remove('open');
}));
document.addEventListener('keydown', ev => {
  if (ev.key === 'Escape') $$('.cap-modal-back.open').forEach(m => m.classList.remove('open'));
});

/* ── Exportaciones · siempre sobre listaVisible() ───────────────────── */
function descripcionFiltro() {
  const partes = [];
  const est = $('#capFilterEstado').querySelector('button.active');
  if (est.dataset.e !== 'todos') partes.push('Estado: ' + est.textContent.trim());
  const co = $('#capFilterCoord');
  if (co.value) partes.push('Coordinador: ' + co.options[co.selectedIndex].text);
  const me = $('#capFilterMes');
  if (me.value) partes.push(me.options[me.selectedIndex].text);
  const q = $('#capSearch').value.trim();
  if (q) partes.push('Búsqueda: "' + q + '"');
  return partes.length ? partes.join(' · ') : 'Todas las capacitaciones';
}

$('#btnExcel').addEventListener('click', () => {
  const lista = listaVisible();
  if (!lista.length) { toast('No hay filas que exportar.', 'err'); return; }
  const cab = ['Fecha','Hora','Título','Expositor','Lugar','Duración (min)','Temas',
               'Adjuntos','Asistieron','Tardanza','Faltaron','Sin marcar','Plantilla',
               'Estado','Coordinador','Validado por','Comentario del administrador'];
  const filas = lista.map(c => {
    const sin = Math.max(0, (c.plantilla || 0) - c.marcados);
    return [fFecha(c.fecha), fHora(c.hora), c.titulo, c.expositor || '', c.lugar || '',
            c.duracion_min || '', (c.temas || []).map(t => t.titulo).join(' | '),
            (c.adjuntos || []).length, c.asistieron, c.tardanzas, c.faltas, sin, c.plantilla || 0,
            ESTADOS[c.estado]?.label || c.estado, c.coordinador,
            c.validado_por || '', (c.comentario_admin || '').replace(/\s+/g, ' ')];
  });
  const csv = [cab, ...filas]
    .map(f => f.map(v => '"' + String(v ?? '').replace(/"/g, '""') + '"').join(';'))
    .join('\r\n');
  const blob = new Blob(['﻿' + csv], {type: 'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'capacitaciones_' + new Date().toISOString().slice(0, 10) + '.csv';
  a.click();
  URL.revokeObjectURL(a.href);
});

$('#btnPdf').addEventListener('click', () => {
  const lista = listaVisible();
  if (!lista.length) { toast('No hay filas que exportar.', 'err'); return; }
  const filas = lista.map(c => {
    const e = ESTADOS[c.estado] || {label: c.estado, color: '#000'};
    const sin = Math.max(0, (c.plantilla || 0) - c.marcados);
    return '<tr>' +
      '<td>' + fFecha(c.fecha) + '<br><small>' + fHora(c.hora) + '</small></td>' +
      '<td><b>' + esc(c.titulo) + '</b>' +
        ((c.temas || []).length ? '<br><small>' + esc((c.temas || []).map(t => t.titulo).join(' · ')) + '</small>' : '') + '</td>' +
      '<td>' + esc(c.expositor || '—') + '<br><small>' + esc(c.lugar || '') + '</small></td>' +
      '<td style="text-align:center">' + c.asistieron + ' / ' + c.tardanzas + ' / ' + c.faltas +
        (sin ? '<br><small>' + sin + ' sin marcar</small>' : '') + '</td>' +
      '<td style="color:' + e.color + ';font-weight:700">' + esc(e.label) + '</td>' +
      '<td>' + esc(c.coordinador) + '</td>' +
      '<td><small>' + esc(c.comentario_admin || '') + '</small></td></tr>';
  }).join('');

  const w = window.open('', '_blank');
  w.document.write(
    '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Capacitaciones</title><style>' +
    'body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:22px}' +
    'header{display:flex;align-items:center;gap:14px;border-bottom:2px solid #00875A;padding-bottom:10px;margin-bottom:6px}' +
    'header img{height:44px}h1{margin:0;font-size:17px}.sub{font-size:11px;color:#555;margin-top:2px}' +
    '.meta{font-size:11px;color:#555;margin:8px 0 12px}' +
    'table{width:100%;border-collapse:collapse;font-size:10.5px}' +
    'th{background:#00875A;color:#fff;text-align:left;padding:6px 7px;font-size:9.5px;text-transform:uppercase}' +
    'td{border-bottom:1px solid #e5e7eb;padding:6px 7px;vertical-align:top}' +
    'small{color:#666;font-size:9px}@media print{body{margin:10px}}' +
    '</style></head><body><header>' +
    (LOGO_B64 ? '<img src="' + LOGO_B64 + '">' : '') +
    '<div><h1>Registro de Capacitaciones</h1><div class="sub">Estiba Shift Command Deck</div></div></header>' +
    '<div class="meta">' + esc(descripcionFiltro()) + ' · ' + lista.length + ' registro' +
    (lista.length === 1 ? '' : 's') + ' · generado el ' + new Date().toLocaleString('es-PE') + '</div>' +
    '<table><thead><tr><th>Fecha</th><th>Capacitación y temas</th><th>Expositor / lugar</th>' +
    '<th style="text-align:center">A / T / F</th><th>Estado</th><th>Coordinador</th>' +
    '<th>Comentario admin.</th></tr></thead><tbody>' + filas + '</tbody></table></body></html>');
  w.document.close();
  w.focus();
  setTimeout(() => w.print(), 350);
});

cargar();
</script>
</body>
</html>
