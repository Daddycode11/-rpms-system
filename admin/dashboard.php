<?php
session_start();
require_once '../config/database.php';

// --- ROLE CHECK: Only Admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// --- ADMIN OTP CHECK ---
if ($_SESSION['role'] === 'admin' && empty($_SESSION['otp_verified'])) {
    header("Location: ../auth/otp_verify.php");
    exit;
}

// --- FETCH DASHBOARD STATS ---
try {

    // Total Vendors
    $total_vendors = $pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();

    // Total Sections
    $total_sections = $pdo->query("SELECT COUNT(*) FROM sections")->fetchColumn();

    // Total Collection
    $total_collection_stmt = $pdo->query("
        SELECT COALESCE(SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)),0) AS total
        FROM payments
        WHERE status = 'paid'
    ");
    $total_collection = (float)$total_collection_stmt->fetchColumn();

    // Monthly Collection
    $monthlyDataStmt = $pdo->query("
        SELECT 
            YEAR(payment_date) AS year,
            DATE_FORMAT(payment_date, '%b') AS month,
            COALESCE(SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)),0) AS total
        FROM payments
        WHERE status = 'paid'
        GROUP BY YEAR(payment_date), MONTH(payment_date)
        ORDER BY YEAR(payment_date), MONTH(payment_date)
    ");
    $monthlyData = $monthlyDataStmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent Payments (Last 5)
    $recentPaymentsStmt = $pdo->query("
        SELECT 
            p.payment_date,
            CONCAT(u.first_name, ' ', u.last_name) AS vendor,
            p.amount_paid,
            COALESCE(p.status,'paid') AS status
        FROM payments p
        JOIN vendors v ON v.id = p.vendor_id
        JOIN users u ON u.id = v.user_id
        ORDER BY p.payment_date DESC
        LIMIT 5
    ");
    $recentPayments = $recentPaymentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Top 5 Vendors by Collection
    $topVendorsStmt = $pdo->query("
        SELECT 
            CONCAT(u.first_name, ' ', u.last_name) AS name,
            COALESCE(SUM(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)),0) AS total
        FROM payments p
        JOIN vendors v ON v.id = p.vendor_id
        JOIN users u ON u.id = v.user_id
        WHERE p.status = 'paid'
        GROUP BY v.id
        ORDER BY total DESC
        LIMIT 5
    ");
    $topVendors = $topVendorsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Activity Logs (Last 5)
    try {
        $logsStmt = $pdo->query("
            SELECT action, created_at 
            FROM activity_logs 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $logs = [];
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | RPMS</title>
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
</head>
<body>
<?php include 'navbar.php'; ?>


<div class="container py-5">

    <!-- HEADER -->
    <div class="page-header mb-4">
        <h2>Admin Dashboard</h2>
        <p>Overview of market operations and collections</p>
    </div>

    <!-- STATS -->
    <div class="row g-4">

        <!-- Vendors -->
        <div class="col-md-4">
            <div class="card stat-card bg-soft-primary">
                <div class="card-body">
                    <div class="stat-title">Total Vendors</div>
                    <div class="stat-value"><?php echo $total_vendors; ?></div>
                </div>
            </div>
        </div>

        <!-- Sections -->
        <div class="col-md-4">
            <div class="card stat-card bg-soft-success">
                <div class="card-body">
                    <div class="stat-title">Total Sections</div>
                    <div class="stat-value"><?php echo $total_sections; ?></div>
                </div>
            </div>
        </div>

   <!-- Collection -->
<div class="col-md-4">
    <div class="card stat-card bg-soft-warning">
        <div class="card-body">
            <div class="stat-title">Total Collection</div>
            <div class="stat-value">
                ₱<?php echo number_format($total_collection, 2); ?>
            </div>
        </div>
    </div>
</div>


    </div>
<div class="row g-4 mt-2">

    <!-- Monthly Collection Chart -->
    <div class="col-md-8">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Monthly Collection</h6>
                <canvas id="monthlyChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Vendors -->
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Top Vendors</h6>
                <ul class="list-group list-group-flush">
                    <?php foreach($topVendors as $v): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><?= htmlspecialchars($v['name']) ?></span>
                            <strong>₱<?= number_format($v['total'],2) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

</div>

<!-- Recent Payments + Logs -->
<div class="row g-4 mt-2">

    <!-- Recent Payments -->
    <div class="col-md-8">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Recent Payments</h6>
                <table class="table table-sm align-middle">
                    <thead class="text-muted">
                        <tr>
                            <th>Date</th>
                            <th>Vendor</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recentPayments as $p): ?>
                        <tr>
                            <td><?= $p['payment_date'] ?></td>
                            <td><?= htmlspecialchars($p['vendor']) ?></td>
                            <td>₱<?= number_format($p['amount_paid'],2) ?></td>
                            <td class="text-capitalize"><?= $p['status'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<!-- Activity Logs -->
<div class="col-md-4">
    <div class="card stat-card">
        <div class="card-body">
            <h6 class="fw-semibold mb-3">Activity Logs</h6>

            <?php if (!empty($logs)): ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($logs as $log): ?>
                        <li class="list-group-item">
                            <?= htmlspecialchars($log['action']) ?><br>
                            <small class="text-muted">
                                <?= date('M d, Y h:i A', strtotime($log['created_at'])) ?>
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="text-muted small text-center py-3">
                    No activity recorded yet
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('monthlyChart');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthlyData,'month')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($monthlyData,'total')) ?>,
            borderWidth: 2,
            fill: false,
            tension: 0.4
        }]
    },
    options: {
        plugins:{ legend:{ display:false }},
        scales:{
            y:{ beginAtZero:true }
        }
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
