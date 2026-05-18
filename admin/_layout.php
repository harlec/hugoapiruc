<?php
// admin/_layout.php — Layout compartido para el panel admin
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/config.php';

session_start();

// Verificar autenticación admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

use App\DB;
$db = DB::connection();
