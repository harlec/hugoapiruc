<?php
// cron/monitor.php — Se ejecuta cada 10 minutos vía Plesk Scheduled Tasks
// */10 * * * * php /var/www/vhosts/tudominio.com/perudata-api/cron/monitor.php

declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Monitor\SourceMonitor;

$monitor = new SourceMonitor();
$monitor->checkAll();

echo '[' . date('Y-m-d H:i:s') . '] Monitor ejecutado OK' . PHP_EOL;
