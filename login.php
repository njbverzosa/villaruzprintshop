<?php
// login.php – with auto biometric prompt + fallback to password

// Set session lifetime
$sessionLifetime = 604800;
ini_set('session.cookie_lifetime', $sessionLifetime);
ini_set('session.gc_maxlifetime', $sessionLifetime);

session_start();
require_once __DIR__ . '/DB_Conn/config.php';

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

        // ✅ Also set session to keep user logged in
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

    error_log('🔐 Biometric login API called - User ID: ' . $userId . ', Type: ' . $userType);

    if (!$userId || !$userType) {
        exit;
    }

    $table = ($userType === 'Admin') ? 'admins' : 'customers';
    $stmt = $pdo->prepare("SELECT id, biometric_enrolled, acc_number, f_name FROM $table WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        exit;
    }

    if ($user['biometric_enrolled'] != 1) {
        exit;
    }

    // ✅ Biometric is valid - log the user in
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $userType;
    $_SESSION['acc_number'] = $user['acc_number'];

    // Set cookie for auto-login
    setcookie('user_id', $user['id'], time() + (86400 * 365), "/");
    setcookie('user_type', $userType, time() + (86400 * 365), "/");

    // Determine redirect URL
    if ($userType === 'Admin') {
        $redirectUrl = 'web/all_products.php';
    } else {
        $isGuest = ($user['f_name'] === 'Guest' || empty($user['f_name']));
        $redirectUrl = $isGuest ? 'public/account-edit.php' : 'public/shop.php';
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
            $stmt = $pdo->prepare("SELECT id, password, acc_number, phone_number, f_name, role, status, email, authorize_access 
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
            $stmt = $pdo->prepare("SELECT id, password, acc_number, account, phone_number, f_name, 'Customer' as role, status, email 
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

            if ($userType === 'Admin') {
                session_regenerate_id(true);
                $_SESSION['user_role'] = 'Admin';
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['acc_number'] = $user['acc_number'];

                // ✅ Set cookie for auto-login
                setcookie('user_id', $user['id'], time() + (86400 * 365), "/");
                setcookie('user_type', 'Admin', time() + (86400 * 365), "/");
                setcookie('biometric_enrolled', $user['biometric_enrolled'] ?? 0, time() + (86400 * 365), "/");

                $loginSuccess = true;
                $redirectUrl = 'web/all_products.php';

            } elseif ($userType === 'Customer') {
                $updateStmt = $pdo->prepare("UPDATE customers SET online_time = ? WHERE id = ?");
                $updateStmt->execute([$currentTime, $user['id']]);
                session_regenerate_id(true);

                $_SESSION['user_role'] = 'Customer';
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['acc_number'] = $user['acc_number'];

                // ✅ Set cookie for auto-login
                setcookie('user_id', $user['id'], time() + (86400 * 365), "/");
                setcookie('user_type', 'Customer', time() + (86400 * 365), "/");
                setcookie('biometric_enrolled', $user['biometric_enrolled'] ?? 0, time() + (86400 * 365), "/");

                $loginSuccess = true;

                $isGuest = ($user['f_name'] === 'Guest' || empty($user['f_name']));

                if ($isGuest) {
                    $redirectUrl = 'public/account-edit.php';
                } else {
                    $redirectUrl = 'public/shop.php';
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

        .btn-biometric {
            width: 100%;
            background: linear-gradient(145deg, #22c55e, #16a34a);
            border: none;
            padding: 16px;
            border-radius: 5px;
            font-weight: 700;
            font-size: 16px;
            color: white;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-biometric:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }

        .btn-biometric i {
            margin-right: 10px;
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
            align-items: center;
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

        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            gap: 15px;
        }

        .divider hr {
            flex: 1;
            border: none;
            border-top: 2px solid #e2e8f0;
        }

        .divider span {
            color: #94a3b8;
            font-weight: 600;
            font-size: 14px;
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

        .biometric-loading {
            text-align: center;
            padding: 20px;
        }

        .biometric-loading .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top-color: #22c55e;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .hidden {
            display: none !important;
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
            <img src="logo/logo.jpeg" alt="Villaruz Print Shop Logo">
        </div>
        <div>
            <a href="index.php" class="nav-link">Home</a>
        </div>
    </nav>

    <div class="auth-container">
        <div class="auth-card">
            <p class="auth-sub">Log In your account</p>
            <div style="text-align: center; margin-bottom: 20px;">
                <span class="version-badge">V11.50.41</span>
            </div>

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
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Accessing your account...
                </div>
                <br>
                <script>
                    setTimeout(function () {
                        window.location.href = '<?php echo $redirectUrl; ?>';
                    }, 1500);
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
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>

                    <div class="divider">
                        <hr>
                        <span>— OR —</span>
                        <hr>
                    </div>

                    <!-- ========================================== -->
                    <!-- ✅ BIOMETRIC SECTION -->
                    <!-- ========================================== -->
                    <div id="biometricSection">
                        <div id="biometricLoading" class="biometric-loading">
                            <div class="spinner"></div>
                            <p style="color: #64748b;">Checking biometric...</p>
                        </div>

                        <div id="biometricContent" class="hidden">
                            <!-- Biometric Button -->
                            <button type="button" class="btn-biometric" id="biometricLoginBtn">
                                <i class="fas fa-fingerprint"></i> Login with Fingerprint/PIN/Pattern
                            </button>
                        </div>
                    </div>

                    <div class="auth-footer">
                        Don't have an account? <a href="registration.php">Sign Up</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // ==========================================
        // PAGE LOAD: Auto-show biometric prompt
        // ==========================================
        const biometricLoading = document.getElementById('biometricLoading');
        const biometricContent = document.getElementById('biometricContent');
        const passwordSection = document.getElementById('passwordSection');
        const biometricLoginBtn = document.getElementById('biometricLoginBtn');
        const biometricStatus = document.getElementById('biometricStatus');

        // Check if running inside the app
        const isInApp = typeof window.AndroidBiometric !== 'undefined';

        // ✅ Check if biometric is enrolled (from server)
        const hasBiometric = <?php echo $hasBiometric ? 'true' : 'false'; ?>;
        const userId = <?php echo json_encode($biometricUserId); ?>;
        const userType = <?php echo json_encode($biometricUserType); ?>;

        // Show/hide sections based on biometric status
        document.addEventListener('DOMContentLoaded', function () {
            // Hide loading after 1 second
            setTimeout(function () {
                biometricLoading.classList.add('hidden');
                biometricContent.classList.remove('hidden');

                if (hasBiometric && isInApp && userId) {
                    // showBiometricStatus('🔐 Please authenticate...', 'info');
                    window.AndroidBiometric.authenticate('auto');
                } else if (hasBiometric && !isInApp) {
                    showBiometricStatus('Use the app for biometric login', 'info');
                } else {
                    showBiometricStatus('Biometric not registered. Please login with password.', 'error');
                    passwordSection.classList.remove('hidden');
                }
            }, 1000);
        });

        // ==========================================
        // BIOMETRIC LOGIN BUTTON (Manual)
        // ==========================================
        biometricLoginBtn.addEventListener('click', function () {
            if (!isInApp) {
                showBiometricStatus('Biometric login is only available in the app', 'error');
                return;
            }

            if (!hasBiometric || !userId) {
                showBiometricStatus('Biometric not registered. Please login with password.', 'error');
                passwordSection.classList.remove('hidden');
                return;
            }

            // showBiometricStatus('🔐 Please authenticate...', 'info');
            window.AndroidBiometric.authenticate('manual');
        });

        function showBiometricStatus(message, type) {
            biometricStatus.textContent = message;
            biometricStatus.className = 'status-message show ' + type;
        }

        // ==========================================
        // BIOMETRIC CALLBACKS (from Android)
        // ==========================================

        // Called from Android when biometric succeeds
        function biometricSuccess(data) {
            // showBiometricStatus('✅ Authentication successful! Checking database...', 'info');

            const userId = <?php echo json_encode($biometricUserId); ?>;
            const userType = <?php echo json_encode($biometricUserType); ?>;

            if (!userId) {
                showBiometricStatus('Biometric not registered. Please login with password.', 'error');
                passwordSection.classList.remove('hidden');
                return;
            }

            // Send login request to server
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
                        showBiometricStatus('Accessing your account...', 'success');
                        setTimeout(function () {
                            window.location.href = data.redirect;
                        }, 500);
                    } else {
                        showBiometricStatus('' + data.message, 'error');
                        passwordSection.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    showBiometricStatus('Error: ' + error.message, 'error');
                    passwordSection.classList.remove('hidden');
                });
        }

        function biometricFailed() {
            showBiometricStatus('Authentication failed. Please try again.', 'error');
        }

        function biometricCancel() {
            // showBiometricStatus('⏹️ Authentication canceled.', 'info');
            passwordSection.classList.remove('hidden');
            setTimeout(() => {
                biometricStatus.className = 'status-message';
                biometricStatus.textContent = '';
            }, 3000);
        }

        function biometricError(error) {
            showBiometricStatus('Error: ' + error, 'error');
            passwordSection.classList.remove('hidden');
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

        <?php if ($loginSuccess): ?>
            document.addEventListener('DOMContentLoaded', function () {
                const loginBtn = document.getElementById('loginBtn');
                if (loginBtn) {
                    loginBtn.disabled = true;
                }
                setTimeout(function () {
                    window.location.href = '<?php echo $redirectUrl; ?>';
                }, 1500);
            });
        <?php endif; ?>
    </script>
</body>

</html>