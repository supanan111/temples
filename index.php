<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';

$user_lat = isset($_GET['lat']) && is_string($_GET['lat']) ? filter_var($_GET['lat'], FILTER_VALIDATE_FLOAT) : false;
$user_lng = isset($_GET['lng']) && is_string($_GET['lng']) ? filter_var($_GET['lng'], FILTER_VALIDATE_FLOAT) : false;
$has_user_location = $user_lat !== false && $user_lng !== false
    && $user_lat >= -90 && $user_lat <= 90
    && $user_lng >= -180 && $user_lng <= 180;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    if ($has_user_location) {
        $sql = "SELECT *, 
                CASE
                    WHEN latitude IS NOT NULL AND longitude IS NOT NULL
                        AND latitude BETWEEN -90 AND 90
                        AND longitude BETWEEN -180 AND 180
                        AND NOT (latitude = 0 AND longitude = 0)
                    THEN 6371 * 2 * ASIN(SQRT(LEAST(1.0, GREATEST(0.0,
                        POWER(SIN(RADIANS(latitude - :user_lat_1) / 2), 2)
                        + COS(RADIANS(:user_lat_2)) * COS(RADIANS(latitude))
                        * POWER(SIN(RADIANS(longitude - :user_lng) / 2), 2)
                    ))))
                    ELSE NULL
                END AS distance
                FROM temples
                ORDER BY distance IS NULL ASC, distance ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_lat_1' => $user_lat,
            ':user_lat_2' => $user_lat,
            ':user_lng' => $user_lng,
        ]);
    } else {
        $sql = "SELECT *, NULL as distance FROM temples";
        $stmt = $pdo->query($sql);
    }
    
    $temples_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_temples = [];
    foreach ($temples_data as $t) {
        $badge = !empty($t['badge']) ? $t['badge'] : 'แนะนำ';
        if ($has_user_location && $t['distance'] !== null) {
            $badge = number_format((float)$t['distance'], 1) . " กม.";
        } elseif ($has_user_location) {
            $badge = 'ไม่ระบุพิกัด';
        }

        $image_path = !empty($t['image']) ? $t['image'] : 'assets/images/download.jpg';

        $formatted_temples[] = [
            'id' => (int)$t['id'],
            'name' => (string)($t['name'] ?? ''),
            'province' => (string)($t['province'] ?? $t['district'] ?? 'ไม่ระบุที่อยู่'),
            'district' => (string)($t['district'] ?? ''),
            'image' => $image_path,
            'badge' => $badge
        ];
    }

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดจาก DB: " . $e->getMessage());
}

// แปลง JSON ให้ปลอดภัย
$json_temples_safe = htmlspecialchars(json_encode($formatted_temples, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ThumWadThaiHub - ระบบสนับสนุนกิจการวัดส่วนกลาง</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;0,9..144,900&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <style>
    /* 1. Global Box-Sizing & ป้องกันจอเลื่อนแนวนอน (No Horizontal Overflow) */
    html, body {
      overflow-x: hidden;
      width: 100%;
      max-width: 100%;
      margin: 0;
      padding: 0;
    }
    *, *::before, *::after {
      box-sizing: border-box;
    }

    body { font-family: 'Sarabun', sans-serif; }
    [x-cloak] { display: none !important; }

    /* 2. Container & Section Widths */
    .hero-cream {
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
      overflow-x: hidden;
      background-color: #f7f0d8 !important;
      background-image: linear-gradient(rgba(247, 240, 216, 0.35), rgba(247, 240, 216, 0.35)), url('assets/images/bg.jpg') !important;
      background-position: center;
      background-size: cover;
      background-repeat: no-repeat;
    }
    .popular-section {
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
      overflow-x: hidden;
      background-color: #f8fafc;
      background-image: linear-gradient(rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.78)), url('assets/images/bg2.jpg');
      background-position: center;
      background-size: cover;
      background-repeat: no-repeat;
    }

    .container {
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
    }
    @media (min-width: 1024px) {
      .container {
        max-width: 72rem;
      }
    }

    /* 3. Responsive Headline (ThumWadThaiHub) */
    .hero-title {
      font-size: clamp(2.1rem, 7.5vw, 3.75rem);
      font-weight: 700;
      line-height: 1.15;
      word-break: break-word;
      overflow-wrap: break-word;
    }
    @media (min-width: 1024px) {
      .hero-title {
        font-size: 3.75rem; /* 60px คงเดิมบน Desktop */
        line-height: 1.1;
      }
    }

    /* 4. Composition (ซองจดหมาย + รูปภาพตรงกลาง + ดอกบัว) */
    .composition {
      position: relative;
      width: 100%;
      max-width: 420px;
      aspect-ratio: 1 / 1.02;
      margin: 0 auto;
      box-sizing: border-box;
    }
    .comp-inner {
      position: relative;
      width: 100%;
      height: 100%;
      opacity: 0;
      transform: translateY(64px);
      animation: riseIn 1.05s cubic-bezier(0.16, 0.9, 0.28, 1) 0.15s forwards;
    }
    @keyframes riseIn { to { opacity: 1; transform: translateY(0); } }

    /* ซองจดหมาย + การ์ด (Desktop สเกลเดิม) */
    .letter {
      position: absolute;
      left: 5%;
      top: 0;
      width: 90%;
      max-width: 100%;
      z-index: 1;
      transform: scale(1.12);
    }
    .letter .envelope-img {
      display: block;
      width: 100%;
      max-width: 100%;
      height: auto;
      filter: drop-shadow(0 20px 28px rgba(60, 40, 10, 0.25));
    }
    .letter .temple-photo {
      position: absolute;
      left: 9.4%;
      top: 36%;
      width: 74.4%;
      max-width: 100%;
      height: 49.5%;
      object-fit: cover;
      z-index: 2;
    }
    .hero-lotus {
      position: absolute;
      left: -2%;
      bottom: -24%;
      width: 56%;
      max-width: 100%;
      height: auto;
      transform: scale(1.12);
      filter: drop-shadow(0 16px 22px rgba(60, 40, 10, 0.22));
      z-index: 3;
    }
    .lotus-img {
      position: fixed;
      left: 16px;
      bottom: 16px;
      width: clamp(64px, 7vw, 84px);
      height: auto;
      filter: drop-shadow(0 4px 10px rgba(60, 40, 10, 0.18));
      opacity: 0.85;
      pointer-events: none;
      z-index: 40;
    }

    /* 5. ช่องค้นหา (Search Bar) Responsive */
    .hero-search-form {
      width: 100%;
      max-width: 28rem;
      box-sizing: border-box;
    }

    /* 📱 Tablet & Mobile Media Queries (ไม่กระทบ Desktop) */
    @media (max-width: 1023px) {
      .composition {
        max-width: 360px;
      }
    }
    @media (max-width: 768px) {
      .hero-cream {
        padding-top: 2.5rem;
        padding-bottom: 2.5rem;
      }
      .composition {
        max-width: min(85vw, 320px);
      }
      .letter {
        transform: scale(1.04);
      }
      .hero-lotus {
        width: 50%;
        left: 0;
        bottom: -16%;
        transform: scale(1.04);
      }
      .hero-search-form {
        max-width: 100%;
      }
    }
    @media (max-width: 440px) {
      .composition {
        max-width: min(88vw, 280px);
      }
      .letter {
        transform: none;
        left: 5%;
        width: 90%;
      }
      .hero-lotus {
        width: 46%;
        bottom: -12%;
        left: 2%;
        transform: none;
      }
      .lotus-img {
        width: 38px;
        left: 8px;
        bottom: 8px;
        opacity: 0.65;
      }
      .hero-search-form {
        padding: 4px;
      }
      .hero-search-form input {
        font-size: 11px;
        padding-left: 0.75rem;
      }
      .hero-search-form button {
        padding: 0.5rem 0.85rem;
        font-size: 11px;
      }
    }
  </style>
</head>

<body class="bg-white" 
  id="app-root"
  data-temples="<?php echo $json_temples_safe; ?>"
  x-data="{
    isSticky: false,
    mobileNavOpen: false,
    templeName: localStorage.getItem('selected_temple_name') || '',
    searchQuery: '<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>',
    popularTemples: [],

    init() {
      try {
        const rawData = document.getElementById('app-root').getAttribute('data-temples');
        this.popularTemples = JSON.parse(rawData) || [];
      } catch (e) {
        console.error('JSON Parse error:', e);
        this.popularTemples = [];
      }
    },

    selectForUser(id, name) {
      localStorage.setItem('selected_temple_id', id);
      localStorage.setItem('selected_temple_name', name);
      this.templeName = name;
      window.location.href = 'temple_page.php?id=' + id;
    },
    clearSelectedTemple() {
      localStorage.removeItem('selected_temple_id');
      localStorage.removeItem('selected_temple_name');
      this.templeName = '';
    }
  }" 
  @scroll.window="isSticky = (window.pageYOffset > 50)">

  <img class="lotus-img" src="assets/images/lotus-icon.png" alt="" aria-hidden="true">

  <header class="w-full bg-white text-gray-800 border-b border-gray-100 transition-all duration-300"
    :class="isSticky ? 'fixed top-0 left-0 z-50 shadow-md border-b-0 animate-fade-down' : 'relative'">
    <div class="container mx-auto flex justify-between items-center py-4 px-6">
      <a href="index.php" class="flex items-center gap-2 font-bold text-gray-900">
        <i class="fa-solid fa-dharmachakra text-amber-600 text-xl"></i>
        <span class="text-xs uppercase font-extrabold tracking-wider text-amber-950">ThumWadThaiHub</span>
      </a>
      <nav class="hidden lg:flex justify-end flex-grow mr-6">
        <ul class="flex items-center space-x-8 text-xs uppercase tracking-widest font-bold text-gray-600">
          <li><a href="index.php" class="text-amber-600">หน้าแรก</a></li>
        </ul>
      </nav>

      <div class="hidden md:flex items-center gap-4 text-xs font-bold uppercase tracking-widest">
        <?php if (isset($_SESSION['user_id'])): ?>
          <span class="normal-case tracking-normal text-gray-700 whitespace-nowrap">
            สวัสดี, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
          </span>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'): ?>
          <a href="my_bookings.php" class="bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-bold px-4 py-2 rounded-xl shadow-sm hover:shadow-md flex items-center gap-1.5 transition whitespace-nowrap">
            <i class="fa-solid fa-calendar-check text-[10px]"></i> รายการจองของฉัน
          </a>
        <?php elseif (isset($_SESSION['user_id'])): ?>
          <a href="<?php echo $_SESSION['role'] === 'superadmin' ? 'superadmin_dashboard.php' : 'admin_dashboard.php'; ?>" class="bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-bold px-4 py-2 rounded-xl shadow-sm hover:shadow-md flex items-center gap-1.5 transition whitespace-nowrap">
            <i class="fa-solid fa-user-shield text-[10px]"></i> หน้าจัดการระบบ
          </a>
        <?php else: ?>
          <a href="register.php" class="text-amber-700 font-bold px-3 py-2 rounded-xl hover:bg-amber-50 transition whitespace-nowrap">สมัครสมาชิก</a>
          <a href="login.php" class="bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-bold px-4 py-2 rounded-xl shadow-sm hover:shadow-md flex items-center gap-1.5 transition whitespace-nowrap">
            <i class="fa-solid fa-user text-[10px]"></i> เข้าสู่ระบบ
          </a>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="logout.php" class="border border-red-200 text-red-600 hover:bg-red-50 font-bold px-4 py-2 rounded-xl transition whitespace-nowrap">
            <i class="fa-solid fa-right-from-bracket text-[10px]"></i> ออกจากระบบ
          </a>
        <?php endif; ?>
      </div>

      <!-- Mobile Hamburger Button -->
      <button @click="mobileNavOpen = !mobileNavOpen" type="button" class="md:hidden p-2 rounded-xl text-gray-700 hover:text-amber-600 hover:bg-amber-50 focus:outline-none transition" aria-label="เปิดเมนูนำทาง">
        <i class="fa-solid" :class="mobileNavOpen ? 'fa-xmark text-lg' : 'fa-bars text-lg'"></i>
      </button>
    </div>
    <!-- Mobile Dropdown Menu -->
    <div x-cloak x-show="mobileNavOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2" class="md:hidden border-t border-gray-100 bg-white px-5 py-4 shadow-lg">
      <?php if (isset($_SESSION['user_id'])): ?>
        <div class="mb-3 pb-3 border-b border-gray-100 flex items-center gap-2 text-xs text-gray-700 font-semibold">
          <i class="fa-solid fa-circle-user text-amber-600 text-base"></i>
          <span>สวัสดี, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></span>
        </div>
      <?php endif; ?>

      <div class="flex flex-col gap-2 text-xs font-bold uppercase tracking-wider">
        <a href="index.php" class="flex items-center gap-2.5 py-2.5 px-3 rounded-xl text-amber-700 bg-amber-50">
          <i class="fa-solid fa-house text-sm w-4 text-center"></i> หน้าแรก
        </a>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'): ?>
          <a href="my_bookings.php" class="flex items-center gap-2.5 py-2.5 px-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow-sm">
            <i class="fa-solid fa-calendar-check text-sm w-4 text-center"></i> รายการจองของฉัน
          </a>
        <?php elseif (isset($_SESSION['user_id'])): ?>
          <a href="<?php echo $_SESSION['role'] === 'superadmin' ? 'superadmin_dashboard.php' : 'admin_dashboard.php'; ?>" class="flex items-center gap-2.5 py-2.5 px-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow-sm">
            <i class="fa-solid fa-user-shield text-sm w-4 text-center"></i> หน้าจัดการระบบ
          </a>
        <?php else: ?>
          <a href="login.php" class="flex items-center gap-2.5 py-2.5 px-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow-sm">
            <i class="fa-solid fa-user text-sm w-4 text-center"></i> เข้าสู่ระบบ
          </a>
          <a href="register.php" class="flex items-center gap-2.5 py-2.5 px-3 rounded-xl text-amber-800 border border-amber-200 hover:bg-amber-50">
            <i class="fa-solid fa-user-plus text-sm w-4 text-center"></i> สมัครสมาชิก
          </a>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
          <a href="logout.php" class="flex items-center gap-2.5 py-2.5 px-3 rounded-xl border border-red-200 text-red-600 hover:bg-red-50 mt-1">
            <i class="fa-solid fa-right-from-bracket text-sm w-4 text-center"></i> ออกจากระบบ
          </a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <section class="hero-cream py-10 sm:py-12 lg:py-20 px-4 sm:px-6">
    <div class="container mx-auto max-w-6xl grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">

      <div class="composition">
  <div class="comp-inner">
    <div class="letter">
      <img class="envelope-img" src="assets/images/letter.png" alt="ซองจดหมาย">
      <img class="temple-photo" src="assets/images/temple-watyai.jpg" alt="รูปวัด"
           onerror="this.onerror=null; this.src='assets/images/download.jpg';">
    </div>
  </div>
  <img class="hero-lotus" src="assets/images/lotus.png" alt="ดอกบัว">
</div>

      <div class="text-center lg:text-left">
        <div class="flex items-center justify-center lg:justify-start gap-2 text-amber-600 font-semibold text-xs uppercase tracking-widest mb-3">
          <span class="w-6 h-0.5 bg-amber-600 inline-block"></span> THAI TEMPLE SEARCH PLATFORM
        </div>
        <h1 class="hero-title text-amber-950 mb-4" style="font-family:'Fraunces', serif;">
          ThumWadThaiHub
        </h1>
        <p class="text-gray-600 text-xs md:text-sm leading-relaxed max-w-md mx-auto lg:mx-0 mb-6">
          ค้นหาวัดที่อยู่ใกล้คุณที่สุด เพื่อความสะดวกในการทำบุญและร่วมกิจกรรมทางศาสนา
        </p>

        <form action="index.php" method="GET" class="hero-search-form max-w-md mx-auto lg:mx-0 bg-white p-1.5 rounded-full flex items-center shadow-md border border-gray-100">
          <?php if($user_lat && $user_lng): ?>
            <input type="hidden" name="lat" value="<?php echo htmlspecialchars($user_lat, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="lng" value="<?php echo htmlspecialchars($user_lng, ENT_QUOTES, 'UTF-8'); ?>">
          <?php endif; ?>
          <input type="search" name="search" x-model.debounce.150ms="searchQuery" @input="$dispatch('temple-search-changed')" placeholder="พิมพ์ชื่อวัดหรือบางส่วนของชื่อวัด..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
            class="w-full min-w-0 bg-transparent pl-3 sm:pl-4 pr-2 py-2 text-gray-800 focus:outline-none text-xs placeholder-gray-400">
          <button type="submit" class="shrink-0 bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs px-4 sm:px-5 py-2.5 rounded-full flex items-center gap-1.5 transition whitespace-nowrap">
            <i class="fa-solid fa-magnifying-glass text-[11px]"></i> ค้นหา
          </button>
        </form>
      </div>

    </div>
  </section>

  <section id="popular-section" class="popular-section py-12 px-4 sm:px-6 border-t border-gray-100" x-data="{
    activeIndex: 0,
    touchStartX: 0,
    touchEndX: 0,
    handleSwipe() {
      if (this.touchEndX < this.touchStartX - 40) this.next();
      if (this.touchEndX > this.touchStartX + 40) this.prev();
    },
    get filteredTemples() {
      const query = (this.searchQuery || '').trim().toLocaleLowerCase();
      if (!query) return popularTemples;
      return popularTemples.filter((temple) =>
        [temple.name, temple.province, temple.district]
          .some((value) => (value || '').toLocaleLowerCase().includes(query))
      );
    },
    next() {
      const count = this.filteredTemples.length;
      if (count) this.activeIndex = (this.activeIndex + 1) % count;
    },
    prev() {
      const count = this.filteredTemples.length;
      if (count) this.activeIndex = (this.activeIndex - 1 + count) % count;
    },
    getCardStyle(index) {
      const count = this.filteredTemples.length;
      let offset = index - this.activeIndex;
      if (offset > count / 2) offset -= count;
      if (offset < -count / 2) offset += count;
      const abs = Math.abs(offset);
      if (abs > 3) {
        return 'left:50%; top:50%; opacity:0; pointer-events:none; transform:translate(-50%, -50%) scale(0.5);';
      }
      const isMobile = window.innerWidth < 640;
      const step = isMobile ? 120 : 220;
      const translateX = offset * step;
      const rotateY = offset === 0 ? 0 : (offset > 0 ? (isMobile ? -25 : -35) : (isMobile ? 25 : 35));
      const scale = offset === 0 ? 1 : (isMobile ? 0.88 - (abs - 1) * 0.08 : 0.85 - (abs - 1) * 0.05);
      const opacity = offset === 0 ? 1 : 0.7 - (abs - 1) * 0.2;
      const zIndex = 100 - abs;
      return `left:calc(50% + ${translateX}px); top:50%; transform: translate(-50%, -50%) rotateY(${rotateY}deg) scale(${scale});
              opacity: ${Math.max(opacity, 0.1)};
              z-index: ${zIndex};`;
    }
  }" @temple-search-changed.window="activeIndex = 0">

    <div class="container mx-auto max-w-6xl">

      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6 border-b border-gray-200 pb-4">
        <div class="flex items-center gap-2 text-base font-bold text-gray-800">
          <i class="fa-solid fa-location-dot text-red-600"></i>
          <h2>วัดที่อยู่ใกล้คุณ (ยอดนิยม)</h2>
        </div>
        <button onclick="getLocation()"
          class="bg-amber-600 hover:bg-amber-700 text-white text-[11px] font-semibold px-4 py-2 rounded-lg flex items-center gap-1.5 shadow-sm transition">
          <i class="fa-solid fa-paper-plane text-[9px]"></i> อัปเดตตำแหน่งของฉัน
        </button>
      </div>
      <p class="mb-2 text-right text-[10px] text-gray-500">
        พิกัดอ้างอิงจาก <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer" class="underline">© OpenStreetMap contributors</a>
      </p>

      <div class="relative w-full py-6 min-h-[400px] flex items-center justify-center overflow-hidden select-none" style="perspective: 1000px;" x-show="filteredTemples.length > 0" @touchstart="touchStartX = $event.changedTouches[0].screenX" @touchend="touchEndX = $event.changedTouches[0].screenX; handleSwipe()">

        <button @click="prev()"
          class="absolute left-2 top-1/2 -translate-y-1/2 z-50 bg-white/90 hover:bg-amber-600 hover:text-white text-gray-700 w-9 h-9 rounded-full shadow-md flex items-center justify-center transition">
          <i class="fa-solid fa-chevron-left text-xs"></i>
        </button>

        <div class="relative w-full flex items-center justify-center h-[380px]" style="transform-style: preserve-3d;">
          <template x-for="(temple, index) in filteredTemples" :key="temple.id">
            <div class="absolute w-56 sm:w-64 bg-white rounded-2xl border border-gray-100 shadow-xl overflow-hidden flex flex-col justify-between cursor-pointer transition-all duration-500 ease-out"
              :style="getCardStyle(index)" @click="activeIndex = index">
              
              <div>
                <div class="w-full h-40 bg-gray-100 relative overflow-hidden">
                  <img :src="temple.image" :alt="temple.name" class="w-full h-full object-cover"
                    onerror="this.onerror=null; this.src='assets/images/download.jpg';">
                  <div class="absolute top-2 left-2">
                    <span class="text-[9px] bg-amber-600 text-white font-bold px-2 py-0.5 rounded-full uppercase" x-text="temple.badge"></span>
                  </div>
                </div>

                <div class="p-4">
                  <span class="text-[10px] text-gray-400 block mb-1">
                    <i class="fa-solid fa-location-dot text-amber-600 mr-1"></i><span x-text="temple.province"></span>
                  </span>
                  <h3 class="font-bold text-gray-800 text-sm mb-1 line-clamp-1" x-text="temple.name"></h3>
                  <span class="text-[11px] text-gray-500 block line-clamp-1" x-text="temple.district"></span>
                </div>
              </div>

              <div class="p-4 pt-0">
                <button @click.stop="selectForUser(temple.id, temple.name)"
                  class="w-full bg-gray-50 hover:bg-amber-600 hover:text-white text-gray-700 text-xs font-medium py-2 rounded-xl flex items-center justify-center gap-1 transition">
                  เข้าสู่หน้าของวัด <i class="fa-solid fa-arrow-right text-[9px]"></i>
                </button>
              </div>

            </div>
          </template>
        </div>

        <button @click="next()"
          class="absolute right-2 top-1/2 -translate-y-1/2 z-50 bg-white/90 hover:bg-amber-600 hover:text-white text-gray-700 w-9 h-9 rounded-full shadow-md flex items-center justify-center transition">
          <i class="fa-solid fa-chevron-right text-xs"></i>
        </button>
      </div>

      <div x-cloak x-show="filteredTemples.length === 0" class="mx-auto my-8 max-w-lg rounded-2xl border border-amber-100 bg-white px-6 py-10 text-center shadow-sm">
        <i class="fa-solid fa-magnifying-glass mb-3 text-2xl text-amber-600" aria-hidden="true"></i>
        <p class="font-semibold text-gray-800">ไม่พบวัดที่ตรงกับคำค้นหา</p>
        <p class="mt-1 text-sm text-gray-500">ลองพิมพ์ชื่อวัดหรือบางส่วนของชื่ออีกครั้ง</p>

</div>

      <div class="flex justify-center gap-1.5 mt-2">
        <template x-for="(temple, index) in filteredTemples" :key="'dot-' + temple.id">
          <button @click="activeIndex = index" class="w-2 h-2 rounded-full transition-all"
            :class="activeIndex === index ? 'bg-amber-600 w-4' : 'bg-gray-300'"></button>
        </template>
      </div>

    </div>
  </section>

  <section class="border-t border-gray-100 bg-white px-4 sm:px-6 py-14" aria-labelledby="region-title" x-data="{
    scrollRegions(direction) {
      const track = this.$refs.regionTrack;
      track.scrollBy({ left: direction * track.clientWidth * 0.8, behavior: 'smooth' });
    }
  }">
    <div class="container mx-auto max-w-6xl">
      <div class="relative mb-8 flex flex-col items-center gap-4 text-center sm:block">
        <div class="sm:text-center">
          <h2 id="region-title" class="text-2xl font-semibold text-gray-800" style="font-family:'Fraunces', serif;">TEMPLE BY REGION</h2>
          <p class="mt-1 text-sm text-gray-500">ค้นหาข้อมูลวัดตามภูมิภาค</p>
        </div>
        <div class="flex shrink-0 gap-2 sm:absolute sm:right-0 sm:top-1/2 sm:-translate-y-1/2">
          <button type="button" @click="scrollRegions(-1)" aria-label="เลื่อนภูมิภาคไปทางซ้าย"
            class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 text-gray-700 transition hover:border-amber-600 hover:bg-amber-600 hover:text-white">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
          </button>
          <button type="button" @click="scrollRegions(1)" aria-label="เลื่อนภูมิภาคไปทางขวา"
            class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 text-gray-700 transition hover:border-amber-600 hover:bg-amber-600 hover:text-white">
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <div x-ref="regionTrack" class="flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-4">
        <a href="temples_by_region.php?region=north" aria-label="ดูข้อมูลวัดภาคเหนือ" class="group block w-[220px] shrink-0 snap-start sm:w-[240px] lg:w-[250px]">
          <img src="assets/images/north.jpg" alt="วัดในภาคเหนือ" class="h-72 w-full object-cover transition group-hover:opacity-90">
          <h3 class="mt-4 text-lg font-semibold text-gray-800 group-hover:text-amber-700">ภาคเหนือ</h3>
        </a>
        <a href="temples_by_region.php?region=central" aria-label="ดูข้อมูลวัดภาคกลาง" class="group block w-[220px] shrink-0 snap-start sm:w-[240px] lg:w-[250px]">
          <img src="assets/images/khlang.jpeg" alt="วัดในภาคกลาง" class="h-72 w-full object-cover transition group-hover:opacity-90">
          <h3 class="mt-4 text-lg font-semibold text-gray-800 group-hover:text-amber-700">ภาคกลาง</h3>
        </a>
        <a href="temples_by_region.php?region=northeast" aria-label="ดูข้อมูลวัดภาคอีสาน" class="group block w-[220px] shrink-0 snap-start sm:w-[240px]
         lg:w-[250px]">
          <img src="assets/images/esan.jpg" alt="วัดในภาคอีสาน" class="h-72 w-full object-cover transition group-hover:opacity-90">
          <h3 class="mt-4 text-lg font-semibold text-gray-800 group-hover:text-amber-700">ภาคอีสาน</h3>
        </a>
        <a href="temples_by_region.php?region=east" aria-label="ดูข้อมูลวัดภาคตะวันออก" class="group block w-[220px] shrink-0 snap-start sm:w-[240px] lg:w-[250px]">
          <img src="assets/images/oak.jpg" alt="วัดในภาคตะวันออก" class="h-72 w-full object-cover transition group-hover:opacity-90">
          <h3 class="mt-4 text-lg font-semibold text-gray-800 group-hover:text-amber-700">ภาคตะวันออก</h3>
        </a>
        <a href="temples_by_region.php?region=west" aria-label="ดูข้อมูลวัดภาคตะวันตก" class="group block w-[220px] shrink-0 snap-start sm:w-[240px] lg:w-[250px]">
          <img src="assets/images/Phraya_Nakhon_Cave.jpg" alt="วัดในภาคตะวันตก" class="h-72 w-full object-cover transition group-hover:opacity-90">
          <h3 class="mt-4 text-lg font-semibold text-gray-800 group-hover:text-amber-700">ภาคตะวันตก</h3>
        </a>
        <a href="temples_by_region.php?region=south" aria-label="ดูข้อมูลวัดภาคใต้" class="group block w-[220px] shrink-0 snap-start sm:w-[240px] lg:w-[250px]">
          <img src="assets/images/tai.jpg" alt="วัดในภาคใต้" class="h-72 w-full object-cover transition group-hover:opacity-90">
          <h3 class="mt-4 text-lg font-semibold text-gray-800 group-hover:text-amber-700">ภาคใต้</h3>
        </a>
      </div>
    </div>
  </section>

  <footer class="border-t border-gray-200 bg-white px-4 sm:px-6 py-8">
    <div class="container mx-auto max-w-6xl grid grid-cols-1 gap-6 sm:grid-cols-2">
      <section aria-labelledby="developer-credit-title">
        <h2 id="developer-credit-title" class="mb-2 text-sm font-bold text-gray-800">เครดิตผู้พัฒนา</h2>
        <p class="text-sm text-gray-600">นาย ลิง</p>
        <p class="text-sm text-gray-600">นางสาว สวย</p>
      </section>
      <section aria-labelledby="contact-title">
        <h2 id="contact-title" class="mb-2 text-sm font-bold text-gray-800">ช่องทางติดต่อ</h2>
        <p class="text-sm text-gray-600">ยังไม่ได้ระบุข้อมูลติดต่อ</p>
      </section>
    </div>
  </footer>

  <script>
    function getLocation() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showPosition, showError, {
          enableHighAccuracy: true,
          maximumAge: 0,
          timeout: 15000
        });
      } else {
        alert("เบราว์เซอร์ไม่รองรับการระบุตำแหน่ง");
      }
    }

    function showPosition(position) {
      const { latitude, longitude } = position.coords;
      const params = new URLSearchParams({
        lat: String(latitude),
        lng: String(longitude)
      });
      const currentSearch = "<?php echo urlencode($search); ?>";
      if (currentSearch) params.set('search', currentSearch);
      window.location.href = "index.php?" + params.toString();
    }

    function showError(error) {
      const messages = {
        1: "กรุณาอนุญาตให้เว็บไซต์เข้าถึงตำแหน่งของคุณ",
        2: "ไม่สามารถระบุตำแหน่งได้ กรุณาลองใหม่ในบริเวณที่รับสัญญาณได้",
        3: "การระบุตำแหน่งใช้เวลานานเกินไป กรุณาลองอีกครั้ง"
      };
      alert(messages[error.code] || "ไม่สามารถดึงตำแหน่งได้ กรุณาลองอีกครั้ง");
    }
  </script>

</body>

</html>