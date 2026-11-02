<?php
function ensure_temple_sections_table($pdo)
{
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS temple_sections (
                    id SERIAL PRIMARY KEY,
                    temple_id INT NOT NULL,
                    title VARCHAR(150) NOT NULL,
                    content TEXT NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_temple_sections_temple_order ON temple_sections(temple_id, sort_order, id);
            ");
        } else {
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
    } catch (PDOException $e) {
        // Log warning but do not crash page if table already exists
        error_log("ensure_temple_sections_table warning: " . $e->getMessage());
    }
}

function get_temple_sections($pdo, $temple_id)
{
    try {
        $stmt = $pdo->prepare("SELECT title, content FROM temple_sections WHERE temple_id = ? ORDER BY sort_order, id");
        $stmt->execute([$temple_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
