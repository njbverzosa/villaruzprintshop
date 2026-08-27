<?php
// public/account.php - Services Page

session_start();
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 1. CHECK LOGIN STATUS
// ==============================================
if (!isset($_SESSION['user_role']) || !isset($_SESSION['user_id']) || !isset($_SESSION['acc_number'])) {
    $_SESSION['login_error'] = 'Please login first.';
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 2. GET USER DATA
// ==============================================
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number FROM admins WHERE id = ?");
} else {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number FROM customers WHERE id = ?");
}
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 3. GET CART COUNT
// ==============================================
$cartStmt = $pdo->prepare("SELECT SUM(pieces) as total_items FROM cart WHERE acc_number = ?");
$cartStmt->execute([$accNumber]);
$cartTotalItems = intval($cartStmt->fetch(PDO::FETCH_ASSOC)['total_items'] ?? 0);

// ==============================================
// 4. GENERATE CSRF TOKEN
// ==============================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ==============================================
// 5. GET MESSAGES FROM SESSION
// ==============================================
$successMessage = $_SESSION['edit_success'] ?? '';
$errorMessage = $_SESSION['edit_error'] ?? '';
unset($_SESSION['edit_success'], $_SESSION['edit_error']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Services | Villaruz Print Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== RESET & BASE ========== */
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
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            padding: 20px;
            background: #f1f5f9;
        }

        /* ========== HEADER ========== */
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
            margin-right: 8px;
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

        /* ========== TOAST ========== */
        .toast-message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .toast-message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .toast-message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .toast-message.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .toast-message i {
            font-size: 18px;
            flex-shrink: 0;
        }

        .toast-message .toast-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 18px;
            margin-left: auto;
            opacity: 0.6;
            padding: 0 4px;
        }

        .toast-message .toast-close:hover {
            opacity: 1;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ========== SERVICES ========== */
        .services-section {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .service-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px 16px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            color: #1e293b;
        }

        .service-card:hover {
            border-color: #3b82f6;
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .service-card:active {
            transform: scale(0.97);
        }

        .service-card .service-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            flex-shrink: 0;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            transition: all 0.3s;
        }

        .service-card:hover .service-icon {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .service-card .service-name {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
        }

        .service-card .service-desc {
            font-size: 11px;
            color: #94a3b8;
            line-height: 1.3;
        }

        .service-card .service-arrow {
            color: #94a3b8;
            font-size: 12px;
            transition: all 0.3s;
        }

        .service-card:hover .service-arrow {
            color: #3b82f6;
            transform: translateX(3px);
        }

        /* ========== BOTTOM NAV ========== */
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
            transition: all 0.3s ease;
        }

        .bottom-nav .nav-item span {
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
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
            font-size: 14px;
            font-weight: bold;
            padding: 1px 6px;
            border-radius: 20px;
            min-width: 12px;
            text-align: center;
            line-height: 14px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .services-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 12px;
            }

            .service-card {
                padding: 16px 10px;
            }

            .service-card .service-icon {
                width: 48px;
                height: 48px;
                font-size: 20px;
            }

            .welcome h3 {
                font-size: 17px;
            }

            .dashboard-header {
                padding: 14px 18px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 12px;
            }

            body {
                padding-bottom: 60px;
            }

            .services-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .service-card {
                padding: 14px 10px;
            }

            .service-card .service-icon {
                width: 42px;
                height: 42px;
                font-size: 18px;
            }

            .service-card .service-name {
                font-size: 12px;
            }

            .service-card .service-desc {
                font-size: 10px;
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

            .dashboard-header {
                padding: 12px 14px;
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

            .toast-message {
                padding: 8px 12px;
                font-size: 12px;
                margin-bottom: 12px;
            }
        }

        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .bottom-nav {
                padding-bottom: calc(12px + env(safe-area-inset-bottom));
            }
        }
    </style>
</head>

<body>

    <main class="main-content">
        <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">

        <!-- Header -->
        <div class="dashboard-header">
            <div class="welcome">
                <h3><i class="fas fa-th-large"></i> Services</h3>
            </div>
            <div class="user-badge">
                <div class="avatar">
                    <?php echo strtoupper(substr($user['f_name'] ?? 'G', 0, 1)); ?>
                </div>
                <span class="name"><?php echo htmlspecialchars($user['f_name'] ?? 'Guest'); ?></span>
            </div>
        </div>

        <!-- Toast Messages -->
        <?php if ($successMessage): ?>
            <div class="toast-message success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($successMessage); ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="toast-message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($errorMessage); ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Services Grid -->
        <div class="services-section">
            <div class="services-grid">

                <!-- Account -->
                <a href="account-details.php" class="service-card">
                    <div class="service-icon"><i class="fas fa-user"></i></div>
                    <div class="service-name">Account</div>
                    <div class="service-desc">Manage your profile</div>
                    <div class="service-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>

                <!-- PayLater -->
                <a href="#" class="service-card" onclick="showComingSoon('PayLater')">
                    <div class="service-icon"><i class="fas fa-credit-card"></i></div>
                    <div class="service-name">PayLater</div>
                    <div class="service-desc">Get now, pay later</div>
                    <div class="service-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>



                <!-- Option 2: Clipboard with check -->
                <a href="delivered.php" class="service-card">
                    <div class="service-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="service-name">Received Orders</div>
                    <div class="service-desc">View and manage your received orders</div>
                    <div class="service-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>

                <!-- Earning Task -->
                <!-- <a href="#" class="service-card" onclick="showComingSoon('Earning Task')">
                    <div class="service-icon"><i class="fas fa-coins"></i></div>
                    <div class="service-name">Earning Task</div>
                    <div class="service-desc">Complete tasks, earn</div>
                    <div class="service-arrow"><i class="fas fa-chevron-right"></i></div>
                </a> -->

                <!-- Cancelled Order -->
                <a href="cancelled_orders.php" class="service-card">
                    <div class="service-icon"><i class="fas fa-window-close"></i></div>
                    <div class="service-name">Cancelled Orders</div>
                    <div class="service-desc">View and manage your cancelled orders</div>
                    <div class="service-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>

                <!-- Sell Product -->
                <a href="#" class="service-card" onclick="showComingSoon('Sell Product')">
                    <div class="service-icon"><i class="fas fa-store-alt"></i></div>
                    <div class="service-name">Sell Product</div>
                    <div class="service-desc">List your products</div>
                    <div class="service-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <!-- Legitimate -->
                <a href="#" class="service-card" onclick="showLegalities()">
                    <div class="service-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="service-name">Legitimate</div>
                    <div class="service-desc">Verified services</div>
                    <div class="service-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>

                <!-- Chat Us -->
                <a href="chat.php" class="service-card">
                    <div class="service-icon"><i class="fas fa-comment-dots"></i></div>
                    <div class="service-name">Chat Us</div>
                    <div class="service-desc">Talk to support</div>
                    <div class="service-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>

                <!-- Terms & Conditions -->
                <a href="PP_TAC.php" class="service-card">
                    <div class="service-icon"><i class="fas fa-file-contract"></i></div>
                    <div class="service-name">Terms & Conditions</div>
                    <div class="service-desc">Read our policies</div>
                    <div class="service-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>

            </div>
        </div>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="shop.php" class="nav-item">
            <i class="fas fa-store"></i>
            <span>Shop</span>
        </a>
        <a href="cart.php" class="nav-item">
            <i class="fas fa-shopping-cart"></i>
            <span>Cart</span>
            <?php if ($cartTotalItems > 0): ?>
                <span class="badge"><?php echo $cartTotalItems; ?></span>
            <?php endif; ?>
        </a>
        <a href="orders.php" class="nav-item">
            <i class="fas fa-truck"></i>
            <span>Orders</span>
        </a>
        <a href="account.php" class="nav-item active">
            <i class="fas fa-th-large"></i>
            <span>Services</span>
        </a>
        <a href="closed.php" class="nav-item" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <script>
        const csrfToken = document.getElementById('csrfToken').value;

        // Toast auto-dismiss
        document.addEventListener('DOMContentLoaded', function () {
            const toast = document.querySelector('.toast-message');
            if (toast) {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity 0.5s';
                    setTimeout(() => toast.remove(), 500);
                }, 5000);
            }
        });

        function showComingSoon(serviceName) {
            showToast(serviceName + ' service is coming soon! Stay tuned.', 'info');
        }

        function showLegalities() {
            showToast('Visit our shop at New Public Market, Dasol, Pangasinan for legitimate services.', 'info');
        }

        function showToast(message, type = 'success') {
            const existing = document.querySelector('.toast-message');
            if (existing) existing.remove();

            const toast = document.createElement('div');
            toast.className = 'toast-message ' + type;
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-info-circle';
            toast.innerHTML = `
                <i class="fas ${icon}"></i> 
                ${message}
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;

            const header = document.querySelector('.dashboard-header');
            header.parentNode.insertBefore(toast, header.nextSibling);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.5s';
                setTimeout(() => toast.remove(), 500);
            }, 5000);
        }

        console.log('👤 Services page loaded');
        console.log('📧 Account:', '<?php echo htmlspecialchars($accNumber); ?>');
    </script>

</body>

</html>