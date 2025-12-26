<?php
require_once '../config/database.php';
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

// --- ROLE CHECK: Only Admin ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?role=admin");
    exit;
}

$error = "";
$info = "";
$otp_success = false;

// Function to send OTP via PHPMailer
function sendOTP($userId, $pdo) {
    $otp = rand(100000, 999999);
    $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    $stmt = $pdo->prepare("UPDATE users SET otp_code=?, otp_expires=? WHERE id=?");
    $stmt->execute([$otp, $expires, $userId]);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'eutech253@gmail.com';
        $mail->Password = 'zryiwafboroqoknh';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->setFrom('eutech253@gmail.com', 'RPMS Admin');
        $mail->addAddress('rpmsa00@gmail.com');

        $mail->isHTML(false);
        $mail->Subject = 'Your RPMS Admin OTP';
        $mail->Body    = "Your OTP code is: $otp. Expires in 5 minutes.";

        $mail->send();
        return "OTP sent to admin email.";
    } catch (Exception $e) {
        return "Mailer Error: {$mail->ErrorInfo}";
    }
}

// --- Handle OTP submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        $info = sendOTP($_SESSION['user_id'], $pdo);
    } else {
        $inputOtp = $_POST['otp'] ?? '';
        $stmt = $pdo->prepare("SELECT otp_code, otp_expires FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (new DateTime() > new DateTime($user['otp_expires'])) {
                $error = "OTP expired. A new OTP has been sent.";
                $info = sendOTP($_SESSION['user_id'], $pdo);
            } elseif ($inputOtp === $user['otp_code']) {
                $_SESSION['otp_verified'] = true;
                $otp_success = true;
            } else {
                $error = "Invalid OTP. Try again.";
            }
        }
    }
} else {
    $stmt = $pdo->prepare("SELECT otp_code, otp_expires FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($user['otp_code']) || new DateTime() > new DateTime($user['otp_expires'])) {
        $info = sendOTP($_SESSION['user_id'], $pdo);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin OTP Verification</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body {
    font-family: 'Poppins', sans-serif;
    background: url("assets/images/market-bg.png")  no-repeat center center;
    background-size: cover;
}
.bg-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255,255,255,0.85); /* low opacity overlay */
    z-index: -1;
}
</style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
<div class="bg-overlay"></div>
<div class="card p-4" style="width:350px; z-index:1;">
    <h4 class="text-center text-success">Enter OTP</h4>
    <p class="text-center text-muted">Check your email for the 6-digit code</p>

    <?php if($info): ?><div class="alert alert-info text-center"><?= $info ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger text-center"><?= $error ?></div><?php endif; ?>

    <form method="post">
        <input type="text" name="otp" class="form-control mb-3" placeholder="Enter OTP" required>
        <button type="submit" class="btn btn-success w-100">Verify</button>
    </form>

    <form method="post" class="mt-2">
        <button type="submit" name="resend" class="btn btn-secondary w-100">Resend OTP</button>
    </form>
</div>

<?php if ($otp_success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'OTP Verified!',
    text: 'Redirecting to Admin Dashboard...',
    timer: 2000,
    showConfirmButton: false
}).then(() => {
    window.location.href = '../admin/dashboard.php';
});
</script>
<?php endif; ?>

<?php if ($error && empty($otp_success)): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Oops!',
    text: '<?= addslashes($error) ?>'
});
</script>
<?php endif; ?>

</body>
</html>