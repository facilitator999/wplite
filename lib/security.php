<?php
/**
 * WPlite security layer: sessions, login throttling, CSRF, and auth for both
 * per-site admins and the engine master (installer). Loaded by lib.php.
 *
 * Throttle/CSRF functions take a $scope — a site slug, or '_master' for the
 * installer. '_master' can never collide with a real site (slugs can't start
 * with an underscore). The default WPL_SITE is resolved at call time.
 */

declare(strict_types=1);

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

function wpl_throttle_file(?string $scope = null): string
{
    $dir = WPL_ROOT . '/data/throttle';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/' . md5(($scope ?? WPL_SITE) . '|' . wpl_client_ip()) . '.json';
}

/**
 * IP-level brute-force lockout (file-based, survives cookie-less clients):
 * 5 misses lock for 60s, doubling per further miss up to 1h. Counters reset
 * after a quiet day. Session-level lock kept as a second layer.
 */
function wpl_ip_lock_until(?string $scope = null): int
{
    $f = wpl_throttle_file($scope);
    if (!is_file($f)) {
        return 0;
    }
    $d = json_decode((string)@file_get_contents($f), true) ?: [];
    return (int)($d['until'] ?? 0);
}

function wpl_ip_fail(?string $scope = null): void
{
    $f = wpl_throttle_file($scope);
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

function wpl_lock_until(?string $scope = null): int
{
    wpl_session_start();
    return max((int)($_SESSION['wpl_lock'][$scope ?? WPL_SITE] ?? 0), wpl_ip_lock_until($scope));
}

/** Shared login core: verify $password against $hash under $scope's throttle. */
function wpl_check_login(string $scope, string $password, string $hash): bool
{
    wpl_session_start();
    if (wpl_lock_until($scope) > time()) {
        return false;
    }
    usleep(500000);
    if ($hash !== '' && password_verify($password, $hash)) {
        session_regenerate_id(true);
        $_SESSION['wpl_auth'][$scope] = true;
        unset($_SESSION['wpl_fails'][$scope], $_SESSION['wpl_lock'][$scope]);
        @unlink(wpl_throttle_file($scope));
        return true;
    }
    wpl_ip_fail($scope);
    $_SESSION['wpl_fails'][$scope] = ($_SESSION['wpl_fails'][$scope] ?? 0) + 1;
    if ($_SESSION['wpl_fails'][$scope] >= 5) {
        $_SESSION['wpl_lock'][$scope] = time() + 60;
        $_SESSION['wpl_fails'][$scope] = 0;
    }
    return false;
}

/** Returns true on success; throttles and locks out brute force, per site + per IP. */
function wpl_login(string $password): bool
{
    return wpl_check_login(WPL_SITE, $password, (string)(wpl_config()['admin_hash'] ?? ''));
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

function wpl_csrf_token(?string $scope = null): string
{
    wpl_session_start();
    $scope ??= WPL_SITE;
    if (empty($_SESSION['wpl_csrf'][$scope])) {
        $_SESSION['wpl_csrf'][$scope] = bin2hex(random_bytes(32));
    }
    return $_SESSION['wpl_csrf'][$scope];
}

function wpl_csrf_check(?string $scope = null): bool
{
    wpl_session_start();
    $scope ??= WPL_SITE;
    return isset($_POST['csrf'], $_SESSION['wpl_csrf'][$scope])
        && hash_equals($_SESSION['wpl_csrf'][$scope], (string)$_POST['csrf']);
}

/* ------------------------------------------------- engine master (installer) */

function wpl_master_file(): string
{
    return WPL_ROOT . '/data/master.php';
}

function wpl_master_exists(): bool
{
    return is_file(wpl_master_file());
}

function wpl_master_hash(): string
{
    $d = wpl_master_exists() ? require wpl_master_file() : [];
    return (string)(is_array($d) ? ($d['master_hash'] ?? '') : '');
}

function wpl_master_login(string $password): bool
{
    return wpl_check_login('_master', $password, wpl_master_hash());
}

function wpl_is_master(): bool
{
    wpl_session_start();
    return !empty($_SESSION['wpl_auth']['_master']);
}

function wpl_master_logout(): void
{
    wpl_session_start();
    unset($_SESSION['wpl_auth']['_master'], $_SESSION['wpl_csrf']['_master']);
    if (empty($_SESSION['wpl_auth'])) {
        $_SESSION = [];
        session_destroy();
    }
}
