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
$collector = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$collector->execute([$collector_id]);
$collector = $collector->fetch(PDO::FETCH_ASSOC);

// --- Fetch payments handled by this collector ---
$payments = $pdo->prepare("
    SELECT p.*, v.stall_number, u.first_name AS vendor_first, u.last_name AS vendor_last
    FROM payments p
    JOIN vendors v ON v.id = p.vendor_id
    JOIN users u ON u.id = v.user_id
    WHERE p.collector_id = ?
    ORDER BY p.payment_date DESC
");
$payments->execute([$collector_id]);
$payments = $payments->fetchAll(PDO::FETCH_ASSOC);

// --- Calculate total collected ---
$total_collected = 0;
foreach ($payments as $p) {
    $total_collected += $p['amount_paid'] - $p['discount'] + $p['penalty'];
}

// --- Vendor summary for pie chart ---
$vendorStatus = $pdo->query("
    SELECT 
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status='inactive' THEN 1 ELSE 0 END) AS inactive,
        SUM(CASE WHEN status='overdue' THEN 1 ELSE 0 END) AS overdue
    FROM vendors
")->fetch(PDO::FETCH_ASSOC);

// --- Total vendors and sections ---
$total_vendors = $pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
$total_sections = $pdo->query("SELECT COUNT(*) FROM sections")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Collector Dashboard | RPMS</title>
<?php include __DIR__ . '/includes/favicon.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<style>
body { font-family:'Poppins',sans-serif; background:#f4f6f9; }
.card { border-radius:12px; }
.table th, .table td { vertical-align: middle; }
@media(max-width:768px){ .chart-card{margin-bottom:1rem;} }
</style>
</head>
<body>

<?php include __DIR__ . '/collector_navbar.php'; ?>

<div class="container mt-5">

    <h2 class="mb-4">Welcome, <?= htmlspecialchars($collector['first_name']); ?></h2>

    <!-- SUMMARY CARDS -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm text-center p-3 bg-primary text-white">
                <h6>Total Vendors</h6>
                <p class="fs-2"><?= $total_vendors ?></p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm text-center p-3 bg-success text-white">
                <h6>Total Collected</h6>
                <p class="fs-2" id="totalCollected">₱<?= number_format($total_collected,2); ?></p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm text-center p-3 bg-warning text-white">
                <h6>Total Sections</h6>
                <p class="fs-2"><?= $total_sections ?></p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm text-center p-3 bg-info text-white">
                <h6>Collectors</h6>
                <p class="fs-2">1</p>
            </div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="row mb-4">
        <div class="col-md-8 chart-card">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>Payments Over Time</h5>
                    <canvas id="paymentsChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4 chart-card">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>Vendor Status</h5>
                    <canvas id="vendorPieChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- PAYMENT FORM -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h5 class="mb-3">Submit Payment</h5>
            <form id="paymentForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label>Vendor Stall #</label>
                        <input type="text" name="stall_number" id="stall_number" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label>Amount Paid</label>
                        <input type="number" step="0.01" name="amount_paid" id="amount_paid" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label>Discount</label>
                        <input type="number" step="0.01" name="discount" id="discount" class="form-control" value="0">
                    </div>
                    <div class="col-md-2">
                        <label>Penalty</label>
                        <input type="number" step="0.01" name="penalty" id="penalty" class="form-control" value="0">
                    </div>
                    <div class="col-md-2">
                        <label>Total</label>
                        <input type="text" id="total" class="form-control" readonly>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-success">Submit Payment & Print Receipt</button>
                </div>
            </form>
            <div class="mt-2" id="paymentMsg"></div>
        </div>
    </div>

    <!-- RECENT PAYMENTS TABLE -->
    <div class="card shadow-sm mb-5">
        <div class="card-body">
            <h5>Recent Payments</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle" id="paymentsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Vendor</th>
                            <th>Stall #</th>
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
                            <td><?= $p['payment_date'] ?></td>
                            <td><?= $p['vendor_first'].' '.$p['vendor_last'] ?></td>
                            <td><?= $p['stall_number'] ?></td>
                            <td>₱<?= number_format($p['amount_paid'],2) ?></td>
                            <td>₱<?= number_format($p['discount'],2) ?></td>
                            <td>₱<?= number_format($p['penalty'],2) ?></td>
                            <td>₱<?= number_format($total,2) ?></td>
                            <td><a href="collector_receipt.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-primary">Print</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// --- Auto-calculate total ---
function updateTotal() {
    let amount = parseFloat($('#amount_paid').val())||0;
    let discount = parseFloat($('#discount').val())||0;
    let penalty = parseFloat($('#penalty').val())||0;
    $('#total').val((amount-discount+penalty).toFixed(2));
}
$('#amount_paid,#discount,#penalty').on('input',updateTotal);
updateTotal();

// --- Initialize Charts ---
let paymentsChart, vendorPieChart;

// Payments Line Chart
function initPaymentsChart(labels,data){
    const ctx = document.getElementById('paymentsChart').getContext('2d');
    if(paymentsChart) paymentsChart.destroy();
    paymentsChart = new Chart(ctx,{
        type:'line',
        data:{labels:labels,datasets:[{
            label:'Total Collected (₱)',
            data:data,
            borderColor:'rgba(40,167,69,1)',
            backgroundColor:'rgba(40,167,69,0.2)',
            fill:true,
            tension:0.3
        }]},
        options:{responsive:true,scales:{y:{beginAtZero:true}}}
    });
}

// Vendor Pie Chart
function initVendorPie(active,inactive,overdue){
    const ctx = document.getElementById('vendorPieChart').getContext('2d');
    if(vendorPieChart) vendorPieChart.destroy();
    vendorPieChart = new Chart(ctx,{
        type:'pie',
        data:{
            labels:['Active','Inactive','Overdue'],
            datasets:[{
                data:[active,inactive,overdue],
                backgroundColor:['#28a745','#6c757d','#dc3545']
            }]
        },
        options:{responsive:true}
    });
}

// Fetch initial charts data
function fetchCharts(){
    // Payments over time
    $.getJSON('collector_api.php?chart=1',function(json){
        initPaymentsChart(json.labels,json.data);
    });
    // Vendor status pie
    $.getJSON('collector_api.php?vendor_status=1',function(json){
        initVendorPie(json.active,json.inactive,json.overdue);
    });
}
fetchCharts();

// --- AJAX Payment Submission ---
$('#paymentForm').on('submit',function(e){
    e.preventDefault();
    $.post('collector_api.php',$(this).serialize(),function(res){
        if(res.success){
            $('#paymentMsg').html('<div class="alert alert-success">Payment recorded!</div>');
            window.open('collector_receipt.php?id='+res.payment_id,'_blank');

            // Refresh recent payments
            $.get('collector_api.php?recent_payments=1',function(html){
                $('#paymentsTable tbody').html(html);
            });

            // Refresh total collected
            $.getJSON('collector_api.php?total_collected=1',function(json){
                $('#totalCollected').text('₱'+parseFloat(json.total).toFixed(2));
            });

            // Refresh charts
            fetchCharts();

            $('#paymentForm')[0].reset();
            updateTotal();
        }else{
            $('#paymentMsg').html('<div class="alert alert-danger">'+res.message+'</div>');
        }
    },'json');
});
</script>
</body>
</html>
