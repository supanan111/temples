<?php
require_once 'db_config.php';

// รหัสผ่านตั้งต้นคือ superadmin123
$password = password_hash('superadmin123', PASSWORD_DEFAULT);

try {
    // เช็คก่อนว่าเคยสร้างไปแล้วหรือยัง
    $stmt = $pdo->query("SELECT id FROM users WHERE username = 'superadmin'");
    if ($stmt->rowCount() == 0) {
        // สร้างบัญชีที่ไม่มี temple_id (เพราะดูแลทุกวัด) และให้สิทธิ์เป็น superadmin
        $pdo->exec("INSERT INTO users (username, password, full_name, role, temple_id) 
                    VALUES ('superadmin', '$password', 'ผู้ดูแลระบบสูงสุด (เจ้าของแพลตฟอร์ม)', 'superadmin', NULL)");
        echo "<h1 style='color: #27ae60;'>✅ สร้างบัญชี Super Admin สำเร็จ!</h1>";
        echo "<p>Username: <strong>superadmin</strong></p>";
        echo "<p>Password: <strong>superadmin123</strong></p>";
        echo "<a href='login.php'>คลิกที่นี่เพื่อไปหน้าเข้าสู่ระบบ</a>";
    } else {
        echo "<h1 style='color: #e74c3c;'>⚠️ บัญชี Super Admin ถูกสร้างไว้แล้วในระบบครับ</h1>";
    }
} catch(PDOException $e) { 
    echo "เกิดข้อผิดพลาด: " . $e->getMessage(); 
}
?>