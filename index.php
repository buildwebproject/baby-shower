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

$storageDir  = __DIR__ . '/storage';
$rsvpFile    = $storageDir . '/rsvp.json';
$qrFile      = $storageDir . '/qr.png';
$qrPublicPath = 'storage/qr.png';

if (!is_dir($storageDir)) mkdir($storageDir, 0775, true);
if (!is_file($rsvpFile)) writeJsonFile($rsvpFile, []);

$currentPageUrl = currentPageUrl();

$ganeshLine  = (string)($invitation['ganesh_line']  ?? 'શ્રી ગણેશાય નમઃ');
$ganeshImage = 'assets/images/ganesha.png';

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
$whatsAppMessage = (string)($invitation['whatsapp_message'] ?? '');

$ogTitle      = $title;
$ogDescription = 'ભાવભર્યું આમંત્રણ';
$scriptBase   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$ogImageUrl   = baseUrl() . $scriptBase . '/share-image.php';

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
    <meta property="og:type"              content="website">
    <meta property="og:title"             content="<?php echo e($ogTitle); ?>">
    <meta property="og:description"       content="<?php echo e($ogDescription); ?>">
    <meta property="og:image"             content="<?php echo e($ogImageUrl); ?>">
    <meta property="og:image:secure_url"  content="<?php echo e($ogImageUrl); ?>">
    <meta property="og:image:type"        content="image/png">
    <meta property="og:image:width"       content="1200">
    <meta property="og:image:height"      content="630">
    <meta property="og:url"               content="<?php echo e($currentPageUrl); ?>">
    <meta name="twitter:card"             content="summary_large_image">
    <meta name="twitter:title"            content="<?php echo e($ogTitle); ?>">
    <meta name="twitter:description"      content="<?php echo e($ogDescription); ?>">
    <meta name="twitter:image"            content="<?php echo e($ogImageUrl); ?>">

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
        }
        .orn-tl { top: 0;  left: 0;  width: 110px; animation: floatA 7s ease-in-out infinite; }
        .orn-tr { top: 0;  right: 0; width: 100px; animation: floatB 8s ease-in-out infinite; transform-origin: top right; }
        .orn-bl { bottom: 0; left: 0;  width: 120px; animation: floatB 9s ease-in-out infinite; }
        .orn-br { bottom: 0; right: 0; width:  90px; animation: floatA 7.5s ease-in-out infinite; }

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
            padding: 20px 32px 20px;
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
            margin-bottom: 4px;
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
            .orn-tl, .orn-tr { width: 80px; }
            .orn-bl, .orn-br { width: 88px; }
            .balloon { width: 24px; height: 34px; }
            .card-content { padding: 14px 16px 16px; }
            .btn-row { grid-template-columns: 1fr; }
            .inv-card { padding-bottom: 80px; }
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
        <img src="assets/images/baby-clothes.svg"         alt="" class="orn orn-tl">
        <img src="assets/images/baby-mobile.svg"           alt="" class="orn orn-tr">
        <img src="assets/images/baby-stroller-blocks.svg"  alt="" class="orn orn-bl">
        <img src="assets/images/alphabet-blocks.svg"       alt="" class="orn orn-br">

        <div class="card-content">

            <!-- Ganesh -->
            <div class="ganesh-wrap">
                <img src="<?php echo e($ganeshImage); ?>" alt="ગણેશજી">
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
<script src="assets/js/main.js"></script>
<script>
window.addEventListener('load', function () {
    document.body.classList.add('page-loaded');
});
</script>
</body>
</html>
