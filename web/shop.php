<?php
// web/shop.php - Admin Shop Page

session_start();

require_once __DIR__ . '/../DB_Conn/config.php';

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
if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, role, user_name, authorize_access FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$user = $userData;

$stmt = $pdo->prepare("SELECT * FROM merchandise_inventory ORDER BY STR_TO_DATE(last_restocked, '%d %M %Y %h:%i %p') DESC");
$stmt->execute();
$allProducts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Shop | Villaruz Print Shop & General Merchandise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== MODERN LIGHT GRAY DASHBOARD STYLES ========== */
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

        .last_restocked {
            font-size: 10px;
            color: #64748b;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        /* ========== BURGER BUTTON (Mobile Only) ========== */
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
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .welcome h4 {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
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

        /* ========== PRODUCT IMAGE ========== */
        .product-image-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 10px;
            line-height: 0;
        }

        .product-image-clickable {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.3s ease;
            display: block;
        }

        .product-image-clickable:hover {
            transform: scale(1.04);
        }

        /* Update (edit) button — top-LEFT corner of the IMAGE */
        .edit-btn {
            position: absolute;
            top: -6px;
            left: -6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.95);
            color: black;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 5;
            font-size: 13px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
        }

        .edit-btn:hover {
            background: #3b82f6;
            color: #ffffff;
            transform: scale(1.05);
        }

        .edit-btn i {
            pointer-events: none;
        }

        /* Trash button — top-RIGHT corner of the IMAGE */
        .delete-btn {
            position: absolute;
            top: -6px;
            right: -6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.95);
            color: black;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 5;
            font-size: 13px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
        }

        .delete-btn:hover {
            background: #ef4444;
            color: #ffffff;
            transform: scale(1.05);
        }

        .delete-btn i {
            pointer-events: none;
        }

        .delete-btn:hover i {
            transform: rotate(-8deg);
        }

        /* ========== SEARCH & ADD PRODUCT ========== */
        .shop-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 25px;
            flex-wrap: nowrap;
        }

        .search-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }

        .search-input {
            flex: 1;
            position: relative;
            min-width: 0;
        }

        .search-input i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-input input {
            width: 100%;
            padding: 10px 15px 10px 45px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-input input:focus {
            outline: none;
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .clear-search-btn {
            background: #e2e8f0;
            border: none;
            border-radius: 30px;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.3s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .clear-search-btn:hover {
            background: #cbd5e1;
        }

        .add-product-btn {
            background: linear-gradient(145deg, #3b82f6, #6366f1);
            border: none;
            padding: 10px 24px;
            border-radius: 30px;
            color: white;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            text-decoration: none;
        }

        .add-product-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .add-product-btn i {
            font-size: 14px;
        }

        /* ========== PRODUCTS GRID ========== */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 20px;
            margin-top: 10px;
            padding: 20px;
        }

        .product-card {
            position: relative;
            background: #ffffff;
            border-radius: 5px;
            padding: 16px 12px;
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
            font-weight: 700;
            margin-bottom: 5px;
            color: #0f172a;
            line-height: 1.3;
        }

        /* ✅ Price + Unit aligned in a grid row */
        .price-unit-grid {
            display: grid;
            grid-template-columns: auto auto;
            align-items: baseline;
            justify-content: center;
            column-gap: 6px;
            margin-bottom: 12px;
            width: 100%;
        }

        .product-price {
            font-size: 16px;
            font-weight: 800;
            color: #3b82f6;
            line-height: 1.2;
            white-space: nowrap;
        }

        .product-unit {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            line-height: 1.2;
            white-space: nowrap;
        }

        .card-qty-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #f8fafc;
            border-radius: 30px;
            padding: 4px 8px;
            margin-bottom: 10px;
            width: 100%;
            border: 1px solid #e2e8f0;
        }

        .card-qty-btn {
            background: #ffffff;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-size: 16px;
            font-weight: bold;
            color: #3b82f6;
            cursor: pointer;
            transition: 0.2s;
        }

        .card-qty-btn:hover {
            background: #3b82f6;
            color: #ffffff;
        }

        .card-qty-value {
            font-size: 14px;
            font-weight: 600;
            min-width: 35px;
            text-align: center;
            color: #0f172a;
        }

        .card-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            width: 100%;
            margin-top: 4px;
        }

        .card-add-btn,
        .card-desc-btn {
            border: none;
            width: 100%;
            padding: 9px 0;
            border-radius: 5px;
            font-weight: 600;
            font-size: 12px;
            color: #ffffff;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            margin: 0;
        }

        .card-add-btn {
            background: #3b82f6;
        }

        .card-add-btn:hover {
            background: #2563eb;
            transform: scale(0.97);
        }

        .card-desc-btn {
            background: #8b5cf6;
        }

        .card-desc-btn:hover {
            background: #7c3aed;
            transform: scale(0.97);
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
            border-radius: 32px;
            max-width: 500px;
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
            padding: 24px 28px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .desc-modal-header h3 {
            font-size: 22px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .desc-modal-header h3 i {
            font-size: 28px;
        }

        .close-desc-modal {
            font-size: 32px;
            font-weight: 300;
            cursor: pointer;
            transition: all 0.2s;
            line-height: 1;
            opacity: 0.8;
        }

        .close-desc-modal:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        .desc-modal-body {
            padding: 28px;
        }

        .product-info-section {
            background: #ffffff;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .product-detail-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .product-detail-row:last-child {
            border-bottom: none;
        }

        .product-detail-icon {
            width: 40px;
            height: 40px;
            background: #eff6ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8b5cf6;
        }

        .product-detail-icon i {
            font-size: 18px;
        }

        .product-detail-text {
            flex: 1;
        }

        .product-detail-label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .product-detail-value {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 2px;
        }

        .description-section {
            background: #ffffff;
            border-radius: 20px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .description-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }

        .description-title i {
            color: #8b5cf6;
            font-size: 18px;
        }

        .description-title span {
            font-weight: 600;
            color: #0f172a;
            font-size: 14px;
        }

        .description-text {
            color: #475569;
            line-height: 1.6;
            font-size: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .desc-modal-footer {
            padding: 20px 28px 28px;
            border-top: 1px solid #e2e8f0;
            background: #ffffff;
        }

        .close-desc-btn {
            width: 100%;
            background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
            border: none;
            padding: 14px;
            border-radius: 16px;
            color: white;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .close-desc-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
        }

        /* ========== TOAST ========== */
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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

        /* ========== LOADING OVERLAY ========== */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            z-index: 3000;
            justify-content: center;
            align-items: center;
        }

        .loading-spinner {
            background: white;
            padding: 30px 40px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .loading-spinner i {
            font-size: 32px;
            color: #3b82f6;
            animation: spin 1s linear infinite;
        }

        .loading-spinner p {
            margin-top: 12px;
            font-weight: 500;
            color: #1e293b;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
                padding-top: 20px;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 12px;
                padding: 15px;
            }

            .shop-controls {
                padding: 12px 15px;
                gap: 8px;
                flex-wrap: nowrap;
            }

            .search-wrapper {
                gap: 8px;
            }

            .search-input input {
                padding: 8px 12px 8px 35px;
                font-size: 13px;
            }

            .search-input i {
                left: 12px;
                font-size: 13px;
            }

            .clear-search-btn {
                padding: 8px 14px;
                font-size: 12px;
            }

            .add-product-btn {
                padding: 8px 16px;
                font-size: 12px;
            }

            .add-product-btn i {
                font-size: 12px;
            }

            .desc-modal-content {
                width: 95%;
            }

            .desc-modal-header h3 {
                font-size: 18px;
            }

            .dashboard-header {
                padding: 15px 20px;
            }
        }

        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 10px;
                padding: 10px;
            }

            .main-content {
                padding: 15px;
                padding-top: 15px;
            }

            .dashboard-header {
                padding: 12px 15px;
                border-radius: 12px;
            }

            .welcome h4 {
                font-size: 16px;
            }

            .shop-controls {
                padding: 10px 12px;
                gap: 6px;
                flex-wrap: nowrap;
            }

            .search-input input {
                padding: 8px 12px 8px 35px;
                font-size: 12px;
            }

            .search-input i {
                left: 12px;
                font-size: 12px;
            }

            .clear-search-btn {
                padding: 6px 10px;
                font-size: 11px;
                gap: 4px;
            }

            .clear-search-btn i {
                font-size: 11px;
            }

            .add-product-btn {
                padding: 6px 12px;
                font-size: 11px;
                gap: 4px;
            }

            .add-product-btn i {
                font-size: 11px;
            }
        }

        @media (max-width: 380px) {
            .shop-controls {
                gap: 4px;
                padding: 8px 10px;
            }

            .search-wrapper {
                gap: 4px;
            }

            .clear-search-btn {
                padding: 5px 8px;
                font-size: 10px;
            }

            .add-product-btn {
                padding: 5px 10px;
                font-size: 10px;
            }

            .add-product-btn i {
                font-size: 10px;
            }

            .search-input input {
                padding: 6px 10px 6px 30px;
                font-size: 11px;
            }

            .search-input i {
                left: 10px;
                font-size: 11px;
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
                        <h4>Purchase Order</h4>
                    </div>
                </div>
            </div>

            <div class="merchandise-section">
                <div class="shop-controls">
                    <div class="search-wrapper">
                        <div class="search-input">
                            <i class="fas fa-search"></i>
                            <input type="text" id="liveSearchInput" placeholder="Search by product name..."
                                autocomplete="off">
                        </div>
                        <button class="clear-search-btn" id="clearSearchBtn">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                    <a href="upload_products.php" class="add-product-btn" id="addProductBtn"
                        style="text-decoration: none;">
                        <i class="fas fa-plus-circle"></i> Add New
                    </a>
                </div>

                <div class="products-grid" id="productsGrid">
                    <?php foreach ($allProducts as $product): ?>
                        <div class="product-card" data-id="<?php echo $product['id']; ?>"
                            data-name="<?php echo strtolower(htmlspecialchars($product['product_name'])); ?>"
                            data-fullname="<?php echo htmlspecialchars($product['product_name']); ?>">
                            <div class="product-image-wrapper">
                                <img src="https://villaruz-print-shop-and-general-merchandise.shop/Products/<?php echo htmlspecialchars($product['product_image']); ?>"
                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                    class="product-image-clickable"
                                    onclick="openImageModal('../Products/<?php echo htmlspecialchars($product['product_image']); ?>', '<?php echo htmlspecialchars($product['product_name']); ?>')">

                                <a href="update_products.php?product_number=<?php echo urlencode($product['product_number']); ?>"
                                    class="edit-btn" title="Update product">
                                    <i class="fas fa-pen"></i>
                                </a>

                                <a href="../API/delete_product.php?product_number=<?php echo urlencode($product['product_number']); ?>"
                                    class="delete-btn"
                                    onclick="event.stopPropagation(); return confirm('Are you sure you want to delete this product?');"
                                    title="Delete product">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>

                            <div class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></div>

                            <div class="price-unit-grid">
                                <div class="product-price">₱ <?php echo number_format($product['selling_price'], 2); ?></div>
                                <div class="product-unit">/ <?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?></div>
                            </div>

                            <div class="card-qty-control">
                                <button class="card-qty-btn decrement-card"
                                    data-id="<?php echo $product['id']; ?>">-</button>
                                <span class="card-qty-value" id="qty-<?php echo $product['id']; ?>">0</span>
                                <button class="card-qty-btn increment-card"
                                    data-id="<?php echo $product['id']; ?>">+</button>
                            </div>

                            <div class="card-actions-grid">
                                <button class="card-add-btn add-to-cart-card" data-id="<?php echo $product['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                    data-price="<?php echo $product['selling_price']; ?>"
                                    data-unit="<?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?>">
                                    Add
                                </button>

                                <button class="card-desc-btn desc-btn" data-id="<?php echo $product['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                    data-unit="<?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?>"
                                    data-price="<?php echo number_format($product['selling_price'], 2); ?>"
                                    data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>">
                                    Description
                                </button>
                            </div>
                            <div class="last_restocked">
                                <?php echo htmlspecialchars($product['last_restocked']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="descriptionModal" class="desc-modal">
        <div class="desc-modal-content">
            <div class="desc-modal-header">
                <h3><i class="fas fa-file-alt"></i> Product Information</h3>
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
                    <div class="description-title">
                        <i class="fas fa-align-left"></i>
                        <span>Description</span>
                    </div>
                    <div class="description-text" id="descProductDescription">No description available.</div>
                </div>
            </div>
            <div class="desc-modal-footer">
                <button class="close-desc-btn"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>
    </div>

    <div id="imageModal" class="desc-modal" onclick="closeImageModal()">
        <div class="desc-modal-content" style="max-width: 90%; background: #000; padding: 12px;"
            onclick="event.stopPropagation()">
            <img id="imageModalImg" src="" alt="Product Image"
                style="width: 100%; height: auto; max-height: 80vh; object-fit: contain; border-radius: 12px;">
            <button class="close-desc-btn" style="margin-top: 12px;" onclick="closeImageModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner"></i>
            <p>Processing...</p>
        </div>
    </div>

    <?php include '../footer.php'; ?>

    <script>
        const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        const accNum = '<?php echo htmlspecialchars($user['acc_number'] ?? ''); ?>';

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

        // ========== DESCRIPTION MODAL ==========
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

        if (closeDescModalBtn) closeDescModalBtn.addEventListener('click', closeDescriptionModal);
        if (closeDescFooterBtn) closeDescFooterBtn.addEventListener('click', closeDescriptionModal);

        window.addEventListener('click', (e) => {
            if (e.target === descModal) closeDescriptionModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && descModal.style.display === 'flex') {
                closeDescriptionModal();
            }
        });

        // ========== IMAGE MODAL ==========
        function openImageModal(imageSrc, productName) {
            const modal = document.getElementById('imageModal');
            document.getElementById('imageModalImg').src = imageSrc;
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        // ========== TOAST ==========
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
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.style.display = 'flex';
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.style.display = 'none';
        }

        // ========== SEARCH ==========
        const searchInput = document.getElementById('liveSearchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const productsGrid = document.getElementById('productsGrid');

        function filterProducts() {
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const cards = productsGrid.querySelectorAll('.product-card');

            cards.forEach(card => {
                const productName = card.getAttribute('data-fullname') || '';
                const match = searchTerm === '' || productName.toLowerCase().includes(searchTerm);
                card.style.display = match ? '' : 'none';
            });
        }

        let searchTimeout;
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(filterProducts, 300);
            });
        }

        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', () => {
                searchInput.value = '';
                filterProducts();
                searchInput.focus();
            });
        }

        // ========== ADD TO CART ==========
        async function addToCart(productId, productName, price, unit, quantity) {
            if (quantity <= 0) {
                showToast('Please select quantity first (use + button to increase)', 'error');
                return false;
            }

            if (!accNum) {
                showToast('User not authenticated', 'error');
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

                const response = await fetch('../API/add_to_cart.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    const qtySpan = document.getElementById(`qty-${productId}`);
                    if (qtySpan) qtySpan.textContent = '0';
                    showToast(`An item(s) added to cart!`, 'success');
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

        // ========== QUANTITY CONTROLS ==========
        document.querySelectorAll('.decrement-card').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const productId = this.dataset.id;
                const qtySpan = document.getElementById(`qty-${productId}`);
                if (!qtySpan) return;
                let currentQty = parseInt(qtySpan.textContent) || 0;
                if (currentQty > 0) qtySpan.textContent = currentQty - 1;
            });
        });

        document.querySelectorAll('.increment-card').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const productId = this.dataset.id;
                const qtySpan = document.getElementById(`qty-${productId}`);
                if (!qtySpan) return;
                let currentQty = parseInt(qtySpan.textContent) || 0;
                qtySpan.textContent = currentQty + 1;
            });
        });

        // ========== ADD TO CART BUTTONS ==========
        document.querySelectorAll('.add-to-cart-card').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const productId = this.dataset.id;
                const productName = this.dataset.name;
                const price = parseFloat(this.dataset.price);
                const unit = this.dataset.unit;
                const qtySpan = document.getElementById(`qty-${productId}`);
                const quantity = qtySpan ? parseInt(qtySpan.textContent) : 0;
                addToCart(productId, productName, price, unit, quantity);
            });
        });

        // ========== DESCRIPTION BUTTONS ==========
        document.querySelectorAll('.desc-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openDescriptionModal(
                    this.dataset.name,
                    this.dataset.unit,
                    this.dataset.price,
                    this.dataset.description || ''
                );
            });
        });

        filterProducts();
    </script>
</body>

</html>