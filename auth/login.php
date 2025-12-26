<?php
require_once '../config/database.php';
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$error = "";
$rememberEmail = $_COOKIE['remember_email'] ?? "";
$roleFromUrl = $_GET['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $roleSelected = $_POST['role'];

    // Remember Me
    if (isset($_POST['remember'])) {
        setcookie("remember_email", $email, time() + (86400*30), "/");
    } else {
        setcookie("remember_email", "", time() - 3600, "/");
    }

    // Fetch user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Guaranteed Admin
    if ($email === 'admin@rpms.com') {
        if (!$user) {
            $hashed = password_hash('Admin123!', PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (role, first_name, last_name, email, password, status, created_at, two_factor_enabled) 
                                     VALUES ('admin','System','Admin','admin@rpms.com', ?, 'active', NOW(), 1)");
            $insert->execute([$hashed]);
            $user = [
                'id' => $pdo->lastInsertId(),
                'role' => 'admin',
                'first_name' => 'System',
                'last_name' => 'Admin',
                'password' => $hashed,
                'status' => 'active',
                'two_factor_enabled' => 1
            ];
            $password = 'Admin123!';
        }
    }

    // Verify credentials
    if ($user && password_verify($password, $user['password']) && $user['status']==='active' && $user['role']===$roleSelected) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'] ?? '';

        // Admin OTP
        if ($user['role'] === 'admin' && $user['two_factor_enabled']) {
            $otp = rand(100000, 999999);
            $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            // Save OTP
            $stmt = $pdo->prepare("UPDATE users SET otp_code=?, otp_expires=? WHERE id=?");
            $stmt->execute([$otp, $expires, $user['id']]);

            // Send OTP via PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'rpmsa00@gmail.com'; // Your Gmail
                $mail->Password   = 'your-app-password'; // Gmail App Password
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom('rpmsa00@gmail.com', 'RPMS Admin');
                $mail->addAddress($user['email']);

                $mail->isHTML(false);
                $mail->Subject = 'Your RPMS Admin OTP';
                $mail->Body    = "Your OTP code is: $otp. Expires in 5 minutes.";

                $mail->send();
            } catch (Exception $e) {
                $error = "Mailer Error: {$mail->ErrorInfo}";
            }

            header("Location: otp_verify.php");
            exit;
        }

        // Non-admin
        switch ($user['role']) {
            case 'collector':
                header("Location: ../collector/dashboard.php");
                break;
            case 'vendor':
                header("Location: ../vendor/dashboard.php");
                break;
        }
        exit;
    } else {
        $error = "Invalid email, password, or role";
    }
}
?>

<!-- HTML LOGIN FORM -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | RPMS</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --primary-green:#28a745; --dark-green:#1e7e34; }
body { font-family:'Poppins',sans-serif; background:#f4f6f9; min-height:100vh; }
.login-wrapper { max-width:900px; background:#fff; border-radius:20px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,.15); }
.login-left { background:url('../assets/images/market-bg.png') center/cover no-repeat; position:relative; color:#fff; }
.login-left::after { content:""; position:absolute; inset:0; background:rgba(0,0,0,.55); }
.login-left-content { position:relative; z-index:2; padding:3rem; text-align:center; }
.login-right { padding:3rem; }
.btn-custom { background:var(--primary-green); color:#fff; }
.btn-custom:hover { background:var(--dark-green); }
.password-toggle { cursor:pointer; }
@media(max-width:768px){ .login-left{display:none;} .login-right{padding:2rem;} }
</style>
</head>
<body>
<div class="container min-vh-100 d-flex justify-content-center align-items-center px-3">
<div class="login-wrapper row w-100">

    <div class="col-md-6 login-left d-flex align-items-center">
        <div class="login-left-content w-100">
            <img src="../assets/images/logo.png" height="80" class="mb-3">
            <h3 class="fw-bold">RPMS</h3>
            <p class="mb-0">Rental Payment Management System</p>
            <small>San Jose Public Market</small>
        </div>
    </div>

    <div class="col-md-6 login-right position-relative">
        <a href="../index.php" class="btn btn-light btn-sm position-absolute top-0 end-0 mt-2 me-2">🏠 Home</a>
        <h4 class="fw-bold text-success mb-1">Welcome Back</h4>
        <p class="text-muted mb-4">Log in to your account</p>

        <form method="post" id="loginForm">
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($rememberEmail) ?>" required>
            </div>

            <div class="mb-3">
                <label>Role</label>
                <select name="role" class="form-select" required>
                    <option value="admin" <?= $roleFromUrl==='admin'?'selected':'' ?>>Admin</option>
                    <option value="collector" <?= $roleFromUrl==='collector'?'selected':'' ?>>Collector</option>
                    <option value="vendor" <?= $roleFromUrl==='vendor'?'selected':'' ?>>Vendor</option>
                </select>
            </div>

            <div class="mb-3">
                <label>Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control" required>
                    <span class="input-group-text password-toggle" onclick="togglePassword()">👁</span>
                </div>
            </div>

            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" <?= $rememberEmail?'checked':''?>>
                    <label class="form-check-label small">Remember me</label>
                </div>
            </div>

            <button type="submit" class="btn btn-custom w-100 fw-semibold" id="loginBtn">
                <span id="btnText">Log In</span>
                <span id="btnSpinner" class="spinner-border spinner-border-sm d-none"></span>
            </button>

            <?php if ($error): ?>
                <div class="alert alert-danger mt-3 text-center"><?= $error ?></div>
            <?php endif; ?>
        </form>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword() {
    const pass = document.getElementById("password");
    pass.type = pass.type==="password"?"text":"password";
}

document.getElementById("loginForm").addEventListener("submit", ()=>{
    document.getElementById("btnText").classList.add("d-none");
    document.getElementById("btnSpinner").classList.remove("d-none");
    document.getElementById("loginBtn").disabled = true;
});
</script>
</body>
</html>
