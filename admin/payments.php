<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$payments = $pdo->query("
    SELECT p.*, v.vendor_name
    FROM payments p
    JOIN vendors v ON v.id=p.vendor_id
    ORDER BY p.paid_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Payments</title>

    <!-- Bootstrap CSS -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- Poppins Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f6f9;
        }

        .page-header h4 {
            font-weight: 600;
        }

        .card {
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,.08);
        }

        table.dataTable thead th {
            background: #0d6efd;
            color: #fff;
        }

        .dataTables_filter input {
            border-radius: 8px;
            padding: 4px 10px;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="page-header mb-3">
        <h4>All Payments</h4>
        <p class="text-muted">View all payments made by vendors with filtering and sorting.</p>
    </div>

    <div class="card p-3">
        <div class="table-responsive">
            <table id="paymentsTable" class="table table-striped table-hover table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($payments as $p): ?>
                    <tr>
                        <td><?= date('Y-m-d H:i', strtotime($p['paid_at'])) ?></td>
                        <td><?= htmlspecialchars($p['vendor_name']) ?></td>
                        <td>₱<?= number_format($p['amount_paid'], 2) ?></td>
                        <td><?= ucfirst($p['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>

<!-- jQuery and DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#paymentsTable').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 10,
        "lengthMenu": [5,10,25,50],
        "columnDefs": [
            { "type": "num", "targets": 2 } // ensure Amount column sorts numerically
        ]
    });
});
</script>

</body>
</html>
