<?php
session_start();
require_once '../config/database.php';

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Detect balance column
$columns = $pdo->query("SHOW COLUMNS FROM vendors")->fetchAll(PDO::FETCH_COLUMN);
$possibleBalances = ['balance', 'outstanding_balance', 'amount_due', 'total_due'];
$balanceColumn = null;
foreach ($possibleBalances as $col) {
    if (in_array($col, $columns)) {
        $balanceColumn = $col;
        break;
    }
}
if (!$balanceColumn) die("Error: No balance column found in vendors table.");

// Fetch vendors with positive balance
$vendors = $pdo->query("
    SELECT v.id, v.vendor_name, v.$balanceColumn AS balance
    FROM vendors v
    WHERE v.$balanceColumn > 0
    ORDER BY balance DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin vendor overdue | RPMS</title>
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
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <div class="page-header mb-4">
        <h2>Overdue Vendors</h2>
        <p class="text-muted">List of vendors with outstanding balances.</p>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-primary text-center">
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 70%;">Vendor Name</th>
                            <th style="width: 25%;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($vendors)): ?>
                            <?php foreach($vendors as $i => $vendor): ?>
                            <tr class="text-center">
                                <td><?= $i + 1 ?></td>
                                <td class="text-start"><?= htmlspecialchars($vendor['vendor_name']) ?></td>
                                <td><?= number_format($vendor['balance'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">No vendors with outstanding balances.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
