<?php
session_start();
require_once '../config/database.php';

// --- ROLE CHECK: Vendor only ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../auth/login.php');
    exit;
}

$vendor_user_id = $_SESSION['user_id'];

// --- Fetch vendor ID for this user ---
$stmt = $pdo->prepare("SELECT id, stall_number FROM vendors WHERE user_id = :uid");
$stmt->execute([':uid' => $vendor_user_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    die("Vendor record not found.");
}

// --- Fetch payment history ---
$paymentsStmt = $pdo->prepare("
    SELECT id, amount_paid, discount, (amount_paid - discount) AS net_amount, status, paid_at
    FROM payments
    WHERE vendor_id = :vendor_id
    ORDER BY paid_at DESC
");
$paymentsStmt->execute([':vendor_id' => $vendor['id']]);
$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment History</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
.card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
h4 { font-weight: 600; }
.table th { font-weight: 500; }
</style>
</head>

<body>
<!-- NAVBAR -->
<?php include __DIR__ . '/vendor_navbar.php'; ?>

<div class="container py-4">
    <h4 class="mb-3">Payment History - Stall <?= htmlspecialchars($vendor['stall_number']) ?></h4>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Amount Paid</th>
                        <th>Discount</th>
                        <th>Net Amount</th>
                        <th>Status</th>
                        <th>Payment Date</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(!$payments): ?>
                    <tr><td colspan="7" class="text-center">No payment records found.</td></tr>
                <?php else: ?>
                    <?php foreach($payments as $p): ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td>₱<?= number_format($p['amount_paid'],2) ?></td>
                            <td>₱<?= number_format($p['discount'],2) ?></td>
                            <td class="fw-bold">₱<?= number_format($p['net_amount'],2) ?></td>
                            <td><?= ucfirst($p['status']) ?></td>
                            <td><?= date('M d, Y', strtotime($p['paid_at'])) ?></td>
                            <td>
                                <a href="print_receipt.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">Print</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
