<?php
// items.php 
require_once 'access_sessions.php';

$userAccNumber = $user['acc_number'] ?? '';
$deliveryNumber = $_GET['delivery_number'] ?? '';

// Redirect if no delivery number provided
if (empty($deliveryNumber)) {
    header('Location: orders.php');
    exit;
}

// Fetch delivery info
$stmt = $pdo->prepare("SELECT * FROM for_deliveries WHERE delivery_number = ? AND acc_number = ?");
$stmt->execute([$deliveryNumber, $userAccNumber]);
$delivery = $stmt->fetch();

// If delivery not found, redirect
if (!$delivery) {
    header('Location: orders.php');
    exit;
}

// Fetch order items from order_status_history
$stmt = $pdo->prepare("SELECT * FROM order_status_history WHERE delivery_number = ? ORDER BY id ASC");
$stmt->execute([$deliveryNumber]);
$orderItems = $stmt->fetchAll();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Function to format amount
function formatAmount($amount)
{
    return '₱ ' . number_format($amount, 2, '.', ',');
}

// Function to format date
function formatDeliveryDateDisplay($date)
{
    if (empty($date) || $date === 'N/A')
        return 'N/A';
    $timestamp = strtotime($date);
    if ($timestamp) {
        return date('D, j M Y', $timestamp);
    }
    return 'N/A';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Order Items | Villaruz Print Shop & General Merchandise</title>
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
            display: flex;
            flex: 1;
            min-height: 100vh;
        }

        /* LEFT SIDEBAR */
        .side-menu {
            width: 280px;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            flex-shrink: 0;
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.03);
        }

        /* Menu header with user info and logo */
        .menu-header {
            padding: 25px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            text-align: center;
        }

        .logo {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .logo img {
            max-width: 200px;
            height: auto;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .logo img:hover {
            transform: scale(1.02);
        }

        .menu-header .user-greeting {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }

        .menu-header .user-name {
            font-weight: 700;
            font-size: 15px;
            color: #0f172a;
            margin-top: 4px;
            white-space: nowrap;
        }


        .menu-nav {
            flex: 1;
            padding: 24px 16px;
        }

        .menu-nav .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
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

        .main-content {
            flex: 1;
            padding: 30px 35px;
            overflow-y: auto;
            background: #f1f5f9;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }

        .page-header h2 i {
            color: #3b82f6;
            margin-right: 10px;
        }

        .back-btn {
            background: #64748b;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .back-btn:hover {
            background: #475569;
            transform: translateY(-2px);
        }

        .add-order-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .add-order-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        /* Delivery Info Card */
        .delivery-info-card {
            background: white;
            border-radius: 20px;
            padding: 20px 25px;
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            justify-content: space-between;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .info-item i {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            border-radius: 12px;
            color: #3b82f6;
            font-size: 18px;
        }

        .info-item .info-label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
        }

        .info-item .info-value {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
        }

        /* Items Table */
        .items-container {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            text-align: left;
            padding: 18px 20px;
            background: #f8fafc;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }

        .items-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .items-table tr:last-child td {
            border-bottom: none;
        }

        .items-table tr:hover {
            background: #f8fafc;
        }

        .items-table tr.editing {
            background: #fef3c7;
        }

        /* Editable Input Styles */
        .editable-input {
            padding: 8px 12px;
            border: 2px solid #3b82f6;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            width: 100%;
            max-width: 200px;
            background: white;
        }

        .editable-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .product-name-input {
            min-width: 180px;
        }

        .price-input,
        .pieces-input {
            width: 100px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-edit,
        .btn-update,
        .btn-remove {
            padding: 6px 14px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-edit {
            background: #f59e0b;
            color: white;
        }

        .btn-edit:hover {
            background: #d97706;
            transform: translateY(-1px);
        }

        .btn-update {
            background: #10b981;
            color: white;
        }

        .btn-update:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-remove {
            background: #ef4444;
            color: white;
        }

        .btn-remove:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .btn-remove:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .total-row {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
        }

        .total-row td {
            padding: 20px;
            font-weight: 700;
            font-size: 18px;
        }

        .empty-items {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-items i {
            font-size: 60px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        /* Modal Styles */
        .instruction-modal {
            display: none;
            position: fixed;
            z-index: 1200;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
        }

        .modal-container {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 500px;
            animation: modalSlideIn 0.3s ease;
            overflow: hidden;
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

        .modal-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 20px 25px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .modal-close:hover {
            transform: scale(1.1);
        }

        .modal-body {
            padding: 30px;
        }

        .instruction-list {
            list-style: none;
        }

        .instruction-list li {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .instruction-list li:last-child {
            border-bottom: none;
        }

        .step-number {
            width: 36px;
            height: 36px;
            background: #10b981;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
        }

        .step-text {
            flex: 1;
            font-size: 14px;
            color: #1e293b;
            line-height: 1.5;
        }

        .step-text strong {
            color: #10b981;
            font-weight: 600;
        }

        .delivery-number-box {
            background: #f1f5f9;
            padding: 12px;
            border-radius: 12px;
            margin: 15px 0;
            text-align: center;
            font-family: monospace;
            font-size: 16px;
            font-weight: 600;
            color: #3b82f6;
            word-break: break-all;
        }

        .modal-footer {
            padding: 20px 30px 30px;
            text-align: center;
        }

        .btn-got-it {
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-got-it:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 2000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
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

        footer {
            background: #ffffff;
            padding: 20px 5%;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
            margin-top: auto;
        }

        @media (max-width: 768px) {
            .app-wrapper {
                flex-direction: column;
            }

            .side-menu {
                width: 100%;
                height: auto;
                position: relative;
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
            }

            .main-content {
                padding: 20px;
            }

            .delivery-info-card {
                flex-direction: column;
                gap: 15px;
            }

            .items-table th,
            .items-table td {
                padding: 12px;
                font-size: 12px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }

            .menu-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                padding: 16px;
            }

            .menu-nav .nav-item {
                flex: 1;
                min-width: 100px;
                justify-content: center;
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .add-order-btn,
            .back-btn {
                justify-content: center;
            }
        }

        .receipt-btn {
            background: #858484;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .receipt-btn:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .receipt-btn i {
            font-size: 18px;
        }
    </style>
</head>

<body>
    <div class="app-wrapper">
        <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">
        <input type="hidden" id="deliveryNumber" value="<?php echo htmlspecialchars($deliveryNumber); ?>">

        <!-- LEFT SIDEBAR (ALWAYS EXPANDED, NO TOGGLE BUTTON) -->
        <div class="side-menu">
            <div class="menu-header">
                <div class="logo">
                    <img src="logo/logo.jpeg" alt="Villaruz Print Shop Logo">
                </div>
                <div class="user-greeting">Logged in as</div>
                <div class="user-name"><?php echo $user['f_name']; ?></div>
            </div>
            <div class="menu-nav">
                <a href="shop.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Shop</span>
                </a>
                <a href="cart.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Cart</span>
                </a>
                <a href="orders.php" class="nav-item active">
                    <i class="fas fa-truck"></i>
                    <span>Order</span>
                </a>
                <a href="closed.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>


        <main class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-boxes"></i> Order Items</h2>
                <button class="receipt-btn delivery-receipt" onclick="generateReceipt()">
                    <i class="fas fa-truck"></i> Delivery Receipt
                </button>
                <div style="display: flex; gap: 10px;">
                    <button class="add-order-btn" onclick="openInstructionModal()">
                        <i class="fas fa-plus-circle"></i> Add item for this Order ?
                    </button>
                    <a href="orders.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Orders</a>
                </div>
            </div>

            <!-- Delivery Information Card -->
            <div class="delivery-info-card">
                <div class="info-item">
                    <i class="fas fa-hashtag"></i>
                    <div>
                        <div class="info-label">Delivery Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($delivery['delivery_number']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-user"></i>
                    <div>
                        <div class="info-label">Ordered By</div>
                        <div class="info-value"><?php echo htmlspecialchars($delivery['ordered_by']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <div class="info-label">Delivery Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($delivery['delivery_address']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-calendar"></i>
                    <div>
                        <div class="info-label">Delivery Date</div>
                        <div class="info-value">
                            <?php echo formatDeliveryDateDisplay($delivery['delivery_date'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-tag"></i>
                    <div>
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge">
                                <?php echo htmlspecialchars($delivery['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="items-container">
                <?php if (empty($orderItems)): ?>
                    <div class="empty-items">
                        <i class="fas fa-box-open"></i>
                        <h3>No Items Found</h3>
                        <p>No items found for this order.</p>
                    </div>
                <?php else: ?>
                    <table class="items-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Unit</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php foreach ($orderItems as $item):
                                $itemTotal = floatval($item['selling_price']) * intval($item['pieces']);
                                ?>
                                <tr data-id="<?php echo $item['id']; ?>" data-editing="false">
                                    <td class="product-name-cell"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td class="unit-cell"><?php echo htmlspecialchars($item['unit'] ?? 'Pcs'); ?></td>
                                    <td class="price-cell"><?php echo number_format($item['selling_price'], 2); ?></td>
                                    <td class="pieces-cell"><?php echo $item['pieces']; ?></td>
                                    <td class="total-cell"><?php echo number_format($itemTotal, 2); ?></td>
                                    <td class="actions-cell">
                                        <div class="action-buttons">
                                            <button class="btn-edit" onclick="toggleEdit(this, <?php echo $item['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn-remove"
                                                onclick="removeItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['product_name']); ?>')">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="total-row">
                            <tr>
                                <td colspan="4" style="text-align: right;"><strong>TOTAL AMOUNT:</strong></td>
                                <td colspan="2"><strong id="grandTotal">₱
                                        <?php echo number_format($delivery['total_amount'], 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Instruction Modal -->
    <div id="instructionModal" class="instruction-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> How to Add Items to This Order</h3>
                <span class="modal-close" onclick="closeInstructionModal()">&times;</span>
            </div>
            <div class="modal-body">
                <ul class="instruction-list">
                    <li>
                        <div class="step-number">1</div>
                        <div class="step-text">Go to <strong>Shop</strong> page and add products to your cart</div>
                    </li>
                    <li>
                        <div class="step-number">2</div>
                        <div class="step-text">Go to <strong>Cart</strong> page</div>
                    </li>
                    <li>
                        <div class="step-number">3</div>
                        <div class="step-text">Copy this <strong>Delivery Number</strong> below:</div>
                    </li>
                </ul>
                <div class="delivery-number-box" id="deliveryNumberCopy">
                    <?php echo htmlspecialchars($deliveryNumber); ?>
                    <button onclick="copyDeliveryNumber()"
                        style="margin-left: 10px; background: #3b82f6; color: white; border: none; padding: 4px 10px; border-radius: 6px; cursor: pointer; font-size: 12px;">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <ul class="instruction-list">
                    <li>
                        <div class="step-number">4</div>
                        <div class="step-text">Paste the <strong>Delivery Number</strong> in the Cart page input field
                        </div>
                    </li>
                    <li>
                        <div class="step-number">5</div>
                        <div class="step-text">Click <strong>Submit</strong> to add items to this order</div>
                    </li>
                </ul>
            </div>
            <div class="modal-footer">
                <button class="btn-got-it" onclick="closeInstructionModal()"><i class="fas fa-check"></i> Got
                    it!</button>
            </div>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner"></i>
            <p>Processing...</p>
        </div>
    </div>

    <footer>
        <div class="copyright">
            <p>© 2026 Villaruz Print Shop & General Merchandise — Quality prints & everyday goods, delivered with care.
            </p>
        </div>
    </footer>

    <script>
        const csrfToken = document.getElementById('csrfToken').value;
        const deliveryNumber = document.getElementById('deliveryNumber').value;
        let currentlyEditingRow = null;

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

        // Instruction Modal Functions
        function openInstructionModal() {
            document.getElementById('instructionModal').style.display = 'flex';
        }

        function closeInstructionModal() {
            document.getElementById('instructionModal').style.display = 'none';
        }

        function copyDeliveryNumber() {
            const deliveryNumber = document.getElementById('deliveryNumber').value;
            navigator.clipboard.writeText(deliveryNumber).then(() => {
                showToast('Delivery number copied to clipboard!', 'success');
            }).catch(() => {
                showToast('Failed to copy', 'error');
            });
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('instructionModal');
            if (event.target === modal) {
                closeInstructionModal();
            }
        }

        function formatNumber(value) {
            return '₱ ' + parseFloat(value).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function updateGrandTotal() {
            let total = 0;
            document.querySelectorAll('#tableBody tr').forEach(row => {
                const price = parseFloat(row.querySelector('.price-cell')?.innerText || row.querySelector('.price-cell input')?.value || 0);
                const pieces = parseInt(row.querySelector('.pieces-cell')?.innerText || row.querySelector('.pieces-cell input')?.value || 0);
                total += price * pieces;
            });
            document.getElementById('grandTotal').innerHTML = formatNumber(total);
        }

        // Toggle Edit Mode - changes row to editable and button to UPDATE
        function toggleEdit(button, itemId) {
            const row = button.closest('tr');

            // If another row is editing, prevent editing multiple rows
            if (currentlyEditingRow && currentlyEditingRow !== row) {
                showToast('Please save or cancel the current edit first', 'error');
                return;
            }

            const isEditing = row.getAttribute('data-editing') === 'true';

            if (!isEditing) {
                // Enter Edit Mode
                enterEditMode(row, button, itemId);
            } else {
                // Exit Edit Mode and Save
                exitEditModeAndSave(row, button, itemId);
            }
        }

        function enterEditMode(row, button, itemId) {
            // Get current values
            const productName = row.cells[0].innerText;
            const unit = row.cells[1].innerText;
            const price = row.cells[2].innerText;
            const pieces = row.cells[3].innerText;

            // Store original values as data attributes
            row.setAttribute('data-original-product', productName);
            row.setAttribute('data-original-unit', unit);
            row.setAttribute('data-original-price', price);
            row.setAttribute('data-original-pieces', pieces);

            // Create editable inputs
            const productInput = document.createElement('input');
            productInput.type = 'text';
            productInput.value = productName;
            productInput.className = 'editable-input product-name-input';

            const unitInput = document.createElement('input');
            unitInput.type = 'text';
            unitInput.value = unit;
            unitInput.className = 'editable-input';
            unitInput.style.width = '80px';

            const priceInput = document.createElement('input');
            priceInput.type = 'number';
            priceInput.value = parseFloat(price);
            priceInput.step = '0.01';
            priceInput.min = '0';
            priceInput.className = 'editable-input price-input';

            const piecesInput = document.createElement('input');
            piecesInput.type = 'number';
            piecesInput.value = parseInt(pieces);
            piecesInput.step = '1';
            piecesInput.min = '1';
            piecesInput.className = 'editable-input pieces-input';

            // Replace cell contents with inputs
            row.cells[0].innerHTML = '';
            row.cells[0].appendChild(productInput);

            row.cells[1].innerHTML = '';
            row.cells[1].appendChild(unitInput);

            row.cells[2].innerHTML = '';
            row.cells[2].appendChild(priceInput);

            row.cells[3].innerHTML = '';
            row.cells[3].appendChild(piecesInput);

            // Update total cell
            row.cells[4].innerHTML = formatNumber(parseFloat(priceInput.value) * parseInt(piecesInput.value));

            // Change button to UPDATE
            button.innerHTML = '<i class="fas fa-save"></i> UPDATE';
            button.classList.remove('btn-edit');
            button.classList.add('btn-update');

            // Add real-time total update
            const updateTotal = () => {
                const newPrice = parseFloat(priceInput.value) || 0;
                const newPieces = parseInt(piecesInput.value) || 0;
                row.cells[4].innerHTML = formatNumber(newPrice * newPieces);
                updateGrandTotal();
            };

            priceInput.addEventListener('input', updateTotal);
            piecesInput.addEventListener('input', updateTotal);

            // Set editing flag
            row.setAttribute('data-editing', 'true');
            currentlyEditingRow = row;

            // Focus on first input
            productInput.focus();
        }

        async function exitEditModeAndSave(row, button, itemId) {
            // Get edited values
            const productInput = row.cells[0].querySelector('input');
            const unitInput = row.cells[1].querySelector('input');
            const priceInput = row.cells[2].querySelector('input');
            const piecesInput = row.cells[3].querySelector('input');

            const newProductName = productInput ? productInput.value.trim() : row.getAttribute('data-original-product');
            const newUnit = unitInput ? unitInput.value.trim() : row.getAttribute('data-original-unit');
            const newPrice = parseFloat(priceInput ? priceInput.value : row.getAttribute('data-original-price'));
            const newPieces = parseInt(piecesInput ? piecesInput.value : row.getAttribute('data-original-pieces'));

            const originalProduct = row.getAttribute('data-original-product');
            const originalUnit = row.getAttribute('data-original-unit');
            const originalPrice = parseFloat(row.getAttribute('data-original-price'));
            const originalPieces = parseInt(row.getAttribute('data-original-pieces'));

            // Check if any changes were made
            const hasChanges = (newProductName !== originalProduct) ||
                (newUnit !== originalUnit) ||
                (newPrice !== originalPrice) ||
                (newPieces !== originalPieces);

            if (!hasChanges) {
                // No changes, just exit edit mode without saving
                exitEditModeWithoutSave(row, button);
                return;
            }

            // Validate
            if (!newProductName) {
                showToast('Product name cannot be empty', 'error');
                return;
            }
            if (isNaN(newPrice) || newPrice <= 0) {
                showToast('Price must be greater than 0', 'error');
                return;
            }
            if (isNaN(newPieces) || newPieces < 1) {
                showToast('Quantity must be at least 1', 'error');
                return;
            }

            showLoading();

            try {
                const formData = new FormData();
                formData.append('action', 'update_item');
                formData.append('item_id', itemId);
                formData.append('delivery_number', deliveryNumber);
                formData.append('product_name', newProductName);
                formData.append('unit', newUnit);
                formData.append('selling_price', newPrice);
                formData.append('pieces', newPieces);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../Customer_API/update_order_item.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Update row with new values
                    row.cells[0].innerHTML = escapeHtml(newProductName);
                    row.cells[1].innerHTML = escapeHtml(newUnit);
                    row.cells[2].innerHTML = newPrice.toFixed(2);
                    row.cells[3].innerHTML = newPieces;
                    row.cells[4].innerHTML = formatNumber(newPrice * newPieces);

                    // Update button back to Edit
                    button.innerHTML = '<i class="fas fa-edit"></i> Edit';
                    button.classList.remove('btn-update');
                    button.classList.add('btn-edit');

                    // Update stored original values
                    row.setAttribute('data-original-product', newProductName);
                    row.setAttribute('data-original-unit', newUnit);
                    row.setAttribute('data-original-price', newPrice);
                    row.setAttribute('data-original-pieces', newPieces);

                    row.setAttribute('data-editing', 'false');
                    currentlyEditingRow = null;

                    updateGrandTotal();
                    showToast('Item updated successfully!', 'success');
                } else {
                    showToast(data.message || 'Failed to update item', 'error');
                    exitEditModeWithoutSave(row, button);
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
                exitEditModeWithoutSave(row, button);
            } finally {
                hideLoading();
            }
        }

        function exitEditModeWithoutSave(row, button) {
            // Restore original values
            const originalProduct = row.getAttribute('data-original-product');
            const originalUnit = row.getAttribute('data-original-unit');
            const originalPrice = row.getAttribute('data-original-price');
            const originalPieces = row.getAttribute('data-original-pieces');

            row.cells[0].innerHTML = escapeHtml(originalProduct);
            row.cells[1].innerHTML = escapeHtml(originalUnit);
            row.cells[2].innerHTML = originalPrice;
            row.cells[3].innerHTML = originalPieces;
            row.cells[4].innerHTML = formatNumber(parseFloat(originalPrice) * parseInt(originalPieces));

            // Change button back to Edit
            button.innerHTML = '<i class="fas fa-edit"></i> Edit';
            button.classList.remove('btn-update');
            button.classList.add('btn-edit');

            row.setAttribute('data-editing', 'false');
            currentlyEditingRow = null;

            updateGrandTotal();
        }

        // Remove Item
        async function removeItem(itemId, productName) {
            const confirmed = confirm(`Remove "${productName}" from this order?`);
            if (!confirmed) return;

            showLoading();

            try {
                const formData = new FormData();
                formData.append('action', 'remove_item');
                formData.append('item_id', itemId);
                formData.append('delivery_number', deliveryNumber);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../Customer_API/update_order_item.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showToast(`"${productName}" removed from order`, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Failed to remove item', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
            } finally {
                hideLoading();
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function generateReceipt() {
            const deliveryNumber = document.getElementById('deliveryNumber').value;

            if (!deliveryNumber) {
                showToast('Delivery number not found', 'error');
                return;
            }

            // Open delivery receipt in new tab
            window.open('../delivery_receipt.php?delivery_number=' + encodeURIComponent(deliveryNumber), '_blank');
        }
    </script>
</body>

</html>