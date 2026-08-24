<?php
// blank_spreadsheet.php - Excel-like spreadsheet with 20 columns
require_once 'access_sessions.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
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
            flex-wrap: wrap;
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

        /* Burger Button */
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

        /* Side Menu */
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

            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
            }

            .table-controls {
                justify-content: space-between;
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

        <div class="side-menu" id="sideMenu">
            <div class="menu-header">
                <i class="fas fa-store"></i>
                <div class="user-greeting">Logged in as</div>
                <div class="user-name"><?php echo htmlspecialchars($user['acc_number'] ?? 'User'); ?></div>
            </div>
            <div class="menu-nav">
                <a href="registered_customers.php" class="nav-item">
                    <i class="fas fa-user-friends"></i>
                    <span>Customers</span>
                </a>
                <a href="all_products.php" class="nav-item">
                    <i class="fas fa-cubes"></i>
                    <span>Stocks</span>
                </a>
                <a href="sold_products.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Sold</span>
                </a>
                <a href="all_pending_orders.php" class="nav-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Orders</span>
                </a>
                <a href="blank_spreadsheet.php" class="nav-item active">
                    <i class="fas fa-table"></i>
                    <span>Spreadsheet</span>
                </a>
                <a href="closed.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <main class="main-content">
            <div class="dashboard-header">
                <div class="welcome">
                    <h4><i class="fas fa-table"></i> Excel-like Spreadsheet</h4>
                    <p style="font-size: 13px; color: #64748b; margin-top: 5px;">20 columns (A-T) | Click any cell to
                        edit | Click headers to rename</p>
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
            toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            document.body.appendChild(toast);

            const style = document.createElement('style');
            style.textContent = `
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
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }
                .toast-success { background: #10b981; }
                .toast-error { background: #ef4444; }
                .toast-info { background: #3b82f6; }
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);

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

        // Burger menu
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
            if (sideMenu.classList.contains('open')) closeMenu();
            else openMenu();
        });

        menuOverlay.addEventListener('click', closeMenu);
        document.querySelectorAll('.side-menu .nav-item').forEach(link => {
            link.addEventListener('click', () => closeMenu());
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeMenu();
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
                    case 'ArrowUp': newRow = Math.max(0, activeRow - 1); break;
                    case 'ArrowDown': newRow = Math.min(ROWS - 1, activeRow + 1); break;
                    case 'ArrowLeft': newCol = Math.max(0, activeCol - 1); break;
                    case 'ArrowRight': newCol = Math.min(COLS - 1, activeCol + 1); break;
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
                    default: return;
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
    </script>
</body>

</html>