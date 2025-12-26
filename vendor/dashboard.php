<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../auth/login.php");
    exit;
}

// --- Get vendor info ---
$vendorStmt = $pdo->prepare("
    SELECT v.id AS vendor_id, v.stall_number, v.monthly_rent, s.section_name,
           u.first_name, u.last_name
    FROM vendors v
    LEFT JOIN users u ON u.id = v.user_id
    LEFT JOIN sections s ON v.section_id = s.id
    WHERE v.user_id = ?
");
$vendorStmt->execute([$_SESSION['user_id']]);
$vendor = $vendorStmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['vendor_id'];

// --- Fetch payments ---
$paymentsStmt = $pdo->prepare("SELECT * FROM payments WHERE vendor_id = ? ORDER BY payment_date ASC");
$paymentsStmt->execute([$vendor_id]);
$payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Calculate totals ---
$total_paid = 0;
$payment_dates = [];
$payment_amounts = [];
foreach ($payments as $p) {
    $amount = $p['amount_paid'] - $p['discount'] + $p['penalty'];
    $total_paid += $amount;
    $payment_dates[] = $p['payment_date'];
    $payment_amounts[] = $amount;
}
$outstanding_balance = max(0, $vendor['monthly_rent'] - $total_paid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendor Dashboard | RPMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
body { font-family: 'Poppins', sans-serif; background: #f4f6f9; }
.card { border-radius: 12px; }
.table th, .table td { vertical-align: middle; }
</style>
</head>
<body>
<?php include __DIR__ . '/vendor_navbar.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4">Welcome, <?= htmlspecialchars($vendor['first_name']); ?></h2>

    <!-- Vendor Info Cards -->
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-3">
            <div class="card shadow-sm text-white bg-primary text-center p-3">
                <h5 class="card-title">Stall Number</h5>
                <p class="card-text fs-2"><?= htmlspecialchars($vendor['stall_number']); ?></p>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card shadow-sm text-white bg-success text-center p-3">
                <h5 class="card-title">Section</h5>
                <p class="card-text fs-2"><?= htmlspecialchars($vendor['section_name']); ?></p>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card shadow-sm text-white bg-warning text-center p-3">
                <h5 class="card-title">Total Paid</h5>
                <p class="card-text fs-2">₱<?= number_format($total_paid, 2); ?></p>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card shadow-sm text-white bg-danger text-center p-3">
                <h5 class="card-title">Outstanding Balance</h5>
                <p class="card-text fs-2" id="outstandingBalance">₱<?= number_format($outstanding_balance, 2); ?></p>
            </div>
        </div>
    </div>

    <!-- Payment Submission Card -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h5 class="mb-3">Submit Payment</h5>
            <form id="paymentForm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label>Amount Paid</label>
                        <input type="number" step="0.01" name="amount_paid" id="amount_paid" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label>Discount</label>
                        <input type="number" step="0.01" name="discount" id="discount" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label>Penalty</label>
                        <input type="number" step="0.01" name="penalty" id="penalty" class="form-control" value="0">
                    </div>
                </div>
                <div class="mt-3">
                    <h5>Total: ₱<span id="totalCalc">0.00</span></h5>
                </div>
                <div class="mt-2">
                    <button type="submit" class="btn btn-success">Submit Payment & Print Receipt</button>
                </div>
            </form>
            <div class="mt-2" id="paymentMsg"></div>
        </div>
    </div>

    <!-- Payment Chart Card -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h5>Payment History</h5>
            <canvas id="paymentChart" height="100"></canvas>
        </div>
    </div>

    <!-- Recent Payments Card -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h5>Recent Payments</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle" id="paymentsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Amount Paid</th>
                            <th>Discount</th>
                            <th>Penalty</th>
                            <th>Total</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['payment_date']); ?></td>
                            <td>₱<?= number_format($p['amount_paid'],2); ?></td>
                            <td>₱<?= number_format($p['discount'],2); ?></td>
                            <td>₱<?= number_format($p['penalty'],2); ?></td>
                            <td>₱<?= number_format($p['amount_paid'] - $p['discount'] + $p['penalty'],2); ?></td>
                            <td><a href="vendor_receipt.php?id=<?= $p['id']; ?>" target="_blank" class="btn btn-sm btn-primary">Print</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// --- Live total calculation ---
function calcTotal() {
    let amt = parseFloat($('#amount_paid').val()) || 0;
    let disc = parseFloat($('#discount').val()) || 0;
    let pen = parseFloat($('#penalty').val()) || 0;
    $('#totalCalc').text((amt - disc + pen).toFixed(2));
}
$('#amount_paid, #discount, #penalty').on('input', calcTotal);
calcTotal();

// --- AJAX Payment Submission ---
$('#paymentForm').on('submit', function(e){
    e.preventDefault();
    const data = $(this).serialize();
    $.post('vendor_api.php', data, function(response){
        $('#paymentMsg').html('<div class="alert alert-success">Payment submitted! Printing receipt...</div>');

        // Open receipt
        window.open('vendor_receipt.php?id=' + response.payment_id, '_blank');

        // Reload payments table
        $.get('vendor_api.php?action=get_payments', function(paymentsHtml){
            $('#paymentsTable tbody').html(paymentsHtml);
        });

        // Update outstanding balance and chart
        $.getJSON('vendor_api.php?action=get_balance', function(balance){
            $('#outstandingBalance').text('₱'+parseFloat(balance.outstanding).toFixed(2));
            $('#totalCalc').text('0.00');

            // Update chart
            paymentChart.data.datasets[0].data.push(balance.latest_payment);
            paymentChart.data.labels.push(balance.latest_date);
            paymentChart.update();
        });

        $('#paymentForm')[0].reset();
    }, 'json');
});

// --- Chart ---
const ctx = document.getElementById('paymentChart').getContext('2d');
const paymentChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($payment_dates); ?>,
        datasets: [{
            label: 'Payment Amount (₱)',
            data: <?= json_encode($payment_amounts); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.7)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1,
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { title: { display: true, text: 'Payment Date' } },
            y: { beginAtZero: true, title: { display: true, text: 'Amount (₱)' } }
        }
    }
});
</script>
</body>
</html>
