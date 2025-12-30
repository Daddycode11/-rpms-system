<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$logs = $pdo->query("
SELECT * FROM audit_logs
ORDER BY created_at DESC
LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);
?>
