<?php
require_once('../includes/auth.php');
require_operaciones();

$rol           = $_SESSION['user_rol'] ?? '';
$naveId        = (int)($_GET['id'] ?? 0);
$canWriteDatos = in_array($rol, ['Administrador', 'Supervisor'], true);
$canDeleteNave = in_array($rol, ['Administrador', 'Supervisor', 'Coordinador'], true);
// El Coordinador también puede editar la nave; su edición se propaga a las
// actividades registradas con esa nave.
$canEditNave   = in_array($rol, ['Administrador', 'Supervisor', 'Coordinador'], true);
$canAvance     = ($rol === 'Coordinador');
$canDefinir    = ($rol === 'Administrador');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Operaciones · Detalle de nave · EstibaDeck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css?v=navy1">
  <link rel="stylesheet" href="../css/sidebar.css?v=navy1">
  <link rel="stylesheet" href="../css/layout.css?v=navy1">
  <link rel="stylesheet" href="../css/ui.css?v=navy1">
  <link rel="stylesheet" href="../css/operaciones.css?v=navy1">
</head>
<body>
<div class="overlay" id="overlay"></div>
<div class="shell">
  <?php $sb_base = '..'; include('../includes/sidebar.php'); ?>

  <div class="main-area">
    <?php include('../includes/header.php'); ?>

    <main class="content">
      <div class="op" id="opRoot">

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
          <a class="op-back" href="operaciones.php">← Volver a naves</a>
          <?php if ($canEditNave): ?>
          <div style="display:flex;align-items:center;gap:8px">
            <button class="op-btn sm" id="btnEditNave" type="button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar nave
            </button>
            <?php if ($canDeleteNave): ?>
            <button class="op-btn danger sm" id="btnDelNave" type="button">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
              Eliminar nave
            </button>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

        <section class="op-hero" id="opHero">
          <div class="op-loading" style="color:rgba(255,255,255,.7)">Cargando nave…</div>
        </section>

        <div class="op-detail">
          <div class="op-col-main">
            <div class="op-card">
              <div class="op-card-head">
                <h3>Información adicional <span id="opTipoTag"></span></h3>
                <span id="opInfoState"></span>
              </div>
              <div class="op-card-body" id="opDatos">
                <div class="op-loading">Cargando…</div>
              </div>
            </div>
          </div>

          <div class="op-col-side">
            <div class="op-card">
              <div class="op-card-head"><h3>Datos generales</h3></div>
              <div class="op-card-body"><dl class="op-dl" id="opGeneral"></dl></div>
            </div>

            <div class="op-card">
              <div class="op-card-head"><h3>Control de descarga</h3></div>
              <div class="op-card-body">
                <div id="opResumen"><div class="op-loading">Cargando…</div></div>
              </div>
            </div>

          </div>
        </div>

      </div>

      <?php if ($canEditNave): ?>
      <!-- MODAL: editar datos base de la nave -->
      <div class="op-modal" id="edNavModal">
        <div class="op-dialog">
          <div class="op-dialog-hero">
            <div class="op-dialog-hero-bg" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 21c.6.5 1.2 1 2.5 1 1.3 0 1.9-.5 2.5-1 .6.5 1.2 1 2.5 1 1.3 0 1.9-.5 2.5-1 .6.5 1.2 1 2.5 1 1.3 0 1.9-.5 2.5-1 .6.5 1.2 1 2.5 1 1.3 0 1.9-.5 2.5-1"/><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.76"/><path d="M19 13V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6"/><path d="M12 10v4"/><path d="M12 2v3"/></svg>
            </div>
            <button class="op-x" id="edNavClose" type="button" aria-label="Cerrar">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <span class="op-dialog-tag">◗ Módulo de Operaciones</span>
            <h3>Editar Nave</h3>
            <p>Actualiza la identificación y la ventana operativa de la nave.</p>
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
                    <input class="op-control has-ic" id="ed-nombre" placeholder="Ej. MSC Valencia" autocomplete="off">
                  </div>
                </div>
                <div class="op-field">
                  <label>Tipo de nave <span class="op-req">*</span></label>
                  <div class="op-input">
                    <svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
                    <select class="op-control has-ic op-select" id="ed-tipo"><option value="">Seleccionar…</option></select>
                    <svg class="op-input-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                  </div>
                </div>
                <div class="op-field">
                  <label>Zona</label>
                  <div class="op-input">
                    <svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    <select class="op-control has-ic op-select" id="ed-muelle">
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
                    <select class="op-control has-ic op-select" id="ed-actividad"><option value="">Seleccionar…</option></select>
                    <svg class="op-input-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                  </div>
                </div>
                <div class="op-field">
                  <label>ETB / ATB <span class="op-req">*</span></label>
                  <div class="op-input">
                    <svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    <input type="datetime-local" class="op-control has-ic" id="ed-eta">
                  </div>
                </div>
                <div class="op-field">
                  <label>ETD / ATD <span class="op-req">*</span></label>
                  <div class="op-input">
                    <svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <input type="datetime-local" class="op-control has-ic" id="ed-etd">
                  </div>
                </div>
                <div class="op-field full">
                  <label>Estado</label>
                  <div class="op-segment" id="ed-estado-seg">
                    <button type="button" class="op-seg on" data-v="Programada"><span class="d"></span>Programada</button>
                    <button type="button" class="op-seg" data-v="En Puerto"><span class="d"></span>En puerto</button>
                    <button type="button" class="op-seg" data-v="En Operaciones"><span class="d"></span>En operaciones</button>
                    <button type="button" class="op-seg" data-v="Finalizada"><span class="d"></span>Finalizada</button>
                  </div>
                  <input type="hidden" id="ed-estado" value="Programada">
                </div>
              </div>
            </div>

            <!-- Sección Operaciones (campos adicionales según tipo de nave) -->
            <div class="op-fsection" id="ed-oper-section" style="display:none">
              <div class="op-fsection-head">
                <span class="op-fsection-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                </span>
                <div>
                  <div class="op-fsection-title">Operaciones</div>
                  <div class="op-fsection-sub">Datos del operativo según el tipo de nave</div>
                </div>
              </div>
              <div id="ed-oper-body" class="op-form-grid"></div>
            </div>

          </div>
          <div class="op-dialog-foot">
            <span class="op-foot-hint"><span class="op-req">*</span> Campos obligatorios</span>
            <div class="op-foot-actions">
              <button class="op-btn" id="edNavCancel" type="button">Cancelar</button>
              <button class="op-btn primary" id="edNavSave" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Guardar cambios
              </button>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </main>
  </div>
</div>

<script>window.OP_CTX = {
  id: <?= $naveId ?>,
  rol: <?= json_encode($rol) ?>,
  canWriteDatos: <?= $canWriteDatos ? 'true' : 'false' ?>,
  canDeleteNave: <?= $canDeleteNave ? 'true' : 'false' ?>,
  canEditNave: <?= $canEditNave ? 'true' : 'false' ?>,
  canAvance: <?= $canAvance ? 'true' : 'false' ?>,
  canDefinir: <?= $canDefinir ? 'true' : 'false' ?>
};</script>
<script src="../js/operaciones.js"></script>
<script>
(function () {
  var $ = OP.$, esc = OP.esc, opApi = OP.opApi, toast = OP.toast,
      fmtDateTimeFull = OP.fmtDateTimeFull, estadoChip = OP.estadoChip;
  var CTX = window.OP_CTX || {};
  var ID = CTX.id, nave = null, campos = [];

  function ctrlId(clave) { return 'f_' + clave; }

  function renderHero(n) {
    function m(k, v) { return '<div class="m"><div class="k">' + k + '</div><div class="v">' + v + '</div></div>'; }
    $('opHero').innerHTML =
      '<div>' +
        '<span class="tag">◗ ' + esc(n.tipo_nave) + '</span>' +
        '<h1>' + esc(n.nombre) + '</h1>' +
        '<div class="op-hero-id">' + estadoChip(n.estado) + '</div>' +
        '<div class="op-hero-meta">' + m('ETB / ATB', fmtDateTimeFull(n.etb || n.eta)) + m('ETD / ATD', fmtDateTimeFull(n.etd)) + '</div>' +
      '</div>';
  }

  function renderGeneral(n) {
    function row(k, v) { return '<div><dt>' + k + '</dt><dd>' + v + '</dd></div>'; }
    $('opGeneral').innerHTML =
      row('Nombre', esc(n.nombre)) +
      row('Tipo', esc(n.tipo_nave)) +
      row('Estado', estadoChip(n.estado)) +
      row('ETB / ATB', fmtDateTimeFull(n.etb || n.eta)) +
      row('ETD / ATD', fmtDateTimeFull(n.etd)) +
      row('Registrada', fmtDateTimeFull(n.created_at));
  }

  function fieldEditable(c, val) {
    var id = ctrlId(c.clave);
    var label = '<label>' + esc(c.etiqueta) + (c.requerido == 1 ? ' <span class="op-req">*</span>' : '') + '</label>';
    var ctrl = '';
    if (c.tipo_dato === 'numero') {
      ctrl = '<input type="number" step="any" class="op-control" id="' + id + '" value="' + esc(val == null ? '' : val) + '">';
    } else if (c.tipo_dato === 'fecha') {
      ctrl = '<input type="date" class="op-control" id="' + id + '" value="' + esc(val == null ? '' : val) + '">';
    } else if (c.tipo_dato === 'booleano') {
      var sv = (val === true ? 'true' : val === false ? 'false' : '');
      ctrl = '<select class="op-control" id="' + id + '">' +
        '<option value="">—</option>' +
        '<option value="true"' + (sv === 'true' ? ' selected' : '') + '>Sí</option>' +
        '<option value="false"' + (sv === 'false' ? ' selected' : '') + '>No</option>' +
      '</select>';
    } else if (c.tipo_dato === 'seleccion') {
      var ops = c.opciones || [];
      ctrl = '<select class="op-control" id="' + id + '"><option value="">—</option>' +
        ops.map(function (o) { return '<option value="' + esc(o) + '"' + (String(val) === String(o) ? ' selected' : '') + '>' + esc(o) + '</option>'; }).join('') +
      '</select>';
    } else { // texto
      ctrl = '<input class="op-control" id="' + id + '" value="' + esc(val == null ? '' : val) + '">';
    }
    return '<div class="op-field">' + label + ctrl + '</div>';
  }

  function fmtVal(c, val) {
    if (val === undefined || val === null || val === '') return '— sin dato —';
    if (c.tipo_dato === 'booleano') return val === true ? 'Sí' : val === false ? 'No' : esc(val);
    return esc(val);
  }
  function fieldReadonly(c, val) {
    var empty = (val === undefined || val === null || val === '');
    return '<div class="op-ro"><span class="k">' + esc(c.etiqueta) + '</span><span class="v' + (empty ? ' empty' : '') + '">' + fmtVal(c, val) + '</span></div>';
  }

  function renderDatos() {
    var box = $('opDatos');
    $('opTipoTag').innerHTML = '<span class="op-tagd">' + esc(nave.tipo_nave) + '</span>';
    if (!campos.length) {
      box.innerHTML = '<div class="op-empty">Este tipo de nave no tiene campos adicionales configurados.' +
        (CTX.canDefinir ? '<br><a class="op-rowlink" href="operaciones_campos.php">Configurar campos →</a>' : '') + '</div>';
      $('opInfoState').innerHTML = '';
      return;
    }
    var datos = nave.datos_adicionales || {};
    var req = campos.filter(function (c) { return c.requerido == 1; });
    var filled = req.filter(function (c) { var v = datos[c.clave]; return v !== undefined && v !== null && v !== ''; }).length;
    var st = !req.length ? { cls: 'na', txt: '—' }
      : filled === 0 ? { cls: 'empty', txt: 'Sin cargar' }
      : filled < req.length ? { cls: 'partial', txt: 'Faltan ' + (req.length - filled) }
      : { cls: 'ok', txt: 'Completa' };
    $('opInfoState').innerHTML = '<span class="op-info ' + st.cls + '"><span class="bar"><i></i></span>' + st.txt + '</span>';

    if (CTX.canWriteDatos) {
      box.innerHTML =
        '<div class="op-datos-form"><div class="op-form-grid">' +
          campos.map(function (c) { return fieldEditable(c, datos[c.clave]); }).join('') +
        '</div><div class="op-datos-actions"><button class="op-btn primary" id="datosSave" type="button">Guardar información</button></div></div>';
      $('datosSave').addEventListener('click', saveDatos);
    } else {
      box.innerHTML = campos.map(function (c) { return fieldReadonly(c, datos[c.clave]); }).join('');
    }
  }

  function collectDatos() {
    var out = {}, missing = null;
    campos.forEach(function (c) {
      var el = $(ctrlId(c.clave)); if (!el) return;
      var raw = el.value;
      if (raw == null || raw === '') { if (c.requerido == 1 && !missing) missing = c; return; }
      if (c.tipo_dato === 'numero') out[c.clave] = Number(raw);
      else if (c.tipo_dato === 'booleano') out[c.clave] = (raw === 'true');
      else out[c.clave] = raw;
    });
    return { out: out, missing: missing };
  }

  async function saveDatos() {
    var r = collectDatos();
    if (r.missing) { toast('Falta el campo requerido: ' + r.missing.etiqueta, 'error'); var el = $(ctrlId(r.missing.clave)); if (el) el.focus(); return; }
    var btn = $('datosSave'); btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      var d = await opApi('naves/' + ID + '/datos', { method: 'PUT', body: { datos_adicionales: r.out } });
      nave = d.data;
      toast('Información guardada', 'success');
      renderDatos();
    } catch (e) { toast(e.message, 'error'); }
    finally { var b = $('datosSave'); if (b) { b.disabled = false; b.textContent = 'Guardar información'; } }
  }

  // ── Control de descarga (resumen) ──
  function fmtTM(n) {
    var v = Number(n) || 0;
    return v.toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }
  function renderResumen(r) {
    var box = $('opResumen');
    if (!r) { box.innerHTML = '<div class="op-empty">Sin datos de control.</div>'; return; }
    var pct = (r.porcentaje == null) ? null : r.porcentaje;
    var barHtml = (pct == null)
      ? '<div class="op-prog-nodata">Sin cantidad total declarada para esta nave.</div>'
      : '<div class="op-prog"><div class="op-prog-bar"><i style="width:' + pct + '%"></i></div>' +
          '<div class="op-prog-pct">' + pct + '%</div></div>';

    box.innerHTML =
      barHtml +
      '<div class="op-prog-nums">' +
        '<div class="op-pn"><span class="k">Movido (barco)</span><span class="v">' + fmtTM(r.movido) + ' <small>TM</small></span></div>' +
        '<div class="op-pn"><span class="k">Total nave</span><span class="v">' + (r.total ? fmtTM(r.total) : '—') + ' <small>TM</small></span></div>' +
        '<div class="op-pn"><span class="k">Saldo</span><span class="v">' + (r.total ? fmtTM(r.saldo) : '—') + ' <small>TM</small></span></div>' +
      '</div>' +
      '<div class="op-flow-sum">' +
        '<span class="op-flow-pill"><i class="d"></i>Directa <b>' + fmtTM(r.directa) + '</b></span>' +
        '<span class="op-flow-pill"><i class="i"></i>Indirecta <b>' + fmtTM(r.indirecta) + '</b></span>' +
        '<span class="op-flow-pill"><i class="p"></i>Despacho <b>' + fmtTM(r.despacho) + '</b></span>' +
      '</div>';
  }

  // ── Historial de reportes de turno ──
  function flowChip(cls, lbl, val) {
    if (val == null || Number(val) === 0) return '';
    return '<span class="op-flow-pill sm"><i class="' + cls + '"></i>' + lbl + ' <b>' + fmtTM(val) + ' TM</b></span>';
  }
  function renderAvances(avances) {
    var box = $('opAvances');
    if (!avances.length) { box.innerHTML = '<div class="op-empty">Aún no hay reportes de turno.</div>'; return; }
    box.innerHTML = avances.map(function (a) {
      var chips = flowChip('d', 'Directa', a.directa_tm) + flowChip('i', 'Indirecta', a.indirecta_tm) + flowChip('p', 'Despacho', a.despacho_tm);
      var head = '';
      if (a.turno || a.fecha_turno) {
        head = '<div class="op-tl-turno">' +
          (a.turno ? '<span class="op-turno-tag ' + (a.turno === 'Noche' ? 'noche' : 'manana') + '">' + esc(a.turno) + '</span>' : '') +
          (a.fecha_turno ? '<span class="op-tl-fecha">' + esc(a.fecha_turno) + '</span>' : '') +
        '</div>';
      }
      var admin = CTX.canWriteDatos
        ? '<div class="op-tl-actions">' +
            '<button class="op-tl-act" data-edit="' + a.id + '" type="button">Editar</button>' +
            '<button class="op-tl-act danger" data-del="' + a.id + '" type="button">Eliminar</button>' +
          '</div>'
        : '';
      return '<div class="op-tl"><div class="dot"></div><div class="op-tl-body">' +
        head +
        (chips ? '<div class="op-tl-flows">' + chips + '</div>' : '') +
        (a.descripcion_avance ? '<div class="op-tl-desc">' + esc(a.descripcion_avance) + '</div>' : '') +
        '<div class="op-tl-meta"><b>' + esc(a.registrado_por) + '</b> · ' + fmtDateTimeFull(a.fecha_registro) + '</div>' +
        admin +
      '</div></div>';
    }).join('');

    if (CTX.canWriteDatos) {
      box.querySelectorAll('[data-del]').forEach(function (b) {
        b.addEventListener('click', function () { deleteAvance(b.getAttribute('data-del')); });
      });
      box.querySelectorAll('[data-edit]').forEach(function (b) {
        b.addEventListener('click', function () { editAvance(b.getAttribute('data-edit')); });
      });
    }
  }

  var lastAvances = [];
  async function loadAvances() {
    try {
      var d = await opApi('naves/' + ID + '/historial');
      lastAvances = d.data.avances || [];
      renderResumen(d.data.resumen);
      renderAvances(lastAvances);
    } catch (e) {
      $('opAvances').innerHTML = '<div class="op-error-box">' + esc(e.message) + '</div>';
      $('opResumen').innerHTML = '';
    }
  }

  // Edición (Admin/Sup): precarga el reporte en un prompt estructurado simple.
  async function editAvance(id) {
    var a = lastAvances.filter(function (x) { return String(x.id) === String(id); })[0];
    if (!a) return;
    var directa  = prompt('Descarga directa (TM) — barco→camión:', a.directa_tm == null ? '' : a.directa_tm);
    if (directa === null) return;
    var indirecta = prompt('Descarga indirecta (TM) — barco→almacén:', a.indirecta_tm == null ? '' : a.indirecta_tm);
    if (indirecta === null) return;
    var despacho = prompt('Despacho de almacén (TM) — almacén→camión:', a.despacho_tm == null ? '' : a.despacho_tm);
    if (despacho === null) return;
    var obs = prompt('Observaciones:', a.descripcion_avance || '');
    if (obs === null) return;
    try {
      await opApi('naves/' + ID + '/avances/' + id, { method: 'PUT', body: {
        fecha_turno: a.fecha_turno || null, turno: a.turno || null,
        directa_tm: directa === '' ? null : Number(directa),
        indirecta_tm: indirecta === '' ? null : Number(indirecta),
        despacho_tm: despacho === '' ? null : Number(despacho),
        observaciones: obs
      } });
      toast('Reporte actualizado', 'success'); loadAvances();
    } catch (e) { toast(e.message, 'error'); }
  }
  async function deleteAvance(id) {
    if (!confirm('¿Eliminar este reporte de turno? Esta acción no se puede deshacer.')) return;
    try { await opApi('naves/' + ID + '/avances/' + id, { method: 'DELETE' }); toast('Reporte eliminado', 'success'); loadAvances(); }
    catch (e) { toast(e.message, 'error'); }
  }

  async function load() {
    if (!ID || ID < 1) { $('opHero').innerHTML = '<div class="op-error-box">Nave no válida.</div>'; return; }
    try {
      var d = await opApi('naves/' + ID);
      nave = d.data.nave; campos = d.data.campos || [];
      renderHero(nave); renderGeneral(nave); renderDatos();
    } catch (e) {
      $('opHero').innerHTML = '<div class="op-error-box">' + esc(e.message) + '</div>';
      $('opDatos').innerHTML = ''; $('opGeneral').innerHTML = '';
      return;
    }
    loadAvances();
  }

  if (CTX.canAvance) {
    // Segmento de turno (Mañana / Noche).
    var turnoSeg = $('rp-turno-seg');
    if (turnoSeg) turnoSeg.addEventListener('click', function (e) {
      var b = e.target.closest('.op-seg'); if (!b) return;
      $('rp-turno').value = b.getAttribute('data-v');
      turnoSeg.querySelectorAll('.op-seg').forEach(function (x) {
        x.classList.toggle('on', x === b);
      });
    });

    function resetReportForm() {
      ['rp-directa', 'rp-indirecta', 'rp-despacho', 'rp-obs', 'rp-fecha'].forEach(function (id) {
        var el = $(id); if (el) el.value = '';
      });
      $('rp-turno').value = 'Mañana';
      if (turnoSeg) turnoSeg.querySelectorAll('.op-seg').forEach(function (x) {
        x.classList.toggle('on', x.getAttribute('data-v') === 'Mañana');
      });
    }

    var rpBtn = $('rp-save');
    if (rpBtn) rpBtn.addEventListener('click', async function () {
      var directa = $('rp-directa').value, indirecta = $('rp-indirecta').value, despacho = $('rp-despacho').value;
      var obs = $('rp-obs').value.trim();
      var hayCifra = [directa, indirecta, despacho].some(function (v) { return v !== '' && Number(v) > 0; });
      if (!hayCifra && !obs) { toast('Registra al menos una cantidad movida o una observación', 'error'); return; }

      var payload = {
        fecha_turno: $('rp-fecha').value || null,
        turno: $('rp-turno').value || null,
        directa_tm: directa === '' ? null : Number(directa),
        indirecta_tm: indirecta === '' ? null : Number(indirecta),
        despacho_tm: despacho === '' ? null : Number(despacho),
        observaciones: obs || null
      };
      rpBtn.disabled = true; rpBtn.textContent = 'Registrando…';
      try {
        await opApi('naves/' + ID + '/avances', { method: 'POST', body: payload });
        resetReportForm(); toast('Reporte de turno registrado', 'success'); loadAvances();
      } catch (e) { toast(e.message, 'error'); }
      finally { rpBtn.disabled = false; rpBtn.textContent = 'Registrar reporte de turno'; }
    });
  }

  // Editar datos base de la nave (Admin/Supervisor/Coordinador): modal pre-rellenado.
  // La edición se propaga a las actividades registradas con esta nave.
  if (CTX.canEditNave) {
    var edModal = $('edNavModal');
    var edTiposLoaded = false;

    // ── Helpers para la sección Operaciones del modal de edición ──
    // Mismas plantillas que en operaciones.php (Nueva Nave) para mantener consistencia.

    function edNorm(s) {
      return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
    }

    var ED_OPER_TPL = {
      containera: {
        html: function () {
          return '<div class="op-form-grid">' +
            '<div class="op-field full"><label>TEU\'s a bordo <span class="op-req">*</span></label>' +
              '<div class="op-input"><svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="1"/><path d="M2 11h20"/><path d="M7 7V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"/></svg>' +
              '<input type="number" min="0" step="1" class="op-control has-ic" id="op-teus" placeholder="Ej. 1200"></div></div>' +
          '</div>';
        },
        wire: function () { return {}; }
      },
      granelera: {
        html: function () {
          return '<div class="op-form-grid">' +
            '<div class="op-field full"><label>Cantidad total (TM) <span class="op-req">*</span></label>' +
              '<div class="op-input"><svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M7 12h10"/><path d="M10 18h4"/></svg>' +
              '<input type="number" min="0" step="any" class="op-control has-ic" id="op-cant" placeholder="Ej. 25000"></div></div>' +
          '</div>';
        },
        wire: function () { return {}; }
      },
      'ro-ro': {
        html: function () {
          return '<div class="op-form-grid">' +
            '<div class="op-field full"><label>Cantidad total de vehículos <span class="op-req">*</span></label>' +
              '<div class="op-input"><svg class="op-input-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 16H9m10 0h3v-3.15a1 1 0 0 0-.84-.99L16 11l-2.7-3.6a1 1 0 0 0-.8-.4H5.24a2 2 0 0 0-1.8 1.1l-.8 1.63A6 6 0 0 0 2 12.42V16h2"/><circle cx="6.5" cy="16.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/></svg>' +
              '<input type="number" min="0" step="1" class="op-control has-ic" id="op-vehiculos" placeholder="Ej. 850"></div></div>' +
          '</div>';
        },
        wire: function () { return {}; }
      }
    };

    function renderEdOper() {
      var edOperSection = $('ed-oper-section'), edOperBody = $('ed-oper-body');
      if (!edOperSection || !edOperBody || !nave) return;
      var datos = nave.datos_adicionales || {};
      var tipoNombre = nave.tipo_nave || '';
      var rawKey = edNorm(tipoNombre);

      var key = rawKey;
      if (key === 'cementero' || key === 'sales' || key === 'carga general') key = 'granelera';
      else if (key === 'portacontenedores') key = 'containera';
      else if (key === 'roro') key = 'ro-ro';

      var tpl = ED_OPER_TPL[key];
      if (!tpl) { edOperSection.style.display = 'none'; edOperBody.innerHTML = ''; return; }

      var head = '<div class="op-oper-typetag">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
        'Tipo: <b style="margin-left:3px">' + esc(tipoNombre) + '</b></div>';

      edOperBody.innerHTML = head + tpl.html();
      edOperSection.style.display = '';

      tpl.wire();

      // Pre-rellenar con datos existentes.
      if (key === 'containera') {
        if ($('op-teus') && datos.teus != null) $('op-teus').value = datos.teus;
      } else if (key === 'granelera') {
        if ($('op-cant') && datos.cantidad_total != null) $('op-cant').value = datos.cantidad_total;
      } else if (key === 'ro-ro') {
        if ($('op-vehiculos') && datos.vehiculos != null) $('op-vehiculos').value = datos.vehiculos;
      }
    }

    function collectEdOper() {
      var tipoNombre = nave ? (nave.tipo_nave || '') : '';
      var key = edNorm(tipoNombre);
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
        var cant = $('op-cant') ? $('op-cant').value.trim() : '';
        if (cant === '') return { ok: false, error: 'Ingresa la cantidad total', focusId: 'op-cant' };
        return { ok: true, datos: { cantidad_total: Number(cant) } };
      }
      return { ok: true, datos: null };
    }

    var edTipos = [];
    async function edLoadTipos() {
      if (edTiposLoaded) return;
      try {
        var d = await opApi('tipos-nave');
        edTipos = d.data || [];
        $('ed-tipo').innerHTML = '<option value="">Seleccionar…</option>' +
          edTipos.map(function (t) { return '<option value="' + t.id + '">' + esc(t.nombre) + '</option>'; }).join('');
        edTiposLoaded = true;
      } catch (e) { toast('No se pudieron cargar los tipos: ' + e.message, 'error'); }
    }

    var edActsLoaded = false, edActividades = [];
    async function edLoadActividades() {
      if (edActsLoaded) return;
      try {
        var d = await opApi('tallyman/actividades');
        edActividades = d.data || [];
        edActsLoaded = true;
      } catch (e) { /* catálogo opcional: si falla, el select queda vacío */ }
    }

    // Filtro de ACTIVIDAD por TIPO DE NAVE (mismo criterio que el registro de muelle):
    // prefijos (en minúsculas) de los nombres de actividad que aplican a cada tipo.
    // Los tipos que no aparecen aquí muestran todo el catálogo.
    var ED_ACTIVIDADES_POR_TIPO = {
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
    // Rellena #ed-actividad con las actividades que aplican al tipo seleccionado,
    // conservando la selección previa si sigue siendo válida (p. ej. datos antiguos).
    function edRenderActividadPorTipo() {
      var sel = $('ed-actividad'); if (!sel) return;
      var id = $('ed-tipo').value;
      var t = edTipos.filter(function (x) { return String(x.id) === String(id); })[0];
      var prefijos = ED_ACTIVIDADES_POR_TIPO[edNorm(t ? t.nombre : '')];
      var keep = sel.value || (nave && nave.actividad_id != null ? String(nave.actividad_id) : '');
      var list = !prefijos ? edActividades.slice() : edActividades.filter(function (a) {
        var nm = String(a.nombre).toLowerCase();
        return prefijos.some(function (p) { return nm.indexOf(p) === 0; });
      });
      // Conserva la actividad guardada aunque ya no calce con el tipo (datos antiguos).
      if (keep && !list.some(function (a) { return String(a.id) === String(keep); })) {
        var ex = edActividades.filter(function (a) { return String(a.id) === String(keep); })[0];
        if (ex) list = [ex].concat(list);
      }
      sel.innerHTML = '<option value="">Seleccionar…</option>' +
        list.map(function (a) { return '<option value="' + a.id + '">' + esc(a.nombre) + '</option>'; }).join('');
      if (keep && [].some.call(sel.options, function (o) { return o.value === keep; })) sel.value = keep;
    }

    function edSetEstado(v) {
      $('ed-estado').value = v;
      var seg = $('ed-estado-seg');
      if (seg) seg.querySelectorAll('.op-seg').forEach(function (x) {
        x.classList.toggle('on', x.getAttribute('data-v') === v);
      });
    }

    // Selecciona el muelle; si el valor actual no está entre las opciones estándar,
    // lo añade para no perderlo al editar.
    function edSetMuelle(v) {
      var sel = $('ed-muelle');
      if (!sel) return;
      sel.value = v || '';
      if ((v || '') !== '' && sel.value !== v) {
        var opt = document.createElement('option');
        opt.value = v; opt.textContent = v;
        sel.appendChild(opt);
        sel.value = v;
      }
    }

    async function openEdit() {
      if (!nave) return;
      await edLoadTipos();
      await edLoadActividades();
      $('ed-nombre').value = nave.nombre || '';
      $('ed-tipo').value   = (nave.tipo_nave_id != null) ? String(nave.tipo_nave_id) : '';
      edRenderActividadPorTipo();   // llena la actividad filtrada por el tipo y preselecciona la guardada
      edSetMuelle(nave.muelle);
      $('ed-eta').value = OP.toLocalInput(nave.eta);
      $('ed-etd').value = OP.toLocalInput(nave.etd);
      edSetEstado(nave.estado || 'Programada');

      // Renderizar sección Operaciones con las mismas plantillas de Nueva Nave.
      renderEdOper();

      edModal.classList.add('open');
      setTimeout(function () { $('ed-nombre').focus(); }, 50);
    }
    function closeEdit() { edModal.classList.remove('open'); }

    var segEd = $('ed-estado-seg');
    if (segEd) segEd.addEventListener('click', function (e) {
      var b = e.target.closest('.op-seg'); if (!b) return;
      edSetEstado(b.getAttribute('data-v'));
    });

    var edTipoSel = $('ed-tipo'); if (edTipoSel) edTipoSel.addEventListener('change', edRenderActividadPorTipo);
    var bEdit = $('btnEditNave'); if (bEdit) bEdit.addEventListener('click', openEdit);
    var edClose = $('edNavClose'); if (edClose) edClose.addEventListener('click', closeEdit);
    var edCancel = $('edNavCancel'); if (edCancel) edCancel.addEventListener('click', closeEdit);
    if (edModal) edModal.addEventListener('click', function (e) { if (e.target === edModal) closeEdit(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && edModal && edModal.classList.contains('open')) closeEdit();
    });

    var edSave = $('edNavSave');
    if (edSave) edSave.addEventListener('click', async function () {
      var nombre = $('ed-nombre').value.trim();
      var tipo   = $('ed-tipo').value;
      var eta    = $('ed-eta').value;
      var etd    = $('ed-etd').value;
      if (!nombre) { toast('El nombre de la nave es obligatorio', 'error'); $('ed-nombre').focus(); return; }
      if (!tipo)   { toast('Selecciona el tipo de nave', 'error'); $('ed-tipo').focus(); return; }
      if (!eta)    { toast('La fecha de atraque (ETB/ATB) es obligatoria', 'error'); $('ed-eta').focus(); return; }
      if (!etd)    { toast('La fecha de salida (ETD/ATD) es obligatoria', 'error'); $('ed-etd').focus(); return; }

      // Validar y recolectar operaciones.
      var operR = collectEdOper();
      if (!operR.ok) { toast(operR.error, 'error'); if (operR.focusId && $(operR.focusId)) $(operR.focusId).focus(); return; }
      var datosR = operR.datos;

      var payload = {
        nombre: nombre,
        muelle: $('ed-muelle').value.trim() || null,
        tipo_nave_id: Number(tipo),
        actividad_id: Number($('ed-actividad').value) || null,
        estado: $('ed-estado').value,
        eta: OP.fromLocalInput(eta),
        etd: OP.fromLocalInput(etd)
      };
      edSave.disabled = true;
      try {
        await opApi('naves/' + ID, { method: 'PUT', body: payload });
        if (datosR !== null) {
          await opApi('naves/' + ID + '/datos', { method: 'PUT', body: { datos_adicionales: datosR } });
        }
        toast('Nave actualizada', 'success');
        closeEdit();
        load();   // re-fetch nave + campos + avances y re-render
      } catch (e) { toast(e.message, 'error'); }
      finally { edSave.disabled = false; }
    });
  }

  // Eliminar nave (roles operativos): borra en cascada sus avances y vuelve al listado.
  if (CTX.canDeleteNave) {
    var bDel = $('btnDelNave');
    if (bDel) bDel.addEventListener('click', async function () {
      var nombre = nave ? nave.nombre : ('#' + ID);
      if (!confirm('¿Eliminar la nave "' + nombre + '"?\n' +
        'Se borrarán también sus reportes de turno. Esta acción no se puede deshacer.')) return;
      bDel.disabled = true;
      try {
        await opApi('naves/' + ID, { method: 'DELETE' });
        toast('Nave eliminada', 'success');
        setTimeout(function () { window.location.href = 'operaciones.php'; }, 700);
      } catch (e) { toast(e.message, 'error'); bDel.disabled = false; }
    });
  }

  load();
})();
</script>
</body>
</html>
