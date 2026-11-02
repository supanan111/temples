<?php

namespace Tests\Unit;

use Tests\TestCase;

class BookingValidationTest extends TestCase
{
    public function testPhoneNumberValidationPattern(): void
    {
        $pattern = '/^[0-9]{9,10}$/';

        // ถูกต้อง: 9-10 หลักตัวเลข
        $this->assertEquals(1, preg_match($pattern, '0812345678'));
        $this->assertEquals(1, preg_match($pattern, '021234567'));

        // ไม่ถูกต้อง: มีตัวอักษร, สั้นไป, ยาวไป, อักขระพิเศษ
        $this->assertEquals(0, preg_match($pattern, '081-234-5678'));
        $this->assertEquals(0, preg_match($pattern, '08123456')); // 8 หลัก
        $this->assertEquals(0, preg_match($pattern, '081234567890')); // 12 หลัก
        $this->assertEquals(0, preg_match($pattern, '081234567a'));
    }

    public function testSelectionTypeValidation(): void
    {
        $allowed = ['stalls', 'dates'];

        $this->assertTrue(in_array('stalls', $allowed, true));
        $this->assertTrue(in_array('dates', $allowed, true));
        $this->assertFalse(in_array('invalid', $allowed, true));
        $this->assertFalse(in_array('', $allowed, true));
    }

    public function testSelectedDataCsvParsing(): void
    {
        $rawStalls = '01, 02 , 05, , 09';
        $items = array_values(array_filter(array_map('trim', explode(',', $rawStalls)), static function ($v) {
            return $v !== '';
        }));

        $this->assertCount(4, $items);
        $this->assertEquals(['01', '02', '05', '09'], $items);
    }

    public function testStallPriceCalculation(): void
    {
        $unitPrice = 500.00;
        $selectedStalls = ['01', '02', '03'];
        $totalPrice = count($selectedStalls) * $unitPrice;

        $this->assertEquals(1500.00, $totalPrice);
    }

    public function testPavilionPriceCalculationWithMultipleDays(): void
    {
        $unitPrice = 1200.00;
        $selectedDates = ['2026-04-10', '2026-04-11', '2026-04-12'];
        $totalPrice = count($selectedDates) * $unitPrice;

        $this->assertEquals(3600.00, $totalPrice);
    }

    public function testBookingStatusEnumAllowedValues(): void
    {
        $allowedStatuses = ['pending', 'approved', 'rejected', 'confirmed', 'success', 'cancelled'];

        $this->assertTrue(in_array('pending', $allowedStatuses, true));
        $this->assertTrue(in_array('approved', $allowedStatuses, true));
        $this->assertTrue(in_array('cancelled', $allowedStatuses, true));
        $this->assertFalse(in_array('deleted', $allowedStatuses, true));
    }
}

