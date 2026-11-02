<?php
/**
 * notify.php — Helper สำหรับสร้าง notification records
 * require_once 'notify.php' ในไฟล์ที่ต้องการใช้
 * ต้อง require_once 'db_config.php' มาก่อนแล้ว
 */

/**
 * สร้างการแจ้งเตือนสำหรับผู้ใช้คนใดคนหนึ่ง
 */
function create_notification(PDO $pdo, int $user_id, string $type, int $ref_id, string $ref_table, string $message): void
{
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $next_id = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM notifications")->fetchColumn();
            $stmt = $pdo->prepare(
                "INSERT INTO notifications (id, user_id, type, ref_id, ref_table, message) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$next_id, $user_id, $type, $ref_id, $ref_table, $message]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, type, ref_id, ref_table, message) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$user_id, $type, $ref_id, $ref_table, $message]);
        }
    } catch (PDOException $e) {
        // ไม่ให้ error notification ทำลาย flow หลัก
        error_log("Notification insert failed: " . $e->getMessage());
    }
}

/**
 * แจ้งเตือน Admin ทั้งหมดของวัดนั้น
 */
function notify_temple_admins(PDO $pdo, int $temple_id, string $type, int $ref_id, string $ref_table, string $message): void
{
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE temple_id = ? AND role = 'admin'");
        $stmt->execute([$temple_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $admin_id) {
            create_notification($pdo, (int)$admin_id, $type, $ref_id, $ref_table, $message);
        }
    } catch (PDOException $e) {
        error_log("Notify temple admins failed: " . $e->getMessage());
    }
}

/**
 * แจ้งเตือน Superadmin ทั้งหมด
 */
function notify_superadmins(PDO $pdo, string $type, int $ref_id, string $ref_table, string $message): void
{
    try {
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'superadmin'");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $admin_id) {
            create_notification($pdo, (int)$admin_id, $type, $ref_id, $ref_table, $message);
        }
    } catch (PDOException $e) {
        error_log("Notify superadmins failed: " . $e->getMessage());
    }
}

/**
 * นับจำนวน unread notifications ของผู้ใช้คนนั้น
 */
function count_unread_notifications(PDO $pdo, int $user_id): int
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
