<?php
/**
 * Shared admin chrome and form helpers. Loaded by admin/index.php (and
 * install.php for the page chrome) — never reachable directly.
 */

defined('WPL_ADMIN') || exit;

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
<link rel="stylesheet" href="<?= esc(wpl_url('assets/easymde.min.css')) ?>?v=2.20.0">
</head>
<body class="admin">
<div class="admin-wrap">
<?php
}

function admin_foot(): void
{
    // Rich markdown editor on any page with a body textarea (progressive
    // enhancement — the plain textarea still works without JS).
    ?>
    <script src="<?= esc(wpl_url('assets/easymde.min.js')) ?>?v=2.20.0"></script>
    <script>
    // Live thumbnail preview when picking an image file.
    document.querySelectorAll('input[type=file][data-preview]').forEach(function (input) {
      input.addEventListener('change', function () {
        var img = document.getElementById(input.dataset.preview);
        if (img && input.files && input.files[0]) {
          img.src = URL.createObjectURL(input.files[0]);
          img.hidden = false;
        }
      });
    });
    </script>
    <script>
    (function () {
      var ta = document.querySelector('textarea[name="body"]');
      if (!ta || !window.EasyMDE) return;
      new EasyMDE({
        element: ta,
        spellChecker: false,
        status: false,
        forceSync: true,
        minHeight: window.innerWidth < 760 ? '50vh' : '320px',
        toolbar: ['bold', 'italic', 'heading', '|', 'quote', 'unordered-list', 'ordered-list', '|',
                  'link', 'upload-image', '|', 'preview', 'fullscreen'],
        // Inline body images: toolbar button, drag-drop, or paste. Uploads go
        // through the same pipeline as featured images.
        uploadImage: true,
        imageUploadEndpoint: <?= json_encode(wpl_url('admin/?action=upload')) ?>,
        imageCSRFToken: <?= json_encode(wpl_csrf_token()) ?>,
        imageCSRFName: 'csrf',
        imageMaxSize: 10 * 1024 * 1024,
        imageAccept: 'image/jpeg, image/png, image/webp',
        errorCallback: function (msg) { alert(msg); },
      });
    })();
    </script>
    </main></div></body></html>
    <?php
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

/** Hidden CSRF input for forms; $scope as in wpl_csrf_token(). */
function admin_csrf_field(?string $scope = null): string
{
    return '<input type="hidden" name="csrf" value="' . esc(wpl_csrf_token($scope)) . '">';
}

/**
 * Run one $_FILES entry through the image pipeline.
 * Returns ['file' => ?string, 'err' => string] — err empty on success.
 */
function admin_upload_image(array $file): array
{
    $result = wpl_process_upload($file);
    return str_starts_with($result, 'error:')
        ? ['file' => null, 'err' => substr($result, 6)]
        : ['file' => $result, 'err' => ''];
}

/**
 * Shared post/page validation: title required, slug valid, no .md with the
 * same slug in $collisionDirs (skipped when the slug is unchanged).
 * Returns an error message, or '' when the entry may be written.
 * Runs BEFORE any uploads are processed so a failed save never touches images.
 */
function admin_validate_entry(?string $oldSlug, string $title, string $slug,
                              array $collisionDirs, string $slugErr, string $collisionErr): string
{
    if ($title === '') {
        return 'Title is required.';
    }
    if (!wpl_valid_slug($slug)) {
        return $slugErr;
    }
    if ($slug !== $oldSlug) {
        foreach ($collisionDirs as $d) {
            if (is_file($d . "/$slug.md")) {
                return sprintf($collisionErr, $slug);
            }
        }
    }
    return '';
}

/** Write a validated post/page, unlinking the old file on rename. */
function admin_write_entry(string $dir, ?string $oldSlug, string $slug, array $meta, string $body): bool
{
    if (!wpl_write($dir . "/$slug.md", wpl_serialize($meta, $body))) {
        return false;
    }
    if ($oldSlug !== null && $oldSlug !== $slug) {
        @unlink($dir . "/$oldSlug.md");
    }
    return true;
}
