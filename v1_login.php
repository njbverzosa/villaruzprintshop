<?php
// login.php – for Villaruz Print Shop

session_start();
require_once __DIR__ . '/DB_Conn/config.php';
require_once __DIR__ . '/Mail/PHPMailerAutoload.php';
require_once __DIR__ . '/login_notif.php';


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
$userTypeSelected = isset($_POST['user_type']) ? $_POST['user_type'] : 'Admin';

// Developer email for notifications (ONLY for customer logins)
$toDev = 'villaruzprintshop@gmail.com';

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
    $selectedCustomer = trim($_POST['customer'] ?? '');
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
        if (empty($selectedCustomer)) {
            $errors[] = 'Please select a customer account.';
        } else {
            // Get customer details
            $customerData = explode('|', $selectedCustomer);
            $customerId = $customerData[0];
            $customerPhone = $customerData[1];
            $identifier = substr(preg_replace('/[^0-9]/', '', $customerPhone), -4);
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
            $customerStmt->execute([$customerId, $identifier]);
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
                
                header('Location: web/all_products.php');
                exit;
                
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
                
                // Send notification ONLY for customer logins
                sendCustomerLoginNotification(
                    $toDev, 
                    $user['f_name'], 
                    $user['email'] ?? 'No email', 
                    $user['acc_number'], 
                    $user['phone_number'], 
                    $lastLoginDate
                );
                
                header('Location: public/shop.php');
                exit;
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
            border-radius: 28px;
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
            font-size: 14px;
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
            cursor: pointer;
        }

        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23475569'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 20px;
        }

        .form-group select:focus,
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

        /* Radio Group Styles */
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
            margin-bottom: 0;
        }

        .radio-group input[type="radio"] {
            width: auto;
            cursor: pointer;
            padding: 0;
            margin: 0;
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
            <h2 class="auth-title">Welcome <span>Back</span></h2>
            <p class="auth-sub">Login to your account</p>

            <!-- Display error message from session (for unauthorized access) -->
            <?php if ($loginErrorMessage): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($loginErrorMessage); ?>
                </div>
            <?php endif; ?>

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

            <form method="POST" action="" autocomplete="off" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="identifier" id="identifier" value="">

                <!-- User Type Selection -->
                <div class="form-group">
                    <label><i class="fas fa-users"></i> Account Type</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="user_type" value="Admin" class="user-type-radio" <?php echo ($userTypeSelected === 'Admin' || $userTypeSelected === '') ? 'checked' : ''; ?>>
                            <i class="fas fa-user-tie"></i> Admin
                        </label>
                        <label>
                            <input type="radio" name="user_type" value="Customer" class="user-type-radio" <?php echo $userTypeSelected === 'Customer' ? 'checked' : ''; ?>>
                            <i class="fas fa-user"></i> Customer
                        </label>
                    </div>
                </div>

                <!-- Admin Role Selection (hidden by default for Customer) -->
                <div id="adminSection" class="form-group" style="display: <?php echo ($userTypeSelected === 'Customer') ? 'none' : 'block'; ?>">
                    <label><i class="fas fa-user-tie"></i> Select Admin Account</label>
                    <select name="role" id="roleSelect">
                        <option value="">-- Select Admin Account --</option>
                        <?php foreach ($existingAdmins as $admin): ?>
                            <option value="<?php echo htmlspecialchars($admin['id']); ?>" 
                                    data-role="<?php echo htmlspecialchars($admin['role']); ?>"
                                    data-phone="<?php echo htmlspecialchars(substr(preg_replace('/[^0-9]/', '', $admin['phone_number']), -4)); ?>"
                                    data-name="<?php echo htmlspecialchars($admin['f_name']); ?>"
                                    data-acc="<?php echo htmlspecialchars($admin['acc_number']); ?>"
                                    <?php echo $selectedRole == $admin['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($admin['f_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="login-hint">
                        <i class="fas fa-info-circle"></i> Select your admin account
                    </div>
                </div>

                <!-- Customer Selection (hidden by default for Admin) -->
                <div id="customerSection" class="form-group" style="display: <?php echo $userTypeSelected === 'Customer' ? 'block' : 'none'; ?>">
                    <label><i class="fas fa-user"></i> Select Customer Account</label>
                    <select name="customer" id="customerSelect">
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($existingCustomers as $customer): ?>
                            <?php 
                                $last4Digits = substr(preg_replace('/[^0-9]/', '', $customer['phone_number']), -4);
                                $customerValue = $customer['id'] . '|' . $customer['phone_number'];
                            ?>
                            <option value="<?php echo htmlspecialchars($customerValue); ?>" 
                                    data-phone="<?php echo $last4Digits; ?>"
                                    data-name="<?php echo htmlspecialchars($customer['f_name']); ?>"
                                    data-acc="<?php echo htmlspecialchars($customer['acc_number']); ?>"
                                    <?php echo (isset($_POST['customer']) && $_POST['customer'] === $customerValue) ? 'selected' : ''; ?>>
                                👤 <?php echo htmlspecialchars($customer['f_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="login-hint">
                        <i class="fas fa-info-circle"></i> Select your customer account
                    </div>
                </div>

                <!-- Password Field -->
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Enter your password"
                            autocomplete="off" required>
                        <i class="fas fa-eye-slash" id="togglePassword"></i>
                    </div>
                    <div class="login-hint">
                        <i class="fas fa-key"></i> Enter the password for the selected account
                    </div>
                </div>

                <button type="submit" class="btn-primary">Login</button>

                <div class="auth-footer">
                    Don't have an account? <a href="registration.php">Register here</a>
                </div>
                <div class="auth-footer">
                    Forgot Password? <a href="forgot_password.php">Continue here</a>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <div class="copyright">
            <p>© 2026 Villaruz Print Shop & General Merchandise. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Toggle password visibility
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

        // Get elements
        const adminSection = document.getElementById('adminSection');
        const customerSection = document.getElementById('customerSection');
        const roleSelect = document.getElementById('roleSelect');
        const customerSelect = document.getElementById('customerSelect');
        const identifierInput = document.getElementById('identifier');
        const userTypeRadios = document.querySelectorAll('.user-type-radio');

        // Function to update identifier for admin
        function updateRoleHint() {
            if (roleSelect && roleSelect.value) {
                const selectedOption = roleSelect.options[roleSelect.selectedIndex];
                const phoneLast4 = selectedOption.getAttribute('data-phone');
                if (identifierInput) {
                    identifierInput.value = phoneLast4;
                }
                password.placeholder = 'Enter password for ' + selectedOption.getAttribute('data-name');
            } else {
                if (identifierInput) identifierInput.value = '';
                password.placeholder = 'Enter your password';
            }
        }

        // Function to update identifier for customer
        function updateCustomerHint() {
            if (customerSelect && customerSelect.value) {
                const selectedOption = customerSelect.options[customerSelect.selectedIndex];
                const phoneLast4 = selectedOption.getAttribute('data-phone');
                if (identifierInput) {
                    identifierInput.value = phoneLast4;
                }
                password.placeholder = 'Enter password for ' + selectedOption.getAttribute('data-name');
            } else {
                if (identifierInput) identifierInput.value = '';
                password.placeholder = 'Enter your password';
            }
        }

        // Function to toggle between Admin and Customer sections
        function toggleUserType() {
            let selectedType = 'Admin';
            for (const radio of userTypeRadios) {
                if (radio.checked) {
                    selectedType = radio.value;
                    break;
                }
            }
            
            if (selectedType === 'Customer') {
                if (adminSection) adminSection.style.display = 'none';
                if (customerSection) customerSection.style.display = 'block';
                if (roleSelect) roleSelect.required = false;
                if (customerSelect) customerSelect.required = true;
                
                // Trigger customer select change if a value is selected
                if (customerSelect && customerSelect.value) {
                    updateCustomerHint();
                } else {
                    if (identifierInput) identifierInput.value = '';
                    password.placeholder = 'Enter your password';
                }
            } else {
                if (adminSection) adminSection.style.display = 'block';
                if (customerSection) customerSection.style.display = 'none';
                if (roleSelect) roleSelect.required = true;
                if (customerSelect) customerSelect.required = false;
                
                // Trigger role select change if a value is selected
                if (roleSelect && roleSelect.value) {
                    updateRoleHint();
                } else {
                    if (identifierInput) identifierInput.value = '';
                    password.placeholder = 'Enter your password';
                }
            }
        }

        // Add event listeners
        if (userTypeRadios) {
            userTypeRadios.forEach(radio => {
                radio.addEventListener('change', toggleUserType);
            });
        }

        if (roleSelect) {
            roleSelect.addEventListener('change', updateRoleHint);
        }

        if (customerSelect) {
            customerSelect.addEventListener('change', updateCustomerHint);
        }

        // Initial setup
        toggleUserType();
        if (roleSelect && roleSelect.value) {
            updateRoleHint();
        }
        if (customerSelect && customerSelect.value) {
            updateCustomerHint();
        }
    </script>
</body>

</html>