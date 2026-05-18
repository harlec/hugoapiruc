<?php
// public/index.php — Front controller de PERÚdata API

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Auth\TokenManager;
use App\Auth\RateLimiter;
use App\Cache\QueryCache;
use App\Scrapers\ScraperRouter;
use App\Helpers\Logger;

// ── Headers ──────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Parseo de ruta ────────────────────────────────────────────────────────────
// Espera: /api/v1/{tipo}/{valor}
$path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = array_values(array_filter(explode('/', trim($path, '/'))));
// $parts[0]=api, $parts[1]=v1, $parts[2]=ruc|dni, $parts[3]=numero

// Ruta de salud: GET /api/v1/status
if (($parts[0] ?? '') === 'api' && ($parts[1] ?? '') === 'v1' && ($parts[2] ?? '') === 'status') {
    echo json_encode(['status' => 'ok', 'version' => '1.0', 'timestamp' => date('c')]);
    exit;
}

if (count($parts) < 4 || $parts[0] !== 'api' || $parts[1] !== 'v1') {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint no válido. Usa /api/v1/ruc/{numero} o /api/v1/dni/{numero}']);
    exit;
}

$type  = strtolower($parts[2]);
$value = preg_replace('/\D/', '', $parts[3]);

// ── Validar tipo ──────────────────────────────────────────────────────────────
$formats = ['ruc' => 11, 'dni' => 8];
if (!isset($formats[$type])) {
    http_response_code(400);
    echo json_encode(['error' => "Tipo '$type' no válido. Usa 'ruc' o 'dni'", 'code' => 'INVALID_TYPE']);
    exit;
}

if (strlen($value) !== $formats[$type]) {
    http_response_code(400);
    echo json_encode([
        'error' => "Formato de $type inválido. Se esperan {$formats[$type]} dígitos",
        'code'  => 'INVALID_FORMAT',
    ]);
    exit;
}

// ── Autenticación ─────────────────────────────────────────────────────────────
$token = TokenManager::fromRequest();
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Token requerido. Usa Authorization: Bearer {token}', 'code' => 'AUTH_REQUIRED']);
    exit;
}

$tenant = TokenManager::validate($token);
if (!$tenant) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido o expirado', 'code' => 'AUTH_INVALID']);
    exit;
}

// ── Verificar acceso a DNI según plan ────────────────────────────────────────
if ($type === 'dni' && empty($tenant['features']['dni'])) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Tu plan no incluye consultas de DNI. Actualiza a plan Pro o superior',
        'code'  => 'PLAN_INSUFFICIENT',
    ]);
    exit;
}

// ── Rate Limiting ─────────────────────────────────────────────────────────────
$limiter = new RateLimiter($tenant);
if (!$limiter->allow()) {
    Logger::log($tenant['id'], $type, $value, false, 0, 'rate_limit');
    http_response_code(429);
    echo json_encode([
        'error'       => 'Límite de consultas diarias alcanzado',
        'code'        => 'RATE_LIMIT',
        'reset_at'    => $limiter->resetAt(),
        'daily_limit' => $tenant['queries_per_day'],
        'remaining'   => 0,
    ]);
    exit;
}

// ── Caché ─────────────────────────────────────────────────────────────────────
$start = microtime(true);
$cache = new QueryCache();
$cached = $cache->get($type, $value);

if ($cached) {
    $ms = (int)round((microtime(true) - $start) * 1000);
    $limiter->increment();
    Logger::log($tenant['id'], $type, $value, true, $ms, 'ok');

    echo json_encode(array_merge($cached, [
        'from_cache'  => true,
        'cached_at'   => $cached['_cached_at'],
        'cache_hits'  => $cached['_cache_hits'],
        'response_ms' => $ms,
    ]));
    exit;
}

// ── Scraping ──────────────────────────────────────────────────────────────────
$router = new ScraperRouter();
$result = $router->query($type, $value);

if (!$result['success']) {
    $ms = (int)round((microtime(true) - $start) * 1000);
    Logger::log($tenant['id'], $type, $value, false, $ms, 'error');
    http_response_code(503);
    echo json_encode([
        'error' => 'No se pudo obtener información en este momento. Intenta más tarde.',
        'code'  => 'SOURCE_ERROR',
    ]);
    exit;
}

// ── Guardar en caché & responder ──────────────────────────────────────────────
$ttl = (int)($tenant['cache_ttl_hours'] ?? 24);
$cache->set($type, $value, $result['data'], $result['source'], $ttl);

$ms = (int)round((microtime(true) - $start) * 1000);
$limiter->increment();
Logger::log($tenant['id'], $type, $value, false, $ms, 'ok');

echo json_encode(array_merge($result['data'], [
    'from_cache'  => false,
    'source'      => $result['source'],
    'response_ms' => $ms,
    'remaining'   => $limiter->remaining(),
]));
