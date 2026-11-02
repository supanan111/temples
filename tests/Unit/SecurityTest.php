<?php

namespace Tests\Unit;

use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function testPasswordHashAndVerify(): void
    {
        $rawPassword = 'SecurePassword123!';
        $hashed = password_hash($rawPassword, PASSWORD_DEFAULT);

        $this->assertNotEquals($rawPassword, $hashed);
        $this->assertTrue(password_verify($rawPassword, $hashed));
        $this->assertFalse(password_verify('WrongPassword', $hashed));
    }

    public function testCsrfTokenGenerationAndVerification(): void
    {
        $token1 = bin2hex(random_bytes(16));
        $token2 = bin2hex(random_bytes(16));

        $this->assertEquals(32, strlen($token1));
        $this->assertNotEquals($token1, $token2);

        // ตรวจสอบ timing-safe string comparison ด้วย hash_equals
        $this->assertTrue(hash_equals($token1, $token1));
        $this->assertFalse(hash_equals($token1, $token2));
        $this->assertFalse(hash_equals($token1, 'invalid_token'));
    }

    public function testImageUploadMimeTypeWhitelist(): void
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        $this->assertTrue(in_array('image/jpeg', $allowedMimes, true));
        $this->assertTrue(in_array('image/png', $allowedMimes, true));
        $this->assertTrue(in_array('image/webp', $allowedMimes, true));

        // ต้องบล็อก executable / script mime types
        $this->assertFalse(in_array('application/x-php', $allowedMimes, true));
        $this->assertFalse(in_array('application/octet-stream', $allowedMimes, true));
        $this->assertFalse(in_array('text/html', $allowedMimes, true));
    }

    public function testFileExtensionValidation(): void
    {
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        $testFiles = [
            'photo.jpg'      => true,
            'image.PNG'      => true,
            'banner.webp'    => true,
            'exploit.php'    => false,
            'shell.php.jpg'  => false, // double extension
            'script.js'      => false,
            'payload.exe'    => false,
        ];

        foreach ($testFiles as $filename => $shouldBeValid) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            // ป้องกัน double extension เช่น shell.php.jpg -> ตรวจสอบว่าไม่มี .php ในชื่อไฟล์
            $hasPhpInName = (bool)preg_match('/\.php(\.|\z)/i', $filename);
            $isValid = in_array($ext, $allowedExts, true) && !$hasPhpInName;

            $this->assertEquals($shouldBeValid, $isValid, "Failed validation for file: {$filename}");
        }
    }
}
