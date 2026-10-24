<?php
session_start();
require_once 'db_config.php';

// ตรวจสอบสิทธิ์ ต้องเป็น admin หรือ superadmin ถึงจะเข้าหน้านี้ได้
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
// ดึง temple_id ของแอดมินคนนี้ เพื่อนำไปกรองข้อมูลเฉพาะวัดที่ตนเองดูแล
$temple_id = $_SESSION['temple_id'] ?? null;

try {
    // 1. ดึงข้อมูลล่าสุดของ Admin ปัจจุบัน (ชื่อ และ รูปภาพโปรไฟล์)
    $stmt_user = $pdo->prepare("SELECT full_name, profile_image FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $current_admin = $stmt_user->fetch();

    $admin_name = $current_admin['full_name'] ?? $_SESSION['full_name'];
    $profile_image = $current_admin['profile_image'] ?? '';

    // 2. ดึงชื่อวัดที่แอดมินคนนี้ดูแลอยู่ (กรณีต้องการแสดงชื่อวัดบนหัวข้อแดชบอร์ด)
    $temple_name_display = "วัดของฉัน";
    if ($temple_id) {
        $stmt_temple = $pdo->prepare("SELECT name FROM temples WHERE id = ?");
        $stmt_temple->execute([$temple_id]);
        $temple_info = $stmt_temple->fetch();
        if ($temple_info) {
            $temple_name_display = $temple_info['name'];
        }
    }

    // 3. ดึงสถิติต่างๆ เฉพาะวัดของแอดมินคนนี้
    // ยอดบริจาครวมเฉพาะวัดนี้ (เช็คว่าตาราง donations มี temple_id หรือเชื่อมผ่าน bookings)
    // สมมติว่าตาราง donations หรือ bookings มี temple_id กรองตาม temple_id
    $stmt_donations = $pdo->prepare("SELECT SUM(amount) FROM donations WHERE temple_id = ? AND status = 'completed'");
    $stmt_donations->execute([$temple_id]);
    $total_donations = $stmt_donations->fetchColumn() ?: 0;

    // คำขอจองรอตรวจสอบเฉพาะวัดนี้
    $stmt_pending = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE temple_id = ? AND status = 'pending'");
    $stmt_pending->execute([$temple_id]);
    $total_pending = $stmt_pending->fetchColumn() ?: 0;

    // จำนวนบริการ/กิจกรรมที่เปิดให้จองของวัดนี้
    $stmt_services = $pdo->prepare("SELECT COUNT(*) FROM bookable_items WHERE temple_id = ?");
    $stmt_services->execute([$temple_id]);
    $total_services = $stmt_services->fetchColumn() ?: 0;

    // 4. ดึงรายการจองล่าสุดเฉพาะวัดนี้
    $stmt_recent_bookings = $pdo->prepare("
        SELECT b.id, b.contact_name, b.contact_phone, b.created_at, i.item_name, t.name AS temple_name 
        FROM bookings b 
        JOIN bookable_items i ON b.item_id = i.id 
        LEFT JOIN temples t ON b.temple_id = t.id
        WHERE b.temple_id = ? AND b.status = 'pending' 
        ORDER BY b.created_at DESC LIMIT 5
    ");
    $stmt_recent_bookings->execute([$temple_id]);
    $recent_bookings = $stmt_recent_bookings->fetchAll();

} catch (PDOException $e) { 
    die("Error: " . $e->getMessage()); 
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ด - <?php echo htmlspecialchars($temple_name_display); ?></title>
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
        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: #EAE5DE; display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0,0,0,0.05); }
        .sidebar-header { padding: 25px 20px; text-align: center; background-color: #1F1A18; font-size: 15px; font-weight: 500; color: var(--primary-color); border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sidebar-menu { list-style: none; padding: 10px 0; margin: 0; flex-grow: 1; }
        .sidebar-menu a { color: #BDB5AF; text-decoration: none; padding: 14px 25px; display: block; transition: 0.2s; font-size: 14px; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--sidebar-hover); color: white; border-left: 3px solid var(--primary-color); }
        .sidebar-menu i { margin-right: 12px; width: 20px; text-align: center; opacity: 0.8;}
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
        .page-header { margin-bottom: 30px; }
        .page-header h2 { margin: 0; color: var(--text-main); font-weight: 500; font-size: 24px; }
        .page-header p { margin: 5px 0 0 0; color: var(--text-muted); font-size: 14px; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .stat-card { background: var(--card-bg); border-radius: 8px; padding: 25px; border: 1px solid var(--border-color); display: flex; align-items: center; border-top: 3px solid var(--primary-color); }
        .stat-card .icon { font-size: 32px; margin-right: 20px; color: var(--primary-color); opacity: 0.8; }
        .stat-card .info h3 { margin: 0 0 5px 0; font-size: 24px; color: var(--text-main); font-weight: 500; }
        .stat-card .info p { margin: 0; font-size: 13px; color: var(--text-muted); }
        .panel { background: var(--card-bg); border-radius: 8px; border: 1px solid var(--border-color); padding: 25px; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px; }
        .panel-header h3 { margin: 0; font-size: 16px; font-weight: 500; color: var(--text-main); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 12px; border-bottom: 2px solid #F0ECE7; color: var(--text-muted); font-weight: 500; font-size: 13px; }
        td { padding: 15px 12px; border-bottom: 1px solid #F0ECE7; font-size: 14px; color: var(--text-main); }
        .btn-view { display: inline-block; padding: 6px 12px; background: #FDFBF7; color: var(--primary-color); border: 1px solid var(--primary-color); border-radius: 4px; text-decoration: none; font-size: 12px; }
        .btn-view:hover { background: var(--primary-color); color: white; }
    </style>
</head>
<body>

    <div class="sidebar">
    <div class="sidebar-header">
        <?php echo htmlspecialchars($_SESSION['role'] === 'superadmin' ? 'Super Admin Panel' : $temple_name_display, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <ul class="sidebar-menu">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin'): ?>
            <li><a href="superadmin_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'superadmin_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
            <li><a href="manage_temples.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_temples.php' ? 'active' : ''; ?>"><i class="fas fa-university"></i> จัดการวัดทั้งหมด</a></li>
            <li><a href="manage_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>"><i class="fas fa-users-cog"></i> จัดการผู้ใช้ระบบ</a></li>
            <li><a href="manage_bookings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_bookings.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-check"></i> ตรวจสอบการจอง</a></li>
        <?php else: ?>
            <li><a href="admin_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
            <li><a href="manage_temples.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_temples.php' ? 'active' : ''; ?>"><i class="fas fa-university"></i> จัดการข้อมูลวัด</a></li>
            <li><a href="edit_temple.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'edit_temple.php' ? 'active' : ''; ?>"><i class="fas fa-pen-to-square"></i> แก้ไขหน้าเว็บไซต์วัด</a></li>
            <li><a href="manage_bookings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_bookings.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-check"></i> ตรวจสอบการจอง</a></li>
            <li><a href="manage_items.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_items.php' ? 'active' : ''; ?>"><i class="fas fa-box-open"></i> จัดการบริการ/สถานที่</a></li>
            <li><a href="manage_events.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> จัดการปฏิทินกิจกรรม</a></li>
            <li><a href="manage_donations.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_donations.php' ? 'active' : ''; ?>"><i class="fas fa-donate"></i> ข้อมูล E-Donation</a></li>
            <li><a href="add_news.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'add_news.php' ? 'active' : ''; ?>"><i class="fas fa-bullhorn"></i> จัดการข่าวสาร/ประชาสัมพันธ์</a></li>
        <?php endif; ?>
        <li><a href="edit_profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'edit_profile.php' ? 'active' : ''; ?>"><i class="fas fa-user-edit"></i> แก้ไขโปรไฟล์ส่วนตัว</a></li>
    </ul>
</div>

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
                        <span class="user-role-label">Temple Administrator</span>
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
                <h2>ภาพรวม: <?php echo htmlspecialchars($temple_name_display); ?></h2>
                <p>ระบบบริหารจัดการข้อมูลและบริการภายในวัดของคุณ</p>
            </div>

            <!-- กล่องสถิติเฉพาะวัด -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="info">
                        <h3>฿<?php echo number_format($total_donations, 2); ?></h3>
                        <p>ยอดบริจาครวมของวัด</p>
                    </div>
                </div>
                <div class="stat-card" style="<?php echo ($total_pending > 0) ? 'border-top-color: #C0392B;' : ''; ?>">
                    <div class="icon"><i class="fas fa-clipboard-list" style="<?php echo ($total_pending > 0) ? 'color: #C0392B;' : ''; ?>"></i></div>
                    <div class="info">
                        <h3 style="<?php echo ($total_pending > 0) ? 'color: #C0392B;' : ''; ?>"><?php echo $total_pending; ?> รายการ</h3>
                        <p>คำขอจองรอตรวจสอบ</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-list-alt"></i></div>
                    <div class="info">
                        <h3><?php echo $total_services; ?> รายการ</h3>
                        <p>บริการ/กิจกรรมในวัด</p>
                    </div>
                </div>
            </div>

            <!-- ตารางแสดงรายการจองล่าสุดเฉพาะวัดนี้ -->
            <div class="panel">
                <div class="panel-header">
                    <h3><i class="fas fa-bell" style="color: var(--primary-color); margin-right: 8px;"></i> คำขอจองบริการล่าสุด (วัดของคุณ)</h3>
                    <a href="manage_temple_bookings.php" style="font-size: 13px; color: var(--primary-color); text-decoration: none;">ดูทั้งหมด <i class="fas fa-arrow-right"></i></a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>เวลาที่ขอ</th>
                            <th>ชื่อบริการ</th>
                            <th>ผู้ติดต่อ</th>
                            <th>เบอร์โทรศัพท์</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_bookings) > 0): ?>
                            <?php foreach ($recent_bookings as $b): ?>
                                <tr>
                                    <td style="color: var(--text-muted);"><?php echo date('d/m H:i', strtotime($b['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($b['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($b['contact_name']); ?></td>
                                    <td><?php echo htmlspecialchars($b['contact_phone']); ?></td>
                                    <td style="text-align: right;"><a href="manage_temple_bookings.php" class="btn-view">ตรวจสอบ</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">ไม่มีรายการจองค้างตรวจสอบภายในวัดของคุณ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>