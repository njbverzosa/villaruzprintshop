<?php
// API/customer_actions.php
session_start();
header('Content-Type: application/json');

require_once '../DB_Conn/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$action = $_POST['action'] ?? '';

// Delete customer
if ($action === 'delete_customer') {
    $customerId = intval($_POST['customer_id'] ?? 0);
    
    if ($customerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
        exit;
    }
    
    try {
        // First, get customer name for logging
        $stmt = $pdo->prepare("SELECT f_name FROM customers WHERE id = :id");
        $stmt->execute([':id' => $customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
            exit;
        }
        
        // Delete the customer
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = :id");
        $stmt->execute([':id' => $customerId]);
        
        if ($stmt->rowCount() > 0) {
            error_log("Customer deleted - ID: {$customerId}, Name: {$customer['f_name']}, User: {$_SESSION['user_id']}");
            echo json_encode([
                'success' => true, 
                'message' => "Customer '{$customer['f_name']}' has been deleted successfully!"
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete customer']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>