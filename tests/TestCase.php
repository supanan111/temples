<?php
/**
 * tests/TestCase.php — Lightweight Base Test Case for Unit Testing
 */

namespace Tests;

use PDO;
use Exception;
use AssertionError;

abstract class TestCase
{
    private int $assertionsCount = 0;

    public function getAssertionsCount(): int
    {
        return $this->assertionsCount;
    }

    protected function setUp(): void
    {
        // Hook for subclasses before each test
    }

    protected function tearDown(): void
    {
        // Hook for subclasses after each test
    }

    /**
     * สร้าง In-Memory SQLite Database สำหรับทดสอบ Database Queries
     */
    protected function createInMemoryDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // สร้างตารางจำลองที่จำเป็นสำหรับการทดสอบ
        $pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                full_name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'user',
                temple_id INTEGER NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE temples (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                province TEXT,
                district TEXT,
                address TEXT,
                description TEXT,
                tags TEXT,
                promptpay_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                ref_id INTEGER,
                ref_table TEXT,
                message TEXT NOT NULL,
                is_read INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE bookable_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                temple_id INTEGER NOT NULL,
                item_name TEXT NOT NULL,
                item_type TEXT NOT NULL,
                price REAL NOT NULL DEFAULT 0,
                total_qty INTEGER NOT NULL DEFAULT 1,
                is_active TEXT NOT NULL DEFAULT 'yes'
            );

            CREATE TABLE bookings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                temple_id INTEGER NOT NULL,
                item_id INTEGER NOT NULL,
                user_id INTEGER NULL,
                booking_date TEXT NOT NULL,
                stall_no TEXT,
                qty_booked INTEGER NOT NULL DEFAULT 1,
                status TEXT NOT NULL DEFAULT 'pending',
                contact_name TEXT NOT NULL,
                contact_phone TEXT NOT NULL,
                admin_note TEXT,
                cancelled_by TEXT,
                cancelled_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE donations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                temple_id INTEGER,
                amount REAL NOT NULL,
                donor_name TEXT NOT NULL DEFAULT 'ผู้ไม่ประสงค์ออกนาม',
                anonymous INTEGER NOT NULL DEFAULT 0,
                tax_deduction TEXT NOT NULL DEFAULT 'no',
                status TEXT NOT NULL DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        return $pdo;
    }

    public function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertionsCount++;
        if ($expected !== $actual) {
            $expectedStr = var_export($expected, true);
            $actualStr = var_export($actual, true);
            $msg = $message ?: "Expected {$expectedStr}, but got {$actualStr}";
            throw new AssertionError($msg);
        }
    }

    public function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertionsCount++;
        if ($expected === $actual) {
            $msg = $message ?: "Expected value not to equal " . var_export($expected, true);
            throw new AssertionError($msg);
        }
    }

    public function assertTrue(mixed $condition, string $message = ''): void
    {
        $this->assertionsCount++;
        if ($condition !== true) {
            throw new AssertionError($message ?: "Failed asserting that condition is true.");
        }
    }

    public function assertFalse(mixed $condition, string $message = ''): void
    {
        $this->assertionsCount++;
        if ($condition !== false) {
            throw new AssertionError($message ?: "Failed asserting that condition is false.");
        }
    }

    public function assertNull(mixed $actual, string $message = ''): void
    {
        $this->assertionsCount++;
        if ($actual !== null) {
            throw new AssertionError($message ?: "Expected null, but got " . var_export($actual, true));
        }
    }

    public function assertNotNull(mixed $actual, string $message = ''): void
    {
        $this->assertionsCount++;
        if ($actual === null) {
            throw new AssertionError($message ?: "Failed asserting that value is not null.");
        }
    }

    public function assertCount(int $expectedCount, countable|array $array, string $message = ''): void
    {
        $this->assertionsCount++;
        $actualCount = count($array);
        if ($actualCount !== $expectedCount) {
            throw new AssertionError($message ?: "Expected count {$expectedCount}, got {$actualCount}");
        }
    }

    public function assertContains(mixed $needle, array $haystack, string $message = ''): void
    {
        $this->assertionsCount++;
        if (!in_array($needle, $haystack, true)) {
            throw new AssertionError($message ?: "Failed asserting that array contains " . var_export($needle, true));
        }
    }

    public function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        $this->assertionsCount++;
        if (preg_match($pattern, $string) !== 1) {
            throw new AssertionError($message ?: "String '{$string}' does not match pattern '{$pattern}'");
        }
    }
}

