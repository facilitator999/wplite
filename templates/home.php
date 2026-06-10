<?php /** @var array $config  @var array $featured  @var array $batch  @var bool $hasMore */ ?>
<?php if ($featured): ?>
<section class="carousel">
  <h2 class="sr-only">Featured posts</h2>
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
    <div class="carousel-dots" role="group" aria-label="Choose slide">
      <?php foreach ($featured as $i => $f): ?>
        <button type="button" class="dot<?= $i === 0 ? ' active' : '' ?>" data-index="<?= $i ?>"
          aria-label="Slide <?= $i + 1 ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>></button>
      <?php endforeach; ?>
    </div>
    <button type="button" class="carousel-pause" aria-pressed="false" aria-label="Pause slideshow">
      <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path class="icon-pause" d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/><path class="icon-play" d="M8 5l11 7-11 7z" style="display:none"/></svg>
    </button>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="grid-section">
<h2 class="sr-only">All posts</h2>
<div class="grid" id="grid">
  <?php foreach ($batch as $p): ?>
    <a class="tile" href="<?= esc(wpl_url($p['slug'])) ?>">
      <?php if ($p['thumb'] !== ''): ?>
        <img src="<?= esc(wpl_upload_url($p['thumb'])) ?>" alt="" loading="lazy">
      <?php endif; ?>
      <span class="tile-title"><?= esc($p['title']) ?></span>
    </a>
  <?php endforeach; ?>
</div>
</section>

<template id="tile-template">
  <a class="tile"><img alt="" loading="lazy"><span class="tile-title"></span></a>
</template>

<?php if ($hasMore): ?>
<div id="sentinel" data-offset="<?= count($batch) ?>" data-feed="<?= esc(wpl_url('feed.json')) ?>"></div>
<?php endif; ?>
