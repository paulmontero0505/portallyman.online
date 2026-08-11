/* ════════════════════════════════════════════════════════════════════════
   ADAPTADOR DE DATOS · CONEXIÓN REAL CON EL BACKEND
   ────────────────────────────────────────────────────────────────────────
   Carga el turno vigente desde la base de datos (api/get_turno_actual.php) y
   arranca el módulo. El backend resuelve el turno (fecha + jornada) según la
   hora del servidor, lo crea vacío si no existe, y devuelve el contrato que
   espera EstibaModule:

     { turnoId, turnoLabel, limitesMin,
       funcionesDisponibles, ubicacionesDisponibles,
       personal: [{ tpId, id, colaboradorId, nombre, funcion, ubicacion,
                    estado, bitacora: [{ id, tipo, horaInicio, horaFin,
                    observaciones }] }] }

   Ya no hay datos hardcodeados: el turno arranca con el personal que se haya
   colocado desde la propia pantalla.
   ════════════════════════════════════════════════════════════════════════ */

window.cargarTurnoActual = async function cargarTurnoActual(fecha, jornadaId) {
  try {
    const qs = [];
    if (fecha)     qs.push('fecha=' + encodeURIComponent(fecha));
    if (jornadaId) qs.push('jornadaId=' + encodeURIComponent(jornadaId));
    const url = 'api/get_turno_actual.php' + (qs.length ? ('?' + qs.join('&')) : '');
    const res  = await fetch(url, { cache: 'no-store' });
    const data = await res.json();
    if (!data || !data.success) {
      console.error('[turno] respuesta inválida:', data);
      window.__EstibaDataSource = { personal: [], funcionesDisponibles: [], ubicacionesDisponibles: [], turnoLabel: '—' };
    } else {
      window.__EstibaDataSource = data;
    }
  } catch (e) {
    console.error('[turno] error de red:', e);
    window.__EstibaDataSource = { personal: [], funcionesDisponibles: [], ubicacionesDisponibles: [], turnoLabel: '—' };
  }
  if (window.EstibaModule && typeof window.EstibaModule.boot === 'function') {
    window.EstibaModule.boot(window.__EstibaDataSource);
  }
};

document.addEventListener('DOMContentLoaded', () => { window.cargarTurnoActual(); });
