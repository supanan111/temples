-- ============================================================
-- ThumWadThaiHub — Migration v2.0
-- Safe to run multiple times (Idempotent)
-- Run this on database: if0_43263069_temple_platform
-- Date: 2026-11-03
-- ============================================================

-- 1. เพิ่ม column 'tags' ในตาราง temples (Feature 1: ค้นหา keyword)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'temples' AND COLUMN_NAME = 'tags'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE temples ADD COLUMN tags TEXT NULL COMMENT \'คีย์เวิร์ดพิเศษ เช่น ฝังลูกนิมิต,ผ้าป่า คั่นด้วย comma\' AFTER description',
    'SELECT 1 -- tags column already exists'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. เพิ่ม column 'event_image' ในตาราง events (Feature 5: ปฏิทินรูปภาพ)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'event_image'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE events ADD COLUMN event_image VARCHAR(255) NULL AFTER description',
    'SELECT 1 -- event_image column already exists'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. เพิ่ม column 'admin_note' ในตาราง bookings (Feature 4: หมายเหตุจาก Admin)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'admin_note'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE bookings ADD COLUMN admin_note TEXT NULL COMMENT \'หมายเหตุจาก Admin เมื่อ reject/approve\' AFTER contact_phone',
    'SELECT 1 -- admin_note column already exists'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. เพิ่ม column 'cancelled_by' และ 'cancelled_at' ในตาราง bookings (Feature 4: user ยกเลิกเอง)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'cancelled_by'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE bookings ADD COLUMN cancelled_by ENUM(\'user\',\'admin\') NULL COMMENT \'ใครเป็นคนยกเลิก\' AFTER admin_note',
    'SELECT 1 -- cancelled_by column already exists'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'cancelled_at'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE bookings ADD COLUMN cancelled_at DATETIME NULL AFTER cancelled_by',
    'SELECT 1 -- cancelled_at column already exists'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- อัปเดต ENUM status ของ bookings ให้รองรับ 'cancelled'
-- (ระวัง: ถ้า status เป็น ENUM จะต้องตรวจสอบก่อน)
SET @status_col = (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'status'
);
-- อัปเดตเฉพาะถ้ายังไม่มี 'cancelled' ใน enum
SET @needs_update = IF(@status_col NOT LIKE '%cancelled%', 1, 0);
SET @sql = IF(@needs_update = 1,
    'ALTER TABLE bookings MODIFY COLUMN status ENUM(\'pending\',\'approved\',\'confirmed\',\'success\',\'rejected\',\'cancelled\') NOT NULL DEFAULT \'pending\'',
    'SELECT 1 -- status enum already includes cancelled'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5. เพิ่ม column 'anonymous' ในตาราง donations (Feature 6: ไม่ระสงค์ออกนาม)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donations' AND COLUMN_NAME = 'anonymous'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE donations ADD COLUMN anonymous TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'1=ผู้ไม่ประสงค์ออกนาม\' AFTER donor_name',
    'SELECT 1 -- anonymous column already exists'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 6. สร้างตาราง notifications (Feature 2+3: ระบบแจ้งเตือน Admin และ User)
CREATE TABLE IF NOT EXISTS notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL COMMENT 'ผู้รับการแจ้งเตือน (admin หรือ user id)',
    type        ENUM(
                    'new_booking',
                    'booking_approved',
                    'booking_rejected',
                    'booking_cancelled',
                    'new_donation',
                    'donation_approved',
                    'donation_rejected',
                    'new_member'
                ) NOT NULL,
    ref_id      INT NOT NULL COMMENT 'id ของ record ที่เกี่ยวข้อง',
    ref_table   VARCHAR(50) NOT NULL COMMENT 'ชื่อตารางที่ ref_id อ้างถึง',
    message     TEXT NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_user_read (user_id, is_read),
    INDEX idx_notif_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='ระบบแจ้งเตือนสำหรับ admin และ user';

-- ============================================================
-- ตรวจสอบผล Migration
-- ============================================================
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    (TABLE_NAME = 'temples'   AND COLUMN_NAME = 'tags') OR
    (TABLE_NAME = 'events'    AND COLUMN_NAME = 'event_image') OR
    (TABLE_NAME = 'bookings'  AND COLUMN_NAME IN ('admin_note','cancelled_by','cancelled_at')) OR
    (TABLE_NAME = 'donations' AND COLUMN_NAME = 'anonymous') OR
    TABLE_NAME = 'notifications'
  )
ORDER BY TABLE_NAME, COLUMN_NAME;

