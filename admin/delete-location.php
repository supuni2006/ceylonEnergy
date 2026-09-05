<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/gallery.php';

ce_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#admin-flash');
    exit;
}

if (!ce_csrf_check($_POST['csrf'] ?? '')) {
    ce_flash_set('Your session expired — please try again.', 'error');
    header('Location: index.php#gallery-admin');
    exit;
}

$locationId = (string)($_POST['location_id'] ?? '');

if ($locationId === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $locationId)) {
    ce_flash_set('Invalid location.', 'error');
    header('Location: index.php#gallery-admin');
    exit;
}

if (ce_delete_location($locationId)) {
    ce_flash_set('Location (and everything under it) removed from the website.');
} else {
    ce_flash_set('Could not find that location — it may already be removed.', 'error');
}

header('Location: index.php#gallery-admin');
exit;