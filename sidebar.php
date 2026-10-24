<?php
// เช็กสิทธิ์ Super Admin จาก Session (เพื่อป้องกัน Error หากไม่ได้ประกาศไว้ในหน้าหลัก)
$is_superadmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin');
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-header">
        <?php echo htmlspecialchars($temple_name ?? 'ระบบจัดการวัด'); ?>
    </div>

    <ul class="sidebar-menu">
        <li>
            <a href="admin_dashboard.php" class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> แดชบอร์ด
            </a>
        </li>
        
        <?php if ($is_superadmin): ?>
            <li>
                <a href="manage_temples.php" class="<?php echo ($current_page == 'manage_temples.php') ? 'active' : ''; ?>">
                    <i class="fas fa-university"></i> จัดการวัดทั้งหมด
                </a>
            </li>
            <li>
                <a href="manage_users.php" class="<?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i> จัดการผู้ใช้งานระบบ
                </a>
            </li>
        <?php endif; ?>

        <li>
            <a href="manage_bookings.php" class="<?php echo ($current_page == 'manage_bookings.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> ตรวจสอบการจองศาลา
            </a>
        </li>
        <li>
            <a href="edit_temple.php" class="<?php echo ($current_page == 'edit_temple.php') ? 'active' : ''; ?>">
                <i class="fas fa-pen-to-square"></i> แก้ไขหน้าเว็บไซต์วัด
            </a>
        </li>
        <li>
            <a href="manage_items.php" class="<?php echo ($current_page == 'manage_items.php') ? 'active' : ''; ?>">
                <i class="fas fa-box-open"></i> จัดการบริการ/สถานที่
            </a>
        </li>
        <li>
            <a href="manage_events.php" class="<?php echo ($current_page == 'manage_events.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> จัดการปฏิทินกิจกรรม
            </a>
        </li>
        <li>
            <a href="manage_donations.php" class="<?php echo ($current_page == 'manage_donations.php') ? 'active' : ''; ?>">
                <i class="fas fa-donate"></i> ข้อมูล E-Donation
            </a>
        </li>
        <li>
            <a href="add_news.php" class="<?php echo ($current_page == 'add_news.php') ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i> จัดการข่าวสาร/ประชาสัมพันธ์
            </a>
        </li>
        <li>
            <a href="manage_temple.php" class="<?php echo ($current_page == 'manage_temple.php') ? 'active' : ''; ?>">
                <i class="fas fa-vihara"></i> จัดการข้อมูลวัด
            </a>
        </li>
    </ul>
</div>