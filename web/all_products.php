<?php
// web/all_products.php
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
} elseif ($userRole === 'Customer') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number FROM customers WHERE id = ?");
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

// ==============================================
// 5. SET TIMEZONE
// ==============================================
date_default_timezone_set('Asia/Manila');
$timezone = new DateTimeZone('Asia/Manila');

// Fetch all products sorted by last_restocked
$stmt = $pdo->prepare("
    SELECT * FROM merchandise_inventory 
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

// Group products by restock status for display
$recentlyRestocked = [];
$olderRestocked = [];
$neverRestocked = [];

foreach ($allProducts as $product) {
    if (empty($product['last_restocked'])) {
        $neverRestocked[] = $product;
    } else {
        try {
            $restockDate = DateTime::createFromFormat('j M Y g:i A', $product['last_restocked']);
            if ($restockDate === false) {
                $restockDate = new DateTime($product['last_restocked']);
            }
            $daysDiff = $restockDate->diff(new DateTime('now', $timezone))->days;

            if ($daysDiff <= 7) {
                $recentlyRestocked[] = $product;
            } else {
                $olderRestocked[] = $product;
            }
        } catch (Exception $e) {
            $neverRestocked[] = $product;
        }
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Shop Products | Villaruz Print Shop & General Merchandise</title>
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
        }

        .welcome h1 {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
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

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .search-wrapper {
            flex: 1;
            position: relative;
            max-width: 400px;
        }

        .search-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .add-product-btn {
            background: linear-gradient(145deg, #3b82f6, #6366f1);
            border: none;
            border-radius: 30px;
            padding: 10px 25px;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .add-product-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .search-info {
            font-size: 13px;
            color: #64748b;
            margin-top: 8px;
            margin-bottom: 15px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }

        .product-card {
            background: #ffffff;
            border-radius: 7px;
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

        .update-btn:disabled,
        .desc-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .last_restocked {
            font-size: 10px;
            color: #64748b;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        /* ========== MODAL STYLES ========== */
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

        /* Update Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
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
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            animation: modalFadeIn 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes modalFadeIn {
            from {
                transform: scale(0.95);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
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
            font-family: 'Poppins', sans-serif;
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

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1024px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 18px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                /* 2 columns on tablet */
                gap: 15px;
            }

            .product-card {
                padding: 14px 10px;
            }

            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-wrapper {
                max-width: none;
            }

            .add-product-btn {
                justify-content: center;
            }

            .dashboard-header {
                padding: 15px 20px;
                flex-wrap: wrap;
                gap: 10px;
            }

            .welcome h1 {
                font-size: 22px;
            }

            .modal-content {
                padding: 20px;
                max-height: 85vh;
            }

            .desc-modal-content {
                width: 95%;
            }

            .desc-modal-header h3 {
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }

            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                /* 2 columns on mobile */
                gap: 12px;
            }

            .product-card {
                padding: 12px 8px;
                border-radius: 5px;
            }

            .product-title {
                font-size: 13px;
            }

            .product-price {
                font-size: 15px;
                margin-bottom: 8px;
            }

            .product-unit {
                font-size: 10px;
                padding: 1px 8px;
                margin-bottom: 6px;
            }

            .card-qty-control {
                padding: 2px 6px;
                margin-bottom: 8px;
            }

            .card-qty-btn {
                width: 24px;
                height: 24px;
                font-size: 14px;
            }

            .card-qty-value {
                font-size: 13px;
                min-width: 30px;
            }

            .update-btn,
            .desc-btn {
                font-size: 10px;
                padding: 6px 0;
                border-radius: 6px;
            }

            .last_restocked {
                font-size: 9px;
                margin-top: 6px;
            }

            .dashboard-header {
                padding: 20px 30px;
                border-radius: 10px;
            }

            .welcome h1 {
                font-size: 18px;
            }

            .add-product-btn {
                font-size: 12px;
                padding: 8px 16px;
            }

            .search-input {
                font-size: 13px;
                padding: 10px 12px 10px 38px;
            }

            .modal-content {
                padding: 16px;
                border-radius: 16px;
            }

            .modal-content h3 {
                font-size: 18px;
            }

            .modal-btn {
                font-size: 13px;
                padding: 10px;
            }

            .desc-modal-content {
                border-radius: 20px;
            }

            .desc-modal-header {
                padding: 16px 20px;
            }

            .desc-modal-header h3 {
                font-size: 16px;
            }

            .desc-modal-body {
                padding: 16px;
            }

            .desc-modal-footer {
                padding: 16px 20px 20px;
            }

            .burger-btn {
                width: 40px;
                height: 40px;
                top: 12px;
                right: 12px;
            }

            .burger-btn i {
                font-size: 20px;
            }
        }

        /* Extra small devices */
        @media (max-width: 380px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .product-card {
                padding: 10px 6px;
            }

            .product-title {
                font-size: 12px;
            }

            .product-price {
                font-size: 13px;
            }

            .card-qty-btn {
                width: 20px;
                height: 20px;
                font-size: 12px;
            }

            .card-qty-value {
                font-size: 12px;
                min-width: 25px;
            }

            .update-btn,
            .desc-btn {
                font-size: 9px;
                padding: 4px 0;
            }
        }

        /* ========== COPY ICON STYLES ========== */

        .product-title {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
            user-select: text;
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
        }

        .copy-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 4px;
            font-size: 14px;
            color: #94a3b8;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 4px;
        }

        .copy-btn:hover {
            color: #3b82f6;
            background: #f1f5f9;
        }

        .copy-btn.copied {
            color: #10b981;
        }

        .copy-btn .copy-icon {
            transition: all 0.3s ease;
        }

        .copy-btn .check-icon {
            display: none;
            transition: all 0.3s ease;
        }

        .copy-btn.copied .copy-icon {
            display: none;
        }

        .copy-btn.copied .check-icon {
            display: inline-block;
            animation: checkPop 0.3s ease;
        }

        @keyframes checkPop {
            0% {
                transform: scale(0);
                opacity: 0;
            }

            50% {
                transform: scale(1.3);
            }

            100% {
                transform: scale(1);
                opacity: 1;
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
                        Shop
                    </h4>
                </div>
            </div>

            <div class="top-bar">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="liveSearchInput" class="search-input" placeholder="Search by product name..."
                        autocomplete="off">
                </div>
                <button class="add-product-btn" id="addProductBtn">
                    <i class="fas fa-plus-circle"></i> Add New Product
                </button>
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

                            <!-- Clickable title (copies URL) -->
                            <div class="product-title-wrapper">
                                <div class="product-title" onclick="copyProductName(event, '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>', this)" title="Copy product link">
                                    <?php echo htmlspecialchars($product['product_name']); ?> 📋
                                </div>
                            </div>

                            <div class="product-unit"><?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?></div>
                            <div class="product-price">₱ <?php echo number_format($product['selling_price'], 2); ?></div>

                            <div class="card-qty-control">
                                <button class="card-qty-btn decrement-card" data-id="<?php echo $product['id']; ?>">-</button>
                                <span class="card-qty-value" id="qty-<?php echo $product['id']; ?>"><?php echo number_format($product['qty_on_hand']); ?></span>
                                <button class="card-qty-btn increment-card" data-id="<?php echo $product['id']; ?>">+</button>
                            </div>

                            <div class="card-actions">
                                <button class="desc-btn" data-id="<?php echo $product['id']; ?>">
                                    <i class="fas fa-info-circle"></i> DESCRIPTION
                                </button>
                            </div>
                            <div class="card-actions" style="margin-top: 4px;">
                                <button class="update-btn" data-id="<?php echo $product['id']; ?>"
                                    style="width: 100%;">UPDATE</button>
                            </div>
                            <div class="last_restocked">
                                <?php echo htmlspecialchars($product['last_restocked'] ?? 'Never'); ?>
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
                    <div class="product-detail-row">
                        <div class="product-detail-icon">
                            <i class="fas fa-cubes"></i>
                        </div>
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
                    <div class="description-text" id="descProductDescription">
                        No description available.
                    </div>
                </div>
            </div>
            <div class="desc-modal-footer">
                <button class="close-desc-btn">
                    <i class="fas fa-check-circle"></i> Got it
                </button>
            </div>
        </div>
    </div>

    <!-- Update Product Modal -->
    <div id="updateProductModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-edit"></i> Update Product</h3>

            <label>Product Name <span class="required">*</span></label>
            <input type="text" id="updateProductName" placeholder="Enter product name">
            <div class="error-message" id="updateNameError">Please enter a valid product name</div>

            <label>Unit <span class="required">*</span></label>
            <input type="text" id="updateUnit" placeholder="Enter unit (e.g., Pcs, box, ream)">
            <div class="error-message" id="updateUnitError">Please enter a valid unit</div>

            <label>Quantity <span class="required">*</span></label>
            <input type="text" id="updateQuantity" placeholder="Enter quantity" pattern="[0-9]+">
            <div class="error-message" id="updateQuantityError">Please enter a valid number (0-9 only)</div>

            <label>Unit Cost (₱) <span class="required">*</span></label>
            <input type="text" id="updatePrice" placeholder="Enter selling price">
            <div class="error-message" id="updatePriceError">Please enter a valid price (numbers and decimal only)</div>

            <label>Description</label>
            <textarea id="updateDescription" placeholder="Enter product description (optional)"></textarea>

            <div class="modal-buttons">
                <button class="modal-btn modal-cancel" id="cancelUpdateProduct">Cancel</button>
                <button class="modal-btn modal-confirm" id="confirmUpdateProduct">Update Now</button>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-plus-circle"></i> Add New Product</h3>

            <label>Product Name <span class="required">*</span></label>
            <input type="text" id="productName" placeholder="Enter product name">
            <div class="error-message" id="addNameError">Please enter a valid product name</div>

            <label>Unit <span class="required">*</span></label>
            <input type="text" id="productUnit" placeholder="Enter unit (e.g., Pcs, Roll, Ream)" value="Pcs">
            <div class="error-message" id="addUnitError">Please enter a valid unit</div>

            <label>Quantity <span class="required">*</span></label>
            <input type="number" id="productQuantity" placeholder="Enter quantity on hand" step="1" min="0">
            <div class="error-message" id="addQuantityError">Please enter a valid quantity</div>

            <label>Unit Cost (₱) <span class="required">*</span></label>
            <input type="text" id="productPrice" placeholder="Enter selling price">
            <div class="error-message" id="addPriceError">Please enter a valid price</div>

            <label>Description</label>
            <textarea id="productDescription" placeholder="Enter product description (optional)"></textarea>

            <div class="modal-buttons">
                <button class="modal-btn modal-cancel" id="cancelAddProduct">Cancel</button>
                <button class="modal-btn modal-confirm" id="confirmAddProduct">Add Product</button>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let selectedProductId = null;

        // ========== DESCRIPTION MODAL FUNCTIONS ==========
        const descModal = document.getElementById('descriptionModal');
        const closeDescModalBtn = document.querySelector('.close-desc-modal');
        const closeDescFooterBtn = document.querySelector('.close-desc-btn');

        function openDescriptionModal(productCard) {
            const productName = productCard.getAttribute('data-fullname') || 'N/A';
            const productUnit = productCard.getAttribute('data-unit') || 'N/A';
            const productPrice = productCard.getAttribute('data-price') || '0';
            const productQty = productCard.getAttribute('data-qty') || '0';
            const productDescription = productCard.getAttribute('data-description') || '';

            document.getElementById('descProductName').textContent = productName;
            document.getElementById('descProductUnit').textContent = productUnit;
            document.getElementById('descProductPrice').textContent = '₱ ' + productPrice;
            document.getElementById('descProductQty').textContent = productQty;

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

        // ========== VALIDATION FUNCTIONS ==========
        function validateText(input, errorElement, fieldName) {
            const value = input.value.trim();
            if (value === '') {
                errorElement.style.display = 'block';
                errorElement.textContent = `Please enter a valid ${fieldName}`;
                return false;
            }
            errorElement.style.display = 'none';
            return true;
        }

        function validateUnit(input, errorElement, fieldName) {
            const value = input.value.trim();
            if (value === '') {
                errorElement.style.display = 'block';
                errorElement.textContent = `Please enter a valid ${fieldName}`;
                return false;
            }
            errorElement.style.display = 'none';
            return true;
        }

        function validateNumber(input, errorElement) {
            const value = input.value.trim();
            if (value === '') {
                errorElement.style.display = 'block';
                errorElement.textContent = 'This field is required';
                return false;
            }
            if (!/^\d+$/.test(value)) {
                errorElement.style.display = 'block';
                errorElement.textContent = 'Please enter numbers only (0-9)';
                return false;
            }
            if (parseInt(value) < 0) {
                errorElement.style.display = 'block';
                errorElement.textContent = 'Quantity cannot be negative';
                return false;
            }
            errorElement.style.display = 'none';
            return true;
        }

        function validatePrice(input, errorElement) {
            const value = input.value.trim();
            if (value === '') {
                errorElement.style.display = 'block';
                errorElement.textContent = 'This field is required';
                return false;
            }
            if (!/^\d+(\.\d{1,2})?$/.test(value)) {
                errorElement.style.display = 'block';
                errorElement.textContent = 'Please enter a valid price (e.g., 99.99)';
                return false;
            }
            if (parseFloat(value) <= 0) {
                errorElement.style.display = 'block';
                errorElement.textContent = 'Price must be greater than 0';
                return false;
            }
            errorElement.style.display = 'none';
            return true;
        }

        function clearAddValidationErrors() {
            document.getElementById('addNameError').style.display = 'none';
            document.getElementById('addUnitError').style.display = 'none';
            document.getElementById('addQuantityError').style.display = 'none';
            document.getElementById('addPriceError').style.display = 'none';
        }

        function clearUpdateValidationErrors() {
            document.getElementById('updateNameError').style.display = 'none';
            document.getElementById('updateUnitError').style.display = 'none';
            document.getElementById('updateQuantityError').style.display = 'none';
            document.getElementById('updatePriceError').style.display = 'none';
        }

        // Real-time validation
        const addNameInput = document.getElementById('productName');
        const addUnitInput = document.getElementById('productUnit');
        const addQuantityInput = document.getElementById('productQuantity');
        const addPriceInput = document.getElementById('productPrice');

        if (addNameInput) {
            addNameInput.addEventListener('input', () => validateText(addNameInput, document.getElementById('addNameError'), 'product name'));
        }
        if (addUnitInput) {
            addUnitInput.addEventListener('input', () => validateUnit(addUnitInput, document.getElementById('addUnitError'), 'unit'));
        }
        if (addQuantityInput) {
            addQuantityInput.addEventListener('input', () => validateNumber(addQuantityInput, document.getElementById('addQuantityError')));
        }
        if (addPriceInput) {
            addPriceInput.addEventListener('input', () => validatePrice(addPriceInput, document.getElementById('addPriceError')));
        }

        const updateNameInput = document.getElementById('updateProductName');
        const updateUnitInput = document.getElementById('updateUnit');
        const updateQuantityInput = document.getElementById('updateQuantity');
        const updatePriceInput = document.getElementById('updatePrice');

        if (updateNameInput) {
            updateNameInput.addEventListener('input', () => validateText(updateNameInput, document.getElementById('updateNameError'), 'product name'));
        }
        if (updateUnitInput) {
            updateUnitInput.addEventListener('input', () => validateUnit(updateUnitInput, document.getElementById('updateUnitError'), 'unit'));
        }
        if (updateQuantityInput) {
            updateQuantityInput.addEventListener('input', () => validateNumber(updateQuantityInput, document.getElementById('updateQuantityError')));
        }
        if (updatePriceInput) {
            updatePriceInput.addEventListener('input', () => validatePrice(updatePriceInput, document.getElementById('updatePriceError')));
        }

        // ========== BURGER MENU ==========
        const burgerBtn = document.getElementById('burgerBtn');
        const sideMenu = document.getElementById('sideMenu');
        const menuOverlay = document.getElementById('menuOverlay');

        function openMenu() {
            sideMenu.classList.add('open');
            menuOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMenu() {
            sideMenu.classList.remove('open');
            menuOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        burgerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (sideMenu.classList.contains('open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        menuOverlay.addEventListener('click', closeMenu);

        document.querySelectorAll('.side-menu .nav-item').forEach(link => {
            link.addEventListener('click', () => {
                closeMenu();
            });
        });

        // ========== SEARCH FUNCTIONALITY ==========
        const searchInput = document.getElementById('liveSearchInput');
        const searchInfo = document.getElementById('searchInfo');
        const productCards = document.querySelectorAll('.product-card');

        function performLiveSearch() {
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            let visibleCount = 0;

            productCards.forEach(card => {
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
                searchInfo.innerHTML = `<i class="fas fa-search"></i> Found ${visibleCount} product(s) matching "${escapeHtml(searchTerm)}"`;
            }
        }

        let searchTimeout;
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performLiveSearch, 100);
            });
        }
        performLiveSearch();

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

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

        // ========== QUANTITY CONTROLS ==========
        document.querySelectorAll('.decrement-card').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.dataset.id;
                const qtySpan = document.getElementById(`qty-${productId}`);
                let currentQty = parseInt(qtySpan.textContent.replace(/,/g, ''));
                if (!isNaN(currentQty) && currentQty > 0) {
                    qtySpan.textContent = currentQty - 1;
                    const card = this.closest('.product-card');
                    if (card) card.setAttribute('data-qty', qtySpan.textContent);
                }
            });
        });

        document.querySelectorAll('.increment-card').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.dataset.id;
                const qtySpan = document.getElementById(`qty-${productId}`);
                let currentQty = parseInt(qtySpan.textContent.replace(/,/g, ''));
                if (!isNaN(currentQty)) {
                    qtySpan.textContent = currentQty + 1;
                    const card = this.closest('.product-card');
                    if (card) card.setAttribute('data-qty', qtySpan.textContent);
                }
            });
        });

        // ========== DESCRIPTION BUTTON ==========
        document.querySelectorAll('.desc-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const productCard = this.closest('.product-card');
                openDescriptionModal(productCard);
            });
        });

        // ========== UPDATE BUTTON ==========
        document.querySelectorAll('.update-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.dataset.id;
                const card = this.closest('.product-card');
                const productName = card.querySelector('.product-title').textContent;
                const unitElem = card.querySelector('.product-unit');
                const unit = unitElem ? unitElem.textContent : 'Pcs';
                const qtySpan = document.getElementById(`qty-${productId}`);
                const currentQty = parseInt(qtySpan.textContent.replace(/,/g, ''));
                const priceElem = card.querySelector('.product-price');
                const currentPrice = parseFloat(priceElem.textContent.replace('₱ ', '').replace(/,/g, ''));
                const description = card.getAttribute('data-description') || '';

                document.getElementById('updateProductName').value = productName;
                document.getElementById('updateUnit').value = unit;
                document.getElementById('updateQuantity').value = currentQty;
                document.getElementById('updatePrice').value = currentPrice;
                document.getElementById('updateDescription').value = description;

                selectedProductId = productId;
                clearUpdateValidationErrors();
                document.getElementById('updateProductModal').style.display = 'flex';
                setTimeout(() => document.getElementById('updateProductName').focus(), 100);
            });
        });

        // ========== UPDATE PRODUCT ==========
        const updateModal = document.getElementById('updateProductModal');
        const cancelUpdate = document.getElementById('cancelUpdateProduct');
        const confirmUpdate = document.getElementById('confirmUpdateProduct');

        cancelUpdate.addEventListener('click', () => {
            updateModal.style.display = 'none';
            selectedProductId = null;
        });

        confirmUpdate.addEventListener('click', async () => {
            const isValidName = validateText(updateNameInput, document.getElementById('updateNameError'), 'product name');
            const isValidUnit = validateUnit(updateUnitInput, document.getElementById('updateUnitError'), 'unit');
            const isValidQuantity = validateNumber(updateQuantityInput, document.getElementById('updateQuantityError'));
            const isValidPrice = validatePrice(updatePriceInput, document.getElementById('updatePriceError'));

            if (!isValidName || !isValidUnit || !isValidQuantity || !isValidPrice) {
                showToast('Please correct the errors in the form', 'error');
                return;
            }

            const productName = updateNameInput.value.trim();
            const unit = updateUnitInput.value.trim();
            const quantity = parseInt(updateQuantityInput.value.trim());
            const price = parseFloat(updatePriceInput.value.trim());
            const description = document.getElementById('updateDescription').value.trim();

            updateModal.style.display = 'none';
            const saveIndicator = document.createElement('span');
            saveIndicator.className = 'save-spinner';
            confirmUpdate.appendChild(saveIndicator);
            confirmUpdate.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'update_product');
                formData.append('product_id', selectedProductId);
                formData.append('product_name', productName);
                formData.append('unit', unit);
                formData.append('quantity', quantity);
                formData.append('selling_price', price);
                formData.append('description', description);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/update_product.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showToast('Product updated successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Update failed', 'error');
                }
            } catch (err) {
                showToast('Network error', 'error');
            } finally {
                saveIndicator.remove();
                confirmUpdate.disabled = false;
            }
        });

        // ========== ADD PRODUCT ==========
        const addModal = document.getElementById('addProductModal');
        const addBtn = document.getElementById('addProductBtn');
        const cancelAdd = document.getElementById('cancelAddProduct');
        const confirmAdd = document.getElementById('confirmAddProduct');

        addBtn.addEventListener('click', () => {
            document.getElementById('productName').value = '';
            document.getElementById('productUnit').value = 'Pcs';
            document.getElementById('productQuantity').value = '';
            document.getElementById('productPrice').value = '';
            document.getElementById('productDescription').value = '';
            clearAddValidationErrors();
            addModal.style.display = 'flex';
            setTimeout(() => document.getElementById('productName').focus(), 100);
        });

        cancelAdd.addEventListener('click', () => {
            addModal.style.display = 'none';
        });

        confirmAdd.addEventListener('click', async () => {
            const isValidName = validateText(addNameInput, document.getElementById('addNameError'), 'product name');
            const isValidUnit = validateUnit(addUnitInput, document.getElementById('addUnitError'), 'unit');
            const isValidQuantity = validateNumber(addQuantityInput, document.getElementById('addQuantityError'));
            const isValidPrice = validatePrice(addPriceInput, document.getElementById('addPriceError'));

            if (!isValidName || !isValidUnit || !isValidQuantity || !isValidPrice) {
                showToast('Please correct the errors in the form', 'error');
                return;
            }

            const productName = addNameInput.value.trim();
            const unit = addUnitInput.value.trim();
            const quantity = parseInt(addQuantityInput.value.trim());
            const price = parseFloat(addPriceInput.value.trim());
            const description = document.getElementById('productDescription').value.trim();

            addModal.style.display = 'none';
            const saveIndicator = document.createElement('span');
            saveIndicator.className = 'save-spinner';
            confirmAdd.appendChild(saveIndicator);
            confirmAdd.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'add_product');
                formData.append('product_name', productName);
                formData.append('unit', unit);
                formData.append('quantity', quantity);
                formData.append('selling_price', price);
                formData.append('description', description);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/add_product.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showToast('Product added successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Add failed', 'error');
                }
            } catch (err) {
                showToast('Network error', 'error');
            } finally {
                saveIndicator.remove();
                confirmAdd.disabled = false;
            }
        });

        // ============================================================
        // COPY PRODUCT NAME FROM TITLE CLICK
        // ============================================================
        function copyProductName(event, productName, element) {
            // Stop event from bubbling
            event.stopPropagation();

            // Construct the full URL
            const baseUrl = window.location.origin + '/public/shop';
            const encodedProductName = encodeURIComponent(productName);
            const fullUrl = baseUrl + '?product_name=' + encodedProductName;

            // Copy the full URL to clipboard
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(fullUrl)
                    .then(() => {
                        showCopiedFeedbackTitle(element);
                    })
                    .catch(() => {
                        fallbackCopyTextTitle(fullUrl, element);
                    });
            } else {
                fallbackCopyTextTitle(fullUrl, element);
            }
        }

        function fallbackCopyTextTitle(text, element) {
            const tempInput = document.createElement('input');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            tempInput.setSelectionRange(0, 99999);

            try {
                document.execCommand('copy');
                showCopiedFeedbackTitle(element);
            } catch (err) {
                element.style.color = '#ef4444';
                setTimeout(() => {
                    element.style.color = '';
                }, 1500);
            }

            document.body.removeChild(tempInput);
        }

        function showCopiedFeedbackTitle(element) {
            // Save the original text (remove any existing emojis)
            let originalText = element.textContent;
            // Remove all emoji characters (📋, ✅, ⭕, ☑️, etc.)
            originalText = originalText.replace(/[📋✅⭕☑️🔵🟢✔️✓]/g, '').trim();

            // Show circle checkmark (you can use ✅, ⭕, or ☑️)
            element.innerHTML = originalText + ' ✔️';

            // Reset after 2.5 seconds back to copy icon
            setTimeout(() => {
                element.innerHTML = originalText + ' 📋';
                element.style.color = '';
            }, 2500);
        }

        console.log('📦 Shop Stock Management loaded');
        console.log('🔄 Auto-refresh on update/add');
        console.log('📱 2-column grid on mobile');
    </script>
</body>

</html>