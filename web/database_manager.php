<?php
// web/database_manager.php

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

// Get all tables in the database
$stmt = $pdo->query("SHOW TABLES");
$allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Filter to show only relevant tables
$displayTables = ['cart', 'for_deliveries', 'merchandise_inventory', 'order_status_history', 'customers', 'admins', 'location', 'logs', 'dtr'];

// Get selected table from URL parameter
$selectedTable = isset($_GET['table']) ? $_GET['table'] : ($displayTables[0] ?? '');
$tableData = [];
$tableColumns = [];
$tableCount = 0;
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');

    if (isset($_POST['action']) && $_POST['action'] === 'update_cell') {
        $tableName = $_POST['table_name'];
        $rowId = intval($_POST['row_id']);
        $column = $_POST['column'];
        $value = $_POST['value'];

        try {
            if (!in_array($tableName, $displayTables)) {
                echo json_encode(['success' => false, 'message' => 'Invalid table name']);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE `$tableName` SET `$column` = :value WHERE id = :id");
            $stmt->execute([':value' => $value === '' ? null : $value, ':id' => $rowId]);

            echo json_encode(['success' => true, 'message' => 'Cell updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'add_row') {
        $tableName = $_POST['table_name'];

        try {
            if (!in_array($tableName, $displayTables)) {
                echo json_encode(['success' => false, 'message' => 'Invalid table name']);
                exit();
            }

            $columns = $pdo->query("DESCRIBE `$tableName`")->fetchAll(PDO::FETCH_ASSOC);
            $insertColumns = [];
            $insertValues = [];

            foreach ($columns as $column) {
                $field = $column['Field'];
                if ($field !== 'id') {
                    $insertColumns[] = "`$field`";
                    $insertValues[] = ":{$field}";
                }
            }

            $sql = "INSERT INTO `$tableName` (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
            $stmt = $pdo->prepare($sql);

            $params = [];
            foreach ($columns as $column) {
                $field = $column['Field'];
                if ($field !== 'id') {
                    $params[":{$field}"] = null;
                }
            }
            $stmt->execute($params);
            $newId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("SELECT * FROM `$tableName` WHERE id = ?");
            $stmt->execute([$newId]);
            $newRow = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'message' => 'New row added successfully', 'row' => $newRow, 'row_id' => $newId]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_row') {
        $tableName = $_POST['table_name'];
        $rowId = intval($_POST['row_id']);

        try {
            if (!in_array($tableName, $displayTables)) {
                echo json_encode(['success' => false, 'message' => 'Invalid table name']);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM `$tableName` WHERE id = :id");
            $stmt->execute([':id' => $rowId]);

            echo json_encode(['success' => true, 'message' => 'Row deleted successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
}

if ($selectedTable && in_array($selectedTable, $displayTables)) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$selectedTable]);
    if ($stmt->rowCount() > 0) {
        $columns = $pdo->query("DESCRIBE `$selectedTable`")->fetchAll(PDO::FETCH_ASSOC);
        $tableColumns = $columns;

        if (!empty($searchTerm)) {
            $searchConditions = [];
            foreach ($columns as $column) {
                $field = $column['Field'];
                $type = $column['Type'];
                if (strpos($type, 'varchar') !== false || strpos($type, 'text') !== false || strpos($type, 'char') !== false) {
                    $searchConditions[] = "`$field` LIKE :search";
                }
            }

            if (!empty($searchConditions)) {
                $searchSql = "SELECT * FROM `$selectedTable` WHERE " . implode(' OR ', $searchConditions) . " ORDER BY id DESC";
                $stmt = $pdo->prepare($searchSql);
                $stmt->execute([':search' => "%$searchTerm%"]);
            } else {
                $stmt = $pdo->query("SELECT * FROM `$selectedTable` ORDER BY id DESC");
            }
        } else {
            $stmt = $pdo->query("SELECT * FROM `$selectedTable` ORDER BY id DESC");
        }

        $tableData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tableCount = count($tableData);
    }
}

$tableCounts = [];
foreach ($displayTables as $table) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    if ($stmt->rowCount() > 0) {
        $count = $pdo->query("SELECT COUNT(*) as count FROM `$table`")->fetch(PDO::FETCH_ASSOC);
        $tableCounts[$table] = $count['count'];
    } else {
        $tableCounts[$table] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Database Manager | Villaruz Print Shop</title>
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
            height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 80px);
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
            flex-shrink: 0;
        }

        .welcome h4 {
            font-size: 22px;
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

        .database-layout {
            display: flex;
            gap: 20px;
            flex: 1;
            min-height: 0;
        }

        .table-sidebar {
            width: 260px;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 15px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: #0f172a;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .sidebar-header i {
            color: #3b82f6;
            margin-right: 8px;
        }

        .sidebar-header .download-all-btn {
            background: #ffffff;
            color: #3b82f6;
            border: 1px solid #e2e8f0;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .sidebar-header .download-all-btn:hover {
            background: #f1f5f9;
            border-color: #3b82f6;
            transform: scale(1.05);
        }

        .table-list {
            list-style: none;
            flex: 1;
            overflow-y: auto;
        }

        .table-list li {
            border-bottom: 1px solid #e2e8f0;
        }

        .table-list li a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            text-decoration: none;
            color: #475569;
            transition: all 0.2s;
        }

        .table-list li a:hover {
            background: #eff6ff;
            color: #3b82f6;
        }

        .table-list li.active a {
            background: #eff6ff;
            color: #3b82f6;
            border-left: 3px solid #3b82f6;
        }

        .table-name {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-name i {
            width: 20px;
            font-size: 14px;
        }

        .table-count {
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .table-content {
            flex: 1;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .table-info-bar {
            padding: 15px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            flex-shrink: 0;
        }

        .table-title {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .table-title h3 {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
        }

        .table-title .badge {
            background: #dbeafe;
            color: #3b82f6;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .search-box {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .search-box input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            width: 250px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .search-box button {
            padding: 8px 12px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .search-box button:hover {
            background: #2563eb;
        }

        .clear-search {
            background: #e2e8f0;
            color: #475569;
        }

        .clear-search:hover {
            background: #cbd5e1;
        }

        .table-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #e2e8f0;
            color: #475569;
        }

        .btn-outline:hover {
            background: #f1f5f9;
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* TABLE WRAPPER - ONLY THIS IS SCROLLABLE */
        .table-wrapper {
            flex: 1;
            overflow: auto;
            position: relative;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .data-table th {
            background: #f8fafc;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
        }

        .data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
            vertical-align: middle;
        }

        .data-table tr:hover {
            background: #f8fafc;
        }

        .data-table .id-column {
            font-weight: 600;
            color: #3b82f6;
        }

        .editable-cell {
            cursor: pointer;
            transition: background 0.2s;
            min-width: 100px;
        }

        .editable-cell:hover {
            background: #eff6ff;
        }

        .editable-cell.editing {
            background: #fef3c7;
            padding: 4px;
        }

        .editable-cell .cell-value {
            display: block;
            padding: 4px;
            word-break: break-word;
            white-space: normal;
            max-width: 300px;
        }

        .editable-cell .cell-input {
            width: 100%;
            padding: 6px 8px;
            border: 2px solid #3b82f6;
            border-radius: 6px;
            font-size: 12px;
            font-family: inherit;
            background: white;
            min-width: 80px;
        }

        .editable-cell .cell-input:focus {
            outline: none;
        }

        .cell-actions {
            display: inline-flex;
            gap: 5px;
            margin-left: 5px;
        }

        .btn-go {
            background: #10b981;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            cursor: pointer;
        }

        .btn-go:hover {
            background: #059669;
        }

        .btn-cancel {
            background: #ef4444;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            cursor: pointer;
        }

        .btn-cancel:hover {
            background: #dc2626;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #94a3b8;
            width: 100%;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 2000;
            animation: slideIn 0.3s ease;
            font-size: 13px;
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
                padding: 15px;
                height: calc(100vh - 60px);
            }

            .database-layout {
                flex-direction: column;
                min-height: 0;
            }

            .table-sidebar {
                width: 100%;
                max-height: 200px;
            }

            .table-list {
                display: flex;
                overflow-x: auto;
                overflow-y: hidden;
                flex: none;
                max-height: none;
            }

            .table-list li {
                border-bottom: none;
                border-right: 1px solid #e2e8f0;
                flex-shrink: 0;
            }

            .table-list li a {
                white-space: nowrap;
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

            .table-info-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-box input {
                width: 180px;
            }

            .editable-cell .cell-value {
                max-width: 150px;
            }

            .table-content {
                min-height: 300px;
            }
        }

        @media (max-width: 480px) {
            .table-actions {
                flex-direction: column;
                width: 100%;
            }

            .table-actions .btn-sm {
                width: 100%;
                text-align: center;
            }

            .search-box {
                width: 100%;
            }

            .search-box input {
                flex: 1;
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
                        <i class="fas fa-database" style="color: #3b82f6;"></i> Database Manager
                    </h4>
                </div>
            </div>

            <div class="database-layout">
                <!-- Sidebar with table list -->
                <div class="table-sidebar">
                    <div class="sidebar-header">
                        <span><i class="fas fa-tables"></i> Tables</span>
                        <button class="download-all-btn" onclick="downloadAllTables()" title="Download all tables as SQL">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                    <ul class="table-list">
                        <?php foreach ($displayTables as $table): ?>
                            <li class="<?php echo $selectedTable === $table ? 'active' : ''; ?>">
                                <a
                                    href="?table=<?php echo urlencode($table); ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                                    <span class="table-name">
                                        <i class="fas fa-table"></i>
                                        <?php echo htmlspecialchars($table); ?>
                                    </span>
                                    <span class="table-count"><?php echo number_format($tableCounts[$table]); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Main content - Table data -->
                <div class="table-content">
                    <div class="table-info-bar">
                        <div class="table-title">
                            <h3><?php echo htmlspecialchars($selectedTable); ?></h3>
                            <span class="badge"><?php echo number_format($tableCount); ?> records</span>
                            <?php if (!empty($searchTerm)): ?>
                                <span class="badge" style="background: #fef3c7; color: #d97706;">
                                    <i class="fas fa-search"></i> Searching: <?php echo htmlspecialchars($searchTerm); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="search-box">
                            <form method="GET" style="display: flex; gap: 8px; align-items: center;">
                                <input type="hidden" name="table"
                                    value="<?php echo htmlspecialchars($selectedTable); ?>">
                                <input type="text" name="search" placeholder="Search records..."
                                    value="<?php echo htmlspecialchars($searchTerm); ?>">
                                <button type="submit"><i class="fas fa-search"></i></button>
                                <?php if (!empty($searchTerm)): ?>
                                    <a href="?table=<?php echo urlencode($selectedTable); ?>" class="btn-sm clear-search"
                                        style="text-decoration: none; display: inline-flex; align-items: center; padding: 8px 12px; border-radius: 8px; background: #e2e8f0; color: #475569;">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="table-actions">
                            <button class="btn-sm btn-primary" onclick="addNewRow()">
                                <i class="fas fa-plus"></i> Add Row
                            </button>
                            <button class="btn-sm btn-outline" onclick="copyTableData()">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                            <button class="btn-sm btn-outline" onclick="exportToCSV()">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </button>
                        </div>
                    </div>

                    <!-- TABLE WRAPPER - ONLY THIS IS SCROLLABLE -->
                    <div class="table-wrapper" id="tableWrapper">
                        <?php if (empty($tableData)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No data found in this
                                    table<?php echo !empty($searchTerm) ? ' matching "' . htmlspecialchars($searchTerm) . '"' : ''; ?>
                                </p>
                                <button class="btn-sm btn-primary" onclick="addNewRow()" style="margin-top: 15px;">
                                    <i class="fas fa-plus"></i> Add First Row
                                </button>
                            </div>
                        <?php else: ?>
                            <table class="data-table" id="dataTable">
                                <thead>
                                    <tr>
                                        <?php foreach ($tableColumns as $column): ?>
                                            <th><?php echo htmlspecialchars($column['Field']); ?></th>
                                        <?php endforeach; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <?php foreach ($tableData as $row): ?>
                                        <tr data-row-id="<?php echo $row['id']; ?>" id="row-<?php echo $row['id']; ?>">
                                            <?php foreach ($tableColumns as $column):
                                                $field = $column['Field'];
                                                $value = $row[$field];
                                                $isId = ($field === 'id');
                                                ?>
                                                <td class="<?php echo $isId ? 'id-column' : 'editable-cell'; ?>"
                                                    data-field="<?php echo $field; ?>"
                                                    data-value="<?php echo htmlspecialchars($value); ?>"
                                                    data-original-value="<?php echo htmlspecialchars($value); ?>">
                                                    <?php if ($isId): ?>
                                                        <span class="cell-value"><?php echo htmlspecialchars($value); ?></span>
                                                    <?php else: ?>
                                                        <div class="cell-value"
                                                            ondblclick="makeEditable(this, '<?php echo $field; ?>', <?php echo $row['id']; ?>)">
                                                            <?php
                                                            if ($value === null) {
                                                                echo '<span style="color: #94a3b8; font-style: italic;">NULL</span>';
                                                            } elseif (is_numeric($value) && strlen($value) > 10 && !$isId) {
                                                                echo '<span style="font-family: monospace;">' . htmlspecialchars($value) . '</span>';
                                                            } else {
                                                                echo htmlspecialchars($value);
                                                            }
                                                            ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td>
                                                <button class="btn-delete" onclick="deleteRow(<?php echo $row['id']; ?>)">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include '../footer.php'; ?>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const currentTable = '<?php echo $selectedTable; ?>';

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

        // Toast notification
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

        // Add new row
        async function addNewRow() {
            showToast('Adding new row...', 'success');

            try {
                const formData = new FormData();
                formData.append('action', 'add_row');
                formData.append('table_name', currentTable);
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
                    showToast('New row added successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Error adding row', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
            }
        }

        // Delete row
        async function deleteRow(rowId) {
            if (!confirm('Are you sure you want to delete this row? This action cannot be undone!')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete_row');
                formData.append('table_name', currentTable);
                formData.append('row_id', rowId);
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
                    showToast('Row deleted successfully!', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Error deleting row', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
            }
        }

        // Make cell editable on double click
        function makeEditable(element, field, rowId) {
            const cell = element.parentElement;
            const currentValue = element.innerText;

            if (cell.classList.contains('editing')) return;

            cell.classList.add('editing');

            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentValue === 'NULL' ? '' : currentValue;
            input.className = 'cell-input';

            const buttonContainer = document.createElement('div');
            buttonContainer.className = 'cell-actions';

            const goBtn = document.createElement('button');
            goBtn.innerHTML = '<i class="fas fa-check"></i> Go';
            goBtn.className = 'btn-go';
            goBtn.onclick = () => saveCellValue(cell, input, field, rowId);

            const cancelBtn = document.createElement('button');
            cancelBtn.innerHTML = '<i class="fas fa-times"></i>';
            cancelBtn.className = 'btn-cancel';
            cancelBtn.onclick = () => cancelEdit(cell, element);

            buttonContainer.appendChild(goBtn);
            buttonContainer.appendChild(cancelBtn);

            cell.innerHTML = '';
            cell.appendChild(input);
            cell.appendChild(buttonContainer);

            input.focus();

            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    saveCellValue(cell, input, field, rowId);
                }
            });
        }

        function cancelEdit(cell, originalElement) {
            cell.classList.remove('editing');
            cell.innerHTML = '';
            cell.appendChild(originalElement);
        }

        async function saveCellValue(cell, input, field, rowId) {
            const newValue = input.value.trim();
            const originalValue = cell.getAttribute('data-original-value');

            if (newValue === originalValue) {
                const originalElement = cell.querySelector('.cell-value');
                cancelEdit(cell, originalElement || document.createElement('div'));
                return;
            }

            const goBtn = cell.querySelector('.btn-go');
            const originalBtnText = goBtn.innerHTML;
            goBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            goBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'update_cell');
                formData.append('table_name', currentTable);
                formData.append('row_id', rowId);
                formData.append('column', field);
                formData.append('value', newValue);
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
                    const displayValue = newValue === '' ? '<span style="color: #94a3b8; font-style: italic;">NULL</span>' : escapeHtml(newValue);
                    const newElement = document.createElement('div');
                    newElement.className = 'cell-value';
                    newElement.setAttribute('ondblclick', `makeEditable(this, '${field}', ${rowId})`);
                    newElement.innerHTML = displayValue;

                    cell.classList.remove('editing');
                    cell.innerHTML = '';
                    cell.appendChild(newElement);
                    cell.setAttribute('data-value', newValue);
                    cell.setAttribute('data-original-value', newValue);

                    showToast('Cell updated successfully!', 'success');
                } else {
                    showToast(data.message || 'Error updating cell', 'error');
                    cancelEdit(cell, cell.querySelector('.cell-value') || document.createElement('div'));
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
                cancelEdit(cell, cell.querySelector('.cell-value') || document.createElement('div'));
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function copyTableData() {
            const table = document.getElementById('dataTable');
            if (!table) {
                alert('No data to copy');
                return;
            }

            let copyText = '';
            const rows = table.querySelectorAll('tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('th, td');
                const rowText = Array.from(cells).map(cell => {
                    const value = cell.querySelector('.cell-value')?.innerText || cell.innerText;
                    return value.trim();
                }).join('\t');
                copyText += rowText + '\n';
            });

            navigator.clipboard.writeText(copyText).then(() => {
                alert('Table data copied to clipboard!');
            }).catch(() => {
                alert('Failed to copy data');
            });
        }

        function exportToCSV() {
            const table = document.getElementById('dataTable');
            if (!table) {
                alert('No data to export');
                return;
            }

            let csv = [];
            const rows = table.querySelectorAll('tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('th, td');
                const rowData = Array.from(cells).map(cell => {
                    let text = cell.querySelector('.cell-value')?.innerText || cell.innerText;
                    text = text.trim();
                    if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                        text = '"' + text.replace(/"/g, '""') + '"';
                    }
                    return text;
                });
                csv.push(rowData.join(','));
            });

            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = '<?php echo $selectedTable; ?>_export.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Download all tables as SQL
        function downloadAllTables() {
            showToast('Preparing download...', 'success');

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../API/export_all_tables.php';

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>
</body>

</html>