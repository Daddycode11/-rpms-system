<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="vendor_dashboard.php">Vendor Panel</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">

        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>" href="dashboard.php">Dashboard</a>
        </li>

        <!-- Vendor Actions Dropdown -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['vendor_account.php','make_payment.php','vendor_receipts.php'])?'active':'' ?>" href="#" id="vendorDropdown" role="button" data-bs-toggle="dropdown">
            My Account
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="vendorDropdown">
            <li><a class="dropdown-item" href="vendor_account.php">View Account</a></li>
            <li><a class="dropdown-item" href="vendor_payment_history.php">View Payment</a></li>
            <li><a class="dropdown-item" href="vendor_receipts.php">Download Receipts</a></li>
            <li><a class="dropdown-item" href="update_profile.php">Update Profile</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="../auth/logout.php">Logout</a>
        </li>

      </ul>
    </div>
  </div>
</nav>
