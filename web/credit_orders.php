<?php
// paid_folder_with.php
session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../DB_Conn/config.php';

// Check if user is logged in with acc_number
if (!isset($_SESSION['acc_number'])) {
    header('Location: ../login.php');
    exit();
}

// Fetch full user data from admins table
$stmt = $pdo->prepare("SELECT * FROM admins WHERE acc_number = ?");
$stmt->execute([$_SESSION['acc_number']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}


// Store user role in session for easy access
$_SESSION['role'] = $user['role'];

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

// Get delivery_number from URL
$selectedDeliveryNumber = isset($_GET['delivery_number']) ? $_GET['delivery_number'] : '';

if (empty($selectedDeliveryNumber)) {
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

// Fetch delivery information from for_deliveries table
$stmt = $pdo->prepare("SELECT * FROM for_deliveries WHERE delivery_number = ?");
$stmt->execute([$selectedDeliveryNumber]);
$deliveryInfo = $stmt->fetch(PDO::FETCH_ASSOC);

// If not found in for_deliveries table, try to get from order_status_history
if (!$deliveryInfo && !empty($orderItems)) {
    // Get the first item's delivery info if available
    $firstItem = $orderItems[0];
    $deliveryInfo = [
        'ordered_by' => $firstItem['ordered_by'] ?? ($firstItem['customer_name'] ?? 'Unknown Customer'),
        'delivery_m_y' => $firstItem['delivery_m_y'] ?? '',
        'status' => $firstItem['status'] ?? 'CREDIT',
        'delivery_number' => $selectedDeliveryNumber
    ];
}

// Set default if still no delivery info
if (!$deliveryInfo) {
    $deliveryInfo = [
        'ordered_by' => 'Customer Information Not Found',
        'delivery_m_y' => '',
        'status' => 'CREDIT',
        'delivery_number' => $selectedDeliveryNumber
    ];
}

// Get customer name - prioritize from deliveryInfo first, then from order items
$customerName = $deliveryInfo['ordered_by'] ?? '';
if (empty($customerName) && !empty($orderItems)) {
    // Try to get customer name from order items
    if (isset($orderItems[0]['ordered_by'])) {
        $customerName = $orderItems[0]['ordered_by'];
    } elseif (isset($orderItems[0]['customer_name'])) {
        $customerName = $orderItems[0]['customer_name'];
    } else {
        $customerName = 'Unknown Customer';
    }
}

$monthYear = $deliveryInfo['delivery_m_y'] ?? '';
$deliveryNumber = $deliveryInfo['delivery_number'] ?? $selectedDeliveryNumber;
$currentStatus = $deliveryInfo['status'] ?? 'CREDIT';

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
    <title>Credit Orders | <?= htmlspecialchars($selectedDeliveryNumber) ?> | Villaruz Print Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SheetJS for Excel export -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
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
            flex-wrap: wrap;
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
        }

        .delivery-header i {
            margin-right: 10px;
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

        /* Buttons */

        .receipt-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }

        .receipt-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: white;
            color: black;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
            border: 1;
            font-size: 14px;
        }

        .receipt-btn:hover {
            background: gray;
            transform: translateY(-2px);
        }

        .receipt-btn i {
            font-size: 18px;
        }



        /* Custom Select Styles */
        .status-select {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: white;
            color: black;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
            border: 1;
            font-size: 14px;
        }

        .status-select:hover {
            background: gray;
            transform: translateY(-2px);
        }

        .status-select option {
            background: white;
            color: #1e293b;
            padding: 10px;
        }

        .status-select option[value="PAID"] {
            color: black;
        }

        .status-select option[value="CANCELLED"] {
            color: black;
        }

        .status-select option[value="PENDING"] {
            color: black;
        }

        .status-select option[value="CREDIT"] {
            color: black;
        }

        .status-wrapper i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            pointer-events: none;
            font-size: 14px;
        }

        /* Editable input styles */
        .editable-input {
            width: 100%;
            padding: 6px 10px;
            border: 2px solid #8b5cf6;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            background: #fff;
            transition: all 0.2s;
        }

        .editable-input:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .editable-select {
            width: 100%;
            padding: 6px 10px;
            border: 2px solid #8b5cf6;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            background: #fff;
            cursor: pointer;
        }

        .editable-select:focus {
            outline: none;
            border-color: #7c3aed;
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
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', 'Poppins', system-ui, monospace;
        }

        .system-modal {
            background: #f0f0f0;
            min-width: 380px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.5);
            border: 2px solid #3a6ea5;
            border-radius: 0;
            overflow: hidden;
            animation: modalAppear 0.2s ease;
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
            padding: 25px 20px;
            background: white;
            border-bottom: 1px solid #ccc;
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

        .system-modal-message.warning i {
            color: #ff9800;
        }

        .system-modal-message.error i {
            color: #f44336;
        }

        .system-modal-message.success i {
            color: #4caf50;
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

        .system-modal-btn.danger {
            background: #d32f2f;
            border-color: #9a1a1a;
            color: white;
        }

        .system-modal-btn.danger:hover {
            background: #b71c1c;
        }

        .xampp-btn {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            font-size: 12px;
            padding: 4px 12px;
            border: 1px solid #707070;
            border-radius: 2px;
            cursor: pointer;
            min-width: 70px;
            background: linear-gradient(to bottom, #f0f0f0 0%, #e1e1e1 100%);
            box-shadow: inset 1px 1px 0px #fff;
        }

        /* Hover state mimics standard Windows buttons */
        .xampp-btn:hover {
            background: linear-gradient(to bottom, #e5f1fb 0%, #e5f1fb 100%);
            border-color: #0078d7;
        }

        @media (max-width: 480px) {
            .system-modal {
                min-width: 300px;
            }

            .system-modal-message {
                font-size: 13px;
            }

            .system-modal-btn {
                padding: 5px 15px;
                min-width: 60px;
            }

            .editable-input,
            .editable-select {
                font-size: 11px;
                padding: 4px 6px;
            }
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

            .orders-table td {
                padding: 4px 5px;
                font-size: 10px;
            }

            .delivery-header {
                font-size: 12px;
                padding: 10px 15px;
            }

            .breadcrumb {
                padding: 10px 15px;
            }

            .breadcrumb-item,
            .breadcrumb-current {
                font-size: 11px;
            }

            .welcome h4 {
                font-size: 14px;
            }

            .receipt-btn {
                padding: 8px 16px;
                font-size: 12px;
            }

            .receipt-btn i {
                font-size: 14px;
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
                    <h4>
                        <a href="credit_folder.php"><i class="fas fa-folder-open"></i> Credit Folders </a>
                        <?php if ($monthYear): ?>
                            <i class="fas fa-chevron-right"></i>
                            <a href="credit_folder_with.php?month=<?= urlencode($monthYear) ?>">
                                <i class="fas fa-folder-open"></i> <?= htmlspecialchars($monthYear) ?>
                            </a>
                        <?php endif; ?>
                        <i class="fas fa-chevron-right"></i>
                        <i class="fas fa-folder-open"></i> <?= htmlspecialchars($deliveryNumber) ?>
                    </h4>
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
                            <i class="fas fa-user"></i> Customer: <?= htmlspecialchars($customerName) ?>
                        </div>
                        <div class="orders-table-container">
                            <table class="orders-table" id="orders-table">
                                <tbody>
                                    <?php foreach ($orderItems as $index => $item): ?>
                                        <tr data-id="<?= htmlspecialchars($item['id'] ?? $index) ?>"
                                            data-product="<?= htmlspecialchars($item['product_name'] ?? '') ?>"
                                            data-pieces="<?= htmlspecialchars($item['pieces'] ?? '0') ?>"
                                            data-unit="<?= htmlspecialchars($item['unit'] ?? 'N/A') ?>"
                                            data-selling-price="<?= floatval($item['selling_price']) ?>"
                                            data-total="<?= floatval($item['total_amount'] ?? 0) ?>">
                                            <td class="product-name"><?= htmlspecialchars($item['product_name'] ?? 'N/A') ?>
                                            </td>
                                            <td class="pieces"><?= htmlspecialchars($item['pieces'] ?? '0') ?></td>
                                            <td class="unit"><?= htmlspecialchars($item['unit'] ?? 'N/A') ?></td>
                                            <td class="selling_price">₱ <?= htmlspecialchars($item['selling_price'] ?? 'N/A') ?>
                                            </td>
                                            <td class="total-amount">
                                                ₱ <?= number_format(floatval($item['total_amount'] ?? 0), 2) ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <button class="xampp-btn remove-btn" data-row-id="<?= $index ?>">Remove</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td colspan="4" style="text-align: right; font-weight: 600;">TOTAL:</td>
                                        <td style="font-weight: 600;" id="total-amount-display">₱
                                            <?= number_format($totalAmount, 2) ?>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Receipt Buttons and Status Update -->
                        <div class="receipt-actions">
                            <button class="receipt-btn delivery-receipt" onclick="generateReceipt('delivery')">
                                <i class="fas fa-truck"></i> Delivery Receipt
                            </button>
                            <button class="receipt-btn billing-receipt" onclick="generateReceipt('billing')">
                                <i class="fas fa-file-invoice-dollar"></i> Billing Receipt
                            </button>
                            <button class="receipt-btn download-excel" id="download-excel-btn">
                                <i class="fas fa-file-excel"></i> Download Excel
                            </button>
                            <button class="receipt-btn edit-mode-btn" id="edit-mode-btn">
                                <i class="fas fa-edit"></i>
                            </button>

                            <!-- Update Status Dropdown -->
                            <div class="status-wrapper">
                                <select class="status-select" id="status-select"
                                    data-delivery-number="<?= htmlspecialchars($deliveryNumber) ?>">
                                    <option value="PENDING" <?= $currentStatus == 'PENDING' ? 'selected' : '' ?>><i
                                            class="fas fa-pending"></i> PENDING</option>
                                    <option value="PAID" <?= $currentStatus == 'PAID' ? 'selected' : '' ?>><i
                                            class="fas fa-pending"></i> PAID</option>
                                    <!-- <option value="CANCELLED" <?= $currentStatus == 'CANCELLED' ? 'selected' : '' ?>><i
                                            class="fas fa-pending"></i> CANCELLED</option> -->
                                    <option value="CREDIT" <?= $currentStatus == 'CREDIT' ? 'selected' : '' ?>><i
                                            class="fas fa-pending"></i> CREDIT</option>
                                </select>
                            </div>
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
        const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        let isEditMode = false;
        let originalData = new Map(); // Store original data for each row

        // Burger menu functionality
        const burgerBtn = document.getElementById('burgerBtn');
        const menuOverlay = document.getElementById('menuOverlay');
        const sideMenu = document.querySelector('.side-menu');

        if (burgerBtn && menuOverlay && sideMenu) {
            burgerBtn.addEventListener('click', () => {
                sideMenu.classList.add('open');
                menuOverlay.classList.add('active');
            });

            menuOverlay.addEventListener('click', () => {
                sideMenu.classList.remove('open');
                menuOverlay.classList.remove('active');
            });
        }

        function generateReceipt(type) {
            const deliveryNumber = '<?= htmlspecialchars($selectedDeliveryNumber) ?>';

            if (type === 'delivery') {
                window.location.href = '../delivery_receipt.php?delivery_number=' + encodeURIComponent(deliveryNumber);
            } else if (type === 'billing') {
                window.location.href = '../billing_receipt.php?delivery_number=' + encodeURIComponent(deliveryNumber);
            }
        }

        // ========== EXCEL DOWNLOAD FUNCTION ==========
        document.getElementById('download-excel-btn')?.addEventListener('click', function () {
            const deliveryNumber = '<?= htmlspecialchars($selectedDeliveryNumber) ?>';

            // Get all order items from the table
            const rows = document.querySelectorAll('#orders-table tbody tr:not(.total-row)');
            const excelData = [];

            // Add header row
            excelData.push(['#', 'Unit', 'Item Description', 'Quantity', 'Unit Cost', 'Total Cost']);

            // Add data rows
            let rowNumber = 1;
            rows.forEach(row => {
                const unit = row.cells[2]?.innerText || '';
                const itemDescription = row.cells[0]?.innerText || '';
                const quantity = row.cells[1]?.innerText || '0';
                let unitCost = row.cells[3]?.innerText || '0';
                let totalCost = row.cells[4]?.innerText || '0';

                // Remove ₱ symbol and commas
                unitCost = unitCost.replace('₱', '').replace(/,/g, '').trim();
                totalCost = totalCost.replace('₱', '').replace(/,/g, '').trim();

                excelData.push([
                    rowNumber,
                    unit,
                    itemDescription,
                    quantity,
                    unitCost,
                    totalCost
                ]);

                rowNumber++;
            });

            // Add empty row and total
            excelData.push([]);
            excelData.push(['', '', '', '', 'TOTAL:', '']);

            // Add total amount
            const totalDisplay = document.getElementById('total-amount-display');
            let totalAmount = totalDisplay?.innerText || '0';
            totalAmount = totalAmount.replace('₱', '').replace(/,/g, '').trim();
            excelData.push(['', '', '', '', '', totalAmount]);

            // Create worksheet
            const ws = XLSX.utils.aoa_to_sheet(excelData);

            // Set column widths
            ws['!cols'] = [
                { wch: 5 },   // #
                { wch: 10 },  // Unit
                { wch: 35 },  // Item Description
                { wch: 10 },  // Quantity
                { wch: 12 },  // Unit Cost
                { wch: 15 }   // Total Cost
            ];

            // Apply center alignment to all cells
            const range = XLSX.utils.decode_range(ws['!ref']);
            for (let row = range.s.r; row <= range.e.r; row++) {
                for (let col = range.s.c; col <= range.e.c; col++) {
                    const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                    if (!ws[cellAddress]) continue;

                    ws[cellAddress].s = {
                        alignment: {
                            horizontal: "center",
                            vertical: "center"
                        }
                    };
                }
            }

            // Style the header row (green background + bold white text + centered)
            for (let col = range.s.c; col <= range.e.c; col++) {
                const cellAddress = XLSX.utils.encode_cell({ r: 0, c: col });
                if (ws[cellAddress]) {
                    ws[cellAddress].s = {
                        font: { bold: true, color: { rgb: "FFFFFF" } },
                        fill: { fgColor: { rgb: "4CAF50" } },
                        alignment: {
                            horizontal: "center",
                            vertical: "center"
                        }
                    };
                }
            }

            // Style the total row (bold)
            const totalRowIndex = excelData.length - 1;
            for (let col = range.s.c; col <= range.e.c; col++) {
                const cellAddress = XLSX.utils.encode_cell({ r: totalRowIndex, c: col });
                if (ws[cellAddress]) {
                    ws[cellAddress].s = {
                        font: { bold: true },
                        alignment: {
                            horizontal: "center",
                            vertical: "center"
                        }
                    };
                }
            }

            // Create workbook and save
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, `Order_${deliveryNumber}`);

            // Download file
            XLSX.writeFile(wb, `Order_${deliveryNumber}.xlsx`);

            // Show success message
            showMessageModal('EXPORT SUCCESS', `Order data has been exported to Excel successfully!`, 'success');
        });

        // ========== COMPUTER-STYLE SYSTEM MODAL ==========
        function showSystemModal(title, message, type = 'warning', buttons = []) {
            return new Promise((resolve) => {
                // Remove existing modal
                const existingModal = document.querySelector('.system-modal-overlay');
                if (existingModal) {
                    existingModal.remove();
                }

                const overlay = document.createElement('div');
                overlay.className = 'system-modal-overlay';

                let iconClass = 'fa-exclamation-triangle';
                let iconColor = 'warning';

                switch (type) {
                    case 'error':
                        iconClass = 'fa-times-circle';
                        iconColor = 'error';
                        break;
                    case 'success':
                        iconClass = 'fa-check-circle';
                        iconColor = 'success';
                        break;
                    case 'info':
                        iconClass = 'fa-info-circle';
                        iconColor = 'info';
                        break;
                    default:
                        iconClass = 'fa-exclamation-triangle';
                        iconColor = 'warning';
                }

                let buttonsHtml = '';
                if (buttons.length === 0) {
                    buttonsHtml = `<button class="system-modal-btn primary" data-action="ok">OK</button>`;
                } else {
                    buttonsHtml = buttons.map(btn => {
                        let btnClass = 'system-modal-btn';
                        if (btn.type === 'primary') btnClass += ' primary';
                        if (btn.type === 'danger') btnClass += ' danger';
                        return `<button class="${btnClass}" data-action="${btn.action}">${btn.label}</button>`;
                    }).join('');
                }

                overlay.innerHTML = `
                    <div class="system-modal">
                        <div class="system-modal-header">
                            <i class="fas fa-shop"></i>
                            <span>${escapeHtml(title)}</span>
                        </div>
                        <div class="system-modal-content">
                            <div class="system-modal-message ${iconColor}">
                                <i class="fas ${iconClass}"></i>
                                <div class="message-text">${escapeHtml(message)}</div>
                            </div>
                        </div>
                        <div class="system-modal-footer">
                            ${buttonsHtml}
                        </div>
                    </div>
                `;

                document.body.appendChild(overlay);

                // Handle button clicks
                const buttonsElements = overlay.querySelectorAll('.system-modal-btn');
                buttonsElements.forEach(btn => {
                    btn.addEventListener('click', () => {
                        const action = btn.dataset.action;
                        overlay.remove();
                        resolve(action);
                    });
                });
            });
        }

        function showConfirmModal(title, message, onConfirm, onCancel = null) {
            showSystemModal(title, message, 'warning', [
                { label: 'CANCEL', action: 'cancel', type: '' },
                { label: 'CONFIRM', action: 'confirm', type: 'primary' }
            ]).then(result => {
                if (result === 'confirm' && onConfirm) {
                    onConfirm();
                } else if (result === 'cancel' && onCancel) {
                    onCancel();
                }
            });
        }

        function showMessageModal(title, message, type = 'info', onOk = null) {
            showSystemModal(title, message, type, [
                { label: 'OK', action: 'ok', type: 'primary' }
            ]).then(() => {
                if (onOk) onOk();
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function (m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        // ========== EDIT MODE FUNCTIONALITY ==========
        const editModeBtn = document.getElementById('edit-mode-btn');

        function enableEditMode() {
            isEditMode = true;
            editModeBtn.innerHTML = '<i class="fas fa-save"></i>';
            editModeBtn.classList.remove('edit-mode-btn');
            editModeBtn.classList.add('update-mode-btn');

            // Store original data and replace with input fields
            const rows = document.querySelectorAll('#orders-table tbody tr:not(.total-row)');

            rows.forEach((row, index) => {
                // Store original data
                const originalProduct = row.cells[0].innerText;
                const originalPieces = row.cells[1].innerText;
                const originalUnit = row.cells[2].innerText;
                const originalSellingPrice = row.cells[3].innerText.replace('₱', '').replace(/,/g, '');
                const originalTotal = row.cells[4].innerText.replace('₱', '').replace(/,/g, '');

                originalData.set(index, {
                    product: originalProduct,
                    pieces: originalPieces,
                    unit: originalUnit,
                    selling_price: originalSellingPrice,
                    total: originalTotal
                });

                // Replace with editable inputs
                row.cells[0].innerHTML = `<input type="text" class="editable-input" value="${escapeHtml(originalProduct)}" data-field="product">`;
                row.cells[1].innerHTML = `<input type="text" class="editable-input" value="${originalPieces}" data-field="pieces">`;
                row.cells[2].innerHTML = `
                    <select class="editable-select" data-field="unit">
                        <option value="PCS" ${originalUnit === 'PCS' ? 'selected' : ''}>PCS</option>
                        <option value="BOX" ${originalUnit === 'BOX' ? 'selected' : ''}>BOX</option>
                        <option value="REAMS" ${originalUnit === 'REAMS' ? 'selected' : ''}>REAMS</option>
                        <option value="SET" ${originalUnit === 'SET' ? 'selected' : ''}>SET</option>
                    </select>
                `;
                row.cells[3].innerHTML = `<input type="text" class="editable-input" value="${originalSellingPrice}" data-field="selling_price">`;
                row.cells[4].innerHTML = `<input type="text" class="editable-input" value="${originalTotal}" data-field="total">`;

                // Add auto-calculation: when pieces or selling price changes, update total
                const piecesInput = row.cells[1].querySelector('input');
                const sellingPriceInput = row.cells[3].querySelector('input');
                const totalInput = row.cells[4].querySelector('input');

                function calculateTotal() {
                    const pieces = parseFloat(piecesInput.value) || 0;
                    const price = parseFloat(sellingPriceInput.value) || 0;
                    const newTotal = pieces * price;
                    totalInput.value = newTotal.toFixed(2);
                }

                if (piecesInput && sellingPriceInput && totalInput) {
                    piecesInput.addEventListener('input', calculateTotal);
                    sellingPriceInput.addEventListener('input', calculateTotal);
                }
            });
        }

        async function disableEditMode() {
            // Collect all updated data
            const updatedItems = [];
            const rows = document.querySelectorAll('#orders-table tbody tr:not(.total-row)');
            let isValid = true;

            rows.forEach((row, index) => {
                const productInput = row.cells[0].querySelector('input');
                const piecesInput = row.cells[1].querySelector('input');
                const unitSelect = row.cells[2].querySelector('select');
                const sellingPriceInput = row.cells[3].querySelector('input');
                const totalInput = row.cells[4].querySelector('input');

                if (!productInput || !piecesInput || !unitSelect || !sellingPriceInput || !totalInput) {
                    isValid = false;
                    return;
                }

                const newProduct = productInput.value.trim();
                const newPieces = piecesInput.value;
                const newUnit = unitSelect.value;
                const newSellingPrice = sellingPriceInput.value;
                const newTotal = totalInput.value;

                if (!newProduct || !newPieces || newPieces <= 0 || newSellingPrice < 0 || newTotal <= 0) {
                    isValid = false;
                    showMessageModal('INVALID DATA', 'Please fill all fields with valid values (Product name, pieces > 0, selling price >= 0, total > 0)', 'error');
                    return;
                }

                updatedItems.push({
                    rowId: row.dataset.id,
                    original: originalData.get(index),
                    updated: {
                        product: newProduct,
                        pieces: newPieces,
                        unit: newUnit,
                        selling_price: newSellingPrice,
                        total: newTotal
                    },
                    rowElement: row
                });
            });

            if (!isValid) return;

            // Check if there are changes
            let hasChanges = false;
            for (const item of updatedItems) {
                if (item.original.product !== item.updated.product ||
                    item.original.pieces !== item.updated.pieces ||
                    item.original.unit !== item.updated.unit ||
                    parseFloat(item.original.selling_price) !== parseFloat(item.updated.selling_price) ||
                    parseFloat(item.original.total) !== parseFloat(item.updated.total)) {
                    hasChanges = true;
                    break;
                }
            }

            if (!hasChanges) {
                // No changes, just exit edit mode
                exitEditMode();
                return;
            }

            // Confirm update
            showConfirmModal(
                'UPDATE ORDER ITEMS',
                'Are you sure you want to update the order items? This action will modify the records.',
                async () => {
                    // Send update to server
                    await updateOrderItems(updatedItems);
                },
                () => {
                    // User cancelled, stay in edit mode
                }
            );
        }

        async function updateOrderItems(updatedItems) {
            try {
                const deliveryNumber = '<?= htmlspecialchars($selectedDeliveryNumber) ?>';
                const formData = new FormData();
                formData.append('action', 'update_order_items');
                formData.append('delivery_number', deliveryNumber);
                formData.append('csrf_token', csrfToken);
                formData.append('items', JSON.stringify(updatedItems.map(item => ({
                    id: item.rowId,
                    product_name: item.updated.product,
                    pieces: item.updated.pieces,
                    unit: item.updated.unit,
                    selling_price: item.updated.selling_price,
                    total_amount: item.updated.total
                }))));

                const response = await fetch('../API/update_delivery_status.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showMessageModal('SUCCESS', data.message, 'success', () => {
                        // Reload the page to show updated data
                        window.location.reload();
                    });
                } else {
                    showMessageModal('ERROR', data.message || 'Failed to update items', 'error');
                }
            } catch (err) {
                console.error('Update error:', err);
                showMessageModal('NETWORK ERROR', 'Connection error: ' + err.message, 'error');
            }
        }

        function exitEditMode() {
            // Reload page to restore original data
            window.location.reload();
        }

        // Edit/Update button click handler
        if (editModeBtn) {
            editModeBtn.addEventListener('click', () => {
                if (!isEditMode) {
                    enableEditMode();
                } else {
                    disableEditMode();
                }
            });
        }

        // ========== STATUS UPDATE ==========
        const statusSelect = document.getElementById('status-select');
        if (statusSelect) {
            let previousStatus = statusSelect.value;

            statusSelect.addEventListener('change', async function () {
                const deliveryNumber = this.dataset.deliveryNumber;
                const newStatus = this.value;

                // If changing to PAID, show confirmation modal
                if (newStatus === 'PAID') {
                    showConfirmModal(
                        'CONFIRM STATUS CHANGE',
                        `Clicking CONFIRM orders with Delivery No. ${deliveryNumber} have been paid by the customer.`,
                        async () => {
                            // On Confirm - proceed with update
                            await updateStatus(deliveryNumber, newStatus);
                        },
                        () => {
                            // On Cancel - revert selection
                            statusSelect.value = previousStatus;
                        }
                    );
                } else if (newStatus === 'CANCELLED') {
                    showConfirmModal(
                        'CONFIRM CANCELLATION',
                        `Are you sure you want to cancel Delivery No. ${deliveryNumber}? This action cannot be undone.`,
                        async () => {
                            await updateStatus(deliveryNumber, newStatus);
                        },
                        () => {
                            statusSelect.value = previousStatus;
                        }
                    );
                } else {
                    // For other status changes (e.g., PENDING), update directly
                    await updateStatus(deliveryNumber, newStatus);
                }
            });
        }

        async function updateStatus(deliveryNumber, newStatus) {
            const statusSelect = document.getElementById('status-select');
            let previousStatus = statusSelect ? statusSelect.value : '';

            try {
                if (statusSelect) statusSelect.disabled = true;

                const formData = new FormData();
                formData.append('action', 'update_order_status');
                formData.append('delivery_number', deliveryNumber);
                formData.append('status', newStatus);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/update_delivery_status.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showMessageModal('SUCCESS', data.message, 'success', () => {
                        window.location.href = 'pending_folder.php';
                    });
                } else {
                    showMessageModal('ERROR', data.message || 'Failed to update status', 'error');
                    if (statusSelect) statusSelect.value = previousStatus;
                }
            } catch (err) {
                showMessageModal('NETWORK ERROR', 'Connection error: ' + err.message, 'error');
                if (statusSelect) statusSelect.value = previousStatus;
            } finally {
                if (statusSelect) statusSelect.disabled = false;
            }
        }

        // ========== REMOVE ITEM FUNCTIONALITY ==========
        // Attach event listeners to all remove buttons
        document.querySelectorAll('.remove-btn').forEach(button => {
            button.addEventListener('click', async function () {
                if (isEditMode) {
                    showMessageModal('EDIT MODE ACTIVE', 'Please click UPDATE to save changes or refresh the page to cancel edit mode.', 'warning');
                    return;
                }

                const row = this.closest('tr');
                const productName = row.cells[0]?.innerText || '';
                const pieces = row.cells[1]?.innerText || '0';
                const totalAmount = row.cells[4]?.innerText.replace('₱', '').replace(/,/g, '') || '0';
                const deliveryNumber = '<?= htmlspecialchars($selectedDeliveryNumber) ?>';

                // Show confirmation modal before removing
                showConfirmModal(
                    'REMOVE ITEM',
                    `Are you sure you want to remove "${productName}" (${pieces} pcs) from this order?\n\nThis action cannot be undone.`,
                    async () => {
                        // On Confirm - proceed with removal
                        await removeOrderItem(deliveryNumber, productName, pieces, totalAmount, row);
                    }
                );
            });
        });

        /**
         * Remove an item from order_status_history
         * 
         * @param {string} deliveryNumber - Delivery number
         * @param {string} productName - Product name to remove
         * @param {string|number} pieces - Quantity
         * @param {string|number} totalAmount - Total amount of the item
         * @param {HTMLElement} row - The table row to remove
         */
        async function removeOrderItem(deliveryNumber, productName, pieces, totalAmount, row) {
            try {
                const formData = new FormData();
                formData.append('action', 'remove_order_item');
                formData.append('delivery_number', deliveryNumber);
                formData.append('product_name', productName);
                formData.append('pieces', pieces);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/update_delivery_status.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Remove the row from the table
                    row.remove();

                    // Update the total amount
                    const totalDisplay = document.getElementById('total-amount-display');
                    const currentTotalText = totalDisplay.innerText.replace('₱', '').replace(/,/g, '');
                    const currentTotal = parseFloat(currentTotalText) || 0;
                    const newTotal = currentTotal - parseFloat(totalAmount);

                    totalDisplay.innerHTML = `₱ ${newTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

                    // Check if there are no more items left
                    const remainingRows = document.querySelectorAll('#orders-table tbody tr:not(.total-row)').length;
                    if (remainingRows === 0) {
                        // Show empty state and redirect after a moment
                        showMessageModal('ORDER EMPTY', 'No items remaining in this order. Redirecting...', 'info', () => {
                            window.location.href = 'credit_folder.php';
                        });
                    } else {
                        showMessageModal('SUCCESS', data.message, 'success');
                    }
                } else {
                    showMessageModal('ERROR', data.message || 'Failed to remove item', 'error');
                }
            } catch (err) {
                console.error('Remove error:', err);
                showMessageModal('NETWORK ERROR', 'Connection error: ' + err.message, 'error');
            }
        }
    </script>
</body>

</html>