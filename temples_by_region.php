<?php
require_once 'db_config.php';

$regions = [
    'north' => [
        'name' => 'ภาคเหนือ',
        'provinces' => ['เชียงราย', 'เชียงใหม่', 'น่าน', 'พะเยา', 'แพร่', 'แม่ฮ่องสอน', 'ลำปาง', 'ลำพูน', 'อุตรดิตถ์']
    ],
    'northeast' => [
        'name' => 'ภาคตะวันออกเฉียงเหนือ',
        'provinces' => ['กาฬสินธุ์', 'ขอนแก่น', 'ชัยภูมิ', 'นครพนม', 'นครราชสีมา', 'บึงกาฬ', 'บุรีรัมย์', 'มหาสารคาม', 'มุกดาหาร', 'ยโสธร', 'ร้อยเอ็ด', 'เลย', 'ศรีสะเกษ', 'สกลนคร', 'สุรินทร์', 'หนองคาย', 'หนองบัวลำภู', 'อุดรธานี', 'อุบลราชธานี', 'อำนาจเจริญ']
    ],
    'central' => [
        'name' => 'ภาคกลาง',
        'provinces' => ['กรุงเทพมหานคร', 'กำแพงเพชร', 'ชัยนาท', 'นครนายก', 'นครปฐม', 'นครสวรรค์', 'นนทบุรี', 'ปทุมธานี', 'พิจิตร', 'พิษณุโลก', 'เพชรบูรณ์', 'พระนครศรีอยุธยา', 'ลพบุรี', 'สมุทรปราการ', 'สมุทรสงคราม', 'สมุทรสาคร', 'สิงห์บุรี', 'สุโขทัย', 'สุพรรณบุรี', 'สระบุรี', 'อ่างทอง', 'อุทัยธานี']
    ],
    'east' => [
        'name' => 'ภาคตะวันออก',
        'provinces' => ['ฉะเชิงเทรา', 'จันทบุรี', 'ชลบุรี', 'ตราด', 'ปราจีนบุรี', 'ระยอง', 'สระแก้ว']
    ],
    'west' => [
        'name' => 'ภาคตะวันตก',
        'provinces' => ['กาญจนบุรี', 'ตาก', 'ประจวบคีรีขันธ์', 'เพชรบุรี', 'ราชบุรี']
    ],
    'south' => [
        'name' => 'ภาคใต้',
        'provinces' => ['กระบี่', 'ชุมพร', 'ตรัง', 'นครศรีธรรมราช', 'นราธิวาส', 'ปัตตานี', 'พังงา', 'พัทลุง', 'ภูเก็ต', 'ยะลา', 'ระนอง', 'สงขลา', 'สตูล', 'สุราษฎร์ธานี']
    ]
];

$region_key = isset($_GET['region']) && is_string($_GET['region']) ? $_GET['region'] : '';
if (!isset($regions[$region_key])) {
    http_response_code(404);
    exit('ไม่พบภูมิภาคที่เลือก');
}

$region = $regions[$region_key];
$temples = [];
try {
    $placeholders = implode(',', array_fill(0, count($region['provinces']), '?'));
    $stmt = $pdo->prepare("SELECT id, name, province, district, image FROM temples WHERE province IN ($placeholders) ORDER BY name");
    $stmt->execute($region['provinces']);
    $temples = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    exit('ไม่สามารถโหลดข้อมูลวัดได้ในขณะนี้');
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($region['name'], ENT_QUOTES, 'UTF-8'); ?> | ThumWadThaiHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>body { font-family: 'Sarabun', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
  <main class="mx-auto max-w-6xl px-4 sm:px-6 py-6 sm:py-10">
    <a href="index.php" class="mb-8 inline-flex items-center gap-2 text-sm font-medium text-amber-700 hover:text-amber-800">
      <span aria-hidden="true">&larr;</span> กลับหน้าหลัก
    </a>
    <header class="mb-8 border-b border-slate-200 pb-5">
      <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($region['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="mt-2 text-sm text-slate-600">วัดใน<?php echo htmlspecialchars($region['name'], ENT_QUOTES, 'UTF-8'); ?></p>
    </header>

    <?php if (!$temples): ?>
      <p class="py-16 text-center text-slate-500">ยังไม่มีข้อมูลวัดในภูมิภาคนี้</p>
    <?php else: ?>
      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($temples as $temple): ?>
          <?php $image = !empty($temple['image']) ? $temple['image'] : 'assets/images/download.jpg'; ?>
          <a href="temple_page.php?id=<?php echo (int)$temple['id']; ?>" class="overflow-hidden rounded-lg border border-slate-200 bg-white transition hover:-translate-y-1 hover:shadow-lg">
            <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($temple['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="h-52 w-full object-cover" onerror="this.onerror=null; this.src='assets/images/download.jpg';">
            <div class="p-4">
              <h2 class="font-semibold"><?php echo htmlspecialchars($temple['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
              <p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars($temple['province'] ?? '', ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($temple['district']) ? ' · ' . htmlspecialchars($temple['district'], ENT_QUOTES, 'UTF-8') : ''; ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
