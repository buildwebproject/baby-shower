<?php

declare(strict_types=1);

require __DIR__ . '/../includes/invitation_store.php';
invitation_admin_session_start();
invitation_admin_logout();
header('Location: /admin/login.php');
exit;
