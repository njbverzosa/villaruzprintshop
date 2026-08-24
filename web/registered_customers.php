<?php
// paid_folder_with.php
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
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$user = $userData;

// Set timezone
date_default_timezone_set('Asia/Manila');
$currentDateTime = date('D, j M Y g:i A');

// Daily login bonus / update last login date
$storedDate = $user['last_login_date'] ?? '';
if ($storedDate !== $currentDateTime) {
    $updateStmt = $pdo->prepare("UPDATE admins SET last_login_date = ? WHERE acc_number = ?");
    $updateStmt->execute([$currentDateTime, $_SESSION['acc_number']]);

    // Refresh user data
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE acc_number = ?");
    $stmt->execute([$_SESSION['acc_number']]);
    $user = $stmt->fetch();
}

// Update status to online (1 = online, 0 = offline)
$stmt = $pdo->prepare("UPDATE admins SET status = 1 WHERE acc_number = ?");
$stmt->execute([$_SESSION['acc_number']]);

// Fetch all customers from customers table
$stmt = $pdo->prepare("SELECT * FROM customers ORDER BY id DESC");
$stmt->execute();
$customers = $stmt->fetchAll();

// Calculate statistics
$totalCustomers = count($customers);
$activeCustomers = 0;
$inactiveCustomers = 0;

foreach ($customers as $customer) {
    if (isset($customer['active_email']) && ($customer['active_email'] == 1 || $customer['active_email'] === null)) {
        $activeCustomers++;
    } else {
        $inactiveCustomers++;
    }
}

// ==============================================
// FUNCTION TO DETERMINE ONLINE STATUS - UPDATED THRESHOLDS
// ==============================================
function getOnlineStatus($onlineTime)
{
    if (empty($onlineTime)) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'label' => 'Offline'];
    }

    // Parse the stored time (format: g:i A)
    $storedTimestamp = strtotime($onlineTime);
    if ($storedTimestamp === false) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'label' => 'Offline'];
    }

    $currentTimestamp = time();
    $diffSeconds = $currentTimestamp - $storedTimestamp;
    $diffMinutes = floor($diffSeconds / 60); // Convert to minutes

    // Green: 1-2 minutes (Online - Recently Active)
    if ($diffMinutes >= 1 && $diffMinutes <= 2) {
        return ['status' => 'online', 'class' => 'status-online', 'text' => '● Online', 'label' => 'Active'];
    }
    // Yellow/Orange: 3-5 minutes (Away - Idle)
    elseif ($diffMinutes >= 3 && $diffMinutes <= 5) {
        return ['status' => 'away', 'class' => 'status-away', 'text' => '● Away', 'label' => 'Away'];
    }
    // Red: 6+ minutes (Offline)
    elseif ($diffMinutes >= 6) {
        return ['status' => 'offline', 'class' => 'status-offline', 'text' => '● Offline', 'label' => 'Offline'];
    }
    // If less than 1 minute, still consider as online (just logged in)
    else {
        return ['status' => 'online', 'class' => 'status-online', 'text' => '● Online', 'label' => 'Active'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Registered Customers | Villaruz Print Shop & General Merchandise</title>
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

        /* Main content wrapper (no sidebar) */
        .app-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Main content */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
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

        /* Stats Cards */
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
            --webkit-background-clip: text;
            --webkit-text-fill-color: transparent;
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

        /* Table Styles */
        .merchandise-section {
            background: #ffffff;
            border-radius: 10px;
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
            --webkit-background-clip: text;
            --webkit-text-fill-color: transparent;
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
        }

        .inventory-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            position: sticky;
            top: 0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .inventory-table tr:hover {
            background: #f8fafc;
        }

        .delete-btn {
            background: #ef4444;
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
        }

        .delete-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        .active-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            margin-left: 8px;
        }

        .badge-active {
            background: #10b981;
            color: white;
        }

        .badge-inactive {
            background: #ef4444;
            color: white;
        }

        /* ==============================================
           ONLINE STATUS INDICATORS - UPDATED COLORS
           ============================================== */

        /* Green - Online (1-2 minutes) */
        .status-online {
            color: #10b981;
            font-size: 24px;
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
            animation: pulse-green 2s infinite;
        }

        /* Orange/Yellow - Away (3-5 minutes) */
        .status-away {
            color: #f59e0b;
            font-size: 24px;
            animation: pulse-away 1.5s infinite;
        }

        /* Gray - Offline (6+ minutes) */
        .status-offline {
            color: #94a3b8;
            font-size: 20px;
            opacity: 0.5;
        }

        .status-text {
            font-size: 11px;
            font-weight: 500;
            display: block;
            margin-top: 2px;
        }

        .status-text.online {
            color: #10b981;
        }

        .status-text.away {
            color: #f59e0b;
        }

        .status-text.offline {
            color: #94a3b8;
        }

        @keyframes pulse-green {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.6;
                transform: scale(1.2);
            }
        }

        @keyframes pulse-away {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.5;
                transform: scale(1.1);
            }
        }

        /* Status column header */
        .status-header {
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-cell {
            text-align: center;
            min-width: 50px;
        }

        .status-dot-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* ============================================== */

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
        }
    </style>
</head>

<body>
    <div class="app-wrapper">
        <!-- Burger Button -->
        <div class="burger-btn" id="burgerBtn">
            <i class="fas fa-bars"></i>
        </div>

        <!-- Overlay -->
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
                    <h4>Customers</h4>
                </div>
            </div>

            <!-- <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo $totalCustomers; ?></div>
                    <div class="stat-label">Total Customers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-envelope-open-text"></i></div>
                    <div class="stat-value"><?php echo $activeCustomers; ?></div>
                    <div class="stat-label">Active Email</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                    <div class="stat-value"><?php echo $inactiveCustomers; ?></div>
                    <div class="stat-label">Inactive Email</div>
                </div>
            </div> -->

            <div class="merchandise-section">
                <div class="section-header">
                    <h5>(<?php echo $totalCustomers; ?>) Customer List</h5>
                </div>
                <div style="overflow-x: auto;">
                    <table class="inventory-table" id="customersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Phone Number</th>
                                <th>Email</th>
                                <th>Registered Date</th>
                                <th class="status-header">Status</th>
                                <th>Action</th>
                                <th>User / Pass</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">No customers found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer):
                                    $onlineStatus = getOnlineStatus($customer['online_time'] ?? '');
                                ?>
                                    <tr data-id="<?php echo $customer['id']; ?>"
                                        data-name="<?php echo strtolower(htmlspecialchars($customer['f_name'] ?? '')); ?>"
                                        data-phone="<?php echo htmlspecialchars($customer['phone_number'] ?? ''); ?>"
                                        data-email="<?php echo strtolower(htmlspecialchars($customer['email'] ?? '')); ?>">
                                        <td><?php echo $customer['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($customer['f_name'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($customer['phone_number'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?>
                                            <span class="active-badge <?php echo (isset($customer['active_email']) && ($customer['active_email'] == 1 || $customer['active_email'] === null)) ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php echo (isset($customer['active_email']) && ($customer['active_email'] == 1 || $customer['active_email'] === null)) ? 'ACTIVE' : 'INACTIVE'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($customer['registered_at'])); ?></td>
                                        <td class="status-cell">
                                            <div class="status-dot-wrapper">
                                                <?php if ($onlineStatus['status'] === 'online'): ?>
                                                    <i class="fas fa-circle status-online" title="Online - Recently Active"></i>
                                                    <span class="status-text online">Active</span>
                                                <?php elseif ($onlineStatus['status'] === 'away'): ?>
                                                    <i class="fas fa-circle status-away" title="Away - Idle for 3-5 min"></i>
                                                    <span class="status-text away">Away</span>
                                                <?php else: ?>
                                                    <i class="fas fa-circle status-offline" title="Offline - 6+ min inactive"></i>
                                                    <span class="status-text offline">Offline</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="delete-btn"
                                                onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo addslashes($customer['f_name'] ?? 'Customer'); ?>')">
                                                <i class="fas fa-trash-alt"></i> DELETE
                                            </button>
                                        </td>
                                        <td style="white-space: nowrap;">
                                            <?php echo htmlspecialchars($customer['acc_number']); ?> /
                                            <?php echo htmlspecialchars($customer['text_pass'] ?? ''); ?>
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

    <?php include '../footer.php'; ?>

    <script>
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?php echo $_SESSION['csrf_token']; ?>';

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

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

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

        // ========== DELETE CUSTOMER ==========
        async function deleteCustomer(customerId, customerName) {
            const confirmed = confirm(`⚠️ Are you sure you want to delete "${customerName}"?\n\nThis action will permanently remove this customer from the database and cannot be undone!`);
            if (!confirmed) return;

            const deleteBtn = event.target.closest('.delete-btn');
            const originalText = deleteBtn.innerHTML;
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

            try {
                const formData = new FormData();
                formData.append('action', 'delete_customer');
                formData.append('customer_id', customerId);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/customer_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Failed to delete customer', 'error');
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalText;
                }
            } catch (err) {
                console.error('Error:', err);
                showToast('Network error. Please try again.', 'error');
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalText;
            }
        }

        // ==============================================
        // AUTO-REFRESH STATUS EVERY 60 SECONDS
        // ==============================================
        function refreshOnlineStatus() {
            fetch(window.location.href, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newStatuses = doc.querySelectorAll('.status-cell');
                    const currentStatuses = document.querySelectorAll('.status-cell');

                    if (newStatuses.length === currentStatuses.length) {
                        currentStatuses.forEach((cell, index) => {
                            if (newStatuses[index]) {
                                cell.innerHTML = newStatuses[index].innerHTML;
                            }
                        });
                    }
                })
                .catch(error => console.error('Error refreshing status:', error));
        }

        // Refresh every 60 seconds (1 minute)
        setInterval(refreshOnlineStatus, 60000);

        console.log('👥 Registered Customers page loaded');
        console.log('🟢 Status indicators update every 60 seconds');
        console.log('📊 Status thresholds: 1-2 min = Green (Active), 3-5 min = Orange (Away), 6+ min = Gray (Offline)');
    </script>
</body>

</html>