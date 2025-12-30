<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// ----------------------
// Role check
// ----------------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

// ----------------------
// Check POST
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendor_id = $_POST['id'] ?? null;

    if ($vendor_id) {
        $delete = $pdo->prepare("DELETE FROM vendors WHERE id=?");
        $delete->execute([$vendor_id]);
    }
}

header("Location: vendors_list.php");
exit;
