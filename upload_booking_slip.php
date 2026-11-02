<?php
/**
 * upload_booking_slip.php — อัปโหลดสลิปการชำระเงินสำหรับการจอง
 */
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_bookings.php");
    exit();
}

$raw_ids = trim($_POST['booking_ids'] ?? $_POST['booking_id'] ?? '');
if ($raw_ids === '') {
    die("ไม่พบรหัสการจองที่ต้องการแนบสลิป");
}

$id_parts = array_filter(array_map('intval', explode(',', $raw_ids)), fn($id) => $id > 0);
if (empty($id_parts)) {
    die("รหัสการจองไม่ถูกต้อง");
}

// ตรวจสอบไฟล์สลิป
if (!isset($_FILES['slip_image']) || $_FILES['slip_image']['error'] !== UPLOAD_ERR_OK) {
    die("กรุณาเลือกไฟล์สลิปหลักฐานการโอนเงิน");
}

$file = $_FILES['slip_image'];
if ($file['size'] > 5 * 1024 * 1024) {
    die("ขนาดไฟล์สลิปต้องไม่เกิน 5 MB");
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
if ($finfo) finfo_close($finfo);

$allowed_types = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$img_info = @getimagesize($file['tmp_name']);
if (!$img_info || !isset($allowed_types[$mime_type]) || $img_info['mime'] !== $mime_type) {
    die("รองรับเฉพาะไฟล์รูปภาพ JPG, PNG หรือ WEBP เท่านั้น");
}

// ตรวจสอบสิทธิ์ว่าการจองเป็นของผู้ใช้นี้จริง (หรือเป็น admin)
$in_clause = implode(',', array_fill(0, count($id_parts), '?'));
$params = $id_parts;
$auth_sql = "SELECT id, temple_id, total_price, status, payment_status, contact_name, contact_phone FROM bookings WHERE id IN ($in_clause)";

if ($user_role === 'user') {
    $auth_sql .= " AND user_id = ?";
    $params[] = $user_id;
}

$stmt_check = $pdo->prepare($auth_sql);
$stmt_check->execute($params);
$bookings_to_update = $stmt_check->fetchAll();

if (empty($bookings_to_update)) {
    die("ไม่พบรายการจองหรือคุณไม่มีสิทธิ์ในการแก้ไขรายการนี้");
}

// บันทึกไฟล์สลิป
$upload_dir = __DIR__ . '/uploads/slips';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0775, true);
}

$filename = 'slip_bk_' . $id_parts[0] . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_types[$mime_type];
$dest = $upload_dir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    die("เกิดข้อผิดพลาดในการบันทึกไฟล์สลิป กรุณาลองใหม่อีกครั้ง");
}

// อัปเดตฐานข้อมูล
$update_ids = array_column($bookings_to_update, 'id');
$update_in = implode(',', array_fill(0, count($update_ids), '?'));

$stmt_up = $pdo->prepare("
    UPDATE bookings 
    SET slip_image = ?, payment_status = 'pending_review', slip_uploaded_at = CURRENT_TIMESTAMP 
    WHERE id IN ($update_in)
");
$stmt_up->execute(array_merge([$filename], $update_ids));

// ส่งการแจ้งเตือน
if (file_exists('notify.php')) {
    require_once 'notify.php';
    $temple_id = (int)$bookings_to_update[0]['temple_id'];
    $total_sum = array_sum(array_column($bookings_to_update, 'total_price'));
    $ids_str = '#' . implode(', #', $update_ids);
    $price_str = $total_sum > 0 ? " (ยอดเงิน ฿" . number_format($total_sum, 2) . ")" : "";

    $admin_msg = "มีการแนบสลิปชำระเงินสำหรับการจอง {$ids_str}{$price_str} กรุณาตรวจสอบ";
    notify_temple_admins($pdo, $temple_id, 'booking_slip_uploaded', (int)$update_ids[0], 'bookings', $admin_msg);
    notify_superadmins($pdo, 'booking_slip_uploaded', (int)$update_ids[0], 'bookings', $admin_msg);

    create_notification($pdo, $user_id, 'booking_slip_received', (int)$update_ids[0], 'bookings', "แนบสลิปการจอง {$ids_str} เรียบร้อยแล้ว อยู่ระหว่างเจ้าหน้าที่ตรวจสอบ");
}

$redirect = $_POST['redirect_to'] ?? 'my_bookings.php';
if (!in_array($redirect, ['my_bookings.php', 'check_status.php', 'booking_process.php'], true)) {
    $redirect = 'my_bookings.php';
}

header("Location: {$redirect}?slip_success=1&bk=" . urlencode(implode(',', $update_ids)));
exit();
