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

// ==============================================
// DELETE CUSTOMER
// ==============================================
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

// ==============================================
// TOGGLE ACCOUNT STATUS (LOCK/UNLOCK)
// ==============================================
if ($action === 'toggle_account_status') {
    $customerId = intval($_POST['customer_id'] ?? 0);
    $statusAction = $_POST['status_action'] ?? ''; // 'lock' or 'unlock'
    
    // Validate input
    if ($customerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
        exit;
    }
    
    if (!in_array($statusAction, ['lock', 'unlock'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid action. Use "lock" or "unlock"']);
        exit;
    }
    
    try {
        // First, get customer name for logging
        $stmt = $pdo->prepare("SELECT f_name, active_email FROM customers WHERE id = :id");
        $stmt->execute([':id' => $customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
            exit;
        }
        
        // Determine new status
        $newStatus = ($statusAction === 'unlock') ? 1 : 0;
        $currentStatus = $customer['active_email'];
        
        // Check if already in the requested state
        if (($statusAction === 'lock' && $currentStatus == 0) || 
            ($statusAction === 'unlock' && ($currentStatus == 1 || $currentStatus === null))) {
            $statusText = ($statusAction === 'lock') ? 'locked' : 'unlocked';
            echo json_encode([
                'success' => true, 
                'message' => "Customer account is already {$statusText}"
            ]);
            exit;
        }
        
        // Update the customer's active_email status
        $stmt = $pdo->prepare("UPDATE customers SET active_email = :status WHERE id = :id");
        $result = $stmt->execute([
            ':status' => $newStatus,
            ':id' => $customerId
        ]);
        
        if ($result) {
            $actionText = ($statusAction === 'unlock') ? 'Unlocked' : 'Locked';
            $statusText = ($statusAction === 'unlock') ? 'active' : 'inactive';
            
            
            echo json_encode([
                'success' => true, 
                'message' => "Account updated!"
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update account status']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==============================================
// GET CUSTOMER DETAILS (Optional - for future use)
// ==============================================
if ($action === 'get_customer_details') {
    $customerId = intval($_POST['customer_id'] ?? 0);
    
    if ($customerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, f_name, email, phone_number, active_email, acc_number, text_pass FROM customers WHERE id = :id");
        $stmt->execute([':id' => $customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customer) {
            echo json_encode(['success' => true, 'data' => $customer]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==============================================
// BULK LOCK/UNLOCK (Optional - for future use)
// ==============================================
if ($action === 'bulk_toggle_status') {
    $customerIds = $_POST['customer_ids'] ?? [];
    $statusAction = $_POST['status_action'] ?? ''; // 'lock' or 'unlock'
    
    if (empty($customerIds) || !is_array($customerIds)) {
        echo json_encode(['success' => false, 'message' => 'No customers selected']);
        exit;
    }
    
    if (!in_array($statusAction, ['lock', 'unlock'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
    
    try {
        $newStatus = ($statusAction === 'unlock') ? 1 : 0;
        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
        
        $stmt = $pdo->prepare("UPDATE customers SET account = ? WHERE id IN ({$placeholders})");
        $params = array_merge([$newStatus], $customerIds);
        $result = $stmt->execute($params);
        
        if ($result) {
            $affected = $stmt->rowCount();
            $actionText = ($statusAction === 'unlock') ? 'unlocked' : 'locked';
            error_log("Bulk {$actionText} - {$affected} customers, User: {$_SESSION['user_id']}");
            
            echo json_encode([
                'success' => true, 
                'message' => "{$affected} customer(s) have been {$actionText} successfully!"
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update account statuses']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==============================================
// INVALID ACTION
// ==============================================
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>