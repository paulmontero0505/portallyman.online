/**
 * TALLYMAN CONTROL - Receptor de adjuntos de Sugerencias hacia Google Drive
 *
 * Web App de Apps Script. El servidor PHP (includes/drive_uploader.php) le
 * envia un POST JSON con el archivo en base64; este script lo escribe en la
 * subcarpeta del canal, dentro de la carpeta raiz compartida.
 *
 * DESPLIEGUE (una sola vez):
 *   1. script.google.com -> Nuevo proyecto.
 *   2. En el editor, presiona Ctrl+A y luego Supr para BORRAR el contenido
 *      por defecto (function myFunction() {...}). Recien ahi pega este archivo.
 *   3. Cambia SECRETO por una cadena larga y aleatoria.
 *   4. Guarda (Ctrl+S) y ejecuta la funcion probarAcceso para autorizar Drive.
 *   5. Implementar -> Nueva implementacion -> Tipo: Aplicacion web.
 *        - Ejecutar como:      Yo (tu cuenta, la duena de la carpeta)
 *        - Quien tiene acceso: Cualquier persona
 *   6. Copia la URL /exec y ponla en includes/drive_config.php,
 *      junto con el mismo SECRETO.
 *
 * ANONIMATO: este script nunca recibe DNI ni nombre del colaborador. El
 * nombre del archivo es solo la fecha y hora de subida.
 */

// Cambia esto por una cadena larga y aleatoria, y usa la MISMA en PHP.
var SECRETO = 'c0934ee5bdedb112e2eeb75fa6e75f51407baf503cea6965acf4192a410bc84f';

// Carpeta raiz de Drive donde viven las subcarpetas por canal.
var RAIZ_ID = '1XoA32V9CW4x8V5dW-bokC5Zqz9DlMdyv';


function doPost(e) {
  try {
    if (!e || !e.postData || !e.postData.contents) {
      return responder({ ok: false, error: 'Sin cuerpo en la peticion.' });
    }

    var req = JSON.parse(e.postData.contents);

    if (!req.secreto || req.secreto !== SECRETO) {
      return responder({ ok: false, error: 'No autorizado.' });
    }
    if (!req.carpeta || !req.nombre || !req.contenido) {
      return responder({ ok: false, error: 'Faltan campos obligatorios.' });
    }

    var raiz = DriveApp.getFolderById(req.raizId || RAIZ_ID);
    var carpeta = obtenerOCrearCarpeta(raiz, String(req.carpeta));

    var bytes = Utilities.base64Decode(req.contenido);
    var mime = req.mime || 'application/octet-stream';
    var nombre = nombreDisponible(carpeta, String(req.nombre));

    var blob = Utilities.newBlob(bytes, mime, nombre);
    var archivo = carpeta.createFile(blob);

    return responder({
      ok: true,
      fileId: archivo.getId(),
      url: archivo.getUrl(),
      nombre: archivo.getName()
    });
  } catch (err) {
    return responder({ ok: false, error: String(err && err.message ? err.message : err) });
  }
}


// Devuelve la subcarpeta por nombre; la crea si no existe.
function obtenerOCrearCarpeta(raiz, nombre) {
  // Un lock evita que dos subidas simultaneas creen la carpeta por duplicado.
  var lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    var it = raiz.getFoldersByName(nombre);
    return it.hasNext() ? it.next() : raiz.createFolder(nombre);
  } finally {
    lock.releaseLock();
  }
}


// Si ya existe un archivo con ese nombre, anade un sufijo -2, -3, etc.
function nombreDisponible(carpeta, nombre) {
  if (!carpeta.getFilesByName(nombre).hasNext()) return nombre;

  var punto = nombre.lastIndexOf('.');
  var base = punto > 0 ? nombre.substring(0, punto) : nombre;
  var ext = punto > 0 ? nombre.substring(punto) : '';

  for (var i = 2; i < 100; i++) {
    var intento = base + '-' + i + ext;
    if (!carpeta.getFilesByName(intento).hasNext()) return intento;
  }
  return base + '-' + Utilities.getUuid().substring(0, 8) + ext;
}


function responder(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}


// Prueba manual desde el editor: confirma acceso y crea las 4 subcarpetas.
function probarAcceso() {
  var raiz = DriveApp.getFolderById(RAIZ_ID);
  Logger.log('Carpeta raiz: ' + raiz.getName());
  var canales = ['Observaciones', 'Consultas', 'Solicitudes', 'Propuestas de mejora'];
  for (var i = 0; i < canales.length; i++) {
    Logger.log('  subcarpeta: ' + obtenerOCrearCarpeta(raiz, canales[i]).getName());
  }
  Logger.log('OK: el script puede escribir en Drive.');
}
