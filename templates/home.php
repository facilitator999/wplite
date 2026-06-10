<?php /** @var array $config  @var array $featured  @var array $batch  @var bool $hasMore  @var array $pages */ ?>
<div class="profile-layout">

<aside class="profile-card">
  <?php if (!empty($config['portrait']) && is_file(WPL_UPLOADS . '/' . basename($config['portrait']))): ?>
    <img class="portrait" src="<?= esc(wpl_upload_url($config['portrait'])) ?>" alt="Portrait of <?= esc($config['site_name']) ?>">
  <?php endif; ?>
  <h1><?= esc($config['site_name']) ?></h1>
  <p class="blurb"><?= esc($config['blurb']) ?></p>
  <?php if ($pages): ?>
    <nav class="profile-links" aria-label="Pages">
      <?php foreach ($pages as $p): ?>
        <a href="<?= esc(wpl_url($p['slug'])) ?>"><?= esc($p['title']) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
</aside>

<div class="feed">
  <?php if ($featured): ?>
  <section class="carousel" aria-label="Featured posts">
    <div class="carousel-track">
      <?php foreach ($featured as $f): ?>
        <a class="slide" href="<?= esc(wpl_url($f['slug'])) ?>">
          <?php if ($f['image'] !== ''): ?>
            <img src="<?= esc(wpl_upload_url($f['image'])) ?>" alt="" loading="lazy">
          <?php endif; ?>
          <span class="slide-title"><?= esc($f['title']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if (count($featured) > 1): ?>
      <div class="carousel-dots" role="tablist">
        <?php foreach ($featured as $i => $f): ?>
          <button class="dot<?= $i === 0 ? ' active' : '' ?>" data-index="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="grid" id="grid">
    <?php foreach ($batch as $p): ?>
      <a class="tile" href="<?= esc(wpl_url($p['slug'])) ?>">
        <?php if ($p['thumb'] !== ''): ?>
          <img src="<?= esc(wpl_upload_url($p['thumb'])) ?>" alt="" loading="lazy">
        <?php endif; ?>
        <span class="tile-title"><?= esc($p['title']) ?></span>
      </a>
    <?php endforeach; ?>
  </section>

  <template id="tile-template">
    <a class="tile"><img alt="" loading="lazy"><span class="tile-title"></span></a>
  </template>

  <?php if ($hasMore): ?>
  <div id="sentinel" data-offset="<?= count($batch) ?>" data-feed="<?= esc(wpl_url('feed.json')) ?>"></div>
  <?php endif; ?>
</div>

</div>
