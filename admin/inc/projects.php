<?php
/**
 * Ceylon Energy Services — Admin: project gallery
 *
 * Everything here is plain PHP working on plain files, which is all a
 * cPanel account gives you: no database server, no Node process to keep
 * alive, no image-hosting account to keep paying for.
 *
 *   The photos      assets/images/completed-projects/<location>/<project>/photo-001.png
 *   Web-sized copies .../<project>/thumbs/photo-001-400.jpg, -900.jpg, -1600.jpg
 *   The structure   assets/data/projects.json
 *
 * assets/js/main.js reads that one JSON file, so whatever this file
 * writes is exactly what the live site shows. Uploading the folder to a
 * different host moves the whole gallery with it.
 *
 * Every function that changes something returns `true` on success or a
 * sentence explaining what went wrong, which project-actions.php shows
 * to the user as-is.
 */
require_once __DIR__ . '/config.php';

/* ------------------------------------------------------------------ */
/* Reading and writing the gallery file                                */
/* ------------------------------------------------------------------ */

/**
 * Load the gallery.
 *
 * Missing keys are filled in rather than left absent, so the template
 * can print $proj['name'] without checking whether it exists first.
 */
function ce_load_projects() {
    if (!file_exists(PROJECTS_JSON)) return [];

    $json = json_decode(file_get_contents(PROJECTS_JSON), true);
    if (!is_array($json)) return [];

    $out = [];
    foreach ($json as $loc) {
        if (!is_array($loc)) continue;

        $projects = [];
        foreach ((is_array($loc['projects'] ?? null) ? $loc['projects'] : []) as $proj) {
            if (!is_array($proj)) continue;
            $projects[] = [
                'id'     => (string)($proj['id'] ?? ''),
                'name'   => (string)($proj['name'] ?? ''),
                'photos' => is_array($proj['photos'] ?? null) ? array_values($proj['photos']) : [],
            ];
        }

        $out[] = [
            'id'       => (string)($loc['id'] ?? ''),
            'name'     => (string)($loc['name'] ?? ''),
            'projects' => $projects,
        ];
    }

    return $out;
}

/**
 * Save the gallery.
 *
 * Written to a temporary file and renamed into place, because rename()
 * is atomic: a visitor loading the site mid-save reads either the old
 * file or the new one, never half of each.
 */
function ce_save_projects($locations) {
    $dir = dirname(PROJECTS_JSON);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;

    $tmp = PROJECTS_JSON . '.tmp';
    $json = json_encode(
        array_values($locations),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if (@file_put_contents($tmp, $json) === false) return false;
    if (!@rename($tmp, PROJECTS_JSON)) { @unlink($tmp); return false; }
    return true;
}

/* ------------------------------------------------------------------ */
/* Ids, names and paths                                                */
/* ------------------------------------------------------------------ */

/**
 * Turn a name into an id that is also safe as a folder name:
 * "Wattala (I C M Perera's site)" -> "proj-wattala-i-c-m-perera-s-site".
 *
 * The prefix keeps the existing folders on disk working unchanged —
 * they are already named loc-* and proj-*.
 */
function ce_gallery_slug($name, $prefix) {
    $slug = strtolower(trim((string)$name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') $slug = 'item';
    return $prefix . $slug;
}

/**
 * Refuse anything that is not a plain slug.
 *
 * Location and project ids arrive from a form and are then used to build
 * a folder path, so a value like "../../.." must never get that far.
 * Returns '' for anything suspicious, and every caller treats '' as
 * "not found".
 */
function ce_gallery_safe_id($id) {
    $id = (string)$id;
    return preg_match('/^[a-z0-9][a-z0-9._-]{0,120}$/i', $id) && strpos($id, '..') === false
        ? $id
        : '';
}

/** Make a slug unique against a list of ids already in use. */
function ce_gallery_unique_slug($slug, array $taken) {
    if (!in_array($slug, $taken, true)) return $slug;
    for ($n = 2; $n < 500; $n++) {
        if (!in_array($slug . '-' . $n, $taken, true)) return $slug . '-' . $n;
    }
    return $slug . '-' . bin2hex(random_bytes(3));
}

/** Absolute path of one project's photo folder. */
function ce_project_dir($locationId, $projectId) {
    return PROJECTS_IMAGES_DIR . '/' . $locationId . '/' . $projectId;
}

/** Site-root-relative path ("assets/images/...") for an absolute path. */
function ce_relative_path($absolute) {
    $root = SITE_ROOT . '/';
    return strpos($absolute, $root) === 0 ? substr($absolute, strlen($root)) : $absolute;
}

/* ------------------------------------------------------------------ */
/* Reading one photo                                                   */
/* ------------------------------------------------------------------ */

/**
 * Best small/medium/large URL for one photo.
 *
 * A photo is normally an array with thumb/medium/large paths. Plain
 * strings are also accepted: that is the shape an older gallery file
 * used, and tolerating it means an out-of-date projects.json still
 * renders instead of showing a page of broken images.
 */
function ce_photo_thumb($photo) {
    if (is_string($photo)) return $photo;
    if (!is_array($photo)) return 'assets/images/dummy.png';
    return $photo['thumb'] ?? $photo['medium'] ?? $photo['large'] ?? $photo['file'] ?? 'assets/images/dummy.png';
}

function ce_photo_large($photo) {
    if (is_string($photo)) return $photo;
    if (!is_array($photo)) return 'assets/images/dummy.png';
    return $photo['large'] ?? $photo['file'] ?? $photo['medium'] ?? $photo['thumb'] ?? 'assets/images/dummy.png';
}

/** The id used to delete one photo — its filename without the extension. */
function ce_photo_id($photo) {
    return is_array($photo) ? (string)($photo['id'] ?? '') : '';
}

/**
 * Make a stored path usable from inside /admin/.
 *
 * Paths in projects.json are relative to the site root, because that is
 * where index.html reads them from. The admin pages live one folder
 * deeper, so they need "../" in front — except for a full http(s) URL,
 * which is already absolute.
 */
function ce_admin_asset_url($path) {
    $path = (string)$path;
    if ($path === '') return '../assets/images/dummy.png';
    if (preg_match('#^(https?:)?//#i', $path)) return $path;
    return '../' . ltrim($path, '/');
}

/* ------------------------------------------------------------------ */
/* Making the smaller copies                                           */
/* ------------------------------------------------------------------ */

/**
 * Can GD hold an image this size?
 *
 * GD works on uncompressed pixels: a 6000x4000 photo needs about 96MB
 * of memory regardless of how small the JPEG on disk is. Asking it to
 * open one that does not fit kills the whole request with a fatal
 * memory error and the upload appears to do nothing. Checking first
 * lets us skip resizing and still keep the photo.
 */
function ce_image_fits_in_memory($width, $height) {
    $limit = ce_ini_bytes(ini_get('memory_limit'));
    if ($limit <= 0) return true; // no limit set

    // 4 bytes per pixel, and we hold the source and the copy at once.
    $needed = (float)$width * (float)$height * 4 * 2.2;
    return ($needed + memory_get_usage(true)) < $limit;
}

/** Open an image file as a GD image, or false if we cannot. */
function ce_image_open($path, $mime) {
    switch ($mime) {
        case 'image/jpeg': return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false;
        case 'image/png':  return function_exists('imagecreatefrompng')  ? @imagecreatefrompng($path)  : false;
        case 'image/webp': return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
    }
    return false;
}

/**
 * Write one smaller JPEG copy of $source.
 *
 * $height === null scales to $width and keeps the proportions; giving a
 * height instead crops to fill that exact box, which is what the square
 * grid cards want. Never enlarges: a photo already smaller than the
 * target is copied at its own size rather than blown up and blurred.
 */
function ce_write_resized_copy($source, $dest, $width, $height = null) {
    if (!function_exists('imagecreatetruecolor')) return false;

    $info = @getimagesize($source);
    if (!$info) return false;

    list($srcW, $srcH) = $info;
    $mime = $info['mime'] ?? '';
    if ($srcW < 1 || $srcH < 1) return false;
    if (!ce_image_fits_in_memory($srcW, $srcH)) return false;

    $src = ce_image_open($source, $mime);
    if (!$src) return false;

    if ($height === null) {
        $scale = min(1, $width / $srcW);
        $dstW = max(1, (int)round($srcW * $scale));
        $dstH = max(1, (int)round($srcH * $scale));
        $cropX = 0; $cropY = 0; $cropW = $srcW; $cropH = $srcH;
    } else {
        // Crop the middle of the photo to the card's shape, then scale.
        $dstW = min($width, $srcW);
        $dstH = max(1, (int)round($dstW * ($height / $width)));
        if ($dstH > $srcH) { $dstH = $srcH; $dstW = max(1, (int)round($dstH * ($width / $height))); }

        $targetRatio = $width / $height;
        if ($srcW / $srcH > $targetRatio) {
            $cropH = $srcH;
            $cropW = max(1, (int)round($srcH * $targetRatio));
        } else {
            $cropW = $srcW;
            $cropH = max(1, (int)round($srcW / $targetRatio));
        }
        $cropX = (int)(($srcW - $cropW) / 2);
        $cropY = (int)(($srcH - $cropH) / 2);
    }

    $dst = @imagecreatetruecolor($dstW, $dstH);
    if (!$dst) { imagedestroy($src); return false; }

    // JPEG has no transparency, so anything see-through would come out
    // black. Fill with white first.
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);

    $ok = @imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $dstW, $dstH, $cropW, $cropH);
    if ($ok) {
        $dir = dirname($dest);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $ok = @imagejpeg($dst, $dest, PROJECTS_JPEG_QUALITY);
    }

    imagedestroy($src);
    imagedestroy($dst);
    return (bool)$ok;
}

/**
 * Build the thumb and medium copies for one photo and return the paths
 * the gallery file should record.
 *
 * If GD is unavailable or the photo is too large to resize, the
 * original stands in for every size. The gallery then still works — it
 * just sends bigger files than it needs to, which is far better than
 * refusing the upload.
 *
 * The original is never modified or replaced, so the full-quality photo
 * is always still there under its own name.
 *
 * A copy that already exists and is newer than the photo is left alone.
 * That is what keeps a rescan of a large gallery quick: resizing is the
 * slow part, and on the second run there is almost nothing to do.
 */
function ce_build_photo_sizes($photoAbsPath) {
    $file = ce_relative_path($photoAbsPath);
    $sizes = ['file' => $file, 'thumb' => $file, 'medium' => $file, 'large' => $file];

    $dir  = dirname($photoAbsPath) . '/' . PROJECTS_THUMB_DIRNAME;
    $stem = pathinfo($photoAbsPath, PATHINFO_FILENAME);
    $sourceTime = @filemtime($photoAbsPath) ?: 0;

    // The original file is never touched -- it stays on disk as the
    // archive copy. What the website actually serves is these three.
    $wanted = [
        'thumb'  => [$dir . '/' . $stem . '-' . PROJECTS_THUMB_WIDTH . '.jpg',  PROJECTS_THUMB_WIDTH,  PROJECTS_THUMB_HEIGHT],
        'medium' => [$dir . '/' . $stem . '-' . PROJECTS_MEDIUM_WIDTH . '.jpg', PROJECTS_MEDIUM_WIDTH, null],
        'large'  => [$dir . '/' . $stem . '-' . PROJECTS_LARGE_WIDTH . '.jpg',  PROJECTS_LARGE_WIDTH,  null],
    ];

    foreach ($wanted as $key => list($dest, $width, $height)) {
        $upToDate = is_file($dest) && filesize($dest) > 0 && @filemtime($dest) >= $sourceTime;
        if ($upToDate || ce_write_resized_copy($photoAbsPath, $dest, $width, $height)) {
            $sizes[$key] = ce_relative_path($dest);
        }
    }

    return $sizes;
}

/* ------------------------------------------------------------------ */
/* Adding                                                              */
/* ------------------------------------------------------------------ */

function ce_add_location($name) {
    $name = trim((string)$name);
    if ($name === '') return 'Please enter a location name.';

    $locations = ce_load_projects();
    $taken = array_column($locations, 'id');
    $id = ce_gallery_unique_slug(ce_gallery_slug($name, 'loc-'), $taken);

    $locations[] = ['id' => $id, 'name' => $name, 'projects' => []];

    if (!ce_save_projects($locations)) {
        return 'Could not save ' . basename(PROJECTS_JSON) . '. Check that '
             . ce_relative_path(dirname(PROJECTS_JSON)) . ' is writable (permissions 755).';
    }
    return true;
}

function ce_add_project($locationId, $projectName) {
    $projectName = trim((string)$projectName);
    if ($projectName === '') return 'Please enter a project name.';

    $locationId = ce_gallery_safe_id($locationId);
    $locations  = ce_load_projects();

    foreach ($locations as &$loc) {
        if ($loc['id'] !== $locationId) continue;

        $taken = array_column($loc['projects'], 'id');
        $id = ce_gallery_unique_slug(ce_gallery_slug($projectName, 'proj-'), $taken);
        $loc['projects'][] = ['id' => $id, 'name' => $projectName, 'photos' => []];
        unset($loc);

        if (!ce_save_projects($locations)) {
            return 'Could not save ' . basename(PROJECTS_JSON) . ' — check the folder permissions.';
        }
        return true;
    }
    unset($loc);

    return 'That location no longer exists — reload the page and try again.';
}

/**
 * Check an uploaded photo before we touch the disk.
 *
 * The file's real contents decide whether it is an image, not its
 * name — an extension can claim .jpg over absolutely anything.
 * Returns true, or the reason to show the user.
 */
function ce_validate_project_photo($file) {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return 'Please choose a photo to upload.';
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return 'That photo is bigger than this server accepts ('
             . ce_human_bytes(ce_ini_bytes(ini_get('upload_max_filesize'))) . ' per file).';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'The photo upload failed (error code ' . $file['error'] . ').';
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        return 'That photo is larger than the ' . ce_human_bytes(MAX_IMAGE_BYTES) . ' limit.';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return 'Photos must be a JPG, PNG or WebP image.';
    }
    return true;
}

/** The next unused photo-NNN name in a project folder. */
function ce_next_photo_stem($projectDir) {
    $max = 0;
    foreach ((array)@glob($projectDir . '/photo-*.*') as $path) {
        if (preg_match('/photo-(\d+)$/', pathinfo($path, PATHINFO_FILENAME), $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return sprintf('photo-%03d', $max + 1);
}

/** $file is a $_FILES['photo']-style entry already passed through ce_validate_project_photo(). */
function ce_add_project_photo($locationId, $projectId, $file) {
    $locationId = ce_gallery_safe_id($locationId);
    $projectId  = ce_gallery_safe_id($projectId);

    $locations = ce_load_projects();
    $locIndex = $projIndex = null;
    foreach ($locations as $i => $loc) {
        if ($loc['id'] !== $locationId) continue;
        foreach ($loc['projects'] as $j => $proj) {
            if ($proj['id'] !== $projectId) continue;
            $locIndex = $i;
            $projIndex = $j;
            break 2;
        }
    }
    if ($locIndex === null) {
        return 'That project no longer exists — reload the page and try again.';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? 'jpg';

    $dir = ce_project_dir($locationId, $projectId);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return 'Could not create the folder ' . ce_relative_path($dir)
             . '. Check that assets/images/completed-projects is writable (permissions 755).';
    }

    $stem = ce_next_photo_stem($dir);
    $dest = $dir . '/' . $stem . '.' . $ext;

    if (!@move_uploaded_file($file['tmp_name'], $dest)) {
        return 'Could not save the photo into ' . ce_relative_path($dir)
             . '. That folder is usually not writable — set its permissions to 755 in File Manager.';
    }
    @chmod($dest, 0644);

    $locations[$locIndex]['projects'][$projIndex]['photos'][] = array_merge([
        'id'      => $stem,
        'caption' => $locations[$locIndex]['name'] . ' — ' . $locations[$locIndex]['projects'][$projIndex]['name'],
    ], ce_build_photo_sizes($dest));

    if (!ce_save_projects($locations)) {
        // The file is on disk but the gallery file did not record it, so
        // take the file back out rather than leave an orphan behind.
        @unlink($dest);
        return 'The photo uploaded but ' . basename(PROJECTS_JSON) . ' could not be saved — check its permissions (644).';
    }
    return true;
}

/* ------------------------------------------------------------------ */
/* Removing                                                            */
/* ------------------------------------------------------------------ */

/**
 * Move a file into storage/backups/deleted-gallery/ instead of erasing
 * it, so an accidental click can be undone from File Manager. Falls
 * back to deleting if the backup folder cannot be created.
 */
function ce_backup_gallery_file($absPath) {
    if (!is_file($absPath)) return;

    if (!is_dir(BACKUP_DELETED_GALLERY_DIR) && !@mkdir(BACKUP_DELETED_GALLERY_DIR, 0755, true)) {
        @unlink($absPath);
        return;
    }

    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '-' . basename($absPath);
    if (!@rename($absPath, BACKUP_DELETED_GALLERY_DIR . '/' . $name)) {
        @unlink($absPath);
    }
}

/** Back up the photo and its generated copies. */
function ce_remove_photo_files($photo) {
    foreach (['file', 'large', 'medium', 'thumb'] as $key) {
        $rel = is_array($photo) ? ($photo[$key] ?? '') : (string)$photo;
        if (!is_string($rel) || $rel === '') continue;
        if (preg_match('#^(https?:)?//#i', $rel)) continue;    // a full URL from an older setup — nothing local to remove
        if (strpos($rel, '..') !== false) continue;            // never follow a path out of the site

        $abs = SITE_ROOT . '/' . ltrim($rel, '/');
        if (strpos($abs, PROJECTS_IMAGES_DIR . '/') !== 0) continue; // only ever inside the gallery folder
        ce_backup_gallery_file($abs);
    }
}

/**
 * Remove a folder once nothing is left in it, and only ever inside the
 * gallery folder.
 *
 * Without this, deleting a project leaves its empty folder behind — and
 * because ce_rescan_gallery() reads the folders to decide what exists,
 * the next rescan would bring the deleted project straight back as an
 * empty entry.
 */
function ce_remove_dir_if_empty($dir) {
    if (strpos($dir, PROJECTS_IMAGES_DIR . '/') !== 0) return;   // never above the gallery
    if (!is_dir($dir)) return;

    $left = @scandir($dir);
    if ($left === false) return;
    if (array_diff($left, ['.', '..'])) return;                  // something is still in there

    @rmdir($dir);
}

/** Clear away a project's folder, and its location's if that empties too. */
function ce_cleanup_project_dir($locationId, $projectId) {
    $projectDir = ce_project_dir($locationId, $projectId);
    ce_remove_dir_if_empty($projectDir . '/' . PROJECTS_THUMB_DIRNAME);
    ce_remove_dir_if_empty($projectDir);
    ce_remove_dir_if_empty(PROJECTS_IMAGES_DIR . '/' . $locationId);
}

function ce_delete_photo($locationId, $projectId, $photoId) {
    $locationId = ce_gallery_safe_id($locationId);
    $projectId  = ce_gallery_safe_id($projectId);
    $photoId    = (string)$photoId;
    if ($photoId === '') return 'That photo has no id — try reloading the page.';

    $locations = ce_load_projects();

    foreach ($locations as &$loc) {
        if ($loc['id'] !== $locationId) continue;
        foreach ($loc['projects'] as &$proj) {
            if ($proj['id'] !== $projectId) continue;

            $keep = [];
            $removed = null;
            foreach ($proj['photos'] as $photo) {
                if ($removed === null && ce_photo_id($photo) === $photoId) {
                    $removed = $photo;
                } else {
                    $keep[] = $photo;
                }
            }
            if ($removed === null) return 'That photo was already removed — reload the page.';

            $proj['photos'] = $keep;
            unset($loc, $proj);

            if (!ce_save_projects($locations)) {
                return 'Could not save ' . basename(PROJECTS_JSON) . ' — check its permissions (644).';
            }
            // Files go only after the gallery file is safely written, so
            // a failed save never leaves the site pointing at a photo
            // that is no longer there.
            ce_remove_photo_files($removed);
            return true;
        }
        unset($proj);
    }
    unset($loc);

    return 'That project no longer exists — reload the page and try again.';
}

function ce_delete_project($locationId, $projectId) {
    $locationId = ce_gallery_safe_id($locationId);
    $projectId  = ce_gallery_safe_id($projectId);

    $locations = ce_load_projects();

    foreach ($locations as &$loc) {
        if ($loc['id'] !== $locationId) continue;

        $keep = [];
        $removed = null;
        foreach ($loc['projects'] as $proj) {
            if ($removed === null && $proj['id'] === $projectId) {
                $removed = $proj;
            } else {
                $keep[] = $proj;
            }
        }
        if ($removed === null) return 'That project was already removed — reload the page.';

        $loc['projects'] = $keep;
        unset($loc);

        if (!ce_save_projects($locations)) {
            return 'Could not save ' . basename(PROJECTS_JSON) . ' — check its permissions (644).';
        }
        foreach ($removed['photos'] as $photo) {
            ce_remove_photo_files($photo);
        }
        ce_cleanup_project_dir($locationId, $projectId);
        return true;
    }
    unset($loc);

    return 'That location no longer exists — reload the page and try again.';
}

function ce_delete_location($locationId) {
    $locationId = ce_gallery_safe_id($locationId);

    $locations = ce_load_projects();
    $keep = [];
    $removed = null;

    foreach ($locations as $loc) {
        if ($removed === null && $loc['id'] === $locationId) {
            $removed = $loc;
        } else {
            $keep[] = $loc;
        }
    }
    if ($removed === null) return 'That location was already removed — reload the page.';

    if (!ce_save_projects($keep)) {
        return 'Could not save ' . basename(PROJECTS_JSON) . ' — check its permissions (644).';
    }
    foreach ($removed['projects'] as $proj) {
        foreach ($proj['photos'] as $photo) {
            ce_remove_photo_files($photo);
        }
        ce_cleanup_project_dir($locationId, $proj['id']);
    }
    ce_remove_dir_if_empty(PROJECTS_IMAGES_DIR . '/' . $locationId);
    return true;
}

/* ------------------------------------------------------------------ */
/* Rescanning the folders                                              */
/* ------------------------------------------------------------------ */

/**
 * Turn a folder name back into something readable, for a project that
 * appears on disk without a name in the gallery file:
 * "proj-belihuloya-project-01" -> "Belihuloya project 01".
 */
function ce_name_from_slug($slug, $prefix) {
    $name = $slug;
    if (strpos($name, $prefix) === 0) $name = substr($name, strlen($prefix));
    $name = trim(str_replace('-', ' ', $name));
    return $name === '' ? 'Untitled' : ucfirst($name);
}

/** Every image file directly inside a project folder, sorted by name. */
function ce_photo_files_in($dir) {
    $files = [];
    foreach ((array)@glob($dir . '/*') as $path) {
        if (!is_file($path)) continue;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) continue;
        $files[] = $path;
    }
    sort($files, SORT_NATURAL);
    return $files;
}

/**
 * Rebuild assets/data/projects.json from the photos actually on disk.
 *
 * This exists because the folders are the real gallery — the JSON file
 * only describes them. So it is always safe to run, and it is the fix
 * for the two things that otherwise need a developer:
 *
 *   - photos copied straight into a folder with File Manager or FTP,
 *     which no upload form ever saw
 *   - a projects.json that has drifted out of step with the folders
 *
 * Names already in the file are kept (a folder cannot remember that
 * "loc-piliyandala" is spelled "piliyandala"), and locations or projects
 * with no folder yet are kept too, so a location added a minute ago and
 * not yet filled does not disappear.
 *
 * Returns counts plus any notes worth showing.
 */
function ce_rescan_gallery() {
    $existing = ce_load_projects();

    // Names and captions from the current file, so a rescan never loses
    // wording that was typed by hand.
    $knownNames = [];
    $knownCaptions = [];
    foreach ($existing as $loc) {
        $knownNames[$loc['id']] = $loc['name'];
        foreach ($loc['projects'] as $proj) {
            $knownNames[$loc['id'] . '/' . $proj['id']] = $proj['name'];
            foreach ($proj['photos'] as $photo) {
                $id = ce_photo_id($photo);
                if ($id !== '' && is_array($photo) && !empty($photo['caption'])) {
                    $knownCaptions[$loc['id'] . '/' . $proj['id'] . '/' . $id] = $photo['caption'];
                }
            }
        }
    }

    $stats = ['locations' => 0, 'projects' => 0, 'photos' => 0, 'resized' => 0, 'notes' => []];

    // Folders on disk, indexed so we can tell which ones the file
    // already knows about.
    $diskLocations = [];
    foreach ((array)@glob(PROJECTS_IMAGES_DIR . '/*', GLOB_ONLYDIR) as $locDir) {
        $diskLocations[basename($locDir)] = $locDir;
    }

    // Existing order first, then anything new found on disk, so a
    // rescan never reshuffles the live site.
    $locationIds = array_column($existing, 'id');
    foreach (array_keys($diskLocations) as $slug) {
        if (!in_array($slug, $locationIds, true)) $locationIds[] = $slug;
    }

    $out = [];
    foreach ($locationIds as $locId) {
        if (ce_gallery_safe_id($locId) === '') continue;

        $prevProjects = [];
        foreach ($existing as $loc) {
            if ($loc['id'] === $locId) $prevProjects = $loc['projects'];
        }

        $locDir = $diskLocations[$locId] ?? null;
        $diskProjects = [];
        if ($locDir !== null) {
            foreach ((array)@glob($locDir . '/*', GLOB_ONLYDIR) as $projDir) {
                if (basename($projDir) === PROJECTS_THUMB_DIRNAME) continue;
                $diskProjects[basename($projDir)] = $projDir;
            }
        }

        $projectIds = array_column($prevProjects, 'id');
        foreach (array_keys($diskProjects) as $slug) {
            if (!in_array($slug, $projectIds, true)) $projectIds[] = $slug;
        }

        $projects = [];
        foreach ($projectIds as $projId) {
            if (ce_gallery_safe_id($projId) === '') continue;

            $projName = $knownNames[$locId . '/' . $projId] ?? ce_name_from_slug($projId, 'proj-');
            $locName  = $knownNames[$locId] ?? ce_name_from_slug($locId, 'loc-');

            $photos = [];
            foreach (ce_photo_files_in($diskProjects[$projId] ?? '') as $path) {
                $stem = pathinfo($path, PATHINFO_FILENAME);
                $sizes = ce_build_photo_sizes($path);
                if ($sizes['thumb'] !== $sizes['file']) $stats['resized']++;

                $photos[] = array_merge([
                    'id'      => $stem,
                    'caption' => $knownCaptions[$locId . '/' . $projId . '/' . $stem]
                                 ?? ($locName . ' — ' . $projName),
                ], $sizes);
            }

            $projects[] = ['id' => $projId, 'name' => $projName, 'photos' => $photos];
            $stats['projects']++;
            $stats['photos'] += count($photos);
        }

        $out[] = [
            'id'       => $locId,
            'name'     => $knownNames[$locId] ?? ce_name_from_slug($locId, 'loc-'),
            'projects' => $projects,
        ];
        $stats['locations']++;
    }

    if ($stats['photos'] > 0 && $stats['resized'] === 0 && !function_exists('imagecreatetruecolor')) {
        $stats['notes'][] = 'PHP\'s GD image extension is not enabled here, so no smaller copies '
                          . 'could be made — the gallery will show the full-size photos instead. '
                          . 'Ask your host to enable GD (in cPanel: Select PHP Version → Extensions → gd).';
    }

    if (!ce_save_projects($out)) {
        return ['error' => 'Could not write ' . ce_relative_path(PROJECTS_JSON)
                         . '. Set that file\'s permissions to 644 and its folder\'s to 755, then try again.'];
    }

    return $stats;
}
