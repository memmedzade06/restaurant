<?php

namespace App\Support;

/**
 * Azərbaycan mobil nömrələrini vahid formata salır: +994XXXXXXXXX
 */
class Phone
{
    /** Mobil operator kodları */
    private const PREFIXES = ['10', '50', '51', '55', '60', '70', '77', '99'];

    /**
     * "050 123 45 67", "+994 50 123-45-67", "994501234567" → "+994501234567".
     * Tanınmayan nömrə üçün null qaytarır.
     */
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);

        if (str_starts_with($digits, '994')) {
            $digits = substr($digits, 3);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 9 || ! in_array(substr($digits, 0, 2), self::PREFIXES, true)) {
            return null;
        }

        return '+994' . $digits;
    }

    /** "+994501234567" → "+994 50 123 45 67" */
    public static function format(?string $phone): string
    {
        if (! $phone || ! preg_match('/^\+994(\d{2})(\d{3})(\d{2})(\d{2})$/', $phone, $m)) {
            return (string) $phone;
        }

        return "+994 {$m[1]} {$m[2]} {$m[3]} {$m[4]}";
    }
}
