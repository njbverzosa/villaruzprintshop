<?php
// web/registered_customers.php
session_start();

// ==============================================
// 1. FIX PATHS
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

// ==============================================
// 4. USER + AUTHORIZE ACCESS
// ==============================================
$user = $userData;
$authorizeAccess = $userData['authorize_access'] ?? 0;

// ✅ Only show User/Pass column when authorize_access == 0
$canViewUserPass = ($authorizeAccess == 0);

// ==============================================
// 5. SET TIMEZONE
// ==============================================
date_default_timezone_set('Asia/Manila');
$timezone = new DateTimeZone('Asia/Manila');

// ==============================================
// 6. FETCH ALL 3 DATASETS
// ==============================================
$stmt = $pdo->prepare("SELECT * FROM admins WHERE authorize_access IN (1, 2) ORDER BY id DESC");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM investors ORDER BY id DESC");
$stmt->execute();
$investors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM customers ORDER BY id DESC");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==============================================
// HELPER: Normalize image path from DB
// ==============================================
function normalizeImagePath($path)
{
    $path = trim($path ?? '');
    if ($path === '')
        return '';
    $path = ltrim($path, '/');
    $path = preg_replace('#^(\.\./)+#', '', $path);
    return $path;
}

// ==============================================
// FUNCTION TO GET UNREAD MESSAGE COUNT
// ==============================================
function getUnreadCount($pdo, $customerAccNumber)
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count 
            FROM chat_conversation 
            WHERE acc_number = ? 
            AND status = 0
        ");
        $stmt->execute([$customerAccNumber]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['unread_count'] ?? 0);
    } catch (PDOException $e) {
        error_log("Error getting unread count: " . $e->getMessage());
        return 0;
    }
}

// ==============================================
// FUNCTION TO DETERMINE ONLINE STATUS
// ==============================================
function getOnlineStatus($onlineTime)
{
    if (empty($onlineTime)) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'time_diff' => ''];
    }

    $storedTimestamp = strtotime($onlineTime);
    if ($storedTimestamp === false) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'time_diff' => ''];
    }

    $currentTimestamp = time();
    $diffSeconds = $currentTimestamp - $storedTimestamp;
    $diffMinutes = floor($diffSeconds / 60);
    $diffHours = floor($diffSeconds / 3600);
    $diffDays = floor($diffSeconds / 86400);
    $diffWeeks = floor($diffSeconds / 604800);

    if ($diffMinutes <= 1) {
        return ['status' => 'online', 'class' => 'status-online', 'text' => '● Online', 'time_diff' => ''];
    } elseif ($diffMinutes >= 1 && $diffMinutes <= 60) {
        return ['status' => 'away', 'class' => 'status-away', 'text' => '● Away', 'time_diff' => $diffMinutes . 'm'];
    } elseif ($diffHours >= 1 && $diffHours < 24) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'time_diff' => $diffHours . 'h'];
    } elseif ($diffDays >= 1 && $diffDays < 7) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'time_diff' => $diffDays . 'd'];
    } elseif ($diffWeeks >= 1 && $diffWeeks < 4) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'time_diff' => $diffWeeks . 'w'];
    } else {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'time_diff' => '4w+'];
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Registered Users | Villaruz Print Shop & General Merchandise</title>
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

        /* ========== SIDEBAR ========== */
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

        @media (max-width: 768px) {
            .sidebar-wrapper {
                transform: translateX(-100%);
            }

            .sidebar-wrapper.open {
                transform: translateX(0);
            }
        }

        @media (min-width: 769px) {
            .sidebar-wrapper {
                transform: translateX(0) !important;
            }

            .main-content {
                margin-left: 280px;
                padding: 30px;
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
            padding: 30px;
            overflow-y: auto;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
                margin-left: 0 !important;
                padding-top: 20px;
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
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
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

        /* ========== SECTION / TABLE ========== */
        .merchandise-section {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            overflow-x: auto;
            margin-top: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
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

        /* Status Indicators */
        .status-online {
            color: #10b981;
            font-size: 20px;
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
            animation: pulse-green 2s infinite;
        }

        .status-away {
            color: #f59e0b;
            font-size: 20px;
            animation: pulse-away 1.5s infinite;
        }

        .status-offline {
            color: #94a3b8;
            font-size: 18px;
            opacity: 0.5;
        }

        .status-text {
            font-size: 11px;
            font-weight: 500;
            margin-left: 6px;
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

        /* Used / Login Type icons */
        .used-icon {
            font-size: 20px;
            vertical-align: middle;
        }

        .used-icon.desktop {
            color: #3b82f6;
        }

        .used-icon.mobile {
            color: #10b981;
        }

        /* Email Status Dot */
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

        /* Password Styles */
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

        /* Copy Buttons */
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

        /* Chat Icon */
        .chat-icon-wrapper {
            position: relative;
            display: inline-block;
        }

        .chat-icon {
            cursor: pointer;
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

        /* Toast */
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

        /* Image Thumbnails */
        .landmark-thumb,
        .permit-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            cursor: pointer;
            border-radius: 5px;
            transition: transform 0.2s;
            border: 1px solid #e2e8f0;
        }

        .landmark-thumb:hover,
        .permit-thumb:hover {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }

        .no-photo {
            color: #999;
            font-style: italic;
            font-size: 12px;
        }

        /* Modal */
        .landmark-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            overflow: hidden;
            cursor: zoom-out;
        }

        .landmark-modal-content {
            position: relative;
            margin: auto;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            padding: 20px;
        }

        .landmark-modal-image {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            transition: transform 0.3s ease;
            cursor: zoom-in;
        }

        .landmark-modal-image.zoomed {
            transform: scale(2);
            cursor: zoom-out;
        }

        .landmark-modal-close {
            position: fixed;
            top: 20px;
            right: 35px;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            z-index: 10000;
            background: rgba(0, 0, 0, 0.5);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            color: white;
        }

        .landmark-modal-close:hover {
            color: #bbb;
            background: rgba(0, 0, 0, 0.8);
            transform: scale(1.1);
        }

        .landmark-modal-caption {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-size: 18px;
            background: rgba(0, 0, 0, 0.7);
            padding: 10px 20px;
            border-radius: 5px;
            text-align: center;
            max-width: 80%;
        }

        .zoom-controls {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 15px;
            z-index: 10000;
        }

        .zoom-controls button {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }

        .zoom-controls button:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
                padding-top: 20px;
            }

            .inventory-table th,
            .inventory-table td {
                padding: 10px 8px;
                font-size: 12px;
            }

            .password-wrapper {
                padding: 2px 4px;
                gap: 4px;
            }

            .copy-btn {
                padding: 2px 4px;
                font-size: 12px;
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

            .dashboard-header {
                padding: 15px 20px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
                padding-top: 15px;
            }

            .dashboard-header {
                padding: 12px 15px;
                border-radius: 10px;
            }

            .welcome h4 {
                font-size: 14px;
            }

            .inventory-table {
                font-size: 10px;
            }

            .inventory-table th,
            .inventory-table td {
                padding: 6px 4px;
                font-size: 10px;
            }

            .landmark-thumb,
            .permit-thumb {
                width: 40px;
                height: 40px;
            }

            .password-wrapper {
                padding: 2px 4px;
                gap: 2px;
            }

            .password-text,
            .password-placeholder {
                font-size: 10px;
            }

            .copy-btn {
                font-size: 10px;
                padding: 2px 3px;
            }

            .chat-icon {
                padding: 4px;
            }

            .chat-icon i {
                font-size: 12px;
            }

            .badge-unread {
                font-size: 7px;
                min-width: 12px;
                height: 12px;
                top: -3px;
                right: -3px;
                padding: 1px 3px;
            }

            .email-status-dot {
                width: 8px;
                height: 8px;
                margin-left: 3px;
            }

            .status-online,
            .status-away {
                font-size: 16px;
            }

            .status-offline {
                font-size: 14px;
            }

            .status-text {
                font-size: 9px;
            }

            .used-icon {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>
    <div class="app-wrapper">
        <div class="menu-overlay" id="menuOverlay"></div>

        <div class="sidebar-wrapper" id="sidebarWrapper">
            <div class="side-menu" id="sideMenu">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <main class="main-content">
            <div class="dashboard-header">
                <div class="header-left">
                    <button class="burger-btn" id="burgerBtn" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="welcome">
                        <h4>Registered Users</h4>
                    </div>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- TABLE 1: ADMINS -->
            <!-- ============================================== -->
            <div class="merchandise-section">
                <div style="overflow-x: auto;">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Status</th>
                                <th>Used</th>
                                <?php if ($canViewUserPass): ?>
                                    <th>User / Pass</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($admins)): ?>
                                <tr>
                                    <td colspan="<?php echo $canViewUserPass ? '4' : '3'; ?>" style="padding: 40px;">No admins found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($admins as $admin):
                                    $onlineStatus = getOnlineStatus($admin['online_time'] ?? '');
                                    ?>
                                    <tr data-id="<?php echo $admin['id']; ?>" data-role="Admin">
                                        <td><?php echo htmlspecialchars($admin['f_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($onlineStatus['status'] === 'online'): ?>
                                                <i class="fas fa-circle status-online" title="Online - Recently Active"></i>
                                                <span class="status-text online">Active</span>
                                            <?php elseif ($onlineStatus['status'] === 'away'): ?>
                                                <i class="fas fa-clock status-away"
                                                    title="Away - <?php echo $onlineStatus['time_diff']; ?> ago"></i>
                                                <span class="status-text away"><?php echo $onlineStatus['time_diff']; ?></span>
                                            <?php else: ?>
                                                <i class="fas fa-clock status-offline"
                                                    title="Offline - <?php echo $onlineStatus['time_diff']; ?> ago"></i>
                                                <span class="status-text offline"><?php echo $onlineStatus['time_diff']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $loginType = $admin['login_type'] ?? 'web'; ?>
                                            <?php if ($loginType === 'app'): ?>
                                                <i class="fas fa-mobile-alt used-icon mobile" title="App"></i>
                                            <?php else: ?>
                                                <i class="fas fa-desktop used-icon desktop" title="Web"></i>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($canViewUserPass): ?>
                                            <td style="white-space: nowrap;">
                                                <div class="password-wrapper">
                                                    <span style="color: #475569; font-weight: 500;">
                                                        <?php echo htmlspecialchars($admin['acc_number']); ?>
                                                    </span>
                                                    <span style="color: #94a3b8;">/</span>
                                                    <span class="password-text" id="pass_admin_<?php echo $admin['id']; ?>"
                                                        style="display: none;">
                                                        <?php echo htmlspecialchars($admin['text_pass'] ?? ''); ?>
                                                    </span>
                                                    <span class="password-placeholder"
                                                        id="placeholder_admin_<?php echo $admin['id']; ?>">
                                                        ••••••••
                                                    </span>
                                                    <button class="copy-btn copy-btn-eye"
                                                        onclick="togglePassword('admin_<?php echo $admin['id']; ?>')"
                                                        title="Show/Hide password">
                                                        <i class="fas fa-eye" id="eye_admin_<?php echo $admin['id']; ?>"></i>
                                                    </button>
                                                    <button class="copy-btn copy-btn-copy"
                                                        onclick="copyPassword('<?php echo addslashes($admin['text_pass'] ?? ''); ?>')"
                                                        title="Copy password">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- TABLE 2: INVESTORS -->
            <!-- ============================================== -->
            <div class="merchandise-section">
                <div style="overflow-x: auto;">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Business Permit</th>
                                <th>Full Name</th>
                                <th>Status</th>
                                <th>Used</th>
                                <th>Chat</th>
                                <th>Phone Number</th>
                                <?php if ($canViewUserPass): ?>
                                    <th>User / Pass</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($investors)): ?>
                                <tr>
                                    <td colspan="<?php echo $canViewUserPass ? '7' : '6'; ?>" style="padding: 40px;">No investors found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($investors as $investor):
                                    $onlineStatus = getOnlineStatus($investor['online_time'] ?? '');
                                    $unreadCount = getUnreadCount($pdo, $investor['acc_number']);

                                    $permitPhoto = normalizeImagePath($investor['business_permit'] ?? '');
                                    $investorName = htmlspecialchars($investor['f_name'] ?? 'Investor');
                                    ?>
                                    <tr data-id="<?php echo $investor['id']; ?>" data-role="Investor">
                                        <td>
                                            <?php if ($permitPhoto !== ''): ?>
                                                <img src="../Business_Docs/<?php echo htmlspecialchars($permitPhoto); ?>"
                                                    alt="Business permit of <?php echo $investorName; ?>" class="permit-thumb"
                                                    onclick="openPermitModal('../Business_Docs/<?php echo htmlspecialchars($permitPhoto); ?>', '<?php echo $investorName; ?>')"
                                                    title="Click to zoom"
                                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                                <span class="no-photo" style="display:none;">No permit</span>
                                            <?php else: ?>
                                                <span class="no-photo">No permit</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($investor['f_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($onlineStatus['status'] === 'online'): ?>
                                                <i class="fas fa-circle status-online" title="Online - Recently Active"></i>
                                                <span class="status-text online">Active</span>
                                            <?php elseif ($onlineStatus['status'] === 'away'): ?>
                                                <i class="fas fa-clock status-away"
                                                    title="Away - <?php echo $onlineStatus['time_diff']; ?> ago"></i>
                                                <span class="status-text away"><?php echo $onlineStatus['time_diff']; ?></span>
                                            <?php else: ?>
                                                <i class="fas fa-clock status-offline"
                                                    title="Offline - <?php echo $onlineStatus['time_diff']; ?> ago"></i>
                                                <span class="status-text offline"><?php echo $onlineStatus['time_diff']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $loginType = $investor['login_type'] ?? 'web'; ?>
                                            <?php if ($loginType === 'app'): ?>
                                                <i class="fas fa-mobile-alt used-icon mobile" title="App"></i>
                                            <?php else: ?>
                                                <i class="fas fa-desktop used-icon desktop" title="Web"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="chat-icon-wrapper">
                                                <span class="chat-icon"
                                                    onclick="window.location.href='chat_view.php?acc=<?php echo urlencode($investor['acc_number']); ?>'"
                                                    data-acc="<?php echo htmlspecialchars($investor['acc_number']); ?>">
                                                    <i class="fas fa-paper-plane"></i>
                                                </span>
                                                <?php if ($unreadCount > 0): ?>
                                                    <span class="badge-unread" id="badge_investor_<?php echo $investor['id']; ?>">
                                                        <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge-unread hidden"
                                                        id="badge_investor_<?php echo $investor['id']; ?>"></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($investor['phone_number'] ?? 'N/A'); ?>
                                            <?php if (!empty($investor['phone_number'])): ?>
                                                <button class="copy-btn copy-btn-phone"
                                                    onclick="copyToClipboard('<?php echo htmlspecialchars($investor['phone_number']); ?>', 'Phone number')"
                                                    title="Copy phone number">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($canViewUserPass): ?>
                                            <td style="white-space: nowrap;">
                                                <div class="password-wrapper">
                                                    <span style="color: #475569; font-weight: 500;">
                                                        <?php echo htmlspecialchars($investor['acc_number']); ?>
                                                    </span>
                                                    <span style="color: #94a3b8;">/</span>
                                                    <span class="password-text" id="pass_investor_<?php echo $investor['id']; ?>"
                                                        style="display: none;">
                                                        <?php echo htmlspecialchars($investor['text_pass'] ?? ''); ?>
                                                    </span>
                                                    <span class="password-placeholder"
                                                        id="placeholder_investor_<?php echo $investor['id']; ?>">
                                                        ••••••••
                                                    </span>
                                                    <button class="copy-btn copy-btn-eye"
                                                        onclick="togglePassword('investor_<?php echo $investor['id']; ?>')"
                                                        title="Show/Hide password">
                                                        <i class="fas fa-eye" id="eye_investor_<?php echo $investor['id']; ?>"></i>
                                                    </button>
                                                    <button class="copy-btn copy-btn-copy"
                                                        onclick="copyPassword('<?php echo addslashes($investor['text_pass'] ?? ''); ?>')"
                                                        title="Copy password">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- TABLE 3: CUSTOMERS -->
            <!-- ============================================== -->
            <div class="merchandise-section">
                <div style="overflow-x: auto;">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Landmark</th>
                                <th>Full Name</th>
                                <th>Status</th>
                                <th>Used</th>
                                <th>Chat</th>
                                <th>Phone Number</th>
                                <th>Email</th>
                                <?php if ($canViewUserPass): ?>
                                    <th>User / Pass</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="<?php echo $canViewUserPass ? '8' : '7'; ?>" style="padding: 40px;">No customers found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer):
                                    $onlineStatus = getOnlineStatus($customer['online_time'] ?? '');
                                    $isEmailActive = isset($customer['active_email']) && $customer['active_email'] == 1;
                                    $unreadCount = getUnreadCount($pdo, $customer['acc_number']);

                                    $landmarkPhoto = normalizeImagePath($customer['landmark_photo'] ?? '');
                                    $customerName = htmlspecialchars($customer['f_name'] ?? 'Customer');
                                    ?>
                                    <tr data-id="<?php echo $customer['id']; ?>" data-role="Customer">
                                        <td>
                                            <?php if ($landmarkPhoto !== ''): ?>
                                                <img src="../Landmark/<?php echo htmlspecialchars($landmarkPhoto); ?>"
                                                    alt="Landmark photo of <?php echo $customerName; ?>" class="landmark-thumb"
                                                    onclick="openLandmarkModal('../Landmark/<?php echo htmlspecialchars($landmarkPhoto); ?>', '<?php echo $customerName; ?>')"
                                                    title="Click to zoom"
                                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                                <span class="no-photo" style="display:none;">No photo</span>
                                            <?php else: ?>
                                                <span class="no-photo">No photo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($customer['f_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($onlineStatus['status'] === 'online'): ?>
                                                <i class="fas fa-circle status-online" title="Online - Recently Active"></i>
                                                <span class="status-text online">Active</span>
                                            <?php elseif ($onlineStatus['status'] === 'away'): ?>
                                                <i class="fas fa-clock status-away"
                                                    title="Away - <?php echo $onlineStatus['time_diff']; ?> ago"></i>
                                                <span class="status-text away"><?php echo $onlineStatus['time_diff']; ?></span>
                                            <?php else: ?>
                                                <i class="fas fa-clock status-offline"
                                                    title="Offline - <?php echo $onlineStatus['time_diff']; ?> ago"></i>
                                                <span class="status-text offline"><?php echo $onlineStatus['time_diff']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $loginType = $customer['login_type'] ?? 'web'; ?>
                                            <?php if ($loginType === 'app'): ?>
                                                <i class="fas fa-mobile-alt used-icon mobile" title="App"></i>
                                            <?php else: ?>
                                                <i class="fas fa-desktop used-icon desktop" title="Web"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="chat-icon-wrapper">
                                                <span class="chat-icon"
                                                    onclick="window.location.href='chat_view.php?acc=<?php echo urlencode($customer['acc_number']); ?>'"
                                                    data-acc="<?php echo htmlspecialchars($customer['acc_number']); ?>">
                                                    <i class="fas fa-paper-plane"></i>
                                                </span>
                                                <?php if ($unreadCount > 0): ?>
                                                    <span class="badge-unread" id="badge_customer_<?php echo $customer['id']; ?>">
                                                        <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge-unread hidden"
                                                        id="badge_customer_<?php echo $customer['id']; ?>"></span>
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
                                            <span
                                                class="email-status-dot <?php echo $isEmailActive ? 'dot-active' : 'dot-inactive'; ?>"
                                                title="<?php echo $isEmailActive ? 'Email Active' : 'Email Inactive'; ?>">
                                            </span>
                                        </td>
                                        <?php if ($canViewUserPass): ?>
                                            <td style="white-space: nowrap;">
                                                <div class="password-wrapper">
                                                    <span style="color: #475569; font-weight: 500;">
                                                        <?php echo htmlspecialchars($customer['acc_number']); ?>
                                                    </span>
                                                    <span style="color: #94a3b8;">/</span>
                                                    <span class="password-text" id="pass_customer_<?php echo $customer['id']; ?>"
                                                        style="display: none;">
                                                        <?php echo htmlspecialchars($customer['text_pass'] ?? ''); ?>
                                                    </span>
                                                    <span class="password-placeholder"
                                                        id="placeholder_customer_<?php echo $customer['id']; ?>">
                                                        ••••••••
                                                    </span>
                                                    <button class="copy-btn copy-btn-eye"
                                                        onclick="togglePassword('customer_<?php echo $customer['id']; ?>')"
                                                        title="Show/Hide password">
                                                        <i class="fas fa-eye" id="eye_customer_<?php echo $customer['id']; ?>"></i>
                                                    </button>
                                                    <button class="copy-btn copy-btn-copy"
                                                        onclick="copyPassword('<?php echo addslashes($customer['text_pass'] ?? ''); ?>')"
                                                        title="Copy password">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Image Modal -->
    <div id="landmarkModal" class="landmark-modal">
        <button class="landmark-modal-close" onclick="closeLandmarkModal()">&times;</button>
        <div class="landmark-modal-content">
            <img id="landmarkModalImage" class="landmark-modal-image" src="" alt="Image">
        </div>
        <div id="landmarkModalCaption" class="landmark-modal-caption"></div>
        <div class="zoom-controls">
            <button onclick="zoomIn()">🔍 Zoom In</button>
            <button onclick="zoomOut()">🔍 Zoom Out</button>
            <button onclick="resetZoom()">↺ Reset</button>
        </div>
    </div>

    <?php include '../footer.php'; ?>

    <script>
        // ========== SIDEBAR TOGGLE ==========
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
            if (isSidebarOpen) closeSidebar();
            else openSidebar();
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

        document.querySelectorAll('.side-menu .nav-item, .side-menu .nav-dropdown-item').forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 768) {
                    if (!this.closest('.nav-dropdown-toggle')) {
                        closeSidebar();
                    }
                }
            });
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                if (isSidebarOpen) closeSidebar();
                sidebarWrapper.classList.remove('open');
                menuOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // ========== HELPERS ==========
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?php echo $_SESSION['csrf_token']; ?>';

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

        function copyToClipboard(text, label) {
            if (!text || text === 'N/A' || text === '') {
                showToast('Nothing to copy', 'warning');
                return;
            }
            navigator.clipboard.writeText(text).then(() => {
                showToast(`${label} copied to clipboard!`, 'success');
            }).catch(() => {
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

        function togglePassword(key) {
            const passwordText = document.getElementById('pass_' + key);
            const placeholder = document.getElementById('placeholder_' + key);
            const eyeIcon = document.getElementById('eye_' + key);
            if (!passwordText || !placeholder || !eyeIcon) return;

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

        function copyPassword(password) {
            if (!password) {
                showToast('No password to copy', 'warning');
                return;
            }
            navigator.clipboard.writeText(password).then(() => {
                showToast('Password copied to clipboard!', 'success');
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

        // ========== IMAGE MODAL ==========
        let currentZoom = 1;
        const zoomStep = 0.25;
        const maxZoom = 5;
        const minZoom = 1;

        function openLandmarkModal(imageSrc, name) {
            openImageModal(imageSrc, name + "'s Landmark Photo");
        }

        function openPermitModal(imageSrc, name) {
            openImageModal(imageSrc, name + "'s Business Permit");
        }

        function openImageModal(imageSrc, captionText) {
            const modal = document.getElementById('landmarkModal');
            const modalImage = document.getElementById('landmarkModalImage');
            const caption = document.getElementById('landmarkModalCaption');

            modalImage.src = imageSrc;
            caption.textContent = captionText;
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';

            resetZoom();
            document.addEventListener('keydown', handleKeyPress);
        }

        function closeLandmarkModal() {
            const modal = document.getElementById('landmarkModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            document.removeEventListener('keydown', handleKeyPress);
            resetZoom();
        }

        function handleKeyPress(e) {
            if (e.key === 'Escape') closeLandmarkModal();
            else if (e.key === '+' || e.key === '=') zoomIn();
            else if (e.key === '-') zoomOut();
            else if (e.key === '0') resetZoom();
        }

        function zoomIn() {
            const img = document.getElementById('landmarkModalImage');
            if (currentZoom < maxZoom) {
                currentZoom = Math.min(currentZoom + zoomStep, maxZoom);
                img.style.transform = `scale(${currentZoom})`;
            }
        }

        function zoomOut() {
            const img = document.getElementById('landmarkModalImage');
            if (currentZoom > minZoom) {
                currentZoom = Math.max(currentZoom - zoomStep, minZoom);
                img.style.transform = `scale(${currentZoom})`;
            }
        }

        function resetZoom() {
            currentZoom = 1;
            const img = document.getElementById('landmarkModalImage');
            img.style.transform = 'scale(1)';
            const container = img.parentElement;
            container.scrollTop = 0;
            container.scrollLeft = 0;
        }

        document.getElementById('landmarkModal').addEventListener('click', function (e) {
            if (e.target === this) closeLandmarkModal();
        });

        document.getElementById('landmarkModalImage').addEventListener('click', function (e) {
            e.stopPropagation();
            if (currentZoom === 1) zoomIn();
            else resetZoom();
        });

        document.getElementById('landmarkModalImage').addEventListener('wheel', function (e) {
            e.preventDefault();
            if (e.deltaY < 0) zoomIn();
            else zoomOut();
        });

        window.addEventListener('resize', function () {
            if (document.getElementById('landmarkModal').style.display === 'block') {
                resetZoom();
            }
        });

        console.log('📱 Sidebar loaded | 3 Tables: Admins | Investors | Customers');
        console.log('🔐 Can view User/Pass: <?php echo $canViewUserPass ? "YES" : "NO"; ?>');
    </script>
</body>

</html>