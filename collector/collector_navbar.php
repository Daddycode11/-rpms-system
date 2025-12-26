<?php
// collector_navbar.php
/* session_start(); */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

$firstName = $_SESSION['first_name'] ?? 'Collector';
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm mb-4">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-success" href="dashboard.php">
            RPMS Collector
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collectorNav" aria-controls="collectorNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="collectorNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="collector_payments.php">Payments</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="collector_history.php">History</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="collector_profile.php">Profile</a>
                </li>
            </ul>

            <span class="navbar-text me-3">
                Hello, <?= htmlspecialchars($firstName); ?>
            </span>

            <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>
