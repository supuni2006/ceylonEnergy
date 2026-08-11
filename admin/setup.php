<?php
require_once __DIR__ . '/inc/auth.php';

if (ce_auth_configured()) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ce_csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Your session expired — please try again.';
    } else {
        $pw = (string)($_POST['password'] ?? '');
        $pw2 = (string)($_POST['password2'] ?? '');
        if (strlen($pw) < 4) {
            $error = 'Please choose a password of at least 4 characters.';
        } elseif ($pw !== $pw2) {
            $error = 'Passwords do not match.';
        } else {
            ce_save_password_hash(password_hash($pw, PASSWORD_DEFAULT));
            ce_flash_set('Admin password created. You can log in now.');
            header('Location: login.php');
            exit;
        }
    }
}
$csrf = ce_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Set Up Admin Access — Ceylon Energy Services</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
</head>
<body class="admin-auth-page">
<div class="auth-card">
  <p class="eyebrow">Ceylon Energy Services — Admin</p>
  <h1>Create Your Admin Password</h1>
  <p class="lede">This is a one-time setup. Choose a password only you know — you'll use it to update the Company Profile attachment on the website.</p>

  <?php if ($error): ?><div class="notice notice-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <label>New password
      <input type="password" name="password" minlength="4" required autofocus>
    </label>
    <label>Confirm password
      <input type="password" name="password2" minlength="4" required>
    </label>
    <button class="btn" type="submit">Create Password &amp; Continue</button>
  </form>
  <p class="fine-print">At least 4 characters. Store it somewhere safe — there's no automatic "forgot password" email; a developer can reset it by deleting <code>admin/data/auth.php</code> on the server.</p>
</div>
</body>
</html>