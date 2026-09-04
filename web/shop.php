<?php
// web/shop.php - Admin Shop Page

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


// Fetch all merchandise inventory
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

        .menu-nav .nav-item.shop {
            background: #eff6ff;
            color: #3b82f6;
            border-left: 3px solid #3b82f6;
        }

        /* ========== DROPDOWN STYLES ========== */
        .nav-dropdown {
            margin-bottom: 8px;
        }

        .nav-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 12px;
            border-radius: 14px;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .nav-dropdown-toggle:hover {
            background: #eff6ff;
            color: #1e293b;
        }

        .nav-dropdown-toggle i:first-child {
            width: 24px;
            font-size: 20px;
            color: #3b82f6;
        }

        .nav-dropdown-toggle span {
            flex: 1;
            font-size: 15px;
            font-weight: 500;
        }

        .dropdown-arrow {
            font-size: 12px !important;
            transition: transform 0.3s ease;
            width: auto !important;
        }

        .dropdown-arrow.rotated {
            transform: rotate(180deg);
        }

        .nav-dropdown-menu {
            display: none;
            margin-left: 35px;
            margin-top: 5px;
            margin-bottom: 5px;
            border-left: 2px solid #e2e8f0;
        }

        .nav-dropdown-menu.show {
            display: block;
        }

        .nav-dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 14px;
        }

        .nav-dropdown-item i {
            width: 20px;
            font-size: 14px;
            color: #3b82f6;
        }

        .nav-dropdown-item span {
            font-size: 13px;
            font-weight: 500;
        }

        .nav-dropdown-item:hover {
            background: #eff6ff;
            color: #1e293b;
        }

        .nav-dropdown-item.active {
            background: #eff6ff;
            color: #3b82f6;
            border-left: 3px solid #3b82f6;
        }

        .nav-dropdown-item.active_paid {
            background: #eff6ff;
            color: green;
            border-left: 3px solid green;
        }

        .nav-dropdown-item.active_paid:hover {
            background: #d1fae5;
            color: #065f46;
        }

        .nav-dropdown-item.active_pending {
            background: #eff6ff;
            color: orange;
            border-left: 3px solid orange;
        }

        .nav-dropdown-item.active_pending:hover {
            background: #fef3c7;
            color: #92400e;
        }

        .nav-dropdown-item.active_outside {
            background: #eff6ff;
            color: #3b82f6;
            border-left: 3px solid #3b82f6;
        }

        .nav-dropdown-item.active_outside:hover {
            background: #dbeafe;
            color: #1e40af;
        }

        .nav-dropdown-item.active_credit {
            background: #eff6ff;
            color: red;
            border-left: 3px solid red;
        }

        .nav-dropdown-item.active_credit:hover {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ========== SEARCH & ADD PRODUCT - STRAIGHT ALIGNED ========== */
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
            background: linear-gradient(135deg, #10b981, #059669);
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
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            flex-shrink: 0;
        }

        .add-product-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .add-product-btn i {
            font-size: 14px;
        }

        .search-info {
            font-size: 13px;
            color: #64748b;
            padding: 0 25px 10px 25px;
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 16px;
            margin-top: 10px;
            padding: 20px;
        }

        .product-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px 10px;
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

        .product-icon {
            font-size: 42px;
            color: #3b82f6;
            margin-bottom: 10px;
        }

        .product-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #0f172a;
            line-height: 1.3;
        }

        .product-unit {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 8px;
            background: #f1f5f9;
            padding: 2px 10px;
            border-radius: 20px;
            display: inline-block;
        }

        .product-price {
            font-size: 18px;
            font-weight: 800;
            color: #3b82f6;
            margin-bottom: 12px;
        }

        /* Quantity control inside card */
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

        .card-add-btn {
            background: #3b82f6;
            border: none;
            width: 100%;
            padding: 8px 0;
            border-radius: 30px;
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

        .card-desc-btn {
            background: #8b5cf6;
            border: none;
            width: 100%;
            padding: 8px 0;
            border-radius: 30px;
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

        /* Description Modal - Modern Design */
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

        /* Toast Notification */
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

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
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

        /* Add Product Modal */
        .add-product-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1300;
            justify-content: center;
            align-items: center;
        }

        .add-product-modal-content {
            background: #ffffff;
            border-radius: 24px;
            max-width: 550px;
            width: 92%;
            animation: modalSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-height: 90vh;
            overflow-y: auto;
        }

        .add-product-modal-header {
            background: linear-gradient(135deg, #10b981, #059669);
            padding: 20px 28px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .add-product-modal-header h3 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .add-product-modal-header h3 i {
            font-size: 24px;
        }

        .close-add-product-modal {
            font-size: 32px;
            font-weight: 300;
            cursor: pointer;
            transition: all 0.2s;
            line-height: 1;
            opacity: 0.8;
        }

        .close-add-product-modal:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        .add-product-modal-body {
            padding: 28px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .form-group label i {
            color: #10b981;
            margin-right: 6px;
        }

        .form-group label .required {
            color: #ef4444;
            margin-left: 2px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group .hint {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
        }

        .add-product-modal-footer {
            padding: 20px 28px 28px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-cancel-add {
            padding: 12px 28px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: white;
            color: #475569;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel-add:hover {
            background: #f1f5f9;
        }

        .btn-submit-add {
            padding: 12px 32px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
        }

        .btn-submit-add:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-submit-add .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .btn-submit-add.loading .spinner {
            display: inline-block;
        }

        .btn-submit-add.loading .btn-text {
            display: none;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
                margin-left: 0 !important;
                padding-top: 20px;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                gap: 12px;
                padding: 15px;
            }

            .desc-modal-content {
                width: 95%;
            }

            .desc-modal-header h3 {
                font-size: 18px;
            }

            .welcome h4 {
                font-size: 14px;
            }

            .dashboard-header {
                padding: 15px 20px;
            }

            .shop-controls {
                padding: 12px 15px;
                gap: 8px;
                flex-wrap: nowrap;
            }

            .search-wrapper {
                gap: 8px;
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

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 10px;
                padding: 10px;
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

            .search-info {
                font-size: 11px;
                padding: 0 15px 8px 15px;
            }

            .add-product-modal-content {
                width: 95%;
            }

            .add-product-modal-body {
                padding: 20px;
            }

            .add-product-modal-footer {
                flex-direction: column;
            }

            .btn-cancel-add,
            .btn-submit-add {
                width: 100%;
                justify-content: center;
            }
        }

        /* Extra small screens - prevent wrapping */
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
        <!-- Overlay (Mobile Only) -->
        <div class="menu-overlay" id="menuOverlay"></div>

        <!-- Sidebar Wrapper -->
        <div class="sidebar-wrapper" id="sidebarWrapper">
            <div class="side-menu" id="sideMenu">
                <!-- Close Button (Mobile Only) -->
                <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close sidebar">
                    <i class="fas fa-arrow-left"></i>
                </button>

                <div class="menu-header">
                    <i class="fas fa-store"></i>
                    <div class="user-greeting">Logged in as</div>
                    <div class="user-name">
                        <?php
                        echo htmlspecialchars($user['user_name'] ?? 'User');
                        ?>
                    </div>
                    <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                        <?php echo htmlspecialchars($user['acc_number'] ?? ''); ?>
                    </div>
                </div>
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
                            Purchase Order
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="merchandise-section">
                <!-- Search Bar & Add Product - Straight Aligned -->
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
                    <button class="add-product-btn" id="addProductBtn">
                        <i class="fas fa-plus-circle"></i> Add Product
                    </button>
                </div>
                <div id="searchInfo" class="search-info"></div>

                <!-- Products Grid -->
                <div class="products-grid" id="productsGrid">
                    <?php foreach ($allProducts as $product): ?>
                        <div class="product-card" data-id="<?php echo $product['id']; ?>"
                            data-name="<?php echo strtolower(htmlspecialchars($product['product_name'])); ?>"
                            data-fullname="<?php echo htmlspecialchars($product['product_name']); ?>"
                            data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>"
                            data-unit="<?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?>"
                            data-price="<?php echo number_format($product['selling_price'], 2); ?>">
                            <div class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-unit"><?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?></div>
                            <div class="product-price">₱ <?php echo number_format($product['selling_price'], 2); ?></div>

                            <div class="card-qty-control">
                                <button class="card-qty-btn decrement-card"
                                    data-id="<?php echo $product['id']; ?>">-</button>
                                <span class="card-qty-value" id="qty-<?php echo $product['id']; ?>">0</span>
                                <button class="card-qty-btn increment-card"
                                    data-id="<?php echo $product['id']; ?>">+</button>
                            </div>

                            <button class="card-add-btn add-to-cart-card" data-id="<?php echo $product['id']; ?>"
                                data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                data-price="<?php echo $product['selling_price']; ?>"
                                data-unit="<?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?>">
                                <i class="fas fa-cart-plus"></i> Add to Cart
                            </button>
                            <button class="card-desc-btn desc-btn" data-id="<?php echo $product['id']; ?>"
                                data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                data-unit="<?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?>"
                                data-price="<?php echo number_format($product['selling_price'], 2); ?>"
                                data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>">
                                <i class="fas fa-info-circle"></i> Description
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="add-product-modal">
        <div class="add-product-modal-content">
            <div class="add-product-modal-header">
                <h3>
                    <i class="fas fa-box"></i>
                    Add New Product
                </h3>
                <span class="close-add-product-modal">&times;</span>
            </div>
            <div class="add-product-modal-body">
                <form id="addProductForm">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Product Name <span class="required">*</span></label>
                        <input type="text" id="product_name" placeholder="Enter product name" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-cube"></i> Unit <span class="required">*</span></label>
                        <input type="text" id="unit" placeholder="e.g., Pcs, Box, Kg" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-sort-numeric-up"></i> Quantity <span class="required">*</span></label>
                        <input type="number" id="quantity" placeholder="Enter initial quantity" required min="0"
                            step="1">
                        <div class="hint">Enter the initial stock quantity.</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-coins"></i> Unit Cost <span class="required">*</span></label>
                        <input type="number" id="selling_price" placeholder="0.00" required min="0" step="0.01">
                        <div class="hint">Enter the selling price per unit.</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea id="description" placeholder="Enter product description (optional)"></textarea>
                    </div>
                </form>
            </div>
            <div class="add-product-modal-footer">
                <button class="btn-cancel-add" id="cancelAddProduct">Cancel</button>
                <button class="btn-submit-add" id="submitAddProduct">
                    <span class="spinner"></span>
                    <span class="btn-text"><i class="fas fa-save"></i> Save Product</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Description Modal - Modern Design -->
    <div id="descriptionModal" class="desc-modal">
        <div class="desc-modal-content">
            <div class="desc-modal-header">
                <h3>
                    <i class="fas fa-file-alt"></i>
                    Product Information
                </h3>
                <span class="close-desc-modal">&times;</span>
            </div>
            <div class="desc-modal-body">
                <div class="product-info-section">
                    <div class="product-detail-row">
                        <div class="product-detail-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="product-detail-text">
                            <div class="product-detail-label">Product Name</div>
                            <div class="product-detail-value" id="descProductName">-</div>
                        </div>
                    </div>
                    <div class="product-detail-row">
                        <div class="product-detail-icon">
                            <i class="fas fa-tag"></i>
                        </div>
                        <div class="product-detail-text">
                            <div class="product-detail-label">Unit</div>
                            <div class="product-detail-value" id="descProductUnit">-</div>
                        </div>
                    </div>
                    <div class="product-detail-row">
                        <div class="product-detail-icon">
                            <i class="fas fa-coins"></i>
                        </div>
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
                    <div class="description-text" id="descProductDescription">
                        No description available.
                    </div>
                </div>
            </div>
            <div class="desc-modal-footer">
                <button class="close-desc-btn">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner"></i>
            <p>Processing...</p>
        </div>
    </div>

    <?php
    include '../footer.php';
    ?>

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
                if (isSidebarOpen) {
                    closeSidebar();
                }
                sidebarWrapper.classList.remove('open');
                menuOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // ========== DESCRIPTION MODAL FUNCTIONS ==========
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
            if (e.target === descModal) {
                closeDescriptionModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && descModal.style.display === 'flex') {
                closeDescriptionModal();
            }
        });

        // ========== ADD PRODUCT MODAL ==========
        const addProductModal = document.getElementById('addProductModal');
        const addProductBtn = document.getElementById('addProductBtn');
        const closeAddProductModalBtn = document.querySelector('.close-add-product-modal');
        const cancelAddProductBtn = document.getElementById('cancelAddProduct');
        const submitAddProductBtn = document.getElementById('submitAddProduct');
        const addProductForm = document.getElementById('addProductForm');

        function openAddProductModal() {
            addProductModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            addProductForm.reset();
            submitAddProductBtn.disabled = false;
            submitAddProductBtn.classList.remove('loading');
        }

        function closeAddProductModal() {
            addProductModal.style.display = 'none';
            document.body.style.overflow = '';
        }

        if (addProductBtn) {
            addProductBtn.addEventListener('click', openAddProductModal);
        }

        if (closeAddProductModalBtn) {
            closeAddProductModalBtn.addEventListener('click', closeAddProductModal);
        }

        if (cancelAddProductBtn) {
            cancelAddProductBtn.addEventListener('click', closeAddProductModal);
        }

        window.addEventListener('click', (e) => {
            if (e.target === addProductModal) {
                closeAddProductModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && addProductModal.style.display === 'flex') {
                closeAddProductModal();
            }
        });

        // ========== SUBMIT ADD PRODUCT ==========
        if (submitAddProductBtn) {
            submitAddProductBtn.addEventListener('click', async function (e) {
                e.preventDefault();

                const productName = document.getElementById('product_name').value.trim();
                const unit = document.getElementById('unit').value.trim();
                const quantity = document.getElementById('quantity').value.trim();
                const sellingPrice = document.getElementById('selling_price').value.trim();
                const description = document.getElementById('description').value.trim();

                if (!productName) {
                    showToast('Please enter product name', 'error');
                    document.getElementById('product_name').focus();
                    return;
                }

                if (!unit) {
                    showToast('Please enter unit', 'error');
                    document.getElementById('unit').focus();
                    return;
                }

                if (!quantity || parseInt(quantity) < 0) {
                    showToast('Please enter valid quantity', 'error');
                    document.getElementById('quantity').focus();
                    return;
                }

                if (!sellingPrice || parseFloat(sellingPrice) <= 0) {
                    showToast('Please enter valid unit cost (must be greater than 0)', 'error');
                    document.getElementById('selling_price').focus();
                    return;
                }

                submitAddProductBtn.disabled = true;
                submitAddProductBtn.classList.add('loading');

                try {
                    const formData = new FormData();
                    formData.append('action', 'add_product');
                    formData.append('product_name', productName);
                    formData.append('unit', unit);
                    formData.append('quantity', quantity);
                    formData.append('selling_price', sellingPrice);
                    formData.append('description', description);
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch('../API/add_product.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        showToast(data.message || 'Product added successfully!', 'success');
                        closeAddProductModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showToast(data.message || 'Error adding product', 'error');
                    }
                } catch (err) {
                    console.error('Error:', err);
                    showToast('Network error. Please try again.', 'error');
                } finally {
                    submitAddProductBtn.disabled = false;
                    submitAddProductBtn.classList.remove('loading');
                }
            });
        }

        document.getElementById('addProductForm').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                document.getElementById('submitAddProduct').click();
            }
        });

        // ========== TOAST NOTIFICATION ==========
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML =
                `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
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

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>]/g, function (m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        // ========== SEARCH FUNCTIONALITY ==========
        function filterProducts() {
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const cards = productsGrid.querySelectorAll('.product-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const productName = card.getAttribute('data-fullname') || '';
                const productNameLower = productName.toLowerCase();

                let searchMatch = true;
                if (searchTerm !== '') {
                    searchMatch = productNameLower.includes(searchTerm);
                }

                if (searchMatch) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (searchTerm === '') {
                searchInfo.innerHTML = `<i class="fas fa-info-circle"></i> Showing all ${visibleCount} products`;
            } else {
                searchInfo.innerHTML =
                    `<i class="fas fa-search"></i> Found ${visibleCount} product(s) matching "${escapeHtml(searchInput.value)}"`;
            }
        }

        // ========== ADD TO CART FUNCTION ==========
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
                    if (qtySpan) {
                        qtySpan.textContent = '0';
                    }
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

        // ========== QUANTITY CONTROL EVENT LISTENERS ==========
        document.querySelectorAll('.decrement-card').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const productId = this.dataset.id;
                const qtySpan = document.getElementById(`qty-${productId}`);
                if (!qtySpan) return;
                let currentQty = parseInt(qtySpan.textContent) || 0;
                if (currentQty > 0) {
                    qtySpan.textContent = currentQty - 1;
                }
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

        // ========== ADD TO CART BUTTON EVENT LISTENERS ==========
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

        // ========== DESCRIPTION BUTTON EVENT LISTENERS ==========
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

        // ========== LIVE SEARCH FUNCTIONALITY ==========
        const searchInput = document.getElementById('liveSearchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const searchInfo = document.getElementById('searchInfo');
        const productsGrid = document.getElementById('productsGrid');

        function performLiveSearch() {
            filterProducts();
        }

        let searchTimeout;
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performLiveSearch, 300);
            });
        }

        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', () => {
                searchInput.value = '';
                performLiveSearch();
                searchInput.focus();
            });
        }

        performLiveSearch();

        console.log('📱 Sidebar menu loaded - Left Side');
        console.log('📐 Desktop: Sidebar expanded | Mobile: Burger menu');
    </script>
</body>

</html>