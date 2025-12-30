<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

if (empty($_SESSION['otp_verified'])) {
    header('Location: ../auth/otp_verify.php');
    exit;
}
