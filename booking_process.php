<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$total_booking_price = 0.0;
$created_booking_ids = [];

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
        // เคลียร์สถานะ Transaction ที่อาจค้างจาก Connection Pooler ให้สะอาด 100%
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            try { $pdo->exec("ROLLBACK;"); } catch (Throwable $t) {}
        }

        $stmt_details = $pdo->prepare("
            SELECT i.item_name, i.price, i.item_type, t.name AS temple_name,
                   t.bank_name, t.bank_account_no, t.bank_account_name, t.promptpay_id, t.qr_code_image
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

        $unit_price = isset($booking_details['price']) ? (float)$booking_details['price'] : 0.0;

        // คำนวณ ID ถัดไปโดยตรงเพื่อการันตีว่าไม่ชนกับข้อมูลเก่าแน่นอน
        $stmt_max = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM bookings");
        $next_id = (int)$stmt_max->fetchColumn();

        foreach ($data_array as $val) {
            $cur_booking_id = $next_id++;
            $created_booking_ids[] = $cur_booking_id;

            if ($selection_type == 'stalls') {
                $row_price = $unit_price;
                $total_booking_price += $row_price;
                $row_payment_status = ($row_price > 0) ? 'unpaid' : 'paid';

                $stmt = $pdo->prepare("
                    INSERT INTO bookings (id, temple_id, item_id, user_id, booking_date, stall_no, qty_booked, total_price, payment_status, status, contact_name, contact_phone) 
                    VALUES (:id, :temple_id, :item_id, :user_id, CURRENT_DATE, :stall_no, :qty_booked, :total_price, :payment_status, :status, :contact_name, :contact_phone)
                ");
                $stmt->execute([
                    ':id' => $cur_booking_id,
                    ':temple_id' => $temple_id,
                    ':item_id' => $item_id,
                    ':user_id' => $user_id,
                    ':stall_no' => $val,
                    ':qty_booked' => 1,
                    ':total_price' => $row_price,
                    ':payment_status' => $row_payment_status,
                    ':status' => $status,
                    ':contact_name' => $contact_name,
                    ':contact_phone' => $contact_phone
                ]);

            } else {
                $row_price = $unit_price * max(1, (int)$qty);
                $total_booking_price += $row_price;
                $row_payment_status = ($row_price > 0) ? 'unpaid' : 'paid';

                $stmt = $pdo->prepare("
                    INSERT INTO bookings (id, temple_id, item_id, user_id, booking_date, qty_booked, total_price, payment_status, status, contact_name, contact_phone) 
                    VALUES (:id, :temple_id, :item_id, :user_id, :booking_date, :qty_booked, :total_price, :payment_status, :status, :contact_name, :contact_phone)
                ");
                $stmt->execute([
                    ':id' => $cur_booking_id,
                    ':temple_id' => $temple_id,
                    ':item_id' => $item_id,
                    ':user_id' => $user_id,
                    ':booking_date' => $val,
                    ':qty_booked' => $qty,
                    ':total_price' => $row_price,
                    ':payment_status' => $row_payment_status,
                    ':status' => $status,
                    ':contact_name' => $contact_name,
                    ':contact_phone' => $contact_phone
                ]);
            }
        }

        // แจ้งเตือนแอดมินวัดและ superadmin
        if (file_exists('notify.php')) {
            require_once 'notify.php';
            $item_title = $booking_details['item_name'] ?? 'บริการ';
            $first_booking_id = $created_booking_ids[0] ?? 0;
            $price_str = $total_booking_price > 0 ? " (ยอดเงิน: ฿" . number_format($total_booking_price, 2) . ")" : "";
            $notify_msg = "มีคำขอจองใหม่: {$item_title}{$price_str} โดยคุณ {$contact_name} (เบอร์โทร: {$contact_phone})";
            notify_temple_admins($pdo, (int)$temple_id, 'new_booking', (int)$first_booking_id, 'bookings', $notify_msg);
            notify_superadmins($pdo, 'new_booking', (int)$first_booking_id, 'bookings', $notify_msg);
            if ($user_id > 0) {
                create_notification($pdo, $user_id, 'booking_created', (int)$first_booking_id, 'bookings', "ส่งคำขอจอง {$item_title} เรียบร้อยแล้ว (สถานะ: รอตรวจสอบ)");
            }
        }

    } catch (Throwable $e) {
        // ถ้ามีอะไรผิดพลาด ให้ยกเลิกการบันทึก (Rollback) ทันที
        try {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $t) {}
        error_log("Booking submission failed: " . $e->getMessage());
        http_response_code(500);
        die("เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . htmlspecialchars($e->getMessage()));
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
            padding: 30px;
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

        /* การ์ดชำระเงิน */
        .payment-box {
            background: #FFFFFF;
            border: 1px solid #EAE5DE;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 8px 24px rgba(62, 53, 47, .08);
            margin-bottom: 24px;
        }
        .payment-title {
            font-size: 18px;
            font-weight: 600;
            color: #A06816;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #F0ECE6;
            padding-bottom: 12px;
        }
        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }
        .bank-info-table {
            background: #FDFBF7;
            border: 1px solid #EAE5DE;
            border-radius: 8px;
            padding: 16px;
            font-size: 14px;
            line-height: 1.8;
        }
        .bank-info-table strong { color: #2B2522; }
        .qr-section {
            text-align: center;
            background: #FDFBF7;
            border: 1px solid #EAE5DE;
            border-radius: 8px;
            padding: 16px;
        }
        .qr-image {
            max-width: 170px;
            height: auto;
            border-radius: 8px;
            border: 1px solid #EAE5DE;
            background: white;
            padding: 8px;
            margin: 0 auto 10px;
            display: block;
        }
        .slip-upload-form {
            margin-top: 20px;
            background: #FFFDF8;
            border: 2px dashed #C08221;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        .slip-input {
            margin: 12px 0;
            font-size: 14px;
        }
        .btn-upload-slip {
            background: #C08221;
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-upload-slip:hover { background: #A06816; }

        .links { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
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

        @media (max-width: 640px) {
            .booking-card { flex-direction: column; text-align: center; gap: 16px; padding: 20px; }
            .calendar-icon { height: 75px; }
            .calendar-icon svg { width: 75px; height: 75px; }
            .payment-grid { grid-template-columns: 1fr; }
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
                <?php if ($total_booking_price > 0): ?>
                    <span class="price-badge"><i class="fa-solid fa-coins"></i> ยอดชำระ: ฿<?php echo number_format($total_booking_price, 2); ?></span>
                <?php else: ?>
                    <span class="price-badge"><i class="fa-solid fa-gift"></i> ฟรี (ไม่มีค่าใช้จ่าย)</span>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($total_booking_price > 0): ?>
            <!-- กล่องข้อมูลการชำระเงินและแนบสลิป -->
            <section class="payment-box">
                <div class="payment-title">
                    <i class="fa-solid fa-credit-card"></i> ช่องทางการชำระเงินของวัด
                </div>

                <div class="payment-grid">
                    <div class="bank-info-table">
                        <div style="margin-bottom: 8px;">
                            <span style="color:#857B74;">ยอดที่ต้องชำระ:</span>
                            <strong style="font-size: 20px; color:#A06816; display:block;">฿<?php echo number_format($total_booking_price, 2); ?> บาท</strong>
                        </div>
                        <?php if (!empty($booking_details['bank_name'])): ?>
                            <div style="margin-top: 6px;">
                                <strong>ธนาคาร:</strong> <?php echo htmlspecialchars($booking_details['bank_name']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($booking_details['bank_account_no'])): ?>
                            <div style="margin-top: 4px;">
                                <strong>เลขที่บัญชี:</strong> <span style="font-size:16px; font-weight:700; color:#2C3E50;"><?php echo htmlspecialchars($booking_details['bank_account_no']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($booking_details['bank_account_name'])): ?>
                            <div style="margin-top: 4px;">
                                <strong>ชื่อบัญชี:</strong> <?php echo htmlspecialchars($booking_details['bank_account_name']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($booking_details['promptpay_id'])): ?>
                            <div style="margin-top: 4px;">
                                <strong>พร้อมเพย์ (PromptPay):</strong> <?php echo htmlspecialchars($booking_details['promptpay_id']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (empty($booking_details['bank_name']) && empty($booking_details['promptpay_id'])): ?>
                            <div style="color:#E67E22; margin-top:8px;">
                                <i class="fa-solid fa-circle-info"></i> วัดยังไม่ได้ระบุเลขบัญชีในระบบ กรุณาติดต่อวัดโดยตรงหรือแนบสลิปหากโอนแล้ว
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="qr-section">
                        <?php if (!empty($booking_details['qr_code_image']) && file_exists(__DIR__ . '/' . $booking_details['qr_code_image'])): ?>
                            <img src="<?php echo htmlspecialchars($booking_details['qr_code_image']); ?>" alt="QR Code ชำระเงิน" class="qr-image">
                            <span style="font-size: 13px; color:#857B74; display:block;">สแกน QR Code ผ่านแอปธนาคาร</span>
                        <?php else: ?>
                            <div style="padding: 25px 10px; color: #857B74; font-size: 13px;">
                                <i class="fa-solid fa-qrcode fa-3x" style="opacity: 0.3; margin-bottom: 10px; display:block;"></i>
                                โอนผ่านเลขบัญชีหรือพร้อมเพย์ด้านซ้าย
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ฟอร์มอัปโหลดสลิป -->
                <div class="slip-upload-form">
                    <h3 style="margin: 0 0 8px; font-size: 16px; color: #A06816;">
                        <i class="fa-solid fa-receipt"></i> แนบสลิปหลักฐานการโอนเงิน
                    </h3>
                    <p style="margin: 0 0 12px; font-size: 13px; color: #857B74;">
                        เมื่อโอนเงินเรียบร้อยแล้ว แนบสลิปเพื่อให้เจ้าหน้าที่วัดตรวจสอบและอนุมัติการจอง
                    </p>
                    <form action="upload_booking_slip.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="booking_ids" value="<?php echo implode(',', $created_booking_ids); ?>">
                        <input type="hidden" name="redirect_to" value="my_bookings.php">
                        <div>
                            <input type="file" name="slip_image" accept="image/jpeg,image/png,image/webp" required class="slip-input">
                        </div>
                        <button type="submit" class="btn-upload-slip">
                            <i class="fa-solid fa-cloud-arrow-up"></i> อัปโหลดสลิปยืนยันการชำระเงิน
                        </button>
                    </form>
                    <small style="display:block; margin-top: 10px; color: #857B74;">
                        * หากยังไม่สะดวกแนบตอนนี้ ท่านสามารถกลับมาแนบสลิปได้ภายหลังที่เมนู <strong>"ดูรายการจองของฉัน"</strong>
                    </small>
                </div>
            </section>
        <?php endif; ?>

        <div class="links">
            <a class="action-button" href="my_bookings.php"><i class="fa-solid fa-list-check"></i> ดูรายการจองของฉัน</a>
            <a class="action-button" href="temple_page.php?id=<?php echo (int)$temple_id; ?>"><i class="fa-solid fa-place-of-worship"></i> กลับไปหน้าวัด</a>
            <a class="action-button action-button-primary" href="booking.php?temple_id=<?php echo (int)$temple_id; ?>"><i class="fa-solid fa-calendar-plus"></i> จองบริการอื่น</a>
        </div>
    </main>
</body>
</html>
