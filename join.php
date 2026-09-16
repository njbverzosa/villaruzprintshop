<?php
// join.php — plain HTML form POST, no JSON, no AJAX

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
    $f_name = trim($_POST['f_name'] ?? '');
    $business_name = trim($_POST['business_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $password = $_POST['password'] ?? '';

    /* 3. Validate */
    if ($f_name === '')
        $errors[] = 'Full name is required.';
    if ($business_name === '')
        $errors[] = 'Business name is required.';

    if ($phone_number === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s()]{7,15}$/', $phone_number)) {
        $errors[] = 'Phone number format is invalid.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    /* 4. File upload */
    $file = $_FILES['business_permit'] ?? null;

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Business permit photo is required.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed (code ' . $file['error'] . ').';
    } else {
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'File is too large. Max 5MB.';
        }

        $allowedMime = ['image/png', 'image/jpeg', 'image/jpg'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMime, true)) {
            $errors[] = 'Only PNG, JPG, or JPEG images are allowed.';
        }
    }

    /* 5. Account number + username */
    $acc_number = null;
    $user_name = '';
    if (empty($errors)) {
        $digits = preg_replace('/\D/', '', $phone_number);
        $acc_number = substr($digits, -4);

        // First word of f_name → user_name
        $nameParts = preg_split('/\s+/', $f_name);
        $user_name = $nameParts[0] ?? '';
    }

    /* 6. Move uploaded file */
    $permitPath = null;
    if (empty($errors)) {
        $uploadDir = __DIR__ . '/Business_Docs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = $acc_number . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $errors[] = 'Could not save the uploaded file. Check that Business_Docs is writable.';
        } else {
            $permitPath = $fileName;
        }
    }

    /* 7. Insert into admins + hash password */
    if (empty($errors)) {
        date_default_timezone_set('Asia/Manila');
        $registeredAt = date('d F Y');
        $profile = 'profile.jpg';
        $authorizeAccess = 3; 

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $sql = "INSERT INTO investors
                    (registered_at, acc_number, f_name, user_name, phone_number,
                     password, text_pass, authorize_access, profile,
                     business_name, business_permit)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $registeredAt,
                $acc_number,
                $f_name,
                $user_name,
                $phone_number,
                $hashedPassword,    // ✅ hashed password
                $password,          // plain text for admin reference (optional)
                $authorizeAccess,   // 3 → Investor
                $profile,
                $business_name,
                $permitPath
            ]);

            // ✅ Redirect to login.php after success
            header('Location: login.php');
            exit;

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Segoe UI', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #eff6ff 0%, #f1f5f9 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 40px 20px;
            color: #1e293b;
        }

        .reg-card {
            background: #fff;
            width: 100%;
            max-width: 560px;
            border-radius: 16px;
            padding: 36px 34px 30px 34px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
        }

        .reg-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .reg-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #0b1e2e;
            margin-bottom: 6px;
        }

        .reg-header p {
            color: #64748b;
            font-size: 0.92rem;
        }

        .section-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #3b82f6;
            margin: 22px 0 16px 0;
        }

        .section-label:first-of-type {
            margin-top: 0;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

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
            color: #3b82f6;
            margin-right: 4px;
            font-size: 0.82rem;
        }

        .form-group input {
            width: 100%;
            padding: 13px 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            color: #1e293b;
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .form-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
            background: #fff;
        }

        .form-group input::placeholder {
            color: #94a3b8;
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            padding-right: 46px;
        }

        .password-wrapper .toggle-eye {
            position: absolute;
            right: 15px;
            cursor: pointer;
            color: #94a3b8;
            font-size: 1rem;
            transition: color 0.2s;
        }

        .password-wrapper .toggle-eye:hover {
            color: #3b82f6;
        }

        .password-hint {
            font-size: 0.72rem;
            color: #94a3b8;
            margin-top: 6px;
            line-height: 1.5;
        }

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
            border-color: #3b82f6;
            background: #eff6ff;
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
            background: #dbeafe;
            color: #1d4ed8;
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

        .preview-wrap.visible {
            display: block;
        }

        .preview-wrap img {
            max-width: 100%;
            max-height: 180px;
            border-radius: 8px;
            object-fit: contain;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(145deg, #3b82f6, #6366f1);
            border: none;
            padding: 15px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            color: #fff;
            cursor: pointer;
            margin-top: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: inherit;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.35);
        }

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

        .alert ul {
            margin-left: 18px;
        }

        @media (min-width: 900px) {
            .reg-card {
                max-width: 980px;
                padding: 44px 52px 38px 52px;
            }

            .form-grid {
                grid-template-columns: 1fr 1fr;
                gap: 20px 26px;
            }

            .form-grid .full-width {
                grid-column: 1 / -1;
            }

            .btn-submit {
                max-width: 320px;
                margin: 24px auto 0 auto;
            }
        }
    </style>
</head>

<body>

    <form class="reg-card" method="POST" enctype="multipart/form-data">
        <div class="reg-header">
            <h1>Become an Investor Partner</h1>
            <p>Register your business to start investing with us</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><strong>Registration successful!</strong><br>You can now log in.</div>
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
            <div class="form-group">
                <label for="phone_number"><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" name="phone_number" id="phone_number" placeholder="e.g. 09XXXXXXXXX"
                    pattern="[0-9+\-\s()]{7,15}" required>
                <div class="password-hint">Last 4 digits will be your account number.</div>
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-key"></i> Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" placeholder="Create a password" minlength="5"
                        required>
                    <i class="fas fa-eye-slash toggle-eye" id="togglePassword"></i>
                </div>
                <div class="password-hint">Minimum 5 characters.</div>
            </div>
        </div>

        <button type="submit" class="btn-submit">
            <i class="fas fa-check-circle"></i>
            <span>Submit Registration</span>
        </button>
    </form>

    <script>
        /* Password toggle */
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        togglePassword.addEventListener('click', function () {
            const isPwd = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPwd ? 'text' : 'password');
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        /* File preview */
        const fileInput = document.getElementById('business_permit');
        const fileName = document.getElementById('fileName');
        const previewWrap = document.getElementById('previewWrap');
        const previewImg = document.getElementById('previewImg');

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
    </script>

</body>

</html>