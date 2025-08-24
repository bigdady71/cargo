<?php
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

/* Auth: require logged-in customer */
function requireUser() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}
requireUser(); // enforce login for dashboard
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/public/dashboard.css"><!-- change per page -->
</head>
<body>
  <!-- Dashboard content -->
</body>
</html>
