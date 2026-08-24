<?php
// API/remove_data.php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); 
}
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, log them instead

require_once __DIR__ . '/../DB_Conn/config.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

// Get the action from POST request
$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    // Check if PDO connection exists
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Database connection failed');
    }
    
    $pdo->beginTransaction();

    if ($action === 'restore_order') {
        // Get POST data
        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $productName = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
        $pieces = isset($_POST['pieces']) ? intval($_POST['pieces']) : 0;

        // Validate inputs
        if ($orderId <= 0 || empty($productName) || $pieces <= 0) {
            throw new Exception('Invalid order data');
        }

        // First, get the order details to verify it exists and get status
        $checkStmt = $pdo->prepare("
            SELECT status, product_name, pieces, delivery_number 
            FROM order_status_history 
            WHERE id = :order_id
        ");
        $checkStmt->execute([':order_id' => $orderId]);
        $order = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception('Order not found');
        }

        // Only allow cancellation for orders that are not already cancelled
        if ($order['status'] === 'CANCELLED') {
            throw new Exception('This order is already cancelled');
        }

        // COMMENTED OUT: Update inventory - DO NOT add back the cancelled quantity
        /*
        // Check if product exists in merchandise_inventory
        $checkProductStmt = $pdo->prepare("
            SELECT id, qty_on_hand, unit 
            FROM merchandise_inventory 
            WHERE product_name = :product_name
        ");
        $checkProductStmt->execute([':product_name' => $productName]);
        $product = $checkProductStmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            // Update inventory - add back the cancelled quantity
            $updateInventoryStmt = $pdo->prepare("
                UPDATE merchandise_inventory 
                SET qty_on_hand = qty_on_hand + :pieces,
                    last_restocked = :last_restocked
                WHERE product_name = :product_name
            ");
            
            $updateInventoryStmt->execute([
                ':pieces' => $pieces,
                ':last_restocked' => date('Y-m-d H:i:s'),
                ':product_name' => $productName
            ]);
        } else {
            // Product not found in inventory - log but continue with deletion
            error_log("Product '{$productName}' not found in inventory when cancelling order {$orderId}");
        }
        */

        // Get delivery number for updating for_deliveries table
        $deliveryNumber = $order['delivery_number'];
        
        // Delete the order from order_status_history
        $deleteStmt = $pdo->prepare("DELETE FROM order_status_history WHERE id = :order_id");
        $deleteStmt->execute([':order_id' => $orderId]);

        // COMMENTED OUT: Check and update for_deliveries
        /*
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
            ");
            $deleteDeliveryStmt->execute([':delivery_number' => $deliveryNumber]);
        } else {
            // Update the total amount in for_deliveries if there are remaining items
            $updateDeliveryStmt = $pdo->prepare("
                UPDATE for_deliveries 
                SET total_amount = :new_total_amount
                WHERE delivery_number = :delivery_number
            ");
            $updateDeliveryStmt->execute([
                ':new_total_amount' => $remaining['total_amount_sum'],
                ':delivery_number' => $deliveryNumber
            ]);
        }
        */

        // COMMENTED OUT: Log the cancellation
        /*
        try {
            $logStmt = $pdo->prepare("
                INSERT INTO order_cancellation_logs (
                    order_id, 
                    product_name, 
                    pieces, 
                    cancelled_by, 
                    cancelled_at,
                    delivery_number
                ) VALUES (
                    :order_id,
                    :product_name,
                    :pieces,
                    :cancelled_by,
                    :cancelled_at,
                    :delivery_number
                )
            ");
            
            $logStmt->execute([
                ':order_id' => $orderId,
                ':product_name' => $productName,
                ':pieces' => $pieces,
                ':cancelled_by' => $_SESSION['acc_number'] ?? 'Unknown',
                ':cancelled_at' => date('Y-m-d H:i:s'),
                ':delivery_number' => $deliveryNumber
            ]);
        } catch (Exception $logError) {
            // Log table might not exist - continue anyway
            error_log("Failed to log cancellation: " . $logError->getMessage());
        }
        */

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true, 
            'message' => "Order cancelled successfully! {$pieces} piece(s) of '{$productName}' have been removed."
        ]);

    } else {
        throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>