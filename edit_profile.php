<?php
session_start();
include 'db_config.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "ไม่พบข้อมูลผู้ใช้งานในระบบ";
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขโปรไฟล์ส่วนตัว - ระบบจัดการวัด</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-responsive.css">
    
    <style>
        :root { 
            --sidebar-bg: #2B2522; 
            --sidebar-hover: #3E352F; 
            --primary-color: #C08221; 
            --bg-color: #FDFBF7; 
            --text-main: #3E352F; 
            --text-muted: #857B74;
            --card-bg: #FFFFFF;
            --border-color: #EAE5DE;
        }
        
        body { 
            font-family: 'Prompt', sans-serif; 
            margin: 0; 
            padding: 0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            background-color: var(--bg-color); 
            color: var(--text-main); 
            min-height: 100vh; 
        }

        .profile-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 4px 20px rgba(62, 53, 47, 0.05);
            box-sizing: border-box;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-header h2 {
            margin: 0;
            color: var(--text-main);
            font-weight: 500;
            font-size: 22px;
        }

        .profile-header p {
            margin: 6px 0 0 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .avatar-container {
            position: relative;
            width: 110px;
            height: 110px;
            margin: 0 auto 25px auto;
        }

        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            box-shadow: 0 4px 12px rgba(192, 130, 33, 0.15);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-family: 'Prompt', sans-serif;
            font-size: 14px;
            background-color: #FAFAFA;
            color: var(--text-main);
            box-sizing: border-box;
            transition: 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(192, 130, 33, 0.1);
        }

        input[type="file"].form-control {
            padding: 9px 12px;
            font-size: 13px;
            cursor: pointer;
        }

        .form-text {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        .btn-container {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn-submit {
            flex: 1;
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-family: 'Prompt', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            text-align: center;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(192, 130, 33, 0.2);
        }

        .btn-submit:hover {
            background-color: #A66E1B;
        }

        .btn-back {
            flex: 1;
            background-color: #FDFBF7;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            padding: 12px;
            border-radius: 6px;
            font-family: 'Prompt', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            text-align: center;
            text-decoration: none;
        }

        .btn-back:hover {
            background-color: #F0ECE7;
            color: var(--text-main);
        }
    </style>
</head>
<body>

    <div class="profile-card">
        <div class="profile-header">
            <h2>แก้ไขโปรไฟล์ส่วนตัว</h2>
            <p>จัดการข้อมูลส่วนตัวและรูปภาพโปรไฟล์ผู้ดูแลระบบ</p>
        </div>

        <form action="update_profile.php" method="POST" enctype="multipart/form-data">
            
            <div class="avatar-container">
                <img src="<?php echo !empty($user['profile_image']) ? 'uploads/' . $user['profile_image'] : 'assets/default-avatar.png'; ?>" 
                     alt="Profile Picture" 
                     class="profile-avatar">
            </div>

            <div class="form-group">
                <label for="full_name" class="form-label">ชื่อ-นามสกุล</label>
                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="profile_image" class="form-label">เปลี่ยนรูปโปรไฟล์</label>
                <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                <div class="form-text">รองรับไฟล์ภาพนามสกุล: JPG, JPEG, PNG, WEBP</div>
            </div>

            <div class="btn-container">
                <button type="submit" class="btn-submit">บันทึกข้อมูล</button>
                
                <?php 
                    // กำหนดหน้าที่จะกลับไปอัตโนมัติตามสิทธิ์ (Role) ของผู้ใช้คนนั้นๆ
                    $back_page = 'login.php';
                    if (isset($_SESSION['role'])) {
                        if ($_SESSION['role'] === 'superadmin') {
                            $back_page = 'superadmin_dashboard.php';
                        } elseif ($_SESSION['role'] === 'admin') {
                            $back_page = 'admin_dashboard.php';
                        }
                    }
                ?>
                <a href="<?php echo $back_page; ?>" class="btn-back">กลับหน้าหลัก</a>
            </div>
        </form>
    </div>

</body>
</html>