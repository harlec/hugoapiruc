<?php
/**
 * setup.php — Configuración inicial vía navegador.
 * ⚠ ELIMINAR ESTE ARCHIVO después de crear el admin.
 * Acceso: https://apisunat.harlec.com.pe/setup.php?key=SETUP_SECRET
 */

// Clave de acceso — cámbiala antes de subir
define('SETUP_KEY', 'PeruData2026!');

// Validar clave en URL
if (($_GET['key'] ?? '') !== SETUP_KEY) {
    http_response_code(403);
    die('<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:80px">
        <h2>403 — Acceso denegado</h2>
        <p>Usa: <code>?key=SETUP_SECRET</code></p>
    </body></html>');
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (!$username || !$email || !$password) {
        $error = 'Todos los campos son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no válido.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $db   = App\DB::connection();
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $db->exec("DELETE FROM admins");
            $db->prepare("INSERT INTO admins (username, email, password) VALUES (?, ?, ?)")
               ->execute([$username, $email, $hash]);

            $success = "Admin <strong>$username</strong> creado correctamente.<br>
                        Ahora accede al panel: <a href='/admin/login.php'>/admin/login.php</a><br><br>
                        <strong style='color:#dc2626'>⚠ Elimina este archivo del servidor: <code>public/setup.php</code></strong>";
        } catch (\PDOException $e) {
            $error = 'Error de base de datos: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PERÚdata API — Configuración inicial</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-md p-8">

        <div class="text-center mb-6">
            <div class="text-4xl mb-2">🇵🇪</div>
            <h1 class="text-2xl font-bold text-gray-800">PERÚdata API</h1>
            <p class="text-gray-500 text-sm mt-1">Configuración inicial del administrador</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-300 text-green-800 rounded-lg p-4 text-sm mb-4">
                ✅ <?= $success ?>
            </div>
        <?php else: ?>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 text-sm mb-4">
                    ⚠ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="?key=<?= htmlspecialchars(SETUP_KEY) ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
                    <input type="text" name="username" required
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="admin">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="admin@tudominio.com">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                    <input type="password" name="password" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Mínimo 8 caracteres">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña</label>
                    <input type="password" name="confirm" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Repite la contraseña">
                </div>

                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition text-sm">
                    Crear administrador
                </button>
            </form>

            <p class="text-xs text-gray-400 text-center mt-4">
                ⚠ Elimina este archivo del servidor después de usarlo.
            </p>

        <?php endif; ?>
    </div>
</body>
</html>
