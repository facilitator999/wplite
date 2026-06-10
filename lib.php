<?php
/**
 * WPlite engine: multi-tenant site resolution, config, front-matter posts,
 * escaping, auth, CSRF, image pipeline, upload serving.
 *
 * One engine install serves many sites. Each site lives in sites/{slug}/
 * (config.php + content/ + uploads/) and is reached three ways:
 *   domain mode — the client's domain points at this install ('domains' in config)
 *   path mode   — preview at {engine-host}/wplite/s/{slug}/
 *   root mode   — fallback site served unprefixed at /wplite/ (demo)
 */

declare(strict_types=1);

define('WPL_ROOT', __DIR__);

// Hosts where /s/{slug} previews are honored (never matched to a tenant domain).
const WPL_ENGINE_HOSTS = ['aqntech.com', 'localhost', '127.0.0.1'];

// Slugs that can never be post/page names (they are routes or real dirs).
const WPL_RESERVED = ['admin', 'assets', 'content', 'uploads', 'templates', 'sites', 's', 'wplite', 'throttle',
    'feed.json', 'index.php', 'config.php', 'lib.php', 'seed.php', 'Parsedown.php'];

// Serve images as direct static files from sites/{slug}/uploads/ (fast path for
// nginx hosts, where .htaccess can't block the dir anyway). Set false on
// Apache/LiteSpeed hosts where sites/.htaccess denies direct access.
const WPL_STATIC_UPLOADS = true;

// Names a site folder may never use.
const WPL_RESERVED_SITE_SLUGS = ['s', 'admin', 'assets', 'sites', 'content', 'uploads', 'templates', 'www'];

function wpl_valid_site_slug(string $slug): bool
{
    return preg_match('/^[a-z0-9][a-z0-9-]{1,29}$/', $slug) === 1
        && !in_array($slug, WPL_RESERVED_SITE_SLUGS, true);
}

/** Base URL path of the engine install, e.g. "/wplite" — "" on addon-domain docroots. */
function wpl_base(): string
{
    // Set by the WordPress mu-plugin that hands /wplite/* requests to this
    // engine on nginx hosts (SCRIPT_NAME is the WP front controller there).
    if (defined('WPL_BASE_OVERRIDE')) {
        return WPL_BASE_OVERRIDE;
    }
    // CLI has no base; the cli-server router makes SCRIPT_NAME mirror the
    // request path, and its docroot is this folder, so base is '' there too.
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') {
        return '';
    }
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return str_ends_with($base, '/admin') ? substr($base, 0, -6) : $base;
}

/**
 * Decide which site this request belongs to. Domain match wins (and disables
 * /s/ previews on client domains); then /s/{slug} path previews; then demo.
 * Defines WPL_SITE, WPL_MODE, WPL_SITE_PREFIX, WPL_SITE_DIR and the content dirs.
 */
function wpl_resolve_site(): void
{
    $site = null;
    $mode = 'root';
    $prefix = '';

    if (PHP_SAPI === 'cli') {
        $site = defined('WPL_FORCE_SITE') && wpl_valid_site_slug(WPL_FORCE_SITE) ? WPL_FORCE_SITE : 'demo';
    } else {
        $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/^www\./', '', $host);

        if ($host !== '' && !in_array($host, WPL_ENGINE_HOSTS, true)) {
            foreach (glob(WPL_ROOT . '/sites/*/config.php') ?: [] as $cf) {
                $slug = basename(dirname($cf));
                if (!wpl_valid_site_slug($slug)) {
                    continue;
                }
                try {
                    $cfg = require $cf;
                } catch (Throwable) {
                    continue; // one broken tenant config must not take down the engine
                }
                if (!is_array($cfg)) {
                    continue;
                }
                foreach ((array)($cfg['domains'] ?? []) as $d) {
                    $d = strtolower(preg_replace('/^www\./', '', trim((string)$d)));
                    if ($d !== '' && $d === $host) {
                        $site = $slug;
                        $mode = 'domain';
                        break 2;
                    }
                }
            }
        }

        if ($site === null) {
            $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
            $base = wpl_base();
            if ($base !== '' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
            }
            if (preg_match('#^/s/([a-z0-9][a-z0-9-]{1,29})(/|$)#', $path, $m)
                && wpl_valid_site_slug($m[1])
                && is_file(WPL_ROOT . '/sites/' . $m[1] . '/config.php')) {
                $site = $m[1];
                $mode = 'path';
                $prefix = '/s/' . $m[1];
            }
        }

        if ($site === null || !is_dir(WPL_ROOT . '/sites/' . $site)) {
            $site = 'demo';
            $mode = 'root';
            $prefix = '';
        }
    }

    define('WPL_SITE', $site);
    define('WPL_MODE', $mode);
    define('WPL_SITE_PREFIX', $prefix);
    define('WPL_SITE_DIR', WPL_ROOT . '/sites/' . $site);
    define('WPL_POSTS', WPL_SITE_DIR . '/content/posts');
    define('WPL_PAGES', WPL_SITE_DIR . '/content/pages');
    define('WPL_UPLOADS', WPL_SITE_DIR . '/uploads');
}

function wpl_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require WPL_SITE_DIR . '/config.php';
    }
    return $config;
}

function esc(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** Base URL of the CURRENT SITE: engine base + site prefix. */
function wpl_site_base(): string
{
    return wpl_base() . WPL_SITE_PREFIX;
}

function wpl_url(string $path = ''): string
{
    return wpl_site_base() . '/' . ltrim($path, '/');
}

/** Public URL of an uploaded image — direct static path or the PHP route. */
function wpl_upload_url(string $file): string
{
    $file = basename($file);
    return WPL_STATIC_UPLOADS
        ? wpl_base() . '/sites/' . WPL_SITE . '/uploads/' . $file
        : wpl_url('uploads/' . $file);
}

/** Route within the current site: URI minus engine base minus site prefix. */
function wpl_route(): string
{
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
    $base = wpl_base();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    if (WPL_SITE_PREFIX !== '' && str_starts_with($path, WPL_SITE_PREFIX)) {
        $path = substr($path, strlen(WPL_SITE_PREFIX));
    }
    $route = trim($path, '/');
    if ($route === '' && isset($_GET['p'])) {
        $route = trim((string)$_GET['p'], '/');
    }
    if ($route === 'index.php') {
        $route = '';
    }
    return $route;
}

/* ---------------------------------------------------------------- content */

/**
 * Parse a front-matter markdown file into ['meta' => [...], 'body' => string].
 * Front matter is flat "key: value" lines between two "---" lines.
 */
function wpl_parse_file(string $path): ?array
{
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $meta = [];
    $body = $raw;
    if (str_starts_with($raw, "---")) {
        $end = strpos($raw, "\n---", 3);
        if ($end !== false) {
            $block = substr($raw, 3, $end - 3);
            $body = ltrim(substr($raw, $end + 4), "\r\n");
            foreach (preg_split('/\R/', $block) as $line) {
                if (preg_match('/^([A-Za-z_][\w-]*):\s*(.*)$/', trim($line), $m)) {
                    $meta[strtolower($m[1])] = trim($m[2]);
                }
            }
        }
    }
    return ['meta' => $meta, 'body' => $body];
}

function wpl_valid_slug(string $slug): bool
{
    return preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/', $slug) === 1
        && !in_array($slug, WPL_RESERVED, true);
}

/** Load one post (or page) by slug; returns null when absent. */
function wpl_get(string $dir, string $slug): ?array
{
    if (!wpl_valid_slug($slug)) {
        return null;
    }
    $path = $dir . '/' . $slug . '.md';
    if (!is_file($path)) {
        return null;
    }
    $doc = wpl_parse_file($path);
    if ($doc === null) {
        return null;
    }
    return wpl_hydrate($slug, $doc);
}

function wpl_hydrate(string $slug, array $doc): array
{
    $m = $doc['meta'];
    $image = basename($m['image'] ?? '');
    return [
        'slug' => $slug,
        'title' => $m['title'] ?? $slug,
        'date' => $m['date'] ?? '',
        'image' => $image,
        'thumb' => $image !== '' ? 'thumb_' . preg_replace('/^img_/', '', $image) : '',
        'featured' => in_array(strtolower($m['featured'] ?? ''), ['true', 'yes', '1'], true),
        'excerpt' => $m['excerpt'] ?? mb_substr(trim(strip_tags($doc['body'])), 0, 140),
        'body' => $doc['body'],
    ];
}

/** All posts, newest first. Parses front matter only (body kept — files are small). */
function wpl_posts(): array
{
    $posts = [];
    foreach (glob(WPL_POSTS . '/*.md') ?: [] as $path) {
        $slug = basename($path, '.md');
        if (!wpl_valid_slug($slug)) {
            continue;
        }
        $doc = wpl_parse_file($path);
        if ($doc !== null) {
            $posts[] = wpl_hydrate($slug, $doc);
        }
    }
    usort($posts, fn($a, $b) => strcmp($b['date'], $a['date']) ?: strcmp($a['slug'], $b['slug']));
    return $posts;
}

function wpl_pages(): array
{
    $pages = [];
    foreach (glob(WPL_PAGES . '/*.md') ?: [] as $path) {
        $slug = basename($path, '.md');
        if (!wpl_valid_slug($slug)) {
            continue;
        }
        $doc = wpl_parse_file($path);
        if ($doc !== null) {
            $pages[] = wpl_hydrate($slug, $doc);
        }
    }
    usort($pages, fn($a, $b) => strcmp($a['slug'], $b['slug']));
    return $pages;
}

function wpl_markdown(string $text): string
{
    static $parser = null;
    if ($parser === null) {
        require_once WPL_ROOT . '/Parsedown.php';
        $parser = new Parsedown();
        $parser->setSafeMode(true);
    }
    return $parser->text($text);
}

/** Atomic write: temp file in same dir + rename. */
function wpl_write(string $path, string $data): bool
{
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $data, LOCK_EX) === false) {
        return false;
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/* ----------------------------------------------------------- upload serving */

/**
 * Stream a file from the current site's uploads dir with caching headers.
 * Random-id img_/thumb_ files are immutable; portrait/logo names are not.
 */
function wpl_serve_upload(string $name): void
{
    $name = basename($name);
    $types = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
    ];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $path = WPL_UPLOADS . '/' . $name;
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $name) || !isset($types[$ext]) || !is_file($path)) {
        http_response_code(404);
        header('Content-Type: text/plain');
        exit('Not found');
    }
    $mtime = filemtime($path);
    $size = filesize($path);
    $etag = sprintf('"%x-%x"', $mtime, $size);
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Cache-Control: ' . (preg_match('/^(img|thumb)_/', $name)
        ? 'public, max-age=31536000, immutable'
        : 'public, max-age=86400'));
    header('X-Content-Type-Options: nosniff');
    if ($ext === 'svg') {
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
    }
    $ims = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '') ?: 0;
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag || $ims >= $mtime) {
        http_response_code(304);
        exit;
    }
    header('Content-Type: ' . $types[$ext]);
    header('Content-Length: ' . $size);
    readfile($path);
    exit;
}

/* ------------------------------------------------------------- admin auth */

function wpl_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('wplite_sid');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'path' => wpl_base() . '/',
    ]);
    session_start();
}

function wpl_is_authed(): bool
{
    wpl_session_start();
    return !empty($_SESSION['wpl_auth'][WPL_SITE]);
}

/**
 * Client IP for throttling. Cloudflare fronts this host, so prefer its header;
 * REMOTE_ADDR is mixed in so direct-to-origin requests can't spoof a clean slate.
 */
function wpl_client_ip(): string
{
    return ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '') . '|' . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function wpl_throttle_file(): string
{
    $dir = WPL_ROOT . '/throttle';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755);
    }
    return $dir . '/' . md5(WPL_SITE . '|' . wpl_client_ip()) . '.json';
}

/**
 * IP-level brute-force lockout (file-based, survives cookie-less clients):
 * 5 misses lock for 60s, doubling per further miss up to 1h. Counters reset
 * after a quiet day. Session-level lock kept as a second layer.
 */
function wpl_ip_lock_until(): int
{
    $f = wpl_throttle_file();
    if (!is_file($f)) {
        return 0;
    }
    $d = json_decode((string)@file_get_contents($f), true) ?: [];
    return (int)($d['until'] ?? 0);
}

function wpl_ip_fail(): void
{
    $f = wpl_throttle_file();
    $d = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
    if (($d['ts'] ?? 0) < time() - 86400) {
        $d = [];
    }
    $d['count'] = ($d['count'] ?? 0) + 1;
    $d['ts'] = time();
    if ($d['count'] >= 5) {
        $d['until'] = time() + min(3600, 60 * (2 ** min(6, $d['count'] - 5)));
    }
    @file_put_contents($f, json_encode($d), LOCK_EX);
}

function wpl_lock_until(): int
{
    wpl_session_start();
    return max((int)($_SESSION['wpl_lock'][WPL_SITE] ?? 0), wpl_ip_lock_until());
}

/** Returns true on success; throttles and locks out brute force, per site + per IP. */
function wpl_login(string $password): bool
{
    wpl_session_start();
    if (wpl_lock_until() > time()) {
        return false;
    }
    usleep(500000);
    $hash = (string)(wpl_config()['admin_hash'] ?? '');
    if ($hash !== '' && password_verify($password, $hash)) {
        session_regenerate_id(true);
        $_SESSION['wpl_auth'][WPL_SITE] = true;
        unset($_SESSION['wpl_fails'][WPL_SITE], $_SESSION['wpl_lock'][WPL_SITE]);
        @unlink(wpl_throttle_file());
        return true;
    }
    wpl_ip_fail();
    $_SESSION['wpl_fails'][WPL_SITE] = ($_SESSION['wpl_fails'][WPL_SITE] ?? 0) + 1;
    if ($_SESSION['wpl_fails'][WPL_SITE] >= 5) {
        $_SESSION['wpl_lock'][WPL_SITE] = time() + 60;
        $_SESSION['wpl_fails'][WPL_SITE] = 0;
    }
    return false;
}

/** Log out of THIS site only — other previewed sites' logins survive. */
function wpl_logout(): void
{
    wpl_session_start();
    unset($_SESSION['wpl_auth'][WPL_SITE], $_SESSION['wpl_csrf'][WPL_SITE]);
    if (empty($_SESSION['wpl_auth'])) {
        $_SESSION = [];
        session_destroy();
    }
}

function wpl_csrf_token(): string
{
    wpl_session_start();
    if (empty($_SESSION['wpl_csrf'][WPL_SITE])) {
        $_SESSION['wpl_csrf'][WPL_SITE] = bin2hex(random_bytes(32));
    }
    return $_SESSION['wpl_csrf'][WPL_SITE];
}

function wpl_csrf_check(): bool
{
    wpl_session_start();
    return isset($_POST['csrf'], $_SESSION['wpl_csrf'][WPL_SITE])
        && hash_equals($_SESSION['wpl_csrf'][WPL_SITE], (string)$_POST['csrf']);
}

/* ----------------------------------------------------------------- images */

/**
 * Validate + re-encode an uploaded image. Writes img_{id}.jpg (max 1600px wide)
 * and thumb_{id}.jpg (640x640 center crop). Returns "img_{id}.jpg" or an
 * error string prefixed with "error:".
 */
function wpl_process_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'error:upload failed (code ' . ($file['error'] ?? '?') . ')';
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return 'error:image larger than 10 MB';
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return 'error:only JPEG, PNG or WebP images allowed';
    }
    $raw = file_get_contents($file['tmp_name']);
    $dims = getimagesizefromstring($raw);
    if ($dims === false || $dims[0] < 1 || $dims[0] > 12000 || $dims[1] > 12000) {
        return 'error:not a valid image';
    }
    $src = @imagecreatefromstring($raw);
    if ($src === false) {
        return 'error:could not decode image';
    }

    $id = bin2hex(random_bytes(6));
    $w = imagesx($src);
    $h = imagesy($src);

    // Full image, max 1600px wide, flattened to white.
    $fw = min($w, 1600);
    $fh = (int)round($h * $fw / $w);
    $full = imagecreatetruecolor($fw, $fh);
    imagefill($full, 0, 0, imagecolorallocate($full, 255, 255, 255));
    imagecopyresampled($full, $src, 0, 0, 0, 0, $fw, $fh, $w, $h);
    imagejpeg($full, WPL_UPLOADS . "/img_$id.jpg", 82);
    imagedestroy($full);

    // 640x640 center-crop thumbnail.
    $side = min($w, $h);
    $sx = (int)(($w - $side) / 2);
    $sy = (int)(($h - $side) / 2);
    $thumb = imagecreatetruecolor(640, 640);
    imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
    imagecopyresampled($thumb, $src, 0, 0, $sx, $sy, 640, 640, $side, $side);
    imagejpeg($thumb, WPL_UPLOADS . "/thumb_$id.jpg", 78);
    imagedestroy($thumb);
    imagedestroy($src);

    return "img_$id.jpg";
}

function wpl_delete_images(string $image): void
{
    $image = basename($image);
    if (preg_match('/^img_[0-9a-f]+\.jpg$/', $image)) {
        @unlink(WPL_UPLOADS . '/' . $image);
        @unlink(WPL_UPLOADS . '/thumb_' . substr($image, 4));
    }
}

/* ----------------------------------------------------------------- saving */

/** Serialize meta + body back to front-matter markdown. */
function wpl_serialize(array $meta, string $body): string
{
    $out = "---\n";
    foreach ($meta as $k => $v) {
        if ($v === '' || $v === null || $v === false) {
            continue;
        }
        $v = $v === true ? 'true' : str_replace(["\r", "\n"], ' ', (string)$v);
        $out .= "$k: $v\n";
    }
    return $out . "---\n" . rtrim($body) . "\n";
}

/** Rewrite the current site's config.php, then drop it from OPcache. */
function wpl_save_config(array $config): bool
{
    $php = "<?php\n// WPlite site configuration. The admin Settings page rewrites this file.\nreturn "
        . var_export($config, true) . ";\n";
    $ok = wpl_write(WPL_SITE_DIR . '/config.php', $php);
    if ($ok && function_exists('opcache_invalidate')) {
        opcache_invalidate(WPL_SITE_DIR . '/config.php', true);
    }
    return $ok;
}

wpl_resolve_site();
