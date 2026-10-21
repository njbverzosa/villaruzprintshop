<style>
    /* ============================================================
       SIDEBAR — MODERN MIXED-GRADIENT BACKGROUND
       ============================================================ */

    /* ✅ Sidebar shell — mesh gradient background (NO SCROLLER) */
    .side-menu {
        position: relative;
        overflow: hidden;
        background-color: #eef2ff;
        background-image:
            radial-gradient(at 12% 8%, rgba(99, 102, 241, 0.35) 0px, transparent 55%),
            radial-gradient(at 92% 4%, rgba(59, 130, 246, 0.30) 0px, transparent 55%),
            radial-gradient(at 88% 42%, rgba(168, 85, 247, 0.26) 0px, transparent 55%),
            radial-gradient(at 18% 72%, rgba(236, 72, 153, 0.22) 0px, transparent 55%),
            radial-gradient(at 82% 94%, rgba(14, 165, 233, 0.24) 0px, transparent 55%),
            radial-gradient(at 8% 100%, rgba(139, 92, 246, 0.24) 0px, transparent 55%),
            linear-gradient(160deg, #f5f3ff 0%, #eef2ff 40%, #eff6ff 70%, #fdf2f8 100%);
        background-attachment: local;
    }

    /* ✅ Soft floating blobs behind the content for depth */
    .side-menu::before,
    .side-menu::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        filter: blur(70px);
        pointer-events: none;
        z-index: 0;
        opacity: 0.6;
    }

    .side-menu::before {
        width: 260px;
        height: 260px;
        top: -90px;
        left: -80px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.65), transparent 70%);
        animation: sidebarFloat 16s ease-in-out infinite;
    }

    .side-menu::after {
        width: 300px;
        height: 300px;
        bottom: -120px;
        right: -100px;
        background: radial-gradient(circle, rgba(236, 72, 153, 0.45), transparent 70%);
        animation: sidebarFloat 20s ease-in-out infinite reverse;
    }

    @keyframes sidebarFloat {

        0%,
        100% {
            transform: translate(0, 0) scale(1);
        }

        50% {
            transform: translate(20px, -20px) scale(1.08);
        }
    }

    /* ✅ Keep header + nav above the blobs */
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

    /* ---------- User Header (glass) ---------- */
    .menu-header {
        background: rgba(255, 255, 255, 0.55);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.85);
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
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
        color: #6366f1;
        padding: 6px 7px;
        flex-shrink: 0;
    }

    /* ---------- Nav Container (no inner scroll) ---------- */
    .menu-nav {
        padding: 16px 14px 24px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        overflow: visible;
    }

    /* ============================================================
       MAIN NAV ITEMS — glassy tiles on gradient
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
        transition: background 0.2s ease, color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
        cursor: pointer;
        overflow: hidden;
        border: 1px solid transparent;
    }

    .menu-nav .nav-item i {
        width: 22px;
        font-size: 18px;
        color: #64748b;
        transition: color 0.2s ease, transform 0.2s ease;
        text-align: center;
        flex-shrink: 0;
    }

    .menu-nav .nav-item span {
        font-size: 14.5px;
        font-weight: 500;
        letter-spacing: -0.005em;
    }

    .menu-nav .nav-item:hover {
        background: rgba(255, 255, 255, 0.65);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        color: #0f172a;
        border-color: rgba(255, 255, 255, 0.9);
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
    }

    .menu-nav .nav-item:hover i {
        color: #6366f1;
        transform: scale(1.08);
    }

    .menu-nav .nav-item.active,
    .menu-nav .nav-item.shop {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(238, 242, 255, 0.92) 100%);
        color: #4338ca;
        font-weight: 600;
        border-color: rgba(255, 255, 255, 0.95);
        box-shadow:
            0 6px 18px rgba(99, 102, 241, 0.18),
            0 1px 0 rgba(255, 255, 255, 0.9) inset;
    }

    .menu-nav .nav-item.active::before,
    .menu-nav .nav-item.shop::before {
        content: "";
        position: absolute;
        left: 0;
        top: 20%;
        bottom: 20%;
        width: 3px;
        background: linear-gradient(180deg, #6366f1, #8b5cf6);
        border-radius: 0 3px 3px 0;
    }

    .menu-nav .nav-item.active i,
    .menu-nav .nav-item.shop i {
        color: #4f46e5;
    }

    .menu-nav .nav-item.active:hover,
    .menu-nav .nav-item.shop:hover {
        background: linear-gradient(135deg, #ffffff 0%, #eef2ff 100%);
        color: #3730a3;
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
        transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        cursor: pointer;
        user-select: none;
        border: 1px solid transparent;
    }

    .nav-dropdown-toggle:hover {
        background: rgba(255, 255, 255, 0.65);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        color: #0f172a;
        border-color: rgba(255, 255, 255, 0.9);
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
    }

    .nav-dropdown-toggle i:first-child {
        width: 22px;
        font-size: 18px;
        color: #64748b;
        transition: color 0.2s ease, transform 0.2s ease;
        text-align: center;
        flex-shrink: 0;
    }

    .nav-dropdown-toggle:hover i:first-child {
        color: #6366f1;
        transform: scale(1.08);
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
        transition: transform 0.3s ease, color 0.2s ease;
        padding: 4px;
        border-radius: 6px;
    }

    .dropdown-arrow.rotated {
        transform: rotate(180deg);
    }

    .nav-dropdown-toggle.active-dropdown {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(238, 242, 255, 0.92) 100%);
        color: #4338ca;
        font-weight: 600;
        border-color: rgba(255, 255, 255, 0.95);
        box-shadow:
            0 6px 18px rgba(99, 102, 241, 0.18),
            0 1px 0 rgba(255, 255, 255, 0.9) inset;
    }

    .nav-dropdown-toggle.active-dropdown i:first-child {
        color: #4f46e5;
    }

    .nav-dropdown-toggle.active-dropdown .dropdown-arrow {
        color: #6366f1 !important;
    }

    /* ============================================================
       DROPDOWN MENU CONTAINER — glass card
       ============================================================ */
    .nav-dropdown-menu {
        display: none;
        margin: 6px 0 8px 22px;
        padding: 6px;
        border-left: none;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.55);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
        animation: dropdownSlide 0.2s ease;
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
       DROPDOWN ITEMS (child items — hover REMOVED)
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
        transition: background 0.2s ease, color 0.2s ease;
        font-size: 13.5px;
        cursor: pointer;
        margin-bottom: 2px;
    }

    .nav-dropdown-item i {
        width: 18px;
        font-size: 13px;
        color: #64748b;
        transition: color 0.2s ease;
        text-align: center;
        flex-shrink: 0;
    }

    .nav-dropdown-item span {
        font-size: 13.5px;
        font-weight: 500;
        letter-spacing: -0.005em;
    }

    /* ============================================================
       CHILD ACTIVE STATES — transparent bg, colored text,
       4px left accent bar (matches Products Source style)
       ============================================================ */

    /* ---------- Generic active (Indigo) ---------- */
    .nav-dropdown-item.active {
        color: #4338ca;
        font-weight: 600;
    }

    .nav-dropdown-item.active::before {
        content: "";
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 5px;
        background: #6366f1;
        border-radius: 0 2px 2px 0;
    }

    .nav-dropdown-item.active i {
        color: #6366f1;
    }

    /* ---------- PENDING (Amber) ---------- */
    .nav-dropdown-item.active_pending {
        color: #b45309;
        font-weight: 600;
    }

    .nav-dropdown-item.active_pending::before {
        content: "";
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 4px;
        background: #f59e0b;
        border-radius: 0 2px 2px 0;
    }

    .nav-dropdown-item.active_pending i {
        color: #f59e0b;
    }

    /* ---------- PAID (Emerald) ---------- */
    .nav-dropdown-item.active_paid {
        color: #047857;
        font-weight: 600;
    }

    .nav-dropdown-item.active_paid::before {
        content: "";
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 4px;
        background: #10b981;
        border-radius: 0 2px 2px 0;
    }

    .nav-dropdown-item.active_paid i {
        color: #10b981;
    }

    /* ---------- CREDIT (Rose) ---------- */
    .nav-dropdown-item.active_credit {
        color: #b91c1c;
        font-weight: 600;
    }

    .nav-dropdown-item.active_credit::before {
        content: "";
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 4px;
        background: #ef4444;
        border-radius: 0 2px 2px 0;
    }

    .nav-dropdown-item.active_credit i {
        color: #ef4444;
    }

    /* ---------- SHOP PO (Blue) ---------- */
    .nav-dropdown-item.active_shop_po {
        color: #1d4ed8;
        font-weight: 600;
    }

    .nav-dropdown-item.active_shop_po::before {
        content: "";
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 4px;
        background: #3b82f6;
        border-radius: 0 2px 2px 0;
    }

    .nav-dropdown-item.active_shop_po i {
        color: #3b82f6;
    }

    /* ---------- CART PO (Amber) ---------- */
    .nav-dropdown-item.active_cart_po {
        color: #b45309;
        font-weight: 600;
    }

    .nav-dropdown-item.active_cart_po::before {
        content: "";
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 4px;
        background: #f59e0b;
        border-radius: 0 2px 2px 0;
    }

    .nav-dropdown-item.active_cart_po i {
        color: #f59e0b;
    }

    /* ---------- CUSTOMERS (Teal) ---------- */
    .nav-dropdown-item.active_customers {
        color: #0f766e;
        font-weight: 600;
    }

    .nav-dropdown-item.active_customers::before {
        content: "";
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 4px;
        background: #14b8a6;
        border-radius: 0 2px 2px 0;
    }

    .nav-dropdown-item.active_customers i {
        color: #14b8a6;
    }

    /* ---------- DATABASE (Blue) ---------- */
    .nav-dropdown-item.active_database {
        color: #1d4ed8;
        font-weight: 600;
    }

    .nav-dropdown-item.active_database::before {
        content: "";
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 4px;
        background: #3b82f6;
        border-radius: 0 2px 2px 0;
    }

    .nav-dropdown-item.active_database i {
        color: #3b82f6;
    }

    /* ---------- DOWNLOAD IMAGES (Violet) ---------- */
    .nav-dropdown-item.active_download_images {
        color: #6d28d9;
        font-weight: 600;
    }

    .nav-dropdown-item.active_download_images::before {
        content: "";
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 4px;
        background: #8b5cf6;
        border-radius: 0 2px 2px 0;
    }

    .nav-dropdown-item.active_download_images i {
        color: #8b5cf6;
    }

    /* ---------- INVESTORS (Pink) ---------- */
    .nav-dropdown-item.active_investors {
        color: #be185d;
        font-weight: 600;
    }

    .nav-dropdown-item.active_investors::before {
        content: "";
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 4px;
        background: #ec4899;
        border-radius: 0 2px 2px 0;
    }

    .nav-dropdown-item.active_investors i {
        color: #ec4899;
    }

    /* ---------- PRODUCTS SOURCE (Indigo) ---------- */
    .nav-dropdown-item.active_products_source {
        color: #4338ca;
        font-weight: 600;
    }

    .nav-dropdown-item.active_products_source::before {
        content: "";
        position: absolute;
        left: 0;
        top: 18%;
        bottom: 18%;
        width: 4px;
        background: #6366f1;
        border-radius: 0 2px 2px 0;
    }

    .nav-dropdown-item.active_products_source i {
        color: #6366f1;
    }

    /* ============================================================
       CLOSE BUTTON (mobile) — glass
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
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.95);
        border-radius: 10px;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s ease;
        z-index: 20;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
    }

    .sidebar-close-btn:hover {
        background: #ffffff;
        color: #0f172a;
        transform: translateX(-2px);
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
        transition: color 0.2s ease;
    }

    .nav-dropdown-toggle.sm-unlocked .sm-lock-icon {
        color: #10b981 !important;
    }

    /* ============================================================
       🔐 PIN MODAL — glass w/ gradient accents
       ============================================================ */
    .pin-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s ease;
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
        transition: transform 0.25s ease;
        position: relative;
    }

    .pin-modal::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.08), transparent 50%),
            radial-gradient(at 100% 100%, rgba(236, 72, 153, 0.08), transparent 50%);
        pointer-events: none;
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
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        border-radius: 50%;
        color: #ffffff;
        font-size: 22px;
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.35);
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
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        font-family: 'Poppins', monospace;
        box-sizing: border-box;
    }

    .pin-input::placeholder {
        letter-spacing: 0.2em;
        color: #cbd5e1;
        font-size: 14px;
    }

    .pin-input:focus {
        border-color: #6366f1;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.14);
    }

    .pin-input.error {
        border-color: #ef4444;
        background: #fef2f2;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12);
        animation: pinShake 0.35s ease;
    }

    .pin-input.success {
        border-color: #10b981;
        background: #ecfdf5;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        animation: pinSuccessPulse 0.35s ease;
    }

    @keyframes pinShake {

        0%,
        100% {
            transform: translateX(0);
        }

        20% {
            transform: translateX(-6px);
        }

        40% {
            transform: translateX(6px);
        }

        60% {
            transform: translateX(-4px);
        }

        80% {
            transform: translateX(4px);
        }
    }

    @keyframes pinSuccessPulse {
        0% {
            transform: scale(1);
        }

        40% {
            transform: scale(1.03);
        }

        100% {
            transform: scale(1);
        }
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
        transition: color 0.2s ease, background 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pin-toggle-visibility:hover {
        color: #6366f1;
        background: #eef2ff;
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
        animation: dropdownSlide 0.2s ease;
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
        transition: all 0.2s ease;
        border: none;
        font-family: inherit;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
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
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
    }

    .pin-btn-confirm:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        box-shadow: 0 6px 18px rgba(99, 102, 241, 0.45);
        transform: translateY(-1px);
    }

    .pin-btn-confirm:active {
        transform: translateY(0);
    }

    .pin-btn-confirm:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
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

    // ✅ System User covers both Investors and Customers pages
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
            $showCart = false; // fail safe → hide cart if query errors
        }
    }
    ?>

    <!-- ========== SHOP ========== -->
    <a href="shop.php" class="nav-item <?php echo $currentPage == 'shop.php' ? 'active' : ''; ?>">
        <i class="fas fa-store"></i>
        <span>Shop</span>
    </a>

    <!-- ========== CART (only if user has a row in cart table) ========== -->
    <?php if ($showCart): ?>
        <a href="cart.php" class="nav-item <?php echo $currentPage == 'cart.php' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Cart</span>
        </a>
    <?php endif; ?>

    <!-- ========== ORDERS DROPDOWN ========== -->
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

    <!-- ========== SOURCE DROPDOWN ========== -->
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

    <!-- ========== SYSTEM USER DROPDOWN ========== -->
    <?php if (in_array($authorizeAccess, [0, 2], true)): ?>
        <div class="nav-dropdown">
            <div class="nav-dropdown-toggle <?php echo $customersActive ? 'active-dropdown' : ''; ?>"
                onclick="toggleDropdown('customersDropdown')">
                <i class="fas fa-user-friends"></i>
                <span>System User</span>
                <i class="fas fa-chevron-down dropdown-arrow <?php echo $customersActive ? 'rotated' : ''; ?>"
                    id="customersArrow"></i>
            </div>

            <!-- ✅ ONE menu container holding both links -->
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

    <!-- ========== SYSTEM MANAGER / DB MANAGER DROPDOWN ========== -->
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

    <!-- ========== LOGOUT ========== -->
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
    // ============================================================
    // SYSTEM MANAGER PIN GATE — AUTO-SUBMIT ON CORRECT PIN
    // ============================================================
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