<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    exit("Unauthorized");
}

if (!isset($_GET['id'])) {
    exit("Payment ID is required");
}

$payment_id = intval($_GET['id']);

// Get vendor_id for the logged-in vendor
$stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vendor) exit("Vendor not found");
$vendor_id = $vendor['id'];

// Fetch payment and make sure it belongs to this vendor
$stmt = $pdo->prepare("SELECT * FROM payments WHERE id=? AND vendor_id=?");
$stmt->execute([$payment_id, $vendor_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) exit("Payment not found or unauthorized");

// Fetch vendor info for receipt
$stmt = $pdo->prepare("
    SELECT v.stall_number, s.section_name, u.first_name, u.last_name
    FROM vendors v
    LEFT JOIN users u ON u.id = v.user_id
    LEFT JOIN sections s ON v.section_id = s.id
    WHERE v.id = ?
");
$stmt->execute([$vendor_id]);
$vendor_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate total
$total = $payment['amount_paid'] - $payment['discount'] + $payment['penalty'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Receipt</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: 'Poppins', sans-serif; padding: 20px; }
.receipt { max-width: 600px; margin: auto; border: 1px solid #ccc; padding: 20px; }
h2 { text-align: center; margin-bottom: 20px; }
.table td, .table th { vertical-align: middle; }
@media print {
    .no-print { display: none; }
}
</style>
</head>
<body>
<div class="receipt">
    <h2>Payment Receipt</h2>
    <p><strong>Vendor:</strong> <?= htmlspecialchars($vendor_info['first_name'] . ' ' . $vendor_info['last_name']); ?></p>
    <p><strong>Stall Number:</strong> <?= htmlspecialchars($vendor_info['stall_number']); ?></p>
    <p><strong>Section:</strong> <?= htmlspecialchars($vendor_info['section_name']); ?></p>
    <p><strong>Payment Date:</strong> <?= htmlspecialchars($payment['payment_date']); ?></p>
    <table class="table table-bordered mt-3">
        <tr>
            <th>Amount Paid</th>
            <td>₱<?= number_format($payment['amount_paid'],2); ?></td>
        </tr>
        <tr>
            <th>Discount</th>
            <td>₱<?= number_format($payment['discount'],2); ?></td>
        </tr>
        <tr>
            <th>Penalty</th>
            <td>₱<?= number_format($payment['penalty'],2); ?></td>
        </tr>
        <tr>
            <th>Total</th>
            <td>₱<?= number_format($total,2); ?></td>
        </tr>
    </table>
    <div class="text-center mt-3">
        <button onclick="window.print();" class="btn btn-primary no-print">Print Receipt</button>
    </div>
</div>
</body>
</html>
