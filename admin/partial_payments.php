<?php
session_start();
require_once '../config/database.php';

/* ADMIN CHECK */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

/* FETCH PARTIAL PAYMENTS */
$stmt = $pdo->query("
    SELECT 
        pp.id,
        v.stall_number,
        pp.amount,
        pp.paid_at
    FROM partial_payments pp
    JOIN vendors v ON v.id = pp.vendor_id
    ORDER BY pp.paid_at DESC
");
$partials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<title>Partial Payments</title>
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

/* DASHBOARD CARD */
.stat-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(0,0,0,.08);
    transition: transform .2s ease;
}
.stat-card:hover {
    transform: translateY(-4px);
}

.stat-title {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: .5px;
    color:#6c757d;
}

.stat-value {
    font-size: 2.2rem;
    font-weight: 600;
}

.bg-soft-primary { background: #e7f1ff; color:#0d6efd; }
.bg-soft-success { background: #e6f4ea; color:#198754; }
.bg-soft-warning { background: #fff4e5; color:#d39e00; }
</style>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <h4>Partial Payments</h4>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Stall</th>
                <th>Amount</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($partials as $i => $p): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($p['stall_number']) ?></td>
                <td>₱<?= number_format($p['amount'],2) ?></td>
                <td><?= date('M d, Y h:i A', strtotime($p['paid_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($partials)): ?>
            <tr><td colspan="4" class="text-center">No partial payments</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
