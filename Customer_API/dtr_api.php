<?php
// Customer_API/dtr_api.php

session_start();
require_once __DIR__ . '/../DB_Conn/config.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// ============================================================
// HEADERS
// ============================================================
header('Content-Type: application/json');

// ============================================================
// CHECK LOGIN
// ============================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

// ============================================================
// VALIDATE CSRF TOKEN
// ============================================================
$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

// ============================================================
// GET ACTION
// ============================================================
$action = $_POST['action'] ?? '';

// ============================================================
// ACTION: time_in
// ============================================================
if ($action === 'time_in') {
    $today = date('Y-m-d');
    $now = date('H:i:s');
    $nowTimestamp = strtotime($now);

    // Check if already clocked in today
    $checkStmt = $pdo->prepare("SELECT id FROM dtr WHERE acc_number = ? AND date = ? AND time_in IS NOT NULL AND time_out IS NULL");
    $checkStmt->execute([$accNumber, $today]);

    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'You are already clocked in.']);
        exit;
    }

    // Check if already completed today
    $completedStmt = $pdo->prepare("SELECT id FROM dtr WHERE acc_number = ? AND date = ? AND time_out IS NOT NULL");
    $completedStmt->execute([$accNumber, $today]);

    if ($completedStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already completed your shift today.']);
        exit;
    }

    // Insert or update DTR record
    $stmt = $pdo->prepare("INSERT INTO dtr (acc_number, user_id, date, time_in, status) VALUES (?, ?, ?, ?, 'present') ON DUPLICATE KEY UPDATE time_in = VALUES(time_in), status = 'present'");
    $stmt->execute([$accNumber, $userId, $today, $now]);

    // Check if late
    $cutOffIn = strtotime('08:00:00');
    $isLate = $nowTimestamp > $cutOffIn;
    $lateMinutes = $isLate ? floor(($nowTimestamp - $cutOffIn) / 60) : 0;

    $message = 'Time in recorded';
    if ($isLate) {
        $message .=' Late Yarn!';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'time_in' => $now,
            'is_late' => $isLate,
            'late_minutes' => $lateMinutes
        ]
    ]);
    exit;
}

// ============================================================
// ACTION: time_out
// ============================================================
if ($action === 'time_out') {
    $today = date('Y-m-d');
    $now = date('H:i:s');
    $nowTimestamp = strtotime($now);

    // Check if clocked in today
    $checkStmt = $pdo->prepare("SELECT id, time_in FROM dtr WHERE acc_number = ? AND date = ? AND time_in IS NOT NULL AND time_out IS NULL");
    $checkStmt->execute([$accNumber, $today]);
    $dtrData = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$dtrData) {
        echo json_encode(['success' => false, 'message' => 'You are not clocked in.']);
        exit;
    }

    $dtrId = $dtrData['id'];

    // Update time_out
    $stmt = $pdo->prepare("UPDATE dtr SET time_out = ? WHERE id = ?");
    $stmt->execute([$now, $dtrId]);

    // Check if overtime
    $cutOffOut = strtotime('17:00:00');
    $isOT = $nowTimestamp > $cutOffOut;
    $otMinutes = $isOT ? floor(($nowTimestamp - $cutOffOut) / 60) : 0;

    // Calculate hours worked
    $timeIn = new DateTime($dtrData['time_in']);
    $timeOut = new DateTime($now);
    $diff = $timeIn->diff($timeOut);
    $hoursWorked = $diff->h + ($diff->i / 60);

    $message = 'Time out recorded';
    if ($isOT) {
        $message .= ' O.T. Yarn!';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'time_out' => $now,
            'hours_worked' => round($hoursWorked, 2),
            'is_ot' => $isOT,
            'ot_minutes' => $otMinutes
        ]
    ]);
    exit;
}

// ============================================================
// ACTION: get_dtr_today
// ============================================================
if ($action === 'get_dtr_today') {
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("SELECT * FROM dtr WHERE acc_number = ? AND date = ?");
    $stmt->execute([$accNumber, $today]);
    $dtr = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dtr) {
        echo json_encode([
            'success' => true,
            'data' => $dtr
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => null,
            'message' => 'No DTR record for today.'
        ]);
    }
    exit;
}

// ============================================================
// ACTION: get_dtr_month
// ============================================================
if ($action === 'get_dtr_month') {
    $monthStart = date('Y-m-01');

    $stmt = $pdo->prepare("SELECT * FROM dtr WHERE acc_number = ? AND date >= ? ORDER BY date DESC");
    $stmt->execute([$accNumber, $monthStart]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $records
    ]);
    exit;
}

// ============================================================
// INVALID ACTION
// ============================================================
echo json_encode([
    'success' => false,
    'message' => 'Invalid action: ' . $action
]);
exit;
?>