<?php
/**
 * WPlite admin entry: auth gate + ?action= dispatch into actions/*.php.
 * All writes require auth + CSRF (checked inside the action files).
 */

require_once dirname(__DIR__) . '/lib.php';
define('WPL_ADMIN', true);
require __DIR__ . '/helpers.php';

$config = wpl_config();
$action = $_GET['action'] ?? 'dashboard';
$msg = '';
$err = '';

/* ------------------------------------------------------------ login/logout */

if ($action === 'logout') {
    wpl_logout();
    header('Location: ' . wpl_url('admin/'));
    exit;
}

if (!wpl_is_authed()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (wpl_login((string)$_POST['password'])) {
            header('Location: ' . wpl_url('admin/'));
            exit;
        }
        $err = wpl_lock_until() > time()
            ? 'Too many attempts — wait a minute and try again.'
            : 'Wrong password.';
    }
    admin_head('Log in', $config);
    ?>
    <main class="admin-main admin-login">
      <div class="login-card">
        <span class="brand-mark">✺</span>
        <h1><?= esc($config['site_name']) ?></h1>
        <p class="login-sub">Sign in to admin</p>
        <?php if ($err): ?><p class="msg err"><?= esc($err) ?></p><?php endif; ?>
        <form method="post">
          <label>Password <input type="password" name="password" required autofocus></label>
          <button class="btn">Log in</button>
        </form>
      </div>
    <?php
    admin_foot();
    exit;
}

/* --------------------------------------------------------------- dispatch */

require __DIR__ . '/actions/' . match ($action) {
    'pages' => 'pages.php',
    'settings' => 'settings.php',
    'upload' => 'upload.php', // in-editor image uploads (JSON)
    default => 'posts.php', // dashboard, edit, delete, unknown actions
};
