<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if(!isset($_SESSION['role']) || $_SESSION['role']!=='collector'){
    header("Location: ../auth/login.php"); exit;
}

$vendor_id = $_GET['vendor_id'] ?? null;
if(!$vendor_id){
    echo "<div class='alert alert-warning'>Vendor ID missing</div>"; exit;
}

// Fetch vendor info
$stmt = $pdo->prepare("SELECT vendor_name, monthly_rent, stall_number, status FROM vendors WHERE id=?");
$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$vendor){
    echo "<div class='alert alert-warning'>Vendor not found</div>"; exit;
}

// Fetch all payments for this vendor
$stmt = $pdo->prepare("SELECT * FROM payments WHERE vendor_id=? ORDER BY payment_date ASC");
$stmt->execute([$vendor_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compute running balance
$remaining = (float)$vendor['monthly_rent'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Vendor Account | <?= htmlspecialchars($vendor['vendor_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<style>
body{font-family:Poppins;background:#f4f6f9;padding:20px;}
.table th, .table td {vertical-align:middle;}
.overdue{background:#f8d7da !important;}
</style>
</head>
<body>
<div class="container">
    <h4>Vendor Account: <?= htmlspecialchars($vendor['vendor_name']) ?></h4>
    <p>Stall #: <?= htmlspecialchars($vendor['stall_number']) ?> | Monthly Rent: ₱<?= number_format($vendor['monthly_rent'],2) ?> | Status: <strong><?= ucfirst($vendor['status']) ?></strong></p>
    <hr>

    <h5>Payment History</h5>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Payment Type</th>
                    <th>Amount Paid</th>
                    <th>Discount</th>
                    <th>Penalty</th>
                    <th>Total Collected</th>
                    <th>Remaining Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($payments as $i => $p):
                    $amount = (float)$p['amount_paid'];
                    $discount = (float)$p['discount'];
                    $penalty = (float)$p['penalty'];
                    $total = $amount - $discount + $penalty;
                    $remaining -= $total;
                    $overdue_class = $remaining > 0 && strtotime($p['payment_date']) < strtotime('-1 month') ? 'overdue' : '';
                ?>
                <tr class="<?= $overdue_class ?>">
                    <td><?= $i+1 ?></td>
                    <td><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                    <td><?= ucfirst($p['payment_type']) ?></td>
                    <td>₱<?= number_format($amount,2) ?></td>
                    <td>₱<?= number_format($discount,2) ?></td>
                    <td>₱<?= number_format($penalty,2) ?></td>
                    <td>₱<?= number_format($total,2) ?></td>
                    <td>₱<?= number_format(max($remaining,0),2) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($payments)): ?>
                <tr><td colspan="8" class="text-center">No payments found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
