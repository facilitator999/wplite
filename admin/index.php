<?php
/**
 * WPlite admin: login, post CRUD, page editor, settings.
 * Single file, ?action= dispatch. All writes require auth + CSRF.
 */

require dirname(__DIR__) . '/lib.php';

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

/* --------------------------------------------------------------- helpers */

function admin_head(string $title, array $config): void
{
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= esc($title) ?> — WPlite Admin</title>
<link rel="stylesheet" href="<?= esc(wpl_url('assets/admin.css')) ?>?v=<?= filemtime(WPL_ROOT . '/assets/admin.css') ?>">
</head>
<body class="admin">
<div class="admin-wrap">
<?php
}

function admin_foot(): void
{
    echo '</main></div></body></html>';
}

/** Sidebar navigation; also opens <main> — admin_foot() closes it. */
function admin_nav(string $active = ''): void
{
    $config = wpl_config();
    $items = [
        'posts' => ['Posts', wpl_url('admin/')],
        'pages' => ['Pages', wpl_url('admin/?action=pages')],
        'settings' => ['Settings', wpl_url('admin/?action=settings')],
    ];
    ?>
    <aside class="admin-side">
      <div class="brand">
        <span class="brand-mark">✺</span>
        <span class="brand-text"><strong><?= esc($config['site_name']) ?></strong><small>WPlite</small></span>
      </div>
      <nav class="admin-nav">
        <?php foreach ($items as $key => [$label, $href]): ?>
          <a href="<?= esc($href) ?>"<?= $key === $active ? ' class="active"' : '' ?>><?= esc($label) ?></a>
        <?php endforeach; ?>
      </nav>
      <nav class="admin-nav admin-nav-bottom">
        <a href="<?= esc(wpl_url()) ?>" target="_blank" rel="noopener">View site &nearr;</a>
        <a href="<?= esc(wpl_url('admin/?action=logout')) ?>">Log out</a>
      </nav>
    </aside>
    <main class="admin-main">
    <?php
}

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

$csrf = wpl_csrf_token();
$postCsrf = fn() => '<input type="hidden" name="csrf" value="' . esc($csrf) . '">';

/* ------------------------------------------------------------ post editor */

if ($action === 'edit') {
    $slug = $_GET['slug'] ?? '';
    $editing = $slug !== '' ? wpl_get(WPL_POSTS, $slug) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!wpl_csrf_check()) {
            $err = 'Session expired — please retry.';
        } else {
            $title = trim((string)($_POST['title'] ?? ''));
            $newSlug = slugify((string)($_POST['slug'] ?? '')) ?: slugify($title);
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date'] ?? '') ? $_POST['date'] : date('Y-m-d');
            $body = str_replace("\r\n", "\n", (string)($_POST['body'] ?? ''));
            $image = $editing['image'] ?? '';

            if ($title === '') {
                $err = 'Title is required.';
            } elseif (!wpl_valid_slug($newSlug)) {
                $err = 'Slug must be lowercase letters, numbers and hyphens (and not a reserved word).';
            } elseif ($newSlug !== ($editing['slug'] ?? null)
                && (is_file(WPL_POSTS . "/$newSlug.md") || is_file(WPL_PAGES . "/$newSlug.md"))) {
                $err = "A post or page with slug \"$newSlug\" already exists.";
            } else {
                if (!empty($_FILES['image']['name'])) {
                    $result = wpl_process_upload($_FILES['image']);
                    if (str_starts_with($result, 'error:')) {
                        $err = substr($result, 6);
                    } else {
                        if ($image !== '') {
                            wpl_delete_images($image);
                        }
                        $image = $result;
                    }
                }
                if ($err === '') {
                    $meta = [
                        'title' => $title,
                        'date' => $date,
                        'image' => $image,
                        'featured' => isset($_POST['featured']),
                    ];
                    if (wpl_write(WPL_POSTS . "/$newSlug.md", wpl_serialize($meta, $body))) {
                        if ($editing && $editing['slug'] !== $newSlug) {
                            @unlink(WPL_POSTS . '/' . $editing['slug'] . '.md');
                        }
                        header('Location: ' . wpl_url('admin/?saved=' . rawurlencode($newSlug)));
                        exit;
                    }
                    $err = 'Could not write the post file — check folder permissions.';
                }
            }
            // Re-show submitted values on error.
            $editing = [
                'slug' => $newSlug, 'title' => $title, 'date' => $date,
                'image' => $image, 'featured' => isset($_POST['featured']), 'body' => $body,
            ];
        }
    }

    admin_head($editing ? 'Edit post' : 'New post', $config);
    admin_nav('posts');
    ?>
    <h1><?= $editing && $slug !== '' ? 'Edit post' : 'New post' ?></h1>
    <?php if ($err): ?><p class="msg err"><?= esc($err) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <?= $postCsrf() ?>
      <label>Title <input type="text" name="title" value="<?= esc($editing['title'] ?? '') ?>" required autofocus></label>
      <label>Slug (URL) <input type="text" name="slug" value="<?= esc($editing['slug'] ?? '') ?>" placeholder="auto from title"></label>
      <label>Date <input type="date" name="date" value="<?= esc($editing['date'] ?? date('Y-m-d')) ?>"></label>
      <label class="check"><input type="checkbox" name="featured" <?= !empty($editing['featured']) ? 'checked' : '' ?>> Featured (show in carousel)</label>
      <label>Featured image <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
        <?php if (!empty($editing['image'])): ?><small>current: <?= esc($editing['image']) ?></small><?php endif; ?>
      </label>
      <label>Body (markdown) <textarea name="body"><?= esc($editing['body'] ?? '') ?></textarea></label>
      <button class="btn">Save post</button>
    </form>
    <?php
    admin_foot();
    exit;
}

/* ----------------------------------------------------------------- delete */

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!wpl_csrf_check()) {
        $err = 'Session expired — please retry.';
    } else {
        $slug = (string)($_POST['slug'] ?? '');
        $post = wpl_get(WPL_POSTS, $slug);
        if ($post !== null) {
            if ($post['image'] !== '') {
                wpl_delete_images($post['image']);
            }
            @unlink(WPL_POSTS . "/$slug.md");
        }
    }
    header('Location: ' . wpl_url('admin/'));
    exit;
}

/* ------------------------------------------------------------------ pages */

if ($action === 'pages') {
    $slug = $_GET['slug'] ?? '';
    $editing = $slug !== '' ? wpl_get(WPL_PAGES, $slug) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!wpl_csrf_check()) {
            $err = 'Session expired — please retry.';
        } else {
            $title = trim((string)($_POST['title'] ?? ''));
            $newSlug = slugify((string)($_POST['slug'] ?? '')) ?: slugify($title);
            $body = str_replace("\r\n", "\n", (string)($_POST['body'] ?? ''));
            if ($title === '') {
                $err = 'Title is required.';
            } elseif (!wpl_valid_slug($newSlug)) {
                $err = 'Slug must be lowercase letters, numbers and hyphens.';
            } elseif ($newSlug !== ($editing['slug'] ?? null) && is_file(WPL_POSTS . "/$newSlug.md")) {
                $err = "A post with slug \"$newSlug\" already exists.";
            } else {
                if (wpl_write(WPL_PAGES . "/$newSlug.md", wpl_serialize(['title' => $title], $body))) {
                    if ($editing && $editing['slug'] !== $newSlug) {
                        @unlink(WPL_PAGES . '/' . $editing['slug'] . '.md');
                    }
                    $msg = 'Page saved.';
                    $editing = wpl_get(WPL_PAGES, $newSlug);
                    $slug = $newSlug;
                } else {
                    $err = 'Could not write the page file.';
                }
            }
        }
    }

    admin_head('Pages', $config);
    admin_nav('pages');
    ?>
    <h1>Pages</h1>
    <?php if ($msg): ?><p class="msg"><?= esc($msg) ?></p><?php endif; ?>
    <?php if ($err): ?><p class="msg err"><?= esc($err) ?></p><?php endif; ?>
    <table>
      <?php foreach (wpl_pages() as $p): ?>
        <tr>
          <td><a href="<?= esc(wpl_url('admin/?action=pages&slug=' . $p['slug'])) ?>"><?= esc($p['title']) ?></a></td>
          <td>/<?= esc($p['slug']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <h2><?= $editing ? 'Edit page' : 'New page' ?></h2>
    <form method="post">
      <?= $postCsrf() ?>
      <label>Title <input type="text" name="title" value="<?= esc($editing['title'] ?? '') ?>" required></label>
      <label>Slug (URL) <input type="text" name="slug" value="<?= esc($editing['slug'] ?? '') ?>" placeholder="auto from title"></label>
      <label>Body (markdown) <textarea name="body"><?= esc($editing['body'] ?? '') ?></textarea></label>
      <button class="btn">Save page</button>
    </form>
    <?php
    admin_foot();
    exit;
}

/* --------------------------------------------------------------- settings */

if ($action === 'settings') {
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
                    $result = wpl_process_upload($_FILES[$field]);
                    if (str_starts_with($result, 'error:')) {
                        $err = ucfirst($field) . ': ' . substr($result, 6);
                    } else {
                        $new[$field] = $result;
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
      <?= $postCsrf() ?>
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
    exit;
}

/* -------------------------------------------------------------- dashboard */

admin_head('Posts', $config);
admin_nav('posts');
if (isset($_GET['saved'])) {
    $msg = 'Post saved: ' . $_GET['saved'];
}
?>
<div class="page-head">
  <h1>Posts</h1>
  <a class="btn" href="<?= esc(wpl_url('admin/?action=edit')) ?>">+ New post</a>
</div>
<?php if ($msg): ?><p class="msg"><?= esc($msg) ?></p><?php endif; ?>
<table>
  <tr><th>Title</th><th>Date</th><th>Featured</th><th></th></tr>
  <?php foreach (wpl_posts() as $p): ?>
    <tr>
      <td><a href="<?= esc(wpl_url('admin/?action=edit&slug=' . $p['slug'])) ?>"><?= esc($p['title']) ?></a></td>
      <td><?= esc($p['date']) ?></td>
      <td><?= $p['featured'] ? '★' : '' ?></td>
      <td>
        <form method="post" action="<?= esc(wpl_url('admin/?action=delete')) ?>"
              onsubmit="return confirm('Delete &quot;<?= esc($p['title']) ?>&quot;?')">
          <?= $postCsrf() ?>
          <input type="hidden" name="slug" value="<?= esc($p['slug']) ?>">
          <button class="btn btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php
admin_foot();
