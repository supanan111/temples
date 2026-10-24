<?php
session_start();
require_once 'db_config.php';
require_once 'temple_sections.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    ensure_temple_sections_table($pdo);
    // ดึงข้อมูลวัด (รวมถึง description ที่เพิ่งเพิ่มเข้าไป)
    $stmt = $pdo->prepare("SELECT * FROM temples WHERE id = ?");
    $stmt->execute([$id]);
    $temple = $stmt->fetch();
    if (!$temple) { die("<h2 style='text-align:center; margin-top:50px; color:#4A4036;'>ไม่พบข้อมูลวัดที่คุณค้นหา</h2>"); }

    // ดึงข่าวสาร
    $stmt_news = $pdo->prepare("SELECT * FROM news WHERE temple_id = ? ORDER BY created_at DESC LIMIT 6");
    $stmt_news->execute([$id]);
    $news_list = $stmt_news->fetchAll();

    // ดึงกิจกรรมที่กำลังจะมาถึง
    $stmt_events = $pdo->prepare("SELECT * FROM events WHERE temple_id = ? AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5");
    $stmt_events->execute([$id]);
    $events = $stmt_events->fetchAll();
    $custom_sections = get_temple_sections($pdo, $id);

} catch (PDOException $e) { die("เกิดข้อผิดพลาด: " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($temple['name']); ?> - บริการออนไลน์</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 🌟 Theme พุทธศิลป์ร่วมสมัย 🌟 */
        :root { 
            --primary-color: #C08221; /* สีทองหม่น */
            --primary-hover: #A06816; 
            --bg-color: #FDFBF7; /* สีขาวครีมงาช้าง */
            --text-main: #3E352F; /* สีเทาน้ำตาลเข้ม */
            --text-muted: #857B74; 
            --card-bg: #FFFFFF;
            --border-color: #EAE5DE;
        }
        
        body { font-family: 'Sarabun', sans-serif; background: var(--bg-color); margin: 0; color: var(--text-main); line-height: 1.6; }
        
        /* Navbar เรียบหรู */
        nav { background: var(--card-bg); padding: 15px 50px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(62, 53, 47, 0.02); }
        .logo { font-size: 20px; font-weight: 500; color: var(--primary-color); text-decoration: none; display: flex; align-items: center; gap: 10px; letter-spacing: 0.5px;}
        .home-link { padding: 9px 16px 9px 10px; border: 1px solid #E8D8B8; border-radius: 999px; background: linear-gradient(135deg, #FFFDF8, #F8F0E1); box-shadow: 0 3px 10px rgba(62, 53, 47, 0.06); transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; }
        .home-link:hover { transform: translateY(-1px); border-color: var(--primary-color); box-shadow: 0 6px 16px rgba(192, 130, 33, 0.16); }
        .home-link:focus-visible { outline: 3px solid rgba(192, 130, 33, 0.35); outline-offset: 3px; }
        .home-link i { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--primary-color); color: #fff; font-size: 14px; }
        .login-link { font-size: 14px; padding: 9px 16px 9px 10px; border: 1px solid #E8D8B8; border-radius: 999px; background: linear-gradient(135deg, #FFFDF8, #F8F0E1); box-shadow: 0 3px 10px rgba(62, 53, 47, 0.06); color: var(--primary-color); text-decoration: none; display: inline-flex; align-items: center; gap: 9px; transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; }
        .login-link:hover { transform: translateY(-1px); border-color: var(--primary-color); box-shadow: 0 6px 16px rgba(192, 130, 33, 0.16); }
        .login-link:focus-visible { outline: 3px solid rgba(192, 130, 33, 0.35); outline-offset: 3px; }
        .login-link i { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--primary-color); color: #fff; font-size: 14px; }
        
        /* Hero Section แบบ Minimal */
        .hero-section { position: relative; overflow: hidden; background: #2B2522; padding: 70px 20px; text-align: center; color: white; margin-bottom: 50px; border-bottom: 4px solid var(--primary-color); }
        .hero-section::after { content: ""; position: absolute; inset: 0; z-index: 1; background: linear-gradient(rgba(43, 37, 34, 0.7), rgba(43, 37, 34, 0.8)); }
        .hero-image { position: absolute; inset: 0; z-index: 0; width: 100%; height: 100%; object-fit: cover; }
        .hero-section h1, .hero-section p { position: relative; z-index: 2; }
        .hero-section h1 { margin: 0; font-size: 38px; font-weight: 500; letter-spacing: 1px; }
        .hero-section p { font-size: 16px; margin-top: 15px; color: #EAE5DE; font-weight: 300; }

        .container { max-width: 1100px; margin: 0 auto; padding: 0 20px 50px 20px; }
        .section-title { font-size: 20px; color: var(--text-main); border-bottom: 2px solid var(--border-color); padding-bottom: 10px; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; font-weight: 500; }

        /* การ์ดเมนูจอง */
        .category-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 60px;}
        .category-card { background: var(--card-bg); border-radius: 8px; padding: 35px 20px; text-align: center; border: 1px solid var(--border-color); text-decoration: none; color: var(--text-main); transition: 0.3s; position: relative; overflow: hidden; isolation: isolate; }
        .category-card::before, .category-card::after { content: ""; position: absolute; inset: 0; z-index: -1; background-position: center; background-size: cover; transition: opacity 0.25s; }
        .category-card::before { background-image: linear-gradient(rgba(255, 255, 255, 0.48), rgba(255, 255, 255, 0.48)), url('assets/images/icon2.jpg'); opacity: 1; }
        .category-card::after { background-image: linear-gradient(rgba(0, 0, 0, 0.42), rgba(0, 0, 0, 0.42)), var(--hover-image); opacity: 0; }
        .category-card:hover { transform: translateY(-3px); border-color: var(--primary-color); box-shadow: 0 10px 20px rgba(192, 130, 33, 0.05); color: #FFFFFF; }
        .category-card:hover::before { opacity: 0; }
        .category-card:hover::after { opacity: 1; }
        .category-card:hover .category-icon i { color: #FFFFFF; }
        .category-card > * { position: relative; z-index: 1; }
        .category-icon { width: 48px; height: 48px; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; position: relative; }
        .category-icon i { font-size: 40px; color: var(--primary-color); opacity: 0.9; }
        .category-card h3 { margin: 0; font-size: 16px; font-weight: 500; }

        /* โครงสร้าง 2 คอลัมน์ (ซ้ายข่าว - ขวาปฏิทิน) */
        .content-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; }
        @media (max-width: 768px) { .content-layout { grid-template-columns: 1fr; } }

        /* ข่าวสารแบบมินิมอล */
        .news-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; }
        .news-card { background: var(--card-bg); border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); transition: 0.3s; }
        .news-card:hover { box-shadow: 0 8px 15px rgba(62, 53, 47, 0.05); }
        .news-img { width: 100%; height: 200px; object-fit: cover; background: #F5F2ED; }
        .news-body { padding: 20px; }
        .news-date { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; display: block; }
        .news-title { font-size: 16px; font-weight: 500; color: var(--text-main); margin: 0 0 10px 0; line-height: 1.4; }
        .news-desc { font-size: 14px; color: var(--text-muted); margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

        /* ปฏิทินและ E-Donation */
        .event-panel { background: var(--card-bg); border-radius: 8px; padding: 25px; border: 1px solid var(--border-color); }
        .event-item { display: flex; gap: 15px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color); }
        .event-item:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .event-date-box { background: #FDFBF7; color: var(--primary-color); min-width: 65px; height: 65px; border-radius: 6px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: bold; border: 1px solid #EAE5DE;}
        .event-date-box .d { font-size: 22px; line-height: 1; }
        .event-date-box .m { font-size: 13px; font-weight: 400; margin-top: 2px;}
        .event-detail h4 { margin: 0 0 5px 0; color: var(--text-main); font-size: 15px; font-weight: 500; }
        .event-detail p { margin: 0; font-size: 13px; color: var(--text-muted); }

        .btn-donate { display:block; background: var(--primary-color); color: white; text-align: center; padding: 15px; border-radius: 8px; text-decoration: none; margin-top: 25px; font-weight: 500; font-size: 15px; transition: 0.3s; }
        .btn-donate:hover { background: var(--primary-hover); }
        
        /* กล่องประวัติวัด */
        .history-panel { margin-top: 50px; background: url('assets/images/krob.png') center / 100% 100% no-repeat; padding: 130px 20%; border-radius: 8px; border: none; box-shadow: none; }
        .history-content { color: var(--text-main); line-height: 1.8; font-size: 15px; white-space: pre-wrap; }
        .custom-section { margin-top: 28px; padding: 28px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 8px 24px rgba(62, 53, 47, 0.04); }
        .custom-section .section-title { margin-bottom: 16px; }
        .custom-section-content { color: var(--text-main); line-height: 1.9; font-size: 15px; white-space: normal; overflow-wrap: anywhere; }
        @media (max-width: 768px) {
            nav { padding: 12px 16px; }
            .hero-section { padding: 45px 16px; margin-bottom: 30px; }
            .hero-section h1 { font-size: clamp(22px, 5.5vw, 34px); }
            .hero-section p { font-size: 14px; }
            .container { padding: 0 16px 40px; }
            .category-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 40px; }
            .category-card { padding: 22px 12px; }
            .category-card h3 { font-size: 14px; }
            .category-icon { width: 38px; height: 38px; margin-bottom: 10px; }
            .category-icon i { font-size: 32px; }
            .news-grid { grid-template-columns: 1fr; gap: 18px; }
            .history-panel { padding: 50px 20px; background-size: cover; border-radius: 12px; }
            .custom-section { padding: 20px 16px; }
        }
        @media (max-width: 480px) {
            .home-link, .login-link { padding: 7px 12px 7px 8px; font-size: 13px; gap: 6px; }
            .home-link i, .login-link i { width: 28px; height: 28px; font-size: 12px; }
            .category-grid { grid-template-columns: 1fr; }
            .event-panel { padding: 16px 14px; }
            .event-item { gap: 10px; }
            .event-date-box { min-width: 55px; height: 55px; }
            .event-date-box .d { font-size: 18px; }
        }
    </style>
</head>
<body>
<nav>
    <a href="index.php" class="logo home-link"><i class="fas fa-house"></i><span>กลับหน้าหลัก</span></a>
    <div style="display:flex; align-items:center; gap:16px;">
        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'): ?>
            <a href="my_bookings.php" style="color: var(--primary-color); text-decoration:none; font-size:14px;">รายการจองของฉัน</a>
        <?php elseif (!isset($_SESSION['user_id'])): ?>
            <a href="login.php" class="login-link"><i class="fas fa-right-to-bracket"></i><span>เข้าสู่ระบบ / สมัครสมาชิก</span></a>
        <?php endif; ?>
    </div>
</nav>

<div class="hero-section">
    <img class="hero-image" src="<?php echo htmlspecialchars(!empty($temple['image']) ? $temple['image'] : 'assets/images/download.jpg', ENT_QUOTES, 'UTF-8'); ?>" alt="" onerror="this.onerror=null; this.src='assets/images/download.jpg';">
    <h1><i class="fas fa-dharmachakra" style="color: var(--primary-color); font-size: 32px; margin-right: 10px;"></i> <?php echo htmlspecialchars($temple['name']); ?></h1>
    <p><i class="fas fa-map-marker-alt" style="color: var(--primary-color);"></i> <?php echo htmlspecialchars($temple['address']); ?></p>
</div>

<div class="container">
    <h2 class="section-title"><i class="fas fa-concierge-bell" style="color:var(--text-muted);"></i> ระบบบริการออนไลน์</h2>
    <div class="category-grid">
        <a href="booking.php?temple_id=<?php echo $id; ?>&type=pavilion" class="category-card" style="--hover-image: url('assets/images/temple-watyai.jpg');">
            <span class="category-icon"><i class="fas fa-vihara"></i></span><h3>จองศาลา / พิธีศพ</h3>
        </a>
        <a href="booking.php?temple_id=<?php echo $id; ?>&type=monk" class="category-card" style="--hover-image: url('assets/images/temple-watpho.jpg');">
            <span class="category-icon"><i class="fas fa-praying-hands"></i></span><h3>นิมนต์พระ</h3>
        </a>
        <a href="booking.php?temple_id=<?php echo $id; ?>&type=equipment" class="category-card" style="--hover-image: url('assets/images/temple-doisuthep.jpg');">
            <span class="category-icon"><i class="fas fa-chair"></i></span><h3>ยืมอุปกรณ์ / ของใช้</h3>
        </a>
        <a href="booking.php?temple_id=<?php echo $id; ?>&type=fair_stall" class="category-card" style="--hover-image: url('assets/images/temple-saman.jpg');">
            <span class="category-icon"><i class="fas fa-store"></i></span><h3>จองล็อคงานวัด</h3>
        </a>
    </div>

    <div class="content-layout">
        <div>
            <h2 class="section-title"><i class="fas fa-newspaper" style="color:var(--text-muted);"></i> ข่าวสารและประชาสัมพันธ์</h2>
            <?php if (count($news_list) > 0): ?>
                <div class="news-grid">
                    <?php foreach ($news_list as $news): ?>
                        <div class="news-card">
                            <?php if (!empty($news['image']) && file_exists('uploads/' . $news['image'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($news['image']); ?>" class="news-img" alt="รูปปกข่าว">
                            <?php else: ?>
                                <div class="news-img" style="display:flex; align-items:center; justify-content:center; color:var(--text-muted); opacity: 0.5;">
                                    <i class="fas fa-image fa-2x"></i>
                                </div>
                            <?php endif; ?>
                            <div class="news-body">
                                <span class="news-date"><?php echo date('d/m/Y', strtotime($news['created_at'])); ?></span>
                                <h3 class="news-title"><?php echo htmlspecialchars($news['title']); ?></h3>
                                <p class="news-desc"><?php echo nl2br(htmlspecialchars($news['content'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="background: var(--card-bg); padding: 40px; text-align: center; border-radius: 8px; color: var(--text-muted); border: 1px solid var(--border-color);">
                    <p>ยังไม่มีประกาศข่าวสารในขณะนี้</p>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <h2 class="section-title"><i class="fas fa-calendar-alt" style="color:var(--text-muted);"></i> ปฏิทินกิจกรรม</h2>
            <div class="event-panel">
                <?php if (count($events) > 0): ?>
                    <?php foreach ($events as $evt): 
                        $event_name = $evt['event_name'] ?? $evt['title'] ?? $evt['name'] ?? 'กิจกรรมของวัด';
                        $m_th = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
                        $d = date('d', strtotime($evt['event_date']));
                        $m = $m_th[(int)date('m', strtotime($evt['event_date']))];
                    ?>
                        <div class="event-item">
                            <div class="event-date-box">
                                <span class="d"><?php echo $d; ?></span>
                                <span class="m"><?php echo $m; ?></span>
                            </div>
                            <div class="event-detail">
                                <h4><?php echo htmlspecialchars($event_name); ?></h4>
                                <?php if(!empty($evt['event_time'])): ?>
                                    <p><i class="far fa-clock"></i> <?php echo date('H:i', strtotime($evt['event_time'])); ?> น.</p>
                                <?php endif; ?>
                                <?php if(!empty($evt['description'])): ?>
                                    <p style="margin-top:4px;"><?php echo htmlspecialchars($evt['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 20px 0;">
                        <p>ไม่มีกิจกรรมที่กำลังจะจัดขึ้น</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <a href="donate.php?temple_id=<?php echo (int)$id; ?>" class="btn-donate">
                <i class="fas fa-hand-holding-heart"></i> ร่วมทำบุญออนไลน์ผ่าน PromptPay
            </a>
        </div>
    </div>

    <?php if(!empty($temple['description'])): ?>
    <div class="history-panel">
        <h2 class="section-title"><i class="fas fa-book-open" style="color:var(--primary-color);"></i> ข้อมูลและประวัติที่น่าสนใจ</h2>
        <div class="history-content">
            <?php echo htmlspecialchars($temple['description']); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php foreach ($custom_sections as $section): ?>
        <section class="custom-section">
            <h2 class="section-title"><i class="fas fa-circle-info" style="color:var(--primary-color);"></i> <?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="custom-section-content"><?php echo nl2br(htmlspecialchars($section['content'], ENT_QUOTES, 'UTF-8')); ?></div>
        </section>
    <?php endforeach; ?>
    
</div>

</body>
</html>