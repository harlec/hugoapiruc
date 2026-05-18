<?php
// cron/cleanup.php — Limpia caché expirado cada hora
// 0 * * * * php /var/www/vhosts/tudominio.com/perudata-api/cron/cleanup.php

declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Cache\QueryCache;

$cache   = new QueryCache();
$deleted = $cache->cleanExpired();

echo '[' . date('Y-m-d H:i:s') . "] Cleanup: $deleted registros expirados eliminados" . PHP_EOL;
