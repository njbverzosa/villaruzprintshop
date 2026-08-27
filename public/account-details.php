<?php
// public/account.php (renamed from account-details.php)

session_start();

// ==============================================
// 1. FIX PATHS - config.php is in DB_Conn folder at root level
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 2. CHECK LOGIN STATUS
// ==============================================
function isLoggedIn() {
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

// Fetch user details from database - ADDED vip column
$userData = null;
if ($userRole === 'Customer') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, vip FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 4. USE $userData
// ==============================================
$user = $userData;

// ==============================================
// 5. GET CART COUNT FOR BOTTOM NAV
// ==============================================
$cartCountStmt = $pdo->prepare("SELECT SUM(pieces) as total_items FROM cart WHERE acc_number = ?");
$cartCountStmt->execute([$accNumber]);
$cartCountResult = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
$cartTotalItems = intval($cartCountResult['total_items'] ?? 0);

// ==============================================
// 6. SET USER VARIABLES WITH FALLBACKS
// ==============================================
$userAccNumber = $user['acc_number'] ?? $accNumber;
$userFullName = $user['f_name'] ?? '';
$userStreet = $user['street'] ?? '';
$userBarangay = $user['barangay'] ?? '';
$userLandMark = $user['land_mark'] ?? '';
$userEmail = $user['email'] ?? '';
$userContact = $user['phone_number'] ?? '';
$userJoined = $user['registered_at'] ?? '';
$isEmailVerified = $user['active_email'] ?? 0;

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Get success/error messages from session
$successMessage = $_SESSION['edit_success'] ?? '';
$errorMessage = $_SESSION['edit_error'] ?? '';
unset($_SESSION['edit_success'], $_SESSION['edit_error']);

// Check if user is VIP
$isVip = isset($user['vip']) && $user['vip'] == 1;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Account | Villaruz Print Shop & General Merchandise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== RESET & BASE STYLES ========== */
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
            padding-bottom: 70px;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }

        input,
        textarea,
        [contenteditable="true"] {
            user-select: text;
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            padding: 20px 20px 30px;
            overflow-y: auto;
            background: #f1f5f9;
        }

        /* ========== DASHBOARD HEADER ========== */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: #ffffff;
            padding: 18px 25px;
            border-radius: 2px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .welcome h3 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }

        .welcome h3 i {
            color: #3b82f6;
            margin-left: 8px;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f1f5f9;
            padding: 6px 14px 6px 10px;
            border-radius: 5px;
        }

        .user-badge .avatar {
            width: 32px;
            height: 32px;
            border-radius: 20px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }

        .user-badge .name {
            font-size: 13px;
            font-weight: 500;
            color: #0f172a;
        }

        /* ========== TOAST MESSAGES ========== */
        .toast-message {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        .toast-message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .toast-message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .toast-message.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .toast-message i {
            font-size: 18px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .toast-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 16px;
            margin-left: auto;
            opacity: 0.7;
            transition: opacity 0.3s;
            padding: 0 4px;
        }

        .toast-close:hover {
            opacity: 1;
        }

        /* ========== ACCOUNT DETAILS CARD ========== */
        .account-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 750px;
            margin: 0 auto;
            width: 100%;
            border: 1px solid #e2e8f0;
        }

        .account-card .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 25px;
        }

        .account-card .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 32px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .account-card .profile-name {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
        }

        .account-card .profile-acc {
            font-size: 14px;
            color: #94a3b8;
        }

        .account-detail {
            display: flex;
            align-items: center;
            padding: 14px 12px;
            border-bottom: 1px solid #f1f5f9;
            gap: 10px;
            transition: all 0.3s;
            flex-wrap: wrap;
            border-radius: 8px;
            margin: 2px 0;
        }

        .account-detail:last-child {
            border-bottom: none;
        }

        .account-detail:hover {
            background: #f8fafc;
        }

        .account-detail .label {
            width: 140px;
            font-weight: 600;
            color: #475569;
            font-size: 14px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .account-detail .label i {
            width: 18px;
            color: #3b82f6;
            font-size: 16px;
        }

        .account-detail .value-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex-wrap: wrap;
        }

        .account-detail .value-display {
            flex: 1;
            padding: 8px 14px;
            min-height: 40px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid transparent;
            word-break: break-word;
            font-size: 14px;
            color: #0f172a;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            transition: all 0.3s;
        }

        .account-detail .value-edit {
            flex: 1;
            display: none;
            min-width: 0;
        }

        .account-detail .value-edit input,
        .account-detail .value-edit textarea {
            width: 100%;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            background: white;
            transition: all 0.3s ease;
            color: #0f172a;
            outline: none;
        }

        .account-detail .value-edit input:focus,
        .account-detail .value-edit textarea:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            background: #ffffff;
        }

        .account-detail .value-edit input:hover,
        .account-detail .value-edit textarea:hover {
            border-color: #94a3b8;
        }

        .account-detail .value-edit input::placeholder,
        .account-detail .value-edit textarea::placeholder {
            color: #94a3b8;
            font-size: 13px;
        }

        .account-detail .value-edit textarea {
            resize: vertical;
            min-height: 60px;
            line-height: 1.6;
        }

        .account-detail.editing {
            background: #eff6ff;
            border-radius: 10px;
            padding: 14px 12px;
            border-left: 4px solid #3b82f6;
            margin: 4px 0;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.08);
        }

        .account-detail.editing .value-edit input,
        .account-detail.editing .value-edit textarea {
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .account-detail.editing .label {
            color: #1e40af;
        }

        .account-detail.editing .label i {
            color: #2563eb;
        }

        .account-detail.editing .value-edit {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .account-detail.editing .value-display {
            display: none;
        }

        .account-detail .btn-group {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }

        .account-detail .edit-btn {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid transparent;
            white-space: nowrap;
            min-width: 44px;
            justify-content: center;
            font-weight: 500;
        }

        .account-detail .edit-btn:hover:not(:disabled) {
            background: #eff6ff;
            color: #3b82f6;
            border-color: #bfdbfe;
        }

        .account-detail .edit-btn:active:not(:disabled) {
            transform: scale(0.95);
        }

        .account-detail .edit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .account-detail .edit-btn.saving {
            color: #1e40af;
            background: #dbeafe;
            border-color: #93c5fd;
        }

        .account-detail .edit-btn.success {
            color: #10b981;
            background: #d1fae5;
            border-color: #6ee7b7;
        }

        .account-detail .edit-btn.error {
            color: #ef4444;
            background: #fee2e2;
            border-color: #fca5a5;
        }

        .account-detail .cancel-btn {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #64748b;
            cursor: pointer;
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 13px;
            display: none;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            font-weight: 500;
        }

        .account-detail .cancel-btn:hover {
            background: #fee2e2;
            color: #ef4444;
            border-color: #fca5a5;
        }

        .account-detail .cancel-btn:active {
            transform: scale(0.95);
        }

        .account-detail.editing .cancel-btn {
            display: flex;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            margin-left: 8px;
        }

        .status-badge.verified {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.unverified {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ========== BOTTOM NAVIGATION ========== */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 0 12px;
            z-index: 1000;
            box-shadow: 0 -2px 15px rgba(0, 0, 0, 0.06);
            height: 65px;
        }

        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            color: #525f70;
            text-decoration: none;
            transition: all 0.3s ease;
            padding: 4px 16px;
            position: relative;
            min-width: 56px;
        }

        .bottom-nav .nav-item i {
            font-size: 25px;
            transition: all 0.3s ease;
        }

        .bottom-nav .nav-item span {
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
        }

        .bottom-nav .nav-item:hover {
            color: #3b82f6;
        }

        .bottom-nav .nav-item.active {
            color: #3b82f6;
        }

        .bottom-nav .nav-item .badge {
            position: absolute;
            top: 0;
            right: 4px;
            background: lightgreen;
            color: #020e20;
            font-size: 14px;
            font-weight: bold;
            padding: 1px 6px;
            border-radius: 20px;
            min-width: 12px;
            text-align: center;
            line-height: 14px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px 15px 20px;
            }

            .dashboard-header {
                padding: 14px 18px;
                flex-direction: row;
                flex-wrap: wrap;
                gap: 8px;
            }

            .welcome h3 {
                font-size: 17px;
            }

            .user-badge .name {
                font-size: 12px;
            }

            .account-card .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 12px;
                padding-bottom: 15px;
                margin-bottom: 18px;
            }

            .account-card .profile-avatar {
                width: 70px;
                height: 70px;
                font-size: 28px;
            }

            .account-card .profile-name {
                font-size: 20px;
            }

            .account-detail {
                flex-direction: column;
                align-items: stretch;
                gap: 6px;
                padding: 12px 10px;
            }

            .account-detail .label {
                width: 100%;
                font-size: 12px;
                padding: 0 4px;
            }

            .account-detail .value-wrapper {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
            }

            .account-detail .value-display {
                font-size: 13px;
                padding: 8px 12px;
                min-height: 38px;
            }

            .account-detail .value-edit {
                width: 100%;
            }

            .account-detail .value-edit input,
            .account-detail .value-edit textarea {
                font-size: 14px;
                padding: 10px 12px;
            }

            .account-detail .btn-group {
                width: 100%;
                justify-content: flex-end;
                gap: 8px;
                margin-top: 2px;
            }

            .account-detail .edit-btn {
                font-size: 13px;
                padding: 8px 16px;
                min-width: 48px;
            }

            .account-detail .cancel-btn {
                font-size: 12px;
                padding: 8px 16px;
            }

            .account-detail.editing {
                padding: 12px 10px;
                margin: 4px 0;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 12px 12px 16px;
            }

            body {
                padding-bottom: 60px;
            }

            .account-card {
                padding: 14px;
                border-radius: 8px;
            }

            .account-card .profile-header {
                gap: 10px;
                padding-bottom: 12px;
                margin-bottom: 14px;
            }

            .account-card .profile-avatar {
                width: 56px;
                height: 56px;
                font-size: 22px;
            }

            .account-card .profile-name {
                font-size: 17px;
            }

            .account-card .profile-acc {
                font-size: 12px;
            }

            .account-detail {
                padding: 10px 8px;
                gap: 4px;
            }

            .account-detail .label {
                font-size: 11px;
                padding: 0 2px;
            }

            .account-detail .label i {
                font-size: 13px;
            }

            .account-detail .value-display {
                font-size: 12px;
                padding: 6px 10px;
                min-height: 32px;
            }

            .account-detail .value-edit input,
            .account-detail .value-edit textarea {
                font-size: 13px;
                padding: 8px 10px;
                border-radius: 6px;
            }

            .account-detail .value-edit textarea {
                min-height: 50px;
            }

            .account-detail .edit-btn {
                font-size: 12px;
                padding: 6px 12px;
                min-width: 40px;
            }

            .account-detail .cancel-btn {
                font-size: 11px;
                padding: 6px 12px;
            }

            .account-detail.editing {
                padding: 10px 8px;
                margin: 3px 0;
                border-left-width: 3px;
            }

            .dashboard-header {
                padding: 12px 14px;
                border-radius: 5px;
            }

            .welcome h3 {
                font-size: 15px;
            }

            .user-badge .avatar {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }

            .user-badge .name {
                font-size: 11px;
            }

            .bottom-nav {
                padding: 4px 0 8px;
                height: 56px;
            }

            .bottom-nav .nav-item {
                padding: 2px 6px;
                min-width: 36px;
            }

            .bottom-nav .nav-item i {
                font-size: 18px;
            }

            .bottom-nav .nav-item span {
                font-size: 9px;
            }

            .bottom-nav .nav-item .badge {
                font-size: 10px;
                min-width: 14px;
                line-height: 14px;
                top: -2px;
                right: 0px;
                padding: 0 5px;
            }
        }

        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .bottom-nav {
                padding-bottom: calc(12px + env(safe-area-inset-bottom));
            }
        }

        @media print {
            body {
                padding-bottom: 0;
            }
            .bottom-nav {
                display: none;
            }
            .account-detail .edit-btn,
            .account-detail .cancel-btn {
                display: none !important;
            }
            .account-card {
                box-shadow: none;
                border: 1px solid #e2e8f0;
            }
            .main-content {
                padding: 10px;
            }
        }
         /* VIP Avatar Styles */
        .user-badge .avatar.vip {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            font-size: 12px;
            font-weight: 700;
        }

        .user-badge .vip-badge i {
            font-size: 10px;
        }
    </style>
</head>

<body>

    <!-- ========== MAIN CONTENT ========== -->
    <main class="main-content">
        <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">
        <input type="hidden" id="userAccNumber" value="<?php echo htmlspecialchars($userAccNumber); ?>">

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome">
                <h3><i class="fas fa-user"></i> Account</h3>
            </div>
            <div class="user-badge">
                <div class="avatar <?php echo (isset($user['vip']) && $user['vip'] == 1) ? 'vip' : ''; ?>">
                    <?php
                    $isVip = isset($user['vip']) && $user['vip'] == 1;

                    if ($isVip):
                        ?>
                        <i class="fas fa-crown"></i>
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['f_name'] ?? 'G', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <span class="name"><?php echo htmlspecialchars($user['f_name'] ?? 'Guest'); ?></span>
            </div>
        </div>

        <!-- Toast Messages -->
        <?php if ($successMessage): ?>
            <div class="toast-message success" id="toastMessage">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($successMessage); ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="toast-message error" id="toastMessage">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($errorMessage); ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Account Details Card -->
        <div class="account-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($userFullName, 0, 1) ?: '?'); ?>
                </div>
                <div>
                    <div class="profile-name" id="profileName">
                        <?php echo htmlspecialchars($userFullName ?: 'Guest'); ?>
                    </div>
                    <div class="profile-acc">
                        <i class="fas fa-id-card"></i> Account #: <?php echo htmlspecialchars($userAccNumber); ?>
                    </div>
                </div>
            </div>

            <!-- Full Name (Editable) -->
            <div class="account-detail" data-field="f_name">
                <span class="label"><i class="fas fa-user"></i> Fullname</span>
                <div class="value-wrapper">
                    <div class="value-display" id="display_f_name"><?php echo htmlspecialchars($userFullName); ?></div>
                    <div class="value-edit">
                        <input type="text" class="edit-input" id="input_f_name"
                            value="<?php echo htmlspecialchars($userFullName); ?>"
                            placeholder="Enter your full name">
                    </div>
                    <div class="btn-group">
                        <button class="edit-btn" onclick="toggleEdit(this)" title="Edit field">
                            <i class="fas fa-pen"></i> Edit
                        </button>
                        <button class="cancel-btn" onclick="cancelEdit(this)" title="Cancel edit">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Street (Editable) -->
            <div class="account-detail" data-field="street">
                <span class="label"><i class="fas fa-road"></i> Street</span>
                <div class="value-wrapper">
                    <div class="value-display" id="display_street">
                        <?php echo htmlspecialchars($userStreet ?: 'Not set'); ?>
                    </div>
                    <div class="value-edit">
                        <input type="text" class="edit-input" id="input_street"
                            value="<?php echo htmlspecialchars($userStreet); ?>"
                            placeholder="Enter your street name">
                    </div>
                    <div class="btn-group">
                        <button class="edit-btn" onclick="toggleEdit(this)" title="Edit field">
                            <i class="fas fa-pen"></i> Edit
                        </button>
                        <button class="cancel-btn" onclick="cancelEdit(this)" title="Cancel edit">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Barangay (Editable) -->
            <div class="account-detail" data-field="barangay">
                <span class="label"><i class="fas fa-building"></i> Barangay</span>
                <div class="value-wrapper">
                    <div class="value-display" id="display_barangay">
                        <?php echo htmlspecialchars($userBarangay ?: 'Not set'); ?>
                    </div>
                    <div class="value-edit">
                        <input type="text" class="edit-input" id="input_barangay"
                            value="<?php echo htmlspecialchars($userBarangay); ?>"
                            placeholder="Enter your barangay">
                    </div>
                    <div class="btn-group">
                        <button class="edit-btn" onclick="toggleEdit(this)" title="Edit field">
                            <i class="fas fa-pen"></i> Edit
                        </button>
                        <button class="cancel-btn" onclick="cancelEdit(this)" title="Cancel edit">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Land Mark (Editable) -->
            <div class="account-detail" data-field="land_mark">
                <span class="label"><i class="fas fa-map-pin"></i> Land Mark</span>
                <div class="value-wrapper">
                    <div class="value-display" id="display_land_mark">
                        <?php echo htmlspecialchars($userLandMark ?: 'Not set'); ?>
                    </div>
                    <div class="value-edit">
                        <input type="text" class="edit-input" id="input_land_mark"
                            value="<?php echo htmlspecialchars($userLandMark); ?>"
                            placeholder="Enter a landmark near your location">
                    </div>
                    <div class="btn-group">
                        <button class="edit-btn" onclick="toggleEdit(this)" title="Edit field">
                            <i class="fas fa-pen"></i> Edit
                        </button>
                        <button class="cancel-btn" onclick="cancelEdit(this)" title="Cancel edit">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Email (Editable) -->
            <div class="account-detail" data-field="email">
                <span class="label"><i class="fas fa-envelope"></i> Email</span>
                <div class="value-wrapper">
                    <div class="value-display" id="display_email">
                        <?php echo htmlspecialchars($userEmail ?: 'Not set'); ?>
                        <?php if ($isEmailVerified): ?>
                            <span class="status-badge verified">
                                <i class="fas fa-check-circle"></i> Verified
                            </span>
                        <?php else: ?>
                            <span class="status-badge unverified">
                                <i class="fas fa-exclamation-circle"></i> Unverified
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="value-edit">
                        <input type="email" class="edit-input" id="input_email"
                            value="<?php echo htmlspecialchars($userEmail); ?>"
                            placeholder="Enter your email address">
                    </div>
                    <div class="btn-group">
                        <button class="edit-btn" onclick="toggleEdit(this)" title="Edit field">
                            <i class="fas fa-pen"></i> Edit
                        </button>
                        <button class="cancel-btn" onclick="cancelEdit(this)" title="Cancel edit">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Contact Number (Not Editable) -->
            <div class="account-detail" data-field="phone_number">
                <span class="label"><i class="fas fa-phone"></i> Contact Number</span>
                <div class="value-wrapper">
                    <div class="value-display" id="display_phone_number">
                        <?php echo htmlspecialchars($userContact ?: 'Not set'); ?>
                    </div>
                    <div class="value-edit">
                        <input type="text" class="edit-input" id="input_phone_number"
                            value="<?php echo htmlspecialchars($userContact); ?>"
                            placeholder="Enter your contact number" disabled>
                    </div>
                    <div class="btn-group">
                        <button class="edit-btn" disabled style="opacity:0.5; cursor:not-allowed;" title="Contact number cannot be edited">
                            <i class="fas fa-lock"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Date Joined (Read Only) -->
            <div class="account-detail">
                <span class="label"><i class="fas fa-calendar-check"></i> Date Joined</span>
                <div class="value-wrapper">
                    <div class="value-display"><?php echo htmlspecialchars($userJoined ?: 'N/A'); ?></div>
                    <div class="value-edit">
                        <input type="text" class="edit-input"
                            value="<?php echo htmlspecialchars($userJoined); ?>" disabled>
                    </div>
                    <div class="btn-group">
                        <button class="edit-btn" disabled style="opacity:0.5; cursor:not-allowed;" title="Read only field">
                            <i class="fas fa-lock"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- ========== BOTTOM NAVIGATION ========== -->
    <nav class="bottom-nav">
        <a href="shop.php" class="nav-item">
            <i class="fas fa-store"></i>
            <span>Shop</span>
        </a>
        <a href="cart.php" class="nav-item">
            <i class="fas fa-shopping-cart"></i>
            <span>Cart</span>
            <?php if ($cartTotalItems > 0): ?>
                <span class="badge"><?php echo $cartTotalItems; ?></span>
            <?php endif; ?>
        </a>
        <a href="orders.php" class="nav-item">
            <i class="fas fa-truck"></i>
            <span>Orders</span>
        </a>
        <a href="account.php" class="nav-item active">
            <i class="fas fa-th-large"></i>
            <span>Services</span>
        </a>
       <a href="closed.php" class="nav-item" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <script>
        const csrfToken = document.getElementById('csrfToken').value;
        const accNumber = document.getElementById('userAccNumber').value;

        // ============================================================
        // TOAST MESSAGES
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.getElementById('toastMessage');
            if (toast) {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity 0.5s';
                    setTimeout(() => toast.remove(), 500);
                }, 5000);
            }
        });

        function showToast(message, type = 'success') {
            const existingToast = document.querySelector('.toast-message');
            if (existingToast) existingToast.remove();

            const toast = document.createElement('div');
            toast.className = 'toast-message ' + type;
            const icon = type === 'success' ? 'fa-check-circle' : type === 'info' ? 'fa-info-circle' :
                'fa-exclamation-circle';
            toast.innerHTML = `
                <i class="fas ${icon}"></i> 
                ${message}
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;

            const mainContent = document.querySelector('.main-content');
            const header = document.querySelector('.dashboard-header');
            if (mainContent && header) {
                mainContent.insertBefore(toast, header.nextSibling);
            }

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.5s';
                setTimeout(() => toast.remove(), 500);
            }, 5000);
        }

        // ============================================================
        // TOGGLE EDIT
        // ============================================================
        function toggleEdit(btn) {
            const detail = btn.closest('.account-detail');
            const display = detail.querySelector('.value-display');
            const edit = detail.querySelector('.value-edit');
            const cancelBtn = detail.querySelector('.cancel-btn');

            display.style.display = 'none';
            edit.style.display = 'block';
            cancelBtn.style.display = 'flex';

            btn.innerHTML = '<i class="fas fa-save"></i> Save';
            btn.className = 'edit-btn saving';
            btn.onclick = function() {
                saveField(this);
            };
            detail.classList.add('editing');

            const input = detail.querySelector('.edit-input');
            if (input) {
                setTimeout(() => input.focus(), 100);
                if (input.tagName === 'INPUT' && input.type !== 'hidden') {
                    input.select();
                }
            }
        }

        // ============================================================
        // CANCEL EDIT
        // ============================================================
        function cancelEdit(btn) {
            const detail = btn.closest('.account-detail');
            const display = detail.querySelector('.value-display');
            const edit = detail.querySelector('.value-edit');
            const editBtn = detail.querySelector('.edit-btn');

            display.style.display = 'flex';
            edit.style.display = 'none';
            btn.style.display = 'none';

            editBtn.innerHTML = '<i class="fas fa-pen"></i> Edit';
            editBtn.className = 'edit-btn';
            editBtn.onclick = function() {
                toggleEdit(this);
            };
            detail.classList.remove('editing');

            const input = detail.querySelector('.edit-input');
            const displayText = display.textContent.trim();
            if (input && displayText !== 'N/A' && displayText !== 'Not set') {
                input.value = displayText;
            }
        }

        // ============================================================
        // SAVE FIELD
        // ============================================================
        async function saveField(btn) {
            const detail = btn.closest('.account-detail');
            const field = detail.dataset.field;
            const input = detail.querySelector('.edit-input');
            const value = input.value.trim();

            if (!value) {
                showToast('Please enter a value', 'error');
                btn.innerHTML = '<i class="fas fa-pen"></i> Edit';
                btn.className = 'edit-btn';
                btn.onclick = function() {
                    toggleEdit(this);
                };
                return;
            }

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            btn.className = 'edit-btn saving';
            btn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'update_field');
                formData.append('acc_number', accNumber);
                formData.append('field', field);
                formData.append('value', value);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../Customer_API/edit_account.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    showToast('Field updated successfully! Refreshing page...', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    btn.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error';
                    btn.className = 'edit-btn error';
                    showToast(data.message || 'Error updating field', 'error');
                    setTimeout(() => {
                        btn.innerHTML = '<i class="fas fa-pen"></i> Edit';
                        btn.className = 'edit-btn';
                        btn.disabled = false;
                        btn.onclick = function() {
                            toggleEdit(this);
                        };
                    }, 2000);
                }
            } catch (error) {
                console.error('Error:', error);
                btn.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error';
                btn.className = 'edit-btn error';
                showToast('Network error. Please try again.', 'error');
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-pen"></i> Edit';
                    btn.className = 'edit-btn';
                    btn.disabled = false;
                    btn.onclick = function() {
                        toggleEdit(this);
                    };
                }, 2000);
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.classList.contains('edit-input') && e.target.tagName !== 'TEXTAREA') {
                const detail = e.target.closest('.account-detail');
                if (detail) {
                    const btn = detail.querySelector('.edit-btn.saving');
                    if (btn) btn.click();
                }
            }
            if (e.key === 'Escape') {
                const detail = e.target.closest('.account-detail.editing');
                if (detail) {
                    const cancelBtn = detail.querySelector('.cancel-btn');
                    if (cancelBtn) cancelBtn.click();
                }
            }
        });

        console.log('👤 Account page loaded');
        console.log('📧 Email verified: <?php echo $isEmailVerified ? 'Yes' : 'No'; ?>');
        console.log('🏠 Street: <?php echo htmlspecialchars($userStreet ?: 'Not set'); ?>');
        console.log('🏘️ Barangay: <?php echo htmlspecialchars($userBarangay ?: 'Not set'); ?>');
        console.log('📍 Land Mark: <?php echo htmlspecialchars($userLandMark ?: 'Not set'); ?>');
    </script>

</body>

</html>