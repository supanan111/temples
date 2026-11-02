-- ============================================================
-- ThumWadThaiHub — Full MySQL Schema
-- Character Set: utf8mb4 / Collation: utf8mb4_unicode_ci
-- ============================================================

CREATE TABLE IF NOT EXISTS `temples` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `province` VARCHAR(100) NULL,
    `district` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `description` TEXT NULL,
    `tags` TEXT NULL COMMENT 'คีย์เวิร์ดพิเศษ คั่นด้วย comma',
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,
    `image` VARCHAR(255) NULL,
    `promptpay_id` VARCHAR(13) NULL,
    `badge` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `temple_sections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `temple_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sections_temple` (`temple_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'user',
    `temple_id` INT NULL,
    `profile_image` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_users_temple` (`temple_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bookable_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `temple_id` INT NOT NULL,
    `item_name` VARCHAR(150) NOT NULL,
    `item_type` VARCHAR(50) NOT NULL COMMENT 'pavilion_funeral, pavilion_merit, monk, equipment, fair_stall',
    `description` TEXT NULL,
    `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_qty` INT NOT NULL DEFAULT 1,
    `is_active` ENUM('yes', 'no') NOT NULL DEFAULT 'yes',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_items_temple` (`temple_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `temple_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `user_id` INT NULL,
    `booking_date` DATE NOT NULL,
    `stall_no` VARCHAR(20) NULL,
    `qty_booked` INT NOT NULL DEFAULT 1,
    `status` ENUM('pending', 'approved', 'confirmed', 'success', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    `contact_name` VARCHAR(100) NOT NULL,
    `contact_phone` VARCHAR(20) NOT NULL,
    `admin_note` TEXT NULL COMMENT 'หมายเหตุจาก Admin',
    `cancelled_by` ENUM('user', 'admin') NULL,
    `cancelled_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_bookings_temple` (`temple_id`),
    INDEX `idx_bookings_user` (`user_id`),
    INDEX `idx_bookings_item` (`item_id`),
    INDEX `idx_bookings_date` (`booking_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `donations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `temple_id` INT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `donor_name` VARCHAR(100) NOT NULL DEFAULT 'ผู้ไม่ประสงค์ออกนาม',
    `anonymous` TINYINT(1) NOT NULL DEFAULT 0,
    `tax_deduction` ENUM('yes', 'no') NOT NULL DEFAULT 'no',
    `status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `slip_image` VARCHAR(255) NULL,
    `receipt_no` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_donations_temple_created` (`temple_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `temple_id` INT NOT NULL,
    `event_name` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `event_image` VARCHAR(255) NULL,
    `event_date` DATE NOT NULL,
    `event_time` TIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_events_temple_date` (`temple_id`, `event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `news` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `temple_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `image` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_news_temple` (`temple_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'User ID ผู้รับการแจ้งเตือน',
    `type` VARCHAR(50) NOT NULL COMMENT 'new_booking, booking_approved, booking_rejected, booking_cancelled, new_donation, new_member',
    `ref_id` INT NULL,
    `ref_table` VARCHAR(50) NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notif_user_read` (`user_id`, `is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
