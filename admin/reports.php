<?php
session_start();
require_once '../config/database.php';

/* =========================
   ROLE CHECK
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

/* =========================
   FILTERS
========================= */
$from      = $_GET['from'] ?? date('Y-m-01');
$to        = $_GET['to'] ?? date('Y-m-d');
$vendor_id = $_GET['vendor_id'] ?? 'all';
$group_by  = $_GET['group_by'] ?? 'none';

$params = [
    ':from' => $from,
    ':to'   => $to
];

$vendorWhere = '';
if ($vendor_id !== 'all') {
    $vendorWhere = ' AND p.vendor_id = :vendor_id ';
    $params[':vendor_id'] = $vendor_id;
}

/* =========================
   SUMMARY
========================= */
$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(p.id) AS total_payments,
        COALESCE(SUM(p.amount_paid),0) AS total_amount,
        COALESCE(SUM(p.discount),0) AS total_discount,
        COALESCE(SUM(p.amount_paid - p.discount),0) AS net_total
    FROM payments p
    WHERE p.paid_at BETWEEN :from AND :to
    $vendorWhere
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   GROUPING
========================= */
$groupSelect = '';
$groupJoin   = '';
$groupBySql  = '';

if ($group_by === 'vendor') {
    $groupSelect = ', v.stall_number AS group_name';
    $groupJoin   = ' JOIN vendors v ON v.id = p.vendor_id ';
    $groupBySql  = ' GROUP BY p.vendor_id ';
}

if ($group_by === 'collector') {
    $groupSelect = ', CONCAT(u.first_name," ",u.last_name) AS group_name';
    $groupJoin   = ' JOIN users u ON u.id = p.collector_id ';
    $groupBySql  = ' GROUP BY p.collector_id ';
}

/* =========================
   DETAILS
========================= */
$detailsStmt = $pdo->prepare("
    SELECT
        p.id,
        p.amount_paid AS amount,
        p.discount,
        (p.amount_paid - p.discount) AS net_amount,
        p.status,
        p.paid_at AS payment_date
        $groupSelect
    FROM payments p
    $groupJoin
    WHERE p.paid_at BETWEEN :from AND :to
    $vendorWhere
    $groupBySql
    ORDER BY p.paid_at DESC
");
$detailsStmt->execute($params);
$rows = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   VENDORS LIST
========================= */
$vendors = $pdo->query("
    SELECT id, stall_number
    FROM vendors
    ORDER BY stall_number
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   OVERDUE ALERTS
========================= */
$overdueVendors = $pdo->query("
    SELECT v.stall_number,
           DATEDIFF(CURDATE(), MAX(p.paid_at)) AS days_due
    FROM vendors v
    LEFT JOIN payments p ON p.vendor_id = v.id
    GROUP BY v.id
    HAVING days_due > 30
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   AUDIT LOGS
========================= */
try {
    $auditLogs = $pdo->query("
        SELECT action, created_at
        FROM activity_logs
        ORDER BY created_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $auditLogs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Reports</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    body {
        font-family: 'Poppins', sans-serif;
        font-size: 14px;
    }

    h1, h2, h3, h4, h5, h6 {
        font-weight: 600;
    }

    .table th {
        font-weight: 500;
    }

    .btn {
        font-weight: 500;
    }

    .card {
        border-radius: 12px;
    }
    .card {
    box-shadow: 0 4px 12px rgba(0,0,0,.05);
}

</style>

</head>

<body class="bg-light">
<?php include 'navbar.php'; ?>

<div class="container-fluid p-4">

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold">Reports</h4>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#auditModal">🧾 Audit Trail</button>
        <a href="export_excel.php?<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm">Export Excel</a>
        <a href="export_pdf.php?<?= http_build_query($_GET) ?>" class="btn btn-danger btn-sm">Export PDF</a>
    </div>
</div>

<!-- OVERDUE ALERT -->
<?php if ($overdueVendors): ?>
<div class="alert alert-warning">
    <strong>⚠ Overdue Vendors</strong>
    <ul class="mb-0">
        <?php foreach ($overdueVendors as $o): ?>
            <li>Stall <?= htmlspecialchars($o['stall_number']) ?> – <?= $o['days_due'] ?> days overdue</li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- FILTER FORM -->
<form id="filterForm" class="card card-body mb-4">
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">From</label>
        <input type="date" name="from" value="<?= $from ?>" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">To</label>
        <input type="date" name="to" value="<?= $to ?>" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Vendor</label>
        <select name="vendor_id" class="form-select">
            <option value="all">All Vendors</option>
            <?php foreach ($vendors as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $vendor_id == $v['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($v['stall_number']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Group By</label>
        <select name="group_by" class="form-select">
            <option value="none">None</option>
            <option value="vendor" <?= $group_by==='vendor'?'selected':'' ?>>Vendor</option>
            <option value="collector" <?= $group_by==='collector'?'selected':'' ?>>Collector</option>
        </select>
    </div>
    <div class="col-md-1 d-flex align-items-end">
        <button class="btn btn-primary w-100">Go</button>
    </div>
</div>
</form>

<!-- SUMMARY -->
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body">Payments<br><b><?= $summary['total_payments'] ?></b></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body">Total<br><b>₱<?= number_format($summary['total_amount'],2) ?></b></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body">Discount<br><b>₱<?= number_format($summary['total_discount'],2) ?></b></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body">Net<br><b class="text-success">₱<?= number_format($summary['net_total'],2) ?></b></div></div></div>
</div>

<!-- CHART -->
<div class="card mb-4">
<div class="card-body">
<h6 class="fw-semibold">Collection Trend</h6>
<canvas id="reportChart" height="90"></canvas>
</div>
</div>

<!-- TABLE -->
<div class="card shadow-sm">
<div class="card-header fw-semibold">Payment Records</div>
<div class="table-responsive">
<table class="table table-striped table-hover mb-0">
<thead class="table-dark">
<tr>
    <th>#</th>
    <?php if($group_by!=='none'): ?><th><?= ucfirst($group_by) ?></th><?php endif; ?>
    <th>Amount</th>
    <th>Discount</th>
    <th>Net</th>
    <th>Status</th>
    <th>Date</th>
    <th>Receipt</th>
</tr>
</thead>
<tbody>
<?php if(!$rows): ?>
<tr><td colspan="8" class="text-center">No records found</td></tr>
<?php endif; ?>
<?php foreach($rows as $r): ?>
<tr>
    <td><?= $r['id'] ?></td>
    <?php if($group_by!=='none'): ?><td><?= htmlspecialchars($r['group_name']) ?></td><?php endif; ?>
    <td>₱<?= number_format($r['amount'],2) ?></td>
    <td>₱<?= number_format($r['discount'],2) ?></td>
    <td class="fw-bold">₱<?= number_format($r['net_amount'],2) ?></td>
    <td><?= ucfirst($r['status']) ?></td>
    <td><?= date('M d, Y', strtotime($r['payment_date'])) ?></td>
    <td><a href="print_receipt.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">Print</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

</div>

<!-- AUDIT MODAL -->
<div class="modal fade" id="auditModal">
<div class="modal-dialog modal-lg modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header">
    <h5 class="modal-title">Audit Trail</h5>
    <button class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<ul class="list-group list-group-flush small">
<?php foreach($auditLogs as $log): ?>
<li class="list-group-item">
<?= htmlspecialchars($log['action']) ?><br>
<small class="text-muted"><?= date('M d, Y h:i A', strtotime($log['created_at'])) ?></small>
</li>
<?php endforeach; ?>
</ul>
</div>
</div>
</div>
</div>

<script>
new Chart(reportChart,{
    type:'bar',
    data:{
        labels:<?= json_encode(array_map(fn($r)=>date('M d',strtotime($r['payment_date'])),$rows)) ?>,
        datasets:[{data:<?= json_encode(array_column($rows,'net_amount')) ?>}]
    },
    options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
});

// auto refresh every 30s
setInterval(()=>location.reload(),30000);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
