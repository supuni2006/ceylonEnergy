<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/projects.php';

ce_require_login();

function ce_projects_back() {
    header('Location: projects.php#admin-flash');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ce_projects_back();
}

if (ce_post_too_large()) {
    ce_flash_set(ce_post_too_large_message(), 'error');
    ce_projects_back();
}

if (!ce_csrf_check($_POST['csrf'] ?? '')) {
    ce_flash_set('Your session expired — please try again.', 'error');
    ce_projects_back();
}

$action = (string)($_POST['action'] ?? '');

switch ($action) {

    case 'add_location':
        $name = trim(strip_tags((string)($_POST['name'] ?? '')));
        if ($name === '') {
            ce_flash_set('Please enter a location name.', 'error');
            break;
        }
        $result = ce_add_location($name);
        if ($result !== true) {
            ce_flash_set($result, 'error');
            break;
        }
        ce_flash_set('Location added.');
        break;

    case 'add_project':
        $locationId = (string)($_POST['location_id'] ?? '');
        $name = trim(strip_tags((string)($_POST['name'] ?? '')));
        if ($name === '') {
            ce_flash_set('Please enter a project name.', 'error');
            break;
        }
        $result = ce_add_project($locationId, $name);
        if ($result !== true) {
            ce_flash_set($result, 'error');
            break;
        }
        ce_flash_set('Project added.');
        break;

    case 'add_photo':
        $locationId = (string)($_POST['location_id'] ?? '');
        $projectId  = (string)($_POST['project_id'] ?? '');
        $check = ce_validate_project_photo($_FILES['photo'] ?? null);
        if ($check !== true) {
            ce_flash_set($check, 'error');
            break;
        }
        $result = ce_add_project_photo($locationId, $projectId, $_FILES['photo']);
        if ($result !== true) {
            ce_flash_set($result, 'error');
            break;
        }
        ce_flash_set('Photo uploaded — it now shows on the live website.');
        break;

    case 'delete_photo':
        $locationId = (string)($_POST['location_id'] ?? '');
        $projectId  = (string)($_POST['project_id'] ?? '');
        $photo      = (string)($_POST['photo'] ?? '');
        $result = ce_delete_photo($locationId, $projectId, $photo);
        if ($result === true) {
            ce_flash_set('Photo removed. A copy is kept in storage/backups/deleted-gallery in case you need it back.');
        } else {
            ce_flash_set($result, 'error');
        }
        break;

    case 'delete_project':
        $locationId = (string)($_POST['location_id'] ?? '');
        $projectId  = (string)($_POST['project_id'] ?? '');
        $result = ce_delete_project($locationId, $projectId);
        if ($result === true) {
            ce_flash_set('Project removed. Copies of its photos are kept in storage/backups/deleted-gallery.');
        } else {
            ce_flash_set($result, 'error');
        }
        break;

    case 'delete_location':
        $locationId = (string)($_POST['location_id'] ?? '');
        $result = ce_delete_location($locationId);
        if ($result === true) {
            ce_flash_set('Location removed. Copies of its photos are kept in storage/backups/deleted-gallery.');
        } else {
            ce_flash_set($result, 'error');
        }
        break;

    default:
        ce_flash_set('Unknown action.', 'error');
}

ce_projects_back();
