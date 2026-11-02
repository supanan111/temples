<?php
session_start();
require_once 'db_config.php';
if (file_exists('notify.php')) require_once 'notify.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// CSRF token สำหรับยกเลิกการจอง
if (empty($_SESSION['cancel_booking_csrf'])) {
    $_SESSION['cancel_booking_csrf'] = bin2hex(random_bytes(16));
}
$cancel_csrf = $_SESSION['cancel_booking_csrf'];

// ดึงการแจ้งเตือนของผู้ใช้
$user_notifications = [];
$unread_notif_count = 0;
try {
    $stmt_notif = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 5");
    $stmt_notif->execute([$user_id]);
    $user_notifications = $stmt_notif->fetchAll();
    
    $stmt_unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt_unread->execute([$user_id]);
    $unread_notif_count = (int)$stmt_unread->fetchColumn();
} catch (PDOException $e) {
    // ถ้าตาราง notifications ยังไม่มี
    $user_notifications = [];
}

try {
    $stmt = $pdo->prepare("
        SELECT b.*, t.name AS temple_name, i.item_name
        FROM bookings b
        LEFT JOIN temples t ON t.id = b.temple_id
        LEFT JOIN bookable_items i ON i.id = b.item_id
        WHERE b.user_id = ?
        ORDER BY b.id DESC
    ");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Unable to load member bookings: " . $e->getMessage());
    http_response_code(500);
    die("ไม่สามารถโหลดประวัติการจองได้ กรุณาตรวจสอบการตั้งค่าฐานข้อมูล");
}

$status_labels = [
    'pending' => 'รอตรวจสอบ',
    'confirmed' => 'อนุมัติแล้ว',
    'success' => 'อนุมัติแล้ว',
    'approved' => 'อนุมัติแล้ว',
    'rejected' => 'ไม่อนุมัติ',
    'cancelled' => 'ยกเลิกแล้ว',
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการจองของฉัน - ThumWadThaiHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;0,9..144,900&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .page-background {
            background-color: #f8fafc;
            background-image: linear-gradient(rgba(255,255,255,.86), rgba(255,255,255,.86)), url('assets/images/bg2.jpg');
            background-position: center;
            background-size: cover;
            background-attachment: fixed;
        }
    </style>
</head>
<body class="page-background min-h-screen text-gray-800">
<header class="sticky top-0 z-20 border-b border-gray-100 bg-white/95 shadow-sm backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4">
        <a href="index.php" class="flex items-center gap-2 font-bold text-gray-900">
            <i class="fa-solid fa-dharmachakra text-xl text-amber-600"></i>
            <span class="text-xs font-extrabold uppercase tracking-wider text-amber-950">ThumWadThaiHub</span>
        </a>
        <div class="flex items-center gap-3 text-xs font-bold sm:gap-4">
            <span class="hidden normal-case tracking-normal text-gray-600 sm:inline">
                สวัสดี, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <a href="check_status.php" class="whitespace-nowrap rounded-xl border border-amber-300 px-3 py-2 text-amber-800 transition hover:bg-amber-50">
                <i class="fa-solid fa-magnifying-glass mr-1"></i> ตรวจสอบสถานะ
            </a>
            <a href="index.php" class="whitespace-nowrap rounded-xl px-3 py-2 text-amber-800 transition hover:bg-amber-50">
                <i class="fa-solid fa-house mr-1"></i> หน้าหลัก
            </a>
            <?php include 'user_notification_bell.php'; ?>
            <a href="logout.php" class="whitespace-nowrap rounded-xl border border-red-200 px-3 py-2 text-red-600 transition hover:bg-red-50">
                <i class="fa-solid fa-right-from-bracket mr-1"></i> ออกจากระบบ
            </a>
        </div>
    </div>
</header>

<main class="mx-auto max-w-6xl px-5 py-10 md:py-14">
    <!-- แจ้งเตือนข้อความสถานะการยกเลิก -->
    <?php if (isset($_GET['cancelled'])): ?>
        <div class="mb-6 flex items-center gap-3 rounded-2xl bg-emerald-50 border border-emerald-200 px-5 py-4 text-emerald-800">
            <i class="fa-solid fa-circle-check text-xl text-emerald-600"></i>
            <div>
                <strong>ยกเลิกคำขอจองเรียบร้อยแล้ว</strong>
                <p class="text-xs text-emerald-700">คำขอของคุณได้ถูกยกเลิกแล้ว</p>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="mb-6 flex items-center gap-3 rounded-2xl bg-red-50 border border-red-200 px-5 py-4 text-red-800">
            <i class="fa-solid fa-triangle-exclamation text-xl text-red-600"></i>
            <div>
                <strong>ไม่สามารถดำเนินการได้</strong>
                <p class="text-xs text-red-700"><?php echo htmlspecialchars($_GET['error']); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- กล่องแจ้งเตือนอัปเดตสถานะสำหรับ User (Feature 3) -->
    <?php if (!empty($user_notifications)): ?>
        <div class="mb-8 rounded-3xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h3 class="flex items-center gap-2 text-sm font-bold text-amber-950">
                    <i class="fa-solid fa-bell text-amber-600"></i> การแจ้งเตือนล่าสุดของคุณ
                    <?php if ($unread_notif_count > 0): ?>
                        <span class="rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white"><?php echo $unread_notif_count; ?> ใหม่</span>
                    <?php endif; ?>
                </h3>
                <?php if ($unread_notif_count > 0): ?>
                    <button onclick="markAllUserRead()" class="text-xs font-semibold text-amber-800 hover:text-amber-950 underline">อ่านทั้งหมด</button>
                <?php endif; ?>
            </div>
            <div class="space-y-2">
                <?php foreach ($user_notifications as $notif): ?>
                    <div class="flex items-center justify-between rounded-xl bg-white/80 px-4 py-2.5 text-xs text-gray-800 <?php echo empty($notif['is_read']) ? 'border-l-4 border-amber-500 font-semibold' : 'opacity-80'; ?>">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-dot text-[8px] <?php echo empty($notif['is_read']) ? 'text-amber-600' : 'text-gray-300'; ?>"></i>
                            <span><?php echo htmlspecialchars($notif['message']); ?></span>
                        </div>
                        <span class="text-[11px] text-gray-500 whitespace-nowrap ml-3"><?php echo htmlspecialchars($notif['created_at']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <section class="mb-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[.2em] text-amber-700">
                <span class="h-0.5 w-6 bg-amber-600"></span> บัญชีของฉัน
            </div>
            <h1 class="text-3xl font-bold text-amber-950 md:text-4xl" style="font-family:'Fraunces',serif;">ประวัติการจองของฉัน</h1>
            <p class="mt-2 text-sm text-gray-600">ติดตามรายการจอง ตรวจสอบผลการอนุมัติ และจัดการคำขอจองของคุณ</p>
        </div>
        <div class="inline-flex w-fit items-center gap-3 rounded-2xl border border-amber-100 bg-white px-5 py-3 shadow-sm">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                <i class="fa-solid fa-calendar-check"></i>
            </span>
            <span>
                <span class="block text-xs text-gray-500">รายการจองทั้งหมด</span>
                <span class="text-lg font-bold text-amber-950"><?php echo count($bookings); ?> รายการ</span>
            </span>
        </div>
    </section>

    <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-lg shadow-amber-950/5">
        <?php if (!$bookings): ?>
            <div class="px-6 py-16 text-center">
                <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50 text-2xl text-amber-600">
                    <i class="fa-regular fa-calendar-xmark"></i>
                </span>
                <h2 class="mt-5 text-xl font-bold text-gray-800">ยังไม่มีรายการจอง</h2>
                <p class="mt-2 text-sm text-gray-500">เริ่มค้นหาวัดและบริการที่คุณสนใจได้เลย</p>
                <a href="index.php" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 px-5 py-3 text-sm font-bold text-white shadow-md transition hover:from-amber-600 hover:to-orange-700">
                    <i class="fa-solid fa-magnifying-glass"></i> ค้นหาวัดเพื่อเริ่มจอง
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[750px] border-collapse text-left">
                    <thead class="bg-amber-50/80 text-xs uppercase tracking-wider text-amber-900">
                        <tr>
                            <th class="px-6 py-4 font-bold">รหัส</th>
                            <th class="px-6 py-4 font-bold">วัด</th>
                            <th class="px-6 py-4 font-bold">บริการ</th>
                            <th class="px-6 py-4 font-bold">วันที่ / รายละเอียด</th>
                            <th class="px-6 py-4 font-bold">สถานะ</th>
                            <th class="px-6 py-4 font-bold text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                    <?php foreach ($bookings as $booking): ?>
                        <?php
                            $status = trim((string)($booking['status'] ?? '')) ?: 'pending';
                            $status_style = [
                                'pending' => 'bg-amber-100 text-amber-800',
                                'confirmed' => 'bg-emerald-100 text-emerald-800',
                                'success' => 'bg-emerald-100 text-emerald-800',
                                'approved' => 'bg-emerald-100 text-emerald-800',
                                'rejected' => 'bg-red-100 text-red-700',
                                'cancelled' => 'bg-gray-100 text-gray-600',
                            ][$status] ?? 'bg-gray-100 text-gray-700';
                            $is_pending = ($status === 'pending');
                        ?>
                        <tr class="transition hover:bg-amber-50/40">
                            <td class="px-6 py-4 font-semibold text-gray-500">#<?php echo $booking['id']; ?></td>
                            <td class="px-6 py-4 font-semibold text-gray-800"><?php echo htmlspecialchars($booking['temple_name'] ?? 'ไม่ระบุวัด', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($booking['item_name'] ?? 'บริการ', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-6 py-4 text-gray-600">
                                <div><?php echo htmlspecialchars(!empty($booking['stall_no']) ? 'ล็อค ' . $booking['stall_no'] : ($booking['booking_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php if (!empty($booking['admin_note'])): ?>
                                    <div class="mt-1 text-xs text-orange-800 bg-orange-50 rounded px-2 py-0.5 inline-block">
                                        <i class="fa-solid fa-comment-dots mr-1"></i> หมายเหตุ: <?php echo htmlspecialchars($booking['admin_note']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold <?php echo $status_style; ?>">
                                    <?php echo htmlspecialchars($status_labels[$status] ?? 'ไม่ทราบสถานะ', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($is_pending): ?>
                                    <a href="cancel_booking.php?id=<?php echo $booking['id']; ?>&csrf=<?php echo htmlspecialchars($cancel_csrf); ?>"
                                       class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50 transition"
                                       onclick="return confirm('คุณต้องการยกเลิกคำขอจอง #<?php echo $booking['id']; ?> ใช่หรือไม่?');">
                                        <i class="fa-solid fa-ban"></i> ยกเลิก
                                    </a>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-6 py-4 text-xs text-gray-500">
                <i class="fa-solid fa-circle-info mr-1 text-amber-600"></i> คุณสามารถยกเลิกคำขอจองได้เฉพาะรายการที่อยู่ในสถานะ “รอตรวจสอบ” เท่านั้น
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
function markAllUserRead() {
    fetch('get_notifications.php?action=mark_all_read')
        .then(() => location.reload())
        .catch(() => {});
}
</script>
</body>
</html>
