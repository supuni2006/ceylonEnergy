<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/awards.php';
require_once __DIR__ . '/inc/nav.php';

ce_require_login();

$awards = ce_load_awards();
$flash = ce_flash_take();
$csrf = ce_csrf_token();

ce_admin_head('Awards');
ce_admin_header('awards', 'Awards & Recognitions');
?>
<main class="admin-main">

  <?php if ($flash): ?>
    <div class="notice notice-<?= $flash['type'] === 'error' ? 'error' : 'ok' ?>" id="admin-flash" tabindex="-1"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <section class="admin-card">
    <h2>Add an award</h2>
    <p class="muted">Upload a photo of the award, certificate, or trophy — it appears in the "Awards" section on the live site, newest first.</p>

    <form method="post" action="award-actions.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="add_award">

      <label>Title <input type="text" name="title" maxlength="120" placeholder="e.g. Best Solar Installer" required></label>
      <label>Year (optional) <input type="text" name="year" maxlength="4" placeholder="2025" inputmode="numeric" pattern="[0-9]{4}"></label>

      <label class="file-field">
        <span>Award image (JPG, PNG, or WEBP)</span>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
      </label>

      <button class="btn" type="submit">Add Award</button>
    </form>
  </section>

  <section class="admin-card">
    <h2>Current awards (<?= count($awards) ?>)</h2>

    <?php if (!$awards): ?>
      <p class="muted">No awards added yet — add one above and it'll show up here and on the live site.</p>
    <?php else: ?>
      <div class="thumb-grid award-grid">
        <?php foreach ($awards as $a): ?>
          <div class="thumb-item award-item">
            <img src="../<?= htmlspecialchars($a['imageUrl']) ?>" loading="lazy" alt="<?= htmlspecialchars($a['title']) ?>">
            <div class="award-caption">
              <strong><?= htmlspecialchars($a['title']) ?></strong>
              <?php if (!empty($a['year'])): ?><span><?= htmlspecialchars($a['year']) ?></span><?php endif; ?>
            </div>
            <form method="post" action="award-actions.php" onsubmit="return confirm('Remove this award from the live site?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="action" value="delete_award">
              <input type="hidden" name="id" value="<?= htmlspecialchars($a['id']) ?>">
              <button class="thumb-remove" type="submit" aria-label="Remove award">✕</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</main>
<?php ce_admin_foot(); ?>
