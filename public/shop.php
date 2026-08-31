<?php
// public/shop.php

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

// ==============================================
// 4. UPDATE ONLINE TIME AFTER USER IS DEFINED
// ==============================================
date_default_timezone_set('Asia/Manila');
$currentTime = date('M j, g:i A'); // e.g., Aug 31, 2:30 PM

if ($userRole === 'Customer') {
    $updateStmt = $pdo->prepare("UPDATE customers SET online_time = ? WHERE id = ?");
    $updateStmt->execute([$currentTime, $userData['id']]);
}

$user = $userData;

$updateStmt = $pdo->prepare("UPDATE customers SET online_time = ? WHERE id = ?");
$updateStmt->execute([$currentTime, $user['id']]);

// ==============================================
// 4. CART BADGE FOR BOTTOM BAR
// ==============================================
$cartCountStmt = $pdo->prepare("SELECT SUM(pieces) as total_items FROM cart WHERE acc_number = ?");
$cartCountStmt->execute([$user['acc_number']]);
$cartCountResult = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
$cartTotalItems = intval($cartCountResult['total_items'] ?? 0);

// Fetch all merchandise inventory
$stmt = $pdo->prepare("SELECT * FROM merchandise_inventory ORDER BY STR_TO_DATE(last_restocked, '%d %M %Y %h:%i %p') DESC");
$stmt->execute();
$allProducts = $stmt->fetchAll();

// Get product_name from URL parameter
$productNameFromUrl = isset($_GET['product_name']) ? rawurldecode(trim($_GET['product_name'])) : '';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is VIP
$isVip = isset($user['vip']) && $user['vip'] == 1;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Shop | Villaruz Print Shop & General Merchandise</title>
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

        /* ========== PRODUCTS GRID ========== */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 18px;
            margin-top: 10px;
            padding: 10px 0 20px;
        }

        .product-card {
            background: #ffffff;
            border-radius: 7px;
            padding: 18px 14px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .product-card:hover {
            border-color: #3b82f6;
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .product-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #0f172a;
            line-height: 1.3;
        }

        .product-unit {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 6px;
            background: #f1f5f9;
            padding: 2px 10px;
            border-radius: 20px;
            display: inline-block;
        }

        .product-price {
            font-size: 19px;
            font-weight: 800;
            color: #3b82f6;
            margin-bottom: 12px;
        }

        /* ========== QUANTITY SELECTOR ========== */
        .quantity-selector {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 12px;
            width: 100%;
        }

        .qty-btn {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-size: 16px;
            font-weight: bold;
            color: #3b82f6;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-btn:hover {
            background: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
        }

        .quantity-input {
            width: 60px;
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
            background: #f8fafc;
            transition: all 0.3s;
        }

        .quantity-input:focus {
            outline: none;
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* ========== BUTTONS ========== */
        .card-add-btn {
            background: #3b82f6;
            border: none;
            width: 100%;
            padding: 9px 0;
            border-radius: 25px;
            font-weight: 600;
            font-size: 12px;
            color: #ffffff;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 6px;
        }

        .card-add-btn:hover {
            background: #2563eb;
            transform: scale(0.97);
        }

        .card-add-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .card-desc-btn {
            background: #8b5cf6;
            border: none;
            width: 100%;
            padding: 9px 0;
            border-radius: 25px;
            font-weight: 600;
            font-size: 12px;
            color: #ffffff;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .card-desc-btn:hover {
            background: #7c3aed;
            transform: scale(0.97);
        }

        /* ========== SEARCH BAR ========== */
        .search-section {
            padding: 10px 0 5px 0;
        }

        .search-wrapper {
            display: flex;
            gap: 10px;
            align-items: center;
            position: relative;
        }

        .search-input {
            flex: 1;
            position: relative;
        }

        .search-input .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
            pointer-events: none;
            z-index: 1;
        }

        .search-input input {
            width: 100%;
            padding: 11px 45px 11px 42px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 28px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-input input:focus {
            outline: none;
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-input .clear-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 16px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 50%;
            display: none;
            z-index: 2;
        }

        .search-input .clear-btn:hover {
            color: #ef4444;
            background: #f1f5f9;
        }

        .search-input .clear-btn.visible {
            display: block;
            animation: fadeInBtn 0.2s ease;
        }

        @keyframes fadeInBtn {
            from {
                opacity: 0;
                transform: translateY(-50%) scale(0.8);
            }

            to {
                opacity: 1;
                transform: translateY(-50%) scale(1);
            }
        }

        .search-info {
            font-size: 13px;
            color: #64748b;
            margin-top: 6px;
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
        }

        .bottom-nav .nav-item span {
            font-size: 15px;
            font-weight: 500;
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

        /* ========== MODALS ========== */
        .desc-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1200;
            justify-content: center;
            align-items: center;
        }

        .desc-modal-content {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 28px;
            max-width: 460px;
            width: 90%;
            animation: modalSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        @keyframes modalSlideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .desc-modal-header {
            background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
            padding: 20px 24px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .desc-modal-header h3 {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-desc-modal {
            font-size: 28px;
            font-weight: 300;
            cursor: pointer;
            opacity: 0.8;
        }

        .close-desc-modal:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        .desc-modal-body {
            padding: 24px;
        }

        .product-info-section {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px 18px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
        }

        .product-detail-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .product-detail-row:last-child {
            border-bottom: none;
        }

        .product-detail-icon {
            width: 36px;
            height: 36px;
            background: #eff6ff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8b5cf6;
        }

        .product-detail-label {
            font-size: 10px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 500;
        }

        .product-detail-value {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
        }

        .description-section {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px 18px;
            border: 1px solid #e2e8f0;
        }

        .description-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }

        .description-title i {
            color: #8b5cf6;
            font-size: 16px;
        }

        .description-title span {
            font-weight: 600;
            color: #0f172a;
            font-size: 13px;
        }

        .description-text {
            color: #475569;
            line-height: 1.6;
            font-size: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .desc-modal-footer {
            padding: 16px 24px 24px;
            border-top: 1px solid #e2e8f0;
            background: #ffffff;
        }

        .close-desc-btn {
            width: 100%;
            background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
            border: none;
            padding: 12px;
            border-radius: 14px;
            color: white;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .close-desc-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
        }

        /* ========== IMAGE MODAL ========== */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .image-modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .image-modal-content img {
            width: 90%;
            height: auto;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
        }

        .image-modal-close {
            position: absolute;
            top: -15px;
            right: -15px;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            color: #1e293b;
            font-size: 24px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .image-modal-close:hover {
            background: #ffffff;
            transform: scale(1.1) rotate(90deg);
        }

        .image-modal-caption {
            position: absolute;
            bottom: -45px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            white-space: nowrap;
            background: rgba(0, 0, 0, 0.5);
            padding: 6px 18px;
            border-radius: 20px;
            backdrop-filter: blur(4px);
        }

        .product-image-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 6px;
        }

        .product-image-clickable {
            transition: transform 0.3s ease;
            border-radius: 8px;
        }

        .product-image-clickable:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        /* ========== TOAST ========== */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 14px 20px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 2000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            font-size: 14px;
        }

        .toast-success {
            background: #10b981;
        }

        .toast-error {
            background: #ef4444;
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
            padding: 20px 30px;
            border-radius: 12px;
            text-align: center;
        }

        .loading-spinner i {
            font-size: 36px;
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

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px 15px 20px;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 14px;
                padding: 8px 0 15px;
            }

            .dashboard-header {
                padding: 14px 18px;
                flex-wrap: wrap;
                gap: 8px;
            }

            .welcome h3 {
                font-size: 17px;
            }

            .user-badge .name {
                font-size: 12px;
            }

            .search-input input {
                padding: 10px 42px 10px 38px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 12px 12px 16px;
            }

            body {
                padding-bottom: 60px;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                gap: 12px;
            }

            .product-card {
                padding: 14px 10px;
                border-radius: 14px;
            }

            .product-title {
                font-size: 13px;
            }

            .product-price {
                font-size: 16px;
            }

            .quantity-input {
                width: 50px;
                font-size: 12px;
                padding: 5px;
            }

            .card-add-btn,
            .card-desc-btn {
                font-size: 11px;
                padding: 8px 0;
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

            .desc-modal-content {
                width: 95%;
                border-radius: 20px;
            }

            .desc-modal-header h3 {
                font-size: 17px;
            }

            .search-input input {
                font-size: 13px;
                padding: 9px 38px 9px 34px;
                border-radius: 24px;
            }

            .search-input .search-icon {
                font-size: 14px;
                left: 10px;
            }

            .search-input .clear-btn {
                right: 8px;
                font-size: 14px;
            }

            .search-info {
                font-size: 12px;
            }

            .image-modal-close {
                top: -35px;
                right: 25px;
                width: 20px;
                height: 20px;
                font-size: 20px;
            }

            .image-modal-caption {
                bottom: -38px;
                font-size: 13px;
                white-space: normal;
                max-width: 90%;
                padding: 4px 14px;
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

    <main class="main-content">
        <div class="dashboard-header">
            <div class="welcome">
                <h3><i class="fas fa-store"></i> Shop</h3>
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

        <!-- Search Bar -->
        <div class="search-section">
            <div class="search-wrapper">
                <div class="search-input">
                    <i class="fas fa-search search-icon" id="searchIcon"></i>
                    <input type="text" id="liveSearchInput" placeholder="Search products..." autocomplete="off"
                        value="<?php echo htmlspecialchars($productNameFromUrl); ?>">
                    <button class="clear-btn" id="clearSearchBtn" aria-label="Clear search">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="products-grid" id="productsGrid">
            <?php foreach ($allProducts as $product): ?>
                <div class="product-card" data-id="<?php echo $product['id']; ?>"
                    data-name="<?php echo strtolower(htmlspecialchars($product['product_name'])); ?>"
                    data-fullname="<?php echo htmlspecialchars($product['product_name']); ?>"
                    data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>"
                    data-unit="<?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?>"
                    data-price="<?php echo $product['selling_price']; ?>">

                    <div class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></div>
                    <div class="product-image-wrapper">
                        <img src="../Products/<?php echo htmlspecialchars($product['product_image']); ?>"
                            alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="product-image-clickable"
                            onclick="openImageModal('../Products/<?php echo htmlspecialchars($product['product_image']); ?>', '<?php echo htmlspecialchars($product['product_name']); ?>')"
                            style="width: 100px; height: auto; cursor: pointer;">
                    </div>
                    <div class="product-unit"><?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?></div>
                    <div class="product-price">₱ <?php echo number_format($product['selling_price'], 2); ?></div>

                    <div class="quantity-selector">
                        <button class="qty-btn decrement" data-id="<?php echo $product['id']; ?>">-</button>
                        <input type="number" class="quantity-input" id="qty-<?php echo $product['id']; ?>" value="1" min="1"
                            max="999">
                        <button class="qty-btn increment" data-id="<?php echo $product['id']; ?>">+</button>
                    </div>

                    <button class="card-add-btn add-to-cart-card" data-id="<?php echo $product['id']; ?>"
                        data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                        data-price="<?php echo $product['selling_price']; ?>"
                        data-unit="<?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?>">
                        <i class="fas fa-cart-plus"></i> Add
                    </button>

                    <button class="card-desc-btn desc-btn" data-id="<?php echo $product['id']; ?>"
                        data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                        data-unit="<?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?>"
                        data-price="<?php echo number_format($product['selling_price'], 2); ?>"
                        data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>">
                        <i class="fas fa-info-circle"></i> Info
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="shop.php" class="nav-item active">
            <i class="fas fa-store"></i>
            <span>Shop</span>
        </a>
        <a href="cart.php" class="nav-item" id="cartNavItem">
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
        <a href="account.php" class="nav-item">
            <i class="fas fa-th-large"></i>
            <span>Services</span>
        </a>
        <a href="closed.php" class="nav-item" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <!-- Description Modal -->
    <div id="descriptionModal" class="desc-modal">
        <div class="desc-modal-content">
            <div class="desc-modal-header">
                <h3><i class="fas fa-file-alt"></i> Product Info</h3>
                <span class="close-desc-modal">&times;</span>
            </div>
            <div class="desc-modal-body">
                <div class="product-info-section">
                    <div class="product-detail-row">
                        <div class="product-detail-icon"><i class="fas fa-box"></i></div>
                        <div class="product-detail-text">
                            <div class="product-detail-label">Product Name</div>
                            <div class="product-detail-value" id="descProductName">-</div>
                        </div>
                    </div>
                    <div class="product-detail-row">
                        <div class="product-detail-icon"><i class="fas fa-tag"></i></div>
                        <div class="product-detail-text">
                            <div class="product-detail-label">Unit</div>
                            <div class="product-detail-value" id="descProductUnit">-</div>
                        </div>
                    </div>
                    <div class="product-detail-row">
                        <div class="product-detail-icon"><i class="fas fa-coins"></i></div>
                        <div class="product-detail-text">
                            <div class="product-detail-label">Price</div>
                            <div class="product-detail-value" id="descProductPrice">-</div>
                        </div>
                    </div>
                </div>
                <div class="description-section">
                    <div class="description-title"><i class="fas fa-align-left"></i><span>Description</span></div>
                    <div class="description-text" id="descProductDescription">No description available.</div>
                </div>
            </div>
            <div class="desc-modal-footer">
                <button class="close-desc-btn"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal" onclick="closeImageModal()">
        <div class="image-modal-content" onclick="event.stopPropagation()">
            <img id="imageModalImg" src="" alt="Product Image">
            <div class="image-modal-caption" id="imageModalCaption">Product Name</div>
            <button class="image-modal-close" onclick="closeImageModal()">&times;</button>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner"></i>
            <p>Processing...</p>
        </div>
    </div>

    <script>
        const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        const accNum = '<?php echo htmlspecialchars($accNumber); ?>';
        const productNameFromUrl = <?php echo json_encode($productNameFromUrl); ?>;

        // ============================================================
        // QUANTITY CONTROLS
        // ============================================================
        document.querySelectorAll('.decrement').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const productId = this.dataset.id;
                const qtyInput = document.getElementById(`qty-${productId}`);
                if (qtyInput) {
                    let currentQty = parseInt(qtyInput.value) || 1;
                    if (currentQty > 1) {
                        qtyInput.value = currentQty - 1;
                    }
                }
            });
        });

        document.querySelectorAll('.increment').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const productId = this.dataset.id;
                const qtyInput = document.getElementById(`qty-${productId}`);
                if (qtyInput) {
                    let currentQty = parseInt(qtyInput.value) || 1;
                    if (currentQty < 999) {
                        qtyInput.value = currentQty + 1;
                    }
                }
            });
        });

        // ============================================================
        // SEARCH WITH CLEAR BUTTON
        // ============================================================
        const searchInput = document.getElementById('liveSearchInput');
        const clearBtn = document.getElementById('clearSearchBtn');
        const searchIcon = document.getElementById('searchIcon');
        let searchTimeout = null;

        function toggleClearButton() {
            const hasText = searchInput.value.length > 0;
            if (hasText) {
                clearBtn.classList.add('visible');
                searchIcon.style.opacity = '0.5';
            } else {
                clearBtn.classList.remove('visible');
                searchIcon.style.opacity = '1';
            }
        }

        function clearSearch() {
            searchInput.value = '';
            toggleClearButton();
            filterProducts();
            searchInput.focus();
        }

        searchInput.addEventListener('input', function () {
            toggleClearButton();
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            searchTimeout = setTimeout(filterProducts, 300);
        });

        clearBtn.addEventListener('click', function (e) {
            e.preventDefault();
            clearSearch();
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                clearSearch();
                e.preventDefault();
            }
        });

        toggleClearButton();

        // ============================================================
        // SEARCH FILTER FUNCTION
        // ============================================================
        const searchInfo = document.getElementById('searchInfo');
        const productsGrid = document.getElementById('productsGrid');

        function filterProducts() {
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const cards = productsGrid.querySelectorAll('.product-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const productName = card.getAttribute('data-fullname') || '';
                const match = searchTerm === '' || productName.toLowerCase().includes(searchTerm);
                card.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });

            if (searchTerm === '') {
                searchInfo.innerHTML = `<i class="fas fa-info-circle"></i> Showing all ${visibleCount} products`;
            } else {
                searchInfo.innerHTML = `<i class="fas fa-search"></i> Found ${visibleCount} product(s) matching "${escapeHtml(searchInput.value)}"`;
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/[&<>]/g, function (m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        // ============================================================
        // TOAST NOTIFICATION
        // ============================================================
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
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

        // ============================================================
        // DESCRIPTION MODAL FUNCTIONS
        // ============================================================
        const descModal = document.getElementById('descriptionModal');
        const closeDescModalBtn = document.querySelector('.close-desc-modal');
        const closeDescFooterBtn = document.querySelector('.close-desc-btn');

        function openDescriptionModal(productName, productUnit, productPrice, productDescription) {
            document.getElementById('descProductName').textContent = productName;
            document.getElementById('descProductUnit').textContent = productUnit;
            document.getElementById('descProductPrice').textContent = '₱ ' + productPrice;

            const descElement = document.getElementById('descProductDescription');
            if (productDescription && productDescription.trim() !== '') {
                descElement.innerHTML = productDescription.replace(/\n/g, '<br>');
            } else {
                descElement.innerHTML = '<em style="color: #94a3b8;">No description available.</em>';
            }

            descModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeDescriptionModal() {
            descModal.style.display = 'none';
            document.body.style.overflow = '';
        }

        closeDescModalBtn.addEventListener('click', closeDescriptionModal);
        closeDescFooterBtn.addEventListener('click', closeDescriptionModal);

        window.addEventListener('click', (e) => {
            if (e.target === descModal) closeDescriptionModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && descModal.style.display === 'flex') {
                closeDescriptionModal();
            }
        });

        // ============================================================
        // ADD TO CART FUNCTION
        // ============================================================
        async function addToCart(productId, productName, price, unit, quantity) {
            if (quantity <= 0) {
                showToast('Please enter a valid quantity (minimum 1)', 'error');
                return false;
            }

            if (!accNum) {
                showToast('User not authenticated. Please login again.', 'error');
                return false;
            }

            showLoading();

            try {
                const formData = new FormData();
                formData.append('action', 'add_to_cart');
                formData.append('product_id', productId);
                formData.append('quantity', quantity);
                formData.append('acc_number', accNum);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../Customer_API/add_to_cart.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    document.getElementById(`qty-${productId}`).value = '1';
                    showToast(`An item(s) added to cart`, 'success');

                    const badge = document.getElementById('cartBadge');
                    if (badge) {
                        const currentCount = parseInt(badge.textContent) || 0;
                        badge.textContent = currentCount + quantity;
                        badge.style.display = 'inline-block';
                    }
                    return true;
                } else {
                    showToast(data.message || 'Error adding to cart', 'error');
                    return false;
                }
            } catch (err) {
                console.error('Error:', err);
                showToast('Network error. Please try again.', 'error');
                return false;
            } finally {
                hideLoading();
            }
        }

        // ============================================================
        // UPDATE CART BADGE FROM SERVER
        // ============================================================
        async function updateCartBadge() {
            if (!accNum) return;
            try {
                const response = await fetch('../Customer_API/get_cart_count.php?acc_number=' + encodeURIComponent(accNum));
                const data = await response.json();
                const badge = document.getElementById('cartBadge');
                if (badge) {
                    const count = data.count || 0;
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'inline-block' : 'none';
                }
            } catch (err) {
                console.error('Error updating cart badge:', err);
            }
        }

        // ============================================================
        // IMAGE MODAL FUNCTIONS
        // ============================================================
        function openImageModal(imageSrc, productName) {
            const modal = document.getElementById('imageModal');
            document.getElementById('imageModalImg').src = imageSrc;
            document.getElementById('imageModalCaption').textContent = productName;
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        // ============================================================
        // EVENT LISTENERS
        // ============================================================
        document.querySelectorAll('.add-to-cart-card').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const productId = this.dataset.id;
                const productName = this.dataset.name;
                const price = parseFloat(this.dataset.price);
                const unit = this.dataset.unit;
                const qtyInput = document.getElementById(`qty-${productId}`);
                const quantity = qtyInput ? parseInt(qtyInput.value) : 1;
                addToCart(productId, productName, price, unit, quantity);
            });
        });

        document.querySelectorAll('.desc-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const productName = this.dataset.name;
                const productUnit = this.dataset.unit;
                const productPrice = this.dataset.price;
                const productDescription = this.dataset.description || '';
                openDescriptionModal(productName, productUnit, productPrice, productDescription);
            });
        });

        // ============================================================
        // AUTO-SEARCH FROM URL PARAMETER
        // ============================================================
        if (productNameFromUrl && productNameFromUrl.trim() !== '') {
            searchInput.value = productNameFromUrl;
            setTimeout(function () {
                filterProducts();
                toggleClearButton();
                document.querySelector('.search-section').scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                searchInput.focus();
                searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
            }, 300);
        } else {
            filterProducts();
        }

        updateCartBadge();

        console.log('🛍️ Villaruz Print Shop - Customer Dashboard');
        console.log('👤 Account number:', accNum);
        if (productNameFromUrl) {
            console.log('🔍 Auto-searching for:', productNameFromUrl);
        }
    </script>

</body>

</html>