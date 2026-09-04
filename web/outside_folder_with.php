<?php
// web/pending_folder_with.php
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

// Get selected month from URL
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : '';

if (empty($selectedMonth)) {
    // If no month selected, redirect back to pending_folder.php
    header('Location: pending_folder.php');
    exit;
}

// Fetch deliveries for the selected delivery_m_y with status PENDING
$stmt = $pdo->prepare("SELECT * FROM for_deliveries WHERE delivery_m_y = ? AND status IN ('PENDING', 'PACKING', 'SHIPPED', 'OFD', 'DELIVERED') AND total_amount < 500 ORDER BY id DESC");
$stmt->execute([$selectedMonth]);
$allDeliveries = $stmt->fetchAll();

// Group deliveries by delivery_number instead of customer
$deliveriesByDeliveryNumber = [];

foreach ($allDeliveries as $delivery) {
    $deliveryNumber = $delivery['delivery_number'];

    // Initialize delivery group if not exists
    if (!isset($deliveriesByDeliveryNumber[$deliveryNumber])) {
        $deliveriesByDeliveryNumber[$deliveryNumber] = [
            'deliveries' => [],
            'customer' => $delivery['ordered_by'],
            'delivery_number' => $deliveryNumber
        ];
    }

    // Add delivery item to the delivery's list
    $deliveriesByDeliveryNumber[$deliveryNumber]['deliveries'][] = $delivery;
}

// ==============================================
// CALCULATE TOTAL PENDING AMOUNT FOR SELECTED MONTH
// ==============================================

$totalPendingAmount = 0;

if (!empty($allDeliveries)) {
    foreach ($allDeliveries as $delivery) {
        $deliveryNumber = $delivery['delivery_number'];

        // Fetch total amount from order_status_history for this delivery
        $stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM order_status_history WHERE delivery_number = ?");
        $stmt->execute([$deliveryNumber]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalPendingAmount += floatval($result['total'] ?? 0);
    }
}

// ==============================================
// FETCH CONTRACTS FOR THE SELECTED MONTH
// ==============================================

$stmt = $pdo->prepare("SELECT * FROM contracts WHERE contract_m_y = ? ORDER BY contractor");
$stmt->execute([$selectedMonth]);
$contracts = $stmt->fetchAll();

// Calculate total contract value for the month
$totalContractValue = 0;
foreach ($contracts as $contract) {
    $totalContractValue += floatval($contract['contract_value'] ?? 0);
}

// Calculate grand total (Contracts + Pending Sales)
$grandTotal = $totalContractValue + $totalPendingAmount;

// Extract month name from selected month
$selectedMonthName = '';
if (!empty($selectedMonth)) {
    $parts = explode(' ', $selectedMonth);
    $selectedMonthName = $parts[0] ?? $selectedMonth;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Pending Orders | <?= htmlspecialchars($selectedMonth) ?> | Villaruz Print Shop</title>
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

        .modal-large {
            max-width: 900px;
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

        .system-modal-btn.success {
            background: #10b981;
            border-color: #059669;
            color: white;
        }

        .system-modal-btn.success:hover {
            background: #059669;
        }

        .system-modal-btn.warning {
            background: #f59e0b;
            border-color: #d97706;
            color: white;
        }

        .system-modal-btn.warning:hover {
            background: #d97706;
        }

        .amount-display {
            font-size: 24px;
            font-weight: 700;
            color: #f59e0b;
            text-align: center;
            margin-top: 10px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .total-contract-display {
            font-size: 20px;
            font-weight: 700;
            color: #3b82f6;
            text-align: right;
            margin-bottom: 15px;
            padding: 10px;
            background: #eff6ff;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }

        /* Summary Modal Styles */
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

        .summary-value.contract {
            color: #3b82f6;
        }

        .summary-value.pending {
            color: #f59e0b;
        }

        .summary-value.grand {
            color: #8b5cf6;
            font-size: 22px;
        }

        .divider-line {
            height: 2px;
            background: linear-gradient(90deg, #3b82f6, #f59e0b, #8b5cf6);
            margin: 15px 0;
        }

        .grand-total-row {
            background: #f8fafc;
            border-radius: 8px;
            margin-top: 10px;
        }

        /* Contracts Table Styles */
        .contracts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .contracts-table th,
        .contracts-table td {
            border: 1px solid #e2e8f0;
            padding: 10px;
            text-align: left;
            font-size: 13px;
        }

        .contracts-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #0f172a;
        }

        .contracts-table td {
            background: #ffffff;
        }

        .contracts-table .display-mode {
            padding: 6px;
            min-height: 35px;
        }

        .contracts-table .edit-mode input,
        .contracts-table .edit-mode textarea {
            width: 100%;
            padding: 6px;
            border: 1px solid #3a6ea5;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
        }

        .contracts-table .edit-mode textarea {
            resize: vertical;
        }

        .contracts-table .edit-mode input:focus,
        .contracts-table .edit-mode textarea:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.1);
        }

        .edit-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            margin-right: 5px;
        }

        .edit-btn:hover {
            background: #2563eb;
        }

        .delete-row-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
        }

        .delete-row-btn:hover {
            background: #dc2626;
        }

        .save-row-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            margin-right: 5px;
        }

        .save-row-btn:hover {
            background: #059669;
        }

        .cancel-row-btn {
            background: #6b7280;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
        }

        .cancel-row-btn:hover {
            background: #4b5563;
        }

        .add-row-btn {
            margin-top: 15px;
            padding: 8px 16px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .add-row-btn:hover {
            background: #2563eb;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
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

            .contracts-table th,
            .contracts-table td {
                font-size: 11px;
                padding: 6px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .edit-btn,
            .save-row-btn,
            .cancel-row-btn,
            .delete-row-btn {
                font-size: 10px;
                padding: 3px 6px;
            }

            .total-contract-display {
                font-size: 16px;
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
                            <a href="outside_folder.php"><i class="fas fa-folder-open"></i> Outside Folders </a>
                            <?php if ($selectedMonth): ?>
                                <i class="fas fa-chevron-right"></i> <i class="fas fa-folder-open"></i>
                                <?= htmlspecialchars($selectedMonth) ?>
                            <?php endif; ?>
                        </h4>
                    </div>
                </div>
            </div>

            <div class="folders-grid">
                <?php if (empty($deliveriesByDeliveryNumber)): ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-folder-open"></i>
                        <p>No pending deliveries for <?= htmlspecialchars($selectedMonth) ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($deliveriesByDeliveryNumber as $deliveryData): ?>
                        <div class="folder-item"
                            onclick="viewCustomerOrders('<?= urlencode($deliveryData['delivery_number']) ?>')">
                            <div class="folder-icon">
                                <i class="fas fa-folder"></i>
                            </div>
                            <div class="folder-name">
                                <?= htmlspecialchars($deliveryData['customer']) ?>
                                <small style="display: block; font-size: 11px; color: #64748b; margin-top: 5px;">
                                    #<?= htmlspecialchars($deliveryData['delivery_number']) ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Total Pending Amount Card - Click to show modal -->
                <div class="folder-item" onclick="showTotalPendingModal()">
                    <div class="folder-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="folder-name">
                        Total Pending
                    </div>
                </div>

                <!-- Monthly Summary Folder -->
                <div class="folder-item" onclick="showSummaryModal()">
                    <div class="folder-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="folder-name">
                        Monthly Summary
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Total Pending Amount Modal -->
    <div id="totalPendingModal" class="system-modal-overlay">
        <div class="system-modal">
            <div class="system-modal-header">
                <i class="fas fa-coins"></i>
                <span>TOTAL PENDING REPORT</span>
            </div>
            <div class="system-modal-content">
                <div class="system-modal-message info">
                    <i class="fas fa-chart-line"></i>
                    <div class="message-text">
                        Total pending amount for <strong><?= htmlspecialchars($selectedMonth) ?></strong>
                        <div class="amount-display">
                            ₱<?= number_format($totalPendingAmount, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="system-modal-footer">
                <button class="system-modal-btn primary" onclick="closeTotalPendingModal()">OK</button>
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
                            <i class="fas fa-file-signature"></i> Contracts Total
                        </div>
                        <div class="summary-value contract">
                            ₱<?= number_format($totalContractValue, 2) ?>
                        </div>
                    </div>
                    <div class="summary-row">
                        <div class="summary-label">
                            <i class="fas fa-coins"></i> Pending Sales Total
                        </div>
                        <div class="summary-value pending">
                            ₱<?= number_format($totalPendingAmount, 2) ?>
                        </div>
                    </div>
                    <div class="divider-line"></div>
                    <div class="summary-row grand-total-row">
                        <div class="summary-label">
                            <i class="fas fa-calculator"></i> GRAND TOTAL
                        </div>
                        <div class="summary-value grand">
                            ₱<?= number_format($grandTotal, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="system-modal-footer">
                <button class="system-modal-btn primary" onclick="closeSummaryModal()">CLOSE</button>
            </div>
        </div>
    </div>

    <!-- Contracts Modal -->
    <div id="contractsModal" class="system-modal-overlay">
        <div class="system-modal modal-large">
            <div class="system-modal-header">
                <i class="fas fa-file-signature"></i>
                <span>CONTRACTS - <?= htmlspecialchars($selectedMonth) ?></span>
            </div>
            <div class="system-modal-content">
                <!-- Total Contract Value Display -->
                <div class="total-contract-display">
                    <i class="fas fa-chart-line"></i> Total Contract Value:
                    <strong>₱<?= number_format($totalContractValue, 2) ?></strong>
                </div>

                <div style="margin-bottom: 15px;">
                    <button class="add-row-btn" onclick="addNewContractRow()">
                        <i class="fas fa-plus"></i> Add New Contract
                    </button>
                </div>
                <div style="overflow-x: auto;">
                    <table class="contracts-table" id="contracts-table">
                        <thead>
                            <tr>
                                <th>Contractor</th>
                                <th>Contract Address</th>
                                <th>Contract Value</th>
                                <th style="width: 120px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="contracts-tbody">
                            <?php if (!empty($contracts)): ?>
                                <?php foreach ($contracts as $contract): ?>
                                    <tr data-id="<?= htmlspecialchars($contract['id'] ?? '') ?>">
                                        <td class="contractor-cell">
                                            <span
                                                class="display-mode"><?= htmlspecialchars($contract['contractor'] ?? '') ?></span>
                                            <input type="text" class="edit-mode contractor-input" style="display: none;"
                                                value="<?= htmlspecialchars($contract['contractor'] ?? '') ?>">
                                        </td>
                                        <td class="address-cell">
                                            <span
                                                class="display-mode"><?= nl2br(htmlspecialchars($contract['contract_address'] ?? '')) ?></span>
                                            <textarea class="edit-mode address-input" rows="2"
                                                style="display: none; resize: vertical;"><?= htmlspecialchars($contract['contract_address'] ?? '') ?></textarea>
                                        </td>
                                        <td class="value-cell">
                                            <span
                                                class="display-mode">₱<?= number_format(floatval($contract['contract_value'] ?? 0), 2) ?></span>
                                            <input type="number" class="edit-mode value-input" step="0.01"
                                                style="display: none;"
                                                value="<?= floatval($contract['contract_value'] ?? 0) ?>">
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="edit-btn" onclick="editContractRow(this)">Edit</button>
                                                <button class="delete-row-btn" onclick="deleteContractRow(this)">Delete</button>
                                            </div>
                                            <div class="action-buttons edit-actions" style="display: none;">
                                                <button class="save-row-btn" onclick="saveContractRow(this)">Save</button>
                                                <button class="cancel-row-btn" onclick="cancelEditRow(this)">Cancel</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="no-data-row">
                                    <td colspan="4" style="text-align: center; color: #64748b;">No contracts found for this
                                        month. Click "Add New Contract" to add.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="system-modal-footer">
                <button class="system-modal-btn" onclick="closeContractsModal()">CLOSE</button>
            </div>
        </div>
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
        const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        const selectedMonth = '<?= htmlspecialchars($selectedMonth) ?>';
        let totalContractValue = <?= $totalContractValue ?>;
        let totalPendingValue = <?= $totalPendingAmount ?>;

        function viewCustomerOrders(deliveryNumber) {
            window.location.href = 'pending_orders.php?delivery_number=' + encodeURIComponent(deliveryNumber);
        }

        // Total Pending Modal Functions
        function showTotalPendingModal() {
            const modal = document.getElementById('totalPendingModal');
            modal.style.display = 'flex';
        }

        function closeTotalPendingModal() {
            const modal = document.getElementById('totalPendingModal');
            modal.style.display = 'none';
        }

        // Summary Modal Functions
        function showSummaryModal() {
            const modal = document.getElementById('summaryModal');
            modal.style.display = 'flex';
        }

        function closeSummaryModal() {
            const modal = document.getElementById('summaryModal');
            modal.style.display = 'none';
        }

        // Update summary modal values (called after contract changes)
        function updateSummaryModalValues() {
            const summaryContractSpan = document.querySelector('#summaryModal .summary-value.contract');
            const summaryPendingSpan = document.querySelector('#summaryModal .summary-value.pending');
            const summaryGrandSpan = document.querySelector('#summaryModal .summary-value.grand');

            if (summaryContractSpan) {
                summaryContractSpan.innerText = '₱' + totalContractValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (summaryPendingSpan) {
                summaryPendingSpan.innerText = '₱' + totalPendingValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (summaryGrandSpan) {
                const grandTotal = totalContractValue + totalPendingValue;
                summaryGrandSpan.innerText = '₱' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        // Contracts Modal Functions
        function showContractsModal() {
            const modal = document.getElementById('contractsModal');
            modal.style.display = 'flex';
        }

        function closeContractsModal() {
            const modal = document.getElementById('contractsModal');
            modal.style.display = 'none';
        }

        // Update total contract value display
        function updateTotalContractValue() {
            const totalDisplay = document.querySelector('.total-contract-display strong');
            if (totalDisplay) {
                totalDisplay.innerText = '₱' + totalContractValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            // Also update summary modal
            updateSummaryModalValues();
        }

        // Edit contract row
        function editContractRow(button) {
            const row = button.closest('tr');
            const displaySpans = row.querySelectorAll('.display-mode');
            const editInputs = row.querySelectorAll('.edit-mode');
            const normalActions = row.querySelector('.action-buttons:not(.edit-actions)');
            const editActions = row.querySelector('.edit-actions');

            displaySpans.forEach(span => span.style.display = 'none');
            editInputs.forEach(input => input.style.display = 'block');
            normalActions.style.display = 'none';
            editActions.style.display = 'flex';
        }

        // Cancel edit
        function cancelEditRow(button) {
            const row = button.closest('tr');
            const displaySpans = row.querySelectorAll('.display-mode');
            const editInputs = row.querySelectorAll('.edit-mode');
            const normalActions = row.querySelector('.action-buttons:not(.edit-actions)');
            const editActions = row.querySelector('.edit-actions');

            const contractorSpan = row.querySelector('.contractor-cell .display-mode');
            const contractorInput = row.querySelector('.contractor-cell .contractor-input');
            if (contractorSpan && contractorInput) {
                contractorInput.value = contractorSpan.innerText;
            }

            const addressSpan = row.querySelector('.address-cell .display-mode');
            const addressInput = row.querySelector('.address-cell .address-input');
            if (addressSpan && addressInput) {
                addressInput.value = addressSpan.innerText.replace(/<br\s*\/?>/g, '\n');
            }

            const valueSpan = row.querySelector('.value-cell .display-mode');
            const valueInput = row.querySelector('.value-cell .value-input');
            if (valueSpan && valueInput) {
                const valueText = valueSpan.innerText.replace('₱', '').replace(/,/g, '');
                valueInput.value = parseFloat(valueText) || 0;
            }

            displaySpans.forEach(span => span.style.display = 'block');
            editInputs.forEach(input => input.style.display = 'none');
            normalActions.style.display = 'flex';
            editActions.style.display = 'none';
        }

        // Save contract row
        async function saveContractRow(button) {
            const row = button.closest('tr');
            const contractorInput = row.querySelector('.contractor-input');
            const addressInput = row.querySelector('.address-input');
            const valueInput = row.querySelector('.value-input');
            const contractId = row.dataset.id || null;
            const oldValueSpan = row.querySelector('.value-cell .display-mode');
            let oldValue = 0;

            if (oldValueSpan) {
                const oldValueText = oldValueSpan.innerText.replace('₱', '').replace(/,/g, '');
                oldValue = parseFloat(oldValueText) || 0;
            }

            const contractor = contractorInput ? contractorInput.value.trim() : '';
            const address = addressInput ? addressInput.value.trim() : '';
            const newValue = valueInput ? parseFloat(valueInput.value) : 0;

            if (!contractor) {
                showMessage('Validation Error', 'Contractor name is required.', 'error');
                return;
            }

            if (newValue <= 0) {
                showMessage('Validation Error', 'Contract value must be greater than 0.', 'error');
                return;
            }

            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            button.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'save_single_contract');
                formData.append('contract_id', contractId);
                formData.append('contractor', contractor);
                formData.append('contract_address', address);
                formData.append('contract_value', newValue);
                formData.append('contract_m_y', selectedMonth);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/contract_operations.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    if (contractId) {
                        totalContractValue = totalContractValue - oldValue + newValue;
                    } else {
                        totalContractValue += newValue;
                    }
                    updateTotalContractValue();

                    const contractorSpan = row.querySelector('.contractor-cell .display-mode');
                    const addressSpan = row.querySelector('.address-cell .display-mode');
                    const valueSpan = row.querySelector('.value-cell .display-mode');

                    if (contractorSpan) contractorSpan.innerText = contractor;
                    if (addressSpan) addressSpan.innerText = address;
                    if (valueSpan) valueSpan.innerText = '₱' + newValue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    if (!contractId && data.new_id) {
                        row.dataset.id = data.new_id;
                    }

                    cancelEditRow(button);
                    showMessage('Success', 'Contract saved successfully!', 'success');
                } else {
                    showMessage('Error', data.message || 'Failed to save contract', 'error');
                }
            } catch (err) {
                console.error('Save error:', err);
                showMessage('Network Error', 'Connection error: ' + err.message, 'error');
            } finally {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }

        // Add new contract row
        function addNewContractRow() {
            const tbody = document.getElementById('contracts-tbody');
            const noDataRow = document.getElementById('no-data-row');

            if (noDataRow) {
                noDataRow.remove();
            }

            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td class="contractor-cell">
                    <span class="display-mode" style="display: none;"></span>
                    <input type="text" class="edit-mode contractor-input" style="display: block;" placeholder="Enter contractor name">
                </td>
                <td class="address-cell">
                    <span class="display-mode" style="display: none;"></span>
                    <textarea class="edit-mode address-input" rows="2" style="display: block; resize: vertical;" placeholder="Enter contract address"></textarea>
                </td>
                <td class="value-cell">
                    <span class="display-mode" style="display: none;"></span>
                    <input type="number" class="edit-mode value-input" step="0.01" style="display: block;" placeholder="0.00">
                </td>
                <td>
                    <div class="action-buttons" style="display: none;">
                        <button class="edit-btn" onclick="editContractRow(this)">Edit</button>
                        <button class="delete-row-btn" onclick="deleteContractRow(this)">Delete</button>
                    </div>
                    <div class="action-buttons edit-actions" style="display: flex;">
                        <button class="save-row-btn" onclick="saveContractRow(this)">Save</button>
                        <button class="cancel-row-btn" onclick="cancelNewContractRow(this)">Cancel</button>
                    </div>
                </td>
            `;
            tbody.appendChild(newRow);
        }

        // Cancel new contract row
        function cancelNewContractRow(button) {
            const row = button.closest('tr');
            const tbody = document.getElementById('contracts-tbody');
            row.remove();

            if (tbody.children.length === 0) {
                tbody.innerHTML = `
                    <tr id="no-data-row">
                        <td colspan="4" style="text-align: center; color: #64748b;">No contracts found for this month. Click "Add New Contract" to add.</td>
                    </tr>
                `;
            }
        }

        // Delete contract row
        async function deleteContractRow(button) {
            const row = button.closest('tr');
            const contractId = row.dataset.id;
            const valueSpan = row.querySelector('.value-cell .display-mode');
            let contractValue = 0;

            if (valueSpan) {
                const valueText = valueSpan.innerText.replace('₱', '').replace(/,/g, '');
                contractValue = parseFloat(valueText) || 0;
            }

            showConfirmModal('Confirm Delete', 'Are you sure you want to delete this contract?', async () => {
                if (contractId) {
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    button.disabled = true;

                    try {
                        const formData = new FormData();
                        formData.append('action', 'delete_contract');
                        formData.append('contract_id', contractId);
                        formData.append('csrf_token', csrfToken);

                        const response = await fetch('../API/contract_operations.php', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            totalContractValue -= contractValue;
                            updateTotalContractValue();
                            row.remove();
                            showMessage('Success', 'Contract deleted successfully!', 'success');
                        } else {
                            showMessage('Error', data.message || 'Failed to delete contract', 'error');
                            button.innerHTML = 'Delete';
                            button.disabled = false;
                        }
                    } catch (err) {
                        console.error('Delete error:', err);
                        showMessage('Network Error', 'Connection error: ' + err.message, 'error');
                        button.innerHTML = 'Delete';
                        button.disabled = false;
                    }
                } else {
                    row.remove();
                }

                const tbody = document.getElementById('contracts-tbody');
                if (tbody.children.length === 0) {
                    tbody.innerHTML = `
                        <tr id="no-data-row">
                            <td colspan="4" style="text-align: center; color: #64748b;">No contracts found for this month. Click "Add New Contract" to add.</td>
                        </tr>
                    `;
                    totalContractValue = 0;
                    updateTotalContractValue();
                }
            });
        }

        // Confirm Modal
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
                        <button class="system-modal-btn" onclick="this.closest('.system-modal-overlay').remove()">CANCEL</button>
                        <button class="system-modal-btn primary" id="confirm-btn">CONFIRM</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);

            const confirmBtn = overlay.querySelector('#confirm-btn');
            confirmBtn.addEventListener('click', () => {
                overlay.remove();
                onConfirm();
            });
        }

        // Simple message modal
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
                        <button class="system-modal-btn primary" onclick="this.closest('.system-modal-overlay').remove()">OK</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);

            if (onOk) {
                const okBtn = overlay.querySelector('.system-modal-btn');
                okBtn.addEventListener('click', onOk, { once: true });
            }
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

        // Close modals when clicking outside
        window.onclick = function (event) {
            const totalModal = document.getElementById('totalPendingModal');
            if (event.target === totalModal) {
                closeTotalPendingModal();
            }
            const summaryModal = document.getElementById('summaryModal');
            if (event.target === summaryModal) {
                closeSummaryModal();
            }
            const contractsModal = document.getElementById('contractsModal');
            if (event.target === contractsModal) {
                closeContractsModal();
            }
        }

        // Close modals on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const totalModal = document.getElementById('totalPendingModal');
                if (totalModal.style.display === 'flex') {
                    closeTotalPendingModal();
                }
                const summaryModal = document.getElementById('summaryModal');
                if (summaryModal.style.display === 'flex') {
                    closeSummaryModal();
                }
                const contractsModal = document.getElementById('contractsModal');
                if (contractsModal.style.display === 'flex') {
                    closeContractsModal();
                }
            }
        });

        console.log('📱 Sidebar menu loaded - Left Side');
        console.log('📐 Desktop: Sidebar expanded | Mobile: Burger menu');
    </script>
</body>

</html>