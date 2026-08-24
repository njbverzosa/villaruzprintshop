<?php
// Customer_API/add_to_cart.php

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 1. CHECK IF USER IS LOGGED IN
// ==============================================
if (!isset($_SESSION['user_role']) || !isset($_SESSION['user_id']) || !isset($_SESSION['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// ==============================================
// 2. VERIFY CSRF TOKEN
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
    echo json_encode(['success' => false, 'message' => 'Please refresh the page.']);
    exit();
}

// ==============================================
// 3. GET USER DATA FROM SESSION
// ==============================================
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$userAccNumber = $_SESSION['acc_number'];

$action = $_POST['action'] ?? '';

try {
    if ($action !== 'add_to_cart') {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
    }
    
    $productId = $_POST['product_id'] ?? 0;
    $quantity = intval($_POST['quantity'] ?? 0);
    
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
        exit();
    }
    
    // ==============================================
    // 4. GET PRODUCT DETAILS
    // ==============================================
    $stmt = $pdo->prepare("SELECT * FROM merchandise_inventory WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    // ==============================================
    // 5. UPDATE USER ONLINE TIME (EVERY ADD TO CART)
    // ==============================================
    date_default_timezone_set('Asia/Manila');
    $currentTime = date('g:i A'); // Format: 9:50 AM
    
    // Update online_time - this runs EVERY TIME user adds to cart
    if ($userRole === 'Customer') {
        $updateStmt = $pdo->prepare("UPDATE customers SET online_time = ? WHERE acc_number = ?");
        $updateStmt->execute([$currentTime, $userAccNumber]);
    } elseif ($userRole === 'Admin') {
        $updateStmt = $pdo->prepare("UPDATE admins SET online_time = ? WHERE acc_number = ?");
        $updateStmt->execute([$currentTime, $userAccNumber]);
    }
    
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
    // 7. CHECK IF PRODUCT EXISTS IN CART
    // ==============================================
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
        
        $stmt = $pdo->prepare("
            UPDATE cart 
            SET pieces = ?, total_amount = ?, date_time_add = ? 
            WHERE id = ?
        ");
        $stmt->execute([$newPieces, $newTotalAmount, $dateTimeAdd, $existingItem['id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => "Item on cart updated",
            'order_number' => $orderNumber
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
            'message' => "An item(s) added to cart",
            'order_number' => $orderNumber
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>