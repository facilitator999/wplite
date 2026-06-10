<?php
/** @var array $config  @var string $view  @var array $pages */
$titles = [
    'home' => $config['site_name'],
    'post' => isset($post) ? $post['title'] . ' — ' . $config['site_name'] : '',
    'page' => isset($page) ? $page['title'] . ' — ' . $config['site_name'] : '',
    'credit' => 'Powered by WPlite — ' . $config['site_name'],
    '404' => 'Not found — ' . $config['site_name'],
];

$icons = [
    'instagram' => '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M12 2.2c3.2 0 3.6 0 4.8.1 1.2.1 1.8.2 2.2.4.6.2 1 .5 1.4.9.4.4.7.8.9 1.4.2.4.4 1 .4 2.2.1 1.2.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 1.2-.2 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.8.7-1.4.9-.4.2-1 .4-2.2.4-1.2.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-1.2-.1-1.8-.2-2.2-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.8-.9-1.4-.2-.4-.4-1-.4-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.8c.1-1.2.2-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.2 1-.4 2.2-.4C8.4 2.2 8.8 2.2 12 2.2m0 1.8c-3.1 0-3.5 0-4.7.1-1.1.1-1.5.2-1.8.3-.5.2-.8.4-1.1.7-.3.3-.5.6-.7 1.1-.1.3-.3.8-.3 1.8-.1 1.2-.1 1.6-.1 4.7s0 3.5.1 4.7c.1 1.1.2 1.5.3 1.8.2.5.4.8.7 1.1.3.3.6.5 1.1.7.3.1.8.3 1.8.3 1.2.1 1.6.1 4.7.1s3.5 0 4.7-.1c1.1-.1 1.5-.2 1.8-.3.5-.2.8-.4 1.1-.7.3-.3.5-.6.7-1.1.1-.3.3-.8.3-1.8.1-1.2.1-1.6.1-4.7s0-3.5-.1-4.7c-.1-1.1-.2-1.5-.3-1.8-.2-.5-.4-.8-.7-1.1-.3-.3-.6-.5-1.1-.7-.3-.1-.8-.3-1.8-.3-1.2-.1-1.6-.1-4.7-.1zm0 3.1a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 8.2a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4zm6.4-8.4a1.2 1.2 0 1 1-2.4 0 1.2 1.2 0 0 1 2.4 0z"/></svg>',
    'x' => '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M18.9 2H22l-7 8 8.3 12h-6.5l-5.1-7.3L5.8 22H2.7l7.5-8.6L2.2 2h6.7l4.6 6.5L18.9 2zm-1.1 18h1.8L7.8 3.8H5.9L17.8 20z"/></svg>',
    'facebook' => '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12z"/></svg>',
    'youtube' => '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31.2 31.2 0 0 0 0 12c0 1.9.2 3.9.5 5.8a3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1c.3-1.9.5-3.9.5-5.8s-.2-3.9-.5-5.8zM9.5 15.6V8.4L15.8 12l-6.3 3.6z"/></svg>',
    'email' => '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/></svg>',
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($titles[$view] ?? $config['site_name']) ?></title>
<meta name="description" content="<?= esc($config['blurb']) ?>">
<link rel="stylesheet" href="<?= esc(wpl_url('assets/style.css')) ?>?v=<?= filemtime(WPL_ROOT . '/assets/style.css') ?>">
<style>:root { --accent: <?= esc($config['accent']) ?>; }</style>
</head>
<body>
<a class="skip-link" href="#content">Skip to content</a>
<header class="site-header">
  <div class="header-inner">
    <a class="logo" href="<?= esc(wpl_url()) ?>">
      <?php if (!empty($config['logo']) && is_file(WPL_UPLOADS . '/' . basename($config['logo']))): ?>
        <img src="<?= esc(wpl_upload_url($config['logo'])) ?>" alt="<?= esc($config['site_name']) ?>">
      <?php else: ?>
        <span class="logo-text"><?= esc($config['site_name']) ?></span>
      <?php endif; ?>
    </a>
    <?php $headerPages = wpl_pages_in('header', $pages); ?>
    <?php if ($headerPages): ?>
      <nav class="header-nav" aria-label="Pages">
        <?php foreach ($headerPages as $p): ?>
          <a href="<?= esc(wpl_url($p['slug'])) ?>"><?= esc($p['title']) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <nav class="socials" aria-label="Social links">
      <?php foreach ($config['socials'] as $key => $link): ?>
        <?php if ($link === '' || !isset($icons[$key])) continue; ?>
        <?php $href = $key === 'email' ? 'mailto:' . $link : $link; ?>
        <a href="<?= esc($href) ?>" aria-label="<?= esc(ucfirst($key)) ?>" <?= $key === 'email' ? '' : 'target="_blank" rel="noopener"' ?>><?= $icons[$key] ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<main>
<div class="profile-layout">
  <?php require __DIR__ . '/profile-card.php'; ?>
  <div class="feed" id="content" tabindex="-1">
    <?php require __DIR__ . '/' . ($view === '404' ? 'page' : $view) . '.php'; ?>
  </div>
</div>
</main>

<footer class="site-footer">
  <nav class="footer-links" aria-label="Footer">
    <?php foreach (wpl_pages_in('footer', $pages) as $p): ?>
      <a href="<?= esc(wpl_url($p['slug'])) ?>"><?= esc($p['title']) ?></a>
    <?php endforeach; ?>
  </nav>
  <p>&copy; <?= date('Y') ?> <?= esc($config['site_name']) ?> · <a class="credit-link" href="<?= esc(wpl_url('wplite')) ?>">Powered by WPlite</a></p>
</footer>

<script src="<?= esc(wpl_url('assets/app.js')) ?>?v=<?= filemtime(WPL_ROOT . '/assets/app.js') ?>" defer></script>
</body>
</html>
