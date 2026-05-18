<?php
/**
 * setup_admin.php — Ejecutar UNA VEZ desde CLI para configurar la contraseña admin.
 * Uso: php setup_admin.php
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

echo "=== PERÚdata API — Configuración inicial del admin ===\n\n";

echo "Nuevo usuario admin: ";
$username = trim(fgets(STDIN));

echo "Nuevo email: ";
$email = trim(fgets(STDIN));

echo "Nueva contraseña: ";
$password = trim(fgets(STDIN));

if (strlen($password) < 8) {
    echo "La contraseña debe tener al menos 8 caracteres.\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$db = App\DB::connection();

// Eliminar admins existentes y crear el nuevo
$db->exec("DELETE FROM admins");
$stmt = $db->prepare("INSERT INTO admins (username, email, password) VALUES (?, ?, ?)");
$stmt->execute([$username, $email, $hash]);

echo "\n✅ Admin creado correctamente.\n";
echo "Usuario: $username\n";
echo "Email:   $email\n";
echo "\nAccede al panel en: " . APP_URL . "/admin/login.php\n";
echo "\nELIMINA ESTE ARCHIVO DEL SERVIDOR después de usarlo.\n";
