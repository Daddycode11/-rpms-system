<?php
session_start();
require_once '../config/database.php';

// --- Collector role check ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

$collector_id = $_SESSION['user_id'];

// --- Fetch collector info ---
$collector = $pdo->prepare("SELECT * FROM users WHERE id=?");
$collector->execute([$collector_id]);
$collector = $collector->fetch(PDO::FETCH_ASSOC);

// --- Fetch all payments handled by this collector ---
$payments = $pdo->prepare("
    SELECT p.*, v.stall_number, v.monthly_rent, s.section_name,
           u.first_name AS vendor_first, u.last_name AS vendor_last
    FROM payments p
    JOIN vendors v ON v.id=p.vendor_id
    JOIN users u ON u.id=v.user_id
    LEFT JOIN sections s ON s.id=v.section_id
    WHERE p.collector_id = ?
    ORDER BY p.payment_date DESC
");
$payments->execute([$collector_id]);
$payments = $payments->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Collector Payment History | RPMS</title>
<?php include __DIR__ . '/includes/favicon.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
body { font-family: 'Poppins', sans-serif; background:#f4f6f9; }
.card { border-radius:12px; }
.table th, .table td { vertical-align: middle; }
</style>
</head>
<body>

<?php include __DIR__ . '/collector_navbar.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4">Payment Collection History</h2>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Vendor</th>
                            <th>Stall #</th>
                            <th>Section</th>
                            <th>Amount Paid</th>
                            <th>Discount</th>
                            <th>Penalty</th>
                            <th>Total</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $p):
                            $total = $p['amount_paid'] - $p['discount'] + $p['penalty'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($p['payment_date']); ?></td>
                            <td><?= htmlspecialchars($p['vendor_first'].' '.$p['vendor_last']); ?></td>
                            <td><?= htmlspecialchars($p['stall_number']); ?></td>
                            <td><?= htmlspecialchars($p['section_name'] ?? '-'); ?></td>
                            <td>₱<?= number_format($p['amount_paid'],2); ?></td>
                            <td>₱<?= number_format($p['discount'],2); ?></td>
                            <td>₱<?= number_format($p['penalty'],2); ?></td>
                            <td>₱<?= number_format($total,2); ?></td>
                            <td><a href="collector_receipt.php?id=<?= $p['id']; ?>" target="_blank" class="btn btn-sm btn-primary">Print</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($payments)): ?>
                        <tr><td colspan="9" class="text-center">No payments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
