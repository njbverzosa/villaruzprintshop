<?php
// web/location_with.php
session_start();

require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 1. CHECK LOGIN STATUS
// ==============================================
function isLoggedIn()
{
    return isset($_SESSION['user_role']) &&
        isset($_SESSION['user_id']) &&
        isset($_SESSION['acc_number']);
}

if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Please login first to access the shop.';
    header('Location: ../admins-portal.php');
    exit;
}

// ==============================================
// 2. GET USER DATA
// ==============================================
$userRole  = $_SESSION['user_role'];
$userId    = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

$userData = null;
if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, role, user_name, authorize_access FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../admins-portal.php');
    exit;
}

$user = $userData;

// ==============================================
// 3. ✅ READ ADDRESS FROM URL
// ==============================================
$selectedAddress = isset($_GET['address']) ? trim($_GET['address']) : '';

if ($selectedAddress === '') {
    header('Location: location.php');
    exit;
}

// ==============================================
// 4. ✅ FETCH ALL PAID DELIVERIES FOR THE ADDRESS
// ==============================================
$stmt = $pdo->prepare("
    SELECT *
    FROM for_deliveries
    WHERE delivery_address = ?
      AND status = 'PAID'
    ORDER BY id DESC
");
$stmt->execute([$selectedAddress]);
$allDeliveries = $stmt->fetchAll();

// ==============================================
// 5. ✅ GROUP PAID DELIVERIES BY CUSTOMER (ordered_by)
// ==============================================
$deliveriesByCustomer = [];

foreach ($allDeliveries as $delivery) {
    $customer = trim($delivery['ordered_by'] ?? '');
    if ($customer === '') $customer = 'Unknown Customer';

    if (!isset($deliveriesByCustomer[$customer])) {
        $deliveriesByCustomer[$customer] = [
            'customer'         => $customer,
            'deliveries'       => [],
            'delivery_numbers' => [],
        ];
    }

    $deliveriesByCustomer[$customer]['deliveries'][] = $delivery;

    $dn = $delivery['delivery_number'] ?? '';
    if ($dn !== '' && !in_array($dn, $deliveriesByCustomer[$customer]['delivery_numbers'], true)) {
        $deliveriesByCustomer[$customer]['delivery_numbers'][] = $dn;
    }
}

// ==============================================
// 6. ✅ TOTAL PAID AMOUNT FOR THE ADDRESS
//    (one sum per unique delivery_number)
// ==============================================
$totalPaidAmount = 0;
$seenDeliveries  = [];

foreach ($allDeliveries as $delivery) {
    $dn = $delivery['delivery_number'] ?? '';
    if ($dn === '' || in_array($dn, $seenDeliveries, true)) continue;
    $seenDeliveries[] = $dn;

    $stmt2 = $pdo->prepare("SELECT SUM(total_amount) AS total FROM order_status_history WHERE delivery_number = ?");
    $stmt2->execute([$dn]);
    $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    $totalPaidAmount += floatval($row2['total'] ?? 0);
}

// No contracts in the paid-address view
$contracts          = [];
$totalContractValue = 0;
$grandTotal         = $totalPaidAmount;

// Aliases kept for the template (breadcrumb + summary modal safety)
$selectedMonth     = $selectedAddress;
$selectedMonthName = $selectedAddress;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Paid Deliveries | <?= htmlspecialchars($selectedAddress) ?> | Villaruz Print Shop</title>
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

        /* ========== FOLDERS GRID ========== */
        .folders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .folder-item {
            border-radius: 5px;
            padding: 25px 20px;
            background: #ffffff;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: center;
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
            line-height: 1.4;
        }

        .folder-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 6px;
            font-weight: 500;
        }

        .folder-sub i {
            font-size: 10px;
            margin-right: 4px;
            color: #10b981;
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

        /* ========== SYSTEM MODAL ========== */
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

        @keyframes modalAppear {
            from { transform: scale(0.95); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
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

        .system-modal-header i { font-size: 14px; }
        .system-modal-header span { flex: 1; }

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

        .system-modal-message i { font-size: 32px; }

        .system-modal-message.info i    { color: #2196f3; }
        .system-modal-message.success i { color: #10b981; }
        .system-modal-message.error i   { color: #ef4444; }
        .system-modal-message.warning i { color: #f59e0b; }

        .system-modal-message .message-text { flex: 1; }

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
            background: #ecfdf5;
            border-radius: 8px;
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
            }

            .folder-icon i {
                font-size: 40px;
            }

            .folder-name {
                font-size: 13px;
            }

            .dashboard-header {
                padding: 15px 20px;
            }

            .system-modal {
                min-width: unset;
                max-width: 95%;
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
                            <a href="location.php"><i class="fas fa-folder-open"></i> Paid Locations</a>
                            <i class="fas fa-chevron-right"></i>
                            <i class="fas fa-folder-open"></i>
                            <?= htmlspecialchars($selectedAddress) ?>
                        </h4>
                    </div>
                </div>
            </div>

            <div class="folders-grid">
                <?php if (empty($deliveriesByCustomer)): ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-folder-open"></i>
                        <p>No PAID deliveries for <strong><?= htmlspecialchars($selectedAddress) ?></strong></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($deliveriesByCustomer as $customerData): ?>
                        <?php
                        $firstDeliveryNumber = $customerData['delivery_numbers'][0] ?? '';
                        $itemCount           = count($customerData['deliveries']);
                        ?>
                        <div class="folder-item"
                            onclick="viewCustomerOrders(<?= htmlspecialchars(json_encode($firstDeliveryNumber), ENT_QUOTES) ?>)">
                            <div class="folder-icon">
                                <i class="fas fa-folder"></i>
                            </div>
                            <div class="folder-name">
                                <?= htmlspecialchars($customerData['customer']) ?>
                            </div>
                            <div class="folder-sub">
                                <i class="fas fa-check-circle"></i>
                                <?= $itemCount ?> PAID item<?= $itemCount === 1 ? '' : 's' ?>
                            </div>
                            <?php if ($firstDeliveryNumber !== ''): ?>
                                <div style="font-size: 10px; color: #94a3b8; margin-top: 4px;">
                                    #<?= htmlspecialchars($firstDeliveryNumber) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Total Paid summary card -->
                <div class="folder-item" onclick="showTotalPendingModal()">
                    <div class="folder-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="folder-name">
                        Total Paid
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- ============================================================
         TOTAL PAID MODAL
         ============================================================ -->
    <div id="totalPendingModal" class="system-modal-overlay">
        <div class="system-modal">
            <div class="system-modal-header">
                <i class="fas fa-coins"></i>
                <span>TOTAL PAID REPORT</span>
            </div>
            <div class="system-modal-content">
                <div class="system-modal-message info">
                    <i class="fas fa-chart-line"></i>
                    <div class="message-text">
                        Total PAID amount for
                        <strong><?= htmlspecialchars($selectedAddress) ?></strong>
                        <div class="amount-display">
                            ₱<?= number_format($totalPaidAmount, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="system-modal-footer">
                <button class="system-modal-btn primary" onclick="closeTotalPendingModal()">OK</button>
            </div>
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
            if (isSidebarOpen) closeSidebar(); else openSidebar();
        }

        if (burgerBtn) burgerBtn.addEventListener('click', function (e) { e.stopPropagation(); toggleSidebar(); });
        if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', function (e) { e.stopPropagation(); closeSidebar(); });
        if (menuOverlay) menuOverlay.addEventListener('click', closeSidebar);

        document.querySelectorAll('.side-menu .nav-item, .side-menu .nav-dropdown-item').forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 768) {
                    if (!this.closest('.nav-dropdown-toggle')) closeSidebar();
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

        // ========== NAVIGATION ==========
        function viewCustomerOrders(deliveryNumber) {
            if (!deliveryNumber) return;
            window.location.href = 'pending_orders.php?delivery_number=' + encodeURIComponent(deliveryNumber);
        }

        // ========== TOTAL PAID MODAL ==========
        function showTotalPendingModal() {
            document.getElementById('totalPendingModal').style.display = 'flex';
        }

        function closeTotalPendingModal() {
            document.getElementById('totalPendingModal').style.display = 'none';
        }

        // ========== HELPERS ==========
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, function (m) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
            });
        }

        function showMessage(title, message, type = 'info', onOk = null) {
            const overlay = document.createElement('div');
            overlay.className = 'system-modal-overlay';
            overlay.style.display = 'flex';

            let iconClass = 'fa-info-circle';
            if (type === 'error') iconClass = 'fa-times-circle';
            else if (type === 'success') iconClass = 'fa-check-circle';
            else if (type === 'warning') iconClass = 'fa-exclamation-triangle';

            overlay.innerHTML = `
                <div class="system-modal" style="min-width: 350px;">
                    <div class="system-modal-header">
                        <i class="fas ${iconClass}"></i>
                        <span>${escapeHtml(title)}</span>
                    </div>
                    <div class="system-modal-content">
                        <div class="system-modal-message ${type}">
                            <div class="message-text">${escapeHtml(message)}</div>
                        </div>
                    </div>
                    <div class="system-modal-footer">
                        <button class="system-modal-btn primary" id="ok-btn">OK</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);

            const close = () => overlay.remove();
            overlay.querySelector('#ok-btn').addEventListener('click', close);
            if (onOk) overlay.querySelector('#ok-btn').addEventListener('click', onOk, { once: true });
        }

        function showConfirmModal(title, message, onConfirm) {
            const overlay = document.createElement('div');
            overlay.className = 'system-modal-overlay';
            overlay.style.display = 'flex';

            overlay.innerHTML = `
                <div class="system-modal" style="min-width: 350px;">
                    <div class="system-modal-header">
                        <i class="fas fa-question-circle"></i>
                        <span>${escapeHtml(title)}</span>
                    </div>
                    <div class="system-modal-content">
                        <div class="system-modal-message warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div class="message-text">${escapeHtml(message)}</div>
                        </div>
                    </div>
                    <div class="system-modal-footer">
                        <button class="system-modal-btn" id="cancel-btn">CANCEL</button>
                        <button class="system-modal-btn primary" id="confirm-btn">CONFIRM</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);
            overlay.querySelector('#cancel-btn').addEventListener('click', () => overlay.remove());
            overlay.querySelector('#confirm-btn').addEventListener('click', () => {
                overlay.remove();
                onConfirm();
            });
        }

        // ========== GLOBAL CLOSE ==========
        window.addEventListener('click', function (event) {
            const totalModal = document.getElementById('totalPendingModal');
            if (event.target === totalModal) closeTotalPendingModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const totalModal = document.getElementById('totalPendingModal');
                if (totalModal.style.display === 'flex') closeTotalPendingModal();
            }
        });
    </script>
</body>

</html>