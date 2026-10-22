<?php
// customers-registration.php – Customer registration (desktop + mobile)

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

    if (!preg_match('/^[0-9]{6}$/', $password)) {
        $errors[] = 'PIN must be exactly 6 digits.';
    }

    if ($password !== $confirm) {
        $errors[] = 'PINs do not match.';
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
    <title>Register | Villaruz Print Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

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
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
        }

        /* ============================================
           LEFT BRAND PANEL (desktop only)
           ============================================ */
        .brand-panel {
            display: none;
        }

        /* ============================================
           RIGHT FORM PANEL
           ============================================ */
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
            padding: 28px 22px 32px 22px;
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

        /* ========== LOGO ========== */
        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }

        .logo img {
            width: 130px;
            height: 130px;
            object-fit: contain;
            display: block;
            border-radius: 28px;
        }

        /* ========== PORTAL HEADER ========== */
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
            margin-bottom: 26px;
            padding-left: 17px;
            font-weight: 400;
        }

        /* ========== FORM ========== */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
            font-size: 14px;
        }

        .form-group label i {
            color: #1a2a6c;
            font-size: 14px;
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
            border-color: #1a2a6c;
            box-shadow: 0 0 0 4px rgba(26, 42, 108, 0.1);
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
            color: #1a2a6c;
        }

        /* ========== HINTS ========== */
        .phone-hint,
        .password-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
            padding: 5px 12px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #1a2a6c;
            display: flex;
            align-items: center;
            gap: 6px;
            line-height: 1.4;
        }

        .password-hint i {
            color: #1a2a6c;
            margin-right: 0;
        }

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


        /* ========== ACTION AREA ========== */
        .action-area {
            margin-top: 8px;
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
            -webkit-appearance: none;
            appearance: none;
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
            background: #eef2fb;
            color: #1a2a6c;
            border: 1px solid #c7d4f0;
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
            margin-top: 6px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        .auth-footer-mobile a {
            color: #1a2a6c;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.2s;
            margin-left: 4px;
        }

        .auth-footer-mobile a:hover {
            color: #3b6fd4;
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
            color: #1a2a6c;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.2s;
            margin-left: 4px;
        }

        .auth-footer-bottom a:hover {
            color: #3b6fd4;
            text-decoration: underline;
        }

        /* ============================================
           DESKTOP — SPLIT SCREEN
           ============================================ */
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

            .brand-panel::before {
                width: 400px;
                height: 400px;
                top: -120px;
                right: -120px;
            }

            .brand-panel::after {
                width: 300px;
                height: 300px;
                bottom: -100px;
                left: -80px;
            }

            .brand-logo {
                display: flex;
                align-items: center;
                gap: 14px;
                margin-bottom: 48px;
                z-index: 1;
            }

            .brand-logo-img {
                width: 56px;
                height: 56px;
                border-radius: 12px;
                object-fit: cover;
                background: #f5b301;
                padding: 4px;
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

            .logo {
                display: none;
            }

            /* logo lives in brand panel on desktop */

            .portal-header {
                margin-top: 8px;
            }

            h1 {
                font-size: 28px;
            }

            .portal-subtitle {
                margin-bottom: 32px;
            }

            .auth-footer-mobile {
                display: none;
            }
        }

        /* Tablet tweak (between mobile and desktop) */
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

            .auth-container {
                padding: 40px;
            }

            .logo {
                margin-bottom: 24px;
            }

            .logo img {
                width: 160px;
                height: 160px;
                border-radius: 32px;
            }
        }

        /* ========== SMALL PHONES ========== */
        @media (max-width: 380px) {
            .auth-container {
                padding: 22px 18px 26px 18px;
            }

            .logo {
                margin-bottom: 16px;
            }

            .logo img {
                width: 115px;
                height: 115px;
                border-radius: 26px;
            }

            h1 {
                font-size: 21px;
            }

            .portal-subtitle {
                font-size: 12px;
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
                padding: 20px 22px 24px 22px;
            }

            .logo {
                margin-bottom: 14px;
            }

            .logo img {
                width: 100px;
                height: 100px;
                border-radius: 22px;
            }

            h1 {
                font-size: 21px;
            }

            .portal-subtitle {
                font-size: 12px;
                margin-bottom: 20px;
            }

            .form-group {
                margin-bottom: 14px;
            }

            .form-group input {
                height: 50px;
            }

            .btn-primary {
                height: 50px;
            }
        }

        /* ========== MOBILE + SHORT SCREENS ========== */
        @media (max-width: 640px) and (max-height: 700px) {
            .auth-container {
                padding: 20px 22px 24px 22px;
            }

            .logo img {
                width: 100px;
                height: 100px;
                border-radius: 22px;
            }
        }
    </style>
</head>

<body>

    <!-- LEFT: Brand panel (desktop only) -->
    <section class="brand-panel">
        <div class="brand-logo">
            <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/logo.jpeg" alt="Villaruz Logo"
                class="brand-logo-img">
            <div class="brand-logo-text">VILLARUZ PRINT SHOP<br>&amp; GENERAL MERCHANDISE</div>
        </div>
        <h1>Create your account.</h1>
        <p>Join thousands of happy customers — order prints, track deliveries, and manage your account in one place.</p>
        <div class="motto">"Precision in every print, care in every order."</div>
    </section>

    <!-- RIGHT: Form panel -->
    <section class="form-panel">
        <div class="auth-container">
            <div class="auth-card">

                <!-- Logo (mobile only — hidden on desktop) -->
                <div class="logo">
                    <div class="logo-stack">
                        <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/logo.jpeg"
                            alt="Villaruz Print Shop Logo" class="main-logo">
                    </div>
                </div>

                <div class="portal-header">
                    <h1>Create your account</h1>
                </div>
                <p class="portal-subtitle">Register with your mobile number and a 6-digit PIN</p>

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
                            inputmode="numeric" pattern="[0-9]{11}"
                            value="<?php echo htmlspecialchars($formData['phone_number']); ?>" required>
                        <div class="phone-hint">
                            Enter 11-digit mobile number (e.g., 09123456789)
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> 6-Digit PIN</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" placeholder="Enter 6-digit PIN"
                                maxlength="6" inputmode="numeric" pattern="[0-9]{6}" required>
                            <i class="fas fa-eye-slash" id="togglePassword"></i>
                        </div>
                        <div class="password-hint">
                            Enter a 6-digit PIN (numbers only)
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-check-circle"></i> Confirm PIN</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password"
                                placeholder="Confirm your 6-digit PIN" maxlength="6" inputmode="numeric"
                                pattern="[0-9]{6}" required>
                            <i class="fas fa-eye-slash" id="toggleConfirmPassword"></i>
                        </div>
                    </div>

                    <div class="action-area">
                        <button type="submit" class="btn-primary" id="registerBtn">
                            <i class="fas fa-user-plus"></i> Register
                        </button>
                    </div>
                </form>

                <div class="register-footer">
                    Already have an account? <a href="customers-portal.php">Login here</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Desktop footer (hidden on mobile) -->
    <div class="auth-footer-bottom" style="display:none;"></div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        if (togglePassword && password) {
            togglePassword.addEventListener('click', function () {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPassword = document.getElementById('confirm_password');
        if (toggleConfirmPassword && confirmPassword) {
            toggleConfirmPassword.addEventListener('click', function () {
                const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPassword.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        const phoneInput = document.querySelector('input[name="phone_number"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
            });
        }

        document.querySelectorAll('input[name="password"], input[name="confirm_password"]').forEach(function (input) {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        });

        <?php if ($registrationSuccess): ?>
            document.addEventListener('DOMContentLoaded', function () {
                const registerBtn = document.getElementById('registerBtn');
                const alertMessage = document.getElementById('alertMessage');
                const alertElement = document.getElementById('successAlert');

                if (registerBtn) registerBtn.disabled = true;

                if (alertMessage) {
                    alertMessage.textContent = 'Creating your account. Please wait...';
                }

                setTimeout(function () {
                    if (alertElement) alertElement.style.animation = 'fadeOut 0.2s ease forwards';
                    setTimeout(function () {
                        window.location.href = 'customers-portal.php';
                    }, 500);
                }, 6000);
            });
        <?php endif; ?>
    </script>
</body>

</html>