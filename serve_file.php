<?php
/**
 * serve_file.php
 * Secure file-serving endpoint for uploaded attachments.
 *
 * This proxy bypasses the document-root / URL-path mismatch between
 * localhost (IIS, wwwroot) and Hostinger (Apache, public_html/EndDev).
 *
 * Usage: /EndDev/serve_file.php?file=uploads/makeup_attachments/makeup_1_xxx.jpg
 *        (also accepts legacy 'EndDev/uploads/...' prefix for backward compatibility)
 */

// Allowed directories - only files inside these relative paths may be served
$ALLOWED_DIRS = [
    'uploads/makeup_attachments',
    'uploads/leave_attachments',
    'staffmanagement/leave_attachments',
    'uploads/profile_photos',
    'profile_photos',
];

$rel_path = $_GET['file'] ?? '';

// --- Validation ---
if (empty($rel_path)) {
    http_response_code(400);
    exit('Missing file parameter.');
}

// Block directory traversal
if (strpos($rel_path, '..') !== false || strpos($rel_path, "\0") !== false) {
    http_response_code(403);
    exit('Forbidden.');
}

// Strip legacy 'EndDev/' prefix (stored by old versions of makeup_class_api.php)
$rel_path = preg_replace('#^EndDev[\\/]+#i', '', ltrim($rel_path, '/'));

// Ensure the path falls inside an allowed directory
$allowed = false;
foreach ($ALLOWED_DIRS as $dir) {
    if (strpos($rel_path, $dir) === 0) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    http_response_code(403);
    exit('Access denied.');
}

// Resolve absolute path from THIS file's location (__DIR__ = EndDev root)
$abs_path = realpath(__DIR__ . '/' . $rel_path);

// Make sure realpath didn't escape the EndDev directory
if ($abs_path === false || strpos($abs_path, realpath(__DIR__)) !== 0) {
    http_response_code(404);
    exit('File not found.');
}

if (!is_file($abs_path)) {
    http_response_code(404);
    exit('File not found.');
}

// Serve the file
$mime = 'application/octet-stream';
if (function_exists('mime_content_type')) {
    $mime = mime_content_type($abs_path) ?: $mime;
} elseif (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $abs_path) ?: $mime;
    finfo_close($finfo);
}

$filename = basename($abs_path);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs_path));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=86400');
readfile($abs_path);
exit;
