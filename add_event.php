<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มกิจกรรมใหม่ - ระบบจัดการวัด</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f4f6f9; padding: 40px 20px; }
        .form-container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-top: 4px solid #3498db; }
        h2 { color: #2c3e50; margin-top: 0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #34495e; }
        input[type="text"], input[type="date"], input[type="time"], textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-family: 'Prompt', sans-serif; box-sizing: border-box; }
        .btn-submit { background-color: #3498db; color: white; padding: 12px 25px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
        .btn-submit:hover { background-color: #2980b9; }
        .btn-cancel { background-color: #95a5a6; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin-left: 10px; }
        @media (max-width: 600px) {
            body { padding: 16px 12px; }
            .form-container { padding: 20px 16px; }
            .btn-submit, .btn-cancel { display: block; width: 100%; box-sizing: border-box; text-align: center; margin: 10px 0 0 0; }
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>เพิ่มกิจกรรมใหม่ลงในปฏิทิน</h2>
    <form action="add_event_process.php" method="POST">
        <div class="form-group">
            <label>ชื่องาน / กิจกรรม</label>
            <input type="text" name="event_name" required placeholder="เช่น งานทำบุญวันมาฆบูชา">
        </div>
        <div class="form-group">
            <label>วันที่จัดงาน</label>
            <input type="date" name="event_date" required>
        </div>
        <div class="form-group">
            <label>เวลาที่เริ่มงาน</label>
            <input type="time" name="event_time" required>
        </div>
        <div class="form-group">
            <label>รายละเอียดกิจกรรม (ระบุสถานที่หรือสิ่งที่ต้องเตรียม)</label>
            <textarea name="description" rows="4" placeholder="เช่น ขอเชิญร่วมเวียนเทียน ณ อุโบสถ..."></textarea>
        </div>
        <button type="submit" class="btn-submit">บันทึกกิจกรรม</button>
        <a href="manage_events.php" class="btn-cancel">ยกเลิก</a>
    </form>
</div>

</body>
</html>