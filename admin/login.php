<?php

declare(strict_types=1);

require __DIR__ . '/../includes/invitation_store.php';
invitation_admin_session_start();

if (invitation_admin_is_logged_in()) {
    header('Location: /admin/index.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!invitation_admin_validate_csrf($csrf)) {
        $error = 'Invalid request token. Refresh and try again.';
    } else {
        $result = invitation_admin_login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
        if (!empty($result['ok'])) {
            header('Location: /admin/index.php');
            exit;
        }
        $error = (string)($result['message'] ?? 'Login failed.');
    }
}

$csrfToken = invitation_admin_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Login</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6fb; margin:0; min-height:100vh; display:grid; place-items:center; }
        .card { width:min(92vw,400px); background:#fff; border-radius:10px; padding:22px; box-shadow:0 12px 34px rgba(0,0,0,.12); }
        h1 { margin:0 0 16px; font-size:22px; }
        label { display:block; margin-bottom:6px; font-weight:600; font-size:14px; }
        input { width:100%; padding:10px; border:1px solid #ccd3e0; border-radius:8px; margin-bottom:14px; }
        button { width:100%; border:0; background:#1f5eff; color:#fff; padding:11px; border-radius:8px; font-weight:700; cursor:pointer; }
        .error { margin-bottom:12px; color:#b31330; font-size:14px; font-weight:700; }
    </style>
</head>
<body>
    <form method="post" class="card" autocomplete="off">
        <h1>Admin Login</h1>
        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <label for="username">Username</label>
        <input id="username" name="username" type="text" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <button type="submit">Sign in</button>
    </form>
</body>
</html>
