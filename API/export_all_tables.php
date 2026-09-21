<?php
// API/export_all_tables.php - Export all tables as SQL without COLLATE

session_start();

// ==============================================
// 1. FIX PATHS
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 2. CHECK LOGIN STATUS
// ==============================================
if (!isset($_SESSION['user_role']) || !isset($_SESSION['user_id']) || !isset($_SESSION['acc_number'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

if ($_SESSION['user_role'] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit();
}

// ==============================================
// 3. VERIFY CSRF TOKEN (POST only)
// ==============================================
$csrfToken = $_POST['csrf_token'] ?? '';
if ($csrfToken === '' || $csrfToken !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// ==============================================
// 4. TABLES TO EXPORT
// ==============================================
$tablesToExport = [
    'admins',
    'cart',
    'chat_account',
    'chat_conversation',
    'contracts',
    'customers',
    'for_deliveries',
    'investors',
    'investors_inventory',
    'investors_sales',
    'logs',
    'merchandise_inventory',
    'order_status_history'
];

// ==============================================
// 5. GENERATE SQL DUMP
// ==============================================
function generateSQLDump($pdo, $tables)
{
    $output = "-- ==============================================\n";
    $output .= "-- Database Export\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- ==============================================\n\n";

    $output .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $output .= "START TRANSACTION;\n";
    $output .= "SET time_zone = '+00:00';\n\n";

    foreach ($tables as $table) {
        // Skip if table doesn't exist
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() == 0) {
            continue;
        }

        // ----- Table structure -----
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($createTable) {
            $createSQL = $createTable['Create Table'];

            // Strip COLLATE clauses
            $createSQL = preg_replace('/ COLLATE=utf8mb4_uca1400_ai_ci/', '', $createSQL);
            $createSQL = preg_replace('/ COLLATE=utf8mb4_0900_ai_ci/', '', $createSQL);
            $createSQL = preg_replace('/ COLLATE=utf8mb4_unicode_ci/', '', $createSQL);
            $createSQL = preg_replace('/ COLLATE=utf8mb4_general_ci/', '', $createSQL);

            $output .= "-- --------------------------------------------------------\n";
            $output .= "-- Table structure for `$table`\n";
            $output .= "-- --------------------------------------------------------\n\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $output .= $createSQL . ";\n\n";
        }

        // ----- Table data -----
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
                        $values[] = "'" . addslashes($value) . "'";
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

if (empty($tables) || !is_array($tables)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No tables selected']);
    exit();
}

// ==============================================
// 7. GENERATE AND DOWNLOAD
// ==============================================
try {
    $sqlDump = generateSQLDump($pdo, $tables);

    // Clean any output buffering so the SQL isn't corrupted
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Content-Disposition BEFORE Content-Type
    header('Content-Disposition: attachment; filename="database_export_' . date('Y-m-d') . '.sql"');
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . strlen($sqlDump));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    echo $sqlDump;
    exit();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Export error: ' . $e->getMessage()]);
    exit();
}