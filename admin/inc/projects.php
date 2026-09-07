<?php
/**
 * Ceylon Energy Services — Admin: project gallery
 *
 * Photos live in Cloudinary and the gallery structure lives in MongoDB
 * Atlas. This file no longer writes image files or JSON directly:
 *
 *   Reading  — from assets/data/projects.json, which is a copy of what
 *              the backend holds. Reading from the file rather than the
 *              API means this screen still lists everything when the
 *              backend is stopped.
 *   Writing  — through the API in server/, which updates Cloudinary and
 *              MongoDB together and then refreshes that same file.
 *
 * The previous version numbered photos (project-1.jpg, project-2.jpg)
 * and stored the numbers. Photos are now objects carrying their
 * Cloudinary URLs, so anything that treated a photo as an integer has
 * been removed rather than adapted — casting one of those objects to int
 * is what made every thumbnail request project-1.jpg.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api.php';

/**
 * Load the gallery for display.
 *
 * Unlike the old version this never writes the file back — the backend
 * is the source of truth, and a read should not be able to change it.
 */
function ce_load_projects() {
    if (!file_exists(PROJECTS_JSON)) return [];

    $json = json_decode(file_get_contents(PROJECTS_JSON), true);
    if (!is_array($json)) return [];

    // Normalise so the template can assume every key exists.
    foreach ($json as &$loc) {
        $loc['id']       = $loc['id']   ?? '';
        $loc['name']     = $loc['name'] ?? '';
        $loc['projects'] = is_array($loc['projects'] ?? null) ? $loc['projects'] : [];

        foreach ($loc['projects'] as &$proj) {
            $proj['id']     = $proj['id']   ?? '';
            $proj['name']   = $proj['name'] ?? '';
            $proj['photos'] = is_array($proj['photos'] ?? null) ? $proj['photos'] : [];
        }
        unset($proj);
    }
    unset($loc);

    return $json;
}

/**
 * Best display URL for one photo.
 *
 * Photos are objects since the move to Cloudinary, but a gallery that
 * has not been migrated yet may still hold plain path strings — both are
 * accepted so the panel never renders a broken thumbnail mid-migration.
 */
function ce_photo_thumb($photo) {
    if (is_string($photo)) return $photo;
    if (!is_array($photo)) return 'assets/images/dummy.png';
    return $photo['thumb'] ?? $photo['medium'] ?? $photo['large'] ?? 'assets/images/dummy.png';
}

/** The id used to delete one photo. */
function ce_photo_id($photo) {
    return is_array($photo) ? (string)($photo['id'] ?? '') : '';
}

/* ------------------------------------------------------------------ */
/* Writes — all go through the API                                     */
/* ------------------------------------------------------------------ */

/**
 * Every write follows the same shape: call the API, and on success
 * refresh the local copy so the next page load shows the change.
 * Returns true, or an error message string to show the user.
 */
function ce_apply_write($method, $path, array $fields = [], array $files = []) {
    $res = ce_api_request($method, $path, $fields, $files);
    if (!$res['ok']) return $res['error'];
    ce_refresh_static_json();
    return true;
}

function ce_add_location($name) {
    $name = trim((string)$name);
    if ($name === '') return 'Please enter a location name.';
    return ce_apply_write('POST', '/api/projects/locations', ['name' => $name]);
}

function ce_add_project($locationId, $projectName) {
    $projectName = trim((string)$projectName);
    if ($projectName === '') return 'Please enter a project name.';
    return ce_apply_write(
        'POST',
        '/api/projects/' . rawurlencode($locationId) . '/projects',
        ['name' => $projectName]
    );
}

/** Validate a single uploaded photo ($_FILES['photo']-style entry). */
function ce_validate_project_photo($file) {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return 'Please choose a photo to upload.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'The photo upload failed (error code ' . $file['error'] . ').';
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        return 'That photo is larger than the ' . ce_human_bytes(MAX_IMAGE_BYTES) . ' limit.';
    }

    // Trust the file's actual contents, not its name — an extension can
    // say .jpg over anything at all.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return 'Photos must be a JPG, PNG or WebP image.';
    }
    return true;
}

/** $file is a validated $_FILES['photo']-style entry. */
function ce_add_project_photo($locationId, $projectId, $file) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    return ce_apply_write(
        'POST',
        '/api/projects/' . rawurlencode($locationId) . '/' . rawurlencode($projectId) . '/photos',
        [],
        ['photos' => [
            'tmp'  => $file['tmp_name'],
            'name' => $file['name'],
            'type' => $mime,
        ]]
    );
}

function ce_delete_photo($locationId, $projectId, $photoId) {
    $photoId = (string)$photoId;
    if ($photoId === '') return 'That photo has no id — try reloading the page.';

    return ce_apply_write(
        'DELETE',
        '/api/projects/' . rawurlencode($locationId) . '/' . rawurlencode($projectId)
            . '/photos/' . rawurlencode($photoId)
    );
}

function ce_delete_project($locationId, $projectId) {
    return ce_apply_write(
        'DELETE',
        '/api/projects/' . rawurlencode($locationId) . '/' . rawurlencode($projectId)
    );
}

function ce_delete_location($locationId) {
    return ce_apply_write('DELETE', '/api/projects/' . rawurlencode($locationId));
}
