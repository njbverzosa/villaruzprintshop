<?php
// public/account-edit.php

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

// Fetch user details from database
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
$cartTotalItems = intval($cartCountResult['total_items'] ?? 0);

// ==============================================
// 6. SET USER VARIABLES
// ==============================================
$userAccNumber = $user['acc_number'] ?? $accNumber;
$userFullName = $user['f_name'] ?? '';
$userStreet = $user['street'] ?? '';
$userBarangay = $user['barangay'] ?? '';
$userLandMark = $user['landmark_photo'] ?? '';
$userEmail = $user['email'] ?? '';
$userContact = $user['phone_number'] ?? '';
$isEmailVerified = $user['active_email'] ?? 0;
$isVip = isset($user['vip']) && $user['vip'] == 1;

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Check if user is Guest (no name set)
$isGuest = ($userFullName === 'Guest' || empty($userFullName));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Edit Account | Villaruz Print Shop & General Merchandise</title>
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
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .notification-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
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
            0% {
                opacity: 0;
                transform: scale(0.7) translateY(30px);
            }

            60% {
                transform: scale(1.02) translateY(-5px);
            }

            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
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

        .notification-box .icon-wrapper.success {
            background: #d1fae5;
            color: #10b981;
        }

        .notification-box .icon-wrapper.error {
            background: #fee2e2;
            color: #ef4444;
        }

        .notification-box .icon-wrapper.warning {
            background: #fef3c7;
            color: #f59e0b;
        }

        .notification-box .icon-wrapper.info {
            background: #dbeafe;
            color: #3b82f6;
        }

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

        .notification-box .btn-close-notification.success-btn {
            background: #10b981;
        }

        .notification-box .btn-close-notification.success-btn:hover {
            background: #059669;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .notification-box .btn-close-notification.error-btn {
            background: #ef4444;
        }

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

        .form-group label .field-required {
            color: #ef4444;
            margin-left: 2px;
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

        .form-group .form-control:hover {
            border-color: #94a3b8;
        }

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

        .status-badge.verified {
            color: #065f46;
        }

        .status-badge.unverified {
            color: #991b1b;
        }

        /* ========== FORM ROW (2 columns) ========== */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
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

        .btn-submit {
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
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            min-width: 160px;
            justify-content: center;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-submit:active:not(:disabled) {
            transform: scale(0.95);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-submit .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .btn-submit.loading .spinner {
            display: inline-block;
        }

        .btn-submit.loading .btn-text {
            display: none;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .btn-cancel {
            padding: 14px 30px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            color: #475569;
            min-width: 120px;
            justify-content: center;
            text-decoration: none;
        }

        .btn-cancel:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        /* ========== LANDMARK PHOTO UPLOAD ========== */
        .upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8fafc;
            width: 100%;
        }

        .upload-area:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .upload-area.dragover {
            border-color: #3b82f6;
            background: #dbeafe;
            transform: scale(1.02);
        }

        .upload-area i {
            font-size: 40px;
            color: #94a3b8;
            margin-bottom: 10px;
            display: block;
        }

        .upload-area:hover i {
            color: #3b82f6;
        }

        .upload-area p {
            color: #475569;
            margin-bottom: 4px;
            font-weight: 500;
        }

        .upload-area small {
            color: #94a3b8;
            font-size: 12px;
        }

        .landmark-photo-wrapper {
            position: relative;
            display: inline-block;
        }

        .remove-photo-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
            transition: all 0.3s;
        }

        .remove-photo-btn:hover {
            background: #dc2626;
            transform: scale(1.1);
        }

        .photo-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        .photo-input-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .photo-input-wrapper .upload-area {
            flex: 1;
            padding: 15px 20px;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .photo-input-wrapper .upload-area i {
            font-size: 28px;
            margin-bottom: 4px;
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

            .account-card {
                padding: 20px;
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

            .form-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .btn-submit,
            .btn-cancel {
                width: 100%;
                padding: 14px 20px;
                font-size: 15px;
            }

            .submit-container {
                justify-content: stretch;
            }

            .notification-box {
                padding: 30px 24px;
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

            .form-group label {
                font-size: 12px;
            }

            .form-group .form-control {
                font-size: 13px;
                padding: 10px 12px;
                border-radius: 6px;
            }

            .field-hint {
                font-size: 11px;
            }

            .btn-submit,
            .btn-cancel {
                font-size: 14px;
                padding: 12px 16px;
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

            .photo-input-wrapper {
                flex-direction: column;
                align-items: stretch;
            }

            .photo-preview {
                max-width: 100%;
                max-height: 120px;
            }

            .notification-box {
                padding: 24px 18px;
            }

            .notification-box .icon-wrapper {
                width: 60px;
                height: 60px;
                font-size: 26px;
            }

            .notification-box h4 {
                font-size: 18px;
            }

            .notification-box p {
                font-size: 13px;
            }
        }

        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .bottom-nav {
                padding-bottom: calc(12px + env(safe-area-inset-bottom));
            }
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
                <h3><i class="fas fa-edit"></i> Edit Account</h3>
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

            <form class="account-form" id="accountForm" method="POST" enctype="multipart/form-data">
                <!-- Hidden action field -->
                <input type="hidden" name="action" value="update_all_fields">

                <!-- ===== PERSONAL INFORMATION SECTION ===== -->
                <div style="margin-bottom: 12px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    <h4 style="color: #0f172a; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-user-circle" style="color: #3b82f6;"></i> Personal Information
                    </h4>
                </div>

                <!-- Full Name -->
                <div class="form-group">
                    <label>
                        Full Name <span class="field-required">*</span>
                    </label>
                    <input type="text" class="form-control" id="f_name" name="f_name"
                        value="<?php echo htmlspecialchars($userFullName); ?>" placeholder="Enter your full name"
                        required>
                    <span class="field-hint">Enter your full name as it appears on official documents.</span>
                </div>

                <!-- ===== ADDRESS INFORMATION SECTION ===== -->
                <div style="margin: 16px 0 12px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    <h4 style="color: #0f172a; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-map-marker-alt" style="color: #3b82f6;"></i> Address Information
                    </h4>
                </div>

                <!-- Street & Barangay (2 columns) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>
                            Street <span class="field-required">*</span>
                        </label>
                        <input type="text" class="form-control" id="street" name="street"
                            value="<?php echo htmlspecialchars($userStreet); ?>" placeholder="Enter your street name"
                            required>
                        <span class="field-hint">House number, street name, subdivision, etc.</span>
                    </div>

                    <div class="form-group">
                        <label>
                            Barangay <span class="field-required">*</span>
                        </label>
                        <input type="text" class="form-control" id="barangay" name="barangay"
                            value="<?php echo htmlspecialchars($userBarangay); ?>" placeholder="Enter your barangay"
                            required>
                        <span class="field-hint">Your barangay or district.</span>
                    </div>
                </div>

                <!-- ===== CONTACT INFORMATION SECTION ===== -->
                <div style="margin: 16px 0 12px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    <h4 style="color: #0f172a; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-address-card" style="color: #3b82f6;"></i> Contact Information
                    </h4>
                </div>

                <!-- Email & Contact Number (2 columns) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>
                            Email Address <span class="field-required">*</span>
                            <?php if ($isEmailVerified): ?>
                                <span class="status-badge verified">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                            <?php else: ?>
                                <span class="status-badge unverified">
                                    <i class="fas fa-exclamation-circle"></i>
                                </span>
                            <?php endif; ?>
                        </label>
                        <input type="email" class="form-control" id="email" name="email"
                            value="<?php echo htmlspecialchars($userEmail); ?>" placeholder="Enter your email address"
                            required>
                        <span class="field-hint">We'll send order confirmations and updates to this email.</span>
                    </div>

                    <div class="form-group">
                        <label>
                            Contact Number <span class="field-required">*</span>
                        </label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number"
                            value="<?php echo htmlspecialchars($userContact); ?>"
                            placeholder="Enter your contact number" disabled readonly>
                        <span class="field-hint">Contact number cannot be changed. Please contact support for
                            updates.</span>
                    </div>
                </div>

                <!-- ===== DELIVERY INFORMATION SECTION ===== -->
                <div style="margin: 16px 0 12px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    <h4 style="color: #0f172a; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-truck" style="color: #3b82f6;"></i> Delivery Information
                    </h4>
                </div>

                <!-- Landmark Photo -->
                <div class="form-group">
                    <label>
                        Landmark Photo
                    </label>
                    <div class="photo-input-wrapper">
                        <div class="upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click or drag to upload</p>
                            <small>JPG, JPEG, PNG (Max 5MB)</small>
                            <input type="file" id="landmarkFileInput" name="landmark_photo" accept=".jpg,.jpeg,.png"
                                style="display: none;">
                        </div>
                        <?php if (!empty($userLandMark) && file_exists(__DIR__ . '/../' . $userLandMark)): ?>
                            <div class="landmark-photo-wrapper">
                                <img src="../<?php echo htmlspecialchars($userLandMark); ?>" alt="Landmark Photo"
                                    class="photo-preview" id="landmarkPreview">
                                <button type="button" class="remove-photo-btn" onclick="deleteLandmarkPhoto()"
                                    title="Remove photo">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div id="previewContainer" style="display: none; margin-top: 10px;">
                        <img id="previewImage" src="#" alt="Preview" class="photo-preview">
                        <button type="button" class="remove-photo-btn" onclick="cancelPhotoUpload()"
                            title="Cancel upload"
                            style="position: relative; top: auto; right: auto; margin-top: 8px; display: inline-flex;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                    <span class="field-hint">Upload a photo of your landmark to help delivery drivers find you
                        easily.</span>
                </div>

                <!-- ===== SUBMIT BUTTON ===== -->
                <div class="submit-container">
                    <a href="account-details.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span class="spinner"></span>
                        <span class="btn-text">Save Changes</span>
                    </button>
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
        <a href="closed.php" class="nav-item" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <script>
        const csrfToken = document.getElementById('csrfToken').value;
        const accNumber = document.getElementById('userAccNumber').value;

        // ============================================================
        // NOTIFICATION MODAL
        // ============================================================
        let isRedirecting = false;

        function showNotification(message, type = 'success', title = '') {
            const overlay = document.getElementById('notificationOverlay');
            const icon = document.getElementById('notifIcon');
            const titleEl = document.getElementById('notifTitle');
            const msgEl = document.getElementById('notifMessage');
            const btn = document.getElementById('notifBtn');

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

            if (type === 'success') {
                isRedirecting = true;
            }

            clearTimeout(window.notificationTimeout);
            window.notificationTimeout = setTimeout(() => {
                closeNotification();
            }, 5000);
        }

        function closeNotification() {
            document.getElementById('notificationOverlay').classList.remove('active');
            clearTimeout(window.notificationTimeout);

            if (isRedirecting) {
                window.location.href = 'account-details.php';
            }
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

        // ============================================================
        // FORM SUBMISSION
        // ============================================================
        document.getElementById('accountForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const formData = new FormData(this);

            // Validate required fields
            const requiredFields = document.querySelectorAll('.form-control[required]');
            let isValid = true;
            let firstInvalid = null;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#ef4444';
                    field.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.15)';
                    if (!firstInvalid) firstInvalid = field;
                } else {
                    field.style.borderColor = '';
                    field.style.boxShadow = '';
                }
            });

            if (!isValid) {
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                showNotification('Please fill in all required fields.', 'error');
                return;
            }

            // Email validation
            const emailInput = document.getElementById('email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailInput.value.trim())) {
                showNotification('Please enter a valid email address.', 'error');
                emailInput.focus();
                emailInput.style.borderColor = '#ef4444';
                emailInput.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.15)';
                return;
            }

            // Add CSRF token to form data
            formData.append('csrf_token', csrfToken);
            formData.append('acc_number', accNumber);

            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;

            try {
                const response = await fetch('../Customer_API/edit_account.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;

                if (data.success) {
                    showNotification(data.message || 'Account updated successfully!', 'success');
                } else {
                    showNotification(data.message || 'Error updating account.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
                showNotification('Network error. Please try again.', 'error');
            }
        });

        // ============================================================
        // LANDMARK PHOTO UPLOAD
        // ============================================================
        let selectedFile = null;

        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('landmarkFileInput');

        if (uploadArea && fileInput) {
            uploadArea.addEventListener('click', function (e) {
                e.stopPropagation();
                fileInput.click();
            });

            uploadArea.addEventListener('dragover', function (e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', function (e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', function (e) {
                e.preventDefault();
                this.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleFileSelect(files[0]);
                }
            });

            fileInput.addEventListener('change', function (e) {
                if (this.files.length > 0) {
                    handleFileSelect(this.files[0]);
                }
            });
        }

        function handleFileSelect(file) {
            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!allowedTypes.includes(file.type)) {
                showNotification('Only JPG, JPEG, and PNG files are allowed', 'error');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                showNotification('File size exceeds 5MB limit', 'error');
                return;
            }

            selectedFile = file;

            const previewContainer = document.getElementById('previewContainer');
            const previewImage = document.getElementById('previewImage');
            const reader = new FileReader();

            reader.onload = function (e) {
                previewImage.src = e.target.result;
                previewContainer.style.display = 'block';
                if (uploadArea) uploadArea.style.display = 'none';
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;
            };
            reader.readAsDataURL(file);
        }

        function cancelPhotoUpload() {
            selectedFile = null;
            document.getElementById('previewContainer').style.display = 'none';
            document.getElementById('previewImage').src = '#';
            if (uploadArea) uploadArea.style.display = 'block';
            fileInput.value = '';
        }

        async function deleteLandmarkPhoto() {
            if (!confirm('Are you sure you want to remove this photo?')) return;

            try {
                const formData = new FormData();
                formData.append('action', 'delete_landmark_photo');
                formData.append('csrf_token', csrfToken);
                formData.append('acc_number', accNumber);

                const response = await fetch('../Customer_API/edit_account.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, 'success');
                    const wrapper = document.querySelector('.landmark-photo-wrapper');
                    if (wrapper) wrapper.remove();
                    if (uploadArea) uploadArea.style.display = 'block';
                } else {
                    showNotification(data.message || 'Failed to remove photo', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error. Please try again.', 'error');
            }
        }

        // ============================================================
        // REAL-TIME VALIDATION
        // ============================================================
        document.querySelectorAll('.form-control[required]').forEach(field => {
            field.addEventListener('blur', function () {
                if (this.disabled) return;
                if (this.value.trim()) {
                    this.style.borderColor = '#22c55e';
                    this.style.boxShadow = '0 0 0 4px rgba(34, 197, 94, 0.15)';
                } else {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                }
            });

            field.addEventListener('focus', function () {
                if (this.disabled) return;
                this.style.borderColor = '#3b82f6';
                this.style.boxShadow = '0 0 0 4px rgba(59, 130, 246, 0.15)';
            });

            field.addEventListener('input', function () {
                if (this.disabled) return;
                if (this.value.trim()) {
                    this.style.borderColor = '#22c55e';
                    this.style.boxShadow = '0 0 0 4px rgba(34, 197, 94, 0.15)';
                } else {
                    this.style.borderColor = '#e2e8f0';
                    this.style.boxShadow = 'none';
                }
            });
        });

        document.getElementById('email').addEventListener('input', function () {
            if (this.disabled) return;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value.trim() && emailRegex.test(this.value.trim())) {
                this.style.borderColor = '#22c55e';
                this.style.boxShadow = '0 0 0 4px rgba(34, 197, 94, 0.15)';
            } else if (this.value.trim()) {
                this.style.borderColor = '#ef4444';
                this.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.15)';
            } else {
                this.style.borderColor = '#e2e8f0';
                this.style.boxShadow = 'none';
            }
        });
    </script>

</body>

</html>