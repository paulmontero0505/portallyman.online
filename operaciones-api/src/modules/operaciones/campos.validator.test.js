import { test } from 'node:test';
import assert from 'node:assert/strict';
import { validarDatos } from './campos.validator.js';

const campos = [
  { clave: 'teus', tipo_dato: 'numero', requerido: 1, opciones: null },
  { clave: 'naviera', tipo_dato: 'texto', requerido: 0, opciones: null },
  { clave: 'arribo', tipo_dato: 'fecha', requerido: 0, opciones: null },
  { clave: 'peligrosa', tipo_dato: 'booleano', requerido: 0, opciones: null },
  { clave: 'bandera', tipo_dato: 'seleccion', requerido: 0, opciones: ['PA', 'LR'] },
];

test('rechaza clave desconocida', () => {
  assert.throws(() => validarDatos(campos, { foo: 1 }), { status: 400 });
});

test('numero válido se normaliza a Number', () => {
  assert.equal(validarDatos(campos, { teus: '3500' }).teus, 3500);
});

test('numero inválido lanza 400', () => {
  assert.throws(() => validarDatos(campos, { teus: 'abc' }), { status: 400 });
});

test('fecha inválida lanza 400', () => {
  assert.throws(() => validarDatos(campos, { arribo: '2026-13-01' }), { status: 400 });
});

test('booleano coacciona "true" a true', () => {
  assert.equal(validarDatos(campos, { peligrosa: 'true' }).peligrosa, true);
});

test('seleccion fuera de opciones lanza 400', () => {
  assert.throws(() => validarDatos(campos, { bandera: 'XX' }), { status: 400 });
});

test('seleccion válida pasa', () => {
  assert.equal(validarDatos(campos, { bandera: 'PA' }).bandera, 'PA');
});

test('requireAll exige los requeridos', () => {
  assert.throws(() => validarDatos(campos, {}, { requireAll: true }), { status: 400 });
});

test('sin requireAll, requerido ausente se omite', () => {
  assert.deepEqual(validarDatos(campos, { naviera: 'MAERSK' }), { naviera: 'MAERSK' });
});
