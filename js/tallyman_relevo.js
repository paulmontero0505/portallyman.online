/* Relevo de turno · Reporte ejecutivo (rediseño apaisado).
   Cruza datos de la API Node (avance muelle + manifiesto de patio) y del PHP
   (personal/radios de turno_personal + incidencias del módulo). Permite
   capturar observaciones, "Generar" (guarda quién/cuándo) y exporta el PDF
   ejecutivo apaisado con jsPDF + autotable. Patrón window.OP. */
(function () {
  'use strict';
  var OP = window.OP, $ = OP.$;
  var rv = null, turnoInfo = null, turnoActual = null, jornadas = [];

  // ── Google Drive vía endpoint PHP propio ─────────────────────────────────
  // El navegador NO puede llamar a Apps Script directo (CORS). Mandamos el PDF
  // (base64) a ../api/relevo_drive.php, que reenvía a Apps Script por cURL del
  // lado del servidor — igual que la subida de incidencias.
  async function uploadToDrive(blob, filename) {
    try {
      var buf = await blob.arrayBuffer();
      var bytes = new Uint8Array(buf);
      var binary = '';
      for (var i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
      var base64 = btoa(binary);
      var subfolder = 'Relevo · ' + (turnoInfo ? turnoInfo.fecha + ' · ' + (turnoInfo.nombre || turnoInfo.turno) : filename);
      var r = await fetch('../api/relevo_drive.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ folder: subfolder, filename: filename, content: base64 })
      });
      var d = await r.json();
      if (d.ok) { OP.toast('PDF guardado en Google Drive ✓', 'success'); }
      else { OP.toast('Drive: ' + (d.error || 'error'), 'error'); }
    } catch (e) { OP.toast('Drive: ' + e.message, 'error'); }
  }

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
    if (fecha && jornada) {
      url += '?fecha=' + encodeURIComponent(fecha) + '&jornada=' + encodeURIComponent(jornada);
    } else {
      // Carga inicial: abrir en el turno elegido por el coordinador al ingresar
      // (el backend resuelve la fecha: turno noche en la mañana → día anterior).
      var sel = sessionStorage.getItem('tm_turno_codigo');
      if (sel) url += '?jornada=' + encodeURIComponent(sel);
    }
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
        if (pp > 0) segs += '<span style="width:' + pp + '%;background:#059669">' + fmt(previo) + '</span>';
        if (pe > 0) segs += '<span style="width:' + pe + '%;background:#10b981">+' + fmt(exec) + '</span>';
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
  function miniBarPatio(r) {
    var total  = r.planned != null ? Number(r.planned) : null;
    var acum   = Number(r.accumulated || 0);
    var exec   = Number(r.executed || 0);
    var previo = Math.max(acum - exec, 0);
    var pend   = total != null ? Math.max(total - acum, 0) : null;
    var pct    = r.porcentaje != null ? r.porcentaje
               : (total != null && total > 0 ? Math.min(Math.round(acum / total * 1000) / 10, 100) : null);

    if (total == null || total <= 0) {
      return '<div style="font-size:11px;color:#6b7a8d">Sin plan · Acum: <b>' + fmt(acum) + '</b></div>';
    }

    var pp = previo / total * 100, pe = exec / total * 100, pn = pend / total * 100;
    var segs = '';
    if (pp > 0) segs += '<span style="width:' + pp + '%;background:#059669;display:inline-flex;align-items:center;justify-content:center;height:100%;vertical-align:top;color:#fff;font-size:8px">' + (pp >= 10 ? fmt(previo) : '') + '</span>';
    if (pe > 0) segs += '<span style="width:' + pe + '%;background:#10b981;display:inline-flex;align-items:center;justify-content:center;height:100%;vertical-align:top;color:#fff;font-size:8px">' + (pe >= 10 ? fmt(exec) : '') + '</span>';
    if (pn > 0) segs += '<span style="width:' + pn + '%;background:#e2e8f0;display:inline-flex;align-items:center;justify-content:center;height:100%;vertical-align:top;color:#6b7a8d;font-size:8px">' + (pn >= 10 && pend > 0 ? fmt(pend) : '') + '</span>';

    var pctColor = pct != null && pct < 50 ? '#e57c00' : '#059669';
    return '<div style="display:flex;align-items:center;gap:6px">' +
      '<div style="flex:1;height:14px;border-radius:4px;overflow:hidden;border:1px solid #cbd5e1;font-size:0;min-width:70px">' + segs + '</div>' +
      '<span style="font-size:11px;font-weight:700;color:' + pctColor + ';white-space:nowrap;min-width:34px;text-align:right">' + (pct != null ? pct + '%' : '—') + '</span>' +
    '</div>' +
    '<div style="display:flex;justify-content:space-between;font-size:10px;color:#6b7a8d;margin-top:2px">' +
      '<span>Avance:<b style="margin-left:3px">' + fmt(acum) + '</b></span>' +
      '<span>Total:<b style="margin-left:3px">' + fmt(total) + '</b></span>' +
    '</div>';
  }

  function pintarPatio() {
    var regs = patios();
    if (!regs.length) { $('rvPatio').innerHTML = '<div class="rv-empty">Sin actividad de patio en este turno.</div>'; return; }
    var body = regs.map(function (r) {
      var naveLabel = r.nave || r.nave_patio || '—';
      var total = r.planned != null ? Number(r.planned) : 0;
      var acum = Number(r.accumulated || 0);
      var pend = total > 0 ? Math.max(total - acum, 0) : null;
      var main = '<tr>' +
        '<td>' + OP.esc(naveLabel) + '</td>' +
        '<td>' + OP.esc(r.actividad || '—') + '</td>' +
        '<td class="c">' + chipStatus(r.status_act) + '</td>' +
        '<td class="num" style="white-space:nowrap"><b>' + fmt(r.executed) + '</b></td>' +
        '<td class="num" style="white-space:nowrap;color:' + (pend > 0 ? '#e57c00' : '#10b981') + '"><b>' + fmt(pend || 0) + '</b></td>' +
        '<td class="rv-prog-td">' + miniBarPatio(r) + '</td>' +
        '</tr>';
      var obs = r.details ? '<tr><td colspan="6" style="font-size:11px;color:#6b7a8d;font-style:italic;padding:2px 9px 8px 9px">' + OP.esc(r.details) + '</td></tr>' : '';
      return main + obs;
    }).join('');
    $('rvPatio').innerHTML = '<table class="rv-table"><thead><tr>' +
      '<th>Nave (Patio)</th><th>Actividad</th><th class="c">Status</th>' +
      '<th class="num">Ejec. turno</th><th class="num">Pending</th><th class="rv-prog-th" style="text-align:right">Avance · Total · %</th>' +
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
    var html = '<table class="rv-table"><thead><tr><th>Ubicación</th><th class="c">Personas</th><th class="c">Radios</th></tr></thead><tbody>' + rows + '</tbody></table>';
    html += pintarChecklistHtml(p.checklist);
    $('rvPersonal').innerHTML = html;
  }

  function pintarChecklistHtml(checklist) {
    if (!checklist || !checklist.length) return '';
    var rows = [];
    checklist.forEach(function (per) {
      (per.items || []).forEach(function (it) {
        rows.push('<tr><td>' + OP.esc(per.nombre || '—') + '</td>' +
          '<td>' + OP.esc(per.ubicacion || '—') + '</td>' +
          '<td>' + OP.esc(it.item || '—') + '</td>' +
          '<td>' + OP.esc(it.comentario || '—') + '</td></tr>');
      });
    });
    return '<div style="margin-top:14px">' +
      '<div style="font-size:12px;font-weight:800;color:#1a503c;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px">Checklist tallyman</div>' +
      '<table class="rv-table"><thead><tr><th>Tallyman</th><th>Ubicación</th><th>Ítem</th><th>Comentario</th></tr></thead>' +
      '<tbody>' + rows.join('') + '</tbody></table></div>';
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
    var doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'portrait' });
    var W = doc.internal.pageSize.getWidth(), H = doc.internal.pageSize.getHeight(), M = 26;
    
    // Paleta fiel a la imagen
    var NAVY  = [26, 80, 60],      // Verde Oscuro: cabecera y tabla
        NAVY2 = [51, 65, 85],      // Slate 700: acumulado
        STEEL = [42, 175, 120],    // Verde brillante: turno actual
        STEELD= [30, 41, 59],      // Slate oscuro: texto
        TRACK = [226, 232, 240],   // Gris claro: barra fondo y conector
        MUTE  = [100, 116, 139],   // Gris texto
        INK   = [0, 0, 0],         // Negro texto
        LINE  = [226, 232, 240];   // Bordes
    var PAD = 14;

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
    var HDR = 68;
    doc.setFillColor.apply(doc, NAVY); doc.rect(0, 0, W, HDR, 'F');
    doc.setFillColor.apply(doc, STEEL); doc.rect(0, HDR, W, 4, 'F');

    var textX = M;
    if (logoDataUrl) {
      var logoSz = 48;
      var logoY = (HDR - logoSz) / 2;
      doc.setFillColor(255, 255, 255);
      doc.roundedRect(M, logoY, logoSz, logoSz, 3, 3, 'F');
      doc.addImage(logoDataUrl, 'PNG', M + 2, logoY + 2, logoSz - 4, logoSz - 4);
      textX = M + logoSz + 16;
    }
    doc.setTextColor(167, 243, 208); doc.setFont('helvetica', 'normal'); doc.setFontSize(8);
    doc.text('PORT TERMINAL - COSCO SHIPPING', textX, 24);
    
    doc.setTextColor(255, 255, 255); doc.setFontSize(18); doc.text('Shift Handover Report', textX, 46);
    
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(241, 245, 249);
    var rightTextX = W - M;
    doc.text('Shift: ' + (rv._label || rv.turno), rightTextX, 20, { align: 'right' });
    doc.text('Date: ' + rv.fecha, rightTextX, 32, { align: 'right' });
    doc.text('Generated: ' + genTs, rightTextX, 44, { align: 'right' });
    var userName = window.RELEVO_CTX ? window.RELEVO_CTX.userName : 'Usuario';
    doc.setFont('helvetica', 'bold');
    doc.text('COORDINATOR: ' + userName, rightTextX, 56, { align: 'right' });

    // ── Helpers ──
    function cardTitle(x, top, w, txt) {
      doc.setFillColor(250, 251, 252);
      doc.roundedRect(x, top, w, 26, 6, 6, 'F');
      doc.rect(x, top + 10, w, 16, 'F');
      
      doc.setFillColor.apply(doc, STEEL); 
      doc.rect(x + PAD, top + 8, 4, 12, 'F');
      
      doc.setTextColor(30, 41, 59); doc.setFont('helvetica', 'normal'); doc.setFontSize(10);
      doc.text(txt.toUpperCase(), x + PAD + 10, top + 18);
      
      doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.5); doc.line(x, top + 26, x + w, top + 26);
      return top + 34;
    }
    function cardFrame(x, top, w, bottom) {
      doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.5);
      doc.roundedRect(x, top, w, bottom - top, 6, 6, 'S');
    }

    var usable = W - 2 * M, gap = 16;
    var colW = Math.floor((usable - gap) / 2);
    var leftX = M, rightX = M + colW + gap;
    var rowTop = HDR + 20;

    function statusRGB(s) {
      return s === 'Culminado' ? [78, 153, 113] : s === 'En Proceso' ? [217, 165, 76] : [114, 156, 178];
    }
    function drawProgressItem(r, X, w, startY, nameStr, isLast) {
      var leftOffset = 42; 
      var bw = w - 2 * PAD - leftOffset;
      var total = num(r.planned), acum = num(r.accumulated), exec = num(r.executed), previo = Math.max(acum - exec, 0);
      var y = startY;

      var stStr = r.status_act || '';
      var circleColor = statusRGB(stStr);
      
      var circleX = X + PAD + 18;
      var circleY = y + 14;
      var contentY = y;

      var pctStr = (r.porcentaje != null) ? (r.porcentaje + '%') : '0%';

      doc.setFontSize(6);
      var stW = stStr ? doc.getTextWidth(stStr) + 8 : 0; // Text width + 8px padding
      var rightLimit = X + w - PAD - stW - 6; 
      
      doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor.apply(doc, INK);
      var nameMax = rightLimit - (X + PAD + leftOffset);
      var nameDraw = nameStr;
      if (nameMax > 0 && doc.getTextWidth(nameDraw) > nameMax) {
        while (nameDraw.length > 1 && doc.getTextWidth(nameDraw + '…') > nameMax) nameDraw = nameDraw.slice(0, -1);
        nameDraw += '…';
      }
      doc.text(nameDraw, X + PAD + leftOffset, contentY + 7);
      
      if (stStr) {
        doc.setFillColor.apply(doc, circleColor);
        var chipX = X + w - PAD - stW;
        doc.roundedRect(chipX, contentY - 1, stW, 11, 3, 3, 'F');
        doc.setFont('helvetica', 'bold'); doc.setFontSize(6); doc.setTextColor(255, 255, 255);
        doc.text(stStr, chipX + stW / 2, contentY + 7.5, { align: 'center' });
      }

      contentY += 16;

      if (total > 0) {
        doc.setFont('helvetica', 'bold'); doc.setFontSize(6.5);
        var valX = X + PAD + leftOffset;
        var dotR = 2.5;

        var pwText = fmt(previo), ewText = '+' + fmt(exec), pnText = fmt(Math.max(total - acum, 0));

        if (previo > 0) {
            doc.setFillColor.apply(doc, NAVY2);
            doc.circle(valX + dotR, contentY - 2.5, dotR, 'F');
            doc.setTextColor.apply(doc, NAVY2);
            doc.text(pwText, valX + dotR * 2 + 3, contentY + 0.5);
            valX += doc.getTextWidth(pwText) + dotR * 2 + 12;
        }

        if (exec > 0) {
            doc.setFillColor.apply(doc, STEEL);
            doc.circle(valX + dotR, contentY - 2.5, dotR, 'F');
            doc.setTextColor.apply(doc, STEEL);
            doc.text(ewText, valX + dotR * 2 + 3, contentY + 0.5);
            valX += doc.getTextWidth(ewText) + dotR * 2 + 12;
        }

        var pendVal = Math.max(total - acum, 0);
        if (pendVal > 0) {
            doc.setFillColor(203, 213, 225); // Slate 300 for visibility
            doc.circle(valX + dotR, contentY - 2.5, dotR, 'F');
            doc.setTextColor.apply(doc, MUTE);
            doc.text(pnText, valX + dotR * 2 + 3, contentY + 0.5);
        }
        
        contentY += 6;
      }

      if (total > 0) {
        var barH = 7, bx = X + PAD + leftOffset, by = contentY;
        doc.setFillColor.apply(doc, TRACK); doc.rect(bx, by, bw, barH, 'F');
        
        var pw = previo / total * bw, ew = exec / total * bw;
        if (pw > bw) pw = bw;
        if (pw + ew > bw) ew = Math.max(bw - pw, 0);

        if (pw > 0) {
          doc.setFillColor.apply(doc, NAVY2); doc.rect(bx, by, pw, barH, 'F');
        }
        if (ew > 0) {
          doc.setFillColor.apply(doc, STEEL); doc.rect(bx + pw, by, ew, barH, 'F');
        }
        
        contentY += barH + 8;
      } else {
        doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor.apply(doc, MUTE);
        doc.text('Sin plan  ·  Turno: ' + fmt(exec) + '  ·  Acum: ' + fmt(acum), X + PAD + leftOffset, contentY + 2);
        contentY += 12;
      }

      if (r.actividad) {
        doc.setFont('helvetica', 'italic'); doc.setFontSize(7.5); doc.setTextColor.apply(doc, INK);
        var actC = doc.splitTextToSize(r.actividad, bw);
        doc.text(actC, X + PAD + leftOffset + bw / 2, contentY, { align: 'center' });
        contentY += actC.length * 9 + 2;
      }

      if (total > 0) {
        doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor.apply(doc, INK);
        doc.text('Avance ' + fmt(acum), X + PAD + leftOffset, contentY);
        doc.text((r.ubicacion_tipo === 'BERTH' ? 'of Total ' : 'Total ') + fmt(total), X + w - PAD, contentY, { align: 'right' });
        contentY += 10;
      }

      if (r.details) {
        doc.setFont('helvetica', 'italic'); doc.setFontSize(7.5); doc.setTextColor.apply(doc, MUTE);
        var dl = doc.splitTextToSize('Note: ' + r.details, bw);
        doc.text(dl, X + PAD + leftOffset, contentY); contentY += dl.length * 10;
      }

      var endY = contentY + 6;

      if (!isLast) {
        doc.setDrawColor.apply(doc, TRACK); 
        doc.setLineWidth(2);
        doc.line(circleX, circleY + 16, circleX, endY + 14);
      }

      doc.setFillColor(255, 255, 255);
      doc.setDrawColor.apply(doc, circleColor);
      doc.setLineWidth(2.2);
      doc.circle(circleX, circleY, 13, 'FD');
      doc.setFont('helvetica', 'bold'); doc.setFontSize(5.5); doc.setTextColor(0, 0, 0);
      doc.text(pctStr, circleX, circleY + 2.5, { align: 'center' });

      return endY;
    }

    var my = cardTitle(leftX, rowTop, colW, 'Berth Progress');
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7);
    var lx = leftX + PAD + 42;
    function leg(color, t) {
      doc.setFillColor.apply(doc, color); doc.rect(lx, my - 5.5, 8, 8, 'F');
      doc.setTextColor.apply(doc, STEELD); doc.text(t, lx + 12, my + 1); lx += 12 + doc.getTextWidth(t) + 12;
    }
    var mregs = muelles();
    leg(NAVY2, 'Accumulated'); leg(STEEL, 'In This Shift'); leg(TRACK, 'Pending');
    my += 16;
    if (!mregs.length) { doc.setFont('helvetica', 'italic'); doc.setTextColor.apply(doc, MUTE); doc.setFontSize(8.5); doc.text('No berth progress in this shift.', leftX + PAD, my); my += 16; }
    for (var bi = 0; bi < mregs.length; bi++) {
      if (my > H - 70) { doc.setFont('helvetica', 'italic'); doc.setFontSize(8); doc.setTextColor.apply(doc, MUTE); doc.text('+ ' + (mregs.length - bi) + ' más (ver sistema)', leftX + PAD, my); my += 12; break; }
      var rb = mregs[bi];
      my = drawProgressItem(rb, leftX, colW, my, rb.ubicacion + (rb.nave ? ' · ' + rb.nave : ''), bi === mregs.length - 1);
    }
    var berthEnd = my;

    var py = cardTitle(rightX, rowTop, colW, 'Yard Activity · Cargo');
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7);
    var lx2 = rightX + PAD + 42;
    function leg2(color, t) {
      doc.setFillColor.apply(doc, color); doc.rect(lx2, py - 5.5, 8, 8, 'F');
      doc.setTextColor.apply(doc, STEELD); doc.text(t, lx2 + 12, py + 1); lx2 += 12 + doc.getTextWidth(t) + 12;
    }
    leg2(NAVY2, 'Accumulated'); leg2(STEEL, 'In This Shift'); leg2(TRACK, 'Pending');
    py += 16;
    var pregs = patios();
    if (!pregs.length) { doc.setFont('helvetica', 'italic'); doc.setFontSize(8.5); doc.setTextColor.apply(doc, MUTE); doc.text('No yard activity in this shift.', rightX + PAD, py); py += 16; }
    for (var yi = 0; yi < pregs.length; yi++) {
      if (py > H - 70) { doc.setFont('helvetica', 'italic'); doc.setFontSize(8); doc.setTextColor.apply(doc, MUTE); doc.text('+ ' + (pregs.length - yi) + ' más (ver sistema)', rightX + PAD, py); py += 12; break; }
      var ry = pregs[yi];
      py = drawProgressItem(ry, rightX, colW, py, ry.nave || ry.nave_patio || '—', yi === pregs.length - 1);
    }
    var yardEnd = py;

    var berthBottom = berthEnd + PAD, yardBottom = yardEnd + PAD;
    cardFrame(leftX, rowTop, colW, berthBottom); cardFrame(rightX, rowTop, colW, yardBottom);
    var patioBottom = Math.max(berthBottom, yardBottom);

    var blockTop = patioBottom + 16;
    if (blockTop + 160 > H - 24) { doc.addPage(); blockTop = 40; }

    var per = rv.personal, perBody = [];
    if (per && per.porUbicacion) {
      per.porUbicacion.forEach(function (u) { perBody.push([u.ubicacion, String(u.personas), String(u.radios)]); });
      perBody.push([{ content: 'Total', styles: { fontStyle: 'bold' } }, { content: String(per.totalPersonas), styles: { fontStyle: 'bold', halign: 'center' } }, { content: String(per.totalRadios), styles: { fontStyle: 'bold', halign: 'center' } }]);
      if (per.coordinador && per.coordinador.existe) perBody.push(['Coordinator', '—', per.coordinador.radio ? 'With radio' : 'No radio']);
    }
    if (!perBody.length) perBody = [['No staff loaded', '—', '—']];
    var qy = cardTitle(leftX, blockTop, colW, 'Personnel & Radios');
    var pfs = perBody.length > 14 ? 8 : 9;
    doc.autoTable({
      startY: qy - 4, margin: { left: leftX + PAD }, tableWidth: colW - 2 * PAD,
      head: [['Location', 'Pers.', 'Radios']], body: perBody, theme: 'striped', rowPageBreak: 'avoid',
      styles: { fontSize: pfs, cellPadding: 4, textColor: INK, lineColor: LINE },
      headStyles: { fillColor: NAVY, textColor: 255, fontSize: pfs, fontStyle: 'bold' },
      alternateRowStyles: { fillColor: [250, 251, 252] },
      columnStyles: { 1: { halign: 'center' }, 2: { halign: 'center' } }
    });
    var perBottom = doc.lastAutoTable.finalY + PAD + 4;

    var iy = cardTitle(rightX, blockTop, colW, 'Incidents (Module)');
    var incs = rv.incidenciasMod || [];
    if (!incs.length) {
      doc.setFont('helvetica', 'italic'); doc.setFontSize(9); doc.setTextColor.apply(doc, MUTE);
      doc.text('No incidents registered for this shift.', rightX + PAD, iy); iy += 16;
    } else {
      for (var ii = 0; ii < incs.length; ii++) {
        var inc = incs[ii];
        if (iy > H - 46) { doc.setFont('helvetica', 'italic'); doc.setFontSize(8); doc.setTextColor.apply(doc, MUTE); doc.text('+ ' + (incs.length - ii) + ' más (ver sistema)', rightX + PAD, iy); iy += 12; break; }
        doc.setFont('helvetica', 'bold'); doc.setFontSize(9); doc.setTextColor.apply(doc, NAVY);
        var pl = doc.splitTextToSize(inc.punto || '—', colW - 2 * PAD); doc.text(pl, rightX + PAD, iy); iy += pl.length * 12;
        doc.setFont('helvetica', 'normal'); doc.setFontSize(8.5); doc.setTextColor.apply(doc, STEELD);
        var who = 'Staff: ' + (inc.colaborador || '—') + (inc.cargo ? ' · ' + inc.cargo : '') + (inc.zona ? ' · Zone: ' + inc.zona : '');
        var wl = doc.splitTextToSize(who, colW - 2 * PAD); doc.text(wl, rightX + PAD, iy); iy += wl.length * 12;
        if (inc.detalle) { doc.setTextColor.apply(doc, MUTE); doc.setFontSize(8); var dl = doc.splitTextToSize(inc.detalle, colW - 2 * PAD); doc.text(dl, rightX + PAD, iy); iy += dl.length * 11; }
        iy += 10;
      }
    }
    var incBottom = iy + PAD - 10;
    cardFrame(rightX, blockTop, colW, incBottom);

    var obsTop = incBottom + 16;
    var oy = cardTitle(rightX, obsTop, colW, 'Shift Observations');
    var obsTxtStr = (obs && obs.trim()) ? obs.trim() : 'No observations.';
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor.apply(doc, INK);
    var obsLines = doc.splitTextToSize(obsTxtStr, colW - 2 * PAD);
    doc.text(obsLines, rightX + PAD, oy, { lineHeightFactor: 1.5 });
    var obsBottom = oy + obsLines.length * 14 + PAD;

    cardFrame(leftX, blockTop, colW, perBottom);
    cardFrame(rightX, obsTop, colW, obsBottom);

    var fullW = (rightX + colW) - leftX;
    var checklist = (per && per.checklist) || [];
    if (checklist.length) {
      var sectionBottom = Math.max(perBottom, obsBottom);
      var clTop = sectionBottom + 16;
      if (clTop + 90 > H - 24) { doc.addPage(); clTop = 40; }
      var cy = cardTitle(leftX, clTop, fullW, 'Checklist Tallyman');
      var clBody = [];
      checklist.forEach(function (per_) {
        (per_.items || []).forEach(function (it) {
          clBody.push([per_.nombre || '—', per_.ubicacion || '—', it.item || '—', it.comentario || '—']);
        });
      });
      doc.autoTable({
        startY: cy - 4, margin: { left: leftX + PAD }, tableWidth: fullW - 2 * PAD,
        head: [['Tallyman', 'Ubicación', 'Ítem', 'Comentario']], body: clBody, theme: 'striped', rowPageBreak: 'avoid',
        styles: { fontSize: 8.5, cellPadding: 4, textColor: INK, lineColor: LINE },
        headStyles: { fillColor: NAVY, textColor: 255, fontSize: 8.5, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [250, 251, 252] }
      });
      var clBottom = doc.lastAutoTable.finalY + PAD + 4;
      cardFrame(leftX, clTop, fullW, clBottom);
    }

    var totalPages = doc.getNumberOfPages();
    for (var pp = 1; pp <= totalPages; pp++) {
      doc.setPage(pp);
      var footY = H - 16;
      doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.5); doc.line(M, footY - 12, W - M, footY - 12);
      if (totalPages > 1) { doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor.apply(doc, MUTE); doc.text(pp + ' / ' + totalPages, W - M, footY, { align: 'right' }); }
    }

    var dn = new Date();
    var pad = function(n) { return String(n).padStart(2, '0'); };
    var strFecha = dn.getFullYear() + '-' + pad(dn.getMonth() + 1) + '-' + pad(dn.getDate());
    var strHora = pad(dn.getHours()) + '-' + pad(dn.getMinutes()) + '-' + pad(dn.getSeconds());
    var pdfName = 'HANDOVER_' + strFecha + '_' + strHora + '.pdf';

    if (window.RELEVO_PREVIEW) {
      var f = document.getElementById('rvPreviewFrame');
      if (f) f.src = doc.output('bloburl');
      return;
    }

    doc.save(pdfName);
    uploadToDrive(doc.output('blob'), pdfName);
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
