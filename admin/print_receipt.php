<?php
require_once '../config/database.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("
    SELECT p.*, CONCAT(u.first_name,' ',u.last_name) AS vendor, v.stall_number
    FROM payments p
    JOIN vendors v ON v.id = p.vendor_id
    JOIN users u ON u.id = v.user_id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<body onload="window.print()">
<h3>SAN JOSE PUBLIC MARKET</h3>
<hr>
Vendor: <?= $r['vendor'] ?><br>
Stall: <?= $r['stall_number'] ?><br>
Amount: ₱<?= $r['amount_paid'] ?><br>
Discount: ₱<?= $r['discount'] ?><br>
Penalty: ₱<?= $r['penalty'] ?><br>
<hr>
Total: ₱<?= $r['amount_paid'] - $r['discount'] + $r['penalty'] ?><br>
Date: <?= $r['payment_date'] ?>
</body>
</html>
