<?php
require_once('../includes/auth.php');
require_once('../includes/db.php');
require_once('../includes/tareas_catalogo.php');
require_tareas();

$JS_ESTADOS     = tk_estados();
$JS_PRIORIDADES = tk_prioridades();
$JS_SEMAFOROS   = tk_semaforos();
$JS_ESCALA      = ed_escala();

$ES_ADMIN  = is_admin();
$ES_SUPER  = ($_SESSION['user_rol'] ?? '') === 'Supervisor';
$USER_ID   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$USER_NAME = $_SESSION['user_name'] ?? '';
$USER_ROL  = $_SESSION['user_rol']  ?? '';

$ES_TABLERO = $ES_ADMIN || $ES_SUPER;

$MIS_SOPORTES = [];
if ($USER_ROL === 'Coordinador' && $USER_ID > 0) {
    $st = mysqli_prepare($conn,
        "SELECT id, nombre FROM usuarios WHERE rol='Soporte' AND soporte_de_id=? ORDER BY nombre");
    mysqli_stmt_bind_param($st, 'i', $USER_ID);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    while ($s = mysqli_fetch_assoc($rs)) $MIS_SOPORTES[] = ['id' => (int)$s['id'], 'nombre' => $s['nombre']];
    mysqli_stmt_close($st);
}

$logo_path = __DIR__ . '/../logo/logo.png';
$LOGO_B64  = is_file($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tareas · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <link rel="stylesheet" href="../css/estiba.css">

  <style>
    .tk-wrap {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0,135,90,.18); --co-line-bold:rgba(0,135,90,.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      --ok:#047857; --ok-bg:rgba(4,120,87,.10);
      --wn:#d97706; --wn-bg:rgba(217,119,6,.10);
      --er:#dc2626; --er-bg:rgba(220,38,38,.10);
      --sl:#475569; --sl-bg:rgba(100,116,139,.12);
      --bl:#2563eb; --bl-bg:rgba(37,99,235,.10);
      --vi:#7c3aed; --vi-bg:rgba(124,58,237,.10);
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .tk-wrap *, .tk-wrap *::before, .tk-wrap *::after { box-sizing:border-box; }

    .tk-hero { background:linear-gradient(135deg,#005c3d 0%,#00875A 100%); color:#fff;
      border-radius:20px; padding:22px 28px; display:flex; align-items:center;
      justify-content:space-between; gap:18px; box-shadow:0 8px 32px rgba(0,135,90,.08); }
    .tk-hero h1 { margin:6px 0 4px; font-size:22px; font-weight:700; letter-spacing:-.01em; }
    .tk-hero p  { margin:0; color:rgba(255,255,255,.85); font-size:13px; max-width:640px; }
    .tk-hero .tag { display:inline-flex; align-items:center; padding:5px 11px; border-radius:999px;
      background:rgba(255,255,255,.15); font-size:11px; font-weight:700; letter-spacing:.06em; }

    .tk-btn { display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:10px;
      border:1px solid rgba(0,135,90,.3); background:#fff; cursor:pointer; text-decoration:none;
      font-family:inherit; font-size:13px; font-weight:600; color:#00875A; transition:all .15s; }
    .tk-btn svg { width:15px; height:15px; }
    .tk-btn:hover { background:rgba(0,135,90,.05); }
    .tk-btn:disabled { opacity:.5; cursor:not-allowed; }
    .tk-btn.ghost-light { background:rgba(255,255,255,.15); color:#fff; border-color:rgba(255,255,255,.25); }
    .tk-btn.primary { background:linear-gradient(135deg,#00875A 0%,#005c3d 100%); color:#fff;
      border:none; font-weight:700; box-shadow:0 4px 18px rgba(0,135,90,.2); }
    .tk-btn.primary:hover { filter:brightness(1.08); transform:translateY(-1px); }
    .tk-btn.danger { color:var(--er); border-color:rgba(220,38,38,.3); }
    .tk-btn.danger:hover { background:var(--er-bg); }

    .tk-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; }
    .tk-kpi { background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:14px 16px; }
    .tk-kpi .lbl { font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase;
      color:var(--co-faint); }
    .tk-kpi .val { font-size:26px; font-weight:800; line-height:1.1; margin-top:4px; }
    .tk-kpi.is-alert .val { color:var(--er); }
    .tk-kpi.is-warn  .val { color:var(--wn); }
    .tk-kpi.is-ok    .val { color:var(--ok); }
    .tk-kpi.is-info  .val { color:var(--bl); }

    .tk-card { background:#fff; border:1px solid var(--co-line); border-radius:16px; }
    .tk-tools { display:flex; flex-wrap:wrap; align-items:center; gap:10px; padding:14px 16px;
      border-bottom:1px solid var(--co-line); }
    .tk-search { flex:1 1 220px; min-width:180px; padding:9px 12px; border-radius:10px;
      border:1px solid var(--co-line-bold); font-family:inherit; font-size:13px; }
    .tk-sel { padding:9px 12px; border-radius:10px; border:1px solid var(--co-line-bold);
      font-family:inherit; font-size:13px; background:#fff; }
    .tk-chips { display:flex; flex-wrap:wrap; gap:6px; }
    .tk-chip { padding:6px 12px; border-radius:999px; border:1px solid var(--co-line-bold);
      background:#fff; cursor:pointer; font-family:inherit; font-size:12px; font-weight:600;
      color:var(--co-mute); }
    .tk-chip.on { background:var(--co-navy-700); color:#fff; border-color:var(--co-navy-700); }
    .tk-chip.is-late { color:var(--er); border-color:rgba(220,38,38,.35); }
    .tk-chip.is-late.on { background:var(--er); color:#fff; border-color:var(--er); }

    .tk-tablewrap { overflow-x:auto; }
    .tk-table { width:100%; border-collapse:collapse; font-size:13px; }
    .tk-table th { text-align:left; padding:11px 14px; font-size:11px; font-weight:700;
      letter-spacing:.05em; text-transform:uppercase; color:var(--co-faint);
      border-bottom:1px solid var(--co-line); white-space:nowrap; }
    .tk-table td { padding:12px 14px; border-bottom:1px solid rgba(0,0,0,.045); vertical-align:top; }
    .tk-table tbody tr { cursor:pointer; }
    .tk-table tr:hover td { background:rgba(0,135,90,.025); }
    .tk-table tr.is-late td { background:rgba(220,38,38,.035); }
    .tk-table tr.is-late:hover td { background:rgba(220,38,38,.06); }
    .tk-tt { font-weight:600; cursor:pointer; }
    .tk-tt:hover { color:var(--co-navy-700); text-decoration:underline; }
    .tk-sub { font-size:11px; color:var(--co-faint); margin-top:2px; }

    .tk-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 9px; border-radius:999px;
      font-size:11px; font-weight:700; white-space:nowrap; }
    .tk-badge .dot { width:6px; height:6px; border-radius:50%; background:currentColor; }
    .tk-rolchip { display:inline-block; padding:2px 7px; border-radius:6px; font-size:10px;
      font-weight:700; background:var(--sl-bg); color:var(--sl); }
    .tk-rolchip.is-sop { background:var(--vi-bg); color:var(--vi); }
    .tk-late { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:6px;
      font-size:10px; font-weight:800; letter-spacing:.04em; background:var(--er-bg); color:var(--er); }
    .tk-tacha { text-decoration:line-through; color:var(--co-faint); font-size:11px; }
    .tk-stars { color:var(--wn); letter-spacing:1px; }

    .tk-list { display:flex; flex-direction:column; gap:10px; }
    .tk-item { background:#fff; border:1px solid var(--co-line); border-left-width:4px;
      border-radius:14px; padding:14px 16px; display:flex; gap:14px; align-items:flex-start;
      cursor:pointer; transition:all .15s; }
    .tk-item:hover { box-shadow:0 4px 18px rgba(0,0,0,.06); transform:translateY(-1px); }
    .tk-item.sem-vencida  { border-left-color:var(--er); }
    .tk-item.sem-hoy      { border-left-color:var(--wn); }
    .tk-item.sem-proxima  { border-left-color:#b45309; }
    .tk-item.sem-a_tiempo { border-left-color:var(--co-line-bold); }
    .tk-item .grow { flex:1; min-width:0; }
    .tk-item h4 { margin:0 0 4px; font-size:14px; font-weight:700; }
    .tk-item p  { margin:0; font-size:12px; color:var(--co-mute);
      display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }

    .tk-empty { text-align:center; padding:44px 20px; color:var(--co-faint); font-size:13px; }
    .tk-seg { display:flex; gap:6px; margin-bottom:4px; }
    .tk-seg button { padding:8px 16px; border-radius:10px; border:1px solid var(--co-line-bold);
      background:#fff; cursor:pointer; font-family:inherit; font-size:13px; font-weight:600;
      color:var(--co-mute); }
    .tk-seg button.on { background:var(--co-navy-700); color:#fff; border-color:var(--co-navy-700); }

    .tk-toast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(20px);
      background:#111827; color:#fff; padding:12px 20px; border-radius:12px; font-size:13px;
      font-weight:600; opacity:0; pointer-events:none; transition:all .25s; z-index:9999; }
    .tk-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
    .tk-toast.is-error { background:var(--er); }

    .tk-mb { position:fixed; inset:0; background:rgba(17,24,39,.55); backdrop-filter:blur(3px);
      display:none; align-items:center; justify-content:center; z-index:900; padding:20px; }
    .tk-mb.open { display:flex; }
    .tk-modal { background:#fff; border-radius:20px; width:100%; max-width:620px;
      max-height:92vh; display:flex; flex-direction:column; box-shadow:0 24px 64px rgba(0,0,0,.28); }
    .tk-modal.wide { max-width:860px; }
    .tk-mh { display:flex; align-items:flex-start; justify-content:space-between; gap:14px;
      padding:20px 24px; border-bottom:1px solid var(--co-line); }
    .tk-mh h3 { margin:0; font-size:17px; font-weight:700; }
    .tk-mh .sub { font-size:12px; color:var(--co-faint); margin-top:3px; }
    .tk-mx { background:none; border:none; cursor:pointer; color:var(--co-faint); padding:4px; }
    .tk-mbody { padding:20px 24px; overflow-y:auto; display:flex; flex-direction:column; gap:14px; }
    .tk-mf { display:flex; justify-content:flex-end; gap:10px; padding:16px 24px;
      border-top:1px solid var(--co-line); }
    .tk-field { display:flex; flex-direction:column; gap:5px; }
    .tk-field label { font-size:12px; font-weight:700; color:var(--co-mute); }
    .tk-field input, .tk-field select, .tk-field textarea {
      padding:10px 12px; border-radius:10px; border:1px solid var(--co-line-bold);
      font-family:inherit; font-size:13px; background:#fff; width:100%; }
    .tk-field textarea { min-height:84px; resize:vertical; }
    .tk-field .hint { font-size:11px; color:var(--co-faint); }
    .tk-row2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    .tk-dest { border:1px solid var(--co-line-bold); border-radius:12px; max-height:220px;
      overflow-y:auto; }
    .tk-dest .grp { padding:7px 12px; font-size:10px; font-weight:800; letter-spacing:.06em;
      text-transform:uppercase; color:var(--co-faint); background:var(--co-deck);
      position:sticky; top:0; }
    .tk-dest label { display:flex; align-items:center; gap:9px; padding:8px 12px; cursor:pointer;
      font-size:13px; border-top:1px solid rgba(0,0,0,.04); }
    .tk-dest label:hover { background:rgba(0,135,90,.04); }
    .tk-dest input { width:16px; height:16px; accent-color:var(--co-navy-700); }
    .tk-dest .who { font-size:11px; color:var(--co-faint); margin-left:auto; }

    .tk-sec { border:1px solid var(--co-line); border-radius:14px; padding:16px 18px;
      background:#fff; box-shadow:0 1px 2px rgba(16,24,40,.03); }
    .tk-sec h5 { margin:0 0 12px; font-size:11px; font-weight:800; letter-spacing:.06em;
      text-transform:uppercase; color:var(--co-faint); display:flex; align-items:center; gap:7px; }
    .tk-sec h5 svg { width:14px; height:14px; color:var(--co-navy-700); flex-shrink:0; }
    .tk-sec + .tk-sec { margin-top:2px; }
    .tk-obs { border:1px solid rgba(217,119,6,.35); background:linear-gradient(135deg,var(--wn-bg),rgba(217,119,6,.03));
      border-radius:14px; padding:16px 18px; box-shadow:0 1px 2px rgba(16,24,40,.03); }
    .tk-obs h5 { margin:0 0 8px; font-size:11px; font-weight:800; letter-spacing:.06em;
      text-transform:uppercase; color:var(--wn); display:flex; align-items:center; gap:7px; }
    .tk-obs h5 svg { width:14px; height:14px; flex-shrink:0; }
    .tk-obs p { margin:0; font-size:13px; white-space:pre-wrap; line-height:1.55; }

    .tk-resultado { border-radius:14px; padding:16px 18px; border:1px solid var(--co-line);
      border-left-width:5px; background:#fff; box-shadow:0 1px 2px rgba(16,24,40,.03); }
    .tk-resultado .top { display:flex; align-items:center; justify-content:space-between;
      gap:12px; flex-wrap:wrap; margin-bottom:12px; }
    .tk-resultado .quien { font-size:12px; color:var(--co-mute); }
    .tk-resultado .quien b { color:var(--co-ink); }
    .tk-resultado .coment { margin-top:12px; padding-top:12px; border-top:1px dashed var(--co-line);
      font-size:13px; white-space:pre-wrap; line-height:1.55; color:var(--co-ink); }

    .tk-meta { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; }
    .tk-meta .k { font-size:10px; font-weight:800; letter-spacing:.05em; text-transform:uppercase;
      color:var(--co-faint); }
    .tk-meta .v { font-size:13px; font-weight:600; margin-top:2px; }

    .tk-drop { border:2px dashed var(--co-line-bold); border-radius:12px; padding:18px;
      text-align:center; cursor:pointer; color:var(--co-mute); font-size:13px; }
    .tk-drop.over { border-color:var(--co-navy-700); background:rgba(0,135,90,.05); }
    .tk-files { display:flex; flex-direction:column; gap:7px; margin-top:10px; }
    .tk-file { display:flex; align-items:center; gap:9px; padding:8px 11px; border-radius:10px;
      background:var(--co-deck); font-size:12px; }
    .tk-file .nm { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .tk-file .st { font-size:10px; font-weight:800; letter-spacing:.04em; }
    .tk-file .st.pendiente { color:var(--wn); }
    .tk-file .st.error     { color:var(--er); }
    .tk-file .del { background:none; border:none; cursor:pointer; color:var(--er); font-size:16px;
      line-height:1; padding:0 4px; }
    .tk-ronda { font-size:10px; font-weight:800; letter-spacing:.05em; text-transform:uppercase;
      color:var(--co-faint); margin:10px 0 4px; }

    .tk-ver { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
    .tk-ver button { position:relative; display:flex; flex-direction:column; align-items:center;
      gap:8px; padding:16px 10px; border-radius:14px; border:1.5px solid var(--co-line-bold);
      background:#fff; cursor:pointer; font-family:inherit; font-weight:700; font-size:13px;
      color:var(--co-mute); transition:all .15s; }
    .tk-ver button .ic { width:34px; height:34px; border-radius:50%; display:flex;
      align-items:center; justify-content:center; background:var(--co-deck); color:var(--co-faint);
      transition:all .15s; }
    .tk-ver button .ic svg { width:17px; height:17px; }
    .tk-ver button .d { display:block; font-size:10.5px; font-weight:600; margin-top:0;
      color:var(--co-faint); letter-spacing:0; }
    .tk-ver button:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(16,24,40,.08); }
    .tk-ver button.on { box-shadow:0 6px 18px rgba(16,24,40,.1); }
    .tk-ver button.on[data-v="aprobada"]  { border-color:var(--ok); background:var(--ok-bg); color:var(--ok); }
    .tk-ver button.on[data-v="aprobada"] .ic  { background:var(--ok); color:#fff; }
    .tk-ver button.on[data-v="observada"] { border-color:var(--wn); background:var(--wn-bg); color:var(--wn); }
    .tk-ver button.on[data-v="observada"] .ic { background:var(--wn); color:#fff; }
    .tk-ver button.on[data-v="rechazada"] { border-color:var(--er); background:var(--er-bg); color:var(--er); }
    .tk-ver button.on[data-v="rechazada"] .ic { background:var(--er); color:#fff; }
    .tk-ver button.on .check { position:absolute; top:8px; right:8px; width:16px; height:16px;
      border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; }
    .tk-ver button.on .check svg { width:10px; height:10px; }
    .tk-ver button.on[data-v="aprobada"] .check  { background:var(--ok); }
    .tk-ver button.on[data-v="observada"] .check { background:var(--wn); }
    .tk-ver button.on[data-v="rechazada"] .check { background:var(--er); }

    .tk-notas { display:flex; gap:7px; flex-wrap:wrap; }
    .tk-notas button { flex:1; min-width:88px; display:flex; flex-direction:column; align-items:center;
      gap:5px; padding:11px 6px; border-radius:12px; border:1.5px solid var(--co-line-bold);
      background:#fff; cursor:pointer; font-family:inherit; font-size:11.5px; font-weight:600;
      color:var(--co-mute); transition:all .15s; }
    .tk-notas button:hover { transform:translateY(-2px); }
    .tk-notas button .n { display:flex; align-items:center; justify-content:center; width:26px; height:26px;
      border-radius:50%; background:var(--co-deck); font-size:13px; font-weight:800; color:var(--co-mute); }
    .tk-notas button.on { border-color:var(--wn); background:var(--wn-bg); color:var(--wn);
      box-shadow:0 4px 14px rgba(217,119,6,.14); }
    .tk-notas button.on .n { background:var(--wn); color:#fff; }

    .tk-tl { position:relative; padding-left:22px; }
    .tk-tl::before { content:''; position:absolute; left:6px; top:5px; bottom:5px; width:2px;
      background:var(--co-line); }
    .tk-tl-i { position:relative; padding-bottom:16px; }
    .tk-tl-i:last-child { padding-bottom:0; }
    .tk-tl-i::before { content:''; position:absolute; left:-21px; top:3px; width:11px; height:11px;
      border-radius:50%; background:var(--tl-c, var(--co-navy-700)); border:2px solid #fff;
      box-shadow:0 0 0 1.5px var(--tl-c, var(--co-navy-700)); }
    .tk-tl-i .ac { font-size:12.5px; font-weight:700; color:var(--co-ink); }
    .tk-tl-i .dt { font-size:11px; color:var(--co-faint); margin-top:1px; }
    .tk-tl-i .de { font-size:12px; color:var(--co-mute); margin-top:4px; white-space:pre-wrap;
      background:var(--co-deck); border-radius:8px; padding:7px 10px; }

    /* Modal workspace · shared composition and detail language */
    .tk-mb {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0,135,90,.18); --co-line-bold:rgba(0,135,90,.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#6b7280;
      --ok:#047857; --ok-bg:rgba(4,120,87,.10); --wn:#d97706; --wn-bg:rgba(217,119,6,.10);
      --er:#dc2626; --er-bg:rgba(220,38,38,.10); --sl:#475569; --sl-bg:rgba(100,116,139,.12);
      --bl:#2563eb; --bl-bg:rgba(37,99,235,.10); --vi:#7c3aed; --vi-bg:rgba(124,58,237,.10);
      background:rgba(6,24,18,.66); backdrop-filter:blur(7px); padding:24px;
    }
    .tk-mb.open .tk-modal { animation:tk-modal-in .22s cubic-bezier(.22,.8,.3,1) both; }
    @keyframes tk-modal-in {
      from { opacity:0; transform:translateY(14px) scale(.985); }
      to { opacity:1; transform:translateY(0) scale(1); }
    }
    .tk-modal { max-width:680px; max-height:min(92vh,860px); max-height:min(92dvh,860px);
      overflow:hidden; border:1px solid rgba(255,255,255,.7); border-radius:22px;
      box-shadow:0 32px 90px rgba(2,44,29,.28),0 8px 24px rgba(0,0,0,.16); }
    .tk-modal.wide { max-width:920px; }
    .tk-mh { position:relative; align-items:center; padding:22px 24px; border:0;
      background:linear-gradient(125deg,#005c3d 0%,#007d54 58%,#00875A 100%); color:#fff; }
    .tk-mh::after { content:''; position:absolute; inset:auto 0 0; height:1px;
      background:rgba(255,255,255,.16); }
    .tk-modal-heading { display:flex; align-items:center; gap:14px; min-width:0; }
    .tk-modal-headmark { width:44px; height:44px; flex:0 0 44px; display:grid; place-items:center;
      border:1px solid rgba(255,255,255,.24); border-radius:13px; background:rgba(255,255,255,.13);
      box-shadow:inset 0 1px 0 rgba(255,255,255,.14); }
    .tk-modal-headmark svg { width:21px; height:21px; }
    .tk-modal-kicker { margin-bottom:4px; color:rgba(255,255,255,.7); font-size:10px;
      font-weight:800; letter-spacing:.12em; line-height:1; text-transform:uppercase; }
    .tk-mh h3 { color:#fff; font-size:19px; line-height:1.2; letter-spacing:-.02em; }
    .tk-mh .sub { max-width:650px; color:rgba(255,255,255,.76); font-size:12px; line-height:1.45; }
    .tk-mx { width:44px; height:44px; flex:0 0 44px; display:grid; place-items:center; padding:0;
      border:1px solid rgba(255,255,255,.16); border-radius:12px; color:#fff;
      transition:background .18s,border-color .18s,transform .18s; }
    .tk-mx:hover { background:rgba(255,255,255,.13); border-color:rgba(255,255,255,.28); }
    .tk-mx:active { transform:scale(.96); }
    .tk-mbody { gap:16px; padding:20px 24px 24px; background:var(--co-deck);
      scrollbar-color:rgba(0,135,90,.3) transparent; scrollbar-width:thin; }
    .tk-mf { align-items:center; justify-content:space-between; min-height:74px; padding:14px 24px;
      border-color:var(--co-line); background:rgba(255,255,255,.96); box-shadow:0 -8px 24px rgba(8,55,40,.05); }
    .tk-footer-actions,.tk-footer-secondary,.tk-footer-primary { display:flex; align-items:center; gap:10px; }
    .tk-modal-footnote { display:flex; align-items:center; gap:7px; color:var(--co-faint); font-size:11px; }
    .tk-modal-footnote svg { width:14px; height:14px; color:var(--co-navy-700); }
    .tk-mf .tk-btn { min-height:42px; padding:10px 17px; }
    .tk-mf .tk-btn.primary { min-width:132px; justify-content:center; box-shadow:0 7px 20px rgba(0,135,90,.22); }

    .tk-form-section { padding:17px; border:1px solid var(--co-line); border-radius:17px;
      background:#fff; box-shadow:0 1px 2px rgba(16,24,40,.025); }
    .tk-form-section-head { display:flex; align-items:flex-start; gap:10px; margin-bottom:15px; }
    .tk-form-section-icon { width:30px; height:30px; flex:0 0 30px; display:grid; place-items:center;
      border-radius:9px; background:rgba(0,135,90,.09); color:var(--co-navy-700); }
    .tk-form-section-icon svg { width:15px; height:15px; }
    .tk-form-section-title { margin:0; color:var(--co-ink); font-size:13px; font-weight:800; line-height:1.25; }
    .tk-form-section-copy { margin-top:2px; color:var(--co-faint); font-size:11px; line-height:1.4; }
    .tk-field { gap:7px; }
    .tk-field + .tk-field { margin-top:14px; }
    .tk-row2 > .tk-field + .tk-field { margin-top:0; }
    .tk-field label { color:#374151; font-size:11px; letter-spacing:.025em; }
    .tk-field input,.tk-field select,.tk-field textarea { min-height:44px; padding:11px 13px;
      border-color:rgba(17,24,39,.14); border-radius:11px; color:var(--co-ink); outline:none;
      transition:border-color .18s,box-shadow .18s,background .18s; }
    .tk-field textarea { min-height:104px; line-height:1.55; }
    .tk-field input:hover,.tk-field select:hover,.tk-field textarea:hover { border-color:var(--co-line-bold); }
    .tk-field input:focus,.tk-field select:focus,.tk-field textarea:focus { border-color:var(--co-navy-700);
      box-shadow:0 0 0 3px rgba(0,135,90,.12); }
    .tk-field .hint { line-height:1.45; }
    .tk-field.is-invalid input,.tk-field.is-invalid textarea,.tk-field.is-invalid .tk-dest {
      border-color:var(--er); box-shadow:0 0 0 3px rgba(220,38,38,.08); }
    .tk-sr-only { position:absolute!important; width:1px!important; height:1px!important; padding:0!important;
      margin:-1px!important; overflow:hidden!important; clip:rect(0,0,0,0)!important; white-space:nowrap!important; border:0!important; }
    .tk-priority-options { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
    .tk-priority-option { --priority-color:var(--co-navy-700); position:relative; min-height:56px;
      display:flex; align-items:center; gap:9px; padding:10px 11px; border:1px solid rgba(17,24,39,.13);
      border-radius:11px; background:#fff; color:var(--co-mute); cursor:pointer; font:700 12px 'DM Sans',sans-serif;
      transition:border-color .18s,background .18s,box-shadow .18s; }
    .tk-priority-option::before { content:''; width:9px; height:9px; flex:0 0 9px; border-radius:50%;
      background:var(--priority-color); box-shadow:0 0 0 4px color-mix(in srgb,var(--priority-color) 12%,transparent); }
    .tk-priority-option.is-low { --priority-color:#64748b; }
    .tk-priority-option.is-medium { --priority-color:#d97706; }
    .tk-priority-option.is-high { --priority-color:#dc2626; }
    .tk-priority-option:hover { border-color:var(--priority-color); background:color-mix(in srgb,var(--priority-color) 4%,#fff); }
    .tk-priority-option[aria-pressed="true"] { border-color:var(--priority-color);
      background:color-mix(in srgb,var(--priority-color) 8%,#fff); color:var(--co-ink);
      box-shadow:0 0 0 2px color-mix(in srgb,var(--priority-color) 13%,transparent); }
    .tk-priority-option[aria-pressed="true"]::after { content:'✓'; margin-left:auto; color:var(--priority-color); font-size:13px; }

    .tk-dest { max-height:238px; border-color:rgba(17,24,39,.14); background:#fff; }
    .tk-dest .grp { z-index:1; padding:9px 12px; background:#f2f7f5; color:#60706a; }
    .tk-dest label { min-height:46px; padding:9px 12px; transition:background .15s; }
    .tk-dest label:has(input:checked) { background:rgba(0,135,90,.065); }
    .tk-dest input { width:18px; height:18px; flex:0 0 18px; }
    .tk-selection-summary { display:flex; align-items:center; gap:8px; min-height:34px; margin-top:9px;
      padding:8px 10px; border-radius:10px; background:rgba(0,135,90,.07); color:#316452; font-size:11px; font-weight:650; }
    .tk-selection-summary svg { width:14px; height:14px; flex:0 0 14px; color:var(--co-navy-700); }
    .tk-batch-option { padding:13px 14px; border:1px solid rgba(217,119,6,.22); border-radius:12px;
      background:rgba(217,119,6,.055); }

    .tk-detail-summary { flex:0 0 auto; display:grid; grid-template-columns:1.2fr repeat(3,1fr); overflow:hidden;
      border:1px solid var(--co-line); border-radius:16px; background:#fff; box-shadow:0 1px 2px rgba(16,24,40,.03); }
    .tk-detail-summary > div { min-height:76px; display:flex; flex-direction:column; justify-content:center;
      gap:6px; padding:14px 16px; border-right:1px solid var(--co-line); }
    .tk-detail-summary > div:last-child { border-right:0; }
    .tk-detail-summary strong { color:var(--co-ink); font-size:13px; text-transform:capitalize; }
    .tk-detail-summary .is-danger strong { color:var(--er); }
    .tk-summary-label { color:var(--co-faint); font-size:9.5px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
    .tk-detail .tk-sec { border-radius:16px; padding:18px; }
    .tk-detail .tk-sec h5 { color:#53645e; }
    .tk-detail .tk-sec h5 svg { width:16px; height:16px; }
    .tk-delivery-panel { padding:18px; border:1px solid rgba(0,135,90,.24); border-radius:17px;
      background:linear-gradient(145deg,rgba(0,135,90,.075),rgba(255,255,255,.95) 65%); }
    .tk-delivery-title { display:flex; align-items:center; gap:10px; margin-bottom:12px; color:var(--co-ink); font-size:13px; font-weight:800; }
    .tk-delivery-title span { width:30px; height:30px; display:grid; place-items:center; border-radius:9px; background:var(--co-navy-700); color:#fff; }
    .tk-delivery-title svg { width:15px; height:15px; }
    .tk-drop { padding:24px 18px; border-color:rgba(0,135,90,.36); background:rgba(255,255,255,.78);
      transition:border-color .18s,background .18s,box-shadow .18s; }
    .tk-drop:hover,.tk-drop.over { border-color:var(--co-navy-700); background:#fff; box-shadow:0 0 0 4px rgba(0,135,90,.08); }
    .tk-drop-icon { width:42px; height:42px; display:grid; place-items:center; margin:0 auto 9px;
      border-radius:12px; background:rgba(0,135,90,.1); color:var(--co-navy-700); }
    .tk-drop-icon svg { width:20px; height:20px; }
    .tk-drop strong { display:block; margin-bottom:3px; color:var(--co-ink); font-size:13px; }
    .tk-file { min-height:48px; padding:8px 10px; border:1px solid rgba(17,24,39,.07); background:#f7faf9; }
    .tk-file-icon { width:30px; height:30px; flex:0 0 30px; display:grid; place-items:center;
      border-radius:8px; background:#fff; color:var(--co-navy-700); box-shadow:0 1px 3px rgba(0,0,0,.06); }
    .tk-file-icon svg { width:15px; height:15px; }
    .tk-file .del { width:40px; height:40px; display:grid; place-items:center; padding:0; border-radius:9px; }
    .tk-file .del:hover { background:var(--er-bg); }

    .tk-btn:focus-visible,.tk-mx:focus-visible,.tk-priority-option:focus-visible,.tk-dest input:focus-visible,
    .tk-drop:focus-visible { outline:3px solid rgba(255,255,255,.9); outline-offset:2px; box-shadow:0 0 0 5px rgba(0,135,90,.45); }
    .tk-field input:focus-visible,.tk-field select:focus-visible,.tk-field textarea:focus-visible {
      outline:2px solid var(--co-navy-700); outline-offset:2px; }

    @media (max-width:720px) {
      .tk-mb { padding:14px; }
      .tk-modal { max-height:calc(100dvh - 28px); }
      .tk-row2 { grid-template-columns:1fr; gap:14px; }
      .tk-row2 > .tk-field + .tk-field { margin-top:0; }
      .tk-detail-summary { grid-template-columns:repeat(2,1fr); }
      .tk-detail-summary > div:nth-child(2) { border-right:0; }
      .tk-detail-summary > div:nth-child(-n+2) { border-bottom:1px solid var(--co-line); }
      .tk-ver { grid-template-columns:1fr; }
      .tk-mf { align-items:stretch; }
      .tk-footer-secondary,.tk-footer-primary { flex-wrap:wrap; }
    }
    @media (max-width:520px) {
      .tk-mb { align-items:stretch; padding:0; }
      .tk-modal,.tk-modal.wide { max-height:100dvh; height:100dvh; border:0; border-radius:0; }
      .tk-mh { padding:17px 16px; }
      .tk-modal-headmark { width:40px; height:40px; flex-basis:40px; }
      .tk-mh h3 { font-size:17px; }
      .tk-mh .sub { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
      .tk-mbody { padding:15px 14px 20px; }
      .tk-form-section { padding:15px 13px; }
      .tk-priority-options { grid-template-columns:1fr; }
      .tk-priority-option { min-height:46px; }
      .tk-mf { min-height:auto; flex-direction:column; padding:12px 14px max(12px,env(safe-area-inset-bottom)); }
      .tk-modal-footnote { display:none; }
      .tk-footer-actions,.tk-footer-secondary,.tk-footer-primary { width:100%; }
      .tk-footer-actions .tk-btn,.tk-footer-primary .tk-btn { flex:1; justify-content:center; }
      .tk-footer-secondary .tk-btn { flex:1; justify-content:center; }
      .tk-detail-summary > div { min-height:68px; padding:12px; }
      .tk-dest .who { display:none; }
      .tk-notas button { min-width:72px; }
    }
    @media (prefers-reduced-motion:reduce) {
      .tk-mb.open .tk-modal { animation:none; }
      .tk-modal * { scroll-behavior:auto!important; transition-duration:.01ms!important; animation-duration:.01ms!important; }
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
      <div class="tk-wrap">

        <div class="tk-hero">
          <div>
            <span class="tag"><?= $ES_TABLERO ? 'CONTROL DE TAREAS' : 'MIS TAREAS' ?></span>
            <h1>Tareas</h1>
            <p><?= $ES_TABLERO
                ? 'Encarga trabajo con plazo a coordinadores y Tally Soporte, revisa la evidencia y califica el resultado.'
                : 'Lo que tienes encargado, con su plazo. Sube la evidencia y envía la entrega antes de la fecha.' ?></p>
          </div>
          <?php if ($ES_ADMIN): ?>
          <button class="tk-btn ghost-light" id="btnNueva">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nueva tarea
          </button>
          <?php endif; ?>
        </div>

        <div class="tk-kpis" id="tkKpis"></div>

        <?php if (count($MIS_SOPORTES)): ?>
        <div class="tk-seg">
          <button class="on" data-ambito="mias">Mis tareas</button>
          <button data-ambito="soporte">Mi soporte<?= count($MIS_SOPORTES) === 1 ? ' · ' . htmlspecialchars($MIS_SOPORTES[0]['nombre']) : '' ?></button>
        </div>
        <?php endif; ?>

        <div class="tk-card">
          <div class="tk-tools">
            <input class="tk-search" id="tkQ" type="search" placeholder="Buscar por título, descripción o persona…">
            <div class="tk-chips" id="tkChips"></div>
            <?php if ($ES_TABLERO): ?>
            <select class="tk-sel" id="tkPersona"><option value="">Todas las personas</option></select>
            <?php endif; ?>
            <select class="tk-sel" id="tkMes"><option value="">Todos los meses</option></select>
            <button class="tk-btn" id="btnExcel" type="button">Excel</button>
            <button class="tk-btn" id="btnPdf" type="button">PDF</button>
          </div>

          <?php if ($ES_TABLERO): ?>
          <div class="tk-tablewrap">
            <table class="tk-table">
              <thead><tr>
                <th>Tarea</th><th>Asignado</th><th>Plazo</th><th>Entrega</th>
                <th>Adj.</th><th>Estado</th><th>Nota</th>
              </tr></thead>
              <tbody id="tkTbody"></tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="tk-list" id="tkList" style="padding:14px 16px"></div>
          <?php endif; ?>
        </div>

      </div>
    </main>
  </div>
</div>

<?php if ($ES_ADMIN): ?>
<div class="tk-mb" id="tkModalBack">
  <div class="tk-modal tk-compose" role="dialog" aria-modal="true" aria-labelledby="tkModalTitle">
    <div class="tk-mh">
      <div class="tk-modal-heading">
        <div class="tk-modal-headmark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg>
        </div>
        <div>
          <div class="tk-modal-kicker">Planificación operativa</div>
          <h3 id="tkModalTitle">Nueva tarea</h3>
          <div class="sub" id="tkModalSub">Se creará una tarea independiente por cada destinatario.</div>
        </div>
      </div>
      <button class="tk-mx" id="tkModalClose" type="button" aria-label="Cerrar formulario de tarea">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="tk-mbody">
      <input type="hidden" id="tm-id">
      <section class="tk-form-section">
        <div class="tk-form-section-head">
          <span class="tk-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5V4.5A2.5 2.5 0 0 1 6.5 2H19v20H6.5A2.5 2.5 0 0 1 4 19.5z"/><path d="M8 7h7M8 11h7"/></svg></span>
          <div><h4 class="tk-form-section-title">Información de la tarea</h4><div class="tk-form-section-copy">Define con claridad qué debe hacerse y cómo se comprobará.</div></div>
        </div>
        <div class="tk-field">
          <label for="tm-titulo">Título <span aria-hidden="true">*</span></label>
          <input id="tm-titulo" type="text" maxlength="180" placeholder="Ej. Inventario de precintos del almacén" autocomplete="off">
        </div>
        <div class="tk-field">
          <label for="tm-desc">Descripción</label>
          <textarea id="tm-desc" placeholder="Explica qué hay que hacer y qué evidencia esperas."></textarea>
          <span class="hint">Incluye el resultado esperado para reducir dudas durante la entrega.</span>
        </div>
      </section>

      <section class="tk-form-section">
        <div class="tk-form-section-head">
          <span class="tk-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
          <div><h4 class="tk-form-section-title">Planificación</h4><div class="tk-form-section-copy">Marca la importancia y el plazo real de cumplimiento.</div></div>
        </div>
        <div class="tk-row2">
          <div class="tk-field">
            <label for="tm-prioridad">Prioridad</label>
            <select id="tm-prioridad" class="tk-sr-only" tabindex="-1" aria-hidden="true">
              <option value="baja">Baja</option>
              <option value="media" selected>Media</option>
              <option value="alta">Alta</option>
            </select>
            <div class="tk-priority-options" id="tm-priority-options" role="group" aria-label="Prioridad de la tarea">
              <button type="button" class="tk-priority-option is-low" data-priority="baja" aria-pressed="false">Baja</button>
              <button type="button" class="tk-priority-option is-medium" data-priority="media" aria-pressed="true">Media</button>
              <button type="button" class="tk-priority-option is-high" data-priority="alta" aria-pressed="false">Alta</button>
            </div>
          </div>
          <div class="tk-field">
            <label for="tm-fecha">Fecha límite <span aria-hidden="true">*</span></label>
            <input id="tm-fecha" type="datetime-local">
            <span class="hint">Si la hora queda en 00:00, se guardará como 23:59.</span>
          </div>
        </div>
      </section>

      <section class="tk-form-section" id="tm-dest-wrap">
        <div class="tk-form-section-head">
          <span class="tk-form-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
          <div><h4 class="tk-form-section-title">Asignación</h4><div class="tk-form-section-copy">Cada persona recibirá una tarea independiente.</div></div>
        </div>
        <div class="tk-field">
          <label>Destinatarios <span aria-hidden="true">*</span></label>
          <div class="tk-dest" id="tm-dest"></div>
          <div class="tk-selection-summary" id="tm-dest-hint" aria-live="polite">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
            <span id="tm-dest-hint-text">Ninguno seleccionado.</span>
          </div>
        </div>
      </section>

      <div class="tk-field tk-batch-option" id="tm-lote-wrap" style="display:none">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" id="tm-lote" style="width:18px;height:18px">
          Aplicar los cambios a todo el lote
        </label>
        <span class="hint">Solo se actualizarán las tareas pendientes. Las entregadas o calificadas conservarán su enunciado original.</span>
      </div>
    </div>
    <div class="tk-mf">
      <div class="tk-modal-footnote"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg>Los campos con * son obligatorios</div>
      <div class="tk-footer-actions">
        <button class="tk-btn" id="tkModalCancel" type="button">Cancelar</button>
        <button class="tk-btn primary" id="tkModalSave" type="button">Crear tarea</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="tk-mb" id="tkDetBack">
  <div class="tk-modal wide tk-detail" role="dialog" aria-modal="true" aria-labelledby="tkDetTitle">
    <div class="tk-mh">
      <div class="tk-modal-heading">
        <div class="tk-modal-headmark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg>
        </div>
        <div>
          <div class="tk-modal-kicker">Detalle de tarea</div>
          <h3 id="tkDetTitle">Tarea</h3>
          <div class="sub" id="tkDetSub">—</div>
        </div>
      </div>
      <button class="tk-mx" id="tkDetClose" type="button" aria-label="Cerrar detalle de tarea">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="tk-mbody" id="tkDetBody"></div>
    <div class="tk-mf" id="tkDetFoot"></div>
  </div>
</div>

<div class="tk-toast" id="tkToast">—</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script>
(function () {
  'use strict';

  const ESTADOS     = <?= json_encode($JS_ESTADOS, JSON_UNESCAPED_UNICODE) ?>;
  const PRIORIDADES = <?= json_encode($JS_PRIORIDADES, JSON_UNESCAPED_UNICODE) ?>;
  const SEMAFOROS   = <?= json_encode($JS_SEMAFOROS, JSON_UNESCAPED_UNICODE) ?>;
  const ESCALA      = <?= json_encode($JS_ESCALA, JSON_UNESCAPED_UNICODE) ?>;
  const ES_ADMIN    = <?= $ES_ADMIN ? 'true' : 'false' ?>;
  const ES_TABLERO  = <?= $ES_TABLERO ? 'true' : 'false' ?>;
  const USER_ID     = <?= $USER_ID ?>;
  const MIS_SOPORTES = <?= json_encode($MIS_SOPORTES, JSON_UNESCAPED_UNICODE) ?>;
  const LOGO_B64    = <?= json_encode($LOGO_B64) ?>;

  const $ = (id) => document.getElementById(id);

  let tareas   = [];
  let asignables = [];
  let fEstado  = '';
  let fAtrasadas = false;
  let fPersona = '';
  let fMes     = '';
  let query    = '';
  let ambito   = 'mias';

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c =>
      ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }
  function toast(msg, type) {
    const t = $('tkToast');
    t.textContent = msg;
    t.className = 'tk-toast show' + (type === 'error' ? ' is-error' : '');
    clearTimeout(t._t); t._t = setTimeout(() => t.className = 'tk-toast', 3200);
  }
  function fmt(dt) {
    if (!dt) return '—';
    const m = String(dt).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : String(dt);
  }
  function fmtFecha(dt) {
    if (!dt) return '—';
    const m = String(dt).match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : String(dt);
  }
  function estrellas(n) {
    if (!n) return '<span style="color:var(--co-faint)">—</span>';
    return `<span class="tk-stars">${'★'.repeat(n)}${'☆'.repeat(5 - n)}</span>`
         + `<div class="tk-sub">${esc(ESCALA[n] || '')}</div>`;
  }
  function badgeEstado(t) {
    const e = ESTADOS[t.estado] || { label: t.estado, color: '#475569', bg: '#eee' };
    return `<span class="tk-badge" style="color:${e.color};background:${e.bg}">`
         + `<span class="dot"></span>${esc(e.label)}</span>`;
  }
  function chipAtraso(t) {
    if (!t.atrasada) return '';
    const d = t.dias_atraso;
    return `<span class="tk-late">ATRASADA${d > 0 ? ' · ' + d + (d === 1 ? ' día' : ' días') : ''}</span>`;
  }
  function celdaPlazo(t) {
    const s = SEMAFOROS[t.semaforo] || SEMAFOROS.a_tiempo;
    let html = `<div style="font-weight:600;color:${t.atrasada ? 'var(--er)' : 'inherit'}">${fmt(t.plazo_vigente)}</div>`;
    if (t.tiene_prorroga) html += `<div class="tk-sub">2ª fecha · antes <span class="tk-tacha">${fmt(t.fecha_limite)}</span></div>`;
    if (t.es_abierta && !t.atrasada && t.semaforo !== 'a_tiempo') {
      html += `<div class="tk-sub" style="color:${s.color};font-weight:700">${esc(s.label)}</div>`;
    }
    if (t.atrasada) html += `<div style="margin-top:3px">${chipAtraso(t)}</div>`;
    return html;
  }
  function celdaEntrega(t) {
    if (!t.enviado_at) return '<span style="color:var(--co-faint)">Sin entregar</span>';
    let html = `<div>${fmt(t.enviado_at)}</div>`;
    if (t.entregada_tarde) html += `<div style="margin-top:3px"><span class="tk-late">FUERA DE PLAZO</span></div>`;
    if (t.entregas_count > 1) html += `<div class="tk-sub">${t.entregas_count}.º envío</div>`;
    return html;
  }

  function listaVisible() {
    const q = query.trim().toLowerCase();
    return tareas.filter(t => {
      if (fEstado && t.estado !== fEstado) return false;
      if (fAtrasadas && !t.atrasada) return false;
      if (fPersona && String(t.asignado_id) !== String(fPersona)) return false;
      if (fMes && String(t.fecha_limite).slice(0, 7) !== fMes) return false;
      if (MIS_SOPORTES.length) {
        const mia = Number(t.asignado_id) === USER_ID;
        if (ambito === 'mias' && !mia) return false;
        if (ambito === 'soporte' && mia) return false;
      }
      if (q) {
        const heno = [t.titulo, t.descripcion, t.asignado_nombre].join(' ').toLowerCase();
        if (!heno.includes(q)) return false;
      }
      return true;
    });
  }

  function pintarKpis() {
    const L = listaVisible();
    const n = (f) => L.filter(f).length;
    let kpis;
    if (ES_TABLERO) {
      const conNota = L.filter(t => t.nota);
      const media = conNota.length
        ? (conNota.reduce((a, t) => a + t.nota, 0) / conNota.length).toFixed(1) : '—';
      kpis = [
        ['Pendientes',   n(t => t.estado === 'pendiente'),  ''],
        ['Atrasadas',    n(t => t.atrasada),                'is-alert'],
        ['Por revisar',  n(t => t.estado === 'entregada'),  'is-info'],
        ['Aprobadas',    n(t => t.estado === 'aprobada'),   'is-ok'],
        ['Nota media',   media,                             'is-warn'],
      ];
    } else {
      kpis = [
        ['Por hacer',   n(t => t.es_abierta),              ''],
        ['Atrasadas',   n(t => t.atrasada),                'is-alert'],
        ['En revisión', n(t => t.estado === 'entregada'),  'is-info'],
        ['Aprobadas',   n(t => t.estado === 'aprobada'),   'is-ok'],
      ];
    }
    $('tkKpis').innerHTML = kpis.map(([lbl, val, cls]) =>
      `<div class="tk-kpi ${cls}"><div class="lbl">${esc(lbl)}</div><div class="val">${esc(val)}</div></div>`
    ).join('');
  }

  function pintarChips() {
    let html = `<button class="tk-chip${fEstado === '' && !fAtrasadas ? ' on' : ''}" data-estado="">Todas</button>`;
    Object.entries(ESTADOS).forEach(([k, e]) => {
      html += `<button class="tk-chip${fEstado === k ? ' on' : ''}" data-estado="${k}">${esc(e.label)}</button>`;
    });
    html += `<button class="tk-chip is-late${fAtrasadas ? ' on' : ''}" data-atrasadas="1">Atrasadas</button>`;
    $('tkChips').innerHTML = html;
  }

  function pintarTabla() {
    const tbody = $('tkTbody');
    const L = listaVisible();
    if (!L.length) {
      tbody.innerHTML = `<tr><td colspan="7" class="tk-empty">Sin tareas que coincidan con el filtro.</td></tr>`;
      return;
    }
    tbody.innerHTML = L.map(t => {
      const pr = PRIORIDADES[t.prioridad] || PRIORIDADES.media;
      const esSop = t.asignado_rol === 'Soporte';
      return `<tr class="${t.atrasada ? 'is-late' : ''}" data-abrir="${t.id}">
        <td>
          <div class="tk-tt">${esc(t.titulo)}</div>
          <div class="tk-sub">
            <span style="color:${pr.color};font-weight:700">${esc(pr.label)}</span>
            ${t.descripcion ? ' · ' + esc(String(t.descripcion).slice(0, 70)) : ''}
          </div>
        </td>
        <td>
          <div>${esc(t.asignado_nombre)}</div>
          <div class="tk-sub"><span class="tk-rolchip ${esSop ? 'is-sop' : ''}">${esc(t.asignado_rol_label)}</span></div>
        </td>
        <td>${celdaPlazo(t)}</td>
        <td>${celdaEntrega(t)}</td>
        <td style="text-align:center">${t.adjuntos.length || '—'}</td>
        <td>${badgeEstado(t)}</td>
        <td>${estrellas(t.nota)}</td>
      </tr>`;
    }).join('');
  }

  function pintarLista() {
    const cont = $('tkList');
    const L = listaVisible();
    if (!L.length) {
      cont.innerHTML = `<div class="tk-empty">No tienes tareas que coincidan con el filtro.</div>`;
      return;
    }
    cont.innerHTML = L.map(t => `
      <div class="tk-item sem-${esc(t.semaforo)}" data-abrir="${t.id}">
        <div class="grow">
          <h4>${esc(t.titulo)} ${chipAtraso(t)}</h4>
          ${t.descripcion ? `<p>${esc(t.descripcion)}</p>` : ''}
          <div class="tk-sub" style="margin-top:6px">
            Vence ${fmt(t.plazo_vigente)}
            ${t.tiene_prorroga ? ` · 2ª fecha (antes <span class="tk-tacha">${fmt(t.fecha_limite)}</span>)` : ''}
            ${t.adjuntos.length ? ` · ${t.adjuntos.length} archivo${t.adjuntos.length === 1 ? '' : 's'}` : ''}
          </div>
        </div>
        <div style="text-align:right;display:flex;flex-direction:column;gap:6px;align-items:flex-end">
          ${badgeEstado(t)}
          ${t.nota ? estrellas(t.nota) : ''}
        </div>
      </div>`).join('');
  }

  function pintar() {
    pintarChips();
    pintarKpis();
    if (ES_TABLERO) pintarTabla(); else pintarLista();
  }

  async function cargar() {
    try {
      const res  = await fetch('../api/get_tareas.php', { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) { toast(data.error || 'Error al cargar', 'error'); return; }
      tareas = data.data || [];
      pintarSelectMes();
      pintar();
    } catch (e) { toast('Error de red al cargar las tareas', 'error'); }
  }

  async function cargarAsignables() {
    if (!ES_TABLERO && !ES_ADMIN) return;
    try {
      const res  = await fetch('../api/get_asignables.php', { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) return;
      asignables = data.data || [];
      const sel = $('tkPersona');
      if (!sel) return;
      let html = '<option value="">Todas las personas</option>';
      ['Coordinador', 'Soporte'].forEach(rol => {
        const g = asignables.filter(a => a.rol === rol);
        if (!g.length) return;
        html += `<optgroup label="${rol === 'Soporte' ? 'Tally Soporte' : 'Coordinadores'}">`
              + g.map(a => `<option value="${a.id}">${esc(a.nombre)}</option>`).join('')
              + '</optgroup>';
      });
      sel.innerHTML = html;
    } catch (e) {  }
  }

  function pintarSelectMes() {
    const meses = [...new Set(tareas.map(t => String(t.fecha_limite).slice(0, 7)))].sort().reverse();
    const nom = ['','enero','febrero','marzo','abril','mayo','junio','julio',
                 'agosto','septiembre','octubre','noviembre','diciembre'];
    $('tkMes').innerHTML = '<option value="">Todos los meses</option>'
      + meses.map(m => {
          const [y, mm] = m.split('-');
          return `<option value="${m}"${fMes === m ? ' selected' : ''}>${nom[Number(mm)]} ${y}</option>`;
        }).join('');
  }

  $('tkQ').addEventListener('input', e => { query = e.target.value; pintar(); });
  $('tkMes').addEventListener('change', e => { fMes = e.target.value; pintar(); });
  if ($('tkPersona')) $('tkPersona').addEventListener('change', e => { fPersona = e.target.value; pintar(); });

  $('tkChips').addEventListener('click', e => {
    const b = e.target.closest('.tk-chip'); if (!b) return;
    if (b.dataset.atrasadas) { fAtrasadas = !fAtrasadas; fEstado = ''; }
    else { fEstado = b.dataset.estado; fAtrasadas = false; }
    pintar();
  });

  document.querySelectorAll('.tk-seg button').forEach(b => {
    b.addEventListener('click', () => {
      document.querySelectorAll('.tk-seg button').forEach(x => x.classList.remove('on'));
      b.classList.add('on');
      ambito = b.dataset.ambito;
      pintar();
    });
  });

  document.addEventListener('click', e => {
    const el = e.target.closest('[data-abrir]');
    if (el) abrirDetalle(Number(el.dataset.abrir));
  });

  window.abrirDetalle = window.abrirDetalle || function () { toast('Detalle aún no disponible'); };

  cargarAsignables();
  cargar();
  window.tkRecargar = cargar;
  window.tkToast    = toast;
  window.tkListaVisible = listaVisible;
  window.tkEsc      = esc;
  window.tkFmt      = fmt;
  window.tkFmtFecha = fmtFecha;
  window.tkBadgeEstado = badgeEstado;
  window.tkEstrellas   = estrellas;
  window.tkAsignables = () => asignables;
  window.tkLogo     = LOGO_B64;
})();
</script>
<?php if ($ES_ADMIN): ?>
<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const esc = window.tkEsc;
  let editando = null;

  function pintarDestinatarios(preseleccion) {
    const lista = window.tkAsignables();
    let html = '';
    [['Coordinador', 'Coordinadores'], ['Soporte', 'Tally Soporte']].forEach(([rol, titulo]) => {
      const g = lista.filter(a => a.rol === rol);
      if (!g.length) return;
      html += `<div class="grp">${titulo}</div>`;
      html += g.map(a => `
        <label>
          <input type="checkbox" value="${a.id}" ${preseleccion === a.id ? 'checked' : ''}>
          <span>${esc(a.nombre)}</span>
          ${a.coordinador_nombre ? `<span class="who">apoyo de ${esc(a.coordinador_nombre)}</span>` : ''}
        </label>`).join('');
    });
    $('tm-dest').innerHTML = html || '<div class="tk-empty">No hay coordinadores ni soportes activos.</div>';
    actualizarHint();
  }

  function seleccionados() {
    return [...$('tm-dest').querySelectorAll('input:checked')].map(i => Number(i.value));
  }

  function actualizarHint() {
    const n = seleccionados().length;
    $('tm-dest-hint-text').textContent = n === 0
      ? 'Ninguno seleccionado.'
      : `Se ${n === 1 ? 'creará 1 tarea' : 'crearán ' + n + ' tareas'} independientes, una por persona.`;
  }

  function setPriority(value) {
    $('tm-prioridad').value = value;
    $('tm-priority-options').querySelectorAll('[data-priority]').forEach(button => {
      button.setAttribute('aria-pressed', String(button.dataset.priority === value));
    });
  }

  function clearFieldErrors() {
    $('tkModalBack').querySelectorAll('.tk-field.is-invalid').forEach(field => field.classList.remove('is-invalid'));
  }

  function markFieldError(id) {
    $(id).closest('.tk-field')?.classList.add('is-invalid');
  }

  function abrir(t) {
    editando = t || null;
    clearFieldErrors();
    $('tkModalTitle').textContent = t ? 'Editar tarea' : 'Nueva tarea';
    $('tkModalSub').textContent   = t
      ? 'Solo el enunciado y la 1ª fecha. El estado y la nota no se tocan aquí.'
      : 'Se creará una tarea independiente por cada destinatario.';
    $('tm-id').value        = t ? t.id : '';
    $('tm-titulo').value    = t ? t.titulo : '';
    $('tm-desc').value      = t ? (t.descripcion || '') : '';
    setPriority(t ? t.prioridad : 'media');
    $('tm-fecha').value     = t ? String(t.fecha_limite).slice(0, 16).replace(' ', 'T') : '';
    $('tkModalSave').textContent = t ? 'Guardar cambios' : 'Crear tarea';

    $('tm-dest-wrap').style.display = t ? 'none' : '';
    $('tm-lote-wrap').style.display = (t && t.lote_id) ? '' : 'none';
    $('tm-lote').checked = false;
    if (!t) pintarDestinatarios(null);

    $('tkModalBack').classList.add('open');
    setTimeout(() => $('tm-titulo').focus(), 80);
  }
  function cerrar() { $('tkModalBack').classList.remove('open'); editando = null; }

  async function guardar() {
    clearFieldErrors();
    const titulo = $('tm-titulo').value.trim();
    let   fecha  = $('tm-fecha').value.trim();

    if (!titulo) { markFieldError('tm-titulo'); window.tkToast('Indica el título de la tarea', 'error'); $('tm-titulo').focus(); return; }
    if (!fecha)  { markFieldError('tm-fecha'); window.tkToast('Indica la fecha límite', 'error');       $('tm-fecha').focus();  return; }

    fecha = fecha.replace('T', ' ');
    if (fecha.endsWith(' 00:00')) fecha = fecha.slice(0, 11) + '23:59';

    const payload = {
      id:            Number($('tm-id').value) || 0,
      titulo,
      descripcion:   $('tm-desc').value.trim(),
      prioridad:     $('tm-prioridad').value,
      fecha_limite:  fecha,
    };

    if (payload.id) {
      payload.aplicar_a_lote = $('tm-lote').checked;
    } else {
      payload.destinatarios = seleccionados();
      if (!payload.destinatarios.length) {
        markFieldError('tm-dest');
        window.tkToast('Elige al menos un destinatario', 'error'); return;
      }
    }

    const btn = $('tkModalSave');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res  = await fetch('../api/save_tarea.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!data.success) { window.tkToast(data.error || 'No se pudo guardar', 'error'); return; }
      window.tkToast(payload.id
        ? (data.afectadas > 1 ? `Actualizadas ${data.afectadas} tareas del lote` : 'Tarea actualizada')
        : `Se ${data.creadas === 1 ? 'creó 1 tarea' : 'crearon ' + data.creadas + ' tareas'}`);
      cerrar();
      window.tkRecargar();
    } catch (e) {
      window.tkToast('Error de red al guardar', 'error');
    } finally {
      btn.disabled = false; btn.textContent = payload.id ? 'Guardar cambios' : 'Crear tarea';
    }
  }

  $('btnNueva').addEventListener('click', () => abrir(null));
  $('tkModalClose').addEventListener('click', cerrar);
  $('tkModalCancel').addEventListener('click', cerrar);
  $('tkModalSave').addEventListener('click', guardar);
  $('tm-dest').addEventListener('change', actualizarHint);
  $('tm-priority-options').addEventListener('click', e => {
    const button = e.target.closest('[data-priority]');
    if (button) setPriority(button.dataset.priority);
  });
  ['tm-titulo', 'tm-fecha'].forEach(id => $(id).addEventListener('input', () => $(id).closest('.tk-field')?.classList.remove('is-invalid')));
  $('tm-dest').addEventListener('change', () => $('tm-dest').closest('.tk-field')?.classList.remove('is-invalid'));
  $('tkModalBack').addEventListener('click', e => { if (e.target.id === 'tkModalBack') cerrar(); });

  window.tkAbrirEdicion = abrir;
})();
</script>
<?php endif; ?>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const esc = window.tkEsc, fmt = window.tkFmt;
  let T = null;
  const ICO_CLIP = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>';
  const ICO_UPLOAD = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 8l5-5 5 5"/><path d="M5 21h14a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2"/></svg>';
  const ICO_TASK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg>';

  function detailSummaryHtml(t) {
    const priorities = { baja: 'Baja', media: 'Media', alta: 'Alta' };
    return `<div class="tk-detail-summary">
      <div class="tk-summary-status"><span class="tk-summary-label">Estado</span>${window.tkBadgeEstado(t)}</div>
      <div><span class="tk-summary-label">Prioridad</span><strong>${esc(priorities[t.prioridad] || t.prioridad)}</strong></div>
      <div class="${t.atrasada ? 'is-danger' : ''}"><span class="tk-summary-label">Plazo vigente</span><strong>${fmt(t.plazo_vigente)}</strong></div>
      <div><span class="tk-summary-label">Envíos</span><strong>${Number(t.entregas_count) || 0}</strong></div>
    </div>`;
  }

  async function abrirDetalle(id) {
    try {
      const res  = await fetch('../api/get_tarea.php?id=' + id, { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) { window.tkToast(data.error || 'No se pudo abrir', 'error'); return; }
      T = data.data;
      pintar();
      $('tkDetBack').classList.add('open');
    } catch (e) { window.tkToast('Error de red', 'error'); }
  }
  function cerrar() { $('tkDetBack').classList.remove('open'); T = null; }

  function pintar() {
    $('tkDetTitle').textContent = T.titulo;
    $('tkDetSub').textContent   =
      `${T.asignado_nombre} · ${T.asignado_rol_label} · creada por ${T.creado_por}`;

    const ronda = T.entregas_count + 1;
    let html = detailSummaryHtml(T);

    if (T.estado === 'observada' && T.comentario_admin) {
      html += `<div class="tk-obs">
        <h5>Devuelta con observaciones${T.nota ? ' · nota ' + T.nota : ''}</h5>
        <p>${esc(T.comentario_admin)}</p>
        <div class="tk-sub" style="margin-top:8px">Por ${esc(T.revisado_por || '—')} el ${fmt(T.revisado_at)}</div>
      </div>`;
    }

    html += `<div class="tk-sec">
      <h5>${ICO_TASK}Encargo</h5>
      ${T.descripcion ? `<p style="margin:0 0 12px;font-size:13px;white-space:pre-wrap">${esc(T.descripcion)}</p>`
                      : `<p style="margin:0 0 12px;font-size:13px;color:var(--co-faint)">Sin descripción.</p>`}
      <div class="tk-meta">
        <div><div class="k">1ª fecha</div><div class="v">${fmt(T.fecha_limite)}</div></div>
        <div><div class="k">2ª fecha</div><div class="v">${T.fecha_limite_2 ? fmt(T.fecha_limite_2) : '—'}</div></div>
      </div>
      ${T.prorroga_motivo ? `<div class="tk-sub" style="margin-top:10px">Motivo de la prórroga: ${esc(T.prorroga_motivo)} — ${esc(T.prorroga_por || '')}</div>` : ''}
    </div>`;

    const material = T.adjuntos.filter(a => a.origen === 'admin');
    if (material.length) {
      html += `<div class="tk-sec"><h5>${ICO_CLIP}Material de referencia</h5>
        <div class="tk-files">${material.map(fileHtml).join('')}</div></div>`;
    }

    const evid = T.adjuntos.filter(a => a.origen === 'asignado');
    html += T.permisos.entregar
      ? `<div class="tk-delivery-panel"><div class="tk-delivery-title"><span>${ICO_UPLOAD}</span>Preparar entrega</div>`
      : `<div class="tk-sec"><h5>${ICO_CLIP}Evidencia de la entrega</h5>`;
    if (evid.length) {
      const rondas = [...new Set(evid.map(a => a.entrega_nro))].sort();
      rondas.forEach(r => {
        if (rondas.length > 1) html += `<div class="tk-ronda">Envío n.º ${r}</div>`;
        html += `<div class="tk-files">${evid.filter(a => a.entrega_nro === r).map(fileHtml).join('')}</div>`;
      });
    } else {
      html += `<p style="margin:0;font-size:13px;color:var(--co-faint)">Todavía no hay archivos.</p>`;
    }

    if (T.permisos.entregar) {
      html += `
        <div class="tk-ronda" style="margin-top:14px">Añadir a este envío (n.º ${ronda})</div>
        <div class="tk-drop" id="tkDrop" role="button" tabindex="0" aria-label="Seleccionar archivos para la entrega">
          <span class="tk-drop-icon">${ICO_UPLOAD}</span>
          <strong>Arrastra tus archivos o haz clic para elegirlos</strong>
          <span class="tk-sub">Máximo 4 MB por archivo y hasta 10 archivos por tarea.</span>
        </div>
        <input type="file" id="tkFileInput" multiple style="display:none">
        <div class="tk-field" style="margin-top:16px">
          <label for="tkComent">Comentario de entrega</label>
          <textarea id="tkComent" placeholder="Resume qué estás entregando y cualquier dato importante.">${esc(T.entrega_comentario || '')}</textarea>
          <span class="hint">Si no adjuntas archivos, el comentario es obligatorio.</span>
        </div>
      </div>`;
    } else if (T.entrega_comentario) {
      html += `</div>`;
      html += `<div class="tk-sec"><h5>Comentario de la entrega</h5>
        <p style="margin:0;font-size:13px;white-space:pre-wrap">${esc(T.entrega_comentario)}</p>
        <div class="tk-sub" style="margin-top:8px">Enviado el ${fmt(T.enviado_at)}${T.entregada_tarde ? ' · <span style="color:var(--er);font-weight:700">FUERA DE PLAZO</span>' : ''}</div>
      </div>`;
    } else {
      html += `</div>`;
    }

    html += `<div id="tkRevSlot"></div>`;
    $('tkDetBody').innerHTML = html;

    let secondary = '';
    if (T.permisos.editar)   secondary += `<button class="tk-btn" id="tkDetEditar">Editar</button>`;
    if (T.permisos.eliminar) secondary += `<button class="tk-btn danger" id="tkDetBorrar">Eliminar</button>`;
    secondary += `<button class="tk-btn" id="tkDetCerrar">Cerrar</button>`;
    let primary = '';
    if (T.permisos.entregar) {
      primary = `<button class="tk-btn primary" id="tkDetEnviar">${T.estado === 'observada' ? 'Reenviar entrega' : 'Enviar entrega'}</button>`;
    }
    $('tkDetFoot').innerHTML = `<div class="tk-footer-secondary">${secondary}</div>${primary ? `<div class="tk-footer-primary">${primary}</div>` : ''}`;

    conectar();
    if (window.tkPintarRevision) window.tkPintarRevision(T);
  }

  function fileHtml(a) {
    const puedeBorrar = (a.subido_por_id === <?= $USER_ID ?> && T.es_abierta) || <?= $ES_ADMIN ? 'true' : 'false' ?>;
    const verUrl = a.drive_url ? a.drive_url : (a.ruta_local ? `../api/ver_tarea_adjunto.php?id=${a.id}` : null);
    const enlace = verUrl
      ? `<a href="${esc(verUrl)}" target="_blank" rel="noopener" class="nm">${esc(a.nombre_archivo)}</a>`
      : `<span class="nm">${esc(a.nombre_archivo)}</span>`;
    return `<div class="tk-file">
      <span class="tk-file-icon" aria-hidden="true">${ICO_CLIP}</span>
      ${enlace}
      ${a.estado !== 'subido' ? `<span class="st ${esc(a.estado)}">${a.estado === 'pendiente' ? 'EN EL SERVIDOR' : 'ERROR'}</span>` : ''}
      <span class="tk-sub">${Math.round(a.peso_bytes / 1024)} KB</span>
      ${puedeBorrar ? `<button class="del" data-borrar-adj="${a.id}" title="Quitar" aria-label="Quitar ${esc(a.nombre_archivo)}">&times;</button>` : ''}
    </div>`;
  }

  async function subir(files) {
    for (const f of files) {
      const fd = new FormData();
      fd.append('id', T.id);
      fd.append('origen', T.permisos.editar && !T.permisos.entregar ? 'admin' : 'asignado');
      fd.append('file', f);
      try {
        const res  = await fetch('../api/upload_tarea_file.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) { window.tkToast(data.error, 'error'); continue; }
        if (data.aviso) window.tkToast(data.aviso, 'error');
      } catch (e) { window.tkToast('Error de red al subir ' + f.name, 'error'); }
    }
    await abrirDetalle(T.id);
  }

  async function borrarAdjunto(adjId) {
    if (!confirm('¿Quitar este archivo de la tarea?')) return;
    try {
      const res  = await fetch('../api/delete_tarea_adjunto.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: adjId }),
      });
      const data = await res.json();
      if (!data.success) { window.tkToast(data.error, 'error'); return; }
      await abrirDetalle(T.id);
    } catch (e) { window.tkToast('Error de red', 'error'); }
  }

  async function enviar() {
    const btn = $('tkDetEnviar');
    btn.disabled = true; btn.textContent = 'Enviando…';
    try {
      const res  = await fetch('../api/enviar_tarea.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: T.id, comentario: ($('tkComent')?.value || '').trim() }),
      });
      const data = await res.json();
      if (!data.success) { window.tkToast(data.error, 'error'); return; }
      window.tkToast(data.entregada_tarde
        ? 'Entrega registrada, fuera de plazo'
        : 'Entrega enviada. Queda en revisión.');
      cerrar();
      window.tkRecargar();
    } catch (e) {
      window.tkToast('Error de red al enviar', 'error');
    } finally {
      btn.disabled = false;
    }
  }

  async function borrarTarea() {
    if (!confirm('¿Eliminar esta tarea? Se borran también su evidencia y su historial.')) return;
    const res  = await fetch('../api/delete_tarea.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: T.id }),
    });
    const data = await res.json();
    if (!data.success) { window.tkToast(data.error, 'error'); return; }
    window.tkToast('Tarea eliminada');
    cerrar();
    window.tkRecargar();
  }

  function conectar() {
    const drop = $('tkDrop'), input = $('tkFileInput');
    if (drop && input) {
      drop.addEventListener('click', () => input.click());
      drop.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
      });
      input.addEventListener('change', () => { if (input.files.length) subir([...input.files]); });
      ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => {
        e.preventDefault(); drop.classList.add('over');
      }));
      ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => {
        e.preventDefault(); drop.classList.remove('over');
      }));
      drop.addEventListener('drop', e => {
        if (e.dataTransfer.files.length) subir([...e.dataTransfer.files]);
      });
    }
    $('tkDetBody').querySelectorAll('[data-borrar-adj]').forEach(b =>
      b.addEventListener('click', () => borrarAdjunto(Number(b.dataset.borrarAdj))));

    $('tkDetCerrar').addEventListener('click', cerrar);
    if ($('tkDetEnviar')) $('tkDetEnviar').addEventListener('click', enviar);
    if ($('tkDetBorrar')) $('tkDetBorrar').addEventListener('click', borrarTarea);
    if ($('tkDetEditar')) $('tkDetEditar').addEventListener('click', () => {
      cerrar(); window.tkAbrirEdicion(T);
    });
  }

  $('tkDetClose').addEventListener('click', cerrar);
  $('tkDetBack').addEventListener('click', e => { if (e.target.id === 'tkDetBack') cerrar(); });

  window.abrirDetalle  = abrirDetalle;
  window.tkTareaActual = () => T;
  window.tkCerrarDet   = cerrar;
})();
</script>

<?php if ($ES_ADMIN): ?>
<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const esc = window.tkEsc, fmt = window.tkFmt;
  const ESCALA = <?= json_encode($JS_ESCALA, JSON_UNESCAPED_UNICODE) ?>;

  let T = null, veredicto = null, nota = null;

  const ICO_RELOJ = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
  const ICO_CHECKCLIP = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>';
  const ICO_ACTIVIDAD = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>';
  const ICO_CHECK   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
  const ICO_APROBAR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
  const ICO_OBSERVAR= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
  const ICO_RECHAZAR= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
  const TL_COLOR = { creada:'#64748b', editada:'#64748b', enviada:'#2563eb', observada:'#d97706',
    aprobada:'#047857', rechazada:'#dc2626', prorroga:'#7c3aed', prorroga_retirada:'#7c3aed',
    adjunto:'#64748b', adjunto_borrado:'#64748b' };

  function pintarRevision(tarea) {
    T = tarea; veredicto = null; nota = tarea.nota || null;
    const slot = $('tkRevSlot');
    if (!slot) return;

    let html = '';

    if (T.permisos.prorrogar) {
      html += `<div class="tk-sec">
        <h5>${ICO_RELOJ}Plazo · 2ª fecha</h5>
        <div class="tk-row2">
          <div class="tk-field">
            <label>Nueva fecha de entrega</label>
            <input id="tkP-fecha" type="datetime-local" value="${T.fecha_limite_2 ? String(T.fecha_limite_2).slice(0,16).replace(' ','T') : ''}">
            <span class="hint">Debe ser posterior al ${fmt(T.fecha_limite)}.</span>
          </div>
          <div class="tk-field">
            <label>Motivo</label>
            <input id="tkP-motivo" type="text" maxlength="255" value="${esc(T.prorroga_motivo || '')}" placeholder="Por qué se amplía el plazo">
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:10px">
          <button class="tk-btn" id="tkP-guardar">Conceder prórroga</button>
          ${T.fecha_limite_2 ? `<button class="tk-btn danger" id="tkP-retirar">Retirar prórroga</button>` : ''}
        </div>
      </div>`;
    }

    if (T.permisos.revisar) {
      html += `<div class="tk-sec">
        <h5>${ICO_CHECKCLIP}Revisión</h5>
        <div class="tk-ver" id="tkV">
          <button data-v="aprobada"><span class="ic">${ICO_APROBAR}</span>Aprobar<span class="d">Cierra la tarea</span></button>
          <button data-v="observada"><span class="ic">${ICO_OBSERVAR}</span>Observar<span class="d">Vuelve al asignado</span></button>
          <button data-v="rechazada"><span class="ic">${ICO_RECHAZAR}</span>Rechazar<span class="d">Cierra sin aprobar</span></button>
        </div>

        <div class="tk-field" style="margin-top:14px">
          <label>Nota</label>
          <div class="tk-notas" id="tkN">
            ${Object.entries(ESCALA).map(([k, v]) =>
              `<button data-n="${k}" class="${String(nota) === String(k) ? 'on' : ''}"><span class="n">${k}</span>${esc(v)}</button>`
            ).join('')}
          </div>
          <span class="hint">Obligatoria para aprobar y para rechazar. Opcional al observar.</span>
        </div>

        <div class="tk-field" style="margin-top:12px">
          <label>Comentario</label>
          <textarea id="tkC" placeholder="Qué estuvo bien, qué falta o por qué se rechaza.">${esc(T.comentario_admin || '')}</textarea>
          <span class="hint">Obligatorio al observar y al rechazar.</span>
        </div>

        <div id="tkV2" style="display:none;margin-top:12px" class="tk-row2">
          <div class="tk-field">
            <label>2ª fecha para el reenvío (opcional)</label>
            <input id="tkV2-fecha" type="datetime-local">
          </div>
          <div class="tk-field">
            <label>Motivo de la prórroga</label>
            <input id="tkV2-motivo" type="text" maxlength="255" placeholder="Obligatorio si pones 2ª fecha">
          </div>
        </div>

        <button class="tk-btn primary" id="tkV-guardar" style="margin-top:14px" disabled>Elige un veredicto</button>
      </div>`;
    } else if (T.revisado_at) {
      const tlc = TL_COLOR[T.estado] || '#64748b';
      html += `<div class="tk-resultado" style="border-left-color:${tlc}">
        <div class="top">
          ${window.tkBadgeEstado(T)}
          ${window.tkEstrellas(T.nota)}
        </div>
        <div class="quien">Revisó <b>${esc(T.revisado_por || '—')}</b> · ${fmt(T.revisado_at)}</div>
        ${T.comentario_admin ? `<div class="coment">${esc(T.comentario_admin)}</div>` : ''}
      </div>`;
    }

    if (T.historial && T.historial.length) {
      html += `<div class="tk-sec"><h5>${ICO_ACTIVIDAD}Historial</h5><div class="tk-tl">`
        + T.historial.map(h => `<div class="tk-tl-i" style="--tl-c:${TL_COLOR[h.accion] || '#64748b'}">
            <div class="ac">${esc(h.accion_label)}</div>
            <div class="dt">${fmt(h.created_at)} · ${esc(h.usuario_nombre || '—')} (${esc(h.usuario_rol_label || '—')})</div>
            ${h.detalle ? `<div class="de">${esc(h.detalle)}</div>` : ''}
          </div>`).join('')
        + `</div></div>`;
    }

    slot.innerHTML = html;
    conectar();
  }

  function conectar() {
    if ($('tkV')) {
      $('tkV').addEventListener('click', e => {
        const b = e.target.closest('button[data-v]'); if (!b) return;
        veredicto = b.dataset.v;
        $('tkV').querySelectorAll('button').forEach(x => {
          x.classList.remove('on');
          x.querySelector('.check')?.remove();
        });
        b.classList.add('on');
        b.insertAdjacentHTML('beforeend', '<span class="check">' + ICO_CHECK + '</span>');
        $('tkV2').style.display = (veredicto === 'observada') ? '' : 'none';
        const g = $('tkV-guardar');
        g.disabled = false;
        g.textContent = { aprobada: 'Aprobar tarea', observada: 'Devolver con observaciones',
                          rechazada: 'Rechazar tarea' }[veredicto];
      });
    }
    if ($('tkN')) {
      $('tkN').addEventListener('click', e => {
        const b = e.target.closest('button[data-n]'); if (!b) return;
        nota = (String(nota) === b.dataset.n) ? null : Number(b.dataset.n);
        $('tkN').querySelectorAll('button').forEach(x => x.classList.remove('on'));
        if (nota) b.classList.add('on');
      });
    }
    if ($('tkV-guardar')) $('tkV-guardar').addEventListener('click', revisar);
    if ($('tkP-guardar')) $('tkP-guardar').addEventListener('click', () => prorrogar(false));
    if ($('tkP-retirar')) $('tkP-retirar').addEventListener('click', () => prorrogar(true));
  }

  function normalizaFecha(v) {
    if (!v) return '';
    let f = v.replace('T', ' ');
    if (f.endsWith(' 00:00')) f = f.slice(0, 11) + '23:59';
    return f;
  }

  async function revisar() {
    const payload = {
      id: T.id, veredicto, nota,
      comentario: ($('tkC')?.value || '').trim(),
    };
    if (veredicto === 'observada') {
      const f = normalizaFecha($('tkV2-fecha')?.value || '');
      if (f) { payload.fecha_limite_2 = f; payload.prorroga_motivo = ($('tkV2-motivo')?.value || '').trim(); }
    }
    const btn = $('tkV-guardar');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res  = await fetch('../api/revisar_tarea.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!data.success) { window.tkToast(data.error, 'error'); btn.disabled = false; return; }
      window.tkToast({ aprobada: 'Tarea aprobada', observada: 'Devuelta al asignado',
                       rechazada: 'Tarea rechazada' }[veredicto]);
      window.tkCerrarDet();
      window.tkRecargar();
    } catch (e) {
      window.tkToast('Error de red', 'error'); btn.disabled = false;
    }
  }

  async function prorrogar(retirar) {
    const payload = retirar
      ? { id: T.id, fecha_limite_2: null }
      : { id: T.id,
          fecha_limite_2: normalizaFecha($('tkP-fecha')?.value || ''),
          motivo: ($('tkP-motivo')?.value || '').trim() };
    if (!retirar && !payload.fecha_limite_2) {
      window.tkToast('Indica la nueva fecha de entrega', 'error'); return;
    }
    try {
      const res  = await fetch('../api/prorrogar_tarea.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!data.success) { window.tkToast(data.error, 'error'); return; }
      window.tkToast(retirar ? 'Prórroga retirada' : 'Prórroga concedida');
      window.abrirDetalle(T.id);
      window.tkRecargar();
    } catch (e) { window.tkToast('Error de red', 'error'); }
  }

  window.tkPintarRevision = pintarRevision;
})();
</script>
<?php endif; ?>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const fmt = window.tkFmt;

  function filas() {
    return window.tkListaVisible().map(t => [
      t.titulo,
      t.asignado_nombre,
      t.asignado_rol_label,
      fmt(t.fecha_limite),
      t.fecha_limite_2 ? fmt(t.fecha_limite_2) : '',
      fmt(t.plazo_vigente),
      t.enviado_at ? fmt(t.enviado_at) : '',
      t.entregada_tarde ? 'Sí' : '',
      t.atrasada ? (t.dias_atraso > 0 ? t.dias_atraso + ' días' : 'Sí') : '',
      t.estado,
      t.nota ? t.nota + ' · ' + t.nota_label : '',
      t.entregas_count,
    ]);
  }

  const CABECERAS = ['Tarea','Asignado','Puesto','1ª fecha','2ª fecha','Plazo vigente',
                     'Entregado','Fuera de plazo','Atrasada','Estado','Nota','Envíos'];

  function excel() {
    const rows = filas();
    if (!rows.length) { window.tkToast('No hay filas que exportar', 'error'); return; }
    const q = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const csv = '﻿' + [CABECERAS, ...rows].map(r => r.map(q).join(';')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'tareas_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
  }

  function pdf() {
    const rows = filas();
    if (!rows.length) { window.tkToast('No hay filas que exportar', 'error'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

    if (window.tkLogo) { try { doc.addImage(window.tkLogo, 'PNG', 40, 24, 34, 34); } catch (e) {} }
    doc.setFontSize(15); doc.setTextColor(0, 92, 61);
    doc.text('Control de Tareas', 84, 42);
    doc.setFontSize(9); doc.setTextColor(120);
    doc.text('Generado el ' + new Date().toLocaleString('es-PE') + ' · ' + rows.length + ' tareas', 84, 56);

    doc.autoTable({
      head: [CABECERAS],
      body: rows,
      startY: 74,
      styles: { fontSize: 7.5, cellPadding: 4 },
      headStyles: { fillColor: [0, 135, 90], textColor: 255, fontStyle: 'bold' },
      alternateRowStyles: { fillColor: [245, 248, 247] },
      didParseCell: (d) => {
        if (d.section === 'body' && d.row.raw[8]) d.cell.styles.textColor = [220, 38, 38];
      },
    });

    doc.save('tareas_' + new Date().toISOString().slice(0, 10) + '.pdf');
  }

  $('btnExcel').addEventListener('click', excel);
  $('btnPdf').addEventListener('click', pdf);
})();
</script>
</body>
</html>
