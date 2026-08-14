<?php
require_once('../includes/auth.php');
require_operaciones();

$rol       = $_SESSION['user_rol'] ?? '';
$canCreate = in_array($rol, ['Administrador', 'Supervisor'], true);
$canDelete = in_array($rol, ['Administrador', 'Supervisor', 'Coordinador'], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Operaciones · Naves · EstibaDeck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <link rel="stylesheet" href="../css/operaciones.css?v=20260602-futuristic">
</head>
<body>
<div class="overlay" id="overlay"></div>
<div class="shell">
  <?php $sb_base = '..'; include('../includes/sidebar.php'); ?>

  <div class="main-area">
    <?php include('../includes/header.php'); ?>

    <main class="content">
      <div class="op" id="opRoot">

        <section class="op-hero">
          <div>
            <span class="tag">◗ Módulo de Operaciones</span>
            <h1>Naves en operación</h1>
            <p>Programación y seguimiento de naves: estado, ventanas ETB/ATB y ETD/ATD, información adicional por tipo y avances del turno.</p>
          </div>
          <div class="op-hero-right">
            <div class="op-kpis" id="opKpis"></div>
            <?php if ($canCreate): ?>
            <button class="op-btn ghost-light" id="btnNuevaHero">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Nueva nave
            </button>
            <?php endif; ?>
          </div>
        </section>

        <div class="op-toolbar">
          <div class="op-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input id="opSearch" placeholder="Buscar nave por nombre o tipo…" autocomplete="off">
          </div>
          <div class="op-filters" id="opFilters">
            <span class="op-fchip on" data-estado="">Activas</span>
            <span class="op-fchip" data-estado="Programada"><span class="op-fchip-dot" style="background:#38bdf8"></span>Programada</span>
            <span class="op-fchip" data-estado="En Puerto"><span class="op-fchip-dot" style="background:#22c55e"></span>En puerto</span>
            <span class="op-fchip" data-estado="Finalizada"><span class="op-fchip-dot" style="background:#94a3b8"></span>Finalizada</span>
          </div>
          <div class="op-viewtog" id="opViewTog" role="tablist" aria-label="Cambiar vista">
            <button type="button" class="on" data-view="cal" aria-label="Vista calendario">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
              Calendario
            </button>
            <button type="button" data-view="tab" aria-label="Vista tabla">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
              Tabla
            </button>
          </div>
        </div>

        <!-- Vista calendario · Berthing Schedule (muelles × tiempo) -->
        <div class="op-sched" id="opSched">
          <div class="op-sched-nav">
            <h3>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
              Berthing Schedule
            </h3>
            <div class="op-wknav">
              <button type="button" class="op-wkbtn" id="opWkPrev" aria-label="Semana anterior">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
              </button>
              <span class="lbl" id="opWkLbl">—</span>
              <button type="button" class="op-wkbtn" id="opWkNext" aria-label="Semana siguiente">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
              </button>
              <button type="button" class="op-wktoday" id="opWkToday">Hoy</button>
            </div>
          </div>
          <div class="op-sched-scroll">
            <div class="op-sched-head">
              <div class="corner"></div>
              <div class="corner-time"></div>
              <div class="op-quay">Muelle 1</div>
              <div class="op-quay">Muelle 2</div>
              <div class="op-quay">Muelle 3</div>
              <div class="op-quay">Muelle 4</div>
            </div>
            <div class="op-sched-grid" id="opSchedGrid">
              <div class="op-sched-msg">Cargando…</div>
            </div>
            <div id="opUnsched"></div>
            <div class="op-sched-legend" id="opSchedLegend"></div>
          </div>
        </div>

        <!-- Vista tabla (alterna con el calendario) -->
        <div class="op-card" id="opTableCard" style="display:none">
          <table class="op-table">
            <thead>
              <tr>
                <th>Nave</th><th>Tipo</th><th>Muelle</th><th>Estado</th><th>ETB / ATB</th><th>ETD / ATD</th><th></th>
              </tr>
            </thead>
            <tbody id="opTbody">
              <tr><td colspan="7" class="op-loading">Cargando…</td></tr>
            </tbody>
          </table>
        </div>

      </div>

      <?php if ($canCreate): ?>
      <div class="op-modal" id="navModal">
        <div class="op-dialog">

          <div class="op-dialog-hero">
            <div class="op-dialog-hero-bg" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 21c.6.5 1.2 1 2.5 1 1.3 0 1.9-.5 2.5-1 .6.5 1.2 1 2.5 1 1.3 0 1.9-.5 2.5-1 .6.5 1.2 1 2.5 1 1.3 0 1.9-.5 2.5-1 .6.5 1.2 1 2.5 1 1.3 0 1.9-.5 2.5-1"/><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.76"/><path d="M19 13V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6"/><path d="M12 10v4"/><path d="M12 2v3"/></svg>
            </div>
            <button class="op-x" id="navClose" type="button" aria-label="Cerrar">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <span class="op-dialog-tag">◗ Módulo de Operaciones</span>
            <h3>Nueva Nave</h3>
            <p>Registra la nave y su ventana operativa en el muelle.</p>
          </div>

          <div class="op-dialog-body">

            <div class="op-fsection">
              <div class="op-fsection-head">
                <span class="op-fsection-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 21c.6.5 1.2 1 2.5 1 1.3 0 1.9-.5 2.5-1 .6.5 1.2 1 2.5 1 1.3 0 1.9-.5 2.5-1 .6.5 1.2 1 2.5 1 1.3 0 1.9-.5 2.5-1 .6.5 1.2 1 2.5 1 1.3 0 1.9-.5 2.5-1"/><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.76"/><path d="M19 13V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6"/><path d="M12 10v4"/><path d="M12 2v3"/></svg>
                </span>
                <div>
                  <div class="op-fsection-title">Información básica</div>
                  <div class="op-fsection-sub">Identificación y programación de la nave</div>
                </div>
              </div>
              <div class="op-form-grid">
                <div class="op-field full">
                  <label>Nombre de la nave <span class="op-req">*</span></label>
                  <div class="op-input">
                    <svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
                    <input class="op-control has-ic" id="nv-nombre" placeholder="Ej. MSC VALENCIA" autocomplete="off" style="text-transform:uppercase">
                  </div>
                </div>
                <div class="op-field">
                  <label>Tipo de nave <span class="op-req">*</span></label>
                  <div class="op-input">
                    <svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
                    <select class="op-control has-ic op-select" id="nv-tipo"><option value="">Seleccionar…</option></select>
                    <svg class="op-input-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                  </div>
                </div>
                <div class="op-field">
                  <label>Zona</label>
                  <div class="op-input">
                    <svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    <select class="op-control has-ic op-select" id="nv-muelle">
                      <option value="">Seleccionar…</option>
                      <option value="Berth 01">Berth 01</option>
                      <option value="Berth 02">Berth 02</option>
                      <option value="Berth 03">Berth 03</option>
                      <option value="Berth 3.5">Berth 3.5</option>
                      <option value="Berth 04">Berth 04</option>
                    </select>
                    <svg class="op-input-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                  </div>
                </div>
                <div class="op-field full">
                  <label>Actividad <span style="font-weight:500;color:var(--faint);text-transform:none;letter-spacing:0">(se hereda al registrar indicadores)</span></label>
                  <div class="op-input">
                    <svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <select class="op-control has-ic op-select" id="nv-actividad"><option value="">Seleccionar…</option></select>
                    <svg class="op-input-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                  </div>
                </div>
                <div class="op-field">
                  <label>ETB / ATB <span class="op-req">*</span></label>
                  <div class="op-input">
                    <svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    <input type="datetime-local" class="op-control has-ic" id="nv-eta">
                  </div>
                </div>
                <div class="op-field">
                  <label>ETD / ATD <span class="op-req">*</span></label>
                  <div class="op-input">
                    <svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <input type="datetime-local" class="op-control has-ic" id="nv-etd">
                  </div>
                </div>
                <div class="op-field full">
                  <label>Estado inicial</label>
                  <div class="op-segment op-segment--3" id="nv-estado-seg">
                    <button type="button" class="op-seg on" data-v="Programada"><span class="d"></span>Programada</button>
                    <button type="button" class="op-seg" data-v="En Puerto"><span class="d"></span>En puerto</button>
                    <button type="button" class="op-seg" data-v="Finalizada"><span class="d"></span>Finalizada</button>
                  </div>
                  <input type="hidden" id="nv-estado" value="Programada">
                </div>
              </div>
            </div>

            <div class="op-fsection">
              <div class="op-fsection-head">
                <span class="op-fsection-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                </span>
                <div>
                  <div class="op-fsection-title">Operaciones</div>
                  <div class="op-fsection-sub">Carga e información del operativo según el tipo de nave</div>
                </div>
              </div>
              <div class="op-oper-empty" id="nv-oper-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m16 12-4-4-4 4"/><path d="M12 16V8"/></svg>
                <p>Selecciona el <b>tipo de nave</b> en el bloque anterior para configurar la operación.</p>
              </div>
              <div class="op-oper-active" id="nv-oper-active" style="display:none"></div>
            </div>

          </div>
          <div class="op-dialog-foot">
            <span class="op-foot-hint"><span class="op-req">*</span> Campos obligatorios</span>
            <div class="op-foot-actions">
              <button class="op-btn" id="navCancel" type="button">Cancelar</button>
              <button class="op-btn primary" id="navSave" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Guardar nave
              </button>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </main>
  </div>
</div>

<script>window.OP_CTX = { rol: <?= json_encode($rol) ?>, canCreate: <?= $canCreate ? 'true' : 'false' ?>, canDelete: <?= $canDelete ? 'true' : 'false' ?> };</script>
<script src="../js/operaciones.js"></script>
<script>
(function () {
  var $ = OP.$, esc = OP.esc, opApi = OP.opApi, toast = OP.toast,
      fmtDateTime = OP.fmtDateTime, estadoChip = OP.estadoChip, fromLocalInput = OP.fromLocalInput;
  var CTX = window.OP_CTX || {};
  var naves = [], tipos = [], curEstado = '', searchQ = '';
  var view = 'cal';          // 'cal' (calendario) | 'tab' (tabla)
  var weekStart = null;      // Date 00:00 del lunes de la semana visible

  // ── Berthing Schedule ────────────────────────────────────────────────
  // Paleta por tipo de nave. Clave = nombre normalizado (sin acentos/min.).
  // Cualquier tipo no listado cae en COLOR_DEFAULT.
  var TYPE_COLORS = {
    granelera:         '#047857',   // verde oscuro
    'ro-ro':           '#ef4444',   // rojo
    containera:        '#0066ff',   // azul eléctrico
    'carga general':   '#ff6600',   // naranja neón
    cementero:         '#94a3b8',   // cromo plateado
    sales:             '#ff6600',
  };
  var COLOR_DEFAULT = '#475569';
  var HOUR_MS = 3600 * 1000, DAY_MS = 24 * HOUR_MS;
  var DAY_PX = 150;          // alto en px de cada fila de día (debe coincidir con --op-day)
  var DOW = ['LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM'];
  var MON = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

  function norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
  }
  function typeColor(tipo) { return TYPE_COLORS[norm(tipo)] || COLOR_DEFAULT; }

  // Mapea el texto de muelle a columna 0..3. Un muelle "X.5" (ej. 3.5) se
  // asocia a la columna del entero inferior (3) pero se dibuja a caballo sobre
  // el límite con el muelle siguiente (ver esMuelleHalf + el desplazamiento).
  // Devuelve -1 si no hay muelle reconocible.
  function muelleCol(muelle) {
    var m = String(muelle || '').match(/(\d+(?:\.\d+)?)/);
    if (!m) return -1;
    var n = parseFloat(m[1]);
    if (n >= 1 && n < 2) return 0;   // Muelle 1
    if (n >= 2 && n < 3) return 1;   // Muelle 2
    if (n >= 3 && n < 4) return 2;   // Muelle 3 (incluye 3.5 → se dibuja a caballo con el 4)
    if (n >= 4 && n < 5) return 3;   // Muelle 4
    return -1;
  }

  // ¿El muelle es "X.5"? Esas naves se dibujan centradas en el límite entre su
  // columna y la siguiente (p. ej. 3.5 → entre Muelle 3 y Muelle 4).
  function esMuelleHalf(muelle) {
    var m = String(muelle || '').match(/(\d+(?:\.\d+)?)/);
    return m ? (parseFloat(m[1]) % 1) === 0.5 : false;
  }

  // Parser local: 'YYYY-MM-DD HH:MM:SS' → Date en hora local. null si inválido.
  function parseDT(s) {
    var m = String(s == null ? '' : s).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    if (!m) return null;
    return new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], 0, 0);
  }
  function startOfDay(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }
  function mondayOf(d) {
    var x = startOfDay(d);
    var dow = (x.getDay() + 6) % 7;   // 0 = lunes
    x.setDate(x.getDate() - dow);
    return x;
  }
  function addDays(d, n) { var x = new Date(d); x.setDate(x.getDate() + n); return x; }

  // Inicio visual de la barra: ATB (atraque) si existe, si no ETA. Fin = ETD.
  // Nave en curso (atracada/operando con ATB pero sin ETD todavía): se dibuja
  // como ocupación vigente, extendiéndose hasta "ahora" hasta que se Culmine.
  function shipWindow(n) {
    var ini = parseDT(n.etb) || parseDT(n.eta);
    var fin = parseDT(n.etd);
    if (ini && !fin && (n.estado === 'En Operaciones' || n.estado === 'En Puerto')) {
      var now = new Date();
      fin = now > ini ? now : new Date(ini.getTime() + HOUR_MS);
    }
    return { ini: ini, fin: fin };
  }

  function matchSearch(n) {
    var q = searchQ.trim().toLowerCase();
    if (!q) return true;
    return (n.nombre + ' ' + n.tipo_nave + ' ' + (n.muelle || '')).toLowerCase().indexOf(q) !== -1;
  }

  // Reparte en "carriles" las naves que se solapan en tiempo dentro de un muelle.
  // Entra una lista [{ini,fin,...}] y asigna .lane y marca el total .lanes.
  function assignLanes(items) {
    items.sort(function (a, b) { return a.s - b.s; });
    var laneEnds = [];   // fin (ms) de la última barra en cada carril
    items.forEach(function (it) {
      var placed = false;
      for (var i = 0; i < laneEnds.length; i++) {
        if (it.s >= laneEnds[i]) { it.lane = i; laneEnds[i] = it.e; placed = true; break; }
      }
      if (!placed) { it.lane = laneEnds.length; laneEnds.push(it.e); }
    });
    var total = laneEnds.length || 1;
    items.forEach(function (it) { it.lanes = total; });
    return items;
  }

  function isoWeekNum(d) {
    var tmp = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    var day = tmp.getUTCDay() || 7;
    tmp.setUTCDate(tmp.getUTCDate() + 4 - day);
    var yearStart = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1));
    return Math.ceil(((tmp - yearStart) / 86400000 + 1) / 7);
  }
  function fmtWeekLabel(ws) {
    var we = addDays(ws, 6);
    var a = ws.getDate() + ' ' + MON[ws.getMonth()];
    var b = we.getDate() + ' ' + MON[we.getMonth()];
    return a + ' – ' + b + ' ' + we.getFullYear();
  }

  function legendHtml() {
    var used = {};
    naves.forEach(function (n) { used[norm(n.tipo_nave)] = n.tipo_nave; });
    var keys = Object.keys(used);
    if (!keys.length) keys = Object.keys(TYPE_COLORS);
    var seenColor = {};
    var legs = keys.map(function (k) {
      var col = TYPE_COLORS[k] || COLOR_DEFAULT;
      var label = used[k] || (k.charAt(0).toUpperCase() + k.slice(1));
      if (col === COLOR_DEFAULT && seenColor[col]) return '';
      seenColor[col] = 1;
      return '<span class="op-leg"><i style="background:' + col + '"></i>' + esc(label) + '</span>';
    }).filter(Boolean).join('');
    var ests = '<span class="op-leg-est"><span class="est" style="background:#22c55e"></span>En puerto</span>' +
      '<span class="op-leg-est"><span class="est" style="background:#38bdf8"></span>Programada</span>' +
      '<span class="op-leg-est"><span class="est" style="background:#94a3b8"></span>Finalizada</span>';
    return legs + '<span class="op-leg-sep"></span>' + ests;
  }
  var EST_DOT = {
    'En Puerto':     '#22c55e',   // verde neón
    'En Operaciones':'#f59e0b',   // ámbar — operando
    'Programada':    '#38bdf8',   // cian eléctrico
    'Finalizada':    '#94a3b8'    // cromo plateado
  };

  function renderSchedule() {
    var grid = $('opSchedGrid');
    if (!grid) return;
    if (!weekStart) weekStart = mondayOf(new Date());
    var ws = weekStart, weMs = ws.getTime() + 7 * DAY_MS;
    $('opWkLbl').innerHTML = fmtWeekLabel(ws) + ' <span class="op-wknum">S' + isoWeekNum(ws) + '</span>';
    $('opSchedLegend').innerHTML = legendHtml();

    var gridH = 7 * DAY_PX;

    // Eje de fechas (columna 1 izq.): un bloque por día.
    var axis = '<div class="op-sched-axis" style="height:' + gridH + 'px">';
    for (var d = 0; d < 7; d++) {
      var day = addDays(ws, d);
      var top = d * DAY_PX;
      axis += '<div class="op-axis-day" style="position:absolute;left:0;right:0;top:' + top + 'px;height:' + DAY_PX + 'px">' +
        '<span class="dow">' + DOW[d] + '</span>' +
        '<span class="dnum">' + day.getDate() + '</span>' +
        '<span class="mon">' + MON[day.getMonth()] + '</span>' +
      '</div>';
      axis += '<div class="op-axis-mid" style="top:' + (top + DAY_PX / 2) + 'px"></div>';
    }
    axis += '</div>';

    // Eje de horas (columna 2): 00-12 / 12-24 por día.
    var axisTime = '<div class="op-sched-axis-time" style="height:' + gridH + 'px">';
    for (var d = 0; d < 7; d++) {
      var top = d * DAY_PX;
      axisTime += '<div class="op-axis-time-day" style="top:' + top + 'px;height:' + DAY_PX + 'px">' +
        '<span class="h-range">00-12</span>' +
        '<span class="h-range">12-24</span>' +
      '</div>';
      axisTime += '<div class="op-axis-time-mid" style="top:' + (top + DAY_PX / 2) + 'px"></div>';
    }
    axisTime += '</div>';

    // 4 carriles de muelle (1, 2, 3, 4). Las naves de un muelle "X.5" (ej. 3.5)
    // viven en la columna del entero inferior pero se desplazan media columna a la
    // derecha para quedar centradas en el límite con el muelle siguiente.
    var todayS = startOfDay(new Date()).getTime();
    var lanesHtml = '';
    for (var col = 0; col < 4; col++) {
      var here = [];
      naves.forEach(function (n) {
        if (muelleCol(n.muelle) !== col) return;
        var w = shipWindow(n);
        if (!w.ini || !w.fin) return;
        var s = w.ini.getTime(), e = w.fin.getTime();
        if (e <= s) e = s + HOUR_MS;            // ventana mínima de 1h para que se vea
        if (e <= ws.getTime() || s >= weMs) return;  // fuera de la semana
        here.push({ n: n, s: s, e: e, half: esMuelleHalf(n.muelle) });
      });
      // Las naves del muelle entero y las "X.5" no compiten por carril: aparte.
      var mainItems = here.filter(function (x) { return !x.half; });
      var halfItems = here.filter(function (x) { return x.half; });
      assignLanes(mainItems);
      assignLanes(halfItems);

      var guides = '';
      for (var g = 0; g <= 7; g++) {
        guides += '<div class="gl" style="top:' + (g * DAY_PX) + 'px"></div>';
        if (g < 7) guides += '<div class="glm" style="top:' + (g * DAY_PX + DAY_PX / 2) + 'px"></div>';
      }
      // banda del día de hoy (si cae en la semana visible)
      var todayBand = '';
      if (todayS >= ws.getTime() && todayS < weMs) {
        var bt = ((todayS - ws.getTime()) / DAY_MS) * DAY_PX;
        todayBand = '<div class="op-today-band" style="top:' + bt + 'px;height:' + DAY_PX + 'px"></div>';
      }

      var barHtml = function (it) {
        var n = it.n;
        var sClamp = Math.max(it.s, ws.getTime());
        var eClamp = Math.min(it.e, weMs);
        var top = ((sClamp - ws.getTime()) / DAY_MS) * DAY_PX;
        var h = Math.max(((eClamp - sClamp) / DAY_MS) * DAY_PX, 30);
        var wPct = 100 / it.lanes;
        var leftPct = it.lane * wPct;
        var col2 = typeColor(n.tipo_nave);
        var dim = matchSearch(n) ? '' : ' dim';
        var tiny = h < 56 ? ' tiny' : '';
        var dot = EST_DOT[n.estado] || '#94a3b8';
        var times = fmtDateTime(n.etb || n.eta) + ' → ' + (n.etd ? fmtDateTime(n.etd) : 'en curso');
        // "X.5": media columna a la derecha → centrada en el límite con el muelle siguiente.
        var shift = it.half ? 'transform:translateX(50%);' : '';
        var z = 10 + it.lane + (it.half ? 20 : 0);
        var da = n.datos_adicionales || {};
        var planned = da.cantidad_total != null ? da.cantidad_total + ' TM'
                    : da.teus           != null ? da.teus + ' TEUs'
                    : da.vehiculos      != null ? da.vehiculos + ' Veh.'
                    : '';
        var tipoop  = da.tipo_operacion || '';
        var detailHtml = h >= 90
          ? '<span class="sh-detail">' +
              (n.actividad ? '<span class="sh-row">▸ ' + esc(n.actividad) + '</span>' : '') +
              (planned || tipoop ? '<span class="sh-row">📦 ' + (planned ? esc(planned) : '') + (planned && tipoop ? ' · ' : '') + (tipoop ? esc(tipoop) : '') + '</span>' : '') +
            '</span>'
          : '';
        return '<a class="op-ship' + dim + tiny + '" href="operaciones_nave.php?id=' + n.id + '" ' +
          'title="' + esc(n.nombre) + ' · ' + esc(n.tipo_nave) + (it.half ? ' · ' + esc(n.muelle) : '') + '" ' +
          'style="top:' + top + 'px;height:' + h + 'px;left:calc(' + leftPct + '% + 3px);' +
          'width:calc(' + wPct + '% - 6px);background:' + col2 + ';' + shift + 'z-index:' + z + '">' +
          '<span class="est" style="background:' + dot + '"></span>' +
          '<span class="nm">' + esc(n.nombre) + '</span>' +
          '<span class="sh-mid">' +
            '<span class="ty">' + esc(n.tipo_nave) + (n.muelle ? ' · ' + esc(n.muelle) : '') + '</span>' +
            detailHtml +
          '</span>' +
          '<span class="tm">' + times + '</span>' +
        '</a>';
      };
      var bars = mainItems.map(barHtml).join('') + halfItems.map(barHtml).join('');

      lanesHtml += '<div class="op-mlane" style="height:' + gridH + 'px">' + guides + todayBand + bars + '</div>';
    }

    grid.innerHTML = axis + axisTime + lanesHtml;
    renderUnscheduled();
  }

  // Naves sin muelle reconocible o sin ventana de fechas válida → chips.
  function renderUnscheduled() {
    var box = $('opUnsched');
    if (!box) return;
    var list = naves.filter(function (n) {
      var w = shipWindow(n);
      return muelleCol(n.muelle) === -1 || !w.ini || !w.fin;
    });
    if (!list.length) { box.innerHTML = ''; return; }
    var chips = list.map(function (n) {
      var col = typeColor(n.tipo_nave);
      var dot = EST_DOT[n.estado] || '#94a3b8';
      var dim = matchSearch(n) ? '' : ' dim';
      var sub = n.muelle ? esc(n.muelle) : 'sin muelle';
      return '<a class="op-uchip' + dim + '" href="operaciones_nave.php?id=' + n.id + '" style="background:' + col + '">' +
        '<span class="est" style="background:' + dot + '"></span>' +
        esc(n.nombre) + ' <small>' + sub + '</small></a>';
    }).join('');
    box.innerHTML = '<div class="op-unsched">' +
      '<div class="op-unsched-h">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
        'Sin programar (sin muelle o sin fechas)</div>' +
      '<div class="op-unsched-list">' + chips + '</div></div>';
  }

  function rowHtml(n) {
    var del = CTX.canDelete
      ? '<button class="op-btn danger sm" type="button" data-del="' + n.id + '" title="Eliminar nave">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>' +
          'Eliminar</button>'
      : '';
    return '<tr>' +
      '<td><div class="nave-name">' + esc(n.nombre) + '</div></td>' +
      '<td class="op-muted">' + esc(n.tipo_nave) + '</td>' +
      '<td class="op-muted">' + (n.muelle ? esc(n.muelle) : '<span style="color:var(--faint)">—</span>') + '</td>' +
      '<td>' + estadoChip(n.estado) + '</td>' +
      '<td class="op-mono">' + fmtDateTime(n.etb || n.eta) + '</td>' +
      '<td class="op-mono">' + fmtDateTime(n.etd) + '</td>' +
      '<td><div style="display:flex;gap:10px;align-items:center;justify-content:flex-end">' +
        '<a class="op-rowlink" href="operaciones_nave.php?id=' + n.id + '">Ver ' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg></a>' +
        del +
      '</div></td>' +
    '</tr>';
  }

  // Elimina una nave (roles operativos). El backend borra en cascada sus avances.
  async function eliminarNave(id) {
    var n = naves.filter(function (x) { return String(x.id) === String(id); })[0];
    var nombre = n ? n.nombre : ('#' + id);
    if (!confirm('¿Eliminar la nave "' + nombre + '"?\n' +
      'Se borrarán también sus reportes de turno. Esta acción no se puede deshacer.')) return;
    try {
      await opApi('naves/' + id, { method: 'DELETE' });
      toast('Nave eliminada', 'success');
      load(curEstado);
    } catch (e) { toast(e.message, 'error'); }
  }

  function renderTable() {
    var tb = $('opTbody');
    var list = naves.filter(matchSearch);
    if (!list.length) { tb.innerHTML = '<tr><td colspan="7" class="op-empty-cell">Sin naves para este filtro.</td></tr>'; return; }
    tb.innerHTML = list.map(rowHtml).join('');
  }
  // Pinta la vista activa. El calendario atenúa las no-coincidentes; la tabla filtra.
  function render() {
    if (view === 'cal') renderSchedule();
    else renderTable();
  }

  function kpi(lbl, val, sub) {
    return '<div class="op-kpi"><div class="lbl">' + esc(lbl) + '</div><div class="val">' + val +
      (sub ? ' <small>' + esc(sub) + '</small>' : '') + '</div></div>';
  }
  function renderKpis() {
    var enPuerto   = naves.filter(function (n) { return n.estado === 'En Puerto'; }).length;
    var prog       = naves.filter(function (n) { return n.estado === 'Programada'; }).length;
    var finalizadas = naves.filter(function (n) { return n.estado === 'Finalizada'; }).length;
    $('opKpis').innerHTML =
      kpi('En puerto', enPuerto) +
      kpi('Programadas', prog) +
      kpi('Total activas', naves.length) +
      kpi('Finalizadas', finalizadas);
  }

  function showLoading() {
    if (view === 'cal') { var g = $('opSchedGrid'); if (g) g.innerHTML = '<div class="op-sched-msg">Cargando…</div>'; }
    else $('opTbody').innerHTML = '<tr><td colspan="7" class="op-loading">Cargando…</td></tr>';
  }
  function showError(msg) {
    if (view === 'cal') {
      var g = $('opSchedGrid'); if (g) g.innerHTML = '<div class="op-sched-msg err">' + esc(msg) + '</div>';
      var u = $('opUnsched'); if (u) u.innerHTML = '';
    } else {
      $('opTbody').innerHTML = '<tr><td colspan="7" class="op-error">' + esc(msg) + '</td></tr>';
    }
  }

  async function load(estado) {
    curEstado = estado;
    showLoading();
    try {
      var d = await opApi('naves', estado ? { query: { estado: estado } } : {});
      naves = d.data || [];
      render();
      if (estado === '') renderKpis();
    } catch (e) {
      showError(e.message);
      if (estado === '') $('opKpis').innerHTML = '';
    }
  }

  // ── Toggle de vista (Calendario / Tabla) ──
  function setView(v) {
    view = v;
    $('opSched').style.display = (v === 'cal') ? '' : 'none';
    $('opTableCard').style.display = (v === 'tab') ? '' : 'none';
    document.querySelectorAll('#opViewTog button').forEach(function (b) {
      b.classList.toggle('on', b.getAttribute('data-view') === v);
    });
    render();
  }
  var vt = $('opViewTog');
  if (vt) vt.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-view]'); if (!b) return;
    setView(b.getAttribute('data-view'));
  });

  // ── Navegación de semana ──
  weekStart = mondayOf(new Date());
  function shiftWeek(n) { weekStart = addDays(weekStart, n * 7); if (view === 'cal') renderSchedule(); }
  var wp = $('opWkPrev'); if (wp) wp.addEventListener('click', function () { shiftWeek(-1); });
  var wn = $('opWkNext'); if (wn) wn.addEventListener('click', function () { shiftWeek(1); });
  var wt = $('opWkToday'); if (wt) wt.addEventListener('click', function () { weekStart = mondayOf(new Date()); if (view === 'cal') renderSchedule(); });

  document.querySelectorAll('.op-fchip').forEach(function (ch) {
    ch.addEventListener('click', function () {
      document.querySelectorAll('.op-fchip').forEach(function (x) { x.classList.remove('on'); });
      ch.classList.add('on');
      load(ch.getAttribute('data-estado') || '');
    });
  });
  $('opSearch').addEventListener('input', function (e) { searchQ = e.target.value; render(); });

  // Eliminar nave desde la vista tabla (botón por fila; roles operativos).
  var tbodyEl = $('opTbody');
  if (tbodyEl) tbodyEl.addEventListener('click', function (e) {
    var b = e.target.closest('[data-del]'); if (!b) return;
    eliminarNave(b.getAttribute('data-del'));
  });

  if (CTX.canCreate) {
    var modal = $('navModal');
    async function loadTipos() {
      try {
        var d = await opApi('tipos-nave'); tipos = d.data || [];
        $('nv-tipo').innerHTML = '<option value="">Seleccionar…</option>' +
          tipos.map(function (t) { return '<option value="' + t.id + '">' + esc(t.nombre) + '</option>'; }).join('');
      } catch (e) { $('nv-tipo').innerHTML = '<option value="">Error al cargar</option>'; toast('No se pudieron cargar los tipos: ' + e.message, 'error'); }
    }
    var actividades = [];
    async function loadActividades() {
      try {
        var d = await opApi('tallyman/actividades'); actividades = d.data || [];
        renderActividadPorTipo();
      } catch (e) { $('nv-actividad').innerHTML = '<option value="">Error al cargar</option>'; }
    }
    // Filtro de ACTIVIDAD por TIPO DE NAVE (mismo criterio que el registro de muelle):
    // prefijos (en minúsculas) de los nombres de actividad que aplican a cada tipo.
    // Los tipos que no aparecen aquí muestran todo el catálogo.
    var ACTIVIDADES_POR_TIPO = {
      'ro-ro': ['car loading', 'car dispatch'],
      'containera': ['containers loading', 'container deconsolidation', 'containers dispatch'],
      'granelera': ['corn loading', 'salt loading', 'soybean unloading', 'bulk carrier loading'],
      'carga general': [
        'general cargo loading',
        'cement big bags loading',
        'nitrate big bags loading',
        'urea big bags loading',
        'steel balls big bags loading'
      ]
    };
    // Rellena #nv-actividad con las actividades que aplican al tipo seleccionado,
    // conservando la selección previa si sigue siendo válida.
    function renderActividadPorTipo() {
      var sel = $('nv-actividad'); if (!sel) return;
      var id = $('nv-tipo').value;
      var t = tipos.filter(function (x) { return String(x.id) === String(id); })[0];
      var prefijos = ACTIVIDADES_POR_TIPO[norm(t ? t.nombre : '')];
      var list = !prefijos ? actividades.slice() : actividades.filter(function (a) {
        var nm = String(a.nombre).toLowerCase();
        return prefijos.some(function (p) { return nm.indexOf(p) === 0; });
      });
      var keep = sel.value;
      sel.innerHTML = '<option value="">Seleccionar…</option>' +
        list.map(function (a) { return '<option value="' + a.id + '">' + esc(a.nombre) + '</option>'; }).join('');
      if (keep && [].some.call(sel.options, function (o) { return o.value === keep; })) sel.value = keep;
    }
    var segWrap = $('nv-estado-seg');
    if (segWrap) segWrap.addEventListener('click', function (e) {
      var b = e.target.closest('.op-seg'); if (!b) return;
      setEstado(b.getAttribute('data-v'));
    });
    function setEstado(v) {
      $('nv-estado').value = v;
      if (segWrap) segWrap.querySelectorAll('.op-seg').forEach(function (x) {
        x.classList.toggle('on', x.getAttribute('data-v') === v);
      });
    }
    // ── Bloque 2 · Operaciones (campos por tipo de nave) ──
    function norm(s) {
      // Quita acentos (rango de marcas combinantes U+0300–U+036F) y normaliza a minúsculas.
      return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
    }
    function operacionSegHtml(idBase) { return ''; }
    // Plantillas por tipo. Cada una devuelve el HTML del cuerpo y, opcionalmente,
    // engancha su reactividad interna en wire().
    var OPER_TPL = {
      containera: {
        html: function () {
          return '<div class="op-form-grid">' +
            '<div class="op-field full">' +
              '<label>TEU’s a bordo <span class="op-req">*</span></label>' +
              '<div class="op-input">' +
                '<svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="1"/><path d="M2 11h20"/><path d="M7 7V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"/></svg>' +
                '<input type="number" min="0" step="1" class="op-control has-ic" id="op-teus" placeholder="Ej. 1200">' +
              '</div>' +
            '</div>' +
            operacionSegHtml('op-tipoop') +
          '</div>';
        },
        wire: function () {}
      },
      granelera: {
        html: function () {
          return '<div class="op-form-grid">' +
            '<div class="op-field full">' +
              '<label>Cantidad total (TM) <span class="op-req">*</span></label>' +
              '<div class="op-input">' +
                '<svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M7 12h10"/><path d="M10 18h4"/></svg>' +
                '<input type="number" min="0" step="any" class="op-control has-ic" id="op-cant" placeholder="Ej. 25000">' +
              '</div>' +
            '</div>' +
            operacionSegHtml('op-tipoop') +
          '</div>';
        },
        wire: function () {}
      },
      'ro-ro': {
        html: function () {
          return '<div class="op-form-grid">' +
            '<div class="op-field full">' +
              '<label>Cantidad total de vehículos <span class="op-req">*</span></label>' +
              '<div class="op-input">' +
                '<svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 16H9m10 0h3v-3.15a1 1 0 0 0-.84-.99L16 11l-2.7-3.6a1 1 0 0 0-.8-.4H5.24a2 2 0 0 0-1.8 1.1l-.8 1.63A6 6 0 0 0 2 12.42V16h2"/><circle cx="6.5" cy="16.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/></svg>' +
                '<input type="number" min="0" step="1" class="op-control has-ic" id="op-vehiculos" placeholder="Ej. 850">' +
              '</div>' +
            '</div>' +
            operacionSegHtml('op-tipoop') +
          '</div>';
        },
        wire: function () {}
      }
    };

    // Helper genérico para segmentos (marca el botón activo y opcional hidden).
    function setSeg(segEl, v, hiddenId) {
      if (!segEl) return;
      segEl.querySelectorAll('.op-seg').forEach(function (x) {
        x.classList.toggle('on', x.getAttribute('data-v') === v);
      });
      if (hiddenId && $(hiddenId)) $(hiddenId).value = v;
    }

    function renderOper() {
      var id = $('nv-tipo').value;
      renderActividadPorTipo();   // refiltra la ACTIVIDAD según el tipo elegido
      var empty = $('nv-oper-empty'), active = $('nv-oper-active');
      if (!empty || !active) return;
      if (!id) { empty.style.display = ''; active.style.display = 'none'; active.innerHTML = ''; return; }
      var t = tipos.filter(function (x) { return String(x.id) === String(id); })[0];
      var nombre = t ? t.nombre : 'Tipo seleccionado';
      empty.style.display = 'none'; active.style.display = '';

      // Granelera, Cementero, Sales y Carga General comparten el operativo de
      // granel (cantidad TM + operación). El chip de arriba
      // muestra el nombre real del tipo (p. ej. "Tipo: Cementero").
      var key = norm(nombre);
      if (key === 'cementero' || key === 'sales' || key === 'carga general') key = 'granelera';
      else if (key === 'portacontenedores') key = 'containera';
      else if (key === 'roro') key = 'ro-ro'; // por si el nombre llega sin guion
      var tpl = OPER_TPL[key];

      var head =
        '<div class="op-oper-typetag">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
          'Tipo: <b style="margin-left:3px">' + esc(nombre) + '</b>' +
        '</div>';

      if (!tpl) {
        active.innerHTML = head +
          '<p class="op-oper-note">Aún no se han definido los campos de operación para este tipo de nave.</p>';
        return;
      }

      active.innerHTML = head + tpl.html();

      // Engancha los segmentos "Tipo de operación" (comunes a las plantillas).
      active.querySelectorAll('.op-oper-seg').forEach(function (seg) {
        seg.addEventListener('click', function (e) {
          var b = e.target.closest('.op-seg'); if (!b) return;
          setSeg(seg, b.getAttribute('data-v'), seg.getAttribute('data-target'));
        });
      });
      if (typeof tpl.wire === 'function') tpl.wire();
    }

    // Recolecta los datos del operativo según el tipo activo.
    // Devuelve { ok, datos, error, focusId } — datos es null si no hay tipo soportado.
    function collectOper() {
      var id = $('nv-tipo').value;
      var t = tipos.filter(function (x) { return String(x.id) === String(id); })[0];
      var key = norm(t ? t.nombre : '');
      if (key === 'cementero' || key === 'sales' || key === 'carga general') key = 'granelera';
      else if (key === 'portacontenedores') key = 'containera';
      else if (key === 'roro') key = 'ro-ro';

      if (key === 'ro-ro') {
        var veh = $('op-vehiculos') ? $('op-vehiculos').value.trim() : '';
        if (veh === '') return { ok: false, error: 'Ingresa la cantidad total de vehículos', focusId: 'op-vehiculos' };
        return { ok: true, datos: { vehiculos: Number(veh) } };
      }

      if (key === 'containera') {
        var teus = $('op-teus') ? $('op-teus').value.trim() : '';
        if (teus === '') return { ok: false, error: 'Ingresa los TEU\'s a bordo', focusId: 'op-teus' };
        return { ok: true, datos: { teus: Number(teus) } };
      }

      if (key === 'granelera') {
        var cant  = $('op-cant') ? $('op-cant').value.trim() : '';
        if (cant === '') return { ok: false, error: 'Ingresa la cantidad total', focusId: 'op-cant' };
        return { ok: true, datos: { cantidad_total: Number(cant) } };
      }

      // Tipo sin operativo definido aún: no se envían datos adicionales.
      return { ok: true, datos: null };
    }
    var selTipo = $('nv-tipo'); if (selTipo) selTipo.addEventListener('change', renderOper);
    $('nv-nombre').addEventListener('input', function () { var s = this.selectionStart, e = this.selectionEnd; this.value = this.value.toUpperCase(); this.setSelectionRange(s, e); });
    function openModal() {
      $('nv-nombre').value = ''; $('nv-muelle').value = ''; $('nv-tipo').value = ''; $('nv-actividad').value = ''; setEstado('Programada'); renderOper();
      $('nv-eta').value = ''; $('nv-etd').value = '';
      if (!tipos.length) loadTipos();
      if (!actividades.length) loadActividades();
      modal.classList.add('open'); setTimeout(function () { $('nv-nombre').focus(); }, 50);
    }
    function closeModal() { modal.classList.remove('open'); }
    var bH = $('btnNuevaHero'); if (bH) bH.addEventListener('click', openModal);
    $('navClose').addEventListener('click', closeModal);
    $('navCancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

    $('navSave').addEventListener('click', async function () {
      var nombre = $('nv-nombre').value.trim();
      var tipo   = $('nv-tipo').value;
      var eta    = $('nv-eta').value;
      var etd    = $('nv-etd').value;
      if (!nombre) { toast('El nombre de la nave es obligatorio', 'error'); $('nv-nombre').focus(); return; }
      if (!tipo)   { toast('Selecciona el tipo de nave', 'error'); $('nv-tipo').focus(); return; }
      if (!eta)    { toast('La fecha de atraque (ETB/ATB) es obligatoria', 'error'); $('nv-eta').focus(); return; }
      if (!etd)    { toast('La fecha de salida (ETD/ATD) es obligatoria', 'error'); $('nv-etd').focus(); return; }

      // Bloque 2 · datos del operativo según el tipo de nave.
      var oper = collectOper();
      if (!oper.ok) { toast(oper.error, 'error'); if (oper.focusId && $(oper.focusId)) $(oper.focusId).focus(); return; }

      var payload = {
        nombre: nombre,
        muelle: $('nv-muelle').value.trim() || null,
        tipo_nave_id: Number(tipo),
        actividad_id: Number($('nv-actividad').value) || null,
        estado: $('nv-estado').value,
        eta: fromLocalInput(eta),
        etd: fromLocalInput(etd)
      };
      if (oper.datos) payload.datos_adicionales = oper.datos;
      var btn = $('navSave'); btn.disabled = true;
      try {
        await opApi('naves', { method: 'POST', body: payload });
        toast('Nave registrada', 'success'); closeModal(); load(curEstado);
      } catch (e) { toast(e.message, 'error'); }
      finally { btn.disabled = false; }
    });
    loadTipos();
    loadActividades();
  }

  load('');
})();
</script>
</body>
</html>
