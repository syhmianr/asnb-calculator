<?php

if (! function_exists('rm')) {
    /** Format an amount as Malaysian Ringgit, e.g. RM 1,234.50 */
    function rm(mixed $amount, int $decimals = 2): string
    {
        $amount = is_numeric($amount) ? (float) $amount : 0.0;

        return ($amount < 0 ? '-' : '') . "RM\u{00A0}" . number_format(abs($amount), $decimals);
    }
}

if (! function_exists('pct')) {
    function pct(mixed $rate): string
    {
        return number_format((float) $rate, 2) . '%';
    }
}
