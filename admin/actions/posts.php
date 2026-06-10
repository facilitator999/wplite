<?php
/** Posts: dashboard list (default action), editor (?action=edit), delete. */

defined('WPL_ADMIN') || exit;

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
            $excerpt = trim((string)($_POST['excerpt'] ?? ''));
            $draft = ($_POST['status'] ?? 'published') === 'draft';
            $image = $editing['image'] ?? '';
            $gallery = $editing['images'] ?? [];

            $err = admin_validate_entry($editing['slug'] ?? null, $title, $newSlug,
                [WPL_POSTS, WPL_PAGES],
                'Slug must be lowercase letters, numbers and hyphens (and not a reserved word).',
                'A post or page with slug "%s" already exists.');
            if ($err === '') {
                if (!empty($_FILES['image']['name'])) {
                    $up = admin_upload_image($_FILES['image']);
                    if ($up['err'] !== '') {
                        $err = $up['err'];
                    } else {
                        if ($image !== '') {
                            wpl_delete_images($image);
                        }
                        $image = $up['file'];
                    }
                }
                // Gallery: drop the ones ticked for removal, then add new uploads.
                foreach (array_map('basename', (array)($_POST['remove_images'] ?? [])) as $r) {
                    if (in_array($r, $gallery, true)) {
                        wpl_delete_images($r);
                        $gallery = array_values(array_diff($gallery, [$r]));
                    }
                }
                if ($err === '' && !empty($_FILES['gallery']['name'][0])) {
                    foreach ((array)$_FILES['gallery']['name'] as $i => $n) {
                        if ($n === '') {
                            continue;
                        }
                        $up = admin_upload_image([
                            'name' => $n,
                            'type' => $_FILES['gallery']['type'][$i],
                            'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                            'error' => $_FILES['gallery']['error'][$i],
                            'size' => $_FILES['gallery']['size'][$i],
                        ]);
                        if ($up['err'] !== '') {
                            $err = '"' . $n . '": ' . $up['err'];
                            break;
                        }
                        $gallery[] = $up['file'];
                    }
                }
            }
            if ($err === '') {
                $meta = [
                    'title' => $title,
                    'date' => $date,
                    'image' => $image,
                    'images' => implode(' ', $gallery),
                    'featured' => isset($_POST['featured']),
                    'draft' => $draft,
                    'excerpt' => $excerpt,
                ];
                if (admin_write_entry(WPL_POSTS, $editing['slug'] ?? null, $newSlug, $meta, $body)) {
                    header('Location: ' . wpl_url('admin/?saved=' . rawurlencode($newSlug)));
                    exit;
                }
                $err = 'Could not write the post file — check folder permissions.';
            }
            // Re-show submitted values on error.
            $editing = [
                'slug' => $newSlug, 'title' => $title, 'date' => $date,
                'image' => $image, 'images' => $gallery,
                'featured' => isset($_POST['featured']), 'draft' => $draft,
                'excerpt' => $excerpt, 'body' => $body,
            ];
        }
    }

    admin_head($editing ? 'Edit post' : 'New post', $config);
    admin_nav('posts');
    ?>
    <h1><?= $editing && $slug !== '' ? 'Edit post' : 'New post' ?></h1>
    <?php if ($err): ?><p class="msg err"><?= esc($err) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="editor-form">

      <div class="editor-main">
        <?= admin_csrf_field() ?>
        <label>Title <input type="text" name="title" value="<?= esc($editing['title'] ?? '') ?>" required autofocus></label>
        <label>Excerpt (short summary — used as the post's search/share description)
          <input type="text" name="excerpt" value="<?= esc($editing['excerpt'] ?? '') ?>" maxlength="200">
        </label>
        <label>Body (markdown)
          <textarea name="body"><?= esc($editing['body'] ?? '') ?></textarea>
          <small>Tip: paste a YouTube link on its own line and it becomes an embedded video player.</small>
        </label>
      </div>

      <aside class="editor-side">
        <fieldset class="settings-group">
          <legend>Publish</legend>
          <label>Status
            <select name="status">
              <option value="published">Published</option>
              <option value="draft" <?= !empty($editing['draft']) ? 'selected' : '' ?>>Draft (hidden from the site)</option>
            </select>
          </label>
          <label>Date <input type="date" name="date" value="<?= esc($editing['date'] ?? date('Y-m-d')) ?>"></label>
          <label class="check"><input type="checkbox" name="featured" <?= !empty($editing['featured']) ? 'checked' : '' ?>> Featured (show in carousel)</label>
          <label>Slug (URL) <input type="text" name="slug" value="<?= esc($editing['slug'] ?? '') ?>" placeholder="auto from title"></label>
          <button class="btn">Save post</button>
        </fieldset>

        <fieldset class="settings-group">
          <legend>Images</legend>
          <label>Featured image <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-preview="preview-featured">
            <img id="preview-featured" class="image-preview" alt=""
              <?php if (!empty($editing['image'])): ?>src="<?= esc(wpl_upload_url('thumb_' . preg_replace('/^img_/', '', $editing['image']))) ?>"<?php else: ?>hidden<?php endif; ?>>
          </label>
          <label>Gallery (shown in the post — select several at once)
            <input type="file" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple>
          </label>
          <?php if (!empty($editing['images'])): ?>
            <div class="gallery-manage">
              <?php foreach ($editing['images'] as $img): ?>
                <label class="gallery-item">
                  <img src="<?= esc(wpl_upload_url('thumb_' . preg_replace('/^img_/', '', $img))) ?>" alt="">
                  <span class="check"><input type="checkbox" name="remove_images[]" value="<?= esc($img) ?>"> remove</span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </fieldset>
      </aside>

    </form>
    <?php
    admin_foot();
    exit;
}

/* ----------------------------------------------------------------- delete */

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (wpl_csrf_check()) {
        $slug = (string)($_POST['slug'] ?? '');
        $post = wpl_get(WPL_POSTS, $slug);
        if ($post !== null) {
            if ($post['image'] !== '') {
                wpl_delete_images($post['image']);
            }
            foreach ($post['images'] as $img) {
                wpl_delete_images($img);
            }
            @unlink(WPL_POSTS . "/$slug.md");
        }
    }
    header('Location: ' . wpl_url('admin/'));
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
  <tr><th>Title</th><th>Date</th><th>Status</th><th>Featured</th><th></th></tr>
  <?php foreach (wpl_posts(true) as $p): ?>
    <tr>
      <td><a href="<?= esc(wpl_url('admin/?action=edit&slug=' . $p['slug'])) ?>"><?= esc($p['title']) ?></a></td>
      <td><?= esc($p['date']) ?></td>
      <td><?= $p['draft'] ? '<span class="badge-draft">Draft</span>' : 'Published' ?></td>
      <td><?= $p['featured'] ? '★' : '' ?></td>
      <td>
        <form method="post" action="<?= esc(wpl_url('admin/?action=delete')) ?>"
              onsubmit="return confirm('Delete &quot;<?= esc($p['title']) ?>&quot;?')">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="slug" value="<?= esc($p['slug']) ?>">
          <button class="btn btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php
admin_foot();
