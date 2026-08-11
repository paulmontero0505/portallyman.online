# Quitar DNI · Usar Código como identificador · Diseño

**Fecha:** 2026-05-28
**Estado:** Aprobado para implementación
**Alcance:** Eliminar el campo DNI del módulo de Colaboradores en toda la pila (BD, API, UI, plantilla Excel) y reemplazar su rol de identificador único por un campo `codigo` de ingreso manual.

---

## 1. Objetivo

Simplificar el alta y la importación de colaboradores quitando el requisito de DNI. El sistema solo pedirá tres datos operativos (**Nombre**, **Función**, **Cuadrilla**) más un **Código** que el usuario asigna manualmente y que actúa como identificador único de la fila.

Esta decisión responde a una necesidad operativa: no siempre se cuenta con el DNI al momento de cargar la plantilla, y el usuario maneja códigos internos propios (no necesariamente con el formato `ST-XXX` que el sistema auto-generaba).

---

## 2. Decisiones de diseño

| Tema | Decisión | Razón |
|---|---|---|
| Identificador único | `codigo` VARCHAR(20) NOT NULL UNIQUE, ingreso manual. | El usuario maneja nomenclatura propia. Reemplaza el rol que tenía `dni`. |
| Auto-generación de código | Eliminada. | Antes el sistema generaba `ST-XXX` tras el INSERT. Ya no aplica: el código viene del usuario en todos los flujos. |
| Validación del código | Alfanumérico estricto, regex `^[A-Za-z0-9]+$`, máx. 20 caracteres. | Evita errores tipográficos con espacios o símbolos invisibles. Mantiene el código como string corto y legible. |
| Campo DNI | Eliminado por completo (DROP COLUMN). | La tabla está vacía tras el TRUNCATE previo, así que el DROP es seguro y mantiene el esquema limpio. |
| Política de duplicados en import | UPSERT por `codigo` (igual semántica que antes con DNI). | Permite re-importar para corregir lotes sin duplicar filas. |
| Fuente de la plantilla Excel | `Código, Nombre, Función, Cuadrilla` en ese orden. | Código primero porque es la identidad. |

---

## 3. Cambios por capa

### 3.1 Base de datos

Nuevo script de migración `sql/003_remove_dni.sql`:

```sql
USE estiba_turno;

ALTER TABLE colaboradores DROP INDEX uq_dni;
ALTER TABLE colaboradores DROP COLUMN dni;
ALTER TABLE colaboradores MODIFY codigo VARCHAR(20) NOT NULL;
```

El script `sql/002_colaboradores.sql` queda como histórico; no se modifica. La migración se ejecuta una sola vez con:

```
mysql -uroot estiba_turno < sql/003_remove_dni.sql
```

### 3.2 Backend (PHP)

| Archivo | Cambios |
|---|---|
| `api/save_colaborador.php` | Quitar lectura/validación del campo `dni`. Añadir lectura y validación de `codigo` (alfanumérico, no vacío, ≤20). INSERT/UPDATE usa `codigo` provisto por el usuario. Traducir error de duplicado a mensaje claro: "Ese código ya está registrado." |
| `api/get_colaboradores.php` | Quitar `dni` del SELECT. Mantener `codigo` en el resultado. |
| `api/import_colaboradores.php` | Reemplazar validación `^\d{8}$` por validación alfanumérica del código. Eliminar padding de DNI. `ON DUPLICATE KEY UPDATE` opera sobre `uq_codigo` (no `uq_dni`). Eliminar bloque de auto-generación de `ST-XXX` (líneas que hacen UPDATE de `codigo` tras INSERT). INSERT statement pasa a incluir `codigo` desde la fila. Mensaje de error de colisión: "Ese código ya está registrado." |
| `api/delete_colaborador.php` | Sin cambios. |

Forma de la respuesta del importador se mantiene: `{success, inserted, updated, total}`.

### 3.3 Frontend (`pages/colaboradores.php`)

**Modal de alta/edición:**

- Eliminar input `cm-dni` y su validación cliente.
- Añadir input `cm-codigo` con `pattern="[A-Za-z0-9]+"`, `maxlength="20"`, `placeholder="Ej: A001"` **en la misma posición que ocupaba DNI** (entre Nombre y Cuadrilla). Mantiene el orden actual del formulario: Nombre → Código → Cuadrilla → Función.
- Validación cliente: no vacío + regex alfanumérico. Mensaje de error: "El código solo puede contener letras y números (sin espacios)."

**Tabla principal:**

- Eliminar columna DNI del `<thead>` y de las celdas renderizadas.
- Columna `Código` ya existe — sin cambios estructurales.
- Búsqueda libre (`colSearch`): quitar `c.dni` del array de campos buscables; mantener `c.codigo`, `c.nombre`, `c.funcion_principal`, `c.cuadrilla`.

**Modal de importación Excel:**

- Mapa de sinónimos: reemplazar la entrada `dni: [...]` por:
  ```js
  codigo: ['Código','Codigo','CODIGO','Cod.','COD.','Código Trabajador','ID']
  ```
- Validación cliente por fila: reemplazar `^\d{8}$` por validación alfanumérica del código.
- Eliminar bloque de padding de DNI (`out.dniRaw`, etc.).
- Detección de duplicados internos: `seenInFile` ahora compara por `codigo`.
- Verificación de columnas obligatorias: `if (!fileMap.codigo || !fileMap.nombre)`.
- Preview table: columna DNI → Código.
- Subtítulo del modal: *"Selecciona un archivo .xlsx con las columnas: Código, Nombre, Función, Cuadrilla."*

**Generador de plantilla (`impDownloadTemplate`):**

```js
XLSX.utils.aoa_to_sheet([
  ['Código','Nombre','Función','Cuadrilla'],
  ['A001','Ejemplo Colaborador Uno','Estibador','A'],
  ['A002','Ejemplo Colaborador Dos','Winchero','B'],
]);
```

### 3.4 Fuera de alcance

- `js/data-source.js` — no contiene DNI. Los IDs `ST-001..ST-012` hardcoded en `personal` son fake data para la vista de turno; serán reemplazados cuando esa vista se conecte al backend real. No se toca en este cambio.
- `sql/002_colaboradores.sql` — queda como histórico, sin modificación.
- Tabla `usuarios` — sin cambios.

---

## 4. Flujo de datos resultante

### Alta manual

1. Usuario abre modal "Nuevo colaborador".
2. Escribe Código (alfanumérico), Cuadrilla, Función, Nombre.
3. Cliente valida regex + campos no vacíos.
4. POST a `api/save_colaborador.php`.
5. Backend re-valida. INSERT con `codigo` del usuario.
6. Si `codigo` colisiona → mensaje "Ese código ya está registrado." Sin rollback (solo es una fila).

### Importación Excel

1. Usuario descarga plantilla (4 columnas: Código, Nombre, Función, Cuadrilla).
2. Llena y sube el `.xlsx`.
3. Cliente parsea con SheetJS, mapea columnas por sinónimos, valida fila por fila, detecta duplicados internos.
4. Preview muestra cada fila marcada como `nuevo` (verde), `update` (azul) o `error` (rojo).
5. Usuario confirma.
6. POST a `api/import_colaboradores.php` con array `rows`.
7. Backend re-valida, ejecuta INSERT ... ON DUPLICATE KEY UPDATE en transacción.
8. Respuesta: `{inserted, updated, total}`. UI muestra toast.

---

## 5. Validaciones (resumen consolidado)

| Campo | Regla | Donde se valida |
|---|---|---|
| `codigo` | Obligatorio, alfanumérico `^[A-Za-z0-9]+$`, ≤20, único | Cliente (form, import) + Backend (save, import) + BD (UNIQUE) |
| `nombre` | Obligatorio, ≥3 caracteres, ≤150 | Cliente + Backend |
| `funcion_principal` | Obligatorio, ≤60 | Cliente + Backend |
| `cuadrilla` | Obligatorio, ≤20 | Cliente + Backend |
| `activo` | Default 1 en import, toggle desde UI individual | Backend |

---

## 6. Riesgos y mitigación

| Riesgo | Mitigación |
|---|---|
| Usuario escribe código con espacio o símbolos | Validación regex en cliente con mensaje claro; backend re-valida como defensa. |
| Excel con código duplicado interno | Detección cliente antes de enviar; fila marcada como error en preview. |
| Excel antiguo con columna "DNI" pero sin "Código" | El parser ignora columnas no mapeadas; falta de columna `Código` produce error claro: *"No se encontró la columna Código en el archivo."* |
| Re-import sobreescribe datos buenos por accidente | Preview muestra filas marcadas como `update` antes de confirmar; usuario puede cancelar. |
| Sistema queda con cualquier valor que el usuario ponga como código | Aceptado: el usuario es responsable de su nomenclatura. La unicidad y el alfanumérico son el único contrato. |

---

## 7. Criterios de aceptación

- [ ] La columna `dni` no existe en la tabla `colaboradores`.
- [ ] El índice `uq_dni` no existe.
- [ ] `codigo` es NOT NULL y único.
- [ ] El modal de alta no muestra campo DNI y sí muestra campo Código con validación alfanumérica.
- [ ] La tabla principal no muestra columna DNI.
- [ ] La búsqueda libre no busca por DNI.
- [ ] El modal de import acepta archivos con columna `Código` (con variantes de sinónimos).
- [ ] La plantilla descargable (`plantilla_colaboradores.xlsx`) tiene cabeceras `Código, Nombre, Función, Cuadrilla`.
- [ ] Re-importar un Excel con códigos ya existentes hace UPDATE, no duplica.
- [ ] Intentar guardar un código duplicado muestra mensaje claro al usuario.
- [ ] Una importación con código alfanumérico inválido (con espacio o símbolo) es rechazada con mensaje claro.
