/* Relevo de turno · Reporte ejecutivo (rediseño apaisado).
   Cruza datos de la API Node (avance muelle + manifiesto de patio) y del PHP
   (personal/radios de turno_personal + incidencias del módulo). Permite
   capturar observaciones, "Generar" (guarda quién/cuándo) y exporta el PDF
   ejecutivo apaisado con jsPDF + autotable. Patrón window.OP. */
(function () {
  'use strict';
  var OP = window.OP, $ = OP.$;
  var rv = null, turnoInfo = null, turnoActual = null, jornadas = [];

  function esVistaActual() {
    return turnoActual && turnoInfo &&
      turnoInfo.fecha === turnoActual.fecha && turnoInfo.turno === turnoActual.turno;
  }
  function actualizarModoLectura() {
    var ro = !esVistaActual();
    var badge = $('rvReadonly'), hoyBtn = $('rvHoyBtn');
    if (badge) badge.style.display = ro ? '' : 'none';
    if (hoyBtn) hoyBtn.style.display = ro ? '' : 'none';
    var btn = $('rvGenerar'), lbl = $('rvGenerarLabel');
    if (btn) {
      if (ro) {
        if (lbl) lbl.textContent = 'Descargar PDF';
        btn.title = 'Exportar PDF de este turno anterior';
      } else {
        if (lbl) lbl.textContent = 'Generar relevo + PDF';
        btn.title = '';
      }
    }
  }

  function fmt(v) { return v == null || v === '' ? '—' : (Math.round(Number(v) * 100) / 100).toLocaleString('es-PE'); }
  function num(v) { return v == null || v === '' ? 0 : Number(v); }
  function muelles() { return rv.registros.filter(function (r) { return r.ubicacion_tipo === 'BERTH'; }); }
  function muellesPlan() { return muelles().filter(function (r) { return num(r.planned) > 0; }); }
  function patios() { return rv.registros.filter(function (r) { return r.ubicacion_tipo === 'YARD'; }); }

  // ───────── carga ─────────
  async function cargar(fecha, jornada) {
    var url = '../includes/tallyman_turno.php';
    if (fecha && jornada) url += '?fecha=' + encodeURIComponent(fecha) + '&jornada=' + encodeURIComponent(jornada);
    var tr = await fetch(url, { cache: 'no-store' });
    if (!tr.ok) throw new Error('No se pudo obtener el turno (HTTP ' + tr.status + ').');
    var td = await tr.json();
    if (!td.success) throw new Error(td.error || 'Sin turno');
    turnoInfo = td.data;

    var nodeP = OP.opApi('tallyman/relevo', { query: { fecha: turnoInfo.fecha, turno: turnoInfo.turno } });
    var phpP = fetch('../api/get_relevo_datos.php?fecha=' + encodeURIComponent(turnoInfo.fecha) + '&turno=' + encodeURIComponent(turnoInfo.turno), { cache: 'no-store' })
      .then(function (r) { return r.json(); }).catch(function () { return { success: false }; });
    var res = await Promise.all([nodeP, phpP]);

    rv = res[0].data;
    rv._label = turnoInfo.label;
    var php = res[1] || {};
    rv.personal = php.success ? php.personal : null;
    rv.incidenciasMod = php.success ? (php.incidencias || []) : [];
    rv.generado = php.success ? php.generado : null;
    return rv;
  }

  async function cargarJornadas() {
    try {
      var r = await fetch('../includes/tallyman_turno.php?action=jornadas', { cache: 'no-store' });
      var d = await r.json();
      jornadas = (d.success && d.data) ? d.data : [];
      var sel = $('rvJornadaFiltro');
      if (!sel || !jornadas.length) return;
      sel.innerHTML = jornadas.map(function (j) {
        return '<option value="' + OP.esc(j.codigo) + '">' + OP.esc(j.nombre) + '</option>';
      }).join('');
    } catch (e) { /* silencioso */ }
  }

  async function aplicarFiltro() {
    var fecha = $('rvFechaFiltro').value;
    var jornada = $('rvJornadaFiltro').value;
    if (!fecha || !jornada) return;
    var btn = $('rvGenerar'); if (btn) btn.disabled = true;
    try {
      await cargar(fecha, jornada);
      pintarMeta(); pintarMuelles(); pintarPatio(); pintarPersonal(); pintarInc(); pintarGenInfo();
      actualizarModoLectura();
      // En turno anterior el botón genera solo PDF (sin guardar)
      if (btn) btn.disabled = false;
    } catch (e) { OP.toast('Error al cambiar el filtro: ' + e.message, 'error'); if (btn) btn.disabled = false; }
  }

  // ───────── preview en pantalla ─────────
  function pintarMeta() {
    var m = [];
    m.push('Fecha: <b>' + OP.esc(rv.fecha) + '</b>');
    m.push('Turno: <b>' + OP.esc(rv._label || rv.turno) + '</b>');
    if (rv.coord_saliente) m.push('Saliente: <b>' + OP.esc(rv.coord_saliente) + '</b>');
    if (rv.coord_entrante) m.push('Entrante: <b>' + OP.esc(rv.coord_entrante) + '</b>');
    $('rvMeta').innerHTML = m.join('');
  }

  function pintarGenInfo() {
    $('rvObs').value = '';
    var obsPanel = $('rvObsDisplay'), obsTxt = $('rvObsTxt'), obsMeta = $('rvObsMeta');
    if (!rv.generado) {
      $('rvGenInfo').textContent = 'Aún no generado en este turno.';
      if (obsPanel) obsPanel.style.display = 'none';
      return;
    }
    $('rvGenInfo').innerHTML = 'Último: <b>' + OP.esc(rv.generado.generado_por) + '</b> · ' + OP.esc(rv.generado.generado_en);
    if (rv.generado.observaciones) {
      $('rvObs').value = rv.generado.observaciones;
      if (obsPanel && obsTxt && obsMeta) {
        obsTxt.textContent = rv.generado.observaciones;
        obsMeta.textContent = 'Por ' + rv.generado.generado_por + ' · ' + rv.generado.generado_en;
        obsPanel.style.display = '';
      }
    } else {
      if (obsPanel) obsPanel.style.display = 'none';
    }
  }

  function pintarMuelles() {
    var regs = muelles();
    if (!regs.length) { $('rvMuelles').innerHTML = '<div class="rv-empty">Sin avance de muelle con meta en este turno.</div>'; return; }
    $('rvMuelles').innerHTML = regs.map(function (r) {
      var total = num(r.planned), acum = num(r.accumulated), exec = num(r.executed);
      var previo = Math.max(acum - exec, 0), pend = Math.max(total - acum, 0);
      var pct = r.porcentaje;
      var inner;
      if (total > 0) {
        var pp = previo / total * 100, pe = exec / total * 100, pn = pend / total * 100;
        var segs = '';
        if (pp > 0) segs += '<span style="width:' + pp + '%;background:#274a6d">' + fmt(previo) + '</span>';
        if (pe > 0) segs += '<span style="width:' + pe + '%;background:#5f86a8">+' + fmt(exec) + '</span>';
        segs += '<span class="pend" style="width:' + pn + '%;background:transparent">' + fmt(pend) + '</span>';
        inner = '<div class="rvseg">' + segs + '</div>' +
          '<div class="cap"><span class="a">Avance ' + fmt(acum) + '</span><span class="tt">Total ' + fmt(total) + '</span></div>';
      } else {
        inner = '<div class="rv-prog-wrap"><div class="rv-prog-noplan">Sin plan · Turno: <b>' + fmt(exec) + '</b> · Acum: <b>' + fmt(acum) + '</b></div></div>';
      }
      return '<div class="rvm">' +
        '<div class="h"><span class="nm">' + OP.esc(r.ubicacion) + (r.nave ? '<small>' + OP.esc(r.nave) + '</small>' : '') + '</span>' +
        '<span class="pc' + (pct != null && pct < 50 ? ' low' : '') + '">' + (pct == null ? '—' : pct + '%') + '</span></div>' +
        inner +
        (r.details ? '<div class="rvm-obs">' + OP.esc(r.details) + '</div>' : '') +
        '</div>';
    }).join('');
  }

  function chipStatus(s) {
    var bg = s === 'Culminado' ? '#dcfce7' : s === 'En Proceso' ? '#fef9c3' : '#eff6ff';
    var cl = s === 'Culminado' ? '#15803d' : s === 'En Proceso' ? '#a16207' : '#1d4ed8';
    return '<span style="display:inline-block;padding:2px 7px;border-radius:999px;font-size:10px;font-weight:700;background:' + bg + ';color:' + cl + '">' + OP.esc(s || '—') + '</span>';
  }
  function miniBarPatio(accumulated, planned, pend, pctRaw) {
    var acc  = Number(accumulated || 0);
    var plan = planned != null ? Number(planned) : null;
    var pendVal = pend != null ? Number(pend)
                : (plan != null ? Math.max(plan - acc, 0) : null);
    var pctNum  = pctRaw != null ? Number(pctRaw)
                : (plan != null && plan > 0 ? Math.round((acc / plan) * 100) : null);

    if (plan == null || plan <= 0) {
      // Sin planificado: sólo muestra acumulado sin barra.
      return '<div class="rv-prog-wrap">' +
        '<div class="rv-prog-noplan">Sin plan · Acum: <b>' + fmt(acc) + '</b></div>' +
        '</div>';
    }

    var fillPct  = Math.min(Math.max(pctNum != null ? pctNum : 0, 0), 100);
    var fillCls  = fillPct >= 100 ? 'rv-prog-fill done' : (fillPct >= 75 ? 'rv-prog-fill good' : 'rv-prog-fill');

    return '<div class="rv-prog-wrap">' +
      '<div class="rv-prog-header">' +
        '<span class="rv-prog-pct">' + (pctNum != null ? pctNum + '%' : '—') + '</span>' +
        '<span class="rv-prog-meta">Plan: ' + fmt(plan) + '</span>' +
      '</div>' +
      '<div class="rv-prog-bar">' +
        '<div class="' + fillCls + '" style="width:' + fillPct + '%"></div>' +
      '</div>' +
      '<div class="rv-prog-nums">' +
        '<span>Acum: <b>' + fmt(acc) + '</b></span>' +
        '<span>Pend: <b style="color:' + (pendVal > 0 ? '#e57c00' : '#10b981') + '">' + fmt(pendVal) + '</b></span>' +
      '</div>' +
    '</div>';
  }

  function pintarPatio() {
    var regs = patios();
    if (!regs.length) { $('rvPatio').innerHTML = '<div class="rv-empty">Sin actividad de patio en este turno.</div>'; return; }
    var body = regs.map(function (r) {
      var naveLabel = r.nave || r.nave_patio || '—';
      var pend = r.pending != null ? r.pending
               : (r.planned != null ? Math.max(Number(r.planned) - Number(r.accumulated || 0), 0) : null);
      var main = '<tr>' +
        '<td>' + OP.esc(naveLabel) + '</td>' +
        '<td>' + OP.esc(r.actividad || '—') + '</td>' +
        '<td class="c">' + chipStatus(r.status_act) + '</td>' +
        '<td class="num">' + fmt(r.executed) + '</td>' +
        '<td class="rv-prog-td">' + miniBarPatio(r.accumulated, r.planned, pend, r.porcentaje) + '</td>' +
        '</tr>';
      var obs = r.details ? '<tr><td colspan="5" style="font-size:11px;color:#6b7a8d;font-style:italic;padding:2px 9px 8px 9px">' + OP.esc(r.details) + '</td></tr>' : '';
      return main + obs;
    }).join('');
    $('rvPatio').innerHTML = '<table class="rv-table"><thead><tr>' +
      '<th>Nave (Patio)</th><th>Actividad</th><th class="c">Status</th>' +
      '<th class="num">Ejec. turno</th><th class="rv-prog-th">Progreso</th>' +
      '</tr></thead><tbody>' + body + '</tbody></table>';
  }

  function pintarPersonal() {
    var p = rv.personal;
    if (!p || !p.porUbicacion || (!p.porUbicacion.length && !p.totalPersonas)) {
      $('rvPersonal').innerHTML = '<div class="rv-empty">Sin personal cargado en el turno (módulo de turno).</div>'; return;
    }
    var rows = p.porUbicacion.map(function (u) {
      return '<tr><td>' + OP.esc(u.ubicacion) + '</td><td class="c"><span class="rv-tag per">' + u.personas + '</span></td>' +
        '<td class="c"><span class="rv-tag rad">' + u.radios + '</span></td></tr>';
    }).join('');
    rows += '<tr style="font-weight:800;background:#f1f4f8"><td>Total</td>' +
      '<td class="c"><span class="rv-tag per">' + p.totalPersonas + '</span></td>' +
      '<td class="c"><span class="rv-tag rad">' + p.totalRadios + '</span></td></tr>';
    if (p.coordinador && p.coordinador.existe) {
      rows += '<tr><td>Coordinador</td><td class="c">—</td><td class="c"><span class="rv-yesno">' +
        (p.coordinador.radio ? 'Con radio' : 'Sin radio') + '</span></td></tr>';
    }
    $('rvPersonal').innerHTML = '<table class="rv-table"><thead><tr><th>Ubicación</th><th class="c">Personas</th><th class="c">Radios</th></tr></thead><tbody>' + rows + '</tbody></table>';
  }

  function pintarInc() {
    var incs = rv.incidenciasMod || [];
    if (!incs.length) { $('rvInc').innerHTML = '<div class="rv-empty">Sin incidencias registradas para este turno.</div>'; return; }
    $('rvInc').innerHTML = incs.map(function (i) {
      return '<div class="rv-inc"><div class="ti">' + OP.esc(i.punto || '—') + '</div>' +
        '<div class="who">Personal: <b>' + OP.esc(i.colaborador || '—') + '</b>' + (i.cargo ? ' · ' + OP.esc(i.cargo) : '') +
        (i.zona ? '<span class="z"> · Zona: ' + OP.esc(i.zona) + '</span>' : '') + '</div>' +
        (i.detalle ? '<div class="dt">' + OP.esc(i.detalle) + '</div>' : '') + '</div>';
    }).join('');
  }

  // ───────── generar (guardar + PDF) ─────────
  async function generar() {
    var obs = $('rvObs').value.trim();
    var snapshot = { personal: rv.personal, totales: rv.totales, fecha: rv.fecha, turno: rv.turno };
    var btn = $('rvGenerar'); btn.disabled = true;
    try {
      var r = await fetch('../api/save_relevo.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ fecha: turnoInfo.fecha, turno: turnoInfo.turno, observaciones: obs, datos: snapshot })
      }).then(function (x) { return x.json(); });
      if (!r.success) throw new Error(r.error || 'No se pudo guardar el relevo');
      rv.generado = { generado_por: r.generado_por, generado_en: r.generado_en, observaciones: obs };
      pintarGenInfo();
      await exportarPDF(obs, r.generado_por, r.generado_en);
      OP.toast('Relevo generado · PDF descargado', 'success');
    } catch (e) { OP.toast(e.message, 'error'); }
    finally { btn.disabled = false; }
  }

  // ───────── PDF ejecutivo apaisado (tarjetas enmarcadas) ─────────
  async function exportarPDF(obs, genPor, genEn) {
    if (!window.jspdf || !window.jspdf.jsPDF) { OP.toast('jsPDF no cargó', 'error'); return; }
    var jsPDF = window.jspdf.jsPDF;
    var doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'landscape' });
    var W = doc.internal.pageSize.getWidth(), H = doc.internal.pageSize.getHeight(), M = 26;
    var NAVY = [0, 27, 58], NAVY2 = [0, 43, 92], STEEL = [37, 99, 235], STEELD = [0, 27, 58],
        TRACK = [233, 236, 241], MUTE = [107, 122, 141], INK = [27, 42, 64], LINE = [219, 234, 254],
        WARN = [156, 107, 46];
    var PAD = 12;

    // Fecha/hora de generación: la del servidor si viene; si se re-exporta un
    // relevo ya generado, la última guardada; en último caso, la hora local.
    function _fmtNow() {
      var d = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
      return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) +
             ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    }
    var genTs = genEn || (rv.generado && rv.generado.generado_en) || _fmtNow();

    // ── Cargar logo ──
    var logoDataUrl = null;
    try {
      var resp = await fetch('../img/logo.jpg');
      var blob = await resp.blob();
      logoDataUrl = await new Promise(function (res) {
        var fr = new FileReader(); fr.onload = function (e) { res(e.target.result); }; fr.readAsDataURL(blob);
      });
    } catch (e) { /* sin logo si no carga */ }

    // ── Cabecera ──
    var HDR = 64;
    doc.setFillColor.apply(doc, NAVY); doc.rect(0, 0, W, HDR, 'F');
    doc.setFillColor.apply(doc, STEEL); doc.rect(0, HDR, W, 3, 'F');

    var textX = M;
    if (logoDataUrl) {
      var logoSz = 44;
      doc.addImage(logoDataUrl, 'PNG', M, (HDR - logoSz) / 2, logoSz, logoSz);
      textX = M + logoSz + 12;
    }
    doc.setTextColor(159, 179, 201); doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5);
    doc.text('PORT TERMINAL · COSCO SHIPPING', textX, 18);
    // Fecha y hora de generación del reporte (esquina superior derecha).
    doc.setFont('helvetica', 'normal');
    doc.text('Generated: ' + genTs, W - M, 18, { align: 'right' });
    doc.setTextColor(255, 255, 255); doc.setFontSize(17); doc.text('Shift Handover Report', textX, 40);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor(220, 228, 236);
    var meta = 'Date: ' + rv.fecha + '     Shift: ' + (rv._label || rv.turno);
    if (rv.coord_saliente) meta += '     Outgoing: ' + rv.coord_saliente;
    if (rv.coord_entrante) meta += '     Incoming: ' + rv.coord_entrante;
    doc.text(meta, textX, 56);
    var userName = window.RELEVO_CTX ? window.RELEVO_CTX.userName : 'Usuario';
    doc.text('COORDINATOR: ' + userName, W - M, 56, { align: 'right' });

    // ── Helpers ──
    function cardTitle(x, top, w, txt) {
      doc.setFillColor.apply(doc, STEEL); doc.rect(x + PAD, top + 10, 3, 10, 'F');
      doc.setTextColor.apply(doc, NAVY); doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5);
      doc.text(txt.toUpperCase(), x + PAD + 7, top + 18);
      doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.6); doc.line(x + PAD, top + 23, x + w - PAD, top + 23);
      return top + 36;
    }
    function cardFrame(x, top, w, bottom) {
      doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.9);
      doc.roundedRect(x, top, w, bottom - top, 5, 5, 'S');
    }

    var usable = W - 2 * M, gap = 14;
    var muelleW = Math.round((usable - gap) * 0.42), patioW = usable - gap - muelleW;
    var muelleX = M, patioX = M + muelleW + gap;
    var rowTop = HDR + 16;

    // ── Berth Progress ──
    var my = cardTitle(muelleX, rowTop, muelleW, 'Berth Progress');
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7);
    var lx = muelleX + PAD;
    function leg(color, t) {
      doc.setFillColor.apply(doc, color); doc.rect(lx, my - 5.5, 7, 7, 'F');
      doc.setTextColor.apply(doc, MUTE); doc.text(t, lx + 10, my); lx += 10 + doc.getTextWidth(t) + 10;
    }
    var barW = muelleW - 2 * PAD;
    var mregs = muelles();
    var legTotAcum = 0, legTotShift = 0, legTotPend = 0;
    mregs.forEach(function (r) {
      var total = num(r.planned), acum = num(r.accumulated), exec = num(r.executed);
      legTotAcum += Math.max(acum - exec, 0);
      legTotShift += exec;
      legTotPend += total > 0 ? Math.max(total - acum, 0) : 0;
    });
    leg(NAVY2, 'Accumulated: ' + fmt(legTotAcum));
    leg(STEEL, 'In This Shift: ' + fmt(legTotShift));
    leg(TRACK, 'Pending: ' + fmt(legTotPend) + ' · bar = Total');
    my += 14;
    if (!mregs.length) { doc.setTextColor.apply(doc, MUTE); doc.setFontSize(8.5); doc.text('No berth progress in this shift.', muelleX + PAD, my); my += 14; }
    mregs.forEach(function (r) {
      var total = num(r.planned), acum = num(r.accumulated), exec = num(r.executed), previo = Math.max(acum - exec, 0);
      doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor.apply(doc, NAVY);
      doc.text(r.ubicacion + (r.nave ? '  ·  ' + r.nave : ''), muelleX + PAD, my);
      if (r.porcentaje != null) { doc.setTextColor.apply(doc, r.porcentaje < 50 ? WARN : STEELD); doc.text(r.porcentaje + '%', muelleX + muelleW - PAD, my, { align: 'right' }); }
      my += 7;
      if (total > 0) {
        var barH = 14, bx = muelleX + PAD, by = my;
        doc.setDrawColor.apply(doc, LINE); doc.setFillColor.apply(doc, TRACK); doc.rect(bx, by, barW, barH, 'FD');
        var pw = previo / total * barW, ew = exec / total * barW;
        var pnW = Math.max(barW - pw - ew, 0);
        var ppPct = Math.round(previo / total * 100);
        var pePct = Math.round(exec / total * 100);
        var pnPct = Math.round(Math.max(total - acum, 0) / total * 100);
        var barTy = by + barH / 2 + 2.5;
        doc.setFont('helvetica', 'bold'); doc.setFontSize(6.5);
        if (pw > 0) {
          doc.setFillColor.apply(doc, NAVY2); doc.rect(bx, by, pw, barH, 'F');
          if (pw > 16) { doc.setTextColor(255, 255, 255); doc.text(ppPct + '%', bx + pw / 2, barTy, { align: 'center' }); }
        }
        if (ew > 0) {
          doc.setFillColor.apply(doc, STEEL); doc.rect(bx + pw, by, ew, barH, 'F');
          if (ew > 16) { doc.setTextColor(255, 255, 255); doc.text(pePct + '%', bx + pw + ew / 2, barTy, { align: 'center' }); }
        }
        if (pnW > 16 && pnPct > 0) { doc.setTextColor.apply(doc, MUTE); doc.text(pnPct + '%', bx + pw + ew + pnW / 2, barTy, { align: 'center' }); }
        my += barH + 11;
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor.apply(doc, STEELD);
        doc.text('Progress ' + fmt(acum), muelleX + PAD, my);
        doc.setTextColor.apply(doc, MUTE); doc.text('Total ' + fmt(total), muelleX + muelleW - PAD, my, { align: 'right' });
        my += 11;
      } else {
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor.apply(doc, MUTE);
        doc.text('Sin plan  ·  Turno: ' + fmt(exec) + '  ·  Acum: ' + fmt(acum), muelleX + PAD, my);
        my += 11;
      }
      if (r.details) {
        doc.setFont('helvetica', 'italic'); doc.setFontSize(7); doc.setTextColor.apply(doc, MUTE);
        var dl = doc.splitTextToSize('Note: ' + r.details, barW);
        doc.text(dl, muelleX + PAD, my); my += dl.length * 9.5;
      }
      my += 8;
    });
    var muelleEnd = my;

    // ── Yard Activity ──
    var py = cardTitle(patioX, rowTop, patioW, 'Yard Activity · Cargo');
    var pregs = patios();
    var patioBody = [];
    pregs.forEach(function (r) {
      var pend = r.pending != null ? r.pending
               : (r.planned != null ? Math.max(num(r.planned) - num(r.accumulated), 0) : null);
      patioBody.push([r.nave || r.nave_patio || '—', r.actividad || '—', r.status_act || '—',
        r.executed != null ? fmt(r.executed) : '—', r.accumulated != null ? fmt(r.accumulated) : '—',
        r.planned != null ? fmt(r.planned) : '—', pend != null ? fmt(pend) : '—',
        r.porcentaje != null ? r.porcentaje + '%' : '—']);
      if (r.details) patioBody.push([{ content: 'Obs: ' + r.details, colSpan: 8, styles: { fontStyle: 'italic', textColor: MUTE, fontSize: 6.5, cellPadding: { top: 2, bottom: 4, left: 10, right: 4 } } }]);
    });
    if (!patioBody.length) patioBody = [['No yard activity in this shift', '', '', '', '', '', '', '']];
    // Escala la tabla según cantidad de filas para mantener todo en una hoja.
    var yfs = patioBody.length > 16 ? 6 : patioBody.length > 10 ? 6.7 : 7.5;
    var ypad = patioBody.length > 16 ? 2 : patioBody.length > 10 ? 2.6 : 3.5;
    doc.autoTable({
      startY: py - 4, margin: { left: patioX + PAD }, tableWidth: patioW - 2 * PAD,
      head: [['Vessel (Yard)', 'Activity', 'Status', 'Shift Exec.', 'Accumulated', 'Planned', 'Pending', '%']],
      body: patioBody, theme: 'striped', rowPageBreak: 'avoid',
      styles: { fontSize: yfs, cellPadding: ypad, overflow: 'linebreak', textColor: INK, lineColor: LINE },
      headStyles: { fillColor: NAVY, textColor: 255, fontSize: yfs, halign: 'left' },
      alternateRowStyles: { fillColor: [248, 250, 251] },
      columnStyles: { 3: { halign: 'right' }, 4: { halign: 'right' }, 5: { halign: 'right' }, 6: { halign: 'right' }, 7: { halign: 'center', fontStyle: 'bold', textColor: NAVY } }
    });
    var patioEnd = doc.lastAutoTable.finalY;

    var topBottom = Math.max(muelleEnd, patioEnd) + PAD;
    cardFrame(muelleX, rowTop, muelleW, topBottom);
    cardFrame(patioX, rowTop, patioW, topBottom);

    // ── Fila inferior: Personal (40%) | Observaciones (25%) | Incidencias (35%) ──
    var row2Top = topBottom + 14;
    var perW = Math.round(usable * 0.38), obsW = Math.round(usable * 0.24), incW2 = usable - perW - obsW - 2 * gap;
    var perX = M, obsX2 = M + perW + gap, incX2 = M + perW + gap + obsW + gap;

    // Personnel & Radios
    var qy = cardTitle(perX, row2Top, perW, 'Personnel & Radios');
    var per = rv.personal, perBody = [];
    if (per && per.porUbicacion) {
      per.porUbicacion.forEach(function (u) { perBody.push([u.ubicacion, String(u.personas), String(u.radios)]); });
      perBody.push([{ content: 'Total', styles: { fontStyle: 'bold' } }, { content: String(per.totalPersonas), styles: { fontStyle: 'bold', halign: 'center' } }, { content: String(per.totalRadios), styles: { fontStyle: 'bold', halign: 'center' } }]);
      if (per.coordinador && per.coordinador.existe) perBody.push(['Coordinator', '—', per.coordinador.radio ? 'With radio' : 'No radio']);
    }
    if (!perBody.length) perBody = [['No staff loaded', '—', '—']];
    var pfs = perBody.length > 18 ? 6 : perBody.length > 12 ? 6.7 : 7.5;
    var ppad = perBody.length > 18 ? 2 : perBody.length > 12 ? 2.6 : 3.5;
    doc.autoTable({
      startY: qy - 4, margin: { left: perX + PAD }, tableWidth: perW - 2 * PAD,
      head: [['Location', 'Pers.', 'Radios']], body: perBody, theme: 'striped', rowPageBreak: 'avoid',
      styles: { fontSize: pfs, cellPadding: ppad, textColor: INK, lineColor: LINE },
      headStyles: { fillColor: NAVY, textColor: 255, fontSize: pfs },
      alternateRowStyles: { fillColor: [248, 250, 251] },
      columnStyles: { 1: { halign: 'center' }, 2: { halign: 'center' } }
    });
    var perEnd = doc.lastAutoTable.finalY;

    // Shift Observations
    var oy = cardTitle(obsX2, row2Top, obsW, 'Shift Observations');
    doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor.apply(doc, INK);
    var obsTxtStr = (obs && obs.trim()) ? obs.trim() : 'No observations.';
    var obsLines = doc.splitTextToSize(obsTxtStr, obsW - 2 * PAD);
    doc.text(obsLines, obsX2 + PAD, oy, { lineHeightFactor: 1.6 });
    var obsEnd = oy + obsLines.length * 13;

    // Incidents
    var iy = cardTitle(incX2, row2Top, incW2, 'Incidents (Module)');
    var incs = rv.incidenciasMod || [];
    if (!incs.length) {
      doc.setFont('helvetica', 'italic'); doc.setFontSize(8); doc.setTextColor.apply(doc, MUTE);
      doc.text('No incidents registered for this shift.', incX2 + PAD, iy); iy += 14;
    } else {
      incs.forEach(function (i) {
        if (iy > H - 30) return;  // no paginar: se recorta para mantener una sola hoja
        doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5); doc.setTextColor.apply(doc, NAVY);
        doc.text(i.punto || '—', incX2 + PAD, iy); iy += 12;
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor.apply(doc, STEELD);
        var who = 'Staff: ' + (i.colaborador || '—') + (i.cargo ? ' · ' + i.cargo : '') + (i.zona ? ' · Zone: ' + i.zona : '');
        var wl = doc.splitTextToSize(who, incW2 - 2 * PAD); doc.text(wl, incX2 + PAD, iy); iy += wl.length * 11;
        if (i.detalle) {
          doc.setTextColor.apply(doc, MUTE);
          var dl = doc.splitTextToSize(i.detalle, incW2 - 2 * PAD); doc.text(dl, incX2 + PAD, iy); iy += dl.length * 11;
        }
        iy += 8;
      });
    }
    var incEnd = iy;

    var row2Bottom = Math.max(perEnd, obsEnd, incEnd) + PAD;
    // Estirar hasta cerca del pie si hay espacio
    var footReserve = 32;
    if (row2Bottom < H - footReserve - 10) row2Bottom = H - footReserve - 4;
    cardFrame(perX, row2Top, perW, row2Bottom);
    cardFrame(obsX2, row2Top, obsW, row2Bottom);
    cardFrame(incX2, row2Top, incW2, row2Bottom);

    // ── Pie (siempre en la página 1) ──
    doc.setPage(1);
    var footY = H - 12;
    doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.8); doc.line(M, footY - 10, W - M, footY - 10);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor.apply(doc, MUTE);
    var userName = window.RELEVO_CTX ? window.RELEVO_CTX.userName : 'Usuario';
    doc.text('Generated by: ' + (genPor || userName) + '  ·  ' + genTs, M, footY);
    doc.text('Confidential · Management', W - M, footY, { align: 'right' });

    // Garantía de una sola hoja: descartar páginas extra si algún bloque desbordó.
    while (doc.getNumberOfPages() > 1) doc.deletePage(doc.getNumberOfPages());

    doc.save('relevo_' + rv.fecha + '_' + rv.turno + '.pdf');
  }

  // ───────── init ─────────
  async function init() {
    await cargarJornadas();
    try { await cargar(); }
    catch (e) { $('rvMeta').textContent = 'No se pudo cargar el relevo — ¿servicio de Operaciones activo?'; OP.toast(e.message, 'error'); return; }

    // Fijar selector al turno actual
    turnoActual = { fecha: turnoInfo.fecha, turno: turnoInfo.turno };
    var fi = $('rvFechaFiltro'), js = $('rvJornadaFiltro');
    if (fi) fi.value = turnoInfo.fecha;
    if (js) js.value = turnoInfo.turno;

    pintarMeta(); pintarMuelles(); pintarPatio(); pintarPersonal(); pintarInc(); pintarGenInfo();
    actualizarModoLectura();
    $('rvGenerar').disabled = false;
    $('rvGenerar').addEventListener('click', async function () {
      if (!esVistaActual()) {
        await exportarPDF($('rvObs').value.trim(), '', '');
        return;
      }
      generar();
    });

    // Filtro
    var fi2 = $('rvFechaFiltro'), js2 = $('rvJornadaFiltro'), hoy = $('rvHoyBtn');
    if (fi2) fi2.addEventListener('change', aplicarFiltro);
    if (js2) js2.addEventListener('change', aplicarFiltro);
    if (hoy) hoy.addEventListener('click', function () {
      if (!turnoActual) return;
      var f = $('rvFechaFiltro'), j = $('rvJornadaFiltro');
      if (f) f.value = turnoActual.fecha;
      if (j) j.value = turnoActual.turno;
      aplicarFiltro();
    });
  }
  init();
})();
