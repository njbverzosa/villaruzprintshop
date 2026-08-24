<?php
// API/restore_order.php
session_start();
header('Content-Type: application/json');

require_once '../DB_Conn/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'restore_order') {
    $orderId = intval($_POST['order_id'] ?? 0);
    $productName = trim($_POST['product_name'] ?? '');
    $pieces = intval($_POST['pieces'] ?? 0);

    // Validate inputs
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit;
    }

    if (empty($productName)) {
        echo json_encode(['success' => false, 'message' => 'Product name is required']);
        exit;
    }

    if ($pieces <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
        exit;
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        $last_restocked = date('j F Y g:i A');

        // First, get the current stock of the product
        $stmt = $pdo->prepare("SELECT qty_on_hand, id FROM merchandise_inventory WHERE product_name = :product_name");
        $stmt->execute([':product_name' => $productName]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception("Product '{$productName}' not found in inventory");
        }
 
        // Update the stock in merchandise_inventory (add back the pieces)
        $newStock = $product['qty_on_hand'] + $pieces;
        $stmt = $pdo->prepare("UPDATE merchandise_inventory SET qty_on_hand = :new_stock, last_restocked = :last_restocked WHERE id = :product_id");
        $stmt->execute([
            ':new_stock' => $newStock,
            ':last_restocked' => $last_restocked,
            ':product_id' => $product['id']
        ]);

        // Get the delivery_number before deleting
        $stmt = $pdo->prepare("SELECT delivery_number, order_id FROM order_status_history WHERE id = :order_id");
        $stmt->execute([':order_id' => $orderId]);
        $orderData = $stmt->fetch(PDO::FETCH_ASSOC);
        $deliveryNumber = $orderData['delivery_number'] ?? null;
        $mainOrderId = $orderData['order_id'] ?? null;

        // Delete the restored order from order_status_history
        $stmt = $pdo->prepare("DELETE FROM order_status_history WHERE id = :order_id");
        $stmt->execute([':order_id' => $orderId]);

        // Check if there are any remaining items with the same delivery_number
        if ($deliveryNumber) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_status_history WHERE delivery_number = :delivery_number");
            $stmt->execute([':delivery_number' => $deliveryNumber]);
            $remaining = $stmt->fetch(PDO::FETCH_ASSOC);

            // If no more items for this delivery, delete the main order from for_deliveries
            if ($remaining['count'] == 0) {
                $stmt = $pdo->prepare("DELETE FROM for_deliveries WHERE delivery_number = :delivery_number");
                $stmt->execute([':delivery_number' => $deliveryNumber]);
            } else {
                // Update the total amount in for_deliveries
                $stmt = $pdo->prepare("SELECT SUM(total_amount) as new_total FROM order_status_history WHERE delivery_number = :delivery_number");
                $stmt->execute([':delivery_number' => $deliveryNumber]);
                $newTotal = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("UPDATE for_deliveries SET total_amount = :new_total WHERE delivery_number = :delivery_number");
                $stmt->execute([
                    ':new_total' => $newTotal['new_total'],
                    ':delivery_number' => $deliveryNumber
                ]);
            }
        }

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Successfully restored {$pieces} piece(s) of '{$productName}' back to inventory and removed from records!",
            'new_stock' => $newStock,
            'deleted' => true
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Restore Order Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>