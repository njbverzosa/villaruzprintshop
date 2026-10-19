<style>
    /* Sidebar */
    .logo img {
        width: 245px;
        height: auto;
        object-fit: contain;
        border-radius: 5px;
    }

    /* ========== SIDEBAR NAVIGATION - COMPLETE HOVER & ACTIVE STATES ========== */

    /* ===== MAIN NAV ITEMS ===== */
    .menu-nav .nav-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 14px 12px;
        border-radius: 14px;
        color: #475569;
        text-decoration: none;
        transition: all 0.2s ease;
        margin-bottom: 8px;
        cursor: pointer;
    }

    .menu-nav .nav-item i {
        width: 24px;
        font-size: 20px;
        color: #3b82f6;
        transition: transform 0.2s ease;
    }

    .menu-nav .nav-item span {
        font-size: 15px;
        font-weight: 500;
    }

    .menu-nav .nav-item:hover {
        background: #eff6ff;
        color: #1e293b;
    }

    .menu-nav .nav-item:hover i {
        transform: scale(1.05);
    }

    .menu-nav .nav-item.active {
        background: #eff6ff;
        color: #3b82f6;
        border-left: 3px solid #3b82f6;
    }

    .menu-nav .nav-item.active:hover {
        background: #dbeafe;
        color: #1e40af;
    }

    .menu-nav .nav-item.shop {
        background: #eff6ff;
        color: #3b82f6;
        border-left: 3px solid #3b82f6;
    }

    .menu-nav .nav-item.shop:hover {
        background: #dbeafe;
        color: #1e40af;
    }

    /* ===== DROPDOWN TOGGLES ===== */
    .nav-dropdown-toggle {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 14px 12px;
        border-radius: 14px;
        color: #475569;
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .nav-dropdown-toggle:hover {
        background: #eff6ff;
        color: #1e293b;
    }

    .nav-dropdown-toggle i:first-child {
        width: 24px;
        font-size: 20px;
        color: #3b82f6;
        transition: transform 0.2s ease;
    }

    .nav-dropdown-toggle:hover i:first-child {
        transform: scale(1.05);
    }

    .nav-dropdown-toggle span {
        flex: 1;
        font-size: 15px;
        font-weight: 500;
    }

    .dropdown-arrow {
        font-size: 12px !important;
        transition: transform 0.3s ease;
        width: auto !important;
    }

    .dropdown-arrow.rotated {
        transform: rotate(180deg);
    }

    /* ===== DROPDOWN ITEMS - ALL ===== */
    .nav-dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 10px;
        color: #475569;
        text-decoration: none;
        transition: all 0.2s ease;
        font-size: 14px;
        cursor: pointer;
    }

    .nav-dropdown-item:hover {
        background: #eff6ff;
        color: #1e293b;
    }

    .nav-dropdown-item i {
        width: 20px;
        font-size: 14px;
        color: #3b82f6;
        transition: transform 0.2s ease;
    }

    .nav-dropdown-item:hover i {
        transform: scale(1.05);
    }

    .nav-dropdown-item span {
        font-size: 13px;
        font-weight: 500;
    }

    /* ===== GENERIC ACTIVE STATE ===== */
    .nav-dropdown-item.active {
        background: #eff6ff;
        color: #2563eb;
        border-left: 3px solid #3b82f6;
    }

    .nav-dropdown-item.active:hover {
        background: #dbeafe;
        color: #1e40af;
    }

    .nav-dropdown-item.active i {
        color: #3b82f6;
    }

    /* ===== PENDING (Orange) ===== */
    .nav-dropdown-item.active_pending {
        background: #eff6ff;
        color: #d97706;
        border-left: 3px solid #f59e0b;
    }

    .nav-dropdown-item.active_pending:hover {
        background: #fef3c7;
        color: #92400e;
    }

    .nav-dropdown-item.active_pending i {
        color: #f59e0b;
    }

    /* ===== PAID (Green) ===== */
    .nav-dropdown-item.active_paid {
        background: #eff6ff;
        color: #059669;
        border-left: 3px solid #10b981;
    }

    .nav-dropdown-item.active_paid:hover {
        background: #d1fae5;
        color: #065f46;
    }

    .nav-dropdown-item.active_paid i {
        color: #10b981;
    }

    /* ===== CREDIT (Red) ===== */
    .nav-dropdown-item.active_credit {
        background: #eff6ff;
        color: #dc2626;
        border-left: 3px solid #ef4444;
    }

    .nav-dropdown-item.active_credit:hover {
        background: #fee2e2;
        color: #991b1b;
    }

    .nav-dropdown-item.active_credit i {
        color: #ef4444;
    }

    /* ===== PURCHASE ORDER - SHOP (Blue) ===== */
    .nav-dropdown-item.active_shop_po {
        background: #eff6ff;
        color: #2563eb;
        border-left: 3px solid #3b82f6;
    }

    .nav-dropdown-item.active_shop_po:hover {
        background: #dbeafe;
        color: #1e40af;
    }

    .nav-dropdown-item.active_shop_po i {
        color: #3b82f6;
    }

    /* ===== PURCHASE ORDER - CART (Orange) ===== */
    .nav-dropdown-item.active_cart_po {
        background: #eff6ff;
        color: #d97706;
        border-left: 3px solid #f59e0b;
    }

    .nav-dropdown-item.active_cart_po:hover {
        background: #fef3c7;
        color: #92400e;
    }

    .nav-dropdown-item.active_cart_po i {
        color: #f59e0b;
    }

    /* ===== CUSTOMERS (Teal) ===== */
    .nav-dropdown-item.active_customers {
        background: #eff6ff;
        color: #0d9488;
        border-left: 3px solid #14b8a6;
    }

    .nav-dropdown-item.active_customers:hover {
        background: #ccfbf1;
        color: #0f766e;
    }

    .nav-dropdown-item.active_customers i {
        color: #14b8a6;
    }

    /* ===== DB MANAGER (Blue) ===== */
    .nav-dropdown-item.active_database {
        background: #eff6ff;
        color: #2563eb;
        border-left: 3px solid #3b82f6;
    }

    .nav-dropdown-item.active_database:hover {
        background: #dbeafe;
        color: #1e40af;
    }

    .nav-dropdown-item.active_database i {
        color: #3b82f6;
    }

    /* ===== DOWNLOAD IMAGES (Purple) ===== */
    .nav-dropdown-item.active_download_images {
        background: #eff6ff;
        color: #7c3aed;
        border-left: 3px solid #8b5cf6;
    }

    .nav-dropdown-item.active_download_images:hover {
        background: #ede9fe;
        color: #5b21b6;
    }

    .nav-dropdown-item.active_download_images i {
        color: #8b5cf6;
    }

    /* ===== INVESTORS (Pink) ===== */
    .nav-dropdown-item.active_investors {
        background: #eff6ff;
        color: #db2777;
        border-left: 3px solid #ec4899;
    }

    .nav-dropdown-item.active_investors:hover {
        background: #fce7f3;
        color: #9d174d;
    }

    .nav-dropdown-item.active_investors i {
        color: #ec4899;
    }

    /* ===== PRODUCTS - SOURCE (Indigo) ===== */
    .nav-dropdown-item.active_products_source {
        background: #eff6ff;
        color: #4f46e5;
        border-left: 3px solid #6366f1;
    }

    .nav-dropdown-item.active_products_source:hover {
        background: #e0e7ff;
        color: #3730a3;
    }

    .nav-dropdown-item.active_products_source i {
        color: #6366f1;
    }

    /* ===== DROPDOWN MENU CONTAINER ===== */
    .nav-dropdown-menu {
        display: none;
        margin-left: 35px;
        margin-top: 5px;
        margin-bottom: 5px;
        border-left: 2px solid #e2e8f0;
        padding-left: 10px;
    }

    .nav-dropdown-menu.show {
        display: block;
    }

    /* ===== ACTIVE INDICATOR FOR DROPDOWN TOGGLE ===== */
    .nav-dropdown-toggle.active-dropdown {
        background: #eff6ff;
        color: #3b82f6;
    }

    .nav-dropdown-toggle.active-dropdown i:first-child {
        color: #3b82f6;
    }
</style>

<button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close sidebar">
    <i class="fas fa-arrow-left"></i>
</button>

<div class="menu-header">
    <div class="logo">
        <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/logo.jpeg"
            alt="Villaruz Print Shop Logo">
    </div>

    <div class="user-name">
        <?php
        echo htmlspecialchars($user['user_name'] ?? 'User');
        ?> - <?php echo htmlspecialchars($user['acc_number'] ?? ''); ?>
    </div>
</div>

<div class="menu-nav">
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    $authorizeAccess = isset($user['authorize_access']) ? (int) $user['authorize_access'] : 0;

    // ==========================================
    // ACTIVE FLAGS
    // ==========================================
    $pendingActive = in_array($currentPage, ['pending_folder.php', 'pending_folder_with.php', 'pending_orders.php']);
    $paidActive = in_array($currentPage, ['paid_folder.php', 'paid_folder_with.php', 'paid_orders.php']);
    $creditActive = in_array($currentPage, ['credit_folder.php', 'credit_folder_with.php', 'credit_orders.php']);

    $ordersActive = $pendingActive || $paidActive || $creditActive;

    $customersActive = in_array($currentPage, ['registered_customers.php', 'chat_view.php']);
    $sourceActive = in_array($currentPage, ['investors.php', 'investors_products.php']);

    // DB Manager dropdown becomes active when either child is the current page
    $dbManagerActive = in_array($currentPage, ['database_manager.php']);
    ?>

    <!-- ========== SHOP + CART ========== -->
    <a href="shop.php" class="nav-item <?php echo $currentPage == 'shop.php' ? 'active' : ''; ?>">
        <i class="fas fa-store"></i>
        <span>Shop</span>
    </a>
    <a href="cart.php" class="nav-item <?php echo $currentPage == 'cart.php' ? 'active' : ''; ?>">
        <i class="fas fa-shopping-cart"></i>
        <span>Cart</span>
    </a>

    <!-- ========== SOURCE DROPDOWN ========== -->
    <div class="nav-dropdown">
        <div class="nav-dropdown-toggle" onclick="toggleDropdown('sourceDropdown')">
            <i class="fas fa-layer-group"></i>
            <span>Source</span>
            <i class="fas fa-chevron-down dropdown-arrow <?php echo $sourceActive ? 'rotated' : ''; ?>"
                id="sourceArrow"></i>
        </div>
        <div class="nav-dropdown-menu <?php echo $sourceActive ? 'show' : ''; ?>" id="sourceDropdown">
            <a href="investors_products.php"
                class="nav-dropdown-item <?php echo $currentPage == 'investors_products.php' ? 'active_products_source' : ''; ?>">
                <i class="fas fa-boxes"></i>
                <span>Products</span>
            </a>
        </div>
    </div>

    <!-- ========== ORDERS DROPDOWN ========== -->
    <div class="nav-dropdown">
        <div class="nav-dropdown-toggle" onclick="toggleDropdown('ordersDropdown')">
            <i class="fas fa-shopping-bag"></i>
            <span>Orders</span>
            <i class="fas fa-chevron-down dropdown-arrow <?php echo $ordersActive ? 'rotated' : ''; ?>"
                id="ordersArrow"></i>
        </div>
        <div class="nav-dropdown-menu <?php echo $ordersActive ? 'show' : ''; ?>" id="ordersDropdown">
            <a href="pending_folder.php"
                class="nav-dropdown-item <?php echo $pendingActive ? 'active_pending' : ''; ?>">
                <i class="fas fa-clock"></i>
                <span>Pending</span>
            </a>
            <a href="paid_folder.php" class="nav-dropdown-item <?php echo $paidActive ? 'active_paid' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                <span>Paid</span>
            </a>
            <?php if ($authorizeAccess == 1): ?>
                <a href="credit_folder.php" class="nav-dropdown-item <?php echo $creditActive ? 'active_credit' : ''; ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Credit</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========== CUSTOMERS DROPDOWN (authorize_access == 0 only) ========== -->
    <?php if ($authorizeAccess == 0): ?>
        <div class="nav-dropdown">
            <div class="nav-dropdown-toggle" onclick="toggleDropdown('customersDropdown')">
                <i class="fas fa-user-friends"></i>
                <span>Customers</span>
                <i class="fas fa-chevron-down dropdown-arrow <?php echo $customersActive ? 'rotated' : ''; ?>"
                    id="customersArrow"></i>
            </div>
            <div class="nav-dropdown-menu <?php echo $customersActive ? 'show' : ''; ?>" id="customersDropdown">
                <a href="registered_customers.php"
                    class="nav-dropdown-item <?php echo in_array($currentPage, ['registered_customers.php', 'chat_view.php']) ? 'active_customers' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>List</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========== SYSTEM MANAGER / DB MANAGER DROPDOWN (authorize_access == 0 only) ========== -->
    <?php if ($authorizeAccess == 0): ?>
        <div class="nav-dropdown">
            <div class="nav-dropdown-toggle" onclick="toggleDropdown('dbManagerDropdown')">
                <i class="fas fa-database"></i>
                <span>System Manager</span>
                <i class="fas fa-chevron-down dropdown-arrow <?php echo $dbManagerActive ? 'rotated' : ''; ?>"
                    id="dbManagerArrow"></i>
            </div>
            <div class="nav-dropdown-menu <?php echo $dbManagerActive ? 'show' : ''; ?>" id="dbManagerDropdown">

                <a href="database_manager.php"
                    class="nav-dropdown-item <?php echo $currentPage == 'database_manager.php' ? 'active_database' : ''; ?>">
                    <i class="fas fa-table"></i>
                    <span>Database</span>
                </a>

                <a href="../API/download_images.php"
                    class="nav-dropdown-item <?php echo $currentPage == 'download_images.php' ? 'active_download_images' : ''; ?>">
                    <i class="fas fa-images"></i>
                    <span>Download Images</span>
                </a>

                <a href="#" onclick="downloadFullDatabase(event)" class="nav-dropdown-item">
                    <i class="fas fa-file-export"></i>
                    <span>Download Database</span>
                </a>

            </div>
        </div>
    <?php endif; ?>

    <!-- ========== LOGOUT ========== -->
    <a href="closed.php" class="nav-item <?php echo $currentPage == 'closed.php' ? 'active' : ''; ?>">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</div>

<script>
    function downloadFullDatabase(e) {
        if (e) e.preventDefault();

        const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>';

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../API/export_all_tables.php';
        form.style.display = 'none';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    function toggleDropdown(id) {
        const menu = document.getElementById(id);
        if (!menu) return;

        menu.classList.toggle('show');

        const arrowMap = {
            sourceDropdown: 'sourceArrow',
            ordersDropdown: 'ordersArrow',
            customersDropdown: 'customersArrow',
            dbManagerDropdown: 'dbManagerArrow'
        };

        const arrowId = arrowMap[id];
        if (arrowId) {
            const arrow = document.getElementById(arrowId);
            if (arrow) arrow.classList.toggle('rotated');
        }
    }
</script>