<?php
session_start();
require_once 'db_config.php';

function slip_upload_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo '<!DOCTYPE html><html lang="th"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>ส่งหลักฐานไม่สำเร็จ</title><body style="font-family:sans-serif;max-width:540px;margin:60px auto;padding:20px;text-align:center">';
    echo '<h1>ส่งหลักฐานไม่สำเร็จ</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="javascript:history.back()">กลับไปหน้ารายการ</a></body></html>';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$donation_id = filter_input(INPUT_POST, 'donation_id', FILTER_VALIDATE_INT);
$upload_token = $_POST['upload_token'] ?? '';
$session_tokens = $_SESSION['donation_slip_tokens'] ?? [];
if (!$donation_id || $donation_id < 1 || !is_string($upload_token) ||
    !is_array($session_tokens) || !isset($session_tokens[$donation_id]) ||
    !hash_equals($session_tokens[$donation_id], $upload_token)) {
    slip_upload_error('ลิงก์อัปโหลดหมดอายุหรือไม่ถูกต้อง กรุณาสร้างรายการบริจาคใหม่');
}

$file = $_FILES['slip_image'] ?? null;
if (!is_array($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    slip_upload_error('กรุณาเลือกไฟล์สลิปที่ต้องการส่ง');
}
if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024 ||
    empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    slip_upload_error('ไฟล์ต้องมีขนาดไม่เกิน 5 MB');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
if ($finfo) {
    finfo_close($finfo);
}
$allowed_types = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$image_info = @getimagesize($file['tmp_name']);
if (!$image_info || !isset($allowed_types[$mime_type]) || $image_info['mime'] !== $mime_type) {
    slip_upload_error('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG หรือ WEBP เท่านั้น');
}

$don_info = null;

try {
    $stmt = $pdo->prepare("SELECT id, status, slip_image FROM donations WHERE id = ? AND status = 'pending'");
    $stmt->execute([$donation_id]);
    $donation = $stmt->fetch();
    if (!$donation) {
        slip_upload_error('ไม่พบรายการบริจาคที่รอส่งหลักฐาน หรือรายการนี้ดำเนินการแล้ว', 404);
    }

    $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'donation_uploads';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        throw new RuntimeException('Unable to create donation evidence directory');
    }
    $filename = 'donation_' . $donation_id . '_' . bin2hex(random_bytes(16)) . '.' . $allowed_types[$mime_type];
    $target_path = $upload_dir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new RuntimeException('Unable to store donation evidence');
    }

    $stmt_update = $pdo->prepare('UPDATE donations SET slip_image = ? WHERE id = ? AND status = \'pending\'');
    $stmt_update->execute([$filename, $donation_id]);
    if ($stmt_update->rowCount() !== 1) {
        unlink($target_path);
        slip_upload_error('รายการบริจาคเปลี่ยนสถานะแล้ว กรุณาติดต่อวัด', 409);
    }
    if (!empty($donation['slip_image'])) {
        $old_path = $upload_dir . DIRECTORY_SEPARATOR . basename($donation['slip_image']);
        if (is_file($old_path)) {
            unlink($old_path);
        }
    }
    unset($_SESSION['donation_slip_tokens'][$donation_id]);

    // ดึงข้อมูลบริจาคพร้อมชื่อวัดสำหรับแสดงผลหน้าขอบคุณและแจ้งเตือน
    $stmt_don = $pdo->prepare("
        SELECT d.*, t.name AS temple_name, t.id AS temple_id 
        FROM donations d 
        LEFT JOIN temples t ON t.id = d.temple_id 
        WHERE d.id = ?
    ");
    $stmt_don->execute([$donation_id]);
    $don_info = $stmt_don->fetch(PDO::FETCH_ASSOC);

    // แจ้งเตือนแอดมินวัดและ superadmin
    if (file_exists('notify.php') && $don_info) {
        require_once 'notify.php';
        try {
            $amt_fmt = number_format((float)$don_info['amount'], 2);
            $msg_don = "มีเงินบริจาคใหม่รอตรวจสอบ: {$amt_fmt} บาท โดยคุณ {$don_info['donor_name']}";
            notify_temple_admins($pdo, (int)$don_info['temple_id'], 'new_donation', (int)$donation_id, 'donations', $msg_don);
            notify_superadmins($pdo, 'new_donation', (int)$donation_id, 'donations', $msg_don);
        } catch (Exception $e_notif) {}
    }
} catch (PDOException $e) {
    if (isset($target_path) && is_file($target_path)) {
        unlink($target_path);
    }
    error_log('Unable to save donation slip: ' . $e->getMessage());
    slip_upload_error('บันทึกหลักฐานไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 500);
} catch (RuntimeException $e) {
    error_log('Unable to store donation slip: ' . $e->getMessage());
    slip_upload_error('จัดเก็บไฟล์หลักฐานไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 500);
}

$temple_title = $don_info['temple_name'] ?? 'วัด';
$amount_display = isset($don_info['amount']) ? number_format((float)$don_info['amount'], 2) : '0.00';
$donor_display = !empty($don_info['donor_name']) ? $don_info['donor_name'] : 'ผู้ร่วมทำบุญ';
$temple_id_num = (int)($don_info['temple_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งหลักฐานเรียบร้อยแล้ว - <?php echo htmlspecialchars($temple_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            padding: 40px 16px 60px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            background: #FDFBF7;
            color: #3E352F;
            font-family: 'Sarabun', sans-serif;
        }
        .confirmation { width: 100%; max-width: 760px; }
        h1 { margin: 0 0 28px; color: #A06816; text-align: center; font-size: clamp(24px, 4vw, 32px); font-weight: 600; }
        
        .booking-card {
            display: flex;
            align-items: center;
            gap: 28px;
            padding: 32px;
            border: 1px solid #EAE5DE;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(62, 53, 47, .08);
            margin-bottom: 24px;
        }
        .calendar-icon { flex: 0 0 110px; height: 100px; display: grid; place-items: center; }
        .calendar-icon svg { display: block; width: 96px; height: 96px; }
        .details { min-width: 0; flex: 1; }
        .details h2 { margin: 0 0 6px; color: #A06816; font-size: 22px; font-weight: 600; }
        .details p { margin: 4px 0; color: #3E352F; font-size: 16px; overflow-wrap: anywhere; }
        
        .status { display: inline-flex; align-items: center; gap: 7px; margin-top: 10px; padding: 5px 12px; border-radius: 999px; background: #FBF3E5; color: #A06816; font-size: 13px; font-weight: 600; }
        .price-badge { display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; margin-left: 8px; padding: 5px 12px; border-radius: 999px; background: #E8F8F5; color: #16A085; font-size: 14px; font-weight: 700; }

        .info-card {
            background: #FFFFFF;
            border: 1px solid #EAE5DE;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(62, 53, 47, .08);
            margin-bottom: 24px;
            text-align: center;
        }
        .info-card p { margin: 0; font-size: 15px; color: #5D534A; line-height: 1.6; }

        .links { display: flex; justify-content: center; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
        .action-button {
            display: inline-flex;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border: 1px solid #C08221;
            border-radius: 8px;
            background: #fff;
            color: #A06816;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s, border-color .2s, color .2s, transform .2s;
        }
        .action-button:hover { transform: translateY(-1px); background: #FBF3E5; }
        .action-button-primary { background: #C08221; color: #fff; }
        .action-button-primary:hover { border-color: #A06816; background: #A06816; color: #fff; }

        @media (max-width: 640px) {
            .booking-card { flex-direction: column; text-align: center; gap: 16px; padding: 22px 18px; }
            .calendar-icon { height: 75px; }
            .calendar-icon svg { width: 75px; height: 75px; }
            .links { flex-direction: column; }
            .action-button { width: 100%; }
        }
    </style>
</head>
<body>
    <main class="confirmation">
        <h1>ส่งหลักฐานเรียบร้อยแล้ว</h1>

        <section class="booking-card" aria-label="รายละเอียดการส่งหลักฐานบริจาค">
            <div class="calendar-icon" aria-hidden="true">
                <svg viewBox="0 0 128 128" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="22" y="30" width="84" height="74" rx="7" fill="#F7F5FF" stroke="#171717" stroke-width="2.5"/>
                    <path d="M22 47H106" stroke="#171717" stroke-width="2.5"/>
                    <path d="M43 24V37M85 24V37" stroke="#171717" stroke-width="3.5" stroke-linecap="round"/>
                    <path d="M45 72L57 83L82 59" stroke="#171717" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="16" cy="54" r="2" fill="#FF6F61"/>
                    <circle cx="111" cy="67" r="2.5" fill="#FF8A65"/>
                    <circle cx="32" cy="19" r="2" fill="#F5C542"/>
                    <circle cx="97" cy="21" r="2" fill="#55C8E8"/>
                    <circle cx="103" cy="112" r="2" fill="#55C8E8"/>
                    <circle cx="37" cy="112" r="1.5" fill="#FF8A65"/>
                </svg>
            </div>
            <div class="details">
                <h2><?php echo htmlspecialchars($temple_title, ENT_QUOTES, 'UTF-8'); ?></h2>
                <p>ทำบุญสมทบทุน / ร่วมบริจาคออนไลน์ (E-Donation)</p>
                <p style="color: #6B5E55;">
                    <i class="fa-solid fa-user mr-1 text-amber-700"></i> ผู้ร่วมทำบุญ: <strong><?php echo htmlspecialchars($donor_display, ENT_QUOTES, 'UTF-8'); ?></strong>
                </p>
                <div>
                    <span class="status"><i class="fa-solid fa-clock"></i> รอเจ้าหน้าที่วัดตรวจสอบสลิป</span>
                    <span class="price-badge"><i class="fa-solid fa-hand-holding-heart"></i> ยอดทำบุญ: ฿<?php echo $amount_display; ?> บาท</span>
                </div>
            </div>
        </section>

        <section class="info-card">
            <div style="font-size: 28px; color: #27AE60; margin-bottom: 10px;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <p>
                ระบบได้รับสลิปหลักฐานการโอนเงินของท่านเรียบร้อยแล้ว<br>
                ทางวัดจะทำการตรวจสอบความถูกต้องและยืนยันยอดเงินเข้าบัญชี<br>
                <strong>ขออนุโมทนาบุญกับท่านและครอบครัวมา ณ โอกาสนี้</strong>
            </p>
        </section>

        <div class="links">
            <?php if ($temple_id_num > 0): ?>
                <a class="action-button" href="temple_page.php?id=<?php echo $temple_id_num; ?>">
                    <i class="fa-solid fa-place-of-worship"></i> กลับไปหน้าวัด
                </a>
            <?php endif; ?>
            <a class="action-button action-button-primary" href="index.php">
                <i class="fa-solid fa-house"></i> กลับสู่หน้าหลัก
            </a>
            <a class="action-button" href="donate.php<?php echo ($temple_id_num > 0) ? '?temple_id=' . $temple_id_num : ''; ?>">
                <i class="fa-solid fa-heart"></i> ร่วมทำบุญรายการอื่น
            </a>
        </div>
    </main>
</body>
</html>
