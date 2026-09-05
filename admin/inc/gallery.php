<?php
/**
 * Ceylon Energy Services — Admin: project gallery (locations -> projects -> photos)
 *
 * This powers the "Our Projects" section on the live site (assets/js/main.js
 * fetches GALLERY_JSON instead of using a hardcoded array). Like the
 * attachments system, everything here is additive — adding a location,
 * project, or photo never touches what's already published — and
 * deletions move files to a backup folder instead of erasing them.
 */
require_once __DIR__ . '/config.php';

/** Load the gallery tree exactly as stored on disk. */
function ce_load_gallery_raw() {
    if (!file_exists(GALLERY_JSON)) return [];
    $json = json_decode(file_get_contents(GALLERY_JSON), true);
    return is_array($json) ? $json : [];
}

function ce_save_gallery_raw($items) {
    if (!is_dir(DOCS_DIR)) mkdir(DOCS_DIR, 0755, true);
    $tmp = GALLERY_JSON . '.tmp';
    file_put_contents($tmp, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($tmp, GALLERY_JSON);
}

/** For the admin screen: locations sorted alphabetically by name. */
function ce_list_gallery() {
    $items = ce_load_gallery_raw();
    usort($items, function ($a, $b) {
        return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
    });
    return $items;
}

/** Turn a name into a URL/file-safe slug, e.g. "Belihuloya Site #2" -> "belihuloya-site-2". */
function ce_slugify($str) {
    $slug = strtolower(trim((string)$str));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'item';
}

/** Make an id unique among $existingIds by appending a short random suffix if needed. */
function ce_unique_id($base, array $existingIds) {
    if (!in_array($base, $existingIds, true)) return $base;
    return $base . '-' . bin2hex(random_bytes(2));
}

/**
 * Add a brand-new location (top-level album). Returns the new location's
 * id, or false if the name is blank/too long or a location with that
 * name already exists.
 */
function ce_add_location($name) {
    $name = trim((string)$name);
    $name = preg_replace('/\s+/', ' ', strip_tags($name));
    if ($name === '' || mb_strlen($name) > 80) return false;

    $items = ce_load_gallery_raw();
    foreach ($items as $loc) {
        if (mb_strtolower($loc['name'] ?? '') === mb_strtolower($name)) {
            return false; // keep location names unique
        }
    }

    $existingIds = array_column($items, 'id');
    $id = ce_unique_id('loc-' . ce_slugify($name), $existingIds);

    $items[] = [
        'id'        => $id,
        'name'      => $name,
        'createdAt' => date('c'),
        'projects'  => [],
    ];
    ce_save_gallery_raw($items);
    return $id;
}

/**
 * Add a new project (sub-album) under an existing location. Returns the
 * new project's id, or false if the location doesn't exist or the name
 * is invalid.
 */
function ce_add_project($locationId, $name) {
    $name = trim((string)$name);
    $name = preg_replace('/\s+/', ' ', strip_tags($name));
    if ($name === '' || mb_strlen($name) > 120) return false;

    $items = ce_load_gallery_raw();
    foreach ($items as &$loc) {
        if (($loc['id'] ?? '') !== $locationId) continue;

        if (!isset($loc['projects']) || !is_array($loc['projects'])) $loc['projects'] = [];
        $existingIds = array_column($loc['projects'], 'id');
        $id = ce_unique_id('proj-' . ce_slugify($name), $existingIds);

        $loc['projects'][] = [
            'id'        => $id,
            'name'      => $name,
            'createdAt' => date('c'),
            'photos'    => [],
        ];
        ce_save_gallery_raw($items);
        return $id;
    }
    unset($loc);
    return false; // location not found
}

/**
 * Validate an uploaded set of gallery photos ($_FILES['photos']-style
 * multi-file entry). Accepts JPG or PNG. Returns an ordered array of
 * ['tmp' => tmp_name, 'name' => original_name] on success, or an error
 * message string.
 */
function ce_validate_gallery_photo_files($filesEntry) {
    if (empty($filesEntry) || empty($filesEntry['name'][0])) {
        return 'Please choose at least one photo (JPG or PNG).';
    }

    $count = count($filesEntry['name']);
    if ($count > MAX_GALLERY_PHOTOS_PER_UPLOAD) {
        return 'That\'s more photos than supported in one go (' . MAX_GALLERY_PHOTOS_PER_UPLOAD . ' max — upload in batches).';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $valid = [];
    $okMimes = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png']];

    for ($i = 0; $i < $count; $i++) {
        $error = $filesEntry['error'][$i];
        $tmp   = $filesEntry['tmp_name'][$i];
        $name  = $filesEntry['name'][$i];
        $size  = $filesEntry['size'][$i];

        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) {
            finfo_close($finfo);
            return 'One of the photos failed to upload (error code ' . $error . ').';
        }
        if ($size > MAX_GALLERY_PHOTO_BYTES) {
            finfo_close($finfo);
            return '"' . $name . '" is larger than the ' . ce_human_bytes(MAX_GALLERY_PHOTO_BYTES) . ' per-photo limit.';
        }

        $mime = finfo_file($finfo, $tmp);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset($okMimes[$mime]) || !in_array($ext, $okMimes[$mime], true)) {
            finfo_close($finfo);
            return '"' . $name . '" must be a JPG or PNG image.';
        }

        $valid[] = ['tmp' => $tmp, 'name' => $name];
    }
    finfo_close($finfo);

    if (empty($valid)) {
        return 'Please choose at least one photo (JPG or PNG).';
    }
    return $valid;
}

/**
 * Save validated photos into an existing project, appending them after
 * whatever's already there — existing photos are never touched or
 * renumbered. $validFiles is the array returned by
 * ce_validate_gallery_photo_files(). Returns the number of photos added,
 * or false if the location/project doesn't exist or nothing could be saved.
 */
function ce_add_project_photos($locationId, $projectId, array $validFiles) {
    $items = ce_load_gallery_raw();

    foreach ($items as &$loc) {
        if (($loc['id'] ?? '') !== $locationId) continue;

        foreach ($loc['projects'] as &$proj) {
            if (($proj['id'] ?? '') !== $projectId) continue;

            $dir = GALLERY_IMAGES_DIR . '/' . $locationId . '/' . $projectId;
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            if (!isset($proj['photos']) || !is_array($proj['photos'])) $proj['photos'] = [];
            $nextNum = count($proj['photos']) + 1;
            $added = 0;

            foreach ($validFiles as $file) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext === 'jpeg') $ext = 'jpg';
                $fileName = sprintf('photo-%03d.%s', $nextNum, $ext);
                $dest = $dir . '/' . $fileName;

                if (!move_uploaded_file($file['tmp'], $dest)) {
                    continue; // skip this one, keep going with the rest
                }
                $proj['photos'][] = 'assets/images/completed-projects/' . $locationId . '/' . $projectId . '/' . $fileName;
                $nextNum++;
                $added++;
            }

            if ($added > 0) {
                ce_save_gallery_raw($items);
            }
            return $added > 0 ? $added : false;
        }
        unset($proj);
        return false; // project not found
    }
    unset($loc);
    return false; // location not found
}

/** Remove a single photo (by its index within the project's photo list). */
function ce_delete_photo($locationId, $projectId, $photoIndex) {
    $items = ce_load_gallery_raw();

    foreach ($items as &$loc) {
        if (($loc['id'] ?? '') !== $locationId) continue;

        foreach ($loc['projects'] as &$proj) {
            if (($proj['id'] ?? '') !== $projectId) continue;

            if (!isset($proj['photos'][$photoIndex])) return false;

            $url = $proj['photos'][$photoIndex];
            array_splice($proj['photos'], $photoIndex, 1);
            ce_save_gallery_raw($items);

            $src = SITE_ROOT . '/' . ltrim($url, '/');
            if (file_exists($src)) {
                if (!is_dir(BACKUP_DELETED_GALLERY_DIR)) mkdir(BACKUP_DELETED_GALLERY_DIR, 0755, true);
                $backupName = date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '-' . basename($src);
                @rename($src, BACKUP_DELETED_GALLERY_DIR . '/' . $backupName);
            }
            return true;
        }
        unset($proj);
        return false;
    }
    unset($loc);
    return false;
}

/** Remove an entire project and all its photos (moved to a backup folder). */
function ce_delete_project($locationId, $projectId) {
    $items = ce_load_gallery_raw();

    foreach ($items as &$loc) {
        if (($loc['id'] ?? '') !== $locationId) continue;

        $keep = [];
        $removed = null;
        foreach (($loc['projects'] ?? []) as $proj) {
            if (($proj['id'] ?? '') === $projectId) {
                $removed = $proj;
            } else {
                $keep[] = $proj;
            }
        }
        if (!$removed) return false;

        $loc['projects'] = $keep;
        ce_save_gallery_raw($items);

        $dir = GALLERY_IMAGES_DIR . '/' . $locationId . '/' . $projectId;
        if (is_dir($dir)) {
            if (!is_dir(BACKUP_DELETED_GALLERY_DIR)) mkdir(BACKUP_DELETED_GALLERY_DIR, 0755, true);
            $backupDir = BACKUP_DELETED_GALLERY_DIR . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '-' . $projectId;
            @rename($dir, $backupDir);
        }
        return true;
    }
    unset($loc);
    return false;
}

/** Remove an entire location and everything under it (moved to a backup folder). */
function ce_delete_location($locationId) {
    $items = ce_load_gallery_raw();

    $keep = [];
    $removed = null;
    foreach ($items as $loc) {
        if (($loc['id'] ?? '') === $locationId) {
            $removed = $loc;
        } else {
            $keep[] = $loc;
        }
    }
    if (!$removed) return false;

    ce_save_gallery_raw($keep);

    $dir = GALLERY_IMAGES_DIR . '/' . $locationId;
    if (is_dir($dir)) {
        if (!is_dir(BACKUP_DELETED_GALLERY_DIR)) mkdir(BACKUP_DELETED_GALLERY_DIR, 0755, true);
        $backupDir = BACKUP_DELETED_GALLERY_DIR . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '-' . $locationId;
        @rename($dir, $backupDir);
    }
    return true;
}
