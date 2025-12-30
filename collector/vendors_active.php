<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if(!isset($_SESSION['role']) || $_SESSION['role']!=='collector'){header("Location: ../auth/login.php"); exit;}
$vendors = $pdo->query("SELECT * FROM vendors WHERE status='active' ORDER BY vendor_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Collector Vendors-Active | RPMS</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<style>
body{font-family:Poppins;background:#f4f6f9}
.card{border-radius:14px}
.stat-card{color:#fff}
.table th,.table td{vertical-align:middle}
.badge-gold{background:#ffc107;color:#000}
@media(max-width:768px){
    h2{font-size:1.2rem}
    .btn{width:100%}
}
</style>
</head>

<body>
<?php include __DIR__ . '/collector_navbar.php'; ?>
<div class="container mt-5">
    <h4>✅ Active Vendors</h4>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Stall #</th>
                    <th>Quick Pay</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($vendors as $i=>$v): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($v['vendor_name']) ?></td>
                    <td><?= htmlspecialchars($v['stall_number']) ?></td>
                    <td>
                        <a href="collector_payments.php?stall=<?= $v['stall_number'] ?>" class="btn btn-sm btn-success">
                            Pay
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>