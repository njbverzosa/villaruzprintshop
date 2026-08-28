<?php
// Customer_API/chat.php - Backend for Customer (MULTI-CUSTOMER FIXED)

session_start();
header('Content-Type: application/json');

// ==============================================
// 0. SET TIMEZONE TO ASIA/MANILA
// ==============================================
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 1. CHECK LOGIN STATUS
// ==============================================
if (!isset($_SESSION['user_role']) || !isset($_SESSION['user_id']) || !isset($_SESSION['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

// ==============================================
// 2. VERIFY CSRF TOKEN
// ==============================================
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// ==============================================
// 3. CREATE TABLES IF NOT EXISTS
// ==============================================
function createChatTables($pdo) {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS chat_account (
        id INT PRIMARY KEY AUTO_INCREMENT,
        acc_number VARCHAR(50) NOT NULL UNIQUE,
        chat_sent DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TINYINT(1) DEFAULT 1 COMMENT '0=blocked, 1=unblocked',
        INDEX idx_acc_number (acc_number),
        INDEX idx_status (status)
    )");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS chat_conversation (
        id INT PRIMARY KEY AUTO_INCREMENT,
        acc_number VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        time TIME DEFAULT CURRENT_TIME,
        date DATE DEFAULT CURRENT_DATE,
        status TINYINT(1) DEFAULT 0 COMMENT '0=unread, 1=read',
        sender_type ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
        receiver_acc VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_acc_number (acc_number),
        INDEX idx_status (status),
        INDEX idx_date (date),
        INDEX idx_sender (sender_type),
        INDEX idx_receiver (receiver_acc)
    )");
}

createChatTables($pdo);

// ==============================================
// 4. GET OR CREATE CHAT ACCOUNT
// ==============================================
function getOrCreateChatAccount($pdo, $accNumber) {
    $stmt = $pdo->prepare("SELECT id, status FROM chat_account WHERE acc_number = ?");
    $stmt->execute([$accNumber]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account) {
        return $account;
    }
    
    // Use Asia/Manila time for insertion
    $currentDateTime = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        INSERT INTO chat_account (acc_number, chat_sent, status) 
        VALUES (?, ?, 1)
    ");
    $stmt->execute([$accNumber, $currentDateTime]);
    
    return [
        'id' => $pdo->lastInsertId(),
        'status' => 1
    ];
}

// ==============================================
// 5. FIXED RECEIVER ACCOUNT (ADMIN)
// ==============================================
define('RECEIVER_ACCOUNT', '4819');

// ==============================================
// 6. GET ACTION
// ==============================================
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        // ==============================================
        // GET MESSAGES - FIXED: Get ALL messages for this customer
        // ==============================================
        case 'get_messages':
            // Get or create chat account for this customer
            $account = getOrCreateChatAccount($pdo, $accNumber);
            
            if ($account['status'] == 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Your account has been blocked from chat.',
                    'blocked' => true
                ]);
                exit();
            }
            
            // Get ALL messages where this customer is sender OR receiver
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    acc_number,
                    message,
                    time,
                    date,
                    created_at,
                    status,
                    sender_type,
                    receiver_acc
                FROM chat_conversation 
                WHERE acc_number = ? OR receiver_acc = ?
                ORDER BY created_at ASC 
                LIMIT 200
            ");
            $stmt->execute([$accNumber, $accNumber]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format dates using PHP with Asia/Manila timezone - format: j F Y g:i A
            foreach ($messages as &$msg) {
                // Format time
                if (!empty($msg['time'])) {
                    $timeObj = DateTime::createFromFormat('H:i:s', $msg['time']);
                    if ($timeObj) {
                        $msg['time'] = $timeObj->format('g:i A');
                    }
                }
                
                // Format date
                if (!empty($msg['date'])) {
                    $dateObj = DateTime::createFromFormat('Y-m-d', $msg['date']);
                    if ($dateObj) {
                        $msg['date_formatted'] = $dateObj->format('j F Y');
                    }
                }
                
                // Format created_at - format: j F Y g:i A
                if (!empty($msg['created_at'])) {
                    $createdObj = new DateTime($msg['created_at']);
                    $createdObj->setTimezone(new DateTimeZone('Asia/Manila'));
                    $msg['formatted_time'] = $createdObj->format('j F Y g:i A');
                }
            }
            
            // Mark messages as READ (status = 1) for this user
            // Mark messages where this user is the receiver (messages sent TO this user)
            $stmt = $pdo->prepare("
                UPDATE chat_conversation 
                SET status = 1 
                WHERE receiver_acc = ? AND status = 0
            ");
            $stmt->execute([$accNumber]);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'acc_number' => $accNumber,
                'account_status' => $account['status'],
                'total_messages' => count($messages),
                'timezone' => 'Asia/Manila'
            ]);
            break;

        // ==============================================
        // SEND MESSAGE - Customer sends to admin
        // ==============================================
        case 'send_message':
            $message = trim($_POST['message'] ?? '');
            
            if (empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
                exit();
            }
            
            // Get or create chat account for this customer
            $account = getOrCreateChatAccount($pdo, $accNumber);
            
            if ($account['status'] == 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Your account has been blocked from chat.',
                    'blocked' => true
                ]);
                exit();
            }
            
            // Customer sends to admin (RECEIVER_ACCOUNT)
            $senderType = 'customer';
            $receiverAcc = RECEIVER_ACCOUNT;
            
            // Get current Asia/Manila time
            $currentTime = date('H:i:s');
            $currentDate = date('Y-m-d');
            $currentDateTime = date('Y-m-d H:i:s');
            
            // Insert message with status = 0 (unread for admin)
            $stmt = $pdo->prepare("
                INSERT INTO chat_conversation (
                    acc_number, 
                    message, 
                    time, 
                    date, 
                    status, 
                    sender_type, 
                    receiver_acc,
                    created_at
                ) VALUES (?, ?, ?, ?, 0, ?, ?, ?)
            ");
            $stmt->execute([$accNumber, $message, $currentTime, $currentDate, $senderType, $receiverAcc, $currentDateTime]);
            
            // Get the inserted message ID for logging
            $messageId = $pdo->lastInsertId();
            
            // Update chat_sent timestamp for this customer with Asia/Manila time
            $stmt = $pdo->prepare("
                UPDATE chat_account 
                SET chat_sent = ? 
                WHERE acc_number = ?
            ");
            $stmt->execute([$currentDateTime, $accNumber]);
            
            // Return the new message data
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    acc_number,
                    message,
                    time,
                    date,
                    created_at,
                    status,
                    sender_type,
                    receiver_acc
                FROM chat_conversation 
                WHERE id = ?
            ");
            $stmt->execute([$messageId]);
            $newMessage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Format the new message time - format: j F Y g:i A
            if (!empty($newMessage['created_at'])) {
                $createdObj = new DateTime($newMessage['created_at']);
                $createdObj->setTimezone(new DateTimeZone('Asia/Manila'));
                $newMessage['formatted_time'] = $createdObj->format('j F Y g:i A');
            }
            if (!empty($newMessage['time'])) {
                $timeObj = DateTime::createFromFormat('H:i:s', $newMessage['time']);
                if ($timeObj) {
                    $newMessage['time'] = $timeObj->format('g:i A');
                }
            }
            if (!empty($newMessage['date'])) {
                $dateObj = DateTime::createFromFormat('Y-m-d', $newMessage['date']);
                if ($dateObj) {
                    $newMessage['date_formatted'] = $dateObj->format('j F Y');
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Message sent',
                'acc_number' => $accNumber,
                'receiver_acc' => $receiverAcc,
                'message_data' => $newMessage,
                'timezone' => 'Asia/Manila'
            ]);
            break;

        // ==============================================
        // ADMIN SEND MESSAGE - Admin sends to customer
        // ==============================================
        case 'admin_send_message':
            if ($userRole !== 'Admin') {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit();
            }
            
            $targetAcc = $_POST['target_acc'] ?? '';
            $message = trim($_POST['message'] ?? '');
            
            if (empty($targetAcc) || empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Target account and message are required']);
                exit();
            }
            
            // Get or create chat account for the target customer
            $account = getOrCreateChatAccount($pdo, $targetAcc);
            
            if ($account['status'] == 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'This customer has been blocked from chat.',
                    'blocked' => true
                ]);
                exit();
            }
            
            // Admin sends to customer (targetAcc)
            $senderType = 'admin';
            $receiverAcc = $targetAcc;
            $senderAcc = RECEIVER_ACCOUNT;
            
            // Get current Asia/Manila time
            $currentTime = date('H:i:s');
            $currentDate = date('Y-m-d');
            $currentDateTime = date('Y-m-d H:i:s');
            
            // Insert message with status = 0 (unread for customer)
            $stmt = $pdo->prepare("
                INSERT INTO chat_conversation (
                    acc_number, 
                    message, 
                    time, 
                    date, 
                    status, 
                    sender_type, 
                    receiver_acc,
                    created_at
                ) VALUES (?, ?, ?, ?, 0, ?, ?, ?)
            ");
            $stmt->execute([$senderAcc, $message, $currentTime, $currentDate, $senderType, $receiverAcc, $currentDateTime]);
            
            // Get the inserted message ID
            $messageId = $pdo->lastInsertId();
            
            // Update chat_sent timestamp for this customer
            $stmt = $pdo->prepare("
                UPDATE chat_account 
                SET chat_sent = ? 
                WHERE acc_number = ?
            ");
            $stmt->execute([$currentDateTime, $targetAcc]);
            
            // Return the new message data
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    acc_number,
                    message,
                    time,
                    date,
                    created_at,
                    status,
                    sender_type,
                    receiver_acc
                FROM chat_conversation 
                WHERE id = ?
            ");
            $stmt->execute([$messageId]);
            $newMessage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Format the new message time
            if (!empty($newMessage['created_at'])) {
                $createdObj = new DateTime($newMessage['created_at']);
                $createdObj->setTimezone(new DateTimeZone('Asia/Manila'));
                $newMessage['formatted_time'] = $createdObj->format('j F Y g:i A');
            }
            if (!empty($newMessage['time'])) {
                $timeObj = DateTime::createFromFormat('H:i:s', $newMessage['time']);
                if ($timeObj) {
                    $newMessage['time'] = $timeObj->format('g:i A');
                }
            }
            if (!empty($newMessage['date'])) {
                $dateObj = DateTime::createFromFormat('Y-m-d', $newMessage['date']);
                if ($dateObj) {
                    $newMessage['date_formatted'] = $dateObj->format('j F Y');
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Message sent to customer',
                'target_acc' => $targetAcc,
                'message_data' => $newMessage,
                'timezone' => 'Asia/Manila'
            ]);
            break;

        // ==============================================
        // GET CONVERSATIONS (for admin only)
        // ==============================================
        case 'get_conversations':
            if ($userRole !== 'Admin') {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit();
            }
            
            // Get all unique customers with their chat info
            $stmt = $pdo->prepare("
                SELECT 
                    ca.acc_number,
                    ca.chat_sent,
                    ca.status as account_status,
                    c.f_name as customer_name,
                    c.email as customer_email,
                    c.phone_number as customer_phone,
                    (SELECT message FROM chat_conversation 
                     WHERE acc_number = ca.acc_number OR receiver_acc = ca.acc_number 
                     ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM chat_conversation 
                     WHERE acc_number = ca.acc_number OR receiver_acc = ca.acc_number 
                     ORDER BY created_at DESC LIMIT 1) as last_message_raw,
                    (SELECT COUNT(*) FROM chat_conversation 
                     WHERE acc_number = ca.acc_number AND status = 0) as unread_count,
                    (SELECT COUNT(*) FROM chat_conversation 
                     WHERE acc_number = ca.acc_number OR receiver_acc = ca.acc_number) as total_messages
                FROM chat_account ca
                LEFT JOIN customers c ON ca.acc_number = c.acc_number
                WHERE ca.acc_number != ?
                ORDER BY ca.chat_sent DESC
            ");
            $stmt->execute([RECEIVER_ACCOUNT]);
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format dates using PHP with Asia/Manila timezone - format: j F Y g:i A
            foreach ($conversations as &$conv) {
                if (!empty($conv['last_message_raw'])) {
                    $dateObj = new DateTime($conv['last_message_raw']);
                    $dateObj->setTimezone(new DateTimeZone('Asia/Manila'));
                    $conv['last_message_time'] = $dateObj->format('j F Y g:i A');
                }
                if (!empty($conv['chat_sent'])) {
                    $dateObj = new DateTime($conv['chat_sent']);
                    $dateObj->setTimezone(new DateTimeZone('Asia/Manila'));
                    $conv['chat_sent'] = $dateObj->format('j F Y g:i A');
                }
            }
            
            echo json_encode([
                'success' => true,
                'conversations' => $conversations,
                'total' => count($conversations),
                'timezone' => 'Asia/Manila'
            ]);
            break;

        // ==============================================
        // GET UNREAD COUNT FOR SPECIFIC CUSTOMER
        // ==============================================
        case 'get_unread_count':
            $targetAcc = $_POST['customer_acc'] ?? '';
            
            if (empty($targetAcc)) {
                echo json_encode(['success' => false, 'message' => 'Customer account required']);
                exit();
            }
            
            if ($userRole === 'Admin') {
                // Admin checking unread messages from this customer
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as unread_count 
                    FROM chat_conversation 
                    WHERE acc_number = ? 
                    AND status = 0
                ");
                $stmt->execute([$targetAcc]);
            } else {
                // Customer checking their own unread messages
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as unread_count 
                    FROM chat_conversation 
                    WHERE receiver_acc = ? 
                    AND status = 0
                ");
                $stmt->execute([$targetAcc]);
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'unread_count' => intval($result['unread_count'] ?? 0)
            ]);
            break;

        // ==============================================
        // BLOCK/UNBLOCK USER (admin only)
        // ==============================================
        case 'toggle_block':
            if ($userRole !== 'Admin') {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit();
            }
            
            $targetAcc = $_POST['target_acc'] ?? '';
            $blockStatus = intval($_POST['block_status'] ?? 0);
            
            if (empty($targetAcc)) {
                echo json_encode(['success' => false, 'message' => 'Target account required']);
                exit();
            }
            
            $stmt = $pdo->prepare("
                UPDATE chat_account 
                SET status = ? 
                WHERE acc_number = ?
            ");
            $stmt->execute([$blockStatus, $targetAcc]);
            
            echo json_encode([
                'success' => true,
                'message' => $blockStatus == 0 ? 'User blocked' : 'User unblocked',
                'status' => $blockStatus,
                'timezone' => 'Asia/Manila'
            ]);
            break;

        // ==============================================
        // CHECK ACCOUNT STATUS
        // ==============================================
        case 'check_status':
            $account = getOrCreateChatAccount($pdo, $accNumber);
            
            echo json_encode([
                'success' => true,
                'status' => $account['status'],
                'is_blocked' => $account['status'] == 0,
                'timezone' => 'Asia/Manila'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Chat Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Chat Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>