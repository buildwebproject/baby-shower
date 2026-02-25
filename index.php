<?php

declare(strict_types=1);

$invitation = require __DIR__ . '/data.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function currentPageUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $uri = $_SERVER['REQUEST_URI'] ?? '/index.php';
    return $scheme . '://' . $host . $uri;
}

function baseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    return $scheme . '://' . $host;
}

function readJsonFile(string $filePath): array
{
    if (!is_file($filePath)) return [];
    $content = file_get_contents($filePath);
    if ($content === false || $content === '') return [];
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function writeJsonFile(string $filePath, array $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    return file_put_contents($filePath, $json, LOCK_EX) !== false;
}

function textLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

/**
 * @param mixed $value
 * @return list<string>
 */
function stringList($value): array
{
    if (!is_array($value)) return [];
    $result = [];
    foreach ($value as $item) {
        if (is_scalar($item)) {
            $text = trim((string)$item);
            if ($text !== '') $result[] = $text;
        }
    }
    return $result;
}

/**
 * @param list<string> $candidates
 */
function firstExistingPublicPath(array $candidates, string $fallback = ''): string
{
    foreach ($candidates as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '') continue;
        if (preg_match('#^https?://#i', $candidate)) return $candidate;
        $normalized = ltrim($candidate, '/');
        if (is_file(__DIR__ . '/' . $normalized)) return $normalized;
    }
    return ltrim($fallback, '/');
}

function assetUrl(string $path): string
{
    if (preg_match('#^https?://#i', $path)) return $path;
    $segments = explode('/', ltrim($path, '/'));
    $segments = array_map(static fn(string $segment): string => rawurlencode($segment), $segments);
    return implode('/', $segments);
}

$storageDir  = __DIR__ . '/storage';
$rsvpFile    = $storageDir . '/rsvp.json';
$qrFile      = $storageDir . '/qr.png';
$qrPublicPath = 'storage/qr.png';

if (!is_dir($storageDir)) mkdir($storageDir, 0775, true);
if (!is_file($rsvpFile)) writeJsonFile($rsvpFile, []);

$currentPageUrl = currentPageUrl();

$ganeshLine  = (string)($invitation['ganesh_line']  ?? 'શ્રી ગણેશાય નમઃ');
$ganeshImage = firstExistingPublicPath([
    (string)($invitation['ganesh_image'] ?? ''),
    'assets/images/ganesha.png',
], 'assets/images/ganesha.png');
$doorCenterImage = firstExistingPublicPath([
    (string)($invitation['door_center_image'] ?? ''),
    'assets/images/krishna and radha.png',
], 'assets/images/krishna and radha.png');

$decorCandidates = array_merge(
    stringList($invitation['decor_images'] ?? []),
    [
        'assets/images/image-1.png',
        'assets/images/image-2.png',
        'assets/images/image-3.png',
    ]
);
$decorPool = [];
foreach ($decorCandidates as $candidate) {
    $resolvedPath = firstExistingPublicPath([$candidate]);
    if ($resolvedPath !== '' && !in_array($resolvedPath, $decorPool, true)) {
        $decorPool[] = $resolvedPath;
    }
}
if ($decorPool === []) {
    $decorPool[] = $ganeshImage;
}
while (count($decorPool) < 3) {
    $decorPool[] = $decorPool[count($decorPool) - 1];
}
$decorSlotConfig = is_array($invitation['decor_slots'] ?? null) ? $invitation['decor_slots'] : [];
$cornerImageFallbacks = [
    'top_left' => $decorPool[0],
    'top_right' => $decorPool[1],
    'bottom_left' => $decorPool[2],
    'bottom_right' => $decorPool[2],
];
$cornerImages = [];
foreach ($cornerImageFallbacks as $slot => $fallbackPath) {
    $configured = $decorSlotConfig[$slot] ?? '';
    $configuredPath = is_scalar($configured) ? (string)$configured : '';
    $cornerImages[$slot] = firstExistingPublicPath([$configuredPath, $fallbackPath], $fallbackPath);
}



$title       = (string)($invitation['title']        ?? 'સીમંત વિધિ (બેબી શાવર)');
$specialLine = (string)($invitation['special_line'] ?? '');

$motherName  = (string)($invitation['mother_name']  ?? '');
$fatherName  = (string)($invitation['father_name']  ?? '');
$familyName  = (string)($invitation['family_name']  ?? '');

$dateText    = (string)($invitation['date_text']    ?? '');
$timeText    = (string)($invitation['time_text']    ?? '');
$venueName   = (string)($invitation['venue_name']   ?? '');
$fullAddress = (string)($invitation['full_address'] ?? '');
$city        = (string)($invitation['city']         ?? '');

$leadLines = stringList($invitation['lead_lines'] ?? []);
if ($leadLines === []) {
    $leadLines = [
        'સહર્ષ ' . ($familyName !== '' ? $familyName : 'પરિવાર') . ' તરફથી ભાવભર્યું આમંત્રણ.',
        (($motherName !== '' || $fatherName !== '') ? trim($motherName . ' અને ' . $fatherName) : 'અમારા પરિવાર') . ' ના શુભ પ્રસંગે આપનું હાર્દિક સ્વાગત છે.',
    ];
}

$programLine  = (string)($invitation['program_line']   ?? '');
$mealDateText = (string)($invitation['meal_date_text']  ?? $dateText);
$mealTimeText = (string)($invitation['meal_time_text']  ?? $timeText);
$mealNote     = (string)($invitation['meal_note']       ?? '');

$venueLines = stringList($invitation['venue_lines'] ?? []);
if ($venueLines === []) {
    $venueLines = array_values(array_filter([
        $venueName,
        trim($fullAddress . (($city !== '') ? ', ' . $city : '')),
    ], static fn(string $l): bool => $l !== ''));
}

$inviters = stringList($invitation['inviters'] ?? []);
if ($inviters === []) {
    $inviters = array_values(array_filter([
        $familyName,
        ($fatherName !== '') ? $fatherName . ' પરિવાર' : '',
    ], static fn(string $l): bool => $l !== ''));
}

$phoneDisplay    = (string)($invitation['contact_phone']    ?? '');
$phoneDial       = preg_replace('/[^0-9+]/', '', $phoneDisplay) ?: '';
$mapsUrl         = (string)($invitation['google_maps_url']  ?? '#');
$whatsAppMessage = trim((string)($invitation['whatsapp_message'] ?? ''));

$ogTitle = trim($title) !== '' ? trim($title) : 'સીમંત વિધિ (બેબી શાવર)';
$ogDescriptionParts = ['આપ સહપરિવારને હાર્દિક આમંત્રણ'];
if ($dateText !== '') $ogDescriptionParts[] = 'તા. ' . $dateText;
if ($timeText !== '') $ogDescriptionParts[] = $timeText;
if ($venueName !== '') $ogDescriptionParts[] = 'સ્થળ: ' . $venueName;
$ogDescription = implode(' | ', $ogDescriptionParts);
if ($ogDescription === '') $ogDescription = 'ભાવભર્યું આમંત્રણ';
if (function_exists('mb_strimwidth')) {
    $ogDescription = mb_strimwidth($ogDescription, 0, 190, '…', 'UTF-8');
}

$ogImageAlt = trim($ogTitle . ($motherName !== '' ? ' - ' . $motherName : '') . ' આમંત્રણ કાર્ડ');
if ($ogImageAlt === '') $ogImageAlt = 'સીમંત વિધિ આમંત્રણ કાર્ડ';

if ($whatsAppMessage === '') {
    $whatsAppMessage = $ogTitle . ' - ' . $ogDescription;
}
$scriptBase   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$ogImageVersion = (string)(@filemtime(__DIR__ . '/data.php') ?: time());
$ogImageUrl   = baseUrl() . $scriptBase . '/share-image.php?v=' . rawurlencode($ogImageVersion);

$flash  = ['type' => '', 'message' => ''];
$old    = ['name' => '', 'mobile' => '', 'guests' => '1'];
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$selfAction    = (string)($_SERVER['PHP_SELF'] ?? 'index.php');

if ($requestMethod === 'POST' && !empty($invitation['rsvp_enabled']) && ($_POST['form_type'] ?? '') === 'rsvp') {
    $name      = trim((string)($_POST['name']   ?? ''));
    $mobile    = trim((string)($_POST['mobile'] ?? ''));
    $guestsRaw = trim((string)($_POST['guests'] ?? ''));
    $old = ['name' => $name, 'mobile' => $mobile, 'guests' => $guestsRaw];
    if ($name === '' || textLength($name) < 2 || textLength($name) > 80)
        $flash = ['type' => 'error', 'message' => 'કૃપા કરીને યોગ્ય નામ દાખલ કરો.'];
    elseif (!preg_match('/^\+?[0-9\s\-]{8,15}$/', $mobile))
        $flash = ['type' => 'error', 'message' => 'કૃપા કરીને યોગ્ય મોબાઇલ નંબર દાખલ કરો.'];
    $guests = filter_var($guestsRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 25]]);
    if ($flash['type'] === '' && $guests === false)
        $flash = ['type' => 'error', 'message' => 'અતિથિઓની સંખ્યા 1 થી 25 વચ્ચે હોવી જોઈએ.'];
    if ($flash['type'] === '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $entries = readJsonFile($rsvpFile);
        $oneHourAgo = time() - 3600;
        $recentCount = 0;
        foreach ($entries as $entry) {
            $entryIp  = (string)($entry['ip'] ?? '');
            $timestamp = strtotime((string)($entry['timestamp'] ?? ''));
            if ($entryIp === $ip && $timestamp !== false && $timestamp >= $oneHourAgo) $recentCount++;
        }
        if ($recentCount >= 3) {
            $flash = ['type' => 'error', 'message' => 'એક કલાકમાં વધુ RSVP મોકલી શકાય નહી.'];
        } else {
            $entries[] = ['name' => $name, 'mobile' => $mobile, 'guests' => (int)$guests, 'timestamp' => date('c'), 'ip' => $ip];
            if (writeJsonFile($rsvpFile, $entries)) {
                $flash = ['type' => 'success', 'message' => 'આભાર! RSVP સફળ.'];
                $old   = ['name' => '', 'mobile' => '', 'guests' => '1'];
            } else {
                $flash = ['type' => 'error', 'message' => 'RSVP સાચવી શકાયું નથી.'];
            }
        }
    }
}

$qrEnabled = !empty($invitation['qr_enabled']);
$qrReady   = false;
$qrError   = '';
if ($qrEnabled) {
    $qrLibPath = __DIR__ . '/libs/phpqrcode/qrlib.php';
    if (is_file($qrLibPath)) {
        require_once $qrLibPath;
        try {
            QRcode::png($currentPageUrl, $qrFile, QR_ECLEVEL_L, 5, 2);
            $qrReady = is_file($qrFile);
            if (!$qrReady) $qrError = 'QR કોડ જનરેટ થઈ શક્યો નથી.';
        } catch (Throwable $e) {
            $qrError = 'QR library error.';
        }
    } else {
        $qrError = 'QR library મળતી નથી.';
    }
}
?>
<!doctype html>
<html lang="gu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($ogTitle); ?></title>
    <meta name="description" content="<?php echo e($ogDescription); ?>">
    <link rel="canonical" href="<?php echo e($currentPageUrl); ?>">
    <meta property="og:type"              content="website">
    <meta property="og:site_name"         content="Simant Invitation">
    <meta property="og:locale"            content="gu_IN">
    <meta property="og:title"             content="<?php echo e($ogTitle); ?>">
    <meta property="og:description"       content="<?php echo e($ogDescription); ?>">
    <meta property="og:image:url"         content="<?php echo e($ogImageUrl); ?>">
    <meta property="og:image"             content="<?php echo e($ogImageUrl); ?>">
    <meta property="og:image:secure_url"  content="<?php echo e($ogImageUrl); ?>">
    <meta property="og:image:type"        content="image/png">
    <meta property="og:image:width"       content="1200">
    <meta property="og:image:height"      content="630">
    <meta property="og:image:alt"         content="<?php echo e($ogImageAlt); ?>">
    <meta property="og:url"               content="<?php echo e($currentPageUrl); ?>">
    <meta name="twitter:card"             content="summary_large_image">
    <meta name="twitter:title"            content="<?php echo e($ogTitle); ?>">
    <meta name="twitter:description"      content="<?php echo e($ogDescription); ?>">
    <meta name="twitter:image"            content="<?php echo e($ogImageUrl); ?>">
    <meta name="twitter:image:alt"        content="<?php echo e($ogImageAlt); ?>">

    <!-- Gujarati + Elegant Display fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Hind+Vadodara:wght@300;400;500;600;700&family=Tiro+Devanagari+Sanskrit:ital@0;1&family=Noto+Serif+Gujarati:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ── Reset ──────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── Page ───────────────────────────────── */
        body {
            font-family: 'Hind Vadodara', 'Noto Sans Gujarati', sans-serif;
            min-height: 100vh;
            background:
                /* subtle diagonal texture */
                repeating-linear-gradient(
                    135deg,
                    rgba(255, 173, 204, 0.08) 0px, rgba(255, 173, 204, 0.08) 1px,
                    transparent 1px, transparent 22px
                ),
                linear-gradient(160deg, #fff2f8 0%, #f7ebff 46%, #edf5ff 100%);
            padding: 24px 12px 48px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            overflow-x: hidden;
            /* no outer shadow — keep it clean */
            box-shadow: none;
            outline: none;
        }

        /* ── Opening cover experience ───────────── */
        .opening-stage {
            display: block;
            width: min(100%, 520px);
            border-radius: 14px;
            border: 1.5px solid rgba(121, 167, 220, 0.45);
            background:
                repeating-linear-gradient(
                    135deg,
                    rgba(255, 168, 214, 0.08) 0px, rgba(255, 168, 214, 0.08) 1px,
                    transparent 1px, transparent 20px
                ),
                linear-gradient(165deg, #fff0f8 0%, #f2eaff 52%, #e7f1ff 100%);
            box-shadow:
                inset 0 0 0 5px rgba(255, 176, 218, 0.16),
                0 12px 32px rgba(88, 129, 173, 0.22);
            padding: 18px 14px 16px;
            margin-bottom: 14px;
            max-height: 1400px;
            position: relative;
            overflow: hidden;
            transform-origin: top center;
            z-index: 4;
            transition:
                opacity 0.48s ease,
                transform 0.48s ease,
                max-height 0.48s ease,
                margin-bottom 0.48s ease,
                padding 0.48s ease;
        }

        body:not(.gate-opened) .card-wrap {
            display: none;
        }

        body.gate-opened .opening-stage {
            opacity: 0;
            transform: translateY(-16px) scale(0.985);
            max-height: 0;
            margin-bottom: 0;
            padding-top: 0;
            padding-bottom: 0;
            border-width: 0;
            pointer-events: none;
        }

        body.gate-opened .card-wrap {
            display: flex;
            animation: revealCard 0.9s cubic-bezier(0.2, 0.68, 0.2, 1) both;
        }

        @keyframes revealCard {
            from { opacity: 0; transform: translateY(22px) scale(0.985); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .opening-head {
            text-align: center;
            margin-bottom: 14px;
        }

        .opening-overline {
            color: #7e4a67;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.8px;
        }

        .opening-title {
            font-family: 'Noto Serif Gujarati', serif;
            color: #4f243d;
            font-size: clamp(2rem, 8vw, 2.8rem);
            font-weight: 700;
            line-height: 1.1;
            margin-top: 4px;
        }

        .opening-ganesh-wrap {
            margin-top: 6px;
            display: grid;
            place-items: center;
        }

        .opening-ganesh-wrap img {
            width: 42px;
            height: 42px;
            object-fit: contain;
            filter: drop-shadow(0 2px 7px rgba(129, 82, 110, 0.25));
        }

        .opening-shell {
            position: relative;
            width: 100%;
            aspect-ratio: 3 / 4;
            border-radius: 18px;
            border: 1.5px solid rgba(187, 159, 200, 0.55);
            background:
                repeating-linear-gradient(
                    135deg,
                    rgba(255, 178, 216, 0.08) 0px, rgba(255, 178, 216, 0.08) 1px,
                    transparent 1px, transparent 16px
                ),
                linear-gradient(170deg, #fff4fa 0%, #f4edff 50%, #eaf2ff 100%);
            box-shadow:
                inset 0 0 0 5px rgba(255, 196, 228, 0.2),
                0 10px 24px rgba(103, 79, 129, 0.18);
            perspective: 1450px;
            overflow: hidden;
        }

        .opening-door {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 50%;
            border: 1px solid rgba(196, 153, 188, 0.62);
            background:
                repeating-linear-gradient(
                    140deg,
                    rgba(255,255,255,0.24) 0px, rgba(255,255,255,0.24) 2px,
                    transparent 2px, transparent 17px
                ),
                linear-gradient(160deg, #fce4f3 0%, #eadfff 100%);
            box-shadow: 0 10px 26px rgba(126, 84, 118, 0.2);
            backface-visibility: hidden;
            transform-style: preserve-3d;
            overflow: hidden;
            z-index: 2;
        }

        .opening-door.left {
            left: 0;
            transform-origin: left center;
        }

        .opening-door.right {
            right: 0;
            transform-origin: right center;
        }

        .opening-door::before {
            content: '';
            position: absolute;
            inset: 12px;
            border: 1px solid rgba(197, 160, 191, 0.45);
            border-radius: 14px;
        }

        .door-inner {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            color: #8e5b7c;
            font-family: 'Noto Serif Gujarati', serif;
            font-size: clamp(1.5rem, 4.8vw, 2rem);
            font-weight: 700;
            letter-spacing: 1px;
            text-align: center;
            padding: 34px 18px 18px;
            backface-visibility: hidden;
            transform: translateZ(2px);
        }

        .door-word {
            position: relative;
            color: #865271;
            text-shadow: 0 2px 10px rgba(157, 114, 141, 0.2);
            backface-visibility: hidden;
            margin-top: 4px;
            z-index: 2;
        }

        .door-word::before,
        .door-word::after {
            position: absolute;
            top: 50%;
            transform: translateY(-52%);
            font-size: 0.5em;
            color: #b27598;
            opacity: 0.9;
        }

        .door-word::before {
            content: '✽';
            right: calc(100% + 10px);
        }

        .door-word::after {
            content: '✽';
            left: calc(100% + 10px);
        }

        .door-center-line {
            position: absolute;
            left: 50%;
            top: 4%;
            bottom: 4%;
            width: 2px;
            transform: translateX(-50%);
            background: linear-gradient(180deg, transparent, rgba(190, 130, 175, 0.8), transparent);
            z-index: 4;
            transition: opacity 0.3s ease;
        }

        .door-top-decor {
            position: absolute;
            left: 50%;
            top: 47%;
            transform: translate(-50%, -100%);
            width: 130px;
            
            pointer-events: none;
            z-index: 5;
            display: grid;
            place-items: center;
        }

        .door-top-decor .center-deity {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 4px 10px rgba(124, 75, 109, 0.3));
        }

        .opening-ribbon {
            position: absolute;
            left: 50%;
            top: 56%;
            width: 90%;
            height: 136px;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 6;
        }

        .ribbon-band {
            position: absolute;
            top: 58px;
            height: 20px;
            width: 44%;
            background: linear-gradient(160deg, #f7d0e7 0%, #efafcf 38%, #d474a8 100%);
            box-shadow:
                inset 0 1px 2px rgba(255,255,255,0.62),
                inset 0 -2px 4px rgba(131, 63, 96, 0.24),
                0 5px 14px rgba(120, 70, 102, 0.24);
            border: 1px solid rgba(184, 108, 151, 0.4);
        }

        .ribbon-band::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 10px;
            right: 10px;
            height: 5px;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(255,255,255,0.45), rgba(255,255,255,0.08));
        }

        .ribbon-band.left {
            left: 0;
            border-radius: 999px 10px 10px 999px;
        }

        .ribbon-band.right {
            right: 0;
            border-radius: 10px 999px 999px 10px;
        }

        .ribbon-tail {
            position: absolute;
            top: 68px;
            width: 58px;
            height: 46px;
            background: linear-gradient(180deg, #f8d2e8 0%, #de80b4 100%);
            clip-path: polygon(0 0, 100% 0, 74% 100%, 50% 74%, 26% 100%);
            border: 1px solid rgba(183, 108, 149, 0.36);
            filter: drop-shadow(0 6px 10px rgba(113, 55, 89, 0.3));
        }

        .ribbon-tail.left {
            left: calc(50% - 64px);
            transform: rotate(4deg);
        }

        .ribbon-tail.right {
            right: calc(50% - 64px);
            transform: scaleX(-1) rotate(4deg);
        }

        .ribbon-knot {
            position: absolute;
            top: 42px;
            left: 50%;
            width: 98px;
            height: 44px;
            transform: translateX(-50%);
            border-radius: 24px;
            background: radial-gradient(circle at 30% 28%, rgba(255,255,255,0.7), rgba(255,255,255,0) 42%),
                        linear-gradient(165deg, #f7d7eb 0%, #e89ac5 45%, #cf6ea6 100%);
            box-shadow:
                inset 0 -5px 8px rgba(129, 60, 96, 0.24),
                inset 0 2px 3px rgba(255,255,255,0.45),
                0 8px 14px rgba(107, 52, 80, 0.24);
            border: 1px solid rgba(186, 113, 154, 0.42);
        }

        .ribbon-knot::before,
        .ribbon-knot::after {
            content: '';
            position: absolute;
            top: 8px;
            width: 26px;
            height: 26px;
            border-radius: 50% 55% 55% 50%;
            background: linear-gradient(145deg, #fbe4f3, #e18cbe 68%);
            border: 1px solid rgba(186, 113, 154, 0.35);
        }

        .ribbon-knot::before {
            left: -17px;
            transform: rotate(-14deg);
        }

        .ribbon-knot::after {
            right: -17px;
            transform: scaleX(-1) rotate(-14deg);
        }

        .opening-btn {
            position: absolute;
            left: 50%;
            top: 77%;
            transform: translate(-50%, -50%);
            width: min(90%, 380px);
            border: 1.5px solid rgba(177, 121, 165, 0.62);
            border-radius: 999px;
            background: linear-gradient(160deg, rgba(255, 244, 250, 0.96), rgba(246, 236, 255, 0.96));
            color: #6a3f5d;
            font-family: inherit;
            font-size: clamp(0.88rem, 2.5vw, 1rem);
            font-weight: 700;
            letter-spacing: 0.2px;
            padding: 10px 14px;
            cursor: pointer;
            z-index: 7;
            box-shadow: 0 8px 20px rgba(122, 73, 105, 0.2);
            transition: transform 0.26s ease, box-shadow 0.26s ease, opacity 0.35s ease;
        }

        .opening-btn:hover,
        .opening-btn:focus-visible {
            transform: translate(-50%, -52%);
            box-shadow: 0 12px 24px rgba(122, 73, 105, 0.24);
        }

        .opening-btn:focus-visible {
            outline: 2px solid rgba(184, 127, 161, 0.55);
            outline-offset: 2px;
        }

        .opening-btn:disabled {
            cursor: default;
        }

        .opening-sparkles {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 8;
        }

        .opening-sparkles span {
            position: absolute;
            left: 50%;
            top: 50%;
            width: 7px;
            height: 7px;
            opacity: 0;
            transform: translate(-50%, -50%) scale(0.35);
            border-radius: 2px;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.98), rgba(255, 210, 120, 0.9));
            box-shadow: 0 0 10px rgba(255, 220, 150, 0.84);
        }

        .opening-sparkles span:nth-child(1)  { --dx: -190px; --dy: -140px; --delay: 20ms; }
        .opening-sparkles span:nth-child(2)  { --dx: -145px; --dy: -180px; --delay: 65ms; }
        .opening-sparkles span:nth-child(3)  { --dx: -85px;  --dy: -160px; --delay: 95ms; }
        .opening-sparkles span:nth-child(4)  { --dx: -20px;  --dy: -190px; --delay: 130ms; }
        .opening-sparkles span:nth-child(5)  { --dx: 62px;   --dy: -172px; --delay: 165ms; }
        .opening-sparkles span:nth-child(6)  { --dx: 135px;  --dy: -125px; --delay: 210ms; }
        .opening-sparkles span:nth-child(7)  { --dx: 178px;  --dy: -72px;  --delay: 240ms; }
        .opening-sparkles span:nth-child(8)  { --dx: -170px; --dy: -58px;  --delay: 128ms; }
        .opening-sparkles span:nth-child(9)  { --dx: -110px; --dy: 36px;   --delay: 195ms; }
        .opening-sparkles span:nth-child(10) { --dx: 118px;  --dy: 46px;   --delay: 225ms; }
        .opening-sparkles span:nth-child(11) { --dx: 168px;  --dy: -18px;  --delay: 265ms; }
        .opening-sparkles span:nth-child(12) { --dx: 20px;   --dy: -128px; --delay: 296ms; }

        .opening-shell.is-untying .opening-btn {
            opacity: 0;
            transform: translate(-50%, -40%) scale(0.95);
        }

        .opening-shell.is-untying .ribbon-band.left {
            animation: ribbonLeftFly 0.85s cubic-bezier(0.28, 0.74, 0.2, 1) forwards;
        }

        .opening-shell.is-untying .ribbon-band.right {
            animation: ribbonRightFly 0.85s cubic-bezier(0.28, 0.74, 0.2, 1) forwards;
        }

        .opening-shell.is-untying .ribbon-tail.left {
            animation: tailLeftFly 0.9s ease-out forwards;
        }

        .opening-shell.is-untying .ribbon-tail.right {
            animation: tailRightFly 0.9s ease-out forwards;
        }

        .opening-shell.is-untying .ribbon-knot {
            animation: knotRelease 0.75s cubic-bezier(0.2, 0.8, 0.25, 1) forwards;
        }

        .opening-shell.is-opening .opening-door.left {
            animation: openDoorLeft 1.2s cubic-bezier(0.66, 0.02, 0.16, 1) forwards;
        }

        .opening-shell.is-opening .opening-door.right {
            animation: openDoorRight 1.2s cubic-bezier(0.66, 0.02, 0.16, 1) forwards;
        }

        .opening-shell.is-opening .door-center-line {
            opacity: 0;
        }

        .opening-shell.is-opened .opening-sparkles span {
            animation: sparkleBurst 1.1s ease-out forwards;
            animation-delay: var(--delay);
        }

        @keyframes ribbonLeftFly {
            0% { transform: translateX(0) rotate(0deg); opacity: 1; }
            100% { transform: translate(-148px, -20px) rotate(-16deg) scale(0.9); opacity: 0; }
        }

        @keyframes ribbonRightFly {
            0% { transform: translateX(0) rotate(0deg); opacity: 1; }
            100% { transform: translate(148px, -20px) rotate(16deg) scale(0.9); opacity: 0; }
        }

        @keyframes tailLeftFly {
            0% { transform: rotate(5deg) translateX(0); opacity: 1; }
            100% { transform: rotate(-22deg) translate(-126px, -48px) scale(0.86); opacity: 0; }
        }

        @keyframes tailRightFly {
            0% { transform: scaleX(-1) rotate(5deg) translateX(0); opacity: 1; }
            100% { transform: scaleX(-1) rotate(-22deg) translate(-126px, -48px) scale(0.86); opacity: 0; }
        }

        @keyframes knotRelease {
            0% { transform: translateX(-50%) scale(1) rotate(0deg); opacity: 1; }
            40% { transform: translateX(-50%) scale(1.05) rotate(-4deg); opacity: 1; }
            100% { transform: translateX(-50%) scale(0.55) rotate(10deg) translateY(-30px); opacity: 0; }
        }

        @keyframes openDoorLeft {
            0% { transform: rotateY(0deg); filter: brightness(1); }
            100% { transform: rotateY(-108deg); filter: brightness(0.9); }
        }

        @keyframes openDoorRight {
            0% { transform: rotateY(0deg); filter: brightness(1); }
            100% { transform: rotateY(108deg); filter: brightness(0.9); }
        }

        @keyframes sparkleBurst {
            0% { opacity: 0; transform: translate(-50%, -50%) scale(0.3); }
            20% { opacity: 0.96; }
            100% { opacity: 0; transform: translate(calc(-50% + var(--dx)), calc(-50% + var(--dy))) rotate(25deg) scale(1.04); }
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        .balloon-layer {
            position: absolute;
            inset: 8px;
            bottom: 108px;
            border-radius: 3px;
            pointer-events: none;
            overflow: hidden;
            z-index: 1;
            opacity: 0;
            transition: opacity 0.45s ease;
        }

        body.page-loaded .inv-card .balloon-layer { opacity: 1; }

        .balloon {
            position: absolute;
            bottom: -140px;
            width: 30px;
            height: 40px;
            border-radius: 55% 55% 50% 50%;
            opacity: 0;
            box-shadow: inset -7px -10px 15px rgba(255,255,255,0.22);
            animation: balloonRise 20s linear infinite;
            animation-play-state: paused;
            will-change: transform, opacity;
        }

        body.page-loaded .inv-card .balloon { animation-play-state: running; }

        .balloon::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 100%;
            width: 1.5px;
            height: 50px;
            background: rgba(112, 78, 47, 0.4);
            transform: translateX(-50%);
        }

        .balloon::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: -5px;
            width: 8px;
            height: 7px;
            background: inherit;
            clip-path: polygon(50% 100%, 0 0, 100% 0);
            transform: translateX(-50%);
        }

        .b1 { left: 5%;  background: linear-gradient(170deg, #ff9ccd, #f06292); animation-duration: 22s; animation-delay: 0s; }
        .b2 { left: 16%; background: linear-gradient(170deg, #9fd8ff, #6ab8f6); animation-duration: 19s; animation-delay: 3s; }
        .b3 { left: 30%; background: linear-gradient(170deg, #ffb6df, #ff7fb9); animation-duration: 24s; animation-delay: 5s; }
        .b4 { left: 48%; background: linear-gradient(170deg, #a9e2ff, #7bc8ff); animation-duration: 21s; animation-delay: 1.5s; }
        .b5 { left: 66%; background: linear-gradient(170deg, #ffc2e5, #ff8abf); animation-duration: 20s; animation-delay: 6s; }
        .b6 { left: 82%; background: linear-gradient(170deg, #b9e6ff, #87ceff); animation-duration: 23s; animation-delay: 2s; }
        .b7 { left: 92%; background: linear-gradient(170deg, #ffd0ec, #ffa0cc); animation-duration: 18s; animation-delay: 7s; }
        .b8 { left: 10%; background: linear-gradient(170deg, #ffc4e5, #ff93c3); animation-duration: 25s; animation-delay: 4s; }
        .b9 { left: 22%; background: linear-gradient(170deg, #bde8ff, #8ad0ff); animation-duration: 20s; animation-delay: 8s; }
        .b10 { left: 37%; background: linear-gradient(170deg, #ffb3da, #ff7fb6); animation-duration: 26s; animation-delay: 2.8s; }
        .b11 { left: 55%; background: linear-gradient(170deg, #a9deff, #74c4ff); animation-duration: 19s; animation-delay: 9s; }
        .b12 { left: 72%; background: linear-gradient(170deg, #ffd4ee, #ff9fcb); animation-duration: 23s; animation-delay: 4.6s; }
        .b13 { left: 86%; background: linear-gradient(170deg, #c7ecff, #92d6ff); animation-duration: 21s; animation-delay: 10s; }
        .b14 { left: 96%; background: linear-gradient(170deg, #ffc7e8, #ff95c6); animation-duration: 24s; animation-delay: 6.8s; }

        @keyframes balloonRise {
            0%   { transform: translate3d(0, 0, 0) scale(0.82) rotate(0deg); opacity: 0; }
            10%  { opacity: 0.55; }
            45%  { transform: translate3d(16px, -45vh, 0) scale(0.92) rotate(4deg); }
            75%  { transform: translate3d(-14px, -90vh, 0) scale(1) rotate(-4deg); opacity: 0.55; }
            100% { transform: translate3d(10px, -130vh, 0) scale(1.08) rotate(2deg); opacity: 0; }
        }

        /* ── Card wrapper ────────────────────────── */
        .card-wrap {
            width: min(100%, 520px);
            display: flex;
            flex-direction: column;
            gap: 16px;
            position: relative;
            z-index: 2;
        }

        /* ── Main Invitation Card ────────────────── */
        .inv-card {
            position: relative;
            background:
                repeating-linear-gradient(
                    45deg,
                    rgba(255, 138, 192, 0.08) 0px, rgba(255, 138, 192, 0.08) 1px,
                    transparent 1px, transparent 18px
                ),
                linear-gradient(170deg, #ffe9f5 0%, #f7ebff 48%, #e9f3ff 100%);
            border-radius: 6px;
            border: 2px solid rgba(101, 156, 214, 0.55);
            padding: 0 0 100px; /* extra bottom padding for bottom ornaments */
            overflow: visible;   /* let ornaments breathe without clipping */
            box-shadow:
                inset 0 0 0 6px rgba(255, 166, 212, 0.14),
                0 12px 40px rgba(88, 129, 173, 0.2);
            animation: fadeUp 0.9s ease both;
        }

        /* Inner border line */
        .inv-card::before {
            content: '';
            position: absolute;
            inset: 8px;
            bottom: 108px; /* match the extra padding */
            border: 1px solid rgba(132, 178, 227, 0.45);
            border-radius: 3px;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes fadeUp {
            from { opacity:0; transform: translateY(28px); }
            to   { opacity:1; transform: translateY(0); }
        }

        /* ── Corner ornaments ────────────────────── */
        .orn {
            position: absolute;
            pointer-events: none;
            z-index: 4;
            filter: drop-shadow(0 3px 6px rgba(80,50,10,0.15));
            opacity: 0.92;
            user-select: none;
        }
        .orn-tl { top: 4px; left: 4px; width: 126px; animation: floatA 7s ease-in-out infinite; }
        .orn-tr { top: 6px; right: 8px; width: 90px; animation: floatB 8s ease-in-out infinite; transform-origin: top right; }
        .orn-bl { bottom: 0; left: 6px; width: 108px; animation: floatB 9s ease-in-out infinite; }
        .orn-br { bottom: 0; right: 6px; width: 108px; animation: floatA 7.5s ease-in-out infinite; }

        @keyframes floatA {
            0%,100% { transform: translateY(0)   rotate(0deg); }
            50%      { transform: translateY(-7px) rotate(-1deg); }
        }
        @keyframes floatB {
            0%,100% { transform: translateY(0)  rotate(0deg); }
            50%      { transform: translateY(7px) rotate(1deg); }
        }

        /* ── Card content ────────────────────────── */
        .card-content {
            position: relative;
            z-index: 3;
            text-align: center;
            padding: 28px 32px 20px;
        }

        /* Ganesh Icon */
        .ganesh-wrap {
            margin: 0 auto 4px;
            width: 60px;
            height: 60px;
            display: grid;
            place-items: center;
        }
        .ganesh-wrap img { width: 52px; height: 52px; object-fit: contain; }

        /* Ganesh line */
        .ganesh-text {
            font-size: 0.85rem;
            font-weight: 600;
            color: #5a3015;
            letter-spacing: 1.5px;
            margin-bottom: 6px;
        }

        /* Main title */
        .main-title {
            font-family: 'Noto Serif Gujarati', serif;
            font-size: clamp(2rem, 8vw, 2.8rem);
            font-weight: 800;
            color: #3a1a05;
            line-height: 1.1;
            margin: 0 0 14px;
            letter-spacing: 0.5px;
        }

        /* Lead lines */
        .lead-block {
            font-size: clamp(0.82rem, 2.8vw, 0.96rem);
            font-weight: 500;
            color: #4a2e10;
            line-height: 1.75;
            margin-bottom: 10px;
        }

        /* Father/husband line */
        .relation-line {
            font-size: clamp(0.82rem, 2.6vw, 0.9rem);
            color: #6b4020;
            font-weight: 600;
            margin-bottom: 15px;
        }

        /* Mother's name – big display */
        .mother-name {
            font-family: 'Noto Serif Gujarati', serif;
            font-size: clamp(1.8rem, 7.5vw, 2.5rem);
            font-weight: 800;
            color: #2b1204;
            letter-spacing: 1px;
            line-height: 1.1;
            margin: 2px 0 10px;
        }

        /* Special / program line */
        .invite-line {
            font-size: clamp(0.8rem, 2.5vw, 0.9rem);
            color: #5a3820;
            font-weight: 400;
            line-height: 1.7;
            margin-bottom: 4px;
        }

        /* ── Dashed divider ──────────────────────── */
        .dash-divider {
            border: none;
            border-top: 1.5px dashed rgba(120, 80, 30, 0.5);
            margin: 14px auto;
            width: 75%;
        }

        /* ── Detail section ──────────────────────── */
        .detail-section {
            margin-bottom: 4px;
        }

        .section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #8b1a1a;
            letter-spacing: 2px;
            margin-bottom: 6px;
        }

        .detail-line {
            font-size: clamp(0.82rem, 2.6vw, 0.92rem);
            color: #3e2510;
            font-weight: 500;
            line-height: 1.7;
        }

        .detail-line strong {
            color: #1e0a00;
            font-weight: 700;
        }

        .venue-name {
            font-size: clamp(0.9rem, 3vw, 1rem);
            font-weight: 700;
            color: #2b1204;
        }

        .venue-photo-wrap {
            width: min(100%, 220px);
            margin: 10px auto 2px;
        }

        .venue-photo {
            width: 100%;
            height: auto;
            border-radius: 8px;
            border: 1.5px solid rgba(120, 80, 30, 0.35);
            box-shadow: 0 5px 16px rgba(80, 50, 10, 0.2);
        }

        .inviter-name {
            font-size: clamp(0.88rem, 2.8vw, 1rem);
            font-weight: 600;
            color: #2b1204;
            line-height: 1.7;
        }

        /* ── Bottom utils panel ──────────────────── */
        .util-panel {
            background:
                linear-gradient(160deg, #ffeef8 0%, #edf5ff 100%);
            border: 1.5px solid rgba(116, 167, 220, 0.45);
            border-radius: 6px;
            padding: 18px 18px 14px;
            box-shadow:
                0 0 0 5px rgba(255, 168, 214, 0.12),
                0 8px 28px rgba(90, 129, 172, 0.16);
            animation: fadeUp 0.9s 0.15s ease both;
        }

        /* Action button */
        .btn-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
            margin-bottom: 0;
        }

        .ic-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 1.5px solid rgba(140,90,30,0.55);
            border-radius: 6px;
            background: linear-gradient(175deg, #f5e8c0, #e8d490);
            color: #3a200a;
            font-family: inherit;
            font-size: clamp(0.75rem, 2.4vw, 0.85rem);
            font-weight: 700;
            padding: 10px 8px;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 3px 8px rgba(100,70,20,0.15);
        }

        .ic-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(100,70,20,0.22);
        }

        .ic-btn svg { flex-shrink: 0; }

        /* RSVP panel */
        .rsvp-panel {
            background: linear-gradient(160deg, #ffeef8 0%, #edf5ff 100%);
            border: 1.5px solid rgba(116, 167, 220, 0.45);
            border-radius: 6px;
            padding: 18px;
            box-shadow: 0 0 0 5px rgba(255, 168, 214, 0.12), 0 8px 28px rgba(90, 129, 172, 0.16);
            animation: fadeUp 0.9s 0.28s ease both;
        }

        .rsvp-title {
            text-align: center;
            font-size: 1.05rem;
            font-weight: 700;
            color: #8b1a1a;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }

        .rsvp-sub {
            text-align: center;
            font-size: 0.8rem;
            color: #7a5520;
            margin-bottom: 14px;
        }

        .alert {
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .alert.success { background:#e9f5e9; color:#1b5e20; border:1px solid #a5d6a7; }
        .alert.error   { background:#fdecea; color:#8b1a1a; border:1px solid #f4a9a1; }

        .form-lbl {
            display:block;
            font-size: 0.78rem;
            font-weight: 700;
            color: #5a3015;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
        }
        .form-grp { margin-bottom: 10px; }

        .form-inp {
            width: 100%;
            border: 1.5px solid rgba(155,110,45,0.45);
            border-radius: 6px;
            padding: 10px 12px;
            font-family: inherit;
            font-size: 0.9rem;
            color: #2b1204;
            background: rgba(255,255,255,0.85);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-inp:focus {
            border-color: #8b1a1a;
            box-shadow: 0 0 0 3px rgba(139,26,26,0.12);
            background: #fff;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(175deg, #a02020, #7a0f0f);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 14px rgba(120,20,20,0.3);
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 4px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 7px 18px rgba(120,20,20,0.38); }

        /* ── Responsive ──────────────────────────── */
        @media (max-width: 420px) {
            .opening-stage {
                border-radius: 10px;
                padding: 14px 10px 12px;
            }
            .opening-overline {
                font-size: 0.82rem;
            }
            .opening-title {
                font-size: clamp(1.8rem, 9vw, 2.35rem);
            }
            .opening-ganesh-wrap img {
                width: 36px;
                height: 36px;
            }
            .opening-shell {
                border-radius: 14px;
            }
            .door-inner {
                padding-top: 26px;
            }
            .door-top-decor {
                
            }
            .opening-btn {
                width: min(92%, 320px);
                font-size: clamp(0.78rem, 3.2vw, 0.9rem);
                padding: 9px 12px;
            }
            .ribbon-band {
                width: 43%;
            }
            .ribbon-tail {
                width: 48px;
                height: 38px;
            }
            .orn-tl { width: 98px; top: 4px; left: 4px; }
            .orn-tr { width: 70px; top: 4px; right: 5px; }
            .orn-bl { width: 84px; left: 4px; bottom: 2px; }
            .orn-br { width: 84px; right: 4px; bottom: 2px; }
            .balloon { width: 24px; height: 34px; }
            .card-content { padding: 14px 16px 16px; }
            .btn-row { grid-template-columns: 1fr; }
            .inv-card { padding-bottom: 64px; overflow: hidden; }
            .inv-card::before { bottom: 72px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body
    data-page="invite"
    data-whatsapp-message="<?php echo e($whatsAppMessage); ?>"
>

<div id="openingStage" class="opening-stage" aria-label="આમંત્રણ કવર">
    <div class="opening-head">
        <div class="opening-overline">સ્નેહભર્યું આમંત્રણ</div>
        <div class="opening-title"><?php echo e($title !== '' ? $title : 'સીમંત વિધિ'); ?></div>
        <div class="opening-ganesh-wrap">
            <img src="<?php echo e(assetUrl($ganeshImage)); ?>" alt="ગણેશજી">
        </div>
    </div>

    <div id="openingShell" class="opening-shell">
        <div class="opening-door left">
            <div class="door-inner">
                <span class="door-word">શુભ</span>
            </div>
        </div>
        <div class="opening-door right">
            <div class="door-inner">
                <span class="door-word">લાભ</span>
            </div>
        </div>
        <span class="door-center-line" aria-hidden="true"></span>
        <div class="door-top-decor" aria-hidden="true">
            <img src="<?php echo e(assetUrl($doorCenterImage)); ?>" alt="" class="center-deity">
        </div>

        <div class="opening-ribbon" aria-hidden="true">
            <span class="ribbon-band left"></span>
            <span class="ribbon-band right"></span>
            <span class="ribbon-tail left"></span>
            <span class="ribbon-tail right"></span>
            <span class="ribbon-knot"></span>
        </div>

        <button
            id="openCoverBtn"
            type="button"
            class="opening-btn"
            onclick="if(this.dataset.running==='1'){return;} this.dataset.running='1'; this.disabled=true; var sh=document.getElementById('openingShell'); if(sh){ sh.classList.add('is-untying'); setTimeout(function(){sh.classList.add('is-opening');},760); setTimeout(function(){sh.classList.add('is-opened'); document.body.classList.add('gate-opened');},1980);} if(window.openInvitationGate){window.openInvitationGate(); }"
        >
            🎀 રિબન ખોલવા માટે અહીં ક્લિક કરો 🎀
        </button>

        <div class="opening-sparkles" aria-hidden="true">
            <span></span><span></span><span></span><span></span><span></span><span></span>
            <span></span><span></span><span></span><span></span><span></span><span></span>
        </div>
    </div>
    <p id="openingStatus" class="sr-only" aria-live="polite">કાર્ડ બંધ છે.</p>
</div>

<div class="card-wrap">

    <!-- ══════════ INVITATION CARD ══════════ -->
    <div id="invitationCard" class="inv-card">

        <div class="balloon-layer" aria-hidden="true">
            <span class="balloon b1"></span>
            <span class="balloon b2"></span>
            <span class="balloon b3"></span>
            <span class="balloon b4"></span>
            <span class="balloon b5"></span>
            <span class="balloon b6"></span>
            <span class="balloon b7"></span>
            <span class="balloon b8"></span>
            <span class="balloon b9"></span>
            <span class="balloon b10"></span>
            <span class="balloon b11"></span>
            <span class="balloon b12"></span>
            <span class="balloon b13"></span>
            <span class="balloon b14"></span>
        </div>

        <!-- Corner ornaments -->
        <img src="<?php echo e(assetUrl($cornerImages['top_left'])); ?>" alt="" class="orn orn-tl">
        <img src="<?php echo e(assetUrl($cornerImages['top_right'])); ?>" alt="" class="orn orn-tr">
        <img src="<?php echo e(assetUrl($cornerImages['bottom_left'])); ?>" alt="" class="orn orn-bl">
        <img src="<?php echo e(assetUrl($cornerImages['bottom_right'])); ?>" alt="" class="orn orn-br">

        <div class="card-content">

            <!-- Ganesh -->
            <div class="ganesh-wrap">
                <img src="<?php echo e(assetUrl($ganeshImage)); ?>" alt="ગણેશજી">
            </div>
            <p class="ganesh-text"><?php echo e($ganeshLine); ?></p>

            <!-- Main title -->
            <h1 class="main-title"><?php echo e($title); ?></h1>

            <!-- Lead invitation lines -->
            <div class="lead-block">
                <?php foreach ($leadLines as $line): ?>
                    <div><?php echo e($line); ?></div>
                <?php endforeach; ?>
            </div>

            <?php if ($specialLine !== ''): ?>
                <p class="invite-line"><?php echo e($specialLine); ?></p>
            <?php endif; ?>

            <!-- Father + Mother -->
            <?php if ($fatherName !== ''): ?>
                <p class="relation-line">
                    <?php echo e($fatherName); ?> ના ધર્મ&shy;પત્ની
                </p>
            <?php endif; ?>

            <?php if ($motherName !== ''): ?>
                <div class="mother-name"><?php echo e($motherName); ?></div>
            <?php endif; ?>

            <?php if ($programLine !== ''): ?>
                <p class="invite-line"><?php echo e($programLine); ?></p>
            <?php endif; ?>

            <!-- ══ MEAL ══ -->
            <hr class="dash-divider">

            <div class="detail-section">
                <div class="section-title">:: ભોજન ::</div>
                <?php if ($mealDateText !== ''): ?>
                    <div class="detail-line"><strong>તા.</strong> <?php echo e($mealDateText); ?></div>
                <?php endif; ?>
                <?php if ($mealTimeText !== ''): ?>
                    <div class="detail-line"><?php echo e($mealTimeText); ?></div>
                <?php endif; ?>
                <?php if ($mealNote !== ''): ?>
                    <div class="detail-line"><?php echo e($mealNote); ?></div>
                <?php endif; ?>
            </div>

            <!-- ══ VENUE ══ -->
            <hr class="dash-divider">

            <div class="detail-section">
                <div class="section-title">:: સ્થળ ::</div>
                <?php foreach ($venueLines as $i => $line): ?>
                    <div class="detail-line <?php echo $i === 0 ? 'venue-name' : ''; ?>"><?php echo e($line); ?></div>
                <?php endforeach; ?>
                <?php if ($venueImage !== ''): ?>
                    <div class="venue-photo-wrap">
                        <img src="<?php echo e(assetUrl($venueImage)); ?>" alt="<?php echo e(($venueName !== '' ? $venueName : 'સ્થળ') . ' ફોટો'); ?>" class="venue-photo">
                    </div>
                <?php endif; ?>
            </div>

            <!-- ══ INVITERS ══ -->
            <hr class="dash-divider">

            <div class="detail-section">
                <div class="section-title">:: નિમંત્રક ::</div>
                <?php foreach ($inviters as $line): ?>
                    <div class="inviter-name"><?php echo e($line); ?></div>
                <?php endforeach; ?>
            </div>

        </div><!-- /card-content -->
    </div><!-- /inv-card -->

    <!-- ══════════ UTILITY PANEL ══════════ -->
    <div class="util-panel">

        <div class="btn-row">
            <a class="ic-btn" href="<?php echo e($mapsUrl); ?>" target="_blank" rel="noopener noreferrer">
                <svg width="15" height="15" fill="none" stroke="#5a3015" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                સરનામે જવા માટે ક્લિક કરો
            </a>
        </div>

    </div><!-- /util-panel -->

    <!-- ══════════ RSVP PANEL ══════════ -->
    <?php if (!empty($invitation['rsvp_enabled'])): ?>
        <div class="rsvp-panel">
            <div class="rsvp-title">:: RSVP ::</div>
            <p class="rsvp-sub">કૃપા કરીને આવ-જવ ની ખાતરી કરો.</p>

            <?php if ($flash['type'] !== ''): ?>
                <div class="alert <?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo e($selfAction); ?>">
                <input type="hidden" name="form_type" value="rsvp">

                <div class="form-grp">
                    <label class="form-lbl" for="name">નામ</label>
                    <input type="text" id="name" name="name" class="form-inp" required maxlength="80" value="<?php echo e($old['name']); ?>" placeholder="આપનું નામ">
                </div>
                <div class="form-grp">
                    <label class="form-lbl" for="mobile">મોબાઇલ</label>
                    <input type="tel" id="mobile" name="mobile" class="form-inp" required maxlength="15" value="<?php echo e($old['mobile']); ?>" placeholder="+91 98xxxxxx">
                </div>
                <div class="form-grp">
                    <label class="form-lbl" for="guests">અતિથિઓ</label>
                    <input type="number" id="guests" name="guests" class="form-inp" required min="1" max="25" value="<?php echo e($old['guests']); ?>">
                </div>
                <button type="submit" class="btn-submit">RSVP મોકલો</button>
            </form>
        </div>
    <?php endif; ?>

</div><!-- /card-wrap -->

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="assets/js/main.js?v=<?php echo rawurlencode((string)(@filemtime(__DIR__ . '/assets/js/main.js') ?: time())); ?>"></script>
<script>
window.addEventListener('load', function () {
    document.body.classList.add('page-loaded');
});
</script>
</body>
</html>
