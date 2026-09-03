<?php
// API/face_recognition_api.php

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../DB_Conn/config.php';

// ============================================================
// 1. CHECK LOGIN
// ============================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

// ============================================================
// 2. CSRF VALIDATION
// ============================================================
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}
// ============================================================
// 3. CREATE UPLOAD DIRECTORY
// ============================================================
// Go up 2 levels from API/ to project root, then to uploads/faces/
$uploadDir = __DIR__ . '/../faceVerification';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
// ============================================================
// 4. PROCESS ACTION
// ============================================================
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'register_face':
        registerFace($pdo, $uploadDir);
        break;
    case 'get_registered_users':
        getRegisteredUsers($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// ============================================================
// FUNCTIONS
// ============================================================

function registerFace($pdo, $uploadDir) {
    $accNumber = $_POST['acc_number'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    $userName = $_POST['user_name'] ?? '';
    $faceImage = $_POST['face_image'] ?? '';

    // Validate
    if (empty($accNumber) || empty($userId) || empty($userName)) {
        echo json_encode(['success' => false, 'message' => 'User information missing']);
        exit;
    }

    if (empty($faceImage)) {
        echo json_encode(['success' => false, 'message' => 'No face image captured']);
        exit;
    }

    // Check if user already has face registered
    $stmt = $pdo->prepare("SELECT id FROM face_recognition WHERE acc_number = ? AND status = 'active'");
    $stmt->execute([$accNumber]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'You already have a registered face']);
        exit;
    }

    // Process base64 image
    $imageData = base64_decode(preg_replace('#^data:image/[^;]+;base64,#', '', $faceImage));
    if ($imageData === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid image data']);
        exit;
    }

    // Save image
    $timestamp = date('Ymd_His');
    $filename = 'face_' . $accNumber . '_' . $timestamp . '.jpg';
    $filepath = $uploadDir . $filename;
    
    if (!file_put_contents($filepath, $imageData)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save image']);
        exit;
    }

    // For now, we store a simple descriptor (in production, use proper face recognition library)
    // This is a placeholder - actual face recognition would extract features
    $faceDescriptor = json_encode([
        'image_path' => $filepath,
        'filename' => $filename,
        'registered_at' => date('Y-m-d H:i:s')
    ]);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO face_recognition (acc_number, user_id, user_name, face_descriptor, face_image, registered_at, status) 
            VALUES (?, ?, ?, ?, ?, NOW(), 'active')
        ");
        $stmt->execute([
            $accNumber,
            $userId,
            $userName,
            $faceDescriptor,
            $filename
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Face registered successfully!',
            'face_id' => $pdo->lastInsertId(),
            'image_path' => $filename
        ]);

    } catch (PDOException $e) {
        // Delete uploaded file if database insert fails
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getRegisteredUsers($pdo) {
    $stmt = $pdo->prepare("
        SELECT id, acc_number, user_name, status, registered_at 
        FROM face_recognition 
        ORDER BY registered_at DESC 
        LIMIT 50
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
}