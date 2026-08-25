<!-- Sidebar Menu -->
<style>
    /* Dropdown Menu Styles */
    .nav-dropdown {
        margin-bottom: 8px;
    }

    .nav-dropdown-toggle {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 14px 12px;
        border-radius: 14px;
        color: #475569;
        text-decoration: none;
        transition: all 0.2s;
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

    .nav-dropdown-menu {
        display: none;
        margin-left: 35px;
        margin-top: 5px;
        margin-bottom: 5px;
        border-left: 2px solid #e2e8f0;
    }

    .nav-dropdown-menu.show {
        display: block;
    }

    .nav-dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 10px;
        color: #475569;
        text-decoration: none;
        transition: all 0.2s;
        font-size: 14px;
    }

    .nav-dropdown-item i {
        width: 20px;
        font-size: 14px;
        color: #3b82f6;
    }

    .nav-dropdown-item span {
        font-size: 13px;
        font-weight: 500;
    }

    .nav-dropdown-item:hover {
        background: #eff6ff;
        color: #1e293b;
    }

    .nav-dropdown-item.active {
        background: #eff6ff;
        color: #3b82f6;
        border-left: 3px solid #3b82f6;
    }

    .nav-dropdown-item.active_paid {
        background: #eff6ff;
        color: green;
        border-left: 3px solid green;
    }

    .nav-dropdown-item.active_paid:hover {
        background: #d1fae5;
        color: #065f46;
    }

    .nav-dropdown-item.active_pending {
        background: #eff6ff;
        color: #d97706;
        border-left: 3px solid #d97706;
    }

    .nav-dropdown-item.active_pending:hover {
        background: #fef3c7;
        color: #92400e;
    }

    .nav-dropdown-item.active_outside {
        background: #eff6ff;
        color: #3b82f6;
        border-left: 3px solid #3b82f6;
    }

    .nav-dropdown-item.active_outside:hover {
        background: #dbeafe;
        color: #1e40af;
    }

    .nav-dropdown-item.active_credit {
        background: #eff6ff;
        color: #7c3aed;
        border-left: 3px solid #7c3aed;
    }

    .nav-dropdown-item.active_credit:hover {
        background: #ede9fe;
        color: #5b21b6;
    }

    .nav-dropdown-item.warehouse {
        background: #eff6ff;
        color: #3b82f6;
        border-left: 3px solid #3b82f6;
    }

    .nav-dropdown-item.shop {
        background: #eff6ff;
        color: #3b82f6;
        border-left: 3px solid #3b82f6;
    }

    .nav-item {
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

    .nav-item i {
        width: 24px;
        font-size: 20px;
        color: #3b82f6;
    }

    .nav-item span {
        font-size: 15px;
        font-weight: 500;
    }

    .nav-item:hover {
        background: #eff6ff;
        color: #1e293b;
    }

    .nav-item.active {
        background: #eff6ff;
        color: #3b82f6;
        border-left: 3px solid #3b82f6;
    }

    .nav-item.shop {
        background: #eff6ff;
        color: #3b82f6;
        border-left: 3px solid #3b82f6;
    }

    /* Access Modal Styles */
    .access-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 2000;
        justify-content: center;
        align-items: center;
    }

    .access-modal-content {
        background: #ffffff;
        border-radius: 16px;
        width: 90%;
        max-width: 400px;
        animation: modalFadeIn 0.3s ease;
        overflow: hidden;
    }

    @keyframes modalFadeIn {
        from {
            transform: scale(0.95);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    .access-modal-header {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        padding: 20px;
        text-align: center;
        color: white;
    }

    .access-modal-header i {
        font-size: 40px;
        margin-bottom: 10px;
        display: block;
    }

    .access-modal-header h3 {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }

    .access-modal-body {
        padding: 25px;
    }

    .access-modal-body p {
        color: #475569;
        font-size: 14px;
        margin-bottom: 15px;
        text-align: center;
    }

    .access-input {
        width: 100%;
        padding: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        text-align: center;
        letter-spacing: 2px;
    }

    .access-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .access-error {
        color: #ef4444;
        font-size: 12px;
        margin-top: 8px;
        text-align: center;
        display: none;
    }

    .access-modal-footer {
        padding: 20px;
        display: flex;
        gap: 10px;
        border-top: 1px solid #e2e8f0;
    }

    .access-btn {
        flex: 1;
        padding: 10px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .confirm-btn {
        background: #3b82f6;
        color: white;
    }

    .confirm-btn:hover {
        background: #2563eb;
        transform: translateY(-1px);
    }

    .cancel-btn {
        background: #e2e8f0;
        color: #475569;
    }

    .cancel-btn:hover {
        background: #cbd5e1;
    }
</style>

<div class="side-menu" id="sideMenu">
    <div class="menu-header">
        <i class="fas fa-store"></i>
        <div class="user-greeting">Logged in as</div>
        <div class="user-name">
            <?php 
            // Use f_name from user data, fallback to 'User' if not set
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
        ?>

        <!-- Shop Link -->
        <a href="all_products.php" class="nav-item <?php echo $currentPage == 'all_products.php' ? 'shop' : ''; ?>">
            <i class="fas fa-store"></i>
            <span>Shop</span>
        </a>

        <!-- Orders Dropdown -->
        <?php 
        $ordersActive = in_array($currentPage, ['outside_folder.php', 'pending_folder.php', 'paid_folder.php', 'credit_folder.php', 'outside_orders.php', 'pending_folder_with.php', 'paid_folder_with.php', 'credit_folder_with.php']);
        ?>
        <div class="nav-dropdown">
            <div class="nav-dropdown-toggle" onclick="toggleDropdown('ordersDropdown')">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
                <i class="fas fa-chevron-down dropdown-arrow <?php echo $ordersActive ? 'rotated' : ''; ?>" id="ordersArrow"></i>
            </div>
            <div class="nav-dropdown-menu <?php echo $ordersActive ? 'show' : ''; ?>" id="ordersDropdown">
                
                <a href="pending_folder.php"
                    class="nav-dropdown-item <?php echo $currentPage == 'pending_folder.php' ? 'active_pending' : ''; ?>">
                    <i class="fas fa-clock"></i>
                    <span>Pending</span>
                </a>
                <a href="paid_folder.php"
                    class="nav-dropdown-item <?php echo $currentPage == 'paid_folder.php' ? 'active_paid' : ''; ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Paid</span>
                </a>
                <a href="credit_folder.php"
                    class="nav-dropdown-item <?php echo $currentPage == 'credit_folder.php' ? 'active_credit' : ''; ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Credit</span>
                </a>
            </div>
        </div>

        <!-- Purchase Order Dropdown -->
        <?php 
        $poActive = in_array($currentPage, ['shop.php', 'cart.php']);
        ?>
        <div class="nav-dropdown">
            <div class="nav-dropdown-toggle" onclick="toggleDropdown('addOrderDropdown')">
                <i class="fas fa-plus-circle"></i>
                <span>Purchase Order</span>
                <i class="fas fa-chevron-down dropdown-arrow <?php echo $poActive ? 'rotated' : ''; ?>" id="addOrderArrow"></i>
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

        <!-- Logout -->
        <a href="closed.php" class="nav-item <?php echo $currentPage == 'closed.php' ? 'active' : ''; ?>">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<script>
    let pendingUrl = '';

    function toggleDropdown(dropdownId) {
        const dropdown = document.getElementById(dropdownId);
        const arrowId = dropdownId.replace('Dropdown', 'Arrow');
        const arrow = document.getElementById(arrowId);

        if (dropdown && arrow) {
            // Toggle the clicked dropdown
            dropdown.classList.toggle('show');
            arrow.classList.toggle('rotated');
        }
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const isDropdownToggle = event.target.closest('.nav-dropdown-toggle');
        const isDropdownMenu = event.target.closest('.nav-dropdown-menu');
        
        if (!isDropdownToggle && !isDropdownMenu) {
            // Don't close if the dropdown has an active child
            const activeDropdown = document.querySelector('.nav-dropdown-menu.show');
            if (activeDropdown && activeDropdown.querySelector('.active, .active_paid, .active_pending, .active_outside, .active_credit')) {
                return;
            }
            
            document.querySelectorAll('.nav-dropdown-menu.show').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
            document.querySelectorAll('.dropdown-arrow.rotated').forEach(arrow => {
                arrow.classList.remove('rotated');
            });
        }
    });

    // Initialize dropdowns - keep open if child is active
    document.addEventListener('DOMContentLoaded', function() {
        // Check if any dropdown has an active child
        document.querySelectorAll('.nav-dropdown-menu').forEach(menu => {
            const activeItem = menu.querySelector('.active, .active_paid, .active_pending, .active_outside, .active_credit');
            if (activeItem) {
                // Find the parent dropdown
                const dropdown = menu.closest('.nav-dropdown');
                if (dropdown) {
                    const menuId = menu.id;
                    const arrowId = menuId.replace('Dropdown', 'Arrow');
                    const arrow = document.getElementById(arrowId);
                    
                    menu.classList.add('show');
                    if (arrow) {
                        arrow.classList.add('rotated');
                    }
                }
            }
        });
    });

    console.log('📱 Sidebar menu loaded');
    console.log('👤 User: <?php echo htmlspecialchars($user['f_name'] ?? $user['user_name'] ?? 'User'); ?>');
</script>