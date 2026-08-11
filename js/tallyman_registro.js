/* Registro de actividad de turno (rediseño) — modal estilo "crear nave" + tarjetas.
   Reutiliza los componentes op-* de css/operaciones.css. Patrón window.OP.
   Backend de Fase 1 sin cambios: tallyman/registros, tallyman/incidencias, etc. */
(function () {
  'use strict';
  var OP = window.OP, $ = OP.$;
  var turno = null, turnoActual = null, jornadas = [];
  var actividades = [], naves = [], registros = [], incidencia = null, tipos = [];
  var navesPatioData = null, patioNombres = [], patioNombresMeta = {};
  var datosListos = false, editId = null, modo = null;
  var canReg = !!(window.TM_CTX && window.TM_CTX.canRegistrar);
  var isAdmin = !!(window.TM_CTX && window.TM_CTX.isAdmin);
  var puedeAnteriores = !!(window.TM_CTX && window.TM_CTX.puedeAnteriores);
  var userRol = (window.TM_CTX && window.TM_CTX.userRol) || '';
  // Modo de vista del histórico (solo Admin): 'turno' (normal) | 'semana' | 'mes' | 'todos'
  var modoRango = 'turno', registrosRango = [];
  // Avance del turno anterior (executed acumulado, sin el turno actual) y status
  // sugerido por el backend. Se usan en recalc() para mostrar la proyección y el
  // auto-Culminado. Se refrescan en sugerirStatus() al cambiar nave/actividad.
  var acumuladoPrevio = 0, statusSugerido = 'Inicio';

  function esVistaActual() {
    return turnoActual && turno &&
      turno.fecha === turnoActual.fecha && turno.turno === turnoActual.turno;
  }
  // ¿Puede modificar (agregar/editar/eliminar) el turno que se está viendo?
  //  · Admin/Supervisor → siempre pueden (turno actual o cualquier anterior).
  //  · Coordinador      → solo si el turno está 'abierto' (independiente de la fecha).
  //  · Operador u otros → nunca.
  function puedeModificarTurno() {
    if (!canReg) return false;
    if (puedeAnteriores) return true;
    return !!(turno && turno.estado === 'abierto');
  }
  function actualizarModoLectura() {
    var ro = !puedeModificarTurno();
    var badge = $('tmReadonly'), hoyBtn = $('tmHoyBtn');
    if (badge) badge.style.display = ro ? '' : 'none';
    if (hoyBtn) hoyBtn.style.display = !esVistaActual() ? '' : 'none';
    var addB = $('addBerth'), addY = $('addYard');
    if (addB) addB.style.display = ro ? 'none' : '';
    if (addY) addY.style.display = ro ? 'none' : '';
  }

  var UBICS_BERTH = ['Berth 01', 'Berth 02', 'Berth 03', 'Berth 04', 'Berth 3.5'];
  var ESTADOS_POS = ['ACTIVE', 'INACTIVE', 'FINISH'];
  var STATUS_ACT = ['Inicio', 'En Proceso', 'Culminado'];

  function fmt(v) { return v == null || v === '' ? '—' : (Math.round(Number(v) * 100) / 100).toLocaleString('es-PE'); }
  function plannedDeNave(n) { if (!n || !n.datos_adicionales) return ''; var d = n.datos_adicionales; return d.cantidad_total || d.teus || d.vehiculos || ''; }
  function statusClass(s) { return s === 'Culminado' ? 's2' : (s === 'En Proceso' ? 's1' : 's0'); }

  // ── Mapeo muelle de Operaciones ("Muelle N") ↔ ubicación de muelle del tallyman ("Berth 0N").
  function ubicNum(s) { var m = String(s == null ? '' : s).match(/(\d+(?:\.\d+)?)/); return m ? parseFloat(m[1]) : null; }
  function muelleToBerth(muelle) { var n = ubicNum(muelle); if (n == null) return null; for (var i = 0; i < UBICS_BERTH.length; i++) { if (ubicNum(UBICS_BERTH[i]) === n) return UBICS_BERTH[i]; } return null; }
  function naveById(id) { return naves.filter(function (x) { return String(x.id) === String(id); })[0] || null; }
  function navesEnBerth(berthVal) { var n = ubicNum(berthVal); if (n == null) return []; return naves.filter(function (x) { return ubicNum(x.muelle) === n; }); }

  // Opciones del select NAVE filtradas por tipo. Si tipoVal está vacío, muestra todas.
  // ensureId fuerza incluir esa nave aunque no calce con el tipo (p. ej. al editar).
  function naveOptsHTML(tipoVal, ensureId) {
    var list = naves.filter(function (n) { return !tipoVal || String(n.tipo_nave_id) === String(tipoVal); });
    if (ensureId != null && !list.some(function (n) { return String(n.id) === String(ensureId); })) {
      var ex = naveById(ensureId); if (ex) list = [ex].concat(list);
    }
    return '<option value="">— Sin nave —</option>' +
      list.map(function (n) { return '<option value="' + OP.esc(String(n.id)) + '">' + OP.esc(n.nombre) + '</option>'; }).join('') +
      '<option value="__otros__">Otros (registrar nueva nave)</option>';
  }

  // Filtro de ACTIVIDAD por TIPO DE NAVE. Cada entrada lista los prefijos (en
  // minúsculas) de los nombres de actividad que aplican a ese tipo. Los tipos que
  // no aparecen aquí muestran todo el catálogo. Para añadir un tipo, agrega su
  // entrada (ej. Ro-Ro solo opera carga/descarga y despacho de vehículos).
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
  // Opciones del select ACTIVIDAD filtradas por el nombre del tipo de nave.
  // ensureId fuerza incluir una actividad concreta aunque no calce con el tipo
  // (p. ej. al editar un registro antiguo).
  function actividadOptsHTML(tipoNombre, ensureId) {
    var key = String(tipoNombre || '').trim().toLowerCase();
    var prefijos = ACTIVIDADES_POR_TIPO[key];
    var list = !prefijos ? actividades.slice() : actividades.filter(function (a) {
      var nm = String(a.nombre).toLowerCase();
      return prefijos.some(function (p) { return nm.indexOf(p) === 0; });
    });
    if (ensureId != null && ensureId !== '' && !list.some(function (a) { return String(a.id) === String(ensureId); })) {
      var ex = actividades.filter(function (a) { return String(a.id) === String(ensureId); })[0];
      if (ex) list = [ex].concat(list);
    }
    return list.map(function (a) { return '<option value="' + OP.esc(String(a.id)) + '">' + OP.esc(a.nombre) + '</option>'; }).join('');
  }

  // Clave de `datos_adicionales` donde se guarda el planned (total operativo) según
  // el tipo de nave, para que Operaciones lo muestre con la unidad correcta:
  //   · Ro-Ro     → "vehiculos" (cantidad total de vehículos)
  //   · Containera→ "teus"
  //   · otros     → "cantidad_total" (TM)
  var PLANNED_KEY_POR_TIPO = { 'ro-ro': 'vehiculos', 'containera': 'teus', 'portacontenedores': 'teus' };
  function plannedKeyPorTipo(tipoNombre) {
    return PLANNED_KEY_POR_TIPO[String(tipoNombre || '').trim().toLowerCase()] || 'cantidad_total';
  }

  // ── Helpers para el select NAVE (PATIO) ──
  // Valor del option: "n:ID" para nave real, "p:IDX" para nombre texto, "__nuevo__" para nuevo.
  function cargarPatioNombres(data) {
    var raw = (data && data.nombres_patio) ? data.nombres_patio : [];
    patioNombres = [];
    patioNombresMeta = {};
    raw.forEach(function (x) {
      if (x && typeof x === 'object' && x.nombre) {
        patioNombres.push(x.nombre);
        patioNombresMeta[x.nombre] = {
          actividad_id_sugerida: x.actividad_id_sugerida || null,
          planned_sugerido: x.planned_sugerido != null ? x.planned_sugerido : null
        };
      } else if (typeof x === 'string') {
        patioNombres.push(x);
      }
    });
  }

  function patioNavePorId(id) {
    var data = navesPatioData && navesPatioData.naves ? navesPatioData.naves : [];
    return data.filter(function (x) { return String(x.id) === String(id); })[0] || null;
  }

  var CARET = '<svg class="op-input-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';


  function fSelect(label, k, opts, req) {
    var o = opts.map(function (x) { return '<option value="' + OP.esc(x.v) + '">' + OP.esc(x.t) + '</option>'; }).join('');
    return '<div class="op-field"><label>' + label + (req ? ' <span class="op-req">*</span>' : '') + '</label>' +
      '<div class="op-input"><select class="op-control op-select" data-k="' + k + '">' + o + '</select>' + CARET + '</div></div>';
  }
  function fInput(label, k, type, req, ph) {
    var extra = type === 'number' ? ' min="0" step="0.01"' : '';
    return '<div class="op-field"><label>' + label + (req ? ' <span class="op-req">*</span>' : '') + '</label>' +
      '<div class="op-input"><input class="op-control" data-k="' + k + '" type="' + type + '"' + extra +
      (ph ? ' placeholder="' + OP.esc(ph) + '"' : '') + '></div></div>';
  }
  function fInputRO(label, k) {
    return '<div class="op-field"><label>' + label + '</label>' +
      '<div class="op-input"><input class="op-control" data-k="' + k + '" readonly placeholder="—"></div></div>';
  }
  function fSeg(label, k, values, req) {
    var btns = values.map(function (v, i) { return '<button type="button" class="op-seg' + (i === 0 ? ' on' : '') + '" data-v="' + OP.esc(v) + '">' + OP.esc(v) + '</button>'; }).join('');
    return '<div class="op-field full"><label>' + label + (req ? ' <span class="op-req">*</span>' : '') + '</label>' +
      '<div class="op-segment" data-seg="' + k + '" style="grid-template-columns:repeat(' + values.length + ',1fr)">' + btns + '</div>' +
      '<input type="hidden" data-k="' + k + '" value="' + OP.esc(values[0]) + '"></div>';
  }
  function fArea(label, k, ph) {
    return '<div class="op-field full"><label>' + label + '</label>' +
      '<textarea class="op-control" data-k="' + k + '" rows="2"' + (ph ? ' placeholder="' + OP.esc(ph) + '"' : '') + '></textarea></div>';
  }
  function fsection(title, sub, inner) {
    var icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 22h18"/><path d="M5 22V10l7-5 7 5v12"/></svg>';
    return '<div class="op-fsection"><div class="op-fsection-head"><span class="op-fsection-icon">' + icon +
      '</span><div><div class="op-fsection-title">' + title + '</div><div class="op-fsection-sub">' + sub + '</div></div></div>' +
      '<div class="op-form-grid">' + inner + '</div></div>';
  }

  // ───────── cuerpo del modal por modo ─────────
  function bodyActividad(tipo) {
    var statusNote =
      '<div data-statusnote style="display:none;grid-column:1/-1;margin-top:-4px;font-size:11.5px;padding:6px 10px;border-radius:8px;background:#eef4fb;border:1px solid #c7dff4;color:#1d4f8a;align-items:center;gap:6px">' +
        '<svg style="flex-shrink:0;width:13px;height:13px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
        '<span data-statusnote-txt></span>' +
      '</div>';

    if (tipo === 'BERTH') {
      var navesOpt = [{ v: '', t: '— Sin nave —' }]
        .concat(naves.map(function (n) { return { v: String(n.id), t: n.nombre }; }))
        .concat([{ v: '__otros__', t: 'Otros (registrar nueva nave)' }]);
      var actOpt = actividades.map(function (a) { return { v: String(a.id), t: a.nombre }; });
      var tipoFiltroOpt = [{ v: '', t: 'Todos los tipos' }]
        .concat(tipos.map(function (t) { return { v: String(t.id), t: t.nombre }; }));
      // Aviso (solo en modo Otros): al guardar se creará la nave en Operaciones.
      var nuevaNaveBanner =
        '<div data-nuevanave style="display:none;grid-column:1/-1">' +
          '<div style="margin-bottom:8px;padding:8px 12px;background:#fff7ed;border:1px solid #fbbf24;border-radius:8px;font-size:11.5px;color:#92400e;display:flex;gap:7px;align-items:center">' +
            '<svg style="flex-shrink:0;width:13px;height:13px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
            'Al guardar se creará la nave en Operaciones. El ETB se registra automáticamente (ahora); el ETD se llena al Culminar.' +
          '</div>' +
        '</div>';
      // Nombre de la nave nueva: aparece al costado de "Nave" (solo en modo Otros).
      var nnNombreField =
        '<div class="op-field" data-nnnombre style="display:none"><label>Nombre de la nave <span class="op-req">*</span></label>' +
          '<div class="op-input"><input class="op-control" data-k="nn_nombre" type="text" placeholder="Ej. MSC Valencia"></div></div>';
      // Campo oculto que replica el tipo de nave elegido arriba (tipo_filtro) para la
      // nave nueva; el guardado y los cálculos leen nn_tipo. No se muestra al usuario.
      var nnTipoBlock =
        '<div class="op-field" data-nntipo style="display:none"><label>Tipo de nave</label>' +
          '<div class="op-input">' +
            '<select class="op-control op-select" data-k="nn_tipo"><option value="">Seleccionar…</option>' +
              tipos.map(function (t) { return '<option value="' + t.id + '">' + OP.esc(t.nombre) + '</option>'; }).join('') +
            '</select>' +
            CARET +
          '</div></div>';
      var s1 = '<div class="op-field"><label>Ubicación <span class="op-req">*</span></label>' +
          '<div class="op-input"><select class="op-control op-select" data-k="ubicacion">' +
            UBICS_BERTH.map(function (u) { return '<option value="' + OP.esc(u) + '">' + OP.esc(u) + '</option>'; }).join('') +
          '</select>' + CARET + '</div></div>' +
          fSelect('Tipo de nave', 'tipo_filtro', tipoFiltroOpt, false) +
          '<div class="op-field full" data-ubicnote style="display:none;font-size:12px;color:var(--faint);margin-top:-6px"></div>' +
          fSelect('Nave', 'nave_id', navesOpt, false) +
          nnNombreField +
          nuevaNaveBanner +
          fSelect('Actividad', 'actividad_id', actOpt, true) +
          nnTipoBlock +
          fSeg('Status', 'status_act', STATUS_ACT, true) +
          statusNote;
      var s2 = fInputRO('Planned (heredado de la nave)', 'planned') +
        fInput('Descarga Directa', 'directa', 'number', false, 'Ej. 500') +
        fInput('Descarga Interna', 'interna', 'number', false, 'Ej. 300') +
        fArea('Observaciones', 'details', 'Avances, QC, novedades del turno…') +
        '<div class="tm-calc"><span>Acumulado previo: <b data-calc="prev">—</b></span><span>Con este registro: <b data-calc="proj">—</b></span><span>Pendiente: <b data-calc="pending">—</b></span><span>Avance: <b data-calc="pct">—</b></span><span style="color:var(--faint)">El acumulado real se calcula al guardar.</span></div>';
      return fsection('Ubicación y actividad', 'Dónde y qué se trabajó', s1) +
        fsection('Indicadores', 'El total se hereda de la nave; tú ingresas lo ejecutado', s2);
    }

    // ── YARD ──
    var patioNaves = navesPatioData && navesPatioData.naves ? navesPatioData.naves : [];
    var yardOpt = [{ v: '', t: '— Sin nave —' }]
      .concat(patioNaves.map(function (n) { return { v: 'n:' + n.id, t: n.nombre }; }))
      .concat(patioNombres.map(function (nm, i) { return { v: 'p:' + i, t: nm + ' (patio)' }; }))
      .concat([{ v: '__nuevo__', t: 'Otros (nuevo nombre)' }]);
    var actOptY = actividades.map(function (a) { return { v: String(a.id), t: a.nombre }; });
    var nuevoNombreBlock =
      '<div data-nuevanave style="display:none;grid-column:1/-1">' +
        '<div style="margin-bottom:8px;padding:8px 12px;background:#fff7ed;border:1px solid #fbbf24;border-radius:8px;font-size:11.5px;color:#92400e;display:flex;gap:7px;align-items:center">' +
          '<svg style="flex-shrink:0;width:13px;height:13px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
          'El nombre se añade temporalmente al listado hasta que el registro sea Culminado.' +
        '</div>' +
        '<div class="op-form-grid">' +
          fInput('Nombre (Patio)', 'np_nombre_patio', 'text', true, 'Ej. SOYA LOT A') +
        '</div>' +
      '</div>';
    var sY1 = '<div class="op-field"><label>Ubicación <span class="op-req">*</span></label>' +
        '<div class="op-input"><input class="op-control" data-k="ubicacion" value="Yard" placeholder="Ej. Yard / Z1D1"></div></div>' +
        fSelect('NAVE (PATIO)', 'nave_id', yardOpt, false) +
        nuevoNombreBlock +
        fSelect('Actividad', 'actividad_id', actOptY, true) +
        fSeg('Status', 'status_act', STATUS_ACT, true) +
        statusNote;
    var sY2 = fInput('Planned (descarga interna acumulada)', 'planned', 'number', false, 'Ej. 500') +
      fInput('Executed in Turn', 'executed', 'number', true, 'Ej. 200') +
      fArea('Observaciones', 'details', 'Avances, QC, novedades del turno…') +
      '<div class="tm-calc"><span>Pendiente (estimado): <b data-calc="pending">—</b></span><span>Avance: <b data-calc="pct">—</b></span><span style="color:var(--faint)">El acumulado real se calcula al guardar.</span></div>';
    return fsection('Ubicación y actividad', 'Dónde y qué se trabajó', sY1) +
      fsection('Indicadores', 'El ejecutado acumulado por nave y actividad', sY2);
  }
  function bodyIncidencia() {
    var s = fSeg('¿Hubo incidente, demora o problema?', 'hubo', ['No', 'Sí'], true) +
      '<div class="op-field full" data-incdet style="display:none"><label>Detalle del incidente <span class="op-req">*</span></label>' +
      '<textarea class="op-control" data-k="detalle" rows="4" placeholder="Describe el incidente, demora o condición anómala."></textarea></div>';
    return fsection('Incidencia del turno', 'Reporta cualquier incidente, demora o condición anómala', s);
  }

  var META = {
    BERTH: { title: 'Actividad en Muelle', sub: 'Registra lo realizado en el muelle durante tu turno.', save: 'Guardar actividad' },
    YARD: { title: 'Actividad en Patio', sub: 'Registra lo realizado en patio durante tu turno.', save: 'Guardar actividad' },
    INCIDENCIA: { title: 'Incidencia del turno', sub: 'Reporta cualquier incidente, demora o condición anómala.', save: 'Guardar incidencia' }
  };

  // ───────── helpers de lectura/escritura del modal ─────────
  function bodyEl() { return $('tmModalBody'); }
  function getField(k) { var el = bodyEl().querySelector('[data-k="' + k + '"]'); return el ? el.value : ''; }
  function setField(k, v) { var el = bodyEl().querySelector('[data-k="' + k + '"]'); if (el) el.value = (v == null ? '' : v); }
  function setSeg(k, v) {
    var seg = bodyEl().querySelector('[data-seg="' + k + '"]');
    if (!seg) return;
    seg.querySelectorAll('.op-seg').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-v') === v); });
    setField(k, v);
  }
  function recalc() {
    var pIn = bodyEl().querySelector('[data-k="planned"]');
    if (!pIn) return;
    var p = Number(pIn.value) || 0;
    // YARD tiene campo "executed" directo; BERTH tiene directa+interna
    var execIn = bodyEl().querySelector('[data-k="executed"]');
    var dIn = bodyEl().querySelector('[data-k="directa"]');
    var iIn = bodyEl().querySelector('[data-k="interna"]');
    var e;
    if (execIn) {
      e = Number(execIn.value) || 0;
    } else {
      var dv = dIn ? Number(dIn.value) || 0 : 0;
      var iv = iIn ? Number(iIn.value) || 0 : 0;
      // Cement Big Bags: el avance del muelle cuenta SOLO la descarga interna (a piso);
      // la descarga directa se contabiliza aparte en la actividad de patio.
      e = esActividadCementBigBags() ? iv : (dv + iv);
    }
    // Acumulado previo (turnos anteriores) + lo de este registro = proyección total.
    var prev = Number(acumuladoPrevio) || 0;
    var accumulated = prev + e;
    var pend = p > 0 ? Math.max(p - accumulated, 0) : null;
    var prevEl = bodyEl().querySelector('[data-calc="prev"]'); if (prevEl) prevEl.textContent = prev.toFixed(2);
    var projEl = bodyEl().querySelector('[data-calc="proj"]'); if (projEl) projEl.textContent = accumulated.toFixed(2);
    var pe = bodyEl().querySelector('[data-calc="pending"]'); if (pe) pe.textContent = pend == null ? '—' : pend.toFixed(2);
    var pc = bodyEl().querySelector('[data-calc="pct"]'); if (pc) pc.textContent = p > 0 ? Math.min(accumulated / p * 100, 100).toFixed(1) + '%' : '—';
    aplicarAutoStatus(p, accumulated);
  }
  // Auto-Culminado: si al ingresar datos el acumulado alcanza/supera el total (planned),
  // el status pasa de "En Proceso"/sugerido a "Culminado". Si baja del total, se
  // restablece el status sugerido. Solo actúa cuando hay un planned > 0.
  function aplicarAutoStatus(planned, accumulated) {
    var seg = bodyEl().querySelector('[data-seg="status_act"]');
    if (!seg || !(planned > 0)) return;
    var actual = getField('status_act');
    if (accumulated >= planned) {
      if (actual !== 'Culminado') { setSeg('status_act', 'Culminado'); setStatusNoteSafe('El acumulado alcanza el total → status "Culminado".'); }
    } else if (actual === 'Culminado' && statusSugerido !== 'Culminado') {
      setSeg('status_act', statusSugerido);
    }
  }
  // setStatusNote vive dentro del wire; este wrapper evita error si aún no existe.
  function setStatusNoteSafe(msg) {
    var el = bodyEl().querySelector('[data-statusnote]');
    var tx = bodyEl().querySelector('[data-statusnote-txt]');
    if (!el || !tx) return;
    if (msg) { tx.textContent = msg; el.style.display = 'flex'; } else { el.style.display = 'none'; tx.textContent = ''; }
  }
  function setUbicNote(msg) {
    var el = bodyEl().querySelector('[data-ubicnote]');
    if (!el) return;
    el.textContent = msg || '';
    el.style.display = msg ? '' : 'none';
  }
  // ── Modo Cementero ────────────────────────────────────────────────────────
  function tipoNombrePorId(id) {
    for (var i = 0; i < tipos.length; i++) { if (String(tipos[i].id) === String(id)) return tipos[i].nombre; }
    return '';
  }
  // Nombre (en minúsculas) del TIPO DE NAVE efectivo del formulario. Si se está
  // registrando una nave nueva (Otros), se toma el tipo de la nueva nave (nn_tipo);
  // en caso contrario, el del filtro superior (tipo_filtro).
  function tipoSelNombre() {
    var body = bodyEl();
    var naveSel = body.querySelector('[data-k="nave_id"]');
    var key = (naveSel && naveSel.value === '__otros__') ? 'nn_tipo' : 'tipo_filtro';
    var el = body.querySelector('[data-k="' + key + '"]');
    if (!el) return '';
    return String(tipoNombrePorId(el.value)).trim().toLowerCase();
  }
  // ¿El TIPO DE NAVE seleccionado en el formulario es "Cementero"?
  function esCementeroSel() {
    return tipoSelNombre() === 'cementero';
  }
  // Nombre (en minúsculas) de la ACTIVIDAD seleccionada en el formulario.
  function actividadSelNombre() {
    var el = bodyEl().querySelector('[data-k="actividad_id"]');
    if (!el) return '';
    var id = el.value;
    for (var i = 0; i < actividades.length; i++) {
      if (String(actividades[i].id) === String(id)) return String(actividades[i].nombre).toLowerCase();
    }
    return '';
  }
  // ¿La actividad seleccionada es "Cement Big Bags Loading/Discharge"? En ese caso el
  // avance del muelle cuenta SOLO la descarga interna (la directa va 100% a patio).
  function esActividadCementBigBags() {
    return actividadSelNombre().indexOf('cement big bags loading') === 0;
  }
  // ¿El TIPO DE NAVE seleccionado es "Containera"? Estas naves no manejan
  // descarga interna, así que ese campo se oculta del formulario de muelle.
  function esContaineraSel() {
    return tipoSelNombre() === 'containera';
  }
  // Muestra/oculta el campo DESCARGA INTERNA según el tipo de nave. En naves
  // containeras se oculta y se limpia para que no afecte el avance.
  function aplicarVisibilidadInterna() {
    var iIn = bodyEl().querySelector('[data-k="interna"]');
    if (!iIn) return; // solo aplica al formulario BERTH
    var wrap = iIn.closest('.op-field');
    var ocultar = esContaineraSel();
    if (wrap) wrap.style.display = ocultar ? 'none' : '';
    if (ocultar && iIn.value) iIn.value = '';
    recalc();
  }
  // Ajusta las etiquetas de los flujos de muelle (directa/interna) según el tipo de nave:
  //   · Cementero → "Despacho Indirecto (carga a camión)" / "Movimiento Interno (descarga a piso)"
  //   · Ro-Ro     → "Transbordo" / "Tránsito Aduanero"
  //   · Otros     → "Descarga Directa" / "Descarga Interna"
  // y recalcula el avance (en Cementero solo cuenta el movimiento interno).
  // Ajusta SOLO los rótulos de los flujos de muelle (directa/interna) según la
  // ACTIVIDAD o el tipo de nave. Prioridad: la actividad "Cement Big Bags" manda
  // (estilo Cementero) por encima del tipo de nave.
  function aplicarEtiquetasFlujo() {
    var body = bodyEl();
    var dIn = body.querySelector('[data-k="directa"]');
    var iIn = body.querySelector('[data-k="interna"]');
    if (!dIn || !iIn) return; // solo aplica al formulario BERTH
    var tipo = tipoSelNombre();
    var dLab = dIn.closest('.op-field') && dIn.closest('.op-field').querySelector('label');
    var iLab = iIn.closest('.op-field') && iIn.closest('.op-field').querySelector('label');
    var dTxt = 'Descarga Directa', iTxt = 'Descarga Interna';
    if (esActividadCementBigBags()) { dTxt = 'Despacho Indirecto (piso a camión)'; iTxt = 'Movimiento Interno (descarga a piso)'; }
    else if (tipo === 'cementero') { dTxt = 'Despacho Indirecto (carga a camión)'; iTxt = 'Movimiento Interno (descarga a piso)'; }
    else if (tipo === 'ro-ro') { dTxt = 'Transbordo'; iTxt = 'Tránsito Aduanero'; }
    if (dLab) dLab.textContent = dTxt;
    if (iLab) iLab.textContent = iTxt;
  }
  function aplicarModoCementero() {
    var body = bodyEl();
    var dIn = body.querySelector('[data-k="directa"]');
    var iIn = body.querySelector('[data-k="interna"]');
    if (!dIn || !iIn) return; // solo aplica al formulario BERTH
    var tipo = tipoSelNombre();
    aplicarEtiquetasFlujo();
    // Filtrar la ACTIVIDAD según el tipo de nave (p. ej. Ro-Ro → solo Car Loading /
    // Car Dispatch). En edición se conserva la actividad guardada aunque no calce con
    // el tipo (datos antiguos); al crear se filtra de forma estricta y, si la selección
    // previa ya no aplica, queda la primera actividad válida.
    var actSelEl = body.querySelector('[data-k="actividad_id"]');
    if (actSelEl) {
      var keepAct = actSelEl.value;
      actSelEl.innerHTML = actividadOptsHTML(tipo, editId ? keepAct : null);
      if (keepAct && [].some.call(actSelEl.options, function (o) { return o.value === keepAct; })) actSelEl.value = keepAct;
    }
    aplicarVisibilidadInterna();
    recalc();
  }
  function aplicarMuelleDeNave(n, keepValue) {
    var ubicSel = bodyEl().querySelector('[data-k="ubicacion"]');
    if (!ubicSel || ubicSel.tagName !== 'SELECT') return;
    ubicSel.disabled = false;
    if (!n) { setUbicNote(''); return; }
    var berth = n.muelle ? muelleToBerth(n.muelle) : null;
    if (berth && !keepValue) ubicSel.value = berth;
    setUbicNote(n && n.muelle && !berth ? 'Esta nave no tiene un muelle reconocible — selecciónalo manualmente.' : '');
  }

  // ───────── abrir / cerrar / wire ─────────
  function abrirModal(m, reg) {
    if (!canReg) { OP.toast('No tienes permiso para registrar.', 'error'); return; }
    if (modoRango === 'turno' && !puedeModificarTurno()) {
      OP.toast('Solo Administrador o Supervisor pueden modificar turnos anteriores.', 'error'); return;
    }
    if (!datosListos) { OP.toast('Datos no cargados aún. Reintenta en unos segundos.', 'error'); return; }
    // Al editar, asegurar que la nave/patio del registro esté en las opciones,
    // aunque ya no esté activa (otro turno, culminado o nave finalizada). Así
    // se puede conservar o corregir el nombre de la nave.
    if (reg && m === 'YARD') {
      if (reg.nave_patio && patioNombres.indexOf(reg.nave_patio) < 0) patioNombres.push(reg.nave_patio);
    } else if (reg && m === 'BERTH') {
      if (reg.nave_id != null && !naveById(reg.nave_id)) {
        naves.push({ id: reg.nave_id, nombre: reg.nave || ('Nave #' + reg.nave_id), muelle: reg.ubicacion,
          tipo_nave_id: reg.tipo_nave_id != null ? reg.tipo_nave_id : null, datos_adicionales: null });
      }
    }
    modo = m; editId = (reg && reg.id) ? reg.id : null;
    $('tmModalTitle').textContent = META[m].title + (editId ? ' (editar)' : '');
    $('tmModalSub').textContent = META[m].sub;
    $('tmModalSaveLabel').textContent = META[m].save;
    bodyEl().innerHTML = (m === 'INCIDENCIA') ? bodyIncidencia() : bodyActividad(m);
    wireBody(m);
    if (reg) prefill(m, reg);
    $('tmModal').classList.add('open');
  }
  function cerrarModal() { $('tmModal').classList.remove('open'); editId = null; modo = null; }

  function wireBody(m) {
    var body = bodyEl();
    body.querySelectorAll('.op-segment').forEach(function (seg) {
      seg.addEventListener('click', function (e) {
        var b = e.target.closest('.op-seg'); if (!b) return;
        var k = seg.getAttribute('data-seg');
        setSeg(k, b.getAttribute('data-v'));
        if (m === 'INCIDENCIA' && k === 'hubo') {
          body.querySelector('[data-incdet]').style.display = (b.getAttribute('data-v') === 'Sí') ? '' : 'none';
        }
      });
    });
    if (m === 'INCIDENCIA') return;

    var naveSel = body.querySelector('[data-k="nave_id"]');
    var plannedIn = body.querySelector('[data-k="planned"]');
    var actSel = body.querySelector('[data-k="actividad_id"]');
    var nuevaNaveWrap = body.querySelector('[data-nuevanave]');

    function setStatusNote(msg) {
      var el = bodyEl().querySelector('[data-statusnote]');
      var tx = bodyEl().querySelector('[data-statusnote-txt]');
      if (!el || !tx) return;
      if (msg) { tx.textContent = msg; el.style.display = 'flex'; }
      else { el.style.display = 'none'; tx.textContent = ''; }
    }

    // ── YARD ──────────────────────────────────────────────────────────────
    if (m === 'YARD') {
      var execIn = body.querySelector('[data-k="executed"]');

      function mostrarNuevoNombre(show) {
        if (!nuevaNaveWrap) return;
        nuevaNaveWrap.style.display = show ? '' : 'none';
      }

      async function sugerirStatusYard(naveId, navePatio) {
        acumuladoPrevio = 0; statusSugerido = 'Inicio';
        var actId = getField('actividad_id');
        if (!actId) { setStatusNote('No hay registros de nave y actividad.'); recalc(); return; }
        try {
          var q = { actividad_id: actId, fecha_turno: (turno && turno.fecha) || '', turno: (turno && turno.turno) || '' };
          if (naveId) q.nave_id = naveId;
          else if (navePatio) q.nave_patio = navePatio;
          else { setStatusNote('No hay registros de nave y actividad.'); recalc(); return; }
          var r = await OP.opApi('tallyman/estado-sugerido', { query: q });
          if (!r || !r.data) return;
          var status = r.data.status, ultimo = r.data.ultimo;
          acumuladoPrevio = Number(r.data.acumulado) || 0;
          statusSugerido = status;
          setSeg('status_act', status);
          recalc();
          if (status === 'Inicio' && !ultimo)
            setStatusNote('Sugerido automáticamente: Inicio — no hay registros previos de esta nave y actividad.');
          else if (status === 'Inicio' && ultimo === 'Culminado')
            setStatusNote('Sugerido automáticamente: Inicio — el ciclo anterior fue Culminado, empieza uno nuevo.');
          else if (status === 'En Proceso')
            setStatusNote('Sugerido automáticamente: En Proceso — ya existe un registro abierto de esta nave y actividad.');
          else setStatusNote('');
        } catch (e) { setStatusNote(''); }
      }

      naveSel.addEventListener('change', function () {
        var v = naveSel.value;
        mostrarNuevoNombre(v === '__nuevo__');
        if (v === '__nuevo__') {
          if (plannedIn) { plannedIn.value = ''; plannedIn.focus(); }
          recalc(); return;
        }
        if (v === '') {
          if (plannedIn) plannedIn.value = '';
          setStatusNote('No hay registros de nave y actividad.');
          recalc(); return;
        }
        if (v.startsWith('n:')) {
          var nid = v.slice(2);
          var pn = patioNavePorId(nid);
          // Autocompletar actividad del último registro YARD abierto de esta nave
          if (pn && pn.actividad_id_sugerida && actSel && !editId) {
            actSel.value = String(pn.actividad_id_sugerida);
          }
          // Planned = total acumulado de descarga interna (BERTH) de esta nave.
          // Siempre la suma viva (planned_patio), no un valor congelado, para que
          // refleje todos los turnos: ej. 2 (turno previo) + 177 (este turno) = 179.
          if (plannedIn) {
            plannedIn.value = pn ? pn.planned_patio : '';
          }
          recalc();
          sugerirStatusYard(nid, null);
        } else if (v.startsWith('p:')) {
          var idx = Number(v.slice(2));
          var nm = patioNombres[idx] || null;
          var meta = nm ? (patioNombresMeta[nm] || {}) : {};
          // Autocompletar actividad del último registro YARD abierto
          if (meta.actividad_id_sugerida && actSel && !editId) {
            actSel.value = String(meta.actividad_id_sugerida);
          }
          // Planned del último registro YARD abierto
          if (plannedIn) plannedIn.value = meta.planned_sugerido != null ? meta.planned_sugerido : '';
          recalc();
          sugerirStatusYard(null, nm);
        }
      });

      if (actSel) actSel.addEventListener('change', function () {
        var v = naveSel ? naveSel.value : '';
        if (!v || v === '__nuevo__' || v === '') { setStatusNote('No hay registros de nave y actividad.'); return; }
        if (v.startsWith('n:')) sugerirStatusYard(v.slice(2), null);
        else if (v.startsWith('p:')) sugerirStatusYard(null, patioNombres[Number(v.slice(2))] || null);
      });

      if (execIn) execIn.addEventListener('input', recalc);
      setStatusNote('No hay registros de nave y actividad.');
      return;
    }

    // ── BERTH ─────────────────────────────────────────────────────────────
    var directaIn = body.querySelector('[data-k="directa"]');
    var internaIn = body.querySelector('[data-k="interna"]');
    var ubicSel = body.querySelector('[data-k="ubicacion"]');
    var tipoSel = body.querySelector('[data-k="tipo_filtro"]');
    var tiposLoaded = false;

    // Reconstruye el select NAVE según el tipo elegido, conservando la selección
    // actual si sigue presente. ensureId fuerza incluir una nave concreta (editar).
    function rebuildNaves(keepVal, ensureId) {
      if (!naveSel) return;
      naveSel.innerHTML = naveOptsHTML(tipoSel ? tipoSel.value : '', ensureId);
      var has = [].some.call(naveSel.options, function (o) { return o.value === keepVal; });
      naveSel.value = has ? keepVal : '';
    }
    if (tipoSel) tipoSel.addEventListener('change', function () {
      rebuildNaves(naveSel.value, null);
      var nnTipo = body.querySelector('[data-k="nn_tipo"]');
      if (nnTipo) nnTipo.value = tipoSel.value;  // el tipo de la nave nueva = tipo elegido arriba
      naveSel.dispatchEvent(new Event('change'));
      aplicarModoCementero();
    });

    async function cargarTiposNave() {
      if (tiposLoaded || tipos.length) return;  // ya vienen del catálogo global (inline)
      try {
        var d = await OP.opApi('tipos-nave'); tiposLoaded = true;
        var sel = body.querySelector('[data-k="nn_tipo"]');
        if (!sel) return;
        sel.innerHTML = '<option value="">Seleccionar…</option>' +
          (d.data || []).map(function (t) { return '<option value="' + t.id + '">' + OP.esc(t.nombre) + '</option>'; }).join('');
      } catch (e) { /* silencioso */ }
    }
    function mostrarNuevaNav(show) {
      // En modo Otros se muestran: el campo NOMBRE (al costado de Nave) y el aviso.
      // El TIPO DE NAVE se toma del selector superior (tipo_filtro), que permanece visible.
      var nombreWrap = body.querySelector('[data-nnnombre]');
      if (nombreWrap) nombreWrap.style.display = show ? '' : 'none';
      if (nuevaNaveWrap) nuevaNaveWrap.style.display = show ? '' : 'none';
      // En Otros el TIPO DE NAVE es obligatorio (define la nave nueva): marca el asterisco.
      var tipoLab = tipoSel && tipoSel.closest('.op-field') && tipoSel.closest('.op-field').querySelector('label');
      if (tipoLab) tipoLab.innerHTML = show ? 'Tipo de nave <span class="op-req">*</span>' : 'Tipo de nave';
      if (show) cargarTiposNave();
    }
    // El TIPO DE NAVE (tipo_filtro) permanece visible siempre: en modo normal acota la
    // lista de naves; en modo Otros define el tipo de la nave nueva. Se mantiene la
    // función por compatibilidad, pero ya no se oculta.
    function mostrarTipoFiltro(show) {
      if (!tipoSel) return;
      var wrap = tipoSel.closest('.op-field');
      if (wrap) wrap.style.display = '';
    }
    // Autocompleta la fecha operativa (ETB/ETD) con la fecha del turno + la hora
    // actual. Editable: el usuario puede ajustarla antes de guardar.
    function prefillOpFecha() {
      var el = body.querySelector('[data-k="op_fecha"]');
      if (!el || el.value) return;
      var pad = function (n) { return String(n).padStart(2, '0'); };
      var now = new Date();
      var fecha = (turno && turno.fecha) ? turno.fecha
        : (now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()));
      el.value = fecha + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    }
    prefillOpFecha();
    naveSel.addEventListener('change', function () {
      var esOtros = naveSel.value === '__otros__';
      mostrarNuevaNav(esOtros);
      mostrarTipoFiltro(!esOtros);
      aplicarModoCementero();
    });
    // Al cambiar el tipo de la nave nueva (Otros), refrescar etiquetas de flujo y
    // la visibilidad de la descarga interna según ese tipo.
    var nnTipoSel = body.querySelector('[data-k="nn_tipo"]');
    if (nnTipoSel) nnTipoSel.addEventListener('change', aplicarModoCementero);

    // El planned es editable/obligatorio cuando se está iniciando una nave
    // (status Inicio) o registrando una nave nueva (Otros). En el resto de los
    // casos queda heredado de la nave y de solo lectura.
    function plannedEditable(on) {
      var lab = plannedIn.closest('.op-field') && plannedIn.closest('.op-field').querySelector('label');
      if (on) {
        plannedIn.removeAttribute('readonly'); plannedIn.placeholder = 'Ingresa el planned';
        if (lab) lab.innerHTML = 'Planned <span class="op-req">*</span>';
      } else {
        plannedIn.setAttribute('readonly', ''); plannedIn.placeholder = '—';
        if (lab) lab.textContent = 'Planned (heredado de la nave)';
      }
    }
    // El campo de fecha (ETB/ETD) solo aplica a Inicio y Culminado; en En Proceso
    // no se usa, así que se oculta.
    function aplicarFechaSegunStatus() {
      var el = body.querySelector('[data-k="op_fecha"]');
      var wrap = el && el.closest('.op-field');
      if (wrap) wrap.style.display = (getField('status_act') === 'En Proceso') ? 'none' : '';
    }
    function aplicarPlannedSegunStatus() {
      plannedEditable(naveSel.value === '__otros__' || getField('status_act') === 'Inicio');
      aplicarFechaSegunStatus();
    }

    function heredarActividad(n) { if (actSel && n && n.actividad_id != null) actSel.value = String(n.actividad_id); }

    async function sugerirStatus() {
      acumuladoPrevio = 0; statusSugerido = 'Inicio';
      var naveId = getField('nave_id'), actId = getField('actividad_id');
      if (naveId === '__otros__') { recalc(); return; }  // nave nueva: sin historial
      if (!naveId || !actId) { setStatusNote('No hay registros de nave y actividad.'); recalc(); return; }
      try {
        var r = await OP.opApi('tallyman/estado-sugerido', { query: {
          nave_id: naveId, actividad_id: actId,
          ubicacion: getField('ubicacion') || '',
          fecha_turno: (turno && turno.fecha) || '', turno: (turno && turno.turno) || ''
        } });
        if (!r || !r.data) return;
        var status = r.data.status, ultimo = r.data.ultimo;
        acumuladoPrevio = Number(r.data.acumulado) || 0;
        statusSugerido = status;
        setSeg('status_act', status);
        aplicarPlannedSegunStatus();
        if (status === 'Inicio' && !ultimo)
          setStatusNote('Sugerido automáticamente: Inicio — no hay registros previos de esta nave y actividad.');
        else if (status === 'Inicio' && ultimo === 'Culminado')
          setStatusNote('Sugerido automáticamente: Inicio — el ciclo anterior fue Culminado, empieza uno nuevo.');
        else if (status === 'En Proceso')
          setStatusNote('Sugerido: En Proceso — avance previo del turno anterior: ' + acumuladoPrevio.toFixed(2) + '.');
        else setStatusNote('');
        recalc();
      } catch (e) { setStatusNote(''); }
    }

    naveSel.addEventListener('change', function () {
      if (naveSel.value === '__otros__') {
        // Una nave nueva siempre arranca un ciclo → Status "Inicio" (con planned obligatorio).
        acumuladoPrevio = 0; statusSugerido = 'Inicio';
        setSeg('status_act', 'Inicio');
        setStatusNote('Nueva nave → Status "Inicio". Ingresa el Planned (obligatorio).');
        plannedIn.value = '';
        plannedEditable(true);
        var nnTipo = body.querySelector('[data-k="nn_tipo"]');
        if (nnTipo && tipoSel && tipoSel.value) nnTipo.value = tipoSel.value;  // hereda el tipo del filtro
        plannedIn.focus();
        aplicarModoCementero();  // refresca etiquetas/visibilidad según el tipo heredado
        recalc(); return;
      }
      var n = naveById(naveSel.value);
      // Mantener el filtro de tipo en sintonía con la nave elegida.
      if (tipoSel && n && n.tipo_nave_id != null) tipoSel.value = String(n.tipo_nave_id);
      plannedIn.value = n ? plannedDeNave(n) : '';
      heredarActividad(n);
      aplicarMuelleDeNave(n);
      aplicarPlannedSegunStatus();
      aplicarModoCementero();
      recalc();
      sugerirStatus();
    });
    if (actSel) actSel.addEventListener('change', function () {
      // Al cambiar la actividad: refrescar rótulos (Cement Big Bags → despacho indirecto),
      // recalcular el avance y volver a sugerir el status/acumulado.
      aplicarEtiquetasFlujo();
      aplicarVisibilidadInterna();
      sugerirStatus();
    });

    // Cambiar el Status a Inicio habilita el planned editable/obligatorio.
    var statusSeg = body.querySelector('[data-seg="status_act"]');
    if (statusSeg) statusSeg.addEventListener('click', function (e) {
      if (e.target.closest('.op-seg')) aplicarPlannedSegunStatus();
    });

    sugerirStatus();
    aplicarPlannedSegunStatus();
    aplicarModoCementero();

    if (ubicSel && ubicSel.tagName === 'SELECT') {
      ubicSel.addEventListener('change', function () {
        var ns = navesEnBerth(ubicSel.value);
        if (ns.length >= 1) {
          // Sincroniza el filtro de tipo y reconstruye la lista para no perder la nave.
          if (tipoSel && ns[0].tipo_nave_id != null) tipoSel.value = String(ns[0].tipo_nave_id);
          rebuildNaves(String(ns[0].id), ns[0].id);
          naveSel.value = String(ns[0].id);
          plannedIn.value = plannedDeNave(ns[0]);
          heredarActividad(ns[0]);
          aplicarMuelleDeNave(ns[0]);
          aplicarPlannedSegunStatus();
          aplicarModoCementero();
          recalc();
          sugerirStatus();
          setUbicNote(ns.length > 1 ? 'Hay varias naves en este muelle — verifica la nave seleccionada.' : '');
        } else {
          naveSel.value = ''; plannedIn.value = '';
          setUbicNote('No hay nave registrada en este muelle (Operaciones).');
          sugerirStatus();
        }
      });
    }
    if (directaIn) directaIn.addEventListener('input', recalc);
    if (internaIn) internaIn.addEventListener('input', recalc);
  }

  function prefill(m, reg) {
    if (m === 'INCIDENCIA') {
      setSeg('hubo', reg.hubo ? 'Sí' : 'No');
      setField('detalle', reg.detalle || '');
      bodyEl().querySelector('[data-incdet]').style.display = reg.hubo ? '' : 'none';
      return;
    }
    setField('ubicacion', reg.ubicacion);
    setField('actividad_id', String(reg.actividad_id));
    setSeg('status_act', reg.status_act);
    setField('planned', reg.planned != null ? reg.planned : '');
    setField('details', reg.details || '');

    if (m === 'BERTH') {
      setField('nave_id', reg.nave_id != null ? String(reg.nave_id) : '');
      // Reflejar el TIPO DE NAVE real (heredado de Operaciones). Se prefiere el que
      // viene en el registro (válido aun si la nave ya no está activa); como respaldo,
      // el del catálogo de naves activas.
      var nEdit = naveById(reg.nave_id);
      var tipoReal = (reg.tipo_nave_id != null) ? reg.tipo_nave_id
        : (nEdit && nEdit.tipo_nave_id != null ? nEdit.tipo_nave_id : null);
      var tipoFilEl = bodyEl().querySelector('[data-k="tipo_filtro"]');
      if (tipoFilEl && tipoReal != null) tipoFilEl.value = String(tipoReal);
      var _dir = reg.directa, _int = reg.interna;
      if (_dir == null && _int == null && reg.executed != null) _dir = reg.executed;
      setField('directa', _dir != null ? _dir : '');
      setField('interna', _int != null ? _int : '');
      setField('productivity', reg.productivity != null ? reg.productivity : '');
      aplicarMuelleDeNave(naveById(reg.nave_id), true);
      // Al editar un registro en Inicio, el planned queda editable (es obligatorio).
      if (reg.status_act === 'Inicio') {
        var plEl = bodyEl().querySelector('[data-k="planned"]');
        if (plEl) { plEl.removeAttribute('readonly'); plEl.placeholder = 'Ingresa el planned'; }
      }
      // La fecha (ETB/ETD) solo aplica a Inicio/Culminado; ocultar en En Proceso.
      var fEl = bodyEl().querySelector('[data-k="op_fecha"]');
      var fWrap = fEl && fEl.closest('.op-field');
      if (fWrap) fWrap.style.display = (reg.status_act === 'En Proceso') ? 'none' : '';
      // Reflejar etiquetas/flujo según el tipo de nave (Cementero vs. estándar).
      aplicarModoCementero();
    } else if (m === 'YARD') {
      // Restaurar la selección de nave (patio)
      if (reg.nave_id != null) {
        setField('nave_id', 'n:' + reg.nave_id);
      } else if (reg.nave_patio) {
        var idx = patioNombres.indexOf(reg.nave_patio);
        if (idx >= 0) setField('nave_id', 'p:' + idx);
        // si no está en la lista (ya Culminado), dejar vacío
      }
      setField('executed', reg.executed != null ? reg.executed : '');
      setField('cargo_type', reg.cargo_type || '');
      setField('bl', reg.bl || '');
      setField('producto', reg.producto || '');
      setField('unidades', reg.unidades != null ? reg.unidades : '');
      setField('tons', reg.tons != null ? reg.tons : '');
      setField('ubic_codigo', reg.ubic_codigo || '');
    }
    recalc();
  }

  // ───────── guardar / eliminar ─────────
  async function guardar() {
    if (modo === 'INCIDENCIA') return guardarIncidencia();

    var body;
    // Intención de propagar el TIPO DE NAVE a Operaciones (se aplica tras guardar
    // el registro, solo en BERTH con nave real cuyo tipo cambió).
    var propTipo = null;
    // Intención de propagar el MUELLE / ACTIVIDAD a la nave en Operaciones (solo
    // BERTH con nave real cuyo muelle o actividad difieran de los de la nave).
    var propMuelleAct = null;

    if (modo === 'YARD') {
      var ejecutadoStr = getField('executed').toString().trim();
      if (ejecutadoStr === '') { OP.toast('Ingresa Executed in Turn', 'error'); return; }
      var ejecutado = Number(ejecutadoStr);

      var navePSel = getField('nave_id');
      var naveIdFinalY = null, navePatio = null;
      if (navePSel && navePSel.startsWith('n:')) {
        naveIdFinalY = Number(navePSel.slice(2));
      } else if (navePSel && navePSel.startsWith('p:')) {
        var idx = Number(navePSel.slice(2));
        navePatio = patioNombres[idx] || null;
      } else if (navePSel === '__nuevo__') {
        navePatio = getField('np_nombre_patio').trim();
        if (!navePatio) { OP.toast('Ingresa el nombre del patio', 'error'); return; }
      }

      body = {
        fecha_turno: turno.fecha, turno: turno.turno,
        ubicacion_tipo: 'YARD', ubicacion: getField('ubicacion').trim(),
        nave_id: naveIdFinalY, nave_patio: navePatio,
        actividad_id: Number(getField('actividad_id')),
        status_act: getField('status_act'),
        planned: getField('planned') || null,
        executed: ejecutado,
        directa: null, interna: null,
        details: getField('details').trim() || null,
        cargo_type: getField('cargo_type').trim() || null,
        bl: getField('bl').trim() || null,
        producto: getField('producto').trim() || null,
        unidades: getField('unidades') || null,
        tons: getField('tons') || null,
        ubic_codigo: getField('ubic_codigo').trim() || null
      };
    } else {
      // BERTH
      var directaStr = getField('directa').toString().trim();
      var internaStr = getField('interna').toString().trim();
      if (directaStr === '' && internaStr === '') {
        OP.toast(esCementeroSel()
          ? 'Ingresa Despacho Indirecto y/o Movimiento Interno'
          : 'Ingresa Descarga Directa y/o Interna', 'error');
        return;
      }
      var directa = directaStr === '' ? null : Number(directaStr);
      var interna = internaStr === '' ? null : Number(internaStr);

      // En Inicio el planned es obligatorio (define el total y se refleja en la nave).
      var statusBerth = getField('status_act');
      var plannedStr = getField('planned').toString().trim();
      if (statusBerth === 'Inicio' && (plannedStr === '' || Number(plannedStr) <= 0)) {
        OP.toast('El Planned es obligatorio cuando el status es Inicio', 'error'); return;
      }

      // Cement Big Bags: el avance del muelle (executed) cuenta SOLO la descarga interna;
      // la descarga directa genera la actividad de patio en el backend.
      var esCem = esActividadCementBigBags();

      // Fecha operativa (ETB en Inicio / ETD en Culminado): 'YYYY-MM-DD HH:MM:SS'.
      // Se calcula antes de crear la nave para usarla también como ETB/ATB inicial.
      // El campo de fecha se retiró del formulario: el ETB/ETD se registra
      // automáticamente con la fecha del turno + la hora actual ('YYYY-MM-DD HH:MM:SS').
      var opFechaV = getField('op_fecha');
      var opFecha;
      if (opFechaV) {
        opFecha = opFechaV.replace('T', ' ') + (opFechaV.length === 16 ? ':00' : '');
      } else {
        var _pad = function (n) { return String(n).padStart(2, '0'); };
        var _now = new Date();
        var _fecha = (turno && turno.fecha) ? turno.fecha
          : (_now.getFullYear() + '-' + _pad(_now.getMonth() + 1) + '-' + _pad(_now.getDate()));
        opFecha = _fecha + ' ' + _pad(_now.getHours()) + ':' + _pad(_now.getMinutes()) + ':' + _pad(_now.getSeconds());
      }

      var naveIdFinal = getField('nave_id') ? getField('nave_id') : null;
      if (naveIdFinal === '__otros__') {
        var nnNombre = getField('nn_nombre').trim();
        var nnTipo   = bodyEl().querySelector('[data-k="nn_tipo"]');
        var nnTipoV  = nnTipo ? nnTipo.value : '';
        if (!nnNombre) { OP.toast('Ingresa el nombre de la nave', 'error'); return; }
        if (!nnTipoV)  { OP.toast('Selecciona el tipo de nave', 'error'); return; }
        if (plannedStr === '' || Number(plannedStr) <= 0) { OP.toast('Ingresa el Planned de la nave', 'error'); return; }
        var actIdNueva = Number(getField('actividad_id')) || null;
        // El planned se guarda bajo la clave del operativo que corresponde al tipo de
        // nave (Ro-Ro → "vehiculos", Containera → "teus", resto → "cantidad_total"),
        // para que Operaciones lo muestre con la unidad correcta.
        var plannedDatos = {};
        plannedDatos[plannedKeyPorTipo(tipoNombrePorId(nnTipoV))] = Number(plannedStr);
        var btn2 = $('tmModalSave'); btn2.disabled = true;
        try {
          // Al crear la nave se reflejan de una vez: la ACTIVIDAD seleccionada, el
          // PLANNED y el ETB/ATB = momento de creación (la fecha del formulario). La
          // sincronización del registro (Inicio) reafirma estos valores en el backend.
          var naveResp = await OP.opApi('naves', { method: 'POST', body: {
            nombre: nnNombre, tipo_nave_id: Number(nnTipoV),
            actividad_id: actIdNueva,
            // La ubicación del muelle ya viene como "Berth 0N" (nomenclatura de Operaciones).
            muelle: getField('ubicacion').trim() || null,
            // ETB/ATB = momento de creación. Se escribe en `eta` (columna que el
            // formulario de Operaciones usa para "ETB/ATB") y en `etb` por consistencia.
            eta: opFecha, etb: opFecha,
            estado: 'En Operaciones', datos_adicionales: plannedDatos
          }});
          naveIdFinal = String(naveResp.data.id);
          naves.push(naveResp.data);
          OP.toast('Nave "' + OP.esc(nnNombre) + '" creada en Operaciones', 'success');
        } catch (e) { OP.toast('Error al crear la nave: ' + e.message, 'error'); btn2.disabled = false; return; }
      }

      body = {
        fecha_turno: turno.fecha, turno: turno.turno,
        ubicacion_tipo: 'BERTH', ubicacion: getField('ubicacion').trim(),
        nave_id: naveIdFinal ? Number(naveIdFinal) : null, nave_patio: null,
        actividad_id: Number(getField('actividad_id')),
        status_act: getField('status_act'),
        planned: getField('planned') || null,
        executed: esCem ? (interna || 0) : (directa || 0) + (interna || 0),
        directa: directa, interna: interna,
        productivity: null,
        details: getField('details').trim() || null,
        op_fecha: opFecha
      };

      // Propagar el TIPO DE NAVE a Operaciones cuando hay una nave real seleccionada
      // (no "Otros", que ya nace con su tipo) y el tipo elegido difiere del actual.
      if (naveIdFinal && getField('nave_id') !== '__otros__') {
        var tipoElB = bodyEl().querySelector('[data-k="tipo_filtro"]');
        var tipoElV = tipoElB ? tipoElB.value : '';
        var naveSelObj = naveById(naveIdFinal);
        var tipoActual = (naveSelObj && naveSelObj.tipo_nave_id != null) ? String(naveSelObj.tipo_nave_id) : '';
        if (tipoElV && tipoElV !== tipoActual) {
          propTipo = { naveId: Number(naveIdFinal), tipo_nave_id: Number(tipoElV) };
        }

        // Propagar MUELLE / ACTIVIDAD a la nave cuando difieran de los actuales.
        // Solo se envían los campos que cambiaron (el backend ignora lo no enviado).
        if (naveSelObj) {
          var nuevoMuelle = getField('ubicacion').trim();
          var nuevaActId  = Number(getField('actividad_id')) || null;
          var cambiosNave = {};
          if (nuevoMuelle && nuevoMuelle !== String(naveSelObj.muelle || '')) {
            cambiosNave.muelle = nuevoMuelle;
          }
          if (nuevaActId && String(nuevaActId) !== String(naveSelObj.actividad_id || '')) {
            cambiosNave.actividad_id = nuevaActId;
          }
          if (Object.keys(cambiosNave).length) {
            propMuelleAct = { naveId: Number(naveIdFinal), cambios: cambiosNave };
          }
        }
      }
    }

    var btn = $('tmModalSave'); btn.disabled = true;
    try {
      if (editId) await OP.opApi('tallyman/registros/' + editId, { method: 'PUT', body: body });
      else await OP.opApi('tallyman/registros', { method: 'POST', body: body });
      OP.toast('Actividad guardada', 'success');
      // Propagar el tipo de nave a Operaciones (best-effort: no bloquea el guardado).
      if (propTipo) {
        try {
          var trsp = await OP.opApi('naves/' + propTipo.naveId + '/tipo', { method: 'PUT', body: { tipo_nave_id: propTipo.tipo_nave_id } });
          var nc = naveById(propTipo.naveId);
          if (nc) nc.tipo_nave_id = (trsp && trsp.data && trsp.data.tipo_nave_id != null) ? trsp.data.tipo_nave_id : propTipo.tipo_nave_id;
          OP.toast('Tipo de nave actualizado en Operaciones', 'success');
        } catch (e) {
          OP.toast('Actividad guardada, pero no se pudo actualizar el tipo de nave: ' + e.message, 'error');
        }
      }
      // Propagar muelle/actividad a la nave en Operaciones (best-effort: no bloquea).
      if (propMuelleAct) {
        try {
          var mrsp = await OP.opApi('naves/' + propMuelleAct.naveId + '/muelle-actividad', { method: 'PUT', body: propMuelleAct.cambios });
          var ncm = naveById(propMuelleAct.naveId);
          if (ncm && mrsp && mrsp.data) {
            if (mrsp.data.muelle != null || 'muelle' in propMuelleAct.cambios) ncm.muelle = mrsp.data.muelle;
            if (mrsp.data.actividad_id != null || 'actividad_id' in propMuelleAct.cambios) ncm.actividad_id = mrsp.data.actividad_id;
          }
          OP.toast('Muelle/actividad de la nave actualizados en Operaciones', 'success');
        } catch (e) {
          OP.toast('Actividad guardada, pero no se pudo actualizar muelle/actividad de la nave: ' + e.message, 'error');
        }
      }
      // Refrescar navesPatioData tras cualquier guardado: en YARD aparecen nuevos
      // nombres de patio, y en BERTH cambia la descarga interna acumulada
      // (planned_patio) que el formulario de Patio usa como Planned.
      try {
        var pd = await OP.opApi('tallyman/naves-patio');
        navesPatioData = pd.data || { naves: [], nombres_patio: [] };
        cargarPatioNombres(navesPatioData);
      } catch (e) { /* silencioso */ }
      cerrarModal(); await refrescar();
    } catch (e) { OP.toast(e.message, 'error'); }
    finally { btn.disabled = false; }
  }
  async function guardarIncidencia() {
    var hubo = getField('hubo') === 'Sí';
    var detalle = getField('detalle').trim();
    if (hubo && !detalle) { OP.toast('Detalla el incidente', 'error'); return; }
    var btn = $('tmModalSave'); btn.disabled = true;
    try {
      await OP.opApi('tallyman/incidencias', { method: 'POST', body: { fecha_turno: turno.fecha, turno: turno.turno, hubo: hubo, detalle: detalle } });
      OP.toast('Incidencia guardada', 'success');
      cerrarModal(); await recargarIncidencia();
    } catch (e) { OP.toast(e.message, 'error'); }
    finally { btn.disabled = false; }
  }
  async function eliminar(id) {
    if (modoRango === 'turno' && !puedeModificarTurno()) {
      OP.toast('Solo Administrador o Supervisor pueden eliminar en turnos anteriores.', 'error'); return;
    }
    if (!confirm('¿Eliminar esta actividad registrada?')) return;
    try {
      await OP.opApi('tallyman/registros/' + id, { method: 'DELETE' });
      OP.toast('Actividad eliminada', 'success');
      try {
        var pd = await OP.opApi('tallyman/naves-patio');
        navesPatioData = pd.data || { naves: [], nombres_patio: [] };
        cargarPatioNombres(navesPatioData);
      } catch (e) { /* silencioso */ }
      await refrescar();
    }
    catch (e) { OP.toast(e.message, 'error'); }
  }

  // ───────── render de tarjetas ─────────
  function card(reg) {
    var nombreNave = reg.nave || reg.nave_patio || '';
    var pct = reg.porcentaje != null ? Number(reg.porcentaje) : null;
    var nums = pct == null
      ? '<div class="tm-nums">Executed ' + fmt(reg.executed) + '</div>'
      : '<div class="tm-bar"><i style="width:' + pct.toFixed(1) + '%"></i></div>' +
        '<div class="tm-nums">Executed ' + fmt(reg.executed) + ' / Planned ' + fmt(reg.planned) +
        ' · Acum ' + fmt(reg.accumulated) + ' · ' + pct.toFixed(1) + '%</div>';
    return '<div class="tm-item" data-id="' + reg.id + '">' +
      '<div class="tm-item-top"><div><div class="tm-item-loc">' + OP.esc(reg.ubicacion) + (nombreNave ? ' · ' + OP.esc(nombreNave) : '') + '</div>' +
      '<div class="tm-item-act">' + OP.esc(reg.actividad) + '</div></div>' +
      '<span class="tm-chip ' + statusClass(reg.status_act) + '">' + OP.esc(reg.status_act) + '</span></div>' +
      nums +
      (puedeModificarTurno() ? '<div class="tm-item-actions"><button class="tm-iconbtn" data-edit="' + reg.id + '">✎ Editar</button>' +
        '<button class="tm-iconbtn del" data-del="' + reg.id + '">🗑 Eliminar</button></div>' : '') +
      '</div>';
  }
  function renderListas() {
    var berth = registros.filter(function (r) { return r.ubicacion_tipo === 'BERTH'; });
    var yard = registros.filter(function (r) { return r.ubicacion_tipo === 'YARD'; });
    $('tmBerthList').innerHTML = berth.length ? berth.map(card).join('') : '<div class="tm-empty">Sin actividad de muelle en este turno.</div>';
    $('tmYardList').innerHTML = yard.length ? yard.map(card).join('') : '<div class="tm-empty">Sin actividad de patio en este turno.</div>';
  }
  function renderIncidencia() {
    var v = $('tmIncView');
    if (!v) return;
    if (!incidencia) { v.innerHTML = '<div class="tm-empty">Sin incidencia registrada.</div>'; return; }
    var txt = incidencia.hubo ? OP.esc(incidencia.detalle || '') : 'Sin incidentes durante el turno.';
    v.innerHTML = '<div class="tm-item"><div class="tm-item-top"><div class="tm-item-loc">' +
      (incidencia.hubo ? 'Incidente reportado' : 'Sin incidentes') + '</div>' +
      '<span class="tm-chip ' + (incidencia.hubo ? 's1' : 's2') + '">' + (incidencia.hubo ? 'Sí' : 'No') + '</span></div>' +
      '<div class="tm-item-act" style="white-space:pre-wrap">' + txt + '</div>' +
      (puedeModificarTurno() ? '<div class="tm-item-actions"><button class="tm-iconbtn" id="tmIncEdit">✎ Editar</button></div>' : '') + '</div>';
    if (puedeModificarTurno()) $('tmIncEdit').addEventListener('click', function () { abrirModal('INCIDENCIA', incidencia || { hubo: false, detalle: '' }); });
  }

  // ───────── carga ─────────
  async function recargarRegistros() {
    var r = await OP.opApi('tallyman/registros', { query: { fecha: turno.fecha, turno: turno.turno } });
    registros = r.data || []; renderListas();
  }
  async function recargarIncidencia() {
    var r = await OP.opApi('tallyman/incidencias', { query: { fecha: turno.fecha, turno: turno.turno } });
    incidencia = r.data || null; renderIncidencia();
  }
  async function cargarTurno() {
    // Si el coordinador eligió su turno al ingresar, abrir en ESE turno (el backend
    // resuelve la fecha: turno noche en la mañana → día anterior). Si no, turno vigente.
    var sel = sessionStorage.getItem('tm_turno_codigo');
    var url = '../includes/tallyman_turno.php' + (sel ? ('?jornada=' + encodeURIComponent(sel)) : '');
    var r = await fetch(url, { cache: 'no-store' });
    if (!r.ok) throw new Error('No se pudo obtener el turno (HTTP ' + r.status + ').');
    var d = await r.json();
    if (!d.success) throw new Error(d.error || 'Sin turno');
    turno = d.data;
    turnoActual = { fecha: turno.fecha, turno: turno.turno };
    $('tmTurno').textContent = 'Turno: ' + turno.label + ' · ' + turno.fecha;
    // Sincronizar selector con el turno actual
    var fi = $('tmFechaFiltro'), js = $('tmJornadaFiltro');
    if (fi) fi.value = turno.fecha;
    if (js) js.value = turno.turno;
  }

  async function cargarJornadas() {
    try {
      var r = await fetch('../includes/tallyman_turno.php?action=jornadas', { cache: 'no-store' });
      var d = await r.json();
      jornadas = (d.success && d.data) ? d.data : [];
      var sel = $('tmJornadaFiltro');
      if (!sel || !jornadas.length) return;
      sel.innerHTML = jornadas.map(function (j) {
        return '<option value="' + OP.esc(j.codigo) + '">' + OP.esc(j.nombre) + '</option>';
      }).join('');
    } catch (e) { /* silencioso */ }
  }

  async function aplicarFiltro() {
    var fecha = $('tmFechaFiltro').value;
    var jornada = $('tmJornadaFiltro').value;
    if (!fecha || !jornada) return;
    try {
      var url = '../includes/tallyman_turno.php?fecha=' + encodeURIComponent(fecha) +
                '&jornada=' + encodeURIComponent(jornada);
      var r = await fetch(url, { cache: 'no-store' });
      var d = await r.json();
      if (!d.success) { OP.toast(d.error || 'Turno no encontrado', 'error'); return; }
      turno = d.data;
      $('tmTurno').textContent = 'Turno: ' + turno.label + ' · ' + turno.fecha;
      actualizarModoLectura();
      await recargarRegistros();
      await recargarIncidencia();
    } catch (e) { OP.toast('Error al cambiar el filtro: ' + e.message, 'error'); }
  }

  // ───────── histórico por rango (solo Administrador) ─────────
  function pad2(n) { return String(n).padStart(2, '0'); }
  // ISO week ("YYYY-Www") → { desde, hasta } (lunes a domingo)
  function isoWeekToRange(val) {
    var m = /^(\d{4})-W(\d{2})$/.exec(val || ''); if (!m) return null;
    var year = +m[1], week = +m[2];
    var simple = new Date(Date.UTC(year, 0, 4));
    var dow = simple.getUTCDay() || 7;
    var monday = new Date(simple); monday.setUTCDate(simple.getUTCDate() - (dow - 1) + (week - 1) * 7);
    var sunday = new Date(monday); sunday.setUTCDate(monday.getUTCDate() + 6);
    var f = function (d) { return d.getUTCFullYear() + '-' + pad2(d.getUTCMonth() + 1) + '-' + pad2(d.getUTCDate()); };
    return { desde: f(monday), hasta: f(sunday) };
  }
  // Mes ("YYYY-MM") → { desde, hasta }
  function monthToRange(val) {
    var m = /^(\d{4})-(\d{2})$/.exec(val || ''); if (!m) return null;
    var last = new Date(Date.UTC(+m[1], +m[2], 0)).getUTCDate();
    return { desde: m[1] + '-' + m[2] + '-01', hasta: m[1] + '-' + m[2] + '-' + pad2(last) };
  }
  function currentISOWeek() {
    var d = new Date(); d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    var dow = d.getUTCDay() || 7; d.setUTCDate(d.getUTCDate() + 4 - dow);
    var yStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    var wk = Math.ceil((((d - yStart) / 86400000) + 1) / 7);
    return d.getUTCFullYear() + '-W' + pad2(wk);
  }
  function currentMonth() { var d = new Date(); return d.getFullYear() + '-' + pad2(d.getMonth() + 1); }

  function renderRango() {
    var tb = $('tmRangoBody'); if (!tb) return;
    if (!registrosRango.length) {
      tb.innerHTML = '<tr><td colspan="12" class="tm-empty">Sin registros para este filtro.</td></tr>';
      if ($('tmRangoCount')) $('tmRangoCount').textContent = '';
      return;
    }
    if ($('tmRangoCount')) $('tmRangoCount').textContent = '· ' + registrosRango.length + ' registro' + (registrosRango.length !== 1 ? 's' : '');
    tb.innerHTML = registrosRango.map(function (reg) {
      var esBerth = reg.ubicacion_tipo === 'BERTH';
      var pct = reg.porcentaje != null ? Number(reg.porcentaje).toFixed(1) + '%' : '—';
      var nave = reg.nave || reg.nave_patio || '—';
      return '<tr data-id="' + reg.id + '">' +
        '<td>' + OP.esc(reg.fecha_turno) + '</td>' +
        '<td>' + OP.esc(reg.turno) + '</td>' +
        '<td><span class="tm-rango-tag ' + (esBerth ? 'berth' : 'yard') + '">' + (esBerth ? 'Muelle' : 'Patio') + '</span></td>' +
        '<td>' + OP.esc(reg.ubicacion || '—') + '</td>' +
        '<td>' + OP.esc(nave) + '</td>' +
        '<td>' + OP.esc(reg.actividad || '—') + '</td>' +
        '<td class="num">' + fmt(reg.planned) + '</td>' +
        '<td class="num">' + fmt(reg.executed) + '</td>' +
        '<td class="num">' + fmt(reg.accumulated) + '</td>' +
        '<td class="num">' + pct + '</td>' +
        '<td><span class="tm-chip ' + statusClass(reg.status_act) + '">' + OP.esc(reg.status_act) + '</span></td>' +
        '<td>' + (canReg ? '<button class="tm-iconbtn" data-edit="' + reg.id + '" title="Editar">✎</button> ' +
          '<button class="tm-iconbtn del" data-del="' + reg.id + '" title="Eliminar">🗑</button>' : '') + '</td>' +
        '</tr>';
    }).join('');
  }

  async function aplicarRango() {
    var q = {};
    if (modoRango === 'dia') {
      var d = $('tmDiaInput').value;
      if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) { OP.toast('Selecciona un día', 'error'); return; }
      q.desde = d; q.hasta = d;
    } else if (modoRango === 'semana') {
      var rs = isoWeekToRange($('tmSemanaInput').value);
      if (!rs) { OP.toast('Selecciona una semana', 'error'); return; }
      q.desde = rs.desde; q.hasta = rs.hasta;
    } else if (modoRango === 'mes') {
      var rm = monthToRange($('tmMesInput').value);
      if (!rm) { OP.toast('Selecciona un mes', 'error'); return; }
      q.desde = rm.desde; q.hasta = rm.hasta;
    } // 'todos' → sin parámetros (todo el histórico)
    if ($('tmRangoBody')) $('tmRangoBody').innerHTML = '<tr><td colspan="12" class="tm-empty">Cargando…</td></tr>';
    try {
      var r = await OP.opApi('tallyman/registros-rango', { query: q });
      registrosRango = r.data || [];
      renderRango();
    } catch (e) {
      if ($('tmRangoBody')) $('tmRangoBody').innerHTML = '<tr><td colspan="12" class="tm-empty">' + OP.esc(e.message) + '</td></tr>';
      OP.toast(e.message, 'error');
    }
  }

  function setModoRango(m) {
    modoRango = m;
    var esRango = (m !== 'turno');
    if ($('tmDiaInput'))    $('tmDiaInput').style.display = (m === 'dia') ? '' : 'none';
    if ($('tmSemanaInput')) $('tmSemanaInput').style.display = (m === 'semana') ? '' : 'none';
    if ($('tmMesInput'))    $('tmMesInput').style.display = (m === 'mes') ? '' : 'none';
    // El filtro por turno (fecha/jornada/hoy) solo aplica en modo 'turno'.
    if ($('tmFechaFiltro'))   $('tmFechaFiltro').style.display = esRango ? 'none' : '';
    if ($('tmJornadaFiltro')) $('tmJornadaFiltro').style.display = esRango ? 'none' : '';
    if (esRango && $('tmHoyBtn')) $('tmHoyBtn').style.display = 'none';
    // Alternar tarjetas (turno) ↔ tabla (rango).
    var berthCard = $('tmBerthList') && $('tmBerthList').closest('.tm-card');
    var yardCard  = $('tmYardList') && $('tmYardList').closest('.tm-card');
    if (berthCard) berthCard.style.display = esRango ? 'none' : '';
    if (yardCard)  yardCard.style.display = esRango ? 'none' : '';
    if ($('tmRangoCard')) $('tmRangoCard').style.display = esRango ? '' : 'none';
    if (!esRango) { actualizarModoLectura(); return; }
    if (m === 'todos') { aplicarRango(); return; }
    var tieneFecha = (m === 'dia') ? $('tmDiaInput').value
                   : (m === 'semana') ? $('tmSemanaInput').value
                   : $('tmMesInput').value;
    if (tieneFecha) aplicarRango();
    else if ($('tmRangoBody')) {
      var qtxt = (m === 'dia') ? 'un día' : (m === 'semana') ? 'una semana' : 'un mes';
      $('tmRangoBody').innerHTML = '<tr><td colspan="12" class="tm-empty">Selecciona ' + qtxt + '.</td></tr>';
      if ($('tmRangoCount')) $('tmRangoCount').textContent = '';
    }
  }

  // Refresca la vista activa tras guardar/eliminar (turno o rango).
  async function refrescar() {
    if (modoRango === 'turno') await recargarRegistros();
    else await aplicarRango();
  }

  // Abre el modal de edición desde la tabla, inyectando la nave/nombre patio
  // histórico al catálogo si ya no está activo, para no perder el vínculo al guardar.
  function abrirEdicionRango(reg) {
    if (reg.ubicacion_tipo === 'YARD') {
      if (reg.nave_patio && patioNombres.indexOf(reg.nave_patio) < 0) patioNombres.push(reg.nave_patio);
    } else if (reg.nave_id != null && !naveById(reg.nave_id)) {
      naves.push({ id: reg.nave_id, nombre: reg.nave || ('Nave #' + reg.nave_id), muelle: reg.ubicacion, datos_adicionales: null });
    }
    abrirModal(reg.ubicacion_tipo, reg);
  }

  // ───────── listeners ─────────
  function enlazar() {
    $('addBerth').addEventListener('click', function () { abrirModal('BERTH'); });
    $('addYard').addEventListener('click', function () { abrirModal('YARD'); });
    $('tmModalClose').addEventListener('click', cerrarModal);
    $('tmModalCancel').addEventListener('click', cerrarModal);
    $('tmModal').addEventListener('click', function (e) { if (e.target === $('tmModal')) cerrarModal(); });
    $('tmModalSave').addEventListener('click', guardar);
    ['tmBerthList', 'tmYardList'].forEach(function (id) {
      $(id).addEventListener('click', function (e) {
        var ed = e.target.closest('[data-edit]');
        if (ed) { var reg = registros.filter(function (r) { return String(r.id) === ed.getAttribute('data-edit'); })[0]; if (reg) abrirModal(reg.ubicacion_tipo, reg); return; }
        var dl = e.target.closest('[data-del]');
        if (dl) eliminar(Number(dl.getAttribute('data-del')));
      });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrarModal(); });

    // ── Filtro de fecha / jornada ──
    var fi = $('tmFechaFiltro'), js = $('tmJornadaFiltro'), hoy = $('tmHoyBtn');
    if (fi) fi.addEventListener('change', function () { if (datosListos) aplicarFiltro(); });
    if (js) js.addEventListener('change', function () { if (datosListos) aplicarFiltro(); });
    if (hoy) hoy.addEventListener('click', function () {
      if (!turnoActual) return;
      var fi2 = $('tmFechaFiltro'), js2 = $('tmJornadaFiltro');
      if (fi2) fi2.value = turnoActual.fecha;
      if (js2) js2.value = turnoActual.turno;
      aplicarFiltro();
    });

    // ── Histórico por rango (solo Administrador) ──
    if (isAdmin) {
      var modoSel = $('tmModoFiltro');
      if (modoSel) modoSel.addEventListener('change', function () {
        var m = modoSel.value;
        if (m === 'dia' && $('tmDiaInput') && !$('tmDiaInput').value) $('tmDiaInput').value = (turnoActual && turnoActual.fecha) || (turno && turno.fecha) || '';
        if (m === 'semana' && $('tmSemanaInput') && !$('tmSemanaInput').value) $('tmSemanaInput').value = currentISOWeek();
        if (m === 'mes' && $('tmMesInput') && !$('tmMesInput').value) $('tmMesInput').value = currentMonth();
        setModoRango(m);
      });
      if ($('tmDiaInput'))    $('tmDiaInput').addEventListener('change', function () { if (modoRango === 'dia') aplicarRango(); });
      if ($('tmSemanaInput')) $('tmSemanaInput').addEventListener('change', function () { if (modoRango === 'semana') aplicarRango(); });
      if ($('tmMesInput'))    $('tmMesInput').addEventListener('change', function () { if (modoRango === 'mes') aplicarRango(); });
      var rb = $('tmRangoBody');
      if (rb) rb.addEventListener('click', function (e) {
        var ed = e.target.closest('[data-edit]');
        if (ed) { var reg = registrosRango.filter(function (r) { return String(r.id) === ed.getAttribute('data-edit'); })[0]; if (reg) abrirEdicionRango(reg); return; }
        var dl = e.target.closest('[data-del]');
        if (dl) eliminar(Number(dl.getAttribute('data-del')));
      });
    }
  }

  async function init() {
    enlazar();
    try {
      await Promise.all([cargarTurno(), cargarJornadas()]);
      // Re-sincronizar el selector tras (re)construir las opciones de jornada,
      // para que muestre el turno cargado (evita carrera con cargarJornadas).
      if (turno) {
        var fiInit = $('tmFechaFiltro'), jsInit = $('tmJornadaFiltro');
        if (fiInit) fiInit.value = turno.fecha;
        if (jsInit) jsInit.value = turno.turno;
      }
      var a = await OP.opApi('tallyman/actividades'); actividades = a.data || [];
      var ti = await OP.opApi('tipos-nave'); tipos = ti.data || [];
      var n = await OP.opApi('naves'); naves = n.data || [];
      var p = await OP.opApi('tallyman/naves-patio');
      navesPatioData = p.data || { naves: [], nombres_patio: [] };
      cargarPatioNombres(navesPatioData);
      await recargarRegistros();
      await recargarIncidencia();
      datosListos = true;
      actualizarModoLectura();
    } catch (e) {
      $('tmTurno').textContent = 'No se pudieron cargar los datos — ¿servicio de Operaciones activo?';
      $('tmBerthList').innerHTML = $('tmYardList').innerHTML = '<div class="tm-empty">No disponible.</div>';
      OP.toast('Error al cargar: ' + e.message, 'error');
    }
  }
  init();

  // ── Observación para relevo ──────────────────────────────────────────────
  (function () {
    var modal = $('tmObsModal'), txt = $('tmObsTxt'), info = $('tmObsInfo');
    var btn = $('tmObsBtn'), closeBtn = $('tmObsClose'), cancelBtn = $('tmObsCancelBtn'), saveBtn = $('tmObsSaveBtn');
    if (!modal || !btn) return;

    function abrirObs() {
      if (!turno) { OP.toast('Esperando datos del turno…', 'error'); return; }
      modal.style.display = 'flex';
      info.textContent = '';
      // Cargar observación existente
      fetch('../api/get_relevo_datos.php?fecha=' + encodeURIComponent(turno.fecha) + '&turno=' + encodeURIComponent(turno.turno), { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.success && d.generado && d.generado.observaciones) {
            txt.value = d.generado.observaciones;
            info.textContent = 'Última guardada por ' + d.generado.generado_por + ' · ' + d.generado.generado_en;
          }
        })
        .catch(function () {});
      txt.focus();
    }

    function cerrarObs() { modal.style.display = 'none'; }

    async function guardarObs() {
      if (!turno) return;
      var obs = txt.value.trim();
      saveBtn.disabled = true;
      try {
        var r = await fetch('../api/save_relevo.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ fecha: turno.fecha, turno: turno.turno, observaciones: obs, datos: {} })
        }).then(function (x) { return x.json(); });
        if (!r.success) throw new Error(r.error || 'Error al guardar');
        OP.toast('Observación guardada en el relevo', 'success');
        cerrarObs();
      } catch (e) { OP.toast(e.message, 'error'); }
      finally { saveBtn.disabled = false; }
    }

    btn.addEventListener('click', abrirObs);
    closeBtn.addEventListener('click', cerrarObs);
    cancelBtn.addEventListener('click', cerrarObs);
    saveBtn.addEventListener('click', guardarObs);
    modal.addEventListener('click', function (e) { if (e.target === modal) cerrarObs(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.style.display === 'flex') cerrarObs(); });
    txt.addEventListener('focus', function () { txt.style.borderColor = 'rgba(0,243,255,.4)'; txt.style.boxShadow = '0 0 0 3px rgba(0,243,255,.07)'; });
    txt.addEventListener('blur',  function () { txt.style.borderColor = 'rgba(0,243,255,.15)'; txt.style.boxShadow = ''; });
  }());
})();
