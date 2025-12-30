<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// ===================== AUTH =====================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$collector_id = $_SESSION['user_id'];

/* ===================== VENDOR LOOKUP ===================== */
if (isset($_GET['lookup_vendor']) && $_GET['lookup_vendor'] == 1 && isset($_GET['stall'])) {

    $stall_number = trim($_GET['stall']);

    $stmt = $pdo->prepare("
        SELECT 
            v.id AS vendor_id,
            v.monthly_rent,
            CONCAT(u.first_name,' ',u.last_name) AS vendor_name,
            u.contact AS vendor_contact,
            v.stall_number
        FROM vendors v
        JOIN users u ON u.id = v.user_id
        WHERE v.stall_number = ?
        LIMIT 1
    ");
    $stmt->execute([$stall_number]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vendor) {
        echo json_encode([
            'success' => true,
            'vendor_id' => $vendor['vendor_id'],
            'vendor_name' => $vendor['vendor_name'],
            'vendor_contact' => $vendor['vendor_contact'],
            'stall_number' => $vendor['stall_number'],
            'monthly_rent' => $vendor['monthly_rent']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Vendor not found']);
    }
    exit;
}

/* ===================== SUBMIT PAYMENT ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stall_number'])) {

    $stall_number = trim($_POST['stall_number']);

    $payment_type = $_POST['payment_type'] ?? 'daily';
    $period_start = $_POST['period_start'] ?? date('Y-m-d');
    $period_end   = $_POST['period_end'] ?? date('Y-m-d');
    $penalty      = floatval($_POST['penalty'] ?? 0);

    /* ---------- FETCH VENDOR ---------- */
    $stmt = $pdo->prepare("SELECT id, monthly_rent FROM vendors WHERE stall_number = ? LIMIT 1");
    $stmt->execute([$stall_number]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        echo json_encode([
            'success' => false,
            'message' => 'Vendor not found'
        ]);
        exit;
    }

    /* ---------- CALCULATE AMOUNT BASED ON PAYMENT TYPE ---------- */
    $monthly_rent = floatval($vendor['monthly_rent']);
    $amount_paid = $monthly_rent; // default monthly

    if ($payment_type === 'daily') {
        $amount_paid = $monthly_rent / 30;
    } elseif ($payment_type === 'weekly') {
        $amount_paid = $monthly_rent / 4;
    }

    /* ---------- AUTOMATIC DISCOUNT 5% FOR 1st-5th ---------- */
    $discount = 0;
    $today = date('j'); // day of month
    if ($today >= 1 && $today <= 5) {
        $discount = 0.05 * $monthly_rent; // 5% of monthly rent
    }

    /* ---------- VALIDATION ---------- */
    if (!$stall_number || $amount_paid <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Stall number and valid amount are required'
        ]);
        exit;
    }

    /* ---------- INSERT PAYMENT ---------- */
    $stmt = $pdo->prepare("
        INSERT INTO payments
        (
            vendor_id,
            collector_id,
            amount_paid,
            discount,
            penalty,
            payment_type,
            period_start,
            period_end,
            payment_date
        )
        VALUES (?,?,?,?,?,?,?,?,NOW())
    ");

    $stmt->execute([
        $vendor['id'],
        $collector_id,
        $amount_paid,
        $discount,
        $penalty,
        $payment_type,
        $period_start,
        $period_end
    ]);

    $payment_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'payment_id' => $payment_id,
        'amount_paid' => $amount_paid,
        'discount' => $discount
    ]);
    exit;
}

/* ===================== FALLBACK ===================== */
echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;
