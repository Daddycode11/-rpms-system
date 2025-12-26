<?php
session_start();
require_once '../config/database.php';

// --- ROLE CHECK: Only Admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $settings = [];
}

// --- IMAGE UPLOAD HELPER ---
function uploadImage($file, $prefix) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) return 'SIZE_ERROR';

    $allowed = ['jpg','jpeg','png','webp','ico'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return null;

    $newName = uniqid($prefix.'_'.time().'_', true) . '.' . $ext;
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/rpms-system/uploads/settings/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) return $newName;
    return null;
}

// --- HANDLE SAVE SETTINGS ---
if (isset($_POST['save_settings'])) {
    $site_name = $_POST['site_name'];
    $description = $_POST['description'];
    $contact_email = $_POST['contact_email'];
    $primary_color = $_POST['primary_color'] ?? '#0d6efd';
    $secondary_color = $_POST['secondary_color'] ?? '#6c757d';

    $logoSql = $faviconSql = $homepageSql = '';
    $params = [$site_name, $description, $contact_email, $primary_color, $secondary_color];

    if (!empty($_FILES['logo']['name'])) {
        $upload = uploadImage($_FILES['logo'], 'logo');
        if ($upload && $upload !== 'SIZE_ERROR') {
            $logoSql = ', logo=?';
            $params[] = $upload;
        }
    }
    if (!empty($_FILES['favicon']['name'])) {
        $upload = uploadImage($_FILES['favicon'], 'favicon');
        if ($upload && $upload !== 'SIZE_ERROR') {
            $faviconSql = ', favicon=?';
            $params[] = $upload;
        }
    }
    if (!empty($_FILES['homepage']['name'])) {
        $upload = uploadImage($_FILES['homepage'], 'homepage');
        if ($upload && $upload !== 'SIZE_ERROR') {
            $homepageSql = ', homepage_image=?';
            $params[] = $upload;
        }
    }

    if ($settings) {
        $params[] = $settings['id'];
        $stmt = $pdo->prepare("UPDATE settings SET site_name=?, description=?, contact_email=?, primary_color=?, secondary_color=? $logoSql $faviconSql $homepageSql WHERE id=?");
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare("INSERT INTO settings (site_name, description, contact_email, primary_color, secondary_color, logo, favicon, homepage_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([...$params, $params[5] ?? null, $params[6] ?? null, $params[7] ?? null]);
    }

    exit(json_encode(['success' => true]));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings | Admin - RPMS</title>
<?php include $_SERVER['DOCUMENT_ROOT'].'/rpms-system/includes/favicon.php'; ?>
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
body { font-family: 'Poppins', sans-serif; background:#f4f6f9; }
img.preview-img { max-height:150px; display:block; margin-bottom:10px; border:1px solid #ddd; padding:5px; border-radius:5px; }
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2>Site Settings</h2>
    <form id="settingsForm" enctype="multipart/form-data" class="mt-4">

        <div class="mb-3">
            <label class="form-label">Site Name</label>
            <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($settings['description'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Contact Email</label>
            <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Primary Color</label>
            <input type="color" name="primary_color" class="form-control form-control-color" value="<?= htmlspecialchars($settings['primary_color'] ?? '#0d6efd') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Secondary Color</label>
            <input type="color" name="secondary_color" class="form-control form-control-color" value="<?= htmlspecialchars($settings['secondary_color'] ?? '#6c757d') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Logo</label><br>
            <img id="logoPreview" class="preview-img" src="<?= isset($settings['logo']) ? '/rpms-system/uploads/settings/'.$settings['logo'] : '' ?>" style="<?= isset($settings['logo']) ? '' : 'display:none;' ?>">
            <input type="file" name="logo" class="form-control" accept="image/*" onchange="previewImage(this,'logoPreview')">
        </div>

        <div class="mb-3">
            <label class="form-label">Favicon</label><br>
            <img id="faviconPreview" class="preview-img" src="<?= isset($settings['favicon']) ? '/rpms-system/uploads/settings/'.$settings['favicon'] : '' ?>" style="<?= isset($settings['favicon']) ? '' : 'display:none;' ?>">
            <input type="file" name="favicon" class="form-control" accept="image/*,.ico" onchange="previewImage(this,'faviconPreview')">
        </div>

        <div class="mb-3">
            <label class="form-label">Homepage Default Image</label><br>
            <img id="homepagePreview" class="preview-img" src="<?= isset($settings['homepage_image']) ? '/rpms-system/uploads/settings/'.$settings['homepage_image'] : '' ?>" style="<?= isset($settings['homepage_image']) ? '' : 'display:none;' ?>">
            <input type="file" name="homepage" class="form-control" accept="image/*" onchange="previewImage(this,'homepagePreview')">
        </div>

        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function previewImage(input, id){
    const img = document.getElementById(id);
    if(input.files && input.files[0]){
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; img.style.display='block'; }
        reader.readAsDataURL(input.files[0]);
    }
}

// AJAX Save
$('#settingsForm').submit(function(e){
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('save_settings', true);
    $.ajax({
        url: 'settings.php',
        type: 'POST',
        data: formData,
        contentType:false,
        processData:false,
        success:function(){ alert('Settings saved successfully!'); location.reload(); }
    });
});
</script>
</body>
</html>
