# Adjuntos de Sugerencias → Google Drive

Los 4 canales del buzón (Observaciones, Consultas, Solicitudes, Propuestas de mejora)
aceptan hasta **3 archivos** por envío, de **menos de 4 MB** cada uno. Los archivos se
suben a Google Drive mediante un **Web App de Apps Script**, que corre con tu cuenta
(la dueña de la carpeta), así que el servidor no maneja tokens de OAuth ni cuentas de
servicio.

Cada archivo se guarda en la subcarpeta de su canal y se nombra con la **fecha y hora
de subida**: `2026-07-09_14-32-05.pdf`. Si un envío trae varios archivos, se añade un
sufijo: `..._2.pdf`, `..._3.pdf`.

```
Carpeta raíz (1XoA32V9CW4x8V5dW-bokC5Zqz9DlMdyv)
├── Observaciones/        ← anónimo: el archivo no se vincula a ninguna identidad
├── Consultas/
├── Solicitudes/
└── Propuestas de mejora/
```
Las subcarpetas se crean solas la primera vez que llega un archivo de ese canal.

---

## 1. Base de datos

```bash
mysql -u<usuario> -p portally_system < sql/018_sugerencias_adjuntos.sql
```

## 2. Desplegar el Apps Script

1. Entra a [script.google.com](https://script.google.com) **con la cuenta dueña de la carpeta**.
2. Nuevo proyecto → pega el contenido de [`apps-script/SugerenciasDrive.gs`](../apps-script/SugerenciasDrive.gs).
3. Reemplaza `SECRETO` por una cadena larga y aleatoria. Guárdala, la necesitas en el paso 3.
4. (Opcional) Ejecuta la función `probarAcceso` para autorizar los permisos y verificar
   que el script ve la carpeta y crea las subcarpetas.
5. **Implementar → Nueva implementación → Aplicación web**:
   - *Ejecutar como*: **Yo** (tu cuenta)
   - *Quién tiene acceso*: **Cualquier persona**
6. Copia la URL. Debe terminar en **`/exec`** (no en `/dev`).

> El acceso "Cualquier persona" es necesario para que tu servidor PHP pueda llamarlo,
> pero el `SECRETO` impide que nadie más escriba en tu Drive.

## 3. Configurar el servidor

Edita [`includes/drive_config.php`](../includes/drive_config.php):

```php
define('DRIVE_APPS_SCRIPT_URL', 'https://script.google.com/macros/s/AKfy.../exec');
define('DRIVE_SHARED_SECRET',   'el-mismo-secreto-del-paso-2');
```

Ambos valores también se pueden inyectar por variables de entorno
(`DRIVE_APPS_SCRIPT_URL`, `DRIVE_SHARED_SECRET`), igual que las credenciales de `db.php`.

**Si vuelves a desplegar el script, usa "Gestionar implementaciones → editar → Nueva versión"**
para conservar la misma URL. Crear una implementación nueva genera otra URL.

---

## Qué pasa si Drive falla

La sugerencia **siempre se registra**. Nunca se pierde lo que escribió el colaborador.

| Situación | `sugerencias_adjuntos.estado` | Qué ocurre |
|---|---|---|
| Subida correcta | `subido` | Se guarda `drive_file_id` y `drive_url` |
| Drive caído / mal configurado | `pendiente` | Copia local en `uploads/sugerencias/<canal>/` |
| Ni Drive ni disco local | `error` | Se registra el motivo en `error_msg` |

El panel de administración marca en ámbar los adjuntos que no llegaron a Drive.

---

## Anonimato

El canal **Observaciones** es anónimo, y eso se respeta de punta a punta:

- El formulario reemplaza el nombre del colaborador por **MODO ANÓNIMO**.
- `guardar_sugerencia.php` descarta la identidad en el servidor, sin importar lo que
  mande el cliente (`colaborador_id`, `_nombre` y `_cargo` quedan `NULL`).
- La tabla `sugerencias_adjuntos` no guarda identidad alguna: solo se vincula al `id`
  de la sugerencia, que ya es anónima.
- El **nombre del archivo es solo la fecha y hora** — nunca el DNI ni el nombre.
- El Apps Script jamás recibe DNI ni nombre.

## Tipos permitidos

Imágenes (`jpg jpeg png webp gif heic`), PDF, documentos
(`doc docx xls xlsx ppt pptx txt csv`) y video (`mp4 webm mov 3gp`).

La validación compara la extensión **y el MIME real del contenido**, así que renombrar
un `.exe` a `.pdf` no lo cuela.
