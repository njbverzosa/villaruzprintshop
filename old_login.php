<?php
// login.php – for Villaruz Print Shop

session_start();
require_once __DIR__ . '/DB_Conn/config.php';

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
$userData = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    $userData = trim($_POST['user_data'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($userData))
        $errors[] = 'Email or phone number is required.';
    if (empty($password))
        $errors[] = 'Password cannot be empty.';

    if (empty($errors)) {
        $userType = null;
        $user = null;

        // Check if user_data is an email
        $isEmail = filter_var($userData, FILTER_VALIDATE_EMAIL);

        if ($isEmail) {
            // Search by email in ADMINS table
            $adminStmt = $pdo->prepare("SELECT id, password, acc_number, phone_number, f_name, role, status, email 
                                       FROM admins WHERE email = ?");
            $adminStmt->execute([$userData]);
            $admin = $adminStmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $userType = 'Admin';
                $user = $admin;
            }

            // If not found in admins, check CUSTOMERS table by email
            if (!$userType) {
                $customerStmt = $pdo->prepare("SELECT id, password, acc_number, phone_number, f_name, role, status, email 
                                              FROM customers WHERE email = ?");
                $customerStmt->execute([$userData]);
                $customer = $customerStmt->fetch();

                if ($customer && password_verify($password, $customer['password'])) {
                    $userType = 'Customer';
                    $user = $customer;
                }
            }
        } else {
            // Search by phone number - check if it's a full phone number or last 4 digits
            // Remove any non-numeric characters
            $cleanPhone = preg_replace('/[^0-9]/', '', $userData);
            
            // Check if it's a valid Philippine mobile number (11 digits starting with 09)
            if (preg_match('/^09[0-9]{9}$/', $cleanPhone)) {
                // Full phone number provided - search by full phone number
                // Search in ADMINS table by phone number
                $adminStmt = $pdo->prepare("SELECT id, password, acc_number, phone_number, f_name, role, status, email 
                                           FROM admins WHERE phone_number = ?");
                $adminStmt->execute([$cleanPhone]);
                $admin = $adminStmt->fetch();

                if ($admin && password_verify($password, $admin['password'])) {
                    $userType = 'Admin';
                    $user = $admin;
                }

                // If not found in admins, check CUSTOMERS table by phone number
                if (!$userType) {
                    $customerStmt = $pdo->prepare("SELECT id, password, acc_number, phone_number, f_name, role, status, email 
                                                  FROM customers WHERE phone_number = ?");
                    $customerStmt->execute([$cleanPhone]);
                    $customer = $customerStmt->fetch();

                    if ($customer && password_verify($password, $customer['password'])) {
                        $userType = 'Customer';
                        $user = $customer;
                    }
                }
            } else {
                // Try as last 4 digits of phone number
                $last4Digits = substr($cleanPhone, -4);
                
                if (strlen($last4Digits) == 4) {
                    // Search in ADMINS table by phone (last 4 digits)
                    $adminStmt = $pdo->prepare("SELECT id, password, acc_number, phone_number, f_name, role, status, email 
                                               FROM admins WHERE RIGHT(phone_number, 4) = ?");
                    $adminStmt->execute([$last4Digits]);
                    $admin = $adminStmt->fetch();

                    if ($admin && password_verify($password, $admin['password'])) {
                        $userType = 'Admin';
                        $user = $admin;
                    }

                    // If not found in admins, check CUSTOMERS table by phone (last 4 digits)
                    if (!$userType) {
                        $customerStmt = $pdo->prepare("SELECT id, password, acc_number, phone_number, f_name, role, status, email 
                                                      FROM customers WHERE RIGHT(phone_number, 4) = ?");
                        $customerStmt->execute([$last4Digits]);
                        $customer = $customerStmt->fetch();

                        if ($customer && password_verify($password, $customer['password'])) {
                            $userType = 'Customer';
                            $user = $customer;
                        }
                    }
                } else {
                    $errors[] = 'Please enter a valid email, full phone number (e.g., 09455374819), or the last 4 digits of your phone (e.g., 4819)';
                }
            }
        }

        // Process based on user type and role
        if ($userType && $user && empty($errors)) {
            date_default_timezone_set('Asia/Manila');
            $lastLoginDate = date('D, j M Y g:i A');
            $userRole = $user['role'];

            // Process Admin users (System, CEO, Admin)
            if ($userType === 'Admin') {
                $updateStmt = $pdo->prepare("UPDATE admins SET last_login_date = ?, status = 1 WHERE id = ?");
                $updateStmt->execute([$lastLoginDate, $user['id']]);
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['acc_number'] = $user['acc_number'];
                $_SESSION['phone_number'] = $user['phone_number'];
                $_SESSION['fullname'] = $user['f_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_type'] = 'Admin';
                $_SESSION['email'] = $user['email'];

                // Redirect based on role
                if ($userRole === 'System') {
                    header('Location: web/all_products.php');
                } elseif ($userRole === 'CEO') {
                    header('Location: web/all_products.php');
                } elseif ($userRole === 'Admin') {
                    header('Location: web/all_products.php');
                } else {
                    header('Location: web/all_products.php');
                }
                exit;

            }
            // Process Customer users
            elseif ($userType === 'Customer') {
                $updateStmt = $pdo->prepare("UPDATE customers SET last_login_date = ?, status = 1 WHERE id = ?");
                $updateStmt->execute([$lastLoginDate, $user['id']]);
                session_regenerate_id(true);

                $_SESSION['customer_id'] = $user['id'];
                $_SESSION['customer_acc_number'] = $user['acc_number'];
                $_SESSION['customer_phone'] = $user['phone_number'];
                $_SESSION['customer_name'] = $user['f_name'];
                $_SESSION['customer_role'] = $user['role'];
                $_SESSION['user_type'] = 'Customer';
                $_SESSION['customer_email'] = $user['email'];

                header('Location: public/shop.php');
                exit;
            }

        } elseif (empty($errors)) {
            $errors[] = 'Invalid credentials. Please check your email/phone and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Villaruz Print Shop</title>
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
            padding: 40px;
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
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .auth-sub {
            text-align: center;
            color: #64748b;
            margin-bottom: 30px;
            font-size: 25px;
        }

        .version-badge {
            display: inline-block;
            color: #475569;
            font-size: 11px;
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

        .form-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
            transition: 0.3s;
        }

        .auth-footer a:hover {
            color: #2563eb;
            text-decoration: underline;
        }

        footer {
            background: #ffffff;
            padding: 20px 5%;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .login-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }

        .login-hint i {
            color: #3b82f6;
            margin-right: 5px;
        }

        .input-group {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            transition: 0.3s;
            overflow: hidden;
        }

        .input-group:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: #ffffff;
        }

        .input-group .input-icon {
            padding: 14px 0 14px 16px;
            color: #94a3b8;
            font-size: 16px;
            min-width: 44px;
        }

        .input-group input {
            border: none !important;
            background: transparent !important;
            padding: 14px 16px 14px 8px !important;
            flex: 1;
            outline: none;
            font-size: 15px;
        }

        .input-group input:focus {
            box-shadow: none !important;
        }

        .input-group .input-icon i {
            font-size: 18px;
        }

        .login-tabs {
            display: flex;
            background: #f1f5f9;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 25px;
        }

        .login-tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: #64748b;
            transition: 0.3s;
            border: none;
            background: transparent;
        }

        .login-tab.active {
            background: #ffffff;
            color: #3b82f6;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .login-tab:hover:not(.active) {
            color: #1e293b;
        }

        @media (max-width: 500px) {
            .auth-card {
                padding: 30px 25px;
            }

            .auth-title {
                font-size: 28px;
            }

            .logo img {
                width: 75px;
            }

            .login-tab {
                font-size: 12px;
                padding: 8px;
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
            <p class="auth-sub">Login to your account</p>
            <div style="text-align: center; margin-bottom: 20px;">
                <span class="version-badge">V4.11.24</span>
            </div>

            <?php if ($logoutMessage): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($logoutMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="form-group">
                    <label>Email or Phone Number</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="text" name="user_data" 
                               placeholder="Enter email or phone number (e.g., njbverzosa@gmail.com or 09455374819)"
                               value="<?php echo htmlspecialchars($userData); ?>" required>
                    </div>
                    <div class="login-hint">
                        <i class="fas fa-info-circle"></i>
                        <span>You can login with your registered email, full phone number (e.g., 09455374819), or the last 4 digits of your phone</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Enter your password"
                            autocomplete="current-password" required>
                        <i class="fas fa-eye-slash" id="togglePassword"></i>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i> Login
                </button>

                <div class="auth-footer">
                    Don't have an account? <a href="registration.php">Register here</a>
                </div>
                <div class="auth-footer">
                    Forgot Password? <a href="change_password.php">Reset here</a>
                </div>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Toggle password visibility - ONLY JavaScript remaining
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');

        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>

</html>