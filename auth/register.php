<?php
require_once '../config/database.php';
session_start();

$error = "";
$success = "";

// Fixed role
$role = 'collector';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $error = "Email is already registered.";
    } else {
        // Hash password
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // Insert user as collector
        $insert = $pdo->prepare("INSERT INTO users (role, first_name, last_name, email, password, status, created_at)
                                 VALUES (?, ?, ?, ?, ?, 'active', NOW())");
        $insert->execute([$role, $first_name, $last_name, $email, $hashed]);

        $success = "Collector registration successful! You can now log in.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Collector | RPMS</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
body { font-family: 'Poppins', sans-serif; background:#f4f6f9; }
.register-wrapper { max-width:500px; margin:60px auto; background:#fff; padding:2rem; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,.1); }
.btn-custom { background:#28a745; color:#fff; }
.btn-custom:hover { background:#1e7e34; }
</style>
</head>
<body>

<div class="container">
    <div class="register-wrapper position-relative">
        <!-- Home button -->
        <a href="../index.php" class="position-absolute top-0 start-0 mt-3 ms-3 btn btn-light btn-sm">
            🏠 Home
        </a>
    <div class="register-wrapper">
        <h3 class="mb-3">Register as Collector</h3>

        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-custom w-100">Register</button>
        </form>

        <p class="mt-3 text-center">
            Already have an account? <a href="login.php?role=collector">Log in as Collector</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
