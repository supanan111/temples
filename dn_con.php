<?php
require_once 'db_config.php'; // ดึงไฟล์เชื่อมต่อมาใช้

try {
    // ตัวอย่างการดึงข้อมูลจากตาราง donations
    $stmt = $pdo->query("SELECT * FROM donations ORDER BY created_at DESC");
    $donations = $stmt->fetchAll();
    
    // แสดงผลเบื้องต้น
    /*
    foreach ($donations as $row) {
        echo "ผู้บริจาค: " . $row['donor_name'] . " จำนวน: " . $row['amount'] . " บาท<br>";
    }
    */
} catch (Exception $e) {
    echo "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}
?>