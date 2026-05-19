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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs — PERÚdata Admin</title>
    <style>
        .filter-select {
            background:var(--bg-hover);border:1px solid var(--border);color:var(--text-1);
            border-radius:8px;padding:7px 10px;font-size:13px;outline:none;cursor:pointer;
            transition:border-color 0.15s;
        }
        .filter-select:focus { border-color:var(--accent); }
        .btn-filter {
            background:var(--accent);color:#fff;border:none;border-radius:8px;
            padding:7px 16px;font-size:13px;font-weight:600;cursor:pointer;transition:opacity 0.15s;
        }
        .btn-filter:hover { opacity:0.88; }
        .btn-clear {
            background:var(--bg-hover);color:var(--text-2);border:1px solid var(--border);
            border-radius:8px;padding:7px 16px;font-size:13px;font-weight:600;cursor:pointer;
            text-decoration:none;display:inline-flex;align-items:center;transition:background 0.15s;
        }
        .btn-clear:hover { background:var(--border); }
        .status-dot {
            width:7px;height:7px;border-radius:50%;display:inline-block;margin-right:5px;flex-shrink:0;
        }
        .page-btn {
            background:var(--bg-hover);border:1px solid var(--border);color:var(--text-2);
            border-radius:8px;padding:6px 14px;font-size:13px;text-decoration:none;
            transition:all 0.15s;display:inline-block;
        }
        .page-btn:hover { border-color:var(--accent);color:var(--accent); }
    </style>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main-wrapper">
    <header class="topbar">
        <div style="flex:1;">
            <h1 style="font-size:18px;font-weight:700;color:var(--text-1);">Logs de Consultas</h1>
            <p style="font-size:12px;color:var(--text-2);margin-top:2px;"><?= number_format($totalRows) ?> registros encontrados</p>
        </div>
    </header>

    <main class="main-content">

        <!-- Filtros -->
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:20px;">
            <select name="tenant" class="filter-select">
                <option value="0">Todos los tenants</option>
                <?php foreach ($tenants as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $filter_tenant === $t['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="type" class="filter-select">
                <option value="">RUC + DNI</option>
                <option value="ruc" <?= $filter_type === 'ruc' ? 'selected' : '' ?>>Solo RUC</option>
                <option value="dni" <?= $filter_type === 'dni' ? 'selected' : '' ?>>Solo DNI</option>
            </select>

            <select name="status" class="filter-select">
                <option value="">Todos los estados</option>
                <option value="ok"         <?= $filter_status === 'ok'         ? 'selected' : '' ?>>OK</option>
                <option value="error"      <?= $filter_status === 'error'      ? 'selected' : '' ?>>Error</option>
                <option value="rate_limit" <?= $filter_status === 'rate_limit' ? 'selected' : '' ?>>Rate Limit</option>
            </select>

            <button type="submit" class="btn-filter">Filtrar</button>
            <a href="/admin/logs.php" class="btn-clear">Limpiar</a>
        </form>

        <!-- Tabla -->
        <div class="card" style="overflow:hidden;margin-bottom:16px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tiempo</th>
                        <th>Tenant</th>
                        <th>Tipo</th>
                        <th>Número consultado</th>
                        <th>Origen</th>
                        <th>Estado</th>
                        <th style="text-align:right;">MS</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        [$dotColor, $pillBg, $pillColor] = match($log['status']) {
                            'ok'         => ['#22c55e', 'rgba(34,197,94,0.1)',  '#16a34a'],
                            'error'      => ['#ef4444', 'rgba(239,68,68,0.1)',  '#dc2626'],
                            'rate_limit' => ['#f59e0b', 'rgba(245,158,11,0.1)','#d97706'],
                            default      => ['#94a3b8', 'rgba(148,163,184,0.1)','#64748b'],
                        };
                    ?>
                    <tr>
                        <td style="font-family:monospace;font-size:12px;color:var(--text-2);white-space:nowrap;">
                            <?= date('d/m H:i:s', strtotime($log['created_at'])) ?>
                        </td>
                        <td style="font-size:13px;color:var(--text-2);"><?= htmlspecialchars($log['tenant_name']) ?></td>
                        <td>
                            <span class="pill" style="background:var(--bg-hover);color:var(--text-2);font-family:monospace;">
                                <?= strtoupper($log['query_type'] ?? '') ?>
                            </span>
                        </td>
                        <td style="font-family:monospace;font-size:13px;"><?= htmlspecialchars($log['query_value'] ?? '') ?></td>
                        <td style="font-size:12px;color:var(--text-3);">
                            <?= $log['from_cache'] ? 'caché' : 'live' ?>
                        </td>
                        <td>
                            <span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;background:<?= $pillBg ?>;color:<?= $pillColor ?>;">
                                <span class="status-dot" style="background:<?= $dotColor ?>;"></span>
                                <?= $log['status'] ?>
                            </span>
                        </td>
                        <td style="text-align:right;font-size:12px;color:var(--text-2);font-family:monospace;">
                            <?= $log['response_ms'] ?>
                        </td>
                        <td style="font-family:monospace;font-size:11px;color:var(--text-3);">
                            <?= htmlspecialchars($log['ip_address'] ?? '') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:40px;color:var(--text-3);">
                            Sin registros con los filtros actuales.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:12px;color:var(--text-3);">
                Página <?= $page ?> de <?= $totalPages ?> — <?= number_format($totalRows) ?> registros
            </span>
            <div style="display:flex;gap:8px;">
                <?php $qs = http_build_query(['tenant'=>$filter_tenant,'type'=>$filter_type,'status'=>$filter_status]); ?>
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&<?= $qs ?>" class="page-btn">← Anterior</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&<?= $qs ?>" class="page-btn">Siguiente →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
