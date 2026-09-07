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

// Parse the file exactly as ce_env() does, so what we report is what it
// sees — no second, subtly different reader to disagree with.
$parsed = [];
if ($readable) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $eq = strpos($line, '=');
        if ($eq === false) continue;
        $parsed[trim(substr($line, 0, $eq))] = trim(substr($line, $eq + 1));
    }
}

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
      <tr><th>Health check</th><td><?= $probe['ok'] ? 'Answered correctly' : htmlspecialchars($probe['problem']) ?></td></tr>
    </table>
    <?php if ($base === 'http://localhost:5050'): ?>
      <p class="fine-print">This is the built-in fallback, not something you configured. It means <code>GALLERY_API_BASE</code> was not found above.</p>
    <?php endif; ?>
  </section>

  <p><a class="btn btn-ghost" href="projects.php">Back to Projects</a></p>
</main>
</body>
</html>
