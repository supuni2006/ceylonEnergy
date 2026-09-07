<?php
require_once __DIR__ . '/config.php';

/** Load the public awards list, newest first. */
function ce_load_awards() {
    $items = ce_load_awards_raw();
    usort($items, function ($a, $b) {
        return strcmp($b['addedAt'] ?? '', $a['addedAt'] ?? '');
    });
    return $items;
}

/** Load the raw (unsorted) list, as stored on disk. */
function ce_load_awards_raw() {
    if (!file_exists(AWARDS_JSON)) return [];
    $json = json_decode(file_get_contents(AWARDS_JSON), true);
    return is_array($json) ? $json : [];
}

function ce_save_awards_raw($items) {
    $dir = dirname(AWARDS_JSON);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $tmp = AWARDS_JSON . '.tmp';
    file_put_contents($tmp, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($tmp, AWARDS_JSON);
}

/**
 * Validate an uploaded award image ($_FILES['image']-style entry).
 * Returns the file extension to save it under ('jpg'/'png'/'webp') if
 * valid, or an error message string if not.
 */
function ce_validate_award_image($file) {
    if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return 'Please choose an award image to upload.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'The image upload failed (error code ' . $file['error'] . ').';
    }
    if ($file['size'] > MAX_AWARD_IMAGE_BYTES) {
        return 'That image is larger than the ' . ce_human_bytes(MAX_AWARD_IMAGE_BYTES) . ' limit.';
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($map[$mime])) {
        return 'Award images must be a JPG, PNG, or WEBP file.';
    }
    return $map[$mime];
}

/**
 * Add a new award. $file is a $_FILES['image']-style entry that has
 * already been validated with ce_validate_award_image(); $ext is the
 * extension that call returned.
 */
function ce_add_award($title, $year, $file, $ext) {
    if (!is_dir(AWARDS_IMAGES_DIR)) mkdir(AWARDS_IMAGES_DIR, 0755, true);

    $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $dest = AWARDS_IMAGES_DIR . '/' . $id . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return false;
    }

    $items = ce_load_awards_raw();
    $items[] = [
        'id'       => $id,
        'title'    => $title !== '' ? $title : 'Award',
        'year'     => $year !== '' ? $year : null,
        'imageUrl' => 'assets/images/awards/' . $id . '.' . $ext,
        'addedAt'  => date('c'),
    ];
    ce_save_awards_raw($items);

    return $id;
}

/** Remove an award by id, deleting its image file too. */
function ce_delete_award($id) {
    $items = ce_load_awards_raw();
    $keep = [];
    $removed = null;

    foreach ($items as $item) {
        if (($item['id'] ?? '') === $id) {
            $removed = $item;
        } else {
            $keep[] = $item;
        }
    }

    if (!$removed) return false;

    ce_save_awards_raw($keep);

    $path = SITE_ROOT . '/' . ltrim($removed['imageUrl'] ?? '', '/');
    if ($path !== SITE_ROOT . '/' && file_exists($path)) {
        @unlink($path);
    }

    return true;
}
