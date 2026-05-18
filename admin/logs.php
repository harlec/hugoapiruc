<?php
// admin/logs.php — Log de consultas con filtros
require_once __DIR__ . '/_layout.php';

$filter_tenant = (int)($_GET['tenant'] ?? 0);
$filter_type   = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 50;
$offset        = ($page - 1) * $perPage;

// Construir query dinámica
$where  = ['1=1'];
$params = [];

if ($filter_tenant > 0) {
    $where[]  = 'u.tenant_id = ?';
    $params[] = $filter_tenant;
}
if (in_array($filter_type, ['ruc','dni'])) {
    $where[]  = 'u.query_type = ?';
    $params[] = $filter_type;
}
if (in_array($filter_status, ['ok','error','rate_limit'])) {
    $where[]  = 'u.status = ?';
    $params[] = $filter_status;
}

$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM usage_log u WHERE $whereStr");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $db->prepare("
    SELECT u.*, t.name AS tenant_name
    FROM usage_log u JOIN tenants t ON t.id = u.tenant_id
    WHERE $whereStr
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$tenants = $db->query("SELECT id, name FROM tenants ORDER BY name")->fetchAll();

$statusClasses = [
    'ok'         => 'bg-green-900/40 text-green-400',
    'error'      => 'bg-red-900/40 text-red-400',
    'rate_limit' => 'bg-yellow-900/40 text-yellow-400',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs — PERÚdata Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-white flex">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="flex-1 ml-64 p-8">

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold">Logs de consultas</h1>
            <p class="text-gray-400 text-sm mt-0.5"><?= number_format($totalRows) ?> registros</p>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="flex gap-3 mb-6">
        <select name="tenant"
            class="bg-gray-800 border border-gray-700 text-sm text-white rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
            <option value="0">Todos los tenants</option>
            <?php foreach ($tenants as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $filter_tenant === $t['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <select name="type"
            class="bg-gray-800 border border-gray-700 text-sm text-white rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
            <option value="">RUC + DNI</option>
            <option value="ruc" <?= $filter_type === 'ruc' ? 'selected' : '' ?>>Solo RUC</option>
            <option value="dni" <?= $filter_type === 'dni' ? 'selected' : '' ?>>Solo DNI</option>
        </select>
        <select name="status"
            class="bg-gray-800 border border-gray-700 text-sm text-white rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
            <option value="">Todos los estados</option>
            <option value="ok" <?= $filter_status === 'ok' ? 'selected' : '' ?>>OK</option>
            <option value="error" <?= $filter_status === 'error' ? 'selected' : '' ?>>Error</option>
            <option value="rate_limit" <?= $filter_status === 'rate_limit' ? 'selected' : '' ?>>Rate Limit</option>
        </select>
        <button type="submit"
            class="bg-red-600 hover:bg-red-500 text-white text-sm font-medium px-4 py-2 rounded-xl transition-colors">
            Filtrar
        </button>
        <a href="/admin/logs.php"
            class="bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-medium px-4 py-2 rounded-xl transition-colors">
            Limpiar
        </a>
    </form>

    <!-- Tabla -->
    <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden mb-5">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-800 text-gray-400 text-xs uppercase tracking-wider">
                    <th class="text-left p-4">Fecha</th>
                    <th class="text-left p-4">Tenant</th>
                    <th class="text-left p-4">Tipo</th>
                    <th class="text-left p-4">Valor</th>
                    <th class="text-left p-4">Estado</th>
                    <th class="text-left p-4">Fuente</th>
                    <th class="text-left p-4">Ms</th>
                    <th class="text-left p-4">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                <?php foreach ($logs as $log): ?>
                <tr class="hover:bg-gray-800/50 transition-colors">
                    <td class="p-4 text-gray-400 text-xs font-mono whitespace-nowrap">
                        <?= date('d/m H:i:s', strtotime($log['created_at'])) ?>
                    </td>
                    <td class="p-4 text-gray-300 text-xs"><?= htmlspecialchars($log['tenant_name']) ?></td>
                    <td class="p-4">
                        <span class="font-mono text-xs bg-gray-800 px-1.5 py-0.5 rounded text-gray-300">
                            <?= strtoupper($log['query_type'] ?? '') ?>
                        </span>
                    </td>
                    <td class="p-4 font-mono text-xs"><?= htmlspecialchars($log['query_value'] ?? '') ?></td>
                    <td class="p-4">
                        <span class="px-2 py-0.5 rounded-full text-xs <?= $statusClasses[$log['status']] ?? '' ?>">
                            <?= $log['status'] ?>
                        </span>
                    </td>
                    <td class="p-4 text-gray-500 text-xs"><?= $log['from_cache'] ? '💾 caché' : '🌐 scraping' ?></td>
                    <td class="p-4 text-gray-400 text-xs"><?= $log['response_ms'] ?>ms</td>
                    <td class="p-4 text-gray-600 text-xs font-mono"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr><td colspan="8" class="p-8 text-center text-gray-600">Sin registros</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between">
        <span class="text-xs text-gray-500">
            Página <?= $page ?> de <?= $totalPages ?> (<?= number_format($totalRows) ?> registros)
        </span>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&tenant=<?= $filter_tenant ?>&type=<?= $filter_type ?>&status=<?= $filter_status ?>"
                class="bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm px-3 py-1.5 rounded-xl transition-colors">
                ← Anterior
            </a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>&tenant=<?= $filter_tenant ?>&type=<?= $filter_type ?>&status=<?= $filter_status ?>"
                class="bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm px-3 py-1.5 rounded-xl transition-colors">
                Siguiente →
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</main>
</body>
</html>
