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
    <style>
        .copy-btn {
            background:none;border:none;cursor:pointer;color:var(--text-3);
            padding:3px;border-radius:4px;transition:color 0.15s;display:inline-flex;align-items:center;
        }
        .copy-btn:hover { color:var(--accent); }
        .action-btn {
            background:var(--bg-hover);border:1px solid var(--border);color:var(--text-2);
            border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;transition:all 0.15s;
        }
        .action-btn:hover { border-color:var(--accent);color:var(--accent); }
        .regen-btn {
            background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);color:#d97706;
            border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;transition:all 0.15s;
        }
        .regen-btn:hover { background:rgba(245,158,11,0.2); }
        .progress-bar {
            height:4px;border-radius:99px;background:var(--border);margin-top:4px;overflow:hidden;
        }
        .progress-fill { height:4px;border-radius:99px;background:var(--accent);transition:width 0.3s; }
        .progress-fill.danger { background:#ef4444; }
    </style>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="main-wrapper">
    <header class="topbar">
        <h1 style="font-size:18px;font-weight:700;color:var(--text-1);flex:1;">API Keys y Consumo</h1>
        <span style="font-size:13px;color:var(--text-2);"><?= count($tenants) ?> tenants registrados</span>
    </header>

    <main class="main-content">

        <?php if ($flash): ?>
        <div style="margin-bottom:16px;padding:10px 16px;background:rgba(13,148,136,0.1);border:1px solid var(--accent);border-radius:10px;font-size:13px;color:var(--accent);">
            <?= htmlspecialchars($flash) ?>
        </div>
        <?php endif; ?>

        <div class="card" style="overflow:hidden;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Token</th>
                        <th>Plan</th>
                        <th style="text-align:center;">Estado</th>
                        <th style="text-align:center;">Consultas hoy</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:right;">Cache hit%</th>
                        <th>Último uso</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $t):
                        $pct        = $t['queries_per_day'] > 0 ? round($t['queries_today'] / $t['queries_per_day'] * 100) : 0;
                        $cacheRate  = $t['total_queries'] > 0 ? round($t['cache_hits'] / $t['total_queries'] * 100) : 0;
                        [$pillBg, $pillColor] = match($t['status']) {
                            'active'    => ['rgba(13,148,136,0.1)',  'var(--accent)'],
                            'trial'     => ['rgba(59,130,246,0.1)',  '#3b82f6'],
                            'suspended' => ['rgba(239,68,68,0.1)',   '#ef4444'],
                            default     => ['var(--bg-hover)',       'var(--text-2)'],
                        };
                        $tokenShort = substr($t['api_token'], 0, 16) . '...' . substr($t['api_token'], -8);
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;color:var(--text-1);"><?= htmlspecialchars($t['name']) ?></div>
                            <div style="font-size:11px;color:var(--text-3);"><?= htmlspecialchars($t['email']) ?></div>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <code style="font-size:11px;font-family:monospace;color:var(--text-2);background:var(--bg-hover);padding:3px 7px;border-radius:5px;border:1px solid var(--border);" id="token-<?= $t['id'] ?>">
                                    <?= $tokenShort ?>
                                </code>
                                <button class="copy-btn" onclick="copyToken('<?= htmlspecialchars($t['api_token'], ENT_QUOTES) ?>', this)" title="Copiar token completo">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        </td>
                        <td style="color:var(--text-2);font-size:13px;"><?= htmlspecialchars($t['plan_name']) ?></td>
                        <td style="text-align:center;">
                            <span class="pill" style="background:<?= $pillBg ?>;color:<?= $pillColor ?>;">
                                <?= ucfirst($t['status']) ?>
                            </span>
                        </td>
                        <td style="text-align:center;min-width:110px;">
                            <div style="font-weight:700;font-size:15px;color:var(--text-1);"><?= number_format($t['queries_today']) ?></div>
                            <div class="progress-bar">
                                <div class="progress-fill <?= $pct >= 90 ? 'danger' : '' ?>" style="width:<?= min($pct,100) ?>%;"></div>
                            </div>
                            <div style="font-size:10px;color:var(--text-3);margin-top:2px;"><?= $pct ?>% del límite</div>
                        </td>
                        <td style="text-align:right;font-weight:600;color:var(--text-1);"><?= number_format($t['total_queries']) ?></td>
                        <td style="text-align:right;">
                            <span style="font-weight:600;color:<?= $cacheRate >= 50 ? 'var(--accent)' : '#f59e0b' ?>;">
                                <?= $cacheRate ?>%
                            </span>
                        </td>
                        <td style="font-size:12px;color:var(--text-2);white-space:nowrap;">
                            <?= $t['last_used'] ? date('d/m/y H:i', strtotime($t['last_used'])) : 'Nunca' ?>
                        </td>
                        <td style="text-align:center;">
                            <div style="display:flex;gap:6px;justify-content:center;">
                                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Cambiar estado del tenant?')">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="action-btn" title="Activar / Suspender">
                                        <?= $t['status'] === 'suspended' ? '▶ Activar' : '⏸ Suspender' ?>
                                    </button>
                                </form>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Regenerar token? El token anterior quedará inválido.')">
                                    <input type="hidden" name="action" value="regenerate">
                                    <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="regen-btn" title="Regenerar token">↺ Regenerar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tenants)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:40px;color:var(--text-3);">
                            No hay tenants registrados aún.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<script>
function copyToken(token, btn) {
    navigator.clipboard.writeText(token).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<svg width="13" height="13" fill="none" stroke="#22c55e" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
        setTimeout(() => { btn.innerHTML = orig; }, 1500);
    });
}
</script>
</body>
</html>
