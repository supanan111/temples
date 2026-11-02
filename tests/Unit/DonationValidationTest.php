<?php

namespace Tests\Unit;

use Tests\TestCase;

class DonationValidationTest extends TestCase
{
    public function testDonationAmountRegexValidation(): void
    {
        $pattern = '/\A\d{1,8}(?:\.\d{1,2})?\z/';

        // ถูกต้อง
        $this->assertEquals(1, preg_match($pattern, '100'));
        $this->assertEquals(1, preg_match($pattern, '500.50'));
        $this->assertEquals(1, preg_match($pattern, '1000.00'));
        $this->assertEquals(1, preg_match($pattern, '99999999.99'));

        // ไม่ถูกต้อง
        $this->assertEquals(0, preg_match($pattern, '-50'));
        $this->assertEquals(0, preg_match($pattern, 'abc'));
        $this->assertEquals(0, preg_match($pattern, '100.555')); // ทศนิยมเกิน 2 ตำแหน่ง
        $this->assertEquals(0, preg_match($pattern, '100,000')); // มีลูกน้ำ
        $this->assertEquals(0, preg_match($pattern, ''));
    }

    public function testAnonymousDonorOverride(): void
    {
        // กรณีติ๊กเลือกไม่ประสงค์ออกนาม
        $anonymousInput = 'yes';
        $donorNameInput = 'นายสมชาย ใจดี';

        $donorName = ($anonymousInput === 'yes' || trim($donorNameInput) === '')
            ? 'ผู้ไม่ประสงค์ออกนาม'
            : trim($donorNameInput);

        $this->assertEquals('ผู้ไม่ประสงค์ออกนาม', $donorName);

        // กรณีไม่ติ๊กนิรนาม และระบุชื่อ
        $anonymousInput = 'no';
        $donorNameInput = 'นายสมชาย ใจดี';

        $donorName = ($anonymousInput === 'yes' || trim($donorNameInput) === '')
            ? 'ผู้ไม่ประสงค์ออกนาม'
            : trim($donorNameInput);

        $this->assertEquals('นายสมชาย ใจดี', $donorName);
    }

    public function testTaxDeductionValueOptions(): void
    {
        $allowedTaxOptions = ['yes', 'no', ''];

        $this->assertTrue(in_array('yes', $allowedTaxOptions, true));
        $this->assertTrue(in_array('no', $allowedTaxOptions, true));
        $this->assertFalse(in_array('maybe', $allowedTaxOptions, true));
    }

    public function testPromptpayNumberValidation(): void
    {
        $pattern = '/\A(?:0\d{9}|\d{13})\z/';

        // เบอร์โทรศัพท์ 10 หลักขึ้นต้นด้วย 0
        $this->assertEquals(1, preg_match($pattern, '0812345678'));
        $this->assertEquals(1, preg_match($pattern, '0999999999'));

        // เลขประจำตัวประชาชน / นิติบุคคล 13 หลัก
        $this->assertEquals(1, preg_match($pattern, '1234567890123'));

        // ไม่ถูกต้อง
        $this->assertEquals(0, preg_match($pattern, '081-234-5678')); // มีขีด
        $this->assertEquals(0, preg_match($pattern, '1812345678')); // 10 หลักแต่ไม่ขึ้นด้วย 0
        $this->assertEquals(0, preg_match($pattern, '12345')); // สั้นไป
    }
}
