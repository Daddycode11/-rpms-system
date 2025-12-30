<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$vendors = $pdo->query("
SELECT v.*, 
CASE 
 WHEN v.balance <= 0 THEN 'Settled'
 WHEN v.next_due_date < CURDATE() THEN 'Overdue'
 ELSE 'Active'
END AS status
FROM vendors v
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Vendor Accounts</title>
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
<h4>Vendor Accounts</h4>

<table class="table table-striped">
<thead class="table-dark">
<tr>
<th>Vendor</th>
<th>Balance</th>
<th>Status</th>
<th>Next Due</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($vendors as $v): ?>
<tr class="<?= $v['status']=='Overdue'?'table-danger':'' ?>">
<td><?= htmlspecialchars($v['vendor_name']) ?></td>
<td>₱<?= number_format($v['balance'],2) ?></td>
<td><span class="badge bg-<?= $v['status']=='Overdue'?'danger':'success' ?>">
<?= $v['status'] ?></span></td>
<td><?= $v['next_due_date'] ?></td>
<td>
<a href="vendor_payment_history.php?vendor_id=<?= $v['id'] ?>" class="btn btn-sm btn-primary">History</a>
</td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
</body>
</html>
