<?php
// admin/monitor.php — Monitor de fuentes en tiempo real
require_once __DIR__ . '/_layout.php';

// Trigger manual de chequeo
if (isset($_GET['check'])) {
    (new \App\Monitor\SourceMonitor())->checkAll();
    header('Location: /admin/monitor.php?checked=1');
    exit;
}

$sources = $db->query("SELECT * FROM source_monitors ORDER BY id")->fetchAll();

$statusInfo = [
    'ok'      => ['label' => 'Operativa',  'color' => 'text-green-400',  'bg' => 'bg-green-500',  'ring' => 'ring-green-600'],
    'slow'    => ['label' => 'Lenta',      'color' => 'text-yellow-400', 'bg' => 'bg-yellow-500', 'ring' => 'ring-yellow-600'],
    'error'   => ['label' => 'Error',      'color' => 'text-red-400',    'bg' => 'bg-red-500',    'ring' => 'ring-red-600'],
    'changed' => ['label' => 'Cambio HTML','color' => 'text-orange-400', 'bg' => 'bg-orange-500', 'ring' => 'ring-orange-600'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor — PERÚdata Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-white flex">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="flex-1 ml-64 p-8">

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold">Monitor de Fuentes</h1>
            <p class="text-gray-400 text-sm mt-0.5">Estado en tiempo real de las fuentes de datos</p>
        </div>
        <a href="/admin/monitor.php?check=1"
            class="bg-gray-800 hover:bg-gray-700 border border-gray-700 text-white text-sm font-medium px-4 py-2 rounded-xl transition-colors">
            🔄 Verificar ahora
        </a>
    </div>

    <?php if (isset($_GET['checked'])): ?>
    <div class="mb-6 p-3 bg-green-900/40 border border-green-700 rounded-xl text-green-300 text-sm">
        Verificación completada — <?= date('H:i:s') ?>
    </div>
    <?php endif; ?>

    <!-- Cards de fuentes -->
    <div class="grid grid-cols-3 gap-5 mb-8">
        <?php foreach ($sources as $src): ?>
        <?php $si = $statusInfo[$src['last_status']] ?? $statusInfo['error']; ?>
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="font-semibold text-white"><?= htmlspecialchars($src['source_name']) ?></h3>
                    <p class="text-gray-500 text-xs mt-0.5 break-all"><?= htmlspecialchars($src['source_url']) ?></p>
                </div>
                <div class="relative flex-shrink-0 ml-3">
                    <span class="w-3 h-3 rounded-full <?= $si['bg'] ?> block <?= $src['last_status'] === 'ok' ? 'animate-pulse' : '' ?>"></span>
                </div>
            </div>

            <div class="flex items-center gap-2 mb-3">
                <span class="text-sm font-medium <?= $si['color'] ?>"><?= $si['label'] ?></span>
                <?php if ($src['consecutive_failures'] > 0): ?>
                <span class="text-xs bg-red-900/50 text-red-400 border border-red-700 px-1.5 py-0.5 rounded-full">
                    <?= $src['consecutive_failures'] ?> fallos
                </span>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="bg-gray-800 rounded-xl p-3">
                    <div class="text-gray-500 mb-0.5">Tiempo respuesta</div>
                    <div class="font-semibold <?= $src['response_ms'] > 5000 ? 'text-yellow-400' : 'text-white' ?>">
                        <?= number_format($src['response_ms']) ?>ms
                    </div>
                </div>
                <div class="bg-gray-800 rounded-xl p-3">
                    <div class="text-gray-500 mb-0.5">Último chequeo</div>
                    <div class="font-semibold text-white">
                        <?= $src['last_check'] ? date('H:i', strtotime($src['last_check'])) : '—' ?>
                    </div>
                </div>
            </div>

            <?php if ($src['last_error']): ?>
            <div class="mt-3 p-2 bg-red-950/50 border border-red-800 rounded-lg">
                <p class="text-xs text-red-400 font-mono break-all"><?= htmlspecialchars($src['last_error']) ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Info cron -->
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
        <h2 class="text-sm font-semibold text-gray-300 mb-3">Configuración de Cron Jobs (Plesk)</h2>
        <div class="space-y-2 font-mono text-xs">
            <div class="bg-gray-800 rounded-lg p-3 text-gray-300">
                <span class="text-gray-500"># Monitor fuentes — cada 10 min</span><br>
                */10 * * * * php <?= dirname(dirname($_SERVER['SCRIPT_FILENAME'])) ?>/cron/monitor.php
            </div>
            <div class="bg-gray-800 rounded-lg p-3 text-gray-300">
                <span class="text-gray-500"># Limpieza caché — cada hora</span><br>
                0 * * * * php <?= dirname(dirname($_SERVER['SCRIPT_FILENAME'])) ?>/cron/cleanup.php
            </div>
            <div class="bg-gray-800 rounded-lg p-3 text-gray-300">
                <span class="text-gray-500"># Reporte diario — 8am Lima</span><br>
                0 8 * * * php <?= dirname(dirname($_SERVER['SCRIPT_FILENAME'])) ?>/cron/daily_report.php
            </div>
        </div>
    </div>
</main>
</body>
</html>
