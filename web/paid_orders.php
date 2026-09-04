<?php
// web/paid_orders.php

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

// Get delivery_number from URL
$selectedDeliveryNumber = isset($_GET['delivery_number']) ? $_GET['delivery_number'] : '';

if (empty($selectedDeliveryNumber)) {
    echo '<div class="empty-state">
            <i class="fas fa-exclamation-triangle"></i>
            <p>No delivery number selected. Please go back and try again.</p>
            <a href="pending_folder.php" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px;">Go Back</a>
          </div>';
    exit;
}

// First, check if delivery number exists in for_deliveries
$stmt = $pdo->prepare("SELECT * FROM for_deliveries WHERE delivery_number = ?");
$stmt->execute([$selectedDeliveryNumber]);
$deliveryInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deliveryInfo) {
    echo '<div class="empty-state">
            <i class="fas fa-search"></i>
            <p>Delivery #' . htmlspecialchars($selectedDeliveryNumber) . ' not found in deliveries.</p>
            <a href="pending_folder.php" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px;">Go Back</a>
          </div>';
    exit;
}

// Fetch order items from order_status_history for this delivery number
$stmt = $pdo->prepare("SELECT * FROM order_status_history WHERE delivery_number = ?");
$stmt->execute([$selectedDeliveryNumber]);
$orderItems = $stmt->fetchAll();

// Debug: Check if table has data but different column name
if (empty($orderItems)) {
    // Try to get the first row to see column names
    $stmt = $pdo->query("SELECT * FROM order_status_history LIMIT 1");
    $columns = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($columns) {
        $availableColumns = array_keys($columns);
        // Check if there's a similar column name
        $possibleColumns = ['delivery_num', 'delivery_id', 'order_number', 'order_id'];
        foreach ($possibleColumns as $col) {
            if (in_array($col, $availableColumns)) {
                // Try with the found column name
                $stmt = $pdo->prepare("SELECT * FROM order_status_history WHERE $col = ?");
                $stmt->execute([$selectedDeliveryNumber]);
                $orderItems = $stmt->fetchAll();
                if (!empty($orderItems)) {
                    break;
                }
            }
        }
    }
}

$customerName = $deliveryInfo['ordered_by'] ?? '';
$monthYear = $deliveryInfo['delivery_m_y'] ?? '';
$deliveryNumber = $deliveryInfo['delivery_number'] ?? '';

// Calculate total amount
$totalAmount = 0;
foreach ($orderItems as $item) {
    $totalAmount += floatval($item['total_amount'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Paid Orders | <?= htmlspecialchars($selectedDeliveryNumber) ?> | Villaruz Print Shop</title>
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
            flex-wrap: wrap;
            gap: 15px;
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

        .welcome h4 a {
            text-decoration: none;
            color: #0f172a;
            transition: color 0.3s;
        }

        .welcome h4 a:hover {
            color: #f59e0b;
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

        

        .orders-container {
            background: #ffffff;
            border-radius: 5px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .delivery-group {
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            overflow: hidden;
        }

        .delivery-header {
            background: black;
            color: white;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .delivery-header i {
            margin-right: 10px;
        }

        .delivery-header .customer-info {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        @media (max-width: 480px) {
            .delivery-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px 15px;
                gap: 6px;
                font-size: 12px;
            }

            .delivery-header .customer-info {
                font-size: 12px;
                width: 100%;
            }
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table tr {
            border-bottom: 1px solid #f1f5f9;
        }

        .orders-table tr:last-child {
            border-bottom: none;
        }

        .orders-table td {
            padding: 12px 15px;
            color: #1e293b;
            font-size: 13px;
        }

        .orders-table td:first-child {
            font-weight: 500;
        }

        .orders-table tr:hover {
            background: #f8fafc;
        }

        .total-row {
            background: #fefce8;
            font-weight: 600;
        }

        .total-row td {
            border-top: 1px solid #e2e8f0;
            padding: 12px 15px;
        }

        .receipt-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            padding: 20px;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .receipt-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .receipt-actions {
                padding: 12px;
                gap: 8px;
            }
        }

        .receipt-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 24px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            font-size: 14px;
            flex: 1;
            min-width: 150px;
        }

        @media (max-width: 768px) {
            .receipt-btn {
                width: 100%;
                padding: 10px 16px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .receipt-btn {
                padding: 8px 12px;
                font-size: 12px;
                min-width: unset;
            }

            .receipt-btn i {
                font-size: 14px;
            }
        }

        .receipt-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .delivery-receipt {
            background: #3b82f6;
        }

        .delivery-receipt:hover {
            background: #2563eb;
        }

        .billing-receipt {
            background: #10b981;
        }

        .billing-receipt:hover {
            background: #059669;
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

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
                padding-top: 20px;
            }

            .orders-table td {
                padding: 8px 10px;
                font-size: 11px;
            }

            .delivery-header {
                font-size: 12px;
                padding: 10px 15px;
            }

            .welcome h4 {
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
                font-size: 13px;
            }

            .orders-table td {
                padding: 6px 8px;
                font-size: 10px;
            }

            .orders-table td:first-child {
                font-size: 10px;
            }

            .total-row td {
                font-size: 11px;
                padding: 8px 10px;
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
                            <a href="paid_folder.php"><i class="fas fa-folder-open"></i> Paid Folders <i
                                    class="fas fa-chevron-right"></i></a>
                            <?php if ($monthYear): ?>
                                <a href="paid_folder_with.php?month=<?= urlencode($monthYear) ?>">
                                    <i class="fas fa-folder-open"></i> <?= htmlspecialchars($monthYear) ?>
                                </a>
                            <?php endif; ?>
                            <i class="fas fa-chevron-right"></i>
                            <i class="fas fa-folder-open"></i> <?= htmlspecialchars($deliveryNumber) ?>
                        </h4>
                    </div>
                </div>
            </div>

            <?php if (empty($orderItems)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No order items found for this delivery.</p>
                </div>
            <?php else: ?>
                <div class="orders-container">
                    <div class="delivery-group">
                        <div class="delivery-header">
                            <div class="customer-info">
                                <i class="fas fa-user"></i> Customer: <?= htmlspecialchars($customerName) ?>
                            </div>
                        </div>
                        <div class="orders-table-container">
                            <table class="orders-table">
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($item['pieces'] ?? '0') ?></td>
                                        <td><?= htmlspecialchars($item['unit'] ?? 'N/A') ?></td>
                                        <td>₱ <?= number_format(floatval($item['total_amount'] ?? 0), 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="3" style="text-align: right; font-weight: 600;">TOTAL:</td>
                                    <td style="font-weight: 600;">₱ <?= number_format($totalAmount, 2) ?></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Receipt Icons -->
                        <div class="receipt-actions">
                            <button class="receipt-btn delivery-receipt" onclick="generateReceipt('delivery')">
                                <i class="fas fa-truck"></i> Delivery Receipt
                            </button>
                            <button class="receipt-btn billing-receipt" onclick="generateReceipt('billing')">
                                <i class="fas fa-file-invoice-dollar"></i> Billing Receipt
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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

        // ========== EXISTING FUNCTIONS ==========
        function generateReceipt(type) {
            const deliveryNumber = '<?= htmlspecialchars($selectedDeliveryNumber) ?>';
            const customerName = '<?= htmlspecialchars($customerName) ?>';
            const monthYear = '<?= htmlspecialchars($monthYear) ?>';
            const totalAmount = '<?= number_format($totalAmount, 2) ?>';

            // Get order items data
            const orderItems = <?= json_encode($orderItems) ?>;

            // Store data in sessionStorage or pass via URL
            if (type === 'delivery') {
                window.location.href = '../delivery_receipt.php?delivery_number=' + encodeURIComponent(deliveryNumber);
            } else if (type === 'billing') {
                window.location.href = '../billing_receipt.php?delivery_number=' + encodeURIComponent(deliveryNumber);
            }
        }

        console.log('📱 Sidebar menu loaded - Left Side');
        console.log('📐 Desktop: Sidebar expanded | Mobile: Burger menu');
    </script>
</body>

</html>