<?php
require_once('../includes/auth.php');
require_operaciones();
$rol = $_SESSION['user_rol'] ?? '';
$canRegistrar = in_array($rol, ['Administrador', 'Supervisor', 'Coordinador'], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Registro de actividad · Tallyman</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css?v=navy1">
  <link rel="stylesheet" href="../css/sidebar.css?v=navy1">
  <link rel="stylesheet" href="../css/layout.css?v=navy1">
  <link rel="stylesheet" href="../css/ui.css?v=navy1">
  <link rel="stylesheet" href="../css/operaciones.css?v=navy1">  <style>
    .tm-wrap {
      --navy:#001b3a; --navy7:#002b5c; --deck:#f5f8f7; --line:rgba(0, 43, 92, 0.18); --lineb:rgba(0, 43, 92, 0.3);
      --ink:#111827; --mute:#4b5563; --faint:#9ca3af; --mono:ui-monospace,Consolas,monospace;
      display:flex; flex-direction:column; gap:18px; font-family:'DM Sans',system-ui,sans-serif; color:var(--ink);
    }
    .tm-wrap *,.tm-wrap *::before,.tm-wrap *::after{box-sizing:border-box;}
    .tm-hero{background:linear-gradient(135deg, #001b3a 0%, #002b5c 100%) !important;color:#fff;border-radius:20px;padding:22px 28px;border: 1px solid rgba(0, 43, 92, 0.2) !important;box-shadow: 0 8px 32px rgba(0, 43, 92, 0.08) !important;}
    .tm-hero h1{margin:6px 0 4px;font-size:22px;font-weight:700;}
    .tm-hero p{margin:0;color:rgba(255,255,255,.85);font-size:13px;max-width:680px;}
    .tm-turno{display:inline-flex;gap:8px;align-items:center;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);font-size:12px;font-weight:600;}
    .tm-hero-row{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-top:10px;}
    .tm-filtro{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .tm-filtro-input,.tm-filtro-sel{background:rgba(255,255,255,.15) !important;border:1px solid rgba(255,255,255,.25) !important;color:#fff !important;border-radius:8px;padding:6px 10px;font:inherit;font-size:12px;cursor:pointer;outline:none;}
    .tm-filtro-input::-webkit-calendar-picker-indicator{filter:invert(1);opacity:.8;cursor:pointer;}
    .tm-filtro-sel option{background:#001b3a;color:#fff;}
    .tm-filtro-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.15);color:#fff;font:inherit;font-size:12px;font-weight:600;cursor:pointer;}
    .tm-filtro-btn:hover{background:rgba(255,255,255,.25);}
    .tm-readonly{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.35);font-size:11px;font-weight:700;color:#d97706;}
    
    .tm-card{background:#fff !important;border:1px solid var(--line) !important;border-radius:16px;padding:18px 20px;box-shadow: 0 4px 16px rgba(0,0,0,.02) !important;}
    .tm-card-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap;}
    .tm-card-head h2{margin:0;font-size:15px;font-weight:700;color:var(--navy) !important;display:flex;align-items:center;gap:10px;}
    .tm-card-head .ic{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;color:#fff;background:linear-gradient(135deg,#002b5c,#001b3a) !important;border: 1px solid rgba(0, 43, 92, 0.2) !important;flex-shrink:0;box-shadow: 0 2px 8px rgba(0, 43, 92, 0.15) !important;}
    .tm-card-head .ic svg{width:18px;height:18px;}
    .tm-add{display:inline-flex;align-items:center;gap:7px;cursor:pointer;padding:9px 15px;border-radius:10px;border:none !important;background:linear-gradient(135deg, #002b5c 0%, #001b3a 100%) !important;color:#fff !important;font:inherit;font-size:13px;font-weight:700 !important;box-shadow: 0 4px 15px rgba(0, 43, 92, 0.2) !important;transition:all 0.2s ease;}
    .tm-add:hover{transform: translateY(-1.5px) !important;box-shadow: 0 6px 20px rgba(0, 43, 92, 0.35) !important;filter: brightness(1.1) !important;}
    .tm-add:disabled{opacity:.5;cursor:not-allowed;}
    .tm-items{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:12px;}
    .tm-empty{padding:22px;text-align:center;color:var(--faint);font-size:13px;border:1.5px dashed var(--lineb);border-radius:12px;}
    .tm-item{border:1px solid var(--line) !important;border-radius:13px;padding:14px;background:#ffffff !important;display:flex;flex-direction:column;gap:9px;transition:border-color .18s,box-shadow .18s,transform .18s;box-shadow:0 2px 8px rgba(0,0,0,0.01) !important;}
    .tm-item:hover{border-color:var(--lineb) !important;box-shadow:0 4px 18px rgba(0, 43, 92, 0.08) !important;transform:translateY(-2px) !important;}
    .tm-item-top{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;}
    .tm-item-loc{font-weight:700;font-size:13.5px;color:var(--ink) !important;}
    .tm-item-act{font-size:12.5px;color:var(--mute) !important;margin-top:2px;}
    .tm-chip{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:3px 8px;border-radius:999px;white-space:nowrap;}
    .tm-chip.s0{background:#eff6ff;color:#1d4ed8;}
    .tm-chip.s1{background:#fef9c3;color:#a16207;}
    .tm-chip.s2{background:#dcfce7;color:#15803d;}
    .tm-nums{font-size:11.5px;color:var(--mute) !important;font-family:var(--mono);}
    .tm-bar{height:7px;border-radius:999px;background:#f3f4f6 !important;overflow:hidden;}
    .tm-bar > i{display:block;height:100%;background:linear-gradient(90deg,#002b5c,#2563eb) !important;}
    .tm-item-actions{display:flex;justify-content:flex-end;gap:6px;margin-top:1px;}
    .tm-iconbtn{cursor:pointer;border:1px solid var(--lineb) !important;background:#fff !important;border-radius:8px;padding:5px 9px;font-size:12px;font-weight:600;color:var(--mute) !important;display:inline-flex;align-items:center;gap:5px;}
    .tm-iconbtn:hover{background:#f3f4f6 !important;color:var(--navy) !important;}
    .tm-iconbtn.del:hover{color:#dc2626 !important;border-color:#fca5a5 !important;background:#fef2f2 !important;}
    .tm-calc{display:flex;gap:16px;flex-wrap:wrap;margin-top:2px;padding:10px 12px;border-radius:10px;background:#f5f8f7 !important;border:1.5px solid var(--lineb) !important;font-size:12.5px;grid-column:1/-1;color: var(--ink) !important;}
    .tm-calc b{font-family:var(--mono);color:var(--navy) !important;}
    .content{padding:24px 28px 60px;overflow-y:auto;}

    /* Secondary buttons */
    body .tm-wrap #tmObsBtn {
      background: linear-gradient(135deg, #2563eb 0%, #002b5c 100%) !important;
      border: 1px solid rgba(255, 255, 255, 0.25) !important;
      color: #ffffff !important;
      border-radius: 10px !important;
      font-weight: 700 !important;
      transition: all 0.2s ease !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 5px !important;
      cursor: pointer !important;
      box-shadow: 0 4px 12px rgba(0, 43, 92, 0.15) !important;
    }
    body .tm-wrap #tmObsBtn:hover {
      background: linear-gradient(135deg, #002b5c 0%, #001b3a 100%) !important;
      transform: translateY(-1px) !important;
      box-shadow: 0 6px 18px rgba(0, 43, 92, 0.3) !important;
      color: #ffffff !important;
    }

    body .tm-wrap #tmHoyBtn {
      background: rgba(0, 43, 92, 0.05) !important;
      border: 1px solid rgba(0, 43, 92, 0.25) !important;
      color: #002b5c !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      transition: all 0.2s ease !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 5px !important;
      cursor: pointer !important;
    }
    body .tm-wrap #tmHoyBtn:hover {
      background: #002b5c !important;
      color: #ffffff !important;
      box-shadow: 0 2px 8px rgba(0, 43, 92, 0.2) !important;
    }

    /* Modal dialog (op-dialog / op-modal) */
    body .op-dialog {
      background-color: #ffffff !important;
      border: 1px solid var(--line) !important;
      box-shadow: 0 24px 64px rgba(0, 43, 92, 0.15) !important;
      border-radius: 18px !important;
    }
    body .op-dialog-foot {
      border-top: 1px solid rgba(0, 43, 92, 0.08) !important;
      background-color: #ffffff !important;
    }
    body .op-dialog .op-btn {
      background: #f3f4f6 !important;
      border: 1px solid #d1d5db !important;
      color: #4b5563 !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      padding: 10px 18px !important;
      cursor: pointer !important;
      transition: all 0.2s ease !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 7px !important;
    }
    body .op-dialog .op-btn:hover {
      background: #e5e7eb !important;
      color: #111827 !important;
    }
    body .op-dialog .op-btn.primary {
      background: linear-gradient(135deg, #002b5c 0%, #001b3a 100%) !important;
      border: none !important;
      color: #ffffff !important;
      box-shadow: 0 4px 15px rgba(0, 43, 92, 0.2) !important;
    }
    body .op-dialog .op-btn.primary:hover {
      transform: translateY(-1.5px) !important;
      box-shadow: 0 6px 20px rgba(0, 43, 92, 0.35) !important;
      filter: brightness(1.08) !important;
    }
    body .op-dialog .op-x {
      background: rgba(0,0,0,0.05) !important;
      border: 1px solid rgba(0,0,0,0.1) !important;
      color: #4b5563 !important;
      transition: all 0.2s ease !important;
    }
    body .op-dialog .op-x:hover {
      background: rgba(239, 68, 68, 0.1) !important;
      border-color: rgba(239, 68, 68, 0.3) !important;
      color: #dc2626 !important;
    }

    /* Form Fields inside the Modal */
    body .op-dialog-body input,
    body .op-dialog-body select,
    body .op-dialog-body textarea,
    body .op-dialog-body .op-control {
      background-color: #ffffff !important;
      border: 1.5px solid #cbd5e1 !important;
      color: #111827 !important;
      border-radius: 10px !important;
      padding: 10px 14px !important;
      outline: none !important;
      transition: all 0.2s ease !important;
      box-shadow: none !important;
      font-weight: 500 !important;
    }
    body .op-dialog-body input::placeholder,
    body .op-dialog-body textarea::placeholder {
      color: #94a3b8 !important;
    }
    body .op-dialog-body select option {
      background: #ffffff !important;
      color: #111827 !important;
    }
    body .op-dialog-body input:focus,
    body .op-dialog-body select:focus,
    body .op-dialog-body textarea:focus,
    body .op-dialog-body .op-control:focus {
      border-color: #002b5c !important;
      background-color: #fff !important;
      box-shadow: 0 0 0 3px rgba(0, 43, 92, 0.15) !important;
    }
    body .op-dialog-body label {
      color: #374151 !important;
      font-weight: 700 !important;
      font-size: 11px !important;
      text-transform: uppercase !important;
      letter-spacing: 0.06em !important;
      margin-bottom: 6px;
      display: inline-block;
    }

    /* Observación Modal overrides for light theme */
    #tmObsModal {
      background: rgba(0, 0, 0, 0.3) !important;
    }
    #tmObsModal > div {
      background: #ffffff !important;
      border: 1px solid var(--line) !important;
      box-shadow: 0 24px 64px rgba(0, 43, 92, 0.15) !important;
    }
    #tmObsModal h3 {
      color: #111827 !important;
    }
    #tmObsModal p {
      color: #4b5563 !important;
    }
    #tmObsModal textarea {
      background: #ffffff !important;
      border: 1.5px solid #cbd5e1 !important;
      color: #111827 !important;
    }
    #tmObsModal textarea::placeholder {
      color: #94a3b8 !important;
    }
    #tmObsModal button#tmObsClose {
      border: 1px solid #cbd5e1 !important;
      color: #4b5563 !important;
    }
    #tmObsModal button#tmObsClose:hover {
      background: #f3f4f6 !important;
    }
    #tmObsModal button#tmObsCancelBtn {
      background: #f3f4f6 !important;
      border: 1px solid #d1d5db !important;
      color: #4b5563 !important;
    }
    #tmObsModal button#tmObsCancelBtn:hover {
      background: #e5e7eb !important;
    }
    #tmObsModal button#tmObsSaveBtn {
      background: linear-gradient(135deg, #002b5c 0%, #001b3a 100%) !important;
      color: #ffffff !important;
      box-shadow: 0 4px 15px rgba(0, 43, 92, 0.2) !important;
    }
    #tmObsModal button#tmObsSaveBtn:hover {
      filter: brightness(1.08) !important;
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
      <div class="tm-wrap">
        <section class="tm-hero">
          <h1>Registro de actividad de turno</h1>
          <p>Registra lo realizado en tu turno por muelle y patio. El total planeado se hereda de la nave; tú solo ingresas lo ejecutado.</p>
          <div class="tm-hero-row">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <span class="tm-turno" id="tmTurno">Cargando turno…</span>
              <span class="tm-readonly" id="tmReadonly" style="display:none">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Solo lectura
              </span>
              <button id="tmObsBtn" class="tm-filtro-btn" title="Observación para el relevo de turno">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Añadir observación para relevo
              </button>
            </div>
            <div class="tm-filtro" id="tmFiltro">
              <?php if ($rol === 'Administrador'): ?>
              <select id="tmModoFiltro" class="tm-filtro-sel" title="Modo de vista">
                <option value="turno">Por turno</option>
                <option value="dia">Por día</option>
                <option value="semana">Por semana</option>
                <option value="mes">Por mes</option>
                <option value="todos">Todos</option>
              </select>
              <input type="date" id="tmDiaInput" class="tm-filtro-input" style="display:none" title="Día">
              <input type="week" id="tmSemanaInput" class="tm-filtro-input" style="display:none" title="Semana">
              <input type="month" id="tmMesInput" class="tm-filtro-input" style="display:none" title="Mes">
              <?php endif; ?>
              <input type="date" id="tmFechaFiltro" class="tm-filtro-input" title="Fecha del turno">
              <select id="tmJornadaFiltro" class="tm-filtro-sel" title="Jornada"></select>
              <button id="tmHoyBtn" class="tm-filtro-btn" style="display:none" title="Ver turno actual">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Hoy
              </button>
            </div>
          </div>
        </section>

        <section class="tm-card">
          <div class="tm-card-head">
            <h2><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 22h18"/><path d="M5 22V10l7-5 7 5v12"/><path d="M9 22v-6h6v6"/></svg></span> Actividad en Muelle (Berth)</h2>
            <button class="tm-add" id="addBerth" <?= $canRegistrar?'':'disabled' ?>>
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Añadir actividad de muelle
            </button>
          </div>
          <div class="tm-items" id="tmBerthList"><div class="tm-empty">Cargando…</div></div>
        </section>

        <section class="tm-card">
          <div class="tm-card-head">
            <h2><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></span> Actividad en Patio (Yard)</h2>
            <button class="tm-add" id="addYard" <?= $canRegistrar?'':'disabled' ?>>
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Añadir actividad de patio
            </button>
          </div>
          <div class="tm-items" id="tmYardList"><div class="tm-empty">Cargando…</div></div>
        </section>

        <?php if ($rol === 'Administrador'): ?>
        <style>
          .tm-rango-wrap{overflow-x:auto;}
          table.tm-rango-table{width:100%;border-collapse:collapse;font-size:12.5px;}
          table.tm-rango-table th,table.tm-rango-table td{padding:8px 10px;text-align:left;border-bottom:1px solid #eef2f7;white-space:nowrap;}
          table.tm-rango-table th{font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:#64748b;background:#f8fafc;position:sticky;top:0;}
          table.tm-rango-table tr:hover td{background:#f9fbfd;}
          table.tm-rango-table .num{text-align:right;font-variant-numeric:tabular-nums;}
          .tm-rango-tag{display:inline-block;padding:2px 7px;border-radius:6px;font-size:10.5px;font-weight:700;}
          .tm-rango-tag.berth{background:#e0f2fe;color:#075985;}
          .tm-rango-tag.yard{background:#ede9fe;color:#6d28d9;}
        </style>
        <section class="tm-card" id="tmRangoCard" style="display:none">
          <div class="tm-card-head">
            <h2><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span> Histórico de registros <span id="tmRangoCount" style="font-size:12px;font-weight:600;color:#64748b"></span></h2>
          </div>
          <div class="tm-rango-wrap">
            <table class="tm-rango-table" id="tmRangoTable">
              <thead>
                <tr>
                  <th>Fecha</th><th>Turno</th><th>Tipo</th><th>Ubicación</th><th>Nave</th><th>Actividad</th>
                  <th class="num">Planned</th><th class="num">Executed</th><th class="num">Acum</th><th class="num">%</th><th>Estado</th><th></th>
                </tr>
              </thead>
              <tbody id="tmRangoBody"><tr><td colspan="12" class="tm-empty">—</td></tr></tbody>
            </table>
          </div>
        </section>
        <?php endif; ?>

      </div>
    </main>
  </div>
</div>

<!-- Modal adaptable (reutiliza componentes op-* de css/operaciones.css) -->
<div class="op">
  <div class="op-modal" id="tmModal">
    <div class="op-dialog">
      <div class="op-dialog-hero">
        <div class="op-dialog-hero-bg" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 22h18"/><path d="M5 22V10l7-5 7 5v12"/><path d="M9 22v-6h6v6"/></svg>
        </div>
        <button class="op-x" id="tmModalClose" type="button" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <span class="op-dialog-tag">◗ Registro de turno</span>
        <h3 id="tmModalTitle">Actividad</h3>
        <p id="tmModalSub">—</p>
      </div>
      <div class="op-dialog-body" id="tmModalBody"></div>
      <div class="op-dialog-foot">
        <span class="op-foot-hint"><span class="op-req">*</span> Campos obligatorios</span>
        <div class="op-foot-actions">
          <button class="op-btn" id="tmModalCancel" type="button">Cancelar</button>
          <button class="op-btn primary" id="tmModalSave" type="button">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <span id="tmModalSaveLabel">Guardar</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Observación para relevo -->
<div id="tmObsModal" style="display:none;position:fixed;inset:0;z-index:1100;background:rgba(0,0,0,0.3);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;">
  <div style="background:#ffffff;border:1px solid rgba(0, 43, 92,.18);border-radius:16px;width:min(520px,92vw);box-shadow:0 24px 64px rgba(0, 43, 92,.12);overflow:hidden;display:flex;flex-direction:column;">
    <div style="padding:18px 22px 14px;border-bottom:1px solid rgba(0, 43, 92,.12);display:flex;justify-content:space-between;align-items:center;">
      <div>
        <div style="font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:#002b5c;font-weight:700;margin-bottom:4px;">◗ Tallyman · Relevo</div>
        <h3 style="margin:0;font-size:17px;font-weight:800;color:#111827;">Observación del turno</h3>
        <p style="margin:4px 0 0;font-size:12px;color:#4b5563;">Queda registrada y aparece en el relevo de turno.</p>
      </div>
      <button id="tmObsClose" style="background:transparent;border:1px solid rgba(0, 43, 92,.2);border-radius:8px;width:32px;height:32px;cursor:pointer;color:#4b5563;display:grid;place-items:center;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div style="padding:18px 22px;">
      <textarea id="tmObsTxt" rows="5" placeholder="Escribe las observaciones para el coordinador entrante…" style="width:100%;background:#ffffff;border:1.5px solid #cbd5e1;border-radius:10px;padding:12px 14px;font-family:inherit;font-size:13.5px;color:#111827;resize:vertical;outline:none;min-height:110px;box-sizing:border-box;"></textarea>
      <div id="tmObsInfo" style="font-size:11.5px;color:#5a6e8b;margin-top:6px;min-height:16px;"></div>
    </div>
    <div style="padding:14px 22px;border-top:1px solid rgba(0, 43, 92,.1);display:flex;justify-content:flex-end;gap:10px;">
      <button id="tmObsCancelBtn" style="padding:9px 16px;border-radius:9px;border:1px solid rgba(0, 43, 92,.2);background:transparent;color:#4b5563;font:inherit;font-size:13px;cursor:pointer;">Cancelar</button>
      <button id="tmObsSaveBtn" style="padding:9px 18px;border-radius:9px;border:none;background:linear-gradient(135deg,#002b5c,#001b3a);color:#ffffff;font:inherit;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
        Guardar observación
      </button>
    </div>
  </div>
</div>

<script src="../js/operaciones.js?v=20260608b"></script>
<script>window.TM_CTX = {
  canRegistrar: <?= $canRegistrar ? 'true' : 'false' ?>,
  isAdmin: <?= $rol === 'Administrador' ? 'true' : 'false' ?>,
  puedeAnteriores: <?= in_array($rol, ['Administrador', 'Supervisor'], true) ? 'true' : 'false' ?>,
  userRol: <?= json_encode($rol) ?>
};</script>
<script src="../js/tallyman_registro.js?v=20260701c"></script>
</body>
</html>
