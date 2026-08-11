<?php
require_once('../includes/auth.php');
require_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Jornadas · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <link rel="stylesheet" href="../css/layout.css">
  <link rel="stylesheet" href="../css/ui.css">

  <style>
    .jo-wrap {
      --mono: ui-monospace, Consolas, monospace;
      display:flex; flex-direction:column; gap:18px;
      font-family:'DM Sans', system-ui, sans-serif;
    }
    .jo-wrap *, .jo-wrap *::before, .jo-wrap *::after { box-sizing:border-box; }

    /* Hero style with emerald green gradient */
    .jo-hero {
      background: linear-gradient(135deg, #005c3d 0%, #00875A 100%) !important;
      color:#fff; border-radius:20px; padding:24px 30px;
      display:flex; align-items:center; justify-content:space-between; gap:18px;
      border: 1px solid rgba(0, 135, 90, 0.2) !important;
      box-shadow: 0 8px 32px rgba(0, 135, 90, 0.08) !important;
    }
    .jo-hero h1 { margin:6px 0 4px; font-size:24px; font-weight:700; letter-spacing:-.015em; }
    .jo-hero p  { margin:0; color:rgba(255,255,255,0.85); font-size:13.5px; max-width:620px; }
    .jo-hero .tag {
      display:inline-flex; align-items:center; gap:8px; padding:5px 12px; border-radius:999px;
      background:rgba(255, 255, 255, 0.12); border:1px solid rgba(255, 255, 255, 0.2);
      font-size:11px; font-weight:600; letter-spacing:.12em; text-transform:uppercase;
      color: #ffffff !important;
    }
    
    /* Buttons */
    body .jo-btn {
      display:inline-flex; align-items:center; gap:8px; cursor:pointer;
      padding:10px 18px; border-radius:10px; border:1px solid rgba(0, 135, 90, 0.3) !important;
      background: #ffffff !important; color: #00875A !important; font:inherit; font-size:13px; font-weight:600;
      transition: all 0.2s ease;
    }
    body .jo-btn:hover {
      border-color: #005c3d !important;
      color: #005c3d !important;
      background: rgba(0, 135, 90, 0.05) !important;
      box-shadow: 0 4px 12px rgba(0, 135, 90, 0.08) !important;
    }
    body .jo-btn.primary {
      background: linear-gradient(135deg, #00875A 0%, #005c3d 100%) !important;
      color:#fff !important; border:none !important;
      font-weight: 700 !important;
      box-shadow: 0 4px 15px rgba(0, 135, 90, 0.2) !important;
    }
    body .jo-btn.primary:hover {
      transform: translateY(-1.5px) !important;
      box-shadow: 0 6px 20px rgba(0, 135, 90, 0.35) !important;
      filter: brightness(1.1) !important;
    }
    body .jo-btn.primary:active {
      transform: translateY(0.5px) !important;
    }
    body .jo-btn.danger {
      background: rgba(239, 68, 68, 0.05) !important;
      border-color: rgba(239, 68, 68, 0.25) !important;
      color: #ef4444 !important;
    }
    body .jo-btn.danger:hover {
      background: #ef4444 !important;
      color: #ffffff !important;
      border-color: #ef4444 !important;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2) !important;
    }
    body .jo-btn svg { width:15px; height:15px; }

    /* Table Wrap and Grid */
    body .jo-table-wrap {
      background: #ffffff !important;
      border: 1px solid var(--gris-borde) !important;
      border-radius: 14px !important; overflow:hidden;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.02) !important;
    }
    body .jo-table { width:100%; border-collapse:collapse; font-size:13.5px; }
    body .jo-table thead tr { background: rgba(0, 135, 90, 0.04) !important; border-bottom: 1px solid var(--gris-borde) !important; }
    body .jo-table th {
      padding:14px 18px; text-align:left;
      font-size:11px; letter-spacing:.08em; text-transform:uppercase;
      color: #005c3d !important; font-weight:700; white-space:nowrap;
    }
    body .jo-table tbody tr { border-bottom: 1px solid rgba(0, 135, 90, 0.06) !important; transition: all 0.2s ease; }
    body .jo-table tbody tr:last-child { border-bottom:0 !important; }
    body .jo-table tbody tr:hover { background: rgba(0, 135, 90, 0.02) !important; }
    body .jo-table td { padding:14px 18px; vertical-align:middle; color: #111827 !important; }
    
    .jo-name { font-weight:600; color: #111827 !important; }
    .jo-codigo { font-size:11px; color: #6b7280 !important; font-family:var(--mono); }
    .jo-hours { font-family:var(--mono); font-size:13px; color: #111827 !important; }
    
    .jo-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:6px; font-size:10.5px; font-weight:700; text-transform:uppercase; }
    .jo-badge .dot { width:6px; height:6px; border-radius:50%; }
    .jo-badge.on {
      background: rgba(16, 185, 129, 0.08) !important;
      border: 1px solid rgba(16, 185, 129, 0.25) !important;
      color: #059669 !important;
    }
    .jo-badge.on .dot { background: #10b981 !important; }
    .jo-badge.off {
      background: rgba(239, 68, 68, 0.08) !important;
      border: 1px solid rgba(239, 68, 68, 0.25) !important;
      color: #dc2626 !important;
    }
    .jo-badge.off .dot { background: #ef4444 !important; }

    /* Action Buttons in Table */
    .jo-actions { display:flex; gap:8px; justify-content:flex-end; }
    body .jo-ico-btn {
      cursor:pointer; border: 1px solid rgba(0, 135, 90, 0.25) !important;
      background: rgba(0, 135, 90, 0.05) !important; border-radius:8px; padding:6px 10px; color: #00875A !important;
      transition: all 0.2s ease;
    }
    body .jo-ico-btn:not(.danger):hover {
      background: #00875A !important;
      color: #ffffff !important;
      border-color: #00875A !important;
      box-shadow: 0 2px 8px rgba(0, 135, 90, 0.2) !important;
    }
    body .jo-ico-btn.danger {
      border-color: rgba(239, 68, 68, 0.25) !important;
      color: #ef4444 !important;
    }
    body .jo-ico-btn.danger:hover {
      background: #ef4444 !important;
      color: #ffffff !important;
      border-color: #ef4444 !important;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.2) !important;
    }
    body .jo-ico-btn svg { width:15px; height:15px; display:block; }

    /* Modal Form overrides */
    body .jo-modal {
      background: #ffffff !important;
      border: 1px solid var(--gris-borde) !important;
      color: #111827 !important;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1) !important;
      width: min(480px, calc(100vw - 32px));
    }
    body .jo-modal-head {
      border-bottom: 1px solid rgba(0, 135, 90, 0.08) !important;
      background: linear-gradient(135deg, #005c3d 0%, #00875A 100%) !important;
      padding: 20px 22px !important;
      color: #ffffff !important;
    }
    body .jo-modal-head .jo-head-ico {
      background: rgba(255, 255, 255, 0.1) !important;
      border: 1px solid rgba(255, 255, 255, 0.15) !important;
      color: #ffffff !important;
    }
    body .jo-modal-head h2 { color: #ffffff !important; }
    body .jo-modal-head .jo-head-sub { color: rgba(255, 255, 255, 0.8) !important; }
    
    body .jo-x {
      background: rgba(255, 255, 255, 0.1) !important;
      border: 1px solid rgba(255, 255, 255, 0.15) !important;
      color: #ffffff !important;
    }
    body .jo-x:hover {
      background: rgba(255, 255, 255, 0.2) !important;
      color: #ffffff !important;
    }

    body .jo-modal-body {
      background: #ffffff !important;
      padding: 20px 22px !important;
    }
    body .jo-modal-foot {
      background: #ffffff !important;
      border-top: 1px solid rgba(0, 135, 90, 0.08) !important;
      padding: 15px 22px !important;
    }

    body .jo-modal .jo-field label {
      color: #374151 !important;
      font-weight: 600 !important;
      font-size: 11.5px !important;
    }
    body .jo-modal .jo-field input[type="text"],
    body .jo-modal .jo-field input[type="time"] {
      background-color: #ffffff !important;
      border: 1.5px solid #d1d5db !important;
      color: #111827 !important;
      border-radius: 8px !important;
      padding: 10px 12px !important;
    }
    body .jo-modal .jo-field input[type="text"]:focus,
    body .jo-modal .jo-field input[type="time"]:focus {
      border-color: #00875A !important;
      box-shadow: 0 0 0 3px rgba(0, 135, 90, 0.15) !important;
      background-color: #ffffff !important;
    }
    body .jo-modal .jo-field input::placeholder {
      color: #9ca3af !important;
    }
    body .jo-modal .jo-in-ico {
      color: #6b7280 !important;
    }

    /* Modal error fields */
    body .jo-modal .jo-field.is-error input {
      border-color: #ef4444 !important;
      background: rgba(239, 68, 68, 0.03) !important;
    }
    body .jo-modal .jo-field-err {
      color: #ef4444 !important;
    }
    body .jo-modal .jo-hint {
      color: #6b7280 !important;
      opacity: 0.9;
    }
    .jo-row2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    /* Duration Preview Card */
    body .jo-preview {
      background: #f9fafb !important;
      border: 1px solid rgba(0, 135, 90, 0.15) !important;
      padding: 13px 15px !important;
      border-radius: 12px !important;
    }
    body .jo-preview .jo-pv-ico {
      background: rgba(0, 135, 90, 0.08) !important;
      border: 1px solid rgba(0, 135, 90, 0.2) !important;
      color: #00875A !important;
    }
    body .jo-preview .jo-pv-meta {
      color: #6b7280 !important;
    }
    body .jo-preview .jo-pv-val {
      color: #111827 !important;
    }
    body .jo-preview .mid {
      color: #ef4444 !important;
    }

    /* Checkbox Active Card */
    body .jo-check {
      background: #f9fafb !important;
      border: 1px solid rgba(0, 135, 90, 0.15) !important;
      color: #111827 !important;
      padding: 12px 14px !important;
      border-radius: 11px !important;
    }
    body .jo-check:hover {
      border-color: rgba(0, 135, 90, 0.3) !important;
    }
    body .jo-check input {
      accent-color: #00875A !important;
    }
    body .jo-check .jo-check-sub {
      color: #6b7280 !important;
    }

    /* Modal cancellation buttons */
    body .jo-modal-foot button:not(.primary) {
      background: #f3f4f6 !important;
      border: 1px solid #d1d5db !important;
      color: #4b5563 !important;
    }
    body .jo-modal-foot button:not(.primary):hover {
      background: #e5e7eb !important;
      border-color: #c4c5c9 !important;
      color: #1f2937 !important;
    }

    .content { padding:24px 28px 60px; overflow-y:auto; }

    /* ── Backdrop ── */
    .jo-back {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.3); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px);
      z-index:999;
    }
    .jo-back.open { display:block; }

    /* ── Modal positioning ── */
    .jo-modal { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); z-index:1000; border-radius:16px; overflow:hidden; flex-direction:column; max-height:calc(100vh - 40px); overflow-y:auto; }
    .jo-modal.open { display:flex; }

    /* ── Modal head layout ── */
    .jo-modal-head { display:flex; align-items:center; gap:14px; position:relative; }
    .jo-head-ico { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .jo-head-ico svg { width:22px; height:22px; }
    .jo-head-txt { flex:1; min-width:0; }
    .jo-head-txt h2 { margin:0 0 2px; font-size:17px; font-weight:700; }
    .jo-head-sub { margin:0; font-size:13px; }
    .jo-x { width:30px; height:30px; border-radius:8px; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .2s; flex-shrink:0; margin-left:auto; }

    /* ── Form fields ── */
    .jo-field { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
    .jo-field label { font-size:11.5px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; }
    .jo-field input { width:100%; font:inherit; font-size:14px; outline:none; }
    .jo-field-err { display:none; font-size:12px; font-weight:600; }
    .jo-field.is-error .jo-field-err { display:block; }
    .jo-hint { font-size:12px; margin-top:2px; }

    /* ── Input with icon ── */
    .jo-input-wrap { position:relative; }
    .jo-input-wrap input { padding-right:36px !important; }
    .jo-in-ico { position:absolute; right:10px; top:50%; transform:translateY(-50%); width:16px; height:16px; pointer-events:none; }

    /* ── Duration preview ── */
    .jo-preview { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
    .jo-pv-ico { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .jo-pv-ico svg { width:18px; height:18px; }
    .jo-pv-meta { font-size:11px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; margin-bottom:2px; }
    .jo-pv-val { font-size:15px; font-weight:700; }

    /* ── Checkbox card ── */
    .jo-check { display:flex; align-items:center; gap:10px; cursor:pointer; margin-bottom:0; }
    .jo-check > input[type="checkbox"] { width:16px; height:16px; cursor:pointer; flex-shrink:0; }
    .jo-check > span { font-size:14px; font-weight:600; display:flex; flex-direction:column; gap:2px; }
    .jo-check-sub { font-size:12px; font-weight:400; }

    /* ── Modal footer ── */
    .jo-modal-foot { display:flex; justify-content:flex-end; gap:10px; }

    /* ── Toast ── */
    body .jo-toast {
      position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(12px);
      padding:10px 18px; border-radius:10px; font-size:13.5px; font-weight:600;
      opacity:0; pointer-events:none; transition:all .25s; z-index:2000; white-space:nowrap;
      background: #111827 !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
      color: #ffffff !important;
    }
    body .jo-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
  </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="shell">
  <?php $sb_base = '..'; include('../includes/sidebar.php'); ?>

  <div class="main-area">
    <?php include('../includes/header.php'); ?>

    <main class="content">
      <div class="jo-wrap">

        <section class="jo-hero">
          <div>
            <span class="tag">ADMINISTRACIÓN · JORNADAS</span>
            <h1>Jornadas / Horarios de turno</h1>
            <p>Define los horarios con los que opera la estiba (ej. Día 07:00–19:00, Noche 19:00–07:00, Único 08:00–21:15). Al trabajar un turno eliges una de estas jornadas. Las jornadas que cruzan medianoche (fin menor al inicio) se manejan automáticamente.</p>
          </div>
          <button class="jo-btn primary" id="joNew">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nueva jornada
          </button>
        </section>

        <div class="jo-table-wrap">
          <table class="jo-table">
            <thead>
              <tr>
                <th>Jornada</th><th>Código</th><th>Inicio</th><th>Fin</th><th>Duración</th><th>Estado</th><th style="text-align:right">Acciones</th>
              </tr>
            </thead>
            <tbody id="joTbody">
              <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--co-faint)">Cargando…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Modal alta/edición -->
<div class="jo-back" id="joBack"></div>
<div class="jo-modal" id="joModal" aria-hidden="true">
  <div class="jo-modal-head">
    <span class="jo-head-ico">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
    </span>
    <div class="jo-head-txt">
      <h2 id="joModalTitle">Nueva jornada</h2>
      <p class="jo-head-sub" id="joModalSub">Define un horario de turno</p>
    </div>
    <button class="jo-x" id="joClose" aria-label="Cerrar">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="jo-modal-body">
    <input type="hidden" id="joId" value="0">
    <div class="jo-field" id="joNombreField">
      <label>Nombre</label>
      <input type="text" id="joNombre" placeholder="Ej. Día, Noche, Único" maxlength="30">
      <div class="jo-field-err">El nombre es obligatorio.</div>
    </div>
    <div class="jo-row2">
      <div class="jo-field" id="joIniField">
        <label>Hora inicio</label>
        <div class="jo-input-wrap">
          <input type="time" id="joIni">
          <svg class="jo-in-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
        </div>
        <div class="jo-field-err">Requerida.</div>
      </div>
      <div class="jo-field" id="joFinField">
        <label>Hora fin</label>
        <div class="jo-input-wrap">
          <input type="time" id="joFin">
          <svg class="jo-in-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
        </div>
        <div class="jo-field-err">Requerida.</div>
      </div>
    </div>
    <div class="jo-preview" id="joPreview">
      <span class="jo-pv-ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
      </span>
      <div>
        <div class="jo-pv-meta">Duración del turno</div>
        <div class="jo-pv-val"><span id="joDur">—</span><span id="joMid"></span></div>
      </div>
    </div>
    <div class="jo-field">
      <label>Código (opcional)</label>
      <input type="text" id="joCodigo" placeholder="Ej. D, N, U" maxlength="8">
      <div class="jo-hint">Abreviatura corta para identificar la jornada en tablas y reportes.</div>
    </div>
    <label class="jo-check">
      <input type="checkbox" id="joActivo" checked>
      <span>Jornada activa<span class="jo-check-sub">Disponible para elegir al registrar un turno</span></span>
    </label>
  </div>
  <div class="jo-modal-foot">
    <button class="jo-btn" id="joCancel">Cancelar</button>
    <button class="jo-btn primary" id="joSave">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      Guardar jornada
    </button>
  </div>
</div>

<div class="jo-toast" id="joToast">—</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  let jornadas = [];

  function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
  let toastT;
  function toast(m){ const t=$('joToast'); t.textContent=m; t.classList.add('show'); clearTimeout(toastT); toastT=setTimeout(()=>t.classList.remove('show'),2400); }

  function toMin(t){ const p=String(t).split(':'); return (+p[0])*60 + (+(p[1]||0)); }
  function durLabel(ini, fin){
    let d = toMin(fin) - toMin(ini); if (d <= 0) d += 1440;
    const h = Math.floor(d/60), m = d%60;
    return h + 'h' + (m ? ' ' + String(m).padStart(2,'0') : '');
  }

  async function apiPost(url, body){
    const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
    let d; try { d = await r.json(); } catch { d = null; }
    if (!r.ok || !d || !d.success) throw new Error((d && d.error) || ('Error ' + r.status));
    return d;
  }

  function render(){
    const tb = $('joTbody'); tb.innerHTML='';
    if (!jornadas.length){ tb.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--co-faint)">Sin jornadas. Crea la primera.</td></tr>'; return; }
    jornadas.forEach(j => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><span class="jo-name">${esc(j.nombre)}</span>${toMin(j.hora_fin)<=toMin(j.hora_inicio)?' <span class="jo-codigo">· cruza medianoche</span>':''}</td>
        <td><span class="jo-codigo">${esc(j.codigo || '—')}</span></td>
        <td class="jo-hours">${esc(j.hora_inicio)}</td>
        <td class="jo-hours">${esc(j.hora_fin)}</td>
        <td class="jo-hours">${durLabel(j.hora_inicio, j.hora_fin)}</td>
        <td><span class="jo-badge ${j.activo? 'on':'off'}"><span class="dot"></span>${j.activo?'Activa':'Inactiva'}</span></td>
        <td>
          <div class="jo-actions">
            <button class="jo-ico-btn" data-edit="${j.id}" title="Editar">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
            </button>
            <button class="jo-ico-btn danger" data-del="${j.id}" title="Eliminar">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          </div>
        </td>`;
      tb.append(tr);
    });
  }

  function clearErrors(){ ['joNombreField','joIniField','joFinField'].forEach(id => $(id).classList.remove('is-error')); }
  function updatePreview(){
    const ini = $('joIni').value, fin = $('joFin').value;
    if (!ini || !fin){ $('joDur').textContent = '—'; $('joMid').textContent = ''; return; }
    $('joDur').textContent = durLabel(ini, fin);
    $('joMid').innerHTML = toMin(fin) <= toMin(ini) ? '<span class="mid">Cruza medianoche</span>' : '';
  }

  function openModal(j){
    $('joModalTitle').textContent = j ? 'Editar jornada' : 'Nueva jornada';
    $('joModalSub').textContent   = j ? 'Modifica el horario de turno' : 'Define un horario de turno';
    $('joId').value     = j ? j.id : 0;
    $('joNombre').value = j ? j.nombre : '';
    $('joIni').value    = j ? j.hora_inicio : '';
    $('joFin').value    = j ? j.hora_fin : '';
    $('joCodigo').value = j ? (j.codigo || '') : '';
    $('joActivo').checked = j ? !!j.activo : true;
    clearErrors(); updatePreview();
    $('joModal').classList.add('open'); $('joModal').setAttribute('aria-hidden','false');
    $('joBack').classList.add('open');
    $('joNombre').focus();
  }
  function closeModal(){ $('joModal').classList.remove('open'); $('joModal').setAttribute('aria-hidden','true'); $('joBack').classList.remove('open'); }

  async function cargar(){
    try {
      const r = await fetch('../api/get_jornadas.php', { cache:'no-store' });
      const d = await r.json();
      if (!d.success) throw new Error(d.error || 'No se pudo cargar');
      jornadas = d.data || [];
      render();
    } catch(e){ $('joTbody').innerHTML = `<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--st-er-fg)">${esc(e.message)}</td></tr>`; }
  }

  async function save(){
    const body = {
      id: +$('joId').value,
      nombre: $('joNombre').value.trim(),
      hora_inicio: $('joIni').value,
      hora_fin: $('joFin').value,
      codigo: $('joCodigo').value.trim(),
      activo: $('joActivo').checked ? 1 : 0
    };
    clearErrors();
    let bad = false;
    if (!body.nombre)      { $('joNombreField').classList.add('is-error'); bad = true; }
    if (!body.hora_inicio) { $('joIniField').classList.add('is-error'); bad = true; }
    if (!body.hora_fin)    { $('joFinField').classList.add('is-error'); bad = true; }
    if (bad) { toast('Revisa los campos marcados'); return; }
    const btn = $('joSave'); btn.disabled = true;
    try { await apiPost('../api/save_jornada.php', body); toast('Jornada guardada'); closeModal(); await cargar(); }
    catch(e){ toast('Error: ' + e.message); }
    finally { btn.disabled = false; }
  }

  async function del(id){
    const j = jornadas.find(x => x.id === id);
    if (!confirm(`¿Eliminar la jornada "${j ? j.nombre : ''}"?`)) return;
    try { await apiPost('../api/delete_jornada.php', { id }); toast('Jornada eliminada'); await cargar(); }
    catch(e){ toast(e.message); }
  }

  $('joNew').addEventListener('click', () => openModal(null));
  $('joClose').addEventListener('click', closeModal);
  $('joCancel').addEventListener('click', closeModal);
  $('joBack').addEventListener('click', closeModal);
  $('joSave').addEventListener('click', save);
  $('joIni').addEventListener('input', () => { $('joIniField').classList.remove('is-error'); updatePreview(); });
  $('joFin').addEventListener('input', () => { $('joFinField').classList.remove('is-error'); updatePreview(); });
  $('joNombre').addEventListener('input', () => $('joNombreField').classList.remove('is-error'));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
  $('joTbody').addEventListener('click', e => {
    const ed = e.target.closest('[data-edit]'); if (ed) { const j = jornadas.find(x => x.id === +ed.dataset.edit); openModal(j); return; }
    const dl = e.target.closest('[data-del]');  if (dl) { del(+dl.dataset.del); }
  });

  cargar();
})();
</script>

</body>
</html>
