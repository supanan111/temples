<?php
/**
 * seed_all_temples.php — Automatic Database Seeder for All 80 Temples
 * - Can be called directly via web browser: https://domain/seed_all_temples.php
 * - Can be run via CLI: php seed_all_temples.php
 * - Can be included and called via run_temple_seeder($pdo, true)
 */

if (!defined('SEEDER_INCLUDED')) {
    define('SEEDER_INCLUDED', true);
}

require_once __DIR__ . '/db_config.php';

function run_temple_seeder(PDO $pdo, bool $silent = false): array
{
    $report = [
        'temples_count' => 0,
        'items_added'   => 0,
        'events_added'  => 0,
        'news_added'    => 0,
        'error'         => null
    ];

    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $is_pgsql = ($driver === 'pgsql');

        // ดึงรายชื่อวัดทั้งหมด
        $stmt_t = $pdo->query("SELECT id, name FROM temples ORDER BY id ASC");
        $all_temples = $stmt_t->fetchAll(PDO::FETCH_KEY_PAIR);

        if (empty($all_temples)) {
            $report['error'] = 'ไม่พบข้อมูลวัดในตาราง temples กรุณานำเข้า schema และข้อมูลวัดก่อน';
            return $report;
        }

        $report['temples_count'] = count($all_temples);

        // หา ID สูงสุดปัจจุบันของแต่ละตาราง
        $next_item_id  = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM bookable_items")->fetchColumn();
        $next_event_id = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM events")->fetchColumn();
        $next_news_id  = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM news")->fetchColumn();

        foreach ($all_temples as $tid => $tname) {
            $tid = (int)$tid;

            // 1. ตรวจสอบ bookable_items ของวัดนี้
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM bookable_items WHERE temple_id = ?");
            $stmt_chk->execute([$tid]);
            $has_items = (int)$stmt_chk->fetchColumn();

            if ($has_items === 0) {
                // สุ่มราคาตามความเหมาะสม
                $p_funeral  = 1000 + ($tid % 4) * 500;
                $p_merit    = ($tid % 3 === 0) ? 0 : 800 + ($tid % 3) * 200;
                $p_equip    = 200 + ($tid % 3) * 100;
                $p_stall    = 300 + ($tid % 3) * 150;
                $stalls_qty = 15 + ($tid % 3) * 5;

                $items_to_add = [
                    ["ศาลา 1 เมรุ/พิธีสวดอภิธรรม ({$tname})", "pavilion_funeral", "ศาลาปรับอากาศสำหรับประกอบพิธีสวดอภิธรรมและฌาปนกิจ พร้อมอุปกรณ์อำนวยความสะดวกครบครัน", $p_funeral, 1],
                    ["ศาลาการเปรียญ ทำบุญเลี้ยงพระ/พิธีมงคล", "pavilion_merit", "ศาลาทรงไทยกว้างขวาง เหมาะสำหรับจัดงานทำบุญครบรอบ งานมงคลสมรส หรืองานบุญอุทิศส่วนกุศล", $p_merit, 1],
                    ["นิมนต์พระสงฆ์ 9 รูป ประกอบพิธีมงคล", "monk", "นิมนต์พระสงฆ์เจริญพระพุทธมนต์ ฉันภัตตาหารเพล/เช้า ทำบุญบ้าน และพิธีมงคลต่างๆ", 0, 1],
                    ["ชุดโต๊ะหมู่บูชา เต็นท์พิธี และเก้าอี้ 100 ตัว", "equipment", "ยืมอุปกรณ์สำหรับจัดงานพิธี ชุดโต๊ะหมู่บูชา อาสนะสงฆ์ เต็นท์ผ้าใบ และเก้าอี้พลาสติกพร้อมผ้าคลุม", $p_equip, 5],
                    ["ล็อคจำหน่ายสินค้า งานประจำปี/งานวัด (โซน A)", "fair_stall", "พื้นที่ขนาด 3x3 เมตร สำหรับตั้งร้านจำหน่ายอาหาร ขนม สินค้าชุมชน และของที่ระลึกในงานวัด", $p_stall, $stalls_qty],
                ];

                foreach ($items_to_add as $it) {
                    if ($is_pgsql) {
                        $stmt_ins = $pdo->prepare("INSERT INTO bookable_items (id, temple_id, item_name, item_type, description, price, total_qty, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 'yes')");
                        $stmt_ins->execute([$next_item_id++, $tid, $it[0], $it[1], $it[2], $it[3], $it[4]]);
                    } else {
                        $stmt_ins = $pdo->prepare("INSERT INTO bookable_items (temple_id, item_name, item_type, description, price, total_qty, is_active) VALUES (?, ?, ?, ?, ?, ?, 'yes')");
                        $stmt_ins->execute([$tid, $it[0], $it[1], $it[2], $it[3], $it[4]]);
                    }
                    $report['items_added']++;
                }
            }

            // 2. ตรวจสอบ events ของวัดนี้
            $stmt_chk_ev = $pdo->prepare("SELECT COUNT(*) FROM events WHERE temple_id = ?");
            $stmt_chk_ev->execute([$tid]);
            $has_events = (int)$stmt_chk_ev->fetchColumn();

            if ($has_events === 0) {
                $events_to_add = [
                    ["งานทำบุญตักบาตรและเวียนเทียน วันสำคัญทางพระพุทธศาสนา", "ขอเชิญพุทธศาสนิกชนร่วมทำบุญตักบาตรข้าวสารอาหารแห้ง ฟังพระธรรมเทศนา และเวียนเทียนรอบพระอุโบสถ", "2026-11-15", "07:00:00"],
                    ["งานประเพณีทอดกฐินสามัคคีประจำปี ({$tname})", "ขอเชิญร่วมเป็นเจ้าภาพทอดกฐินสามัคคี เพื่อสมทบทุนบูรณปฏิสังขรณ์เสนาสนะและถาวรวัตถุภายในวัด", "2026-11-28", "09:30:00"],
                    ["พิธีสวดมนต์ข้ามปี เจริญจิตภาวนา เสริมสิริมงคลชีวิต", "ร่วมส่งท้ายปีเก่าต้อนรับปีใหม่ ด้วยการสวดมนต์ข้ามปี รับฟังธรรมโอวาท และรับน้ำพระพุทธมนต์เพื่อความเป็นสิริมงคล", "2026-12-31", "22:00:00"],
                    ["งานประจำปี นมัสการปิดทองพระพุทธรูปศักดิ์สิทธิ์", "งานสมโภชปิดทองไหว้พระ ขอพรสิ่งศักดิ์สิทธิ์ประจำวัด พร้อมการออกร้านจำหน่ายสินค้าชุมชนและการแสดงมหรสพสมโภช", "2027-02-20", "08:00:00"],
                ];

                foreach ($events_to_add as $ev) {
                    if ($is_pgsql) {
                        $stmt_ins = $pdo->prepare("INSERT INTO events (id, temple_id, event_name, description, event_date, event_time) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt_ins->execute([$next_event_id++, $tid, $ev[0], $ev[1], $ev[2], $ev[3]]);
                    } else {
                        $stmt_ins = $pdo->prepare("INSERT INTO events (temple_id, event_name, description, event_date, event_time) VALUES (?, ?, ?, ?, ?)");
                        $stmt_ins->execute([$tid, $ev[0], $ev[1], $ev[2], $ev[3]]);
                    }
                    $report['events_added']++;
                }
            }

            // 3. ตรวจสอบ news ของวัดนี้
            $stmt_chk_nw = $pdo->prepare("SELECT COUNT(*) FROM news WHERE temple_id = ?");
            $stmt_chk_nw->execute([$tid]);
            $has_news = (int)$stmt_chk_nw->fetchColumn();

            if ($has_news === 0) {
                $news_to_add = [
                    ["ขอเชิญร่วมทำบุญและร่วมกิจกรรมทางพระพุทธศาสนา ณ {$tname}", "ทางวัดขอเชิญชวนพุทธศาสนิกชนและสาธุชนทุกท่าน ร่วมทำบุญ ตักบาตร รักษาศีล และเจริญจิตตภาวนา ณ บริเวณพระวิหารและพระอุโบสถ สามารถเดินทางมาร่วมบุญได้ทุกวัน"],
                    ["ประกาศกำหนดการและเวลาเปิด-ปิดการเข้าสักการะ", "เพื่อความสะดวกและความเป็นระเบียบเรียบร้อย ทางวัดเปิดให้พุทธศาสนิกชนเข้าสักการะพระประธานและเยี่ยมชมโบราณสถาน ตั้งแต่เวลา 07.00 น. ถึง 18.00 น. ของทุกวัน"],
                    ["โครงการบูรณปฏิสังขรณ์เสนาสนะและถาวรวัตถุวัด", "ทางวัดกำลังดำเนินการบูรณปฏิสังขรณ์หลังคาพระอุโบสถและศาลาการเปรียญ ขอเชิญผู้มีจิตศรัทธาร่วมทำบุญตามกำลังศรัทธา ผ่านระบบ E-Donation ของทางวัดได้ตลอด 24 ชั่วโมง"]
                ];

                foreach ($news_to_add as $nw) {
                    if ($is_pgsql) {
                        $stmt_ins = $pdo->prepare("INSERT INTO news (id, temple_id, title, content) VALUES (?, ?, ?, ?)");
                        $stmt_ins->execute([$next_news_id++, $tid, $nw[0], $nw[1]]);
                    } else {
                        $stmt_ins = $pdo->prepare("INSERT INTO news (temple_id, title, content) VALUES (?, ?, ?)");
                        $stmt_ins->execute([$tid, $nw[0], $nw[1]]);
                    }
                    $report['news_added']++;
                }
            }
        }

        // ซิงค์ sequence ถ้าเป็น PostgreSQL
        if ($is_pgsql) {
            try {
                $pdo->exec("SELECT setval(pg_get_serial_sequence('bookable_items', 'id'), (SELECT COALESCE(MAX(id), 1) FROM bookable_items))");
                $pdo->exec("SELECT setval(pg_get_serial_sequence('events', 'id'), (SELECT COALESCE(MAX(id), 1) FROM events))");
                $pdo->exec("SELECT setval(pg_get_serial_sequence('news', 'id'), (SELECT COALESCE(MAX(id), 1) FROM news))");
            } catch (Exception $e) {}
        }

    } catch (Exception $e) {
        $report['error'] = $e->getMessage();
    }

    return $report;
}

// ถ้าถูกเรียกตรงๆ ผ่าน Web หรือ CLI ให้แสดงผล
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>Seeding Temple Data</title>';
    echo '<style>body{font-family:sans-serif;padding:24px;background:#f8fafc;color:#1e293b;} .card{background:#fff;padding:28px;border-radius:16px;box-shadow:0 6px 20px rgba(0,0,0,0.06);max-width:800px;margin:auto;} .success{color:#16a34a;font-weight:bold;font-size:16px;} .info{color:#0284c7;} ul{line-height:1.8;} a.btn{display:inline-block;padding:12px 24px;background:#c08221;color:#fff;border-radius:10px;text-decoration:none;font-weight:bold;margin-top:16px;}</style></head><body><div class="card">';
    echo '<h2>🌸 ระบบนำเข้าข้อมูลตัวอย่างสำหรับทุกวัด (ThumWadThaiHub)</h2>';

    $report = run_temple_seeder($pdo, false);

    if (!empty($report['error'])) {
        echo '<p style="color:#ef4444;font-weight:bold;">เกิดข้อผิดพลาด: ' . htmlspecialchars($report['error']) . '</p>';
    } else {
        echo '<p class="info">พบวัดในระบบทั้งหมด <strong>' . $report['temples_count'] . '</strong> วัด</p>';
        echo '<p class="success">✅ ดำเนินการตรวจสอบและนำเข้าข้อมูลสำเร็จเรียบร้อยแล้ว!</p>';
        echo '<ul>';
        echo '<li>บริการและการจอง (bookable_items): <strong>+' . $report['items_added'] . '</strong> รายการ (ศาลา, นิมนต์พระ, ยืมอุปกรณ์, ล็อคงานวัด)</li>';
        echo '<li>กิจกรรมวัด (events): <strong>+' . $report['events_added'] . '</strong> รายการ (งานกฐิน, ทำบุญตักบาตร, สวดมนต์ข้ามปี, งานประจำปี)</li>';
        echo '<li>ข่าวสารประชาสัมพันธ์ (news): <strong>+' . $report['news_added'] . '</strong> รายการ</li>';
        echo '</ul>';
        echo '<p><a href="index.php" class="btn">เข้าสู่หน้าแรกเพื่อทดลองใช้งาน</a></p>';
    }

    echo '</div></body></html>';
}
