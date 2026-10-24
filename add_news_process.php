<?php
session_start();
require_once 'db_config.php';

// ตรวจสอบสิทธิ์อีกครั้งเพื่อความปลอดภัย
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับค่าจากฟอร์ม
    $title = $_POST['title'];
    $content = $_POST['content'];
    
    // ดึง ID ของคนที่กำลังล็อกอินอยู่ (แอดมินที่โพสต์ข่าวนี้)
    $author_id = $_SESSION['user_id'];

    try {
        // ใช้ Prepared Statement เพื่อบันทึกข้อมูลลงตาราง news ป้องกัน SQL Injection
        $stmt = $pdo->prepare("INSERT INTO news (title, content, author_id) VALUES (:title, :content, :author_id)");
        
        // ผูกค่าตัวแปรเข้ากับคำสั่ง SQL
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':author_id', $author_id);
        
        // สั่งทำงาน
        if ($stmt->execute()) {
            // ถ้าบันทึกสำเร็จ ให้เด้งกลับไปหน้า Dashboard พร้อมแจ้งเตือน
            echo "<script>
                    alert('เพิ่มข่าวสารใหม่เรียบร้อยแล้ว!');
                    window.location.href = 'admin_dashboard.php';
                  </script>";
        } else {
            echo "<script>alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล'); window.history.back();</script>";
        }

    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
} else {
    header("Location: add_news.php");
    exit();
}
?>