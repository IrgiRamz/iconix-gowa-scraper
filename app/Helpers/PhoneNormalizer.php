<?php

namespace App\Helpers;

/**
 * Helper untuk normalisasi nomor telepon ke format WhatsApp JID.
 */
class PhoneNormalizer
{
    /**
     * Normalisasi nomor HP ke format JID WhatsApp.
     *
     * Aturan:
     * - Hapus semua karakter non-digit
     * - Ganti awalan "08" menjadi "628"
     *
     * @param  string|null  $phone
     * @return string
     */
    public static function normalize(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Hapus semua karakter non-digit
        $digits = preg_replace('/\D/', '', $phone);

        if (empty($digits)) {
            return '';
        }

        // Hapus leading zero jika ada
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        // Pastikan tidak ada leading +
        $digits = ltrim($digits, '+');

        return $digits;
    }

    /**
     * Format nomor ke JID WhatsApp (dengan @s.whatsapp.net).
     *
     * @param  string|null  $phone
     * @return string
     */
    public static function toJid(?string $phone): string
    {
        $normalized = self::normalize($phone);

        if (empty($normalized)) {
            return '';
        }

        return $normalized . '@s.whatsapp.net';
    }

    /**
     * Cek apakah nomor valid (minimal 10 digit).
     *
     * @param  string|null  $phone
     * @return bool
     */
    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);

        return strlen($normalized) >= 10 && strlen($normalized) <= 15;
    }
}
