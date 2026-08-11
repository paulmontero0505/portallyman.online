(() => {
  'use strict';

  const $ = id => document.getElementById(id);
  const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[char]));
  const today = () => new Date().toISOString().slice(0, 10);

  const state = {
    colaboradores: [],
    incidencias: [],
    trabajadorId: 0,
    evidencePath: '',
    loaded: false
  };

  function initials(name) {
    return String(name || '').trim().split(/\s+/).slice(0, 2)
      .map(part => part.charAt(0).toUpperCase()).join('') || '-';
  }

  function daysBetween(start, end) {
    if (!start || !end || end < start) return 0;
    return Math.floor((new Date(end + 'T00:00:00') - new Date(start + 'T00:00:00')) / 86400000) + 1;
  }

  function puntos() {
    return window.SD_CATALOGOS && window.SD_CATALOGOS.puntos ? window.SD_CATALOGOS.puntos : {};
  }

  function impactos() {
    return window.SD_CATALOGOS && window.SD_CATALOGOS.impactos ? window.SD_CATALOGOS.impactos : {};
  }

  function zonas() {
    return window.SD_CATALOGOS && Array.isArray(window.SD_CATALOGOS.zonas) ? window.SD_CATALOGOS.zonas : [];
  }

  function setVisible(id, visible) {
    const node = $(id);
    if (node) node.style.display = visible ? '' : 'none';
  }

  function syncRail() {
    const worker = state.colaboradores.find(item => Number(item.id) === state.trabajadorId);
    const incId = Number(($('sdNewInc') || {}).value || 0);
    const point = ($('sdNewPunto') || {}).value || '';
    const impact = ($('sdNewImpacto') || {}).value || '';
    const comp = puntos()[point] || '';

    $('sdRailAvatar').textContent = initials(worker && worker.nombre);
    $('sdRailName').textContent = worker ? worker.nombre : 'Sin trabajador';
    $('sdRailCargo').textContent = worker ? (worker.funcion_principal || '-') : 'Selecciona un trabajador';
    $('sdRailImpacto').textContent = incId && impact ? ((impactos()[impact] && impactos()[impact].label) || impact) : 'No aplica';
    $('sdRailComp').textContent = incId && comp ? comp : 'Solo si se anexa incidencia';
    if ($('sdNewCompetencia')) $('sdNewCompetencia').value = comp;
  }

  function syncTipo() {
    const tipo = $('sdNewTipo').value;
    const suspension = tipo === 'suspension';
    setVisible('sdFechaUnicaWrap', !suspension);
    setVisible('sdFechasSuspension', suspension);
    if (!suspension) {
      $('sdNewIni').value = $('sdNewFechaUnica').value || today();
      $('sdNewFin').value = $('sdNewIni').value;
    }
    syncDays();
  }

  function syncDays() {
    const tipo = $('sdNewTipo').value;
    if (tipo !== 'suspension') {
      $('sdNewDays').textContent = '1 dia';
      return;
    }
    const total = daysBetween($('sdNewIni').value, $('sdNewFin').value);
    $('sdNewDays').textContent = total ? `${total} dia(s)` : '0 dia(s)';
  }

  function syncAnexo() {
    const hasInc = Number($('sdNewInc').value || 0) > 0;
    setVisible('sdSecEvaluacion', hasInc);
    setVisible('sdSecContexto', hasInc);
    setVisible('sdSecDetalle', hasInc);
    syncRail();
  }

  const iconizeActions = () => document.querySelectorAll('[data-sd]').forEach(button => {
    if (button.dataset.iconized) return;
    const deleting = button.dataset.sd === 'del';
    button.dataset.iconized = '1';
    button.className = `sd-action-icon${deleting ? ' is-danger' : ''}`;
    button.title = deleting ? 'Eliminar sancion' : 'Editar sancion';
    button.setAttribute('aria-label', button.title);
    button.innerHTML = deleting
      ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v5M14 11v5"/></svg>'
      : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l11-11a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13 7 4 4"/></svg>';
    if (button.parentElement && !button.parentElement.classList.contains('sd-action-set')) {
      button.parentElement.classList.add('sd-action-set');
    }
  });

  async function uploadEvidence(file) {
    if (!file) return '';
    const form = new FormData();
    form.append('file', file);
    const response = await fetch('../api/upload_sancion_evidencia.php', { method: 'POST', body: form });
    const result = await response.json();
    if (!result.success) throw new Error(result.error || 'No se pudo cargar la evidencia.');
    return result.path;
  }

  async function loadSources() {
    if (state.loaded) return;
    const [colRes, incRes] = await Promise.all([
      fetch('../api/get_colaboradores.php', { cache: 'no-store' }),
      fetch('../api/get_incidencias.php', { cache: 'no-store' })
    ]);
    const [colJson, incJson] = await Promise.all([colRes.json(), incRes.json()]);
    if (!colJson.success) throw new Error(colJson.error || 'No fue posible cargar trabajadores.');
    if (!incJson.success) throw new Error(incJson.error || 'No fue posible cargar incidencias.');
    state.colaboradores = (colJson.data || []).filter(item => Number(item.activo) === 1);
    state.incidencias = (incJson.data || []).sort((a, b) =>
      String(b.fecha || '').localeCompare(String(a.fecha || '')) || Number(b.id || 0) - Number(a.id || 0)
    );
    state.loaded = true;
  }

  function renderWorkers(query) {
    const panel = $('sdNewWorkerPanel');
    const q = String(query || '').trim().toLowerCase();
    const list = (q
      ? state.colaboradores.filter(item => `${item.nombre || ''} ${item.codigo || ''} ${item.dni || ''}`.toLowerCase().includes(q))
      : state.colaboradores
    ).slice(0, 40);

    panel.innerHTML = list.length ? list.map(item => `
      <button type="button" class="sd-pick-row" data-worker="${Number(item.id)}">
        <span class="sd-pick-avatar">${esc(initials(item.nombre))}</span>
        <span><strong>${esc(item.nombre)}</strong><small>${esc(item.codigo || 'Sin codigo')} - ${esc(item.funcion_principal || '-')}</small></span>
      </button>
    `).join('') : '<div class="sd-pick-empty">No hay trabajadores con ese criterio.</div>';
    panel.classList.add('show');
  }

  function selectWorker(id) {
    state.trabajadorId = Number(id || 0);
    const worker = state.colaboradores.find(item => Number(item.id) === state.trabajadorId);
    $('sdNewWorker').value = worker ? worker.nombre : '';
    $('sdNewCargo').value = worker ? (worker.funcion_principal || '-') : '';
    $('sdNewWorkerPanel').classList.remove('show');
    populateIncidences();
    syncRail();
  }

  function populateIncidences() {
    const workerId = state.trabajadorId;
    const list = state.incidencias.filter(item => !workerId || Number(item.colaborador_id) === workerId);
    $('sdNewInc').innerHTML = '<option value="">Sin incidencia anexada</option>' + list.map(item =>
      `<option value="${Number(item.id)}">${esc(item.fecha)} - ${esc(item.punto_mejorar)} - ${esc(item.zona_trabajo || '-')}</option>`
    ).join('');
  }

  function applyIncident(id) {
    const incId = Number(id || 0);
    if (!incId) {
      $('sdNewPunto').value = '';
      $('sdNewCompetencia').value = '';
      setImpact('');
      $('sdNewTurno').value = 'dia';
      $('sdNewFechaInc').value = today();
      $('sdNewZona').value = '';
      $('sdNewDetalle').value = '';
      syncAnexo();
      return;
    }

    const inc = state.incidencias.find(item => Number(item.id) === incId);
    if (!inc) return;
    if (inc.colaborador_id) selectWorker(inc.colaborador_id);
    $('sdNewInc').value = String(inc.id);
    $('sdNewPunto').value = inc.punto_mejorar || '';
    $('sdNewCompetencia').value = inc.competencia || puntos()[inc.punto_mejorar] || '';
    setImpact(inc.impacto || '');
    $('sdNewTurno').value = inc.turno || 'dia';
    $('sdNewFechaInc').value = inc.fecha || today();
    $('sdNewZona').value = inc.zona_trabajo || '';
    $('sdNewDetalle').value = inc.detalle || '';
    syncAnexo();
  }

  function buildCatalogControls() {
    $('sdNewPunto').innerHTML = '<option value="">Selecciona...</option>' + Object.keys(puntos())
      .map(point => `<option value="${esc(point)}">${esc(point)}</option>`).join('');
    $('sdNewZona').innerHTML = '<option value="">Selecciona...</option>' + zonas()
      .map(zone => `<option value="${esc(zone)}">${esc(zone)}</option>`).join('');
    $('sdImpactGrid').innerHTML = Object.keys(impactos()).map(key => `
      <button type="button" class="sd-sev-opt" data-impacto="${esc(key)}" style="--sev:${esc(impactos()[key].color || '#007a50')}">
        ${esc(impactos()[key].label || key)}
      </button>
    `).join('');
  }

  function setImpact(value) {
    $('sdNewImpacto').value = value || '';
    document.querySelectorAll('#sdImpactGrid [data-impacto]').forEach(button => {
      button.classList.toggle('active', button.dataset.impacto === value);
    });
    syncRail();
  }

  async function openNewSanction() {
    const modal = $('sdNewModal');
    modal.style.display = 'flex';
    $('sdNewSave').disabled = true;
    $('sdNewSave').textContent = 'Cargando...';

    try {
      await loadSources();
      resetForm();
      $('sdNewSave').disabled = false;
      $('sdNewSave').textContent = 'Registrar sancion';
      $('sdNewWorker').focus();
    } catch (error) {
      alert(error.message);
      $('sdNewSave').disabled = false;
      $('sdNewSave').textContent = 'Registrar sancion';
    }
  }

  function resetForm() {
    state.trabajadorId = 0;
    state.evidencePath = '';
    $('sdNewWorker').value = '';
    $('sdNewCargo').value = '';
    $('sdNewTipo').value = 'amonestacion_escrita';
    $('sdNewFechaUnica').value = today();
    $('sdNewIni').value = today();
    $('sdNewFin').value = today();
    $('sdNewFile').value = '';
    $('sdNewPunto').value = '';
    $('sdNewCompetencia').value = '';
    $('sdNewTurno').value = 'dia';
    $('sdNewFechaInc').value = today();
    $('sdNewZona').value = '';
    $('sdNewDetalle').value = '';
    setImpact('');
    populateIncidences();
    $('sdNewInc').value = '';
    syncTipo();
    syncAnexo();
  }

  async function saveNewSanction() {
    const tipo = $('sdNewTipo').value;
    const isSuspension = tipo === 'suspension';
    const payload = {
      colaborador_id: state.trabajadorId,
      incidencia_id: Number($('sdNewInc').value || 0),
      tipo_sancion: tipo,
      fecha_inicio: isSuspension ? $('sdNewIni').value : $('sdNewFechaUnica').value,
      fecha_fin: isSuspension ? $('sdNewFin').value : $('sdNewFechaUnica').value,
      punto_mejorar: $('sdNewPunto').value,
      impacto: $('sdNewImpacto').value,
      turno: $('sdNewTurno').value,
      fecha_incidencia: $('sdNewFechaInc').value,
      zona_trabajo: $('sdNewZona').value,
      detalle: $('sdNewDetalle').value,
      evidencia_path: state.evidencePath
    };

    if (!payload.colaborador_id) { alert('Selecciona un trabajador.'); return; }
    if (!payload.fecha_inicio || !payload.fecha_fin || payload.fecha_fin < payload.fecha_inicio) {
      alert(isSuspension ? 'Completa fecha inicio y fecha fin de la suspension.' : 'Indica la fecha de la amonestacion.');
      return;
    }
    if (payload.incidencia_id && (!payload.punto_mejorar || !payload.impacto || !payload.fecha_incidencia || !payload.zona_trabajo)) {
      alert('La incidencia anexada debe tener evaluacion, fecha y zona.');
      return;
    }

    const button = $('sdNewSave');
    button.disabled = true;
    button.textContent = 'Registrando...';
    try {
      const response = await fetch('../api/create_sancion_disciplinaria.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (!result.success) throw new Error(result.error || 'No se pudo registrar la sancion.');
      location.reload();
    } catch (error) {
      alert(error.message);
      button.disabled = false;
      button.textContent = 'Registrar sancion';
    }
  }

  function buildNewSanctionForm() {
    const modal = $('sdNewModal');
    if (!modal) return;
    modal.className = 'sd-dialog sd-dialog--wide';
    modal.removeAttribute('style');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'sdNewTitle');
    modal.innerHTML = `
      <div class="sd-form-shell">
        <aside class="sd-form-rail">
          <div class="sd-rail-kicker">FICHA - SANCION</div>
          <div class="sd-rail-person">
            <div class="sd-rail-avatar" id="sdRailAvatar">-</div>
            <div>
              <div class="sd-rail-name" id="sdRailName">Sin trabajador</div>
              <div class="sd-rail-sub" id="sdRailCargo">Selecciona un trabajador</div>
            </div>
          </div>
          <div class="sd-rail-block"><span>Nivel de impacto</span><strong id="sdRailImpacto">No aplica</strong></div>
          <div class="sd-rail-block"><span>Competencia afectada</span><strong id="sdRailComp">Solo si se anexa incidencia</strong></div>
          <div class="sd-rail-foot"><span>Administrador que registra</span><strong>Sistemas CSP</strong></div>
        </aside>
        <section class="sd-form-main">
          <div class="sd-form-head">
            <div>
              <h3 id="sdNewTitle">Registrar sancion disciplinaria</h3>
              <p>Selecciona al trabajador y define la medida aplicada.</p>
            </div>
            <button id="sdNewX" class="sd-close" type="button" aria-label="Cerrar">x</button>
          </div>
          <div class="sd-form-body">
            <section class="sd-sec">
              <div class="sd-sec-head"><span>01</span>Trabajador</div>
              <div class="sd-grid-2">
                <label>Nombre
                  <div class="sd-worker-picker">
                    <input id="sdNewWorker" type="text" autocomplete="off" placeholder="Buscar por nombre, codigo o DNI...">
                    <div id="sdNewWorkerPanel" class="sd-pick-panel"></div>
                  </div>
                </label>
                <label>Cargo<input id="sdNewCargo" type="text" readonly placeholder="-"></label>
              </div>
            </section>
            <section class="sd-sec">
              <div class="sd-sec-head"><span>02</span>Sancion disciplinaria</div>
              <div class="sd-grid-2">
                <label>Tipo de sancion
                  <select id="sdNewTipo">
                    <option value="amonestacion_escrita">Amonestacion escrita</option>
                    <option value="suspension">Suspension</option>
                  </select>
                </label>
                <label>Evidencia de sancion<input id="sdNewFile" type="file" accept=".pdf,image/jpeg,image/png"></label>
              </div>
              <div id="sdFechaUnicaWrap" class="sd-grid-2" style="margin-top:12px">
                <label>Fecha de registro<input id="sdNewFechaUnica" type="date"></label>
              </div>
              <div id="sdFechasSuspension" class="sd-grid-3" style="margin-top:12px">
                <label>Fecha inicio<input id="sdNewIni" type="date"></label>
                <label>Fecha fin<input id="sdNewFin" type="date"></label>
                <label>Total aplicado<div class="sd-days" id="sdNewDays">0 dia(s)</div></label>
              </div>
            </section>
            <section class="sd-sec">
              <div class="sd-sec-head"><span>03</span>Declaracion / incidencia anexada</div>
              <label>Registro relacionado
                <select id="sdNewInc"><option value="">Sin incidencia anexada</option></select>
              </label>
            </section>
            <section class="sd-sec" id="sdSecEvaluacion">
              <div class="sd-sec-head"><span>04</span>Evaluacion</div>
              <div class="sd-grid-2">
                <label>Punto a mejorar<select id="sdNewPunto"></select></label>
                <label>Competencia afectada<input id="sdNewCompetencia" type="text" readonly placeholder="-"></label>
              </div>
              <input id="sdNewImpacto" type="hidden">
              <div class="sd-impact-grid" id="sdImpactGrid"></div>
            </section>
            <section class="sd-sec" id="sdSecContexto">
              <div class="sd-sec-head"><span>05</span>Contexto</div>
              <div class="sd-grid-2">
                <label>Turno
                  <select id="sdNewTurno"><option value="dia">Dia</option><option value="noche">Noche</option></select>
                </label>
                <label>Fecha de incidencia<input id="sdNewFechaInc" type="date"></label>
              </div>
              <label>Zona de trabajo<select id="sdNewZona"></select></label>
            </section>
            <section class="sd-sec" id="sdSecDetalle">
              <div class="sd-sec-head"><span>06</span>Detalle</div>
              <label>Observacion<textarea id="sdNewDetalle" maxlength="2000" placeholder="Describe el sustento de la sancion..."></textarea></label>
            </section>
          </div>
          <div class="sd-form-foot">
            <button id="sdNewCancel" class="sd-btn" type="button">Cancelar</button>
            <button id="sdNewSave" class="sd-btn sd-btn--primary" type="button">Registrar sancion</button>
          </div>
        </section>
      </div>`;
    buildCatalogControls();
  }

  document.addEventListener('DOMContentLoaded', () => {
    buildNewSanctionForm();
    const modal = $('sdNewModal');
    const add = $('sdNew');
    if (!modal || !add) return;

    add.className = 'sd-new';
    add.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Nueva sancion';
    add.addEventListener('click', openNewSanction);
    $('sdNewCancel').addEventListener('click', () => { modal.style.display = 'none'; });
    $('sdNewX').addEventListener('click', () => { modal.style.display = 'none'; });
    $('sdNewTipo').addEventListener('change', syncTipo);
    $('sdNewFechaUnica').addEventListener('change', syncTipo);
    $('sdNewIni').addEventListener('change', syncDays);
    $('sdNewFin').addEventListener('change', syncDays);
    $('sdNewWorker').addEventListener('focus', event => renderWorkers(event.target.value));
    $('sdNewWorker').addEventListener('input', event => renderWorkers(event.target.value));
    $('sdNewWorkerPanel').addEventListener('mousedown', event => {
      const row = event.target.closest('[data-worker]');
      if (row) selectWorker(row.dataset.worker);
    });
    $('sdNewInc').addEventListener('change', event => applyIncident(event.target.value));
    $('sdNewPunto').addEventListener('change', syncRail);
    $('sdImpactGrid').addEventListener('click', event => {
      const button = event.target.closest('[data-impacto]');
      if (button) setImpact(button.dataset.impacto);
    });
    $('sdNewFile').addEventListener('change', async event => {
      const file = event.target.files[0];
      if (!file) return;
      try { state.evidencePath = await uploadEvidence(file); }
      catch (error) { event.target.value = ''; alert(error.message); }
    });
    $('sdNewSave').addEventListener('click', saveNewSanction);
    document.addEventListener('click', event => {
      if (!event.target.closest('.sd-worker-picker') && $('sdNewWorkerPanel')) $('sdNewWorkerPanel').classList.remove('show');
    });
    new MutationObserver(iconizeActions).observe(document.body, { childList: true, subtree: true });
    iconizeActions();
  });
})();
