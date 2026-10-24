<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบจัดการวัด</title>
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
        .login-wrapper {
            position: relative;
            z-index: 1;
            background-color: #fffaf1;
            padding: 34px 48px 38px;
            border-radius: 34px;
            width: min(100%, 480px);
            box-sizing: border-box;
            margin-right: 0;
            transform: translateX(240px);
            box-shadow: 0 18px 48px rgba(83, 61, 43, 0.2), 0 3px 12px rgba(83, 61, 43, 0.08);
            animation: card-popup 700ms cubic-bezier(0.2, 0.75, 0.25, 1) 120ms both;
        }
        .login-wrapper h2 {
            color: #826b5c;
            text-align: center;
            margin: 0 0 6px;
            font-size: 30px;
            font-weight: 500;
        }
        .login-subtitle {
            color: #8b796b;
            text-align: center;
            margin: 0 0 30px;
            font-size: 13px;
        }
        .login-wrapper .error-message {
            color: #c0392b;
            text-align: center;
            margin: -14px 0 18px;
            font-size: 13px;
        }
        .input-group {
            margin-bottom: 18px;
        }
        .input-group label {
            display: block;
            margin: 0 8px 6px;
            color: #826b5c;
            font-weight: 500;
            font-size: 18px;
        }
        .input-group input {
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
        .input-group input::placeholder {
            color: #b9ab91;
        }
        .input-group input:focus {
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
            margin-top: 14px;
        }
        button:hover {
            background: #715b4d;
            transform: translateY(-2px);
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: #826b5c;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s;
        }
        .back-link:hover {
            color: #59483d;
        }
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
            .login-wrapper {
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
            .login-wrapper {
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
            .login-wrapper {
                padding: 30px 22px 28px;
                border-radius: 26px;
                margin-right: 0;
                transform: none;
            }
            .login-wrapper h2 {
                font-size: 26px;
            }
            .login-subtitle {
                margin-bottom: 26px;
                font-size: 12px;
            }
            .input-group label {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>

<img class="temple-art" src="assets/images/bgmid.png" alt="" aria-hidden="true">
<div class="login-wrapper">
    <h2>เข้าสู่ระบบ</h2>
    <p class="login-subtitle">เข้าสู่ระบบเพื่อจองบริการและติดตามรายการจอง</p>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
        <p class="error-message">ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง</p>
    <?php endif; ?>

    <form action="login_process.php" method="POST">
        <input type="hidden" name="next" value="<?php echo htmlspecialchars($_GET['next'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <div class="input-group">
            <label for="username">ชื่อผู้ใช้งาน</label>
            <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้งาน" required>
        </div>
        
        <div class="input-group">
            <label for="password">รหัสผ่าน</label>
            <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
        </div>
        
        <button type="submit">เข้าสู่ระบบ</button>
    </form>
    
    <a href="register.php<?php echo !empty($_GET['next']) ? '?next=' . rawurlencode($_GET['next']) : ''; ?>" class="back-link">ยังไม่มีบัญชีใช่ไหม? สมัครสมาชิก</a>
    <a href="index.php" class="back-link">← กลับสู่หน้าหลัก</a>
</div>

</body>
</html>