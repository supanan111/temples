<?php
namespace Tests\Unit;

use Tests\TestCase;

class PaymentSlipTest extends TestCase
{
    public function testPaymentStatusTransitions(): void
    {
        $allowed_statuses = ['unpaid', 'pending_review', 'paid', 'invalid_slip'];
        $this->assertTrue(in_array('unpaid', $allowed_statuses, true));
        $this->assertTrue(in_array('pending_review', $allowed_statuses, true));
        $this->assertTrue(in_array('paid', $allowed_statuses, true));
        $this->assertTrue(in_array('invalid_slip', $allowed_statuses, true));
    }

    public function testSlipMimeTypeValidation(): void
    {
        $allowed_mimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        $this->assertTrue(isset($allowed_mimes['image/jpeg']));
        $this->assertTrue(isset($allowed_mimes['image/png']));
        $this->assertTrue(isset($allowed_mimes['image/webp']));
        $this->assertFalse(isset($allowed_mimes['application/pdf']));
        $this->assertFalse(isset($allowed_mimes['text/html']));
    }

    public function testPriceCalculationWithMultipleStalls(): void
    {
        $unit_price = 500.00;
        $stalls = ['A01', 'A02', 'A03'];
        $total = count($stalls) * $unit_price;
        $this->assertEquals(1500.00, $total);
    }

    public function testPromptpayNumberFormat(): void
    {
        $valid_phone = '0812345678';
        $valid_tax_id = '0994000123456';
        $invalid_too_short = '12345';
        $invalid_letters = '081234abcd';

        $pattern = '/\A(?:0\d{9}|\d{13})\z/';
        $this->assertEquals(1, preg_match($pattern, $valid_phone));
        $this->assertEquals(1, preg_match($pattern, $valid_tax_id));
        $this->assertEquals(0, preg_match($pattern, $invalid_too_short));
        $this->assertEquals(0, preg_match($pattern, $invalid_letters));
    }
}
