<?php
require_once('../includes/auth.php');
require_once('../includes/db.php');
require_once('../includes/indicadores_catalogo.php');
require_once('../includes/indicadores_engine.php');
require_indicadores();

$ES_ADMIN = is_admin();
$ES_COORD = ($_SESSION['user_rol'] ?? '') === 'Coordinador';

// Team a cargo del Coordinador, para no pintarle celdas de otros teams que el
// backend va a rechazar igual. Null = no se pudo determinar (sin colaboradores
// asignados, o asignados a teams mixtos): en ese caso no se bloquea nada aqui
// y el backend sigue siendo el que decide.
$MI_TEAM = $ES_COORD
    ? ind_team_de_coordinador($conn, (int)($_SESSION['user_id'] ?? 0))
    : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Indicadores · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <link rel="stylesheet" href="../css/estiba.css">

  <style>
    /* ════════════════ INDICADORES (prefijo .ind-*) ════════════════ */
    .ind-wrap {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-navy-900:#003d28;
      --co-deck:#f5f8f7; --co-deck-2:#e9f2ee;
      --co-line:rgba(0,135,90,.16); --co-line-bold:rgba(0,135,90,.28);
      --co-ink:#111827; --co-mute:#6b7280; --co-faint:#9ca3af;
      --ok:#047857; --ok-bg:rgba(4,120,87,.10);
      --wn:#d97706; --wn-bg:rgba(217,119,6,.10);
      --er:#dc2626; --er-bg:rgba(220,38,38,.10);
      --sl:#475569; --sl-bg:rgba(100,116,139,.12);
      --mono: ui-monospace, 'Cascadia Code', 'SF Mono', Consolas, monospace;
      --team-a:#0f4c81; --team-b:#7c3aed; --team-c:#d97706; --team-d:#0f766e;
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .ind-wrap *, .ind-wrap *::before, .ind-wrap *::after { box-sizing:border-box; }

    /* ─── Hero ─── */
    .ind-hero {
      position:relative; overflow:hidden; isolation:isolate;
      background:linear-gradient(155deg,#005c3d 0%,#00875A 55%,#00b377 100%);
      color:#fff; border-radius:20px; padding:24px 28px;
      display:flex; flex-direction:column; gap:18px;
    }
    .ind-hero::before {
      content:''; position:absolute; inset:0;
      background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);
      background-size:28px 28px;
      mask-image:radial-gradient(ellipse at top right,#000 30%,transparent 75%);
      z-index:0;
    }
    .ind-hero > * { position:relative; z-index:1; }
    .ind-hero-top { display:flex; flex-direction:column; gap:6px; }
    .ind-hero .tag {
      display:inline-flex; align-items:center; gap:7px; width:fit-content;
      padding:5px 11px; border-radius:999px;
      background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2);
      font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
    }
    .ind-hero .tag .pulse {
      width:7px; height:7px; border-radius:50%; background:#5ee2a0;
      box-shadow:0 0 0 0 rgba(94,226,160,.6); animation:indPulse 1.8s infinite;
    }
    @keyframes indPulse {
      0%   { box-shadow:0 0 0 0    rgba(94,226,160,.55); }
      70%  { box-shadow:0 0 0 9px  rgba(94,226,160,0); }
      100% { box-shadow:0 0 0 0    rgba(94,226,160,0); }
    }
    .ind-hero h1 { margin:6px 0 4px; font-size:24px; font-weight:700; letter-spacing:-.01em; }
    .ind-hero p { margin:0; color:rgba(255,255,255,.78); font-size:13.5px; max-width:640px; line-height:1.5; }

    .ind-hero-kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
    .ind-kpi {
      background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2);
      border-radius:13px; padding:12px 10px 10px; backdrop-filter:blur(6px); text-align:center;
    }
    .ind-kpi .lbl {
      font-size:9.5px; letter-spacing:.08em; text-transform:uppercase;
      color:rgba(255,255,255,.72); font-weight:700; white-space:nowrap;
      overflow:hidden; text-overflow:ellipsis;
    }
    .ind-kpi .val { font-family:var(--mono); font-size:24px; font-weight:700; margin-top:4px; }
    .ind-kpi.kpi-ok .val { color:#5ee2a0; }
    .ind-kpi.kpi-wn .val { color:#ffc266; }
    .ind-kpi.kpi-er .val { color:#ff7a85; }
    .ind-kpi.kpi-sl .val { color:#cbd5e1; }

    /* ─── Tabs (píldora) ─── */
    .ind-tabs {
      display:flex; gap:4px; flex-wrap:wrap;
      background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:6px;
    }
    .ind-tab {
      display:inline-flex; align-items:center; gap:7px;
      padding:9px 15px; border-radius:10px; border:none; background:none;
      font-family:inherit; font-size:13px; font-weight:600; color:var(--co-mute);
      cursor:pointer; transition:all .15s;
    }
    .ind-tab svg { width:15px; height:15px; flex-shrink:0; }
    .ind-tab:hover { color:var(--co-navy); background:var(--co-deck); }
    .ind-tab.active { color:#fff; background:var(--co-navy); }
    .ind-panel { display:none; }
    .ind-panel.active { display:block; }

    /* ─── Badges de estado ─── */
    .ind-estado-badge {
      display:inline-flex; align-items:center; gap:5px;
      padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap;
    }
    .ind-estado-badge .dot { width:6px; height:6px; border-radius:50%; background:currentColor; flex-shrink:0; }
    .ind-estado-CUMPLE { background:var(--ok-bg); color:var(--ok); }
    .ind-estado-EN_RIESGO { background:var(--wn-bg); color:var(--wn); }
    .ind-estado-NO_CUMPLE { background:var(--er-bg); color:var(--er); }
    .ind-estado-SIN_DATO { background:var(--sl-bg); color:var(--sl); }

    .ind-select { padding:8px 12px; border-radius:9px; border:1px solid var(--co-line-bold); font-family:inherit; font-size:13px; background:#fff; color:var(--co-ink); }

    /* ─── Chips: código, automático/manual ─── */
    .ind-code {
      display:inline-flex; align-items:center; justify-content:center;
      padding:3px 9px; border-radius:7px; background:var(--co-deck); color:var(--co-navy);
      font-family:var(--mono); font-size:11.5px; font-weight:700; white-space:nowrap;
    }
    .ind-badge-auto {
      display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:7px;
      background:rgba(124,58,237,.1); color:#7c3aed; font-size:11px; font-weight:700; white-space:nowrap;
    }
    .ind-badge-auto svg { width:11px; height:11px; }
    .ind-badge-manual {
      display:inline-flex; align-items:center; padding:3px 9px; border-radius:7px;
      background:var(--sl-bg); color:var(--sl); font-size:11px; font-weight:700; white-space:nowrap;
    }

    /* ─── Título de card con ícono ─── */
    .ind-ctitle { display:flex; align-items:center; gap:9px; }
    .ind-ctitle .ic {
      width:32px; height:32px; border-radius:9px; background:var(--co-navy); color:#fff;
      display:grid; place-items:center; flex-shrink:0;
    }
    .ind-ctitle .ic svg { width:16px; height:16px; }
    .ind-info-row {
      display:flex; gap:12px; align-items:flex-start;
      padding:11px 13px; background:var(--co-deck); border-radius:10px;
    }
    .ind-info-row .ind-info-tag {
      flex-shrink:0; min-width:140px; font-weight:700; color:var(--co-navy); font-size:12.5px;
    }
    .ind-info-row p { margin:0; font-size:12.5px; color:var(--co-mute); line-height:1.5; }

    /* ─── Barra de progreso (% vs Meta) ─── */
    .ind-bar-wrap { display:flex; align-items:center; gap:8px; min-width:130px; }
    .ind-bar-track { flex:1; height:7px; border-radius:4px; background:var(--co-deck-2); overflow:hidden; }
    .ind-bar-fill { height:100%; border-radius:4px; transition:width .4s ease; }
    .ind-bar-val { font-family:var(--mono); font-size:11.5px; font-weight:700; min-width:44px; text-align:right; }

    /* ─── Resumen por gestión ─── */
    .ind-gestion-card { margin-bottom:14px; }
    .ind-gestion-head { display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; }
    .ind-gestion-head .avg { display:flex; align-items:center; gap:10px; min-width:200px; }
    .ind-gestion-head .avg .ind-bar-track { width:120px; }

    /* ─── Dashboard: tarjetas donut ─── */
    .ind-dash-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:14px; }
    .ind-donut-card {
      background:#fff; border:1px solid var(--co-line); border-radius:16px; padding:16px;
      display:flex; align-items:center; gap:14px; transition:transform .15s,box-shadow .15s;
    }
    .ind-donut-card:hover { transform:translateY(-2px); box-shadow:0 8px 22px -10px rgba(0,92,61,.25); }
    .ind-donut-card svg { flex-shrink:0; }
    .ind-donut-info { min-width:0; }
    .ind-donut-info .code { font-size:10.5px; letter-spacing:.1em; text-transform:uppercase; color:var(--co-faint); font-weight:700; }
    .ind-donut-info .name { font-size:13px; font-weight:700; color:var(--co-ink); margin-top:2px; line-height:1.3; }
    .ind-donut-info .sub { font-size:11px; color:var(--co-mute); margin-top:5px; }

    /* ─── Cronograma: badges de team ─── */
    .ind-team-badge {
      display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:8px;
      font-size:11.5px; font-weight:700; white-space:nowrap;
    }
    .ind-team-badge .dot { width:7px; height:7px; border-radius:50%; background:currentColor; }
    .ind-team-A { background:rgba(15,76,129,.1); color:var(--team-a); }
    .ind-team-B { background:rgba(124,58,237,.1); color:var(--team-b); }
    .ind-team-C { background:rgba(217,119,6,.1); color:var(--team-c); }
    .ind-team-D { background:rgba(15,118,110,.1); color:var(--team-d); }
    .ind-team-empty { color:var(--co-faint); font-size:12px; }

    /* ─── Tablas: extras sobre .ui-table global ─── */
    .ind-wrap .ui-table td:nth-child(2) { white-space:normal; min-width:180px; }
    .ind-cap-n, .ind-cap-d {
      border:1px solid var(--co-line-bold); border-radius:7px; padding:5px 6px;
      font-family:var(--mono); font-size:12px; text-align:center;
    }
    .ind-cap-n:focus, .ind-cap-d:focus {
      outline:none; border-color:var(--co-navy); box-shadow:0 0 0 3px rgba(0,135,90,.12);
    }
    .ind-cap-sep { color:var(--co-faint); margin:0 2px; }
    .ind-cat-meta { border:1px solid var(--co-line-bold); border-radius:7px; padding:5px 8px; font-family:var(--mono); font-size:12.5px; }

    @media (max-width:760px) {
      .ind-hero-kpis { grid-template-columns:repeat(2,1fr); }
      .ind-dash-grid { grid-template-columns:1fr; }
      .ind-gestion-head { flex-direction:column; align-items:flex-start; }
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
      <div class="ind-wrap">

        <div class="ind-hero">
          <div class="ind-hero-top">
            <span class="tag"><span class="pulse"></span> PANEL DE SEGUIMIENTO</span>
            <h1>Indicadores</h1>
            <p>Coordinación Tally 2026 — 4 Gestiones · 21 Indicadores · 4 Teams. Los indicadores marcados
            "Automático" se calculan en tiempo real desde los módulos operativos del sistema.</p>
          </div>
          <div class="ind-hero-kpis" id="indHeroKpis">
            <div class="ind-kpi kpi-ok"><div class="lbl">Cumple</div><div class="val" id="indKpiCumple">—</div></div>
            <div class="ind-kpi kpi-wn"><div class="lbl">En Riesgo</div><div class="val" id="indKpiRiesgo">—</div></div>
            <div class="ind-kpi kpi-er"><div class="lbl">No Cumple</div><div class="val" id="indKpiNoCumple">—</div></div>
            <div class="ind-kpi kpi-sl"><div class="lbl">Sin Dato</div><div class="val" id="indKpiSinDato">—</div></div>
          </div>
        </div>

        <div class="ind-tabs" id="indTabs">
          <button class="ind-tab active" data-tab="inicio"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>Inicio</button>
          <button class="ind-tab" data-tab="dashboard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 9 9h-9V3z"/></svg>Dashboard</button>
          <button class="ind-tab" data-tab="resumen"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4"/><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M9 3v18M9 8h.01M9 13h.01"/></svg>Resumen Gestión</button>
          <button class="ind-tab" data-tab="datos"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>Datos Mensuales</button>
          <button class="ind-tab" data-tab="catalogo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>Catálogo</button>
          <button class="ind-tab" data-tab="cronograma"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Cronograma</button>
        </div>

        <section class="ind-panel active" id="indPanelInicio">
          <div class="card">
            <div class="card-body">
              <div class="ind-ctitle" style="margin-bottom:14px">
                <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg></div>
                <h3 style="margin:0">Estructura del panel</h3>
              </div>
              <div style="display:flex;flex-direction:column;gap:8px">
                <div class="ind-info-row"><span class="ind-info-tag">Dashboard</span><p>vista ejecutiva con selector de mes y team, semáforo de avance por gestión.</p></div>
                <div class="ind-info-row"><span class="ind-info-tag">Resumen Gestión</span><p>consolidado por las 4 gestiones y por mes, con detalle por indicador.</p></div>
                <div class="ind-info-row"><span class="ind-info-tag">Datos Mensuales</span><p>captura del Numerador y Denominador por team. El Valor, Resultado, % vs Meta y Estado se calculan (automáticamente para los indicadores con fuente de datos ya conectada).</p></div>
                <div class="ind-info-row"><span class="ind-info-tag">Catálogo</span><p>listado completo de los 21 indicadores con código, fórmula, meta y tipo de cálculo.</p></div>
                <div class="ind-info-row"><span class="ind-info-tag">Cronograma</span><p>team responsable por gestión y mes.</p></div>
              </div>
            </div>
          </div>
        </section>

        <section class="ind-panel" id="indPanelDashboard"></section>
        <section class="ind-panel" id="indPanelResumen"></section>
        <section class="ind-panel" id="indPanelDatos"></section>
        <section class="ind-panel" id="indPanelCatalogo"></section>
        <section class="ind-panel" id="indPanelCronograma"></section>

      </div>
    </main>
  </div>
</div>

<script>
const IND_ES_ADMIN = <?= $ES_ADMIN ? 'true' : 'false' ?>;
const IND_ES_COORD = <?= $ES_COORD ? 'true' : 'false' ?>;
const IND_MI_TEAM = <?= $MI_TEAM === null ? 'null' : json_encode($MI_TEAM) ?>;
const IND_BASE = '..';

document.querySelectorAll('.ind-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.ind-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.ind-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const map = { inicio: 'indPanelInicio', dashboard: 'indPanelDashboard', resumen: 'indPanelResumen',
      datos: 'indPanelDatos', catalogo: 'indPanelCatalogo', cronograma: 'indPanelCronograma' };
    document.getElementById(map[btn.dataset.tab]).classList.add('active');
    if (typeof window.indOnTabShown === 'function') window.indOnTabShown(btn.dataset.tab);
  });
});

// Todo texto que venga de la base (nombres de gestion, KPI, fuente) pasa por
// aqui antes de entrar a un innerHTML: hoy son datos de catalogo controlados,
// pero el catalogo es editable y no queremos que eso se vuelva un XSS.
function indEsc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// Espejo exacto de ind_pct_vs_meta() en includes/indicadores_catalogo.php.
// La vista por team del Dashboard es el unico sitio que necesita recalcular el
// cumplimiento en el cliente (el servidor solo publica el consolidado), y sin
// esta inversion los 5 indicadores con operador '<=' aparecerian al reves:
// el team con cero incumplimientos puntuaria 0% y el peor 400%.
function indPctVsMeta(resultado, meta, operador) {
  if (resultado === null || meta === null) return null;
  if (operador === '<=') return resultado === 0 ? 2 : meta / resultado;
  if (!meta) return null;
  return resultado / meta;
}

const IND_ICON_BOLT = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 3 14h7l-1 8 11-14h-7l1-6z"/></svg>';

// Colores/umbrales identicos al semaforo del backend (ind_estado en indicadores_catalogo.php):
// >=1 CUMPLE, >=0.8 EN RIESGO, si no NO CUMPLE.
function indColorPct(pct) {
  if (pct === null) return 'var(--sl)';
  if (pct >= 1) return 'var(--ok)';
  if (pct >= 0.8) return 'var(--wn)';
  return 'var(--er)';
}

function indBarraPct(pct, ancha) {
  if (pct === null) return '<span class="ind-team-empty">Sin dato</span>';
  const clamped = Math.max(0, Math.min(1, pct));
  const color = indColorPct(pct);
  const label = (pct * 100).toFixed(1) + '%';
  return `<div class="ind-bar-wrap"${ancha ? ' style="min-width:180px"' : ''}>
    <div class="ind-bar-track"><div class="ind-bar-fill" style="width:${(clamped * 100).toFixed(1)}%;background:${color}"></div></div>
    <span class="ind-bar-val" style="color:${color}">${label}</span>
  </div>`;
}

function indDonutSvg(pct) {
  const size = 76, stroke = 9, r = (size - stroke) / 2, cx = size / 2, cy = size / 2, c = 2 * Math.PI * r;
  const color = pct === null ? 'var(--co-faint)' : indColorPct(pct);
  const dash = pct === null ? 0 : c * Math.max(0, Math.min(1, pct));
  const label = pct === null ? 'N/D' : (pct * 100).toFixed(0) + '%';
  return `<svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
    <g transform="rotate(-90 ${cx} ${cy})">
      <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="var(--co-deck-2)" stroke-width="${stroke}"/>
      <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="${color}" stroke-width="${stroke}" stroke-linecap="round"
        stroke-dasharray="${dash.toFixed(1)} ${c.toFixed(1)}"/>
    </g>
    <text x="${cx}" y="${cy}" text-anchor="middle" dominant-baseline="central" font-family="var(--mono)" font-size="15" font-weight="700" fill="${color}">${label}</text>
  </svg>`;
}

async function indLoadHeroKpis() {
  const heroPeriodo = new Date().toISOString().slice(0, 7);
  try {
    const res = await fetch(`${IND_BASE}/api/get_indicadores.php?periodo=${heroPeriodo}`);
    const json = await res.json();
    if (!json.success) return;
    const counts = { CUMPLE: 0, EN_RIESGO: 0, NO_CUMPLE: 0, SIN_DATO: 0 };
    json.data.forEach(ind => {
      const key = ind.estado.replace(/ /g, '_');
      if (key in counts) counts[key]++;
    });
    document.getElementById('indKpiCumple').textContent = counts.CUMPLE;
    document.getElementById('indKpiRiesgo').textContent = counts.EN_RIESGO;
    document.getElementById('indKpiNoCumple').textContent = counts.NO_CUMPLE;
    document.getElementById('indKpiSinDato').textContent = counts.SIN_DATO;
  } catch (e) {
    // El hero es informativo: si falla el fetch, se deja el guion por defecto.
  }
}
indLoadHeroKpis();

let indDatosLoaded = false;
let indCatalogoLoaded = false;
let indCronogramaLoaded = false;
let indResumenLoaded = false;
let indDashboardLoaded = false;

/**
 * Datos, Resumen y Dashboard comparten la global indDatosPeriodo, y cada una
 * la muta desde su propio selector de mes. Como cada pestaña se pinta una sola
 * vez, sin esto una pestaña ya renderizada seguiria mostrando el mes viejo
 * mientras la global apunta a otro: la captura se guardaria en un mes distinto
 * al que se ve en pantalla. Al cambiar el periodo se invalidan las tres para
 * que se repinten con el mes vigente al volver a abrirlas.
 */
function indCambiarPeriodo(nuevoPeriodo, exceptoTab) {
  indDatosPeriodo = nuevoPeriodo;
  if (exceptoTab !== 'datos') indDatosLoaded = false;
  if (exceptoTab !== 'resumen') indResumenLoaded = false;
  if (exceptoTab !== 'dashboard') indDashboardLoaded = false;
}

/** Tras escribir (captura o edicion de meta) las vistas derivadas quedan rancias. */
function indInvalidarVistasDerivadas() {
  indResumenLoaded = false;
  indDashboardLoaded = false;
}

/**
 * Marca la pestaña como cargada ANTES de disparar (para que dos clics rapidos
 * no lancen dos cargas) y la desmarca si la carga termino en error, para que
 * un fallo de red o una sesion vencida no dejen la pestaña muerta: basta con
 * volver a entrar para reintentar. indFallo() es la señal que usan las
 * funciones de carga para reportar que no pudieron pintar nada.
 */
let indFalloEnCarga = false;
function indFallo() { indFalloEnCarga = true; }

window.indOnTabShown = function (tab) {
  const cargar = (cargado, setFlag, fn) => {
    if (cargado) return;
    setFlag(true);
    indFalloEnCarga = false;
    Promise.resolve(fn())
      .catch(() => { indFalloEnCarga = true; })
      .then(() => { if (indFalloEnCarga) setFlag(false); });
  };
  if (tab === 'datos')      cargar(indDatosLoaded,      v => indDatosLoaded = v,      loadDatosMensuales);
  if (tab === 'catalogo')   cargar(indCatalogoLoaded,   v => indCatalogoLoaded = v,   loadCatalogo);
  if (tab === 'cronograma') cargar(indCronogramaLoaded, v => indCronogramaLoaded = v, loadCronograma);
  if (tab === 'resumen')    cargar(indResumenLoaded,    v => indResumenLoaded = v,    loadResumenGestion);
  if (tab === 'dashboard')  cargar(indDashboardLoaded,  v => indDashboardLoaded = v,  loadDashboard);
};

// ── Datos Mensuales ──────────────────────────────────────────────────
let indDatosPeriodo = new Date().toISOString().slice(0, 7);
let indDatosCache = null;

// El id se parametriza porque las pestañas Datos, Resumen y Dashboard montan
// cada una su propio selector de mes y conviven en el DOM al mismo tiempo:
// un id fijo produciria tres elementos con el mismo id y getElementById
// devolveria siempre el de la primera pestaña renderizada.
function indMesInputHtml(id) {
  return `<input type="month" id="${id}" class="ind-select" value="${indDatosPeriodo}">`;
}

async function loadDatosMensuales() {
  const panel = document.getElementById('indPanelDatos');
  panel.innerHTML = `
    <div class="card">
      <div class="card-header">
        <div class="card-title">Datos Mensuales</div>
        <div>${indMesInputHtml('indDatosPeriodoInput')}</div>
      </div>
      <div class="card-body" id="indDatosBody">Cargando…</div>
    </div>`;
  document.getElementById('indDatosPeriodoInput').addEventListener('change', (e) => {
    indCambiarPeriodo(e.target.value, 'datos');
    loadDatosMensuales();
  });
  await indFetchYRenderDatos();
}

async function indFetchYRenderDatos() {
  const body = document.getElementById('indDatosBody');
  try {
    const res = await fetch(`${IND_BASE}/api/get_indicadores.php?periodo=${indDatosPeriodo}`);
    const json = await res.json();
    if (!json.success) { indFallo(); body.innerHTML = `<p>${indEsc(json.error || 'Error al cargar.')}</p>`; return; }
    indDatosCache = json.data;
    body.innerHTML = indRenderTablaDatos(json.data);
    indBindCapturaInputs();
  } catch (e) {
    indFallo();
    body.innerHTML = '<p>Error de red al cargar los indicadores.</p>';
  }
}

function indRenderTablaDatos(indicadores) {
  const teams = ['TEAM A', 'TEAM B', 'TEAM C', 'TEAM D'];
  const filas = indicadores.map(ind => {
    const celdas = teams.map(team => {
      const t = ind.teams[team];
      // Solo es celda de solo lectura cuando NADA se puede capturar. G1.1 es
      // parcial: su numerador lo calcula el sistema pero su denominador se
      // escribe a mano, asi que no puede caer en esta rama o quedaria mudo.
      if (!ind.captura_numerador && !ind.captura_denominador) {
        const val = t.valor === null ? '—' : (ind.tipo_calculo === 'Ratio' ? (t.valor * 100).toFixed(1) + '%' : t.valor);
        const detalle = t.fuente ? `Fuente: ${t.fuente}` + (t.n_registros !== null ? ` · ${t.n_registros} registro(s)` : '') : '';
        return `<td><span class="ind-badge-auto" title="${indEsc(detalle)}">${IND_ICON_BOLT}${indEsc(val)}</span></td>`;
      }
      const n = t.numerador === null ? '' : t.numerador;
      const d = t.denominador === null ? '' : t.denominador;
      // Un Coordinador solo captura su propio team; el backend rechaza el resto,
      // asi que no le ofrecemos un input que siempre fallaria.
      const teamAjeno = IND_ES_COORD && IND_MI_TEAM !== null && team !== IND_MI_TEAM;
      const nBloqueado = !ind.captura_numerador || teamAjeno;
      const dBloqueado = !ind.captura_denominador || teamAjeno;
      const nCelda = nBloqueado && !ind.captura_numerador
        ? `<span class="ind-badge-auto" title="${indEsc(t.fuente || '')}">${IND_ICON_BOLT}${indEsc(n === '' ? '—' : n)}</span>`
        : `<input type="number" step="0.01" class="ind-cap-n" data-codigo="${indEsc(ind.codigo)}" data-team="${team}" value="${n}" ${nBloqueado ? 'disabled' : ''} style="width:58px">`;
      return `<td>
        ${nCelda}
        <span class="ind-cap-sep">/</span>
        <input type="number" step="0.01" class="ind-cap-d" data-codigo="${indEsc(ind.codigo)}" data-team="${team}" value="${d}" ${dBloqueado ? 'disabled' : ''} style="width:58px">
      </td>`;
    }).join('');
    const estadoClase = 'ind-estado-' + ind.estado.replace(/ /g, '_');
    return `<tr>
      <td><span class="ind-code">${indEsc(ind.codigo)}</span></td>
      <td>${indEsc(ind.kpi)}</td>
      ${celdas}
      <td>${ind.resultado_general === null ? '—' : indEsc(ind.resultado_general)}</td>
      <td><span class="ind-estado-badge ${estadoClase}"><span class="dot"></span>${indEsc(ind.estado)}</span></td>
    </tr>`;
  }).join('');

  return `<div class="table-wrap"><table class="ui-table">
    <thead><tr><th>Cód.</th><th>Indicador</th><th>TEAM A</th><th>TEAM B</th><th>TEAM C</th><th>TEAM D</th><th>Resultado</th><th>Estado</th></tr></thead>
    <tbody>${filas}</tbody>
  </table></div>`;
}

/** Identifica un input de captura para poder devolverle el foco tras repintar. */
function indHuellaInput(el) {
  if (!el || !el.dataset || !el.dataset.codigo) return null;
  const clase = el.classList.contains('ind-cap-n') ? 'ind-cap-n' : 'ind-cap-d';
  return { sel: `.${clase}[data-codigo="${el.dataset.codigo}"][data-team="${el.dataset.team}"]`,
           valor: el.value, inicio: el.selectionStart, fin: el.selectionEnd };
}

function indBindCapturaInputs() {
  document.querySelectorAll('.ind-cap-n, .ind-cap-d').forEach(input => {
    input.addEventListener('change', async () => {
      const codigo = input.dataset.codigo;
      const team = input.dataset.team;
      const nEl = document.querySelector(`.ind-cap-n[data-codigo="${codigo}"][data-team="${team}"]`);
      const dEl = document.querySelector(`.ind-cap-d[data-codigo="${codigo}"][data-team="${team}"]`);
      // 'change' se dispara al perder el foco: cuando el usuario tabula del
      // numerador al denominador ya esta escribiendo en el siguiente campo.
      // Guardamos donde estaba y que llevaba escrito para restaurarlo despues
      // del repintado, o el repintado le borraria lo tecleado.
      const foco = indHuellaInput(document.activeElement);
      try {
        const res = await fetch(`${IND_BASE}/api/save_indicador_captura.php`, {
          method: 'POST',
          body: JSON.stringify({
            indicador_codigo: codigo, periodo: indDatosPeriodo, team,
            numerador: nEl ? nEl.value : '', denominador: dEl ? dEl.value : '',
          }),
        });
        const json = await res.json();
        if (!json.success) { alert(json.error || 'Error al guardar.'); return; }
        // Resumen y Dashboard derivan de estos numeros: si ya estaban pintados
        // mostrarian el valor anterior hasta recargar la pagina.
        indInvalidarVistasDerivadas();
        await indFetchYRenderDatos();
        if (foco) {
          const destino = document.querySelector(foco.sel);
          if (destino && !destino.disabled) {
            if (foco.valor !== '' && destino.value !== foco.valor) destino.value = foco.valor;
            destino.focus();
            try { destino.setSelectionRange(foco.inicio, foco.fin); } catch (e) { /* number input sin seleccion */ }
          }
        }
      } catch (e) {
        alert('Error de red al guardar.');
      }
    });
  });
}

// ── Catálogo ────────────────────────────────────────────────────────
async function loadCatalogo() {
  const panel = document.getElementById('indPanelCatalogo');
  panel.innerHTML = `<div class="card"><div class="card-header"><div class="card-title">Catálogo de Indicadores</div></div>
    <div class="card-body" id="indCatalogoBody">Cargando…</div></div>`;
  try {
    const res = await fetch(`${IND_BASE}/api/get_indicadores.php?periodo=${indDatosPeriodo}`);
    const json = await res.json();
    if (!json.success) { indFallo(); document.getElementById('indCatalogoBody').textContent = json.error || 'Error al cargar.'; return; }
    document.getElementById('indCatalogoBody').innerHTML = indRenderCatalogo(json.data);
    if (IND_ES_ADMIN) indBindCatalogoEdicion();
  } catch (e) {
    indFallo();
    document.getElementById('indCatalogoBody').textContent = 'Error de red.';
  }
}

function indRenderCatalogo(indicadores) {
  const filas = indicadores.map(ind => `
    <tr>
      <td><span class="ind-code">${indEsc(ind.codigo)}</span></td>
      <td>${indEsc(ind.gestion_nombre)}</td>
      <td>${indEsc(ind.kpi)}</td>
      <td>${indEsc(ind.tipo_calculo)}</td>
      <td>${indEsc(ind.frecuencia)}</td>
      <td>${ind.automatico ? `<span class="ind-badge-auto">${IND_ICON_BOLT}Automático</span>` : '<span class="ind-badge-manual">Manual</span>'}</td>
      <td>${IND_ES_ADMIN
        ? `<input type="number" step="0.0001" class="ind-cat-meta" data-codigo="${indEsc(ind.codigo)}" value="${ind.meta}" style="width:80px">`
        : ind.meta}</td>
    </tr>`).join('');
  return `<div class="table-wrap"><table class="ui-table">
    <thead><tr><th>Cód.</th><th>Gestión</th><th>Indicador</th><th>Cálculo</th><th>Frecuencia</th><th>Origen</th><th>Meta</th></tr></thead>
    <tbody>${filas}</tbody>
  </table></div>`;
}

function indBindCatalogoEdicion() {
  document.querySelectorAll('.ind-cat-meta').forEach(input => {
    input.addEventListener('change', async () => {
      try {
        const res = await fetch(`${IND_BASE}/api/save_indicador_catalogo.php`, {
          method: 'POST',
          body: JSON.stringify({ codigo: input.dataset.codigo, meta: input.value, activo: true }),
        });
        const json = await res.json();
        if (!json.success) { alert(json.error || 'Error al guardar.'); return; }
        // La meta alimenta % vs Meta y el semaforo en todas las vistas.
        indDatosLoaded = false;
        indInvalidarVistasDerivadas();
      } catch (e) {
        alert('Error de red al guardar.');
      }
    });
  });
}

// ── Cronograma ──────────────────────────────────────────────────────
const IND_GESTIONES = [
  { codigo: 'G1', nombre: 'Gestión Operativa y de Procesos' },
  { codigo: 'G2', nombre: 'Gestión de Personas y Desarrollo' },
  { codigo: 'G3', nombre: 'Gestión de Seguridad y Salud Ocupacional' },
  { codigo: 'G4', nombre: 'Gestión de Mejora Continua e Innovación' },
];
const IND_MESES = ['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12'];
const IND_MES_LABEL = { '2026-06': 'Junio', '2026-07': 'Julio', '2026-08': 'Agosto', '2026-09': 'Setiembre', '2026-10': 'Octubre', '2026-11': 'Noviembre', '2026-12': 'Diciembre' };

async function loadCronograma() {
  const panel = document.getElementById('indPanelCronograma');
  panel.innerHTML = `<div class="card"><div class="card-header"><div class="card-title">Cronograma de Responsables</div></div>
    <div class="card-body" id="indCronogramaBody">Cargando…</div></div>`;
  try {
    const res = await fetch(`${IND_BASE}/api/get_indicadores_cronograma.php`);
    const json = await res.json();
    if (!json.success) { indFallo(); document.getElementById('indCronogramaBody').textContent = json.error || 'Error al cargar.'; return; }
    document.getElementById('indCronogramaBody').innerHTML = indRenderCronograma(json.data);
    // El backend solo acepta escrituras de Administrador/Supervisor; el Coordinador
    // ve la tabla en modo lectura para no ofrecerle un control que siempre fallaria.
    if (!IND_ES_COORD) indBindCronogramaEdicion();
  } catch (e) {
    indFallo();
    document.getElementById('indCronogramaBody').textContent = 'Error de red.';
  }
}

function indTeamLetra(team) { return (team || '').replace('TEAM ', ''); }

function indRenderCronograma(filas) {
  const mapa = {};
  filas.forEach(f => { mapa[f.gestion_codigo + '|' + f.periodo] = f.team; });
  const teams = ['TEAM A', 'TEAM B', 'TEAM C', 'TEAM D'];
  const soloLectura = IND_ES_COORD;
  const filasHtml = IND_GESTIONES.map(g => {
    const celdas = IND_MESES.map(mes => {
      const actual = mapa[g.codigo + '|' + mes] || '';
      if (soloLectura) {
        return `<td>${actual
          ? `<span class="ind-team-badge ind-team-${indTeamLetra(actual)}"><span class="dot"></span>${indEsc(actual)}</span>`
          : '<span class="ind-team-empty">—</span>'}</td>`;
      }
      const opciones = teams.map(t => `<option value="${t}" ${t === actual ? 'selected' : ''}>${t}</option>`).join('');
      return `<td><select class="ind-cron-select ind-select" data-gestion="${g.codigo}" data-periodo="${mes}">${opciones}</select></td>`;
    }).join('');
    return `<tr><td><span class="ind-code">${indEsc(g.codigo)}</span></td><td>${indEsc(g.nombre)}</td>${celdas}</tr>`;
  }).join('');
  const cabecerasMes = IND_MESES.map(m => `<th>${IND_MES_LABEL[m]}</th>`).join('');
  return `<div class="table-wrap"><table class="ui-table">
    <thead><tr><th>Código</th><th>Gestión</th>${cabecerasMes}</tr></thead>
    <tbody>${filasHtml}</tbody>
  </table></div>`;
}

function indBindCronogramaEdicion() {
  document.querySelectorAll('.ind-cron-select').forEach(sel => {
    sel.addEventListener('change', async () => {
      try {
        const res = await fetch(`${IND_BASE}/api/save_indicador_cronograma.php`, {
          method: 'POST',
          body: JSON.stringify({ gestion_codigo: sel.dataset.gestion, periodo: sel.dataset.periodo, team: sel.value }),
        });
        const json = await res.json();
        if (!json.success) alert(json.error || 'Error al guardar.');
      } catch (e) {
        alert('Error de red al guardar.');
      }
    });
  });
}

// ── Resumen Gestión ─────────────────────────────────────────────────
function indAgruparPorGestion(indicadores) {
  const grupos = {};
  indicadores.forEach(ind => {
    if (!grupos[ind.gestion_codigo]) grupos[ind.gestion_codigo] = { nombre: ind.gestion_nombre, indicadores: [] };
    grupos[ind.gestion_codigo].indicadores.push(ind);
  });
  return grupos;
}

async function loadResumenGestion() {
  const panel = document.getElementById('indPanelResumen');
  panel.innerHTML = `
    <div class="card">
      <div class="card-header"><div class="card-title">Resumen Gestión</div><div>${indMesInputHtml('indResumenPeriodoInput')}</div></div>
      <div class="card-body" id="indResumenBody">Cargando…</div>
    </div>`;
  document.getElementById('indResumenPeriodoInput').addEventListener('change', (e) => {
    indCambiarPeriodo(e.target.value, 'resumen');
    indFetchYRenderResumen();
  });
  await indFetchYRenderResumen();
}

async function indFetchYRenderResumen() {
  const body = document.getElementById('indResumenBody');
  try {
    const res = await fetch(`${IND_BASE}/api/get_indicadores.php?periodo=${indDatosPeriodo}`);
    const json = await res.json();
    if (!json.success) { indFallo(); body.textContent = json.error || 'Error al cargar.'; return; }
    const grupos = indAgruparPorGestion(json.data);
    body.innerHTML = Object.keys(grupos).sort().map(codigo => {
      const g = grupos[codigo];
      const conDato = g.indicadores.filter(i => i.pct_vs_meta !== null);
      const promedio = conDato.length ? (conDato.reduce((s, i) => s + i.pct_vs_meta, 0) / conDato.length) : null;
      const filas = g.indicadores.map(i => `
        <tr>
          <td><span class="ind-code">${indEsc(i.codigo)}</span></td><td>${indEsc(i.kpi)}</td>
          <td>${indBarraPct(i.pct_vs_meta)}</td>
          <td><span class="ind-estado-badge ind-estado-${i.estado.replace(/ /g, '_')}"><span class="dot"></span>${indEsc(i.estado)}</span></td>
        </tr>`).join('');
      return `<div class="card ind-gestion-card">
        <div class="card-header ind-gestion-head">
          <div class="card-title">${indEsc(codigo)} — ${indEsc(g.nombre)}</div>
          <div class="avg">${promedio === null ? '<span class="ind-team-empty">Sin dato</span>' : indBarraPct(promedio, true)}</div>
        </div>
        <div class="table-wrap"><table class="ui-table">
          <thead><tr><th>Cód.</th><th>Indicador</th><th>% vs Meta</th><th>Estado</th></tr></thead>
          <tbody>${filas}</tbody>
        </table></div>
      </div>`;
    }).join('');
  } catch (e) {
    indFallo();
    body.textContent = 'Error de red.';
  }
}

// ── Dashboard ───────────────────────────────────────────────────────
let indVistaTeam = ''; // '' = General

async function loadDashboard() {
  const panel = document.getElementById('indPanelDashboard');
  panel.innerHTML = `
    <div class="card">
      <div class="card-header">
        <div class="card-title">Dashboard de Seguimiento</div>
        <div style="display:flex;gap:10px;align-items:center">
          ${indMesInputHtml('indDashPeriodoInput')}
          <select id="indVistaSelect" class="ind-select">
            <option value="">Vista: General</option>
            <option value="TEAM A">Vista: TEAM A</option>
            <option value="TEAM B">Vista: TEAM B</option>
            <option value="TEAM C">Vista: TEAM C</option>
            <option value="TEAM D">Vista: TEAM D</option>
          </select>
        </div>
      </div>
      <div class="card-body" id="indDashboardBody">Cargando…</div>
    </div>`;
  document.getElementById('indDashPeriodoInput').addEventListener('change', (e) => {
    indCambiarPeriodo(e.target.value, 'dashboard');
    indFetchYRenderDashboard();
  });
  document.getElementById('indVistaSelect').addEventListener('change', (e) => {
    indVistaTeam = e.target.value;
    indFetchYRenderDashboard();
  });
  await indFetchYRenderDashboard();
}

async function indFetchYRenderDashboard() {
  const body = document.getElementById('indDashboardBody');
  try {
    const res = await fetch(`${IND_BASE}/api/get_indicadores.php?periodo=${indDatosPeriodo}`);
    const json = await res.json();
    if (!json.success) { indFallo(); body.textContent = json.error || 'Error al cargar.'; return; }
    const grupos = indAgruparPorGestion(json.data);
    body.innerHTML = `<div class="ind-dash-grid">` +
      Object.keys(grupos).sort().map(codigo => {
        const g = grupos[codigo];
        let valores;
        if (indVistaTeam === '') {
          valores = g.indicadores.filter(i => i.pct_vs_meta !== null).map(i => i.pct_vs_meta);
        } else {
          valores = g.indicadores
            .map(i => {
              const t = i.teams[indVistaTeam];
              if (!t || t.valor === null || !i.meta) return null;
              return indPctVsMeta(t.valor, i.meta, i.operador);
            })
            .filter(v => v !== null);
        }
        const promedio = valores.length ? (valores.reduce((s, v) => s + v, 0) / valores.length) : null;
        return `<div class="ind-donut-card">
          ${indDonutSvg(promedio)}
          <div class="ind-donut-info">
            <div class="code">${indEsc(codigo)}</div>
            <div class="name">${indEsc(g.nombre)}</div>
            <div class="sub">${valores.length} indicador${valores.length === 1 ? '' : 'es'} con dato · % promedio de cumplimiento</div>
          </div>
        </div>`;
      }).join('') + `</div>`;
  } catch (e) {
    indFallo();
    body.textContent = 'Error de red.';
  }
}
</script>
</body>
</html>
