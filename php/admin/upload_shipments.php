<?php
require_once __DIR__ . '/../../assets/inc/init.php';
requireAdmin();
require_once __DIR__ . '/../../assets/inc/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$page_title = 'Admin - Upload Shipments';
$page_css   = '../../assets/css/admin/upload_shipments.css';
$page_js    = '../../assets/js/admin/upload_shipments.js';

@ini_set('memory_limit', '512M');
@set_time_limit(120);

/* -------------------- config -------------------- */
$ALLOWED_STATUSES = ['En Route','In Transit','Arrived','Delivered','Customs','Picked Up','Delayed','Cancelled'];
$acceptedExts = ['csv','xlsx'];
$maxSizeMB    = 20;

$flash_success = '';
$flash_error   = '';
$summary = ['total_rows'=>0,'inserted'=>0,'skipped'=>0,'errors'=>[]];

/* ------------------- helpers -------------------- */
function numOrNull($v) {
  $v = trim((string)$v);
  if ($v === '') return null;

  // Handle negatives written as (123.45)
  $neg = false;
  if ($v[0] === '(' && substr($v, -1) === ')') {
    $neg = true;
    $v = substr($v, 1, -1);
  }

  // Keep only digits, commas, dots and minus
  $v = preg_replace('/[^\d,.\-]/', '', $v);

  // If it uses comma as decimal and no dot, normalize to dot
  if (substr_count($v, ',') > 0 && substr_count($v, '.') === 0) {
    $v = str_replace('.', '', $v);   // thousands dots (just in case)
    $v = str_replace(',', '.', $v);  // decimal comma -> dot
  } else {
    // normal US format: remove thousands commas
    $v = str_replace(',', '', $v);
  }

  if ($neg) $v = '-'.$v;

  return is_numeric($v) ? (float)$v : null;
}

function intOrNull($v){ $v=trim((string)$v); return ($v!=='' && is_numeric($v)) ? (int)$v : null; }
function parseDateOrNull($v){ $v=trim((string)$v); if($v==='')return null; $ts=strtotime($v); return $ts?date('Y-m-d',$ts):null; }
function normalizeStatus(?string $s, array $allowed){ $s=trim((string)$s); return ($s!=='' && in_array($s,$allowed,true))?$s:'En Route'; }
function userIdFromShippingCode(PDO $pdo, ?string $code){
  $code=trim((string)$code); if($code==='')return null;
  $st=$pdo->prepare('SELECT user_id FROM users WHERE shipping_code=? LIMIT 1'); $st->execute([$code]);
  $row=$st->fetch(); return $row ? (int)$row['user_id'] : null;
}
function generateTrackingNumber(PDO $pdo, string $prefix){
  $prefix=strtoupper(preg_replace('/[^A-Z0-9]+/i','',$prefix)); $prefix=substr($prefix,0,10) ?: 'SC';
  for($i=0;$i<7;$i++){
    $c=sprintf('%s-%s-%s',$prefix,date('ymd'),strtoupper(bin2hex(random_bytes(3))));
    $st=$pdo->prepare('SELECT 1 FROM shipments WHERE tracking_number=? LIMIT 1'); $st->execute([$c]);
    if(!$st->fetch()) return $c;
  }
  return sprintf('%s-%u',$prefix,time());
}

/** canon map for tolerant header matching */
$CANON = [
  'photo'=>'photo',
  'item no'=>'item_no','itemno'=>'item_no','item no.'=>'item_no',
  'description'=>'description','desc'=>'description',
  'total ctns'=>'total_ctns','total cartons'=>'total_ctns','ctns'=>'total_ctns',
  'qty/ctn'=>'qty_per_ctn','qty per ctn'=>'qty_per_ctn','qty per carton'=>'qty_per_ctn',
  'totalqty'=>'total_qty','total qty'=>'total_qty',
  'unit price'=>'unit_price','price'=>'unit_price',
  'total amount'=>'total_amount','amount'=>'total_amount',
  'cbm'=>'cbm','total cbm'=>'total_cbm',
  'gwkg'=>'gwkg','gross weight (kg)'=>'gwkg',
  'total gw'=>'total_gw',
  // optional pass-throughs
  'shipping_code'=>'shipping_code','user_id'=>'user_id',
  'status'=>'status','origin'=>'origin','destination'=>'destination',
  'pickup_date'=>'pickup_date','delivery_date'=>'delivery_date',
  'unitprice' => 'unit_price',
'totalamount' => 'total_amount',
'total quantity' => 'total_qty',
'qty_ctn' => 'qty_per_ctn',
'qty per box' => 'qty_per_ctn',
'totalctns' => 'total_ctns',

  'tracking_number'=>'tracking_number'
  
];
$canonize = function(array $row) use ($CANON){
  $out=[];
  foreach($row as $k=>$v){
    $lk=strtolower(trim((string)$k));
    if(isset($CANON[$lk])) $out[$CANON[$lk]] = is_string($v)?trim($v):$v;
  }
  return $out;
};

/* --------------- import handler ----------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['file'])) {
  $f=$_FILES['file'];
  if ($f['error']!==UPLOAD_ERR_OK) { $flash_error='Upload failed.'; }
  else {
    if (($f['size']/(1024*1024))>$maxSizeMB) $flash_error='File too large.';
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    if(!$flash_error && !in_array($ext,$acceptedExts,true)) $flash_error='Unsupported file type. Use CSV or XLSX.';

    if(!$flash_error){
      $tmp=$f['tmp_name'];
      $originalName=pathinfo($f['name'],PATHINFO_FILENAME);
      $hasHeader=isset($_POST['has_header']);
      $rows=[];

      if ($ext==='csv') {
        if (($fh=fopen($tmp,'r'))===false){ $flash_error='Could not read CSV.'; }
        else{
          $header=null;
          while(($cols=fgetcsv($fh,0,','))!==false){
            // skip fully blank lines
            if (!array_filter($cols, fn($v)=>trim((string)$v) !== '')) continue;

            if ($header===null && $hasHeader){
              $header=array_map(fn($h)=>strtolower(trim((string)$h)),$cols);
              continue;
            }
            if ($header!==null){
              $assoc=[];
              foreach($header as $i=>$h){ if($h==='') continue; $assoc[$h]=$cols[$i]??''; }
              $rows[]=$assoc;
            }else{
              // numeric index (0-based)
              $rows[]=[
                'photo'=>$cols[0]??'',
                'item no'=>$cols[1]??'',
                'description'=>$cols[2]??'',
                'total ctns'=>$cols[3]??'',
                'qty/ctn'=>$cols[4]??'',
                'totalqty'=>$cols[5]??'',
                'unit price'=>$cols[6]??'',
                'total amount'=>$cols[7]??'',
                'cbm'=>$cols[8]??'',
                'total cbm'=>$cols[9]??'',
                'gwkg'=>$cols[10]??'',
                'total gw'=>$cols[11]??'',
              ];
            }
          }
          fclose($fh);
        }
      } else {
        try{
          $spreadsheet=IOFactory::load($tmp);
          $sheet=$spreadsheet->getActiveSheet();
          // IMPORTANT: returnCellRef = false -> numeric indexes (0,1,2…)
          $data=$sheet->toArray(null, true, true, false);

          if (!$data) { $flash_error='Empty XLSX sheet.'; }
          else{
            if ($hasHeader){
              $headerRow=array_shift($data);
              $header=array_map(fn($h)=>strtolower(trim((string)$h)),$headerRow);
              foreach($data as $cols){
                if (!array_filter($cols, fn($v)=>trim((string)$v) !== '')) continue;
                $assoc=[];
                foreach($header as $i=>$h){ if($h==='') continue; $assoc[$h]=$cols[$i]??''; }
                $rows[]=$assoc;
              }
            } else {
              foreach($data as $cols){
                if (!array_filter($cols, fn($v)=>trim((string)$v) !== '')) continue;
                $rows[]=[
                  'photo'=>$cols[0]??'',
                  'item no'=>$cols[1]??'',
                  'description'=>$cols[2]??'',
                  'total ctns'=>$cols[3]??'',
                  'qty/ctn'=>$cols[4]??'',
                  'totalqty'=>$cols[5]??'',
                  'unit price'=>$cols[6]??'',
                  'total amount'=>$cols[7]??'',
                  'cbm'=>$cols[8]??'',
                  'total cbm'=>$cols[9]??'',
                  'gwkg'=>$cols[10]??'',
                  'total gw'=>$cols[11]??'',
                ];
              }
            }
          }
        }catch(Throwable $e){ $flash_error='Could not parse XLSX.'; }
      }

      if (!$flash_error && $rows){
        $summary['total_rows']=count($rows);

        // canonize every row once
        $normRows = array_map($canonize, $rows);

        $pdo->beginTransaction();
        try{
          // one tracking per file
          $tracking = generateTrackingNumber($pdo, $originalName);

          // aggregate totals
          $agg=['cartons'=>0,'total_qty'=>0,'total_amount'=>0.0,'cbm'=>0.0,'total_cbm'=>0.0,'gwkg'=>0.0,'total_gw'=>0.0];
          foreach($normRows as $r){
            $agg['cartons']     += intOrNull($r['total_ctns']   ?? null) ?? 0;
            $agg['total_qty']   += intOrNull($r['total_qty']    ?? null) ?? 0;
            $agg['total_amount']+= numOrNull($r['total_amount'] ?? null) ?? 0.0;
            $agg['cbm']         += numOrNull($r['cbm']          ?? null) ?? 0.0;
            $agg['total_cbm']   += numOrNull($r['total_cbm']    ?? null) ?? 0.0;
            $agg['gwkg']        += numOrNull($r['gwkg']         ?? null) ?? 0.0;
            $agg['total_gw']    += numOrNull($r['total_gw']     ?? null) ?? 0.0;
          }

          // resolve user_id from first row that has shipping_code
          $userId=null;
          foreach($normRows as $r){ if(!empty($r['shipping_code'])){ $userId=userIdFromShippingCode($pdo,$r['shipping_code']); break; } }

          // insert one shipment row
$insShipment = $pdo->prepare('
  INSERT INTO shipments (
    user_id, tracking_number, container_number, bl_number, shipping_code,
    product_description, cbm, cartons, weight, gross_weight, total_amount,
    status, origin, destination, pickup_date, delivery_date,
    total_qty, total_cbm, total_gw,   -- ADDED
    created_at, updated_at
  ) VALUES (
    :user_id, :tracking, NULL, NULL, :shipping_code,
    :product_description, :cbm, :cartons, :weight, :gross_weight, :total_amount,
    :status, :origin, :destination, :pickup_date, :delivery_date,
    :total_qty, :total_cbm, :total_gw,  -- ADDED
    NOW(), NOW()
  )');

$insShipment->execute([
  ':user_id'             => $userId,
  ':tracking'            => $tracking,
  ':shipping_code'       => null,
  ':product_description' => sprintf('Imported from %s (%d items)', $originalName, count($normRows)),
  ':cbm'                 => ($agg['cbm'] > 0 ? $agg['cbm'] : $agg['total_cbm']),
  ':cartons'             => $agg['cartons'],
  ':weight'              => ($agg['gwkg'] > 0 ? $agg['gwkg'] : null),
  ':gross_weight'        => ($agg['total_gw'] > 0 ? $agg['total_gw'] : null),
  ':total_amount'        => $agg['total_amount'],
  ':status'              => 'En Route',
  ':origin'              => '',
  ':destination'         => '',
  ':pickup_date'         => null,
  ':delivery_date'       => null,
  // ADDED:
  ':total_qty'           => $agg['total_qty'],
  ':total_cbm'           => $agg['total_cbm'],
  ':total_gw'            => $agg['total_gw'],
]);

          $shipmentId=(int)$pdo->lastInsertId();

          // insert items
          $insItem=$pdo->prepare('
            INSERT INTO shipment_items (
              shipment_id, item_no, description, cartons, qty_per_ctn, total_qty,
              unit_price, total_amount, cbm, total_cbm, gwkg, total_gw
            ) VALUES (
              :shipment_id, :item_no, :description, :cartons, :qty_per_ctn, :total_qty,
              :unit_price, :total_amount, :cbm, :total_cbm, :gwkg, :total_gw
            )');
          foreach($normRows as $r){
            $insItem->execute([
              ':shipment_id'  => $shipmentId,
              ':item_no'      => trim((string)($r['item_no'] ?? '')),
              ':description'  => trim((string)($r['description'] ?? '')),
              ':cartons'      => intOrNull($r['total_ctns']   ?? null),
              ':qty_per_ctn'  => intOrNull($r['qty_per_ctn'] ?? null),
              ':total_qty'    => intOrNull($r['total_qty']   ?? null),
              ':unit_price'   => numOrNull($r['unit_price']  ?? null),
              ':total_amount' => numOrNull($r['total_amount']?? null),
              ':cbm'          => numOrNull($r['cbm']         ?? null),
              ':total_cbm'    => numOrNull($r['total_cbm']   ?? null),
              ':gwkg'         => numOrNull($r['gwkg']        ?? null),
              ':total_gw'     => numOrNull($r['total_gw']    ?? null),
            ]);
          }

          // log once
          try{
            $log=$pdo->prepare('INSERT INTO logs (action_type, actor_id, related_shipment_id, details, timestamp)
                                VALUES (?,?,?,?,NOW())');
            $log->execute(['shipments_import',(int)($_SESSION['admin_id']??0)*-1,$shipmentId,
              json_encode(['file'=>$originalName,'tracking'=>$tracking,'items'=>count($normRows)],JSON_UNESCAPED_UNICODE)]);
          }catch(Throwable $e){}

          $pdo->commit();
          $flash_success = "Upload complete: shipment #$shipmentId created with ".count($normRows)." item(s).";
        }catch(Throwable $tx){
          $pdo->rollBack();
          $flash_error='Upload failed. Transaction rolled back.';
        }
      } else if(!$flash_error){ $flash_error='No rows found.'; }
    }
  }
}

include __DIR__ . '/../../assets/inc/header.php';
?>
<main class="container">
  <h1>Upload Shipments</h1>

  <?php if ($flash_success): ?><div class="alert success"><?= htmlspecialchars($flash_success) ?></div><?php endif; ?>
  <?php if ($flash_error):   ?><div class="alert error"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>

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
    <p>We ignore <strong>PHOTO</strong>. One tracking number per file (customer): the file name is used as the tracking prefix.</p>
  </section>
</main>
<?php include __DIR__ . '/../../assets/inc/footer.php'; ?>
