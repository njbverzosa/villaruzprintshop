<?php
// login.php – with update redirect to download_app.php (no popup)

// Set session lifetime
$sessionLifetime = 604800;
ini_set('session.cookie_lifetime', $sessionLifetime);
ini_set('session.gc_maxlifetime', $sessionLifetime);

session_start();
require_once __DIR__ . '/DB_Conn/config.php';
require_once __DIR__ . '/update_version.php';

// ==============================================
// ✅ DETECT LOGIN PLATFORM (APP OR WEB)
// ==============================================
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Check if user is using the app (WebView with our signature)
$isInApp = (strpos($userAgent, 'SofiaApp') !== false);

// Fallback: check for WebView marker
if (!$isInApp) {
    $isInApp = (strpos($userAgent, 'wv') !== false);
}

// Set login type for database
$loginType = $isInApp ? 'app' : 'web';

// ✅ Use version_compare() for proper version comparison
$needsUpdate = version_compare($latestVersion, $currentVersion, '>');

// ✅ Check if user already reminded about THIS version
$remindedVersion = $_COOKIE['update_reminded_version'] ?? '';
$reminded = ($remindedVersion === $latestVersion);

// ==============================================
// CHECK IF USER IS ALREADY LOGGED IN
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
// CHECK IF USER HAS BIOMETRIC ENROLLED
// ==============================================
$hasBiometric = false;
$biometricUserId = null;
$biometricUserType = null;

// Check from session first (user is already logged in)
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
}
// Check from cookie (user has logged in before)
elseif (isset($_COOKIE['user_id']) && isset($_COOKIE['user_type'])) {
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
// HANDLE BIOMETRIC LOGIN (API)
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

    // ✅ Biometric is valid - log the user in
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $userType;
    $_SESSION['acc_number'] = $user['acc_number'];

    setcookie('user_id', $user['id'], time() + (86400 * 365), "/");
    setcookie('user_type', $userType, time() + (86400 * 365), "/");
    setcookie('biometric_enrolled', $user['biometric_enrolled'] ?? 0, time() + (86400 * 365), "/");

    // ==============================================
    // ✅ SAVE LOGIN TYPE TO DATABASE (app or web)
    // ==============================================
    $platformTable = ($userType === 'Admin') ? 'admins' : 'customers';
    $updateTypeStmt = $pdo->prepare("UPDATE $platformTable SET login_type = ? WHERE id = ?");
    $updateTypeStmt->execute([$loginType, $user['id']]);

    // ✅ Determine redirect URL (with update redirect logic)
    $remindedVersion = $_COOKIE['update_reminded_version'] ?? '';
    $reminded = ($remindedVersion === $latestVersion);
    $needsUpdate = version_compare($latestVersion, $currentVersion, '>');

    if ($needsUpdate && !$reminded) {
        // Cookie not set → redirect to download_app.php
        $redirectUrl = 'public/download_app.php';
    } else {
        // Cookie set → go to dashboard
        if ($userType === 'Admin') {
            $redirectUrl = 'web/all_products.php';
        } else {
            $isGuest = ($user['f_name'] === 'Guest' || empty($user['f_name']));
            $redirectUrl = $isGuest ? 'public/account-edit.php' : 'public/shop.php';
        }
    }

    echo json_encode([
        'success' => true,
        'redirect' => $redirectUrl,
        'message' => 'Accessing your account...'
    ]);
    exit;
}

// ==============================================
// GET ALL DATA (for display in select options)
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
// HANDLE REGULAR LOGIN
// ==============================================
$errors = [];
$loginSuccess = false;
$userTypeSelected = 'Admin';
$selectedRole = '';
$selectedCustomerId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['biometric_login'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    $userTypeSelected = trim($_POST['user_type'] ?? 'Admin');
    $selectedRole = trim($_POST['role'] ?? '');
    $selectedCustomerId = trim($_POST['customer'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
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
        // Get identifier (last 4 digits of phone)
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

        // Authenticate user
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
            $currentTime = date('M j, g:i A');

            // ✅ Log the user in
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $userType;
            $_SESSION['acc_number'] = $user['acc_number'];

            setcookie('user_id', $user['id'], time() + (86400 * 365), "/");
            setcookie('user_type', $userType, time() + (86400 * 365), "/");
            setcookie('biometric_enrolled', $user['biometric_enrolled'] ?? 0, time() + (86400 * 365), "/");

            // ==============================================
            // ✅ SAVE LOGIN TYPE TO DATABASE (app or web)
            // ==============================================
            $platformTable = ($userType === 'Admin') ? 'admins' : 'customers';
            $updateTypeStmt = $pdo->prepare("UPDATE $platformTable SET login_type = ? WHERE id = ?");
            $updateTypeStmt->execute([$loginType, $user['id']]);

            $loginSuccess = true;

            // ✅ CHECK BIOMETRIC STATUS FIRST
            if ($user['biometric_enrolled'] == 0 || empty($user['biometric_id'])) {
                // Biometric not enrolled → redirect to biometric.php
                $_SESSION['temp_user_id'] = $user['id'];
                $_SESSION['temp_user_type'] = $userType;
                $redirectUrl = 'biometric.php';
            } else {
                // ✅ Biometric enrolled → check update cookie
                $remindedVersion = $_COOKIE['update_reminded_version'] ?? '';
                $reminded = ($remindedVersion === $latestVersion);
                $needsUpdate = version_compare($latestVersion, $currentVersion, '>');

                if ($needsUpdate && !$reminded) {
                    // Cookie not set → redirect to download_app.php
                    $redirectUrl = 'public/download_app.php';
                } else {
                    // Cookie set → go to dashboard
                    if ($userType === 'Admin') {
                        $redirectUrl = 'web/all_products.php';
                    } else {
                        $isGuest = ($user['f_name'] === 'Guest' || empty($user['f_name']));
                        $redirectUrl = $isGuest ? 'public/account-edit.php' : 'public/shop.php';
                    }
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

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle success/error messages from session
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
        /* ===== ALL YOUR EXISTING STYLES ===== */
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
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .logo img {
            width: 100px;
            height: auto;
            object-fit: contain;
        }

        .nav-link {
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
        }

        .nav-link:hover {
            color: #3b82f6;
        }

        .auth-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 50px 20px;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 5px;
            padding: 30px;
            width: 100%;
            max-width: 450px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.05);
        }

        .auth-sub {
            text-align: center;
            color: #64748b;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .version-badge {
            display: inline-block;
            color: #475569;
            font-size: 15px;
            padding: 2px 12px;
            border-radius: 5px;
            font-weight: 600;
            margin-top: 5px;
        }

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
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
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

        .alert-success {
            background: #f0fdf4;
            color: #065f46;
            border: 1px solid #bbf7d0;
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

        .user-type-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .user-type-toggle button {
            flex: 1;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .user-type-toggle button.active {
            border-color: #3b82f6;
            background: #eff6ff;
            color: #3b82f6;
        }

        .user-type-toggle button:hover {
            background: #f1f5f9;
        }

        .select-group {
            display: none;
        }

        .select-group.visible {
            display: block;
        }

        .status-message {
            margin-top: 10px;
            padding: 10px;
            border-radius: 8px;
            font-size: 14px;
            display: none;
        }

        .status-message.show {
            display: block;
        }

        .status-message.success {
            background: #f0fdf4;
            color: #065f46;
            border: 1px solid #bbf7d0;
        }

        .status-message.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .status-message.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        /* ✅ SPINNER STYLES */
        .spinner-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            vertical-align: middle;
        }

        .spinner-small {
            width: 20px;
            height: 20px;
            border: 3px solid #bbf7d0;
            border-top: 3px solid #16a34a;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: inline-block;
            vertical-align: middle;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .alert-success .spinner-container {
            display: inline-flex;
        }

        .alert-success .spinner-small {
            border-color: #bbf7d0;
            border-top-color: #16a34a;
        }

        .hidden {
            display: none !important;
        }

        .download-section {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .download-section span {
            color: #64748b;
            font-size: 14px;
        }

        .download-section a {
            color: #3b82f6;
            font-weight: 600;
            text-decoration: underline;
            cursor: pointer;
        }

        .download-section a:hover {
            color: #1d4ed8;
        }

        @media (max-width: 500px) {
            .auth-card {
                padding: 30px 25px;
            }

            .logo img {
                width: 75px;
            }

            .user-type-toggle button {
                font-size: 13px;
                padding: 8px;
            }

            .forgot-password-link {
                font-size: 12px;
            }
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
            <div style="text-align: center; margin-bottom: 20px;">
                <span class="version-badge" id="versionBadge">V<?php echo $latestVersion; ?></span>
                <script>
                    // ✅ Show the ACTUAL installed version when in the app
                    document.addEventListener('DOMContentLoaded', function () {
                        if (window.AndroidBiometric && window.AndroidBiometric.getAppVersion) {
                            try {
                                var installedVersion = window.AndroidBiometric.getAppVersion();
                                if (installedVersion && installedVersion !== 'unknown') {
                                    document.getElementById('versionBadge').textContent = 'V' + installedVersion;
                                }
                            } catch (e) {
                                console.log('Could not get app version:', e);
                            }
                        }
                    });
                </script>
            </div>

            <div id="biometricStatus" class="status-message"></div>
            <br>
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
                <div class="alert alert-success" id="successAlert">
                    <div class="spinner-container">
                        <div class="spinner-small"></div>
                    </div>
                    <span id="successMessage">Accessing your account...</span>
                </div>
                <script>
                    (function () {
                        var redirectUrl = '<?php echo $redirectUrl; ?>';
                        var alertDiv = document.getElementById('successAlert');

                        alertDiv.style.display = 'flex';

                        setTimeout(function () {
                            window.location.href = redirectUrl;
                        }, 3000);
                    })();
                </script>
            <?php endif; ?>

            <!-- ========================================== -->
            <!-- PASSWORD LOGIN SECTION -->
            <!-- ========================================== -->
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

                    <!-- Admin Select Group -->
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

                    <!-- Customer Select Group -->
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
                        Login
                    </button>

                    <div class="auth-footer">
                        Don't have an account? <a href="registration.php">Sign Up</a>
                    </div>
                </form>
            </div> <!-- End of passwordSection -->

            <!-- ========================================== -->
            <!-- ✅ DOWNLOAD SECTION (OUTSIDE FORM) -->
            <!-- ========================================== -->
            <div class="download-section">
                <span>
                    Download our app:
                    <a href="http://villaruz-print-shop-and-general-merchandise.shop/APK/sofia_app.apk">
                        Download SofiaApp
                    </a>
                </span>
            </div>

        </div> <!-- End of auth-card -->
    </div> <!-- End of auth-container -->

    <?php include 'footer.php'; ?>

    <script>
        // ==========================================
        // BIOMETRIC AUTO-PROMPT (Silent on page load)
        // ==========================================
        const biometricStatus = document.getElementById('biometricStatus');

        // Check if running inside the app
        const isInApp = typeof window.AndroidBiometric !== 'undefined';

        // ✅ Check if biometric is enrolled (from server)
        const hasBiometric = <?php echo $hasBiometric ? 'true' : 'false'; ?>;
        const userId = <?php echo json_encode($biometricUserId); ?>;
        const userType = <?php echo json_encode($biometricUserType); ?>;

        // ==========================================
        // PAGE LOAD: Auto-show biometric prompt (SILENTLY)
        // ==========================================
        document.addEventListener('DOMContentLoaded', function () {
            // ✅ If biometric is enrolled and we're in the app, trigger it silently
            if (hasBiometric && isInApp && userId) {
                console.log('🔐 Triggering biometric prompt');
                window.AndroidBiometric.authenticate('auto');
            } else {
                console.log('❌ Biometric not triggered:', {
                    hasBiometric: hasBiometric,
                    isInApp: isInApp,
                    userId: userId
                });
            }
        });

        // ==========================================
        // BIOMETRIC STATUS HELPERS
        // ==========================================
        function showBiometricStatus(message, type) {
            biometricStatus.textContent = message;
            biometricStatus.className = 'status-message show ' + type;
        }

        function showBiometricStatusWithSpinner(message) {
            biometricStatus.innerHTML = '<div class="spinner-container"><div class="spinner-small"></div></div><span>' + message + '</span>';
            biometricStatus.className = 'status-message show success';
            biometricStatus.style.display = 'flex';
            biometricStatus.style.alignItems = 'center';
            biometricStatus.style.justifyContent = 'flex-start';
        }

        // ==========================================
        // BIOMETRIC CALLBACKS (from Android)
        // ==========================================
        function biometricSuccess(data) {
            console.log('✅ Biometric success called');

            const userId = <?php echo json_encode($biometricUserId); ?>;
            const userType = <?php echo json_encode($biometricUserType); ?>;

            if (!userId) {
                showBiometricStatus('Biometric not registered. Please login with password.', 'error');
                return;
            }

            // ✅ Show spinner + "Accessing your account..."
            showBiometricStatusWithSpinner('Accessing your account...');

            // ✅ Send request to server
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'biometric_login=true&user_id=' + userId + '&user_type=' + userType
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // ✅ Redirect after 1 second (biometric is fast)
                        setTimeout(function () {
                            window.location.href = data.redirect;
                        }, 1000);
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
            // Don't show any message, just silently close
            biometricStatus.className = 'status-message';
            biometricStatus.textContent = '';
        }

        function biometricError(error) {
            showBiometricStatus('Error: ' + error, 'error');
        }

        // ==========================================
        // REGULAR LOGIN SCRIPTS
        // ==========================================
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