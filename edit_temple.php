<?php
session_start();
require_once 'db_config.php';
require_once 'temple_sections.php';

// 1. ตรวจสอบสิทธิ์: ต้องเป็น admin หรือ superadmin เท่านั้น
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$session_temple_id = $_SESSION['temple_id'] ?? null;
$user_id = $_SESSION['user_id'];

// 2. กำหนด ID ของวัดที่จะแก้ไขตามสิทธิ์
if ($role === 'superadmin') {
    // Super Admin รับ id จาก URL ได้ (เช่น edit_temple.php?id=2)
    $temple_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$temple_id) {
        header("Location: manage_temples.php");
        exit();
    }
} else {
    // Temple Admin บังคับให้แก้ไขได้เฉพาะวัดของตัวเองเท่านั้น (ตัดปัญหาเรื่อง ID ใน URL ไปเลย)
    $temple_id = (int)$session_temple_id;
    if (!$temple_id) {
        die("ข้อผิดพลาด: บัญชีของคุณยังไม่ได้ผูกกับวัดใดๆ");
    }
}

$error = '';
$sections = [];
$uploaded_image_path = null;
if (empty($_SESSION['temple_editor_csrf'])) {
    $_SESSION['temple_editor_csrf'] = bin2hex(random_bytes(32));
}

try {
    ensure_temple_sections_table($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name_input = $_POST['name'] ?? '';
        $address_input = $_POST['address'] ?? '';
        $description_input = $_POST['description'] ?? '';
        $latitude_input = $_POST['latitude'] ?? '';
        $longitude_input = $_POST['longitude'] ?? '';
        $promptpay_id_input = $_POST['promptpay_id'] ?? '';
        $submitted_token = $_POST['csrf_token'] ?? '';
        $submitted_sections = $_POST['sections'] ?? [];

        if (!is_string($name_input) || !is_string($address_input) || !is_string($description_input) ||
            !is_string($latitude_input) || !is_string($longitude_input) || !is_string($promptpay_id_input)) {
            $error = 'รูปแบบข้อมูลวัดไม่ถูกต้อง';
        } elseif (!is_string($submitted_token) || !hash_equals($_SESSION['temple_editor_csrf'], $submitted_token)) {
            $error = 'คำขอไม่ถูกต้อง กรุณาโหลดหน้านี้ใหม่แล้วลองอีกครั้ง';
        } elseif (trim($name_input) === '') {
            $error = 'กรุณากรอกชื่อวัด';
        } elseif (trim($promptpay_id_input) !== '' &&
            !preg_match('/\A(?:0\d{9}|\d{13})\z/', trim($promptpay_id_input))) {
            $error = 'PromptPay ต้องเป็นเบอร์โทรศัพท์ 10 หลักที่ขึ้นต้นด้วย 0 หรือเลขประจำตัวผู้เสียภาษี 13 หลัก';
        } elseif ((trim($latitude_input) === '') !== (trim($longitude_input) === '')) {
            $error = 'กรุณากรอกละติจูดและลองจิจูดให้ครบทั้งคู่';
        } elseif (trim($latitude_input) !== '' &&
            (!is_numeric($latitude_input) || (float)$latitude_input < -90 || (float)$latitude_input > 90)) {
            $error = 'ละติจูดต้องเป็นตัวเลขระหว่าง -90 ถึง 90';
        } elseif (trim($longitude_input) !== '' &&
            (!is_numeric($longitude_input) || (float)$longitude_input < -180 || (float)$longitude_input > 180)) {
            $error = 'ลองจิจูดต้องเป็นตัวเลขระหว่าง -180 ถึง 180';
        } elseif (!is_array($submitted_sections) || count($submitted_sections) > 30) {
            $error = 'จำนวนหัวข้อไม่ถูกต้อง (เพิ่มได้สูงสุด 30 หัวข้อ)';
        } else {
            $name = trim($name_input);
            $address = trim($address_input);
            $description = trim($description_input);
            $latitude = trim($latitude_input) === '' ? null : trim($latitude_input);
            $longitude = trim($longitude_input) === '' ? null : trim($longitude_input);
            $promptpay_id = trim($promptpay_id_input) === '' ? null : trim($promptpay_id_input);
            $clean_sections = [];
            foreach ($submitted_sections as $section) {
                if (!is_array($section)) {
                    $error = 'รูปแบบข้อมูลหัวข้อไม่ถูกต้อง';
                    break;
                }
                $title_input = $section['title'] ?? '';
                $content_input = $section['content'] ?? '';
                if (!is_string($title_input) || !is_string($content_input)) {
                    $error = 'รูปแบบข้อมูลหัวข้อไม่ถูกต้อง';
                    break;
                }
                $title = trim($title_input);
                $content = trim($content_input);
                if ($title === '' && $content === '') {
                    continue;
                }
                if ($title === '' || mb_strlen($title, 'UTF-8') > 150 || mb_strlen($content, 'UTF-8') > 10000) {
                    $error = 'กรุณาตั้งชื่อหัวข้อและตรวจสอบความยาวข้อมูล (ชื่อไม่เกิน 150 ตัวอักษร เนื้อหาไม่เกิน 10,000 ตัวอักษร)';
                    break;
                }
                $clean_sections[] = ['title' => $title, 'content' => $content];
            }

            if ($error === '') {
                $image_upload = $_FILES['image'] ?? null;
                $new_image_filename = null;
                if (!is_array($image_upload) || !isset($image_upload['error']) ||
                    !in_array($image_upload['error'], [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
                    $error = 'อัปโหลดรูปภาพไม่สำเร็จ กรุณาเลือกไฟล์ใหม่';
                } elseif ($image_upload['error'] === UPLOAD_ERR_OK) {
                    if (($image_upload['size'] ?? 0) > 5 * 1024 * 1024 ||
                        empty($image_upload['tmp_name']) ||
                        !is_uploaded_file($image_upload['tmp_name'])) {
                        $error = 'รูปภาพต้องมีขนาดไม่เกิน 5 MB';
                    } else {
                        $image_info = @getimagesize($image_upload['tmp_name']);
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = $finfo ? finfo_file($finfo, $image_upload['tmp_name']) : false;
                        if ($finfo) {
                            finfo_close($finfo);
                        }
                        $allowed_image_types = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp',
                        ];

                        if (!$image_info || !isset($allowed_image_types[$mime_type]) || $image_info['mime'] !== $mime_type) {
                            $error = 'รองรับเฉพาะรูปภาพ JPG, PNG หรือ WEBP';
                        } else {
                            $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
                            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                                $error = 'ไม่สามารถเตรียมโฟลเดอร์อัปโหลดรูปภาพได้';
                            } else {
                                $new_image_filename = 'temple_' . bin2hex(random_bytes(16)) . '.' . $allowed_image_types[$mime_type];
                                $uploaded_image_path = $upload_dir . DIRECTORY_SEPARATOR . $new_image_filename;
                                if (!move_uploaded_file($image_upload['tmp_name'], $uploaded_image_path)) {
                                    $error = 'บันทึกรูปภาพไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';
                                    $uploaded_image_path = null;
                                }
                            }
                        }
                    }
                }
            }

            if ($error === '') {
                $pdo->beginTransaction();
                $stmt_old_image = $pdo->prepare("SELECT image FROM temples WHERE id = ?");
                $stmt_old_image->execute([$temple_id]);
                $old_image = $stmt_old_image->fetchColumn();

                if ($new_image_filename !== null) {
                    $stmt_update = $pdo->prepare("UPDATE temples SET name = ?, address = ?, description = ?, latitude = ?, longitude = ?, promptpay_id = ?, image = ? WHERE id = ?");
                    $stmt_update->execute([$name, $address, $description, $latitude, $longitude, $promptpay_id, 'uploads/' . $new_image_filename, $temple_id]);
                } else {
                    $stmt_update = $pdo->prepare("UPDATE temples SET name = ?, address = ?, description = ?, latitude = ?, longitude = ?, promptpay_id = ? WHERE id = ?");
                    $stmt_update->execute([$name, $address, $description, $latitude, $longitude, $promptpay_id, $temple_id]);
                }

                $stmt_delete = $pdo->prepare("DELETE FROM temple_sections WHERE temple_id = ?");
                $stmt_delete->execute([$temple_id]);
                $stmt_insert = $pdo->prepare("INSERT INTO temple_sections (temple_id, title, content, sort_order) VALUES (?, ?, ?, ?)");
                foreach ($clean_sections as $order => $section) {
                    $stmt_insert->execute([$temple_id, $section['title'], $section['content'], $order]);
                }
                $pdo->commit();

                if ($new_image_filename !== null && is_string($old_image) &&
                    preg_match('/\Auploads\/temple_[A-Za-z0-9_-]+\.(?:jpg|png|webp)\z/', $old_image)) {
                    $old_image_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $old_image);
                    if (is_file($old_image_path)) {
                        unlink($old_image_path);
                    }
                }

                header('Location: edit_temple.php?id=' . rawurlencode((string)$temple_id) . '&saved=1');
                exit();
            }
        }
    }

    $stmt_user = $pdo->prepare("SELECT full_name, profile_image FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $current_admin = $stmt_user->fetch();

    $admin_name = $current_admin['full_name'] ?? $_SESSION['full_name'];
    $profile_image = $current_admin['profile_image'] ?? '';

    $stmt_temple = $pdo->prepare("SELECT * FROM temples WHERE id = ?");
    $stmt_temple->execute([$temple_id]);
    $temple = $stmt_temple->fetch();

    if (!$temple) {
        die("ไม่พบข้อมูลวัดที่คุณต้องการแก้ไข");
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') {
        $temple['name'] = isset($_POST['name']) && is_string($_POST['name']) ? $_POST['name'] : $temple['name'];
        $temple['address'] = isset($_POST['address']) && is_string($_POST['address']) ? $_POST['address'] : $temple['address'];
        $temple['description'] = isset($_POST['description']) && is_string($_POST['description']) ? $_POST['description'] : $temple['description'];
        $temple['latitude'] = isset($_POST['latitude']) && is_string($_POST['latitude']) ? $_POST['latitude'] : ($temple['latitude'] ?? '');
        $temple['longitude'] = isset($_POST['longitude']) && is_string($_POST['longitude']) ? $_POST['longitude'] : ($temple['longitude'] ?? '');
        $temple['promptpay_id'] = isset($_POST['promptpay_id']) && is_string($_POST['promptpay_id']) ? $_POST['promptpay_id'] : ($temple['promptpay_id'] ?? '');
        $posted_sections = $_POST['sections'] ?? [];
        foreach (is_array($posted_sections) ? $posted_sections : [] as $section) {
            if (is_array($section)) {
                $sections[] = [
                    'title' => isset($section['title']) && is_string($section['title']) ? $section['title'] : '',
                    'content' => isset($section['content']) && is_string($section['content']) ? $section['content'] : '',
                ];
            }
        }
    } else {
        $sections = get_temple_sections($pdo, $temple_id);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($uploaded_image_path !== null && is_file($uploaded_image_path)) {
        unlink($uploaded_image_path);
    }
    error_log("Unable to load temple editor: " . $e->getMessage());
    http_response_code(500);
    die("ไม่สามารถโหลดตัวแก้ไขข้อมูลวัดได้ กรุณาลองใหม่อีกครั้ง");
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลวัด - ระบบบริหารจัดการวัด</title>
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
        
        .content-container { padding: 28px; max-width: 1800px; margin: 0 auto; width: 100%; box-sizing: border-box;}
        .page-header { margin-bottom: 30px; }
        .page-header h2 { margin: 0; color: var(--text-main); font-weight: 500; font-size: 24px; }
        .page-header p { margin: 5px 0 0 0; color: var(--text-muted); font-size: 14px; }

        .panel { background: var(--card-bg); border-radius: 8px; border: 1px solid var(--border-color); padding: 30px; }
        .editor-layout { display: grid; grid-template-columns: minmax(380px, 0.9fr) minmax(420px, 1.1fr); gap: 22px; align-items: start; }
        .editor-column, .preview-column { min-width: 0; }
        .editor-panel, .preview-panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 22px; }
        .preview-panel { position: sticky; top: 18px; }
        .panel-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin: 0 0 18px; padding-bottom: 13px; border-bottom: 1px solid var(--border-color); }
        .panel-heading h3 { margin: 0; font-size: 17px; font-weight: 500; }
        .preview-frame { width: 100%; height: min(76vh, 900px); min-height: 560px; border: 1px solid var(--border-color); border-radius: 8px; background: #fff; }
        .preview-note { margin: 10px 0 0; color: var(--text-muted); font-size: 12px; }
        .section-nav { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 18px; }
        .section-nav a { color: var(--text-main); background: #F8F5F0; border: 1px solid var(--border-color); border-radius: 999px; padding: 6px 11px; font-size: 12px; text-decoration: none; }
        .section-nav a:hover { border-color: var(--primary-color); color: var(--primary-color); }
        .section-card { margin: 14px 0; padding: 16px; border: 1px solid var(--border-color); border-radius: 9px; background: #FFFEFC; }
        .section-card-header { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; }
        .section-card-header strong { font-size: 13px; color: var(--text-muted); }
        .section-actions { display: flex; gap: 6px; }
        .icon-button { border: 1px solid var(--border-color); border-radius: 6px; background: white; color: var(--text-main); padding: 6px 9px; cursor: pointer; }
        .icon-button:hover { border-color: var(--primary-color); color: var(--primary-color); }
        .icon-button.remove-section { color: #A93226; }
        .section-card textarea.form-control { min-height: 120px; }
        .temple-image-preview { display: block; width: 100%; max-height: 220px; margin-top: 10px; border: 1px solid var(--border-color); border-radius: 8px; object-fit: cover; }
        .btn-add-section { border: 1px dashed var(--primary-color); border-radius: 8px; padding: 10px 14px; background: #FFFCF5; color: #8B5A12; font: inherit; cursor: pointer; }
        .btn-add-section:hover { background: #FBF1DE; }
        .editor-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 20px; }
        .editor-actions .btn-cancel { margin-left: 0; }
        .alert-success { background: #EAF6EE; color: #246B3D; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: var(--text-main); }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 6px; font-family: 'Prompt', sans-serif; font-size: 14px; box-sizing: border-box; background-color: #FAFAFA; }
        .form-control:focus { outline: none; border-color: var(--primary-color); background-color: #FFFFFF; }
        textarea.form-control { resize: vertical; min-height: 100px; }

        .btn-submit { background-color: var(--primary-color); color: white; border: none; padding: 12px 24px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: 0.2s; }
        .btn-submit:hover { background-color: #A66E1B; }
        .btn-cancel { background-color: #EAE5DE; color: var(--text-main); text-decoration: none; padding: 12px 20px; border-radius: 6px; font-size: 14px; display: inline-block; margin-left: 10px; }
        .btn-cancel:hover { background-color: #D5CEC5; }

        .alert-error { background-color: #FADBD8; color: #78281F; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        @media (max-width: 1050px) {
            .editor-layout { grid-template-columns: 1fr; }
            .preview-panel { position: static; }
            .preview-frame { height: 70vh; }
        }
        @media (max-width: 700px) {
            .sidebar { width: 70px; flex: 0 0 70px; }
            .sidebar-header { font-size: 0; padding: 18px 4px; }
            .sidebar-menu a { padding: 14px 0; text-align: center; font-size: 0; }
            .sidebar-menu i { margin: 0; font-size: 16px; }
            .content-container { padding: 18px 12px; }
            .topbar { padding: 12px; }
            .user-role-label, .profile-edit-btn { display: none; }
            .editor-panel, .preview-panel { padding: 16px; }
            .preview-frame { min-height: 460px; }
        }
    </style>
</head>
<body>

    <!-- Sidebar แบบไดนามิกแยกตามสิทธิ์ -->
    <div class="sidebar">
        <div class="sidebar-header">
            <?php echo htmlspecialchars($temple['name'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <ul class="sidebar-menu">
            <?php if ($role === 'superadmin'): ?>
                <li><a href="superadmin_dashboard.php"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
                <li><a href="manage_temples.php"><i class="fas fa-university"></i> จัดการวัดทั้งหมด</a></li>
                <li><a href="edit_temple.php?id=<?php echo rawurlencode((string)$temple_id); ?>" class="active"><i class="fas fa-pen-to-square"></i> แก้ไขหน้าเว็บไซต์วัด</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users-cog"></i> จัดการผู้ใช้ระบบ</a></li>
                <li><a href="manage_bookings.php"><i class="fas fa-calendar-check"></i> ตรวจสอบการจอง</a></li>
            <?php else: ?>
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> แดชบอร์ดวัดของฉัน</a></li>
                <li><a href="edit_temple.php" class="active"><i class="fas fa-pen-to-square"></i> แก้ไขหน้าเว็บไซต์วัด</a></li>
                <li><a href="manage_temples.php"><i class="fas fa-university"></i> จัดการข้อมูลวัด</a></li>
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
                <h2>จัดการหน้าเว็บไซต์วัด</h2>
                <p>เพิ่มและจัดเรียงหัวข้อเนื้อหา พร้อมดูหน้าเว็บไซต์วัดจริงทางด้านขวา</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif (isset($_GET['saved'])): ?>
                <div class="alert-success"><i class="fas fa-circle-check"></i> บันทึกข้อมูลและหัวข้อเรียบร้อยแล้ว</div>
            <?php endif; ?>

            <div class="editor-layout">
                <div class="editor-column">
                    <form action="edit_temple.php<?php echo ($role === 'superadmin') ? '?id=' . rawurlencode((string)$temple_id) : ''; ?>" method="POST" enctype="multipart/form-data" id="temple-editor-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['temple_editor_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                        <section class="editor-panel">
                            <div class="panel-heading">
                                <h3><i class="fas fa-pen-to-square" style="color:var(--primary-color);"></i> ข้อมูลหลักของวัด</h3>
                            </div>
                            <div class="form-group">
                                <label for="temple-name">ชื่อวัด</label>
                                <input id="temple-name" type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($temple['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="temple-address">ที่ตั้ง / ที่อยู่วัด</label>
                                <textarea id="temple-address" name="address" class="form-control"><?php echo htmlspecialchars($temple['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="temple-promptpay-id">PromptPay สำหรับรับบริจาค</label>
                                <input id="temple-promptpay-id" type="text" name="promptpay_id" class="form-control" inputmode="numeric" maxlength="13" pattern="(?:0[0-9]{9}|[0-9]{13})?" value="<?php echo htmlspecialchars($temple['promptpay_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="เบอร์พร้อมเพย์ 10 หลัก หรือเลขประจำตัว 13 หลัก">
                                <small style="color:var(--text-muted);">เว้นว่างเพื่อปิดรับบริจาคออนไลน์ กรุณาตรวจสอบว่าเป็นบัญชีของวัดก่อนบันทึก</small>
                            </div>
                            <div class="form-group">
                                <label for="temple-latitude">ละติจูด</label>
                                <input id="temple-latitude" type="number" name="latitude" class="form-control" step="any" min="-90" max="90" value="<?php echo htmlspecialchars((string)($temple['latitude'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="เช่น 13.746">
                            </div>
                            <div class="form-group">
                                <label for="temple-longitude">ลองจิจูด</label>
                                <input id="temple-longitude" type="number" name="longitude" class="form-control" step="any" min="-180" max="180" value="<?php echo htmlspecialchars((string)($temple['longitude'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="เช่น 100.535">
                            </div>
                            <small style="display:block;margin:-8px 0 16px;color:var(--text-muted);">กรอกพิกัดทั้งสองช่อง หรือเว้นว่างทั้งคู่ หากใช้ Google Maps ให้คลิกขวาที่ตำแหน่งวัดเพื่อคัดลอกพิกัด</small>
                            <div class="form-group">
                                <label for="temple-image">รูปภาพวัดที่แสดงบนหน้าแรก</label>
                                <input id="temple-image" type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <small style="display:block;margin-top:6px;color:var(--text-muted);">รองรับ JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB รูปใหม่จะแสดงหลังบันทึก</small>
                                <?php $current_temple_image = !empty($temple['image']) ? $temple['image'] : 'assets/images/download.jpg'; ?>
                                <img id="temple-image-preview" class="temple-image-preview" src="<?php echo htmlspecialchars($current_temple_image, ENT_QUOTES, 'UTF-8'); ?>" alt="รูปภาพวัดปัจจุบัน">
                            </div>
                            <div class="form-group">
                                <label for="temple-description">ข้อมูลและประวัติที่น่าสนใจ</label>
                                <textarea id="temple-description" name="description" class="form-control" placeholder="เล่าประวัติหรือข้อมูลสำคัญของวัด"><?php echo htmlspecialchars($temple['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </section>

                        <section class="editor-panel" style="margin-top:18px;">
                            <div class="panel-heading">
                                <h3><i class="fas fa-layer-group" style="color:var(--primary-color);"></i> หัวข้อเพิ่มเติม</h3>
                                <button type="button" class="btn-add-section" id="add-section"><i class="fas fa-plus"></i> เพิ่มหัวข้อ</button>
                            </div>
                            <p style="font-size:13px;color:var(--text-muted);margin:0 0 14px;">หัวข้อจะแสดงใต้ข้อมูลประวัติวัด สามารถเพิ่ม ลบ และเรียงลำดับได้ (สูงสุด 30 หัวข้อ)</p>
                            <nav class="section-nav" id="section-nav" aria-label="เมนูหัวข้อเนื้อหา"></nav>
                            <div id="sections-list">
                                <?php foreach ($sections as $index => $section): ?>
                                    <article class="section-card" id="section-<?php echo (int)$index; ?>">
                                        <div class="section-card-header">
                                            <strong class="section-number">หัวข้อ <?php echo (int)$index + 1; ?></strong>
                                            <div class="section-actions">
                                                <button type="button" class="icon-button move-up" aria-label="เลื่อนหัวข้อขึ้น"><i class="fas fa-arrow-up"></i></button>
                                                <button type="button" class="icon-button move-down" aria-label="เลื่อนหัวข้อลง"><i class="fas fa-arrow-down"></i></button>
                                                <button type="button" class="icon-button remove-section" aria-label="ลบหัวข้อ"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>ชื่อหัวข้อ</label>
                                            <input type="text" class="form-control section-title-input" name="sections[<?php echo (int)$index; ?>][title]" maxlength="150" value="<?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label>เนื้อหา</label>
                                            <textarea class="form-control" name="sections[<?php echo (int)$index; ?>][content]" maxlength="10000" rows="5" required><?php echo htmlspecialchars($section['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <div class="editor-actions">
                                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึกและอัปเดตหน้าเว็บ</button>
                                <a href="manage_temples.php" class="btn-cancel">ยกเลิก</a>
                            </div>
                        </section>
                    </form>
                </div>

                <aside class="preview-column">
                    <section class="preview-panel">
                        <div class="panel-heading">
                            <h3><i class="fas fa-desktop" style="color:var(--primary-color);"></i> ตัวอย่างหน้าเว็บไซต์</h3>
                            <a href="temple_page.php?id=<?php echo rawurlencode((string)$temple_id); ?>" target="_blank" rel="noopener" style="color:var(--primary-color);font-size:13px;text-decoration:none;">
                                เปิดหน้าเต็ม <i class="fas fa-arrow-up-right-from-square"></i>
                            </a>
                        </div>
                        <iframe class="preview-frame" src="temple_page.php?id=<?php echo rawurlencode((string)$temple_id); ?>" title="ตัวอย่างหน้าเว็บไซต์วัด"></iframe>
                        <p class="preview-note"><i class="fas fa-circle-info"></i> กดบันทึกเพื่ออัปเดตตัวอย่างด้วยข้อมูลล่าสุด</p>
                    </section>
                </aside>
            </div>
            <template id="section-template">
                <article class="section-card">
                    <div class="section-card-header">
                        <strong class="section-number"></strong>
                        <div class="section-actions">
                            <button type="button" class="icon-button move-up" aria-label="เลื่อนหัวข้อขึ้น"><i class="fas fa-arrow-up"></i></button>
                            <button type="button" class="icon-button move-down" aria-label="เลื่อนหัวข้อลง"><i class="fas fa-arrow-down"></i></button>
                            <button type="button" class="icon-button remove-section" aria-label="ลบหัวข้อ"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>ชื่อหัวข้อ</label>
                        <input type="text" class="form-control section-title-input" maxlength="150" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>เนื้อหา</label>
                        <textarea class="form-control" maxlength="10000" rows="5" required></textarea>
                    </div>
                </article>
            </template>
            <script>
                (function () {
                    const imageInput = document.getElementById('temple-image');
                    const imagePreview = document.getElementById('temple-image-preview');
                    let previewObjectUrl = null;
                    imageInput.addEventListener('change', function () {
                        if (previewObjectUrl) URL.revokeObjectURL(previewObjectUrl);
                        if (!this.files || !this.files[0]) return;
                        previewObjectUrl = URL.createObjectURL(this.files[0]);
                        imagePreview.src = previewObjectUrl;
                    });

                    const list = document.getElementById('sections-list');
                    const nav = document.getElementById('section-nav');
                    const addButton = document.getElementById('add-section');
                    const template = document.getElementById('section-template');

                    function updateSections() {
                        const cards = Array.from(list.querySelectorAll('.section-card'));
                        nav.replaceChildren();
                        cards.forEach((card, index) => {
                            card.id = 'section-' + index;
                            card.querySelector('.section-number').textContent = 'หัวข้อ ' + (index + 1);
                            const title = card.querySelector('.section-title-input').value.trim() || 'หัวข้อ ' + (index + 1);
                            const link = document.createElement('a');
                            link.href = '#' + card.id;
                            link.textContent = title;
                            nav.appendChild(link);

                            const titleInput = card.querySelector('.section-title-input');
                            const contentInput = card.querySelector('textarea');
                            titleInput.name = 'sections[' + index + '][title]';
                            contentInput.name = 'sections[' + index + '][content]';
                            card.querySelector('.move-up').disabled = index === 0;
                            card.querySelector('.move-down').disabled = index === cards.length - 1;
                        });
                        addButton.disabled = cards.length >= 30;
                    }

                    list.addEventListener('input', function (event) {
                        if (event.target.classList.contains('section-title-input')) updateSections();
                    });
                    list.addEventListener('click', function (event) {
                        const button = event.target.closest('button');
                        if (!button) return;
                        const card = button.closest('.section-card');
                        if (button.classList.contains('remove-section')) {
                            card.remove();
                        } else if (button.classList.contains('move-up') && card.previousElementSibling) {
                            list.insertBefore(card, card.previousElementSibling);
                        } else if (button.classList.contains('move-down') && card.nextElementSibling) {
                            list.insertBefore(card.nextElementSibling, card);
                        }
                        updateSections();
                    });
                    addButton.addEventListener('click', function () {
                        if (list.querySelectorAll('.section-card').length >= 30) return;
                        const card = template.content.firstElementChild.cloneNode(true);
                        list.appendChild(card);
                        updateSections();
                        card.querySelector('.section-title-input').focus();
                    });
                    updateSections();
                })();
            </script>
        </div>
    </div>
</body>
</html>