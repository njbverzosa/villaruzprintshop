<?php
// web/mark_update_seen.php
// ✅ Sets notif_shown = 1 for the given acc_number

session_start();
require_once __DIR__ . '/../DB_Conn/config.php';

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_role'], $_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Only admins
if ($_SESSION['user_role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// ✅ Validate CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// ✅ acc_number required
$accNum = trim((string) ($_POST['acc_number'] ?? ''));
if ($accNum === '') {
    echo json_encode(['success' => false, 'message' => 'Missing acc_number']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE system_update
        SET notif_shown = 1
        WHERE acc_number = ? AND notif_shown = 0
    ");
    $stmt->execute([$accNum]);

    echo json_encode([
        'success' => true,
        'updated' => $stmt->rowCount()
    ]);
} catch (PDOException $e) {
    error_log("mark_update_seen failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
exit;