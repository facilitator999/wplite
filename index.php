<?php
// php -S router shim: emulate the live .htaccess on the CLI dev server.
if (PHP_SAPI === 'cli-server') {
    $p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // like live nginx: images under sites/*/uploads/ are public statics
    if (preg_match('#^/sites/[a-z0-9-]+/uploads/[\w.-]+\.(jpe?g|png|webp|gif|svg)$#', $p) && is_file(__DIR__ . $p)) {
        return false;
    }
    if (str_starts_with($p, '/sites/') || preg_match('#^/(config|lib|seed|Parsedown)\.php$#', $p)) {
        http_response_code(403);
        exit('Forbidden');
    }
    // path-mode aliases that the live rewrites provide
    if (preg_match('#^/s/[a-z0-9][a-z0-9-]*/assets/([\w.-]+)$#', $p, $m) && is_file(__DIR__ . '/assets/' . $m[1])) {
        $mime = ['css' => 'text/css', 'js' => 'application/javascript'][pathinfo($m[1], PATHINFO_EXTENSION)] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        readfile(__DIR__ . '/assets/' . $m[1]);
        exit;
    }
    if (preg_match('#^/s/[a-z0-9][a-z0-9-]*/admin(/(index\.php)?)?$#', $p)) {
        require __DIR__ . '/admin/index.php';
        exit;
    }
    if ($p !== '/' && is_file(__DIR__ . $p)) {
        return false;
    }
}

require_once __DIR__ . '/lib.php';

$config = wpl_config();
$route = wpl_route();

/* uploads — images served through PHP from the current site's folder */
if (preg_match('#^uploads/([^/]+)$#', $route, $m)) {
    wpl_serve_upload($m[1]);
}

/* admin — reached here when no web-server rewrite exists (nginx + mu-plugin) */
if ($route === 'admin' || str_starts_with($route, 'admin/')) {
    require __DIR__ . '/admin/index.php';
    exit;
}

/* assets — same: serve through PHP when the request was routed to us */
if (preg_match('#^assets/([\w.-]+)$#', $route, $m) && is_file(__DIR__ . '/assets/' . $m[1])) {
    $mime = ['css' => 'text/css', 'js' => 'application/javascript'][pathinfo($m[1], PATHINFO_EXTENSION)] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    readfile(__DIR__ . '/assets/' . $m[1]);
    exit;
}

/* built-in agency page — engine-level, exists on every site, not deletable */
if ($route === 'wplite') {
    $pages = wpl_pages();
    $view = 'credit';
    require __DIR__ . '/templates/layout.php';
    exit;
}

/* feed.json — next batch of grid tiles for the endless scroll */
if ($route === 'feed.json') {
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $batch = max(1, (int)$config['batch_size']);
    $posts = wpl_posts();
    $slice = array_slice($posts, $offset, $batch);
    header('Content-Type: application/json');
    echo json_encode([
        'posts' => array_map(fn($p) => [
            'url' => wpl_url($p['slug']),
            'thumb' => $p['thumb'] !== '' ? wpl_upload_url($p['thumb']) : '',
            'title' => $p['title'],
        ], $slice),
        'hasMore' => $offset + $batch < count($posts),
    ]);
    exit;
}

/* home */
if ($route === '') {
    $posts = wpl_posts();
    $featured = array_values(array_filter($posts, fn($p) => $p['featured']));
    $batch = array_slice($posts, 0, max(1, (int)$config['batch_size']));
    $hasMore = count($posts) > count($batch);
    $pages = wpl_pages();
    $view = 'home';
    require __DIR__ . '/templates/layout.php';
    exit;
}

/* post, then page, then 404 */
$post = wpl_get(WPL_POSTS, $route);
if ($post !== null) {
    $pages = wpl_pages();
    $view = 'post';
    require __DIR__ . '/templates/layout.php';
    exit;
}

$page = wpl_get(WPL_PAGES, $route);
if ($page !== null) {
    $pages = wpl_pages();
    $view = 'page';
    require __DIR__ . '/templates/layout.php';
    exit;
}

http_response_code(404);
$pages = wpl_pages();
$view = '404';
require __DIR__ . '/templates/layout.php';
