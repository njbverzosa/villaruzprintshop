<?php
// item.php — Product Detail View
session_start();

// ==============================================
// 1. DB CONNECTION
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 2. CHECK LOGIN
// ==============================================
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

// ==============================================
// 3. CSRF TOKEN
// ==============================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==============================================
// 4. GET product_number FROM URL
// ==============================================
$productNumber = isset($_GET['product_number']) ? trim($_GET['product_number']) : '';

if ($productNumber === '') {
    header('Location: shop.php');
    exit;
}

// ==============================================
// 5. FETCH THE PRODUCT
// ==============================================
$product = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM merchandise_inventory WHERE product_number = ? LIMIT 1");
    $stmt->execute([$productNumber]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('item.php fetch error: ' . $e->getMessage());
}

// ==============================================
// 6. REDIRECT IF NOT FOUND
// ==============================================
if (!$product) {
    header('Location: shop.php');
    exit;
}

// ==============================================
// 7. CART BADGE COUNT
// ==============================================
$cartCountStmt = $pdo->prepare("SELECT SUM(pieces) as total_items FROM cart WHERE acc_number = ?");
$cartCountStmt->execute([$accNumber]);
$cartCountResult = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
$cartTotalItems = intval($cartCountResult['total_items'] ?? 0);

// ==============================================
// 8. BUILD IMAGE URL
// ==============================================
$imageUrl = '';
$imageExists = false;

if (!empty($product['product_image'])) {
    $relativePath = '../Products/' . $product['product_image'];
    $absolutePath = dirname(__DIR__) . '/Products/' . $product['product_image'];

    if (file_exists($absolutePath)) {
        $imageUrl = $relativePath;
        $imageExists = true;
    } else {
        $imageUrl = 'https://via.placeholder.com/300x300?text=No+Image';
    }
} else {
    $imageUrl = 'https://via.placeholder.com/300x300?text=No+Image';
}

// ==============================================
// 9. FORMAT VALUES FOR DISPLAY
// ==============================================
$unit = htmlspecialchars($product['unit'] ?? 'Pcs');
$price = number_format((float) $product['selling_price'], 2);
$priceRaw = (float) $product['selling_price'];
$description = htmlspecialchars($product['description'] ?? '');
$stock = (int) ($product['qty_on_hand'] ?? 0);
$lastRestocked = htmlspecialchars($product['last_restocked'] ?? '—');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($product['product_name']); ?> | Villaruz Print Shop</title>
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
            padding: 20px;
            padding-bottom: 90px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        /* ========== SINGLE PRODUCT WRAPPER ========== */
        .item-wrapper {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            color: #3b82f6;
            border: 1px solid #e2e8f0;
            padding: 10px 18px;
            border-radius: 24px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 18px;
            transition: 0.2s;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .back-btn:hover {
            background: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
            transform: translateX(-3px);
        }

        /* ========== PRODUCT CARD ========== */
        .product-card {
            background: #ffffff;
            border-radius: 7px;
            padding: 22px 20px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .product-image-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 12px;
        }

        .product-image-clickable {
            transition: transform 0.3s ease;
            border-radius: 8px;
            max-width: 100%;
            width: 220px;
            height: auto;
            cursor: pointer;
        }

        .product-image-clickable:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .product-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #0f172a;
            line-height: 1.3;
        }

        .product-unit {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 10px;
        }

        .product-price {
            font-size: 26px;
            font-weight: 800;
            color: #3b82f6;
            margin-bottom: 16px;
        }

        /* ========== QUANTITY SELECTOR ========== */
        .quantity-selector {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 16px;
            width: 100%;
        }

        .qty-btn {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 18px;
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
            width: 80px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            text-align: center;
            font-size: 15px;
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

        /* ========== ACTION BUTTONS ========== */
        .card-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            width: 100%;
            margin-top: 4px;
        }

        .card-add-btn,
        .card-desc-btn {
            border: none;
            padding: 12px 0;
            border-radius: 5px;
            font-weight: 600;
            font-size: 14px;
            color: #ffffff;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .card-add-btn {
            background: #3b82f6;
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
        }

        .card-desc-btn:hover {
            background: #7c3aed;
            transform: scale(0.97);
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
            font-size: 22px;
        }

        .bottom-nav .nav-item span {
            font-size: 12px;
            font-weight: 500;
        }

        .bottom-nav .nav-item:hover,
        .bottom-nav .nav-item.active {
            color: #3b82f6;
        }

        .bottom-nav .nav-item .badge {
            position: absolute;
            top: 0;
            right: 4px;
            background: lightgreen;
            color: #020e20;
            font-size: 11px;
            font-weight: bold;
            padding: 1px 6px;
            border-radius: 20px;
            min-width: 12px;
            text-align: center;
            line-height: 14px;
        }

        /* ========== MODALS ========== */
        .desc-modal,
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .desc-modal {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1200;
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
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
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

        /* ========== LOADING ========== */
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
        @media (max-width: 480px) {
            body {
                padding: 14px;
                padding-bottom: 80px;
            }

            .product-card {
                padding: 18px 14px;
                border-radius: 14px;
            }

            .product-image-clickable {
                width: 180px;
            }

            .product-title {
                font-size: 17px;
            }

            .product-price {
                font-size: 22px;
            }

            .quantity-input {
                width: 70px;
                font-size: 13px;
                padding: 6px;
            }

            .card-add-btn,
            .card-desc-btn {
                font-size: 12px;
                padding: 10px 0;
            }

            .desc-modal-content {
                width: 95%;
                border-radius: 20px;
            }

            .desc-modal-header h3 {
                font-size: 17px;
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
        }
    </style>
</head>

<body>

    <div class="item-wrapper">

        <!-- Back button -->
        <a href="shop.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Shop
        </a>

        <!-- Product Card -->
        <div class="product-card"
             data-id="<?php echo (int) $product['id']; ?>"
             data-product-number="<?php echo htmlspecialchars($product['product_number']); ?>"
             data-name="<?php echo strtolower(htmlspecialchars($product['product_name'])); ?>"
             data-fullname="<?php echo htmlspecialchars($product['product_name']); ?>"
             data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>"
             data-unit="<?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?>"
             data-price="<?php echo htmlspecialchars($product['selling_price']); ?>">

            <div class="product-image-wrapper">
                <img src="<?php echo $imageUrl; ?>"
                     alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                     class="product-image-clickable"
                     onclick="openImageModal('<?php echo $imageUrl; ?>', '<?php echo htmlspecialchars($product['product_name']); ?>')">
            </div>

            <div class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></div>
            <div class="product-unit"><?php echo $unit; ?></div>
            <div class="product-price">₱ <?php echo $price; ?></div>

            <div class="quantity-selector">
                <button class="qty-btn decrement" data-id="<?php echo (int) $product['id']; ?>">-</button>
                <input type="number" class="quantity-input" id="qty-<?php echo (int) $product['id']; ?>" value="1" min="1" max="999">
                <button class="qty-btn increment" data-id="<?php echo (int) $product['id']; ?>">+</button>
            </div>

            <div class="card-actions-grid">
                <button class="card-add-btn add-to-cart-card"
                        data-id="<?php echo (int) $product['id']; ?>"
                        data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                        data-price="<?php echo htmlspecialchars($product['selling_price']); ?>"
                        data-unit="<?php echo $unit; ?>">
                    <i class="fas fa-cart-plus"></i> Add
                </button>

                <button class="card-desc-btn desc-btn"
                        data-id="<?php echo (int) $product['id']; ?>"
                        data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                        data-unit="<?php echo $unit; ?>"
                        data-price="<?php echo $price; ?>"
                        data-description="<?php echo $description; ?>">
                    <i class="fas fa-info-circle"></i> Info
                </button>
            </div>

        </div>

    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="shop.php" class="nav-item">
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
        <a href="closed.php" class="nav-item">
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
        // ============================================================
        // PHP → JS BRIDGE
        // ============================================================
        const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        const accNum = '<?php echo htmlspecialchars($accNumber); ?>';

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
        // TOAST
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
        // DESCRIPTION MODAL
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
        // IMAGE MODAL
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
        // ADD TO CART
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
        // EVENT LISTENERS
        // ============================================================
        document.querySelectorAll('.add-to-cart-card').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
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
                e.stopPropagation();
                const productName = this.dataset.name;
                const productUnit = this.dataset.unit;
                const productPrice = this.dataset.price;
                const productDescription = this.dataset.description || '';
                openDescriptionModal(productName, productUnit, productPrice, productDescription);
            });
        });
    </script>

</body>

</html>