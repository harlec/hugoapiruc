<?php
namespace App;

use PDO;
use PDOException;

class DB
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST, DB_PORT, DB_NAME
            );
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                if (php_sapi_name() === 'cli') {
                    echo "ERROR de conexión a MySQL: " . $e->getMessage() . "\n";
                    echo "DSN: mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . "\n";
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Error de base de datos', 'code' => 'DB_ERROR']);
                }
                exit;
            }
        }
        return self::$instance;
    }
}
