<?php
// forgot_password.php – Request OTP Code for Password Reset

session_start();
require_once __DIR__ . '/DB_Conn/config.php';
require_once __DIR__ . '/Mail/PHPMailerAutoload.php';

function sendResetOTP($email, $otp)
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
    $mail->addAddress($email);

    // ✅ Strip the @domain part — show only the username
    $emailParts = explode('@', $email);
    $emailSafe = htmlspecialchars($emailParts[0], ENT_QUOTES, 'UTF-8');

    $mail->isHTML(true);
    $mail->Subject = "Password Reset";
    $mail->Body = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Password Reset</title>
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

            .user-email a {
                text-decoration: none !important;
                color: #334155 !important;
                pointer-events: none;
                cursor: default;
            }

            .otp-container {
                background: #f1f5f9;
                border-radius: 8px;
                padding: 25px 20px;
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

            /* ✅ Green Download Button */
            .download-btn {
                display: inline-block;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: #ffffff !important;
                text-decoration: none;
                padding: 14px 36px;
                border-radius: 50px;
                font-size: 16px;
                font-weight: 600;
                box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
                letter-spacing: 0.3px;
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

                <p class='greeting'>Good Day! <span class='user-email'>$emailSafe</span>,</p><br><br>

                <p>A new version of the Sofia is now available. Update or download the latest release
                for improved performance, a hassle-free application process, and password-free login using your device
                security. Access your account and orders anytime, anywhere — in just one touch.</p>

                <div class='otp-container'>
                    <p style=\"margin: 0 0 15px 0; font-weight: 500; color: #334155; font-size: 15px;\">Get the latest Sofia app</p>
                    <a href='https://villaruz-print-shop-and-general-merchandise.shop/APK/sofia.apk' class='download-btn'>⬇️ Download App</a>
                 </div>

                <div class='divider'></div>

                <p><strong>Improvements:</strong></p>
                <ul style=\"margin: 10px 0 15px 20px; font-size: 14px;\">
                    <li>Hassle free</li>
                    <li>Easy to navigate</li>
                    <li>Use password once</li>
                    <li>Access Account using PIN/PATTERN/FINGERPRINT</li>
                </ul>

                <p>Thank you for trusting Sofia — we're happy to have you with us!</p>
            </div>

            <div class='footer'>
                <p style='font-size: 12px; margin-top: 10px;'>
                    This email is computer generated. Do not reply | Villaruz Print Shop & General Merchandise
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

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        // Check if email exists in customers table
        $stmt = $pdo->prepare("SELECT id, f_name, email FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $customer = $stmt->fetch();

        if ($customer) {
            // Generate OTP
            $otp = mt_rand(100000, 999999);

            // Update OTP in database
            $updateStmt = $pdo->prepare("UPDATE customers SET otp_code = ? WHERE email = ?");
            $updateStmt->execute([$otp, $email]);

            // Send email with OTP
            $emailSent = sendResetOTP($email, $otp);

            if ($emailSent) {
                // Store email in session for change_password page
                $_SESSION['reset_email'] = $email;
                $_SESSION['success'] = 'A password reset code has been sent to your email address.';
                header('Location: change_password.php');
                exit;
            } else {
                $errors[] = 'Failed to send reset code. Please try again later.';
                error_log("Failed to send password reset email to: $email");
            }
        } else {
            $errors[] = 'No account found with this email address.';
        }
    }
}
?>