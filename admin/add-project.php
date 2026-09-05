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
    ce_flash_set('Please choose a location.', 'error');
    header('Location: index.php#gallery-admin');
    exit;
}

$id = ce_add_project($locationId, $_POST['name'] ?? '');

if (!$id) {
    ce_flash_set('Could not add that project. Check the name isn\'t blank and the location still exists.', 'error');
} else {
    ce_flash_set('Project added — you can now upload photos to it below.');
}

header('Location: index.php#gallery-admin');
exit;