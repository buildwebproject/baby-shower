<?php

declare(strict_types=1);

function invitation_default_data(): array
{
    static $defaults = null;
    if (is_array($defaults)) {
        return $defaults;
    }

    $data = require __DIR__ . '/../data.php';
    $defaults = is_array($data) ? $data : [];
    return $defaults;
}

function invitation_db_config(): array
{
    invitation_boot_env();

    return [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'babe_shower',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: 'WebifyDev2026Aa_',
        'charset' => 'utf8mb4',
    ];
}

function invitation_boot_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $envPath = __DIR__ . '/../.env';
    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '') {
            continue;
        }

        $isAppKey = str_starts_with($key, 'DB_') || str_starts_with($key, 'ADMIN_');
        if (!$isAppKey && getenv($key) !== false) {
            continue;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, '\'') && str_ends_with($value, '\''))) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function invitation_pdo(): ?PDO
{
    static $pdo = null;
    static $booted = false;

    if ($booted) {
        return $pdo;
    }

    $booted = true;
    $cfg = invitation_db_config();
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']);

    try {
        $pdo = new PDO($dsn, (string)$cfg['user'], (string)$cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        invitation_initialize_schema($pdo, invitation_default_data());
    } catch (Throwable $e) {
        $pdo = null;
    }

    return $pdo;
}

function invitation_initialize_schema(PDO $pdo, array $defaults): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS invitation_content (\n'
        . '  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,\n'
        . '  content_json LONGTEXT NOT NULL,\n'
        . '  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin_users (\n'
        . '  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n'
        . '  username VARCHAR(60) NOT NULL UNIQUE,\n'
        . '  password_hash VARCHAR(255) NOT NULL,\n'
        . '  is_active TINYINT(1) NOT NULL DEFAULT 1,\n'
        . '  last_login_at DATETIME NULL,\n'
        . '  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n'
        . '  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // Backward compatibility with older schema.
    $pdo->exec('ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1');
    $pdo->exec('ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL');
    $pdo->exec('ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin_login_attempts (\n'
        . '  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n'
        . '  username VARCHAR(60) NOT NULL,\n'
        . '  ip_address VARCHAR(45) NOT NULL,\n'
        . '  attempted_at DATETIME NOT NULL,\n'
        . '  INDEX idx_admin_attempts_lookup (username, ip_address, attempted_at)\n'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM invitation_content WHERE id = 1');
    $checkStmt->execute();
    if ((int)$checkStmt->fetchColumn() === 0) {
        $insertStmt = $pdo->prepare('INSERT INTO invitation_content (id, content_json) VALUES (1, :json)');
        $insertStmt->execute([
            ':json' => json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    $adminCheck = $pdo->query('SELECT COUNT(*) FROM admin_users');
    if ((int)$adminCheck->fetchColumn() === 0) {
        $defaultUsername = getenv('ADMIN_DEFAULT_USER') ?: 'Dev';
        $defaultPassword = getenv('ADMIN_DEFAULT_PASSWORD') ?: 'Dev@1882000';
        $adminInsert = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :password_hash)');
        $adminInsert->execute([
            ':username' => $defaultUsername,
            ':password_hash' => password_hash($defaultPassword, PASSWORD_DEFAULT),
        ]);
    }
}

function invitation_load_data(): array
{
    $defaults = invitation_default_data();
    $pdo = invitation_pdo();

    if (!$pdo) {
        return $defaults;
    }

    try {
        $stmt = $pdo->prepare('SELECT content_json FROM invitation_content WHERE id = 1 LIMIT 1');
        $stmt->execute();
        $row = $stmt->fetch();
        if (!is_array($row) || !isset($row['content_json'])) {
            return $defaults;
        }

        $decoded = json_decode((string)$row['content_json'], true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        return array_replace($defaults, $decoded);
    } catch (Throwable $e) {
        return $defaults;
    }
}

function invitation_save_data(array $newData): bool
{
    $pdo = invitation_pdo();
    if (!$pdo) {
        return false;
    }

    $encoded = json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($encoded === false) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO invitation_content (id, content_json) VALUES (1, :json)\n'
            . 'ON DUPLICATE KEY UPDATE content_json = VALUES(content_json), updated_at = CURRENT_TIMESTAMP'
        );
        return $stmt->execute([':json' => $encoded]);
    } catch (Throwable $e) {
        return false;
    }
}

function invitation_admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function invitation_admin_is_logged_in(): bool
{
    return !empty($_SESSION['is_admin']) && !empty($_SESSION['admin_user_id']);
}

function invitation_admin_require_login(): void
{
    if (!invitation_admin_is_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function invitation_admin_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function invitation_admin_validate_csrf(string $token): bool
{
    $known = (string)($_SESSION['csrf_token'] ?? '');
    return $known !== '' && hash_equals($known, $token);
}

function invitation_admin_client_ip(): string
{
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function invitation_admin_find_user_by_username(string $username): ?array
{
    $pdo = invitation_pdo();
    if (!$pdo) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, username, password_hash, is_active FROM admin_users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function invitation_admin_find_user_by_id(int $id): ?array
{
    $pdo = invitation_pdo();
    if (!$pdo) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, username, password_hash, is_active FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function invitation_admin_login_is_blocked(string $username, string $ip): bool
{
    $pdo = invitation_pdo();
    if (!$pdo) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM admin_login_attempts\n'
        . 'WHERE attempted_at >= (NOW() - INTERVAL 15 MINUTE)\n'
        . 'AND (username = :username OR ip_address = :ip)'
    );
    $stmt->execute([
        ':username' => $username,
        ':ip' => $ip,
    ]);

    return ((int)$stmt->fetchColumn()) >= 7;
}

function invitation_admin_record_failed_login(string $username, string $ip): void
{
    $pdo = invitation_pdo();
    if (!$pdo) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO admin_login_attempts (username, ip_address, attempted_at) VALUES (:username, :ip, NOW())');
    $stmt->execute([
        ':username' => $username,
        ':ip' => $ip,
    ]);
}

function invitation_admin_clear_login_attempts(string $username, string $ip): void
{
    $pdo = invitation_pdo();
    if (!$pdo) {
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM admin_login_attempts WHERE username = :username OR ip_address = :ip');
    $stmt->execute([
        ':username' => $username,
        ':ip' => $ip,
    ]);
}

function invitation_admin_login(string $username, string $password): array
{
    $username = trim($username);
    $ip = invitation_admin_client_ip();

    if ($username === '' || $password === '') {
        return ['ok' => false, 'message' => 'Please enter username and password.'];
    }

    if (invitation_admin_login_is_blocked($username, $ip)) {
        return ['ok' => false, 'message' => 'Too many login attempts. Please wait 15 minutes and try again.'];
    }

    $user = invitation_admin_find_user_by_username($username);
    if (!$user || empty($user['is_active']) || !password_verify($password, (string)$user['password_hash'])) {
        invitation_admin_record_failed_login($username, $ip);
        return ['ok' => false, 'message' => 'Invalid login credentials.'];
    }

    if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
        invitation_admin_update_password((int)$user['id'], $password);
    }

    $pdo = invitation_pdo();
    if ($pdo) {
        $stmt = $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => (int)$user['id']]);
    }

    invitation_admin_clear_login_attempts($username, $ip);

    session_regenerate_id(true);
    $_SESSION['is_admin'] = true;
    $_SESSION['admin_user_id'] = (int)$user['id'];
    $_SESSION['admin_user'] = (string)$user['username'];

    return ['ok' => true, 'message' => 'Login success.'];
}

function invitation_admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function invitation_admin_update_password(int $userId, string $newPassword): bool
{
    if ($newPassword === '' || strlen($newPassword) < 8) {
        return false;
    }

    $pdo = invitation_pdo();
    if (!$pdo) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE admin_users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    return $stmt->execute([
        ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ':id' => $userId,
    ]);
}

function invitation_admin_change_password(int $userId, string $currentPassword, string $newPassword): array
{
    $user = invitation_admin_find_user_by_id($userId);
    if (!$user) {
        return ['ok' => false, 'message' => 'User not found.'];
    }

    if (!password_verify($currentPassword, (string)$user['password_hash'])) {
        return ['ok' => false, 'message' => 'Current password is incorrect.'];
    }

    if (strlen($newPassword) < 8) {
        return ['ok' => false, 'message' => 'New password must be at least 8 characters.'];
    }

    if (!invitation_admin_update_password($userId, $newPassword)) {
        return ['ok' => false, 'message' => 'Failed to change password.'];
    }

    return ['ok' => true, 'message' => 'Password updated successfully.'];
}

function invitation_admin_create_user(string $username, string $password): array
{
    $username = trim($username);
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $username)) {
        return ['ok' => false, 'message' => 'Username must be 3-60 chars and contain only letters, numbers, _, -, .'];
    }

    if (strlen($password) < 8) {
        return ['ok' => false, 'message' => 'Password must be at least 8 characters.'];
    }

    $pdo = invitation_pdo();
    if (!$pdo) {
        return ['ok' => false, 'message' => 'Database unavailable.'];
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, is_active) VALUES (:username, :password_hash, 1)');
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        return ['ok' => true, 'message' => 'Admin user created.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Username already exists or user creation failed.'];
    }
}

function invitation_admin_set_user_active(int $targetUserId, bool $active, int $actingUserId): array
{
    if ($targetUserId === $actingUserId && !$active) {
        return ['ok' => false, 'message' => 'You cannot deactivate your own account.'];
    }

    $pdo = invitation_pdo();
    if (!$pdo) {
        return ['ok' => false, 'message' => 'Database unavailable.'];
    }

    $stmt = $pdo->prepare('UPDATE admin_users SET is_active = :active, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $ok = $stmt->execute([
        ':active' => $active ? 1 : 0,
        ':id' => $targetUserId,
    ]);

    if (!$ok || $stmt->rowCount() === 0) {
        return ['ok' => false, 'message' => 'User update failed.'];
    }

    return ['ok' => true, 'message' => 'User status updated.'];
}

function invitation_admin_list_users(): array
{
    $pdo = invitation_pdo();
    if (!$pdo) {
        return [];
    }

    $stmt = $pdo->query('SELECT id, username, is_active, last_login_at, created_at, updated_at FROM admin_users ORDER BY id ASC');
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}
