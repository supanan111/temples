<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php"); exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$csrf = $_GET['csrf'] ?? '';

if (!$id || $id < 1) {
    header("Location: my_bookings.php?error=invalid"); exit();
}

if (empty($_SESSION['cancel_booking_csrf']) || $csrf !== $_SESSION['cancel_booking_csrf']) {
    header("Location: my_bookings.php?error=csrf"); exit();
}

try {
    // ดึงข้อมูลก่อนเพื่อใช้ส่งแจ้งเตือน
    $stmt_bk = $pdo->prepare("SELECT temple_id, contact_name FROM bookings WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt_bk->execute([$id, $_SESSION['user_id']]);
    $bk = $stmt_bk->fetch();

    if (!$bk) {
        header("Location: my_bookings.php?error=not_cancellable"); exit();
    }

    // อัปเดตเฉพาะของ user คนนี้ที่ยังเป็น pending เท่านั้น
    try {
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancelled_by = 'user', cancelled_at = NOW() WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$id, $_SESSION['user_id']]);
    } catch (PDOException $e_col) {
        // fallback ถ้ายังไม่ได้รัน migration เพิ่ม cancelled_by / cancelled_at
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$id, $_SESSION['user_id']]);
    }
    
    // แจ้งเตือนแอดมินวัดและ superadmin
    if (file_exists('notify.php')) {
        require_once 'notify.php';
        $contact = !empty($bk['contact_name']) ? $bk['contact_name'] : ($_SESSION['full_name'] ?? 'ผู้ใช้งาน');
        $msg = "ผู้จอง ({$contact}) ได้ยกเลิกคำขอจอง #{$id}";
        notify_temple_admins($pdo, (int)$bk['temple_id'], 'booking_cancelled', (int)$id, 'bookings', $msg);
        notify_superadmins($pdo, 'booking_cancelled', (int)$id, 'bookings', $msg);
    }

    unset($_SESSION['cancel_booking_csrf']);
    header("Location: my_bookings.php?cancelled=1"); exit();
} catch (PDOException $e) {
    error_log("Cancel booking failed: " . $e->getMessage());
    header("Location: my_bookings.php?error=db"); exit();
}
