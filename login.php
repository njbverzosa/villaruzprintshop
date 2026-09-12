<?php
// login.php – desktop flow unchanged
// ✅ Desktop browser → normal login → dashboard
// ✅ Mobile browser → should already be blocked at index.php
// ✅ In-app version mismatch → installer modal (overlay)
// ✅ In-app SKIP → use_old_app cookie for 1 day → login form unlocks
// ✅ FIX: modal visibility decided by JS after reading installed version

// Set session lifetime
$sessionLifetime = 604800;
ini_set('session.cookie_lifetime', $sessionLifetime);
ini_set('session.gc_maxlifetime', $sessionLifetime);

session_start();
require_once __DIR__ . '/DB_Conn/config.php';
include __DIR__ . '/app_version.php';   // defines $latestVersion + $currentVersion

// ==============================================
// ✅ DETECT PLATFORM
// ==============================================
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isInApp = (strpos($userAgent, 'SofiaApp') !== false);

function isMobileBrowser($userAgent)
{
    $mobileKeywords = [
        'Android',
        'webOS',
        'iPhone',
        'iPad',
        'iPod',
        'BlackBerry',
        'Windows Phone',
        'Opera Mini',
        'IEMobile',
        'Mobile'
    ];
    foreach ($mobileKeywords as $keyword) {
        if (stripos($userAgent, $keyword) !== false) {
            return true;
        }
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
    $latestNorm    = preg_replace('/[^0-9.]/', '', $latestVersion);

    if (!empty($installedNorm) && $installedNorm !== 'unknown') {
        $appVersionMatch = version_compare($installedNorm, $latestNorm, '==');
    } else {
        $appVersionMatch = false;   // unknown → treat as mismatch
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
// ✅ SKIP — set cookie for 1 day, refresh
// ==============================================
if (isset($_GET['skip_update']) && $_GET['skip_update'] === '1') {
    setcookie(
        'use_old_app',
        '1',
        [
            'expires'  => time() + 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
    header('Location: login.php');
    exit;
}

// ==============================================
// ✅ MODAL RENDERING GATE
// ==============================================
// We render the modal HTML only for in-app users who haven't skipped.
// Final visibility (show/hide) is decided by JS after reading the real
// installed version from the Android bridge.
$skipUpdate = isset($_COOKIE['use_old_app']) && $_COOKIE['use_old_app'] === '1';

$renderUpdateModal = ($isInApp && !$skipUpdate);

// ==============================================
// ALREADY LOGGED IN
// ==============================================
$isLoggedIn = false;
$redirectUrl = '';
$userName = '';

if (isset($_SESSION['user_role']) && isset($_SESSION['user_id'])) {
    $isLoggedIn = true;
    $userName = $_SESSION['acc_number'] ?? 'User';

    if ($_SESSION['user_role'] === 'Admin') {
        $redirectUrl = 'web/all_products.php';
    } elseif ($_SESSION['user_role'] === 'Customer') {
        $redirectUrl = 'public/shop.php';
    }
}

// ==============================================
// BIOMETRIC ENROLLED CHECK
// ==============================================
$hasBiometric = false;
$biometricUserId = null;
$biometricUserType = null;

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $userId = $_SESSION['user_id'];
    $userType = $_SESSION['user_role'];

    $table = ($userType === 'Admin') ? 'admins' : 'customers';
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

    $table = ($userType === 'Admin') ? 'admins' : 'customers';
    $stmt = $pdo->prepare("SELECT id, biometric_enrolled, biometric_id FROM $table WHERE id = ?");
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

    $table = ($userType === 'Admin') ? 'admins' : 'customers';
    $stmt = $pdo->prepare("SELECT id, biometric_enrolled, acc_number, f_name FROM $table WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

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

    if ($userType === 'Customer') {
        $updateTypeStmt = $pdo->prepare("UPDATE customers SET login_type = ? WHERE id = ?");
        $updateTypeStmt->execute([$loginType, $user['id']]);
    }

    // ✅ Redirect logic — desktop always goes to dashboard
    if ($userType === 'Admin') {
        $redirectUrl = 'web/all_products.php';
    } else {
        $isGuest = ($user['f_name'] === 'Guest' || empty($user['f_name']));
        $dashboardUrl = $isGuest ? 'public/account-edit.php' : 'public/shop.php';

        if ($isInApp && !$appVersionMatch && !$skipUpdate) {
            // App + outdated + not skipped → modal stays, don't redirect
            echo json_encode([
                'success' => false,
                'message' => 'Please update the app or click SKIP to continue.',
                'show_update_modal' => true
            ]);
            exit;
        }

        $redirectUrl = $dashboardUrl;
    }

    echo json_encode([
        'success' => true,
        'redirect' => $redirectUrl,
        'message' => 'Accessing your account...'
    ]);
    exit;
}

// ==============================================
// GET ALL DATA
// ==============================================
function getAllAdmins($pdo)
{
    $stmt = $pdo->query("SELECT id, acc_number, f_name FROM admins ORDER BY f_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllCustomers($pdo)
{
    $stmt = $pdo->query("SELECT id, acc_number, f_name, phone_number, email FROM customers ORDER BY f_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==============================================
// REGULAR LOGIN
// ==============================================
$errors = [];
$loginSuccess = false;
$userTypeSelected = 'Admin';
$selectedRole = '';
$selectedCustomerId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['biometric_login'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    $userTypeSelected = trim($_POST['user_type'] ?? 'Admin');
    $selectedRole = trim($_POST['role'] ?? '');
    $selectedCustomerId = trim($_POST['customer'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($password)) {
        $errors[] = 'Password cannot be empty.';
    }

    if ($userTypeSelected === 'Admin' && empty($selectedRole)) {
        $errors[] = 'Please select an admin account.';
    }

    if ($userTypeSelected === 'Customer' && empty($selectedCustomerId)) {
        $errors[] = 'Please select a customer account.';
    }

    if (empty($errors)) {
        $identifier = '';
        if ($userTypeSelected === 'Admin') {
            $stmt = $pdo->prepare("SELECT phone_number FROM admins WHERE id = ?");
            $stmt->execute([$selectedRole]);
            $info = $stmt->fetch();
            if ($info) {
                $identifier = substr(preg_replace('/[^0-9]/', '', $info['phone_number']), -4);
            }
        } elseif ($userTypeSelected === 'Customer') {
            $stmt = $pdo->prepare("SELECT phone_number FROM customers WHERE id = ?");
            $stmt->execute([$selectedCustomerId]);
            $info = $stmt->fetch();
            if ($info) {
                $identifier = substr(preg_replace('/[^0-9]/', '', $info['phone_number']), -4);
            }
        }

        $user = null;
        $userType = null;

        if ($userTypeSelected === 'Admin') {
            $stmt = $pdo->prepare("SELECT id, password, acc_number, phone_number, f_name, role, status, email, authorize_access, biometric_enrolled, biometric_id
                              FROM admins WHERE id = ? AND RIGHT(phone_number, 4) = ?");
            $stmt->execute([$selectedRole, $identifier]);
            $user = $stmt->fetch();

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    $userType = 'Admin';
                } else {
                    $errors[] = 'Invalid credentials. Please try again.';
                }
            } else {
                $errors[] = 'Invalid credentials. Please try again.';
            }
        } elseif ($userTypeSelected === 'Customer') {
            $stmt = $pdo->prepare("SELECT id, password, acc_number, account, phone_number, f_name, 'Customer' as role, status, email, biometric_enrolled, biometric_id
                              FROM customers WHERE id = ? AND RIGHT(phone_number, 4) = ?");
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

        if (empty($errors) && $user && $userType) {
            date_default_timezone_set('Asia/Manila');

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $userType;
            $_SESSION['acc_number'] = $user['acc_number'];

            setcookie('user_id', $user['id'], time() + (86400 * 365), "/");
            setcookie('user_type', $userType, time() + (86400 * 365), "/");
            setcookie('biometric_enrolled', $user['biometric_enrolled'] ?? 0, time() + (86400 * 365), "/");

            if ($userType === 'Customer') {
                $updateTypeStmt = $pdo->prepare("UPDATE customers SET login_type = ? WHERE id = ?");
                $updateTypeStmt->execute([$loginType, $user['id']]);
            }

            $loginSuccess = true;

            // ✅ Redirect logic — desktop flow unchanged
            if ($userType === 'Admin') {
                if ($isInApp) {
                    if ($user['biometric_enrolled'] == 0 || empty($user['biometric_id'])) {
                        $_SESSION['temp_user_id'] = $user['id'];
                        $_SESSION['temp_user_type'] = $userType;
                        $redirectUrl = 'biometric.php';
                    } else {
                        $redirectUrl = 'web/all_products.php';
                    }
                } else {
                    $redirectUrl = 'web/all_products.php';
                }
            } else {
                $isGuest = ($user['f_name'] === 'Guest' || empty($user['f_name']));
                $dashboardUrl = $isGuest ? 'public/account-edit.php' : 'public/shop.php';
                $hasBiometricEnrolled = ($user['biometric_enrolled'] == 1 && !empty($user['biometric_id']));

                if ($isInApp) {
                    if (!$hasBiometricEnrolled) {
                        $_SESSION['temp_user_id'] = $user['id'];
                        $_SESSION['temp_user_type'] = $userType;
                        $redirectUrl = 'biometric.php';
                    } else {
                        // In-app: mismatch + not skipped → don't redirect, modal is up
                        if (!$appVersionMatch && !$skipUpdate) {
                            $redirectUrl = 'login.php';   // stay
                        } else {
                            $redirectUrl = $dashboardUrl;
                        }
                    }
                } else {
                    // ✅ Desktop browser — normal flow
                    $redirectUrl = $dashboardUrl;
                }
            }
        }
    }
}

// ==============================================
// GET DATA FOR DROPDOWNS
// ==============================================
$existingAdmins = getAllAdmins($pdo);
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
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #f1f5f9; color: #1e293b; min-height: 100vh; display: flex; flex-direction: column; }
        nav { display: flex; justify-content: space-between; align-items: center; padding: 15px 5%; background: #ffffff; border-bottom: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04); }
        .logo img { width: 100px; height: auto; object-fit: contain; }
        .nav-link { color: #64748b; text-decoration: none; font-weight: 500; transition: 0.3s; }
        .nav-link:hover { color: #3b82f6; }
        .auth-container { flex: 1; display: flex; justify-content: center; align-items: center; padding: 50px 20px; }
        .auth-card { background: #ffffff; border-radius: 5px; padding: 30px; width: 100%; max-width: 450px; border: 1px solid #e2e8f0; box-shadow: 0 20px 35px rgba(0, 0, 0, 0.05); }
        .auth-sub { text-align: center; color: #64748b; margin-bottom: 20px; font-size: 18px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 14px; }
        .form-group select, .form-group input { width: 100%; padding: 14px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; color: #1e293b; font-size: 15px; outline: none; transition: 0.3s; }
        .form-group select:focus, .form-group input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); background: #ffffff; }
        .password-wrapper { position: relative; display: flex; align-items: center; }
        .password-wrapper input { flex: 1; padding-right: 45px; }
        .password-wrapper i { position: absolute; right: 15px; cursor: pointer; color: #94a3b8; transition: color 0.3s; font-size: 18px; }
        .forgot-password-link { text-align: right; margin-top: 6px; font-size: 13px; }
        .forgot-password-link a { color: #3b82f6; text-decoration: none; font-weight: 500; transition: 0.3s; }
        .forgot-password-link a:hover { color: #1d4ed8; text-decoration: underline; }
        .btn-primary { width: 100%; background: linear-gradient(145deg, #3b82f6, #6366f1); border: none; padding: 14px; border-radius: 5px; font-weight: 700; font-size: 16px; color: white; cursor: pointer; transition: 0.3s; margin-top: 10px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .btn-primary:disabled { opacity: 1; cursor: not-allowed; background: linear-gradient(145deg, #3b82f6, #6366f1); transform: none !important; }
        .btn-spinner { display: inline-block; width: 18px; height: 18px; border: 3px solid rgba(255, 255, 255, 0.4); border-top-color: #ffffff; border-radius: 50%; animation: btn-spin 0.8s linear infinite; vertical-align: middle; }
        @keyframes btn-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .auth-footer { text-align: center; margin-top: 25px; color: #64748b; font-size: 14px; }
        .auth-footer a { color: #3b82f6; text-decoration: none; font-weight: 600; }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; display: flex; gap: 10px; animation: slideDown 0.5s ease; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .alert i { font-size: 18px; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .user-type-toggle { display: flex; gap: 10px; margin-bottom: 20px; }
        .user-type-toggle button { flex: 1; padding: 10px; border: 2px solid #e2e8f0; border-radius: 10px; background: #f8fafc; color: #64748b; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .user-type-toggle button.active { border-color: #3b82f6; background: #eff6ff; color: #3b82f6; }
        .user-type-toggle button:hover { background: #f1f5f9; }
        .select-group { display: none; }
        .select-group.visible { display: block; }
        .status-message { margin-top: 10px; padding: 10px; border-radius: 8px; font-size: 14px; display: none; }
        .status-message.show { display: block; }
        .status-message.success { background: #f0fdf4; color: #065f46; border: 1px solid #bbf7d0; }
        .status-message.error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .status-message.info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .hidden { display: none !important; }

        /* Update modal — hidden by default, shown by JS only on mismatch */
        .update-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;   /* ⬅️ hidden by default; JS shows it */
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            padding: 24px 16px;
            animation: fadeIn 0.2s ease-out;
        }
        .update-overlay.visible { display: flex; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .update-card { background: #ffffff; border-radius: 16px; width: 100%; max-width: 480px; box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35); overflow: hidden; animation: popIn 0.25s ease-out; margin: auto; }
        @keyframes popIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .update-header { padding: 24px 22px 18px 22px; border-bottom: 1px solid #edf2f7; text-align: center; position: relative; background: #ffffff; }
        .skip-link { position: absolute; top: 12px; right: 14px; font-size: 13px; font-weight: 700; color: #1d4ed8; text-decoration: none; letter-spacing: 0.02em; padding: 6px 12px; border-radius: 100px; background: #eff6ff; border: 1px solid #bfdbfe; transition: color 0.15s, background 0.15s; z-index: 10; }
        .skip-link:hover { color: #ffffff; background: #1d4ed8; }
        .update-icon { display: flex; align-items: center; justify-content: center; margin: 0 auto 10px auto; }
        .update-icon img { max-width: 64px; height: auto; }
        .update-title { font-size: 1.35rem; font-weight: 700; color: #0b1e2e; letter-spacing: -0.015em; }
        .update-installed-version { display: inline-block; margin-top: 8px; font-size: 0.8rem; font-weight: 600; color: #475569; background: #f1f5f9; padding: 4px 12px; border-radius: 5px; border: 1px solid #e2e8f0; }
        .update-installed-version.match { background: #f0fdf4; color: #065f46; border-color: #bbf7d0; }
        .update-installed-version.mismatch { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .update-recommended { font-size: 14px; color: #067bf8; margin-top: 12px; font-weight: 600; }
        .update-body { padding: 18px 22px 22px 22px; background: #ffffff; }
        .update-info-block { background: #f8fafd; border-left: 4px solid #2563eb; padding: 12px 14px; border-radius: 10px; margin-bottom: 12px; font-size: 0.875rem; color: #1e2b3c; line-height: 1.5; }
        .update-info-block strong { color: #1e40af; }
        .update-info-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #5f6f80; margin-bottom: 3px; }
        .update-details { list-style: none; margin: 0 0 18px 0; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .update-details li { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; font-size: 0.83rem; border-bottom: 1px solid #edf2f7; }
        .update-details li:last-child { border-bottom: none; }
        .update-details .label { color: #5f6f80; }
        .update-details .value { color: #0b1e2e; font-weight: 500; }
        .update-actions { display: flex; flex-direction: column; gap: 10px; }
        .update-btn { display: inline-flex; align-items: center; justify-content: center; padding: 13px 20px; border-radius: 40px; font-weight: 600; font-size: 15px; border: 1px solid transparent; cursor: pointer; text-decoration: none; line-height: 1.2; transition: background 0.15s, box-shadow 0.15s; width: 100%; }
        .update-btn-primary { background: #1d4ed8; color: #ffffff; border-color: #1d4ed8; box-shadow: 0 2px 6px rgba(29, 78, 216, 0.2); }
        .update-btn-primary:hover { background: #1e40af; }
        .update-btn-outline { background: #ffffff; color: #1e2b3c; border-color: #cbd5e1; }
        .update-btn-outline:hover { background: #f8fafd; }

        @media (max-width: 500px) {
            .auth-card { padding: 30px 25px; }
            .logo img { width: 75px; }
            .user-type-toggle button { font-size: 13px; padding: 8px; }
            .forgot-password-link { font-size: 12px; }
            .update-card { max-width: 100%; }
        }
    </style>
</head>

<body>

    <nav>
        <div class="logo">
            <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/logo.jpeg"
                alt="Villaruz Print Shop Logo">
        </div>
        <div>
            <a href="index.php" class="nav-link">Home</a>
        </div>
    </nav>

    <div class="auth-container">
        <div class="auth-card">
            <p class="auth-sub">Log In your account</p>

            <div id="biometricStatus" class="status-message"></div>

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

            <?php if ($loginSuccess): ?>
                <script>
                    (function () {
                        var redirectUrl = '<?php echo $redirectUrl; ?>';
                        setTimeout(function () {
                            window.location.href = redirectUrl;
                        }, 3000);
                    })();
                </script>
            <?php endif; ?>

            <div id="passwordSection">
                <form method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="user-type-toggle">
                        <button type="button" class="<?php echo $userTypeSelected === 'Admin' ? 'active' : ''; ?>"
                            onclick="switchUserType('Admin')">
                            <i class="fas fa-user-tie"></i> Admin
                        </button>
                        <button type="button" class="<?php echo $userTypeSelected === 'Customer' ? 'active' : ''; ?>"
                            onclick="switchUserType('Customer')">
                            <i class="fas fa-user"></i> Customer
                        </button>
                    </div>

                    <input type="hidden" name="user_type" id="userTypeInput" value="<?php echo $userTypeSelected; ?>">
                    <input type="hidden" name="installed_version" id="installedVersionInput" value="">

                    <div class="form-group select-group <?php echo $userTypeSelected === 'Admin' ? 'visible' : ''; ?>"
                        id="adminSelectGroup">
                        <label><i class="fas fa-users"></i> Select Admin Account</label>
                        <select name="role" id="adminSelect">
                            <option value="">-- Select your account --</option>
                            <?php foreach ($existingAdmins as $admin): ?>
                                <option value="<?php echo $admin['id']; ?>" <?php echo ($selectedRole == $admin['id'] && $userTypeSelected === 'Admin') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($admin['acc_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group select-group <?php echo $userTypeSelected === 'Customer' ? 'visible' : ''; ?>"
                        id="customerSelectGroup">
                        <label><i class="fas fa-users"></i> Select Customer Account</label>
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
                        <?php if ($loginSuccess): ?>
                            <span class="btn-spinner"></span>
                            <span>Logging in...</span>
                        <?php else: ?>
                            Login
                        <?php endif; ?>
                    </button>

                    <div class="auth-footer">
                        Don't have an account? <a href="registration.php">Sign Up</a>
                    </div>
                </form>
                <div class="auth-footer">
                    Download the <a
                        href="https://villaruz-print-shop-and-general-merchandise.shop/APK/sofia_app.apk">SofiaApp</a>
                    App
                </div>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- ==============================================
         UPDATE MODAL — rendered only for in-app users,
         hidden by default. JS decides visibility based
         on the real installed version from AndroidBiometric.
         ============================================== -->
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
                        A new version of the <strong>Sofia App</strong> is now available. Update or download the latest
                        release for improved performance, a hassle-free application process, and password-free login
                        using your device security.
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
        const biometricStatus = document.getElementById('biometricStatus');
        const isInApp = typeof window.AndroidBiometric !== 'undefined';
        const hasBiometric = <?php echo $hasBiometric ? 'true' : 'false'; ?>;
        const userId = <?php echo json_encode($biometricUserId); ?>;
        const userType = <?php echo json_encode($biometricUserType); ?>;
        const latestVersion = <?php echo json_encode($latestVersion); ?>;

        /**
         * Reads the installed version, preferring:
         *   1) window.__SOFIA_APP_VERSION__ — injected by MainActivity.onPageStarted()
         *   2) AndroidBiometric.getAppVersion() bridge
         *   3) 'unknown'
         */
        function readInstalledVersion() {
            if (window.__SOFIA_APP_VERSION__) return window.__SOFIA_APP_VERSION__;
            try {
                if (window.AndroidBiometric && window.AndroidBiometric.getAppVersion) {
                    return window.AndroidBiometric.getAppVersion() || 'unknown';
                }
            } catch (e) {
                console.warn('Could not read app version:', e);
            }
            return 'unknown';
        }

        function versionsEqual(a, b) {
            const norm = s => (s || '').toString().replace(/[^0-9.]/g, '');
            return norm(a) === norm(b);
        }

        document.addEventListener('DOMContentLoaded', function () {
            const installedVersion = readInstalledVersion();
            console.log('📱 Installed version:', installedVersion, '| Latest:', latestVersion);

            // ✅ Fill hidden input with version for password-login POST
            var versionInput = document.getElementById('installedVersionInput');
            if (versionInput && isInApp) {
                versionInput.value = installedVersion;
            }

            // ✅ Decide modal visibility based on ACTUAL installed version
            const overlay = document.getElementById('updateOverlay');
            if (overlay) {
                if (!isInApp) {
                    // Not in app — no modal ever
                    overlay.classList.remove('visible');
                } else if (versionsEqual(installedVersion, latestVersion)) {
                    // ✅ Version matches → keep modal hidden
                    overlay.classList.remove('visible');
                    console.log('✅ Version matches — modal stays hidden');
                } else {
                    // ⚠️ Version mismatch (or unknown) → show modal
                    overlay.classList.add('visible');
                    console.log('⚠️ Version mismatch — modal shown');
                }
            }

            // ✅ Update modal badge
            var badge = document.getElementById('installedVersionBadge');
            var versionValue = document.getElementById('installedVersionValue');
            if (badge && versionValue) {
                if (!isInApp) {
                    badge.textContent = 'Not running in the app';
                    badge.classList.add('mismatch');
                    versionValue.textContent = 'N/A';
                } else if (!installedVersion || installedVersion === 'unknown') {
                    badge.textContent = 'Version unknown';
                    badge.classList.add('mismatch');
                    versionValue.textContent = 'Unknown';
                } else {
                    var matched = versionsEqual(installedVersion, latestVersion);
                    badge.textContent = 'Installed: ' + installedVersion;
                    badge.classList.remove('match', 'mismatch');
                    badge.classList.add(matched ? 'match' : 'mismatch');
                    versionValue.textContent = installedVersion;
                }
            }

            // ✅ Biometric auto-prompt — only when modal is NOT showing
            const modalVisible = overlay && overlay.classList.contains('visible');
            if (!modalVisible && hasBiometric && isInApp && userId) {
                console.log('🔐 Triggering biometric prompt');
                window.AndroidBiometric.authenticate('auto');
            }
        });

        // ✅ Show spinner on button click
        (function () {
            var form = document.getElementById('loginForm');
            var btn = document.getElementById('loginBtn');
            if (form && btn) {
                form.addEventListener('submit', function () {
                    if (btn.disabled) return;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="btn-spinner"></span><span>Logging in...</span>';
                });
            }
        })();

        function showBiometricStatus(message, type) {
            biometricStatus.textContent = message;
            biometricStatus.className = 'status-message show ' + type;
        }

        function showBiometricStatusWithSpinner(message) {
            biometricStatus.innerHTML = '<div class="btn-spinner" style="border-color: #bbf7d0; border-top-color: #16a34a; margin-right: 8px;"></div><span>' + message + '</span>';
            biometricStatus.className = 'status-message show success';
            biometricStatus.style.display = 'flex';
            biometricStatus.style.alignItems = 'center';
            biometricStatus.style.justifyContent = 'flex-start';
        }

        function biometricSuccess(data) {
            console.log('✅ Biometric success called');

            const userId = <?php echo json_encode($biometricUserId); ?>;
            const userType = <?php echo json_encode($biometricUserType); ?>;

            if (!userId) {
                showBiometricStatus('Biometric not registered. Please login with password.', 'error');
                return;
            }

            var installedVersion = readInstalledVersion();

            showBiometricStatusWithSpinner('Accessing your account...');

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'biometric_login=true'
                    + '&user_id=' + encodeURIComponent(userId)
                    + '&user_type=' + encodeURIComponent(userType)
                    + '&installed_version=' + encodeURIComponent(installedVersion)
            })
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        setTimeout(function () {
                            window.location.href = data.redirect;
                        }, 1000);
                    } else if (data.show_update_modal) {
                        // Show the update modal
                        const overlay = document.getElementById('updateOverlay');
                        if (overlay) overlay.classList.add('visible');
                    } else {
                        showBiometricStatus('' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('❌ Fetch error:', error);
                    showBiometricStatus('Error: ' + error.message, 'error');
                });
        }

        function biometricFailed() {
            showBiometricStatus('Authentication failed. Please try again.', 'error');
        }

        function biometricCancel() {
            biometricStatus.className = 'status-message';
            biometricStatus.textContent = '';
        }

        function biometricError(error) {
            showBiometricStatus('Error: ' + error, 'error');
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

            const buttons = document.querySelectorAll('.user-type-toggle button');
            buttons.forEach(btn => btn.classList.remove('active'));

            document.querySelectorAll('.select-group').forEach(group => {
                group.classList.remove('visible');
            });

            if (type === 'Admin') {
                buttons[0].classList.add('active');
                document.getElementById('adminSelectGroup').classList.add('visible');
                document.getElementById('adminSelect').disabled = false;
                document.getElementById('customerSelect').disabled = true;
            } else {
                buttons[1].classList.add('active');
                document.getElementById('customerSelectGroup').classList.add('visible');
                document.getElementById('customerSelect').disabled = false;
                document.getElementById('adminSelect').disabled = true;
            }
        }
    </script>
</body>

</html>