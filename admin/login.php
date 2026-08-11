<?php
require_once __DIR__ . '/inc/auth.php';

if (!ce_auth_configured()) {
    header('Location: setup.php');
    exit;
}
if (ce_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$locked = ce_login_locked_out();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    if (!ce_csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Your session expired — please try again.';
    } else {
        $pw = (string)($_POST['password'] ?? '');
        $hash = ce_get_password_hash();
        if ($hash && password_verify($pw, $hash)) {
            ce_clear_login_attempts();
            session_regenerate_id(true);
            $_SESSION['ceylon_admin_authed'] = true;
            header('Location: index.php');
            exit;
        } else {
            ce_register_failed_login();
            $error = 'Incorrect password.';
            $locked = ce_login_locked_out();
        }
    }
}

$flash = ce_flash_take();
$csrf = ce_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login — Ceylon Energy Services</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
</head>
<body class="admin-auth-page">
<div class="auth-card">
  <p class="eyebrow">Ceylon Energy Services — Admin</p>
  <h1>Admin Login</h1>
  <p class="lede">Log in to update the Company Profile attachment shown on the website.</p>

  <?php if ($flash): ?><div class="notice notice-ok"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <?php if ($locked): ?>
    <div class="notice notice-error">Too many failed attempts. Please try again in a few minutes.</div>
  <?php elseif ($error): ?>
    <div class="notice notice-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <label>Password
      <input type="password" name="password" required autofocus <?= $locked ? 'disabled' : '' ?>>
    </label>
    <button class="btn" type="submit" <?= $locked ? 'disabled' : '' ?>>Log In</button>
  </form>
  <p class="fine-print"><a href="../index.html">&larr; Back to website</a></p>
</div>
</body>
</html>