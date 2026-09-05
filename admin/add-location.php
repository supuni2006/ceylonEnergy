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

$id = ce_add_location($_POST['name'] ?? '');

if (!$id) {
    ce_flash_set('Could not add that location. Check the name isn\'t blank and doesn\'t already exist.', 'error');
} else {
    ce_flash_set('Location added — it now shows on the live site, and you can add a project under it below.');
}

header('Location: index.php#gallery-admin');
exit;