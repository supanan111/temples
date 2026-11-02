<?php
session_start();
require_once 'db_config.php';
if (file_exists('notify.php')) require_once 'notify.php';

// 1. ตรวจสอบสิทธิ์: ต้องเป็น admin หรือ superadmin เท่านั้น
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$temple_id = $_SESSION['temple_id'] ?? null;
$user_id = $_SESSION['user_id'];
$temple_name_display = 'Super Admin Panel';

// สร้าง CSRF token สำหรับ delete
if (empty($_SESSION['delete_booking_csrf'])) {
    $_SESSION['delete_booking_csrf'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['delete_booking_csrf'];

// จัดการการอนุมัติ/ปฏิเสธสลิปแบบด่วน (GET)
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'verify_slip') {
    $v_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $v_status = ($_GET['status'] ?? '') === 'paid' ? 'paid' : 'invalid_slip';
    $v_note = trim($_GET['note'] ?? '');

    if ($v_id) {
        $new_booking_st = ($v_status === 'paid') ? 'approved' : 'pending';
        $note_val = $v_note ?: ($v_status === 'paid' ? 'สลิปถูกต้อง ชำระเงินแล้ว' : 'สลิปไม่ถูกต้อง กรุณาแนบใหม่');

        try {
            if ($role === 'superadmin') {
                $stmt_v = $pdo->prepare("UPDATE bookings SET payment_status = ?, status = ?, admin_note = ? WHERE id = ?");
                $stmt_v->execute([$v_status, $new_booking_st, $note_val, $v_id]);
            } else {
                $stmt_v = $pdo->prepare("UPDATE bookings SET payment_status = ?, status = ?, admin_note = ? WHERE id = ? AND temple_id = ?");
                $stmt_v->execute([$v_status, $new_booking_st, $note_val, $v_id, $temple_id]);
            }

            if (function_exists('create_notification')) {
                $stmt_u = $pdo->prepare("SELECT user_id FROM bookings WHERE id = ?");
                $stmt_u->execute([$v_id]);
                $u_id = (int)$stmt_u->fetchColumn();
                if ($u_id > 0) {
                    if ($v_status === 'paid') {
                        create_notification($pdo, $u_id, 'booking_approved', (int)$v_id, 'bookings', "การชำระเงินและคำขอจอง #{$v_id} ของท่านได้รับการอนุมัติแล้ว");
                    } else {
                        create_notification($pdo, $u_id, 'booking_slip_invalid', (int)$v_id, 'bookings', "สลิปการจอง #{$v_id} ไม่ผ่านการอนุมัติ: {$note_val} กรุณาแนบสลิปใหม่");
                    }
                }
            }
            header("Location: manage_bookings.php?success=1");
            exit();
        } catch (PDOException $e) {
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// จัดการการเปลี่ยนสถานะการจอง (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['status'])) {
    $booking_id = filter_var($_POST['booking_id'] ?? '', FILTER_VALIDATE_INT);
    $new_status  = $_POST['status'];
    $new_payment_status = $_POST['payment_status'] ?? null;
    $admin_note_input = trim((string)($_POST['admin_note'] ?? ''));
    $allowed_statuses = ['pending', 'approved', 'rejected'];
    $allowed_payment = ['unpaid', 'pending_review', 'paid', 'invalid_slip'];

    if (!$booking_id || !is_string($new_status) || !in_array($new_status, $allowed_statuses, true)) {
        $error = 'ข้อมูลการอัปเดตสถานะไม่ถูกต้อง';
    } elseif ($role === 'admin' && !filter_var($temple_id, FILTER_VALIDATE_INT)) {
        $error = 'ไม่พบข้อมูลวัดที่คุณมีสิทธิ์จัดการ';
    } else {
        try {
            $update_sql = "UPDATE bookings SET status = ?, admin_note = ?";
            $update_params = [$new_status, $admin_note_input ?: null];

            if ($new_payment_status && in_array($new_payment_status, $allowed_payment, true)) {
                $update_sql .= ", payment_status = ?";
                $update_params[] = $new_payment_status;
            }

            if ($role === 'superadmin') {
                $update_sql .= " WHERE id = ?";
                $update_params[] = $booking_id;
            } else {
                $update_sql .= " WHERE id = ? AND temple_id = ?";
                $update_params[] = $booking_id;
                $update_params[] = $temple_id;
            }

            $stmt_update = $pdo->prepare($update_sql);
            $stmt_update->execute($update_params);

            // แจ้งเตือน user เจ้าของการจอง
            if (function_exists('create_notification')) {
                $stmt_bk = $pdo->prepare("SELECT user_id FROM bookings WHERE id = ?");
                $stmt_bk->execute([$booking_id]);
                $bk_user_id = (int)$stmt_bk->fetchColumn();
                if ($bk_user_id > 0) {
                    if ($new_status === 'approved') {
                        $ntype = 'booking_approved';
                        $nmsg  = 'คำขอจองของคุณได้รับการอนุมัติแล้ว' . ($new_payment_status === 'paid' ? ' (ชำระเงินเรียบร้อย)' : '');
                    } elseif ($new_status === 'rejected') {
                        $ntype = 'booking_rejected';
                        $nmsg  = 'คำขอจองของคุณถูกปฏิเสธ' . ($admin_note_input ? ": $admin_note_input" : '');
                    } else {
                        $ntype = 'booking_updated';
                        $nmsg  = 'สถานะคำขอจองของคุณได้รับการอัปเดต';
                    }
                    create_notification($pdo, $bk_user_id, $ntype, (int)$booking_id, 'bookings', $nmsg);
                }
            }

            header("Location: manage_bookings.php?success=1");
            exit();
        } catch (PDOException $e) {
            error_log("Booking status update failed: " . $e->getMessage());
            $error = 'เกิดข้อผิดพลาดในการอัปเดตสถานะ: ' . $e->getMessage();
        }
    }
}

// ดึงข้อมูลโปรไฟล์ของ Admin ปัจจุบันมาแสดงบน Topbar
try {
    $stmt_user = $pdo->prepare("SELECT full_name, profile_image FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $current_admin = $stmt_user->fetch();

    $admin_name    = $current_admin['full_name'] ?? $_SESSION['full_name'];
    $profile_image = $current_admin['profile_image'] ?? '';

    // 2. ดึงข้อมูลการจองตามสิทธิ์
    if ($role === 'superadmin') {
        $sql  = "SELECT b.*, t.name AS temple_name, u.full_name AS user_full_name, i.item_name
                 FROM bookings b
                 LEFT JOIN temples t ON b.temple_id = t.id
                 LEFT JOIN bookable_items i ON b.item_id = i.id
                 LEFT JOIN users   u ON b.user_id   = u.id
                 ORDER BY b.id DESC";
        $stmt = $pdo->query($sql);
        $bookings = $stmt->fetchAll();
    } else {
        $stmt_temple = $pdo->prepare("SELECT name FROM temples WHERE id = ?");
        $stmt_temple->execute([$temple_id]);
        $temple_name_display = $stmt_temple->fetchColumn() ?: 'ไม่พบข้อมูลวัด';

        $sql  = "SELECT b.*, t.name AS temple_name, u.full_name AS user_full_name, i.item_name
                 FROM bookings b
                 LEFT JOIN temples t ON b.temple_id = t.id
                 LEFT JOIN bookable_items i ON b.item_id = i.id
                 LEFT JOIN users   u ON b.user_id   = u.id
                 WHERE b.temple_id = ?
                 ORDER BY b.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$temple_id]);
        $bookings = $stmt->fetchAll();
    }

    // นับ unread notifications
    $unread_count = function_exists('count_unread_notifications') ? count_unread_notifications($pdo, $user_id) : 0;
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการจองและตรวจสอบสลิป - ระบบบริหารจัดการวัด</title>
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

        body {
            font-family: 'Prompt', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
        }

        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: #EAE5DE; display: flex; flex-direction: column; flex-shrink: 0; box-shadow: 2px 0 10px rgba(0,0,0,0.05); }
        .sidebar-header { padding: 25px 20px; text-align: center; background-color: #1F1A18; font-size: 16px; font-weight: 500; color: var(--primary-color); border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sidebar-menu { list-style: none; padding: 10px 0; margin: 0; flex-grow: 1; }
        .sidebar-menu a { color: #BDB5AF; text-decoration: none; padding: 14px 25px; display: block; transition: 0.2s; font-size: 14px; position: relative; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--sidebar-hover); color: white; border-left: 3px solid var(--primary-color); }
        .sidebar-menu i { margin-right: 12px; width: 20px; text-align: center; opacity: 0.8; }
        .notif-badge { background: #e74c3c; color: #fff; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 10px; margin-left: 6px; vertical-align: middle; }

        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-x: hidden; }
        .topbar { background-color: var(--card-bg); padding: 15px 35px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        .user-profile-section { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-color); }
        .user-info-text { display: flex; flex-direction: column; text-align: left; }
        .user-name { font-weight: 500; color: var(--text-main); font-size: 14px; line-height: 1.2; }
        .user-role-label { font-size: 11px; color: var(--text-muted); }
        .profile-edit-btn { background: #FDFBF7; border: 1px solid var(--border-color); color: var(--text-main); padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; margin-left: 10px; }
        .profile-edit-btn:hover { background: #EAE5DE; }
        .logout-btn { color: #C0392B; text-decoration: none; margin-left: 10px; font-weight: 500; border: 1px solid #C0392B; padding: 5px 12px; border-radius: 4px; transition: 0.2s; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
        .logout-btn:hover { background: #C0392B; color: white; }
        .bell-btn { position: relative; background: none; border: none; cursor: pointer; font-size: 18px; color: var(--text-muted); padding: 5px 8px; }
        .bell-btn .bell-badge { position: absolute; top: 0; right: 0; background: #e74c3c; color: #fff; font-size: 10px; font-weight: 700; min-width: 16px; height: 16px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .notif-dropdown { position: absolute; top: 60px; right: 20px; width: 340px; background: #fff; border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); z-index: 999; display: none; }
        .notif-dropdown.show { display: block; }
        .notif-header { padding: 12px 16px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-weight: 500; font-size: 14px; }
        .notif-list { max-height: 300px; overflow-y: auto; }
        .notif-item { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; font-size: 13px; cursor: pointer; }
        .notif-item:hover { background: #fafafa; }
        .notif-item.unread { background: #FFF8EE; border-left: 3px solid var(--primary-color); }
        .notif-time { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
        .notif-footer { padding: 10px 16px; text-align: center; border-top: 1px solid var(--border-color); font-size: 12px; }
        .notif-footer a { color: var(--primary-color); text-decoration: none; }
        .mark-all-btn { font-size: 11px; color: var(--primary-color); background: none; border: none; cursor: pointer; }

        .content-container { padding: 35px; max-width: 1250px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { margin: 0; font-size: 22px; font-weight: 600; color: #1F1A18; }
        .page-header p { margin: 5px 0 0; color: var(--text-muted); font-size: 14px; }

        .panel { background-color: var(--card-bg); border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 4px 6px rgba(0,0,0,0.02); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
        th { background-color: #FAF8F5; padding: 12px 16px; border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-weight: 500; }
        td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #FAF8F5; }

        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-pending { background-color: #FEF9E7; color: #B7950B; }
        .badge-confirmed { background-color: #E8F8F5; color: #1E8449; }
        .badge-cancelled { background-color: #FDEDEC; color: #C0392B; }
        .badge-cancelled2 { background-color: #F2F3F4; color: #7F8C8D; }

        .status-form { display: flex; flex-direction: column; gap: 6px; align-items: flex-end; }
        .status-select { padding: 5px 8px; border-radius: 4px; border: 1px solid var(--border-color); font-family: 'Prompt', sans-serif; font-size: 12px; width: 135px; }
        .admin-note-input { padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border-color); font-family: 'Prompt', sans-serif; font-size: 11px; width: 135px; resize: vertical; min-height: 36px; }
        .btn-row { display: flex; gap: 5px; justify-content: flex-end; }
        .btn-update { background: var(--primary-color); color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 12px; cursor: pointer; }
        .btn-update:hover { background: #A66E1B; }
        .btn-delete { background: #FDFBF7; color: #C0392B; border: 1px solid #C0392B; padding: 5px 10px; border-radius: 4px; font-size: 12px; text-decoration: none; display: inline-block; }
        .btn-delete:hover { background: #C0392B; color: white; }
        .booking-detail { font-size: 12px; color: var(--text-muted); margin-top: 3px; }

        /* Slip Thumbnail & Modal */
        .slip-thumb { width: 44px; height: 44px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; cursor: pointer; transition: 0.2s; }
        .slip-thumb:hover { transform: scale(1.08); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        .slip-preview-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 20px; }
        .slip-preview-modal.active { display: flex; }
        .slip-modal-card { background: white; border-radius: 12px; max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 24px; position: relative; text-align: center; }
        .slip-modal-img { max-width: 100%; max-height: 55vh; object-fit: contain; border-radius: 8px; border: 1px solid #eee; margin: 15px auto; display: block; }
        .btn-approve-slip { background: #27AE60; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer; margin-right: 8px; text-decoration: none; display: inline-block; }
        .btn-reject-slip { background: #C0392B; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .close-modal-btn { position: absolute; top: 15px; right: 18px; font-size: 20px; color: #857B74; cursor: pointer; background: none; border: none; }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <?php echo htmlspecialchars($temple_name_display, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <ul class="sidebar-menu">
            <?php if ($role === 'superadmin'): ?>
                <li><a href="superadmin_dashboard.php"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
                <li><a href="manage_temples.php"><i class="fas fa-university"></i> จัดการวัดทั้งหมด</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users-cog"></i> จัดการผู้ใช้ระบบ</a></li>
                <li><a href="manage_bookings.php" class="active"><i class="fas fa-calendar-check"></i> ตรวจสอบการจอง
                    <?php if ($unread_count > 0): ?><span class="notif-badge" id="sidebar-badge"><?php echo $unread_count; ?></span><?php endif; ?>
                </a></li>
            <?php else: ?>
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
                <li><a href="manage_temples.php"><i class="fas fa-university"></i> จัดการข้อมูลวัด</a></li>
                <li><a href="edit_temple.php"><i class="fas fa-pen-to-square"></i> แก้ไขหน้าเว็บไซต์วัด</a></li>
                <li><a href="manage_bookings.php" class="active"><i class="fas fa-calendar-check"></i> ตรวจสอบการจอง
                    <?php if ($unread_count > 0): ?><span class="notif-badge" id="sidebar-badge"><?php echo $unread_count; ?></span><?php endif; ?>
                </a></li>
                <li><a href="manage_items.php"><i class="fas fa-box-open"></i> จัดการบริการ/สถานที่</a></li>
                <li><a href="manage_events.php"><i class="fas fa-calendar-alt"></i> จัดการปฏิทินกิจกรรม</a></li>
                <li><a href="manage_donations.php"><i class="fas fa-donate"></i> ข้อมูล E-Donation</a></li>
                <li><a href="add_news.php"><i class="fas fa-bullhorn"></i> จัดการข่าวสาร/ประชาสัมพันธ์</a></li>
                <li><a href="manage_temple.php"><i class="fas fa-vihara"></i> จัดการข้อมูลวัดและบัญชี</a></li>
            <?php endif; ?>
            <li><a href="edit_profile.php"><i class="fas fa-user-edit"></i> แก้ไขโปรไฟล์ส่วนตัว</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <div><i class="fas fa-bars" style="color: var(--text-muted);"></i></div>
            <div style="display: flex; align-items: center; gap: 15px; position: relative;">
                <!-- Notification Bell -->
                <button class="bell-btn" id="bellBtn" onclick="toggleNotifDropdown()">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                    <span class="bell-badge" id="bell-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </button>
                <!-- Notification Dropdown -->
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <span>การแจ้งเตือน</span>
                        <button class="mark-all-btn" onclick="markAllRead()">อ่านทั้งหมด</button>
                    </div>
                    <div class="notif-list" id="notifList">
                        <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 13px;">กำลังโหลด...</div>
                    </div>
                    <div class="notif-footer"><a href="#">ดูการแจ้งเตือนทั้งหมด</a></div>
                </div>

                <div class="user-profile-section">
                    <img src="<?php echo !empty($profile_image) ? 'uploads/' . $profile_image : 'assets/default-avatar.png'; ?>"
                         alt="Admin Profile" class="user-avatar">
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
                    <h2><?php echo ($role === 'superadmin') ? 'ตรวจสอบการจองและสลิปการชำระเงินทั้งหมด' : 'จัดการการจองและตรวจสอบสลิปของวัด'; ?></h2>
                    <p>ตรวจสอบสลิปโอนเงิน อนุมัติการชำระเงิน และอัปเดตสถานะการจองของผู้ใช้งาน</p>
                </div>
            </div>

            <?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
                <div role="status" style="margin-bottom: 16px; padding: 12px 16px; border-radius: 6px; background: #D4EFDF; color: #145A32;">
                    <i class="fas fa-check-circle mr-1"></i> อัปเดตสถานะและการตรวจสอบสลิปเรียบร้อยแล้ว
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div role="alert" style="margin-bottom: 16px; padding: 12px 16px; border-radius: 6px; background: #FADBD8; color: #78281F;">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="panel">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <?php if ($role === 'superadmin'): ?>
                                <th>วัดที่จอง</th>
                            <?php endif; ?>
                            <th>ผู้จอง</th>
                            <th>บริการ / วันที่</th>
                            <th>การชำระเงิน & สลิป</th>
                            <th>สถานะการจอง</th>
                            <th style="text-align: right; min-width: 180px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bookings) > 0): ?>
                            <?php foreach ($bookings as $b): ?>
                                <?php
                                    $status = trim((string)($b['status'] ?? '')) ?: 'pending';
                                    $badge_class = 'badge-pending';
                                    $status_text = 'รอดำเนินการ';
                                    if (in_array($status, ['confirmed', 'success', 'approved'], true)) {
                                        $badge_class = 'badge-confirmed';
                                        $status_text = 'อนุมัติแล้ว';
                                    } elseif ($status === 'rejected') {
                                        $badge_class = 'badge-cancelled';
                                        $status_text = 'ปฏิเสธ';
                                    } elseif ($status === 'cancelled') {
                                        $badge_class = 'badge-cancelled2';
                                        $status_text = 'ยกเลิก';
                                    }
                                    $is_cancelled = ($status === 'cancelled');

                                    // Payment Info
                                    $price = (float)($b['total_price'] ?? 0);
                                    $p_status = $b['payment_status'] ?? 'unpaid';
                                    $has_slip = !empty($b['slip_image']);
                                ?>
                                <tr>
                                    <td>#<?php echo $b['id']; ?></td>
                                    <?php if ($role === 'superadmin'): ?>
                                        <td><strong><?php echo htmlspecialchars($b['temple_name'] ?? 'ไม่ระบุวัด'); ?></strong></td>
                                    <?php endif; ?>
                                    <td>
                                        <strong><?php echo htmlspecialchars($b['contact_name'] ?? $b['user_full_name'] ?? 'ผู้ใช้งานทั่วไป'); ?></strong>
                                        <?php if (!empty($b['contact_phone'])): ?>
                                            <br><small><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($b['contact_phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($b['item_name'] ?? 'บริการ'); ?></strong>
                                        <div class="booking-detail">
                                            <?php echo !empty($b['stall_no']) ? 'ล็อค: ' . htmlspecialchars($b['stall_no']) : ('วันที่: ' . htmlspecialchars($b['booking_date'] ?? '')); ?>
                                            <?php if (!empty($b['qty_booked']) && empty($b['stall_no'])): ?>
                                                (<?php echo (int)$b['qty_booked']; ?> รายการ)
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($price <= 0): ?>
                                            <span class="badge" style="background:#EAECEE; color:#5D6D7E;">ฟรี (ไม่ต้องชำระ)</span>
                                        <?php else: ?>
                                            <strong style="color: #A06816;">฿<?php echo number_format($price, 2); ?></strong>
                                            <div style="margin-top: 4px;">
                                                <?php if ($p_status === 'paid'): ?>
                                                    <span class="badge badge-confirmed"><i class="fas fa-check-circle"></i> ชำระแล้ว</span>
                                                <?php elseif ($p_status === 'pending_review'): ?>
                                                    <span class="badge badge-pending"><i class="fas fa-clock"></i> แนบสลิปแล้ว (รอตรวจ)</span>
                                                <?php elseif ($p_status === 'invalid_slip'): ?>
                                                    <span class="badge badge-cancelled"><i class="fas fa-times-circle"></i> สลิปไม่ถูกต้อง</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background:#FADBD8; color:#78281F;"><i class="fas fa-exclamation-circle"></i> ยังไม่ชำระ</span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($has_slip): ?>
                                                <div style="margin-top: 6px; display: flex; align-items: center; gap: 8px;">
                                                    <img src="view_booking_slip.php?id=<?php echo $b['id']; ?>" 
                                                         alt="สลิป" 
                                                         class="slip-thumb" 
                                                         onclick="openSlipModal(<?php echo $b['id']; ?>, 'view_booking_slip.php?id=<?php echo $b['id']; ?>', '<?php echo htmlspecialchars($b['contact_name'] ?? '', ENT_QUOTES); ?>', '<?php echo number_format($price, 2); ?>')">
                                                    <button type="button" 
                                                            onclick="openSlipModal(<?php echo $b['id']; ?>, 'view_booking_slip.php?id=<?php echo $b['id']; ?>', '<?php echo htmlspecialchars($b['contact_name'] ?? '', ENT_QUOTES); ?>', '<?php echo number_format($price, 2); ?>')"
                                                            style="background:none; border:none; color:var(--primary-color); font-size:12px; cursor:pointer; text-decoration:underline; padding:0;">
                                                        <i class="fas fa-search-plus"></i> ตรวจสอบสลิป
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                                        <?php if (!empty($b['admin_note'])): ?>
                                            <div class="booking-detail" style="color:#8a4a00;"><i class="fas fa-comment-dots"></i> <?php echo htmlspecialchars($b['admin_note']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($b['cancelled_by'])): ?>
                                            <div class="booking-detail" style="color:#78281F;">ยกเลิกโดย: <?php echo $b['cancelled_by'] === 'user' ? 'ผู้จอง' : 'แอดมิน'; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if (!$is_cancelled): ?>
                                        <form action="manage_bookings.php" method="POST" class="status-form">
                                            <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                            
                                            <select name="status" class="status-select" title="สถานะการจอง">
                                                <option value="pending"   <?php echo ($status === 'pending')   ? 'selected' : ''; ?>>สถานะ: รอดำเนินการ</option>
                                                <option value="approved"  <?php echo in_array($status, ['confirmed','success','approved'], true) ? 'selected' : ''; ?>>สถานะ: อนุมัติ</option>
                                                <option value="rejected"  <?php echo ($status === 'rejected')  ? 'selected' : ''; ?>>สถานะ: ปฏิเสธ</option>
                                            </select>

                                            <?php if ($price > 0): ?>
                                            <select name="payment_status" class="status-select" title="สถานะการชำระเงิน">
                                                <option value="unpaid" <?php echo ($p_status === 'unpaid') ? 'selected' : ''; ?>>เงิน: ยังไม่ชำระ</option>
                                                <option value="pending_review" <?php echo ($p_status === 'pending_review') ? 'selected' : ''; ?>>เงิน: รอตรวจสลิป</option>
                                                <option value="paid" <?php echo ($p_status === 'paid') ? 'selected' : ''; ?>>เงิน: ชำระแล้ว</option>
                                                <option value="invalid_slip" <?php echo ($p_status === 'invalid_slip') ? 'selected' : ''; ?>>เงิน: สลิปไม่ถูกต้อง</option>
                                            </select>
                                            <?php endif; ?>

                                            <textarea name="admin_note" class="admin-note-input" placeholder="หมายเหตุถึงผู้จอง (ถ้ามี)"><?php echo htmlspecialchars($b['admin_note'] ?? ''); ?></textarea>
                                            
                                            <div class="btn-row">
                                                <button type="submit" class="btn-update"><i class="fas fa-check"></i> บันทึก</button>
                                                <?php if ($role === 'superadmin'): ?>
                                                    <a href="delete_booking.php?id=<?php echo $b['id']; ?>&csrf=<?php echo htmlspecialchars($csrf_token); ?>"
                                                       class="btn-delete"
                                                       onclick="return confirm('ต้องการลบรายการจองนี้ใช่หรือไม่?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: var(--text-muted);">ยกเลิกแล้ว</span>
                                            <?php if ($role === 'superadmin'): ?>
                                                <br>
                                                <a href="delete_booking.php?id=<?php echo $b['id']; ?>&csrf=<?php echo htmlspecialchars($csrf_token); ?>"
                                                   class="btn-delete"
                                                   style="margin-top: 5px;"
                                                   onclick="return confirm('ต้องการลบรายการจองนี้ใช่หรือไม่?');">
                                                    <i class="fas fa-trash"></i> ลบ
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo ($role === 'superadmin') ? '7' : '6'; ?>" style="text-align: center; color: var(--text-muted); padding: 40px;">
                                    <i class="fas fa-calendar-times" style="font-size: 32px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                    ยังไม่มีข้อมูลการจองในระบบ
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Slip Preview Modal -->
    <div class="slip-preview-modal" id="slipModal" onclick="closeSlipModal(event)">
        <div class="slip-modal-card" onclick="event.stopPropagation()">
            <button class="close-modal-btn" onclick="closeSlipModal()"><i class="fas fa-times"></i></button>
            <h3 style="margin: 0 0 4px; font-size: 18px; color: #2B2522;">
                <i class="fas fa-receipt" style="color: var(--primary-color);"></i> ตรวจสอบสลิปการโอนเงิน
            </h3>
            <p id="modalSlipDetails" style="margin: 0; font-size: 13px; color: var(--text-muted);"></p>
            
            <img id="modalSlipImg" src="" alt="สลิปหลักฐานการโอน" class="slip-modal-img">
            
            <div style="margin-top: 15px;">
                <a id="modalApproveBtn" href="#" class="btn-approve-slip">
                    <i class="fas fa-check-circle"></i> อนุมัติสลิป (ชำระเงินแล้ว)
                </a>
                <a id="modalRejectBtn" href="#" class="btn-reject-slip">
                    <i class="fas fa-times-circle"></i> สลิปไม่ถูกต้อง
                </a>
            </div>
        </div>
    </div>

<script>
// Slip modal inspection
function openSlipModal(id, imgSrc, contactName, price) {
    document.getElementById('modalSlipImg').src = imgSrc;
    document.getElementById('modalSlipDetails').textContent = 'คำขอจอง #' + id + ' โดยคุณ ' + contactName + ' (ยอดเงิน ฿' + price + ' บาท)';
    document.getElementById('modalApproveBtn').href = 'manage_bookings.php?action=verify_slip&id=' + id + '&status=paid';
    document.getElementById('modalRejectBtn').href = 'manage_bookings.php?action=verify_slip&id=' + id + '&status=invalid_slip';
    document.getElementById('slipModal').classList.add('active');
}
function closeSlipModal() {
    document.getElementById('slipModal').classList.remove('active');
}

// Notification dropdown
function toggleNotifDropdown() {
    const dd = document.getElementById('notifDropdown');
    dd.classList.toggle('show');
    if (dd.classList.contains('show')) {
        updateBadge(0);
        fetch('get_notifications.php?action=mark_all_read').catch(() => {});
        loadNotifications();
    }
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('#bellBtn') && !e.target.closest('#notifDropdown')) {
        document.getElementById('notifDropdown').classList.remove('show');
    }
});
function loadNotifications() {
    fetch('get_notifications.php?action=list')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('notifList');
            const items = data.notifications || data.items || [];
            const cnt = data.unread_count !== undefined ? data.unread_count : (data.count || 0);

            if (items.length === 0) {
                list.innerHTML = '<div style="padding:20px;text-align:center;color:#857B74;font-size:13px;">ไม่มีการแจ้งเตือน</div>';
                updateBadge(0);
                return;
            }
            list.innerHTML = items.map(n => `
                <div class="notif-item ${n.is_read ? '' : 'unread'}" onclick="markRead(${n.id})">
                    <div>${n.message}</div>
                    <div class="notif-time">${n.time_ago}</div>
                </div>`).join('');
            updateBadge(cnt);
        }).catch(() => {
            document.getElementById('notifList').innerHTML = '<div style="padding:20px;text-align:center;color:#857B74;font-size:13px;">ไม่สามารถโหลดได้</div>';
        });
}
function markRead(id) {
    updateBadge(0);
    fetch('get_notifications.php?action=mark_read&id=' + id).then(() => loadNotifications());
}
function markAllRead() {
    updateBadge(0);
    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
    fetch('get_notifications.php?action=mark_all_read').then(() => { loadNotifications(); });
}
function updateBadge(cnt) {
    let bell = document.getElementById('bell-badge');
    let sidebar = document.getElementById('sidebar-badge');
    if (cnt > 0) {
        if (!bell) {
            const btn = document.getElementById('bellBtn');
            if (btn) {
                bell = document.createElement('span');
                bell.className = 'bell-badge';
                bell.id = 'bell-badge';
                btn.appendChild(bell);
            }
        }
        if (!sidebar) {
            const link = document.querySelector('.sidebar-menu a.active') || document.querySelector('.sidebar-menu a[href*="manage_bookings"]');
            if (link) {
                sidebar = document.createElement('span');
                sidebar.className = 'notif-badge';
                sidebar.id = 'sidebar-badge';
                link.appendChild(sidebar);
            }
        }
        if (bell) {
            bell.textContent = cnt;
            bell.style.display = 'flex';
        }
        if (sidebar) {
            sidebar.textContent = cnt;
            sidebar.style.display = 'inline-block';
        }
    } else {
        if (bell) {
            bell.style.display = 'none';
            bell.textContent = '0';
        }
        if (sidebar) {
            sidebar.style.display = 'none';
            sidebar.textContent = '0';
        }
    }
}
// Poll every 60 seconds
setInterval(() => {
    fetch('get_notifications.php?action=list')
        .then(r => r.json())
        .then(data => updateBadge(data.unread_count || 0))
        .catch(() => {});
}, 60000);
</script>
</body>
</html>
