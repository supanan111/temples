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

// ดึงข้อมูลโปรไฟล์ของ Admin ปัจจุบันมาแสดงบน Topbar
try {
    $stmt_user = $pdo->prepare("SELECT full_name, profile_image FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $current_admin = $stmt_user->fetch();

    $admin_name = $current_admin['full_name'] ?? $_SESSION['full_name'];
    $profile_image = $current_admin['profile_image'] ?? '';

    // 2. ดึงข้อมูลวัดตามสิทธิ์
    if ($role === 'superadmin') {
        // Super Admin เห็นวัดทั้งหมด (สำหรับแสดงในตาราง)
        $stmt = $pdo->query("SELECT * FROM temples ORDER BY id DESC");
        $temples = $stmt->fetchAll();
    } else {
        // Admin วัด เห็นเฉพาะวัดของตนเอง (1 แอดมิน 1 วัด)
        $stmt = $pdo->prepare("SELECT * FROM temples WHERE id = ?");
        $stmt->execute([$temple_id]);
        $my_temple = $stmt->fetch(); // ดึงมาเป็นแถวเดี่ยวสำหรับทำ UI การ์ด
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
    <title>จัดการข้อมูลวัด - ระบบบริหารจัดการวัด</title>
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
        
        .btn-add { background-color: var(--primary-color); color: white; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 6px rgba(192, 130, 33, 0.2); transition: 0.2s; }
        .btn-add:hover { background-color: #A66E1B; }

        .panel { background: var(--card-bg); border-radius: 8px; border: 1px solid var(--border-color); padding: 25px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 12px; border-bottom: 2px solid #F0ECE7; color: var(--text-muted); font-weight: 500; font-size: 13px; }
        td { padding: 15px 12px; border-bottom: 1px solid #F0ECE7; font-size: 14px; color: var(--text-main); vertical-align: middle; }
        
        .btn-action { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 500; margin-right: 5px; display: inline-block; }
        .btn-edit { background: #FDFBF7; color: var(--primary-color); border: 1px solid var(--primary-color); }
        .btn-edit:hover { background: var(--primary-color); color: white; }
        .btn-delete { background: #FDFBF7; color: #C0392B; border: 1px solid #C0392B; }
        .btn-delete:hover { background: #C0392B; color: white; }

        /* ดีไซน์เฉพาะสำหรับ Admin วัด (Card View) */
        .temple-card-detail { display: flex; flex-direction: column; gap: 20px; }
        .temple-info-group label { font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 4px; }
        .temple-info-group div { font-size: 16px; font-weight: 500; color: var(--text-main); }
    </style>
</head>
<body>

    <!-- Sidebar แบบไดนามิกแยกตามสิทธิ์ -->
    <div class="sidebar">
        <div class="sidebar-header">
            <?php echo htmlspecialchars($role === 'superadmin' ? 'Super Admin Panel' : ($my_temple['name'] ?? 'ไม่พบข้อมูลวัด'), ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <ul class="sidebar-menu">
            <?php if ($role === 'superadmin'): ?>
                <li><a href="superadmin_dashboard.php"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
                <li><a href="manage_temples.php" class="active"><i class="fas fa-university"></i> จัดการวัดทั้งหมด</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users-cog"></i> จัดการผู้ใช้ระบบ</a></li>
                <li><a href="manage_bookings.php"><i class="fas fa-calendar-check"></i> ตรวจสอบการจอง</a></li>
            <?php else: ?>
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
                <li><a href="manage_temples.php" class="active"><i class="fas fa-university"></i> จัดการข้อมูลวัด</a></li>
                <li><a href="edit_temple.php"><i class="fas fa-pen-to-square"></i> แก้ไขหน้าเว็บไซต์วัด</a></li>
                <li><a href="manage_bookings.php"><i class="fas fa-calendar-check"></i> ตรวจสอบการจอง</a></li>
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
                    <h2><?php echo ($role === 'superadmin') ? 'จัดการวัดทั้งหมดในระบบ' : 'ข้อมูลวัดที่คุณดูแล'; ?></h2>
                    <p><?php echo ($role === 'superadmin') ? 'เพิ่ม แก้ไข หรือลบข้อมูลวัดในระบบภาพรวม' : 'ตรวจสอบและแก้ไขข้อมูลรายละเอียดวัดของคุณ'; ?></p>
                </div>
                
                <?php if ($role === 'superadmin'): ?>
                    <a href="add_temples.php" class="btn-add"><i class="fas fa-plus"></i> เพิ่มวัดใหม่</a>
                <?php endif; ?>
            </div>

            <!-- ส่วนแสดงผลแยกตาม Role -->
            <?php if ($role === 'superadmin'): ?>
                <!-- โหมด Super Admin: แสดงเป็นตารางรายการวัดทั้งหมด -->
                <div class="panel">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>ชื่อวัด</th>
                                <th>รายละเอียด / ที่ตั้ง</th>
                                <th style="text-align: right; width: 150px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($temples) > 0): ?>
                                <?php foreach ($temples as $t): ?>
                                    <tr>
                                        <td>#<?php echo $t['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                                        <td style="color: var(--text-muted);"><?php echo htmlspecialchars($t['address'] ?? 'ไม่มีข้อมูลที่ตั้ง'); ?></td>
                                        <td style="text-align: right;">
                                            <a href="edit_temple.php?id=<?php echo $t['id']; ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i> แก้ไข</a>
                                            <a href="delete_temple.php?id=<?php echo $t['id']; ?>" class="btn-action btn-delete" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบวัดนี้?');"><i class="fas fa-trash"></i> ลบ</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align: center; padding: 30px; color: var(--text-muted);">ไม่พบข้อมูลวัดในระบบ</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <!-- โหมด Admin วัด: แสดงเป็น Card ข้อมูลวัดตัวเอง (1 แอดมินต่อ 1 วัด) -->
                <div class="panel" style="max-width: 700px;">
                    <?php if ($my_temple): ?>
                        <div class="temple-card-detail">
                            <div class="temple-info-group">
                                <label>ชื่อวัด</label>
                                <div style="font-size: 20px; color: var(--primary-color); font-weight: 600;"><?php echo htmlspecialchars($my_temple['name']); ?></div>
                            </div>
                            <div class="temple-info-group">
                                <label>ที่ตั้ง / ที่อยู่วัด</label>
                                <div><?php echo !empty($my_temple['address']) ? htmlspecialchars($my_temple['address']) : '<span style="color: var(--text-muted); font-weight: normal;">ยังไม่ได้ระบุที่อยู่</span>'; ?></div>
                            </div>
                            <!-- เพิ่มฟิลด์อื่นๆ ตามโครงสร้างตาราง temples ของคุณ เช่น เบอร์โทร, รายละเอียดวัด -->
                            
                            <div style="padding-top: 15px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
                                <a href="edit_temple.php?id=<?php echo $my_temple['id']; ?>" class="btn-add" style="font-size: 13px; padding: 8px 16px;"><i class="fas fa-pen-to-square"></i> แก้ไขหน้าเว็บไซต์วัด</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                            ไม่พบข้อมูลวัดที่คุณสังกัดอยู่ กรุณาติดต่อ Super Admin เพื่อผูกบัญชีกับวัด
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>