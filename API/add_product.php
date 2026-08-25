<?php
// API/add_product.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 1. CHECK LOGIN STATUS
// ==============================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['acc_number']) || !isset($_SESSION['user_role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$accNumber = $_SESSION['acc_number'];

// ==============================================
// 2. FETCH USER NAME FROM DATABASE
// ==============================================
$userName = 'Unknown User';
if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT f_name FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userName = $user['f_name'];
    }
}

// Get first name only
$firstName = explode(' ', trim($userName))[0] ?? 'User';

// ==============================================
// 3. VERIFY CSRF TOKEN
// ==============================================
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

if ($action === 'add_product') {
    $productName = trim($_POST['product_name']);
    $unit = trim($_POST['unit']);
    $quantity = intval($_POST['quantity']);
    $sellingPrice = floatval($_POST['selling_price']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

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

    // Check if product already exists
    $checkStmt = $pdo->prepare("SELECT id FROM merchandise_inventory WHERE product_name = :product_name");
    $checkStmt->execute([':product_name' => $productName]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Product already exists!']);
        exit;
    }

    try {
        date_default_timezone_set('Asia/Manila');
        $last_restocked = date('j F Y g:i A');

        // ==============================================
        // 4. GENERATE PRODUCT NUMBER (PRD00001 format)
        // ==============================================
        // Get the highest existing product number
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(product_number, 4) AS UNSIGNED)) as max_num FROM merchandise_inventory");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNumber = ($result['max_num'] ?? 0) + 1;
        
        // Format as PRD00001, PRD00002, etc.
        $productNumber = 'PRD' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        // ==============================================
        // 5. INSERT PRODUCT WITH PRODUCT NUMBER
        // ==============================================
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO merchandise_inventory (product_number, product_name, unit, qty_on_hand, selling_price, description, last_restocked) VALUES (:product_number, :product_name, :unit, :qty_on_hand, :selling_price, :description, :last_restocked)");
        $result = $stmt->execute([
            ':product_number' => $productNumber,
            ':product_name' => $productName,
            ':unit' => $unit,
            ':qty_on_hand' => $quantity,
            ':selling_price' => $sellingPrice,
            ':description' => $description,
            ':last_restocked' => $last_restocked
        ]);

        if ($result) {
            $productId = $pdo->lastInsertId();

            // ==============================================
            // 6. INSERT INTO LOGS WITH PROPER NAME
            // ==============================================
            $logDetails = "Added new product: {$productName} | Product #: {$productNumber} | Unit: {$unit} | Quantity: {$quantity} | Price: ₱{$sellingPrice} | Description: " . ($description ?: 'N/A');
            
            $logStmt = $pdo->prepare("INSERT INTO logs (name, action, details, created_at) VALUES (?, ?, ?, ?)");
            $logStmt->execute([
                $firstName,
                "Added New Product",
                $logDetails,
                $last_restocked
            ]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Product added successfully',
                'product_id' => $productId,
                'product_number' => $productNumber
            ]);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to add product']);
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>