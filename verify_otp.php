<?php
// verify_otp.php – OTP Verification for Villaruz Print Shop

session_start();
require_once __DIR__ . '/DB_Conn/config.php';

$errors = [];
$success = '';

// Handle resend OTP request
if (isset($_GET['resend']) && $_GET['resend'] == 'true') {
    if (isset($_SESSION['verification_email'])) {
        $email = $_SESSION['verification_email'];

        // Get user data from database
        $stmt = $pdo->prepare("SELECT id, f_name, phone_number, email, otp_code FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate new OTP
            $newOtp = mt_rand(100000, 999999);

            // Update OTP in database
            $updateStmt = $pdo->prepare("UPDATE customers SET otp_code = ? WHERE email = ?");
            $updateStmt->execute([$newOtp, $email]);

            // Update session
            $_SESSION['verification_otp'] = $newOtp;
            $_SESSION['verification_expires'] = time() + 600;

            // Resend email
            require_once __DIR__ . '/Mail/phpmailer/PHPMailerAutoload.php';

            function sendVerificationEmail($email, $fullname, $phone_number, $otp)
            {
                $mail = new PHPMailer;
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->Port = 587;
                $mail->SMTPAuth = true;
                $mail->SMTPSecure = 'tls';
                $mail->Username = 'thisdomain24@gmail.com';
                $mail->Password = 'rhtq qcaj mdqp sdkv';
                $mail->setFrom('thisdomain24@gmail.com', 'Villaruz Print Shop');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = "Your New Verification Code - Villaruz Print Shop";
                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: 'Poppins', Arial, sans-serif; }
                        .otp-code { font-size: 32px; font-weight: bold; color: #1e40af; padding: 20px; background: #f1f5f9; display: inline-block; border-radius: 8px; letter-spacing: 3px; }
                        .container { max-width: 600px; margin: auto; padding: 20px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2>New Verification Code</h2>
                        <p>Hello $fullname,</p>
                        <p>You requested a new verification code. Please use the code below to verify your email:</p>
                        <div class='otp-code'>$otp</div>
                        <p>This code will expire in 10 minutes.</p>
                        <p>If you didn't request this, please ignore this email.</p>
                    </div>
                </body>
                </html>
                ";
                return $mail->send();
            }

            $emailSent = sendVerificationEmail($email, $user['f_name'], $user['phone_number'], $newOtp);

            if ($emailSent) {
                $success = 'A new verification code has been sent to your email.';
            } else {
                $errors[] = 'Failed to resend verification code. Please try again.';
            }
        } else {
            $errors[] = 'Email not found. Please register again.';
        }
    } else {
        $errors[] = 'Session expired. Please register again.';
    }
}

// Handle OTP verification from URL (clicked email link)
if (isset($_GET['otp'])) {
    $otp = $_GET['otp'];

    // Verify OTP from database
    $stmt = $pdo->prepare("SELECT id, f_name, acc_number, phone_number, email, otp_code, active_email, registered_at 
                           FROM customers 
                           WHERE otp_code = ?");
    $stmt->execute([$otp]);
    $customer = $stmt->fetch();

    if ($customer) {
        // Check if OTP is expired (10 minutes)
        $registeredTime = strtotime($customer['registered_at']);
        $currentTime = time();
        $timeDiff = ($currentTime - $registeredTime) / 60;

        if ($timeDiff > 10) {
            $errors[] = 'OTP code has expired. Please request a new code.';
        } elseif ($customer['active_email'] == 1) {
            $errors[] = 'This account has already been verified. Please login.';
        } else {
            // Update customer status - set active_email to 1
            $updateStmt = $pdo->prepare("UPDATE customers 
                                        SET active_email = 1, 
                                            otp_code = NULL 
                                        WHERE id = ?");
            $updateStmt->execute([$customer['id']]);

            $_SESSION['success'] = 'Email verified successfully! You can now login.';
            header('Location: login.php');
            exit;
        }
    } else {
        $errors[] = 'Invalid verification link. Please check your email or request a new code.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_code = trim($_POST['otp_code'] ?? '');

    if (empty($otp_code)) {
        $errors[] = 'Please enter the verification code.';
    }

    if (empty($errors)) {
        // Check OTP in database
        $stmt = $pdo->prepare("SELECT *
                               FROM customers 
                               WHERE otp_code = ?");
        $stmt->execute([$otp_code]);
        $customer = $stmt->fetch();

        if ($customer) {
            // Check if OTP is expired (10 minutes)
            $registeredTime = strtotime($customer['registered_at']);
            $currentTime = time();
            $timeDiff = ($currentTime - $registeredTime) / 60;

            if ($timeDiff > 10) {
                $errors[] = 'OTP code has expired (10 minutes). Please request a new code.';
            } elseif ($customer['active_email'] == 1) {
                $errors[] = 'This account has already been verified. Please login.';
            } else {
                // FIXED: Correct UPDATE statement - only 1 placeholder
                $updateStmt = $pdo->prepare("UPDATE customers SET active_email = 1 WHERE otp_code = ?");
                $updateStmt->execute([$customer['otp_code']]);

                // Clear verification session data
                unset($_SESSION['verification_otp']);
                unset($_SESSION['verification_email']);
                unset($_SESSION['verification_expires']);

                header('Location: welcome.html');
                exit;
            }
        } else {
            // Check session OTP as fallback
            if (isset($_SESSION['verification_otp']) && $_SESSION['verification_otp'] == $otp_code) {
                if (isset($_SESSION['verification_expires']) && time() > $_SESSION['verification_expires']) {
                    $errors[] = 'OTP code has expired. Please register again.';
                } else {
                    // Get email from session
                    $email = $_SESSION['verification_email'];

                    // Update database
                    $updateStmt = $pdo->prepare("UPDATE customers 
                                                SET active_email = 1, 
                                                    otp_code = NULL 
                                                WHERE email = ?");
                    $updateStmt->execute([$email]);

                    $_SESSION['success'] = 'Email verified successfully! You can now login.';
                    header('Location: welcome.html');
                    exit;
                }
            } else {
                $errors[] = 'Invalid verification code. Please try again.';
            }
        }
    }
}

// Get the email for display from session
$displayEmail = $_SESSION['verification_email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Villaruz Print Shop</title>
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
            max-width: 480px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.1);
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
            text-align: center;
            font-size: 28px;
            letter-spacing: 8px;
            font-weight: 600;
        }

        .form-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: #ffffff;
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

        .otp-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
            text-align: center;
        }

        .otp-hint i {
            color: #3b82f6;
            margin-right: 5px;
        }

        .resend-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .resend-link a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .resend-link a:hover {
            text-decoration: underline;
        }

        .email-display {
            background: #f1f5f9;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 25px;
            color: #1e40af;
            font-weight: 500;
            font-size: 14px;
            word-break: break-all;
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

            .form-group input {
                font-size: 22px;
                letter-spacing: 5px;
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
            <h2 class="auth-title">Verify <span>Email</span></h2>
            <p class="auth-sub">Enter the verification code sent to your email</p>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($displayEmail): ?>
                <div class="email-display">
                    <i class="fas fa-envelope"></i> Verifying: <?php echo htmlspecialchars($displayEmail); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off">
                <div class="form-group">
                    <label>Verification Code (OTP)</label>
                    <input type="text" name="otp_code" placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                        autocomplete="off" required>
                    <div class="otp-hint">
                        <i class="fas fa-info-circle"></i> Enter the 6-digit code sent to your email
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-check-circle"></i> Verify Account
                </button>

                <div class="resend-link">
                    <a href="?resend=true">
                        <i class="fas fa-redo-alt"></i> Didn't receive the code? Resend OTP
                    </a>
                </div>

                <div class="auth-footer">
                    <a href="registration.php"><i class="fas fa-arrow-left"></i> Back to Registration</a>
                </div>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>


    <script>
        // Auto-format OTP to allow only numbers and limit to 6 digits
        const otpInput = document.querySelector('input[name="otp_code"]');
        if (otpInput) {
            otpInput.addEventListener('input', function (e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
            });

            // Auto-focus on OTP field
            otpInput.focus();
        }
    </script>
</body>

</html>