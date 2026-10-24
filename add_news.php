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

// 4. จัดการอัปโหลดรูปภาพและบันทึกข่าว
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_news'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $image_name = null;

    if (isset($_FILES['news_image']) && $_FILES['news_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['news_image']['type'], $allowed_types)) {
            $ext = pathinfo($_FILES['news_image']['name'], PATHINFO_EXTENSION);
            $image_name = uniqid('news_') . '.' . $ext;
            $upload_path = 'uploads/' . $image_name;
            move_uploaded_file($_FILES['news_image']['tmp_name'], $upload_path);
        } else {
            echo "<script>alert('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG, GIF เท่านั้นครับ');</script>";
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO news (temple_id, title, content, image) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin_temple_id, $title, $content, $image_name]);
        echo "<script>alert('✅ ประกาศข่าวสารเรียบร้อยแล้ว!'); window.location.href='add_news.php';</script>";
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage());
    }
}

// 5. จัดการลบข่าวสารและลบรูปภาพ
if (isset($_GET['delete'])) {
    $news_id = $_GET['delete'];
    try {
        $stmt_img = $pdo->prepare("SELECT image FROM news WHERE id = ? AND temple_id = ?");
        $stmt_img->execute([$news_id, $admin_temple_id]);
        $old_image = $stmt_img->fetchColumn();

        if ($old_image && file_exists('uploads/' . $old_image)) {
            unlink('uploads/' . $old_image);
        }

        $stmt = $pdo->prepare("DELETE FROM news WHERE id = ? AND temple_id = ?");
        $stmt->execute([$news_id, $admin_temple_id]);
        echo "<script>window.location.href='add_news.php';</script>";
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการลบ: " . $e->getMessage());
    }
}

// 6. ดึงข่าวสารเฉพาะของวัดที่เลือก
try {
    $stmt = $pdo->prepare("SELECT * FROM news WHERE temple_id = :temple_id ORDER BY created_at DESC");
    $stmt->execute([':temple_id' => $admin_temple_id]);
    $news_list = $stmt->fetchAll();
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข่าวสาร - <?php echo htmlspecialchars($temple_name); ?></title>
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

        .data-card { background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 25px; border: 1px solid var(--border-color); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 14px; margin-bottom: 8px; color: #34495e; font-weight: 500; }
        input[type="text"], textarea, input[type="file"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-family: 'Prompt', sans-serif; box-sizing: border-box; }
        textarea { resize: vertical; min-height: 100px; }
        
        input[type="file"] { background-color: #f8f9fa; cursor: pointer; border: 1px dashed #ccc;}
        input[type="file"]::file-selector-button { background-color: var(--primary-color); color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-family: 'Prompt', sans-serif; margin-right: 15px; transition: 0.2s;}
        input[type="file"]::file-selector-button:hover { opacity: 0.9; }

        .btn-submit { background-color: var(--primary-color); color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 15px; transition: 0.2s; width: 100%; font-weight: 600;}
        .btn-submit:hover { opacity: 0.9; }

        .btn-delete { background-color: #e74c3c; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-delete:hover { background-color: #c0392b;}

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: #f8f9fa; padding: 15px; border-bottom: 2px solid #ddd; color: #2c3e50; font-weight: 600; font-size: 15px; }
        td { padding: 15px; border-bottom: 1px solid #eee; color: #555; vertical-align: middle; font-size: 14px; }
        tr:hover { background-color: #fcfcfc; }
        
        .news-img-preview { width: 80px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
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
            <li><a href="manage_donations.php"><i class="fas fa-donate"></i> ข้อมูล E-Donation</a></li>
            <li><a href="add_news.php" class="active"><i class="fas fa-bullhorn"></i> จัดการข่าวสาร/ประชาสัมพันธ์</a></li>
            <li><a href="manage_temple.php"><i class="fas fa-vihara"></i> จัดการข้อมูลวัด</a></li>
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
            <div class="page-header">
                <h2><i class="fas fa-bullhorn"></i> จัดการประกาศข่าวสาร (<?php echo htmlspecialchars($temple_name); ?>)</h2>
            </div>

            <div class="data-card" style="border-top: 4px solid var(--primary-color);">
                <h3 style="margin-top: 0; color: var(--primary-color); font-size: 16px; margin-bottom: 15px;"><i class="fas fa-edit"></i> เขียนข่าวสารใหม่</h3>
                <form action="add_news.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="add_news" value="1">
                    <div class="form-group">
                        <label>หัวข้อข่าว <span style="color:red;">*</span></label>
                        <input type="text" name="title" required placeholder="เช่น ขอเชิญร่วมงานประจำปี...">
                    </div>
                    <div class="form-group">
                        <label>รายละเอียด</label>
                        <textarea name="content" required placeholder="กรอกเนื้อหาข่าวสารที่ต้องการแจ้งให้ทราบ..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>รูปภาพหน้าปก (ถ้ามี)</label>
                        <input type="file" name="news_image" accept="image/png, image/jpeg, image/gif">
                        <small style="color: #7f8c8d; display: block; margin-top: 5px;">* แนะนำรูปภาพแนวนอน ขนาดไม่เกิน 2MB</small>
                    </div>
                    <div class="form-group" style="margin-bottom: 0; margin-top: 20px;">
                        <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> เผยแพร่ข่าวสาร</button>
                    </div>
                </form>
            </div>

            <div class="data-card" style="padding: 0;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 100px;">รูปภาพ</th>
                            <th>หัวข้อข่าวสาร</th>
                            <th>วันที่ลงประกาศ</th>
                            <th style="width: 100px; text-align:center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($news_list) > 0): ?>
                            <?php foreach ($news_list as $row): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($row['image']) && file_exists('uploads/' . $row['image'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>" class="news-img-preview" alt="รูปปกข่าว">
                                        <?php else: ?>
                                            <div style="width: 80px; height: 60px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #bdc3c7; font-size: 20px;">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong style="color: #2c3e50; font-size: 15px;"><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                    <td style="color: #7f8c8d;"><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                    <td style="text-align:center;">
                                        <a href="add_news.php?delete=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('ยืนยันการลบข่าวสารนี้ใช่หรือไม่?');">
                                            <i class="fas fa-trash-alt"></i> ลบ
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align: center; padding: 40px; color: #7f8c8d;">ยังไม่มีประกาศข่าวสารในขณะนี้</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>