<?php
// admin/tenants.php — Gestión de clientes multi-tenant
require_once __DIR__ . '/_layout.php';

use App\Auth\TokenManager;

$msg = '';
$err = '';

// ── Acciones POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $planId  = (int)($_POST['plan_id'] ?? 1);
        $status  = $_POST['status'] ?? 'trial';
        $expires = $_POST['expires_at'] ?: null;
        $token   = TokenManager::generate();

        try {
            $db->prepare("
                INSERT INTO tenants (name, email, api_token, plan_id, status, expires_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$name, $email, $token, $planId, $status, $expires]);
            $msg = "Tenant creado. Token: <code class='font-mono bg-gray-800 px-1 py-0.5 rounded text-xs'>$token</code>";
        } catch (\Exception $e) {
            $err = 'Error: ' . $e->getMessage();
        }
    }

    if ($action === 'suspend' && isset($_POST['id'])) {
        $db->prepare("UPDATE tenants SET status = 'suspended' WHERE id = ?")
           ->execute([(int)$_POST['id']]);
        $msg = 'Tenant suspendido.';
    }

    if ($action === 'activate' && isset($_POST['id'])) {
        $db->prepare("UPDATE tenants SET status = 'active' WHERE id = ?")
           ->execute([(int)$_POST['id']]);
        $msg = 'Tenant activado.';
    }

    if ($action === 'regen_token' && isset($_POST['id'])) {
        $new = TokenManager::generate();
        $db->prepare("UPDATE tenants SET api_token = ? WHERE id = ?")
           ->execute([$new, (int)$_POST['id']]);
        $msg = "Token regenerado: <code class='font-mono bg-gray-800 px-1 rounded text-xs'>$new</code>";
    }
}

// ── Datos ─────────────────────────────────────────────────────────────────────
$tenants = $db->query("
    SELECT t.*, p.name AS plan_name,
        (SELECT COUNT(*) FROM usage_log u WHERE u.tenant_id = t.id AND DATE(u.created_at) = CURDATE()) AS today_uses
    FROM tenants t JOIN plans p ON p.id = t.plan_id
    ORDER BY t.created_at DESC
")->fetchAll();

$plans = $db->query("SELECT * FROM plans WHERE is_active = 1")->fetchAll();

$statusBadge = [
    'active'    => 'bg-green-900/50 text-green-400 border border-green-700',
    'trial'     => 'bg-blue-900/50 text-blue-400 border border-blue-700',
    'suspended' => 'bg-red-900/50 text-red-400 border border-red-700',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenants — PERÚdata Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-white flex">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="flex-1 ml-64 p-8">

    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold">Tenants</h1>
        <button onclick="document.getElementById('modalCreate').classList.remove('hidden')"
            class="bg-red-600 hover:bg-red-500 text-white text-sm font-medium px-4 py-2 rounded-xl transition-colors">
            + Nuevo tenant
        </button>
    </div>

    <?php if ($msg): ?>
    <div class="mb-4 p-3 bg-green-900/40 border border-green-700 rounded-xl text-green-300 text-sm">
        <?= $msg ?>
    </div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="mb-4 p-3 bg-red-900/40 border border-red-700 rounded-xl text-red-300 text-sm">
        <?= htmlspecialchars($err) ?>
    </div>
    <?php endif; ?>

    <!-- Tabla de tenants -->
    <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-800 text-gray-400 text-xs uppercase tracking-wider">
                    <th class="text-left p-4">Nombre / Email</th>
                    <th class="text-left p-4">Plan</th>
                    <th class="text-left p-4">Estado</th>
                    <th class="text-left p-4">Usos hoy</th>
                    <th class="text-left p-4">Vence</th>
                    <th class="text-left p-4">Token</th>
                    <th class="text-left p-4">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                <?php foreach ($tenants as $t): ?>
                <tr class="hover:bg-gray-800/50 transition-colors">
                    <td class="p-4">
                        <div class="font-medium"><?= htmlspecialchars($t['name']) ?></div>
                        <div class="text-gray-500 text-xs"><?= htmlspecialchars($t['email']) ?></div>
                    </td>
                    <td class="p-4 text-gray-300"><?= htmlspecialchars($t['plan_name']) ?></td>
                    <td class="p-4">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $statusBadge[$t['status']] ?? '' ?>">
                            <?= $t['status'] ?>
                        </span>
                    </td>
                    <td class="p-4 text-gray-300"><?= number_format($t['today_uses']) ?></td>
                    <td class="p-4 text-gray-400 text-xs">
                        <?= $t['expires_at'] ? date('d/m/Y', strtotime($t['expires_at'])) : '—' ?>
                    </td>
                    <td class="p-4">
                        <code class="font-mono text-xs text-gray-500" title="<?= htmlspecialchars($t['api_token']) ?>">
                            <?= substr($t['api_token'], 0, 12) ?>...
                        </code>
                    </td>
                    <td class="p-4">
                        <div class="flex gap-2">
                            <?php if ($t['status'] === 'suspended'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button class="text-xs text-green-400 hover:text-green-300">Activar</button>
                            </form>
                            <?php elseif ($t['status'] !== 'suspended'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="suspend">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button class="text-xs text-red-400 hover:text-red-300">Suspender</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('¿Regenerar token? El anterior dejará de funcionar.')">
                                <input type="hidden" name="action" value="regen_token">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button class="text-xs text-yellow-400 hover:text-yellow-300">Regen token</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modal crear tenant -->
<div id="modalCreate" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center z-50">
    <div class="bg-gray-900 border border-gray-700 rounded-2xl p-6 w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-semibold">Nuevo Tenant</h2>
            <button onclick="document.getElementById('modalCreate').classList.add('hidden')"
                class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Nombre empresa / cliente</label>
                <input type="text" name="name" required
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Email</label>
                <input type="email" name="email" required
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Plan</label>
                <select name="plan_id"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500">
                    <?php foreach ($plans as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> — S/ <?= $p['price_soles'] ?>/mes</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Estado</label>
                    <select name="status"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="trial">Trial</option>
                        <option value="active">Activo</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Vence el (opcional)</label>
                    <input type="date" name="expires_at"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                    class="flex-1 bg-red-600 hover:bg-red-500 text-white font-medium py-2 rounded-xl text-sm transition-colors">
                    Crear tenant
                </button>
                <button type="button"
                    onclick="document.getElementById('modalCreate').classList.add('hidden')"
                    class="flex-1 bg-gray-800 hover:bg-gray-700 text-gray-300 font-medium py-2 rounded-xl text-sm transition-colors">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
