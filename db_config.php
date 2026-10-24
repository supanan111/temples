<?php
// db_config.php

$host = "localhost";
$dbname = "temple_platform";
$username = "root"; // ปกติคือ root
$password = "";     // ถ้าไม่ได้ตั้งรหัสผ่านให้เว้นว่างไว้

try {
    // สร้างการเชื่อมต่อ PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // ตั้งค่า Error Mode ให้แสดง Exception เมื่อเกิดปัญหา
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ตั้งค่าการดึงข้อมูลให้เป็นแบบ Array Associative เป็นค่าเริ่มต้น
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // echo "เชื่อมต่อฐานข้อมูลสำเร็จ!"; // เปิดไว้เช็คตอนแรกได้ครับ
} catch (PDOException $e) {
    // ถ้าเชื่อมต่อไม่ได้ ให้แสดงข้อความแจ้งเตือน
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . $e->getMessage());
}
?>