<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Auto-registro de turno — Tallyman Control</title>
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
.page{ position:relative; z-index:1; width:100%; max-width:980px; display:grid; grid-template-columns:1fr 420px; gap:48px; align-items:center; }
.col-info{ display:flex; flex-direction:column; gap:20px; }
.eyebrow{ display:flex; align-items:center; gap:10px; }
.eyebrow-line{ width:24px; height:2px; background:#4ade80; border-radius:2px; }
.eyebrow-text{ font-size:10px; font-weight:700; letter-spacing:2.6px; text-transform:uppercase; color:rgba(255,255,255,.72); }
.col-titulo{ font-size:40px; font-weight:800; color:#fff; line-height:1.1; letter-spacing:-1px; text-shadow:0 2px 12px rgba(0,0,0,.5); }
.col-titulo span{ color:#5eead4; }
.col-desc{ font-size:14px; color:rgba(255,255,255,.82); line-height:1.7; max-width:380px; }
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
.card-body{ padding:22px; }

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
.btn-warn{ background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%); color:#fff; box-shadow:0 4px 16px rgba(245,158,11,.3); }
.btn-ok{ background:linear-gradient(135deg,#10b981 0%,#047857 100%); color:#fff; box-shadow:0 4px 16px rgba(16,185,129,.3); }
.spinner{ width:14px; height:14px; border:2px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; display:none; }
@keyframes spin{ to{ transform:rotate(360deg); } }

.msg{ display:none; align-items:center; gap:8px; border-radius:10px; padding:10px 14px; margin-top:12px; font-size:12.5px; font-weight:500; }
.msg svg{ flex-shrink:0; width:15px; height:15px; }
.msg.error{ background:#FEF2F2; border:1px solid #FECACA; color:var(--rojo); }
.msg.exito{ background:#ECFDF5; border:1px solid #A7F3D0; color:#047857; }

.resultado{ display:none; flex-direction:column; animation:fadeUp .35s ease; }
@keyframes fadeUp{ from{ opacity:0; transform:translateY(10px);} to{ opacity:1; transform:translateY(0);} }
.perfil-top{ display:flex; align-items:center; gap:13px; padding-bottom:16px; border-bottom:1px solid #EEF2F7; margin-bottom:14px; }
.avatar{ width:52px; height:52px; border-radius:13px; background:linear-gradient(135deg,var(--verde),var(--verde-osc)); display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:800; color:#fff; }
.perfil-nombre{ font-size:15px; font-weight:700; color:var(--texto-main); line-height:1.2; }
.perfil-cargo{ font-size:12px; color:var(--gris-texto); margin-top:3px; }
.badge{ margin-left:auto; padding:4px 10px; border-radius:20px; font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; }
.badge.activo{ background:#DCFCE7; color:#15803D; }
.badge.refrigerio{ background:#FEF3C7; color:#B45309; }
.badge.fuera{ background:#E5EAF0; color:#475569; }

.turno-chip{ display:flex; align-items:center; gap:8px; background:#F0FDF4; border:1px solid #BBF7D0; border-radius:10px; padding:10px 12px; margin-bottom:14px; font-size:12.5px; color:#047857; font-weight:600; }
.turno-chip svg{ width:15px; height:15px; }

.datos-grid{ display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:16px; }
.dato{ background:var(--gris-fondo); border-radius:10px; padding:10px 12px; }
.dato-label{ font-size:9.5px; font-weight:700; letter-spacing:.8px; text-transform:uppercase; color:#B0B8C4; margin-bottom:3px; }
.dato-valor{ font-size:13px; font-weight:600; color:var(--texto-main); }

.accion-box{ display:none; flex-direction:column; gap:10px; }
.select-ubic{ width:100%; padding:12px 14px; border-radius:10px; border:1.5px solid #D1D9E0; font-size:14px; font-family:var(--fuente); color:var(--texto-main); background:#fff; outline:none; }
.select-ubic:focus{ border-color:var(--verde); box-shadow:0 0 0 3px rgba(0,135,90,.14); }
.geo{ font-size:12.5px; font-weight:600; padding:9px 12px; border-radius:9px; display:flex; align-items:center; gap:7px; }
.geo-wait{ background:#EFF6FF; border:1px solid #BFDBFE; color:#1d4ed8; }
.geo-ok{ background:#ECFDF5; border:1px solid #A7F3D0; color:#047857; }
.geo-bad{ background:#FEF2F2; border:1px solid #FECACA; color:#b91c1c; }
.foto-box{ position:relative; width:100%; aspect-ratio:4/3; background:#0b1220; border-radius:12px; overflow:hidden; display:flex; align-items:center; justify-content:center; }
.foto-box video, .foto-box img{ width:100%; height:100%; object-fit:cover; display:none; }
.foto-ph{ color:#64748b; font-size:13px; }
.foto-btns{ display:flex; gap:8px; margin-top:8px; }
.btn-sm{ flex:1; padding:9px; border-radius:9px; border:1.5px solid #D1D9E0; background:#fff; color:var(--gris-texto); font-family:var(--fuente); font-size:12.5px; font-weight:700; cursor:pointer; }
.btn-sm:hover{ border-color:var(--verde); color:var(--verde); }
.btn-sm-primary{ background:var(--verde); color:#fff; border-color:var(--verde); }
.btn-sm-primary:hover{ color:#fff; filter:brightness(1.05); }

.btn-nueva{ width:100%; padding:10px; background:transparent; color:var(--verde); font-size:12px; font-weight:700; font-family:var(--fuente);
  border:1px dashed #A7F3D0; border-radius:10px; cursor:pointer; margin-top:12px; display:none; align-items:center; justify-content:center; gap:6px; }
.btn-nueva:hover{ background:#F0FDF4; border-color:var(--verde); }

.card-footer{ padding:11px 22px; background:var(--gris-fondo); border-top:1px solid #E5EAF0; display:flex; align-items:center; justify-content:space-between; }
.card-footer-text{ font-size:10px; color:#B0B8C4; font-weight:500; }
.dot-online{ width:7px; height:7px; border-radius:50%; background:#4ADE80; box-shadow:0 0 7px #4ADE80; }

@media (max-width:820px){
  .page{ grid-template-columns:1fr; max-width:440px; }
  .col-info{ display:none; }
  body{ padding:74px 16px 28px; align-items:flex-start; }
}
</style>
</head>
<body>
<div class="bg"></div>

<nav class="topbar">
  <div><b>Tallyman <span>Control</span></b><div class="topbar-label">Shift Command · Auto-registro</div></div>
  <img src="img/logo.jpg" alt="Logo" style="height:34px;border-radius:7px">
</nav>

<div class="page">
  <div class="col-info">
    <div class="eyebrow"><div class="eyebrow-line"></div><span class="eyebrow-text">Registro de turno</span></div>
    <div class="col-titulo">Marca tu <span>ingreso</span><br>y tu refrigerio</div>
    <p class="col-desc">Ingresa tu número de DNI para registrar tu ingreso al turno vigente o para marcar el inicio/fin de tu refrigerio. Rápido y sin contraseña.</p>
    <div class="chips">
      <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Ingreso al turno</div>
      <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Refrigerio</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-header-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="12" r="2"/><path d="M14 9h4M14 12h4M14 15h2"/></svg>
      </div>
      <div>
        <div class="card-header-titulo">Registro por DNI</div>
        <div class="card-header-sub">Ingresa tu documento de identidad</div>
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
          <span id="btnTxt">Consultar</span>
        </button>
        <div class="msg error" id="msgError"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span id="msgErrorTxt"></span></div>
      </form>

      <div class="resultado" id="resultado">
        <div class="perfil-top">
          <div class="avatar" id="resAvatar">--</div>
          <div>
            <div class="perfil-nombre" id="resNombre">—</div>
            <div class="perfil-cargo" id="resCargo">—</div>
          </div>
          <span class="badge activo" id="resBadge">—</span>
        </div>

        <div class="turno-chip">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span id="resTurno">—</span>
        </div>

        <div class="datos-grid">
          <div class="dato"><div class="dato-label">DNI</div><div class="dato-valor" id="resDni">—</div></div>
          <div class="dato"><div class="dato-label">Código</div><div class="dato-valor" id="resCodigo">—</div></div>
          <div class="dato"><div class="dato-label">Función</div><div class="dato-valor" id="resFuncion">—</div></div>
          <div class="dato"><div class="dato-label">Team</div><div class="dato-valor" id="resTeam">—</div></div>
        </div>

        <!-- Acción: INGRESO -->
        <div class="accion-box" id="accIngreso">
          <div class="geo geo-wait" id="geoStatus">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span id="geoTxt">Verificando tu ubicación…</span>
          </div>
          <div>
            <label class="campo-label">Ubicación donde trabajarás</label>
            <select class="select-ubic" id="selUbic"><option value="">— Selecciona ubicación —</option></select>
          </div>
          <div>
            <label class="campo-label">Foto de asistencia</label>
            <div class="foto-box">
              <video id="cam" autoplay playsinline muted></video>
              <img id="fotoPrev" alt="">
              <div class="foto-ph" id="fotoPh">Sin foto</div>
            </div>
            <canvas id="canvas" style="display:none"></canvas>
            <input type="file" id="fotoFile" accept="image/*" capture="user" style="display:none">
            <div class="foto-btns">
              <button type="button" class="btn-sm" id="btnCam">Abrir cámara</button>
              <button type="button" class="btn-sm btn-sm-primary" id="btnSnap" style="display:none">Tomar foto</button>
              <button type="button" class="btn-sm" id="btnRetake" style="display:none">Repetir</button>
            </div>
          </div>
          <button class="btn btn-primario" id="btnIngreso" disabled>
            <div class="spinner" id="spIngreso"></div>
            <span>Registrar ingreso al turno</span>
          </button>
        </div>

        <!-- Acción: INICIAR REFRIGERIO -->
        <div class="accion-box" id="accRefriIni">
          <button class="btn btn-warn" id="btnRefriIni"><div class="spinner" id="spRefriIni"></div><span>Iniciar refrigerio</span></button>
        </div>

        <!-- Acción: TERMINAR REFRIGERIO -->
        <div class="accion-box" id="accRefriFin">
          <div>
            <label class="campo-label">¿Tienes radio asignado?</label>
            <select class="select-ubic" id="selRadioFin">
              <option value="0">Sin radio</option>
              <option value="1">Con radio</option>
            </select>
          </div>
          <button class="btn btn-ok" id="btnRefriFin"><div class="spinner" id="spRefriFin"></div><span>Terminar refrigerio</span></button>
        </div>

        <!-- Ciclo del turno completo (ingreso + refrigerio ya hechos) -->
        <div class="geo geo-ok" id="cicloCompleto" style="display:none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <span>Ya registraste tu ingreso y tu refrigerio de hoy. No necesitas hacer nada más.</span>
        </div>

        <div class="msg exito" id="msgExito"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span id="msgExitoTxt"></span></div>

        <button class="btn-nueva" id="btnNueva"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>Nuevo registro</button>
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
let actual = null; // { colaborador, turno, ... }

// ── Geocerca + cámara (solo asistencia / ingreso) ──
const GEO_LAT = -11.58901, GEO_LNG = -77.2748, GEO_RADIO = 300;
let geoOk = false, geoCoords = null, fotoData = null, camStream = null;

function distM(la1, lo1, la2, lo2){
  const R = 6371000, r = x => x * Math.PI / 180;
  const dLa = r(la2 - la1), dLo = r(lo2 - lo1);
  const a = Math.sin(dLa/2)**2 + Math.cos(r(la1)) * Math.cos(r(la2)) * Math.sin(dLo/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}
function setGeo(cls, txt){ $('geoStatus').className = 'geo ' + cls; $('geoTxt').textContent = txt; }
function updateIngresoBtn(){ $('btnIngreso').disabled = !(geoOk && fotoData && $('selUbic').value); }

function startGeo(){
  geoOk = false; geoCoords = null; setGeo('geo-wait', 'Verificando tu ubicación…'); updateIngresoBtn();
  if (!navigator.geolocation){ setGeo('geo-bad', 'Tu dispositivo no soporta geolocalización.'); return; }
  navigator.geolocation.getCurrentPosition(p => {
    geoCoords = { lat: p.coords.latitude, lng: p.coords.longitude };
    const d = distM(GEO_LAT, GEO_LNG, geoCoords.lat, geoCoords.lng);
    if (d <= GEO_RADIO){ geoOk = true; setGeo('geo-ok', 'Dentro del área permitida (' + Math.round(d) + ' m).'); }
    else { geoOk = false; setGeo('geo-bad', 'Estás fuera del área (' + Math.round(d) + ' m). Máximo ' + GEO_RADIO + ' m.'); }
    updateIngresoBtn();
  }, () => {
    geoOk = false; setGeo('geo-bad', 'No se pudo obtener tu ubicación. Activa el GPS y permite el acceso.'); updateIngresoBtn();
  }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
}

function stopCam(){ if (camStream){ camStream.getTracks().forEach(t => t.stop()); camStream = null; } }
function resetFoto(){
  fotoData = null; stopCam();
  $('cam').style.display = 'none';
  $('fotoPrev').style.display = 'none'; $('fotoPrev').src = '';
  $('fotoPh').style.display = 'block';
  $('btnCam').style.display = ''; $('btnSnap').style.display = 'none'; $('btnRetake').style.display = 'none';
  updateIngresoBtn();
}
async function startCam(){
  try {
    camStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
    const v = $('cam'); v.srcObject = camStream; v.style.display = 'block';
    $('fotoPh').style.display = 'none'; $('fotoPrev').style.display = 'none';
    $('btnCam').style.display = 'none'; $('btnSnap').style.display = ''; $('btnRetake').style.display = 'none';
  } catch (e) {
    // Sin cámara/permiso: usar input de archivo (en móvil abre la cámara).
    $('fotoFile').click();
  }
}
// Dibuja una imagen (video o Image) reducida a máx. 640px y guarda el JPEG en fotoData.
function capturarDesde(fuente, w, h){
  const scale = Math.min(1, 640 / w);
  const cw = Math.round(w * scale), ch = Math.round(h * scale);
  const cv = $('canvas'); cv.width = cw; cv.height = ch;
  cv.getContext('2d').drawImage(fuente, 0, 0, cw, ch);
  fotoData = cv.toDataURL('image/jpeg', 0.8);
  const im = $('fotoPrev'); im.src = fotoData; im.style.display = 'block';
  $('fotoPh').style.display = 'none'; $('cam').style.display = 'none';
  $('btnCam').style.display = 'none'; $('btnSnap').style.display = 'none'; $('btnRetake').style.display = '';
  updateIngresoBtn();
}
function snap(){ const v = $('cam'); if (!v.videoWidth) return; capturarDesde(v, v.videoWidth, v.videoHeight); stopCam(); }

$('btnCam').addEventListener('click', startCam);
$('btnSnap').addEventListener('click', snap);
$('btnRetake').addEventListener('click', () => { resetFoto(); startCam(); });
$('selUbic').addEventListener('change', updateIngresoBtn);
$('fotoFile').addEventListener('change', e => {
  const f = e.target.files[0]; if (!f) return;
  const rd = new FileReader();
  rd.onload = () => { const img = new Image(); img.onload = () => capturarDesde(img, img.width, img.height); img.src = rd.result; };
  rd.readAsDataURL(f);
});

function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function initials(n){ const w = String(n||'').trim().split(/\s+/); return ((w[0]?.[0]||'')+(w[1]?.[0]||'')).toUpperCase(); }
function showErr(m){ $('msgErrorTxt').textContent = m; $('msgError').style.display='flex'; }
function hideErr(){ $('msgError').style.display='none'; }
function spin(btnSp, on){ btnSp.style.display = on ? 'block' : 'none'; }

$('dni').addEventListener('input', e => {
  e.target.value = e.target.value.replace(/\D/g,'');
  const len = e.target.value.length;
  $('dniCounter').textContent = len + ' / 8 dígitos';
  $('dniCounter').className = 'dni-counter' + (len===8 ? ' ok' : '');
  $('btnBuscar').disabled = len !== 8;
  hideErr();
});

$('formDni').addEventListener('submit', async e => {
  e.preventDefault();
  const dni = $('dni').value.trim();
  if (dni.length !== 8) return;
  hideErr();
  spin($('spinner'), true); $('icoBuscar').style.display='none'; $('btnTxt').textContent='Consultando…'; $('btnBuscar').disabled=true;
  try{
    const r = await fetch(API + 'buscar_colaborador.php?dni=' + encodeURIComponent(dni));
    const j = await r.json();
    spin($('spinner'), false); $('icoBuscar').style.display='block'; $('btnTxt').textContent='Consultar'; $('btnBuscar').disabled=false;
    if (!j.success){ showErr(j.error || 'No se encontró el colaborador.'); return; }
    actual = j;
    pintarResultado(j);
  }catch(err){
    spin($('spinner'), false); $('icoBuscar').style.display='block'; $('btnTxt').textContent='Consultar'; $('btnBuscar').disabled=false;
    showErr('Error de conexión. Intenta nuevamente.');
  }
});

function pintarResultado(j){
  const c = j.colaborador;
  $('resAvatar').textContent = initials(c.nombre) || '--';
  $('resNombre').textContent = c.nombre;
  $('resCargo').textContent  = c.funcion || 'Sin función';
  $('resDni').textContent    = c.dni;
  $('resCodigo').textContent = c.codigo;
  $('resFuncion').textContent= c.funcion || '—';
  $('resTeam').textContent   = c.cuadrilla || '—';
  $('resTurno').textContent  = 'Turno vigente: ' + j.turno.label;

  // Badge de estado
  const b = $('resBadge');
  if (!j.enTurno){ b.textContent='Fuera de turno'; b.className='badge fuera'; }
  else if (j.estado==='refrigerio'){ b.textContent='En refrigerio'; b.className='badge refrigerio'; }
  else { b.textContent='Activo'; b.className='badge activo'; }

  // Reset acciones
  ['accIngreso','accRefriIni','accRefriFin'].forEach(id => $(id).style.display='none');
  $('msgExito').style.display='none';
  $('cicloCompleto').style.display='none';

  stopCam();
  if (j.turno.estado === 'cerrado'){
    showErr('El turno vigente está cerrado. Consulta con tu coordinador.');
  } else if (!j.enTurno){
    // Poblar ubicaciones y mostrar ingreso (con geocerca + foto obligatoria)
    const sel = $('selUbic');
    sel.innerHTML = '<option value="">— Selecciona ubicación —</option>' +
      (j.ubicaciones||[]).map(u => '<option value="'+esc(u)+'">'+esc(u)+'</option>').join('');
    resetFoto();
    $('accIngreso').style.display='flex';
    startGeo();
  } else if (j.refrigerioAbierto){
    // Solo si hay un evento de refrigerio REALMENTE abierto (con inicio y sin fin).
    // Se confía en el evento, no en el estado: si el coordinador borró el evento,
    // el colaborador podrá volver a iniciar su refrigerio.
    $('accRefriFin').style.display='flex';
  } else if (j.refrigerioCompletado){
    // Ya hizo ingreso + refrigerio: ciclo del turno completo, sin más acciones.
    $('cicloCompleto').style.display='flex';
  } else {
    $('accRefriIni').style.display='flex';
  }

  $('formDni').style.display='none';
  $('resultado').style.display='flex';
  $('btnNueva').style.display='flex';
}

// Registrar ingreso
$('btnIngreso').addEventListener('click', async () => {
  const ubic = $('selUbic').value;
  if (!ubic){ showErr('Selecciona tu ubicación.'); return; }
  if (!geoOk || !geoCoords){ showErr('Debes estar dentro del área permitida (≤300 m) para registrar.'); return; }
  if (!fotoData){ showErr('Toma la foto de asistencia.'); return; }
  hideErr(); spin($('spIngreso'), true); $('btnIngreso').disabled=true;
  await accion('registrar_ingreso.php',
    { dni: actual.colaborador.dni, ubicacion: ubic, lat: geoCoords.lat, lng: geoCoords.lng, foto: fotoData },
    $('spIngreso'), $('btnIngreso'));
});
// Iniciar refrigerio
$('btnRefriIni').addEventListener('click', async () => {
  hideErr(); spin($('spRefriIni'), true); $('btnRefriIni').disabled=true;
  await accion('refrigerio.php', { dni: actual.colaborador.dni, accion:'iniciar' }, $('spRefriIni'), $('btnRefriIni'));
});
// Terminar refrigerio
$('btnRefriFin').addEventListener('click', async () => {
  hideErr(); spin($('spRefriFin'), true); $('btnRefriFin').disabled=true;
  await accion('refrigerio.php', { dni: actual.colaborador.dni, accion:'terminar', radio: $('selRadioFin').value }, $('spRefriFin'), $('btnRefriFin'));
});

async function accion(endpoint, body, sp, btn){
  try{
    const r = await fetch(API + endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
    const j = await r.json();
    spin(sp, false); btn.disabled=false;
    if (!j.success){ showErr(j.error || 'No se pudo registrar.'); return; }
    stopCam();
    ['accIngreso','accRefriIni','accRefriFin'].forEach(id => $(id).style.display='none');
    $('msgExitoTxt').textContent = j.mensaje || 'Registrado correctamente.';
    $('msgExito').style.display='flex';
  }catch(err){
    spin(sp, false); btn.disabled=false;
    showErr('Error de conexión. Intenta nuevamente.');
  }
}

$('btnNueva').addEventListener('click', () => {
  actual = null; stopCam(); resetFoto();
  $('dni').value=''; $('dniCounter').textContent='0 / 8 dígitos'; $('dniCounter').className='dni-counter';
  $('btnBuscar').disabled=true; hideErr();
  $('resultado').style.display='none'; $('btnNueva').style.display='none';
  $('formDni').style.display='block';
  $('dni').focus();
});
</script>
</body>
</html>
