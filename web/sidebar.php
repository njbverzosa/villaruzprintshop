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
    
    // Add ALL order-related pages including admin.php
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
            <a href="paid_folder.php"
                class="nav-dropdown-item <?php echo $paidActive ? 'active_paid' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                <span>Paid</span>
            </a>
           
            <!-- Credit - Only visible when authorize_access is 1 -->
            <?php if ($authorizeAccess == 1): ?>
                <a href="credit_folder.php"
                    class="nav-dropdown-item <?php echo $creditActive ? 'active_credit' : ''; ?>">
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
        'cart.php',
        'admin.php'  // ADDED - Purchase Order dropdown will stay open on admin.php
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
            <a href="shop.php" class="nav-dropdown-item <?php echo $currentPage == 'shop.php' ? 'active' : ''; ?>">
                <i class="fas fa-store"></i>
                <span>Shop</span>
            </a>
            <a href="cart.php" class="nav-dropdown-item <?php echo $currentPage == 'cart.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Cart</span>
            </a>
        </div>
    </div>

    <!-- Settings Dropdown - Only visible when authorize_access is 0 -->
    <?php if ($authorizeAccess == 0): ?>
        <?php
        $settingsActive = in_array($currentPage, ['admin.php', 'database_manager.php', 'registered_customers.php', 'dtr.php']);
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
                    class="nav-dropdown-item <?php echo $currentPage == 'database_manager.php' ? 'active' : ''; ?>">
                    <i class="fas fa-database"></i>
                    <span>DB Manager</span>
                </a>
                <a href="registered_customers.php"
                    class="nav-dropdown-item <?php echo $currentPage == 'registered_customers.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-friends"></i>
                    <span>Customers</span>
                </a>
                <a href="dtr.php" class="nav-dropdown-item <?php echo $currentPage == 'dtr.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>DTR</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Logout - Visible to ALL -->
    <a href="closed.php" class="nav-item <?php echo $currentPage == 'closed.php' ? 'active' : ''; ?>">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</div>