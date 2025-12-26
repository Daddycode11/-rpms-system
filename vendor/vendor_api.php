<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    exit(json_encode(['error' => 'Unauthorized']));
}

// --- Get vendor ID ---
$stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vendor) exit(json_encode(['error'=>'Vendor not found']));
$vendor_id = $vendor['id'];

// --- Add payment ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount_paid = floatval($_POST['amount_paid']);
    $discount = floatval($_POST['discount']);
    $penalty = floatval($_POST['penalty']);

    $stmt = $pdo->prepare("INSERT INTO payments (vendor_id, amount_paid, discount, penalty, payment_date) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$vendor_id, $amount_paid, $discount, $penalty]);
    $payment_id = $pdo->lastInsertId();

    echo json_encode(['payment_id'=>$payment_id]);
    exit;
}

// --- Get payments table HTML for AJAX ---
if (isset($_GET['action']) && $_GET['action'] === 'get_payments') {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE vendor_id=? ORDER BY payment_date ASC");
    $stmt->execute([$vendor_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($payments as $p){
        $total = $p['amount_paid'] - $p['discount'] + $p['penalty'];
        echo "<tr>
            <td>".htmlspecialchars($p['payment_date'])."</td>
            <td>₱".number_format($p['amount_paid'],2)."</td>
            <td>₱".number_format($p['discount'],2)."</td>
            <td>₱".number_format($p['penalty'],2)."</td>
            <td>₱".number_format($total,2)."</td>
            <td><a href='vendor_receipt.php?id={$p['id']}' target='_blank' class='btn btn-sm btn-primary'>Print</a></td>
        </tr>";
    }
    exit;
}

// --- Get outstanding balance and latest payment for chart ---
if (isset($_GET['action']) && $_GET['action'] === 'get_balance') {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE vendor_id=? ORDER BY payment_date ASC");
    $stmt->execute([$vendor_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT monthly_rent FROM vendors WHERE id=?");
    $stmt->execute([$vendor_id]);
    $monthly_rent = floatval($stmt->fetchColumn());

    $total_paid = 0;
    $latest_payment = 0;
    $latest_date = '';
    foreach($payments as $p){
        $amount = $p['amount_paid'] - $p['discount'] + $p['penalty'];
        $total_paid += $amount;
        $latest_payment = $amount;
        $latest_date = $p['payment_date'];
    }
    $outstanding = max(0, $monthly_rent - $total_paid);
    echo json_encode(['outstanding'=>$outstanding,'latest_payment'=>$latest_payment,'latest_date'=>$latest_date]);
    exit;
}
