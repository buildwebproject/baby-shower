<?php

declare(strict_types=1);

$invitation = require __DIR__ . '/data.php';

if (!function_exists('imagecreatetruecolor')) {
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgMBgN5LecQAAAAASUVORK5CYII=');
    exit;
}

/**
 * @return list<string>
 */
function wrapTextToWidth(string $text, string $fontPath, int $fontSize, int $maxWidth): array
{
    $text = trim($text);
    if ($text === '' || !function_exists('imagettfbbox')) {
        return [$text];
    }

    $words = preg_split('/\s+/u', $text) ?: [];
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        if ($word === '') {
            continue;
        }

        $candidate = $line === '' ? $word : $line . ' ' . $word;
        $box = imagettfbbox($fontSize, 0, $fontPath, $candidate);
        $width = is_array($box) ? (int)abs($box[2] - $box[0]) : 0;

        if ($line !== '' && $width > $maxWidth) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines === [] ? [$text] : $lines;
}

function drawCenteredLine($image, string $fontPath, int $fontSize, int $y, int $color, string $text, int $canvasWidth): void
{
    if ($text === '') {
        return;
    }

    $box = imagettfbbox($fontSize, 0, $fontPath, $text);
    if (!is_array($box)) {
        return;
    }

    $textWidth = (int)abs($box[2] - $box[0]);
    $x = (int)(($canvasWidth - $textWidth) / 2);
    imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text);
}

$fontCandidates = [
    '/usr/share/fonts/truetype/lohit-gujarati/Lohit-Gujarati.ttf',
    '/usr/share/fonts/truetype/samyak-fonts/Samyak-Gujarati.ttf',
    '/usr/share/fonts/truetype/fonts-kalapi/Kalapi.ttf',
    '/usr/share/fonts/truetype/freefont/FreeSerif.ttf',
];

$fontPath = '';
foreach ($fontCandidates as $candidate) {
    if (is_file($candidate)) {
        $fontPath = $candidate;
        break;
    }
}

$width = 1200;
$height = 630;
$image = imagecreatetruecolor($width, $height);

$bg = imagecolorallocate($image, 236, 231, 220);
$cardBg = imagecolorallocate($image, 245, 239, 227);
$border = imagecolorallocate($image, 164, 122, 72);
$textMain = imagecolorallocate($image, 103, 67, 35);
$textAccent = imagecolorallocate($image, 131, 52, 49);
$lineSoft = imagecolorallocate($image, 194, 164, 130);

imagefill($image, 0, 0, $bg);

// Card body.
$cardX1 = 70;
$cardY1 = 40;
$cardX2 = $width - 70;
$cardY2 = $height - 40;
imagefilledrectangle($image, $cardX1, $cardY1, $cardX2, $cardY2, $cardBg);
imagerectangle($image, $cardX1, $cardY1, $cardX2, $cardY2, $border);
imagerectangle($image, $cardX1 + 8, $cardY1 + 8, $cardX2 - 8, $cardY2 - 8, $lineSoft);

// Decorative corner dots.
imagefilledellipse($image, $cardX1 + 32, $cardY1 + 32, 16, 16, $lineSoft);
imagefilledellipse($image, $cardX2 - 32, $cardY1 + 32, 16, 16, $lineSoft);
imagefilledellipse($image, $cardX1 + 32, $cardY2 - 32, 16, 16, $lineSoft);
imagefilledellipse($image, $cardX2 - 32, $cardY2 - 32, 16, 16, $lineSoft);

$ganeshLine = trim((string)($invitation['ganesh_line'] ?? 'શ્રી ગણેશાય નમઃ'));
$title = trim((string)($invitation['title'] ?? 'સીમંત વિધિ (બેબી શાવર)'));
$specialLine = trim((string)($invitation['special_line'] ?? 'ભાવભર્યું આમંત્રણ'));
$dateText = trim((string)($invitation['date_text'] ?? ''));
$timeText = trim((string)($invitation['time_text'] ?? ''));
$venue = trim((string)($invitation['venue_name'] ?? ''));
$city = trim((string)($invitation['city'] ?? ''));

$detailLine = trim($dateText . ($timeText !== '' ? ' | ' . $timeText : ''));
$placeLine = trim($venue . ($city !== '' ? ' - ' . $city : ''));

header('Content-Type: image/png');
header('Cache-Control: public, max-age=600');

if ($fontPath === '' || !function_exists('imagettftext') || !function_exists('imagettfbbox')) {
    imagestring($image, 5, 330, 120, 'Simant Vidhi (Baby Shower)', $textMain);
    imagestring($image, 4, 410, 200, 'Bhavbharyu Amantran', $textAccent);
    imagestring($image, 3, 220, 270, 'Please join us for the celebration', $textMain);
    imagestring($image, 3, 230, 330, $dateText . ' ' . $timeText, $textMain);
    imagestring($image, 3, 250, 380, $venue . ' ' . $city, $textMain);
    imagepng($image);
    imagedestroy($image);
    exit;
}

$y = 130;
drawCenteredLine($image, $fontPath, 38, $y, $textAccent, $ganeshLine, $width);
$y += 72;
drawCenteredLine($image, $fontPath, 50, $y, $textMain, $title, $width);
$y += 64;
drawCenteredLine($image, $fontPath, 36, $y, $textAccent, 'ભાવભર્યું આમંત્રણ', $width);
$y += 56;

$specialLines = wrapTextToWidth($specialLine, $fontPath, 28, 900);
foreach ($specialLines as $line) {
    drawCenteredLine($image, $fontPath, 28, $y, $textMain, $line, $width);
    $y += 44;
}

$y += 12;
imageline($image, 250, $y, 950, $y, $lineSoft);
$y += 56;

if ($detailLine !== '') {
    drawCenteredLine($image, $fontPath, 30, $y, $textMain, $detailLine, $width);
    $y += 46;
}
if ($placeLine !== '') {
    drawCenteredLine($image, $fontPath, 28, $y, $textMain, $placeLine, $width);
}

imagepng($image);
imagedestroy($image);
