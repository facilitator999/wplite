<?php /** @var array $post */ ?>
<article class="post">
  <?php if ($post['image'] !== ''): ?>
    <img class="post-image" src="<?= esc(wpl_upload_url($post['image'])) ?>" alt="">
  <?php endif; ?>
  <h1><?= esc($post['title']) ?></h1>
  <?php if ($post['date'] !== ''): ?>
    <time datetime="<?= esc($post['date']) ?>"><?= esc(date('j F Y', strtotime($post['date']) ?: time())) ?></time>
  <?php endif; ?>
  <div class="post-body"><?= wpl_markdown($post['body']) ?></div>
  <?php if ($post['images']): ?>
    <div class="post-gallery">
      <?php foreach ($post['images'] as $img): ?>
        <img src="<?= esc(wpl_upload_url($img)) ?>" alt="" loading="lazy">
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <a class="back" href="<?= esc(wpl_url()) ?>">&larr; All posts</a>
</article>
