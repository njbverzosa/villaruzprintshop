<?php
// investors/all_products.php

session_start();

require_once __DIR__ . '/../DB_Conn/config.php';

// Store user name in session for API use
if (isset($userData['f_name']) && !isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = $userData['f_name'];
}

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

date_default_timezone_set('Asia/Manila');
$timezone = new DateTimeZone('Asia/Manila');

$stmt = $pdo->prepare("
    SELECT * FROM investors_product 
    ORDER BY 
        CASE 
            WHEN last_restocked IS NULL OR last_restocked = '' THEN 1 
            ELSE 0 
        END,
        STR_TO_DATE(last_restocked, '%d %M %Y %h:%i %p') DESC,
        id DESC
");
$stmt->execute();
$allProducts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Shop Products | Villaruz Print Shop & General Merchandise</title>
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
            inset: 0;
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
            padding: 25px 50px 25px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            flex-shrink: 0;
        }

        .menu-header .user-name {
            font-weight: 700;
            font-size: 18px;
            color: #0f172a;
            margin-top: 8px;
        }

        .menu-nav {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

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
        }

        .add-product-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .search-info {
            font-size: 13px;
            color: #64748b;
            padding: 0 25px 10px 25px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 20px;
            margin-top: 10px;
            padding: 20px;
        }

        .product-card {
            background: #ffffff;
            border-radius: 20px;
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

        /* Image sits BELOW the product title */
        .product-image {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 12px;
            overflow: hidden;
            background: #f8fafc;
            margin: 8px 0 12px 0;
            border: 1px solid #e2e8f0;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .product-image.no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #cbd5e1;
            font-size: 32px;
        }

        .product-title {
            font-size: 15px;
            font-weight: 700;
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

        .card-qty-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #f8fafc;
            border-radius: 40px;
            padding: 4px 8px;
            margin-bottom: 12px;
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

        .card-actions {
            display: flex;
            gap: 8px;
            width: 100%;
            margin-top: 4px;
        }

        .update-btn,
        .desc-btn {
            flex: 1;
            border: none;
            border-radius: 10px;
            padding: 8px 0;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .update-btn {
            background: #3b82f6;
            color: white;
        }

        .update-btn:hover:not(:disabled) {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .desc-btn {
            background: #8b5cf6;
            color: white;
        }

        .desc-btn:hover {
            background: #7c3aed;
            transform: translateY(-2px);
        }

        .last_restocked {
            font-size: 10px;
            color: #64748b;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        /* MODALS */
        .desc-modal {
            display: none;
            position: fixed;
            inset: 0;
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
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
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

        .close-desc-modal {
            font-size: 32px;
            font-weight: 300;
            cursor: pointer;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1100;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-content h3 {
            color: #1e293b;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .modal-content h3 i {
            color: #3b82f6;
            margin-right: 8px;
        }

        .modal-content input,
        .modal-content select,
        .modal-content textarea {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
        }

        .modal-content input:focus,
        .modal-content select:focus,
        .modal-content textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: #ffffff;
        }

        .modal-content textarea {
            resize: vertical;
            min-height: 80px;
        }

        .modal-content label {
            display: block;
            text-align: left;
            margin-top: 10px;
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }

        .modal-content label .required {
            color: #ef4444;
            margin-left: 4px;
        }

        .modal-content .error-message {
            color: #ef4444;
            font-size: 12px;
            margin-top: -8px;
            margin-bottom: 8px;
            display: none;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .modal-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .modal-confirm {
            background: #3b82f6;
            color: white;
        }

        .modal-confirm:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .modal-cancel {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .modal-cancel:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        /* TOAST */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 2000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .toast-success {
            background: #10b981;
        }

        .toast-error {
            background: #ef4444;
        }

        .save-spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid #ffffff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-left: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* TABS */
        .modal-tabs {
            display: flex;
            gap: 60px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 10px 0;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            color: #666;
            position: relative;
        }

        .tab-btn.active {
            color: #f5b342;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #f5b342;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* EXCEL UPLOAD */
        .excel-info {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .excel-format-image {
            margin-bottom: 15px;
            display: flex;
            justify-content: center;
        }

        .excel-format-image img {
            max-width: 100%;
            width: 600px;
            height: auto;
            border: 1px solid #ddd;
            padding: 10px;
            background: white;
        }

        .excel-format-text {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
        }

        .file-upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .file-upload-area:hover {
            border-color: #f5b342;
            background: #fafafa;
        }

        .upload-placeholder i {
            font-size: 48px;
            color: #999;
            margin-bottom: 10px;
        }

        .upload-placeholder p {
            margin: 10px 0;
            color: #666;
        }

        .file-hint {
            font-size: 12px;
            color: #999;
        }

        .upload-preview {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px;
            background: #e8f0fe;
            border-radius: 8px;
        }

        .upload-preview i {
            font-size: 24px;
            color: #28a745;
        }

        .upload-preview .file-name {
            color: #333;
            font-size: 14px;
        }

        .remove-file {
            background: none;
            border: none;
            cursor: pointer;
            color: #dc3545;
            font-size: 16px;
            padding: 0 5px;
        }

        /* CAMERA */
        .camera-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px;
            margin-top: 8px;
        }

        .camera-preview-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 1 / 1;
            background: #0f172a;
            border-radius: 12px;
            overflow: hidden;
            display: none;
            margin-bottom: 10px;
        }

        .camera-preview-wrap.visible {
            display: block;
        }

        .camera-preview-wrap video,
        .camera-preview-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .camera-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .camera-btn {
            flex: 1;
            min-width: 120px;
            padding: 10px 14px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .camera-btn-open {
            background: #3b82f6;
            color: #fff;
        }

        .camera-btn-capture {
            background: #10b981;
            color: #fff;
        }

        .camera-btn-stop {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .camera-btn-retake {
            background: #f59e0b;
            color: #fff;
        }

        .camera-hint {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 6px;
            text-align: center;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 12px;
                padding: 15px;
            }

            .shop-controls {
                padding: 12px 15px;
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

            .clear-search-btn,
            .add-product-btn {
                padding: 8px 14px;
                font-size: 12px;
            }

            .modal-content {
                padding: 20px;
                max-height: 85vh;
            }

            .modal-tabs {
                gap: 30px;
            }

            .dashboard-header {
                padding: 15px 20px;
            }

            .search-info {
                padding: 0 15px 10px 15px;
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
            }

            .search-input input {
                padding: 8px 12px 8px 35px;
                font-size: 12px;
            }

            .clear-search-btn,
            .add-product-btn {
                padding: 6px 10px;
                font-size: 11px;
            }

            .search-info {
                font-size: 11px;
                padding: 0 12px 8px 12px;
            }

            .modal-tabs {
                gap: 15px;
            }

            .tab-btn {
                font-size: 12px;
                padding: 8px 0;
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
                        <h4>Shop</h4>
                    </div>
                </div>
            </div>

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
                <a href="upload_product.php" style="text-decoration: none;">
                    <button class="add-product-btn" id="addProductBtn">
                        <i class="fas fa-plus-circle"></i> Add New Product
                    </button>
                </a>
            </div>
            <div id="searchInfo" class="search-info"></div>

            <div class="products-grid" id="productsGrid">
                <?php if (empty($allProducts)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: #94a3b8;">
                        <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
                        No products found. Click "Add New Product" to get started.
                    </div>
                <?php else: ?>
                    <?php foreach ($allProducts as $product): ?>

                        <div class="product-card" data-id="<?php echo $product['id']; ?>"
                            data-name="<?php echo strtolower(htmlspecialchars($product['product_name'])); ?>"
                            data-fullname="<?php echo htmlspecialchars($product['product_name']); ?>"
                            data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>"
                            data-unit="<?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?>"
                            data-price="<?php echo number_format($product['selling_price'], 2); ?>"
                            data-qty="<?php echo number_format($product['qty_on_hand']); ?>">

                            <!-- Product name first -->
                            <div class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-unit"><?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?></div>

                            <div class="product-image-wrapper">
                                <img src="../Products/<?php echo htmlspecialchars($product['product_image']); ?>"
                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                    class="product-image-clickable"
                                    onclick="openImageModal('../Products/<?php echo htmlspecialchars($product['product_image']); ?>', '<?php echo htmlspecialchars($product['product_name']); ?>')"
                                    style="width: 100px; height: auto; cursor: pointer;">
                            </div>

                            <div class="product-price">₱ <?php echo number_format($product['selling_price'], 2); ?></div>

                            <div class="card-qty-control">
                                <button class="card-qty-btn decrement-card" data-id="<?php echo $product['id']; ?>">-</button>
                                <span class="card-qty-value"
                                    id="qty-<?php echo $product['id']; ?>"><?php echo number_format($product['qty_on_hand']); ?></span>
                                <button class="card-qty-btn increment-card" data-id="<?php echo $product['id']; ?>">+</button>
                            </div>

                            <div class="card-actions">
                                <button class="desc-btn" data-id="<?php echo $product['id']; ?>">
                                    <i class="fas fa-info-circle"></i> INFO
                                </button>
                            </div>
                            <div class="card-actions" style="margin-top: 4px;">
                                <button class="update-btn" data-id="<?php echo $product['id']; ?>"
                                    style="width: 100%;">UPDATE</button>
                            </div>

                            <div class="last_restocked">
                                <?php echo htmlspecialchars($product['last_restocked']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Description Modal -->
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
                    <div class="product-detail-row">
                        <div class="product-detail-icon"><i class="fas fa-cubes"></i></div>
                        <div class="product-detail-text">
                            <div class="product-detail-label">Stock Quantity</div>
                            <div class="product-detail-value" id="descProductQty">-</div>
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
                <button class="close-desc-btn"><i class="fas fa-check-circle"></i> Got it</button>
            </div>
        </div>
    </div>


    <script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>

    <script>
        /* ========== SIDEBAR ========== */
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
        function toggleSidebar() { isSidebarOpen ? closeSidebar() : openSidebar(); }

        if (burgerBtn) burgerBtn.addEventListener('click', e => { e.stopPropagation(); toggleSidebar(); });
        if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', e => { e.stopPropagation(); closeSidebar(); });
        if (menuOverlay) menuOverlay.addEventListener('click', closeSidebar);

        document.querySelectorAll('.side-menu .nav-item, .side-menu .nav-dropdown-item').forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 768 && !this.closest('.nav-dropdown-toggle')) closeSidebar();
            });
        });

        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            const arrow = document.getElementById(dropdownId.replace('Dropdown', 'Arrow'));
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

        /* ========== DESCRIPTION MODAL ========== */
        const descModal = document.getElementById('descriptionModal');
        const closeDescModalBtn = document.querySelector('.close-desc-modal');
        const closeDescFooterBtn = document.querySelector('.close-desc-btn');

        function openDescriptionModal(productCard) {
            document.getElementById('descProductName').textContent = productCard.getAttribute('data-fullname') || 'N/A';
            document.getElementById('descProductUnit').textContent = productCard.getAttribute('data-unit') || 'N/A';
            document.getElementById('descProductPrice').textContent = '₱ ' + (productCard.getAttribute('data-price') || '0');
            document.getElementById('descProductQty').textContent = productCard.getAttribute('data-qty') || '0';

            const productDescription = productCard.getAttribute('data-description') || '';
            const descElement = document.getElementById('descProductDescription');
            descElement.innerHTML = productDescription.trim()
                ? productDescription.replace(/\n/g, '<br>')
                : '<em style="color: #94a3b8;">No description available.</em>';

            descModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeDescriptionModal() {
            descModal.style.display = 'none';
            document.body.style.overflow = '';
        }
        if (closeDescModalBtn) closeDescModalBtn.addEventListener('click', closeDescriptionModal);
        if (closeDescFooterBtn) closeDescFooterBtn.addEventListener('click', closeDescriptionModal);
        window.addEventListener('click', e => { if (e.target === descModal) closeDescriptionModal(); });

        /* ========== VALIDATION ========== */
        function validateText(input, errEl, name) {
            if (input.value.trim() === '') { errEl.style.display = 'block'; errEl.textContent = `Please enter a valid ${name}`; return false; }
            errEl.style.display = 'none'; return true;
        }
        function validateUnit(input, errEl, name) {
            if (input.value.trim() === '') { errEl.style.display = 'block'; errEl.textContent = `Please enter a valid ${name}`; return false; }
            errEl.style.display = 'none'; return true;
        }
        function validateNumber(input, errEl) {
            const v = input.value.trim();
            if (v === '') { errEl.style.display = 'block'; errEl.textContent = 'This field is required'; return false; }
            if (!/^\d+$/.test(v)) { errEl.style.display = 'block'; errEl.textContent = 'Please enter numbers only (0-9)'; return false; }
            if (parseInt(v) < 0) { errEl.style.display = 'block'; errEl.textContent = 'Quantity cannot be negative'; return false; }
            errEl.style.display = 'none'; return true;
        }
        function validatePrice(input, errEl) {
            const v = input.value.trim();
            if (v === '') { errEl.style.display = 'block'; errEl.textContent = 'This field is required'; return false; }
            if (!/^\d+(\.\d{1,2})?$/.test(v)) { errEl.style.display = 'block'; errEl.textContent = 'Please enter a valid price (e.g., 99.99)'; return false; }
            if (parseFloat(v) <= 0) { errEl.style.display = 'block'; errEl.textContent = 'Price must be greater than 0'; return false; }
            errEl.style.display = 'none'; return true;
        }
        function clearAddValidationErrors() {
            ['addNameError', 'addUnitError', 'addQuantityError', 'addPriceError'].forEach(id => document.getElementById(id).style.display = 'none');
        }
        function clearUpdateValidationErrors() {
            ['updateNameError', 'updateUnitError', 'updateQuantityError', 'updatePriceError'].forEach(id => document.getElementById(id).style.display = 'none');
        }

        /* ========== INPUTS ========== */
        const addNameInput = document.getElementById('productName');
        const addUnitInput = document.getElementById('productUnit');
        const addQuantityInput = document.getElementById('productQuantity');
        const addPriceInput = document.getElementById('productPrice');

        if (addNameInput) addNameInput.addEventListener('input', () => validateText(addNameInput, document.getElementById('addNameError'), 'product name'));
        if (addUnitInput) addUnitInput.addEventListener('input', () => validateUnit(addUnitInput, document.getElementById('addUnitError'), 'unit'));
        if (addQuantityInput) addQuantityInput.addEventListener('input', () => validateNumber(addQuantityInput, document.getElementById('addQuantityError')));
        if (addPriceInput) addPriceInput.addEventListener('input', () => validatePrice(addPriceInput, document.getElementById('addPriceError')));

        const updateNameInput = document.getElementById('updateProductName');
        const updateUnitInput = document.getElementById('updateUnit');
        const updateQuantityInput = document.getElementById('updateQuantity');
        const updatePriceInput = document.getElementById('updatePrice');

        if (updateNameInput) updateNameInput.addEventListener('input', () => validateText(updateNameInput, document.getElementById('updateNameError'), 'product name'));
        if (updateUnitInput) updateUnitInput.addEventListener('input', () => validateUnit(updateUnitInput, document.getElementById('updateUnitError'), 'unit'));
        if (updateQuantityInput) updateQuantityInput.addEventListener('input', () => validateNumber(updateQuantityInput, document.getElementById('updateQuantityError')));
        if (updatePriceInput) updatePriceInput.addEventListener('input', () => validatePrice(updatePriceInput, document.getElementById('updatePriceError')));

        /* ========== SEARCH ========== */
        const searchInput = document.getElementById('liveSearchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const searchInfo = document.getElementById('searchInfo');
        const productCards = document.querySelectorAll('.product-card');

        function performLiveSearch() {
            const term = searchInput ? searchInput.value.toLowerCase().trim() : '';
            let count = 0;
            productCards.forEach(card => {
                const name = (card.getAttribute('data-fullname') || '').toLowerCase();
                const match = term === '' || name.includes(term);
                card.style.display = match ? '' : 'none';
                if (match) count++;
            });
            searchInfo.innerHTML = term === ''
                ? `<i class="fas fa-info-circle"></i> Showing all ${count} products`
                : `<i class="fas fa-search"></i> Found ${count} product(s) matching "${escapeHtml(searchInput.value)}"`;
        }
        let searchTimeout;
        if (searchInput) searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performLiveSearch, 300);
        });
        if (clearSearchBtn) clearSearchBtn.addEventListener('click', () => { searchInput.value = ''; performLiveSearch(); searchInput.focus(); });
        performLiveSearch();

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /* ========== TOAST ========== */
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
        }

        /* ========== QUANTITY CONTROLS ========== */
        document.querySelectorAll('.decrement-card').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const qtySpan = document.getElementById(`qty-${this.dataset.id}`);
                let q = parseInt(qtySpan.textContent);
                if (!isNaN(q) && q > 0) {
                    qtySpan.textContent = q - 1;
                    const card = this.closest('.product-card');
                    if (card) card.setAttribute('data-qty', qtySpan.textContent);
                }
            });
        });
        document.querySelectorAll('.increment-card').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const qtySpan = document.getElementById(`qty-${this.dataset.id}`);
                let q = parseInt(qtySpan.textContent);
                if (!isNaN(q)) {
                    qtySpan.textContent = q + 1;
                    const card = this.closest('.product-card');
                    if (card) card.setAttribute('data-qty', qtySpan.textContent);
                }
            });
        });

        /* ========== DESCRIPTION BUTTON ========== */
        document.querySelectorAll('.desc-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                openDescriptionModal(btn.closest('.product-card'));
            });
        });

        /* ========== UPDATE PRODUCT ========== */
        let selectedProductId = null;
        const updateModal = document.getElementById('updateProductModal');
        const cancelUpdate = document.getElementById('cancelUpdateProduct');
        const confirmUpdate = document.getElementById('confirmUpdateProduct');

        document.querySelectorAll('.update-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const card = this.closest('.product-card');
                const productId = this.dataset.id;
                const qtySpan = document.getElementById(`qty-${productId}`);

                document.getElementById('updateProductName').value = card.querySelector('.product-title').textContent;
                document.getElementById('updateUnit').value = card.querySelector('.product-unit')?.textContent || 'Pcs';
                document.getElementById('updateQuantity').value = parseInt(qtySpan.textContent);
                document.getElementById('updatePrice').value = parseFloat(card.querySelector('.product-price').textContent.replace('₱ ', '').replace(',', ''));
                document.getElementById('updateDescription').value = card.getAttribute('data-description') || '';

                selectedProductId = productId;
                clearUpdateValidationErrors();
                updateModal.style.display = 'flex';
                setTimeout(() => document.getElementById('updateProductName').focus(), 100);
            });
        });

        if (cancelUpdate) cancelUpdate.addEventListener('click', () => {
            updateModal.style.display = 'none';
            selectedProductId = null;
        });

        if (confirmUpdate) confirmUpdate.addEventListener('click', async () => {
            const ok1 = validateText(updateNameInput, document.getElementById('updateNameError'), 'product name');
            const ok2 = validateUnit(updateUnitInput, document.getElementById('updateUnitError'), 'unit');
            const ok3 = validateNumber(updateQuantityInput, document.getElementById('updateQuantityError'));
            const ok4 = validatePrice(updatePriceInput, document.getElementById('updatePriceError'));
            if (!ok1 || !ok2 || !ok3 || !ok4) { showToast('Please correct the errors', 'error'); return; }

            const formData = new FormData();
            formData.append('action', 'update_product');
            formData.append('product_id', selectedProductId);
            formData.append('product_name', updateNameInput.value.trim());
            formData.append('unit', updateUnitInput.value.trim());
            formData.append('quantity', parseInt(updateQuantityInput.value.trim()));
            formData.append('selling_price', parseFloat(updatePriceInput.value.trim()));
            formData.append('description', document.getElementById('updateDescription').value.trim());
            formData.append('csrf_token', csrfToken);

            updateModal.style.display = 'none';
            const spinner = document.createElement('span');
            spinner.className = 'save-spinner';
            confirmUpdate.appendChild(spinner);
            confirmUpdate.disabled = true;

            try {
                const res = await fetch('../API/update_product.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast('Product updated successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Update failed', 'error');
                }
            } catch { showToast('Network error', 'error'); }
            finally { spinner.remove(); confirmUpdate.disabled = false; }
        });

        /* ========== ADD PRODUCT ========== */
        const addModal = document.getElementById('addProductModal');
        const addBtn = document.getElementById('addProductBtn');
        const cancelAdd = document.getElementById('cancelAddProduct');
        const confirmAdd = document.getElementById('confirmAddProduct');

        if (addBtn) addBtn.addEventListener('click', () => {
            document.getElementById('productName').value = '';
            document.getElementById('productUnit').value = 'Pcs';
            document.getElementById('productQuantity').value = '';
            document.getElementById('productPrice').value = '';
            document.getElementById('productDescription').value = '';

            capturedImageBase64 = null;
            stopCameraStream();
            if (cameraPhoto) cameraPhoto.src = '';
            resetCameraUI();

            clearAddValidationErrors();
            addModal.style.display = 'flex';
            setTimeout(() => document.getElementById('productName').focus(), 100);
        });

        if (cancelAdd) cancelAdd.addEventListener('click', () => {
            stopCameraStream();
            addModal.style.display = 'none';
        });

        if (confirmAdd) confirmAdd.addEventListener('click', async () => {
            const ok1 = validateText(addNameInput, document.getElementById('addNameError'), 'product name');
            const ok2 = validateUnit(addUnitInput, document.getElementById('addUnitError'), 'unit');
            const ok3 = validateNumber(addQuantityInput, document.getElementById('addQuantityError'));
            const ok4 = validatePrice(addPriceInput, document.getElementById('addPriceError'));
            if (!ok1 || !ok2 || !ok3 || !ok4) { showToast('Please correct the errors', 'error'); return; }

            const formData = new FormData();
            formData.append('action', 'add_product');
            formData.append('product_name', addNameInput.value.trim());
            formData.append('unit', addUnitInput.value.trim());
            formData.append('quantity', parseInt(addQuantityInput.value.trim()));
            formData.append('selling_price', parseFloat(addPriceInput.value.trim()));
            formData.append('description', document.getElementById('productDescription').value.trim());
            formData.append('csrf_token', csrfToken);

            if (capturedImageBase64) {
                formData.append('product_image_base64', capturedImageBase64);
            }

            addModal.style.display = 'none';
            stopCameraStream();

            const spinner = document.createElement('span');
            spinner.className = 'save-spinner';
            confirmAdd.appendChild(spinner);
            confirmAdd.disabled = true;

            try {
                const res = await fetch('../API/add_product.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast('Product added successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Add failed', 'error');
                }
            } catch { showToast('Network error', 'error'); }
            finally { spinner.remove(); confirmAdd.disabled = false; }
        });

        /* ========== CAMERA CAPTURE ========== */
        let cameraStream = null;
        let capturedImageBase64 = null;

        const cameraPreviewWrap = document.getElementById('cameraPreviewWrap');
        const cameraVideo = document.getElementById('cameraVideo');
        const cameraPhoto = document.getElementById('cameraPhoto');
        const cameraActionsIdle = document.getElementById('cameraActionsIdle');
        const cameraActionsLive = document.getElementById('cameraActionsLive');
        const cameraActionsCaptured = document.getElementById('cameraActionsCaptured');
        const openCameraBtn = document.getElementById('openCameraBtn');
        const captureBtn = document.getElementById('captureBtn');
        const stopCameraBtn = document.getElementById('stopCameraBtn');
        const retakeBtn = document.getElementById('retakeBtn');
        const removePhotoBtn = document.getElementById('removePhotoBtn');

        function resetCameraUI() {
            if (!cameraPreviewWrap) return;
            cameraPreviewWrap.classList.remove('visible');
            cameraVideo.style.display = 'none';
            cameraPhoto.style.display = 'none';
            cameraActionsIdle.style.display = 'flex';
            cameraActionsLive.style.display = 'none';
            cameraActionsCaptured.style.display = 'none';
        }

        function stopCameraStream() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(t => t.stop());
                cameraStream = null;
            }
        }

        async function openCamera() {
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 1280 } },
                    audio: false
                });
                cameraVideo.srcObject = cameraStream;
                cameraVideo.style.display = 'block';
                cameraPhoto.style.display = 'none';
                cameraPreviewWrap.classList.add('visible');
                cameraActionsIdle.style.display = 'none';
                cameraActionsLive.style.display = 'flex';
                cameraActionsCaptured.style.display = 'none';
            } catch (err) {
                console.error('Camera error:', err);
                showToast('Could not open camera. Check permissions.', 'error');
            }
        }

        function capturePhoto() {
            if (!cameraStream || !cameraVideo.videoWidth) {
                showToast('Camera not ready yet.', 'error');
                return;
            }
            const canvas = document.createElement('canvas');
            canvas.width = cameraVideo.videoWidth;
            canvas.height = cameraVideo.videoHeight;
            canvas.getContext('2d').drawImage(cameraVideo, 0, 0);

            capturedImageBase64 = canvas.toDataURL('image/jpeg', 0.85);

            cameraPhoto.src = capturedImageBase64;
            cameraPhoto.style.display = 'block';
            cameraVideo.style.display = 'none';

            stopCameraStream();

            cameraActionsLive.style.display = 'none';
            cameraActionsCaptured.style.display = 'flex';
        }

        function retakePhoto() {
            capturedImageBase64 = null;
            cameraPhoto.src = '';
            openCamera();
        }

        function removePhoto() {
            capturedImageBase64 = null;
            cameraPhoto.src = '';
            stopCameraStream();
            resetCameraUI();
        }

        if (openCameraBtn) openCameraBtn.addEventListener('click', openCamera);
        if (captureBtn) captureBtn.addEventListener('click', capturePhoto);
        if (stopCameraBtn) stopCameraBtn.addEventListener('click', () => { stopCameraStream(); resetCameraUI(); });
        if (retakeBtn) retakeBtn.addEventListener('click', retakePhoto);
        if (removePhotoBtn) removePhotoBtn.addEventListener('click', removePhoto);

        /* ========== EXCEL UPLOAD ========== */
        const fileUploadArea = document.getElementById('fileUploadArea');
        const excelFile = document.getElementById('excelFile');
        let selectedFile = null;

        if (fileUploadArea && excelFile) {
            fileUploadArea.addEventListener('click', () => excelFile.click());
            fileUploadArea.addEventListener('dragover', e => { e.preventDefault(); fileUploadArea.style.borderColor = '#f5b342'; fileUploadArea.style.background = '#fafafa'; });
            fileUploadArea.addEventListener('dragleave', () => { fileUploadArea.style.borderColor = '#ccc'; fileUploadArea.style.background = 'transparent'; });
            fileUploadArea.addEventListener('drop', e => {
                e.preventDefault();
                const file = e.dataTransfer.files[0];
                if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls'))) {
                    selectedFile = file;
                    updateFilePreview(file);
                } else alert('Please upload a valid Excel file (.xlsx or .xls)');
                fileUploadArea.style.borderColor = '#ccc';
                fileUploadArea.style.background = 'transparent';
            });
            excelFile.addEventListener('change', e => {
                const file = e.target.files[0];
                if (file) { selectedFile = file; updateFilePreview(file); }
            });
        }

        function updateFilePreview(file) {
            const placeholder = document.querySelector('.upload-placeholder');
            const preview = document.querySelector('.upload-preview');
            const nameSpan = document.querySelector('.upload-preview .file-name');
            if (placeholder) placeholder.style.display = 'none';
            if (preview) preview.style.display = 'flex';
            if (nameSpan) nameSpan.textContent = file.name;
        }

        document.querySelector('.remove-file')?.addEventListener('click', e => {
            e.stopPropagation();
            selectedFile = null;
            document.querySelector('.upload-placeholder').style.display = 'block';
            document.querySelector('.upload-preview').style.display = 'none';
            excelFile.value = '';
        });

        /* ========== TABS ========== */
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.dataset.tab;
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                tabContents.forEach(c => c.classList.remove('active'));
                document.getElementById(tabId + 'Tab').classList.add('active');
            });
        });

        /* ========== CSRF ========== */
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        /* ========== CLOSE MODALS ON OUTSIDE CLICK ========== */
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === this) this.style.display = 'none';
            });
        });
    </script>

    <?php include '../footer.php'; ?>
</body>

</html>