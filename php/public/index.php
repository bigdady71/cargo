<?php
// ---------- TOP OF FILE (no output before this) ----------
session_start();

/* DB connection (duplicate on every page by design) */
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

/* Optional helper (kept here for convenience if you later flip a page to protected) */
function requireUser() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}
// ---------- END HEADER BLOCK ----------
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Public Page</title>
  <link rel="stylesheet" href="../../assets/css/public/index.css"><!-- change per page -->
</head>
<body>
  <!-- Your public content -->
</body>
</html>
