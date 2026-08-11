const test = require('node:test');
const assert = require('node:assert/strict');
const {
  blockProgress,
  createWorkspace,
  markDirty,
  selectEvaluation,
  confirmSelection,
  scoreFormula,
  coverageSummary,
  evidenceLabel,
} = require('../assets/js/evades-bloques-model.js');

test('calculates completion progress from the frozen roster', () => {
  assert.deepEqual(blockProgress([
    { id: 1, completa: true },
    { id: 2, completa: false },
  ]), { complete: 1, total: 2, percent: 50 });
});

test('empty roster has zero progress without division errors', () => {
  assert.deepEqual(blockProgress([]), { complete: 0, total: 0, percent: 0 });
});

test('refuses silent navigation when current evaluation is dirty', () => {
  const initial = createWorkspace({ estado: 'modificado', evaluaciones: [{ id: 1 }, { id: 2 }] });
  const state = markDirty(initial);
  const next = selectEvaluation(state, 2);
  assert.equal(next.needsConfirmation, true);
  assert.equal(next.pendingSelectionId, 2);
  assert.equal(next.selectedId, 1);
});

test('confirmed navigation clears dirty state and selects the pending person', () => {
  const dirty = markDirty(createWorkspace({ estado: 'modificado', evaluaciones: [{ id: 1 }, { id: 2 }] }));
  const pending = selectEvaluation(dirty, 2);
  const confirmed = confirmSelection(pending);
  assert.equal(confirmed.selectedId, 2);
  assert.equal(confirmed.dirty, false);
  assert.equal(confirmed.needsConfirmation, false);
});

test('closed workspace is never editable', () => {
  const state = createWorkspace({ estado: 'cerrado', evaluaciones: [{ id: 1 }] });
  assert.equal(state.editable, false);
});

test('builds the visible score formula with the guide limits', () => {
  assert.deepEqual(scoreFormula(4, 2), { base: 6, increment: 4, discount: 2, final: 8 });
  assert.deepEqual(scoreFormula(6, 0), { base: 6, increment: 4, discount: 0, final: 10 });
});

test('summarizes coverage returned by the block preview', () => {
  assert.deepEqual(coverageSummary({ total_competencias: 20, suficiente: 7, parcial: 11, sin_fuente: 2 }), {
    total: 20, sufficient: 7, partial: 11, missing: 2, percent: 35,
  });
});

test('explains normalized evidence in plain language', () => {
  assert.match(evidenceLabel({ tipo: 'incidencia', fecha: '2026-08-02', impacto: 'moderado', punto_mejorar: 'Trabajo en PS', es_cruce: true }), /Cruce/);
  assert.match(evidenceLabel({ tipo: 'ept', promedio: 4.6, muestras: 5 }), /4.6/);
  assert.match(evidenceLabel({ tipo: 'apreciacion', direccion: 'positiva', nivel: 4, descripcion: 'Lideró el relevo' }), /Lideró el relevo/);
});
