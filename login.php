<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · Estiba Shift Command Deck</title>
<link rel="icon" type="image/png" href="img/logo.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
  --azul-oscuro:  #005c3d;
  --azul-medio:   #00875A;
  --azul-claro:   #00b377;
  --blanco:       #ffffff;
  --gris-fondo:   #EEF2F7;
  --gris-texto:   #6B7280;
  --texto-main:   #0D1A2E;
  --rojo-error:   #DC2626;
  --verde-ok:     #16A34A;
  --fuente:       'DM Sans', 'Segoe UI', sans-serif;
}

html, body { height: 100%; font-family: var(--fuente); }
body {
  display: flex; justify-content: center; align-items: center;
  min-height: 100vh;
  background-image: url('img/puerto.jpg');
  background-size: cover; background-position: center;
  overflow-x: hidden;
}
body::before {
  content: ''; position: fixed; inset: 0;
  background: rgba(4, 28, 60, 0.55);
  backdrop-filter: blur(2px);
  z-index: 0;
}

/* ════════════════════════ CONTENEDOR ════════════════════════ */
.cuerpo {
  position: relative; z-index: 1;
  display: flex;
  width: 92%; max-width: 1100px;
  height: 82vh; min-height: 520px;
  border-radius: 20px; overflow: hidden;
  box-shadow:
    0 4px 6px rgba(0,0,0,0.10),
    0 16px 48px rgba(0,0,0,0.40),
    0 32px 80px rgba(0,0,0,0.20);
  flex-wrap: wrap;
}

/* ════════════════════════ PANEL IZQUIERDO ════════════════════════ */
.left {
  flex: 1;
  background: var(--blanco);
  padding: 44px 36px;
  display: flex; flex-direction: column; justify-content: center;
  min-width: 300px;
}

.logo-cosco-left {
  width: 150px;
  margin: 0 auto 28px;
  display: block;
  object-fit: contain;
}

.titulo-label {
  font-size: 10px; font-weight: 600; letter-spacing: 2.5px;
  text-transform: uppercase; color: var(--azul-medio);
  text-align: center; margin-bottom: 6px;
}
#titulo {
  font-size: 20px; font-weight: 600; color: var(--texto-main);
  text-align: center; line-height: 1.3; min-height: 52px;
  margin-bottom: 22px;
  display: flex; justify-content: center; align-items: center;
  white-space: nowrap; overflow: hidden;
}
.cursor {
  display: inline-block; width: 2px; height: 1.1em;
  background: var(--azul-medio); margin-left: 3px;
  vertical-align: text-bottom; flex-shrink: 0;
  animation: parpadeo 1s step-end infinite;
}
@keyframes parpadeo { 0%,100% { opacity: 1; } 50% { opacity: 0; } }

.separador {
  height: 1px;
  background: linear-gradient(to right, transparent, rgba(0,0,0,0.08), transparent);
  margin-bottom: 22px;
}

.campo { margin-bottom: 14px; }
.campo label {
  display: block; font-size: 11px; font-weight: 600; letter-spacing: 0.8px;
  text-transform: uppercase; color: var(--gris-texto); margin-bottom: 6px;
}
.input-wrap { position: relative; }
.input-wrap .icono {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  width: 15px; height: 15px; color: #B0B8C4; pointer-events: none;
  transition: color 0.2s;
}
.input-wrap input {
  width: 100%; padding: 11px 14px 11px 36px;
  border: 1px solid #D1D9E0; border-radius: 10px;
  font-size: 13px; font-family: var(--fuente); color: var(--texto-main);
  background: var(--gris-fondo); outline: none;
  transition: border 0.2s, box-shadow 0.2s, background 0.2s;
}
.input-wrap input::placeholder { color: #C0C8D2; }
.input-wrap input:focus {
  border-color: var(--azul-medio); background: var(--blanco);
  box-shadow: 0 0 0 3px rgba(0,135,90,0.13);
}
.input-wrap input.input-error {
  border-color: var(--rojo-error); background: #FEF2F2;
  box-shadow: 0 0 0 3px rgba(220,38,38,0.10);
}
.input-wrap input.input-ok {
  border-color: var(--verde-ok); background: var(--blanco);
  box-shadow: 0 0 0 3px rgba(22,163,74,0.10);
}
.input-wrap.error .icono { color: var(--rojo-error); }
.input-wrap.ok    .icono { color: var(--verde-ok); }

.msg-error {
  display: none; align-items: center; gap: 5px;
  font-size: 11px; color: var(--rojo-error);
  margin-top: 6px; font-weight: 500;
}
.msg-error.visible { display: flex; }
.msg-error svg { flex-shrink: 0; }

.alerta-credenciales {
  display: none; align-items: center; gap: 10px;
  background: #FEF2F2; border: 1px solid #FECACA; border-radius: 10px;
  padding: 11px 14px; margin-bottom: 16px;
  font-size: 12px; color: var(--rojo-error); font-weight: 500;
  animation: shake 0.4s ease;
}
.alerta-credenciales.visible { display: flex; }
.alerta-credenciales svg { flex-shrink: 0; color: var(--rojo-error); }

@keyframes shake {
  0%,100% { transform: translateX(0); }
  20%     { transform: translateX(-6px); }
  40%     { transform: translateX(6px); }
  60%     { transform: translateX(-4px); }
  80%     { transform: translateX(4px); }
}

.opciones {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 18px;
}
.recuerdame {
  display: flex; align-items: center; gap: 7px;
  font-size: 12px; color: var(--gris-texto);
  cursor: pointer; user-select: none;
}
.recuerdame input[type="checkbox"] {
  accent-color: var(--azul-medio); width: 14px; height: 14px; cursor: pointer;
}
.olvide {
  font-size: 12px; color: var(--azul-medio);
  background: none; border: none; cursor: pointer;
  font-family: var(--fuente); padding: 0;
  transition: color 0.2s;
}
.olvide:hover { color: var(--azul-oscuro); text-decoration: underline; }

.btn-ingresar {
  width: 100%; padding: 12px;
  background: var(--azul-medio); color: var(--blanco);
  font-size: 13px; font-weight: 600; font-family: var(--fuente);
  letter-spacing: 0.4px; border: none; border-radius: 10px;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
  box-shadow: 0 4px 14px rgba(0,135,90,0.30);
  display: flex; align-items: center; justify-content: center; gap: 8px;
  position: relative; overflow: hidden;
}
.btn-ingresar:hover:not(:disabled) {
  background: var(--azul-oscuro);
  box-shadow: 0 6px 18px rgba(0,92,61,0.35);
}
.btn-ingresar:active:not(:disabled) { transform: scale(0.99); }
.btn-ingresar:disabled { opacity: 0.75; cursor: not-allowed; }

.btn-spinner {
  display: none; width: 15px; height: 15px;
  border: 2px solid rgba(255,255,255,0.35);
  border-top-color: #fff; border-radius: 50%;
  animation: spin 0.7s linear infinite;
  flex-shrink: 0;
}
.btn-ingresar.cargando .btn-spinner { display: block; }
.btn-ingresar.cargando .btn-texto   { opacity: 0.7; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Demo credentials hint */
.demo-hint {
  margin-top: 16px;
  padding: 10px 12px;
  background: #EFF6FF;
  border: 1px dashed #BFDBFE;
  border-radius: 8px;
  font-size: 11px;
  color: #1D4ED8;
  text-align: center;
  font-family: ui-monospace, Consolas, monospace;
}
.demo-hint b { font-weight: 700; }

/* Overlay de éxito */
.overlay-exito {
  position: fixed; inset: 0;
  background: rgba(4, 44, 83, 0.0);
  z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none;
  transition: opacity 0.4s ease, background 0.6s ease;
}
.overlay-exito.activo {
  opacity: 1; pointer-events: all;
  background: rgba(4, 44, 83, 0.85);
}
.exito-box {
  background: #fff; border-radius: 20px;
  padding: 40px 44px; text-align: center;
  box-shadow: 0 24px 60px rgba(0,0,0,0.25);
  transform: scale(0.85);
  transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
  max-width: 320px; width: 90%;
}
.overlay-exito.activo .exito-box { transform: scale(1); }
.exito-icono {
  width: 64px; height: 64px; border-radius: 50%;
  background: #DCFCE7; display: flex;
  align-items: center; justify-content: center;
  margin: 0 auto 18px; color: var(--verde-ok);
}
.exito-titulo { font-size: 1.1rem; font-weight: 700; color: var(--texto-main); margin-bottom: 6px; }
.exito-sub    { font-size: 0.82rem; color: var(--gris-texto); margin-bottom: 20px; line-height: 1.5; }
.exito-barra-wrap { height: 4px; background: #E2E8F0; border-radius: 4px; overflow: hidden; }
.exito-barra { height: 100%; width: 0%; background: var(--azul-medio); border-radius: 4px; transition: width linear; }

.footer-login { margin-top: 18px; font-size: 11px; color: #C0C8D2; text-align: center; }

/* ════════════════════════ PANEL DERECHO · SLIDER ════════════════════════ */
.right {
  flex: 1.5; position: relative; overflow: hidden;
  min-height: 250px;
  display: flex; flex-direction: column; justify-content: flex-end;
}

.media-slide {
  position: absolute; inset: 0;
  opacity: 0;
  transition: opacity 1.4s ease-in-out;
  z-index: 0;
}
.media-slide.activo { opacity: 1; z-index: 1; }
.media-slide.es-imagen { background-size: cover; background-position: center; }
.media-slide.es-video video {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  object-fit: cover; object-position: center;
}
.media-slide::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(
    160deg,
    rgba(4,44,83,0.25) 0%,
    rgba(4,44,83,0.60) 50%,
    rgba(4,44,83,0.92) 100%
  );
  z-index: 2;
}
.media-slide::before {
  content: ''; position: absolute; inset: 0;
  background-image: radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
  background-size: 22px 22px;
  z-index: 3;
}

.logo-cosco {
  position: absolute; top: 18px; right: 18px;
  width: 72px; height: 72px;
  z-index: 10;
  object-fit: contain;
  background: rgba(255,255,255,0.95);
  border-radius: 14px;
  padding: 8px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.25);
}

.right-content { position: relative; z-index: 10; padding: 28px 32px 34px; }
.linea-acento {
  width: 28px; height: 2px; background: var(--azul-claro);
  border-radius: 2px; margin-bottom: 12px;
}
.right-etiqueta {
  font-size: 10px; font-weight: 600; letter-spacing: 2.5px;
  text-transform: uppercase; color: rgba(255,255,255,0.48);
  margin-bottom: 8px;
}
.right-titulo {
  font-size: 26px; font-weight: 600; color: var(--blanco);
  line-height: 1.25; margin-bottom: 20px;
}
.stats { display: flex; gap: 10px; flex-wrap: wrap; }
.stat {
  background: rgba(255,255,255,0.09);
  border: 1px solid rgba(255,255,255,0.14);
  border-radius: 10px;
  padding: 10px 16px;
  min-width: 76px;
  backdrop-filter: blur(4px);
}
.stat-num   { font-size: 18px; font-weight: 600; color: var(--blanco); line-height: 1; margin-bottom: 4px; }
.stat-desc  { font-size: 10px; color: rgba(255,255,255,0.48); }

.slide-dots {
  position: absolute; bottom: 14px; right: 32px;
  display: flex; gap: 7px; z-index: 10;
}
.dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: rgba(255,255,255,0.30);
  border: 1px solid rgba(255,255,255,0.40);
  cursor: pointer; transition: background 0.3s, transform 0.3s;
}
.dot.activo { background: var(--blanco); transform: scale(1.25); }

.progreso-barra {
  position: absolute; bottom: 0; left: 0;
  height: 3px; width: 0%;
  background: var(--azul-claro);
  z-index: 10; transition: width linear;
}

/* ════════════════════════ RESPONSIVE ════════════════════════ */
@media (max-width: 900px) {
  body { align-items: flex-start; }
  .cuerpo {
    flex-direction: column; width: 100%; max-width: 100%;
    height: auto; min-height: 100vh; border-radius: 0;
  }
  .right { order: -1; width: 100%; height: 220px; flex: none; }
  .right-titulo { font-size: 20px; }
  .stats { display: none; }
  .left { padding: 32px 24px 40px; }
  #titulo { font-size: 17px; min-height: 44px; }
}
@media (max-width: 480px) {
  .left { padding: 26px 18px 34px; }
  .right { height: 180px; }
  .right-titulo { font-size: 17px; }
  #titulo { font-size: 15px; }
  .logo-cosco-left { width: 120px; }
}
</style>
</head>
<body>

<!-- Overlay de éxito -->
<div class="overlay-exito" id="overlayExito">
  <div class="exito-box">
    <div class="exito-icono">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
        <polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
    </div>
    <div class="exito-titulo">¡Bienvenido!</div>
    <div class="exito-sub" id="exitoSub">Acceso verificado. Redirigiendo al sistema…</div>
    <div class="exito-barra-wrap">
      <div class="exito-barra" id="exitoBarra"></div>
    </div>
  </div>
</div>

<div class="cuerpo">

  <!-- ── PANEL IZQUIERDO ── -->
  <div class="left">

    <img src="img/logo.jpg" alt="COSCO Shipping" class="logo-cosco-left">

    <div class="titulo-label">Bienvenido</div>
    <h2 id="titulo">
      <span id="texto"></span><span class="cursor"></span>
    </h2>

    <div class="separador"></div>

    <form id="formLogin" onsubmit="return false;" novalidate>

      <div class="alerta-credenciales" id="alertaGlobal">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span id="alertaTexto">Credenciales incorrectas. Verifica tus datos.</span>
      </div>

      <div class="campo">
        <label for="email">Usuario</label>
        <div class="input-wrap" id="wrapEmail">
          <svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="4" width="20" height="16" rx="2"/>
            <polyline points="2,4 12,13 22,4"/>
          </svg>
          <input type="email" id="email" placeholder="email@estiba.local" autocomplete="email">
        </div>
        <div class="msg-error" id="errEmail">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <span id="errEmailTxt">Campo requerido</span>
        </div>
      </div>

      <div class="campo">
        <label for="password">Contraseña</label>
        <div class="input-wrap" id="wrapPass">
          <svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          <input type="password" id="password" placeholder="••••••••" autocomplete="current-password">
        </div>
        <div class="msg-error" id="errPass">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <span id="errPassTxt">Campo requerido</span>
        </div>
      </div>

      <div class="opciones">
        <label class="recuerdame">
          <input type="checkbox" id="remember">
          Recuérdame
        </label>
        <button type="button" class="olvide" id="btnOlvide">¿Olvidaste tu contraseña?</button>
      </div>

      <button type="submit" class="btn-ingresar" id="btnLogin">
        <div class="btn-spinner"></div>
        <span class="btn-texto">Ingresar</span>
      </button>

    </form>

    <div class="demo-hint">
      Demo · <b>admin@estiba.local</b> / <b>admin123</b>
    </div>

    <p class="footer-login">Estiba · Shift Command Deck · v1.0</p>

  </div>

  <!-- ── PANEL DERECHO · SLIDER ── -->
  <div class="right" id="slider">

    <div class="media-slide es-imagen activo"
         style="background-image: url('img/foto_cosco.jpg')">
    </div>

    <div class="media-slide es-video">
      <video src="img/puerto.mp4" muted loop playsinline preload="auto"></video>
    </div>

    <img src="img/logo.jpg" alt="Logo COSCO" class="logo-cosco">

    <div class="right-content">
      <div class="linea-acento"></div>
      <div class="right-etiqueta">Operaciones de Estiba</div>
      <div class="right-titulo">Shift Command<br>Deck</div>
      <div class="stats">
        <div class="stat">
          <div class="stat-num">24/7</div>
          <div class="stat-desc">Operación</div>
        </div>
        <div class="stat">
          <div class="stat-num">Live</div>
          <div class="stat-desc">Monitoreo</div>
        </div>
        <div class="stat">
          <div class="stat-num">Real</div>
          <div class="stat-desc">Tiempo</div>
        </div>
      </div>
    </div>

    <div class="slide-dots" id="dots"></div>
    <div class="progreso-barra" id="progreso"></div>
  </div>

</div>

<script>
const REDIRECCION = 'index.php';
const DELAY_MS    = 1600;

/* ════════════════════════════
   TYPING (línea única)
════════════════════════════ */
const TEXTO_TITULO = 'Plataforma asistente de estiba';
const spanTexto = document.getElementById('texto');
let idxChar = 0, typingDone = false;

function tick() {
  if (typingDone) return;
  idxChar++;
  spanTexto.textContent = TEXTO_TITULO.slice(0, idxChar);
  if (idxChar === TEXTO_TITULO.length) { typingDone = true; return; }
  setTimeout(tick, 60);
}

/* ════════════════════════════
   LOGIN
════════════════════════════ */
const formLogin    = document.getElementById('formLogin');
const inputEmail   = document.getElementById('email');
const inputPass    = document.getElementById('password');
const wrapEmail    = document.getElementById('wrapEmail');
const wrapPass     = document.getElementById('wrapPass');
const errEmail     = document.getElementById('errEmail');
const errEmailTxt  = document.getElementById('errEmailTxt');
const errPass      = document.getElementById('errPass');
const errPassTxt   = document.getElementById('errPassTxt');
const alertaGlobal = document.getElementById('alertaGlobal');
const alertaTexto  = document.getElementById('alertaTexto');
const btnLogin     = document.getElementById('btnLogin');
const overlayExito = document.getElementById('overlayExito');
const exitoSub     = document.getElementById('exitoSub');
const exitoBarra   = document.getElementById('exitoBarra');
const checkRemember = document.getElementById('remember');
const btnOlvide    = document.getElementById('btnOlvide');

function limpiarCampo(c) {
  alertaGlobal.classList.remove('visible');
  if (c === 'email') {
    inputEmail.classList.remove('input-error','input-ok');
    wrapEmail.classList.remove('error','ok');
    errEmail.classList.remove('visible');
  }
  if (c === 'pass') {
    inputPass.classList.remove('input-error','input-ok');
    wrapPass.classList.remove('error','ok');
    errPass.classList.remove('visible');
  }
}
inputEmail.addEventListener('input', () => limpiarCampo('email'));
inputPass.addEventListener('input',  () => limpiarCampo('pass'));

function setError(c, m) {
  if (c === 'email') {
    inputEmail.classList.add('input-error');
    wrapEmail.classList.add('error');
    errEmailTxt.textContent = m;
    errEmail.classList.add('visible');
  }
  if (c === 'pass') {
    inputPass.classList.add('input-error');
    wrapPass.classList.add('error');
    errPassTxt.textContent = m;
    errPass.classList.add('visible');
  }
}
function setOk(c) {
  if (c === 'email') { inputEmail.classList.add('input-ok'); wrapEmail.classList.add('ok'); }
  if (c === 'pass')  { inputPass.classList.add('input-ok');  wrapPass.classList.add('ok'); }
}
function mostrarAlertaGlobal(mensaje, tipo = 'error') {
  alertaTexto.textContent = mensaje;
  alertaGlobal.classList.remove('visible');
  if (tipo === 'error') {
    alertaGlobal.style.background = '#FEF2F2';
    alertaGlobal.style.borderColor = '#FECACA';
    alertaGlobal.style.color = '#DC2626';
    alertaGlobal.querySelector('svg').style.color = '#DC2626';
  } else if (tipo === 'info') {
    alertaGlobal.style.background = '#EFF6FF';
    alertaGlobal.style.borderColor = '#BFDBFE';
    alertaGlobal.style.color = '#1D4ED8';
    alertaGlobal.querySelector('svg').style.color = '#1D4ED8';
  }
  void alertaGlobal.offsetWidth;
  alertaGlobal.classList.add('visible');
}
function setBtnCargando(s) {
  btnLogin.classList.toggle('cargando', s);
  btnLogin.disabled = s;
}
function mostrarExito(usuario) {
  if (checkRemember.checked) localStorage.setItem('estibaUserRemember', usuario.email);
  else localStorage.removeItem('estibaUserRemember');

  exitoSub.textContent = `Bienvenido, ${usuario.nombre}. Redirigiendo…`;
  overlayExito.classList.add('activo');
  requestAnimationFrame(() => {
    exitoBarra.style.transition = `width ${DELAY_MS}ms linear`;
    exitoBarra.style.width = '100%';
  });
  setTimeout(() => { window.location.href = REDIRECCION; }, DELAY_MS);
}

async function intentarLogin() {
  const email = inputEmail.value.trim();
  const pass  = inputPass.value;
  let valido = true;

  limpiarCampo('email'); limpiarCampo('pass');

  if (!email) { setError('email','El correo es requerido.'); valido = false; }
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { setError('email','Ingresa un correo válido.'); valido = false; }
  else setOk('email');

  if (!pass) { setError('pass','La contraseña es requerida.'); valido = false; }
  else if (pass.length < 4) { setError('pass','Mínimo 4 caracteres.'); valido = false; }
  else setOk('pass');

  if (!valido) return;

  setBtnCargando(true);

  try {
    const res = await fetch('api/login.php', {
      method: 'POST',
      body: JSON.stringify({ email, password: pass }),
      headers: { 'Content-Type': 'application/json' }
    });
    const json = await res.json();
    if (json.success) {
      mostrarExito(json.user);
    } else {
      setBtnCargando(false);
      if (json.error && json.error.indexOf('ontraseña') !== -1) {
        setError('pass','Contraseña incorrecta.');
        mostrarAlertaGlobal('Contraseña incorrecta. Inténtalo de nuevo.');
      } else {
        setError('email','Usuario no registrado.');
        mostrarAlertaGlobal(json.error || 'Usuario no encontrado.');
      }
      const left = document.querySelector('.left');
      left.style.animation = 'none'; void left.offsetWidth;
      left.style.animation = 'shake 0.4s ease';
    }
  } catch (e) {
    console.error(e);
    setBtnCargando(false);
    mostrarAlertaGlobal('Error de conexión con el servidor.');
  }
}

formLogin.addEventListener('submit', e => { e.preventDefault(); intentarLogin(); });
btnLogin.addEventListener('click', intentarLogin);
inputEmail.addEventListener('keydown', e => { if (e.key === 'Enter') inputPass.focus(); });
inputPass.addEventListener('keydown',  e => { if (e.key === 'Enter') intentarLogin(); });
btnOlvide.addEventListener('click', () => {
  mostrarAlertaGlobal('Contacta al administrador del sistema.', 'info');
});

/* ════════════════════════════
   SLIDER (panel derecho)
════════════════════════════ */
const slides = document.querySelectorAll('.media-slide');
const dotsWrap = document.getElementById('dots');
const progreso = document.getElementById('progreso');
const TOTAL = slides.length;
const DUR_IMG = 6000;

let actual = 0;
let timer = null;

slides.forEach((_, i) => {
  const d = document.createElement('div');
  d.classList.add('dot');
  if (i === 0) d.classList.add('activo');
  d.addEventListener('click', () => { irA(i); reiniciarTimer(); });
  dotsWrap.appendChild(d);
});
const dots = document.querySelectorAll('.dot');

function irA(idx) {
  const prev = slides[actual];
  const prevVideo = prev.querySelector('video');
  if (prevVideo) prevVideo.pause();

  prev.classList.remove('activo');
  dots[actual].classList.remove('activo');

  actual = idx;
  const curr = slides[actual];
  const currVideo = curr.querySelector('video');

  curr.classList.add('activo');
  dots[actual].classList.add('activo');

  if (currVideo) { currVideo.currentTime = 0; currVideo.play(); }
}

function getDuracion() {
  const v = slides[actual].querySelector('video');
  if (v && v.duration && isFinite(v.duration)) return Math.min(v.duration * 1000, 15000);
  return DUR_IMG;
}
function animarProgreso(d) {
  progreso.style.transition = 'none';
  progreso.style.width = '0%';
  void progreso.offsetWidth;
  progreso.style.transition = `width ${d}ms linear`;
  progreso.style.width = '100%';
}
function siguiente() { irA((actual + 1) % TOTAL); reiniciarTimer(); }
function reiniciarTimer() {
  clearTimeout(timer);
  setTimeout(() => {
    const d = getDuracion();
    animarProgreso(d);
    timer = setTimeout(siguiente, d);
  }, 100);
}

document.getElementById('slider').addEventListener('mouseenter', () => {
  clearTimeout(timer);
  const anchoActual = parseFloat(getComputedStyle(progreso).width);
  const anchoTotal  = progreso.parentElement.offsetWidth;
  progreso.style.transition = 'none';
  progreso.style.width = ((anchoActual / anchoTotal) * 100) + '%';
});
document.getElementById('slider').addEventListener('mouseleave', reiniciarTimer);

/* ════════════════════════════
   INIT
════════════════════════════ */
window.addEventListener('load', () => {
  const remembered = localStorage.getItem('estibaUserRemember');
  if (remembered) { inputEmail.value = remembered; checkRemember.checked = true; }

  setTimeout(tick, 500);

  const videoInicial = slides[0].querySelector('video');
  if (videoInicial) videoInicial.play();

  reiniciarTimer();
});
</script>

</body>
</html>
