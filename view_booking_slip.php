<?php
/**
 * view_booking_slip.php — แสดงไฟล์สลิปการจองอย่างปลอดภัย
 */
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('กรุณาเข้าสู่ระบบก่อนดูสลิป');
}

$booking_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$booking_id || $booking_id < 1) {
    http_response_code(400);
    exit('ไม่พบรหัสการจอง');
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';
$user_temple_id = (int)($_SESSION['active_temple_id'] ?? $_SESSION['temple_id'] ?? 0);

try {
    $stmt = $pdo->prepare("SELECT user_id, temple_id, slip_image FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    exit('เกิดข้อผิดพลาดในการดึงข้อมูล');
}

if (!$booking || empty($booking['slip_image'])) {
    http_response_code(404);
    exit('ไม่พบสลิปการชำระเงินของการจองนี้');
}

// ตรวจสอบสิทธิ์
$is_owner = ((int)$booking['user_id'] === $user_id);
$is_superadmin = ($role === 'superadmin');
$is_temple_admin = ($role === 'admin' && (int)$booking['temple_id'] === $user_temple_id);

if (!$is_owner && !$is_superadmin && !$is_temple_admin) {
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์เข้าถึงสลิปนี้');
}

$filename = basename($booking['slip_image']);
$file_path = __DIR__ . '/uploads/slips/' . $filename;

if (!file_exists($file_path)) {
    http_response_code(404);
    exit('ไม่พบไฟล์สลิปบนเซิร์ฟเวอร์');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = $finfo ? finfo_file($finfo, $file_path) : 'image/jpeg';
if ($finfo) finfo_close($finfo);

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=86400');
readfile($file_path);
exit();
