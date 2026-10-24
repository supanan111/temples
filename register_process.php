<?php
session_start();
require_once 'db_config.php';
require_once 'auth_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit();
}

$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$next = safe_booking_return_target($_POST['next'] ?? '');

if ($full_name === '' || $username === '' || $password === '' || $confirm_password === '') {
    header("Location: register.php?error=required" . ($next !== '' ? '&next=' . rawurlencode($next) : ''));
    exit();
}

if (preg_match('/\A.{1,100}\z/us', $full_name) !== 1) {
    header("Location: register.php?error=full_name" . ($next !== '' ? '&next=' . rawurlencode($next) : ''));
    exit();
}

if (preg_match('/\A.{3,50}\z/us', $username) !== 1) {
    header("Location: register.php?error=username" . ($next !== '' ? '&next=' . rawurlencode($next) : ''));
    exit();
}

if (strlen($password) < 8 || $password !== $confirm_password) {
    header("Location: register.php?error=password" . ($next !== '' ? '&next=' . rawurlencode($next) : ''));
    exit();
}

try {
    $existing_user = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $existing_user->execute([$username]);
    if ($existing_user->fetchColumn()) {
        header("Location: register.php?error=exists" . ($next !== '' ? '&next=' . rawurlencode($next) : ''));
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, temple_id) VALUES (?, ?, ?, 'user', NULL)");
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$pdo->lastInsertId();
    $_SESSION['username'] = $username;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['role'] = 'user';
    $_SESSION['temple_id'] = null;

    header("Location: " . ($next !== '' ? $next : 'my_bookings.php'));
    exit();
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        header("Location: register.php?error=exists" . ($next !== '' ? '&next=' . rawurlencode($next) : ''));
        exit();
    }

    error_log("Registration failed: " . $e->getMessage());
    http_response_code(500);
    die("เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาตรวจสอบการตั้งค่าฐานข้อมูลแล้วลองใหม่");
}
