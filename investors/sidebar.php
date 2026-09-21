<style>
    .logo img {
        width: 245px;
        height: auto;
        object-fit: contain;
        border-radius: 5px;
    }

    /* ========== SIDEBAR NAVIGATION ========== */

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

    /* ===== DROPDOWN ITEMS ===== */
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
        <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/logo.jpeg" alt="Villaruz Print Shop Logo">
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
    ?>

    <!-- Shop Link - Visible to ALL -->
    <a href="investors_product.php" class="nav-item <?php echo $currentPage == 'investors_product.php' ? 'shop' : ''; ?>">
        <i class="fas fa-store"></i>
        <span>Shop</span>
    </a>

    <!-- Orders Dropdown - Visible to ALL -->
    <?php
    // ✅ Paid pages (including the _with variant)
    $paidActive = in_array($currentPage, [
        'paid_folder.php',
        'paid_folder_with.php',
        'paid_orders.php'
    ]);

    // ✅ Orders dropdown expands if ANY of its children is active
    $ordersActive = $paidActive;
    ?>
    <div class="nav-dropdown">
        <div class="nav-dropdown-toggle" onclick="toggleDropdown('ordersDropdown')">
            <i class="fas fa-shopping-cart"></i>
            <span>Orders</span>
            <i class="fas fa-chevron-down dropdown-arrow <?php echo $ordersActive ? 'rotated' : ''; ?>"
                id="ordersArrow"></i>
        </div>
        <div class="nav-dropdown-menu <?php echo $ordersActive ? 'show' : ''; ?>" id="ordersDropdown">
            <a href="paid_folder.php" class="nav-dropdown-item <?php echo $paidActive ? 'active_paid' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                <span>Sales</span>
            </a>
        </div>
    </div>

    <!-- Logout - Visible to ALL -->
    <a href="closed.php" class="nav-item <?php echo $currentPage == 'closed.php' ? 'active' : ''; ?>">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</div>