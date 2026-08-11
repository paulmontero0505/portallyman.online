<?php
require_once('../includes/auth.php');
require_operaciones();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Relevo de turno · Tallyman</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css?v=navy1">
  <link rel="stylesheet" href="../css/sidebar.css?v=navy1">
  <link rel="stylesheet" href="../css/layout.css?v=navy1">
  <link rel="stylesheet" href="../css/ui.css?v=navy1">  <style>
    .rv-wrap {
      --navy:#001b3a; --navy2:#002b5c; --steel:#2563eb; --steeld:#001b3a; --mute:#4b5563;
      --line:rgba(0, 43, 92, 0.18); --deck:#f5f8f7; --track:#e2e8f0; --warn:#d97706; --ink:#111827;
      display:flex; flex-direction:column; gap:16px; font-family:'DM Sans',system-ui,sans-serif; color:var(--ink);
    }
    .rv-wrap *,.rv-wrap *::before,.rv-wrap *::after{box-sizing:border-box;}
    .rv-hero{background:linear-gradient(135deg, #001b3a 0%, #002b5c 100%) !important;color:#fff;border-radius:14px;
      padding:18px 24px;display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;border-bottom:3px solid var(--steel);box-shadow: 0 8px 32px rgba(0, 43, 92, 0.08) !important;}
    .rv-hero .co{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:rgba(255, 255, 255, 0.7);font-weight:700;}
    .rv-hero h1{margin:4px 0 4px;font-size:22px;font-weight:800;}
    .rv-meta{margin-top:8px;display:flex;gap:16px;flex-wrap:wrap;font-size:12.5px;color:rgba(255,255,255,.85);}
    .rv-meta b{color:#fff;}
    .rv-actions{display:flex;flex-direction:column;align-items:flex-end;gap:8px;}
    .rv-btn{display:inline-flex;align-items:center;gap:7px;cursor:pointer;padding:11px 18px;border-radius:9px;border:none !important;
      background:linear-gradient(135deg, #002b5c 0%, #001b3a 100%) !important;color:#fff !important;font:inherit;font-size:13px;font-weight:700;box-shadow: 0 4px 15px rgba(0, 43, 92, 0.2) !important;transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;}
    .rv-btn:hover{transform: translateY(-1.5px) !important;box-shadow: 0 6px 20px rgba(0, 43, 92, 0.35) !important;filter: brightness(1.1) !important;}
    .rv-btn:disabled{background: #f3f4f6 !important; border: 1px solid #d1d5db !important; color: #9ca3af !important; box-shadow: none !important; cursor: not-allowed !important; transform: none !important;}
    .rv-geninfo{font-size:11.5px;color:rgba(255,255,255,.8);text-align:right;}
    .rv-filtro{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px;}
    .rv-filtro-input,.rv-filtro-sel{background:rgba(255,255,255,.15) !important;border:1px solid rgba(255,255,255,0.25) !important;color:#fff !important;border-radius:8px;padding:6px 10px;font:inherit;font-size:12px;cursor:pointer;outline:none;}
    .rv-filtro-input::-webkit-calendar-picker-indicator{filter:invert(1);opacity:.8;cursor:pointer;}
    .rv-filtro-sel option{background:#001b3a;color:#fff;}
    .rv-filtro-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.15);color:#fff;font:inherit;font-size:12px;font-weight:600;cursor:pointer;}
    .rv-filtro-btn:hover{background:rgba(255,255,255,.25);}
    .rv-readonly{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.35);font-size:11px;font-weight:700;color:#d97706;}
    
    .rv-card{background:#fff !important;border:1px solid var(--line) !important;border-radius:16px;padding:16px 18px;box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;}
    .rv-card h2{margin:0 0 12px;font-size:13px;font-weight:800;color:var(--navy) !important;text-transform:uppercase;letter-spacing:.05em;
      border-bottom:1px solid var(--line) !important;padding-bottom:8px;display:flex;align-items:center;gap:7px;}
    .rv-card h2::before{content:"";width:3px;height:13px;background:var(--navy2) !important;box-shadow:none !important;border-radius:1px;}
    .rv-grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    @media(max-width:920px){.rv-grid2{grid-template-columns:1fr;}}
    #rvObs{width:100%;border:1px solid var(--line) !important;border-radius:8px;padding:10px 12px;font:inherit;font-size:13px;resize:vertical;}
    
    /* barra única por muelle */
    .rvm{margin-bottom:13px;}
    .rvm:last-child{margin-bottom:0;}
    .rvm .h{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;}
    .rvm .nm{font-size:13px;font-weight:800;color:var(--navy) !important;}
    .rvm .nm small{font-size:11px;color:var(--mute) !important;font-weight:600;margin-left:6px;}
    .rvm .pc{font-size:14px;font-weight:800;color:var(--navy) !important;}
    .rvm .pc.low{color:var(--warn) !important;}
    .rvseg{height:22px;border-radius:5px;overflow:hidden;display:flex;background:#e2e8f0 !important;border:1px solid rgba(0, 0, 0, 0.1) !important;}
    .rvseg span{display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;}
    .rvseg .pend{color:var(--mute) !important;}
    .rvm .cap{display:flex;justify-content:space-between;margin-top:5px;font-size:11px;}
    .rvm .cap .a{color:var(--navy);font-weight:800;}
    .rvm .cap .tt{color:var(--mute);font-weight:600;}
    .rv-leg{display:flex;gap:14px;font-size:10.5px;color:var(--mute) !important;font-weight:700;margin-bottom:10px;}
    .rv-leg i{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:4px;vertical-align:middle;}
    
    .rv-table{width:100%;border-collapse:collapse;font-size:12px;}
    .rv-table th{background:rgba(0, 43, 92,0.06) !important;color:var(--navy) !important;text-align:left;padding:7px 9px;font-size:9.5px;text-transform:uppercase;letter-spacing:.04em;border-bottom: 2px solid var(--line) !important;}
    .rv-table th.num,.rv-table td.num{text-align:right;}
    .rv-table th.c,.rv-table td.c{text-align:center;}
    .rv-table td{padding:6px 9px;border-bottom:1px solid var(--line) !important;color:var(--ink) !important;}
    .rv-table tr:nth-child(even) td{background:#f9fafb !important;}
    .rv-table tr:hover td{background:rgba(0, 43, 92,0.02) !important;}
    .rv-table .ubic{font-weight:800;color:var(--navy) !important;text-align:center;}
    
    .rv-tag{display:inline-block;min-width:20px;text-align:center;font-weight:800;border-radius:5px;padding:2px 8px;font-size:11px;}
    .rv-tag.per{background:rgba(0, 43, 92, 0.08) !important;color:var(--steeld) !important;border:1px solid rgba(0, 43, 92, 0.25) !important;}
    .rv-tag.rad{background:rgba(124, 58, 237, 0.08) !important;color:#7c3aed !important;border:1px solid rgba(124, 58, 237, 0.25) !important;}
    .rv-yesno{font-size:11px;font-weight:700;padding:2px 9px;border-radius:5px;background:rgba(16, 185, 129, 0.08) !important;color:#059669 !important;border:1px solid rgba(16, 185, 129, 0.25) !important;}
    
    .rv-inc{border:1px solid var(--line) !important;border-left:4px solid var(--navy2) !important;background:#f9fafb !important;border-radius:10px !important;padding:12px 15px !important;margin-bottom:8px;}
    .rv-inc:last-child{margin-bottom:0;}
    .rv-inc .ti{font-size:12.5px;font-weight:800;color:var(--navy) !important;}
    .rv-inc .who{font-size:11.5px;margin:3px 0;color:var(--ink) !important;}
    .rv-inc .who b{color:var(--navy2) !important;}
    .rv-inc .who .z{color:var(--mute) !important;font-weight:600;}
    .rv-inc .dt{font-size:11.5px;color:var(--mute) !important;}
    .rv-empty{padding:18px;text-align:center;color:var(--mute) !important;font-size:12.5px;background:transparent !important;border:none !important;}
    .rvm-obs{margin-top:4px;font-size:11px;color:var(--mute) !important;font-style:italic;padding-left:1px;}
    .content{padding:24px 28px 60px;overflow-y:auto;}

    /* Observación de Relevo overrides for light theme */
    #rvObsDisplay {
      border-top: 1px solid var(--line) !important;
    }
    #rvObsDisplay > div:first-child {
      color: var(--navy2) !important;
    }
    #rvObsTxt {
      color: var(--ink) !important;
    }
    #rvObsMeta {
      color: var(--mute) !important;
    }
    .rv-wrap .rv-empty {
      background: #f9fafb !important;
      border: 1px dashed var(--line) !important;
      color: var(--mute) !important;
      padding: 30px 20px !important;
      border-radius: 12px !important;
    }
  </style>
</head>
<body>
<div class="overlay" id="overlay"></div>
<div class="shell">
  <?php $sb_base='..'; include('../includes/sidebar.php'); ?>
  <div class="main-area">
    <?php include('../includes/header.php'); ?>
    <main class="content">
      <div class="rv-wrap" id="rvWrap">
        <section class="rv-hero">
          <div>
            <div class="co">Terminal Portuario</div>
            <h1>Relevo de Turno</h1>
            <div class="rv-meta" id="rvMeta">Cargando…</div>
            <div class="rv-filtro">
              <input type="date" id="rvFechaFiltro" class="rv-filtro-input" title="Fecha del turno">
              <select id="rvJornadaFiltro" class="rv-filtro-sel" title="Jornada"></select>
              <button id="rvHoyBtn" class="rv-filtro-btn" style="display:none" title="Ver turno actual">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Hoy
              </button>
              <span class="rv-readonly" id="rvReadonly" style="display:none">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Consulta
              </span>
            </div>
          </div>
          <div class="rv-actions">
            <button class="rv-btn" id="rvGenerar" disabled>
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              <span id="rvGenerarLabel">Generar relevo + PDF</span>
            </button>
            <a href="https://drive.google.com/drive/folders/1XTySfr5IC-38iZW6OCabICaTCMOXgqlV" target="_blank" id="rvDriveBtn"
               style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.35);color:#fff;font:inherit;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;transition:background 0.2s">
              <svg width="14" height="14" viewBox="0 0 87.3 78" fill="currentColor"><path d="M6.6 66.85l3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/><path d="M43.65 25L29.9 1.2C28.55 2 27.4 3.1 26.6 4.5L1.2 48.5C.4 49.9 0 51.45 0 53h27.5z" fill="#00ac47"/><path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H59.8l5.85 11.2z" fill="#ea4335"/><path d="M43.65 25L57.4 1.2C56.05.4 54.5 0 52.9 0H34.4c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/><path d="M59.8 53H27.5L13.75 76.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/><path d="M73.4 26.5l-12.65-21.9c-.8-1.4-1.95-2.5-3.3-3.3L43.65 25 59.8 53h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/></svg>
              Files en Drive
            </a>
            <div class="rv-geninfo" id="rvGenInfo"></div>
          </div>
        </section>

        <textarea id="rvObs" style="display:none" aria-hidden="true"></textarea>

        <div class="rv-grid2">
          <section class="rv-card">
            <h2>Avance por muelle</h2>
            <div class="rv-leg">
              <span><i style="background:#059669"></i>Accumulated</span>
              <span><i style="background:#10b981"></i>In This Shift</span>
              <span><i style="background:#e2e8f0;border:1px solid #cbd5e1"></i>Pending · bar = Total</span>
            </div>
            <div id="rvMuelles"><div class="rv-empty">Cargando…</div></div>
          </section>
          <section class="rv-card">
            <h2>Actividad de patio · carga</h2>
            <div id="rvPatio"><div class="rv-empty">Cargando…</div></div>
          </section>
        </div>

        <div class="rv-grid2">
          <section class="rv-card">
            <h2>Personal y radios</h2>
            <div id="rvPersonal"><div class="rv-empty">Cargando…</div></div>
          </section>
          <section class="rv-card">
            <h2>Incidencias (módulo)</h2>
            <div id="rvInc"><div class="rv-empty">Cargando…</div></div>
            <div id="rvObsDisplay" style="display:none;margin-top:14px;padding-top:12px;border-top:1px solid rgba(0, 43, 92,.15);">
              <div style="font-size:9.5px;letter-spacing:.16em;text-transform:uppercase;color:#002b5c;font-weight:700;margin-bottom:8px;">◗ Observación del turno</div>
              <div id="rvObsTxt" style="font-size:13px;color:#d1d9e8;line-height:1.55;white-space:pre-wrap;"></div>
              <div id="rvObsMeta" style="font-size:11px;color:#5a6e8b;margin-top:6px;"></div>
            </div>
          </section>
        </div>
      </div>
    </main>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.0/jspdf.plugin.autotable.min.js"></script>
<script src="../js/operaciones.js?v=20260608b"></script>
<script>
<?php require_once('../includes/sheets_config.php'); ?>
window.RELEVO_CTX = {
  userName:      <?= json_encode($_SESSION['user_name'] ?? 'Usuario') ?>,
  sheetsUrl:     <?= json_encode(defined('SHEETS_WEBAPP_URL') ? SHEETS_WEBAPP_URL : '') ?>,
  sheetsToken:   <?= json_encode(defined('SHEETS_TOKEN')      ? SHEETS_TOKEN      : '') ?>,
  driveFolderId: '1XTySfr5IC-38iZW6OCabICaTCMOXgqlV'
};
</script>
<script src="../js/tallyman_relevo.js?v=20260708c"></script>
</body>
</html>
