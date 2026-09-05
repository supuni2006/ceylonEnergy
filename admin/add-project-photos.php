<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/gallery.php';

ce_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#admin-flash');
    exit;
}

if (ce_post_too_large()) {
    ce_flash_set(ce_post_too_large_message(), 'error');
    header('Location: index.php#gallery-admin');
    exit;
}

if (!ce_csrf_check($_POST['csrf'] ?? '')) {
    ce_flash_set('Your session expired — please try again.', 'error');
    header('Location: index.php#gallery-admin');
    exit;
}

$locationId = (string)($_POST['location_id'] ?? '');
$projectId  = (string)($_POST['project_id'] ?? '');

if ($locationId === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $locationId) ||
    $projectId === ''  || !preg_match('/^[a-zA-Z0-9_-]+$/', $projectId)) {
    ce_flash_set('Please choose a location and a project.', 'error');
    header('Location: index.php#gallery-admin');
    exit;
}

$photosCheck = ce_validate_gallery_photo_files($_FILES['photos'] ?? null);
if (!is_array($photosCheck)) {
    ce_flash_set($photosCheck, 'error');
    header('Location: index.php#gallery-admin');
    exit;
}

$added = ce_add_project_photos($locationId, $projectId, $photosCheck);

if (!$added) {
    ce_flash_set('Could not save the uploaded photos. Please check server file permissions and try again.', 'error');
} else {
    ce_flash_set($added . ' photo' . ($added === 1 ? '' : 's') . ' added — they now show on the live site.');
}

header('Location: index.php#gallery-admin');
exit;