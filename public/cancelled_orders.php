<?php
// public/cancel_order.php

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

// Fetch user details from database - ADDED vip column
$userData = null;
if ($userRole === 'Customer') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, vip FROM customers WHERE id = ?");
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
// 4. UPDATE ONLINE TIME AFTER USER IS DEFINED
// ==============================================
date_default_timezone_set('Asia/Manila');
$currentTime = date('M j, g:i A'); // e.g., Aug 31, 2:30 PM

if ($userRole === 'Customer') {
    $updateStmt = $pdo->prepare("UPDATE customers SET online_time = ? WHERE id = ?");
    $updateStmt->execute([$currentTime, $userData['id']]);
} 


// ==============================================
// 4. HANDLE CANCEL ORDER POST (Non-AJAX fallback)
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = 'Security validation failed. Please try again.';
        header('Location: cancel_order.php');
        exit;
    }

    $deliveryNumber = $_POST['delivery_number'] ?? '';
    $cancelReason = $_POST['cancel_reason'] ?? '';
    $otherReason = $_POST['other_reason'] ?? '';

    if (empty($deliveryNumber)) {
        $_SESSION['error_message'] = 'Invalid order.';
        header('Location: cancel_order.php');
        exit;
    }

    try {
        // Verify order belongs to user and is cancellable
        $stmt = $pdo->prepare("SELECT status FROM for_deliveries WHERE delivery_number = ? AND acc_number = ?");
        $stmt->execute([$deliveryNumber, $accNumber]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $_SESSION['error_message'] = 'Order not found.';
            header('Location: cancel_order.php');
            exit;
        }

        $allowedStatuses = ['PENDING', 'PACKING'];
        if (!in_array(strtoupper($order['status']), $allowedStatuses)) {
            $_SESSION['error_message'] = 'This order cannot be cancelled. Current status: ' . $order['status'];
            header('Location: cancel_order.php');
            exit;
        }

        // Update order status to CANCELLED with reason
        if ($cancelReason === 'Other') {
            $stmt = $pdo->prepare("UPDATE for_deliveries 
                                   SET status = 'CANCELLED', 
                                       cancel_reason = ?, 
                                       other_reason = ? 
                                   WHERE delivery_number = ? AND acc_number = ?");
            $stmt->execute(['Other', $otherReason, $deliveryNumber, $accNumber]);
        } else {
            $stmt = $pdo->prepare("UPDATE for_deliveries 
                                   SET status = 'CANCELLED', 
                                       cancel_reason = ?, 
                                       other_reason = NULL 
                                   WHERE delivery_number = ? AND acc_number = ?");
            $stmt->execute([$cancelReason, $deliveryNumber, $accNumber]);
        }

        $_SESSION['success_message'] = 'Order ' . htmlspecialchars($deliveryNumber) . ' has been cancelled successfully.';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    }

    header('Location: cancel_order.php');
    exit;
}

// ==============================================
// 5. Display messages
// ==============================================
$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// ==============================================
// 6. Cart badge
// ==============================================
$cartCountStmt = $pdo->prepare("SELECT SUM(pieces) as total_items FROM cart WHERE acc_number = ?");
$cartCountStmt->execute([$accNumber]);
$cartCountResult = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
$cartTotalItems = intval($cartCountResult['total_items'] ?? 0);

// ==============================================
// 7. Fetch deliveries - ONLY CANCELLED
// ==============================================
$stmt = $pdo->prepare("SELECT delivery_number, delivery_date, total_amount, charge, status, ordered_by, delivery_address, date_time_sold, cancel_reason, other_reason FROM for_deliveries WHERE acc_number = ? AND status IN ('CANCELLED') ORDER BY id DESC");
$stmt->execute([$accNumber]);
$deliveries = $stmt->fetchAll();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Function to format amount
function formatAmount($amount)
{
    return '₱ ' . number_format($amount, 2, '.', ',');
}

// Function to format delivery date
function formatDeliveryDateDisplay($date)
{
    if (empty($date) || $date === 'N/A')
        return 'N/A';
    if (preg_match('/^[A-Za-z]{3}, \d{1,2} [A-Za-z]{3} \d{4}$/', $date)) {
        return $date;
    }
    $timestamp = strtotime($date);
    if ($timestamp) {
        return date('D, j M Y', $timestamp);
    }
    return 'N/A';
}

// Fetch products for each delivery
function getOrderProducts($pdo, $deliveryNumber)
{
    $stmt = $pdo->prepare("SELECT product_name, pieces, unit, selling_price, total_amount FROM order_status_history WHERE delivery_number = ?");
    $stmt->execute([$deliveryNumber]);
    return $stmt->fetchAll();
}

// Check if user is VIP
$isVip = isset($user['vip']) && $user['vip'] == 1;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Cancel Orders | Villaruz Print Shop & General Merchandise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== RESET & BASE STYLES ========== */
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
            padding-bottom: 70px;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }

        input,
        textarea,
        [contenteditable="true"] {
            user-select: text;
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            padding: 20px 20px 30px;
            overflow-y: auto;
            background: #f1f5f9;
        }

        /* ========== DASHBOARD HEADER ========== */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: #ffffff;
            padding: 18px 25px;
            border-radius: 2px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .welcome h3 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }

        .welcome h3 i {
            color: #3b82f6;
            margin-left: 8px;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f1f5f9;
            padding: 6px 14px 6px 10px;
            border-radius: 5px;
        }

        .user-badge .avatar {
            width: 32px;
            height: 32px;
            border-radius: 20px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }

        .user-badge .name {
            font-size: 13px;
            font-weight: 500;
            color: #0f172a;
        }

        /* ========== ALERT MESSAGES ========== */
        .alert {
            padding: 14px 18px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left-color: #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left-color: #ef4444;
        }

        .alert i {
            font-size: 18px;
        }

        /* ========== ORDER CARD ========== */
        .order-card {
            background: #ffffff;
            border-radius: 2px;
            border: 1px solid #aac9f3;
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            transition: all 0.2s ease;
        }

        .order-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        /* Order Header */
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 10px;
        }

        .order-header .order-info {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .order-header .order-number {
            font-weight: 600;
            font-size: 14px;
            color: #0f172a;
        }

        .order-header .order-date {
            font-size: 12px;
            color: #94a3b8;
        }

        /* Order Status Badge */
        .order-status {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 20px;
            text-transform: uppercase;
        }

        .order-status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .order-status.packing {
            background: #dbeafe;
            color: #1e40af;
        }

        .order-status.shipped {
            background: #dbeafe;
            color: #1e40af;
        }

        .order-status.ofd {
            background: #dbeafe;
            color: #1e40af;
        }

        .order-status.delivered {
            background: #d1fae5;
            color: #065f46;
        }

        .order-status.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        /* View Status Button */
        .view-status-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 16px;
            background: white;
            color: black;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* ========== STATUS TRACKER ========== */
        .status-tracker {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            margin: 0;
        }

        .status-tracker .status-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            flex: 1;
            position: relative;
        }

        .status-tracker .status-step .dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 3px solid #e2e8f0;
            background: #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .status-tracker .status-step .dot.active {
            border-color: #3b82f6;
            background: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
        }

        .status-tracker .status-step .dot.completed {
            border-color: #10b981;
            background: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2);
        }

        .status-tracker .status-step .dot.cancelled {
            border-color: #ef4444;
            background: #ef4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2);
        }

        .status-tracker .status-step .label {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 500;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-tracker .status-step .label.active {
            color: #3b82f6;
            font-weight: 600;
        }

        .status-tracker .status-step .label.completed {
            color: #10b981;
            font-weight: 600;
        }

        .status-tracker .status-step .label.cancelled {
            color: #ef4444;
            font-weight: 600;
        }

        .status-tracker .status-line {
            flex: 1;
            height: 2px;
            background: #e2e8f0;
            margin: 0 4px;
            position: relative;
            top: -10px;
        }

        .status-tracker .status-line.active {
            background: #3b82f6;
        }

        .status-tracker .status-line.completed {
            background: #10b981;
        }

        .status-tracker .status-line.cancelled {
            background: #ef4444;
        }

        /* ========== ORDER BODY ========== */
        .order-body {
            padding: 16px 18px;
        }

        .order-product-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .order-product-item:last-child {
            border-bottom: none;
        }

        .order-product-item .product-icon {
            width: 44px;
            height: 44px;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3b82f6;
            font-size: 18px;
            flex-shrink: 0;
        }

        .order-product-item .product-details {
            flex: 1;
        }

        .order-product-item .product-name {
            font-size: 14px;
            font-weight: 500;
            color: #0f172a;
        }

        .order-product-item .product-meta {
            font-size: 12px;
            color: #94a3b8;
        }

        .order-product-item .product-price {
            font-size: 14px;
            font-weight: 600;
            color: #3b82f6;
            white-space: nowrap;
        }

        /* Order Footer */
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            border-top: 1px solid #e2e8f0;
            background: #fafafa;
            flex-wrap: wrap;
            gap: 10px;
        }

        .order-footer-left {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .order-footer-right {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .order-footer .total-amount {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .order-footer .total-amount span {
            color: #3b82f6;
        }

        .order-footer .shipping-note {
            font-size: 11px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 2px;
        }

        .order-footer .shipping-note i {
            color: #f59e0b;
            font-size: 12px;
        }

        /* Empty state */
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 5px;
        }

        .empty-orders i {
            font-size: 60px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-orders h3 {
            font-size: 20px;
            color: #475569;
            margin-bottom: 10px;
        }

        .empty-orders p {
            color: #94a3b8;
            margin-bottom: 25px;
        }

        .shop-now-btn {
            display: inline-block;
            background: #5d93e9;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 40px;
            font-weight: 600;
            transition: 0.2s;
        }

        .shop-now-btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        /* Order number wrapper */
        .order-number-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .copy-number-btn {
            background: none;
            border: none;
            color: #0a101a;
            cursor: pointer;
            font-size: 14px;
            padding: 4px;
            transition: all 0.2s ease;
            border-radius: 4px;
            line-height: 1;
        }

        .copy-number-btn:hover {
            color: #065ff0;
            background: #eff6ff;
        }

        .copy-number-btn.copied {
            color: #10b981;
        }

        .copyable-number {
            user-select: text;
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
        }

        /* Copy toast */
        .copy-toast {
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: #0f172a;
            color: white;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            z-index: 9999;
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            pointer-events: none;
        }

        .copy-toast.show {
            opacity: 1;
            bottom: 90px;
        }

        /* Delivery Address */
        .delivery-address {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #f1f5f9;
            border-radius: 6px;
            width: 100%;
            font-size: 12px;
            color: #475569;
        }

        .delivery-address i {
            color: #3b82f6;
            font-size: 14px;
            flex-shrink: 0;
        }

        .delivery-address .address-text {
            flex: 1;
            word-break: break-word;
        }

        /* ========== CANCEL FORM STYLES ========== */
        .cancel-form-wrapper {
            padding: 16px 18px;
            background: #f1f5f9;
            border-top: 2px solid #e2e8f0;
        }

        .cancel-form-wrapper .form-title {
            color: #1e293b;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .cancel-form-wrapper .form-title i {
            margin-right: 8px;
            color: #ef4444;
        }

        .cancel-form-wrapper .form-group {
            margin-bottom: 12px;
        }

        .cancel-form-wrapper label {
            display: block;
            font-weight: 500;
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .cancel-form-wrapper label .required {
            color: #ef4444;
        }

        .cancel-form-wrapper select,
        .cancel-form-wrapper textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            background: white;
            transition: border-color 0.2s;
        }

        .cancel-form-wrapper select:focus,
        .cancel-form-wrapper textarea:focus {
            outline: 2px solid #3b82f6;
            outline-offset: 1px;
            border-color: transparent;
        }

        .cancel-form-wrapper textarea {
            resize: vertical;
            min-height: 80px;
        }

        .cancel-form-wrapper .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 12px;
        }

        .cancel-form-wrapper .btn {
            padding: 8px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .cancel-form-wrapper .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }

        .cancel-form-wrapper .btn-secondary:hover {
            background: #cbd5e1;
        }

        .cancel-form-wrapper .btn-danger {
            background: #ef4444;
            color: white;
        }

        .cancel-form-wrapper .btn-danger:hover {
            background: #dc2626;
        }

        .cancel-order-btn {
            background: #ef4444;
            color: white;
            padding: 6px 16px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .cancel-order-btn:hover {
            background: #dc2626;
            transform: scale(1.02);
        }

        /* ========== BOTTOM NAVIGATION ========== */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 0 12px;
            z-index: 1000;
            box-shadow: 0 -2px 15px rgba(0, 0, 0, 0.06);
            height: 65px;
        }

        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            color: #525f70;
            text-decoration: none;
            transition: all 0.3s ease;
            padding: 4px 16px;
            position: relative;
            min-width: 56px;
        }

        .bottom-nav .nav-item i {
            font-size: 25px;
            transition: all 0.3s ease;
        }

        .bottom-nav .nav-item span {
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
        }

        .bottom-nav .nav-item:hover {
            color: #3b82f6;
        }

        .bottom-nav .nav-item.active {
            color: #3b82f6;
        }

        .bottom-nav .nav-item .badge {
            position: absolute;
            top: 0;
            right: 4px;
            background: lightgreen;
            color: #020e20;
            font-size: 14px;
            font-weight: bold;
            padding: 1px 6px;
            border-radius: 20px;
            min-width: 12px;
            text-align: center;
            line-height: 14px;
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px 15px 20px;
            }

            .dashboard-header {
                padding: 14px 18px;
                flex-direction: row;
                flex-wrap: wrap;
                gap: 8px;
            }

            .welcome h3 {
                font-size: 17px;
            }

            .user-badge .name {
                font-size: 12px;
            }

            .order-header {
                padding: 12px 14px;
            }

            .order-header .order-info {
                gap: 10px;
            }

            .order-body {
                padding: 12px 14px;
            }

            .view-status-btn {
                font-size: 12px;
                padding: 5px 14px;
            }

            .order-footer {
                padding: 10px 14px;
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }

            .order-footer .total-amount {
                font-size: 14px;
            }

            .order-product-item .product-name {
                font-size: 13px;
            }

            .order-product-item .product-price {
                font-size: 13px;
            }

            .status-tracker {
                padding: 12px 14px;
                flex-wrap: nowrap;
                overflow-x: auto;
            }

            .status-tracker .status-step .label {
                font-size: 8px;
            }

            .status-tracker .status-step .dot {
                width: 16px;
                height: 16px;
            }

            .cancel-form-wrapper .form-actions {
                flex-direction: column;
            }

            .cancel-form-wrapper .btn {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 12px 12px 16px;
            }

            body {
                padding-bottom: 60px;
            }

            .order-header {
                flex-direction: column;
                align-items: stretch;
                gap: 6px;
            }

            .order-product-item {
                gap: 10px;
            }

            .order-product-item .product-icon {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }

            .order-product-item .product-name {
                font-size: 12px;
            }

            .order-product-item .product-price {
                font-size: 12px;
            }

            .order-footer {
                padding: 10px 14px;
                flex-direction: row;
                align-items: center;
                gap: 8px;
            }

            .order-footer .total-amount {
                font-size: 13px;
            }

            .order-footer-right .view-status-btn {
                font-size: 11px;
                padding: 5px 12px;
                white-space: nowrap;
            }

            .dashboard-header {
                padding: 12px 14px;
                border-radius: 5px;
            }

            .welcome h3 {
                font-size: 15px;
            }

            .user-badge .avatar {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }

            .user-badge .name {
                font-size: 11px;
            }

            .bottom-nav {
                padding: 4px 0 8px;
                height: 56px;
            }

            .bottom-nav .nav-item {
                padding: 2px 6px;
                min-width: 36px;
            }

            .bottom-nav .nav-item i {
                font-size: 18px;
            }

            .bottom-nav .nav-item span {
                font-size: 9px;
            }

            .bottom-nav .nav-item .badge {
                font-size: 10px;
                min-width: 14px;
                line-height: 14px;
                top: -2px;
                right: 0px;
                padding: 0 5px;
            }

            .status-tracker {
                padding: 10px 8px;
            }

            .status-tracker .status-step .label {
                font-size: 7px;
            }

            .status-tracker .status-step .dot {
                width: 14px;
                height: 14px;
                border-width: 2px;
            }

            .view-status-btn {
                font-size: 11px;
                padding: 5px 12px;
            }

            .cancel-form-wrapper {
                padding: 12px 14px;
            }

            .cancel-form-wrapper .form-actions {
                flex-direction: column;
            }

            .cancel-form-wrapper .btn {
                width: 100%;
                text-align: center;
            }
        }

        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .bottom-nav {
                padding-bottom: calc(12px + env(safe-area-inset-bottom));
            }
        }
         /* VIP Avatar Styles */
        .user-badge .avatar.vip {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            font-size: 12px;
            font-weight: 700;
        }

        .user-badge .vip-badge i {
            font-size: 10px;
        }
    </style>
</head>

<body>

    <!-- ========== MAIN CONTENT ========== -->
    <main class="main-content">
        <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">
        <input type="hidden" id="userAccNumber" value="<?php echo htmlspecialchars($accNumber); ?>">

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome">
                <h3><i class="fas fa-window-close"></i> Cancelled Orders</h3>
            </div>
            <div class="user-badge">
                <div class="avatar <?php echo (isset($user['vip']) && $user['vip'] == 1) ? 'vip' : ''; ?>">
                    <?php
                    $isVip = isset($user['vip']) && $user['vip'] == 1;

                    if ($isVip):
                        ?>
                        <i class="fas fa-crown"></i>
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['f_name'] ?? 'G', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <span class="name"><?php echo htmlspecialchars($user['f_name'] ?? 'Guest'); ?></span>
            </div>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($deliveries)): ?>
            <div class="empty-orders">
                <i class="fas fa-shopping-bag"></i>
                <h3>No orders yet</h3>
                <p>Start shopping to see your orders here!</p>
            </div>
        <?php else: ?>
            <?php foreach ($deliveries as $delivery):
                $products = getOrderProducts($pdo, $delivery['delivery_number']);
                $productCount = count($products);
                $shippingFee = floatval($delivery['charge'] ?? 0);

                // Determine status class
                $statusClass = strtolower($delivery['status']);
                $statusDisplay = ucfirst(strtolower($delivery['status']));
                $isCancelled = $statusClass === 'cancelled';
                $canCancel = in_array($statusClass, ['pending', 'packing']);
            ?>
                <div class="order-card" data-delivery-number="<?php echo htmlspecialchars($delivery['delivery_number']); ?>">
                    <!-- Order Header -->
                    <div class="order-header">
                        <div class="order-info">
                            <div class="order-number-wrapper">
                                <button class="copy-number-btn"
                                    onclick="copyDeliveryNumber('<?php echo htmlspecialchars($delivery['delivery_number']); ?>', this)"
                                    title="Copy delivery number">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <span class="order-number copyable-number"
                                    onclick="copyDeliveryNumber('<?php echo htmlspecialchars($delivery['delivery_number']); ?>', this)"
                                    style="cursor: pointer;">
                                    <?php echo htmlspecialchars($delivery['delivery_number']); ?>
                                </span>
                            </div>
                            <span class="order-date">
                                <i class="fas fa-truck"></i>
                                <?php echo formatDeliveryDateDisplay($delivery['delivery_date'] ?? 'This Day'); ?> - 8:00AM - 5:00PM
                            </span>
                            
                        </div>
                    </div>

                    <!-- Order Body - Products (All products displayed) -->
                    <div class="order-body">
                        <?php foreach ($products as $product): ?>
                            <div class="order-product-item">
                                <div class="product-icon">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="product-details">
                                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    <div class="product-meta">
                                        <?php echo htmlspecialchars($product['pieces']); ?> ×
                                        <?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?>
                                    </div>
                                </div>
                                <div class="product-price">
                                    ₱ <?php echo number_format($product['total_amount'] ?? 0, 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php
                    // Define status steps
                    $statusSteps = [
                        'pending' => ['label' => 'Pending', 'color' => '#f59e0b'],
                        'packing' => ['label' => 'Packing', 'color' => '#3b82f6'],
                        'shipped' => ['label' => 'Shipped', 'color' => '#3b82f6'],
                        'ofd' => ['label' => 'OFD', 'color' => '#10b981'],
                        'delivered' => ['label' => 'Delivered', 'color' => '#10b981']
                    ];

                    if ($isCancelled) {
                        $statusSteps['cancelled'] = ['label' => 'Cancelled', 'color' => '#ef4444'];
                    }

                    $currentStatus = $statusClass;
                    $statusKeys = array_keys($statusSteps);
                    $currentIndex = array_search($currentStatus, $statusKeys);
                    if ($currentIndex === false) $currentIndex = 0;
                    ?>
                    <div class="status-tracker">
                        <?php foreach ($statusSteps as $key => $step):
                            $stepIndex = array_search($key, $statusKeys);
                            $isActive = $stepIndex <= $currentIndex;
                            $isCurrent = $stepIndex == $currentIndex;
                            $isCompleted = $stepIndex < $currentIndex;
                            $statusClassDot = $isCancelled ? 'cancelled' : ($isCompleted ? 'completed' : ($isCurrent ? 'active' : ''));
                            $lineClass = $isCancelled ? 'cancelled' : ($isActive ? 'active' : '');
                        ?>
                            <div class="status-step">
                                <div class="dot <?php echo $statusClassDot; ?>"></div>
                                <span class="label <?php echo $statusClassDot; ?>"><?php echo $step['label']; ?></span>
                            </div>
                            <?php if ($stepIndex < count($statusSteps) - 1): ?>
                                <div class="status-line <?php echo $lineClass; ?>"></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Order Footer -->
                    <div class="order-footer">
                        <div class="order-footer-left">
                            <div class="total-amount">
                                Total: <span><?php echo formatAmount($delivery['total_amount']); ?></span>
                            </div>
                            <?php if ($shippingFee > 0): ?>
                                <div class="shipping-note">
                                    <?php echo formatAmount($shippingFee); ?> shipping fee is included
                                </div>
                            <?php else: ?>
                                <div class="shipping-note">
                                    <i class="fas fa-gift"></i> Free shipping applied
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    </div>

                    <!-- Delivery Address -->
                    <div class="delivery-address">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="address-text">
                            <?php echo !empty($delivery['delivery_address']) ? htmlspecialchars($delivery['delivery_address']) : 'No delivery address provided'; ?>
                        </span>
                    </div>

                    <!-- Display Cancellation Reason if Cancelled -->
                    <?php if ($isCancelled && !empty($delivery['cancel_reason'])): ?>
                        <div style="padding: 10px 18px; background: #fef2f2; border-top: 1px solid #fee2e2;">
                            <span style="font-size: 12px; color: #991b1b;">
                                Cancelled: <?php echo htmlspecialchars($delivery['cancel_reason']); ?>
                                <?php if (!empty($delivery['other_reason'])): ?>
                                    - <?php echo htmlspecialchars($delivery['other_reason']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

     <!-- ========== BOTTOM NAVIGATION ========== -->
    <nav class="bottom-nav">
        <a href="shop.php" class="nav-item">
            <i class="fas fa-store"></i>
            <span>Shop</span>
        </a>
        <a href="cart.php" class="nav-item">
            <i class="fas fa-shopping-cart"></i>
            <span>Cart</span>
            <?php if ($cartTotalItems > 0): ?>
                <span class="badge"><?php echo $cartTotalItems; ?></span>
            <?php endif; ?>
        </a>
        <a href="orders.php" class="nav-item">
            <i class="fas fa-truck"></i>
            <span>Orders</span>
        </a>
        <a href="account.php" class="nav-item active">
            <i class="fas fa-th-large"></i>
            <span>Services</span>
        </a>
        <a href="closed.php" class="nav-item" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <!-- Copy Toast -->
    <div class="copy-toast" id="copyToast">📋 Copied!</div>

    <script>
        const csrfToken = document.getElementById('csrfToken').value;
        const userAccNumber = document.getElementById('userAccNumber').value;

        // ============================================================
        // COPY DELIVERY NUMBER FUNCTION
        // ============================================================
        function copyDeliveryNumber(deliveryNumber, element) {
            navigator.clipboard.writeText(deliveryNumber).then(() => {
                const toast = document.getElementById('copyToast');
                if (toast) {
                    toast.textContent = '📋 Copied: ' + deliveryNumber;
                    toast.classList.add('show');
                }

                if (element) {
                    const icon = element.querySelector ? element.querySelector('i') : null;
                    if (icon) {
                        icon.className = 'fas fa-check';
                    }
                    element.classList.add('copied');
                }

                setTimeout(() => {
                    if (toast) {
                        toast.classList.remove('show');
                    }
                    if (element) {
                        const icon = element.querySelector ? element.querySelector('i') : null;
                        if (icon) {
                            icon.className = 'fas fa-copy';
                        }
                        element.classList.remove('copied');
                    }
                }, 2000);
            }).catch(() => {
                const input = document.createElement('input');
                input.value = deliveryNumber;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);

                const toast = document.getElementById('copyToast');
                if (toast) {
                    toast.textContent = '📋 Copied: ' + deliveryNumber;
                    toast.classList.add('show');
                    setTimeout(() => {
                        toast.classList.remove('show');
                    }, 2000);
                }
            });
        }

        // ============================================================
        // TOGGLE CANCEL FORM
        // ============================================================
        function toggleCancelForm(deliveryNumber) {
            const form = document.getElementById('cancelForm_' + deliveryNumber);
            if (form) {
                if (form.style.display === 'none' || form.style.display === '') {
                    document.querySelectorAll('.cancel-form-wrapper').forEach(function(el) {
                        if (el.id !== 'cancelForm_' + deliveryNumber) {
                            el.style.display = 'none';
                        }
                    });
                    form.style.display = 'block';
                    form.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                } else {
                    form.style.display = 'none';
                }
            }
        }

        // ============================================================
        // SHOW/HIDE OTHER REASON TEXTAREA
        // ============================================================
        document.querySelectorAll('select[name="cancel_reason"]').forEach(function(select) {
            select.addEventListener('change', function() {
                const deliveryNumber = this.id.replace('cancelReason_', '');
                const otherGroup = document.getElementById('otherReasonGroup_' + deliveryNumber);
                if (this.value === 'Other') {
                    otherGroup.style.display = 'block';
                } else {
                    otherGroup.style.display = 'none';
                }
            });
        });

        // ============================================================
        // VALIDATE CANCEL FORM - AJAX
        // ============================================================
        function validateCancelForm(deliveryNumber) {
            const reasonSelect = document.getElementById('cancelReason_' + deliveryNumber);
            const otherReason = document.getElementById('otherReason_' + deliveryNumber);

            if (!reasonSelect.value) {
                showToast('Please select a reason for cancellation.', 'error');
                reasonSelect.focus();
                return false;
            }

            if (reasonSelect.value === 'Other') {
                const otherText = otherReason.value.trim();
                if (!otherText) {
                    showToast('Please specify your reason in the text box.', 'error');
                    otherReason.focus();
                    return false;
                }
            }

            // Show loading state
            const submitBtn = document.querySelector('#cancelForm_' + deliveryNumber + ' .btn-danger');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;

            // Prepare data
            const data = {
                delivery_number: deliveryNumber,
                cancel_reason: reasonSelect.value,
                other_reason: otherReason.value,
                csrf_token: csrfToken
            };

            // Send AJAX request
            fetch('../Customer_API/cancel_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;

                    if (result.success) {
                        showToast(result.message, 'success');
                        document.getElementById('cancelForm_' + deliveryNumber).style.display = 'none';
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showToast(result.message, 'error');
                    }
                })
                .catch(error => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    showToast('An error occurred. Please try again.', 'error');
                    console.error('Error:', error);
                });

            return false;
        }

        // ============================================================
        // SHOW TOAST MESSAGE
        // ============================================================
        function showToast(message, type) {
            const toast = document.getElementById('copyToast');
            if (toast) {
                toast.textContent = message;
                toast.className = 'copy-toast';

                if (type === 'success') {
                    toast.style.background = '#10b981';
                } else if (type === 'error') {
                    toast.style.background = '#ef4444';
                } else {
                    toast.style.background = '#0f172a';
                }

                toast.classList.add('show');

                setTimeout(() => {
                    toast.classList.remove('show');
                    toast.style.background = '#0f172a';
                }, 3000);
            }
        }

        // ============================================================
        // CLOSE CANCEL FORM ON ESCAPE KEY
        // ============================================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.cancel-form-wrapper').forEach(function(el) {
                    el.style.display = 'none';
                });
            }
        });

        // ============================================================
        // CLOSE FORM ON CLICK OUTSIDE
        // ============================================================
        document.addEventListener('click', function(e) {
            document.querySelectorAll('.cancel-form-wrapper').forEach(function(el) {
                if (el.style.display === 'block') {
                    const isClickInside = el.contains(e.target);
                    const isClickOnButton = e.target.closest('.cancel-order-btn');
                    if (!isClickInside && !isClickOnButton) {
                        el.style.display = 'none';
                    }
                }
            });
        });

        console.log('📦 Cancel Orders page loaded');
        console.log('👤 Account number:', userAccNumber);
    </script>

</body>

</html>