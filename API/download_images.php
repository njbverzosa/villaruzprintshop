<?php
// API/download_images.php

session_start();

// ==============================================
// 1. CHECK LOGIN (Admin only)
// ==============================================
function isLoggedIn()
{
    return isset($_SESSION['user_role']) &&
        isset($_SESSION['user_id']) &&
        isset($_SESSION['acc_number']);
}

if (!isLoggedIn() || $_SESSION['user_role'] !== 'Admin') {
    http_response_code(403);
    exit('Unauthorized');
}

// ==============================================
// 2. VERIFY ZIP EXTENSION
// ==============================================
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZipArchive extension is not enabled on this server.');
}

// ==============================================
// 3. LOCATE THE PRODUCTS FOLDER
// ==============================================
$productsDir = realpath(__DIR__ . '/../Products');

if ($productsDir === false || !is_dir($productsDir)) {
    http_response_code(404);
    exit('Products folder not found.');
}

// ==============================================
// 4. COLLECT ALL IMAGE FILES
// ==============================================
$allowedExtensions = ['jpg', 'jpeg', 'png'];

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($productsDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $ext = strtolower($file->getExtension());
    if (in_array($ext, $allowedExtensions, true)) {
        $files[] = $file->getPathname();
    }
}

if (empty($files)) {
    http_response_code(404);
    exit('No images found in the Products folder.');
}

// ==============================================
// 5. CREATE A TEMPORARY ZIP
// ==============================================
$zipName = 'Products_' . date('Y-m-d_His') . '.zip';
$tmpZip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipName;

$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Could not create ZIP file.');
}

foreach ($files as $filePath) {
    // Use only the filename inside the zip (flat structure)
    $localName = basename($filePath);
    // Avoid duplicate names by appending a counter
    $counter = 1;
    while ($zip->locateName($localName) !== false) {
        $info = pathinfo($filePath);
        $localName = $info['filename'] . ' (' . $counter . ').' . ($info['extension'] ?? '');
        $counter++;
    }
    $zip->addFile($filePath, $localName);
}

$zip->close();

// ==============================================
// 6. STREAM THE ZIP TO THE BROWSER
// ==============================================
if (!file_exists($tmpZip)) {
    http_response_code(500);
    exit('ZIP file was not created.');
}

// Clean any output buffering so the ZIP isn't corrupted
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tmpZip));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($tmpZip);

// Delete temp file after sending
@unlink($tmpZip);
exit;