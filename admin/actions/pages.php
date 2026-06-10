<?php
/** Pages: list + editor (?action=pages). */

defined('WPL_ADMIN') || exit;

$slug = $_GET['slug'] ?? '';
$editing = $slug !== '' ? wpl_get(WPL_PAGES, $slug) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!wpl_csrf_check()) {
        $err = 'Session expired — please retry.';
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $newSlug = slugify((string)($_POST['slug'] ?? '')) ?: slugify($title);
        $body = str_replace("\r\n", "\n", (string)($_POST['body'] ?? ''));
        $nav = array_values(array_intersect(WPL_NAV_LOCATIONS, (array)($_POST['nav'] ?? [])));
        $err = admin_validate_entry($editing['slug'] ?? null, $title, $newSlug,
            [WPL_POSTS],
            'Slug must be lowercase letters, numbers and hyphens.',
            'A post with slug "%s" already exists.');
        if ($err === '') {
            // 'none' keeps the page reachable by URL but out of every menu
            // ('' would serialize away and fall back to the footer default).
            $meta = ['title' => $title, 'nav' => $nav ? implode(' ', $nav) : 'none'];
            if (admin_write_entry(WPL_PAGES, $editing['slug'] ?? null, $newSlug, $meta, $body)) {
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
  <tr><th>Title</th><th>URL</th><th>Shown in</th></tr>
  <?php foreach (wpl_pages() as $p): ?>
    <tr>
      <td><a href="<?= esc(wpl_url('admin/?action=pages&slug=' . $p['slug'])) ?>"><?= esc($p['title']) ?></a></td>
      <td>/<?= esc($p['slug']) ?></td>
      <td><?= esc($p['nav'] ? implode(', ', $p['nav']) : 'unlisted') ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<h2><?= $editing ? 'Edit page' : 'New page' ?></h2>
<?php $editNav = $editing['nav'] ?? ['footer']; ?>
<form method="post">
  <?= admin_csrf_field() ?>
  <label>Title <input type="text" name="title" value="<?= esc($editing['title'] ?? '') ?>" required></label>
  <label>Slug (URL) <input type="text" name="slug" value="<?= esc($editing['slug'] ?? '') ?>" placeholder="auto from title"></label>
  <fieldset class="nav-locations">
    <legend>Show link in</legend>
    <?php foreach (['header' => 'Header (top bar)', 'side' => 'Side (profile card)', 'footer' => 'Footer'] as $loc => $label): ?>
      <label class="check"><input type="checkbox" name="nav[]" value="<?= $loc ?>"
        <?= in_array($loc, $editNav, true) ? 'checked' : '' ?>> <?= $label ?></label>
    <?php endforeach; ?>
    <small>Untick all to keep the page unlisted (reachable by URL only).</small>
  </fieldset>
  <label>Body (markdown) <textarea name="body"><?= esc($editing['body'] ?? '') ?></textarea></label>
  <button class="btn">Save page</button>
</form>
<?php
admin_foot();
