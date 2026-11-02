<?php
/**
 * ensure_schema.php — Auto migration for temple payment info & booking slip columns
 * Runs safely once or when schema version marker is missing.
 */
function ensure_payment_and_slip_schema(PDO $pdo): void
{
    static $executed = false;
    if ($executed) return;
    $executed = true;

    $cache_flag = __DIR__ . '/cache/.schema_payment_v1';
    if (file_exists($cache_flag)) return;

    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $pdo->exec("
                ALTER TABLE temples ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100);
                ALTER TABLE temples ADD COLUMN IF NOT EXISTS bank_account_no VARCHAR(50);
                ALTER TABLE temples ADD COLUMN IF NOT EXISTS bank_account_name VARCHAR(150);
                ALTER TABLE temples ADD COLUMN IF NOT EXISTS qr_code_image VARCHAR(255);

                ALTER TABLE bookings ADD COLUMN IF NOT EXISTS total_price DECIMAL(10, 2) DEFAULT 0.00;
                ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_status VARCHAR(30) DEFAULT 'unpaid';
                ALTER TABLE bookings ADD COLUMN IF NOT EXISTS slip_image VARCHAR(255);
                ALTER TABLE bookings ADD COLUMN IF NOT EXISTS slip_uploaded_at TIMESTAMP WITH TIME ZONE;
            ");
        } else {
            // MySQL fallback
            $temple_cols = $pdo->query("SHOW COLUMNS FROM temples")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('bank_name', $temple_cols, true)) {
                $pdo->exec("ALTER TABLE temples ADD COLUMN bank_name VARCHAR(100) NULL");
            }
            if (!in_array('bank_account_no', $temple_cols, true)) {
                $pdo->exec("ALTER TABLE temples ADD COLUMN bank_account_no VARCHAR(50) NULL");
            }
            if (!in_array('bank_account_name', $temple_cols, true)) {
                $pdo->exec("ALTER TABLE temples ADD COLUMN bank_account_name VARCHAR(150) NULL");
            }
            if (!in_array('qr_code_image', $temple_cols, true)) {
                $pdo->exec("ALTER TABLE temples ADD COLUMN qr_code_image VARCHAR(255) NULL");
            }

            $booking_cols = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('total_price', $booking_cols, true)) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN total_price DECIMAL(10, 2) DEFAULT 0.00");
            }
            if (!in_array('payment_status', $booking_cols, true)) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN payment_status VARCHAR(30) DEFAULT 'unpaid'");
            }
            if (!in_array('slip_image', $booking_cols, true)) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN slip_image VARCHAR(255) NULL");
            }
            if (!in_array('slip_uploaded_at', $booking_cols, true)) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN slip_uploaded_at DATETIME NULL");
            }
        }

        // Mark as executed
        if (!is_dir(__DIR__ . '/cache')) {
            @mkdir(__DIR__ . '/cache', 0775, true);
        }
        @touch($cache_flag);

    } catch (Throwable $e) {
        error_log("Schema migration error: " . $e->getMessage());
    }
}
