<?php
// web/pending_folder.php

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


// Fetch distinct delivery_y_m values from for_deliveries table where status is PENDING
$stmt = $pdo->prepare("SELECT delivery_m_y FROM for_deliveries WHERE delivery_m_y IS NOT NULL AND status = 'PENDING' ORDER BY id DESC");
$stmt->execute();
$distinctMonths = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Prepare the deliveries by month array
$deliveriesByMonth = [];

foreach ($distinctMonths as $monthYear) {
    // Split month and year for potential separate display
    $parts = explode(' ', $monthYear);
    $monthOnly = $parts[0] ?? $monthYear;
    $yearOnly = $parts[1] ?? '';

    $deliveriesByMonth[$monthYear] = [
        'deliveries' => [],
        'customers' => [],
        'month' => $monthOnly,
        'year' => $yearOnly,
        'raw_value' => $monthYear
    ];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Pending Folders | Villaruz Print Shop</title>
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
            font-size: 15px;
            font-weight: 600;
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

        .folders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 5px;
            margin-top: 20px;
        }

        .folder-item {
            border-radius: 5px;
            padding: 25px 20px;
            width: 190px;
            background: #ffffff;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .folder-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            background: whitesmoke;
        }

        .folder-icon i {
            font-size: 48px;
            color: #f59e0b;
            margin-bottom: 15px;
        }

        .folder-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
            word-break: break-word;
        }

        .customer-list {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            text-align: left;
        }

        .customer-name {
            font-size: 11px;
            color: #64748b;
            padding: 3px 5px;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .customer-name i {
            font-size: 10px;
            margin-right: 5px;
            color: #f59e0b;
        }

        .more-indicator {
            font-size: 10px;
            color: #f59e0b;
            padding: 3px 5px;
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }

        .empty-state i {
            font-size: 80px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 16px;
            color: #64748b;
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

            .burger-btn {
                top: 15px;
                right: 15px;
                width: 42px;
                height: 42px;
            }

            .side-menu {
                width: 260px;
            }

            .folders-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
            }

            .folder-icon {
                font-size: 60px;
            }

            .folder-name {
                font-size: 14px;
            }
        }
         @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }

            
            .dashboard-header {
                padding: 20px 30px;
                border-radius: 10px;
            }

            .welcome h1 {
                font-size: 18px;
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
                        <i class="fas fa-folder-open"></i> Pending Folders <i class="fas fa-chevron-right"></i>
                    </h4>
                </div>
            </div>

            <div class="folders-grid">
                <?php if (empty($deliveriesByMonth)): ?>

                <?php else: ?>
                    <?php foreach ($deliveriesByMonth as $monthYear => $data): ?>
                        <div class="folder-item" onclick="viewMonth('<?= htmlspecialchars($monthYear) ?>')">
                            <div class="folder-icon">
                                <i class="fas fa-folder"></i>
                            </div>
                            <div class="folder-name">
                                <?= htmlspecialchars($monthYear) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php
        include '../footer.php';
    ?>


    <script>
        // Burger menu functionality
        const burgerBtn = document.getElementById('burgerBtn');
        const menuOverlay = document.getElementById('menuOverlay');
        const sideMenu = document.querySelector('.side-menu');

        if (burgerBtn && menuOverlay && sideMenu) {
            burgerBtn.addEventListener('click', () => {
                sideMenu.classList.add('open');
                menuOverlay.classList.add('active');
            });

            menuOverlay.addEventListener('click', () => {
                sideMenu.classList.remove('open');
                menuOverlay.classList.remove('active');
            });
        }

        function viewMonth(monthYear) {
            window.location.href = 'pending_folder_with.php?month=' + encodeURIComponent(monthYear);
        }
    </script>
</body>

</html>