<?php
/**
 * Ceylon Energy Services — Admin: "why can't you find my .env?"
 *
 * The panel falling back to http://localhost:5050 means one thing:
 * ce_env('GALLERY_API_BASE') came back empty, so the default in
 * ce_api_base() was used. On a live server that is almost never a code
 * problem — the .env file is one folder off, or File Manager quietly
 * saved it as ".env.txt", or the web user cannot read it.
 *
 * Guessing which is slow. This page just prints the answer: the exact
 * path PHP looks at, whether that file is there and readable, and every
 * key it managed to parse out of it. Secrets are masked, but it still
 * reveals server paths, so it sits behind the admin login.
 */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/api.php';
require_once __DIR__ . '/inc/nav.php';

ce_require_login();

$envPath = SITE_ROOT . '/.env';
$exists  = file_exists($envPath);
$readable = $exists && is_readable($envPath);

/** Show enough of a secret to compare it against another copy, no more. */
function ce_mask($value) {
    $len = strlen($value);
    if ($len === 0) return '(empty)';
    if ($len <= 8) return str_repeat('•', $len) . "  ($len chars)";
    return substr($value, 0, 4) . str_repeat('•', min($len - 8, 20)) . substr($value, -4) . "  ($len chars)";
}

// The panel's own reader, not a copy of it. A second parser that differs
// in any small way is worse than none: this page would then swear the
// file is fine while the panel keeps failing on the very same file.
$parsed = ce_env_all();

// Anything in the site root whose name starts with a dot, plus the
// near-misses File Manager creates. Seeing ".env.txt" here explains the
// whole problem in one glance.
$neighbours = [];
foreach ((array)@scandir(SITE_ROOT) as $name) {
    if ($name === '.' || $name === '..') continue;
    if ($name[0] === '.' || stripos($name, 'env') !== false) $neighbours[] = $name;
}

$base  = ce_api_base();
$probe = ce_api_probe();

ce_admin_head('Setup check');
ce_admin_header('projects', 'Setup Check');
?>
<main class="admin-main">

  <div class="notice <?= $readable && !empty($parsed['GALLERY_API_BASE']) ? 'notice-ok' : 'notice-error' ?>">
    <?php if (!$exists): ?>
      <strong>There is no .env file where the panel looks for it.</strong>
      Create it at the exact path in the table below — that path is not a
      suggestion, it is the only place this page reads.
    <?php elseif (!$readable): ?>
      <strong>The .env file exists but PHP cannot read it.</strong>
      Fix its permissions in File Manager: right-click the file &rarr;
      <em>Change Permissions</em> &rarr; set it to <code>644</code>.
    <?php elseif (empty($parsed['GALLERY_API_BASE'])): ?>
      <strong>The .env file is being read, but it has no GALLERY_API_BASE line.</strong>
      Without it the panel falls back to <code>http://localhost:5050</code>,
      which is what the red banner is telling you.
    <?php else: ?>
      <strong>The .env file is being read correctly.</strong>
      If the gallery banner still complains, the problem is now the
      backend itself, not this file — see the probe result at the bottom.
    <?php endif; ?>
  </div>

  <section class="admin-card">
    <h2>The file</h2>
    <table class="admin-table">
      <tr><th>Path the panel reads</th><td><code><?= htmlspecialchars($envPath) ?></code></td></tr>
      <tr><th>Exists?</th><td><?= $exists ? 'Yes' : 'No' ?></td></tr>
      <tr><th>Readable by PHP?</th><td><?= $readable ? 'Yes' : ($exists ? 'No — check permissions (644)' : '—') ?></td></tr>
      <?php if ($exists): ?>
        <tr><th>Size</th><td><?= number_format(filesize($envPath)) ?> bytes</td></tr>
        <tr><th>Last changed</th><td><?= date('Y-m-d H:i:s', filemtime($envPath)) ?></td></tr>
      <?php endif; ?>
    </table>
  </section>

  <section class="admin-card">
    <h2>What it parsed</h2>
    <?php if (!$parsed): ?>
      <p>Nothing — no <code>NAME=value</code> lines were found.</p>
    <?php else: ?>
      <table class="admin-table">
        <tr><th>Key</th><th>Value</th></tr>
        <?php foreach ($parsed as $key => $value): ?>
          <tr>
            <td><code><?= htmlspecialchars($key) ?></code></td>
            <td><?php
              // GALLERY_API_BASE is a public address, and seeing it in
              // full is the entire point of this page.
              echo htmlspecialchars($key === 'GALLERY_API_BASE' ? ($value === '' ? '(empty)' : $value) : ce_mask($value));
            ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <p class="fine-print">Only <code>GALLERY_API_BASE</code> and <code>ADMIN_API_TOKEN</code> are ever used by this panel. Anything else here is ignored.</p>
    <?php endif; ?>
  </section>

  <section class="admin-card">
    <h2>Files in the site root that look related</h2>
    <?php if (!$neighbours): ?>
      <p>None. If you thought you had created a <code>.env</code> here, it went somewhere else.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($neighbours as $name): ?>
          <li><code><?= htmlspecialchars($name) ?></code><?php
            if (strcasecmp($name, '.env') !== 0 && stripos($name, 'env') !== false) {
                echo ' &larr; not read. The file must be named exactly <code>.env</code>';
            }
          ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="admin-card">
    <h2>Reaching the backend</h2>
    <table class="admin-table">
      <tr><th>Address being used</th><td><code><?= htmlspecialchars($base) ?></code></td></tr>
      <tr><th>Asked for</th><td><code><?= htmlspecialchars($base) ?>/api/health</code></td></tr>
      <tr><th>Answered with</th><td><?= ($probe['status'] ?? 0) ? 'HTTP ' . (int)$probe['status'] : 'nothing (no reply)' ?><?= !empty($probe['server']) ? ' from &ldquo;' . htmlspecialchars($probe['server']) . '&rdquo;' : '' ?></td></tr>
      <?php if (!empty($probe['body'])): ?>
        <tr><th>First bytes of the reply</th><td><code><?= htmlspecialchars(substr(trim(preg_replace('/\s+/', ' ', strip_tags($probe['body']))), 0, 160)) ?></code></td></tr>
      <?php endif; ?>
      <tr><th>Verdict</th><td><?= $probe['ok'] ? 'Answered correctly — this is the gallery API.' : htmlspecialchars($probe['problem']) ?></td></tr>
    </table>
    <?php if ($base === 'http://localhost:5050'): ?>
      <p class="fine-print">This is the built-in fallback, not something you configured. It means <code>GALLERY_API_BASE</code> was not found above.</p>
    <?php endif; ?>
  </section>

  <?php if (ce_api_is_same_site()): ?>
  <section class="admin-card">
    <h2>Where the backend is published on this server</h2>
    <p class="muted">
      <code>GALLERY_API_BASE</code> points at a folder of this same website, which is how
      cPanel's <em>Setup Node.js App</em> publishes a Node app. That means the answer is on
      this disk rather than a guess: cPanel connects the folder to the app by writing a
      <code>PassengerAppRoot</code> line into an <code>.htaccess</code> inside it. No line,
      no connection — and the web server answers 404 by itself.
    </p>
    <?php
      $folder   = ce_api_mount_folder();
      $dir      = SITE_ROOT . '/' . $folder;
      $htaccess = $dir . '/.htaccess';
      $appRoot  = ce_passenger_app_root($htaccess);
      $mounts   = ce_passenger_mounts();
    ?>
    <table class="admin-table">
      <tr><th>Folder in the address</th><td><code>/<?= htmlspecialchars($folder) ?></code></td></tr>
      <tr><th>That folder on disk</th><td><code><?= htmlspecialchars($dir) ?></code> — <?= is_dir($dir) ? 'exists' : 'does not exist' ?></td></tr>
      <tr><th>Its .htaccess</th><td><?= is_readable($htaccess) ? 'present' : 'missing' ?></td></tr>
      <tr><th>Handed to a Node app?</th><td><?php
        if ($appRoot === null) {
            echo 'No — there is no <code>PassengerAppRoot</code> line, so this address is not connected to any app.';
        } else {
            echo 'Yes' . ($appRoot !== '' ? ' — app root <code>' . htmlspecialchars($appRoot) . '</code>' : '');
        }
      ?></td></tr>
      <tr><th>Node apps published anywhere on this site</th><td><?php
        echo $mounts ? '<code>' . implode('</code>, <code>', array_map('htmlspecialchars', $mounts)) . '</code>' : 'none found';
      ?></td></tr>
    </table>
    <?php if (in_array('/', $mounts, true)): ?>
      <p class="fine-print"><strong>Warning:</strong> the whole website root is handed to a Node app.
      That takes the homepage and this admin panel down with it — see &ldquo;Recovering from a
      site-wide 503&rdquo; in <code>DEPLOY-CPANEL.md</code>.</p>
    <?php endif; ?>
    <?php $hint = ce_api_mount_hint(); if (!$probe['ok'] && $hint !== ''): ?>
      <p><strong>What to do:</strong><?= htmlspecialchars($hint) ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <p><a class="btn btn-ghost" href="projects.php">Back to Projects</a></p>
</main>
</body>
</html>
