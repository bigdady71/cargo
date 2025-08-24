<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/* If your app runs at http://localhost/cargo, set '/cargo'.
   If it runs at the web root, set ''. */
define('APP_BASE', '/cargo');

/* DB: root / empty password */
$dbHost = 'localhost';
$dbName = 'salameh_cargo';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed.');
}

/* Auth helpers */
function requireAdmin() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . APP_BASE . '../../php/admin/login.php');
        exit;
    }
}

/* CSRF (optional) */
function csrf_issue(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_check($t){ return isset($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t ?? ''); }
