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
                <a href="upload_product.php" class="add-product-btn" id="addProductBtn">
                    <i class="fas fa-plus-circle"></i> Add New Product
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

                            <div class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-unit"><?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?></div>

                            <div class="product-image-wrapper">
                                <img src="../Products/<?php echo htmlspecialchars($product['product_image']); ?>"
                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                    style="width: 100px; height: auto;">
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

    <script>
        /* ========== SIDEBAR ========== */
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
        function toggleSidebar() { isSidebarOpen ? closeSidebar() : openSidebar(); }

        if (burgerBtn) burgerBtn.addEventListener('click', e => { e.stopPropagation(); toggleSidebar(); });
        if (menuOverlay) menuOverlay.addEventListener('click', closeSidebar);

        document.querySelectorAll('.side-menu .nav-item, .side-menu .nav-dropdown-item').forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 768 && !this.closest('.nav-dropdown-toggle')) closeSidebar();
            });
        });

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

        /* ========== CSRF ========== */
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    </script>

    <?php include '../footer.php'; ?>
</body>

</html>