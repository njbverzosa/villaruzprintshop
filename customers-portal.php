<?php
// customers-portal.php – Customer-only login (desktop + mobile + in-app flows)

$sessionLifetime = 604800;
ini_set('session.cookie_lifetime', $sessionLifetime);
ini_set('session.gc_maxlifetime', $sessionLifetime);

require_once __DIR__ . '/bots.php';
session_start();
require_once __DIR__ . '/DB_Conn/config.php';
include __DIR__ . '/app_version.php';

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isInApp = (stripos($userAgent, 'SofiaApp') !== false);

function isMobileBrowser($userAgent)
{
    if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent)) {
        return true;
    }
    return false;
}

$isMobileBrowser = isMobileBrowser($userAgent) && !$isInApp;
$showFingerprint = $isMobileBrowser || $isInApp;

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

if (isset($_GET['skip_update']) && $_GET['skip_update'] === '1') {
    setcookie('use_old_app', '1', [
        'expires' => time() + 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    header('Location: customers-portal.php');
    exit;
}

$skipUpdate = isset($_COOKIE['use_old_app']) && $_COOKIE['use_old_app'] === '1';
$renderUpdateModal = ($isInApp && !$skipUpdate);

$skipBiometricAutoPrompt = ($_SERVER['REQUEST_METHOD'] === 'POST');

$biometricPage = 'public/biometric.php';

function updateLoginMeta($pdo, $userId, $loginType)
{
    date_default_timezone_set('Asia/Manila');
    $onlineTime = date('M j, g:i A');

    try {
        $stmt = $pdo->prepare("UPDATE customers SET online_time = ?, login_type = ? WHERE id = ?");
        $stmt->execute([$onlineTime, $loginType, $userId]);
    } catch (PDOException $e) {
        error_log("updateLoginMeta failed: " . $e->getMessage());
    }
}

function getCustomerRedirect($isInApp, $isMobileBrowser, $user)
{
    global $biometricPage;

    $hasBiometric = (($user['biometric_enrolled'] ?? 0) == 1 && !empty($user['biometric_id'] ?? ''));

    if ($isInApp && !$hasBiometric) {
        $_SESSION['temp_user_id'] = $user['id'];
        $_SESSION['temp_user_type'] = 'Customer';
        return $biometricPage;
    }

    if ($isInApp)
        return 'public/shop.php';
    if ($isMobileBrowser)
        return 'public/download_app.php';
    return 'public/shop.php';
}

$isLoggedIn = false;
$redirectUrl = '';
$userName = '';

if (isset($_SESSION['user_role'], $_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer') {
    $isLoggedIn = true;
    $userName = $_SESSION['acc_number'] ?? 'Customer';

    $stmt = $pdo->prepare("SELECT id, f_name, biometric_enrolled, biometric_id FROM customers WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $loggedInUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($loggedInUser) {
        $redirectUrl = getCustomerRedirect($isInApp, $isMobileBrowser, $loggedInUser);
    }
}

$hasBiometric = false;
$biometricUserId = null;
$biometricUserType = null;

if (isset($_SESSION['user_id'], $_SESSION['user_role']) && $_SESSION['user_role'] === 'Customer') {
    $userId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT id, biometric_enrolled, biometric_id FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user && $user['biometric_enrolled'] == 1) {
        $hasBiometric = true;
        $biometricUserId = $userId;
        $biometricUserType = 'Customer';
    }
} elseif (isset($_COOKIE['user_id'], $_COOKIE['user_type']) && $_COOKIE['user_type'] === 'Customer') {
    $userId = $_COOKIE['user_id'];

    $stmt = $pdo->prepare("SELECT id, biometric_enrolled, biometric_id, acc_number FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user && $user['biometric_enrolled'] == 1) {
        $hasBiometric = true;
        $biometricUserId = $userId;
        $biometricUserType = 'Customer';

        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = 'Customer';
        $_SESSION['acc_number'] = $user['acc_number'] ?? 'Customer';
    }
}

if (isset($_POST['biometric_login']) && $_POST['biometric_login'] === 'true') {
    header('Content-Type: application/json');

    $userId = $_POST['user_id'] ?? null;

    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Missing user data']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, biometric_enrolled, acc_number, f_name, biometric_id FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit;
    }

    if ($user['biometric_enrolled'] != 1) {
        echo json_encode(['success' => false, 'message' => 'Biometric not enrolled']);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = 'Customer';
    $_SESSION['acc_number'] = $user['acc_number'];

    setcookie('user_id', $user['id'], time() + (86400 * 365), "/");
    setcookie('user_type', 'Customer', time() + (86400 * 365), "/");
    setcookie('biometric_enrolled', $user['biometric_enrolled'] ?? 0, time() + (86400 * 365), "/");

    updateLoginMeta($pdo, $user['id'], $loginType);

    if ($isInApp && !$appVersionMatch && !$skipUpdate) {
        echo json_encode([
            'success' => false,
            'message' => 'Please update the app or click SKIP to continue.',
            'show_update_modal' => true
        ]);
        exit;
    }

    $redirectUrl = getCustomerRedirect($isInApp, $isMobileBrowser, $user);

    echo json_encode(['success' => true, 'redirect' => $redirectUrl, 'message' => '']);
    exit;
}

$errors = [];
$loginSuccess = false;
$selectedAcc = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['biometric_login'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    $selectedAcc = trim($_POST['acc_number'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($selectedAcc))
        $errors[] = 'Account Number cannot be empty.';
    if (empty($password))
        $errors[] = 'Password cannot be empty.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            SELECT id, password, acc_number, phone_number, f_name,
                   email, biometric_enrolled, biometric_id
            FROM customers
            WHERE acc_number = ?
            LIMIT 1
        ");
        $stmt->execute([$selectedAcc]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errors[] = 'This account is not registered as a Customer. Please check your Account Number or register a Customer account.';
        } elseif (!password_verify($password, $user['password'])) {
            $errors[] = 'Invalid password. Please try again.';
        } else {
            date_default_timezone_set('Asia/Manila');

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = 'Customer';
            $_SESSION['acc_number'] = $user['acc_number'];

            setcookie('user_id', $user['id'], time() + (86400 * 365), "/");
            setcookie('user_type', 'Customer', time() + (86400 * 365), "/");
            setcookie('biometric_enrolled', $user['biometric_enrolled'] ?? 0, time() + (86400 * 365), "/");

            updateLoginMeta($pdo, $user['id'], $loginType);

            $loginSuccess = true;

            $redirectUrl = getCustomerRedirect($isInApp, $isMobileBrowser, $user);

            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

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
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Customer Login | Villaruz Print Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        html,
        body {
            height: 100%;
            overflow-x: hidden;
        }

        body {
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
            position: relative;
        }

        /* ========== APP SHELL ========== */
        .app-shell {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            background: #ffffff;
            min-height: 100dvh;
            position: relative;
            overflow: hidden;
            padding: 0;
            z-index: 2;
        }

        /* ✅ Light GIF — frame ng box sa tuktok */
        .light-overlay {
            display: block;
            width: 100%;
            height: auto;
            object-fit: contain;
            object-position: center top;
            pointer-events: none;
            flex-shrink: 0;
            margin: 0 !important;
            padding: 0 !important;
            border: 0;
            vertical-align: top;
            line-height: 0;
            position: relative;
            top: -45px;
        }

        /* ✅ Gift GIF — footer, katulad ng light */
        .gift-footer {
            display: block;
            width: 100%;
            height: auto;
            object-fit: contain;
            object-position: center bottom;
            pointer-events: none;
            flex-shrink: 0;
            margin: 0 !important;
            padding: 0 !important;
            border: 0;
            vertical-align: bottom;
            line-height: 0;
            position: relative;
            bottom: -20px;
            margin-top: auto;
        }

        /* ========== CONTENT ========== */
        .auth-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0 20px 0 20px;
            min-height: 0;
            margin: 0;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 30px;
            width: 100%;
            max-width: 450px;
            margin: 16px auto 0 auto;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
        }

        /* ========== LOGO (nudged up) ========== */
        .logo {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            margin-top: -85px;
            margin-bottom: 8px;
            gap: 6px;
            position: relative;
            overflow: visible;
            z-index: 3;
        }

        .logo-stack {
            position: relative;
            width: 160px;
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-stack .main-logo {
            width: 96px;
            height: 96px;
            object-fit: contain;
            display: block;
            border-radius: 24px;
        }

        .logo-stack .hood-overlay {
            position: absolute;
            top: 12px;
            right: -4px;
            width: 100px;
            height: 100px;
            object-fit: contain;
            display: block;
            transform: rotate(20deg);
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.18));
            z-index: 2;
            pointer-events: none;
        }

        /* ========== PORTAL HEADER (nudged UP) ========== */
        .portal-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: -30px;
            margin-bottom: 6px;
            padding-left: 2px;
        }

        .portal-header::before {
            content: "";
            width: 5px;
            height: 34px;
            border-radius: 3px;
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
        }

        h1 {
            text-align: left;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.2;
            margin: 0;
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }

        /* ✅ generous space below the subtitle */
        .portal-subtitle {
            text-align: left;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 32px;
            padding-left: 17px;
            font-weight: 400;
            letter-spacing: 0.01em;
        }

        /* ========== FORM ========== */
        .form-group {
            margin-bottom: 16px;
        }

        /* ✅ Extra room above first field */
        .form-group:first-of-type {
            margin-top: 8px;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
            font-size: 14px;
        }

        .form-group label i {
            color: #3b82f6;
            font-size: 13px;
        }

        .form-group input {
            width: 100%;
            height: 54px;
            padding: 0 18px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            color: #1e293b;
            font-size: 16px;
            outline: none;
            transition: 0.25s ease;
            font-family: 'Poppins', sans-serif;
            -webkit-appearance: none;
            appearance: none;
            display: block;
            line-height: normal;
        }

        .form-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            background: #ffffff;
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            flex: 1;
            padding-right: 48px;
        }

        .password-wrapper i {
            position: absolute;
            right: 16px;
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
            margin-top: 8px;
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

        /* ========== ACTION AREA ========== */
        .action-area {
            margin-top: auto;
            padding-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .btn-primary {
            width: 100%;
            height: 54px;
            background: linear-gradient(145deg, #3b82f6, #6366f1);
            border: none;
            padding: 0 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            color: white;
            cursor: pointer;
            transition: 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: 'Poppins', sans-serif;
            -webkit-appearance: none;
            appearance: none;
            box-shadow: 0 6px 18px rgba(59, 130, 246, 0.25);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(59, 130, 246, 0.35);
        }

        .btn-primary:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
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

        /* ========== BIOMETRIC LABEL ========== */
        .biometric-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 14px;
            margin-bottom: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            text-align: center;
            white-space: nowrap;
        }

        .biometric-label::before,
        .biometric-label::after {
            content: "";
            flex: 1;
            height: 1px;
            background: linear-gradient(to right,
                    transparent 0%,
                    #e2e8f0 40%,
                    #e2e8f0 60%,
                    transparent 100%);
        }

        /* ========== FINGERPRINT ========== */
        .fingerprint-row {
            display: flex;
            justify-content: center;
            margin-top: 8px;
        }

        .btn-fingerprint {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            color: #3b82f6;
            cursor: pointer;
            transition: 0.25s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        .btn-fingerprint i {
            font-size: 26px;
            color: #3b82f6;
            transition: color 0.3s;
        }

        .btn-fingerprint:hover {
            border-color: #3b82f6;
            background: #eff6ff;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.25);
        }

        .btn-fingerprint:hover i {
            color: #2563eb;
        }

        .btn-fingerprint:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
            animation: slideDown 0.5s ease;
            line-height: 1.5;
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

        .alert-success {
            background: #f0fdf4;
            color: #065f46;
            border: 1px solid #bbf7d0;
        }

        .alert i {
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
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

        /* ========== FOOTERS ========== */
        .auth-footer {
            text-align: center;
            margin-top: 18px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        .auth-footer a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .auth-footer a:hover {
            color: #2563eb;
            text-decoration: underline;
        }

        /* ==============================================
           ✅ CUSTOM BIOMETRIC POPUP (no URL change)
           ============================================== */
        .bio-popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            animation: bioFadeIn 0.2s ease-out;
        }

        .bio-popup-overlay.visible {
            display: flex;
        }

        .bio-popup {
            background: #ffffff;
            border-radius: 18px;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            text-align: center;
            animation: bioPopIn 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .bio-popup-icon {
            padding: 26px 24px 12px 24px;
            display: flex;
            justify-content: center;
        }

        .bio-popup-icon i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            border-radius: 50%;
            font-size: 32px;
            background: linear-gradient(145deg, #dbeafe, #eff6ff);
            color: #3b82f6;
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.2);
        }

        .bio-popup-title {
            padding: 0 24px;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }

        .bio-popup-message {
            padding: 10px 26px 22px 26px;
            font-size: 14px;
            color: #475569;
            line-height: 1.55;
        }

        .bio-popup-actions {
            padding: 0 20px 20px 20px;
        }

        .bio-popup-btn {
            width: 100%;
            height: 48px;
            background: linear-gradient(145deg, #3b82f6, #6366f1);
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 15px;
            color: #ffffff;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(59, 130, 246, 0.25);
            transition: 0.25s ease;
        }

        .bio-popup-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(59, 130, 246, 0.35);
        }

        .bio-popup-btn:active {
            transform: translateY(0);
        }

        @keyframes bioFadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes bioPopIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(10px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* ========== UPDATE MODAL ========== */
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
           SNOW EFFECT ❄️ (auto — no button needed)
           ============================================== */
        .snow-container {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 9998;
            overflow: hidden;
        }

        .snowflake {
            position: absolute;
            top: -10px;
            color: #60a5fa;
            font-size: 1em;
            font-family: Arial, sans-serif;
            text-shadow: 0 0 6px rgba(255, 255, 255, 0.9),
                0 0 12px rgba(96, 165, 250, 0.8),
                0 0 20px rgba(59, 130, 246, 0.5);
            user-select: none;
            pointer-events: none;
            animation-name: snowfall;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
            will-change: transform, opacity;
        }

        .snowflake::before {
            content: "❄";
        }

        @keyframes snowfall {
            0% {
                transform: translateY(-10vh) translateX(0) rotate(0deg);
                opacity: 0;
            }

            10% { opacity: 1; }
            90% { opacity: 1; }
            100% {
                transform: translateY(110vh) translateX(40px) rotate(360deg);
                opacity: 0;
            }
        }

        .snowflake:nth-child(odd) {
            animation-name: snowfall-sway;
        }

        @keyframes snowfall-sway {
            0% {
                transform: translateY(-10vh) translateX(0) rotate(0deg);
                opacity: 0;
            }

            10% { opacity: 1; }
            50% { transform: translateY(50vh) translateX(-30px) rotate(180deg); }
            90% { opacity: 1; }
            100% {
                transform: translateY(110vh) translateX(30px) rotate(360deg);
                opacity: 0;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .snowflake {
                animation: none !important;
                display: none;
            }
        }

        /* ========== DESKTOP ========== */
        @media (min-width: 600px) {
            body {
                background: #f1f5f9;
                padding-top: 0;
                padding-bottom: 0;
                justify-content: center;
                align-items: center;
            }

            .app-shell {
                min-height: auto;
                height: auto;
                margin: 0 auto;
                border-radius: 15px;
                box-shadow: 0 20px 45px rgba(0, 0, 0, 0.08);
                border: 1px solid #e2e8f0;
                overflow: hidden;
            }

            .light-overlay {
                border-radius: 15px 15px 0 0;
                top: -40px;
            }

            .gift-footer {
                border-radius: 0 0 15px 15px;
                bottom: -20px;
            }

            .auth-container {
                flex: none;
                padding: 0 40px 0 40px;
            }

            .auth-card {
                flex: none;
                padding: 0;
                border: none;
                box-shadow: none;
                max-width: 100%;
                margin-top: 24px;
            }

            .logo {
                margin-top: -95px;
            }

            /* ✅ nudged UP on desktop */
            .portal-header {
                margin-top: -35px;
            }

            h1 {
                font-size: 28px;
            }

            .portal-header::before {
                height: 38px;
            }

            /* ✅ more space on desktop too */
            .portal-subtitle {
                font-size: 14px;
                margin-bottom: 36px;
            }

            .action-area {
                margin-top: 8px;
                padding-top: 8px;
            }
        }

        /* ========== SMALL PHONES ========== */
        @media (max-width: 380px) {
            .auth-container {
                padding: 0 18px 0 18px;
            }

            .auth-card {
                margin-top: 12px;
            }

            .logo {
                margin-top: -75px;
                margin-bottom: 6px;
            }

            .logo-stack {
                width: 140px;
                height: 140px;
            }

            .logo-stack .main-logo {
                width: 80px;
                height: 80px;
            }

            .logo-stack .hood-overlay {
                width: 84px;
                height: 84px;
                top: 6px;
                right: -4px;
                transform: rotate(20deg);
            }

            /* ✅ nudged UP on small phones */
            .portal-header {
                margin-top: -25px;
            }

            h1 {
                font-size: 20px;
            }

            .portal-header::before {
                height: 30px;
                width: 4px;
            }

            .portal-subtitle {
                font-size: 12px;
                padding-left: 16px;
                margin-bottom: 26px;
            }

            .form-group input {
                height: 50px;
            }

            .btn-primary {
                height: 50px;
            }
        }

        /* ========== SHORT SCREENS ========== */
        @media (max-height: 700px) {
            .auth-container {
                padding: 0 24px 0 24px;
            }

            .auth-card {
                margin-top: 12px;
            }

            .logo {
                margin-top: -65px;
                margin-bottom: 6px;
            }

            .logo-stack {
                width: 130px;
                height: 130px;
            }

            .logo-stack .main-logo {
                width: 76px;
                height: 76px;
            }

            .logo-stack .hood-overlay {
                width: 78px;
                height: 78px;
                top: 4px;
                right: -4px;
                transform: rotate(20deg);
            }

            /* ✅ nudged UP on short screens */
            .portal-header {
                margin-top: -20px;
            }

            h1 {
                font-size: 20px;
            }

            .portal-header::before {
                height: 28px;
            }

            .portal-subtitle {
                font-size: 12px;
                margin-bottom: 20px;
            }

            .form-group {
                margin-bottom: 12px;
            }

            .form-group input {
                height: 50px;
            }

            .btn-primary {
                height: 50px;
            }

            .action-area {
                padding-top: 16px;
            }

            .fingerprint-row {
                margin-top: 8px;
            }

            .btn-fingerprint {
                width: 52px;
                height: 52px;
            }

            .btn-fingerprint i {
                font-size: 22px;
            }

            .auth-footer {
                margin-top: 12px;
                font-size: 13px;
            }

            .biometric-label {
                font-size: 11px;
                margin-top: 10px;
                margin-bottom: 2px;
            }
        }

        /* ========== MOBILE FULL-SCREEN APP VIEW ========== */
        @media (max-width: 640px) {
            body {
                background: #ffffff;
            }

            .auth-container {
                padding: 0;
                align-items: stretch;
                justify-content: flex-start;
                flex: 1;
            }

            .auth-card {
                background: #ffffff;
                border-radius: 0;
                border: none;
                box-shadow: none;
                max-width: 100%;
                width: 100%;
                padding: 20px 22px 0 22px;
                min-height: 100dvh;
                display: flex;
                flex-direction: column;
                margin-top: 0;
            }

            .logo {
                align-items: center;
                text-align: center;
                gap: 6px;
                margin-top: -90px;
                margin-bottom: 0;
            }

            /* ✅ nudged UP on mobile app view */
            .portal-header {
                margin-top: -15px;
                margin-bottom: 6px;
            }

            h1 {
                font-size: 22px;
            }

            /* ✅ generous space under subtitle */
            .portal-subtitle {
                font-size: 13px;
                margin-bottom: 28px;
            }

            .form-group {
                margin-bottom: 22px;
            }

            .form-group input {
                padding: 15px 16px;
                font-size: 16px;
                border-radius: 10px;
                height: 54px;
            }

            .btn-primary {
                padding: 16px;
                font-size: 16px;
                border-radius: 10px;
                height: 54px;
            }
        }
    </style>
</head>

<body>
    <!-- ❄️ Snow Effect Layer (auto — no button needed) -->
    <div class="snow-container" id="snowContainer" aria-hidden="true"></div>

    <div class="app-shell">
        <!-- ✅ Light GIF — frame ng box sa tuktok -->
        <img src="logo/light.gif" alt="Light" class="light-overlay">

        <div class="auth-container">
            <div class="auth-card">

                <!-- ✅ Logo with hood on RIGHT side, rotated (no version badge) -->
                <div class="logo">
                    <div class="logo-stack">
                        <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/ic_launcher.png"
                            alt="Villaruz Print Shop Logo" class="main-logo">
                        <img src="logo/hood.jpg" alt="Hood" class="hood-overlay">
                    </div>
                </div>

                <div class="portal-header">
                    <h1>Login to your account</h1>
                </div>
                <br><br>
                <?php if (!empty($offlineMessage)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-sign-out-alt"></i>
                        <div><?php echo htmlspecialchars($offlineMessage); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <?php foreach ($errors as $error): ?>
                                <?php echo htmlspecialchars($error); ?><br>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="passwordSection">
                    <form method="POST" action="" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="installed_version" id="installedVersionInput" value="">

                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Account Number</label>
                            <input type="text" name="acc_number" id="acc_number" placeholder=""
                                value="<?php echo htmlspecialchars($selectedAcc); ?>" autocomplete="username" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Password</label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="password" placeholder=""
                                    autocomplete="current-password" required>
                                <i class="fas fa-eye-slash" id="togglePassword"></i>
                            </div>
                            <div class="forgot-password-link">
                                <a href="forgot_password.php">Forgot password?</a>
                            </div>
                        </div>

                        <div class="action-area">
                            <button type="submit" class="btn-primary" id="loginBtn" <?php echo $loginSuccess ? 'disabled' : ''; ?>>
                                Login
                            </button>

                            <?php if ($showFingerprint): ?>
                                <!-- ✅ Biometric label -->
                                <p class="biometric-label">Login using Biometric</p>

                                <div class="fingerprint-row">
                                    <button type="button" class="btn-fingerprint" id="fingerprintBtn"
                                        onclick="triggerBiometric()" title="Login with fingerprint">
                                        <i class="fas fa-fingerprint"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="auth-footer">
                            Don't have an account? <a href="registration.php">Register</a>
                        </div>
                    </form>
                </div>

            </div>
        </div>

        <!-- ✅ Gift GIF — footer, katulad ng light -->
        <img src="logo/gift.jpg" alt="Gift" class="gift-footer">
    </div>

    <!-- ✅ Custom biometric popup (NO URL change, NO #hash) -->
    <div class="bio-popup-overlay" id="bioPopup" role="dialog" aria-modal="true" aria-labelledby="bioPopupTitle">
        <div class="bio-popup">
            <div class="bio-popup-icon">
                <i class="fas fa-fingerprint" id="bioPopupIcon"></i>
            </div>
            <div class="bio-popup-title" id="bioPopupTitle">Fingerprint not available</div>
            <div class="bio-popup-message" id="bioPopupMessage">
                No fingerprint enrolled. Please log in with password first and enroll your fingerprint.
            </div>
            <div class="bio-popup-actions">
                <button type="button" class="bio-popup-btn" onclick="closeBioPopup()">Got it</button>
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
        const showFingerprint = <?php echo $showFingerprint ? 'true' : 'false'; ?>;

        /* ✅ Custom popup helpers */
        function showBioPopup(title, message, iconClass) {
            document.getElementById('bioPopupTitle').textContent = title;
            document.getElementById('bioPopupMessage').textContent = message;
            const iconEl = document.getElementById('bioPopupIcon');
            iconEl.className = iconClass || 'fas fa-fingerprint';
            document.getElementById('bioPopup').classList.add('visible');
        }

        function closeBioPopup() {
            document.getElementById('bioPopup').classList.remove('visible');
        }

        /* Close on overlay click (outside the popup card) */
        document.getElementById('bioPopup').addEventListener('click', function (e) {
            if (e.target === this) closeBioPopup();
        });

        /* Close on Escape key */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeBioPopup();
        });

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

        /* ✅ Manual trigger — user MUST tap the fingerprint button */
        function triggerBiometric() {
            if (!isInApp) {
                showBioPopup(
                    'App required',
                    'Fingerprint login is only available inside the Sofia app.',
                    'fas fa-mobile-alt'
                );
                return;
            }

            if (!hasBiometric || !userId) {
                showBioPopup(
                    'Fingerprint not enrolled',
                    'No fingerprint enrolled. Please log in with password first and enroll your fingerprint.',
                    'fas fa-fingerprint'
                );
                return;
            }

            var fpBtn = document.getElementById('fingerprintBtn');
            if (fpBtn) fpBtn.disabled = true;

            if (window.AndroidBiometric && window.AndroidBiometric.authenticate) {
                window.AndroidBiometric.authenticate('manual');
            } else {
                showBioPopup(
                    'Biometric unavailable',
                    'Biometric bridge not available on this device.',
                    'fas fa-exclamation-triangle'
                );
                if (fpBtn) fpBtn.disabled = false;
            }
        }

        /* ❄️ Snow Effect Generator — runs automatically, no button needed */
        (function generateSnow() {
            function startSnow() {
                const container = document.getElementById('snowContainer');
                if (!container) return;

                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    return;
                }

                if (container.dataset.started === '1') return;
                container.dataset.started = '1';

                const SNOWFLAKE_COUNT = 60;

                for (let i = 0; i < SNOWFLAKE_COUNT; i++) {
                    const flake = document.createElement('div');
                    flake.className = 'snowflake';

                    flake.style.left = (Math.random() * 100) + '%';

                    const size = Math.random() * 12 + 8;
                    flake.style.fontSize = size + 'px';

                    const duration = Math.random() * 6 + 5;
                    flake.style.animationDuration = duration + 's';

                    flake.style.animationDelay = (Math.random() * 5) + 's';

                    flake.style.opacity = (Math.random() * 0.5 + 0.4).toFixed(2);

                    container.appendChild(flake);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startSnow);
            } else {
                startSnow();
            }
        })();

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

            /* ✅ REMOVED: auto biometric prompt on page load.
               User must now tap the fingerprint button to trigger biometric. */
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

            if (!userId) { resetLoginButton(); return; }

            var installedVersion = readInstalledVersion();

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'biometric_login=true'
                    + '&user_id=' + encodeURIComponent(userId)
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
    </script>
</body>

</html>