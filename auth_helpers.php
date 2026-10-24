<?php
function safe_booking_return_target($target)
{
    if (!is_string($target) || !preg_match('/\Abooking_form\.php(?:\?[A-Za-z0-9_.~%=&,-]*)?\z/', $target)) {
        return '';
    }

    return $target;
}
