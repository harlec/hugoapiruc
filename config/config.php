<?php
// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__) . '/config');
$dotenv->safeLoad();

// Zona horaria Perú
date_default_timezone_set('America/Lima');

// Constantes globales
define('APP_ENV',   $_ENV['APP_ENV']   ?? 'production');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('APP_URL',   $_ENV['APP_URL']   ?? 'http://localhost');

// Configuración de base de datos
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'perudata_api');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Headers de seguridad (solo en contexto web)
if (php_sapi_name() !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    if (!APP_DEBUG) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Alertas y servicios externos
define('ADMIN_EMAIL',    $_ENV['ADMIN_EMAIL']    ?? 'admin@perudata.pe');
define('SLACK_WEBHOOK',  $_ENV['SLACK_WEBHOOK']  ?? '');
define('BACKUP_API_KEY', $_ENV['BACKUP_API_KEY'] ?? '');

// Monitor
define('MONITOR_FAILURE_THRESHOLD', 3);
define('MONITOR_SLOW_MS',          5000);

// Manejo de errores
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', dirname(__DIR__) . '/logs/php_errors.log');
}
