<?php
require_once('../includes/auth.php');
require_admin(); // Definir campos es configuración del sistema: solo Administrador
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Operaciones · Campos por tipo · EstibaDeck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">
  <link rel="stylesheet" href="../css/operaciones.css">
</head>
<body>
<div class="overlay" id="overlay"></div>
<div class="shell">
  <?php $sb_base = '..'; include('../includes/sidebar.php'); ?>

  <div class="main-area">
    <?php include('../includes/header.php'); ?>

    <main class="content">
      <div class="op" id="opRoot">

        <section class="op-hero">
          <div>
            <span class="tag">⚙ Configuración</span>
            <h1>Campos por tipo de nave</h1>
            <p>Define qué información adicional se captura para cada tipo de nave. Estos campos arman automáticamente el formulario del detalle de cada nave.</p>
          </div>
          <a class="op-btn ghost-light" href="operaciones.php">← Volver a naves</a>
        </section>

        <div class="op-toolbar">
          <div class="op-field" style="margin:0;min-width:240px">
            <label>Tipo de nave</label>
            <select class="op-control" id="cmTipo"><option value="">Cargando…</option></select>
          </div>
          <button class="op-btn primary" id="cmNuevo" type="button" style="margin-left:auto;align-self:flex-end">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nuevo campo
          </button>
        </div>

        <div class="op-card">
          <div class="op-card-body" id="cmList"><div class="op-loading">Cargando…</div></div>
        </div>

      </div>

      <div class="op-modal" id="cmModal">
        <div class="op-dialog">
          <div class="op-dialog-head">
            <h3 id="cmModalTitle">Nuevo campo</h3>
            <button class="op-x" id="cmClose" type="button">✕</button>
          </div>
          <div class="op-dialog-body">
            <div class="op-form-grid">
              <div class="op-field">
                <label>Clave <span class="op-req">*</span></label>
                <input class="op-control" id="cm-clave" placeholder="ej. teus" autocomplete="off">
              </div>
              <div class="op-field">
                <label>Etiqueta <span class="op-req">*</span></label>
                <input class="op-control" id="cm-etiqueta" placeholder="ej. TEUs a bordo" autocomplete="off">
              </div>
              <div class="op-field">
                <label>Tipo de dato</label>
                <select class="op-control" id="cm-tipo_dato">
                  <option value="texto">Texto</option>
                  <option value="numero">Número</option>
                  <option value="fecha">Fecha</option>
                  <option value="booleano">Sí / No</option>
                  <option value="seleccion">Selección</option>
                </select>
              </div>
              <div class="op-field">
                <label>Orden</label>
                <input type="number" class="op-control" id="cm-orden" value="0">
              </div>
              <div class="op-field full" id="cm-opts-field" style="display:none">
                <label>Opciones (una por línea)</label>
                <textarea class="op-control" id="cm-opts" rows="4" placeholder="PA&#10;LR&#10;MH"></textarea>
              </div>
              <div class="op-field full">
                <label style="display:flex;flex-direction:row;align-items:center;gap:9px;color:var(--ink);font-size:13px;font-weight:600;cursor:pointer">
                  <input type="checkbox" id="cm-requerido" style="width:16px;height:16px"> Campo requerido
                </label>
              </div>
            </div>
            <p class="op-help">La <b>clave</b> identifica el campo en la base de datos y no se puede cambiar luego. La <b>etiqueta</b> es lo que ve el usuario en el formulario.</p>
          </div>
          <div class="op-dialog-foot">
            <button class="op-btn" id="cmCancel" type="button">Cancelar</button>
            <button class="op-btn primary" id="cmSave" type="button">Guardar</button>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script src="../js/operaciones.js"></script>
<script>
(function () {
  var $ = OP.$, esc = OP.esc, opApi = OP.opApi, toast = OP.toast;
  var tipos = [], campos = [], curTipo = '', editingId = null;

  function render() {
    var box = $('cmList');
    if (!curTipo) { box.innerHTML = '<div class="op-empty">Selecciona un tipo de nave.</div>'; return; }
    if (!campos.length) { box.innerHTML = '<div class="op-empty">Este tipo no tiene campos. Crea el primero con “+ Nuevo campo”.</div>'; return; }
    box.innerHTML = '<div class="op-campos">' + campos.map(function (c) {
      var opts = (c.tipo_dato === 'seleccion' && c.opciones && c.opciones.length)
        ? '<div class="opts">Opciones: ' + c.opciones.map(esc).join(', ') + '</div>' : '';
      return '<div class="op-campo">' +
        '<div class="main"><div class="et">' + esc(c.etiqueta) + (c.requerido == 1 ? ' <span class="op-tagd req">requerido</span>' : '') + '</div>' +
        '<div class="cl">' + esc(c.clave) + '</div>' + opts + '</div>' +
        '<span class="op-tagd">' + esc(c.tipo_dato) + '</span>' +
        '<div class="acts">' +
          '<button class="op-btn sm" data-edit="' + c.id + '" type="button">Editar</button>' +
          '<button class="op-btn sm danger" data-del="' + c.id + '" type="button">Eliminar</button>' +
        '</div></div>';
    }).join('') + '</div>';
    box.querySelectorAll('[data-edit]').forEach(function (b) { b.addEventListener('click', function () { openModal(Number(b.getAttribute('data-edit'))); }); });
    box.querySelectorAll('[data-del]').forEach(function (b) { b.addEventListener('click', function () { del(Number(b.getAttribute('data-del'))); }); });
  }

  async function loadCampos() {
    var box = $('cmList'); box.innerHTML = '<div class="op-loading">Cargando…</div>';
    if (!curTipo) { render(); return; }
    try { var d = await opApi('tipos-nave/' + curTipo + '/campos'); campos = d.data || []; render(); }
    catch (e) { box.innerHTML = '<div class="op-error-box">' + esc(e.message) + '</div>'; }
  }

  async function loadTipos() {
    try {
      var d = await opApi('tipos-nave'); tipos = d.data || [];
      if (!tipos.length) { $('cmTipo').innerHTML = '<option value="">Sin tipos</option>'; $('cmList').innerHTML = '<div class="op-empty">No hay tipos de nave.</div>'; return; }
      $('cmTipo').innerHTML = tipos.map(function (t) { return '<option value="' + t.id + '">' + esc(t.nombre) + '</option>'; }).join('');
      curTipo = String(tipos[0].id);
      loadCampos();
    } catch (e) { $('cmTipo').innerHTML = '<option value="">Error</option>'; $('cmList').innerHTML = '<div class="op-error-box">' + esc(e.message) + '</div>'; }
  }

  var modal = $('cmModal');
  function toggleOpts() { $('cm-opts-field').style.display = ($('cm-tipo_dato').value === 'seleccion') ? 'flex' : 'none'; }

  function openModal(id) {
    editingId = id || null;
    var c = id ? campos.find(function (x) { return x.id === id; }) : null;
    $('cmModalTitle').textContent = c ? 'Editar campo' : 'Nuevo campo';
    $('cm-clave').value = c ? c.clave : '';
    $('cm-clave').disabled = !!c; // la clave no se cambia al editar
    $('cm-etiqueta').value = c ? c.etiqueta : '';
    $('cm-tipo_dato').value = c ? c.tipo_dato : 'texto';
    $('cm-requerido').checked = c ? (c.requerido == 1) : false;
    $('cm-orden').value = c ? c.orden : 0;
    $('cm-opts').value = (c && c.opciones) ? c.opciones.join('\n') : '';
    toggleOpts();
    modal.classList.add('open');
    setTimeout(function () { (c ? $('cm-etiqueta') : $('cm-clave')).focus(); }, 50);
  }
  function closeModal() { modal.classList.remove('open'); editingId = null; }

  async function save() {
    var clave = $('cm-clave').value.trim();
    var etiqueta = $('cm-etiqueta').value.trim();
    var tipo_dato = $('cm-tipo_dato').value;
    var requerido = $('cm-requerido').checked;
    var orden = Number($('cm-orden').value || 0);
    var opciones = null;
    if (tipo_dato === 'seleccion') {
      opciones = $('cm-opts').value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
      if (!opciones.length) { toast('Una selección necesita al menos una opción', 'error'); return; }
    }
    if (!editingId && !/^[a-z][a-z0-9_]*$/.test(clave)) { toast("Clave inválida: usa [a-z][a-z0-9_]* (ej. 'teus')", 'error'); return; }
    if (!etiqueta) { toast('La etiqueta es obligatoria', 'error'); return; }

    var btn = $('cmSave'); btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      if (editingId) {
        await opApi('tipos-nave/' + curTipo + '/campos/' + editingId, {
          method: 'PUT',
          body: { etiqueta: etiqueta, tipo_dato: tipo_dato, requerido: requerido, opciones: opciones, orden: orden, activo: 1 }
        });
        toast('Campo actualizado', 'success');
      } else {
        await opApi('tipos-nave/' + curTipo + '/campos', {
          method: 'POST',
          body: { clave: clave, etiqueta: etiqueta, tipo_dato: tipo_dato, requerido: requerido, opciones: opciones, orden: orden }
        });
        toast('Campo creado', 'success');
      }
      closeModal(); loadCampos();
    } catch (e) { toast(e.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Guardar'; }
  }

  async function del(id) {
    var c = campos.find(function (x) { return x.id === id; });
    if (!c) return;
    if (!confirm('¿Eliminar el campo “' + c.etiqueta + '”?\nDejará de pedirse en los formularios de este tipo de nave.')) return;
    try { await opApi('tipos-nave/' + curTipo + '/campos/' + id, { method: 'DELETE' }); toast('Campo eliminado', 'success'); loadCampos(); }
    catch (e) { toast(e.message, 'error'); }
  }

  $('cmTipo').addEventListener('change', function (e) { curTipo = e.target.value; loadCampos(); });
  $('cmNuevo').addEventListener('click', function () { if (!curTipo) { toast('Selecciona un tipo', 'error'); return; } openModal(null); });
  $('cm-tipo_dato').addEventListener('change', toggleOpts);
  $('cmClose').addEventListener('click', closeModal);
  $('cmCancel').addEventListener('click', closeModal);
  $('cmSave').addEventListener('click', save);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

  loadTipos();
})();
</script>
</body>
</html>
