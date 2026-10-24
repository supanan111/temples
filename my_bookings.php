<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
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
    $stmt->execute([$_SESSION['user_id']]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Unable to load member bookings: " . $e->getMessage());
    http_response_code(500);
    die("ไม่สามารถโหลดประวัติการจองได้ กรุณาตรวจสอบการตั้งค่าฐานข้อมูล");
}

$status_labels = [
    'pending' => 'รอตรวจสอบ',
    'confirmed' => 'ยืนยันแล้ว',
    'success' => 'ยืนยันแล้ว',
    'approved' => 'ยืนยันแล้ว',
    'rejected' => 'ไม่อนุมัติ',
    'cancelled' => 'ยกเลิก',
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
        <div class="flex items-center gap-3 text-xs font-bold sm:gap-5">
            <span class="hidden normal-case tracking-normal text-gray-600 sm:inline">
                สวัสดี, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <a href="index.php" class="whitespace-nowrap rounded-xl px-3 py-2 text-amber-800 transition hover:bg-amber-50">
                <i class="fa-solid fa-house mr-1"></i> หน้าหลัก
            </a>
            <a href="logout.php" class="whitespace-nowrap rounded-xl border border-red-200 px-3 py-2 text-red-600 transition hover:bg-red-50">
                <i class="fa-solid fa-right-from-bracket mr-1"></i> ออกจากระบบ
            </a>
        </div>
    </div>
</header>

<main class="mx-auto max-w-6xl px-5 py-10 md:py-14">
    <section class="mb-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[.2em] text-amber-700">
                <span class="h-0.5 w-6 bg-amber-600"></span> บัญชีของฉัน
            </div>
            <h1 class="text-3xl font-bold text-amber-950 md:text-4xl" style="font-family:'Fraunces',serif;">ประวัติการจองของฉัน</h1>
            <p class="mt-2 text-sm text-gray-600">ติดตามรายการจองและสถานะการดำเนินการของคุณ</p>
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
                <table class="w-full min-w-[700px] border-collapse text-left">
                    <thead class="bg-amber-50/80 text-xs uppercase tracking-wider text-amber-900">
                        <tr>
                            <th class="px-6 py-4 font-bold">วัด</th>
                            <th class="px-6 py-4 font-bold">บริการ</th>
                            <th class="px-6 py-4 font-bold">วันที่ / รายละเอียด</th>
                            <th class="px-6 py-4 font-bold">ติดต่อ</th>
                            <th class="px-6 py-4 font-bold">สถานะ</th>
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
                        ?>
                        <tr class="transition hover:bg-amber-50/40">
                            <td class="px-6 py-4 font-semibold text-gray-800"><?php echo htmlspecialchars($booking['temple_name'] ?? 'ไม่ระบุวัด', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($booking['item_name'] ?? 'บริการ', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars(!empty($booking['stall_no']) ? 'ล็อค ' . $booking['stall_no'] : ($booking['booking_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($booking['contact_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold <?php echo $status_style; ?>">
                                    <?php echo htmlspecialchars($status_labels[$status] ?? 'ไม่ทราบสถานะ', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-6 py-4 text-xs text-gray-500">
                <i class="fa-solid fa-circle-info mr-1 text-amber-600"></i> เลื่อนตารางในแนวนอนเพื่อดูรายละเอียดเพิ่มเติม
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
