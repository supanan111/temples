<?php

namespace Tests\Unit;

use Tests\TestCase;

require_once __DIR__ . '/../../auth_helpers.php';

class AuthHelperTest extends TestCase
{
    public function testSafeBookingReturnTargetWithValidQuery(): void
    {
        $valid1 = 'booking_form.php?item_id=5&type=pavilion';
        $this->assertEquals($valid1, safe_booking_return_target($valid1));

        $valid2 = 'booking_form.php';
        $this->assertEquals($valid2, safe_booking_return_target($valid2));

        $valid3 = 'booking_form.php?temple_id=1&type=fair_stall&selected_data=01,02';
        $this->assertEquals($valid3, safe_booking_return_target($valid3));
    }

    public function testSafeBookingReturnTargetRejectsExternalUrls(): void
    {
        $malicious1 = 'https://attacker.com/malicious';
        $this->assertEquals('', safe_booking_return_target($malicious1));

        $malicious2 = '//attacker.com/booking_form.php';
        $this->assertEquals('', safe_booking_return_target($malicious2));

        $malicious3 = 'javascript:alert(1)';
        $this->assertEquals('', safe_booking_return_target($malicious3));
    }

    public function testSafeBookingReturnTargetRejectsUnauthorizedFiles(): void
    {
        $admin = 'admin_dashboard.php';
        $this->assertEquals('', safe_booking_return_target($admin));

        $delete = 'delete_booking.php?id=1';
        $this->assertEquals('', safe_booking_return_target($delete));

        $empty = '';
        $this->assertEquals('', safe_booking_return_target($empty));
    }

    public function testSafeBookingReturnTargetRejectsNonStrings(): void
    {
        $this->assertEquals('', safe_booking_return_target(null));
        $this->assertEquals('', safe_booking_return_target(123));
        $this->assertEquals('', safe_booking_return_target(['booking_form.php']));
    }
}
