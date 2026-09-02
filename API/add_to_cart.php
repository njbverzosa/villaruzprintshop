<?php
// API/add_to_cart.php

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 1. CHECK IF USER IS LOGGED IN USING SESSION
// ==============================================
function isLoggedIn() {
    return isset($_SESSION['user_role']) && 
           isset($_SESSION['user_id']) && 
           isset($_SESSION['acc_number']);
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first to add items to cart']);
    exit();
}

// ==============================================
// 2. GET USER DATA FROM SESSION
// ==============================================
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$userAccNumber = $_SESSION['acc_number'];

// ==============================================
// 3. VALIDATE REQUEST METHOD
// ==============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// ==============================================
// 4. VERIFY CSRF TOKEN
// ==============================================
if (!isset($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'CSRF token missing from request']);
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'CSRF token not found in session']);
    exit();
}

if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit();
}

// ==============================================
// 5. PROCESS ACTION
// ==============================================
$action = $_POST['action'] ?? '';

try {
    if ($action !== 'add_to_cart') {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
    }
    
    $productId = $_POST['product_id'] ?? 0;
    $quantity = intval($_POST['quantity'] ?? 0);
    
    // Validate product ID
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit();
    }
    
    // Validate quantity
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid quantity. Please enter a number greater than 0.']);
        exit();
    }
    
    // Get product details
    $stmt = $pdo->prepare("SELECT * FROM merchandise_inventory WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    // Check if product has selling price
    if (empty($product['selling_price']) || $product['selling_price'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Product has no selling price set']);
        exit();
    }
    
    // ==============================================
    // STOCK CHECK REMOVED - Allow adding items regardless of stock
    // ==============================================
    
    // ==============================================
    // 6. GET OR CREATE ORDER NUMBER FOR TODAY
    // ==============================================
    $todayDate = date('j M Y');
    $orderNumber = null;
    
    // Check for existing cart items for this user today
    $stmt = $pdo->prepare("
        SELECT DISTINCT order_number 
        FROM cart 
        WHERE acc_number = ? 
        AND order_number LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$userAccNumber, $userAccNumber . '_%_' . $todayDate]);
    $existingOrder = $stmt->fetch();
    
    if ($existingOrder) {
        // Use existing order number
        $orderNumber = $existingOrder['order_number'];
    } else {
        // Generate new order number
        $stmt = $pdo->prepare("
            SELECT order_number 
            FROM cart 
            WHERE order_number LIKE ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([$userAccNumber . '_%_' . $todayDate]);
        $lastOrder = $stmt->fetch();
        
        $sequence = 1;
        if ($lastOrder) {
            preg_match('/' . preg_quote($userAccNumber, '/') . '_(\d+)_/', $lastOrder['order_number'], $matches);
            if (isset($matches[1])) {
                $sequence = intval($matches[1]) + 1;
            }
        }
        
        $sequencePadded = str_pad($sequence, 5, '0', STR_PAD_LEFT);
        $orderNumber = $userAccNumber . '_' . $sequencePadded . '_' . $todayDate;
    }
    
    $dateTimeAdd = date('j M Y');
    $totalAmount = $product['selling_price'] * $quantity;
    
    // ==============================================
    // 7. ADD OR UPDATE CART ITEM
    // ==============================================
    // Check if product already exists in cart with this order_number
    $stmt = $pdo->prepare("
        SELECT * FROM cart 
        WHERE order_number = ? AND product_name = ?
    ");
    $stmt->execute([$orderNumber, $product['product_name']]);
    $existingItem = $stmt->fetch();
    
    if ($existingItem) {
        // Update existing cart item - increment pieces
        $newPieces = $existingItem['pieces'] + $quantity;
        $newTotalAmount = $product['selling_price'] * $newPieces;
        
        // REMOVED: Stock check when updating quantity
        
        $stmt = $pdo->prepare("
            UPDATE cart 
            SET pieces = ?, total_amount = ?, date_time_add = ? 
            WHERE id = ?
        ");
        $stmt->execute([$newPieces, $newTotalAmount, $dateTimeAdd, $existingItem['id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => "Updated {$product['product_name']} quantity to {$newPieces}",
            'order_number' => $orderNumber,
            'cart_count' => getCartCount($pdo, $userAccNumber, $orderNumber)
        ]);
    } else {
        // Insert new cart item
        $stmt = $pdo->prepare("
            INSERT INTO cart (acc_number, order_number, product_name, unit, selling_price, pieces, total_amount, date_time_add) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userAccNumber, 
            $orderNumber, 
            $product['product_name'], 
            $product['unit'], 
            $product['selling_price'], 
            $quantity, 
            $totalAmount, 
            $dateTimeAdd
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => "Added {$quantity} × {$product['product_name']} to cart",
            'order_number' => $orderNumber,
            'cart_count' => getCartCount($pdo, $userAccNumber, $orderNumber)
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// ==============================================
// 8. HELPER FUNCTION: GET CART COUNT
// ==============================================
function getCartCount($pdo, $accNumber, $orderNumber) {
    $stmt = $pdo->prepare("SELECT SUM(pieces) as total FROM cart WHERE acc_number = ? AND order_number = ?");
    $stmt->execute([$accNumber, $orderNumber]);
    $result = $stmt->fetch();
    return intval($result['total'] ?? 0);
}
?>