/* ════════════════════════════════════════════════════════════════════════
   ESTIBA · Shift Command Deck — Lógica del módulo (IIFE)
   ────────────────────────────────────────────────────────────────────────
   Único símbolo expuesto: window.EstibaModule = { boot, getSnapshot }.
   Lee datos desde window.__EstibaDataSource (definido en data-source.js).
   ════════════════════════════════════════════════════════════════════════ */

(function () {
  'use strict';

  // ── Estado interno (en memoria) ────────────────────────────────────────
  const state = {
    // Turno actual
    turnoId: null,
    turnoEstado: null,
    personal: [],
    funciones: [],
    ubicaciones: [],
    naves: [],                       // naves no finalizadas, para el selector de muelle
    turnoLabel: "",
    turnoIni: null, turnoFin: null,  // minutos del día (turnoFin normalizado: +1440 si cruza medianoche)
    turnoWraps: false,               // true si el turno cruza medianoche (Noche)
    jornada: null,                   // código de jornada (etiqueta)
    jornadaId: null,                 // id de jornada activa
    jornadas: [],                    // catálogo (activas) para el selector
    turnoFecha: null,
    motivos: [],                     // catálogo de motivos para observaciones/cambios
    acciones: [],                    // auditoría del turno (movimientos)
    puedeValidar: false,             // rol Supervisor/Administrador (validar + REABRIR)
    puedeCerrar: false,              // Admin/Supervisor/Coordinador (CERRAR turno)
    rol: null,
    filtro: "todos",
    query: "",
    agrupar: localStorage.getItem('est_agrupar') || 'none',
    ordenar: localStorage.getItem('est_ordenar') || 'nombre',
    vista:   localStorage.getItem('est_vista')   || 'grid',
    sideCollapsed: localStorage.getItem('est_side') === '1',
    selectedId: null,
    limites: { refrigerio: 30, permiso: 60 },
    notified: new Set()
  };
  const WARN_RATIO = 0.8;

  // ── Iconos por función (SVG paths) ─────────────────────────────────────
  const FUNC_ICONS = {
    "Winchero":     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    "Estibador":    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
    "Señalero":     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>',
    "Tractorista":  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
    "Capataz":      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
    "Lashing":      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
    "Apoyo Bodega": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2h6a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="15" y2="16"/></svg>',
    "Apoyo Muelle": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="3"/><line x1="12" y1="22" x2="12" y2="8"/><path d="M5 12H2a10 10 0 0 0 20 0h-3"/></svg>'
  };
  const ICON_DEFAULT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
  function iconForFuncion(f) { return FUNC_ICONS[f] || ICON_DEFAULT; }

  // ── Helpers DOM ────────────────────────────────────────────────────────
  const $  = (id) => document.getElementById(id);
  const el = (tag, attrs = {}, ...kids) => {
    const n = document.createElement(tag);
    for (const k in attrs) {
      if (k === 'class') n.className = attrs[k];
      else if (k.startsWith('on')) n.addEventListener(k.slice(2), attrs[k]);
      else if (k === 'data') for (const dk in attrs[k]) n.dataset[dk] = attrs[k][dk];
      else n.setAttribute(k, attrs[k]);
    }
    kids.flat().forEach(c => n.append(c?.nodeType ? c : document.createTextNode(c ?? '')));
    return n;
  };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g,
    c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);

  const labelEstado = { activo: "Activo", traslado: "Traslado", refrigerio: "Refrigerio", permiso: "Permiso", incidencia: "Inactivo" };
  const labelTipo   = { traslado: "Traslado", refrigerio: "Refrigerio", permiso: "Permiso" };

  // ── Lógica de alerta por tiempo ────────────────────────────────────────
  function toMin(hhmm) {
    if (!hhmm || !/^\d{1,2}:\d{2}$/.test(hhmm)) return null;
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
  }
  function nowMin() {
    const d = new Date();
    return d.getHours() * 60 + d.getMinutes();
  }
  // Normaliza un minuto-del-día a la línea de tiempo del turno. Si el turno
  // cruza medianoche (Noche), las horas posteriores a las 00:00 (menores que
  // el inicio) se desplazan +1440 para quedar después del inicio.
  function normMin(min) {
    if (min == null) return null;
    if (state.turnoWraps && min < state.turnoIni) return min + 1440;
    return min;
  }
  // "Ahora" en la línea de tiempo del turno.
  // Si el turno está cerrado, congela el tiempo en turnoFin para que la
  // bitácora, las stats y los segmentos reflejen el estado al cierre.
  function nowMinT() {
    if (state.turnoEstado === 'cerrado' && state.turnoFin != null) return state.turnoFin;
    // Turno de un día anterior (o ya finalizado aunque no se haya validado):
    // congela el tiempo en turnoFin en vez de seguir acumulando con la hora
    // de pared actual, para que al abrir días anteriores se vea lo grabado en
    // ese turno y no una barra que crece como si fuera el turno en curso.
    if (state.turnoFecha && state.turnoIni != null && state.turnoFin != null) {
      const [y, m, d] = String(state.turnoFecha).split('-').map(Number);
      if (y && m && d) {
        // Inicio absoluto del turno = fecha del turno a las 00:00 + turnoIni.
        // turnoFin ya viene normalizado (+1440 si cruza medianoche), por lo que
        // el fin absoluto se calcula igual sobre la misma fecha base.
        const startMs = new Date(y, m - 1, d, 0, 0, 0, 0).getTime() + state.turnoIni * 60000;
        const t = state.turnoIni + Math.floor((Date.now() - startMs) / 60000);
        if (t <= state.turnoIni) return state.turnoIni; // aún no inicia
        if (t >= state.turnoFin) return state.turnoFin; // ya finalizó → congelado
        return t;                                       // en curso (hora real del turno)
      }
    }
    return normMin(nowMin());
  }
  function computeAlert(p) {
    // Refrigerio: total de minutos acumulados en bitácora (independiente del estado)
    const refLimit = state.limites.refrigerio;
    if (refLimit) {
      let refMins = 0;
      p.bitacora.forEach(ev => {
        if (ev.tipo !== 'refrigerio') return;
        const i = normMin(toMin(ev.horaInicio));
        if (i == null) return;
        const f = normMin(toMin(ev.horaFin));
        refMins += f != null ? Math.max(0, f - i) : Math.max(0, nowMinT() - i);
      });
      if (refMins > refLimit)                                      return { level: 'over', mins: refMins - refLimit, limit: refLimit };
      if (refMins >= refLimit * WARN_RATIO && refMins < refLimit) return { level: 'warn', mins: refLimit - refMins, limit: refLimit };
    }
    // Permiso: total de minutos acumulados
    const perLimit = state.limites.permiso;
    if (perLimit && (p.estado === 'permiso' || p.estado === 'incidencia')) {
      let perMins = 0;
      p.bitacora.forEach(ev => {
        if (ev.tipo !== 'permiso') return;
        const i = normMin(toMin(ev.horaInicio));
        if (i == null) return;
        const f = normMin(toMin(ev.horaFin));
        perMins += f != null ? Math.max(0, f - i) : Math.max(0, nowMinT() - i);
      });
      if (perMins > perLimit)                                      return { level: 'over', mins: perMins - perLimit, limit: perLimit };
      if (perMins >= perLimit * WARN_RATIO && perMins < perLimit) return { level: 'warn', mins: perLimit - perMins, limit: perLimit };
    }
    return null;
  }

  // ── Helpers de turno / duración / stats ────────────────────────────────
  function parseTurnoRange(label) {
    // "Mañana · 06:00–14:00"  →  { ini:360, fin:840 }
    const m = String(label || '').match(/(\d{1,2}):(\d{2})\s*[-–—]\s*(\d{1,2}):(\d{2})/);
    if (!m) return { ini: 6*60, fin: 14*60 };
    return { ini: +m[1]*60 + +m[2], fin: +m[3]*60 + +m[4] };
  }
  // Ventana horaria de UNA persona, según su horario asignado (horario_colab).
  // Si no tiene horario propio, cae a la ventana global del turno. El fin se
  // normaliza (+1440) cuando cruza medianoche, igual que state.turnoFin.
  function rangoPersona(p) {
    if (p && p.horario) {
      const r = parseTurnoRange(p.horario);
      const wraps = r.fin <= r.ini;
      return { ini: r.ini, fin: wraps ? r.fin + 1440 : r.fin };
    }
    return { ini: state.turnoIni, fin: state.turnoFin };
  }
  // "HH:MM" del fin del horario de una persona (para autocompletar horas).
  function finHoraPersona(p) {
    const fm = rangoPersona(p).fin % 1440;
    return `${String(Math.floor(fm/60)).padStart(2,'0')}:${String(fm%60).padStart(2,'0')}`;
  }
  function fmtMin(mins) {
    if (mins == null || isNaN(mins) || mins < 0) return '—';
    const h = Math.floor(mins / 60), m = Math.round(mins % 60);
    return h ? `${h}h ${String(m).padStart(2,'0')}` : `${m}m`;
  }
  function evDurMin(ev) {
    const i = normMin(toMin(ev.horaInicio)), f = normMin(toMin(ev.horaFin));
    if (i == null) return 0;
    if (f == null) return Math.max(0, nowMinT() - i);
    return Math.max(0, f - i);
  }
  // Minutos en cada estado dentro del turno actual
  function computeStats(p) {
    const counts = { traslado: 0, refrigerio: 0, permiso: 0 };
    const minutos = { traslado: 0, refrigerio: 0, permiso: 0 };
    p.bitacora.forEach(ev => {
      if (counts[ev.tipo] != null) {
        counts[ev.tipo]++;
        minutos[ev.tipo] += evDurMin(ev);
      }
    });
    const rg          = rangoPersona(p);
    const nowCap      = nowParaPersona(p);
    const turnoActual = Math.max(0, nowCap - rg.ini);
    const noActivo    = minutos.refrigerio + minutos.permiso;
    const activo      = Math.max(0, turnoActual - noActivo);
    return {
      counts,
      minutos,
      minActivo: activo,
      total: p.bitacora.length
    };
  }
  // Minutos en el estado actual
  function getDurationInState(p) {
    if (p.estado === 'refrigerio' || p.estado === 'incidencia') {
      const tipoEsp = p.estado === 'incidencia' ? 'permiso' : 'refrigerio';
      for (let i = p.bitacora.length - 1; i >= 0; i--) {
        if (p.bitacora[i].tipo === tipoEsp) {
          const ini = normMin(toMin(p.bitacora[i].horaInicio));
          if (ini != null) return Math.max(0, nowMinT() - ini);
        }
      }
      return 0;
    }
    // Activo: tiempo desde el fin del último evento o desde el inicio del turno
    const rg = rangoPersona(p);
    let lastEnd = rg.ini;
    p.bitacora.forEach(ev => {
      const f = normMin(toMin(ev.horaFin)) ?? normMin(toMin(ev.horaInicio));
      if (f != null && f > lastEnd) lastEnd = f;
    });
    return Math.max(0, Math.min(nowMinT(), rg.fin) - lastEnd);
  }
  // "Ahora" efectivo para una persona: si el turno está cerrado se asume que
  // cumplió su jornada hasta su propio fin; si está abierto, la hora actual
  // (tope en su fin de horario).
  function nowParaPersona(p) {
    const rg = rangoPersona(p);
    return state.turnoEstado === 'cerrado' ? rg.fin : Math.min(nowMinT(), rg.fin);
  }
  // Horas efectivamente laboradas en el turno: (fin - turnoIni) - pausas
  // Si existe un evento permiso con motivo "Salida Temprana", el turno termina ahí.
  function getHorasLaboradas(p) {
    const rg = rangoPersona(p);
    const baseNow = nowParaPersona(p);
    const salidaEv = p.bitacora.find(ev => ev.tipo === 'permiso' && ev.motivo === 'Salida Temprana');
    const endMin = Math.min(rg.fin, salidaEv
      ? (normMin(toMin(salidaEv.horaInicio)) ?? baseNow)
      : baseNow);
    const turnoLen = Math.max(0, endMin - rg.ini);
    let pausas = 0;
    p.bitacora.forEach(ev => {
      if (ev.tipo === 'refrigerio' || (ev.tipo === 'permiso' && ev.motivo !== 'Salida Temprana')) {
        const i = normMin(toMin(ev.horaInicio));
        const f = normMin(toMin(ev.horaFin));
        if (i != null) pausas += f != null ? Math.max(0, f - i) : Math.max(0, endMin - i);
      }
    });
    return Math.max(0, turnoLen - pausas);
  }
  // Ordenar
  function sortPersonal(list) {
    const arr = [...list];
    if (state.ordenar === 'nombre') {
      arr.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
    } else if (state.ordenar === 'alerta') {
      const rank = { over: 0, warn: 1 };
      arr.sort((a, b) => {
        const ra = rank[computeAlert(a)?.level] ?? 9;
        const rb = rank[computeAlert(b)?.level] ?? 9;
        return ra - rb || a.nombre.localeCompare(b.nombre, 'es');
      });
    } else if (state.ordenar === 'eventos') {
      arr.sort((a, b) => b.bitacora.length - a.bitacora.length || a.nombre.localeCompare(b.nombre, 'es'));
    } else if (state.ordenar === 'estado') {
      const rank = { permiso: 0, incidencia: 0, refrigerio: 1, activo: 2 };
      arr.sort((a, b) => (rank[a.estado] ?? 9) - (rank[b.estado] ?? 9) || a.nombre.localeCompare(b.nombre, 'es'));
    }
    return arr;
  }
  // Agrupar
  function groupPersonal(list) {
    if (state.agrupar === 'none') return [{ key: '', items: list }];
    const buckets = new Map();
    list.forEach(p => {
      let k = '';
      if (state.agrupar === 'funcion')   k = p.funcion   || 'Sin función';
      if (state.agrupar === 'ubicacion') k = p.ubicacion || 'Sin ubicación';
      if (state.agrupar === 'estado')    k = labelEstado[p.estado] || p.estado;
      if (!buckets.has(k)) buckets.set(k, []);
      buckets.get(k).push(p);
    });
    return [...buckets.entries()]
      .sort((a, b) => a[0].localeCompare(b[0], 'es'))
      .map(([key, items]) => ({ key, items }));
  }

  // ── Toast ──────────────────────────────────────────────────────────────
  let toastT;
  function toast(msg) {
    const t = $('estToast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastT);
    toastT = setTimeout(() => t.classList.remove('show'), 2200);
  }

  // ── Backend ──────────────────────────────────────────────────────────────
  // POST JSON a una API; devuelve el objeto parseado. Lanza Error con mensaje
  // legible si la respuesta no es success.
  async function apiPost(url, body) {
    const res  = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    let data;
    try { data = await res.json(); } catch { data = null; }
    if (!res.ok || !data || !data.success) {
      throw new Error((data && data.error) || 'Error de servidor (' + res.status + ')');
    }
    return data;
  }

  // Verdadero si el colaborador ya salió con "Salida Temprana" (permiso definitivo).
  function salidaTemprana(p) {
    const now = nowMinT();
    return (p.bitacora || []).some(ev => {
      if (ev.tipo !== 'permiso') return false;
      if ((ev.motivo || '').toLowerCase().indexOf('salida') === -1) return false;
      const ini = normMin(toMin(ev.horaInicio));
      return ini != null && ini <= now;
    });
  }

  // Verdadero si el colaborador tiene un refrigerio activo ahora mismo según bitácora.
  function estaEnRefrigerioActivo(p) {
    const now = nowMinT();
    return (p.bitacora || []).some(ev => {
      if (ev.tipo !== 'refrigerio') return false;
      const ini = normMin(toMin(ev.horaInicio));
      if (ini == null || ini > now) return false;
      const fin = normMin(toMin(ev.horaFin));
      return fin == null || now <= fin;
    });
  }

  // Verdadero si el colaborador tiene un permiso activo (no salida temprana) según bitácora.
  function estaEnPermisoActivo(p) {
    const now = nowMinT();
    return (p.bitacora || []).some(ev => {
      if (ev.tipo !== 'permiso') return false;
      if ((ev.motivo || '').toLowerCase().indexOf('salida') !== -1) return false;
      const ini = normMin(toMin(ev.horaInicio));
      if (ini == null || ini > now) return false;
      const fin = normMin(toMin(ev.horaFin));
      return fin == null || now <= fin;
    });
  }

  // Verdadero si el colaborador tiene un traslado activo ahora mismo según bitácora.
  function estaEnTrasladoActivo(p) {
    const now = nowMinT();
    return (p.bitacora || []).some(ev => {
      if (ev.tipo !== 'traslado') return false;
      const ini = normMin(toMin(ev.horaInicio));
      if (ini == null || ini > now) return false;
      const fin = normMin(toMin(ev.horaFin));
      return fin == null || now <= fin;
    });
  }

  // ── Permisos por rol + fecha de turno ─────────────────────────────────
  // Admin/Supervisor: cualquier turno. Coordinador: solo el turno de hoy.
  function _esTurnoHoy() {
    // Comparar contra la fecha LOCAL, no UTC. En turno noche (UTC-5), toISOString()
    // ya devuelve el día siguiente y bloqueaba al Coordinador en su propio turno.
    const hoy = new Date();
    const local = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
    return state.turnoFecha === local;
  }
  function puedeModificar() {
    const r = state.rol;
    if (r === 'Administrador' || r === 'Supervisor') return true;
    if (r === 'Coordinador') return _esTurnoHoy();
    return false;
  }
  function puedeEliminar() {
    return puedeModificar();
  }

  // ── KPIs + Resumen visual + Panel lateral ──────────────────────────────
  function renderKpis() {
    $('kpiTotal').textContent     = state.personal.length;
    $('kpiRefri').textContent     = state.personal.filter(p => (p.bitacora || []).some(ev => ev.tipo === 'refrigerio')).length;
    if ($('kpiTraslados')) $('kpiTraslados').textContent = state.personal.filter(p => (p.bitacora || []).some(ev => ev.tipo === 'traslado')).length;
    if ($('kpiPermisos')) $('kpiPermisos').textContent  = state.personal.filter(p => (p.bitacora || []).some(ev => ev.tipo === 'permiso' && (ev.motivo || '').toLowerCase().indexOf('salida') === -1)).length;
    $('kpiInc').textContent = state.personal.filter(p => salidaTemprana(p)).length;
    const alertas = state.personal.reduce((n, p) => n + (computeAlert(p)?.level === 'over' ? 1 : 0), 0);
    $('kpiAlertas').textContent = alertas;
    $('kpiAlertWrap').classList.toggle('has-alerts', alertas > 0);
    $('estTurnoLabel').textContent = state.turnoLabel || '—';
    renderResumenVisual();
    renderSide();
  }

  // SVG donut premium light
  function svgDonut(slices, total) {
    const r = 40, cx = 56, cy = 56, circ = 2 * Math.PI * r;
    let acc = 0;
    const segs = slices.map((s, i) => {
      const frac = total ? (s.value / total) : 0;
      if (!frac) return '';
      const dash = `${circ * frac - 1.5} ${circ * (1 - frac) + 1.5}`;
      const off  = -circ * acc;
      acc += frac;
      return `<circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="${s.color}" stroke-width="11" stroke-dasharray="${dash}" stroke-dashoffset="${off}" transform="rotate(-90 ${cx} ${cy})" stroke-linecap="round"/>`;
    }).join('');
    return `
      <svg width="112" height="112" viewBox="0 0 112 112" style="overflow:visible;flex-shrink:0">
        <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="#f1f5f9" stroke-width="11"/>
        ${segs}
        <circle cx="${cx}" cy="${cy}" r="30" fill="#ffffff"/>
        <circle cx="${cx}" cy="${cy}" r="30" fill="none" stroke="#cbd5e1" stroke-width="1"/>
        <text x="${cx}" y="${cy - 4}" text-anchor="middle" dominant-baseline="central" font-size="24" font-weight="800" fill="#111827">${total}</text>
        <text x="${cx}" y="${cy + 16}" text-anchor="middle" font-size="6.5" font-weight="700" fill="#64748b" letter-spacing="0.14em">EN TURNO</text>
      </svg>`;
  }
  function renderResumenVisual() {
    const cont = $('estResumen'); if (!cont) return;
    const total = state.personal.length;
    const efectivos = state.personal;
    const efectTotal = efectivos.length;
    const slEst = [
      { name: 'Activos',    value: efectivos.filter(p => !salidaTemprana(p) && !estaEnTrasladoActivo(p) && !estaEnRefrigerioActivo(p) && !estaEnPermisoActivo(p)).length, color: '#00e676' },
      { name: 'Traslado',   value: efectivos.filter(p => !salidaTemprana(p) && estaEnTrasladoActivo(p)).length,  color: '#bd00ff' },
      { name: 'Refrigerio', value: efectivos.filter(p => !salidaTemprana(p) && estaEnRefrigerioActivo(p)).length, color: '#ffc107' },
      { name: 'Permiso',    value: efectivos.filter(p => !salidaTemprana(p) && estaEnPermisoActivo(p)).length,    color: '#f97316' },
      { name: 'Inactivos',  value: efectivos.filter(p => salidaTemprana(p)).length, color: '#ff4757' }
    ];

    // Por función
    const fmap = new Map();
    state.personal.forEach(p => fmap.set(p.funcion || '—', (fmap.get(p.funcion || '—') || 0) + 1));
    const funcs = [...fmap.entries()].sort((a, b) => b[1] - a[1]).slice(0, 5);
    const maxF  = Math.max(1, ...funcs.map(f => f[1]));

    // Por ubicación
    const umap = new Map();
    state.personal.forEach(p => umap.set(p.ubicacion || '—', (umap.get(p.ubicacion || '—') || 0) + 1));
    const ubics = [...umap.entries()].sort((a, b) => b[1] - a[1]).slice(0, 5);
    const maxU  = Math.max(1, ...ubics.map(u => u[1]));

    cont.innerHTML = `
      <div class="est-resumen-card">
        <h4>Estado del personal</h4>
        <div class="est-resumen-body">
          ${svgDonut(slEst, efectTotal)}
          <div class="est-resumen-legend">
            ${slEst.map(s => `
              <div class="est-legend-item">
                <span class="est-legend-dot" style="background:${s.color}"></span>
                <span class="est-legend-name">${esc(s.name)}</span>
                <span class="est-legend-val">${s.value}</span>
              </div>`).join('')}
          </div>
        </div>
      </div>
      <div class="est-resumen-card">
        <h4>Personal por función</h4>
        <div class="est-resumen-body">
          <div class="est-hbars">
            ${funcs.length ? funcs.map(([k, v]) => `
              <div class="est-hbar">
                <span class="lbl" title="${esc(k)}">${esc(k)}</span>
                <span class="track"><span class="fill" style="width:${Math.round(v / maxF * 100)}%"></span></span>
                <span class="val">${v}</span>
              </div>`).join('') : '<div class="est-side-empty">Sin datos</div>'}
          </div>
        </div>
      </div>
      <div class="est-resumen-card">
        <h4>Top ubicaciones</h4>
        <div class="est-resumen-body">
          <div class="est-hbars">
            ${ubics.length ? ubics.map(([k, v]) => `
              <div class="est-hbar">
                <span class="lbl" title="${esc(k)}">${esc(k)}</span>
                <span class="track"><span class="fill" style="width:${Math.round(v / maxU * 100)}%"></span></span>
                <span class="val">${v}</span>
              </div>`).join('') : '<div class="est-side-empty">Sin datos</div>'}
          </div>
        </div>
      </div>`;
  }

  function renderSide() { }

  // ── Grid + Lista ───────────────────────────────────────────────────────
  function getVisible() {
    const q = state.query.trim().toLowerCase();
    return state.personal.filter(p => {
      if (state.filtro !== 'todos') {
        if (state.filtro === 'refrigerio') {
          if (!(p.bitacora || []).some(ev => ev.tipo === 'refrigerio')) return false;
        } else if (state.filtro === 'traslado') {
          if (!(p.bitacora || []).some(ev => ev.tipo === 'traslado')) return false;
        } else if (state.filtro === 'permiso') {
          if (!(p.bitacora || []).some(ev => ev.tipo === 'permiso')) return false;
        } else if (state.filtro === 'activo') {
          if (salidaTemprana(p) || estaEnTrasladoActivo(p) || estaEnRefrigerioActivo(p) || estaEnPermisoActivo(p)) return false;
        } else if (state.filtro === 'inactivo') {
          if (!salidaTemprana(p)) return false;
        } else if (p.estado !== state.filtro) return false;
      }
      if (!q) return true;
      return [p.nombre, p.funcion, p.ubicacion, p.id].some(v =>
        String(v).toLowerCase().includes(q));
    });
  }
  function cardHTML(p) {
    const al = computeAlert(p);
    const dur = getHorasLaboradas(p);
    const stats = computeStats(p);
    const refMins  = stats.minutos.refrigerio;
    const trasMins = stats.minutos.traslado;
    const perMins  = stats.minutos.permiso;
    // Estado visual en tiempo real según bitácora activa
    const estadoVivo = salidaTemprana(p) ? 'incidencia'
                     : estaEnTrasladoActivo(p) ? 'traslado'
                     : estaEnRefrigerioActivo(p) ? 'refrigerio'
                     : estaEnPermisoActivo(p) ? 'permiso'
                     : 'activo';
    const ult = p.bitacora.length
      ? [...p.bitacora].sort((a, b) => (b.horaInicio || '').localeCompare(a.horaInicio || ''))[0]
      : null;
    const dataAttr = `data-estado="${esc(estadoVivo)}" data-id="${esc(p.id)}" data-alert="${al ? al.level : ''}"`;

    // Salida temprana: permiso con motivo que contenga "salida"
    const stEv = p.bitacora.find(ev => ev.tipo === 'permiso' && (ev.motivo || '').toLowerCase().includes('salida'));
    const stHora = stEv ? (stEv.horaFin || stEv.horaInicio || '—') : null;

    // Segmented minibar: barra continua activo/evento/futuro.
    // Usa la ventana horaria propia de la persona (08:00–21:15, etc.).
    const rg = rangoPersona(p);
    const now = nowParaPersona(p);
    const turnoLen = Math.max(1, rg.fin - rg.ini);
    const nowCapped = Math.max(rg.ini, Math.min(now, rg.fin));
    const sortedEvs = [...p.bitacora]
      .map(ev => {
        const ini    = normMin(toMin(ev.horaInicio));
        const rawFin = normMin(toMin(ev.horaFin));
        return { tipo: ev.tipo, ini, rawFin, fin: rawFin ?? nowCapped };
      })
      .filter(ev => ev.ini != null)
      .sort((a, b) => a.ini - b.ini);

    const segments = [];
    let cursor = rg.ini;
    for (const ev of sortedEvs) {
      const evStart = Math.max(rg.ini, ev.ini);
      const evEnd   = Math.min(ev.fin, nowCapped);
      if (evStart > cursor && cursor < nowCapped) {
        segments.push({ tipo: 'activo', ini: cursor, fin: Math.min(evStart, nowCapped) });
      }
      if (evEnd > Math.max(cursor, evStart) && evStart < nowCapped) {
        segments.push({ tipo: ev.tipo, ini: Math.max(cursor, evStart), fin: evEnd });
      }
      cursor = Math.max(cursor, ev.rawFin ?? evEnd);
    }
    if (cursor < nowCapped) {
      segments.push({ tipo: 'activo', ini: cursor, fin: nowCapped });
    }
    if (nowCapped < rg.fin) {
      segments.push({ tipo: 'futuro', ini: nowCapped, fin: rg.fin });
    }
    const marks = segments.map(seg => {
      const left  = Math.max(0, (seg.ini - rg.ini) / turnoLen * 100);
      const right = Math.min(100, (seg.fin - rg.ini) / turnoLen * 100);
      const width = Math.max(0.4, right - left);
      return `<span class="est-minibar-mark" data-tipo="${esc(seg.tipo)}" style="left:${left.toFixed(2)}%;width:${width.toFixed(2)}%"></span>`;
    }).join('');
    const nowPct = (nowCapped - rg.ini) / turnoLen * 100;
    const nowLine = (nowCapped > rg.ini && nowCapped < rg.fin)
      ? `<span class="est-minibar-now" style="left:${nowPct.toFixed(2)}%"></span>`
      : '';

    // Etiquetas de hora
    const iniLbl = rg.ini % 1440, finLbl = rg.fin % 1440;
    const startLbl = `${String(Math.floor(iniLbl/60)).padStart(2,'0')}:${String(iniLbl%60).padStart(2,'0')}`;
    const endLbl   = `${String(Math.floor(finLbl/60)).padStart(2,'0')}:${String(finLbl%60).padStart(2,'0')}`;

    const alertPill = al ? `
      <span class="est-alert-pill" data-lv="${al.level}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        ${al.level === 'over' ? `+${al.mins} min excedido` : `${al.mins} min restantes`}
      </span>` : '';

    return `
      <div class="est-card" ${dataAttr}>
        <div class="est-card-head">
          <div class="est-card-head-l">
            <div class="est-card-icon">${iconForFuncion(p.funcion)}</div>
            <div class="col">
              <div class="est-card-name">${esc(p.nombre)}</div>
              <div class="est-card-sub">
                <span>${esc(p.id)}</span>
                <span class="sep">•</span>
                <span>${esc(p.funcion || '—')}</span>
              </div>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
            <span class="est-badge" data-st="${esc(estadoVivo)}">
              <span class="dot"></span>${esc(labelEstado[estadoVivo] || estadoVivo)}
            </span>
            <span class="est-card-dur" title="Horas laboradas en el turno">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              ${fmtMin(dur)}
            </span>
          </div>
        </div>
        <div class="est-card-meta">
          <div class="est-meta">
            <span class="est-meta-lbl">Ubicación</span>
            <span class="est-meta-val">${esc(p.ubicacion || '—')}</span>
          </div>
          <div class="est-meta">
            <span class="est-meta-lbl">Función</span>
            <span class="est-meta-val">${esc(p.tipoFuncion || '—')}</span>
          </div>
          ${p.cuadrilla ? `<div class="est-meta est-meta-team">
            <span class="est-meta-lbl">Team</span>
            <span class="est-meta-team-badge">${esc(p.cuadrilla)}</span>
          </div>` : ''}
        </div>
        <div class="est-card-stats">
          <div class="est-stat est-stat-ref">
            <span class="est-stat-lbl">☕ Refrigerio</span>
            ${stats.counts.refrigerio
              ? `<span class="est-stat-val">${fmtMin(refMins)}</span>`
              : `<span class="est-stat-nil">Sin registro</span>`}
          </div>
          <div class="est-stat est-stat-tra">
            <span class="est-stat-lbl">↔ Traslado</span>
            ${stats.counts.traslado
              ? `<span class="est-stat-val">${fmtMin(trasMins)}</span>`
              : `<span class="est-stat-nil">Sin registro</span>`}
          </div>
          <div class="est-stat est-stat-per">
            <span class="est-stat-lbl">📋 Permiso</span>
            ${stats.counts.permiso
              ? `<span class="est-stat-val">${fmtMin(perMins)}</span>`
              : `<span class="est-stat-nil">Sin registro</span>`}
          </div>
          <div class="est-stat est-stat-sal">
            <span class="est-stat-lbl">🚪 Salida</span>
            ${stHora
              ? `<span class="est-stat-val est-stat-red">${esc(stHora)}</span>`
              : `<span class="est-stat-nil">Sin registro</span>`}
          </div>
        </div>
        ${alertPill}
        <div class="est-minibar">
          <div class="est-minibar-head">
            <span>Bitácora del turno</span>
            <span class="legend"><span class="a">Activo</span><span class="t">Traslado</span><span class="r">Refri.</span><span class="p">Permiso</span></span>
          </div>
          <div class="est-minibar-track">${marks}${nowLine}</div>
          <div class="est-minibar-hours"><span>${startLbl}</span><span>${endLbl}</span></div>
        </div>
        <div class="est-card-foot">
          <span class="last-ev">
            <span style="color:var(--co-faint)">Total horas activas:</span>
            <span class="time" style="color:#16a34a;font-weight:700">${fmtMin(stats.minActivo)}</span>
          </span>
          <span class="arrow">→</span>
        </div>
      </div>`;
  }
  function renderGrid() {
    const grid = $('estGrid'), lwrap = $('estListWrap');
    if (state.vista === 'list') {
      grid.style.display = 'none';
      lwrap.style.display = '';
      renderList();
      return;
    }
    grid.style.display = '';
    lwrap.style.display = 'none';

    const list = sortPersonal(getVisible());
    grid.innerHTML = '';
    if (!list.length) {
      grid.classList.remove('est-grid-grouped');
      grid.style.display = '';
      if (!state.personal.length) {
        // Turno vacío: invitar a colocar personal.
        const box = el('div', { class: 'est-empty est-empty-turno' });
        box.innerHTML = `
          <div class="est-empty-title">Aún no hay personal en este turno</div>
          <div class="est-empty-sub">Coloca colaboradores reales desde el catálogo para empezar a monitorear el turno.</div>
          <button class="est-btn primary" id="estEmptyAdd">+ Añadir personal al turno</button>`;
        box.querySelector('#estEmptyAdd').addEventListener('click', openAddPersonal);
        grid.append(box);
      } else {
        grid.append(el('div', { class: 'est-empty' }, 'No hay personal que coincida con el filtro.'));
      }
      return;
    }
    const groups = groupPersonal(list);
    if (groups.length === 1 && !groups[0].key) {
      grid.classList.remove('est-grid-grouped');
      groups[0].items.forEach(p => grid.insertAdjacentHTML('beforeend', cardHTML(p)));
    } else {
      grid.classList.add('est-grid-grouped');
      groups.forEach(g => {
        const header = `<div class="est-group-header">${esc(g.key)}<span class="est-group-count">${g.items.length}</span></div>`;
        const cards  = `<div class="est-grid">${g.items.map(cardHTML).join('')}</div>`;
        grid.insertAdjacentHTML('beforeend', header + cards);
      });
    }
  }
  function renderList() {
    const tbody = $('estListBody');
    const list  = sortPersonal(getVisible());
    tbody.innerHTML = '';
    if (!list.length) {
      tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:32px;color:var(--co-faint)">No hay personal que coincida con el filtro.</td></tr>`;
      return;
    }
    const groups = groupPersonal(list);
    const useGroups = !(groups.length === 1 && !groups[0].key);
    groups.forEach(g => {
      if (useGroups) {
        tbody.insertAdjacentHTML('beforeend',
          `<tr class="est-list-group"><td colspan="9">${esc(g.key)} · ${g.items.length}</td></tr>`);
      }
      g.items.forEach(p => {
        const st = computeStats(p);
        const al = computeAlert(p);
        const alertCell = al
          ? `<span class="est-alert-pill" data-lv="${al.level}">${al.level === 'over' ? `+${al.mins}m` : `${al.mins}m`}</span>`
          : '<span style="color:var(--co-faint);font-size:11px">—</span>';
        tbody.insertAdjacentHTML('beforeend', `
          <tr data-id="${esc(p.id)}">
            <td>
              <div class="name-cell">
                <div class="est-card-icon">${iconForFuncion(p.funcion)}</div>
                <div class="col">
                  <span class="nm">${esc(p.nombre)}</span>
                  <span class="id">${esc(p.id)}</span>
                </div>
              </div>
            </td>
            <td>${esc(p.funcion || '—')}</td>
            <td>${esc(p.ubicacion || '—')}</td>
            <td><span class="est-badge" data-st="${esc(salidaTemprana(p)?'incidencia':estaEnTrasladoActivo(p)?'traslado':estaEnRefrigerioActivo(p)?'refrigerio':estaEnPermisoActivo(p)?'permiso':'activo')}"><span class="dot"></span>${esc(labelEstado[salidaTemprana(p)?'incidencia':estaEnTrasladoActivo(p)?'traslado':estaEnRefrigerioActivo(p)?'refrigerio':estaEnPermisoActivo(p)?'permiso':'activo']||'Activo')}</span></td>
            <td class="num">${st.counts.traslado}</td>
            <td class="num">${st.counts.refrigerio}</td>
            <td class="num">${st.counts.permiso}</td>
            <td class="num">${fmtMin(getDurationInState(p))}</td>
            <td>${alertCell}</td>
          </tr>`);
      });
    });
  }

  // ── Drawer ─────────────────────────────────────────────────────────────
  function fillSelect(sel, options, current) {
    sel.innerHTML = '';
    options.forEach(opt => {
      const o = document.createElement('option');
      o.value = opt; o.textContent = opt;
      if (opt === current) o.selected = true;
      sel.append(o);
    });
  }

  // ── Nave asignada ──────────────────────────────────────────────────────
  // Sólo las posiciones de muelle atienden una nave. Gate, Balanza,
  // Administrativo y demás no, y ofrecer el selector ahí invitaría a rellenar
  // el campo con cualquier cosa.
  function esUbicacionMuelle(ubic) {
    return /^\s*(muelle|berth)\b/i.test(String(ubic || ''));
  }

  // Carga las naves una vez por sesión de página. Si el servicio no responde,
  // el selector queda vacío y la asignación sigue funcionando sin nave: es un
  // dato opcional, no debe bloquear el registro del turno.
  async function cargarNaves() {
    try {
      const r = await fetch('api/get_naves_muelle.php', { cache: 'no-store' });
      const d = await r.json();
      state.naves = (d && d.success && d.data) ? d.data : [];
    } catch (_) {
      state.naves = [];
    }
  }

  /**
   * Puebla un selector de nave según la ubicación elegida.
   * Devuelve el id seleccionado (o '' si no aplica).
   *
   * Cuando la ubicación es un muelle y hay exactamente una nave atracada ahí,
   * se preselecciona: es lo que va a elegir quien asigna el 99% de las veces.
   * Se sugiere, no se impone — sigue pudiendo cambiarla.
   */
  function fillNaveSelect(sel, wrap, ubicacion, naveIdActual) {
    const aplica = esUbicacionMuelle(ubicacion);
    if (wrap) wrap.style.display = aplica ? '' : 'none';
    sel.disabled = !aplica;
    if (!aplica) { sel.innerHTML = '<option value="">— No aplica —</option>'; sel.value = ''; return ''; }

    const enEsteMuelle = state.naves.filter(n =>
      n.muelle && String(n.muelle).trim().toLowerCase() === String(ubicacion).trim().toLowerCase());
    // Si la nave ya guardada no está en la lista (finalizó, cambió de muelle),
    // se inyecta igualmente para no perderla en silencio al guardar.
    const guardada = naveIdActual
      ? state.naves.find(n => Number(n.id) === Number(naveIdActual)) : null;

    let sugerida = '';
    if (naveIdActual) sugerida = String(naveIdActual);
    else if (enEsteMuelle.length === 1) sugerida = String(enEsteMuelle[0].id);

    const opciones = ['<option value="">— Sin nave —</option>'];
    if (enEsteMuelle.length) {
      opciones.push('<optgroup label="En esta ubicación">'
        + enEsteMuelle.map(n => `<option value="${n.id}">${esc(n.nombre)}</option>`).join('')
        + '</optgroup>');
    }
    const otras = state.naves.filter(n => !enEsteMuelle.includes(n));
    if (otras.length) {
      opciones.push('<optgroup label="Otras naves">'
        + otras.map(n => `<option value="${n.id}">${esc(n.nombre)}${n.muelle ? ' · ' + esc(n.muelle) : ''}</option>`).join('')
        + '</optgroup>');
    }
    if (naveIdActual && !guardada) {
      opciones.push(`<option value="${naveIdActual}">Nave #${naveIdActual} (ya no listada)</option>`);
    }
    sel.innerHTML = opciones.join('');
    sel.value = sugerida;
    return sel.value;
  }
  function openDrawer(id) {
    const p = state.personal.find(x => x.id === id);
    if (!p) return;
    state.selectedId = id;
    $('dwName').textContent = p.nombre;
    $('dwId').textContent   = p.id;
    // Foto de asistencia (auto-registro): se muestra si el colaborador la tomó.
    var fotoWrap = $('dwFotoWrap');
    if (fotoWrap) {
      if (p.fotoIngreso) {
        $('dwFoto').src = p.fotoIngreso;
        var fl = $('dwFotoLink'); if (fl) fl.href = p.fotoIngreso;
        fotoWrap.style.display = '';
      } else {
        fotoWrap.style.display = 'none';
        $('dwFoto').src = '';
      }
    }
    // Asegura que la función/ubicación actuales sean seleccionables aunque no
    // estén en el catálogo operativo (evita sobrescribirlas al guardar).
    const funcOpts = p.funcion && !state.funciones.includes(p.funcion)
      ? [p.funcion, ...state.funciones] : state.funciones;
    const ubicOpts = p.ubicacion && !state.ubicaciones.includes(p.ubicacion)
      ? [p.ubicacion, ...state.ubicaciones] : state.ubicaciones;
    fillSelect($('dwFuncion'),   funcOpts,   p.funcion);
    fillSelect($('dwUbicacion'), ubicOpts, p.ubicacion);
    fillNaveSelect($('dwNave'), $('dwNaveWrap'), p.ubicacion, p.naveId);
    fillDwHorario(p.horario);
    // Solo un refrigerio por turno: si ya tiene, se deshabilita la opción.
    var tieneRefrigerio = (p.bitacora || []).some(function (ev) { return ev.tipo === 'refrigerio'; });
    var refOpt = $('dwEstado').querySelector('option[value="refrigerio"]');
    if (refOpt) {
      refOpt.disabled = tieneRefrigerio;
      refOpt.textContent = tieneRefrigerio ? 'Refrigerio (ya registrado)' : 'Refrigerio';
    }
    $('dwEstado').value = p.estado;
    $('dwHoraWrap').style.display = 'none';
    $('dwRadio').value = p.radio ? '1' : '0';
    const mod = puedeModificar();
    ['dwFuncion', 'dwUbicacion', 'dwHorario', 'dwEstado', 'dwRadio', 'dwRegistrar'].forEach(function(id) {
      const el = $(id); if (el) el.disabled = !mod;
    });
    // dwNave va aparte: ya lo deshabilitó fillNaveSelect() si la ubicación no
    // es un muelle, y el permiso de edición no debe volver a habilitarlo.
    $('dwNave').disabled = !mod || !esUbicacionMuelle($('dwUbicacion').value);
    const dwRem = $('dwRemove');
    dwRem.disabled = false;
    dwRem.style.display = puedeEliminar() ? '' : 'none';
    renderDrawerAlert(p);
    $('evIni').value = ''; $('evFin').value = '';
    fillMotivoSelect($('evMotivo'), state.motivos[0] && state.motivos[0].nombre);
    $('evObs').value = '';
    $('evOtrosWrap').style.display = motivoRequiereTexto($('evMotivo').value) ? '' : 'none';
    const checklistCard = $('dwChecklistCard');
    if (checklistCard) {
      const esTallyman = /tally/i.test(p.tipoFuncion || '') || /tally/i.test(p.funcion || '');
      checklistCard.style.display = esTallyman ? '' : 'none';
      $('dwCheckItem').value = '';
      $('dwCheckComentario').value = '';
      const checkAddBtn = $('dwCheckAdd');
      if (checkAddBtn) checkAddBtn.disabled = !mod;
    }
    renderChecklist();
    renderTimeline();
    $('estDrawer').classList.add('open');
    $('estDrawer').setAttribute('aria-hidden', 'false');
    $('estDrawerBack').classList.add('open');
    document.documentElement.style.setProperty('overflow', 'hidden', 'important');
    document.body.style.setProperty('overflow', 'hidden', 'important');
  }
  function renderDrawerAlert(p) {
    const banner = $('dwAlert');
    const al = computeAlert(p);
    if (!al) { banner.style.display = 'none'; return; }
    banner.style.display = 'flex';
    banner.dataset.lv = al.level;
    $('dwAlertText').textContent = al.level === 'over'
      ? `Límite de refrigerio excedido por ${al.mins} min (tope: ${al.limit} min).`
      : `Quedan ${al.mins} min antes de exceder el límite de ${al.limit} min.`;
  }
  function closeDrawer() {
    $('estDrawer').classList.remove('open');
    $('estDrawer').setAttribute('aria-hidden', 'true');
    $('estDrawerBack').classList.remove('open');
    document.documentElement.style.removeProperty('overflow');
    document.body.style.removeProperty('overflow');
    state.selectedId = null;
  }

  // ── Checklist tallyman ───────────────────────────────────────────────────
  function renderChecklist() {
    const cont = $('dwChecklist');
    if (!cont) return;
    cont.innerHTML = '';
    const p = state.personal.find(x => x.id === state.selectedId);
    if (!p || !(p.checklist || []).length) {
      cont.append(el('div', { class: 'est-empty' }, 'Sin ítems registrados.'));
      return;
    }
    const canDel = puedeEliminar();
    const list = el('div', { class: 'est-checklist' });
    p.checklist.forEach((it) => {
      const node = el('div', { class: 'est-checklist-item' });
      node.innerHTML = `
        <div class="est-checklist-item-body">
          <div class="est-checklist-item-name">${esc(it.item)}</div>
          ${it.comentario ? `<div class="est-checklist-item-comment">${esc(it.comentario)}</div>` : ''}
        </div>
        ${canDel ? `<button class="est-checklist-item-del" title="Eliminar">×</button>` : ''}`;
      const delBtn = node.querySelector('.est-checklist-item-del');
      if (delBtn) delBtn.addEventListener('click', async () => {
        delBtn.disabled = true;
        try {
          if (it.id != null) await apiPost('api/delete_checklist_item.php', { id: it.id });
          p.checklist = p.checklist.filter(x => x !== it);
          renderChecklist();
          toast('Ítem eliminado');
        } catch (err) {
          delBtn.disabled = false;
          toast('No se pudo eliminar: ' + err.message, 'error');
        }
      });
      list.append(node);
    });
    cont.append(list);
  }
  async function agregarChecklistItem() {
    const p = state.personal.find(x => x.id === state.selectedId);
    if (!p) return;
    const itemInp = $('dwCheckItem');
    const comInp  = $('dwCheckComentario');
    const item = itemInp.value.trim();
    const comentario = comInp.value.trim();
    if (!item) { toast('El ítem es obligatorio', 'error'); return; }
    const btn = $('dwCheckAdd'); btn.disabled = true;
    try {
      const r = await apiPost('api/add_checklist_item.php', { tpId: p.tpId, item, comentario });
      p.checklist = p.checklist || [];
      p.checklist.push({ id: r.id, item, comentario });
      itemInp.value = ''; comInp.value = '';
      renderChecklist();
      toast('Ítem agregado');
    } catch (err) {
      toast('No se pudo agregar: ' + err.message, 'error');
    } finally {
      btn.disabled = false;
    }
  }

  // ── Timeline ───────────────────────────────────────────────────────────
  function renderTimeline() {
    const cont = $('dwTimeline');
    cont.innerHTML = '';
    const p = state.personal.find(x => x.id === state.selectedId);
    if (!p || !p.bitacora.length) {
      cont.append(el('div', { class: 'est-empty' }, 'Sin eventos registrados.'));
      return;
    }
    const tl = el('div', { class: 'est-timeline' });
    const canEdit = puedeModificar();
    const canDel  = puedeEliminar();
    [...p.bitacora]
      .sort((a,b) => (a.horaInicio || '').localeCompare(b.horaInicio || ''))
      .forEach((ev, idx) => {
        const node = el('div', { class: 'est-event', data: { tipo: ev.tipo } });
        const obsLabel = ev.motivo === 'Otros' ? (ev.observaciones || 'Otros') : (ev.motivo || ev.observaciones || '');
        node.innerHTML = `
          <div class="est-event-head">
            <span class="est-event-tipo">${esc(labelTipo[ev.tipo] || ev.tipo)}</span>
            <div style="display:flex;align-items:center;gap:6px">
              <span class="est-event-time">${esc(ev.horaInicio || '—')} → ${esc(ev.horaFin || '—')}</span>
              ${canEdit ? `<button class="est-event-edit" title="Editar" data-idx="${idx}" style="background:none;border:none;cursor:pointer;color:#3b82f6;font-size:13px;padding:0 2px">✎</button>` : ''}
              ${canDel ? `<button class="est-event-del" title="Eliminar" data-idx="${idx}">×</button>` : ''}
            </div>
          </div>
          <div class="est-event-obs">${esc(obsLabel)}</div>
          <div class="est-event-editform" style="display:none;margin-top:8px;padding:10px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
              <div>
                <label style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em">Hora inicio</label>
                <input type="time" class="ef-ini" value="${esc(ev.horaInicio || '')}" style="width:100%;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
              </div>
              <div>
                <label style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em">Hora fin</label>
                <input type="time" class="ef-fin" value="${esc(ev.horaFin || '')}" style="width:100%;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
              </div>
            </div>
            <div style="margin-bottom:8px">
              <label style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em">Motivo</label>
              <input type="text" class="ef-motivo" value="${esc(ev.motivo || '')}" style="width:100%;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
            </div>
            <div style="display:flex;gap:8px">
              <button class="ef-save est-btn primary" style="flex:1;font-size:12px;padding:6px">Guardar</button>
              <button class="ef-cancel est-btn" style="font-size:12px;padding:6px">Cancelar</button>
            </div>
          </div>`;

        // Editar
        const editBtn = node.querySelector('.est-event-edit');
        if (editBtn) editBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          const form = node.querySelector('.est-event-editform');
          form.style.display = form.style.display === 'none' ? '' : 'none';
        });
        node.querySelector('.ef-cancel').addEventListener('click', (e) => {
          e.stopPropagation();
          node.querySelector('.est-event-editform').style.display = 'none';
        });
        node.querySelector('.ef-save').addEventListener('click', async (e) => {
          e.stopPropagation();
          const ordered = [...p.bitacora].sort((a, b) => (a.horaInicio || '').localeCompare(b.horaInicio || ''));
          const target = ordered[idx];
          if (!target) return;
          const newIni    = node.querySelector('.ef-ini').value;
          const newFin    = node.querySelector('.ef-fin').value;
          const newMotivo = node.querySelector('.ef-motivo').value.trim();
          if (!newIni) { toast('Hora inicio requerida', 'error'); return; }
          const btn = e.target; btn.disabled = true; btn.textContent = 'Guardando…';
          try {
            if (target.id != null) {
              await apiPost('api/update_evento.php', { id: target.id, horaInicio: newIni, horaFin: newFin, motivo: newMotivo, observaciones: target.observaciones || '' });
            }
            target.horaInicio = newIni; target.horaFin = newFin; target.motivo = newMotivo;
            renderTimeline(); renderGrid(); renderKpis();
            toast('Evento actualizado');
          } catch (err) {
            btn.disabled = false; btn.textContent = 'Guardar';
            toast('No se pudo guardar: ' + err.message, 'error');
          }
        });

        // Eliminar
        const delBtn = node.querySelector('.est-event-del');
        if (delBtn) delBtn.addEventListener('click', async (e) => {
          e.stopPropagation();
          const btn = e.target;
          const ordered = [...p.bitacora].sort((a, b) => (a.horaInicio || '').localeCompare(b.horaInicio || ''));
          const target = ordered[Number(btn.dataset.idx)];
          if (!target) return;
          btn.disabled = true;
          try {
            if (target.id != null) await apiPost('api/delete_evento.php', { id: target.id });
            const realIdx = p.bitacora.indexOf(target);
            if (realIdx >= 0) p.bitacora.splice(realIdx, 1);
            renderTimeline(); renderGrid(); renderKpis();
            toast('Evento eliminado');
          } catch (err) {
            btn.disabled = false;
            toast('No se pudo eliminar: ' + err.message);
          }
        });
        tl.append(node);
      });
    cont.append(tl);
  }

  // ── Mutadores ──────────────────────────────────────────────────────────
  // Registrar cambio explícito de puesto / estado (con motivo). Solo guarda y
  // audita si realmente hubo cambios.
  async function registrarCambio() {
    const p = state.personal.find(x => x.id === state.selectedId);
    if (!p) return;
    const prev  = { funcion: p.funcion, ubicacion: p.ubicacion, naveId: p.naveId || null, horario: p.horario || '', estado: p.estado, radio: p.radio ? 1 : 0 };
    // Si la ubicación nueva no es un muelle, la nave se limpia: mantenerla
    // dejaría a alguien en Balanza «atendiendo» una nave.
    const ubicNueva = $('dwUbicacion').value;
    const naveNueva = esUbicacionMuelle(ubicNueva) && $('dwNave').value ? Number($('dwNave').value) : null;
    const nuevo = { funcion: $('dwFuncion').value, ubicacion: ubicNueva, naveId: naveNueva, horario: ($('dwHorario') ? $('dwHorario').value : (p.horario || '')), estado: $('dwEstado').value || 'activo', radio: $('dwRadio').value === '1' ? 1 : 0 };
    // traslado/refrigerio/permiso son eventos de bitácora — el estado siempre queda activo
    const tipoEvento = nuevo.estado;
    // Un colaborador solo puede tener UN refrigerio por turno.
    if (tipoEvento === 'refrigerio' && (p.bitacora || []).some(ev => ev.tipo === 'refrigerio')) {
      toast('Este colaborador ya tiene un refrigerio registrado en el turno', 'error');
      return;
    }
    if (['traslado', 'refrigerio', 'permiso'].includes(tipoEvento)) nuevo.estado = 'activo';
    if (['traslado','permiso'].includes(tipoEvento)) {
      if (!$('evIni').value) { toast('Hora inicio requerida para ' + (tipoEvento === 'traslado' ? 'traslado' : 'permiso'), 'error'); return; }
      if (!$('evFin').value) { toast('Hora fin requerida para ' + (tipoEvento === 'traslado' ? 'traslado' : 'permiso'), 'error'); return; }
    }
    const hayHora = ['traslado','refrigerio','permiso'].includes(tipoEvento) && (($('evIni') || {}).value || '');
    const sinCambios = prev.funcion === nuevo.funcion && (prev.ubicacion || '') === (nuevo.ubicacion || '') && (prev.horario || '') === (nuevo.horario || '') && prev.estado === nuevo.estado && prev.radio === nuevo.radio;
    if (sinCambios && !hayHora) { toast('No hay cambios que registrar'); return; }
    // Si solo hay hora nueva (sin cambio de puesto), registrar solo el evento de bitácora
    if (sinCambios && hayHora) {
      const ini = $('evIni').value, fin = ($('evFin') || {}).value || '';
      const motivoSel = ($('evMotivo') || {}).value || '';
      const obs = ($('evObs') || {}).value || '';
      const motivoTxt = motivoRequiereTexto(motivoSel) ? obs : motivoSel;
      const btn = $('dwRegistrar'); btn.disabled = true;
      try {
        const r = await apiPost('api/add_evento.php', { tpId: p.tpId, tipo: tipoEvento, horaInicio: ini, horaFin: fin, motivo: motivoSel, observaciones: obs });
        p.bitacora.push({ id: r.id, tipo: tipoEvento, motivo: motivoSel, horaInicio: ini, horaFin: fin, observaciones: obs });
        if (motivoSel === 'Otros' && obs.trim()) agregarMotivoCustom(tipoEvento, obs.trim());
        $('evIni').value = ''; $('evFin').value = ''; if ($('evObs')) $('evObs').value = '';
        if (['traslado', 'refrigerio', 'permiso'].includes(tipoEvento)) { $('dwEstado').value = ''; $('dwHoraWrap').style.display = 'none'; }
        renderTimeline(); renderKpis();
        pushAccion('evento', `${labelEstado[tipoEvento]} ${ini}${fin ? '–'+fin : ''}${motivoTxt ? ' · '+motivoTxt : ''}`, p.id, p.nombre);
        toast('Evento registrado en bitácora · ' + p.nombre);
      } catch (e) { toast('No se pudo registrar: ' + e.message); }
      finally { btn.disabled = false; }
      return;
    }
    const btn = $('dwRegistrar'); btn.disabled = true;
    Object.assign(p, nuevo);
    state.notified.forEach(k => { if (k.startsWith(p.id + ':')) state.notified.delete(k); });
    renderGrid(); renderKpis(); renderDrawerAlert(p);
    try {
      await apiPost('api/update_asignacion.php', {
        tpId: p.tpId, funcion: p.funcion, ubicacion: p.ubicacion, naveId: p.naveId,
        horario: p.horario, estado: p.estado, radio: p.radio
      });
      // El nombre visible se refresca desde el catálogo ya cargado, para no
      // pedir otra vez la lista de naves sólo por mostrar la etiqueta.
      const nv = p.naveId ? state.naves.find(n => Number(n.id) === Number(p.naveId)) : null;
      p.naveNombre = nv ? nv.nombre : null;
      // Detalle para la vista en vivo (el servidor guarda su propia versión).
      const det = [];
      if (prev.funcion !== nuevo.funcion) det.push(`función: ${prev.funcion} → ${nuevo.funcion}`);
      if ((prev.ubicacion || '') !== (nuevo.ubicacion || '')) det.push(`ubicación: ${prev.ubicacion || '—'} → ${nuevo.ubicacion || '—'}`);
      if ((prev.naveId || 0) !== (nuevo.naveId || 0)) {
        const nvAntes = prev.naveId ? state.naves.find(n => Number(n.id) === Number(prev.naveId)) : null;
        det.push(`nave: ${nvAntes ? nvAntes.nombre : '—'} → ${p.naveNombre || '—'}`);
      }
      if ((prev.horario || '') !== (nuevo.horario || '')) det.push(`horario: ${prev.horario || '—'} → ${nuevo.horario || '—'}`);
      if (prev.estado !== nuevo.estado) det.push(`estado: ${labelEstado[prev.estado]} → ${labelEstado[nuevo.estado]}`);
      if (prev.radio !== nuevo.radio) det.push(`radio: ${prev.radio ? 'Sí' : 'No'} → ${nuevo.radio ? 'Sí' : 'No'}`);
      const txt = det.join(' · ');
      const esPuesto = prev.funcion !== nuevo.funcion || (prev.ubicacion || '') !== (nuevo.ubicacion || '') || (prev.horario || '') !== (nuevo.horario || '') || prev.radio !== nuevo.radio;
      pushAccion(esPuesto ? 'cambio' : 'estado', txt, p.id, p.nombre);
      toast('Cambio registrado · ' + p.nombre);
      // Si el tipo de evento requiere hora (traslado/refrigerio/permiso), añadir evento a bitácora
      const ini = ($('evIni') || {}).value || '';
      if (['traslado','refrigerio','permiso'].includes(tipoEvento) && ini) {
        const fin = ($('evFin') || {}).value || '';
        const motivoSel = ($('evMotivo') || {}).value || '';
        const obs = ($('evObs') || {}).value || '';
        const motivoTxt = motivoRequiereTexto(motivoSel) ? obs : motivoSel;
        try {
          const r = await apiPost('api/add_evento.php', {
            tpId: p.tpId, tipo: tipoEvento, horaInicio: ini, horaFin: fin, motivo: motivoSel, observaciones: obs
          });
          p.bitacora.push({ id: r.id, tipo: tipoEvento, motivo: motivoSel, horaInicio: ini, horaFin: fin, observaciones: obs });
          if (motivoSel === 'Otros' && obs.trim()) agregarMotivoCustom(tipoEvento, obs.trim());
          $('evIni').value = ''; $('evFin').value = ''; if ($('evObs')) $('evObs').value = '';
          if (['traslado', 'refrigerio', 'permiso'].includes(tipoEvento)) { $('dwEstado').value = ''; $('dwHoraWrap').style.display = 'none'; }
          renderTimeline(); renderKpis();
          pushAccion('evento', `${labelEstado[tipoEvento]} ${ini}${fin ? '–'+fin : ''}${motivoTxt ? ' · '+motivoTxt : ''}`, p.id, p.nombre);
        } catch (_) { /* no bloquear si el evento falla */ }
      }
    } catch (e) {
      Object.assign(p, prev);
      if (state.selectedId === p.id) { $('dwFuncion').value = p.funcion; $('dwUbicacion').value = p.ubicacion; fillNaveSelect($('dwNave'), $('dwNaveWrap'), p.ubicacion, p.naveId); if ($('dwHorario')) $('dwHorario').value = p.horario || ''; $('dwEstado').value = p.estado; $('dwRadio').value = p.radio ? '1' : '0'; }
      renderGrid(); renderKpis(); renderDrawerAlert(p);
      toast('No se pudo guardar: ' + e.message);
    } finally {
      btn.disabled = false;
    }
  }
  async function addEvento() {
    const p = state.personal.find(x => x.id === state.selectedId);
    if (!p) return;
    const tipo = $('dwEstado') ? $('dwEstado').value : 'traslado';
    const ini  = $('evIni').value;
    const fin  = $('evFin').value;
    const motivoSel = $('evMotivo').value;
    const obs  = $('evObs').value.trim();
    const motivoTxt = motivoRequiereTexto(motivoSel) ? obs : motivoSel;
    if (!ini) { toast('Hora de inicio requerida'); return; }
    if (motivoRequiereTexto(motivoSel) && !obs) { toast('Especifica la observación (Otros)'); return; }
    const btn = $('evAdd'); btn.disabled = true;
    try {
      const r = await apiPost('api/add_evento.php', {
        tpId: p.tpId, tipo, horaInicio: ini, horaFin: fin, motivo: motivoSel, observaciones: obs
      });
      p.bitacora.push({ id: r.id, tipo, motivo: motivoSel, horaInicio: ini, horaFin: fin, observaciones: obs });
      $('evIni').value = ''; $('evFin').value = ''; $('evObs').value = '';
      $('evMotivo').value = state.motivos[0] ? state.motivos[0].nombre : '';
      $('evOtrosWrap').style.display = motivoRequiereTexto($('evMotivo').value) ? '' : 'none';
      renderTimeline(); renderGrid(); renderKpis(); renderDrawerAlert(p);
      pushAccion('evento', `${labelTipo[tipo] || tipo} ${ini}${fin ? '–' + fin : ''}${motivoTxt ? ' · ' + motivoTxt : ''}`, p.id, p.nombre);
      toast('Evento añadido a bitácora');
    } catch (e) {
      toast('No se pudo registrar: ' + e.message);
    } finally {
      btn.disabled = false;
    }
  }

  // ── Turno (jornada) · toggle Día/Noche ───────────────────────────────────
  function fmtFecha(iso) {
    // "2026-05-29" → "29/05/2026"
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso || ''));
    return m ? `${m[3]}/${m[2]}/${m[1]}` : (iso || '—');
  }
  // Rellena un <select> de jornadas con el catálogo activo.
  function fillJornadaSelect(sel) {
    if (!sel) return;
    sel.innerHTML = state.jornadas.map(j =>
      `<option value="${j.id}">${esc(j.nombre)}</option>`
    ).join('');
    if (state.jornadaId != null) sel.value = String(state.jornadaId);
  }
  // Refleja fecha y jornada activas en el selector del tablero y en el del modal.
  function syncJornadaUI() {
    const dateInp = $('estTurnoFechaInput');
    if (dateInp && state.turnoFecha) dateInp.value = state.turnoFecha;
    fillJornadaSelect($('estTurnoJornadaSel'));
    fillJornadaSelect($('addJornadaSel'));
    if ($('addJornadaFecha')) $('addJornadaFecha').textContent = fmtFecha(state.turnoFecha);
  }
  // Recarga el turno para (fecha + jornadaId) desde el backend. Si el modal de
  // añadir está abierto, recalcula candidatos para el nuevo turno.
  async function reloadTurno(fecha, jornadaId) {
    const f  = fecha || state.turnoFecha;
    const jid = jornadaId || state.jornadaId;
    if (f === state.turnoFecha && Number(jid) === Number(state.jornadaId)) return;
    await window.cargarTurnoActual(f, jid);   // fetch + boot() → re-render
    if ($('addModal').classList.contains('open')) recomputeAddCandidatos();
  }

  // ── Estado del turno (abierto/cerrado) + cierre/validación ───────────────
  function renderTurnoEstado() {
    const cerrado = state.turnoEstado === 'cerrado';
    const badge = $('estTurnoEstado'), btn = $('estCerrarBtn');
    if (badge) badge.style.display = cerrado ? '' : 'none';
    if (btn) {
      if (cerrado) {
        // Reabrir un turno cerrado: SOLO Administrador o Supervisor.
        btn.style.display = state.puedeValidar ? '' : 'none';
        btn.textContent = 'Reabrir turno';
        btn.title = 'Reabrir el turno (solo Administrador o Supervisor)';
      } else {
        // Cerrar: Administrador, Supervisor o Coordinador.
        btn.style.display = state.puedeCerrar ? '' : 'none';
        btn.textContent = 'Cerrar turno';
        btn.title = 'Cerrar / validar el turno';
      }
    }
    // Bloquear operaciones si el turno está cerrado o el rol no tiene permiso.
    const mod = puedeModificar();
    const add = $('estAddBtn');
    if (add) {
      add.disabled = cerrado || !mod;
      add.title = cerrado ? 'Turno cerrado' : (!mod ? 'Solo Admin/Supervisor pueden modificar turnos anteriores' : 'Colocar un colaborador del catálogo en este turno');
    }
    const refM = $('estRefMasivoBtn');
    if (refM) {
      refM.disabled = cerrado || !mod;
      refM.title = !mod ? 'Solo Admin/Supervisor pueden modificar turnos anteriores' : 'Registrar refrigerio a todo el personal activo';
    }
    const qm = $('estQuitarMasivoBtn');
    if (qm) {
      qm.style.display = puedeEliminar() ? '' : 'none';
      qm.disabled = cerrado;
      qm.title = cerrado ? 'Turno cerrado' : 'Quitar personal del turno en lote';
    }
  }
  async function toggleCerrarTurno() {
    if (!state.turnoId) return;
    const nuevo = state.turnoEstado === 'cerrado' ? 'abierto' : 'cerrado';
    if (nuevo === 'abierto' && !state.puedeValidar) { toast('Solo un Administrador o Supervisor puede reabrir el turno', 'error'); return; }
    if (nuevo === 'cerrado' && !state.puedeCerrar) { toast('No tienes permiso para cerrar el turno', 'error'); return; }
    if (nuevo === 'cerrado' && !confirm('¿Cerrar/validar este turno? No se podrán registrar más cambios. Solo un Administrador o Supervisor podrá reabrirlo.')) return;
    try {
      await apiPost('api/cerrar_turno.php', { turnoId: state.turnoId, estado: nuevo });
      state.turnoEstado = nuevo;
      var actor = ((window.__ESTIBA_USER && window.__ESTIBA_USER.nombre) || 'Usuario') + ' (' + (state.rol || '—') + ')';
      pushAccion('cierre', (nuevo === 'cerrado' ? 'Turno cerrado / validado por ' : 'Turno reabierto por ') + actor, '', '');
      renderTurnoEstado();
      toast(nuevo === 'cerrado' ? 'Turno cerrado' : 'Turno reabierto');
    } catch (e) { toast('No se pudo: ' + e.message); }
  }

  // ── Ingreso de turno (Coordinador / Supervisor) ──────────────────────────
  function maybeShowIngreso() {
    const rol = state.rol;
    if (rol !== 'Coordinador' && rol !== 'Supervisor') return;
    if (sessionStorage.getItem('est_ingreso_ok') === '1') return;
    if (!state.jornadas.length) return;
    $('ingresoFecha').textContent = fmtFecha(state.turnoFecha) || '—';
    $('ingresoJornadas').innerHTML = state.jornadas.map(j =>
      `<button type="button" data-jid="${j.id}"><span>${esc(j.nombre)}</span></button>`
    ).join('');
    $('ingresoModal').classList.add('open');
    $('ingresoModal').setAttribute('aria-hidden', 'false');
    $('ingresoBack').classList.add('open');
  }
  function closeIngreso() {
    $('ingresoModal').classList.remove('open');
    $('ingresoModal').setAttribute('aria-hidden', 'true');
    $('ingresoBack').classList.remove('open');
  }
  async function elegirIngreso(jid) {
    sessionStorage.setItem('est_ingreso_ok', '1');
    // Recordar la jornada elegida para que Registro tallyman y Relevo de turno
    // abran en el turno del coordinador (no en el turno vigente por hora).
    const jsel = state.jornadas.find(j => Number(j.id) === Number(jid));
    if (jsel && jsel.codigo) sessionStorage.setItem('tm_turno_codigo', jsel.codigo);
    closeIngreso();
    // Sin fecha: el backend la resuelve según la jornada (turno noche en la
    // mañana → día anterior, que es la salida del coordinador).
    await window.cargarTurnoActual(null, jid);
  }

  // ── Acciones / auditoría (panel lateral, vista en vivo) ──────────────────
  const labelAccion = { alta: 'Alta', baja: 'Baja', cambio: 'Cambio de puesto', estado: 'Cambio de estado', evento: 'Evento', evento_borrado: 'Evento eliminado', cierre: 'Cierre' };
  function pushAccion(tipo, detalle, codigo, colaborador) {
    const now = new Date();
    const hora = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    state.acciones.push({ hora, usuario: (window.__ESTIBA_USER && window.__ESTIBA_USER.nombre) || state.rol, rol: state.rol, tipo, codigo, colaborador, detalle });
    renderSideAcciones();
  }
  function renderSideAcciones() {
    const cont = $('sideAcciones'); if (!cont) return;
    $('sideAccCount').textContent = state.acciones.length;
    if (!state.acciones.length) { cont.innerHTML = '<div class="est-empty-acc">Sin movimientos registrados.</div>'; return; }
    cont.innerHTML = [...state.acciones].slice(-8).reverse().map(a => {
      const quien = a.colaborador ? `<b>${esc(a.colaborador)}</b> · ` : '';
      return `<div class="est-acc-item"><span class="est-acc-hora">${esc(a.hora)}</span><span class="est-acc-txt">${quien}${esc(labelAccion[a.tipo] || a.tipo)}: ${esc(a.detalle || '')}</span></div>`;
    }).join('');
  }

  // ── Motivos (selects de cambio y de evento) ──────────────────────────────
  const MOTIVOS_REFRIGERIO = [
    { nombre: 'Almuerzo',    requiereTexto: 0 },
    { nombre: 'Cena',        requiereTexto: 0 },
    { nombre: 'Rancho frio', requiereTexto: 0 },
  ];
  const MOTIVOS_TRASLADO_BASE = [
    { nombre: 'Cambio de Posición', requiereTexto: 0 },
    { nombre: 'Tópico',             requiereTexto: 0 },
    { nombre: 'Otros',              requiereTexto: 1 },
  ];
  const MOTIVO_SALIDA_TEMPRANA = { nombre: 'Salida Temprana', requiereTexto: 1 };
  const EXCLUIR_PERMISO = new Set(['Sin Operaciones en Terminal']);

  // Motivos personalizados ("Otros" guardados) por estado, persistidos en localStorage
  const LS_CUSTOM = 'est_motivos_custom';
  const motivosCustom = (() => {
    try { return JSON.parse(localStorage.getItem(LS_CUSTOM) || '{}'); } catch { return {}; }
  })();
  function saveMotivosCustom() {
    try { localStorage.setItem(LS_CUSTOM, JSON.stringify(motivosCustom)); } catch {}
  }
  function agregarMotivoCustom(estado, nombre) {
    if (!nombre) return;
    if (!motivosCustom[estado]) motivosCustom[estado] = [];
    if (!motivosCustom[estado].includes(nombre)) {
      motivosCustom[estado].push(nombre);
      saveMotivosCustom();
    }
  }

  function motivosPorEstado(estado) {
    if (estado === 'refrigerio') return MOTIVOS_REFRIGERIO;
    if (estado === 'traslado') {
      const custom = (motivosCustom['traslado'] || []).map(n => ({ nombre: n, requiereTexto: 0 }));
      return [...MOTIVOS_TRASLADO_BASE, ...custom.filter(c => !MOTIVOS_TRASLADO_BASE.find(b => b.nombre === c.nombre))];
    }
    if (estado === 'permiso') {
      const base = state.motivos.filter(m => !EXCLUIR_PERMISO.has(m.nombre));
      const withST = base.find(m => m.nombre === 'Salida Temprana') ? base : [...base, MOTIVO_SALIDA_TEMPRANA];
      const custom = (motivosCustom['permiso'] || []).map(n => ({ nombre: n, requiereTexto: 0 }));
      return [...withST, ...custom.filter(c => !withST.find(b => b.nombre === c.nombre))];
    }
    const base = state.motivos.length ? state.motivos : [{ nombre: 'Otros', requiereTexto: 1 }];
    const custom = (motivosCustom[estado] || []).map(n => ({ nombre: n, requiereTexto: 0 }));
    return [...base, ...custom.filter(c => !base.find(b => b.nombre === c.nombre))];
  }
  function fillMotivoSelect(sel, current, estado) {
    if (!sel) return;
    const opts = motivosPorEstado(estado || $('dwEstado') && $('dwEstado').value);
    sel.innerHTML = opts.map(m => `<option value="${esc(m.nombre)}">${esc(m.nombre)}</option>`).join('');
    if (current && opts.find(m => m.nombre === current)) sel.value = current;
    else sel.value = opts[0] ? opts[0].nombre : '';
  }
  function motivoRequiereTexto(nombre) {
    if (nombre === 'Salida Temprana') return true;
    if (nombre === 'Otros') return true;
    const allMotivos = [...state.motivos, ...MOTIVOS_REFRIGERIO, ...MOTIVOS_TRASLADO_BASE];
    const m = allMotivos.find(x => x.nombre === nombre);
    return m ? !!m.requiereTexto : false;
  }

  // ── Añadir / quitar personal del turno ──────────────────────────────────
  const addState = {
    todos: [],           // todos los colaboradores activos (cargados de la API)
    candidatos: [],      // todos minus ya-en-turno
    filtrados: [],       // candidatos filtrados por búsqueda
    seleccionados: new Set(), // Set<colabId number>
    query: ''
  };

  function updateAddSelBar() {
    const n   = addState.seleccionados.size;
    const btn = $('addConfirm');
    $('addSelCount').textContent = n === 0 ? 'Ninguno seleccionado'
      : n === 1 ? '1 seleccionado' : n + ' seleccionados';
    btn.disabled    = n === 0;
    btn.textContent = n === 0 ? '+ Añadir al turno'
      : `+ Añadir ${n} ${n === 1 ? 'persona' : 'personas'} al turno`;
  }

  function recomputeAddCandidatos() {
    const yaEnTurno = new Set(state.personal.map(p => p.colaboradorId));
    addState.candidatos = addState.todos.filter(c => !yaEnTurno.has(Number(c.id)));
    // Quitar de la selección a quien ya entró al turno
    addState.seleccionados.forEach(id => { if (yaEnTurno.has(id)) addState.seleccionados.delete(id); });
    renderAddList();
    updateAddSelBar();
  }

  function fillAddHorario() {
    const sel = $('addHorario'); if (!sel) return;
    const j = state.jornada;
    let opts = [];
    if (j === 'D') {
      opts = ['TURNO DÍA (07:00 - 19:00)', 'TURNO DÍA (08:00 - 21:15)'];
    } else if (j === 'N') {
      opts = ['TURNO NOCHE (19:00 - 07:00)'];
    } else {
      opts = [state.turnoLabel || 'Horario único'];
    }
    sel.innerHTML = opts.map(o => `<option value="${esc(o)}">${esc(o)}</option>`).join('');
  }

  // Horarios disponibles para el drawer (editar jornada de una persona).
  // Antepone el horario actual si no estuviera en el catálogo, para no perderlo.
  function fillDwHorario(current) {
    const sel = $('dwHorario'); if (!sel) return;
    const j = state.jornada;
    let opts = [];
    if (j === 'D') opts = ['TURNO DÍA (07:00 - 19:00)', 'TURNO DÍA (08:00 - 21:15)'];
    else if (j === 'N') opts = ['TURNO NOCHE (19:00 - 07:00)'];
    else opts = [state.turnoLabel || 'Horario único'];
    if (current && !opts.includes(current)) opts = [current, ...opts];
    sel.innerHTML = opts.map(o => `<option value="${esc(o)}"${o === current ? ' selected' : ''}>${esc(o)}</option>`).join('');
  }

  async function openAddPersonal() {
    if (!state.turnoId) { toast('Turno no disponible aún'); return; }
    $('addSearch').value = '';
    addState.query = '';
    addState.seleccionados.clear();
    fillSelect($('addUbicacion'), state.ubicaciones, state.ubicaciones[0] || '');
    fillNaveSelect($('addNave'), $('addNaveWrap'), $('addUbicacion').value, null);
    fillAddHorario();
    updateAddSelBar();
    $('addList').innerHTML = '<div class="est-side-empty">Cargando colaboradores…</div>';
    $('addModal').classList.add('open');
    $('addModal').setAttribute('aria-hidden', 'false');
    $('addBack').classList.add('open');
    syncJornadaUI();
    try {
      const res  = await fetch('api/get_colaboradores.php?turnoId=' + state.turnoId, { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'No se pudo cargar');
      addState.todos = (data.data || []).filter(c => c.activo === 1);
      recomputeAddCandidatos();
    } catch (e) {
      $('addList').innerHTML = `<div class="est-side-empty">Error: ${esc(e.message)}</div>`;
    }
  }

  function closeAddPersonal() {
    $('addModal').classList.remove('open');
    $('addModal').setAttribute('aria-hidden', 'true');
    $('addBack').classList.remove('open');
    addState.seleccionados.clear();
    updateAddSelBar();
  }

  function renderAddList() {
    const q = addState.query.trim().toLowerCase();
    addState.filtrados = addState.candidatos
      .filter(c => !q || [c.nombre, c.codigo, c.funcion_principal, c.cuadrilla]
        .some(v => String(v ?? '').toLowerCase().includes(q)))
      // Disponibles primero (los de turno adyacente al final), y alfabético por nombre.
      .sort((a, b) => (a.conflicto ? 1 : 0) - (b.conflicto ? 1 : 0)
        || String(a.nombre || '').localeCompare(String(b.nombre || ''), 'es', { sensitivity: 'base' }));
    const cont = $('addList');
    if (!addState.candidatos.length) {
      cont.innerHTML = '<div class="est-side-empty">Todos los colaboradores activos ya están en el turno.</div>';
      return;
    }
    if (!addState.filtrados.length) {
      cont.innerHTML = '<div class="est-side-empty">Sin resultados para esta búsqueda.</div>';
      return;
    }
    cont.innerHTML = addState.filtrados.map(c => {
      const cid     = Number(c.id);
      const sel     = addState.seleccionados.has(cid);
      const blocked = !!c.conflicto;
      const chk     = sel
        ? `<svg width="11" height="11" viewBox="0 0 12 12" fill="none"><polyline points="1.5,6 4.5,9.5 10.5,2" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>`
        : '';
      return `<div class="est-add-row${sel ? ' sel' : ''}${blocked ? ' blocked' : ''}" data-cid="${esc(c.id)}"
            title="${blocked ? 'Ya trabajó el turno adyacente · no puede ser agregado' : ''}">
          <div class="est-add-chk">${chk}</div>
          <div class="est-card-icon">${iconForFuncion(c.funcion_principal)}</div>
          <div class="col">
            <span class="name">${esc(c.nombre)}</span>
            <span class="meta">${esc(c.codigo)} · ${esc(c.funcion_principal)} · Team ${esc(c.cuadrilla)}</span>
          </div>
          ${blocked ? '<span class="est-add-conflict-badge">Turno adyacente</span>' : ''}
        </div>`;
    }).join('');
  }

  function toggleCandidate(cid) {
    const c = addState.candidatos.find(x => Number(x.id) === Number(cid));
    if (!c || c.conflicto) return;
    const id = Number(c.id);
    if (addState.seleccionados.has(id)) addState.seleccionados.delete(id);
    else addState.seleccionados.add(id);
    renderAddList();
    updateAddSelBar();
  }

  async function confirmAddPersonal() {
    if (!addState.seleccionados.size) { toast('Elige al menos un colaborador'); return; }
    const ubicacion    = $('addUbicacion').value;
    const naveId       = esUbicacionMuelle(ubicacion) && $('addNave').value ? Number($('addNave').value) : null;
    const horario      = $('addHorario') ? $('addHorario').value : '';
    const colaboradores = [...addState.seleccionados].map(id => {
      const c = addState.candidatos.find(x => Number(x.id) === id);
      return { colaboradorId: id, funcion: c ? (c.funcion_principal || state.funciones[0] || '') : '' };
    }).filter(x => x.funcion);
    if (!colaboradores.length) { toast('Sin función asignada'); return; }
    const btn = $('addConfirm'); btn.disabled = true;
    try {
      const r = await apiPost('api/add_personal_turno_masivo.php', {
        turnoId: state.turnoId, ubicacion, naveId, horario, colaboradores
      });
      if (r.agregados && r.agregados.length) {
        r.agregados.forEach(p => {
          state.personal.push({ ...p, bitacora: [] });
          pushAccion('alta', `Agregado al turno · ${p.funcion}${ubicacion ? ' @ ' + ubicacion : ''}`
            + (p.naveNombre ? ' · nave ' + p.naveNombre : ''), p.id, p.nombre);
        });
        renderGrid(); renderKpis();
        const n = r.agregados.length;
        toast(`${n} ${n === 1 ? 'persona agregada' : 'personas agregadas'} al turno`);
      }
      if (r.errores && r.errores.length) {
        r.errores.forEach(er => toast('⚠ ' + (er.nombre || 'ID ' + er.colaboradorId) + ': ' + er.error));
      }
      addState.seleccionados.clear();
      recomputeAddCandidatos();
    } catch (e) {
      toast('Error: ' + e.message);
    } finally {
      updateAddSelBar();
    }
  }
  async function removePersonal() {
    const p = state.personal.find(x => x.id === state.selectedId);
    if (!p) return;
    if (!confirm(`¿Quitar a ${p.nombre} del turno? Se eliminará también su bitácora de hoy.`)) return;
    const btn = $('dwRemove'); btn.disabled = true;
    try {
      await apiPost('api/remove_personal_turno.php', { tpId: p.tpId });
      state.personal = state.personal.filter(x => x.tpId !== p.tpId);
      state.notified.forEach(k => { if (k.startsWith(p.id + ':')) state.notified.delete(k); });
      pushAccion('baja', 'Quitado del turno', p.id, p.nombre);
      btn.disabled = false;
      closeDrawer();
      renderGrid(); renderKpis();
      toast('Quitado del turno · ' + p.nombre);
    } catch (e) {
      btn.disabled = false;
      toast('No se pudo quitar: ' + e.message);
    }
  }

  // ── Export ─────────────────────────────────────────────────────────────
  function buildSnapshot() {
    return {
      generadoEn: new Date().toISOString(),
      turno: state.turnoLabel,
      fecha: state.turnoFecha,
      jornada: state.jornada,
      resumen: {
        total:       state.personal.length,
        activos:     state.personal.filter(p => p.estado === 'activo').length,
        refrigerio:  state.personal.filter(p => p.estado === 'refrigerio').length,
        incidencias: state.personal.filter(p => p.estado === 'incidencia').length
      },
      personal: state.personal.map(p => {
        const al = computeAlert(p);
        const st = computeStats(p);
        return {
          id: p.id, nombre: p.nombre,
          funcionActual: p.funcion, ubicacionActual: p.ubicacion, estado: p.estado,
          alerta: al ? { nivel: al.level, minutos: al.mins, limite: al.limit } : null,
          totalEventos: p.bitacora.length,
          conteoEventos: st.counts,
          minutosPorTipo: st.minutos,
          minutosActivo: st.minActivo,
          minutosEnEstado: getDurationInState(p),
          bitacora: p.bitacora.map(e => ({ ...e, duracionMin: evDurMin(e) }))
        };
      })
    };
  }

  function exportPDF() {
    if (!window.jspdf || !window.jspdf.jsPDF) {
      toast('No se cargó la librería de PDF. Revisa tu conexión a internet.');
      return;
    }
    const { jsPDF } = window.jspdf;
    const doc  = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'landscape' });
    const W    = doc.internal.pageSize.getWidth();
    const snap = buildSnapshot();
    const fecha = new Date().toLocaleString('es-PE', { dateStyle: 'medium', timeStyle: 'short' });

    // ── Encabezado ──
    doc.setFillColor(0, 43, 92);
    doc.rect(0, 0, W, 70, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold'); doc.setFontSize(16);
    doc.text('Parte de Turno · Estiba', 32, 32);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(10);
    doc.text(`Turno: ${snap.turno || '—'}`, 32, 50);
    doc.setTextColor(255, 255, 255); doc.setFontSize(9);
    doc.text(`Generado: ${fecha}`, W - 32, 50, { align: 'right' });

    // ── Resumen global ──
    doc.setTextColor(11, 31, 58);
    let y = 90;
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11);
    doc.text('Resumen', 32, y);
    y += 6;
    doc.autoTable({
      startY: y,
      head: [['En turno', 'Activos', 'Refrigerio', 'Inactivos']],
      body: [[
        snap.resumen.total,
        snap.resumen.activos,
        snap.resumen.refrigerio,
        snap.resumen.incidencias
      ]],
      theme: 'grid',
      headStyles: { fillColor: [0, 43, 92], textColor: 255, fontStyle: 'bold', fontSize: 9 },
      bodyStyles: { fontSize: 12, halign: 'center', fontStyle: 'bold' },
      margin: { left: 32, right: 32 }
    });

    // ── Conteo por persona ──
    y = doc.lastAutoTable.finalY + 18;
    doc.setFont('helvetica', 'bold'); doc.setFontSize(11);
    doc.text('Conteo por persona', 32, y);
    y += 6;
    doc.autoTable({
      startY: y,
      head: [['#', 'ID', 'Colaborador', 'Función', 'Ubicación', 'Estado',
              'Traslados', 'Refrigerios', 'Permisos',
              'Min. activo', 'Min. refri', 'Min. permiso',
              'Min. en estado', 'Alerta']],
      body: snap.personal.map((p, i) => [
        i + 1, p.id, p.nombre, p.funcionActual, p.ubicacionActual,
        labelEstado[p.estado] || p.estado,
        p.conteoEventos.traslado,
        p.conteoEventos.refrigerio,
        p.conteoEventos.permiso,
        fmtMin(p.minutosActivo),
        fmtMin(p.minutosPorTipo.refrigerio),
        fmtMin(p.minutosPorTipo.permiso),
        fmtMin(p.minutosEnEstado),
        p.alerta ? `${p.alerta.nivel === 'over' ? '+' : ''}${p.alerta.minutos}m ${p.alerta.nivel === 'over' ? '(excedido)' : '(por vencer)'}` : '—'
      ]),
      theme: 'striped',
      headStyles: { fillColor: [0, 43, 92], textColor: 255, fontStyle: 'bold', fontSize: 8 },
      bodyStyles: { fontSize: 8, valign: 'middle' },
      columnStyles: {
        0: { halign: 'center', cellWidth: 22 },
        1: { fontStyle: 'bold', cellWidth: 48 },
        6: { halign: 'center' }, 7: { halign: 'center' }, 8: { halign: 'center' },
        9: { halign: 'right' }, 10: { halign: 'right' }, 11: { halign: 'right' }, 12: { halign: 'right' }
      },
      alternateRowStyles: { fillColor: [248, 250, 252] },
      margin: { left: 32, right: 32 }
    });

    // ── Bitácora detallada por persona ──
    doc.addPage();
    doc.setFontSize(14); doc.setFont('helvetica', 'bold');
    doc.setTextColor(0, 43, 92);
    doc.text('Bitácora detallada · ' + (snap.turno || ''), 32, 40);

    let cursorY = 60;
    snap.personal.forEach(p => {
      if (cursorY > doc.internal.pageSize.getHeight() - 80) {
        doc.addPage(); cursorY = 40;
      }
      doc.setFillColor(241, 245, 249);
      doc.rect(32, cursorY, W - 64, 22, 'F');
      doc.setFontSize(10); doc.setTextColor(11, 31, 58); doc.setFont('helvetica', 'bold');
      doc.text(`${p.nombre}  ·  ${p.id}  ·  ${p.funcionActual} @ ${p.ubicacionActual}`, 40, cursorY + 15);
      doc.setFontSize(8); doc.setFont('helvetica', 'normal');
      doc.setTextColor(100, 116, 139);
      doc.text(`Estado: ${labelEstado[p.estado] || p.estado}  ·  Eventos: ${p.totalEventos}`, W - 40, cursorY + 15, { align: 'right' });
      cursorY += 26;

      if (!p.bitacora.length) {
        doc.setFontSize(9); doc.setTextColor(148, 163, 184); doc.setFont('helvetica', 'italic');
        doc.text('Sin eventos registrados.', 44, cursorY + 4);
        cursorY += 18;
      } else {
        doc.autoTable({
          startY: cursorY,
          head: [['Tipo', 'Inicio', 'Fin', 'Duración', 'Observaciones']],
          body: p.bitacora.map(ev => [
            labelTipo[ev.tipo] || ev.tipo,
            ev.horaInicio || '—',
            ev.horaFin || '—',
            fmtMin(ev.duracionMin),
            ev.observaciones || ''
          ]),
          theme: 'plain',
          headStyles: { fillColor: [233, 238, 244], textColor: [0, 43, 92], fontStyle: 'bold', fontSize: 8 },
          bodyStyles: { fontSize: 8, valign: 'top' },
          columnStyles: {
            0: { cellWidth: 70, fontStyle: 'bold' },
            1: { cellWidth: 50, halign: 'center' },
            2: { cellWidth: 50, halign: 'center' },
            3: { cellWidth: 60, halign: 'right' }
          },
          margin: { left: 40, right: 40 }
        });
        cursorY = doc.lastAutoTable.finalY + 10;
      }
    });

    // ── Pie con paginación ──
    const pages = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pages; i++) {
      doc.setPage(i);
      doc.setFontSize(8); doc.setTextColor(148, 163, 184);
      doc.text(`Página ${i} de ${pages}`, W - 32, doc.internal.pageSize.getHeight() - 12, { align: 'right' });
      doc.text('Shift Command Deck · Estiba', 32, doc.internal.pageSize.getHeight() - 12);
    }

    const stamp = new Date().toISOString().slice(0, 16).replace(/[T:]/g, '-');
    doc.save(`parte_turno_estiba_${stamp}.pdf`);
    toast('PDF generado · ' + snap.personal.length + ' colaboradores');
  }

  // ── Helpers ExcelJS (estilo corporativo) ────────────────────────────────
  const XL = {
    NAVY: 'FF002B5C', NAVY7: 'FF013A78', DECK: 'FFF1F5F9', LINE: 'FFE2E8F0',
    INK: 'FF0B1F3A', WHITE: 'FFFFFFFF', MUTE: 'FF64748B',
    OK: 'FF057A55', WARN: 'FFB54708', ER: 'FFB42318', ALT: 'FFF8FAFC'
  };
  function xlThin() { const s = { style: 'thin', color: { argb: XL.LINE } }; return { top: s, left: s, bottom: s, right: s }; }
  function xlFill(argb) { return { type: 'pattern', pattern: 'solid', fgColor: { argb } }; }
  function colLetter(n) { let s = ''; while (n > 0) { const m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = (n - m - 1) / 26; } return s; }

  // Banda de título corporativa. Devuelve el índice de la primera fila libre.
  function xlTitleBand(ws, nCols, title, lines, logo64) {
    const last = colLetter(nCols);
    ws.mergeCells(`A1:${last}1`);
    const t = ws.getCell('A1');
    t.value = title;
    t.font = { bold: true, size: 16, color: { argb: XL.WHITE } };
    t.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
    t.fill = xlFill(XL.NAVY);
    ws.getRow(1).height = 30;
    let r = 2;
    (lines || []).forEach(line => {
      ws.mergeCells(`A${r}:${last}${r}`);
      const c = ws.getCell(`A${r}`);
      c.value = line;
      c.font = { size: 10, color: { argb: XL.MUTE } };
      c.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
      ws.getRow(r).height = 16;
      r++;
    });
    if (logo64) {
      try {
        const imgId = ws.workbook.addImage({ base64: logo64, extension: 'png' });
        ws.addImage(imgId, { tl: { col: nCols - 2.0, row: 0.15 }, ext: { width: 116, height: 30 } });
      } catch (e) { /* logo opcional */ }
    }
    return r + 1; // deja una fila en blanco
  }
  // Encabezado de sección (subtítulo en barra clara).
  function xlSection(ws, rowIdx, nCols, texto) {
    const last = colLetter(nCols);
    ws.mergeCells(`A${rowIdx}:${last}${rowIdx}`);
    const c = ws.getCell(`A${rowIdx}`);
    c.value = texto;
    c.font = { bold: true, size: 11, color: { argb: XL.NAVY } };
    c.fill = xlFill(XL.DECK);
    c.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
    ws.getRow(rowIdx).height = 20;
    return rowIdx + 1;
  }
  // Estiliza una fila como encabezado de tabla.
  function xlHeaderRow(row, fill) {
    row.eachCell(c => {
      c.fill = xlFill(fill || XL.NAVY);
      c.font = { bold: true, size: 9.5, color: { argb: XL.WHITE } };
      c.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
      c.border = xlThin();
    });
    row.height = 26;
  }
  // Estiliza filas de datos con cebra y bordes.
  function xlBodyRow(row, idx) {
    row.eachCell(c => {
      c.border = xlThin();
      c.font = c.font || { size: 9.5, color: { argb: XL.INK } };
      if (idx % 2 === 1) c.fill = xlFill(XL.ALT);
      if (!c.alignment) c.alignment = { vertical: 'middle' };
    });
  }
  async function xlLoadLogo() {
    try {
      const r = await fetch('img/Logo-CSPCP-Andrea-Vigil.png', { cache: 'force-cache' });
      if (!r.ok) return null;
      const b = await r.blob();
      return await new Promise(res => {
        const fr = new FileReader();
        fr.onload = () => res(fr.result);
        fr.onerror = () => res(null);
        fr.readAsDataURL(b);
      });
    } catch (e) { return null; }
  }
  async function xlDownload(wb, filename) {
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; document.body.appendChild(a); a.click();
    a.remove(); setTimeout(() => URL.revokeObjectURL(url), 1500);
  }
  function xlStamp() { return new Date().toISOString().slice(0, 16).replace(/[T:]/g, '-'); }
  function nowLabel() { return new Date().toLocaleString('es-PE', { dateStyle: 'medium', timeStyle: 'short' }); }

  // ── Fase 2 · Excel rico del turno actual ─────────────────────────────────
  async function exportExcel() {
    if (!window.ExcelJS) { toast('No se cargó ExcelJS. Revisa tu conexión a internet.'); return; }
    const snap = buildSnapshot();
    const fechaTxt = snap.fecha ? fmtFecha(snap.fecha) : '—';
    const sub = [`Turno: ${snap.turno || '—'}`, `Fecha: ${fechaTxt}`, `Generado: ${nowLabel()}`];
    const wb = new ExcelJS.Workbook();
    wb.creator = 'EstibaDeck · Shift Command';
    const logo = await xlLoadLogo();

    sheetCierre(wb, snap, sub, logo);
    sheetIndicadores(wb, snap, sub, logo);
    sheetConteo(wb, snap, sub, logo);
    sheetBitacora(wb, snap, sub, logo);
    sheetCambios(wb, sub, logo);
    sheetAuditoria(wb, sub, logo);

    await xlDownload(wb, `parte_turno_${(snap.jornada || 'turno')}_${snap.fecha || ''}_${xlStamp()}.xlsx`);
    toast('Excel generado · 6 hojas');
  }

  function sheetCierre(wb, snap, sub, logo) {
    const ws = wb.addWorksheet('Cierre', { views: [{ showGridLines: false }] });
    ws.columns = [{ width: 4 }, { width: 22 }, { width: 26 }, { width: 20 }, { width: 20 }, { width: 22 }];
    let r = xlTitleBand(ws, 6, 'Parte / Cierre de Turno · Estiba', sub, logo);

    r = xlSection(ws, r, 6, 'Resumen del turno');
    const hRow = ws.getRow(r);
    ['En turno', 'Activos', 'Refrigerio', 'Incidencias', 'Alertas tiempo'].forEach((h, i) => hRow.getCell(i + 2).value = h);
    xlHeaderRow(hRow); r++;
    const vRow = ws.getRow(r);
    const alertas = snap.personal.filter(p => p.alerta).length;
    [snap.resumen.total, snap.resumen.activos, snap.resumen.refrigerio, snap.resumen.incidencias, alertas]
      .forEach((v, i) => { const c = vRow.getCell(i + 2); c.value = v; c.alignment = { horizontal: 'center' }; c.font = { bold: true, size: 13, color: { argb: XL.NAVY } }; c.border = xlThin(); });
    vRow.height = 24; r += 2;

    r = xlSection(ws, r, 6, 'Incidencias del turno');
    const incs = snap.personal.filter(p => p.estado === 'incidencia');
    if (!incs.length) {
      ws.mergeCells(`B${r}:F${r}`);
      const c = ws.getCell(`B${r}`); c.value = 'Sin incidencias registradas.'; c.font = { italic: true, color: { argb: XL.MUTE } }; r += 2;
    } else {
      const ih = ws.getRow(r);
      ['Código', 'Colaborador', 'Función', 'Ubicación', 'Última observación'].forEach((h, i) => ih.getCell(i + 2).value = h);
      xlHeaderRow(ih, XL.ER); r++;
      incs.forEach((p, k) => {
        const row = ws.getRow(r);
        const ult = p.bitacora.length ? p.bitacora[p.bitacora.length - 1].observaciones : '';
        [p.id, p.nombre, p.funcionActual, p.ubicacionActual, ult].forEach((v, i) => row.getCell(i + 2).value = v ?? '');
        xlBodyRow(row, k); r++;
      });
      r++;
    }

    r = xlSection(ws, r, 6, 'Cierre / Responsable');
    [['Responsable de turno', ''], ['Cargo', ''], ['Observaciones generales', ''], ['Firma', '']].forEach(([lbl]) => {
      const c = ws.getCell(`B${r}`); c.value = lbl; c.font = { bold: true, color: { argb: XL.MUTE }, size: 10 };
      ws.mergeCells(`C${r}:F${r}`);
      const line = ws.getCell(`C${r}`); line.border = { bottom: { style: 'thin', color: { argb: XL.LINE } } };
      ws.getRow(r).height = 22; r++;
    });
  }

  function sheetIndicadores(wb, snap, sub, logo) {
    const ws = wb.addWorksheet('Indicadores', { views: [{ showGridLines: false }] });
    ws.columns = [{ width: 4 }, { width: 30 }, { width: 16 }, { width: 4 }, { width: 24 }, { width: 12 }];
    let r = xlTitleBand(ws, 6, 'Indicadores del turno', sub, logo);

    const sum = (f) => snap.personal.reduce((a, p) => a + f(p), 0);
    const metr = [
      ['Total personal en turno', snap.resumen.total],
      ['Horas-persona activas', fmtMin(sum(p => p.minutosActivo))],
      ['Tiempo total en refrigerio', fmtMin(sum(p => p.minutosPorTipo.refrigerio))],
      ['Tiempo total en permiso', fmtMin(sum(p => p.minutosPorTipo.permiso))],
      ['Eventos · traslados', sum(p => p.conteoEventos.traslado)],
      ['Eventos · refrigerios', sum(p => p.conteoEventos.refrigerio)],
      ['Eventos · permisos', sum(p => p.conteoEventos.permiso)],
      ['Alertas excedidas', snap.personal.filter(p => p.alerta && p.alerta.nivel === 'over').length],
      ['Alertas por vencer', snap.personal.filter(p => p.alerta && p.alerta.nivel === 'warn').length]
    ];
    r = xlSection(ws, r, 6, 'Métricas');
    metr.forEach((m, k) => {
      const row = ws.getRow(r);
      const a = row.getCell(2); a.value = m[0]; a.font = { size: 10, color: { argb: XL.INK } }; a.border = xlThin();
      const b = row.getCell(3); b.value = m[1]; b.alignment = { horizontal: 'center' }; b.font = { bold: true, color: { argb: XL.NAVY } }; b.border = xlThin();
      if (k % 2 === 1) { a.fill = xlFill(XL.ALT); b.fill = xlFill(XL.ALT); }
      r++;
    });
    r++;

    // Distribuciones (función y ubicación) lado a lado, ambas desde la fila `top`.
    const distr = (key) => {
      const m = new Map();
      snap.personal.forEach(p => { const k = (key === 'f' ? p.funcionActual : p.ubicacionActual) || '—'; m.set(k, (m.get(k) || 0) + 1); });
      return [...m.entries()].sort((a, b) => b[1] - a[1]);
    };
    const miniHead = (rowIdx, c1, c2, lblA, lblB) => {
      const h = ws.getRow(rowIdx);
      h.getCell(c1).value = lblA; h.getCell(c2).value = lblB;
      [h.getCell(c1), h.getCell(c2)].forEach(c => { c.fill = xlFill(XL.NAVY); c.font = { bold: true, size: 9.5, color: { argb: XL.WHITE } }; c.alignment = { horizontal: 'center' }; c.border = xlThin(); });
      h.height = 22;
    };
    const miniTitle = (rowIdx, cA, cB, texto) => {
      ws.mergeCells(`${colLetter(cA)}${rowIdx}:${colLetter(cB)}${rowIdx}`);
      const c = ws.getCell(`${colLetter(cA)}${rowIdx}`);
      c.value = texto; c.font = { bold: true, size: 11, color: { argb: XL.NAVY } }; c.fill = xlFill(XL.DECK); c.alignment = { indent: 1 };
      ws.getRow(rowIdx).height = 20;
    };
    const top = r;
    miniTitle(top, 2, 3, 'Por función'); miniHead(top + 1, 2, 3, 'Función', 'N°');
    distr('f').forEach((e, k) => { const row = ws.getRow(top + 2 + k); const a = row.getCell(2); a.value = e[0]; a.border = xlThin(); const b = row.getCell(3); b.value = e[1]; b.alignment = { horizontal: 'center' }; b.border = xlThin(); if (k % 2 === 1) { a.fill = xlFill(XL.ALT); b.fill = xlFill(XL.ALT); } });
    miniTitle(top, 5, 6, 'Por ubicación'); miniHead(top + 1, 5, 6, 'Ubicación', 'N°');
    distr('u').forEach((e, k) => { const row = ws.getRow(top + 2 + k); const a = row.getCell(5); a.value = e[0]; a.border = xlThin(); const b = row.getCell(6); b.value = e[1]; b.alignment = { horizontal: 'center' }; b.border = xlThin(); if (k % 2 === 1) { a.fill = xlFill(XL.ALT); b.fill = xlFill(XL.ALT); } });
  }

  function sheetConteo(wb, snap, sub, logo) {
    const ws = wb.addWorksheet('Conteo por persona', { views: [{ showGridLines: false }] });
    const heads = ['#', 'Código', 'Colaborador', 'Función', 'Ubicación', 'Estado', 'Trasl.', 'Refrig.', 'Permisos', 'Min. activo', 'Min. refri', 'Min. permiso', 'Min. estado', 'Alerta'];
    const widths = [4, 12, 28, 16, 16, 13, 8, 8, 9, 11, 11, 12, 12, 18];
    ws.columns = widths.map(w => ({ width: w }));
    let r = xlTitleBand(ws, heads.length, 'Conteo por persona', sub, logo);

    const hRow = ws.getRow(r); heads.forEach((h, i) => hRow.getCell(i + 1).value = h); xlHeaderRow(hRow); r++;
    const tot = { tr: 0, re: 0, pe: 0, ma: 0, mr: 0, mp: 0 };
    snap.personal.forEach((p, k) => {
      const row = ws.getRow(r);
      const vals = [k + 1, p.id, p.nombre, p.funcionActual, p.ubicacionActual, labelEstado[p.estado] || p.estado,
        p.conteoEventos.traslado, p.conteoEventos.refrigerio, p.conteoEventos.permiso,
        p.minutosActivo, p.minutosPorTipo.refrigerio, p.minutosPorTipo.permiso, p.minutosEnEstado,
        p.alerta ? `${p.alerta.nivel === 'over' ? '+' : ''}${p.alerta.minutos}m ${p.alerta.nivel === 'over' ? '(excedido)' : '(por vencer)'}` : '—'];
      vals.forEach((v, i) => row.getCell(i + 1).value = v);
      [7, 8, 9, 10, 11, 12, 13].forEach(ci => row.getCell(ci).alignment = { horizontal: 'center' });
      row.getCell(1).alignment = { horizontal: 'center' };
      if (p.alerta) { const ac = row.getCell(14); ac.font = { bold: true, color: { argb: p.alerta.nivel === 'over' ? XL.ER : XL.WARN } }; }
      xlBodyRow(row, k);
      tot.tr += p.conteoEventos.traslado; tot.re += p.conteoEventos.refrigerio; tot.pe += p.conteoEventos.permiso;
      tot.ma += p.minutosActivo; tot.mr += p.minutosPorTipo.refrigerio; tot.mp += p.minutosPorTipo.permiso;
      r++;
    });
    // Fila de totales
    const tr = ws.getRow(r);
    tr.getCell(3).value = 'TOTALES'; tr.getCell(7).value = tot.tr; tr.getCell(8).value = tot.re; tr.getCell(9).value = tot.pe;
    tr.getCell(10).value = fmtMin(tot.ma); tr.getCell(11).value = fmtMin(tot.mr); tr.getCell(12).value = fmtMin(tot.mp);
    tr.eachCell(c => { c.fill = xlFill(XL.DECK); c.font = { bold: true, color: { argb: XL.NAVY } }; c.border = xlThin(); c.alignment = { horizontal: 'center' }; });
    tr.getCell(3).alignment = { horizontal: 'left' };
    ws.autoFilter = { from: { row: r - snap.personal.length - 1, column: 1 }, to: { row: r - 1, column: heads.length } };
  }

  function sheetBitacora(wb, snap, sub, logo) {
    const ws = wb.addWorksheet('Bitácora', { views: [{ showGridLines: false }] });
    const heads = ['Código', 'Colaborador', 'Función', 'Ubicación', 'Estado', 'Tipo', 'Inicio', 'Fin', 'Dur. (min)', 'Motivo / Observación'];
    const widths = [12, 26, 15, 16, 12, 12, 9, 9, 11, 40];
    ws.columns = widths.map(w => ({ width: w }));
    let r = xlTitleBand(ws, heads.length, 'Bitácora completa del turno', sub, logo);
    const hRow = ws.getRow(r); heads.forEach((h, i) => hRow.getCell(i + 1).value = h); xlHeaderRow(hRow); r++;
    let k = 0;
    const motivoTxt = (ev) => ev.motivo === 'Otros' ? (ev.observaciones || 'Otros') : (ev.motivo || ev.observaciones || '');
    snap.personal.forEach(p => {
      const base = [p.id, p.nombre, p.funcionActual, p.ubicacionActual, labelEstado[p.estado] || p.estado];
      if (!p.bitacora.length) {
        const row = ws.getRow(r);
        [...base, '', '', '', '', '(sin eventos)'].forEach((v, i) => row.getCell(i + 1).value = v);
        row.getCell(10).font = { italic: true, color: { argb: XL.MUTE } };
        xlBodyRow(row, k++); r++;
      } else {
        p.bitacora.forEach(ev => {
          const row = ws.getRow(r);
          [...base, labelTipo[ev.tipo] || ev.tipo, ev.horaInicio || '—', ev.horaFin || '—', ev.duracionMin, motivoTxt(ev)]
            .forEach((v, i) => row.getCell(i + 1).value = v);
          [7, 8, 9].forEach(ci => row.getCell(ci).alignment = { horizontal: 'center' });
          xlBodyRow(row, k++); r++;
        });
      }
    });
  }

  // Tipos de acción que corresponden a un "cambio de puesto / estado".
  // Se usan para SEPARAR ese registro del de la bitácora (eventos) en el Excel.
  const CAMBIO_TIPOS = ['cambio', 'estado'];

  // Renderiza una hoja de acciones del turno actual filtrando state.acciones.
  function _sheetAccionesTurno(wb, sub, logo, sheetName, titulo, filtro, vacioMsg) {
    const ws = wb.addWorksheet(sheetName, { views: [{ showGridLines: false }] });
    const heads = ['Hora', 'Usuario', 'Rol', 'Acción', 'Código', 'Colaborador', 'Detalle'];
    const widths = [8, 24, 14, 18, 12, 26, 50];
    ws.columns = widths.map(w => ({ width: w }));
    let r = xlTitleBand(ws, heads.length, titulo, sub, logo);
    const hRow = ws.getRow(r); heads.forEach((h, i) => hRow.getCell(i + 1).value = h); xlHeaderRow(hRow); r++;
    const filas = state.acciones.filter(filtro);
    if (!filas.length) {
      ws.mergeCells(`A${r}:G${r}`);
      const c = ws.getCell(`A${r}`); c.value = vacioMsg; c.font = { italic: true, color: { argb: XL.MUTE } };
      return;
    }
    filas.forEach((a, k) => {
      const row = ws.getRow(r);
      [a.hora, a.usuario || '', a.rol || '', labelAccion[a.tipo] || a.tipo, a.codigo || '', a.colaborador || '', a.detalle || '']
        .forEach((v, i) => row.getCell(i + 1).value = v);
      row.getCell(1).alignment = { horizontal: 'center' };
      xlBodyRow(row, k); r++;
    });
  }
  // Hoja "Cambios de puesto / estado": SOLO cambios de asignación o estado.
  function sheetCambios(wb, sub, logo) {
    _sheetAccionesTurno(wb, sub, logo, 'Cambios puesto-estado', 'Cambios de puesto / estado',
      a => CAMBIO_TIPOS.includes(a.tipo), 'Sin cambios de puesto o estado en el turno.');
  }
  // Hoja "Auditoría": el resto (altas, bajas, cierre, eventos borrados…).
  // NO incluye eventos de bitácora (están en su hoja) ni los cambios de puesto.
  function sheetAuditoria(wb, sub, logo) {
    _sheetAccionesTurno(wb, sub, logo, 'Auditoría', 'Auditoría del turno (altas, bajas, cierre)',
      a => !CAMBIO_TIPOS.includes(a.tipo) && a.tipo !== 'evento', 'Sin otros movimientos en el turno.');
  }

  // ── Fase 3 · Reporte consolidado por rango de fechas ─────────────────────
  function isoDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }
  function openRangoReport() {
    const hoy = new Date();
    const desde = new Date(hoy); desde.setDate(hoy.getDate() - 6);
    $('rangoHasta').value = state.turnoFecha || isoDate(hoy);
    $('rangoDesde').value = isoDate(desde);
    $('rangoHint').textContent = '';
    $('rangoHint').classList.remove('err');
    $('rangoModal').classList.add('open');
    $('rangoModal').setAttribute('aria-hidden', 'false');
    $('rangoBack').classList.add('open');
  }
  function closeRangoReport() {
    $('rangoModal').classList.remove('open');
    $('rangoModal').setAttribute('aria-hidden', 'true');
    $('rangoBack').classList.remove('open');
  }
  async function generarReporteRango() {
    if (!window.ExcelJS) { toast('No se cargó ExcelJS. Revisa tu conexión a internet.'); return; }
    const desde = $('rangoDesde').value, hasta = $('rangoHasta').value;
    const hint = $('rangoHint');
    if (!desde || !hasta) { hint.textContent = 'Indica ambas fechas.'; hint.classList.add('err'); return; }
    const btn = $('rangoGenerar'); btn.disabled = true;
    hint.classList.remove('err'); hint.textContent = 'Generando…';
    try {
      const res = await fetch(`api/get_reporte_rango.php?desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}`, { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'No se pudo generar');
      if (!data.turnos.length) { hint.textContent = 'No hay turnos guardados en ese rango.'; return; }
      await buildRangeWorkbook(data);
      hint.textContent = `${data.turnos.length} turno(s) · ${data.bitacora.length} evento(s).`;
      toast('Reporte consolidado generado');
    } catch (e) {
      hint.textContent = 'Error: ' + e.message; hint.classList.add('err');
    } finally {
      btn.disabled = false;
    }
  }

  async function buildRangeWorkbook(data) {
    const wb = new ExcelJS.Workbook();
    wb.creator = 'EstibaDeck · Shift Command';
    const logo = await xlLoadLogo();
    const sub = [`Rango: ${fmtFecha(data.desde)} — ${fmtFecha(data.hasta)}`, `Generado: ${nowLabel()}`];

    // ── Hoja Resumen ──
    const wsR = wb.addWorksheet('Resumen', { views: [{ showGridLines: false }] });
    wsR.columns = [{ width: 4 }, { width: 32 }, { width: 18 }, { width: 4 }, { width: 1 }, { width: 1 }];
    let r = xlTitleBand(wsR, 6, 'Reporte consolidado de turnos · Estiba', sub, logo);
    const dias = new Set(data.turnos.map(t => t.fecha)).size;
    const evTot = data.bitacora.length;
    const evT = data.bitacora.filter(b => b.tipo === 'traslado').length;
    const evR = data.bitacora.filter(b => b.tipo === 'refrigerio').length;
    const evP = data.bitacora.filter(b => b.tipo === 'permiso').length;
    const asign = data.turnos.reduce((a, t) => a + t.totalPersonal, 0);
    r = xlSection(wsR, r, 6, 'Totales del rango');
    [
      ['Turnos en el rango', data.turnos.length],
      ['Días con turnos', dias],
      ['Colaboradores distintos', data.personas.length],
      ['Asignaciones (personal·turno)', asign],
      ['Eventos totales', evTot],
      ['· Traslados', evT], ['· Refrigerios', evR], ['· Permisos', evP]
    ].forEach((m, k) => {
      const row = wsR.getRow(r);
      const a = row.getCell(2); a.value = m[0]; a.border = xlThin(); a.font = { size: 10, color: { argb: XL.INK } };
      const b = row.getCell(3); b.value = m[1]; b.border = xlThin(); b.alignment = { horizontal: 'center' }; b.font = { bold: true, color: { argb: XL.NAVY } };
      if (k % 2 === 1) { a.fill = xlFill(XL.ALT); b.fill = xlFill(XL.ALT); }
      r++;
    });

    // ── Hoja Por turno / día ──
    const wsT = wb.addWorksheet('Por turno', { views: [{ showGridLines: false }] });
    const tHeads = ['Fecha', 'Jornada', 'Horario', 'Personal', 'Activos', 'En refrig.', 'Incid.', 'Ev. traslado', 'Ev. refrig.', 'Ev. permiso'];
    wsT.columns = [12, 12, 14, 10, 9, 11, 9, 13, 12, 12].map(w => ({ width: w }));
    let rt = xlTitleBand(wsT, tHeads.length, 'Detalle por turno', sub, logo);
    const thr = wsT.getRow(rt); tHeads.forEach((h, i) => thr.getCell(i + 1).value = h); xlHeaderRow(thr); rt++;
    data.turnos.forEach((t, k) => {
      const row = wsT.getRow(rt);
      [fmtFecha(t.fecha), t.jornada, `${t.horaInicio}–${t.horaFin}`, t.totalPersonal, t.activos, t.refrigerio, t.incidencia,
        t.eventos.traslado, t.eventos.refrigerio, t.eventos.permiso].forEach((v, i) => row.getCell(i + 1).value = v);
      [4, 5, 6, 7, 8, 9, 10].forEach(ci => row.getCell(ci).alignment = { horizontal: 'center' });
      xlBodyRow(row, k); rt++;
    });

    // ── Hoja Por persona ──
    const wsP = wb.addWorksheet('Por persona', { views: [{ showGridLines: false }] });
    const pHeads = ['Código', 'Colaborador', 'Turnos', 'Traslados', 'Refrigerios', 'Permisos', 'Min. eventos'];
    wsP.columns = [12, 30, 9, 11, 12, 10, 13].map(w => ({ width: w }));
    let rp = xlTitleBand(wsP, pHeads.length, 'Consolidado por colaborador', sub, logo);
    const phr = wsP.getRow(rp); pHeads.forEach((h, i) => phr.getCell(i + 1).value = h); xlHeaderRow(phr); rp++;
    data.personas.forEach((p, k) => {
      const row = wsP.getRow(rp);
      [p.codigo, p.nombre, p.turnos, p.traslado, p.refrigerio, p.permiso, fmtMin(p.minutosEventos)].forEach((v, i) => row.getCell(i + 1).value = v);
      [3, 4, 5, 6, 7].forEach(ci => row.getCell(ci).alignment = { horizontal: 'center' });
      xlBodyRow(row, k); rp++;
    });

    // ── Hoja Bitácora consolidada ──
    const wsB = wb.addWorksheet('Bitácora', { views: [{ showGridLines: false }] });
    const bHeads = ['Fecha', 'Jornada', 'Código', 'Colaborador', 'Función', 'Ubicación', 'Tipo', 'Inicio', 'Fin', 'Dur. (min)', 'Observaciones'];
    wsB.columns = [12, 11, 12, 26, 15, 15, 11, 8, 8, 11, 44].map(w => ({ width: w }));
    let rb = xlTitleBand(wsB, bHeads.length, 'Bitácora consolidada', sub, logo);
    const bhr = wsB.getRow(rb); bHeads.forEach((h, i) => bhr.getCell(i + 1).value = h); xlHeaderRow(bhr); rb++;
    data.bitacora.forEach((b, k) => {
      const row = wsB.getRow(rb);
      [fmtFecha(b.fecha), b.jornada, b.codigo, b.nombre, b.funcion, b.ubicacion,
        labelTipo[b.tipo] || b.tipo, b.horaInicio || '—', b.horaFin || '—', b.duracionMin ?? '', b.observaciones || '']
        .forEach((v, i) => row.getCell(i + 1).value = v);
      [8, 9, 10].forEach(ci => row.getCell(ci).alignment = { horizontal: 'center' });
      xlBodyRow(row, k); rb++;
    });

    // ── Hojas separadas: "Cambios de puesto/estado" vs "Auditoría" ──
    // (la bitácora de eventos ya tiene su propia hoja, arriba).
    const acc = data.acciones || [];
    const rangoAccSheet = (sheetName, titulo, filtro, vacioMsg) => {
      const ws = wb.addWorksheet(sheetName, { views: [{ showGridLines: false }] });
      const aHeads = ['Fecha', 'Jornada', 'Hora', 'Usuario', 'Rol', 'Acción', 'Código', 'Colaborador', 'Detalle'];
      ws.columns = [12, 11, 8, 22, 13, 17, 12, 24, 44].map(w => ({ width: w }));
      let ra = xlTitleBand(ws, aHeads.length, titulo, sub, logo);
      const ahr = ws.getRow(ra); aHeads.forEach((h, i) => ahr.getCell(i + 1).value = h); xlHeaderRow(ahr); ra++;
      const filas = acc.filter(filtro);
      if (!filas.length) {
        ws.mergeCells(`A${ra}:I${ra}`);
        const c = ws.getCell(`A${ra}`); c.value = vacioMsg; c.font = { italic: true, color: { argb: XL.MUTE } };
        return;
      }
      filas.forEach((a, k) => {
        const row = ws.getRow(ra);
        [fmtFecha(a.fecha), a.jornada, a.hora, a.usuario || '', a.rol || '', labelAccion[a.tipo] || a.tipo, a.codigo || '', a.colaborador || '', a.detalle || '']
          .forEach((v, i) => row.getCell(i + 1).value = v);
        row.getCell(3).alignment = { horizontal: 'center' };
        xlBodyRow(row, k); ra++;
      });
    };
    rangoAccSheet('Cambios puesto-estado', 'Cambios de puesto / estado',
      a => CAMBIO_TIPOS.includes(a.tipo), 'Sin cambios de puesto o estado en el rango.');
    rangoAccSheet('Auditoría', 'Auditoría (altas, bajas, cierre)',
      a => !CAMBIO_TIPOS.includes(a.tipo) && a.tipo !== 'evento', 'Sin otros movimientos en el rango.');

    await xlDownload(wb, `reporte_estiba_${data.desde}_a_${data.hasta}.xlsx`);
  }

  // ── Wire-up ────────────────────────────────────────────────────────────
  // ════════════════ Select personalizado (.esel) ════════════════
  // Reemplaza visualmente un <select> por un dropdown estilizado (grupos por
  // categoría + puntos de color). El <select> nativo queda oculto y sigue siendo
  // la fuente de verdad: se intercepta su setter `value` y se observan sus
  // <option>, de modo que toda asignación .value y cada fillSelect sincronizan
  // el botón sin tocar el resto del código.
  const ESEL = {
    GROUPS_UBIC: [
      { test: /^muelle/i,                              label: 'Muelles',  color: '#0f4c81' },
      { test: /^balanza/i,                             label: 'Balanzas', color: '#0d9488' },
      { test: /^(gate|depot|administrativo|sombra)/i,  label: 'Otros',    color: '#b45309' },
      { test: /./,                                     label: 'Yard',     color: '#7c3aed' }
    ],
    COLOR_ESTADO: { activo: '#16a34a', traslado: '#0369a1', refrigerio: '#d97706', permiso: '#9333ea', incidencia: '#dc2626' }
  };
  const eselGroupOf = (value, groups) => groups.find(g => g.test.test(value)) || null;

  function enhanceSelect(sel, opts) {
    if (!sel || sel.dataset.eselReady) return;
    opts = opts || {};
    const groups   = opts.groups   || null;     // [{test,label,color}] → agrupado
    const colorOf  = opts.colorOf  || null;      // fn(value) → color (lista plana con punto)
    const badgeOf  = opts.badgeOf  || null;      // fn(value) → number → muestra badge de conteo
    const onChange = opts.onChange || null;      // fn(value) → callback al seleccionar
    sel.dataset.eselReady = '1';

    // — Envoltura + botón visible; el <select> se oculta dentro —
    const root = document.createElement('div');
    root.className = 'esel';
    sel.parentNode.insertBefore(root, sel);
    root.appendChild(sel);
    sel.classList.add('esel-native');
    sel.setAttribute('tabindex', '-1');
    sel.setAttribute('aria-hidden', 'true');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'esel-trigger';
    trigger.innerHTML = '<span class="esel-val"></span><span class="esel-arrow">▾</span>';
    root.appendChild(trigger);
    const valBox = trigger.querySelector('.esel-val');

    let panel = null, hl = -1, onScroll = null;

    const colorFor = (v) => groups ? ((eselGroupOf(v, groups) || {}).color || null)
                          : colorOf ? (colorOf(v) || null) : null;

    function updateTrigger() {
      const o = sel.options[sel.selectedIndex];
      if (!o) { valBox.innerHTML = '<span class="esel-ph">Selecciona…</span>'; return; }
      const c = colorFor(o.value);
      valBox.innerHTML = (c ? `<span class="esel-dot" style="background:${c}"></span>` : '')
        + `<span>${esc(o.textContent)}</span>`;
    }

    function optionHTML(o) {
      const c = colorFor(o.value);
      const isSel = o.value === sel.value;
      const dot = (!groups && c) ? `<span class="esel-dot" style="background:${c}"></span>` : '';
      const accent = (isSel && groups && c) ? ` style="border-left-color:${c}"` : '';
      const chkStyle = c ? ` style="color:${c}"` : '';
      const bc = badgeOf ? (badgeOf(o.value) || 0) : 0;
      let badge = '', activeClass = '';
      if (bc === -1) {
        badge = `<span class="esel-badge esel-badge--nave">⚓ Nave activa</span>`;
        activeClass = ' esel-opt--nave';
      } else if (bc > 0) {
        badge = `<span class="esel-badge">${bc} pers.</span>`;
        activeClass = ' esel-opt--active';
      }
      return `<div class="esel-opt${isSel ? ' sel' : ''}${activeClass}" role="option" data-val="${esc(o.value)}" title="${esc(o.textContent)}"${accent}>`
        + `<span class="esel-opt-main">${dot}<span>${esc(o.textContent)}</span></span>`
        + badge
        + `<span class="esel-check"${chkStyle}>✓</span></div>`;
    }

    function buildPanel() {
      panel = document.createElement('div');
      panel.className = 'esel-panel';
      const optionEls = Array.from(sel.options);
      let html = '';
      if (groups) {
        for (const g of groups) {
          const items = optionEls.filter(o => eselGroupOf(o.value, groups) === g);
          if (!items.length) continue;
          html += `<div class="esel-group"><span class="esel-dot" style="background:${g.color}"></span>${esc(g.label)}</div>`
                + items.map(optionHTML).join('');
        }
      } else {
        html = optionEls.map(optionHTML).join('');
      }
      panel.innerHTML = html;
      panel.addEventListener('mousedown', e => {       // mousedown: actúa antes del blur/outside
        const it = e.target.closest('.esel-opt'); if (!it) return;
        e.preventDefault();
        sel.value = it.dataset.val;                     // dispara el setter → updateTrigger
        if (onChange) onChange(it.dataset.val);
        close();
      });
    }

    function position() {
      const r = trigger.getBoundingClientRect();
      const vw = window.innerWidth, vh = window.innerHeight, gap = 5;
      const minPanelW = opts.minPanelWidth || 0;
      const panelW = Math.max(minPanelW, r.width);
      let left = r.left;
      if (left + panelW > vw - 8) left = Math.max(8, vw - panelW - 8);
      panel.style.left = left + 'px';
      panel.style.width = panelW + 'px';
      const below = vh - r.bottom - 10, above = r.top - 10;
      if (below >= 180 || below >= above) {
        panel.style.top = (r.bottom + gap) + 'px'; panel.style.bottom = 'auto';
        panel.style.maxHeight = Math.min(400, below + 10 - gap) + 'px';
      } else {
        panel.style.bottom = (vh - r.top + gap) + 'px'; panel.style.top = 'auto';
        panel.style.maxHeight = Math.min(400, above + 10 - gap) + 'px';
      }
      panel.scrollTop = 0;   // siempre abre desde arriba mostrando Muelles primero
    }

    function open() {
      if (panel) return;
      buildPanel();
      document.body.appendChild(panel);
      position();
      requestAnimationFrame(() => panel && panel.classList.add('open'));
      trigger.classList.add('open');
      hl = Array.from(panel.querySelectorAll('.esel-opt')).findIndex(el => el.classList.contains('sel'));
      document.addEventListener('mousedown', onOutside, true);
      document.addEventListener('keydown', onKey, true);
      onScroll = e => { if (panel && !panel.contains(e.target)) close(); };
      window.addEventListener('scroll', onScroll, true);
      window.addEventListener('resize', close, true);
    }
    function close() {
      if (!panel) return;
      const p = panel; panel = null; hl = -1;
      p.classList.remove('open');
      trigger.classList.remove('open');
      setTimeout(() => p.remove(), 150);
      document.removeEventListener('mousedown', onOutside, true);
      document.removeEventListener('keydown', onKey, true);
      if (onScroll) { window.removeEventListener('scroll', onScroll, true); onScroll = null; }
      window.removeEventListener('resize', close, true);
    }
    function onOutside(e) {
      if (panel && !panel.contains(e.target) && !root.contains(e.target)) close();
    }
    function setHl(i) {
      const els = panel.querySelectorAll('.esel-opt'); if (!els.length) return;
      hl = (i + els.length) % els.length;
      els.forEach((el, k) => el.classList.toggle('hl', k === hl));
      els[hl].scrollIntoView({ block: 'nearest' });
    }
    function onKey(e) {
      if (!panel) return;
      if (e.key === 'Escape')         { e.preventDefault(); close(); trigger.focus(); }
      else if (e.key === 'ArrowDown') { e.preventDefault(); setHl(hl + 1); }
      else if (e.key === 'ArrowUp')   { e.preventDefault(); setHl(hl - 1); }
      else if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        const els = panel.querySelectorAll('.esel-opt');
        if (els[hl]) { sel.value = els[hl].dataset.val; close(); trigger.focus(); }
      }
    }

    trigger.addEventListener('click', () => { panel ? close() : open(); });
    trigger.addEventListener('keydown', e => {
      if (!panel && (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); open(); }
    });

    // — Sincronización: interceptar setter `value` + observar la lista de <option> —
    const desc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    Object.defineProperty(sel, 'value', {
      configurable: true,
      get() { return desc.get.call(this); },
      set(v) { desc.set.call(this, v); updateTrigger(); this.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    new MutationObserver(updateTrigger).observe(sel, { childList: true });

    updateTrigger();
  }

  function wire() {
    enhanceSelect($('dwFuncion'));
    const ubBadge = v => {
      const pCount = state.personal.filter(p => p.ubicacion === v).length;
      if (pCount > 0) return pCount;
      if ((state.muellesConNave || []).includes(v)) return -1; // -1 = nave activa sin personal
      return 0;
    };
    enhanceSelect($('dwUbicacion'),  { groups: ESEL.GROUPS_UBIC, badgeOf: ubBadge });
    enhanceSelect($('dwEstado'), {
      colorOf:  v => ESEL.COLOR_ESTADO[v] || null,
      onChange: v => {
        const needsHora = ['traslado', 'refrigerio', 'permiso'].includes(v);
        $('dwHoraWrap').style.display = needsHora ? '' : 'none';
        if (needsHora) fillMotivoSelect($('evMotivo'), null, v);
        if (['traslado', 'permiso'].includes(v)) {
          const now = new Date();
          const hh = String(now.getHours()).padStart(2, '0');
          const mm = String(now.getMinutes()).padStart(2, '0');
          $('evIni').value = hh + ':' + mm;
        }
      }
    });
    enhanceSelect($('addFuncion'));
    enhanceSelect($('addUbicacion'), { groups: ESEL.GROUPS_UBIC, badgeOf: ubBadge, minPanelWidth: 240 });
    // Los selectores de nave se envuelven sin onChange: fillNaveSelect() les
    // asigna .value, y el setter interceptado dispara 'change'. Escucharlos
    // aquí crearía un bucle con el listener de ubicación de abajo.
    enhanceSelect($('dwNave'));
    enhanceSelect($('addNave'));

    // Cambiar de ubicación repuebla la nave: las candidatas dependen del muelle,
    // y salir de un muelle debe dejar el campo en «no aplica».
    $('dwUbicacion').addEventListener('change', e => {
      const p = state.personal.find(x => x.id === state.selectedId);
      // Se conserva la nave guardada sólo si se sigue en la MISMA ubicación;
      // al mover a otro muelle la sugerencia se recalcula desde cero.
      const mantener = (p && p.ubicacion === e.target.value) ? p.naveId : null;
      fillNaveSelect($('dwNave'), $('dwNaveWrap'), e.target.value, mantener);
      $('dwNave').disabled = !puedeModificar() || !esUbicacionMuelle(e.target.value);
    });
    $('addUbicacion').addEventListener('change', e => {
      fillNaveSelect($('addNave'), $('addNaveWrap'), e.target.value, null);
    });

    $('estSearch').addEventListener('input', e => { state.query = e.target.value; renderGrid(); });
    $('estFilter').addEventListener('click', e => {
      const b = e.target.closest('button[data-f]'); if (!b) return;
      state.filtro = b.dataset.f;
      $('estFilter').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
      renderGrid();
    });

    // Dropdowns: agrupar y ordenar (genérico)
    function wireToolMenu(btnId, menuId, attr, labelId, stateKey, lsKey, labels) {
      const btn = $(btnId), menu = $(menuId);
      btn.addEventListener('click', e => {
        e.stopPropagation();
        document.querySelectorAll('.est-tool-menu.open').forEach(m => m !== menu && m.classList.remove('open'));
        menu.classList.toggle('open');
      });
      menu.addEventListener('click', e => {
        const b = e.target.closest(`button[data-${attr}]`); if (!b) return;
        const v = b.dataset[attr];
        state[stateKey] = v;
        localStorage.setItem(lsKey, v);
        menu.querySelectorAll('button').forEach(x => x.classList.toggle('sel', x === b));
        $(labelId).textContent = labels[v] || v;
        btn.classList.toggle('active', v !== (attr === 'grp' ? 'none' : 'nombre'));
        menu.classList.remove('open');
        renderGrid();
      });
      // Restaurar selección desde state
      const cur = state[stateKey];
      menu.querySelectorAll('button').forEach(x => x.classList.toggle('sel', x.dataset[attr] === cur));
      if (labels[cur]) $(labelId).textContent = labels[cur];
      btn.classList.toggle('active', cur !== (attr === 'grp' ? 'none' : 'nombre'));
    }
    wireToolMenu('estGroupBtn', 'estGroupMenu', 'grp', 'estGroupLabel', 'agrupar', 'est_agrupar',
      { none: 'Ninguno', funcion: 'Función', ubicacion: 'Ubicación', estado: 'Estado' });
    wireToolMenu('estSortBtn', 'estSortMenu', 'srt', 'estSortLabel', 'ordenar', 'est_ordenar',
      { nombre: 'Nombre', alerta: 'Alerta', eventos: 'Eventos', estado: 'Estado' });
    document.addEventListener('click', () => {
      document.querySelectorAll('.est-tool-menu.open').forEach(m => m.classList.remove('open'));
    });

    // Toggle vista (grid / list)
    $('estViewToggle').addEventListener('click', e => {
      const b = e.target.closest('button[data-view]'); if (!b) return;
      state.vista = b.dataset.view;
      localStorage.setItem('est_vista', state.vista);
      $('estViewToggle').querySelectorAll('button').forEach(x => x.classList.toggle('active', x === b));
      renderGrid();
    });
    $('estViewToggle').querySelectorAll('button').forEach(x =>
      x.classList.toggle('active', x.dataset.view === state.vista));


    // Grid + lista → abrir drawer
    $('estGrid').addEventListener('click', e => {
      const c = e.target.closest('.est-card[data-id]'); if (!c) return;
      openDrawer(c.dataset.id);
    });
    $('estListBody').addEventListener('click', e => {
      const r = e.target.closest('tr[data-id]'); if (!r) return;
      openDrawer(r.dataset.id);
    });

    // Exports
    $('estExportPDFBtn').addEventListener('click', exportPDF);
    $('estExportXLSBtn').addEventListener('click', exportExcel);
    $('estReporteRangoBtn').addEventListener('click', openRangoReport);
    $('rangoClose').addEventListener('click', closeRangoReport);
    $('rangoBack').addEventListener('click', closeRangoReport);
    $('rangoGenerar').addEventListener('click', generarReporteRango);

    // Drawer
    $('dwClose').addEventListener('click', closeDrawer);
    $('estDrawerBack').addEventListener('click', closeDrawer);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeDrawer(); closeAddPersonal(); closeRangoReport(); } });
    $('dwRegistrar').addEventListener('click', registrarCambio);

    $('evMotivo').addEventListener('change', e => {
      $('evOtrosWrap').style.display = motivoRequiereTexto(e.target.value) ? '' : 'none';
      if (e.target.value === 'Salida Temprana' && $('dwEstado').value === 'permiso') {
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        const t = hh + ':' + mm;
        $('evIni').value = t;
        $('evFin').value = t;
      }
    });
    if ($('evAdd')) $('evAdd').addEventListener('click', addEvento);
    if ($('dwCheckAdd')) $('dwCheckAdd').addEventListener('click', agregarChecklistItem);
    $('dwRemove').addEventListener('click', removePersonal);
    $('estCerrarBtn').addEventListener('click', toggleCerrarTurno);
    $('ingresoJornadas').addEventListener('click', e => {
      const b = e.target.closest('button[data-jid]'); if (!b) return;
      elegirIngreso(Number(b.dataset.jid));
    });

    // Añadir personal al turno (modal selector)
    $('estAddBtn').addEventListener('click', openAddPersonal);

    // ── Refrigerio masivo ──────────────────────────────────────────────────
    // Selección persistente: sobrevive a los filtros, de modo que se puede
    // marcar un grupo (p. ej. una ubicación), cambiar de filtro y marcar otro,
    // y registrar a todos los seleccionados aunque ya no estén visibles.
    let rmSel = new Set();
    function rmActivos() { return state.personal.filter(p => p.estado === 'activo' && !(p.bitacora || []).some(ev => ev.tipo === 'refrigerio')); }
    function rmFiltrados() {
      const u = $('rmFiltroUbic') ? $('rmFiltroUbic').value : '';
      const f = $('rmFiltroFunc') ? $('rmFiltroFunc').value : '';
      const q = $('rmBuscar') ? $('rmBuscar').value.trim().toLowerCase() : '';
      return rmActivos().filter(p =>
        (!u || (p.ubicacion || '—') === u) && (!f || (p.funcion || '—') === f)
        && (!q || [p.nombre, p.codigo, p.funcion, p.ubicacion].some(v => String(v || '').toLowerCase().includes(q))));
    }
    function rmSeleccionados() { return [...rmSel]; }
    function rmUpdateInfo() {
      const sel = rmSel.size;
      const count = $('rmSelectedCount');
      if (count) count.textContent = `${sel} seleccionado${sel !== 1 ? 's' : ''}`;
      $('rmInfo').textContent = sel
        ? `Se registrará refrigerio a ${sel} persona${sel > 1 ? 's' : ''} seleccionada${sel > 1 ? 's' : ''}.`
        : 'Selecciona al menos una persona.';
    }
    function llenarRmFiltros() {
      const activos = rmActivos();
      const opts = (vals, cur) => '<option value="">Todas</option>' +
        [...new Set(vals)].sort((a, b) => a.localeCompare(b))
          .map(v => `<option value="${esc(v)}"${v === cur ? ' selected' : ''}>${esc(v)}</option>`).join('');
      const su = $('rmFiltroUbic'), sf = $('rmFiltroFunc');
      const cu = su ? su.value : '', cf = sf ? sf.value : '';
      if (su) su.innerHTML = opts(activos.map(p => p.ubicacion || '—'), cu);
      if (sf) sf.innerHTML = opts(activos.map(p => p.funcion || '—'), cf);
    }
    function renderRmLista() {
      if (!rmActivos().length) {
        $('rmLista').innerHTML = '<div style="padding:14px;text-align:center;font-size:12px;color:#94a3b8">Todo el personal ya tiene refrigerio registrado</div>';
        rmUpdateInfo();
        return;
      }
      const lista = rmFiltrados();
      if (!lista.length) {
        $('rmLista').innerHTML = '<div style="padding:14px;text-align:center;font-size:12px;color:#94a3b8">Sin personal para este filtro</div>';
        rmUpdateInfo();
        return;
      }
      $('rmLista').innerHTML = lista.map(p => {
        const ini = (p.nombre || '').trim().split(/\s+/);
        const av = ((ini[0]?.[0] || '') + (ini[1]?.[0] || '')).toUpperCase();
        const meta = [p.ubicacion || '—', p.funcion || '—'].filter(Boolean).join(' · ');
        return `<label style="display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;border-bottom:1px solid #e2e8f0;user-select:none" class="rm-row">
          <input type="checkbox" data-id="${esc(p.id)}"${rmSel.has(p.id) ? ' checked' : ''} style="width:15px;height:15px;accent-color:#d97706;flex-shrink:0">
          <span style="width:28px;height:28px;border-radius:8px;background:#fed7aa;color:#92400e;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0">${av}</span>
          <span style="flex:1;min-width:0">
            <span style="display:block;font-size:12.5px;font-weight:600;color:#1b2a40;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.nombre)}</span>
            <span style="font-size:11px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block">${esc(meta)}</span>
          </span>
        </label>`;
      }).join('');
      $('rmLista').querySelectorAll('input[type=checkbox]').forEach(cb => cb.addEventListener('change', () => {
        if (cb.checked) rmSel.add(cb.dataset.id); else rmSel.delete(cb.dataset.id);
        rmUpdateInfo();
      }));
      rmUpdateInfo();
    }
    function openRefMasivo() {
      $('rmIni').value = ''; $('rmFin').value = '';
      if ($('rmFiltroUbic')) $('rmFiltroUbic').value = '';
      if ($('rmFiltroFunc')) $('rmFiltroFunc').value = '';
      if ($('rmBuscar')) $('rmBuscar').value = '';
      rmSel = new Set(rmActivos().map(p => p.id));   // por defecto, todos seleccionados
      llenarRmFiltros();
      renderRmLista();
      $('refMasivoModal').classList.add('open');
      $('refMasivoModal').setAttribute('aria-hidden','false');
      $('refMasivoBack').classList.add('open');
    }
    function closeRefMasivo() {
      $('refMasivoModal').classList.remove('open');
      $('refMasivoModal').setAttribute('aria-hidden','true');
      $('refMasivoBack').classList.remove('open');
    }
    async function guardarRefMasivo() {
      const ini = $('rmIni').value, fin = $('rmFin').value, motivo = $('rmMotivo').value;
      if (!ini) { toast('Hora de inicio requerida', 'error'); return; }
      const ids = rmSeleccionados();
      if (!ids.length) { toast('Selecciona al menos una persona', 'error'); return; }
      const selPersonal = state.personal.filter(p => ids.includes(p.id));
      const btn = $('refMasivoGuardar'); btn.disabled = true;
      btn.textContent = 'Guardando…';
      let ok = 0, err = 0;
      for (const p of selPersonal) {
        try {
          const r = await apiPost('api/add_evento.php', { tpId: p.tpId, tipo: 'refrigerio', horaInicio: ini, horaFin: fin, motivo, observaciones: '' });
          p.bitacora.push({ id: r.id, tipo: 'refrigerio', motivo, horaInicio: ini, horaFin: fin, observaciones: '' });
          ok++;
        } catch (_) { err++; }
      }
      renderGrid(); renderKpis();
      pushAccion('evento', `Refrigerio masivo ${ini}${fin ? '–'+fin : ''} · ${motivo} (${ok} personas)`, '', '');
      toast(`Refrigerio registrado a ${ok} persona${ok !== 1 ? 's' : ''}${err ? ` · ${err} error(es)` : ''}`);
      btn.disabled = false; btn.textContent = 'Registrar refrigerio';
      closeRefMasivo();
    }
    $('estRefMasivoBtn').addEventListener('click', openRefMasivo);
    $('refMasivoClose').addEventListener('click', closeRefMasivo);
    $('refMasivoCancel').addEventListener('click', closeRefMasivo);
    $('refMasivoBack').addEventListener('click', closeRefMasivo);
    $('refMasivoGuardar').addEventListener('click', guardarRefMasivo);
    // "Todos" / "Ninguno" actúan solo sobre las filas visibles (filtradas).
    $('rmSelAll').addEventListener('click', () => {
      $('rmLista').querySelectorAll('input[type=checkbox]').forEach(cb => { cb.checked = true; rmSel.add(cb.dataset.id); });
      rmUpdateInfo();
    });
    $('rmSelNone').addEventListener('click', () => {
      $('rmLista').querySelectorAll('input[type=checkbox]').forEach(cb => { cb.checked = false; rmSel.delete(cb.dataset.id); });
      rmUpdateInfo();
    });
    // Al cambiar un filtro, re-renderiza la lista conservando la selección.
    if ($('rmFiltroUbic')) $('rmFiltroUbic').addEventListener('change', renderRmLista);
    if ($('rmFiltroFunc')) $('rmFiltroFunc').addEventListener('change', renderRmLista);
    if ($('rmBuscar')) $('rmBuscar').addEventListener('input', renderRmLista);

    // ── Cierre de refrigerio masivo (poner hora fin a los abiertos) ─────────
    let rcSel = new Set();
    const rcRefsAbiertos = (p) => (p.bitacora || []).filter(ev => ev.tipo === 'refrigerio' && !ev.horaFin);
    function rcActivos() { return state.personal.filter(p => rcRefsAbiertos(p).length); }
    function rcFiltrados() {
      const u = $('rcFiltroUbic') ? $('rcFiltroUbic').value : '';
      const f = $('rcFiltroFunc') ? $('rcFiltroFunc').value : '';
      return rcActivos().filter(p =>
        (!u || (p.ubicacion || '—') === u) && (!f || (p.funcion || '—') === f));
    }
    function rcUpdateInfo() {
      const sel = rcSel.size;
      $('rcInfo').textContent = sel
        ? `Se cerrará el refrigerio de ${sel} persona${sel > 1 ? 's' : ''}.`
        : 'Selecciona al menos una persona.';
    }
    function llenarRcFiltros() {
      const activos = rcActivos();
      const opts = (vals, cur) => '<option value="">Todas</option>' +
        [...new Set(vals)].sort((a, b) => a.localeCompare(b))
          .map(v => `<option value="${esc(v)}"${v === cur ? ' selected' : ''}>${esc(v)}</option>`).join('');
      const su = $('rcFiltroUbic'), sf = $('rcFiltroFunc');
      if (su) su.innerHTML = opts(activos.map(p => p.ubicacion || '—'), su.value);
      if (sf) sf.innerHTML = opts(activos.map(p => p.funcion || '—'), sf.value);
    }
    function renderRcLista() {
      if (!rcActivos().length) {
        $('rcLista').innerHTML = '<div style="padding:14px;text-align:center;font-size:12px;color:#94a3b8">No hay refrigerios abiertos</div>';
        rcUpdateInfo();
        return;
      }
      const lista = rcFiltrados();
      if (!lista.length) {
        $('rcLista').innerHTML = '<div style="padding:14px;text-align:center;font-size:12px;color:#94a3b8">Sin personal para este filtro</div>';
        rcUpdateInfo();
        return;
      }
      $('rcLista').innerHTML = lista.map(p => {
        const ini = (p.nombre || '').trim().split(/\s+/);
        const av = ((ini[0]?.[0] || '') + (ini[1]?.[0] || '')).toUpperCase();
        const desde = rcRefsAbiertos(p).map(ev => ev.horaInicio).filter(Boolean).sort()[0] || '—';
        const meta = `Refrigerio desde ${esc(desde)} · ${esc(p.ubicacion || '—')}`;
        return `<label style="display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;border-bottom:1px solid #e2e8f0;user-select:none" class="rm-row">
          <input type="checkbox" data-id="${esc(p.id)}"${rcSel.has(p.id) ? ' checked' : ''} style="width:15px;height:15px;accent-color:#d97706;flex-shrink:0">
          <span style="width:28px;height:28px;border-radius:8px;background:#fed7aa;color:#92400e;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0">${av}</span>
          <span style="flex:1;min-width:0">
            <span style="display:block;font-size:12.5px;font-weight:600;color:#1b2a40;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.nombre)}</span>
            <span style="font-size:11px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block">${meta}</span>
          </span>
        </label>`;
      }).join('');
      $('rcLista').querySelectorAll('input[type=checkbox]').forEach(cb => cb.addEventListener('change', () => {
        if (cb.checked) rcSel.add(cb.dataset.id); else rcSel.delete(cb.dataset.id);
        rcUpdateInfo();
      }));
      rcUpdateInfo();
    }
    function openRefCierre() {
      if (!state.turnoId) { toast('Turno no disponible aún'); return; }
      $('rcFin').value = '';
      if ($('rcFiltroUbic')) $('rcFiltroUbic').value = '';
      if ($('rcFiltroFunc')) $('rcFiltroFunc').value = '';
      rcSel = new Set(rcActivos().map(p => p.id));   // por defecto, todos
      llenarRcFiltros();
      renderRcLista();
      $('rcModal').classList.add('open');
      $('rcModal').setAttribute('aria-hidden','false');
      $('rcBack').classList.add('open');
    }
    function closeRefCierre() {
      $('rcModal').classList.remove('open');
      $('rcModal').setAttribute('aria-hidden','true');
      $('rcBack').classList.remove('open');
    }
    async function guardarRefCierre() {
      const fin = $('rcFin').value;
      if (!fin) { toast('Hora fin requerida', 'error'); return; }
      const ids = [...rcSel];
      if (!ids.length) { toast('Selecciona al menos una persona', 'error'); return; }
      const selPersonal = state.personal.filter(p => ids.includes(p.id));
      const btn = $('rcGuardar'); btn.disabled = true; btn.textContent = 'Guardando…';
      let ok = 0, err = 0;
      for (const p of selPersonal) {
        for (const ev of rcRefsAbiertos(p)) {
          try {
            await apiPost('api/update_evento.php', {
              id: ev.id, horaInicio: ev.horaInicio, horaFin: fin,
              motivo: ev.motivo || '', observaciones: ev.observaciones || ''
            });
            ev.horaFin = fin;
            ok++;
          } catch (_) { err++; }
        }
      }
      renderGrid(); renderKpis();
      pushAccion('evento', `Cierre de refrigerio ${fin} (${ok} registro${ok !== 1 ? 's' : ''})`, '', '');
      toast(`Refrigerio cerrado · ${ok} registro${ok !== 1 ? 's' : ''}${err ? ` · ${err} error(es)` : ''}`);
      btn.disabled = false; btn.textContent = 'Cerrar refrigerio';
      closeRefCierre();
    }
    $('estRefCierreBtn').addEventListener('click', openRefCierre);
    $('rcClose').addEventListener('click', closeRefCierre);
    $('rcCancel').addEventListener('click', closeRefCierre);
    $('rcBack').addEventListener('click', closeRefCierre);
    $('rcGuardar').addEventListener('click', guardarRefCierre);
    $('rcSelAll').addEventListener('click', () => {
      $('rcLista').querySelectorAll('input[type=checkbox]').forEach(cb => { cb.checked = true; rcSel.add(cb.dataset.id); });
      rcUpdateInfo();
    });
    $('rcSelNone').addEventListener('click', () => {
      $('rcLista').querySelectorAll('input[type=checkbox]').forEach(cb => { cb.checked = false; rcSel.delete(cb.dataset.id); });
      rcUpdateInfo();
    });
    if ($('rcFiltroUbic')) $('rcFiltroUbic').addEventListener('change', renderRcLista);
    if ($('rcFiltroFunc')) $('rcFiltroFunc').addEventListener('change', renderRcLista);

    // ── Quitar personal masivo ─────────────────────────────────────────────
    function qmSeleccionados() {
      return [...$('qmLista').querySelectorAll('input[type=checkbox]:checked')].map(cb => cb.dataset.tpid);
    }
    function qmUpdateInfo() {
      const sel = qmSeleccionados().length;
      $('qmInfo').textContent = sel
        ? `Se quitarán ${sel} persona${sel > 1 ? 's' : ''} y su bitácora del turno. Esta acción no se puede deshacer.`
        : 'Selecciona al menos una persona.';
      $('qmConfirm').disabled = sel === 0;
    }
    function renderQmLista() {
      const todos = state.personal;
      if (!todos.length) {
        $('qmLista').innerHTML = '<div style="padding:14px;text-align:center;font-size:12px;color:#94a3b8">No hay personal en el turno</div>';
        return;
      }
      $('qmLista').innerHTML = todos.map(p => {
        const ini = (p.nombre || '').trim().split(/\s+/);
        const av  = ((ini[0]?.[0] || '') + (ini[1]?.[0] || '')).toUpperCase();
        const nEv = (p.bitacora || []).length;
        return `<label style="display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;border-bottom:1px solid #e2e8f0;user-select:none" class="qm-row">
          <input type="checkbox" data-tpid="${esc(p.tpId)}" style="width:15px;height:15px;accent-color:#ef4444;flex-shrink:0">
          <span style="width:28px;height:28px;border-radius:8px;background:#fee2e2;color:#991b1b;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0">${av}</span>
          <span style="flex:1;min-width:0">
            <span style="display:block;font-size:12.5px;font-weight:600;color:#1b2a40;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.nombre)}</span>
            <span style="font-size:11px;color:#64748b">${esc(p.ubicacion || '—')} · ${nEv} evento${nEv !== 1 ? 's' : ''}</span>
          </span>
        </label>`;
      }).join('');
      $('qmLista').querySelectorAll('input[type=checkbox]').forEach(cb => cb.addEventListener('change', qmUpdateInfo));
      qmUpdateInfo();
    }
    function openQuitarMasivo() {
      renderQmLista();
      $('qmModal').classList.add('open');
      $('qmModal').setAttribute('aria-hidden', 'false');
      $('qmBack').classList.add('open');
    }
    function closeQuitarMasivo() {
      $('qmModal').classList.remove('open');
      $('qmModal').setAttribute('aria-hidden', 'true');
      $('qmBack').classList.remove('open');
    }
    async function confirmarQuitarMasivo() {
      const tpIds = qmSeleccionados();
      if (!tpIds.length) { toast('Selecciona al menos una persona', 'error'); return; }
      const btn = $('qmConfirm'); btn.disabled = true;
      const prevText = btn.textContent.trim(); btn.textContent = 'Quitando…';
      let ok = 0, lastErr = '';
      for (const tpId of tpIds) {
        const p = state.personal.find(x => String(x.tpId) === String(tpId));
        try {
          await apiPost('api/remove_personal_turno.php', { tpId: Number(tpId) });
          state.personal = state.personal.filter(x => String(x.tpId) !== String(tpId));
          if (p) {
            state.notified.forEach(k => { if (k.startsWith(p.id + ':')) state.notified.delete(k); });
            pushAccion('baja', 'Quitado del turno (masivo)', p.id, p.nombre);
          }
          ok++;
        } catch (e) { lastErr = e.message || 'Error'; }
      }
      if (state.selectedId) {
        const stillHere = state.personal.find(x => x.id === state.selectedId);
        if (!stillHere) closeDrawer();
      }
      renderGrid(); renderKpis();
      btn.disabled = false; btn.textContent = prevText;
      if (ok > 0) {
        toast(`${ok} persona${ok !== 1 ? 's' : ''} quitada${ok !== 1 ? 's' : ''} del turno`);
        closeQuitarMasivo();
      } else {
        toast('No se pudo quitar: ' + lastErr, 'error');
      }
    }
    $('estQuitarMasivoBtn').addEventListener('click', openQuitarMasivo);
    $('qmClose').addEventListener('click', closeQuitarMasivo);
    $('qmCancel').addEventListener('click', closeQuitarMasivo);
    $('qmBack').addEventListener('click', closeQuitarMasivo);
    $('qmConfirm').addEventListener('click', confirmarQuitarMasivo);
    $('qmSelAll').addEventListener('click', () => {
      $('qmLista').querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = true);
      qmUpdateInfo();
    });
    $('qmSelNone').addEventListener('click', () => {
      $('qmLista').querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false);
      qmUpdateInfo();
    });

    $('addClose').addEventListener('click', closeAddPersonal);
    $('addBack').addEventListener('click', closeAddPersonal);
    $('addSearch').addEventListener('input', e => { addState.query = e.target.value; renderAddList(); });
    $('addList').addEventListener('click', e => {
      const row = e.target.closest('.est-add-row[data-cid]'); if (!row) return;
      toggleCandidate(row.dataset.cid);
    });
    $('addSelAll').addEventListener('click', () => {
      addState.filtrados.filter(c => !c.conflicto).forEach(c => addState.seleccionados.add(Number(c.id)));
      renderAddList(); updateAddSelBar();
    });
    $('addSelClear').addEventListener('click', () => {
      addState.seleccionados.clear(); renderAddList(); updateAddSelBar();
    });
    $('addConfirm').addEventListener('click', confirmAddPersonal);

    // Selector de fecha + jornada (tablero) y jornada (modal) → recargan turno.
    const dateInp = $('estTurnoFechaInput');
    if (dateInp) dateInp.addEventListener('change', e => {
      if (e.target.value) reloadTurno(e.target.value, state.jornadaId);
    });
    const jSel = $('estTurnoJornadaSel');
    if (jSel) jSel.addEventListener('change', e => reloadTurno(state.turnoFecha, e.target.value));
    const addSel = $('addJornadaSel');
    if (addSel) addSel.addEventListener('change', e => reloadTurno(state.turnoFecha, e.target.value));
  }

  // ── Tick periódico: re-evalúa alertas y dispara toast en transición ────
  function tickAlerts() {
    let nuevas = 0;
    state.personal.forEach(p => {
      const al = computeAlert(p);
      const key = al ? `${p.id}:${al.level}` : null;
      if (key && !state.notified.has(key)) {
        state.notified.forEach(k => { if (k.startsWith(p.id + ':')) state.notified.delete(k); });
        state.notified.add(key);
        nuevas++;
        if (al.level === 'over') {
          toast(`⚠ ${p.nombre} excedió su ${labelTipo[p.estado === 'incidencia' ? 'permiso' : p.estado].toLowerCase()} (+${al.mins} min)`);
        } else if (al.level === 'warn' && nuevas === 1) {
          toast(`⏰ ${p.nombre}: ${al.mins} min para fin de ${labelTipo[p.estado === 'incidencia' ? 'permiso' : p.estado].toLowerCase()}`);
        }
      }
      if (!al) {
        state.notified.forEach(k => { if (k.startsWith(p.id + ':')) state.notified.delete(k); });
      }
    });
    renderKpis();
    renderGrid();
    if (state.selectedId) {
      const p = state.personal.find(x => x.id === state.selectedId);
      if (p) renderDrawerAlert(p);
    }
  }

  // ── Boot ───────────────────────────────────────────────────────────────
  async function boot(data) {
    const src = data || window.__EstibaDataSource || { personal: [], funcionesDisponibles: [], ubicacionesDisponibles: [], turnoLabel: '' };
    state.turnoId     = src.turnoId ?? null;
    state.turnoEstado = src.turnoEstado ?? null;
    state.personal    = (src.personal || []).map(p => ({ ...p, bitacora: [...(p.bitacora || [])], checklist: [...(p.checklist || [])] }));
    state.funciones       = [...(src.funcionesDisponibles   || [])];
    state.ubicaciones     = [...(src.ubicacionesDisponibles || [])];
    state.muellesConNave  = [...(src.muellesConNave || [])];
    state.turnoLabel  = src.turnoLabel || '';
    state.jornada     = src.turnoJornada ?? null;
    state.jornadaId   = src.turnoJornadaId ?? null;
    state.jornadas    = [...(src.jornadas || [])];
    state.turnoFecha  = src.turnoFecha ?? null;
    state.motivos     = [...(src.motivosDisponibles || [])];
    state.acciones    = [...(src.acciones || [])];
    state.puedeValidar = !!src.puedeValidar;
    state.puedeCerrar  = (src.puedeCerrar !== undefined) ? !!src.puedeCerrar
                       : ['Administrador', 'Supervisor', 'Coordinador'].includes(src.rol);
    state.rol         = src.rol ?? (window.__ESTIBA_USER && window.__ESTIBA_USER.rol) ?? null;
    const rango = parseTurnoRange(state.turnoLabel);
    state.turnoIni   = rango.ini;
    state.turnoWraps = rango.fin <= rango.ini;             // cruza medianoche (Noche)
    state.turnoFin   = state.turnoWraps ? rango.fin + 1440 : rango.fin;
    const lim = (src.limitesMin || {});
    state.limites = {
      refrigerio: lim.refrigerio ?? 30,
      permiso:    lim.permiso    ?? 60
    };

    state.notified.clear();
    // Catálogo de naves para el selector de muelle. Sin await: es un dato
    // opcional del formulario y no debe retrasar el pintado del turno.
    cargarNaves();
    syncJornadaUI();
    renderTurnoEstado();
    renderKpis();
    renderGrid();
    maybeShowIngreso();
    state.personal.forEach(p => {
      const al = computeAlert(p);
      if (al) state.notified.add(`${p.id}:${al.level}`);
    });
    clearInterval(state._tick);
    state._tick = setInterval(tickAlerts, 30000);
  }

  // ── API pública mínima ─────────────────────────────────────────────────
  window.EstibaModule = {
    boot,
    getSnapshot: buildSnapshot
  };

  // wire() prepara los listeners; boot() lo dispara data-source.js tras el fetch.
  document.addEventListener('DOMContentLoaded', wire);
})();
