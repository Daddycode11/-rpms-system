<?php
// collector_navbar.php

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

$firstName = $_SESSION['first_name'] ?? 'Collector';
$current   = basename($_SERVER['PHP_SELF']);

// Fetch overdue vendor count
require_once __DIR__ . '/../config/database.php';
$overdueStmt = $pdo->query("SELECT COUNT(*) FROM vendors WHERE status='overdue'");
$overdueCount = $overdueStmt->fetchColumn();
?>
<style>
    .navbar-dark .nav-link {
    color: rgba(255,255,255,.85);
    font-weight: 500;
}

.navbar-dark .nav-link.active,
.navbar-dark .nav-link:hover {
    color: #ffc107;
}

.dropdown-menu-dark .dropdown-item:hover {
    background-color: rgba(255,193,7,.15);
}

</style>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-lg sticky-top">
    <div class="container-fluid">

        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2 fw-semibold" href="dashboard.php">
            <span class="text-warning fs-5">RPMS</span>
            <span class="badge bg-warning text-dark">Collector</span>
        </a>

        <!-- Mobile Toggle -->
        <button class="navbar-toggler border-0" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collectorNav"
                aria-controls="collectorNav"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nav -->
        <div class="collapse navbar-collapse" id="collectorNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-lg-2">

                <li class="nav-item">
                    <a class="nav-link <?= $current=='dashboard.php'?'active':'' ?>" href="dashboard.php">Dashboard</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $current=='collector_payments.php'?'active':'' ?>" href="collector_payments.php">Payments</a>
                </li>

                <!-- Vendors Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($current,'vendor')?'active':'' ?>"
                       href="#" id="vendorDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Vendors
                        <?php if($overdueCount>0): ?>
                            <span class="badge bg-danger ms-1"><?= $overdueCount ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark shadow">
                        <li><a class="dropdown-item" href="vendors_list.php">📋 Vendor List</a></li>
                        <li><a class="dropdown-item" href="vendors_active.php">✅ Active Vendors</a></li>
                        <li><a class="dropdown-item" href="vendors_overdue.php">⚠️ Overdue Vendors</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-success" href="collector_payments.php">
                                ⚡ Quick Pay Vendor
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $current=='collector_history.php'?'active':'' ?>" href="collector_history.php">History</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $current=='collector_profile.php'?'active':'' ?>" href="collector_profile.php">Profile</a>
                </li>
            </ul>

            <!-- Right -->
            <div class="d-flex align-items-center gap-3">
                <span class="text-light small d-none d-lg-inline">👋 <?= htmlspecialchars($firstName) ?></span>
                <a href="../auth/logout.php" class="btn btn-outline-warning btn-sm">Logout</a>
            </div>
        </div>
    </div>
</nav>

<!-- Bottom Mobile POS Navbar -->
<nav class="navbar fixed-bottom navbar-dark bg-dark d-lg-none shadow-lg">
    <div class="container-fluid justify-content-around">
        <a href="dashboard.php" class="text-center text-light">
            <i class="bi bi-speedometer2 fs-5"></i><br>Dashboard
        </a>
        <a href="collector_payments.php" class="text-center text-light">
            <i class="bi bi-cash-stack fs-5"></i><br>Payments
        </a>
        <a href="vendors_list.php" class="text-center text-light position-relative">
            <i class="bi bi-people fs-5"></i><br>Vendors
            <?php if($overdueCount>0): ?>
                <span class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-danger">
                    <?= $overdueCount ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="collector_history.php" class="text-center text-light">
            <i class="bi bi-clock-history fs-5"></i><br>History
        </a>
    </div>
</nav>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
