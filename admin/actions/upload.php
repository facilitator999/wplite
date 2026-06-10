<?php
/**
 * JSON endpoint for in-editor image uploads (?action=upload). EasyMDE posts
 * the file as "image" + the CSRF token, and expects
 * {"data":{"filePath":…}} on success or {"error":…}.
 * The image goes through the same GD re-encode pipeline as all uploads.
 */

defined('WPL_ADMIN') || exit;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !wpl_csrf_check()) {
    http_response_code(403);
    echo json_encode(['error' => 'Session expired — reload and retry.']);
    exit;
}

$result = wpl_process_upload($_FILES['image'] ?? []);
if (str_starts_with($result, 'error:')) {
    http_response_code(422);
    echo json_encode(['error' => substr($result, 6)]);
    exit;
}

echo json_encode(['data' => ['filePath' => wpl_upload_url($result)]]);
exit;
