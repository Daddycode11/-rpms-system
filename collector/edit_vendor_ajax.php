<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role']!=='collector') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

$vendor_id = $_GET['id'] ?? null;
if (!$vendor_id) { echo json_encode(['success'=>false,'message'=>'Vendor ID missing']); exit; }

$vendor_name = $_POST['vendor_name'] ?? '';
$stall_number = $_POST['stall_number'] ?? '';
$status = $_POST['status'] ?? 'inactive';

if (!$vendor_name || !$stall_number) {
    echo json_encode(['success'=>false,'message'=>'All fields are required']); exit;
}

$update = $pdo->prepare("UPDATE vendors SET vendor_name=?, stall_number=?, status=? WHERE id=?");
$update->execute([$vendor_name,$stall_number,$status,$vendor_id]);

echo json_encode(['success'=>true,'vendor_name'=>$vendor_name,'stall_number'=>$stall_number,'status'=>$status]);
