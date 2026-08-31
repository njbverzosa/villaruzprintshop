<?php
// web/registered_customers.php
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

// Set timezone
date_default_timezone_set('Asia/Manila');
$currentDateTime = date('D, j M Y g:i A');

// Daily login bonus / update last login date
$storedDate = $user['last_login_date'] ?? '';
if ($storedDate !== $currentDateTime) {
    $updateStmt = $pdo->prepare("UPDATE admins SET last_login_date = ? WHERE acc_number = ?");
    $updateStmt->execute([$currentDateTime, $_SESSION['acc_number']]);

    // Refresh user data
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE acc_number = ?");
    $stmt->execute([$_SESSION['acc_number']]);
    $user = $stmt->fetch();
}

// Update status to online (1 = online, 0 = offline)
$stmt = $pdo->prepare("UPDATE admins SET status = 1 WHERE acc_number = ?");
$stmt->execute([$_SESSION['acc_number']]);

// Fetch all customers from customers table
$stmt = $pdo->prepare("SELECT * FROM customers ORDER BY id DESC");
$stmt->execute();
$customers = $stmt->fetchAll();

// Calculate statistics
$totalCustomers = count($customers);
$activeCustomers = 0;
$inactiveCustomers = 0;

foreach ($customers as $customer) {
    if (isset($customer['active_email']) && ($customer['active_email'] == 1 || $customer['active_email'] === null)) {
        $activeCustomers++;
    } else {
        $inactiveCustomers++;
    }
}

// ==============================================
// FUNCTION TO GET UNREAD MESSAGE COUNT - FIXED
// ==============================================
function getUnreadCount($pdo, $customerAccNumber)
{
    try {
        // For admin: count unread messages sent BY this customer (status = 0)
        // For customer: count unread messages sent TO this customer (status = 0)
        if ($_SESSION['user_role'] === 'Admin') {
            // Admin sees unread messages from this customer
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count 
                FROM chat_conversation 
                WHERE acc_number = ? 
                AND status = 0
            ");
            $stmt->execute([$customerAccNumber]);
        } else {
            // Customer sees unread messages sent to them
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count 
                FROM chat_conversation 
                WHERE receiver_acc = ? 
                AND status = 0
            ");
            $stmt->execute([$customerAccNumber]);
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['unread_count'] ?? 0);
    } catch (PDOException $e) {
        // If table doesn't exist, return 0
        error_log("Error getting unread count: " . $e->getMessage());
        return 0;
    }
}

// ==============================================
// FUNCTION TO DETERMINE ONLINE STATUS - UPDATED THRESHOLDS
// ==============================================
function getOnlineStatus($onlineTime)
{
    if (empty($onlineTime)) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'label' => 'Offline'];
    }

    // Parse the stored time (format: g:i A)
    $storedTimestamp = strtotime($onlineTime);
    if ($storedTimestamp === false) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'label' => 'Offline'];
    }

    $currentTimestamp = time();
    $diffSeconds = $currentTimestamp - $storedTimestamp;
    $diffMinutes = floor($diffSeconds / 60);

    if ($diffMinutes >= 1 && $diffMinutes <= 2) {
        return ['status' => 'online', 'class' => 'status-online', 'text' => '● Online', 'label' => 'Active'];
    } elseif ($diffMinutes >= 3 && $diffMinutes <= 5) {
        return ['status' => 'away', 'class' => 'status-away', 'text' => '● Away', 'label' => 'Away'];
    } elseif ($diffMinutes >= 6) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'label' => 'Offline'];
    } else {
        return ['status' => 'online', 'class' => 'status-online', 'text' => '● Online', 'label' => 'Active'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <meta http-equiv="refresh" content="10">
    <title>Registered Customers | Villaruz Print Shop & General Merchandise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
            padding: 30px;
            overflow-y: auto;
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

        .welcome h1 {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
        }

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

        .merchandise-section {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            overflow-x: auto;
            margin-top: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h5 {
            font-size: 22px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #0f172a;
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            white-space: nowrap;
        }

        .inventory-table th,
        .inventory-table td {
            padding: 15px 12px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .inventory-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            position: sticky;
            top: 0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .inventory-table tr:hover {
            background: #f8fafc;
        }

        .delete-btn {
            background: #ef4444;
            border: none;
            border-radius: 20px;
            padding: 6px 16px;
            color: #ffffff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
        }

        .delete-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        .lock-btn {
            background: #f59e0b;
            border: none;
            border-radius: 20px;
            padding: 6px 16px;
            color: #ffffff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
            margin-right: 4px;
        }

        .lock-btn:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }

        .unlock-btn {
            background: #10b981;
            border: none;
            border-radius: 20px;
            padding: 6px 16px;
            color: #ffffff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
            margin-right: 4px;
        }

        .unlock-btn:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            justify-content: center;
        }

        /* ========== ONLINE STATUS INDICATORS ========== */
        .status-online {
            color: #10b981;
            font-size: 24px;
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
            animation: pulse-green 2s infinite;
        }

        .status-away {
            color: #f59e0b;
            font-size: 24px;
            animation: pulse-away 1.5s infinite;
        }

        .status-offline {
            color: #94a3b8;
            font-size: 20px;
            opacity: 0.5;
        }

        .status-text {
            font-size: 11px;
            font-weight: 500;
            display: block;
            margin-top: 2px;
        }

        .status-text.online {
            color: #10b981;
        }

        .status-text.away {
            color: #f59e0b;
        }

        .status-text.offline {
            color: #94a3b8;
        }

        @keyframes pulse-green {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.6;
                transform: scale(1.2);
            }
        }

        @keyframes pulse-away {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.5;
                transform: scale(1.1);
            }
        }

        .status-header {
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-cell {
            text-align: center;
            min-width: 50px;
        }

        .status-dot-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* ========== EMAIL STATUS DOT ========== */
        .email-status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-left: 8px;
            vertical-align: middle;
            animation: pulse-dot 1.5s ease-in-out infinite;
        }

        .dot-active {
            background: #10b981;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.6);
        }

        .dot-inactive {
            background: #ef4444;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.6);
        }

        @keyframes pulse-dot {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.3);
                opacity: 0.7;
            }
        }

        /* ========== PASSWORD STYLES ========== */
        .password-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f8fafc;
            padding: 2px 8px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            justify-content: center;
        }

        .password-text {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #0f172a;
        }

        .password-placeholder {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #94a3b8;
            letter-spacing: 2px;
        }

        /* ========== COPY BUTTONS FOR PHONE AND EMAIL ========== */
        .copy-btn-phone {
            color: #3b82f6;
            font-size: 14px;
            margin-left: 4px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 4px;
            transition: all 0.2s ease;
            border-radius: 4px;
        }

        .copy-btn-phone:hover {
            background: #eff6ff;
            transform: scale(1.1);
        }

        .copy-btn-email {
            color: #8b5cf6;
            font-size: 14px;
            margin-left: 4px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 4px;
            transition: all 0.2s ease;
            border-radius: 4px;
        }

        .copy-btn-email:hover {
            background: #f3e8ff;
            transform: scale(1.1);
        }

        .copy-btn-phone.copied,
        .copy-btn-email.copied {
            color: #10b981;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {

            .copy-btn-phone,
            .copy-btn-email {
                font-size: 11px;
                padding: 1px 3px;
            }
        }

        .copy-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 6px;
            font-size: 14px;
            transition: all 0.2s ease;
            border-radius: 4px;
        }

        .copy-btn:hover {
            transform: scale(1.2);
            background: #e2e8f0;
        }

        .copy-btn-eye {
            color: #3b82f6;
        }

        .copy-btn-eye:hover {
            color: #2563eb;
        }

        .copy-btn-copy {
            color: #10b981;
        }

        .copy-btn-copy:hover {
            color: #059669;
        }

        /* ========== CHAT ICON WITH BADGE ========== */
        .chat-icon-wrapper {
            position: relative;
            display: inline-block;
        }

        .chat-icon {
            cursor: pointer;
            text-align: center;
            color: #3b82f6;
            transition: all 0.3s;
            display: inline-block;
            padding: 8px;
            border-radius: 50%;
        }

        .chat-icon:hover {
            background: #eff6ff;
            transform: scale(1.1);
        }

        .chat-icon i {
            color: #3b82f6;
            font-size: 18px;
        }

        .badge-unread {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.4);
            animation: pulse-badge 2s ease-in-out infinite;
            border: 2px solid #ffffff;
        }

        .badge-unread.hidden {
            display: none;
        }

        @keyframes pulse-badge {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        /* ========== TOAST ========== */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 12px;
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

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .inventory-table th,
            .inventory-table td {
                padding: 10px 8px;
                font-size: 12px;
            }

            .burger-btn {
                top: 15px;
                right: 15px;
                width: 42px;
                height: 42px;
            }

            .side-menu {
                width: 260px;
            }

            .password-wrapper {
                padding: 2px 4px;
                gap: 4px;
            }

            .copy-btn {
                padding: 2px 4px;
                font-size: 12px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }

            .lock-btn,
            .unlock-btn,
            .delete-btn {
                font-size: 10px;
                padding: 4px 10px;
            }

            .email-status-dot {
                width: 10px;
                height: 10px;
                margin-left: 4px;
            }

            .chat-icon i {
                font-size: 14px;
            }

            .badge-unread {
                font-size: 8px;
                min-width: 14px;
                height: 14px;
                top: -4px;
                right: -4px;
                padding: 1px 4px;
            }
        }
    </style>
</head>

<body>
    <div class="app-wrapper">
        <div class="burger-btn" id="burgerBtn">
            <i class="fas fa-bars"></i>
        </div>

        <div class="menu-overlay" id="menuOverlay"></div>

        <?php
        if ($user['authorize_access'] == 0) {
            include 'system_sidebar.php';
        } elseif ($user['authorize_access'] == 1) {
            include 'owner_sidebar.php';
        } elseif ($user['authorize_access'] == 2) {
            include 'admin_sidebar.php';
        }
        ?>

        <main class="main-content">
            <div class="dashboard-header">
                <div class="welcome">
                    <h4>Customers</h4>
                </div>
            </div>

            <div class="merchandise-section">
                <div style="overflow-x: auto;">
                    <table class="inventory-table" id="customersTable">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th class="status-header">Status</th>
                                <th>Chat</th>
                                <th>Phone Number</th>
                                <th>Email</th>
                                <th>Action</th>
                                <th>User / Pass</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px;">No customers found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer):
                                    $onlineStatus = getOnlineStatus($customer['online_time'] ?? '');
                                    // account = 1 means active/unlocked, account = 0 means locked
                                    $isAccountActive = isset($customer['account']) && $customer['account'] == 1;
                                    // active_email = 1 means green dot, active_email = 0 means red dot
                                    $isEmailActive = isset($customer['active_email']) && $customer['active_email'] == 1;
                                    $unreadCount = getUnreadCount($pdo, $customer['acc_number']);
                                    ?>
                                    <tr data-id="<?php echo $customer['id']; ?>">
                                        <td><?php echo htmlspecialchars($customer['f_name'] ?? 'N/A'); ?></td>
                                        <td class="status-cell">
                                            <div class="status-dot-wrapper">
                                                <?php if ($onlineStatus['status'] === 'online'): ?>
                                                    <i class="fas fa-circle status-online" title="Online - Recently Active"></i>
                                                    <span class="status-text online">Active</span>
                                                <?php elseif ($onlineStatus['status'] === 'away'): ?>
                                                    <i class="fas fa-circle status-away" title="Away - Idle for 3-5 min"></i>
                                                    <span class="status-text away">Away</span>
                                                <?php else: ?>
                                                    <i class="fas fa-circle status-offline" title="Offline - 6+ min inactive"></i>
                                                    <span class="status-text offline">Offline</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="chat-icon-wrapper">
                                                <span class="chat-icon"
                                                    onclick="window.location.href='chat_view.php?acc=<?php echo urlencode($customer['acc_number']); ?>'"
                                                    data-acc="<?php echo htmlspecialchars($customer['acc_number']); ?>">
                                                    <i class="fas fa-paper-plane"></i>
                                                </span>
                                                <?php if ($unreadCount > 0): ?>
                                                    <span class="badge-unread" id="badge_<?php echo $customer['id']; ?>">
                                                        <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge-unread hidden"
                                                        id="badge_<?php echo $customer['id']; ?>"></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($customer['phone_number'] ?? 'N/A'); ?>
                                            <?php if (!empty($customer['phone_number'])): ?>
                                                <button class="copy-btn copy-btn-phone"
                                                    onclick="copyToClipboard('<?php echo htmlspecialchars($customer['phone_number']); ?>', 'Phone number')"
                                                    title="Copy phone number">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?>
                                            <?php if (!empty($customer['email'])): ?>
                                                <button class="copy-btn copy-btn-email"
                                                    onclick="copyToClipboard('<?php echo htmlspecialchars($customer['email']); ?>', 'Email')"
                                                    title="Copy email address">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            <?php endif; ?>
                                            <!-- Dot uses active_email column: 1 = green, 0 = red -->
                                            <span
                                                class="email-status-dot <?php echo $isEmailActive ? 'dot-active' : 'dot-inactive'; ?>"
                                                id="dot_<?php echo $customer['id']; ?>"
                                                title="<?php echo $isEmailActive ? 'Email Active' : 'Email Inactive'; ?>">
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons" id="action_<?php echo $customer['id']; ?>">
                                                <?php if ($isAccountActive): ?>
                                                    <!-- Account is active (account = 1) - Show LOCK button -->
                                                    <button class="lock-btn"
                                                        onclick="toggleAccountStatus(<?php echo $customer['id']; ?>, 'lock', '<?php echo addslashes($customer['f_name'] ?? 'Customer'); ?>')">
                                                        <i class="fas fa-lock"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Account is locked (account = 0) - Show UNLOCK button -->
                                                    <button class="unlock-btn"
                                                        onclick="toggleAccountStatus(<?php echo $customer['id']; ?>, 'unlock', '<?php echo addslashes($customer['f_name'] ?? 'Customer'); ?>')">
                                                        <i class="fas fa-unlock"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="delete-btn"
                                                    onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo addslashes($customer['f_name'] ?? 'Customer'); ?>')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td style="white-space: nowrap;">
                                            <div class="password-wrapper">
                                                <span style="color: #475569; font-weight: 500;">
                                                    <?php echo htmlspecialchars($customer['acc_number']); ?>
                                                </span>
                                                <span style="color: #94a3b8;">/</span>
                                                <span class="password-text" id="pass_<?php echo $customer['id']; ?>"
                                                    style="display: none;">
                                                    <?php echo htmlspecialchars($customer['text_pass'] ?? ''); ?>
                                                </span>
                                                <span class="password-placeholder"
                                                    id="placeholder_<?php echo $customer['id']; ?>">
                                                    ••••••••
                                                </span>
                                                <button class="copy-btn copy-btn-eye"
                                                    onclick="togglePassword(<?php echo $customer['id']; ?>, '<?php echo addslashes($customer['text_pass'] ?? ''); ?>')"
                                                    title="Show/Hide password">
                                                    <i class="fas fa-eye" id="eye_<?php echo $customer['id']; ?>"></i>
                                                </button>
                                                <button class="copy-btn copy-btn-copy"
                                                    onclick="copyPassword('<?php echo addslashes($customer['text_pass'] ?? ''); ?>', <?php echo $customer['id']; ?>)"
                                                    title="Copy password">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <?php include '../footer.php'; ?>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?php echo $_SESSION['csrf_token']; ?>';

        // ========== BURGER MENU TOGGLE ==========
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
            link.addEventListener('click', () => {
                closeMenu();
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

        // ========== TOAST ==========
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle';
            toast.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ========== TOGGLE PASSWORD VISIBILITY ==========
        function togglePassword(customerId, password) {
            const passwordText = document.getElementById('pass_' + customerId);
            const placeholder = document.getElementById('placeholder_' + customerId);
            const eyeIcon = document.getElementById('eye_' + customerId);

            if (passwordText.style.display === 'none' || passwordText.style.display === '') {
                passwordText.style.display = 'inline';
                placeholder.style.display = 'none';
                eyeIcon.className = 'fas fa-eye-slash';
            } else {
                passwordText.style.display = 'none';
                placeholder.style.display = 'inline';
                eyeIcon.className = 'fas fa-eye';
            }
        }

        // ========== COPY TO CLIPBOARD (Phone & Email) ==========
        function copyToClipboard(text, label) {
            if (!text || text === 'N/A' || text === '') {
                showToast('Nothing to copy', 'warning');
                return;
            }

            navigator.clipboard.writeText(text).then(() => {
                showToast(`${label} copied to clipboard!`, 'success');

                // Find the clicked button and show checkmark temporarily
                const buttons = document.querySelectorAll('.copy-btn-phone, .copy-btn-email');
                buttons.forEach(btn => {
                    const parentTd = btn.closest('td');
                    if (parentTd && parentTd.textContent.includes(text)) {
                        const icon = btn.querySelector('i');
                        if (icon) {
                            icon.className = 'fas fa-check';
                            btn.classList.add('copied');
                            setTimeout(() => {
                                icon.className = 'fas fa-copy';
                                btn.classList.remove('copied');
                            }, 2000);
                        }
                    }
                });
            }).catch(() => {
                // Fallback for older browsers
                try {
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    showToast(`${label} copied to clipboard!`, 'success');
                } catch (err) {
                    showToast('Failed to copy', 'error');
                }
            });
        }

        // ========== COPY PASSWORD (existing function) ==========
        function copyPassword(password, customerId) {
            if (!password) {
                showToast('No password to copy', 'warning');
                return;
            }

            navigator.clipboard.writeText(password).then(() => {
                showToast('Password copied to clipboard!', 'success');
                const copyBtn = event.target.closest('.copy-btn-copy');
                const icon = copyBtn.querySelector('i');
                const originalClass = icon.className;
                icon.className = 'fas fa-check';
                icon.style.color = '#10b981';
                setTimeout(() => {
                    icon.className = originalClass;
                    icon.style.color = '#10b981';
                }, 1500);
            }).catch(() => {
                try {
                    const textArea = document.createElement('textarea');
                    textArea.value = password;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    showToast('Password copied to clipboard!', 'success');
                } catch (err) {
                    showToast('Failed to copy password', 'error');
                }
            });
        }
        
        // ========== TOGGLE ACCOUNT STATUS (LOCK/UNLOCK) ==========
        async function toggleAccountStatus(customerId, action, customerName) {
            const confirmMessage = action === 'lock'
                ? `⚠️ Are you sure you want to LOCK "${customerName}"'s account?\n\nThis will lock their account.`
                : `⚠️ Are you sure you want to UNLOCK "${customerName}"'s account?\n\nThis will reactivate their account.`;

            if (!confirm(confirmMessage)) return;

            const row = document.querySelector(`tr[data-id="${customerId}"]`);
            const actionBtn = row.querySelector(action === 'lock' ? '.lock-btn' : '.unlock-btn');
            const originalText = actionBtn.innerHTML;
            actionBtn.disabled = true;
            actionBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const formData = new FormData();
                formData.append('action', 'toggle_account_status');
                formData.append('customer_id', customerId);
                formData.append('status_action', action);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/customer_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showToast(data.message, 'success');

                    // Update the action buttons (based on account column)
                    const actionContainer = document.getElementById('action_' + customerId);

                    if (action === 'lock') {
                        // Account is now locked (account = 0) - Show UNLOCK button
                        actionContainer.innerHTML = `
                    <button class="unlock-btn" onclick="toggleAccountStatus(${customerId}, 'unlock', '${customerName.replace(/'/g, "\\'")}')">
                        <i class="fas fa-unlock"></i>
                    </button>
                    <button class="delete-btn" onclick="deleteCustomer(${customerId}, '${customerName.replace(/'/g, "\\'")}')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                `;
                    } else {
                        // Account is now active (account = 1) - Show LOCK button
                        actionContainer.innerHTML = `
                    <button class="lock-btn" onclick="toggleAccountStatus(${customerId}, 'lock', '${customerName.replace(/'/g, "\\'")}')">
                        <i class="fas fa-lock"></i>
                    </button>
                    <button class="delete-btn" onclick="deleteCustomer(${customerId}, '${customerName.replace(/'/g, "\\'")}')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                `;
                    }

                    // Note: The dot color is controlled by active_email column
                    // If your API also updates active_email when locking/unlocking, update the dot here
                    // Otherwise, the dot remains unchanged

                } else {
                    showToast(data.message || 'Failed to update account status', 'error');
                    actionBtn.disabled = false;
                    actionBtn.innerHTML = originalText;
                }
            } catch (err) {
                console.error('Error:', err);
                showToast('Network error. Please try again.', 'error');
                actionBtn.disabled = false;
                actionBtn.innerHTML = originalText;
            }
        }

        // ========== DELETE CUSTOMER ==========
        async function deleteCustomer(customerId, customerName) {
            const confirmed = confirm(`⚠️ Are you sure you want to delete "${customerName}"?\n\nThis action will permanently remove this customer from the database and cannot be undone!`);
            if (!confirmed) return;

            const row = document.querySelector(`tr[data-id="${customerId}"]`);
            const deleteBtn = row.querySelector('.delete-btn');
            const originalText = deleteBtn.innerHTML;
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const formData = new FormData();
                formData.append('action', 'delete_customer');
                formData.append('customer_id', customerId);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/customer_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    row.remove();
                    const headerCount = document.querySelector('.section-header h5');
                    if (headerCount) {
                        const currentCount = parseInt(headerCount.textContent.match(/\((\d+)\)/)?.[1] || 0);
                        headerCount.innerHTML = `(${currentCount - 1}) Customer List`;
                    }
                } else {
                    showToast(data.message || 'Failed to delete customer', 'error');
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalText;
                }
            } catch (err) {
                console.error('Error:', err);
                showToast('Network error. Please try again.', 'error');
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalText;
            }
        }

        // ========== AUTO-REFRESH STATUS EVERY 60 SECONDS ==========
        function refreshOnlineStatus() {
            fetch(window.location.href, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newStatuses = doc.querySelectorAll('.status-cell');
                    const currentStatuses = document.querySelectorAll('.status-cell');

                    if (newStatuses.length === currentStatuses.length) {
                        currentStatuses.forEach((cell, index) => {
                            if (newStatuses[index]) {
                                cell.innerHTML = newStatuses[index].innerHTML;
                            }
                        });
                    }
                })
                .catch(error => console.error('Error refreshing status:', error));
        }

        setInterval(refreshOnlineStatus, 60000);

        // ========== UPDATE UNREAD BADGES EVERY 30 SECONDS ==========
        function updateUnreadBadges() {
            const wrappers = document.querySelectorAll('.chat-icon-wrapper');

            wrappers.forEach(wrapper => {
                const icon = wrapper.querySelector('.chat-icon');
                if (!icon) return;

                const accNumber = icon.getAttribute('data-acc');
                if (!accNumber) return;

                const badge = wrapper.querySelector('.badge-unread');
                if (!badge) return;

                // Find the customer ID from the row
                const row = icon.closest('tr');
                if (!row) return;
                const customerId = row.getAttribute('data-id');
                if (!customerId) return;

                // Fetch unread count for this customer
                const formData = new FormData();
                formData.append('action', 'get_unread_count');
                formData.append('customer_acc', accNumber);
                formData.append('csrf_token', csrfToken);

                fetch('../Customer_API/chat.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const count = data.unread_count || 0;
                            if (count > 0) {
                                badge.textContent = count > 9 ? '9+' : count;
                                badge.classList.remove('hidden');
                            } else {
                                badge.classList.add('hidden');
                            }
                        }
                    })
                    .catch(error => console.error('Error updating badge:', error));
            });
        }

        // Update unread badges every 30 seconds
        setInterval(updateUnreadBadges, 30000);

        console.log('👥 Registered Customers page loaded');
        console.log('🟢 Status indicators update every 60 seconds');
        console.log('🔐 Password hidden by default - click eye to show, copy to copy');
        console.log('🔒 Lock/Unlock buttons update account status without page reload');
        console.log('💬 Unread message badges update every 30 seconds');
    </script>
</body>

</html>