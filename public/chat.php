<?php
// public/chat.php - Customer Chat View (No Toasts)

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
    $_SESSION['login_error'] = 'Please login first to access the chat.';
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 3. GET USER DATA FROM SESSION
// ==============================================
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

// Fetch user details from database - ADDED vip column
$userData = null;
if ($userRole === 'Customer') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, vip FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$user = $userData;

// ==============================================
// 4. UPDATE ONLINE TIME AFTER USER IS DEFINED
// ==============================================
date_default_timezone_set('Asia/Manila');
$currentTime = date('g:i A'); // e.g., 2:30 PM

if ($userRole === 'Customer') {
    $updateStmt = $pdo->prepare("UPDATE customers SET online_time = ? WHERE id = ?");
    $updateStmt->execute([$currentTime, $userData['id']]);
} 


// Get cart count for bottom nav
$cartCountStmt = $pdo->prepare("SELECT SUM(pieces) as total_items FROM cart WHERE acc_number = ?");
$cartCountStmt->execute([$accNumber]);
$cartCountResult = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
$cartTotalItems = intval($cartCountResult['total_items'] ?? 0);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Check if user is VIP
$isVip = isset($user['vip']) && $user['vip'] == 1;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Live Chat | Villaruz Print Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== RESET & BASE STYLES ========== */
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
            padding-bottom: 70px;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            padding: 20px 20px 30px;
            overflow-y: auto;
            background: #f1f5f9;
        }

        /* ========== DASHBOARD HEADER ========== */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: #ffffff;
            padding: 18px 25px;
            border-radius: 2px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .welcome h3 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }

        .welcome h3 i {
            color: #3b82f6;
            margin-right: 8px;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f1f5f9;
            padding: 6px 14px 6px 10px;
            border-radius: 5px;
        }

        .user-badge .avatar {
            width: 32px;
            height: 32px;
            border-radius: 20px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }

        .user-badge .name {
            font-size: 13px;
            font-weight: 500;
            color: #0f172a;
        }

        /* ========== CHAT CONTAINER ========== */
        .chat-container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 600px;
            max-height: calc(100vh - 250px);
        }

        /* Chat Header */
        .chat-header-custom {
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: white;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .chat-header-custom .chat-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-header-custom .chat-title i {
            font-size: 28px;
        }

        .chat-header-custom .chat-title .ai-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .chat-header-custom .chat-title div {
            display: flex;
            flex-direction: column;
        }

        .chat-header-custom .chat-title span {
            font-weight: 600;
            font-size: 18px;
        }

        .chat-header-custom .chat-title .ai-badge {
            font-size: 10px;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 500;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-left: 6px;
        }

        .chat-header-custom .chat-title small {
            font-size: 12px;
            opacity: 0.8;
        }

        .chat-header-custom .chat-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .chat-header-custom .chat-status .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #4ade80;
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

        /* Chat Messages Area - HIDDEN SCROLLBAR */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-height: 200px;
            scroll-behavior: smooth;
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .chat-messages::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar for IE, Edge and Firefox */
        .chat-messages {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .chat-messages .message {
            max-width: 75%;
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
            animation: fadeIn 0.3s ease;
        }

        .chat-messages .message.sent {
            background: #3b82f6;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .chat-messages .message.received {
            background: #ffffff;
            color: #1e293b;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .chat-messages .message .time {
            font-size: 10px;
            opacity: 0.7;
            margin-top: 4px;
            display: block;
        }

        .chat-messages .message.sent .time {
            color: rgba(255, 255, 255, 0.8);
        }

        .chat-messages .message.received .time {
            color: #94a3b8;
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

        /* Typing Indicator */
        .typing-indicator {
            display: none;
            align-self: flex-start;
            padding: 10px 16px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            gap: 4px;
            align-items: center;
            margin-top: 4px;
        }

        .typing-indicator.show {
            display: flex;
        }

        .typing-indicator .dot {
            width: 8px;
            height: 8px;
            background: #94a3b8;
            border-radius: 50%;
            animation: typingDot 1.4s infinite;
        }

        .typing-indicator .dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator .dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typingDot {

            0%,
            60%,
            100% {
                transform: translateY(0);
                opacity: 0.4;
            }

            30% {
                transform: translateY(-8px);
                opacity: 1;
            }
        }

        /* Chat Empty State */
        .chat-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            text-align: center;
            padding: 40px;
            height: 100%;
            min-height: 200px;
        }

        .chat-empty i {
            font-size: 64px;
            margin-bottom: 16px;
            color: #cbd5e1;
        }

        .chat-empty h4 {
            font-size: 20px;
            color: #475569;
            margin-bottom: 4px;
        }

        .chat-empty p {
            font-size: 14px;
        }

        /* Chat Input Area */
        .chat-input-area {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            background: #ffffff;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-shrink: 0;
        }

        .chat-input-area input {
            flex: 1;
            padding: 12px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
            background: #f8fafc;
        }

        .chat-input-area input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: #ffffff;
        }

        .chat-input-area input:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
        }

        .chat-input-area .send-btn {
            padding: 12px 28px;
            border-radius: 24px;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .chat-input-area .send-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .chat-input-area .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* ========== BOTTOM NAVIGATION ========== */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 0 12px;
            z-index: 1000;
            box-shadow: 0 -2px 15px rgba(0, 0, 0, 0.06);
            height: 65px;
        }

        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            color: #525f70;
            text-decoration: none;
            transition: all 0.3s ease;
            padding: 4px 16px;
            position: relative;
            min-width: 56px;
        }

        .bottom-nav .nav-item i {
            font-size: 25px;
            transition: all 0.3s ease;
        }

        .bottom-nav .nav-item span {
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
        }

        .bottom-nav .nav-item:hover {
            color: #3b82f6;
        }

        .bottom-nav .nav-item.active {
            color: #3b82f6;
        }

        .bottom-nav .nav-item .badge {
            position: absolute;
            top: 0;
            right: 4px;
            background: lightgreen;
            color: #020e20;
            font-size: 14px;
            font-weight: bold;
            padding: 1px 6px;
            border-radius: 20px;
            min-width: 12px;
            text-align: center;
            line-height: 14px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px 15px 20px;
            }

            .dashboard-header {
                padding: 14px 18px;
                flex-direction: row;
                flex-wrap: wrap;
                gap: 8px;
            }

            .welcome h3 {
                font-size: 17px;
            }

            .user-badge .name {
                font-size: 12px;
            }

            .chat-container {
                height: 500px;
                max-height: calc(100vh - 200px);
                border-radius: 12px;
            }

            .chat-header-custom {
                padding: 14px 18px;
            }

            .chat-header-custom .chat-title span {
                font-size: 16px;
            }

            .chat-header-custom .chat-title .ai-avatar {
                width: 34px;
                height: 34px;
                font-size: 16px;
            }

            .chat-messages {
                padding: 14px 16px;
            }

            .chat-messages .message {
                max-width: 85%;
                font-size: 13px;
                padding: 8px 14px;
            }

            .chat-input-area {
                padding: 12px 16px;
            }

            .chat-input-area input {
                font-size: 13px;
                padding: 10px 14px;
            }

            .chat-input-area .send-btn {
                padding: 10px 20px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 12px 12px 16px;
            }

            body {
                padding-bottom: 60px;
            }

            .chat-container {
                height: 710px;
                max-height: calc(100vh - 180px);
                border-radius: 10px;
            }

            .chat-header-custom {
                padding: 12px 14px;
            }

            .chat-header-custom .chat-title i {
                font-size: 22px;
            }

            .chat-header-custom .chat-title .ai-avatar {
                width: 30px;
                height: 30px;
                font-size: 14px;
            }

            .chat-header-custom .chat-title span {
                font-size: 14px;
            }

            .chat-header-custom .chat-title .ai-badge {
                font-size: 8px;
                padding: 1px 8px;
            }

            .chat-header-custom .chat-title small {
                font-size: 10px;
            }

            .chat-messages {
                padding: 10px 12px;
            }

            .chat-messages .message {
                max-width: 90%;
                font-size: 12px;
                padding: 8px 12px;
            }

            .chat-input-area {
                padding: 10px 12px;
                gap: 8px;
            }

            .chat-input-area input {
                font-size: 12px;
                padding: 8px 12px;
            }

            .chat-input-area .send-btn {
                padding: 8px 16px;
                font-size: 12px;
            }

            .chat-input-area .send-btn i {
                font-size: 14px;
            }

            .chat-empty i {
                font-size: 48px;
            }

            .chat-empty h4 {
                font-size: 17px;
            }

            .dashboard-header {
                padding: 12px 14px;
                border-radius: 5px;
            }

            .welcome h3 {
                font-size: 15px;
            }

            .user-badge .avatar {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }

            .user-badge .name {
                font-size: 11px;
            }

            .bottom-nav {
                padding: 4px 0 8px;
                height: 56px;
            }

            .bottom-nav .nav-item {
                padding: 2px 6px;
                min-width: 36px;
            }

            .bottom-nav .nav-item i {
                font-size: 18px;
            }

            .bottom-nav .nav-item span {
                font-size: 9px;
            }

            .bottom-nav .nav-item .badge {
                font-size: 10px;
                min-width: 14px;
                line-height: 14px;
                top: -2px;
                right: 0px;
                padding: 0 5px;
            }
        }

        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .bottom-nav {
                padding-bottom: calc(12px + env(safe-area-inset-bottom));
            }
        }

        /* VIP Avatar Styles */
        .user-badge .avatar.vip {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            font-size: 12px;
            font-weight: 700;
        }

        .user-badge .vip-badge i {
            font-size: 10px;
        }
    </style>
</head>

<body>

    <!-- ========== MAIN CONTENT ========== -->
    <main class="main-content">
        <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">
        <input type="hidden" id="userId" value="<?php echo $userId; ?>">
        <input type="hidden" id="userRole" value="<?php echo $userRole; ?>">
        <input type="hidden" id="userAccNumber" value="<?php echo htmlspecialchars($accNumber); ?>">

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome">
                <h3><i class="fas fa-comment-dots"></i> Live Chat Support</h3>
            </div>
            <div class="user-badge">
                <div class="avatar <?php echo (isset($user['vip']) && $user['vip'] == 1) ? 'vip' : ''; ?>">
                    <?php
                    $isVip = isset($user['vip']) && $user['vip'] == 1;

                    if ($isVip):
                        ?>
                        <i class="fas fa-crown"></i>
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['f_name'] ?? 'G', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <span class="name"><?php echo htmlspecialchars($user['f_name'] ?? 'Guest'); ?></span>
            </div>
        </div>

        <!-- Chat Container -->
        <div class="chat-container">

            <!-- Chat Header -->
            <div class="chat-header-custom">
                <div class="chat-title">
                    <div class="ai-avatar">
                        <img src="logo/avatar.png" alt="" style="width: 50px; border-radius: 100px;height: 50px;">
                    </div>
                    <div>
                        <span>
                            Jo Seph
                            <span class="ai-badge"> AI Assistant</span>
                        </span>
                        <small>24/7 Support</small>
                    </div>
                </div>
                <div class="chat-status">
                    <span class="dot"></span>
                    <span>Online</span>
                </div>
            </div>

            <!-- Chat Messages -->
            <div class="chat-messages" id="chatMessages">
                <div class="chat-empty">
                    <i class="fas fa-comment-alt"></i>
                    <h4>Hello! 👋</h4>
                    <p>How can we help you today?</p>
                    <p style="font-size: 12px; margin-top: 8px; color: #94a3b8;">Our support team is here to assist you.
                    </p>
                </div>
            </div>

            <!-- Typing Indicator -->
            <div class="typing-indicator" id="typingIndicator">
                <span class="dot"></span>
                <span class="dot"></span>
                <span class="dot"></span>
                <span style="font-size: 12px; color: #94a3b8; margin-left: 4px;">Support is typing...</span>
            </div>

            <!-- Chat Input -->
            <div class="chat-input-area">
                <input type="text" id="chatInput" placeholder="Type your message..." autocomplete="off">
                <button class="send-btn" id="sendBtn">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </div>
        </div>
    </main>

    <!-- ========== BOTTOM NAVIGATION ========== -->
    <nav class="bottom-nav">
        <a href="shop.php" class="nav-item">
            <i class="fas fa-store"></i>
            <span>Shop</span>
        </a>
        <a href="cart.php" class="nav-item" id="cartNavItem">
            <i class="fas fa-shopping-cart"></i>
            <span>Cart</span>
            <?php if ($cartTotalItems > 0): ?>
                <span class="badge" id="cartBadge"><?php echo $cartTotalItems; ?></span>
            <?php else: ?>
                <span class="badge" id="cartBadge" style="display: none;">0</span>
            <?php endif; ?>
        </a>
        <a href="orders.php" class="nav-item">
            <i class="fas fa-truck"></i>
            <span>Orders</span>
        </a>
        <a href="account.php" class="nav-item active">
            <i class="fas fa-th-large"></i>
            <span>Services</span>
        </a>
        <a href="closed.php" class="nav-item" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <script>
        // ==============================================
        // CHAT SYSTEM
        // ==============================================

        const csrfToken = document.getElementById('csrfToken').value;
        const userId = document.getElementById('userId').value;
        const userRole = document.getElementById('userRole').value;
        const userAccNumber = document.getElementById('userAccNumber').value;

        let chatInterval = null;
        let lastMessageId = 0;
        let isFirstLoad = true;
        let isBlocked = false;
        let isSending = false;

        // ==============================================
        // CHECK ACCOUNT STATUS
        // ==============================================
        function checkAccountStatus() {
            const formData = new FormData();
            formData.append('action', 'check_status');
            formData.append('csrf_token', csrfToken);

            fetch('../Customer_API/chat.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        isBlocked = data.is_blocked;
                        if (isBlocked) {
                            document.getElementById('chatInput').disabled = true;
                            document.getElementById('sendBtn').disabled = true;

                            const container = document.getElementById('chatMessages');
                            container.innerHTML = `
                        <div class="chat-empty">
                            <i class="fas fa-ban" style="color: #ef4444;"></i>
                            <h4 style="color: #ef4444;">Account Blocked</h4>
                            <p>You have been blocked from using the chat feature.</p>
                            <p style="font-size: 12px; margin-top: 8px; color: #94a3b8;">Please contact support for assistance.</p>
                        </div>
                    `;
                        }
                    }
                })
                .catch(error => console.error('Error checking status:', error));
        }

        // ==============================================
        // LOAD MESSAGES
        // ==============================================
        function loadMessages() {
            if (!userId || !userRole || isBlocked) return;

            const formData = new FormData();
            formData.append('action', 'get_messages');
            formData.append('csrf_token', csrfToken);

            fetch('../Customer_API/chat.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const messages = data.messages || [];
                        renderMessages(messages);
                        updateLastMessageId(messages);
                    } else if (data.blocked) {
                        isBlocked = true;
                        document.getElementById('chatInput').disabled = true;
                        document.getElementById('sendBtn').disabled = true;
                    }
                })
                .catch(error => {
                    console.error('❌ Error loading messages:', error);
                });
        }

        // ==============================================
        // UPDATE LAST MESSAGE ID
        // ==============================================
        function updateLastMessageId(messages) {
            if (messages.length > 0) {
                const lastMsg = messages[messages.length - 1];
                if (lastMsg && lastMsg.id) {
                    lastMessageId = lastMsg.id;
                }
            }
        }

        // ==============================================
        // RENDER MESSAGES
        // ==============================================
        function renderMessages(messages) {
            const container = document.getElementById('chatMessages');

            if (!messages || messages.length === 0) {
                if (!container.querySelector('.message')) {
                    container.innerHTML = `
                <div class="chat-empty">
                    <i class="fas fa-comment-alt"></i>
                    <h4>Hello! 👋</h4>
                    <p>How can we help you today?</p>
                    <p style="font-size: 12px; margin-top: 8px; color: #94a3b8;">Our support team is here to assist you.</p>
                </div>
            `;
                }
                return;
            }

            // Remove empty state
            const emptyState = container.querySelector('.chat-empty');
            if (emptyState) emptyState.remove();

            // Get existing message IDs
            const existingIds = new Set();
            container.querySelectorAll('.message').forEach(el => {
                const id = el.getAttribute('data-id');
                if (id) existingIds.add(id);
            });

            let hasNewMessages = false;

            messages.forEach((msg) => {
                const msgId = String(msg.id);

                // Skip if message already exists
                if (existingIds.has(msgId)) return;

                // Determine if sent or received based on acc_number
                const isSent = msg.acc_number === userAccNumber;

                const time = msg.time || (msg.created_at ? new Date(msg.created_at).toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                }) : '');

                const div = document.createElement('div');
                div.className = `message ${isSent ? 'sent' : 'received'}`;
                div.setAttribute('data-id', msgId);
                div.innerHTML = `
            ${escapeHtml(msg.message)}
            <span class="time">${time}</span>
        `;
                container.appendChild(div);
                hasNewMessages = true;
            });

            if (hasNewMessages || isFirstLoad) {
                isFirstLoad = false;
                scrollToBottom();
            }
        }

        // ==============================================
        // SCROLL TO BOTTOM
        // ==============================================
        function scrollToBottom() {
            const container = document.getElementById('chatMessages');
            if (container) {
                requestAnimationFrame(() => {
                    container.scrollTop = container.scrollHeight;
                });
            }
        }

        // ==============================================
        // SEND MESSAGE
        // ==============================================
        function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();

            if (!message) return;
            if (isBlocked) {
                return;
            }
            if (isSending) return;
            if (!userId || !userRole) {
                return;
            }

            isSending = true;
            input.disabled = true;
            document.getElementById('sendBtn').disabled = true;

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('message', message);
            formData.append('csrf_token', csrfToken);

            fetch('../Customer_API/chat.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        input.value = '';
                        isFirstLoad = true;
                        loadMessages();
                    } else if (data.blocked) {
                        isBlocked = true;
                        input.disabled = true;
                        document.getElementById('sendBtn').disabled = true;
                    }
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                })
                .finally(() => {
                    isSending = false;
                    input.disabled = false;
                    document.getElementById('sendBtn').disabled = false;
                    input.focus();
                });
        }

        // ==============================================
        // ESCAPE HTML
        // ==============================================
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ==============================================
        // EVENT LISTENERS
        // ==============================================
        document.getElementById('sendBtn').addEventListener('click', sendMessage);
        document.getElementById('chatInput').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });

        // ==============================================
        // CLEANUP
        // ==============================================
        window.addEventListener('beforeunload', function () {
            if (chatInterval) {
                clearInterval(chatInterval);
            }
        });

        // ==============================================
        // INITIALIZE CHAT
        // ==============================================
        function initChat() {
            if (userId && userRole) {
                checkAccountStatus();

                setTimeout(() => {
                    loadMessages();
                }, 500);

                chatInterval = setInterval(() => {
                    loadMessages();
                }, 3000);
            }
        }

        // Start the chat
        initChat();

        console.log('💬 Live Chat initialized');
        console.log('👤 User ID:', userId);
        console.log('👤 User Role:', userRole);
        console.log('📧 Account:', userAccNumber);
    </script>

</body>

</html>