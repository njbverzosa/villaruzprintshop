<?php
// web/cart.php
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

// Fetch cart items with verification that acc_number matches
$stmt = $pdo->prepare("SELECT * FROM cart WHERE acc_number = ? ORDER BY id ASC");
$stmt->execute([$accNumber]);
$cartItems = $stmt->fetchAll();

// Calculate totals
$totalItems = 0;
$totalAmount = 0;
foreach ($cartItems as $item) {
    $totalItems += (int) $item['pieces'];
    $totalAmount += (float) $item['total_amount'];
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Get cart item count
$cartCountStmt = $pdo->prepare("SELECT SUM(pieces) as total_items FROM cart WHERE acc_number = ?");
$cartCountStmt->execute([$accNumber]);
$cartCountResult = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
$cartTotalItems = intval($cartCountResult['total_items'] ?? 0);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Cart | Villaruz Print Shop & General Merchandise</title>
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
            font-size: 20px;
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

        
        /* ========== CART GRID ========== */
        .cart-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
        }

        .cart-items {
            background: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            transition: 0.2s;
            gap: 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-left {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 0;
        }

        .cart-item-left .product-name {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .cart-item-left .product-unit {
            font-size: 12px;
            color: #94a3b8;
        }

        .cart-item-center {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 12px;
            border-radius: 30px;
        }

        .cart-item-center .qty-btn {
            background: #f5f5f5;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 20px;
            font-weight: 300;
            color: black;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-item-center .qty-btn:hover {
            background: #3b82f6;
            color: white;
        }

        .cart-item-center .qty-value {
            font-size: 15px;
            font-weight: 600;
            min-width: 30px;
            text-align: center;
            color: #0f172a;
        }

        .cart-item-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cart-item-right .item-total {
            font-size: 16px;
            font-weight: 700;
            color: #3b82f6;
            min-width: 80px;
            text-align: right;
        }

        .cart-item-right .remove-btn {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 6px;
            transition: 0.2s;
            font-size: 16px;
        }

        .cart-item-right .remove-btn:hover {
            color: #ef4444;
            transform: scale(1.1);
        }

        .cart-summary {
            background: white;
            border-radius: 5px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        .cart-summary h3 {
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            color: #475569;
        }

        .summary-row.total {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            border-top: 2px solid #e2e8f0;
            margin-top: 10px;
            padding-top: 20px;
        }

        .summary-row.total span:last-child {
            color: #3b82f6;
        }

        .free-delivery-badge {
            color: #059669;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            margin: 10px 0;
        }

        .checkout-inputs {
            margin: 20px 0;
            transition: all 0.3s ease;
        }

        .checkout-inputs input {
            width: 100%;
            padding: 12px;
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
        }

        .checkout-inputs input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .checkout-inputs.hidden-fields {
            display: none;
        }

        .checkout-inputs input:disabled {
            background: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }

        .checkout-inputs input:disabled::placeholder {
            color: #94a3b8;
        }

        .address-selection-group {
            margin-bottom: 15px;
        }

        .address-selection-group label {
            font-weight: 600;
            font-size: 14px;
            display: block;
            margin-bottom: 8px;
            color: #0f172a;
        }

        .address-selection-group label i {
            color: #3b82f6;
            margin-right: 6px;
        }

        .address-selection-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.2s;
            background: #fafbfc;
        }

        .address-selection-group input:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .delivery-date-wrapper {
            position: relative;
            margin-top: 4px;
        }

        .delivery-date-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
        }

        .delivery-date-wrapper input[type="date"] {
            width: 100%;
            padding: 14px 14px 14px 44px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafbfc;
            color: #0f172a;
            cursor: pointer;
        }

        .delivery-date-wrapper input[type="date"]:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .delivery-date-wrapper input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0.5;
            padding: 4px;
            cursor: pointer;
        }

        .delivery-date-wrapper input[type="date"]:hover::-webkit-calendar-picker-indicator {
            opacity: 1;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .existing-order-section {
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .existing-order-section label {
            font-size: 13px;
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
            color: #0f172a;
        }

        .existing-order-section .input-wrapper {
            position: relative;
        }

        .existing-order-section .input-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }

        .existing-order-section input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
            background: white;
            transition: all 0.3s ease;
        }

        .existing-order-section input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .existing-order-section input::placeholder {
            color: #94a3b8;
        }

        .fees_info {
            padding: 10px 14px;
            font-size: 12px;
            border-radius: 8px;
            margin-top: 10px;
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #bbf7d0;
        }

        .existing-order-info {
            padding: 10px 14px;
            font-size: 12px;
            border-radius: 8px;
            margin-top: 10px;
            display: none;
        }

        .existing-order-info.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #bbf7d0;
            display: block;
        }

        .existing-order-info.warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            display: block;
        }

        .existing-order-info.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            display: block;
        }

        .existing-order-info .info-icon {
            margin-right: 6px;
        }

        .existing-order-hint {
            color: #047857;
            font-size: 11px;
            margin-top: 6px;
            padding-left: 4px;
        }

        .existing-order-hint i {
            color: #059669;
            margin-right: 4px;
        }

        .checkout-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.2s;
        }

        .checkout-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .checkout-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .clear-cart-btn {
            width: 100%;
            background: white;
            color: #ef4444;
            border: 2px solid #ef4444;
            padding: 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.2s;
        }

        .clear-cart-btn:hover {
            background: #ef4444;
            color: white;
        }

        .empty-cart {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-cart i {
            font-size: 80px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-cart h4 {
            font-size: 24px;
            color: #475569;
            margin-bottom: 10px;
        }

        .empty-cart p {
            color: #94a3b8;
            margin-bottom: 30px;
        }

        .shop-now-btn {
            display: inline-block;
            background: #3b82f6;
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

        .custom-alert {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 3000;
            justify-content: center;
            align-items: center;
        }

        .custom-alert-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            animation: modalSlideIn 0.3s ease;
        }

        .custom-alert-content i {
            font-size: 60px;
            color: #10b981;
            margin-bottom: 20px;
        }

        .custom-alert-content h3 {
            font-size: 24px;
            color: #0f172a;
            margin-bottom: 15px;
        }

        .custom-alert-content p {
            color: #475569;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .custom-alert-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .custom-alert-btn:hover {
            background: #2563eb;
            transform: scale(1.02);
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .loading-spinner i {
            font-size: 40px;
            color: #3b82f6;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .delivery-address-note {
            font-size: 11px;
            color: #94a3b8;
            margin-top: -6px;
            margin-bottom: 12px;
            padding-left: 4px;
        }

        .delivery-address-note i {
            color: #3b82f6;
            margin-right: 4px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
                padding-top: 20px;
            }

            .cart-grid {
                grid-template-columns: 1fr;
            }

            .cart-item {
                flex-wrap: wrap;
                padding: 14px 0;
                gap: 10px;
            }

            .cart-item-left {
                flex: 1 1 100%;
                order: 1;
            }

            .cart-item-center {
                order: 2;
            }

            .cart-item-right {
                order: 3;
                margin-left: auto;
            }

            .cart-summary {
                position: relative;
                top: 0;
            }

            .dashboard-header {
                padding: 15px 20px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 12px 12px 16px;
                padding-top: 15px;
            }

            body {
                padding-bottom: 60px;
            }

            .cart-item {
                padding: 12px 0;
                gap: 8px;
            }

            .dashboard-header {
                padding: 12px 15px;
                border-radius: 10px;
            }

            .welcome h4 {
                font-size: 14px;
            }

            .cart-item-left .product-name {
                font-size: 14px;
            }

            .cart-item-left .product-unit {
                font-size: 11px;
            }

            .cart-item-center {
                padding: 4px 10px;
                gap: 8px;
            }

            .cart-item-center .qty-btn {
                width: 24px;
                height: 24px;
                font-size: 16px;
            }

            .cart-item-center .qty-value {
                font-size: 15px;
                min-width: auto;
            }

            .cart-item-right .item-total {
                font-size: 14px;
                min-width: 60px;
            }

            .cart-item-right .remove-btn {
                font-size: 14px;
                padding: 4px;
            }

            .cart-summary {
                padding: 18px;
            }

            .cart-summary h3 {
                font-size: 17px;
            }

            .checkout-btn {
                font-size: 14px;
                padding: 12px;
            }

            .custom-alert-content {
                padding: 20px;
            }

            .custom-alert-content i {
                font-size: 48px;
            }

            .custom-alert-content h3 {
                font-size: 20px;
            }

            .address-options {
                display: flex;
                gap: 8px;
                flex-wrap: nowrap;
            }

            .address-option-btn {
                flex: 1;
                padding: 10px 8px;
                font-size: 11px;
                white-space: nowrap;
                min-width: 0;
                border-radius: 8px;
            }

            .address-option-btn i {
                font-size: 14px;
            }

            #newAddressInput .input-wrapper input {
                padding: 12px 12px 12px 40px;
                font-size: 13px;
            }

            .delivery-date-wrapper input[type="date"] {
                padding: 12px 12px 12px 40px;
                font-size: 13px;
            }

            .existing-order-section input {
                padding: 10px 10px 10px 34px;
                font-size: 12px;
            }
        }

        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .bottom-nav {
                padding-bottom: calc(12px + env(safe-area-inset-bottom));
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

        <!-- ========== MAIN CONTENT ========== -->
        <main class="main-content">
            <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">

            <div class="dashboard-header">
                <div class="header-left">
                    <!-- Burger Button (Mobile Only) -->
                    <button class="burger-btn" id="burgerBtn" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="welcome">
                        <h4>
                            Cart
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Cart Grid -->
            <div class="cart-grid">
                <div class="cart-items" id="cartItemsContainer">
                    <?php if (empty($cartItems)): ?>
                        <div class="empty-cart">
                            <i class="fas fa-shopping-cart"></i>
                            <h4>Your cart is empty</h4>
                            <p>Looks like you haven't added any items to your cart yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item" data-id="<?php echo $item['id']; ?>">
                                <div class="cart-item-left">
                                    <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div class="product-unit"><?php echo htmlspecialchars($item['unit'] ?? 'Pcs'); ?></div>
                                </div>

                                <div class="cart-item-center">
                                    <h5>QTY:</h5>
                                    <button class="qty-btn decrement" data-id="<?php echo $item['id']; ?>">-</button>
                                    <span class="qty-value"
                                        id="qty-<?php echo $item['id']; ?>"><?php echo $item['pieces']; ?></span>
                                    <button class="qty-btn increment" data-id="<?php echo $item['id']; ?>">+</button>
                                </div>

                                <div class="cart-item-right">
                                    <span class="item-total">₱ <?php echo number_format($item['total_amount'], 2); ?></span>
                                    <button class="remove-btn remove-item" data-id="<?php echo $item['id']; ?>"
                                        title="Remove item">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div>
                    <!-- Delivery Details -->
                    <?php if (!empty($cartItems)): ?>
                        <div class="cart-summary" style="margin-bottom: 20px;">
                            <h3>Delivery Details</h3>

                            <div class="existing-order-section">
                                <label>Add to Existing Order?</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-hashtag"></i>
                                    <input type="text" id="existingDeliveryNumber"
                                        placeholder="Enter Delivery Number (e.g., VPSGM000001)" autocomplete="off">
                                </div>
                                <div id="existingOrderInfo" class="existing-order-info"></div>
                                <div class="existing-order-hint">
                                    <i class="fas fa-info-circle"></i> Enter a delivery number to combine items with an
                                    existing order
                                </div>
                            </div>

                            <div class="checkout-inputs" id="checkoutInputs">
                                <!-- Customer Name -->
                                <div class="address-selection-group">
                                    <label>
                                        <i class="fas fa-user"></i> Customer Name
                                    </label>
                                    <input type="text" id="customerName" placeholder="Enter customer name" value="">
                                </div>

                                <!-- Delivery Address -->
                                <div class="address-selection-group">
                                    <label>
                                        <i class="fas fa-location-dot"></i> Delivery Address
                                    </label>
                                    <input type="text" id="deliveryAddress" placeholder="Enter delivery address" value="">
                                </div>

                                <label
                                    style="font-weight: 600; font-size: 14px; display: block; margin-bottom: 8px; margin-top: 4px;">
                                    <i class="fas fa-calendar-day" style="color: #3b82f6;"></i> Select Delivery Date
                                </label>
                                <div class="delivery-date-wrapper">
                                    <i class="fas fa-calendar-alt"></i>
                                    <input type="date" id="deliveryDate" required>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Order Summary -->
                    <?php if (!empty($cartItems)): ?>
                        <div class="cart-summary">
                            <h3>Order Summary</h3>
                            <!-- Current Total - Hidden by default, shown with green bg when delivery found -->
                            <div class="summary-row" id="currentTotalRow"
                                style="display: none; background: #d1fae5; padding: 8px 12px; border-radius: 8px; margin: 4px 0;">
                                <span>Current Total:</span>
                                <span id="currentTotalDisplay" style="font-weight: 700; color: #065f46;">₱ 0.00</span>
                            </div>
                            <div class="summary-row">
                                <span>Total Items:</span>
                                <span id="totalItems"><?php echo $totalItems; ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span id="subtotal">₱ <?php echo number_format($totalAmount, 2); ?></span>
                            </div>
                            <div class="summary-row total">
                                <span>New Total:</span>
                                <span id="totalAmountDisplay">₱ <?php echo number_format($totalAmount, 2); ?></span>
                            </div>
                            <button class="checkout-btn" id="checkoutBtn">
                                <i class="fas fa-check-circle"></i> Submit Order
                            </button>
                            <button class="clear-cart-btn" id="clearCartBtn">
                                <i class="fas fa-trash-alt"></i> Clear Cart
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- ========== CUSTOM ALERT ========== -->
    <div id="customAlert" class="custom-alert">
        <div class="custom-alert-content">
            <i class="fas fa-check-circle"></i>
            <h3 id="alertTitle">Order Confirmed!</h3>
            <p id="alertMessage">Thank you for your order! We will send a text message once your order is ready for
                delivery.</p>
            <button class="custom-alert-btn" onclick="closeCustomAlert()">OK</button>
        </div>
    </div>

    <!-- ========== LOADING OVERLAY ========== -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner"></i>
            <p>Processing...</p>
        </div>
    </div>

    <script>
        // ============================================================
        // SIDEBAR TOGGLE (Mobile Only)
        // ============================================================
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

        // ============================================================
        // MAIN CART FUNCTIONALITY
        // ============================================================
        document.addEventListener('DOMContentLoaded', function () {
            const csrfToken = document.getElementById('csrfToken').value;
            const accNum = '<?php echo htmlspecialchars($user['acc_number'] ?? ''); ?>';
            const subtotalAmount = <?php echo $totalAmount; ?>;
            const hasItems = <?php echo json_encode(!empty($cartItems)); ?>;

            const checkoutBtn = document.getElementById('checkoutBtn');
            const deliveryAddressInput = document.getElementById('deliveryAddress');
            const deliveryDateInput = document.getElementById('deliveryDate');
            const existingDeliveryNumberInput = document.getElementById('existingDeliveryNumber');
            const existingOrderInfoDiv = document.getElementById('existingOrderInfo');
            const checkoutInputsDiv = document.getElementById('checkoutInputs');

            let currentTotalAmount = subtotalAmount;
            let existingOrderData = null;

            // ============================================================
            // VALIDATE EXISTING DELIVERY NUMBER
            // ============================================================
            let validateTimeout;
            if (existingDeliveryNumberInput) {
                existingDeliveryNumberInput.addEventListener('input', function () {
                    clearTimeout(validateTimeout);
                    const deliveryNumber = this.value.trim();

                    if (deliveryNumber === '') {
                        existingOrderInfoDiv.style.display = 'none';
                        existingOrderData = null;
                        checkoutInputsDiv.classList.remove('hidden-fields');
                        updateTotalDisplay(null, subtotalAmount, subtotalAmount);
                        validateDeliveryInputs();
                        updateCheckoutButtonText(false);
                        return;
                    }

                    validateTimeout = setTimeout(function () {
                        validateDeliveryNumber(deliveryNumber);
                    }, 500);
                });
            }

            async function validateDeliveryNumber(deliveryNumber) {
                try {
                    console.log('🔍 Validating delivery number:', deliveryNumber);

                    const formData = new FormData();
                    formData.append('action', 'validate_delivery');
                    formData.append('delivery_number', deliveryNumber);
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch('../API/cart_operations.php', {
                        method: 'POST',
                        body: formData
                    });

                    console.log('📡 Response status:', response.status);

                    const data = await response.json();
                    console.log('📊 Response data:', data);

                    if (data.success && data.exists) {
                        existingOrderData = data;
                        console.log('Delivery found:', data);

                        const status = (data.status || '').toUpperCase();
                        if (status === 'PAID' || status === 'COMPLETED' || status === 'DELIVERED' || status ===
                            'SHIPPED') {
                            existingOrderInfoDiv.className = 'existing-order-info error';
                            existingOrderInfoDiv.innerHTML =
                                'This order has already been ' +
                                data.status.toLowerCase() + '. You cannot add items to it.';
                            existingOrderInfoDiv.style.display = 'block';
                            checkoutInputsDiv.classList.remove('hidden-fields');
                            if (checkoutBtn) checkoutBtn.disabled = true;
                            updateCheckoutButtonText(false);
                            return;
                        }

                        const currentTotal = parseFloat(data.total_amount) || 0;
                        const newTotal = currentTotal + subtotalAmount;

                        existingOrderInfoDiv.className = 'existing-order-info success';
                        existingOrderInfoDiv.innerHTML =
                            '</strong>Customer: <strong>' + data.ordered_by + '</strong>';
                        existingOrderInfoDiv.style.display = 'block';

                        checkoutInputsDiv.classList.add('hidden-fields');
                        if (checkoutBtn) checkoutBtn.disabled = false;

                        updateCheckoutButtonText(true);
                        updateTotalDisplay(currentTotal, newTotal, subtotalAmount);

                        const customerNameInput = document.getElementById('customerName');
                        const deliveryAddressInput = document.getElementById('deliveryAddress');

                        if (customerNameInput && existingOrderData.ordered_by) {
                            customerNameInput.value = existingOrderData.ordered_by;
                        }

                        if (deliveryAddressInput && existingOrderData.delivery_address) {
                            deliveryAddressInput.value = existingOrderData.delivery_address;
                        }

                        if (deliveryDateInput && existingOrderData.delivery_date) {
                            deliveryDateInput.value = existingOrderData.delivery_date;
                        }

                    } else if (data.success && !data.exists) {
                        console.log('❌ Delivery not found');
                        existingOrderData = null;
                        existingOrderInfoDiv.className = 'existing-order-info warning';
                        existingOrderInfoDiv.innerHTML =
                            '<i class="fas fa-exclamation-triangle info-icon"></i> Delivery number not found. This will be processed as a new order.';
                        existingOrderInfoDiv.style.display = 'block';
                        checkoutInputsDiv.classList.remove('hidden-fields');
                        updateTotalDisplay(null, subtotalAmount, subtotalAmount);
                        validateDeliveryInputs();
                        updateCheckoutButtonText(false);
                    } else if (!data.success && data.message) {
                        console.log('⚠️ Error:', data.message);
                        existingOrderData = null;
                        existingOrderInfoDiv.className = 'existing-order-info error';
                        existingOrderInfoDiv.innerHTML = '<i class="fas fa-exclamation-circle info-icon"></i> ' + data
                            .message;
                        existingOrderInfoDiv.style.display = 'block';
                        checkoutInputsDiv.classList.remove('hidden-fields');
                        updateTotalDisplay(null, subtotalAmount, subtotalAmount);
                        validateDeliveryInputs();
                        updateCheckoutButtonText(false);
                    }
                } catch (error) {
                    console.error('❌ Validation error:', error);
                    existingOrderData = null;
                    existingOrderInfoDiv.className = 'existing-order-info error';
                    existingOrderInfoDiv.innerHTML =
                        '<i class="fas fa-exclamation-circle info-icon"></i> Error validating delivery number. Please try again.';
                    existingOrderInfoDiv.style.display = 'block';
                    checkoutInputsDiv.classList.remove('hidden-fields');
                    updateTotalDisplay(null, subtotalAmount, subtotalAmount);
                    validateDeliveryInputs();
                    updateCheckoutButtonText(false);
                }
            }

            // ============================================================
            // UPDATE TOTAL DISPLAY
            // ============================================================
            function updateTotalDisplay(currentTotal, newTotal, subtotal) {
                const totalDisplay = document.getElementById('totalAmountDisplay');
                const subtotalDisplay = document.getElementById('subtotal');
                const currentTotalRow = document.getElementById('currentTotalRow');
                const currentTotalDisplay = document.getElementById('currentTotalDisplay');

                if (totalDisplay) {
                    totalDisplay.textContent = formatAmount(newTotal);
                }
                if (subtotalDisplay) {
                    subtotalDisplay.textContent = formatAmount(subtotal);
                }

                if (currentTotalRow && currentTotalDisplay) {
                    if (currentTotal !== null && currentTotal !== undefined) {
                        currentTotalRow.style.display = 'flex';
                        currentTotalDisplay.textContent = formatAmount(currentTotal);
                        currentTotalRow.style.background = '#d1fae5';
                        currentTotalRow.style.padding = '8px 12px';
                        currentTotalRow.style.borderRadius = '8px';
                        currentTotalRow.style.margin = '4px 0';
                    } else {
                        currentTotalRow.style.display = 'none';
                    }
                }

                currentTotalAmount = newTotal;
            }

            // ============================================================
            // HELPER FUNCTIONS
            // ============================================================
            function validateDeliveryInputs() {
                if (existingOrderData && existingOrderData.exists) {
                    const status = (existingOrderData.status || '').toUpperCase();
                    if (status === 'PAID' || status === 'COMPLETED' || status === 'DELIVERED' || status ===
                        'SHIPPED') {
                        if (checkoutBtn) checkoutBtn.disabled = true;
                        return false;
                    }
                    if (checkoutBtn) checkoutBtn.disabled = false;
                    return true;
                }

                const deliveryAddress = document.getElementById('deliveryAddress').value.trim();
                const deliveryDate = deliveryDateInput ? deliveryDateInput.value : '';

                const isValid = deliveryAddress !== '' && deliveryDate !== '';
                if (checkoutBtn) checkoutBtn.disabled = !isValid;
                return isValid;
            }

            function formatAmount(amount) {
                return '₱ ' + parseFloat(amount).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function updateCheckoutButtonText(isExistingOrder) {
                const checkoutBtn = document.getElementById('checkoutBtn');
                if (checkoutBtn) {
                    if (isExistingOrder) {
                        checkoutBtn.innerHTML = '<i class="fas fa-plus-circle"></i> Add Now';
                    } else {
                        checkoutBtn.innerHTML = '<i class="fas fa-check-circle"></i> Submit Order';
                    }
                }
            }

            // ============================================================
            // TOAST & LOADING
            // ============================================================
            function showToast(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = 'toast-notification toast-' + type;
                const icon = type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' :
                    'exclamation-circle';
                toast.innerHTML = '<i class="fas fa-' + icon + '"></i> ' + message;
                if (type === 'warning') {
                    toast.style.background = '#f59e0b';
                }
                document.body.appendChild(toast);
                setTimeout(function () {
                    toast.style.animation = 'slideOut 0.3s ease';
                    setTimeout(function () {
                        toast.remove();
                    }, 300);
                }, 4000);
            }

            function showLoading() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            }

            function hideLoading() {
                document.getElementById('loadingOverlay').style.display = 'none';
            }

            // ============================================================
            // CART OPERATIONS
            // ============================================================
            async function updateCartItem(cartId, action) {
                showLoading();
                try {
                    const formData = new FormData();
                    formData.append('action', action);
                    formData.append('cart_id', cartId);
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch('../API/cart_operations.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        showToast(data.message || 'Error updating cart', 'error');
                    }
                } catch (err) {
                    console.error('Error:', err);
                    showToast('Network error. Please try again.', 'error');
                } finally {
                    hideLoading();
                }
            }

            async function clearCart() {
                if (!confirm('Are you sure you want to clear your entire cart?')) return;

                showLoading();
                try {
                    const formData = new FormData();
                    formData.append('action', 'clear_cart');
                    formData.append('acc_number', accNum);
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch('../API/cart_operations.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        showToast('Cart cleared successfully', 'success');
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        showToast(data.message || 'Error clearing cart', 'error');
                    }
                } catch (err) {
                    console.error('Error:', err);
                    showToast('Network error. Please try again.', 'error');
                } finally {
                    hideLoading();
                }
            }

            // ============================================================
            // CHECKOUT
            // ============================================================
            async function proceedToCheckout() {
                const existingDeliveryNumber = existingDeliveryNumberInput ? existingDeliveryNumberInput.value.trim() :
                    '';

                // If adding to existing order
                if (existingOrderData && existingOrderData.exists) {
                    const status = (existingOrderData.status || '').toUpperCase();
                    if (status === 'PAID' || status === 'COMPLETED' || status === 'DELIVERED' || status ===
                        'SHIPPED') {
                        showToast('This order has already been ' + status.toLowerCase() +
                            '. You cannot add items to it.', 'error');
                        return;
                    }

                    let deliveryAddress = existingOrderData.delivery_address || document.getElementById('deliveryAddress')
                        .value.trim();
                    const newTotal = parseFloat(existingOrderData.total_amount) + subtotalAmount;

                    const confirmed = confirm(
                        'Click OK to proceed!'
                    );

                    if (!confirmed) return;

                    showLoading();
                    try {
                        const formData = new FormData();
                        formData.append('action', 'add_to_existing_delivery');
                        formData.append('acc_number', accNum);
                        formData.append('delivery_number', existingDeliveryNumber);
                        formData.append('csrf_token', csrfToken);

                        const response = await fetch('../API/cart_operations.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        hideLoading();

                        if (data.success) {
                            showToast(
                                'Item(s) added!',
                                'success'
                            );
                            setTimeout(function () {
                                window.location.href = 'pending_folder.php';
                            }, 2000);
                        } else {
                            showToast(data.message || 'Error adding items to order', 'error');
                        }
                    } catch (err) {
                        console.error('Error:', err);
                        hideLoading();
                        showToast('Network error. Please try again.', 'error');
                    }
                    return;
                }

                // New order
                const customerName = document.getElementById('customerName').value.trim();
                let deliveryAddress = document.getElementById('deliveryAddress').value.trim();
                const deliveryDate = deliveryDateInput ? deliveryDateInput.value : '';

                if (!deliveryAddress) {
                    showToast('Please enter complete delivery address', 'error');
                    return;
                }

                if (!deliveryDate) {
                    showToast('Please select delivery date', 'error');
                    return;
                }

                const formattedDate = new Date(deliveryDate).toLocaleDateString('en-US', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });

                const confirmed = confirm(
                    'Click OK to proceed!'
                );

                if (!confirmed) return;

                showLoading();
                try {
                    const formData = new FormData();
                    formData.append('action', 'checkout');
                    formData.append('acc_number', accNum);
                    formData.append('customer_name', customerName);
                    formData.append('delivery_address', deliveryAddress);
                    formData.append('delivery_date', deliveryDate);
                    formData.append('delivery_fee', 0);
                    formData.append('total_amount_with_fee', currentTotalAmount);
                    formData.append('csrf_token', csrfToken);
                    formData.append('update_customer_address', 'true');

                    const response = await fetch('../API/cart_operations.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    hideLoading();

                    if (data.success) {
                        showToast(
                            'Order placed!',
                            'success'
                        );
                        setTimeout(function () {
                            window.location.href = 'pending_folder.php';
                        }, 2500);
                    } else {
                        showToast(data.message || 'Error placing order', 'error');
                    }
                } catch (err) {
                    console.error('Error:', err);
                    hideLoading();
                    showToast('Network error. Please try again.', 'error');
                }
            }

            // ============================================================
            // EVENT LISTENERS
            // ============================================================
            document.querySelectorAll('.decrement').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    updateCartItem(this.dataset.id, 'decrement');
                });
            });

            document.querySelectorAll('.increment').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    updateCartItem(this.dataset.id, 'increment');
                });
            });

            document.querySelectorAll('.remove-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (confirm('Remove this item from cart?')) {
                        updateCartItem(this.dataset.id, 'remove');
                    }
                });
            });

            if (document.getElementById('clearCartBtn')) {
                document.getElementById('clearCartBtn').addEventListener('click', clearCart);
            }

            if (document.getElementById('checkoutBtn')) {
                document.getElementById('checkoutBtn').addEventListener('click', proceedToCheckout);
            }

            const today = new Date().toISOString().split('T')[0];
            if (deliveryDateInput) {
                deliveryDateInput.min = today;
            }

            if (hasItems) {
                if (deliveryAddressInput) deliveryAddressInput.addEventListener('input', validateDeliveryInputs);
                if (deliveryDateInput) deliveryDateInput.addEventListener('change', validateDeliveryInputs);
                validateDeliveryInputs();
            }
        });

        console.log('📱 Sidebar menu loaded - Left Side');
        console.log('📐 Desktop: Sidebar expanded | Mobile: Burger menu');
    </script>
</body>

</html>