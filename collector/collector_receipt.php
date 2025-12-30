<?php
session_start();
require_once '../config/database.php';

/* ===================== AUTH ===================== */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    exit('Unauthorized access');
}

$payment_id = $_GET['id'] ?? null;
if (!$payment_id) {
    exit('Payment ID missing');
}

/* ===================== FETCH PAYMENT ===================== */
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        v.id AS vendor_id,
        v.stall_number,
        v.monthly_rent,
        s.section_name,
        u.first_name AS vendor_first,
        u.last_name AS vendor_last
    FROM payments p
    JOIN vendors v ON v.id = p.vendor_id
    JOIN users u ON u.id = v.user_id
    LEFT JOIN sections s ON s.id = v.section_id
    WHERE p.id = ? AND p.collector_id = ?
    LIMIT 1
");
$stmt->execute([$payment_id, $_SESSION['user_id']]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    exit('Payment not found');
}

/* ===================== BASIC VALUES ===================== */
$paymentDate  = new DateTime($payment['payment_date']);
$paymentType  = $payment['payment_type'] ?? 'monthly';
$monthlyRent  = (float)$payment['monthly_rent'];
$amountPaid   = (float)$payment['amount_paid'];
$discount     = (float)$payment['discount'];
$penalty      = (float)$payment['penalty'];
$total        = $amountPaid - $discount + $penalty;

$collectorName = trim(
    ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')
);

/* ===================== COVERED PERIOD ===================== */
$periodFrom = clone $paymentDate;
$periodTo   = clone $paymentDate;

if ($paymentType === 'daily') {
    $periodTo->modify('+1 day');
}
elseif ($paymentType === 'weekly') {
    $periodTo->modify('+7 days');
}
else {
    $periodFrom->modify('first day of this month');
    $periodTo->modify('last day of this month');
}

/* ===================== ON-TIME / LATE ===================== */
$statusText  = 'ON-TIME';
$statusColor = '#28a745';

if ($paymentType === 'monthly' && (int)$paymentDate->format('d') > 5) {
    $statusText  = 'LATE';
    $statusColor = '#dc3545';
}

/* ===================== NEXT DUE DATE ===================== */
$nextDue = clone $periodTo;

if ($paymentType === 'daily') {
    $nextDue->modify('+1 day');
}
elseif ($paymentType === 'weekly') {
    $nextDue->modify('+7 days');
}
else {
    $nextDue->modify('first day of next month');
}

/* ===================== DISCOUNT PERCENT ===================== */
$discountPercent = $monthlyRent > 0
    ? ($discount / $monthlyRent) * 100
    : 0;

/* ===================== QR CODE ===================== */
$receiptUrl = "https://yourdomain.com/collector_receipt.php?id=".$payment['id'];
$qrCodeUrl  = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data="
                . urlencode($receiptUrl);

/* ===================== PARTIAL PAYMENTS HISTORY ===================== */
$historyStmt = $pdo->prepare("
    SELECT payment_date, amount_paid, discount, penalty
    FROM payments
    WHERE vendor_id = ?
    ORDER BY payment_date DESC
    LIMIT 5
");
$historyStmt->execute([$payment['vendor_id']]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt #<?= $payment['id'] ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
body{
    font-family:'Poppins',sans-serif;
    background:#f4f6f9;
    margin:0;
    padding:20px;
    display:flex;
    justify-content:center;
}
.receipt{
    background:#fff;
    width:350px;
    padding:20px;
    border-radius:12px;
    border:1px solid #ddd;
    box-shadow:0 5px 20px rgba(0,0,0,.1);
}
.header{text-align:center;}
.header img{max-width:100px;}
.status{font-weight:600;margin:5px 0;}
.table{width:100%;font-size:13px;border-collapse:collapse;}
.table td{padding:4px 0;}
.table td:first-child{font-weight:500;}
.table td:last-child{text-align:right;}
.discount{color:#fd7e14;}
.penalty{color:#dc3545;}
.total-row td{
    font-weight:600;
    border-top:2px dashed #ccc;
    padding-top:6px;
    color:#28a745;
}
.footer{text-align:center;font-size:11px;color:#6c757d;margin-top:10px;}
.no-print{margin-top:12px;display:flex;gap:10px;justify-content:center;}

@media print{
    body{background:none;padding:0;}
    .receipt{
        width:220px;
        padding:10px;
        border:none;
        box-shadow:none;
    }
    .table td{font-size:11px;padding:2px 0;}
    .no-print{display:none;}
}
</style>
</head>
<body>

<div class="receipt">

<div class="header">
    <img src="../assets/images/logo.png">
    <div class="status" style="color:<?= $statusColor ?>"><?= $statusText ?></div>
    <small>POS Receipt</small>
</div>

<hr>

<table class="table">
<tr><td>Receipt #</td><td><?= $payment['id'] ?></td></tr>
<tr><td>Date</td><td><?= $paymentDate->format('m/d/Y') ?></td></tr>
<tr><td>Time</td><td><?= $paymentDate->format('h:i A') ?></td></tr>
<tr><td>Collector</td><td><?= htmlspecialchars($collectorName) ?></td></tr>
</table>

<hr>

<table class="table">
<tr><td>Vendor</td><td><?= htmlspecialchars($payment['vendor_first'].' '.$payment['vendor_last']) ?></td></tr>
<tr><td>Stall #</td><td><?= $payment['stall_number'] ?></td></tr>
<tr><td>Section</td><td><?= $payment['section_name'] ?? '-' ?></td></tr>
<tr><td>Payment Type</td><td><?= ucfirst($paymentType) ?></td></tr>
<tr><td>Covered</td><td><?= $periodFrom->format('M d') ?> - <?= $periodTo->format('M d, Y') ?></td></tr>
<tr><td>Next Due</td><td><?= $nextDue->format('M d, Y') ?></td></tr>
</table>

<hr>

<table class="table">
<tr><td>Base</td><td>₱<?= number_format($amountPaid,2) ?></td></tr>
<tr><td>Discount (<?= round($discountPercent) ?>%)</td><td class="discount">- ₱<?= number_format($discount,2) ?></td></tr>
<tr><td>Penalty</td><td class="penalty">+ ₱<?= number_format($penalty,2) ?></td></tr>
<tr class="total-row"><td>TOTAL</td><td>₱<?= number_format($total,2) ?></td></tr>
</table>

<?php if ($history): ?>
<hr>
<strong style="font-size:11px;">Recent Payments</strong>
<table class="table">
<?php foreach($history as $h): ?>
<tr>
<td><?= date('m/d', strtotime($h['payment_date'])) ?></td>
<td style="text-align:right;">
₱<?= number_format($h['amount_paid'] - $h['discount'] + $h['penalty'],2) ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<div style="text-align:center;margin-top:6px;">
<img src="<?= $qrCodeUrl ?>" width="80">
<div style="font-size:10px;">Scan to verify</div>
</div>

<div class="footer">
<p>Thank you for your payment</p>
<strong>Collection Management System</strong>
</div>

<div class="no-print">
<button onclick="window.print()">Print</button>
<button onclick="location.href='dashboard.php'">Back</button>
</div>

</div>

</body>
</html>
