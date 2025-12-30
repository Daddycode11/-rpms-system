<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$data = $pdo->query("
SELECT CONCAT(u.first_name,' ',u.last_name) AS name,
SUM(p.amount_paid) AS total
FROM payments p
JOIN users u ON u.id=p.collector_id
GROUP BY p.collector_id
")->fetchAll(PDO::FETCH_ASSOC);
?>
