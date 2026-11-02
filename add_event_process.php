<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $temple_id = $_SESSION['temple_id'];
    $event_name = trim($_POST['event_name']);
    $event_date = trim($_POST['event_date']);
    $event_time = !empty($_POST['event_time']) ? trim($_POST['event_time']) : null;
    $description = trim($_POST['description'] ?? '');

    try {
        $next_event_id = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM events")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO events (id, temple_id, event_name, description, event_date, event_time) VALUES (:id, :temple_id, :event_name, :description, :event_date, :event_time)");
        $stmt->execute([
            ':id' => $next_event_id,
            ':temple_id' => $temple_id,
            ':event_name' => $event_name,
            ':description' => $description,
            ':event_date' => $event_date,
            ':event_time' => $event_time
        ]);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            try {
                $pdo->exec("SELECT setval(pg_get_serial_sequence('events', 'id'), (SELECT COALESCE(MAX(id), 1) FROM events))");
            } catch (Throwable $t) {}
        }
        
        echo "<script>alert('เพิ่มกิจกรรมลงในปฏิทินสำเร็จ!'); window.location.href = 'manage_events.php';</script>";
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
}
?>