<?php
session_start();

if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            exit;

        case 'collector':
            header("Location: collector/dashboard.php");
            exit;

        case 'vendor':
            header("Location: vendor/dashboard.php");
            exit;

        default:
            session_destroy();
            header("Location: auth/login.php");
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RPMS | Rental Payment System</title>
<?php include __DIR__ . '/includes/favicon.php'; ?>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ===========================
   GLOBALS
=========================== */
body, html { font-family: 'Poppins', sans-serif; scroll-behavior: smooth; }

:root { --primary-green: #0d8c45; --dark-green: #076b33; }

/* ===========================
   HERO BACKGROUND
=========================== */
.hero-section {
    background: url("assets/images/market-bg.png") center/cover no-repeat;
    height: 100vh;
    position: relative;
    display:flex;
    justify-content:center;
    align-items:center;
    overflow:hidden;
    animation: zoomBg 20s ease-in-out infinite alternate;
    transform-origin:center;
}

@keyframes zoomBg { from { transform: scale(1); } to { transform: scale(1.08); } }

/* overlay */
.overlay {
    position:absolute; top:0; left:0;
    width:100%; height:100%;
    background: rgba(0,0,0,.55);
}

/* content */
.hero-content {
    position:relative; z-index:2; text-align:center;
    animation: fadeInUp .9s ease both;
}

@keyframes fadeInUp { from { opacity:0; transform: translateY(30px); } to { opacity:1; transform: translateY(0); } }

.hero-content h1 {
    font-weight:700;
    animation: pulse .9s infinite alternate;
}

@keyframes pulse { from { opacity:.85; } to { opacity:1; } }

/* ===========================
   BUTTON
=========================== */
.btn-custom {
    background: var(--primary-green);
    color: #fff;
    border-radius:6px;
    transition: .3s ease;
}

.btn-custom:hover {
    background: var(--dark-green);
    transform: scale(1.05);
}

/* ===========================
   NAVBAR
=========================== */
.navbar-custom {
    background: white !important;
    opacity: .85;
    backdrop-filter: blur(6px);
}

/* ===========================
   FEATURES
=========================== */
.feature-card {
    border:none;
    border-radius:14px;
    opacity: 0;
    transform: translateY(30px);
    animation: reveal .6s ease forwards;
}

.feature-card:nth-child(1){ animation-delay:.2s; }
.feature-card:nth-child(2){ animation-delay:.35s; }
.feature-card:nth-child(3){ animation-delay:.55s; }

@keyframes reveal { to { opacity:1; transform: translateY(0); } }

.feature-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 5px 18px rgba(0,0,0,.12);
}

/* ===========================
   RESPONSIVE
=========================== */
@media(max-width:768px){
    .hero-content h1 { font-size: 1.6rem; }
    .btn-custom { padding:10px 20px; }
}

/* =============================
   PAGE LOADER
============================= */
#loader {
    position: fixed; inset:0;
    background: #ffffff;
    display:flex;
    justify-content:center;
    align-items:center;
    z-index:9999;
    animation: fadeOut 1s ease forwards;
    animation-delay: 1.5s;
}

.spinner {
    width:100px;
    height:100px;
    border:8px solid #e0e0e0;
    border-top:8px solid var(--primary-green);
    border-radius:50%;
    animation: spin .7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }
@keyframes fadeOut { to { opacity:0; visibility:hidden; } }

body { overflow:hidden; }
body.loaded { overflow:auto; }
/* ===========================
   SCROLL REVEAL (ADD-ON)
=========================== */
.reveal {
    opacity: 0;
    transform: translateY(40px);
    transition: all .8s ease;
}

.reveal.show {
    opacity: 1;
    transform: translateY(0);
}

/* ===========================
   ICON BOX (ADD-ON)
=========================== */
.icon-box {
    background:#fff;
    border-radius:16px;
    padding:30px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
    transition:.4s ease;
}

.icon-box:hover {
    transform: translateY(-8px);
    box-shadow:0 15px 40px rgba(0,0,0,.15);
}

/* ===========================
   STATS STRIP (ADD-ON)
=========================== */
.stats {
    background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
    color:#fff;
}

.stat-box h3 {
    font-size:2.5rem;
    font-weight:700;
}

/* ===========================
   CTA (ADD-ON)
=========================== */
.cta {
    background: url("assets/images/market-bg.png") center/cover fixed;
    position:relative;
}

.cta::before {
    content:"";
    position:absolute;
    inset:0;
    background:rgba(0,0,0,.6);
}

.cta-content {
    position:relative;
    z-index:2;
}
.btn-orange {
    background-color: #FF8000;
    border-color: #FF8000;
    color: #fff;
}

.btn-orange:hover {
    background-color: #e67300; /* slightly darker on hover */
    border-color: #e67300;
    color: #fff;
}

</style>
</head>

<body>

<div id="loader">
    <div class="spinner"></div>
</div>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light navbar-custom fixed-top shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="#">
            <img src="assets/images/logo.png" style="height:60px;">
        </a>

        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto me-3">
                <li class="nav-item"><a class="nav-link fw-semibold" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold" href="#about">About</a></li>
            </ul>

            <!-- Login Buttons -->
            <a href="auth/login.php" class="btn btn-custom btn-sm me-2">Login</a>
            <a href="auth/login.php?role=collector" class="btn btn-outline-success btn-sm">Login as Collector</a>
        </div>
    </div>
</nav>

<!-- HERO -->
<header class="hero-section">
    <div class="overlay"></div>
    <div class="hero-content text-white">
        <h1>San Jose Public Market<br>Rental Payment Management System</h1>
        <p class="lead">POS-Enabled System for Smart Rental Fee Collection</p>

        <!-- Updated Buttons for Collector workflow -->
        <a class="btn btn-custom px-4 py-2 mt-3 me-2" href="auth/register.php?role=collector">
            Register as Collector
        </a>
        <a class="btn btn-custom px-4 py-2 mt-3" href="auth/login.php?role=collector">
            Login as Collector
        </a>
    </div>
</header>


<!-- FEATURES -->
<section id="features" class="py-5">
    <div class="container">
        <h2 class="text-center fw-bold mb-4 text-success">System Features</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <img src="assets/images/pos.png" height="70">
                    <h5 class="fw-bold mt-2">POS Payment</h5>
                    <p>Fast rental fee collections.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <img src="assets/images/stall.png" height="70">
                    <h5 class="fw-bold mt-2">Stall Management</h5>
                    <p>Track tenants & rentals.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <img src="assets/images/report.png" height="70">
                    <h5 class="fw-bold mt-2">Reports</h5>
                    <p>Auto generated insights.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ABOUT -->
<section id="about" class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center fw-bold text-success mb-4">About RPMS</h2>
        <p class="lead text-center w-75 mx-auto">
            The Rental Payment Management System automates rental payments for stall owners,
            improving transparency and payment tracking.
        </p>
    </div>
</section>
<section class="py-5">
    <div class="container">
        <h2 class="text-center fw-bold text-success mb-5 reveal">How It Works</h2>

        <div class="row g-4">
            <div class="col-md-4 reveal">
                <div class="icon-box text-center">
                    <h5 class="fw-bold">Register & Assign</h5>
                    <p>Create collectors, vendors, and stalls.</p>
                </div>
            </div>

            <div class="col-md-4 reveal">
                <div class="icon-box text-center">
                    <h5 class="fw-bold">Collect Payments</h5>
                    <p>POS-enabled daily rental collection.</p>
                </div>
            </div>

            <div class="col-md-4 reveal">
                <div class="icon-box text-center">
                    <h5 class="fw-bold">Monitor & Report</h5>
                    <p>View real-time tracking and reports.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center fw-bold text-success mb-5 reveal">User Roles</h2>

        <div class="row g-4">
            <div class="col-md-4 reveal">
                <div class="icon-box">
                    <h5 class="fw-bold">Administrator</h5>
                    <p>Manages collectors, stalls, and reports.</p>
                </div>
            </div>

            <div class="col-md-4 reveal">
                <div class="icon-box">
                    <h5 class="fw-bold">Collector</h5>
                    <p>Collects payments and issues receipts.</p>
                </div>
            </div>

            <div class="col-md-4 reveal">
                <div class="icon-box">
                    <h5 class="fw-bold">Vendor</h5>
                    <p>Views rental status and payment history.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="stats py-5">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 stat-box reveal">
                <h3>100%</h3>
                <p>Digital Records</p>
            </div>
            <div class="col-md-3 stat-box reveal">
                <h3>24/7</h3>
                <p>System Access</p>
            </div>
            <div class="col-md-3 stat-box reveal">
                <h3>Fast</h3>
                <p>POS Transactions</p>
            </div>
            <div class="col-md-3 stat-box reveal">
                <h3>Secure</h3>
                <p>Role-Based Access</p>
            </div>
        </div>
    </div>
</section>
<section class="cta py-5">
    <div class="container text-center text-white cta-content">
        <h2 class="fw-bold mb-3 reveal">Ready to Go Digital?</h2>
        <p class="lead reveal">Start using RPMS today.</p>
        <a href="auth/login.php" class="btn btn-custom px-5 py-3 reveal">
            Get Started
        </a>
    </div>
</section>

<!-- FOOTER -->
<footer class="bg-dark text-white p-4">
    <div class="container text-center">
        <p class="mb-0">© 2025 San Jose Public Market | RPMS</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.addEventListener("load", () => {
    document.body.classList.add("loaded");
});
</script>
<script>
const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add("show");
        }
    });
}, { threshold: 0.2 });

document.querySelectorAll(".reveal").forEach(el => observer.observe(el));
</script>

</body>
</html>
