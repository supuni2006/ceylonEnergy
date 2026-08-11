<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/attachments.php';

ce_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#admin-flash');
    exit;
}

if (ce_post_too_large()) {
    ce_flash_set(ce_post_too_large_message(), 'error');
    header('Location: index.php#admin-flash');
    exit;
}

if (!ce_csrf_check($_POST['csrf'] ?? '')) {
    ce_flash_set('Your session expired — please try again.', 'error');
    header('Location: index.php#admin-flash');
    exit;
}

function fail_add($msg) {
    ce_flash_set($msg, 'error');
    header('Location: index.php#admin-flash');
    exit;
}

// ---- validate the PDF ---------------------------------------------------
$pdfCheck = ce_validate_pdf_file($_FILES['pdf'] ?? null);
if ($pdfCheck !== true) {
    fail_add($pdfCheck);
}

// ---- validate the page images -------------------------------------------
$imagesCheck = ce_validate_page_image_files($_FILES['page_images'] ?? null);
if (!is_array($imagesCheck)) {
    fail_add($imagesCheck);
}

// ---- title (optional — falls back to the filename) ----------------------
$title = trim((string)($_POST['title'] ?? ''));
$title = preg_replace('/\s+/', ' ', strip_tags($title));
if (mb_strlen($title) > 120) {
    $title = mb_substr($title, 0, 120);
}

// ---- add it — existing attachments and the main profile are never touched
$id = ce_add_attachment($title, $_FILES['pdf']['tmp_name'], $_FILES['pdf']['name'], $_FILES['pdf']['size'], $imagesCheck);

if (!$id) {
    fail_add('Could not save the uploaded files. Please check server file permissions and try again.');
}

ce_flash_set('New document added — it now shows on the live website.');
header('Location: index.php#admin-flash');
exit;