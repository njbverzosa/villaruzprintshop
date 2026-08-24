<?php
// API/chat_api.php - Backend for Admin

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
    // Table: chat_account - stores user chat accounts
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS chat_account (
        id INT PRIMARY KEY AUTO_INCREMENT,
        acc_number VARCHAR(50) NOT NULL UNIQUE,
        chat_sent DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TINYINT(1) DEFAULT 1 COMMENT '0=blocked, 1=unblocked',
        INDEX idx_acc_number (acc_number),
        INDEX idx_status (status)
    )");

    // Table: chat_conversation - stores all messages
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

// Create tables
createChatTables($pdo);

// ==============================================
// 4. GET OR CREATE CHAT ACCOUNT
// ==============================================
function getOrCreateChatAccount($pdo, $accNumber) {
    // Check if account exists
    $stmt = $pdo->prepare("SELECT id, status FROM chat_account WHERE acc_number = ?");
    $stmt->execute([$accNumber]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account) {
        return $account;
    }
    
    // Create new account with Asia/Manila time - format: j F Y g:i A
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
// 5. FIXED RECEIVER ACCOUNT - YOUR ACCOUNT NUMBER
// ==============================================
define('RECEIVER_ACCOUNT', '4819'); // YOUR ACCOUNT NUMBER

// ==============================================
// 6. GET ACTION
// ==============================================
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        // ==============================================
        // GET MESSAGES - FIXED
        // ==============================================
        case 'get_messages':
            // Get or create chat account
            $account = getOrCreateChatAccount($pdo, $accNumber);
            
            // Check if user is blocked
            if ($account['status'] == 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Your account has been blocked from chat. Please contact support.',
                    'blocked' => true
                ]);
                exit();
            }
            
            // Get ALL messages where user is sender OR receiver
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
                    receiver_acc,
                    CASE 
                        WHEN acc_number = ? THEN 'sent'
                        ELSE 'received'
                    END as direction
                FROM chat_conversation 
                WHERE acc_number = ? OR receiver_acc = ?
                ORDER BY created_at ASC 
                LIMIT 200
            ");
            $stmt->execute([$accNumber, $accNumber, $accNumber]);
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
            
            // Mark ALL messages as read for this user
            $stmt = $pdo->prepare("
                UPDATE chat_conversation 
                SET status = 1 
                WHERE receiver_acc = ? AND status = 0
            ");
            $stmt->execute([$accNumber]);
            
            $stmt = $pdo->prepare("
                UPDATE chat_conversation 
                SET status = 1 
                WHERE acc_number = ? AND status = 0
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
        // SEND MESSAGE - FIXED RECEIVER
        // ==============================================
        case 'send_message':
            $message = trim($_POST['message'] ?? '');
            
            if (empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
                exit();
            }
            
            // Get or create chat account
            $account = getOrCreateChatAccount($pdo, $accNumber);
            
            // Check if user is blocked
            if ($account['status'] == 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Your account has been blocked from chat. Please contact support.',
                    'blocked' => true
                ]);
                exit();
            }
            
            // Determine sender type and receiver
            if ($userRole === 'Admin') {
                $senderType = 'admin';
                $receiverAcc = $_POST['receiver_acc'] ?? '';
                
                if (empty($receiverAcc)) {
                    echo json_encode(['success' => false, 'message' => 'Receiver account required']);
                    exit();
                }
            } else {
                $senderType = 'customer';
                $receiverAcc = RECEIVER_ACCOUNT;
            }
            
            // Get current Asia/Manila time
            $currentTime = date('H:i:s');
            $currentDate = date('Y-m-d');
            $currentDateTime = date('Y-m-d H:i:s');
            
            // Insert message with Asia/Manila time
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
            
            // Update chat_sent for the account with Asia/Manila time
            $stmt = $pdo->prepare("
                UPDATE chat_account 
                SET chat_sent = ? 
                WHERE acc_number = ?
            ");
            $stmt->execute([$currentDateTime, $accNumber]);
            
            // Get the inserted message with formatted time
            $messageId = $pdo->lastInsertId();
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
        // GET CONVERSATIONS (for admin)
        // ==============================================
        case 'get_conversations':
            if ($userRole !== 'Admin') {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit();
            }
            
            // Get all unique conversations
            $stmt = $pdo->prepare("
                SELECT 
                    ca.acc_number,
                    ca.chat_sent,
                    ca.status as account_status,
                    (SELECT message FROM chat_conversation WHERE acc_number = ca.acc_number OR receiver_acc = ca.acc_number ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM chat_conversation WHERE acc_number = ca.acc_number OR receiver_acc = ca.acc_number ORDER BY created_at DESC LIMIT 1) as last_message_raw,
                    (SELECT COUNT(*) FROM chat_conversation WHERE receiver_acc = ? AND acc_number = ca.acc_number AND status = 0) as unread_count,
                    (SELECT COUNT(*) FROM chat_conversation WHERE acc_number = ca.acc_number OR receiver_acc = ca.acc_number) as total_messages,
                    c.f_name as customer_name,
                    c.email as customer_email,
                    c.phone_number as customer_phone
                FROM chat_account ca
                LEFT JOIN customers c ON ca.acc_number = c.acc_number
                WHERE ca.acc_number != ?
                ORDER BY ca.chat_sent DESC
            ");
            $stmt->execute([RECEIVER_ACCOUNT, RECEIVER_ACCOUNT]);
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
        // GET UNREAD COUNT
        // ==============================================
        case 'get_unread_count':
            if ($userRole === 'Admin') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total_unread 
                    FROM chat_conversation 
                    WHERE receiver_acc = ? AND status = 0
                ");
                $stmt->execute([RECEIVER_ACCOUNT]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total_unread 
                    FROM chat_conversation 
                    WHERE receiver_acc = ? AND status = 0
                ");
                $stmt->execute([$accNumber]);
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'unread_count' => intval($result['total_unread'] ?? 0),
                'timezone' => 'Asia/Manila'
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

        // ==============================================
        // DEFAULT
        // ==============================================
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