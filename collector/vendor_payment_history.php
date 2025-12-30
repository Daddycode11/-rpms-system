<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if(!isset($_SESSION['role']) || $_SESSION['role']!=='collector'){
    echo "<div class='alert alert-danger'>Unauthorized access</div>";
    exit;
}

$vendor_id = $_GET['vendor_id'] ?? null;
if(!$vendor_id){
    echo "<div class='alert alert-warning'>Vendor ID missing</div>";
    exit;
}

// Fetch vendor info
$stmt = $pdo->prepare("SELECT vendor_name, monthly_rent FROM vendors WHERE id=?");
$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$vendor){
    echo "<div class='alert alert-warning'>Vendor not found</div>";
    exit;
}

// Fetch all payments for this vendor
$stmt = $pdo->prepare("SELECT * FROM payments WHERE vendor_id=? ORDER BY payment_date ASC");
$stmt->execute([$vendor_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$remaining = (float)$vendor['monthly_rent'];
?>
<div>
    <h6>Vendor: <?= htmlspecialchars($vendor['vendor_name']) ?> | Monthly: ₱<?= number_format($vendor['monthly_rent'],2) ?></h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Payment Type</th>
                    <th>Amount Paid</th>
                    <th>Discount</th>
                    <th>Penalty</th>
                    <th>Total Collected</th>
                    <th>Remaining</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($payments as $i=>$p): 
                    $amount = (float)$p['amount_paid'];
                    $discount = (float)$p['discount'];
                    $penalty = (float)$p['penalty'];
                    $total = $amount - $discount + $penalty;
                    $remaining -= $total;
                ?>
                <tr>
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
