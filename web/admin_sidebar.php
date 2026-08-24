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

    .nav-dropdown-item.active_pending {
        background: #eff6ff;
        color: orange;
        border-left: 3px solid orange;
    }

    .nav-dropdown-item.active_credit {
        background: #eff6ff;
        color: red;
        border-left: 3px solid red;
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
        <div class="user-name"><?php echo $userData['user_name']; ?></div>
    </div>
    <div class="menu-nav">
        <?php
        $currentPage = basename($_SERVER['PHP_SELF']);
        ?>


        <a href="all_products.php" class="nav-item <?php echo $currentPage == 'all_products.php' ? 'shop' : ''; ?>">
            <i class="fas fa-shop"></i>
            <span>Shop</span>
        </a>

        <!-- <a href="sold_products.php" class="nav-item <?php echo $currentPage == 'sold_products.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Sold</span>
        </a> -->


        <!-- Orders Dropdown (Fixed) -->
        <div class="nav-dropdown">
            <div class="nav-dropdown-toggle" onclick="toggleDropdown('ordersDropdown')">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
                <i class="fas fa-chevron-down dropdown-arrow" id="ordersArrow"></i>
            </div>
            <div class="nav-dropdown-menu" id="ordersDropdown">
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
            </div>
        </div>

        <!-- Add Order Dropdown (Fixed) -->
        <div class="nav-dropdown">
            <div class="nav-dropdown-toggle" onclick="toggleDropdown('addOrderDropdown')">
                <i class="fas fa-plus-circle"></i>
                <span>Purchase Order</span>
                <i class="fas fa-chevron-down dropdown-arrow" id="addOrderArrow"></i>
            </div>
            <div class="nav-dropdown-menu" id="addOrderDropdown">
                <a href="shop.php" class="nav-dropdown-item <?php echo $currentPage == 'shop.php' ? 'active' : ''; ?>">
                    <i class="fas fa-store"></i>
                    <span>Shop</span>
                </a>
                <a href="cart.php" class="nav-dropdown-item <?php echo $currentPage == 'cart.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-basket"></i>
                    <span>Cart</span>
                </a>
            </div>
        </div>

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
        // Get the corresponding arrow by replacing 'Dropdown' with 'Arrow' in the ID
        const arrowId = dropdownId.replace('Dropdown', 'Arrow');
        const arrow = document.getElementById(arrowId);

        if (dropdown && arrow) {
            // Close other dropdowns first
            const allDropdowns = document.querySelectorAll('.nav-dropdown-menu');
            const allArrows = document.querySelectorAll('.dropdown-arrow');

            allDropdowns.forEach(d => {
                if (d.id !== dropdownId && d.classList.contains('show')) {
                    d.classList.remove('show');
                }
            });
            allArrows.forEach(a => {
                if (a.id !== arrowId && a.classList.contains('rotated')) {
                    a.classList.remove('rotated');
                }
            });

            // Toggle the clicked dropdown
            dropdown.classList.toggle('show');
            arrow.classList.toggle('rotated');
        }
    }
</script>