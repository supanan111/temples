<?php
session_start();
require_once 'db_config.php';

function donation_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo '<!DOCTYPE html><html lang="th"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>ไม่สามารถทำรายการได้</title><body style="font-family:sans-serif;max-width:540px;margin:60px auto;padding:20px;text-align:center">';
    echo '<h1>ไม่สามารถทำรายการได้</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="javascript:history.back()">กลับไปแก้ไขรายการ</a></body></html>';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$submitted_token = $_POST['csrf_token'] ?? '';
$temple_id = filter_input(INPUT_POST, 'temple_id', FILTER_VALIDATE_INT);
$amount_input = $_POST['amount'] ?? '';
$donor_name_input = $_POST['donor_name'] ?? '';
$tax_deduction_input = $_POST['tax_deduction'] ?? '';

if (!$temple_id || $temple_id < 1) {
    donation_error('ไม่พบข้อมูลวัด กรุณากลับไปเริ่มรายการจากหน้าวัด');
}
$csrf_tokens = $_SESSION['donation_csrf_tokens'] ?? [];
if (!is_string($submitted_token) || !is_array($csrf_tokens) ||
    !isset($csrf_tokens[$submitted_token]) || (int)$csrf_tokens[$submitted_token] !== $temple_id) {
    donation_error('คำขอหมดอายุหรือไม่ถูกต้อง กรุณากลับไปหน้าวัดและทำรายการใหม่');
}
unset($_SESSION['donation_csrf_tokens'][$submitted_token]);
if (!is_string($amount_input) || !preg_match('/\A\d{1,8}(?:\.\d{1,2})?\z/', $amount_input) ||
    (float)$amount_input <= 0) {
    donation_error('จำนวนเงินไม่ถูกต้อง กรุณาระบุจำนวนเงินตั้งแต่ 1 บาทขึ้นไปและไม่เกิน 2 ตำแหน่งทศนิยม');
}
if (!is_string($donor_name_input) || mb_strlen(trim($donor_name_input), 'UTF-8') > 100) {
    donation_error('ชื่อผู้บริจาคยาวเกินกำหนด');
}
if ($tax_deduction_input !== '' && $tax_deduction_input !== 'yes') {
    donation_error('ข้อมูลความประสงค์ติดต่อกลับไม่ถูกต้อง');
}

$amount = number_format((float)$amount_input, 2, '.', '');
$donor_name = trim($donor_name_input) !== '' ? trim($donor_name_input) : 'ผู้ไม่ประสงค์ออกนาม';
$tax_deduction = $tax_deduction_input === 'yes' ? 'yes' : 'no';

try {
    $stmt_temple = $pdo->prepare('SELECT id, name, promptpay_id FROM temples WHERE id = ?');
    $stmt_temple->execute([$temple_id]);
    $temple = $stmt_temple->fetch();
    if (!$temple) {
        donation_error('ไม่พบข้อมูลวัดที่รับบริจาค', 404);
    }

    $promptpay_id = preg_replace('/\D/', '', (string)$temple['promptpay_id']);
    if (!preg_match('/\A(?:0\d{9}|\d{13})\z/', $promptpay_id)) {
        donation_error('วัดนี้ยังไม่ได้ตั้งค่า PromptPay ที่ถูกต้อง จึงยังไม่สามารถรับบริจาคออนไลน์ได้', 409);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO donations (temple_id, amount, donor_name, tax_deduction, status)
         VALUES (:temple_id, :amount, :donor_name, :tax_deduction, 'pending')"
    );
    $stmt->execute([
        ':temple_id' => $temple_id,
        ':amount' => $amount,
        ':donor_name' => $donor_name,
        ':tax_deduction' => $tax_deduction,
    ]);
    $donation_id = (int)$pdo->lastInsertId();
} catch (PDOException $e) {
    error_log('Unable to create donation: ' . $e->getMessage());
    donation_error('บันทึกรายการบริจาคไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', 500);
}

$reference = 'DONATE-' . str_pad((string)$donation_id, 8, '0', STR_PAD_LEFT);
$qr_amount = rtrim(rtrim($amount, '0'), '.');
$manual_amount_entry = (float)$amount === 1.0;
$qr_url = 'https://promptpay.io/' . rawurlencode($promptpay_id) .
    ($manual_amount_entry ? '' : '/' . rawurlencode($qr_amount)) . '.png';
$slip_upload_token = bin2hex(random_bytes(32));
if (!isset($_SESSION['donation_slip_tokens']) || !is_array($_SESSION['donation_slip_tokens'])) {
    $_SESSION['donation_slip_tokens'] = [];
}
$_SESSION['donation_slip_tokens'][$donation_id] = $slip_upload_token;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชำระเงินบริจาค - <?php echo htmlspecialchars($temple['name'], ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { position: relative; isolation: isolate; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 24px 16px; overflow-x: hidden; background: #fff8e6; color: #59483d; font-family: 'Sarabun', sans-serif; }
        body::before, body::after { content: ""; position: fixed; inset: 0; pointer-events: none; }
        body::before { z-index: -2; background: url('assets/images/bg.jpg') center / cover no-repeat; opacity: .32; }
        body::after { z-index: -1; inset: auto 0 0; height: clamp(230px, 38vw, 510px); background: url('assets/images/bgkob.png') center bottom / 100% auto no-repeat; filter: contrast(1.18) saturate(1.2); animation: kob-popup 900ms cubic-bezier(.2,.75,.25,1) both; }
        .temple-art { position: fixed; z-index: -2; left: clamp(100px, 13vw, 220px); bottom: 0; width: min(82vw, 1200px); height: 100vh; object-fit: contain; object-position: left bottom; pointer-events: none; }
        .card { position: relative; z-index: 1; width: min(100%, 540px); margin-left: min(24vw, 300px); overflow: hidden; border-radius: 32px; background: #fffaf1; box-shadow: 0 18px 48px rgba(83,61,43,.2), 0 3px 12px rgba(83,61,43,.08); animation: card-popup 700ms cubic-bezier(.2,.75,.25,1) 120ms both; }
        .header { padding: 25px 28px 18px; background: transparent; color: #826b5c; text-align: center; }
        .header h1 { margin: 0 0 6px !important; font-size: 28px !important; font-weight: 500; }
        .header div { color: #8b796b; font-size: 14px; }
        .body { padding: 8px 32px 30px; text-align: center; }
        .qr { display: inline-block; max-width: 100%; padding: 12px; border: 1px solid #eadfc9; border-radius: 18px; background: #fffdf8; }
        .qr img { display: block; width: min(250px, 65vw); height: auto; aspect-ratio: 1; image-rendering: pixelated; }
        .details { margin: 18px 0; padding: 15px 18px; border: 1px solid #eadfc9; border-radius: 18px; background: #f6ecd7; text-align: left; overflow-wrap: anywhere; }
        .details p { margin: 7px 0; color: #715b4d; }
        .amount { color: #826b5c !important; text-align: center; font-size: 27px; font-weight: 700; }
        .notice { margin: 12px 4px; color: #715b4d; font-size: 13px; line-height: 1.6; }
        .slip-form { margin-top: 20px; padding: 17px; border: 1px solid #eadfc9; border-radius: 18px; background: #fffdf8; text-align: left; }
        .slip-form label { display: block; margin: 0 4px 9px; color: #826b5c; font-size: 16px; font-weight: 500; }
        .slip-form input[type=file] { width: 100%; margin-bottom: 12px; color: #715b4d; font: 14px 'Sarabun', sans-serif; }
        .slip-form small { color: #8b796b !important; }
        .slip-form button { width: 100%; padding: 11px; border: 0; border-radius: 999px; background: #826b5c; color: white; font: 500 16px 'Sarabun', sans-serif; cursor: pointer; transition: background .3s, transform .2s; }
        .slip-form button:hover { transform: translateY(-2px); background: #715b4d; }
        .home { display: inline-block; margin-top: 16px; color: #826b5c; text-decoration: none; font-size: 14px; }
        .home:hover { color: #59483d; }
        @keyframes kob-popup { from { opacity: 0; translate: 0 36px; } to { opacity: 1; translate: 0 0; } }
        @keyframes card-popup { from { opacity: 0; scale: .96; translate: 0 20px; } to { opacity: 1; scale: 1; translate: 0 0; } }
        @media (prefers-reduced-motion: reduce) { body::after, .card { animation: none; } }
        @media (max-width: 960px) {
            body::before { background-position: center; opacity: .24; }
            body::after { height: clamp(150px, 50vw, 250px); background-size: 100% auto; }
            .temple-art { left: 6%; bottom: 90px; width: 125vw; height: 72vh; opacity: .82; }
            .card { width: min(100%, 540px); margin: 0; border-radius: 26px; }
            .header { padding: 23px 20px 14px; }
            .header h1 { font-size: 25px !important; }
            .body { padding: 8px 20px 25px; }
            .details { padding: 13px; }
        }
    </style>
</head>
<body>
<img class="temple-art" src="assets/images/bgmid.png" alt="" aria-hidden="true">
<main class="card">
    <header class="header">
        <h1 style="font-size:22px;margin:0">PromptPay QR</h1>
        <div><?php echo htmlspecialchars($temple['name'], ENT_QUOTES, 'UTF-8'); ?></div>
    </header>
    <div class="body">
        <div class="qr">
            <img src="<?php echo htmlspecialchars($qr_url, ENT_QUOTES, 'UTF-8'); ?>" alt="QR Code สำหรับบริจาคผ่าน PromptPay">
        </div>
        <div class="details">
            <p><strong>เลขอ้างอิง:</strong> <?php echo htmlspecialchars($reference, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>ชื่อผู้รับ:</strong> <?php echo htmlspecialchars($temple['name'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>PromptPay:</strong> <?php echo htmlspecialchars($promptpay_id, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>ผู้บริจาค:</strong> <?php echo htmlspecialchars($donor_name, ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="amount">฿<?php echo number_format((float)$amount, 2); ?></p>
        </div>
        <?php if ($manual_amount_entry): ?>
            <p class="notice"><strong>ยอด 1 บาท:</strong> สแกน QR PromptPay แล้วกรอกจำนวนเงิน 1 บาทในแอปธนาคาร จากนั้นตรวจสอบชื่อผู้รับว่าเป็นวัดก่อนยืนยันการโอน</p>
        <?php endif; ?>
        <p class="notice">โปรดตรวจสอบชื่อบัญชีและจำนวนเงินก่อนโอน หากแอปธนาคารแจ้งว่า QR ไม่ถูกต้อง กรุณาอย่าโอนและติดต่อวัดเพื่อตรวจสอบหมายเลข PromptPay ที่ตั้งไว้ หลังโอนแล้วแนบสลิปด้านล่าง รายการจะรอตรวจสอบจนกว่าวัดจะยืนยันยอดเงินจริง</p>
        <form class="slip-form" action="upload_donation_slip.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="donation_id" value="<?php echo $donation_id; ?>">
            <input type="hidden" name="upload_token" value="<?php echo htmlspecialchars($slip_upload_token, ENT_QUOTES, 'UTF-8'); ?>">
            <label for="slip_image">หลังโอนแล้ว แนบรูปสลิปเพื่อให้วัดตรวจสอบ</label>
            <input id="slip_image" type="file" name="slip_image" accept="image/jpeg,image/png,image/webp" required>
            <small style="display:block;color:#667;margin-bottom:10px">รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 5 MB</small>
            <button type="submit">ส่งหลักฐานการโอน</button>
        </form>
        <a class="home" href="temple_page.php?id=<?php echo (int)$temple_id; ?>">กลับไปหน้าวัด</a>
    </div>
</main>
</body>
</html>
