<?php
// web/pending_folder.php

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
    // User not found in database, logout
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 4. USE $userData INSTEAD OF $user
// ==============================================
$user = $userData;

// Fetch distinct delivery_y_m values from for_deliveries table where status is PENDING
$stmt = $pdo->prepare("SELECT delivery_m_y FROM for_deliveries WHERE delivery_m_y IS NOT NULL AND delivery_m_y != '' AND status IN ('PENDING', 'PACKING', 'SHIPPED', 'OFD', 'DELIVERED') AND total_amount < 500 ORDER BY id DESC");
$stmt->execute();
$distinctMonths = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Prepare the deliveries by month array
$deliveriesByMonth = [];

foreach ($distinctMonths as $monthYear) {
    // Split month and year for potential separate display
    $parts = explode(' ', $monthYear);
    $monthOnly = $parts[0] ?? $monthYear;
    $yearOnly = $parts[1] ?? '';

    $deliveriesByMonth[$monthYear] = [
        'deliveries' => [],
        'customers' => [],
        'month' => $monthOnly,
        'year' => $yearOnly,
        'raw_value' => $monthYear
    ];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Pending Folders | Villaruz Print Shop</title>
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

        /* ========== MAIN CONTENT ========== */
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

        .welcome h4 i {
            margin-right: 5px;
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

        
        /* ========== FOLDERS GRID ========== */
        .folders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 5px;
            margin-top: 20px;
        }

        .folder-item {
            border-radius: 5px;
            padding: 25px 20px;
            width: 190px;
            background: #ffffff;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .folder-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            background: whitesmoke;
        }

        .folder-icon i {
            font-size: 48px;
            color: #f59e0b;
            margin-bottom: 15px;
        }

        .folder-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
            word-break: break-word;
        }

        .customer-list {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            text-align: left;
        }

        .customer-name {
            font-size: 11px;
            color: #64748b;
            padding: 3px 5px;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .customer-name i {
            font-size: 10px;
            margin-right: 5px;
            color: #f59e0b;
        }

        .more-indicator {
            font-size: 10px;
            color: #f59e0b;
            padding: 3px 5px;
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }

        .empty-state i {
            font-size: 80px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 16px;
            color: #64748b;
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
                padding-top: 20px;
            }

            .folders-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }

            .folder-icon {
                font-size: 60px;
            }

            .folder-name {
                font-size: 14px;
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

            .folders-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 10px;
            }

            .folder-item {
                padding: 20px 15px;
                width: 100%;
            }

            .folder-icon i {
                font-size: 36px;
            }

            .folder-name {
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
                <?php
                include 'sidebar.php';
                ?>
            </div>
        </div>

        <main class="main-content">
            <div class="dashboard-header">
                <div class="header-left">
                    <!-- Burger Button (Mobile Only) -->
                    <button class="burger-btn" id="burgerBtn" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="welcome">
                        <h4>
                            <i class="fas fa-folder-open"></i> Outside Folders <i class="fas fa-chevron-right"></i>
                        </h4>
                    </div>
                </div>
            </div>

            <div class="folders-grid">
                <?php if (empty($deliveriesByMonth)): ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-folder-open"></i>
                        <p>No outside folders found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($deliveriesByMonth as $monthYear => $data): ?>
                        <div class="folder-item" onclick="viewMonth('<?= htmlspecialchars($monthYear) ?>')">
                            <div class="folder-icon">
                                <i class="fas fa-folder"></i>
                            </div>
                            <div class="folder-name">
                                <?= htmlspecialchars($monthYear) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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

        // ========== VIEW MONTH FUNCTION ==========
        function viewMonth(monthYear) {
            window.location.href = 'pending_folder_with.php?month=' + encodeURIComponent(monthYear);
        }

        console.log('📱 Sidebar menu loaded - Left Side');
        console.log('📐 Desktop: Sidebar expanded | Mobile: Burger menu');
    </script>
</body>

</html>