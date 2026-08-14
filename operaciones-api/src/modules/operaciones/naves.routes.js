import { Router } from 'express';
import { requireRole } from '../../middlewares/auth.js';
import {
  crearNave, listarNaves, actualizarNave, actualizarTipoNave, actualizarMuelleActividadNave, eliminarNave, registrarAvance, editarAvance, eliminarAvance,
  historialNave, obtenerNave, actualizarDatos, listarTipos,
} from './naves.controller.js';

const router = Router();

// Catálogo de tipos de nave (para selects del front): roles operativos
router.get('/tipos-nave', requireRole('Administrador', 'Supervisor', 'Coordinador'), listarTipos);

// Crear nave: Administrador o Supervisor
router.post('/naves', requireRole('Administrador', 'Supervisor'), crearNave);

// Listar naves (activas/programadas): roles operativos
router.get('/naves', requireRole('Administrador', 'Supervisor', 'Coordinador'), listarNaves);

// Registrar avance de turno: Coordinador
router.post('/naves/:id/avances', requireRole('Coordinador'), registrarAvance);

// Editar/eliminar avance: Administrador o Supervisor (corrección/auditoría)
router.put('/naves/:id/avances/:avId', requireRole('Administrador', 'Supervisor'), editarAvance);
router.delete('/naves/:id/avances/:avId', requireRole('Administrador', 'Supervisor'), eliminarAvance);

// Historial de avances (+ resumen de control): roles operativos
router.get('/naves/:id/historial', requireRole('Administrador', 'Supervisor', 'Coordinador'), historialNave);

// Detalle de una nave (incl. datos adicionales) + definiciones de campos de su tipo
router.get('/naves/:id', requireRole('Administrador', 'Supervisor', 'Coordinador'), obtenerNave);

// Reemplazar los datos adicionales (formulario por tipo): roles operativos (incl. Coordinador,
// que puede editar la nave desde el detalle y propagar a sus actividades)
router.put('/naves/:id/datos', requireRole('Administrador', 'Supervisor', 'Coordinador'), actualizarDatos);

// Actualizar SOLO el tipo de nave (propagación desde el registro tallyman): roles operativos
router.put('/naves/:id/tipo', requireRole('Administrador', 'Supervisor', 'Coordinador'), actualizarTipoNave);

// Actualizar SOLO el muelle/actividad (propagación desde el registro tallyman): roles operativos
router.put('/naves/:id/muelle-actividad', requireRole('Administrador', 'Supervisor', 'Coordinador'), actualizarMuelleActividadNave);

// Editar datos base de la nave (nombre, tipo, muelle, ventanas, estado): roles operativos.
// El Coordinador también puede editar; la edición se propaga a las actividades de la nave.
router.put('/naves/:id', requireRole('Administrador', 'Supervisor', 'Coordinador'), actualizarNave);

// Eliminar nave (y sus avances en cascada): roles operativos
router.delete('/naves/:id', requireRole('Administrador', 'Supervisor', 'Coordinador'), eliminarNave);

export default router;
