
<?php
// ---------- TOP ----------
require_once dirname(__DIR__, 2) . '/assets/inc/init.php';
requireAdmin(); // admin-only

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // if ( !csrf_check($_POST['csrf'] ?? '') ) { $error = 'Invalid request.'; }

    $full_name     = trim($_POST['full_name']     ?? '');
    $email         = trim($_POST['email']         ?? '');
    $phone         = trim($_POST['phone']         ?? '');
    $shipping_code = trim($_POST['shipping_code'] ?? '');
    $address       = trim($_POST['address']       ?? '');
    $country       = trim($_POST['country']       ?? '');
    $id_number     = trim($_POST['id_number']     ?? '');

    if ($full_name === '' || $phone === '') {
        $error = 'Full name and phone are required.';
    } else {
        try {
            $stmt = $pdo->prepare('
                INSERT INTO users
                    (full_name, email, phone, shipping_code, address, country, id_number)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $full_name,
                $email !== '' ? $email : null,
                $phone,
                $shipping_code !== '' ? $shipping_code : null,
                $address !== '' ? $address : null,
                $country !== '' ? $country : null,
                $id_number !== '' ? $id_number : null,
            ]);
            $success = 'User created successfully.';
        } catch (PDOException $e) {
            $error = ($e->getCode() === '23000')
                ? 'Phone or shipping code already exists.'
                : 'Database error: ' . $e->getMessage();
        }
    }
}

// supply page vars for header
$page_title = 'Add User';
$page_css   = '../../assets/css/admin/add_user.css';

include dirname(__DIR__, 2) . '/assets/inc/header.php';
?>
<main class="container">
  <h1>Add User</h1>

  <?php if ($success): ?>
    <div class="alert success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" action="add_user.php" autocomplete="off" class="form-grid">
    <!-- <input type="hidden" name="csrf" value="<?= csrf_issue() ?>"> -->

    <label>Full Name
      <input type="text" name="full_name" required>
    </label>

    <label>Email
      <input type="email" name="email" placeholder="optional">
    </label>

    <label>Phone
      <input type="text" name="phone" required>
    </label>

    <label>Shipping Code
      <input type="text" name="shipping_code" placeholder="optional">
    </label>

    <label class="wide">Address
      <input type="text" name="address" placeholder="optional">
    </label>

    <label>Country
      <input type="text" name="country" placeholder="optional">
    </label>

    <label>ID Number
      <input type="text" name="id_number" placeholder="optional">
    </label>

    <div class="actions">
      <button type="submit">Create User</button>
      <a class="btn-secondary" href="dashboard.php">Cancel</a>
    </div>
  </form>
</main>
<?php include dirname(__DIR__, 2) . '/assets/inc/footer.php'; ?>
