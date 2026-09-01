<?php
// web/admin.php

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


$tables = [
    'for_deliveries' => 'For Deliveries',
    'cart' => 'Cart',
    'merchandise_inventory' => 'Merchandise Inventory',
    'customers' => 'Customers',
    'order_status_history' => 'Order Status History',
    'dtr' => 'Date Time Record'
];

// Handle reset ID request
$resetMessage = '';
$resetError = '';
$updateMessage = '';
$updateError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reset_ids' && isset($_POST['table_name'])) {
        $tableName = $_POST['table_name'];

        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            if ($stmt->rowCount() > 0) {
                $pdo->exec("SET @new_id = 0");
                $pdo->exec("UPDATE `$tableName` SET `id` = (@new_id := @new_id + 1) ORDER BY `id` ASC");
                $pdo->exec("ALTER TABLE `$tableName` AUTO_INCREMENT = 1");
                $resetMessage = "IDs in '$tableName' table have been reset successfully!";
            } else {
                $resetError = "Table '$tableName' does not exist!";
            }
        } catch (Exception $e) {
            $resetError = "Error resetting IDs: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'download_table' && isset($_POST['table_name'])) {
        $tableName = $_POST['table_name'];

        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            if ($stmt->rowCount() > 0) {
                $structureStmt = $pdo->query("SHOW CREATE TABLE `$tableName`");
                $structure = $structureStmt->fetch(PDO::FETCH_ASSOC);
                $data = $pdo->query("SELECT * FROM `$tableName`")->fetchAll(PDO::FETCH_ASSOC);

                $sql = "-- --------------------------------------------------------\n";
                $sql .= "-- Table structure for `$tableName`\n";
                $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
                $sql .= "-- --------------------------------------------------------\n\n";
                $sql .= "DROP TABLE IF EXISTS `$tableName`;\n\n";
                $sql .= $structure['Create Table'] . ";\n\n";

                if (!empty($data)) {
                    $sql .= "-- --------------------------------------------------------\n";
                    $sql .= "-- Data for table `$tableName`\n";
                    $sql .= "-- --------------------------------------------------------\n\n";

                    foreach ($data as $row) {
                        $columns = array_keys($row);
                        $values = array_map(function ($value) use ($pdo) {
                            if ($value === null) {
                                return 'NULL';
                            }
                            return $pdo->quote($value);
                        }, array_values($row));

                        $sql .= "INSERT INTO `$tableName` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                    }
                }

                $sql .= "\n-- --------------------------------------------------------\n";
                $sql .= "-- End of backup for `$tableName`\n";
                $sql .= "-- --------------------------------------------------------\n";

                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . $tableName . '_' . date('Y-m-d_H-i-s') . '.sql"');
                echo $sql;
                exit();
            } else {
                $resetError = "Table '$tableName' does not exist!";
            }
        } catch (Exception $e) {
            $resetError = "Error downloading table: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'update_table' && isset($_POST['table_name']) && isset($_POST['sql_query'])) {
        $tableName = $_POST['table_name'];
        $sqlQuery = trim($_POST['sql_query']);

        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            if ($stmt->rowCount() > 0) {
                // Execute the custom SQL query
                $pdo->exec($sqlQuery);
                $updateMessage = "Table '$tableName' has been updated successfully!";
            } else {
                $updateError = "Table '$tableName' does not exist!";
            }
        } catch (Exception $e) {
            $updateError = "Error updating table: " . $e->getMessage();
        }
    }
}

// Fetch table statistics and sample data
$tableStats = [];
$tableColumns = [];
foreach ($tables as $table => $displayName) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    if ($stmt->rowCount() > 0) {
        $count = $pdo->query("SELECT COUNT(*) as count FROM `$table`")->fetch(PDO::FETCH_ASSOC);
        $tableStats[$table] = $count['count'];

        // Get column names
        $columns = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_COLUMN);
        $tableColumns[$table] = $columns;
    } else {
        $tableStats[$table] = 0;
        $tableColumns[$table] = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Admin Settings | Villaruz Print Shop</title>
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

        /* Settings Container */
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .settings-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .card-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header-left i {
            font-size: 28px;
            color: #3b82f6;
        }

        .card-header-left h3 {
            font-size: 20px;
            font-weight: 600;
            color: #0f172a;
        }

        .card-body {
            padding: 25px;
        }

        .stats-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 15px;
        }

        .stat-info {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .stat-item {
            background: #f8fafc;
            padding: 10px 15px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        .stat-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #3b82f6;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .warning-box i {
            color: #f59e0b;
            margin-right: 8px;
        }

        .warning-box p {
            color: #78350f;
            font-size: 12px;
            margin-top: 5px;
        }

        .update-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .update-section h4 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sql-editor {
            width: 100%;
            min-height: 200px;
            padding: 15px;
            background: #1e293b;
            color: #e2e8f0;
            border: 1px solid #475569;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            resize: vertical;
            margin-bottom: 15px;
        }

        .sql-editor:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .query-examples {
            background: #f8fafc;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 12px;
        }

        .query-examples pre {
            background: #e2e8f0;
            padding: 8px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: monospace;
            font-size: 11px;
            margin-top: 5px;
        }

        .message-box {
            margin-top: 15px;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
        }

        .message-success {
            background: #d1fae5;
            color: #059669;
        }

        .message-error {
            background: #fee2e2;
            color: #dc2626;
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

            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .stat-info {
                gap: 15px;
            }

            .btn-group {
                width: 100%;
            }

            .btn {
                flex: 1;
                justify-content: center;
            }

            .sql-editor {
                min-height: 150px;
                font-size: 11px;
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
                    <h4>SQL CMD</h4>
                </div>
            </div>

            <div class="settings-container">
                <?php foreach ($tables as $table => $displayName): ?>
                    <div class="settings-card">
                        <div class="card-header">
                            <div class="card-header-left">
                                <i class="fas fa-table"></i>
                                <h3><?php echo htmlspecialchars($displayName); ?></h3>
                            </div>
                            <div class="stat-info">
                                <div class="stat-item">
                                    <div class="stat-label">Total Records</div>
                                    <div class="stat-value"><?php echo number_format($tableStats[$table]); ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Table Name</div>
                                    <div class="stat-value"><?php echo htmlspecialchars($table); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="warning-box">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Warning!</strong>
                                <p>Resetting IDs will renumber all IDs in this table starting from 1. This action cannot be
                                    undone.</p>
                            </div>

                            <div class="stats-row">
                                <div class="btn-group">
                                    <form method="POST" onsubmit="return confirmReset('<?php echo $displayName; ?>')"
                                        style="display: inline;">
                                        <input type="hidden" name="action" value="reset_ids">
                                        <input type="hidden" name="table_name" value="<?php echo $table; ?>">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-sync-alt"></i> Reset IDs
                                        </button>
                                    </form>

                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="download_table">
                                        <input type="hidden" name="table_name" value="<?php echo $table; ?>">
                                        <input type="hidden" name="csrf_token"
                                            value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-database"></i> Download as SQL
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Update Table Section -->
                            <div class="update-section">
                                <h4><i class="fas fa-edit"></i> Update Table Data</h4>

                                <div class="query-examples">
                                    <strong><i class="fas fa-code"></i> Example Queries:</strong>
                                    <pre>-- Update specific record
                UPDATE `<?php echo $table; ?>` SET `column_name` = 'new_value' WHERE `id` = 1;

                -- Update multiple records
                UPDATE `<?php echo $table; ?>` SET `status` = 'PAID' WHERE `status` = 'PENDING';

                -- Update selected multiple records
                UPDATE `<?php echo $table; ?>` SET `delivery_number` = '' WHERE `status` = 'PAID';

                -- Delete records (use with caution!)
                DELETE FROM `<?php echo $table; ?>` WHERE `id` = 1;

                -- Add new column
                ALTER TABLE `<?php echo $table; ?>` ADD COLUMN `new_column` VARCHAR(255) DEFAULT NULL;

                -- Modify column
                ALTER TABLE `<?php echo $table; ?>` MODIFY COLUMN `column_name` TEXT;</pre>
                                </div>

                                <div class="warning-box" style="margin-bottom: 15px;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Caution!</strong>
                                    <p>Running UPDATE or DELETE queries will permanently modify your data. Make sure to
                                        backup before running!</p>
                                </div>

                                <form method="POST" onsubmit="return confirmUpdate('<?php echo $displayName; ?>')">
                                    <input type="hidden" name="action" value="update_table">
                                    <input type="hidden" name="table_name" value="<?php echo $table; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                    <textarea name="sql_query" class="sql-editor"
                                        placeholder="Enter your SQL query here...&#10;&#10;Examples:&#10;UPDATE `<?php echo $table; ?>` SET `status` = 'PAID' WHERE `id` = 1;&#10;DELETE FROM `<?php echo $table; ?>` WHERE `id` = 5;&#10;ALTER TABLE `<?php echo $table; ?>` ADD COLUMN `notes` TEXT;"></textarea>

                                    <div class="btn-group">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-play"></i> Execute Query
                                        </button>
                                    </div>
                                </form>

                                <?php if ($updateMessage && isset($_POST['table_name']) && $_POST['table_name'] == $table): ?>
                                    <div class="message-box message-success" style="margin-top: 15px;">
                                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($updateMessage); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($updateError && isset($_POST['table_name']) && $_POST['table_name'] == $table): ?>
                                    <div class="message-box message-error" style="margin-top: 15px;">
                                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($updateError); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Manual SQL for this table -->
                            <details style="margin-top: 15px;">
                                <summary style="cursor: pointer; color: #3b82f6; font-size: 13px;">
                                    <i class="fas fa-code"></i> Show Table Columns
                                </summary>
                                <div
                                    style="background: #f8fafc; padding: 15px; border-radius: 12px; overflow-x: auto; margin-top: 10px;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                        <thead>
                                            <tr style="background: #e2e8f0;">
                                                <th style="padding: 8px; text-align: left;">Column Name</th>
                                                <th style="padding: 8px; text-align: left;">Type</th>
                                                <th style="padding: 8px; text-align: left;">Null</th>
                                                <th style="padding: 8px; text-align: left;">Key</th>
                                                <th style="padding: 8px; text-align: left;">Default</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $columns = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($columns as $column):
                                                ?>
                                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                                    <td style="padding: 6px 8px;"><code><?php echo $column['Field']; ?></code>
                                                    </td>
                                                    <td style="padding: 6px 8px;"><?php echo $column['Type']; ?></td>
                                                    <td style="padding: 6px 8px;"><?php echo $column['Null']; ?></td>
                                                    <td style="padding: 6px 8px;"><?php echo $column['Key']; ?></td>
                                                    <td style="padding: 6px 8px;"><?php echo $column['Default'] ?? 'NULL'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($resetMessage && !isset($_POST['table_name'])): ?>
                <div class="message-box message-success" style="margin-top: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($resetMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($resetError && !isset($_POST['table_name'])): ?>
                <div class="message-box message-error" style="margin-top: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($resetError); ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <?php
    include '../footer.php';
    ?>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Burger Menu Toggle
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

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

        function confirmReset(tableName) {
            return confirm(`⚠️ WARNING: This will reset all ID sequences in the "${tableName}" table!\n\nThis will renumber all IDs starting from 1.\n\nThis action cannot be undone.\n\nMake sure you have a backup before proceeding.\n\nAre you absolutely sure?`);
        }

        function confirmUpdate(tableName) {
            const sqlQuery = document.activeElement.closest('form').querySelector('.sql-editor').value;
            if (!sqlQuery.trim()) {
                alert('Please enter an SQL query to execute.');
                return false;
            }
            return confirm(`⚠️ CAUTION: You are about to execute a query on the "${tableName}" table!\n\nQuery: ${sqlQuery.substring(0, 200)}${sqlQuery.length > 200 ? '...' : ''}\n\nThis action will modify your database.\n\nMake sure you have a backup before proceeding.\n\nAre you sure you want to continue?`);
        }
    </script>
</body>

</html>