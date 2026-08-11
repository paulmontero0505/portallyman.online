import { Router } from 'express';
import { requireRole } from '../../middlewares/auth.js';
import {
  listarActividades, listarRegistros, listarRegistrosRango, crearRegistro, editarRegistro,
  eliminarRegistro, guardarIncidencia, obtenerIncidencia, relevoTurno,
  estadoSugerido, navesPatioHandler,
} from './tallyman.controller.js';

const router = Router();
const OPERATIVOS = ['Administrador', 'Supervisor', 'Coordinador'];

// Catálogo de actividades (para selects del front)
router.get('/tallyman/actividades', requireRole(...OPERATIVOS), listarActividades);

// Histórico transversal de registros por rango (solo Administrador).
router.get('/tallyman/registros-rango', requireRole('Administrador'), listarRegistrosRango);

// Registros del turno (lectura): roles operativos
router.get('/tallyman/registros', requireRole(...OPERATIVOS), listarRegistros);

// Relevo del turno (payload completo para la vista): roles operativos
router.get('/tallyman/relevo', requireRole(...OPERATIVOS), relevoTurno);

// Status sugerido para el próximo registro de una nave+actividad: roles operativos
router.get('/tallyman/estado-sugerido', requireRole(...OPERATIVOS), estadoSugerido);

// Naves con descarga interna (planned patio) + nombres activos de patio
router.get('/tallyman/naves-patio', requireRole(...OPERATIVOS), navesPatioHandler);

// Crear registro: roles operativos (Coordinador en su turno; Admin/Supervisor también)
router.post('/tallyman/registros', requireRole(...OPERATIVOS), crearRegistro);

// Editar/eliminar: roles operativos (Coordinador su turno; Admin/Supervisor corrección)
router.put('/tallyman/registros/:id', requireRole(...OPERATIVOS), editarRegistro);
router.delete('/tallyman/registros/:id', requireRole(...OPERATIVOS), eliminarRegistro);

// Incidencias del turno
router.get('/tallyman/incidencias', requireRole(...OPERATIVOS), obtenerIncidencia);
router.post('/tallyman/incidencias', requireRole(...OPERATIVOS), guardarIncidencia);

export default router;
