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

// KPIs del período
$kpiStmt = $db->prepare("
    SELECT COUNT(*) AS total, SUM(from_cache) AS cached, ROUND(AVG(response_ms)) AS avg_ms
    FROM usage_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND status = 'ok'
");
$kpiStmt->execute([$days]);
$kpi = $kpiStmt->fetch();
$cacheRate = $kpi['total'] > 0 ? round($kpi['cached'] / $kpi['total'] * 100, 1) : 0;

// Top 10 consultados
$topItems = $db->prepare("
    SELECT query_type, query_value, COUNT(*) AS hits, MAX(created_at) AS last_seen
    FROM usage_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND status = 'ok'
    GROUP BY query_type, query_value
    ORDER BY hits DESC LIMIT 10
");
$topItems->execute([$days]);
$topItems = $topItems->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics — PERÚdata Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main-wrapper">
    <header class="topbar">
        <h1 style="font-size:18px;font-weight:700;color:var(--text-1);flex:1;">Analytics</h1>
        <div style="display:flex;gap:6px;align-items:center;">
            <?php foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días'] as $d => $label): ?>
            <a href="?days=<?= $d ?>" style="
                padding:6px 14px;border-radius:8px;font-size:12px;font-weight:600;
                text-decoration:none;transition:all 0.15s;
                <?= $days === $d
                    ? 'background:var(--accent);color:#fff;'
                    : 'background:var(--bg-hover);color:var(--text-2);border:1px solid var(--border);' ?>
            "><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </header>

    <main class="main-content">

        <!-- KPI cards -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px;">

            <div class="card" style="padding:20px;">
                <p style="font-size:11px;font-weight:600;color:var(--text-2);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px;">Total consultas</p>
                <div style="font-size:34px;font-weight:700;color:var(--text-1);"><?= number_format($kpi['total']) ?></div>
                <p style="font-size:12px;color:var(--text-3);margin-top:4px;">últimos <?= $days ?> días</p>
            </div>

            <div class="card" style="padding:20px;">
                <p style="font-size:11px;font-weight:600;color:var(--text-2);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px;">Cache Hit Rate</p>
                <div style="font-size:34px;font-weight:700;color:var(--text-1);"><?= $cacheRate ?>%</div>
                <p style="font-size:12px;color:var(--text-3);margin-top:4px;"><?= number_format($kpi['cached']) ?> desde caché</p>
            </div>

            <div class="card" style="padding:20px;">
                <p style="font-size:11px;font-weight:600;color:var(--text-2);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px;">Tiempo promedio</p>
                <div style="font-size:34px;font-weight:700;color:var(--text-1);"><?= $kpi['avg_ms'] ?? 0 ?>ms</div>
                <p style="font-size:12px;color:var(--text-3);margin-top:4px;">respuesta de la API</p>
            </div>

        </div>

        <!-- Gráfico consultas por día -->
        <div class="card" style="padding:20px;margin-bottom:20px;">
            <h2 style="font-size:13px;font-weight:600;color:var(--text-1);margin-bottom:16px;">
                Consultas diarias — últimos <?= $days ?> días
            </h2>
            <canvas id="chartDia" height="70"></canvas>
        </div>

        <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;margin-bottom:20px;">

            <!-- Donut RUC vs DNI -->
            <div class="card" style="padding:20px;">
                <h2 style="font-size:13px;font-weight:600;color:var(--text-1);margin-bottom:16px;">RUC vs DNI</h2>
                <canvas id="chartTipo" height="180"></canvas>
            </div>

            <!-- Top tenants -->
            <div class="card" style="padding:20px;">
                <h2 style="font-size:13px;font-weight:600;color:var(--text-1);margin-bottom:16px;">Top tenants por consumo</h2>
                <?php if (empty($byTenant)): ?>
                <p style="font-size:13px;color:var(--text-3);">Sin datos para este período.</p>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($byTenant as $i => $t): ?>
                    <?php $pct = $byTenant[0]['total'] > 0 ? ($t['total'] / $byTenant[0]['total'] * 100) : 0; ?>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:11px;color:var(--text-3);width:16px;flex-shrink:0;"><?= $i + 1 ?></span>
                        <div style="flex:1;">
                            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                                <span style="font-size:13px;font-weight:500;color:var(--text-1);"><?= htmlspecialchars($t['name']) ?></span>
                                <span style="font-size:12px;color:var(--text-2);"><?= number_format($t['total']) ?></span>
                            </div>
                            <div style="height:5px;background:var(--border);border-radius:99px;">
                                <div style="height:5px;background:var(--accent);border-radius:99px;width:<?= round($pct) ?>%;"></div>
                            </div>
                        </div>
                        <span style="font-size:11px;color:var(--text-3);width:64px;text-align:right;flex-shrink:0;"><?= htmlspecialchars($t['plan_name']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Top 10 RUC/DNI consultados -->
        <div class="card" style="overflow:hidden;margin-bottom:20px;">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);">
                <h2 style="font-size:13px;font-weight:600;color:var(--text-1);">Top 10 más consultados</h2>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipo</th>
                        <th>Número</th>
                        <th>Hits</th>
                        <th>Última consulta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topItems as $i => $item): ?>
                    <tr>
                        <td style="color:var(--text-3);font-size:12px;"><?= $i + 1 ?></td>
                        <td>
                            <span class="pill" style="background:var(--accent-bg);color:var(--accent);">
                                <?= strtoupper($item['query_type']) ?>
                            </span>
                        </td>
                        <td style="font-family:monospace;font-size:13px;"><?= htmlspecialchars($item['query_value']) ?></td>
                        <td style="font-weight:600;"><?= number_format($item['hits']) ?></td>
                        <td style="color:var(--text-2);font-size:12px;"><?= date('d/m/y H:i', strtotime($item['last_seen'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topItems)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-3);padding:32px;">Sin datos para este período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Resumen por tenant -->
        <div class="card" style="overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);">
                <h2 style="font-size:13px;font-weight:600;color:var(--text-1);">Resumen por tenant</h2>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Plan</th>
                        <th style="text-align:right;">Total consultas</th>
                        <th style="text-align:right;">% Caché</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($byTenant as $t):
                        $tenantCache = $t['total'] > 0 ? round($t['cached'] / $t['total'] * 100, 1) : 0;
                    ?>
                    <tr>
                        <td style="font-weight:500;"><?= htmlspecialchars($t['name']) ?></td>
                        <td>
                            <span class="pill" style="background:var(--bg-hover);color:var(--text-2);border:1px solid var(--border);">
                                <?= htmlspecialchars($t['plan_name']) ?>
                            </span>
                        </td>
                        <td style="text-align:right;font-weight:600;"><?= number_format($t['total']) ?></td>
                        <td style="text-align:right;font-weight:600;color:<?= $tenantCache >= 40 ? 'var(--accent)' : '#f97316' ?>;"><?= $tenantCache ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($byTenant)): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-3);padding:32px;">Sin datos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<script>
(function(){
  const isDark     = document.body.classList.contains('dark-mode');
  const gridColor  = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
  const tickColor  = isDark ? '#64748b' : '#94a3b8';
  const legendColor= isDark ? '#94a3b8' : '#64748b';

  const labels  = <?= json_encode(array_column($byDay, 'dia')) ?>;
  const cached  = <?= json_encode(array_map('intval', array_column($byDay, 'cached'))) ?>;
  const scraped = <?= json_encode(array_map('intval', array_column($byDay, 'scraped'))) ?>;

  new Chart(document.getElementById('chartDia'), {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'Scraping', data: scraped, backgroundColor: 'rgba(239,68,68,0.65)', borderRadius: 4, stack: 'a' },
        { label: 'Caché',    data: cached,  backgroundColor: 'rgba(13,148,136,0.65)', borderRadius: 4, stack: 'a' },
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { labels: { color: legendColor, font: { size: 11 } } } },
      scales: {
        x: { stacked: true, ticks: { color: tickColor, font:{size:11} }, grid: { color: gridColor } },
        y: { stacked: true, ticks: { color: tickColor, font:{size:11} }, grid: { color: gridColor } },
      }
    }
  });

  const typeData   = <?= json_encode(array_map(fn($r) => (int)$r['total'], $byType)) ?>;
  const typeLabels = <?= json_encode(array_map(fn($r) => strtoupper($r['query_type']), $byType)) ?>;
  new Chart(document.getElementById('chartTipo'), {
    type: 'doughnut',
    data: {
      labels: typeLabels,
      datasets: [{ data: typeData, backgroundColor: ['#ef4444','#0d9488'], borderWidth: 0 }]
    },
    options: {
      cutout: '65%',
      plugins: {
        legend: { position: 'bottom', labels: { color: legendColor, font: { size: 11 }, padding: 16 } }
      }
    }
  });
})();
</script>
</body>
</html>
