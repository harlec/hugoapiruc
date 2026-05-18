<?php
// admin/index.php — Dashboard principal
require_once __DIR__ . '/_layout.php';

// ── KPIs ──────────────────────────────────────────────────────────────────────
$todayQueries = $db->query("
    SELECT COUNT(*) FROM usage_log WHERE DATE(created_at) = CURDATE() AND status = 'ok'
")->fetchColumn();

$todayYesterday = $db->query("
    SELECT COUNT(*) FROM usage_log WHERE DATE(created_at) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND status = 'ok'
")->fetchColumn();

$pctChange = $todayYesterday > 0
    ? round((($todayQueries - $todayYesterday) / $todayYesterday) * 100, 1)
    : 0;

$cacheStats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(from_cache) AS hits
    FROM usage_log WHERE DATE(created_at) = CURDATE() AND status = 'ok'
")->fetch();
$hitRate = $cacheStats['total'] > 0
    ? round(($cacheStats['hits'] / $cacheStats['total']) * 100, 1)
    : 0;

$activeTenants = $db->query("
    SELECT COUNT(*) FROM tenants WHERE status IN ('active','trial')
")->fetchColumn();

$newThisMonth = $db->query("
    SELECT COUNT(*) FROM tenants WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
")->fetchColumn();

$sources = $db->query("SELECT * FROM source_monitors ORDER BY id")->fetchAll();
$srcOk   = count(array_filter($sources, fn($s) => $s['last_status'] === 'ok'));

// ── Últimas 7 días de consultas ───────────────────────────────────────────────
$last7Days = $db->query("
    SELECT DATE(created_at) AS dia, COUNT(*) AS total, SUM(from_cache) AS cached
    FROM usage_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status = 'ok'
    GROUP BY dia ORDER BY dia
")->fetchAll();

// ── Top 5 RUCs/DNIs consultados ───────────────────────────────────────────────
$topQueries = $db->query("
    SELECT query_type, query_value, COUNT(*) AS total
    FROM usage_log WHERE status = 'ok' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY query_type, query_value ORDER BY total DESC LIMIT 5
")->fetchAll();

// ── Últimas consultas ─────────────────────────────────────────────────────────
$recentLogs = $db->query("
    SELECT u.*, t.name AS tenant_name
    FROM usage_log u JOIN tenants t ON t.id = u.tenant_id
    ORDER BY u.created_at DESC LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — PERÚdata Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body class="bg-gray-950 text-white flex">

<!-- ── Sidebar ──────────────────────────────────────────────────────────────── -->
<?php include __DIR__ . '/_sidebar.php'; ?>

<!-- ── Contenido ─────────────────────────────────────────────────────────────── -->
<main class="flex-1 ml-64 p-8 min-h-screen">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold">Dashboard</h1>
            <p class="text-gray-400 text-sm mt-0.5"><?= date('l, d \d\e F \d\e Y') ?></p>
        </div>
        <span class="text-2xl">🇵🇪</span>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-4 gap-5 mb-8">

        <!-- Consultas hoy -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-gray-400 text-sm font-medium">Consultas hoy</span>
                <span class="text-2xl">📊</span>
            </div>
            <div class="text-3xl font-bold"><?= number_format($todayQueries) ?></div>
            <div class="mt-1 text-xs <?= $pctChange >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                <?= $pctChange >= 0 ? '▲' : '▼' ?> <?= abs($pctChange) ?>% vs ayer
            </div>
        </div>

        <!-- Cache Hit Rate -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-gray-400 text-sm font-medium">Cache Hit Rate</span>
                <span class="text-2xl">⚡</span>
            </div>
            <div class="text-3xl font-bold"><?= $hitRate ?>%</div>
            <div class="mt-1 text-xs text-gray-500">
                <?= number_format($cacheStats['hits']) ?> de <?= number_format($cacheStats['total']) ?> desde caché
            </div>
        </div>

        <!-- Tenants activos -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-gray-400 text-sm font-medium">Tenants activos</span>
                <span class="text-2xl">👥</span>
            </div>
            <div class="text-3xl font-bold"><?= $activeTenants ?></div>
            <div class="mt-1 text-xs text-green-400">+<?= $newThisMonth ?> este mes</div>
        </div>

        <!-- Fuentes OK -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-gray-400 text-sm font-medium">Fuentes OK</span>
                <span class="text-2xl">🔌</span>
            </div>
            <div class="text-3xl font-bold <?= $srcOk < count($sources) ? 'text-yellow-400' : 'text-green-400' ?>">
                <?= $srcOk ?> / <?= count($sources) ?>
            </div>
            <?php if ($srcOk < count($sources)): ?>
            <div class="mt-1 text-xs text-yellow-400">⚠ <?= count($sources) - $srcOk ?> con problemas</div>
            <?php else: ?>
            <div class="mt-1 text-xs text-green-400">Todas operativas</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Gráfico + Estado fuentes -->
    <div class="grid grid-cols-3 gap-5 mb-8">

        <!-- Gráfico consultas 7 días -->
        <div class="col-span-2 bg-gray-900 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-sm font-semibold text-gray-300 mb-4">Consultas — últimos 7 días</h2>
            <canvas id="chartConsultas" height="100"></canvas>
        </div>

        <!-- Estado fuentes -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-sm font-semibold text-gray-300 mb-4">Estado de fuentes</h2>
            <div class="space-y-3">
                <?php foreach ($sources as $src): ?>
                <?php
                    $statusClasses = [
                        'ok'      => 'bg-green-500',
                        'slow'    => 'bg-yellow-500',
                        'error'   => 'bg-red-500',
                        'changed' => 'bg-orange-500',
                    ];
                    $dot = $statusClasses[$src['last_status']] ?? 'bg-gray-500';
                ?>
                <div class="flex items-center justify-between p-3 bg-gray-800 rounded-xl">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full <?= $dot ?>"></span>
                        <span class="text-sm font-medium"><?= htmlspecialchars($src['source_name']) ?></span>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-400"><?= $src['response_ms'] ?>ms</div>
                        <?php if ($src['consecutive_failures'] > 0): ?>
                        <div class="text-xs text-red-400"><?= $src['consecutive_failures'] ?> fallos</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="/admin/monitor.php" class="mt-4 block text-center text-xs text-red-400 hover:text-red-300">
                Ver monitor completo →
            </a>
        </div>
    </div>

    <!-- Top queries + Últimas consultas -->
    <div class="grid grid-cols-2 gap-5">

        <!-- Top 5 consultas -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-sm font-semibold text-gray-300 mb-4">Top consultas (7 días)</h2>
            <div class="space-y-2">
                <?php foreach ($topQueries as $i => $q): ?>
                <div class="flex items-center gap-3 p-2">
                    <span class="text-gray-600 text-sm w-4"><?= $i + 1 ?></span>
                    <span class="bg-gray-800 text-xs font-mono px-2 py-0.5 rounded text-gray-300">
                        <?= strtoupper($q['query_type']) ?>
                    </span>
                    <span class="text-sm font-mono flex-1"><?= htmlspecialchars($q['query_value']) ?></span>
                    <span class="text-sm text-gray-400"><?= number_format($q['total']) ?>x</span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($topQueries)): ?>
                <p class="text-gray-600 text-sm">Sin datos aún</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Últimas consultas -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-sm font-semibold text-gray-300 mb-4">Actividad reciente</h2>
            <div class="space-y-2">
                <?php foreach ($recentLogs as $log): ?>
                <?php
                    $statusColor = ['ok' => 'text-green-400', 'error' => 'text-red-400', 'rate_limit' => 'text-yellow-400'];
                    $sc = $statusColor[$log['status']] ?? 'text-gray-400';
                ?>
                <div class="flex items-center gap-2 text-xs py-1 border-b border-gray-800">
                    <span class="<?= $sc ?> font-mono w-2 h-2 rounded-full bg-current inline-block flex-shrink-0"></span>
                    <span class="text-gray-500 w-20 flex-shrink-0"><?= strtoupper($log['query_type'] ?? '') ?></span>
                    <span class="font-mono flex-1 truncate"><?= htmlspecialchars($log['query_value'] ?? '') ?></span>
                    <span class="text-gray-500 flex-shrink-0"><?= $log['response_ms'] ?>ms</span>
                    <span class="text-gray-600 flex-shrink-0 hidden xl:block">
                        <?= $log['from_cache'] ? '💾' : '🌐' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="/admin/logs.php" class="mt-4 block text-center text-xs text-red-400 hover:text-red-300">
                Ver todos los logs →
            </a>
        </div>
    </div>

</main>

<script>
// Gráfico de consultas
const labels = <?= json_encode(array_column($last7Days, 'dia')) ?>;
const totals  = <?= json_encode(array_map('intval', array_column($last7Days, 'total'))) ?>;
const cached  = <?= json_encode(array_map('intval', array_column($last7Days, 'cached'))) ?>;

new Chart(document.getElementById('chartConsultas'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'Scraping',
                data: totals.map((t, i) => t - (cached[i] ?? 0)),
                backgroundColor: 'rgba(239,68,68,0.7)',
                borderRadius: 4,
            },
            {
                label: 'Caché',
                data: cached,
                backgroundColor: 'rgba(59,130,246,0.7)',
                borderRadius: 4,
            },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#9ca3af', font: { size: 11 } } } },
        scales: {
            x: { stacked: true, ticks: { color: '#6b7280' }, grid: { color: '#1f2937' } },
            y: { stacked: true, ticks: { color: '#6b7280' }, grid: { color: '#1f2937' } },
        }
    }
});
</script>
</body>
</html>
