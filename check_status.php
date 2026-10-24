<?php
require_once 'db_config.php';

$phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';
$bookings = [];

if ($phone !== '') {
    try {
        // ใช้เทคนิค GROUP BY รวบข้อมูลการจองที่ทำพร้อมกัน (เวลาเดียวกัน) ให้เป็นก้อนเดียว
        $stmt = $pdo->prepare("
            SELECT 
                b.item_id, b.status, b.contact_name, b.qty_booked,
                MIN(b.booking_date) as start_date, 
                MAX(b.booking_date) as end_date,
                MIN(b.created_at) as created_at,
                GROUP_CONCAT(b.stall_no ORDER BY b.stall_no ASC) as stall_nos,
                COUNT(b.id) as total_items,
                i.item_name, i.item_type, i.price,
                t.name as temple_name, t.id as temple_id
            FROM bookings b 
            JOIN bookable_items i ON b.item_id = i.id 
            JOIN temples t ON b.temple_id = t.id
            WHERE b.contact_phone = :phone
            GROUP BY b.item_id, b.status, b.contact_name, DATE_FORMAT(b.created_at, '%Y-%m-%d %H:%i')
            ORDER BY created_at DESC
        ");
        $stmt->execute([':phone' => $phone]);
        $bookings = $stmt->fetchAll();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบสถานะการจอง - แพลตฟอร์มศูนย์กลางวัด</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #d35400; --secondary-color: #e67e22; --bg-color: #f0f2f5; }
        body { font-family: 'Prompt', sans-serif; background-color: var(--bg-color); margin: 0; color: #333; }
        nav { background-color: white; padding: 15px 50px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        .logo { font-size: 20px; font-weight: 600; color: var(--primary-color); text-decoration: none; }
        
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        
        .search-box { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 10px 20px rgba(0,0,0,0.05); text-align: center; border-top: 5px solid var(--primary-color); margin-bottom: 30px; }
        .search-box h2 { margin-top: 0; color: #2c3e50; }
        .search-box p { color: #7f8c8d; margin-bottom: 20px; }
        
        .form-group { display: flex; max-width: 500px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius: 30px; overflow: hidden; border: 1px solid #ddd; }
        .form-group input { flex-grow: 1; border: none; padding: 15px 20px; font-size: 16px; font-family: 'Prompt', sans-serif; outline: none; }
        .form-group button { background: var(--primary-color); color: white; border: none; padding: 0 30px; font-size: 16px; cursor: pointer; font-family: 'Prompt', sans-serif; transition: 0.3s; }
        .form-group button:hover { background: #c0392b; }

        /* การ์ดแสดงผลลัพธ์ */
        .result-card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); position: relative; overflow: hidden; border-left: 5px solid transparent; }
        .result-card.status-pending { border-left-color: #f39c12; }
        .result-card.status-approved { border-left-color: #27ae60; }
        .result-card.status-rejected { border-left-color: #e74c3c; }
        
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; border-bottom: 1px dashed #eee; padding-bottom: 15px; }
        .temple-name { font-size: 18px; font-weight: 600; color: #2c3e50; margin: 0 0 5px 0; }
        .item-name { color: var(--primary-color); font-weight: 500; font-size: 16px; margin: 0; }
        .booking-date { font-size: 13px; color: #95a5a6; }
        
        .badge { padding: 6px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; }
        .badge-pending { background: #fef9e7; color: #f39c12; }
        .badge-approved { background: #e8f8f5; color: #27ae60; }
        .badge-rejected { background: #fdedec; color: #e74c3c; }

        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 15px; }
        .detail-item { background: #f8f9fa; padding: 12px; border-radius: 8px; }
        .detail-label { display: block; color: #7f8c8d; font-size: 13px; margin-bottom: 3px; }
        .detail-value { font-weight: 500; color: #34495e; }
        
        .price-tag { text-align: right; font-size: 18px; font-weight: 600; color: #27ae60; margin-top: 15px; }
        
        .empty-state { text-align: center; padding: 40px; background: white; border-radius: 10px; color: #7f8c8d; }

        @media (max-width: 768px) {
            nav { padding: 12px 16px; }
            .container { margin: 20px auto; padding: 0 14px; }
            .search-box { padding: 20px 16px; margin-bottom: 20px; }
            .search-box h2 { font-size: 20px; }
            .search-box p { font-size: 13px; margin-bottom: 16px; }
            .result-card { padding: 18px 14px; }
            .card-header { flex-direction: column; gap: 10px; align-items: flex-start; }
            .details-grid { grid-template-columns: 1fr; gap: 10px; }
        }

        @media (max-width: 480px) {
            .form-group {
                flex-direction: column;
                border-radius: 12px;
            }
            .form-group input {
                padding: 12px 14px;
                font-size: 15px;
                width: 100%;
                box-sizing: border-box;
            }
            .form-group button {
                padding: 12px;
                width: 100%;
                border-top: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>

<nav>
    <a href="index.php" class="logo"><i class="fas fa-home"></i> กลับหน้าหลัก</a>
</nav>

<div class="container">
    <div class="search-box">
        <h2><i class="fas fa-search" style="color: var(--primary-color);"></i> ตรวจสอบสถานะการจอง</h2>
        <p>กรุณากรอกเบอร์โทรศัพท์ที่คุณใช้ทำรายการจอง เพื่อตรวจสอบสถานะ</p>
        
        <form action="check_status.php" method="GET" class="form-group">
            <input type="tel" name="phone" placeholder="ระบุเบอร์โทรศัพท์ 10 หลัก..." value="<?php echo htmlspecialchars($phone); ?>" required pattern="[0-9]{9,10}">
            <button type="submit">ค้นหาข้อมูล</button>
        </form>
    </div>

    <?php if ($phone !== ''): ?>
        <h3 style="color: #2c3e50; margin-bottom: 20px;"><i class="fas fa-list"></i> ผลการค้นหาสำหรับเบอร์: <?php echo htmlspecialchars($phone); ?></h3>
        
        <?php if (count($bookings) > 0): ?>
            <?php foreach ($bookings as $row): 
                // กำหนดคลาสสีให้การ์ด
                $status_class = 'status-' . $row['status'];
                
                // จัดรูปแบบวันที่ให้สวยงาม
                $start = strtotime($row['start_date']);
                $end = strtotime($row['end_date']);
                $date_display = ($start == $end) ? date('d/m/Y', $start) : date('d/m/Y', $start) . " - " . date('d/m/Y', $end);
                
                // คำนวณราคางานวัด
                $total_price = 0;
                if ($row['item_type'] == 'fair_stall') {
                    $total_price = $row['total_items'] * $row['price'];
                }
            ?>
                <div class="result-card <?php echo $status_class; ?>">
                    <div class="card-header">
                        <div>
                            <p class="temple-name"><i class="fas fa-map-marker-alt" style="color:#e74c3c;"></i> <?php echo htmlspecialchars($row['temple_name']); ?></p>
                            <p class="item-name"><?php echo htmlspecialchars($row['item_name']); ?></p>
                            <span class="booking-date">ทำรายการเมื่อ: <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></span>
                        </div>
                        <div>
                            <?php if($row['status'] == 'pending'): ?>
                                <span class="badge badge-pending"><i class="fas fa-clock"></i> รอการตรวจสอบ</span>
                            <?php elseif($row['status'] == 'approved'): ?>
                                <span class="badge badge-approved"><i class="fas fa-check-circle"></i> อนุมัติแล้ว</span>
                            <?php else: ?>
                                <span class="badge badge-rejected"><i class="fas fa-times-circle"></i> ไม่อนุมัติ/ยกเลิก</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label"><?php echo ($row['item_type'] == 'fair_stall') ? 'เบอร์ล็อคที่จอง' : 'วันที่ขอใช้งาน'; ?></span>
                            <span class="detail-value" style="color: #2980b9;">
                                <?php 
                                if ($row['item_type'] == 'fair_stall') {
                                    echo "ล็อค " . htmlspecialchars($row['stall_nos']);
                                } else {
                                    echo $date_display;
                                }
                                ?>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label"><?php echo ($row['item_type'] == 'fair_stall' || $row['item_type'] == 'pavilion_funeral' || $row['item_type'] == 'pavilion_merit') ? 'จำนวนที่จอง' : 'จำนวนที่ขอยืม'; ?></span>
                            <span class="detail-value">
                                <?php echo $row['total_items']; ?> <?php echo ($row['item_type'] == 'fair_stall') ? 'ล็อค' : (($row['item_type'] == 'equipment') ? 'หน่วย' : 'วัน'); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($row['item_type'] == 'fair_stall' && $total_price > 0): ?>
                        <div class="price-tag">
                            ยอดชำระสุทธิ: ฿<?php echo number_format($total_price, 2); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($row['status'] == 'approved'): ?>
                        <div style="margin-top: 15px; padding: 10px; background: #e8f8f5; border-radius: 5px; font-size: 13px; color: #27ae60;">
                            <i class="fas fa-info-circle"></i> การจองของคุณได้รับการอนุมัติแล้ว กรุณาติดต่อวัดเพื่อดำเนินการขั้นตอนต่อไป
                        </div>
                    <?php elseif($row['status'] == 'rejected'): ?>
                        <div style="margin-top: 15px; padding: 10px; background: #fdedec; border-radius: 5px; font-size: 13px; color: #e74c3c;">
                            <i class="fas fa-info-circle"></i> รายการนี้ถูกยกเลิก หรือคิวไม่ว่าง หากมีข้อสงสัยกรุณาติดต่อเจ้าหน้าที่วัด
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search-minus fa-4x" style="color: #bdc3c7; margin-bottom: 15px;"></i>
                <h3>ไม่พบประวัติการจอง</h3>
                <p>ไม่พบข้อมูลการจองที่เชื่อมโยงกับเบอร์โทรศัพท์ <strong><?php echo htmlspecialchars($phone); ?></strong></p>
                <a href="index.php" style="display: inline-block; margin-top: 10px; color: var(--primary-color); text-decoration: none; font-weight: 500;">กลับไปค้นหาวัดเพื่อทำรายการใหม่</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>