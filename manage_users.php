<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['full_name'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];
        $temple_id = !empty($_POST['temple_id']) ? $_POST['temple_id'] : null;

        if (empty($username) || empty($full_name) || empty($role)) {
            $error = "กรุณากรอกข้อมูลสำคัญให้ครบถ้วน";
        } else {
            if ($action === 'add') {
                $password = trim($_POST['password']);
                if (empty($password)) {
                    $error = "กรุณากำหนดรหัสผ่านสำหรับผู้ใช้ใหม่";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, temple_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $full_name, $role, $temple_id]);
                    $message = "เพิ่มผู้ใช้งานสำเร็จ";
                }
            } else {
                $user_id = $_POST['user_id'];
                $password = trim($_POST['password']);
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, role = ?, temple_id = ? WHERE id = ?");
                    $stmt->execute([$username, $hashed_password, $full_name, $role, $temple_id, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, temple_id = ? WHERE id = ?");
                    $stmt->execute([$username, $full_name, $role, $temple_id, $user_id]);
                }
                $message = "อัปเดตข้อมูลผู้ใช้งานสำเร็จ";
            }
        }
    } elseif ($action === 'delete') {
        $user_id = $_POST['user_id'];
        if ($user_id == $_SESSION['user_id']) {
            $error = "ไม่สามารถลบบัญชีตนเองได้";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $message = "ลบผู้ใช้งานเรียบร้อยแล้ว";
        }
    }
}

$users = $pdo->query("SELECT u.*, t.name AS temple_name FROM users u LEFT JOIN temples t ON u.temple_id = t.id ORDER BY u.id DESC")->fetchAll();
$temples = $pdo->query("SELECT id, name FROM temples ORDER BY name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้ระบบ - Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-responsive.css">
    <script src="assets/js/admin-responsive.js" defer></script>
    <style>
        :root { --sidebar-bg: #2B2522; --sidebar-hover: #3E352F; --primary-color: #C08221; --bg-color: #FDFBF7; --text-main: #3E352F; --text-muted: #857B74; --card-bg: #FFFFFF; --border-color: #EAE5DE; }
        body { font-family: 'Prompt', sans-serif; margin: 0; padding: 0; display: flex; background-color: var(--bg-color); color: var(--text-main); min-height: 100vh; }
        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: #EAE5DE; display: flex; flex-direction: column; }
        .sidebar-header { padding: 25px 20px; text-align: center; background-color: #1F1A18; font-size: 16px; font-weight: 500; color: var(--primary-color); border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sidebar-menu { list-style: none; padding: 10px 0; margin: 0; flex-grow: 1; }
        .sidebar-menu a { color: #BDB5AF; text-decoration: none; padding: 14px 25px; display: block; transition: 0.2s; font-size: 14px; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--sidebar-hover); color: white; border-left: 3px solid var(--primary-color); }
        .sidebar-menu i { margin-right: 12px; width: 20px; text-align: center; opacity: 0.8;}
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-x: hidden; }
        .topbar { background-color: var(--card-bg); padding: 15px 35px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); font-size: 14px; }
        .logout-btn { color: #C0392B; text-decoration: none; margin-left: 20px; font-weight: 500; border: 1px solid #C0392B; padding: 6px 15px; border-radius: 4px; }
        .logout-btn:hover { background: #C0392B; color: white; }
        .content-container { padding: 35px; max-width: 1200px; margin: 0 auto; width: 100%; box-sizing: border-box;}
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h2 { margin: 0; font-size: 24px; font-weight: 500; }
        .btn-main { background-color: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-family: 'Prompt'; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; text-decoration: none;}
        .panel { background: var(--card-bg); border-radius: 8px; border: 1px solid var(--border-color); padding: 25px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 14px 12px; border-bottom: 2px solid #F0ECE7; color: var(--text-muted); font-weight: 500; font-size: 13px; }
        td { padding: 15px 12px; border-bottom: 1px solid #F0ECE7; font-size: 14px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge-super { background: #E8F4FD; color: #1976D2; }
        .badge-admin { background: #FFF3E0; color: #E65100; }
        .alert-success { background: #E8F8F5; color: #27AE60; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #A3E4D7; }
        .alert-error { background: #FDEDEC; color: #C0392B; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #F5B7B1; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: var(--card-bg); padding: 30px; border-radius: 8px; width: 100%; max-width: 500px; border: 1px solid var(--border-color); box-sizing: border-box; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 500; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-family: 'Prompt'; font-size: 14px; box-sizing: border-box; background: var(--bg-color); }
        .btn-action { padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border-color); background: white; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
        .btn-edit { color: #2980B9; border-color: #AED6F1; }
        .btn-delete { color: #C0392B; border-color: #F5B7B1; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">Super Admin Panel</div>
        <ul class="sidebar-menu">
            <li><a href="superadmin_dashboard.php"><i class="fas fa-tachometer-alt"></i> แดชบอร์ด</a></li>
            <li><a href="manage_temples.php"><i class="fas fa-university"></i> จัดการวัดทั้งหมด</a></li>
            <li><a href="manage_users.php" class="active"><i class="fas fa-users-cog"></i> จัดการผู้ใช้ระบบ</a></li>
            <li><a href="manage_bookings.php"><i class="fas fa-calendar-check"></i> ตรวจสอบการจอง</a></li>
            <li><a href=" edit_profile.php"><i class="fas fa-user-edit"></i> แก้ไขโปรไฟล์ส่วนตัว</a></li>
           
        </ul>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div><i class="fas fa-bars" style="color: var(--text-muted);"></i></div>
            <div>
                <span style="color: var(--text-muted);">ผู้ดูแลระบบสูงสุด:</span> <strong><?php echo htmlspecialchars($admin_name); ?></strong>
                <a href="logout.php" class="logout-btn">ออกจากระบบ</a>
            </div>
        </div>

        <div class="content-container">
            <div class="page-header">
                <h2>จัดการผู้ใช้ระบบ</h2>
                <button class="btn-main" onclick="openAddModal()"><i class="fas fa-user-plus"></i> เพิ่มผู้ใช้งานใหม่</button>
            </div>

            <?php if (!empty($message)): ?><div class="alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert-error"><?php echo $error; ?></div><?php endif; ?>

            <div class="panel">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>ชื่อ - นามสกุล</th>
                            <th>สิทธิ์</th>
                            <th>วัดที่สังกัด</th>
                            <th style="text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $index => $u): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td style="font-weight: 500;"><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                    <td>
                                        <?php if ($u['role'] === 'superadmin'): ?>
                                            <span class="badge badge-super">Super Admin</span>
                                        <?php elseif ($u['role'] === 'user'): ?>
                                            <span class="badge badge-admin">ผู้ใช้งานระบบ</span>
                                        <?php else: ?>
                                            <span class="badge badge-admin">Admin วัด</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['temple_name'] ?? 'ระบบส่วนกลาง'); ?></td>
                                    <td style="text-align: center;">
                                        <button class="btn-action btn-edit" onclick='openEditModal(<?php echo json_encode($u); ?>)'><i class="fas fa-edit"></i> แก้ไข</button>
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                            <form action="manage_users.php" method="POST" style="display:inline-block;" onsubmit="return confirm('ยืนยันการลบผู้ใช้นี้?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="btn-action btn-delete"><i class="fas fa-trash"></i> ลบ</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">ไม่มีข้อมูลผู้ใช้งาน</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="userModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle" style="margin-top:0; border-bottom: 1px solid #EAE5DE; padding-bottom:10px;">เพิ่มผู้ใช้งานใหม่</h3>
            <form action="manage_users.php" method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="user_id" id="userId">
                <div class="form-group">
                    <label>ชื่อผู้ใช้งาน (Username)</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>รหัสผ่าน <span id="passwordHint" style="font-weight: 300; color: var(--text-muted); font-size: 11px;"></span></label>
                    <input type="password" name="password" id="password" class="form-control">
                </div>
                <div class="form-group">
                    <label>ชื่อ - นามสกุล</label>
                    <input type="text" name="full_name" id="fullName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>สิทธิ์การใช้งาน</label>
                    <select name="role" id="role" class="form-control" onchange="toggleTempleSelect()" required>
                        <option value="user">ผู้ใช้งานระบบ</option>
                        <option value="admin">Admin วัด</option>
                        <option value="superadmin">Super Admin</option>
                    </select>
                </div>
                <div class="form-group" id="templeGroup">
                    <label>วัดที่สังกัด</label>
                    <select name="temple_id" id="templeId" class="form-control">
                        <option value="">-- เลือกวัด --</option>
                        <?php foreach ($temples as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn-action" onclick="closeModal()" style="padding: 10px 15px; margin-right: 5px;">ยกเลิก</button>
                    <button type="submit" class="btn-main" style="border:none;">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('userModal');
        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'เพิ่มผู้ใช้งานใหม่';
            document.getElementById('formAction').value = 'add';
            document.getElementById('userId').value = '';
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
            document.getElementById('password').setAttribute('required', 'required');
            document.getElementById('passwordHint').innerText = '';
            document.getElementById('fullName').value = '';
            document.getElementById('role').value = 'admin';
            document.getElementById('templeId').value = '';
            toggleTempleSelect();
            modal.style.display = 'flex';
        }
        function openEditModal(user) {
            document.getElementById('modalTitle').innerText = 'แก้ไขข้อมูลผู้ใช้งาน';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('userId').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('password').value = '';
            document.getElementById('password').removeAttribute('required');
            document.getElementById('passwordHint').innerText = '(เว้นว่างไว้หากไม่เปลี่ยน)';
            document.getElementById('fullName').value = user.full_name;
            document.getElementById('role').value = user.role;
            document.getElementById('templeId').value = user.temple_id || '';
            toggleTempleSelect();
            modal.style.display = 'flex';
        }
        function closeModal() { modal.style.display = 'none'; }
        function toggleTempleSelect() {
            const role = document.getElementById('role').value;
            document.getElementById('templeGroup').style.display = (role === 'superadmin') ? 'none' : 'block';
        }
    </script>
</body>
</html>