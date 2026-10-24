<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event_name = $_POST['event_name'];
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $description = $_POST['description'];

    try {
        $stmt = $pdo->prepare("INSERT INTO events (event_name, description, event_date, event_time) VALUES (:event_name, :description, :event_date, :event_time)");
        $stmt->execute([
            ':event_name' => $event_name,
            ':description' => $description,
            ':event_date' => $event_date,
            ':event_time' => $event_time
        ]);
        
        echo "<script>alert('เพิ่มกิจกรรมลงในปฏิทินสำเร็จ!'); window.location.href = 'manage_events.php';</script>";
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
}
?>