<?php
// customers-portal.php – Customer-only login (desktop + mobile + in-app flows)

$sessionLifetime = 604800;
ini_set('session.cookie_lifetime', $sessionLifetime);
ini_set('session.gc_maxlifetime', $sessionLifetime);

require_once __DIR__ . '/bots.php';
session_start();
require_once __DIR__ . '/DB_Conn/config.php';

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

$loginType = $isInApp ? 'app' : 'web';

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

// ============================================================
// ✅ COOKIE HELPERS — remember account number across visits
// ============================================================
const ACC_COOKIE_NAME = 'customer_acc_number';
const ACC_COOKIE_TTL  = 86400 * 365; // 1 year

function setAccNumberCookie(string $accNumber): void
{
    if ($accNumber === '') return;
    setcookie(ACC_COOKIE_NAME, $accNumber, [
        'expires'  => time() + ACC_COOKIE_TTL,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[ACC_COOKIE_NAME] = $accNumber;
}

function clearAccNumberCookie(): void
{
    setcookie(ACC_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[ACC_COOKIE_NAME]);
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

    setAccNumberCookie((string) $user['acc_number']);

    updateLoginMeta($pdo, $user['id'], $loginType);

    $redirectUrl = getCustomerRedirect($isInApp, $isMobileBrowser, $user);

    echo json_encode(['success' => true, 'redirect' => $redirectUrl, 'message' => '']);
    exit;
}

$errors = [];
$loginSuccess = false;
$selectedAcc = '';

if (isset($_COOKIE[ACC_COOKIE_NAME]) && is_string($_COOKIE[ACC_COOKIE_NAME])) {
    $selectedAcc = trim($_COOKIE[ACC_COOKIE_NAME]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['biometric_login'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Your session has expired or is invalid. Please refresh the page and try again.';
    } else {
        $selectedAcc = trim($_POST['acc_number'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($selectedAcc))
            $errors[] = 'Account Number cannot be empty.';
        if (empty($password))
            $errors[] = 'PIN cannot be empty.';
        elseif (!preg_match('/^[0-9]{6}$/', $password))
            $errors[] = 'PIN must be exactly 6 digits.';

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
                clearAccNumberCookie();
                $errors[] = 'This account is not registered as a Customer. Please check your Account Number or register a Customer account.';
            } elseif (!password_verify($password, $user['password'])) {
                $errors[] = 'Invalid PIN. Please try again.';
            } else {
                date_default_timezone_set('Asia/Manila');

                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = 'Customer';
                $_SESSION['acc_number'] = $user['acc_number'];

                setcookie('user_id', $user['id'], time() + (86400 * 365), "/");
                setcookie('user_type', 'Customer', time() + (86400 * 365), "/");
                setcookie('biometric_enrolled', $user['biometric_enrolled'] ?? 0, time() + (86400 * 365), "/");

                setAccNumberCookie((string) $user['acc_number']);

                updateLoginMeta($pdo, $user['id'], $loginType);

                $loginSuccess = true;

                $redirectUrl = getCustomerRedirect($isInApp, $isMobileBrowser, $user);

                header('Location: ' . $redirectUrl);
                exit;
            }
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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            height: 100%;
            overflow-x: hidden;
        }

        body {
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
        }

        .brand-panel {
            display: none;
        }

        .form-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            background: #ffffff;
            min-height: 100dvh;
            position: relative;
        }

        .auth-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 30px 20px;
            width: 100%;
        }

        .auth-card {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
            position: relative;
        }

        /* ========== LOGO (no border, no radius, no bg) ========== */
        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 28px;
        }
        .logo-stack .main-logo {
            width: 130px;
            height: 130px;
            object-fit: contain;
            display: block;
        }

        .portal-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .portal-header::before {
            content: "";
            width: 5px;
            height: 34px;
            border-radius: 3px;
            background: linear-gradient(135deg, #1a2a6c 0%, #3b6fd4 100%);
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(26, 42, 108, 0.35);
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.2;
            margin: 0;
            background: linear-gradient(135deg, #1a2a6c 0%, #3b6fd4 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }
        .portal-subtitle {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 32px;
            padding-left: 17px;
            font-weight: 400;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
            font-size: 14px;
        }
        .form-group label i { color: #1a2a6c; font-size: 14px; }

        .form-group select,
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
        }
        .form-group select:focus,
        .form-group input:focus {
            border-color: #1a2a6c;
            box-shadow: 0 0 0 4px rgba(26, 42, 108, 0.1);
            background: #ffffff;
        }
        .form-group input::placeholder { color: #94a3b8; }

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
        .password-wrapper i:hover { color: #1a2a6c; }

        .pin-masked {
            -webkit-text-security: disc;
            text-security: disc;
        }
        .pin-masked.pin-visible {
            -webkit-text-security: none;
            text-security: none;
        }

        .forgot-password-link {
            text-align: right;
            margin-top: 8px;
            font-size: 13px;
        }
        .forgot-password-link a {
            color: #1a2a6c;
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
        }
        .forgot-password-link a:hover {
            color: #3b6fd4;
            text-decoration: underline;
        }

        .action-area {
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .btn-primary {
            width: 100%;
            height: 54px;
            background: linear-gradient(145deg, #1a2a6c, #3b6fd4);
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
            box-shadow: 0 6px 18px rgba(26, 42, 108, 0.25);
        }
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(26, 42, 108, 0.35);
        }
        .btn-primary:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(26, 42, 108, 0.25);
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
        }
        @keyframes btn-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .biometric-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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
            background: linear-gradient(to right, transparent 0%, #e2e8f0 40%, #e2e8f0 60%, transparent 100%);
        }
        .fingerprint-row { display: flex; justify-content: center; }
        .btn-fingerprint {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            color: #1a2a6c;
            cursor: pointer;
            transition: 0.25s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .btn-fingerprint i { font-size: 28px; transition: color 0.3s; }
        .btn-fingerprint:hover {
            border-color: #1a2a6c;
            background: #eef2fb;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(26, 42, 108, 0.25);
        }
        .btn-fingerprint:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            line-height: 1.5;
        }
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .alert-info {
            background: #eef2fb;
            color: #1a2a6c;
            border: 1px solid #c7d4f0;
        }
        .alert i { font-size: 16px; margin-top: 2px; flex-shrink: 0; }

        .register-footer {
            margin-top: 30px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 14px;
            color: #64748b;
        }
        .register-footer a {
            color: #1a2a6c;
            text-decoration: none;
            font-weight: 700;
            transition: 0.2s;
            margin-left: 4px;
        }
        .register-footer a:hover {
            color: #3b6fd4;
            text-decoration: underline;
        }

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
        }
        .bio-popup-overlay.visible { display: flex; animation: bioFadeIn 0.2s ease-out; }
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
        .bio-popup-icon { padding: 26px 24px 12px; display: flex; justify-content: center; }
        .bio-popup-icon i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            border-radius: 50%;
            font-size: 32px;
            background: linear-gradient(145deg, #e3eafa, #eef2fb);
            color: #1a2a6c;
            box-shadow: 0 8px 20px rgba(26, 42, 108, 0.2);
        }
        .bio-popup-title {
            padding: 0 24px;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }
        .bio-popup-message {
            padding: 10px 26px 22px;
            font-size: 14px;
            color: #475569;
            line-height: 1.55;
        }
        .bio-popup-actions { padding: 0 20px 20px; }
        .bio-popup-btn {
            width: 100%;
            height: 48px;
            background: linear-gradient(145deg, #1a2a6c, #3b6fd4);
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 15px;
            color: #ffffff;
            cursor: pointer;
            transition: 0.25s ease;
        }
        .bio-popup-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(26, 42, 108, 0.35);
        }
        @keyframes bioFadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes bioPopIn {
            from { opacity: 0; transform: scale(0.9) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        @media (min-width: 900px) {
            body {
                background: #f4f6fb;
                padding: 0;
                min-height: 100vh;
            }

            .brand-panel {
                display: flex;
                flex: 1;
                background: linear-gradient(135deg, #1a2a6c 0%, #2a4d9b 60%, #3b6fd4 100%);
                color: #fff;
                flex-direction: column;
                justify-content: center;
                padding: 60px;
                position: relative;
                overflow: hidden;
                min-height: 100vh;
            }

            .brand-panel::before,
            .brand-panel::after {
                content: "";
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.06);
            }
            .brand-panel::before { width: 400px; height: 400px; top: -120px; right: -120px; }
            .brand-panel::after  { width: 300px; height: 300px; bottom: -100px; left: -80px; }

            .brand-logo {
                display: flex;
                align-items: center;
                gap: 14px;
                margin-bottom: 48px;
                z-index: 1;
            }
            /* ✅ Brand logo — no radius, no bg, no padding */
            .brand-logo-img {
                width: 56px;
                height: 56px;
                object-fit: contain;
            }
            .brand-logo-text {
                font-size: 18px;
                font-weight: 700;
                letter-spacing: 0.5px;
                line-height: 1.3;
            }

            .brand-panel h1 {
                font-size: 40px;
                line-height: 1.2;
                margin-bottom: 16px;
                z-index: 1;
                background: none;
                -webkit-text-fill-color: #fff;
                color: #fff;
                font-weight: 800;
            }
            .brand-panel p {
                font-size: 17px;
                opacity: 0.85;
                max-width: 380px;
                line-height: 1.6;
                z-index: 1;
            }
            .brand-panel .motto {
                margin-top: 48px;
                font-style: italic;
                font-size: 15px;
                opacity: 0.85;
                border-left: 3px solid #f5b301;
                padding-left: 16px;
                z-index: 1;
            }

            .form-panel {
                flex: 1;
                background: #ffffff;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 40px;
                min-height: 100vh;
            }

            .auth-container {
                padding: 0;
                width: 100%;
                max-width: 420px;
            }

            .auth-card {
                margin: 0;
                width: 100%;
                max-width: 100%;
            }

            .logo { display: none; }

            .portal-header { margin-top: 8px; }
            h1 { font-size: 28px; }
            .portal-subtitle { margin-bottom: 36px; }
        }

        @media (min-width: 640px) and (max-width: 899px) {
            body {
                background: #f1f5f9;
                justify-content: center;
                align-items: center;
            }
            .form-panel {
                flex: none;
                min-height: auto;
                max-width: 440px;
                border-radius: 16px;
                box-shadow: 0 20px 45px rgba(0, 0, 0, 0.08);
                border: 1px solid #e2e8f0;
                margin: 24px auto;
                overflow: hidden;
            }
            .auth-container { padding: 40px; }
            .logo { margin-bottom: 32px; }
            .logo-stack .main-logo { width: 180px; height: 180px; }
        }

        @media (max-width: 380px) {
            .logo { margin-bottom: 20px; }
            .logo-stack .main-logo { width: 120px; height: 120px; }
        }

        @media (max-height: 700px) {
            .logo { margin-bottom: 20px; }
            .logo-stack .main-logo { width: 110px; height: 110px; }
        }

        @media (max-width: 640px) {
            .logo-stack .main-logo {
                width: 130px;
                height: 130px;
            }
        }

        @media (max-width: 640px) and (max-height: 700px) {
            .logo-stack .main-logo {
                width: 110px;
                height: 110px;
            }
        }
    </style>
</head>

<body>

    <section class="brand-panel">
        <div class="brand-logo">
            <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/logo.jpeg"
                 alt="Villaruz Logo" class="brand-logo-img">
            <div class="brand-logo-text">VILLARUZ PRINT SHOP<br>&amp; GENERAL MERCHANDISE</div>
        </div>
        <h1>Welcome back!</h1>
        <p>Sign in to browse products, place orders, and track your deliveries — all in one place.</p>
        <div class="motto">"Precision in every print, care in every order."</div>
    </section>

    <section class="form-panel">
        <div class="auth-container">
            <div class="auth-card">

                <div class="logo">
                    <div class="logo-stack">
                        <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/logo.jpeg"
                            alt="Villaruz Print Shop Logo" class="main-logo">
                    </div>
                </div>

                <div class="portal-header">
                    <h1>Login to your account</h1>
                </div>
                <p class="portal-subtitle">Sign in to shop and track your orders</p>

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
                    <form method="POST" action="" id="loginForm" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Last 4 phone numbers</label>
                            <input type="tel" name="acc_number" id="acc_number" placeholder="Enter your last 4 phone number"
                                value="<?php echo htmlspecialchars($selectedAcc); ?>"
                                maxlength="4" inputmode="numeric" pattern="[0-9]{4}"
                                autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> 6-Digit PIN</label>
                            <div class="password-wrapper">
                                <input type="tel" name="password" id="password" placeholder="Enter your 6-digit PIN"
                                    maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                                    autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                                    class="pin-masked" required>
                                <i class="fas fa-eye-slash" id="togglePassword"></i>
                            </div>
                            <div class="forgot-password-link">
                                <a href="forgot_password.php">Forgot PIN?</a>
                            </div>
                        </div>

                        <div class="action-area">
                            <button type="submit" class="btn-primary" id="loginBtn" <?php echo $loginSuccess ? 'disabled' : ''; ?>>
                                Login
                            </button>

                            <?php if ($showFingerprint): ?>
                                <p class="biometric-label">Login using Biometric</p>
                                <div class="fingerprint-row">
                                    <button type="button" class="btn-fingerprint" id="fingerprintBtn"
                                        onclick="triggerBiometric()" title="Login with fingerprint">
                                        <i class="fas fa-fingerprint"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="register-footer">
                        Don't have an account? <a href="customers-registration.php">Login here</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="bio-popup-overlay" id="bioPopup" role="dialog" aria-modal="true" aria-labelledby="bioPopupTitle">
        <div class="bio-popup">
            <div class="bio-popup-icon">
                <i class="fas fa-fingerprint" id="bioPopupIcon"></i>
            </div>
            <div class="bio-popup-title" id="bioPopupTitle">Fingerprint not available</div>
            <div class="bio-popup-message" id="bioPopupMessage">
                No fingerprint enrolled. Please log in with PIN first and enroll your fingerprint.
            </div>
            <div class="bio-popup-actions">
                <button type="button" class="bio-popup-btn" onclick="closeBioPopup()">Got it</button>
            </div>
        </div>
    </div>

    <script>
        const isInApp = typeof window.AndroidBiometric !== 'undefined';
        const hasBiometric = <?php echo $hasBiometric ? 'true' : 'false'; ?>;
        const userId = <?php echo json_encode($biometricUserId); ?>;
        const userType = <?php echo json_encode($biometricUserType); ?>;
        const showFingerprint = <?php echo $showFingerprint ? 'true' : 'false'; ?>;

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

        document.getElementById('bioPopup').addEventListener('click', function (e) {
            if (e.target === this) closeBioPopup();
        });

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
                showBioPopup('App required', 'Fingerprint login is only available inside the Sofia app.', 'fas fa-mobile-alt');
                return;
            }
            if (!hasBiometric || !userId) {
                showBioPopup('Fingerprint not enrolled', 'No fingerprint enrolled. Please log in with PIN first and enroll your fingerprint.', 'fas fa-fingerprint');
                return;
            }
            var fpBtn = document.getElementById('fingerprintBtn');
            if (fpBtn) fpBtn.disabled = true;

            if (window.AndroidBiometric && window.AndroidBiometric.authenticate) {
                window.AndroidBiometric.authenticate('manual');
            } else {
                showBioPopup('Biometric unavailable', 'Biometric bridge not available on this device.', 'fas fa-exclamation-triangle');
                if (fpBtn) fpBtn.disabled = false;
            }
        }

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
        if (togglePassword && password) {
            togglePassword.addEventListener('click', function () {
                password.classList.toggle('pin-visible');
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        if (password) {
            password.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        }

        const accNumber = document.getElementById('acc_number');
        if (accNumber) {
            accNumber.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            var acc = document.getElementById('acc_number');
            var pin = document.getElementById('password');
            if (acc && pin && acc.value.trim() !== '') {
                pin.focus();
            }
        });
    </script>
</body>
</html>