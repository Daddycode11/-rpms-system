<?php
session_start();
if ($_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Collect Payment</title>
</head>
<body>

<h2>Enter Stall Number</h2>

<form method="POST">
    <input type="text" name="stall_number" placeholder="Stall Number" required>
    <button type="submit">Search</button>
</form>

<a href="dashboard.php">Back</a>

</body>
</html>
