<?php
/**
 * Shared <head> + header + tab navigation for the admin panel's three
 * pages (Projects, Attachments, Awards). Keeping this in one place means
 * the tab bar and page chrome stay identical everywhere.
 */

function ce_admin_head($title) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($title) ?> — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<?php
}

/**
 * $active is one of 'projects' | 'attachments' | 'awards'.
 */
function ce_admin_header($active, $heading) {
    $tabs = [
        'projects'    => ['label' => 'Projects',    'href' => 'projects.php'],
        'attachments' => ['label' => 'Attachments', 'href' => 'attachments.php'],
        'awards'      => ['label' => 'Awards',       'href' => 'awards.php'],
    ];
    ?>
<header class="admin-header">
  <div>
    <p class="eyebrow">Ceylon Energy Services — Admin</p>
    <h1><?= htmlspecialchars($heading) ?></h1>
  </div>
  <div class="admin-header-actions">
    <a class="btn btn-ghost" href="../index.html" target="_blank" rel="noopener">View live site</a>
    <a class="btn btn-ghost" href="logout.php">Log out</a>
  </div>
</header>
<nav class="admin-tabs" aria-label="Admin sections">
  <?php foreach ($tabs as $key => $tab): ?>
    <a class="admin-tab<?= $key === $active ? ' is-active' : '' ?>" href="<?= htmlspecialchars($tab['href']) ?>"><?= htmlspecialchars($tab['label']) ?></a>
  <?php endforeach; ?>
</nav>
<?php
}

function ce_admin_foot($extraScript = '') {
    ?>
<?= $extraScript ?>
</body>
</html>
<?php
}
