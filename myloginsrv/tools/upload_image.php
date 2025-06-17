<?php
// upload_image.php â€“ sicherer Upload mit MIME-Check & Logging
// Version: 2025-05-20_02

date_default_timezone_set('Europe/Berlin');

function log_error($msg) {
    file_put_contents(__DIR__ . '/error.log', "[" . date('Y-m-d H:i:s') . "] ERROR: $msg\n", FILE_APPEND);
}
function log_audit($msg) {
    file_put_contents(__DIR__ . '/audit.log', "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        log_error("Upload directory '$uploadDir' could not be created.");
        echo json_encode(['success' => false, 'error' => 'Upload folder error.']);
        exit;
    }
}

// === Validierung ===
if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
    log_error("No image file received.");
    echo json_encode(['success' => false, 'error' => 'No image uploaded.']);
    exit;
}

$tmp = $_FILES['image']['tmp_name'];
$originalName = $_FILES['image']['name'] ?? 'unknown';
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $tmp);
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

if (!in_array($mime, $allowedTypes)) {
    log_error("Invalid MIME type '$mime' for file '$originalName'");
    echo json_encode(['success' => false, 'error' => 'Invalid image type.']);
    exit;
}

// === Sicherer eindeutiger Dateiname ===
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
do {
    $filename = uniqid('img_', true) . '.' . $ext;
    $targetFile = $uploadDir . $filename;
} while (file_exists($targetFile));

$targetUrl = '/uploads/' . $filename;

// === Upload durchfÃ¼hren ===
if (move_uploaded_file($tmp, $targetFile)) {
    log_audit("Image uploaded: $filename (original: $originalName, MIME: $mime)");
    echo json_encode(['success' => true, 'url' => $targetUrl]);
} else {
    log_error("Failed to move uploaded file to '$targetFile'");
    echo json_encode(['success' => false, 'error' => 'Upload failed.']);
}
