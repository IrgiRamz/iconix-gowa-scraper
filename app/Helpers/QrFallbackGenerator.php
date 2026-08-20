<?php

namespace App\Helpers;

use Illuminate\Http\Response;

/**
 * Generator gambar fallback "QR TIDAK TERSEDIA".
 *
 * Menghasilkan gambar PNG sederhana bertuliskan "QR TIDAK TERSEDIA"
 * menggunakan native PHP (tanpa dependency GD tambahan jika tidak tersedia).
 */
class QrFallbackGenerator
{
    /**
     * Generate gambar PNG fallback dan return sebagai Response.
     *
     * @return \Illuminate\Http\Response
     */
    public static function generate(): Response
    {
        $text = 'QR TIDAK TERSEDIA';
        $width = 300;
        $height = 100;

        // Jika GD tersedia, gunakan untuk hasil lebih bagus
        if (extension_loaded('gd')) {
            return self::generateWithGd($text, $width, $height);
        }

        // Fallback: generate SVG inline
        return self::generateSvgFallback($text, $width, $height);
    }

    /**
     * Generate dengan GD library.
     */
    private static function generateWithGd(string $text, int $width, int $height): Response
    {
        $image = imagecreatetruecolor($width, $height);

        // Warna
        $bgColor = imagecolorallocate($image, 220, 53, 69); // Merah bootstrap danger
        $textColor = imagecolorallocate($image, 255, 255, 255); // Putih
        $borderColor = imagecolorallocate($image, 180, 30, 45);

        // Background merah
        imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

        // Border
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);

        // Teks
        $fontSize = 4;
        $textWidth = imagefontwidth($fontSize) * mb_strlen($text);
        $textHeight = imagefontheight($fontSize);
        $x = (int) (($width - $textWidth) / 2);
        $y = (int) (($height - $textHeight) / 2);

        imagestring($image, $fontSize, $x, $y, $text, $textColor);

        // Output
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => strlen($png),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Generate SVG fallback (tanpa GD).
     */
    private static function generateSvgFallback(string $text, int $width, int $height): Response
    {
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
  <rect width="{$width}" height="{$height}" fill="#dc3545" stroke="#b41e2d" stroke-width="2"/>
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
        fill="white" font-family="Arial, sans-serif" font-size="16" font-weight="bold">
    {$text}
  </text>
</svg>
SVG;

        $png = self::svgToPng($svg, $width, $height);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => strlen($png),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Konversi SVG ke PNG menggunakan rsvg-convert atau imagick (jika tersedia).
     */
    private static function svgToPng(string $svg, int $width, int $height): string
    {
        // Coba rsvg-convert
        if (function_exists('exec')) {
            $tmpSvg = tempnam(sys_get_temp_dir(), 'qr_') . '.svg';
            $tmpPng = tempnam(sys_get_temp_dir(), 'qr_') . '.png';

            file_put_contents($tmpSvg, $svg);

            @exec("rsvg-convert -w {$width} -h {$height} -o " . escapeshellarg($tmpPng) . ' ' . escapeshellarg($tmpSvg), $out, $ret);

            if ($ret === 0 && file_exists($tmpPng)) {
                $png = file_get_contents($tmpPng);
                @unlink($tmpSvg);
                @unlink($tmpPng);

                return $png;
            }

            @unlink($tmpSvg);
            @unlink($tmpPng);
        }

        // Fallback terakhir: return placeholder 1x1 transparent PNG
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    }
}
