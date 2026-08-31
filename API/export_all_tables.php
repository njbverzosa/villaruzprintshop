<?php
// API/export_all_tables.php - Export all tables as SQL without COLLATE

session_start();
header('Content-Type: application/json');

// ==============================================
// 1. FIX PATHS
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 2. CHECK LOGIN STATUS
// ==============================================
if (!isset($_SESSION['user_role']) || !isset($_SESSION['user_id']) || !isset($_SESSION['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

if ($_SESSION['user_role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit();
}

// ==============================================
// 3. VERIFY CSRF TOKEN
// ==============================================
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// ==============================================
// 4. TABLES TO EXPORT - ADDED 'admins'
// ==============================================
$tablesToExport = [
    'admins',
    'cart',
    'chat_account',
    'chat_conversation',
    'contracts',
    'customers',
    'for_deliveries',
    'location',
    'merchandise_inventory',
    'order_status_history',
    'logs'
];

// ==============================================
// 5. GENERATE SQL DUMP
// ==============================================
function generateSQLDump($pdo, $tables) {
    $output = "-- ==============================================\n";
    $output .= "-- Database Export\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- ==============================================\n\n";
    
    $output .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $output .= "START TRANSACTION;\n";
    $output .= "SET time_zone = '+00:00';\n\n";

    foreach ($tables as $table) {
        // Check if table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() == 0) {
            continue;
        }

        // Get table structure
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($createTable) {
            $createSQL = $createTable['Create Table'];
            
            // Remove COLLATE statements
            $createSQL = preg_replace('/ COLLATE=utf8mb4_uca1400_ai_ci/', '', $createSQL);
            $createSQL = preg_replace('/ COLLATE=utf8mb4_0900_ai_ci/', '', $createSQL);
            $createSQL = preg_replace('/ COLLATE=utf8mb4_unicode_ci/', '', $createSQL);
            $createSQL = preg_replace('/ COLLATE=utf8mb4_general_ci/', '', $createSQL);
            
            // Remove CHARSET if needed (optional)
            // $createSQL = preg_replace('/ CHARACTER SET utf8mb4/', '', $createSQL);
            
            $output .= "-- --------------------------------------------------------\n";
            $output .= "-- Table structure for `$table`\n";
            $output .= "-- --------------------------------------------------------\n\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $output .= $createSQL . ";\n\n";
        }

        // Get table data
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $output .= "-- --------------------------------------------------------\n";
            $output .= "-- Data for table `$table`\n";
            $output .= "-- --------------------------------------------------------\n\n";
            
            $columns = array_keys($rows[0]);
            $columnList = "`" . implode("`, `", $columns) . "`";
            
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        // Escape special characters
                        $escaped = addslashes($value);
                        $values[] = "'" . $escaped . "'";
                    }
                }
                $output .= "INSERT INTO `$table` ($columnList) VALUES (" . implode(", ", $values) . ");\n";
            }
            $output .= "\n";
        }
    }

    $output .= "COMMIT;\n";
    $output .= "-- ==============================================\n";
    $output .= "-- Export completed: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- ==============================================\n";

    return $output;
}

// ==============================================
// 6. GET TABLE NAMES FROM POST OR USE ALL
// ==============================================
$tables = isset($_POST['tables']) ? json_decode($_POST['tables'], true) : $tablesToExport;

if (empty($tables)) {
    echo json_encode(['success' => false, 'message' => 'No tables selected']);
    exit();
}

// ==============================================
// 7. GENERATE AND DOWNLOAD
// ==============================================
try {
    $sqlDump = generateSQLDump($pdo, $tables);
    
    // Send as download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="database_export_' . date('Y-m-d') . '.sql"');
    header('Content-Length: ' . strlen($sqlDump));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    
    echo $sqlDump;
    exit();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Export error: ' . $e->getMessage()]);
    exit();
}
?>