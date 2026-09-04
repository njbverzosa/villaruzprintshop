<?php
// web/prompt_ai.php - Type text to do update database

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

// Get current page for sidebar
$currentPage = basename($_SERVER['PHP_SELF']);
$authorizeAccess = isset($userData['authorize_access']) ? (int) $userData['authorize_access'] : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>SQL Executer | Jo Seph AI</title>
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

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: #ffffff;
            padding: 20px 30px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .welcome h4 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .welcome h4 i {
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
            background: linear-gradient(135deg, #0f172a, #1e293b);
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

        .chat-header-inner .header-left .header-icon {
            font-size: 32px;
            color: #3b82f6;
        }

        .chat-header-inner .header-left .header-icon i {
            background: rgba(59, 130, 246, 0.15);
            padding: 8px 10px;
            border-radius: 10px;
        }

        .chat-header-inner .header-left .user-info {
            display: flex;
            flex-direction: column;
        }

        .chat-header-inner .header-left .user-info .user-name {
            font-weight: 700;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-header-inner .header-left .user-info .user-name .ai-badge {
            font-size: 10px;
            background: rgba(59, 130, 246, 0.3);
            padding: 2px 12px;
            border-radius: 12px;
            font-weight: 500;
            letter-spacing: 0.5px;
            color: #60a5fa;
        }

        .chat-header-inner .header-left .user-info .user-acc {
            font-size: 12px;
            opacity: 0.7;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .chat-header-inner .header-left .user-info .user-acc i {
            font-size: 10px;
        }

        .chat-header-inner .header-right .status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            background: rgba(255, 255, 255, 0.08);
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
            scrollbar-width: none;
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

        .message .msg-text code {
            background: rgba(0, 0, 0, 0.08);
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
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
            font-family: 'Courier New', monospace;
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

        .chat-input-area input::placeholder {
            font-family: 'Poppins', sans-serif;
        }

        .chat-input-area .send-btn {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Courier New', monospace;
        }

        .chat-input-area .send-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #1e293b, #2d3748);
            transform: scale(1.02);
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.3);
        }

        .chat-input-area .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .chat-input-area .send-btn i {
            font-size: 18px;
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

            .welcome h4 {
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
                font-size: 15px;
            }

            .chat-header-inner .header-left .user-info .user-acc {
                font-size: 11px;
            }

            .chat-header-inner .header-left .header-icon {
                font-size: 28px;
            }

            .chat-header-inner .header-left .header-icon i {
                padding: 6px 8px;
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
                font-size: 13px;
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

            .welcome h4 {
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

            .chat-header-inner .header-left .user-info .user-name {
                font-size: 13px;
            }

            .chat-header-inner .header-left .user-info .user-acc {
                font-size: 10px;
            }

            .chat-header-inner .header-left .header-icon {
                font-size: 22px;
            }

            .chat-header-inner .header-left .header-icon i {
                padding: 4px 6px;
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
                padding: 8px 10px;
                border-radius: 0 0 10px 10px;
            }

            .chat-input-area input {
                font-size: 12px;
                padding: 8px 12px;
            }

            .chat-input-area .send-btn {
                padding: 8px 12px;
                font-size: 12px;
            }

            .chat-input-area .send-btn i {
                font-size: 14px;
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
                <?php
                include 'sidebar.php';
                ?>
            </div>
        </div>

        <main class="main-content">
            <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">
            <input type="hidden" id="myAcc" value="<?php echo htmlspecialchars($accNumber); ?>">

            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <!-- Burger Button (Mobile Only) -->
                    <button class="burger-btn" id="burgerBtn" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="welcome">
                        <h4><i class="fas fa-code"></i> SQL Executer</h4>
                    </div>
                </div>
            </div>

            <!-- Chat Container -->
            <div class="chat-container">

                <!-- Chat Header INSIDE the container -->
                <div class="chat-header-inner">
                    <div class="header-left">
                        <div class="header-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="user-info">
                            <span class="user-name">
                                Jo Seph AI
                                <span class="ai-badge"><i class="fas fa-brain"></i> AI</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Chat Messages -->
                <div class="chat-messages" id="chatMessages">
                    <div class="empty-chat">
                        <i class="fas fa-code"></i>
                        <h4>No command yet</h4>
                        <p>Type a SQL command or prompt to update database</p>
                    </div>
                    <div class="typing-indicator" id="typingIndicator">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>

                <!-- Message Input -->
                <div class="chat-input-area">
                    <input type="text" id="messageInput" placeholder="Oh good. Type your SQL command..."
                        autocomplete="off">
                    <button class="send-btn" id="sendBtn">
                        <i class="fas fa-play"></i>
                        <span class="send-text">Execute</span>
                    </button>
                </div>
            </div>
        </main>
    </div>

    <?php
    include '../footer.php';
    ?>

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
                if (isSidebarOpen) {
                    closeSidebar();
                }
                sidebarWrapper.classList.remove('open');
                menuOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // ========== CSRF TOKEN ==========
        const csrfToken = document.getElementById('csrfToken').value;

        const chatMessages = document.getElementById('chatMessages');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const typingIndicator = document.getElementById('typingIndicator');

        let isSending = false;
        let shouldAutoScroll = true;

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
        // ADD MESSAGE TO CHAT (UI ONLY)
        // ==============================================
        function addMessageToChat(message, isMine = true, formattedTime = null) {
            const emptyState = chatMessages.querySelector('.empty-chat');
            if (emptyState) emptyState.remove();

            const time = formattedTime || new Date().toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            const div = document.createElement('div');
            div.className = `message ${isMine ? 'admin' : 'customer'}`;
            div.innerHTML = `
                <span class="msg-sender">${isMine ? 'You' : 'Jo Seph AI'}</span>
                <div class="msg-text">${escapeHtml(message)}</div>
                <span class="msg-time">
                    ${escapeHtml(time)}
                    ${isMine ? '<span class="msg-status delivered"><i class="fas fa-check"></i></span>' : ''}
                </span>
            `;
            chatMessages.appendChild(div);
            scrollToBottom();
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
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Thinking...';

            // Show typing indicator
            typingIndicator.style.display = 'block';
            scrollToBottom();

            const formData = new FormData();
            formData.append('action', 'process');
            formData.append('prompt', message);
            formData.append('csrf_token', csrfToken);

            fetch('../API/prompter.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    // Hide typing indicator
                    typingIndicator.style.display = 'none';

                    // Add the user message to chat
                    const now = new Date();
                    const formattedTime = now.toLocaleString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    addMessageToChat(message, true, formattedTime);

                    // Clear input
                    if (!messageText) {
                        messageInput.value = '';
                    }

                    // Add AI response
                    const responseMessage = data.success ? '🤖 ' + data.message : '❌ ' + data.message;
                    addMessageToChat(responseMessage, false, formattedTime);

                    if (data.success) {
                        showToast('✅ Command executed successfully!', 'success');
                    } else {
                        showToast('❌ Command failed. Try again.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    typingIndicator.style.display = 'none';
                    showToast('⚠️ Network error. Please try again.', 'error');
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
        // HELPER FUNCTIONS
        // ==============================================
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

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
        scrollToBottom();

        console.log('📱 Sidebar menu loaded - Left Side');
        console.log('📐 Desktop: Sidebar expanded | Mobile: Burger menu');
        console.log('🤖 Jo Seph AI - SQL Executer loaded');
        console.log('💡 Type a SQL command to execute');
    </script>
</body>

</html>