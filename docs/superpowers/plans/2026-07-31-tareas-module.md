# Módulo de Tareas · Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir el módulo de Tareas —el administrador encarga trabajo con plazo a coordinadores y al nuevo puesto Tally Soporte, el asignado entrega con evidencia, el administrador revisa y califica— junto con el rol de usuario `Soporte` que hoy no existe.

**Architecture:** PHP procedural con `mysqli`, siguiendo el patrón ya establecido por el módulo de Capacitaciones: una migración SQL idempotente, un catálogo (`includes/tareas_catalogo.php`) que es fuente única de verdad de estados y reglas de permiso, endpoints JSON de una responsabilidad cada uno bajo `api/`, y una página autocontenida (`pages/tareas.php`) con su CSS y su JS embebidos. El atraso NO se persiste: se deriva del plazo vigente en cada lectura.

**Tech Stack:** PHP 8 (XAMPP), MySQL/MariaDB, JavaScript vanilla, Google Drive vía Apps Script (`includes/drive_uploader.php`), jsPDF para exportación.

**Spec:** [2026-07-30-tareas-design.md](../specs/2026-07-30-tareas-design.md)

---

## Contexto del entorno · léelo antes de empezar

Cosas que este proyecto **no** tiene y que cambian cómo se verifica cada tarea:

- **No hay framework de tests.** No hay `composer.json`, ni PHPUnit, ni carpeta `tests/`. Este plan introduce **un único** archivo de test: `tests/tareas_catalogo_test.php`, un script PHP con aserciones a mano, sin dependencias, para las funciones puras del catálogo (plazo vigente, atraso, semáforo, permisos). Es donde la lógica es sutil y donde un error no se ve a simple vista. El resto se verifica con `php -l`, consultas SQL y llamadas HTTP reales.
- **No es un repositorio git.** `git init` no está hecho. La Tarea 0 lo inicializa; si decides no hacerlo, salta todos los pasos «Commit».
- **Rutas de las herramientas** (verificadas en esta máquina):
  - PHP: `c:\xampp2026\php\php.exe`, también disponible como `php` en el PATH
  - MySQL: `c:\xampp2026\mysql\bin\mysql.exe`
  - Base de datos: `portally_system`, usuario `portally_sa`, password `Sistemas2100*` (ver [db.php](../../../includes/db.php))
- **URL base: `https://localhost/portallyman.online/` — por HTTPS y con `curl -k`.** No es un capricho: en esta máquina **nginx ocupa el puerto 80** y devuelve 404 para este proyecto, y el único vhost de Apache en el 8081 apunta a `tareo-laravel`. El 443 sí es exclusivo de Apache y sirve el `DocumentRoot` correcto, con certificado autofirmado — de ahí el `-k`. Todo `curl` de este plan lleva `-k` y esa URL.
- **Zona horaria:** todo el sistema opera en `America/Lima`. PHP la fija en `db.php` y `auth.php`; MySQL con `SET time_zone='-05:00'`. Nunca calcules fechas en el navegador.

Atajo para las consultas de verificación de todo el plan:

```bash
alias mysqlp='c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system'
```

En PowerShell:

```powershell
function mysqlp { & c:\xampp2026\mysql\bin\mysql.exe -uportally_sa "-pSistemas2100*" portally_system @args }
```

---

## Estructura de archivos

**Se crean:**

| Archivo | Responsabilidad |
|---|---|
| `sql/029_tareas.sql` | Rol `Soporte`, `usuarios.soporte_de_id` y las tres tablas del módulo |
| `includes/tareas_catalogo.php` | Estados, prioridades, cálculo de plazo/atraso/semáforo y **todas** las reglas de permiso. Sin acceso a BD salvo el helper de bitácora |
| `tests/tareas_catalogo_test.php` | Aserciones sobre las funciones puras del catálogo |
| `api/get_asignables.php` | Coordinadores y Soportes activos para el selector |
| `api/get_tareas.php` | Listado filtrado por visibilidad, con adjuntos anidados |
| `api/get_tarea.php` | Detalle + historial de una tarea |
| `api/save_tarea.php` | Alta multi-destinatario y edición |
| `api/enviar_tarea.php` | Transición a `entregada` |
| `api/revisar_tarea.php` | Veredicto del administrador |
| `api/prorrogar_tarea.php` | 2ª fecha |
| `api/upload_tarea_file.php` | Un archivo a Drive |
| `api/delete_tarea_adjunto.php` | Baja de un adjunto |
| `api/delete_tarea.php` | Borrado de la tarea |
| `pages/tareas.php` | La interfaz completa, bifurcada por rol |

**Se modifican:**

| Archivo | Cambio |
|---|---|
| `includes/auth.php` | `is_soporte()`, `can_tareas()`, `require_tareas()`, `api_require_tareas()` |
| `includes/sidebar.php` | Ítem «Tareas» fuera del bloque de roles operativos |
| `pages/usuarios.php` | Opción «Tally Soporte», campo «Coordinador a cargo», chip `is-sop` |
| `api/save_usuario.php` | Lista blanca de roles + persistir `soporte_de_id` |
| `api/get_usuarios.php` | Devolver `soporte_de_id` y el nombre del coordinador |

**Por qué el catálogo no toca la base de datos:** `tk_puede_ver()` necesita saber de quién es soporte el asignado. Si lo consultara, no se podría testear sin BD y añadiría una consulta por fila en el listado. En su lugar, las consultas de `get_tareas.php` y `get_tarea.php` traen `u.soporte_de_id AS asignado_soporte_de` con un `LEFT JOIN`, y el catálogo lo lee de la fila. La única función que sí usa `$conn` es `tk_historial()`, que escribe en la bitácora.

---

# FASE 1 · Base de datos, catálogo y rol

## Task 0: Preparar el entorno

**Files:**
- Create: `.gitignore`

- [ ] **Step 1: Inicializar git**

Este proyecto no está versionado. Sin git no hay forma de deshacer una tarea que salga mal.

```bash
cd /c/xampp2026/htdocs/portallyman.online
git init
```

- [ ] **Step 2: Crear `.gitignore`**

Hay credenciales y basura de runtime que no deben entrar al repositorio.

```gitignore
# Logs y salidas de runtime
error_log
logs/
_srv.out
_srv.out.err
_boot.txt
api/error_log

# Subidas de usuarios
uploads/

# Configuración local con secretos
includes/sheets_config.php
php.ini
.user.ini

# Sistema
Thumbs.db
desktop.ini
.DS_Store
```

- [ ] **Step 3: Primer commit**

```bash
git add -A
git commit -m "chore: estado inicial del portal antes del modulo de Tareas"
```

- [ ] **Step 4: Verificar que MySQL responde**

Run:
```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "SELECT COUNT(*) AS usuarios FROM usuarios;"
```
Expected: una tabla con el número de usuarios. Si falla, arranca MySQL desde el panel de XAMPP antes de seguir.

- [ ] **Step 5: Verificar la versión de PHP**

Run: `php -v`
Expected: `PHP 8.x`. El plan usa el operador spread y `str_contains`, que necesitan PHP 8.

---

## Task 1: Migración SQL

**Files:**
- Create: `sql/029_tareas.sql`

- [ ] **Step 1: Escribir la migración**

Sin `USE` y con cada `ALTER` protegido por `information_schema`, igual que `sql/024`, `026` y `028`, para que corra igual en local y en el servidor y se pueda reejecutar.

```sql
-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 029 · Módulo de Tareas + rol Tally Soporte
-- ────────────────────────────────────────────────────────────────────
-- El administrador encarga trabajo con plazo; el asignado (Coordinador
-- o Tally Soporte) entrega con evidencia; el administrador revisa,
-- comenta y califica 1-5. Si observa, la tarea vuelve al asignado.
--
--   pendiente ──▶ entregada ──▶ aprobada | rechazada   (terminales)
--       ▲                   └─▶ observada ──┘ (reenvía)
--
-- «ATRASADA» NO es un estado: se DERIVA de COALESCE(fecha_limite_2,
-- fecha_limite) contra NOW() en cada lectura. Un estado guardado
-- necesitaría un proceso programado que este sistema no tiene, y una
-- prórroga concedida no lo limpiaría solo.
--
-- Una fila de `tareas` = un responsable = un expediente completo. El
-- mismo encargo a cinco personas son cinco filas con el mismo lote_id.
--
-- Se ejecuta sobre la base YA seleccionada (en phpMyAdmin: elige la base
-- antes de importar). Idempotente: se puede correr dos veces seguidas.
-- ════════════════════════════════════════════════════════════════════

-- ─── 1 · Rol Tally Soporte ──────────────────────────────────────────
-- Un MODIFY con la definición completa es idempotente por naturaleza:
-- ejecutarlo dos veces deja exactamente el mismo ENUM.
ALTER TABLE usuarios
  MODIFY rol ENUM('Administrador','Supervisor','Coordinador','Soporte','Operador')
  NOT NULL DEFAULT 'Coordinador';

-- ─── 2 · usuarios.soporte_de_id ─────────────────────────────────────
-- Solo tiene sentido cuando rol='Soporte'. Sin UNIQUE a propósito: un
-- coordinador puede llegar a tener dos soportes, y bloquearlo hoy sería
-- inventar una regla de negocio que nadie pidió.
SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'usuarios'
     AND COLUMN_NAME  = 'soporte_de_id'
);
SET @ddl := IF(@col = 0,
  'ALTER TABLE usuarios ADD COLUMN soporte_de_id INT(11) NULL AFTER rol',
  'SELECT "soporte_de_id ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ix := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'usuarios'
     AND INDEX_NAME   = 'ix_usr_soporte_de'
);
SET @ddl := IF(@ix = 0,
  'ALTER TABLE usuarios ADD KEY ix_usr_soporte_de (soporte_de_id)',
  'SELECT "ix_usr_soporte_de ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ON DELETE SET NULL: borrar al coordinador deja al soporte sin jefe,
-- no bloquea el borrado. Mismo criterio que fk_col_coordinador (sql/024).
SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA    = DATABASE()
     AND TABLE_NAME      = 'usuarios'
     AND CONSTRAINT_NAME = 'fk_usr_soporte_de'
);
SET @ddl := IF(@fk = 0,
  'ALTER TABLE usuarios
     ADD CONSTRAINT fk_usr_soporte_de FOREIGN KEY (soporte_de_id)
         REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT "fk_usr_soporte_de ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── 3 · La tarea ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tareas (
  id                 INT(11)      NOT NULL AUTO_INCREMENT,

  -- Agrupa las creadas en una misma tanda. Se resuelve sin secuencia
  -- extra: se inserta la primera fila y su id se usa como lote de todas,
  -- incluida ella misma, así la columna nunca es un caso especial.
  lote_id            INT(11)      NULL,

  titulo             VARCHAR(180) NOT NULL,
  descripcion        TEXT         NULL,
  prioridad          ENUM('baja','media','alta') NOT NULL DEFAULT 'media',

  -- ── Destinatario ──
  -- asignado_id es NULL-able con SET NULL, y el nombre y el rol van
  -- congelados: borrar un usuario no debe borrar ni bloquear el
  -- historial de lo que se le encargó.
  asignado_id        INT(11)      NULL,
  asignado_nombre    VARCHAR(100) NOT NULL,
  asignado_rol       ENUM('Coordinador','Soporte') NOT NULL,
  -- El jefe AL CREAR, no el jefe actual. La visibilidad se resuelve
  -- contra usuarios.soporte_de_id (la relación viva); este par es para
  -- que un reporte viejo siga diciendo bajo quién se encargó.
  -- Van id Y nombre, igual que asignado_id/asignado_nombre y por el mismo
  -- motivo: api/delete_usuario.php borra usuarios de verdad, y un id
  -- suelto que ya no resuelve a nada deja el reporte histórico tan vacío
  -- como si la columna no existiera.
  coordinador_ref_id     INT(11)      NULL,
  coordinador_ref_nombre VARCHAR(100) NULL,

  -- ── Plazos ──
  fecha_limite       DATETIME     NOT NULL,        -- «fecha 1»
  fecha_limite_2     DATETIME     NULL,            -- «fecha 2»: prórroga
  prorroga_motivo    VARCHAR(255) NULL,
  prorroga_por       VARCHAR(100) NULL,
  prorroga_por_id    INT(11)      NULL,
  prorroga_at        TIMESTAMP    NULL,

  -- ── Entrega ──
  estado             ENUM('pendiente','entregada','observada','aprobada','rechazada')
                     NOT NULL DEFAULT 'pendiente',
  entrega_comentario TEXT         NULL,
  enviado_at         TIMESTAMP    NULL,            -- «fecha de envío» vigente
  -- Sellado al entregar. Sin él, una prórroga concedida DESPUÉS de una
  -- entrega tardía convertiría esa entrega en puntual retroactivamente:
  -- el dato que mide el incumplimiento desaparecería justo en el caso
  -- que hay que medir. Mismo razonamiento que total_plantilla (sql/028).
  plazo_al_enviar    DATETIME     NULL,
  entregas_count     INT(11)      NOT NULL DEFAULT 0,

  -- ── Revisión ──
  nota               TINYINT      NULL,            -- 1..5, escala ed_escala()
  comentario_admin   TEXT         NULL,
  revisado_por       VARCHAR(100) NULL,
  revisado_por_id    INT(11)      NULL,
  revisado_at        TIMESTAMP    NULL,

  creado_por         VARCHAR(100) NOT NULL,
  creado_por_id      INT(11)      NULL,
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY ix_tar_asignado (asignado_id),
  KEY ix_tar_estado   (estado),
  KEY ix_tar_fecha    (fecha_limite),
  KEY ix_tar_lote     (lote_id),
  CONSTRAINT fk_tar_asignado FOREIGN KEY (asignado_id)
     REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4 · Adjuntos (Drive, con respaldo local) ───────────────────────
-- origen distingue el material de referencia que cuelga el admin al
-- crear de la evidencia que sube el asignado. entrega_nro dice a qué
-- ronda de envío pertenece: sin él, tras una observación el admin vería
-- un montón de archivos sin saber cuáles responden a lo que observó.
CREATE TABLE IF NOT EXISTS tareas_adjuntos (
  id             INT(11)      NOT NULL AUTO_INCREMENT,
  tarea_id       INT(11)      NOT NULL,
  nombre_archivo VARCHAR(180) NOT NULL,
  mime           VARCHAR(120) NOT NULL,
  peso_bytes     INT UNSIGNED NOT NULL,
  drive_file_id  VARCHAR(120) NULL,
  drive_url      VARCHAR(512) NULL,
  ruta_local     VARCHAR(255) NULL,
  estado         ENUM('subido','pendiente','error') NOT NULL DEFAULT 'pendiente',
  error_msg      VARCHAR(255) NULL,
  origen         ENUM('admin','asignado') NOT NULL DEFAULT 'asignado',
  entrega_nro    INT(11)      NOT NULL DEFAULT 1,
  subido_por     VARCHAR(100) NULL,
  subido_por_id  INT(11)      NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_tard_tarea (tarea_id),
  CONSTRAINT fk_tard_tarea FOREIGN KEY (tarea_id)
     REFERENCES tareas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5 · Bitácora ───────────────────────────────────────────────────
-- Mismo espíritu que turno_acciones (sql/007). Aquí vive lo que la fila
-- de `tareas` sobrescribe: si el admin observa dos veces, el comentario
-- anterior se conserva en `detalle`.
CREATE TABLE IF NOT EXISTS tareas_historial (
  id             INT(11) NOT NULL AUTO_INCREMENT,
  tarea_id       INT(11) NOT NULL,
  accion         ENUM('creada','editada','enviada','observada','aprobada',
                      'rechazada','prorroga','prorroga_retirada',
                      'adjunto','adjunto_borrado') NOT NULL,
  usuario_id     INT(11)      NULL,
  usuario_nombre VARCHAR(100) NULL,
  usuario_rol    VARCHAR(20)  NULL,
  detalle        TEXT         NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_tarh_tarea (tarea_id),
  CONSTRAINT fk_tarh_tarea FOREIGN KEY (tarea_id)
     REFERENCES tareas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 6 · Puesta al día de tablas ya creadas ─────────────────────────
-- CREATE TABLE IF NOT EXISTS no añade columnas a una tabla que ya existe.
-- Cualquier columna incorporada después del primer despliegue necesita su
-- propio ALTER guardado, o las instalaciones viejas se quedan atrás.
SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'tareas'
     AND COLUMN_NAME  = 'coordinador_ref_nombre'
);
SET @ddl := IF(@col = 0,
  'ALTER TABLE tareas ADD COLUMN coordinador_ref_nombre VARCHAR(100) NULL AFTER coordinador_ref_id',
  'SELECT "coordinador_ref_nombre ya existe" AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── Verificación ───────────────────────────────────────────────────
-- SHOW COLUMNS FROM usuarios LIKE 'soporte_de_id';
-- SHOW COLUMNS FROM tareas LIKE 'coordinador_ref%';
-- SHOW TABLES LIKE 'tareas%';
```

- [ ] **Step 2: Ejecutar la migración**

Run:
```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system < sql/029_tareas.sql
```
Expected: sin errores. Puede imprimir filas `info` de los `SELECT` de los `IF` — es normal.

- [ ] **Step 3: Ejecutarla otra vez para probar la idempotencia**

Run: el mismo comando del paso anterior.
Expected: sin errores. Ahora sí deben salir los mensajes `soporte_de_id ya existe`, `ix_usr_soporte_de ya existe`, `fk_usr_soporte_de ya existe`.

- [ ] **Step 4: Verificar el esquema**

Run:
```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "SHOW COLUMNS FROM usuarios LIKE 'rol'; SHOW COLUMNS FROM usuarios LIKE 'soporte_de_id'; SHOW TABLES LIKE 'tareas%';"
```
Expected:
- `rol` = `enum('Administrador','Supervisor','Coordinador','Soporte','Operador')`
- `soporte_de_id` = `int(11)`, `Null: YES`
- Tres tablas: `tareas`, `tareas_adjuntos`, `tareas_historial`

- [ ] **Step 5: Commit**

```bash
git add sql/029_tareas.sql
git commit -m "feat(tareas): migracion 029 - rol Soporte y tablas del modulo"
```

---

## Task 2: Catálogo · funciones puras y sus tests

Esta tarea sí es TDD: las funciones de plazo y atraso son la lógica más sutil del módulo y un error ahí no se ve mirando la pantalla.

**Files:**
- Create: `tests/tareas_catalogo_test.php`
- Create: `includes/tareas_catalogo.php`

- [ ] **Step 1: Escribir el test que falla**

Crea `tests/tareas_catalogo_test.php`. Fija un «ahora» conocido (`2026-08-10 09:00:00`) e inyéctalo en las funciones, para que el resultado no dependa de cuándo se corra el test.

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   Tests de includes/tareas_catalogo.php
   ───────────────────────────────────────────────────────────────────────
   Sin framework: este proyecto no tiene uno. Se ejecuta con
       php tests/tareas_catalogo_test.php
   Solo cubre funciones PURAS (no tocan BD). El «ahora» se inyecta para
   que el resultado no dependa del día en que se corra.
   ═══════════════════════════════════════════════════════════════════════ */

date_default_timezone_set('America/Lima');
require_once(__DIR__ . '/../includes/tareas_catalogo.php');

$TOTAL = 0; $FALLOS = 0;

function ok($cond, $msg) {
    global $TOTAL, $FALLOS;
    $TOTAL++;
    if ($cond) { echo "  ok    $msg\n"; }
    else       { $FALLOS++; echo "  FALLA $msg\n"; }
}
function eq($actual, $esperado, $msg) {
    ok($actual === $esperado, $msg . "  (esperado: " . var_export($esperado, true)
        . ", obtenido: " . var_export($actual, true) . ")");
}

$AHORA = strtotime('2026-08-10 09:00:00');

/** Fila mínima de `tareas` con los campos que usa el catálogo. */
function fila($over = []) {
    return array_merge([
        'id'              => 1,
        'estado'          => 'pendiente',
        'fecha_limite'    => '2026-08-12 23:59:00',
        'fecha_limite_2'  => null,
        'enviado_at'      => null,
        'plazo_al_enviar' => null,
        'asignado_id'     => 7,
        'asignado_soporte_de' => null,
    ], $over);
}

echo "\n── tk_plazo_vigente ──────────────────────────────────────\n";
eq(tk_plazo_vigente(fila()), '2026-08-12 23:59:00',
   'sin 2a fecha usa la fecha 1');
eq(tk_plazo_vigente(fila(['fecha_limite_2' => '2026-08-20 23:59:00'])), '2026-08-20 23:59:00',
   'con 2a fecha usa la 2a');
eq(tk_plazo_vigente(fila(['fecha_limite_2' => ''])), '2026-08-12 23:59:00',
   'una 2a fecha vacia no cuenta como prorroga');

echo "\n── tk_es_abierta / tk_es_terminal ────────────────────────\n";
ok(tk_es_abierta('pendiente'),  'pendiente es abierta');
ok(tk_es_abierta('observada'),  'observada es abierta');
ok(!tk_es_abierta('entregada'), 'entregada NO es abierta (esta en revision)');
ok(!tk_es_abierta('aprobada'),  'aprobada no es abierta');
ok(tk_es_terminal('aprobada'),  'aprobada es terminal');
ok(tk_es_terminal('rechazada'), 'rechazada es terminal');
ok(!tk_es_terminal('observada'),'observada no es terminal');

echo "\n── tk_esta_atrasada ──────────────────────────────────────\n";
ok(!tk_esta_atrasada(fila(), $AHORA),
   'pendiente con plazo futuro no esta atrasada');
ok(tk_esta_atrasada(fila(['fecha_limite' => '2026-08-05 23:59:00']), $AHORA),
   'pendiente con plazo vencido esta atrasada');
ok(tk_esta_atrasada(fila(['estado' => 'observada', 'fecha_limite' => '2026-08-05 23:59:00']), $AHORA),
   'observada con plazo vencido esta atrasada');
ok(!tk_esta_atrasada(fila(['estado' => 'entregada', 'fecha_limite' => '2026-08-05 23:59:00']), $AHORA),
   'entregada NO acumula atraso: ya esta en manos del admin');
ok(!tk_esta_atrasada(fila(['estado' => 'aprobada', 'fecha_limite' => '2026-08-05 23:59:00']), $AHORA),
   'aprobada no acumula atraso');
ok(!tk_esta_atrasada(fila(['fecha_limite' => '2026-08-05 23:59:00',
                           'fecha_limite_2' => '2026-08-20 23:59:00']), $AHORA),
   'la prorroga saca del atraso en el acto, sin tocar filas');

echo "\n── tk_dias_atraso ────────────────────────────────────────\n";
eq(tk_dias_atraso(fila(), $AHORA), 0,
   'sin atraso son 0 dias');
eq(tk_dias_atraso(fila(['fecha_limite' => '2026-08-07 23:59:00']), $AHORA), 3,
   'vencia el 7, hoy es 10 => 3 dias (diferencia de calendario)');
eq(tk_dias_atraso(fila(['fecha_limite' => '2026-08-10 08:00:00']), $AHORA), 0,
   'vencio hace una hora el mismo dia => 0 dias, pero atrasada');
ok(tk_esta_atrasada(fila(['fecha_limite' => '2026-08-10 08:00:00']), $AHORA),
   '... y sigue estando atrasada aunque sean 0 dias');

echo "\n── tk_entregada_tarde ────────────────────────────────────\n";
ok(!tk_entregada_tarde(fila()),
   'sin entrega no hay entrega tardia');
ok(!tk_entregada_tarde(fila(['enviado_at' => '2026-08-12 10:00:00',
                             'plazo_al_enviar' => '2026-08-12 23:59:00'])),
   'entrego antes del plazo sellado');
ok(tk_entregada_tarde(fila(['enviado_at' => '2026-08-14 10:00:00',
                            'plazo_al_enviar' => '2026-08-12 23:59:00'])),
   'entrego despues del plazo sellado');
ok(tk_entregada_tarde(fila(['estado' => 'aprobada',
                            'enviado_at' => '2026-08-14 10:00:00',
                            'plazo_al_enviar' => '2026-08-12 23:59:00',
                            'fecha_limite_2' => '2026-08-30 23:59:00'])),
   'una prorroga concedida DESPUES no borra la marca de entrega tardia');

echo "\n── tk_semaforo ───────────────────────────────────────────\n";
eq(tk_semaforo(fila(['fecha_limite' => '2026-08-05 23:59:00']), $AHORA), 'vencida',
   'plazo pasado => vencida');
eq(tk_semaforo(fila(['fecha_limite' => '2026-08-10 23:59:00']), $AHORA), 'hoy',
   'vence hoy => hoy');
eq(tk_semaforo(fila(['fecha_limite' => '2026-08-11 20:00:00']), $AHORA), 'proxima',
   'vence dentro de 48h => proxima');
eq(tk_semaforo(fila(['fecha_limite' => '2026-08-30 23:59:00']), $AHORA), 'a_tiempo',
   'vence lejos => a_tiempo');
eq(tk_semaforo(fila(['estado' => 'aprobada', 'fecha_limite' => '2026-08-05 23:59:00']), $AHORA), 'a_tiempo',
   'en una tarea cerrada el semaforo no aplica');

echo "\n── tk_filtro_visibilidad ─────────────────────────────────\n";
$_SESSION = ['user_rol' => 'Administrador', 'user_id' => 1];
eq(tk_filtro_visibilidad(), '1=1', 'el administrador ve todo');
$_SESSION = ['user_rol' => 'Supervisor', 'user_id' => 2];
eq(tk_filtro_visibilidad(), '1=1', 'el supervisor ve todo');
$_SESSION = ['user_rol' => 'Soporte', 'user_id' => 9];
eq(tk_filtro_visibilidad(), 't.asignado_id = 9', 'el soporte solo ve las suyas');
$_SESSION = ['user_rol' => 'Coordinador', 'user_id' => 7];
eq(tk_filtro_visibilidad(),
   '(t.asignado_id = 7 OR t.asignado_id IN (SELECT id FROM usuarios WHERE soporte_de_id = 7))',
   'el coordinador ve las suyas y las de su soporte');
$_SESSION = ['user_rol' => 'Operador', 'user_id' => 4];
eq(tk_filtro_visibilidad(), '0=1',
   'un rol no contemplado no ve NADA (fallar cerrado, no abierto)');
$_SESSION = ['user_rol' => 'Coordinador', 'user_id' => 0];
eq(tk_filtro_visibilidad(), '0=1', 'sin user_id no ve nada');

echo "\n── tk_puede_ver ──────────────────────────────────────────\n";
$_SESSION = ['user_rol' => 'Administrador', 'user_id' => 1];
ok(tk_puede_ver(fila()), 'el admin ve cualquier tarea');
$_SESSION = ['user_rol' => 'Coordinador', 'user_id' => 7];
ok(tk_puede_ver(fila(['asignado_id' => 7])), 'el coordinador ve la suya');
ok(!tk_puede_ver(fila(['asignado_id' => 8])), 'el coordinador NO ve la de otro coordinador');
ok(tk_puede_ver(fila(['asignado_id' => 9, 'asignado_soporte_de' => 7])),
   'el coordinador ve la de su soporte');
$_SESSION = ['user_rol' => 'Soporte', 'user_id' => 9];
ok(tk_puede_ver(fila(['asignado_id' => 9])), 'el soporte ve la suya');
ok(!tk_puede_ver(fila(['asignado_id' => 7])), 'el soporte NO ve la de su coordinador');

echo "\n── tk_puede_entregar ─────────────────────────────────────\n";
$_SESSION = ['user_rol' => 'Coordinador', 'user_id' => 7];
ok(tk_puede_entregar(fila(['asignado_id' => 7]))['ok'],
   'el asignado puede entregar su tarea pendiente');
ok(tk_puede_entregar(fila(['asignado_id' => 7, 'estado' => 'observada']))['ok'],
   'el asignado puede reenviar una observada');
ok(!tk_puede_entregar(fila(['asignado_id' => 7, 'estado' => 'entregada']))['ok'],
   'no se entrega dos veces sin que el admin la devuelva');
ok(!tk_puede_entregar(fila(['asignado_id' => 7, 'estado' => 'aprobada']))['ok'],
   'no se entrega una tarea ya aprobada');
ok(!tk_puede_entregar(fila(['asignado_id' => 9, 'asignado_soporte_de' => 7]))['ok'],
   'el coordinador NO entrega por su soporte: la ve en solo lectura');
$_SESSION = ['user_rol' => 'Administrador', 'user_id' => 1];
ok(tk_puede_entregar(fila(['asignado_id' => 7]))['ok'],
   'el admin puede entregar en nombre del asignado');
$_SESSION = ['user_rol' => 'Supervisor', 'user_id' => 2];
ok(!tk_puede_entregar(fila(['asignado_id' => 7]))['ok'],
   'el supervisor mira pero no entrega');

echo "\n── tk_puede_revisar / editar / prorrogar ─────────────────\n";
$_SESSION = ['user_rol' => 'Administrador', 'user_id' => 1];
ok(tk_puede_revisar(fila(['estado' => 'entregada']))['ok'], 'el admin revisa una entregada');
ok(!tk_puede_revisar(fila(['estado' => 'pendiente']))['ok'], 'no se revisa lo que no se entrego');
ok(!tk_puede_revisar(fila(['estado' => 'aprobada']))['ok'],  'no se revisa dos veces');
ok(tk_puede_editar(fila())['ok'], 'el admin edita una pendiente');
ok(!tk_puede_editar(fila(['estado' => 'entregada']))['ok'],
   'no se edita el enunciado de lo que se esta juzgando');
ok(!tk_puede_editar(fila(['estado' => 'aprobada']))['ok'], 'no se edita una terminal');
ok(tk_puede_prorrogar(fila())['ok'], 'el admin prorroga una pendiente');
ok(tk_puede_prorrogar(fila(['estado' => 'observada']))['ok'], 'el admin prorroga una observada');
ok(!tk_puede_prorrogar(fila(['estado' => 'entregada']))['ok'],
   'prorrogar algo ya entregado no significa nada');
$_SESSION = ['user_rol' => 'Coordinador', 'user_id' => 7];
ok(!tk_puede_revisar(fila(['estado' => 'entregada', 'asignado_id' => 7]))['ok'],
   'un coordinador NO se califica a si mismo');
ok(!tk_puede_editar(fila(['asignado_id' => 7]))['ok'], 'un coordinador no edita el enunciado');
ok(!tk_puede_prorrogar(fila(['asignado_id' => 7]))['ok'], 'un coordinador no se da prorrogas');

echo "\n── tk_nota_label ─────────────────────────────────────────\n";
eq(tk_nota_label(1), 'Deficiente',    'la escala es la de Evaluacion de Desempeno');
eq(tk_nota_label(5), 'Sobresaliente', 'nota 5');
eq(tk_nota_label(null), '—',          'sin nota');
eq(tk_nota_label(9), '—',             'una nota fuera de escala no inventa etiqueta');

echo "\n══════════════════════════════════════════════════════════\n";
echo ($FALLOS === 0 ? "TODO OK" : "HAY FALLOS") . ": $TOTAL aserciones, $FALLOS fallidas\n\n";
exit($FALLOS === 0 ? 0 : 1);
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php tests/tareas_catalogo_test.php`
Expected: FALLA con `Failed opening required '.../includes/tareas_catalogo.php'`. El archivo aún no existe.

- [ ] **Step 3: Escribir el catálogo**

Crea `includes/tareas_catalogo.php`.

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Catálogo del módulo de Tareas
   ───────────────────────────────────────────────────────────────────────
   Fuente única de verdad de estados, prioridades, cálculo de plazos y
   reglas de permiso. Se serializa a JS desde pages/tareas.php, igual que
   hacen sg_canales() y cap_estados(), para que servidor y navegador no
   puedan discrepar.

   NO consulta la base de datos (salvo tk_historial, que escribe en la
   bitácora). tk_puede_ver() necesita saber de quién es soporte el
   asignado: esa columna la traen las consultas de get_tareas.php y
   get_tarea.php como `asignado_soporte_de` con un LEFT JOIN. Si el
   catálogo la consultara, no se podría testear sin BD y añadiría una
   consulta por fila en el listado.

   Ni la escala de nota ni los tipos de archivo se redefinen aquí: se
   reutilizan ed_escala() y sg_tipos_permitidos(). Duplicarlos sería
   garantizar que las copias diverjan.
   ═══════════════════════════════════════════════════════════════════════ */

require_once(__DIR__ . '/drive_config.php');                    // sg_tipos_permitidos(), SG_MAX_BYTES
require_once(__DIR__ . '/evaluacion_desempeno_catalogo.php');   // ed_escala()

/* ── Estados del ciclo ──────────────────────────────────────────────────
   pendiente ──(asignado: «Enviar entrega»)──▶ entregada
   entregada ──(administrador)──▶ aprobada | rechazada | observada
   observada ──(asignado: «Reenviar»)────────▶ entregada                 */
function tk_estados() {
    return [
        'pendiente' => ['label' => 'Pendiente', 'color' => '#475569', 'bg' => 'rgba(100,116,139,.12)'],
        'entregada' => ['label' => 'En revisión','color' => '#2563eb', 'bg' => 'rgba(37,99,235,.10)'],
        'observada' => ['label' => 'Observada',  'color' => '#d97706', 'bg' => 'rgba(217,119,6,.10)'],
        'aprobada'  => ['label' => 'Aprobada',   'color' => '#047857', 'bg' => 'rgba(4,120,87,.10)'],
        'rechazada' => ['label' => 'Rechazada',  'color' => '#dc2626', 'bg' => 'rgba(220,38,38,.10)'],
    ];
}

function tk_estado_label($clave) {
    $e = tk_estados();
    return isset($e[$clave]) ? $e[$clave]['label'] : $clave;
}

function tk_prioridades() {
    return [
        'baja'  => ['label' => 'Baja',  'color' => '#64748b'],
        'media' => ['label' => 'Media', 'color' => '#d97706'],
        'alta'  => ['label' => 'Alta',  'color' => '#dc2626'],
    ];
}

/* ── Semáforo del plazo ────────────────────────────────────────────────
   Es lo que convierte la tabla en un tablero en vez de un listado.      */
function tk_semaforos() {
    return [
        'vencida'  => ['label' => 'Vencida',      'color' => '#dc2626', 'bg' => 'rgba(220,38,38,.10)'],
        'hoy'      => ['label' => 'Vence hoy',    'color' => '#d97706', 'bg' => 'rgba(217,119,6,.10)'],
        'proxima'  => ['label' => 'Vence pronto', 'color' => '#b45309', 'bg' => 'rgba(180,83,9,.08)'],
        'a_tiempo' => ['label' => 'A tiempo',     'color' => '#64748b', 'bg' => 'rgba(100,116,139,.08)'],
    ];
}

/** Etiquetas legibles de la bitácora. Las claves son el ENUM de tareas_historial. */
function tk_acciones() {
    return [
        'creada'            => 'Tarea creada',
        'editada'           => 'Enunciado modificado',
        'enviada'           => 'Entrega enviada',
        'observada'         => 'Devuelta con observaciones',
        'aprobada'          => 'Aprobada',
        'rechazada'         => 'Rechazada',
        'prorroga'          => 'Prórroga concedida',
        'prorroga_retirada' => 'Prórroga retirada',
        'adjunto'           => 'Archivo adjuntado',
        'adjunto_borrado'   => 'Archivo eliminado',
    ];
}

/** El puesto tal como se muestra. En BD es 'Soporte'; en pantalla, «Tally Soporte». */
function tk_rol_label($rol) {
    return $rol === 'Soporte' ? 'Tally Soporte' : $rol;
}

/** Roles que pueden recibir una tarea. */
function tk_roles_asignables() {
    return ['Coordinador', 'Soporte'];
}

/** Etiqueta de la nota. Reutiliza la escala 1-5 de Evaluación de Desempeño. */
function tk_nota_label($n) {
    if ($n === null || $n === '') return '—';
    $e = ed_escala();
    $n = (int)$n;
    return isset($e[$n]) ? $e[$n] : '—';
}

/* ══ Plazos ═══════════════════════════════════════════════════════════ */

/**
 * El plazo que rige AHORA MISMO: la 2ª fecha si el administrador concedió
 * prórroga, si no la 1ª. Nadie más decide esto.
 */
function tk_plazo_vigente($row) {
    $f2 = $row['fecha_limite_2'] ?? null;
    if ($f2 !== null && $f2 !== '' && $f2 !== '0000-00-00 00:00:00') return $f2;
    return $row['fecha_limite'] ?? null;
}

/** Estados en los que la tarea sigue en manos del asignado. */
function tk_es_abierta($estado) {
    return $estado === 'pendiente' || $estado === 'observada';
}

/** Estados ya revisados. No hay reapertura. */
function tk_es_terminal($estado) {
    return $estado === 'aprobada' || $estado === 'rechazada';
}

/**
 * ¿Acumula atraso ahora mismo?
 *
 * NO es un estado guardado. Calculado tiene dos propiedades que el
 * guardado no: es correcto sin que corra ningún proceso programado, y en
 * cuanto se concede la prórroga la tarea deja de figurar atrasada al
 * instante, sin actualizar ninguna fila.
 *
 * `entregada` no acumula atraso: la pelota está en el tejado del admin.
 *
 * @param array    $row
 * @param int|null $ahora Timestamp; null = time(). Se inyecta en los tests.
 */
function tk_esta_atrasada($row, $ahora = null) {
    if (!tk_es_abierta($row['estado'] ?? '')) return false;
    $plazo = tk_plazo_vigente($row);
    if (!$plazo) return false;
    $ahora = ($ahora !== null) ? $ahora : time();
    return strtotime($plazo) < $ahora;
}

/**
 * Días de atraso, por diferencia de CALENDARIO, no de horas.
 * «Vencía el 12, hoy es 15» son 3 días para cualquier persona, aunque
 * hayan pasado 62 horas. Vencer hace una hora el mismo día son 0 días,
 * y aun así la tarea está atrasada: la interfaz muestra «ATRASADA» sin
 * número cuando esto devuelve 0.
 */
function tk_dias_atraso($row, $ahora = null) {
    if (!tk_esta_atrasada($row, $ahora)) return 0;
    $ahora = ($ahora !== null) ? $ahora : time();
    $d1 = new DateTime(date('Y-m-d', strtotime(tk_plazo_vigente($row))));
    $d2 = new DateTime(date('Y-m-d', $ahora));
    return (int)$d1->diff($d2)->days;
}

/**
 * ¿La entrega llegó fuera de plazo?
 *
 * Se compara contra `plazo_al_enviar`, que se selló al entregar, NO contra
 * el plazo vigente: si no, una prórroga concedida después de una entrega
 * tardía la convertiría en puntual retroactivamente.
 */
function tk_entregada_tarde($row) {
    $env = $row['enviado_at'] ?? null;
    $plz = $row['plazo_al_enviar'] ?? null;
    if (!$env || !$plz) return false;
    return strtotime($env) > strtotime($plz);
}

/** vencida | hoy | proxima | a_tiempo. En tareas cerradas no aplica. */
function tk_semaforo($row, $ahora = null) {
    if (!tk_es_abierta($row['estado'] ?? '')) return 'a_tiempo';
    $plazo = tk_plazo_vigente($row);
    if (!$plazo) return 'a_tiempo';
    $ahora = ($ahora !== null) ? $ahora : time();
    $ts = strtotime($plazo);
    if ($ts < $ahora) return 'vencida';
    if (date('Y-m-d', $ts) === date('Y-m-d', $ahora)) return 'hoy';
    if ($ts - $ahora <= 48 * 3600) return 'proxima';
    return 'a_tiempo';
}

/* ══ Permisos ═════════════════════════════════════════════════════════
   Las reglas viven aquí una sola vez y las llaman TODOS los endpoints.
   Repetirlas en cada uno sería garantizar que alguna quede desalineada. */

/**
 * Fragmento WHERE que restringe el listado a lo que la sesión puede ver.
 *
 * Se aplica en el SQL, no filtrando en PHP después de traer todo: un
 * coordinador no debe descargar las notas de otro coordinador ni siquiera
 * para que la interfaz las oculte.
 *
 * Sin riesgo de inyección: lo único interpolado es $uid, forzado a int, y
 * $alias, que es un literal de nuestro propio código.
 */
function tk_filtro_visibilidad($alias = 't') {
    $rol = $_SESSION['user_rol'] ?? '';
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    if ($rol === 'Administrador' || $rol === 'Supervisor') return '1=1';
    if ($uid <= 0) return '0=1';
    if ($rol === 'Coordinador') {
        return "($alias.asignado_id = $uid"
             . " OR $alias.asignado_id IN (SELECT id FROM usuarios WHERE soporte_de_id = $uid))";
    }
    if ($rol === 'Soporte') return "$alias.asignado_id = $uid";

    // Cualquier otro rol: nada. Si mañana se añade un rol y alguien olvida
    // actualizar can_tareas(), el fallo será «no veo nada», no «lo veo todo».
    return '0=1';
}

/**
 * ¿La sesión puede ver esta fila? Espera `asignado_soporte_de` resuelto
 * por la consulta (LEFT JOIN usuarios).
 */
function tk_puede_ver($row) {
    if (!$row) return false;
    $rol = $_SESSION['user_rol'] ?? '';
    if ($rol === 'Administrador' || $rol === 'Supervisor') return true;

    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($uid <= 0) return false;
    if ((int)($row['asignado_id'] ?? 0) === $uid) return true;
    if ($rol === 'Coordinador' && (int)($row['asignado_soporte_de'] ?? 0) === $uid) return true;
    return false;
}

/**
 * ¿Se puede modificar el enunciado / la fecha 1?
 * Solo el Administrador y solo mientras la tarea siga abierta: en
 * `entregada` está bajo revisión y cambiar lo que se pidió invalidaría lo
 * que se está juzgando.
 *
 * @return array{ok:bool, error?:string}
 */
function tk_puede_editar($row) {
    if (!$row) return ['ok' => false, 'error' => 'La tarea no existe.'];
    if (($_SESSION['user_rol'] ?? '') !== 'Administrador') {
        return ['ok' => false, 'error' => 'Solo el Administrador puede modificar una tarea.'];
    }
    if (!tk_es_abierta($row['estado'] ?? '')) {
        return ['ok' => false, 'error' => tk_es_terminal($row['estado'])
            ? 'La tarea ya fue revisada y no admite cambios.'
            : 'La tarea está en revisión. Devuélvela con una observación antes de modificarla.'];
    }
    return ['ok' => true];
}

/**
 * ¿Se puede subir evidencia y enviar?
 * El asignado, o el Administrador en su nombre (queda registrado en la
 * bitácora). El Coordinador NO entrega por su soporte: la ve en solo
 * lectura. El Supervisor mira pero no entrega.
 */
function tk_puede_entregar($row) {
    if (!$row) return ['ok' => false, 'error' => 'La tarea no existe.'];
    if (!tk_es_abierta($row['estado'] ?? '')) {
        return ['ok' => false, 'error' => ($row['estado'] === 'entregada')
            ? 'La tarea ya fue enviada y está en revisión.'
            : 'La tarea ya fue revisada y no admite más entregas.'];
    }
    $rol = $_SESSION['user_rol'] ?? '';
    if ($rol === 'Administrador') return ['ok' => true, 'en_nombre_de' => true];

    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($uid > 0 && (int)($row['asignado_id'] ?? 0) === $uid) return ['ok' => true];

    return ['ok' => false, 'error' => 'Solo la persona asignada puede entregar esta tarea.'];
}

/** ¿Se puede dar veredicto? Solo el Administrador, solo sobre `entregada`. */
function tk_puede_revisar($row) {
    if (!$row) return ['ok' => false, 'error' => 'La tarea no existe.'];
    if (($_SESSION['user_rol'] ?? '') !== 'Administrador') {
        return ['ok' => false, 'error' => 'Solo el Administrador puede revisar tareas.'];
    }
    if (($row['estado'] ?? '') !== 'entregada') {
        return ['ok' => false, 'error' => tk_es_terminal($row['estado'] ?? '')
            ? 'La tarea ya fue revisada.'
            : 'Solo se puede revisar una tarea que ya fue entregada.'];
    }
    return ['ok' => true];
}

/** ¿Se puede fijar o retirar la 2ª fecha? Solo el Administrador, solo abierta. */
function tk_puede_prorrogar($row) {
    if (!$row) return ['ok' => false, 'error' => 'La tarea no existe.'];
    if (($_SESSION['user_rol'] ?? '') !== 'Administrador') {
        return ['ok' => false, 'error' => 'Solo el Administrador puede conceder una prórroga.'];
    }
    if (!tk_es_abierta($row['estado'] ?? '')) {
        return ['ok' => false, 'error' => 'Solo se puede prorrogar una tarea pendiente u observada.'];
    }
    return ['ok' => true];
}

/* ══ Adjuntos ═════════════════════════════════════════════════════════ */

function tk_carpeta_drive()  { return 'Tareas'; }
function tk_max_bytes()      { return defined('SG_MAX_BYTES') ? SG_MAX_BYTES : (4 * 1024 * 1024); }
/** Tope por tarea, contando material de referencia y evidencia. */
function tk_max_adjuntos()   { return 10; }

/* ══ Bitácora ═════════════════════════════════════════════════════════ */

/**
 * Inserta un evento en tareas_historial. La única función del catálogo que
 * toca la base de datos. Se llama SIEMPRE dentro de la transacción de la
 * acción que registra, para que no queden eventos de cambios que no se
 * llegaron a aplicar.
 *
 * @param mysqli      $conn
 * @param int         $tareaId
 * @param string      $accion  Uno del ENUM de tareas_historial.
 * @param string|null $detalle Texto libre; aquí se conserva lo que la fila
 *                             de `tareas` sobrescribe.
 */
function tk_historial($conn, $tareaId, $accion, $detalle = null) {
    $uid    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $nombre = $_SESSION['user_name'] ?? 'Sistema';
    $rol    = $_SESSION['user_rol']  ?? '';
    $tareaId = (int)$tareaId;

    $st = mysqli_prepare($conn,
        "INSERT INTO tareas_historial
            (tarea_id, accion, usuario_id, usuario_nombre, usuario_rol, detalle)
         VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($st, 'isisss', $tareaId, $accion, $uid, $nombre, $rol, $detalle);
    mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
}
```

- [ ] **Step 4: Correr el test hasta que pase**

Run: `php tests/tareas_catalogo_test.php`
Expected: `TODO OK: 63 aserciones, 0 fallidas` (el número exacto puede variar si añades casos).

- [ ] **Step 5: Verificar la sintaxis**

Run: `php -l includes/tareas_catalogo.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add includes/tareas_catalogo.php tests/tareas_catalogo_test.php
git commit -m "feat(tareas): catalogo con reglas de plazo, atraso y permisos + tests"
```

---

## Task 3: Helpers de autorización

**Files:**
- Modify: `includes/auth.php` (añadir al final, antes de `can_delete_turno`)

- [ ] **Step 1: Añadir los helpers**

Inserta este bloque en [auth.php](../../../includes/auth.php) justo después de `api_require_admin()` (línea 131).

```php
/* ── Tareas ─────────────────────────────────────────────────────────────
   El rol 'Soporte' (Tally Soporte) SOLO existe para este módulo. No se
   añade a can_report(), can_operaciones(), can_operate() ni
   can_validate(): así Incidencias, Capacitaciones, Operaciones y el resto
   quedan cerrados al rol nuevo sin tocar una línea de esos módulos. */

/** True si el usuario actual es un Tally Soporte. */
function is_soporte() {
    return ($_SESSION['user_rol'] ?? null) === 'Soporte';
}

/** Roles con acceso al módulo de Tareas. */
function can_tareas() {
    return in_array($_SESSION['user_rol'] ?? '',
        ['Administrador', 'Supervisor', 'Coordinador', 'Soporte'], true);
}

/** Bloquea el acceso a la página de Tareas si el rol no aplica. */
function require_tareas() {
    require_login();
    if (!can_tareas()) {
        http_response_code(403);
        die('403 · No tienes permisos para acceder a Tareas.');
    }
}

/** Para endpoints JSON de Tareas: corta con 401/403 si la sesión no aplica. */
function api_require_tareas() {
    api_require_login();
    if (!can_tareas()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No tienes permisos para Tareas.']);
        exit;
    }
}
```

- [ ] **Step 2: Verificar la sintaxis**

Run: `php -l includes/auth.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Verificar que el rol nuevo NO abre nada más**

Run:
```bash
php -r "session_start(); \$_SESSION['user_rol']='Soporte'; require 'includes/auth.php'; var_dump(can_tareas(), can_report(), can_operaciones(), can_operate(), can_validate(), is_admin());"
```
Expected: `bool(true)` seguido de cinco `bool(false)`. Si alguno de los cinco es `true`, has abierto un módulo por accidente.

- [ ] **Step 4: Commit**

```bash
git add includes/auth.php
git commit -m "feat(tareas): helpers de autorizacion del rol Soporte"
```

---

## Task 4: Alta de usuarios Tally Soporte

**Files:**
- Modify: `api/save_usuario.php:17-31` y los bloques UPDATE/INSERT
- Modify: `api/get_usuarios.php:8-23`
- Modify: `pages/usuarios.php:633-650` (formulario), `:708-710` (chip), `:756` y `:768-775` (JS)

- [ ] **Step 1: Ampliar `api/save_usuario.php`**

Sustituye el bloque de lectura de payload y validaciones ([líneas 14-31](../../../api/save_usuario.php#L14-L31)) por:

```php
$id       = isset($data['id']) ? (int)$data['id'] : 0;
$email    = trim($data['email']  ?? '');
$nombre   = trim($data['nombre'] ?? '');
$rol      = $data['rol']         ?? 'Operador';
$estado   = $data['estado']      ?? 'Activo';
$password = $data['password']    ?? '';
$soporteDe = isset($data['soporte_de_id']) && $data['soporte_de_id'] !== ''
           ? (int)$data['soporte_de_id'] : null;

// Validaciones
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Correo inválido.']);
    exit;
}
if ($nombre === '') {
    echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio.']);
    exit;
}
if (!in_array($rol, ['Administrador', 'Supervisor', 'Coordinador', 'Soporte', 'Operador'], true)) $rol = 'Coordinador';
if (!in_array($estado, ['Activo', 'Inactivo'], true))                                             $estado = 'Activo';

// ── Coordinador a cargo (solo para Tally Soporte) ───────────────────────
// Se fuerza a NULL en cualquier otro rol: si no, cambiar el rol de un
// soporte a Coordinador dejaría viva una relación de jefatura huérfana que
// nada volvería a mirar y que el filtro de visibilidad de Tareas sí lee.
if ($rol !== 'Soporte') {
    $soporteDe = null;
} else {
    if (!$soporteDe) {
        echo json_encode(['success' => false,
            'error' => 'Un Tally Soporte necesita un Coordinador a cargo.']);
        exit;
    }
    if ($id > 0 && $soporteDe === $id) {
        echo json_encode(['success' => false,
            'error' => 'Un usuario no puede ser soporte de sí mismo.']);
        exit;
    }
    $st = mysqli_prepare($conn, "SELECT rol FROM usuarios WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $soporteDe);
    mysqli_stmt_execute($st);
    $jefe = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$jefe || $jefe['rol'] !== 'Coordinador') {
        echo json_encode(['success' => false,
            'error' => 'El coordinador a cargo debe ser un usuario con rol Coordinador.']);
        exit;
    }
}
```

- [ ] **Step 2: Persistir `soporte_de_id` en el UPDATE y el INSERT**

Sustituye el bloque `if ($id > 0) { … } else { … }` ([líneas 33-70](../../../api/save_usuario.php#L33-L70)) por:

```php
if ($id > 0) {
    // ── UPDATE ──
    if ($password !== '') {
        // Cambia password
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE usuarios SET email=?, nombre=?, rol=?, estado=?, soporte_de_id=?, password=? WHERE id=?"
        );
        mysqli_stmt_bind_param($stmt, 'ssssisi', $email, $nombre, $rol, $estado, $soporteDe, $hash, $id);
    } else {
        // No toca password
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE usuarios SET email=?, nombre=?, rol=?, estado=?, soporte_de_id=? WHERE id=?"
        );
        mysqli_stmt_bind_param($stmt, 'ssssii', $email, $nombre, $rol, $estado, $soporteDe, $id);
    }
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

} else {
    // ── INSERT ──
    if ($password === '' || strlen($password) < 6) {
        echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.']);
        exit;
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO usuarios (email, password, nombre, rol, estado, soporte_de_id) VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'sssssi', $email, $hash, $nombre, $rol, $estado, $soporteDe);
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
}
```

`mysqli_stmt_bind_param` con tipo `'i'` y valor `null` inserta `NULL`, que es justo lo que queremos cuando el rol no es Soporte.

- [ ] **Step 3: Devolver el dato en `api/get_usuarios.php`**

Sustituye el cuerpo entero del archivo ([líneas 8-23](../../../api/get_usuarios.php#L8-L23)) por:

```php
$r = mysqli_query(
    $conn,
    "SELECT u.id, u.email, u.nombre, u.rol, u.estado, u.ultimo_acceso, u.created_at,
            u.soporte_de_id, c.nombre AS soporte_de_nombre
       FROM usuarios u
       LEFT JOIN usuarios c ON c.id = u.soporte_de_id
      WHERE u.oculto IS NULL OR u.oculto = 0
      ORDER BY u.nombre ASC"
);

$out = [];
while ($u = mysqli_fetch_assoc($r)) {
    $u['soporte_de_id'] = $u['soporte_de_id'] !== null ? (int)$u['soporte_de_id'] : null;
    $out[] = $u;
}

// Coordinadores activos, para poblar el selector «Coordinador a cargo».
$coords = [];
$rc = mysqli_query($conn,
    "SELECT id, nombre FROM usuarios WHERE rol='Coordinador' AND estado='Activo' ORDER BY nombre ASC");
if ($rc) while ($c = mysqli_fetch_assoc($rc)) {
    $coords[] = ['id' => (int)$c['id'], 'nombre' => $c['nombre']];
}

echo json_encode([
    'success'       => true,
    'data'          => $out,
    'roles'         => ['Administrador', 'Supervisor', 'Coordinador', 'Soporte', 'Operador'],
    'coordinadores' => $coords,
]);
```

- [ ] **Step 4: Añadir el campo al formulario de `pages/usuarios.php`**

Sustituye el bloque `<div class="usr-row2">` ([líneas 633-650](../../../pages/usuarios.php#L633-L650)) por:

```html
      <div class="usr-row2">
        <div class="usr-field">
          <label>Rol</label>
          <select id="um-rol">
            <option value="Coordinador">Coordinador</option>
            <option value="Soporte">Tally Soporte</option>
            <option value="Supervisor">Supervisor</option>
            <option value="Administrador">Administrador</option>
            <option value="Operador">Operador</option>
          </select>
        </div>
        <div class="usr-field">
          <label>Estado</label>
          <select id="um-estado">
            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>
          </select>
        </div>
      </div>
      <!-- Solo para Tally Soporte: de qué coordinador es apoyo directo.
           Se muestra y se oculta según el rol elegido. -->
      <div class="usr-field" id="um-soporte-wrap" style="display:none">
        <label>Coordinador a cargo</label>
        <select id="um-soporte-de"><option value="">— Selecciona —</option></select>
        <span class="hint">El Tally Soporte es apoyo directo de este coordinador, que verá sus tareas en solo lectura.</span>
      </div>
```

- [ ] **Step 5: Añadir el chip de rol**

Sustituye el bloque `roleClass` ([líneas 708-710](../../../pages/usuarios.php#L708-L710)) por:

```javascript
      // Dynamic color-coding classes for each role
      const roleClass = u.rol === 'Administrador' ? 'is-admin' :
                        u.rol === 'Coordinador' ? 'is-coord' :
                        u.rol === 'Soporte' ? 'is-sop' :
                        u.rol === 'Supervisor' ? 'is-super' : 'is-op';
      const rolTxt = u.rol === 'Soporte' ? 'Tally Soporte' : u.rol;
```

Y en el `tr.innerHTML`, sustituye la celda del rol ([línea 724](../../../pages/usuarios.php#L724)) por:

```javascript
        <td><span class="usr-badge ${roleClass}"><span class="dot"></span>${esc(rolTxt)}${
              u.soporte_de_nombre ? `<span style="font-weight:500;opacity:.75"> · ${esc(u.soporte_de_nombre)}</span>` : ''
            }</span></td>
```

- [ ] **Step 6: Añadir el estilo del chip**

Busca en el `<style>` de `pages/usuarios.php` la regla `.usr-badge.is-coord` y añade justo debajo:

```css
    .usr-badge.is-sop   { color:#7c3aed; background:rgba(124,58,237,.10); border-color:rgba(124,58,237,.22); }
    .usr-badge.is-sop .dot { background:#7c3aed; }
```

- [ ] **Step 7: Conectar el campo en el JS**

En el bloque `<script>` de `pages/usuarios.php`:

a) Junto a `let usuarios = [];` ([línea 672](../../../pages/usuarios.php#L672)) añade:

```javascript
  let coordinadores = [];
```

b) Dentro de `cargar()`, después de `usuarios = data.data || [];` ([línea 742](../../../pages/usuarios.php#L742)) añade:

```javascript
      coordinadores = data.coordinadores || [];
```

c) Añade estas dos funciones justo antes de `function openModal(id) {` ([línea 749](../../../pages/usuarios.php#L749)):

```javascript
  /* El selector de coordinador solo existe para el Tally Soporte: para
     cualquier otro rol el servidor fuerza soporte_de_id a NULL, así que
     mostrarlo sería ofrecer un dato que se va a descartar. */
  function pintarCoordinadores(sel) {
    const s = $('um-soporte-de');
    s.innerHTML = '<option value="">— Selecciona —</option>';
    coordinadores.forEach(c => {
      const o = document.createElement('option');
      o.value = c.id; o.textContent = c.nombre;
      s.append(o);
    });
    s.value = sel ? String(sel) : '';
  }

  function toggleSoporte() {
    $('um-soporte-wrap').style.display = ($('um-rol').value === 'Soporte') ? '' : 'none';
  }
```

d) Dentro de `openModal()`, después de `$('um-rol').value = u ? u.rol : 'Operador';` ([línea 756](../../../pages/usuarios.php#L756)) añade:

```javascript
    pintarCoordinadores(u ? u.soporte_de_id : null);
    toggleSoporte();
```

e) Registra el listener junto al resto de listeners del final del script:

```javascript
  $('um-rol').addEventListener('change', toggleSoporte);
```

f) En `guardar()`, añade el campo al payload ([líneas 768-775](../../../pages/usuarios.php#L768-L775)):

```javascript
    const payload = {
      id:       parseInt($('um-id').value, 10) || 0,
      nombre:   $('um-nombre').value.trim(),
      email:    $('um-email').value.trim(),
      rol:      $('um-rol').value,
      estado:   $('um-estado').value,
      password: $('um-password').value,
      soporte_de_id: $('um-rol').value === 'Soporte' ? ($('um-soporte-de').value || '') : '',
    };
```

g) Junto al resto de validaciones de `guardar()`, añade:

```javascript
    if (payload.rol === 'Soporte' && !payload.soporte_de_id) {
      toast('Un Tally Soporte necesita un Coordinador a cargo', 'error');
      $('um-soporte-de').focus(); return;
    }
```

- [ ] **Step 8: Verificar la sintaxis**

Run: `php -l api/save_usuario.php && php -l api/get_usuarios.php && php -l pages/usuarios.php`
Expected: tres veces `No syntax errors detected`

- [ ] **Step 9: Probar el alta en el navegador**

1. Entra como Administrador y abre `pages/usuarios.php`.
2. **Nuevo usuario** → rol **Tally Soporte**. Comprueba que aparece el selector «Coordinador a cargo».
3. Guarda sin elegir coordinador → debe salir el error `Un Tally Soporte necesita un Coordinador a cargo`.
4. Elige un coordinador y guarda. En la tabla debe verse el chip morado `Tally Soporte · <nombre del coordinador>`.
5. Edítalo, cámbialo a `Coordinador`, guarda, y verifica en BD que la relación se limpió:

```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "SELECT id, nombre, rol, soporte_de_id FROM usuarios WHERE rol IN ('Soporte','Coordinador');"
```
Expected: la fila que pasó a `Coordinador` tiene `soporte_de_id` = `NULL`.

- [ ] **Step 10: Dejar creados los usuarios de prueba**

Los necesitarás en todas las tareas siguientes. Crea desde la interfaz:
- `coord.test@portally.local` — rol Coordinador — password `test1234`
- `soporte.test@portally.local` — rol Tally Soporte, a cargo de **coord.test** — password `test1234`
- `coord2.test@portally.local` — rol Coordinador — password `test1234` (para probar que no ve lo ajeno)

Anota sus `id`:

```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "SELECT id, nombre, email, rol, soporte_de_id FROM usuarios WHERE email LIKE '%.test@portally.local';"
```

- [ ] **Step 11: Commit**

```bash
git add api/save_usuario.php api/get_usuarios.php pages/usuarios.php
git commit -m "feat(tareas): alta de usuarios Tally Soporte con coordinador a cargo"
```

---

## Task 5: Entrada en el sidebar

**Files:**
- Modify: `includes/sidebar.php:34` y `:143-163`

- [ ] **Step 1: Sacar «Tareas» del bloque de roles operativos**

En [sidebar.php](../../../includes/sidebar.php), el bloque de módulos está envuelto en
`<?php if (in_array($rol, ['Administrador','Supervisor','Coordinador'], true)): ?>` (línea 34).
El Soporte no está ahí y no debe estarlo: no puede ver Incidencias ni Operaciones.

Inserta el ítem **antes** de esa línea 34, justo después del cierre del enlace «Turno Actual» (línea 32):

```php
    <?php if (in_array($rol, ['Administrador', 'Supervisor', 'Coordinador', 'Soporte'], true)): ?>
    <!-- TAREAS · primer nivel a propósito: es lo primero que abre un
         asignado cada día, no un submódulo de Control de Campo. -->
    <a href="<?= $sb_base ?? '..' ?>/pages/tareas.php" class="nav-item<?= ($cur === 'tareas.php') ? ' active' : '' ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 11l3 3 8-8"/>
          <path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>
        </svg>
      </span>
      <span class="nav-label">Tareas</span>
      <span class="tip">Tareas</span>
    </a>
    <?php endif; ?>

```

No hay que tocar nada más: el Soporte no entra en el `if` de la línea 34 ni en el de Administración, así que su sidebar queda en Turno Actual + Tareas + Cerrar sesión.

- [ ] **Step 2: Añadir la miga de pan**

En [header.php](../../../includes/header.php), añade la entrada al `$bcMap` (línea 3-10):

```php
  'tareas.php'      => ['Tareas'],
```

- [ ] **Step 3: Verificar la sintaxis**

Run: `php -l includes/sidebar.php && php -l includes/header.php`
Expected: dos veces `No syntax errors detected`

- [ ] **Step 4: Verificar el sidebar por rol en el navegador**

`pages/tareas.php` todavía no existe, así que el enlace dará 404 — eso es lo esperado en este punto. Lo que se comprueba es **qué ítems aparecen**:

| Entra como | Debe ver |
|---|---|
| `soporte.test@portally.local` | Turno Actual · **Tareas** · Cerrar sesión. **Nada más** |
| `coord.test@portally.local` | Turno Actual · **Tareas** · Control de Campo · Operaciones · Registro tallyman · Relevo · Cerrar sesión |
| Administrador | Todo lo anterior + el bloque Administración |

Si el Soporte ve Control de Campo, el `if` nuevo se coló dentro del bloque de la línea 34 en vez de antes.

- [ ] **Step 5: Commit**

```bash
git add includes/sidebar.php includes/header.php
git commit -m "feat(tareas): entrada de Tareas en el sidebar y sidebar reducido para Soporte"
```

---

# FASE 2 · API

Todos los endpoints siguen el patrón de [save_capacitacion.php](../../../api/save_capacitacion.php): `require_once` de db + auth + catálogo, comprobación de permiso, validación, transacción y respuesta `{success, error}`.

**Cómo probar un endpoint desde la terminal.** Necesitas la cookie de sesión. Primero autentícate y guarda el frasco de cookies:

```bash
curl -s -c /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"TU_ADMIN@dominio.com","password":"TU_PASSWORD"}'
```

Y luego reutilízalo con `-b /tmp/ck-admin.txt`. Repite con `coord.test`, `coord2.test` y `soporte.test` en frascos distintos (`/tmp/ck-coord.txt`, etc.): los vas a necesitar en casi todas las verificaciones.

---

## Task 6: `api/get_asignables.php`

**Files:**
- Create: `api/get_asignables.php`

- [ ] **Step 1: Escribir el endpoint**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   Destinatarios posibles de una tarea
   ───────────────────────────────────────────────────────────────────────
   Coordinadores y Tally Soporte activos, agrupados por puesto, para el
   selector del modal de creación.

   Es un endpoint nuevo y no una ampliación de get_coordinadores.php a
   propósito: ese alimenta hoy el selector de Colaboradores, y devolver
   ahí también a los Soportes metería en esa lista un puesto que no puede
   tener colaboradores a cargo.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_tareas();

header('Content-Type: application/json');

// FIELD() en vez de ORDER BY rol: el orden del ENUM es un detalle del
// esquema y no debe decidir cómo se ve el selector.
$sql = "SELECT u.id, u.nombre, u.rol, u.soporte_de_id, c.nombre AS coordinador_nombre
          FROM usuarios u
          LEFT JOIN usuarios c ON c.id = u.soporte_de_id
         WHERE u.rol IN ('Coordinador','Soporte')
           AND u.estado = 'Activo'
         ORDER BY FIELD(u.rol,'Coordinador','Soporte'), u.nombre ASC";

$r = mysqli_query($conn, $sql);
if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$out = [];
while ($u = mysqli_fetch_assoc($r)) {
    $out[] = [
        'id'                 => (int)$u['id'],
        'nombre'             => $u['nombre'],
        'rol'                => $u['rol'],
        'rol_label'          => tk_rol_label($u['rol']),
        'soporte_de_id'      => $u['soporte_de_id'] !== null ? (int)$u['soporte_de_id'] : null,
        'coordinador_nombre' => $u['coordinador_nombre'],
    ];
}

echo json_encode(['success' => true, 'data' => $out]);
```

- [ ] **Step 2: Verificar la sintaxis**

Run: `php -l api/get_asignables.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Probarlo**

Run:
```bash
curl -s -b /tmp/ck-admin.txt http://localhost/portallyman.online/api/get_asignables.php
```
Expected: `{"success":true,"data":[...]}` con los coordinadores primero y los soportes después, cada soporte con su `coordinador_nombre` poblado y `rol_label` igual a `Tally Soporte`.

- [ ] **Step 4: Verificar que exige sesión**

Run: `curl -s -i http://localhost/portallyman.online/api/get_asignables.php | head -1`
Expected: `HTTP/1.1 401 Unauthorized`

- [ ] **Step 5: Commit**

```bash
git add api/get_asignables.php
git commit -m "feat(tareas): endpoint de destinatarios asignables"
```

---

## Task 7: `api/save_tarea.php`

**Files:**
- Create: `api/save_tarea.php`

- [ ] **Step 1: Escribir el endpoint**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Alta multi-destinatario y edición de una Tarea (JSON)
   ───────────────────────────────────────────────────────────────────────
   Alta:
   { titulo, descripcion?, prioridad?, fecha_limite:'YYYY-MM-DD HH:MM',
     destinatarios:[int,...] }
       → una fila por destinatario, todas con el mismo lote_id.

   Edición:
   { id, titulo, descripcion?, prioridad?, fecha_limite, aplicar_a_lote?:bool }
       → solo el enunciado y la fecha 1. El estado, la 2ª fecha y la nota
         NO se tocan aquí: los mueven enviar/revisar/prorrogar.

   Solo Administrador.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

$id          = isset($data['id']) ? (int)$data['id'] : 0;
$titulo      = trim($data['titulo'] ?? '');
$descripcion = trim($data['descripcion'] ?? '');
$prioridad   = $data['prioridad'] ?? 'media';
$fechaLimite = trim($data['fecha_limite'] ?? '');
$alLote      = !empty($data['aplicar_a_lote']);

// ── Validaciones comunes ────────────────────────────────────────────────
if ($titulo === '')           { echo json_encode(['success'=>false,'error'=>'Indica el título de la tarea.']); exit; }
if (mb_strlen($titulo) > 180) { echo json_encode(['success'=>false,'error'=>'El título supera los 180 caracteres.']); exit; }
if (!array_key_exists($prioridad, tk_prioridades())) $prioridad = 'media';

// Se acepta 'Y-m-d H:i' (lo que manda datetime-local) y 'Y-m-d H:i:s'.
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $fechaLimite)) {
    echo json_encode(['success'=>false,'error'=>'Fecha límite inválida.']); exit;
}
if (strlen($fechaLimite) === 16) $fechaLimite .= ':00';
if (strtotime($fechaLimite) === false) {
    echo json_encode(['success'=>false,'error'=>'Fecha límite inválida.']); exit;
}

$descSql   = $descripcion !== '' ? $descripcion : null;
$creadoPor = $_SESSION['user_name'] ?? 'Administrador';
$creadoUid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

/* ══════════════════════ EDICIÓN ══════════════════════════════════════ */
if ($id > 0) {
    $st = mysqli_prepare($conn,
        "SELECT id, estado, lote_id, titulo, fecha_limite FROM tareas WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $id);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);

    $permiso = tk_puede_editar($row);
    if (!$permiso['ok']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $st = mysqli_prepare($conn,
            "UPDATE tareas SET titulo=?, descripcion=?, prioridad=?, fecha_limite=? WHERE id=?");
        mysqli_stmt_bind_param($st, 'ssssi', $titulo, $descSql, $prioridad, $fechaLimite, $id);
        if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
        mysqli_stmt_close($st);

        $detalle = 'Título: «' . $row['titulo'] . '» → «' . $titulo . '». '
                 . 'Fecha 1: ' . $row['fecha_limite'] . ' → ' . $fechaLimite . '.';
        tk_historial($conn, $id, 'editada', $detalle);

        $afectadas = 1;
        if ($alLote && $row['lote_id']) {
            /* Solo las que sigan PENDIENTES. Tocar una ya entregada o ya
               calificada cambiaría el enunciado bajo el que se juzgó. */
            $lote = (int)$row['lote_id'];
            $st = mysqli_prepare($conn,
                "UPDATE tareas SET titulo=?, descripcion=?, prioridad=?, fecha_limite=?
                  WHERE lote_id=? AND id<>? AND estado='pendiente'");
            mysqli_stmt_bind_param($st, 'ssssii', $titulo, $descSql, $prioridad, $fechaLimite, $lote, $id);
            if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
            $afectadas += mysqli_stmt_affected_rows($st);
            mysqli_stmt_close($st);

            // Bitácora en cada hermana afectada, no solo en la editada.
            $rs = mysqli_query($conn,
                "SELECT id FROM tareas WHERE lote_id=$lote AND id<>$id AND estado='pendiente'");
            while ($h = mysqli_fetch_assoc($rs)) {
                tk_historial($conn, (int)$h['id'], 'editada', 'Editada junto con su lote. ' . $detalle);
            }
        }

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'id' => $id, 'afectadas' => $afectadas]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'error' => 'No se pudo guardar: ' . $e->getMessage()]);
    }
    exit;
}

/* ══════════════════════ ALTA ═════════════════════════════════════════ */
$destinatarios = $data['destinatarios'] ?? [];
if (!is_array($destinatarios) || !count($destinatarios)) {
    echo json_encode(['success'=>false,'error'=>'Elige al menos un destinatario.']); exit;
}
// Sanea a enteros únicos y positivos: lo que se interpola abajo tiene que
// ser incuestionablemente numérico.
$ids = array_values(array_unique(array_filter(array_map('intval', $destinatarios), fn($v) => $v > 0)));
if (!count($ids)) {
    echo json_encode(['success'=>false,'error'=>'Destinatarios inválidos.']); exit;
}
if (count($ids) > 50) {
    echo json_encode(['success'=>false,'error'=>'No se pueden crear más de 50 tareas de una vez.']); exit;
}

// Trae los destinatarios y comprueba que TODOS son válidos. Ya son ints,
// así que el IN es seguro sin placeholders.
$inList = implode(',', $ids);
// El LEFT JOIN trae el nombre del coordinador del que cuelga cada Soporte:
// se congela en la tarea junto al id, para que la atribución histórica
// sobreviva al borrado de ese coordinador.
$rs = mysqli_query($conn,
    "SELECT u.id, u.nombre, u.rol, u.soporte_de_id, c.nombre AS coordinador_nombre
       FROM usuarios u
       LEFT JOIN usuarios c ON c.id = u.soporte_de_id
      WHERE u.id IN ($inList) AND u.estado='Activo' AND u.rol IN ('Coordinador','Soporte')");
$validos = [];
while ($u = mysqli_fetch_assoc($rs)) $validos[(int)$u['id']] = $u;

if (count($validos) !== count($ids)) {
    // Fallar entero en vez de crear las que sí valen: si el admin creyó
    // encargarle a cinco y solo se crearon cuatro, nadie se entera.
    echo json_encode(['success' => false,
        'error' => 'Algún destinatario no existe, está inactivo o no puede recibir tareas.']); exit;
}

mysqli_begin_transaction($conn);
try {
    $ins = mysqli_prepare($conn,
        "INSERT INTO tareas
            (lote_id, titulo, descripcion, prioridad,
             asignado_id, asignado_nombre, asignado_rol,
             coordinador_ref_id, coordinador_ref_nombre,
             fecha_limite, estado, creado_por, creado_por_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?)");

    $loteId  = null;   // la primera fila entra con NULL y luego adopta su propio id
    $creados = [];

    foreach ($ids as $uid) {
        $u       = $validos[$uid];
        $nombre  = $u['nombre'];
        $rolAsig = $u['rol'];
        // El jefe AL CREAR, congelado id + nombre. Solo aplica al Soporte;
        // para un Coordinador ambos son NULL.
        $refCoord    = ($rolAsig === 'Soporte' && $u['soporte_de_id'] !== null)
                     ? (int)$u['soporte_de_id'] : null;
        $refCoordNom = ($rolAsig === 'Soporte') ? $u['coordinador_nombre'] : null;

        mysqli_stmt_bind_param($ins, 'isssississsi',
            $loteId, $titulo, $descSql, $prioridad,
            $uid, $nombre, $rolAsig,
            $refCoord, $refCoordNom,
            $fechaLimite, $creadoPor, $creadoUid);
        if (!mysqli_stmt_execute($ins)) throw new Exception(mysqli_stmt_error($ins));

        $nuevoId = (int)mysqli_insert_id($conn);
        $creados[] = $nuevoId;

        // La primera fila del lote adopta su propio id como lote_id. Así no
        // hace falta ni tabla ni secuencia aparte, y una tarea para una sola
        // persona tampoco es un caso especial: lleva su propio id.
        if ($loteId === null) {
            $loteId = $nuevoId;
            if (!mysqli_query($conn, "UPDATE tareas SET lote_id=$nuevoId WHERE id=$nuevoId")) {
                throw new Exception(mysqli_error($conn));
            }
        }

        tk_historial($conn, $nuevoId, 'creada',
            'Asignada a ' . $nombre . ' (' . tk_rol_label($rolAsig) . '). '
          . 'Fecha límite: ' . $fechaLimite . '.');
    }
    mysqli_stmt_close($ins);

    mysqli_commit($conn);
    echo json_encode([
        'success'  => true,
        'ids'      => $creados,
        'lote_id'  => $loteId,
        'creadas'  => count($creados),
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo crear: ' . $e->getMessage()]);
}
```

- [ ] **Step 2: Verificar la sintaxis**

Run: `php -l api/save_tarea.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Crear una tarea para dos destinatarios**

Sustituye `7` y `9` por los `id` reales de `coord.test` y `soporte.test` (Task 4, Step 10).

Run:
```bash
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/save_tarea.php \
  -H "Content-Type: application/json" \
  -d '{"titulo":"Inventario de precintos","descripcion":"Contar y fotografiar el stock del almacen.","prioridad":"alta","fecha_limite":"2026-08-15 23:59","destinatarios":[7,9]}'
```
Expected: `{"success":true,"ids":[1,2],"lote_id":1,"creadas":2}`

- [ ] **Step 4: Verificar el lote en la base de datos**

Run:
```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "SELECT id, lote_id, titulo, asignado_id, asignado_nombre, asignado_rol, coordinador_ref_id, estado, entregas_count, plazo_al_enviar FROM tareas;"
```
Expected: dos filas con el **mismo** `lote_id`, igual al `id` de la primera. `estado='pendiente'`, `entregas_count=0`, `plazo_al_enviar=NULL`. La fila del soporte tiene `coordinador_ref_id` con el id de su coordinador; la del coordinador, `NULL`.

- [ ] **Step 5: Verificar que un destinatario inválido aborta TODO**

Run:
```bash
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/save_tarea.php \
  -H "Content-Type: application/json" \
  -d '{"titulo":"Prueba parcial","fecha_limite":"2026-08-15 23:59","destinatarios":[7,99999]}'
```
Expected: `{"success":false,"error":"Algún destinatario no existe..."}` y **ninguna** fila nueva:

```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "SELECT COUNT(*) FROM tareas WHERE titulo='Prueba parcial';"
```
Expected: `0`

- [ ] **Step 6: Verificar que un coordinador no puede crear tareas**

Run:
```bash
curl -s -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/save_tarea.php \
  -H "Content-Type: application/json" \
  -d '{"titulo":"No deberia crearse","fecha_limite":"2026-08-15 23:59","destinatarios":[7]}'
```
Expected: `{"success":false,"error":"Solo Administrador."}` con HTTP 403.

- [ ] **Step 7: Commit**

```bash
git add api/save_tarea.php
git commit -m "feat(tareas): alta multi-destinatario por lote y edicion"
```

---

## Task 8: `api/get_tareas.php`

**Files:**
- Create: `api/get_tareas.php`

- [ ] **Step 1: Escribir el endpoint**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Listado de Tareas (JSON)
   ───────────────────────────────────────────────────────────────────────
   Filtros por GET (todos opcionales):
     estado=pendiente|entregada|observada|aprobada|rechazada
     asignado=<id>      mes=YYYY-MM (sobre fecha_limite)      atrasadas=1

   El filtro de VISIBILIDAD se aplica en el SQL, no en PHP después de traer
   todo: un coordinador no debe descargar las notas de otro coordinador ni
   siquiera para que la interfaz las oculte.

   Los cálculos de plazo viajan RESUELTOS en el JSON. Recalcularlos en
   JavaScript los ataría al reloj del navegador; todo el sistema opera en
   America/Lima.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_tareas();

header('Content-Type: application/json');

$where = [tk_filtro_visibilidad('t')];

$estado = $_GET['estado'] ?? '';
if ($estado !== '' && array_key_exists($estado, tk_estados())) {
    $where[] = "t.estado = '" . mysqli_real_escape_string($conn, $estado) . "'";
}

$asignado = isset($_GET['asignado']) ? (int)$_GET['asignado'] : 0;
if ($asignado > 0) $where[] = "t.asignado_id = $asignado";

$mes = $_GET['mes'] ?? '';
if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $where[] = "DATE_FORMAT(t.fecha_limite, '%Y-%m') = '" . mysqli_real_escape_string($conn, $mes) . "'";
}

$sql = "SELECT t.*,
               u.soporte_de_id AS asignado_soporte_de,
               u.estado        AS asignado_estado,
               cr.nombre       AS coordinador_ref_nombre
          FROM tareas t
          LEFT JOIN usuarios u  ON u.id  = t.asignado_id
          LEFT JOIN usuarios cr ON cr.id = t.coordinador_ref_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY (t.estado IN ('pendiente','observada')) DESC,
                  COALESCE(t.fecha_limite_2, t.fecha_limite) ASC,
                  t.id DESC";

$r = mysqli_query($conn, $sql);
if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$filas = [];
$ids   = [];
while ($row = mysqli_fetch_assoc($r)) { $filas[] = $row; $ids[] = (int)$row['id']; }

// ── Adjuntos, en UNA consulta agregada, no una por tarea ────────────────
$adjuntos = [];
if (count($ids)) {
    $inList = implode(',', $ids);
    $ra = mysqli_query($conn,
        "SELECT id, tarea_id, nombre_archivo, mime, peso_bytes, drive_url, ruta_local,
                estado, origen, entrega_nro, subido_por, subido_por_id, created_at
           FROM tareas_adjuntos
          WHERE tarea_id IN ($inList)
          ORDER BY entrega_nro ASC, id ASC");
    while ($a = mysqli_fetch_assoc($ra)) {
        $tid = (int)$a['tarea_id'];
        $a['id']            = (int)$a['id'];
        $a['peso_bytes']    = (int)$a['peso_bytes'];
        $a['entrega_nro']   = (int)$a['entrega_nro'];
        $a['subido_por_id'] = $a['subido_por_id'] !== null ? (int)$a['subido_por_id'] : null;
        if (!isset($adjuntos[$tid])) $adjuntos[$tid] = [];
        $adjuntos[$tid][] = $a;
    }
}

// ── Enriquecer con los cálculos del catálogo ────────────────────────────
$ahora     = time();
$soloAtras = !empty($_GET['atrasadas']);
$out       = [];

foreach ($filas as $row) {
    $atrasada = tk_esta_atrasada($row, $ahora);
    if ($soloAtras && !$atrasada) continue;   // el chip ATRASADAS cruza con los dos estados abiertos

    $tid = (int)$row['id'];
    $out[] = array_merge($row, [
        'id'                  => $tid,
        'lote_id'             => $row['lote_id'] !== null ? (int)$row['lote_id'] : null,
        'asignado_id'         => $row['asignado_id'] !== null ? (int)$row['asignado_id'] : null,
        'asignado_soporte_de' => $row['asignado_soporte_de'] !== null ? (int)$row['asignado_soporte_de'] : null,
        'asignado_rol_label'  => tk_rol_label($row['asignado_rol']),
        'entregas_count'      => (int)$row['entregas_count'],
        'nota'                => $row['nota'] !== null ? (int)$row['nota'] : null,
        'nota_label'          => tk_nota_label($row['nota']),
        'plazo_vigente'       => tk_plazo_vigente($row),
        'tiene_prorroga'      => tk_plazo_vigente($row) !== $row['fecha_limite'],
        'atrasada'            => $atrasada,
        'dias_atraso'         => tk_dias_atraso($row, $ahora),
        'entregada_tarde'     => tk_entregada_tarde($row),
        'semaforo'            => tk_semaforo($row, $ahora),
        'es_abierta'          => tk_es_abierta($row['estado']),
        'es_terminal'         => tk_es_terminal($row['estado']),
        'adjuntos'            => $adjuntos[$tid] ?? [],
    ]);
}

echo json_encode(['success' => true, 'data' => $out, 'ahora' => date('Y-m-d H:i:s', $ahora)]);
```

- [ ] **Step 2: Verificar la sintaxis**

Run: `php -l api/get_tareas.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Verificar que el admin ve todo**

Run:
```bash
curl -s -b /tmp/ck-admin.txt "http://localhost/portallyman.online/api/get_tareas.php" | php -r "\$d=json_decode(file_get_contents('php://stdin'),true); foreach(\$d['data'] as \$t) echo \$t['id'],' ',\$t['asignado_nombre'],' ',\$t['estado'],' plazo=',\$t['plazo_vigente'],' atrasada=',var_export(\$t['atrasada'],true),' sem=',\$t['semaforo'],\"\n\";"
```
Expected: las dos tareas creadas en la Task 7, con `plazo_vigente = 2026-08-15 23:59:00` y `semaforo` coherente con la fecha de hoy.

- [ ] **Step 4: Verificar el aislamiento entre coordinadores**

Run:
```bash
curl -s -b /tmp/ck-coord.txt  "http://localhost/portallyman.online/api/get_tareas.php" | grep -o '"asignado_nombre":"[^"]*"' | sort -u
curl -s -b /tmp/ck-coord2.txt "http://localhost/portallyman.online/api/get_tareas.php" | grep -o '"asignado_nombre":"[^"]*"' | sort -u
```
Expected:
- `coord.test` ve **dos** nombres: el suyo y el de `soporte.test`.
- `coord2.test` no ve **ninguno**: no tiene tareas ni soportes.

Si `coord2.test` ve algo, el filtro de visibilidad no se está aplicando.

- [ ] **Step 5: Verificar que el soporte solo se ve a sí mismo**

Run:
```bash
curl -s -b /tmp/ck-soporte.txt "http://localhost/portallyman.online/api/get_tareas.php" | grep -o '"asignado_nombre":"[^"]*"' | sort -u
```
Expected: solo el nombre de `soporte.test`. **No** debe aparecer su coordinador: la relación es de arriba abajo, no al revés.

- [ ] **Step 6: Verificar el atraso derivado**

Mueve a mano la fecha de una tarea al pasado (esto simula el paso del tiempo sin esperar):

```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "UPDATE tareas SET fecha_limite='2026-07-20 23:59:00' WHERE id=1;"
curl -s -b /tmp/ck-admin.txt "http://localhost/portallyman.online/api/get_tareas.php?atrasadas=1" | grep -o '"id":1,' 
```
Expected: la tarea 1 aparece. Consulta también su `dias_atraso` y `semaforo`: deben ser el número de días de calendario transcurridos y `vencida`, **sin que haya corrido ningún proceso programado**.

- [ ] **Step 7: Commit**

```bash
git add api/get_tareas.php
git commit -m "feat(tareas): listado con visibilidad en SQL y calculos de plazo resueltos"
```

---

## Task 9: `api/get_tarea.php`

**Files:**
- Create: `api/get_tarea.php`

- [ ] **Step 1: Escribir el endpoint**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Detalle de UNA tarea + su bitácora (JSON)
   ───────────────────────────────────────────────────────────────────────
   El historial va aquí y no en el listado: pesa y la tabla no lo usa.

   La consulta trae `asignado_soporte_de` con un LEFT JOIN porque es lo que
   tk_puede_ver() necesita para decidir si un coordinador puede abrir la
   tarea de su soporte.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_tareas();

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de tarea inválido.']); exit;
}

$st = mysqli_prepare($conn,
    "SELECT t.*,
            u.soporte_de_id AS asignado_soporte_de,
            u.estado        AS asignado_estado,
            cr.nombre       AS coordinador_ref_nombre
       FROM tareas t
       LEFT JOIN usuarios u  ON u.id  = t.asignado_id
       LEFT JOIN usuarios cr ON cr.id = t.coordinador_ref_id
      WHERE t.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'La tarea no existe.']); exit;
}
if (!tk_puede_ver($row)) {
    // 403 y no 404: mentir sobre la existencia no protege nada aquí y
    // complica depurar por qué alguien no ve lo que cree que debería.
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permiso para ver esta tarea.']); exit;
}

// ── Adjuntos ────────────────────────────────────────────────────────────
$adjuntos = [];
$ra = mysqli_query($conn,
    "SELECT id, nombre_archivo, mime, peso_bytes, drive_url, ruta_local, estado,
            error_msg, origen, entrega_nro, subido_por, subido_por_id, created_at
       FROM tareas_adjuntos WHERE tarea_id = $id
      ORDER BY entrega_nro ASC, id ASC");
while ($a = mysqli_fetch_assoc($ra)) {
    $a['id']            = (int)$a['id'];
    $a['peso_bytes']    = (int)$a['peso_bytes'];
    $a['entrega_nro']   = (int)$a['entrega_nro'];
    $a['subido_por_id'] = $a['subido_por_id'] !== null ? (int)$a['subido_por_id'] : null;
    $adjuntos[] = $a;
}

// ── Bitácora ────────────────────────────────────────────────────────────
$etiquetas = tk_acciones();
$historial = [];
$rh = mysqli_query($conn,
    "SELECT id, accion, usuario_id, usuario_nombre, usuario_rol, detalle, created_at
       FROM tareas_historial WHERE tarea_id = $id ORDER BY id ASC");
while ($h = mysqli_fetch_assoc($rh)) {
    $h['id']            = (int)$h['id'];
    $h['accion_label']  = $etiquetas[$h['accion']] ?? $h['accion'];
    $h['usuario_rol_label'] = tk_rol_label($h['usuario_rol']);
    $historial[] = $h;
}

$ahora = time();
$tarea = array_merge($row, [
    'id'                  => (int)$row['id'],
    'lote_id'             => $row['lote_id'] !== null ? (int)$row['lote_id'] : null,
    'asignado_id'         => $row['asignado_id'] !== null ? (int)$row['asignado_id'] : null,
    'asignado_soporte_de' => $row['asignado_soporte_de'] !== null ? (int)$row['asignado_soporte_de'] : null,
    'asignado_rol_label'  => tk_rol_label($row['asignado_rol']),
    'entregas_count'      => (int)$row['entregas_count'],
    'nota'                => $row['nota'] !== null ? (int)$row['nota'] : null,
    'nota_label'          => tk_nota_label($row['nota']),
    'plazo_vigente'       => tk_plazo_vigente($row),
    'tiene_prorroga'      => tk_plazo_vigente($row) !== $row['fecha_limite'],
    'atrasada'            => tk_esta_atrasada($row, $ahora),
    'dias_atraso'         => tk_dias_atraso($row, $ahora),
    'entregada_tarde'     => tk_entregada_tarde($row),
    'semaforo'            => tk_semaforo($row, $ahora),
    'es_abierta'          => tk_es_abierta($row['estado']),
    'es_terminal'         => tk_es_terminal($row['estado']),
    'adjuntos'            => $adjuntos,
    'historial'           => $historial,
]);

// Qué puede hacer ESTA sesión con ESTA tarea. Lo decide el servidor, no el
// navegador: la interfaz solo pinta lo que aquí se autoriza.
$tarea['permisos'] = [
    'editar'    => tk_puede_editar($row)['ok'],
    'entregar'  => tk_puede_entregar($row)['ok'],
    'revisar'   => tk_puede_revisar($row)['ok'],
    'prorrogar' => tk_puede_prorrogar($row)['ok'],
    'eliminar'  => is_admin(),
];

echo json_encode(['success' => true, 'data' => $tarea]);
```

- [ ] **Step 2: Verificar la sintaxis**

Run: `php -l api/get_tarea.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Verificar el detalle y los permisos por rol**

Run:
```bash
for c in admin coord coord2 soporte; do
  echo "── $c"
  curl -s -b /tmp/ck-$c.txt "http://localhost/portallyman.online/api/get_tarea.php?id=1" \
    | php -r "\$d=json_decode(file_get_contents('php://stdin'),true); echo \$d['success'] ? json_encode(\$d['data']['permisos']) : \$d['error']; echo \"\n\";"
done
```
Expected (la tarea 1 es la de `coord.test`, en `pendiente`):

| Sesión | Resultado |
|---|---|
| admin | `{"editar":true,"entregar":true,"revisar":false,"prorrogar":true,"eliminar":true}` |
| coord | `{"editar":false,"entregar":true,"revisar":false,"prorrogar":false,"eliminar":false}` |
| coord2 | `No tienes permiso para ver esta tarea.` (HTTP 403) |
| soporte | `No tienes permiso para ver esta tarea.` (HTTP 403) |

`revisar` es `false` incluso para el admin porque la tarea aún no está `entregada`.

- [ ] **Step 4: Verificar que el coordinador ve la de su soporte en solo lectura**

Run:
```bash
curl -s -b /tmp/ck-coord.txt "http://localhost/portallyman.online/api/get_tarea.php?id=2" \
  | php -r "\$d=json_decode(file_get_contents('php://stdin'),true); echo json_encode(\$d['data']['permisos']),\"\n\";"
```
Expected: `{"editar":false,"entregar":false,"revisar":false,"prorrogar":false,"eliminar":false}`. La ve, pero **`entregar` es `false`**: no entrega por su soporte.

- [ ] **Step 5: Commit**

```bash
git add api/get_tarea.php
git commit -m "feat(tareas): detalle con bitacora y permisos resueltos en servidor"
```

---

## Task 10: `api/enviar_tarea.php`

**Files:**
- Create: `api/enviar_tarea.php`

- [ ] **Step 1: Escribir el endpoint**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Enviar la entrega de una tarea (JSON)
   ───────────────────────────────────────────────────────────────────────
   Payload: { id, comentario? }
   Transición: pendiente|observada → entregada

   Guarda: hace falta al menos UN adjunto de esta ronda o un comentario.
   Un envío completamente vacío no comunica nada y sin embargo detendría
   el reloj del atraso. No se exige adjunto siempre: hay encargos
   administrativos cuya entrega es una respuesta escrita.

   Sella `plazo_al_enviar` con el plazo que rige EN ESTE INSTANTE. Sin ese
   sello, una prórroga concedida después convertiría una entrega tardía en
   puntual retroactivamente.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_tareas();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

$id         = isset($data['id']) ? (int)$data['id'] : 0;
$comentario = trim($data['comentario'] ?? '');
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de tarea inválido.']); exit;
}

$st = mysqli_prepare($conn,
    "SELECT t.*, u.soporte_de_id AS asignado_soporte_de
       FROM tareas t LEFT JOIN usuarios u ON u.id = t.asignado_id
      WHERE t.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

$permiso = tk_puede_entregar($row);
if (!$permiso['ok']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
}

// ── Guarda de entrega ───────────────────────────────────────────────────
// La ronda vigente es entregas_count + 1: tras una observación, la
// evidencia del envío anterior ya no sirve para pasar la guarda.
$ronda = (int)$row['entregas_count'] + 1;
$st = mysqli_prepare($conn,
    "SELECT COUNT(*) AS n FROM tareas_adjuntos
      WHERE tarea_id=? AND origen='asignado' AND entrega_nro=?");
mysqli_stmt_bind_param($st, 'ii', $id, $ronda);
mysqli_stmt_execute($st);
$nAdj = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['n'] ?? 0);
mysqli_stmt_close($st);

if ($nAdj === 0 && $comentario === '') {
    echo json_encode(['success' => false,
        'error' => 'Adjunta al menos un archivo de evidencia o escribe un comentario de entrega.']); exit;
}
if (mb_strlen($comentario) > 4000) {
    echo json_encode(['success' => false, 'error' => 'El comentario es demasiado largo.']); exit;
}

$plazoSellado = tk_plazo_vigente($row);
$comentSql    = $comentario !== '' ? $comentario : null;
$enNombreDe   = !empty($permiso['en_nombre_de'])
             && (int)($row['asignado_id'] ?? 0) !== (int)($_SESSION['user_id'] ?? 0);

mysqli_begin_transaction($conn);
try {
    $st = mysqli_prepare($conn,
        "UPDATE tareas
            SET estado='entregada',
                entrega_comentario=?,
                enviado_at=NOW(),
                plazo_al_enviar=?,
                entregas_count=entregas_count+1
          WHERE id=?");
    mysqli_stmt_bind_param($st, 'ssi', $comentSql, $plazoSellado, $id);
    if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
    mysqli_stmt_close($st);

    $tarde   = strtotime('now') > strtotime($plazoSellado);
    $detalle = 'Envío n.º ' . $ronda . '. Plazo vigente al enviar: ' . $plazoSellado . '.'
             . ($tarde ? ' ENTREGA FUERA DE PLAZO.' : '')
             . ' Archivos en esta ronda: ' . $nAdj . '.'
             . ($enNombreDe ? ' Enviada por el Administrador en nombre de ' . $row['asignado_nombre'] . '.' : '');
    tk_historial($conn, $id, 'enviada', $detalle);

    mysqli_commit($conn);
    echo json_encode([
        'success'         => true,
        'estado'          => 'entregada',
        'entregas_count'  => $ronda,
        'plazo_al_enviar' => $plazoSellado,
        'entregada_tarde' => $tarde,
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo enviar: ' . $e->getMessage()]);
}
```

- [ ] **Step 2: Verificar la sintaxis**

Run: `php -l api/enviar_tarea.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Verificar que un envío vacío se rechaza**

Run:
```bash
curl -s -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/enviar_tarea.php \
  -H "Content-Type: application/json" -d '{"id":1}'
```
Expected: `{"success":false,"error":"Adjunta al menos un archivo de evidencia o escribe un comentario de entrega."}`

- [ ] **Step 4: Enviar con comentario**

Run:
```bash
curl -s -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/enviar_tarea.php \
  -H "Content-Type: application/json" \
  -d '{"id":1,"comentario":"Inventario completo. 412 precintos en almacen."}'
```
Expected: `{"success":true,"estado":"entregada","entregas_count":1,...,"entregada_tarde":true}` — `true` porque en la Task 8 moviste la fecha al 20 de julio.

- [ ] **Step 5: Verificar el sello**

Run:
```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "SELECT id, estado, enviado_at, plazo_al_enviar, entregas_count FROM tareas WHERE id=1;"
```
Expected: `estado='entregada'`, `enviado_at` con la hora de Lima, `plazo_al_enviar='2026-07-20 23:59:00'`, `entregas_count=1`.

- [ ] **Step 6: Verificar que la prórroga posterior NO borra la marca de entrega tardía**

Este es el caso que justifica la columna sellada.

```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "UPDATE tareas SET fecha_limite_2='2026-09-30 23:59:00' WHERE id=1;"
curl -s -b /tmp/ck-admin.txt "http://localhost/portallyman.online/api/get_tarea.php?id=1" \
  | grep -o '"entregada_tarde":[a-z]*'
```
Expected: `"entregada_tarde":true`. Si sale `false`, el cálculo se está haciendo contra el plazo vigente en vez de contra `plazo_al_enviar`.

Deshaz la prórroga antes de seguir:

```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "UPDATE tareas SET fecha_limite_2=NULL WHERE id=1;"
```

- [ ] **Step 7: Verificar que no se entrega dos veces**

Run:
```bash
curl -s -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/enviar_tarea.php \
  -H "Content-Type: application/json" -d '{"id":1,"comentario":"otra vez"}'
```
Expected: `{"success":false,"error":"La tarea ya fue enviada y está en revisión."}`

- [ ] **Step 8: Verificar que el coordinador no entrega por su soporte**

Run:
```bash
curl -s -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/enviar_tarea.php \
  -H "Content-Type: application/json" -d '{"id":2,"comentario":"entrego yo por el"}'
```
Expected: HTTP 403 con `Solo la persona asignada puede entregar esta tarea.`

- [ ] **Step 9: Commit**

```bash
git add api/enviar_tarea.php
git commit -m "feat(tareas): envio de entrega con sello de plazo y guarda de contenido"
```

---

## Task 11: `api/revisar_tarea.php`

**Files:**
- Create: `api/revisar_tarea.php`

- [ ] **Step 1: Escribir el endpoint**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Veredicto del administrador sobre una tarea (JSON)
   ───────────────────────────────────────────────────────────────────────
   Payload: { id, veredicto: aprobada|observada|rechazada,
              nota?: 1..5, comentario?, fecha_limite_2?, prorroga_motivo? }

   Reglas:
     aprobada   → nota obligatoria
     rechazada  → nota y comentario obligatorios
     observada  → comentario obligatorio; nota y 2ª fecha opcionales

   `comentario_admin` se sobrescribe, pero el texto anterior se conserva en
   el detalle de la bitácora: si el admin observa dos veces, no se pierde
   qué dijo la primera.

   Solo Administrador: un coordinador no se califica a sí mismo, y eso es
   lo que da sentido al paso de revisión.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

$id         = isset($data['id']) ? (int)$data['id'] : 0;
$veredicto  = $data['veredicto'] ?? '';
$nota       = (isset($data['nota']) && $data['nota'] !== '' && $data['nota'] !== null)
            ? (int)$data['nota'] : null;
$comentario = trim($data['comentario'] ?? '');
$fecha2     = trim($data['fecha_limite_2'] ?? '');
$motivo     = trim($data['prorroga_motivo'] ?? '');

if ($id <= 0) {
    echo json_encode(['success'=>false,'error'=>'ID de tarea inválido.']); exit;
}
if (!in_array($veredicto, ['aprobada','observada','rechazada'], true)) {
    echo json_encode(['success'=>false,'error'=>'Veredicto inválido.']); exit;
}

$st = mysqli_prepare($conn, "SELECT * FROM tareas WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

$permiso = tk_puede_revisar($row);
if (!$permiso['ok']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
}

// ── Reglas por veredicto ────────────────────────────────────────────────
if ($nota !== null && !array_key_exists($nota, ed_escala())) {
    echo json_encode(['success'=>false,'error'=>'La nota debe estar entre 1 y 5.']); exit;
}
if ($veredicto === 'aprobada' && $nota === null) {
    echo json_encode(['success'=>false,'error'=>'Pon una nota del 1 al 5 para aprobar la tarea.']); exit;
}
if ($veredicto === 'rechazada' && ($nota === null || $comentario === '')) {
    echo json_encode(['success'=>false,
        'error'=>'Para rechazar hacen falta la nota y un comentario que explique el motivo.']); exit;
}
if ($veredicto === 'observada' && $comentario === '') {
    echo json_encode(['success'=>false,
        'error'=>'Escribe qué debe corregir antes de devolver la tarea.']); exit;
}
if (mb_strlen($comentario) > 4000) {
    echo json_encode(['success'=>false,'error'=>'El comentario es demasiado largo.']); exit;
}

// ── 2ª fecha opcional, solo al observar ─────────────────────────────────
// Prorrogar algo que se va a cerrar como aprobado o rechazado no significa
// nada: el plazo deja de existir en cuanto la tarea es terminal.
$aplicaProrroga = false;
if ($fecha2 !== '') {
    if ($veredicto !== 'observada') {
        echo json_encode(['success'=>false,
            'error'=>'Solo se puede dar una 2ª fecha cuando devuelves la tarea con observaciones.']); exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $fecha2)) {
        echo json_encode(['success'=>false,'error'=>'2ª fecha inválida.']); exit;
    }
    if (strlen($fecha2) === 16) $fecha2 .= ':00';
    if (strtotime($fecha2) <= strtotime($row['fecha_limite'])) {
        echo json_encode(['success'=>false,
            'error'=>'La 2ª fecha debe ser posterior a la fecha límite original.']); exit;
    }
    if ($motivo === '') {
        echo json_encode(['success'=>false,
            'error'=>'Indica el motivo de la prórroga. Una prórroga sin motivo es indistinguible de un error de digitación.']); exit;
    }
    $aplicaProrroga = true;
}

$comentSql  = $comentario !== '' ? $comentario : null;
$revisadoP  = $_SESSION['user_name'] ?? 'Administrador';
$revisadoId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

mysqli_begin_transaction($conn);
try {
    if ($aplicaProrroga) {
        $st = mysqli_prepare($conn,
            "UPDATE tareas
                SET estado=?, nota=?, comentario_admin=?,
                    revisado_por=?, revisado_por_id=?, revisado_at=NOW(),
                    fecha_limite_2=?, prorroga_motivo=?,
                    prorroga_por=?, prorroga_por_id=?, prorroga_at=NOW()
              WHERE id=?");
        // 10 variables: estado(s) nota(i) comentario(s) revisado_por(s)
        // revisado_por_id(i) fecha2(s) motivo(s) prorroga_por(s)
        // prorroga_por_id(i) id(i).
        mysqli_stmt_bind_param($st, 'sississsii',
            $veredicto, $nota, $comentSql,
            $revisadoP, $revisadoId,
            $fecha2, $motivo, $revisadoP, $revisadoId, $id);
    } else {
        $st = mysqli_prepare($conn,
            "UPDATE tareas
                SET estado=?, nota=?, comentario_admin=?,
                    revisado_por=?, revisado_por_id=?, revisado_at=NOW()
              WHERE id=?");
        // 6 variables: estado(s) nota(i) comentario(s) revisado_por(s)
        // revisado_por_id(i) id(i).
        mysqli_stmt_bind_param($st, 'sissii',
            $veredicto, $nota, $comentSql, $revisadoP, $revisadoId, $id);
    }
    if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
    mysqli_stmt_close($st);

    // Aquí es donde sobrevive lo que la fila acaba de sobrescribir.
    $detalle = 'Nota: ' . ($nota !== null ? $nota . ' · ' . tk_nota_label($nota) : 'sin nota') . '.';
    if ($comentario !== '') $detalle .= ' Comentario: ' . $comentario;
    if (!empty($row['comentario_admin'])) {
        $detalle .= ' | Comentario anterior (sustituido): ' . $row['comentario_admin'];
    }
    tk_historial($conn, $id, $veredicto, $detalle);

    if ($aplicaProrroga) {
        tk_historial($conn, $id, 'prorroga',
            'Nueva fecha de entrega: ' . $fecha2 . '. Motivo: ' . $motivo);
    }

    mysqli_commit($conn);
    echo json_encode([
        'success'   => true,
        'estado'    => $veredicto,
        'nota'      => $nota,
        'nota_label'=> tk_nota_label($nota),
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo revisar: ' . $e->getMessage()]);
}
```

**Corregido tras verificación (2026-08-02):** la versión original de este
documento traía `'sississsiii'` (11 tipos) para la rama con prórroga —10
variables— y `'sissiii'` (7 tipos) para la rama sin prórroga —6 variables—.
Ambas tenían un carácter de sobra y ambas hacían fallar `mysqli_stmt_bind_param`
con `ArgumentCountError` en runtime; se confirmó al ejecutar el Step 4. Los
tipos correctos, ya reflejados arriba, son `'sississsii'` (10 chars) y
`'sissii'` (6 chars).

- [ ] **Step 2: Verificar la sintaxis**

Run: `php -l api/revisar_tarea.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Verificar las guardas del veredicto**

Run:
```bash
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/revisar_tarea.php \
  -H "Content-Type: application/json" -d '{"id":1,"veredicto":"aprobada"}'
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/revisar_tarea.php \
  -H "Content-Type: application/json" -d '{"id":1,"veredicto":"observada","nota":3}'
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/revisar_tarea.php \
  -H "Content-Type: application/json" -d '{"id":1,"veredicto":"aprobada","nota":9}'
```
Expected, en orden:
1. `Pon una nota del 1 al 5 para aprobar la tarea.`
2. `Escribe qué debe corregir antes de devolver la tarea.`
3. `La nota debe estar entre 1 y 5.`

- [ ] **Step 4: Observar con 2ª fecha**

Run:
```bash
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/revisar_tarea.php \
  -H "Content-Type: application/json" \
  -d '{"id":1,"veredicto":"observada","nota":3,"comentario":"Falta la foto del anaquel B.","fecha_limite_2":"2026-08-25 23:59","prorroga_motivo":"Se amplia el plazo para completar la evidencia."}'
```
Expected: `{"success":true,"estado":"observada","nota":3,"nota_label":"Aceptable"}`

- [ ] **Step 5: Verificar que la prórroga saca del atraso en el acto**

Run:
```bash
curl -s -b /tmp/ck-admin.txt "http://localhost/portallyman.online/api/get_tarea.php?id=1" \
  | grep -o '"atrasada":[a-z]*\|"plazo_vigente":"[^"]*"\|"tiene_prorroga":[a-z]*'
```
Expected: `"plazo_vigente":"2026-08-25 23:59:00"`, `"tiene_prorroga":true`, `"atrasada":false` — la tarea vencía el 20 de julio y ha dejado de estar atrasada sin que se haya tocado ninguna otra fila.

- [ ] **Step 6: Verificar que una 2ª fecha anterior a la 1ª se rechaza en el servidor**

Run:
```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "UPDATE tareas SET estado='entregada' WHERE id=1;"
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/revisar_tarea.php \
  -H "Content-Type: application/json" \
  -d '{"id":1,"veredicto":"observada","comentario":"x","fecha_limite_2":"2026-07-01 10:00","prorroga_motivo":"m"}'
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/revisar_tarea.php \
  -H "Content-Type: application/json" \
  -d '{"id":1,"veredicto":"observada","comentario":"x","fecha_limite_2":"2026-09-01 10:00"}'
```
Expected:
1. `La 2ª fecha debe ser posterior a la fecha límite original.`
2. `Indica el motivo de la prórroga. …`

- [ ] **Step 7: Verificar que el comentario anterior sobrevive**

Run:
```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "SELECT accion, LEFT(detalle,160) AS detalle FROM tareas_historial WHERE tarea_id=1 ORDER BY id;"
```
Expected: entre los eventos hay uno `observada` cuyo `detalle` incluye `Comentario anterior (sustituido): Falta la foto del anaquel B.`

- [ ] **Step 8: Verificar que un coordinador no puede revisar**

Run:
```bash
curl -s -i -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/revisar_tarea.php \
  -H "Content-Type: application/json" -d '{"id":1,"veredicto":"aprobada","nota":5}' | head -1
```
Expected: `HTTP/1.1 403 Forbidden`

- [ ] **Step 9: Commit**

```bash
git add api/revisar_tarea.php
git commit -m "feat(tareas): veredicto del administrador con nota y devolucion"
```

---

## Task 12: `api/prorrogar_tarea.php`

**Files:**
- Create: `api/prorrogar_tarea.php`

- [ ] **Step 1: Escribir el endpoint**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Conceder o retirar la 2ª fecha de una tarea (JSON)
   ───────────────────────────────────────────────────────────────────────
   Payload: { id, fecha_limite_2: 'YYYY-MM-DD HH:MM' | null, motivo? }
     · con fecha → concede la prórroga (motivo obligatorio)
     · con null  → la retira; la tarea vuelve a medirse contra la fecha 1

   Solo Administrador y solo sobre tareas abiertas. Prorrogar algo ya
   entregado no significa nada, y prorrogar algo terminal, menos.

   Esta es la vía «suelta»; la otra es dentro de revisar_tarea.php cuando
   el veredicto es «observada».
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

$id     = isset($data['id']) ? (int)$data['id'] : 0;
$fecha2 = isset($data['fecha_limite_2']) ? trim((string)$data['fecha_limite_2']) : '';
$motivo = trim($data['motivo'] ?? '');
$retira = ($fecha2 === '' || $fecha2 === 'null');

if ($id <= 0) {
    echo json_encode(['success'=>false,'error'=>'ID de tarea inválido.']); exit;
}

$st = mysqli_prepare($conn, "SELECT * FROM tareas WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

$permiso = tk_puede_prorrogar($row);
if (!$permiso['ok']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
}

$quien   = $_SESSION['user_name'] ?? 'Administrador';
$quienId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

mysqli_begin_transaction($conn);
try {
    if ($retira) {
        if ($row['fecha_limite_2'] === null) {
            mysqli_rollback($conn);
            echo json_encode(['success'=>false,'error'=>'Esta tarea no tiene una 2ª fecha que retirar.']); exit;
        }
        $st = mysqli_prepare($conn,
            "UPDATE tareas
                SET fecha_limite_2=NULL, prorroga_motivo=NULL,
                    prorroga_por=?, prorroga_por_id=?, prorroga_at=NOW()
              WHERE id=?");
        mysqli_stmt_bind_param($st, 'sii', $quien, $quienId, $id);
        if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
        mysqli_stmt_close($st);

        tk_historial($conn, $id, 'prorroga_retirada',
            'Se retira la 2ª fecha (' . $row['fecha_limite_2'] . '). '
          . 'La tarea vuelve a medirse contra ' . $row['fecha_limite'] . '.');

        mysqli_commit($conn);
        echo json_encode(['success'=>true,'fecha_limite_2'=>null,
                          'plazo_vigente'=>$row['fecha_limite']]);
        exit;
    }

    // ── Conceder ────────────────────────────────────────────────────────
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $fecha2)) {
        mysqli_rollback($conn);
        echo json_encode(['success'=>false,'error'=>'2ª fecha inválida.']); exit;
    }
    if (strlen($fecha2) === 16) $fecha2 .= ':00';
    if (strtotime($fecha2) <= strtotime($row['fecha_limite'])) {
        mysqli_rollback($conn);
        echo json_encode(['success'=>false,
            'error'=>'La 2ª fecha debe ser posterior a la fecha límite original.']); exit;
    }
    if ($motivo === '') {
        mysqli_rollback($conn);
        echo json_encode(['success'=>false,
            'error'=>'Indica el motivo de la prórroga. Una prórroga sin motivo es indistinguible de un error de digitación.']); exit;
    }
    if (mb_strlen($motivo) > 255) $motivo = mb_substr($motivo, 0, 255);

    $st = mysqli_prepare($conn,
        "UPDATE tareas
            SET fecha_limite_2=?, prorroga_motivo=?,
                prorroga_por=?, prorroga_por_id=?, prorroga_at=NOW()
          WHERE id=?");
    mysqli_stmt_bind_param($st, 'sssii', $fecha2, $motivo, $quien, $quienId, $id);
    if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
    mysqli_stmt_close($st);

    $anterior = $row['fecha_limite_2'] !== null
              ? ' Sustituye a la 2ª fecha anterior (' . $row['fecha_limite_2'] . ').' : '';
    tk_historial($conn, $id, 'prorroga',
        'Nueva fecha de entrega: ' . $fecha2 . '. Motivo: ' . $motivo . '.' . $anterior);

    mysqli_commit($conn);
    echo json_encode(['success'=>true,'fecha_limite_2'=>$fecha2,'plazo_vigente'=>$fecha2]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo prorrogar: ' . $e->getMessage()]);
}
```

- [ ] **Step 2: Verificar la sintaxis**

Run: `php -l api/prorrogar_tarea.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Conceder, verificar, retirar y verificar**

La tarea 2 (la de `soporte.test`) sigue en `pendiente`. Pon su fecha en el pasado para ver el efecto sobre el atraso:

```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "UPDATE tareas SET fecha_limite='2026-07-15 23:59:00' WHERE id=2;"

# atrasada antes de la prórroga
curl -s -b /tmp/ck-admin.txt "http://localhost/portallyman.online/api/get_tarea.php?id=2" | grep -o '"atrasada":[a-z]*'

# conceder
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/prorrogar_tarea.php \
  -H "Content-Type: application/json" \
  -d '{"id":2,"fecha_limite_2":"2026-08-30 23:59","motivo":"Nave en muelle: sin disponibilidad esta semana."}'

# ya no atrasada
curl -s -b /tmp/ck-admin.txt "http://localhost/portallyman.online/api/get_tarea.php?id=2" | grep -o '"atrasada":[a-z]*'

# retirar
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/prorrogar_tarea.php \
  -H "Content-Type: application/json" -d '{"id":2,"fecha_limite_2":null}'

# vuelve a estar atrasada
curl -s -b /tmp/ck-admin.txt "http://localhost/portallyman.online/api/get_tarea.php?id=2" | grep -o '"atrasada":[a-z]*'
```
Expected, en orden: `true` → `{"success":true,...}` → `false` → `{"success":true,"fecha_limite_2":null,...}` → `true`.

- [ ] **Step 4: Verificar que no se prorroga una tarea entregada**

Run:
```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "UPDATE tareas SET estado='entregada' WHERE id=2;"
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/prorrogar_tarea.php \
  -H "Content-Type: application/json" -d '{"id":2,"fecha_limite_2":"2026-09-30 23:59","motivo":"x"}'
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "UPDATE tareas SET estado='pendiente' WHERE id=2;"
```
Expected: `Solo se puede prorrogar una tarea pendiente u observada.` con HTTP 403.

- [ ] **Step 5: Commit**

```bash
git add api/prorrogar_tarea.php
git commit -m "feat(tareas): 2a fecha con motivo obligatorio y retirada de prorroga"
```

---

## Task 13: Adjuntos

**Files:**
- Create: `api/upload_tarea_file.php`
- Create: `api/delete_tarea_adjunto.php`

- [ ] **Step 1: Escribir `api/upload_tarea_file.php`**

Mismo esqueleto que [upload_capacitacion_file.php](../../../api/upload_capacitacion_file.php), con dos diferencias: `origen` decide qué permiso se comprueba, y `entrega_nro` etiqueta la ronda.

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Subida de UN adjunto de una tarea
   ───────────────────────────────────────────────────────────────────────
   multipart/form-data { id, origen: admin|asignado, file }

   `origen` no se deduce del rol: el Administrador puede colgar material de
   referencia (admin) Y subir evidencia en nombre del asignado (asignado).
   Cada uno se autoriza con la regla que le toca.

   `entrega_nro` = entregas_count + 1. Sin él, tras una observación el
   administrador vería un montón de archivos sin saber cuáles responden a
   lo que observó.

   Si Drive falla, se guarda copia local y el adjunto queda 'pendiente'.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
require_once('../includes/drive_uploader.php');   // sg_drive_subir, sg_guardar_local
api_require_tareas();

header('Content-Type: application/json');

$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$origen = $_POST['origen'] ?? 'asignado';
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de tarea inválido.']); exit;
}
if (!in_array($origen, ['admin', 'asignado'], true)) $origen = 'asignado';

// ── Autorización ────────────────────────────────────────────────────────
$st = mysqli_prepare($conn,
    "SELECT t.*, u.soporte_de_id AS asignado_soporte_de
       FROM tareas t LEFT JOIN usuarios u ON u.id = t.asignado_id
      WHERE t.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$tarea = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

$permiso = ($origen === 'admin') ? tk_puede_editar($tarea) : tk_puede_entregar($tarea);
if (!$permiso['ok']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
}

// ── Tope de adjuntos ────────────────────────────────────────────────────
$st = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM tareas_adjuntos WHERE tarea_id=?");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$n = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['n'] ?? 0);
mysqli_stmt_close($st);
if ($n >= tk_max_adjuntos()) {
    echo json_encode(['success' => false,
        'error' => 'Ya hay ' . tk_max_adjuntos() . ' archivos en esta tarea, que es el máximo.']); exit;
}

// ── Validación del archivo ──────────────────────────────────────────────
if (empty($_FILES['file']) || !isset($_FILES['file']['error'])) {
    echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo.']); exit;
}
$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE  => 'El archivo excede el tamaño permitido por el servidor.',
        UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño permitido.',
        UPLOAD_ERR_PARTIAL   => 'La subida quedó incompleta. Reintenta.',
        UPLOAD_ERR_NO_FILE   => 'No se recibió ningún archivo.',
    ];
    echo json_encode(['success' => false, 'error' => $codes[$file['error']] ?? 'Error al subir el archivo.']); exit;
}
if ($file['size'] <= 0)              { echo json_encode(['success'=>false,'error'=>'El archivo está vacío.']); exit; }
if ($file['size'] > tk_max_bytes())  { echo json_encode(['success'=>false,'error'=>'El archivo supera los 4 MB.']); exit; }
if (!is_uploaded_file($file['tmp_name'])) { echo json_encode(['success'=>false,'error'=>'Archivo no válido.']); exit; }

// Extensión declarada + MIME real. Los dos tienen que cuadrar.
$tipos = sg_tipos_permitidos();
$ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!isset($tipos[$ext])) {
    echo json_encode(['success' => false,
        'error' => 'El tipo de archivo ".' . htmlspecialchars($ext) . '" no está permitido.']); exit;
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $tipos[$ext], true)) {
    echo json_encode(['success' => false, 'error' => 'El contenido del archivo no coincide con su extensión.']); exit;
}

// ── Nombre final: el original, saneado ──────────────────────────────────
$base = pathinfo($file['name'], PATHINFO_FILENAME);
$base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);
$base = trim(preg_replace('/_+/', '_', $base), '_.');
if ($base === '') $base = 'evidencia';
if (mb_strlen($base) > 90) $base = mb_substr($base, 0, 90);
$nombre = $base . '.' . $ext;

$carpeta = tk_carpeta_drive();
$res     = sg_drive_subir($carpeta, $nombre, $mime, $file['tmp_name']);

if (!empty($res['ok'])) {
    $nombreFinal = $res['nombre'] ?? $nombre;
    $fileId = $res['fileId'] ?? null;
    $url    = $res['url'] ?? null;
    $local  = null;  $estado = 'subido';  $errMsg = null;  $aviso = null;
} else {
    $nombreFinal = $nombre;
    $fileId = null;  $url = null;
    $local  = sg_guardar_local($carpeta, $nombre, $file['tmp_name']);
    $estado = $local ? 'pendiente' : 'error';
    $errMsg = mb_substr($res['error'] ?? 'Fallo desconocido de Drive.', 0, 255);
    $aviso  = 'No se pudo subir a Drive: ' . mb_substr($res['error'] ?? '', 0, 180)
            . ($local ? ' (guardado en el servidor, se subirá luego).' : '.');
}

$peso     = (int)$file['size'];
$ronda    = ($origen === 'asignado') ? (int)$tarea['entregas_count'] + 1 : 1;
$subidoP  = $_SESSION['user_name'] ?? '';
$subidoId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

mysqli_begin_transaction($conn);
try {
    $ins = mysqli_prepare($conn,
        "INSERT INTO tareas_adjuntos
            (tarea_id, nombre_archivo, mime, peso_bytes, drive_file_id, drive_url,
             ruta_local, estado, error_msg, origen, entrega_nro, subido_por, subido_por_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($ins, 'ississssssisi',
        $id, $nombreFinal, $mime, $peso, $fileId, $url,
        $local, $estado, $errMsg, $origen, $ronda, $subidoP, $subidoId);
    if (!mysqli_stmt_execute($ins)) throw new Exception(mysqli_stmt_error($ins));
    $adjId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($ins);

    tk_historial($conn, $id, 'adjunto',
        ($origen === 'admin' ? 'Material de referencia: ' : 'Evidencia (ronda ' . $ronda . '): ')
      . $nombreFinal . ($estado !== 'subido' ? ' [' . $estado . ']' : ''));

    mysqli_commit($conn);
    echo json_encode([
        'success' => true,
        'aviso'   => $aviso,
        'adjunto' => [
            'id'             => $adjId,
            'nombre_archivo' => $nombreFinal,
            'mime'           => $mime,
            'peso_bytes'     => $peso,
            'drive_file_id'  => $fileId,
            'drive_url'      => $url,
            'ruta_local'     => $local,
            'estado'         => $estado,
            'error_msg'      => $errMsg,
            'origen'         => $origen,
            'entrega_nro'    => $ronda,
            'subido_por'     => $subidoP,
            'subido_por_id'  => $subidoId,
        ],
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo registrar el adjunto: ' . $e->getMessage()]);
}
```

- [ ] **Step 2: Escribir `api/delete_tarea_adjunto.php`**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Baja de un adjunto de una tarea (JSON)
   ───────────────────────────────────────────────────────────────────────
   Payload: { id }   ← id del ADJUNTO, no de la tarea

   Puede borrarlo quien lo subió mientras la tarea siga abierta, y el
   Administrador siempre (limpieza de registros erróneos ya cerrados).

   Solo se borra la fila: el archivo en Drive se conserva. Borrarlo desde
   aquí exigiría permisos de escritura en Drive que el Apps Script no
   expone, y un adjunto retirado por error sería irrecuperable.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_tareas();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$adjId = isset($data['id']) ? (int)$data['id'] : 0;
if ($adjId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de adjunto inválido.']); exit;
}

$st = mysqli_prepare($conn,
    "SELECT a.id, a.tarea_id, a.nombre_archivo, a.subido_por_id, a.origen,
            t.estado, t.asignado_id, u.soporte_de_id AS asignado_soporte_de
       FROM tareas_adjuntos a
       JOIN tareas t     ON t.id = a.tarea_id
       LEFT JOIN usuarios u ON u.id = t.asignado_id
      WHERE a.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $adjId);
mysqli_stmt_execute($st);
$a = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if (!$a) {
    echo json_encode(['success' => false, 'error' => 'El adjunto no existe.']); exit;
}
if (!tk_puede_ver($a)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permiso sobre esta tarea.']); exit;
}

$uid    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$esMio  = ($a['subido_por_id'] !== null && (int)$a['subido_por_id'] === $uid);
$puede  = is_admin() || ($esMio && tk_es_abierta($a['estado']));
if (!$puede) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => tk_es_abierta($a['estado'])
        ? 'Solo puedes eliminar los archivos que tú subiste.'
        : 'La tarea ya no admite cambios en sus archivos.']); exit;
}

$tareaId = (int)$a['tarea_id'];

mysqli_begin_transaction($conn);
try {
    $st = mysqli_prepare($conn, "DELETE FROM tareas_adjuntos WHERE id=?");
    mysqli_stmt_bind_param($st, 'i', $adjId);
    if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
    mysqli_stmt_close($st);

    tk_historial($conn, $tareaId, 'adjunto_borrado', 'Archivo retirado: ' . $a['nombre_archivo']);

    mysqli_commit($conn);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo eliminar: ' . $e->getMessage()]);
}
```

- [ ] **Step 3: Verificar la sintaxis**

Run: `php -l api/upload_tarea_file.php && php -l api/delete_tarea_adjunto.php`
Expected: dos veces `No syntax errors detected`

- [ ] **Step 4: Subir evidencia**

Crea un archivo de prueba y súbelo como el coordinador. La tarea 1 está `observada`, así que admite entrega.

```bash
echo "conteo de precintos" > /tmp/evidencia.txt
curl -s -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/upload_tarea_file.php \
  -F "id=1" -F "origen=asignado" -F "file=@/tmp/evidencia.txt"
```
Expected: `{"success":true,...,"entrega_nro":2,...}` — ronda **2**, porque la tarea ya tiene un envío. Si Drive no está configurado en tu entorno, `estado` será `pendiente` con `ruta_local` poblada, y eso también es correcto: el archivo no se pierde.

- [ ] **Step 5: Verificar que se rechaza un tipo no permitido**

Run:
```bash
echo "MZ" > /tmp/malo.exe
curl -s -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/upload_tarea_file.php \
  -F "id=1" -F "origen=asignado" -F "file=@/tmp/malo.exe"
```
Expected: `El tipo de archivo ".exe" no está permitido.`

- [ ] **Step 6: Verificar que un coordinador no puede colgar material de referencia**

Run:
```bash
curl -s -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/upload_tarea_file.php \
  -F "id=1" -F "origen=admin" -F "file=@/tmp/evidencia.txt"
```
Expected: HTTP 403 con `Solo el Administrador puede modificar una tarea.`

- [ ] **Step 7: Verificar el borrado**

Run (sustituye `1` por el `id` del adjunto que devolvió el Step 4):
```bash
curl -s -b /tmp/ck-coord2.txt -X POST http://localhost/portallyman.online/api/delete_tarea_adjunto.php \
  -H "Content-Type: application/json" -d '{"id":1}'
```
Expected: HTTP 403 con `No tienes permiso sobre esta tarea.` — `coord2.test` ni siquiera ve la tarea.

- [ ] **Step 8: Commit**

```bash
git add api/upload_tarea_file.php api/delete_tarea_adjunto.php
git commit -m "feat(tareas): adjuntos a Drive con origen y ronda de entrega"
```

---

## Task 14: `api/delete_tarea.php`

**Files:**
- Create: `api/delete_tarea.php`

- [ ] **Step 1: Escribir el endpoint**

```php
<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Borrado de una tarea (JSON)
   ───────────────────────────────────────────────────────────────────────
   Payload: { id }

   Solo Administrador, en cualquier estado: es la vía para limpiar
   registros creados por error, incluidos los ya revisados.

   Los adjuntos y la bitácora se van en cascada (ON DELETE CASCADE, sql/029).
   Los archivos en Drive se conservan; ver delete_tarea_adjunto.php.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de tarea inválido.']); exit;
}

$st = mysqli_prepare($conn, "SELECT id, titulo, asignado_nombre FROM tareas WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'La tarea no existe.']); exit;
}

$st = mysqli_prepare($conn, "DELETE FROM tareas WHERE id=?");
mysqli_stmt_bind_param($st, 'i', $id);
$ok  = mysqli_stmt_execute($st);
$err = mysqli_stmt_error($st);
mysqli_stmt_close($st);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'No se pudo eliminar: ' . $err]); exit;
}
echo json_encode(['success' => true]);
```

- [ ] **Step 2: Verificar la sintaxis**

Run: `php -l api/delete_tarea.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Verificar el borrado en cascada**

Crea una tarea desechable y bórrala, capturando su id en una variable de shell para no tener que copiarlo a mano.

```bash
DES=$(curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/save_tarea.php \
  -H "Content-Type: application/json" \
  -d '{"titulo":"Desechable","fecha_limite":"2026-12-01 23:59","destinatarios":[7]}' \
  | php -r "echo json_decode(file_get_contents('php://stdin'),true)['ids'][0];")
echo "id creado: $DES"

c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system \
  -e "SELECT COUNT(*) AS hist_antes FROM tareas_historial WHERE tarea_id=$DES;"

curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/delete_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$DES}"

c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system \
  -e "SELECT (SELECT COUNT(*) FROM tareas WHERE id=$DES) AS t,
             (SELECT COUNT(*) FROM tareas_historial WHERE tarea_id=$DES) AS hist,
             (SELECT COUNT(*) FROM tareas_adjuntos WHERE tarea_id=$DES) AS adj;"
```
Expected: `hist_antes` ≥ 1 (el evento `creada`); después de borrar, los tres contadores en `0`.

- [ ] **Step 4: Verificar que un coordinador no puede borrar**

Run:
```bash
curl -s -i -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/delete_tarea.php \
  -H "Content-Type: application/json" -d '{"id":1}' | head -1
```
Expected: `HTTP/1.1 403 Forbidden`

- [ ] **Step 5: Commit**

```bash
git add api/delete_tarea.php
git commit -m "feat(tareas): borrado en cascada solo para administrador"
```

---

# FASE 3 · Interfaz

`pages/tareas.php` es **un solo archivo** que se bifurca por rol. Dos páginas duplicarían el modal de detalle, el catálogo de estados y las exportaciones.

Se construye en cinco tareas y en cada una la página queda funcionando: Task 15 deja el tablero leyéndose, Task 16 permite crear, Task 17 permite entregar, Task 18 permite revisar y Task 19 añade las exportaciones.

## Task 15: La página y el tablero

**Files:**
- Create: `pages/tareas.php`

**Corregido tras verificación (2026-08-02):** el `<body>` original de este
documento usaba `<div class="overlay"></div><main class="main">…</main>`, que
no es el patrón real del sitio. Todas las demás páginas (`sugerencias.php`,
`operaciones_naves.php`, `tallyman.php`…) usan
`overlay#overlay` → `.shell` → `sidebar` + `.main-area` → `header` + `main.content`;
sin `.main-area` el sidebar fijo se queda sin el margen que le compensa, y el
layout se rompe. El snippet de abajo ya trae el markup corregido.

- [ ] **Step 1: Crear el archivo con el andamiaje y el CSS**

```php
<?php
require_once('../includes/auth.php');
require_once('../includes/db.php');
require_once('../includes/tareas_catalogo.php');
require_tareas();

// Catálogos → JS (fuente única de verdad; ver includes/tareas_catalogo.php)
$JS_ESTADOS     = tk_estados();
$JS_PRIORIDADES = tk_prioridades();
$JS_SEMAFOROS   = tk_semaforos();
$JS_ESCALA      = ed_escala();

$ES_ADMIN  = is_admin();
$ES_SUPER  = ($_SESSION['user_rol'] ?? '') === 'Supervisor';
$USER_ID   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$USER_NAME = $_SESSION['user_name'] ?? '';
$USER_ROL  = $_SESSION['user_rol']  ?? '';

// Vista de tablero para quien supervisa; vista «Mis tareas» para quien ejecuta.
$ES_TABLERO = $ES_ADMIN || $ES_SUPER;

// ¿Este coordinador tiene soportes a cargo? Decide si se pinta el segmento
// «Mi soporte». Se resuelve aquí y no en JS para no pedir un endpoint más.
$MIS_SOPORTES = [];
if ($USER_ROL === 'Coordinador' && $USER_ID > 0) {
    $st = mysqli_prepare($conn,
        "SELECT id, nombre FROM usuarios WHERE rol='Soporte' AND soporte_de_id=? ORDER BY nombre");
    mysqli_stmt_bind_param($st, 'i', $USER_ID);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    while ($s = mysqli_fetch_assoc($rs)) $MIS_SOPORTES[] = ['id' => (int)$s['id'], 'nombre' => $s['nombre']];
    mysqli_stmt_close($st);
}

// Logo embebido para el PDF.
$logo_path = __DIR__ . '/../logo/logo.png';
$LOGO_B64  = is_file($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tareas · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <link rel="stylesheet" href="../css/estiba.css">

  <style>
    /* ════════════════ TAREAS (prefijo .tk-*) ════════════════ */
    .tk-wrap {
      --co-navy:#005c3d; --co-navy-700:#00875A; --co-red:#dc2626;
      --co-deck:#f5f8f7; --co-line:rgba(0,135,90,.18); --co-line-bold:rgba(0,135,90,.3);
      --co-ink:#111827; --co-mute:#4b5563; --co-faint:#9ca3af;
      --ok:#047857; --ok-bg:rgba(4,120,87,.10);
      --wn:#d97706; --wn-bg:rgba(217,119,6,.10);
      --er:#dc2626; --er-bg:rgba(220,38,38,.10);
      --sl:#475569; --sl-bg:rgba(100,116,139,.12);
      --bl:#2563eb; --bl-bg:rgba(37,99,235,.10);
      --vi:#7c3aed; --vi-bg:rgba(124,58,237,.10);
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif; color:var(--co-ink);
    }
    .tk-wrap *, .tk-wrap *::before, .tk-wrap *::after { box-sizing:border-box; }

    .tk-hero { background:linear-gradient(135deg,#005c3d 0%,#00875A 100%); color:#fff;
      border-radius:20px; padding:22px 28px; display:flex; align-items:center;
      justify-content:space-between; gap:18px; box-shadow:0 8px 32px rgba(0,135,90,.08); }
    .tk-hero h1 { margin:6px 0 4px; font-size:22px; font-weight:700; letter-spacing:-.01em; }
    .tk-hero p  { margin:0; color:rgba(255,255,255,.85); font-size:13px; max-width:640px; }
    .tk-hero .tag { display:inline-flex; align-items:center; padding:5px 11px; border-radius:999px;
      background:rgba(255,255,255,.15); font-size:11px; font-weight:700; letter-spacing:.06em; }

    .tk-btn { display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:10px;
      border:1px solid rgba(0,135,90,.3); background:#fff; cursor:pointer; text-decoration:none;
      font-family:inherit; font-size:13px; font-weight:600; color:#00875A; transition:all .15s; }
    .tk-btn svg { width:15px; height:15px; }
    .tk-btn:hover { background:rgba(0,135,90,.05); }
    .tk-btn:disabled { opacity:.5; cursor:not-allowed; }
    .tk-btn.ghost-light { background:rgba(255,255,255,.15); color:#fff; border-color:rgba(255,255,255,.25); }
    .tk-btn.primary { background:linear-gradient(135deg,#00875A 0%,#005c3d 100%); color:#fff;
      border:none; font-weight:700; box-shadow:0 4px 18px rgba(0,135,90,.2); }
    .tk-btn.primary:hover { filter:brightness(1.08); transform:translateY(-1px); }
    .tk-btn.danger { color:var(--er); border-color:rgba(220,38,38,.3); }
    .tk-btn.danger:hover { background:var(--er-bg); }

    /* KPIs */
    .tk-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; }
    .tk-kpi { background:#fff; border:1px solid var(--co-line); border-radius:14px; padding:14px 16px; }
    .tk-kpi .lbl { font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase;
      color:var(--co-faint); }
    .tk-kpi .val { font-size:26px; font-weight:800; line-height:1.1; margin-top:4px; }
    .tk-kpi.is-alert .val { color:var(--er); }
    .tk-kpi.is-warn  .val { color:var(--wn); }
    .tk-kpi.is-ok    .val { color:var(--ok); }
    .tk-kpi.is-info  .val { color:var(--bl); }

    /* Toolbar */
    .tk-card { background:#fff; border:1px solid var(--co-line); border-radius:16px; }
    .tk-tools { display:flex; flex-wrap:wrap; align-items:center; gap:10px; padding:14px 16px;
      border-bottom:1px solid var(--co-line); }
    .tk-search { flex:1 1 220px; min-width:180px; padding:9px 12px; border-radius:10px;
      border:1px solid var(--co-line-bold); font-family:inherit; font-size:13px; }
    .tk-sel { padding:9px 12px; border-radius:10px; border:1px solid var(--co-line-bold);
      font-family:inherit; font-size:13px; background:#fff; }
    .tk-chips { display:flex; flex-wrap:wrap; gap:6px; }
    .tk-chip { padding:6px 12px; border-radius:999px; border:1px solid var(--co-line-bold);
      background:#fff; cursor:pointer; font-family:inherit; font-size:12px; font-weight:600;
      color:var(--co-mute); }
    .tk-chip.on { background:var(--co-navy-700); color:#fff; border-color:var(--co-navy-700); }
    .tk-chip.is-late { color:var(--er); border-color:rgba(220,38,38,.35); }
    .tk-chip.is-late.on { background:var(--er); color:#fff; border-color:var(--er); }

    /* Tabla */
    .tk-tablewrap { overflow-x:auto; }
    .tk-table { width:100%; border-collapse:collapse; font-size:13px; }
    .tk-table th { text-align:left; padding:11px 14px; font-size:11px; font-weight:700;
      letter-spacing:.05em; text-transform:uppercase; color:var(--co-faint);
      border-bottom:1px solid var(--co-line); white-space:nowrap; }
    .tk-table td { padding:12px 14px; border-bottom:1px solid rgba(0,0,0,.045); vertical-align:top; }
    .tk-table tr:hover td { background:rgba(0,135,90,.025); }
    .tk-table tr.is-late td { background:rgba(220,38,38,.035); }
    .tk-table tr.is-late:hover td { background:rgba(220,38,38,.06); }
    .tk-tt { font-weight:600; cursor:pointer; }
    .tk-tt:hover { color:var(--co-navy-700); text-decoration:underline; }
    .tk-sub { font-size:11px; color:var(--co-faint); margin-top:2px; }

    .tk-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 9px; border-radius:999px;
      font-size:11px; font-weight:700; white-space:nowrap; }
    .tk-badge .dot { width:6px; height:6px; border-radius:50%; background:currentColor; }
    .tk-rolchip { display:inline-block; padding:2px 7px; border-radius:6px; font-size:10px;
      font-weight:700; background:var(--sl-bg); color:var(--sl); }
    .tk-rolchip.is-sop { background:var(--vi-bg); color:var(--vi); }
    .tk-late { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:6px;
      font-size:10px; font-weight:800; letter-spacing:.04em; background:var(--er-bg); color:var(--er); }
    .tk-tacha { text-decoration:line-through; color:var(--co-faint); font-size:11px; }
    .tk-stars { color:var(--wn); letter-spacing:1px; }

    /* Lista «Mis tareas» */
    .tk-list { display:flex; flex-direction:column; gap:10px; }
    .tk-item { background:#fff; border:1px solid var(--co-line); border-left-width:4px;
      border-radius:14px; padding:14px 16px; display:flex; gap:14px; align-items:flex-start;
      cursor:pointer; transition:all .15s; }
    .tk-item:hover { box-shadow:0 4px 18px rgba(0,0,0,.06); transform:translateY(-1px); }
    .tk-item.sem-vencida  { border-left-color:var(--er); }
    .tk-item.sem-hoy      { border-left-color:var(--wn); }
    .tk-item.sem-proxima  { border-left-color:#b45309; }
    .tk-item.sem-a_tiempo { border-left-color:var(--co-line-bold); }
    .tk-item .grow { flex:1; min-width:0; }
    .tk-item h4 { margin:0 0 4px; font-size:14px; font-weight:700; }
    .tk-item p  { margin:0; font-size:12px; color:var(--co-mute);
      display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }

    .tk-empty { text-align:center; padding:44px 20px; color:var(--co-faint); font-size:13px; }
    .tk-seg { display:flex; gap:6px; margin-bottom:4px; }
    .tk-seg button { padding:8px 16px; border-radius:10px; border:1px solid var(--co-line-bold);
      background:#fff; cursor:pointer; font-family:inherit; font-size:13px; font-weight:600;
      color:var(--co-mute); }
    .tk-seg button.on { background:var(--co-navy-700); color:#fff; border-color:var(--co-navy-700); }

    .tk-toast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(20px);
      background:#111827; color:#fff; padding:12px 20px; border-radius:12px; font-size:13px;
      font-weight:600; opacity:0; pointer-events:none; transition:all .25s; z-index:9999; }
    .tk-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
    .tk-toast.is-error { background:var(--er); }
  </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="shell">
  <?php $sb_base = '..'; include('../includes/sidebar.php'); ?>

  <div class="main-area">
    <?php include('../includes/header.php'); ?>

    <main class="content">
      <div class="tk-wrap">

      <div class="tk-hero">
        <div>
          <span class="tag"><?= $ES_TABLERO ? 'CONTROL DE TAREAS' : 'MIS TAREAS' ?></span>
          <h1>Tareas</h1>
          <p><?= $ES_TABLERO
              ? 'Encarga trabajo con plazo a coordinadores y Tally Soporte, revisa la evidencia y califica el resultado.'
              : 'Lo que tienes encargado, con su plazo. Sube la evidencia y envía la entrega antes de la fecha.' ?></p>
        </div>
        <?php if ($ES_ADMIN): ?>
        <button class="tk-btn ghost-light" id="btnNueva">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nueva tarea
        </button>
        <?php endif; ?>
      </div>

      <div class="tk-kpis" id="tkKpis"></div>

      <?php if (count($MIS_SOPORTES)): ?>
      <!-- El coordinador responde por su soporte: necesita ver si va atrasado. -->
      <div class="tk-seg">
        <button class="on" data-ambito="mias">Mis tareas</button>
        <button data-ambito="soporte">Mi soporte<?= count($MIS_SOPORTES) === 1 ? ' · ' . htmlspecialchars($MIS_SOPORTES[0]['nombre']) : '' ?></button>
      </div>
      <?php endif; ?>

      <div class="tk-card">
        <div class="tk-tools">
          <input class="tk-search" id="tkQ" type="search" placeholder="Buscar por título, descripción o persona…">
          <div class="tk-chips" id="tkChips"></div>
          <?php if ($ES_TABLERO): ?>
          <select class="tk-sel" id="tkPersona"><option value="">Todas las personas</option></select>
          <?php endif; ?>
          <select class="tk-sel" id="tkMes"><option value="">Todos los meses</option></select>
          <button class="tk-btn" id="btnExcel" type="button">Excel</button>
          <button class="tk-btn" id="btnPdf" type="button">PDF</button>
        </div>

        <?php if ($ES_TABLERO): ?>
        <div class="tk-tablewrap">
          <table class="tk-table">
            <thead><tr>
              <th>Tarea</th><th>Asignado</th><th>Plazo</th><th>Entrega</th>
              <th>Adj.</th><th>Estado</th><th>Nota</th>
            </tr></thead>
            <tbody id="tkTbody"></tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="tk-list" id="tkList" style="padding:14px 16px"></div>
        <?php endif; ?>
      </div>

      </div>
    </main>
  </div>
</div>

<div class="tk-toast" id="tkToast">—</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script>
(function () {
  'use strict';

  /* ── Catálogos del servidor. No se redefine ni un estado aquí: si el
        catálogo PHP cambia, esta página cambia con él. ─────────────── */
  const ESTADOS     = <?= json_encode($JS_ESTADOS, JSON_UNESCAPED_UNICODE) ?>;
  const PRIORIDADES = <?= json_encode($JS_PRIORIDADES, JSON_UNESCAPED_UNICODE) ?>;
  const SEMAFOROS   = <?= json_encode($JS_SEMAFOROS, JSON_UNESCAPED_UNICODE) ?>;
  const ESCALA      = <?= json_encode($JS_ESCALA, JSON_UNESCAPED_UNICODE) ?>;
  const ES_ADMIN    = <?= $ES_ADMIN ? 'true' : 'false' ?>;
  const ES_TABLERO  = <?= $ES_TABLERO ? 'true' : 'false' ?>;
  const USER_ID     = <?= $USER_ID ?>;
  const MIS_SOPORTES = <?= json_encode($MIS_SOPORTES, JSON_UNESCAPED_UNICODE) ?>;
  const LOGO_B64    = <?= json_encode($LOGO_B64) ?>;

  const $ = (id) => document.getElementById(id);

  /* ── Estado de la vista ─────────────────────────────────────────── */
  let tareas   = [];
  let asignables = [];
  let fEstado  = '';      // '' = todos
  let fAtrasadas = false;
  let fPersona = '';
  let fMes     = '';
  let query    = '';
  let ambito   = 'mias';  // solo aplica si el coordinador tiene soporte

  /* ── Utilidades ─────────────────────────────────────────────────── */
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c =>
      ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }
  function toast(msg, type) {
    const t = $('tkToast');
    t.textContent = msg;
    t.className = 'tk-toast show' + (type === 'error' ? ' is-error' : '');
    clearTimeout(t._t); t._t = setTimeout(() => t.className = 'tk-toast', 3200);
  }
  /** 'YYYY-MM-DD HH:MM:SS' → '12/08/2026 23:59'. Sin new Date(): la cadena
      ya viene en hora de Lima y construir un Date la reinterpretaría en la
      zona del navegador. */
  function fmt(dt) {
    if (!dt) return '—';
    const m = String(dt).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : String(dt);
  }
  function fmtFecha(dt) {
    if (!dt) return '—';
    const m = String(dt).match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : String(dt);
  }
  function estrellas(n) {
    if (!n) return '<span style="color:var(--co-faint)">—</span>';
    return `<span class="tk-stars">${'★'.repeat(n)}${'☆'.repeat(5 - n)}</span>`
         + `<div class="tk-sub">${esc(ESCALA[n] || '')}</div>`;
  }
  function badgeEstado(t) {
    const e = ESTADOS[t.estado] || { label: t.estado, color: '#475569', bg: '#eee' };
    return `<span class="tk-badge" style="color:${e.color};background:${e.bg}">`
         + `<span class="dot"></span>${esc(e.label)}</span>`;
  }
  function chipAtraso(t) {
    if (!t.atrasada) return '';
    const d = t.dias_atraso;
    return `<span class="tk-late">ATRASADA${d > 0 ? ' · ' + d + (d === 1 ? ' día' : ' días') : ''}</span>`;
  }
  /** Celda de plazo: la fecha vigente con su semáforo y, si hay prórroga,
      la 1ª tachada al lado. Que la prórroga sea visible es media función
      del módulo: entregar a tiempo GRACIAS a una prórroga no es lo mismo
      que entregar a tiempo. */
  function celdaPlazo(t) {
    const s = SEMAFOROS[t.semaforo] || SEMAFOROS.a_tiempo;
    let html = `<div style="font-weight:600;color:${t.atrasada ? 'var(--er)' : 'inherit'}">${fmt(t.plazo_vigente)}</div>`;
    if (t.tiene_prorroga) html += `<div class="tk-sub">2ª fecha · antes <span class="tk-tacha">${fmt(t.fecha_limite)}</span></div>`;
    if (t.es_abierta && !t.atrasada && t.semaforo !== 'a_tiempo') {
      html += `<div class="tk-sub" style="color:${s.color};font-weight:700">${esc(s.label)}</div>`;
    }
    if (t.atrasada) html += `<div style="margin-top:3px">${chipAtraso(t)}</div>`;
    return html;
  }
  function celdaEntrega(t) {
    if (!t.enviado_at) return '<span style="color:var(--co-faint)">Sin entregar</span>';
    let html = `<div>${fmt(t.enviado_at)}</div>`;
    if (t.entregada_tarde) html += `<div style="margin-top:3px"><span class="tk-late">FUERA DE PLAZO</span></div>`;
    if (t.entregas_count > 1) html += `<div class="tk-sub">${t.entregas_count}.º envío</div>`;
    return html;
  }

  /* ── Filtros ────────────────────────────────────────────────────── */
  /** Fuente única de las filas que se ven. La usan la tabla, los KPIs,
      Excel y PDF, así que las exportaciones heredan los filtros sin
      código adicional. */
  function listaVisible() {
    const q = query.trim().toLowerCase();
    return tareas.filter(t => {
      if (fEstado && t.estado !== fEstado) return false;
      if (fAtrasadas && !t.atrasada) return false;
      if (fPersona && String(t.asignado_id) !== String(fPersona)) return false;
      if (fMes && String(t.fecha_limite).slice(0, 7) !== fMes) return false;
      // Segmento «Mis tareas» / «Mi soporte», solo para coordinadores con soporte.
      if (MIS_SOPORTES.length) {
        const mia = Number(t.asignado_id) === USER_ID;
        if (ambito === 'mias' && !mia) return false;
        if (ambito === 'soporte' && mia) return false;
      }
      if (q) {
        const heno = [t.titulo, t.descripcion, t.asignado_nombre].join(' ').toLowerCase();
        if (!heno.includes(q)) return false;
      }
      return true;
    });
  }

  /* ── KPIs ───────────────────────────────────────────────────────── */
  function pintarKpis() {
    const L = listaVisible();
    const n = (f) => L.filter(f).length;
    let kpis;
    if (ES_TABLERO) {
      // Nota media SOBRE LAS FILAS VISIBLES, no sobre todo el histórico:
      // si no, cambiar de mes no cambiaría el número y no diría nada.
      const conNota = L.filter(t => t.nota);
      const media = conNota.length
        ? (conNota.reduce((a, t) => a + t.nota, 0) / conNota.length).toFixed(1) : '—';
      kpis = [
        ['Pendientes',   n(t => t.estado === 'pendiente'),  ''],
        ['Atrasadas',    n(t => t.atrasada),                'is-alert'],
        ['Por revisar',  n(t => t.estado === 'entregada'),  'is-info'],
        ['Aprobadas',    n(t => t.estado === 'aprobada'),   'is-ok'],
        ['Nota media',   media,                             'is-warn'],
      ];
    } else {
      kpis = [
        ['Por hacer',   n(t => t.es_abierta),              ''],
        ['Atrasadas',   n(t => t.atrasada),                'is-alert'],
        ['En revisión', n(t => t.estado === 'entregada'),  'is-info'],
        ['Aprobadas',   n(t => t.estado === 'aprobada'),   'is-ok'],
      ];
    }
    $('tkKpis').innerHTML = kpis.map(([lbl, val, cls]) =>
      `<div class="tk-kpi ${cls}"><div class="lbl">${esc(lbl)}</div><div class="val">${esc(val)}</div></div>`
    ).join('');
  }

  /* ── Chips de estado ────────────────────────────────────────────── */
  function pintarChips() {
    let html = `<button class="tk-chip${fEstado === '' && !fAtrasadas ? ' on' : ''}" data-estado="">Todas</button>`;
    Object.entries(ESTADOS).forEach(([k, e]) => {
      html += `<button class="tk-chip${fEstado === k ? ' on' : ''}" data-estado="${k}">${esc(e.label)}</button>`;
    });
    // ATRASADAS es un filtro, no un estado: cruza con pendiente y observada.
    html += `<button class="tk-chip is-late${fAtrasadas ? ' on' : ''}" data-atrasadas="1">Atrasadas</button>`;
    $('tkChips').innerHTML = html;
  }

  /* ── Tabla (tablero) ────────────────────────────────────────────── */
  function pintarTabla() {
    const tbody = $('tkTbody');
    const L = listaVisible();
    if (!L.length) {
      tbody.innerHTML = `<tr><td colspan="7" class="tk-empty">Sin tareas que coincidan con el filtro.</td></tr>`;
      return;
    }
    tbody.innerHTML = L.map(t => {
      const pr = PRIORIDADES[t.prioridad] || PRIORIDADES.media;
      const esSop = t.asignado_rol === 'Soporte';
      return `<tr class="${t.atrasada ? 'is-late' : ''}">
        <td>
          <div class="tk-tt" data-abrir="${t.id}">${esc(t.titulo)}</div>
          <div class="tk-sub">
            <span style="color:${pr.color};font-weight:700">${esc(pr.label)}</span>
            ${t.descripcion ? ' · ' + esc(String(t.descripcion).slice(0, 70)) : ''}
          </div>
        </td>
        <td>
          <div>${esc(t.asignado_nombre)}</div>
          <div class="tk-sub"><span class="tk-rolchip ${esSop ? 'is-sop' : ''}">${esc(t.asignado_rol_label)}</span></div>
        </td>
        <td>${celdaPlazo(t)}</td>
        <td>${celdaEntrega(t)}</td>
        <td style="text-align:center">${t.adjuntos.length || '—'}</td>
        <td>${badgeEstado(t)}</td>
        <td>${estrellas(t.nota)}</td>
      </tr>`;
    }).join('');
  }

  /* ── Lista («Mis tareas») ───────────────────────────────────────── */
  function pintarLista() {
    const cont = $('tkList');
    const L = listaVisible();
    if (!L.length) {
      cont.innerHTML = `<div class="tk-empty">No tienes tareas que coincidan con el filtro.</div>`;
      return;
    }
    cont.innerHTML = L.map(t => `
      <div class="tk-item sem-${esc(t.semaforo)}" data-abrir="${t.id}">
        <div class="grow">
          <h4>${esc(t.titulo)} ${chipAtraso(t)}</h4>
          ${t.descripcion ? `<p>${esc(t.descripcion)}</p>` : ''}
          <div class="tk-sub" style="margin-top:6px">
            Vence ${fmt(t.plazo_vigente)}
            ${t.tiene_prorroga ? ` · 2ª fecha (antes <span class="tk-tacha">${fmt(t.fecha_limite)}</span>)` : ''}
            ${t.adjuntos.length ? ` · ${t.adjuntos.length} archivo${t.adjuntos.length === 1 ? '' : 's'}` : ''}
          </div>
        </div>
        <div style="text-align:right;display:flex;flex-direction:column;gap:6px;align-items:flex-end">
          ${badgeEstado(t)}
          ${t.nota ? estrellas(t.nota) : ''}
        </div>
      </div>`).join('');
  }

  function pintar() {
    pintarChips();
    pintarKpis();
    if (ES_TABLERO) pintarTabla(); else pintarLista();
  }

  /* ── Carga ──────────────────────────────────────────────────────── */
  async function cargar() {
    try {
      const res  = await fetch('../api/get_tareas.php', { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) { toast(data.error || 'Error al cargar', 'error'); return; }
      tareas = data.data || [];
      pintarSelectMes();
      pintar();
    } catch (e) { toast('Error de red al cargar las tareas', 'error'); }
  }

  async function cargarAsignables() {
    if (!ES_TABLERO && !ES_ADMIN) return;
    try {
      const res  = await fetch('../api/get_asignables.php', { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) return;
      asignables = data.data || [];
      const sel = $('tkPersona');
      if (!sel) return;
      let html = '<option value="">Todas las personas</option>';
      ['Coordinador', 'Soporte'].forEach(rol => {
        const g = asignables.filter(a => a.rol === rol);
        if (!g.length) return;
        html += `<optgroup label="${rol === 'Soporte' ? 'Tally Soporte' : 'Coordinadores'}">`
              + g.map(a => `<option value="${a.id}">${esc(a.nombre)}</option>`).join('')
              + '</optgroup>';
      });
      sel.innerHTML = html;
    } catch (e) { /* el filtro es opcional; no bloquea la página */ }
  }

  /** Meses presentes en los datos, para no ofrecer meses vacíos. */
  function pintarSelectMes() {
    const meses = [...new Set(tareas.map(t => String(t.fecha_limite).slice(0, 7)))].sort().reverse();
    const nom = ['','enero','febrero','marzo','abril','mayo','junio','julio',
                 'agosto','septiembre','octubre','noviembre','diciembre'];
    $('tkMes').innerHTML = '<option value="">Todos los meses</option>'
      + meses.map(m => {
          const [y, mm] = m.split('-');
          return `<option value="${m}"${fMes === m ? ' selected' : ''}>${nom[Number(mm)]} ${y}</option>`;
        }).join('');
  }

  /* ── Eventos ────────────────────────────────────────────────────── */
  $('tkQ').addEventListener('input', e => { query = e.target.value; pintar(); });
  $('tkMes').addEventListener('change', e => { fMes = e.target.value; pintar(); });
  if ($('tkPersona')) $('tkPersona').addEventListener('change', e => { fPersona = e.target.value; pintar(); });

  $('tkChips').addEventListener('click', e => {
    const b = e.target.closest('.tk-chip'); if (!b) return;
    if (b.dataset.atrasadas) { fAtrasadas = !fAtrasadas; fEstado = ''; }
    else { fEstado = b.dataset.estado; fAtrasadas = false; }
    pintar();
  });

  document.querySelectorAll('.tk-seg button').forEach(b => {
    b.addEventListener('click', () => {
      document.querySelectorAll('.tk-seg button').forEach(x => x.classList.remove('on'));
      b.classList.add('on');
      ambito = b.dataset.ambito;
      pintar();
    });
  });

  document.addEventListener('click', e => {
    const el = e.target.closest('[data-abrir]');
    if (el) abrirDetalle(Number(el.dataset.abrir));
  });

  // Se define en la Task 17; aquí solo se declara para que la página no
  // reviente si se pulsa una fila antes de implementarla.
  window.abrirDetalle = window.abrirDetalle || function () { toast('Detalle aún no disponible'); };

  cargarAsignables();
  cargar();
  window.tkRecargar = cargar;
  window.tkToast    = toast;
  window.tkListaVisible = listaVisible;
  window.tkEsc      = esc;
  window.tkFmt      = fmt;
  window.tkFmtFecha = fmtFecha;
  window.tkAsignables = () => asignables;
  window.tkLogo     = LOGO_B64;
})();
</script>
</body>
</html>
```

- [ ] **Step 2: Verificar la sintaxis**

Run: `php -l pages/tareas.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Ver el tablero como administrador**

Abre `http://localhost/portallyman.online/pages/tareas.php` con la sesión de admin.

Expected:
- Cinco KPIs: Pendientes, Atrasadas, Por revisar, Aprobadas, Nota media.
- Tabla con las tareas de prueba. La atrasada sale con fondo rojizo y el chip `ATRASADA · N días`.
- La tarea con 2ª fecha muestra la fecha vigente y debajo `2ª fecha · antes <fecha 1 tachada>`.
- La entrega tardía muestra el chip `FUERA DE PLAZO`.
- Sin errores en la consola del navegador.

- [ ] **Step 4: Ver «Mis tareas» como coordinador y como soporte**

Entra con `coord.test`. Expected: cuatro KPIs, lista de tarjetas con borde izquierdo de color según el semáforo, y el segmento **Mis tareas / Mi soporte** arriba (porque tiene un soporte a cargo). Al pulsar «Mi soporte» debe verse solo la tarea de `soporte.test`.

Entra con `soporte.test`. Expected: la misma vista de lista, **sin** el segmento, y con una sola tarea.

- [ ] **Step 5: Probar los filtros**

Con el admin: pulsa el chip **Atrasadas** y comprueba que los KPIs se recalculan sobre lo filtrado. Escribe en el buscador. Cambia el select de persona y el de mes.

- [ ] **Step 6: Commit**

```bash
git add pages/tareas.php
git commit -m "feat(tareas): pagina con tablero de administrador y vista Mis tareas"
```

---

## Task 16: Modal de creación y edición

Solo se renderiza para el Administrador. Los bloques nuevos van en `pages/tareas.php`; el script se comunica con el anterior a través de los `window.tk*` que la Task 15 dejó expuestos, así cada bloque se mantiene corto y legible por separado.

**Files:**
- Modify: `pages/tareas.php` (CSS, HTML del modal y un `<script>` nuevo)

- [ ] **Step 1: Añadir el CSS de los modales**

Añade al final del `<style>`, antes de `</style>`:

```css
    /* Modales */
    .tk-mb { position:fixed; inset:0; background:rgba(17,24,39,.55); backdrop-filter:blur(3px);
      display:none; align-items:center; justify-content:center; z-index:900; padding:20px; }
    .tk-mb.open { display:flex; }
    .tk-modal { background:#fff; border-radius:20px; width:100%; max-width:620px;
      max-height:92vh; display:flex; flex-direction:column; box-shadow:0 24px 64px rgba(0,0,0,.28); }
    .tk-modal.wide { max-width:860px; }
    .tk-mh { display:flex; align-items:flex-start; justify-content:space-between; gap:14px;
      padding:20px 24px; border-bottom:1px solid var(--co-line); }
    .tk-mh h3 { margin:0; font-size:17px; font-weight:700; }
    .tk-mh .sub { font-size:12px; color:var(--co-faint); margin-top:3px; }
    .tk-mx { background:none; border:none; cursor:pointer; color:var(--co-faint); padding:4px; }
    .tk-mbody { padding:20px 24px; overflow-y:auto; display:flex; flex-direction:column; gap:14px; }
    .tk-mf { display:flex; justify-content:flex-end; gap:10px; padding:16px 24px;
      border-top:1px solid var(--co-line); }
    .tk-field { display:flex; flex-direction:column; gap:5px; }
    .tk-field label { font-size:12px; font-weight:700; color:var(--co-mute); }
    .tk-field input, .tk-field select, .tk-field textarea {
      padding:10px 12px; border-radius:10px; border:1px solid var(--co-line-bold);
      font-family:inherit; font-size:13px; background:#fff; width:100%; }
    .tk-field textarea { min-height:84px; resize:vertical; }
    .tk-field .hint { font-size:11px; color:var(--co-faint); }
    .tk-row2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    /* Selector de destinatarios */
    .tk-dest { border:1px solid var(--co-line-bold); border-radius:12px; max-height:220px;
      overflow-y:auto; }
    .tk-dest .grp { padding:7px 12px; font-size:10px; font-weight:800; letter-spacing:.06em;
      text-transform:uppercase; color:var(--co-faint); background:var(--co-deck);
      position:sticky; top:0; }
    .tk-dest label { display:flex; align-items:center; gap:9px; padding:8px 12px; cursor:pointer;
      font-size:13px; border-top:1px solid rgba(0,0,0,.04); }
    .tk-dest label:hover { background:rgba(0,135,90,.04); }
    .tk-dest input { width:16px; height:16px; accent-color:var(--co-navy-700); }
    .tk-dest .who { font-size:11px; color:var(--co-faint); margin-left:auto; }
```

- [ ] **Step 2: Añadir el HTML del modal**

Inserta justo antes de `<div class="tk-toast" id="tkToast">—</div>`:

```php
<?php if ($ES_ADMIN): ?>
<!-- MODAL · nueva tarea / edición -->
<div class="tk-mb" id="tkModalBack">
  <div class="tk-modal">
    <div class="tk-mh">
      <div>
        <h3 id="tkModalTitle">Nueva tarea</h3>
        <div class="sub" id="tkModalSub">Se creará una tarea independiente por cada destinatario.</div>
      </div>
      <button class="tk-mx" id="tkModalClose">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="tk-mbody">
      <input type="hidden" id="tm-id">
      <div class="tk-field">
        <label>Título</label>
        <input id="tm-titulo" type="text" maxlength="180" placeholder="Ej. Inventario de precintos del almacén">
      </div>
      <div class="tk-field">
        <label>Descripción</label>
        <textarea id="tm-desc" placeholder="Qué hay que hacer exactamente y qué se espera como evidencia."></textarea>
      </div>
      <div class="tk-row2">
        <div class="tk-field">
          <label>Prioridad</label>
          <select id="tm-prioridad">
            <option value="baja">Baja</option>
            <option value="media" selected>Media</option>
            <option value="alta">Alta</option>
          </select>
        </div>
        <div class="tk-field">
          <label>Fecha límite (1ª fecha)</label>
          <input id="tm-fecha" type="datetime-local">
          <span class="hint">Si no pones hora, se toman las 23:59.</span>
        </div>
      </div>

      <div class="tk-field" id="tm-dest-wrap">
        <label>Destinatarios</label>
        <div class="tk-dest" id="tm-dest"></div>
        <span class="hint" id="tm-dest-hint">Ninguno seleccionado.</span>
      </div>

      <div class="tk-field" id="tm-lote-wrap" style="display:none">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" id="tm-lote" style="width:16px;height:16px">
          Aplicar los cambios a todo el lote
        </label>
        <span class="hint">Solo alcanza a las tareas del lote que sigan pendientes. Las ya entregadas o calificadas conservan el enunciado bajo el que se juzgaron.</span>
      </div>
    </div>
    <div class="tk-mf">
      <button class="tk-btn" id="tkModalCancel">Cancelar</button>
      <button class="tk-btn primary" id="tkModalSave">Guardar</button>
    </div>
  </div>
</div>
<?php endif; ?>
```

- [ ] **Step 3: Añadir el script del modal**

Inserta un `<script>` nuevo justo después del `</script>` del bloque principal:

```php
<?php if ($ES_ADMIN): ?>
<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const esc = window.tkEsc;
  let editando = null;   // fila completa cuando se edita; null al crear

  /* ── Destinatarios ──────────────────────────────────────────────── */
  function pintarDestinatarios(preseleccion) {
    const lista = window.tkAsignables();
    let html = '';
    [['Coordinador', 'Coordinadores'], ['Soporte', 'Tally Soporte']].forEach(([rol, titulo]) => {
      const g = lista.filter(a => a.rol === rol);
      if (!g.length) return;
      html += `<div class="grp">${titulo}</div>`;
      html += g.map(a => `
        <label>
          <input type="checkbox" value="${a.id}" ${preseleccion === a.id ? 'checked' : ''}>
          <span>${esc(a.nombre)}</span>
          ${a.coordinador_nombre ? `<span class="who">apoyo de ${esc(a.coordinador_nombre)}</span>` : ''}
        </label>`).join('');
    });
    $('tm-dest').innerHTML = html || '<div class="tk-empty">No hay coordinadores ni soportes activos.</div>';
    actualizarHint();
  }

  function seleccionados() {
    return [...$('tm-dest').querySelectorAll('input:checked')].map(i => Number(i.value));
  }

  function actualizarHint() {
    const n = seleccionados().length;
    $('tm-dest-hint').textContent = n === 0
      ? 'Ninguno seleccionado.'
      : `Se ${n === 1 ? 'creará 1 tarea' : 'crearán ' + n + ' tareas'} independientes, una por persona.`;
  }

  /* ── Abrir / cerrar ─────────────────────────────────────────────── */
  /** Al crear se piden solo enunciado, prioridad, fecha 1 y destinatarios.
      Al editar, los destinatarios ya no se tocan: cambiar de responsable a
      mitad de camino tiraría la evidencia y la bitácora ya acumuladas. */
  function abrir(t) {
    editando = t || null;
    $('tkModalTitle').textContent = t ? 'Editar tarea' : 'Nueva tarea';
    $('tkModalSub').textContent   = t
      ? 'Solo el enunciado y la 1ª fecha. El estado y la nota no se tocan aquí.'
      : 'Se creará una tarea independiente por cada destinatario.';
    $('tm-id').value        = t ? t.id : '';
    $('tm-titulo').value    = t ? t.titulo : '';
    $('tm-desc').value      = t ? (t.descripcion || '') : '';
    $('tm-prioridad').value = t ? t.prioridad : 'media';
    $('tm-fecha').value     = t ? String(t.fecha_limite).slice(0, 16).replace(' ', 'T') : '';

    $('tm-dest-wrap').style.display = t ? 'none' : '';
    $('tm-lote-wrap').style.display = (t && t.lote_id) ? '' : 'none';
    $('tm-lote').checked = false;
    if (!t) pintarDestinatarios(null);

    $('tkModalBack').classList.add('open');
    setTimeout(() => $('tm-titulo').focus(), 80);
  }
  function cerrar() { $('tkModalBack').classList.remove('open'); editando = null; }

  /* ── Guardar ────────────────────────────────────────────────────── */
  async function guardar() {
    const titulo = $('tm-titulo').value.trim();
    let   fecha  = $('tm-fecha').value.trim();

    if (!titulo) { window.tkToast('Indica el título de la tarea', 'error'); $('tm-titulo').focus(); return; }
    if (!fecha)  { window.tkToast('Indica la fecha límite', 'error');       $('tm-fecha').focus();  return; }

    // datetime-local entrega 'YYYY-MM-DDTHH:MM'. Si el usuario dejó la hora
    // en 00:00 casi seguro quería «ese día», no «esa medianoche».
    fecha = fecha.replace('T', ' ');
    if (fecha.endsWith(' 00:00')) fecha = fecha.slice(0, 11) + '23:59';

    const payload = {
      id:            Number($('tm-id').value) || 0,
      titulo,
      descripcion:   $('tm-desc').value.trim(),
      prioridad:     $('tm-prioridad').value,
      fecha_limite:  fecha,
    };

    if (payload.id) {
      payload.aplicar_a_lote = $('tm-lote').checked;
    } else {
      payload.destinatarios = seleccionados();
      if (!payload.destinatarios.length) {
        window.tkToast('Elige al menos un destinatario', 'error'); return;
      }
    }

    const btn = $('tkModalSave');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res  = await fetch('../api/save_tarea.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!data.success) { window.tkToast(data.error || 'No se pudo guardar', 'error'); return; }
      window.tkToast(payload.id
        ? (data.afectadas > 1 ? `Actualizadas ${data.afectadas} tareas del lote` : 'Tarea actualizada')
        : `Se ${data.creadas === 1 ? 'creó 1 tarea' : 'crearon ' + data.creadas + ' tareas'}`);
      cerrar();
      window.tkRecargar();
    } catch (e) {
      window.tkToast('Error de red al guardar', 'error');
    } finally {
      btn.disabled = false; btn.textContent = 'Guardar';
    }
  }

  /* ── Eventos ────────────────────────────────────────────────────── */
  $('btnNueva').addEventListener('click', () => abrir(null));
  $('tkModalClose').addEventListener('click', cerrar);
  $('tkModalCancel').addEventListener('click', cerrar);
  $('tkModalSave').addEventListener('click', guardar);
  $('tm-dest').addEventListener('change', actualizarHint);
  $('tkModalBack').addEventListener('click', e => { if (e.target.id === 'tkModalBack') cerrar(); });

  // El modal de detalle (Task 18) llama aquí para editar.
  window.tkAbrirEdicion = abrir;
})();
</script>
<?php endif; ?>
```

- [ ] **Step 4: Verificar la sintaxis**

Run: `php -l pages/tareas.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Crear una tarea desde la interfaz**

Como administrador, pulsa **Nueva tarea**:
1. Sin destinatarios → el botón guarda y sale `Elige al menos un destinatario`.
2. Marca un Coordinador y un Tally Soporte. El texto de ayuda debe decir `Se crearán 2 tareas independientes, una por persona.`
3. Pon título, fecha y guarda. Expected: toast `Se crearon 2 tareas` y las dos filas nuevas en la tabla con el mismo título y distinto asignado.

- [ ] **Step 6: Verificar el valor por defecto de la hora**

Crea una tarea dejando la hora en `00:00`. Verifica en BD que se guardó como `23:59:00`:

```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "SELECT id, titulo, fecha_limite FROM tareas ORDER BY id DESC LIMIT 2;"
```

- [ ] **Step 7: Commit**

```bash
git add pages/tareas.php
git commit -m "feat(tareas): modal de creacion multi-destinatario y edicion por lote"
```

---

## Task 17: Modal de detalle · evidencia y envío

**Files:**
- Modify: `pages/tareas.php` (CSS, HTML del modal de detalle y un `<script>` nuevo)

- [ ] **Step 1: Añadir el CSS**

Añade al final del `<style>`:

```css
    /* Detalle */
    .tk-sec { border:1px solid var(--co-line); border-radius:14px; padding:14px 16px; }
    .tk-sec h5 { margin:0 0 10px; font-size:11px; font-weight:800; letter-spacing:.06em;
      text-transform:uppercase; color:var(--co-faint); }
    .tk-obs { border:1px solid rgba(217,119,6,.35); background:var(--wn-bg); border-radius:14px;
      padding:14px 16px; }
    .tk-obs h5 { margin:0 0 6px; font-size:11px; font-weight:800; letter-spacing:.06em;
      text-transform:uppercase; color:var(--wn); }
    .tk-obs p { margin:0; font-size:13px; white-space:pre-wrap; }

    .tk-meta { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; }
    .tk-meta .k { font-size:10px; font-weight:800; letter-spacing:.05em; text-transform:uppercase;
      color:var(--co-faint); }
    .tk-meta .v { font-size:13px; font-weight:600; margin-top:2px; }

    .tk-drop { border:2px dashed var(--co-line-bold); border-radius:12px; padding:18px;
      text-align:center; cursor:pointer; color:var(--co-mute); font-size:13px; }
    .tk-drop.over { border-color:var(--co-navy-700); background:rgba(0,135,90,.05); }
    .tk-files { display:flex; flex-direction:column; gap:7px; margin-top:10px; }
    .tk-file { display:flex; align-items:center; gap:9px; padding:8px 11px; border-radius:10px;
      background:var(--co-deck); font-size:12px; }
    .tk-file .nm { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .tk-file .st { font-size:10px; font-weight:800; letter-spacing:.04em; }
    .tk-file .st.pendiente { color:var(--wn); }
    .tk-file .st.error     { color:var(--er); }
    .tk-file .del { background:none; border:none; cursor:pointer; color:var(--er); font-size:16px;
      line-height:1; padding:0 4px; }
    .tk-ronda { font-size:10px; font-weight:800; letter-spacing:.05em; text-transform:uppercase;
      color:var(--co-faint); margin:10px 0 4px; }
```

- [ ] **Step 2: Añadir el HTML del modal de detalle**

Inserta justo antes de `<div class="tk-toast" id="tkToast">—</div>`:

```html
<!-- MODAL · detalle -->
<div class="tk-mb" id="tkDetBack">
  <div class="tk-modal wide">
    <div class="tk-mh">
      <div>
        <h3 id="tkDetTitle">Tarea</h3>
        <div class="sub" id="tkDetSub">—</div>
      </div>
      <button class="tk-mx" id="tkDetClose">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="tk-mbody" id="tkDetBody"></div>
    <div class="tk-mf" id="tkDetFoot"></div>
  </div>
</div>
```

- [ ] **Step 3: Añadir el script del detalle**

Inserta otro `<script>` después del anterior. Este bloque es común a todos los roles: pinta el enunciado, el material, la evidencia y —si la sesión puede entregar— la zona de subida.

```html
<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const esc = window.tkEsc, fmt = window.tkFmt;
  let T = null;    // la tarea abierta, con permisos resueltos por el servidor

  async function abrirDetalle(id) {
    try {
      const res  = await fetch('../api/get_tarea.php?id=' + id, { cache: 'no-store' });
      const data = await res.json();
      if (!data.success) { window.tkToast(data.error || 'No se pudo abrir', 'error'); return; }
      T = data.data;
      pintar();
      $('tkDetBack').classList.add('open');
    } catch (e) { window.tkToast('Error de red', 'error'); }
  }
  function cerrar() { $('tkDetBack').classList.remove('open'); T = null; }

  function pintar() {
    $('tkDetTitle').textContent = T.titulo;
    $('tkDetSub').textContent   =
      `${T.asignado_nombre} · ${T.asignado_rol_label} · creada por ${T.creado_por}`;

    const ronda = T.entregas_count + 1;
    let html = '';

    /* Lo que hay que corregir va ARRIBA del formulario, no debajo: si el
       admin devolvió la tarea, eso es lo primero que hay que leer. */
    if (T.estado === 'observada' && T.comentario_admin) {
      html += `<div class="tk-obs">
        <h5>Devuelta con observaciones${T.nota ? ' · nota ' + T.nota : ''}</h5>
        <p>${esc(T.comentario_admin)}</p>
        <div class="tk-sub" style="margin-top:8px">Por ${esc(T.revisado_por || '—')} el ${fmt(T.revisado_at)}</div>
      </div>`;
    }

    // ── Enunciado y datos ──
    html += `<div class="tk-sec">
      <h5>Encargo</h5>
      ${T.descripcion ? `<p style="margin:0 0 12px;font-size:13px;white-space:pre-wrap">${esc(T.descripcion)}</p>`
                      : `<p style="margin:0 0 12px;font-size:13px;color:var(--co-faint)">Sin descripción.</p>`}
      <div class="tk-meta">
        <div><div class="k">1ª fecha</div><div class="v">${fmt(T.fecha_limite)}</div></div>
        <div><div class="k">2ª fecha</div><div class="v">${T.fecha_limite_2 ? fmt(T.fecha_limite_2) : '—'}</div></div>
        <div><div class="k">Plazo vigente</div><div class="v" style="color:${T.atrasada ? 'var(--er)' : 'inherit'}">${fmt(T.plazo_vigente)}</div></div>
        <div><div class="k">Prioridad</div><div class="v">${esc(T.prioridad)}</div></div>
        <div><div class="k">Envíos</div><div class="v">${T.entregas_count}</div></div>
      </div>
      ${T.prorroga_motivo ? `<div class="tk-sub" style="margin-top:10px">Motivo de la prórroga: ${esc(T.prorroga_motivo)} — ${esc(T.prorroga_por || '')}</div>` : ''}
    </div>`;

    // ── Material de referencia del administrador ──
    const material = T.adjuntos.filter(a => a.origen === 'admin');
    if (material.length) {
      html += `<div class="tk-sec"><h5>Material de referencia</h5>
        <div class="tk-files">${material.map(fileHtml).join('')}</div></div>`;
    }

    // ── Evidencia, agrupada por ronda de envío ──
    const evid = T.adjuntos.filter(a => a.origen === 'asignado');
    html += `<div class="tk-sec"><h5>Evidencia de la entrega</h5>`;
    if (evid.length) {
      const rondas = [...new Set(evid.map(a => a.entrega_nro))].sort();
      rondas.forEach(r => {
        if (rondas.length > 1) html += `<div class="tk-ronda">Envío n.º ${r}</div>`;
        html += `<div class="tk-files">${evid.filter(a => a.entrega_nro === r).map(fileHtml).join('')}</div>`;
      });
    } else {
      html += `<p style="margin:0;font-size:13px;color:var(--co-faint)">Todavía no hay archivos.</p>`;
    }

    // Zona de subida: solo si ESTA sesión puede entregar. Lo decidió el
    // servidor en get_tarea.php; aquí no se vuelve a razonar el permiso.
    if (T.permisos.entregar) {
      html += `
        <div class="tk-ronda" style="margin-top:14px">Añadir a este envío (n.º ${ronda})</div>
        <div class="tk-drop" id="tkDrop">Arrastra archivos aquí o haz clic para elegirlos<br>
          <span class="tk-sub">Máx. 4 MB por archivo, hasta 10 por tarea.</span></div>
        <input type="file" id="tkFileInput" multiple style="display:none">`;
    }
    html += `</div>`;

    // ── Comentario de entrega ──
    if (T.permisos.entregar) {
      html += `<div class="tk-field">
        <label>Comentario de entrega</label>
        <textarea id="tkComent" placeholder="Explica qué entregas. Si no adjuntas archivos, este comentario es obligatorio.">${esc(T.entrega_comentario || '')}</textarea>
      </div>`;
    } else if (T.entrega_comentario) {
      html += `<div class="tk-sec"><h5>Comentario de la entrega</h5>
        <p style="margin:0;font-size:13px;white-space:pre-wrap">${esc(T.entrega_comentario)}</p>
        <div class="tk-sub" style="margin-top:8px">Enviado el ${fmt(T.enviado_at)}${T.entregada_tarde ? ' · <span style="color:var(--er);font-weight:700">FUERA DE PLAZO</span>' : ''}</div>
      </div>`;
    }

    // Punto de anclaje para el panel de revisión, que añade la Task 18.
    html += `<div id="tkRevSlot"></div>`;
    $('tkDetBody').innerHTML = html;

    // ── Pie ──
    let foot = '';
    if (T.permisos.editar)   foot += `<button class="tk-btn" id="tkDetEditar">Editar</button>`;
    if (T.permisos.eliminar) foot += `<button class="tk-btn danger" id="tkDetBorrar">Eliminar</button>`;
    foot += `<button class="tk-btn" id="tkDetCerrar">Cerrar</button>`;
    if (T.permisos.entregar) {
      foot += `<button class="tk-btn primary" id="tkDetEnviar">${T.estado === 'observada' ? 'Reenviar' : 'Enviar entrega'}</button>`;
    }
    $('tkDetFoot').innerHTML = foot;

    conectar();
    if (window.tkPintarRevision) window.tkPintarRevision(T);   // Task 18
  }

  function fileHtml(a) {
    const puedeBorrar = (a.subido_por_id === <?= $USER_ID ?> && T.es_abierta) || <?= $ES_ADMIN ? 'true' : 'false' ?>;
    const enlace = a.drive_url
      ? `<a href="${esc(a.drive_url)}" target="_blank" rel="noopener" class="nm">${esc(a.nombre_archivo)}</a>`
      : `<span class="nm">${esc(a.nombre_archivo)}</span>`;
    return `<div class="tk-file">
      ${enlace}
      ${a.estado !== 'subido' ? `<span class="st ${esc(a.estado)}">${a.estado === 'pendiente' ? 'EN EL SERVIDOR' : 'ERROR'}</span>` : ''}
      <span class="tk-sub">${Math.round(a.peso_bytes / 1024)} KB</span>
      ${puedeBorrar ? `<button class="del" data-borrar-adj="${a.id}" title="Quitar">&times;</button>` : ''}
    </div>`;
  }

  /* ── Subida ─────────────────────────────────────────────────────── */
  async function subir(files) {
    for (const f of files) {
      const fd = new FormData();
      fd.append('id', T.id);
      fd.append('origen', T.permisos.editar && !T.permisos.entregar ? 'admin' : 'asignado');
      fd.append('file', f);
      try {
        const res  = await fetch('../api/upload_tarea_file.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) { window.tkToast(data.error, 'error'); continue; }
        if (data.aviso) window.tkToast(data.aviso, 'error');
      } catch (e) { window.tkToast('Error de red al subir ' + f.name, 'error'); }
    }
    await abrirDetalle(T.id);   // recarga para ver los archivos ya persistidos
  }

  async function borrarAdjunto(adjId) {
    if (!confirm('¿Quitar este archivo de la tarea?')) return;
    try {
      const res  = await fetch('../api/delete_tarea_adjunto.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: adjId }),
      });
      const data = await res.json();
      if (!data.success) { window.tkToast(data.error, 'error'); return; }
      await abrirDetalle(T.id);
    } catch (e) { window.tkToast('Error de red', 'error'); }
  }

  /* ── Envío ──────────────────────────────────────────────────────── */
  async function enviar() {
    const btn = $('tkDetEnviar');
    btn.disabled = true; btn.textContent = 'Enviando…';
    try {
      const res  = await fetch('../api/enviar_tarea.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: T.id, comentario: ($('tkComent')?.value || '').trim() }),
      });
      const data = await res.json();
      if (!data.success) { window.tkToast(data.error, 'error'); return; }
      window.tkToast(data.entregada_tarde
        ? 'Entrega registrada, fuera de plazo'
        : 'Entrega enviada. Queda en revisión.');
      cerrar();
      window.tkRecargar();
    } catch (e) {
      window.tkToast('Error de red al enviar', 'error');
    } finally {
      btn.disabled = false;
    }
  }

  async function borrarTarea() {
    if (!confirm('¿Eliminar esta tarea? Se borran también su evidencia y su historial.')) return;
    const res  = await fetch('../api/delete_tarea.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: T.id }),
    });
    const data = await res.json();
    if (!data.success) { window.tkToast(data.error, 'error'); return; }
    window.tkToast('Tarea eliminada');
    cerrar();
    window.tkRecargar();
  }

  /* ── Conexión de eventos tras cada repintado ────────────────────── */
  function conectar() {
    const drop = $('tkDrop'), input = $('tkFileInput');
    if (drop && input) {
      drop.addEventListener('click', () => input.click());
      input.addEventListener('change', () => { if (input.files.length) subir([...input.files]); });
      ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => {
        e.preventDefault(); drop.classList.add('over');
      }));
      ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => {
        e.preventDefault(); drop.classList.remove('over');
      }));
      drop.addEventListener('drop', e => {
        if (e.dataTransfer.files.length) subir([...e.dataTransfer.files]);
      });
    }
    $('tkDetBody').querySelectorAll('[data-borrar-adj]').forEach(b =>
      b.addEventListener('click', () => borrarAdjunto(Number(b.dataset.borrarAdj))));

    $('tkDetCerrar').addEventListener('click', cerrar);
    if ($('tkDetEnviar')) $('tkDetEnviar').addEventListener('click', enviar);
    if ($('tkDetBorrar')) $('tkDetBorrar').addEventListener('click', borrarTarea);
    if ($('tkDetEditar')) $('tkDetEditar').addEventListener('click', () => {
      cerrar(); window.tkAbrirEdicion(T);
    });
  }

  $('tkDetClose').addEventListener('click', cerrar);
  $('tkDetBack').addEventListener('click', e => { if (e.target.id === 'tkDetBack') cerrar(); });

  window.abrirDetalle  = abrirDetalle;
  window.tkTareaActual = () => T;
  window.tkCerrarDet   = cerrar;
})();
</script>
```

- [ ] **Step 4: Verificar la sintaxis**

Run: `php -l pages/tareas.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Entregar desde la interfaz como coordinador**

Entra con `coord.test`, abre una tarea pendiente:
1. El pie muestra **Enviar entrega**; no muestra Editar ni Eliminar.
2. Pulsa **Enviar entrega** sin comentario ni archivos → toast rojo `Adjunta al menos un archivo de evidencia o escribe un comentario de entrega.`
3. Arrastra un PDF o una imagen a la zona de subida. Debe aparecer en la lista con su peso.
4. Escribe un comentario y envía. Expected: toast de confirmación, el modal se cierra y en la lista la tarea pasa a **En revisión**.

- [ ] **Step 6: Verificar la vista de solo lectura del soporte**

Con `coord.test`, cambia al segmento **Mi soporte** y abre la tarea de `soporte.test`. Expected: se ve todo, pero el pie **no** tiene «Enviar entrega» ni zona de subida — `permisos.entregar` es `false`.

- [ ] **Step 7: Verificar el bloque de observación**

Con el admin, devuelve una tarea con observaciones (por API, como en la Task 11, o esperando a la Task 18). Vuelve a abrirla como el asignado. Expected: el comentario del administrador aparece en un bloque ámbar **encima** del formulario, y el botón dice **Reenviar**.

- [ ] **Step 8: Commit**

```bash
git add pages/tareas.php
git commit -m "feat(tareas): modal de detalle con evidencia, subida a Drive y envio"
```

---

## Task 18: Panel de revisión, prórroga e historial

Se engancha en el `#tkRevSlot` que la Task 17 dejó preparado, mediante la función `window.tkPintarRevision`. Solo se carga para el Administrador.

**Files:**
- Modify: `pages/tareas.php` (CSS y un `<script>` nuevo)

- [ ] **Step 1: Añadir el CSS**

Añade al final del `<style>`:

```css
    /* Revisión */
    .tk-ver { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
    .tk-ver button { padding:14px 10px; border-radius:12px; border:2px solid var(--co-line-bold);
      background:#fff; cursor:pointer; font-family:inherit; font-weight:700; font-size:13px;
      color:var(--co-mute); transition:all .15s; }
    .tk-ver button .d { display:block; font-size:10px; font-weight:600; margin-top:3px;
      color:var(--co-faint); letter-spacing:0; }
    .tk-ver button:hover { transform:translateY(-1px); }
    .tk-ver button.on[data-v="aprobada"]  { border-color:var(--ok); background:var(--ok-bg); color:var(--ok); }
    .tk-ver button.on[data-v="observada"] { border-color:var(--wn); background:var(--wn-bg); color:var(--wn); }
    .tk-ver button.on[data-v="rechazada"] { border-color:var(--er); background:var(--er-bg); color:var(--er); }

    .tk-notas { display:flex; gap:7px; flex-wrap:wrap; }
    .tk-notas button { flex:1; min-width:88px; padding:10px 6px; border-radius:10px;
      border:1px solid var(--co-line-bold); background:#fff; cursor:pointer; font-family:inherit;
      font-size:12px; font-weight:600; color:var(--co-mute); }
    .tk-notas button .n { display:block; font-size:17px; font-weight:800; color:var(--wn); }
    .tk-notas button.on { border-color:var(--wn); background:var(--wn-bg); color:var(--wn); }

    /* Línea de tiempo */
    .tk-tl { position:relative; padding-left:20px; }
    .tk-tl::before { content:''; position:absolute; left:5px; top:5px; bottom:5px; width:2px;
      background:var(--co-line); }
    .tk-tl-i { position:relative; padding-bottom:14px; }
    .tk-tl-i::before { content:''; position:absolute; left:-19px; top:4px; width:10px; height:10px;
      border-radius:50%; background:#fff; border:2px solid var(--co-navy-700); }
    .tk-tl-i .ac { font-size:12px; font-weight:700; }
    .tk-tl-i .dt { font-size:11px; color:var(--co-faint); }
    .tk-tl-i .de { font-size:12px; color:var(--co-mute); margin-top:3px; white-space:pre-wrap; }
```

- [ ] **Step 2: Añadir el script**

Inserta otro `<script>` después del de la Task 17:

```php
<?php if ($ES_ADMIN): ?>
<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const esc = window.tkEsc, fmt = window.tkFmt;
  const ESCALA = <?= json_encode($JS_ESCALA, JSON_UNESCAPED_UNICODE) ?>;

  let T = null, veredicto = null, nota = null;

  /** La llama pintar() del modal de detalle en cada repintado. */
  function pintarRevision(tarea) {
    T = tarea; veredicto = null; nota = tarea.nota || null;
    const slot = $('tkRevSlot');
    if (!slot) return;

    let html = '';

    // ── Prórroga suelta: solo sobre tareas abiertas ──
    if (T.permisos.prorrogar) {
      html += `<div class="tk-sec">
        <h5>Plazo · 2ª fecha</h5>
        <div class="tk-row2">
          <div class="tk-field">
            <label>Nueva fecha de entrega</label>
            <input id="tkP-fecha" type="datetime-local" value="${T.fecha_limite_2 ? String(T.fecha_limite_2).slice(0,16).replace(' ','T') : ''}">
            <span class="hint">Debe ser posterior al ${fmt(T.fecha_limite)}.</span>
          </div>
          <div class="tk-field">
            <label>Motivo</label>
            <input id="tkP-motivo" type="text" maxlength="255" value="${esc(T.prorroga_motivo || '')}" placeholder="Por qué se amplía el plazo">
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:10px">
          <button class="tk-btn" id="tkP-guardar">Conceder prórroga</button>
          ${T.fecha_limite_2 ? `<button class="tk-btn danger" id="tkP-retirar">Retirar prórroga</button>` : ''}
        </div>
      </div>`;
    }

    // ── Veredicto: solo cuando la tarea está entregada ──
    if (T.permisos.revisar) {
      html += `<div class="tk-sec">
        <h5>Revisión</h5>
        <div class="tk-ver" id="tkV">
          <button data-v="aprobada">Aprobar<span class="d">Cierra la tarea</span></button>
          <button data-v="observada">Observar<span class="d">Vuelve al asignado</span></button>
          <button data-v="rechazada">Rechazar<span class="d">Cierra sin aprobar</span></button>
        </div>

        <div class="tk-field" style="margin-top:14px">
          <label>Nota</label>
          <div class="tk-notas" id="tkN">
            ${Object.entries(ESCALA).map(([k, v]) =>
              `<button data-n="${k}" class="${String(nota) === String(k) ? 'on' : ''}"><span class="n">${k}</span>${esc(v)}</button>`
            ).join('')}
          </div>
          <span class="hint">Obligatoria para aprobar y para rechazar. Opcional al observar.</span>
        </div>

        <div class="tk-field" style="margin-top:12px">
          <label>Comentario</label>
          <textarea id="tkC" placeholder="Qué estuvo bien, qué falta o por qué se rechaza.">${esc(T.comentario_admin || '')}</textarea>
          <span class="hint">Obligatorio al observar y al rechazar.</span>
        </div>

        <div id="tkV2" style="display:none;margin-top:12px" class="tk-row2">
          <div class="tk-field">
            <label>2ª fecha para el reenvío (opcional)</label>
            <input id="tkV2-fecha" type="datetime-local">
          </div>
          <div class="tk-field">
            <label>Motivo de la prórroga</label>
            <input id="tkV2-motivo" type="text" maxlength="255" placeholder="Obligatorio si pones 2ª fecha">
          </div>
        </div>

        <button class="tk-btn primary" id="tkV-guardar" style="margin-top:14px" disabled>Elige un veredicto</button>
      </div>`;
    } else if (T.revisado_at) {
      // Ya revisada: se muestra el resultado, no el formulario.
      html += `<div class="tk-sec">
        <h5>Resultado de la revisión</h5>
        <div class="tk-meta">
          <div><div class="k">Veredicto</div><div class="v">${esc(T.estado)}</div></div>
          <div><div class="k">Nota</div><div class="v">${T.nota ? T.nota + ' · ' + esc(T.nota_label) : '—'}</div></div>
          <div><div class="k">Revisó</div><div class="v">${esc(T.revisado_por || '—')}</div></div>
          <div><div class="k">Fecha</div><div class="v">${fmt(T.revisado_at)}</div></div>
        </div>
        ${T.comentario_admin ? `<p style="margin:12px 0 0;font-size:13px;white-space:pre-wrap">${esc(T.comentario_admin)}</p>` : ''}
      </div>`;
    }

    // ── Bitácora ──
    if (T.historial && T.historial.length) {
      html += `<div class="tk-sec"><h5>Historial</h5><div class="tk-tl">`
        + T.historial.map(h => `<div class="tk-tl-i">
            <div class="ac">${esc(h.accion_label)}</div>
            <div class="dt">${fmt(h.created_at)} · ${esc(h.usuario_nombre || '—')} (${esc(h.usuario_rol_label || '—')})</div>
            ${h.detalle ? `<div class="de">${esc(h.detalle)}</div>` : ''}
          </div>`).join('')
        + `</div></div>`;
    }

    slot.innerHTML = html;
    conectar();
  }

  function conectar() {
    if ($('tkV')) {
      $('tkV').addEventListener('click', e => {
        const b = e.target.closest('button[data-v]'); if (!b) return;
        veredicto = b.dataset.v;
        $('tkV').querySelectorAll('button').forEach(x => x.classList.remove('on'));
        b.classList.add('on');
        // La 2ª fecha solo tiene sentido al devolver: si la tarea se cierra,
        // el plazo deja de existir.
        $('tkV2').style.display = (veredicto === 'observada') ? '' : 'none';
        const g = $('tkV-guardar');
        g.disabled = false;
        g.textContent = { aprobada: 'Aprobar tarea', observada: 'Devolver con observaciones',
                          rechazada: 'Rechazar tarea' }[veredicto];
      });
    }
    if ($('tkN')) {
      $('tkN').addEventListener('click', e => {
        const b = e.target.closest('button[data-n]'); if (!b) return;
        // Volver a pulsar la misma nota la quita: al observar puede no haberla.
        nota = (String(nota) === b.dataset.n) ? null : Number(b.dataset.n);
        $('tkN').querySelectorAll('button').forEach(x => x.classList.remove('on'));
        if (nota) b.classList.add('on');
      });
    }
    if ($('tkV-guardar')) $('tkV-guardar').addEventListener('click', revisar);
    if ($('tkP-guardar')) $('tkP-guardar').addEventListener('click', () => prorrogar(false));
    if ($('tkP-retirar')) $('tkP-retirar').addEventListener('click', () => prorrogar(true));
  }

  function normalizaFecha(v) {
    if (!v) return '';
    let f = v.replace('T', ' ');
    if (f.endsWith(' 00:00')) f = f.slice(0, 11) + '23:59';
    return f;
  }

  async function revisar() {
    const payload = {
      id: T.id, veredicto, nota,
      comentario: ($('tkC')?.value || '').trim(),
    };
    if (veredicto === 'observada') {
      const f = normalizaFecha($('tkV2-fecha')?.value || '');
      if (f) { payload.fecha_limite_2 = f; payload.prorroga_motivo = ($('tkV2-motivo')?.value || '').trim(); }
    }
    const btn = $('tkV-guardar');
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const res  = await fetch('../api/revisar_tarea.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!data.success) { window.tkToast(data.error, 'error'); btn.disabled = false; return; }
      window.tkToast({ aprobada: 'Tarea aprobada', observada: 'Devuelta al asignado',
                       rechazada: 'Tarea rechazada' }[veredicto]);
      window.tkCerrarDet();
      window.tkRecargar();
    } catch (e) {
      window.tkToast('Error de red', 'error'); btn.disabled = false;
    }
  }

  async function prorrogar(retirar) {
    const payload = retirar
      ? { id: T.id, fecha_limite_2: null }
      : { id: T.id,
          fecha_limite_2: normalizaFecha($('tkP-fecha')?.value || ''),
          motivo: ($('tkP-motivo')?.value || '').trim() };
    if (!retirar && !payload.fecha_limite_2) {
      window.tkToast('Indica la nueva fecha de entrega', 'error'); return;
    }
    try {
      const res  = await fetch('../api/prorrogar_tarea.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!data.success) { window.tkToast(data.error, 'error'); return; }
      window.tkToast(retirar ? 'Prórroga retirada' : 'Prórroga concedida');
      window.abrirDetalle(T.id);   // repinta con el plazo nuevo
      window.tkRecargar();
    } catch (e) { window.tkToast('Error de red', 'error'); }
  }

  window.tkPintarRevision = pintarRevision;
})();
</script>
<?php endif; ?>
```

- [ ] **Step 3: Verificar la sintaxis**

Run: `php -l pages/tareas.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Revisar una tarea desde la interfaz**

Como administrador, abre una tarea **En revisión**:
1. El botón de guardar sale deshabilitado con el texto `Elige un veredicto`.
2. Pulsa **Aprobar** sin nota y guarda → toast rojo `Pon una nota del 1 al 5 para aprobar la tarea.`
3. Elige nota 4 y aprueba. Expected: toast `Tarea aprobada`, el modal se cierra, y en la tabla la fila muestra ★★★★☆ con `Satisfactorio`.

- [ ] **Step 5: Observar con 2ª fecha desde la interfaz**

Abre otra tarea entregada, pulsa **Observar**. Expected: aparecen los campos de 2ª fecha (que estaban ocultos con «Aprobar»). Escribe el comentario, pon una 2ª fecha **sin** motivo y guarda → error del servidor. Añade el motivo y guarda.

Comprueba en la tabla que la fila muestra la fecha nueva con la 1ª tachada al lado.

- [ ] **Step 6: Verificar el historial**

Vuelve a abrir esa tarea. Expected: la línea de tiempo lista, en orden, `Tarea creada` → `Entrega enviada` → `Devuelta con observaciones` → `Prórroga concedida`, cada uno con quién y cuándo, y el evento de observación con el comentario anterior conservado en su detalle.

- [ ] **Step 7: Verificar que el coordinador no ve nada de esto**

Entra con `coord.test` y abre cualquier tarea. Expected: no hay panel de revisión, ni bloque de prórroga, ni línea de tiempo — el `<script>` entero está dentro de `if ($ES_ADMIN)`, así que ni siquiera se descarga.

- [ ] **Step 8: Commit**

```bash
git add pages/tareas.php
git commit -m "feat(tareas): panel de revision con nota, prorroga e historial"
```

---

## Task 19: Exportaciones

Mismo patrón que Sugerencias y Capacitaciones: **una única `listaVisible()`** alimenta tabla, Excel y PDF, así que las exportaciones heredan los filtros sin código adicional.

**Files:**
- Modify: `pages/tareas.php` (un `<script>` nuevo)

- [ ] **Step 1: Añadir el script**

Inserta otro `<script>` al final, antes de `</body>`:

```html
<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const fmt = window.tkFmt;

  function filas() {
    return window.tkListaVisible().map(t => [
      t.titulo,
      t.asignado_nombre,
      t.asignado_rol_label,
      fmt(t.fecha_limite),
      t.fecha_limite_2 ? fmt(t.fecha_limite_2) : '',
      fmt(t.plazo_vigente),
      t.enviado_at ? fmt(t.enviado_at) : '',
      t.entregada_tarde ? 'Sí' : '',
      t.atrasada ? (t.dias_atraso > 0 ? t.dias_atraso + ' días' : 'Sí') : '',
      t.estado,
      t.nota ? t.nota + ' · ' + t.nota_label : '',
      t.entregas_count,
    ]);
  }

  const CABECERAS = ['Tarea','Asignado','Puesto','1ª fecha','2ª fecha','Plazo vigente',
                     'Entregado','Fuera de plazo','Atrasada','Estado','Nota','Envíos'];

  /* CSV con BOM y separador ';': es lo que abre Excel en español sin pedir
     un asistente de importación. */
  function excel() {
    const rows = filas();
    if (!rows.length) { window.tkToast('No hay filas que exportar', 'error'); return; }
    const q = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const csv = '﻿' + [CABECERAS, ...rows].map(r => r.map(q).join(';')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'tareas_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
  }

  function pdf() {
    const rows = filas();
    if (!rows.length) { window.tkToast('No hay filas que exportar', 'error'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

    if (window.tkLogo) { try { doc.addImage(window.tkLogo, 'PNG', 40, 24, 34, 34); } catch (e) {} }
    doc.setFontSize(15); doc.setTextColor(0, 92, 61);
    doc.text('Control de Tareas', 84, 42);
    doc.setFontSize(9); doc.setTextColor(120);
    doc.text('Generado el ' + new Date().toLocaleString('es-PE') + ' · ' + rows.length + ' tareas', 84, 56);

    doc.autoTable({
      head: [CABECERAS],
      body: rows,
      startY: 74,
      styles: { fontSize: 7.5, cellPadding: 4 },
      headStyles: { fillColor: [0, 135, 90], textColor: 255, fontStyle: 'bold' },
      alternateRowStyles: { fillColor: [245, 248, 247] },
      // Las atrasadas en rojo: el PDF se imprime y se reparte, y el atraso
      // tiene que saltar a la vista igual que en pantalla.
      didParseCell: (d) => {
        if (d.section === 'body' && d.row.raw[8]) d.cell.styles.textColor = [220, 38, 38];
      },
    });

    doc.save('tareas_' + new Date().toISOString().slice(0, 10) + '.pdf');
  }

  $('btnExcel').addEventListener('click', excel);
  $('btnPdf').addEventListener('click', pdf);
})();
</script>
```

- [ ] **Step 2: Verificar la sintaxis**

Run: `php -l pages/tareas.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Verificar que las exportaciones respetan los filtros**

Como administrador:
1. Sin filtros, pulsa **Excel**. Abre el CSV y cuenta las filas: deben ser las mismas que hay en la tabla.
2. Pulsa el chip **Atrasadas** y exporta de nuevo. El CSV ahora debe traer **solo** las atrasadas.
3. Pulsa **PDF** con ese mismo filtro: las filas deben salir en rojo.
4. Filtra por una persona y un mes, exporta y confirma que coincide con lo que se ve.

Si Excel y la tabla discrepan, algo está llamando a `tareas` en vez de a `listaVisible()`.

- [ ] **Step 4: Commit**

```bash
git add pages/tareas.php
git commit -m "feat(tareas): exportacion a Excel y PDF sobre las filas visibles"
```

---

# FASE 4 · Verificación de aceptación

## Task 20: Recorrer los 18 puntos del spec

Las tareas anteriores verificaron cada pieza según se construía. Esta recorre la lista de aceptación del [spec](../specs/2026-07-30-tareas-design.md#verificación) de una sentada y sobre datos limpios, que es distinto: los fallos de integración salen cuando el ciclo se recorre entero.

**Files:** ninguno — solo verificación.

- [ ] **Step 1: Partir de datos limpios**

```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "DELETE FROM tareas;"
```

Los adjuntos y el historial se van en cascada. Los usuarios de prueba se conservan.

- [ ] **Step 2: Idempotencia de la migración (spec 1)**

```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system < sql/029_tareas.sql
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system < sql/029_tareas.sql
```
Expected: dos ejecuciones sin error.

- [ ] **Step 3: Aislamiento del rol Soporte (spec 2)**

Entra como `soporte.test` y visita estas URLs:

| URL | Esperado |
|---|---|
| `pages/tareas.php` | Carga |
| `index.php` | Carga |
| `pages/incidencias.php` | `403 · No tienes permisos…` |
| `pages/capacitaciones.php` | `403` |
| `pages/reporte_inspeccion.php` | `403` |
| `pages/operaciones.php` | `403` |
| `pages/usuarios.php` | `403` |

Y su sidebar solo tiene Turno Actual, Tareas y Cerrar sesión.

- [ ] **Step 4: Validación del coordinador a cargo (spec 3)**

Ya cubierto en la Task 4, Step 9. Repítelo rápido: crear un Soporte sin coordinador se rechaza; cambiarlo a Coordinador pone `soporte_de_id` a `NULL`.

- [ ] **Step 5: 403 en los endpoints de administrador (spec 4)**

```bash
for ep in revisar_tarea prorrogar_tarea save_tarea delete_tarea; do
  printf "%-18s " "$ep"
  curl -s -o /dev/null -w "%{http_code}\n" -b /tmp/ck-coord.txt -X POST \
    http://localhost/portallyman.online/api/$ep.php \
    -H "Content-Type: application/json" -d '{"id":1}'
done
```
Expected: `403` en los cuatro.

- [ ] **Step 6: Crear el lote (spec 6)**

```bash
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/save_tarea.php \
  -H "Content-Type: application/json" \
  -d '{"titulo":"Checklist de precintos","descripcion":"Contar y fotografiar.","prioridad":"alta","fecha_limite":"2026-08-15 23:59","destinatarios":[7,9]}'
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "SELECT id, lote_id, estado, entregas_count, plazo_al_enviar FROM tareas;"
```
Expected: dos filas, mismo `lote_id`, `estado='pendiente'`, `entregas_count=0`, `plazo_al_enviar=NULL`.

- [ ] **Step 7: Visibilidad (spec 5)**

```bash
for c in coord coord2 soporte; do
  printf "%-8s " $c
  curl -s -b /tmp/ck-$c.txt "http://localhost/portallyman.online/api/get_tareas.php" \
    | grep -o '"asignado_nombre":"[^"]*"' | sort -u | tr '\n' ' '; echo
done
```
Expected: `coord` → dos nombres (el suyo y el de su soporte). `coord2` → vacío. `soporte` → solo el suyo.

- [ ] **Step 8: Atraso derivado y prórroga (spec 7 y 8)**

```bash
ID=$(c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -N -e "SELECT MIN(id) FROM tareas;")
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "UPDATE tareas SET fecha_limite='2026-07-01 23:59:00' WHERE id=$ID;"

echo -n "atrasada antes:   "; curl -s -b /tmp/ck-admin.txt "http://localhost/portallyman.online/api/get_tarea.php?id=$ID" | grep -o '"atrasada":[a-z]*'
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/prorrogar_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$ID,\"fecha_limite_2\":\"2026-09-15 23:59\",\"motivo\":\"Nave en muelle.\"}" > /dev/null
echo -n "atrasada despues: "; curl -s -b /tmp/ck-admin.txt "http://localhost/portallyman.online/api/get_tarea.php?id=$ID" | grep -o '"atrasada":[a-z]*'
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/prorrogar_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$ID,\"fecha_limite_2\":null}" > /dev/null
echo -n "tras retirar:     "; curl -s -b /tmp/ck-admin.txt "http://localhost/portallyman.online/api/get_tarea.php?id=$ID" | grep -o '"atrasada":[a-z]*'
```
Expected: `true` → `false` → `true`, **sin que haya corrido ningún proceso programado**.

- [ ] **Step 9: Validaciones de la 2ª fecha en servidor (spec 9)**

```bash
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/prorrogar_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$ID,\"fecha_limite_2\":\"2026-06-01 10:00\",\"motivo\":\"x\"}"
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/prorrogar_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$ID,\"fecha_limite_2\":\"2026-09-15 10:00\"}"
```
Expected: `La 2ª fecha debe ser posterior…` y `Indica el motivo de la prórroga…`.

- [ ] **Step 10: Guarda de entrega (spec 11)**

```bash
curl -s -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/enviar_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$ID}"
curl -s -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/enviar_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$ID,\"comentario\":\"412 precintos contados.\"}"
```
Expected: la primera se rechaza; la segunda devuelve `"success":true` con `"entregada_tarde":true`.

- [ ] **Step 11: La prórroga posterior no borra la entrega tardía (spec 10)**

```bash
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/prorrogar_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$ID,\"fecha_limite_2\":\"2026-12-01 23:59\",\"motivo\":\"x\"}"
curl -s -b /tmp/ck-admin.txt "http://localhost/portallyman.online/api/get_tarea.php?id=$ID" | grep -o '"entregada_tarde":[a-z]*'
```
Expected: la prórroga se **rechaza** (la tarea está `entregada`, no abierta) y `entregada_tarde` sigue en `true`. Este es el punto que justifica `plazo_al_enviar`.

- [ ] **Step 12: Guardas del veredicto (spec 13)**

```bash
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/revisar_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$ID,\"veredicto\":\"aprobada\"}"
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/revisar_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$ID,\"veredicto\":\"observada\"}"
```
Expected: `Pon una nota del 1 al 5 para aprobar la tarea.` y `Escribe qué debe corregir antes de devolver la tarea.`

- [ ] **Step 13: Ciclo de observación y reenvío (spec 12)**

```bash
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/revisar_tarea.php \
  -H "Content-Type: application/json" \
  -d "{\"id\":$ID,\"veredicto\":\"observada\",\"nota\":3,\"comentario\":\"Falta la foto del anaquel B.\"}"

echo "conteo" > /tmp/ev2.txt
curl -s -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/upload_tarea_file.php \
  -F "id=$ID" -F "origen=asignado" -F "file=@/tmp/ev2.txt" | grep -o '"entrega_nro":[0-9]*'

curl -s -b /tmp/ck-coord.txt -X POST http://localhost/portallyman.online/api/enviar_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$ID,\"comentario\":\"Foto agregada.\"}" | grep -o '"entregas_count":[0-9]*'

curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/revisar_tarea.php \
  -H "Content-Type: application/json" \
  -d "{\"id\":$ID,\"veredicto\":\"aprobada\",\"nota\":4,\"comentario\":\"Conforme.\"}"

c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "SELECT accion, LEFT(detalle,90) FROM tareas_historial WHERE tarea_id=$ID ORDER BY id;"
```
Expected: `"entrega_nro":2`, `"entregas_count":2`, y en el historial un evento `aprobada` cuyo detalle contiene `Comentario anterior (sustituido): Falta la foto del anaquel B.`

- [ ] **Step 14: No se edita ni se revisa lo cerrado (spec 14)**

```bash
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/save_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$ID,\"titulo\":\"Cambiado\",\"fecha_limite\":\"2026-10-01 23:59\"}"
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/revisar_tarea.php \
  -H "Content-Type: application/json" -d "{\"id\":$ID,\"veredicto\":\"rechazada\",\"nota\":1,\"comentario\":\"x\"}"
```
Expected: `La tarea ya fue revisada y no admite cambios.` y `La tarea ya fue revisada.`

- [ ] **Step 15: `aplicar_a_lote` solo alcanza a las pendientes (spec 14)**

La otra tarea del lote sigue `pendiente`. Edítala pidiendo aplicar al lote y comprueba que la aprobada **no** cambia:

```bash
OTRA=$(c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -N -e "SELECT id FROM tareas WHERE estado='pendiente' LIMIT 1;")
curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/save_tarea.php \
  -H "Content-Type: application/json" \
  -d "{\"id\":$OTRA,\"titulo\":\"Checklist de precintos v2\",\"fecha_limite\":\"2026-08-20 23:59\",\"aplicar_a_lote\":true}"
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "SELECT id, estado, titulo FROM tareas;"
```
Expected: solo la fila `pendiente` cambia de título. La `aprobada` conserva `Checklist de precintos`.

- [ ] **Step 16: Adjuntos con Drive caído (spec 15)**

Fuerza el fallo editando temporalmente [drive_config.php:17](../../../includes/drive_config.php#L17) para que la URL no resuelva:

```php
    define('DRIVE_APPS_SCRIPT_URL', getenv('DRIVE_APPS_SCRIPT_URL') ?: 'https://script.google.invalid/exec');
```

Sube un archivo desde el modal de detalle de una tarea abierta y comprueba:

```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system \
  -e "SELECT nombre_archivo, estado, ruta_local, LEFT(error_msg,60) AS err FROM tareas_adjuntos ORDER BY id DESC LIMIT 1;"
ls -la uploads/sugerencias/tareas/
```

El respaldo local **no** va a `uploads/Tareas`: `sg_guardar_local()` cuelga todo de `SG_UPLOAD_DIR` (`uploads/sugerencias`) y pasa el nombre de la carpeta por un slug en minúsculas, así que la ruta real es `uploads/sugerencias/tareas/`. Ese directorio se crea solo y queda cerrado a la web por el `.htaccess` que el propio helper deja ahí.

Expected: `estado='pendiente'`, `ruta_local` poblada, `err` con el fallo de red, y el archivo realmente presente en disco. En la interfaz debe salir el aviso «No se pudo subir a Drive… (guardado en el servidor, se subirá luego)» y el archivo listado con la etiqueta `EN EL SERVIDOR`.

**Restaura la línea original de `drive_config.php` antes de continuar.**

- [ ] **Step 17: La tarea sobrevive al borrado del usuario (spec 16)**

Primero crea una tarea para `coord2.test`, y solo después borra al usuario. Al revés no se prueba nada.

```bash
C2=$(c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -N \
     -e "SELECT id FROM usuarios WHERE email='coord2.test@portally.local';")

TC2=$(curl -s -b /tmp/ck-admin.txt -X POST http://localhost/portallyman.online/api/save_tarea.php \
  -H "Content-Type: application/json" \
  -d "{\"titulo\":\"Tarea de usuario que se borrara\",\"fecha_limite\":\"2026-11-01 23:59\",\"destinatarios\":[$C2]}" \
  | php -r "echo json_decode(file_get_contents('php://stdin'),true)['ids'][0];")

c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system \
  -e "DELETE FROM usuarios WHERE id=$C2;"

c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system \
  -e "SELECT id, asignado_id, asignado_nombre, asignado_rol FROM tareas WHERE id=$TC2;"
```
Expected: la fila sigue existiendo, con `asignado_id = NULL` y `asignado_nombre` / `asignado_rol` legibles. El borrado del usuario no se bloquea ni arrastra la tarea.

Comprueba también que el listado no revienta con ese `NULL`:

```bash
curl -s -o /dev/null -w "%{http_code}\n" -b /tmp/ck-admin.txt \
  "http://localhost/portallyman.online/api/get_tareas.php"
```
Expected: `200`. Y en la interfaz, esa fila muestra el nombre congelado con normalidad.

- [ ] **Step 18: Borrado en cascada (spec 17)**

Ya cubierto en la Task 14, Step 3.

- [ ] **Step 19: Exportaciones filtradas (spec 18)**

Ya cubierto en la Task 19, Step 3. Repítelo con el estado final de los datos.

- [ ] **Step 20: Correr los tests del catálogo una última vez**

Run: `php tests/tareas_catalogo_test.php`
Expected: `TODO OK`. Nada de lo implementado debe haber roto las funciones puras.

- [ ] **Step 21: Verificar la sintaxis de todo lo tocado**

Run:
```bash
for f in includes/tareas_catalogo.php includes/auth.php includes/sidebar.php includes/header.php \
         pages/tareas.php pages/usuarios.php \
         api/get_asignables.php api/get_tareas.php api/get_tarea.php api/save_tarea.php \
         api/enviar_tarea.php api/revisar_tarea.php api/prorrogar_tarea.php \
         api/upload_tarea_file.php api/delete_tarea_adjunto.php api/delete_tarea.php \
         api/save_usuario.php api/get_usuarios.php; do
  php -l $f
done
```
Expected: `No syntax errors detected` en los 18.

- [ ] **Step 22: Limpiar los datos de prueba**

Incluye la cuenta `admin.dev.tareas@portally.local` creada durante la ejecución
de este plan para tener una sesión de administrador con la que probar la API
(ver `HANDOFF-TAREAS.md`). No es un usuario `.test`, así que el primer `DELETE`
no la alcanza — hay que nombrarla aparte.

```bash
c:/xampp2026/mysql/bin/mysql.exe -uportally_sa -pSistemas2100* portally_system -e "DELETE FROM tareas; DELETE FROM usuarios WHERE email LIKE '%.test@portally.local' OR email='admin.dev.tareas@portally.local';"
rm -f /tmp/ck-*.txt /tmp/evidencia.txt /tmp/ev2.txt /tmp/malo.exe
```

- [ ] **Step 23: Commit final**

```bash
git add -A
git commit -m "feat(tareas): modulo de Tareas completo con rol Tally Soporte"
```

---

## Notas para quien despliegue esto

- **`sql/029_tareas.sql` hay que correrlo también en producción**, sobre la base ya seleccionada en phpMyAdmin. Es idempotente.
- **Los adjuntos van a la subcarpeta `Tareas`** de la carpeta raíz de Drive. Se crea sola la primera vez que el Apps Script recibe un archivo; no hay que prepararla a mano.
- **No hay notificaciones.** El asignado se entera de que tiene una tarea cuando entra al portal. Está declarado fuera de alcance en el spec, pero conviene decirlo en la capacitación del módulo, porque es la primera pregunta que va a salir.
- **Los usuarios Soporte hay que crearlos** desde Usuarios antes de poder asignarles nada. El selector de destinatarios solo muestra Coordinadores y Soportes **activos**.
