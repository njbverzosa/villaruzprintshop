<?php
// API/sold_products.php
session_start();
header('Content-Type: application/json');

require_once '../DB_Conn/config.php';

// Check if user is logged in
if (!isset($_SESSION['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get user's first name from session (using acc_number to fetch from DB if needed)
$firstName = '';
if (isset($_SESSION['f_name'])) {
    $userName = $_SESSION['f_name'];
    $firstName = explode(' ', trim($userName))[0];
} else {
    // Fetch from database if not in session
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

// Handle sell product
if ($action === 'sell_product') {
    if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $productId = intval($_POST['product_id']);
    $quantityToSell = intval($_POST['quantity']);
    $submittedProductName = $_POST['product_name'] ?? '';
    $submittedSellingPrice = floatval($_POST['selling_price'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');

    if ($quantityToSell <= 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0']);
        exit;
    }

    if (empty($purpose)) {
        echo json_encode(['success' => false, 'message' => 'Please select a purpose for this sale']);
        exit;
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Set timezone to Asia/Manila
        date_default_timezone_set('Asia/Manila');
        $displayDateTime = date('j F Y g:i A');

        // Get product details including unit and official selling_price
        $stmt = $pdo->prepare("SELECT * FROM merchandise_inventory WHERE id = :id");
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception('Product not found');
        }

        // Use the official selling price from the database
        $officialSellingPrice = floatval($product['selling_price']);
        $totalAmount = $officialSellingPrice * $quantityToSell;
        $unit = $product['unit'] ?? 'Pcs';

        // Determine status based on purpose
        $status = ($purpose === 'In Use') ? 'PAID' : 'PENDING';

        // Insert into order_status_history
        $stmt = $pdo->prepare("INSERT INTO order_status_history 
            (product_name, status, pieces, unit, selling_price, total_amount, date_time_sold, note) 
            VALUES 
            (:product_name, :status, :pieces, :unit, :selling_price, :total_amount, :date_time_sold, :note)");

        $result = $stmt->execute([
            ':product_name' => $product['product_name'],
            ':status' => $status,
            ':pieces' => $quantityToSell,
            ':unit' => $unit,
            ':selling_price' => $officialSellingPrice,
            ':total_amount' => $totalAmount,
            ':date_time_sold' => $displayDateTime,
            ':note' => $purpose
        ]);

        if (!$result) {
            throw new Exception('Failed to insert into order_status_history');
        }

        $recordId = $pdo->lastInsertId();

        // Track old quantity for logging
        $oldQty = 0;
        $newQtyOnHand = 0;

        // If status is PAID (because purpose is "In Use"), deduct from inventory
        if ($status === 'PAID') {
            $stmt = $pdo->prepare("SELECT qty_on_hand FROM merchandise_inventory WHERE id = :product_id");
            $stmt->execute([':product_id' => $productId]);
            $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
            $oldQty = $inventory['qty_on_hand'];

            if ($quantityToSell > $oldQty) {
                throw new Exception("Insufficient stock for {$product['product_name']}. Available: {$oldQty}, Requested: {$quantityToSell}");
            }

            $newQtyOnHand = $oldQty - $quantityToSell;
            $stmt = $pdo->prepare("UPDATE merchandise_inventory SET qty_on_hand = :qty_on_hand, last_restocked = :last_restocked WHERE id = :product_id");
            $stmt->execute([
                ':qty_on_hand' => $newQtyOnHand,
                ':last_restocked' => $displayDateTime,
                ':product_id' => $productId
            ]);
        }

        // Insert into logs
        $logDetails = "Sold product: {$product['product_name']} | Quantity: {$quantityToSell} {$unit} | Price: ₱{$officialSellingPrice} each | Total: ₱{$totalAmount} | Purpose: {$purpose} | Status: {$status}";
        if ($status === 'PAID') {
            $logDetails .= " | Inventory deducted from {$oldQty} to {$newQtyOnHand}";
        }

        $logStmt = $pdo->prepare("INSERT INTO logs (name, action, details, created_at) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$firstName, "Sold Product", $logDetails, $displayDateTime]);

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => $status === 'PAID'
                ? 'Sale recorded and inventory deducted for In Use purpose!'
                : 'Sale recorded successfully (inventory not deducted)',
            'pieces' => $quantityToSell,
            'unit' => $unit,
            'selling_price' => $officialSellingPrice,
            'total_amount' => $totalAmount,
            'product_name' => $product['product_name'],
            'date_time_sold' => $displayDateTime,
            'display_text' => $quantityToSell . ' ' . $unit,
            'record_id' => $recordId,
            'purpose' => $purpose,
            'status' => $status
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>