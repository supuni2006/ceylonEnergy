<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/attachments.php';

ce_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#admin-flash');
    exit;
}

if (!ce_csrf_check($_POST['csrf'] ?? '')) {
    ce_flash_set('Your session expired — please try again.', 'error');
    header('Location: index.php#admin-flash');
    exit;
}

$id = (string)($_POST['id'] ?? '');

if ($id === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
    ce_flash_set('Invalid attachment.', 'error');
    header('Location: index.php#admin-flash');
    exit;
}

if (ce_delete_attachment($id)) {
    ce_flash_set('Attachment removed from the website.');
} else {
    ce_flash_set('Could not find that attachment — it may already be removed.', 'error');
}

header('Location: index.php#admin-flash');
exit;