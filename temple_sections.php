<?php
function ensure_temple_sections_table($pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS temple_sections (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            temple_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            content TEXT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            INDEX idx_temple_sections_temple_order (temple_id, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function get_temple_sections($pdo, $temple_id)
{
    $stmt = $pdo->prepare("SELECT title, content FROM temple_sections WHERE temple_id = ? ORDER BY sort_order, id");
    $stmt->execute([$temple_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
