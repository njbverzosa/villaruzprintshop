<style>
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

    /* Hover effect for main nav items - NO MOVEMENT */
    .menu-nav .nav-item:hover {
        background: #eff6ff;
        color: #1e293b;
    }

    .menu-nav .nav-item:hover i {
        transform: scale(1.05);
    }

    /* Active state for main nav items */
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

    /* ===== GENERIC ACTIVE STATE FOR ALL DROPDOWN ITEMS ===== */
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

    /* Base hover for ALL dropdown items - NO MOVEMENT */
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

    /* ===== E-COMMERCE / OUTSIDE (Blue) ===== */
    .nav-dropdown-item.active_outside {
        background: #eff6ff;
        color: #2563eb;
        border-left: 3px solid #3b82f6;
    }

    .nav-dropdown-item.active_outside:hover {
        background: #dbeafe;
        color: #1e40af;
    }

    .nav-dropdown-item.active_outside i {
        color: #3b82f6;
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

    /* ===== CUSTOMERS (Blue) ===== */
    .nav-dropdown-item.active_customers {
        background: #eff6ff;
        color: #2563eb;
        border-left: 3px solid #3b82f6;
    }

    .nav-dropdown-item.active_customers:hover {
        background: #dbeafe;
        color: #1e40af;
    }

    .nav-dropdown-item.active_customers i {
        color: #3b82f6;
    }

    /* ===== DTR (Blue) ===== */
    .nav-dropdown-item.active_dtr {
        background: #eff6ff;
        color: #2563eb;
        border-left: 3px solid #3b82f6;
    }

    .nav-dropdown-item.active_dtr:hover {
        background: #dbeafe;
        color: #1e40af;
    }

    .nav-dropdown-item.active_dtr i {
        color: #3b82f6;
    }

    /* ===== JOESPH AI (Purple) ===== */
    .nav-dropdown-item.active_prompt {
        background: #eff6ff;
        color: #7c3aed;
        border-left: 3px solid #8b5cf6;
    }

    .nav-dropdown-item.active_prompt:hover {
        background: #ede9fe;
        color: #5b21b6;
    }

    .nav-dropdown-item.active_prompt i {
        color: #8b5cf6;
    }

    /* ===== SPREAD SHEET (Green) ===== */
    .nav-dropdown-item.active_spreadsheet {
        background: #eff6ff;
        color: #059669;
        border-left: 3px solid #10b981;
    }

    .nav-dropdown-item.active_spreadsheet:hover {
        background: #d1fae5;
        color: #065f46;
    }

    .nav-dropdown-item.active_spreadsheet i {
        color: #10b981;
    }

    /* ===== SETTINGS DROPDOWN - Keep settings toggle with gear icon ===== */
    #settingsDropdown .nav-dropdown-item {
        border-radius: 10px;
    }

    #settingsDropdown .nav-dropdown-item.active_database,
    #settingsDropdown .nav-dropdown-item.active_customers,
    #settingsDropdown .nav-dropdown-item.active_dtr,
    #settingsDropdown .nav-dropdown-item.active_prompt,
    #settingsDropdown .nav-dropdown-item.active_spreadsheet {
        border-radius: 10px 0 0 10px;
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
    <i class="fas fa-store"></i>
    <div class="user-greeting">Logged in as</div>
    <div class="user-name">
        <?php
        echo htmlspecialchars($user['user_name'] ?? 'User');
        ?>
    </div>
    <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
        <?php echo htmlspecialchars($user['acc_number'] ?? ''); ?>
    </div>
</div>

<div class="menu-nav">
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    // Store authorize_access in a PHP variable for conditional logic
    $authorizeAccess = isset($user['authorize_access']) ? (int) $user['authorize_access'] : 0;
    ?>

    <!-- Shop Link - Visible to ALL -->
    <a href="all_products.php" class="nav-item <?php echo $currentPage == 'all_products.php' ? 'shop' : ''; ?>">
        <i class="fas fa-store"></i>
        <span>Shop</span>
    </a>

    <!-- Orders Dropdown - Visible to ALL -->
    <?php
    // Check if any pending-related page is active
    $pendingActive = in_array($currentPage, ['pending_folder.php', 'pending_folder_with.php', 'pending_orders.php']);
    $paidActive = in_array($currentPage, ['paid_folder.php', 'paid_folder_with.php', 'paid_orders.php']);
    $outsideActive = in_array($currentPage, ['outside_folder.php', 'outside_orders.php']);
    $creditActive = in_array($currentPage, ['credit_folder.php', 'credit_folder_with.php', 'credit_orders.php']);

    // Add ALL order-related pages
    $ordersActive = in_array($currentPage, [
        'outside_folder.php',
        'pending_folder.php',
        'paid_folder.php',
        'credit_folder.php',
        'outside_orders.php',
        'pending_folder_with.php',
        'paid_folder_with.php',
        'pending_orders.php',
        'paid_orders.php',
        'credit_folder_with.php',
        'credit_orders.php'
    ]);
    ?>
    <div class="nav-dropdown">
        <div class="nav-dropdown-toggle" onclick="toggleDropdown('ordersDropdown')">
            <i class="fas fa-shopping-cart"></i>
            <span>Orders</span>
            <i class="fas fa-chevron-down dropdown-arrow <?php echo $ordersActive ? 'rotated' : ''; ?>"
                id="ordersArrow"></i>
        </div>
        <div class="nav-dropdown-menu <?php echo $ordersActive ? 'show' : ''; ?>" id="ordersDropdown">
            <!-- Pending - Visible to ALL -->
            <a href="pending_folder.php"
                class="nav-dropdown-item <?php echo $pendingActive ? 'active_pending' : ''; ?>">
                <i class="fas fa-clock"></i>
                <span>Pending</span>
            </a>
            <!-- Paid - Visible to ALL -->
            <a href="paid_folder.php" class="nav-dropdown-item <?php echo $paidActive ? 'active_paid' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                <span>Paid</span>
            </a>

            <!-- Credit - Only visible when authorize_access is 1 -->
            <?php if ($authorizeAccess == 1): ?>
                <a href="credit_folder.php" class="nav-dropdown-item <?php echo $creditActive ? 'active_credit' : ''; ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Credit</span>
                </a>
            <?php endif; ?>

            <!-- E-commerce (Outside) - Visible to ALL -->
            <a href="outside_folder.php"
                class="nav-dropdown-item <?php echo $outsideActive ? 'active_outside' : ''; ?>">
                <i class="fas fa-shopping-bag"></i>
                <span>E-commerce</span>
            </a>
        </div>
    </div>

    <!-- Purchase Order Dropdown - Visible to ALL -->
    <?php
    $poActive = in_array($currentPage, [
        'shop.php',
        'cart.php'
    ]);
    ?>
    <div class="nav-dropdown">
        <div class="nav-dropdown-toggle" onclick="toggleDropdown('addOrderDropdown')">
            <i class="fas fa-plus-circle"></i>
            <span>Purchase Order</span>
            <i class="fas fa-chevron-down dropdown-arrow <?php echo $poActive ? 'rotated' : ''; ?>"
                id="addOrderArrow"></i>
        </div>
        <div class="nav-dropdown-menu <?php echo $poActive ? 'show' : ''; ?>" id="addOrderDropdown">
            <a href="shop.php" class="nav-dropdown-item <?php echo $currentPage == 'shop.php' ? 'active_shop_po' : ''; ?>">
                <i class="fas fa-store"></i>
                <span>Shop</span>
            </a>
            <a href="cart.php" class="nav-dropdown-item <?php echo $currentPage == 'cart.php' ? 'active_cart_po' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Cart</span>
            </a>
        </div>
    </div>

    <!-- Settings Dropdown - Only visible when authorize_access is 0 -->
    <?php if ($authorizeAccess == 0): ?>
        <?php
        // Check if any settings page is active
        $settingsActive = in_array($currentPage, [
            'prompt_ai.php',
            'database_manager.php',
            'registered_customers.php',
            'dtr.php',
            'blank_spreadsheet.php'
        ]);
        ?>
        <div class="nav-dropdown">
            <div class="nav-dropdown-toggle" onclick="toggleDropdown('settingsDropdown')">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
                <i class="fas fa-chevron-down dropdown-arrow <?php echo $settingsActive ? 'rotated' : ''; ?>"
                    id="settingsArrow"></i>
            </div>
            <div class="nav-dropdown-menu <?php echo $settingsActive ? 'show' : ''; ?>" id="settingsDropdown">
                <a href="database_manager.php"
                    class="nav-dropdown-item <?php echo $currentPage == 'database_manager.php' ? 'active_database' : ''; ?>">
                    <i class="fas fa-database"></i>
                    <span>DB Manager</span>
                </a>
                <a href="registered_customers.php"
                    class="nav-dropdown-item <?php echo $currentPage == 'registered_customers.php' ? 'active_customers' : ''; ?>">
                    <i class="fas fa-user-friends"></i>
                    <span>Customers</span>
                </a>
                <!-- <a href="dtr.php" class="nav-dropdown-item <?php echo $currentPage == 'dtr.php' ? 'active_dtr' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>DTR</span>
                </a> -->
                <!-- <a href="prompt_ai.php"
                    class="nav-dropdown-item <?php echo $currentPage == 'prompt_ai.php' ? 'active_prompt' : ''; ?>">
                    <i class="fas fa-robot"></i>
                    <span>Joesph AI</span>
                </a> -->
                <!-- <a href="blank_spreadsheet.php"
                    class="nav-dropdown-item <?php echo $currentPage == 'blank_spreadsheet.php' ? 'active_spreadsheet' : ''; ?>">
                    <i class="fas fa-table"></i>
                    <span>Spread Sheet</span>
                </a> -->
            </div>
        </div>
    <?php endif; ?>

    <!-- Logout - Visible to ALL -->
    <a href="closed.php" class="nav-item <?php echo $currentPage == 'closed.php' ? 'active' : ''; ?>">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</div>