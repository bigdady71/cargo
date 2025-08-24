<?php
require_once __DIR__ . '/../../assets/inc/init.php';
requireAdmin();

$page_title = 'Admin – Manage Shipments';
$page_css   = '../../assets/css/admin/manage_shipments.css';
$page_js    = '../../assets/js/admin/manage_shipments.js'; // optional
include __DIR__ . '/../../assets/inc/header.php';

/* ---------------------------
   Config
---------------------------- */
$ALLOWED_STATUSES = [
  'En Route', 'In Transit', 'Arrived', 'Delivered',
  'Customs', 'Picked Up', 'Delayed', 'Cancelled'
];

$flash_success = '';
$flash_error   = '';

/* ---------------------------
   Handle inline status update (POST)
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    // if (!csrf_check($_POST['csrf'] ?? '')) { $flash_error = 'Invalid request.'; }
    $shipment_id = (int)($_POST['shipment_id'] ?? 0);
    $new_status  = trim((string)($_POST['new_status'] ?? ''));

    if ($shipment_id <= 0 || $new_status === '' || !in_array($new_status, $ALLOWED_STATUSES, true)) {
        $flash_error = 'Invalid status update.';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE shipments SET status = ?, updated_at = NOW() WHERE shipment_id = ?');
            $stmt->execute([$new_status, $shipment_id]);

            // log
            try {
              $log = $pdo->prepare('INSERT INTO logs (action_type, actor_id, related_shipment_id, details, timestamp)
                                    VALUES (?,?,?,?,NOW())');
              $log->execute([
                'status_updated',
                (int)($_SESSION['admin_id'] ?? 0),
                $shipment_id,
                json_encode(['new_status' => $new_status], JSON_UNESCAPED_UNICODE),
              ]);
            } catch (Throwable $e) {}

            $flash_success = "Shipment #{$shipment_id} updated to '{$new_status}'.";
        } catch (Throwable $e) {
            $flash_error = 'Could not update status.';
        }
    }
}

/* ---------------------------
   Filters (GET)
   q: searches tracking/container/BL
   status: exact match from allowed list
   page: pagination (20/page)
---------------------------- */
$q       = trim((string)($_GET['q'] ?? ''));
$fStatus = trim((string)($_GET['status'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($q !== '') {
  $where[] = '(tracking_number LIKE ? OR container_number LIKE ? OR bl_number LIKE ?)';
  $like = '%' . $q . '%';
  $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($fStatus !== '' && in_array($fStatus, $ALLOWED_STATUSES, true)) {
  $where[] = 'status = ?';
  $params[] = $fStatus;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* Count for pagination */
$total = 0;
try {
  $st = $pdo->prepare("SELECT COUNT(*) AS c FROM shipments {$whereSql}");
  $st->execute($params);
  $total = (int)($st->fetch()['c'] ?? 0);
} catch (Throwable $e) { $total = 0; }

$pages = max(1, (int)ceil($total / $perPage));

/* Fetch page */
$rows = [];
try {
  $sql = "SELECT shipment_id, tracking_number, container_number, bl_number, status, origin, destination, updated_at
          FROM shipments
          {$whereSql}
          ORDER BY shipment_id DESC
          LIMIT {$perPage} OFFSET {$offset}";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();
} catch (Throwable $e) {
  $rows = [];
}

/* Utility: build query string preserving filters */
function linkWith($page, $q, $fStatus) {
  $qs = [];
  if ($q !== '')        $qs['q'] = $q;
  if ($fStatus !== '')  $qs['status'] = $fStatus;
  $qs['page'] = $page;
  return 'manage_shipments.php?' . http_build_query($qs);
}
?>
<main class="container">
  <h1>Manage Shipments</h1>

  <?php if ($flash_success): ?>
    <div class="alert success"><?= htmlspecialchars($flash_success) ?></div>
  <?php endif; ?>
  <?php if ($flash_error): ?>
    <div class="alert error"><?= htmlspecialchars($flash_error) ?></div>
  <?php endif; ?>

  <form method="get" action="manage_shipments.php" class="filters" autocomplete="off">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search tracking / container / BL">
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach ($ALLOWED_STATUSES as $opt): ?>
        <option value="<?= htmlspecialchars($opt) ?>" <?= $opt===$fStatus?'selected':'' ?>>
          <?= htmlspecialchars($opt) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
    <?php if ($q !== '' || $fStatus !== ''): ?>
      <a class="btn-secondary" href="manage_shipments.php">Reset</a>
    <?php endif; ?>
  </form>

  <div class="results-meta">
    <span>Total: <?= (int)$total ?></span>
    <?php if ($total > 0): ?>
      <span> • Page <?= (int)$page ?> / <?= (int)$pages ?></span>
    <?php endif; ?>
  </div>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Tracking #</th>
          <th>Container</th>
          <th>BL</th>
          <th>Status (inline)</th>
          <th>Origin</th>
          <th>Destination</th>
          <th>Updated</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9">No shipments found.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['shipment_id'] ?></td>
              <td><?= htmlspecialchars((string)$r['tracking_number']) ?></td>
              <td><?= htmlspecialchars((string)$r['container_number']) ?></td>
              <td><?= htmlspecialchars((string)$r['bl_number']) ?></td>
              <td>
                <form method="post" action="manage_shipments.php<?php
                  // preserve filters/page on submit by appending current query string
                  $qs = $_GET; if (!empty($qs)) echo '?' . http_build_query($qs);
                ?>" class="inline-form">
                  <!-- <input type="hidden" name="csrf" value="<?= csrf_issue() ?>"> -->
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="shipment_id" value="<?= (int)$r['shipment_id'] ?>">
                  <select name="new_status">
                    <?php foreach ($ALLOWED_STATUSES as $opt): ?>
                      <option value="<?= htmlspecialchars($opt) ?>" <?= $opt===(string)$r['status']?'selected':'' ?>>
                        <?= htmlspecialchars($opt) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit">Save</button>
                </form>
              </td>
              <td><?= htmlspecialchars((string)$r['origin']) ?></td>
              <td><?= htmlspecialchars((string)$r['destination']) ?></td>
              <td><?= htmlspecialchars((string)$r['updated_at']) ?></td>
              <td>
                <!-- Placeholder for future actions: view/edit detail -->
                <a class="btn-link" href="#">View</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="pagination">
      <?php if ($page > 1): ?>
        <a href="<?= linkWith($page-1, $q, $fStatus) ?>">&laquo; Prev</a>
      <?php else: ?>
        <span class="disabled">&laquo; Prev</span>
      <?php endif; ?>

      <span>Page <?= (int)$page ?> of <?= (int)$pages ?></span>

      <?php if ($page < $pages): ?>
        <a href="<?= linkWith($page+1, $q, $fStatus) ?>">Next &raquo;</a>
      <?php else: ?>
        <span class="disabled">Next &raquo;</span>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/../../assets/inc/footer.php'; ?>
