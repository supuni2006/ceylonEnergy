<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/projects.php';
require_once __DIR__ . '/inc/nav.php';

ce_require_login();

$locations = ce_load_projects();
$flash = ce_flash_take();
$csrf = ce_csrf_token();

ce_admin_head('Projects');
ce_admin_header('projects', 'Project Gallery');
?>
<main class="admin-main">

  <?php if ($flash): ?>
    <div class="notice notice-<?= $flash['type'] === 'error' ? 'error' : 'ok' ?>" id="admin-flash" tabindex="-1"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <?php
    // The one thing that actually stops this page working on shared
    // hosting is a folder PHP is not allowed to write to. It fails
    // silently at the worst moment — half way through an upload — so
    // it is worth saying up front rather than after someone has picked
    // a file and pressed the button.
    $jsonWritable   = file_exists(PROJECTS_JSON) ? is_writable(PROJECTS_JSON) : is_writable(dirname(PROJECTS_JSON));
    $imagesWritable = is_dir(PROJECTS_IMAGES_DIR) ? is_writable(PROJECTS_IMAGES_DIR) : is_writable(dirname(PROJECTS_IMAGES_DIR));
    if (!$jsonWritable || !$imagesWritable):
  ?>
    <div class="notice notice-error">
      <strong>Uploads will not work until these are writable.</strong>
      In cPanel's File Manager, right-click each one &rarr; <em>Change Permissions</em>:
      folders to <code>755</code>, files to <code>644</code>.
      <ul>
        <?php if (!$imagesWritable): ?>
          <li><code>assets/images/completed-projects</code> — where the photos are saved</li>
        <?php endif; ?>
        <?php if (!$jsonWritable): ?>
          <li><code>assets/data/projects.json</code> — the list the website reads</li>
        <?php endif; ?>
      </ul>
      <a href="check-env.php">Open the setup check</a> for the full list of what this site needs.
    </div>
  <?php elseif (!function_exists('imagecreatetruecolor')): ?>
    <div class="notice notice-error">
      <strong>PHP's image extension (GD) is switched off, so photos cannot be shrunk.</strong>
      Uploads still work, but the website will hand visitors the full-size originals,
      which is slow on a phone. In cPanel: <em>Select PHP Version</em> &rarr;
      <em>Extensions</em> &rarr; tick <code>gd</code>, then use
      <a href="rebuild-gallery.php">Rescan gallery folders</a> to make the small copies.
    </div>
  <?php endif; ?>

  <section class="admin-card">
    <h2>Add a location</h2>
    <p class="muted">Locations group projects on the live site (e.g. "Colombo", "Kaluthara") and show up as the first level of the gallery.</p>
    <form method="post" action="project-actions.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="add_location">
      <label>Location name <input type="text" name="name" maxlength="80" required></label>
      <button class="btn" type="submit">Add Location</button>
    </form>
    <p class="fine-print">
      Adding a lot of photos at once? Copy them into the folders with cPanel's File
      Manager, then <a href="rebuild-gallery.php">rescan the gallery folders</a> to pick
      them all up in one go.
    </p>
  </section>

  <?php if (!$locations): ?>
    <p class="muted">
      No locations yet — add one above to get started. If the photos are already on the
      server, <a href="rebuild-gallery.php">rescan the gallery folders</a> instead and
      they will be listed automatically.
    </p>
  <?php endif; ?>

  <?php foreach ($locations as $loc): ?>
  <section class="admin-card">
    <div class="admin-card-head">
      <h2><?= htmlspecialchars($loc['name']) ?></h2>
      <form method="post" action="project-actions.php" onsubmit="return confirm('Remove this whole location and all its projects from the website? Copies of the photos are kept in storage/backups/deleted-gallery.');">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="delete_location">
        <input type="hidden" name="location_id" value="<?= htmlspecialchars($loc['id']) ?>">
        <button class="btn btn-ghost btn-danger" type="submit">Remove location</button>
      </form>
    </div>

    <form method="post" action="project-actions.php" class="inline-form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="add_project">
      <input type="hidden" name="location_id" value="<?= htmlspecialchars($loc['id']) ?>">
      <label>New project name <input type="text" name="name" maxlength="80" required></label>
      <button class="btn btn-ghost" type="submit">Add Project</button>
    </form>

    <?php if (!$loc['projects']): ?>
      <p class="muted fine-print">No projects in this location yet.</p>
    <?php endif; ?>

    <?php foreach ($loc['projects'] as $proj): ?>
      <div class="project-block">
        <div class="admin-card-head">
          <h3><?= htmlspecialchars($proj['name']) ?> <span class="fine-print">(<?= count($proj['photos']) ?> photo<?= count($proj['photos']) === 1 ? '' : 's' ?>)</span></h3>
          <form method="post" action="project-actions.php" onsubmit="return confirm('Remove this project and its photos from the website? Copies are kept in storage/backups/deleted-gallery.');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="delete_project">
            <input type="hidden" name="location_id" value="<?= htmlspecialchars($loc['id']) ?>">
            <input type="hidden" name="project_id" value="<?= htmlspecialchars($proj['id']) ?>">
            <button class="btn btn-ghost btn-danger" type="submit">Remove project</button>
          </form>
        </div>

        <?php if ($proj['photos']): ?>
        <div class="thumb-grid">
          <?php foreach ($proj['photos'] as $photo): ?>
            <div class="thumb-item">
              <img src="<?= htmlspecialchars(ce_admin_asset_url(ce_photo_thumb($photo))) ?>" loading="lazy" alt=""
                   onerror="this.onerror=null;this.src='../assets/images/dummy.png';">
              <form method="post" action="project-actions.php" onsubmit="return confirm('Remove this photo from the website? A copy is kept in storage/backups/deleted-gallery.');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="delete_photo">
                <input type="hidden" name="location_id" value="<?= htmlspecialchars($loc['id']) ?>">
                <input type="hidden" name="project_id" value="<?= htmlspecialchars($proj['id']) ?>">
                <input type="hidden" name="photo" value="<?= htmlspecialchars(ce_photo_id($photo)) ?>">
                <button class="thumb-remove" type="submit" aria-label="Remove photo">✕</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="post" action="project-actions.php" enctype="multipart/form-data" class="inline-form">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="add_photo">
          <input type="hidden" name="location_id" value="<?= htmlspecialchars($loc['id']) ?>">
          <input type="hidden" name="project_id" value="<?= htmlspecialchars($proj['id']) ?>">
          <label class="file-field">Add photo (JPG, PNG or WebP) <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>
          <button class="btn btn-ghost" type="submit">Upload Photo</button>
        </form>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>

</main>
<?php ce_admin_foot(); ?>
