<?php
/**
 * The admin panel now has three separate pages (Projects, Attachments,
 * Awards) — see projects.php, attachments.php and awards.php. This file
 * is kept only as the default landing URL and just forwards to Projects.
 */
require_once __DIR__ . '/inc/auth.php';

ce_require_login();

header('Location: projects.php');
exit;
