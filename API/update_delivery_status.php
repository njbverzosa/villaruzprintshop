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
 * 
 * @param string $status The status to validate
 * @return bool True if status is valid
 */
function isValidStatus($status)
{
    $allowedStatuses = ['PENDING', 'PAID', 'CANCELLED', 'CREDIT', 'PACKING', 'SHIPPED', 'OFD', 'DELIVERED'];
    return in_array($status, $allowedStatuses);
}

/**
 * Get current status of a delivery order
 * 
 * @param PDO $pdo Database connection
 * @param string $deliveryNumber Delivery number to lookup
 * @return array|null Current status and delivery_date or null if not found
 */
function getCurrentDeliveryStatus($pdo, $deliveryNumber)
{
    $stmt = $pdo->prepare("SELECT status, delivery_date FROM for_deliveries WHERE delivery_number = :delivery_number");
    $stmt->execute([':delivery_number' => $deliveryNumber]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update delivery status and delivery date in for_deliveries table
 * 
 * @param PDO $pdo Database connection
 * @param string $deliveryNumber Delivery number
 * @param string $newStatus New status to set
 * @param string|null $deliveryDate New delivery date (optional)
 * @return bool True on success
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
 * 
 * @param PDO $pdo Database connection
 * @param string $deliveryNumber Delivery number
 * @param string $newStatus New status to set
 * @return bool True on success
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
 * 
 * @param PDO $pdo Database connection
 * @param string $deliveryNumber Delivery number
 * @return array Array of order items with product_name and pieces
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
 * 
 * @param PDO $pdo Database connection
 * @param string $productName Product name to check
 * @return array|null Product data or null if not found
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
 * 
 * @param PDO $pdo Database connection
 * @param string $productName Product name
 * @param float $sellingPrice New selling price
 * @param string $dateTime Current date for last_restocked
 * @return bool True on success
 */
function updateInventorySellingPrice($pdo, $productName, $sellingPrice, $dateTime)
{
    // Check if product exists
    $currentStock = getProductStock($pdo, $productName);
    
    if (!$currentStock) {
        // Product not found in inventory, skip
        return false;
    }
    
    // Update selling price and last_restocked
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
 * Deduct quantity from product inventory (when order is PAID)
 * 
 * @param PDO $pdo Database connection
 * @param string $productName Product name
 * @param int $quantity Quantity to deduct
 * @param string $dateTime Current date for last_restocked
 * @return bool True on success
 * @throws Exception If insufficient stock
 */
function deductProductStock($pdo, $productName, $quantity, $dateTime)
{
    // Check current stock first
    $currentStock = getProductStock($pdo, $productName);

    if (!$currentStock) {
        // Product not found - log but don't throw
        error_log("Product '{$productName}' not found in inventory during deduction");
        return false;
    }

    if ($currentStock['qty_on_hand'] < $quantity) {
        // Insufficient stock - log but allow deduction to negative (or zero)
        error_log("Insufficient stock for '{$productName}'. Available: {$currentStock['qty_on_hand']}, Required: {$quantity}");
        // Deduct whatever is available (set to 0)
        $newQuantity = max(0, $currentStock['qty_on_hand'] - $quantity);
    } else {
        $newQuantity = $currentStock['qty_on_hand'] - $quantity;
    }

    // Perform deduction
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
 * Restore quantity to product inventory (when PAID order is CANCELLED)
 * 
 * @param PDO $pdo Database connection
 * @param string $productName Product name
 * @param int $quantity Quantity to restore
 * @param string $dateTime Current date for last_restocked
 * @return bool True on success
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
 * Continues even if some items fail to deduct
 * 
 * @param PDO $pdo Database connection
 * @param string $deliveryNumber Delivery number
 * @param string $dateTime Current date time
 * @return array Array with success flag and errors if any
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
            $result = deductProductStock(
                $pdo,
                $item['product_name'],
                intval($item['pieces']),
                $dateTime
            );
            if ($result) {
                $successCount++;
            } else {
                $errors[] = "Failed to deduct stock for '{$item['product_name']}'";
            }
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
 * 
 * @param PDO $pdo Database connection
 * @param string $deliveryNumber Delivery number
 * @param string $dateTime Current date time
 */
function processCancelledPaidOrderInventory($pdo, $deliveryNumber, $dateTime)
{
    $orderItems = getDeliveryOrderItems($pdo, $deliveryNumber);

    if (!empty($orderItems)) {
        foreach ($orderItems as $item) {
            restoreProductStock(
                $pdo,
                $item['product_name'],
                intval($item['pieces']),
                $dateTime
            );
        }
    }
}

/**
 * Process inventory restoration when PAID order is changed to CREDIT
 * 
 * @param PDO $pdo Database connection
 * @param string $deliveryNumber Delivery number
 * @param string $dateTime Current date time
 */
function processCreditFromPaidInventory($pdo, $deliveryNumber, $dateTime)
{
    $orderItems = getDeliveryOrderItems($pdo, $deliveryNumber);

    if (!empty($orderItems)) {
        foreach ($orderItems as $item) {
            restoreProductStock(
                $pdo,
                $item['product_name'],
                intval($item['pieces']),
                $dateTime
            );
        }
    }
}

/**
 * Remove an item from order_status_history
 * 
 * @param PDO $pdo Database connection
 * @param string $deliveryNumber Delivery number
 * @param string $productName Product name to remove
 * @return array Result with success flag and message
 */
function removeOrderItem($pdo, $deliveryNumber, $productName)
{
    // Check if the item exists
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

    // Delete the item from order_status_history
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

    // Check if there are any items left for this delivery
    $checkRemainingStmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM order_status_history 
        WHERE delivery_number = :delivery_number
    ");
    $checkRemainingStmt->execute([':delivery_number' => $deliveryNumber]);
    $remaining = $checkRemainingStmt->fetch(PDO::FETCH_ASSOC);

    // If no items left, delete the delivery record
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
 * 
 * @param PDO $pdo Database connection
 * @param string $deliveryNumber Delivery number
 * @param array $items Array of items to update
 * @param string|null $deliveryDate New delivery date (optional)
 * @return array Result with success flag and message
 */
function updateOrderItems($pdo, $deliveryNumber, $items, $deliveryDate = null)
{
    $updatedCount = 0;
    $errors = [];
    $currentDateTime = date('j M Y');

    foreach ($items as $item) {
        // Validate required fields
        if (empty($item['product_name']) || empty($item['pieces']) || !isset($item['selling_price']) || empty($item['total_amount'])) {
            $errors[] = "Missing required fields for an item";
            continue;
        }

        // Check if the item exists - use ID if available, otherwise use product_name
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

        // Update the item with selling_price
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
            
            // Update the selling price in merchandise_inventory
            try {
                updateInventorySellingPrice($pdo, $item['product_name'], $item['selling_price'], $currentDateTime);
            } catch (Exception $e) {
                // Log but don't fail the main update
                error_log("Failed to update selling price for {$item['product_name']}: " . $e->getMessage());
            }
        } else {
            $errors[] = "Failed to update '{$item['product_name']}'";
        }
    }

    if (!empty($errors)) {
        throw new Exception("Update errors: " . implode(", ", $errors));
    }

    // Update the total amount in for_deliveries table
    $totalStmt = $pdo->prepare("
        SELECT SUM(total_amount) as total 
        FROM order_status_history 
        WHERE delivery_number = :delivery_number
    ");
    $totalStmt->execute([':delivery_number' => $deliveryNumber]);
    $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $newTotal = $totalResult['total'] ?? 0;

    // Update delivery total and optionally delivery date
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
    // Get and validate input parameters
    $deliveryNumber = trim($_POST['delivery_number'] ?? '');
    $newStatus = trim($_POST['status'] ?? '');

    // Validate delivery number
    if (empty($deliveryNumber)) {
        echo json_encode(['success' => false, 'message' => 'Delivery number is required']);
        exit;
    }

    // Validate status value
    if (!isValidStatus($newStatus)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit;
    }

    try {
        // Start database transaction - all operations must succeed or rollback
        $pdo->beginTransaction();

        // Get current date for last_restocked field - Format: j M Y (e.g., "15 May 2026")
        $currentDateTime = date('j M Y');

        // STEP 1: Get current status before making any changes
        $deliveryInfo = getCurrentDeliveryStatus($pdo, $deliveryNumber);

        if ($deliveryInfo === null) {
            throw new Exception("Delivery #{$deliveryNumber} not found");
        }

        $oldStatus = $deliveryInfo['status'];

        // STEP 2: Update status in for_deliveries table
        $updateResult = updateDeliveryStatus($pdo, $deliveryNumber, $newStatus);

        if (!$updateResult) {
            throw new Exception("Failed to update delivery status");
        }

        // STEP 3: Handle inventory changes based on status transition
        $inventoryErrors = [];
        
        // Case 1: PENDING/CREDIT -> PAID
        // Action: Deduct stock from inventory (continues even if errors)
        if ($newStatus === 'PAID' && $oldStatus !== 'PAID') {
            $inventoryResult = processPaidOrderInventory($pdo, $deliveryNumber, $currentDateTime);
            $inventoryErrors = $inventoryResult['errors'] ?? [];
        }
        
        // Case 2: PAID -> CANCELLED
        // Action: Restore stock back to inventory
        if ($newStatus === 'CANCELLED' && $oldStatus === 'PAID') {
            processCancelledPaidOrderInventory($pdo, $deliveryNumber, $currentDateTime);
        }
        
        // Case 3: PENDING -> CREDIT
        // Action: No inventory change (reservation only)
        if ($newStatus === 'CREDIT' && $oldStatus === 'PENDING') {
            // No inventory changes needed for CREDIT
        }
        
        // Case 4: PAID -> CREDIT
        // Action: Restore stock back to inventory
        if ($newStatus === 'CREDIT' && $oldStatus === 'PAID') {
            processCreditFromPaidInventory($pdo, $deliveryNumber, $currentDateTime);
        }
        
        // Case 5: CREDIT -> CANCELLED
        // Action: No inventory change
        if ($newStatus === 'CANCELLED' && $oldStatus === 'CREDIT') {
            // No inventory restoration needed
        }
        
        // Case 6: CREDIT -> PENDING
        // Action: No inventory change
        if ($newStatus === 'PENDING' && $oldStatus === 'CREDIT') {
            // No inventory changes needed
        }
        
        // Case 7: PAID -> PENDING
        // Action: Restore stock back to inventory
        if ($newStatus === 'PENDING' && $oldStatus === 'PAID') {
            processCancelledPaidOrderInventory($pdo, $deliveryNumber, $currentDateTime);
        }

        // STEP 4: Update status in order_status_history for all items
        $historyUpdateResult = updateOrderHistoryStatus($pdo, $deliveryNumber, $newStatus);

        if (!$historyUpdateResult) {
            throw new Exception("Failed to update order history status");
        }

        // STEP 5: Commit all changes to database
        $pdo->commit();

        // STEP 6: Prepare success response message
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
        // Rollback transaction on any error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Log error for debugging
        error_log("Update Order Status Error: " . $e->getMessage());

        // Return error response
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// ==============================================
// ACTION 2: REMOVE ORDER ITEM
// ==============================================
elseif ($action === 'remove_order_item') {
    $deliveryNumber = trim($_POST['delivery_number'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');

    // Validate inputs
    if (empty($deliveryNumber) || empty($productName)) {
        echo json_encode(['success' => false, 'message' => 'Delivery number and product name are required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Remove the item using the helper function
        $result = removeOrderItem($pdo, $deliveryNumber, $productName);

        $pdo->commit();

        echo json_encode($result);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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

    // Validate inputs
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

        // Update all items using the helper function with delivery date
        $result = updateOrderItems($pdo, $deliveryNumber, $items, $deliveryDate);

        $pdo->commit();

        echo json_encode($result);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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

    // Validate inputs
    if (empty($deliveryNumber)) {
        echo json_encode(['success' => false, 'message' => 'Delivery number is required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // First, check if the delivery exists
        $checkStmt = $pdo->prepare("SELECT delivery_number FROM for_deliveries WHERE delivery_number = :delivery_number");
        $checkStmt->execute([':delivery_number' => $deliveryNumber]);
        $exists = $checkStmt->fetch();

        if (!$exists) {
            throw new Exception("Delivery #{$deliveryNumber} not found");
        }

        // Delete from order_status_history
        $stmt1 = $pdo->prepare("DELETE FROM order_status_history WHERE delivery_number = :delivery_number");
        $result1 = $stmt1->execute([':delivery_number' => $deliveryNumber]);

        // Delete from for_deliveries
        $stmt2 = $pdo->prepare("DELETE FROM for_deliveries WHERE delivery_number = :delivery_number");
        $result2 = $stmt2->execute([':delivery_number' => $deliveryNumber]);

        // Check if at least one deletion was successful
        if (!$result1 && !$result2) {
            throw new Exception("Failed to delete order data");
        }

        // Log what was deleted
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
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Delete Order Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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