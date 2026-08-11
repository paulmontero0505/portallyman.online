/* ══════════════════════════════════════════════════════════════════════
   OPERACIONES · helpers compartidos del front (Fase 3)
   Expone window.OP con: $, esc, opApi, toast, formato de fechas y chips.
   Todas las llamadas a la API Node pasan por el proxy PHP (same-origin),
   que inyecta el rol desde la sesión. Nunca se llama a :4000 directo.
   ══════════════════════════════════════════════════════════════════════ */
(function (global) {
  'use strict';

  // El proxy vive en /api/ ; las páginas están en /pages/ → ruta relativa.
  var PROXY = '../api/operaciones_proxy.php';

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // Llama a la API de Operaciones vía proxy. Devuelve el JSON {success,data,...}
  // o lanza Error con el mensaje del backend.
  async function opApi(path, opts) {
    opts = opts || {};
    var url = PROXY + '?path=' + encodeURIComponent(path);
    if (opts.query) {
      for (var k in opts.query) {
        var v = opts.query[k];
        if (v != null && v !== '') url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(v);
      }
    }
    var fetchOpts = { method: opts.method || 'GET', cache: 'no-store', headers: {} };
    if (opts.body != null) {
      fetchOpts.headers['Content-Type'] = 'application/json';
      fetchOpts.body = JSON.stringify(opts.body);
    }
    var res, data;
    try {
      res = await fetch(url, fetchOpts);
    } catch (e) {
      throw new Error('Error de red al contactar el servidor.');
    }
    try {
      data = await res.json();
    } catch (e) {
      throw new Error('Respuesta inválida del servidor (HTTP ' + res.status + ').');
    }
    if (!res.ok || !data || data.success === false) {
      throw new Error((data && data.error) ? data.error : ('Error HTTP ' + res.status));
    }
    return data;
  }

  // ── Toast ──
  var toastWrap = null;
  function toast(msg, type) {
    if (!toastWrap) {
      toastWrap = document.createElement('div');
      toastWrap.className = 'op-toast-wrap';
      document.body.appendChild(toastWrap);
    }
    var t = document.createElement('div');
    t.className = 'op-toast' + (type === 'error' ? ' error' : type === 'success' ? ' success' : '');
    t.textContent = msg;
    toastWrap.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('show'); });
    setTimeout(function () {
      t.classList.remove('show');
      setTimeout(function () { t.remove(); }, 250);
    }, type === 'error' ? 4200 : 2600);
  }

  // ── Fechas (la API entrega DATETIME como 'YYYY-MM-DD HH:MM:SS') ──
  function _parts(s) {
    var m = String(s == null ? '' : s).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m; // [full, Y, M, D, h, m] o null
  }
  function fmtDateTime(s) {            // 'DD/MM HH:MM'
    var m = _parts(s);
    if (!m) return s ? esc(s) : '—';
    return m[3] + '/' + m[2] + ' ' + m[4] + ':' + m[5];
  }
  function fmtDateTimeFull(s) {        // 'DD/MM/YYYY HH:MM'
    var m = _parts(s);
    if (!m) return s ? esc(s) : '—';
    return m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5];
  }
  function toLocalInput(s) {           // value de <input type=datetime-local>
    var m = _parts(s);
    return m ? (m[1] + '-' + m[2] + '-' + m[3] + 'T' + m[4] + ':' + m[5]) : '';
  }
  function fromLocalInput(v) {         // datetime-local -> 'YYYY-MM-DD HH:MM:00' | null
    if (!v) return null;
    var base = v.replace('T', ' ');
    return base.length === 16 ? base + ':00' : base;
  }

  // ── Chip de estado ──
  var ESTADO_META = {
    'Programada':     { c: 'c-prog',   t: 'Programada' },
    'En Puerto':      { c: 'c-puerto', t: 'En puerto' },
    'En Operaciones': { c: 'c-oper',   t: 'En operaciones' },
    'Finalizada':     { c: 'c-fin',    t: 'Finalizada' }
  };
  function estadoChip(estado) {
    var m = ESTADO_META[estado] || { c: 'c-prog', t: estado };
    return '<span class="op-chip ' + m.c + '"><span class="d"></span>' + esc(m.t) + '</span>';
  }

  global.OP = {
    $: $, esc: esc, opApi: opApi, toast: toast,
    fmtDateTime: fmtDateTime, fmtDateTimeFull: fmtDateTimeFull,
    toLocalInput: toLocalInput, fromLocalInput: fromLocalInput,
    estadoChip: estadoChip
  };
})(window);
