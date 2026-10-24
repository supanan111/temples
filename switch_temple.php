<?php
session_start();
require_once 'db_config.php';

// เช็กสิทธิ์เฉพาะ superadmin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

// รับค่า temple_id ที่เลือกมา
if (isset($_GET['id'])) {
    $_SESSION['active_temple_id'] = (int)$_GET['id'];
}

// ส่งกลับไปที่หน้าเดิม หรือกลับไปที่ admin_dashboard.php
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin_dashboard.php';
header("Location: " . $redirect);
exit();
?>