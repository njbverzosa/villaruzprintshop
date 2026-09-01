<?php
// public/dtr.php

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

// ==============================================
// 4. USE $userData
// ==============================================
$user = $userData;

// ==============================================
// 5. UPDATE ONLINE TIME AFTER USER IS DEFINED
// ==============================================
date_default_timezone_set('Asia/Manila');
$currentTime = date('M j, g:i A');

if ($userRole === 'Customer') {
    $updateStmt = $pdo->prepare("UPDATE customers SET online_time = ? WHERE id = ?");
    $updateStmt->execute([$currentTime, $userData['id']]);
}

// ==============================================
// 6. GET CART COUNT FOR BOTTOM NAV
// ==============================================
$cartCountStmt = $pdo->prepare("SELECT SUM(pieces) as total_items FROM cart WHERE acc_number = ?");
$cartCountStmt->execute([$accNumber]);
$cartCountResult = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
$cartTotalItems = intval($cartCountResult['total_items'] ?? 0);

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

// ==============================================
// 7. DTR - FETCH TODAY'S RECORDS
// ==============================================
$today = date('Y-m-d');
$dtrRecords = [];

// Check if table exists, if not create it
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS dtr (
        id INT AUTO_INCREMENT PRIMARY KEY,
        acc_number VARCHAR(50) NOT NULL,
        user_id INT NOT NULL,
        date DATE NOT NULL,
        time_in TIME,
        time_out TIME,
        status ENUM('present', 'absent', 'late', 'half_day') DEFAULT 'present',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_acc_date (acc_number, date),
        INDEX idx_user_date (user_id, date)
    )");
    
    // Add photo column if not exists
    $pdo->exec("ALTER TABLE dtr ADD COLUMN IF NOT EXISTS time_in_photo VARCHAR(255) NULL, ADD COLUMN IF NOT EXISTS time_out_photo VARCHAR(255) NULL");
} catch (PDOException $e) {
    // Table might already exist or column already exists
}

// Fetch today's DTR record for this user
$dtrStmt = $pdo->prepare("SELECT * FROM dtr WHERE acc_number = ? AND date = ?");
$dtrStmt->execute([$accNumber, $today]);
$dtrToday = $dtrStmt->fetch(PDO::FETCH_ASSOC);

// Fetch this month's DTR records
$monthStart = date('Y-m-01');
$monthStmt = $pdo->prepare("SELECT * FROM dtr WHERE acc_number = ? AND date >= ? ORDER BY date DESC");
$monthStmt->execute([$accNumber, $monthStart]);
$dtrRecords = $monthStmt->fetchAll(PDO::FETCH_ASSOC);

$isCheckedIn = false;
$checkedInTime = null;
$isCheckedOut = false;

if ($dtrToday) {
    if ($dtrToday['time_in'] && !$dtrToday['time_out']) {
        $isCheckedIn = true;
        $checkedInTime = $dtrToday['time_in'];
    }
    if ($dtrToday['time_out']) {
        $isCheckedOut = true;
    }
}

// ==============================================
// 8. CHECK WORKING HOURS - REMOVED LIMIT FOR TESTING
// ==============================================
$isDtrDisabled = false; // Always enabled
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>DTR | Villaruz Print Shop & General Merchandise</title>
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

        .toast-message.warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .toast-message.warning i {
            color: #f59e0b;
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

        /* ========== DTR CARD ========== */
        .dtr-card {
            background: white;
            border-radius: 5px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 850px;
            margin: 0 auto;
            width: 100%;
            border: 1px solid #e2e8f0;
        }

        /* ========== HEXAGON CAMERA PREVIEW ========== */
        .camera-preview-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 10px 0 20px;
            padding: 5px;
        }

        .hexagon-wrapper {
            position: relative;
            width: 200px;
            height: 200px;
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            -webkit-clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            overflow: hidden;
            background: #1e293b;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 3px solid #3b82f6;
            transition: all 0.3s ease;
        }

        .hexagon-wrapper:hover {
            transform: scale(1.02);
            border-color: #22c55e;
        }

        .hexagon-wrapper video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scale(1.1);
            display: none;
        }

        .hexagon-wrapper video.active {
            display: block;
        }

        .hexagon-wrapper .camera-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #94a3b8;
            font-size: 14px;
            text-align: center;
            padding: 20px;
        }

        .hexagon-wrapper .camera-placeholder.hidden {
            display: none;
        }

        .hexagon-wrapper .camera-placeholder i {
            font-size: 48px;
            color: #3b82f6;
            margin-bottom: 10px;
            opacity: 0.6;
        }

        .hexagon-wrapper .camera-placeholder .status-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        .hexagon-wrapper .camera-status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            z-index: 5;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .hexagon-wrapper .camera-status-badge .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .hexagon-wrapper .camera-status-badge .dot.active {
            background: #22c55e;
            animation: pulse 1.5s infinite;
        }

        .hexagon-wrapper .camera-status-badge .dot.inactive {
            background: #ef4444;
        }

        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* ========== CAPTURE OVERLAY ========== */
        .capture-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            z-index: 9998;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .capture-overlay.active {
            display: flex;
        }

        .capture-overlay .capture-spinner {
            width: 80px;
            height: 80px;
            border: 6px solid rgba(255, 255, 255, 0.1);
            border-top-color: #22c55e;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .capture-overlay .capture-text {
            color: #fff;
            margin-top: 20px;
            font-size: 18px;
            font-weight: 500;
        }

        .capture-overlay .capture-subtext {
            color: #94a3b8;
            margin-top: 8px;
            font-size: 14px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ========== DTR BUTTONS ========== */
        .dtr-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .btn-dtr {
            padding: 14px 32px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 160px;
            justify-content: center;
        }

        .btn-dtr:active {
            transform: scale(0.95);
        }

        .btn-dtr.time-in {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
        }

        .btn-dtr.time-in:hover:not(:disabled) {
            box-shadow: 0 6px 25px rgba(34, 197, 94, 0.4);
            transform: translateY(-2px);
        }

        .btn-dtr.time-in:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-dtr.time-out {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-dtr.time-out:hover:not(:disabled) {
            box-shadow: 0 6px 25px rgba(239, 68, 68, 0.4);
            transform: translateY(-2px);
        }

        .btn-dtr.time-out:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-dtr .icon {
            font-size: 20px;
        }

        /* ========== TODAY'S LOG ========== */
        .today-log {
            background: #f8fafc;
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .today-log .label {
            font-weight: 600;
            color: #475569;
        }

        .today-log .value {
            color: #0f172a;
            font-weight: 500;
        }

        .today-log .value .highlight {
            color: #3b82f6;
            font-weight: 700;
        }

        /* ========== DTR NOTICE ========== */
        .dtr-notice {
            background: #fef3c7;
            padding: 12px 18px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .dtr-notice i {
            font-size: 20px;
            color: #f59e0b;
        }

        .dtr-notice strong {
            font-weight: 700;
        }

        /* ========== DTR LOG TABLE ========== */
        .dtr-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin: 20px 0 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dtr-section-title i {
            color: #3b82f6;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        .dtr-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 500px;
        }

        .dtr-table th {
            background: #f1f5f9;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }

        .dtr-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }

        .dtr-table tr:hover td {
            background: #f8fafc;
        }

        .no-records {
            text-align: center;
            padding: 30px;
            color: #94a3b8;
            font-size: 14px;
        }

        .no-records i {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
            color: #cbd5e1;
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

            .dtr-card {
                padding: 20px;
            }

            .btn-dtr {
                padding: 12px 20px;
                font-size: 14px;
                min-width: 140px;
            }

            .dtr-table {
                font-size: 12px;
                min-width: 400px;
            }

            .dtr-table th,
            .dtr-table td {
                padding: 8px 12px;
            }

            .hexagon-wrapper {
                width: 160px;
                height: 160px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 12px 12px 16px;
            }

            body {
                padding-bottom: 60px;
            }

            .dtr-card {
                padding: 14px;
                border-radius: 8px;
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

            .btn-dtr {
                padding: 10px 16px;
                font-size: 13px;
                min-width: 120px;
                border-radius: 8px;
            }

            .btn-dtr .icon {
                font-size: 16px;
            }

            .today-log {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
                padding: 12px 16px;
            }

            .dtr-notice {
                font-size: 12px;
                padding: 10px 14px;
                flex-wrap: wrap;
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

            .dtr-table {
                font-size: 11px;
                min-width: 320px;
            }

            .dtr-table th,
            .dtr-table td {
                padding: 6px 10px;
            }

            .hexagon-wrapper {
                width: 140px;
                height: 140px;
            }
        }

        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .bottom-nav {
                padding-bottom: calc(12px + env(safe-area-inset-bottom));
            }
        }

        /* ========== PRINT STYLES ========== */
        @media print {

            .bottom-nav,
            .dashboard-header,
            .dtr-buttons,
            .toast-message,
            .dtr-notice,
            .no-print,
            .camera-preview-container {
                display: none !important;
            }

            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .main-content {
                padding: 20px !important;
                background: white !important;
            }

            .dtr-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                border-radius: 0 !important;
                padding: 20px !important;
                max-width: 100% !important;
            }

            .today-log {
                background: #f8fafc !important;
                border: 1px solid #ddd !important;
                padding: 12px 16px !important;
                margin-bottom: 20px !important;
            }

            .today-log .label {
                color: #333 !important;
            }

            .today-log .value {
                color: #000 !important;
            }

            .dtr-section-title {
                font-size: 14px !important;
                color: #000 !important;
                margin-top: 15px !important;
            }

            .dtr-section-title i {
                color: #000 !important;
            }

            .table-wrapper {
                border: 1px solid #ddd !important;
                overflow: visible !important;
            }

            .dtr-table {
                font-size: 12px !important;
                min-width: auto !important;
                width: 100% !important;
            }

            .dtr-table th {
                background: #f1f5f9 !important;
                color: #000 !important;
                border-bottom: 2px solid #000 !important;
                padding: 8px 12px !important;
            }

            .dtr-table td {
                padding: 8px 12px !important;
                border-bottom: 1px solid #e2e8f0 !important;
                color: #000 !important;
            }

            .dtr-table tr:hover td {
                background: transparent !important;
            }

            .no-records {
                padding: 20px !important;
                color: #666 !important;
            }

            .no-records i {
                color: #ccc !important;
            }

            .print-header {
                display: block !important;
                text-align: center;
                padding-bottom: 15px;
                border-bottom: 3px double #000;
                margin-bottom: 20px;
            }

            .print-header h1 {
                font-size: 22px;
                color: #000;
                margin: 0;
            }

            .print-header p {
                font-size: 13px;
                color: #333;
                margin: 4px 0 0;
            }

            .dtr-card {
                page-break-inside: avoid;
            }

            .dtr-table tr {
                page-break-inside: avoid;
            }

            .print-footer {
                display: block !important;
                text-align: center;
                font-size: 11px;
                color: #666;
                margin-top: 20px;
                padding-top: 10px;
                border-top: 1px solid #ddd;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }

        .print-header {
            display: none;
        }

        .print-footer {
            display: none;
        }
    </style>
</head>

<body>

    <!-- ========== CAPTURE OVERLAY ========== -->
    <div class="capture-overlay" id="captureOverlay">
        <div class="capture-spinner"></div>
        <div class="capture-text">Capturing Photo...</div>
        <div class="capture-subtext">Please wait while we capture your image</div>
    </div>

    <!-- ========== MAIN CONTENT ========== -->
    <main class="main-content">
        <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">
        <input type="hidden" id="userAccNumber" value="<?php echo htmlspecialchars($accNumber); ?>">

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome">
                <h3><i class="fas fa-clock"></i> Daily Time Record</h3>
            </div>
            <div class="user-badge">
                <div class="avatar <?php echo $isVip ? 'vip' : ''; ?>">
                    <?php if ($isVip): ?>
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

        <!-- ========== DTR CONTENT ========== -->
        <div class="dtr-card" id="dtrPrintArea">

            <!-- ========== DTR NOTICE ========== -->
            <div class="dtr-notice no-print">
                <i class="fas fa-info-circle"></i>
                <span>
                    This system is for official use only
                </span>
            </div>

            <!-- ========== HEXAGON CAMERA PREVIEW ========== -->
            <div class="camera-preview-container no-print">
                <div class="hexagon-wrapper" id="hexagonWrapper">
                    <video id="previewVideo" autoplay playsinline muted></video>
                    <div class="camera-placeholder" id="cameraPlaceholder">
                        <i class="fas fa-camera"></i>
                        <span>Camera Ready</span>
                        <span class="status-text">Click Time In/Out to capture</span>
                    </div>
                    <div class="camera-status-badge">
                        <span class="dot inactive" id="statusDot"></span>
                        <span id="statusText">Offline</span>
                    </div>
                </div>
            </div>

            <!-- Today's Log -->
            <div class="today-log">
                <span>
                    <span class="label"><i class="fas fa-today"></i> Today's Status:</span>
                    <?php if ($isCheckedOut): ?>
                        <span class="value">
                            <span class="highlight" style="color: #22c55e;">
                                Completed
                            </span>
                        </span>
                    <?php elseif ($isCheckedIn): ?>
                        <?php
                        $checkInTimeStr = $dtrToday['time_in'] ?? null;
                        ?>
                        <span class="value">
                            <span class="highlight" style="color: #eab308;">
                                Clocked In at <?php echo $checkInTimeStr ? date('h:i A', strtotime($checkInTimeStr)) : 'N/A'; ?>
                            </span>
                        </span>
                    <?php else: ?>
                        <span class="value" style="color: #94a3b8;">Not clocked in yet</span>
                    <?php endif; ?>
                </span>
            </div>

            <!-- DTR Buttons -->
            <div class="dtr-buttons no-print">
                <?php
                // Determine if both buttons should be disabled
                $disableButtons = $isDtrDisabled || $isCheckedOut;

                // Time In button: disabled if already checked in OR DTR is disabled
                $timeInDisabled = $isCheckedIn || $disableButtons;

                // Time Out button: disabled if not checked in OR already checked out OR DTR is disabled
                $timeOutDisabled = !$isCheckedIn || $isCheckedOut || $disableButtons;
                ?>

                <button class="btn-dtr time-in" id="timeInBtn" <?php echo $timeInDisabled ? 'disabled' : ''; ?>>
                    <i class="fas fa-sign-in-alt icon"></i>
                    <span>Time In</span>
                </button>

                <button class="btn-dtr time-out" id="timeOutBtn" <?php echo $timeOutDisabled ? 'disabled' : ''; ?>>
                    <i class="fas fa-sign-out-alt icon"></i>
                    <span>Time Out</span>
                </button>
            </div>

            <!-- DTR Log Table -->
            <div class="dtr-section-title">
                <i class="fas fa-list"></i> This Month's Records
                <span style="font-weight: 400; color: #94a3b8; font-size: 12px; margin-left: 4px;">
                    (<?php echo date('F Y'); ?>)
                </span>
            </div>

            <div class="table-wrapper">
                <table class="dtr-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Photo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($dtrRecords) > 0): ?>
                            <?php foreach ($dtrRecords as $record): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($record['date'])); ?></td>
                                    <td><?php echo $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '—'; ?>
                                    </td>
                                    <td><?php echo $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '—'; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($record['time_in_photo'])): ?>
                                            <a href="../DTR_Photos/<?php echo $record['time_in_photo']; ?>" target="_blank" title="Time In Photo">
                                                <i class="fas fa-camera" style="color: #3b82f6;"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($record['time_out_photo'])): ?>
                                            <a href="../DTR_Photos/<?php echo $record['time_out_photo']; ?>" target="_blank" title="Time Out Photo">
                                                <i class="fas fa-camera" style="color: #ef4444;"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (empty($record['time_in_photo']) && empty($record['time_out_photo'])): ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">
                                    <div class="no-records">
                                        <i class="far fa-calendar-alt"></i>
                                        No records found for this month.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Print Footer (only visible when printing) -->
            <div class="print-footer">
                <p>Generated on: <?php echo date('F j, Y h:i A'); ?></p>
                <p>This is a system-generated DTR report. <?php echo date('Y'); ?> &copy; Villaruz Print Shop & General
                    Merchandise</p>
            </div>
        </div>
    </main>

    <!-- ========== BOTTOM NAVIGATION ========== -->
    <nav class="bottom-nav no-print">
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
        // ============================================================
        // CONFIGURATION
        // ============================================================
        const csrfToken = document.getElementById('csrfToken').value;
        const accNumber = document.getElementById('userAccNumber').value;

        // ============================================================
        // CAMERA VARIABLES
        // ============================================================
        let previewStream = null;
        let isPreviewActive = false;
        let isProcessing = false;

        // ============================================================
        // DOM ELEMENTS
        // ============================================================
        const previewVideo = document.getElementById('previewVideo');
        const cameraPlaceholder = document.getElementById('cameraPlaceholder');
        const statusDot = document.getElementById('statusDot');
        const statusText = document.getElementById('statusText');
        const captureOverlay = document.getElementById('captureOverlay');
        const timeInBtn = document.getElementById('timeInBtn');
        const timeOutBtn = document.getElementById('timeOutBtn');

        // ============================================================
        // TOAST MESSAGES
        // ============================================================
        document.addEventListener('DOMContentLoaded', function () {
            const toast = document.getElementById('toastMessage');
            if (toast) {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity 0.5s';
                    setTimeout(() => toast.remove(), 500);
                }, 5000);
            }
            
            // Auto-start camera on page load
            console.log('Page loaded, starting camera...');
            startCamera();
        });

        function showToast(message, type = 'success') {
            const existingToast = document.querySelector('.toast-message');
            if (existingToast) existingToast.remove();

            const toast = document.createElement('div');
            toast.className = 'toast-message ' + type;
            const icon = type === 'success' ? 'fa-check-circle' :
                type === 'warning' ? 'fa-exclamation-triangle' :
                type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle';
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
            }, 6000);
        }

        // ============================================================
        // CAMERA FUNCTIONS
        // ============================================================
        async function startCamera() {
            try {
                console.log('Attempting to start camera...');
                
                // Try to get camera with facingMode: 'user' (front camera)
                const constraints = {
                    video: {
                        facingMode: 'user',
                        width: { ideal: 640 },
                        height: { ideal: 480 }
                    },
                    audio: false
                };
                
                previewStream = await navigator.mediaDevices.getUserMedia(constraints);
                console.log('Camera stream obtained successfully');
                
                previewVideo.srcObject = previewStream;
                await previewVideo.play();
                console.log('Video playing');
                
                // Show video, hide placeholder
                previewVideo.classList.add('active');
                cameraPlaceholder.classList.add('hidden');
                
                // Update status
                statusDot.className = 'dot active';
                statusText.textContent = 'Live';
                isPreviewActive = true;
                
                console.log('Camera started successfully.');
            } catch (error) {
                console.error('Camera error:', error);
                
                // Try without facingMode constraint as fallback
                try {
                    console.log('Trying fallback constraints...');
                    const fallbackConstraints = {
                        video: true,
                        audio: false
                    };
                    
                    previewStream = await navigator.mediaDevices.getUserMedia(fallbackConstraints);
                    console.log('Fallback camera stream obtained');
                    
                    previewVideo.srcObject = previewStream;
                    await previewVideo.play();
                    
                    previewVideo.classList.add('active');
                    cameraPlaceholder.classList.add('hidden');
                    
                    statusDot.className = 'dot active';
                    statusText.textContent = 'Live';
                    isPreviewActive = true;
                    
                    console.log('Camera started with fallback constraints.');
                } catch (fallbackError) {
                    console.error('Fallback camera error:', fallbackError);
                    showToast('Unable to access camera. Please allow camera permissions and refresh.', 'error');
                    cameraPlaceholder.innerHTML = `
                        <i class="fas fa-camera" style="font-size: 48px; color: #ef4444; margin-bottom: 10px; opacity: 0.6;"></i>
                        <span style="color: #ef4444;">Camera Unavailable</span>
                        <span class="status-text" style="color: #94a3b8;">Please allow camera access</span>
                    `;
                }
            }
        }

        function stopCamera() {
            if (previewStream) {
                previewStream.getTracks().forEach(track => track.stop());
                previewStream = null;
            }
            
            previewVideo.srcObject = null;
            previewVideo.classList.remove('active');
            cameraPlaceholder.classList.remove('hidden');
            
            statusDot.className = 'dot inactive';
            statusText.textContent = 'Offline';
            isPreviewActive = false;
        }

        // ============================================================
        // CAPTURE AND SUBMIT
        // ============================================================
        function captureAndSubmit(action) {
            if (isProcessing) {
                showToast('Please wait, processing...', 'info');
                return;
            }

            if (!isPreviewActive || !previewStream) {
                showToast('Camera not available. Please refresh the page.', 'error');
                // Try to restart camera
                startCamera();
                return;
            }

            // Show overlay
            captureOverlay.classList.add('active');
            isProcessing = true;

            // Use a canvas to capture from the video
            const canvas = document.createElement('canvas');
            canvas.width = previewVideo.videoWidth || 640;
            canvas.height = previewVideo.videoHeight || 480;
            const context = canvas.getContext('2d');
            context.drawImage(previewVideo, 0, 0, canvas.width, canvas.height);

            // Convert to blob
            canvas.toBlob(function(blob) {
                if (blob) {
                    submitDtrWithPhoto(blob, action);
                } else {
                    showToast('Failed to capture photo. Please try again.', 'error');
                    captureOverlay.classList.remove('active');
                    isProcessing = false;
                }
            }, 'image/jpeg', 0.85);
        }

        // ============================================================
        // SUBMIT DTR WITH PHOTO
        // ============================================================
        async function submitDtrWithPhoto(photoBlob, action) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('acc_number', accNumber);
            formData.append('csrf_token', csrfToken);
            formData.append('photo', photoBlob, 'dtr_photo.jpg');

            try {
                const response = await fetch('../Customer_API/dtr_api.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                // Hide overlay
                captureOverlay.classList.remove('active');
                isProcessing = false;

                if (data.success) {
                    const msgType = action === 'time_in' ? (data.data?.is_late ? 'warning' : 'success') : 
                                   (data.data?.is_ot ? 'warning' : 'success');
                    showToast(data.message, msgType);
                    setTimeout(() => {
                        location.reload();
                    }, 2500);
                } else {
                    showToast(data.message || 'Error processing request', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
                captureOverlay.classList.remove('active');
                isProcessing = false;
            }
        }

        // ============================================================
        // TIME IN
        // ============================================================
        timeInBtn.addEventListener('click', function () {
            console.log('Time In button clicked');
            // Capture and submit
            captureAndSubmit('time_in');
        });

        // ============================================================
        // TIME OUT
        // ============================================================
        timeOutBtn.addEventListener('click', function () {
            console.log('Time Out button clicked');
            // Capture and submit
            captureAndSubmit('time_out');
        });

        // ============================================================
        // CLEANUP ON PAGE UNLOAD
        // ============================================================
        window.addEventListener('beforeunload', function() {
            stopCamera();
        });

        // ============================================================
        // RECOVER CAMERA IF TAB BECOMES VISIBLE AGAIN
        // ============================================================
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && !isPreviewActive) {
                console.log('Tab became visible, restarting camera...');
                startCamera();
            }
        });
    </script>

</body>

</html>