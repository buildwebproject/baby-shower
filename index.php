<?php

declare(strict_types=1);

require __DIR__ . '/includes/invitation_store.php';
$invitation = invitation_load_data();

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
$venueImage  = firstExistingPublicPath([
    (string)($invitation['venue_image'] ?? ''),
], '');

$memorySlides = [
    [
        'label' => 'Couple Photo',
        'path' => firstExistingPublicPath([
            (string)($invitation['memory_couple_photo'] ?? ''),
            (string)($invitation['couple_photo'] ?? ''),
            'assets/images/image-1.png',
        ], 'assets/images/image-1.png'),
    ],
    [
        'label' => 'Baby Scan Photo',
        'path' => firstExistingPublicPath([
            (string)($invitation['memory_baby_scan_photo'] ?? ''),
            (string)($invitation['baby_scan_photo'] ?? ''),
            'assets/images/image-2.png',
        ], 'assets/images/image-2.png'),
    ],
    [
        'label' => 'Family Photo',
        'path' => firstExistingPublicPath([
            (string)($invitation['memory_family_photo'] ?? ''),
            (string)($invitation['family_photo'] ?? ''),
            'assets/images/image-3.png',
        ], 'assets/images/image-3.png'),
    ],
];

$eventDateTimeRaw = trim((string)($invitation['event_datetime'] ?? ''));
$countdownTargetMs = null;
if ($eventDateTimeRaw !== '') {
    $eventTimestamp = strtotime($eventDateTimeRaw);
    if ($eventTimestamp !== false && $eventTimestamp > time()) {
        $countdownTargetMs = $eventTimestamp * 1000;
    }
}

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
$mapsUrl         = trim($mapsUrl);
if ($mapsUrl === '' || $mapsUrl === 'https://share.google/kWK4z4pDiLebfvpTQ') {
    $mapsUrl = 'https://maps.app.goo.gl/dpNe2PrEfzKqUfcV7';
}
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

        /* ── Top Balloon Mini Interaction ───────── */
        .mini-balloons {
            position: fixed;
            top: 8px;
            left: 50%;
            transform: translateX(-50%);
            width: min(220px, calc(100vw - 24px));
            height: 80px;
            z-index: 10030;
            pointer-events: none;
        }

        body:not(.gate-opened) .mini-balloons {
            opacity: 0;
            pointer-events: none;
            visibility: hidden;
        }

        .mini-balloons__items {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .mini-balloon {
            position: absolute;
            top: 8px;
            width: 48px;
            height: 62px;
            border: 1px solid rgba(182, 96, 149, 0.52);
            border-radius: 50% 50% 46% 46%;
            display: grid;
            place-items: center;
            color: rgba(255, 255, 255, 0.94);
            text-shadow: 0 1px 2px rgba(95, 43, 73, 0.24);
            font-size: 1.18rem;
            box-shadow: 0 6px 14px rgba(138, 77, 111, 0.24);
            cursor: pointer;
            pointer-events: auto;
            animation: miniBalloonFloat 3.8s ease-in-out infinite;
            transition: transform 0.18s ease, opacity 0.2s ease;
            will-change: transform, opacity;
        }

        .mini-balloon::before {
            content: '';
            position: absolute;
            top: calc(100% - 2px);
            left: 50%;
            width: 2px;
            height: 18px;
            transform: translateX(-50%);
            background: linear-gradient(180deg, rgba(192, 119, 162, 0.86), rgba(192, 119, 162, 0.05));
        }

        .mini-balloon::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            width: 9px;
            height: 8px;
            transform: translateX(-50%);
            border-radius: 2px;
            background: rgba(190, 116, 159, 0.9);
        }

        .mini-balloon:nth-child(1) {
            left: 8px;
            background: linear-gradient(165deg, #ff9fd2 0%, #e056a0 100%);
            animation-delay: 0s;
        }

        .mini-balloon:nth-child(2) {
            left: calc(50% - 24px);
            background: linear-gradient(165deg, #ffb0cf 0%, #de6ca6 100%);
            animation-delay: 0.25s;
        }

        .mini-balloon:nth-child(3) {
            right: 8px;
            background: linear-gradient(165deg, #ff9bc4 0%, #d95694 100%);
            animation-delay: 0.55s;
        }

        .mini-balloon:hover {
            transform: translateY(-2px) scale(1.03);
        }

        .mini-balloon.is-popping {
            animation: miniBalloonPop 340ms cubic-bezier(0.2, 0.68, 0.2, 1) forwards;
            pointer-events: none;
        }

        .mini-balloon.is-popped {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            animation: none;
            transform: scale(0.4);
        }

        .mini-balloons__msg {
            position: absolute;
            top: 74px;
            left: 50%;
            transform: translateX(-50%) translateY(8px);
            width: max-content;
            max-width: min(320px, calc(100vw - 24px));
            border: 1px solid rgba(177, 104, 150, 0.5);
            border-radius: 999px;
            background: linear-gradient(165deg, rgba(255, 241, 249, 0.98), rgba(248, 227, 241, 0.98));
            color: #7a335c;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            text-align: center;
            padding: 8px 12px;
            box-shadow: 0 8px 18px rgba(129, 70, 107, 0.2);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.26s ease, transform 0.26s ease;
        }

        .mini-balloons.show-msg .mini-balloons__msg {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .mini-confetti {
            position: absolute;
            left: 50%;
            top: 48%;
            width: 8px;
            height: 12px;
            border-radius: 2px;
            opacity: 0;
            pointer-events: none;
            animation: miniConfettiBlast 680ms ease-out forwards;
            transform: translate(-50%, -50%) rotate(0deg);
        }

        .scroll-petals {
            position: fixed;
            inset: 0;
            z-index: 10005;
            pointer-events: none;
            overflow: hidden;
        }

        .scroll-petal {
            position: absolute;
            top: -10vh;
            left: 50vw;
            width: var(--petal-size, 10px);
            height: calc(var(--petal-size, 10px) * 1.36);
            opacity: var(--petal-opacity, 0.28);
            background: linear-gradient(165deg, rgba(255, 215, 232, 0.92), rgba(255, 176, 210, 0.72));
            border-radius: 70% 48% 72% 46%;
            box-shadow: inset -1px -1px 3px rgba(255, 143, 187, 0.22);
            transform: translate3d(0, 0, 0) rotate(0deg);
            will-change: transform, opacity;
            animation: scrollPetalFall var(--petal-duration, 11200ms) linear forwards;
        }

        @keyframes scrollPetalFall {
            0% {
                opacity: 0;
                transform: translate3d(0, -6vh, 0) rotate(0deg);
            }
            12% {
                opacity: var(--petal-opacity, 0.28);
            }
            100% {
                opacity: 0;
                transform: translate3d(var(--petal-drift, 42px), 112vh, 0) rotate(var(--petal-rotate, 260deg));
            }
        }

        @keyframes miniBalloonFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-7px); }
        }

        @keyframes miniBalloonPop {
            0% { opacity: 1; transform: scale(1); }
            45% { opacity: 1; transform: scale(1.16); }
            100% { opacity: 0; transform: scale(0.48); }
        }

        @keyframes miniConfettiBlast {
            0% {
                opacity: 1;
                transform: translate(-50%, -50%) rotate(0deg);
            }
            100% {
                opacity: 0;
                transform: translate(calc(-50% + var(--tx, 0px)), calc(-50% + var(--ty, 0px))) rotate(var(--rot, 0deg));
            }
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

        .opening-ganesh-text {
            margin-top: 4px;
            color: #6b3f1d;
            font-size: 0.86rem;
            font-weight: 700;
            letter-spacing: 0.6px;
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

        .opening-loader {
            position: absolute;
            inset: 10px;
            border-radius: 14px;
            z-index: 1;
            pointer-events: none;
            opacity: 0;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at 26% 24%, rgba(255, 255, 255, 0.75), rgba(255, 255, 255, 0) 36%),
                linear-gradient(150deg, rgba(255, 250, 253, 0.93) 0%, rgba(246, 236, 255, 0.96) 52%, rgba(235, 244, 255, 0.96) 100%);
            border: 1px solid rgba(196, 160, 193, 0.35);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.65);
            transition: opacity 0.28s ease;
            overflow: hidden;
        }

        .opening-loader::before {
            content: '';
            position: absolute;
            top: -30%;
            bottom: -30%;
            left: -55%;
            width: 38%;
            background: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.52) 50%,
                rgba(255, 255, 255, 0) 100%
            );
            transform: rotate(10deg);
            animation: loaderSweep 1.35s linear infinite;
        }

        .opening-loader-inner {
            position: relative;
            z-index: 1;
            display: grid;
            place-items: center;
            gap: 10px;
            color: #7b4f67;
        }

        .opening-loader-ring {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            border: 4px solid rgba(216, 166, 196, 0.42);
            border-top-color: rgba(160, 97, 133, 0.92);
            animation: loaderSpin 1s linear infinite;
            box-shadow: 0 0 16px rgba(174, 122, 153, 0.18);
        }

        .opening-loader-text {
            font-size: 0.92rem;
            font-weight: 600;
            letter-spacing: 0.2px;
            text-align: center;
        }

        .opening-loader-dots {
            display: inline-flex;
            gap: 4px;
            margin-left: 2px;
            transform: translateY(-1px);
        }

        .opening-loader-dots span {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: rgba(145, 86, 120, 0.86);
            animation: loaderDot 1s ease-in-out infinite;
        }

        .opening-loader-dots span:nth-child(2) { animation-delay: 0.18s; }
        .opening-loader-dots span:nth-child(3) { animation-delay: 0.36s; }

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

        .opening-swastik {
            position: absolute;
            left: 50%;
            top: 9%;
            transform: translate(-50%, -50%);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #b14b84;
            font-size: 1.42rem;
            font-weight: 700;
            line-height: 1;
            background: radial-gradient(circle at 30% 30%, #ffeef8 0%, #f7cce3 52%, #e49cc3 100%);
            border: 1px solid rgba(178, 93, 142, 0.44);
            box-shadow:
                inset 0 2px 3px rgba(255, 255, 255, 0.56),
                0 6px 14px rgba(126, 64, 101, 0.22);
            z-index: 6;
            pointer-events: none;
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

        .opening-shell.is-opening .opening-loader,
        .opening-shell.is-opened .opening-loader {
            opacity: 1;
        }

        .opening-shell.is-opened .opening-loader {
            animation: loaderOut 0.42s ease forwards;
            animation-delay: 0.14s;
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

        @keyframes loaderSpin {
            100% { transform: rotate(360deg); }
        }

        @keyframes loaderSweep {
            0% { transform: translateX(0) rotate(10deg); opacity: 0; }
            12% { opacity: 0.88; }
            70% { opacity: 0.88; }
            100% { transform: translateX(430%) rotate(10deg); opacity: 0; }
        }

        @keyframes loaderDot {
            0%, 100% { transform: translateY(0); opacity: 0.45; }
            50% { transform: translateY(-4px); opacity: 1; }
        }

        @keyframes loaderOut {
            to { opacity: 0; transform: scale(0.98); }
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
            margin: 50px auto 4px;
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

        .memory-slider {
            margin: 12px auto 10px;
        }

        .memory-slider-title {
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: #8b1a1a;
            margin-bottom: 8px;
        }

        .memory-slider-stage {
            position: relative;
            width: 158px;
            height: 158px;
            margin: 0 auto;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid rgba(168, 102, 139, 0.58);
            box-shadow:
                0 8px 20px rgba(136, 82, 119, 0.26),
                inset 0 0 0 5px rgba(255, 196, 223, 0.4);
            background: linear-gradient(165deg, #fff3f8 0%, #f7efff 100%);
        }

        .memory-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.95s ease;
            z-index: 0;
        }

        .memory-slide.is-active {
            opacity: 1;
            z-index: 1;
        }

        .memory-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .memory-slider-caption {
            margin-top: 8px;
            font-size: 0.76rem;
            font-weight: 600;
            color: #6d3a57;
            min-height: 1.3em;
        }

        .memory-slider-dots {
            margin-top: 5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .memory-slider-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: rgba(180, 125, 160, 0.38);
            transition: transform 0.22s ease, background-color 0.22s ease;
        }

        .memory-slider-dot.is-active {
            background: rgba(193, 86, 143, 0.96);
            transform: scale(1.24);
        }

        .countdown-block {
            margin: 12px auto 8px;
            border: 1.5px solid rgba(171, 103, 144, 0.42);
            border-radius: 12px;
            background:
                linear-gradient(170deg, rgba(255, 242, 250, 0.95), rgba(246, 233, 255, 0.95));
            padding: 10px 12px;
            box-shadow: 0 7px 16px rgba(121, 71, 108, 0.14);
        }

        .countdown-title {
            color: #7f345f;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            margin-bottom: 8px;
        }

        .countdown-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 7px;
        }

        .countdown-item {
            border: 1px solid rgba(170, 111, 151, 0.32);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.65);
            padding: 7px 4px 6px;
        }

        .countdown-value {
            display: block;
            color: #5f2248;
            font-size: 1rem;
            line-height: 1;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .countdown-label {
            display: block;
            margin-top: 3px;
            color: #7c4c67;
            font-size: 0.64rem;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
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
            border-radius: 10px;
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
            gap: 7px;
            border: 1.5px solid rgba(176, 116, 160, 0.56);
            border-radius: 12px;
            background:
                linear-gradient(160deg, rgba(255, 245, 252, 0.98) 0%, rgba(248, 234, 255, 0.98) 52%, rgba(236, 244, 255, 0.98) 100%);
            color: #5f2f4f;
            font-family: inherit;
            font-size: clamp(0.75rem, 2.4vw, 0.85rem);
            font-weight: 700;
            padding: 11px 10px;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            box-shadow:
                inset 0 0 0 2px rgba(255, 255, 255, 0.64),
                0 5px 14px rgba(143, 92, 138, 0.2);
            position: relative;
            overflow: hidden;
        }

        .ic-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.34), rgba(255, 255, 255, 0));
            pointer-events: none;
        }

        .ic-btn:hover {
            transform: translateY(-2px);
            border-color: rgba(176, 97, 152, 0.72);
            box-shadow:
                inset 0 0 0 2px rgba(255, 255, 255, 0.7),
                0 8px 18px rgba(134, 77, 124, 0.27);
        }

        .ic-btn svg {
            flex-shrink: 0;
            stroke: #7d3f67;
        }

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

        /* BABY PLAY WIDGET START */
        .baby-play {
            position: fixed;
            right: 18px;
            bottom: 16px;
            z-index: 10020;
            width: 72px;
            height: 72px;
            color: #5e2d4f;
            font-family: 'Hind Vadodara', 'Noto Sans Gujarati', sans-serif;
            user-select: none;
        }

        body:not(.gate-opened) .baby-play {
            display: none;
        }

        .baby-play .baby-play__controls {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .baby-play .baby-play__tooltip {
            position: absolute;
            right: 0;
            bottom: calc(100% + 42px);
            max-width: 210px;
            border-radius: 10px;
            border: 1px solid rgba(161, 106, 145, 0.34);
            background: rgba(255, 247, 252, 0.97);
            color: #6e3d61;
            font-size: 0.74rem;
            line-height: 1.35;
            padding: 7px 10px;
            box-shadow: 0 7px 16px rgba(87, 51, 90, 0.2);
            opacity: 0;
            transform: translateY(6px) scale(0.98);
            transform-origin: right bottom;
            transition: opacity 0.22s ease, transform 0.22s ease;
            pointer-events: none;
            white-space: nowrap;
        }

        .baby-play .baby-play__blessing-toast {
            position: absolute;
            right: -2px;
            bottom: calc(100% + 78px);
            max-width: min(240px, calc(100vw - 26px));
            border-radius: 999px;
            border: 1px solid rgba(198, 109, 158, 0.44);
            background: linear-gradient(165deg, rgba(255, 243, 250, 0.99), rgba(254, 230, 243, 0.99));
            color: #7b305a;
            font-size: 0.73rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            line-height: 1.25;
            padding: 7px 11px;
            box-shadow: 0 8px 18px rgba(122, 67, 99, 0.22);
            opacity: 0;
            transform: translateY(8px) scale(0.97);
            transform-origin: right bottom;
            transition: opacity 0.2s ease, transform 0.2s ease;
            pointer-events: none;
            white-space: nowrap;
            z-index: 2;
        }

        .baby-play.show-blessing .baby-play__blessing-toast {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .baby-play.show-blessing .baby-play__tooltip {
            opacity: 0;
            transform: translateY(6px) scale(0.98);
        }

        .baby-play .baby-play__blessing-counter {
            position: absolute;
            right: -2px;
            bottom: calc(100% + 8px);
            border-radius: 999px;
            border: 1px solid rgba(172, 114, 152, 0.38);
            background: rgba(255, 247, 252, 0.96);
            color: #6f3f61;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            line-height: 1;
            padding: 7px 10px;
            box-shadow: 0 6px 14px rgba(95, 59, 95, 0.17);
            pointer-events: none;
            white-space: nowrap;
        }

        .baby-play .baby-play__tooltip::after {
            content: '';
            position: absolute;
            right: 14px;
            top: 100%;
            width: 9px;
            height: 9px;
            border-right: 1px solid rgba(161, 106, 145, 0.34);
            border-bottom: 1px solid rgba(161, 106, 145, 0.34);
            background: rgba(255, 247, 252, 0.97);
            transform: translateY(-4px) rotate(45deg);
        }

        .baby-play.show-tip .baby-play__tooltip,
        .baby-play:hover .baby-play__tooltip,
        .baby-play:focus-within .baby-play__tooltip,
        .baby-play.is-dragging .baby-play__tooltip {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .baby-play .baby-play__avatar {
            position: relative;
            width: 72px;
            height: 72px;
            border: 1px solid rgba(181, 122, 166, 0.45);
            border-radius: 50%;
            background:
                radial-gradient(circle at 34% 28%, rgba(255, 255, 255, 0.94), transparent 44%),
                linear-gradient(165deg, #ffeef7 0%, #ffd8ea 100%);
            display: grid;
            place-items: center;
            cursor: grab;
            touch-action: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 8px 18px rgba(153, 88, 128, 0.26);
        }

        .baby-play .baby-play__avatar:active {
            cursor: grabbing;
        }

        .baby-play .baby-play__avatar:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(153, 88, 128, 0.34);
        }

        .baby-play .baby-play__emoji {
            font-size: 2.2rem;
            line-height: 1;
            transform: translateY(2px);
            pointer-events: none;
            transition: opacity 0.16s ease, transform 0.24s cubic-bezier(0.2, 0.68, 0.2, 1), filter 0.24s ease;
            will-change: transform, opacity, filter;
        }

        .baby-play.is-mood-changing .baby-play__emoji {
            opacity: 0;
            transform: translateY(-4px) scale(0.88) rotate(-7deg);
            filter: saturate(1.05);
        }

        .baby-play.is-mood-popped .baby-play__emoji {
            opacity: 1;
            transform: translateY(2px) scale(1.08);
            filter: saturate(1.12);
        }

        .baby-play .baby-play__avatar-img {
            display: none;
            width: 46px;
            height: 46px;
            object-fit: contain;
            pointer-events: none;
        }

        .baby-play.has-custom-image .baby-play__avatar-img {
            display: block;
        }

        .baby-play.has-custom-image .baby-play__emoji {
            display: none;
        }

        .baby-play .baby-play__cheek {
            position: absolute;
            bottom: 21px;
            width: 11px;
            height: 7px;
            border-radius: 999px;
            background: rgba(234, 106, 130, 0.6);
            opacity: 0;
            filter: blur(0.2px);
            transition: opacity 0.22s ease;
        }

        .baby-play .baby-play__cheek--left {
            left: 17px;
            transform: rotate(-10deg);
        }

        .baby-play .baby-play__cheek--right {
            right: 17px;
            transform: rotate(10deg);
        }

        .baby-play .baby-play__hearts {
            position: absolute;
            left: 35px;
            bottom: 40px;
            width: 0;
            height: 0;
            overflow: visible;
            pointer-events: none;
        }

        .baby-play .baby-play__heart {
            position: absolute;
            left: 0;
            bottom: 0;
            opacity: 0;
            transform: translate(var(--heart-x, 0px), 0) scale(var(--heart-size, 1));
            font-size: calc(14px * var(--heart-size, 1));
            line-height: 1;
            animation: baby-play-heart-float var(--heart-duration, 1120ms) ease-out forwards;
            filter: drop-shadow(0 2px 3px rgba(186, 95, 141, 0.24));
        }

        .baby-play .baby-play__audio {
            display: none;
        }

        .baby-play.is-laughing .baby-play__avatar {
            animation: baby-play-laugh 0.78s cubic-bezier(0.2, 0.68, 0.2, 1);
        }

        .baby-play.is-laughing .baby-play__cheek {
            opacity: 0.95;
        }

        .baby-play.is-dragging .baby-play__avatar {
            transform: scale(1.04);
            box-shadow: 0 13px 24px rgba(153, 88, 128, 0.36);
        }

        @keyframes baby-play-laugh {
            0% { transform: scale(1) rotate(0deg); }
            20% { transform: scale(1.08) rotate(-8deg); }
            40% { transform: scale(1.1) rotate(8deg); }
            60% { transform: scale(1.08) rotate(-6deg); }
            80% { transform: scale(1.04) rotate(4deg); }
            100% { transform: scale(1) rotate(0deg); }
        }

        @keyframes baby-play-heart-float {
            0% {
                opacity: 0;
                transform: translate(var(--heart-x, 0px), 0) scale(var(--heart-size, 1));
            }
            15% {
                opacity: 1;
            }
            100% {
                opacity: 0;
                transform: translate(var(--heart-end-x, 8px), -84px) scale(calc(var(--heart-size, 1) * 1.16));
            }
        }
        /* BABY PLAY WIDGET END */

        /* ── Responsive ──────────────────────────── */
        @media (max-width: 420px) {
            .mini-balloons {
                top: 6px;
                width: min(190px, calc(100vw - 20px));
                height: 72px;
            }
            .mini-balloon {
                width: 42px;
                height: 56px;
                font-size: 1.04rem;
            }
            .mini-balloons__msg {
                top: 66px;
                font-size: 0.72rem;
                padding: 7px 10px;
            }
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
                width: 80px;
                height: 80px;
            }
            .opening-shell {
                border-radius: 14px;
            }
            .opening-swastik {
                top: 8.5%;
                width: 40px;
                height: 40px;
                font-size: 1.28rem;
            }
            .door-inner {
                padding-top: 26px;
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

            .baby-play {
                left: auto;
                right: 12px;
                bottom: 8px;
                width: 62px;
                height: 62px;
            }

            .baby-play .baby-play__tooltip {
                right: -2px;
                bottom: calc(100% + 38px);
                max-width: min(180px, calc(100vw - 28px));
                font-size: 0.67rem;
                padding: 6px 9px;
                white-space: normal;
            }

            .baby-play .baby-play__blessing-toast {
                right: -2px;
                bottom: calc(100% + 68px);
                max-width: min(200px, calc(100vw - 24px));
                font-size: 0.64rem;
                padding: 6px 9px;
                white-space: normal;
            }

            .baby-play .baby-play__blessing-counter {
                right: -2px;
                bottom: calc(100% + 8px);
                font-size: 0.62rem;
                padding: 6px 8px;
            }

            .baby-play .baby-play__avatar {
                width: 62px;
                height: 62px;
            }

            .baby-play .baby-play__emoji {
                font-size: 1.92rem;
            }

            .baby-play .baby-play__avatar-img {
                width: 40px;
                height: 40px;
            }

            .baby-play .baby-play__hearts {
                left: 30px;
                bottom: 34px;
            }

            .memory-slider {
                margin-top: 10px;
            }

            .memory-slider-stage {
                width: 132px;
                height: 132px;
            }

            .memory-slider-caption {
                font-size: 0.67rem;
            }

            .countdown-block {
                margin-top: 10px;
                padding: 9px 10px;
            }

            .countdown-title {
                font-size: 0.7rem;
                margin-bottom: 6px;
            }

            .countdown-grid {
                gap: 5px;
            }

            .countdown-value {
                font-size: 0.9rem;
            }

            .countdown-label {
                font-size: 0.58rem;
            }
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
<div id="scrollPetalsLayer" class="scroll-petals" aria-hidden="true"></div>

<div id="miniBalloons" class="mini-balloons" aria-label="બલૂન મિની ગેમ">
    <div class="mini-balloons__items" aria-hidden="false">
        <button type="button" class="mini-balloon" aria-label="બલૂન પોપ 1">🎈</button>
        <button type="button" class="mini-balloon" aria-label="બલૂન પોપ 2">🎈</button>
        <button type="button" class="mini-balloon" aria-label="બલૂન પોપ 3">🎈</button>
    </div>
    <div id="miniBalloonsMsg" class="mini-balloons__msg" role="status" aria-live="polite">નાનકડા મહેમાન માટે શુભેચ્છા 🎉</div>
</div>

<div id="openingStage" class="opening-stage" aria-label="આમંત્રણ કવર">
    <div class="opening-head">
        <div class="opening-overline">સ્નેહભર્યું આમંત્રણ</div>
        <div class="opening-title"><?php echo e($title !== '' ? $title : 'સીમંત વિધિ'); ?></div>
        <div class="opening-ganesh-wrap">
            <img src="<?php echo e(assetUrl($ganeshImage)); ?>" alt="ગણેશજી">
        </div>
        <div class="opening-ganesh-text">શ્રી ગણેશાય નમઃ</div>
    </div>

    <div id="openingShell" class="opening-shell">
        <div class="opening-loader" aria-hidden="true">
            <div class="opening-loader-inner">
                <span class="opening-loader-ring"></span>
                <div class="opening-loader-text">
                    આમંત્રણ ખુલી રહ્યું છે
                    <span class="opening-loader-dots"><span></span><span></span><span></span></span>
                </div>
            </div>
        </div>
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
        <span class="opening-swastik" aria-hidden="true">卐</span>

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
        <img src="<?php echo e(assetUrl($cornerImages['bottom_right'])); ?>" alt="" class="orn orn-tr">

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

            <div id="photoMemorySlider" class="memory-slider" data-interval-ms="5000" aria-label="Photo memories">
                <div class="memory-slider-title">Photo Memories</div>
                <div class="memory-slider-stage" aria-live="polite">
                    <?php foreach ($memorySlides as $index => $slide): ?>
                        <figure class="memory-slide <?php echo $index === 0 ? 'is-active' : ''; ?>" data-label="<?php echo e($slide['label']); ?>" aria-hidden="<?php echo $index === 0 ? 'false' : 'true'; ?>">
                            <img src="<?php echo e(assetUrl((string)$slide['path'])); ?>" alt="<?php echo e((string)$slide['label']); ?>">
                        </figure>
                    <?php endforeach; ?>
                </div>
                <p id="photoMemoryCaption" class="memory-slider-caption"><?php echo e((string)($memorySlides[0]['label'] ?? 'Photo Memory')); ?></p>
                <div class="memory-slider-dots" aria-hidden="true">
                    <?php foreach ($memorySlides as $index => $slide): ?>
                        <span class="memory-slider-dot <?php echo $index === 0 ? 'is-active' : ''; ?>"></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($countdownTargetMs !== null): ?>
                <div id="eventCountdown" class="countdown-block" data-target-ms="<?php echo e((string)$countdownTargetMs); ?>">
                    <div class="countdown-title">આનંદમય દિવસ માટે બાકી સમય</div>
                    <div class="countdown-grid" role="timer" aria-live="polite">
                        <div class="countdown-item">
                            <span id="countdownDays" class="countdown-value">0</span>
                            <span class="countdown-label">Days</span>
                        </div>
                        <div class="countdown-item">
                            <span id="countdownHours" class="countdown-value">0</span>
                            <span class="countdown-label">Hours</span>
                        </div>
                        <div class="countdown-item">
                            <span id="countdownMinutes" class="countdown-value">0</span>
                            <span class="countdown-label">Minutes</span>
                        </div>
                    </div>
                </div>
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

<!-- BABY PLAY WIDGET START -->
<div id="babyPlayWidget" class="baby-play" data-baby-image="">
    <div class="baby-play__controls">
        <p id="babyPlayTooltip" class="baby-play__tooltip">બેબી પર ટેપ કરો અને આશીર્વાદ મોકલો</p>
        <p id="babyPlayBlessingToast" class="baby-play__blessing-toast" role="status" aria-live="polite">આશીર્વાદ મોકલાયો 💖</p>
        <p id="babyPlayBlessingCounter" class="baby-play__blessing-counter" aria-live="polite">આશીર્વાદ: 0</p>
        <button type="button" id="babyPlayAvatar" class="baby-play__avatar" aria-label="બેબીને હસાવો" title="બેબી પર ટેપ કરો, હવર કરો અથવા ખેંચીને ખસેડો">
            <span class="baby-play__emoji" aria-hidden="true">👶</span>
            <img class="baby-play__avatar-img" src="" alt="બેબી">
            <span class="baby-play__cheek baby-play__cheek--left" aria-hidden="true"></span>
            <span class="baby-play__cheek baby-play__cheek--right" aria-hidden="true"></span>
        </button>
        <div id="babyPlayHearts" class="baby-play__hearts" aria-hidden="true"></div>
        <audio id="babyPlayAudio" class="baby-play__audio" src="assets/freesound_community-baby-giggle-85158.mp3" preload="none"></audio>
    </div>
</div>
<!-- BABY PLAY WIDGET END -->

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="assets/js/main.js?v=<?php echo rawurlencode((string)(@filemtime(__DIR__ . '/assets/js/main.js') ?: time())); ?>"></script>
<script>
window.addEventListener('load', function () {
    document.body.classList.add('page-loaded');
});
</script>
</body>
</html>
