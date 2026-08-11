# Módulo de Colaboradores · Diseño

**Fecha:** 2026-05-27
**Estado:** Aprobado para implementación
**Alcance:** Primer paso para almacenar información de trabajadores. Reemplaza la fuente de datos hardcoded en `js/data-source.js` por una tabla MySQL persistente, con interfaz CRUD dedicada e importación masiva desde Excel.

---

## 1. Objetivo

Crear un módulo que permita:

1. Mantener un catálogo maestro de colaboradores en base de datos.
2. Importar colaboradores en bloque desde un archivo Excel (.xlsx) con vista previa y UPSERT por DNI.
3. Realizar CRUD individual (alta, edición, eliminación) desde una página dedicada accesible solo a Administradores.
4. Sustituir el seed `plantilla` de `js/data-source.js` por una API real, de modo que la pestaña "Plantilla" del `index.php` siga funcionando sin regresiones visuales.

Este módulo es la **fundación** sobre la que en pasos posteriores se construirán: contacto, asistencia, historial, asignación automática a turno, etc. Por eso el diseño prioriza un esquema simple y extensible.

---

## 2. Decisiones de diseño

| Tema | Decisión | Razón |
|---|---|---|
| Relación con "Plantilla" | Colaboradores reemplaza el seed JS; la pestaña Plantilla sigue existiendo como vista read-only que consume la nueva API. | Evita duplicar fuente de verdad. |
| Formato de import | Solo `.xlsx`. | Lo que usa el usuario en la realidad operativa. |
| Parser de Excel | SheetJS (`xlsx.full.min.js`) en navegador. | El proyecto no usa Composer; SheetJS evita dependencias PHP. Permite preview antes de tocar la BD. |
| Columnas mínimas | Nombre, DNI, Función Principal, Cuadrilla. | Mínimo viable; coincide con lo que ya muestra la UI actual. |
| Política de duplicados | UPSERT por DNI (actualizar el existente). | Mantiene la lista sincronizada con la fuente externa (planilla del usuario). |
| Vista previa | Obligatoria antes de confirmar. | Evita sobreescrituras accidentales. |
| Acceso | Solo `Administrador`. | Es administración del catálogo, no operación diaria. |
| Generación de código humano (`ST-001`) | Auto-incrementado en backend al insertar. | Compatible con los IDs que ya usa `js/estiba.js`. |

---

## 3. Arquitectura

### 3.1 Esquema de base de datos

Archivo: `sql/002_colaboradores.sql`

```sql
USE estiba_turno;

CREATE TABLE IF NOT EXISTS colaboradores (
  id                 INT(11)      NOT NULL AUTO_INCREMENT,
  codigo             VARCHAR(20)  NULL,                 -- ST-001, ST-002, … (poblado post-insert)
  nombre             VARCHAR(150) NOT NULL,
  dni                VARCHAR(8)   NOT NULL,
  funcion_principal  VARCHAR(60)  NOT NULL,
  cuadrilla          VARCHAR(20)  NOT NULL,
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dni (dni),
  UNIQUE KEY uq_codigo (codigo),
  KEY ix_cuadrilla (cuadrilla),
  KEY ix_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`codigo` es `NULL`-able a propósito: MySQL permite múltiples `NULL` en una columna `UNIQUE`, por lo que en el momento del `INSERT` no causa conflicto. Se rellena inmediatamente vía trigger.

**Seed inicial:** los 12 colaboradores que actualmente viven en `js/data-source.js` (líneas 85-98) se cargan con `INSERT IGNORE` para no duplicar si el script se corre varias veces. Para el seed se asigna `codigo` explícitamente (ST-001 a ST-012) para preservar los IDs ya referenciados por `personal` en `data-source.js`.

**Generación de `codigo` para inserts posteriores:** trigger `AFTER INSERT` que actualiza la fila recién creada:

```sql
CREATE TRIGGER trg_colaboradores_codigo
AFTER INSERT ON colaboradores
FOR EACH ROW
BEGIN
  IF NEW.codigo IS NULL THEN
    UPDATE colaboradores SET codigo = CONCAT('ST-', LPAD(NEW.id, 3, '0')) WHERE id = NEW.id;
  END IF;
END;
```

Nota: el `IF` permite que el seed inicial (que pasa `codigo` explícito) no sea pisado.

### 3.2 Estructura de archivos

**Nuevos:**

```
sql/
  002_colaboradores.sql              # Schema + seed + trigger de código

pages/
  colaboradores.php                  # Página dedicada (CRUD + import)

api/
  get_colaboradores.php              # GET listado completo
  save_colaborador.php               # POST alta/edición individual
  delete_colaborador.php             # POST eliminación
  import_colaboradores.php           # POST importación masiva (UPSERT)

js/vendor/
  xlsx.full.min.js                   # SheetJS local (~900KB)
```

**Modificados:**

```
includes/sidebar.php                 # Nueva entrada "Colaboradores" en Administración
index.php                            # Modal de plantilla: redirigir botón "Nuevo" a página dedicada
js/data-source.js                    # Eliminar array `plantilla`
js/estiba.js                         # bootPlantilla() consume API en lugar de seed
```

---

## 4. UI/UX

### 4.1 Página `pages/colaboradores.php`

Sigue el patrón visual de `pages/usuarios.php` para mantener coherencia.

**Estructura:**

```
┌─────────────────────────────────────────────────────────────┐
│ HERO (gradient navy)                                        │
│ ADMINISTRACIÓN · COLABORADORES                              │
│ Catálogo maestro de personal                                │
│ Base maestra del personal. Importable desde planilla.       │
│                       [Importar Excel]  [+ Nuevo colaborador]│
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ KPI strip                                                   │
│  Total: 124 · Activos: 118 · Inactivos: 6                  │
│  Cuadrillas: A, B, C  ·  Funciones distintas: 8            │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ 🔍 Buscar (nombre, DNI, función, cuadrilla)   [Cuadrilla ▾]│
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Tabla:                                                      │
│  Colaborador (avatar+nombre+código)                         │
│  DNI                                                        │
│  Función                                                    │
│  Cuadrilla (badge)                                          │
│  Estado (Activo/Inactivo badge)                             │
│  Acciones (Editar, Eliminar)                                │
└─────────────────────────────────────────────────────────────┘
```

**Modal "Nuevo / Editar colaborador":**

Campos: Nombre completo, DNI (8 dígitos), Cuadrilla, Función Principal (select desde `funcionesDisponibles`), Estado.

Validación cliente: DNI exactamente 8 dígitos numéricos. Validación servidor: DNI único.

### 4.2 Modal "Importar desde Excel"

**Paso 1 · Selección de archivo:**

- Drop zone + file picker (acepta `.xlsx`).
- Enlace "Descargar plantilla Excel" que genera (en navegador con SheetJS) un .xlsx vacío con los encabezados correctos.
- Subtítulo: "Encabezados esperados: Nombre · DNI · Función · Cuadrilla".

**Paso 2 · Vista previa:**

- Contador resumen: `✓ 80 nuevos · ↻ 12 actualizar · ⚠ 3 con errores`.
- Tabla con todas las filas del archivo:
  - Verde (`✓ Nuevo`): DNI no existe, fila válida.
  - Ámbar (`↻ Actualizar`): DNI existe, se sobreescribirá.
  - Rojo (`⚠ Error`): fila inválida, mensaje debajo. No se procesa.
- Filtro "Mostrar: Todos / Solo nuevos / Solo actualizar / Solo errores".
- Botón principal: "Confirmar X filas" (suma de verdes + ámbares). Las rojas no se incluyen.
- Botón "← Volver" reabre el paso 1; "Cancelar" cierra el modal.

### 4.3 Sidebar

Modificar `includes/sidebar.php` para añadir, bajo la sección "Administración" (visible solo a `Administrador`):

```
Administración
  Colaboradores    → pages/colaboradores.php
  Usuarios         → pages/usuarios.php
```

La entrada "Plantilla" del sidebar (que apunta a `index.php?tab=plantilla`) se mantiene intacta.

---

## 5. Flujo de importación

### 5.1 Lectura del archivo (cliente)

```js
const buf = await file.arrayBuffer();
const wb = XLSX.read(buf, { type: 'array' });
const sheet = wb.Sheets[wb.SheetNames[0]];
const rows = XLSX.utils.sheet_to_json(sheet, { defval: '' });
```

### 5.2 Mapeo de encabezados (tolerante)

```js
const HEADER_MAP = {
  nombre:    ['Nombre','NOMBRE','Nombres','Nombre completo','Apellidos y Nombres'],
  dni:       ['DNI','D.N.I.','Documento','Cédula'],
  funcion:   ['Función','Funcion','Función Principal','Cargo','Puesto'],
  cuadrilla: ['Cuadrilla','Turno','Grupo','Equipo']
};
```

Para cada fila, los nombres de columna se normalizan según `HEADER_MAP`. Si el archivo no tiene ninguna columna reconocible para Nombre o DNI, el archivo entero se rechaza con un toast claro y un enlace a descargar plantilla.

### 5.3 Validación por fila (cliente)

Reglas:

- `nombre`: requerido, ≥ 3 caracteres, ≤ 150.
- `dni`: requerido, exactamente 8 dígitos numéricos (`/^\d{8}$/`).
- `funcion`: requerido, ≤ 60 caracteres.
- `cuadrilla`: requerido, ≤ 20 caracteres.
- Detección de duplicados intra-archivo: si el mismo DNI aparece dos veces, la segunda aparición se marca como error ("DNI duplicado en fila N del archivo").

El estado de cada fila se calcula así:

```
errors.length > 0          → 'error'    (rojo, descartado)
DNI ya existe en BD        → 'update'   (ámbar)
DNI nuevo                  → 'new'      (verde)
```

El set de DNIs existentes se obtiene de la lista ya cargada en la página (no requiere fetch adicional).

### 5.4 Envío al backend

Tras confirmar, el navegador envía solo las filas con status `new` o `update`:

```json
POST /api/import_colaboradores.php
{
  "rows": [
    { "nombre": "Pedro Ramírez", "dni": "45678901",
      "funcion": "Winchero", "cuadrilla": "A" },
    ...
  ]
}
```

### 5.5 Backend (UPSERT transaccional)

`api/import_colaboradores.php`:

1. `require_admin()`.
2. Leer JSON, validar que `rows` es array no vacío y no excede 1000 filas.
3. `mysqli_begin_transaction($conn)`.
4. Por cada fila:
   - Re-validar (defensivo) los mismos checks del cliente. Si alguna fila falla en servidor → rollback completo y respuesta `{ "success": false, "error": "Fila N inválida: …" }`. El cliente ya validó, así que solo se llega aquí por manipulación o race; abortar es lo correcto.
   - Ejecutar prepared statement con `INSERT ... ON DUPLICATE KEY UPDATE`:
     ```sql
     INSERT INTO colaboradores (nombre, dni, funcion_principal, cuadrilla, activo)
     VALUES (?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE
       nombre = VALUES(nombre),
       funcion_principal = VALUES(funcion_principal),
       cuadrilla = VALUES(cuadrilla),
       updated_at = CURRENT_TIMESTAMP
     ```
   - `codigo` no se incluye en el INSERT; el trigger `AFTER INSERT` lo rellena automáticamente. En UPDATE (DNI ya existente) `codigo` se conserva del registro previo.
   - Contar `mysqli_affected_rows()`: 1 = insertado, 2 = actualizado (semántica de `ON DUPLICATE KEY UPDATE`).
5. `mysqli_commit($conn)`.
6. Respuesta:
   ```json
   { "success": true, "inserted": 80, "updated": 12, "total": 92 }
   ```

Cualquier excepción → `mysqli_rollback` y respuesta `{ "success": false, "error": "…" }`.

### 5.6 Feedback al usuario

- Toast verde: "Importación completada: 80 nuevos, 12 actualizados".
- Modal se cierra.
- La tabla principal se recarga llamando a `get_colaboradores.php`.

### 5.7 Manejo de errores

| Caso | Manejo |
|---|---|
| Archivo no es `.xlsx` válido | SheetJS lanza error → toast rojo "Archivo inválido" |
| Hoja vacía (0 filas de datos) | Toast "El archivo no tiene filas para importar" |
| Sin columnas reconocibles | Toast "No se encontró columna Nombre/DNI" + link a descargar plantilla |
| Más de 1000 filas | Bloqueo cliente con sugerencia de dividir el archivo |
| Pérdida de conexión durante POST | Rollback automático, toast "Error de red — ningún cambio guardado" |
| Fila válida en cliente pero rechazada en servidor | Respuesta lista los DNIs rechazados; toast con conteo |

---

## 6. APIs (contratos)

### 6.1 `GET api/get_colaboradores.php`

**Request:** sin parámetros.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "codigo": "ST-001",
      "nombre": "Juan Pérez Quispe",
      "dni": "45123678",
      "funcion_principal": "Winchero",
      "cuadrilla": "A",
      "activo": 1,
      "created_at": "2026-05-27 10:30:00",
      "updated_at": "2026-05-27 10:30:00"
    }
  ]
}
```

Ordenado por `cuadrilla ASC, nombre ASC`. Devuelve todos (activos e inactivos); el filtrado de "solo activos" se hace en cliente.

### 6.2 `POST api/save_colaborador.php`

**Request:**
```json
{
  "id": 0,
  "nombre": "Juan Pérez",
  "dni": "45123678",
  "funcion_principal": "Winchero",
  "cuadrilla": "A",
  "activo": 1
}
```

`id = 0` → alta. `id > 0` → edición.

**Response success:** `{ "success": true, "id": 13, "codigo": "ST-013" }`
**Response error:** `{ "success": false, "error": "DNI ya existe" }`

Solo Administrador.

### 6.3 `POST api/delete_colaborador.php`

**Request:** `{ "id": 13 }`
**Response:** `{ "success": true }`

Solo Administrador. Borrado físico (no soft delete) — en este primer paso no hay relaciones FK que proteger.

### 6.4 `POST api/import_colaboradores.php`

Definido en sección 5.5.

---

## 7. Integración con la pestaña "Plantilla" del index.php

### 7.1 Cambios en `js/data-source.js`

Eliminar el array `plantilla` (líneas 82-98). El objeto `window.__EstibaDataSource` queda con: `turnoLabel`, `limitesMin`, `funcionesDisponibles`, `ubicacionesDisponibles`, `personal`. Sin `plantilla`.

### 7.2 Cambios en `js/estiba.js`

En el arranque del módulo (probablemente función `boot()` o equivalente), antes de renderizar la pestaña Plantilla, hacer:

```js
const res = await fetch('api/get_colaboradores.php', { cache: 'no-store' });
const data = await res.json();
__EstibaDataSource.plantilla = (data.data || []).map(c => ({
  id: c.codigo,                          // "ST-001"
  nombre: c.nombre,
  dni: c.dni,
  funcionPrincipal: c.funcion_principal,
  cuadrilla: c.cuadrilla,
  activo: !!c.activo
}));
```

Esto preserva el formato exacto que el resto del JS espera.

### 7.3 Modal de alta en `index.php` (líneas 146-193)

El modal actual permite crear colaboradores desde el tab Plantilla. Se modifica el botón "Nuevo colaborador" del toolbar del tab para que redirija a `pages/colaboradores.php` en lugar de abrir el modal. Razón: centraliza la administración en una sola página, evita duplicar lógica de CRUD, y respeta el control de acceso (solo Admin puede crear desde la página dedicada).

El modal HTML actual queda inerte; puede eliminarse en una iteración posterior. Por este spec, se deja para minimizar superficie de cambio.

### 7.4 Compatibilidad con el módulo de Turno actual

El array `personal` en `js/data-source.js` (los que están en turno) sigue siendo seed. Los IDs `ST-001`, `ST-002`… que aparecen en `personal` coinciden con los `codigo` de la nueva tabla. La sincronización personal ↔ colaboradores se hará en un siguiente módulo, fuera del alcance de este spec.

---

## 8. Orden de implementación recomendado

1. **Crear schema y seed:** `sql/002_colaboradores.sql` (tabla + trigger + seed con los 12 actuales).
2. **APIs CRUD básicas:** `get_colaboradores.php`, `save_colaborador.php`, `delete_colaborador.php`.
3. **Página `pages/colaboradores.php`** con CRUD funcional (sin import todavía).
4. **Sidebar:** añadir entrada "Colaboradores" en Administración.
5. **SheetJS local:** descargar `xlsx.full.min.js` a `js/vendor/`.
6. **API de import:** `import_colaboradores.php`.
7. **Modal de import** en la página: paso 1 (selección) + paso 2 (preview) + confirmación.
8. **Integración con tab Plantilla:** modificar `js/data-source.js`, `js/estiba.js`, redirigir botón "Nuevo" de `index.php`.
9. **Verificación end-to-end:**
   - Login como Admin → entrar a Colaboradores → ver los 12 seed.
   - Crear, editar, eliminar manualmente.
   - Importar un .xlsx de prueba con 5 nuevos + 2 que sobreescriben DNI existente.
   - Verificar que el tab Plantilla del index muestra todos (incluidos los recién importados).

---

## 9. Lo que NO está en este alcance

Explícitamente fuera para evitar scope creep:

- Soft delete / historial de cambios.
- Carga de fotos/avatares.
- Campos adicionales (teléfono, email, fecha de ingreso, dirección).
- Sincronización con el módulo de Turno (asignación automática).
- Exportación de colaboradores a Excel.
- Filtros avanzados (rangos, multi-cuadrilla).
- Roles internos del colaborador (líder de cuadrilla, certificaciones, etc.).
- Bulk delete o bulk inactivar.

Cualquiera de estas funciones se tratará en specs posteriores.
