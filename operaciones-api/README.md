# API · Módulo de Operaciones (Fase 1)

Backend Node.js (Express + mysql2) para registro de naves y seguimiento de sus avances.

## Requisitos
- Node.js 18+ y MySQL/MariaDB.

## Puesta en marcha
```bash
mysql -u root < sql/schema.sql      # crea BD `operaciones` + tablas + catálogo de tipos
cp .env.example .env                # ajusta credenciales si hace falta
npm install
npm run dev                         # o: npm start
```
Servidor en `http://localhost:4000` (configurable en `.env`).

## Autenticación (Fase 1 — simulada)
La identidad llega por headers; en producción se reemplaza por sesión/JWT **sin tocar los controladores**:
`x-user-role` (`Administrador` | `Supervisor` | `Coordinador`) y `x-user-name`.

## Endpoints
| Método | Ruta | Rol |
|---|---|---|
| POST | `/api/operaciones/naves` | Administrador, Supervisor |
| GET  | `/api/operaciones/naves` | roles operativos |
| POST | `/api/operaciones/naves/:id/avances` | Coordinador |
| GET  | `/api/operaciones/naves/:id/historial` | roles operativos |
