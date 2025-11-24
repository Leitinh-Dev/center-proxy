<?php
/**
 * PROXY PARA RENDER.COM / RAILWAY.APP / OUTROS SERVIÇOS
 * 
 * Este arquivo funciona em qualquer serviço PHP (Render.com, Railway.app, etc.)
 * 
 * CONFIGURAÇÃO:
 * 1. Faça upload deste arquivo no seu serviço
 * 2. Configure Start Command: php -S 0.0.0.0:$PORT render_proxy.php
 * 3. Adicione variável de ambiente: TARGET_HOST=https://seudominio.com
 * 4. Copie a URL gerada e configure em config/api_proxy.php
 * 
 * GUIAS DISPONÍVEIS:
 * - GUIA_RAPIDO_SEM_GIT.md (Railway.app - mais fácil, sem Git)
 * - CONFIGURAR_RENDER.md (Render.com - requer Git)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Nonce, X-Timestamp, X-Requested-With');

// Responder a requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuração
$TARGET_HOST = getenv('TARGET_HOST') ?: 'https://seudominio.com';
$TARGET_HOST = rtrim($TARGET_HOST, '/');

// Mapeamento de módulos para URLs
$MODULE_MAP = [
    'chk_get_net' => '/Modulos/Chk-Get-Net/api.php',
    'chk_visa_master' => '/Modulos/Chk-Visa-Master/api.php',
    'chk_pagar_me' => '/Modulos/Chk-Pagar.me/api.php',
    'matriz' => '/Modulos/Matriz/api.php'
];

// Health check endpoint
if (isset($_GET['health']) || $_SERVER['REQUEST_URI'] === '/health.php' || $_SERVER['REQUEST_URI'] === '/') {
    echo 'OK';
    exit;
}

// Obter módulo da query string
$module = $_GET['module'] ?? '';

if (empty($module) || !isset($MODULE_MAP[$module])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Módulo não especificado ou inválido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Construir URL de destino
$targetUrl = $TARGET_HOST . $MODULE_MAP[$module];

// Preservar query string original (exceto 'module')
$queryParams = $_GET;
unset($queryParams['module']);
if (!empty($queryParams)) {
    $targetUrl .= '?' . http_build_query($queryParams);
}

// Preparar headers para encaminhar
$forwardHeaders = [];
$headersToForward = [
    'Authorization',
    'X-CSRF-Token',
    'X-Nonce',
    'X-Timestamp',
    'Content-Type',
    'Accept',
    'User-Agent',
    'Referer',
    'Origin'
];

foreach ($headersToForward as $header) {
    $headerKey = 'HTTP_' . str_replace('-', '_', strtoupper($header));
    if (isset($_SERVER[$headerKey])) {
        $forwardHeaders[] = $header . ': ' . $_SERVER[$headerKey];
    }
}

// Adicionar headers padrão
$forwardHeaders[] = 'X-Forwarded-For: ' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
$forwardHeaders[] = 'X-Forwarded-Proto: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');
$forwardHeaders[] = 'X-Proxy-Source: Render.com';

// Preparar dados para POST
$postData = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = file_get_contents('php://input');
    if (empty($postData)) {
        $postData = http_build_query($_POST);
    }
}

// Fazer requisição ao servidor de destino
$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => $forwardHeaders,
    CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Render-Proxy/1.0',
    CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD']
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postData !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Se houver erro, retornar erro
if ($error) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao conectar com servidor de destino: ' . $error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Retornar resposta com código HTTP apropriado
http_response_code($httpCode);
echo $response;

