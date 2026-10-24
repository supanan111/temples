<?php
session_start();
require_once 'db_config.php';

function slip_upload_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo '<!DOCTYPE html><html lang="th"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>ส่งหลักฐานไม่สำเร็จ</title><body style="font-family:sans-serif;max-width:540px;margin:60px auto;padding:20px;text-align:center">';
    echo '<h1>ส่งหลักฐานไม่สำเร็จ</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="javascript:history.back()">กลับไปหน้ารายการ</a></body></html>';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$donation_id = filter_input(INPUT_POST, 'donation_id', FILTER_VALIDATE_INT);
$upload_token = $_POST['upload_token'] ?? '';
$session_tokens = $_SESSION['donation_slip_tokens'] ?? [];
if (!$donation_id || $donation_id < 1 || !is_string($upload_token) ||
    !is_array($session_tokens) || !isset($session_tokens[$donation_id]) ||
    !hash_equals($session_tokens[$donation_id], $upload_token)) {
    slip_upload_error('ลิงก์อัปโหลดหมดอายุหรือไม่ถูกต้อง กรุณาสร้างรายการบริจาคใหม่');
}

$file = $_FILES['slip_image'] ?? null;
if (!is_array($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    slip_upload_error('กรุณาเลือกไฟล์สลิปที่ต้องการส่ง');
}
if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024 ||
    empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    slip_upload_error('ไฟล์ต้องมีขนาดไม่เกิน 5 MB');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
if ($finfo) {
    finfo_close($finfo);
}
$allowed_types = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$image_info = @getimagesize($file['tmp_name']);
if (!$image_info || !isset($allowed_types[$mime_type]) || $image_info['mime'] !== $mime_type) {
    slip_upload_error('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG หรือ WEBP เท่านั้น');
}

try {
    $stmt = $pdo->prepare("SELECT id, status, slip_image FROM donations WHERE id = ? AND status = 'pending'");
    $stmt->execute([$donation_id]);
    $donation = $stmt->fetch();
    if (!$donation) {
        slip_upload_error('ไม่พบรายการบริจาคที่รอส่งหลักฐาน หรือรายการนี้ดำเนินการแล้ว', 404);
    }

    $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'donation_uploads';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        throw new RuntimeException('Unable to create donation evidence directory');
    }
    $filename = 'donation_' . $donation_id . '_' . bin2hex(random_bytes(16)) . '.' . $allowed_types[$mime_type];
    $target_path = $upload_dir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new RuntimeException('Unable to store donation evidence');
    }

    $stmt_update = $pdo->prepare('UPDATE donations SET slip_image = ? WHERE id = ? AND status = \'pending\'');
    $stmt_update->execute([$filename, $donation_id]);
    if ($stmt_update->rowCount() !== 1) {
        unlink($target_path);
        slip_upload_error('รายการบริจาคเปลี่ยนสถานะแล้ว กรุณาติดต่อวัด', 409);
    }
    if (!empty($donation['slip_image'])) {
        $old_path = $upload_dir . DIRECTORY_SEPARATOR . basename($donation['slip_image']);
        if (is_file($old_path)) {
            unlink($old_path);
        }
    }
    unset($_SESSION['donation_slip_tokens'][$donation_id]);
} catch (PDOException $e) {
    if (isset($target_path) && is_file($target_path)) {
        unlink($target_path);
    }
    error_log('Unable to save donation slip: ' . $e->getMessage());
    slip_upload_error('บันทึกหลักฐานไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 500);
} catch (RuntimeException $e) {
    error_log('Unable to store donation slip: ' . $e->getMessage());
    slip_upload_error('จัดเก็บไฟล์หลักฐานไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 500);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งหลักฐานเรียบร้อยแล้ว</title>
</head>
<body style="font-family:sans-serif;max-width:540px;margin:60px auto;padding:20px;text-align:center">
    <h1>ส่งหลักฐานเรียบร้อยแล้ว</h1>
    <p>วัดจะตรวจสอบสลิปและยืนยันยอดหลังตรวจพบเงินจริงในบัญชี</p>
    <a href="index.php">กลับหน้าหลัก</a>
</body>
</html>
