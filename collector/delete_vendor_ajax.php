<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role']!=='collector') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

$vendor_id = $_POST['id'] ?? null;
if (!$vendor_id) { echo json_encode(['success'=>false,'message'=>'Vendor ID missing']); exit; }

$delete = $pdo->prepare("DELETE FROM vendors WHERE id=?");
$delete->execute([$vendor_id]);

echo json_encode(['success'=>true]);
