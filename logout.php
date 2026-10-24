<?php
// 1. เริ่มต้น Session เพื่อให้ระบบรู้ว่ากำลังจะจัดการกับ Session ของใคร
session_start();

// 2. ล้างค่าข้อมูลทั้งหมดที่เก็บไว้ในตัวแปร Session (เช่น user_id, role, full_name)
$_SESSION = array();

// 3. ทำลาย Session ทิ้งไปอย่างถาวร
session_destroy();

// 4. เด้งผู้ใช้งานกลับไปที่หน้า Login
header("Location: login.php");
exit();
?>