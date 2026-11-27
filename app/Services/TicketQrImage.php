<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Builds the QR picture used in the ticket e-mail as a PNG (e-mail apps do not show SVG).
 * With a background configured by the admin, the QR is centred on that image.
 */
class TicketQrImage
{
    private const CANVAS = 640;

    /**
     * @param  array|null  $template  the template for this ticket's event; the global one when omitted
     * @return string raw PNG bytes
     */
    public static function png(string $token, ?array $template = null): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Ekstensi PHP GD belum aktif, sehingga QR code tidak bisa dibuat. Aktifkan extension=gd di php.ini.');
        }

        $qr = Builder::create()
            ->writer(new PngWriter)
            ->data($token)
            ->size(360)
            ->margin(16) // white quiet zone, keeps the code scannable on any background
            ->build()
            ->getString();

        $template ??= EmailTemplate::all();
        $path = $template['qr_background'];

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return $qr;
        }

        try {
            return self::onBackground($qr, Storage::disk('public')->get($path), (int) $template['qr_scale']);
        } catch (\Throwable $e) {
            report($e); // a broken background image must never block sending the tickets

            return $qr;
        }
    }

    private static function onBackground(string $qrPng, string $backgroundData, int $scalePercent): string
    {
        $background = @imagecreatefromstring($backgroundData);
        $qr = @imagecreatefromstring($qrPng);

        if (! $background || ! $qr) {
            throw new RuntimeException('Gambar latar QR tidak bisa dibaca.');
        }

        $size = self::CANVAS;
        $canvas = imagecreatetruecolor($size, $size);

        // "Cover" fit: fill the square, crop what overflows.
        $sourceW = imagesx($background);
        $sourceH = imagesy($background);
        $ratio = max($size / $sourceW, $size / $sourceH);
        $drawW = (int) ceil($sourceW * $ratio);
        $drawH = (int) ceil($sourceH * $ratio);
        imagecopyresampled($canvas, $background, (int) (($size - $drawW) / 2), (int) (($size - $drawH) / 2), 0, 0, $drawW, $drawH, $sourceW, $sourceH);

        $qrSize = (int) round($size * $scalePercent / 100);
        $offset = (int) (($size - $qrSize) / 2);
        imagecopyresampled($canvas, $qr, $offset, $offset, 0, 0, $qrSize, $qrSize, imagesx($qr), imagesy($qr));

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }
}
