<?php
session_start();
require_once 'db_config.php';

// บังคับล็อกอิน: หน้าเฉพาะสมาชิกเท่านั้น
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'my_bookings.php';
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// CSRF สำหรับการยกเลิกการจอง
if (empty($_SESSION['cancel_csrf_token'])) {
    $_SESSION['cancel_csrf_token'] = bin2hex(random_bytes(16));
}
$cancel_csrf = $_SESSION['cancel_csrf_token'];

// ดึงการแจ้งเตือนของ user
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
    $user_notifications = [];
}

try {
    $stmt = $pdo->prepare("
        SELECT b.*, t.name AS temple_name, t.bank_name, t.bank_account_no, t.bank_account_name, t.promptpay_id, t.qr_code_image, i.item_name
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
            background-image:
                radial-gradient(circle at 10% 20%, rgba(245, 158, 11, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(180, 83, 9, 0.05) 0%, transparent 20%);
        }
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.65);
            display: none; align-items: center; justify-content: center; z-index: 9999; padding: 16px;
        }
        .modal-overlay.active { display: flex; }
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
    <!-- แจ้งเตือนเมื่ออัปโหลดสลิปสำเร็จ -->
    <?php if (isset($_GET['slip_success'])): ?>
        <div class="mb-6 flex items-center gap-3 rounded-2xl bg-emerald-50 border border-emerald-200 px-5 py-4 text-emerald-800">
            <i class="fa-solid fa-circle-check text-2xl text-emerald-600"></i>
            <div>
                <strong class="text-sm">แนบสลิปชำระเงินเรียบร้อยแล้ว!</strong>
                <p class="text-xs text-emerald-700">เจ้าหน้าที่วัดจะทำการตรวจสอบและอนุมัติคำขอจองของท่านโดยเร็ว</p>
            </div>
        </div>
    <?php endif; ?>

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

    <section class="mb-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[.2em] text-amber-700">
                <span class="h-0.5 w-6 bg-amber-600"></span> บัญชีของฉัน
            </div>
            <h1 class="text-3xl font-bold text-amber-950 md:text-4xl" style="font-family:'Fraunces',serif;">ประวัติการจองและชำระเงิน</h1>
            <p class="mt-2 text-sm text-gray-600">ติดตามรายการจอง โอนเงินแนบสลิป และตรวจสอบผลการอนุมัติได้ที่นี่</p>
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
                <h3 class="mt-4 text-lg font-bold text-gray-900">ยังไม่มีรายการจอง</h3>
                <p class="mt-1 text-sm text-gray-500">ท่านยังไม่ได้ทำการจองศาลา ที่พัก หรือบริการใดๆ จากวัด</p>
                <a href="index.php" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 text-xs font-bold text-white transition hover:bg-amber-700 shadow-md">
                    <i class="fa-solid fa-magnifying-glass"></i> ค้นหาวัดและบริการ
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] border-collapse text-left">
                    <thead class="bg-amber-50/80 text-xs uppercase tracking-wider text-amber-900">
                        <tr>
                            <th class="px-6 py-4 font-bold">รหัส</th>
                            <th class="px-6 py-4 font-bold">วัด</th>
                            <th class="px-6 py-4 font-bold">บริการ</th>
                            <th class="px-6 py-4 font-bold">วันที่ / รายละเอียด</th>
                            <th class="px-6 py-4 font-bold">ยอดเงิน & การชำระ</th>
                            <th class="px-6 py-4 font-bold">สถานะการจอง</th>
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

                            $b_price = (float)($booking['total_price'] ?? 0);
                            $pay_status = $booking['payment_status'] ?? 'unpaid';
                            $has_slip = !empty($booking['slip_image']);
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

                            <!-- คอลัมน์การชำระเงินและสลิป -->
                            <td class="px-6 py-4">
                                <?php if ($b_price <= 0): ?>
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">ฟรี (ไม่ต้องชำระ)</span>
                                <?php else: ?>
                                    <div class="font-bold text-amber-900">฿<?php echo number_format($b_price, 2); ?></div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <?php if ($pay_status === 'paid'): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                                                <i class="fa-solid fa-circle-check"></i> ชำระแล้ว
                                            </span>
                                            <?php if ($has_slip): ?>
                                                <a href="view_booking_slip.php?id=<?php echo $booking['id']; ?>" target="_blank" class="text-xs text-amber-700 hover:underline">
                                                    (ดูสลิป)
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif ($pay_status === 'pending_review'): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800">
                                                <i class="fa-solid fa-clock"></i> รอตรวจสลิป
                                            </span>
                                            <?php if ($has_slip): ?>
                                                <a href="view_booking_slip.php?id=<?php echo $booking['id']; ?>" target="_blank" class="text-xs text-amber-700 hover:underline">
                                                    (ดูสลิปที่แนบ)
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif ($pay_status === 'invalid_slip'): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-bold text-red-800">
                                                <i class="fa-solid fa-triangle-exclamation"></i> สลิปไม่ถูกต้อง
                                            </span>
                                            <button type="button" onclick="openPaymentModal(<?php echo htmlspecialchars(json_encode([
                                                'id' => $booking['id'],
                                                'temple_name' => $booking['temple_name'] ?? 'วัด',
                                                'item_name' => $booking['item_name'] ?? 'บริการ',
                                                'price' => number_format($b_price, 2),
                                                'bank_name' => $booking['bank_name'] ?? '',
                                                'bank_account_no' => $booking['bank_account_no'] ?? '',
                                                'bank_account_name' => $booking['bank_account_name'] ?? '',
                                                'promptpay_id' => $booking['promptpay_id'] ?? '',
                                                'qr_code_image' => $booking['qr_code_image'] ?? ''
                                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)" class="inline-flex items-center gap-1 text-xs font-bold text-red-600 hover:underline">
                                                <i class="fa-solid fa-cloud-arrow-up"></i> แนบสลิปใหม่
                                            </button>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-bold text-orange-800">
                                                <i class="fa-solid fa-circle-exclamation"></i> ยังไม่ชำระ
                                            </span>
                                            <button type="button" onclick="openPaymentModal(<?php echo htmlspecialchars(json_encode([
                                                'id' => $booking['id'],
                                                'temple_name' => $booking['temple_name'] ?? 'วัด',
                                                'item_name' => $booking['item_name'] ?? 'บริการ',
                                                'price' => number_format($b_price, 2),
                                                'bank_name' => $booking['bank_name'] ?? '',
                                                'bank_account_no' => $booking['bank_account_no'] ?? '',
                                                'bank_account_name' => $booking['bank_account_name'] ?? '',
                                                'promptpay_id' => $booking['promptpay_id'] ?? '',
                                                'qr_code_image' => $booking['qr_code_image'] ?? ''
                                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)" class="inline-flex items-center gap-1 rounded-lg bg-amber-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-amber-700 transition">
                                                <i class="fa-solid fa-credit-card mr-0.5"></i> แนบสลิป
                                            </button>
                                        <?php endif; ?>
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

<!-- Modal: ชำระเงิน & แนบสลิปการโอนเงิน -->
<div class="modal-overlay" id="paymentModal" onclick="closePaymentModal(event)">
    <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 md:p-8 shadow-2xl" onclick="event.stopPropagation()">
        <button class="absolute top-5 right-5 text-gray-400 hover:text-gray-600 text-lg" onclick="closePaymentModal()">&times;</button>
        
        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-amber-700 mb-1">
            <span class="h-0.5 w-5 bg-amber-600"></span> ชำระเงิน & แนบสลิป
        </div>
        <h2 id="modalTempleName" class="text-xl font-bold text-gray-900 mb-1">วัด</h2>
        <p id="modalItemDetails" class="text-xs text-gray-500 mb-4">บริการ</p>

        <!-- ข้อมูลบัญชีธนาคาร -->
        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 mb-5 text-xs text-gray-700 space-y-1.5">
            <div class="flex justify-between items-center text-sm font-bold text-amber-950 pb-2 border-b border-amber-200/60">
                <span>ยอดที่ต้องชำระ:</span>
                <span id="modalPrice" class="text-base text-amber-700">฿0.00 บาท</span>
            </div>
            <div id="modalBankRow" style="display:none;">
                <span class="text-gray-500">ธนาคาร:</span> <strong id="modalBankName" class="text-gray-900"></strong>
            </div>
            <div id="modalAccountRow" style="display:none;">
                <span class="text-gray-500">เลขที่บัญชี:</span> <strong id="modalAccountNo" class="text-gray-900 font-mono text-sm"></strong>
            </div>
            <div id="modalAccountNameRow" style="display:none;">
                <span class="text-gray-500">ชื่อบัญชี:</span> <strong id="modalAccountName" class="text-gray-900"></strong>
            </div>
            <div id="modalPromptpayRow" style="display:none;">
                <span class="text-gray-500">พร้อมเพย์:</span> <strong id="modalPromptpay" class="text-gray-900 font-mono"></strong>
            </div>
            <div id="modalQrRow" style="display:none; text-align:center; padding-top:6px;">
                <img id="modalQrImg" src="" alt="QR Code ของวัด" class="max-w-[140px] max-h-[140px] mx-auto rounded-lg border border-gray-200 bg-white p-1">
                <span class="text-[11px] text-gray-500 mt-1 block">สแกน QR Code เพื่อชำระเงิน</span>
            </div>
        </div>

        <!-- ฟอร์มส่งสลิป -->
        <form action="upload_booking_slip.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="booking_id" id="modalBookingId" value="">
            <input type="hidden" name="redirect_to" value="my_bookings.php">
            
            <label class="block text-xs font-bold text-gray-700 mb-1">
                <i class="fa-solid fa-receipt mr-1 text-amber-600"></i> เลือกไฟล์สลิปหลักฐานการโอนเงิน <span class="text-red-500">*</span>
            </label>
            <input type="file" name="slip_image" accept="image/jpeg,image/png,image/webp" required
                   class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200 cursor-pointer border border-gray-200 rounded-xl p-1 mb-4">

            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-xl bg-amber-600 py-2.5 text-xs font-bold text-white hover:bg-amber-700 transition">
                    <i class="fa-solid fa-cloud-arrow-up mr-1"></i> อัปโหลดสลิป
                </button>
                <button type="button" onclick="closePaymentModal()" class="rounded-xl border border-gray-200 px-4 py-2.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                    ยกเลิก
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaymentModal(data) {
    document.getElementById('modalBookingId').value = data.id;
    document.getElementById('modalTempleName').textContent = data.temple_name;
    document.getElementById('modalItemDetails').textContent = 'คำขอจอง #' + data.id + ' • ' + data.item_name;
    document.getElementById('modalPrice').textContent = '฿' + data.price + ' บาท';

    // ธนาคาร
    const bankRow = document.getElementById('modalBankRow');
    if (data.bank_name) {
        document.getElementById('modalBankName').textContent = data.bank_name;
        bankRow.style.display = 'block';
    } else { bankRow.style.display = 'none'; }

    // เลขบัญชี
    const accRow = document.getElementById('modalAccountRow');
    if (data.bank_account_no) {
        document.getElementById('modalAccountNo').textContent = data.bank_account_no;
        accRow.style.display = 'block';
    } else { accRow.style.display = 'none'; }

    // ชื่อบัญชี
    const nameRow = document.getElementById('modalAccountNameRow');
    if (data.bank_account_name) {
        document.getElementById('modalAccountName').textContent = data.bank_account_name;
        nameRow.style.display = 'block';
    } else { nameRow.style.display = 'none'; }

    // พร้อมเพย์
    const ppRow = document.getElementById('modalPromptpayRow');
    if (data.promptpay_id) {
        document.getElementById('modalPromptpay').textContent = data.promptpay_id;
        ppRow.style.display = 'block';
    } else { ppRow.style.display = 'none'; }

    // QR Code
    const qrRow = document.getElementById('modalQrRow');
    if (data.qr_code_image) {
        document.getElementById('modalQrImg').src = data.qr_code_image;
        qrRow.style.display = 'block';
    } else { qrRow.style.display = 'none'; }

    document.getElementById('paymentModal').classList.add('active');
}

function closePaymentModal(e) {
    if (!e || e.target.id === 'paymentModal' || e.target.tagName === 'BUTTON') {
        document.getElementById('paymentModal').classList.remove('active');
    }
}

function markAllUserRead() {
    fetch('get_notifications.php?action=mark_all_read')
        .then(() => location.reload())
        .catch(() => {});
}
</script>
</body>
</html>
