<?php
// admin/index.php — Dashboard principal
require_once __DIR__ . '/_layout.php';

// ── KPIs ──────────────────────────────────────────────────────────────────────
$todayQueries = (int)$db->query("
    SELECT COUNT(*) FROM usage_log WHERE DATE(created_at) = CURDATE() AND status = 'ok'
")->fetchColumn();

$yesterdayQueries = (int)$db->query("
    SELECT COUNT(*) FROM usage_log WHERE DATE(created_at) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND status = 'ok'
")->fetchColumn();

$pctChange = $yesterdayQueries > 0
    ? round((($todayQueries - $yesterdayQueries) / $yesterdayQueries) * 100, 1) : 0;

$cacheStats = $db->query("
    SELECT COUNT(*) AS total, SUM(from_cache) AS hits
    FROM usage_log WHERE DATE(created_at) = CURDATE() AND status = 'ok'
")->fetch();
$hitRate = $cacheStats['total'] > 0
    ? round(($cacheStats['hits'] / $cacheStats['total']) * 100, 1) : 0;

$activeTenants = (int)$db->query("SELECT COUNT(*) FROM tenants WHERE status IN ('active','trial')")->fetchColumn();
$newThisMonth  = (int)$db->query("SELECT COUNT(*) FROM tenants WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

$sources = $db->query("SELECT * FROM source_monitors ORDER BY id")->fetchAll();
$srcOk   = count(array_filter($sources, fn($s) => $s['last_status'] === 'ok'));

// ── Actividad últimos 7 días ───────────────────────────────────────────────────
$last7 = $db->query("
    SELECT DATE(created_at) AS dia,
           COUNT(*) AS total,
           SUM(from_cache) AS cached
    FROM usage_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status='ok'
    GROUP BY dia ORDER BY dia
")->fetchAll();

// Rellenar días faltantes
$chartDays   = [];
$chartTotal  = [];
$chartCached = [];
for ($i = 6; $i >= 0; $i--) {
    $day   = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime($day));
    $found = array_values(array_filter($last7, fn($r) => $r['dia'] === $day));
    $chartDays[]   = $label;
    $chartTotal[]  = $found ? (int)$found[0]['total']  : 0;
    $chartCached[] = $found ? (int)$found[0]['cached'] : 0;
}

// ── Consultas por tipo hoy ─────────────────────────────────────────────────────
$byType = $db->query("
    SELECT query_type, COUNT(*) AS total
    FROM usage_log WHERE DATE(created_at) = CURDATE() AND status='ok'
    GROUP BY query_type
")->fetchAll(\PDO::FETCH_KEY_PAIR);

// ── Top tenants consumo hoy ────────────────────────────────────────────────────
$topTenants = $db->query("
    SELECT t.name, t.email, p.name AS plan_name, p.queries_per_day,
           COUNT(u.id) AS used_today
    FROM tenants t
    JOIN plans p ON p.id = t.plan_id
    LEFT JOIN usage_log u ON u.tenant_id = t.id AND DATE(u.created_at) = CURDATE()
    WHERE t.status IN ('active','trial')
    GROUP BY t.id
    ORDER BY used_today DESC
    LIMIT 6
")->fetchAll();

// ── Últimas consultas ──────────────────────────────────────────────────────────
$recentLogs = $db->query("
    SELECT u.query_type, u.query_value, u.from_cache, u.response_ms, u.status, u.created_at,
           t.name AS tenant_name
    FROM usage_log u JOIN tenants t ON t.id = u.tenant_id
    ORDER BY u.created_at DESC LIMIT 8
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
<style>
  body { background: #0d1117; }
  .card { background: #161b27; border: 1px solid #1e2536; border-radius: 16px; }
  .card-inner { background: #1a2035; border-radius: 12px; }
  ::-webkit-scrollbar { width: 4px; } ::-webkit-scrollbar-track { background: #0d1117; } ::-webkit-scrollbar-thumb { background: #2d3748; border-radius: 4px; }
</style>
</head>
<body class="text-gray-100 flex h-screen overflow-hidden">

<!-- ── Sidebar ─────────────────────────────────────────────────────────────── -->
<?php include __DIR__ . '/_sidebar.php'; ?>

<!-- ── Main ───────────────────────────────────────────────────────────────── -->
<div class="flex-1 ml-64 flex flex-col overflow-hidden">

  <!-- Top Navbar -->
  <header style="background:#161b27;border-bottom:1px solid #1e2536;" class="flex items-center px-6 py-3.5 gap-4 shrink-0">
    <!-- Search -->
    <div class="flex-1 max-w-md">
      <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" placeholder="Buscar tenants, RUC, DNI..."
          style="background:#0d1117;border:1px solid #1e2536;"
          class="w-full pl-9 pr-4 py-2 rounded-xl text-sm text-gray-300 placeholder-gray-600 focus:outline-none focus:border-indigo-500">
      </div>
    </div>

    <div class="flex items-center gap-3 ml-auto">
      <!-- Notificación fuentes caídas -->
      <?php if ($srcOk < count($sources)): ?>
      <div class="relative">
        <button style="background:#1a2035;" class="p-2 rounded-xl relative">
          <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-xs flex items-center justify-center font-bold">
            <?= count($sources) - $srcOk ?>
          </span>
        </button>
      </div>
      <?php endif; ?>

      <!-- Links rápidos -->
      <a href="/playground.php" target="_blank"
        style="background:#1a2035;" class="px-3 py-2 rounded-xl text-xs text-indigo-400 hover:text-indigo-300 font-medium transition flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        API Tester
      </a>

      <!-- User -->
      <div style="background:#1a2035;" class="flex items-center gap-2.5 px-3 py-2 rounded-xl">
        <div class="w-7 h-7 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center text-xs font-bold">
          <?= strtoupper(substr($_SESSION['admin_user'] ?? 'A', 0, 1)) ?>
        </div>
        <div class="leading-tight">
          <div class="text-xs font-semibold text-white"><?= htmlspecialchars($_SESSION['admin_user'] ?? '') ?></div>
          <div class="text-xs text-gray-500">Admin</div>
        </div>
      </div>
    </div>
  </header>

  <!-- Scrollable content -->
  <main class="flex-1 overflow-auto p-6">

    <!-- Page title -->
    <div class="mb-6">
      <h1 class="text-xl font-bold text-white">Dashboard</h1>
      <p class="text-gray-500 text-sm"><?= date('l, d \d\e F Y') ?></p>
    </div>

    <!-- ── KPI Cards ── -->
    <div class="grid grid-cols-4 gap-4 mb-6">

      <!-- Consultas hoy -->
      <div class="card p-5">
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Consultas Hoy</p>
            <?php if ($pctChange >= 0): ?>
            <span class="text-xs text-teal-400 font-medium">↑ <?= abs($pctChange) ?>%</span>
            <?php else: ?>
            <span class="text-xs text-red-400 font-medium">↓ <?= abs($pctChange) ?>%</span>
            <?php endif; ?>
          </div>
          <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(20,184,166,0.15)">
            <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
          </div>
        </div>
        <div class="text-3xl font-bold text-white"><?= number_format($todayQueries) ?></div>
        <p class="text-xs text-gray-500 mt-1">vs <?= number_format($yesterdayQueries) ?> ayer</p>
      </div>

      <!-- Cache Hit Rate -->
      <div class="card p-5">
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Cache Hit Rate</p>
            <?php if ($hitRate < 30): ?>
            <span class="text-xs text-orange-400 font-medium">Alert</span>
            <?php else: ?>
            <span class="text-xs text-orange-400 font-medium">Ahorro en uso</span>
            <?php endif; ?>
          </div>
          <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(251,146,60,0.15)">
            <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
          </div>
        </div>
        <div class="text-3xl font-bold text-white"><?= $hitRate ?>%</div>
        <p class="text-xs text-gray-500 mt-1"><?= number_format($cacheStats['hits']) ?> desde caché hoy</p>
      </div>

      <!-- Tenants activos -->
      <div class="card p-5">
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Tenants Activos</p>
            <span class="text-xs text-pink-400 font-medium">Active</span>
          </div>
          <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(236,72,153,0.15)">
            <svg class="w-5 h-5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
          </div>
        </div>
        <div class="text-3xl font-bold text-white"><?= $activeTenants ?></div>
        <p class="text-xs text-gray-500 mt-1">+<?= $newThisMonth ?> este mes</p>
      </div>

      <!-- Fuentes OK -->
      <div class="card p-5">
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Fuentes API</p>
            <span class="text-xs <?= $srcOk === count($sources) ? 'text-indigo-400' : 'text-yellow-400' ?> font-medium">
              <?= $srcOk === count($sources) ? round($srcOk / max(count($sources),1) * 100) . '%' : 'Alerta' ?>
            </span>
          </div>
          <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(99,102,241,0.15)">
            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
            </svg>
          </div>
        </div>
        <div class="text-3xl font-bold <?= $srcOk < count($sources) ? 'text-yellow-400' : 'text-white' ?>">
          <?= $srcOk ?> / <?= count($sources) ?>
        </div>
        <p class="text-xs text-gray-500 mt-1"><?= $srcOk < count($sources) ? count($sources)-$srcOk.' con problemas' : 'Todas operativas' ?></p>
      </div>
    </div>

    <!-- ── Row 2: Gráfico + Monitor ── -->
    <div class="grid grid-cols-3 gap-4 mb-6">

      <!-- Gráfico actividad semanal -->
      <div class="card col-span-2 p-6">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h2 class="text-sm font-semibold text-white">Actividad Semanal</h2>
            <p class="text-xs text-gray-500 mt-0.5">Consultas en vivo vs desde caché</p>
          </div>
          <div class="flex items-center gap-4 text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span class="w-3 h-0.5 bg-teal-400 inline-block rounded"></span>En vivo</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-0.5 bg-indigo-400 inline-block rounded"></span>Caché</span>
          </div>
        </div>
        <canvas id="chartActividad" height="110"></canvas>
      </div>

      <!-- Monitor fuentes estilo SupplyChain -->
      <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-white">Monitor de Fuentes</h2>
          <a href="/admin/monitor.php?check=1" class="text-xs text-indigo-400 hover:text-indigo-300">↺ Verificar</a>
        </div>

        <div class="space-y-3">
          <?php
          $srcColors = [
            'ok'      => ['bg'=>'rgba(20,184,166,0.12)','icon'=>'text-teal-400','dot'=>'bg-teal-400','label'=>'Operativa'],
            'slow'    => ['bg'=>'rgba(251,191,36,0.12)','icon'=>'text-yellow-400','dot'=>'bg-yellow-400','label'=>'Lenta'],
            'error'   => ['bg'=>'rgba(239,68,68,0.12)','icon'=>'text-red-400','dot'=>'bg-red-400','label'=>'Error'],
            'changed' => ['bg'=>'rgba(251,146,60,0.12)','icon'=>'text-orange-400','dot'=>'bg-orange-400','label'=>'Cambio HTML'],
          ];
          $srcIcons = [
            'SUNAT_RUC'  => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
            'RENIEC_DNI' => 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2',
            'BACKUP_API' => 'M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
          ];
          foreach ($sources as $src):
            $sc = $srcColors[$src['last_status']] ?? $srcColors['error'];
            $iconPath = $srcIcons[$src['source_name']] ?? $srcIcons['BACKUP_API'];
          ?>
          <div style="background:<?= $sc['bg'] ?>;border-radius:12px;" class="p-3.5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(255,255,255,0.05)">
                  <svg class="w-4 h-4 <?= $sc['icon'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $iconPath ?>"/>
                  </svg>
                </div>
                <div>
                  <div class="text-xs font-semibold text-white"><?= htmlspecialchars($src['source_name']) ?></div>
                  <div class="text-xs text-gray-500"><?= $src['last_check'] ? date('H:i', strtotime($src['last_check'])) : 'Sin chequeo' ?></div>
                </div>
              </div>
              <div class="text-right">
                <div class="text-sm font-bold <?= $sc['icon'] ?>"><?= $src['response_ms'] ?>ms</div>
                <div class="text-xs <?= $sc['icon'] ?>"><?= $sc['label'] ?></div>
              </div>
            </div>
            <?php if ($src['consecutive_failures'] > 0): ?>
            <div class="mt-2 flex items-center gap-1 text-xs text-red-400">
              <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse inline-block"></span>
              <?= $src['consecutive_failures'] ?> fallos consecutivos
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ── Row 3: Consumo tenants + Actividad reciente ── -->
    <div class="grid grid-cols-2 gap-4">

      <!-- Consumo por tenant (estilo Storage Utilization) -->
      <div class="card p-6">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-sm font-semibold text-white">Consumo por Tenant — Hoy</h2>
          <a href="/admin/api-keys.php" class="text-xs text-indigo-400 hover:text-indigo-300">Ver todos →</a>
        </div>

        <div class="space-y-4">
          <?php foreach ($topTenants as $t):
            $pct = $t['queries_per_day'] > 0 ? min(100, round($t['used_today'] / $t['queries_per_day'] * 100)) : 0;
            $barColor = $pct >= 90 ? '#ef4444' : ($pct >= 70 ? '#f59e0b' : '#14b8a6');
          ?>
          <div>
            <div class="flex items-center justify-between mb-1">
              <div>
                <span class="text-sm text-white font-medium"><?= htmlspecialchars($t['name']) ?></span>
                <span style="background:#1a2035;" class="ml-2 text-xs text-gray-400 px-1.5 py-0.5 rounded">
                  <?= htmlspecialchars($t['plan_name']) ?>
                </span>
              </div>
              <span class="text-sm font-bold" style="color:<?= $barColor ?>"><?= $pct ?>%</span>
            </div>
            <div class="text-xs text-gray-500 mb-1.5">
              <?= number_format($t['used_today']) ?> / <?= number_format($t['queries_per_day']) ?> consultas
            </div>
            <div style="background:#1a2035;border-radius:99px;height:6px;" class="w-full">
              <div style="width:<?= $pct ?>%;background:<?= $barColor ?>;border-radius:99px;height:6px;transition:width 0.5s;"></div>
            </div>
          </div>
          <?php endforeach; ?>

          <?php if (empty($topTenants)): ?>
          <p class="text-gray-600 text-sm text-center py-4">Sin consultas hoy</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Actividad reciente -->
      <div class="card p-6">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-sm font-semibold text-white">Actividad Reciente</h2>
          <a href="/admin/logs.php" class="text-xs text-indigo-400 hover:text-indigo-300">Ver logs →</a>
        </div>

        <div class="space-y-2">
          <?php foreach ($recentLogs as $log):
            $dotColor = match($log['status']) { 'ok' => 'bg-teal-400', 'error' => 'bg-red-400', 'rate_limit' => 'bg-yellow-400', default => 'bg-gray-500' };
            $typeColor = $log['query_type'] === 'ruc' ? 'text-indigo-400' : 'text-pink-400';
          ?>
          <div style="border-bottom:1px solid #1e2536;" class="flex items-center gap-3 py-2">
            <span class="w-2 h-2 rounded-full <?= $dotColor ?> shrink-0"></span>
            <span class="text-xs font-bold font-mono <?= $typeColor ?> w-8 shrink-0"><?= strtoupper($log['query_type'] ?? '') ?></span>
            <span class="text-xs font-mono text-gray-300 flex-1 truncate"><?= htmlspecialchars($log['query_value'] ?? '') ?></span>
            <span class="text-xs text-gray-500 shrink-0"><?= $log['from_cache'] ? '💾' : '🌐' ?></span>
            <span class="text-xs text-gray-600 shrink-0 w-14 text-right"><?= $log['response_ms'] ?>ms</span>
            <span class="text-xs text-gray-600 shrink-0 w-12 text-right"><?= date('H:i', strtotime($log['created_at'])) ?></span>
          </div>
          <?php endforeach; ?>

          <?php if (empty($recentLogs)): ?>
          <p class="text-gray-600 text-sm text-center py-8">Sin actividad aún</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </main>
</div>

<script>
const chartDays   = <?= json_encode($chartDays) ?>;
const chartLive   = <?= json_encode(array_map(fn($t,$c) => $t - $c, $chartTotal, $chartCached)) ?>;
const chartCached = <?= json_encode($chartCached) ?>;

Chart.defaults.color = '#6b7280';
Chart.defaults.font.family = 'system-ui, sans-serif';

new Chart(document.getElementById('chartActividad'), {
  type: 'line',
  data: {
    labels: chartDays,
    datasets: [
      {
        label: 'En vivo',
        data: chartLive,
        borderColor: '#14b8a6',
        backgroundColor: 'rgba(20,184,166,0.08)',
        pointBackgroundColor: '#14b8a6',
        pointRadius: 4,
        pointHoverRadius: 6,
        tension: 0.4,
        fill: true,
      },
      {
        label: 'Caché',
        data: chartCached,
        borderColor: '#6366f1',
        backgroundColor: 'rgba(99,102,241,0.08)',
        pointBackgroundColor: '#6366f1',
        pointRadius: 4,
        pointHoverRadius: 6,
        tension: 0.4,
        fill: true,
      },
    ]
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1a2035',
        borderColor: '#1e2536',
        borderWidth: 1,
        titleColor: '#e5e7eb',
        bodyColor: '#9ca3af',
        padding: 10,
      }
    },
    scales: {
      x: { grid: { color: '#1e2536' }, ticks: { color: '#4b5563' } },
      y: { grid: { color: '#1e2536' }, ticks: { color: '#4b5563' }, beginAtZero: true },
    }
  }
});
</script>

</body>
</html>
