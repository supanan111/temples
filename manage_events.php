<?php
session_start();
require_once 'db_config.php';

// 1. ตรวจสอบสิทธิ์การใช้งาน (อนุญาตให้ทั้ง admin และ superadmin เข้าใช้งานได้)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['full_name'];
$is_superadmin = ($_SESSION['role'] === 'superadmin');

// 2. จัดการเรื่อง active_temple_id สำหรับ Super Admin และ Admin ทั่วไป
if ($is_superadmin) {
    if (empty($_SESSION['active_temple_id'])) {
        $stmt_first_temple = $pdo->query("SELECT id FROM temples ORDER BY id ASC LIMIT 1");
        $_SESSION['active_temple_id'] = $stmt_first_temple->fetchColumn() ?: 1;
    }
    $admin_temple_id = $_SESSION['active_temple_id'];
} else {
    $admin_temple_id = isset($_SESSION['temple_id']) ? $_SESSION['temple_id'] : 1;
}

// 3. ดึงชื่อวัดมาแสดงที่เมนูด้านซ้ายและส่วนหัว
try {
    $stmt_temple = $pdo->prepare("SELECT name FROM temples WHERE id = :id");
    $stmt_temple->execute([':id' => $admin_temple_id]);
    $temple_name = $stmt_temple->fetchColumn();
    if(empty($temple_name)) { $temple_name = "ระบบจัดการวัด"; }
} catch (PDOException $e) {
    $temple_name = "ระบบจัดการวัด";
}

// 4. บันทึกกิจกรรมใหม่
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_event'])) {
    $event_name_input = trim($_POST['event_name']);
    $description = trim($_POST['description'] ?? '');
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];

    try {
        $stmt = $pdo->prepare("INSERT INTO events (temple_id, event_name, description, event_date, event_time) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$admin_temple_id, $event_name_input, $description, $event_date, $event_time]);
        echo "<script>alert('เพิ่มกิจกรรมเรียบร้อยแล้ว'); window.location.href='manage_events.php';</script>";
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage());
    }
}

// 5. ลบกิจกรรม
if (isset($_GET['delete'])) {
    $event_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND temple_id = ?");
        $stmt->execute([$event_id, $admin_temple_id]);
        echo "<script>window.location.href='manage_events.php';</script>";
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการลบ: " . $e->getMessage());
    }
}

// 6. ดึงข้อมูลกิจกรรมของวัดที่เลือก
try {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE temple_id = :temple_id ORDER BY event_date ASC, event_time ASC");
    $stmt->execute([':temple_id' => $admin_temple_id]);
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการปฏิทินกิจกรรม - <?php echo htmlspecialchars($temple_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-responsive.css">
    <script src="assets/js/admin-responsive.js" defer></script>
    
    <style>
        /* 🌟 Theme พุทธศิลป์ สำหรับ Admin 🌟 */
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
        .sidebar-header { padding: 25px 20px; text-align: center; background-color: #1F1A18; font-size: 16px; font-weight: 500; color: var(--primary-color); border-bottom: 1px solid rgba(255,255,255,0.05); letter-spacing: 0.5px;}
        .sidebar-menu { list-style: none; padding: 10px 0; margin: 0; flex-grow: 1; }
        .sidebar-menu a { color: #BDB5AF; text-decoration: none; padding: 14px 25px; display: block; transition: 0.2s; font-size: 14px; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--sidebar-hover); color: white; border-left: 3px solid var(--primary-color); }
        .sidebar-menu i { margin-right: 12px; width: 20px; text-align: center; opacity: 0.8;}
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-x: hidden; }
        .topbar { background-color: var(--card-bg); padding: 15px 35px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        .logout-btn { color: #C0392B; text-decoration: none; margin-left: 20px; font-weight: 500; border: 1px solid #C0392B; padding: 6px 15px; border-radius: 4px; transition: 0.2s; }
        .logout-btn:hover { background: #C0392B; color: white; }
        .content-container { padding: 35px; max-width: 1200px; margin: 0 auto; width: 100%; box-sizing: border-box;}
        .page-header { margin-bottom: 30px; }
        .page-header h2 { margin: 0; color: var(--text-main); font-weight: 500; font-size: 24px; }

        .data-card { background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 25px; border: 1px solid var(--border-color); }
        .form-row { display: grid; grid-template-columns: 1fr 2fr 1fr 1fr; gap: 15px; align-items: end; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-size: 14px; margin-bottom: 5px; color: #34495e; font-weight: 500; }
        input[type="text"], input[type="date"], input[type="time"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: 'Prompt', sans-serif; box-sizing: border-box; }
        
        .btn-submit { background-color: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 15px; transition: 0.2s; height: 42px; width: 100%; font-weight: 500; }
        .btn-submit:hover { background-color: #219a52; }
        .btn-delete { background-color: #e74c3c; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-delete:hover { background-color: #c0392b; }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: #f8f9fa; padding: 15px; border-bottom: 2px solid #ddd; color: #2c3e50; font-weight: 600; font-size: 15px; }
        td { padding: 15px; border-bottom: 1px solid #eee; color: #555; vertical-align: middle; font-size: 15px; }
        tr:hover { background-color: #fcfcfc; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header"><?php echo htmlspecialchars($temple_name); ?></div>

        <!-- 🌟 Dropdown สลับวัดสำหรับ Super Admin 🌟 -->
        <?php if ($is_superadmin): ?>
            <div style="padding: 12px 15px; background: #1F1A18; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <label style="color: #857B74; font-size: 11px; display: block; margin-bottom: 5px;">จัดการในนามวัด:</label>
                <select onchange="location = this.value;" style="width: 100%; padding: 6px 8px; border-radius: 4px; background: #2B2522; color: #FFF; border: 1px solid #3E352F; font-family: 'Prompt'; font-size: 13px; cursor: pointer;">
                    <?php
                    $stmt_all_temples = $pdo->query("SELECT id, name FROM temples ORDER BY id ASC");
                    while ($t = $stmt_all_temples->fetch()) {
                        $selected = ($t['id'] == $admin_temple_id) ? 'selected' : '';
                        echo "<option value='switch_temple.php?id={$t['id']}' {$selected}>{$t['name']}</option>";
                    }
                    ?>
                </select>
            </div>
        <?php endif; ?>

        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
            <li><a href="edit_temple.php"><i class="fas fa-pen-to-square"></i> แก้ไขหน้าเว็บไซต์วัด</a></li>
            <?php if ($is_superadmin): ?>
                <li><a href="manage_temples.php"><i class="fas fa-university"></i> จัดการวัดทั้งหมด</a></li>
            <?php endif; ?>
            <li><a href="manage_bookings.php"><i class="fas fa-calendar-check"></i> ตรวจสอบการจองศาลา</a></li>
            <li><a href="manage_items.php"><i class="fas fa-box-open"></i> จัดการบริการ/สถานที่</a></li>
            <li><a href="manage_events.php" class="active"><i class="fas fa-calendar-alt"></i> จัดการปฏิทินกิจกรรม</a></li>
            <li><a href="manage_donations.php"><i class="fas fa-donate"></i> ข้อมูล E-Donation</a></li>
            <li><a href="add_news.php"><i class="fas fa-bullhorn"></i> จัดการข่าวสาร/ประชาสัมพันธ์</a></li>
            <li><a href="manage_temple.php"><i class="fas fa-vihara"></i> จัดการข้อมูลวัด</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div><i class="fas fa-bars" style="color: var(--text-muted);"></i></div>
            <div style="display: flex; align-items: center;">
                <span style="color: var(--text-muted);">ผู้ดูแลระบบ:</span> <strong style="margin-left: 5px;"><?php echo htmlspecialchars($admin_name); ?></strong>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
            </div>
        </div>

        <div class="content-container">
            <div class="page-header">
                <h2><i class="fas fa-calendar-alt"></i> จัดการปฏิทินกิจกรรมวัด (<?php echo htmlspecialchars($temple_name); ?>)</h2>
            </div>

            <div class="data-card" style="border-top: 4px solid #27ae60;">
                <h3 style="margin-top: 0; color: #27ae60; font-size: 16px; margin-bottom: 15px;"><i class="fas fa-plus-circle"></i> เพิ่มกิจกรรมใหม่</h3>
                <form action="manage_events.php" method="POST" class="form-row">
                    <input type="hidden" name="add_event" value="1">
                    <div class="form-group">
                        <label>วันที่จัดงาน</label>
                        <input type="date" name="event_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label>ชื่องาน / กิจกรรม</label>
                        <input type="text" name="event_name" required placeholder="เช่น งานทำบุญตักบาตร...">
                    </div>
                    
                    <div class="form-group">
                        <label>เวลา (ถ้ามี)</label>
                        <input type="time" name="event_time">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึกกิจกรรม</button>
                    </div>
                </form>
            </div>

            <div class="data-card" style="padding: 0; overflow: hidden;">
                <table>
                    <thead>
                        <tr>
                            <th>วันที่จัดงาน</th>
                            <th>เวลา</th>
                            <th>ชื่องาน / กิจกรรม</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($events) > 0): ?>
                            <?php foreach ($events as $row): ?>
                                <tr>
                                    <td style="font-weight: 600; color: #d35400;">
                                        <?php echo date('d/m/Y', strtotime($row['event_date'])); ?>
                                    </td>
                                    <td style="color: #7f8c8d;">
                                        <?php echo !empty($row['event_time']) ? date('H:i', strtotime($row['event_time'])) . ' น.' : '-'; ?>
                                    </td>
                                    
                                    <td style="font-weight: 500; color: #2c3e50;">
                                        <?php 
                                            $display_name = $row['event_name'] ?? $row['title'] ?? $row['name'] ?? 'ไม่ได้ระบุชื่อกิจกรรม';
                                            echo htmlspecialchars($display_name); 
                                        ?>
                                    </td>
                                    
                                    <td>
                                        <a href="manage_events.php?delete=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('ยืนยันการลบกิจกรรมนี้ใช่หรือไม่?');">
                                            <i class="fas fa-trash-alt"></i> ลบ
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    ยังไม่มีข้อมูลกิจกรรมในระบบของวัดนี้
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