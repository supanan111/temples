<?php
session_start();
require_once 'db_config.php';

// 1. ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    
    try {
        // 2. จัดการเรื่องอัปโหลดรูปภาพโปรไฟล์ (ถ้ามีการเลือกไฟล์รูปมา)
        $profile_image_sql = "";
        $params = [$full_name, $user_id];

        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $file = $_FILES['profile_image'];
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            // เช็คนามสกุลไฟล์
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_extensions)) {
                // ตั้งชื่อไฟล์ใหม่เพื่อป้องกันชื่อซ้ำ (เช่น profile_id_เวลา.ext)
                $new_filename = "profile_" . $user_id . "_" . time() . "." . $file_extension;
                $upload_dir = "uploads/";
                
                // ตรวจสอบว่ามีโฟลเดอร์ uploads หรือยัง ถ้ายังให้สร้างอัตโนมัติ
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $upload_path = $upload_dir . $new_filename;

                // ย้ายไฟล์รูปภาพไปยังโฟลเดอร์ uploads
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // ดึงชื่อไฟล์เดิมในฐานข้อมูลมาลบ (ถ้ามี) เพื่อไม่ให้เปลืองพื้นที่เซิร์ฟเวอร์
                    $stmt_old = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                    $stmt_old->execute([$user_id]);
                    $old_data = $stmt_old->fetch();
                    
                    if (!empty($old_data['profile_image']) && file_exists($upload_dir . $old_data['profile_image'])) {
                        unlink($upload_dir . $old_data['profile_image']); // ลบรูปเก่าออก
                    }

                    // เตรียมคำสั่ง SQL อัปเดตทั้งชื่อและรูปภาพใหม่
                    $profile_image_sql = ", profile_image = ?";
                    // จัดเรียง Parameter ใหม่ให้อยู่ในลำดับที่จะ Execute
                    $params = [$full_name, $new_filename, $user_id];
                }
            }
        }

        // 3. ทำการอัปเดตข้อมูลลงในฐานข้อมูล
        $sql = "UPDATE users SET full_name = ? $profile_image_sql WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // 4. อัปเดตค่า full_name ใน Session ให้เป็นค่าปัจจุบันทันที
        $_SESSION['full_name'] = $full_name;

        // บันทึกสำเร็จ เด้งกลับไปหน้า Dashboard พร้อมแจ้งเตือน
        header("Location: admin_dashboard.php?status=success");
        exit();

    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $e->getMessage());
    }
} else {
    header("Location: edit_profile.php");
    exit();
}
?>