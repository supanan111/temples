<?php
require_once 'db_config.php';

$temple_id = isset($_GET['temple_id']) ? $_GET['temple_id'] : 1;
if (isset($_GET['type']) && !is_string($_GET['type'])) {
    http_response_code(400);
    die("หมวดบริการไม่ถูกต้อง");
}
$service_type = isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : null;
$item_id = isset($_GET['item_id']) ? $_GET['item_id'] : null;
$service_categories = [
    'pavilion' => ['icon' => 'fa-vihara', 'label' => 'จองศาลา / พิธีศพ'],
    'monk' => ['icon' => 'fa-praying-hands', 'label' => 'นิมนต์พระ'],
    'equipment' => ['icon' => 'fa-chair', 'label' => 'ยืมอุปกรณ์ / ของใช้'],
    'fair_stall' => ['icon' => 'fa-store', 'label' => 'จองล็อคงานวัด'],
];

if ($service_type !== null && !isset($service_categories[$service_type])) {
    http_response_code(400);
    die("ไม่พบหมวดบริการที่เลือก");
}

$year = isset($_GET['y']) ? $_GET['y'] : date('Y');
$thai_months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];

try {
    $stmt = $pdo->prepare("SELECT * FROM temples WHERE id = :id");
    $stmt->execute([':id' => $temple_id]);
    $temple = $stmt->fetch();
    if (!$temple) { die("ไม่พบข้อมูลวัด"); }

    $items = [];
    if ($service_type) {
        $type_condition = ($service_type == 'pavilion') ? "item_type IN ('pavilion_funeral', 'pavilion_merit')" : "item_type = :service_type";
        $stmt_items = $pdo->prepare("SELECT * FROM bookable_items WHERE temple_id = :temple_id AND $type_condition AND is_active = 'yes'");
        if ($service_type == 'pavilion') {
            $stmt_items->execute([':temple_id' => $temple_id]);
        } else {
            $stmt_items->execute([':temple_id' => $temple_id, ':service_type' => $service_type]);
        }
        $items = $stmt_items->fetchAll();
    }

    $booked_days = [];
    $booked_stalls = []; 
    $selected_item = null;
    
    if ($item_id) {
        $selected_type_condition = ($service_type == 'pavilion')
            ? "item_type IN ('pavilion_funeral', 'pavilion_merit')"
            : "item_type = :service_type";
        $stmt_selected = $pdo->prepare("
            SELECT * FROM bookable_items
            WHERE id = :item_id AND temple_id = :temple_id AND is_active = 'yes'
                AND $selected_type_condition
        ");
        $selected_params = [
            ':item_id' => $item_id,
            ':temple_id' => $temple_id,
        ];
        if ($service_type != 'pavilion') {
            $selected_params[':service_type'] = $service_type;
        }
        $stmt_selected->execute($selected_params);
        $selected_item = $stmt_selected->fetch();

        if ($selected_item && $service_type == 'fair_stall') {
            $stmt_bookings = $pdo->prepare("SELECT stall_no FROM bookings WHERE item_id = :item_id AND status != 'rejected' AND stall_no IS NOT NULL");
            $stmt_bookings->execute([':item_id' => $item_id]);
            $booked_stalls = $stmt_bookings->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($selected_item) {
            $stmt_bookings = $pdo->prepare("SELECT booking_date, SUM(qty_booked) as total_booked FROM bookings WHERE item_id = :item_id AND YEAR(booking_date) = :year AND status != 'rejected' GROUP BY booking_date");
            $stmt_bookings->execute([':item_id' => $item_id, ':year' => $year]);
            while ($row = $stmt_bookings->fetch()) { 
                $booked_days[$row['booking_date']] = $row['total_booked']; 
            }
        }
    }
} catch (PDOException $e) { die("เกิดข้อผิดพลาด: " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บริการจอง - <?php echo htmlspecialchars($temple['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 🌟 Theme พุทธศิลป์ร่วมสมัย 🌟 */
        :root { 
            --primary-color: #C08221; /* สีทองหม่น */
            --primary-hover: #A06816; 
            --bg-color: #FDFBF7; /* สีขาวครีมงาช้าง */
            --text-main: #3E352F; /* สีเทาน้ำตาลเข้ม */
            --text-muted: #857B74; 
            --card-bg: #FFFFFF;
            --border-color: #EAE5DE;
            --danger-bg: #FDF2F2;
            --danger-text: #C0392B;
        }
        
        * { box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(180deg, #FBF7EF 0, var(--bg-color) 280px); margin: 0; color: var(--text-main); line-height: 1.6; min-height: 100vh; }
        
        nav { background: rgba(255, 255, 255, 0.94); padding: 12px max(24px, calc((100% - 1200px) / 2)); display: flex; justify-content: space-between; align-items: center; gap: 16px; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 18px rgba(62, 53, 47, 0.05); backdrop-filter: blur(12px); }
        .logo { font-size: 15px; font-weight: 600; color: var(--text-main); text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
        .nav-actions { display: flex; align-items: center; gap: 10px; }
        .nav-link { min-height: 42px; padding: 7px 15px 7px 9px; border: 1px solid #E8D8B8; border-radius: 999px; background: linear-gradient(135deg, #FFFDF8, #F8F0E1); color: var(--primary-hover); text-decoration: none; display: inline-flex; align-items: center; gap: 9px; font-size: 14px; font-weight: 600; box-shadow: 0 3px 10px rgba(62, 53, 47, 0.05); transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; }
        .nav-link:hover { transform: translateY(-1px); border-color: var(--primary-color); box-shadow: 0 6px 16px rgba(192, 130, 33, 0.14); }
        .nav-link:focus-visible, .category-card:focus-visible, .btn-submit:focus-visible { outline: 3px solid rgba(192, 130, 33, 0.35); outline-offset: 3px; }
        .nav-link i { width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--primary-color); color: #fff; font-size: 12px; }
        
        .container { max-width: 1200px; margin: 34px auto 60px; padding: 0 20px; }
        .page-heading { position: relative; overflow: hidden; margin-bottom: 28px; padding: 28px 24px; text-align: center; border: 1px solid #E8D8B8; border-radius: 18px; background: radial-gradient(ellipse at top, rgba(255,255,255,.95), rgba(248,240,225,.88)); box-shadow: 0 12px 30px rgba(62, 53, 47, 0.06); }
        .page-heading::before, .page-heading::after { content: ""; position: absolute; width: 170px; height: 170px; border: 1px solid rgba(192,130,33,.12); border-radius: 50%; pointer-events: none; }
        .page-heading::before { left: -75px; top: -105px; }
        .page-heading::after { right: -75px; bottom: -115px; }
        .page-heading > * { position: relative; z-index: 1; }
        .heading-kicker { margin: 0 0 5px; color: var(--primary-hover); font-size: 13px; font-weight: 600; letter-spacing: .04em; }
        .page-heading h1 { margin: 0; color: var(--text-main); font-size: clamp(23px, 3vw, 30px); font-weight: 600; }
        .page-heading h1 i { color: var(--primary-color); margin-right: 7px; }
        .page-heading p { margin: 7px 0 0; color: var(--text-muted); font-size: 15px; }
        
        /* เมนู 4 หมวดหมู่ */
        .category-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 32px;}
        .category-grid-selected { grid-template-columns: minmax(220px, 340px); justify-content: center; margin-bottom: 24px; }
        .category-card { position: relative; overflow: hidden; background: rgba(255,255,255,.92); border-radius: 14px; padding: 26px 20px; text-align: center; border: 1px solid var(--border-color); text-decoration: none; color: var(--text-main); transition: transform 0.22s, border-color 0.22s, box-shadow 0.22s; box-shadow: 0 5px 16px rgba(62, 53, 47, 0.035); }
        .category-card::before { content: ""; position: absolute; inset: auto 18% 0; height: 3px; border-radius: 3px 3px 0 0; background: var(--primary-color); opacity: 0; transform: scaleX(.5); transition: .22s; }
        .category-card:hover { transform: translateY(-4px); border-color: #D8B77F; box-shadow: 0 12px 26px rgba(192, 130, 33, 0.12); }
        .category-card:hover::before, .category-card-selected::before { opacity: 1; transform: scaleX(1); }
        .category-card-selected { background: linear-gradient(145deg, #fffdf8, #f8f0e1); border-color: #e2c998; box-shadow: 0 8px 22px rgba(192, 130, 33, 0.1); }
        .category-card i { display: inline-flex; width: 58px; height: 58px; align-items: center; justify-content: center; margin-bottom: 13px; border: 1px solid #F0E2C9; border-radius: 18px; background: linear-gradient(145deg, #FFFDF8, #F8F0E1); font-size: 26px; color: var(--primary-color); opacity: 0.95; }
        .category-card h3 { margin: 0; font-size: 16px; font-weight: 600; }

        /* ส่วนเลือกบริการ */
        .booking-section { background: rgba(255,255,255,.95); padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 8px 24px rgba(62, 53, 47, 0.05); }
        .service-label { margin-bottom: 9px; display: flex; align-items: center; gap: 8px; color: var(--text-main); font-weight: 600; }
        .service-label i { color: var(--primary-color); }
        select, input[type="number"] { width: 100%; padding: 13px 15px; font-family: 'Sarabun', sans-serif; border: 1px solid #D5D0C8; border-radius: 9px; background: #FDFBF7; color: var(--text-main); font-size: 15px; outline: none; transition: 0.2s;}
        select:focus, input[type="number"]:focus { border-color: var(--primary-color); background: white; box-shadow: 0 0 0 4px rgba(192,130,33,.1); }
        
        .btn-submit { background: linear-gradient(135deg, #C88A2A, #A96D17); color: white; border: none; padding: 13px 25px; border-radius: 9px; cursor: pointer; font-family: 'Sarabun', sans-serif; font-size: 16px; font-weight: 600; width: 100%; margin-top: 15px; transition: 0.2s; box-shadow: 0 5px 12px rgba(160, 104, 22, .18);}
        .btn-submit:hover { background: linear-gradient(135deg, #B7791F, #8D5912); transform: translateY(-1px); box-shadow: 0 8px 16px rgba(160, 104, 22, .22); }

        .layout-wrapper { display: grid; grid-template-columns: minmax(0, 1.8fr) minmax(280px, .8fr); gap: 22px; align-items: start; margin-top: 28px;}
        .months-container { min-width: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); gap: 16px; background: linear-gradient(145deg, #FAF8F4, #F6F2EA); padding: 18px; border-radius: 14px; border: 1px solid var(--border-color); }
        .calendar-intro { grid-column: 1 / -1; padding: 2px 2px 5px; }
        .calendar-intro h3 { margin: 0; color: var(--text-main); font-size: 19px; font-weight: 600; }
        .calendar-intro h3 i { margin-right: 7px; color: var(--primary-color); }
        .calendar-intro p { margin: 5px 0 0; color: var(--text-muted); font-size: 14px; }
        .calendar-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; margin-top: 13px; }
        .legend-item { display: inline-flex; align-items: center; gap: 7px; color: var(--text-muted); font-size: 12px; }
        .legend-swatch { width: 13px; height: 13px; border: 1px solid var(--border-color); border-radius: 4px; background: #fff; }
        .legend-available { background: #FFFDF8; border-color: #D9C59F; }
        .legend-full { background: var(--danger-bg); border-color: #F0C7C2; }
        .legend-past { background: #F0EEE9; border-color: #E3DED5; }
        .legend-selected { background: var(--primary-color); border-color: var(--primary-hover); }
        
        .month-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 17px; box-shadow: 0 4px 12px rgba(62, 53, 47, 0.035); }
        .month-title { text-align: center; margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 1px solid #F0ECE5; color: var(--text-main); font-size: 16px; font-weight: 600; }

        /* ผังงานวัด (Stall) */
        .stall-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(65px, 1fr)); gap: 10px; width: 100%; }
        .stall-box { min-height: 58px; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 5px; border-radius: 10px; font-size: 16px; font-weight: 600; text-align: center; border: 1px solid var(--border-color); cursor: pointer; transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s; user-select: none; }
        .stall-green { background: linear-gradient(145deg, #fff, #FCFAF5); color: var(--text-main); border-color: #E4DCCF; }
        .stall-green:hover { border-color: var(--primary-color); color: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 5px 12px rgba(192, 130, 33, 0.12); }
        .stall-red { background: var(--danger-bg); color: var(--danger-text); border-color: #F5D3D0; cursor: not-allowed; opacity: 0.85;}
        .stall-selected { background: var(--primary-color) !important; color: white !important; border-color: var(--primary-hover) !important; transform: translateY(-2px); box-shadow: 0 5px 12px rgba(160, 104, 22, .2); }
        
        /* ปฏิทิน */
        .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 5px; text-align: center; }
        .day-name { font-weight: 600; color: #766B5D; padding: 7px 0; border: 0; border-radius: 6px; background: #F7F3EB; font-size: 12px; margin-bottom: 3px; }
        .day-cell { padding: 7px 1px; border-radius: 8px; font-size: 14px; border: 1px solid transparent; transition: transform 0.16s, box-shadow 0.16s, border-color 0.16s, background 0.16s; user-select: none; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 52px;}
        .day-empty { background: transparent; border: none; }
        .day-past { background: #F5F3EF; color: #AAA39A; border-color: #EFEBE5; }
        .day-green { background: linear-gradient(145deg, #FFFFFF, #FFFDF8); color: var(--text-main); border-color: #E8E0D3; cursor: pointer; font-weight: 600; }
        .day-green:hover { border-color: var(--primary-color); color: var(--primary-hover); transform: translateY(-2px); z-index: 10; position: relative; box-shadow: 0 5px 12px rgba(192, 130, 33, 0.13);}
        .day-red { background: var(--danger-bg); color: var(--danger-text); border-color: #F3D8D4; opacity: 0.9; font-weight: 600; }
        .day-selected { background: linear-gradient(145deg, #C98B2C, #A96D17) !important; color: white !important; border-color: var(--primary-hover) !important; transform: translateY(-2px); z-index: 10; box-shadow: 0 5px 12px rgba(160, 104, 22, .2);}
        .status-text { display: block; max-width: 100%; margin-top: 3px; color: var(--text-muted); font-size: 10px; line-height: 1.15; font-weight: 500; white-space: nowrap; }
        .day-red .status-text { color: var(--danger-text); }
        .day-selected .status-text { color: rgba(255,255,255,.9); }

        /* กล่องขวา (สรุปการเลือก) */
        .form-panel { min-width: 0; position: sticky; top: 84px; background: var(--card-bg); border: 1px solid var(--border-color); padding: 22px; border-radius: 14px; border-top: 3px solid var(--primary-color); box-shadow: 0 8px 22px rgba(62, 53, 47, 0.05); }
        .selected-display { background: linear-gradient(145deg, #FBF8F1, #F7F3EB); border: 1px dashed #D5C6A7; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center; font-size: 15px; max-height: 180px; overflow-y: auto; color: var(--text-main); }
        @media (max-width: 760px) {
            nav { padding: 10px 16px; }
            .nav-actions { gap: 7px; }
            .nav-link { min-height: 40px; padding: 6px 10px; font-size: 13px; gap: 6px; }
            .nav-link i { width: 26px; height: 26px; }
            .container { margin-top: 22px; padding: 0 14px; }
            .page-heading { padding: 22px 14px; border-radius: 14px; }
            .page-heading p { font-size: 14px; }
            .category-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
            .category-grid-selected { grid-template-columns: minmax(0, 1fr); }
            .category-card { padding: 20px 10px; border-radius: 12px; }
            .category-card i { width: 48px; height: 48px; border-radius: 15px; font-size: 22px; }
            .category-card h3 { font-size: 14px; }
            .booking-section { padding: 18px; border-radius: 13px; }
            .layout-wrapper { grid-template-columns: minmax(0, 1fr); gap: 16px; }
            .months-container { padding: 12px; gap: 12px; }
            .calendar-intro h3 { font-size: 17px; }
            .calendar-legend { gap: 7px 12px; }
            .legend-item { font-size: 11px; }
            .month-card { padding: 13px 10px; }
            .calendar-grid { gap: 4px; }
            .day-cell { min-height: 46px; font-size: 13px; }
            .status-text { font-size: 9px; }
            .form-panel { position: static; }
        }
        @media (max-width: 420px) {
            .nav-link { padding: 6px 8px; font-size: 12px; }
            .nav-link i { display: none; }
            .logo { font-size: 13px; gap: 6px; }
            .category-grid { grid-template-columns: minmax(0, 1fr); }
        }
    </style>
</head>
<body>

<nav>
    <a href="index.php" class="nav-link"><i class="fas fa-house"></i><span>กลับหน้าหลัก</span></a>
</nav>

<div class="container">
    <header class="page-heading">
        <p class="heading-kicker"><?php echo htmlspecialchars($temple['name'], ENT_QUOTES, 'UTF-8'); ?></p>
        <h1><i class="fas fa-concierge-bell"></i> ระบบบริการออนไลน์</h1>
        <p>เลือกบริการที่ต้องการ แล้วตรวจสอบวันว่างหรือรายการก่อนทำการจอง</p>
    </header>

    <div class="category-grid<?php echo $service_type !== null ? ' category-grid-selected' : ''; ?>">
        <?php foreach ($service_categories as $category_type => $category): ?>
            <?php if ($service_type === null || $service_type === $category_type): ?>
                <a href="booking.php?temple_id=<?php echo rawurlencode((string)$temple_id); ?>&amp;type=<?php echo rawurlencode($category_type); ?>"
                   class="category-card<?php echo $service_type === $category_type ? ' category-card-selected' : ''; ?>"
                   <?php echo $service_type === $category_type ? 'aria-current="page"' : ''; ?>>
                    <i class="fas <?php echo htmlspecialchars($category['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    <h3><?php echo htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php if ($service_type): ?>
        <div class="booking-section">
            <form action="booking.php" method="GET" style="text-align: center;">
                <input type="hidden" name="temple_id" value="<?php echo $temple_id; ?>">
                <input type="hidden" name="type" value="<?php echo $service_type; ?>">
                <div style="display: inline-block; width: 100%; max-width: 500px; text-align: left;">
                    <label class="service-label"><i class="fas fa-list-check"></i>เลือกลักษณะบริการที่ต้องการ</label>
                    <select name="item_id" onchange="this.form.submit()" required>
                        <option value=""> กรุณาเลือกรายการ </option>
                        <?php foreach($items as $item): ?>
                            <option value="<?php echo $item['id']; ?>" <?php echo ($item_id == $item['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['item_name']); ?> 
                                <?php if($service_type == 'fair_stall') echo "(ราคา ฿".number_format($item['price'])."/ล็อค)"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($item_id && $selected_item): ?>
                <div class="layout-wrapper">
                    <div class="months-container">
                        <?php if ($service_type == 'fair_stall'): ?>
                            <div class="calendar-intro">
                                <h3><i class="fas fa-store"></i>แผนผังล็อค</h3>
                                <p>ราคา ฿<?php echo number_format($selected_item['price']); ?> / ล็อค · คลิกเลือกหมายเลขที่ต้องการ</p>
                                <div class="calendar-legend" aria-label="คำอธิบายสถานะล็อค">
                                    <span class="legend-item"><span class="legend-swatch legend-available"></span>ว่าง</span>
                                    <span class="legend-item"><span class="legend-swatch legend-full"></span>จองแล้ว</span>
                                    <span class="legend-item"><span class="legend-swatch legend-selected"></span>เลือกอยู่</span>
                                </div>
                            </div>
                            <div class="stall-grid" style="grid-column: 1 / -1;">
                                <?php
                                for ($i = 1; $i <= $selected_item['total_qty']; $i++) {
                                    $stall_no = str_pad($i, 2, '0', STR_PAD_LEFT); 
                                    if (in_array($stall_no, $booked_stalls) || in_array((string)$i, $booked_stalls)) {
                                        echo "<div class='stall-box stall-red' title='เต็มแล้ว'>$stall_no<br><small style='font-size:10px;'>เต็ม</small></div>";
                                    } else {
                                        echo "<div class='stall-box stall-green' onclick=\"toggleSelection(this, '$stall_no', 'stall')\">$stall_no</div>";
                                    }
                                }
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="calendar-intro">
                                <h3><i class="fas fa-calendar-days"></i>ปฏิทินการจอง ปี <?php echo $year + 543; ?></h3>
                                <p>เลือกวันที่ว่างเพื่อทำรายการจอง</p>
                                <div class="calendar-legend" aria-label="คำอธิบายสถานะวัน">
                                    <span class="legend-item"><span class="legend-swatch legend-available"></span>ว่าง</span>
                                    <span class="legend-item"><span class="legend-swatch legend-full"></span>เต็ม</span>
                                    <span class="legend-item"><span class="legend-swatch legend-past"></span>วันที่ผ่านมา</span>
                                    <span class="legend-item"><span class="legend-swatch legend-selected"></span>เลือกอยู่</span>
                                </div>
                            </div>
                            <?php
                            for ($m = 1; $m <= 12; $m++) {
                                $days_in_this_month = cal_days_in_month(CAL_GREGORIAN, $m, $year);
                                $first_day = date('w', strtotime(sprintf("%04d-%02d-01", $year, $m)));
                            ?>
                                <div class="month-card">
                                    <h4 class="month-title"><?php echo $thai_months[$m]; ?></h4>
                                    <div class="calendar-grid">
                                        <div class="day-name">อา</div><div class="day-name">จ</div><div class="day-name">อ</div>
                                        <div class="day-name">พ</div><div class="day-name">พฤ</div><div class="day-name">ศ</div><div class="day-name">ส</div>
                                        <?php
                                        for ($i = 0; $i < $first_day; $i++) { echo '<div class="day-cell day-empty"></div>'; }
                                        for ($day = 1; $day <= $days_in_this_month; $day++) {
                                            $current_date = sprintf("%04d-%02d-%02d", $year, $m, $day);
                                            $booked_qty = isset($booked_days[$current_date]) ? $booked_days[$current_date] : 0;
                                            $available_qty = $selected_item['total_qty'] - $booked_qty;

                                            if (strtotime($current_date) < strtotime('today')) {
                                                echo "<div class='day-cell day-past'>$day</div>";
                                            } elseif ($available_qty <= 0) {
                                                echo "<div class='day-cell day-red'>$day <span class='status-text'>เต็ม</span></div>";
                                            } else {
                                                echo "<div class='day-cell day-green' onclick=\"toggleSelection(this, '$current_date', 'date')\">$day <span class='status-text'>ว่าง</span></div>";
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php endif; ?>
                    </div>

                    <div class="form-panel">
                        <h3 style="margin-top:0; color: var(--text-main); font-weight: 500;"><i class="fas fa-check-circle" style="color: var(--primary-color);"></i> สรุปการเลือก</h3>
                        <form action="booking_form.php" method="GET" onsubmit="return validateForm()">
                            <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                            <input type="hidden" name="type" value="<?php echo $service_type; ?>">
                            <input type="hidden" name="selection_type" value="<?php echo ($service_type == 'fair_stall') ? 'stalls' : 'dates'; ?>">
                            <input type="hidden" name="selected_data" id="selected_data" value="">

                            <div style="margin-bottom: 15px;">
                                <label style="font-weight: 500; display:block; margin-bottom:8px; color: var(--text-muted);">สิ่งที่คุณเลือก:</label>
                                <div id="display_selection" class="selected-display">
                                    <span style="color: #bdc3c7;"><i class="fas fa-hand-pointer"></i><br>(ยังไม่ได้เลือก)</span>
                                </div>
                            </div>
                            
                            <?php if ($service_type == 'fair_stall'): ?>
                                <div style="background: var(--bg-color); border: 1px solid var(--border-color); padding: 15px; border-radius: 6px; margin-bottom: 15px; text-align: center;">
                                    <span style="color: var(--text-muted); font-size: 14px;">ยอดรวมชำระเบื้องต้น</span><br>
                                    <strong style="font-size: 24px; color: var(--primary-color);">฿<span id="total_price">0</span></strong>
                                </div>
                            <?php elseif ($service_type == 'equipment'): ?>
                                <div style="margin-bottom: 15px;">
                                    <label style="font-weight: 500; display:block; margin-bottom:8px; color: var(--text-main);">จำนวนที่ต้องการยืมต่อวัน:</label>
                                    <input type="number" name="qty" required min="1" max="<?php echo $selected_item['total_qty']; ?>" value="1">
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="qty" value="1">
                            <?php endif; ?>

                            <button type="submit" class="btn-submit">ขั้นตอนต่อไป <i class="fas fa-arrow-right"></i></button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    let selectedItems = [];
    const unitPrice = <?php echo isset($selected_item['price']) ? $selected_item['price'] : 0; ?>;
    const isMultiSelect = <?php echo ($service_type == 'pavilion' || $service_type == 'fair_stall') ? 'true' : 'false'; ?>;

    function toggleSelection(element, value, type) {
        if (!isMultiSelect && type === 'date') {
            document.querySelectorAll('.day-selected').forEach(el => el.classList.remove('day-selected'));
            selectedItems = [];
        }

        const index = selectedItems.indexOf(value);
        if (index > -1) {
            selectedItems.splice(index, 1);
            element.classList.remove(type === 'stall' ? 'stall-selected' : 'day-selected');
        } else {
            selectedItems.push(value);
            element.classList.add(type === 'stall' ? 'stall-selected' : 'day-selected');
        }

        if(type === 'stall') { selectedItems.sort((a, b) => a - b); } else { selectedItems.sort(); }
        
        document.getElementById('selected_data').value = selectedItems.join(',');
        
        const displayDiv = document.getElementById('display_selection');
        if(selectedItems.length > 0) {
            if(type === 'stall') {
                displayDiv.innerHTML = "<strong style='color: var(--primary-color);'>ล็อคเบอร์: " + selectedItems.join(', ') + "</strong>";
                document.getElementById('total_price').innerText = (selectedItems.length * unitPrice).toLocaleString();
            } else {
                let niceDates = selectedItems.map(d => {
                    let parts = d.split('-');
                    return parts[2] + "/" + parts[1] + "/" + parts[0]; 
                });
                displayDiv.innerHTML = "<strong style='color: var(--primary-color);'>" + niceDates.join('<br>') + "</strong>";
            }
        } else {
            displayDiv.innerHTML = "<span style='color: #bdc3c7;'><i class='fas fa-hand-pointer'></i><br>(ยังไม่ได้เลือก)</span>";
            if(type === 'stall') document.getElementById('total_price').innerText = "0";
        }
    }

    function validateForm() {
        if (selectedItems.length === 0) {
            alert('กรุณาคลิกเลือกอย่างน้อย 1 รายการก่อนกดขั้นตอนต่อไปครับ'); return false;
        }
        return true;
    }
</script>

</body>
</html>