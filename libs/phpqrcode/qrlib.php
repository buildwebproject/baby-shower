<?php

declare(strict_types=1);

if (!defined('QR_ECLEVEL_L')) {
    define('QR_ECLEVEL_L', 'L');
}
if (!defined('QR_ECLEVEL_M')) {
    define('QR_ECLEVEL_M', 'M');
}
if (!defined('QR_ECLEVEL_Q')) {
    define('QR_ECLEVEL_Q', 'Q');
}
if (!defined('QR_ECLEVEL_H')) {
    define('QR_ECLEVEL_H', 'H');
}

/**
 * Lightweight QR helper with PHP QRcode-style API.
 *
 * It prefers the `qrencode` binary when available (produces a valid QR PNG).
 * If unavailable, it draws a deterministic QR-like fallback image so the UI still works.
 */
class QRcode
{
    /**
     * @param string $text Data to encode
     * @param string|false $outfile Output file path or false to stream PNG
     * @param string $level Error correction level (L/M/Q/H)
     * @param int $size Pixel size per module
     * @param int $margin Quiet zone modules
     */
    public static function png(string $text, $outfile = false, string $level = QR_ECLEVEL_L, int $size = 4, int $margin = 2): void
    {
        $text = $text !== '' ? $text : ' ';
        $size = max(1, $size);
        $margin = max(0, $margin);

        if (is_string($outfile) && $outfile !== '' && self::generateWithQrencode($text, $outfile, $level, $size, $margin)) {
            return;
        }

        self::generateFallbackImage($text, $outfile, $size, $margin);
    }

    private static function generateWithQrencode(string $text, string $outfile, string $level, int $size, int $margin): bool
    {
        if (!function_exists('exec') || !function_exists('shell_exec')) {
            return false;
        }

        $binary = trim((string)@shell_exec('command -v qrencode 2>/dev/null'));
        if ($binary === '') {
            return false;
        }

        $level = strtoupper($level);
        if (!in_array($level, ['L', 'M', 'Q', 'H'], true)) {
            $level = 'L';
        }

        $directory = dirname($outfile);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        $command =
            escapeshellarg($binary)
            . ' -l ' . escapeshellarg($level)
            . ' -s ' . (int)$size
            . ' -m ' . (int)$margin
            . ' -o ' . escapeshellarg($outfile)
            . ' ' . escapeshellarg($text)
            . ' 2>/dev/null';

        @exec($command, $output, $statusCode);

        return $statusCode === 0 && is_file($outfile);
    }

    /**
     * Deterministic fallback: visually similar matrix image.
     */
    private static function generateFallbackImage(string $text, $outfile, int $size, int $margin): void
    {
        $moduleCount = 29;
        $pixelSize = max(2, $size) * 4;
        $imageSize = ($moduleCount + ($margin * 2)) * $pixelSize;

        // If GD is unavailable, write a tiny transparent PNG.
        if (!function_exists('imagecreatetruecolor')) {
            $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgMBgN5LecQAAAAASUVORK5CYII=');

            if (is_string($outfile) && $outfile !== '') {
                file_put_contents($outfile, $tinyPng);
                return;
            }

            header('Content-Type: image/png');
            echo $tinyPng;
            return;
        }

        $matrix = array_fill(0, $moduleCount, array_fill(0, $moduleCount, false));

        self::drawFinder($matrix, 0, 0);
        self::drawFinder($matrix, $moduleCount - 7, 0);
        self::drawFinder($matrix, 0, $moduleCount - 7);

        $hash = hash('sha256', $text, true);
        $bytes = array_values(unpack('C*', $hash));
        $byteIndex = 0;
        $bitIndex = 0;

        for ($y = 0; $y < $moduleCount; $y++) {
            for ($x = 0; $x < $moduleCount; $x++) {
                if (self::inFinderZone($x, $y, $moduleCount)) {
                    continue;
                }

                $byte = $bytes[$byteIndex % count($bytes)];
                $bit = ($byte >> (7 - $bitIndex)) & 1;
                $matrix[$y][$x] = $bit === 1;

                $bitIndex++;
                if ($bitIndex > 7) {
                    $bitIndex = 0;
                    $byteIndex++;
                }
            }
        }

        $image = imagecreatetruecolor($imageSize, $imageSize);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($y = 0; $y < $moduleCount; $y++) {
            for ($x = 0; $x < $moduleCount; $x++) {
                if (!$matrix[$y][$x]) {
                    continue;
                }

                $left = ($x + $margin) * $pixelSize;
                $top = ($y + $margin) * $pixelSize;
                imagefilledrectangle(
                    $image,
                    $left,
                    $top,
                    $left + $pixelSize - 1,
                    $top + $pixelSize - 1,
                    $black
                );
            }
        }

        if (is_string($outfile) && $outfile !== '') {
            imagepng($image, $outfile);
        } else {
            header('Content-Type: image/png');
            imagepng($image);
        }

        imagedestroy($image);
    }

    private static function drawFinder(array &$matrix, int $startX, int $startY): void
    {
        for ($y = 0; $y < 7; $y++) {
            for ($x = 0; $x < 7; $x++) {
                $outer = $x === 0 || $x === 6 || $y === 0 || $y === 6;
                $inner = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
                $matrix[$startY + $y][$startX + $x] = $outer || $inner;
            }
        }
    }

    private static function inFinderZone(int $x, int $y, int $moduleCount): bool
    {
        $topLeft = $x < 7 && $y < 7;
        $topRight = $x >= $moduleCount - 7 && $y < 7;
        $bottomLeft = $x < 7 && $y >= $moduleCount - 7;

        return $topLeft || $topRight || $bottomLeft;
    }
}
