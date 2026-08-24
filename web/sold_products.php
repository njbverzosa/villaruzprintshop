<?php
// web/sold_products.php

session_start();

// ==============================================
// 1. FIX PATHS - config.php is in DB_Conn folder at root level
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 2. CHECK LOGIN STATUS
// ==============================================
function isLoggedIn() {
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


// Fetch where columns are NULL OR empty string (walk-in orders only)
$stmt = $pdo->prepare("
    SELECT * FROM order_status_history 
    WHERE (acc_number IS NULL OR acc_number = '')
      AND (delivery_address IS NULL OR delivery_address = '')
      AND (delivery_number IS NULL OR delivery_number = '')
    ORDER BY id DESC
");
$stmt->execute();
$orders = $stmt->fetchAll();

// Calculate statistics
$totalPaid = 0;
$totalPendingAmount = 0;
$totalCancelled = 0;
$pendingCount = 0;
$paidCount = 0;
$cancelledCount = 0;

foreach ($orders as $o) {
    $amount = floatval($o['total_amount']);
    $status = $o['status'];

    if ($status === 'PAID') {
        $totalPaid += $amount;
        $paidCount++;
    } elseif ($status === 'CANCELLED') {
        $totalCancelled += $amount;
        $cancelledCount++;
    } else {
        $totalPendingAmount += $amount;
        $pendingCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Sold Products | Villaruz Print Shop & General Merchandise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== MODERN LIGHT GRAY DASHBOARD STYLES ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Poppins', 'System UI', -apple-system, BlinkMacSystemFont, sans-serif;
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

        .welcome h4 {
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: #3b82f6;
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.1);
        }

        .stat-icon {
            font-size: 32px;
            background: linear-gradient(145deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #0f172a;
        }

        .stat-label {
            font-size: 14px;
            color: #64748b;
        }

        .merchandise-section {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow-x: auto;
            margin-top: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h5 {
            font-size: 22px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #0f172a;
        }

        .section-header h5 i {
            background: linear-gradient(145deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .info-banner {
            background: #e0f2fe;
            border-left: 4px solid #0284c7;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #0369a1;
        }

        .info-banner i {
            font-size: 20px;
        }

        .info-banner span {
            flex: 1;
            font-size: 14px;
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            white-space: nowrap;
        }

        .inventory-table th,
        .inventory-table td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            white-space: nowrap;
        }

        .inventory-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            position: sticky;
            top: 0;
            white-space: nowrap;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .inventory-table tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .status-paid {
            background: #10b981;
            color: #ffffff;
        }

        .status-pending {
            background: #f59e0b;
            color: #ffffff;
        }

        .status-cancelled {
            background: #ef4444;
            color: #ffffff;
        }

        .action-cell {
            white-space: nowrap;
        }

        .restore-btn {
            background: linear-gradient(145deg, #059669, #047857);
            border: none;
            border-radius: 20px;
            padding: 6px 16px;
            color: #ffffff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
            margin: 2px;
        }

        .restore-btn:hover {
            background: linear-gradient(145deg, #047857, #065f46);
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .status-select {
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            background: #f8fafc;
            transition: all 0.3s;
            margin-right: 8px;
        }

        .status-select:hover {
            border-color: #3b82f6;
        }

        .status-select.status-paid {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }

        .status-select.status-pending {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
        }

        .status-select.status-cancelled {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }

        /* ========== COMPUTER WARNING DIALOG STYLES ========== */
        .system-dialog-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: none;
            justify-content: center;
            align-items: center;
        }

        .system-dialog {
            background: #f0f0f0;
            border-radius: 8px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.28), 0 0 0 1px rgba(0, 0, 0, 0.05);
            width: 480px;
            max-width: 90%;
            animation: dialogSlideIn 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Segoe UI', 'System UI', -apple-system, sans-serif;
        }

        @keyframes dialogSlideIn {
            from {
                opacity: 0;
                transform: scale(0.96);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .dialog-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 8px 8px 0 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .dialog-header.warning {
            background: linear-gradient(135deg, #c2410c 0%, #9a3412 100%);
        }

        .dialog-header.error {
            background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%);
        }

        .dialog-header.info {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }

        .dialog-header.success {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        .dialog-header i {
            font-size: 18px;
        }

        .dialog-body {
            background: #ffffff;
            padding: 24px 20px;
            border-radius: 0 0 8px 8px;
        }

        .dialog-message {
            font-size: 14px;
            color: #1f2937;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .dialog-message strong {
            color: #0f172a;
            font-weight: 600;
        }

        .dialog-details {
            background: #f8fafc;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            font-family: 'Consolas', monospace;
            margin: 12px 0;
            border-left: 3px solid #3b82f6;
        }

        .dialog-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .dialog-btn {
            padding: 8px 20px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.15s;
            font-family: inherit;
        }

        .dialog-btn:hover {
            background: #f1f5f9;
            transform: translateY(-1px);
        }

        .dialog-btn-primary {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }

        .dialog-btn-primary:hover {
            background: #2563eb;
        }

        .dialog-btn-danger {
            background: #ef4444;
            border-color: #ef4444;
            color: white;
        }

        .dialog-btn-danger:hover {
            background: #dc2626;
        }

        .dialog-btn-warning {
            background: #f59e0b;
            border-color: #f59e0b;
            color: white;
        }

        .dialog-btn-warning:hover {
            background: #d97706;
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

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .inventory-table th,
            .inventory-table td {
                padding: 10px 8px;
                font-size: 12px;
                text-align: center;
            }

            .burger-btn {
                top: 15px;
                right: 15px;
                width: 42px;
                height: 42px;
            }

            .side-menu {
                width: 260px;
            }

            .system-dialog {
                width: 90%;
                margin: 20px;
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
                    <h4>SOLD PRODUCTS</h4>
                </div>
            </div>

            <div class="info-banner">
                <i class="fas fa-info-circle"></i>
                <span>Showing products without customer information (walk-in customers / counter sales). Orders from
                    today can be restored or cancelled.</span>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-value">₱ <?php echo number_format($totalPaid, 2); ?></div>
                    <div class="stat-label">Total Sales (Paid)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-value">₱ <?php echo number_format($totalPendingAmount, 2); ?></div>
                    <div class="stat-label">Pending Amount</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?php echo $paidCount; ?></div>
                    <div class="stat-label">Paid Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-value"><?php echo $cancelledCount; ?></div>
                    <div class="stat-label">Cancelled Orders</div>
                </div>
            </div>

            <div class="merchandise-section">
                <div class="section-header">
                    <h3><i class="fas fa-history"></i> Walk-in Orders</h3>
                </div>
                <div style="overflow-x: auto;">
                    <table class="inventory-table" id="ordersTable">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Total Amount</th>
                                <th>Date Sold</th>
                                <th>Note</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-shopping-bag"
                                            style="font-size: 48px; color: #94a3b8; margin-bottom: 15px; display: block;"></i>
                                        No walk-in orders found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    $orderDate = date('Y-m-d', strtotime($order['date_time_sold']));
                                    $today = date('Y-m-d');
                                    $isToday = ($orderDate === $today);
                                    $note = $order['note'] ?? '';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                        <td><?php echo $order['pieces']; ?></td>
                                        <td><?php echo htmlspecialchars($order['unit'] ?? 'Pcs'); ?></td>
                                        <td>₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($order['date_time_sold'])); ?></td>
                                        <td><?php echo htmlspecialchars($note); ?></td>
                                        <td>
                                            <span class="status-badge 
                                                <?php
                                                if ($order['status'] == 'PAID')
                                                    echo 'status-paid';
                                                elseif ($order['status'] == 'CANCELLED')
                                                    echo 'status-cancelled';
                                                else
                                                    echo 'status-pending';
                                                ?>
                                            ">
                                                <?php echo $order['status'] ?? 'PENDING'; ?>
                                            </span>
                                            </span>
                                        </td>
                                        <td class="action-cell">
                                            <?php if ($isToday): ?>
                                                <?php if ($order['status'] == 'PENDING' || empty($order['status'])): ?>
                                                    <select class="status-select" id="status-select-<?php echo $order['id']; ?>"
                                                        data-order-id="<?php echo $order['id']; ?>"
                                                        data-product-name="<?php echo htmlspecialchars($order['product_name']); ?>"
                                                        data-pieces="<?php echo $order['pieces']; ?>">
                                                        <option value="PENDING" <?php echo ($order['status'] == 'PENDING' || empty($order['status'])) ? 'selected' : ''; ?>>PENDING</option>
                                                        <option value="PAID" <?php echo ($order['status'] == 'PAID') ? 'selected' : ''; ?>>PAID</option>
                                                        <option value="CANCELLED">CANCELLED</option>
                                                    </select>
                                                <?php endif; ?>

                                                <?php if ($order['status'] == 'PAID'): ?>
                                                    <button class="restore-btn"
                                                        onclick="showRestoreDialog(<?php echo $order['id']; ?>, '<?php echo addslashes($order['product_name']); ?>', <?php echo $order['pieces']; ?>)">
                                                        <i class="fas fa-undo-alt"></i> RESTORE
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 12px;">No actions available</span>
                                            <?php endif; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- System Dialog Component -->
    <div id="systemDialog" class="system-dialog-overlay">
        <div class="system-dialog">
            <div class="dialog-header" id="dialogHeader">
                <i class="fas" id="dialogIcon"></i>
                <span id="dialogTitle">System Message</span>
            </div>
            <div class="dialog-body">
                <div class="dialog-message" id="dialogMessage"></div>
                <div class="dialog-details" id="dialogDetails" style="display: none;"></div>
                <div class="dialog-buttons" id="dialogButtons"></div>
            </div>
        </div>
    </div>

    <?php
        include '../footer.php';
    ?>

    <script>
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?php echo $_SESSION['csrf_token']; ?>';

        // ========== SYSTEM DIALOG ==========
        let pendingAction = null;
        let pendingParams = null;

        function showSystemDialog(options) {
            return new Promise((resolve) => {
                const overlay = document.getElementById('systemDialog');
                const header = document.getElementById('dialogHeader');
                const icon = document.getElementById('dialogIcon');
                const title = document.getElementById('dialogTitle');
                const messageDiv = document.getElementById('dialogMessage');
                const detailsDiv = document.getElementById('dialogDetails');
                const buttonsDiv = document.getElementById('dialogButtons');

                // Set header style and icon
                header.className = 'dialog-header';
                if (options.type === 'warning') {
                    header.classList.add('warning');
                    icon.className = 'fas fa-exclamation-triangle';
                    title.textContent = options.title || 'System Warning';
                } else if (options.type === 'error') {
                    header.classList.add('error');
                    icon.className = 'fas fa-times-circle';
                    title.textContent = options.title || 'System Error';
                } else if (options.type === 'success') {
                    header.classList.add('success');
                    icon.className = 'fas fa-check-circle';
                    title.textContent = options.title || 'Success';
                } else {
                    header.classList.add('info');
                    icon.className = 'fas fa-info-circle';
                    title.textContent = options.title || 'System Information';
                }

                // Set message
                messageDiv.innerHTML = options.message;

                // Set details if provided
                if (options.details) {
                    detailsDiv.style.display = 'block';
                    detailsDiv.innerHTML = options.details;
                } else {
                    detailsDiv.style.display = 'none';
                }

                // Create buttons
                buttonsDiv.innerHTML = '';
                if (options.buttons) {
                    options.buttons.forEach(btn => {
                        const button = document.createElement('button');
                        button.className = `dialog-btn ${btn.class || ''}`;
                        button.textContent = btn.label;
                        button.addEventListener('click', () => {
                            closeDialog();
                            resolve(btn.value);
                        });
                        buttonsDiv.appendChild(button);
                    });
                }

                overlay.style.display = 'flex';
                document.body.style.overflow = 'hidden';

                function closeDialog() {
                    overlay.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }

        // ========== BURGER MENU TOGGLE ==========
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

        if (burgerBtn) {
            burgerBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (sideMenu.classList.contains('open')) {
                    closeMenu();
                } else {
                    openMenu();
                }
            });
        }

        if (menuOverlay) {
            menuOverlay.addEventListener('click', closeMenu);
        }

        document.querySelectorAll('.side-menu .nav-item').forEach(link => {
            link.addEventListener('click', () => {
                closeMenu();
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

        // ========== TOAST ==========
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'}"></i> ${message}`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ========== NAVIGATION FUNCTION ==========
        function navigateToAllPorducts() {
            // Use window.location to navigate to sold_products.php
            window.location.href = 'all_products.php';
        }

        // ========== UPDATE STATUS WITH SYSTEM DIALOG ==========
        document.querySelectorAll('.status-select').forEach(select => {
            const updateSelectColor = (selectEl) => {
                const value = selectEl.value;
                selectEl.classList.remove('status-paid', 'status-pending', 'status-cancelled');
                if (value === 'PAID') {
                    selectEl.classList.add('status-paid');
                } else if (value === 'CANCELLED') {
                    selectEl.classList.add('status-cancelled');
                } else {
                    selectEl.classList.add('status-pending');
                }
            };

            updateSelectColor(select);

            select.addEventListener('change', async function () {
                const orderId = this.dataset.orderId;
                const productName = this.dataset.productName;
                const pieces = this.dataset.pieces;
                const newStatus = this.value;

                let dialogConfig = {
                    type: 'warning',
                    title: 'Confirm Status Change',
                    message: '',
                    buttons: [
                        { label: 'Cancel', value: false, class: '' },
                        { label: 'Confirm', value: true, class: 'dialog-btn-primary' }
                    ]
                };

                if (newStatus === 'CANCELLED') {
                    dialogConfig.message = `
                        <strong>⚠️ WARNING: This action cannot be undone!</strong><br><br>
                        You are about to <strong>CANCEL</strong> the order for:<br>
                        <strong>Product:</strong> ${productName}<br>
                        <strong>Quantity:</strong> ${pieces} piece(s)<br><br>
                        This will permanently delete this order record.
                    `;
                    dialogConfig.details = 'System Action: DELETE_ORDER_RECORD';
                } else if (newStatus === 'PAID') {
                    dialogConfig.message = `
                        <strong>💰 Confirm Payment</strong><br><br>
                        You are about to mark as <strong>PAID</strong>:<br>
                        <strong>Product:</strong> ${productName}<br>
                        <strong>Quantity:</strong> ${pieces} piece(s)<br><br>
                        This will deduct ${pieces} item(s) from inventory.
                    `;
                    dialogConfig.details = 'System Action: DEDUCT_INVENTORY';
                } else {
                    dialogConfig.message = `
                        You are about to change status to <strong>${newStatus}</strong> for:<br>
                        <strong>Product:</strong> ${productName}
                    `;
                }

                const confirmed = await showSystemDialog(dialogConfig);

                if (!confirmed) {
                    const originalStatus = this.querySelector('option[selected]')?.value || 'PENDING';
                    this.value = originalStatus;
                    updateSelectColor(this);
                    return;
                }

                this.disabled = true;

                try {
                    const formData = new FormData();
                    formData.append('action', 'update_status');
                    formData.append('order_id', orderId);
                    formData.append('status', newStatus);
                    formData.append('product_name', productName);
                    formData.append('pieces', pieces);
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch('../API/update_sold_products.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        await showSystemDialog({
                            type: 'success',
                            title: 'Status Updated',
                            message: data.message,
                            buttons: [{ label: 'OK', value: true, class: 'dialog-btn-primary' }]
                        });
                        // Navigate to sold products after successful sale
                        setTimeout(() => {
                            navigateToAllPorducts();
                        }, 1500); // 1.5 second delay to show the success toast
                    } else {
                        await showSystemDialog({
                            type: 'error',
                            title: 'Update Failed',
                            message: data.message || 'Failed to update status',
                            buttons: [{ label: 'OK', value: false, class: '' }]
                        });
                        const originalStatus = this.querySelector('option[selected]')?.value || 'PENDING';
                        this.value = originalStatus;
                        updateSelectColor(this);
                    }
                } catch (err) {
                    console.error('Error:', err);
                    await showSystemDialog({
                        type: 'error',
                        title: 'Network Error',
                        message: 'Network error. Please try again.',
                        buttons: [{ label: 'OK', value: false, class: '' }]
                    });
                    const originalStatus = this.querySelector('option[selected]')?.value || 'PENDING';
                    this.value = originalStatus;
                    updateSelectColor(this);
                } finally {
                    this.disabled = false;
                }
            });
        });

        // ========== RESTORE ORDER WITH SYSTEM DIALOG ==========
        window.showRestoreDialog = async function (orderId, productName, pieces) {
            const confirmed = await showSystemDialog({
                type: 'warning',
                title: 'Confirm Restore Action',
                message: `
            <strong>🔄 RESTORE ORDER TO INVENTORY</strong><br><br>
            You are about to restore:<br>
            <strong>Product:</strong> ${productName}<br>
            <strong>Quantity:</strong> ${pieces} piece(s)<br><br>
            <strong style="color: #dc2626;">⚠️ This action cannot be undone!</strong><br><br>
            This will:<br>
            ✅ Add ${pieces} item(s) back to inventory<br>
            ❌ Permanently delete this order record
        `,
                details: 'System Action: RESTORE_INVENTORY_AND_DELETE_ORDER',
                buttons: [
                    { label: 'Cancel', value: false, class: '' },
                    { label: 'Confirm Restore', value: true, class: 'dialog-btn-danger' }
                ]
            });

            if (!confirmed) return;

            // Find the restore button that was clicked
            const restoreBtn = document.querySelector(`.restore-btn[onclick*="showRestoreDialog(${orderId},"]`);
            if (!restoreBtn) return;

            const originalText = restoreBtn.innerHTML;
            restoreBtn.disabled = true;
            restoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Restoring...';

            try {
                const formData = new FormData();
                formData.append('action', 'restore_order');
                formData.append('order_id', orderId);
                formData.append('product_name', productName);
                formData.append('pieces', pieces);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/update_sold_products.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    await showSystemDialog({
                        type: 'success',
                        title: 'Order Restored',
                        message: data.message,
                        buttons: [{ label: 'OK', value: true, class: 'dialog-btn-primary' }]
                    });
                    location.reload();
                } else {
                    await showSystemDialog({
                        type: 'error',
                        title: 'Restore Failed',
                        message: data.message || 'Failed to restore order',
                        buttons: [{ label: 'OK', value: false, class: '' }]
                    });
                    restoreBtn.disabled = false;
                    restoreBtn.innerHTML = originalText;
                }
            } catch (err) {
                console.error('Error:', err);
                await showSystemDialog({
                    type: 'error',
                    title: 'Network Error',
                    message: 'Network error. Please try again.',
                    buttons: [{ label: 'OK', value: false, class: '' }]
                });
                restoreBtn.disabled = false;
                restoreBtn.innerHTML = originalText;
            }
        };

        // Make restoreOrder available globally for onclick
        window.showRestoreDialog = showRestoreDialog;
    </script>

    <style>
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 2001;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            font-size: 14px;
        }

        .toast-success {
            background: #10b981;
        }

        .toast-error {
            background: #ef4444;
        }

        @keyframes slideInRight {
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
    </style>
</body>

</html>