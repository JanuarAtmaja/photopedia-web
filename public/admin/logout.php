<?php
// public/admin/logout.php — Destroy admin session
require_once dirname(__DIR__, 2) . '/config/admin-auth.php';

admin_session_start();
admin_logout();

header('Location: /admin/login');
exit;
