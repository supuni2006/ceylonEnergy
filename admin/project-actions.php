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
        ce_add_location($name);
        ce_flash_set('Location added.');
        break;

    case 'add_project':
        $locationId = (string)($_POST['location_id'] ?? '');
        $name = trim(strip_tags((string)($_POST['name'] ?? '')));
        if ($name === '') {
            ce_flash_set('Please enter a project name.', 'error');
            break;
        }
        if (!ce_add_project($locationId, $name)) {
            ce_flash_set('Could not add the project — location not found.', 'error');
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
        if (!ce_add_project_photo($locationId, $projectId, $_FILES['photo'])) {
            ce_flash_set('Could not save the photo. Please check server file permissions and try again.', 'error');
            break;
        }
        ce_flash_set('Photo added — it now shows on the live website.');
        break;

    case 'delete_photo':
        $locationId = (string)($_POST['location_id'] ?? '');
        $projectId  = (string)($_POST['project_id'] ?? '');
        $photo      = (int)($_POST['photo'] ?? 0);
        if (ce_delete_photo($locationId, $projectId, $photo)) {
            ce_flash_set('Photo removed.');
        } else {
            ce_flash_set('Could not find that photo — it may already be removed.', 'error');
        }
        break;

    case 'delete_project':
        $locationId = (string)($_POST['location_id'] ?? '');
        $projectId  = (string)($_POST['project_id'] ?? '');
        if (ce_delete_project($locationId, $projectId)) {
            ce_flash_set('Project removed.');
        } else {
            ce_flash_set('Could not find that project — it may already be removed.', 'error');
        }
        break;

    case 'delete_location':
        $locationId = (string)($_POST['location_id'] ?? '');
        if (ce_delete_location($locationId)) {
            ce_flash_set('Location removed.');
        } else {
            ce_flash_set('Could not find that location — it may already be removed.', 'error');
        }
        break;

    default:
        ce_flash_set('Unknown action.', 'error');
}

ce_projects_back();
