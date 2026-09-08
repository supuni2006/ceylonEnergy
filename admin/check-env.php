<?php
/**
 * Ceylon Energy Services — Admin: setup check
 *
 * This site is plain PHP and plain files, so there is very little that
 * can be misconfigured — but the little there is fails quietly, which
 * is the worst way to fail. An upload into a folder the web server may
 * not write to does not raise an error the browser can show; it just
 * does nothing. GD being switched off does not break anything visibly
 * either; the site simply starts serving 3MB originals to phones.
 *
 * So rather than leaving anyone to guess, this page states what the
 * site needs and whether this server provides it. It reveals server
 * paths, so it sits behind the admin login.
 */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/projects.php';
require_once __DIR__ . '/inc/nav.php';

ce_require_login();

/** Is this path writable — and if it does not exist yet, is its parent? */
function ce_check_writable($path) {
    if (file_exists($path)) {
        return ['ok' => is_writable($path), 'note' => is_writable($path) ? 'Writable' : 'Not writable'];
    }
    $parent = dirname($path);
    if (!is_dir($parent)) {
        return ['ok' => false, 'note' => 'Missing, and so is the folder it belongs in'];
    }
    return is_writable($parent)
        ? ['ok' => true,  'note' => 'Does not exist yet — will be created on first use']
        : ['ok' => false, 'note' => 'Does not exist, and its folder is not writable'];
}

$paths = [
    'Photo folders'        => PROJECTS_IMAGES_DIR,
    'Gallery list'         => PROJECTS_JSON,
    'Deleted-photo backup' => BACKUP_DELETED_GALLERY_DIR,
    'Awards images'        => AWARDS_IMAGES_DIR,
    'Awards list'          => AWARDS_JSON,
    'Company profile PDF'  => DOCS_DIR,
    'Attachments'          => ATTACHMENTS_DIR,
    'Login details'        => AUTH_FILE,
];

$extensions = [
    'gd'       => 'Shrinks uploaded photos to web sizes. Without it the site serves the full-size originals, which is slow on a phone.',
    'fileinfo' => 'Checks that an uploaded file really is an image. Uploads are refused without it.',
    'json'     => 'Reads and writes the gallery and awards files.',
    'mbstring' => 'Handles names with accented characters correctly.',
];

$uploadMax = ce_ini_bytes(ini_get('upload_max_filesize'));
$postMax   = ce_ini_bytes(ini_get('post_max_size'));

$locations = ce_load_projects();
$photoCount = 0;
$missingFiles = [];
foreach ($locations as $loc) {
    foreach ($loc['projects'] as $proj) {
        foreach ($proj['photos'] as $photo) {
            $photoCount++;
            $rel = is_array($photo) ? ($photo['large'] ?? $photo['file'] ?? '') : (string)$photo;
            if ($rel === '' || preg_match('#^(https?:)?//#i', $rel)) {
                $missingFiles[] = $rel === '' ? '(no file recorded)' : $rel;
            } elseif (!file_exists(SITE_ROOT . '/' . ltrim($rel, '/'))) {
                $missingFiles[] = $rel;
            }
        }
    }
}

$pathProblems = 0;
foreach ($paths as $path) {
    if (!ce_check_writable($path)['ok']) $pathProblems++;
}
$allGood = $pathProblems === 0 && extension_loaded('gd') && extension_loaded('fileinfo') && !$missingFiles;

ce_admin_head('Setup check');
ce_admin_header('projects', 'Setup Check');
?>
<main class="admin-main">

  <div class="notice <?= $allGood ? 'notice-ok' : 'notice-error' ?>">
    <?php if ($allGood): ?>
      <strong>Everything this site needs is in place.</strong>
      Uploads will save, photos will be shrunk for the web, and every photo the
      gallery lists is really on the server.
    <?php else: ?>
      <strong>Some things need attention — see the tables below.</strong>
      Anything marked with a cross is fixed in cPanel, not in the code.
    <?php endif; ?>
  </div>

  <section class="admin-card">
    <h2>Folders and files this site writes to</h2>
    <p class="muted">
      A folder the web server cannot write to is the usual reason an upload appears to
      do nothing at all. Fix it in File Manager: right-click &rarr;
      <em>Change Permissions</em> &rarr; folders <code>755</code>, files <code>644</code>.
    </p>
    <table class="admin-table">
      <tr><th>What</th><th>Where</th><th>Status</th></tr>
      <?php foreach ($paths as $label => $path):
        $check = ce_check_writable($path); ?>
        <tr>
          <td><?= htmlspecialchars($label) ?></td>
          <td><code><?= htmlspecialchars(ce_relative_path($path)) ?></code></td>
          <td><?= $check['ok'] ? '✓ ' : '✕ ' ?><?= htmlspecialchars($check['note']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <p class="fine-print">Full path to the site: <code><?= htmlspecialchars(SITE_ROOT) ?></code></p>
  </section>

  <section class="admin-card">
    <h2>PHP</h2>
    <table class="admin-table">
      <tr><th>Version</th><td><?= htmlspecialchars(PHP_VERSION) ?><?= version_compare(PHP_VERSION, '7.4', '<') ? ' — too old, ask your host for PHP 8' : '' ?></td></tr>
      <?php foreach ($extensions as $name => $why): ?>
        <tr>
          <td><code><?= htmlspecialchars($name) ?></code></td>
          <td>
            <?= extension_loaded($name) ? '✓ Enabled' : '✕ Not enabled' ?>
            <span class="fine-print"><?= htmlspecialchars($why) ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <p class="fine-print">
      To switch an extension on: cPanel &rarr; <em>Select PHP Version</em> &rarr;
      <em>Extensions</em> &rarr; tick it &rarr; the change applies straight away.
    </p>
  </section>

  <section class="admin-card">
    <h2>Upload limits</h2>
    <p class="muted">
      These come from <code>.user.ini</code> in the site folder. PHP caches that file for
      a few minutes, so a change to it is not always visible on the very next page load.
    </p>
    <table class="admin-table">
      <tr><th>Largest single file</th><td><?= htmlspecialchars(ce_human_bytes($uploadMax)) ?> <span class="fine-print">(upload_max_filesize)</span></td></tr>
      <tr><th>Largest whole form</th><td><?= htmlspecialchars(ce_human_bytes($postMax)) ?> <span class="fine-print">(post_max_size)</span></td></tr>
      <tr><th>Photo limit in this panel</th><td><?= htmlspecialchars(ce_human_bytes(MAX_IMAGE_BYTES)) ?> per photo</td></tr>
      <tr><th>Time allowed per request</th><td><?= htmlspecialchars(ini_get('max_execution_time')) ?> seconds</td></tr>
      <tr><th>Memory allowed</th><td><?= htmlspecialchars(ini_get('memory_limit')) ?> <span class="fine-print">— shrinking a very large photo needs this</span></td></tr>
    </table>
  </section>

  <section class="admin-card">
    <h2>The gallery</h2>
    <table class="admin-table">
      <tr><th>Locations</th><td><?= count($locations) ?></td></tr>
      <tr><th>Photos listed</th><td><?= $photoCount ?></td></tr>
      <tr><th>Photos actually on this server</th><td><?= $photoCount - count($missingFiles) ?></td></tr>
    </table>
    <?php if ($missingFiles): ?>
      <p>
        <?= count($missingFiles) ?> photo<?= count($missingFiles) === 1 ? ' is' : 's are' ?>
        listed in the gallery but not on this server, so
        <?= count($missingFiles) === 1 ? 'it' : 'they' ?> will show as broken.
        <a href="rebuild-gallery.php">Rescan the gallery folders</a> to rebuild the list
        from the photos that are really there.
      </p>
      <ul class="fine-print">
        <?php foreach (array_slice($missingFiles, 0, 10) as $rel): ?>
          <li><code><?= htmlspecialchars($rel) ?></code></li>
        <?php endforeach; ?>
        <?php if (count($missingFiles) > 10): ?>
          <li>…and <?= count($missingFiles) - 10 ?> more.</li>
        <?php endif; ?>
      </ul>
    <?php else: ?>
      <p class="muted">Every listed photo was found on disk.</p>
    <?php endif; ?>
  </section>

  <p>
    <a class="btn btn-ghost" href="projects.php">Back to Projects</a>
    <a class="btn btn-ghost" href="rebuild-gallery.php">Rescan gallery folders</a>
  </p>
</main>
<?php ce_admin_foot(); ?>
