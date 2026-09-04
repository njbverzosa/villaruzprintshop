<?php
// web/chat_view.php - Chat View (Admin View) - REAL-TIME

session_start();

// ==============================================
// 1. FIX PATHS - config.php is in DB_Conn folder at root level
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// STORE USER NAME IN SESSION FOR API USE
// ==============================================
if (isset($userData['f_name']) && !isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = $userData['f_name'];
}

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
}

if (!$userData) {
    // User not found in database, logout
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 4. USE $userData INSTEAD OF $user
// ==============================================
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
    // GET MESSAGES - REAL-TIME
    // ==============================================
    if ($action === 'get_messages') {
        $lastId = intval($_POST['last_id'] ?? 0);

        // Get only new messages since last ID
        $stmt = $pdo->prepare("
            SELECT cc.*, 
                   DATE_FORMAT(cc.created_at, '%b %d, %Y %h:%i %p') as formatted_time
            FROM chat_conversation cc
            WHERE (cc.acc_number = ? OR cc.receiver_acc = ?)
              AND cc.id > ?
            ORDER BY cc.created_at ASC
        ");
        $stmt->execute([$targetAcc, $targetAcc, $lastId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark messages as read if there are new ones
        if (!empty($messages)) {
            // Mark messages sent by customer to admin as read
            $stmt = $pdo->prepare("
                UPDATE chat_conversation 
                SET status = 1 
                WHERE acc_number = ? AND receiver_acc = ? AND status = 0
            ");
            $stmt->execute([$targetAcc, $accNumber]);

            // Mark messages sent by admin to customer as read
            $stmt2 = $pdo->prepare("
                UPDATE chat_conversation 
                SET status = 1 
                WHERE acc_number = ? AND receiver_acc = ? AND status = 0
            ");
            $stmt2->execute([$accNumber, $targetAcc]);
        }

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
            'target_acc' => $targetAcc,
            'last_id' => !empty($messages) ? $messages[count($messages) - 1]['id'] : $lastId
        ]);
        exit();
    }

    // ==============================================
    // SEND MESSAGE
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
    // CHECK FOR NEW MESSAGES (LONG POLLING)
    // ==============================================
    if ($action === 'check_new') {
        $lastId = intval($_POST['last_id'] ?? 0);

        // Check if there are new messages
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as new_count
            FROM chat_conversation 
            WHERE (acc_number = ? OR receiver_acc = ?)
              AND id > ?
        ");
        $stmt->execute([$targetAcc, $targetAcc, $lastId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'has_new' => ($result['new_count'] ?? 0) > 0,
            'count' => $result['new_count'] ?? 0
        ]);
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

// Get the highest message ID for tracking
$maxId = 0;
if (!empty($initialMessages)) {
    $maxId = $initialMessages[count($initialMessages) - 1]['id'];
}

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

// ==============================================
// 7. SUGGESTED PROMPTS
// ==============================================
$suggestedPrompts = [
    "Hello! How can I help you today?",
    "Thank you for your order!",
    "Your order is being processed.",
    "We've received your payment.",
    "Your order is ready for deliver.",
    "We'll update you on the status.",
    "Do you have any questions?",
    "We appreciate your business!",
    "Hello good morning! How can I help you today?",
    "Hello good afternoon! How can I help you today?"
];

// Get current page for sidebar
$currentPage = basename($_SERVER['PHP_SELF']);
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

        /* ========== SIDEBAR - LEFT SIDE ========== */
        .sidebar-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            z-index: 1000;
            transition: transform 0.3s ease;
            transform: translateX(0);
        }

        .side-menu {
            width: 280px;
            height: 100vh;
            background: #ffffff;
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e2e8f0;
            overflow-y: auto;
            position: relative;
        }

        /* Mobile: sidebar hidden by default */
        @media (max-width: 768px) {
            .sidebar-wrapper {
                transform: translateX(-100%);
            }

            .sidebar-wrapper.open {
                transform: translateX(0);
            }
        }

        /* Desktop: sidebar always visible */
        @media (min-width: 769px) {
            .sidebar-wrapper {
                transform: translateX(0) !important;
            }

            .main-content {
                margin-left: 280px;
                padding: 20px;
            }

            .burger-btn {
                display: none !important;
            }

            .menu-overlay {
                display: none !important;
            }

            .sidebar-close-btn {
                display: none !important;
            }
        }

        /* Mobile overlay */
        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
            z-index: 999;
            display: none;
        }

        .menu-overlay.active {
            display: block;
        }

        /* ========== BURGER BUTTON (Mobile Only) - In Header ========== */
        .burger-btn {
            background: none;
            border: none;
            color: #3b82f6;
            font-size: 24px;
            cursor: pointer;
            padding: 5px 10px;
            display: none;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .burger-btn:hover {
            color: #2563eb;
            transform: scale(1.05);
        }

        .burger-btn i {
            font-size: 24px;
        }

        @media (max-width: 768px) {
            .burger-btn {
                display: flex;
            }
        }

        /* ========== SIDEBAR CLOSE BUTTON (Mobile Only) ========== */
        .sidebar-close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: #64748b;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s;
            display: none;
            z-index: 10;
        }

        .sidebar-close-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        @media (max-width: 768px) {
            .sidebar-close-btn {
                display: block;
            }
        }

        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
                margin-left: 0 !important;
                padding-top: 10px;
            }
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

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
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

        .menu-header {
            padding: 25px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            flex-shrink: 0;
            padding-right: 50px;
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
            overflow-y: auto;
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

        .menu-nav .nav-item.shop {
            background: #eff6ff;
            color: #3b82f6;
            border-left: 3px solid #3b82f6;
        }

        /* ========== DROPDOWN STYLES ========== */
        .nav-dropdown {
            margin-bottom: 8px;
        }

        .nav-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 12px;
            border-radius: 14px;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .nav-dropdown-toggle:hover {
            background: #eff6ff;
            color: #1e293b;
        }

        .nav-dropdown-toggle i:first-child {
            width: 24px;
            font-size: 20px;
            color: #3b82f6;
        }

        .nav-dropdown-toggle span {
            flex: 1;
            font-size: 15px;
            font-weight: 500;
        }

        .dropdown-arrow {
            font-size: 12px !important;
            transition: transform 0.3s ease;
            width: auto !important;
        }

        .dropdown-arrow.rotated {
            transform: rotate(180deg);
        }

        .nav-dropdown-menu {
            display: none;
            margin-left: 35px;
            margin-top: 5px;
            margin-bottom: 5px;
            border-left: 2px solid #e2e8f0;
        }

        .nav-dropdown-menu.show {
            display: block;
        }

        .nav-dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 14px;
        }

        .nav-dropdown-item i {
            width: 20px;
            font-size: 14px;
            color: #3b82f6;
        }

        .nav-dropdown-item span {
            font-size: 13px;
            font-weight: 500;
        }

        .nav-dropdown-item:hover {
            background: #eff6ff;
            color: #1e293b;
        }

        .nav-dropdown-item.active_paid {
            background: #eff6ff;
            color: green;
            border-left: 3px solid green;
        }

        .nav-dropdown-item.active_pending {
            background: #eff6ff;
            color: orange;
            border-left: 3px solid orange;
        }

        .nav-dropdown-item.active_outside {
            background: #eff6ff;
            color: #3b82f6;
            border-left: 3px solid #3b82f6;
        }

        .nav-dropdown-item.active_credit {
            background: #eff6ff;
            color: red;
            border-left: 3px solid red;
        }

        .nav-dropdown-item.shop {
            background: #eff6ff;
            color: #3b82f6;
            border-left: 3px solid #3b82f6;
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

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.4;
            }
        }

        .chat-header-inner .header-right .status .online-text {
            font-weight: 500;
        }

        /* ========== CHAT MESSAGES - HIDDEN SCROLLBAR ========== */
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #fafbfc;
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-height: 300px;
            scroll-behavior: smooth;
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .chat-messages::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar for IE, Edge and Firefox */
        .chat-messages {
            -ms-overflow-style: none;
            /* IE and Edge */
            scrollbar-width: none;
            /* Firefox */
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

        /* ========== SUGGESTED PROMPTS ========== */
        .suggested-prompts {
            padding: 10px 15px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            flex-shrink: 0;
            max-height: 80px;
            overflow-x: auto;
            align-items: center;
        }

        .suggested-prompts .prompt-label {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 500;
            margin-right: 4px;
            white-space: nowrap;
        }

        .suggested-prompts .prompt-btn {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 12px;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .suggested-prompts .prompt-btn:hover {
            background: #eff6ff;
            border-color: #3b82f6;
            color: #3b82f6;
            transform: scale(1.02);
        }

        .suggested-prompts .prompt-btn:active {
            transform: scale(0.95);
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

            0%,
            60%,
            100% {
                transform: translateY(0);
                opacity: 0.4;
            }

            30% {
                transform: translateY(-6px);
                opacity: 1;
            }
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
                padding-top: 10px;
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

            .suggested-prompts {
                padding: 8px 12px;
                max-height: 70px;
                gap: 6px;
            }

            .suggested-prompts .prompt-btn {
                font-size: 11px;
                padding: 4px 10px;
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
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 8px;
                padding-top: 8px;
            }

            .dashboard-header {
                padding: 10px 12px;
            }

            .dashboard-header .welcome h4 {
                font-size: 13px;
            }

            .chat-container {
                border-radius: 10px;
                height: calc(100vh - 80px);
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

            .suggested-prompts {
                padding: 6px 10px;
                max-height: 60px;
                gap: 4px;
            }

            .suggested-prompts .prompt-label {
                font-size: 10px;
            }

            .suggested-prompts .prompt-btn {
                font-size: 10px;
                padding: 3px 8px;
            }

            .chat-input-area {
                padding: 8px 10px;
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
    </style>
</head>

<body>
    <div class="app-wrapper">
        <!-- Overlay (Mobile Only) -->
        <div class="menu-overlay" id="menuOverlay"></div>

        <!-- Sidebar Wrapper -->
        <div class="sidebar-wrapper" id="sidebarWrapper">
            <div class="side-menu" id="sideMenu">
                <!-- Close Button (Mobile Only) -->
                <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close sidebar">
                    <i class="fas fa-arrow-left"></i>
                </button>

                <div class="menu-header">
                    <i class="fas fa-store"></i>
                    <div class="user-greeting">Logged in as</div>
                    <div class="user-name">
                        <?php
                        echo htmlspecialchars($user['user_name'] ?? 'User');
                        ?>
                    </div>
                    <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                        <?php echo htmlspecialchars($user['acc_number'] ?? ''); ?>
                    </div>
                </div>
                <?php
                include 'sidebar.php';
                ?>
            </div>
        </div>

        <main class="main-content">
            <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">
            <input type="hidden" id="targetAcc" value="<?php echo htmlspecialchars($targetAcc); ?>">
            <input type="hidden" id="myAcc" value="<?php echo htmlspecialchars($accNumber); ?>">
            <input type="hidden" id="lastMessageId" value="<?php echo $maxId; ?>">

            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <!-- Burger Button (Mobile Only) -->
                    <button class="burger-btn" id="burgerBtn" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="welcome">
                        <h4><i class="fas fa-comment-dots"></i> Live Chat Centre</h4>
                    </div>
                </div>
            </div>

            <!-- Chat Container -->
            <div class="chat-container">

                <!-- Chat Header INSIDE the container -->
                <div class="chat-header-inner">
                    <div class="header-left">
                        <button class="back-btn" onclick="window.location.href='registered_customers.php'"
                            title="Back to conversations">
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
                            $isMine = ($msg['acc_number'] == $accNumber);
                            ?>
                            <div class="message <?php echo $isMine ? 'admin' : 'customer'; ?>"
                                data-id="<?php echo $msg['id']; ?>">
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

                <!-- Suggested Prompts -->
                <div class="suggested-prompts" id="suggestedPrompts">
                    <span class="prompt-label"><i class="fas fa-lightbulb"></i> Quick replies:</span>
                    <?php foreach ($suggestedPrompts as $prompt): ?>
                        <button class="prompt-btn" data-prompt="<?php echo htmlspecialchars($prompt); ?>">
                            <?php echo htmlspecialchars($prompt); ?>
                        </button>
                    <?php endforeach; ?>
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
        // ========== SIDEBAR TOGGLE (Mobile Only) ==========
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
        const sidebarWrapper = document.getElementById('sidebarWrapper');
        const menuOverlay = document.getElementById('menuOverlay');
        let isSidebarOpen = false;

        function openSidebar() {
            sidebarWrapper.classList.add('open');
            menuOverlay.classList.add('active');
            isSidebarOpen = true;
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebarWrapper.classList.remove('open');
            menuOverlay.classList.remove('active');
            isSidebarOpen = false;
            document.body.style.overflow = '';
        }

        function toggleSidebar() {
            if (isSidebarOpen) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        if (burgerBtn) {
            burgerBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                toggleSidebar();
            });
        }

        if (sidebarCloseBtn) {
            sidebarCloseBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                closeSidebar();
            });
        }

        if (menuOverlay) {
            menuOverlay.addEventListener('click', closeSidebar);
        }

        // Close sidebar when clicking a nav link (mobile only)
        document.querySelectorAll('.side-menu .nav-item, .side-menu .nav-dropdown-item').forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 768) {
                    // Don't close if it's a dropdown toggle
                    if (!this.closest('.nav-dropdown-toggle')) {
                        closeSidebar();
                    }
                }
            });
        });

        // ========== DROPDOWN TOGGLE ==========
        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            const arrowId = dropdownId.replace('Dropdown', 'Arrow');
            const arrow = document.getElementById(arrowId);

            if (dropdown && arrow) {
                dropdown.classList.toggle('show');
                arrow.classList.toggle('rotated');
            }
        }

        // ========== BURGER VISIBILITY ON RESIZE ==========
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                // Desktop: close sidebar if open and hide overlay
                if (isSidebarOpen) {
                    closeSidebar();
                }
                sidebarWrapper.classList.remove('open');
                menuOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // ==============================================
        // CSRF TOKEN
        // ==============================================
        const csrfToken = document.getElementById('csrfToken').value;
        const targetAcc = document.getElementById('targetAcc').value;
        const myAcc = document.getElementById('myAcc').value;
        let lastMessageId = parseInt(document.getElementById('lastMessageId').value) || 0;

        const chatMessages = document.getElementById('chatMessages');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const typingIndicator = document.getElementById('typingIndicator');
        const suggestedPrompts = document.getElementById('suggestedPrompts');

        let isFetching = false;
        let isSending = false;
        let isUserScrolling = false;
        let shouldAutoScroll = true;

        // ==============================================
        // SCROLL TO BOTTOM
        // ==============================================
        function scrollToBottom() {
            if (shouldAutoScroll) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        // ==============================================
        // SCROLL DETECTION
        // ==============================================
        chatMessages.addEventListener('scroll', function () {
            const atBottom = this.scrollHeight - this.scrollTop - this.clientHeight < 10;
            if (atBottom) {
                shouldAutoScroll = true;
            } else {
                shouldAutoScroll = false;
            }
        });

        // ==============================================
        // CHECK FOR NEW MESSAGES (EVERY 1 SECOND)
        // ==============================================
        function checkForNewMessages() {
            if (isFetching) return;

            const formData = new FormData();
            formData.append('action', 'get_messages');
            formData.append('last_id', lastMessageId);
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
                    if (data.success && data.messages && data.messages.length > 0) {
                        const emptyState = chatMessages.querySelector('.empty-chat');
                        if (emptyState) emptyState.remove();

                        // Add each new message
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

                            // Update last message ID
                            if (msg.id > lastMessageId) {
                                lastMessageId = msg.id;
                            }
                        });

                        // Update the hidden input with the latest ID
                        document.getElementById('lastMessageId').value = lastMessageId;

                        // Auto-scroll if user is near bottom
                        scrollToBottom();
                    }
                })
                .catch(error => {
                    // Silently fail - no user notification for polling errors
                    console.debug('Polling error:', error);
                })
                .finally(() => {
                    isFetching = false;
                });
        }

        // ==============================================
        // SEND MESSAGE
        // ==============================================
        function sendMessage(messageText) {
            const message = messageText || messageInput.value.trim();
            if (!message || isSending) return;

            isSending = true;
            sendBtn.disabled = true;
            messageInput.disabled = true;
            const originalBtnHTML = sendBtn.innerHTML;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

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
                        // Clear input if it was from the input field
                        if (!messageText) {
                            messageInput.value = '';
                        }

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

                        if (msg.id > lastMessageId) {
                            lastMessageId = msg.id;
                        }
                        document.getElementById('lastMessageId').value = lastMessageId;

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
                    sendBtn.innerHTML = originalBtnHTML;
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
                .catch(error => console.debug('Error marking messages as read:', error));
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
        // SUGGESTED PROMPTS - AUTO SEND ON CLICK
        // ==============================================
        document.querySelectorAll('.prompt-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const prompt = this.getAttribute('data-prompt');
                if (prompt) {
                    // Send the prompt immediately
                    sendMessage(prompt);
                }
            });
        });

        // ==============================================
        // EVENT LISTENERS
        // ==============================================
        sendBtn.addEventListener('click', function () {
            sendMessage();
        });

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
            const lastId = parseInt(lastMsg.getAttribute('data-id'));
            if (lastId > lastMessageId) {
                lastMessageId = lastId;
            }
        }
        document.getElementById('lastMessageId').value = lastMessageId;

        scrollToBottom();
        markAsRead();

        // ==============================================
        // REAL-TIME POLLING - EVERY 1 SECOND
        // ==============================================
        // Initial check after 500ms
        setTimeout(() => {
            checkForNewMessages();
        }, 500);

        // Then check every 1 second (1000ms)
        setInterval(() => {
            checkForNewMessages();
        }, 1000);

        // Also check when tab becomes visible again
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                checkForNewMessages();
                markAsRead();
            }
        });

        typingIndicator.style.display = 'none';

        console.log('📱 Sidebar menu loaded - Left Side');
        console.log('📐 Desktop: Sidebar expanded | Mobile: Burger menu');
        console.log('💬 Real-time Chat loaded');
        console.log('👤 Target: ' + targetAcc);
        console.log('📨 Last message ID: ' + lastMessageId);
        console.log('🔄 Polling every 1 second');
        console.log('💡 Quick reply prompts available - click to auto-send');
    </script>
</body>

</html>