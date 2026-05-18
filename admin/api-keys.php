<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

session_start();
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php'); exit;
}

use App\DB;
$db = DB::connection();

// Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'regenerate' && !empty($_POST['tenant_id'])) {
        $newToken = \App\Auth\TokenManager::generate();
        $db->prepare("UPDATE tenants SET api_token = ? WHERE id = ?")
           ->execute([$newToken, (int)$_POST['tenant_id']]);
        $_SESSION['flash'] = 'Token regenerado correctamente.';
    }

    if ($action === 'toggle' && !empty($_POST['tenant_id'])) {
        $db->prepare("UPDATE tenants SET status = CASE WHEN status='active' THEN 'suspended' WHEN status='suspended' THEN 'active' ELSE status END WHERE id = ?")
           ->execute([(int)$_POST['tenant_id']]);
        $_SESSION['flash'] = 'Estado actualizado.';
    }

    header('Location: api-keys.php'); exit;
}

// Datos: tenants con uso
$tenants = $db->query("
    SELECT t.*, p.name AS plan_name, p.queries_per_day,
           COUNT(u.id)                                         AS total_queries,
           SUM(CASE WHEN DATE(u.created_at) = CURDATE() THEN 1 ELSE 0 END) AS queries_today,
           SUM(u.from_cache)                                   AS cache_hits,
           MAX(u.created_at)                                   AS last_used
    FROM tenants t
    JOIN plans p ON p.id = t.plan_id
    LEFT JOIN usage_log u ON u.tenant_id = t.id
    GROUP BY t.id
    ORDER BY queries_today DESC, t.created_at DESC
")->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API Keys — PERÚdata Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex">

<!-- Sidebar -->
<aside class="w-56 bg-gray-900 border-r border-gray-800 flex flex-col shrink-0">
  <div class="p-4 border-b border-gray-800">
    <div class="flex items-center gap-2">
      <div class="w-7 h-7 bg-red-600 rounded-lg flex items-center justify-center font-bold text-xs">PE</div>
      <span class="font-semibold text-sm">PERÚdata Admin</span>
    </div>
  </div>
  <nav class="flex-1 p-3 space-y-1 text-sm">
    <a href="index.php"    class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white">📊 Dashboard</a>
    <a href="tenants.php"  class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white">👥 Tenants</a>
    <a href="api-keys.php" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-800 text-white font-medium">🔑 API Keys</a>
    <a href="monitor.php"  class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white">📡 Monitor</a>
    <a href="analytics.php"class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white">📈 Analytics</a>
    <a href="plans.php"    class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white">💳 Planes</a>
    <a href="logs.php"     class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white">📋 Logs</a>
  </nav>
  <div class="p-3 border-t border-gray-800">
    <a href="../playground.php" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white text-sm">▶ Probar API</a>
    <a href="../docs.php" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white text-sm">📚 Docs</a>
    <a href="logout.php" class="flex items-center gap-2 px-3 py-2 rounded-lg text-red-400 hover:bg-red-950 text-sm mt-1">⏏ Salir</a>
  </div>
</aside>

<div class="flex-1 flex flex-col overflow-hidden">
  <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between">
    <h1 class="text-xl font-bold">🔑 API Keys y Consumo</h1>
    <span class="text-sm text-gray-400"><?= count($tenants) ?> tenants registrados</span>
  </header>

  <main class="flex-1 overflow-auto p-6">
    <?php if ($flash): ?>
    <div class="mb-4 px-4 py-3 bg-green-900 border border-green-700 rounded-lg text-green-300 text-sm"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <!-- Tabla -->
    <div class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-800 text-gray-400 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Tenant</th>
            <th class="px-4 py-3 text-left">API Token</th>
            <th class="px-4 py-3 text-left">Plan</th>
            <th class="px-4 py-3 text-center">Estado</th>
            <th class="px-4 py-3 text-center">Hoy</th>
            <th class="px-4 py-3 text-center">Total</th>
            <th class="px-4 py-3 text-center">Cache Hit%</th>
            <th class="px-4 py-3 text-left">Último uso</th>
            <th class="px-4 py-3 text-center">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-800">
          <?php foreach ($tenants as $t):
            $pct = $t['queries_per_day'] > 0 ? round($t['queries_today'] / $t['queries_per_day'] * 100) : 0;
            $cacheRate = $t['total_queries'] > 0 ? round($t['cache_hits'] / $t['total_queries'] * 100) : 0;
            $statusColor = match($t['status']) {
              'active'    => 'bg-green-900 text-green-300',
              'trial'     => 'bg-blue-900 text-blue-300',
              'suspended' => 'bg-red-900 text-red-300',
              default     => 'bg-gray-700 text-gray-300'
            };
          ?>
          <tr class="hover:bg-gray-800/50 transition">
            <td class="px-4 py-3">
              <div class="font-medium text-white"><?= htmlspecialchars($t['name']) ?></div>
              <div class="text-xs text-gray-500"><?= htmlspecialchars($t['email']) ?></div>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <code class="text-xs font-mono text-gray-400 bg-gray-800 px-2 py-1 rounded" id="token-<?= $t['id'] ?>">
                  <?= substr($t['api_token'], 0, 16) ?>...<?= substr($t['api_token'], -8) ?>
                </code>
                <button onclick="copyToken('<?= $t['api_token'] ?>')"
                  class="text-gray-500 hover:text-white transition" title="Copiar token completo">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
              </div>
            </td>
            <td class="px-4 py-3 text-gray-300"><?= htmlspecialchars($t['plan_name']) ?></td>
            <td class="px-4 py-3 text-center">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $statusColor ?>">
                <?= ucfirst($t['status']) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <div class="text-white font-medium"><?= number_format($t['queries_today']) ?></div>
              <div class="w-full bg-gray-700 rounded-full h-1 mt-1">
                <div class="bg-red-500 h-1 rounded-full" style="width: <?= min($pct, 100) ?>%"></div>
              </div>
              <div class="text-xs text-gray-500 mt-0.5"><?= $pct ?>% del límite</div>
            </td>
            <td class="px-4 py-3 text-center text-gray-300"><?= number_format($t['total_queries']) ?></td>
            <td class="px-4 py-3 text-center">
              <span class="text-<?= $cacheRate >= 50 ? 'green' : 'yellow' ?>-400 font-medium"><?= $cacheRate ?>%</span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-400">
              <?= $t['last_used'] ? date('d/m/y H:i', strtotime($t['last_used'])) : 'Nunca' ?>
            </td>
            <td class="px-4 py-3 text-center">
              <div class="flex gap-1 justify-center">
                <!-- Toggle estado -->
                <form method="POST" onsubmit="return confirm('¿Cambiar estado?')">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                  <button type="submit"
                    class="px-2 py-1 text-xs rounded bg-gray-700 hover:bg-gray-600 text-gray-300 transition"
                    title="Activar/Suspender">
                    <?= $t['status'] === 'suspended' ? '▶' : '⏸' ?>
                  </button>
                </form>
                <!-- Regenerar token -->
                <form method="POST" onsubmit="return confirm('¿Regenerar token? El token anterior quedará inválido.')">
                  <input type="hidden" name="action" value="regenerate">
                  <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                  <button type="submit"
                    class="px-2 py-1 text-xs rounded bg-yellow-900 hover:bg-yellow-800 text-yellow-300 transition"
                    title="Regenerar token">↺</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (empty($tenants)): ?>
      <div class="text-center py-12 text-gray-500">No hay tenants registrados aún.</div>
      <?php endif; ?>
    </div>
  </main>
</div>

<script>
function copyToken(token) {
  navigator.clipboard.writeText(token).then(() => {
    const btn = event.currentTarget;
    btn.innerHTML = '<svg class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
    setTimeout(() => {
      btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
    }, 1500);
  });
}
</script>
</body>
</html>
