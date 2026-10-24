<?php
session_start();
require_once 'db_config.php';
require_once 'auth_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $next = safe_booking_return_target($_POST['next'] ?? '');

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
            
            $_SESSION['user_id'] = $user['id'];          
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            $_SESSION['temple_id'] = isset($user['temple_id']) ? $user['temple_id'] : null;
            session_regenerate_id(true);

            if (!in_array($user['role'], ['user', 'admin', 'superadmin'], true)) {
                header("Location: login.php?error=invalid" . ($next !== '' ? '&next=' . rawurlencode($next) : ''));
                exit();
            }

            if ($user['role'] === 'superadmin') {
                header("Location: superadmin_dashboard.php");
                exit();
            } elseif ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
                exit();
            }

            if ($next !== '') {
                header("Location: " . $next);
                exit();
            }

            header("Location: index.php");
            exit();
            
        } else {
            header("Location: login.php?error=invalid" . ($next !== '' ? '&next=' . rawurlencode($next) : ''));
            exit();
        }

    } catch (PDOException $e) {
        error_log("Login failed: " . $e->getMessage());
        http_response_code(500);
        die("เกิดข้อผิดพลาดในการเข้าสู่ระบบ กรุณาลองใหม่อีกครั้ง");
    }
} else {
    header("Location: login.php");
    exit();
}
?>