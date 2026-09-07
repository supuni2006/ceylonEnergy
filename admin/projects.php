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
    // Photos are stored in Cloudinary now, so adding or removing one
    // needs the backend running. The list below still renders without
    // it, which would otherwise make this page look perfectly fine
    // right up until the first upload fails.
    $apiHealth = ce_api_health();
    if ($apiHealth === null):
  ?>
    <div class="notice notice-error">
      <strong>The gallery backend is not responding.</strong>
      You can still browse below, but adding or removing photos will not work
      until it is running. Start it from the project folder with
      <code>npm start</code>, then reload this page.
      <span class="fine-print">(Looking for it at <?= htmlspecialchars(ce_api_base()) ?> — set <code>GALLERY_API_BASE</code> in <code>.env</code> to change that.)</span>
    </div>
  <?php elseif (($apiHealth['mongo'] ?? '') !== 'connected'): ?>
    <div class="notice notice-error">
      <strong>The backend is running but cannot reach MongoDB Atlas.</strong>
      Check your <code>MONGODB_URI</code> in <code>.env</code>, and that this
      server's IP is allowed under Atlas &rarr; Network Access.
      Run <code>npm run check-db</code> for a clearer message.
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
  </section>

  <?php if (!$locations): ?>
    <p class="muted">No locations yet — add one above to get started.</p>
  <?php endif; ?>

  <?php foreach ($locations as $loc): ?>
  <section class="admin-card">
    <div class="admin-card-head">
      <h2><?= htmlspecialchars($loc['name']) ?></h2>
      <form method="post" action="project-actions.php" onsubmit="return confirm('Remove this whole location and all its projects from the live site? Photo files stay on disk.');">
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
          <form method="post" action="project-actions.php" onsubmit="return confirm('Remove this project and its photos from the live site?');">
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
              <img src="<?= htmlspecialchars(ce_photo_thumb($photo)) ?>" loading="lazy" alt=""
                   onerror="this.onerror=null;this.src='../assets/images/dummy.png';">
              <form method="post" action="project-actions.php" onsubmit="return confirm('Remove this photo? It is deleted from Cloudinary and cannot be undone.');">
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
          <label class="file-field">Add photo (JPG or PNG) <input type="file" name="photo" accept="image/jpeg,image/png" required></label>
          <button class="btn btn-ghost" type="submit">Upload Photo</button>
        </form>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>

</main>
<?php ce_admin_foot(); ?>
