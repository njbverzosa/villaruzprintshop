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
    'phone_number' => ''
];
$registrationSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    // Get form data
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $phone_number = trim($_POST['phone_number'] ?? '');

    // Store for form repopulation
    $formData = [
        'phone_number' => $phone_number
    ];

    // ------------------------------------------------------------
    // 4a. VALIDATION
    // ------------------------------------------------------------

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
        // Generate account number from phone number
        $accNumber = generateAccNumberFromPhone($pdo, $phone_number);
        
        date_default_timezone_set('Asia/Manila');
        $registrationDate = date('D, j M Y g:i A');
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert user - FIXED: 5 placeholders with 5 values
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
                // Store phone number in session for verification
                $_SESSION['verification_phone'] = $phone_number;
                $_SESSION['verification_acc_number'] = $accNumber;
                
                // Set registration success flag
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

        footer {
            background: #ffffff;
            padding: 20px 5%;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
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
            transition: opacity 0.5s ease;
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

        .alert .alert-message {
            flex: 1;
        }

        @media (max-width: 500px) {
            .auth-card {
                padding: 30px 25px;
            }

            .logo img {
                width: 75px;
            }

            .alert {
                flex-wrap: wrap;
                padding: 12px 14px;
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
            <p class="auth-sub">Create Account</p>
            <div style="text-align: center; margin-bottom: 20px;">
                <span class="version-badge">V11.50.41</span>
            </div>

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
                    <input type="tel" name="phone_number" placeholder="09123456789" maxlength="11"
                        value="<?php echo htmlspecialchars($formData['phone_number']); ?>" required>
                    <div class="phone-hint">
                        <i class="fas fa-info-circle"></i> Enter 11-digit mobile number (e.g., 09123456789)
                    </div>
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

                <button type="submit" class="btn-primary" id="registerBtn">
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
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPassword = document.getElementById('confirm_password');
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Phone number formatting - allow only numbers and limit to 11
        document.querySelector('input[name="phone_number"]').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
        });

        // ============================================================
        // SUCCESS ALERT - SEQUENTIAL MESSAGES
        // ============================================================
        <?php if ($registrationSuccess): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const registerBtn = document.getElementById('registerBtn');
            const alertMessage = document.getElementById('alertMessage');
            const alertElement = document.getElementById('successAlert');
            
            // Disable register button
            if (registerBtn) {
                registerBtn.disabled = true;
            }
            
            // Show first message for 10 seconds
            alertMessage.textContent = 'Creating your account. Please wait...';
                        
            // Redirect after 10 seconds total
            setTimeout(function() {
                // Fade out animation
                alertElement.style.animation = 'fadeOut 0.2s ease forwards';
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 500);
            }, 6000);
        });
        <?php endif; ?>
    </script>
</body>

</html>