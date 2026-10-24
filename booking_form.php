<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $next = 'booking_form.php' . ($query !== '' ? '?' . $query : '');
    header("Location: login.php?next=" . rawurlencode($next));
    exit();
}

// รับข้อมูลที่ส่งมาจากหน้า booking.php
$item_id = isset($_GET['item_id']) ? $_GET['item_id'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : null;
$qty = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;
$selection_type = isset($_GET['selection_type']) ? $_GET['selection_type'] : 'dates'; // แยกรูปแบบ 'dates' หรือ 'stalls'
$selected_data_str = isset($_GET['selected_data']) ? $_GET['selected_data'] : '';

if (!$item_id || !is_string($selected_data_str) || $selected_data_str === '' ||
    !is_string($selection_type) || !in_array($selection_type, ['stalls', 'dates'], true)) {
    die("ข้อมูลไม่ครบถ้วน กรุณากลับไปทำรายการใหม่ผ่านหน้าเว็บของวัด");
}

// แตกข้อความที่ถูกเลือกออกมาเป็น Array (เช่น "01,02,05" หรือ "2026-03-25,2026-03-26")
$data_array = array_values(array_filter(array_map('trim', explode(',', $selected_data_str)), static function ($value) {
    return $value !== '';
}));
if (!$data_array) {
    die("กรุณาเลือกวันที่หรือรายการที่ต้องการจอง");
}

try {
    // ดึงข้อมูลบริการและข้อมูลวัดที่ใช้แสดงในแบบฟอร์มยืนยัน
    $stmt = $pdo->prepare("SELECT bookable_items.*, temples.name AS temple_name, temples.id AS temple_id,
                                  temples.address AS temple_address, temples.image AS temple_image
                           FROM bookable_items 
                           JOIN temples ON bookable_items.temple_id = temples.id 
                           WHERE bookable_items.id = :item_id");
    $stmt->execute([':item_id' => $item_id]);
    $item = $stmt->fetch();

    if (!$item) { die("ไม่พบข้อมูลบริการนี้ในระบบ"); }

    $item['temple_image'] = !empty($item['temple_image']) ? $item['temple_image'] : 'assets/images/download.jpg';
    $item['temple_address'] = trim((string)($item['temple_address'] ?? '')) ?: 'ไม่ได้ระบุที่ตั้งวัด';
    $selected_items = array_map(static function ($value) use ($selection_type) {
        if ($selection_type === 'stalls') {
            return 'ล็อคหมายเลข ' . $value;
        }

        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value
            ? $date->format('d/m/Y')
            : $value;
    }, $data_array);

    // คำนวณราคารวม (เฉพาะสิ่งที่มีราคา เช่น ล็อคงานวัด)
    $total_price = count($data_array) * $item['price'];

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยันการจอง - <?php echo htmlspecialchars($item['temple_name'], ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        :root {
            --primary: #C08221;
            --primary-dark: #A06816;
            --primary-soft: #FBF3E5;
            --text-main: #3E352F;
            --text-muted: #857B74;
            --border: #EAE5DE;
            --card: #FFFFFF;
        }
        body { margin: 0; min-height: 100vh; padding: 42px 20px; background: #FDFBF7; color: var(--text-main); font-family: 'Sarabun', sans-serif; }
        .booking-page { width: 100%; max-width: 1120px; margin: 0 auto; }
        .breadcrumb { margin-bottom: 18px; color: var(--text-muted); font-size: 14px; }
        .breadcrumb a { color: var(--primary-dark); text-decoration: none; }
        .breadcrumb i { margin: 0 8px; color: #B9A994; font-size: 11px; }
        .page-title { margin: 0 0 24px; color: var(--primary-dark); font-size: 25px; font-weight: 600; }
        .page-title i { margin-right: 8px; color: var(--primary); }
        .layout { display: grid; grid-template-columns: minmax(0, 2fr) minmax(285px, .92fr); gap: 22px; align-items: start; }
        .main-card, .summary-card { overflow: hidden; border: 1px solid var(--border); border-radius: 10px; background: var(--card); box-shadow: 0 5px 18px rgba(62, 53, 47, .045); }
        .main-card { padding: 22px; }
        .temple-section { display: grid; grid-template-columns: minmax(200px, 42%) minmax(0, 1fr); gap: 20px; padding-bottom: 22px; border-bottom: 1px solid var(--border); }
        .temple-image-wrap { min-height: 185px; overflow: hidden; border-radius: 7px; background: var(--primary-soft); }
        .temple-image { display: block; width: 100%; height: 100%; min-height: 185px; object-fit: cover; }
        .temple-info h2 { margin: 0 0 6px; font-size: 22px; font-weight: 600; }
        .temple-info .address { display: flex; gap: 8px; margin: 0; color: var(--text-muted); font-size: 14px; line-height: 1.7; }
        .temple-info .address i { margin-top: 5px; color: var(--primary); }
        .temple-label { display: inline-flex; gap: 7px; align-items: center; margin-top: 16px; color: var(--primary-dark); font-size: 13px; font-weight: 500; }
        .section-heading { margin: 20px 0 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border); color: var(--primary-dark); font-size: 17px; font-weight: 600; }
        .section-heading i { margin-right: 7px; color: var(--primary); }
        .contact-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-group { min-width: 0; }
        .form-group label { display: block; margin-bottom: 6px; color: var(--text-muted); font-size: 13px; font-weight: 500; }
        .form-group input { width: 100%; min-height: 44px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 7px; background: #FFFEFC; color: var(--text-main); font: inherit; font-size: 14px; }
        .form-group input:focus { outline: 3px solid rgba(192, 130, 33, .18); border-color: var(--primary); }
        .note-group { margin-top: 12px; }
        .notice { margin: 16px 0 0; color: var(--text-muted); font-size: 13px; line-height: 1.7; }
        .summary-card { padding: 20px; }
        .summary-heading { margin: 0 0 18px; padding-bottom: 13px; border-bottom: 1px solid var(--border); color: var(--primary-dark); font-size: 18px; font-weight: 600; }
        .summary-heading i { margin-right: 8px; color: var(--primary); }
        .summary-block { margin-bottom: 18px; }
        .summary-label { display: block; margin-bottom: 5px; color: var(--text-muted); font-size: 13px; }
        .summary-value { color: var(--text-main); font-size: 15px; font-weight: 500; overflow-wrap: anywhere; }
        .selected-dates { display: grid; gap: 7px; margin: 8px 0 0; padding: 0; list-style: none; }
        .selected-dates li { display: flex; align-items: flex-start; gap: 8px; color: var(--text-main); font-size: 14px; }
        .selected-dates i { margin-top: 4px; color: var(--primary); }
        .summary-rule { margin: 16px 0; border: 0; border-top: 1px solid var(--border); }
        .total-row { display: flex; justify-content: space-between; gap: 12px; color: var(--primary-dark); font-weight: 600; }
        .status-note { margin: 16px 0; padding: 10px 12px; border: 1px solid #F0DDBB; border-radius: 7px; background: var(--primary-soft); color: var(--primary-dark); font-size: 13px; line-height: 1.6; }
        .button { display: inline-flex; width: 100%; min-height: 46px; align-items: center; justify-content: center; gap: 8px; padding: 10px 14px; border: 1px solid var(--primary); border-radius: 7px; background: var(--primary); color: #fff; font-family: inherit; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; transition: background .2s, border-color .2s, transform .2s; }
        .button:hover { transform: translateY(-1px); border-color: var(--primary-dark); background: var(--primary-dark); }
        .button:focus-visible { outline: 3px solid rgba(192, 130, 33, .3); outline-offset: 2px; }
        .back-link { display: block; margin-top: 12px; color: var(--text-muted); font-size: 14px; text-align: center; text-decoration: none; }
        .back-link:hover { color: var(--primary-dark); }
        @media (max-width: 760px) {
            body { padding: 24px 14px; }
            .layout { grid-template-columns: 1fr; }
            .summary-card { grid-row: 1; }
        }
        @media (max-width: 520px) {
            .page-title { font-size: 21px; }
            .main-card { padding: 16px; }
            .temple-section { grid-template-columns: 1fr; gap: 14px; }
            .temple-image-wrap, .temple-image { min-height: 190px; max-height: 240px; }
            .contact-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class="booking-page">
        <div class="breadcrumb">
            <a href="index.php"><i class="fa-solid fa-house"></i> หน้าหลัก</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="temple_page.php?id=<?php echo (int)$item['temple_id']; ?>">หน้าวัด</a>
            <i class="fa-solid fa-chevron-right"></i>
            ยืนยันการจอง
        </div>
        <h1 class="page-title"><i class="fa-solid fa-clipboard-check"></i> ตรวจสอบและยืนยันการจอง</h1>

        <form action="booking_process.php" method="POST">
            <input type="hidden" name="temple_id" value="<?php echo (int)$item['temple_id']; ?>">
            <input type="hidden" name="item_id" value="<?php echo (int)$item_id; ?>">
            <input type="hidden" name="selection_type" value="<?php echo htmlspecialchars($selection_type, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="selected_data" value="<?php echo htmlspecialchars(implode(',', $data_array), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="qty" value="<?php echo (int)$qty; ?>">

            <div class="layout">
                <section class="main-card" aria-label="ข้อมูลวัดและผู้ติดต่อ">
                    <div class="temple-section">
                        <div class="temple-image-wrap">
                            <img
                                class="temple-image"
                                src="<?php echo htmlspecialchars($item['temple_image'], ENT_QUOTES, 'UTF-8'); ?>"
                                alt="รูป<?php echo htmlspecialchars($item['temple_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                onerror="this.onerror=null; this.src='assets/images/download.jpg';">
                        </div>
                        <div class="temple-info">
                            <h2><?php echo htmlspecialchars($item['temple_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p class="address"><i class="fa-solid fa-location-dot"></i><span><?php echo htmlspecialchars($item['temple_address'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                            <span class="temple-label"><i class="fa-solid fa-place-of-worship"></i> สถานที่ให้บริการ</span>
                        </div>
                    </div>

                    <h2 class="section-heading"><i class="fa-regular fa-address-card"></i> รายละเอียดผู้ติดต่อ</h2>
                    <div class="contact-grid">
                        <div class="form-group">
                            <label for="contact_name">ชื่อ-นามสกุล <span style="color:var(--primary);">*</span></label>
                            <input id="contact_name" type="text" name="contact_name" required maxlength="100"
                                   value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="กรอกชื่อผู้ติดต่อ">
                        </div>
                        <div class="form-group">
                            <label for="contact_phone">เบอร์โทรศัพท์ <span style="color:var(--primary);">*</span></label>
                            <input id="contact_phone" type="tel" name="contact_phone" required pattern="[0-9]{9,10}"
                                   title="กรุณากรอกเบอร์โทรศัพท์ 9-10 หลัก" placeholder="เช่น 0812345678">
                        </div>
                    </div>
                    <div class="form-group note-group">
                        <label for="mhaihet">หมายเหตุเพิ่มเติม</label>
                        <input id="mhaihet" type="text" name="mhaihet" required placeholder="ระบุรายละเอียดเพิ่มเติม">
                    </div>
                    <p class="notice">เมื่อส่งคำขอแล้ว สถานะจะเป็น “รอตรวจสอบ” กรุณารอเจ้าหน้าที่วัดติดต่อกลับเพื่อยืนยันคิวและรายละเอียด</p>
                    <button type="submit" class="button" style="margin-top: 18px;"><i class="fa-solid fa-paper-plane"></i> ยืนยันข้อมูลการจอง</button>
                </section>

                <aside class="summary-card" aria-label="รายละเอียดการจอง">
                    <h2 class="summary-heading"><i class="fa-regular fa-rectangle-list"></i> รายละเอียดการจอง</h2>
                    <div class="summary-block">
                        <span class="summary-label">รายการที่ต้องการ</span>
                        <div class="summary-value"><?php echo htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="summary-block">
                        <span class="summary-label"><?php echo $selection_type === 'stalls' ? 'ล็อคที่เลือก' : 'วันที่เลือก'; ?> (<?php echo count($selected_items); ?> รายการ)</span>
                        <ul class="selected-dates">
                            <?php foreach ($selected_items as $selected_item): ?>
                                <li><i class="<?php echo $selection_type === 'stalls' ? 'fa-solid fa-store' : 'fa-regular fa-calendar-check'; ?>"></i><span><?php echo htmlspecialchars($selected_item, ENT_QUOTES, 'UTF-8'); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php if ($type === 'equipment'): ?>
                        <hr class="summary-rule">
                        <div class="total-row">
                            <span>จำนวนที่ยืมต่อวัน</span>
                            <span><?php echo (int)$qty; ?> หน่วย</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($item['price'] > 0): ?>
                        <hr class="summary-rule">
                        <div class="total-row">
                            <span>ยอดชำระเบื้องต้น</span>
                            <span>฿<?php echo number_format($total_price, 2); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="status-note"><i class="fa-regular fa-clock"></i> หลังส่งคำขอ สถานะจะเป็นรอเจ้าหน้าที่ตรวจสอบ</div>
                    <a href="javascript:history.back()" class="back-link">กลับไปแก้ไขข้อมูล</a>
                </aside>
            </div>
        </form>
    </main>
</body>
</html>