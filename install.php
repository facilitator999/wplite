<?php
/**
 * WPlite installer / master dashboard, reached at /install (root mode only).
 *
 * First run: sets the engine master password (stored as a bcrypt hash in
 * data/master.php). After that: master login + dashboard that lists all
 * sites and creates new ones — no CLI needed.
 */

require_once __DIR__ . '/lib.php';
define('WPL_ADMIN', true); // unlocks admin/helpers.php for the page chrome
require_once __DIR__ . '/admin/helpers.php';

if (PHP_SAPI !== 'cli' && WPL_MODE !== 'root') {
    http_response_code(404);
    exit('Not found');
}

$err = '';
$msg = '';
$chrome = ['site_name' => 'WPlite']; // admin_head() only reads site_name

/** Create data/master.php holding the engine master password hash. */
function install_write_master(string $password): bool
{
    $php = "<?php\n// WPlite engine master credentials — created by the installer.\nreturn "
        . var_export(['master_hash' => password_hash($password, PASSWORD_DEFAULT)], true) . ";\n";
    if (!is_dir(WPL_ROOT . '/data') && !@mkdir(WPL_ROOT . '/data', 0755)) {
        return false;
    }
    $ok = wpl_write(wpl_master_file(), $php);
    if ($ok && function_exists('opcache_invalidate')) {
        opcache_invalidate(wpl_master_file(), true);
    }
    return $ok;
}

/** Scaffold a new site; returns '' on success or an error message. */
function install_create_site(string $slug, string $name, string $password): string
{
    if (!wpl_valid_site_slug($slug)) {
        return 'Slug must be 2-30 lowercase letters, numbers or hyphens (and not a reserved word).';
    }
    if (is_file(wpl_site_config_file($slug)) || is_dir(WPL_ROOT . "/sites/$slug")) {
        return "A site \"$slug\" already exists.";
    }
    if ($name === '') {
        return 'Site name is required.';
    }
    if (strlen($password) < 8) {
        return 'Admin password must be at least 8 characters.';
    }
    foreach (['content/posts', 'content/pages', 'uploads'] as $d) {
        if (!@mkdir(WPL_ROOT . "/sites/$slug/$d", 0755, true)) {
            return 'Could not create the site folders — check permissions.';
        }
    }
    @copy(WPL_ROOT . '/sites/_template/content/posts/hello.md',
        WPL_ROOT . "/sites/$slug/content/posts/hello.md");
    // Config written last: the site only resolves once this file exists.
    if (!wpl_write_site_config($slug, wpl_default_config($name, password_hash($password, PASSWORD_DEFAULT)))) {
        return 'Could not write the site config — check permissions.';
    }
    return '';
}

/* --------------------------------------------------------------- first run */

if (!wpl_master_exists()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['master_password'])) {
        $pw = (string)$_POST['master_password'];
        if (!wpl_csrf_check('_master')) {
            $err = 'Session expired — please retry.';
        } elseif (strlen($pw) < 12) {
            $err = 'Master password must be at least 12 characters.';
        } elseif ($pw !== (string)($_POST['master_password2'] ?? '')) {
            $err = 'Passwords do not match.';
        } elseif (!install_write_master($pw)) {
            $err = 'Could not write data/master.php — check permissions.';
        } else {
            wpl_master_login($pw);
            header('Location: ' . wpl_url('install'));
            exit;
        }
    }
    admin_head('Install', $chrome);
    ?>
    <main class="admin-main admin-login">
      <div class="login-card">
        <span class="brand-mark">✺</span>
        <h1>Welcome to WPlite</h1>
        <p class="login-sub">Set the engine master password. It guards site creation — make it long.</p>
        <?php if ($err): ?><p class="msg err"><?= esc($err) ?></p><?php endif; ?>
        <form method="post">
          <?= admin_csrf_field('_master') ?>
          <label>Master password <input type="password" name="master_password" minlength="12" required autofocus></label>
          <label>Repeat password <input type="password" name="master_password2" minlength="12" required></label>
          <button class="btn">Install</button>
        </form>
      </div>
    <?php
    admin_foot();
    exit;
}

/* ----------------------------------------------------------- master login */

if (($_GET['action'] ?? '') === 'logout') {
    wpl_master_logout();
    header('Location: ' . wpl_url('install'));
    exit;
}

if (!wpl_is_master()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (wpl_master_login((string)$_POST['password'])) {
            header('Location: ' . wpl_url('install'));
            exit;
        }
        $err = wpl_lock_until('_master') > time()
            ? 'Too many attempts — wait a minute and try again.'
            : 'Wrong password.';
    }
    admin_head('Master login', $chrome);
    ?>
    <main class="admin-main admin-login">
      <div class="login-card">
        <span class="brand-mark">✺</span>
        <h1>WPlite</h1>
        <p class="login-sub">Engine master login</p>
        <?php if ($err): ?><p class="msg err"><?= esc($err) ?></p><?php endif; ?>
        <form method="post">
          <label>Master password <input type="password" name="password" required autofocus></label>
          <button class="btn">Log in</button>
        </form>
      </div>
    <?php
    admin_foot();
    exit;
}

/* -------------------------------------------------------------- dashboard */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_slug'])) {
    if (!wpl_csrf_check('_master')) {
        $err = 'Session expired — please retry.';
    } else {
        $slug = strtolower(trim((string)$_POST['new_slug']));
        $err = install_create_site($slug, trim((string)($_POST['new_name'] ?? '')),
            (string)($_POST['new_password'] ?? ''));
        if ($err === '') {
            $msg = "Site \"$slug\" created.";
        }
    }
}

$sites = [];
foreach (glob(WPL_ROOT . '/data/*/config.php') ?: [] as $cf) {
    $slug = basename(dirname($cf));
    if (!wpl_valid_site_slug($slug)) {
        continue;
    }
    try {
        $cfg = require $cf;
    } catch (Throwable) {
        $cfg = [];
    }
    $sites[$slug] = is_array($cfg) ? $cfg : [];
}
ksort($sites);

admin_head('Sites', $chrome);
?>
<main class="admin-main">
<div class="page-head">
  <h1>Sites</h1>
  <a class="btn" href="<?= esc(wpl_url('install?action=logout')) ?>">Log out</a>
</div>
<?php if ($msg): ?><p class="msg"><?= esc($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="msg err"><?= esc($err) ?></p><?php endif; ?>
<table>
  <tr><th>Site</th><th>Name</th><th>Domains</th><th></th></tr>
  <?php foreach ($sites as $slug => $cfg): ?>
    <tr>
      <td><?= esc($slug) ?></td>
      <td><?= esc((string)($cfg['site_name'] ?? '?')) ?></td>
      <td><?= esc(implode(', ', (array)($cfg['domains'] ?? []))) ?></td>
      <td>
        <a href="<?= esc(wpl_base() . '/s/' . $slug . '/') ?>" target="_blank" rel="noopener">view</a> ·
        <a href="<?= esc(wpl_base() . '/s/' . $slug . '/admin/') ?>" target="_blank" rel="noopener">admin</a>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<h2>Create a site</h2>
<form method="post">
  <?= admin_csrf_field('_master') ?>
  <label>Slug (folder + preview URL) <input type="text" name="new_slug" pattern="[a-z0-9][a-z0-9-]{1,29}" required placeholder="acme"></label>
  <label>Site / person name <input type="text" name="new_name" required placeholder="Acme Corp"></label>
  <label>Site admin password <input type="password" name="new_password" minlength="8" required autocomplete="new-password"></label>
  <button class="btn">Create site</button>
</form>
<?php
admin_foot();
