<style>
    /* ============================================================
       SIDEBAR — STATIC GRADIENT (MOBILE-OPTIMIZED)
       No backdrop-filter, no filter: blur(), no animations.
       Painted once, scrolls on GPU without repaint.
       ============================================================ */

    .side-menu {
        position: relative;
        overflow: hidden;
        background-color: #f4f6fb;
        /* Layered static gradients — no animation, no blur filter */
        background-image:
            radial-gradient(ellipse 420px 320px at 10% 4%,  rgba(26, 42, 108, 0.22), transparent 60%),
            radial-gradient(ellipse 420px 320px at 92% 8%,  rgba(59, 111, 212, 0.20), transparent 60%),
            radial-gradient(ellipse 500px 400px at 90% 45%, rgba(99, 102, 241, 0.14), transparent 60%),
            radial-gradient(ellipse 420px 320px at 12% 78%, rgba(59, 130, 246, 0.12), transparent 60%),
            radial-gradient(ellipse 500px 400px at 88% 100%, rgba(14, 165, 233, 0.14), transparent 60%),
            linear-gradient(160deg, #f4f6fb 0%, #eef2fb 40%, #eff6ff 70%, #f8fafc 100%);
        background-attachment: scroll; /* NOT fixed — avoids repaint on scroll */
        background-size: cover;
        background-repeat: no-repeat;
    }

    /* No blurred pseudo-elements, no infinite animation — GPU-friendly */

    /* ✅ Keep header + nav above the gradient */
    .menu-header,
    .menu-nav {
        position: relative;
        z-index: 1;
    }

    /* ---------- Logo ---------- */
    .logo {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 4px 0 10px;
    }

    .logo img {
        width: 220px;
        max-width: 100%;
        height: auto;
        object-fit: contain;
        border-radius: 12px;
        padding: 6px;
    }

    /* ---------- User Header (solid, not glassy) ---------- */
    .menu-header {
        background: rgba(255, 255, 255, 0.94); /* solid-ish, no blur */
        border-bottom: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
    }

    .user-name {
        margin-top: 14px;
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.01em;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        word-break: break-word;
    }

    .user-name::before {
        content: "\f007";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        font-size: 13px;
        color: #3b6fd4;
        padding: 6px 7px;
        flex-shrink: 0;
    }

    /* ---------- Nav Container ---------- */
    .menu-nav {
        padding: 16px 14px 24px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        overflow: visible;
    }

    /* ============================================================
       MAIN NAV ITEMS — solid backgrounds, no backdrop-filter
       ============================================================ */
    .menu-nav .nav-item {
        position: relative;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 14px;
        border-radius: 12px;
        color: #334155;
        text-decoration: none;
        transition: background 0.15s ease, color 0.15s ease;
        cursor: pointer;
        overflow: hidden;
        border: 1px solid transparent;
        /* GPU hint — cheap transform on tap */
        -webkit-tap-highlight-color: transparent;
    }

    .menu-nav .nav-item i {
        width: 22px;
        font-size: 18px;
        color: #64748b;
        transition: color 0.15s ease;
        text-align: center;
        flex-shrink: 0;
    }

    .menu-nav .nav-item span {
        font-size: 14.5px;
        font-weight: 500;
        letter-spacing: -0.005em;
    }

    .menu-nav .nav-item:hover {
        background: rgba(255, 255, 255, 0.75);
        color: #0f172a;
    }

    .menu-nav .nav-item:hover i {
        color: #3b6fd4;
    }

    .menu-nav .nav-item.active,
    .menu-nav .nav-item.shop {
        background: #ffffff;
        color: #1a2a6c;
        font-weight: 600;
        border-color: #e2e8f0;
        box-shadow: 0 2px 6px rgba(26, 42, 108, 0.10);
    }

    .menu-nav .nav-item.active::before,
    .menu-nav .nav-item.shop::before {
        content: "";
        position: absolute;
        left: 0;
        top: 20%;
        bottom: 20%;
        width: 3px;
        background: linear-gradient(180deg, #1a2a6c, #3b6fd4);
        border-radius: 0 3px 3px 0;
    }

    .menu-nav .nav-item.active i,
    .menu-nav .nav-item.shop i {
        color: #1a2a6c;
    }

    /* ============================================================
       DROPDOWN TOGGLES
       ============================================================ */
    .nav-dropdown-toggle {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 14px;
        border-radius: 12px;
        color: #334155;
        text-decoration: none;
        transition: background 0.15s ease, color 0.15s ease;
        cursor: pointer;
        user-select: none;
        border: 1px solid transparent;
        -webkit-tap-highlight-color: transparent;
    }

    .nav-dropdown-toggle:hover {
        background: rgba(255, 255, 255, 0.75);
        color: #0f172a;
    }

    .nav-dropdown-toggle i:first-child {
        width: 22px;
        font-size: 18px;
        color: #64748b;
        transition: color 0.15s ease;
        text-align: center;
        flex-shrink: 0;
    }

    .nav-dropdown-toggle:hover i:first-child {
        color: #3b6fd4;
    }

    .nav-dropdown-toggle span {
        flex: 1;
        font-size: 14.5px;
        font-weight: 500;
        letter-spacing: -0.005em;
    }

    .dropdown-arrow {
        font-size: 11px !important;
        color: #94a3b8 !important;
        width: auto !important;
        transition: transform 0.2s ease, color 0.15s ease;
        padding: 4px;
        border-radius: 6px;
    }

    .dropdown-arrow.rotated {
        transform: rotate(180deg);
    }

    .nav-dropdown-toggle.active-dropdown {
        background: #ffffff;
        color: #1a2a6c;
        font-weight: 600;
        border-color: #e2e8f0;
        box-shadow: 0 2px 6px rgba(26, 42, 108, 0.10);
    }

    .nav-dropdown-toggle.active-dropdown i:first-child {
        color: #1a2a6c;
    }

    .nav-dropdown-toggle.active-dropdown .dropdown-arrow {
        color: #3b6fd4 !important;
    }

    /* ============================================================
       DROPDOWN MENU CONTAINER — solid bg, no blur
       ============================================================ */
    .nav-dropdown-menu {
        display: none;
        margin: 6px 0 8px 22px;
        padding: 6px;
        border-left: none;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
        animation: dropdownSlide 0.18s ease;
    }

    .nav-dropdown-menu.show {
        display: block;
    }

    @keyframes dropdownSlide {
        from {
            opacity: 0;
            transform: translateY(-4px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ============================================================
       DROPDOWN ITEMS
       ============================================================ */
    .nav-dropdown-item {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 9px 12px;
        border-radius: 10px;
        color: #475569;
        text-decoration: none;
        transition: background 0.15s ease, color 0.15s ease;
        font-size: 13.5px;
        cursor: pointer;
        margin-bottom: 2px;
        -webkit-tap-highlight-color: transparent;
    }

    .nav-dropdown-item i {
        width: 18px;
        font-size: 13px;
        color: #64748b;
        transition: color 0.15s ease;
        text-align: center;
        flex-shrink: 0;
    }

    .nav-dropdown-item span {
        font-size: 13.5px;
        font-weight: 500;
        letter-spacing: -0.005em;
    }

    /* ============================================================
       CHILD ACTIVE STATES
       ============================================================ */
    .nav-dropdown-item.active {
        color: #1a2a6c;
        font-weight: 600;
    }
    .nav-dropdown-item.active::before {
        content: "";
        position: absolute;
        left: 0; top: 18%; bottom: 18%;
        width: 5px;
        background: #3b6fd4;
        border-radius: 0 2px 2px 0;
    }
    .nav-dropdown-item.active i { color: #3b6fd4; }

    .nav-dropdown-item.active_pending { color: #b45309; font-weight: 600; }
    .nav-dropdown-item.active_pending::before {
        content: ""; position: absolute; left: 0; top: 18%; bottom: 18%;
        width: 4px; background: #f59e0b; border-radius: 0 2px 2px 0;
    }
    .nav-dropdown-item.active_pending i { color: #f59e0b; }

    .nav-dropdown-item.active_paid { color: #047857; font-weight: 600; }
    .nav-dropdown-item.active_paid::before {
        content: ""; position: absolute; left: 0; top: 18%; bottom: 18%;
        width: 4px; background: #10b981; border-radius: 0 2px 2px 0;
    }
    .nav-dropdown-item.active_paid i { color: #10b981; }

    .nav-dropdown-item.active_credit { color: #b91c1c; font-weight: 600; }
    .nav-dropdown-item.active_credit::before {
        content: ""; position: absolute; left: 0; top: 18%; bottom: 18%;
        width: 4px; background: #ef4444; border-radius: 0 2px 2px 0;
    }
    .nav-dropdown-item.active_credit i { color: #ef4444; }

    .nav-dropdown-item.active_shop_po { color: #1d4ed8; font-weight: 600; }
    .nav-dropdown-item.active_shop_po::before {
        content: ""; position: absolute; left: 0; top: 18%; bottom: 18%;
        width: 4px; background: #3b82f6; border-radius: 0 2px 2px 0;
    }
    .nav-dropdown-item.active_shop_po i { color: #3b82f6; }

    .nav-dropdown-item.active_cart_po { color: #b45309; font-weight: 600; }
    .nav-dropdown-item.active_cart_po::before {
        content: ""; position: absolute; left: 0; top: 18%; bottom: 18%;
        width: 4px; background: #f59e0b; border-radius: 0 2px 2px 0;
    }
    .nav-dropdown-item.active_cart_po i { color: #f59e0b; }

    .nav-dropdown-item.active_customers { color: #0f766e; font-weight: 600; }
    .nav-dropdown-item.active_customers::before {
        content: ""; position: absolute; left: 0; top: 18%; bottom: 18%;
        width: 4px; background: #14b8a6; border-radius: 0 2px 2px 0;
    }
    .nav-dropdown-item.active_customers i { color: #14b8a6; }

    .nav-dropdown-item.active_database { color: #1d4ed8; font-weight: 600; }
    .nav-dropdown-item.active_database::before {
        content: ""; position: absolute; left: 0; top: 18%; bottom: 18%;
        width: 4px; background: #3b82f6; border-radius: 0 2px 2px 0;
    }
    .nav-dropdown-item.active_database i { color: #3b82f6; }

    .nav-dropdown-item.active_download_images { color: #6d28d9; font-weight: 600; }
    .nav-dropdown-item.active_download_images::before {
        content: ""; position: absolute; left: 0; top: 18%; bottom: 18%;
        width: 4px; background: #8b5cf6; border-radius: 0 2px 2px 0;
    }
    .nav-dropdown-item.active_download_images i { color: #8b5cf6; }

    .nav-dropdown-item.active_investors { color: #be185d; font-weight: 600; }
    .nav-dropdown-item.active_investors::before {
        content: ""; position: absolute; left: 0; top: 18%; bottom: 18%;
        width: 4px; background: #ec4899; border-radius: 0 2px 2px 0;
    }
    .nav-dropdown-item.active_investors i { color: #ec4899; }

    .nav-dropdown-item.active_products_source { color: #1a2a6c; font-weight: 600; }
    .nav-dropdown-item.active_products_source::before {
        content: ""; position: absolute; left: 0; top: 18%; bottom: 18%;
        width: 4px; background: #3b6fd4; border-radius: 0 2px 2px 0;
    }
    .nav-dropdown-item.active_products_source i { color: #3b6fd4; }

    /* ============================================================
       CLOSE BUTTON (mobile) — solid, no blur
       ============================================================ */
    .sidebar-close-btn {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 36px;
        height: 36px;
        display: none;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: #475569;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
        z-index: 20;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
        -webkit-tap-highlight-color: transparent;
    }

    .sidebar-close-btn:hover {
        background: #f8fafc;
        color: #0f172a;
    }

    @media (max-width: 768px) {
        .sidebar-close-btn {
            display: flex;
        }
    }

    /* ============================================================
       🔒 SYSTEM MANAGER LOCK ICON
       ============================================================ */
    .sm-lock-icon {
        font-size: 11px !important;
        color: #94a3b8 !important;
        width: auto !important;
        margin-left: 2px;
        transition: color 0.15s ease;
    }

    .nav-dropdown-toggle.sm-unlocked .sm-lock-icon {
        color: #10b981 !important;
    }

    /* ============================================================
       🔐 PIN MODAL — solid overlay, no backdrop-filter
       ============================================================ */
    .pin-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.18s ease, visibility 0.18s ease;
        padding: 20px;
    }

    .pin-modal-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    .pin-modal {
        background: #ffffff;
        border-radius: 20px;
        width: 100%;
        max-width: 380px;
        box-shadow:
            0 20px 60px rgba(15, 23, 42, 0.3),
            0 4px 12px rgba(15, 23, 42, 0.1);
        overflow: hidden;
        transform: translateY(10px) scale(0.97);
        transition: transform 0.22s ease;
        position: relative;
    }

    .pin-modal-overlay.show .pin-modal {
        transform: translateY(0) scale(1);
    }

    .pin-modal-header {
        padding: 22px 22px 0;
        text-align: center;
        position: relative;
        z-index: 1;
    }

    .pin-modal-icon {
        width: 56px;
        height: 56px;
        margin: 0 auto 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #1a2a6c 0%, #3b6fd4 100%);
        border-radius: 50%;
        color: #ffffff;
        font-size: 22px;
        box-shadow: 0 8px 20px rgba(26, 42, 108, 0.35);
    }

    .pin-modal-title {
        font-size: 17px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .pin-modal-subtitle {
        font-size: 13px;
        color: #64748b;
        line-height: 1.5;
    }

    .pin-modal-body {
        padding: 20px 22px 8px;
        position: relative;
        z-index: 1;
    }

    .pin-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .pin-input {
        width: 100%;
        padding: 14px 46px 14px 16px;
        font-size: 20px;
        font-weight: 700;
        letter-spacing: 0.5em;
        text-align: center;
        color: #0f172a;
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        outline: none;
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
        font-family: 'Poppins', monospace;
        box-sizing: border-box;
    }

    .pin-input::placeholder {
        letter-spacing: 0.2em;
        color: #cbd5e1;
        font-size: 14px;
    }

    .pin-input:focus {
        border-color: #3b6fd4;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(59, 111, 212, 0.14);
    }

    .pin-input.error {
        border-color: #ef4444;
        background: #fef2f2;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12);
        animation: pinShake 0.3s ease;
    }

    .pin-input.success {
        border-color: #10b981;
        background: #ecfdf5;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
    }

    @keyframes pinShake {
        0%, 100% { transform: translateX(0); }
        20%      { transform: translateX(-6px); }
        40%      { transform: translateX(6px); }
        60%      { transform: translateX(-4px); }
        80%      { transform: translateX(4px); }
    }

    .pin-toggle-visibility {
        position: absolute;
        right: 12px;
        background: transparent;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        font-size: 15px;
        padding: 6px;
        border-radius: 8px;
        transition: color 0.15s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        -webkit-tap-highlight-color: transparent;
    }

    .pin-toggle-visibility:hover {
        color: #1a2a6c;
    }

    .pin-error-message {
        display: none;
        align-items: center;
        gap: 8px;
        margin-top: 12px;
        padding: 10px 12px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 10px;
        color: #b91c1c;
        font-size: 12.5px;
        font-weight: 500;
    }

    .pin-error-message.show {
        display: flex;
    }

    .pin-error-message i {
        font-size: 13px;
        flex-shrink: 0;
    }

    .pin-modal-footer {
        padding: 16px 22px 22px;
        display: flex;
        gap: 10px;
        position: relative;
        z-index: 1;
    }

    .pin-btn {
        flex: 1;
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s ease, box-shadow 0.15s ease;
        border: none;
        font-family: inherit;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        -webkit-tap-highlight-color: transparent;
    }

    .pin-btn-cancel {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .pin-btn-cancel:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .pin-btn-confirm {
        background: linear-gradient(135deg, #1a2a6c 0%, #3b6fd4 100%);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(26, 42, 108, 0.35);
    }

    .pin-btn-confirm:hover {
        background: linear-gradient(135deg, #0f1c4d 0%, #2a4d9b 100%);
    }

    .pin-btn-confirm:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* ============================================================
       MOBILE PERFORMANCE — disable animations on small screens
       ============================================================ */
    @media (max-width: 768px) {
        /* Drop the only remaining animation on mobile */
        .nav-dropdown-menu {
            animation: none;
        }
        .pin-input.error {
            animation: none;
        }
        /* Faster transitions */
        .menu-nav .nav-item,
        .nav-dropdown-toggle,
        .nav-dropdown-item {
            transition-duration: 0.1s;
        }
    }

    /* ============================================================
       REDUCED MOTION — respect system preference
       ============================================================ */
    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation: none !important;
            transition-duration: 0.01ms !important;
        }
    }
</style>

<button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close sidebar">
    <i class="fas fa-arrow-left"></i>
</button>

<div class="menu-header">
    <div class="logo">
        <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/Villaruz Print Shop Logo.png"
            alt="Villaruz Print Shop Logo" loading="lazy" decoding="async">
    </div>

    <div class="user-name">
        <?php
        echo htmlspecialchars($user['user_name'] ?? 'User');
        ?> — <?php echo htmlspecialchars($user['acc_number'] ?? ''); ?>
    </div>
</div>

<div class="menu-nav">
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    $authorizeAccess = isset($user['authorize_access']) ? (int) $user['authorize_access'] : 0;

    $pendingActive = in_array($currentPage, ['pending_folder.php', 'pending_folder_with.php', 'pending_orders.php']);
    $paidActive = in_array($currentPage, ['paid_folder.php', 'paid_folder_with.php', 'paid_orders.php']);
    $creditActive = in_array($currentPage, ['credit_folder.php', 'credit_folder_with.php', 'credit_orders.php']);

    $ordersActive = $pendingActive || $paidActive || $creditActive;

    $customersActive = in_array($currentPage, [
        'registered_investors.php',
        'registered_customers.php',
        'chat_view.php'
    ]);
    $sourceActive = in_array($currentPage, ['investors.php', 'investors_products.php']);

    $smPages = ['database_manager.php', 'download_images.php'];
    $dbManagerActive = in_array($currentPage, $smPages);

    // ==========================================
    // ✅ CART VISIBILITY — checked against cart table
    // ==========================================
    $currentAccNumber = (string) ($user['acc_number'] ?? '');

    $showCart = false;
    if ($currentAccNumber !== '') {
        try {
            $cartStmt = $pdo->prepare("SELECT 1 FROM cart WHERE acc_number = ? LIMIT 1");
            $cartStmt->execute([$currentAccNumber]);
            $showCart = (bool) $cartStmt->fetchColumn();
        } catch (PDOException $e) {
            $showCart = false;
        }
    }
    ?>

    <a href="shop.php" class="nav-item <?php echo $currentPage == 'shop.php' ? 'active' : ''; ?>">
        <i class="fas fa-store"></i>
        <span>Shop</span>
    </a>

    <?php if ($showCart): ?>
        <a href="cart.php" class="nav-item <?php echo $currentPage == 'cart.php' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Cart</span>
        </a>
    <?php endif; ?>

    <div class="nav-dropdown">
        <div class="nav-dropdown-toggle <?php echo $ordersActive ? 'active-dropdown' : ''; ?>"
            onclick="toggleDropdown('ordersDropdown')">
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

    <div class="nav-dropdown">
        <div class="nav-dropdown-toggle <?php echo $sourceActive ? 'active-dropdown' : ''; ?>"
            onclick="toggleDropdown('sourceDropdown')">
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

    <?php if (in_array($authorizeAccess, [0, 2], true)): ?>
        <div class="nav-dropdown">
            <div class="nav-dropdown-toggle <?php echo $customersActive ? 'active-dropdown' : ''; ?>"
                onclick="toggleDropdown('customersDropdown')">
                <i class="fas fa-user-friends"></i>
                <span>System User</span>
                <i class="fas fa-chevron-down dropdown-arrow <?php echo $customersActive ? 'rotated' : ''; ?>"
                    id="customersArrow"></i>
            </div>

            <div class="nav-dropdown-menu <?php echo $customersActive ? 'show' : ''; ?>" id="customersDropdown">
                <a href="registered_investors.php"
                    class="nav-dropdown-item <?php echo $currentPage == 'registered_investors.php' ? 'active_investors' : ''; ?>">
                    <i class="fas fa-user-tie"></i>
                    <span>Investors</span>
                </a>

                <a href="registered_customers.php"
                    class="nav-dropdown-item <?php echo in_array($currentPage, ['registered_customers.php', 'chat_view.php']) ? 'active_customers' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>Customers</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($authorizeAccess == 0): ?>
        <div class="nav-dropdown">
            <div class="nav-dropdown-toggle <?php echo $dbManagerActive ? 'active-dropdown' : ''; ?>" id="smToggle"
                onclick="handleSystemManagerClick(event)">
                <i class="fas fa-database"></i>
                <span>System Manager</span>
                <i class="fas fa-lock sm-lock-icon" id="smLockIcon"></i>
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

    <a href="closed.php" class="nav-item <?php echo $currentPage == 'closed.php' ? 'active' : ''; ?>">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</div>

<!-- 🔐 PIN MODAL -->
<div class="pin-modal-overlay" id="pinModalOverlay" aria-hidden="true">
    <div class="pin-modal" role="dialog" aria-modal="true" aria-labelledby="pinModalTitle">
        <div class="pin-modal-header">
            <div class="pin-modal-icon">
                <i class="fas fa-shield-halved"></i>
            </div>
            <div class="pin-modal-title" id="pinModalTitle">System Manager Access</div>
            <div class="pin-modal-subtitle">
                Enter your PIN to unlock the System Manager tools.
            </div>
        </div>

        <div class="pin-modal-body">
            <div class="pin-input-wrapper">
                <input type="password" id="pinInput" class="pin-input" placeholder="••••" inputmode="numeric"
                    autocomplete="off" maxlength="12">
                <button type="button" class="pin-toggle-visibility" id="pinToggleVisibility"
                    aria-label="Toggle PIN visibility">
                    <i class="fas fa-eye" id="pinEyeIcon"></i>
                </button>
            </div>

            <div class="pin-error-message" id="pinErrorMessage">
                <i class="fas fa-circle-exclamation"></i>
                <span id="pinErrorText">Incorrect PIN. Please try again.</span>
            </div>
        </div>

        <div class="pin-modal-footer">
            <button type="button" class="pin-btn pin-btn-cancel" id="pinCancelBtn">Cancel</button>
            <button type="button" class="pin-btn pin-btn-confirm" id="pinConfirmBtn">
                <i class="fas fa-unlock"></i>
                Unlock
            </button>
        </div>
    </div>
</div>

<script>
    const SYSTEM_MANAGER_PIN = '0022';
    const SM_UNLOCK_KEY = 'sm_unlocked_session';

    const SM_PAGES = ['database_manager.php', 'download_images.php'];
    const CURRENT_PAGE = <?php echo json_encode($currentPage); ?>;

    const pinOverlay = document.getElementById('pinModalOverlay');
    const pinInput = document.getElementById('pinInput');
    const pinConfirmBtn = document.getElementById('pinConfirmBtn');
    const pinCancelBtn = document.getElementById('pinCancelBtn');
    const pinToggleBtn = document.getElementById('pinToggleVisibility');
    const pinEyeIcon = document.getElementById('pinEyeIcon');
    const pinErrorMessage = document.getElementById('pinErrorMessage');
    const pinErrorText = document.getElementById('pinErrorText');
    const smToggle = document.getElementById('smToggle');
    const smLockIcon = document.getElementById('smLockIcon');
    const dbManagerDropdown = document.getElementById('dbManagerDropdown');
    const dbManagerArrow = document.getElementById('dbManagerArrow');

    let pinModalOpen = false;
    let isSubmitting = false;
    let autoSubmitTimer = null;

    function isUnlocked() {
        try {
            return sessionStorage.getItem(SM_UNLOCK_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function setUnlockedUI(unlocked) {
        if (smLockIcon) {
            smLockIcon.classList.toggle('fa-lock', !unlocked);
            smLockIcon.classList.toggle('fa-lock-open', unlocked);
        }
        if (smToggle) {
            smToggle.classList.toggle('sm-unlocked', unlocked);
        }
    }

    function relockSystemManager() {
        try { sessionStorage.removeItem(SM_UNLOCK_KEY); } catch (e) { }
        setUnlockedUI(false);
        if (dbManagerDropdown) dbManagerDropdown.classList.remove('show');
        if (dbManagerArrow) dbManagerArrow.classList.remove('rotated');
        if (smToggle) smToggle.classList.remove('active-dropdown');
    }

    (function enforceScope() {
        const insideSM = SM_PAGES.includes(CURRENT_PAGE);
        if (!insideSM && isUnlocked()) {
            relockSystemManager();
        }
    })();

    function openPinModal() {
        if (pinModalOpen) return;
        pinModalOpen = true;
        isSubmitting = false;

        pinOverlay.classList.add('show');
        pinOverlay.setAttribute('aria-hidden', 'false');

        pinInput.value = '';
        pinInput.type = 'password';
        pinEyeIcon.className = 'fas fa-eye';
        pinErrorMessage.classList.remove('show');
        pinInput.classList.remove('error', 'success');

        setTimeout(() => pinInput.focus(), 120);
    }

    function closePinModal() {
        if (!pinModalOpen) return;
        pinModalOpen = false;

        if (autoSubmitTimer) {
            clearTimeout(autoSubmitTimer);
            autoSubmitTimer = null;
        }

        pinOverlay.classList.remove('show');
        pinOverlay.setAttribute('aria-hidden', 'true');
    }

    function showPinError(msg) {
        pinErrorText.textContent = msg;
        pinErrorMessage.classList.add('show');
        pinInput.classList.add('error');
        pinInput.classList.remove('success');
        pinInput.focus();
        pinInput.select();
    }

    function clearPinError() {
        pinErrorMessage.classList.remove('show');
        pinInput.classList.remove('error');
    }

    function handleSystemManagerClick(e) {
        if (e) e.preventDefault();

        if (isUnlocked()) {
            toggleDropdown('dbManagerDropdown');
            return;
        }

        openPinModal();
    }

    function tryUnlock() {
        if (isSubmitting) return;

        const value = (pinInput.value || '').trim();

        if (value === '') {
            showPinError('Please enter your PIN.');
            return;
        }

        isSubmitting = true;

        if (value === SYSTEM_MANAGER_PIN) {
            pinInput.classList.remove('error');
            pinInput.classList.add('success');

            try { sessionStorage.setItem(SM_UNLOCK_KEY, '1'); } catch (e) { }
            setUnlockedUI(true);

            setTimeout(() => {
                closePinModal();
                if (dbManagerDropdown) dbManagerDropdown.classList.add('show');
                if (dbManagerArrow) dbManagerArrow.classList.add('rotated');
                if (smToggle) smToggle.classList.add('active-dropdown');
                isSubmitting = false;
            }, 180);
        } else {
            showPinError('Incorrect PIN. Please try again.');
            isSubmitting = false;
        }
    }

    if (pinConfirmBtn) pinConfirmBtn.addEventListener('click', tryUnlock);
    if (pinCancelBtn) pinCancelBtn.addEventListener('click', closePinModal);

    if (pinInput) {
        pinInput.addEventListener('input', () => {
            clearPinError();
            pinInput.value = pinInput.value.replace(/[^0-9]/g, '');

            if (autoSubmitTimer) clearTimeout(autoSubmitTimer);

            const value = pinInput.value.trim();
            if (value.length === SYSTEM_MANAGER_PIN.length) {
                autoSubmitTimer = setTimeout(() => {
                    if (pinInput.value.trim() === SYSTEM_MANAGER_PIN) {
                        tryUnlock();
                    }
                }, 120);
            }
        });

        pinInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                tryUnlock();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closePinModal();
            }
        });
    }

    if (pinToggleBtn) {
        pinToggleBtn.addEventListener('click', () => {
            if (pinInput.type === 'password') {
                pinInput.type = 'text';
                pinEyeIcon.className = 'fas fa-eye-slash';
            } else {
                pinInput.type = 'password';
                pinEyeIcon.className = 'fas fa-eye';
            }
            pinInput.focus();
        });
    }

    if (pinOverlay) {
        pinOverlay.addEventListener('click', (e) => {
            if (e.target === pinOverlay) closePinModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (pinModalOpen && e.key === 'Escape') closePinModal();
    });

    (function restoreLockState() {
        const insideSM = SM_PAGES.includes(CURRENT_PAGE);
        setUnlockedUI(insideSM && isUnlocked());
    })();

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

        if (id === 'dbManagerDropdown' && smToggle) {
            const isOpen = menu.classList.contains('show');
            smToggle.classList.toggle('active-dropdown', isOpen);
        }
    }
</script>