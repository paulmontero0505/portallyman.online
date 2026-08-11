<?php
require_once(__DIR__ . '/includes/drive_config.php');
require_once(__DIR__ . '/includes/sugerencias_catalogo.php');
$ENCUESTA_SECCIONES = sg_encuesta_secciones();
$ENCUESTA_TITULO    = sg_encuesta_titulo();
$ENCUESTA_INTRO     = sg_encuesta_intro();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sugerencias Tallyman — Tallyman Control</title>
<link rel="icon" type="image/png" href="img/logo.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
:root{
  --verde-osc:#005c3d; --verde:#00875A; --verde-cl:#00b377;
  --blanco:#fff; --gris-fondo:#EEF2F7; --gris-texto:#6B7280;
  --texto-main:#0D1A2E; --fuente:'DM Sans','Segoe UI',sans-serif; --rojo:#DC2626;
}
html,body{ height:100%; font-family:var(--fuente); }
.bg{ position:fixed; inset:0; background:url('img/puerto.jpg') center/cover no-repeat; z-index:0; }
.bg::after{ content:''; position:absolute; inset:0;
  background:linear-gradient(150deg, rgba(0,50,33,0.90) 0%, rgba(0,92,61,0.78) 45%, rgba(0,25,18,0.92) 100%); }
.topbar{ position:fixed; top:0; left:0; right:0; z-index:10; display:flex; align-items:center;
  justify-content:space-between; padding:0 32px; height:60px; background:rgba(0,30,20,0.72);
  backdrop-filter:blur(14px); border-bottom:1px solid rgba(255,255,255,0.10); }
.topbar b{ color:#fff; font-size:15px; font-weight:800; letter-spacing:.2px; }
.topbar b span{ color:#4ade80; }
.topbar-label{ font-size:9.5px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:rgba(255,255,255,.55); }
body{ display:flex; align-items:center; justify-content:center; min-height:100vh; padding:78px 20px 32px; }
.page{ position:relative; z-index:1; width:100%; max-width:980px; display:grid; grid-template-columns:1fr 460px; gap:48px; align-items:center; }
.col-info{ display:flex; flex-direction:column; gap:20px; }
.eyebrow{ display:flex; align-items:center; gap:10px; }
.eyebrow-line{ width:24px; height:2px; background:#4ade80; border-radius:2px; }
.eyebrow-text{ font-size:10px; font-weight:700; letter-spacing:2.6px; text-transform:uppercase; color:rgba(255,255,255,.72); }
.col-titulo{ font-size:38px; font-weight:800; color:#fff; line-height:1.15; letter-spacing:-1px; text-shadow:0 2px 12px rgba(0,0,0,.5); }
.col-titulo span{ color:#5eead4; }
.col-desc{ font-size:14px; color:rgba(255,255,255,.82); line-height:1.7; max-width:400px; }
.chips{ display:flex; flex-wrap:wrap; gap:8px; }
.chip{ display:flex; align-items:center; gap:7px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.22);
  border-radius:8px; padding:7px 13px; font-size:12px; font-weight:500; color:rgba(255,255,255,.9); }
.chip svg{ width:13px; height:13px; color:#5eead4; }

.card{ background:#fff; border-radius:20px; overflow:hidden; width:100%;
  box-shadow:0 2px 4px rgba(0,0,0,.06), 0 24px 64px rgba(0,0,0,.42); }
.card-header{ background:linear-gradient(135deg,var(--verde-osc) 0%,var(--verde) 100%); padding:18px 22px; display:flex; align-items:center; gap:12px; }
.card-header-icon{ width:38px; height:38px; border-radius:10px; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.2);
  display:flex; align-items:center; justify-content:center; }
.card-header-icon svg{ width:18px; height:18px; color:#fff; }
.card-header-titulo{ font-size:14px; font-weight:700; color:#fff; }
.card-header-sub{ font-size:11px; color:rgba(255,255,255,.6); margin-top:2px; }
.card-body{ padding:22px; max-height:70vh; overflow-y:auto; }

.campo-label{ display:block; font-size:10.5px; font-weight:700; letter-spacing:.8px; text-transform:uppercase; color:var(--gris-texto); margin-bottom:8px; }
.dni-wrap{ display:flex; margin-bottom:6px; border-radius:11px; overflow:hidden; border:1.5px solid #D1D9E0; transition:border-color .2s, box-shadow .2s; }
.dni-wrap:focus-within{ border-color:var(--verde); box-shadow:0 0 0 3px rgba(0,135,90,.14); }
.dni-prefix{ width:52px; display:flex; align-items:center; justify-content:center; background:var(--gris-fondo); border-right:1.5px solid #D1D9E0;
  font-size:11px; font-weight:700; color:var(--verde); }
.dni-wrap input{ flex:1; padding:13px 14px; border:none; font-size:22px; font-weight:600; color:var(--texto-main); background:var(--gris-fondo); outline:none; letter-spacing:6px; }
.dni-wrap:focus-within input{ background:#fff; }
.dni-counter{ text-align:right; font-size:11px; color:#B0B8C4; font-weight:500; margin-bottom:16px; }
.dni-counter.ok{ color:var(--verde); }

.btn{ width:100%; padding:13px; border:none; border-radius:10px; cursor:pointer; font-family:var(--fuente); font-size:13px; font-weight:700;
  display:flex; align-items:center; justify-content:center; gap:8px; transition:all .2s; }
.btn:disabled{ opacity:.45; cursor:not-allowed; }
.btn-primario{ background:linear-gradient(135deg,var(--verde) 0%,var(--verde-osc) 100%); color:#fff; box-shadow:0 4px 16px rgba(0,135,90,.3); }
.btn-primario:hover:not(:disabled){ transform:translateY(-1px); box-shadow:0 6px 22px rgba(0,92,61,.42); }
.spinner{ width:14px; height:14px; border:2px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; display:none; }
@keyframes spin{ to{ transform:rotate(360deg); } }

.msg{ display:none; align-items:center; gap:8px; border-radius:10px; padding:10px 14px; margin-top:12px; font-size:12.5px; font-weight:500; }
.msg svg{ flex-shrink:0; width:15px; height:15px; }
.msg.error{ background:#FEF2F2; border:1px solid #FECACA; color:var(--rojo); }
.msg.exito{ background:#ECFDF5; border:1px solid #A7F3D0; color:#047857; }

.resultado{ display:none; flex-direction:column; animation:fadeUp .35s ease; }
@keyframes fadeUp{ from{ opacity:0; transform:translateY(10px);} to{ opacity:1; transform:translateY(0);} }
.perfil-top{ display:flex; align-items:center; gap:13px; padding-bottom:16px; border-bottom:1px solid #EEF2F7; margin-bottom:16px; }
.avatar{ width:52px; height:52px; border-radius:13px; background:linear-gradient(135deg,var(--verde),var(--verde-osc)); display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:800; color:#fff; flex-shrink:0; }
.perfil-nombre{ font-size:15px; font-weight:700; color:var(--texto-main); line-height:1.2; }
.perfil-cargo{ font-size:12px; color:var(--gris-texto); margin-top:3px; }

.perfil-top.anon .avatar{ background:linear-gradient(135deg,#475569,#1e293b); }
.perfil-top.anon .avatar svg{ width:24px; height:24px; }
.perfil-top.anon .perfil-nombre{ letter-spacing:.06em; color:#1e293b; }
.perfil-top.anon .perfil-cargo{ color:#64748b; }

.canal-grid{ display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:6px; }
.canal-opt{ position:relative; cursor:pointer; border:1.5px solid #D1D9E0; border-radius:11px; padding:12px 10px; text-align:center; transition:all .15s; background:#fff; }
.canal-opt:hover{ border-color:var(--verde-cl); }
.canal-opt svg{ width:20px; height:20px; color:var(--gris-texto); margin-bottom:6px; }
.canal-opt span{ display:block; font-size:12px; font-weight:700; color:var(--texto-main); }
.canal-opt.sel{ border-color:var(--verde); background:#F0FDF4; box-shadow:0 0 0 3px rgba(0,135,90,.12); }
.canal-opt.sel svg{ color:var(--verde); }
.canal-anon{ display:none; align-items:flex-start; gap:9px; background:#EFF6FF; border:1px solid #BFDBFE; color:#1d4ed8;
  border-radius:9px; padding:11px 12px; font-size:12px; font-weight:600; margin:10px 0; }
.canal-anon svg{ width:15px; height:15px; flex-shrink:0; margin-top:1px; }
.canal-anon strong{ display:block; font-size:12.5px; font-weight:800; margin-bottom:3px; }
.canal-anon p{ margin:0; font-weight:500; line-height:1.45; color:#1e40af; }

.detalle-wrap{ display:none; flex-direction:column; gap:6px; margin-top:6px; }
.detalle-wrap textarea{ width:100%; min-height:120px; padding:12px 14px; border-radius:11px; border:1.5px solid #D1D9E0;
  font-family:var(--fuente); font-size:13.5px; color:var(--texto-main); resize:vertical; outline:none; }
.detalle-wrap textarea:focus{ border-color:var(--verde); box-shadow:0 0 0 3px rgba(0,135,90,.14); }
.detalle-counter{ text-align:right; font-size:11px; color:#B0B8C4; }

#encuestaWrap{ display:none; flex-direction:column; gap:12px; }
.enc-titulo{ font-size:14px; font-weight:800; color:var(--texto-main); }
.enc-intro{ font-size:12.5px; color:var(--gris-texto); line-height:1.55; margin:0; }
.enc-sec{ border:1.5px solid #E3E8EE; border-radius:12px; padding:12px 14px; background:#FBFCFD; }
.enc-sec-titulo{ font-size:12.5px; font-weight:700; color:var(--verde-osc); letter-spacing:.2px; margin-bottom:9px; }
.enc-items{ display:flex; flex-direction:column; gap:7px; }
.enc-item{ display:flex; align-items:flex-start; gap:9px; cursor:pointer; font-size:13px; color:var(--texto-main); line-height:1.4; }
.enc-item input{ margin-top:2.5px; accent-color:var(--verde); width:15px; height:15px; flex-shrink:0; cursor:pointer; }
.enc-otros{ margin-top:10px; }
.enc-otros label{ display:block; font-size:11px; font-weight:600; color:var(--gris-texto); margin-bottom:5px; }
.enc-otros input{ width:100%; padding:9px 11px; border-radius:9px; border:1.5px solid #D1D9E0;
  font-family:var(--fuente); font-size:13px; color:var(--texto-main); outline:none; }
.enc-otros input:focus{ border-color:var(--verde); box-shadow:0 0 0 3px rgba(0,135,90,.14); }
.enc-hint{ font-size:11px; color:#B0B8C4; }

.adj-wrap{ display:flex; flex-direction:column; gap:7px; }
.adj-drop{ display:flex; align-items:center; justify-content:center; gap:8px; cursor:pointer;
  border:1.5px dashed #C7D2DD; border-radius:11px; padding:11px 12px; background:#FBFCFD;
  font-size:12.5px; font-weight:700; color:var(--gris-texto); transition:all .15s; }
.adj-drop:hover{ border-color:var(--verde); color:var(--verde); background:#F0FDF4; }
.adj-drop svg{ width:16px; height:16px; flex-shrink:0; }
.adj-drop.lleno{ opacity:.5; cursor:not-allowed; }
.adj-drop.lleno:hover{ border-color:#C7D2DD; color:var(--gris-texto); background:#FBFCFD; }
.adj-hint{ font-size:11px; color:#B0B8C4; text-align:center; }
.adj-lista{ display:flex; flex-direction:column; gap:6px; }
.adj-item{ display:flex; align-items:center; gap:9px; border:1px solid #E3E8EE; border-radius:9px;
  padding:8px 10px; background:#fff; }
.adj-item svg.ico{ width:17px; height:17px; color:var(--verde); flex-shrink:0; }
.adj-meta{ flex:1; min-width:0; }
.adj-nombre{ font-size:12px; font-weight:600; color:var(--texto-main); white-space:nowrap;
  overflow:hidden; text-overflow:ellipsis; }
.adj-peso{ font-size:10.5px; color:#98A2B3; margin-top:1px; }
.adj-quitar{ border:none; background:transparent; cursor:pointer; color:#B0B8C4; font-size:17px;
  line-height:1; padding:2px 4px; border-radius:5px; flex-shrink:0; }
.adj-quitar:hover{ color:#DC2626; background:#FEF2F2; }

.btn-nueva{ width:100%; padding:10px; background:transparent; color:var(--verde); font-size:12px; font-weight:700; font-family:var(--fuente);
  border:1px dashed #A7F3D0; border-radius:10px; cursor:pointer; margin-top:12px; display:none; align-items:center; justify-content:center; gap:6px; }
.btn-nueva:hover{ background:#F0FDF4; border-color:var(--verde); }

.card-footer{ padding:11px 22px; background:var(--gris-fondo); border-top:1px solid #E5EAF0; display:flex; align-items:center; justify-content:space-between; }
.card-footer-text{ font-size:10px; color:#B0B8C4; font-weight:500; }
.dot-online{ width:7px; height:7px; border-radius:50%; background:#4ADE80; box-shadow:0 0 7px #4ADE80; }

@media (max-width:820px){
  .page{ grid-template-columns:1fr; max-width:460px; }
  .col-info{ display:none; }
  body{ padding:74px 16px 28px; align-items:flex-start; }
}
</style>
</head>
<body>
<div class="bg"></div>

<nav class="topbar">
  <div><b>Tallyman <span>Control</span></b><div class="topbar-label">Shift Command · Sugerencias</div></div>
  <img src="img/logo.jpg" alt="Logo" style="height:34px;border-radius:7px">
</nav>

<div class="page">
  <div class="col-info">
    <div class="eyebrow"><div class="eyebrow-line"></div><span class="eyebrow-text">Buzón Tallyman</span></div>
    <div class="col-titulo">Tu voz <span>importa</span><br>para nosotros</div>
    <p class="col-desc">Ingresa tu DNI y comparte tus observaciones, consultas, solicitudes o propuestas de mejora. Las observaciones se registran de forma anónima.</p>
    <div class="chips">
      <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3"/></svg>Observaciones anónimas</div>
      <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 1 1 5.83 1c0 2-3 2-3 4"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Consultas y solicitudes</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-header-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
      </div>
      <div>
        <div class="card-header-titulo">Sugerencias Tallyman</div>
        <div class="card-header-sub">Identifícate con tu documento de identidad</div>
      </div>
    </div>

    <div class="card-body">
      <form id="formDni" autocomplete="off">
        <label class="campo-label" for="dni">Número de DNI</label>
        <div class="dni-wrap">
          <div class="dni-prefix">DNI</div>
          <input type="text" id="dni" placeholder="• • • • • • • •" maxlength="8" inputmode="numeric" autocomplete="off" spellcheck="false">
        </div>
        <div class="dni-counter" id="dniCounter">0 / 8 dígitos</div>
        <button type="submit" class="btn btn-primario" id="btnBuscar" disabled>
          <svg id="icoBuscar" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <div class="spinner" id="spinner"></div>
          <span id="btnTxt">Continuar</span>
        </button>
        <div class="msg error" id="msgError"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span id="msgErrorTxt"></span></div>
      </form>

      <div class="resultado" id="resultado">
        <div class="perfil-top" id="perfilTop">
          <div class="avatar" id="resAvatar">--</div>
          <div>
            <div class="perfil-nombre" id="resNombre">—</div>
            <div class="perfil-cargo" id="resCargo">—</div>
          </div>
        </div>

        <label class="campo-label">¿Qué deseas registrar?</label>
        <div class="canal-grid" id="canalGrid"></div>

        <div class="canal-anon" id="canalAnon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <div>
            <strong>Tu anonimato está garantizado</strong>
            <p>No se registrará tu nombre, DNI, función ni ningún dato que permita identificarte. Solo se guarda el texto de tu observación. Escribe con total confianza.</p>
          </div>
        </div>

        <div class="detalle-wrap" id="detalleWrap">
          <div id="textoLibreWrap">
            <label class="campo-label" id="detalleLabel">Detalle</label>
            <textarea id="detalle" maxlength="2000" placeholder="Escribe aquí…"></textarea>
            <div class="detalle-counter" id="detalleCounter">0 / 2000</div>

            <div class="adj-wrap">
              <input type="file" id="adjInput" multiple hidden
                     accept="<?= htmlspecialchars(sg_accept_attr(), ENT_QUOTES) ?>">
              <div class="adj-drop" id="adjDrop">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                <span id="adjDropTxt">Adjuntar archivo (opcional)</span>
              </div>
              <div class="adj-hint">Imagen, PDF, documento o video · máx. <?= SG_MAX_ARCHIVOS ?> archivos · menos de 4 MB cada uno</div>
              <div class="adj-lista" id="adjLista"></div>
            </div>
          </div>

          <div id="encuestaWrap">
            <div class="enc-titulo"><?= htmlspecialchars($ENCUESTA_TITULO) ?></div>
            <p class="enc-intro"><?= htmlspecialchars($ENCUESTA_INTRO) ?></p>
            <div id="encuestaSecciones"></div>
          </div>

          <button class="btn btn-primario" id="btnEnviar" disabled style="margin-top:6px">
            <div class="spinner" id="spEnviar"></div>
            <span>Enviar</span>
          </button>
        </div>

        <div class="msg exito" id="msgExito"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span id="msgExitoTxt"></span></div>

        <button class="btn-nueva" id="btnNueva"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>Enviar otra</button>
      </div>
    </div>

    <div class="card-footer">
      <span class="card-footer-text">Tallyman Control · Shift Command</span>
      <div class="dot-online"></div>
    </div>
  </div>
</div>

<script>
'use strict';
const $ = id => document.getElementById(id);
const API = 'api/publico/';
let actual = null;
let canalSel = null;

const CANALES = [
  { key:'observacion', label:'Observaciones',      anonimo:true,  icon:'<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3"/>' },
  { key:'consulta',    label:'Consultas',          anonimo:false, icon:'<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 1 1 5.83 1c0 2-3 2-3 4"/><line x1="12" y1="17" x2="12.01" y2="17"/>' },
  { key:'solicitud',   label:'Solicitudes',        anonimo:false, icon:'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>' },
  { key:'propuesta',   label:'Propuestas de mejora', anonimo:false, icon:'<path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/>' },
  { key:'encuesta',    label:'Encuestas',            anonimo:false, icon:'<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>' },
];

const ENC_SECCIONES = <?= json_encode($ENCUESTA_SECCIONES, JSON_UNESCAPED_UNICODE) ?>;

function initials(n){ const w=String(n||'').trim().split(/\s+/); return ((w[0]?.[0]||'')+(w[1]?.[0]||'')).toUpperCase()||'--'; }

function buildCanalGrid(){
  $('canalGrid').innerHTML = CANALES.map(c => `
    <div class="canal-opt" data-canal="${c.key}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${c.icon}</svg>
      <span>${c.label}</span>
    </div>`).join('');
}
buildCanalGrid();

const ICONO_ANON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><circle cx="12" cy="10" r="2.4"/><path d="M8.4 16a3.9 3.9 0 0 1 7.2 0"/></svg>';

function pintarPerfil(anon){
  const c = (actual && actual.colaborador) || {};
  const top = $('perfilTop');
  top.classList.toggle('anon', !!anon);
  if (anon) {
    $('resAvatar').innerHTML   = ICONO_ANON;
    $('resNombre').textContent = 'MODO ANÓNIMO';
    $('resCargo').textContent  = 'Tu identidad no será registrada';
  } else {
    $('resAvatar').textContent = initials(c.nombre);
    $('resNombre').textContent = c.nombre || '—';
    $('resCargo').textContent  = c.funcion || '—';
  }
}

function buildEncuestaUI(){
  $('encuestaSecciones').innerHTML = Object.entries(ENC_SECCIONES).map(([clave, sec]) => `
    <div class="enc-sec" data-sec="${clave}">
      <div class="enc-sec-titulo">${sec.label}</div>
      <div class="enc-items">
        ${sec.items.map(it => `
          <label class="enc-item">
            <input type="checkbox" value="${it.replace(/"/g,'&quot;')}">
            <span>${it}</span>
          </label>`).join('')}
      </div>
      <div class="enc-otros">
        <label>Otros (${sec.label})</label>
        <input type="text" maxlength="300" data-otros placeholder="Especifica un tema no listado…">
      </div>
    </div>`).join('');
  $('encuestaSecciones').addEventListener('change', validarEnvio);
  $('encuestaSecciones').addEventListener('input', validarEnvio);
}
buildEncuestaUI();

function leerEncuesta(){
  const out = {};
  document.querySelectorAll('#encuestaSecciones .enc-sec').forEach(box => {
    const clave = box.dataset.sec;
    const items = [...box.querySelectorAll('input[type=checkbox]:checked')].map(i => i.value);
    const otros = box.querySelector('[data-otros]').value.trim();
    if (items.length || otros) out[clave] = { items, otros };
  });
  return out;
}
function encuestaVacia(){ return Object.keys(leerEncuesta()).length === 0; }
function limpiarEncuesta(){
  document.querySelectorAll('#encuestaSecciones input[type=checkbox]').forEach(i => i.checked = false);
  document.querySelectorAll('#encuestaSecciones [data-otros]').forEach(i => i.value = '');
}

function selectCanal(key){
  canalSel = CANALES.find(c => c.key === key) || null;
  document.querySelectorAll('.canal-opt').forEach(el => el.classList.toggle('sel', el.dataset.canal === key));
  if (!canalSel) { $('detalleWrap').style.display = 'none'; $('canalAnon').style.display = 'none'; pintarPerfil(false); return; }
  pintarPerfil(canalSel.anonimo);
  $('canalAnon').style.display = canalSel.anonimo ? 'flex' : 'none';

  const esEncuesta = canalSel.key === 'encuesta';
  $('textoLibreWrap').style.display = esEncuesta ? 'none' : 'block';
  $('encuestaWrap').style.display   = esEncuesta ? 'flex' : 'none';
  $('detalleLabel').textContent = 'Detalle de ' + canalSel.label;
  $('detalle').placeholder = canalSel.key === 'observacion' ? 'Escribe tu observación…' : 'Escribe aquí…';
  $('detalleWrap').style.display = 'flex';
  $('msgExito').style.display = 'none';
  validarEnvio();
}

$('canalGrid').addEventListener('click', e => {
  const el = e.target.closest('.canal-opt'); if (!el) return;
  selectCanal(el.dataset.canal);
});

const dniInput = $('dni'), btnBuscar = $('btnBuscar'), dniCounter = $('dniCounter');
dniInput.addEventListener('input', () => {
  dniInput.value = dniInput.value.replace(/\D/g, '').slice(0, 8);
  const n = dniInput.value.length;
  dniCounter.textContent = n + ' / 8 dígitos';
  dniCounter.classList.toggle('ok', n === 8);
  btnBuscar.disabled = n !== 8;
});

$('formDni').addEventListener('submit', async e => {
  e.preventDefault();
  const dni = dniInput.value;
  $('msgError').style.display = 'none';
  btnBuscar.disabled = true;
  $('icoBuscar').style.display = 'none'; $('spinner').style.display = 'block'; $('btnTxt').textContent = 'Consultando…';
  try {
    const res = await fetch(API + 'identificar_colaborador.php?dni=' + dni, { cache: 'no-store' });
    const j = await res.json();
    if (!j.success) {
      $('msgErrorTxt').textContent = j.error || 'No se encontró el colaborador.';
      $('msgError').style.display = 'flex';
      btnBuscar.disabled = false;
      return;
    }
    actual = j;
    pintarResultado();
  } catch (err) {
    $('msgErrorTxt').textContent = 'Error de conexión. Inténtalo de nuevo.';
    $('msgError').style.display = 'flex';
    btnBuscar.disabled = false;
  } finally {
    $('icoBuscar').style.display = 'block'; $('spinner').style.display = 'none'; $('btnTxt').textContent = 'Continuar';
  }
});

function pintarResultado(){
  $('formDni').style.display = 'none';
  pintarPerfil(false);
  $('resultado').style.display = 'flex';
  $('btnNueva').style.display = 'flex';
}

const detalleInput = $('detalle'), btnEnviar = $('btnEnviar'), detalleCounter = $('detalleCounter');
detalleInput.addEventListener('input', () => {
  detalleCounter.textContent = detalleInput.value.length + ' / 2000';
  validarEnvio();
});
function validarEnvio(){
  if (canalSel && canalSel.key === 'encuesta') {
    btnEnviar.disabled = encuestaVacia();
    return;
  }
  btnEnviar.disabled = !(canalSel && detalleInput.value.trim().length > 0);
}

const MAX_ARCHIVOS = <?= SG_MAX_ARCHIVOS ?>;
const MAX_BYTES    = <?= SG_MAX_BYTES ?>;
const adjInput = $('adjInput'), adjDrop = $('adjDrop'), adjLista = $('adjLista');
let adjuntos = [];

function fmtPeso(b){
  if (b < 1024) return b + ' B';
  if (b < 1048576) return (b / 1024).toFixed(0) + ' KB';
  return (b / 1048576).toFixed(1) + ' MB';
}

function renderAdjuntos(){
  adjLista.innerHTML = adjuntos.map((f, i) => `
    <div class="adj-item">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
      <div class="adj-meta">
        <div class="adj-nombre">${f.name.replace(/</g, '&lt;')}</div>
        <div class="adj-peso">${fmtPeso(f.size)}</div>
      </div>
      <button type="button" class="adj-quitar" data-i="${i}" aria-label="Quitar">&times;</button>
    </div>`).join('');

  const lleno = adjuntos.length >= MAX_ARCHIVOS;
  adjDrop.classList.toggle('lleno', lleno);
  $('adjDropTxt').textContent = lleno
    ? `Máximo ${MAX_ARCHIVOS} archivos alcanzado`
    : (adjuntos.length ? 'Añadir otro archivo' : 'Adjuntar archivo (opcional)');
}

adjDrop.addEventListener('click', () => {
  if (adjuntos.length < MAX_ARCHIVOS) adjInput.click();
});

adjInput.addEventListener('change', () => {
  $('msgError').style.display = 'none';
  for (const f of adjInput.files) {
    if (adjuntos.length >= MAX_ARCHIVOS) {
      mostrarError(`Solo puedes adjuntar hasta ${MAX_ARCHIVOS} archivos.`); break;
    }
    if (f.size > MAX_BYTES) {
      mostrarError(`"${f.name}" pesa ${fmtPeso(f.size)}. El límite es 4 MB por archivo.`); continue;
    }
    if (adjuntos.some(a => a.name === f.name && a.size === f.size)) continue;
    adjuntos.push(f);
  }
  adjInput.value = '';
  renderAdjuntos();
});

adjLista.addEventListener('click', e => {
  const b = e.target.closest('.adj-quitar'); if (!b) return;
  adjuntos.splice(Number(b.dataset.i), 1);
  renderAdjuntos();
});

function limpiarAdjuntos(){ adjuntos = []; adjInput.value = ''; renderAdjuntos(); }
function mostrarError(txt){ $('msgErrorTxt').textContent = txt; $('msgError').style.display = 'flex'; }

$('btnEnviar').addEventListener('click', async () => {
  if (!actual || !canalSel) return;
  const esEncuesta = canalSel.key === 'encuesta';
  const detalle = esEncuesta ? JSON.stringify(leerEncuesta()) : detalleInput.value.trim();
  if (esEncuesta ? encuestaVacia() : !detalle) return;
  btnEnviar.disabled = true;
  $('spEnviar').style.display = 'block';
  try {
    const fd = new FormData();
    fd.append('dni', dniInput.value);
    fd.append('canal', canalSel.key);
    fd.append('detalle', detalle);
    if (!esEncuesta) adjuntos.forEach(f => fd.append('archivos[]', f, f.name));

    const res = await fetch(API + 'guardar_sugerencia.php', { method: 'POST', body: fd });
    const j = await res.json();
    if (!j.success) {
      mostrarError(j.error || 'No se pudo enviar.');
      btnEnviar.disabled = false;
      return;
    }
    const etiqueta = canalSel.label.toLowerCase();
    $('detalleWrap').style.display = 'none';
    $('canalAnon').style.display = 'none';
    document.querySelectorAll('.canal-opt').forEach(el => el.classList.remove('sel'));

    let msg = '¡Gracias! Tu ' + etiqueta + ' se registró correctamente.';
    if (j.adjuntos_pendientes > 0) {
      msg += ' Nota: ' + j.adjuntos_pendientes + ' archivo(s) quedaron guardados en el servidor y se subirán a Drive más tarde.';
    }
    $('msgExitoTxt').textContent = msg;
    $('msgExito').style.display = 'flex';
    limpiarAdjuntos();
    limpiarEncuesta();
    pintarPerfil(false);
    canalSel = null;
  } catch (err) {
    mostrarError('Error de conexión. Inténtalo de nuevo.');
  } finally {
    btnEnviar.disabled = false;
    $('spEnviar').style.display = 'none';
  }
});

$('btnNueva').addEventListener('click', () => {
  actual = null; canalSel = null;
  dniInput.value = ''; dniCounter.textContent = '0 / 8 dígitos'; dniCounter.classList.remove('ok');
  btnBuscar.disabled = true;
  detalleInput.value = ''; detalleCounter.textContent = '0 / 2000';
  limpiarAdjuntos();
  limpiarEncuesta();
  $('detalleWrap').style.display = 'none';
  $('canalAnon').style.display = 'none';
  $('msgExito').style.display = 'none';
  $('msgError').style.display = 'none';
  document.querySelectorAll('.canal-opt').forEach(el => el.classList.remove('sel'));
  $('resultado').style.display = 'none';
  $('btnNueva').style.display = 'none';
  $('formDni').style.display = 'block';
  dniInput.focus();
});
</script>
</body>
</html>
