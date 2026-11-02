<?php
/**
 * db_config.php — Database Configuration
 * รองรับทั้ง Local XAMPP, Render, Vercel, และ Neon Database
 * สามารถตั้งค่าผ่าน Environment Variables หรือไฟล์ .env
 */

// โหลดไฟล์ .env ถ้ามีอยู่ (สำหรับ Local หรือ Custom Environment)
$env_file = __DIR__ . '/.env';
if (file_exists($env_file) && is_readable($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            if (!isset($_ENV[$k]) && getenv($k) === false) {
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
}

// 1. ตรวจสอบ DATABASE_URL (มาตรฐาน Render, Neon, Railway, Heroku)
$database_url = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? null);

if ($database_url) {
    $db_parts = parse_url($database_url);
    $driver   = strtolower($db_parts['scheme'] ?? 'mysql');
    if ($driver === 'postgres' || $driver === 'postgresql') {
        $driver = 'pgsql';
    }
    $host     = $db_parts['host'] ?? 'localhost';
    $port     = $db_parts['port'] ?? ($driver === 'pgsql' ? 5432 : 3306);
    $user     = $db_parts['user'] ?? '';
    $pass     = $db_parts['pass'] ?? '';
    $dbname   = ltrim($db_parts['path'] ?? '', '/');
    $query    = [];
    if (!empty($db_parts['query'])) {
        parse_str($db_parts['query'], $query);
    }
    $sslmode  = $query['sslmode'] ?? ($driver === 'pgsql' ? 'require' : null);

    if ($driver === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}" . ($sslmode ? ";sslmode={$sslmode}" : "");
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    }
} else {
    // 2. ตรวจสอบแยกแต่ละตัวแปร หรือใช้ค่า Default
    $driver = getenv('DB_DRIVER') ?: ($_ENV['DB_DRIVER'] ?? 'mysql');
    if ($driver === 'postgres' || $driver === 'postgresql') {
        $driver = 'pgsql';
    }
    $host   = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'sql107.infinityfree.com');
    $port   = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? ($driver === 'pgsql' ? 5432 : 3306));
    $dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'if0_43263069_temple_platform');
    $user   = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'if0_43263069');
    $pass   = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? 'T96Xb6WKTw');

    if ($driver === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    }
}

try {
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);

    // เคลียร์สถานะ Transaction ค้างสำหรับ PostgreSQL (PgBouncer Pooler) ให้พร้อมทำงานเสมอ
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
        try {
            $pdo->exec("ROLLBACK;");
        } catch (Throwable $e) {}
    }
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . htmlspecialchars($e->getMessage()));
}
