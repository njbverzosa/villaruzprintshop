<?php
// batch_update_order.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../DB_Conn/config.php';

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// Check authentication
if (!isset($_POST['acc_number']) || empty($_POST['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$accNumber = $_POST['acc_number'];
$deliveryNumber = $_POST['delivery_number'] ?? '';
$quantityUpdates = json_decode($_POST['quantity_updates'] ?? '[]', true);
$removalIds = json_decode($_POST['removal_ids'] ?? '[]', true);

if (empty($deliveryNumber)) {
    echo json_encode(['success' => false, 'message' => 'Delivery number is required']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Verify ownership
    $verifyStmt = $pdo->prepare("SELECT id FROM for_deliveries WHERE delivery_number = ? AND acc_number = ?");
    $verifyStmt->execute([$deliveryNumber, $accNumber]);
    if (!$verifyStmt->fetch()) {
        throw new Exception('Unauthorized: This order does not belong to you');
    }
    
    // Process removals
    if (!empty($removalIds)) {
        $placeholders = implode(',', array_fill(0, count($removalIds), '?'));
        $deleteStmt = $pdo->prepare("DELETE FROM order_status_history WHERE id IN ($placeholders) AND delivery_number = ?");
        $params = array_merge($removalIds, [$deliveryNumber]);
        $deleteStmt->execute($params);
    }
    
    // Process quantity updates
    foreach ($quantityUpdates as $update) {
        $orderId = $update['order_id'];
        $newQuantity = floatval($update['quantity']);
        
        // Get current order item
        $stmt = $pdo->prepare("SELECT * FROM order_status_history WHERE id = ? AND delivery_number = ?");
        $stmt->execute([$orderId, $deliveryNumber]);
        $order = $stmt->fetch();
        
        if ($order) {
            $newTotalAmount = $order['selling_price'] * $newQuantity;
            $updateStmt = $pdo->prepare("UPDATE order_status_history SET pieces = ?, total_amount = ? WHERE id = ?");
            $updateStmt->execute([$newQuantity, $newTotalAmount, $orderId]);
        }
    }
    
    // Update grand total in for_deliveries
    $totalStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as grand_total FROM order_status_history WHERE delivery_number = ?");
    $totalStmt->execute([$deliveryNumber]);
    $grandTotal = $totalStmt->fetch(PDO::FETCH_ASSOC)['grand_total'];
    
    // If no items left, delete the delivery record
    if ($grandTotal == 0) {
        $deleteDeliveryStmt = $pdo->prepare("DELETE FROM for_deliveries WHERE delivery_number = ? AND acc_number = ?");
        $deleteDeliveryStmt->execute([$deliveryNumber, $accNumber]);
    } else {
        $updateDeliveryStmt = $pdo->prepare("UPDATE for_deliveries SET total_amount = ? WHERE delivery_number = ? AND acc_number = ?");
        $updateDeliveryStmt->execute([$grandTotal, $deliveryNumber, $accNumber]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'All changes saved successfully',
        'new_grand_total' => $grandTotal
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>