<?php
// API/restore_delivery.php
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

if ($action === 'restore_delivery') {
    $deliveryNumber = trim($_POST['delivery_number'] ?? '');

    if (empty($deliveryNumber)) {
        echo json_encode(['success' => false, 'message' => 'Delivery number is required']);
        exit;
    }

    try {

        date_default_timezone_set('Asia/Manila');
        $formattedDate = date('j F Y g:i A');

        // Start transaction
        $pdo->beginTransaction();

        // Get all items from order_status_history for this delivery_number
        $stmt = $pdo->prepare("SELECT id, product_name, pieces FROM order_status_history WHERE delivery_number = :delivery_number");
        $stmt->execute([':delivery_number' => $deliveryNumber]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            throw new Exception("No items found for delivery number: {$deliveryNumber}");
        }

        $restoredItems = [];

        // For each item, add the pieces back to merchandise_inventory
        foreach ($items as $item) {
            $productName = $item['product_name'];
            $pieces = intval($item['pieces']);

            // Get current stock
            $stmt = $pdo->prepare("SELECT qty_on_hand, id FROM merchandise_inventory WHERE product_name = :product_name");
            $stmt->execute([':product_name' => $productName]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                // Update stock by adding the pieces back
                $newStock = $product['qty_on_hand'] + $pieces;
                $stmt = $pdo->prepare("UPDATE merchandise_inventory SET qty_on_hand = :new_stock, last_restocked = :last_restocked WHERE id = :product_id");
                $stmt->execute([
                    ':new_stock' => $newStock,
                    ':last_restocked' => $formattedDate,
                    ':product_id' => $product['id']
                ]);

                $restoredItems[] = [
                    'product_name' => $productName,
                    'pieces' => $pieces,
                    'new_stock' => $newStock
                ];
            }
        }

        // Delete all items from order_status_history for this delivery_number
        $stmt = $pdo->prepare("DELETE FROM order_status_history WHERE delivery_number = :delivery_number");
        $stmt->execute([':delivery_number' => $deliveryNumber]);

        // Delete the delivery record from for_deliveries
        $stmt = $pdo->prepare("DELETE FROM for_deliveries WHERE delivery_number = :delivery_number");
        $stmt->execute([':delivery_number' => $deliveryNumber]);

        // Commit transaction
        $pdo->commit();

        $itemCount = count($restoredItems);
        echo json_encode([
            'success' => true,
            'message' => "Successfully restored {$itemCount} item(s) from Delivery #{$deliveryNumber} back to inventory!",
            'restored_items' => $restoredItems
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Restore Delivery Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>