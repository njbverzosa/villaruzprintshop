<?php
// blank_spreadsheet.php - Excel-like spreadsheet with 20 columns
session_start();

// ==============================================
// 1. FIX PATHS - config.php is in DB_Conn folder at root level
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// STORE USER NAME IN SESSION FOR API USE
// ==============================================
if (isset($userData['f_name']) && !isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = $userData['f_name'];
}

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

// Get current page for sidebar
$currentPage = basename($_SERVER['PHP_SELF']);

// Store authorize_access in a variable
$authorizeAccess = isset($userData['authorize_access']) ? (int) $userData['authorize_access'] : 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Spreadsheet | Villaruz Print Shop</title>
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

        /* ========== SIDEBAR - LEFT SIDE ========== */
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

        /* Mobile: sidebar hidden by default */
        @media (max-width: 768px) {
            .sidebar-wrapper {
                transform: translateX(-100%);
            }

            .sidebar-wrapper.open {
                transform: translateX(0);
            }
        }

        /* Desktop: sidebar always visible */
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

            .sidebar-close-btn {
                display: none !important;
            }
        }

        /* Mobile overlay */
        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
            z-index: 999;
            display: none;
        }

        .menu-overlay.active {
            display: block;
        }

        /* ========== BURGER BUTTON (Mobile Only) - In Header ========== */
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

        .burger-btn i {
            font-size: 24px;
        }

        @media (max-width: 768px) {
            .burger-btn {
                display: flex;
            }
        }

        /* ========== SIDEBAR CLOSE BUTTON (Mobile Only) ========== */
        .sidebar-close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: #64748b;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s;
            display: none;
            z-index: 10;
        }

        .sidebar-close-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        @media (max-width: 768px) {
            .sidebar-close-btn {
                display: block;
            }
        }

        /* ========== MAIN CONTENT ========== */
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
                padding-top: 20px;
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
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .welcome h4 {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
        }

        .welcome h4 i {
            color: #3b82f6;
            margin-right: 10px;
        }

        .welcome p {
            font-size: 13px;
            color: #64748b;
            margin-top: 5px;
        }

        .menu-header {
            padding: 25px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            flex-shrink: 0;
            padding-right: 50px;
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
            overflow-y: auto;
        }

        

        /* Table Controls */
        .table-controls {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(145deg, #3b82f6, #2563eb);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-success {
            background: linear-gradient(145deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-warning {
            background: linear-gradient(145deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-danger {
            background: linear-gradient(145deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        /* Spreadsheet */
        .spreadsheet-container {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: auto;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            max-height: 65vh;
        }

        .spreadsheet-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .spreadsheet-table th {
            background: #f1f5f9;
            padding: 12px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            color: #475569;
            border: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .spreadsheet-table td {
            padding: 0;
            border: 1px solid #e2e8f0;
        }

        .spreadsheet-table input {
            width: 100%;
            padding: 10px 8px;
            border: none;
            font-size: 13px;
            background: white;
            outline: none;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
        }

        .spreadsheet-table input:focus {
            background: #eff6ff;
            box-shadow: inset 0 0 0 2px #3b82f6;
        }

        .spreadsheet-table input:hover {
            background: #f8fafc;
        }

        .row-number {
            background: #f8fafc;
            text-align: center;
            font-weight: 500;
            color: #64748b;
            width: 40px;
        }

        .header-input {
            font-weight: 700 !important;
            background: #f1f5f9 !important;
            text-align: center !important;
        }

        /* Formula Bar */
        .formula-bar {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 12px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .formula-label {
            font-weight: 600;
            color: #64748b;
            font-size: 13px;
            background: #f1f5f9;
            padding: 6px 12px;
            border-radius: 8px;
        }

        .formula-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .formula-input:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .cell-address {
            font-family: monospace;
            font-weight: 600;
            color: #3b82f6;
            background: #eff6ff;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
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

        /* Toast */
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

        .toast-info {
            background: #3b82f6;
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
                padding-top: 20px;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
            }

            .table-controls {
                justify-content: space-between;
            }

            .btn {
                font-size: 12px;
                padding: 8px 14px;
            }

            .formula-bar {
                padding: 10px 15px;
                gap: 10px;
            }

            .dashboard-header {
                padding: 15px 20px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
                padding-top: 15px;
            }

            .dashboard-header {
                padding: 12px 15px;
                border-radius: 10px;
            }

            .welcome h4 {
                font-size: 16px;
            }

            .welcome p {
                font-size: 11px;
            }

            .btn {
                font-size: 11px;
                padding: 6px 10px;
            }

            .btn i {
                font-size: 12px;
            }

            .table-controls {
                gap: 8px;
            }

            .formula-bar {
                padding: 8px 12px;
                gap: 6px;
            }

            .formula-label {
                font-size: 11px;
                padding: 4px 8px;
            }

            .cell-address {
                font-size: 11px;
                padding: 4px 8px;
            }

            .formula-input {
                font-size: 12px;
                padding: 6px 10px;
            }

            .spreadsheet-table input {
                font-size: 11px;
                padding: 6px 4px;
            }

            .spreadsheet-container {
                max-height: 50vh;
            }
        }
    </style>
</head>

<body>
    <div class="app-wrapper">
        <!-- Overlay (Mobile Only) -->
        <div class="menu-overlay" id="menuOverlay"></div>

        <!-- Sidebar Wrapper -->
        <div class="sidebar-wrapper" id="sidebarWrapper">
            <div class="side-menu" id="sideMenu">
                <?php
                include 'sidebar.php';
                ?>
            </div>
        </div>

        <main class="main-content">
            <div class="dashboard-header">
                <div class="header-left">
                    <!-- Burger Button (Mobile Only) -->
                    <button class="burger-btn" id="burgerBtn" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="welcome">
                        <h4><i class="fas fa-table"></i> Excel-like Spreadsheet</h4>
                        <p style="font-size: 13px; color: #64748b; margin-top: 5px;">20 columns (A-T) | Click any cell
                            to
                            edit | Click headers to rename</p>
                    </div>
                </div>
                <div class="table-controls">
                    <button class="btn btn-primary" id="addRowBtn">
                        <i class="fas fa-plus"></i> Add Row
                    </button>
                    <button class="btn btn-primary" id="addColumnBtn">
                        <i class="fas fa-columns"></i> Add Column
                    </button>
                    <button class="btn btn-danger" id="removeColumnBtn">
                        <i class="fas fa-trash"></i> Remove Column
                    </button>
                    <button class="btn btn-success" id="saveSheetBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button class="btn btn-secondary" id="exportCsvBtn">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                    <button class="btn btn-warning" id="clearAllBtn">
                        <i class="fas fa-eraser"></i> Clear All
                    </button>
                </div>
            </div>

            <!-- Formula Bar -->
            <div class="formula-bar">
                <span class="formula-label">FX</span>
                <span class="cell-address" id="cellAddress">A1</span>
                <input type="text" class="formula-input" id="formulaInput" placeholder="Enter value...">
            </div>

            <!-- Spreadsheet -->
            <div class="spreadsheet-container">
                <table class="spreadsheet-table" id="spreadsheetTable">
                    <thead id="tableHeader">
                        <tr id="headerRow">
                            <th style="width: 40px; background: #e2e8f0;">#</th>
                            <!-- Column headers will be generated by JavaScript -->
                        </tr>
                    </thead>
                    <tbody id="spreadsheetBody"></tbody>
                </table>
            </div>
        </main>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner"></i>
            <p>Processing...</p>
        </div>
    </div>

    <?php
    include '../footer.php';
    ?>

    <script>
        // ========== SIDEBAR TOGGLE (Mobile Only) ==========
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
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

        function toggleSidebar() {
            if (isSidebarOpen) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        if (burgerBtn) {
            burgerBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                toggleSidebar();
            });
        }

        if (sidebarCloseBtn) {
            sidebarCloseBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                closeSidebar();
            });
        }

        if (menuOverlay) {
            menuOverlay.addEventListener('click', closeSidebar);
        }

        // Close sidebar when clicking a nav link (mobile only)
        document.querySelectorAll('.side-menu .nav-item, .side-menu .nav-dropdown-item').forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 768) {
                    if (!this.closest('.nav-dropdown-toggle')) {
                        closeSidebar();
                    }
                }
            });
        });

        // ========== DROPDOWN TOGGLE ==========
        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            const arrowId = dropdownId.replace('Dropdown', 'Arrow');
            const arrow = document.getElementById(arrowId);

            if (dropdown && arrow) {
                dropdown.classList.toggle('show');
                arrow.classList.toggle('rotated');
            }
        }

        // ========== BURGER VISIBILITY ON RESIZE ==========
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                if (isSidebarOpen) {
                    closeSidebar();
                }
                sidebarWrapper.classList.remove('open');
                menuOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // ========== EXISTING FUNCTIONS ==========
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Configuration - 20 columns by default
        let ROWS = 30;
        let COLS = 20;

        // Generate default column headers (A through T)
        let columnHeaders = [];
        for (let i = 0; i < COLS; i++) {
            columnHeaders.push(getColumnLetter(i));
        }

        let spreadsheetData = [];
        let activeCell = null;
        let activeRow = 0;
        let activeCol = 0;

        // Show/hide loading
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        // Initialize empty spreadsheet
        function initEmptySpreadsheet() {
            spreadsheetData = [];
            for (let i = 0; i < ROWS; i++) {
                spreadsheetData[i] = [];
                for (let j = 0; j < COLS; j++) {
                    spreadsheetData[i][j] = '';
                }
            }
            renderSpreadsheet();
        }

        // Get column letter from index (supports up to Z and beyond)
        function getColumnLetter(index) {
            let letter = '';
            let num = index;
            while (num >= 0) {
                letter = String.fromCharCode(65 + (num % 26)) + letter;
                num = Math.floor(num / 26) - 1;
            }
            return letter;
        }

        // Render spreadsheet
        function renderSpreadsheet() {
            const thead = document.getElementById('tableHeader');
            const tbody = document.getElementById('spreadsheetBody');

            // Render header row
            const headerRow = document.getElementById('headerRow');
            headerRow.innerHTML = '<th style="width: 40px; background: #e2e8f0;">#</th>';

            for (let j = 0; j < COLS; j++) {
                const th = document.createElement('th');
                th.style.position = 'relative';
                th.style.padding = '4px';
                th.style.minWidth = '100px';

                const headerInput = document.createElement('input');
                headerInput.type = 'text';
                headerInput.value = columnHeaders[j] || getColumnLetter(j);
                headerInput.style.width = '100%';
                headerInput.style.padding = '8px';
                headerInput.style.border = 'none';
                headerInput.style.background = '#f1f5f9';
                headerInput.style.fontWeight = '700';
                headerInput.style.textAlign = 'center';
                headerInput.style.fontSize = '13px';
                headerInput.setAttribute('data-col', j);
                headerInput.classList.add('header-input');

                headerInput.addEventListener('change', function () {
                    const colIdx = parseInt(this.getAttribute('data-col'));
                    columnHeaders[colIdx] = this.value;
                    showToast(`Column ${getColumnLetter(colIdx)} renamed to "${this.value}"`, 'success');
                });

                th.appendChild(headerInput);
                headerRow.appendChild(th);
            }

            // Render body
            tbody.innerHTML = '';

            for (let i = 0; i < ROWS; i++) {
                const row = document.createElement('tr');

                // Row number cell
                const rowNumCell = document.createElement('td');
                rowNumCell.className = 'row-number';
                rowNumCell.textContent = i + 1;
                rowNumCell.style.backgroundColor = '#f8fafc';
                rowNumCell.style.fontWeight = '500';
                row.appendChild(rowNumCell);

                // Data cells
                for (let j = 0; j < COLS; j++) {
                    const cell = document.createElement('td');
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.value = (spreadsheetData[i] && spreadsheetData[i][j] !== undefined) ? spreadsheetData[i][j] : '';
                    input.setAttribute('data-row', i);
                    input.setAttribute('data-col', j);

                    input.addEventListener('focus', function () {
                        activeRow = parseInt(this.getAttribute('data-row'));
                        activeCol = parseInt(this.getAttribute('data-col'));
                        activeCell = this;
                        document.getElementById('cellAddress').textContent = `${getColumnLetter(activeCol)}${activeRow + 1}`;
                        document.getElementById('formulaInput').value = (spreadsheetData[activeRow] && spreadsheetData[activeRow][activeCol] !== undefined) ? spreadsheetData[activeRow][activeCol] : '';
                    });

                    input.addEventListener('input', function () {
                        const rowIdx = parseInt(this.getAttribute('data-row'));
                        const colIdx = parseInt(this.getAttribute('data-col'));
                        if (!spreadsheetData[rowIdx]) spreadsheetData[rowIdx] = [];
                        spreadsheetData[rowIdx][colIdx] = this.value;
                    });

                    cell.appendChild(input);
                    row.appendChild(cell);
                }
                tbody.appendChild(row);
            }
        }

        // Add new row
        function addRow() {
            ROWS++;
            const newRow = [];
            for (let j = 0; j < COLS; j++) {
                newRow.push('');
            }
            spreadsheetData.push(newRow);
            renderSpreadsheet();
            showToast(`Row ${ROWS} added`, 'success');
        }

        // Add new column
        function addColumn() {
            COLS++;
            const newHeader = prompt('Enter column header name:', `Column ${getColumnLetter(COLS - 1)}`);
            columnHeaders.push(newHeader || `Column ${getColumnLetter(COLS - 1)}`);

            // Add new column to each row
            for (let i = 0; i < spreadsheetData.length; i++) {
                if (!spreadsheetData[i]) spreadsheetData[i] = [];
                spreadsheetData[i].push('');
            }

            renderSpreadsheet();
            showToast(`Column ${getColumnLetter(COLS - 1)} added. Total columns: ${COLS}`, 'success');
        }

        // Remove last column
        function removeLastColumn() {
            if (COLS <= 1) {
                showToast('Cannot remove the last column', 'error');
                return;
            }

            if (confirm(`Are you sure you want to remove column "${columnHeaders[COLS - 1]}"? All data in this column will be lost.`)) {
                COLS--;
                columnHeaders.pop();

                // Remove last column from each row
                for (let i = 0; i < spreadsheetData.length; i++) {
                    if (spreadsheetData[i] && spreadsheetData[i].length > 0) {
                        spreadsheetData[i].pop();
                    }
                }

                renderSpreadsheet();
                showToast(`Column removed. Total columns: ${COLS}`, 'success');
            }
        }

        // Clear all data
        function clearAll() {
            if (confirm('Are you sure you want to clear all data? This cannot be undone.')) {
                for (let i = 0; i < ROWS; i++) {
                    for (let j = 0; j < COLS; j++) {
                        spreadsheetData[i][j] = '';
                    }
                }
                renderSpreadsheet();
                showToast('All data cleared!', 'success');
            }
        }

        // Export to CSV
        function exportToCSV() {
            let csvContent = '';

            // Header row with custom column names
            const headerRow = ['#'];
            for (let j = 0; j < COLS; j++) {
                let headerValue = columnHeaders[j] || getColumnLetter(j);
                headerValue = headerValue.replace(/"/g, '""');
                if (headerValue.includes(',') || headerValue.includes('"') || headerValue.includes('\n')) {
                    headerValue = `"${headerValue}"`;
                }
                headerRow.push(headerValue);
            }
            csvContent += headerRow.join(',') + '\n';

            // Data rows
            for (let i = 0; i < ROWS; i++) {
                const rowData = [i + 1];
                for (let j = 0; j < COLS; j++) {
                    let cellValue = (spreadsheetData[i] && spreadsheetData[i][j] !== undefined) ? spreadsheetData[i][j] : '';
                    cellValue = String(cellValue).replace(/"/g, '""');
                    if (cellValue.includes(',') || cellValue.includes('"') || cellValue.includes('\n')) {
                        cellValue = `"${cellValue}"`;
                    }
                    rowData.push(cellValue);
                }
                csvContent += rowData.join(',') + '\n';
            }

            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.href = url;
            link.setAttribute('download', 'spreadsheet_export.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            showToast('Exported successfully!', 'success');
        }

        // Save to localStorage
        function saveToLocal() {
            const saveData = {
                rows: ROWS,
                cols: COLS,
                headers: columnHeaders,
                data: spreadsheetData
            };
            localStorage.setItem('spreadsheet_data', JSON.stringify(saveData));
            showToast('Spreadsheet saved locally!', 'success');
        }

        // Load from localStorage
        function loadFromLocal() {
            const saved = localStorage.getItem('spreadsheet_data');
            if (saved) {
                const saveData = JSON.parse(saved);
                ROWS = saveData.rows;
                COLS = saveData.cols;
                columnHeaders = saveData.headers;
                spreadsheetData = saveData.data;
                renderSpreadsheet();
                showToast('Spreadsheet loaded from local storage!', 'success');
            } else {
                initEmptySpreadsheet();
                showToast('New spreadsheet with 20 columns created!', 'success');
            }
        }

        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle';
            toast.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Formula bar update
        document.getElementById('formulaInput').addEventListener('change', function () {
            if (activeCell) {
                const value = this.value;
                activeCell.value = value;
                if (!spreadsheetData[activeRow]) spreadsheetData[activeRow] = [];
                spreadsheetData[activeRow][activeCol] = value;
            }
        });

        // Button events
        document.getElementById('addRowBtn').addEventListener('click', addRow);
        document.getElementById('addColumnBtn').addEventListener('click', addColumn);
        document.getElementById('removeColumnBtn').addEventListener('click', removeLastColumn);
        document.getElementById('saveSheetBtn').addEventListener('click', saveToLocal);
        document.getElementById('exportCsvBtn').addEventListener('click', exportToCSV);
        document.getElementById('clearAllBtn').addEventListener('click', clearAll);

        // Keyboard navigation
        document.addEventListener('keydown', function (e) {
            if (activeCell) {
                let newRow = activeRow;
                let newCol = activeCol;

                switch (e.key) {
                    case 'ArrowUp':
                        newRow = Math.max(0, activeRow - 1);
                        break;
                    case 'ArrowDown':
                        newRow = Math.min(ROWS - 1, activeRow + 1);
                        break;
                    case 'ArrowLeft':
                        newCol = Math.max(0, activeCol - 1);
                        break;
                    case 'ArrowRight':
                        newCol = Math.min(COLS - 1, activeCol + 1);
                        break;
                    case 'Tab':
                        e.preventDefault();
                        newCol = Math.min(COLS - 1, activeCol + 1);
                        if (newCol === activeCol && activeCol === COLS - 1) {
                            newCol = 0;
                            newRow = Math.min(ROWS - 1, activeRow + 1);
                        }
                        break;
                    case 'Enter':
                        e.preventDefault();
                        newRow = Math.min(ROWS - 1, activeRow + 1);
                        break;
                    default:
                        return;
                }

                if (newRow !== activeRow || newCol !== activeCol) {
                    const newCell = document.querySelector(`input[data-row="${newRow}"][data-col="${newCol}"]`);
                    if (newCell) newCell.focus();
                }
            }
        });

        // Ctrl+S shortcut
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveToLocal();
            }
        });

        // Initial load
        loadFromLocal();

        console.log('📱 Sidebar menu loaded - Left Side');
        console.log('📐 Desktop: Sidebar expanded | Mobile: Burger menu');
    </script>
</body>

</html>