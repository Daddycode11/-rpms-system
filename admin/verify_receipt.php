
<?php

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$result = null;
if(isset($_GET['code'])){
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE receipt_code=?");
    $stmt->execute([$_GET['code']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Verify Receipt</title>
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container py-5 text-center">
<h4>QR Receipt Verification</h4>

<?php if($result): ?>
<div class="alert alert-success">
✅ Valid Receipt<br>
Amount: ₱<?= number_format($result['amount_paid'],2) ?>
</div>
<?php elseif(isset($_GET['code'])): ?>
<div class="alert alert-danger">❌ Invalid Receipt</div>
<?php endif ?>
</div>
</body>
</html>
 