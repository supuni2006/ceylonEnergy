<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/profile.php';
require_once __DIR__ . '/inc/attachments.php'; // shared validators: ce_validate_pdf_file / ce_validate_page_image_files

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

function fail($msg) {
    ce_flash_set($msg, 'error');
    header('Location: index.php#admin-flash');
    exit;
}

// ---- validate the PDF ---------------------------------------------------
$pdfCheck = ce_validate_pdf_file($_FILES['pdf'] ?? null);
if ($pdfCheck !== true) {
    fail($pdfCheck);
}

// ---- validate the page images -------------------------------------------
$imagesCheck = ce_validate_page_image_files($_FILES['page_images'] ?? null);
if (!is_array($imagesCheck)) {
    fail($imagesCheck);
}

// ---- publish: backs up the previous version automatically ---------------
$ok = ce_publish_profile($_FILES['pdf']['tmp_name'], $imagesCheck);

if (!$ok) {
    fail('Could not save the uploaded files. Please check server file permissions and try again.');
}

ce_flash_set('Company profile updated — it now shows on the live website.');
header('Location: index.php#admin-flash');
exit;