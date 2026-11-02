<?php
// convert_to_neon.php — Export MySQL temple_platform data to Neon PostgreSQL format

$pdo = new PDO('mysql:host=127.0.0.1;dbname=temple_platform;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function sqlVal($val) {
    if ($val === null) return 'NULL';
    if (is_numeric($val) && !is_string($val)) return (string)$val;
    // Handle string escape for Postgres (double single quotes)
    $escaped = str_replace("'", "''", (string)$val);
    return "'{$escaped}'";
}

$out = [];
$out[] = "-- ============================================================";
$out[] = "-- ThumWadThaiHub — Data Seed for Neon PostgreSQL";
$out[] = "-- Generated on: " . date('Y-m-d H:i:s');
$out[] = "-- Run this in Neon SQL Editor (https://console.neon.tech)";
$out[] = "-- Ensure database/schema_postgres_neon.sql is executed first!";
$out[] = "-- ============================================================";
$out[] = "";
$out[] = "BEGIN;";
$out[] = "";

// 1. temples
$stmt = $pdo->query("SELECT * FROM temples ORDER BY id ASC");
$temples = $stmt->fetchAll();
$out[] = "-- 1. TEMPLES (" . count($temples) . " rows)";
foreach ($temples as $r) {
    $id = (int)$r['id'];
    $name = sqlVal($r['name']);
    $province = sqlVal($r['province']);
    $district = sqlVal($r['district']);
    $address = sqlVal($r['address']);
    $description = sqlVal($r['description']);
    $tags = sqlVal($r['tags'] ?? null);
    $lat = $r['latitude'] !== null ? (float)$r['latitude'] : 'NULL';
    $lng = $r['longitude'] !== null ? (float)$r['longitude'] : 'NULL';
    $image = sqlVal($r['image']);
    $promptpay = sqlVal($r['promptpay_id'] ?? null);
    $badge = sqlVal($r['badge'] ?? 'แนะนำ');
    $created_at = sqlVal($r['created_at']);

    $out[] = "INSERT INTO temples (id, name, province, district, address, description, tags, latitude, longitude, image, promptpay_id, badge, created_at) VALUES ($id, $name, $province, $district, $address, $description, $tags, $lat, $lng, $image, $promptpay, $badge, $created_at) ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, province = EXCLUDED.province, district = EXCLUDED.district, address = EXCLUDED.address, description = EXCLUDED.description, tags = EXCLUDED.tags, latitude = EXCLUDED.latitude, longitude = EXCLUDED.longitude, image = EXCLUDED.image, promptpay_id = EXCLUDED.promptpay_id, badge = EXCLUDED.badge;";
}
$out[] = "";

// 2. temple_sections
$stmt = $pdo->query("SELECT * FROM temple_sections ORDER BY id ASC");
$sections = $stmt->fetchAll();
$out[] = "-- 2. TEMPLE_SECTIONS (" . count($sections) . " rows)";
foreach ($sections as $r) {
    $id = (int)$r['id'];
    $temple_id = (int)$r['temple_id'];
    $title = sqlVal($r['title']);
    $content = sqlVal($r['content']);
    $created_at = sqlVal(date('Y-m-d H:i:s'));

    $out[] = "INSERT INTO temple_sections (id, temple_id, title, content, created_at) VALUES ($id, $temple_id, $title, $content, $created_at) ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, content = EXCLUDED.content;";
}
$out[] = "";

// 3. users
$stmt = $pdo->query("SELECT * FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();
$out[] = "-- 3. USERS (" . count($users) . " rows)";
foreach ($users as $r) {
    $id = (int)$r['id'];
    $username = sqlVal($r['username']);
    $password = sqlVal($r['password']);
    $full_name = sqlVal($r['full_name']);
    $role = sqlVal($r['role']);
    $temple_id = $r['temple_id'] !== null ? (int)$r['temple_id'] : 'NULL';
    $profile_image = sqlVal($r['profile_image'] ?? null);
    $created_at = sqlVal($r['created_at']);

    $out[] = "INSERT INTO users (id, username, password, full_name, role, temple_id, profile_image, created_at) VALUES ($id, $username, $password, $full_name, $role, $temple_id, $profile_image, $created_at) ON CONFLICT (id) DO UPDATE SET username = EXCLUDED.username, password = EXCLUDED.password, full_name = EXCLUDED.full_name, role = EXCLUDED.role, temple_id = EXCLUDED.temple_id, profile_image = EXCLUDED.profile_image;";
}
$out[] = "";

// 4. bookable_items
$stmt = $pdo->query("SELECT * FROM bookable_items ORDER BY id ASC");
$items = $stmt->fetchAll();
$out[] = "-- 4. BOOKABLE_ITEMS (" . count($items) . " rows)";
foreach ($items as $r) {
    $id = (int)$r['id'];
    $temple_id = (int)$r['temple_id'];
    $item_name = sqlVal($r['item_name']);
    $item_type = sqlVal($r['item_type']);
    $desc = sqlVal($r['description'] ?? null);
    $price = (float)($r['price'] ?? 0.00);
    $total_qty = (int)($r['total_qty'] ?? 1);
    $is_active = sqlVal($r['is_active'] ?? 'yes');
    $created_at = sqlVal(date('Y-m-d H:i:s'));

    $out[] = "INSERT INTO bookable_items (id, temple_id, item_name, item_type, description, price, total_qty, is_active, created_at) VALUES ($id, $temple_id, $item_name, $item_type, $desc, $price, $total_qty, $is_active, $created_at) ON CONFLICT (id) DO UPDATE SET item_name = EXCLUDED.item_name, item_type = EXCLUDED.item_type, description = EXCLUDED.description, price = EXCLUDED.price, total_qty = EXCLUDED.total_qty, is_active = EXCLUDED.is_active;";
}
$out[] = "";

// 5. bookings
$stmt = $pdo->query("SELECT * FROM bookings ORDER BY id ASC");
$bookings = $stmt->fetchAll();
$out[] = "-- 5. BOOKINGS (" . count($bookings) . " rows)";
foreach ($bookings as $r) {
    $id = (int)$r['id'];
    $temple_id = (int)$r['temple_id'];
    $item_id = $r['item_id'] !== null ? (int)$r['item_id'] : 'NULL';
    $user_id = $r['user_id'] !== null ? (int)$r['user_id'] : 'NULL';
    $booking_date = sqlVal($r['booking_date']);
    $stall_no = sqlVal($r['stall_no'] ?? null);
    $qty_booked = (int)($r['qty_booked'] ?? 1);
    $status = sqlVal($r['status'] ?? 'pending');
    $contact_name = sqlVal($r['contact_name']);
    $contact_phone = sqlVal($r['contact_phone']);
    $admin_note = sqlVal($r['admin_note'] ?? null);
    $cancelled_by = sqlVal($r['cancelled_by'] ?? null);
    $cancelled_at = sqlVal($r['cancelled_at'] ?? null);
    $created_at = sqlVal($r['created_at']);

    $out[] = "INSERT INTO bookings (id, temple_id, item_id, user_id, booking_date, stall_no, qty_booked, status, contact_name, contact_phone, admin_note, cancelled_by, cancelled_at, created_at) VALUES ($id, $temple_id, $item_id, $user_id, $booking_date, $stall_no, $qty_booked, $status, $contact_name, $contact_phone, $admin_note, $cancelled_by, $cancelled_at, $created_at) ON CONFLICT (id) DO UPDATE SET status = EXCLUDED.status, contact_name = EXCLUDED.contact_name, contact_phone = EXCLUDED.contact_phone, admin_note = EXCLUDED.admin_note;";
}
$out[] = "";

// 6. donations
$stmt = $pdo->query("SELECT * FROM donations ORDER BY id ASC");
$donations = $stmt->fetchAll();
$out[] = "-- 6. DONATIONS (" . count($donations) . " rows)";
foreach ($donations as $r) {
    $id = (int)$r['id'];
    $temple_id = $r['temple_id'] !== null ? (int)$r['temple_id'] : 1;
    $amount = (float)$r['amount'];
    $donor_name = sqlVal($r['donor_name'] ?: 'ผู้ไม่ประสงค์ออกนาม');
    $anon = (int)($r['anonymous'] ?? ($r['donor_name'] === 'ผู้ไม่ประสงค์ออกนาม' ? 1 : 0));
    $tax = sqlVal($r['tax_deduction'] ?? 'no');
    $status = sqlVal($r['status'] ?? 'pending');
    $slip = sqlVal($r['slip_image'] ?? null);
    $receipt = sqlVal($r['receipt_no'] ?? null);
    $created_at = sqlVal($r['created_at']);

    $out[] = "INSERT INTO donations (id, temple_id, amount, donor_name, anonymous, tax_deduction, status, slip_image, receipt_no, created_at) VALUES ($id, $temple_id, $amount, $donor_name, $anon, $tax, $status, $slip, $receipt, $created_at) ON CONFLICT (id) DO UPDATE SET amount = EXCLUDED.amount, donor_name = EXCLUDED.donor_name, status = EXCLUDED.status, receipt_no = EXCLUDED.receipt_no, slip_image = EXCLUDED.slip_image;";
}
$out[] = "";

// 7. events
$stmt = $pdo->query("SELECT * FROM events ORDER BY id ASC");
$events = $stmt->fetchAll();
$out[] = "-- 7. EVENTS (" . count($events) . " rows)";
foreach ($events as $r) {
    $id = (int)$r['id'];
    $temple_id = (int)$r['temple_id'];
    $event_name = sqlVal($r['event_name']);
    $desc = sqlVal($r['description'] ?? null);
    $event_img = sqlVal($r['event_image'] ?? null);
    $event_date = sqlVal($r['event_date']);
    $event_time = sqlVal($r['event_time'] ?? null);
    $created_at = sqlVal($r['created_at']);

    $out[] = "INSERT INTO events (id, temple_id, event_name, description, event_image, event_date, event_time, created_at) VALUES ($id, $temple_id, $event_name, $desc, $event_img, $event_date, $event_time, $created_at) ON CONFLICT (id) DO UPDATE SET event_name = EXCLUDED.event_name, description = EXCLUDED.description, event_image = EXCLUDED.event_image, event_date = EXCLUDED.event_date, event_time = EXCLUDED.event_time;";
}
$out[] = "";

// 8. news
$stmt = $pdo->query("SELECT * FROM news ORDER BY id ASC");
$news = $stmt->fetchAll();
$out[] = "-- 8. NEWS (" . count($news) . " rows)";
foreach ($news as $r) {
    $id = (int)$r['id'];
    $temple_id = (int)$r['temple_id'];
    $title = sqlVal($r['title']);
    $content = sqlVal($r['content']);
    $img = sqlVal($r['image'] ?? null);
    $created_at = sqlVal($r['created_at']);

    $out[] = "INSERT INTO news (id, temple_id, title, content, image, created_at) VALUES ($id, $temple_id, $title, $content, $img, $created_at) ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, content = EXCLUDED.content, image = EXCLUDED.image;";
}
$out[] = "";

// 9. Reset Sequences
$out[] = "-- ============================================================";
$out[] = "-- 9. RESET SERIAL SEQUENCES";
$out[] = "-- ============================================================";
$tables = ['temples', 'temple_sections', 'users', 'bookable_items', 'bookings', 'donations', 'events', 'news', 'notifications'];
foreach ($tables as $t) {
    $out[] = "SELECT setval(pg_get_serial_sequence('$t', 'id'), COALESCE(MAX(id), 1)) FROM $t;";
}
$out[] = "";
$out[] = "COMMIT;";
$out[] = "";

$filePath = __DIR__ . '/database/data_seed_postgres_neon.sql';
file_put_contents($filePath, implode("\n", $out));
echo "Successfully generated $filePath with:\n";
echo "- Temples: " . count($temples) . "\n";
echo "- Temple Sections: " . count($sections) . "\n";
echo "- Users: " . count($users) . "\n";
echo "- Bookable Items: " . count($items) . "\n";
echo "- Bookings: " . count($bookings) . "\n";
echo "- Donations: " . count($donations) . "\n";
echo "- Events: " . count($events) . "\n";
echo "- News: " . count($news) . "\n";
