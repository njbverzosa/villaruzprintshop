<?php
// web/all_products.php

session_start();

require_once __DIR__ . '/../DB_Conn/config.php';

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

        .side-menu {
            position: relative;
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

        .welcome h1 {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
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

        .search-info {
            font-size: 13px;
            color: #64748b;
            padding: 0 25px 10px 25px;
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
            /* ✅ needed for absolute trash */
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

        /* ✅ Price + Unit on a single line, no background on unit */
        .price-unit-grid {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 6px;
            margin-bottom: 12px;
            white-space: nowrap;
            width: 100%;
        }

        .product-price {
            font-size: 16px;
            font-weight: 800;
            color: #3b82f6;
            line-height: 1.2;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .product-unit {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            line-height: 1.2;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* ✅ INFO + UPDATE aligned in one grid row */
        .card-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            width: 100%;
            margin-top: 4px;
        }

        .update-btn,
        .desc-btn {
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
            width: 100%;
            text-decoration: none;
            box-sizing: border-box;
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

        .delete-btn {
            position: absolute;
            top: 4px;
            right: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            color: black;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 3;
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

            .search-info {
                font-size: 11px;
                padding: 0 12px 8px 12px;
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
                <a href="upload_products.php" class="add-product-btn" id="addProductBtn">
                    <i class="fas fa-plus-circle"></i> Add New
                </a>

                <?php if ((int) ($user['authorize_access'] ?? -1) === 0): ?>
                    <a href="/API/download_images.php" class="add-product-btn" id="downloadImagesBtn">
                        <i class="fas fa-download"></i> Images
                    </a>
                <?php endif; ?>

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
                            data-price="<?php echo number_format($product['selling_price'], 2); ?>">

                            <!-- ✅ Trash button — top-right corner -->
                            <a href="../API/delete_product.php?product_number=<?php echo urlencode($product['product_number']); ?>"
                                class="delete-btn" onclick="return confirm('Are you sure you want to delete this product?');"
                                title="Delete product">
                                <i class="fas fa-trash"></i>
                            </a>

                            <div class="product-image-wrapper">
                                <img src="../Products/<?php echo htmlspecialchars($product['product_image']); ?>"
                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                    class="product-image-clickable"
                                    onclick="openImageModal('../Products/<?php echo htmlspecialchars($product['product_image']); ?>', '<?php echo htmlspecialchars($product['product_name']); ?>')"
                                    style="width: 150px; height: auto;border-radius:5px;">
                            </div>
                            <div class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="price-unit-grid">
                                <div class="product-price">₱ <?php echo number_format($product['selling_price'], 2); ?></div>/
                                <div class="product-unit"><?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?></div>
                            </div>

                            <div class="card-actions-grid">
                                <button class="desc-btn" data-id="<?php echo $product['id']; ?>">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                                <a href="update_products.php?product_number=<?php echo urlencode($product['product_number']); ?>"
                                    class="update-btn">
                                    <i class="fas fa-pen"></i>
                                </a>
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
                    <i class="fas fa-check-circle"></i> Got it
                </button>
            </div>
        </div>
    </div>

    <script>
        // ========== SIDEBAR TOGGLE (Mobile Only) ==========
        const burgerBtn = document.getElementById('burgerBtn');
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

        // ========== DROPDOWN TOGGLE (Sidebar) ==========
        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            const arrowId = dropdownId.replace('Dropdown', 'Arrow');
            const arrow = document.getElementById(arrowId);

            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (arrow) {
                arrow.classList.toggle('rotated');
            }
        }

        // ========== DESCRIPTION MODAL ==========
        const descModal = document.getElementById('descriptionModal');
        const closeDescModalBtn = document.querySelector('.close-desc-modal');
        const closeDescFooterBtn = document.querySelector('.close-desc-btn');

        function openDescriptionModal(productCard) {
            const productName = productCard.getAttribute('data-fullname') || 'N/A';
            const productUnit = productCard.getAttribute('data-unit') || 'N/A';
            const productPrice = productCard.getAttribute('data-price') || '0';
            const productDescription = productCard.getAttribute('data-description') || '';

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

        // ========== SEARCH FUNCTIONALITY ==========
        const searchInput = document.getElementById('liveSearchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
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
                searchInfo.innerHTML =
                    `<i class="fas fa-search"></i> Found ${visibleCount} product(s) matching "${escapeHtml(searchInput.value)}"`;
            }
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

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ========== DESCRIPTION BUTTON ==========
        document.querySelectorAll('.desc-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const productCard = this.closest('.product-card');
                openDescriptionModal(productCard);
            });
        });
    </script>

    <?php include '../footer.php'; ?>
</body>

</html>