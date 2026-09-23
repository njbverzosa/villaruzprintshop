<?php
// change_password.php – Reset Password with OTP Code

session_start();
require_once __DIR__ . '/DB_Conn/config.php';
require_once __DIR__ . '/Mail/PHPMailerAutoload.php';

function sendNotiftoCostumer($resetEmail)
{
    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    $mail->Username = 'villaruzsofiaapp@gmail.com';
    $mail->Password = 'lewu ykhi nttm xlvw';
    $mail->setFrom('villaruzsofiaapp@gmail.com', 'Sofia');
    $mail->addAddress($resetEmail);

    // ✅ Strip the @domain part — show only the username in the greeting
    $emailParts = explode('@', $resetEmail);
    $emailSafe = htmlspecialchars($emailParts[0], ENT_QUOTES, 'UTF-8');

    $mail->isHTML(true);
    $mail->Subject = "Villaruz Print Shop & Gen. MDSE. - Password Updated";
    $mail->Body = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Password Updated</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

            body {
                font-family: 'Poppins', Arial, sans-serif;
                margin: 0;
                padding: 0;
                background-color: #f8fafc;
                color: #334155;
            }

            .email-container {
                max-width: 600px;
                margin: 20px auto;
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                overflow: hidden;
                border: 1px solid #e2e8f0;
            }

            .email-body {
                padding: 30px;
                line-height: 1.6;
            }

            .email-body p {
                margin: 15px 0;
                font-size: 16px;
            }

            .greeting {
                margin: 15px 0;
                font-size: 16px;
            }

            .user-email {
                text-decoration: none !important;
                color: #334155 !important;
                font-weight: 600;
            }

            .otp-container {
                background: #f1f5f9;
                border-radius: 8px;
                padding: 20px;
                text-align: center;
                margin: 25px 0;
            }

            .otp-code {
                font-size: 32px;
                font-weight: 700;
                letter-spacing: 3px;
                color: #1e40af;
                margin: 10px 0;
            }

            .action-button {
                display: inline-block;
                background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                color: #ffffff;
                text-decoration: none;
                padding: 12px 30px;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 500;
                margin: 20px 0;
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 6px rgba(29, 78, 216, 0.15);
            }

            .divider {
                height: 1px;
                background: linear-gradient(to right, transparent, #cbd5e1, transparent);
                margin: 25px 0;
            }

            .footer {
                text-align: center;
                background: #f1f5f9;
                padding: 20px;
                font-size: 14px;
                color: #64748b;
                border-top: 1px solid #e2e8f0;
            }

            .email-logo-wrap {
                text-align: center;
                margin-bottom: 20px;
            }

            .email-logo {
                display: block;
                margin: 0 auto;
                width: 120px;
                max-width: 120px;
                height: auto;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='email-body'>

            <div style='text-align: center; margin-bottom: 20px;'>
                <img src='https://villaruz-print-shop-and-general-merchandise.shop/logo/ic_launcher.png' alt='Sofia Logo' width='120' style='display: block; margin: 0 auto; max-width: 120px; height: auto;'>
            </div>

                <p class='greeting'>Good Day! <span class='user-email'>$emailSafe</span>,</p><br>

                <p>Your password has been successfully updated.</p>

                <div class='divider'></div>

                <p><strong>🔒 Security Tips:</strong></p>
                <ul style=\"margin: 10px 0 15px 20px; font-size: 14px;\">
                    <li>Never share your OTP code with anyone</li>
                    <li>Update your password regularly</li>
                </ul>

                <p>Thank you for trusting Sofia — we're happy to have you with us!</p>
            </div>

            <div class='footer'>
                <p style='font-size: 12px; margin-top: 10px;'>
                    This email is computer generated. Do not reply | Villaruz Print Shop & Gen. MDSE.
                </p>
                <p style='font-size: 11px; color: #94a3b8;'>
                    📧 Need help? Email us at villaruzsofiaapp@gmail.com
                </p>
            </div>
        </div>
    </body>
    </html>
    ";

    return $mail->send();
}


$errors = [];
$success = '';
$otp_code = '';
$password = '';
$confirm_password = '';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if email exists in session (from forgot_password.php)
$resetEmail = $_SESSION['reset_email'] ?? '';

if (empty($resetEmail)) {
    $_SESSION['error'] = 'Please request a password reset first.';
    header('Location: forgot_password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    $otp_code = trim($_POST['otp_code'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate OTP
    if (empty($otp_code)) {
        $errors[] = 'Please enter the verification code.';
    } elseif (!preg_match('/^[0-9]{6}$/', $otp_code)) {
        $errors[] = 'Please enter a valid 6-digit verification code.';
    }

    // Validate password
    $passwordValid = true;
    if (strlen($password) < 8)
        $passwordValid = false;
    if (!preg_match('/[A-Z]/', $password))
        $passwordValid = false;
    if (!preg_match('/[0-9]/', $password))
        $passwordValid = false;
    if (!preg_match('/[^a-zA-Z0-9]/', $password))
        $passwordValid = false;

    if (!$passwordValid) {
        $errors[] = 'Password must be at least 8 characters with uppercase, number, and special character.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        // Check if OTP exists in database for this email
        $stmt = $pdo->prepare("SELECT id, otp_code FROM customers WHERE email = ?");
        $stmt->execute([$resetEmail]);
        $customer = $stmt->fetch();

        if ($customer) {
            if ($customer['otp_code'] == $otp_code) {
                // Update password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE customers SET password = ?, text_pass = ? WHERE email = ?");

                if ($updateStmt->execute([$hashedPassword, $password, $resetEmail])) {

                    $emailSent = sendNotiftoCostumer($resetEmail);

                    // Clear session
                    unset($_SESSION['reset_email']);
                    header('Location: login.php');
                    exit;
                } else {
                    $errors[] = 'Failed to reset password. Please try again.';
                }
            } else {
                $errors[] = 'Invalid verification code. Please try again.';
            }
        } else {
            $errors[] = 'Account not found. Please request a new reset code.';
            header('Location: forgot_password.php');
            exit;
        }
    }
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
    <title>Reset Password - Villaruz Print Shop</title>
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
            background: #ffffff;
            color: #1e293b;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
        }

        /* ========== APP CONTAINER ========== */
        .app-shell {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            background: #ffffff;
        }

        /* ========== HEADER ========== */
        .app-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 24px 0 24px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #3b82f6;
            text-decoration: none;
            font-size: 16px;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: #e2e8f0;
            transform: scale(1.05);
        }

        .back-btn:active {
            transform: scale(0.95);
        }

        .app-header-title {
            font-size: 16px;
            font-weight: 600;
            color: #64748b;
            letter-spacing: 0.3px;
        }

        /* ========== CONTENT ========== */
        .auth-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            padding: 10px 24px 40px 24px;
        }

        .auth-card {
            width: 100%;
        }

        .auth-logo {
            display: flex;
            justify-content: center;
            margin-top: 10px;
            margin-bottom: 24px;
        }

        .auth-logo img {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }

        .auth-title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
            text-align: center;
            color: #0f172a;
            line-height: 1.2;
        }

        .auth-title span {
            background: linear-gradient(145deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .auth-sub {
            text-align: center;
            color: #64748b;
            margin-bottom: 28px;
            font-size: 14px;
            line-height: 1.5;
            padding: 0 10px;
        }

        .form-group {
            margin-bottom: 20px;
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
            padding: 16px 18px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            color: #1e293b;
            font-size: 16px;
            outline: none;
            transition: 0.25s ease;
            -webkit-appearance: none;
            appearance: none;
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }

        .form-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            background: #ffffff;
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
            transition: color 0.2s;
            font-size: 18px;
        }

        .password-wrapper i:hover {
            color: #3b82f6;
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(145deg, #3b82f6, #6366f1);
            border: none;
            padding: 16px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 16px;
            color: white;
            cursor: pointer;
            transition: 0.25s ease;
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            -webkit-appearance: none;
            appearance: none;
            box-shadow: 0 6px 18px rgba(59, 130, 246, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(59, 130, 246, 0.35);
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
        }

        .auth-footer {
            text-align: center;
            margin-top: 28px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
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

        .alert {
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .alert i {
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
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

        .password-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
            display: flex;
            align-items: flex-start;
            gap: 5px;
            line-height: 1.4;
        }

        .password-hint i {
            color: #3b82f6;
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* ========== DESKTOP VIEW ========== */
        @media (min-width: 600px) {
            body {
                background: #f1f5f9;
                padding-top: 0;
                padding-bottom: 0;
            }

            .app-shell {
                min-height: auto;
                margin: 40px auto;
                border-radius: 24px;
                box-shadow: 0 20px 45px rgba(0, 0, 0, 0.08);
                border: 1px solid #e2e8f0;
                overflow: hidden;
            }

            .auth-container {
                padding: 10px 40px 50px 40px;
            }

            .app-header {
                padding: 24px 30px 0 30px;
            }
        }

        /* ========== SMALL PHONES ========== */
        @media (max-width: 380px) {
            .auth-title {
                font-size: 24px;
            }

            .auth-container {
                padding: 8px 18px 30px 18px;
            }

            .auth-logo img {
                width: 84px;
                height: 84px;
            }

            .app-header {
                padding: 16px 18px 0 18px;
            }
        }

        /* ========== SHORT SCREENS ========== */
        @media (max-height: 700px) {
            .auth-logo {
                margin-top: 0;
                margin-bottom: 16px;
            }

            .auth-logo img {
                width: 80px;
                height: 80px;
            }

            .auth-sub {
                margin-bottom: 20px;
            }

            .auth-container {
                padding: 8px 24px 24px 24px;
            }
        }
    </style>
</head>

<body>
    <div class="app-shell">


        <div class="auth-container">
            <div class="auth-card">

                <div class="auth-logo">
                    <img src="logo/ic_launcher.png" alt="Villaruz Print Shop Logo">
                </div>

                <h2 class="auth-title">Update <span>Password</span></h2>

                <br><br>
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
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div><?php echo htmlspecialchars($success); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="form-group">
                        <label><i class="fas fa-qrcode"></i> Verification Code</label>
                        <input type="text" name="otp_code" placeholder="Enter 6-digit code"
                            value="<?php echo htmlspecialchars($otp_code); ?>" maxlength="6" required>
                        <div class="password-hint">
                            <span>Enter the 6-digit code sent to your email</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> New Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" placeholder="Enter new password"
                                required>
                            <i class="fas fa-eye-slash" id="togglePassword"></i>
                        </div>
                        <div class="password-hint">
                            <span>At least 8 characters with uppercase, number, and special character</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-check-circle"></i> Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password"
                                placeholder="Confirm new password" required>
                            <i class="fas fa-eye-slash" id="toggleConfirmPassword"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">
                        Change Password
                    </button>

                    <div class="auth-footer">
                        <a href="forgot_password.php">← Request new code</a>
                    </div>
                </form>

            </div>
        </div>

    </div>

    <script>
        // Toggle password visibility
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
    </script>
</body>

</html>