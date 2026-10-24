<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: login.php');
    exit();
}

$error = '';
$form = [
    'name' => '',
    'address' => '',
    'province' => '',
    'district' => '',
    'latitude' => '',
    'longitude' => '',
    'description' => '',
];

if (empty($_SESSION['add_temple_csrf'])) {
    $_SESSION['add_temple_csrf'] = bin2hex(random_bytes(32));
}

try {
    $stmt_user = $pdo->prepare("SELECT full_name, profile_image FROM users WHERE id = ?");
    $stmt_user->execute([$_SESSION['user_id']]);
    $admin = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $admin_name = $admin['full_name'] ?? ($_SESSION['full_name'] ?? 'ผู้ดูแลระบบ');
    $profile_image = $admin['profile_image'] ?? '';
} catch (PDOException $e) {
    error_log('Unable to load superadmin profile: ' . $e->getMessage());
    http_response_code(500);
    die('ไม่สามารถโหลดข้อมูลผู้ดูแลระบบได้ กรุณาลองใหม่อีกครั้ง');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $value) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            $form[$key] = trim($_POST[$key]);
        }
    }

    $csrf_token = $_POST['csrf_token'] ?? '';
    $image = $_FILES['image'] ?? null;
    $upload_path = null;

    if (!is_string($csrf_token) || !hash_equals($_SESSION['add_temple_csrf'], $csrf_token)) {
        $error = 'คำขอไม่ถูกต้อง กรุณาโหลดหน้านี้ใหม่แล้วลองอีกครั้ง';
    } elseif ($form['name'] === '') {
        $error = 'กรุณากรอกชื่อวัด';
    } elseif (($form['latitude'] !== '' && !is_numeric($form['latitude'])) ||
              ($form['longitude'] !== '' && !is_numeric($form['longitude']))) {
        $error = 'ละติจูดและลองจิจูดต้องเป็นตัวเลข';
    } elseif (($form['latitude'] !== '' && ((float)$form['latitude'] < -90 || (float)$form['latitude'] > 90)) ||
              ($form['longitude'] !== '' && ((float)$form['longitude'] < -180 || (float)$form['longitude'] > 180))) {
        $error = 'พิกัดอยู่นอกช่วงที่ถูกต้อง';
    } elseif (!is_array($image) || !isset($image['error']) || !in_array($image['error'], [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
        $error = 'อัปโหลดรูปไม่สำเร็จ กรุณาเลือกไฟล์ใหม่';
    } elseif (is_array($image) && $image['error'] === UPLOAD_ERR_OK) {
        $max_image_size = 5 * 1024 * 1024;
        if ($image['size'] > $max_image_size || !is_uploaded_file($image['tmp_name'])) {
            $error = 'รูปภาพต้องมีขนาดไม่เกิน 5 MB';
        } else {
            $image_info = @getimagesize($image['tmp_name']);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = $finfo ? finfo_file($finfo, $image['tmp_name']) : false;
            if ($finfo) {
                finfo_close($finfo);
            }

            $allowed_types = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            if (!$image_info || !isset($allowed_types[$mime_type]) || $image_info['mime'] !== $mime_type) {
                $error = 'รองรับเฉพาะรูปภาพ JPG, PNG หรือ WEBP';
            } else {
                $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                    $error = 'ไม่สามารถเตรียมโฟลเดอร์อัปโหลดรูปภาพได้';
                } else {
                    $filename = 'temple_' . bin2hex(random_bytes(16)) . '.' . $allowed_types[$mime_type];
                    $upload_path = $upload_dir . DIRECTORY_SEPARATOR . $filename;
                    if (!move_uploaded_file($image['tmp_name'], $upload_path)) {
                        $error = 'บันทึกรูปภาพไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';
                        $upload_path = null;
                    }
                }
            }
        }
    }

    if ($error === '') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO temples (name, address, province, district, latitude, longitude, description, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $form['name'],
                $form['address'],
                $form['province'],
                $form['district'],
                $form['latitude'] === '' ? null : $form['latitude'],
                $form['longitude'] === '' ? null : $form['longitude'],
                $form['description'],
                isset($filename) ? 'uploads/' . $filename : null,
            ]);
            header('Location: manage_temples.php?success=created');
            exit();
        } catch (PDOException $e) {
            if ($upload_path !== null && is_file($upload_path)) {
                unlink($upload_path);
            }
            error_log('Unable to create temple: ' . $e->getMessage());
            $error = 'บันทึกข้อมูลวัดไม่สำเร็จ กรุณาตรวจสอบข้อมูลแล้วลองอีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มวัดใหม่ - ระบบบริหารจัดการวัด</title>
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
        * { box-sizing: border-box; }
        body { margin: 0; display: flex; min-height: 100vh; color: var(--text-main); background: var(--bg-color); font-family: 'Prompt', sans-serif; }
        .sidebar { flex: 0 0 260px; background: var(--sidebar-bg); color: #EAE5DE; box-shadow: 2px 0 10px rgba(0,0,0,.05); }
        .sidebar-header { padding: 25px 20px; background: #1F1A18; color: var(--primary-color); text-align: center; font-size: 15px; font-weight: 500; border-bottom: 1px solid rgba(255,255,255,.05); }
        .sidebar-menu { list-style: none; padding: 10px 0; margin: 0; }
        .sidebar-menu a { display: block; padding: 14px 25px; color: #BDB5AF; text-decoration: none; font-size: 14px; transition: .2s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { color: white; background: var(--sidebar-hover); border-left: 3px solid var(--primary-color); }
        .sidebar-menu i { width: 20px; margin-right: 12px; text-align: center; }
        .main-content { display: flex; flex: 1; min-width: 0; flex-direction: column; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 15px 30px; background: white; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        .user-tools { display: flex; align-items: center; gap: 14px; }
        .user-avatar { width: 38px; height: 38px; border: 2px solid var(--primary-color); border-radius: 50%; object-fit: cover; }
        .user-name { font-weight: 500; }
        .user-role { display: block; color: var(--text-muted); font-size: 11px; text-align: right; }
        .logout-btn { padding: 6px 12px; border: 1px solid #C0392B; border-radius: 5px; color: #C0392B; text-decoration: none; font-size: 12px; }
        .logout-btn:hover { color: white; background: #C0392B; }
        .content { width: 100%; max-width: 1050px; margin: 0 auto; padding: 32px; }
        .page-header { margin-bottom: 22px; }
        .page-header h1 { margin: 0; font-size: 25px; font-weight: 500; }
        .page-header p { margin: 5px 0 0; color: var(--text-muted); font-size: 14px; }
        .panel { padding: 26px; background: white; border: 1px solid var(--border-color); border-radius: 10px; box-shadow: 0 5px 18px rgba(62,53,47,.04); }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 18px; }
        .form-group { margin-bottom: 18px; }
        .form-group.full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 7px; font-size: 13px; font-weight: 500; }
        .form-control { width: 100%; padding: 11px 12px; border: 1px solid var(--border-color); border-radius: 6px; background: #FFFEFC; color: var(--text-main); font: inherit; font-size: 14px; }
        .form-control:focus { outline: 2px solid rgba(192,130,33,.2); border-color: var(--primary-color); }
        textarea.form-control { min-height: 110px; resize: vertical; }
        input[type=file].form-control { padding: 8px; }
        .hint { margin-top: 6px; color: var(--text-muted); font-size: 12px; }
        .image-preview { display: none; width: 100%; max-height: 260px; margin-top: 12px; border: 1px solid var(--border-color); border-radius: 8px; object-fit: cover; }
        .alert-error { margin-bottom: 18px; padding: 12px 14px; color: #78281F; background: #FADBD8; border-radius: 6px; font-size: 14px; }
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 8px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 17px; border: 0; border-radius: 6px; font: inherit; font-size: 14px; text-decoration: none; cursor: pointer; }
        .btn-primary { color: white; background: var(--primary-color); }
        .btn-primary:hover { background: #A66E1B; }
        .btn-secondary { color: var(--text-main); background: #EAE5DE; }
        .btn-secondary:hover { background: #D5CEC5; }
        @media (max-width: 700px) {
            .sidebar { flex-basis: 70px; }
            .sidebar-header { padding: 18px 3px; font-size: 0; }
            .sidebar-menu a { padding: 14px 0; text-align: center; font-size: 0; }
            .sidebar-menu i { margin: 0; font-size: 16px; }
            .content { padding: 20px 14px; }
            .topbar { padding: 12px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full { grid-column: auto; }
            .panel { padding: 18px; }
            .user-role { display: none; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">Super Admin Panel</div>
        <ul class="sidebar-menu">
            <li><a href="superadmin_dashboard.php"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
            <li><a href="manage_temples.php" class="active"><i class="fas fa-university"></i> จัดการวัดทั้งหมด</a></li>
            <li><a href="manage_users.php"><i class="fas fa-users-cog"></i> จัดการผู้ใช้ระบบ</a></li>
            <li><a href="manage_bookings.php"><i class="fas fa-calendar-check"></i> ตรวจสอบการจอง</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <span><i class="fas fa-plus-circle" style="color:var(--primary-color);"></i> เพิ่มข้อมูลวัด</span>
            <div class="user-tools">
                <img class="user-avatar" src="<?php echo !empty($profile_image) ? 'uploads/' . rawurlencode($profile_image) : 'assets/default-avatar.png'; ?>" alt="">
                <div><span class="user-name"><?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?></span><span class="user-role">Super Administrator</span></div>
                <a class="logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
            </div>
        </header>

        <div class="content">
            <div class="page-header">
                <h1>เพิ่มวัดใหม่</h1>
                <p>กรอกข้อมูลวัดและเพิ่มรูปภาพเพื่อแสดงบนเว็บไซต์</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert-error"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <section class="panel">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['add_temple_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="name">ชื่อวัด <span style="color:#C0392B;">*</span></label>
                            <input class="form-control" id="name" name="name" type="text" maxlength="255" value="<?php echo htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="form-group full">
                            <label for="address">ที่อยู่</label>
                            <textarea class="form-control" id="address" name="address"><?php echo htmlspecialchars($form['address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="province">จังหวัด</label>
                            <input class="form-control" id="province" name="province" type="text" maxlength="100" value="<?php echo htmlspecialchars($form['province'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="district">อำเภอ / เขต</label>
                            <input class="form-control" id="district" name="district" type="text" maxlength="100" value="<?php echo htmlspecialchars($form['district'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="latitude">ละติจูด</label>
                            <input class="form-control" id="latitude" name="latitude" type="number" step="any" min="-90" max="90" value="<?php echo htmlspecialchars($form['latitude'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="เช่น 13.746">
                        </div>
                        <div class="form-group">
                            <label for="longitude">ลองจิจูด</label>
                            <input class="form-control" id="longitude" name="longitude" type="number" step="any" min="-180" max="180" value="<?php echo htmlspecialchars($form['longitude'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="เช่น 100.535">
                        </div>
                        <div class="form-group full">
                            <label for="description">ข้อมูลและประวัติวัด</label>
                            <textarea class="form-control" id="description" name="description"><?php echo htmlspecialchars($form['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="form-group full">
                            <label for="image">รูปภาพวัด</label>
                            <input class="form-control" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                            <div class="hint">รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 5 MB</div>
                            <img class="image-preview" id="image-preview" alt="ตัวอย่างรูปภาพวัด">
                        </div>
                    </div>
                    <div class="form-actions">
                        <a class="btn btn-secondary" href="manage_temples.php">ยกเลิก</a>
                        <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> บันทึกข้อมูลวัด</button>
                    </div>
                </form>
            </section>
        </div>
    </main>
    <script>
        document.getElementById('image').addEventListener('change', function () {
            const preview = document.getElementById('image-preview');
            if (!this.files || !this.files[0]) {
                preview.removeAttribute('src');
                preview.style.display = 'none';
                return;
            }
            preview.src = URL.createObjectURL(this.files[0]);
            preview.style.display = 'block';
        });
    </script>
</body>
</html>
