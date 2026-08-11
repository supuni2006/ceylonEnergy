<?php
require_once __DIR__ . '/config.php';

/** Load the public attachments list, newest first. */
function ce_list_attachments() {
    if (!file_exists(ATTACHMENTS_JSON)) return [];
    $json = json_decode(file_get_contents(ATTACHMENTS_JSON), true);
    $items = is_array($json) ? $json : [];
    usort($items, function ($a, $b) {
        return strcmp($b['uploadedAt'] ?? '', $a['uploadedAt'] ?? '');
    });
    return $items;
}

/** Load the raw (unsorted) list, as stored on disk. */
function ce_load_attachments_raw() {
    if (!file_exists(ATTACHMENTS_JSON)) return [];
    $json = json_decode(file_get_contents(ATTACHMENTS_JSON), true);
    return is_array($json) ? $json : [];
}

function ce_save_attachments_raw($items) {
    if (!is_dir(DOCS_DIR)) mkdir(DOCS_DIR, 0755, true);
    $tmp = ATTACHMENTS_JSON . '.tmp';
    file_put_contents($tmp, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($tmp, ATTACHMENTS_JSON);
}

/**
 * Validate an uploaded PDF ($_FILES['pdf']-style single-file entry).
 * Returns true if valid, or an error message string if not.
 */
function ce_validate_pdf_file($file) {
    if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return 'Please choose a PDF to upload.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'The PDF upload failed (error code ' . $file['error'] . '). Please try again.';
    }
    if ($file['size'] > MAX_PDF_BYTES) {
        return 'That PDF is larger than the ' . ce_human_bytes(MAX_PDF_BYTES) . ' limit.';
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $extOk = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'pdf';
    if ($mime !== 'application/pdf' || !$extOk) {
        return 'The main file must be a PDF.';
    }
    return true;
}

/**
 * Validate an uploaded set of page images ($_FILES['page_images']-style
 * multi-file entry). Returns an ordered array of valid tmp paths, or an
 * error message string if something's wrong.
 */
function ce_validate_page_image_files($filesEntry) {
    if (empty($filesEntry) || empty($filesEntry['name'][0])) {
        return 'Please choose at least one page image (JPG).';
    }

    $count = count($filesEntry['name']);
    if ($count > MAX_ATTACHMENT_PAGES) {
        return 'That\'s more pages than supported (' . MAX_ATTACHMENT_PAGES . ' max).';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $validTmpPaths = [];

    for ($i = 0; $i < $count; $i++) {
        $error = $filesEntry['error'][$i];
        $tmp   = $filesEntry['tmp_name'][$i];
        $name  = $filesEntry['name'][$i];
        $size  = $filesEntry['size'][$i];

        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) {
            finfo_close($finfo);
            return 'One of the page images failed to upload (error code ' . $error . ').';
        }
        if ($size > MAX_IMAGE_BYTES) {
            finfo_close($finfo);
            return '"' . $name . '" is larger than the ' . ce_human_bytes(MAX_IMAGE_BYTES) . ' per-page limit.';
        }

        $mime = finfo_file($finfo, $tmp);
        $extOk = in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true);
        if ($mime !== 'image/jpeg' || !$extOk) {
            finfo_close($finfo);
            return '"' . $name . '" must be a JPG image.';
        }

        $validTmpPaths[] = $tmp;
    }
    finfo_close($finfo);

    if (empty($validTmpPaths)) {
        return 'Please choose at least one page image (JPG).';
    }

    return $validTmpPaths;
}

// ce_human_bytes() lives in config.php (shared with profile.php)

/**
 * Add a newly uploaded PDF + its ordered page images as a brand-new
 * attachment entry, each with its own flip-through viewer on the site.
 * Never touches any existing attachment — this is always additive.
 *
 * $orderedImageTmpPaths is an ordered array of temp file paths (page 1
 * first) as returned by ce_validate_page_image_files().
 */
function ce_add_attachment($title, $tmpPath, $originalName, $size, array $orderedImageTmpPaths) {
    if (!is_dir(ATTACHMENTS_DIR)) mkdir(ATTACHMENTS_DIR, 0755, true);
    if (!is_dir(ATTACHMENTS_IMAGES_DIR)) mkdir(ATTACHMENTS_IMAGES_DIR, 0755, true);

    $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $dest = ATTACHMENTS_DIR . '/' . $id . '.pdf';

    if (!move_uploaded_file($tmpPath, $dest)) {
        return false;
    }

    $imgDir = ATTACHMENTS_IMAGES_DIR . '/' . $id;
    mkdir($imgDir, 0755, true);

    $pageCount = 0;
    foreach ($orderedImageTmpPaths as $imgTmp) {
        $pageCount++;
        $pageName = sprintf('page-%02d.jpg', $pageCount);
        if (!move_uploaded_file($imgTmp, $imgDir . '/' . $pageName)) {
            // roll back what we've done so far rather than leave a half-built entry
            @unlink($dest);
            ce_delete_dir_recursive_local($imgDir);
            return false;
        }
    }

    $items = ce_load_attachments_raw();
    $items[] = [
        'id'           => $id,
        'title'        => $title !== '' ? $title : pathinfo($originalName, PATHINFO_FILENAME),
        'originalName' => $originalName,
        'url'          => 'assets/docs/attachments/' . $id . '.pdf',
        'imagesUrl'    => 'assets/images/attachments/' . $id . '/',
        'pageCount'    => $pageCount,
        'size'         => (int)$size,
        'uploadedAt'   => date('c'),
    ];
    ce_save_attachments_raw($items);

    return $id;
}

/**
 * Remove an attachment by id. The PDF and its page images are moved to
 * a backup folder rather than deleted outright, so they can be restored
 * if needed.
 */
function ce_delete_attachment($id) {
    $items = ce_load_attachments_raw();
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

    ce_save_attachments_raw($keep);

    if (!is_dir(BACKUP_DELETED_DIR)) mkdir(BACKUP_DELETED_DIR, 0755, true);

    // legacy entries (seeded from the old single-PDF setup) may point
    // outside ATTACHMENTS_DIR — only move files we actually manage.
    $srcPath = SITE_ROOT . '/' . ltrim($removed['url'], '/');
    if (file_exists($srcPath)) {
        $backupName = $id . '-' . basename($srcPath);
        @rename($srcPath, BACKUP_DELETED_DIR . '/' . $backupName);
    }

    $imgDir = ATTACHMENTS_IMAGES_DIR . '/' . $id;
    if (is_dir($imgDir)) {
        $imgBackupDir = BACKUP_DELETED_DIR . '/' . $id . '-images';
        @rename($imgDir, $imgBackupDir);
    }

    return true;
}

function ce_delete_dir_recursive_local($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        is_dir($path) ? ce_delete_dir_recursive_local($path) : @unlink($path);
    }
    @rmdir($dir);
}