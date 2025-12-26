<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$vendor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $discount = floatval($_POST['discount'] ?? 0);
    $penalty = floatval($_POST['penalty'] ?? 0);

    try {
        $stmt = $pdo->prepare("INSERT INTO payments (vendor_id, amount_paid, discount, penalty, payment_date) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$vendor_id, $amount_paid, $discount, $penalty]);

        $lastId = $pdo->lastInsertId();

        echo json_encode(['success' => true, 'payment_id' => $lastId]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
