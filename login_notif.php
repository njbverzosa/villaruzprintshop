<?php

function sendCustomerLoginNotification($toDev, $customerName, $customerEmail, $customerAccNumber, $customerPhone, $loginTime)
{
    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    $mail->Username = 'villaruzprintshop@gmail.com';
    $mail->Password = 'ydyu zfbg onec qmmu';
    $mail->setFrom('villaruzprintshop@gmail.com', 'Villaruz Print Shop');
    $mail->addAddress($toDev);

    $mail->isHTML(true);
    $mail->Subject = "Customer Login Notification - Villaruz Print Shop";
    $mail->Body = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Customer Login Notification</title>
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
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                padding: 30px;
                text-align: center;
                color: white;
            }
            
            .email-header h2 {
                margin: 0;
                font-size: 24px;
            }
            
            .email-body {
                padding: 30px;
                line-height: 1.6;
            }
            
            .login-details {
                background: #f1f5f9;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            
            .login-details p {
                margin: 8px 0;
            }
            
            .login-details strong {
                color: #059669;
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
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='email-header'>
                <h2>🛍️ Customer Login Notification</h2>
            </div>
            <div class='email-body'>
                <p>A customer has successfully logged in to the Villaruz Print Shop system.</p>
                
                <div class='login-details'>
                    <p><strong>👤 Customer Name:</strong> " . htmlspecialchars($customerName) . "</p>
                    <p><strong>📧 Email:</strong> " . htmlspecialchars($customerEmail) . "</p>
                    <p><strong>🆔 Account Number:</strong> " . htmlspecialchars($customerAccNumber) . "</p>
                    <p><strong>📱 Phone Number:</strong> " . htmlspecialchars($customerPhone) . "</p>
                    <p><strong>⏰ Login Time:</strong> " . htmlspecialchars($loginTime) . "</p>
                </div>
                
                <div class='divider'></div>
                
                <p>This is an automated notification. No action is required.</p>
            </div>
            <div class='footer'>
                <p style='font-size: 12px; margin-top: 10px;'>
                    This email is computer generated. Do not reply | Villaruz Print Shop & General Merchandise
                </p>
                <p style='font-size: 11px; color: #94a3b8;'>
                    📧 Need help? Email us at villaruzprintshop@gmail.com
                </p>
            </div>
        </div>
    </body>
    </html>
    ";

    return $mail->send();
}
