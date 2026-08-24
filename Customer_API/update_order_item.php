<?php
// update_order_item.php
// API endpoint for updating and removing order items

session_start();
header('Content-Type: application/json');

// Correct path to config file (adjust based on your folder structure)
require_once __DIR__ . '/../DB_Conn/config.php';



// Verify CSRF token
$inputCSRF = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || $inputCSRF !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'update_item') {
    updateOrderItem($pdo);
} elseif ($action === 'remove_item') {
    removeOrderItem($pdo);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function updateOrderItem($pdo)
{
    $itemId = $_POST['item_id'] ?? 0;
    $deliveryNumber = $_POST['delivery_number'] ?? '';
    $productName = trim($_POST['product_name'] ?? '');
    $unit = trim($_POST['unit'] ?? 'Pcs');
    $sellingPrice = floatval($_POST['selling_price'] ?? 0);
    $pieces = intval($_POST['pieces'] ?? 0);

    // Validate inputs
    if (!$itemId || !$deliveryNumber) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    if (empty($productName)) {
        echo json_encode(['success' => false, 'message' => 'Product name cannot be empty']);
        return;
    }

    if ($sellingPrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Price must be greater than 0']);
        return;
    }

    if ($pieces < 1) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1']);
        return;
    }

    $totalAmount = $sellingPrice * $pieces;

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Update the order item
        $updateStmt = $pdo->prepare("
            UPDATE order_status_history 
            SET product_name = ?, unit = ?, selling_price = ?, pieces = ?, total_amount = ?
            WHERE id = ? AND delivery_number = ?
        ");
        $updateStmt->execute([$productName, $unit, $sellingPrice, $pieces, $totalAmount, $itemId, $deliveryNumber]);

        // Check if update was successful
        if ($updateStmt->rowCount() == 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Item not found']);
            return;
        }

        // Recalculate total amount for the delivery from order_status_history
        $totalStmt = $pdo->prepare("
            SELECT SUM(total_amount) as new_total 
            FROM order_status_history 
            WHERE delivery_number = ?
        ");
        $totalStmt->execute([$deliveryNumber]);
        $result = $totalStmt->fetch(PDO::FETCH_ASSOC);
        $newTotal = $result['new_total'] ?? 0;

        // Update for_deliveries table with new total amount
        $updateDeliveryStmt = $pdo->prepare("
            UPDATE for_deliveries 
            SET total_amount = ? 
            WHERE delivery_number = ?
        ");
        $updateDeliveryStmt->execute([$newTotal, $deliveryNumber]);

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Item updated successfully',
            'new_total' => $newTotal,
            'formatted_total' => '₱ ' . number_format($newTotal, 2)
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database error in update_order_item: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

function removeOrderItem($pdo)
{
    $itemId = $_POST['item_id'] ?? 0;
    $deliveryNumber = $_POST['delivery_number'] ?? '';

    // Validate inputs
    if (!$itemId || !$deliveryNumber) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Delete the order item
        $deleteStmt = $pdo->prepare("DELETE FROM order_status_history WHERE id = ? AND delivery_number = ?");
        $deleteStmt->execute([$itemId, $deliveryNumber]);

        // Check if delete was successful
        if ($deleteStmt->rowCount() == 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Item not found']);
            return;
        }

        // Check if there are any remaining items for this delivery
        $remainingStmt = $pdo->prepare("
            SELECT COUNT(*) as count, SUM(total_amount) as new_total 
            FROM order_status_history 
            WHERE delivery_number = ?
        ");
        $remainingStmt->execute([$deliveryNumber]);
        $remaining = $remainingStmt->fetch(PDO::FETCH_ASSOC);
        $itemCount = $remaining['count'] ?? 0;
        $newTotal = $remaining['new_total'] ?? 0;

        if ($itemCount == 0) {
            // No items left, delete the delivery record
            $deleteDeliveryStmt = $pdo->prepare("
                DELETE FROM for_deliveries 
                WHERE delivery_number = ?
            ");
            $deleteDeliveryStmt->execute([$deliveryNumber]);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Item removed. Order is now empty.',
                'empty_order' => true
            ]);
        } else {
            // Update for_deliveries table with new total amount
            $updateDeliveryStmt = $pdo->prepare("
                UPDATE for_deliveries 
                SET total_amount = ? 
                WHERE delivery_number = ?
            ");
            $updateDeliveryStmt->execute([$newTotal, $deliveryNumber]);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Item removed successfully',
                'new_total' => $newTotal,
                'formatted_total' => '₱ ' . number_format($newTotal, 2),
                'empty_order' => false
            ]);
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database error in remove_order_item: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}
?>