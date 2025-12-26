<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(['success'=>false,'message'=>'Invalid request']);
    exit;
}

$first = trim($_POST['first_name']);
$last = trim($_POST['last_name']);
$email = trim($_POST['email']);
$password = $_POST['password'];

// Check if email exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
$stmt->execute([$email]);
if($stmt->rowCount() > 0){
    echo json_encode(['success'=>false,'message'=>'Email already registered']);
    exit;
}

// Hash password
$hash = password_hash($password, PASSWORD_DEFAULT);

// Insert collector account
$stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?,?,?,?,?)");
if($stmt->execute([$first, $last, $email, $hash, 'collector'])){
    echo json_encode(['success'=>true,'message'=>'Collector account registered successfully!']);
} else {
    echo json_encode(['success'=>false,'message'=>'Registration failed. Try again.']);
}
