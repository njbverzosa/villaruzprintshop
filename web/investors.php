<?php
// web/registered_customers.php
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
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 4. USE $userData INSTEAD OF $user
// ==============================================
$user = $userData;
$authorizeAccess = isset($user['authorize_access']) ? (int) $user['authorize_access'] : 0;

// ==============================================
// 5. SET TIMEZONE
// ==============================================
date_default_timezone_set('Asia/Manila');
$timezone = new DateTimeZone('Asia/Manila');

// Fetch all customers from investors table
$stmt = $pdo->prepare("SELECT * FROM investors ORDER BY id DESC");
$stmt->execute();
$customers = $stmt->fetchAll();

// Get current page for sidebar
$currentPage = basename($_SERVER['PHP_SELF']);
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

        /* Profile / Permit Thumbnail */
        .profile-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            transition: transform 0.2s;
            cursor: pointer;
        }

        .profile-thumb:hover {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }

        .no-photo {
            color: #999;
            font-style: italic;
            font-size: 12px;
        }

        /* Business Permit Badge */
        .permit-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .permit-badge.has-permit {
            background: #d1fae5;
            color: #065f46;
        }

        .permit-badge.has-permit:hover {
            background: #a7f3d0;
            transform: scale(1.05);
        }

        .permit-badge.no-permit {
            background: #fee2e2;
            color: #991b1b;
            cursor: default;
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

            .profile-thumb {
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

            .lock-btn,
            .unlock-btn,
            .delete-btn {
                font-size: 9px;
                padding: 3px 6px;
            }

            .landmark-modal-close {
                width: 36px;
                height: 36px;
                font-size: 28px;
                top: 10px;
                right: 15px;
            }

            .landmark-modal-caption {
                font-size: 14px;
                bottom: 20px;
                padding: 8px 15px;
            }

            .zoom-controls {
                bottom: 70px;
                gap: 8px;
            }

            .zoom-controls button {
                padding: 6px 12px;
                font-size: 12px;
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
                        <h4>Investors</h4>
                    </div>
                </div>
            </div>

            <div class="merchandise-section">
                <div style="overflow-x: auto;">
                    <table class="inventory-table" id="customersTable">
                        <thead>
                            <tr>
                                <th>Permit</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Acc Number</th>
                                <th>Phone Number</th>
                                <th>Business Name</th>
                                <?php if ($authorizeAccess == 0): ?>
                                    <th>User / Pass</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="<?php echo $authorizeAccess == 0 ? '9' : '7'; ?>"
                                        style="text-align: center; padding: 40px;">No customers found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer):
                                    $isAccountActive = isset($customer['status']) && $customer['status'] == 1;

                                    // Business permit
                                    $businessPermit = $customer['business_permit'] ?? '';
                                    $hasPermit = !empty($businessPermit);
                                    $permitPath = $hasPermit ? '../Business_Docs/' . $businessPermit : '';
                                    $permitAbsolutePath = $hasPermit ? __DIR__ . '/../Business_Docs/' . $businessPermit : '';
                                    $permitExists = $hasPermit && file_exists($permitAbsolutePath);
                                ?>
                                    <tr data-id="<?php echo $customer['id']; ?>">
                                        <td>
                                            <?php if ($permitExists): ?>
                                                <img src="../Business_Docs/<?php echo htmlspecialchars($businessPermit); ?>"
                                                    alt="Permit of <?php echo htmlspecialchars($customer['f_name'] ?? 'Customer'); ?>"
                                                    class="profile-thumb"
                                                    onclick="openLandmarkModal('../Business_Docs/<?php echo htmlspecialchars($businessPermit); ?>', '<?php echo htmlspecialchars($customer['f_name'] ?? 'Customer'); ?> - Business Permit')"
                                                    title="Click to zoom">
                                            <?php else: ?>
                                                <span class="no-photo">No permit</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($customer['f_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($customer['user_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($customer['acc_number'] ?? ''); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($customer['phone_number'] ?? ''); ?>
                                            <?php if (!empty($customer['phone_number'])): ?>
                                                <button class="copy-btn copy-btn-phone"
                                                    onclick="copyToClipboard('<?php echo htmlspecialchars($customer['phone_number']); ?>', 'Phone number')"
                                                    title="Copy phone number">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($customer['business_name'] ?? 'N/A'); ?></td>
                                        
                                        <?php if ($authorizeAccess == 0): ?>
                                           
                                            <td style="white-space: nowrap;">
                                                <div class="password-wrapper">
                                                    <span style="color: #475569; font-weight: 500;">
                                                        <?php echo htmlspecialchars($customer['acc_number'] ?? ''); ?>
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
            <img id="landmarkModalImage" class="landmark-modal-image" src="" alt="Photo">
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

        // ========== EXISTING FUNCTIONS ==========
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?php echo $_SESSION['csrf_token']; ?>';

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

        // ========== COPY TO CLIPBOARD ==========
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

        // ========== COPY PASSWORD ==========
        function copyPassword(password, customerId) {
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

        // ========== TOGGLE ACCOUNT STATUS ==========
        async function toggleAccountStatus(customerId, action, customerName) {
            const confirmMessage = action === 'lock'
                ? `⚠️ Are you sure you want to LOCK "${customerName}"'s account?`
                : `⚠️ Are you sure you want to UNLOCK "${customerName}"'s account?`;

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

                    const actionContainer = document.getElementById('action_' + customerId);

                    if (action === 'lock') {
                        actionContainer.innerHTML = `
                            <button class="unlock-btn" onclick="toggleAccountStatus(${customerId}, 'unlock', '${customerName.replace(/'/g, "\\'")}')">
                                <i class="fas fa-unlock"></i>
                            </button>
                            <button class="delete-btn" onclick="deleteCustomer(${customerId}, '${customerName.replace(/'/g, "\\'")}')">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        `;
                    } else {
                        actionContainer.innerHTML = `
                            <button class="lock-btn" onclick="toggleAccountStatus(${customerId}, 'lock', '${customerName.replace(/'/g, "\\'")}')">
                                <i class="fas fa-lock"></i>
                            </button>
                            <button class="delete-btn" onclick="deleteCustomer(${customerId}, '${customerName.replace(/'/g, "\\'")}')">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        `;
                    }
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
            const confirmed = confirm(`⚠️ Are you sure you want to delete "${customerName}"? This action cannot be undone!`);
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

        // ========== IMAGE MODAL ZOOM FUNCTIONALITY ==========
        let currentZoom = 1;
        const zoomStep = 0.25;
        const maxZoom = 5;
        const minZoom = 1;

        function openLandmarkModal(imageSrc, captionText) {
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
            if (e.key === 'Escape') {
                closeLandmarkModal();
            } else if (e.key === '+' || e.key === '=') {
                zoomIn();
            } else if (e.key === '-') {
                zoomOut();
            } else if (e.key === '0') {
                resetZoom();
            }
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
            if (e.target === this) {
                closeLandmarkModal();
            }
        });

        document.getElementById('landmarkModalImage').addEventListener('click', function (e) {
            e.stopPropagation();
            if (currentZoom === 1) {
                zoomIn();
            } else {
                resetZoom();
            }
        });

        document.getElementById('landmarkModalImage').addEventListener('wheel', function (e) {
            e.preventDefault();
            if (e.deltaY < 0) {
                zoomIn();
            } else {
                zoomOut();
            }
        });

        window.addEventListener('resize', function () {
            if (document.getElementById('landmarkModal').style.display === 'block') {
                resetZoom();
            }
        });

        console.log('📱 Sidebar menu loaded - Left Side');
        console.log('📐 Desktop: Sidebar expanded | Mobile: Burger menu');
        console.log('👥 Registered Investors page loaded');
        console.log('🔐 Password hidden by default - click eye to show');
        console.log('🔑 authorize_access: <?php echo $authorizeAccess; ?>');
    </script>
</body>

</html>