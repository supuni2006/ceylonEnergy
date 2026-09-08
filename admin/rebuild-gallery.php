<?php
/**
 * Ceylon Energy Services — Admin: rescan the gallery folders
 *
 * The photo folders are the real gallery; assets/data/projects.json only
 * describes them. So the folders can always be trusted, and rebuilding
 * that description from them is the fix for the two situations the
 * upload form cannot cover:
 *
 *   - photos copied straight into a folder with cPanel's File Manager
 *     or over FTP, which no form ever saw
 *   - a projects.json left behind by an older version of the site, or
 *     one that has drifted out of step with the folders
 *
 * It is safe to run whenever. Nothing is deleted: names already in the
 * file are kept, and a location or project with no folder yet stays
 * listed so a newly added one does not vanish.
 */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/projects.php';
require_once __DIR__ . '/inc/nav.php';

ce_require_login();

$csrf   = ce_csrf_token();
$result = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ce_csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Your session expired — please try again.';
    } else {
        // Resizing is the slow part, so a first run over a few hundred
        // photos can take a while. Ask for as long as the host allows
        // rather than dying half way with a blank page.
        @set_time_limit(300);
        $result = ce_rescan_gallery();
        if (isset($result['error'])) {
            $error = $result['error'];
            $result = null;
        }
    }
}

$locations = ce_load_projects();
$photoCount = 0;
foreach ($locations as $loc) {
    foreach ($loc['projects'] as $proj) $photoCount += count($proj['photos']);
}

ce_admin_head('Rescan gallery');
ce_admin_header('projects', 'Rescan Gallery Folders');
?>
<main class="admin-main">

  <?php if ($error): ?>
    <div class="notice notice-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($result): ?>
    <div class="notice notice-ok">
      <strong>Done — the gallery now matches the folders.</strong>
      Found <?= (int)$result['locations'] ?> location<?= $result['locations'] === 1 ? '' : 's' ?>,
      <?= (int)$result['projects'] ?> project<?= $result['projects'] === 1 ? '' : 's' ?>
      and <?= (int)$result['photos'] ?> photo<?= $result['photos'] === 1 ? '' : 's' ?>,
      <?= (int)$result['resized'] ?> of which have small web-sized copies.
      <br>Open the live site to see the result.
    </div>
    <?php foreach ($result['notes'] as $note): ?>
      <div class="notice notice-error"><?= htmlspecialchars($note) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <section class="admin-card">
    <h2>What this does</h2>
    <p class="muted">
      It looks through <code>assets/images/completed-projects/</code>, one folder per
      location and one inside that per project, and writes what it finds into
      <code>assets/data/projects.json</code> — the file the website reads. It also makes
      the small copies of each photo that the gallery loads, so visitors on a phone are
      not downloading full-size originals.
    </p>
    <p class="muted">
      Use it after copying photos in with File Manager or FTP. Nothing is deleted, and
      names you have typed here are kept, so running it twice does no harm.
    </p>

    <table class="admin-table">
      <tr><th>Photo folders</th><td><code>assets/images/completed-projects/&lt;location&gt;/&lt;project&gt;/</code></td></tr>
      <tr><th>Gallery file</th><td><code>assets/data/projects.json</code></td></tr>
      <tr><th>Listed right now</th><td><?= count($locations) ?> locations, <?= $photoCount ?> photos</td></tr>
    </table>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <button class="btn" type="submit">Rescan folders now</button>
    </form>
    <p class="fine-print">
      The first run has to shrink every photo, so on a big gallery give it a minute.
      Later runs skip anything already done and finish almost instantly.
    </p>
  </section>

  <section class="admin-card">
    <h2>Adding photos by File Manager</h2>
    <p class="muted">Useful when you have a lot to add at once — the upload form takes one at a time.</p>
    <ol class="muted">
      <li>In cPanel, open <strong>File Manager</strong> and go to
          <code>public_html/assets/images/completed-projects/</code>.</li>
      <li>Open the location folder you want, then the project folder inside it. To start a
          new one, create folders named like the existing ones —
          <code>loc-galle</code> and inside it <code>proj-new-site</code>. Lower case,
          words joined by dashes, no spaces.</li>
      <li>Upload your JPG or PNG photos into the project folder.</li>
      <li>Come back here and press <strong>Rescan folders now</strong>.</li>
    </ol>
    <p class="fine-print">
      A folder created this way is named from its folder name — <code>loc-galle</code>
      becomes "Galle". Rename it properly afterwards by adding the location on the
      Projects page with the spelling you want.
    </p>
  </section>

  <p><a class="btn btn-ghost" href="projects.php">Back to Projects</a></p>
</main>
<?php ce_admin_foot(); ?>
