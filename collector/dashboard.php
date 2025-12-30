<?php
session_start();
require_once __DIR__ . '/../config/database.php';

/* ===================== AUTH ===================== */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

$collector_id = $_SESSION['user_id'];

/* ===================== DATE FILTER ===================== */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

/* ===================== COLLECTOR INFO ===================== */
$collectorStmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$collectorStmt->execute([$collector_id]);
$collector = $collectorStmt->fetch(PDO::FETCH_ASSOC);

/* ===================== PAGINATION SETUP ===================== */
$limit = 10; // records per page
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

/* ===================== PAYMENTS ===================== */
// Total payments count for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM payments 
    WHERE collector_id = ? 
      AND DATE(paid_at) BETWEEN ? AND ?
");
$countStmt->execute([$collector_id, $from, $to]);
$totalPayments = $countStmt->fetchColumn();
$totalPages = ceil($totalPayments / $limit);

// Fetch paginated payments
$paymentsStmt = $pdo->prepare("
    SELECT p.*, v.vendor_name, v.stall_number
    FROM payments p
    LEFT JOIN vendors v ON v.id = p.vendor_id
    WHERE p.collector_id = :collector_id
      AND DATE(p.paid_at) BETWEEN :from AND :to
    ORDER BY p.paid_at DESC
    LIMIT :start, :limit
");
$paymentsStmt->bindValue(':collector_id', $collector_id, PDO::PARAM_INT);
$paymentsStmt->bindValue(':from', $from);
$paymentsStmt->bindValue(':to', $to);
$paymentsStmt->bindValue(':start', $start, PDO::PARAM_INT);
$paymentsStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$paymentsStmt->execute();
$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

/* ===================== TOTAL COLLECTED ===================== */
$total_collected = 0;
foreach ($payments as $p) {
    $total_collected += ($p['amount_paid'] ?? 0)
                      - ($p['discount'] ?? 0)
                      + ($p['penalty'] ?? 0);
}

/* ===================== BEST VENDOR ===================== */
$bestVendorStmt = $pdo->query("
    SELECT CONCAT(u.first_name, ' ', u.last_name) AS name,
           SUM(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)) AS total,
           AVG(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)) AS avg_daily
    FROM payments p
    JOIN vendors v ON v.id = p.vendor_id
    JOIN users u ON u.id = v.user_id
    WHERE p.status = 'paid'
    GROUP BY v.id
    ORDER BY total DESC
    LIMIT 1
");
$bestVendor = $bestVendorStmt->fetch(PDO::FETCH_ASSOC);

/* ===================== VENDOR STATUS ===================== */
$statusStmt = $pdo->query("
    SELECT 
        SUM(status='active') AS active,
        SUM(status='inactive') AS inactive,
        SUM(status='overdue') AS overdue
    FROM vendors
");
$vendorStatus = $statusStmt->fetch(PDO::FETCH_ASSOC);

/* ===================== VENDOR PERFORMANCE ===================== */
$vendorRankingStmt = $pdo->prepare("
    SELECT 
        v.id,
        CONCAT(u.first_name,' ', u.last_name) AS name,
        COUNT(p.id) AS transactions,
        COALESCE(SUM(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)),0) AS total_collected
    FROM vendors v
    LEFT JOIN payments p 
        ON p.vendor_id = v.id
       AND DATE(p.paid_at) BETWEEN :from AND :to
    LEFT JOIN users u ON u.id = v.user_id
    GROUP BY v.id
    ORDER BY total_collected DESC
");
$vendorRankingStmt->execute([':from' => $from, ':to' => $to]);
$rankings = $vendorRankingStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX request for vendor info by stall number
if (isset($_GET['stall_number'])) {
    $stall_number = $_GET['stall_number'];

    $stmt = $pdo->prepare("
        SELECT v.*, u.first_name, u.last_name, s.section_name
        FROM vendors v
        JOIN users u ON u.id = v.user_id
        LEFT JOIN sections s ON s.id = v.section_id
        WHERE v.stall_number = ? 
        LIMIT 1
    ");
    $stmt->execute([$stall_number]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vendor) {
        echo json_encode([
            'success' => true,
            'vendor' => [
                'vendor_id' => $vendor['id'],
                'vendor_name' => $vendor['first_name'].' '.$vendor['last_name'],
                'vendor_contact' => $vendor['contact'] ?? '',
                'stall_number' => $vendor['stall_number'],
                'monthly_rent' => $vendor['monthly_rent'],
                'section_name' => $vendor['section_name']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Vendor not found']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Collector Dashboard | RPMS</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<style>
body{font-family:Poppins;background:#f4f6f9}
.card{border-radius:14px}
.stat-card{color:#fff}
.table th,.table td{vertical-align:middle}
.badge-gold{background:#ffc107;color:#000}
@media(max-width:768px){
    h2{font-size:1.2rem}
    .btn{width:100%}
}
</style>
</head>

<body>
<?php include __DIR__ . '/collector_navbar.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4">Collection Payment Management</h2>

<h2 class="mb-3">Welcome, <?= htmlspecialchars($collector['first_name']) ?></h2>

<!-- ===================== DATE FILTER ===================== -->
<form class="row g-2 mb-4">
    <div class="col-6 col-md-3">
        <input type="date" name="from" value="<?= $from ?>" class="form-control">
    </div>
    <div class="col-6 col-md-3">
        <input type="date" name="to" value="<?= $to ?>" class="form-control">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary">Filter</button>
    </div>
</form>

<!-- ===================== STATS ===================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card bg-success p-3 text-center stat-card">
            <h6>Total Collected</h6>
            <p class="fs-3">₱<?= number_format($total_collected,2) ?></p>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card bg-info p-3 text-center stat-card">
            <h6>Transactions</h6>
            <p class="fs-3"><?= count($payments) ?></p>
        </div>
    </div>
    <div class="col-6 col-md-3">
    <div class="card bg-warning p-3 text-center stat-card">
        <h6>Avg Daily Collection</h6>
        <p class="fs-3">
            <?= $bestVendor && isset($bestVendor['avg_daily']) 
                ? '₱'.number_format($bestVendor['avg_daily'], 2) 
                : '—' ?>
        </p>
    </div>
</div>


<div class="col-6 col-md-3">
    <div class="card bg-primary p-3 text-center stat-card">
        <h6>Best Vendor</h6>
        <p class="fs-5">
            <?= $bestVendor
                ? htmlspecialchars($bestVendor['name']) // Assuming your query returns 'name'
                : '—' ?>
        </p>
    </div>
</div>


<!-- ===================== CHARTS ===================== -->
<div class="row mb-4">
    <div class="col-md-8 mb-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5>Payments Over Time</h5>
                <canvas id="paymentsChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5>Vendor Status</h5>
                <canvas id="vendorChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>


    <!-- ===================== PAYMENT FORM ===================== -->
        <div class="card shadow-sm mb-4">
        <div class="card-body">

            <h5 class="mb-3">Submit Payment</h5>

            <form id="paymentForm">
            <div class="row g-3">

                <!-- Stall # / Barcode -->
                <div class="col-12 col-md-3">
                <label class="fw-semibold">Stall # / Barcode</label>
                <input type="text" id="stall_number" name="stall_number"
                        class="form-control form-control-lg" autocomplete="off" autofocus>
                <small id="vendorLookup" class="text-muted"></small>
                </div>

                <!-- Vendor Info -->
                <div class="col-12 col-md-9">
                <fieldset class="border p-3 rounded">
                    <legend class="float-none w-auto px-2">Vendor Info</legend>

                    <div class="row g-2">
                    <div class="col-12 col-md-4">
                        <label class="fw-semibold">Name</label>
                        <input type="text" id="vendor_name" name="vendor_name" class="form-control form-control-lg" readonly>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="fw-semibold">Stall #</label>
                        <input type="text" id="stall_number_display" name="stall_number_display" class="form-control form-control-lg" readonly>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="fw-semibold">Section</label>
                        <input type="text" id="section_name" name="section_name" class="form-control form-control-lg" readonly>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="fw-semibold">Monthly Rent</label>
                        <input type="text" id="monthly_rent" name="monthly_rent" class="form-control form-control-lg" readonly>
                    </div>

                    <!-- Hidden Vendor ID -->
                    <input type="hidden" id="vendor_id" name="vendor_id">
                    </div>
                </fieldset>
                </div>
            
<!-- Payment Type -->
<div class="col-6 col-md-2">
  <label class="fw-semibold">Payment Type</label>
  <select id="payment_type" name="payment_type" class="form-select form-select-lg">
    <option value="daily">Daily</option>
    <option value="weekly">Weekly</option>
    <option value="monthly">Monthly</option>
  </select>
</div>

<!-- Period (Auto-computed by JS) -->
<input type="hidden" id="period_start" name="period_start">
<input type="hidden" id="period_end" name="period_end">


                <!-- Amount -->
                <div class="col-6 col-md-2">
                <label>Amount</label>
                <input type="number" step="0.01" id="amount_paid" name="amount_paid" class="form-control form-control-lg">
                </div>

                <!-- Discount -->
                <div class="col-6 col-md-2">
                <label>Discount</label>
                <input type="number" step="0.01" id="discount" name="discount" value="0" class="form-control form-control-lg">
                </div>

                <!-- Penalty -->
                <div class="col-6 col-md-2">
                <label>Penalty</label>
                <input type="number" step="0.01" id="penalty" name="penalty" value="0" class="form-control form-control-lg">
                </div>

                <!-- Total -->
                <div class="col-6 col-md-3">
                <label>Total</label>
                <input type="text" id="total" class="form-control form-control-lg fw-bold" readonly>
                </div>

            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-success btn-lg mt-3">Submit & Preview Receipt</button>
            </form>

            <!-- Message Area -->
            <div id="paymentMsg" class="mt-2"></div>

        </div>
        </div>
    </div>


<!-- ===================== RECENT PAYMENTS ===================== -->
<div class="card shadow-sm mb-5">
  <div class="card-body">
    <h5>Recent Payments</h5>
    <div class="table-responsive">
      <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
          <tr>
            <th>Date</th>
            <th>Vendor</th>
            <th>Stall #</th>
            <th>Amount Paid</th>
            <th>Discount</th>
            <th>Penalty</th>
            <th>Total</th>
            <th>Receipt</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!empty($payments)): ?>
          <?php foreach ($payments as $p):
              $total = ($p['amount_paid'] ?? 0) - ($p['discount'] ?? 0) + ($p['penalty'] ?? 0);
          ?>
          <tr>
            <td><?= htmlspecialchars($p['paid_at']) ?></td>
            <td><?= htmlspecialchars($p['vendor_name']) ?></td>
            <td><?= htmlspecialchars($p['stall_number']) ?></td>
            <td>₱<?= number_format($p['amount_paid'], 2) ?></td>
            <td>₱<?= number_format($p['discount'], 2) ?></td>
            <td>₱<?= number_format($p['penalty'], 2) ?></td>
            <td>₱<?= number_format($total, 2) ?></td>
            <td>
              <a href="collector_receipt.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-primary">Print</a>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" class="text-center">No payments found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination Links -->
    <nav aria-label="Page navigation" class="mt-3">
      <ul class="pagination justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= $page-1 ?>">Previous</a>
        </li>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>

        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= $page+1 ?>">Next</a>
        </li>
      </ul>
    </nav>

  </div>
</div>
<!-- Vendor Performance Ranking -->
<div class="card shadow-sm mb-5">
    <div class="card-body">
        <h5>📊 Vendor Performance Ranking</h5>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Vendor</th>
                        <th>🏆</th>
                        <th>Transactions</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rankings)): ?>
                        <?php foreach ($rankings as $i => $v): ?>
                        <tr class="<?= $i === 0 ? 'table-success fw-semibold' : '' ?>">
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($v['name']) ?></td>
                            <td>
                                <?php if ($i === 0): ?>
                                    <span class="badge bg-warning text-dark">BEST</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $v['transactions'] ?></td>
                            <td>₱<?= number_format($v['total_collected'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No vendors found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Vendor Ranking Pagination">
            <ul class="pagination justify-content-center mt-3">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?>&from=<?= $from ?>&to=<?= $to ?>">
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="receiptPreviewModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Receipt Preview</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="receiptFrame" style="width:100%;height:400px;border:none;"></iframe>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-success btn-sm" id="printReceiptBtn">Print</button>
      </div>
    </div>
  </div>
</div>
<!-- 🔔 SOUND -->
<audio id="beepSound" preload="auto">
<source src="../assets/sounds/beep.mp3" type="audio/mpeg">
</audio>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function(){

    let vendorLoaded = false;
    let currentVendor = null;

    /* ===================== TOTAL ===================== */
    function updateTotal(){
        const amount   = parseFloat($('#amount_paid').val()) || 0;
        const discount = parseFloat($('#discount').val()) || 0;
        const penalty  = parseFloat($('#penalty').val()) || 0;
        $('#total').val((amount - discount + penalty).toFixed(2));
    }

    $('#amount_paid, #discount, #penalty').on('input', updateTotal);


    /* ===================== AMOUNT COMPUTE ===================== */
    function computeAmount(monthly, type){
        if(!monthly) return 0;
        if(type === 'daily')  return monthly / 30;
        if(type === 'weekly') return monthly / 4;
        return monthly;
    }


    /* ===================== DISCOUNT LOGIC ===================== */
    function computeDiscount(baseAmount){
        const today = new Date().getDate();
        return (today >= 1 && today <= 5) ? baseAmount * 0.05 : 0;
    }
    /* ===================== PENALTY LOGIC ===================== */
    function computePenalty(baseAmount, type, paymentDate = new Date()){
    const today = new Date();
    let late = false;

    if(type === 'monthly'){
        late = today.getDate() > 5;
    }
    else if(type === 'weekly'){
        const diffDays = Math.floor((today - paymentDate) / (1000 * 60 * 60 * 24));
        late = diffDays > 7;
    }
    else if(type === 'daily'){
        const diffDays = Math.floor((today - paymentDate) / (1000 * 60 * 60 * 24));
        late = diffDays > 1;
    }

    return late ? baseAmount * 0.02 : 0;
}


    /* ===================== APPLY COMPUTATION ===================== */
   function applyComputation(){
    if(!vendorLoaded || !currentVendor) return;

    const monthly = parseFloat(currentVendor.monthly_rent) || 0;
    const type    = $('#payment_type').val();
    const payDate = new Date($('#payment_date').val() || new Date());

    const amount   = computeAmount(monthly, type);
    const discount = computeDiscount(amount);
    const penalty  = computePenalty(amount, type, payDate);

    $('#amount_paid').val(amount.toFixed(2));
    $('#discount').val(discount.toFixed(2));
    $('#penalty').val(penalty.toFixed(2));

    updateTotal();
}

    /* ===================== LIVE VENDOR LOOKUP ===================== */
    let lookupTimer = null;

    $('#stall_number').on('input', function(){
        clearTimeout(lookupTimer);

        const stall = $(this).val().trim();

        vendorLoaded = false;
        currentVendor = null;

        $('#vendor_id').val('');
        $('#vendor_name, #stall_number_display, #section_name, #monthly_rent').val('');
        $('#amount_paid, #discount, #total').val('');
        $('#vendorLookup').removeClass('text-danger').text('');

        if(!stall) return;

        $('#vendorLookup').text('Searching...');

        lookupTimer = setTimeout(() => {

            $.getJSON('collector_payments.php', { stall_number: stall }, function(res){

                if(res.success){

                    const v = res.vendor;
                    currentVendor = v;
                    vendorLoaded  = true;

                    $('#vendor_id').val(v.id);
                    $('#vendor_name').val(v.first_name + ' ' + v.last_name);
                    $('#stall_number_display').val(v.stall_number);
                    $('#section_name').val(v.section_name ?? '');
                    $('#monthly_rent').val(parseFloat(v.monthly_rent).toFixed(2));

                    $('#vendorLookup').text('');

                    applyComputation();

                    if(document.getElementById('beepSound')){
                        document.getElementById('beepSound').play();
                    }

                } else {
                    $('#vendorLookup')
                        .text(res.message || 'Vendor not found')
                        .addClass('text-danger');
                }
            });

        }, 300);
    });


    /* ===================== PAYMENT TYPE CHANGE ===================== */
    $('#payment_type').on('change', function(){
        applyComputation();
    });


    /* ===================== KEYBOARD FLOW ===================== */
    $('#paymentForm input, #paymentForm select').on('keydown', function(e){
        if(e.key === 'Enter' && !e.ctrlKey){
            e.preventDefault();
            $(this).closest('.col').next().find('input, select').focus();
        }
        if(e.ctrlKey && e.key === 'Enter'){
            $('#paymentForm').submit();
        }
    });

 
    /* ===================== SUBMIT PAYMENT ===================== */
    $('#paymentForm').on('submit', function(e){
        e.preventDefault();

        const vendorId = $('#vendor_id').val();
        const amount   = parseFloat($('#amount_paid').val()) || 0;

        if(!vendorLoaded || !vendorId){
            Swal.fire({
                icon: 'warning',
                title: 'No vendor selected',
                text: 'Please type a valid stall number and wait for vendor details.'
            });
            return;
        }

        if(amount <= 0){
            Swal.fire({
                icon: 'warning',
                title: 'Invalid amount',
                text: 'Amount must be greater than zero.'
            });
            return;
        }

        Swal.fire({
            title: 'Confirm Payment',
            html: `
                <div class="text-start">
                    <p><strong>Vendor:</strong> ${$('#vendor_name').val()}</p>
                    <p><strong>Stall #:</strong> ${$('#stall_number_display').val()}</p>
                    <p><strong>Section:</strong> ${$('#section_name').val()}</p>
                    <p><strong>Payment Type:</strong> ${$('#payment_type').val()}</p>
                    <hr>
                    <p><strong>Total:</strong> ₱${$('#total').val()}</p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, submit payment',
            reverseButtons: true
        }).then((result) => {

            if(!result.isConfirmed) return;

            $.ajax({
                url: 'collector_api.php',
                type: 'POST',
                data: $('#paymentForm').serialize(),
                dataType: 'json',
                success: function(res){
                    if(res.success){
                        Swal.fire({
                            icon: 'success',
                            title: 'Payment Successful',
                            timer: 1200,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href =
                                'collector_receipt.php?id=' + res.payment_id;
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message || 'Payment failed'
                        });
                    }
                },
                error: function(){
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: 'Please try again.'
                    });
                }
            });
        });
    });
    
    /* ===================== AUTO PERIOD & AMOUNT ===================== */
$(document).ready(function(){

    /* ===================== DATE UTIL ===================== */
    function formatDate(d){
        return d.toISOString().split('T')[0];
    }

    function addDays(date, days){
        const d = new Date(date);
        d.setDate(d.getDate() + days);
        return d;
    }

    function endOfMonth(date){
        return new Date(date.getFullYear(), date.getMonth() + 1, 0);
    }

    /* ===================== AUTO PERIOD ===================== */
    function computePeriod(){

        const type = $('#payment_type').val();
        const base = $('#payment_date').val()
            ? new Date($('#payment_date').val())
            : new Date();

        let start = new Date(base);
        let end   = new Date(base);

        if(type === 'daily'){
            end = start;

        } else if(type === 'weekly'){
            end = addDays(start, 6);

        } else if(type === 'monthly'){
            start = new Date(start.getFullYear(), start.getMonth(), 1);
            end   = endOfMonth(start);
        }

        $('#period_start').val(formatDate(start));
        $('#period_end').val(formatDate(end));

        $('#period_display').val(
            formatDate(start) + ' → ' + formatDate(end)
        );
    }

    /* ===================== AUTO AMOUNT (OPTIONAL) ===================== */
    function autoAmount(){
        const type = $('#payment_type').val();
        const rent = parseFloat($('#monthly_rent').data('rent')) || 0;

        if(!rent) return;

        if(type === 'daily'){
            $('#amount_paid').val((rent / 30).toFixed(2));
        }

        if(type === 'weekly'){
            $('#amount_paid').val((rent / 4).toFixed(2));
        }

        if(type === 'monthly'){
            $('#amount_paid').val(rent.toFixed(2));
        }
    }

    /* ===================== EVENTS ===================== */
    $('#payment_type, #payment_date').on('change', function(){
        computePeriod();
        autoAmount();
    });

    /* ===================== INIT ===================== */
    if(!$('#payment_date').val()){
        $('#payment_date').val(formatDate(new Date()));
    }

    computePeriod();
});

    /* ---------- Offline Detection ---------- */
    function checkOnline(){
        if(!navigator.onLine){
            $('#paymentMsg').html('<div class="alert alert-warning">Offline mode</div>');
            $('button').prop('disabled', true);
        } else {
            $('button').prop('disabled', false);
        }
    }
    window.addEventListener('online', checkOnline);
    window.addEventListener('offline', checkOnline);
    checkOnline();

    /* ---------- Charts ---------- */
    new Chart(document.getElementById('vendorChart'), {
        type: 'pie',
        data: {
            labels: ['Active','Inactive','Overdue'],
            datasets: [{
                data: [<?= $vendorStatus['active'] ?>, <?= $vendorStatus['inactive'] ?>, <?= $vendorStatus['overdue'] ?>],
                backgroundColor: ['#28a745','#6c757d','#dc3545']
            }]
        },
        options: { responsive: true }
    });

    new Chart(document.getElementById('paymentsChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($p)=>date('M d', strtotime($p['paid_at'])), $payments)) ?>,
            datasets: [{
                label: 'Collected',
                data: <?= json_encode(array_map(fn($p)=>($p['amount_paid'] - $p['discount'] + $p['penalty']), $payments)) ?>,
                borderColor: '#0d6efd',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: { y: { beginAtZero: true } }
        }
    });

});
</script>
</body>
</html>