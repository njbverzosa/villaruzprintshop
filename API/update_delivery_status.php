<?php
/**
 * API: Update Delivery Status
 * 
 * This script handles updating order status (PENDING/PAID/CANCELLED/CREDIT)
 * and manages inventory adjustments accordingly.
 * 
 * Status Change Logic:
 * - PENDING -> PAID: Deduct stock from inventory (continues even if fails)
 * - PENDING -> CREDIT: No inventory change (reservation only)
 * - CREDIT -> PAID: Deduct stock from inventory (continues even if fails)
 * - CREDIT -> CANCELLED: No inventory change
 * - PAID -> CANCELLED: Restore stock to inventory
 * - PAID -> CREDIT: Restore stock to inventory
 * - Other changes: No inventory impact
 * 
 * Also handles removing individual items from orders (remove_order_item)
 * And updating multiple order items (update_order_items)
 * And reordering items (reorder_items)
 */

session_start();
header('Content-Type: application/json');

// ==============================================
// REQUIRE DEPENDENCIES
// ==============================================
require_once '../DB_Conn/config.php';

// ==============================================
// AUTHENTICATION & SECURITY CHECKS
// ==============================================

/**
 * Verify user is logged in
 */
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login first']);
    exit;
}

/**
 * Verify CSRF token to prevent cross-site request forgery
 */
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// ==============================================
// HELPER FUNCTIONS
// ==============================================

/**
 * Validate and sanitize delivery status
 */
function isValidStatus($status)
{
    $allowedStatuses = ['PENDING', 'PAID', 'CANCELLED', 'CREDIT', 'PACKING', 'SHIPPED', 'OFD', 'DELIVERED'];
    return in_array($status, $allowedStatuses);
}

/**
 * Get current status of a delivery order
 */
function getCurrentDeliveryStatus($pdo, $deliveryNumber)
{
    $stmt = $pdo->prepare("SELECT status, delivery_date FROM for_deliveries WHERE delivery_number = :delivery_number");
    $stmt->execute([':delivery_number' => $deliveryNumber]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update delivery status and delivery date in for_deliveries table
 */
function updateDeliveryStatus($pdo, $deliveryNumber, $newStatus, $deliveryDate = null)
{
    if ($deliveryDate !== null) {
        $stmt = $pdo->prepare("UPDATE for_deliveries SET status = :status, delivery_date = :delivery_date WHERE delivery_number = :delivery_number");
        return $stmt->execute([
            ':status' => $newStatus,
            ':delivery_date' => $deliveryDate,
            ':delivery_number' => $deliveryNumber
        ]);
    } else {
        $stmt = $pdo->prepare("UPDATE for_deliveries SET status = :status WHERE delivery_number = :delivery_number");
        return $stmt->execute([
            ':status' => $newStatus,
            ':delivery_number' => $deliveryNumber
        ]);
    }
}

/**
 * Update status in order_status_history for all items under a delivery
 */
function updateOrderHistoryStatus($pdo, $deliveryNumber, $newStatus)
{
    $stmt = $pdo->prepare("UPDATE order_status_history SET status = :status WHERE delivery_number = :delivery_number");
    return $stmt->execute([
        ':status' => $newStatus,
        ':delivery_number' => $deliveryNumber
    ]);
}

/**
 * Get all order items for a specific delivery
 */
function getDeliveryOrderItems($pdo, $deliveryNumber)
{
    $stmt = $pdo->prepare("
        SELECT product_name, pieces 
        FROM order_status_history 
        WHERE delivery_number = :delivery_number
    ");
    $stmt->execute([':delivery_number' => $deliveryNumber]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check current stock level of a product
 */
function getProductStock($pdo, $productName)
{
    $stmt = $pdo->prepare("
        SELECT id, product_name, qty_on_hand, selling_price 
        FROM merchandise_inventory 
        WHERE product_name = :product_name
    ");
    $stmt->execute([':product_name' => $productName]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update selling price in inventory
 */
function updateInventorySellingPrice($pdo, $productName, $sellingPrice, $dateTime)
{
    $currentStock = getProductStock($pdo, $productName);
    if (!$currentStock)
        return false;

    $stmt = $pdo->prepare("
        UPDATE merchandise_inventory 
        SET selling_price = :selling_price,
            last_restocked = :last_restocked
        WHERE product_name = :product_name
    ");

    return $stmt->execute([
        ':selling_price' => $sellingPrice,
        ':last_restocked' => $dateTime,
        ':product_name' => $productName
    ]);
}

/**
 * Deduct quantity from product inventory
 */
function deductProductStock($pdo, $productName, $quantity, $dateTime)
{
    $currentStock = getProductStock($pdo, $productName);

    if (!$currentStock) {
        error_log("Product '{$productName}' not found in inventory during deduction");
        return false;
    }

    if ($currentStock['qty_on_hand'] < $quantity) {
        error_log("Insufficient stock for '{$productName}'. Available: {$currentStock['qty_on_hand']}, Required: {$quantity}");
        $newQuantity = max(0, $currentStock['qty_on_hand'] - $quantity);
    } else {
        $newQuantity = $currentStock['qty_on_hand'] - $quantity;
    }

    $stmt = $pdo->prepare("
        UPDATE merchandise_inventory 
        SET qty_on_hand = :new_quantity,
            last_restocked = :last_restocked
        WHERE product_name = :product_name
    ");

    return $stmt->execute([
        ':new_quantity' => $newQuantity,
        ':last_restocked' => $dateTime,
        ':product_name' => $productName
    ]);
}

/**
 * Restore quantity to product inventory
 */
function restoreProductStock($pdo, $productName, $quantity, $dateTime)
{
    $stmt = $pdo->prepare("
        UPDATE merchandise_inventory 
        SET qty_on_hand = qty_on_hand + :quantity,
            last_restocked = :last_restocked
        WHERE product_name = :product_name
    ");

    return $stmt->execute([
        ':quantity' => $quantity,
        ':last_restocked' => $dateTime,
        ':product_name' => $productName
    ]);
}

/**
 * Process inventory deduction when order is marked as PAID
 */
function processPaidOrderInventory($pdo, $deliveryNumber, $dateTime)
{
    $orderItems = getDeliveryOrderItems($pdo, $deliveryNumber);

    if (empty($orderItems)) {
        return [
            'success' => true,
            'errors' => [],
            'message' => 'No items found for delivery number: ' . $deliveryNumber
        ];
    }

    $errors = [];
    $successCount = 0;

    foreach ($orderItems as $item) {
        try {
            $result = deductProductStock($pdo, $item['product_name'], intval($item['pieces']), $dateTime);
            if ($result)
                $successCount++;
            else
                $errors[] = "Failed to deduct stock for '{$item['product_name']}'";
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    return [
        'success' => true,
        'errors' => $errors,
        'success_count' => $successCount,
        'total_items' => count($orderItems),
        'message' => $successCount . ' of ' . count($orderItems) . ' items had stock deducted' . (empty($errors) ? '.' : ', but some errors occurred.')
    ];
}

/**
 * Process inventory restoration when PAID order is CANCELLED
 */
function processCancelledPaidOrderInventory($pdo, $deliveryNumber, $dateTime)
{
    $orderItems = getDeliveryOrderItems($pdo, $deliveryNumber);
    if (!empty($orderItems)) {
        foreach ($orderItems as $item) {
            restoreProductStock($pdo, $item['product_name'], intval($item['pieces']), $dateTime);
        }
    }
}

/**
 * Process inventory restoration when PAID order is changed to CREDIT
 */
function processCreditFromPaidInventory($pdo, $deliveryNumber, $dateTime)
{
    $orderItems = getDeliveryOrderItems($pdo, $deliveryNumber);
    if (!empty($orderItems)) {
        foreach ($orderItems as $item) {
            restoreProductStock($pdo, $item['product_name'], intval($item['pieces']), $dateTime);
        }
    }
}

/**
 * Remove an item from order_status_history
 */
function removeOrderItem($pdo, $deliveryNumber, $productName)
{
    $checkStmt = $pdo->prepare("
        SELECT * FROM order_status_history 
        WHERE delivery_number = :delivery_number AND product_name = :product_name
    ");
    $checkStmt->execute([
        ':delivery_number' => $deliveryNumber,
        ':product_name' => $productName
    ]);
    $item = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Item '{$productName}' not found in this order");
    }

    $deleteStmt = $pdo->prepare("
        DELETE FROM order_status_history 
        WHERE delivery_number = :delivery_number AND product_name = :product_name
    ");
    $deleteResult = $deleteStmt->execute([
        ':delivery_number' => $deliveryNumber,
        ':product_name' => $productName
    ]);

    if (!$deleteResult) {
        throw new Exception("Failed to remove item from order");
    }

    $checkRemainingStmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM order_status_history 
        WHERE delivery_number = :delivery_number
    ");
    $checkRemainingStmt->execute([':delivery_number' => $deliveryNumber]);
    $remaining = $checkRemainingStmt->fetch(PDO::FETCH_ASSOC);

    if ($remaining['count'] == 0) {
        $deleteDeliveryStmt = $pdo->prepare("
            DELETE FROM for_deliveries WHERE delivery_number = :delivery_number
        ");
        $deleteDeliveryStmt->execute([':delivery_number' => $deliveryNumber]);

        return [
            'success' => true,
            'message' => 'Item removed. No items remaining in this order.',
            'order_empty' => true
        ];
    }

    return [
        'success' => true,
        'message' => "Successfully removed {$productName} from the order",
        'product_name' => $productName,
        'order_empty' => false
    ];
}

/**
 * Update multiple order items with delivery date
 */
function updateOrderItems($pdo, $deliveryNumber, $items, $deliveryDate = null)
{
    $updatedCount = 0;
    $errors = [];
    date_default_timezone_set('Asia/Manila');
    $currentDateTime = date('j F Y g:i A');

    foreach ($items as $item) {
        if (empty($item['product_name']) || empty($item['pieces']) || !isset($item['selling_price']) || empty($item['total_amount'])) {
            $errors[] = "Missing required fields for an item";
            continue;
        }

        if (!empty($item['id'])) {
            $checkStmt = $pdo->prepare("
                SELECT * FROM order_status_history 
                WHERE delivery_number = :delivery_number AND id = :id
            ");
            $checkStmt->execute([
                ':delivery_number' => $deliveryNumber,
                ':id' => $item['id']
            ]);
        } else {
            $checkStmt = $pdo->prepare("
                SELECT * FROM order_status_history 
                WHERE delivery_number = :delivery_number AND product_name = :product_name
            ");
            $checkStmt->execute([
                ':delivery_number' => $deliveryNumber,
                ':product_name' => $item['product_name']
            ]);
        }

        $existingItem = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingItem) {
            $errors[] = "Item '{$item['product_name']}' not found in this order";
            continue;
        }

        $updateStmt = $pdo->prepare("
            UPDATE order_status_history 
            SET product_name = :product_name,
                pieces = :pieces,
                unit = :unit,
                selling_price = :selling_price,
                total_amount = :total_amount
            WHERE delivery_number = :delivery_number AND id = :id
        ");

        $result = $updateStmt->execute([
            ':product_name' => $item['product_name'],
            ':pieces' => $item['pieces'],
            ':unit' => $item['unit'],
            ':selling_price' => $item['selling_price'],
            ':total_amount' => $item['total_amount'],
            ':delivery_number' => $deliveryNumber,
            ':id' => $existingItem['id']
        ]);

        if ($result) {
            $updatedCount++;
            try {
                updateInventorySellingPrice($pdo, $item['product_name'], $item['selling_price'], $currentDateTime);
            } catch (Exception $e) {
                error_log("Failed to update selling price for {$item['product_name']}: " . $e->getMessage());
            }
        } else {
            $errors[] = "Failed to update '{$item['product_name']}'";
        }
    }

    if (!empty($errors)) {
        throw new Exception("Update errors: " . implode(", ", $errors));
    }

    $totalStmt = $pdo->prepare("
        SELECT SUM(total_amount) as total 
        FROM order_status_history 
        WHERE delivery_number = :delivery_number
    ");
    $totalStmt->execute([':delivery_number' => $deliveryNumber]);
    $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $newTotal = $totalResult['total'] ?? 0;

    if ($deliveryDate !== null) {
        $updateDeliveryStmt = $pdo->prepare("
            UPDATE for_deliveries 
            SET total_amount = :total_amount,
                delivery_date = :delivery_date
            WHERE delivery_number = :delivery_number
        ");
        $updateDeliveryStmt->execute([
            ':total_amount' => $newTotal,
            ':delivery_date' => $deliveryDate,
            ':delivery_number' => $deliveryNumber
        ]);
    } else {
        $updateDeliveryStmt = $pdo->prepare("
            UPDATE for_deliveries 
            SET total_amount = :total_amount 
            WHERE delivery_number = :delivery_number
        ");
        $updateDeliveryStmt->execute([
            ':total_amount' => $newTotal,
            ':delivery_number' => $deliveryNumber
        ]);
    }

    return [
        'success' => true,
        'message' => "Successfully updated {$updatedCount} item(s)" . ($deliveryDate !== null ? " and delivery date to {$deliveryDate}" : ""),
        'updated_count' => $updatedCount,
        'new_total' => $newTotal,
        'delivery_date' => $deliveryDate
    ];
}

// ==============================================
// MAIN EXECUTION - ROUTE ACTIONS
// ==============================================

$action = $_POST['action'] ?? '';

// ==============================================
// ACTION 1: UPDATE ORDER STATUS
// ==============================================
if ($action === 'update_order_status') {
    $deliveryNumber = trim($_POST['delivery_number'] ?? '');
    $newStatus = trim($_POST['status'] ?? '');

    if (empty($deliveryNumber)) {
        echo json_encode(['success' => false, 'message' => 'Delivery number is required']);
        exit;
    }

    if (!isValidStatus($newStatus)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        date_default_timezone_set('Asia/Manila');
        $currentDateTime = date('j F Y g:i A');

        $deliveryInfo = getCurrentDeliveryStatus($pdo, $deliveryNumber);
        if ($deliveryInfo === null) {
            throw new Exception("Delivery #{$deliveryNumber} not found");
        }

        $oldStatus = $deliveryInfo['status'];

        $updateResult = updateDeliveryStatus($pdo, $deliveryNumber, $newStatus);
        if (!$updateResult) {
            throw new Exception("Failed to update delivery status");
        }

        $inventoryErrors = [];

        // PENDING/CREDIT -> PAID: Deduct stock
        if ($newStatus === 'PAID' && $oldStatus !== 'PAID') {
            $inventoryResult = processPaidOrderInventory($pdo, $deliveryNumber, $currentDateTime);
            $inventoryErrors = $inventoryResult['errors'] ?? [];
        }

        // PAID -> CANCELLED: Restore stock
        if ($newStatus === 'CANCELLED' && $oldStatus === 'PAID') {
            processCancelledPaidOrderInventory($pdo, $deliveryNumber, $currentDateTime);
        }

        // PAID -> CREDIT: Restore stock
        if ($newStatus === 'CREDIT' && $oldStatus === 'PAID') {
            processCreditFromPaidInventory($pdo, $deliveryNumber, $currentDateTime);
        }

        // PAID -> PENDING: Restore stock
        if ($newStatus === 'PENDING' && $oldStatus === 'PAID') {
            processCancelledPaidOrderInventory($pdo, $deliveryNumber, $currentDateTime);
        }

        $historyUpdateResult = updateOrderHistoryStatus($pdo, $deliveryNumber, $newStatus);
        if (!$historyUpdateResult) {
            throw new Exception("Failed to update order history status");
        }

        $pdo->commit();

        $message = "Order status updated to {$newStatus} successfully!";

        if ($newStatus === 'PAID' && $oldStatus !== 'PAID') {
            if (empty($inventoryErrors)) {
                $message .= " Inventory has been deducted.";
            } else {
                $message .= " Inventory deduction had errors: " . implode(", ", $inventoryErrors) . " Please check stock manually.";
            }
        } elseif ($newStatus === 'CANCELLED' && $oldStatus === 'PAID') {
            $message .= " Inventory has been restored.";
        } elseif ($newStatus === 'CREDIT' && $oldStatus === 'PENDING') {
            $message .= " Order has been placed on credit. No inventory deducted.";
        } elseif ($newStatus === 'CREDIT' && $oldStatus === 'PAID') {
            $message .= " Inventory has been restored. Order is now on credit.";
        } elseif ($newStatus === 'PENDING' && $oldStatus === 'PAID') {
            $message .= " Inventory has been restored. Order is now pending.";
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'status' => $newStatus,
            'old_status' => $oldStatus,
            'delivery_number' => $deliveryNumber,
            'inventory_errors' => $inventoryErrors
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        error_log("Update Order Status Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ==============================================
// ACTION 2: REMOVE ORDER ITEM
// ==============================================
elseif ($action === 'remove_order_item') {
    $deliveryNumber = trim($_POST['delivery_number'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');

    if (empty($deliveryNumber) || empty($productName)) {
        echo json_encode(['success' => false, 'message' => 'Delivery number and product name are required']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $result = removeOrderItem($pdo, $deliveryNumber, $productName);
        $pdo->commit();
        echo json_encode($result);
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        error_log("Remove Order Item Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ==============================================
// ACTION 3: UPDATE ORDER ITEMS (BULK EDIT)
// ==============================================
elseif ($action === 'update_order_items') {
    $deliveryNumber = trim($_POST['delivery_number'] ?? '');
    $itemsJson = $_POST['items'] ?? '';
    $deliveryDate = isset($_POST['delivery_date']) && !empty($_POST['delivery_date']) ? trim($_POST['delivery_date']) : null;

    if (empty($deliveryNumber)) {
        echo json_encode(['success' => false, 'message' => 'Delivery number is required']);
        exit;
    }

    if (empty($itemsJson)) {
        echo json_encode(['success' => false, 'message' => 'Items data is required']);
        exit;
    }

    $items = json_decode($itemsJson, true);

    if (!is_array($items) || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Invalid items data format']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $result = updateOrderItems($pdo, $deliveryNumber, $items, $deliveryDate);
        $pdo->commit();
        echo json_encode($result);
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        error_log("Update Order Items Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ==============================================
// ACTION 4: DELETE ORDER (CANCELLED)
// ==============================================
elseif ($action === 'delete_order') {
    $deliveryNumber = trim($_POST['delivery_number'] ?? '');

    if (empty($deliveryNumber)) {
        echo json_encode(['success' => false, 'message' => 'Delivery number is required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $checkStmt = $pdo->prepare("SELECT delivery_number FROM for_deliveries WHERE delivery_number = :delivery_number");
        $checkStmt->execute([':delivery_number' => $deliveryNumber]);
        $exists = $checkStmt->fetch();

        if (!$exists) {
            throw new Exception("Delivery #{$deliveryNumber} not found");
        }

        $stmt1 = $pdo->prepare("DELETE FROM order_status_history WHERE delivery_number = :delivery_number");
        $result1 = $stmt1->execute([':delivery_number' => $deliveryNumber]);

        $stmt2 = $pdo->prepare("DELETE FROM for_deliveries WHERE delivery_number = :delivery_number");
        $result2 = $stmt2->execute([':delivery_number' => $deliveryNumber]);

        if (!$result1 && !$result2) {
            throw new Exception("Failed to delete order data");
        }

        $deletedFromHistory = $stmt1->rowCount();
        $deletedFromDeliveries = $stmt2->rowCount();

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "{$deliveryNumber} deleted!",
            'deleted_from_history' => $deletedFromHistory,
            'deleted_from_deliveries' => $deletedFromDeliveries
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        error_log("Delete Order Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ==============================================
// ACTION 5: REORDER ITEMS (drag-and-drop)
// ==============================================
elseif ($action === 'reorder_items') {
    $deliveryNumber = trim($_POST['delivery_number'] ?? '');
    $orderJson = $_POST['order'] ?? '[]';

    $newOrder = json_decode($orderJson, true);

    if (empty($deliveryNumber) || !is_array($newOrder) || empty($newOrder)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // ✅ Uses order_id — NOT sort_order
        $stmt = $pdo->prepare("
            UPDATE order_status_history 
            SET order_id = ? 
            WHERE id = ? AND delivery_number = ?
        ");

        foreach ($newOrder as $item) {
            $id = intval($item['id'] ?? 0);
            $position = intval($item['sort_order'] ?? 0);

            if ($id > 0) {
                $stmt->execute([$position, $id, $deliveryNumber]);
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Item order updated successfully'
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();

        // ✅ Send the actual error message so we can debug
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}
// ==============================================
// INVALID ACTION
// ==============================================
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    exit;
}
?>