<?php
// public/partnership.php

session_start();

// ==============================================
// 1. FIX PATHS - config.php is in DB_Conn folder at root level
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 2. CHECK LOGIN STATUS
// ==============================================
function isLoggedIn()
{
    return isset($_SESSION['user_role']) &&
        isset($_SESSION['user_id']) &&
        isset($_SESSION['acc_number']);
}

if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Please login first to access the shop.';
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 3. GET USER DATA FROM SESSION
// ==============================================
$userRole  = $_SESSION['user_role'];
$userId    = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

$userData = null;
if ($userRole === 'Customer') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, street, barangay, landmark_photo, registered_at, active_email, vip FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$user = $userData;

// ==============================================
// 4. UPDATE ONLINE TIME
// ==============================================
date_default_timezone_set('Asia/Manila');
$currentTime = date('M j, g:i A');

if ($userRole === 'Customer') {
    $updateStmt = $pdo->prepare("UPDATE customers SET online_time = ? WHERE id = ?");
    $updateStmt->execute([$currentTime, $userData['id']]);
}

// ==============================================
// 5. GET CART COUNT FOR BOTTOM NAV
// ==============================================
$cartCountStmt = $pdo->prepare("SELECT SUM(pieces) as total_items FROM cart WHERE acc_number = ?");
$cartCountStmt->execute([$accNumber]);
$cartCountResult = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
$cartTotalItems  = intval($cartCountResult['total_items'] ?? 0);

// ==============================================
// 6. SET USER VARIABLES
// ==============================================
$userAccNumber   = $user['acc_number']   ?? $accNumber;
$userFullName    = $user['f_name']       ?? '';
$userStreet      = $user['street']       ?? '';
$userBarangay    = $user['barangay']     ?? '';
$userLandMark    = $user['landmark_photo'] ?? '';
$userEmail       = $user['email']        ?? '';
$userContact     = $user['phone_number'] ?? '';
$isEmailVerified = $user['active_email'] ?? 0;
$isVip           = isset($user['vip']) && $user['vip'] == 1;

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Check if user is Guest (no name set)
$isGuest = ($userFullName === 'Guest' || empty($userFullName));

// ==============================================
// 7. PRODUCT TYPE OPTIONS
// ==============================================
$productTypes = [
    'Laundry Tools',
    'Hardware Tools',
    'School Supply',
    'Foods',
    'Electronic Devices',
    'Clothing',
    'Gadget Accessories',
    'Gadgets',
    'Appliances',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Invest | Villaruz Print Shop & General Merchandise</title>
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

        input, textarea, [contenteditable="true"] {
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

        .user-badge .avatar.vip {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            font-size: 12px;
            font-weight: 700;
        }

        .user-badge .name {
            font-size: 13px;
            font-weight: 500;
            color: #0f172a;
        }

        /* ========== TOAST / NOTIFICATION MODAL ========== */
        .notification-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .notification-overlay.active { display: flex; }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to   { opacity: 1; transform: scale(1); }
        }

        .notification-box {
            background: #ffffff;
            border-radius: 16px;
            padding: 40px 50px;
            max-width: 420px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: bounceIn 0.4s ease;
        }

        @keyframes bounceIn {
            0%   { opacity: 0; transform: scale(0.7) translateY(30px); }
            60%  { transform: scale(1.02) translateY(-5px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        .notification-box .icon-wrapper {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 32px;
        }

        .notification-box .icon-wrapper.success { background: #d1fae5; color: #10b981; }
        .notification-box .icon-wrapper.error   { background: #fee2e2; color: #ef4444; }
        .notification-box .icon-wrapper.warning { background: #fef3c7; color: #f59e0b; }
        .notification-box .icon-wrapper.info    { background: #dbeafe; color: #3b82f6; }

        .notification-box h4 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .notification-box p {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .notification-box .btn-close-notification {
            padding: 10px 32px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #3b82f6;
            color: white;
        }

        .notification-box .btn-close-notification:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .notification-box .btn-close-notification.success-btn { background: #10b981; }
        .notification-box .btn-close-notification.success-btn:hover {
            background: #059669;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .notification-box .btn-close-notification.error-btn { background: #ef4444; }
        .notification-box .btn-close-notification.error-btn:hover {
            background: #dc2626;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
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

        /* ========== FORM STYLES ========== */
        .account-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-group label {
            font-weight: 600;
            color: #475569;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label i {
            width: 18px;
            color: #3b82f6;
            font-size: 16px;
        }

        .form-group .form-control {
            width: 100%;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            background: #f8fafc;
            transition: all 0.3s ease;
            color: #0f172a;
            outline: none;
        }

        .form-group .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            background: #ffffff;
        }

        .form-group .form-control:hover { border-color: #94a3b8; }

        .form-group .form-control:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .form-group .form-control::placeholder {
            color: #94a3b8;
            font-size: 13px;
        }

        .field-hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            margin-left: 8px;
        }

        .status-badge.verified   { color: #065f46; }
        .status-badge.unverified { color: #991b1b; }

        /* ========== FORM ROW (2 columns) ========== */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* ========== CHECKBOX GROUP (Type of Product) ========== */
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-top: 6px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
            font-size: 13px;
            font-weight: 500;
            color: #475569;
        }

        .checkbox-item:hover {
            border-color: #94a3b8;
            background: #f1f5f9;
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #3b82f6;
            cursor: pointer;
            flex-shrink: 0;
        }

        .checkbox-item input[type="checkbox"]:checked + span {
            color: #1d4ed8;
            font-weight: 600;
        }

        .checkbox-item:has(input:checked) {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        /* ========== SUBMIT BUTTON ========== */
        .submit-container {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-edit {
            padding: 14px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
            min-width: 160px;
            justify-content: center;
            text-decoration: none;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(245, 158, 11, 0.4);
        }

        .btn-edit:active { transform: scale(0.95); }

        /* ========== BOTTOM NAVIGATION ========== */
        .bottom-nav {
            position: fixed;
            bottom: 0; left: 0; right: 0;
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

        .bottom-nav .nav-item:hover { color: #3b82f6; }
        .bottom-nav .nav-item.active { color: #3b82f6; }

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
            .main-content { padding: 15px 15px 20px; }

            .dashboard-header {
                padding: 14px 18px;
                flex-direction: row;
                flex-wrap: wrap;
                gap: 8px;
            }

            .welcome h3 { font-size: 17px; }
            .user-badge .name { font-size: 12px; }

            .account-card { padding: 20px; }

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

            .account-card .profile-name { font-size: 20px; }

            .form-row { grid-template-columns: 1fr; gap: 12px; }
            .checkbox-grid { grid-template-columns: 1fr; }

            .btn-edit {
                width: 100%;
                padding: 14px 20px;
                font-size: 15px;
            }

            .submit-container { justify-content: stretch; }
            .notification-box { padding: 30px 24px; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px 12px 16px; }
            body { padding-bottom: 60px; }

            .account-card { padding: 14px; border-radius: 8px; }

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

            .account-card .profile-name { font-size: 17px; }
            .account-card .profile-acc  { font-size: 12px; }

            .form-group label { font-size: 12px; }

            .form-group .form-control {
                font-size: 13px;
                padding: 10px 12px;
                border-radius: 6px;
            }

            .checkbox-item { font-size: 12px; padding: 9px 10px; }

            .field-hint { font-size: 11px; }

            .btn-edit { font-size: 14px; padding: 12px 16px; }

            .dashboard-header { padding: 12px 14px; border-radius: 5px; }
            .welcome h3 { font-size: 15px; }

            .user-badge .avatar { width: 28px; height: 28px; font-size: 12px; }
            .user-badge .name { font-size: 11px; }

            .bottom-nav { padding: 4px 0 8px; height: 56px; }
            .bottom-nav .nav-item { padding: 2px 6px; min-width: 36px; }
            .bottom-nav .nav-item i { font-size: 18px; }
            .bottom-nav .nav-item span { font-size: 9px; }
            .bottom-nav .nav-item .badge {
                font-size: 10px;
                min-width: 14px;
                line-height: 14px;
                top: -2px;
                right: 0px;
                padding: 0 5px;
            }

            .notification-box { padding: 24px 18px; }
            .notification-box .icon-wrapper { width: 60px; height: 60px; font-size: 26px; }
            .notification-box h4 { font-size: 18px; }
            .notification-box p { font-size: 13px; }
        }

        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .bottom-nav { padding-bottom: calc(12px + env(safe-area-inset-bottom)); }
        }

        @media print {
            body { padding-bottom: 0; }
            .bottom-nav { display: none; }
            .btn-edit { display: none !important; }
            .account-card { box-shadow: none; border: 1px solid #e2e8f0; }
            .main-content { padding: 10px; }
        }
    </style>
</head>

<body>

    <!-- ========== NOTIFICATION MODAL ========== -->
    <div class="notification-overlay" id="notificationOverlay">
        <div class="notification-box">
            <div class="icon-wrapper" id="notifIcon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h4 id="notifTitle">Success!</h4>
            <p id="notifMessage">Your account has been updated successfully.</p>
            <button class="btn-close-notification" id="notifBtn">Okay</button>
        </div>
    </div>

    <!-- ========== MAIN CONTENT ========== -->
    <main class="main-content">
        <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">
        <input type="hidden" id="userAccNumber" value="<?php echo htmlspecialchars($userAccNumber); ?>">

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome">
                <h3><i class="fas fa-handshake"></i> Partnership</h3>
            </div>
            <div class="user-badge">
                <div class="avatar <?php echo $isVip ? 'vip' : ''; ?>">
                    <?php if ($isVip): ?>
                        <i class="fas fa-crown"></i>
                    <?php else: ?>
                        <?php echo strtoupper(substr($userFullName ?: 'G', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <span class="name"><?php echo htmlspecialchars($userFullName ?: 'Guest'); ?></span>
            </div>
        </div>

        <!-- Account Details Card -->
        <div class="account-card">

            <form class="account-form" id="accountForm">
                <!-- ===== BUSINESS INFORMATION SECTION ===== -->
                <div style="margin-bottom: 12px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    <h4 style="color: #0f172a; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-user-circle" style="color: #3b82f6;"></i> Business Information
                    </h4>
                </div>

                <div class="form-group">
                    <label>Business Name</label>
                    <input type="text" class="form-control" id="f_name" name="f_name"
                        value="<?php echo htmlspecialchars($userFullName); ?>"
                        placeholder="Enter your business name" required disabled>
                    <span class="field-hint">Enter your business name as it appears on official documents.</span>
                </div>

                <!-- ===== ADDRESS INFORMATION SECTION ===== -->
                <div style="margin: 16px 0 12px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    <h4 style="color: #0f172a; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-map-marker-alt" style="color: #3b82f6;"></i> Address Information
                    </h4>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Business Place (Street)</label>
                        <input type="text" class="form-control" id="street" name="street"
                            value="<?php echo htmlspecialchars($userStreet); ?>"
                            placeholder="Enter your street / business place" required disabled>
                        <span class="field-hint">House number, street name, subdivision, etc.</span>
                    </div>

                    <div class="form-group">
                        <label>Barangay</label>
                        <input type="text" class="form-control" id="barangay" name="barangay"
                            value="<?php echo htmlspecialchars($userBarangay); ?>"
                            placeholder="Enter your barangay" required disabled>
                        <span class="field-hint">Barangay where your business is located.</span>
                    </div>
                </div>

                <!-- ===== TYPE OF PRODUCT (CHECKBOX GROUP) ===== -->
                <div style="margin: 16px 0 12px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    <h4 style="color: #0f172a; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-tags" style="color: #3b82f6;"></i> Type of Product
                    </h4>
                </div>

                <div class="form-group">
                    <label>Select the product types you sell</label>
                    <div class="checkbox-grid">
                        <?php foreach ($productTypes as $index => $type): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="product_types[]" value="<?php echo htmlspecialchars($type); ?>" disabled>
                                <span><?php echo htmlspecialchars($type); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <span class="field-hint">You can select more than one.</span>
                </div>

                <!-- ===== CONTACT INFORMATION SECTION ===== -->
                <div style="margin: 16px 0 12px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    <h4 style="color: #0f172a; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-address-card" style="color: #3b82f6;"></i> Contact Information
                    </h4>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            Email Address
                            <?php if ($isEmailVerified): ?>
                                <span class="status-badge verified">Verified</span>
                            <?php else: ?>
                                <span class="status-badge unverified">Unverified</span>
                            <?php endif; ?>
                        </label>
                        <input type="email" class="form-control" id="email" name="email"
                            value="<?php echo htmlspecialchars($userEmail); ?>"
                            placeholder="Enter your email" disabled>
                        <span class="field-hint">Used for order updates and account recovery.</span>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" class="form-control" id="phone_number" name="phone_number"
                            value="<?php echo htmlspecialchars($userContact); ?>"
                            placeholder="Enter your phone number" disabled>
                        <span class="field-hint">Include country code if outside the Philippines.</span>
                    </div>
                </div>

                <!-- ===== EDIT / REGISTER BUTTON ===== -->
                <div class="submit-container">
                    <a href="account-edit.php" class="btn-edit">
                        Register my Business
                    </a>
                </div>
            </form>
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
        <a href="closed.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <script>
        // Check for success message from edit page
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');

            if (success === '1') {
                showNotification('Account updated successfully!', 'success');
                const newUrl = window.location.pathname + window.location.search.replace(/[?&]success=1/, '').replace(/^&/, '?');
                window.history.replaceState({}, document.title, newUrl);
            }
        });

        // ============================================================
        // NOTIFICATION MODAL
        // ============================================================
        let isReloading = false;

        function showNotification(message, type = 'success', title = '') {
            const overlay = document.getElementById('notificationOverlay');
            const icon    = document.getElementById('notifIcon');
            const titleEl = document.getElementById('notifTitle');
            const msgEl   = document.getElementById('notifMessage');
            const btn     = document.getElementById('notifBtn');

            icon.className = 'icon-wrapper';

            if (type === 'success') {
                icon.classList.add('success');
                icon.innerHTML = '<i class="fas fa-check-circle"></i>';
                titleEl.textContent = title || 'Success!';
                btn.className = 'btn-close-notification success-btn';
            } else if (type === 'error') {
                icon.classList.add('error');
                icon.innerHTML = '<i class="fas fa-times-circle"></i>';
                titleEl.textContent = title || 'Error!';
                btn.className = 'btn-close-notification error-btn';
            } else if (type === 'warning') {
                icon.classList.add('warning');
                icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                titleEl.textContent = title || 'Warning!';
                btn.className = 'btn-close-notification';
            } else {
                icon.classList.add('info');
                icon.innerHTML = '<i class="fas fa-info-circle"></i>';
                titleEl.textContent = title || 'Info';
                btn.className = 'btn-close-notification';
            }

            msgEl.textContent = message;
            overlay.classList.add('active');

            clearTimeout(window.notificationTimeout);
            window.notificationTimeout = setTimeout(() => {
                closeNotification();
            }, 5000);
        }

        function closeNotification() {
            document.getElementById('notificationOverlay').classList.remove('active');
            clearTimeout(window.notificationTimeout);
        }

        document.getElementById('notifBtn').addEventListener('click', function () {
            closeNotification();
        });

        document.getElementById('notificationOverlay').addEventListener('click', function (e) {
            if (e.target === this) {
                closeNotification();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeNotification();
            }
        });
    </script>

</body>

</html>