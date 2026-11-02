<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php"); exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$csrf = $_GET['csrf'] ?? '';

if (!$id || $id < 1) {
    header("Location: manage_bookings.php?error=invalid"); exit();
}

// Simple CSRF: token stored in session
if (empty($_SESSION['delete_booking_csrf']) || $csrf !== $_SESSION['delete_booking_csrf']) {
    header("Location: manage_bookings.php?error=csrf"); exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->execute([$id]);
    unset($_SESSION['delete_booking_csrf']);
    header("Location: manage_bookings.php?deleted=1"); exit();
} catch (PDOException $e) {
    error_log("Delete booking failed: " . $e->getMessage());
    header("Location: manage_bookings.php?error=db"); exit();
}
