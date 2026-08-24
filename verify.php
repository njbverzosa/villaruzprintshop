<?php
// user.php - This is your verification page
session_start();
require_once __DIR__ . '/DB_Conn/config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get OTP from URL
$otp = $_GET['otp'] ?? '';
$email = $_GET['email'] ?? '';

// If email is not in URL, try to get from session
if (empty($email) && isset($_SESSION['verification_email'])) {
    $email = $_SESSION['verification_email'];
}

// If OTP is empty, show error
if (empty($otp)) {
    die("<h2>❌ Invalid Verification Link</h2><p>No OTP code provided. Please check your email for the correct link.</p>");
}

// If email is empty, show error
if (empty($email)) {
    die("<h2>❌ Invalid Verification Link</h2><p>No email address found. Please click the link from your email again.</p>");
}

// Database connection
try {
    // Find user with this OTP and email
    $stmt = $pdo->prepare("SELECT acc_number, f_name, email, otp_code, active_email FROM customers WHERE otp_code = ? AND email = ?");
    $stmt->execute([$otp, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Try without email filter (in case email was changed)
        $stmt = $pdo->prepare("SELECT acc_number, f_name, email, otp_code, active_email FROM customers WHERE otp_code = ?");
        $stmt->execute([$otp]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            die("<h2>❌ Invalid Verification Code</h2>
                 <p>The verification code you provided is invalid or has expired.</p>
                 <p>Please request a new verification email.</p>
                 <p><a href='account.php'>Go back to Account</a></p>");
        }
    }

    // Check if already verified
    if ($user['active_email'] == 1) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Email Already Verified</title>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background: #f1f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 500px; text-align: center; }
                .success-icon { font-size: 60px; color: #10b981; margin-bottom: 20px; }
                h2 { color: #0f172a; }
                p { color: #475569; line-height: 1.6; }
                .btn { display: inline-block; background: #3b82f6; color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; margin-top: 20px; }
                .btn:hover { background: #2563eb; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='success-icon'>✅</div>
                <h2>Email Already Verified</h2>
                <p>Your email address <strong>" . htmlspecialchars($user['email']) . "</strong> has already been verified.</p>
                <p>You can now access all features of your account.</p>
            </div>
        </body>
        </html>";
        exit();
    }

    // Update the user's email verification status
    $stmt = $pdo->prepare("UPDATE customers SET active_email = 1, otp_code = NULL WHERE acc_number = ?");
    $result = $stmt->execute([$user['acc_number']]);

    if ($result) {
        // Update session if user is logged in
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['active_email'] = 1;
            $_SESSION['user']['email'] = $user['email'];
        }
        
        // Clear verification session data
        unset($_SESSION['verification_otp']);
        unset($_SESSION['verification_email']);
        unset($_SESSION['verification_expires']);
        
        // Success page
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Email Verified Successfully</title>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; background: #f1f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 500px; text-align: center; animation: fadeIn 0.5s; }
                .success-icon { font-size: 60px; color: #10b981; margin-bottom: 20px; animation: bounce 1s; }
                h2 { color: #0f172a; margin-bottom: 10px; }
                p { color: #475569; line-height: 1.6; }
                .btn { display: inline-block; background: #3b82f6; color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; margin-top: 20px; transition: background 0.3s; }
                .btn:hover { background: #2563eb; }
                @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
                @keyframes bounce { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.2); } }
                .email-highlight { background: #f1f5f9; padding: 10px; border-radius: 6px; font-weight: 600; color: #0f172a; }
                .redirect-note { font-size: 13px; color: #94a3b8; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='success-icon'>✅</div>
                <h2>Email Verified Successfully!</h2>
                <p>Your email address <br><span class='email-highlight'>" . htmlspecialchars($user['email']) . "</span><br> has been verified.</p>
                <p>You can now enjoy all the features of your account.</p>
                <p class='redirect-note'>Redirecting to login page in 5 seconds...</p>
            </div>
            <script>
                // Auto-redirect after 5 seconds
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 5000);
            </script>
        </body>
        </html>";
    } else {
        die("<h2>❌ Verification Failed</h2>
             <p>There was an error verifying your email. Please try again or contact support.</p>
             <p><a href='account.php'>Go back to Account</a></p>");
    }

} catch (PDOException $e) {
    error_log("Verification Error: " . $e->getMessage());
    die("<h2>❌ System Error</h2>
         <p>An error occurred while verifying your email. Please try again later.</p>
         <p><a href='account.php'>Go back to Account</a></p>");
}
?>