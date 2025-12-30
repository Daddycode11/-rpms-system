<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// ----------------------
// Role check
// ----------------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

// ----------------------
// Get vendor ID
// ----------------------
$vendor_id = $_GET['id'] ?? null;
if (!$vendor_id) {
    header("Location: vendors_list.php");
    exit;
}

// ----------------------
// Fetch vendor info
// ----------------------
$stmt = $pdo->prepare("SELECT * FROM vendors WHERE id=?");
$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    echo "Vendor not found!";
    exit;
}

// ----------------------
// Handle form submission
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['vendor_name'] ?? '';
    $stall = $_POST['stall_number'] ?? '';
    $status = $_POST['status'] ?? 'inactive';

    $update = $pdo->prepare("UPDATE vendors SET vendor_name=?, stall_number=?, status=? WHERE id=?");
    $update->execute([$name, $stall, $status, $vendor_id]);

    header("Location: vendors_list.php");
    exit;
}

?>

<?php include 'collector_navbar.php'; ?>

<div class="container mt-4">
    <h4>Edit Vendor</h4>

    <form method="post" class="mt-3">
        <div class="mb-3">
            <label>Vendor Name</label>
            <input type="text" name="vendor_name" class="form-control" required value="<?= htmlspecialchars($vendor['vendor_name']) ?>">
        </div>

        <div class="mb-3">
            <label>Stall Number</label>
            <input type="text" name="stall_number" class="form-control" required value="<?= htmlspecialchars($vendor['stall_number']) ?>">
        </div>

        <div class="mb-3">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="active" <?= $vendor['status']=='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $vendor['status']=='inactive'?'selected':'' ?>>Inactive</option>
                <option value="overdue" <?= $vendor['status']=='overdue'?'selected':'' ?>>Overdue</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Update Vendor</button>
        <a href="vendors_list.php" class="btn btn-secondary ms-2">Cancel</a>
    </form>
</div>
