<?php
require_once 'db_config.php';

$username = "admin";
$password = "123456"; // รหัสผ่านที่เราจะใช้ล็อกอิน
$full_name = "ผู้ดูแลระบบ";
$role = "admin";

// เข้ารหัสผ่านก่อนบันทึกลงฐานข้อมูล
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (:username, :password, :full_name, :role)");
    $stmt->execute([
        ':username' => $username,
        ':password' => $hashed_password,
        ':full_name' => $full_name,
        ':role' => $role
    ]);
    echo "สร้างบัญชีผู้ดูแลระบบสำเร็จ! รหัสผ่านของคุณคือ 123456 <br> <a href='login.php'>กลับไปหน้าเข้าสู่ระบบ</a>";
} catch (PDOException $e) {
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>