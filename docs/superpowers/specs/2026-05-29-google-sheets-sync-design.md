# Diseño · Sincronización con Google Sheets (Estiba_Turno)

**Fecha:** 2026-05-29
**Estado:** Aprobado para planificar
**Autor:** Claude + usuario

---

## 1. Objetivo

Enviar (espejar) la información del sistema a una **Google Sheet** en tiempo real, de modo
que la hoja sea el "reporte vivo" compartible. **MySQL sigue siendo la fuente de verdad**;
Google Sheets es un destino/espejo. La exportación a Excel se **oculta** (no se elimina el
código) y se reemplaza por un enlace a la hoja.

### Decisiones tomadas (Q&A con el usuario)

| Tema | Decisión |
|---|---|
| Modelo de almacenamiento | MySQL sigue como base; se **empuja** a Sheets (espejo). |
| Conexión | **Google Apps Script (Web App)** — sin composer, sin Google Cloud. |
| Datos a enviar | Incidencias, Reporte del turno (cierre/indicadores/conteo/bitácora), Cambios de puesto/estado + Auditoría, Colaboradores. |
| Disparador | **Automático al guardar** (tiempo real). |
| Conteo por persona | Pestaña **fija** `Conteo_Personal` (al cerrar turno). |
| Excel | **Ocultar** botones/modal (código intacto) + enlace "Abrir Google Sheet". |
| Alcance extra | Sincronización inicial (backfill) para sembrar la hoja con lo existente. |

---

## 2. Arquitectura

```
[Navegador] → guarda → [PHP + MySQL]  ──cURL POST {token,sheet,mode,rows}──►  [Apps Script /exec] → [Google Sheet]
                         (fuente de verdad)                                     (valida token, escribe)
```

- El envío es **siempre server-side** (PHP cURL). La URL del Web App y el token **nunca**
  llegan al navegador.
- El push ocurre **después** de que la escritura en MySQL fue exitosa.
- Si Sheets falla / no hay internet / cURL no está: **el guardado en MySQL no se ve afectado**.
  El error se registra en `logs/sheets.log` y la operación del usuario sigue normal.
- La integración es **opt-in**: si `includes/sheets_config.php` no existe o la URL está vacía,
  `sheets_push()` es un no-op silencioso (el sistema funciona igual que hoy).

---

## 3. Componentes nuevos

### 3.1 `includes/sheets.php`
Helper único. Expone:

```php
// $sheet: nombre de pestaña destino. $rows: array de arrays (filas).
// $mode: 'append' | 'replace' | 'upsert'. $opts: ['keyCols'=>[...], 'header'=>[...]]
function sheets_enabled(): bool                 // hay config válida + cURL disponible
function sheets_push(string $sheet, array $rows, string $mode='append', array $opts=[]): bool
function sheets_log(string $msg): void          // append a logs/sheets.log
```

- Lee `SHEETS_WEBAPP_URL` y `SHEETS_TOKEN` desde `includes/sheets_config.php`.
- Arma `{ token, sheet, mode, header, keyCols, rows }` y hace `POST` con `Content-Type: application/json`,
  timeout corto (connect 3s / total 5s), `CURLOPT_RETURNTRANSFER`.
- Devuelve `true` si la respuesta JSON trae `{ok:true}`; si no, hace `sheets_log()` y devuelve `false`.
- **Nunca lanza excepción** hacia el llamador (try/catch interno); el guardado no debe romperse.

### 3.2 `includes/sheets_config.php` (lo crea el usuario; no se versiona)
```php
<?php
define('SHEETS_WEBAPP_URL', 'https://script.google.com/macros/s/XXXXX/exec');
define('SHEETS_TOKEN',      'un-secreto-largo-que-tu-inventes');
```
Se entrega `includes/sheets_config.example.php` como plantilla y se agrega `sheets_config.php`
a `.gitignore` (si más adelante se versiona el proyecto).

### 3.3 Apps Script `doPost(e)` (se pega en la hoja; ver Apéndice A)
- Valida `token` contra una constante del script.
- Resuelve/crea la pestaña `sheet`.
- Si la pestaña está vacía y viene `header`, escribe encabezado (negrita, fondo navy, fila congelada).
- Aplica `mode`:
  - `append`: agrega filas al final (una sola escritura `setValues`).
  - `replace`: borra el contenido de datos (deja encabezado) y escribe `rows`.
  - `upsert`: por `keyCols`, actualiza la fila existente que coincide o agrega una nueva.
- Devuelve `{ok:true, wrote:n}` o `{ok:false,error:'...'}`.

### 3.4 `logs/sheets.log`
Línea por error: `[fecha] sheet=<x> mode=<y> http=<code> err=<msg>`. Carpeta con guard
(`index.php` 403, como en `uploads/`).

---

## 4. Mapeo de datos → pestañas

| Pestaña | Disparador (archivo) | Modo | Columnas |
|---|---|---|---|
| `Incidencias` | `api/save_incidencia.php` (tras INSERT/UPDATE ok) | append | id, registrado_en, colaborador, cargo, punto_mejorar, competencia, impacto, coordinador, turno, fecha, zona_trabajo, detalle, foto_path, declaracion_path |
| `Bitacora` | `api/add_evento.php` (tras INSERT ok) | append | registrado_en, fecha_turno, jornada, codigo, colaborador, funcion, ubicacion, tipo, hora_inicio, hora_fin, duracion_min, motivo, observaciones, usuario |
| `Cambios_Estado` | `includes/acciones.php` → `registrar_accion()` cuando `tipo ∈ {cambio, estado}` | append | registrado_en, fecha_turno, jornada, hora, usuario, rol, accion, codigo, colaborador, detalle |
| `Auditoria` | `registrar_accion()` cuando `tipo ∉ {cambio, estado, evento}` | append | (mismas columnas que Cambios_Estado) |
| `Colaboradores` | `save/import/delete_colaborador.php` (tras ok) | replace | codigo, nombre, funcion_principal, cuadrilla, activo |
| `Resumen_Turnos` | `api/cerrar_turno.php` (al cerrar) | upsert (key: fecha+jornada) | fecha, jornada, horario, total_personal, activos, refrigerio, incidencias, ev_traslado, ev_refrigerio, ev_permiso, cerrado_por, cerrado_en |
| `Conteo_Personal` | `api/cerrar_turno.php` (al cerrar) | upsert (key: fecha+jornada+codigo) | fecha, jornada, codigo, colaborador, funcion, ubicacion, estado, traslados, refrigerios, permisos, min_refrigerio, min_permiso |

**Punto único para cambios/auditoría:** todo pasa por `registrar_accion()`. Ahí se decide la
pestaña según `tipo`. El `tipo = 'evento'` se **omite** en ese hook porque `add_evento.php` ya
envía la fila rica a `Bitacora` (no se duplica).

**Cálculo de duraciones** (Bitacora, Conteo_Personal): minutos con lógica de cruce de medianoche
(si fin < inicio, +1440), reutilizando el patrón de `api/get_reporte_rango.php` (`_dur`).

**Agregados al cerrar** (`Resumen_Turnos`, `Conteo_Personal`): se calculan en
`cerrar_turno.php` con consultas SQL sobre `turno_personal` + `turno_eventos` del turno cerrado.

---

## 5. Sincronización inicial (backfill)

Endpoint admin `api/sheets_sync_all.php` (solo Administrador) + botón **"Sincronizar todo a Sheets"**
(en una sección de administración, p. ej. encabezado de Colaboradores o un panel de Configuración).
Envía en lotes:
1. `Colaboradores` (replace, catálogo completo).
2. `Incidencias` (append, todas las existentes).
3. `Bitacora` (append, todos los eventos históricos).
4. `Cambios_Estado` y `Auditoria` (append, desde `turno_acciones`).
5. `Resumen_Turnos` y `Conteo_Personal` (upsert, por cada turno).

Cada pestaña se manda en **un solo POST** con todas sus filas (escritura por lotes en Apps Script).
Devuelve un resumen `{pestaña: filas_enviadas}` para mostrar al usuario.

---

## 6. Qué pasa con el Excel (ocultar, no borrar)

- En `index.php`: ocultar los botones `#estExportXLSBtn` y `#estReporteRangoBtn` y el modal de rango
  (CSS `display:none` o quitar del flujo, dejando el HTML), y **conservar** el `<script>` de ExcelJS
  y las funciones de `estiba.js` intactas (código durmiente, re-activable).
- Agregar botón/enlace **"Abrir Google Sheet"** (`#estOpenSheetBtn`) que abre `SHEETS_SHEET_URL`
  (una tercera constante opcional en la config con el enlace de la hoja) en pestaña nueva. Si no hay
  config, el botón se oculta.

---

## 7. Seguridad

- Token compartido validado por Apps Script; sin token válido → 401 lógico (`{ok:false}`).
- URL/token solo en el servidor (`sheets_config.php`), nunca en el cliente.
- Web App desplegado como "ejecutar como: yo / acceso: cualquiera con el enlace" (necesario para
  recibir POST sin login de Google); el token es la barrera real.
- El endpoint de backfill exige rol Administrador (`api_require_admin`).

---

## 8. Manejo de errores / robustez

- `sheets_push()` jamás interrumpe el guardado: try/catch interno, timeout corto, log de fallos.
- Las respuestas de las APIs pueden incluir `"sheets": "ok"|"skip"|"error"` (informativo); el
  éxito de la operación depende **solo** de MySQL.
- Si `php_curl` no está disponible → no-op + log una vez.

---

## 9. Pasos manuales del usuario (una sola vez)

1. Crear una Google Sheet.
2. Extensiones → Apps Script → pegar el script del Apéndice A → fijar `TOKEN` igual al de la config.
3. Implementar → Nueva implementación → tipo **App web** → ejecutar como tú, acceso "cualquiera con el enlace".
4. Copiar la URL `.../exec`.
5. Copiar `includes/sheets_config.example.php` a `includes/sheets_config.php` y pegar URL + token (+ enlace de la hoja).
6. (Opcional) Pulsar "Sincronizar todo a Sheets" para sembrar los datos existentes.

---

## 10. Plan de verificación

- `sheets_enabled()` falso sin config → todas las APIs siguen funcionando igual (smoke test sin config).
- Con config de prueba apuntando a un Web App de prueba:
  - Guardar una incidencia → aparece fila en `Incidencias`.
  - Registrar evento → fila en `Bitacora`.
  - Cambiar puesto/estado → fila en `Cambios_Estado`; alta/baja → `Auditoria`.
  - Editar colaborador → `Colaboradores` se reescribe (replace).
  - Cerrar turno → `Resumen_Turnos` (1 fila upsert) + `Conteo_Personal` (filas del turno).
  - Backfill → todas las pestañas sembradas.
- Apagar internet / URL inválida → guardado sigue OK, error en `logs/sheets.log`.
- `php -l` en todos los PHP nuevos/modificados; `node --check js/estiba.js`.

---

## Apéndice A · Apps Script (doPost)

> Código que el usuario pega en Apps Script. Soporta append / replace / upsert, crea pestañas,
> formatea encabezados y congela la primera fila.

```javascript
const TOKEN = 'un-secreto-largo-que-tu-inventes'; // DEBE coincidir con SHEETS_TOKEN

function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents || '{}');
    if (body.token !== TOKEN) return _json({ ok: false, error: 'token' });

    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const name = String(body.sheet || '').slice(0, 90);
    if (!name) return _json({ ok: false, error: 'sheet' });

    let sh = ss.getSheetByName(name);
    if (!sh) sh = ss.insertSheet(name);

    const header = body.header || [];
    const rows = body.rows || [];
    const mode = body.mode || 'append';

    // Encabezado si la hoja está vacía
    if (header.length && sh.getLastRow() === 0) {
      sh.getRange(1, 1, 1, header.length).setValues([header])
        .setFontWeight('bold').setFontColor('#ffffff').setBackground('#002b5c');
      sh.setFrozenRows(1);
    }

    if (mode === 'replace') {
      const startRow = header.length ? 2 : 1;
      const last = sh.getLastRow();
      if (last >= startRow) sh.deleteRows(startRow, last - startRow + 1);
      if (rows.length) sh.getRange(startRow, 1, rows.length, rows[0].length).setValues(rows);
      return _json({ ok: true, wrote: rows.length });
    }

    if (mode === 'upsert') {
      const keyCols = body.keyCols || [1]; // 1-based
      const wrote = _upsert(sh, header.length, rows, keyCols);
      return _json({ ok: true, wrote });
    }

    // append (por defecto)
    if (rows.length) {
      sh.getRange(sh.getLastRow() + 1, 1, rows.length, rows[0].length).setValues(rows);
    }
    return _json({ ok: true, wrote: rows.length });
  } catch (err) {
    return _json({ ok: false, error: String(err) });
  }
}

function _upsert(sh, hasHeader, rows, keyCols) {
  const startRow = hasHeader ? 2 : 1;
  const last = sh.getLastRow();
  const existing = last >= startRow ? sh.getRange(startRow, 1, last - startRow + 1, sh.getLastColumn()).getValues() : [];
  const keyOf = (row) => keyCols.map(c => String(row[c - 1])).join('||');
  const index = {};
  existing.forEach((row, i) => { index[keyOf(row)] = startRow + i; });
  let wrote = 0;
  rows.forEach(row => {
    const k = keyOf(row);
    if (index[k]) {
      sh.getRange(index[k], 1, 1, row.length).setValues([row]);
    } else {
      const r = sh.getLastRow() + 1;
      sh.getRange(r, 1, 1, row.length).setValues([row]);
      index[k] = r;
    }
    wrote++;
  });
  return wrote;
}

function _json(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
```

---

## Apéndice B · Archivos afectados

**Nuevos:**
- `includes/sheets.php`
- `includes/sheets_config.example.php`
- `api/sheets_sync_all.php`
- `logs/.htaccess` + `logs/index.php` (guard)
- (el usuario crea) `includes/sheets_config.php`

**Modificados (agregan 1 llamada a `sheets_push` tras el éxito de la BD):**
- `api/save_incidencia.php`
- `api/add_evento.php`
- `includes/acciones.php` (`registrar_accion`)
- `api/save_colaborador.php`, `api/import_colaboradores.php`, `api/delete_colaborador.php`
- `api/cerrar_turno.php`
- `index.php` (ocultar Excel + botón "Abrir Google Sheet")
