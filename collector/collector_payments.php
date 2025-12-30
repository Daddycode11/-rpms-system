<?php
session_start();
require_once '../config/database.php';

// --- Collector role check ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

// Handle AJAX request for vendor info by stall number
if (isset($_GET['stall_number'])) {
    $stall_number = $_GET['stall_number'];

    $stmt = $pdo->prepare("
        SELECT v.*, u.first_name, u.last_name, s.section_name
        FROM vendors v
        JOIN users u ON u.id = v.user_id
        LEFT JOIN sections s ON s.id = v.section_id
        WHERE v.stall_number = ? 
        LIMIT 1
    ");
    $stmt->execute([$stall_number]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vendor) {
        echo json_encode(['success' => true, 'vendor' => $vendor]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Vendor not found']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Collector Payment | RPMS</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
body { font-family: 'Poppins', sans-serif; background:#f4f6f9; }
.card { border-radius:12px; }
.table th, .table td { vertical-align: middle; }
</style>
</head>
<body>

<?php include __DIR__ . '/collector_navbar.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4">Collection Payment Management</h2>

    <!-- Search by Stall Number -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="input-group">
                <input type="text" id="stallInput" class="form-control" placeholder="Enter Stall Number">
                <button id="searchBtn" class="btn btn-success">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
            <div id="vendorInfo" class="mt-3"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script>
$('#searchBtn').on('click', function(){
    let stallNumber = $('#stallInput').val().trim();
    if(!stallNumber) {
        alert('Please enter a stall number');
        return;
    }

    $.getJSON('collector_payments.php', { stall_number: stallNumber }, function(res){
        if(res.success) {
            let v = res.vendor;
            let html = `
                <div class="card p-3">
                    <h5>Vendor Info</h5>
                    <p><strong>Name:</strong> ${v.first_name} ${v.last_name}</p>
                    <p><strong>Stall #:</strong> ${v.stall_number}</p>
                    <p><strong>Section:</strong> ${v.section_name ?? 'N/A'}</p>
                    <p><strong>Monthly Rent:</strong> ₱${parseFloat(v.monthly_rent).toFixed(2)}</p>
                </div>
            `;
            $('#vendorInfo').html(html);
        } else {
            $('#vendorInfo').html('<div class="alert alert-danger">'+res.message+'</div>');
        }
    });
});
</script>
</body>
</html>
