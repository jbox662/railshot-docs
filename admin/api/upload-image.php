<?php
/**
 * RailShot TV — Venue image upload endpoint
 * POST /admin/api/upload-image.php
 * Accepts: multipart/form-data with field "image"
 * Returns: { ok: true, url: "/images/venues/..." }
 */
require_once dirname(__DIR__, 2) . '/api/bootstrap.php';

if (!railshot_admin_exists()) {
    railshot_json_response(['error' => 'Admin not configured'], 503);
}

railshot_require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    railshot_json_response(['error' => 'Method not allowed'], 405);
}

if (empty($_FILES['image'])) {
    railshot_json_response(['error' => 'No file uploaded'], 400);
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
    ];
    $msg = $errors[$file['error']] ?? 'Upload error code ' . $file['error'];
    railshot_json_response(['error' => $msg], 400);
}

// Validate file size (max 5 MB)
$maxBytes = 5 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    railshot_json_response(['error' => 'File is too large. Maximum size is 5 MB.'], 400);
}

// Validate MIME type using finfo (not just the browser-supplied type)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime, $allowedMimes, true)) {
    railshot_json_response(['error' => 'Invalid file type. Allowed: JPG, PNG, WebP, GIF.'], 400);
}

// Map MIME to extension
$extMap = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];
$ext = $extMap[$mime];

// Determine save directory — relative to the web root
// Assumes this file lives at <webroot>/admin/api/upload-image.php
$webRoot = dirname(__DIR__, 2);
$saveDir = $webRoot . '/images/venues';

if (!is_dir($saveDir)) {
    if (!mkdir($saveDir, 0755, true)) {
        railshot_json_response(['error' => 'Could not create upload directory.'], 500);
    }
}

// Generate a unique filename
$filename = 'venue-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
$savePath = $saveDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $savePath)) {
    railshot_json_response(['error' => 'Failed to save uploaded file.'], 500);
}

$publicUrl = '/images/venues/' . $filename;
railshot_json_response(['ok' => true, 'url' => $publicUrl]);
