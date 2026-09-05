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
$projectId  = (string)($_POST['project_id'] ?? '');
$photoIndex = filter_var($_POST['photo_index'] ?? '', FILTER_VALIDATE_INT);

if ($locationId === '' || $projectId === '' || $photoIndex === false || $photoIndex < 0) {
    ce_flash_set('Invalid photo.', 'error');
    header('Location: index.php#gallery-admin');
    exit;
}

if (ce_delete_photo($locationId, $projectId, $photoIndex)) {
    ce_flash_set('Photo removed from the website.');
} else {
    ce_flash_set('Could not find that photo — it may already be removed.', 'error');
}

header('Location: index.php#gallery-admin');
exit;