<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $vendor_id = $_POST['vendor_id'];
    $amount = $_POST['amount_paid'];
    $discount = $_POST['discount'];
    $penalty = $_POST['penalty'];
    $collector_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        INSERT INTO payments 
        (vendor_id, collector_id, amount_paid, discount, penalty, payment_date, status)
        VALUES (?, ?, ?, ?, ?, CURDATE(), 'paid')
    ");
    $stmt->execute([$vendor_id, $collector_id, $amount, $discount, $penalty]);

    header("Location: print_receipt.php?id=" . $pdo->lastInsertId());
    exit;
}

// Vendors list
$vendors = $pdo->query("
    SELECT v.id, CONCAT(u.first_name,' ',u.last_name) AS name, v.stall_number
    FROM vendors v
    JOIN users u ON u.id = v.user_id
")->fetchAll(PDO::FETCH_ASSOC);
?>
