<?php
// admin/login.php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/config.php';
session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: /admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = \App\DB::connection()->prepare(
            "SELECT id, password, email FROM admins WHERE username = ? LIMIT 1"
        );
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']    = $admin['id'];
            $_SESSION['admin_user']  = $username;
            $_SESSION['admin_email'] = $admin['email'];
            header('Location: /admin/index.php');
            exit;
        }
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PERÚdata — Login Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 min-h-screen flex items-center justify-center">
<div class="w-full max-w-md px-6">
    <div class="text-center mb-8">
        <div class="inline-flex items-center gap-2 mb-2">
            <span class="text-3xl">🇵🇪</span>
            <h1 class="text-2xl font-bold text-white">PERÚdata API</h1>
        </div>
        <p class="text-gray-400 text-sm">Panel de Administración</p>
    </div>

    <div class="bg-gray-900 rounded-2xl border border-gray-800 p-8 shadow-2xl">
        <?php if ($error): ?>
        <div class="mb-4 p-3 bg-red-900/50 border border-red-700 rounded-lg text-red-300 text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Usuario</label>
                <input type="text" name="username" required autocomplete="username"
                    class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent placeholder-gray-500"
                    placeholder="admin">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Contraseña</label>
                <input type="password" name="password" required autocomplete="current-password"
                    class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent placeholder-gray-500"
                    placeholder="••••••••">
            </div>
            <button type="submit"
                class="w-full bg-red-600 hover:bg-red-500 text-white font-semibold py-2.5 rounded-lg transition-colors">
                Ingresar
            </button>
        </form>
    </div>

    <p class="text-center text-gray-600 text-xs mt-6">AUNOR IT · PERÚdata API v1.0</p>
</div>
</body>
</html>
