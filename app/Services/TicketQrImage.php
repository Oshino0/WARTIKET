<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Builds an e-ticket PNG for e-mail.
 * Full-bleed artwork + dark gradient overlay for readable details & QR.
 */
class TicketQrImage
{
    private const CANVAS = 640;

    private const TICKET_WIDTH = 800;

    private const TICKET_HEIGHT = 1000;

    /**
     * @param  array|null  $template  the template for this ticket's event; the global one when omitted
     * @param  array|null  $ticket  ticket details to render into a complete e-ticket image
     * @return string raw PNG bytes
     */
    public static function png(string $token, ?array $template = null, ?array $ticket = null): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Ekstensi PHP GD belum aktif, sehingga QR code tidak bisa dibuat. Aktifkan extension=gd di php.ini.');
        }

        $qr = Builder::create()
            ->writer(new PngWriter)
            ->data($token)
            ->size(360)
            ->margin(16)
            ->build()
            ->getString();

        $template ??= EmailTemplate::all();
        $path = $template['qr_background'];

        if ($ticket !== null) {
            $backgroundData = null;

            if ($path && Storage::disk('public')->exists($path)) {
                try {
                    $backgroundData = Storage::disk('public')->get($path);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return self::onTicket($qr, $backgroundData, $template, $ticket);
        }

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return $qr;
        }

        try {
            return self::onBackground($qr, Storage::disk('public')->get($path), (int) $template['qr_scale']);
        } catch (\Throwable $e) {
            report($e);

            return $qr;
        }
    }

    private static function onTicket(string $qrPng, ?string $backgroundData, array $template, array $ticket): string
    {
        if (! function_exists('imagettftext')) {
            throw new RuntimeException('PHP GD harus mendukung FreeType untuk membuat gambar e-tiket.');
        }

        $font = base_path('vendor/endroid/qr-code/assets/noto_sans.otf');
        if (! is_file($font)) {
            throw new RuntimeException('Font untuk membuat gambar e-tiket tidak ditemukan.');
        }

        $canvas = imagecreatetruecolor(self::TICKET_WIDTH, self::TICKET_HEIGHT);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $paper = imagecolorallocate($canvas, 255, 255, 255);
        $surface = imagecolorallocate($canvas, 15, 23, 42);
        $accent = self::color($canvas, $template['accent_color']);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $soft = imagecolorallocate($canvas, 203, 213, 225);

        // Base dark fill (fallback when no artwork)
        imagefill($canvas, 0, 0, $surface);

        $background = null;
        if ($backgroundData !== null) {
            $background = @imagecreatefromstring($backgroundData);
            if (! $background) {
                report(new RuntimeException('Gambar latar e-tiket tidak bisa dibaca.'));
            }
        }

        // Full-bleed artwork covering the entire ticket
        if ($background) {
            self::drawFullBleed($canvas, $background);
        } else {
            // No artwork → solid dark with accent top strip
            imagefilledrectangle($canvas, 0, 0, self::TICKET_WIDTH, 180, $accent);
        }

        $qr = imagecreatefromstring($qrPng);
        if (! $qr) {
            throw new RuntimeException('QR code tidak bisa dibaca untuk gambar e-tiket.');
        }

        // Dark gradients so text & QR stay readable
        self::drawGradients($canvas);

        // === TOP ===
        self::text($canvas, 'WARTIKET  ·  E-TICKET', 36, 42, 13, $soft, $font, 720);
        self::wrappedText($canvas, (string) ($ticket['event'] ?? 'Event'), 36, 90, 728, 34, 2, $white, $font);

        // Ticket type pill
        $type = (string) ($ticket['ticket_type'] ?? 'TIKET');
        $pillW = min(220, self::textWidth($type, 13, $font) + 32);
        imagefilledrectangle($canvas, 36, 175, 36 + $pillW, 210, $accent);
        self::text($canvas, $type, 48, 198, 13, $white, $font, $pillW - 16);

        // === INFO (compact) ===
        $left = 36;
        $right = 410;

        self::text($canvas, 'TANGGAL & WAKTU', $left, 250, 11, $soft, $font, 340);
        self::text($canvas, (string) ($ticket['date'] ?? 'Jadwal belum ditentukan'), $left, 278, 16, $white, $font, 340);

        self::text($canvas, 'LOKASI', $right, 250, 11, $soft, $font, 350);
        self::wrappedText($canvas, (string) ($ticket['location'] ?? 'Lokasi belum ditentukan'), $right, 278, 350, 15, 2, $white, $font);

        self::text($canvas, 'NAMA PEMEGANG', $left, 340, 11, $soft, $font, 340);
        self::text($canvas, (string) ($ticket['holder'] ?? '-'), $left, 368, 16, $white, $font, 340);

        self::text($canvas, 'TELP. PEMEGANG', $right, 340, 11, $soft, $font, 350);
        self::text($canvas, (string) ($ticket['phone'] ?? '-'), $right, 368, 16, $white, $font, 350);

        // Thin divider
        $line = imagecolorallocatealpha($canvas, 255, 255, 255, 100);
        imageline($canvas, 36, 410, 764, 410, $line);

        // === QR ===
        $qrSize = min(280, max(220, (int) round(250 * (int) $template['qr_scale'] / 55)));
        $qrX = (int) ((self::TICKET_WIDTH - $qrSize) / 2);
        $qrY = 450;
        self::drawQrTile($canvas, $qr, $qrX, $qrY, $qrSize, $paper);

        // Codes under QR
        self::text($canvas, 'KODE TIKET', $left, 780, 11, $soft, $font, 340);
        self::text($canvas, (string) ($ticket['ticket_code'] ?? '-'), $left, 808, 15, $white, $font, 340);

        self::text($canvas, 'NOMOR PESANAN', $right, 780, 11, $soft, $font, 350);
        self::text($canvas, (string) ($ticket['order_number'] ?? '-'), $right, 808, 15, $white, $font, 350);

        self::text($canvas, 'Tunjukkan QR code ini saat memasuki acara.', 36, 870, 12, $soft, $font, 728);

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        imagedestroy($qr);
        imagedestroy($canvas);
        if ($background) {
            imagedestroy($background);
        }

        return $png;
    }

    private static function drawFullBleed(\GdImage $canvas, \GdImage $background): void
    {
        $sourceW = imagesx($background);
        $sourceH = imagesy($background);
        $ratio = max(self::TICKET_WIDTH / $sourceW, self::TICKET_HEIGHT / $sourceH);
        $drawW = (int) ceil($sourceW * $ratio);
        $drawH = (int) ceil($sourceH * $ratio);

        imagecopyresampled(
            $canvas,
            $background,
            (int) ((self::TICKET_WIDTH - $drawW) / 2),
            (int) ((self::TICKET_HEIGHT - $drawH) / 2),
            0,
            0,
            $drawW,
            $drawH,
            $sourceW,
            $sourceH,
        );
    }

    /**
     * Top fade for title + strong bottom dark gradient for details/QR.
     * GD alpha: 0 = opaque, 127 = fully transparent.
     */
    private static function drawGradients(\GdImage $canvas): void
    {
        // Soft top fade
        for ($y = 0; $y < 220; $y++) {
            $t = $y / 220;
            $alpha = (int) (35 + $t * 75);
            $color = imagecolorallocatealpha($canvas, 8, 12, 24, min(115, $alpha));
            imageline($canvas, 0, $y, self::TICKET_WIDTH, $y, $color);
        }

        // Strong bottom dark area
        for ($y = 230; $y < self::TICKET_HEIGHT; $y++) {
            $t = ($y - 230) / (self::TICKET_HEIGHT - 230);
            $alpha = (int) (70 - $t * 35);
            $color = imagecolorallocatealpha($canvas, 8, 12, 24, max(25, min(90, $alpha)));
            imageline($canvas, 0, $y, self::TICKET_WIDTH, $y, $color);
        }
    }

    private static function drawQrTile(\GdImage $canvas, \GdImage $qr, int $x, int $y, int $size, int $paper): void
    {
        imagefilledrectangle($canvas, $x - 14, $y - 14, $x + $size + 14, $y + $size + 14, $paper);

        $border = imagecolorallocate($canvas, 226, 232, 240);
        imagerectangle($canvas, $x - 14, $y - 14, $x + $size + 14, $y + $size + 14, $border);

        imagecopyresampled($canvas, $qr, $x, $y, 0, 0, $size, $size, imagesx($qr), imagesy($qr));
    }

    private static function text(\GdImage $canvas, string $value, int $x, int $y, int $size, int $color, string $font, int $maxWidth): void
    {
        $value = self::fitText($value, $size, $font, $maxWidth);
        imagettftext($canvas, $size, 0, $x, $y, $color, $font, $value);
    }

    private static function wrappedText(\GdImage $canvas, string $value, int $x, int $y, int $maxWidth, int $size, int $maxLines, int $color, string $font): void
    {
        $words = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if (self::textWidth($candidate, $size, $font) <= $maxWidth) {
                $line = $candidate;
                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
            }
            $line = $word;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $last = array_key_last($lines);
            $lines[$last] = self::fitText($lines[$last].'...', $size, $font, $maxWidth);
        }

        foreach ($lines as $index => $text) {
            self::text($canvas, $text, $x, $y + ($index * ($size + 7)), $size, $color, $font, $maxWidth);
        }
    }

    private static function fitText(string $value, int $size, string $font, int $maxWidth): string
    {
        $original = $value;
        $value = trim($value);

        while ($value !== '' && self::textWidth($value, $size, $font) > $maxWidth) {
            $value = mb_substr($value, 0, mb_strlen($value) - 1);
        }

        if ($value !== trim($original)) {
            while ($value !== '' && self::textWidth($value.'...', $size, $font) > $maxWidth) {
                $value = mb_substr($value, 0, mb_strlen($value) - 1);
            }

            return $value.'...';
        }

        return $value;
    }

    private static function textWidth(string $value, int $size, string $font): int
    {
        $bounds = imagettfbbox($size, 0, $font, $value);

        return abs($bounds[2] - $bounds[0]);
    }

    private static function color(\GdImage $canvas, string $hex): int
    {
        return imagecolorallocate(
            $canvas,
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        );
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