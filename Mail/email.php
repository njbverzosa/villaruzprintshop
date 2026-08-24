<?php

/**
 * Send welcome email with account details
 */
function sendWelcomeEmail($email, $accNumber, $username, $myReferral) {
    require_once 'PHPMailerAutoload.php';
    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    $mail->Username = 'thisdomain24@gmail.com';
    $mail->Password = 'rhtq qcaj mdqp sdkv';
    $mail->setFrom('thisdomain24@gmail.com', 'GameEarn');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Welcome to GameEarn! Your Account Details";
    $mail->Body = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Welcome to GameEarn</title>
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
            .email-header {
                background: linear-gradient(135deg, #0b0e1a 0%, #1a1f30 100%);
                padding: 30px;
                text-align: center;
            }
            .email-header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
                background: linear-gradient(145deg, #f5b342, #ffdd88);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            .email-body {
                padding: 30px;
                line-height: 1.6;
            }
            .email-body h2 {
                font-size: 22px;
                color: #1e40af;
                margin-top: 0;
                font-weight: 600;
            }
            .details-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                background: #f1f5f9;
                border-radius: 8px;
                overflow: hidden;
            }
            .details-table td {
                padding: 12px 16px;
                border-bottom: 1px solid #e2e8f0;
            }
            .details-table td:first-child {
                font-weight: 600;
                width: 40%;
                background: #e9eef3;
            }
            .action-button {
                display: inline-block;
                background: linear-gradient(135deg, #f5b342 0%, #ffdd88 100%);
                color: #0b0e1a;
                text-decoration: none;
                padding: 12px 30px;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                margin: 20px 0;
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            .footer {
                text-align: center;
                background: #f1f5f9;
                padding: 20px;
                font-size: 14px;
                color: #64748b;
                border-top: 1px solid #e2e8f0;
            }
            .footer a {
                color: #f5b342;
                text-decoration: none;
                font-weight: 500;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='email-header'>
                <h1>GameEarn</h1>
            </div>
            <div class='email-body'>
                <h2>Welcome to GameEarn!</h2>
                <p>Hello $email,</p>
                <p>Thank you for joining GameEarn! Your account has been successfully created. Below are your login credentials:</p>
                
                <table class='details-table'>
                     <tr><td>Account Number</td><td><strong>$accNumber</strong></td></tr>
                     <tr><td>Username</td><td><strong>$username</strong></td></tr>
                     <tr><td>Referral Code</td><td><strong>$myReferral</strong></td></tr>
                </table>
                
                <p>You can log in using your <strong>Account Number</strong> or <strong>Email</strong> together with the password you set during registration.</p>
                
                <center>
                    <a href='http://localhost/New_Project_2026/login.php' class='action-button'>Go to Login</a>
                </center>
                
                <p>If you didn't request this, please ignore this email or contact support if you have questions.</p>
            </div>
            <div class='footer'>
                <p>&copy; 2025 GameEarn. All rights reserved.</p>
                <p><a href='#'>Privacy Policy</a> | <a href='#'>Terms of Service</a></p>
                <p style='font-size: 12px; margin-top: 10px;'>This email was sent to $email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return $mail->send();
}


?>