<?php /** @var ?array $page */ ?>
<article class="page">
  <?php if (isset($page)): ?>
    <h1><?= esc($page['title']) ?></h1>
    <div class="post-body"><?= wpl_markdown($page['body']) ?></div>
  <?php else: ?>
    <h1>Page not found</h1>
    <p>Sorry, that page doesn&rsquo;t exist.</p>
  <?php endif; ?>
  <a class="back" href="<?= esc(wpl_url()) ?>">&larr; Home</a>
</article>
