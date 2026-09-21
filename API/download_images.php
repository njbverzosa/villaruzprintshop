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
// 3. FOLDERS TO INCLUDE IN THE ZIP
//    key   = folder name on disk (project root)
//    value = folder name inside the zip
// ==============================================
$foldersToZip = [
    'Inv_Products' => 'Inv_Products',
    'Business_Docs' => 'Business_Docs',
    'Products'      => 'Products',
];

$projectRoot = realpath(__DIR__ . '/..');

if ($projectRoot === false) {
    http_response_code(500);
    exit('Could not resolve project root.');
}

$allowedExtensions = ['jpg', 'jpeg', 'png'];

// ==============================================
// 4. COLLECT FILES FROM EACH FOLDER
// ==============================================
$filesByFolder = [];   // [ zipFolderName => [absolute paths...] ]
$totalFiles    = 0;

foreach ($foldersToZip as $diskFolder => $zipFolder) {
    $absFolder = realpath($projectRoot . DIRECTORY_SEPARATOR . $diskFolder);

    if ($absFolder === false || !is_dir($absFolder)) {
        // Folder doesn't exist — skip silently
        continue;
    }

    $bucket = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absFolder, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        $ext = strtolower($file->getExtension());
        if (in_array($ext, $allowedExtensions, true)) {
            $bucket[] = $file->getPathname();
        }
    }

    if (!empty($bucket)) {
        $filesByFolder[$zipFolder] = $bucket;
        $totalFiles += count($bucket);
    }
}

if ($totalFiles === 0) {
    http_response_code(404);
    exit('No images found in any of the configured folders.');
}

// ==============================================
// 5. CREATE A TEMPORARY ZIP
// ==============================================
$zipName = 'Media_Backup_' . date('Y-m-d_His') . '.zip';
$tmpZip  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipName;

$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Could not create ZIP file.');
}

// Keep track of used names PER FOLDER so no file is overwritten
foreach ($filesByFolder as $zipFolder => $paths) {
    $usedNames = [];

    foreach ($paths as $filePath) {
        $baseName = basename($filePath);

        // Deduplicate within the same zip folder
        if (isset($usedNames[$baseName])) {
            $info = pathinfo($filePath);
            $counter = 1;
            do {
                $candidate = $info['filename'] . ' (' . $counter . ').' . ($info['extension'] ?? '');
                $counter++;
            } while (isset($usedNames[$candidate]));
            $baseName = $candidate;
        }

        $usedNames[$baseName] = true;

        // Preserve folder structure inside the zip
        $localName = $zipFolder . '/' . $baseName;
        $zip->addFile($filePath, $localName);
    }
}

$zip->close();

// ==============================================
// 6. STREAM THE ZIP TO THE BROWSER
// ==============================================
if (!file_exists($tmpZip)) {
    http_response_code(500);
    exit('ZIP file was not created.');
}

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

@unlink($tmpZip);
exit;