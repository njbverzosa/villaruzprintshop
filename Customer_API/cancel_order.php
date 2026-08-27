<?php
// Customer_API/cancel_order.php

session_start();

// ==============================================
// 1. SET HEADERS FOR JSON RESPONSE
// ==============================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// ==============================================
// 2. INCLUDE CONFIG
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 3. CHECK LOGIN STATUS
// ==============================================
function isLoggedIn()
{
    return isset($_SESSION['user_role']) &&
        isset($_SESSION['user_id']) &&
        isset($_SESSION['acc_number']);
}

if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login first.'
    ]);
    exit;
}

// ==============================================
// 4. GET USER DATA
// ==============================================
$accNumber = $_SESSION['acc_number'];

// ==============================================
// 5. GET POST DATA
// ==============================================
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Fallback to regular POST if JSON not provided
    $deliveryNumber = $_POST['delivery_number'] ?? '';
    $cancelReason = $_POST['cancel_reason'] ?? '';
    $otherReason = $_POST['other_reason'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
} else {
    $deliveryNumber = $input['delivery_number'] ?? '';
    $cancelReason = $input['cancel_reason'] ?? '';
    $otherReason = $input['other_reason'] ?? '';
    $csrfToken = $input['csrf_token'] ?? '';
}

// ==============================================
// 6. VALIDATE CSRF TOKEN
// ==============================================
if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false,
        'message' => 'Security validation failed. Please refresh the page and try again.'
    ]);
    exit;
}

// ==============================================
// 7. VALIDATE INPUT
// ==============================================
if (empty($deliveryNumber)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid order number.'
    ]);
    exit;
}

if (empty($cancelReason)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select a reason for cancellation.'
    ]);
    exit;
}

// ==============================================
// 8. PROCESS CANCELLATION
// ==============================================
try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Verify order belongs to user and is cancellable
    $stmt = $pdo->prepare("SELECT status FROM for_deliveries WHERE delivery_number = ? AND acc_number = ?");
    $stmt->execute([$deliveryNumber, $accNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode([
            'success' => false,
            'message' => 'Order not found.'
        ]);
        exit;
    }

    $allowedStatuses = ['PENDING', 'PACKING'];
    if (!in_array(strtoupper($order['status']), $allowedStatuses)) {
        echo json_encode([
            'success' => false,
            'message' => 'This order cannot be cancelled. Current status: ' . $order['status']
        ]);
        exit;
    }

    // Update order based on reason selection
    if ($cancelReason === 'Other') {
        // If "Other" is selected, save the typed reason in other_reason column
        $stmt = $pdo->prepare("UPDATE for_deliveries 
                               SET status = 'CANCELLED', 
                                   cancel_reason = ?, 
                                   other_reason = ? 
                               WHERE delivery_number = ? AND acc_number = ?");
        $stmt->execute(['Other', $otherReason, $deliveryNumber, $accNumber]);
    } else {
        // If a predefined reason is selected, save it in cancel_reason column
        $stmt = $pdo->prepare("UPDATE for_deliveries 
                               SET status = 'CANCELLED', 
                                   cancel_reason = ?, 
                                   other_reason = NULL 
                               WHERE delivery_number = ? AND acc_number = ?");
        $stmt->execute([$cancelReason, $deliveryNumber, $accNumber]);
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order has been cancelled.'
    ]);

} catch (PDOException $e) {
    // Rollback on error
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>