(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) root.EvadesBloquesModel = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  function blockProgress(evaluations) {
    const rows = Array.isArray(evaluations) ? evaluations : [];
    const complete = rows.filter(row => row && row.completa === true).length;
    const total = rows.length;
    const percent = total > 0 ? Math.round((complete / total) * 100) : 0;
    return { complete, total, percent };
  }

  function createWorkspace(block) {
    const source = block || {};
    const evaluations = Array.isArray(source.evaluaciones) ? source.evaluaciones : [];
    return {
      block: source,
      selectedId: evaluations.length ? Number(evaluations[0].id) : null,
      dirty: false,
      needsConfirmation: false,
      pendingSelectionId: null,
      editable: source.estado !== 'cerrado',
    };
  }

  function markDirty(state) {
    return { ...state, dirty: true };
  }

  function selectEvaluation(state, id) {
    const targetId = Number(id);
    if (state.dirty && targetId !== state.selectedId) {
      return { ...state, needsConfirmation: true, pendingSelectionId: targetId };
    }
    return { ...state, selectedId: targetId, needsConfirmation: false, pendingSelectionId: null };
  }

  function confirmSelection(state) {
    if (!state.needsConfirmation || state.pendingSelectionId === null) return state;
    return {
      ...state,
      selectedId: state.pendingSelectionId,
      pendingSelectionId: null,
      needsConfirmation: false,
      dirty: false,
    };
  }

  function scoreFormula(increment, discount) {
    const inc = Math.max(0, Math.min(4, Number(increment) || 0));
    const dec = Math.max(0, Math.min(10, Number(discount) || 0));
    return { base: 6, increment: inc, discount: dec, final: Math.max(0, Math.min(10, 6 + inc - dec)) };
  }

  function coverageSummary(source) {
    const data = source || {};
    const total = Number(data.total_competencias) || 0;
    const sufficient = Number(data.suficiente) || 0;
    return {
      total,
      sufficient,
      partial: Number(data.parcial) || 0,
      missing: Number(data.sin_fuente) || 0,
      percent: total > 0 ? Math.round((sufficient / total) * 100) : 0,
    };
  }

  function evidenceLabel(item) {
    const ev = item || {};
    const date = ev.fecha ? ` · ${ev.fecha}` : '';
    const cross = ev.es_cruce ? 'Cruce · ' : '';
    const description = ev.descripcion || ev.detalle || ev.punto_mejorar || '';
    if (ev.tipo === 'incidencia') return `${cross}Incidencia${date}${ev.impacto ? ` · ${ev.impacto}` : ''}${description ? ` · ${description}` : ''}`;
    if (ev.tipo === 'reconocimiento') return `Reconocimiento${date}${ev.valor ? ` · +${ev.valor}` : ''}${description ? ` · ${description}` : ''}`;
    if (ev.tipo === 'ept') {
      const score = ev.promedio ?? ev.valor;
      const samples = ev.muestras ?? ev.n;
      return `Evaluación en puesto${score != null ? ` · ${score}/5` : ''}${samples ? ` · ${samples} muestras` : ''}${date}`;
    }
    if (ev.tipo === 'asistencia') return `Asistencia${date}${ev.impacto ? ` · ${ev.impacto}` : ''}${description ? ` · ${description}` : ''}`;
    if (ev.tipo === 'propuesta') return `Propuesta${date}${ev.valor ? ` · +${ev.valor}` : ''}${description ? ` · ${description}` : ''}`;
    if (ev.tipo === 'apreciacion') {
      const level = ev.nivel ?? ev.valor;
      return `Apreciación ${ev.direccion || ''}${level ? ` · +${level}` : ''}${ev.impacto ? ` · ${ev.impacto}` : ''}${description ? ` · ${description}` : ''}`.trim();
    }
    if (ev.tipo === 'bono_evaluacion_diaria') return `Evaluaciones en puesto · ${ev.n || 0} registros · promedio ${ev.promedio || 0}`;
    return description || 'Evidencia registrada';
  }

  return { blockProgress, createWorkspace, markDirty, selectEvaluation, confirmSelection, scoreFormula, coverageSummary, evidenceLabel };
});
