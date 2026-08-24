<?php
// Customer_API/cancel_order_item.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1); // Temporarily enable for debugging

require_once __DIR__ . '/../DB_Conn/config.php';

// Log all POST data for debugging
error_log('POST data: ' . print_r($_POST, true));

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$productName = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
$pieces = isset($_POST['pieces']) ? intval($_POST['pieces']) : 0;
$deliveryNumber = isset($_POST['delivery_number']) ? trim($_POST['delivery_number']) : '';
$userAccNumber = isset($_POST['acc_number']) ? trim($_POST['acc_number']) : '';
$csrfToken = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';

// Debug log
error_log("Processing cancellation - Order ID: $orderId, Product: $productName, Pieces: $pieces, Delivery: $deliveryNumber, User: $userAccNumber");

// Validate inputs with detailed error messages
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID: ' . $orderId]);
    exit;
}
if (empty($productName)) {
    echo json_encode(['success' => false, 'message' => 'Product name is required']);
    exit;
}
if ($pieces <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid pieces quantity: ' . $pieces]);
    exit;
}
if (empty($deliveryNumber)) {
    echo json_encode(['success' => false, 'message' => 'Delivery number is required']);
    exit;
}
if (empty($userAccNumber)) {
    echo json_encode(['success' => false, 'message' => 'Account number is required']);
    exit;
}

// Verify CSRF token
if (empty($csrfToken) || empty($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

try {
    // Check if PDO connection exists
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Database connection failed');
    }
    
    $pdo->beginTransaction();
    
    // First, verify the order exists and belongs to the user
    $verifyStmt = $pdo->prepare("
        SELECT osh.id, osh.status, osh.product_name, osh.pieces, osh.total_amount 
        FROM order_status_history osh
        WHERE osh.id = :order_id 
        AND osh.delivery_number = :delivery_number
    ");
    $verifyStmt->execute([
        ':order_id' => $orderId,
        ':delivery_number' => $deliveryNumber
    ]);
    $order = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order item not found');
    }
    
    // Verify the delivery belongs to the user
    $verifyDeliveryStmt = $pdo->prepare("
        SELECT acc_number 
        FROM for_deliveries 
        WHERE delivery_number = :delivery_number 
        AND acc_number = :acc_number
    ");
    $verifyDeliveryStmt->execute([
        ':delivery_number' => $deliveryNumber,
        ':acc_number' => $userAccNumber
    ]);
    
    if (!$verifyDeliveryStmt->fetch()) {
        throw new Exception('This order does not belong to you');
    }
    
    // Check if order is already cancelled
    if ($order['status'] === 'CANCELLED') {
        throw new Exception('This item is already cancelled');
    }
    
    // Delete the specific order item from order_status_history
    $deleteStmt = $pdo->prepare("
        DELETE FROM order_status_history 
        WHERE id = :order_id AND delivery_number = :delivery_number
    ");
    $deleteStmt->execute([
        ':order_id' => $orderId,
        ':delivery_number' => $deliveryNumber
    ]);
    
    // Check if there are any remaining items for this delivery number
    $checkRemainingStmt = $pdo->prepare("
        SELECT COUNT(*) as remaining_count,
               COALESCE(SUM(total_amount), 0) as total_amount_sum
        FROM order_status_history 
        WHERE delivery_number = :delivery_number
    ");
    $checkRemainingStmt->execute([':delivery_number' => $deliveryNumber]);
    $remaining = $checkRemainingStmt->fetch(PDO::FETCH_ASSOC);
    
    // If no items left for this delivery, delete from for_deliveries as well
    if ($remaining['remaining_count'] == 0) {
        $deleteDeliveryStmt = $pdo->prepare("
            DELETE FROM for_deliveries 
            WHERE delivery_number = :delivery_number
            AND acc_number = :acc_number
        ");
        $deleteDeliveryStmt->execute([
            ':delivery_number' => $deliveryNumber,
            ':acc_number' => $userAccNumber
        ]);
    } else {
        // Update the total amount in for_deliveries
        $updateDeliveryStmt = $pdo->prepare("
            UPDATE for_deliveries 
            SET total_amount = :new_total_amount
            WHERE delivery_number = :delivery_number
            AND acc_number = :acc_number
        ");
        $updateDeliveryStmt->execute([
            ':new_total_amount' => $remaining['total_amount_sum'],
            ':delivery_number' => $deliveryNumber,
            ':acc_number' => $userAccNumber
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Item cancelled successfully!"
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Cancel order error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>