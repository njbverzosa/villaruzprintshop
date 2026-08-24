<?php
// API/update_product.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../DB_Conn/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userName = $_SESSION['fullname'];  
$firstName = explode(' ', trim($userName))[0];  
 
// Verify CSRF token
if (!isset($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'CSRF token missing from request']);
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'CSRF token not found in session']);
    exit;
}

if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid CSRF token. Please refresh the page and try again.'
    ]);
    exit;
}

$action = $_POST['action'] ?? '';

// Handle update product
if ($action === 'update_product') {
    if (!isset($_POST['product_id']) || !isset($_POST['product_name']) || !isset($_POST['selling_price'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $productId = intval($_POST['product_id']);
    $productName = trim($_POST['product_name']);
    $unit = trim($_POST['unit'] ?? 'Pcs');
    $quantity = intval($_POST['quantity'] ?? 0);
    $sellingPrice = floatval($_POST['selling_price'] ?? 0);
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Get old product data for logging
    $oldStmt = $pdo->prepare("SELECT * FROM merchandise_inventory WHERE id = :id");
    $oldStmt->execute([':id' => $productId]);
    $oldProduct = $oldStmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldProduct) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    // Validation
    if (empty($productName)) {
        echo json_encode(['success' => false, 'message' => 'Product name is required']);
        exit;
    }

    if ($sellingPrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Unit cost must be greater than 0']);
        exit;
    }

    if ($quantity < 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity cannot be negative']);
        exit;
    }

    try {
        date_default_timezone_set('Asia/Manila');
         $formattedDate = date('j F Y g:i A');

        $pdo->beginTransaction();

        // Update product
        $stmt = $pdo->prepare("UPDATE merchandise_inventory SET product_name = :product_name, unit = :unit, qty_on_hand = :qty_on_hand, selling_price = :selling_price, description = :description, last_restocked = :last_restocked WHERE id = :id");
        $result = $stmt->execute([
            ':product_name' => $productName,
            ':unit' => $unit,
            ':qty_on_hand' => $quantity,
            ':selling_price' => $sellingPrice,
            ':description' => $description,
            ':last_restocked' => $formattedDate,
            ':id' => $productId
        ]);

        if ($result) {
            // Prepare change details for log
            $changes = [];
            if ($oldProduct['product_name'] != $productName) $changes[] = "Name: '{$oldProduct['product_name']}' → '{$productName}'";
            if ($oldProduct['unit'] != $unit) $changes[] = "Unit: '{$oldProduct['unit']}' → '{$unit}'";
            if ($oldProduct['qty_on_hand'] != $quantity) $changes[] = "Quantity: {$oldProduct['qty_on_hand']} → {$quantity}";
            if ($oldProduct['selling_price'] != $sellingPrice) $changes[] = "Price: ₱{$oldProduct['selling_price']} → ₱{$sellingPrice}";
            
            $logDetails = "Updated product: {$oldProduct['product_name']} (ID: {$productId}) | Changes: " . (empty($changes) ? "No changes" : implode(", ", $changes));
            
            // Insert into logs
            $logStmt = $pdo->prepare("INSERT INTO logs (name, action, details, created_at) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$firstName, "Updated Product", $logDetails, $formattedDate]);
            
            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Product updated successfully',
                'product_id' => $productId,
                'last_updated' => $formattedDate
            ]);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to update product']);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>