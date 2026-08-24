<?php
// Customer_API/get_order_details.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../DB_Conn/config.php';

$deliveryNumber = isset($_POST['delivery_number']) ? trim($_POST['delivery_number']) : '';
$userAccNumber = isset($_POST['acc_number']) ? trim($_POST['acc_number']) : '';

if (empty($deliveryNumber) || empty($userAccNumber)) {
    echo json_encode(['success' => false, 'message' => 'Delivery number and account number are required']);
    exit;
}

try {
    // Verify that this delivery belongs to the user
    $verifyStmt = $pdo->prepare("
        SELECT delivery_number 
        FROM for_deliveries 
        WHERE delivery_number = :delivery_number 
        AND acc_number = :acc_number
    ");
    $verifyStmt->execute([
        ':delivery_number' => $deliveryNumber,
        ':acc_number' => $userAccNumber
    ]);
    
    if (!$verifyStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: This order does not belong to you']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            product_name,
            selling_price,
            pieces,
            unit,
            total_amount,
            date_time_sold,
            status
        FROM order_status_history 
        WHERE delivery_number = :delivery_number
        ORDER BY id ASC
    ");
    $stmt->execute([':delivery_number' => $deliveryNumber]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'No items found for this delivery']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>