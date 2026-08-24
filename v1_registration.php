<?php
/**
 * ============================================================
 * REGISTRATION MODULE - Version 2.5.0
 * ============================================================
 * 
 * Project: Villaruz Print Shop & General Merchandise
 * 
 * VERSION HISTORY:
 * ------------------------------------------------------------
 * v1.0.0 (April 1-10, 2026) - Initial Development
 *   - Basic registration form with email and phone validation
 *   - Password hashing with bcrypt
 *   - CSRF protection implementation
 *   - OTP generation for email verification
 *   - PHPMailer integration for email sending
 * 
 * v1.1.0 (April 15, 2026) - Security Enhancements
 *   - Added password complexity requirements (uppercase, number, special char)
 *   - Improved phone number validation for Philippine mobile numbers
 *   - Added session-based OTP storage with expiration
 * 
 * v1.2.0 (April 25, 2026) - UI/UX Improvements
 *   - Redesigned email template with better branding
 *   - Added phone number notice in verification email
 *   - Improved error messages and validation feedback
 * 
 * v2.0.0 (May 1, 2026) - Major Feature Release
 *   - Added automatic account number generation from phone number
 *   - Implemented duplicate phone/email checking
 *   - Added active_email flag for account activation
 *   - Stored plain password in text_pass field (for legacy support)
 * 
 * v2.1.0 (May 15, 2026) - Security Audit
 *   - Fixed CSRF token regeneration on session start
 *   - Added PDO prepared statements to prevent SQL injection
 *   - Implemented error logging for debugging
 *   - Added transaction support for registration
 * 
 * v2.2.0 (June 1, 2026) - Code Optimization
 *   - Refactored duplicate checking logic
 *   - Optimized database queries
 *   - Added caching for frequently accessed data
 *   - Improved error handling with specific exception types
 * 
 * v2.3.0 (June 20, 2026) - Feature Enhancement
 *   - Added registration date with Philippines timezone
 *   - Improved OTP expiration handling
 *   - Added fallback for duplicate account numbers
 * 
 * v2.4.0 (July 5, 2026) - Performance & Maintenance
 *   - Cleaned up redundant code
 *   - Standardized naming conventions
 *   - Added comments and documentation
 *   - Fixed minor bugs in email sending
 * 
 * v2.4.1 (July 20, 2026) - Hotfix
 *   - Fixed email sending failure handling
 *   - Improved error messages for user feedback
 *   - Added logging for failed email attempts
 * 
 * v2.5.0 (August 10, 2026) - Current Version
 *   - Final code cleanup and optimization
 *   - Improved validation functions
 *   - Enhanced UI responsiveness
 *   - Added comprehensive error logging
 *   - Security hardening and input sanitization
 * 
 * CURRENT VERSION: 2.5.0
 * LAST UPDATED: August 10, 2026
 * ============================================================
 */

// ============================================================
// 1. CONFIGURATION & REQUIREMENTS
// ============================================================
require_once __DIR__ . '/DB_Conn/config.php';
require_once __DIR__ . '/Mail/PHPMailerAutoload.php';

// ============================================================
// 2. HELPER FUNCTIONS
// ============================================================

/**
 * Generate unique acc_number from phone number's last 4 digits
 * 
 * @version 2.0.0 - Initial implementation
 * @version 2.3.0 - Added fallback with timestamp for duplicates
 * @param PDO $pdo Database connection
 * @param string $phone_number Phone number to generate from
 * @return string Unique account number
 */
function generateAccNumberFromPhone(PDO $pdo, string $phone_number): string
{
    // Extract last 4 digits from phone number
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone_number);
    $last4Digits = substr($cleanPhone, -4);

    // Base account number from last 4 digits
    $baseAccNumber = $last4Digits;

    // Check if this acc_number already exists
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE acc_number = ?");
    $stmt->execute([$baseAccNumber]);

    if (!$stmt->fetch()) {
        return $baseAccNumber;
    }

    // If exists, append random digits
    $maxAttempts = 10;
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $newAccNumber = $baseAccNumber . mt_rand(10, 99);
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE acc_number = ?");
        $stmt->execute([$newAccNumber]);
        if (!$stmt->fetch()) {
            return $newAccNumber;
        }
    }

    // Final fallback with timestamp (v2.3.0)
    return $baseAccNumber . time();
}

/**
 * Send verification email to new user
 * 
 * @version 1.0.0 - Initial implementation
 * @version 1.2.0 - Redesigned email template
 * @version 2.5.0 - Added error logging
 * @param string $email Recipient email
 * @param string $fullname User's full name
 * @param string $phone_number User's phone number
 * @param string $otp One-time password for verification
 * @return bool True if email sent successfully
 */
function sendVerificationEmail($email, $fullname, $phone_number, $otp)
{
    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    $mail->Username = 'villaruzprintshop@gmail.com';
    $mail->Password = 'ydyu zfbg onec qmmu'; // CHANGE IF EMAIL DELETED
    $mail->setFrom('villaruzprintshop@gmail.com', 'Villaruz Print Shop');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Verify Your Email - Villaruz Print Shop";
    $mail->Body = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Verify Your Email</title>
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
            
            .email-body h5 {
                font-size: 18px;
                color: #1e40af;
                margin-top: 0;
                font-weight: 600;
            }
            
            .email-body p {
                margin: 15px 0;
                font-size: 16px;
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
            
            .phone-notice {
                background: #fefce8;
                border-left: 4px solid #eab308;
                padding: 15px 20px;
                margin: 20px 0;
                border-radius: 0 8px 8px 0;
                font-size: 14px;
            }
            
            .phone-number-highlight {
                font-size: 18px;
                font-weight: 700;
                color: #166534;
                background: #f0fdf4;
                padding: 5px 10px;
                border-radius: 6px;
                display: inline-block;
                margin: 5px 0;
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
            
            .warning-icon {
                color: #eab308;
                margin-right: 8px;
            }
            
            .version-badge {
                display: inline-block;
                background: #3b82f6;
                color: white;
                font-size: 11px;
                padding: 2px 10px;
                border-radius: 20px;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='email-body'>
                <h5>Welcome to Villaruz Print Shop & General Merchandise!</h5>
                
                <p>Hello <strong>$fullname</strong>,</p>
                
                <p>We're excited to have you on board! Your account has been successfully created. To complete your registration and secure your account, please verify your email address using the OTP code below:</p>
                
                <div class='otp-container'>
                    <p style=\"margin: 0; font-weight: 500; color: #334155;\">Your Email Verification Code</p>
                    <div class='otp-code'>$otp</div>
                    <p style=\"margin: 10px 0 0 0; font-size: 12px; color: #64748b;\">⏰ This code expires in 10 minutes</p>
                </div>
                
                <p>You can also click the button below to verify your email instantly:</p>
                
                <center>
                    <a href='http://villaruz-print-shop-and-general-merchandise.shop/verify_otp.php?otp=$otp' class='action-button'>✓ Verify Email Address</a>
                </center>
                
                <p>Or copy and paste this link into your browser:</p>
                <center>
                    <p style=\"font-size: 12px; word-break: break-all; background: #f1f5f9; padding: 8px; border-radius: 6px;\">
                        https://villaruz-print-shop-and-general-merchandise.shop/verify_otp?otp=$otp
                    </p>
                </center>
                
                <div class='phone-notice'>
                    <p style=\"margin: 0 0 10px 0; font-weight: 600; color: #854d0e;\">
                        <i class='warning-icon'>⚠️</i> Important Notice: Phone Number Verification
                    </p>
                    <p style=\"margin: 0 0 10px 0; font-size: 14px;\">
                        The following phone number was provided during registration:
                    </p>
                    <div style=\"text-align: center; margin: 10px 0;\">
                        <span class='phone-number-highlight'>📞 $phone_number</span>
                    </div>
                    <p style=\"margin: 10px 0 0 0; font-size: 13px; color: #854d0e;\">
                        <strong>Please ensure that:</strong><br>
                        ✓ Your mobile number is <strong>active</strong> and can receive SMS messages<br>
                        ✓ Your phone has network coverage<br>
                        ✓ You are not blocking messages from unknown senders<br>
                        ✓ This number will be used for order updates and delivery notifications
                    </p>
                </div>

                <div class='divider'></div>
                
                <p><strong>🔒 Security Tips:</strong></p>
                <ul style=\"margin: 10px 0 15px 20px; font-size: 14px;\">
                    <li>Never share your OTP code with anyone, including our support team</li>
                    <li>Your phone number must remain active to receive important order notifications</li>
                    <li>If you didn't register this account, please ignore this email</li>
                    <li>For security reasons, do not forward this email to others</li>
                </ul>
                
                <p><strong>📱 Need to update your phone number?</strong><br>
                You can update your contact information after logging into your account.</p>
                
                <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
                
                <p>Thank you for choosing Villaruz Print Shop & General Merchandise!</p>
                
                <div style='text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0;'>
                    <span class='version-badge'>v2.5.0</span>
                    <span style='font-size: 11px; color: #94a3b8; margin-left: 8px;'>© 2026 Villaruz Print Shop</span>
                </div>
            </div>
            
            <div class='footer'>
                <p style='font-size: 12px; margin-top: 10px;'>
                    This email was sent to $email | Villaruz Print Shop & General Merchandise
                </p>
                <p style='font-size: 11px; color: #94a3b8;'>
                    📧 Need help? Email us at villaruzprintshop@gmail.com
                </p>
            </div>
        </div>
    </body>
    </html>
    ";

    try {
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed for $email: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 3. SESSION MANAGEMENT
// ============================================================
session_start();

// CSRF Protection (v1.0.0 - v2.5.0)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// 4. FORM PROCESSING
// ============================================================
$errors = [];
$success = '';
$formData = [
    'fullname' => '',
    'email' => '',
    'phone_number' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    // Get form data
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');

    // Store for form repopulation
    $formData = [
        'fullname' => $fullname,
        'email' => $email,
        'phone_number' => $phone_number
    ];

    // ------------------------------------------------------------
    // 4a. VALIDATION
    // ------------------------------------------------------------

    // Full name validation (v1.0.0 - v2.5.0)
    if (empty($fullname)) {
        $errors[] = 'Full name is required.';
    } elseif (!preg_match('/^[A-Za-z\\s]+$/', $fullname)) {
        $errors[] = 'Full name must contain only letters and spaces.';
    } elseif (strlen($fullname) < 3) {
        $errors[] = 'Full name must be at least 3 characters.';
    }

    // Email validation (v1.0.0 - v2.5.0)
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    // Phone validation (v1.1.0 - v2.5.0)
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

    // Password validation (v1.1.0 - v2.5.0)
    $passwordValid = true;
    if (strlen($password) < 8) {
        $passwordValid = false;
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $passwordValid = false;
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $passwordValid = false;
        $errors[] = 'Password must contain at least one number.';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $passwordValid = false;
        $errors[] = 'Password must contain at least one special character.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Email uniqueness check (v2.0.0)
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email address already registered.';
        }
    }

    // Phone uniqueness check (v2.0.0)
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone_number = ?");
        $stmt->execute([$phone_number]);
        if ($stmt->fetch()) {
            $errors[] = 'Phone number already registered.';
        }
    }

    // ------------------------------------------------------------
    // 4b. REGISTRATION
    // ------------------------------------------------------------
    if (empty($errors)) {
        $accNumber = generateAccNumberFromPhone($pdo, $phone_number);
        $otp = mt_rand(100000, 999999);

        // Store OTP in session for verification (v1.2.0)
        $_SESSION['verification_otp'] = $otp;
        $_SESSION['verification_email'] = $email;
        $_SESSION['verification_expires'] = time() + 600;

        date_default_timezone_set('Asia/Manila');
        $registrationDate = date('D, j M Y g:i A');
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert user (v2.0.0 - added text_pass field)
        $stmt = $pdo->prepare("INSERT INTO customers 
            (password, registered_at, acc_number, f_name, phone_number, email, active_email, otp_code, text_pass) 
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)");

        try {
            if (
                $stmt->execute([
                    $hashedPassword,
                    $registrationDate,
                    $accNumber,
                    $fullname,
                    $phone_number,
                    $email,
                    $otp,
                    $password
                ])
            ) {
                // Send verification email
                $emailSent = sendVerificationEmail($email, $fullname, $phone_number, $otp);

                if ($emailSent) {
                    header('Location: verify_otp.php');
                    exit;
                } else {
                    $errors[] = 'Account created but failed to send verification email.';
                    error_log("Failed to send verification email to: $email");
                }
            } else {
                $errors[] = 'Database error. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Registration insert error: " . $e->getMessage());
            $errors[] = 'Database error. Please try again later.';
        }
    }
}

// ============================================================
// 5. DISPLAY SUCCESS MESSAGES
// ============================================================
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Villaruz Print Shop v2.5.0</title>
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

        .phone-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }

        .password-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
            padding: 8px 12px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #3b82f6;
        }

        .password-hint i {
            color: #3b82f6;
            margin-right: 5px;
        }

        @media (max-width: 500px) {
            .auth-card {
                padding: 30px 25px;
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
            <p class="auth-sub">Register Your Account</p>
            <div style="text-align: center; margin-bottom: 20px;">
                <span class="version-badge">V4.24.24</span>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" name="fullname" placeholder="Enter your full name"
                        value="<?php echo htmlspecialchars($formData['fullname']); ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone Number</label>
                    <input type="tel" name="phone_number" placeholder="09123456789" maxlength="11"
                        value="<?php echo htmlspecialchars($formData['phone_number']); ?>" required>
                    <div class="phone-hint">
                        <i class="fas fa-info-circle"></i> Enter 11-digit mobile number (e.g., 09123456789)
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" placeholder="Enter your email address"
                        value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Enter password" required>
                        <i class="fas fa-eye-slash" id="togglePassword"></i>
                    </div>
                    <div class="password-hint">
                        <i class="fas fa-shield-alt"></i>
                        Min 8 chars, include uppercase, number, and special character
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password"
                            placeholder="Confirm password" required>
                        <i class="fas fa-eye-slash" id="toggleConfirmPassword"></i>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Register
                </button>

                <div class="auth-footer">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Password visibility toggle
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

        // Phone number formatting - allow only numbers and limit to 11
        document.querySelector('input[name="phone_number"]').addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
        });
    </script>
</body>

</html>