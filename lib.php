<?php
/**
 * WPlite engine core: multi-tenant site resolution, config, front-matter
 * posts, escaping, markdown. Auth/CSRF/throttle live in lib/security.php,
 * the image pipeline in lib/images.php (both loaded below).
 *
 * One engine install serves many sites. Each site's content + uploads live in
 * sites/{slug}/, its config (incl. admin password hash) in the web-blocked
 * data/{slug}/config.php. A site is reached three ways:
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
    'install', 'data', 'lib',
    'feed.json', 'index.php', 'config.php', 'lib.php', 'seed.php', 'install.php', 'Parsedown.php'];

// Serve images as direct static files from sites/{slug}/uploads/ (fast path for
// nginx hosts, where .htaccess can't block the dir anyway). Set false on
// Apache/LiteSpeed hosts where sites/.htaccess denies direct access.
const WPL_STATIC_UPLOADS = true;

// Names a site folder may never use.
const WPL_RESERVED_SITE_SLUGS = ['s', 'admin', 'assets', 'sites', 'content', 'uploads', 'templates', 'www',
    'data', 'install', 'lib', 'throttle', 'master'];

function wpl_valid_site_slug(string $slug): bool
{
    return preg_match('/^[a-z0-9][a-z0-9-]{1,29}$/', $slug) === 1
        && !in_array($slug, WPL_RESERVED_SITE_SLUGS, true);
}

/** A site exists iff its config exists here — sites/{slug}/ holds only content + uploads. */
function wpl_site_config_file(string $slug): string
{
    return WPL_ROOT . '/data/' . $slug . '/config.php';
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
            foreach (glob(WPL_ROOT . '/data/*/config.php') ?: [] as $cf) {
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
                && is_file(wpl_site_config_file($m[1]))) {
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
    define('WPL_DATA_DIR', WPL_ROOT . '/data/' . $site);
    define('WPL_POSTS', WPL_SITE_DIR . '/content/posts');
    define('WPL_PAGES', WPL_SITE_DIR . '/content/pages');
    define('WPL_UPLOADS', WPL_SITE_DIR . '/uploads');
}

function wpl_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require WPL_DATA_DIR . '/config.php';
    }
    return $config;
}

/** Fresh config for a new site; values mirror the original _template config. */
function wpl_default_config(string $siteName = 'New Client', string $adminHash = ''): array
{
    return [
        'site_name' => $siteName,
        'blurb' => '',
        'portrait' => '',
        'logo' => '',
        'accent' => '#c2185b',
        'socials' => [
            'instagram' => '',
            'x' => '',
            'facebook' => '',
            'youtube' => '',
            'email' => '',
        ],
        'domains' => [],
        'batch_size' => 12,
        'admin_hash' => $adminHash, // empty hash = admin locked, nobody can log in
    ];
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
        'images' => array_values(array_filter(array_map('basename',
            preg_split('/\s+/', $m['images'] ?? '', -1, PREG_SPLIT_NO_EMPTY)))),
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

/** Extract a YouTube video id from a watch/share/shorts/embed URL, or null. */
function wpl_youtube_id(string $url): ?string
{
    if (preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?v=|shorts/|live/|embed/)|youtu\.be/)([\w-]{6,20})#', trim($url), $m)) {
        return $m[1];
    }
    return null;
}

function wpl_markdown(string $text): string
{
    static $parser = null;
    if ($parser === null) {
        require_once WPL_ROOT . '/Parsedown.php';
        $parser = new Parsedown();
        $parser->setSafeMode(true);
    }

    // A YouTube link alone on a line becomes an embedded player. Tokenize
    // before parsing (safe mode would escape an iframe), swap in after.
    $embeds = [];
    $lines = preg_split('/\R/', $text);
    foreach ($lines as $i => $line) {
        $id = wpl_youtube_id(trim($line));
        if ($id !== null) {
            $token = 'WPLYTEMBED' . count($embeds) . 'X';
            $embeds[$token] = '<div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/'
                . esc($id) . '" title="YouTube video" loading="lazy"'
                . ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"'
                . ' allowfullscreen></iframe></div>';
            $lines[$i] = $token;
        }
    }

    $html = $parser->text(implode("\n", $lines));
    foreach ($embeds as $token => $embed) {
        $html = str_replace(['<p>' . $token . '</p>', $token], [$embed, $embed], $html);
    }
    return $html;
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

/** Serialize + atomically write data/{slug}/config.php, then drop it from OPcache. */
function wpl_write_site_config(string $slug, array $config): bool
{
    $file = wpl_site_config_file($slug);
    if (!is_dir(dirname($file)) && !@mkdir(dirname($file), 0755, true)) {
        return false;
    }
    $php = "<?php\n// WPlite site configuration. The admin Settings page rewrites this file.\nreturn "
        . var_export($config, true) . ";\n";
    $ok = wpl_write($file, $php);
    if ($ok && function_exists('opcache_invalidate')) {
        opcache_invalidate($file, true);
    }
    return $ok;
}

/** Rewrite the current site's config. */
function wpl_save_config(array $config): bool
{
    return wpl_write_site_config(WPL_SITE, $config);
}

require __DIR__ . '/lib/security.php';
require __DIR__ . '/lib/images.php';

wpl_resolve_site();
