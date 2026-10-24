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

// 3. ดึงชื่อวัดมาแสดงที่เมนูด้านซ้าย
try {
    $stmt_temple = $pdo->prepare("SELECT name FROM temples WHERE id = :id");
    $stmt_temple->execute([':id' => $admin_temple_id]);
    $temple_name = $stmt_temple->fetchColumn();
    if(empty($temple_name)) { $temple_name = "ระบบจัดการวัด"; }
} catch (PDOException $e) {
    $temple_name = "ระบบจัดการวัด";
}

// 4. จัดการ Action (เปิด/ปิด การจอง หรือ ลบรายการ)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $item_id = $_GET['id'];
    $action = $_GET['action'];
    try {
        if ($action == 'toggle') {
            $stmt = $pdo->prepare("UPDATE bookable_items SET is_active = IF(is_active='yes', 'no', 'yes') WHERE id = :id AND temple_id = :temple_id");
            $stmt->execute([':id' => $item_id, ':temple_id' => $admin_temple_id]);
            echo "<script>window.location.href='manage_items.php';</script>"; 
            exit();
        } elseif ($action == 'delete') {
            $stmt = $pdo->prepare("DELETE FROM bookable_items WHERE id = :id AND temple_id = :temple_id");
            $stmt->execute([':id' => $item_id, ':temple_id' => $admin_temple_id]);
            echo "<script>alert('ลบรายการสำเร็จ'); window.location.href='manage_items.php';</script>"; 
            exit();
        }
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            echo "<script>alert('ลบไม่ได้เนื่องจากมีประวัติการจองอยู่ แนะนำให้ปิดการจองแทนครับ'); window.location.href='manage_items.php';</script>";
        } else { 
            die("เกิดข้อผิดพลาด: " . $e->getMessage()); 
        }
    }
}

// 5. จัดการเพิ่มรายการใหม่
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_item'])) {
    $item_type = $_POST['item_type'];
    $item_name = $_POST['item_name'];
    $total_qty = $_POST['total_qty'];
    $price = $_POST['price']; 
    
    try {
        $stmt = $pdo->prepare("INSERT INTO bookable_items (temple_id, item_type, item_name, total_qty, price, is_active) VALUES (:temple_id, :item_type, :item_name, :total_qty, :price, 'yes')");
        $stmt->execute([':temple_id' => $admin_temple_id, ':item_type' => $item_type, ':item_name' => $item_name, ':total_qty' => $total_qty, ':price' => $price]);
        echo "<script>alert('เพิ่มรายการใหม่เรียบร้อยแล้ว'); window.location.href='manage_items.php';</script>"; 
        exit();
    } catch (PDOException $e) { 
        die("เกิดข้อผิดพลาด: " . $e->getMessage()); 
    }
}

// 6. ดึงรายการบริการ/สถานที่ ของวัดที่กำลังจัดการอยู่
$stmt = $pdo->prepare("SELECT * FROM bookable_items WHERE temple_id = :temple_id ORDER BY item_type ASC, id DESC");
$stmt->execute([':temple_id' => $admin_temple_id]);
$items = $stmt->fetchAll();

$type_names = [
    'pavilion_funeral' => 'ศาลาทำพิธีศพ', 
    'pavilion_merit' => 'ศาลาทำบุญเลี้ยงพระ', 
    'monk' => 'นิมนต์พระสงฆ์', 
    'equipment' => 'ยืมอุปกรณ์/ของใช้', 
    'fair_stall' => 'ล็อคงานวัดประจำปี'
];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบริการและสถานที่ - <?php echo htmlspecialchars($temple_name); ?></title>
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
        
        .add-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 30px; border-top: 4px solid #27ae60; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-left: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); }
        .form-row { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 150px; margin-bottom: 0; }
        .form-group label { display: block; font-size: 14px; margin-bottom: 5px; font-weight: 500; }
        select, input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: 'Prompt', sans-serif; box-sizing: border-box; }
        .btn-submit { background: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; height: 42px; font-family: 'Prompt', sans-serif; font-weight: 500; }
        .btn-submit:hover { background: #219a52; }
        
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid var(--border-color); }
        th { background: #f8f9fa; padding: 15px; border-bottom: 2px solid #ddd; text-align: left; color: var(--text-main); }
        td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .badge-type { background: #eaf2f8; color: #2980b9; padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: 500; }
        .btn-toggle { padding: 6px 12px; border-radius: 20px; text-decoration: none; font-size: 13px; font-weight: 500; border: 1px solid transparent; display: inline-block; }
        .toggle-on { background: #e8f8f5; color: #27ae60; border-color: #27ae60; }
        .toggle-off { background: #fdedec; color: #e74c3c; border-color: #e74c3c; }
        .btn-delete { color: #e74c3c; text-decoration: none; margin-left: 10px; font-size: 14px; font-weight: 500; }
        .btn-delete:hover { text-decoration: underline; }
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
            <li><a href="manage_items.php" class="active"><i class="fas fa-box-open"></i> จัดการบริการ/สถานที่</a></li>
            <li><a href="manage_events.php"><i class="fas fa-calendar-alt"></i> จัดการปฏิทินกิจกรรม</a></li>
            <li><a href="manage_donations.php"><i class="fas fa-donate"></i> ข้อมูล E-Donation</a></li>
            <li><a href="add_news.php"><i class="fas fa-bullhorn"></i> จัดการข่าวสาร/ประชาสัมพันธ์</a></li>
            <li><a href="manage_temple.php"><i class="fas fa-vihara"></i> จัดการข้อมูลวัด</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div><i class="fas fa-bars" style="color: var(--text-muted);"></i></div>
            <div>
                <span style="color: var(--text-muted);">ผู้ดูแลระบบ:</span> <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
            </div>
        </div>

        <div class="content-container">
            <div class="page-header">
                <h2><i class="fas fa-box-open"></i> จัดการข้อมูลบริการ และ สถานที่ (<?php echo htmlspecialchars($temple_name); ?>)</h2>
            </div>
            
            <div class="add-card">
                <h3 style="margin-top:0; color:#27ae60; font-size: 18px;"><i class="fas fa-plus-circle"></i> เพิ่มรายการใหม่</h3>
                <form action="manage_items.php" method="POST" class="form-row">
                    <input type="hidden" name="add_item" value="1">
                    <div class="form-group">
                        <label>หมวดหมู่</label>
                        <select name="item_type" required>
                            <option value="pavilion_funeral">ศาลาทำพิธีศพ</option>
                            <option value="pavilion_merit">ศาลาทำบุญเลี้ยงพระ</option>
                            <option value="monk">นิมนต์พระสงฆ์</option>
                            <option value="equipment">ยืมอุปกรณ์/ของใช้</option>
                            <option value="fair_stall">ล็อคงานวัดประจำปี</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <label>ชื่อรายการ</label>
                        <input type="text" name="item_name" required placeholder="เช่น ล็อคโซนอาหาร / ศาลา 1">
                    </div>
                    <div class="form-group" style="flex: 0.5;">
                        <label>จำนวน/ล็อค</label>
                        <input type="number" name="total_qty" required min="1" value="1">
                    </div>
                    <div class="form-group" style="flex: 0.5;">
                        <label>ราคา (บาท)</label>
                        <input type="number" name="price" required min="0" value="0">
                    </div>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึก</button>
                </form>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>หมวดหมู่</th>
                        <th>ชื่อบริการ</th>
                        <th style="text-align:center;">จำนวน</th>
                        <th>ราคา/หน่วย</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($items) > 0): ?>
                        <?php foreach ($items as $row): ?>
                            <tr>
                                <td><span class="badge-type"><?php echo $type_names[$row['item_type']] ?? $row['item_type']; ?></span></td>
                                <td style="font-weight: 500;"><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td style="text-align:center; font-weight:bold; color:#d35400;"><?php echo $row['total_qty']; ?></td>
                                <td style="color:#27ae60; font-weight:bold;">฿<?php echo number_format($row['price'], 2); ?></td>
                                <td>
                                    <?php if($row['is_active'] == 'yes'): ?>
                                        <a href="manage_items.php?action=toggle&id=<?php echo $row['id']; ?>" class="btn-toggle toggle-on"><i class="fas fa-check-circle"></i> เปิดอยู่</a>
                                    <?php else: ?>
                                        <a href="manage_items.php?action=toggle&id=<?php echo $row['id']; ?>" class="btn-toggle toggle-off"><i class="fas fa-minus-circle"></i> ปิดการจอง</a>
                                    <?php endif; ?>
                                </td>
                                <td><a href="manage_items.php?action=delete&id=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('ลบรายการนี้?');"><i class="fas fa-trash"></i> ลบ</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #7f8c8d;">
                                ไม่พบข้อมูลรายการบริการหรือสถานที่ในวัดนี้
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>