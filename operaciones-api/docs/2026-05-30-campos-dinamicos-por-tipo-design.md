# Operaciones · Fase 2 — Información adicional por tipo de nave (backend)

**Fecha:** 2026-05-30
**Estado:** Aprobado
**Ámbito:** Solo backend, dentro de `operaciones-api/`. No toca la app PHP ni otras carpetas.

## Resumen

Permitir que el **Administrador** defina, por cada tipo de nave, un conjunto
**configurable en runtime** de campos adicionales (sin redeploy). Cada nave guarda
los valores de esos campos en una **columna JSON**, validados contra el catálogo
de definiciones. Caso de uso: formularios de detalle por tipo de nave.

## Decisiones

- **Modelo:** catálogo de definiciones (`campos_tipo_nave`) + valores en
  `naves.datos_adicionales` (JSON). Elegido sobre EAV por simplicidad y porque el
  uso es leer/escribir la nave completa (formularios), no reportería cruzada.
- **Campos dinámicos:** definidos por el admin vía API (no hardcode, no DDL por campo).
- **Tipos de dato soportados:** `texto`, `numero`, `fecha` (YYYY-MM-DD), `booleano`, `seleccion`.
- **Claves desconocidas:** se **rechazan** (400) al guardar valores (evita typos/basura).
- **Requeridos:** NO se exigen al crear la nave (datos opcionales en `POST /naves`);
  SÍ se exigen al "enviar el formulario" (`PUT /naves/:id/datos`).
- **Sin dependencias nuevas:** tests con el runner integrado `node --test`.

## Modelo de datos — `sql/002_campos_dinamicos.sql`

```sql
USE operaciones;

-- Catálogo de definiciones de campo por tipo de nave (gestionado por el Admin).
CREATE TABLE IF NOT EXISTS campos_tipo_nave (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo_nave_id  INT UNSIGNED NOT NULL,
  clave         VARCHAR(50)  NOT NULL,             -- nombre máquina, ej. 'teus'
  etiqueta      VARCHAR(100) NOT NULL,             -- label visible, ej. 'TEUs'
  tipo_dato     ENUM('texto','numero','fecha','booleano','seleccion')
                NOT NULL DEFAULT 'texto',
  requerido     TINYINT(1)   NOT NULL DEFAULT 0,
  opciones      JSON         NULL,                 -- solo 'seleccion': ["A","B"]
  orden         INT          NOT NULL DEFAULT 0,
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_campos_tipo_nave
    FOREIGN KEY (tipo_nave_id) REFERENCES tipos_nave (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  UNIQUE KEY uq_campo_tipo_clave (tipo_nave_id, clave),
  KEY idx_campos_tipo (tipo_nave_id, activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valores por nave (objeto clave→valor).
ALTER TABLE naves ADD COLUMN datos_adicionales JSON NULL AFTER estado;
```

> La migración se aplica una vez. `ADD COLUMN` sobre la tabla recién creada de Fase 1.

Valores huérfanos: si un campo se desactiva (`activo=0`), los valores ya guardados
en el JSON permanecen pero el validador (que solo mira campos activos) los ignora;
`GET /naves/:id` devuelve los campos activos para que el consumidor sepa cuáles rigen.

## Validador — `campos.validator.js`

`validarDatos(campos, datos, { requireAll })` → objeto normalizado o `ApiError(400)`.

- `campos`: definiciones **activas** del tipo `[{clave, tipo_dato, requerido, opciones}]`.
- `datos`: objeto entrante `{clave: valor}`.
- Reglas:
  1. Toda clave de `datos` debe existir en `campos`; si no → `400 "Campo desconocido: <clave>"`.
  2. Si `requireAll`, cada campo `requerido` debe venir con valor no vacío → `400`.
  3. Coacción/validación por `tipo_dato`:
     - `texto` → `String(v)`
     - `numero` → `Number(v)` finito, si no → `400`
     - `fecha` → `/^\d{4}-\d{2}-\d{2}$/` y fecha válida, si no → `400`
     - `booleano` → acepta `true/false/1/0/"true"/"false"` → `Boolean`
     - `seleccion` → `v ∈ opciones`, si no → `400`
  4. Devuelve solo las claves válidas, con el valor ya tipado (lo que se guarda como JSON).

## Endpoints

Prefijo: `/api/operaciones`. Auth simulada por headers (`x-user-role`, `x-user-name`).

### Definición de campos (rol **Administrador**; lectura para roles operativos)
| Método | Ruta | Rol | Cuerpo / efecto |
|---|---|---|---|
| GET | `/tipos-nave/:tipoId/campos` | operativos | lista campos activos del tipo (orden, luego id) |
| POST | `/tipos-nave/:tipoId/campos` | Administrador | `{clave, etiqueta, tipo_dato, requerido?, opciones?, orden?}` → 201 |
| PUT | `/tipos-nave/:tipoId/campos/:campoId` | Administrador | actualiza etiqueta/tipo_dato/requerido/opciones/orden/activo → 200 |
| DELETE | `/tipos-nave/:tipoId/campos/:campoId` | Administrador | soft-delete (`activo=0`) → 200 |

Validaciones de definición: `tipo_dato` válido; `clave` única por tipo (`[a-z0-9_]+`);
si `tipo_dato='seleccion'` → `opciones` array no vacío; el `tipoId` debe existir.

### Valores en la nave
| Método | Ruta | Rol | Efecto |
|---|---|---|---|
| POST | `/naves` *(mod)* | Admin/Supervisor | acepta `datos_adicionales?` opcional, validado con `requireAll=false` |
| GET | `/naves/:id` *(nuevo)* | operativos | `{ nave (incl. datos_adicionales), campos: [defs activas del tipo] }` |
| PUT | `/naves/:id/datos` *(nuevo)* | Admin/Supervisor | reemplaza `datos_adicionales`, validado con `requireAll=true` |

`GET /naves` y `GET /naves/:id/historial` incluyen `datos_adicionales` en el SELECT.

## Estructura de archivos

```
operaciones-api/
  sql/002_campos_dinamicos.sql                 (nuevo)
  src/modules/operaciones/
    campos.model.js        (nuevo)  SQL de campos_tipo_nave
    campos.validator.js    (nuevo)  validarDatos() + tests
    campos.controller.js   (nuevo)  CRUD de definiciones
    campos.routes.js       (nuevo)  rutas /tipos-nave/:tipoId/campos
    campos.validator.test.js (nuevo)  node --test
    naves.model.js         (mod)    datos_adicionales en selects; setDatos(); obtener campos del tipo
    naves.controller.js    (mod)    crear acepta datos; obtenerUno; actualizarDatos
    naves.routes.js        (mod)    GET /naves/:id ; PUT /naves/:id/datos
  src/app.js               (mod)    montar campos.routes
  package.json             (mod)    "test": "node --test"
```

## Pruebas

- **Unitarias** (`node --test`) del validador: claves desconocidas, requeridos,
  cada `tipo_dato` (ok y error), `seleccion` fuera de opciones, normalización.
- **End-to-end** con curl (servidor real + MySQL): definir un campo (Admin),
  403 si lo intenta otro rol, crear nave con datos válidos/ inválidos, `PUT /datos`
  exigiendo requeridos, `GET /naves/:id` devolviendo nave+campos.

## Criterios de éxito

1. El admin crea/edita/desactiva campos de un tipo vía API (y solo el admin: 403 a otros).
2. Una nave guarda y devuelve `datos_adicionales` validados contra el catálogo.
3. `PUT /naves/:id/datos` rechaza datos inválidos/incompletos con 400 claro.
4. `GET /naves/:id` entrega nave + definiciones activas (todo para un formulario).
5. Fase 1 sigue intacta; la app PHP no se toca.

## Notas

- El proyecto **no es repo git** (no se commitea el spec).
- Fase 3 (frontend de los formularios) queda fuera de alcance.
