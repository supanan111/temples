<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'unauthorized', 'count' => 0, 'unread_count' => 0, 'items' => [], 'notifications' => []]);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

try {
    if ($action === 'mark_read' && isset($_GET['id'])) {
        $notif_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($notif_id) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notif_id, $user_id]);
        }
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'mark_all_read') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
        echo json_encode(['success' => true]);
        exit();
    }

    // default: list notifications
    $stmt = $pdo->prepare(
        "SELECT id, type, ref_id, ref_table, message, is_read, created_at 
         FROM notifications 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 20"
    );
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unread_count = 0;
    foreach ($items as &$item) {
        $item['is_read'] = (bool)$item['is_read'];
        if (!$item['is_read']) $unread_count++;
        // format time
        $ts = strtotime($item['created_at']);
        $diff = time() - $ts;
        if ($diff < 60) $item['time_ago'] = 'เมื่อกี้';
        elseif ($diff < 3600) $item['time_ago'] = floor($diff/60) . ' นาทีที่แล้ว';
        elseif ($diff < 86400) $item['time_ago'] = floor($diff/3600) . ' ชั่วโมงที่แล้ว';
        else $item['time_ago'] = date('d/m/Y', $ts);
    }

    echo json_encode([
        'count'         => $unread_count,
        'unread_count'  => $unread_count,
        'items'         => $items,
        'notifications' => $items,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error', 'count' => 0, 'unread_count' => 0, 'items' => [], 'notifications' => []]);
}
