<?php /** Sticky profile card — left column of every view. @var array $config  @var array $pages  @var string $view */ ?>
<?php $nameTag = $view === 'home' ? 'h1' : 'p'; // posts/pages bring their own h1 ?>
<aside class="profile-card">
  <?php if (!empty($config['portrait']) && is_file(WPL_UPLOADS . '/' . basename($config['portrait']))): ?>
    <a href="<?= esc(wpl_url()) ?>"><img class="portrait" src="<?= esc(wpl_upload_url($config['portrait'])) ?>" alt="Portrait of <?= esc($config['site_name']) ?>"></a>
  <?php endif; ?>
  <<?= $nameTag ?> class="profile-name"><a href="<?= esc(wpl_url()) ?>"><?= esc($config['site_name']) ?></a></<?= $nameTag ?>>
  <p class="blurb"><?= esc($config['blurb']) ?></p>
  <?php $sidePages = wpl_pages_in('side', $pages); ?>
  <?php if ($sidePages): ?>
    <nav class="profile-links" aria-label="Pages">
      <?php foreach ($sidePages as $p): ?>
        <a href="<?= esc(wpl_url($p['slug'])) ?>"><?= esc($p['title']) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
</aside>
