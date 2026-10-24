<?php
require_once 'auth_helpers.php';
$next = safe_booking_return_target($_GET['next'] ?? '');
$error_messages = [
    'required' => 'กรุณากรอกข้อมูลให้ครบถ้วน',
    'username' => 'ชื่อผู้ใช้งานต้องมีความยาว 3-50 ตัวอักษร',
    'password' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษรและกรอกให้ตรงกัน',
    'full_name' => 'ชื่อ-นามสกุลต้องมีความยาวไม่เกิน 100 ตัวอักษร',
    'exists' => 'ชื่อผู้ใช้งานนี้ถูกใช้แล้ว กรุณาเลือกชื่ออื่น',
    'failed' => 'สมัครสมาชิกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง',
];
$error = $error_messages[$_GET['error'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - ระบบจัดการวัด</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            position: relative;
            isolation: isolate;
            font-family: 'Sarabun', sans-serif;
            background: #fff8e6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 24px 16px;
            box-sizing: border-box;
            overflow-x: hidden;
        }
        body::before,
        body::after {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
        }
        body::before {
            z-index: -2;
            background: url('assets/images/bg.jpg') center / cover no-repeat;
            opacity: 0.32;
        }
        body::after {
            z-index: -1;
            inset: auto 0 0;
            height: clamp(230px, 38vw, 510px);
            background: url('assets/images/bgkob.png') center bottom / 100% auto no-repeat;
            filter: contrast(1.18) saturate(1.2);
            animation: kob-popup 900ms cubic-bezier(0.2, 0.75, 0.25, 1) both;
        }
        .temple-art {
            position: fixed;
            z-index: -2;
            left: clamp(100px, 13vw, 220px);
            bottom: 0;
            width: min(82vw, 1200px);
            height: 100vh;
            object-fit: contain;
            object-position: left bottom;
            opacity: 1;
            pointer-events: none;
        }
        .register-wrapper {
            position: relative;
            z-index: 1;
            background-color: #fffaf1;
            padding: 30px 42px 34px;
            border-radius: 34px;
            width: min(100%, 480px);
            box-sizing: border-box;
            transform: translateX(240px);
            box-shadow: 0 18px 48px rgba(83, 61, 43, 0.2), 0 3px 12px rgba(83, 61, 43, 0.08);
            animation: card-popup 700ms cubic-bezier(0.2, 0.75, 0.25, 1) 120ms both;
        }
        h2 {
            color: #826b5c;
            text-align: center;
            margin: 0 0 6px;
            font-size: 30px;
            font-weight: 500;
        }
        .intro {
            color: #8b796b;
            text-align: center;
            margin: 0 0 26px;
            font-size: 13px;
        }
        .input-group { margin-bottom: 16px; }
        label {
            display: block;
            margin: 0 8px 6px;
            color: #826b5c;
            font-weight: 500;
            font-size: 18px;
        }
        input {
            width: 100%;
            padding: 11px 17px;
            border: 1px solid transparent;
            border-radius: 999px;
            background: #f6ecd7;
            color: #59483d;
            font-size: 14px;
            font-family: 'Sarabun', sans-serif;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #826b5c;
            box-shadow: 0 0 0 3px rgba(130, 107, 92, 0.15);
        }
        button {
            width: 100%;
            background: #826b5c;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 999px;
            font-size: 17px;
            font-family: 'Sarabun', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
            margin-top: 12px;
        }
        button:hover {
            background: #715b4d;
            transform: translateY(-2px);
        }
        .error {
            color: #c0392b;
            background: #fdecea;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 13px;
            margin: -14px 0 18px;
        }
        .link {
            display: block;
            text-align: center;
            margin-top: 13px;
            color: #826b5c;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s;
        }
        .link:hover { color: #59483d; }
        @keyframes kob-popup {
            from { opacity: 0; translate: 0 36px; }
            to { opacity: 1; translate: 0 0; }
        }
        @keyframes card-popup {
            from { opacity: 0; scale: 0.96; translate: 0 20px; }
            to { opacity: 1; scale: 1; translate: 0 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            body::after,
            .register-wrapper {
                animation: none;
            }
        }
        @media (max-width: 1024px) {
            body {
                justify-content: center;
                padding: 20px 16px;
            }
            .temple-art {
                opacity: 0.4;
                left: 0;
            }
            .register-wrapper {
                transform: none;
                margin: 0 auto;
            }
        }
        @media (max-width: 480px) {
            body {
                justify-content: center;
            }
            body::before {
                background-position: center;
                opacity: 0.24;
            }
            body::after {
                height: clamp(150px, 50vw, 250px);
                background-size: 100% auto;
            }
            .temple-art {
                left: 6%;
                bottom: 90px;
                width: 125vw;
                height: 72vh;
                opacity: 0.82;
            }
            .register-wrapper {
                padding: 30px 22px 28px;
                border-radius: 26px;
                transform: none;
            }
            h2 { font-size: 26px; }
            .intro {
                margin-bottom: 26px;
                font-size: 12px;
            }
            label { font-size: 16px; }
        }
    </style>
</head>
<body>
<img class="temple-art" src="assets/images/bgmid.png" alt="" aria-hidden="true">
<main class="register-wrapper">
    <h2>สมัครสมาชิก</h2>
    <p class="intro">สร้างบัญชีเพื่อจองบริการจากวัดและติดตามสถานะ</p>
    <?php if ($error !== ''): ?><div class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form action="register_process.php" method="POST">
        <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="input-group">
            <label for="full_name">ชื่อ-นามสกุล</label>
            <input type="text" id="full_name" name="full_name" maxlength="100" autocomplete="name" required>
        </div>
        <div class="input-group">
            <label for="username">ชื่อผู้ใช้งาน</label>
            <input type="text" id="username" name="username" minlength="3" maxlength="50" autocomplete="username" required>
        </div>
        <div class="input-group">
            <label for="password">รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)</label>
            <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required>
        </div>
        <div class="input-group">
            <label for="confirm_password">ยืนยันรหัสผ่าน</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
        </div>
        <button type="submit">สมัครสมาชิก</button>
    </form>
    <a class="link" href="login.php<?php echo $next !== '' ? '?next=' . rawurlencode($next) : ''; ?>">มีบัญชีแล้ว? เข้าสู่ระบบ</a>
    <a class="link" href="index.php">กลับสู่หน้าหลัก</a>
</main>
</body>
</html>
