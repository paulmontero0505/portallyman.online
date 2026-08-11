<?php
require_once('../includes/auth.php');
require_admin();

// Mes por defecto: el actual. El selector navega desde aquí.
$MES_INICIAL = preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '') ? $_GET['mes'] : date('Y-m');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Histórico de naves · Estiba Shift Command Deck</title>
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
       HISTÓRICO DE NAVES · submódulo de Operaciones (prefijo .nv-*)
       Alcance: sólo ubicacion_tipo='BERTH'. Patio queda fuera.
    ════════════════════════════════════════════════════════════════ */
    .nv-wrap {
      --co-navy:#005c3d; --co-navy-700:#00875A;
      --co-deck:#f5f8f7; --co-line:rgba(0,135,90,.18);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      --amber:#B45309; --amber-bg:#FFFBEB; --amber-line:#FDE68A;
      display:flex; flex-direction:column; gap:16px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .nv-wrap *, .nv-wrap *::before, .nv-wrap *::after { box-sizing:border-box; }

    .nv-hero {
      background: linear-gradient(135deg,#005c3d 0%,#00875A 100%) !important;
      color:#fff; border-radius:20px; padding:22px 28px;
      display:flex; align-items:center; justify-content:space-between; gap:18px; flex-wrap:wrap;
      border:1px solid rgba(0,135,90,.2) !important; box-shadow:0 8px 32px rgba(0,135,90,.08) !important;
    }
    .nv-hero h1 { margin:6px 0 4px; font-size:22px; font-weight:700; letter-spacing:-.01em; }
    .nv-hero p  { margin:0; color:rgba(255,255,255,.85); font-size:13px; max-width:620px; }
    .nv-hero .tag {
      display:inline-flex; align-items:center; gap:8px; padding:5px 11px; border-radius:999px;
      background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18);
      font-size:11px; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:#fff !important;
    }
    .nv-mes {
      display:flex; align-items:center; gap:8px; background:rgba(255,255,255,.15);
      border:1px solid rgba(255,255,255,.25); border-radius:12px; padding:8px 12px;
    }
    .nv-mes button { border:0; background:transparent; color:#fff; font:inherit; font-size:17px;
      cursor:pointer; padding:0 7px; opacity:.85; line-height:1; }
    .nv-mes button:hover { opacity:1; }
    .nv-mes .val { font-size:14px; font-weight:700; min-width:130px; text-align:center; text-transform:capitalize; }

    .nv-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; }
    .nv-kpi { background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:14px 18px;
      box-shadow:0 4px 16px rgba(0,0,0,.02) !important; }
    .nv-kpi .lbl { font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--co-mute); }
    .nv-kpi .val { font-size:25px; font-weight:800; margin-top:4px; line-height:1; }
    .nv-kpi .sub { font-size:11px; color:var(--co-faint); margin-top:5px; }
    .nv-kpi:nth-child(1) .val { color:var(--co-navy-700); }
    .nv-kpi:nth-child(2) .val { color:#3b82f6; }
    .nv-kpi:nth-child(3) .val { color:#7c3aed; }
    .nv-kpi:nth-child(4) .val { color:#F79009; }
    .nv-kpi:nth-child(5) .val { color:var(--amber); }

    .nv-card { background:#fff; border:1px solid var(--co-line); border-radius:14px; box-shadow:0 4px 16px rgba(0,0,0,.02) !important; }
    .nv-card-head { padding:14px 18px; border-bottom:1px solid rgba(0,135,90,.08);
      display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .nv-card-head h2 { margin:0; font-size:14px; font-weight:800; letter-spacing:.02em; }
    .nv-card-head .hint { font-size:11.5px; color:var(--co-faint); }

    .nv-mix { padding:14px 18px; display:flex; flex-direction:column; gap:9px; }
    .nv-mix-row { display:grid; grid-template-columns:minmax(110px,160px) 1fr 100px; gap:12px; align-items:center; }
    .nv-mix-row .nm { font-size:12.5px; font-weight:600; }
    .nv-mix-bar { height:20px; background:#f1f5f4; border-radius:6px; overflow:hidden; }
    .nv-mix-bar span { display:block; height:100%; }
    .nv-mix-num { font-size:11.5px; color:var(--co-mute); text-align:right; font-variant-numeric:tabular-nums; }

    .nv-toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; background:#fff;
      border:1px solid var(--co-line); border-radius:14px; padding:10px 12px; box-shadow:0 4px 16px rgba(0,0,0,.02) !important; }
    .nv-search { flex:1; min-width:220px; display:flex; align-items:center; gap:8px; background:var(--co-deck);
      border:1px solid rgba(0,135,90,.15); border-radius:10px; padding:8px 12px; }
    .nv-search:focus-within { border-color:var(--co-navy-700); background:#fff; box-shadow:0 0 0 3px rgba(0,135,90,.15); }
    .nv-search input { flex:1; border:0; outline:0; background:transparent; font:inherit; font-size:13.5px; color:var(--co-ink); }
    .nv-search svg { width:15px; height:15px; color:var(--co-mute); }
    .nv-sel { display:flex; align-items:center; gap:6px; background:#fff; border:1px solid rgba(0,135,90,.15);
      border-radius:10px; padding:5px 9px; }
    .nv-sel.on { border-color:var(--co-navy-700); box-shadow:0 0 0 2px rgba(0,135,90,.12); }
    .nv-sel label { font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--co-mute); }
    .nv-sel select { border:0; outline:0; background:transparent; font:inherit; font-size:12.5px;
      font-weight:600; color:var(--co-ink); cursor:pointer; max-width:170px; }

    .nv-table-wrap { overflow-x:auto; }
    .nv-table { width:100%; border-collapse:collapse; font-size:13px; }
    .nv-table thead tr { background:rgba(0,135,90,.04) !important; border-bottom:1px solid var(--co-line); }
    .nv-table th { padding:11px 14px; text-align:left; white-space:nowrap; font-size:10.5px;
      letter-spacing:.1em; text-transform:uppercase; color:var(--co-navy); font-weight:700; }
    .nv-table tbody tr { border-bottom:1px solid rgba(0,135,90,.06); }
    .nv-table tbody tr.nv-row:hover { background:rgba(0,135,90,.02); }
    .nv-table td { padding:11px 14px; vertical-align:middle; color:var(--co-ink) !important; }
    .nv-nm { font-weight:700; }
    .nv-sub { font-size:11px; color:var(--co-faint); }
    .nv-chip { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px;
      font-size:10.5px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:#fff; }
    .nv-pill { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:999px;
      font-size:11px; font-weight:700; }
    .nv-big { font-size:17px; font-weight:800; font-variant-numeric:tabular-nums; }
    .nv-big small { font-size:11px; font-weight:600; color:var(--co-faint); }
    .nv-dn { display:flex; gap:4px; margin-top:3px; flex-wrap:wrap; }
    .nv-dn i { font-style:normal; font-size:9.5px; font-weight:800; padding:1px 5px; border-radius:4px;
      background:#f1f5f4; color:var(--co-mute); }
    .nv-dn i.unk { background:var(--amber-bg); color:var(--amber); border:1px solid var(--amber-line); }
    .nv-idle { font-size:10px; font-weight:800; color:var(--amber); background:var(--amber-bg);
      border:1px solid var(--amber-line); padding:1px 6px; border-radius:5px; display:inline-block; margin-top:3px; }
    .nv-f2 { font-size:9.5px; font-weight:800; letter-spacing:.06em; color:var(--co-faint);
      background:#f1f5f4; border:1px dashed #d1d5db; padding:2px 7px; border-radius:5px; }
    .nv-exp { border:1px solid rgba(0,135,90,.25); background:rgba(0,135,90,.05); color:var(--co-navy-700);
      border-radius:7px; padding:5px 10px; font:inherit; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap; }
    .nv-exp:hover { background:#00875A; color:#fff; border-color:#00875A; }

    .nv-av { min-width:130px; }
    .nv-av-bar { height:7px; background:#eef2f1; border-radius:99px; overflow:hidden; }
    .nv-av-bar span { display:block; height:100%; background:linear-gradient(90deg,#00875A,#12B76A); }
    .nv-av-txt { font-size:10.5px; color:var(--co-mute); margin-top:4px; font-variant-numeric:tabular-nums; }

    /* ── Detalle expandido ── */
    .nv-table tr.nv-detail > td { background:var(--co-deck); padding:0; }
    .nv-det { padding:16px 18px; display:flex; flex-direction:column; gap:16px; }
    .nv-det-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media (max-width:1100px) { .nv-det-grid { grid-template-columns:1fr; } }
    .nv-sect { font-size:10.5px; font-weight:800; letter-spacing:.1em; text-transform:uppercase;
      color:var(--co-navy); margin-bottom:8px; }

    .nv-cal { background:#fff; border:1px solid var(--co-line); border-radius:10px; padding:12px; overflow-x:auto; }
    .nv-cal-grid { display:inline-flex; flex-direction:column; gap:3px; min-width:min-content; }
    .nv-cal-r { display:flex; gap:3px; align-items:center; }
    .nv-cal-r .k { width:34px; flex:0 0 34px; font-size:9px; font-weight:800; color:var(--co-mute); }
    .nv-cal-c { width:23px; height:19px; flex:0 0 23px; border-radius:4px; background:#eef2f1;
      display:grid; place-items:center; font-size:8.5px; font-weight:800; color:#cbd5e1; }
    .nv-cal-c.on { background:#00875A; color:#fff; }
    .nv-cal-c.idle { background:var(--amber-bg); color:var(--amber); border:1px solid var(--amber-line); }
    .nv-cal-c.opaco { background:#E0E7FF; color:#3730A3; }
    .nv-cal-d { width:23px; flex:0 0 23px; font-size:8px; color:var(--co-faint); text-align:center; font-weight:700; }
    .nv-cal-leg { display:flex; gap:13px; font-size:10.5px; color:var(--co-mute); margin-top:10px; flex-wrap:wrap; }
    .nv-cal-leg b { display:inline-block; width:9px; height:9px; border-radius:3px; vertical-align:-1px; margin-right:4px; }
    .nv-cal-win { display:flex; gap:16px; margin-top:11px; padding-top:10px;
      border-top:1px solid rgba(0,135,90,.08); flex-wrap:wrap; }
    .nv-cal-win .k { color:var(--co-faint); font-weight:700; text-transform:uppercase; font-size:9.5px; letter-spacing:.06em; }
    .nv-cal-win .v { font-weight:700; font-size:11.5px; font-variant-numeric:tabular-nums; }

    .nv-tl { background:#fff; border:1px solid var(--co-line); border-radius:10px; padding:6px 0;
      max-height:340px; overflow-y:auto; }
    .nv-tl-i { display:grid; grid-template-columns:66px 1fr; gap:10px; padding:9px 13px;
      border-bottom:1px solid rgba(0,135,90,.06); }
    .nv-tl-i:last-child { border-bottom:0; }
    .nv-tl-i.gap { background:var(--amber-bg); }
    .nv-tl-d { font-size:10.5px; font-weight:800; color:var(--co-navy-700); font-variant-numeric:tabular-nums; }
    .nv-tl-d span { display:block; font-size:9px; font-weight:700; color:var(--co-faint); }
    .nv-tl-t { font-size:12.5px; font-weight:600; }
    .nv-tl-m { font-size:11px; color:var(--co-mute); margin-top:2px; font-variant-numeric:tabular-nums; }
    .nv-tl-tags { display:flex; gap:5px; margin-top:5px; flex-wrap:wrap; }
    .nv-tag { font-size:9.5px; font-weight:700; padding:2px 6px; border-radius:5px; background:#f1f5f4; color:var(--co-mute); }
    .nv-tag.ok { background:rgba(0,135,90,.10); color:var(--co-navy-700); }
    .nv-tag.warn { background:var(--amber-bg); color:var(--amber); }

    .nv-f2box { background:#fff; border:1px dashed #d1d5db; border-radius:10px; padding:13px 16px;
      display:flex; align-items:center; gap:12px; color:var(--co-faint); font-size:12.5px; }
    .nv-f2box .ico { font-size:19px; }
    .nv-f2box b { color:var(--co-mute); }

    /* Dotación: cantidades netas por turno, sin nombres. */
    .nv-dot { background:#fff; border:1px solid var(--co-line); border-radius:10px; overflow:hidden; }
    .nv-dot table { font-size:12.5px; }
    .nv-dot th { padding:8px 13px; font-size:9.5px; }
    .nv-dot td { padding:8px 13px; }
    .nv-dot tbody tr:last-child { border-bottom:0; }
    .nv-dot tfoot td { background:rgba(0,135,90,.04); font-weight:800; border-top:1px solid var(--co-line); }
    .nv-qty { font-size:15px; font-weight:800; font-variant-numeric:tabular-nums; }
    .nv-ubi { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px;
      font-size:11px; font-weight:600; background:#f1f5f4; color:var(--co-mute); margin:2px 3px 2px 0; }
    .nv-ubi b { font-weight:800; color:var(--co-ink); }
    .nv-ubi.berth { background:rgba(0,135,90,.10); color:var(--co-navy-700); }
    .nv-ubi.berth b { color:var(--co-navy-700); }

    .nv-empty { text-align:center; padding:38px; color:var(--co-faint); font-size:13.5px; }
    .nv-toast { position:fixed; bottom:24px; right:24px; z-index:999; background:#111827; color:#fff;
      padding:12px 18px; border-radius:10px; font-size:13px; font-weight:600;
      box-shadow:0 8px 24px rgba(0,0,0,.1); transform:translateY(120%); opacity:0; transition:all .25s; }
    .nv-toast.show { transform:translateY(0); opacity:1; }
    .nv-toast.is-error { background:#dc2626; }

    .content { padding:24px 28px 60px; overflow-y:auto; }
  </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="shell">
  <?php $sb_base = '..'; include('../includes/sidebar.php'); ?>

  <div class="main-area">
    <?php include('../includes/header.php'); ?>

    <main class="content">
      <div class="nv-wrap">

        <section class="nv-hero">
          <div>
            <span class="tag">OPERACIONES · HISTÓRICO DE NAVES</span>
            <h1>Histórico de naves</h1>
            <p>Cada nave atendida en el mes: cuándo empezó y terminó su operación de muelle, cuántos días estuvo, cuántos turnos consumió y cómo avanzó. No incluye operaciones de patio.</p>
          </div>
          <div class="nv-mes">
            <button type="button" id="nvPrev" title="Mes anterior">‹</button>
            <span class="val" id="nvMesLabel">—</span>
            <button type="button" id="nvNext" title="Mes siguiente">›</button>
          </div>
        </section>

        <section class="nv-kpis">
          <div class="nv-kpi"><div class="lbl">Naves atendidas</div><div class="val" id="kNaves">0</div><div class="sub">con operación en muelle</div></div>
          <div class="nv-kpi"><div class="lbl">Días de estadía</div><div class="val" id="kDias">0</div><div class="sub">suma de inicio → fin de cada nave</div></div>
          <div class="nv-kpi"><div class="lbl">Turnos trabajados</div><div class="val" id="kTurnos">0</div><div class="sub">solo turnos con avance en muelle</div></div>
          <div class="nv-kpi"><div class="lbl">TM movilizadas</div><div class="val" id="kTm">0</div><div class="sub">suma de lo ejecutado en el mes</div></div>
          <div class="nv-kpi"><div class="lbl">Turnos sin operación</div><div class="val" id="kGap">0</div><div class="sub">dentro de la estadía, sin avance</div></div>
        </section>

        <section class="nv-card" id="nvMixCard" style="display:none">
          <div class="nv-card-head"><h2>Composición por tipo de nave</h2><span class="hint">días de estadía · naves</span></div>
          <div class="nv-mix" id="nvMix"></div>
        </section>

        <div class="nv-toolbar">
          <div class="nv-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="nvSearch" type="text" placeholder="Buscar nave o muelle…">
          </div>
          <div class="nv-sel" id="nvTipoWrap"><label for="nvTipo">Tipo</label><select id="nvTipo"><option value="todos">Todos</option></select></div>
          <div class="nv-sel" id="nvMuelleWrap"><label for="nvMuelle">Muelle</label><select id="nvMuelle"><option value="todos">Todos</option></select></div>
          <div class="nv-sel" id="nvEstadoWrap"><label for="nvEstado">Estado</label><select id="nvEstado"><option value="todos">Todos</option></select></div>
        </div>

        <section class="nv-card">
          <div class="nv-card-head"><h2>Naves con operación en muelle</h2><span class="hint" id="nvCount">—</span></div>
          <div class="nv-table-wrap">
            <table class="nv-table">
              <thead>
                <tr>
                  <th>Nave</th><th>Tipo</th><th>Muelle</th><th>Operación en muelle</th>
                  <th>Días</th><th>Turnos</th><th>Avance</th><th>Personas</th><th>Estado</th><th></th>
                </tr>
              </thead>
              <tbody id="nvTbody">
                <tr><td colspan="10" class="nv-empty">Cargando…</td></tr>
              </tbody>
            </table>
          </div>
        </section>

      </div>
    </main>
  </div>
</div>

<div class="nv-toast" id="nvToast">—</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const BASE = '..';

  const TIPO_COLOR = {
    'Containera':'#00875A', 'Portacontenedores':'#00875A', 'Granelera':'#3b82f6',
    'Ro-Ro':'#7c3aed', 'Tanquero':'#0891b2', 'Carga General':'#F79009',
  };
  const ESTADO_COLOR = {
    'Finalizada':   ['rgba(0,135,90,.10)', '#00875A'],
    'En Operaciones':['#FFFBEB', '#B45309'],
    'En Puerto':    ['#EFF6FF', '#1d4ed8'],
    'Programada':   ['#f1f5f4', '#4b5563'],
  };

  let mes = <?= json_encode($MES_INICIAL) ?>;
  let payload = null;
  let query = '', fTipo = 'todos', fMuelle = 'todos', fEstado = 'todos';
  const abiertas = new Set();   // ids de naves con el detalle desplegado

  function toast(msg, type) {
    const t = $('nvToast');
    t.textContent = msg;
    t.className = 'nv-toast show' + (type === 'error' ? ' is-error' : '');
    setTimeout(() => t.classList.remove('show'), 3500);
  }
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g,
      c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  }
  // Miles con espacio fino: 12 480. El punto se confunde con el decimal.
  function num(n) {
    if (n === null || n === undefined) return '—';
    return Number(n).toLocaleString('es-PE', { maximumFractionDigits: 0 }).replace(/,/g, ' ');
  }
  function fdm(iso) {           // '2026-06-02' → '02/06'
    if (!iso) return '—';
    const p = String(iso).split('-');
    return p.length === 3 ? `${p[2]}/${p[1]}` : iso;
  }
  function mesLabel(m) {
    const [y, mo] = m.split('-').map(Number);
    const d = new Date(y, mo - 1, 1);
    return d.toLocaleDateString('es-PE', { month: 'long', year: 'numeric' });
  }
  function shiftMes(m, delta) {
    const [y, mo] = m.split('-').map(Number);
    const d = new Date(y, mo - 1 + delta, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  }

  // ─── Carga ───
  async function cargar() {
    $('nvMesLabel').textContent = mesLabel(mes);
    $('nvTbody').innerHTML = '<tr><td colspan="10" class="nv-empty">Cargando…</td></tr>';
    try {
      const res = await fetch(`${BASE}/api/get_operaciones_naves.php?mes=${encodeURIComponent(mes)}`, { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) {
        payload = null;
        $('nvTbody').innerHTML = `<tr><td colspan="10" class="nv-empty">${esc(data.error || 'Error al cargar')}</td></tr>`;
        toast(data.error || 'Error al cargar', 'error');
        renderKpis(); return;
      }
      payload = data.data;
      abiertas.clear();
      poblarFiltros();
      renderKpis(); renderMix(); render();
    } catch (e) {
      $('nvTbody').innerHTML = '<tr><td colspan="10" class="nv-empty">Error de red.</td></tr>';
      toast('Error de red', 'error');
    }
  }

  // Los selects se pueblan con lo que trae el mes: filtrar por un tipo que no
  // existe en el periodo sólo produce una tabla vacía sin explicación.
  function poblarFiltros() {
    const naves = payload.naves || [];
    const set = (sel, valores, actual) => {
      const el = $(sel);
      el.innerHTML = '<option value="todos">Todos</option>'
        + valores.map(v => `<option value="${esc(v)}">${esc(v)}</option>`).join('');
      el.value = valores.includes(actual) ? actual : 'todos';
    };
    const uniq = (arr) => Array.from(new Set(arr.filter(Boolean))).sort();
    set('nvTipo',   uniq(naves.map(n => n.nave.tipo)), fTipo);
    set('nvMuelle', uniq(naves.map(n => n.nave.muelle)), fMuelle);
    set('nvEstado', uniq(naves.map(n => n.nave.estado)), fEstado);
    fTipo = $('nvTipo').value; fMuelle = $('nvMuelle').value; fEstado = $('nvEstado').value;
    marcarFiltros();
  }
  function marcarFiltros() {
    $('nvTipoWrap').classList.toggle('on', fTipo !== 'todos');
    $('nvMuelleWrap').classList.toggle('on', fMuelle !== 'todos');
    $('nvEstadoWrap').classList.toggle('on', fEstado !== 'todos');
  }

  // ─── KPIs ───
  // Vienen calculados del servidor sobre el mes completo: no se recalculan al
  // filtrar, porque los filtros son lentes sobre la tabla, no sobre el periodo.
  function renderKpis() {
    const k = (payload && payload.kpis) || { naves:0, dias_estadia:0, turnos:0, tm:0, sin_operacion:0 };
    $('kNaves').textContent  = k.naves;
    $('kDias').textContent   = k.dias_estadia;
    $('kTurnos').textContent = k.turnos;
    $('kTm').textContent     = num(k.tm);
    $('kGap').textContent    = k.sin_operacion;
  }

  function renderMix() {
    const mix = (payload && payload.mix) || [];
    $('nvMixCard').style.display = mix.length ? '' : 'none';
    if (!mix.length) return;
    const max = Math.max.apply(null, mix.map(m => m.dias)) || 1;
    $('nvMix').innerHTML = mix.map(m => `
      <div class="nv-mix-row">
        <span class="nm">${esc(m.tipo)}</span>
        <div class="nv-mix-bar"><span style="width:${Math.round(m.dias / max * 100)}%;background:${TIPO_COLOR[m.tipo] || '#64748b'}"></span></div>
        <span class="nv-mix-num">${m.dias} d · ${m.naves} nave${m.naves === 1 ? '' : 's'}</span>
      </div>`).join('');
  }

  // ─── Tabla ───
  function listaVisible() {
    const q = query.trim().toLowerCase();
    return ((payload && payload.naves) || []).filter(n => {
      if (fTipo   !== 'todos' && n.nave.tipo   !== fTipo)   return false;
      if (fMuelle !== 'todos' && n.nave.muelle !== fMuelle) return false;
      if (fEstado !== 'todos' && n.nave.estado !== fEstado) return false;
      if (!q) return true;
      return [n.nave.nombre, n.nave.muelle, n.nave.tipo]
        .some(v => String(v ?? '').toLowerCase().includes(q));
    });
  }

  function ventanaCell(n) {
    const v = n.ventana;
    const fin = v.en_curso ? 'en curso' : fdm(v.cierre_fecha);
    const ini = `inicio ${fdm(v.inicio_fecha)} ${esc(v.inicio_label)}`;
    const cie = v.en_curso ? 'sin cierre' : `cierre ${fdm(v.cierre_fecha)} ${esc(v.cierre_label)}`;
    // El inicio/cierre inferido se marca: no lo declaró nadie, se dedujo del
    // primer y último turno con avance.
    const inf = (v.inicio_marca === 'inferido' || (!v.en_curso && v.cierre_marca === 'inferido'))
      ? ' <span title="Deducido del primer/último turno con avance: falta el marcador de estado">≈</span>' : '';
    return `<div style="font-weight:700;font-size:12.5px">${fdm(v.inicio_fecha)} → ${fin}</div>
            <div class="nv-sub">${ini} · ${cie}${inf}</div>`;
  }

  // `dotacion === null` significa que ningún turno de esta nave tiene nave_id:
  // datos anteriores a la captura del vínculo. Es distinto de «0 personas»,
  // que afirmaría que nadie estuvo asignado. La UI no los confunde.
  function personasCell(n) {
    const d = n.dotacion;
    if (!d) {
      return `<span class="nv-f2" title="Ningún turno de esta nave tiene nave asignada. Se captura desde la pantalla de turno; los periodos anteriores no son reconstruibles.">SIN TRAZA</span>`;
    }
    return `<span class="nv-big">${d.personas_mes}</span>
            <div class="nv-sub">prom ${d.promedio} · pico ${d.pico} / turno</div>`;
  }

  function avanceCell(n) {
    if (n.planeado === null || n.avance_pct === null) {
      return `<div class="nv-av"><div class="nv-av-bar"><span style="width:0"></span></div>
              <div class="nv-av-txt">${num(n.ejecutado_total)} TM · sin plan</div></div>`;
    }
    const w = Math.min(n.avance_pct, 100);
    const orig = n.plan_origen === 'maximo'
      ? ' <span title="La nave no tiene registro con estado Inicio; se usó el mayor plan visto">≈</span>' : '';
    return `<div class="nv-av"><div class="nv-av-bar"><span style="width:${w}%"></span></div>
            <div class="nv-av-txt">${num(n.ejecutado_total)} / ${num(n.planeado)} TM · ${n.avance_pct}%${orig}</div></div>`;
  }

  function render() {
    const list = listaVisible();
    const total = ((payload && payload.naves) || []).length;
    $('nvCount').textContent = payload
      ? `${mesLabel(mes)} · ${list.length}${list.length !== total ? ' de ' + total : ''} nave${list.length === 1 ? '' : 's'}`
      : '—';

    const tb = $('nvTbody');
    if (!list.length) {
      tb.innerHTML = `<tr><td colspan="10" class="nv-empty">${total ? 'Ninguna nave calza con los filtros.' : 'Sin operaciones de muelle en este mes.'}</td></tr>`;
      return;
    }

    tb.innerHTML = list.map(n => {
      const id = n.nave.id;
      const abierta = abiertas.has(id);
      const tipo = n.nave.tipo || 'Sin tipo';
      const est  = ESTADO_COLOR[n.nave.estado] || ['#f1f5f4', '#4b5563'];
      const dn = (n.turnos_desglose || []).map(d =>
        `<i class="${d.conocido ? '' : 'unk'}" title="${d.conocido ? '' : 'Código de turno fuera del catálogo de jornadas'}">${d.n} ${esc(d.label)}</i>`).join('');
      const idle = n.turnos_sin_operacion > 0
        ? `<div class="nv-idle">${n.turnos_sin_operacion} turno${n.turnos_sin_operacion === 1 ? '' : 's'} sin operación</div>` : '';
      const antes = n.ventana.empezo_antes ? '<div class="nv-sub">empezó el mes anterior</div>' : '';

      return `
        <tr class="nv-row">
          <td><div class="nv-nm">${esc(n.nave.nombre)}</div>
              <div class="nv-sub">${n.nave.etb ? 'ETB ' + esc(String(n.nave.etb).slice(0,16)) : 'sin ETB'}</div></td>
          <td><span class="nv-chip" style="background:${TIPO_COLOR[tipo] || '#64748b'}">${esc(tipo)}</span></td>
          <td>${n.nave.muelle ? esc(n.nave.muelle) : '<span class="nv-sub">Sin muelle</span>'}</td>
          <td>${ventanaCell(n)}</td>
          <td><span class="nv-big">${n.dias_estadia} <small>d</small></span>${antes}${idle}</td>
          <td><span class="nv-big">${n.turnos_trabajados}</span><div class="nv-dn">${dn}</div></td>
          <td>${avanceCell(n)}</td>
          <td>${personasCell(n)}</td>
          <td><span class="nv-pill" style="background:${est[0]};color:${est[1]}">${esc(n.nave.estado || '—')}</span></td>
          <td><button class="nv-exp" data-id="${id}">${abierta ? 'Ocultar ▲' : 'Ver ▼'}</button></td>
        </tr>
        ${abierta ? `<tr class="nv-detail"><td colspan="10">${detalle(n)}</td></tr>` : ''}`;
    }).join('');
  }

  // ─── Detalle ───
  function detalle(n) {
    return `<div class="nv-det">
      <div class="nv-det-grid">
        <div><div class="nv-sect">Ventana de operación en muelle</div>${rejilla(n)}</div>
        <div><div class="nv-sect">Cómo fue el proceso</div>${lineaTiempo(n)}</div>
      </div>
      ${dotacionTabla(n)}
    </div>`;
  }

  // Cantidades netas por turno, sin nombres: cuánta gente y en qué ubicaciones.
  function dotacionTabla(n) {
    const d = n.dotacion;
    if (!d) {
      return `<div class="nv-f2box">
        <span class="ico">👥</span>
        <div><b>Sin trazabilidad de personas en este periodo.</b><br>
          La nave asignada se registra desde la pantalla de turno. Los turnos anteriores a
          esa captura no tienen el dato y no son reconstruibles.</div>
      </div>`;
    }
    const filas = d.turnos.map(t => {
      const ubis = Object.entries(t.ubicaciones).map(([u, c]) =>
        `<span class="nv-ubi ${/^\s*(muelle|berth)/i.test(u) ? 'berth' : ''}">${esc(u)} <b>${c}</b></span>`).join('');
      return `<tr><td>${fdm(t.fecha)}</td><td>${esc(t.label)}</td>
              <td><span class="nv-qty">${t.personas}</span></td><td>${ubis}</td></tr>`;
    }).join('');
    return `<div>
      <div class="nv-sect">Personas por turno y ubicación</div>
      <div class="nv-dot">
        <table>
          <thead><tr><th style="width:90px">Fecha</th><th style="width:90px">Turno</th>
            <th style="width:90px">Personas</th><th>Ubicaciones</th></tr></thead>
          <tbody>${filas}</tbody>
          <tfoot><tr><td colspan="2">${d.turnos.length} turno${d.turnos.length === 1 ? '' : 's'}</td>
            <td><span class="nv-qty">${d.personas_mes}</span> <span class="nv-sub">distintas</span></td>
            <td class="nv-sub">Promedio ${d.promedio} por turno · pico ${d.pico}</td></tr></tfoot>
        </table>
      </div>
    </div>`;
  }

  // La rejilla hace AUDITABLE el conteo: no dice «13 turnos», muestra cuáles.
  function rejilla(n) {
    const dias = n.rejilla || [];
    if (!dias.length) return '<div class="nv-cal"><span class="nv-sub">Sin ventana que mostrar.</span></div>';
    const codigos = Object.keys(dias[0].celdas || {});
    const jorn = (payload.jornadas || []).reduce((a, j) => (a[j.codigo] = j, a), {});

    const cab = `<div class="nv-cal-r"><span class="k"></span>${
      dias.map(d => `<span class="nv-cal-d">${String(d.fecha).slice(8)}</span>`).join('')}</div>`;

    const filas = codigos.map(cod => `
      <div class="nv-cal-r"><span class="k">${esc((jorn[cod] && jorn[cod].corta) || cod)}</span>${
        dias.map(d => {
          const e = d.celdas[cod];
          const cls = e === 'trabajado' ? 'on' : (e === 'opaco' ? 'opaco' : 'idle');
          const txt = e === 'trabajado' ? cod : (e === 'opaco' ? '?' : '—');
          const ttl = e === 'trabajado' ? `${d.fecha} · turno ${cod} con avance`
                    : e === 'opaco'     ? `${d.fecha} · hubo registro con código ${d.otros.join(', ')}`
                                        : `${d.fecha} · turno ${cod} sin operación`;
          return `<span class="nv-cal-c ${cls}" title="${esc(ttl)}">${esc(txt)}</span>`;
        }).join('')}</div>`).join('');

    const v = n.ventana;
    const posibles = dias.length * codigos.length;
    const pct = posibles ? Math.round(n.turnos_trabajados / posibles * 100) : 0;
    const muelles = (n.muelles_operados || []).length > 1
      ? `<div><div class="k">Atracaderos</div><div class="v">${esc(n.muelles_operados.join(' + '))}</div></div>` : '';

    return `<div class="nv-cal">
      <div class="nv-cal-grid">${cab}${filas}</div>
      <div class="nv-cal-leg">
        <span><b style="background:#00875A"></b>Turno con avance</span>
        <span><b style="background:#FFFBEB;border:1px solid #FDE68A"></b>Sin operación</span>
        <span><b style="background:#E0E7FF"></b>Código de turno no reconocido</span>
      </div>
      <div class="nv-cal-win">
        <div><div class="k">Inicio real</div><div class="v">${fdm(v.inicio_fecha)} · ${esc(v.inicio_label)}</div></div>
        <div><div class="k">Cierre real</div><div class="v">${v.en_curso ? 'en curso' : fdm(v.cierre_fecha) + ' · ' + esc(v.cierre_label)}</div></div>
        <div><div class="k">Estadía</div><div class="v">${n.dias_estadia} días</div></div>
        <div><div class="k">Turnos posibles</div><div class="v">${posibles}</div></div>
        <div><div class="k">Trabajados</div><div class="v">${n.turnos_trabajados} · ${pct}%</div></div>
        ${muelles}
      </div>
    </div>`;
  }

  // Los huecos aparecen como fila propia en vez de omitirse: un turno sin
  // avance dentro de la estadía es información, no ausencia de información.
  function lineaTiempo(n) {
    const items = n.linea_tiempo || [];
    if (!items.length) return '<div class="nv-tl"><div class="nv-tl-i"><span class="nv-sub">Sin turnos en el mes.</span></div></div>';
    return `<div class="nv-tl">${items.map(i => {
      if (i.tipo === 'hueco') {
        return `<div class="nv-tl-i gap">
          <div class="nv-tl-d">${fdm(i.fecha)}<span>${esc(i.label)}</span></div>
          <div><div class="nv-tl-t">Sin operación</div>
            <div class="nv-tl-m">Turno dentro de la estadía sin ningún registro de avance</div>
            <div class="nv-tl-tags"><span class="nv-tag warn">Hueco</span></div></div></div>`;
      }
      const acum = i.plan ? `acumulado ${num(i.acumulado)} / ${num(i.plan)}` : `acumulado ${num(i.acumulado)}`;
      const prod = i.productividad !== null ? ` · ${i.productividad} TM/h` : '';
      const tags = []
        .concat((i.status || []).map(s => `<span class="nv-tag ${s === 'Culminado' || s === 'Inicio' ? 'ok' : ''}">${esc(s)}</span>`))
        .concat(i.coord_entrante ? [`<span class="nv-tag">Coord. entra: ${esc(i.coord_entrante)}</span>`] : []);
      return `<div class="nv-tl-i">
        <div class="nv-tl-d">${fdm(i.fecha)}<span>${esc(i.label)}</span></div>
        <div><div class="nv-tl-t">${esc((i.actividades || []).join(' · ') || 'Actividad sin nombre')}</div>
          <div class="nv-tl-m">${num(i.tm)} TM · ${acum}${prod}</div>
          <div class="nv-tl-tags">${tags.join('')}</div></div></div>`;
    }).join('')}</div>`;
  }

  // ─── Eventos ───
  $('nvPrev').addEventListener('click', () => { mes = shiftMes(mes, -1); cargar(); });
  $('nvNext').addEventListener('click', () => { mes = shiftMes(mes,  1); cargar(); });
  $('nvSearch').addEventListener('input', e => { query = e.target.value; render(); });
  $('nvTipo').addEventListener('change',   e => { fTipo   = e.target.value; marcarFiltros(); render(); });
  $('nvMuelle').addEventListener('change', e => { fMuelle = e.target.value; marcarFiltros(); render(); });
  $('nvEstado').addEventListener('change', e => { fEstado = e.target.value; marcarFiltros(); render(); });
  $('nvTbody').addEventListener('click', e => {
    const b = e.target.closest('.nv-exp'); if (!b) return;
    const id = Number(b.dataset.id);
    if (abiertas.has(id)) abiertas.delete(id); else abiertas.add(id);
    render();
  });

  cargar();
})();
</script>

</body>
</html>
