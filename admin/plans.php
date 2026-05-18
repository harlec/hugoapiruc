<?php
// admin/plans.php — Gestión de planes
require_once __DIR__ . '/_layout.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $db->prepare("
            INSERT INTO plans (name, price_soles, queries_per_day, queries_per_mo, cache_ttl_hours, features)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            trim($_POST['name']),
            (float)$_POST['price'],
            (int)$_POST['qday'],
            (int)$_POST['qmo'],
            (int)$_POST['ttl'],
            json_encode([
                'ruc'  => isset($_POST['ruc']),
                'dni'  => isset($_POST['dni']),
                'bulk' => isset($_POST['bulk']),
            ]),
        ]);
        $msg = 'Plan creado correctamente.';
    }

    if ($action === 'toggle' && isset($_POST['id'])) {
        $db->prepare("UPDATE plans SET is_active = NOT is_active WHERE id = ?")
           ->execute([(int)$_POST['id']]);
        $msg = 'Estado del plan actualizado.';
    }
}

$plans = $db->query("
    SELECT p.*, COUNT(t.id) AS tenant_count
    FROM plans p LEFT JOIN tenants t ON t.plan_id = p.id
    GROUP BY p.id ORDER BY p.price_soles
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planes — PERÚdata Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-white flex">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="flex-1 ml-64 p-8">

    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold">Planes de Suscripción</h1>
        <button onclick="document.getElementById('modalPlan').classList.remove('hidden')"
            class="bg-red-600 hover:bg-red-500 text-white text-sm font-medium px-4 py-2 rounded-xl transition-colors">
            + Nuevo plan
        </button>
    </div>

    <?php if ($msg): ?>
    <div class="mb-4 p-3 bg-green-900/40 border border-green-700 rounded-xl text-green-300 text-sm"><?= $msg ?></div>
    <?php endif; ?>

    <!-- Cards de planes -->
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-5">
        <?php foreach ($plans as $plan): ?>
        <?php $features = json_decode($plan['features'], true) ?? []; ?>
        <div class="bg-gray-900 border <?= $plan['is_active'] ? 'border-gray-800' : 'border-red-900 opacity-60' ?> rounded-2xl p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="font-bold text-lg"><?= htmlspecialchars($plan['name']) ?></h3>
                    <div class="text-2xl font-bold text-red-400 mt-1">
                        S/ <?= number_format($plan['price_soles'], 2) ?>
                        <span class="text-sm text-gray-500 font-normal">/mes</span>
                    </div>
                </div>
                <?php if (!$plan['is_active']): ?>
                <span class="text-xs bg-red-900/50 text-red-400 px-2 py-0.5 rounded-full border border-red-700">inactivo</span>
                <?php endif; ?>
            </div>

            <div class="space-y-1.5 text-sm mb-4">
                <div class="flex justify-between">
                    <span class="text-gray-400">Consultas/día</span>
                    <span class="font-medium"><?= number_format($plan['queries_per_day']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Consultas/mes</span>
                    <span class="font-medium"><?= number_format($plan['queries_per_mo']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Caché TTL</span>
                    <span class="font-medium"><?= $plan['cache_ttl_hours'] ?>h</span>
                </div>
            </div>

            <!-- Features -->
            <div class="space-y-1 text-xs mb-4">
                <div class="<?= ($features['ruc'] ?? false) ? 'text-green-400' : 'text-gray-600 line-through' ?>">
                    <?= ($features['ruc'] ?? false) ? '✓' : '✗' ?> RUC SUNAT
                </div>
                <div class="<?= ($features['dni'] ?? false) ? 'text-green-400' : 'text-gray-600' ?>">
                    <?= ($features['dni'] ?? false) ? '✓' : '–' ?> DNI RENIEC
                </div>
                <div class="<?= ($features['bulk'] ?? false) ? 'text-green-400' : 'text-gray-600' ?>">
                    <?= ($features['bulk'] ?? false) ? '✓' : '–' ?> Consultas masivas
                </div>
            </div>

            <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500"><?= $plan['tenant_count'] ?> tenants</span>
                <form method="POST">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                    <button class="text-gray-500 hover:text-white transition-colors">
                        <?= $plan['is_active'] ? 'Desactivar' : 'Activar' ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- Modal nuevo plan -->
<div id="modalPlan" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center z-50">
    <div class="bg-gray-900 border border-gray-700 rounded-2xl p-6 w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-semibold">Nuevo Plan</h2>
            <button onclick="document.getElementById('modalPlan').classList.add('hidden')"
                class="text-gray-500 hover:text-white text-xl">&times;</button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Nombre</label>
                    <input type="text" name="name" required
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Precio (S/)</label>
                    <input type="number" name="price" step="0.01" min="0" required
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Consultas/día</label>
                    <input type="number" name="qday" min="1" required
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Consultas/mes</label>
                    <input type="number" name="qmo" min="1" required
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Caché TTL (h)</label>
                    <input type="number" name="ttl" min="1" value="24" required
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-2">Características</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="ruc" checked class="accent-red-500"> RUC
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="dni" class="accent-red-500"> DNI
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="bulk" class="accent-red-500"> Bulk
                    </label>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                    class="flex-1 bg-red-600 hover:bg-red-500 text-white font-medium py-2 rounded-xl text-sm transition-colors">
                    Crear plan
                </button>
                <button type="button" onclick="document.getElementById('modalPlan').classList.add('hidden')"
                    class="flex-1 bg-gray-800 hover:bg-gray-700 text-gray-300 font-medium py-2 rounded-xl text-sm transition-colors">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
