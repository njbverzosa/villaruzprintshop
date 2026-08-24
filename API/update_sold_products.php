<?php
// API/update_sold_products.php
session_start();
header('Content-Type: application/json');

require_once '../DB_Conn/config.php';

// Check if user is logged in
if (!isset($_SESSION['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get user's first name
$firstName = '';
if (isset($_SESSION['f_name'])) {
    $userName = $_SESSION['f_name'];
    $firstName = explode(' ', trim($userName))[0];
} else {
    try {
        $stmt = $pdo->prepare("SELECT f_name FROM admins WHERE acc_number = ?");
        $stmt->execute([$_SESSION['acc_number']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $firstName = explode(' ', trim($user['f_name']))[0];
            $_SESSION['f_name'] = $user['f_name'];
        } else {
            $firstName = 'Unknown';
        }
    } catch (Exception $e) {
        $firstName = 'Unknown';
    }
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$action = $_POST['action'] ?? '';

// Handle update status
if ($action === 'update_status') {
    $orderId = intval($_POST['order_id']);
    $status = $_POST['status'];
    $productName = $_POST['product_name'];
    $pieces = intval($_POST['pieces']);

    try {
        $pdo->beginTransaction();
        date_default_timezone_set('Asia/Manila');
        $displayDateTime = date('j F Y g:i A');

        if ($status === 'PAID') {
            // Get product details first
            $stmt = $pdo->prepare("SELECT id, qty_on_hand, product_name FROM merchandise_inventory WHERE product_name = :product_name");
            $stmt->execute([':product_name' => $productName]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception('Product not found in inventory');
            }

            // Check if enough stock
            if ($pieces > $product['qty_on_hand']) {
                throw new Exception("Insufficient stock! Available: {$product['qty_on_hand']}, Requested: {$pieces}");
            }

            // Deduct from inventory
            $newQty = $product['qty_on_hand'] - $pieces;
            $stmt = $pdo->prepare("UPDATE merchandise_inventory SET qty_on_hand = :qty, last_restocked = :last_restocked WHERE product_name = :product_name");
            $stmt->execute([
                ':qty' => $newQty,
                ':last_restocked' => $displayDateTime,
                ':product_name' => $productName
            ]);
        }

        // Update order status
        $stmt = $pdo->prepare("UPDATE order_status_history SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $orderId]);

        // Log the action
        $logDetails = "Order #{$orderId} status changed to {$status} for product: {$productName}";
        $logStmt = $pdo->prepare("INSERT INTO logs (name, action, details, created_at) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$firstName, "Update Order Status", $logDetails, $displayDateTime]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Order status updated to {$status} successfully!"
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle restore order
if ($action === 'restore_order') {
    $orderId = intval($_POST['order_id']);
    $productName = $_POST['product_name'];
    $pieces = intval($_POST['pieces']);

    try {
        $pdo->beginTransaction();
        date_default_timezone_set('Asia/Manila');
        $displayDateTime = date('j F Y g:i A');

        // Get the order details first
        $stmt = $pdo->prepare("SELECT * FROM order_status_history WHERE id = :id");
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception('Order not found');
        }

        // Only restore if status is PAID
        if ($order['status'] !== 'PAID') {
            throw new Exception('Only PAID orders can be restored');
        }

        // Update inventory - add back the quantity
        $stmt = $pdo->prepare("UPDATE merchandise_inventory SET qty_on_hand = qty_on_hand + :pieces, last_restocked = :last_restocked WHERE product_name = :product_name");
        $stmt->execute([
            ':pieces' => $pieces,
            ':last_restocked' => $displayDateTime,
            ':product_name' => $productName
        ]);

        // Delete the order record
        $stmt = $pdo->prepare("DELETE FROM order_status_history WHERE id = :id");
        $stmt->execute([':id' => $orderId]);

        // Log the action
        $logDetails = "Restored order #{$orderId} - Added {$pieces} pcs of {$productName} back to inventory";
        $logStmt = $pdo->prepare("INSERT INTO logs (name, action, details, created_at) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$firstName, "Restore Order", $logDetails, $displayDateTime]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Order restored successfully! {$pieces} item(s) added back to inventory."
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>