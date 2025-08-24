<?php
require_once __DIR__ . '/../../assets/inc/init.php';
requireAdmin();

// Enable PhpSpreadsheet for XLSX
require_once __DIR__ . '/../../assets/inc/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$page_title = 'Admin – Upload Shipments';
$page_css   = '../../assets/css/admin/upload_shipments.css';
$page_js    = '../../assets/js/admin/upload_shipments.js'; // optional
include __DIR__ . '/../../assets/inc/header.php';

/* ---------------------------------------
   Config
---------------------------------------- */
$ALLOWED_STATUSES = [
  'En Route', 'In Transit', 'Arrived', 'Delivered',
  'Customs', 'Picked Up', 'Delayed', 'Cancelled'
];

$acceptedExts = ['csv','xlsx'];
$maxSizeMB    = 20;

$flash_success = '';
$flash_error   = '';
$summary = ['total_rows'=>0,'inserted'=>0,'skipped'=>0,'errors'=>[]];

/* ---------------------------------------
   Helpers
---------------------------------------- */
function numOrNull($v) {
  $v = trim((string)$v);
  if ($v === '') return null;
  // normalize "1,234.56" → 1234.56
  $v = str_replace([',',' '], ['',''], $v);
  return is_numeric($v) ? (float)$v : null;
}
function intOrNull($v) {
  $v = trim((string)$v);
  if ($v === '' || !is_numeric($v)) return null;
  return (int)$v;
}
function parseDateOrNull($v): ?string {
  $v = trim((string)$v);
  if ($v === '') return null;
  $ts = strtotime($v);
  return $ts ? date('Y-m-d', $ts) : null;
}
function normalizeStatus(?string $s, array $allowed): string {
  $s = trim((string)$s);
  return ($s !== '' && in_array($s, $allowed, true)) ? $s : 'En Route';
}
function userIdFromShippingCode(PDO $pdo, ?string $code): ?int {
  $code = trim((string)$code);
  if ($code === '') return null;
  $st = $pdo->prepare('SELECT user_id FROM users WHERE shipping_code=? LIMIT 1');
  $st->execute([$code]);
  $row = $st->fetch();
  return $row ? (int)$row['user_id'] : null;
}
// tracking number with file-name prefix
function generateTrackingNumber(PDO $pdo, string $prefix): string {
  $prefix = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $prefix));
  $prefix = substr($prefix, 0, 10);
  if ($prefix === '') $prefix = 'SC';
  for ($i=0; $i<7; $i++) {
    $candidate = sprintf('%s-%s-%s', $prefix, date('ymd'), strtoupper(bin2hex(random_bytes(3))));
    $st = $pdo->prepare('SELECT 1 FROM shipments WHERE tracking_number=? LIMIT 1');
    $st->execute([$candidate]);
    if (!$st->fetch()) return $candidate;
  }
  return sprintf('%s-%u', $prefix, time());
}

/* ---------------------------------------
   Import handler
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
  // if (!csrf_check($_POST['csrf'] ?? '')) { $flash_error = 'Invalid request.'; }

  $f = $_FILES['file'];
  if ($f['error'] !== UPLOAD_ERR_OK) {
    $flash_error = 'Upload failed.';
  } else {
    $sizeMB = $f['size'] / (1024*1024);
    if ($sizeMB > $maxSizeMB) {
      $flash_error = 'File too large.';
    } else {
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, $acceptedExts, true)) {
        $flash_error = 'Unsupported file type. Use CSV or XLSX.';
      } else {
        $tmp          = $f['tmp_name'];
        $originalName = pathinfo($f['name'], PATHINFO_FILENAME); // for tracking prefix
        $rows         = [];
        $hasHeader    = isset($_POST['has_header']);

        // Read rows → array of associative rows (lowercased headers)
        if ($ext === 'csv') {
          $fh = fopen($tmp, 'r');
          if ($fh === false) {
            $flash_error = 'Could not read CSV.';
          } else {
            $header = null;
            while (($cols = fgetcsv($fh, 0, ',')) !== false) {
              if (count($cols) === 1 && trim((string)$cols[0]) === '') continue;
              if ($header === null && $hasHeader) {
                $header = array_map(fn($h)=>strtolower(trim((string)$h)), $cols);
                continue;
              }
              if ($header !== null) {
                $assoc = [];
                foreach ($header as $i=>$h) { $assoc[$h] = $cols[$i] ?? ''; }
                $rows[] = $assoc;
              } else {
                // No header → assume fixed order (PHOTO first, ignored)
                $rows[] = [
                  'photo'        => $cols[0]  ?? '',
                  'item no'      => $cols[1]  ?? '',
                  'description'  => $cols[2]  ?? '',
                  'total ctns'   => $cols[3]  ?? '',
                  'qty/ctn'      => $cols[4]  ?? '',
                  'totalqty'     => $cols[5]  ?? '',
                  'unit price'   => $cols[6]  ?? '',
                  'total amount' => $cols[7]  ?? '',
                  'cbm'          => $cols[8]  ?? '',
                  'total cbm'    => $cols[9]  ?? '',
                  'gwkg'         => $cols[10] ?? '',
                  'total gw'     => $cols[11] ?? '',
                ];
              }
            }
            fclose($fh);
          }
        } else { // XLSX
          try {
            $spreadsheet = IOFactory::load($tmp);
            $sheet       = $spreadsheet->getActiveSheet();
            $data        = $sheet->toArray(null, true, true, true); // rows of cells

            if (!$data) { $flash_error = 'Empty XLSX sheet.'; }
            else {
              if ($hasHeader) {
                $headerRow = array_shift($data);
                $header    = [];
                foreach ($headerRow as $cell) {
                  $header[] = strtolower(trim((string)$cell));
                }
                foreach ($data as $r) {
                  $assoc = [];
                  $i=0;
                  foreach ($header as $h) {
                    $i++;
                    $assoc[$h] = $r[$i] ?? '';
                  }
                  $rows[] = $assoc;
                }
              } else {
                foreach ($data as $r) {
                  $rows[] = [
                    'photo'        => $r[1]  ?? '',
                    'item no'      => $r[2]  ?? '',
                    'description'  => $r[3]  ?? '',
                    'total ctns'   => $r[4]  ?? '',
                    'qty/ctn'      => $r[5]  ?? '',
                    'totalqty'     => $r[6]  ?? '',
                    'unit price'   => $r[7]  ?? '',
                    'total amount' => $r[8]  ?? '',
                    'cbm'          => $r[9]  ?? '',
                    'total cbm'    => $r[10] ?? '',
                    'gwkg'         => $r[11] ?? '',
                    'total gw'     => $r[12] ?? '',
                  ];
                }
              }
            }
          } catch (Throwable $e) {
            $flash_error = 'Could not parse XLSX.';
          }
        }

        if (!$flash_error && $rows) {
          $summary['total_rows'] = count($rows);

          // Normalize keys to canonical names (handle variants and casing)
          $canon = [
            'photo' => 'photo',
            'item no' => 'item_no',
            'itemno'  => 'item_no',
            'description' => 'description',
            'total ctns'  => 'total_ctns',
            'total cartons'=> 'total_ctns',
            'qty/ctn'     => 'qty_per_ctn',
            'qty per ctn' => 'qty_per_ctn',
            'totalqty'    => 'total_qty',
            'total qty'   => 'total_qty',
            'unit price'  => 'unit_price',
            'total amount'=> 'total_amount',
            'cbm'         => 'cbm',
            'total cbm'   => 'total_cbm',
            'gwkg'        => 'gwkg',
            'total gw'    => 'total_gw',
            // Optional passthroughs if present in a sheet (ignored if missing)
            'shipping_code'=> 'shipping_code',
            'user_id'      => 'user_id',
            'status'       => 'status',
            'origin'       => 'origin',
            'destination'  => 'destination',
            'pickup_date'  => 'pickup_date',
            'delivery_date'=> 'delivery_date',
            'tracking_number'=>'tracking_number'
          ];

          $pdo->beginTransaction();
          try {
            $ins = $pdo->prepare('
              INSERT INTO shipments (
                user_id, tracking_number, container_number, bl_number, shipping_code,
                product_description, cbm, cartons, weight, gross_weight, total_amount,
                status, origin, destination, pickup_date, delivery_date,
                item_no, qty_per_ctn, total_qty, unit_price, total_cbm, total_gw,
                created_at, updated_at
              ) VALUES (
                :user_id, :tracking_number, :container_number, :bl_number, :shipping_code,
                :product_description, :cbm, :cartons, :weight, :gross_weight, :total_amount,
                :status, :origin, :destination, :pickup_date, :delivery_date,
                :item_no, :qty_per_ctn, :total_qty, :unit_price, :total_cbm, :total_gw,
                NOW(), NOW()
              )
            ');

            foreach ($rows as $idx => $r) {
              // Lowercase keys and map to canonical
              $norm = [];
              foreach ($r as $k=>$v) {
                $lk = strtolower(trim((string)$k));
                if (isset($canon[$lk])) $norm[$canon[$lk]] = $v;
              }

              // Resolve user (optional)
              $userId = null;
              if (!empty($norm['user_id'])) {
                $userId = intOrNull($norm['user_id']);
              } elseif (!empty($norm['shipping_code'])) {
                $userId = userIdFromShippingCode($pdo, $norm['shipping_code']);
              }

              // Tracking number (use provided or generate with filename prefix)
              $tracking = trim((string)($norm['tracking_number'] ?? ''));
              if ($tracking === '') {
                $tracking = generateTrackingNumber($pdo, $originalName);
              }

              // Business fields
              $itemNo       = trim((string)($norm['item_no'] ?? ''));
              $desc         = trim((string)($norm['description'] ?? ''));
              $cartons      = intOrNull($norm['total_ctns'] ?? null);
              $qtyPerCtn    = intOrNull($norm['qty_per_ctn'] ?? null);
              $totalQty     = intOrNull($norm['total_qty'] ?? null);
              $unitPrice    = numOrNull($norm['unit_price'] ?? null);
              $totalAmount  = numOrNull($norm['total_amount'] ?? null);
              $cbm          = numOrNull($norm['cbm'] ?? null);
              $totalCbm     = numOrNull($norm['total_cbm'] ?? null);
              $gwkg         = numOrNull($norm['gwkg'] ?? null);
              $totalGw      = numOrNull($norm['total_gw'] ?? null);

              $status = normalizeStatus($norm['status'] ?? null, $ALLOWED_STATUSES);
              $origin = trim((string)($norm['origin'] ?? ''));
              $dest   = trim((string)($norm['destination'] ?? ''));
              $pickup = parseDateOrNull($norm['pickup_date'] ?? null);
              $deliv  = parseDateOrNull($norm['delivery_date'] ?? null);

              try {
                $ins->execute([
                  ':user_id'          => $userId,
                  ':tracking_number'  => $tracking,
                  ':container_number' => null,
                  ':bl_number'        => null,
                  ':shipping_code'    => $norm['shipping_code'] ?? null,

                  ':product_description'=> $desc,
                  ':cbm'               => $cbm ?? $totalCbm, // prefer specific cbm, fallback to total
                  ':cartons'           => $cartons,
                  ':weight'            => $gwkg,
                  ':gross_weight'      => $totalGw,
                  ':total_amount'      => $totalAmount,

                  ':status'            => $status,
                  ':origin'            => $origin,
                  ':destination'       => $dest,
                  ':pickup_date'       => $pickup,
                  ':delivery_date'     => $deliv,

                  ':item_no'           => $itemNo,
                  ':qty_per_ctn'       => $qtyPerCtn,
                  ':total_qty'         => $totalQty,
                  ':unit_price'        => $unitPrice,
                  ':total_cbm'         => $totalCbm,
                  ':total_gw'          => $totalGw,
                ]);

                $summary['inserted']++;

                // Log create
                try {
                  $log = $pdo->prepare('INSERT INTO logs (action_type, actor_id, related_shipment_id, details, timestamp)
                                        VALUES (?,?,?,?,NOW())');
                  $log->execute([
                    'shipments_import',
                    (int)($_SESSION['admin_id'] ?? 0) * -1, // negative = admin
                    (int)$pdo->lastInsertId(),
                    json_encode(['file'=>$originalName, 'tracking'=>$tracking], JSON_UNESCAPED_UNICODE),
                  ]);
                } catch (Throwable $e) {}
              } catch (Throwable $rowErr) {
                $summary['skipped']++;
                $summary['errors'][] = "Row ".($idx+1).": ".$rowErr->getMessage();
              }
            }

            $pdo->commit();
            $flash_success = "Upload complete: {$summary['inserted']} inserted, {$summary['skipped']} skipped.";
          } catch (Throwable $tx) {
            $pdo->rollBack();
            $flash_error = 'Upload failed. Transaction rolled back.';
          }
        } elseif (!$flash_error) {
          $flash_error = 'No rows found.';
        }
      }
    }
  }
}
?>
<main class="container">
  <h1>Upload Shipments</h1>

  <?php if ($flash_success): ?>
    <div class="alert success"><?= htmlspecialchars($flash_success) ?></div>
  <?php endif; ?>
  <?php if ($flash_error): ?>
    <div class="alert error"><?= htmlspecialchars($flash_error) ?></div>
  <?php endif; ?>

  <?php if ($summary['total_rows'] > 0): ?>
    <div class="summary">
      <p>Total rows: <?= (int)$summary['total_rows'] ?> • Inserted: <?= (int)$summary['inserted'] ?> • Skipped: <?= (int)$summary['skipped'] ?></p>
      <?php if ($summary['errors']): ?>
        <details><summary>Row errors (<?= count($summary['errors']) ?>)</summary>
          <ul class="errors"><?php foreach ($summary['errors'] as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </details>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="upload_shipments.php" enctype="multipart/form-data" class="upload-form">
    <!-- <input type="hidden" name="csrf" value="<?= csrf_issue() ?>"> -->
    <div class="field">
      <label>Choose file (CSV or XLSX)</label>
      <input type="file" name="file" accept=".csv,.xlsx" required>
    </div>
    <div class="field checkbox">
      <label><input type="checkbox" name="has_header" checked> First row is header</label>
    </div>
    <button type="submit">Upload</button>
  </form>

  <section class="help">
    <h2>Expected Columns</h2>
    <code style="display:block;white-space:pre-wrap">
PHOTO, ITEM NO, DESCRIPTION, TOTAL CTNS, QTY/CTN, TOTALQTY, UNIT PRICE, TOTAL AMOUNT, CBM, TOTAL CBM, GWKG, TOTAL GW
    </code>
    <p>We ignore <strong>PHOTO</strong>. Tracking numbers are auto-generated using the <strong>file name</strong> as prefix.</p>
  </section>
</main>

<?php include __DIR__ . '/../../assets/inc/footer.php'; ?>
