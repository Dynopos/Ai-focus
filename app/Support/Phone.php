<?php

namespace App\Support;

final class Phone
{
    /**
     * Normalise to WhatsApp wa_id form: digits only, country code, no "+".
     * Local Malaysian numbers ("012-345 6789") get the default country code.
     */
    public static function normalize(?string $phone, string $defaultCountryCode = '60'): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = $defaultCountryCode.substr($digits, 1);
        }

        return strlen($digits) >= 8 ? $digits : null;
    }

    public static function mask(?string $phone): string
    {
        if (! $phone) {
            return '-';
        }

        return substr($phone, 0, 4).str_repeat('•', max(0, strlen($phone) - 7)).substr($phone, -3);
    }
}
