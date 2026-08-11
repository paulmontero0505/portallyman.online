import { Router } from 'express';
import { requireRole } from '../../middlewares/auth.js';
import { listarCampos, crearCampo, actualizarCampo, desactivarCampo } from './campos.controller.js';

const router = Router();

// Leer definiciones: roles operativos (para armar formularios)
router.get('/tipos-nave/:tipoId/campos', requireRole('Administrador', 'Supervisor', 'Coordinador'), listarCampos);

// Definir/editar/desactivar campos: solo Administrador (configuración del sistema)
router.post('/tipos-nave/:tipoId/campos', requireRole('Administrador'), crearCampo);
router.put('/tipos-nave/:tipoId/campos/:campoId', requireRole('Administrador'), actualizarCampo);
router.delete('/tipos-nave/:tipoId/campos/:campoId', requireRole('Administrador'), desactivarCampo);

export default router;
