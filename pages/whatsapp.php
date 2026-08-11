<?php
require_once('../includes/auth.php');
require_once('../includes/whatsapp_baileys.php');
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'nuevo_qr') {
    [$ok, , $error] = wa_regenerar_qr();
    header('Location: whatsapp.php?estado=' . ($ok ? 'qr_solicitado' : 'error') . ($ok ? '' : '&error=' . rawurlencode($error)));
    exit;
}

[$disponible, $estado, $errorEstado] = wa_estado();
$conectado = !empty($estado['connected']);
$tieneQr = !empty($estado['qrAvailable']);
$conexionInfo = trim((string)($estado['connectionInfo'] ?? ''));
$qrSolicitado = ($_GET['estado'] ?? '') === 'qr_solicitado';
$errorAccion = trim((string)($_GET['error'] ?? ''));
$qrSvg = '';
if ($disponible && !$conectado && $tieneQr) {
    [$qrOk, $qrData] = wa_qr();
    if ($qrOk && !empty($qrData['svg'])) $qrSvg = base64_encode($qrData['svg']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WhatsApp · Estiba Shift Command Deck</title>
  <link rel="icon" type="image/png" href="../img/logo.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/header.css"><link rel="stylesheet" href="../css/sidebar.css"><link rel="stylesheet" href="../css/layout.css"><link rel="stylesheet" href="../css/ui.css">
  <style>
    .wa-wrap{font-family:'DM Sans',system-ui,sans-serif;display:flex;flex-direction:column;gap:18px;color:#111827}.wa-wrap *{box-sizing:border-box}.wa-hero{background:linear-gradient(135deg,#005c3d,#00875a);color:#fff;border-radius:20px;padding:23px 28px;display:flex;justify-content:space-between;gap:18px;align-items:center}.wa-tag{font-size:11px;letter-spacing:.1em;font-weight:700;text-transform:uppercase;color:rgba(255,255,255,.85)}.wa-hero h1{margin:6px 0 4px;font-size:23px}.wa-hero p{margin:0;color:rgba(255,255,255,.84);font-size:13px}.wa-btn{border:1px solid rgba(0,135,90,.3);border-radius:10px;padding:10px 16px;background:#fff;color:#00875a;font:600 13px inherit;cursor:pointer}.wa-btn:hover{background:#f0fdf4}.wa-btn:disabled{opacity:.55;cursor:not-allowed}.wa-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(300px,.8fr);gap:18px}.wa-panel{background:#fff;border:1px solid rgba(0,135,90,.18);border-radius:14px;padding:22px}.wa-row{display:flex;gap:14px;align-items:flex-start}.wa-icon{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:#dcfce7;color:#047857;font-size:22px;font-weight:800;flex:none}.wa-icon.off{background:#fff7ed;color:#c2410c}.wa-panel h2{font-size:16px;margin:0 0 4px}.wa-panel p{margin:0;color:#4b5563;font-size:13px;line-height:1.5}.wa-status{display:inline-flex;margin-top:16px;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:700}.wa-status.ok{background:#dcfce7;color:#047857}.wa-status.warn{background:#fff7ed;color:#b45309}.wa-status.err{background:#fef2f2;color:#b91c1c}.wa-steps{margin:18px 0 0;padding-left:18px;color:#4b5563;font-size:13px;line-height:1.65}.wa-qr{width:min(100%,290px);display:block;margin:0 auto;background:#fff;padding:10px;border:1px solid #d1d5db;border-radius:12px}.wa-qr-caption{text-align:center;margin:14px 0 0;color:#4b5563;font-size:13px;line-height:1.55}.wa-note{background:#f0fdf4;border:1px solid rgba(0,135,90,.18);border-radius:10px;padding:12px 14px;font-size:12px;line-height:1.55;color:#065f46;margin-top:16px}.wa-pending{width:88px;height:88px;margin:22px auto;border-radius:50%;border:4px solid #d1fae5;border-top-color:#00875a;animation:wa-spin .85s linear infinite}@keyframes wa-spin{to{transform:rotate(360deg)}}.content{padding:24px 28px 60px}@media(max-width:760px){.wa-grid{grid-template-columns:1fr}.wa-hero{align-items:flex-start;flex-direction:column}}
  </style>
</head>
<body><div class="overlay" id="overlay"></div><div class="shell"><?php $sb_base='..'; include('../includes/sidebar.php'); ?><div class="main-area"><?php include('../includes/header.php'); ?><main class="content"><div class="wa-wrap">
  <section class="wa-hero"><div><div class="wa-tag">Administración · Mensajería</div><h1>Conexión de WhatsApp</h1><p>Envía respuestas y puntajes de Sugerencias Tallyman al celular registrado de cada colaborador.</p></div><form method="post"><input type="hidden" name="accion" value="nuevo_qr"><button class="wa-btn" type="submit" <?= $disponible ? '' : 'disabled title="Configura primero el puente de WhatsApp"' ?>>Generar nuevo QR</button></form></section>
  <div class="wa-grid"><section class="wa-panel"><div class="wa-row"><div class="wa-icon <?= $conectado ? '' : 'off' ?>">↗</div><div><h2>Estado del puente</h2><p><?= $conectado ? 'La cuenta está vinculada y lista para enviar mensajes.' : ($disponible ? 'El puente está activo, pero aún no hay una cuenta vinculada.' : 'Configura y enciende el puente de WhatsApp para poder generar el QR.') ?></p></div></div><span class="wa-status <?= $conectado ? 'ok' : ($disponible ? 'warn' : 'err') ?>"><?= $conectado ? '● Conectado' : ($disponible ? '● Sin vincular' : '● Sin servicio') ?></span><ol class="wa-steps"><li>Cuando el puente esté activo, pulsa “Generar nuevo QR”.</li><li>El código aparecerá automáticamente en este mismo panel derecho.</li><li>Desde el teléfono corporativo abre WhatsApp → Dispositivos vinculados y escanéalo.</li></ol><div class="wa-note"><?= $disponible ? 'Tras solicitarlo, esta página se actualiza sola hasta mostrar el QR.' : 'Falta completar includes/whatsapp_config.php con el token y ejecutar el servicio Node.js. Mientras el estado sea “Sin servicio”, WhatsApp no puede entregar un QR.' ?></div></section><section class="wa-panel"><?php if ($qrSvg): ?><img class="wa-qr" src="data:image/svg+xml;base64,<?= $qrSvg ?>" alt="Código QR para vincular WhatsApp"><p class="wa-qr-caption">Escanea este QR desde el teléfono corporativo.</p><?php elseif ($conectado): ?><div class="wa-icon" style="width:88px;height:88px;margin:22px auto;font-size:38px">✓</div><p class="wa-qr-caption">La sesión está lista para enviar respuestas.</p><?php elseif ($disponible && $qrSolicitado): ?><div class="wa-pending" aria-label="Generando código QR"></div><p class="wa-qr-caption">Generando QR… aparecerá aquí automáticamente.</p><?php if ($conexionInfo !== ''): ?><div class="wa-note" style="max-width:390px;margin:12px auto 0;background:#fff7ed;color:#92400e;border-color:#fed7aa"><strong>Estado de conexión:</strong> <?= htmlspecialchars($conexionInfo) ?><br>Si permanece conectando más de un minuto, solicita al hosting permitir conexiones WebSocket salientes hacia WhatsApp.</div><?php endif; ?><?php else: ?><div class="wa-icon off" style="width:88px;height:88px;margin:22px auto;font-size:38px">?</div><p class="wa-qr-caption"><?= htmlspecialchars($errorAccion ?: $errorEstado ?: 'Genera un nuevo QR y espera unos segundos.') ?></p><?php endif; ?></section></div>
</div></main></div></div></body></html>
<?php if ($disponible && !$conectado && (!$qrSvg || $qrSolicitado)): ?>
<script>
  // Baileys tarda un instante en producir el QR tras el reinicio. Se consulta la
  // misma pantalla hasta que el código esté disponible, sin abrir otra vista.
  setTimeout(() => location.replace('whatsapp.php'), 1800);
</script>
<?php endif; ?>
