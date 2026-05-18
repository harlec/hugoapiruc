<?php
// admin/analytics.php — Analytics de consultas
require_once __DIR__ . '/_layout.php';

$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90])) $days = 30;

// Consultas por día
$byDay = $db->prepare("
    SELECT
        DATE(created_at) AS dia,
        COUNT(*) AS total,
        SUM(from_cache) AS cached,
        COUNT(*) - SUM(from_cache) AS scraped,
        ROUND(AVG(response_ms)) AS avg_ms
    FROM usage_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
      AND status = 'ok'
    GROUP BY dia ORDER BY dia
");
$byDay->execute([$days]);
$byDay = $byDay->fetchAll();

// Por tipo (RUC vs DNI)
$byType = $db->prepare("
    SELECT query_type, COUNT(*) AS total
    FROM usage_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND status = 'ok'
    GROUP BY query_type
");
$byType->execute([$days]);
$byType = $byType->fetchAll();

// Por tenant
$byTenant = $db->prepare("
    SELECT t.name, t.plan_id, p.name AS plan_name, COUNT(*) AS total,
           SUM(u.from_cache) AS cached
    FROM usage_log u
    JOIN tenants t ON t.id = u.tenant_id
    JOIN plans p ON p.id = t.plan_id
    WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND u.status = 'ok'
    GROUP BY t.id ORDER BY total DESC LIMIT 10
");
$byTenant->execute([$days]);
$byTenant = $byTenant->fetchAll();

// Errores por día
$errors = $db->prepare("
    SELECT DATE(created_at) AS dia, COUNT(*) AS total
    FROM usage_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND status = 'error'
    GROUP BY dia ORDER BY dia
");
$errors->execute([$days]);
$errors = $errors->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics — PERÚdata Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body class="bg-gray-950 text-white flex">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="flex-1 ml-64 p-8">

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold">Analytics</h1>
            <p class="text-gray-400 text-sm mt-0.5">Estadísticas de uso de la API</p>
        </div>
        <!-- Selector de período -->
        <div class="flex gap-2">
            <?php foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días'] as $d => $label): ?>
            <a href="?days=<?= $d ?>"
                class="px-3 py-1.5 rounded-xl text-sm transition-colors <?= $days === $d ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Gráfico consultas por día -->
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 mb-5">
        <h2 class="text-sm font-semibold text-gray-300 mb-4">Consultas diarias (últimos <?= $days ?> días)</h2>
        <canvas id="chartDia" height="80"></canvas>
    </div>

    <div class="grid grid-cols-3 gap-5 mb-5">
        <!-- Por tipo -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-sm font-semibold text-gray-300 mb-4">RUC vs DNI</h2>
            <canvas id="chartTipo" height="180"></canvas>
        </div>

        <!-- Top tenants -->
        <div class="col-span-2 bg-gray-900 border border-gray-800 rounded-2xl p-6">
            <h2 class="text-sm font-semibold text-gray-300 mb-4">Top tenants por consumo</h2>
            <div class="space-y-2">
                <?php foreach ($byTenant as $i => $t): ?>
                <div class="flex items-center gap-3">
                    <span class="text-gray-600 text-xs w-4"><?= $i + 1 ?></span>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-sm"><?= htmlspecialchars($t['name']) ?></span>
                            <span class="text-xs text-gray-400"><?= number_format($t['total']) ?> consultas</span>
                        </div>
                        <?php $pct = $byTenant[0]['total'] > 0 ? ($t['total'] / $byTenant[0]['total'] * 100) : 0; ?>
                        <div class="h-1.5 bg-gray-800 rounded-full">
                            <div class="h-1.5 bg-red-600 rounded-full" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                    <span class="text-xs text-gray-500 w-16 text-right flex-shrink-0"><?= $t['plan_name'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($byTenant)): ?>
                <p class="text-gray-600 text-sm">Sin datos para este período</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
const labels = <?= json_encode(array_column($byDay, 'dia')) ?>;
const totals  = <?= json_encode(array_map('intval', array_column($byDay, 'total'))) ?>;
const cached  = <?= json_encode(array_map('intval', array_column($byDay, 'cached'))) ?>;
const scraped = <?= json_encode(array_map('intval', array_column($byDay, 'scraped'))) ?>;

new Chart(document.getElementById('chartDia'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            { label: 'Scraping', data: scraped, backgroundColor: 'rgba(239,68,68,0.7)', borderRadius: 3 },
            { label: 'Caché',    data: cached,  backgroundColor: 'rgba(59,130,246,0.7)', borderRadius: 3 },
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

const typeData = <?= json_encode(array_column($byType, 'total')) ?>;
const typeLabels = <?= json_encode(array_map(fn($r) => strtoupper($r['query_type']), $byType)) ?>;
new Chart(document.getElementById('chartTipo'), {
    type: 'doughnut',
    data: {
        labels: typeLabels,
        datasets: [{ data: typeData, backgroundColor: ['#ef4444','#3b82f6'], borderWidth: 0 }]
    },
    options: {
        plugins: {
            legend: { position: 'bottom', labels: { color: '#9ca3af', font: { size: 11 } } }
        }
    }
});
</script>
</body>
</html>
