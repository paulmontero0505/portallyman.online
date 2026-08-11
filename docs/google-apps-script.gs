/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Receptor de datos en Google Sheets (Apps Script Web App)
   ───────────────────────────────────────────────────────────────────────
   CÓMO USAR
     1) Abre tu Google Sheet → Extensiones → Apps Script.
     2) Borra el contenido y pega TODO este archivo.
     3) Cambia TOKEN por un secreto largo (el MISMO que pondrás en
        includes/sheets_config.php → SHEETS_TOKEN).
     4) Implementar → Nueva implementación → tipo "App web":
            · Ejecutar como: Yo
            · Quién tiene acceso: Cualquier persona con el enlace
     5) Copia la URL que termina en /exec → SHEETS_WEBAPP_URL en la config.

   Soporta modos: append | replace | upsert. Crea la pestaña si no existe,
   escribe encabezados con formato y congela la primera fila.
   ═══════════════════════════════════════════════════════════════════════ */

const TOKEN = '33186c536b3b72fff1d8585ffb418a1cd57ac425523a4035';  // == SHEETS_TOKEN en sheets_config.php

function doPost(e) {
  try {
    const body = JSON.parse((e && e.postData && e.postData.contents) || '{}');
    if (body.token !== TOKEN) return _json({ ok: false, error: 'token' });

    // ── Acción: subir un archivo a Drive (evidencias de incidencias) ──
    // Crea/halla una subcarpeta dentro de body.folderId y guarda el archivo.
    if (body.action === 'driveUpload') return _driveUpload(body);

    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const name = String(body.sheet || '').slice(0, 90);
    if (!name) return _json({ ok: false, error: 'sheet' });

    let sh = ss.getSheetByName(name);
    if (!sh) sh = ss.insertSheet(name);

    const header = body.header || [];
    const rows = body.rows || [];
    const mode = body.mode || 'append';

    // Encabezado solo si la hoja está vacía.
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
      const keyCols = (body.keyCols && body.keyCols.length) ? body.keyCols : [1]; // 1-based
      const wrote = _upsert(sh, header.length > 0, rows, keyCols);
      return _json({ ok: true, wrote: wrote });
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
  const width = sh.getLastColumn();
  const existing = (last >= startRow && width > 0)
    ? sh.getRange(startRow, 1, last - startRow + 1, width).getValues() : [];
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

/* ── Subida de archivo a Drive ──────────────────────────────────────────
   body = { action:'driveUpload', token, folderId, folder, filename, mimeType, content(base64) }
   · folderId : ID de la carpeta raíz (la compartida).
   · folder   : nombre de la subcarpeta (se crea si no existe). Ej: "NOMBRE - 2026-06-04".
   Devuelve { ok:true, url, id }.
   NOTA: al pegar esto y reimplementar, Apps Script pedirá AUTORIZAR Drive. */
function _driveUpload(body) {
  try {
    const parentId = String(body.folderId || '').trim();
    if (!parentId) return _json({ ok: false, error: 'folderId' });
    const subName = String(body.folder || 'General').slice(0, 240).replace(/[\\/:*?"<>|]/g, ' ').trim() || 'General';
    const fileName = String(body.filename || 'archivo').slice(0, 240);
    const mime = body.mimeType || 'application/octet-stream';
    if (!body.content) return _json({ ok: false, error: 'content' });

    const parent = DriveApp.getFolderById(parentId);
    const it = parent.getFoldersByName(subName);
    const sub = it.hasNext() ? it.next() : parent.createFolder(subName);

    const blob = Utilities.newBlob(Utilities.base64Decode(body.content), mime, fileName);
    const file = sub.createFile(blob);
    try { file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW); } catch (e2) {}

    return _json({ ok: true, url: file.getUrl(), id: file.getId(), folder: subName });
  } catch (err) {
    return _json({ ok: false, error: String(err) });
  }
}

function _json(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
