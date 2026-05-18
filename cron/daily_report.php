<?php
// cron/daily_report.php — Reporte diario a las 8am Lima
// 0 8 * * * php /var/www/vhosts/tudominio.com/perudata-api/cron/daily_report.php

declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\DB;
use App\Cache\QueryCache;

$db    = DB::connection();
$cache = new QueryCache();

// Estadísticas del día anterior
$yesterday = date('Y-m-d', strtotime('-1 day'));

$totalConsultas = $db->prepare("
    SELECT COUNT(*) FROM usage_log WHERE DATE(created_at) = ? AND status = 'ok'
")->execute([$yesterday]) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;

$stmt = $db->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(from_cache) AS cache_hits,
        COUNT(DISTINCT tenant_id) AS tenants_activos,
        AVG(response_ms) AS avg_ms
    FROM usage_log
    WHERE DATE(created_at) = ? AND status = 'ok'
");
$stmt->execute([$yesterday]);
$stats = $stmt->fetch();

$cacheStats = $cache->stats();
$hitRate    = $stats['total'] > 0
    ? round(($stats['cache_hits'] / $stats['total']) * 100, 1)
    : 0;

$body = <<<TXT
Reporte diario PERUdata API — {$yesterday}

Consultas totales : {$stats['total']}
Cache hit rate    : {$hitRate}%
Tenants activos   : {$stats['tenants_activos']}
Tiempo prom (ms)  : {$stats['avg_ms']}

Cache total       : {$cacheStats['total_cached']} entradas activas
Total hits caché  : {$cacheStats['total_hits']}

-- PERUdata API / AUNOR IT
TXT;

mail(ADMIN_EMAIL, "[PERUdata] Reporte diario $yesterday", $body,
    "From: reporte@perudata.pe\r\nContent-Type: text/plain; charset=utf-8");

echo '[' . date('Y-m-d H:i:s') . '] Reporte diario enviado' . PHP_EOL;
