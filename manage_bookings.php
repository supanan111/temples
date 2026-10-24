<?php
session_start();
require_once 'db_config.php';

// 1. ตรวจสอบสิทธิ์: ต้องเป็น admin หรือ superadmin เท่านั้น
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$temple_id = $_SESSION['temple_id'] ?? null;
$user_id = $_SESSION['user_id'];
$temple_name_display = 'Super Admin Panel';

// จัดการการเปลี่ยนสถานะการจอง (ถ้ามีการส่งค่า POST มา)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['status'])) {
    $booking_id = is_string($_POST['booking_id']) ? filter_var($_POST['booking_id'], FILTER_VALIDATE_INT) : false;
    $new_status = $_POST['status'];
    $allowed_statuses = ['pending', 'approved', 'rejected'];

    if (!$booking_id || !is_string($new_status) || !in_array($new_status, $allowed_statuses, true)) {
        $error = 'ข้อมูลการอัปเดตสถานะไม่ถูกต้อง';
    } elseif ($role === 'admin' && !filter_var($temple_id, FILTER_VALIDATE_INT)) {
        $error = 'ไม่พบข้อมูลวัดที่คุณมีสิทธิ์จัดการ';
    } else {
        try {
            if ($role === 'superadmin') {
                $stmt_update = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
                $stmt_update->execute([$new_status, $booking_id]);
                $stmt_exists = $pdo->prepare("SELECT id FROM bookings WHERE id = ?");
                $stmt_exists->execute([$booking_id]);
            } else {
                $stmt_update = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ? AND temple_id = ?");
                $stmt_update->execute([$new_status, $booking_id, $temple_id]);
                $stmt_exists = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND temple_id = ?");
                $stmt_exists->execute([$booking_id, $temple_id]);
            }

            if ($stmt_update->rowCount() > 0 || $stmt_exists->fetchColumn()) {
                header("Location: manage_bookings.php?success=1");
                exit();
            }

            $error = 'ไม่พบรายการจองนี้ หรือคุณไม่มีสิทธิ์เปลี่ยนสถานะ';
        } catch (PDOException $e) {
            error_log("Booking status update failed: " . $e->getMessage());
            $error = 'เกิดข้อผิดพลาดในการอัปเดตสถานะ กรุณาลองใหม่อีกครั้ง';
        }
    }
}

// ดึงข้อมูลโปรไฟล์ของ Admin ปัจจุบันมาแสดงบน Topbar
try {
    $stmt_user = $pdo->prepare("SELECT full_name, profile_image FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $current_admin = $stmt_user->fetch();

    $admin_name = $current_admin['full_name'] ?? $_SESSION['full_name'];
    $profile_image = $current_admin['profile_image'] ?? '';

    // 2. ดึงข้อมูลการจองตามสิทธิ์
    if ($role === 'superadmin') {
        // Super Admin ดึงการจองทั้งหมด พร้อม Join ชื่อวัด
        $sql = "SELECT b.*, t.name AS temple_name 
                FROM bookings b 
                LEFT JOIN temples t ON b.temple_id = t.id 
                ORDER BY b.id DESC";
        $stmt = $pdo->query($sql);
        $bookings = $stmt->fetchAll();
    } else {
        $stmt_temple = $pdo->prepare("SELECT name FROM temples WHERE id = ?");
        $stmt_temple->execute([$temple_id]);
        $temple_name_display = $stmt_temple->fetchColumn() ?: 'ไม่พบข้อมูลวัด';

        // Admin วัด ดึงเฉพาะการจองของวัดตัวเอง
        $sql = "SELECT b.*, t.name AS temple_name 
                FROM bookings b 
                LEFT JOIN temples t ON b.temple_id = t.id 
                WHERE b.temple_id = ? 
                ORDER BY b.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$temple_id]);
        $bookings = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการจอง - ระบบบริหารจัดการวัด</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-responsive.css">
    <script src="assets/js/admin-responsive.js" defer></script>
    
    <style>
        :root { 
            --sidebar-bg: #2B2522; 
            --sidebar-hover: #3E352F; 
            --primary-color: #C08221; 
            --bg-color: #FDFBF7; 
            --text-main: #3E352F; 
            --text-muted: #857B74;
            --card-bg: #FFFFFF;
            --border-color: #EAE5DE;
        }
        body { font-family: 'Prompt', sans-serif; margin: 0; padding: 0; display: flex; background-color: var(--bg-color); color: var(--text-main); min-height: 100vh; }
        
        /* Sidebar Styles */
        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: #EAE5DE; display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0,0,0,0.05); }
        .sidebar-header { padding: 25px 20px; text-align: center; background-color: #1F1A18; font-size: 15px; font-weight: 500; color: var(--primary-color); border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sidebar-menu { list-style: none; padding: 10px 0; margin: 0; flex-grow: 1; }
        .sidebar-menu a { color: #BDB5AF; text-decoration: none; padding: 14px 25px; display: block; transition: 0.2s; font-size: 14px; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--sidebar-hover); color: white; border-left: 3px solid var(--primary-color); }
        .sidebar-menu i { margin-right: 12px; width: 20px; text-align: center; opacity: 0.8;}
        
        /* Main Content Styles */
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-x: hidden; }
        .topbar { background-color: var(--card-bg); padding: 15px 35px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        
        .user-profile-section { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-color); }
        .user-info-text { display: flex; flex-direction: column; line-height: 1.2; text-align: right; }
        .user-name { font-weight: 500; color: var(--text-main); }
        .user-role-label { font-size: 11px; color: var(--text-muted); }
        .profile-edit-btn { color: var(--primary-color); text-decoration: none; font-size: 12px; border: 1px solid var(--primary-color); padding: 4px 10px; border-radius: 4px; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px; }
        .profile-edit-btn:hover { background: var(--primary-color); color: white; }

        .logout-btn { color: #C0392B; text-decoration: none; margin-left: 10px; font-weight: 500; border: 1px solid #C0392B; padding: 5px 12px; border-radius: 4px; transition: 0.2s; font-size: 12px; }
        .logout-btn:hover { background: #C0392B; color: white; }
        
        .content-container { padding: 35px; max-width: 1200px; margin: 0 auto; width: 100%; box-sizing: border-box;}
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h2 { margin: 0; color: var(--text-main); font-weight: 500; font-size: 24px; }
        .page-header p { margin: 5px 0 0 0; color: var(--text-muted); font-size: 14px; }

        .panel { background: var(--card-bg); border-radius: 8px; border: 1px solid var(--border-color); padding: 25px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 12px; border-bottom: 2px solid #F0ECE7; color: var(--text-muted); font-weight: 500; font-size: 13px; }
        td { padding: 15px 12px; border-bottom: 1px solid #F0ECE7; font-size: 14px; color: var(--text-main); vertical-align: middle; }
        
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: 500; display: inline-block; }
        .badge-pending { background: #FCF3CF; color: #7D6608; }
        .badge-confirmed { background: #D4EFDF; color: #145A32; }
        .badge-cancelled { background: #FADBD8; color: #78281F; }

        .status-form { display: inline-flex; gap: 5px; align-items: center; }
        .status-select { padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border-color); font-family: 'Prompt', sans-serif; font-size: 12px; background: #FAFAFA; }
        .btn-update { background: var(--primary-color); color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 12px; cursor: pointer; }
        .btn-update:hover { background: #A66E1B; }

        .btn-delete { background: #FDFBF7; color: #C0392B; border: 1px solid #C0392B; padding: 5px 10px; border-radius: 4px; font-size: 12px; text-decoration: none; display: inline-block; }
        .btn-delete:hover { background: #C0392B; color: white; }
    </style>
</head>
<body>

    <!-- Sidebar แบบไดนามิกแยกตามสิทธิ์ -->
    <div class="sidebar">
        <div class="sidebar-header">
            <?php echo htmlspecialchars($temple_name_display, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <ul class="sidebar-menu">
            <?php if ($role === 'superadmin'): ?>
                <li><a href="superadmin_dashboard.php"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
                <li><a href="manage_temples.php"><i class="fas fa-university"></i> จัดการวัดทั้งหมด</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users-cog"></i> จัดการผู้ใช้ระบบ</a></li>
                <li><a href="manage_bookings.php" class="active"><i class="fas fa-calendar-check"></i> ตรวจสอบการจอง</a></li>
            <?php else: ?>
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
                <li><a href="manage_temples.php"><i class="fas fa-university"></i> จัดการข้อมูลวัด</a></li>
                <li><a href="edit_temple.php"><i class="fas fa-pen-to-square"></i> แก้ไขหน้าเว็บไซต์วัด</a></li>
                <li><a href="manage_bookings.php" class="active"><i class="fas fa-calendar-check"></i> ตรวจสอบการจอง</a></li>
                <li><a href="manage_items.php"><i class="fas fa-box-open"></i> จัดการบริการ/สถานที่</a></li>
                <li><a href="manage_events.php"><i class="fas fa-calendar-alt"></i> จัดการปฏิทินกิจกรรม</a></li>
                <li><a href="manage_donations.php"><i class="fas fa-donate"></i> ข้อมูล E-Donation</a></li>
                <li><a href="add_news.php"><i class="fas fa-bullhorn"></i> จัดการข่าวสาร/ประชาสัมพันธ์</a></li>
            <?php endif; ?>
            <li><a href="edit_profile.php"><i class="fas fa-user-edit"></i> แก้ไขโปรไฟล์ส่วนตัว</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <div><i class="fas fa-bars" style="color: var(--text-muted);"></i></div>
            
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="user-profile-section">
                    <img src="<?php echo !empty($profile_image) ? 'uploads/' . $profile_image : 'assets/default-avatar.png'; ?>" 
                         alt="Admin Profile" 
                         class="user-avatar">
                    <div class="user-info-text">
                        <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                        <span class="user-role-label"><?php echo ($role === 'superadmin') ? 'Super Administrator' : 'Temple Administrator'; ?></span>
                    </div>
                </div>
                
                <div>
                    <a href="edit_profile.php" class="profile-edit-btn"><i class="fas fa-user-edit"></i> แก้ไขโปรไฟล์</a>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
                </div>
            </div>
        </div>

        <div class="content-container">
            <div class="page-header">
                <div>
                    <h2><?php echo ($role === 'superadmin') ? 'ตรวจสอบการจองทั้งหมดในระบบ' : 'จัดการการจองของวัด'; ?></h2>
                    <p><?php echo ($role === 'superadmin') ? 'ดูรายการจองทั้งหมดและภาพรวมการใช้งานในทุกวัด' : 'ตรวจสอบและอัปเดตสถานะการจองของผู้ใช้งานภายในวัดของคุณ'; ?></p>
                </div>
            </div>

            <?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
                <div role="status" style="margin-bottom: 16px; padding: 12px 16px; border-radius: 6px; background: #D4EFDF; color: #145A32;">
                    อัปเดตสถานะการจองเรียบร้อยแล้ว
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div role="alert" style="margin-bottom: 16px; padding: 12px 16px; border-radius: 6px; background: #FADBD8; color: #78281F;">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="panel">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <?php if ($role === 'superadmin'): ?>
                                <th>วัดที่จอง</th>
                            <?php endif; ?>
                            <th>ผู้จอง</th>
                            <th>วันที่จอง / รายละเอียด</th>
                            <th>สถานะ</th>
                            <th style="text-align: right; width: 180px;">จัดการสถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bookings) > 0): ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td>#<?php echo $b['id']; ?></td>
                                    
                                    <?php if ($role === 'superadmin'): ?>
                                        <td><strong><?php echo htmlspecialchars($b['temple_name'] ?? 'ไม่ระบุวัด'); ?></strong></td>
                                    <?php endif; ?>

                                    <td>
                                        <strong><?php echo htmlspecialchars($b['contact_name'] ?? $b['name'] ?? $b['user_name'] ?? 'ผู้ใช้งานทั่วไป'); ?></strong>
                                        <?php if (!empty($b['contact_phone'])): ?>
                                            <br><small><?php echo htmlspecialchars($b['contact_phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: var(--text-muted);">
                                        <?php echo htmlspecialchars($b['booking_date'] ?? $b['date'] ?? 'ไม่ระบุวันที่'); ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $status = trim((string)($b['status'] ?? '')) ?: 'pending';
                                            $badge_class = 'badge-pending';
                                            $status_text = 'รอดำเนินการ';
                                            if (in_array($status, ['confirmed', 'success', 'approved'], true)) {
                                                $badge_class = 'badge-confirmed';
                                                $status_text = 'ยืนยันแล้ว';
                                            } elseif ($status === 'rejected') {
                                                $badge_class = 'badge-cancelled';
                                                $status_text = 'ไม่อนุมัติ';
                                            }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <form action="manage_bookings.php" method="POST" class="status-form">
                                            <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                            <select name="status" class="status-select">
                                                <option value="pending" <?php echo ($status === 'pending') ? 'selected' : ''; ?>>รอดำเนินการ</option>
                                                <option value="approved" <?php echo in_array($status, ['confirmed', 'success', 'approved'], true) ? 'selected' : ''; ?>>ยืนยัน</option>
                                                <option value="rejected" <?php echo ($status === 'rejected') ? 'selected' : ''; ?>>ไม่อนุมัติ</option>
                                            </select>
                                            <button type="submit" class="btn-update"><i class="fas fa-check"></i></button>
                                        </form>

                                        <!-- Super Admin สามารถลบรายการจองได้ถ้าจำเป็น -->
                                        <?php if ($role === 'superadmin'): ?>
                                            <a href="delete_booking.php?id=<?php echo $b['id']; ?>" class="btn-delete" onclick="return confirm('ต้องการลบรายการจองนี้ใช่หรือไม่?');"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo ($role === 'superadmin') ? '6' : '5'; ?>" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    ยังไม่มีรายการจองในขณะนี้
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>