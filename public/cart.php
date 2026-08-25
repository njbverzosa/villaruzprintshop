<?php
// public/cart.php

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
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, role FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($userRole === 'Customer') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$user = $userData;

$userAccNumber = $user['acc_number'] ?? '';
$userFullName = $user['f_name'] ?? '';

// Fetch user's address details from customers table
$stmt = $pdo->prepare("SELECT street, barangay, land_mark FROM customers WHERE acc_number = ?");
$stmt->execute([$userAccNumber]);
$userAddressData = $stmt->fetch(PDO::FETCH_ASSOC);

$userStreet = $userAddressData['street'] ?? '';
$userBarangay = $userAddressData['barangay'] ?? '';
$userLandMark = $userAddressData['land_mark'] ?? '';

// Check if user has both street and barangay (address is complete)
$hasCompleteAddress = !empty($userStreet) && !empty($userBarangay);

// Build full address for display
$userAddress = '';
if (!empty($userStreet) && !empty($userBarangay)) {
    $userAddress = $userStreet . ', ' . $userBarangay;
    if (!empty($userLandMark)) {
        $userAddress .= ', ' . $userLandMark;
    }
}

// Fetch ALL cart items for current user
$stmt = $pdo->prepare("SELECT * FROM cart WHERE acc_number = ? ORDER BY id ASC");
$stmt->execute([$userAccNumber]);
$cartItems = $stmt->fetchAll();

// Calculate cart totals
$totalItems = 0;
$totalAmount = 0;
foreach ($cartItems as $item) {
    $totalItems += $item['pieces'];
    $totalAmount += $item['total_amount'];
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Get cart item count for bottom nav badge
$cartCountStmt = $pdo->prepare("SELECT SUM(pieces) as total_items FROM cart WHERE acc_number = ?");
$cartCountStmt->execute([$userAccNumber]);
$cartCountResult = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
$cartTotalItems = intval($cartCountResult['total_items'] ?? 0);

// Check if address exists (both street and barangay must be present)
$hasAddress = $hasCompleteAddress;
$addressDisplay = !empty($userAddress) ? htmlspecialchars($userAddress) : 'No Saved Address';

// ==============================================
// CALCULATE DELIVERY FEE BASED ON BARANGAY
// ==============================================
$deliveryFee = 0;
$barangay = $userBarangay ?? '';

if (!empty($barangay)) {
    $barangayLower = strtolower(trim($barangay));
    $barangay15 = ['poblacion'];
    $barangay30 = ['bobonot', 'amalbalan', 'gais-guipe', 'gaisguipe', 'hermosa', 'petal'];

    if (in_array($barangayLower, $barangay15)) {
        $deliveryFee = 15;
    } elseif (in_array($barangayLower, $barangay30)) {
        $deliveryFee = 30;
    } else {
        $deliveryFee = 50;
    }
}

// Free delivery for orders ₱500 and above
if ($totalAmount >= 500 && !empty($barangay)) {
    $deliveryFee = 0;
}

// Calculate total with delivery fee
$totalWithDelivery = $totalAmount + $deliveryFee;
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

        .checkout-inputs {
            margin: 20px 0;
            transition: all 0.3s ease;
        }

        .checkout-inputs.hidden-fields {
            display: none;
        }

        .address-selection-group {
            margin-bottom: 15px;
        }

        .address-selection-group label {
            font-weight: 600;
            font-size: 14px;
            display: block;
            margin-bottom: 12px;
            color: #0f172a;
        }

        .address-selection-group label i {
            color: #3b82f6;
            margin-right: 6px;
        }

        .address-options {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
        }

        .address-option-btn {
            flex: 1;
            padding: 12px 16px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #475569;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            background: white;
            font-weight: 600;
            min-width: 0;
        }

        .address-option-btn:hover:not(:disabled) {
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .address-option-btn i {
            font-size: 14px;
        }

        .address-option-btn#savedAddressBtn.active {
            border-color: #059669;
            background: #f0fdf4;
            color: #065f46;
        }

        .address-option-btn#savedAddressBtn.active i {
            color: #059669;
        }

        .address-option-btn#newAddressBtn.active {
            border-color: #f59e0b;
            background: #fffbeb;
            color: #92400e;
        }

        .address-option-btn#newAddressBtn.active i {
            color: #f59e0b;
        }

        .address-option-btn.no-address {
            opacity: 0.6;
            cursor: not-allowed;
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: #94a3b8;
        }

        .address-option-btn.no-address:hover {
            transform: none;
            background: #f1f5f9;
            box-shadow: none;
        }

        .address-option-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        #savedAddressDisplay {
            animation: fadeIn 0.3s ease;
            padding: 14px 16px;
            background: #f0fdf4;
            border-radius: 10px;
            border: 2px solid #bbf7d0;
            margin-bottom: 14px;
        }

        #savedAddressDisplay .address-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
        }

        #savedAddressDisplay .address-header i {
            color: #059669;
            font-size: 16px;
        }

        #savedAddressDisplay .address-header .label {
            font-weight: 600;
            color: #065f46;
            font-size: 14px;
        }

        #savedAddressDisplay .address-text {
            color: #047857;
            font-size: 13px;
            padding-left: 30px;
            line-height: 1.5;
        }

        #newAddressInput {
            animation: fadeIn 0.3s ease;
            margin-bottom: 8px;
        }

        #newAddressInput .input-wrapper {
            position: relative;
        }

        #newAddressInput .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
        }

        #newAddressInput .input-wrapper input {
            width: 100%;
            padding: 14px 14px 14px 44px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        #newAddressInput .input-wrapper input:focus {
            outline: none;
            border-color: #f59e0b;
            background: white;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
        }

        #newAddressInput .input-wrapper input::placeholder {
            color: #94a3b8;
            font-weight: 400;
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

        .delivery-address-note {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 8px;
            padding-left: 4px;
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }

        .delivery-address-note i {
            color: #3b82f6;
            margin-top: 2px;
            font-size: 12px;
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

        .setup-btn {
            width: 100%;
            background: #f59e0b;
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

        .setup-btn:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
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
            color: #b6b6b8;
            margin-bottom: 20px;
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

        /* ========== TOASTS & ALERTS ========== */
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
            max-width: 90%;
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

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px 15px 20px;
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

            .address-option-btn {
                padding: 10px 12px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 12px 12px 16px;
            }

            body {
                padding-bottom: 60px;
            }

            .cart-item {
                padding: 12px 0;
                gap: 8px;
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

    <!-- ========== MAIN CONTENT ========== -->
    <main class="main-content">
        <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome">
                <h3><i class="fas fa-shopping-cart"></i> Cart</h3>
            </div>
            <div class="user-badge">
                <div class="avatar">
                    <?php echo strtoupper(substr($user['f_name'] ?? 'G', 0, 1)); ?>
                </div>
                <span class="name"><?php echo htmlspecialchars($user['f_name'] ?? 'Guest'); ?></span>
            </div>
        </div>

        <!-- Cart Grid -->
        <div class="cart-grid">
            <div class="cart-items" id="cartItemsContainer">
                <?php if (empty($cartItems)): ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-bag"></i>
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
                                <span style="font-size:13px; font-weight:600; color:#475569;">QTY:</span>
                                <button class="qty-btn decrement" data-id="<?php echo $item['id']; ?>">-</button>
                                <span class="qty-value" id="qty-<?php echo $item['id']; ?>"><?php echo $item['pieces']; ?></span>
                                <button class="qty-btn increment" data-id="<?php echo $item['id']; ?>">+</button>
                            </div>

                            <div class="cart-item-right">
                                <span class="item-total">₱ <?php echo number_format($item['total_amount'], 2); ?></span>
                                <button class="remove-btn remove-item" data-id="<?php echo $item['id']; ?>" title="Remove item">
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
                            <label><i class="fas fa-truck"></i> Add to Existing Order?</label>
                            <div class="input-wrapper">
                                <i class="fas fa-hashtag"></i>
                                <input type="text" id="existingDeliveryNumber"
                                    placeholder="Enter Delivery Number (e.g., VPSGM000001)" autocomplete="off">
                            </div>
                            <div id="existingOrderInfo" class="existing-order-info"></div>
                            <div class="existing-order-hint">
                                <i class="fas fa-info-circle"></i> Enter a delivery number to combine items with an existing order in your account
                            </div>
                        </div>

                        <div class="checkout-inputs" id="checkoutInputs">
                            <div class="address-selection-group">
                                <label>
                                    <i class="fas fa-map-pin"></i> Delivery Address
                                </label>

                                <div class="address-options">
                                    <button type="button" class="address-option-btn <?php echo $hasAddress ? '' : 'no-address'; ?>"
                                        id="savedAddressBtn"
                                        onclick="selectSavedAddress()"
                                        <?php echo $hasAddress ? '' : 'disabled'; ?>
                                        title="<?php echo $hasAddress ? 'Use your saved address' : 'No saved address available. Please set a new address first.'; ?>">
                                        <i class="fas <?php echo $hasAddress ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                        <?php echo $hasAddress ? 'My Saved Address' : 'No Saved Address'; ?>
                                    </button>
                                    <button type="button" class="address-option-btn active" id="newAddressBtn" onclick="selectNewAddress()">
                                        <i class="fas fa-plus-circle"></i>
                                        <?php echo $hasAddress ? 'Set New Address' : 'Enter Delivery Address'; ?>
                                    </button>
                                </div>

                                <div id="savedAddressDisplay" style="display: <?php echo $hasAddress ? 'block' : 'none'; ?>;">
                                    <div class="address-header">
                                        <i class="fas fa-home"></i>
                                        <span class="label">Saved Address</span>
                                    </div>
                                    <div class="address-text" id="savedAddressText">
                                        <?php echo htmlspecialchars($userAddress); ?>
                                    </div>
                                </div>

                                <div id="newAddressInput" style="display: <?php echo $hasAddress ? 'none' : 'block'; ?>;">
                                    <div class="input-wrapper">
                                        <i class="fas fa-location-dot"></i>
                                        <input type="text" id="deliveryAddress"
                                            placeholder="Enter your complete delivery address (Street, Purok, House No.)"
                                            required autocomplete="off"
                                            value="<?php echo $hasAddress ? '' : htmlspecialchars($userAddress); ?>"
                                            style="border-color: <?php echo $hasAddress ? '#e2e8f0' : '#f59e0b'; ?>;">
                                    </div>
                                </div>

                                <div class="delivery-address-note" id="addressNote">
                                    <i class="fas fa-info-circle"></i>
                                    <span id="addressNoteText">
                                        <?php if ($hasAddress): ?>
                                            Click "My Saved Address" to use your saved address or "Set New Address" to use a different one
                                        <?php else: ?>
                                            Please enter your complete delivery address. It will be saved for your future orders.
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <label style="font-weight: 600; font-size: 14px; display: block; margin-bottom: 8px; margin-top: 4px;">
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

                        <div class="summary-row">
                            <span>Total Items:</span>
                            <span id="totalItems"><?php echo $totalItems; ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">₱ <?php echo number_format($totalAmount, 2); ?></span>
                        </div>
                        <div class="summary-row" id="deliveryFeeRow">
                            <span>Delivery Fee:</span>
                            <span id="deliveryFeeDisplay">
                                <?php if ($deliveryFee > 0): ?>
                                    ₱ <?php echo number_format($deliveryFee, 2); ?>
                                <?php else: ?>
                                    <span style="color: #10b981;">FREE</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span id="totalAmountDisplay">₱ <?php echo number_format($totalWithDelivery, 2); ?></span>
                        </div>

                        <!-- Checkout Button - Conditional based on delivery address -->
                        <?php
                        $hasDeliveryAddress = !empty($userStreet) && !empty($userBarangay);
                        ?>

                        <?php if (!$hasDeliveryAddress): ?>
                            <button class="setup-btn" id="setupAccountBtn" onclick="window.location.href='account-details.php'">
                                <i class="fas fa-times-circle"></i> Setup your account
                            </button>
                        <?php else: ?>
                            <button class="checkout-btn" id="checkoutBtn">
                                <i class="fas fa-check-circle"></i> Submit Order
                            </button>
                        <?php endif; ?>
                        <button class="clear-cart-btn" id="clearCartBtn">
                            <i class="fas fa-trash-alt"></i> Clear Cart
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <nav class="bottom-nav">
        <a href="shop.php" class="nav-item">
            <i class="fas fa-store"></i>
            <span>Shop</span>
        </a>
        <a href="cart.php" class="nav-item active" id="cartNavItem">
            <i class="fas fa-shopping-cart"></i>
            <span>Cart</span>
            <?php if ($cartTotalItems > 0): ?>
                <span class="badge" id="cartBadge"><?php echo $cartTotalItems; ?></span>
            <?php else: ?>
                <span class="badge" id="cartBadge" style="display: none;">0</span>
            <?php endif; ?>
        </a>
        <a href="orders.php" class="nav-item">
            <i class="fas fa-truck"></i>
            <span>Orders</span>
        </a>
        <a href="delivered.php" class="nav-item">
            <i class="fas fa-box"></i>
            <span>Received</span>
        </a>
        <a href="account.php" class="nav-item">
            <i class="fas fa-th-large"></i>
            <span>Services</span>
        </a>
        <a href="closed.php" class="nav-item" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <!-- ========== CUSTOM ALERT ========== -->
    <div id="customAlert" class="custom-alert">
        <div class="custom-alert-content">
            <i class="fas fa-check-circle"></i>
            <h3 id="alertTitle">Order Confirmed!</h3>
            <p id="alertMessage">Thank you for your order! We will send a text message once your order is ready for delivery.</p>
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
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = document.getElementById('csrfToken').value;
            const accNum = '<?php echo htmlspecialchars($user['acc_number'] ?? ''); ?>';
            const subtotalAmount = <?php echo $totalAmount; ?>;
            const hasItems = <?php echo json_encode(!empty($cartItems)); ?>;
            const barangay = '<?php echo htmlspecialchars($barangay); ?>';

            const checkoutBtn = document.getElementById('checkoutBtn');
            const deliveryAddressInput = document.getElementById('deliveryAddress');
            const deliveryDateInput = document.getElementById('deliveryDate');
            const existingDeliveryNumberInput = document.getElementById('existingDeliveryNumber');
            const existingOrderInfoDiv = document.getElementById('existingOrderInfo');
            const checkoutInputsDiv = document.getElementById('checkoutInputs');

            let currentTotalAmount = subtotalAmount;
            let existingOrderData = null;

            // Address Selection State
            let savedAddress = '<?php echo htmlspecialchars($userAddress); ?>';
            let isUsingSavedAddress = <?php echo $hasAddress ? 'true' : 'false'; ?>;

            // ============================================================
            // ADDRESS SELECTION FUNCTIONS
            // ============================================================
            window.selectSavedAddress = function() {
                const hasAddress = <?php echo json_encode($hasAddress); ?>;

                if (!hasAddress) {
                    showToast('You don\'t have a saved address. Please enter your delivery address first.', 'warning');
                    window.selectNewAddress();
                    const deliveryInput = document.getElementById('deliveryAddress');
                    if (deliveryInput) {
                        setTimeout(() => deliveryInput.focus(), 300);
                    }
                    return;
                }

                isUsingSavedAddress = true;

                document.getElementById('savedAddressBtn').classList.add('active');
                document.getElementById('savedAddressBtn').classList.remove('no-address');
                document.getElementById('newAddressBtn').classList.remove('active');

                document.getElementById('savedAddressDisplay').style.display = 'block';
                document.getElementById('newAddressInput').style.display = 'none';

                document.getElementById('addressNoteText').textContent = 'Using your saved address';

                const deliveryInput = document.getElementById('deliveryAddress');
                if (deliveryInput) {
                    deliveryInput.value = savedAddress;
                    deliveryInput.style.borderColor = '#bbf7d0';
                }

                validateDeliveryInputs();
            }

            window.selectNewAddress = function() {
                isUsingSavedAddress = false;

                document.getElementById('newAddressBtn').classList.add('active');
                document.getElementById('savedAddressBtn').classList.remove('active');

                document.getElementById('savedAddressDisplay').style.display = 'none';
                document.getElementById('newAddressInput').style.display = 'block';

                const hasAddress = <?php echo json_encode($hasAddress); ?>;
                if (hasAddress) {
                    document.getElementById('addressNoteText').textContent = 'Enter your new delivery address';
                } else {
                    document.getElementById('addressNoteText').textContent = 'Please enter your complete delivery address. It will be saved for future orders.';
                }

                const deliveryInput = document.getElementById('deliveryAddress');
                if (deliveryInput) {
                    deliveryInput.value = '';
                    deliveryInput.style.borderColor = '#f59e0b';
                    deliveryInput.focus();
                }

                validateDeliveryInputs();
            }

            // ============================================================
            // UPDATE TOTALS WITH DELIVERY FEE
            // ============================================================
            function updateTotalsWithDelivery(subtotal, barangay) {
                let deliveryFee = 0;

                if (subtotal < 500 && barangay) {
                    const barangayLower = barangay.toLowerCase().trim();
                    const barangay15 = ['poblacion'];
                    const barangay30 = ['bobonot', 'amalbalan', 'gais-guipe', 'gaisguipe', 'hermosa', 'petal'];

                    if (barangay15.includes(barangayLower)) {
                        deliveryFee = 15;
                    } else if (barangay30.includes(barangayLower)) {
                        deliveryFee = 30;
                    } else {
                        deliveryFee = 50;
                    }
                }

                const total = subtotal + deliveryFee;

                const subtotalDisplay = document.getElementById('subtotal');
                if (subtotalDisplay) {
                    subtotalDisplay.textContent = '₱ ' + subtotal.toFixed(2);
                }

                const deliveryFeeDisplay = document.getElementById('deliveryFeeDisplay');
                if (deliveryFeeDisplay) {
                    deliveryFeeDisplay.textContent = deliveryFee > 0 ? '₱ ' + deliveryFee.toFixed(2) : 'FREE';
                    if (deliveryFee === 0) {
                        deliveryFeeDisplay.style.color = '#10b981';
                    } else {
                        deliveryFeeDisplay.style.color = '';
                    }
                }

                const totalDisplay = document.getElementById('totalAmountDisplay');
                if (totalDisplay) {
                    totalDisplay.textContent = '₱ ' + total.toFixed(2);
                }

                return { subtotal, deliveryFee, total };
            }

            // ============================================================
            // UPDATE TOTALS AFTER REMOVAL
            // ============================================================
            function updateTotalsAfterRemoval() {
                const items = document.querySelectorAll('.cart-item');
                let totalItems = 0;
                let totalAmount = 0;

                items.forEach(item => {
                    const qty = parseInt(item.querySelector('.qty-value').textContent) || 0;
                    const priceText = item.querySelector('.item-total').textContent.replace('₱ ', '').replace(/,/g, '');
                    const price = parseFloat(priceText) || 0;
                    totalItems += qty;
                    totalAmount += price;
                });

                document.getElementById('totalItems').textContent = totalItems;
                
                // Update delivery fee and total
                updateTotalsWithDelivery(totalAmount, barangay);

                const badge = document.getElementById('cartBadge');
                if (badge) {
                    badge.textContent = totalItems;
                    badge.style.display = totalItems > 0 ? 'inline-block' : 'none';
                }

                if (items.length === 0) {
                    const container = document.getElementById('cartItemsContainer');
                    container.innerHTML = `
                        <div class="empty-cart">
                            <i class="fas fa-shopping-bag"></i>
                            <p>Looks like you haven't added any items to your cart yet.</p>
                            <a href="shop.php" class="shop-now-btn">Shop Now</a>
                        </div>
                    `;
                    const summaries = document.querySelectorAll('.cart-summary');
                    summaries.forEach(sum => sum.style.display = 'none');
                    const deliveryDetails = document.querySelector('.cart-summary[style*="margin-bottom: 20px;"]');
                    if (deliveryDetails) deliveryDetails.style.display = 'none';
                }
            }

            // ============================================================
            // VALIDATE EXISTING DELIVERY NUMBER
            // ============================================================
            let validateTimeout;
            existingDeliveryNumberInput.addEventListener('input', function() {
                clearTimeout(validateTimeout);
                const deliveryNumber = this.value.trim();

                if (deliveryNumber === '') {
                    existingOrderInfoDiv.style.display = 'none';
                    existingOrderData = null;
                    checkoutInputsDiv.classList.remove('hidden-fields');
                    validateDeliveryInputs();
                    return;
                }

                validateTimeout = setTimeout(() => {
                    validateDeliveryNumber(deliveryNumber);
                }, 500);
            });

            async function validateDeliveryNumber(deliveryNumber) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'validate_delivery');
                    formData.append('delivery_number', deliveryNumber);
                    formData.append('acc_number', accNum);
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch('../Customer_API/cart_operations.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success && data.exists) {
                        existingOrderData = data;
                        existingOrderInfoDiv.className = 'existing-order-info success';
                        existingOrderInfoDiv.innerHTML = '<i class="fas fa-check-circle info-icon"></i> Add to existing order? Existing total: ' + formatAmount(
                            data.total_amount);
                        existingOrderInfoDiv.style.display = 'block';
                        checkoutInputsDiv.classList.add('hidden-fields');
                        if (checkoutBtn) checkoutBtn.disabled = false;
                    } else if (data.success && !data.exists) {
                        existingOrderData = null;
                        existingOrderInfoDiv.className = 'existing-order-info warning';
                        existingOrderInfoDiv.innerHTML = '<i class="fas fa-exclamation-triangle info-icon"></i> Delivery number not found in your account, this will be processed as a new order.';
                        existingOrderInfoDiv.style.display = 'block';
                        checkoutInputsDiv.classList.remove('hidden-fields');
                        validateDeliveryInputs();
                    } else if (!data.success && data.message) {
                        existingOrderData = null;
                        existingOrderInfoDiv.className = 'existing-order-info error';
                        existingOrderInfoDiv.innerHTML = '<i class="fas fa-exclamation-circle info-icon"></i> ' + data.message;
                        existingOrderInfoDiv.style.display = 'block';
                        checkoutInputsDiv.classList.remove('hidden-fields');
                        validateDeliveryInputs();
                    }
                } catch (error) {
                    console.error('Error:', error);
                    existingOrderData = null;
                    existingOrderInfoDiv.className = 'existing-order-info error';
                    existingOrderInfoDiv.innerHTML = '<i class="fas fa-exclamation-circle info-icon"></i> Error validating delivery number. Please try again.';
                    existingOrderInfoDiv.style.display = 'block';
                    checkoutInputsDiv.classList.remove('hidden-fields');
                    validateDeliveryInputs();
                }
            }

            // ============================================================
            // HELPER FUNCTIONS
            // ============================================================
            function validateDeliveryInputs() {
                if (existingOrderData && existingOrderData.exists) {
                    if (checkoutBtn) checkoutBtn.disabled = false;
                    return true;
                }

                const deliveryAddress = document.getElementById('deliveryAddress').value.trim();
                const deliveryDate = deliveryDateInput ? deliveryDateInput.value : '';

                if (isUsingSavedAddress && savedAddress.trim() !== '') {
                    if (checkoutBtn) checkoutBtn.disabled = false;
                    return true;
                }

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

            // ============================================================
            // TOAST & LOADING
            // ============================================================
            function showToast(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = `toast-notification toast-${type}`;
                const icon = type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' :
                    'exclamation-circle';
                toast.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
                if (type === 'warning') {
                    toast.style.background = '#f59e0b';
                }
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }

            function showLoading() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            }

            function hideLoading() {
                document.getElementById('loadingOverlay').style.display = 'none';
            }

            function showCustomAlert(title, message) {
                document.getElementById('alertTitle').innerHTML = title;
                document.getElementById('alertMessage').innerHTML = message;
                document.getElementById('customAlert').style.display = 'flex';
            }

            window.closeCustomAlert = function() {
                document.getElementById('customAlert').style.display = 'none';
                window.location.href = 'orders.php';
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

                    const response = await fetch('../Customer_API/cart_operations.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error('Server returned ' + response.status);
                    }

                    let data;
                    try {
                        data = await response.json();
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        setTimeout(() => location.reload(), 500);
                        return;
                    }

                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showToast(data.message || 'Operation failed', 'error');
                        hideLoading();
                    }
                } catch (err) {
                    console.error('Error:', err);
                    setTimeout(() => location.reload(), 500);
                }
            }

            // ============================================================
            // REMOVE ITEM - WITH IMMEDIATE FEEDBACK
            // ============================================================
            document.querySelectorAll('.remove-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const cartId = this.dataset.id;
                    if (!confirm('Remove this item from cart?')) return;

                    const itemElement = this.closest('.cart-item');
                    if (itemElement) {
                        itemElement.style.transition = 'opacity 0.3s';
                        itemElement.style.opacity = '0.5';
                    }

                    showLoading();

                    const formData = new FormData();
                    formData.append('action', 'remove');
                    formData.append('cart_id', cartId);
                    formData.append('csrf_token', csrfToken);

                    fetch('../Customer_API/cart_operations.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Server error');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.success) {
                            if (itemElement) {
                                setTimeout(() => {
                                    itemElement.remove();
                                    updateTotalsAfterRemoval();
                                    hideLoading();
                                }, 300);
                            }
                            showToast('Item removed from cart', 'success');
                        } else {
                            showToast(data?.message || 'Error removing item', 'error');
                            hideLoading();
                            setTimeout(() => location.reload(), 1000);
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        setTimeout(() => location.reload(), 500);
                    });
                });
            });

            async function clearCart() {
                if (!confirm('Are you sure you want to clear your entire cart?')) return;

                showLoading();
                try {
                    const formData = new FormData();
                    formData.append('action', 'clear_cart');
                    formData.append('acc_number', accNum);
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch('../Customer_API/cart_operations.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        showToast('Cart cleared successfully', 'success');
                        setTimeout(() => location.reload(), 1000);
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
                const existingDeliveryNumber = existingDeliveryNumberInput ? existingDeliveryNumberInput.value.trim() : '';

                if (existingOrderData && existingOrderData.exists) {
                    if (existingOrderData.status && existingOrderData.status.toUpperCase() === 'PAID') {
                        showToast('This order has already been paid/delivered. You cannot add items to it.', 'error');
                        return;
                    }

                    let deliveryAddress = isUsingSavedAddress ? savedAddress : document.getElementById('deliveryAddress').value
                        .trim();

                    const confirmed = confirm(
                        'Add Items to Existing Order\n\n' +
                        'Order #: ' + existingOrderData.delivery_number + '\n' +
                        'Customer: ' + existingOrderData.ordered_by + '\n' +
                        'Current Total: ' + formatAmount(existingOrderData.total_amount) + '\n' +
                        'Status: ' + existingOrderData.status + '\n' +
                        'Address: ' + deliveryAddress + '\n' +
                        'New Items Subtotal: ₱ ' + subtotalAmount.toFixed(2) + '\n' +
                        'New Total Amount: ₱ ' + (existingOrderData.total_amount + subtotalAmount).toFixed(2) + '\n\n' +
                        'Are you sure you want to add these items to the existing order?'
                    );

                    if (!confirmed) return;

                    showLoading();
                    try {
                        const formData = new FormData();
                        formData.append('action', 'checkout');
                        formData.append('acc_number', accNum);
                        formData.append('customer_name', existingOrderData.ordered_by);
                        formData.append('delivery_address', deliveryAddress);
                        formData.append('delivery_date', existingOrderData.delivery_date || '');
                        formData.append('delivery_fee', 0);
                        formData.append('total_amount_with_fee', subtotalAmount);
                        formData.append('csrf_token', csrfToken);
                        formData.append('existing_delivery_number', existingDeliveryNumber);
                        formData.append('address_mode', isUsingSavedAddress ? 'saved' : 'new');

                        const response = await fetch('../Customer_API/cart_operations.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.success) {
                            hideLoading();
                            showCustomAlert('Items Added to Existing Order!', 'Successfully added items to order #' + data
                                .delivery_number + '. Total amount updated to ' + formatAmount(data.new_total_amount) +
                                '.');
                        } else {
                            showToast(data.message || 'Error adding items to order', 'error');
                            hideLoading();
                        }
                    } catch (err) {
                        console.error('Error:', err);
                        showToast('Network error. Please try again.', 'error');
                        hideLoading();
                    }
                    return;
                }

                // New order
                const customerName = '<?php echo htmlspecialchars($userFullName); ?>';
                let deliveryAddress = '';

                if (isUsingSavedAddress && savedAddress.trim() !== '') {
                    deliveryAddress = savedAddress;
                } else {
                    deliveryAddress = document.getElementById('deliveryAddress').value.trim();
                }

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

                const addressType = isUsingSavedAddress ? 'Saved Address' : 'New Address';

                const confirmed = confirm(
                    '📋 Order Confirmation\n\n' +
                    'Customer: ' + customerName + '\n' +
                    'Address: ' + deliveryAddress + '\n' +
                    'Address Type: ' + addressType + '\n' +
                    'Delivery Date: ' + formattedDate + '\n' +
                    'Subtotal: ₱ ' + subtotalAmount.toFixed(2) + '\n' +
                    'Total Amount: ₱ ' + currentTotalAmount.toFixed(2) + '\n\n' +
                    'Are you sure you want to place this order?'
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
                    formData.append('address_mode', isUsingSavedAddress ? 'saved' : 'new');
                    formData.append('update_customer_address', 'true');

                    const response = await fetch('../Customer_API/cart_operations.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        hideLoading();
                        showCustomAlert('Order Confirmed!', 'Thank you for your order! Delivery #: ' + data.delivery_number +
                            '. We will send a text message once your order is ready for delivery.');
                    } else {
                        showToast(data.message || 'Error placing order', 'error');
                        hideLoading();
                    }
                } catch (err) {
                    console.error('Error:', err);
                    showToast('Network error. Please try again.', 'error');
                    hideLoading();
                }
            }

            // ============================================================
            // EVENT LISTENERS
            // ============================================================
            document.querySelectorAll('.decrement').forEach(btn => {
                btn.addEventListener('click', function() {
                    updateCartItem(this.dataset.id, 'decrement');
                });
            });

            document.querySelectorAll('.increment').forEach(btn => {
                btn.addEventListener('click', function() {
                    updateCartItem(this.dataset.id, 'increment');
                });
            });

            if (document.getElementById('clearCartBtn')) {
                document.getElementById('clearCartBtn').addEventListener('click', clearCart);
            }

            if (document.getElementById('checkoutBtn')) {
                document.getElementById('checkoutBtn').addEventListener('click', proceedToCheckout);
            }

            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            if (deliveryDateInput) {
                deliveryDateInput.min = today;
            }

            // Initialize address selection
            const hasAddress = <?php echo json_encode($hasAddress); ?>;
            const savedAddressValue = '<?php echo htmlspecialchars($userAddress); ?>';

            if (hasAddress && savedAddressValue && savedAddressValue.trim() !== '') {
                window.selectSavedAddress();
            } else {
                window.selectNewAddress();
                const savedBtn = document.getElementById('savedAddressBtn');
                savedBtn.disabled = true;
                savedBtn.classList.add('no-address');
                savedBtn.title = 'No saved address available. Please enter a new address first.';
            }

            if (hasItems) {
                if (deliveryAddressInput) deliveryAddressInput.addEventListener('input', validateDeliveryInputs);
                if (deliveryDateInput) deliveryDateInput.addEventListener('change', validateDeliveryInputs);
                validateDeliveryInputs();
            }
        });
    </script>

</body>

</html>