<?php
// ---------- TOP OF FILE ----------
session_start();

/* DB connection */
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
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

/* Auth: require admin */
function requireAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}
requireAdmin(); // enforce admin login
// ---------- END HEADER BLOCK ----------
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Page</title>
  <link rel="stylesheet" href="../../assets/css/admin/automation.css"><!-- change per page -->
</head>
<body>
  <!-- Admin content -->
</body>
</html>
