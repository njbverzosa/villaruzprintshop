<?php
// API/save_spreadsheet.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../DB_Conn/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';

// Save spreadsheet data
if ($action === 'save_spreadsheet') {
    $sheetName = $_POST['sheet_name'] ?? 'default';
    $data = json_decode($_POST['data'], true);
    $rowCount = intval($_POST['row_count'] ?? 50);
    $colCount = intval($_POST['col_count'] ?? 10);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid data format']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Delete existing data for this sheet
        $deleteStmt = $pdo->prepare("DELETE FROM spreadsheet_data WHERE sheet_name = ?");
        $deleteStmt->execute([$sheetName]);
        
        // Insert new data
        $insertStmt = $pdo->prepare("INSERT INTO spreadsheet_data (sheet_name, row_num, col_letter, cell_value) VALUES (?, ?, ?, ?)");
        
        $colLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        
        foreach ($data as $rowIdx => $row) {
            $rowNum = $rowIdx + 1;
            foreach ($row as $colIdx => $cellValue) {
                if ($cellValue !== null && $cellValue !== '') {
                    $colLetter = $colLetters[$colIdx] ?? chr(65 + $colIdx);
                    $insertStmt->execute([$sheetName, $rowNum, $colLetter, $cellValue]);
                }
            }
        }
        
        // Update or insert sheet info
        $sheetStmt = $pdo->prepare("INSERT INTO saved_sheets (sheet_name, title, row_count, col_count, created_by) 
                                    VALUES (?, ?, ?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE 
                                    title = VALUES(title), 
                                    row_count = VALUES(row_count), 
                                    col_count = VALUES(col_count),
                                    updated_at = CURRENT_TIMESTAMP");
        
        $title = $_POST['title'] ?? 'Untitled Spreadsheet';
        $createdBy = $_SESSION['acc_number'] ?? $_SESSION['user_email'] ?? 'unknown';
        $sheetStmt->execute([$sheetName, $title, $rowCount, $colCount, $createdBy]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Spreadsheet saved successfully!']);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Load spreadsheet data
if ($action === 'load_spreadsheet') {
    $sheetName = $_POST['sheet_name'] ?? 'default';
    
    try {
        // Get sheet info
        $sheetStmt = $pdo->prepare("SELECT * FROM saved_sheets WHERE sheet_name = ?");
        $sheetStmt->execute([$sheetName]);
        $sheetInfo = $sheetStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get cell data
        $dataStmt = $pdo->prepare("SELECT row_num, col_letter, cell_value FROM spreadsheet_data WHERE sheet_name = ? ORDER BY row_num, col_letter");
        $dataStmt->execute([$sheetName]);
        $cells = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $rowCount = $sheetInfo['row_count'] ?? 50;
        $colCount = $sheetInfo['col_count'] ?? 10;
        
        // Build data array
        $data = [];
        foreach ($cells as $cell) {
            $rowNum = $cell['row_num'] - 1;
            $colLetter = $cell['col_letter'];
            $colIdx = ord($colLetter) - 65;
            
            if (!isset($data[$rowNum])) {
                $data[$rowNum] = [];
            }
            $data[$rowNum][$colIdx] = $cell['cell_value'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'sheet_info' => $sheetInfo,
            'row_count' => $rowCount,
            'col_count' => $colCount
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Get all saved sheets
if ($action === 'get_sheets') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM saved_sheets ORDER BY updated_at DESC");
        $stmt->execute();
        $sheets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'sheets' => $sheets]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Delete sheet
if ($action === 'delete_sheet') {
    $sheetName = $_POST['sheet_name'] ?? '';
    
    if (empty($sheetName)) {
        echo json_encode(['success' => false, 'message' => 'Sheet name required']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $deleteData = $pdo->prepare("DELETE FROM spreadsheet_data WHERE sheet_name = ?");
        $deleteData->execute([$sheetName]);
        
        $deleteSheet = $pdo->prepare("DELETE FROM saved_sheets WHERE sheet_name = ?");
        $deleteSheet->execute([$sheetName]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Sheet deleted successfully!']);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>