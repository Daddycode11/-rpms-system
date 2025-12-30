<?php
session_start();
require_once '../config/database.php';

/* =========================
   ADMIN ACCESS CONTROL
========================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (empty($_SESSION['otp_verified'])) {
    header("Location: ../auth/otp_verify.php");
    exit;
}

/* =========================
   DASHBOARD DATA
========================= */
try {
    $total_vendors = (int)$pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
    $total_sections = (int)$pdo->query("SELECT COUNT(*) FROM sections")->fetchColumn();
    $total_collectors = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='collector'")->fetchColumn();
    $total_collection = (float)$pdo
        ->query("SELECT COALESCE(SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)),0) FROM payments")
        ->fetchColumn();

    $monthlyData = $pdo->query("
        SELECT DATE_FORMAT(paid_at,'%b') AS month,
               SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) AS total
        FROM payments
        GROUP BY MONTH(paid_at)
        ORDER BY MONTH(paid_at)
    ")->fetchAll(PDO::FETCH_ASSOC);

    $recentPayments = $pdo->query("
        SELECT p.paid_at AS payment_date,
               CONCAT(u.first_name,' ',u.last_name) AS vendor,
               p.amount_paid,
               COALESCE(p.status,'paid') AS status
        FROM payments p
        JOIN vendors v ON v.id = p.vendor_id
        JOIN users u ON u.id = v.user_id
        ORDER BY p.paid_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $topVendors = $pdo->query("
        SELECT CONCAT(u.first_name,' ',u.last_name) AS name,
               SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) AS total
        FROM payments p
        JOIN vendors v ON v.id = p.vendor_id
        JOIN users u ON u.id = v.user_id
        WHERE p.status='paid'
        GROUP BY v.id
        ORDER BY total DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $logs = $pdo->query("
        SELECT action, created_at
        FROM activity_logs
        ORDER BY created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    $overdueCount = (int)$pdo->query("SELECT COUNT(*) FROM vendors WHERE status='overdue'")->fetchColumn();
    $bestDay = $pdo->query("
        SELECT DATE_FORMAT(paid_at,'%W')
        FROM payments
        GROUP BY DATE_FORMAT(paid_at,'%W')
        ORDER BY SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) DESC
        LIMIT 1
    ")->fetchColumn() ?: 'N/A';

    $topCollector = $pdo->query("
        SELECT CONCAT(u.first_name,' ',u.last_name)
        FROM payments p
        JOIN users u ON u.id = p.collector_id
        GROUP BY p.collector_id
        ORDER BY SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) DESC
        LIMIT 1
    ")->fetchColumn() ?: 'N/A';

    $avgDaily = (float)$pdo->query("
        SELECT AVG(total) FROM (
            SELECT SUM(amount_paid - COALESCE(discount,0) + COALESCE(penalty,0)) AS total
            FROM payments
            GROUP BY DATE(paid_at)
        ) t
    ")->fetchColumn();

    $vendorStatusCounts = $pdo->query("
        SELECT 
            SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid,
            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status='overdue' THEN 1 ELSE 0 END) AS overdue
        FROM vendors
    ")->fetch(PDO::FETCH_ASSOC);
    $vendorStatusCounts = array_values($vendorStatusCounts);

    $collectorPerformance = $pdo->query("
        SELECT CONCAT(u.first_name,' ',u.last_name) AS name,
               SUM(p.amount_paid - COALESCE(p.discount,0) + COALESCE(p.penalty,0)) AS total
        FROM payments p
        JOIN users u ON u.id = p.collector_id
        WHERE p.status='paid'
        GROUP BY p.collector_id
        ORDER BY total DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: ".$e->getMessage());
}

$annualTarget = 2000000;
$collectionProgress = $annualTarget > 0
    ? min(100, round(($total_collection / $annualTarget) * 100))
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | RPMS</title>
<?php include $_SERVER['DOCUMENT_ROOT'].'/rpms-system/includes/favicon.php'; ?>

<!-- BOOTSTRAP -->
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- POPPINS FONT -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { font-family:'Poppins',sans-serif; background:#f8f9fa; color:#495057; }
.page-header h2 { font-weight:600; }
.page-header p { color:#6c757d; }

.card { border:none; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.05); }
.stat-title { font-size:0.85rem; text-transform:uppercase; letter-spacing:0.5px; color:#6c757d; }
.stat-value { font-size:1.8rem; font-weight:600; }
.kpi-badge { display:inline-block; padding:3px 10px; font-size:0.75rem; font-weight:600; border-radius:12px; color:#fff; }
.bg-badge-success { background:#28a745; }
.bg-badge-warning { background:#ffc107; color:#212529; }
.bg-badge-danger { background:#dc3545; }

.table-sm th, .table-sm td { font-size:0.85rem; }
.list-group-item { border:none; padding:8px 15px; }
.list-group-item small { color:#6c757d; }
.progress { height:22px; border-radius:12px; overflow:hidden; }
.progress-bar { transition: width 1.5s; font-weight:600; }
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid py-4">

<!-- HEADER -->
<div class="page-header mb-4">
    <h2>Admin Dashboard</h2>
    <p class="text-muted">Overview of market operations and collections</p>
</div>

<!-- KPI CARDS - Dynamic & Interactive -->
<!-- KPI CARDS - Dynamic & Interactive -->
<div class="row g-4" id="kpi-cards">
    <!-- Total Vendors -->
    <div class="col-md-3">
        <div class="card text-white bg-primary p-3 position-relative">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title text-white">Total Vendors</div>
                    <div class="stat-value" id="total_vendors"><?= $total_vendors ?></div>
                </div>
                <div><i class="bi bi-people-fill fs-2"></i></div>
            </div>
            <div class="position-absolute top-0 end-0 mt-2 me-2 dropdown">
                <a href="#" class="text-white" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="vendors.php">View Details</a></li>
                    <li><a class="dropdown-item" href="#" onclick="updateKPI('vendors')">Refresh</a></li>
                </ul>
            </div>
            <small class="d-block mt-2 text-white-50">Number of active vendors</small>
        </div>
    </div>

    <!-- Total Sections -->
    <div class="col-md-3">
        <div class="card text-white bg-success p-3 position-relative">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title text-white">Total Sections</div>
                    <div class="stat-value" id="total_sections"><?= $total_sections ?></div>
                </div>
                <div><i class="bi bi-grid-1x2-fill fs-2"></i></div>
            </div>
            <div class="position-absolute top-0 end-0 mt-2 me-2 dropdown">
                <a href="#" class="text-white" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="sections.php">View Details</a></li>
                    <li><a class="dropdown-item" href="#" onclick="updateKPI('sections')">Refresh</a></li>
                </ul>
            </div>
            <small class="d-block mt-2 text-white-50">Sections in the market</small>
        </div>
    </div>

    <!-- Total Collectors -->
    <div class="col-md-3">
        <div class="card text-white bg-warning p-3 position-relative">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title text-white">Total Collectors</div>
                    <div class="stat-value" id="total_collectors"><?= $total_collectors ?></div>
                </div>
                <div><i class="bi bi-person-badge-fill fs-2"></i></div>
            </div>
            <div class="position-absolute top-0 end-0 mt-2 me-2 dropdown">
                <a href="#" class="text-white" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="collectors.php">View Details</a></li>
                    <li><a class="dropdown-item" href="#" onclick="updateKPI('collectors')">Refresh</a></li>
                </ul>
            </div>
            <small class="d-block mt-2 text-white-50">Collectors currently active</small>
        </div>
    </div>

    <!-- Total Collection -->
    <div class="col-md-3">
        <div class="card text-white bg-danger p-3 position-relative">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-title text-white">Total Collection</div>
                    <div class="stat-value" id="total_collection">₱<?= number_format($total_collection,2) ?></div>
                </div>
                <div><i class="bi bi-currency-dollar fs-2"></i></div>
            </div>
            <div class="position-absolute top-0 end-0 mt-2 me-2 dropdown">
                <a href="#" class="text-white" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="payments.php">View Details</a></li>
                    <li><a class="dropdown-item" href="#" onclick= "updateKPI('collection')">Refresh</a></li>
                </ul>
            </div>
            <small class="d-block mt-2 text-white-50">Total market collection</small>
        </div>
    </div>
</div>

<script>
// Example AJAX refresh function for KPI cards
function updateKPI(metric) {
    fetch('dashboard_kpi_ajax.php?metric=' + metric)
    .then(res => res.json())
    .then(data => {
        if(metric === 'vendors') document.getElementById('total_vendors').innerText = data.value;
        if(metric === 'sections') document.getElementById('total_sections').innerText = data.value;
        if(metric === 'collectors') document.getElementById('total_collectors').innerText = data.value;
        if(metric === 'collection') document.getElementById('total_collection').innerText = '₱' + parseFloat(data.value).toLocaleString('en-PH', {minimumFractionDigits:2});
    })
    .catch(err => console.error(err));
}
</script>


<!-- CHARTS + TOP VENDORS -->
<div class="row g-4 mt-3">
    <div class="col-md-8">
        <div class="card p-3">
            <h6>Monthly Collection</h6>
            <canvas id="monthlyChart" height="150"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3">
            <h6>Top Vendors</h6>
            <ul class="list-group list-group-flush">
                <?php foreach($topVendors as $v): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <?= htmlspecialchars($v['name']) ?>
                    <span>₱<?= number_format($v['total'],2) ?></span>
                </li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
</div>

<!-- RECENT PAYMENTS + LOGS -->
<div class="row g-4 mt-3">
    <div class="col-md-8">
        <div class="card p-3">
            <h6>Recent Payments</h6>
            <table class="table table-sm table-striped">
                <thead>
                    <tr><th>Date</th><th>Vendor</th><th>Amount</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach($recentPayments as $p): ?>
                    <tr>
                        <td><?= $p['payment_date'] ?></td>
                        <td><?= htmlspecialchars($p['vendor']) ?></td>
                        <td>₱<?= number_format($p['amount_paid'],2) ?></td>
                        <td>
                            <?php 
                                $statusColor = $p['status']=='paid'?'bg-badge-success':($p['status']=='pending'?'bg-badge-warning':'bg-badge-danger'); 
                            ?>
                            <span class="kpi-badge <?= $statusColor ?>"><?= ucfirst($p['status']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3">
            <h6>Activity Logs</h6>
            <ul class="list-group list-group-flush small-text">
                <?php foreach($logs as $l): ?>
                    <li class="list-group-item">
                        <?= htmlspecialchars($l['action']) ?><br>
                        <small><?= date('M d, Y h:i A',strtotime($l['created_at'])) ?></small>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
</div>

<!-- ADVANCED ANALYTICS -->
<div class="row g-4 mt-3">
    <div class="col-md-4">
        <div class="card p-3">
            <h6>Vendor Payment Status</h6>
            <canvas id="vendorStatusChart" height="180"></canvas>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card p-3">
            <h6>Collector Performance</h6>
            <canvas id="collectorChart" height="180"></canvas>
        </div>
    </div>
</div>

<!-- PROGRESS + INSIGHTS -->
<div class="row g-4 mt-3">
    <div class="col-md-8">
        <div class="card p-3">
            <h6>Annual Collection Progress</h6>
            <div class="progress mb-2">
                <div class="progress-bar bg-success" style="width:0">
                    <?= $collectionProgress ?>%
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3">
            <h6>System Insights</h6>
            <ul class="small mb-0">
                <li><?= $overdueCount ?> overdue vendors</li>
                <li>Best day: <?= $bestDay ?></li>
                <li>Top collector: <?= $topCollector ?></li>
                <li>Avg daily: ₱<?= number_format($avgDaily,2) ?></li>
            </ul>
        </div>
    </div>
</div>

</div>

<script>
// Animate progress bar
setTimeout(() => { document.querySelector('.progress-bar').style.width = '<?= $collectionProgress ?>%'; }, 300);

// Monthly Collection Chart
new Chart(document.getElementById('monthlyChart'), {
    type:'line',
    data:{
        labels:<?= json_encode(array_column($monthlyData,'month')) ?>,
        datasets:[{
            label:'Collection',
            data:<?= json_encode(array_column($monthlyData,'total')) ?>,
            backgroundColor:'rgba(13,110,253,0.2)',
            borderColor:'#0d6efd',
            fill:true,
            tension:0.3,
            pointRadius:5
        }]
    },
    options:{plugins:{legend:{display:true}}, scales:{y:{beginAtZero:true}}}
});

// Vendor Status Chart
new Chart(document.getElementById('vendorStatusChart'),{
    type:'doughnut',
    data:{
        labels:['Paid','Pending','Overdue'],
        datasets:[{data:<?= json_encode($vendorStatusCounts) ?>, backgroundColor:['#198754','#ffc107','#dc3545']}]
    },
    options:{plugins:{legend:{position:'bottom'}}}
});

// Collector Performance Chart
new Chart(document.getElementById('collectorChart'),{
    type:'bar',
    data:{
        labels:<?= json_encode(array_column($collectorPerformance,'name')) ?>,
        datasets:[{
            label:'Total Collection',
            data:<?= json_encode(array_column($collectorPerformance,'total')) ?>,
            backgroundColor:'#0d6efd'
        }]
    },
    options:{plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
});
</script>
</body>
</html>
