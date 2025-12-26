<?php
require_once '../config/database.php';
session_start();

// --- ROLE CHECK: Admin only ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// --- FILTERS ---
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$vendor_id = $_GET['vendor_id'] ?? 'all';
$group_by = $_GET['group_by'] ?? 'none'; // none | vendor | collector

$params = [':from' => $from, ':to' => $to];
$vendorWhere = '';
if ($vendor_id !== 'all') {
    $vendorWhere = ' AND p.vendor_id = :vendor_id ';
    $params[':vendor_id'] = $vendor_id;
}

// --- SUMMARY ---
$summarySql = "
    SELECT
        COUNT(p.id) AS total_payments,
        COALESCE(SUM(p.amount_paid),0) AS total_amount,
        COALESCE(SUM(p.discount),0) AS total_discount,
        COALESCE(SUM(p.amount_paid - p.discount),0) AS net_total
    FROM payments p
    WHERE p.payment_date BETWEEN :from AND :to
    $vendorWhere
";
$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

// --- GROUPING SETUP ---
$groupSelect = '';
$groupJoin   = '';
$groupBy     = '';

if ($group_by === 'vendor') {
    $groupSelect = ', v.stall_number AS group_name';
    $groupJoin   = ' JOIN vendors v ON v.id = p.vendor_id ';
    $groupBy     = ' GROUP BY p.vendor_id ';
}

if ($group_by === 'collector') {
    $groupSelect = ', u.name AS group_name';
    $groupJoin   = ' JOIN users u ON u.id = p.collector_id ';
    $groupBy     = ' GROUP BY p.collector_id ';
}

// --- DETAILS ---
$detailsSql = "
    SELECT
        p.id,
        p.amount_paid AS amount,
        p.discount,
        (p.amount_paid - p.discount) AS net_amount,
        p.status,
        p.payment_date
        $groupSelect
    FROM payments p
    $groupJoin
    WHERE p.payment_date BETWEEN :from AND :to
    $vendorWhere
    $groupBy
    ORDER BY p.payment_date DESC
";
$detailsStmt = $pdo->prepare($detailsSql);
$detailsStmt->execute($params);
$rows = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);

// --- VENDORS LIST ---
$vendors = $pdo->query('SELECT id, stall_number FROM vendors ORDER BY stall_number')
               ->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Reports</title>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php include 'navbar.php'; ?>
<div class="container-fluid p-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold">Reports</h4>
    <div>
        <a href="export_excel.php?from=<?= $from ?>&to=<?= $to ?>&vendor_id=<?= $vendor_id ?>&group_by=<?= $group_by ?>" class="btn btn-success btn-sm">Export Excel</a>
        <a href="export_pdf.php?from=<?= $from ?>&to=<?= $to ?>&vendor_id=<?= $vendor_id ?>&group_by=<?= $group_by ?>" class="btn btn-danger btn-sm">Export PDF</a>
    </div>
</div>

<form class="card card-body mb-4" method="GET">
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

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body">Payments<br><b><?= $summary['total_payments'] ?></b></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body">Total<br><b>₱<?= number_format($summary['total_amount'],2) ?></b></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body">Discount<br><b>₱<?= number_format($summary['total_discount'],2) ?></b></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body">Net<br><b class="text-success">₱<?= number_format($summary['net_total'],2) ?></b></div></div></div>
</div>

<div class="card shadow-sm">
<div class="card-header fw-semibold">Payment Records</div>
<div class="table-responsive">
<table class="table table-striped table-hover mb-0">
<thead class="table-dark">
<tr>
    <th>#</th>
    <?php if($group_by!=='none'): ?><th><?= ucfirst($group_by) ?></th><?php endif; ?>
    <th>Amount Paid</th>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
