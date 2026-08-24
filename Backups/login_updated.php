<?php
// login.php – for Villaruz Print Shop

session_start();
require_once __DIR__ . '/DB_Conn/config.php';
// require_once __DIR__ . '/Mail/PHPMailerAutoload.php';
// require_once __DIR__ . '/login_notif.php';

$logoutMessage = '';
if (isset($_SESSION['logout_message'])) {
    $logoutMessage = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}

$successMessage = '';
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Display error message from session (for unauthorized access)
$loginErrorMessage = '';
if (isset($_SESSION['login_error'])) {
    $loginErrorMessage = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// If already logged in as Admin, log them out
if (isset($_SESSION['acc_number'])) {
    $stmt = $pdo->prepare("UPDATE admins SET status = 0 WHERE acc_number = ?");
    $stmt->execute([$_SESSION['acc_number']]);
    session_unset();
    session_destroy();
    session_start();
}

// If already logged in as Customer, log them out
if (isset($_SESSION['customer_id'])) {
    $stmt = $pdo->prepare("UPDATE customers SET status = 0 WHERE id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    session_unset();
    session_destroy();
    session_start();
}

$errors = [];
$identifier = '';
$selectedRole = '';
$selectedCustomerId = '';
$userTypeSelected = isset($_POST['user_type']) ? $_POST['user_type'] : 'Admin';
$loginSuccess = false; // Flag to show success message
$redirectUrl = '';

// // Developer email for notifications (ONLY for customer logins)
// $toDev = 'njbverzosa@gmail.com';

// Fetch all admin accounts with role 'Admin' only
$adminStmt = $pdo->query("SELECT * FROM admins ORDER BY f_name");
$existingAdmins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all customer accounts
$customerStmt = $pdo->query("SELECT id, f_name, acc_number, phone_number, email FROM customers ORDER BY f_name");
$existingCustomers = $customerStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    $userTypeSelected = trim($_POST['user_type'] ?? 'Admin');
    $selectedRole = trim($_POST['role'] ?? '');
    $selectedCustomerId = trim($_POST['customer'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($userTypeSelected)) {
        $errors[] = 'Please select user type.';
    }
    if (empty($password)) {
        $errors[] = 'Password cannot be empty.';
    }

    if ($userTypeSelected === 'Admin') {
        if (empty($selectedRole)) {
            $errors[] = 'Please select an admin account.';
        } else {
            // Get admin details to get phone number
            $adminInfoStmt = $pdo->prepare("SELECT phone_number FROM admins WHERE id = ?");
            $adminInfoStmt->execute([$selectedRole]);
            $adminInfo = $adminInfoStmt->fetch();
            if ($adminInfo) {
                $identifier = substr(preg_replace('/[^0-9]/', '', $adminInfo['phone_number']), -4);
            }
        }
    } elseif ($userTypeSelected === 'Customer') {
        if (empty($selectedCustomerId)) {
            $errors[] = 'Please select a customer account.';
        } else {
            // Get customer details
            $customerInfoStmt = $pdo->prepare("SELECT phone_number FROM customers WHERE id = ?");
            $customerInfoStmt->execute([$selectedCustomerId]);
            $customerInfo = $customerInfoStmt->fetch();
            if ($customerInfo) {
                $identifier = substr(preg_replace('/[^0-9]/', '', $customerInfo['phone_number']), -4);
            }
        }
    }

    if (empty($errors)) {
        $userType = null;
        $user = null;

        if ($userTypeSelected === 'Admin') {
            // Search in ADMINS table by ID and phone number
            $adminStmt = $pdo->prepare("SELECT id, password, acc_number, phone_number, f_name, role, status, email, authorize_access 
                                       FROM admins WHERE id = ? AND RIGHT(phone_number, 4) = ?");
            $adminStmt->execute([$selectedRole, $identifier]);
            $admin = $adminStmt->fetch();

            if ($admin) {
                if (password_verify($password, $admin['password'])) {
                    $userType = 'Admin';
                    $user = $admin;
                } else {
                    $errors[] = 'Invalid password for admin account.';
                }
            } else {
                $errors[] = 'No admin account found. Please contact administrator.';
            }
        } elseif ($userTypeSelected === 'Customer') {
            // Search in CUSTOMERS table
            $customerStmt = $pdo->prepare("SELECT id, password, acc_number, phone_number, f_name, 'Customer' as role, status, email 
                                          FROM customers WHERE id = ? AND RIGHT(phone_number, 4) = ?");
            $customerStmt->execute([$selectedCustomerId, $identifier]);
            $customer = $customerStmt->fetch();

            if ($customer) {
                if (password_verify($password, $customer['password'])) {
                    $userType = 'Customer';
                    $user = $customer;
                } else {
                    $errors[] = 'Invalid password for customer account.';
                }
            } else {
                $errors[] = 'Customer account not found.';
            }
        }

        // Process based on user type
        if ($userType && $user) {
            date_default_timezone_set('Asia/Manila');
            $lastLoginDate = date('D, j M Y g:i A');

            if ($userType === 'Admin') {
                $updateStmt = $pdo->prepare("UPDATE admins SET last_login_date = ?, status = 1 WHERE id = ?");
                $updateStmt->execute([$lastLoginDate, $user['id']]);
                session_regenerate_id(true);

                $_SESSION['acc_number'] = $user['acc_number'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['phone_number'] = $user['phone_number'];
                $_SESSION['fullname'] = $user['f_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['authorize_access'] = $user['authorize_access'];
                $_SESSION['user_type'] = 'Admin';
                $_SESSION['user_email'] = $user['email'] ?? 'No email';

                // ✅ ADDED: Set login success flag and redirect URL for Admin
                $loginSuccess = true;
                $redirectUrl = 'web/all_products.php';  // No .php (clean URL)
                $successMessage = 'Accessing your account..';

                // Note: We don't redirect immediately - the success message will show first
                // The JavaScript will handle the redirect after 5 seconds
                
                // If you want immediate redirect without success message, uncomment:
                // header('Location: web/all_products');
                // exit;
                
            } elseif ($userType === 'Customer') {
                $updateStmt = $pdo->prepare("UPDATE customers SET last_login_date = ?, status = 1 WHERE id = ?");
                $updateStmt->execute([$lastLoginDate, $user['id']]);
                session_regenerate_id(true);

                $_SESSION['customer_id'] = $user['id'];
                $_SESSION['customer_acc_number'] = $user['acc_number'];
                $_SESSION['customer_phone'] = $user['phone_number'];
                $_SESSION['customer_name'] = $user['f_name'];
                $_SESSION['customer_role'] = 'Customer';
                $_SESSION['user_type'] = 'Customer';
                $_SESSION['user_email'] = $user['email'] ?? 'No email';

                // Check if f_name is null, empty, or "Guest" - redirect to account page to complete profile
                if (empty($user['f_name']) || is_null($user['f_name']) || $user['f_name'] === 'Guest') {
                    header('Location: public/account');
                    exit;
                }

                // Set login success flag and redirect URL for Customer
                $loginSuccess = true;
                $redirectUrl = 'public/shop.php';  // No .php (clean URL)
                $successMessage = 'Accessing your account..';
            }
        }
    }
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
            position: sticky;
            top: 0;
            z-index: 100;
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

        .auth-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 10px;
            text-align: center;
            color: #0f172a;
        }

        .auth-title span {
            background: linear-gradient(145deg, #3b82f6, #8b5cf6);
            --webkit-background-clip: text;
            --webkit-text-fill-color: transparent;
        }

        .auth-sub {
            text-align: center;
            color: #64748b;
            margin-bottom: 30px;
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
            border-radius: 40px;
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

        /* ==============================================
           SMALL SPINNER STYLES
           ============================================== */
        .spinner-container {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .spinner-small {
            width: 20px;
            height: 20px;
            border: 3px solid #bbf7d0;
            border-top: 3px solid #16a34a;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        /* Alert with spinner override */
        .alert-success .spinner-small {
            border-color: #bbf7d0;
            border-top-color: #16a34a;
        }

        .alert-success span {
            font-size: 15px;
            font-weight: 600;
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

            .spinner-small {
                width: 16px;
                height: 16px;
                border-width: 2.5px;
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
                <span class="version-badge">V5.30.41</span>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($logoutMessage): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($logoutMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($loginSuccess && $successMessage): ?>
                <div class="alert alert-success" id="successAlert">
                    <div class="spinner-container">
                        <div class="spinner-small"></div>
                    </div>
                    <span><?php echo htmlspecialchars($successMessage); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($loginErrorMessage): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($loginErrorMessage); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="user-type-toggle">
                    <button type="button" class="<?php echo $userTypeSelected === 'Admin' ? 'active' : ''; ?>" onclick="switchUserType('Admin')">
                        <i class="fas fa-user-tie"></i> Admin
                    </button>
                    <button type="button" class="<?php echo $userTypeSelected === 'Customer' ? 'active' : ''; ?>" onclick="switchUserType('Customer')">
                        <i class="fas fa-user"></i> Customer
                    </button>
                </div>

                <input type="hidden" name="user_type" id="userTypeInput" value="<?php echo $userTypeSelected; ?>">

                <!-- Admin Select Group -->
                <div class="form-group select-group <?php echo $userTypeSelected === 'Admin' ? 'visible' : ''; ?>" id="adminSelectGroup">
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
                <div class="form-group select-group <?php echo $userTypeSelected === 'Customer' ? 'visible' : ''; ?>" id="customerSelectGroup">
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
                        <input type="password" name="password" id="password" placeholder="Enter your password" required>
                        <i class="fas fa-eye-slash" id="togglePassword"></i>
                    </div>
                    <!-- Forgot Password link at the end of password input -->
                    <div class="forgot-password-link">
                        <a href="forgot_password.php"><i class="fas fa-key"></i> Forgot password?</a>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="loginBtn" <?php echo $loginSuccess ? 'disabled' : ''; ?>>
                    <i class="fas fa-sign-in-alt"></i> Log In
                </button>

                <div class="auth-footer">
                    Don't have an account? <a href="registration.php">Sign Up</a>
                </div>

            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // ==============================================
        // PASSWORD VISIBILITY TOGGLE
        // ==============================================
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // ==============================================
        // USER TYPE SWITCH
        // ==============================================
        function switchUserType(type) {
            document.getElementById('userTypeInput').value = type;

            // Update button styles
            const buttons = document.querySelectorAll('.user-type-toggle button');
            buttons.forEach(btn => btn.classList.remove('active'));

            // Hide all select groups
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

        // ==============================================
        // SHOW SPINNER FOR 5 SECONDS THEN REDIRECT
        // ==============================================
        <?php if ($loginSuccess): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const loginBtn = document.getElementById('loginBtn');
            
            // Disable login button
            if (loginBtn) {
                loginBtn.disabled = true;
            }
            
            // After 5 seconds, redirect to shop
            setTimeout(function() {
                window.location.href = '<?php echo $redirectUrl; ?>';
            }, 1000);
        });
        <?php endif; ?>

        // ==============================================
        // PREVENT FORM SUBMISSION IF SUCCESS
        // ==============================================
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            <?php if ($loginSuccess): ?>
                e.preventDefault();
                return false;
            <?php endif; ?>
        });
    </script>
</body>

</html>