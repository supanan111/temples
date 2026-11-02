<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php"); exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$csrf = $_GET['csrf'] ?? '';

if (!$id || $id < 1) {
    header("Location: manage_temples.php?error=invalid"); exit();
}

if (empty($_SESSION['delete_temple_csrf']) || $csrf !== $_SESSION['delete_temple_csrf']) {
    header("Location: manage_temples.php?error=csrf"); exit();
}

try {
    $pdo->beginTransaction();
    // ลบข้อมูลที่เกี่ยวข้องก่อน
    $pdo->prepare("DELETE FROM bookings WHERE temple_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM bookable_items WHERE temple_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM events WHERE temple_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM news WHERE temple_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM temple_sections WHERE temple_id = ?")->execute([$id]);
    $pdo->prepare("UPDATE users SET temple_id = NULL WHERE temple_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM temples WHERE id = ?")->execute([$id]);
    $pdo->commit();
    unset($_SESSION['delete_temple_csrf']);
    header("Location: manage_temples.php?deleted=1"); exit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Delete temple failed: " . $e->getMessage());
    header("Location: manage_temples.php?error=db"); exit();
}
