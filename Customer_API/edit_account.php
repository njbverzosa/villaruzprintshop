<?php
// Customer_API/edit_account.php
session_start();
require_once __DIR__ . '/../DB_Conn/config.php';
require_once __DIR__ . '/../Mail/PHPMailerAutoload.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$action = $_POST['action'] ?? '';
$accNumber = $_POST['acc_number'] ?? '';

if (empty($accNumber)) {
    echo json_encode(['success' => false, 'message' => 'Account number is required']);
    exit();
}

function sendVerificationEmail($email, $fullname, $otp)
{
    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->Port = 587;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        $mail->Username = 'villaruzprintshop@gmail.com';
        $mail->Password = 'ydyu zfbg onec qmmu';
        $mail->setFrom('villaruzprintshop@gmail.com', '<no reply>');
        $mail->addAddress($email);
        $mail->addReplyTo('villaruzprintshop@gmail.com', '<no reply>');

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->Timeout = 30;

        $mail->isHTML(true);
        $mail->Subject = "Verify Your Email - Villaruz Print Shop";
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Email Verification</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 500px; margin: 40px auto; background: #ffffff; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h2 { color: #1e40af; text-align: center; }
                .otp { font-size: 32px; font-weight: bold; text-align: center; padding: 15px; background: #eff6ff; border-radius: 8px; margin: 20px 0; letter-spacing: 5px; color: #1e40af; }
                .btn { display: inline-block; background: #2563eb; color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-size: 16px; font-weight: 500; margin: 15px 0; border: none; cursor: pointer; }
                .btn:hover { background: #1d4ed8; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
                .center { text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <p>Hello <strong>" . htmlspecialchars($fullname) . "</strong>,</p>
                <p>Your email address has been updated. Please verify your new email using the button below:</p>
                
                <div class='center'>
                    <a href='https://villaruz-print-shop-and-general-merchandise.shop/user.php?otp=" . urlencode($otp) . "&email=" . urlencode($email) . "' class='btn'>✓ Verify Email Address</a>
                </div>
                                
                <div class='footer'>
                    <p>© 2026 Villaruz Print Shop & General Merchandise</p>
                </div>
            </div>
        </body>
        </html>
        ";

        if ($mail->send()) {
            return true;
        } else {
            error_log("Email send failed: " . $mail->ErrorInfo);
            return false;
        }
    } catch (Exception $e) {
        error_log("Email sending failed for $email: " . $e->getMessage());
        return false;
    }
}

try {
    // Ensure columns exist
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'otp_code'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN otp_code VARCHAR(10) NULL");
        }
    } catch (PDOException $e) {
        error_log("Column check error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'active_email'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN active_email TINYINT(1) DEFAULT 0");
        }
    } catch (PDOException $e) {
        error_log("Column check error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'street'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN street VARCHAR(255) NULL");
        }
    } catch (PDOException $e) {
        error_log("Column check error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'barangay'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN barangay VARCHAR(100) NULL");
        }
    } catch (PDOException $e) {
        error_log("Column check error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'land_mark'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN land_mark VARCHAR(255) NULL");
        }
    } catch (PDOException $e) {
        error_log("Column check error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'landmark_photo'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN landmark_photo VARCHAR(500) NULL");
        }
    } catch (PDOException $e) {
        error_log("Column check error: " . $e->getMessage());
    }

    // Get current user data
    $stmt = $pdo->prepare("SELECT f_name, email FROM customers WHERE acc_number = ?");
    $stmt->execute([$accNumber]);
    $currentUserData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUserData) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    switch ($action) {
        // ============================================================
        // UPDATE ALL FIELDS - NEW ACTION FOR FORM SUBMISSION
        // ============================================================
        case 'update_all_fields':
            // Get all fields from POST
            $f_name = trim($_POST['f_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $street = trim($_POST['street'] ?? '');
            $barangay = trim($_POST['barangay'] ?? '');
            
            // Validate required fields
            if (empty($f_name)) {
                echo json_encode(['success' => false, 'message' => 'Full name is required']);
                exit();
            }
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Valid email is required']);
                exit();
            }
            
            if (empty($street)) {
                echo json_encode(['success' => false, 'message' => 'Street is required']);
                exit();
            }
            
            if (empty($barangay)) {
                echo json_encode(['success' => false, 'message' => 'Barangay is required']);
                exit();
            }

            // Check if email changed
            $emailChanged = ($email !== $currentUserData['email']);
            $oldEmail = $currentUserData['email'];

            // Start transaction
            $pdo->beginTransaction();

            try {
                // Update all fields
                $stmt = $pdo->prepare("UPDATE customers SET f_name = ?, email = ?, street = ?, barangay = ? WHERE acc_number = ?");
                $result = $stmt->execute([$f_name, $email, $street, $barangay, $accNumber]);

                if (!$result) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Failed to update account']);
                    exit();
                }

                // Update session
                $_SESSION['user']['f_name'] = $f_name;
                $_SESSION['user']['email'] = $email;

                // Handle landmark photo if uploaded
                $photoUploaded = false;
                $photoPath = null;

                if (isset($_FILES['landmark_photo']) && $_FILES['landmark_photo']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['landmark_photo'];
                    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png'];

                    if (in_array($fileExt, $allowedExtensions) && $file['size'] <= 5 * 1024 * 1024) {
                        // Create upload directory
                        $uploadDir = __DIR__ . '/../Landmark_Photos/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

                        // Generate unique filename
                        $newFileName = time() . '_' . $accNumber . '.' . $fileExt;
                        $uploadPath = $uploadDir . $newFileName;
                        $dbPath = 'Landmark_Photos/' . $newFileName;

                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            // Delete old landmark photo if exists
                            if (!empty($currentUserData['landmark_photo'])) {
                                $oldFilePath = __DIR__ . '/../' . $currentUserData['landmark_photo'];
                                if (file_exists($oldFilePath)) {
                                    unlink($oldFilePath);
                                }
                            }

                            // Update database with new photo
                            $photoStmt = $pdo->prepare("UPDATE customers SET landmark_photo = ? WHERE acc_number = ?");
                            $photoStmt->execute([$dbPath, $accNumber]);
                            $photoUploaded = true;
                            $photoPath = $dbPath;
                        }
                    }
                }

                // Commit transaction
                $pdo->commit();

                // If email changed, send verification email
                if ($emailChanged) {
                    $otp = mt_rand(100000, 999999);
                    $otpStmt = $pdo->prepare("UPDATE customers SET otp_code = ?, active_email = 0 WHERE acc_number = ?");
                    $otpStmt->execute([$otp, $accNumber]);

                    $_SESSION['verification_otp'] = $otp;
                    $_SESSION['verification_email'] = $email;
                    $_SESSION['verification_expires'] = time() + 600;

                    $emailSent = sendVerificationEmail(
                        $email,
                        $f_name,
                        $otp
                    );

                    $message = 'Account updated successfully!';
                    if ($emailSent) {
                        $message .= ' Verification email sent to ' . $email;
                    } else {
                        $message .= ' Please verify your email to activate your account.';
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => $message,
                        'email_changed' => true,
                        'email_sent' => $emailSent,
                        'photo_uploaded' => $photoUploaded
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Account updated successfully!',
                        'email_changed' => false,
                        'photo_uploaded' => $photoUploaded
                    ]);
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Update all fields error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        // ============================================================
        // UPDATE SINGLE FIELD (kept for backward compatibility)
        // ============================================================
        case 'update_field':
            $field = $_POST['field'] ?? '';
            $value = $_POST['value'] ?? '';

            $allowedFields = ['f_name', 'email', 'street', 'barangay', 'land_mark'];

            if (!in_array($field, $allowedFields)) {
                echo json_encode(['success' => false, 'message' => 'Invalid field']);
                exit();
            }

            if (empty($value)) {
                echo json_encode(['success' => false, 'message' => 'Value cannot be empty']);
                exit();
            }

            $value = trim($value);

            if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                exit();
            }

            // Update the field
            $stmt = $pdo->prepare("UPDATE customers SET $field = ? WHERE acc_number = ?");
            $result = $stmt->execute([$value, $accNumber]);

            if ($result) {
                // Update session
                $_SESSION['user'][$field] = $value;

                // If email was changed, send verification email
                if ($field === 'email') {
                    $otp = mt_rand(100000, 999999);

                    $stmt = $pdo->prepare("UPDATE customers SET otp_code = ?, active_email = 0 WHERE acc_number = ?");
                    $stmt->execute([$otp, $accNumber]);

                    $_SESSION['verification_otp'] = $otp;
                    $_SESSION['verification_email'] = $value;
                    $_SESSION['verification_expires'] = time() + 600;

                    $emailSent = sendVerificationEmail(
                        $value,
                        $currentUserData['f_name'],
                        $otp
                    );

                    if ($emailSent) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Email updated successfully. Verification email sent to ' . $value,
                            'field' => $field,
                            'value' => $value,
                            'email_sent' => true
                        ]);
                    } else {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Email updated but verification email failed to send.',
                            'field' => $field,
                            'value' => $value,
                            'email_sent' => false
                        ]);
                    }
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Field updated successfully',
                        'field' => $field,
                        'value' => $value
                    ]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update field']);
            }
            break;

        // ============================================================
        // UPLOAD LANDMARK PHOTO
        // ============================================================
        case 'upload_landmark_photo':
            // Check if file was uploaded
            if (!isset($_FILES['landmark_photo']) || $_FILES['landmark_photo']['error'] !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                ];
                $errorCode = $_FILES['landmark_photo']['error'] ?? UPLOAD_ERR_NO_FILE;
                $errorMsg = $errorMessages[$errorCode] ?? 'Unknown upload error';
                echo json_encode(['success' => false, 'message' => $errorMsg]);
                exit();
            }

            $file = $_FILES['landmark_photo'];
            $fileName = $file['name'];
            $fileTmp = $file['tmp_name'];
            $fileSize = $file['size'];

            // Get file extension
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Allowed extensions
            $allowedExtensions = ['jpg', 'jpeg', 'png'];

            // Validate file extension
            if (!in_array($fileExt, $allowedExtensions)) {
                echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, and PNG files are allowed']);
                exit();
            }

            // Validate file size (max 5MB)
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            if ($fileSize > $maxFileSize) {
                echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
                exit();
            }

            // Validate file is actually an image
            if (!getimagesize($fileTmp)) {
                echo json_encode(['success' => false, 'message' => 'Uploaded file is not a valid image']);
                exit();
            }

            try {
                // Get user data to check for existing photo
                $stmt = $pdo->prepare("SELECT landmark_photo FROM customers WHERE acc_number = ?");
                $stmt->execute([$accNumber]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);

                // Create upload directory if it doesn't exist
                $uploadDir = __DIR__ . '/../Landmark_Photos/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Generate unique filename
                $newFileName = time() . '_' . $accNumber . '.' . $fileExt;
                $uploadPath = $uploadDir . $newFileName;
                $dbPath = 'Landmark_Photos/' . $newFileName;

                // Move uploaded file
                if (move_uploaded_file($fileTmp, $uploadPath)) {
                    // Delete old landmark photo if exists
                    if (!empty($userData['landmark_photo'])) {
                        $oldFilePath = __DIR__ . '/../' . $userData['landmark_photo'];
                        if (file_exists($oldFilePath)) {
                            unlink($oldFilePath);
                        }
                    }

                    // Update database with the new photo path
                    $updateStmt = $pdo->prepare("UPDATE customers SET landmark_photo = ? WHERE acc_number = ?");
                    $updateStmt->execute([$dbPath, $accNumber]);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Landmark photo uploaded successfully!',
                        'photo_path' => $dbPath,
                        'photo_url' => '../' . $dbPath
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        // ============================================================
        // DELETE LANDMARK PHOTO
        // ============================================================
        case 'delete_landmark_photo':
            try {
                // Get current landmark photo path
                $stmt = $pdo->prepare("SELECT landmark_photo FROM customers WHERE acc_number = ?");
                $stmt->execute([$accNumber]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($userData && !empty($userData['landmark_photo'])) {
                    // Delete file from server
                    $filePath = __DIR__ . '/../' . $userData['landmark_photo'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }

                    // Update database to NULL
                    $updateStmt = $pdo->prepare("UPDATE customers SET landmark_photo = NULL WHERE acc_number = ?");
                    $updateStmt->execute([$accNumber]);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Landmark photo removed successfully!'
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => 'No landmark photo to remove'
                    ]);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            break;
    }
} catch (PDOException $e) {
    error_log("Edit Account Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Edit Account General Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>