# Puente WhatsApp para Sugerencias Tallyman

Este puente usa Baileys/WhatsApp Web para enviar, desde el panel, respuestas a sugerencias no anónimas. Solo escucha en `127.0.0.1`; no debe exponerse a Internet. Esta versión está fijada para ser compatible con Node.js 18 de cPanel.

1. Instala Node.js LTS en el servidor (cPanel debe ofrecer una aplicación Node.js o acceso SSH).
2. Copia `.env.example` como `.env`, define un token aleatorio de al menos 24 caracteres y conserva el puerto `3002` salvo que cambies la configuración PHP.
3. Copia `../includes/whatsapp_config.example.php` como `../includes/whatsapp_config.php`, registra el mismo token y usa la URL pública que cPanel asignó a la app (por ejemplo, `https://portallyman.com/whatsapp-bridge`).
4. En cPanel usa “Run NPM Install” y “Restart Application”. Solo usa `npm start` en un servidor propio, no en una aplicación administrada por cPanel.
5. En Administración → WhatsApp, genera y escanea el QR con el teléfono corporativo.

Si el puente corre en otro host o puerto, actualiza `WA_BAILEYS_API_URL` en `includes/whatsapp_config.php`. El servidor PHP debe poder alcanzarlo de forma privada.
