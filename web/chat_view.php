<?php
// web/chat_view.php - Chat View (Admin View) - FIXED

session_start();

// ==============================================
// 1. FIX PATHS - config.php is in DB_Conn folder at root level
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 2. CHECK LOGIN STATUS
// ==============================================
function isLoggedIn()
{
    return isset($_SESSION['user_role']) &&
        isset($_SESSION['user_id']) &&
        isset($_SESSION['acc_number']);
}

// Redirect to login if not logged in
if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Please login first to access the shop.';
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 3. GET USER DATA FROM SESSION
// ==============================================
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

// Fetch user details from database
$userData = null;
if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, role, user_name, authorize_access FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($userRole === 'Customer') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$user = $userData;

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ==============================================
// 4. GET THE TARGET ACCOUNT
// ==============================================
$targetAcc = isset($_GET['acc']) ? trim($_GET['acc']) : '';

if (empty($targetAcc)) {
    header('Location: chat.php');
    exit;
}

// Check if the chat account exists
$stmt = $pdo->prepare("SELECT * FROM chat_account WHERE acc_number = ?");
$stmt->execute([$targetAcc]);
$chatAccount = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chatAccount) {
    // If no chat account exists, create one automatically
    $stmt = $pdo->prepare("INSERT INTO chat_account (acc_number, status, chat_sent) VALUES (?, 1, NOW())");
    $stmt->execute([$targetAcc]);
}

// Get customer info if available
$stmt = $pdo->prepare("SELECT f_name, email, phone_number FROM customers WHERE acc_number = ?");
$stmt->execute([$targetAcc]);
$customerInfo = $stmt->fetch(PDO::FETCH_ASSOC);

// ==============================================
// 5. HANDLE AJAX REQUESTS
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }

    $action = $_POST['action'] ?? '';

    // ==============================================
    // GET MESSAGES
    // ==============================================
    if ($action === 'get_messages') {
        $lastId = intval($_POST['last_id'] ?? 0);
        $limit = 50;

        $stmt = $pdo->prepare("
            SELECT cc.*, 
                   DATE_FORMAT(cc.created_at, '%b %d, %Y %h:%i %p') as formatted_time
            FROM chat_conversation cc
            WHERE (cc.acc_number = ? OR cc.receiver_acc = ?)
              AND cc.id > ?
            ORDER BY cc.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$targetAcc, $targetAcc, $lastId, $limit]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark messages as read (messages sent by customer to admin)
        $stmt = $pdo->prepare("
            UPDATE chat_conversation 
            SET status = 1 
            WHERE acc_number = ? AND receiver_acc = ? AND status = 0
        ");
        $stmt->execute([$targetAcc, $accNumber]);

        // Mark messages as read (messages sent by admin to customer)
        $stmt2 = $pdo->prepare("
            UPDATE chat_conversation 
            SET status = 1 
            WHERE acc_number = ? AND receiver_acc = ? AND status = 0
        ");
        $stmt2->execute([$accNumber, $targetAcc]);

        // Get sender info for each message
        foreach ($messages as &$msg) {
            if ($msg['sender_type'] === 'admin') {
                $stmt2 = $pdo->prepare("SELECT f_name FROM admins WHERE acc_number = ?");
                $stmt2->execute([$msg['acc_number']]);
                $sender = $stmt2->fetch(PDO::FETCH_ASSOC);
                $msg['sender_name'] = $sender['f_name'] ?? 'Admin';
                $msg['is_mine'] = ($msg['acc_number'] == $accNumber);
            } else {
                $stmt2 = $pdo->prepare("SELECT f_name FROM customers WHERE acc_number = ?");
                $stmt2->execute([$msg['acc_number']]);
                $sender = $stmt2->fetch(PDO::FETCH_ASSOC);
                $msg['sender_name'] = $sender['f_name'] ?? 'Customer';
                $msg['is_mine'] = false;
            }
        }

        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'target_acc' => $targetAcc
        ]);
        exit();
    }

    // ==============================================
    // SEND MESSAGE - FIXED
    // ==============================================
    if ($action === 'send_message') {
        $message = trim($_POST['message'] ?? '');

        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
            exit();
        }

        try {
            // Check if chat account exists, if not create one
            $stmt = $pdo->prepare("SELECT * FROM chat_account WHERE acc_number = ?");
            $stmt->execute([$targetAcc]);
            $chatAccountCheck = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$chatAccountCheck) {
                $stmt = $pdo->prepare("INSERT INTO chat_account (acc_number, status, chat_sent) VALUES (?, 1, NOW())");
                $stmt->execute([$targetAcc]);
            }

            // Insert message with correct sender and receiver
            // acc_number = admin's account (sender)
            // receiver_acc = customer's account (receiver)
            $stmt = $pdo->prepare("
                INSERT INTO chat_conversation 
                (acc_number, receiver_acc, message, sender_type, status, created_at, time, date) 
                VALUES (?, ?, ?, 'admin', 0, NOW(), CURRENT_TIME, CURRENT_DATE)
            ");
            $stmt->execute([$accNumber, $targetAcc, $message]);
            $messageId = $pdo->lastInsertId();

            // Get the inserted message
            $stmt = $pdo->prepare("
                SELECT *, DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') as formatted_time
                FROM chat_conversation 
                WHERE id = ?
            ");
            $stmt->execute([$messageId]);
            $newMessage = $stmt->fetch(PDO::FETCH_ASSOC);
            $newMessage['sender_name'] = $user['f_name'] ?? 'Admin';
            $newMessage['is_mine'] = true;

            echo json_encode([
                'success' => true,
                'message' => 'Message sent successfully',
                'message_data' => $newMessage
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }

    // ==============================================
    // MARK AS READ
    // ==============================================
    if ($action === 'mark_read') {
        // Mark messages sent by customer to admin as read
        $stmt = $pdo->prepare("
            UPDATE chat_conversation 
            SET status = 1 
            WHERE acc_number = ? AND receiver_acc = ? AND status = 0
        ");
        $stmt->execute([$targetAcc, $accNumber]);

        // Also mark messages sent by admin to customer as read
        $stmt2 = $pdo->prepare("
            UPDATE chat_conversation 
            SET status = 1 
            WHERE acc_number = ? AND receiver_acc = ? AND status = 0
        ");
        $stmt2->execute([$accNumber, $targetAcc]);

        $affected = $stmt->rowCount() + $stmt2->rowCount();

        echo json_encode([
            'success' => true,
            'message' => 'Messages marked as read',
            'marked_count' => $affected
        ]);
        exit();
    }

    // ==============================================
    // TYPING INDICATOR
    // ==============================================
    if ($action === 'typing') {
        echo json_encode(['success' => true]);
        exit();
    }
}

// ==============================================
// 6. GET INITIAL MESSAGES
// ==============================================
$stmt = $pdo->prepare("
    SELECT cc.*, 
           DATE_FORMAT(cc.created_at, '%b %d, %Y %h:%i %p') as formatted_time
    FROM chat_conversation cc
    WHERE cc.acc_number = ? OR cc.receiver_acc = ?
    ORDER BY cc.created_at ASC
    LIMIT 100
");
$stmt->execute([$targetAcc, $targetAcc]);
$initialMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark initial messages as read
$stmt = $pdo->prepare("
    UPDATE chat_conversation 
    SET status = 1 
    WHERE acc_number = ? AND receiver_acc = ? AND status = 0
");
$stmt->execute([$targetAcc, $accNumber]);

$stmt2 = $pdo->prepare("
    UPDATE chat_conversation 
    SET status = 1 
    WHERE acc_number = ? AND receiver_acc = ? AND status = 0
");
$stmt2->execute([$accNumber, $targetAcc]);

// Get unread count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as unread_count 
    FROM chat_conversation 
    WHERE receiver_acc = ? AND status = 0
");
$stmt->execute([$accNumber]);
$unreadResult = $stmt->fetch(PDO::FETCH_ASSOC);
$totalUnread = $unreadResult['unread_count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Chat | <?php echo htmlspecialchars($targetAcc); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== BASE STYLES ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .app-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        /* ========== DASHBOARD HEADER ========== */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: #ffffff;
            padding: 18px 25px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .dashboard-header .welcome h4 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .dashboard-header .welcome h4 i {
            color: #3b82f6;
            margin-right: 8px;
        }

        /* ========== CHAT CONTAINER ========== */
        .chat-container {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            height: calc(100vh - 200px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ========== CHAT HEADER INSIDE CONTAINER ========== */
        .chat-header-inner {
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: white;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            border-radius: 10px 10px 0 0;
        }

        .chat-header-inner .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .chat-header-inner .header-left .back-btn {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 10px;
            transition: 0.2s;
        }

        .chat-header-inner .header-left .back-btn:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .chat-header-inner .header-left .user-info {
            display: flex;
            flex-direction: column;
        }

        .chat-header-inner .header-left .user-info .user-name {
            font-weight: 600;
            font-size: 16px;
        }

        .chat-header-inner .header-left .user-info .user-acc {
            font-size: 12px;
            opacity: 0.85;
        }

        .chat-header-inner .header-left .user-info .user-acc i {
            font-size: 10px;
        }

        .chat-header-inner .header-right .status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            background: rgba(255, 255, 255, 0.12);
            padding: 5px 12px;
            border-radius: 20px;
        }

        .chat-header-inner .header-right .status .green-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #4ade80;
            display: inline-block;
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .chat-header-inner .header-right .status .online-text {
            font-weight: 500;
        }

        /* ========== CHAT MESSAGES ========== */
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #fafbfc;
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-height: 300px;
        }

        /* ========== MESSAGE BUBBLES ========== */
        .message {
            max-width: 70%;
            padding: 10px 16px;
            border-radius: 16px;
            word-wrap: break-word;
            position: relative;
            animation: fadeIn 0.2s ease;
        }

        .message.admin {
            align-self: flex-end;
            background: #3b82f6;
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.customer {
            align-self: flex-start;
            background: #e2e8f0;
            color: #1e293b;
            border-bottom-left-radius: 4px;
        }

        .message .msg-time {
            font-size: 9px;
            opacity: 0.7;
            margin-top: 4px;
            display: block;
            text-align: right;
        }

        .message .msg-sender {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 2px;
            display: block;
        }

        .message.admin .msg-sender {
            color: #bfdbfe;
        }

        .message.customer .msg-sender {
            color: #64748b;
        }

        .message .msg-text {
            font-size: 14px;
            line-height: 1.5;
        }

        .message .msg-status {
            font-size: 10px;
            margin-left: 6px;
            opacity: 0.6;
        }

        .message .msg-status.read {
            color: #34d399;
        }

        .message .msg-status.delivered {
            color: #fbbf24;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ========== EMPTY STATE ========== */
        .empty-chat {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #94a3b8;
        }

        .empty-chat i {
            font-size: 60px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-chat h4 {
            color: #64748b;
            font-size: 18px;
        }

        .empty-chat p {
            font-size: 14px;
            margin-top: 4px;
        }

        /* ========== MESSAGE INPUT ========== */
        .chat-input-area {
            padding: 15px 20px;
            border-top: 1px solid #e2e8f0;
            background: #ffffff;
            display: flex;
            gap: 12px;
            align-items: center;
            border-radius: 0 0 10px 10px;
            flex-shrink: 0;
        }

        .chat-input-area input {
            flex: 1;
            padding: 12px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: 0.2s;
            background: #f8fafc;
            outline: none;
        }

        .chat-input-area input:focus {
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .chat-input-area input:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
        }

        .chat-input-area .send-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 16px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chat-input-area .send-btn:hover:not(:disabled) {
            background: #2563eb;
            transform: scale(1.02);
        }

        .chat-input-area .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ========== TYPING INDICATOR ========== */
        .typing-indicator {
            align-self: flex-start;
            padding: 8px 16px;
            background: #e2e8f0;
            border-radius: 16px;
            border-bottom-left-radius: 4px;
            display: none;
        }

        .typing-indicator span {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #94a3b8;
            margin: 0 2px;
            animation: typing 1.4s infinite both;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
                opacity: 0.4;
            }
            30% {
                transform: translateY(-6px);
                opacity: 1;
            }
        }

        /* ========== BURGER MENU ========== */
        .burger-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 48px;
            height: 48px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .burger-btn:hover {
            background: #f8fafc;
            transform: scale(1.02);
        }

        .burger-btn i {
            font-size: 24px;
            color: #3b82f6;
        }

        .side-menu {
            position: fixed;
            top: 0;
            right: -320px;
            width: 280px;
            height: 100vh;
            background: #ffffff;
            box-shadow: -5px 0 25px rgba(0, 0, 0, 0.1);
            z-index: 1002;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #e2e8f0;
        }

        .side-menu.open {
            right: 0;
        }

        .menu-header {
            padding: 25px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .menu-header .user-name {
            font-weight: 700;
            font-size: 18px;
            color: #0f172a;
            margin-top: 8px;
        }

        .menu-header .user-greeting {
            font-size: 13px;
            color: #64748b;
        }

        .menu-header i {
            font-size: 40px;
            color: #3b82f6;
        }

        .menu-nav {
            flex: 1;
            padding: 20px;
        }

        .menu-nav .nav-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 12px;
            border-radius: 14px;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s;
            margin-bottom: 8px;
        }

        .menu-nav .nav-item i {
            width: 24px;
            font-size: 20px;
            color: #3b82f6;
        }

        .menu-nav .nav-item span {
            font-size: 15px;
            font-weight: 500;
        }

        .menu-nav .nav-item:hover {
            background: #eff6ff;
            color: #1e293b;
        }

        .menu-nav .nav-item.active {
            background: #eff6ff;
            color: #3b82f6;
            border-left: 3px solid #3b82f6;
        }

        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
            z-index: 1000;
            display: none;
        }

        .menu-overlay.active {
            display: block;
        }

        /* ========== TOAST ========== */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 10px;
            color: white;
            font-weight: 500;
            z-index: 2000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .toast-success {
            background: #10b981;
        }

        .toast-error {
            background: #ef4444;
        }

        .toast-warning {
            background: #f59e0b;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            .dashboard-header {
                padding: 12px 16px;
                border-radius: 10px;
            }

            .dashboard-header .welcome h4 {
                font-size: 15px;
            }

            .chat-container {
                height: calc(100vh - 100px);
                border-radius: 10px;
            }

            .chat-header-inner {
                padding: 12px 16px;
                border-radius: 10px 10px 0 0;
            }

            .chat-header-inner .header-left .user-info .user-name {
                font-size: 14px;
            }

            .chat-header-inner .header-left .user-info .user-acc {
                font-size: 11px;
            }

            .chat-header-inner .header-right .status {
                font-size: 11px;
                padding: 4px 10px;
            }

            .chat-messages {
                padding: 12px;
                min-height: 200px;
            }

            .message {
                max-width: 85%;
                padding: 8px 14px;
                font-size: 13px;
            }

            .chat-input-area {
                padding: 10px 14px;
                border-radius: 0 0 10px 10px;
            }

            .chat-input-area input {
                font-size: 13px;
                padding: 10px 14px;
            }

            .chat-input-area .send-btn {
                padding: 10px 16px;
                font-size: 14px;
            }

            .burger-btn {
                top: 15px;
                right: 15px;
                width: 42px;
                height: 42px;
            }
        }

        @media (max-width: 480px) {

            .chat-container {
                border-radius: 10px;
            }

            .chat-header-inner {
                padding: 10px 12px;
                border-radius: 10px 10px 0 0;
            }

            .chat-header-inner .header-left .back-btn {
                font-size: 16px;
                padding: 6px 10px;
            }

            .chat-header-inner .header-left .user-info .user-name {
                font-size: 13px;
            }

            .chat-header-inner .header-left .user-info .user-acc {
                font-size: 10px;
            }

            .chat-header-inner .header-right .status {
                font-size: 10px;
                padding: 3px 8px;
            }

            .chat-header-inner .header-right .status .green-dot {
                width: 8px;
                height: 8px;
            }

            .message {
                max-width: 90%;
                padding: 8px 12px;
                font-size: 12px;
            }

            .chat-input-area {
                padding: 10px 12px;
                border-radius: 0 0 10px 10px;
            }

            .chat-input-area input {
                font-size: 12px;
                padding: 8px 12px;
            }

            .chat-input-area .send-btn {
                padding: 8px 12px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }

            
            .dashboard-header {
                padding: 20px 30px;
                border-radius: 20px;
            }

            .welcome h1 {
                font-size: 18px;
            }

        }
    </style>
</head>

<body>
    <div class="app-wrapper">
        <main class="main-content">
            <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">
            <input type="hidden" id="targetAcc" value="<?php echo htmlspecialchars($targetAcc); ?>">
            <input type="hidden" id="myAcc" value="<?php echo htmlspecialchars($accNumber); ?>">

            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="welcome">
                    <h4><i class="fas fa-comment-dots"></i> Live Chat Centre</h4>
                </div>
            </div>

            <!-- Chat Container -->
            <div class="chat-container">

                <!-- Chat Header INSIDE the container -->
                <div class="chat-header-inner">
                    <div class="header-left">
                        <button class="back-btn" onclick="window.location.href='chat.php'" title="Back to conversations">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <div class="user-info">
                            <span class="user-name">
                                <?php echo htmlspecialchars($customerInfo['f_name'] ?? $targetAcc); ?>
                            </span>
                            <span class="user-acc">
                                <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($targetAcc); ?>
                            </span>
                        </div>
                    </div>
                    <div class="header-right">
                        <div class="status">
                            <span class="green-dot"></span>
                            <span class="online-text">Online</span>
                        </div>
                    </div>
                </div>

                <!-- Chat Messages -->
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($initialMessages)): ?>
                        <div class="empty-chat">
                            <i class="fas fa-comment-dots"></i>
                            <h4>No messages yet</h4>
                            <p>Start a conversation with this customer</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($initialMessages as $msg):
                            $isAdmin = $msg['sender_type'] === 'admin';
                            $senderName = $isAdmin ? 'You' : ($customerInfo['f_name'] ?? 'Customer');
                            $isMine = ($msg['acc_number'] == $accNumber);
                        ?>
                            <div class="message <?php echo $isMine ? 'admin' : 'customer'; ?>" data-id="<?php echo $msg['id']; ?>">
                                <div class="msg-text"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <span class="msg-time">
                                    <?php echo htmlspecialchars($msg['formatted_time']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="typing-indicator" id="typingIndicator">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>

                <!-- Message Input -->
                <div class="chat-input-area">
                    <input type="text" id="messageInput" placeholder="Type a message..." autocomplete="off">
                    <button class="send-btn" id="sendBtn">
                        <i class="fas fa-paper-plane"></i>
                        <span class="send-text">Send</span>
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script>
        // ==============================================
        // CSRF TOKEN
        // ==============================================
        const csrfToken = document.getElementById('csrfToken').value;
        const targetAcc = document.getElementById('targetAcc').value;
        const myAcc = document.getElementById('myAcc').value;

        const chatMessages = document.getElementById('chatMessages');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const typingIndicator = document.getElementById('typingIndicator');

        let lastMessageId = 0;
        let isFetching = false;
        let isSending = false;

        // ==============================================
        // BURGER MENU
        // ==============================================
        const burgerBtn = document.getElementById('burgerBtn');
        const sideMenu = document.getElementById('sideMenu');
        const menuOverlay = document.getElementById('menuOverlay');

        function openMenu() {
            sideMenu.classList.add('open');
            menuOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMenu() {
            sideMenu.classList.remove('open');
            menuOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        burgerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (sideMenu.classList.contains('open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        menuOverlay.addEventListener('click', closeMenu);
        document.querySelectorAll('.side-menu .nav-item').forEach(link => {
            link.addEventListener('click', closeMenu);
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeMenu();
        });

        // ==============================================
        // TOAST
        // ==============================================
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'exclamation-triangle';
            toast.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ==============================================
        // SCROLL TO BOTTOM
        // ==============================================
        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // ==============================================
        // LOAD MESSAGES
        // ==============================================
        function loadMessages(lastId = 0) {
            if (isFetching) return;
            isFetching = true;

            const formData = new FormData();
            formData.append('action', 'get_messages');
            formData.append('last_id', lastId);
            formData.append('csrf_token', csrfToken);

            fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        const emptyState = chatMessages.querySelector('.empty-chat');
                        if (emptyState) emptyState.remove();

                        const typing = document.getElementById('typingIndicator');
                        if (typing) typing.style.display = 'none';

                        data.messages.forEach(msg => {
                            const isMine = msg.is_mine === true;
                            const senderName = isMine ? 'You' : msg.sender_name;

                            const div = document.createElement('div');
                            div.className = `message ${isMine ? 'admin' : 'customer'}`;
                            div.setAttribute('data-id', msg.id);
                            div.innerHTML = `
                                <span class="msg-sender">${escapeHtml(senderName)}</span>
                                <div class="msg-text">${nl2br(escapeHtml(msg.message))}</div>
                                <span class="msg-time">
                                    ${escapeHtml(msg.formatted_time)}
                                    ${isMine ? '<span class="msg-status read"><i class="fas fa-check-circle"></i></span>' : ''}
                                </span>
                            `;
                            chatMessages.appendChild(div);
                        });

                        if (data.messages.length > 0) {
                            lastMessageId = data.messages[data.messages.length - 1].id;
                        }

                        setTimeout(scrollToBottom, 100);
                    }
                })
                .catch(error => {
                    console.error('Error loading messages:', error);
                })
                .finally(() => {
                    isFetching = false;
                });
        }

        // ==============================================
        // SEND MESSAGE
        // ==============================================
        function sendMessage() {
            const message = messageInput.value.trim();
            if (!message || isSending) return;

            isSending = true;
            sendBtn.disabled = true;
            messageInput.disabled = true;

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('message', message);
            formData.append('csrf_token', csrfToken);

            fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageInput.value = '';
                        const msg = data.message_data;

                        const emptyState = chatMessages.querySelector('.empty-chat');
                        if (emptyState) emptyState.remove();

                        const div = document.createElement('div');
                        div.className = 'message admin';
                        div.setAttribute('data-id', msg.id);
                        div.innerHTML = `
                            <span class="msg-sender">You</span>
                            <div class="msg-text">${nl2br(escapeHtml(msg.message))}</div>
                            <span class="msg-time">${escapeHtml(msg.formatted_time)} <span class="msg-status delivered"><i class="fas fa-check"></i></span></span>
                        `;
                        chatMessages.appendChild(div);
                        lastMessageId = msg.id;
                        scrollToBottom();
                    } else {
                        showToast(data.message || 'Failed to send message', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                    showToast('Network error. Please try again.', 'error');
                })
                .finally(() => {
                    isSending = false;
                    sendBtn.disabled = false;
                    messageInput.disabled = false;
                    messageInput.focus();
                });
        }

        // ==============================================
        // MARK AS READ
        // ==============================================
        function markAsRead() {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('csrf_token', csrfToken);

            fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .catch(error => console.error('Error marking messages as read:', error));
        }

        // ==============================================
        // HELPER FUNCTIONS
        // ==============================================
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function nl2br(text) {
            return text.replace(/\n/g, '<br>');
        }

        // ==============================================
        // AUTO-REFRESH
        // ==============================================
        function autoRefresh() {
            if (!document.hidden) {
                loadMessages(lastMessageId);
            }
        }

        // ==============================================
        // EVENT LISTENERS
        // ==============================================
        sendBtn.addEventListener('click', sendMessage);

        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        setTimeout(() => messageInput.focus(), 500);

        // ==============================================
        // INITIAL LOAD
        // ==============================================
        const existingMessages = chatMessages.querySelectorAll('.message');
        if (existingMessages.length > 0) {
            const lastMsg = existingMessages[existingMessages.length - 1];
            lastMessageId = parseInt(lastMsg.getAttribute('data-id')) || 0;
        }
        scrollToBottom();
        markAsRead();
        setInterval(autoRefresh, 5000);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                loadMessages(lastMessageId);
                markAsRead();
            }
        });

        typingIndicator.style.display = 'none';

        console.log('💬 Chat view loaded for: ' + targetAcc);
        console.log('👤 Current user: ' + myAcc);
        console.log('📨 Total messages: ' + existingMessages.length);
    </script>
</body>

</html>