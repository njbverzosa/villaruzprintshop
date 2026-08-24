<?php
// API/get_products.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../DB_Conn/config.php';

// Check if user is logged in
if (!isset($_SESSION['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, product_name, product_number, selling_price, qty_on_hand, unit, description FROM merchandise_inventory ORDER BY id ASC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => count($products)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>