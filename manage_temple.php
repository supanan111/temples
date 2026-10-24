<?php
session_start();
require_once 'db_config.php';

// 1. ตรวจสอบสิทธิ์การใช้งาน
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['full_name'];
$is_superadmin = ($_SESSION['role'] === 'superadmin');

// 2. จัดการเรื่อง active_temple_id
if ($is_superadmin) {
    if (empty($_SESSION['active_temple_id'])) {
        $stmt_first = $pdo->query("SELECT id FROM temples ORDER BY id ASC LIMIT 1");
        $_SESSION['active_temple_id'] = $stmt_first->fetchColumn() ?: 1;
    }
    $admin_temple_id = $_SESSION['active_temple_id'];
} else {
    $admin_temple_id = isset($_SESSION['temple_id']) ? $_SESSION['temple_id'] : 1;
}

$message = '';

// 3. บันทึกการแก้ไขข้อมูลวัด
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_temple'])) {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $history = trim($_POST['history']);
    $promptpay_no = trim($_POST['promptpay_no']);
    $promptpay_name = trim($_POST['promptpay_name']);

    try {
        $stmt_update = $pdo->prepare("UPDATE temples SET name = ?, location = ?, history = ?, promptpay_no = ?, promptpay_name = ? WHERE id = ?");
        $stmt_update->execute([$name, $location, $history, $promptpay_no, $promptpay_name, $admin_temple_id]);
        $message = "✅ บันทึกข้อมูลวัดเรียบร้อยแล้ว!";
    } catch (PDOException $e) {
        $message = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// 4. ดึงข้อมูลวัดปัจจุบัน
try {
    $stmt = $pdo->prepare("SELECT * FROM temples WHERE id = ?");
    $stmt->execute([$admin_temple_id]);
    $temple = $stmt->fetch();
    $temple_name = $temple['name'] ?? 'ระบบจัดการวัด';
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูลวัด: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลวัด - <?php echo htmlspecialchars($temple_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-responsive.css">
    <script src="assets/js/admin-responsive.js" defer></script>
    
    <style>
        :root { 
            --sidebar-bg: #2B2522; --sidebar-hover: #3E352F; --primary-color: #C08221; 
            --bg-color: #FDFBF7; --text-main: #3E352F; --text-muted: #857B74; --card-bg: #FFFFFF; --border-color: #EAE5DE;
        }
        body { font-family: 'Prompt', sans-serif; margin: 0; padding: 0; display: flex; background-color: var(--bg-color); color: var(--text-main); min-height: 100vh; }
        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: #EAE5DE; display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0,0,0,0.05); }
        .sidebar-header { padding: 25px 20px; text-align: center; background-color: #1F1A18; font-size: 16px; font-weight: 500; color: var(--primary-color); border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sidebar-menu { list-style: none; padding: 10px 0; margin: 0; flex-grow: 1; }
        .sidebar-menu a { color: #BDB5AF; text-decoration: none; padding: 14px 25px; display: block; transition: 0.2s; font-size: 14px; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--sidebar-hover); color: white; border-left: 3px solid var(--primary-color); }
        .sidebar-menu i { margin-right: 12px; width: 20px; text-align: center; opacity: 0.8;}
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-x: hidden; }
        .topbar { background-color: var(--card-bg); padding: 15px 35px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        .logout-btn { color: #C0392B; text-decoration: none; margin-left: 20px; font-weight: 500; border: 1px solid #C0392B; padding: 6px 15px; border-radius: 4px; transition: 0.2s; }
        .logout-btn:hover { background: #C0392B; color: white; }
        .content-container { padding: 35px; max-width: 1000px; margin: 0 auto; width: 100%; box-sizing: border-box;}
        
        .data-card { background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 30px; margin-bottom: 25px; border: 1px solid var(--border-color); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; margin-bottom: 8px; color: #34495e; font-weight: 500; }
        input[type="text"], textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-family: 'Prompt', sans-serif; box-sizing: border-box; font-size: 14px; }
        textarea { resize: vertical; min-height: 180px; }
        .btn-submit { background-color: var(--primary-color); color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 15px; transition: 0.2s; font-weight: 600; width: 100%; }
        .btn-submit:hover { opacity: 0.9; }
        .alert { padding: 15px; border-radius: 5px; background: #e8f8f5; color: #27ae60; margin-bottom: 20px; border: 1px solid #a3e4d7; font-size: 14px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header"><?php echo htmlspecialchars($temple_name); ?></div>

        <?php if ($is_superadmin): ?>
            <div style="padding: 12px 15px; background: #1F1A18; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <label style="color: #857B74; font-size: 11px; display: block; margin-bottom: 5px;">จัดการในนามวัด:</label>
                <select onchange="location = this.value;" style="width: 100%; padding: 6px 8px; border-radius: 4px; background: #2B2522; color: #FFF; border: 1px solid #3E352F; font-family: 'Prompt'; font-size: 13px; cursor: pointer;">
                    <?php
                    $stmt_all = $pdo->query("SELECT id, name FROM temples ORDER BY id ASC");
                    while ($t = $stmt_all->fetch()) {
                        $selected = ($t['id'] == $admin_temple_id) ? 'selected' : '';
                        echo "<option value='switch_temple.php?id={$t['id']}' {$selected}>{$t['name']}</option>";
                    }
                    ?>
                </select>
            </div>
        <?php endif; ?>

        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
            <li><a href="edit_temple.php<?php echo $is_superadmin ? '?id=' . rawurlencode((string)$admin_temple_id) : ''; ?>"><i class="fas fa-pen-to-square"></i> แก้ไขหน้าเว็บไซต์วัด</a></li>
            <?php if ($is_superadmin): ?>
                <li><a href="manage_temples.php"><i class="fas fa-university"></i> จัดการวัดทั้งหมด</a></li>
            <?php endif; ?>
            <li><a href="manage_bookings.php"><i class="fas fa-calendar-check"></i> ตรวจสอบการจองศาลา</a></li>
            <li><a href="manage_items.php"><i class="fas fa-box-open"></i> จัดการบริการ/สถานที่</a></li>
            <li><a href="manage_events.php"><i class="fas fa-calendar-alt"></i> จัดการปฏิทินกิจกรรม</a></li>
            <li><a href="manage_donations.php"><i class="fas fa-donate"></i> ข้อมูล E-Donation</a></li>
            <li><a href="add_news.php"><i class="fas fa-bullhorn"></i> จัดการข่าวสาร/ประชาสัมพันธ์</a></li>
            <li><a href="manage_temple.php" class="active"><i class="fas fa-vihara"></i> จัดการข้อมูลวัด</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div><i class="fas fa-bars" style="color: var(--text-muted);"></i></div>
            <div style="display: flex; align-items: center;">
                <span style="color: var(--text-muted);">ผู้ดูแลระบบ:</span> <strong style="margin-left:5px;"><?php echo htmlspecialchars($admin_name); ?></strong>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
            </div>
        </div>

        <div class="content-container">
            <h2><i class="fas fa-info-circle" style="color: var(--primary-color);"></i> แก้ไขรายละเอียดและประวัติวัด</h2>

            <?php if (!empty($message)): ?>
                <div class="alert"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="data-card">
                <form action="manage_temple.php" method="POST">
                    <input type="hidden" name="update_temple" value="1">
                    
                    <div class="form-group">
                        <label>ชื่อวัด</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($temple['name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>ที่อยู่ / โลเคชั่น</label>
                        <input type="text" name="location" value="<?php echo htmlspecialchars($temple['location'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>ประวัติและความเป็นมาของวัด</label>
                        <textarea name="history"><?php echo htmlspecialchars($temple['history'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group" style="display: flex; gap: 15px;">
                        <div style="flex: 1;">
                            <label>หมายเลข PromptPay (รับบริจาค)</label>
                            <input type="text" name="promptpay_no" value="<?php echo htmlspecialchars($temple['promptpay_no'] ?? ''); ?>">
                        </div>
                        <div style="flex: 1;">
                            <label>ชื่อบัญชี PromptPay</label>
                            <input type="text" name="promptpay_name" value="<?php echo htmlspecialchars($temple['promptpay_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึกข้อมูลวัด</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>