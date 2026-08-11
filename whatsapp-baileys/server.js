// Compatible con CloudLinux Node.js Selector: no requiere paquetes externos
// para responder en la ruta raíz antes de ejecutar NPM Install.
const { createServer } = require('node:http')
const { mkdir, rm } = require('node:fs/promises')
const path = require('node:path')

const port = Number(process.env.PORT || 3002)
const host = process.env.HOST || '127.0.0.1'
const token = process.env.BAILEYS_API_TOKEN || ''
const configured = token.length >= 24
const authDir = path.join(process.cwd(), 'auth_info')

let socket = null
let connected = false
let reconnectTimer = null
let latestQr = null
let suppressReconnect = false
let deps = null
let dependenciesError = null
let connectionInfo = 'Inicializando el puente.'

process.on('uncaughtException', error => console.error('Error no controlado:', error))
process.on('unhandledRejection', error => console.error('Promesa rechazada:', error))

async function loadDependencies() {
  if (deps) return deps
  try {
    const [baileys, pinoModule, qrCodeModule, terminalModule] = await Promise.all([
      import('@whiskeysockets/baileys'),
      import('pino'),
      import('qrcode'),
      import('qrcode-terminal')
    ])
    deps = {
      baileys,
      logger: (pinoModule.default || pinoModule)({ level: process.env.LOG_LEVEL || 'info' }),
      QRCode: qrCodeModule.default || qrCodeModule,
      terminal: terminalModule.default || terminalModule
    }
    dependenciesError = null
    return deps
  } catch (error) {
    dependenciesError = 'Los módulos de WhatsApp aún no están instalados.'
    throw error
  }
}

async function connectWhatsApp() {
  connectionInfo = 'Conectando con WhatsApp…'
  const { baileys, logger, terminal } = await loadDependencies()
  // Baileys 6 se publica como CommonJS. Al importarlo dinámicamente desde este
  // archivo CommonJS, la función puede estar en el namespace o en default.
  const api = baileys.default && typeof baileys.default === 'object' ? baileys.default : baileys
  const makeWASocket = baileys.makeWASocket || api.makeWASocket || api.default
  const Browsers = baileys.Browsers || api.Browsers
  const DisconnectReason = baileys.DisconnectReason || api.DisconnectReason
  const fetchLatestBaileysVersion = baileys.fetchLatestBaileysVersion || api.fetchLatestBaileysVersion
  const makeCacheableSignalKeyStore = baileys.makeCacheableSignalKeyStore || api.makeCacheableSignalKeyStore
  const useMultiFileAuthState = baileys.useMultiFileAuthState || api.useMultiFileAuthState
  if (typeof makeWASocket !== 'function') throw new Error('La instalación de Baileys no expuso makeWASocket.')
  await mkdir(authDir, { recursive: true })
  const { state, saveCreds } = await useMultiFileAuthState(authDir)
  const { version } = await fetchLatestBaileysVersion()

  socket = makeWASocket({
    version,
    auth: { creds: state.creds, keys: makeCacheableSignalKeyStore(state.keys, logger) },
    logger,
    browser: Browsers.ubuntu('Portally Tallyman'),
    printQRInTerminal: false,
    markOnlineOnConnect: false
  })
  socket.ev.on('creds.update', saveCreds)
  socket.ev.on('connection.update', ({ connection, lastDisconnect, qr }) => {
    if (qr) {
      latestQr = qr
      connectionInfo = 'Código QR disponible.'
      terminal.generate(qr, { small: true })
    }
    if (connection === 'open') {
      connected = true
      latestQr = null
      connectionInfo = 'Cuenta vinculada.'
      console.log('WhatsApp conectado.')
    }
    if (connection === 'close') {
      connected = false
      const detail = lastDisconnect && lastDisconnect.error
        ? (lastDisconnect.error.message || lastDisconnect.error.toString())
        : 'La conexión se cerró antes de generar el QR.'
      connectionInfo = `Conexión cerrada: ${String(detail).slice(0, 220)}`
      const loggedOut = lastDisconnect && lastDisconnect.error && lastDisconnect.error.output && lastDisconnect.error.output.statusCode === DisconnectReason.loggedOut
      if (!loggedOut && !suppressReconnect) {
        clearTimeout(reconnectTimer)
        reconnectTimer = setTimeout(() => connectWhatsApp().catch(console.error), 3000)
      }
    }
  })
}

function json(res, status, body) {
  res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8' })
  res.end(JSON.stringify(body))
}

createServer(async (req, res) => {
  const requestUrl = new URL(req.url, 'http://localhost')
  const rawPath = requestUrl.pathname.replace(/\/+$/, '') || '/'
  let route = rawPath.replace(/^\/whatsapp-bridge(?:-v2)?(?=\/|$)/, '') || '/'
  // Algunos Apache/Passenger de cPanel no conservan los subpaths de una app.
  // El panel PHP usa ?action=... como ruta alternativa sobre la URL raíz.
  const action = requestUrl.searchParams.get('action')
  if (['health', 'qr', 'reset', 'send-message'].includes(action)) route = `/${action}`

  if (req.method === 'GET' && route === '/') {
    return json(res, 200, { ok: true, service: 'portally-whatsapp-baileys', configured, connected, qrAvailable: Boolean(latestQr), connectionInfo })
  }
  if (req.method === 'GET' && route === '/health') {
    return json(res, 200, { ok: true, configured, connected, qrAvailable: Boolean(latestQr), dependenciesError, connectionInfo })
  }
  if (!configured) return json(res, 503, { ok: false, error: 'BAILEYS_API_TOKEN no está configurado' })
  if (req.headers.authorization !== `Bearer ${token}`) return json(res, 401, { ok: false, error: 'No autorizado' })

  if (req.method === 'GET' && route === '/qr') {
    if (!latestQr) return json(res, 404, { ok: false, error: 'No hay QR disponible' })
    const { QRCode } = await loadDependencies()
    return json(res, 200, { ok: true, svg: await QRCode.toString(latestQr, { type: 'svg', margin: 1, width: 300 }) })
  }
  // CloudLinux puede desviar POST hacia Passenger; se permite GET autenticado
  // únicamente para reiniciar la sesión y generar el QR desde el panel.
  if ((req.method === 'POST' || req.method === 'GET') && route === '/reset') {
    suppressReconnect = true
    clearTimeout(reconnectTimer)
    connected = false
    latestQr = null
    await rm(authDir, { recursive: true, force: true })
    if (socket && socket.ws) socket.ws.close()
    setTimeout(() => { suppressReconnect = false; connectWhatsApp().catch(console.error) }, 700)
    return json(res, 202, { ok: true })
  }
  if (req.method !== 'POST' || route !== '/send-message') return json(res, 404, { ok: false })

  let raw = ''
  for await (const chunk of req) {
    raw += chunk
    if (raw.length > 4096) return json(res, 413, { ok: false, error: 'Solicitud muy grande' })
  }
  try {
    const { to, text } = JSON.parse(raw)
    if (!/^519\d{8}$/.test(String(to)) || typeof text !== 'string' || !text.trim() || text.length > 1400) {
      return json(res, 422, { ok: false, error: 'Destino o mensaje inválido' })
    }
    if (!connected || !socket) return json(res, 503, { ok: false, error: 'WhatsApp no está conectado' })
    const result = await socket.sendMessage(`${to}@s.whatsapp.net`, { text: text.trim() })
    return json(res, 200, { ok: true, messageId: result && result.key ? result.key.id : null })
  } catch (error) {
    console.error('No se pudo enviar WhatsApp', error)
    return json(res, 502, { ok: false, error: 'No se pudo enviar el mensaje' })
  }
}).listen(port, host, () => console.log(`Puente WhatsApp en http://${host}:${port}`))

if (configured) {
  connectWhatsApp().catch(error => {
    connectionInfo = `No se pudo iniciar Baileys: ${error.message}`
    console.error('No se pudo iniciar Baileys; instala los módulos y reinicia la aplicación.', error.message)
  })
} else {
  console.warn('BAILEYS_API_TOKEN no configurado: el puente inició sin sesión de WhatsApp.')
}
