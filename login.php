<?php
// login.php – desktop + mobile + in-app flows
// ✅ 3 roles with per-role biometric pages:
//      Admin    → web/biometric.php
//      Investor → investors/biometric.php
//      Customer → public/biometric.php
// ✅ Biometric gate fires only when biometric_enrolled = 0 for that acc_number

// Set session lifetime
$sessionLifetime = 604800;
ini_set('session.cookie_lifetime', $sessionLifetime);
ini_set('session.gc_maxlifetime', $sessionLifetime);

session_start();
require_once __DIR__ . '/DB_Conn/config.php';
include __DIR__ . '/app_version.php';

// ==============================================
// ✅ DETECT PLATFORM
// ==============================================
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isInApp = (strpos($userAgent, 'SofiaApp') !== false);

function isMobileBrowser($userAgent)
{
    if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent)) {
        return true;
    }
    return false;
}

$isMobileBrowser = isMobileBrowser($userAgent) && !$isInApp;

// ==============================================
// ✅ VERSION MATCH (in-app only)
// ==============================================
$installedVersion = trim($_POST['installed_version'] ?? $_GET['installed_version'] ?? '');

if ($isInApp) {
    $installedNorm = preg_replace('/[^0-9.]/', '', $installedVersion);
    $latestNorm = preg_replace('/[^0-9.]/', '', $latestVersion);

    if (!empty($installedNorm) && $installedNorm !== 'unknown') {
        $appVersionMatch = version_compare($installedNorm, $latestNorm, '==');
    } else {
        $appVersionMatch = false;
    }
} else {
    $appVersionMatch = false;
}

$loginType = $isInApp ? 'app' : 'web';

$needsUpdate = version_compare(
    preg_replace('/[^0-9.]/', '', $latestVersion),
    preg_replace('/[^0-9.]/', '', $currentVersion),
    '>'
);

$remindedVersion = $_COOKIE['update_reminded_version'] ?? '';
$reminded = ($remindedVersion === $latestVersion);

// ==============================================
// ✅ SKIP
// ==============================================
if (isset($_GET['skip_update']) && $_GET['skip_update'] === '1') {
    setcookie('use_old_app', '1', [
        'expires' => time() + 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    header('Location: login.php');
    exit;
}

// ==============================================
// ✅ MODAL RENDERING GATE
// ==============================================
$skipUpdate = isset($_COOKIE['use_old_app']) && $_COOKIE['use_old_app'] === '1';
$renderUpdateModal = ($isInApp && !$skipUpdate);

// ==============================================
// ✅ BIOMETRIC AUTO-PROMPT GATE
// ==============================================
$skipBiometricAutoPrompt = ($_SERVER['REQUEST_METHOD'] === 'POST');

// ==============================================
// ✅ PER-ROLE BIOMETRIC PAGE MAP
// ==============================================
$biometricPageMap = [
    'Admin'    => 'web/biometric.php',
    'Investor' => 'investors/biometric.php',
    'Customer' => 'public/biometric.php',
];

// ==============================================
// ✅ REDIRECT LOGIC — SEPARATED PER ROLE
// ==============================================

/**
 * ADMIN redirect rules:
 *   app + not enrolled    → web/biometric.php
 *   app + enrolled        → web/shop.php
 *   mobile web            → download_app.php (root-level)
 *   desktop web           → web/shop.php
 */
function getAdminRedirect($isInApp, $isMobileBrowser, $user)
{
    global $biometricPageMap;

    $hasBiometric = (($user['biometric_enrolled'] ?? 0) == 1 && !empty($user['biometric_id'] ?? ''));

    if ($isInApp && !$hasBiometric) {
        $_SESSION['temp_user_id']   = $user['id'];
        $_SESSION['temp_user_type'] = 'Admin';
        return $biometricPageMap['Admin'];
    }

    if ($isInApp)         return 'web/shop.php';
    if ($isMobileBrowser) return 'web/shop.php';
    return 'web/shop.php';
}

/**
 * INVESTOR redirect rules:
 *   app + not enrolled    → investors/biometric.php
 *   app + enrolled        → investors/investors_product.php
 *   mobile web            → investors/download_app.php
 *   desktop web           → investors/investors_product.php
 */
function getInvestorRedirect($isInApp, $isMobileBrowser, $user)
{
    global $biometricPageMap;

    $hasBiometric = (($user['biometric_enrolled'] ?? 0) == 1 && !empty($user['biometric_id'] ?? ''));

    if ($isInApp && !$hasBiometric) {
        $_SESSION['temp_user_id']   = $user['id'];
        $_SESSION['temp_user_type'] = 'Investor';
        return $biometricPageMap['Investor'];
    }

    if ($isInApp)         return 'investors/investors_product.php';
    if ($isMobileBrowser) return 'investors/download_app.php';
    return 'investors/investors_product.php';
}

/**
 * CUSTOMER redirect rules:
 *   app + not enrolled    → public/biometric.php
 *   app + enrolled        → public/shop.php (or public/account-edit.php for guest)
 *   mobile web            → public/download_app.php
 *   desktop web           → public/shop.php (or public/account-edit.php for guest)
 */
function getCustomerRedirect($isInApp, $isMobileBrowser, $user)
{
    global $biometricPageMap;

    $isGuest      = (($user['f_name'] ?? '') === 'Guest' || empty($user['f_name'] ?? ''));
    $dashboardUrl = $isGuest ? 'public/account-edit.php' : 'public/shop.php';

    $hasBiometric = (($user['biometric_enrolled'] ?? 0) == 1 && !empty($user['biometric_id'] ?? ''));

    if ($isInApp && !$hasBiometric) {
        $_SESSION['temp_user_id']   = $user['id'];
        $_SESSION['temp_user_type'] = 'Customer';
        return $biometricPageMap['Customer'];
    }

    if ($isInApp)         return $dashboardUrl;
    if ($isMobileBrowser) return 'public/download_app.php';
    return $dashboardUrl;
}

/**
 * Master dispatcher — routes to the right role function.
 */
function getRedirectUrl($userType, $isInApp, $isMobileBrowser, $user)
{
    switch ($userType) {
        case 'Admin':    return getAdminRedirect($isInApp, $isMobileBrowser, $user);
        case 'Investor': return getInvestorRedirect($isInApp, $isMobileBrowser, $user);
        case 'Customer': return getCustomerRedirect($isInApp, $isMobileBrowser, $user);
        default:         return 'login.php';
    }
}

// ==============================================
// TABLE MAP (shared)
// ==============================================
$tableMap = [
    'Admin'    => 'admins',
    'Investor' => 'investors',
    'Customer' => 'customers'
];

// ==============================================
// ALREADY LOGGED IN
// ==============================================
$isLoggedIn = false;
$redirectUrl = '';
$userName = '';

if (isset($_SESSION['user_role']) && isset($_SESSION['user_id'])) {
    $isLoggedIn = true;
    $userName = $_SESSION['acc_number'] ?? 'User';

    $table = $tableMap[$_SESSION['user_role']] ?? 'customers';

    $stmt = $pdo->prepare("SELECT id, f_name, biometric_enrolled, biometric_id FROM $table WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $loggedInUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($loggedInUser) {
        $redirectUrl = getRedirectUrl(
            $_SESSION['user_role'],
            $isInApp,
            $isMobileBrowser,
            $loggedInUser
        );
    }
}

// ==============================================
// BIOMETRIC ENROLLED CHECK (for JS bridge)
// ==============================================
$hasBiometric = false;
$biometricUserId = null;
$biometricUserType = null;

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $userId = $_SESSION['user_id'];
    $userType = $_SESSION['user_role'];

    $table = $tableMap[$userType] ?? 'customers';

    $stmt = $pdo->prepare("SELECT id, biometric_enrolled, biometric_id FROM $table WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user && $user['biometric_enrolled'] == 1) {
        $hasBiometric = true;
        $biometricUserId = $userId;
        $biometricUserType = $userType;
    }
} elseif (isset($_COOKIE['user_id']) && isset($_COOKIE['user_type'])) {
    $userId = $_COOKIE['user_id'];
    $userType = $_COOKIE['user_type'];

    $table = $tableMap[$userType] ?? 'customers';

    $stmt = $pdo->prepare("SELECT id, biometric_enrolled, biometric_id, acc_number FROM $table WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user && $user['biometric_enrolled'] == 1) {
        $hasBiometric = true;
        $biometricUserId = $userId;
        $biometricUserType = $userType;

        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $userType;
        $_SESSION['acc_number'] = $user['acc_number'] ?? 'User';
    }
}

// ==============================================
// BIOMETRIC LOGIN (API)
// ==============================================
if (isset($_POST['biometric_login']) && $_POST['biometric_login'] === 'true') {
    header('Content-Type: application/json');

    $userId = $_POST['user_id'] ?? null;
    $userType = $_POST['user_type'] ?? null;

    if (!$userId || !$userType) {
        echo json_encode(['success' => false, 'message' => 'Missing user data']);
        exit;
    }

    $table = $tableMap[$userType] ?? 'customers';

    $stmt = $pdo->prepare("SELECT id, biometric_enrolled, acc_number, f_name, biometric_id FROM $table WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    if ($user['biometric_enrolled'] != 1) {
        echo json_encode(['success' => false, 'message' => 'Biometric not enrolled']);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $userType;
    $_SESSION['acc_number'] = $user['acc_number'];

    setcookie('user_id', $user['id'], time() + (86400 * 365), "/");
    setcookie('user_type', $userType, time() + (86400 * 365), "/");
    setcookie('biometric_enrolled', $user['biometric_enrolled'] ?? 0, time() + (86400 * 365), "/");

    // Update login_type for customers only
    if ($userType === 'Customer') {
        try {
            $updateTypeStmt = $pdo->prepare("UPDATE customers SET login_type = ? WHERE id = ?");
            $updateTypeStmt->execute([$loginType, $user['id']]);
        } catch (PDOException $e) {
            error_log("login_type update failed: " . $e->getMessage());
        }
    }

    // ✅ Customer in-app with mismatched version → ask to update first
    if ($userType === 'Customer' && $isInApp && !$appVersionMatch && !$skipUpdate) {
        echo json_encode([
            'success' => false,
            'message' => 'Please update the app or click SKIP to continue.',
            'show_update_modal' => true
        ]);
        exit;
    }

    // ✅ Use the same role dispatcher
    $redirectUrl = getRedirectUrl($userType, $isInApp, $isMobileBrowser, $user);

    echo json_encode(['success' => true, 'redirect' => $redirectUrl, 'message' => '']);
    exit;
}

// ==============================================
// GET ALL DATA
// ==============================================
function getAllAdmins($pdo)
{
    try {
        $stmt = $pdo->query("SELECT id, acc_number, f_name FROM admins ORDER BY f_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getAllAdmins failed: " . $e->getMessage());
        return [];
    }
}

function getAllInvestors($pdo)
{
    try {
        $stmt = $pdo->query("SELECT id, acc_number, f_name FROM investors ORDER BY f_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getAllInvestors failed: " . $e->getMessage());
        return [];
    }
}

function getAllCustomers($pdo)
{
    try {
        $stmt = $pdo->query("SELECT id, acc_number, f_name, phone_number, email FROM customers ORDER BY f_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getAllCustomers failed: " . $e->getMessage());
        return [];
    }
}

// ==============================================
// REGULAR LOGIN
// ==============================================
$errors = [];
$loginSuccess = false;
$userTypeSelected = 'Admin';
$selectedRole = '';
$selectedInvestorId = '';
$selectedCustomerId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['biometric_login'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    $userTypeSelected = trim($_POST['user_type'] ?? 'Admin');
    $selectedRole = trim($_POST['role'] ?? '');
    $selectedInvestorId = trim($_POST['investor'] ?? '');
    $selectedCustomerId = trim($_POST['customer'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($password)) $errors[] = 'Password cannot be empty.';
    if ($userTypeSelected === 'Admin' && empty($selectedRole)) $errors[] = 'Please select an admin account.';
    if ($userTypeSelected === 'Investor' && empty($selectedInvestorId)) $errors[] = 'Please select an investor account.';
    if ($userTypeSelected === 'Customer' && empty($selectedCustomerId)) $errors[] = 'Please select a customer account.';

    if (empty($errors)) {
        $identifier = '';
        $user = null;
        $userType = null;

        // ---------------- ADMIN ----------------
        if ($userTypeSelected === 'Admin') {
            $stmt = $pdo->prepare("SELECT phone_number FROM admins WHERE id = ?");
            $stmt->execute([$selectedRole]);
            $info = $stmt->fetch();
            if ($info) $identifier = substr(preg_replace('/[^0-9]/', '', $info['phone_number']), -4);

            $stmt = $pdo->prepare("
                SELECT id, password, acc_number, phone_number, f_name, role, status, email, biometric_enrolled, biometric_id
                FROM admins
                WHERE id = ? AND RIGHT(phone_number, 4) = ?
            ");
            $stmt->execute([$selectedRole, $identifier]);
            $user = $stmt->fetch();

            if ($user) {
                if (password_verify($password, $user['password'])) $userType = 'Admin';
                else $errors[] = 'Invalid credentials. Please try again.';
            } else {
                $errors[] = 'Invalid credentials. Please try again.';
            }
        }
        // ---------------- INVESTOR ----------------
        elseif ($userTypeSelected === 'Investor') {
            $stmt = $pdo->prepare("SELECT phone_number FROM investors WHERE id = ?");
            $stmt->execute([$selectedInvestorId]);
            $info = $stmt->fetch();
            if ($info) $identifier = substr(preg_replace('/[^0-9]/', '', $info['phone_number']), -4);

            $stmt = $pdo->prepare("
                SELECT id, password, acc_number, phone_number, f_name, 'Investor' as role, status, email, biometric_enrolled, biometric_id
                FROM investors
                WHERE id = ? AND RIGHT(phone_number, 4) = ?
            ");
            $stmt->execute([$selectedInvestorId, $identifier]);
            $user = $stmt->fetch();

            if ($user) {
                if (password_verify($password, $user['password'])) $userType = 'Investor';
                else $errors[] = 'Invalid credentials. Please try again.';
            } else {
                $errors[] = 'Invalid credentials. Please try again.';
            }
        }
        // ---------------- CUSTOMER ----------------
        elseif ($userTypeSelected === 'Customer') {
            $stmt = $pdo->prepare("SELECT phone_number FROM customers WHERE id = ?");
            $stmt->execute([$selectedCustomerId]);
            $info = $stmt->fetch();
            if ($info) $identifier = substr(preg_replace('/[^0-9]/', '', $info['phone_number']), -4);

            $stmt = $pdo->prepare("
                SELECT id, password, acc_number, account, phone_number, f_name, 'Customer' as role, status, email, biometric_enrolled, biometric_id
                FROM customers
                WHERE id = ? AND RIGHT(phone_number, 4) = ?
            ");
            $stmt->execute([$selectedCustomerId, $identifier]);
            $user = $stmt->fetch();

            if ($user) {
                if ($user['account'] == 1) {
                    $errors[] = 'Account locked. Please contact support.';
                } elseif (password_verify($password, $user['password'])) {
                    $userType = 'Customer';
                } else {
                    $errors[] = 'Invalid credentials. Please try again.';
                }
            } else {
                $errors[] = 'Invalid credentials. Please try again.';
            }
        }

        // ---------------- LOGIN SUCCESS ----------------
        if (empty($errors) && $user && $userType) {
            date_default_timezone_set('Asia/Manila');

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $userType;
            $_SESSION['acc_number'] = $user['acc_number'];

            setcookie('user_id', $user['id'], time() + (86400 * 365), "/");
            setcookie('user_type', $userType, time() + (86400 * 365), "/");
            setcookie('biometric_enrolled', $user['biometric_enrolled'] ?? 0, time() + (86400 * 365), "/");

            // Update login_type for customers only
            if ($userType === 'Customer') {
                try {
                    $updateTypeStmt = $pdo->prepare("UPDATE customers SET login_type = ? WHERE id = ?");
                    $updateTypeStmt->execute([$loginType, $user['id']]);
                } catch (PDOException $e) {
                    error_log("login_type update failed: " . $e->getMessage());
                }
            }

            $loginSuccess = true;

            // ✅ Single dispatcher — same logic for all three roles
            $redirectUrl = getRedirectUrl($userType, $isInApp, $isMobileBrowser, $user);

            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

// ==============================================
// GET DATA FOR DROPDOWNS
// ==============================================
$existingAdmins = getAllAdmins($pdo);
$existingInvestors = getAllInvestors($pdo);
$existingCustomers = getAllCustomers($pdo);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$offlineMessage = '';
if (isset($_SESSION['exit_message'])) {
    $offlineMessage = $_SESSION['exit_message'];
    unset($_SESSION['exit_message']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Villaruz Print Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        html,
        body {
            height: 100%;
        }

        body {
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .logo {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            margin-bottom: 20px;
            gap: 6px;
        }

        .logo img {
            width: 100px;
            height: auto;
            object-fit: contain;
            display: block;
        }

        .version-badge {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            padding: 2px 10px;
            border-radius: 20px;
            letter-spacing: 0.02em;
        }

        .auth-container {
            flex: 1;
            width: 100%;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 20px;
            background: #f1f5f9;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 5px;
            padding: 30px;
            width: 100%;
            max-width: 450px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.05);
            margin: 0 auto;
        }

        .auth-sub {
            text-align: center;
            color: #64748b;
            margin-bottom: 20px;
            font-size: 18px;
        }

        /* ==============================================
           SLIDING SEGMENTED CONTROL
           ============================================== */
        .user-type-toggle {
            position: relative;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            margin-bottom: 20px;
            padding: 4px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .user-type-toggle::before {
            content: "";
            position: absolute;
            top: 4px;
            bottom: 4px;
            left: 4px;
            right: calc(200% / 3 + 4px);
            background: #eff6ff;
            border: 2px solid #3b82f6;
            border-radius: 9px;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 0;
            pointer-events: none;
            box-sizing: border-box;
        }

        .user-type-toggle[data-active="Investor"]::before {
            left: calc(100% / 3 + 4px);
            right: calc(100% / 3 + 4px);
        }

        .user-type-toggle[data-active="Customer"]::before {
            left: calc(200% / 3 + 4px);
            right: 4px;
        }

        .user-type-toggle button {
            position: relative;
            z-index: 1;
            background: transparent;
            border: none;
            padding: 10px 6px;
            border-radius: 9px;
            font-weight: 600;
            font-size: 13px;
            color: #64748b;
            cursor: pointer;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-family: 'Poppins', sans-serif;
        }

        .user-type-toggle button:hover,
        .user-type-toggle button.active {
            color: #3b82f6;
        }

        /* ==============================================
           FORM
           ============================================== */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
            font-size: 14px;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            color: #1e293b;
            font-size: 15px;
            outline: none;
            transition: 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .form-group select:focus,
        .form-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: #ffffff;
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            flex: 1;
            padding-right: 45px;
        }

        .password-wrapper i {
            position: absolute;
            right: 15px;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.3s;
            font-size: 18px;
        }

        .password-wrapper i:hover {
            color: #3b82f6;
        }

        .forgot-password-link {
            text-align: right;
            margin-top: 6px;
            font-size: 13px;
        }

        .forgot-password-link a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
        }

        .forgot-password-link a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(145deg, #3b82f6, #6366f1);
            border: none;
            padding: 14px;
            border-radius: 5px;
            font-weight: 700;
            font-size: 16px;
            color: white;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:disabled {
            opacity: 1;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 3px solid rgba(255, 255, 255, 0.4);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: btn-spin 0.8s linear infinite;
            vertical-align: middle;
        }

        @keyframes btn-spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .fingerprint-row {
            display: flex;
            justify-content: center;
            margin-top: 16px;
        }

        .btn-fingerprint {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            color: #3b82f6;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        .btn-fingerprint i {
            font-size: 24px;
            color: #3b82f6;
            transition: color 0.3s;
        }

        .btn-fingerprint:hover {
            border-color: #3b82f6;
            background: #eff6ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }

        .btn-fingerprint:hover i {
            color: #2563eb;
        }

        .btn-fingerprint:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            gap: 10px;
            animation: slideDown 0.5s ease;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .alert i {
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

        .select-group {
            display: none;
        }

        .select-group.visible {
            display: block;
            animation: fadeIn 0.25s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-footer {
            text-align: center;
            margin-top: 25px;
            color: #64748b;
            font-size: 14px;
        }

        .auth-footer a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
        }

        /* ==============================================
           UPDATE MODAL
           ============================================== */
        .update-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            padding: 24px 16px;
        }

        .update-overlay.visible {
            display: flex;
        }

        .update-card {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            margin: auto;
        }

        .update-header {
            padding: 24px 22px 18px 22px;
            border-bottom: 1px solid #edf2f7;
            text-align: center;
            position: relative;
            background: #ffffff;
        }

        .skip-link {
            position: absolute;
            top: 12px;
            right: 14px;
            font-size: 13px;
            font-weight: 700;
            color: #000513;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 5px;
        }

        .update-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px auto;
        }

        .update-icon img {
            max-width: 64px;
            height: auto;
        }

        .update-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0b1e2e;
        }

        .update-installed-version {
            display: inline-block;
            margin-top: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            background: #f1f5f9;
            padding: 4px 12px;
            border-radius: 5px;
            border: 1px solid #e2e8f0;
        }

        .update-recommended {
            font-size: 14px;
            color: #067bf8;
            margin-top: 12px;
            font-weight: 600;
        }

        .update-body {
            padding: 18px 22px 22px 22px;
            background: #ffffff;
        }

        .update-info-block {
            background: #f8fafd;
            border-left: 4px solid #2563eb;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 12px;
            font-size: 0.875rem;
            color: #1e2b3c;
            line-height: 1.5;
        }

        .update-info-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #5f6f80;
            margin-bottom: 3px;
        }

        .update-details {
            list-style: none;
            margin: 0 0 18px 0;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .update-details li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            font-size: 0.83rem;
            border-bottom: 1px solid #edf2f7;
        }

        .update-details li:last-child {
            border-bottom: none;
        }

        .update-details .label {
            color: #5f6f80;
        }

        .update-details .value {
            color: #0b1e2e;
            font-weight: 500;
        }

        .update-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .update-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 13px 20px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 15px;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            width: 100%;
        }

        .update-btn-primary {
            background: #1d4ed8;
            color: #ffffff;
            border-color: #1d4ed8;
        }

        .update-btn-outline {
            background: #ffffff;
            color: #1e2b3c;
            border-color: #cbd5e1;
        }

        /* ==============================================
           RESPONSIVE
           ============================================== */
        @media (max-width: 500px) {
            .auth-container {
                padding: 20px 16px;
            }

            .auth-card {
                padding: 30px 22px;
                max-width: 100%;
            }

            .logo img {
                width: 75px;
            }

            .user-type-toggle button {
                font-size: 11px;
                padding: 10px 4px;
                gap: 4px;
            }

            .user-type-toggle button i {
                font-size: 13px;
            }

            .forgot-password-link {
                font-size: 12px;
            }

            .btn-fingerprint {
                width: 50px;
                height: 50px;
            }

            .btn-fingerprint i {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

    <div class="auth-container">
        <div class="auth-card">
            <div class="logo">
                <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/ic_launcher.png"
                    alt="Villaruz Print Shop Logo">
                <span class="version-badge">V<?php echo $latestVersion; ?></span>
            </div>

            <?php if (!empty($offlineMessage)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-sign-out-alt"></i> <?php echo htmlspecialchars($offlineMessage); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div id="passwordSection">
                <form method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="user-type-toggle" id="userTypeToggle" data-active="<?php echo $userTypeSelected; ?>">
                        <button type="button" class="<?php echo $userTypeSelected === 'Admin' ? 'active' : ''; ?>"
                            data-role="Admin" onclick="switchUserType('Admin')">
                            <i class="fas fa-user-tie"></i> Admin
                        </button>
                        <button type="button" class="<?php echo $userTypeSelected === 'Investor' ? 'active' : ''; ?>"
                            data-role="Investor" onclick="switchUserType('Investor')">
                            <i class="fas fa-chart-line"></i> Investor
                        </button>
                        <button type="button" class="<?php echo $userTypeSelected === 'Customer' ? 'active' : ''; ?>"
                            data-role="Customer" onclick="switchUserType('Customer')">
                            <i class="fas fa-user"></i> Customer
                        </button>
                    </div>

                    <input type="hidden" name="user_type" id="userTypeInput" value="<?php echo $userTypeSelected; ?>">
                    <input type="hidden" name="installed_version" id="installedVersionInput" value="">

                    <div class="form-group select-group <?php echo $userTypeSelected === 'Admin' ? 'visible' : ''; ?>"
                        id="adminSelectGroup">
                        <label><i class="fas fa-users"></i> Select Account</label>
                        <select name="role" id="adminSelect">
                            <option value="">-- Select your account --</option>
                            <?php foreach ($existingAdmins as $admin): ?>
                                <option value="<?php echo $admin['id']; ?>" <?php echo ($selectedRole == $admin['id'] && $userTypeSelected === 'Admin') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($admin['acc_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group select-group <?php echo $userTypeSelected === 'Investor' ? 'visible' : ''; ?>"
                        id="investorSelectGroup">
                        <label><i class="fas fa-chart-line"></i> Select Account</label>
                        <select name="investor" id="investorSelect">
                            <option value="">-- Select your account --</option>
                            <?php foreach ($existingInvestors as $investor): ?>
                                <option value="<?php echo $investor['id']; ?>" <?php echo ($selectedInvestorId == $investor['id'] && $userTypeSelected === 'Investor') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($investor['acc_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group select-group <?php echo $userTypeSelected === 'Customer' ? 'visible' : ''; ?>"
                        id="customerSelectGroup">
                        <label><i class="fas fa-users"></i> Select Account</label>
                        <select name="customer" id="customerSelect">
                            <option value="">-- Select your account --</option>
                            <?php foreach ($existingCustomers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" <?php echo ($selectedCustomerId == $customer['id'] && $userTypeSelected === 'Customer') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['acc_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" placeholder="Enter your password"
                                required>
                            <i class="fas fa-eye-slash" id="togglePassword"></i>
                        </div>
                        <div class="forgot-password-link">
                            <a href="forgot_password.php"><i class="fas fa-key"></i> Forgot password?</a>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" id="loginBtn" <?php echo $loginSuccess ? 'disabled' : ''; ?>>
                        Login
                    </button>

                    <div class="fingerprint-row">
                        <button type="button" class="btn-fingerprint" id="fingerprintBtn"
                            onclick="triggerBiometric()" title="Login with fingerprint">
                            <i class="fas fa-fingerprint"></i>
                        </button>
                    </div>

                    <div class="auth-footer">
                        Don't have an account? <a href="registration.php">Sign Up</a>
                    </div>
                </form>
                <div class="auth-footer">
                    Download the <a
                        href="https://villaruz-print-shop-and-general-merchandise.shop/APK/sofia.apk">Sofia</a>
                    App
                </div>
            </div>

        </div>
    </div>


    <?php if ($renderUpdateModal): ?>
        <div class="update-overlay" id="updateOverlay">
            <div class="update-card">
                <div class="update-header">
                    <a href="?skip_update=1" class="skip-link">SKIP</a>
                    <div class="update-icon">
                        <img src="logo/ic_launcher.png" alt="Sofia App Logo">
                    </div>
                    <div class="update-title">SofiaApp</div>
                    <div class="update-installed-version" id="installedVersionBadge">
                        Detecting installed version...
                    </div>
                    <div class="update-recommended">
                        A new version is available. Update now or tap SKIP to continue with the current version.
                    </div>
                </div>

                <div class="update-body">
                    <div class="update-info-block">
                        <div class="update-info-label">Update Notice</div>
                        A new version of the <strong>Sofia App</strong> is now available.
                    </div>

                    <div class="update-info-block">
                        <div class="update-info-label">Your Options</div>
                        Keep using your current version, or tap <strong>SKIP</strong> to update later.
                    </div>

                    <div class="update-info-block">
                        <div class="update-info-label">Having Trouble?</div>
                        Uninstall the old app, then download the latest version from the link below.
                    </div>

                    <ul class="update-details">
                        <li>
                            <span class="label">New Release</span>
                            <span class="value"><?php echo htmlspecialchars($latestVersion); ?></span>
                        </li>
                        <li>
                            <span class="label">Old Version</span>
                            <span class="value" id="installedVersionValue">—</span>
                        </li>
                        <li>
                            <span class="label">Requirements</span>
                            <span class="value">Android 6.0 and above.</span>
                        </li>
                    </ul>

                    <div class="update-actions">
                        <a href="https://villaruz-print-shop-and-general-merchandise.shop/APK/sofia_app.apk"
                            class="update-btn update-btn-primary">Download Sofia App</a>
                        <a href="https://villaruz-print-shop-and-general-merchandise.shop/APK/sofia_app.apk"
                            class="update-btn update-btn-outline">Re-Install App</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const isInApp = typeof window.AndroidBiometric !== 'undefined';
        const hasBiometric = <?php echo $hasBiometric ? 'true' : 'false'; ?>;
        const userId = <?php echo json_encode($biometricUserId); ?>;
        const userType = <?php echo json_encode($biometricUserType); ?>;
        const latestVersion = <?php echo json_encode($latestVersion); ?>;
        const skipBiometricAutoPrompt = <?php echo $skipBiometricAutoPrompt ? 'true' : 'false'; ?>;

        function readInstalledVersion() {
            if (window.__SOFIA_APP_VERSION__) return window.__SOFIA_APP_VERSION__;
            try {
                if (window.AndroidBiometric && window.AndroidBiometric.getAppVersion) {
                    return window.AndroidBiometric.getAppVersion() || 'unknown';
                }
            } catch (e) { }
            return 'unknown';
        }

        function versionsEqual(a, b) {
            const norm = s => (s || '').toString().replace(/[^0-9.]/g, '');
            return norm(a) === norm(b);
        }

        function setLoginButtonBusy() {
            var btn = document.getElementById('loginBtn');
            if (!btn) return;
            btn.disabled = true;
            btn.innerHTML = '<span class="btn-spinner"></span>';
        }

        function resetLoginButton() {
            var btn = document.getElementById('loginBtn');
            if (!btn) return;
            btn.disabled = false;
            btn.innerHTML = 'Login';
        }

        function triggerBiometric() {
            if (!isInApp) {
                alert('Fingerprint login is only available inside the SofiaApp.');
                return;
            }

            if (!hasBiometric || !userId) {
                alert('No fingerprint enrolled. Please log in with password first and enroll your fingerprint.');
                return;
            }

            var fpBtn = document.getElementById('fingerprintBtn');
            if (fpBtn) fpBtn.disabled = true;

            if (window.AndroidBiometric && window.AndroidBiometric.authenticate) {
                window.AndroidBiometric.authenticate('manual');
            } else {
                alert('Biometric bridge not available.');
                if (fpBtn) fpBtn.disabled = false;
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const installedVersion = readInstalledVersion();

            var versionInput = document.getElementById('installedVersionInput');
            if (versionInput && isInApp) versionInput.value = installedVersion;

            const overlay = document.getElementById('updateOverlay');
            if (overlay) {
                if (!isInApp || versionsEqual(installedVersion, latestVersion)) {
                    overlay.classList.remove('visible');
                } else {
                    overlay.classList.add('visible');
                }
            }

            var badge = document.getElementById('installedVersionBadge');
            var versionValue = document.getElementById('installedVersionValue');
            if (badge && versionValue) {
                if (!isInApp) {
                    badge.textContent = 'Not running in the app';
                    versionValue.textContent = 'N/A';
                } else if (!installedVersion || installedVersion === 'unknown') {
                    badge.textContent = 'Version unknown';
                    versionValue.textContent = 'Unknown';
                } else {
                    badge.textContent = 'Installed: ' + installedVersion;
                    versionValue.textContent = installedVersion;
                }
            }

            const modalVisible = overlay && overlay.classList.contains('visible');
            if (!skipBiometricAutoPrompt && !modalVisible && hasBiometric && isInApp && userId) {
                window.AndroidBiometric.authenticate('auto');
            }
        });

        (function () {
            var form = document.getElementById('loginForm');
            var btn = document.getElementById('loginBtn');
            if (form && btn) {
                form.addEventListener('submit', function () {
                    if (btn.disabled) return;
                    setLoginButtonBusy();
                });
            }
        })();

        function biometricSuccess(data) {
            const userId = <?php echo json_encode($biometricUserId); ?>;
            const userType = <?php echo json_encode($biometricUserType); ?>;

            if (!userId) { resetLoginButton(); return; }

            var installedVersion = readInstalledVersion();

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'biometric_login=true'
                    + '&user_id=' + encodeURIComponent(userId)
                    + '&user_type=' + encodeURIComponent(userType)
                    + '&installed_version=' + encodeURIComponent(installedVersion)
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        setTimeout(() => window.location.href = data.redirect, 800);
                    } else if (data.show_update_modal) {
                        document.getElementById('updateOverlay')?.classList.add('visible');
                        resetLoginButton();
                    } else {
                        resetLoginButton();
                    }
                })
                .catch(() => resetLoginButton());
        }

        function biometricFailed() {
            resetLoginButton();
            var fpBtn = document.getElementById('fingerprintBtn');
            if (fpBtn) fpBtn.disabled = false;
        }

        function biometricCancel() {
            resetLoginButton();
            var fpBtn = document.getElementById('fingerprintBtn');
            if (fpBtn) fpBtn.disabled = false;
        }

        function biometricError() {
            resetLoginButton();
            var fpBtn = document.getElementById('fingerprintBtn');
            if (fpBtn) fpBtn.disabled = false;
        }

        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        if (togglePassword) {
            togglePassword.addEventListener('click', function () {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        function switchUserType(type) {
            document.getElementById('userTypeInput').value = type;

            const toggle = document.getElementById('userTypeToggle');
            toggle.setAttribute('data-active', type);

            toggle.querySelectorAll('button').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.role === type);
            });

            document.querySelectorAll('.select-group').forEach(group => {
                group.classList.remove('visible');
            });

            const map = {
                'Admin':    'adminSelectGroup',
                'Investor': 'investorSelectGroup',
                'Customer': 'customerSelectGroup'
            };

            const groupId = map[type];
            if (groupId) {
                const el = document.getElementById(groupId);
                if (el) el.classList.add('visible');
            }
        }
    </script>
</body>

</html>