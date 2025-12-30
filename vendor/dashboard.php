<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../auth/login.php");
    exit;
}

/* =========================
   FETCH VENDOR INFO
========================= */
$vendorStmt = $pdo->prepare("
    SELECT v.id AS vendor_id, v.stall_number, v.monthly_rent, s.section_name,
           u.first_name, u.last_name
    FROM vendors v
    LEFT JOIN users u ON u.id = v.user_id
    LEFT JOIN sections s ON v.section_id = s.id
    WHERE v.user_id = ?
");
$vendorStmt->execute([$_SESSION['user_id']]);
$vendor = $vendorStmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['vendor_id'];

/* =========================
   FETCH PAYMENTS
========================= */
$paymentsStmt = $pdo->prepare("
    SELECT * FROM payments 
    WHERE vendor_id = ?
    ORDER BY payment_date ASC
");
$paymentsStmt->execute([$vendor_id]);
$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   COMPUTATIONS
========================= */
$total_paid = 0;
$payment_dates = [];
$payment_amounts = [];

foreach ($payments as $p) {
    $net = $p['amount_paid'] - $p['discount'] + $p['penalty'];
    $total_paid += $net;
    $payment_dates[] = $p['payment_date'];
    $payment_amounts[] = $net;
}

$outstanding_balance = max(0, $vendor['monthly_rent'] - $total_paid);

/* =========================
   DUE DATE & STATUS
========================= */
$today = new DateTime();
$lastPaymentDate = !empty($payments)
    ? new DateTime(end($payments)['payment_date'])
    : new DateTime('first day of this month');

$nextDueDate = (clone $lastPaymentDate)->modify('+1 month');
$graceEnd = (clone $nextDueDate)->modify('+5 days');

$isOverdue = $today > $graceEnd && $outstanding_balance > 0;
$statusLabel = $isOverdue ? 'OVERDUE' : 'ON-TIME';
$statusClass = $isOverdue ? 'danger' : 'success';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Vendor Dashboard | RPMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include $_SERVER['DOCUMENT_ROOT'].'/rpms-system/includes/favicon.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
body {
    font-family: 'Poppins', sans-serif;
    background: #f5f7fb;
}
.card {
    border-radius: 14px;
}
.metric-value {
    font-size: 1.8rem;
    font-weight: 600;
}
.mobile-payment { display:none; }

@media(max-width:768px){
    table{ display:none; }
    .mobile-payment{ display:block; }
}
</style>
</head>

<body>
<?php include __DIR__.'/vendor_navbar.php'; ?>

<div class="container mt-5">

<!-- ================= HEADER ================= -->
<h2>Welcome back, <?= htmlspecialchars($vendor['first_name']); ?> 👋</h2>
<p class="text-muted">Stall <?= $vendor['stall_number']; ?> · <?= $vendor['section_name']; ?></p>

<!-- ================= METRICS ================= -->
<div class="row mb-4">
    <div class="col-md-3 col-6 mb-3">
        <div class="card p-3 text-center bg-primary text-white">
            <div>Monthly Rent</div>
            <div class="metric-value">₱<?= number_format($vendor['monthly_rent'],2); ?></div>
        </div>
    </div>

    <div class="col-md-3 col-6 mb-3">
        <div class="card p-3 text-center bg-success text-white">
            <div>Total Paid</div>
            <div class="metric-value">₱<?= number_format($total_paid,2); ?></div>
        </div>
    </div>

    <div class="col-md-3 col-6 mb-3">
        <div class="card p-3 text-center bg-danger text-white">
            <div>Outstanding</div>
            <div class="metric-value">₱<?= number_format($outstanding_balance,2); ?></div>
        </div>
    </div>

    <div class="col-md-3 col-6 mb-3">
        <div class="card p-3 text-center bg-<?= $statusClass ?> text-white">
            <div>Status</div>
            <div class="metric-value"><?= $statusLabel ?></div>
            <small>Next Due: <?= $nextDueDate->format('M d, Y'); ?></small>
        </div>
    </div>
</div>

<!-- ================= CHARTS ================= -->
<div class="card mb-4 p-4">
    <h5>Payment Overview</h5>
    <div class="row">
        <div class="col-md-8">
            <canvas id="paymentChart"></canvas>
        </div>
        <div class="col-md-4">
            <canvas id="balancePie"></canvas>
        </div>
    </div>
</div>

<!-- ================= PAYMENT TABLE ================= -->
<div class="card p-4">
<h5>Payment History</h5>

<table class="table table-bordered align-middle">
<thead class="table-dark">
<tr>
    <th>Date</th>
    <th>Paid</th>
    <th>Discount</th>
    <th>Penalty</th>
    <th>Total</th>
    <th>Receipt</th>
</tr>
</thead>
<tbody>
<?php foreach($payments as $p): ?>
<tr class="<?= $isOverdue ? 'table-danger':'' ?>">
    <td><?= $p['payment_date']; ?></td>
    <td>₱<?= number_format($p['amount_paid'],2); ?></td>
    <td>₱<?= number_format($p['discount'],2); ?></td>
    <td>₱<?= number_format($p['penalty'],2); ?></td>
    <td>
        ₱<?= number_format($p['amount_paid'] - $p['discount'] + $p['penalty'],2); ?>
        <?php if($outstanding_balance>0): ?>
            <span class="badge bg-warning">Partial</span>
        <?php endif; ?>
    </td>
    <td>
        <a href="vendor_receipt.php?id=<?= $p['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
            Print
        </a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<!-- MOBILE VIEW -->
<div class="mobile-payment">
<?php foreach($payments as $p): ?>
<div class="card mb-2">
<div class="card-body">
<strong><?= date('M d, Y',strtotime($p['payment_date'])) ?></strong>
<div>Total: ₱<?= number_format($p['amount_paid']-$p['discount']+$p['penalty'],2) ?></div>
</div>
</div>
<?php endforeach; ?>
</div>

</div>
</div>

<!-- ================= OVERDUE POPUP ================= -->
<?php if($isOverdue): ?>
<div class="modal fade" id="overdueModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content border-danger">
<div class="modal-header bg-danger text-white">
<h5>⚠ Payment Overdue</h5>
</div>
<div class="modal-body">
<p>Your rent payment is overdue.</p>
<p><strong>Next Due:</strong> <?= $nextDueDate->format('M d, Y'); ?></p>
</div>
<div class="modal-footer">
<button class="btn btn-danger" data-bs-dismiss="modal">OK</button>
</div>
</div>
</div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
<?php if($isOverdue): ?>
new bootstrap.Modal(document.getElementById('overdueModal')).show();
<?php endif; ?>

new Chart(document.getElementById('paymentChart'), {
    type:'bar',
    data:{
        labels: <?= json_encode($payment_dates); ?>,
        datasets:[{
            data: <?= json_encode($payment_amounts); ?>,
            backgroundColor:'#0d6efd'
        }]
    }
});

new Chart(document.getElementById('balancePie'), {
    type:'pie',
    data:{
        labels:['Paid','Outstanding'],
        datasets:[{
            data:[<?= $total_paid ?>,<?= $outstanding_balance ?>],
            backgroundColor:['#198754','#dc3545']
        }]
    }
});
</script>

</body>
</html>
