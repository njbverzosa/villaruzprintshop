<?php
// web/paid_folder.php

session_start();

// ==============================================
// 1. FIX PATHS - config.php is in DB_Conn folder at root level
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 2. CHECK LOGIN STATUS
// ==============================================
function isLoggedIn() {
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
    // User not found in database, logout
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 4. USE $userData INSTEAD OF $user
// ==============================================
$user = $userData;


// Fetch distinct delivery_y_m values from for_deliveries table where status is PAID
$stmt = $pdo->prepare("SELECT delivery_m_y FROM for_deliveries WHERE delivery_m_y IS NOT NULL AND status = 'PAID' ORDER BY id DESC");
$stmt->execute();
$distinctMonths = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Prepare the deliveries by month array
$deliveriesByMonth = [];

foreach ($distinctMonths as $monthYear) {
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

// ==============================================
// FETCH SALES DATA FOR MONTHLY REPORT
// ==============================================

// Get all available years from paid deliveries
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        CASE 
            WHEN delivery_m_y LIKE '%2028' THEN '2028'
            WHEN delivery_m_y LIKE '%2027' THEN '2027'
            WHEN delivery_m_y LIKE '%2026' THEN '2026'
            WHEN delivery_m_y LIKE '%2025' THEN '2025'
            WHEN delivery_m_y LIKE '%2024' THEN '2024'
            ELSE SUBSTRING(delivery_m_y, -4)
        END as year
    FROM for_deliveries 
    WHERE status = 'PAID' AND delivery_m_y IS NOT NULL
    ORDER BY year DESC
");
$stmt->execute();
$availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get sales data grouped by month for each year
$salesData = [];
foreach ($availableYears as $year) {
    $stmt = $pdo->prepare("
        SELECT 
            delivery_m_y,
            SUM(total_amount) as monthly_sales
        FROM (
            SELECT 
                fd.delivery_m_y,
                osh.total_amount
            FROM for_deliveries fd
            JOIN order_status_history osh ON fd.delivery_number = osh.delivery_number
            WHERE fd.status = 'PAID' 
                AND fd.delivery_m_y LIKE :year_pattern
        ) as sales
        GROUP BY delivery_m_y
        ORDER BY 
            CASE 
                WHEN delivery_m_y LIKE 'January%' THEN 1
                WHEN delivery_m_y LIKE 'February%' THEN 2
                WHEN delivery_m_y LIKE 'March%' THEN 3
                WHEN delivery_m_y LIKE 'April%' THEN 4
                WHEN delivery_m_y LIKE 'May%' THEN 5
                WHEN delivery_m_y LIKE 'June%' THEN 6
                WHEN delivery_m_y LIKE 'July%' THEN 7
                WHEN delivery_m_y LIKE 'August%' THEN 8
                WHEN delivery_m_y LIKE 'September%' THEN 9
                WHEN delivery_m_y LIKE 'October%' THEN 10
                WHEN delivery_m_y LIKE 'November%' THEN 11
                WHEN delivery_m_y LIKE 'December%' THEN 12
                ELSE 13
            END
    ");
    $stmt->execute([':year_pattern' => "%$year"]);
    $salesData[$year] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Paid Folders | Villaruz Print Shop</title>
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

        .welcome h4 {
            font-size: 15px;
            font-weight: 600;
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

        /* SIMPLE COMPUTER-STYLE MODAL */
        .sales-modal-overlay {
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
        }

        .sales-modal {
            background: #f0f0f0;
            width: 90%;
            max-width: 500px;
            max-height: 85vh;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.5);
            border: 2px solid #3a6ea5;
            border-radius: 0;
            overflow: hidden;
            animation: modalAppear 0.2s ease;
            display: flex;
            flex-direction: column;
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

        .sales-modal-header {
            background: #3a6ea5;
            color: white;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        .sales-modal-header i {
            font-size: 14px;
        }

        .sales-modal-header span {
            flex: 1;
        }

        .close-modal-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 28px;
            height: 28px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-modal-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .sales-modal-content {
            padding: 20px;
            background: white;
            overflow-y: auto;
            flex: 1;
        }

        .year-selector-simple {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .year-btn-simple {
            padding: 6px 18px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            font-weight: 500;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .year-btn-simple:hover {
            background: #e2e8f0;
        }

        .year-btn-simple.active {
            background: #3a6ea5;
            color: white;
            border-color: #3a6ea5;
        }

        /* Simple list format: Jan. | 20,000 */
        .sales-list {
            width: 100%;
        }

        .sales-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eef2f6;
        }

        .sales-row:last-child {
            border-bottom: none;
        }

        .month-name {
            font-weight: 600;
            font-size: 15px;
            color: #1e293b;
        }

        .amount-value {
            font-weight: 700;
            font-size: 15px;
            color: #10b981;
        }

        .total-row-simple {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 12px 10px;
            border-radius: 8px;
        }

        .total-label {
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            color: #475569;
        }

        .total-amount {
            font-weight: 800;
            font-size: 18px;
            color: #f97316;
        }

        .no-data-simple {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        .no-data-simple i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #cbd5e1;
        }

        .sales-modal-footer {
            background: #e0e0e0;
            padding: 10px 15px;
            display: flex;
            justify-content: flex-end;
        }

        .modal-close-btn {
            padding: 6px 20px;
            font-family: 'Segoe UI', monospace;
            font-size: 12px;
            font-weight: 600;
            background: #e0e0e0;
            border: 1px solid #8a8a8a;
            cursor: pointer;
        }

        .modal-close-btn:hover {
            background: #c0c0c0;
        }

    

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
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

            .folders-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }

            .sales-modal {
                width: 95%;
                max-width: 95%;
            }

            .month-name,
            .amount-value {
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }

            
            .dashboard-header {
                padding: 20px 30px;
                border-radius: 10px;
            }

            .welcome h1 {
                font-size: 18px;
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
                    <h4><i class="fas fa-folder-open"></i> Paid Folders <i class="fas fa-chevron-right"></i></h4>
                </div>
            </div>

            <div class="folders-grid">
                <?php if (!empty($deliveriesByMonth)): ?>
                    <?php foreach ($deliveriesByMonth as $monthYear => $data): ?>
                        <div class="folder-item" onclick="viewMonth('<?= htmlspecialchars($monthYear) ?>')">
                            <div class="folder-icon"><i class="fas fa-folder"></i></div>
                            <div class="folder-name"><?= htmlspecialchars($monthYear) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <!-- Simple Sales Report Card -->
                <div class="folder-item" onclick="openSalesModal()">
                    <div class="folder-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="folder-name">Sales Report</div>
                </div>
            </div>
        </main>
    </div>

    <!-- SIMPLE MODAL: Monthly Sales in format "Jan. | 20,000" -->
    <div id="salesModal" class="sales-modal-overlay">
        <div class="sales-modal">
            <div class="sales-modal-header">
                <i class="fas fa-chart-line"></i>
                <span>MONTHLY SALES REPORT</span>
                <button class="close-modal-btn" onclick="closeSalesModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="sales-modal-content">
                <div class="year-selector-simple" id="yearSelectorSimple">
                    <?php foreach ($availableYears as $year): ?>
                        <button class="year-btn-simple <?= $year == max($availableYears) ? 'active' : '' ?>"
                            data-year="<?= $year ?>">
                            <?= $year ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div id="salesListContainer">
                    <div style="text-align:center; padding:30px;"><i class="fas fa-spinner fa-pulse"></i> Loading...
                    </div>
                </div>
            </div>
            <div class="sales-modal-footer">
                <button class="modal-close-btn" onclick="closeSalesModal()">CLOSE</button>
            </div>
        </div>
    </div>

    <?php
        include '../footer.php';
    ?>


    <script>
        // PHP data passed to JavaScript
        const salesData = <?php echo json_encode($salesData); ?>;
        const availableYears = <?php echo json_encode($availableYears); ?>;
        let currentSelectedYear = availableYears.length > 0 ? availableYears[0] : null;

        // Format number with commas
        function formatNumber(amount) {
            return parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Get short month name with dot (Jan., Feb., Mar., etc.)
        function getShortMonthWithDot(fullMonth) {
            const months = {
                'January': 'Jan.', 'February': 'Feb.', 'March': 'Mar.', 'April': 'Apr.',
                'May': 'May', 'June': 'Jun.', 'July': 'Jul.', 'August': 'Aug.',
                'September': 'Sep.', 'October': 'Oct.', 'November': 'Nov.', 'December': 'Dec.'
            };
            return months[fullMonth] || fullMonth.substring(0, 3) + '.';
        }

        // Render the sales list in format: Jan. | 20,000
        function renderSalesList(year) {
            const container = document.getElementById('salesListContainer');
            if (!container) return;

            const yearData = salesData[year] || [];

            const fullMonthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];

            // Create map of month -> sales
            const salesMap = {};
            yearData.forEach(item => {
                const monthName = item.delivery_m_y.split(' ')[0];
                salesMap[monthName] = parseFloat(item.monthly_sales) || 0;
            });

            let totalSales = 0;
            let listHtml = '<div class="sales-list">';

            for (let i = 0; i < fullMonthNames.length; i++) {
                const fullMonth = fullMonthNames[i];
                const amount = salesMap[fullMonth] || 0;
                totalSales += amount;
                const shortMonth = getShortMonthWithDot(fullMonth);

                listHtml += `
                <div class="sales-row">
                    <span class="month-name">${shortMonth}</span>
                    <span class="amount-value">${formatNumber(amount)}</span>
                </div>
            `;
            }

            listHtml += `
                <div class="total-row-simple">
                    <span class="total-label">TOTAL (${year})</span>
                    <span class="total-amount">${formatNumber(totalSales)}</span>
                </div>
            </div>
        `;

            container.innerHTML = listHtml;
        }

        // Modal functions
        const salesModal = document.getElementById('salesModal');

        function openSalesModal() {
            if (!salesModal) return;
            salesModal.style.display = 'flex';
            if (currentSelectedYear) {
                renderSalesList(currentSelectedYear);
            } else if (availableYears.length > 0) {
                currentSelectedYear = availableYears[0];
                renderSalesList(currentSelectedYear);
            } else {
                document.getElementById('salesListContainer').innerHTML = `
                <div class="no-data-simple">
                    <i class="fas fa-chart-simple"></i>
                    <p>No sales data available.</p>
                </div>
            `;
            }
            updateActiveYearButton(currentSelectedYear);
        }

        function closeSalesModal() {
            if (salesModal) salesModal.style.display = 'none';
        }

        function updateActiveYearButton(year) {
            document.querySelectorAll('#yearSelectorSimple .year-btn-simple').forEach(btn => {
                const btnYear = btn.getAttribute('data-year');
                if (btnYear == year) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }

        function switchYear(year) {
            currentSelectedYear = year;
            renderSalesList(year);
            updateActiveYearButton(year);
        }

        // Bind year buttons
        function bindYearButtons() {
            const btns = document.querySelectorAll('#yearSelectorSimple .year-btn-simple');
            btns.forEach(btn => {
                btn.removeEventListener('click', yearClickHandler);
                btn.addEventListener('click', yearClickHandler);
            });
        }

        function yearClickHandler(e) {
            const year = e.currentTarget.getAttribute('data-year');
            if (year) switchYear(year);
        }

        // Close modal when clicking outside
        if (salesModal) {
            salesModal.addEventListener('click', function (e) {
                if (e.target === salesModal) closeSalesModal();
            });
        }

        // Close on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && salesModal && salesModal.style.display === 'flex') {
                closeSalesModal();
            }
        });

        // Burger menu functionality
        const burgerBtn = document.getElementById('burgerBtn');
        const menuOverlayElem = document.getElementById('menuOverlay');
        const sideMenuElem = document.querySelector('.side-menu');

        if (burgerBtn && menuOverlayElem && sideMenuElem) {
            burgerBtn.addEventListener('click', () => {
                sideMenuElem.classList.add('open');
                menuOverlayElem.classList.add('active');
            });
            menuOverlayElem.addEventListener('click', () => {
                sideMenuElem.classList.remove('open');
                menuOverlayElem.classList.remove('active');
            });
        }

        function viewMonth(monthYear) {
            window.location.href = 'paid_folder_with.php?month=' + encodeURIComponent(monthYear);
        }

        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function () {
            bindYearButtons();
            if (currentSelectedYear) {
                renderSalesList(currentSelectedYear);
                updateActiveYearButton(currentSelectedYear);
            }
            if (!availableYears.length) {
                const container = document.getElementById('salesListContainer');
                if (container) {
                    container.innerHTML = `
                    <div class="no-data-simple">
                        <i class="fas fa-folder-open"></i>
                        <p>No paid sales data available.</p>
                    </div>
                `;
                }
            }
        });
    </script>
</body>

</html>