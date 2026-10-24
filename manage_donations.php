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
if (empty($_SESSION['donation_action_csrf'])) {
    $_SESSION['donation_action_csrf'] = bin2hex(random_bytes(32));
}

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

// 4. ยืนยันหรือปฏิเสธรายการหลังตรวจยอดเงินจริงจากบัญชีวัดแล้ว
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donation_action'])) {
    $submitted_token = $_POST['csrf_token'] ?? '';
    $donation_id = filter_input(INPUT_POST, 'donation_id', FILTER_VALIDATE_INT);
    $action = $_POST['donation_action'];
    if (!is_string($submitted_token) || !hash_equals($_SESSION['donation_action_csrf'], $submitted_token) ||
        !$donation_id || $donation_id < 1 || !in_array($action, ['approve', 'reject'], true)) {
        http_response_code(400);
        exit('คำขอไม่ถูกต้อง กรุณากลับไปโหลดหน้าจัดการรายการบริจาคใหม่');
    }

    try {
        if ($action === 'approve') {
            $receipt_no = 'REC-' . date('Ymd') . '-' . str_pad((string)$donation_id, 8, '0', STR_PAD_LEFT);
            $stmt_update = $pdo->prepare(
                "UPDATE donations
                 SET status = 'completed', receipt_no = ?
                 WHERE id = ? AND temple_id = ? AND status = 'pending'"
            );
            $stmt_update->execute([$receipt_no, $donation_id, $admin_temple_id]);
        } else {
            $stmt_update = $pdo->prepare(
                "UPDATE donations SET status = 'failed'
                 WHERE id = ? AND temple_id = ? AND status = 'pending'"
            );
            $stmt_update->execute([$donation_id, $admin_temple_id]);
        }
        header('Location: manage_donations.php');
        exit();
    } catch (PDOException $e) {
        error_log('Unable to update donation status: ' . $e->getMessage());
        http_response_code(500);
        exit('ไม่สามารถปรับปรุงสถานะรายการบริจาคได้ กรุณาลองใหม่อีกครั้ง');
    }
}

// 5. ดึงข้อมูลประวัติการบริจาคทั้งหมดของวัดที่กำลังจัดการอยู่
try {
    $stmt = $pdo->prepare("SELECT * FROM donations WHERE temple_id = :temple_id ORDER BY created_at DESC");
    $stmt->execute([':temple_id' => $admin_temple_id]);
    $donations = $stmt->fetchAll();
    
    // คำนวณยอดรวมที่อนุมัติแล้วของวัดนี้
    $stmt_sum = $pdo->prepare("SELECT SUM(amount) FROM donations WHERE temple_id = :temple_id AND status = 'completed'");
    $stmt_sum->execute([':temple_id' => $admin_temple_id]);
    $total_sum = $stmt_sum->fetchColumn() ?: 0;
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูล E-Donation - <?php echo htmlspecialchars($temple_name); ?></title>
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
        
        .summary-card { background: linear-gradient(135deg, #f39c12, #d35400); color: white; padding: 20px 30px; border-radius: 10px; display: inline-block; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(211, 84, 0, 0.3); }
        .summary-card h3 { margin: 0 0 5px 0; font-size: 16px; font-weight: 400; opacity: 0.9; }
        .summary-card .amount { font-size: 36px; font-weight: 600; margin: 0; }

        .data-card { background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: #f8f9fa; padding: 15px; border-bottom: 2px solid #ddd; color: #2c3e50; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #eee; color: #555; vertical-align: middle; }
        tr:hover { background-color: #fcfcfc; }
        
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-pending { background: #fef9e7; color: #f39c12; border: 1px solid #f39c12; }
        .status-completed { background: #e8f8f5; color: #27ae60; border: 1px solid #27ae60; }
        .status-rejected { background: #fdedec; color: #e74c3c; border: 1px solid #e74c3c; }

        .btn-action { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block; margin-right: 5px; transition: 0.2s; color: white; border: none; cursor: pointer; }
        .btn-approve { background-color: #27ae60; }
        .btn-approve:hover { background-color: #219a52; }
        .btn-reject { background-color: #e74c3c; }
        .btn-reject:hover { background-color: #c0392b; }
        .btn-disabled { background-color: #bdc3c7; color: white; cursor: not-allowed; }
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
            <li><a href="manage_events.php"><i class="fas fa-calendar-alt"></i> จัดการปฏิทินกิจกรรม</a></li>
            <li><a href="manage_donations.php" class="active"><i class="fas fa-donate"></i> ข้อมูล E-Donation</a></li>
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
                <h2><i class="fas fa-hand-holding-usd" style="color:#d35400;"></i> จัดการข้อมูลการบริจาค (<?php echo htmlspecialchars($temple_name); ?>)</h2>
            </div>
            
            <div class="summary-card">
                <h3>ยอดเงินบริจาคสะสม (เฉพาะที่ตรวจสอบแล้ว)</h3>
                <p class="amount">฿<?php echo number_format($total_sum, 2); ?></p>
            </div>

            <div class="data-card">
                <table>
                    <thead>
                        <tr>
                            <th>วันที่ทำรายการ</th>
                            <th>ชื่อผู้บริจาค</th>
                            <th>จำนวนเงิน (บาท)</th>
                            <th>หลักฐาน</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($donations) > 0): ?>
                            <?php foreach ($donations as $row): ?>
                                <tr>
                                    <td style="font-size: 13px; color: #95a5a6;"><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                    
                                    <td style="font-weight: 500; color: #2c3e50;">
                                        <?php echo htmlspecialchars($row['donor_name']); ?>
                                        <?php if(isset($row['is_anonymous']) && $row['is_anonymous'] == 1) echo " <small style='color:#7f8c8d;'>(ไม่ประสงค์ออกนาม)</small>"; ?>
                                        <?php if(($row['tax_deduction'] ?? 'no') === 'yes'): ?>
                                            <br><small style="color:#8a5a12;">แจ้งขอใบอนุโมทนาบัตร</small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="font-weight: 600; color: #27ae60; font-size: 16px;">
                                        ฿<?php echo number_format($row['amount'], 2); ?>
                                    </td>
                                    
                                    <td>
                                        <?php if(!empty($row['slip_image'])): ?>
                                            <a href="view_donation_slip.php?id=<?php echo (int)$row['id']; ?>" target="_blank" rel="noopener" style="color: #3498db; text-decoration: none;">
                                                <i class="fas fa-file-image"></i> ดูสลิป
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #7f8c8d;">ยังไม่มีสลิป</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if($row['status'] == 'pending'): ?>
                                            <span class="status-badge status-pending"><i class="fas fa-clock"></i> รอตรวจสอบ</span>
                                        <?php elseif($row['status'] == 'completed'): ?>
                                            <span class="status-badge status-completed"><i class="fas fa-check"></i> ได้รับยอดแล้ว</span>
                                        <?php else: ?>
                                            <span class="status-badge status-rejected"><i class="fas fa-times"></i> ไม่พบยอดโอน</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if($row['status'] == 'pending'): ?>
                                            <form method="POST" action="manage_donations.php" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['donation_action_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="donation_id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" name="donation_action" value="approve" class="btn-action btn-approve" onclick="return confirm('ยืนยันว่าได้ตรวจสอบและได้รับยอดเงินจริงแล้ว?');">ยืนยันยอด</button>
                                                <button type="submit" name="donation_action" value="reject" class="btn-action btn-reject" onclick="return confirm('ยืนยันว่าไม่พบยอดโอนสำหรับรายการนี้?');">ไม่พบยอด</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="btn-action btn-disabled">ดำเนินการแล้ว</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    <i class="fas fa-donate fa-3x" style="margin-bottom: 15px; color:#bdc3c7;"></i><br>
                                    ยังไม่มีประวัติการบริจาคในขณะนี้
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