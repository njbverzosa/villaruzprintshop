<?php
// web/paid_folder_with.php
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
// SELECTED MONTH (e.g. "September")
// ==============================================
$selectedMonth = isset($_GET['month']) ? trim($_GET['month']) : '';

if ($selectedMonth === '') {
    header('Location: paid_folder.php');
    exit;
}

// ==============================================
// FETCH ALL PAID SALES IN THAT MONTH FOR THIS INVESTOR ONLY
// Scoped by acc_number
// date_time_sold format: "21 September 2026 10:35 AM"
// ==============================================
$stmt = $pdo->prepare("
    SELECT *
    FROM investors_sales
    WHERE status = 'PAID'
      AND acc_number = :acc_number
      AND SUBSTRING_INDEX(SUBSTRING_INDEX(date_time_sold, ' ', 2), ' ', -1) = :month
    ORDER BY id DESC
");
$stmt->execute([
    ':acc_number' => $accNumber,
    ':month'      => $selectedMonth,
]);
$allSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by day number
$salesByDay = [];
foreach ($allSales as $sale) {
    $day = trim(explode(' ', $sale['date_time_sold'])[0] ?? '');
    if ($day === '') continue;

    if (!isset($salesByDay[$day])) {
        $salesByDay[$day] = [
            'day'   => $day,
            'sales' => [],
            'total' => 0,
        ];
    }
    $salesByDay[$day]['sales'][] = $sale;
    $salesByDay[$day]['total'] += floatval($sale['total_amount'] ?? 0);
}

// Sort days descending (30, 29, 28 …)
krsort($salesByDay, SORT_NUMERIC);

// ==============================================
// TOTAL SALES FOR THE MONTH (this investor only)
// ==============================================
$totalAmountForMonth = 0;
foreach ($allSales as $sale) {
    $totalAmountForMonth += floatval($sale['total_amount'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Paid Folders | <?= htmlspecialchars($selectedMonth) ?> | Villaruz Print Shop</title>
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

        /* Computer-Style Modal Dialog */
        .system-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 10000;
            display: none;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', 'Poppins', system-ui, monospace;
        }

        .system-modal {
            background: #f0f0f0;
            min-width: 380px;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.5);
            border: 2px solid #3a6ea5;
            border-radius: 0;
            overflow: hidden;
            animation: modalAppear 0.2s ease;
            display: flex;
            flex-direction: column;
        }

        .modal-summary {
            max-width: 500px;
        }

        @keyframes modalAppear {
            from {
                transform: scale(0.95);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .system-modal-header {
            background: #3a6ea5;
            color: white;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
            font-family: 'Segoe UI', monospace;
        }

        .system-modal-header i {
            font-size: 14px;
        }

        .system-modal-header span {
            flex: 1;
        }

        .system-modal-content {
            padding: 20px;
            background: white;
            overflow-y: auto;
            flex: 1;
        }

        .system-modal-message {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            color: #000;
            line-height: 1.5;
        }

        .system-modal-message i {
            font-size: 32px;
        }

        .system-modal-message.info i {
            color: #2196f3;
        }

        .system-modal-message .message-text {
            flex: 1;
        }

        .system-modal-footer {
            background: #e0e0e0;
            padding: 10px 15px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .system-modal-btn {
            padding: 6px 20px;
            font-family: 'Segoe UI', monospace;
            font-size: 12px;
            font-weight: 600;
            background: #e0e0e0;
            border: 1px solid #8a8a8a;
            cursor: pointer;
            transition: all 0.1s ease;
            min-width: 70px;
        }

        .system-modal-btn:hover {
            background: #c0c0c0;
            border-color: #666;
        }

        .system-modal-btn:active {
            transform: translateY(1px);
        }

        .system-modal-btn.primary {
            background: #3a6ea5;
            border-color: #2a4d73;
            color: white;
        }

        .system-modal-btn.primary:hover {
            background: #2a5a8a;
        }

        .amount-display {
            font-size: 24px;
            font-weight: 700;
            color: #10b981;
            text-align: center;
            margin-top: 10px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .summary-card {
            padding: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }

        .summary-label i {
            width: 30px;
            color: #3b82f6;
            margin-right: 10px;
        }

        .summary-value {
            font-size: 18px;
            font-weight: 700;
        }

        .summary-value.sales {
            color: #10b981;
        }

        .summary-value.grand {
            color: #8b5cf6;
            font-size: 22px;
        }

        .divider-line {
            height: 2px;
            background: linear-gradient(90deg, #3b82f6, #10b981, #8b5cf6);
            margin: 15px 0;
        }

        .grand-total-row {
            background: #f8fafc;
            border-radius: 8px;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
                padding-top: 20px;
            }

            .folders-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }

            .folder-item {
                padding: 20px 15px;
                width: auto;
            }

            .folder-icon i {
                font-size: 40px;
            }

            .folder-name {
                font-size: 13px;
            }

            .summary-label {
                font-size: 14px;
            }

            .summary-value {
                font-size: 16px;
            }

            .summary-value.grand {
                font-size: 18px;
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

            .folders-grid {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                gap: 10px;
            }

            .folder-item {
                padding: 15px 12px;
                width: auto;
            }

            .folder-icon i {
                font-size: 32px;
            }

            .folder-name {
                font-size: 11px;
            }

            .system-modal {
                max-width: 98%;
                min-width: unset;
            }

            .system-modal-content {
                padding: 15px;
            }

            .system-modal-header {
                font-size: 12px;
                padding: 8px 12px;
            }

            .summary-row {
                padding: 10px;
            }

            .summary-label {
                font-size: 12px;
            }

            .summary-value {
                font-size: 14px;
            }

            .summary-value.grand {
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
                        <h4>
                            <a href="paid_folder.php"><i class="fas fa-folder-open"></i> Sales of Months <i
                                    class="fas fa-chevron-right"></i></a>
                            <?php if ($selectedMonth): ?>
                                <i class="fas fa-folder-open"></i> Sales of 
                                <?= htmlspecialchars($selectedMonth) ?>
                            <?php endif; ?>
                        </h4>
                    </div>
                </div>
            </div>

            <div class="folders-grid">
                <?php if (empty($salesByDay)): ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-folder-open"></i>
                        <p>No PAID sales found for <?= htmlspecialchars($selectedMonth) ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($salesByDay as $day => $group): ?>
                        <div class="folder-item"
                            onclick="viewDay('<?= htmlspecialchars($day, ENT_QUOTES) ?>', '<?= htmlspecialchars($selectedMonth, ENT_QUOTES) ?>')">
                            <div class="folder-icon">
                                <i class="fas fa-folder"></i>
                            </div>
                            <div class="folder-name">
                                <?= htmlspecialchars($day) ?> <?= htmlspecialchars($selectedMonth) ?>
                                <small style="display:block; font-size:11px; color:#666; margin-top: 15px;">
                                    <?= count($group['sales']) ?> order<?= count($group['sales']) === 1 ? '' : 's' ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Total Amount Card -->
                <div class="folder-item" onclick="showTotalAmountModal()">
                    <div class="folder-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="folder-name">
                        Total Sales
                    </div>
                </div>

               
            </div>
        </main>
    </div>

    <!-- Total Amount Modal -->
    <div id="totalAmountModal" class="system-modal-overlay">
        <div class="system-modal">
            <div class="system-modal-header">
                <i class="fas fa-coins"></i>
                <span>TOTAL SALES REPORT</span>
            </div>
            <div class="system-modal-content">
                <div class="system-modal-message info">
                    <i class="fas fa-chart-line"></i>
                    <div class="message-text">
                        Total sales for <strong><?= htmlspecialchars($selectedMonth) ?></strong>
                        <div class="amount-display">
                            ₱<?= number_format($totalAmountForMonth, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="system-modal-footer">
                <button class="system-modal-btn primary" onclick="closeTotalAmountModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Monthly Summary Modal -->
    <div id="summaryModal" class="system-modal-overlay">
        <div class="system-modal modal-summary">
            <div class="system-modal-header">
                <i class="fas fa-chart-pie"></i>
                <span>MONTHLY SUMMARY - <?= htmlspecialchars($selectedMonth) ?></span>
            </div>
            <div class="system-modal-content">
                <div class="summary-card">
                    <div class="summary-row">
                        <div class="summary-label">
                            <i class="fas fa-coins"></i> Sales Total
                        </div>
                        <div class="summary-value sales">
                            ₱<?= number_format($totalAmountForMonth, 2) ?>
                        </div>
                    </div>
                    <div class="divider-line"></div>
                    <div class="summary-row grand-total-row">
                        <div class="summary-label">
                            <i class="fas fa-calculator"></i> GRAND TOTAL
                        </div>
                        <div class="summary-value grand">
                            ₱<?= number_format($totalAmountForMonth, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="system-modal-footer">
                <button class="system-modal-btn primary" onclick="closeSummaryModal()">CLOSE</button>
            </div>
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

        // ========== DATA FROM PHP ==========
        const selectedMonth = '<?= htmlspecialchars($selectedMonth, ENT_QUOTES) ?>';
        const currentAccNumber = '<?= htmlspecialchars($accNumber, ENT_QUOTES) ?>';
        let totalSalesValue = <?= $totalAmountForMonth ?>;

        // ==========================================
        // DAY FOLDER CLICK → opens the day's orders
        // ==========================================
        function viewDay(day, month) {
            window.location.href = 'paid_orders.php?day=' + encodeURIComponent(day) +
                '&month=' + encodeURIComponent(month) +
                '&acc_number=' + encodeURIComponent(currentAccNumber);
        }

        // ========== MODALS ==========
        function showTotalAmountModal() {
            document.getElementById('totalAmountModal').style.display = 'flex';
        }
        function closeTotalAmountModal() {
            document.getElementById('totalAmountModal').style.display = 'none';
        }

        function showSummaryModal() {
            document.getElementById('summaryModal').style.display = 'flex';
        }
        function closeSummaryModal() {
            document.getElementById('summaryModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function (event) {
            const totalModal = document.getElementById('totalAmountModal');
            if (event.target === totalModal) closeTotalAmountModal();

            const summaryModal = document.getElementById('summaryModal');
            if (event.target === summaryModal) closeSummaryModal();
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const totalModal = document.getElementById('totalAmountModal');
                if (totalModal.style.display === 'flex') closeTotalAmountModal();

                const summaryModal = document.getElementById('summaryModal');
                if (summaryModal.style.display === 'flex') closeSummaryModal();
            }
        });

        console.log('📱 Sidebar menu loaded - Left Side');
        console.log('📐 Desktop: Sidebar expanded | Mobile: Burger menu');
    </script>
</body>

</html>