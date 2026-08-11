<?php
/**
 * Ceylon Energy Services — Admin: Company Profile (single PDF + page images)
 *
 * This backs the "Company Profile Attachment" screen in admin/index.php.
 * It is intentionally separate from inc/attachments.php (a different,
 * multi-file system for the additional documents shown in the "More
 * documents" grid alongside the main profile on the public site).
 */

require_once __DIR__ . '/config.php';

/** Info about the currently published profile PDF, or null if none exists. */
function ce_profile_pdf_info() {
    if (!file_exists(PROFILE_PDF_PATH)) return null;
    return [
        'size'     => filesize(PROFILE_PDF_PATH),
        'modified' => filemtime(PROFILE_PDF_PATH),
    ];
}

/** Load the profile-meta.json contents (pageCount, updatedAt), or null. */
function ce_load_profile_meta() {
    if (!file_exists(PROFILE_META_JSON)) return null;
    $json = json_decode(file_get_contents(PROFILE_META_JSON), true);
    return is_array($json) ? $json : null;
}

function ce_save_profile_meta($pageCount) {
    if (!is_dir(DOCS_DIR)) mkdir(DOCS_DIR, 0755, true);
    $data = [
        'pageCount' => (int)$pageCount,
        'updatedAt' => date('c'),
    ];
    $tmp = PROFILE_META_JSON . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($tmp, PROFILE_META_JSON);
}

/** Number of pages currently published (falls back to counting JPGs on disk). */
function ce_current_page_count() {
    $meta = ce_load_profile_meta();
    if ($meta && isset($meta['pageCount'])) {
        return (int)$meta['pageCount'];
    }
    if (!is_dir(PROFILE_IMAGES_DIR)) return 0;
    $files = glob(PROFILE_IMAGES_DIR . '/page-*.jpg');
    return $files ? count($files) : 0;
}

/** List backup folder names under storage/backups, newest first. */
function ce_list_backups() {
    if (!is_dir(PROFILE_BACKUPS_DIR)) return [];
    $entries = scandir(PROFILE_BACKUPS_DIR);
    $backups = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.htaccess') continue;
        if (is_dir(PROFILE_BACKUPS_DIR . '/' . $entry)) {
            $backups[] = $entry;
        }
    }
    rsort($backups); // folder names are timestamp-prefixed, so this sorts newest first
    return $backups;
}

/**
 * Back up the currently published PDF + page images + meta into a
 * timestamped folder under storage/backups, trimming old backups
 * beyond PROFILE_MAX_BACKUPS_KEPT.
 */
function ce_backup_current_profile() {
    $hasPdf = file_exists(PROFILE_PDF_PATH);
    $hasImages = is_dir(PROFILE_IMAGES_DIR) && glob(PROFILE_IMAGES_DIR . '/page-*.jpg');
    if (!$hasPdf && !$hasImages) return; // nothing to back up yet

    $stamp = date('Ymd-His');
    $dest = PROFILE_BACKUPS_DIR . '/' . $stamp;
    if (!is_dir($dest)) mkdir($dest, 0755, true);

    if ($hasPdf) {
        copy(PROFILE_PDF_PATH, $dest . '/' . basename(PROFILE_PDF_PATH));
    }
    if (file_exists(PROFILE_META_JSON)) {
        copy(PROFILE_META_JSON, $dest . '/profile-meta.json');
    }
    if ($hasImages) {
        $imgDest = $dest . '/company-profile';
        mkdir($imgDest, 0755, true);
        foreach (glob(PROFILE_IMAGES_DIR . '/page-*.jpg') as $img) {
            copy($img, $imgDest . '/' . basename($img));
        }
    }

    // trim old backups
    $all = ce_list_backups();
    foreach (array_slice($all, PROFILE_MAX_BACKUPS_KEPT) as $old) {
        ce_delete_dir_recursive(PROFILE_BACKUPS_DIR . '/' . $old);
    }
}

function ce_delete_dir_recursive($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        is_dir($path) ? ce_delete_dir_recursive($path) : unlink($path);
    }
    rmdir($dir);
}

/**
 * Publish a new profile: replaces the PDF and the ordered set of page
 * images, updates profile-meta.json, and backs up the previous version
 * first. $orderedImageTmpPaths is a 1-indexed-friendly plain array of
 * temp file paths, in the order they should appear as pages.
 */
function ce_publish_profile($pdfTmpPath, array $orderedImageTmpPaths) {
    if (!is_dir(DOCS_DIR)) mkdir(DOCS_DIR, 0755, true);
    if (!is_dir(PROFILE_IMAGES_DIR)) mkdir(PROFILE_IMAGES_DIR, 0755, true);
    if (!is_dir(PROFILE_BACKUPS_DIR)) mkdir(PROFILE_BACKUPS_DIR, 0755, true);

    ce_backup_current_profile();

    // move the new PDF into place
    if (!move_uploaded_file($pdfTmpPath, PROFILE_PDF_PATH)) {
        return false;
    }

    // clear out old page images, then write the new ones in order
    foreach (glob(PROFILE_IMAGES_DIR . '/page-*.jpg') as $old) {
        unlink($old);
    }
    $i = 1;
    foreach ($orderedImageTmpPaths as $tmpPath) {
        $name = sprintf('page-%02d.jpg', $i);
        move_uploaded_file($tmpPath, PROFILE_IMAGES_DIR . '/' . $name);
        $i++;
    }

    ce_save_profile_meta($i - 1);

    return true;
}