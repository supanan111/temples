<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าจากฟอร์ม
    $temple_id = filter_input(INPUT_POST, 'temple_id', FILTER_VALIDATE_INT);
    $item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $qty = filter_input(INPUT_POST, 'qty', FILTER_VALIDATE_INT);
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    
    // รับค่าตัวแปรสำคัญที่ใช้แยกประเภทการจอง
    $selection_type = $_POST['selection_type'] ?? '';
    $selected_data_str = $_POST['selected_data'] ?? '';

    if (!$temple_id || !$item_id || !$qty || $contact_name === '' || !preg_match('/^[0-9]{9,10}$/', $contact_phone) ||
        !in_array($selection_type, ['stalls', 'dates'], true) || $selected_data_str === '') {
        http_response_code(400);
        die("ข้อมูลการจองไม่ถูกต้อง กรุณากลับไปตรวจสอบข้อมูลอีกครั้ง");
    }
    
    // แตกข้อมูลกลับเป็น Array
    $data_array = explode(',', $selected_data_str);
    if (!$data_array || count(array_filter($data_array, 'strlen')) !== count($data_array)) {
        http_response_code(400);
        die("ข้อมูลวันที่หรือรายการที่เลือกไม่ถูกต้อง");
    }
    $data_array = array_map('trim', $data_array);
    $data_array = array_values(array_filter($data_array, static function ($value) {
        return $value !== '';
    }));
    if (!$data_array) {
        http_response_code(400);
        die("กรุณาเลือกวันที่หรือรายการที่ต้องการจอง");
    }
    
    // ตั้งค่าสถานะเริ่มต้นเป็น "รอดำเนินการ"
    $status = 'pending';

    try {
        $stmt_details = $pdo->prepare("
            SELECT i.item_name, t.name AS temple_name
            FROM bookable_items i
            INNER JOIN temples t ON t.id = i.temple_id
            WHERE i.id = :item_id AND i.temple_id = :temple_id
        ");
        $stmt_details->execute([
            ':item_id' => $item_id,
            ':temple_id' => $temple_id,
        ]);
        $booking_details = $stmt_details->fetch();
        if (!$booking_details) {
            http_response_code(400);
            die("ไม่พบข้อมูลบริการหรือวัดที่เลือก กรุณากลับไปทำรายการใหม่");
        }

        // เริ่มต้น Transaction เพื่อความปลอดภัยของข้อมูล
        $pdo->beginTransaction();

        foreach ($data_array as $val) {
            if ($selection_type == 'stalls') {
                // ---------------------------------------------------------
                // กรณีจอง "ล็อคงานวัด" 
                // นำเบอร์ล็อคไปใส่ในช่อง stall_no
                // ส่วน booking_date ใส่วันที่ปัจจุบัน (หรือค่าว่าง) ไปก่อนเพื่อไม่ให้ error
                // ---------------------------------------------------------
                $stmt = $pdo->prepare("
                    INSERT INTO bookings (temple_id, item_id, user_id, booking_date, stall_no, qty_booked, status, contact_name, contact_phone) 
                    VALUES (:temple_id, :item_id, :user_id, CURDATE(), :stall_no, :qty_booked, :status, :contact_name, :contact_phone)
                ");
                $stmt->execute([
                    ':temple_id' => $temple_id,
                    ':item_id' => $item_id,
                    ':user_id' => $_SESSION['user_id'],
                    ':stall_no' => $val, // ใส่เบอร์ล็อค
                    ':qty_booked' => 1,  // 1 ล็อค ต่อ 1 แถวข้อมูล
                    ':status' => $status,
                    ':contact_name' => $contact_name,
                    ':contact_phone' => $contact_phone
                ]);

            } else {
                // ---------------------------------------------------------
                // กรณีจอง "ศาลา / นิมนต์พระ / ยืมของ" 
                // นำวันที่ไปใส่ในช่อง booking_date
                // ---------------------------------------------------------
                $stmt = $pdo->prepare("
                    INSERT INTO bookings (temple_id, item_id, user_id, booking_date, qty_booked, status, contact_name, contact_phone) 
                    VALUES (:temple_id, :item_id, :user_id, :booking_date, :qty_booked, :status, :contact_name, :contact_phone)
                ");
                $stmt->execute([
                    ':temple_id' => $temple_id,
                    ':item_id' => $item_id,
                    ':user_id' => $_SESSION['user_id'],
                    ':booking_date' => $val, // ใส่วันที่ (YYYY-MM-DD)
                    ':qty_booked' => $qty,   // ใส่จำนวนที่ยืม
                    ':status' => $status,
                    ':contact_name' => $contact_name,
                    ':contact_phone' => $contact_phone
                ]);
            }
        }

        // ถ้าทั้งหมดสำเร็จ ให้ Commit บันทึกฐานข้อมูลถาวร
        $pdo->commit();

    } catch (PDOException $e) {
        // ถ้ามีอะไรผิดพลาด ให้ยกเลิกการบันทึก (Rollback) ทันที
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Booking submission failed: " . $e->getMessage());
        http_response_code(500);
        die("เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง");
    }
} else {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งคำขอจองสำเร็จ - <?php echo htmlspecialchars($booking_details['temple_name'], ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            padding: 56px 20px 72px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            background: #FDFBF7;
            color: #3E352F;
            font-family: 'Sarabun', sans-serif;
        }
        .confirmation { width: 100%; max-width: 760px; }
        h1 { margin: 0 0 42px; color: #A06816; text-align: center; font-size: clamp(25px, 5vw, 36px); font-weight: 600; }
        .booking-card {
            display: flex;
            align-items: center;
            gap: 34px;
            min-height: 270px;
            padding: 38px;
            border: 1px solid #EAE5DE;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(62, 53, 47, .10);
        }
        .calendar-icon { flex: 0 0 150px; height: 135px; display: grid; place-items: center; }
        .calendar-icon svg { display: block; width: 128px; height: 128px; }
        .details { min-width: 0; }
        .details h2 { margin: 0 0 4px; color: #A06816; font-size: 23px; font-weight: 600; }
        .details p { margin: 3px 0; color: #3E352F; font-size: 18px; overflow-wrap: anywhere; }
        .status { display: inline-flex; align-items: center; gap: 7px; margin-top: 13px; padding: 6px 12px; border-radius: 999px; background: #FBF3E5; color: #A06816; font-size: 14px; font-weight: 600; }
        .booking-items { margin-top: 8px !important; font-size: 15px !important; }
        .links { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin-top: 28px; }
        .action-button {
            display: inline-flex;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 17px;
            border: 1px solid #C08221;
            border-radius: 7px;
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
        .action-button:focus-visible { outline: 3px solid rgba(192, 130, 33, .3); outline-offset: 2px; }
        @media (max-width: 600px) {
            body { padding: 32px 16px; }
            h1 { margin-bottom: 26px; }
            .booking-card { min-height: 0; padding: 25px 20px; flex-direction: column; gap: 12px; text-align: center; }
            .calendar-icon { flex-basis: auto; height: 90px; }
            .calendar-icon svg { width: 90px; height: 90px; }
            .details h2 { font-size: 20px; }
            .details p { font-size: 16px; }
            .links { flex-direction: column; }
            .action-button { width: 100%; }
        }
    </style>
</head>
<body>
    <main class="confirmation">
        <h1>ส่งคำขอจองเรียบร้อยแล้ว</h1>
        <section class="booking-card" aria-label="รายละเอียดคำขอจอง">
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
                <h2><?php echo htmlspecialchars($booking_details['temple_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><?php echo htmlspecialchars($booking_details['item_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="booking-items">
                    <?php echo $selection_type === 'stalls' ? 'หมายเลขล็อค: ' : 'วันที่เลือก: '; ?>
                    <?php echo htmlspecialchars(implode(', ', $data_array), ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <span class="status"><i class="fa-solid fa-clock"></i> รอเจ้าหน้าที่ตรวจสอบ</span>
            </div>
        </section>
        <div class="links">
            <?php if (($_SESSION['role'] ?? '') === 'user'): ?>
                <a class="action-button" href="my_bookings.php"><i class="fa-solid fa-list-check"></i> ดูรายการจองของฉัน</a>
            <?php endif; ?>
            <a class="action-button" href="temple_page.php?id=<?php echo (int)$temple_id; ?>"><i class="fa-solid fa-place-of-worship"></i> กลับไปหน้าวัด</a>
            <a class="action-button action-button-primary" href="booking.php?temple_id=<?php echo (int)$temple_id; ?>"><i class="fa-solid fa-calendar-plus"></i> จองบริการอื่น</a>
        </div>
    </main>
</body>
</html>