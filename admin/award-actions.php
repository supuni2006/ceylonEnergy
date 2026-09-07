<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/awards.php';

ce_require_login();

function ce_awards_back() {
    header('Location: awards.php#admin-flash');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ce_awards_back();
}

if (ce_post_too_large()) {
    ce_flash_set(ce_post_too_large_message(), 'error');
    ce_awards_back();
}

if (!ce_csrf_check($_POST['csrf'] ?? '')) {
    ce_flash_set('Your session expired — please try again.', 'error');
    ce_awards_back();
}

$action = (string)($_POST['action'] ?? '');

if ($action === 'add_award') {

    $title = trim(strip_tags((string)($_POST['title'] ?? '')));
    $title = preg_replace('/\s+/', ' ', $title);
    if (mb_strlen($title) > 120) $title = mb_substr($title, 0, 120);

    $year = trim(strip_tags((string)($_POST['year'] ?? '')));
    if ($year !== '' && !preg_match('/^\d{4}$/', $year)) {
        ce_flash_set('Year must be a 4-digit number, e.g. 2025.', 'error');
        ce_awards_back();
    }

    $ext = ce_validate_award_image($_FILES['image'] ?? null);
    if (!is_string($ext) || !in_array($ext, ['jpg', 'png', 'webp'], true)) {
        // ce_validate_award_image() returns an error message string in this case
        ce_flash_set(is_string($ext) ? $ext : 'Could not validate the image.', 'error');
        ce_awards_back();
    }

    $id = ce_add_award($title, $year, $_FILES['image'], $ext);
    if (!$id) {
        ce_flash_set('Could not save the award image. Please check server file permissions and try again.', 'error');
        ce_awards_back();
    }

    ce_flash_set('Award added — it now shows on the live website.');

} elseif ($action === 'delete_award') {

    $id = (string)($_POST['id'] ?? '');
    if (ce_delete_award($id)) {
        ce_flash_set('Award removed.');
    } else {
        ce_flash_set('Could not find that award — it may already be removed.', 'error');
    }

} else {
    ce_flash_set('Unknown action.', 'error');
}

ce_awards_back();
