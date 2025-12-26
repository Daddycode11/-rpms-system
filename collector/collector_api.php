<?php
session_start();
require_once '../config/database.php';

// Ensure collector is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$collector_id = $_SESSION['user_id'];
header('Content-Type: application/json');

// --- SUBMIT NEW PAYMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stall_number'])) {

    $stall_number = trim($_POST['stall_number']);
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $discount = floatval($_POST['discount'] ?? 0);
    $penalty = floatval($_POST['penalty'] ?? 0);

    if (!$stall_number || $amount_paid <= 0) {
        echo json_encode(['success'=>false,'message'=>'Stall number and amount are required']);
        exit;
    }

    // Fetch vendor by stall number
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE stall_number = ? LIMIT 1");
    $stmt->execute([$stall_number]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        echo json_encode(['success'=>false,'message'=>'Vendor not found']);
        exit;
    }

    // Insert payment
    $stmt = $pdo->prepare("INSERT INTO payments (vendor_id, collector_id, amount_paid, discount, penalty, payment_date) VALUES (?,?,?,?,?,NOW())");
    $stmt->execute([$vendor['id'], $collector_id, $amount_paid, $discount, $penalty]);

    $payment_id = $pdo->lastInsertId();

    echo json_encode(['success'=>true,'payment_id'=>$payment_id]);
    exit;
}

// --- GET RECENT PAYMENTS TABLE HTML ---
if (isset($_GET['recent_payments'])) {
    $stmt = $pdo->prepare("
        SELECT p.*, v.stall_number, v.monthly_rent, s.section_name,
               u.first_name AS vendor_first, u.last_name AS vendor_last
        FROM payments p
        JOIN vendors v ON v.id = p.vendor_id
        JOIN users u ON u.id = v.user_id
        LEFT JOIN sections s ON s.id = v.section_id
        WHERE p.collector_id = ?
        ORDER BY p.payment_date DESC
        LIMIT 10
    ");
    $stmt->execute([$collector_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_start();
    foreach ($payments as $p) {
        $total = $p['amount_paid'] - $p['discount'] + $p['penalty'];
        echo "<tr>
                <td>".htmlspecialchars($p['payment_date'])."</td>
                <td>".htmlspecialchars($p['vendor_first'].' '.$p['vendor_last'])."</td>
                <td>".htmlspecialchars($p['stall_number'])."</td>
                <td>₱".number_format($p['amount_paid'],2)."</td>
                <td>₱".number_format($p['discount'],2)."</td>
                <td>₱".number_format($p['penalty'],2)."</td>
                <td>₱".number_format($total,2)."</td>
                <td><a href='collector_receipt.php?id={$p['id']}' target='_blank' class='btn btn-sm btn-primary'>Print</a></td>
              </tr>";
    }
    echo ob_get_clean();
    exit;
}

// --- GET TOTAL COLLECTED ---
if (isset($_GET['total_collected'])) {
    $stmt = $pdo->prepare("
        SELECT SUM(amount_paid - discount + penalty) AS total
        FROM payments
        WHERE collector_id = ?
    ");
    $stmt->execute([$collector_id]);
    $total = $stmt->fetchColumn() ?: 0;
    echo json_encode(['total'=>$total]);
    exit;
}

// Default fallback
echo json_encode(['success'=>false,'message'=>'Invalid request']);
exit;
