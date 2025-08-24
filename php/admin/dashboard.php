<?php
// ---------- TOP OF FILE ----------
session_start();

/* DB connection (page-local) */
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
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

/* Admin auth (page-local) */
function requireAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}
requireAdmin();

// Example: load a quick metric to prove DB works
$totalShipments = 0;
try {
    $row = $pdo->query('SELECT COUNT(*) AS c FROM shipments')->fetch();
    $totalShipments = (int)($row['c'] ?? 0);
} catch (Throwable $th) {
    // keep lightweight for now
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/admin/dashboard.css">

  

  <?php include __DIR__ . '/../../assets/inc/footer.php'; ?>
