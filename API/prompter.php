<?php
// API/prompter.php - AI SQL Assistant Backend

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
    echo json_encode(['success' => false, 'message' => '🔐 Please login first']);
    exit();
}

if ($_SESSION['user_role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => '⛔ Access denied. Admin only.']);
    exit();
}

// ==============================================
// 3. VERIFY CSRF TOKEN
// ==============================================
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => '❌ Invalid security token']);
    exit();
}

// ==============================================
// 4. GET THE PROMPT
// ==============================================
$prompt = trim($_POST['prompt'] ?? '');

if (empty($prompt)) {
    echo json_encode(['success' => false, 'message' => '💬 Hi! I\'m Jo Seph AI. What would you like me to help you with?']);
    exit();
}

// ==============================================
// 5. CREATE UNDO LOG TABLE IF NOT EXISTS
// ==============================================
function createUndoTable($pdo) {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS undo_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        session_id VARCHAR(255) NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        table_name VARCHAR(100) NOT NULL,
        column_name VARCHAR(100),
        old_value TEXT,
        new_value TEXT,
        affected_rows INT DEFAULT 0,
        query TEXT,
        original_prompt TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        undone TINYINT(1) DEFAULT 0,
        INDEX idx_session (session_id),
        INDEX idx_undone (undone)
    )");
}

createUndoTable($pdo);

// ==============================================
// 6. GET THE ACTION
// ==============================================
$action = $_POST['action'] ?? '';

// ==============================================
// 7. UNDO ACTION
// ==============================================
if ($action === 'undo') {
    $sessionId = session_id();
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM undo_log 
            WHERE session_id = ? AND undone = 0 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$sessionId]);
        $lastAction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lastAction) {
            echo json_encode([
                'success' => false,
                'message' => "😅 There's nothing to undo, Master! You haven't made any changes yet."
            ]);
            exit();
        }
        
        $undoResult = revertAction($pdo, $lastAction);
        
        if ($undoResult['success']) {
            $stmt = $pdo->prepare("UPDATE undo_log SET undone = 1 WHERE id = ?");
            $stmt->execute([$lastAction['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => "✅ Undo successful, Master!\n\n" . $undoResult['message'],
                'undone_action' => $lastAction
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => '⚠️ Failed to undo: ' . $undoResult['message']
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => '⚠️ Error during undo: ' . $e->getMessage()
        ]);
    }
    exit();
}

// ==============================================
// 8. REVERT ACTION FUNCTION
// ==============================================
function revertAction($pdo, $action) {
    $table = $action['table_name'];
    $column = $action['column_name'];
    $oldValue = $action['old_value'];
    $newValue = $action['new_value'];
    $affectedRows = $action['affected_rows'];
    
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() == 0) {
            return ['success' => false, 'message' => "Table '{$table}' no longer exists."];
        }
        
        switch ($action['action_type']) {
            case 'update':
                $stmt = $pdo->prepare("UPDATE {$table} SET {$column} = ? WHERE {$column} = ?");
                $stmt->execute([$oldValue, $newValue]);
                $reverted = $stmt->rowCount();
                return [
                    'success' => true,
                    'message' => "🔄 Reverted update: `{$column}` changed from {$newValue} back to {$oldValue}. Affected {$reverted} rows."
                ];
                
            case 'delete':
                return [
                    'success' => true,
                    'message' => "⚠️ Delete cannot be fully reverted automatically. Please check your data.\nAffected rows: {$affectedRows}"
                ];
                
            case 'insert':
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$column} = ?");
                $stmt->execute([$oldValue]);
                $reverted = $stmt->rowCount();
                return [
                    'success' => true,
                    'message' => "🗑️ Reverted insert: Removed {$reverted} rows with `{$column}` = {$oldValue}"
                ];
                
            case 'add':
                $stmt = $pdo->prepare("UPDATE {$table} SET {$column} = {$column} - ?");
                $stmt->execute([$newValue]);
                $reverted = $stmt->rowCount();
                return [
                    'success' => true,
                    'message' => "➖ Reverted addition: Subtracted {$newValue} from `{$column}`. Affected {$reverted} rows."
                ];
                
            case 'subtract':
                $stmt = $pdo->prepare("UPDATE {$table} SET {$column} = {$column} + ?");
                $stmt->execute([$newValue]);
                $reverted = $stmt->rowCount();
                return [
                    'success' => true,
                    'message' => "➕ Reverted subtraction: Added back {$newValue} to `{$column}`. Affected {$reverted} rows."
                ];
                
            case 'lock':
                $stmt = $pdo->prepare("UPDATE customers SET active_email = 1 WHERE acc_number = ? OR f_name LIKE ?");
                $stmt->execute([$oldValue, "%{$oldValue}%"]);
                $reverted = $stmt->rowCount();
                return [
                    'success' => true,
                    'message' => "🔓 Unlocked {$reverted} customer(s). They can now access their account."
                ];
                
            case 'unlock':
                $stmt = $pdo->prepare("UPDATE customers SET active_email = 0 WHERE acc_number = ? OR f_name LIKE ?");
                $stmt->execute([$oldValue, "%{$oldValue}%"]);
                $reverted = $stmt->rowCount();
                return [
                    'success' => true,
                    'message' => "🔒 Locked {$reverted} customer(s). Their access has been restricted."
                ];
                
            default:
                return ['success' => false, 'message' => "Unknown action type: {$action['action_type']}"];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Revert error: " . $e->getMessage()];
    }
}

// ==============================================
// 9. LOG ACTION
// ==============================================
function logAction($pdo, $actionType, $table, $column, $oldValue, $newValue, $affectedRows, $query, $prompt) {
    $sessionId = session_id();
    
    $stmt = $pdo->prepare("
        INSERT INTO undo_log (
            session_id, action_type, table_name, column_name, 
            old_value, new_value, affected_rows, query, original_prompt
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $sessionId,
        $actionType,
        $table,
        $column,
        $oldValue,
        $newValue,
        $affectedRows,
        $query,
        $prompt
    ]);
    
    return $pdo->lastInsertId();
}

// ==============================================
// 10. AI RESPONSE GENERATOR
// ==============================================
function generateAIResponse($prompt, $result) {
    $responses = [
        'success' => [
            'I\'ve successfully executed your command, Master! ✨',
            'Done! Your wish is my command. 🎯',
            'Executed with precision, Master! 💪',
            'Consider it done! I love being helpful. 🤖',
            'Mission accomplished! What\'s next? 🚀',
            'Perfect! I\'ve completed the task. 🌟',
            'All done, Master! Your database is now updated. 📊'
        ],
        'error' => [
            'Hmm, I couldn\'t quite understand that. Could you rephrase? 🤔',
            'Oops! I need more clarity on that. Try being more specific. 😅',
            'I\'m not sure what you mean. Let me help you with that. 💡',
            'That command is a bit confusing. Here\'s what I can do for you: 📋'
        ],
        'help' => [
            'I can help you with:',
            'Here\'s what I can do:',
            'Try one of these commands:'
        ]
    ];
    
    if ($result['success']) {
        $response = $responses['success'][array_rand($responses['success'])];
        return $response . "\n\n" . $result['message'];
    } else {
        $response = $responses['error'][array_rand($responses['error'])];
        return $response . "\n\n" . $result['message'];
    }
}

// ==============================================
// 11. PROCESS THE PROMPT WITH AI
// ==============================================
function processPromptWithAI($pdo, $prompt) {
    $promptLower = strtolower($prompt);
    
    // ==============================================
    // GREETINGS & CHAT
    // ==============================================
    $greetings = ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'how are you', 'what\'s up', 'yo'];
    foreach ($greetings as $greeting) {
        if (strpos($promptLower, $greeting) !== false) {
            return [
                'success' => true,
                'message' => "👋 Hello Master! I'm Jo Seph AI, your SQL assistant.\n\nI can help you with:\n• 📊 View database tables\n• ✏️ Update values\n• ➕ Add to columns\n• ➖ Subtract from columns\n• 🔒 Lock/Unlock customers\n• 🗑️ Delete records\n• ↩️ Undo last action\n\nTry: 'show customers' or '340 update to 500'",
                'prompt' => $prompt,
                'is_ai_response' => true
            ];
        }
    }
    
    // ==============================================
    // HELP COMMANDS
    // ==============================================
    if (strpos($promptLower, 'help') !== false || strpos($promptLower, '?') !== false || strpos($promptLower, 'what can you do') !== false) {
        return [
            'success' => true,
            'message' => "🤖 I'm Jo Seph AI, your SQL assistant!\n\nHere's what I can do for you:\n\n📊 **View Data**\n• 'show customers' - View all customers\n• 'show cart' - View cart items\n• 'show inventory' - View merchandise\n\n✏️ **Update Data**\n• '340 update to 500' - Update values\n• 'set pieces to 10 where id = 5' - Conditional update\n\n➕ **Add to Data**\n• 'add 5 to pieces in cart' - Add to column\n\n➖ **Subtract from Data**\n• 'subtract 10 from qty_on_hand in inventory'\n\n🔒 **Customer Management**\n• 'status of customer123' - Check customer\n• 'lock customer123' - Lock account\n• 'unlock customer123' - Unlock account\n\n🗑️ **Delete Data**\n• 'delete 100 from cart' - Delete records\n\n↩️ **Undo**\n• 'undo' - Revert last action\n\n💡 Just type what you want and I'll handle it!",
            'prompt' => $prompt,
            'is_ai_response' => true
        ];
    }
    
    // ==============================================
    // THANK YOU
    // ==============================================
    if (strpos($promptLower, 'thank') !== false || strpos($promptLower, 'thanks') !== false) {
        return [
            'success' => true,
            'message' => "🙏 You're welcome, Master! I'm always happy to help.\n\nIs there anything else I can assist you with today?",
            'prompt' => $prompt,
            'is_ai_response' => true
        ];
    }
    
    // ==============================================
    // CHECK FOR UNDO COMMAND
    // ==============================================
    if ($promptLower === 'undo' || $promptLower === 'undo last' || $promptLower === 'revert' || $promptLower === 'back') {
        $sessionId = session_id();
        
        $stmt = $pdo->prepare("
            SELECT * FROM undo_log 
            WHERE session_id = ? AND undone = 0 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$sessionId]);
        $lastAction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lastAction) {
            return [
                'success' => false,
                'message' => "😅 There's nothing to undo, Master! You haven't made any changes yet.",
                'prompt' => $prompt
            ];
        }
        
        $undoResult = revertAction($pdo, $lastAction);
        
        if ($undoResult['success']) {
            $stmt = $pdo->prepare("UPDATE undo_log SET undone = 1 WHERE id = ?");
            $stmt->execute([$lastAction['id']]);
            
            return [
                'success' => true,
                'message' => "✅ Undo successful, Master!\n\n" . $undoResult['message'],
                'prompt' => $prompt,
                'undone_action' => $lastAction
            ];
        } else {
            return [
                'success' => false,
                'message' => '⚠️ Failed to undo: ' . $undoResult['message'],
                'prompt' => $prompt
            ];
        }
    }
    
    // ==============================================
    // GET CURRENT TIME
    // ==============================================
    if (strpos($promptLower, 'time') !== false || strpos($promptLower, 'date') !== false) {
        $now = new DateTime();
        return [
            'success' => true,
            'message' => "🕐 It's " . $now->format('F j, Y g:i A') . " in the Philippines, Master!",
            'prompt' => $prompt,
            'is_ai_response' => true
        ];
    }
    
    // ==============================================
    // TELL A JOKE
    // ==============================================
    $jokes = [
        "Why do programmers prefer dark mode? Because light attracts bugs! 😄",
        "Why did the SQL query go to therapy? It had too many JOIN issues! 😂",
        "What do you call a fake noodle? An impasta! 🍝",
        "Why did the database administrator break up with his girlfriend? She had too many foreign keys! 💔",
        "What's a computer's favorite snack? Microchips! 🍟"
    ];
    
    if (strpos($promptLower, 'joke') !== false || strpos($promptLower, 'funny') !== false) {
        return [
            'success' => true,
            'message' => $jokes[array_rand($jokes)],
            'prompt' => $prompt,
            'is_ai_response' => true
        ];
    }
    
    // ==============================================
    // COMPLIMENT
    // ==============================================
    if (strpos($promptLower, 'you\'re') !== false || strpos($promptLower, 'you are') !== false) {
        if (strpos($promptLower, 'smart') !== false || strpos($promptLower, 'good') !== false || strpos($promptLower, 'great') !== false) {
            return [
                'success' => true,
                'message' => "😊 Thank you, Master! I try my best to serve you well.\n\nI'm always learning and improving. Is there anything else I can help you with?",
                'prompt' => $prompt,
                'is_ai_response' => true
            ];
        }
    }
    
    // ==============================================
    // BYE
    // ==============================================
    if (strpos($promptLower, 'bye') !== false || strpos($promptLower, 'goodbye') !== false || strpos($promptLower, 'see you') !== false) {
        return [
            'success' => true,
            'message' => "👋 Goodbye, Master! It was a pleasure serving you.\n\nI'll be here whenever you need me. Come back anytime! 🤖",
            'prompt' => $prompt,
            'is_ai_response' => true
        ];
    }
    
    // ==============================================
    // PROCESS COMMANDS
    // ==============================================
    return processCommand($pdo, $prompt);
}

// ==============================================
// 12. PROCESS COMMANDS
// ==============================================
function processCommand($pdo, $prompt) {
    $promptLower = strtolower($prompt);
    
    // ==============================================
    // UPDATE COMMAND: "340 update to 500"
    // ==============================================
    if (preg_match('/(\d+)\s*(?:update|change|modify|set)\s*(?:to|as|into)?\s*(\d+)/i', $prompt, $matches)) {
        $oldValue = $matches[1];
        $newValue = $matches[2];
        return updateValueWithLog($pdo, $oldValue, $newValue, $prompt);
    }
    
    // ==============================================
    // UPDATE IN TABLE: "change 100 to 200 in cart"
    // ==============================================
    if (preg_match('/change\s+(\d+)\s+to\s+(\d+)\s+in\s+([a-zA-Z_]+)/i', $prompt, $matches)) {
        $oldValue = $matches[1];
        $newValue = $matches[2];
        $table = $matches[3];
        return updateValueInTableWithLog($pdo, $oldValue, $newValue, $table, $prompt);
    }
    
    // ==============================================
    // CONDITIONAL UPDATE: "set pieces to 10 where id = 5"
    // ==============================================
    if (preg_match('/set\s+([a-zA-Z_]+)\s+to\s+([\d.]+)\s+where\s+([a-zA-Z_]+)\s*=\s*([\d.]+)/i', $prompt, $matches)) {
        $column = $matches[1];
        $newValue = $matches[2];
        $whereColumn = $matches[3];
        $whereValue = $matches[4];
        return updateWithConditionWithLog($pdo, $column, $newValue, $whereColumn, $whereValue, $prompt);
    }
    
    // ==============================================
    // SHOW TABLE: "show customers"
    // ==============================================
    if (preg_match('/show\s+(?:me\s+)?(?:the\s+)?([a-zA-Z_]+)/i', $prompt, $matches)) {
        $table = $matches[1];
        return showTable($pdo, $table, $prompt);
    }
    
    // ==============================================
    // DELETE: "delete 100 from cart"
    // ==============================================
    if (preg_match('/delete\s+(\d+)\s+from\s+([a-zA-Z_]+)/i', $prompt, $matches)) {
        $value = $matches[1];
        $table = $matches[2];
        return deleteFromTableWithLog($pdo, $value, $table, $prompt);
    }
    
    // ==============================================
    // ADD: "add 5 to pieces in cart"
    // ==============================================
    if (preg_match('/add\s+([\d.]+)\s+to\s+([a-zA-Z_]+)\s+in\s+([a-zA-Z_]+)/i', $prompt, $matches)) {
        $value = $matches[1];
        $column = $matches[2];
        $table = $matches[3];
        return addToColumnWithLog($pdo, $value, $column, $table, $prompt);
    }
    
    // ==============================================
    // SUBTRACT: "subtract 10 from qty_on_hand in merchandise_inventory"
    // ==============================================
    if (preg_match('/subtract\s+([\d.]+)\s+from\s+([a-zA-Z_]+)\s+in\s+([a-zA-Z_]+)/i', $prompt, $matches)) {
        $value = $matches[1];
        $column = $matches[2];
        $table = $matches[3];
        return subtractFromColumnWithLog($pdo, $value, $column, $table, $prompt);
    }
    
    // ==============================================
    // COUNT: "count customers"
    // ==============================================
    if (preg_match('/count\s+(?:the\s+)?(?:rows?\s+in\s+)?([a-zA-Z_]+)/i', $prompt, $matches)) {
        $table = $matches[1];
        return countTable($pdo, $table, $prompt);
    }
    
    // ==============================================
    // SUM: "sum total_amount in cart"
    // ==============================================
    if (preg_match('/sum\s+([a-zA-Z_]+)\s+in\s+([a-zA-Z_]+)/i', $prompt, $matches)) {
        $column = $matches[1];
        $table = $matches[2];
        return sumColumn($pdo, $column, $table, $prompt);
    }
    
    // ==============================================
    // CUSTOMER STATUS: "status of customer123"
    // ==============================================
    if (preg_match('/status\s+of\s+([a-zA-Z0-9_]+)/i', $prompt, $matches)) {
        $customer = $matches[1];
        return getCustomerStatus($pdo, $customer, $prompt);
    }
    
    // ==============================================
    // LOCK/UNLOCK: "lock customer123"
    // ==============================================
    if (preg_match('/(lock|unlock)\s+([a-zA-Z0-9_]+)/i', $prompt, $matches)) {
        $action = $matches[1];
        $customer = $matches[2];
        return toggleCustomerStatusWithLog($pdo, $customer, $action, $prompt);
    }
    
    // ==============================================
    // SIMPLE NUMBER UPDATE: "340 = 500"
    // ==============================================
    if (preg_match('/(\d+)\s*=\s*(\d+)/', $prompt, $matches)) {
        $oldValue = $matches[1];
        $newValue = $matches[2];
        return updateValueWithLog($pdo, $oldValue, $newValue, $prompt);
    }
    
    // ==============================================
    // SMART PROCESS - Try to understand
    // ==============================================
    return smartProcess($pdo, $prompt);
}

// ==============================================
// 13. WRAPPED FUNCTIONS WITH LOGGING
// ==============================================

function updateValueWithLog($pdo, $oldValue, $newValue, $prompt) {
    $tables = [
        'cart' => ['selling_price', 'pieces', 'total_amount'],
        'customers' => ['id', 'acc_number', 'phone_number', 'vip'],
        'merchandise_inventory' => ['id', 'selling_price', 'qty_on_hand'],
        'for_deliveries' => ['id', 'total_amount', 'charge'],
        'contracts' => ['id', 'contract_value'],
        'chat_account' => ['id', 'status'],
        'chat_conversation' => ['id', 'status']
    ];
    
    $found = [];
    $allResults = [];
    
    foreach ($tables as $table => $columns) {
        foreach ($columns as $column) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?");
            $stmt->execute([$oldValue]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE {$table} SET {$column} = ? WHERE {$column} = ?");
                    $stmt->execute([$newValue, $oldValue]);
                    $affected = $stmt->rowCount();
                    
                    if ($affected > 0) {
                        $query = "UPDATE {$table} SET {$column} = {$newValue} WHERE {$column} = {$oldValue}";
                        logAction($pdo, 'update', $table, $column, $oldValue, $newValue, $affected, $query, $prompt);
                        $allResults[] = "✅ Updated {$affected} row(s) in `{$table}`: `{$column}` changed from {$oldValue} to {$newValue}";
                    }
                } catch (Exception $e) {}
            }
        }
    }
    
    if (empty($allResults)) {
        return [
            'success' => false,
            'message' => "🤔 I couldn't find any records with value '{$oldValue}' in the database, Master.",
            'prompt' => $prompt
        ];
    }
    
    return [
        'success' => true,
        'message' => implode("\n", $allResults),
        'prompt' => $prompt,
        'old_value' => $oldValue,
        'new_value' => $newValue
    ];
}

function updateValueInTableWithLog($pdo, $oldValue, $newValue, $table, $prompt) {
    $allowedTables = ['cart', 'customers', 'merchandise_inventory', 'for_deliveries', 'contracts', 'chat_account', 'chat_conversation'];
    if (!in_array($table, $allowedTables)) {
        return [
            'success' => false,
            'message' => "⚠️ Table '{$table}' doesn't exist or I don't have access to it, Master.",
            'prompt' => $prompt
        ];
    }
    
    $found = false;
    $results = [];
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table}");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        $colName = $col['Field'];
        if (strpos($col['Type'], 'int') !== false || strpos($col['Type'], 'decimal') !== false || strpos($col['Type'], 'float') !== false) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$table} WHERE {$colName} = ?");
                $stmt->execute([$oldValue]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    $stmt = $pdo->prepare("UPDATE {$table} SET {$colName} = ? WHERE {$colName} = ?");
                    $stmt->execute([$newValue, $oldValue]);
                    $affected = $stmt->rowCount();
                    
                    if ($affected > 0) {
                        $query = "UPDATE {$table} SET {$colName} = {$newValue} WHERE {$colName} = {$oldValue}";
                        logAction($pdo, 'update', $table, $colName, $oldValue, $newValue, $affected, $query, $prompt);
                        $results[] = "✅ Updated {$affected} row(s) in `{$table}`: `{$colName}` changed from {$oldValue} to {$newValue}";
                        $found = true;
                    }
                }
            } catch (Exception $e) {}
        }
    }
    
    if (!$found) {
        return [
            'success' => false,
            'message' => "🔍 I searched but couldn't find '{$oldValue}' in table '{$table}', Master.",
            'prompt' => $prompt
        ];
    }
    
    return [
        'success' => true,
        'message' => implode("\n", $results),
        'prompt' => $prompt,
        'old_value' => $oldValue,
        'new_value' => $newValue,
        'table' => $table
    ];
}

function updateWithConditionWithLog($pdo, $column, $newValue, $whereColumn, $whereValue, $prompt) {
    $allowedTables = ['cart', 'customers', 'merchandise_inventory', 'for_deliveries', 'contracts', 'chat_account', 'chat_conversation'];
    
    $found = false;
    $results = [];
    
    foreach ($allowedTables as $table) {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table}");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array($column, $columns) && in_array($whereColumn, $columns)) {
                $stmt = $pdo->prepare("SELECT {$column} FROM {$table} WHERE {$whereColumn} = ?");
                $stmt->execute([$whereValue]);
                $oldValues = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($oldValues)) {
                    $stmt = $pdo->prepare("UPDATE {$table} SET {$column} = ? WHERE {$whereColumn} = ?");
                    $stmt->execute([$newValue, $whereValue]);
                    $affected = $stmt->rowCount();
                    
                    if ($affected > 0) {
                        $oldValue = $oldValues[0] ?? $whereValue;
                        $query = "UPDATE {$table} SET {$column} = {$newValue} WHERE {$whereColumn} = {$whereValue}";
                        logAction($pdo, 'update', $table, $column, $oldValue, $newValue, $affected, $query, $prompt);
                        $results[] = "✅ Updated {$affected} row(s) in `{$table}`: `{$column}` set to {$newValue} where `{$whereColumn}` = {$whereValue}";
                        $found = true;
                    }
                }
            }
        } catch (Exception $e) {}
    }
    
    if (!$found) {
        return [
            'success' => false,
            'message' => "⚠️ I couldn't find a match for your condition, Master. Check your column names.",
            'prompt' => $prompt
        ];
    }
    
    return [
        'success' => true,
        'message' => implode("\n", $results),
        'prompt' => $prompt
    ];
}

function deleteFromTableWithLog($pdo, $value, $table, $prompt) {
    $allowedTables = ['cart', 'customers', 'merchandise_inventory', 'for_deliveries', 'contracts', 'chat_account', 'chat_conversation'];
    
    if (!in_array($table, $allowedTables)) {
        return [
            'success' => false,
            'message' => "⚠️ Table '{$table}' doesn't exist or I don't have access to it, Master.",
            'prompt' => $prompt
        ];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? OR acc_number = ? OR product_name LIKE ? OR order_number = ?");
        $stmt->execute([$value, $value, "%{$value}%", $value]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($records)) {
            return [
                'success' => false,
                'message' => "🔍 I searched but couldn't find '{$value}' in table '{$table}', Master.",
                'prompt' => $prompt
            ];
        }
        
        $query = "DELETE FROM {$table} WHERE id = {$value} OR acc_number = '{$value}' OR product_name LIKE '%{$value}%' OR order_number = '{$value}'";
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ? OR acc_number = ? OR product_name LIKE ? OR order_number = ?");
        $stmt->execute([$value, $value, "%{$value}%", $value]);
        $affected = $stmt->rowCount();
        
        logAction($pdo, 'delete', $table, 'id', $value, null, $affected, $query, $prompt);
        
        return [
            'success' => true,
            'message' => "🗑️ I've deleted {$affected} record(s) from `{$table}` where value was '{$value}', Master.",
            'prompt' => $prompt,
            'affected_rows' => $affected
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "⚠️ Error deleting: " . $e->getMessage(),
            'prompt' => $prompt
        ];
    }
}

function addToColumnWithLog($pdo, $value, $column, $table, $prompt) {
    $allowedTables = ['cart', 'customers', 'merchandise_inventory', 'for_deliveries', 'contracts', 'chat_account', 'chat_conversation'];
    
    if (!in_array($table, $allowedTables)) {
        return [
            'success' => false,
            'message' => "⚠️ Table '{$table}' doesn't exist or I don't have access to it, Master.",
            'prompt' => $prompt
        ];
    }
    
    try {
        $query = "UPDATE {$table} SET {$column} = {$column} + {$value}";
        $stmt = $pdo->prepare("UPDATE {$table} SET {$column} = {$column} + ?");
        $stmt->execute([$value]);
        $affected = $stmt->rowCount();
        
        logAction($pdo, 'add', $table, $column, null, $value, $affected, $query, $prompt);
        
        return [
            'success' => true,
            'message' => "➕ Added {$value} to `{$column}` in `{$table}`. Affected {$affected} row(s), Master!",
            'prompt' => $prompt,
            'affected_rows' => $affected
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "⚠️ Error adding: " . $e->getMessage(),
            'prompt' => $prompt
        ];
    }
}

function subtractFromColumnWithLog($pdo, $value, $column, $table, $prompt) {
    $allowedTables = ['cart', 'customers', 'merchandise_inventory', 'for_deliveries', 'contracts', 'chat_account', 'chat_conversation'];
    
    if (!in_array($table, $allowedTables)) {
        return [
            'success' => false,
            'message' => "⚠️ Table '{$table}' doesn't exist or I don't have access to it, Master.",
            'prompt' => $prompt
        ];
    }
    
    try {
        $query = "UPDATE {$table} SET {$column} = {$column} - {$value} WHERE {$column} >= {$value}";
        $stmt = $pdo->prepare("UPDATE {$table} SET {$column} = {$column} - ? WHERE {$column} >= ?");
        $stmt->execute([$value, $value]);
        $affected = $stmt->rowCount();
        
        logAction($pdo, 'subtract', $table, $column, null, $value, $affected, $query, $prompt);
        
        return [
            'success' => true,
            'message' => "➖ Subtracted {$value} from `{$column}` in `{$table}`. Affected {$affected} row(s), Master!",
            'prompt' => $prompt,
            'affected_rows' => $affected
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "⚠️ Error subtracting: " . $e->getMessage(),
            'prompt' => $prompt
        ];
    }
}

function toggleCustomerStatusWithLog($pdo, $customer, $action, $prompt) {
    try {
        $status = ($action === 'lock') ? 0 : 1;
        $actionText = ($action === 'lock') ? 'locked' : 'unlocked';
        $actionType = ($action === 'lock') ? 'lock' : 'unlock';
        $emoji = ($action === 'lock') ? '🔒' : '🔓';
        
        $query = "UPDATE customers SET active_email = {$status} WHERE acc_number = '{$customer}' OR f_name LIKE '%{$customer}%'";
        $stmt = $pdo->prepare("UPDATE customers SET active_email = ? WHERE acc_number = ? OR f_name LIKE ?");
        $stmt->execute([$status, $customer, "%{$customer}%"]);
        $affected = $stmt->rowCount();
        
        logAction($pdo, $actionType, 'customers', 'active_email', $customer, $status, $affected, $query, $prompt);
        
        if ($affected > 0) {
            return [
                'success' => true,
                'message' => "{$emoji} I've {$actionText} {$affected} customer(s), Master!",
                'prompt' => $prompt,
                'affected_rows' => $affected,
                'action' => $action
            ];
        } else {
            return [
                'success' => false,
                'message' => "🔍 I couldn't find customer '{$customer}', Master. Please check the name or account number.",
                'prompt' => $prompt
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "⚠️ Error: " . $e->getMessage(),
            'prompt' => $prompt
        ];
    }
}

// ==============================================
// 14. READ-ONLY FUNCTIONS (No logging)
// ==============================================

function showTable($pdo, $table, $prompt) {
    $allowedTables = ['cart', 'customers', 'merchandise_inventory', 'for_deliveries', 'contracts', 'chat_account', 'chat_conversation', 'location', 'order_status_history'];
    
    if (!in_array($table, $allowedTables)) {
        return [
            'success' => false,
            'message' => "⚠️ Table '{$table}' doesn't exist or I don't have access to it, Master.",
            'prompt' => $prompt
        ];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM {$table} LIMIT 20");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($data)) {
            return [
                'success' => true,
                'message' => "📊 Table `{$table}` is empty, Master. No records found.",
                'prompt' => $prompt,
                'data' => [],
                'count' => 0
            ];
        }
        
        $output = "📊 Showing `{$table}` (Top 20 rows):\n\n";
        $headers = array_keys($data[0]);
        $output .= "Columns: " . implode(", ", $headers) . "\n";
        $output .= "Total rows: " . count($data) . "\n\n";
        
        foreach ($data as $row) {
            $rowData = [];
            foreach ($row as $key => $value) {
                if (strlen($value) > 50) {
                    $value = substr($value, 0, 47) . '...';
                }
                $rowData[] = "{$key}: {$value}";
            }
            $output .= implode(" | ", $rowData) . "\n";
        }
        
        return [
            'success' => true,
            'message' => $output,
            'prompt' => $prompt,
            'data' => $data,
            'count' => count($data)
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "⚠️ Error reading table: " . $e->getMessage(),
            'prompt' => $prompt
        ];
    }
}

function countTable($pdo, $table, $prompt) {
    $allowedTables = ['cart', 'customers', 'merchandise_inventory', 'for_deliveries', 'contracts', 'chat_account', 'chat_conversation', 'location', 'order_status_history'];
    
    if (!in_array($table, $allowedTables)) {
        return [
            'success' => false,
            'message' => "⚠️ Table '{$table}' doesn't exist or I don't have access to it, Master.",
            'prompt' => $prompt
        ];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM {$table}");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'message' => "📊 `{$table}` has {$result['total']} record(s), Master.",
            'prompt' => $prompt,
            'count' => $result['total']
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "⚠️ Error counting: " . $e->getMessage(),
            'prompt' => $prompt
        ];
    }
}

function sumColumn($pdo, $column, $table, $prompt) {
    $allowedTables = ['cart', 'customers', 'merchandise_inventory', 'for_deliveries', 'contracts', 'chat_account', 'chat_conversation'];
    
    if (!in_array($table, $allowedTables)) {
        return [
            'success' => false,
            'message' => "⚠️ Table '{$table}' doesn't exist or I don't have access to it, Master.",
            'prompt' => $prompt
        ];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT SUM({$column}) as total FROM {$table}");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'message' => "💰 Sum of `{$column}` in `{$table}`: " . ($result['total'] ?? 0) . ", Master!",
            'prompt' => $prompt,
            'total' => $result['total'] ?? 0
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "⚠️ Error summing: " . $e->getMessage(),
            'prompt' => $prompt
        ];
    }
}

function getCustomerStatus($pdo, $customer, $prompt) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE acc_number = ? OR f_name LIKE ? OR email = ?");
        $stmt->execute([$customer, "%{$customer}%", $customer]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($result)) {
            return [
                'success' => false,
                'message' => "🔍 I couldn't find customer '{$customer}', Master. Please check the name or account number.",
                'prompt' => $prompt
            ];
        }
        
        $output = "👤 Customer Details:\n\n";
        foreach ($result as $row) {
            $output .= "📛 Name: {$row['f_name']}\n";
            $output .= "🔑 Account: {$row['acc_number']}\n";
            $output .= "📧 Email: {$row['email']}\n";
            $output .= "📞 Phone: {$row['phone_number']}\n";
            $output .= "⭐ VIP: " . ($row['vip'] == 1 ? '✅ Yes' : '❌ No') . "\n";
            $output .= "📊 Status: " . ($row['active_email'] == 1 ? '🟢 Active' : '🔴 Inactive') . "\n";
            $output .= "---\n";
        }
        
        return [
            'success' => true,
            'message' => $output,
            'prompt' => $prompt,
            'data' => $result
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "⚠️ Error: " . $e->getMessage(),
            'prompt' => $prompt
        ];
    }
}

// ==============================================
// 15. SMART PROCESS
// ==============================================
function smartProcess($pdo, $prompt) {
    preg_match_all('/\d+/', $prompt, $numbers);
    
    if (count($numbers[0]) >= 2) {
        $oldValue = $numbers[0][0];
        $newValue = $numbers[0][1];
        return updateValueWithLog($pdo, $oldValue, $newValue, $prompt);
    }
    
    $tables = ['cart', 'customers', 'merchandise_inventory', 'for_deliveries', 'contracts', 'chat_account', 'chat_conversation', 'location', 'order_status_history'];
    foreach ($tables as $table) {
        if (strpos(strtolower($prompt), $table) !== false) {
            if (strpos(strtolower($prompt), 'count') !== false) {
                return countTable($pdo, $table, $prompt);
            }
            if (strpos(strtolower($prompt), 'show') !== false || strpos(strtolower($prompt), 'display') !== false) {
                return showTable($pdo, $table, $prompt);
            }
        }
    }
    
    return [
        'success' => false,
        'message' => "🤔 I'm not sure what you mean, Master.\n\nHere are some things I can help with:\n\n• 📊 'show customers' - View data\n• ✏️ '340 update to 500' - Update values\n• 🔒 'lock customer123' - Manage accounts\n• ➕ 'add 5 to pieces in cart' - Add to data\n• ↩️ 'undo' - Revert last action\n• 💬 'help' - See all commands\n\nWhat would you like me to do?",
        'prompt' => $prompt
    ];
}

// ==============================================
// 16. EXECUTE
// ==============================================
$result = processPromptWithAI($pdo, $prompt);
$result['response_time'] = date('Y-m-d H:i:s');
$result['ai_name'] = 'Jo Seph AI';

// Log the action
error_log("AI Prompter - User: {$_SESSION['acc_number']}, Prompt: {$prompt}, Success: " . ($result['success'] ? 'Yes' : 'No'));

echo json_encode($result);
exit;
?>