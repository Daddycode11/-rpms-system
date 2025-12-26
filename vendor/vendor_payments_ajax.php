<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    http_response_code(403);
    exit;
}

$vendor_id = $_SESSION['user_id'];

$payments = $pdo->prepare("SELECT * FROM payments WHERE vendor_id = ? ORDER BY payment_date DESC");
$payments->execute([$vendor_id]);
$payments = $payments->fetchAll(PDO::FETCH_ASSOC);

foreach ($payments as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['payment_date']); ?></td>
    <td>₱<?= number_format($p['amount_paid'],2); ?></td>
    <td>₱<?= number_format($p['discount'],2); ?></td>
    <td>₱<?= number_format($p['penalty'],2); ?></td>
    <td>₱<?= number_format($p['amount_paid'] - $p['discount'] + $p['penalty'],2); ?></td>
    <td><a href="vendor_receipt.php?id=<?= $p['id']; ?>" target="_blank" class="btn btn-sm btn-primary">Print</a></td>
</tr>
<?php endforeach; ?>
