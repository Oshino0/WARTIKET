<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Draws the whole e-ticket as one PNG (event, type, date, venue, buyer, order, code, QR):
 * a landscape ticket with a perforated stub, in the style of a concert ticket.
 *
 * Everything is in the picture because e-mail apps ignore CSS backgrounds and web fonts.
 * Needs PHP GD with FreeType. If that is missing, render() falls back to the plain QR image.
 */
class TicketImage
{
    private const W = 1320;
    private const H = 560;
    private const PERF = 840;   // x of the perforation line; the stub is to its right
    private const CORNER = 40;
    private const NOTCH = 26;

    private const BIG = 'BigShoulders-Bold.ttf';
    private const SANS_BOLD = 'InstrumentSans-Bold.ttf';
    private const SANS = 'InstrumentSans-Regular.ttf';

    /** Ascent / descent of each font as a fraction of its pixel size (to place text by its top). */
    private const ASC = [self::BIG => 0.986, self::SANS_BOLD => 0.97, self::SANS => 0.97];
    private const DESC = [self::BIG => 0.215, self::SANS_BOLD => 0.25, self::SANS => 0.25];

    /** Details shown on the ticket for one issued ticket. */
    public static function detailsFor(Order $order, OrderItem $item, Ticket $ticket, array $template): array
    {
        $product = $item->ticketType?->product;
        $start = $product?->start_at;

        return [
            'brand' => (string) ($template['ticket_brand'] ?? 'JualTiket'),
            'event' => (string) $item->product_name_snapshot,
            'type' => (string) $item->ticket_type_name_snapshot,
            'when' => $start
                ? $start->copy()->locale('id')->translatedFormat('d F Y').' | '.$start->format('H:i')
                : 'Jadwal menyusul',
            'where' => (string) ($product?->location ?? ''),
            'buyer' => (string) $order->buyer_name,
            'order' => (string) $order->order_number,
            'code' => (string) $ticket->ticket_code,
            'qr_token' => (string) $ticket->qr_token,
        ];
    }

    /** The finished ticket, or the plain QR image when the full ticket cannot be drawn. */
    public static function render(array $details, array $template): string
    {
        try {
            return self::compose($details, $template);
        } catch (\Throwable $e) {
            report($e); // never block sending tickets because of a drawing problem

            return TicketQrImage::png($details['qr_token'], $template);
        }
    }

    /** @return string raw PNG bytes */
    public static function compose(array $d, array $template): string
    {
        if (! function_exists('imagettftext')) {
            throw new RuntimeException('PHP GD tanpa dukungan FreeType: teks tiket tidak bisa digambar.');
        }
        foreach ([self::BIG, self::SANS_BOLD, self::SANS] as $font) {
            if (! is_file(self::fontPath($font))) {
                throw new RuntimeException('Font tiket tidak ditemukan: '.$font);
            }
        }

        $W = self::W;
        $H = self::H;
        $perf = self::PERF;

        $accent = self::hex((string) ($template['accent_color'] ?? '#6D28D9'));
        $dark = self::mix($accent, [0, 0, 0], 0.62);
        $light = self::mix($accent, [255, 255, 255], 0.62);

        $im = imagecreatetruecolor($W, $H);
        imagealphablending($im, true);

        $white = self::color($im, [255, 255, 255]);
        $highlight = self::color($im, $light);

        self::paintBackground($im, $accent, $template['qr_background'] ?? null);

        // The stub is a little darker than the main part.
        imagefilledrectangle($im, $perf, 0, $W - 1, $H - 1, self::color($im, [0, 0, 0], 0.31));

        // ---- main part: brand (logo + name), title, type, date, venue
        $logoSize = 40;
        $brandX = 56 + self::pasteLogo($im, 56, 36, $logoSize);
        self::textTop($im, 22, $brandX, 48, $highlight, self::SANS_BOLD, mb_strtoupper($d['brand']));

        $title = mb_strtoupper($d['event']);
        $maxW = $perf - 112;
        $hasWhere = trim($d['where']) !== '';
        $budget = 428 - 88 - ((46 + 8) + (44 + 6) + ($hasWhere ? 30 : 0));

        $chosen = null;
        foreach ([112, 100, 92, 84, 76, 68, 60] as $px) {
            $lines = self::wrap($title, self::BIG, $px, $maxW);
            $lh = (int) round($px * 0.92);
            if (count($lines) <= 2 && self::allFit($lines, self::BIG, $px, $maxW) && count($lines) * $lh <= $budget) {
                $chosen = [$px, $lines, $lh];
                break;
            }
        }
        if (! $chosen) {
            $px = 60;
            $lines = array_slice(self::wrap($title, self::BIG, $px, $maxW), 0, 2);
            $lines[count($lines) - 1] = self::ellipsize($lines[count($lines) - 1], self::BIG, $px, $maxW);
            $chosen = [$px, $lines, (int) round($px * 0.92)];
        }
        [$px, $lines, $lh] = $chosen;

        $y = 88;
        foreach ($lines as $line) {
            self::textTop($im, $px, 56, $y, $white, self::BIG, $line);
            $y += $lh;
        }
        $y += 6;

        [$tp, $tt] = self::oneLine(mb_strtoupper($d['type']), self::BIG, [46, 42, 38, 32], $maxW);
        self::textTop($im, $tp, 56, $y, $highlight, self::BIG, $tt);
        $y += 46 + 8;

        [$dp, $dt] = self::oneLine(mb_strtoupper($d['when']), self::BIG, [44, 40, 36, 30], $maxW);
        self::textTop($im, $dp, 56, $y, $white, self::BIG, $dt);
        $y += 44 + 6;

        if ($hasWhere) {
            [$vp, $vt] = self::oneLine(mb_strtoupper($d['where']), self::SANS_BOLD, [22, 20, 18, 16], $maxW);
            self::textTop($im, $vp, 56, $y, $highlight, self::SANS_BOLD, $vt);
        }

        // ---- fields: buyer, order number, ticket code
        $pillW = intdiv($perf - 112 - 32, 3);
        $pillY = 452;
        $pillH = 56;
        $pillFill = self::color($im, self::mix($dark, [255, 255, 255], 0.16));
        $fields = [['PEMBELI', $d['buyer']], ['NO. PESANAN', $d['order']], ['KODE TIKET', $d['code']]];

        foreach ($fields as $i => [$caption, $value]) {
            $x0 = 56 + $i * ($pillW + 16);
            self::roundedRect($im, $x0, $pillY, $x0 + $pillW, $pillY + $pillH, intdiv($pillH, 2), $pillFill);

            [$vp, $vt] = self::oneLine((string) $value, self::SANS_BOLD, [24, 22, 20, 18, 16], $pillW - 36);
            $vw = self::textWidth($vt, self::SANS_BOLD, $vp);
            $baseline = $pillY + $pillH / 2 + (self::ASC[self::SANS_BOLD] - self::DESC[self::SANS_BOLD]) / 2 * $vp;
            self::textBaseline($im, $vp, $x0 + ($pillW - $vw) / 2, $baseline, $white, self::SANS_BOLD, $vt);

            $cw = self::textWidth($caption, self::SANS, 16);
            self::textTop($im, 16, $x0 + ($pillW - $cw) / 2, $pillY + $pillH + 14, $highlight, self::SANS, $caption);
        }

        // ---- stub: QR, hint, rotated event name and date
        $scale = max(40, min(70, (int) ($template['qr_scale'] ?? 65)));
        $qrSize = (int) round(200 + ($scale - 40) / 30 * 120);
        $qrX = $perf + 36;
        $qrY = intdiv($H - $qrSize, 2) - 30;

        $qrPng = Builder::create()->writer(new PngWriter)->data($d['qr_token'])->size(360)->margin(16)->build()->getString();
        $qr = imagecreatefromstring($qrPng);
        if (! $qr) {
            throw new RuntimeException('QR code tidak bisa dibuat.');
        }
        imagecopyresampled($im, $qr, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qr), imagesy($qr));

        $hint = 'SCAN DI PINTU MASUK';
        $hw = self::textWidth($hint, self::SANS_BOLD, 17);
        self::textTop($im, 17, $qrX + ($qrSize - $hw) / 2, $qrY + $qrSize + 22, $white, self::SANS_BOLD, $hint);

        $stripX = $qrX + $qrSize + 26;
        self::rotated($im, 46, $stripX, 52, $H - 104, $white, self::BIG, mb_strtoupper($d['event']), [46, 42, 38, 34, 30]);
        self::rotated($im, 19, $stripX + 58, 52, $H - 104, $highlight, self::SANS_BOLD, mb_strtoupper($d['when']), [19, 17, 15, 13]);

        // ---- perforation
        imagesetthickness($im, 3);
        $dash = self::color($im, [255, 255, 255], 0.67);
        for ($yy = 40; $yy < $H - 30; $yy += 22) {
            imageline($im, $perf, $yy, $perf, $yy + 11, $dash);
        }
        imagesetthickness($im, 1);

        // ---- transparent rounded corners and the two notches on the perforation
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $clear = imagecolorallocatealpha($im, 0, 0, 0, 127);
        $r = self::CORNER;

        self::cutCorner($im, $clear, 0, 0, $r, $r, -90, -180);
        self::cutCorner($im, $clear, $W - 1, 0, $W - 1 - $r, $r, -90, 0);
        self::cutCorner($im, $clear, $W - 1, $H - 1, $W - 1 - $r, $H - 1 - $r, 0, 90);
        self::cutCorner($im, $clear, 0, $H - 1, $r, $H - 1 - $r, 90, 180);
        imagefilledellipse($im, $perf, 0, self::NOTCH * 2, self::NOTCH * 2, $clear);
        imagefilledellipse($im, $perf, $H, self::NOTCH * 2, self::NOTCH * 2, $clear);

        ob_start();
        imagepng($im, null, 6);

        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------ background

    private static function paintBackground($im, array $accent, ?string $path): void
    {
        $W = self::W;
        $H = self::H;

        if ($path && Storage::disk('public')->exists($path)) {
            $src = @imagecreatefromstring(Storage::disk('public')->get($path));

            if ($src) {
                // "Cover" fit: fill the ticket, crop what overflows, then darken so white text stays readable.
                $sw = imagesx($src);
                $sh = imagesy($src);
                $ratio = max($W / $sw, $H / $sh);
                $dw = (int) ceil($sw * $ratio);
                $dh = (int) ceil($sh * $ratio);
                imagecopyresampled($im, $src, (int) (($W - $dw) / 2), (int) (($H - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
                imagefilledrectangle($im, 0, 0, $W - 1, $H - 1, self::color($im, [0, 0, 0], 0.59));

                return;
            }
        }

        // No image: a dark gradient from the accent colour, with a few soft light bands.
        $top = self::mix($accent, [0, 0, 0], 0.72);
        $bottom = self::mix($accent, [0, 0, 0], 0.40);
        for ($y = 0; $y < $H; $y++) {
            imageline($im, 0, $y, $W - 1, $y, self::color($im, self::mix($top, $bottom, $y / ($H - 1))));
        }

        $band = self::color($im, [255, 255, 255], 0.055);
        foreach ([[120, 90], [330, 40], [520, 120]] as [$x0, $width]) {
            imagefilledpolygon($im, [$x0, 0, $x0 + $width, 0, $x0 + $width - 220, $H, $x0 - 220, $H], $band);
        }
    }

    // ------------------------------------------------------------------ drawing helpers

    /**
     * Paste the transparent brand logo. Returns horizontal space taken (size + gap), or 0 if missing.
     */
    private static function pasteLogo($im, int $x, int $y, int $size): int
    {
        $path = public_path('images/logo.png');
        if (! is_file($path)) {
            return 0;
        }

        $src = @imagecreatefrompng($path);
        if (! $src) {
            return 0;
        }

        imagealphablending($im, true);
        imagesavealpha($src, true);
        imagecopyresampled($im, $src, $x, $y, 0, 0, $size, $size, imagesx($src), imagesy($src));
        imagedestroy($src);

        return $size + 14;
    }

    private static function fontPath(string $font): string
    {
        return resource_path('fonts/'.$font);
    }

    /** GD wants points; at GD's 96 dpi, points = pixels * 0.75. */
    private static function pt(float $px): float
    {
        return $px * 0.75;
    }

    private static function textWidth(string $text, string $font, int $px): float
    {
        $box = imagettfbbox(self::pt($px), 0, self::fontPath($font), $text);

        if ($box === false) {
            throw new RuntimeException('Teks tiket tidak bisa diukur.');
        }

        return (float) ($box[2] - $box[0]);
    }

    /** Draw text whose top (ascender line) is at $yTop. */
    private static function textTop($im, int $px, float $x, float $yTop, int $color, string $font, string $text): void
    {
        self::textBaseline($im, $px, $x, $yTop + self::ASC[$font] * $px, $color, $font, $text);
    }

    private static function textBaseline($im, int $px, float $x, float $baselineY, int $color, string $font, string $text): void
    {
        imagettftext($im, self::pt($px), 0, (int) round($x), (int) round($baselineY), $color, self::fontPath($font), $text);
    }

    /** Text running top-to-bottom (glyph tops point right), starting at $yStart, baseline at $xBaseline. */
    private static function rotated($im, int $px, float $xBaseline, float $yStart, int $maxLength, int $color, string $font, string $text, array $sizes): void
    {
        [$size, $fitted] = self::oneLine($text, $font, $sizes, $maxLength);
        imagettftext($im, self::pt($size), 270, (int) round($xBaseline), (int) round($yStart), $color, self::fontPath($font), $fitted);
    }

    private static function roundedRect($im, int $x0, int $y0, int $x1, int $y1, int $r, int $color): void
    {
        imagefilledrectangle($im, $x0 + $r, $y0, $x1 - $r, $y1, $color);
        imagefilledrectangle($im, $x0, $y0 + $r, $x1, $y1 - $r, $color);
        foreach ([[$x0 + $r, $y0 + $r], [$x1 - $r, $y0 + $r], [$x0 + $r, $y1 - $r], [$x1 - $r, $y1 - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }

    /** Paint the area outside a rounded corner with $clear (used with alpha blending off = transparent). */
    private static function cutCorner($im, int $clear, int $cornerX, int $cornerY, int $cx, int $cy, int $from, int $to): void
    {
        $points = [$cornerX, $cornerY];
        $step = $from < $to ? 5 : -5;

        for ($a = $from; $from < $to ? $a <= $to : $a >= $to; $a += $step) {
            $points[] = (int) round($cx + self::CORNER * cos(deg2rad($a)));
            $points[] = (int) round($cy + self::CORNER * sin(deg2rad($a)));
        }

        imagefilledpolygon($im, $points, $clear);
    }

    // ------------------------------------------------------------------ text fitting

    /** @return string[] */
    private static function wrap(string $text, string $font, int $px, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [''];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $try = trim($current.' '.$word);
            if ($current === '' || self::textWidth($try, $font, $px) <= $maxWidth) {
                $current = $try;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }
        $lines[] = $current;

        return $lines;
    }

    private static function allFit(array $lines, string $font, int $px, int $maxWidth): bool
    {
        foreach ($lines as $line) {
            if (self::textWidth($line, $font, $px) > $maxWidth) {
                return false;
            }
        }

        return true;
    }

    private static function ellipsize(string $text, string $font, int $px, int $maxWidth): string
    {
        while (mb_strlen($text) > 1 && self::textWidth($text.'…', $font, $px) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return $text.'…';
    }

    /** @return array{0: int, 1: string} pixel size and the text, shortened with … when nothing fits */
    private static function oneLine(string $text, string $font, array $sizes, int $maxWidth): array
    {
        foreach ($sizes as $px) {
            if (self::textWidth($text, $font, $px) <= $maxWidth) {
                return [$px, $text];
            }
        }

        $px = end($sizes);

        return [$px, self::ellipsize($text, $font, $px, $maxWidth)];
    }

    // ------------------------------------------------------------------ colour helpers

    private static function hex(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function mix(array $from, array $to, float $amount): array
    {
        return [
            (int) round($from[0] * (1 - $amount) + $to[0] * $amount),
            (int) round($from[1] * (1 - $amount) + $to[1] * $amount),
            (int) round($from[2] * (1 - $amount) + $to[2] * $amount),
        ];
    }

    /** @param  float  $opacity  1 = solid, 0 = invisible */
    private static function color($im, array $rgb, float $opacity = 1.0): int
    {
        return imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], (int) round(127 * (1 - $opacity)));
    }
}
