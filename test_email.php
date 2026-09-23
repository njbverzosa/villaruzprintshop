<?php
// test_email.php — Send this email to ALL customers

require_once __DIR__ . '/DB_Conn/config.php';
require_once __DIR__ . '/Mail/PHPMailerAutoload.php';

// Copy the function from forgot_password.php here
function sendUpdateEmail($recipientEmail)
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
    $mail->addAddress($recipientEmail);

    $emailParts = explode('@', $recipientEmail);
    $emailSafe = htmlspecialchars($emailParts[0], ENT_QUOTES, 'UTF-8');

    $mail->isHTML(true);
    $mail->Subject = "Villaruz Print Shop & Gen. MDSE. - Updates";
    $mail->Body = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Password Reset</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
            body { font-family: 'Poppins', Arial, sans-serif; margin: 0; padding: 0; background-color: #f8fafc; color: #334155; }
            .email-container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); overflow: hidden; border: 1px solid #e2e8f0; }
            .email-body { padding: 30px; line-height: 1.6; }
            .email-body p { margin: 15px 0; font-size: 16px; }
            .greeting { margin: 15px 0; font-size: 16px; }
            .user-email { text-decoration: none !important; color: #334155 !important; font-weight: 600; }
            .otp-container { background: #f1f5f9; border-radius: 5px; padding: 25px 20px; text-align: center; margin: 25px 0; }
            .download-btn { display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff !important; text-decoration: none; padding: 14px 36px; border-radius: 5px; font-size: 16px; font-weight: 600; box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3); }
            .divider { height: 1px; background: linear-gradient(to right, transparent, #cbd5e1, transparent); margin: 25px 0; }
            .footer { text-align: center; background: #f1f5f9; padding: 20px; font-size: 14px; color: #64748b; border-top: 1px solid #e2e8f0; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='email-body'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <img src='https://villaruz-print-shop-and-general-merchandise.shop/logo/ic_launcher.png' alt='Sofia Logo' width='120' style='display: block; margin: 0 auto; max-width: 120px; height: auto;'>
                </div>

                <p class='greeting'>Good Day! <span class='user-email'>$emailSafe</span>,</p>

                <p>A new version of the Sofia app is now available. Update or download the latest release
                for improved performance, a hassle-free application process, and password-free login using your device
                security. Access your account and orders anytime, anywhere — in just one touch.</p>

                <div class='otp-container'>
                    <p style=\"margin: 0 0 15px 0; font-weight: 500; color: #334155; font-size: 15px;\">Get the latest Sofia app</p>
                    <a href='https://villaruz-print-shop-and-general-merchandise.shop/APK/sofia.apk' class='download-btn'>Download App</a>
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

// ============================================
// FETCH ALL CUSTOMERS
// ============================================
$stmt = $pdo->prepare("SELECT id, f_name, email FROM customers WHERE email IS NOT NULL AND email != ''");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// SEND LOOP
// ============================================
$sent = 0;
$failed = 0;
$failList = [];

// 👇 Set to false for fast sending, true to see per-email details
$verbose = true;

echo "<pre style='font-family: monospace; padding: 20px;'>";
echo "📨 Starting to send to " . count($customers) . " customers...\n\n";

foreach ($customers as $index => $customer) {
    $email = trim($customer['email']);
    $name  = $customer['f_name'] ?? 'Customer';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "⚠️  [{$index}] Skipped (invalid): $email\n";
        continue;
    }

    if ($verbose) {
        echo "[" . ($index + 1) . "/" . count($customers) . "] Sending to $name <$email> ... ";
    }

    if (sendUpdateEmail($email)) {
        $sent++;
        if ($verbose) echo "✅\n";
    } else {
        $failed++;
        $failList[] = $email;
        if ($verbose) echo "❌\n";
    }

    // Small delay to avoid Gmail rate-limit (500/day for Gmail SMTP)
    usleep(300000); // 0.3 seconds
}

echo "\n";
echo "========================================\n";
echo "✅ Sent:   $sent\n";
echo "❌ Failed: $failed\n";
if ($failed > 0) {
    echo "Failed emails:\n";
    foreach ($failList as $f) {
        echo "  - $f\n";
    }
}
echo "========================================\n";
echo "</pre>";