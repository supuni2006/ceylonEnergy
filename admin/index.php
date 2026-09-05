<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/profile.php';
require_once __DIR__ . '/inc/attachments.php';
require_once __DIR__ . '/inc/gallery.php';

ce_require_login();

$pdfInfo = ce_profile_pdf_info();
$pageCount = ce_current_page_count();
$backups = ce_list_backups();
$attachments = ce_list_attachments();
$galleryLocations = ce_list_gallery();
$flash = ce_flash_take();
$csrf = ce_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Company Profile Attachment — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<header class="admin-header">
  <div>
    <p class="eyebrow">Ceylon Energy Services — Admin</p>
    <h1>Company Profile Attachment</h1>
  </div>
  <div class="admin-header-actions">
    <a class="btn btn-ghost" href="../index.html#company-profile" target="_blank" rel="noopener">View live section</a>
    <a class="btn btn-ghost" href="logout.php">Log out</a>
  </div>
</header>

<main class="admin-main">

  <?php if ($flash): ?>
    <div class="notice notice-<?= $flash['type'] === 'error' ? 'error' : 'ok' ?>" id="admin-flash" tabindex="-1"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <section class="admin-card">
    <h2>Current attachment</h2>
    <?php if ($pdfInfo): ?>
      <dl class="status-grid">
        <dt>PDF size</dt><dd><?= htmlspecialchars(ce_human_bytes($pdfInfo['size'])) ?></dd>
        <dt>Pages published</dt><dd><?= (int)$pageCount ?></dd>
        <dt>Last updated</dt><dd><?= htmlspecialchars(date('j M Y, g:i a', $pdfInfo['modified'])) ?></dd>
      </dl>
      <a class="link-inline" href="../assets/docs/Ceylon-Energy-Company-Profile.pdf" target="_blank" rel="noopener">Open current PDF &rarr;</a>
    <?php else: ?>
      <p class="muted">No company profile PDF has been uploaded yet.</p>
    <?php endif; ?>
  </section>

  <section class="admin-card">
    <h2>Replace attachment</h2>
    <p class="muted">Upload the new PDF — page images for the "flip through" viewer are generated automatically in your browser, no need to upload JPGs one by one. The previous version is backed up automatically.</p>

    <form method="post" action="upload.php" enctype="multipart/form-data" class="js-page-upload-form" id="profileForm">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

      <label class="file-field">
        <span>Company profile PDF</span>
        <input type="file" class="js-pdf-input" name="pdf" accept="application/pdf" required>
      </label>

      <div class="js-page-images-field">
        <label class="file-field js-manual-images-label">
          <span>Page images (JPG, one per page)</span>
          <input type="file" class="js-page-images-input" name="page_images[]" accept="image/jpeg" multiple required>
        </label>
        <p class="fine-print js-pdf-convert-status" hidden></p>
        <button type="button" class="link-inline link-button js-manual-toggle" hidden>Upload page images manually instead</button>
      </div>

      <div class="js-page-order-hint fine-print" hidden>Drag thumbnails to reorder — this is the order pages will appear in.</div>
      <div class="js-page-thumbs page-thumbs"></div>

      <button class="btn js-submit-btn" type="submit">Save &amp; Publish</button>
    </form>
  </section>

  <section class="admin-card">
    <h2>Add a new document</h2>
    <p class="muted">This adds a brand-new document alongside the existing one(s) below — nothing already published gets replaced or removed. Give it a title and a PDF; page images for its own flip-through viewer are generated automatically in your browser.</p>

    <form method="post" action="add-attachment.php" enctype="multipart/form-data" class="js-page-upload-form" id="addAttachmentForm">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

      <label class="file-field">
        <span>Title (optional — falls back to the filename)</span>
        <input type="text" name="title" maxlength="120" placeholder="e.g. ISO Certification, Product Brochure">
      </label>

      <label class="file-field">
        <span>Document PDF</span>
        <input type="file" class="js-pdf-input" name="pdf" accept="application/pdf" required>
      </label>

      <div class="js-page-images-field">
        <label class="file-field js-manual-images-label">
          <span>Page images (JPG, one per page)</span>
          <input type="file" class="js-page-images-input" name="page_images[]" accept="image/jpeg" multiple required>
        </label>
        <p class="fine-print js-pdf-convert-status" hidden></p>
        <button type="button" class="link-inline link-button js-manual-toggle" hidden>Upload page images manually instead</button>
      </div>

      <div class="js-page-order-hint fine-print" hidden>Drag thumbnails to reorder — this is the order pages will appear in.</div>
      <div class="js-page-thumbs page-thumbs"></div>

      <button class="btn js-submit-btn" type="submit">Add Document</button>
    </form>
  </section>

  <?php if ($attachments): ?>
  <section class="admin-card">
    <h2>Additional documents</h2>
    <p class="muted">These appear on the live site alongside the main company profile, each with its own viewer.</p>
    <ul class="attachment-list">
      <?php foreach ($attachments as $a): ?>
        <li class="attachment-item">
          <div>
            <strong><?= htmlspecialchars($a['title'] ?? 'Untitled') ?></strong>
            <div class="fine-print">
              <?= (int)($a['pageCount'] ?? 0) ?> page<?= (int)($a['pageCount'] ?? 0) === 1 ? '' : 's' ?>
              &middot; <?= htmlspecialchars(ce_human_bytes($a['size'] ?? 0)) ?>
              &middot; added <?= htmlspecialchars(date('j M Y', strtotime($a['uploadedAt'] ?? 'now'))) ?>
            </div>
          </div>
          <div class="attachment-item-actions">
            <a class="link-inline" href="../<?= htmlspecialchars($a['url'] ?? '#') ?>" target="_blank" rel="noopener">Open PDF</a>
            <form method="post" action="delete.php" onsubmit="return confirm('Remove this document from the live site? It will be moved to a backup folder, not permanently deleted.');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="id" value="<?= htmlspecialchars($a['id'] ?? '') ?>">
              <button type="submit" class="btn btn-ghost btn-danger">Remove</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="admin-card" id="gallery-admin">
    <h2>Project Gallery — Locations, Projects &amp; Photos</h2>
    <p class="muted">Powers the "Our Projects" section on the live site: pick a location, then a project, to see its photos. Add a location, then a project under it, then upload photos to that project — nothing already published is touched.</p>

    <div class="gallery-forms-grid">
      <form method="post" action="add-location.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label>
          <span>New location name</span>
          <input type="text" name="name" maxlength="80" placeholder="e.g. Kandy" required>
        </label>
        <button class="btn js-submit-btn" type="submit">Add Location</button>
      </form>

      <form method="post" action="add-project.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label>
          <span>Location</span>
          <select name="location_id" required>
            <option value="" disabled selected>Choose a location&hellip;</option>
            <?php foreach ($galleryLocations as $loc): ?>
              <option value="<?= htmlspecialchars($loc['id']) ?>"><?= htmlspecialchars($loc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          <span>New project name</span>
          <input type="text" name="name" maxlength="120" placeholder="e.g. Project 03" required>
        </label>
        <button class="btn js-submit-btn" type="submit">Add Project</button>
      </form>

      <form method="post" action="add-project-photos.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label>
          <span>Location</span>
          <select name="location_id" class="js-photo-location-select" required>
            <option value="" disabled selected>Choose a location&hellip;</option>
            <?php foreach ($galleryLocations as $loc): ?>
              <option value="<?= htmlspecialchars($loc['id']) ?>"><?= htmlspecialchars($loc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          <span>Project</span>
          <select name="project_id" class="js-photo-project-select" required>
            <option value="" disabled selected>Choose a location first&hellip;</option>
          </select>
        </label>
        <label class="file-field">
          <span>Photos (JPG or PNG, multiple allowed)</span>
          <input type="file" name="photos[]" accept="image/jpeg,image/png" multiple required>
        </label>
        <button class="btn js-submit-btn" type="submit">Upload Photos</button>
      </form>
    </div>

    <?php if ($galleryLocations): ?>
    <div class="gallery-tree">
      <?php foreach ($galleryLocations as $loc): $locProjects = $loc['projects'] ?? []; ?>
        <div class="gallery-location">
          <div class="gallery-location-head">
            <strong><?= htmlspecialchars($loc['name']) ?></strong>
            <span class="fine-print"><?= count($locProjects) ?> project<?= count($locProjects) === 1 ? '' : 's' ?></span>
            <form method="post" action="delete-location.php" onsubmit="return confirm('Remove this entire location, all its projects, and all their photos? Files are moved to a backup folder, not permanently deleted.');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="location_id" value="<?= htmlspecialchars($loc['id']) ?>">
              <button type="submit" class="btn btn-ghost btn-danger">Remove Location</button>
            </form>
          </div>

          <?php foreach ($locProjects as $proj): $photos = $proj['photos'] ?? []; ?>
            <div class="gallery-project">
              <div class="gallery-project-head">
                <span><?= htmlspecialchars($proj['name']) ?> &middot; <?= count($photos) ?> photo<?= count($photos) === 1 ? '' : 's' ?></span>
                <form method="post" action="delete-project.php" onsubmit="return confirm('Remove this project and all its photos? Files are moved to a backup folder, not permanently deleted.');">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="location_id" value="<?= htmlspecialchars($loc['id']) ?>">
                  <input type="hidden" name="project_id" value="<?= htmlspecialchars($proj['id']) ?>">
                  <button type="submit" class="btn btn-ghost btn-danger">Remove Project</button>
                </form>
              </div>

              <?php if ($photos): ?>
                <div class="gallery-photo-grid">
                  <?php foreach ($photos as $i => $photoUrl): ?>
                    <div class="gallery-photo-thumb">
                      <img src="../<?= htmlspecialchars($photoUrl) ?>" alt="" loading="lazy">
                      <form method="post" action="delete-photo.php" onsubmit="return confirm('Remove this photo?');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="location_id" value="<?= htmlspecialchars($loc['id']) ?>">
                        <input type="hidden" name="project_id" value="<?= htmlspecialchars($proj['id']) ?>">
                        <input type="hidden" name="photo_index" value="<?= (int)$i ?>">
                        <button type="submit" class="gallery-photo-remove" aria-label="Remove photo">&times;</button>
                      </form>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <?php if ($backups): ?>
  <section class="admin-card">
    <h2>Recent backups</h2>
    <p class="muted">The last <?= count($backups) ?> version(s) are kept automatically on the server at <code>storage/backups/</code>, in case you need to ask your developer to roll back.</p>
    <ul class="backup-list">
      <?php foreach ($backups as $b): ?>
        <li><?= htmlspecialchars($b) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="admin-card">
    <h2>Change password</h2>
    <form method="post" action="change-password.php" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <label>Current password <input type="password" name="current_password" required></label>
      <label>New password <input type="password" name="new_password" minlength="4" required></label>
      <label>Confirm new password <input type="password" name="new_password2" minlength="4" required></label>
      <button class="btn btn-ghost" type="submit">Update Password</button>
    </form>
  </section>

</main>

<script>
  window.CE_GALLERY_DATA = <?= json_encode(array_map(function ($loc) {
    return [
      'id' => $loc['id'] ?? '',
      'name' => $loc['name'] ?? '',
      'projects' => array_map(function ($p) {
        return ['id' => $p['id'] ?? '', 'name' => $p['name'] ?? ''];
      }, $loc['projects'] ?? []),
    ];
  }, $galleryLocations), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="admin.js"></script>
</body>
</html>