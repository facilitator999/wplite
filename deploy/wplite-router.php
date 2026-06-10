<?php
/**
 * Plugin Name: WPlite Router
 * Description: Hands /wplite/* requests to the WPlite engine before WordPress
 *              routes them. Needed because this host runs nginx (no .htaccess):
 *              unmatched URLs fall through to WordPress, and this catches the
 *              ones that belong to WPlite. Do not remove while WPlite is live.
 */
$wplitePath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

// Legacy URLs from before the folder rename keep working.
if (preg_match('#^/wplight(/.*)?$#', $wplitePath, $m)) {
    header('Location: /wplite' . ($m[1] ?? '/'), true, 301);
    exit;
}

if (preg_match('#^/wplite(/|$)#', $wplitePath)) {
    define('WPL_BASE_OVERRIDE', '/wplite');
    require dirname(__DIR__, 2) . '/wplite/index.php';
    exit;
}
