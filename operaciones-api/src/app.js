import express from 'express';
import cors from 'cors';
import { simulatedAuth } from './middlewares/auth.js';
import { notFound, errorHandler } from './middlewares/errorHandler.js';
import navesRoutes from './modules/operaciones/naves.routes.js';
import camposRoutes from './modules/operaciones/campos.routes.js';
import tallymanRoutes from './modules/tallyman/tallyman.routes.js';

export function createApp() {
  const app = express();
  app.use(cors());
  app.use(express.json());
  app.use(simulatedAuth); // Fase 1: puebla req.user desde headers

  app.get('/health', (_req, res) => res.json({ success: true, status: 'ok' }));
  app.use('/api/operaciones', navesRoutes);
  app.use('/api/operaciones', camposRoutes);
  app.use('/api/operaciones', tallymanRoutes);

  app.use(notFound);
  app.use(errorHandler);
  return app;
}
