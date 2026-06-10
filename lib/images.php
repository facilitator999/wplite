<?php
/**
 * WPlite image pipeline: upload validation + GD re-encoding (kills embedded
 * payloads, strips EXIF), thumbnail generation, and upload serving.
 * Loaded by lib.php.
 */

declare(strict_types=1);

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
