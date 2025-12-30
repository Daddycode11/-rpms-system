<?php
if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$admin_name = $_SESSION['username'] ?? 'Admin';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
<div class="container-fluid">

    <a class="navbar-brand fw-bold" href="dashboard.php">RPMS Admin</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#adminNavbar">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="adminNavbar">

        <!-- LEFT NAV -->
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">

            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?= $current_page=='dashboard.php'?'active fw-bold text-warning':'' ?>"
                   href="dashboard.php">Dashboard</a>
            </li>

            <!-- Vendors Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle <?= in_array($current_page,[
                    'vendors.php','vendor_accounts.php','overdue_vendors.php'
                ])?'active fw-bold text-warning':'' ?>"
                   role="button" data-bs-toggle="dropdown">
                    Vendors
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="vendors.php">Vendor List</a></li>
                    <li><a class="dropdown-item text-danger" href="overdue_vendors.php">Overdue Vendors</a></li>
                    <li><a class="dropdown-item" href="vendor_accounts.php">Vendor Accounts</a></li>
                    <li><a class="dropdown-item" href="vendor_payment_history.php">Payment History</a></li>
                </ul>
            </li>

            <!-- Payments Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle <?= in_array($current_page,[
                    'payments.php','partial_payments.php','verify_receipt.php'
                ])?'active fw-bold text-warning':'' ?>"
                   role="button" data-bs-toggle="dropdown">
                    Payments
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="payments.php">All Payments</a></li>
                    <li><a class="dropdown-item" href="partial_payments.php">Partial Payments</a></li>
                    <li><a class="dropdown-item" href="verify_receipt.php">QR Receipt Verification</a></li>
                </ul>
            </li>

            <!-- Sections (UNCHANGED) -->
            <li class="nav-item">
                <a class="nav-link <?= $current_page=='sections.php'?'active fw-bold text-warning':'' ?>"
                   href="sections.php">Sections</a>
            </li>

            <!-- Reports (UNCHANGED) -->
            <li class="nav-item">
                <a class="nav-link <?= $current_page=='reports.php'?'active fw-bold text-warning':'' ?>"
                   href="reports.php">Reports</a>
            </li>

            <!-- Monitoring -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle"
                   role="button" data-bs-toggle="dropdown">
                    Monitoring
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="collector_performance.php">Collector Performance</a></li>
                    <li><a class="dropdown-item" href="late_payments.php">Late Payments</a></li>
                    <li><a class="dropdown-item" href="audit_logs.php">Audit Logs</a></li>
                </ul>
            </li>

            <!-- Settings (UNCHANGED) -->
            <li class="nav-item">
                <a class="nav-link <?= $current_page=='settings.php'?'active fw-bold text-warning':'' ?>"
                   href="settings.php">Settings</a>
            </li>

        </ul>

        <!-- RIGHT NAV -->
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" role="button" data-bs-toggle="dropdown">
                    <?= htmlspecialchars($admin_name) ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../auth/logout.php">Logout</a></li>
                </ul>
            </li>
        </ul>

    </div>
</div>
</nav>
