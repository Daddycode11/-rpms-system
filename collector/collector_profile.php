<?php
session_start();
require_once '../config/database.php';

// --- Collector role check ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

$collector_id = $_SESSION['user_id'];

// --- Handle AJAX update request ---
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!$first_name || !$last_name || !$email) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?");
        $updated = $stmt->execute([$first_name, $last_name, $email, $phone, $collector_id]);

        if ($updated) {
            $_SESSION['first_name'] = $first_name;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
        }
        exit;
    }

    if ($_POST['action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
            exit;
        }

        if ($new !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
            exit;
        }

        // Fetch current password hash
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$collector_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }

        // Hash new password and update
        $new_hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([$new_hashed, $collector_id]);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
        exit;
    }
}

// --- Fetch collector info ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmt->execute([$collector_id]);
$collector = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Collector Payment Profile | RPMS</title>
<?php include __DIR__ . '/includes/favicon.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
body { font-family: 'Poppins', sans-serif; background:#f4f6f9; }
.card { border-radius:12px; }
.table th, .table td { vertical-align: middle; }
</style>
</head>
<body>

<?php include __DIR__ . '/collector_navbar.php'; ?>

<div class="container mt-5">
    <h2 class="mb-4">My Profile</h2>
    <!-- Profile Form -->
    <div class="card shadow-sm p-4">
        <h3 class="mb-4 text-center">My Profile</h3>
        <form id="profileForm">
            <div class="mb-3">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($collector['first_name']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($collector['last_name']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($collector['email']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($collector['phone'] ?? '') ?>">
            </div>
            <div id="profileMsg"></div>
            <button type="submit" class="btn btn-success w-100">Update Profile</button>
        </form>
    </div>

    <!-- Change Password Form -->
    <div class="card shadow-sm p-4">
        <h3 class="mb-4 text-center">Change Password</h3>
        <form id="passwordForm">
            <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <div id="passwordMsg"></div>
            <button type="submit" class="btn btn-warning w-100">Update Password</button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Profile update AJAX
$('#profileForm').on('submit', function(e){
    e.preventDefault();
    $('#profileMsg').html('');
    const data = $(this).serialize() + '&action=update_profile';
    $.post('collector_profile.php', data, function(res){
        $('#profileMsg').html('<div class="alert alert-'+(res.success?'success':'danger')+'">'+res.message+'</div>');
    }, 'json');
});

// Password change AJAX
$('#passwordForm').on('submit', function(e){
    e.preventDefault();
    $('#passwordMsg').html('');
    const data = $(this).serialize() + '&action=change_password';
    $.post('collector_profile.php', data, function(res){
        $('#passwordMsg').html('<div class="alert alert-'+(res.success?'success':'danger')+'">'+res.message+'</div>');
        if(res.success){
            $('#passwordForm')[0].reset();
        }
    }, 'json');
});
</script>

</body>
</html>
