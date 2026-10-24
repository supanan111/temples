<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true)) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์เข้าถึงหลักฐานการบริจาค');
}

$donation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$donation_id || $donation_id < 1) {
    http_response_code(400);
    exit('ไม่พบรายการบริจาค');
}

if ($_SESSION['role'] === 'superadmin') {
    $temple_id = (int)($_SESSION['active_temple_id'] ?? 0);
} else {
    $temple_id = (int)($_SESSION['temple_id'] ?? 0);
}
if ($temple_id < 1) {
    http_response_code(403);
    exit('บัญชีนี้ยังไม่ได้ผูกกับวัด');
}

try {
    $stmt = $pdo->prepare('SELECT slip_image FROM donations WHERE id = ? AND temple_id = ?');
    $stmt->execute([$donation_id, $temple_id]);
    $filename = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Unable to load donation slip: ' . $e->getMessage());
    http_response_code(500);
    exit('ไม่สามารถโหลดหลักฐานการบริจาคได้');
}

if (!is_string($filename) || !preg_match('/\Adonation_' . $donation_id . '_[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename)) {
    http_response_code(404);
    exit('ไม่พบไฟล์หลักฐานการบริจาค');
}

$file_path = __DIR__ . DIRECTORY_SEPARATOR . 'donation_uploads' . DIRECTORY_SEPARATOR . $filename;
if (!is_file($file_path)) {
    http_response_code(404);
    exit('ไม่พบไฟล์หลักฐานการบริจาค');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = $finfo ? finfo_file($finfo, $file_path) : false;
if ($finfo) {
    finfo_close($finfo);
}
$allowed_mime_types = [
    'jpg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!isset($allowed_mime_types[$extension]) || $mime_type !== $allowed_mime_types[$extension]) {
    http_response_code(415);
    exit('รูปแบบไฟล์หลักฐานไม่ถูกต้อง');
}

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: inline; filename="donation-slip-' . $donation_id . '.' . $extension . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($file_path);
