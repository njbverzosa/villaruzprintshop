<?php
// web/paid_orders.php

session_start();

require_once __DIR__ . '/../DB_Conn/config.php';

if (isset($userData['f_name']) && !isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = $userData['f_name'];
}

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

$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

$userData = null;

if ($userRole === 'Investor') {
    $stmt = $pdo->prepare("
        SELECT id, acc_number, f_name, email, phone_number, user_name,
               business_name, business_permit, profile
        FROM investors
        WHERE id = ?
    ");
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
// SELECTED DAY + MONTH FROM URL
// ==============================================
$selectedDay   = isset($_GET['day'])   ? trim($_GET['day'])   : '';
$selectedMonth = isset($_GET['month']) ? trim($_GET['month']) : '';

if ($selectedDay === '' || $selectedMonth === '') {
    echo '<div class="empty-state">
            <i class="fas fa-exclamation-triangle"></i>
            <p>No day/month selected. Please go back and try again.</p>
            <a href="paid_folder.php" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px;">Go Back</a>
          </div>';
    exit;
}

// ==============================================
// FETCH ALL PAID SALES FOR THIS INVESTOR
// ==============================================
$stmt = $pdo->prepare("
    SELECT *
    FROM investors_sales
    WHERE status = 'PAID'
      AND acc_number = :acc_number
      AND SUBSTRING_INDEX(date_time_sold, ' ', 1) = :day
      AND SUBSTRING_INDEX(SUBSTRING_INDEX(date_time_sold, ' ', 2), ' ', -1) = :month
    ORDER BY id DESC
");
$stmt->execute([
    ':acc_number' => $accNumber,
    ':day'        => $selectedDay,
    ':month'      => $selectedMonth,
]);
$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$totalAmount = 0;
foreach ($orderItems as $item) {
    $totalAmount += floatval($item['total_amount'] ?? 0);
}

$totalPieces = 0;
foreach ($orderItems as $item) {
    $totalPieces += intval($item['pieces'] ?? 0);
}

// Header info
$customerName = $user['f_name'] ?? ($user['user_name'] ?? 'Customer');
$dateLabel    = $selectedDay . ' ' . $selectedMonth;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Paid Orders | <?= htmlspecialchars($dateLabel) ?> | Villaruz Print Shop</title>
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

        /* ========== TABLE ========== */
        .orders-table-container {
            width: 100%;
            overflow-x: auto;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .orders-table thead th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .orders-table thead th:nth-child(1) { width: 40%; }
        .orders-table thead th:nth-child(2) { width: 12%; }
        .orders-table thead th:nth-child(3) { width: 12%; }
        .orders-table thead th:nth-child(4) { width: 16%; }
        .orders-table thead th:nth-child(5) { width: 20%; }

        .orders-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
        }

        .orders-table tbody tr:last-child {
            border-bottom: none;
        }

        .orders-table td {
            padding: 12px 15px;
            color: #1e293b;
            font-size: 13px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .orders-table tbody tr:hover {
            background: #f8fafc;
        }

        .total-row {
            background: #fefce8;
            font-weight: 600;
        }

        .total-row td {
            border-top: 2px solid #e2e8f0;
            padding: 12px 15px;
            font-weight: 600;
        }

        .total-row .total-label {
            text-align: right;
            text-transform: uppercase;
            font-size: 12px;
            color: #475569;
            letter-spacing: 0.5px;
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

            .orders-table thead th {
                padding: 8px 10px;
                font-size: 10px;
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

            .orders-table thead th {
                padding: 6px 8px;
                font-size: 9px;
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
                        <h4>
                            <a href="paid_folder.php"><i class="fas fa-folder-open"></i> Sales of Months <i
                                    class="fas fa-chevron-right"></i></a>
                            <a href="paid_folder_with.php?month=<?= urlencode($selectedMonth) ?>">
                                <i class="fas fa-folder-open"></i> Sales of  <?= htmlspecialchars($selectedMonth) ?>
                            </a>
                            <i class="fas fa-chevron-right"></i>
                            <i class="fas fa-folder-open"></i> Sales of  <?= htmlspecialchars($dateLabel) ?>
                        </h4>
                    </div>
                </div>
            </div>

            <?php if (empty($orderItems)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No paid orders found for <?= htmlspecialchars($dateLabel) ?>.</p>
                </div>
            <?php else: ?>
                <div class="orders-container">
                    <div class="delivery-group">
                        <div class="delivery-header">
                            <div>
                                <i class="fas fa-calendar-day"></i> <?= htmlspecialchars($dateLabel) ?>
                            </div>
                        </div>
                        <div class="orders-table-container">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Pieces</th>
                                        <th>Unit</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['product_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($item['pieces'] ?? '0') ?></td>
                                            <td><?= htmlspecialchars($item['unit'] ?? 'N/A') ?></td>
                                            <td>₱ <?= number_format(floatval($item['selling_price'] ?? 0), 2) ?></td>
                                            <td>₱ <?= number_format(floatval($item['total_amount'] ?? 0), 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <!-- TOTAL ROW: 5 cells to match the 5 columns -->
                                    <tr class="total-row">
                                        <td class="total-label">TOTAL:</td>
                                        <td><?= $totalPieces ?></td>
                                        <td></td>
                                        <td></td>
                                        <td>₱ <?= number_format($totalAmount, 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
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

        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            const arrowId = dropdownId.replace('Dropdown', 'Arrow');
            const arrow = document.getElementById(arrowId);

            if (dropdown && arrow) {
                dropdown.classList.toggle('show');
                arrow.classList.toggle('rotated');
            }
        }

        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                if (isSidebarOpen) closeSidebar();
                sidebarWrapper.classList.remove('open');
                menuOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        console.log('📱 Sidebar menu loaded - Left Side');
        console.log('📐 Desktop: Sidebar expanded | Mobile: Burger menu');
    </script>
</body>

</html>