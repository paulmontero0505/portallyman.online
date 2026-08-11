<?php
/* Cliente del puente local de WhatsApp basado en Baileys. */

$waConfigFile = __DIR__ . '/whatsapp_config.php';
if (is_readable($waConfigFile)) require_once $waConfigFile;

if (!defined('WA_BAILEYS_API_URL')) define('WA_BAILEYS_API_URL', rtrim((string)(getenv('WA_BAILEYS_API_URL') ?: 'http://127.0.0.1:3002'), '/'));
if (!defined('WA_BAILEYS_API_TOKEN')) define('WA_BAILEYS_API_TOKEN', (string)(getenv('WA_BAILEYS_API_TOKEN') ?: ''));

function wa_normalizar_celular_peru(string $celular): ?string {
    if (!preg_match('/^\+51 9\d{8}$/', trim($celular))) return null;
    return str_replace(['+', ' '], '', trim($celular));
}

function wa_solicitud(string $ruta, string $metodo = 'GET', ?array $payload = null): array {
    if (WA_BAILEYS_API_TOKEN === '') return [false, [], 'WhatsApp aún no está configurado en el servidor.'];
    if (!function_exists('curl_init')) return [false, [], 'La extensión cURL de PHP no está disponible.'];

    // CloudLinux puede servir la URL raíz pero descartar subrutas de Passenger.
    // Por eso se comunica la acción por query string, que el puente interpreta.
    $accion = trim($ruta, '/');
    $ch = curl_init(rtrim(WA_BAILEYS_API_URL, '/') . '/?action=' . rawurlencode($accion));
    $opciones = [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . WA_BAILEYS_API_TOKEN],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 15,
    ];
    if ($payload !== null) {
        $opciones[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $opciones[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, $opciones);
    $respuesta = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $datos = json_decode((string)$respuesta, true) ?: [];
    $ok = $respuesta !== false && $http >= 200 && $http < 300 && !empty($datos['ok']);
    $detalle = $error ?: ($datos['error'] ?? ($http ? "El puente respondió con HTTP {$http}." : 'El puente de WhatsApp no respondió.'));
    return [$ok, $datos, $detalle];
}

function wa_estado(): array { return wa_solicitud('/health'); }
function wa_qr(): array { return wa_solicitud('/qr'); }
function wa_regenerar_qr(): array { return wa_solicitud('/reset', 'GET'); }

function wa_nombre_corto(string $nombre): string {
    $partes = preg_split('/\s+/', trim($nombre));
    // En el catálogo el formato habitual es "Apellido paterno Apellido materno Nombres".
    $corto = trim((string)($partes[count($partes) >= 3 ? 2 : 0] ?? 'colaborador'));
    return function_exists('mb_convert_case') ? mb_convert_case($corto, MB_CASE_TITLE, 'UTF-8') : ucfirst(strtolower($corto));
}

function wa_enviar_texto_colaborador(string $celular, string $texto): array {
    $destino = wa_normalizar_celular_peru($celular);
    if (!$destino) return [false, [], 'El colaborador no tiene un celular válido (+51 9XXXXXXXX).'];
    return wa_solicitud('/send-message', 'POST', ['to' => $destino, 'text' => $texto]);
}

function wa_enviar_confirmacion_sugerencia(string $celular, string $nombre, string $canal, string $detalle): array {
    $tipo = [
        'consulta' => 'consulta',
        'solicitud' => 'solicitud',
        'propuesta' => 'propuesta de mejora',
    ][$canal] ?? 'mensaje';
    $texto = "Hola " . wa_nombre_corto($nombre) . ".\n\n"
        . "Respecto a tu mensaje:\n{$detalle}\n\n"
        . "Quiero informarte que estamos revisando tu {$tipo}.\n\n"
        . 'Tallyman Control';
    return wa_enviar_texto_colaborador($celular, $texto);
}

function wa_enviar_respuesta_sugerencia(string $celular, string $nombre, string $canal, string $detalle, string $respuesta, ?int $puntaje, string $comentario = ''): array {
    $detalle = trim($detalle);
    $texto = "Hola " . wa_nombre_corto($nombre) . ".\n\n"
        . "Respecto a tu mensaje:\n{$detalle}\n\n"
        . "Debo indicarte que:\n{$respuesta}";
    if ($puntaje !== null) {
        $texto .= "\n\nPuntaje asignado: {$puntaje}/10.";
        if ($comentario !== '') $texto .= "\nComentario: {$comentario}";
    }
    $texto .= "\n\nTallyman Control";
    return wa_enviar_texto_colaborador($celular, $texto);
}
