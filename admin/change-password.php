<?php
require_once __DIR__ . '/inc/auth.php';

ce_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!ce_csrf_check($_POST['csrf'] ?? '')) {
    ce_flash_set('Your session expired — please try again.', 'error');
    header('Location: index.php');
    exit;
}

$current = (string)($_POST['current_password'] ?? '');
$new1 = (string)($_POST['new_password'] ?? '');
$new2 = (string)($_POST['new_password2'] ?? '');

$hash = ce_get_password_hash();

if (!$hash || !password_verify($current, $hash)) {
    ce_flash_set('Your current password was incorrect.', 'error');
} elseif (strlen($new1) < 4) {
    ce_flash_set('New password must be at least 4 characters.', 'error');
} elseif ($new1 !== $new2) {
    ce_flash_set('New passwords do not match.', 'error');
} else {
    ce_save_password_hash(password_hash($new1, PASSWORD_DEFAULT));
    ce_flash_set('Password updated.');
}

header('Location: index.php');
exit;