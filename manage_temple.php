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
$error = '';

// 3. บันทึกการแก้ไขข้อมูลวัด
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_temple'])) {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_account_no = trim($_POST['bank_account_no'] ?? '');
    $bank_account_name = trim($_POST['bank_account_name'] ?? '');
    $promptpay_id = trim($_POST['promptpay_id'] ?? '');

    // อัปโหลด QR Code ชำระเงิน (ถ้ามี)
    $qr_code_path = null;
    if (!empty($_FILES['qr_code_image']['name']) && $_FILES['qr_code_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['qr_code_image'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) finfo_close($finfo);

        if (!isset($allowed[$mime]) || $file['size'] > 5 * 1024 * 1024) {
            $error = 'ไฟล์ QR Code ต้องเป็น JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB';
        } else {
            $qr_dir = __DIR__ . '/uploads/qrcodes';
            if (!is_dir($qr_dir)) @mkdir($qr_dir, 0775, true);
            $filename = 'qr_' . $admin_temple_id . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
            if (move_uploaded_file($file['tmp_name'], $qr_dir . '/' . $filename)) {
                $qr_code_path = 'uploads/qrcodes/' . $filename;
            }
        }
    }

    if (empty($error)) {
        try {
            if ($qr_code_path !== null) {
                $stmt_update = $pdo->prepare("
                    UPDATE temples 
                    SET name = ?, address = ?, description = ?, 
                        bank_name = ?, bank_account_no = ?, bank_account_name = ?, 
                        promptpay_id = ?, qr_code_image = ? 
                    WHERE id = ?
                ");
                $stmt_update->execute([
                    $name, $address, $description,
                    $bank_name ?: null, $bank_account_no ?: null, $bank_account_name ?: null,
                    $promptpay_id ?: null, $qr_code_path, $admin_temple_id
                ]);
            } else {
                $stmt_update = $pdo->prepare("
                    UPDATE temples 
                    SET name = ?, address = ?, description = ?, 
                        bank_name = ?, bank_account_no = ?, bank_account_name = ?, 
                        promptpay_id = ? 
                    WHERE id = ?
                ");
                $stmt_update->execute([
                    $name, $address, $description,
                    $bank_name ?: null, $bank_account_no ?: null, $bank_account_name ?: null,
                    $promptpay_id ?: null, $admin_temple_id
                ]);
            }
            $message = "✅ บันทึกข้อมูลวัดและบัญชีชำระเงินเรียบร้อยแล้ว!";
        } catch (PDOException $e) {
            $error = "❌ เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
        }
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
    <title>แก้ไขข้อมูลวัดและบัญชีชำระเงิน - <?php echo htmlspecialchars($temple_name); ?></title>
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
        .card-header-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); font-size: 16px; font-weight: 600; color: #2B2522; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; margin-bottom: 8px; color: #34495e; font-weight: 500; }
        .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        input[type="text"], select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-family: 'Prompt', sans-serif; box-sizing: border-box; font-size: 14px; }
        textarea { resize: vertical; min-height: 140px; }
        .btn-submit { background-color: var(--primary-color); color: white; border: none; padding: 14px 25px; border-radius: 5px; cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 16px; transition: 0.2s; font-weight: 600; width: 100%; margin-top: 10px; }
        .btn-submit:hover { opacity: 0.9; }
        .alert-success { padding: 15px; border-radius: 5px; background: #e8f8f5; color: #27ae60; margin-bottom: 20px; border: 1px solid #a3e4d7; font-size: 14px; }
        .alert-error { padding: 15px; border-radius: 5px; background: #fde8e8; color: #c0392b; margin-bottom: 20px; border: 1px solid #f5c6cb; font-size: 14px; }
        .qr-preview-box { display: flex; align-items: center; gap: 20px; margin-top: 10px; padding: 15px; background: #FDFBF7; border: 1px dashed var(--border-color); border-radius: 8px; }
        .qr-preview-img { width: 120px; height: 120px; object-fit: contain; border-radius: 6px; background: #fff; padding: 5px; border: 1px solid #e0e0e0; }
        @media (max-width: 640px) {
            .form-row-2 { grid-template-columns: 1fr; }
        }
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
            <li><a href="manage_bookings.php"><i class="fas fa-calendar-check"></i> ตรวจสอบการจอง</a></li>
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
            <h2><i class="fas fa-university" style="color: var(--primary-color);"></i> จัดการข้อมูลวัดและระบบการชำระเงิน</h2>

            <?php if (!empty($message)): ?>
                <div class="alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form action="manage_temple.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_temple" value="1">

                <!-- การ์ดที่ 1: ข้อมูลทั่วไปของวัด -->
                <div class="data-card">
                    <div class="card-header-bar">
                        <i class="fas fa-info-circle" style="color: var(--primary-color);"></i> ข้อมูลทั่วไปของวัด
                    </div>

                    <div class="form-group">
                        <label>ชื่อวัด <span style="color:red;">*</span></label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($temple['name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>ที่อยู่ / ที่ตั้งวัด <span style="color:red;">*</span></label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($temple['address'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>ประวัติและความเป็นมาของวัด</label>
                        <textarea name="description"><?php echo htmlspecialchars($temple['description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- การ์ดที่ 2: บัญชีธนาคารและระบบชำระเงิน/รับบริจาค -->
                <div class="data-card">
                    <div class="card-header-bar">
                        <i class="fas fa-credit-card" style="color: var(--primary-color);"></i> ช่องทางการชำระเงินและรับบริจาคของวัด
                    </div>

                    <p style="color: var(--text-muted); font-size: 13px; margin-top: -5px; margin-bottom: 20px;">
                        ข้อมูลนี้จะแสดงให้ผู้ใช้เห็นเมื่อทำการจองบริการ (ศาลา, ล็อคงานวัด, ยืมของ) หรือเมื่อร่วมบริจาค เพื่อใช้โอนเงินและแนบสลิปตรวจสอบ
                    </p>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label>ธนาคาร</label>
                            <select name="bank_name">
                                <option value="">-- เลือกธนาคาร --</option>
                                <?php
                                $banks = [
                                    'ธนาคารกสิกรไทย' => 'กสิกรไทย (KBANK)',
                                    'ธนาคารไทยพาณิชย์' => 'ไทยพาณิชย์ (SCB)',
                                    'ธนาคารกรุงไทย' => 'กรุงไทย (KTB)',
                                    'ธนาคารกรุงเทพ' => 'กรุงเทพ (BBL)',
                                    'ธนาคารกรุงศรีอยุธยา' => 'กรุงศรีอยุธยา (BAY)',
                                    'ธนาคารทหารไทยธนชาต' => 'ทหารไทยธนชาต (ttb)',
                                    'ธนาคารออมสิน' => 'ออมสิน (GSB)',
                                    'ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร' => 'ธ.ก.ส. (BAAC)',
                                    'ธนาคารเกียรตินาคินภัทร' => 'เกียรตินาคินภัทร (KKP)',
                                    'ธนาคาร ซีไอเอ็มบี ไทย' => 'ซีไอเอ็มบี ไทย (CIMB)'
                                ];
                                $cur_bank = $temple['bank_name'] ?? '';
                                foreach ($banks as $b_val => $b_label) {
                                    $sel = ($cur_bank === $b_val) ? 'selected' : '';
                                    echo "<option value=\"{$b_val}\" {$sel}>{$b_label}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>เลขที่บัญชีธนาคาร</label>
                            <input type="text" name="bank_account_no" value="<?php echo htmlspecialchars($temple['bank_account_no'] ?? ''); ?>" placeholder="เช่น 123-4-56789-0">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label>ชื่อบัญชีธนาคาร</label>
                            <input type="text" name="bank_account_name" value="<?php echo htmlspecialchars($temple['bank_account_name'] ?? ''); ?>" placeholder="เช่น วัดพระเชตุพนวิมลมังคลาราม">
                        </div>

                        <div class="form-group">
                            <label>หมายเลข PromptPay (เบอร์โทร 10 หลัก หรือเลขบัตร/นิติบุคคล 13 หลัก)</label>
                            <input type="text" name="promptpay_id" value="<?php echo htmlspecialchars($temple['promptpay_id'] ?? ''); ?>" placeholder="เช่น 0812345678 หรือ 0994000123456">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>รูปภาพ QR Code ชำระเงิน / พร้อมเพย์</label>
                        <input type="file" name="qr_code_image" accept="image/jpeg,image/png,image/webp">
                        <small style="display:block; margin-top: 5px; color: var(--text-muted);">
                            รองรับไฟล์ JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB (ผู้ใช้จะสามารถสแกนชำระเงินผ่านแอปธนาคารได้ทันที)
                        </small>

                        <?php if (!empty($temple['qr_code_image']) && file_exists(__DIR__ . '/' . $temple['qr_code_image'])): ?>
                            <div class="qr-preview-box">
                                <img src="<?php echo htmlspecialchars($temple['qr_code_image']); ?>" alt="QR Code ของวัด" class="qr-preview-img">
                                <div>
                                    <strong style="color: #27ae60;"><i class="fas fa-check-circle"></i> มีรูป QR Code ในระบบแล้ว</strong><br>
                                    <span style="font-size: 12px; color: var(--text-muted);">หากต้องการเปลี่ยน ให้เลือกไฟล์รูปภาพใหม่ด้านบนแล้วกดบันทึก</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึกข้อมูลวัดและบัญชีชำระเงิน</button>
            </form>
        </div>
    </div>
</body>
</html>