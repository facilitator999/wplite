<?php
/** Settings: site identity, socials, images, batch size, admin password. */

defined('WPL_ADMIN') || exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!wpl_csrf_check()) {
        $err = 'Session expired — please retry.';
    } else {
        $new = $config;
        $new['site_name'] = trim((string)($_POST['site_name'] ?? '')) ?: $config['site_name'];
        $new['blurb'] = trim((string)($_POST['blurb'] ?? ''));
        $accent = (string)($_POST['accent'] ?? '');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            $new['accent'] = $accent;
        }
        foreach (array_keys($new['socials']) as $key) {
            $v = trim((string)($_POST['social_' . $key] ?? ''));
            if ($key !== 'email' && $v !== '' && !preg_match('~^https://~', $v)) {
                $v = 'https://' . preg_replace('~^https?://~', '', $v);
            }
            $new['socials'][$key] = $v;
        }
        $new['batch_size'] = max(3, min(60, (int)($_POST['batch_size'] ?? 12)));

        foreach (['portrait', 'logo'] as $field) {
            if (!empty($_FILES[$field]['name'])) {
                $up = admin_upload_image($_FILES[$field]);
                if ($up['err'] !== '') {
                    $err = ucfirst($field) . ': ' . $up['err'];
                } else {
                    $new[$field] = $up['file'];
                }
            }
        }

        $pw = (string)($_POST['new_password'] ?? '');
        if ($pw !== '') {
            if (strlen($pw) < 8) {
                $err = 'New password must be at least 8 characters.';
            } else {
                $new['admin_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            }
        }

        if ($err === '') {
            if (wpl_save_config($new)) {
                $config = $new;
                $msg = 'Settings saved.';
            } else {
                $err = 'Could not write config.php — check permissions.';
            }
        }
    }
}

admin_head('Settings', $config);
admin_nav('settings');
?>
<h1>Settings</h1>
<?php if ($msg): ?><p class="msg"><?= esc($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="msg err"><?= esc($err) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
  <?= admin_csrf_field() ?>
  <label>Site / person name <input type="text" name="site_name" value="<?= esc($config['site_name']) ?>" required></label>
  <label>Blurb (one line under your name) <input type="text" name="blurb" value="<?= esc($config['blurb']) ?>"></label>
  <label>Accent colour <input type="color" name="accent" value="<?= esc($config['accent']) ?>"></label>
  <label>Portrait photo <input type="file" name="portrait" accept="image/jpeg,image/png,image/webp">
    <small>current: <?= esc($config['portrait']) ?></small></label>
  <label>Logo image <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
    <small>current: <?= esc($config['logo']) ?> (leave empty to show your name as text)</small></label>
  <?php foreach ($config['socials'] as $key => $val): ?>
    <label><?= esc(ucfirst($key)) ?> <?= $key === 'email' ? 'address' : 'URL' ?>
      <input type="<?= $key === 'email' ? 'email' : 'text' ?>" name="social_<?= esc($key) ?>" value="<?= esc($val) ?>"></label>
  <?php endforeach; ?>
  <label>Posts per scroll batch <input type="text" name="batch_size" value="<?= esc((string)$config['batch_size']) ?>"></label>
  <label>New admin password (leave empty to keep current) <input type="password" name="new_password" minlength="8" autocomplete="new-password"></label>
  <button class="btn">Save settings</button>
</form>
<?php
admin_foot();
