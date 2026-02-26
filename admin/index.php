<?php

declare(strict_types=1);

require __DIR__ . '/../includes/invitation_store.php';
invitation_admin_session_start();
invitation_admin_require_login();

function splitLines(string $value): array
{
    $lines = preg_split('/\r\n|\r|\n/u', $value) ?: [];
    $result = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $result[] = $line;
        }
    }
    return $result;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$defaults = invitation_default_data();
$data = invitation_load_data();
$status = '';
$error = '';
$activeTab = 'content';

$adminUserId = (int)($_SESSION['admin_user_id'] ?? 0);
$adminUsername = (string)($_SESSION['admin_user'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!invitation_admin_validate_csrf($csrf)) {
        $error = 'Invalid request token. Please refresh and retry.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save_content') {
            $activeTab = 'content';
            $payload = $defaults;

            $stringFields = [
                'ganesh_line', 'ganesh_image', 'door_center_image', 'title', 'mother_name', 'father_name', 'family_name',
                'special_line', 'program_line', 'date_text', 'time_text', 'meal_date_text', 'meal_time_text', 'meal_note',
                'event_datetime', 'venue_name', 'full_address', 'city', 'google_maps_url', 'contact_phone', 'whatsapp_message',
            ];

            foreach ($stringFields as $field) {
                $payload[$field] = trim((string)($_POST[$field] ?? ''));
            }

            $payload['lead_lines'] = splitLines((string)($_POST['lead_lines'] ?? ''));
            $payload['venue_lines'] = splitLines((string)($_POST['venue_lines'] ?? ''));
            $payload['inviters'] = splitLines((string)($_POST['inviters'] ?? ''));

            $payload['rsvp_enabled'] = isset($_POST['rsvp_enabled']);
            $payload['qr_enabled'] = isset($_POST['qr_enabled']);

            if (invitation_save_data($payload)) {
                $data = invitation_load_data();
                $status = 'Invitation content saved.';
            } else {
                $error = 'Save failed. Verify MySQL and DB credentials.';
            }
        }

        if ($action === 'change_password') {
            $activeTab = 'auth';
            $result = invitation_admin_change_password(
                $adminUserId,
                (string)($_POST['current_password'] ?? ''),
                (string)($_POST['new_password'] ?? '')
            );
            if (!empty($result['ok'])) {
                $status = (string)$result['message'];
            } else {
                $error = (string)$result['message'];
            }
        }

        if ($action === 'create_user') {
            $activeTab = 'auth';
            $result = invitation_admin_create_user(
                (string)($_POST['new_username'] ?? ''),
                (string)($_POST['new_user_password'] ?? '')
            );
            if (!empty($result['ok'])) {
                $status = (string)$result['message'];
            } else {
                $error = (string)$result['message'];
            }
        }

        if ($action === 'toggle_user') {
            $activeTab = 'auth';
            $targetId = (int)($_POST['target_user_id'] ?? 0);
            $newActive = ((string)($_POST['new_active'] ?? '0')) === '1';
            $result = invitation_admin_set_user_active($targetId, $newActive, $adminUserId);
            if (!empty($result['ok'])) {
                $status = (string)$result['message'];
            } else {
                $error = (string)$result['message'];
            }
        }
    }
}

$leadLinesText = implode("\n", $data['lead_lines'] ?? []);
$venueLinesText = implode("\n", $data['venue_lines'] ?? []);
$invitersText = implode("\n", $data['inviters'] ?? []);
$users = invitation_admin_list_users();
$csrfToken = invitation_admin_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Invitation Admin</title>
    <style>
        :root { --bg:#f6f7fc; --card:#ffffff; --line:#d8deea; --text:#1a1f31; --muted:#667089; --accent:#2f63ff; --ok:#0a7a34; --err:#be1d3e; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Arial,sans-serif; background:var(--bg); color:var(--text); }
        .wrap { width:min(1100px,94vw); margin:24px auto 56px; }
        .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; gap:8px; flex-wrap:wrap; }
        .top a { color:var(--accent); text-decoration:none; font-weight:700; }
        .pill { font-size:12px; padding:4px 8px; border:1px solid var(--line); border-radius:999px; background:#fff; }
        .panel { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:18px; box-shadow:0 8px 24px rgba(15,29,62,.08); margin-bottom:16px; }
        .grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px; }
        .full { grid-column:1 / -1; }
        label { display:block; font-size:13px; color:var(--muted); margin-bottom:6px; font-weight:700; }
        input, textarea { width:100%; border:1px solid var(--line); border-radius:8px; padding:10px; font:inherit; }
        textarea { min-height:94px; resize:vertical; }
        .checks { display:flex; gap:18px; flex-wrap:wrap; }
        .checks label { display:flex; align-items:center; gap:8px; margin:0; color:var(--text); }
        .msg { margin:8px 0 14px; font-weight:700; }
        .ok { color:var(--ok); }
        .err { color:var(--err); }
        .btn { margin-top:14px; border:0; background:var(--accent); color:#fff; padding:12px 18px; border-radius:8px; font-weight:700; cursor:pointer; }
        .btn.secondary { background:#5f6b84; }
        .split { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; border-bottom:1px solid var(--line); padding:8px 6px; font-size:13px; vertical-align:top; }
        .tab-title { margin:0 0 10px; }
        .inline { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        @media (max-width: 760px) { .grid, .split { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div class="inline">
            <h1 style="margin:0;">Invitation Admin</h1>
            <span class="pill">Signed in as <?php echo h($adminUsername); ?></span>
        </div>
        <div>
            <a href="/index.php" target="_blank" rel="noopener">View Site</a> |
            <a href="/admin/logout.php">Logout</a>
        </div>
    </div>

    <?php if ($status !== ''): ?><div class="msg ok"><?php echo h($status); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="msg err"><?php echo h($error); ?></div><?php endif; ?>

    <div class="panel">
        <h2 class="tab-title">Invitation Content</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
            <input type="hidden" name="action" value="save_content">

            <div class="grid">
                <div><label>Title</label><input name="title" value="<?php echo h((string)($data['title'] ?? '')); ?>"></div>
                <div><label>Ganesh Line</label><input name="ganesh_line" value="<?php echo h((string)($data['ganesh_line'] ?? '')); ?>"></div>

                <div><label>Mother Name</label><input name="mother_name" value="<?php echo h((string)($data['mother_name'] ?? '')); ?>"></div>
                <div><label>Father Name</label><input name="father_name" value="<?php echo h((string)($data['father_name'] ?? '')); ?>"></div>
                <div class="full"><label>Family Name</label><input name="family_name" value="<?php echo h((string)($data['family_name'] ?? '')); ?>"></div>

                <div><label>Date Text</label><input name="date_text" value="<?php echo h((string)($data['date_text'] ?? '')); ?>"></div>
                <div><label>Time Text</label><input name="time_text" value="<?php echo h((string)($data['time_text'] ?? '')); ?>"></div>
                <div><label>Meal Date Text</label><input name="meal_date_text" value="<?php echo h((string)($data['meal_date_text'] ?? '')); ?>"></div>
                <div><label>Meal Time Text</label><input name="meal_time_text" value="<?php echo h((string)($data['meal_time_text'] ?? '')); ?>"></div>
                <div><label>Event Datetime (YYYY-MM-DD HH:MM:SS)</label><input name="event_datetime" value="<?php echo h((string)($data['event_datetime'] ?? '')); ?>"></div>
                <div><label>Contact Phone</label><input name="contact_phone" value="<?php echo h((string)($data['contact_phone'] ?? '')); ?>"></div>

                <div><label>Venue Name</label><input name="venue_name" value="<?php echo h((string)($data['venue_name'] ?? '')); ?>"></div>
                <div><label>City</label><input name="city" value="<?php echo h((string)($data['city'] ?? '')); ?>"></div>
                <div class="full"><label>Full Address</label><input name="full_address" value="<?php echo h((string)($data['full_address'] ?? '')); ?>"></div>
                <div class="full"><label>Google Maps URL</label><input name="google_maps_url" value="<?php echo h((string)($data['google_maps_url'] ?? '')); ?>"></div>

                <div class="full"><label>Special Line</label><textarea name="special_line"><?php echo h((string)($data['special_line'] ?? '')); ?></textarea></div>
                <div class="full"><label>Program Line</label><textarea name="program_line"><?php echo h((string)($data['program_line'] ?? '')); ?></textarea></div>
                <div class="full"><label>Meal Note</label><textarea name="meal_note"><?php echo h((string)($data['meal_note'] ?? '')); ?></textarea></div>
                <div class="full"><label>WhatsApp Message</label><textarea name="whatsapp_message"><?php echo h((string)($data['whatsapp_message'] ?? '')); ?></textarea></div>

                <div class="full"><label>Lead Lines (one per line)</label><textarea name="lead_lines"><?php echo h($leadLinesText); ?></textarea></div>
                <div class="full"><label>Venue Lines (one per line)</label><textarea name="venue_lines"><?php echo h($venueLinesText); ?></textarea></div>
                <div class="full"><label>Inviters (one per line)</label><textarea name="inviters"><?php echo h($invitersText); ?></textarea></div>

                <div><label>Ganesh Image Path</label><input name="ganesh_image" value="<?php echo h((string)($data['ganesh_image'] ?? '')); ?>"></div>
                <div><label>Door Center Image Path</label><input name="door_center_image" value="<?php echo h((string)($data['door_center_image'] ?? '')); ?>"></div>

                <div class="full checks">
                    <label><input type="checkbox" name="rsvp_enabled" <?php echo !empty($data['rsvp_enabled']) ? 'checked' : ''; ?>> RSVP Enabled</label>
                    <label><input type="checkbox" name="qr_enabled" <?php echo !empty($data['qr_enabled']) ? 'checked' : ''; ?>> QR Enabled</label>
                </div>
            </div>

            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>

    <div class="split">
        <div class="panel">
            <h2 class="tab-title">Change Password</h2>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="action" value="change_password">

                <label>Current Password</label>
                <input type="password" name="current_password" required>

                <label>New Password</label>
                <input type="password" name="new_password" required minlength="8">

                <button type="submit" class="btn secondary">Update Password</button>
            </form>
        </div>

        <div class="panel">
            <h2 class="tab-title">Create Admin User</h2>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="action" value="create_user">

                <label>Username</label>
                <input type="text" name="new_username" required>

                <label>Password</label>
                <input type="password" name="new_user_password" required minlength="8">

                <button type="submit" class="btn secondary">Create User</button>
            </form>
        </div>
    </div>

    <div class="panel">
        <h2 class="tab-title">Admin Users</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php
                        $uid = (int)($user['id'] ?? 0);
                        $isActive = !empty($user['is_active']);
                    ?>
                    <tr>
                        <td><?php echo $uid; ?></td>
                        <td><?php echo h((string)($user['username'] ?? '')); ?></td>
                        <td><?php echo $isActive ? 'Active' : 'Disabled'; ?></td>
                        <td><?php echo h((string)($user['last_login_at'] ?? '-')); ?></td>
                        <td><?php echo h((string)($user['created_at'] ?? '-')); ?></td>
                        <td>
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                <input type="hidden" name="action" value="toggle_user">
                                <input type="hidden" name="target_user_id" value="<?php echo $uid; ?>">
                                <input type="hidden" name="new_active" value="<?php echo $isActive ? '0' : '1'; ?>">
                                <button type="submit" class="btn secondary" style="margin:0;padding:7px 10px;">
                                    <?php echo $isActive ? 'Disable' : 'Enable'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
