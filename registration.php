<?php
// register.php – Customer registration (desktop + mobile)

require_once __DIR__ . '/DB_Conn/config.php';

// ============================================================
// 2. HELPER FUNCTIONS
// ============================================================
function generateAccNumberFromPhone(PDO $pdo, string $phone_number): string
{
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone_number);
    $last4Digits = substr($cleanPhone, -4);
    $baseAccNumber = $last4Digits;

    $stmt = $pdo->prepare("SELECT id FROM customers WHERE acc_number = ?");
    $stmt->execute([$baseAccNumber]);

    if (!$stmt->fetch()) {
        return $baseAccNumber;
    }

    $maxAttempts = 10;
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $newAccNumber = $baseAccNumber . mt_rand(10, 99);
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE acc_number = ?");
        $stmt->execute([$newAccNumber]);
        if (!$stmt->fetch()) {
            return $newAccNumber;
        }
    }

    return $baseAccNumber . time();
}

// ============================================================
// 3. SESSION MANAGEMENT
// ============================================================
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// 4. FORM PROCESSING
// ============================================================
$errors = [];
$success = '';
$formData = [
    'phone_number' => ''
];
$registrationSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $phone_number = trim($_POST['phone_number'] ?? '');

    $formData = [
        'phone_number' => $phone_number
    ];

    if (empty($phone_number)) {
        $errors[] = 'Phone number is required.';
    } else {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone_number);
        if (!preg_match('/^09[0-9]{9}$/', $cleanPhone)) {
            $errors[] = 'Please enter a valid 11-digit Philippine mobile number starting with 09';
        } else {
            $phone_number = $cleanPhone;
        }
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone_number = ?");
        $stmt->execute([$phone_number]);
        if ($stmt->fetch()) {
            $errors[] = 'Phone number already registered.';
        }
    }

    if (empty($errors)) {
        $accNumber = generateAccNumberFromPhone($pdo, $phone_number);

        date_default_timezone_set('Asia/Manila');
        $registrationDate = date('D, j M Y g:i A');
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO customers 
            (password, registered_at, acc_number, phone_number, text_pass) 
            VALUES (?, ?, ?, ?, ?)");

        try {
            $result = $stmt->execute([
                $hashedPassword,
                $registrationDate,
                $accNumber,
                $phone_number,
                $password
            ]);

            if ($result) {
                $_SESSION['verification_phone'] = $phone_number;
                $_SESSION['verification_acc_number'] = $accNumber;

                $registrationSuccess = true;
                $success = 'Creating your account for customer...';
            } else {
                $errors[] = 'Failed to create account. Please try again.';
                error_log("Registration failed for phone: $phone_number");
            }
        } catch (PDOException $e) {
            error_log("Registration insert error: " . $e->getMessage());
            $errors[] = 'Database error. Please try again later.';
        }
    }
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
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
    <title>Register - Villaruz Print Shop</title>
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
        }

        /* ========== APP SHELL ========== */
        .app-shell {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            background: #ffffff;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            border-radius: 10;
        }

        /* ========== CONTENT ========== */
        .auth-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 50px 20px;
            min-height: 0;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 30px;
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
        }

        /* ========== APP HEADER / LOGO ========== */
        .logo {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            margin-top: 0;
            margin-bottom: 16px;
            gap: 10px;
        }

        .logo img {
            width: 96px;
            height: 96px;
            object-fit: contain;
            display: block;
            border-radius: 24px;
        }

        /* ✅ UNIFIED version badge */
        .version-badge {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            padding: 3px 12px;
            letter-spacing: 0.02em;
        }

        /* ========== PORTAL HEADER ========== */
        .portal-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 6px;
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

        .portal-subtitle {
            text-align: left;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 24px;
            padding-left: 17px;
            font-weight: 400;
            letter-spacing: 0.01em;
        }

        /* ========== FORM ========== */
        .form-group {
            margin-bottom: 16px;
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

        /* ✅ UNIFIED input border-radius: 10px */
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

        /* ========== HINTS ========== */
        .phone-hint,
        .password-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
            padding: 4px 12px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #3b82f6;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .password-hint i {
            color: #3b82f6;
            margin-right: 0;
        }

        /* ========== ACTION AREA ========== */
        .action-area {
            margin-top: auto;
            padding-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* ✅ UNIFIED button border-radius: 10px */
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
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .alert .spinner-small {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #d1fae5;
            border-top: 3px solid #065f46;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 5px;
            flex-shrink: 0;
        }

        .alert .alert-message {
            flex: 1;
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

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(-10px);
            }
        }

        /* ========== FOOTERS ========== */
        .auth-footer-mobile {
            text-align: center;
            margin-top: 20px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        .auth-footer-mobile a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .auth-footer-mobile a:hover {
            color: #2563eb;
            text-decoration: underline;
        }

        .auth-footer-bottom {
            text-align: center;
            padding: 16px 24px calc(20px + env(safe-area-inset-bottom)) 24px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
            border-top: 1px solid #f1f5f9;
            background: #ffffff;
            display: none;
        }

        .auth-footer-bottom a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .auth-footer-bottom a:hover {
            color: #2563eb;
            text-decoration: underline;
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
                border-radius: 10px;
                box-shadow: 0 20px 45px rgba(0, 0, 0, 0.08);
                border: 1px solid #e2e8f0;
                overflow: hidden;
            }

            .auth-container {
                flex: none;
                padding: 40px 40px 20px 40px;
            }

            .auth-card {
                flex: none;
                padding: 0;
                border: none;
                box-shadow: none;
                max-width: 100%;
            }

            .portal-header {
                margin-top: 20px;
            }

            h1 {
                font-size: 28px;
            }

            .portal-header::before {
                height: 38px;
            }

            .portal-subtitle {
                font-size: 14px;
            }

            .action-area {
                margin-top: 8px;
                padding-top: 8px;
            }

            .logo {
                margin-top: 0;
            }

            .auth-footer-mobile {
                display: none;
            }

            .auth-footer-bottom {
                display: block;
                border-top: 1px solid #f1f5f9;
                padding: 16px 40px 24px 40px;
            }
        }

        /* ========== SMALL PHONES ========== */
        @media (max-width: 380px) {
            .auth-container {
                padding: 16px 18px 16px 18px;
            }

            .logo {
                margin-top: 8px;
                margin-bottom: 12px;
            }

            .logo img {
                width: 84px;
                height: 84px;
            }

            .portal-header {
                margin-top: 20px;
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
                padding: 16px 24px 16px 24px;
            }

            .logo {
                margin-top: 4px;
                margin-bottom: 12px;
            }

            .logo img {
                width: 76px;
                height: 76px;
            }

            .portal-header {
                margin-top: 14px;
            }

            h1 {
                font-size: 20px;
            }

            .portal-header::before {
                height: 28px;
            }

            .portal-subtitle {
                font-size: 12px;
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

            .auth-footer-mobile {
                margin-top: 12px;
                font-size: 13px;
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
                padding: 28px 22px 32px 22px;
                min-height: 100dvh;
                display: flex;
                flex-direction: column;
            }

            .logo {
                align-items: center;
                text-align: center;
                gap: 8px;
                margin-top: 8px;
                margin-bottom: 0;
            }

            .logo img {
                width: 84px;
                height: 84px;
                border-radius: 22px;
            }

            .portal-header {
                margin-top: 34px;
                margin-bottom: 6px;
            }

            h1 {
                font-size: 22px;
            }

            .portal-subtitle {
                font-size: 13px;
                margin-bottom: 4px;
            }

            .alert {
                padding: 14px 16px;
                border-radius: 12px;
                font-size: 14px;
                margin-top: 18px;
                margin-bottom: 0;
            }

            #registrationForm {
                display: flex;
                flex-direction: column;
                margin-top: 32px;
                padding-top: 0;
            }

            .form-group {
                margin-bottom: 22px;
            }

            .form-group:last-of-type {
                margin-bottom: 26px;
            }

            .form-group label {
                margin-bottom: 10px;
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

            .auth-footer-mobile {
                margin-top: 22px;
            }
        }

        /* ========== MOBILE + SHORT SCREENS ========== */
        @media (max-width: 640px) and (max-height: 700px) {
            .auth-card {
                padding: 20px 22px 24px 22px;
            }

            .logo img {
                width: 72px;
                height: 72px;
            }

            .portal-header {
                margin-top: 22px;
            }

            h1 {
                font-size: 20px;
            }

            #registrationForm {
                margin-top: 20px;
            }

            .form-group {
                margin-bottom: 16px;
            }

            .form-group:last-of-type {
                margin-bottom: 20px;
            }

            .form-group label {
                margin-bottom: 8px;
            }

            .form-group input {
                padding: 13px 16px;
                height: 50px;
            }

            .btn-primary {
                padding: 14px;
                height: 50px;
            }

            .auth-footer-mobile {
                margin-top: 16px;
            }
        }
    </style>
</head>

<body>
    <div class="app-shell">
        <div class="auth-container">
            <div class="auth-card">

                <div class="logo">
                    <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/ic_launcher.png"
                        alt="Villaruz Print Shop Logo">
                </div>

                <div class="portal-header">
                    <h1>Create your account</h1>
                </div>
                <br>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" id="successAlert">
                        <div class="spinner-small"></div>
                        <span class="alert-message" id="alertMessage"><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" autocomplete="off" id="registrationForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" name="phone_number" placeholder="Enter your phone number" maxlength="11"
                            value="<?php echo htmlspecialchars($formData['phone_number']); ?>" required>
                        <div class="phone-hint">
                            Enter 11-digit mobile number (e.g., 09123456789)
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" placeholder="Enter your password"
                                required>
                            <i class="fas fa-eye-slash" id="togglePassword"></i>
                        </div>
                        <div class="password-hint">
                            Min 8 chars, include uppercase, number, and special character
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-check-circle"></i> Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password"
                                placeholder="Confirm your password" required>
                            <i class="fas fa-eye-slash" id="toggleConfirmPassword"></i>
                        </div>
                    </div>

                    <div class="action-area">
                        <button type="submit" class="btn-primary" id="registerBtn">
                            <i class="fas fa-user-plus"></i> Register
                        </button>

                        <div class="auth-footer-mobile">
                            Already have an account? <a href="customers-portal.php">Login</a>
                        </div>
                    </div>
                </form>

            </div>
        </div>

        <div class="auth-footer-bottom">
            Already have an account? <a href="customers-portal.php">Login</a>
        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPassword = document.getElementById('confirm_password');
        toggleConfirmPassword.addEventListener('click', function () {
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        document.querySelector('input[name="phone_number"]').addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
        });

        <?php if ($registrationSuccess): ?>
            document.addEventListener('DOMContentLoaded', function () {
                const registerBtn = document.getElementById('registerBtn');
                const alertMessage = document.getElementById('alertMessage');
                const alertElement = document.getElementById('successAlert');

                if (registerBtn) registerBtn.disabled = true;

                alertMessage.textContent = 'Creating your account. Please wait...';

                setTimeout(function () {
                    alertElement.style.animation = 'fadeOut 0.2s ease forwards';
                    setTimeout(function () {
                        window.location.href = 'login.php';
                    }, 500);
                }, 6000);
            });
        <?php endif; ?>
    </script>
</body>

</html>