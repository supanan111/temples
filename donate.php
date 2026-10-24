<?php
session_start();
require_once 'db_config.php';

$temple_id = filter_input(INPUT_GET, 'temple_id', FILTER_VALIDATE_INT);
if (!$temple_id || $temple_id < 1) {
    http_response_code(400);
    exit('ไม่พบข้อมูลวัดที่ต้องการรับบริจาค');
}

try {
    $stmt = $pdo->prepare('SELECT id, name, promptpay_id FROM temples WHERE id = ?');
    $stmt->execute([$temple_id]);
    $temple = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Unable to load donation temple: ' . $e->getMessage());
    http_response_code(500);
    exit('ไม่สามารถโหลดข้อมูลวัดได้ กรุณาลองใหม่อีกครั้ง');
}

if (!$temple) {
    http_response_code(404);
    exit('ไม่พบข้อมูลวัดที่ต้องการรับบริจาค');
}

$csrf_tokens = $_SESSION['donation_csrf_tokens'] ?? [];
if (!is_array($csrf_tokens)) {
    $csrf_tokens = [];
}
$csrf_token = bin2hex(random_bytes(32));
$csrf_tokens[$csrf_token] = (int)$temple['id'];
if (count($csrf_tokens) > 10) {
    $csrf_tokens = array_slice($csrf_tokens, -10, null, true);
}
$_SESSION['donation_csrf_tokens'] = $csrf_tokens;
$promptpay_value = is_string($temple['promptpay_id']) ? $temple['promptpay_id'] : '';
$promptpay_configured = preg_match('/\A(?:0\d{9}|\d{13})\z/', $promptpay_value) === 1;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ร่วมทำบุญออนไลน์ - <?php echo htmlspecialchars($temple['name'], ENT_QUOTES, 'UTF-8'); ?></title>
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
        .donate-container { position: relative; z-index: 1; width: min(100%, 460px); margin-left: min(24vw, 300px); padding: 28px 34px 30px; border-radius: 30px; background: #fffaf1; box-shadow: 0 18px 48px rgba(83,61,43,.2), 0 3px 12px rgba(83,61,43,.08); animation: card-popup 700ms cubic-bezier(.2,.75,.25,1) 120ms both; }
        h1 { margin: 0 0 5px; color: #826b5c; text-align: center; font-size: 27px; font-weight: 500; line-height: 1.35; }
        .intro { margin: 0 0 16px; color: #8b796b; text-align: center; font-size: 13px; }
        .notice { margin: 0 0 16px; padding: 10px 13px; border: 1px solid #e8d8b8; border-radius: 13px; background: #fff6e5; color: #715b4d; font-size: 12px; line-height: 1.5; }
        .form-group { margin: 15px 0; }
        .form-group > label { display: block; margin: 0 8px 6px; color: #826b5c; font-size: 16px; font-weight: 500; }
        input[type="number"], input[type="text"] { width: 100%; padding: 10px 16px; border: 1px solid transparent; border-radius: 999px; outline: none; background: #f6ecd7; color: #59483d; font: 14px 'Sarabun', sans-serif; transition: border-color .3s, box-shadow .3s; }
        input[type="number"]:focus, input[type="text"]:focus { border-color: #826b5c; box-shadow: 0 0 0 3px rgba(130,107,92,.15); }
        input::placeholder { color: #b9ab91; }
        .amount-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 9px; }
        .amount-grid button { padding: 8px; border: 1px solid #e6d8c0; border-radius: 999px; background: #fffdf8; color: #826b5c; font: 500 14px 'Sarabun', sans-serif; cursor: pointer; transition: background .2s, color .2s, transform .2s; }
        .amount-grid button:hover { transform: translateY(-1px); border-color: #826b5c; }
        .amount-grid button.selected { border-color: #826b5c; background: #826b5c; color: white; }
        .checkbox-group { display: flex; gap: 9px; align-items: flex-start; margin: 16px 0; padding: 11px 13px; border: 1px solid #eadfc9; border-radius: 13px; background: #fffdf8; font-size: 12px; line-height: 1.45; }
        .checkbox-group input { flex: 0 0 auto; width: 17px; height: 17px; margin: 3px 0 0; accent-color: #826b5c; }
        .checkbox-group label { margin: 0; color: #715b4d; cursor: pointer; }
        .checkbox-group small { color: #8b796b; }
        .submit-btn { width: 100%; margin-top: 4px; padding: 10px; border: 0; border-radius: 999px; background: #826b5c; color: white; font: 500 16px 'Sarabun', sans-serif; cursor: pointer; transition: background .3s, transform .2s; }
        .submit-btn:hover { transform: translateY(-2px); background: #715b4d; }
        .back-link { display: block; margin-top: 15px; color: #826b5c; text-align: center; text-decoration: none; font-size: 13px; }
        .back-link:hover { color: #59483d; }
        @keyframes kob-popup { from { opacity: 0; translate: 0 36px; } to { opacity: 1; translate: 0 0; } }
        @keyframes card-popup { from { opacity: 0; scale: .96; translate: 0 20px; } to { opacity: 1; scale: 1; translate: 0 0; } }
        @media (prefers-reduced-motion: reduce) { body::after, .donate-container { animation: none; } }
        @media (max-width: 960px) {
            body::before { background-position: center; opacity: .24; }
            body::after { height: clamp(150px, 50vw, 250px); background-size: 100% auto; }
            .temple-art { left: 6%; bottom: 90px; width: 125vw; height: 72vh; opacity: .82; }
            .donate-container { width: min(100%, 460px); margin: 0; padding: 26px 22px 25px; border-radius: 25px; }
            h1 { font-size: 24px; }
            .form-group > label { font-size: 15px; }
        }
    </style>
</head>
<body>
<img class="temple-art" src="assets/images/bgmid.png" alt="" aria-hidden="true">
<main class="donate-container">
    <h1>ร่วมทำบุญออนไลน์</h1>
    <p class="intro"><?php echo htmlspecialchars($temple['name'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($promptpay_configured): ?>
        <p class="notice">ร่วมทำบุญผ่าน PromptPay หลังโอนแล้ว รายการจะรอตรวจสอบจากผู้ดูแลวัด และนับยอดเมื่อวัดยืนยันว่าได้รับเงินจริง</p>
        <form action="donate_qr.php" method="POST">
            <input type="hidden" name="temple_id" value="<?php echo (int)$temple['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="custom_amount">จำนวนเงินบริจาค (บาท)</label>
                <div class="amount-grid" aria-label="เลือกจำนวนเงิน">
                    <button type="button" data-amount="100" aria-pressed="false">100 บาท</button>
                    <button type="button" data-amount="500" aria-pressed="false">500 บาท</button>
                    <button type="button" data-amount="1000" aria-pressed="false">1,000 บาท</button>
                </div>
                <input type="number" id="custom_amount" name="amount" min="1" max="99999999.99" step="0.01" placeholder="ระบุจำนวนเงิน" required>
            </div>
            <div class="form-group">
                <label for="donor_name">ชื่อผู้บริจาค (เว้นว่างเพื่อไม่ระบุชื่อ)</label>
                <input type="text" id="donor_name" name="donor_name" maxlength="100" placeholder="ผู้ไม่ประสงค์ออกนาม">
            </div>
            <div class="form-group checkbox-group">
                <input type="checkbox" id="tax_deduction" name="tax_deduction" value="yes">
                <label for="tax_deduction">แจ้งความประสงค์ขอใบอนุโมทนาบัตร<br>
                    <small>วัดจะตรวจสอบคำขอแยกต่างหาก ยังไม่ใช่การส่งข้อมูลเข้าระบบ e-Donation กรมสรรพากร</small>
                </label>
            </div>
            <button class="submit-btn" type="submit">สร้าง QR Code เพื่อโอนเงิน</button>
        </form>
    <?php else: ?>
        <p class="notice">ขณะนี้วัดยังไม่ได้เปิดรับบริจาคผ่าน PromptPay กรุณาติดต่อวัดเพื่อสอบถามช่องทางร่วมทำบุญ</p>
    <?php endif; ?>
    <a class="back-link" href="temple_page.php?id=<?php echo (int)$temple['id']; ?>">กลับไปหน้าวัด</a>
</main>
<script>
    const amountInput = document.getElementById('custom_amount');
    const amountButtons = document.querySelectorAll('[data-amount]');
    amountButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            amountInput.value = button.dataset.amount;
            amountButtons.forEach(function (item) {
                const selected = item === button;
                item.classList.toggle('selected', selected);
                item.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
        });
    });
    amountInput.addEventListener('input', function () {
        amountButtons.forEach(function (button) {
            const selected = amountInput.value === button.dataset.amount;
            button.classList.toggle('selected', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
    });
</script>
</body>
</html>
