<?php
/**
 * API: Contract Operations
 * 
 * This script handles CRUD operations for contracts:
 * - Save single contract (insert/update)
 * - Delete contract
 * - Get contracts by month
 * - Check for duplicate contracts
 */

session_start();
header('Content-Type: application/json');

require_once '../DB_Conn/config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login first']);
    exit;
}

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$action = $_POST['action'] ?? '';

/**
 * Check if contract already exists for the same month
 * 
 * @param PDO $pdo Database connection
 * @param string $contractor Contractor name
 * @param string $monthYear Month/Year
 * @param int|null $excludeId ID to exclude from check (for updates)
 * @return bool True if exists
 */
function contractExists($pdo, $contractor, $monthYear, $excludeId = null) {
    $sql = "SELECT COUNT(*) FROM contracts WHERE contractor = :contractor AND contract_m_y = :month";
    if ($excludeId) {
        $sql .= " AND id != :exclude_id";
    }
    $stmt = $pdo->prepare($sql);
    $params = [':contractor' => $contractor, ':month' => $monthYear];
    if ($excludeId) {
        $params[':exclude_id'] = $excludeId;
    }
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

// Save single contract (Insert or Update)
if ($action === 'save_single_contract') {
    $contractId = $_POST['contract_id'] ?? null;
    $contractor = trim($_POST['contractor'] ?? '');
    $address = trim($_POST['contract_address'] ?? '');
    $value = floatval($_POST['contract_value'] ?? 0);
    $monthYear = trim($_POST['contract_m_y'] ?? '');

    // Validate required fields
    if (empty($contractor)) {
        echo json_encode(['success' => false, 'message' => 'Contractor name is required']);
        exit;
    }

    if ($value <= 0) {
        echo json_encode(['success' => false, 'message' => 'Contract value must be greater than 0']);
        exit;
    }

    if (empty($monthYear)) {
        echo json_encode(['success' => false, 'message' => 'Month/Year is required']);
        exit;
    }

    // Validate contractor name length
    if (strlen($contractor) > 255) {
        echo json_encode(['success' => false, 'message' => 'Contractor name is too long (max 255 characters)']);
        exit;
    }

    // Validate contract value range
    if ($value > 999999999.99) {
        echo json_encode(['success' => false, 'message' => 'Contract value is too high']);
        exit;
    }

    try {
        if ($contractId && !empty($contractId) && $contractId !== 'null') {
            // UPDATE existing contract
            // Check if contract exists for update
            $checkStmt = $pdo->prepare("SELECT id FROM contracts WHERE id = :id");
            $checkStmt->execute([':id' => $contractId]);
            if (!$checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Contract not found']);
                exit;
            }
            
            // Check for duplicate contractor name in same month (excluding current contract)
            if (contractExists($pdo, $contractor, $monthYear, $contractId)) {
                echo json_encode(['success' => false, 'message' => "Contractor '{$contractor}' already exists for this month"]);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE contracts 
                SET contractor = :contractor,
                    contract_address = :address,
                    contract_value = :value,
                    contract_m_y = :month
                WHERE id = :id
            ");
            $result = $stmt->execute([
                ':id' => $contractId,
                ':contractor' => $contractor,
                ':address' => $address,
                ':value' => $value,
                ':month' => $monthYear
            ]);

            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Contract updated successfully',
                    'action' => 'update',
                    'contract_id' => $contractId
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update contract']);
            }
        } else {
            // INSERT new contract
            // Check for duplicate contractor name in same month
            if (contractExists($pdo, $contractor, $monthYear)) {
                echo json_encode(['success' => false, 'message' => "Contractor '{$contractor}' already exists for this month"]);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO contracts (contractor, contract_address, contract_value, contract_m_y) 
                VALUES (:contractor, :address, :value, :month)
            ");
            $result = $stmt->execute([
                ':contractor' => $contractor,
                ':address' => $address,
                ':value' => $value,
                ':month' => $monthYear
            ]);

            if ($result) {
                $newId = $pdo->lastInsertId();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Contract added successfully', 
                    'new_id' => $newId,
                    'action' => 'insert'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add contract']);
            }
        }
    } catch (PDOException $e) {
        error_log("Save Contract PDO Error: " . $e->getMessage());
        if ($e->errorInfo[1] == 1062) { // Duplicate entry error
            echo json_encode(['success' => false, 'message' => 'Contractor already exists for this month']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } catch (Exception $e) {
        error_log("Save Contract Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Delete contract
elseif ($action === 'delete_contract') {
    $contractId = $_POST['contract_id'] ?? 0;

    if (empty($contractId)) {
        echo json_encode(['success' => false, 'message' => 'Contract ID is required']);
        exit;
    }

    try {
        // First, get the contract details for logging (optional)
        $getStmt = $pdo->prepare("SELECT contractor, contract_m_y FROM contracts WHERE id = :id");
        $getStmt->execute([':id' => $contractId]);
        $contract = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contract) {
            echo json_encode(['success' => false, 'message' => 'Contract not found']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM contracts WHERE id = :id");
        $result = $stmt->execute([':id' => $contractId]);

        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Contract deleted successfully',
                'deleted_contract' => $contract['contractor']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Contract not found or already deleted']);
        }
    } catch (Exception $e) {
        error_log("Delete Contract Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Get contracts by month
elseif ($action === 'get_contracts') {
    $monthYear = $_POST['month'] ?? $_GET['month'] ?? '';
    
    if (empty($monthYear)) {
        echo json_encode(['success' => false, 'message' => 'Month/Year is required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM contracts WHERE contract_m_y = :month ORDER BY contractor");
        $stmt->execute([':month' => $monthYear]);
        $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'contracts' => $contracts,
            'count' => count($contracts),
            'total_value' => array_sum(array_column($contracts, 'contract_value'))
        ]);
    } catch (Exception $e) {
        error_log("Get Contracts Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Bulk insert contracts (for initial import or multiple inserts)
elseif ($action === 'bulk_insert_contracts') {
    $contractsJson = $_POST['contracts'] ?? '';
    
    if (empty($contractsJson)) {
        echo json_encode(['success' => false, 'message' => 'No contract data received']);
        exit;
    }
    
    $contracts = json_decode($contractsJson, true);
    
    if (!is_array($contracts) || empty($contracts)) {
        echo json_encode(['success' => false, 'message' => 'Invalid contract data format']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $insertStmt = $pdo->prepare("
            INSERT INTO contracts (contractor, contract_address, contract_value, contract_m_y) 
            VALUES (:contractor, :address, :value, :month)
        ");
        
        $insertedCount = 0;
        $errors = [];
        
        foreach ($contracts as $contract) {
            $contractor = trim($contract['contractor'] ?? '');
            $address = trim($contract['contract_address'] ?? '');
            $value = floatval($contract['contract_value'] ?? 0);
            $monthYear = trim($contract['contract_m_y'] ?? '');
            
            // Validate
            if (empty($contractor)) {
                $errors[] = "Contractor name is required for one of the entries";
                continue;
            }
            
            if ($value <= 0) {
                $errors[] = "Contract value must be greater than 0 for '{$contractor}'";
                continue;
            }
            
            if (empty($monthYear)) {
                $errors[] = "Month/Year is required for '{$contractor}'";
                continue;
            }
            
            // Check for duplicate
            if (contractExists($pdo, $contractor, $monthYear)) {
                $errors[] = "Contractor '{$contractor}' already exists for this month";
                continue;
            }
            
            $result = $insertStmt->execute([
                ':contractor' => $contractor,
                ':address' => $address,
                ':value' => $value,
                ':month' => $monthYear
            ]);
            
            if ($result) {
                $insertedCount++;
            } else {
                $errors[] = "Failed to insert '{$contractor}'";
            }
        }
        
        $pdo->commit();
        
        if ($insertedCount > 0) {
            echo json_encode([
                'success' => true,
                'message' => "Successfully inserted {$insertedCount} contract(s)",
                'inserted_count' => $insertedCount,
                'errors' => $errors
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => "No contracts were inserted",
                'errors' => $errors
            ]);
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Bulk Insert Contracts Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Check if contractor exists (for validation before save)
elseif ($action === 'check_contractor_exists') {
    $contractor = trim($_POST['contractor'] ?? '');
    $monthYear = trim($_POST['contract_m_y'] ?? '');
    $excludeId = $_POST['exclude_id'] ?? null;
    
    if (empty($contractor) || empty($monthYear)) {
        echo json_encode(['success' => false, 'message' => 'Contractor and Month/Year are required']);
        exit;
    }
    
    try {
        $exists = contractExists($pdo, $contractor, $monthYear, $excludeId);
        echo json_encode([
            'success' => true,
            'exists' => $exists,
            'message' => $exists ? "Contractor '{$contractor}' already exists for this month" : "Contractor name is available"
        ]);
    } catch (Exception $e) {
        error_log("Check Contractor Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    exit;
}
?>