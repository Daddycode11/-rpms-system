<?php
session_start();
require_once '../config/database.php';

// --- AUTH CHECK ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = $error = "";

// --- FETCH ADMIN DATA ---
$stmt = $pdo->prepare("
    SELECT first_name, last_name, email, avatar, two_factor_enabled 
    FROM users 
    WHERE id = ?
");
$stmt->execute([$user_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("Admin account not found.");
}

// --- HANDLE PROFILE UPDATE ---
if (isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $twoFA = isset($_POST['enable_2fa']) ? 1 : 0;

    $avatarName = $admin['avatar'];

    // --- AVATAR UPLOAD FIX ---
    if (!empty($_FILES['avatar']['name'])) {
        $uploadDir = "../uploads/avatars/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $avatarName = 'admin_'.$user_id.'.'.$ext;

        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir.$avatarName)) {
            $error .= "Failed to upload avatar.<br>";
        }
    }

    $stmt = $pdo->prepare("
        UPDATE users 
        SET first_name=?, last_name=?, email=?, avatar=?, two_factor_enabled=?
        WHERE id=?
    ");
    $stmt->execute([$first_name, $last_name, $email, $avatarName, $twoFA, $user_id]);

    // Log activity
    $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?,?)")
        ->execute([$user_id, 'Profile updated']);

    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    $success .= "Profile updated successfully.<br>";
}

// --- HANDLE PASSWORD CHANGE ---
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $hashed = $stmt->fetchColumn();

    if (!password_verify($current, $hashed)) {
        $error .= "Current password is incorrect.<br>";
    } elseif ($new !== $confirm) {
        $error .= "New passwords do not match.<br>";
    } elseif (strlen($new) < 6) {
        $error .= "Password must be at least 6 characters.<br>";
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([$newHash, $user_id]);

        // Log activity
        $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?,?)")
            ->execute([$user_id, 'Password changed']);

        $success .= "Password changed successfully.<br>";
    }
}

// --- FETCH RECENT ACTIVITY LOGS (Prepared Statement Fix) ---
try {
    $stmt = $pdo->prepare("
        SELECT action, created_at 
        FROM activity_logs 
        WHERE user_id = ?
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logs = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile</title>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/includes/favicon.php'; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container py-5">
    <h3 class="mb-4">Admin Profile</h3>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- PROFILE INFO -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Profile Information</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">

                        <div class="mb-3 text-center">
                            <img src="<?= $admin['avatar'] ? '../uploads/avatars/'.$admin['avatar'] : '../assets/avatar.png' ?>"
                                 class="rounded-circle mb-2"
                                 width="120" height="120">
                            <input type="file" name="avatar" class="form-control mt-2">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" 
                                   value="<?= htmlspecialchars($admin['first_name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" 
                                   value="<?= htmlspecialchars($admin['last_name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($admin['email']) ?>" required>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="enable_2fa" 
                                <?= $admin['two_factor_enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label">Enable 2FA (Email OTP)</label>
                        </div>

                        <button name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- CHANGE PASSWORD -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Change Password</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                            <div class="progress mt-1" style="height:6px;">
                                <div id="strengthBar" class="progress-bar"></div>
                            </div>
                            <small id="strengthText"></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>

                        <button name="change_password" class="btn btn-warning">Change Password</button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- ACTIVITY LOGS -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Recent Activity</div>
                <div class="card-body">
                    <?php if (!empty($logs)): ?>
                        <ul class="list-group list-group-flush small">
                            <?php foreach($logs as $log): ?>
                                <li class="list-group-item">
                                    <?= htmlspecialchars($log['action']) ?><br>
                                    <small class="text-muted"><?= date('M d, Y h:i A', strtotime($log['created_at'])) ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-muted text-center py-2">No recent activity</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- PASSWORD STRENGTH -->
<script>
const pass = document.querySelector('[name="new_password"]');
const bar = document.getElementById('strengthBar');
const text = document.getElementById('strengthText');

pass.addEventListener('input', () => {
    let v = pass.value;
    let s = 0;
    if (v.length >= 6) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    bar.style.width = (s*25)+'%';
    bar.className = 'progress-bar bg-'+(s<2?'danger':s<4?'warning':'success');
    text.textContent = ['Weak','Fair','Good','Strong'][s-1]||'';
});
</script>

</body>
</html>
