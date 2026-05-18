<?php
// admin/_sidebar.php — Sidebar compartido del panel admin
$nav = [
    ['href' => '/admin/index.php',     'icon' => '📊', 'label' => 'Dashboard'],
    ['href' => '/admin/tenants.php',   'icon' => '👥', 'label' => 'Tenants'],
    ['href' => '/admin/api-keys.php',  'icon' => '🔑', 'label' => 'API Keys'],
    ['href' => '/admin/monitor.php',   'icon' => '🔌', 'label' => 'Monitor'],
    ['href' => '/admin/analytics.php', 'icon' => '📈', 'label' => 'Analytics'],
    ['href' => '/admin/plans.php',     'icon' => '💳', 'label' => 'Planes'],
    ['href' => '/admin/logs.php',      'icon' => '📋', 'label' => 'Logs'],
];
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="fixed left-0 top-0 h-full w-64 bg-gray-900 border-r border-gray-800 flex flex-col z-10">
    <!-- Logo -->
    <div class="p-6 border-b border-gray-800">
        <div class="flex items-center gap-2">
            <span class="text-2xl">🇵🇪</span>
            <div>
                <div class="font-bold text-white text-sm">PERÚdata API</div>
                <div class="text-gray-500 text-xs">Panel Admin</div>
            </div>
        </div>
    </div>

    <!-- Navegación -->
    <nav class="flex-1 p-4">
        <ul class="space-y-1">
            <?php foreach ($nav as $item): ?>
            <?php $active = basename($item['href']) === $currentPage; ?>
            <li>
                <a href="<?= $item['href'] ?>"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                    <?= $active
                        ? 'bg-red-600/20 text-red-400 border border-red-600/30'
                        : 'text-gray-400 hover:bg-gray-800 hover:text-white' ?>">
                    <span><?= $item['icon'] ?></span>
                    <span><?= $item['label'] ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Footer -->
    <div class="p-4 border-t border-gray-800 space-y-1">
        <a href="/playground.php" target="_blank"
            class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-gray-500 hover:bg-gray-800 hover:text-white transition">
            <span>▶</span> Probar API
        </a>
        <a href="/docs.php" target="_blank"
            class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-gray-500 hover:bg-gray-800 hover:text-white transition">
            <span>📚</span> Documentación
        </a>
        <div class="pt-2 border-t border-gray-800">
            <div class="text-xs text-gray-600 mb-1 px-3"><?= $_SESSION['admin_user'] ?></div>
            <a href="/admin/logout.php"
                class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-gray-500 hover:text-red-400 hover:bg-red-950 transition">
                <span>⏏</span> Cerrar sesión
            </a>
        </div>
    </div>
</aside>
