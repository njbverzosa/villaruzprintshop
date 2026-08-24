<?php
// web/chat.php - Chat Centre (Admin View)
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

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ==============================================
// FETCH ALL CHAT ACCOUNTS AND CONVERSATIONS
// ==============================================

// Get all chat accounts with their latest message and unread count
$stmt = $pdo->prepare("
    SELECT 
        ca.id as account_id,
        ca.acc_number,
        ca.chat_sent,
        ca.status as account_status,
        COUNT(DISTINCT cc.id) as total_messages,
        (SELECT COUNT(*) FROM chat_conversation WHERE receiver_acc = ca.acc_number AND status = 0) as unread_count,
        (SELECT message FROM chat_conversation WHERE acc_number = ca.acc_number OR receiver_acc = ca.acc_number ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') FROM chat_conversation WHERE acc_number = ca.acc_number OR receiver_acc = ca.acc_number ORDER BY created_at DESC LIMIT 1) as last_message_time,
        (SELECT sender_type FROM chat_conversation WHERE acc_number = ca.acc_number OR receiver_acc = ca.acc_number ORDER BY created_at DESC LIMIT 1) as last_sender
    FROM chat_account ca
    LEFT JOIN chat_conversation cc ON ca.acc_number = cc.acc_number OR ca.acc_number = cc.receiver_acc
    GROUP BY ca.id
    ORDER BY ca.chat_sent DESC
");
$stmt->execute();
$chatAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total stats
$totalAccounts = count($chatAccounts);
$blockedAccounts = 0;
$activeAccounts = 0;
$totalUnread = 0;

foreach ($chatAccounts as $account) {
    if ($account['account_status'] == 0) {
        $blockedAccounts++;
    } else {
        $activeAccounts++;
    }
    $totalUnread += intval($account['unread_count']);
}

// Handle AJAX request for blocking/unblocking users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_block') {
        $targetAcc = $_POST['target_acc'] ?? '';
        $blockStatus = intval($_POST['block_status'] ?? 0);

        if (empty($targetAcc)) {
            echo json_encode(['success' => false, 'message' => 'Target account required']);
            exit();
        }

        try {
            $updateStmt = $pdo->prepare("UPDATE chat_account SET status = ? WHERE acc_number = ?");
            $updateStmt->execute([$blockStatus, $targetAcc]);

            echo json_encode([
                'success' => true,
                'message' => $blockStatus == 0 ? 'User blocked successfully' : 'User unblocked successfully',
                'status' => $blockStatus
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Chat Centre | Villaruz Print Shop</title>
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


        .user-info {
            font-size: 13px;
            color: #64748b;
        }

        .user-info i {
            color: #3b82f6;
        }

        /* ========== BURGER BUTTON ========== */
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

        /* ========== SIDE MENU ========== */
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

        /* ========== STATS CARDS - 2 COLUMN GRID, 10px RADIUS ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 20px;
            transition: all 0.3s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: #3b82f6;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.1);
        }

        .stat-icon {
            font-size: 28px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            flex-shrink: 0;
        }

        .stat-icon.blue {
            background: #dbeafe;
            color: #3b82f6;
        }

        .stat-icon.green {
            background: #d1fae5;
            color: #10b981;
        }

        .stat-icon.red {
            background: #fee2e2;
            color: #ef4444;
        }

        .stat-icon.yellow {
            background: #fef3c7;
            color: #f59e0b;
        }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 13px;
            color: #64748b;
        }

        /* ========== TABLE STYLES ========== */
        .merchandise-section {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            overflow-x: auto;
            margin-top: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .section-header h5 {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #0f172a;
            white-space: nowrap;
        }

        .section-header h5 i {
            color: #3b82f6;
        }

        .section-header .count-badge {
            font-size: 12px;
            color: #94a3b8;
            background: #f1f5f9;
            padding: 2px 12px;
            border-radius: 10px;
            white-space: nowrap;
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .inventory-table th,
        .inventory-table td {
            padding: 12px 14px;
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
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .inventory-table tr:hover {
            background: #f8fafc;
        }

        .inventory-table .clickable-row {
            cursor: pointer;
            transition: background 0.2s;
        }

        .inventory-table .clickable-row:hover {
            background: #eff6ff;
        }

        /* ========== BADGES - 5px RADIUS ========== */
        .badge-status {
            padding: 3px 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-blocked {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-unread {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-none {
            background: #f1f5f9;
            color: #64748b;
        }

        .badge-customer {
            background: #dbeafe;
            color: #1e40af;
            border-radius: 5px;
        }

        .badge-admin {
            background: #fef3c7;
            color: #92400e;
            border-radius: 5px;
        }

        /* ========== TOGGLE BUTTON ========== */
        .toggle-btn {
            padding: 4px 12px;
            border: none;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .toggle-btn.block {
            background: #fee2e2;
            color: #991b1b;
        }

        .toggle-btn.block:hover {
            background: #fecaca;
        }

        .toggle-btn.unblock {
            background: #d1fae5;
            color: #065f46;
        }

        .toggle-btn.unblock:hover {
            background: #a7f3d0;
        }

        .toggle-btn.loading {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .dashboard-header {
                padding: 12px 16px;
                border-radius: 10px;
            }

            .welcome h4 {
                font-size: 14px;
            }

            .user-info {
                font-size: 12px;
            }

            .stats-grid {
                gap: 10px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 12px 14px;
                border-radius: 10px;
            }

            .stat-icon {
                font-size: 22px;
                width: 40px;
                height: 40px;
                border-radius: 10px;
            }

            .stat-value {
                font-size: 18px;
            }

            .stat-label {
                font-size: 12px;
            }

            .inventory-table th,
            .inventory-table td {
                padding: 8px 10px;
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

            .section-header {
                padding: 12px 15px;
            }

            .section-header h5 {
                font-size: 14px;
            }

            .merchandise-section {
                border-radius: 10px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }

            .dashboard-header {
                padding: 10px 12px;
                border-radius: 10px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .stat-card {
                padding: 10px 12px;
                border-radius: 10px;
            }

            .stat-icon {
                font-size: 18px;
                width: 32px;
                height: 32px;
                border-radius: 10px;
            }

            .stat-value {
                font-size: 16px;
            }

            .stat-label {
                font-size: 11px;
            }

            .inventory-table {
                font-size: 11px;
            }

            .inventory-table th,
            .inventory-table td {
                padding: 6px 8px;
            }

            .inventory-table th {
                font-size: 10px;
            }

            .badge-status {
                font-size: 10px;
                padding: 2px 8px;
            }

            .toggle-btn {
                font-size: 10px;
                padding: 3px 10px;
            }

            .section-header h5 {
                font-size: 13px;
            }

            .merchandise-section {
                border-radius: 10px;
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
                    <h4>Live Chat Support</h4>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $totalAccounts; ?></div>
                        <div class="stat-label">Total Conversations</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-comment"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $totalUnread; ?></div>
                        <div class="stat-label">Unread Messages</div>
                    </div>
                </div>
            </div>

            <!-- Chat Accounts Table -->
            <div class="merchandise-section">
                <div class="section-header">
                    <h5><i class="fas fa-comment"></i> Chat Conversations</h5>
                    <span class="count-badge">
                        <?php echo $totalAccounts; ?> conversation(s)
                    </span>
                </div>
                <div style="overflow-x: auto;">
                    <table class="inventory-table" id="chatTable">
                        <thead>
                            <tr>
                                <th>Account #</th>
                                <th>Last Active</th>
                                <th>Messages</th>
                                <th>Unread</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($chatAccounts)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <i class="fas fa-comment-slash" style="font-size: 32px; display: block; margin-bottom: 10px;"></i>
                                        No chat conversations yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($chatAccounts as $account):
                                    $lastSender = $account['last_sender'] ?? '';
                                    $lastSenderLabel = $lastSender == 'admin' ? 'You' : 'Customer';
                                    $lastSenderClass = $lastSender == 'admin' ? 'badge-admin' : 'badge-customer';
                                ?>
                                    <tr class="clickable-row"
                                        onclick="window.location.href='chat_view.php?acc=<?php echo urlencode($account['acc_number']); ?>'"
                                        data-acc="<?php echo htmlspecialchars($account['acc_number']); ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($account['acc_number']); ?></strong>
                                        </td>
                                       
                                        <td>
                                            <?php if ($account['last_message_time']): ?>
                                                <?php echo htmlspecialchars($account['last_message_time']); ?>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $account['total_messages']; ?></td>
                                        <td>
                                            <?php if ($account['unread_count'] > 0): ?>
                                                <span class="badge-status badge-unread">
                                                    <?php echo $account['unread_count']; ?> unread
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">0</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <button class="toggle-btn <?php echo ($account['account_status'] == 1) ? 'block' : 'unblock'; ?>"
                                                onclick="event.stopPropagation(); toggleBlock('<?php echo htmlspecialchars($account['acc_number']); ?>', <?php echo ($account['account_status'] == 1) ? '0' : '1'; ?>, this)">
                                                <?php echo ($account['account_status'] == 1) ? 'Block' : 'Unblock'; ?>
                                            </button>
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
        // ==============================================
        // CSRF TOKEN
        // ==============================================
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?php echo $csrfToken; ?>';

        // ==============================================
        // BURGER MENU
        // ==============================================
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
            link.addEventListener('click', closeMenu);
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeMenu();
        });

        // ==============================================
        // TOGGLE BLOCK/UNBLOCK
        // ==============================================
        async function toggleBlock(accNumber, blockStatus, button) {
            // Add loading class
            button.classList.add('loading');
            button.textContent = 'Loading...';

            try {
                const formData = new FormData();
                formData.append('action', 'toggle_block');
                formData.append('target_acc', accNumber);
                formData.append('block_status', blockStatus);
                formData.append('csrf_token', csrfToken);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Update button and status badge
                    const row = button.closest('tr');
                    const statusBadge = row.querySelector('.badge-status:not(.badge-unread):not(.badge-customer):not(.badge-admin)');

                    if (blockStatus === 0) {
                        // User blocked
                        button.textContent = 'Unblock';
                        button.className = 'toggle-btn unblock';
                        button.onclick = function(e) {
                            e.stopPropagation();
                            toggleBlock(accNumber, 1, this);
                        };
                        if (statusBadge) {
                            statusBadge.textContent = 'Blocked';
                            statusBadge.className = 'badge-status badge-blocked';
                        }
                    } else {
                        // User unblocked
                        button.textContent = 'Block';
                        button.className = 'toggle-btn block';
                        button.onclick = function(e) {
                            e.stopPropagation();
                            toggleBlock(accNumber, 0, this);
                        };
                        if (statusBadge) {
                            statusBadge.textContent = 'Active';
                            statusBadge.className = 'badge-status badge-active';
                        }
                    }
                }
            } catch (err) {
                console.error('Error:', err);
            } finally {
                button.classList.remove('loading');
                // Restore button text if not changed
                if (!button.textContent.includes('Unblock') && !button.textContent.includes('Block')) {
                    button.textContent = blockStatus === 0 ? 'Block' : 'Unblock';
                }
            }
        }

        // ==============================================
        // REFRESH TABLE (polling)
        // ==============================================
        function refreshChatTable() {
            fetch(window.location.href, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTable = doc.querySelector('#chatTable tbody');
                    const currentTable = document.querySelector('#chatTable tbody');

                    if (newTable && currentTable) {
                        const newHtml = newTable.innerHTML;
                        const currentHtml = currentTable.innerHTML;
                        if (newHtml !== currentHtml) {
                            currentTable.innerHTML = newHtml;
                        }
                    }
                })
                .catch(error => console.error('Error refreshing table:', error));
        }

        // Refresh every 30 seconds
        setInterval(refreshChatTable, 30000);

        console.log('💬 Chat Centre loaded');
        console.log('📊 Total accounts:', <?php echo $totalAccounts; ?>);
        console.log('📨 Total unread:', <?php echo $totalUnread; ?>);
    </script>
</body>

</html>