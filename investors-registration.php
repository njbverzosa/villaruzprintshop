<?php
// join.php — plain HTML form POST, no JSON, no AJAX
require_once __DIR__ . '/bots.php';
session_start();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* 1. Confirm config.php exists */
    $configPath = __DIR__ . '/DB_Conn/config.php';
    if (!file_exists($configPath)) {
        $errors[] = 'config.php NOT FOUND at: ' . $configPath;
    } else {
        require_once $configPath;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            $errors[] = 'config.php must define a variable named $pdo';
        }
    }

    /* 2. Collect inputs */
    $f_name        = trim($_POST['f_name'] ?? '');
    $business_name = trim($_POST['business_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone_number  = trim($_POST['phone_number'] ?? '');
    $password      = $_POST['password'] ?? '';

    /* 3. Validate text inputs */
    if ($f_name === '')
        $errors[] = 'Full name is required.';
    if ($business_name === '')
        $errors[] = 'Business name is required.';

    /* ✅ Email validation — required for OTP delivery */
    if ($email === '') {
        $errors[] = 'Email is required. Your email must receive the OTP code.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($phone_number === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s()]{7,15}$/', $phone_number)) {
        $errors[] = 'Phone number format is invalid.';
    }

    /* ✅ PIN validation — exactly 6 digits */
    if ($password === '') {
        $errors[] = 'PIN is required.';
    } elseif (!preg_match('/^[0-9]{6}$/', $password)) {
        $errors[] = 'PIN must be exactly 6 digits.';
    }

    /* 4. File upload validation */
    $file = $_FILES['business_permit'] ?? null;

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Business permit photo is required.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in the form.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload stopped by a PHP extension.',
        ];
        $errors[] = $uploadErrors[$file['error']] ?? 'File upload failed (code ' . $file['error'] . ').';
    } else {
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'File is too large. Max 5MB.';
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['png', 'jpg', 'jpeg'];

        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = 'Only PNG, JPG, or JPEG images are allowed.';
        }

        if (empty($errors)) {
            if (!extension_loaded('fileinfo')) {
                $errors[] = 'Server misconfiguration: fileinfo extension is not enabled.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowedMime = ['image/png', 'image/jpeg', 'image/pjpeg'];

                if (!in_array($mime, $allowedMime, true)) {
                    $errors[] = 'Invalid image type detected (' . htmlspecialchars($mime) . '). Only PNG, JPG, or JPEG images are allowed.';
                }
            }
        }
    }

    /* 5. Account number + username */
    $acc_number = null;
    $user_name  = '';
    if (empty($errors)) {
        $digits     = preg_replace('/\D/', '', $phone_number);
        $acc_number = substr($digits, -4);

        /* ✅ user_name = first word of f_name */
        $nameParts = preg_split('/\s+/', trim($f_name), -1, PREG_SPLIT_NO_EMPTY);
        $user_name = $nameParts[0] ?? '';

        // Keep only safe characters (letters, numbers, underscore, hyphen)
        $user_name = preg_replace('/[^A-Za-z0-9_\-]/', '', $user_name);

        // Lowercase for consistency
        $user_name = strtolower($user_name);

        // Fallback if it somehow becomes empty
        if ($user_name === '') {
            $user_name = 'user';
        }
    }

    /* 6. Move uploaded file */
    $permitPath = null;
    if (empty($errors)) {
        $uploadDir = __DIR__ . '/Business_Docs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = $acc_number . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $errors[] = 'Could not save the uploaded file. Check that Business_Docs is writable.';
        } else {
            $permitPath = $fileName;
        }
    }

    /* 7. Insert into customers + hash PIN */
    if (empty($errors)) {
        date_default_timezone_set('Asia/Manila');
        $registeredAt    = date('d F Y');
        $profile         = 'profile.jpg';
        $authorizeAccess = 3;

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $sql = "INSERT INTO investors
                    (registered_at, acc_number, f_name, user_name, email, phone_number,
                     password, text_pass, authorize_access, profile,
                     business_name, business_permit)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $registeredAt,
                $acc_number,
                $f_name,
                $user_name,
                $email,
                $phone_number,
                $hashedPassword,
                $password,
                $authorizeAccess,
                $profile,
                $business_name,
                $permitPath
            ]);

            // ✅ Mark success — do NOT redirect here.
            // We'll show the success message, then redirect with JS.
            $success = true;

        } catch (PDOException $e) {
            $errors[] = 'Database insert failed: ' . $e->getMessage();
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
    <title>Business Registration | Villaruz Print Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            height: 100%;
            overflow-x: hidden;
        }

        body {
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
        }

        /* ============================================
           LEFT BRAND PANEL (desktop only)
           ============================================ */
        .brand-panel {
            display: none;
        }

        /* ============================================
           RIGHT FORM PANEL
           ============================================ */
        .form-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            background: #ffffff;
            min-height: 100dvh;
            position: relative;
        }

        .auth-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 28px 22px 32px 22px;
            width: 100%;
        }

        .auth-card {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
            position: relative;
        }

        /* ========== LOGO (borderless — no radius, no bg, no padding) ========== */
        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }
        .logo-stack .main-logo {
            width: 130px;
            height: 130px;
            object-fit: contain;
            display: block;
        }

        /* ========== PORTAL HEADER ========== */
        .portal-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .portal-header::before {
            content: "";
            width: 5px;
            height: 34px;
            border-radius: 3px;
            background: linear-gradient(135deg, #1a2a6c 0%, #3b6fd4 100%);
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(26, 42, 108, 0.35);
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.2;
            margin: 0;
            background: linear-gradient(135deg, #1a2a6c 0%, #3b6fd4 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }
        .portal-subtitle {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 26px;
            padding-left: 17px;
            font-weight: 400;
        }

        /* ========== SECTION LABEL ========== */
        .section-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #1a2a6c;
            margin: 22px 0 16px 0;
        }
        .section-label:first-of-type { margin-top: 0; }
        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }
        .section-label i { font-size: 0.8rem; }

        /* ========== FORM GRID ========== */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #475569;
            font-size: 0.85rem;
        }
        .form-group label i {
            color: #1a2a6c;
            margin-right: 4px;
            font-size: 0.82rem;
        }

        .form-group input {
            width: 100%;
            height: 54px;
            padding: 0 18px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            color: #1e293b;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            outline: none;
            transition: 0.25s ease;
            -webkit-appearance: none;
            appearance: none;
            display: block;
        }
        .form-group input:focus {
            border-color: #1a2a6c;
            box-shadow: 0 0 0 4px rgba(26, 42, 108, 0.1);
            background: #ffffff;
        }
        .form-group input::placeholder { color: #94a3b8; }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-wrapper input {
            flex: 1;
            padding-right: 48px;
        }
        .password-wrapper .toggle-eye {
            position: absolute;
            right: 16px;
            cursor: pointer;
            color: #94a3b8;
            font-size: 18px;
            transition: color 0.3s;
        }
        .password-wrapper .toggle-eye:hover { color: #1a2a6c; }

        .password-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 6px;
            padding: 5px 12px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #1a2a6c;
            display: flex;
            align-items: center;
            gap: 6px;
            line-height: 1.4;
        }

        /* ✅ Highlighted OTP note */
        .otp-hint {
            font-size: 12px;
            color: #1a2a6c;
            margin-top: 6px;
            padding: 5px 12px;
            background: #eef2fb;
            border-radius: 8px;
            border-left: 3px solid #1a2a6c;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            line-height: 1.4;
        }
        .otp-hint i {
            color: #1a2a6c;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        /* ========== FILE UPLOAD ========== */
        .file-upload {
            position: relative;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
        }
        .file-upload:hover {
            border-color: #1a2a6c;
            background: #eef2fb;
        }
        .file-upload input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        .file-upload .upload-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: #e3eafa;
            color: #1a2a6c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .file-upload .upload-text {
            flex: 1;
            min-width: 0;
        }
        .file-upload .upload-text strong {
            display: block;
            font-size: 0.88rem;
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .file-upload .upload-text span {
            font-size: 0.78rem;
            color: #64748b;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .preview-wrap {
            display: none;
            margin-top: 12px;
            padding: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            text-align: center;
        }
        .preview-wrap.visible { display: block; }
        .preview-wrap img {
            max-width: 100%;
            max-height: 180px;
            border-radius: 8px;
            object-fit: contain;
        }

        /* ========== ACTION AREA ========== */
        .form-actions {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 24px;
        }

        .btn-submit {
            flex: 1;
            height: 54px;
            background: linear-gradient(145deg, #1a2a6c, #3b6fd4);
            border: none;
            padding: 0 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            color: #fff;
            cursor: pointer;
            transition: 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'Poppins', sans-serif;
            white-space: nowrap;
            box-shadow: 0 6px 18px rgba(26, 42, 108, 0.25);
        }
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(26, 42, 108, 0.35);
        }
        .btn-submit:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(26, 42, 108, 0.25);
        }

        .login-footer {
            font-size: 0.9rem;
            color: #64748b;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
        }
        .login-footer a {
            color: #1a2a6c;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.2s;
            margin-left: 4px;
        }
        .login-footer a:hover {
            color: #3b6fd4;
            text-decoration: underline;
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.88rem;
            display: flex;
            gap: 10px;
            line-height: 1.5;
        }
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .alert i {
            font-size: 1.05rem;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .alert ul { margin-left: 18px; }

        /* ============================================
           DESKTOP — SPLIT SCREEN
           ============================================ */
        @media (min-width: 900px) {
            body {
                background: #f4f6fb;
                padding: 0;
                min-height: 100vh;
            }

            .brand-panel {
                display: flex;
                flex: 1;
                background: linear-gradient(135deg, #1a2a6c 0%, #2a4d9b 60%, #3b6fd4 100%);
                color: #fff;
                flex-direction: column;
                justify-content: center;
                padding: 60px;
                position: relative;
                overflow: hidden;
                min-height: 100vh;
            }

            .brand-panel::before,
            .brand-panel::after {
                content: "";
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.06);
            }
            .brand-panel::before { width: 400px; height: 400px; top: -120px; right: -120px; }
            .brand-panel::after  { width: 300px; height: 300px; bottom: -100px; left: -80px; }

            .brand-logo {
                display: flex;
                align-items: center;
                gap: 14px;
                margin-bottom: 48px;
                z-index: 1;
            }
            /* ✅ Brand logo — borderless */
            .brand-logo-img {
                width: 56px;
                height: 56px;
                object-fit: contain;
            }
            .brand-logo-text {
                font-size: 18px;
                font-weight: 700;
                letter-spacing: 0.5px;
                line-height: 1.3;
            }

            .brand-panel h1 {
                font-size: 40px;
                line-height: 1.2;
                margin-bottom: 16px;
                z-index: 1;
                background: none;
                -webkit-text-fill-color: #fff;
                color: #fff;
                font-weight: 800;
            }
            .brand-panel p {
                font-size: 17px;
                opacity: 0.85;
                max-width: 380px;
                line-height: 1.6;
                z-index: 1;
            }
            .brand-panel .motto {
                margin-top: 48px;
                font-style: italic;
                font-size: 15px;
                opacity: 0.85;
                border-left: 3px solid #f5b301;
                padding-left: 16px;
                z-index: 1;
            }

            .form-panel {
                flex: 1;
                background: #ffffff;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 40px;
                min-height: 100vh;
                overflow-y: auto;
            }

            .auth-container {
                padding: 0;
                width: 100%;
                max-width: 620px;
            }

            .auth-card {
                margin: 0;
                width: 100%;
                max-width: 100%;
            }

            .logo { display: none; } /* logo lives in brand panel on desktop */

            .portal-header { margin-top: 8px; }
            h1 { font-size: 28px; }
            .portal-subtitle { margin-bottom: 32px; }

            .form-grid {
                grid-template-columns: 1fr 1fr;
                gap: 20px 26px;
            }

            .form-grid .full-width {
                grid-column: 1 / -1;
            }
        }

        /* Tablet tweak (between mobile and desktop) */
        @media (min-width: 640px) and (max-width: 899px) {
            body {
                background: #f1f5f9;
                justify-content: center;
                align-items: center;
            }
            .form-panel {
                flex: none;
                min-height: auto;
                max-width: 600px;
                border-radius: 16px;
                box-shadow: 0 20px 45px rgba(0, 0, 0, 0.08);
                border: 1px solid #e2e8f0;
                margin: 24px auto;
                overflow: hidden;
            }
            .auth-container { padding: 40px; }
            .logo { margin-bottom: 24px; }
            .logo-stack .main-logo { width: 160px; height: 160px; }

            .form-grid {
                grid-template-columns: 1fr 1fr;
                gap: 18px 22px;
            }
            .form-grid .full-width { grid-column: 1 / -1; }
        }

        /* ========== MOBILE: stack button + link vertically ========== */
        @media (max-width: 640px) {
            .form-actions {
                flex-direction: column;
                gap: 14px;
            }
            .btn-submit { width: 100%; }
            .login-footer {
                justify-content: center;
                width: 100%;
            }
        }

        /* ========== SMALL PHONES ========== */
        @media (max-width: 380px) {
            .auth-container { padding: 22px 18px 26px 18px; }
            .logo { margin-bottom: 16px; }
            .logo-stack .main-logo { width: 115px; height: 115px; }
            h1 { font-size: 21px; }
            .portal-subtitle { font-size: 12px; }
            .form-group input { height: 50px; }
            .btn-submit { height: 50px; }
        }

        /* ========== SHORT SCREENS ========== */
        @media (max-height: 700px) {
            .auth-container { padding: 20px 22px 24px 22px; }
            .logo { margin-bottom: 14px; }
            .logo-stack .main-logo { width: 100px; height: 100px; }
            h1 { font-size: 21px; }
            .portal-subtitle { font-size: 12px; margin-bottom: 20px; }
            .form-group input { height: 50px; }
            .btn-submit { height: 50px; }
        }
    </style>
</head>

<body>

    <!-- LEFT: Brand panel (desktop only) -->
    <section class="brand-panel">
        <div class="brand-logo">
            <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/logo.jpeg"
                 alt="Villaruz Logo" class="brand-logo-img">
            <div class="brand-logo-text">VILLARUZ PRINT SHOP<br>&amp; GENERAL MERCHANDISE</div>
        </div>
        <h1>Become an Investor Partner.</h1>
        <p>Register your business and start investing with Villaruz Print Shop — track earnings, manage your products, and grow with us.</p>
        <div class="motto">"Precision in every print, care in every order."</div>
    </section>

    <!-- RIGHT: Form panel -->
    <section class="form-panel">
        <div class="auth-container">
            <div class="auth-card">

                <!-- Logo (mobile only — hidden on desktop) -->
                <div class="logo">
                    <div class="logo-stack">
                        <img src="https://villaruz-print-shop-and-general-merchandise.shop/logo/logo.jpeg"
                            alt="Villaruz Print Shop Logo" class="main-logo">
                    </div>
                </div>

                <div class="portal-header">
                    <h1>Become a Investors Partner</h1>
                </div>
                <p class="portal-subtitle">Register your business to start investing with us</p>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>Registration successful!</strong><br>
                            You can now use our app. Redirecting in <span id="countdown">10</span>s…
                        </div>
                    </div>
                <?php elseif (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <strong>Please fix the following:</strong>
                            <ul>
                                <?php foreach ($errors as $err): ?>
                                    <li><?= htmlspecialchars($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" autocomplete="off" id="joinForm">

                    <div class="section-label"><i class="fas fa-briefcase"></i> Business Information</div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="f_name"><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" name="f_name" id="f_name" placeholder="Enter your full name" required>
                        </div>

                        <div class="form-group">
                            <label for="business_name"><i class="fas fa-store"></i> Business Name</label>
                            <input type="text" name="business_name" id="business_name" placeholder="Enter your business name"
                                required>
                        </div>

                        <div class="form-group full-width">
                            <label><i class="fas fa-file-image"></i> Business Permit (Photo)</label>
                            <label class="file-upload" for="business_permit">
                                <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                <div class="upload-text">
                                    <strong>Upload an image of your business permit</strong>
                                    <span id="fileName">PNG, JPG, or JPEG — max 5MB</span>
                                </div>
                                <input type="file" name="business_permit" id="business_permit"
                                    accept="image/png, image/jpeg, image/jpg" required>
                            </label>
                            <div class="preview-wrap" id="previewWrap">
                                <img id="previewImg" src="" alt="Permit preview">
                            </div>
                        </div>
                    </div>

                    <div class="section-label"><i class="fas fa-lock"></i> Account Credentials</div>

                    <div class="form-grid">
                        <!-- ✅ Email field with OTP note -->
                        <div class="form-group full-width">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" name="email" id="email" placeholder="e.g. yourname@gmail.com"
                                autocomplete="email" required>
                            <div class="otp-hint">
                                <i class="fas fa-shield-alt"></i>
                                <span>Your email must receive the OTP code for password recovery.</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone_number"><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" name="phone_number" id="phone_number" placeholder="e.g. 09XXXXXXXXX"
                                pattern="[0-9+\-\s()]{7,15}" required>
                            <div class="password-hint">Last 4 digits will be your account number.</div>
                        </div>

                        <div class="form-group">
                            <label for="password"><i class="fas fa-key"></i> 6-Digit PIN</label>
                            <div class="password-wrapper">
                                <input type="tel" name="password" id="password" placeholder="Enter 6-digit PIN"
                                    maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                                    autocomplete="off" required>
                                <i class="fas fa-eye-slash toggle-eye" id="togglePassword"></i>
                            </div>
                            <div class="password-hint">Exactly 6 digits (numbers only).</div>
                        </div>
                    </div>

                    <!-- ✅ Button + link side-by-side (desktop) / stacked (mobile) -->
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-check-circle"></i>
                            <span>Submit Registration</span>
                        </button>

                        <div class="login-footer">
                            Already have an account? <a href="investors-portal.php">Login</a>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </section>

    <script>
        /* PIN toggle — switch between masked and visible */
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function () {
                const isPwd = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPwd ? 'text' : 'password');
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });

            /* ✅ Enforce numeric-only, 6-digit PIN */
            passwordInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        }

        /* File preview */
        const fileInput = document.getElementById('business_permit');
        const fileName = document.getElementById('fileName');
        const previewWrap = document.getElementById('previewWrap');
        const previewImg = document.getElementById('previewImg');

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                const file = this.files[0];
                if (!file) {
                    fileName.textContent = 'PNG, JPG, or JPEG — max 5MB';
                    previewWrap.classList.remove('visible');
                    return;
                }
                fileName.textContent = file.name;
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        previewImg.src = e.target.result;
                        previewWrap.classList.add('visible');
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>

    <?php if ($success): ?>
    <script>
        /* Hide the form fields so the success message stands out */
        document.querySelectorAll('.auth-card .section-label').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.auth-card .form-grid').forEach(el => el.style.display = 'none');
        const actions = document.querySelector('.auth-card .form-actions');
        if (actions) actions.style.display = 'none';

        /* Scroll to top so the alert is visible */
        window.scrollTo({ top: 0, behavior: 'smooth' });

        /* Countdown + redirect (10 seconds, 1 tick per second) */
        let seconds = 10;
        const el = document.getElementById('countdown');
        const timer = setInterval(() => {
            seconds--;
            if (el) el.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = 'investors-portal.php';
            }
        }, 1000);

        /* Fallback meta refresh for browsers without JS — matches the 10s countdown */
        const meta = document.createElement('meta');
        meta.httpEquiv = 'refresh';
        meta.content = '10;url=investors-portal.php';
        document.head.appendChild(meta);
    </script>
    <?php endif; ?>

</body>

</html>