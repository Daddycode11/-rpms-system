
<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$vendor_id = $_GET['vendor_id'] ?? 0;

$stmt = $pdo->prepare("
SELECT p.*, v.vendor_name
FROM payments p
JOIN vendors v ON v.id=p.vendor_id
WHERE v.id=?
ORDER BY p.paid_at DESC
");
$stmt->execute([$vendor_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Vendor Payment History</title>
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>


<!-- Bootstrap JS bundle (includes Popper) -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>


<!-- BOOTSTRAP -->
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">

<!-- POPPINS FONT -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
body {
    font-family: 'Poppins', sans-serif;
    background:#f4f6f9;
}

/* PAGE HEADER */
.page-header h2 {
    font-weight:600;
}
.page-header p {
    color:#6c757d;
}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container py-4">
<h4>Payment History</h4>

<table class="table table-sm table-bordered">
<thead class="table-dark">
<tr>
<th>Date</th>
<th>Amount</th>
<th>Discount</th>
<th>Penalty</th>
<th>Total</th>
</tr>
</thead>
<tbody>
<?php foreach($payments as $p): ?>
<tr>
<td><?= $p['paid_at'] ?></td>
<td>₱<?= number_format($p['amount_paid'],2) ?></td>
<td>₱<?= number_format($p['discount'],2) ?></td>
<td>₱<?= number_format($p['penalty'],2) ?></td>
<td><strong>₱<?= number_format($p['amount_paid'] - $p['discount'] + $p['penalty'],2) ?></strong></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
</body>
</html>
