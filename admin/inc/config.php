<?php
/**
 * Ceylon Energy Services — Admin: shared configuration
 */

// Buffer all output from the very start. Without this, any stray
// warning/notice printed before a header('Location: ...') call (e.g. a
// PHP upload-size warning, a deprecation notice) silently breaks that
// redirect — the browser just stays on the current URL showing whatever
// HTML happened to render, which looks exactly like "nothing happened."
// Buffering means header() always still works no matter what got
// echoed earlier; the buffer flushes automatically at the end.
if (ob_get_level() === 0) {
    ob_start();
}

// ---- paths -----------------------------------------------------------
// NOTE: dirname() does not resolve '..' segments — __DIR__.'/..' would
// literally be '.../admin/inc/..', and dirname() of that string just
// strips the trailing '..', landing back on 'admin/inc'. Use dirname()
// directly on __DIR__ (which has no '..' in it) instead.
define('ADMIN_ROOT', dirname(__DIR__));       // .../admin
define('SITE_ROOT',  dirname(ADMIN_ROOT));    // .../  (site root)

define('DATA_DIR',        ADMIN_ROOT . '/data');
define('AUTH_FILE',       DATA_DIR . '/auth.php');

define('DOCS_DIR',          SITE_ROOT . '/assets/docs');
define('ATTACHMENTS_DIR',   DOCS_DIR . '/attachments');           // uploaded PDF files live here
define('ATTACHMENTS_JSON',  DOCS_DIR . '/attachments.json');      // public list read by assets/js/main.js
define('ATTACHMENTS_IMAGES_DIR', SITE_ROOT . '/assets/images/attachments'); // per-attachment page images, one subfolder per id

define('BACKUP_DELETED_DIR', SITE_ROOT . '/storage/backups/deleted-attachments');

// ---- project gallery (locations -> projects -> photos) -----------------
// Powers the "Our Projects" section on the live site (assets/js/main.js
// fetches GALLERY_JSON). Separate from the PDF attachment system above.
define('GALLERY_JSON',        DOCS_DIR . '/gallery.json');                          // public list read by assets/js/main.js
define('GALLERY_IMAGES_DIR',  SITE_ROOT . '/assets/images/completed-projects');     // one subfolder per location id, then per project id
define('BACKUP_DELETED_GALLERY_DIR', SITE_ROOT . '/storage/backups/deleted-gallery');

// ---- company profile (single PDF + page images) -------------------------
// This is the system the live site actually reads (assets/js/main.js uses
// PROFILE_META_JSON + numbered JPGs in PROFILE_IMAGES_DIR). It is separate
// from the multi-attachment system above.
define('PROFILE_PDF_PATH',    DOCS_DIR . '/Ceylon-Energy-Company-Profile.pdf');
define('PROFILE_META_JSON',   DOCS_DIR . '/profile-meta.json');
define('PROFILE_IMAGES_DIR',  SITE_ROOT . '/assets/images/company-profile');
define('PROFILE_BACKUPS_DIR', ADMIN_ROOT . '/storage/backups');
define('PROFILE_MAX_BACKUPS_KEPT', 5);

// ---- limits ------------------------------------------------------------
define('MAX_PDF_BYTES',        35 * 1024 * 1024); // 35 MB — leaves headroom under the 40M/45M server limits for page images in the same request
define('MAX_IMAGE_BYTES',      10 * 1024 * 1024); // 10 MB per page image
define('MAX_ATTACHMENT_PAGES', 200);               // sanity cap on pages per attachment
define('MAX_GALLERY_PHOTO_BYTES',       10 * 1024 * 1024); // 10 MB per gallery photo
define('MAX_GALLERY_PHOTOS_PER_UPLOAD', 60);                // sanity cap per upload batch
define('MAX_LOGIN_ATTEMPTS',    6);
define('LOGIN_LOCKOUT_SECONDS', 300);

// ---- session -----------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ceylon_admin_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => dirname($_SERVER['SCRIPT_NAME']),
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function ce_csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function ce_csrf_check($token) {
    return isset($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

function ce_flash_set($msg, $type = 'ok') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function ce_flash_take() {
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function ce_human_bytes($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    $units = ['KB', 'MB', 'GB'];
    $val = $bytes;
    foreach ($units as $u) {
        $val /= 1024;
        if ($val < 1024) return round($val, 1) . ' ' . $u;
    }
    return round($val, 1) . ' TB';
}

/** Parse a php.ini shorthand size ("8M", "512K", "1G") into bytes. */
function ce_ini_bytes($val) {
    $val = trim((string)$val);
    if ($val === '') return 0;
    $last = strtolower($val[strlen($val) - 1]);
    $num = (int)$val;
    switch ($last) {
        case 'g': $num *= 1024;
        case 'm': $num *= 1024;
        case 'k': $num *= 1024;
    }
    return $num;
}

/**
 * Detect PHP's silent "request body was too large" case: when a
 * multipart upload exceeds post_max_size, PHP empties $_POST and
 * $_FILES entirely before the script ever runs — there's no catchable
 * upload error, it just looks like a blank form submission. That blank
 * submission is what trips the CSRF check further down and produces a
 * misleading "session expired" message, when the real cause is that the
 * PDF + page images together were bigger than the server currently
 * allows. Comparing Content-Length (which the browser always sends) to
 * $_POST being unexpectedly empty lets us tell the two situations apart
 * and report the real reason.
 */
function ce_post_too_large() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return false;
    if (!empty($_POST) || !empty($_FILES)) return false;
    $len = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    return $len > 0;
}

/** Human-readable message for ce_post_too_large(), naming the current server limit. */
function ce_post_too_large_message() {
    $limit = ce_ini_bytes(ini_get('post_max_size'));
    $limitTxt = $limit > 0 ? ce_human_bytes($limit) : 'the server\'s current';
    return 'That upload (PDF + page images together) is bigger than the server currently allows ('
        . $limitTxt . ' per request). Ask your host to raise post_max_size and upload_max_filesize, '
        . 'or upload a smaller PDF / fewer or smaller page images, then try again.';
}