<?php
require_once __DIR__ . '/../../assets/inc/init.php';
requireAdmin();

$page_title = 'Admin – Automation';
$page_css   = '../../assets/css/admin/automation.css';
$page_js    = '../../assets/js/admin/automation.js'; // optional

include __DIR__ . '/../../assets/inc/header.php';

/* ---------------------------
   Config: list of external sources
   (match names you use in shipment_scrapes.source_site)
---------------------------- */
$SITES = [
  'CMA CGM',
  'MSC',
  'Maersk',
  'Evergreen',
  'ONE',
  'Port of Beirut',
  'TrackTrace',
  // add more as needed…
];

$flash_success = '';
$flash_error   = '';

/* ---------------------------
   Handle POST actions
   - action = run_all | run_site | reconcile
   - site   = one of $SITES (when action=run_site)
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If you enabled CSRF in init.php, uncomment:
    // if (!csrf_check($_POST['csrf'] ?? '')) { $flash_error = 'Invalid request.'; }

    $action = trim($_POST['action'] ?? '');
    $site   = trim($_POST['site']   ?? '');

    if ($action === 'run_site') {
        if ($site === '' || !in_array($site, $SITES, true)) {
            $flash_error = 'Invalid site.';
        } else {
            try {
                // queue intent in logs for your runner (Power Automate / Task Scheduler)
                $stmt = $pdo->prepare('INSERT INTO logs (action_type, actor_id, details, timestamp) VALUES (?,?,?,NOW())');
                $stmt->execute([
                    'manual_trigger_site',
                    (int)($_SESSION['admin_id'] ?? 0),
                    json_encode(['site' => $site], JSON_UNESCAPED_UNICODE),
                ]);
                $flash_success = "Queued: scrape for {$site}.";
            } catch (Throwable $e) { $flash_error = 'Could not queue site run.'; }
        }
    } elseif ($action === 'run_all') {
        try {
            $stmt = $pdo->prepare('INSERT INTO logs (action_type, actor_id, details, timestamp) VALUES (?,?,?,NOW())');
            $stmt->execute([
                'manual_trigger_all',
                (int)($_SESSION['admin_id'] ?? 0),
                json_encode(['sites' => $SITES], JSON_UNESCAPED_UNICODE),
            ]);
            $flash_success = 'Queued: scrape for ALL sites.';
        } catch (Throwable $e) { $flash_error = 'Could not queue run-all.'; }
    } elseif ($action === 'reconcile') {
        try {
            $stmt = $pdo->prepare('INSERT INTO logs (action_type, actor_id, details, timestamp) VALUES (?,?,?,NOW())');
            $stmt->execute([
                'manual_reconcile',
                (int)($_SESSION['admin_id'] ?? 0),
                json_encode(['note' => 'reconcile statuses'], JSON_UNESCAPED_UNICODE),
            ]);
            $flash_success = 'Queued: reconcile statuses.';
        } catch (Throwable $e) { $flash_error = 'Could not queue reconcile.'; }
    } else {
        $flash_error = 'Unknown action.';
    }
}

/* ---------------------------
   Telemetry
---------------------------- */
$lastOverall     = null;
$bySource        = [];  // [ ['source_site'=>..., 'last_time'=>..., 'updates'=>int], ... ]
$recentLogs      = [];  // last 20 automation-related logs

try {
  $row = $pdo->query('SELECT MAX(scrape_time) AS last_time FROM shipment_scrapes')->fetch();
  $lastOverall = $row && !empty($row['last_time']) ? $row['last_time'] : null;
} catch (Throwable $e) { $lastOverall = null; }

try {
  $stmt = $pdo->query("
    SELECT source_site,
           MAX(scrape_time) AS last_time,
           COUNT(*)         AS updates
    FROM shipment_scrapes
    GROUP BY source_site
    ORDER BY last_time IS NULL, last_time DESC
  ");
  $bySource = $stmt ? $stmt->fetchAll() : [];
} catch (Throwable $e) { $bySource = []; }

try {
  $stmt = $pdo->query("
    SELECT log_id, action_type, details, timestamp
    FROM logs
    WHERE action_type IN (
      'manual_trigger_site','manual_trigger_all','manual_reconcile',
      'scrape_started','scrape_finished','reconcile_started','reconcile_finished','error'
    )
    ORDER BY log_id DESC
    LIMIT 20
  ");
  $recentLogs = $stmt ? $stmt->fetchAll() : [];
} catch (Throwable $e) { $recentLogs = []; }
?>
<main class="container">
  <h1>Automation</h1>

  <?php if ($flash_success): ?>
    <div class="alert success"><?= htmlspecialchars($flash_success) ?></div>
  <?php endif; ?>
  <?php if ($flash_error): ?>
    <div class="alert error"><?= htmlspecialchars($flash_error) ?></div>
  <?php endif; ?>

  <section class="controls">
    <form method="post" action="automation.php" style="display:inline-block;margin-right:8px;">
      <!-- <input type="hidden" name="csrf" value="<?= csrf_issue() ?>"> -->
      <input type="hidden" name="action" value="run_all">
      <button type="submit">Run All Scrapers</button>
    </form>

    <form method="post" action="automation.php" style="display:inline-block;margin-right:8px;">
      <!-- <input type="hidden" name="csrf" value="<?= csrf_issue() ?>"> -->
      <input type="hidden" name="action" value="reconcile">
      <button type="submit">Reconcile Statuses</button>
    </form>
  </section>

  <section class="per-site">
    <h2>Run a Specific Site</h2>
    <div class="site-grid">
      <?php foreach ($SITES as $s): ?>
        <form method="post" action="automation.php" class="site-card">
          <!-- <input type="hidden" name="csrf" value="<?= csrf_issue() ?>"> -->
          <input type="hidden" name="action" value="run_site">
          <input type="hidden" name="site" value="<?= htmlspecialchars($s) ?>">
          <div class="site-name"><?= htmlspecialchars($s) ?></div>
          <button type="submit">Run Now</button>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="status">
    <h2>Last Update</h2>
    <p>Overall last scrape: <strong><?= $lastOverall ? htmlspecialchars($lastOverall) : '—' ?></strong></p>

    <table class="table">
      <thead>
        <tr>
          <th>Source</th>
          <th>Last Time</th>
          <th>Total Updates</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($bySource): ?>
          <?php foreach ($bySource as $r): ?>
            <tr>
              <td><?= htmlspecialchars((string)$r['source_site']) ?></td>
              <td><?= htmlspecialchars((string)($r['last_time'] ?? '—')) ?></td>
              <td><?= (int)($r['updates'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="3">No scrape data yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="logs">
    <h2>Recent Automation Logs</h2>
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Action</th>
          <th>Details</th>
          <th>When</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recentLogs): ?>
          <?php foreach ($recentLogs as $log): ?>
            <tr>
              <td><?= (int)$log['log_id'] ?></td>
              <td><?= htmlspecialchars((string)$log['action_type']) ?></td>
              <td>
                <code style="font-size:12px;">
                  <?= htmlspecialchars((string)$log['details']) ?>
                </code>
              </td>
              <td><?= htmlspecialchars((string)$log['timestamp']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4">No recent automation logs.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>
</main>

<?php include __DIR__ . '/../../assets/inc/footer.php'; ?>
