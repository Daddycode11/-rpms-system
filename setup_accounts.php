<?php
require_once __DIR__ . '/config/database.php'; // Fixed path
session_start();

$accounts = [
    ['role'=>'admin', 'first_name'=>'System', 'last_name'=>'Admin', 'email'=>'rpmsa00@gmail.com', 'password'=>'Admin123!'],
    ['role'=>'collector', 'first_name'=>'Default', 'last_name'=>'Collector', 'email'=>'collector@rpms.com', 'password'=>'Collector123!']
];

foreach ($accounts as $acc) {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$acc['email']]);
    if ($stmt->rowCount() === 0) {
        $hashed = password_hash($acc['password'], PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO users (role, first_name, last_name, email, password, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
        $insert->execute([$acc['role'], $acc['first_name'], $acc['last_name'], $acc['email'], $hashed]);
        echo "Created account: {$acc['email']} ({$acc['role']})<br>";
    } else {
        echo "Account already exists: {$acc['email']}<br>";
    }
}

echo "Setup complete!";
?>
