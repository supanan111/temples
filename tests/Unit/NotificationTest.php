<?php

namespace Tests\Unit;

use Tests\TestCase;

require_once __DIR__ . '/../../notify.php';

class NotificationTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = $this->createInMemoryDatabase();

        // ใส่ข้อมูลผู้ใช้จำลอง
        $this->pdo->exec("
            INSERT INTO users (id, username, password, full_name, role, temple_id) VALUES
            (1, 'superadmin', 'hash', 'ผู้ดูแลระบบสูงสุด', 'superadmin', NULL),
            (2, 'admin_wat1', 'hash', 'แอดมินวัดใหญ่', 'admin', 10),
            (3, 'admin_wat2', 'hash', 'แอดมินวัดโพธิ์', 'admin', 20),
            (4, 'user1', 'hash', 'สมชาย ใจดี', 'user', NULL);
        ");
    }

    public function testCreateSingleNotification(): void
    {
        create_notification($this->pdo, 4, 'booking_approved', 101, 'bookings', 'คำขอจองได้รับการอนุมัติแล้ว');

        $stmt = $this->pdo->query("SELECT * FROM notifications WHERE user_id = 4");
        $notif = $stmt->fetch();

        $this->assertNotNull($notif);
        $this->assertEquals('booking_approved', $notif['type']);
        $this->assertEquals('101', (string)$notif['ref_id']);
        $this->assertEquals('bookings', $notif['ref_table']);
        $this->assertEquals('คำขอจองได้รับการอนุมัติแล้ว', $notif['message']);
        $this->assertEquals('0', (string)$notif['is_read']);
    }

    public function testNotifyTempleAdminsOnlyTargetsSpecificTemple(): void
    {
        // แจ้งเตือนแอดมินของวัด 10 (user_id = 2)
        notify_temple_admins($this->pdo, 10, 'new_booking', 55, 'bookings', 'มีคำขอจองใหม่เข้ามา');

        // แอดมินวัด 10 ต้องได้รับการแจ้งเตือน
        $countWat1 = count_unread_notifications($this->pdo, 2);
        $this->assertEquals(1, $countWat1);

        // แอดมินวัด 20 (user_id = 3) ต้องไม่ได้รับการแจ้งเตือน
        $countWat2 = count_unread_notifications($this->pdo, 3);
        $this->assertEquals(0, $countWat2);

        // ผู้ใช้ทั่วไป (user_id = 4) ต้องไม่มีการแจ้งเตือน
        $countUser = count_unread_notifications($this->pdo, 4);
        $this->assertEquals(0, $countUser);
    }

    public function testNotifySuperadminsTargetsAllSuperadmins(): void
    {
        notify_superadmins($this->pdo, 'new_member', 99, 'users', 'มีสมาชิกใหม่สมัครเข้ามา');

        $unread = count_unread_notifications($this->pdo, 1);
        $this->assertEquals(1, $unread);

        // เช็คว่า admin วัดทั่วไปไม่ได้รับแจ้งเตือน superadmin นี้
        $this->assertEquals(0, count_unread_notifications($this->pdo, 2));
    }

    public function testCountUnreadNotificationsOnlyCountsUnread(): void
    {
        create_notification($this->pdo, 4, 'type_a', 1, 'tbl', 'แจ้งเตือน 1');
        create_notification($this->pdo, 4, 'type_b', 2, 'tbl', 'แจ้งเตือน 2');
        create_notification($this->pdo, 4, 'type_c', 3, 'tbl', 'แจ้งเตือน 3');

        $this->assertEquals(3, count_unread_notifications($this->pdo, 4));

        // ทำเครื่องหมายว่าอ่านแล้ว 1 รายการ
        $this->pdo->exec("UPDATE notifications SET is_read = 1 WHERE ref_id = 1 AND user_id = 4");

        $this->assertEquals(2, count_unread_notifications($this->pdo, 4));
    }
}

