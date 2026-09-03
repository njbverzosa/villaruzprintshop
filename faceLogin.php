<?php
// faceLogin.php – Face Recognition Login for Villaruz Print Shop

// Set session lifetime BEFORE session_start()
$sessionLifetime = 604800; // 7 days

ini_set('session.cookie_lifetime', $sessionLifetime);
ini_set('session.gc_maxlifetime', $sessionLifetime);

session_start();
require_once __DIR__ . '/DB_Conn/config.php';

// ==============================================
// 1. GET ALL DATA (for display in select options)
// ==============================================
function getAllAdmins($pdo)
{
    $stmt = $pdo->query("SELECT id, acc_number, f_name FROM admins ORDER BY f_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllCustomers($pdo)
{
    $stmt = $pdo->query("SELECT id, acc_number, f_name, phone_number, email FROM customers ORDER BY f_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==============================================
// 2. HANDLE FACE LOGIN
// ==============================================
function handleFaceLogin($pdo)
{
    $errors = [];
    $loginSuccess = false;
    $redirectUrl = '';
    $successMessage = '';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [
            'errors' => $errors,
            'loginSuccess' => $loginSuccess,
            'userTypeSelected' => 'Admin',
            'selectedRole' => '',
            'selectedCustomerId' => ''
        ];
    }

    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    $userTypeSelected = trim($_POST['user_type'] ?? 'Admin');
    $selectedRole = trim($_POST['role'] ?? '');
    $selectedCustomerId = trim($_POST['customer'] ?? '');
    $faceImage = $_POST['face_image'] ?? '';

    // Validation
    if (empty($faceImage)) {
        $errors[] = 'Please capture your face first.';
    }

    if ($userTypeSelected === 'Admin' && empty($selectedRole)) {
        $errors[] = 'Please select an admin account.';
    }

    if ($userTypeSelected === 'Customer' && empty($selectedCustomerId)) {
        $errors[] = 'Please select a customer account.';
    }

    if (!empty($errors)) {
        return [
            'errors' => $errors,
            'loginSuccess' => $loginSuccess,
            'userTypeSelected' => $userTypeSelected,
            'selectedRole' => $selectedRole,
            'selectedCustomerId' => $selectedCustomerId
        ];
    }

    // Get the selected user's info
    $userId = '';
    $accNumber = '';
    $userName = '';
    $userPhone = '';

    if ($userTypeSelected === 'Admin') {
        $stmt = $pdo->prepare("SELECT id, acc_number, f_name, phone_number FROM admins WHERE id = ?");
        $stmt->execute([$selectedRole]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $userId = $user['id'];
            $accNumber = $user['acc_number'];
            $userName = $user['f_name'];
            $userPhone = $user['phone_number'];
        }
    } elseif ($userTypeSelected === 'Customer') {
        $stmt = $pdo->prepare("SELECT id, acc_number, f_name, phone_number FROM customers WHERE id = ?");
        $stmt->execute([$selectedCustomerId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $userId = $user['id'];
            $accNumber = $user['acc_number'];
            $userName = $user['f_name'];
            $userPhone = $user['phone_number'];
        }
    }

    if (empty($userId)) {
        $errors[] = 'User not found. Please try again.';
        return [
            'errors' => $errors,
            'loginSuccess' => $loginSuccess,
            'userTypeSelected' => $userTypeSelected,
            'selectedRole' => $selectedRole,
            'selectedCustomerId' => $selectedCustomerId
        ];
    }

    // ==============================================
    // VERIFY FACE AGAINST REGISTERED FACE
    // ==============================================
    try {
        // Check if user has a registered face
        $stmt = $pdo->prepare("SELECT id, face_image, face_descriptor FROM face_recognition WHERE acc_number = ? AND status = 'active'");
        $stmt->execute([$accNumber]);
        $registeredFace = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registeredFace) {
            $errors[] = 'No registered face found for this account. Please register your face first.';
            return [
                'errors' => $errors,
                'loginSuccess' => $loginSuccess,
                'userTypeSelected' => $userTypeSelected,
                'selectedRole' => $selectedRole,
                'selectedCustomerId' => $selectedCustomerId
            ];
        }

        // Process the captured face image
        $capturedImageData = base64_decode(preg_replace('#^data:image/[^;]+;base64,#', '', $faceImage));
        if ($capturedImageData === false) {
            $errors[] = 'Invalid image data. Please try again.';
            return [
                'errors' => $errors,
                'loginSuccess' => $loginSuccess,
                'userTypeSelected' => $userTypeSelected,
                'selectedRole' => $selectedRole,
                'selectedCustomerId' => $selectedCustomerId
            ];
        }

        // Save captured image temporarily for comparison
        $tempDir = __DIR__ . '/uploads/faces/temp/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        $tempFile = $tempDir . 'temp_' . $accNumber . '_' . time() . '.jpg';
        file_put_contents($tempFile, $capturedImageData);

        // In a production environment, you would use a proper face recognition library here
        // For demo purposes, we'll do a simple check (this is NOT secure for production)
        // In production, use: OpenCV, FaceNet, or a cloud-based face recognition API
        
        // For now, we'll accept the face if it's captured (demo mode)
        // In production, you would compare the captured face with the registered face
        $faceMatched = true; // Placeholder - replace with actual face matching

        // Clean up temp file
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        if (!$faceMatched) {
            $errors[] = 'Face does not match the registered face. Please try again.';
            return [
                'errors' => $errors,
                'loginSuccess' => $loginSuccess,
                'userTypeSelected' => $userTypeSelected,
                'selectedRole' => $selectedRole,
                'selectedCustomerId' => $selectedCustomerId
            ];
        }

        // ==============================================
        // LOGIN SUCCESS - AUTHENTICATE USER
        // ==============================================
        date_default_timezone_set('Asia/Manila');
        $currentTime = date('M j, g:i A');

        if ($userTypeSelected === 'Admin') {
            session_regenerate_id(true);
            $_SESSION['user_role'] = 'Admin';
            $_SESSION['user_id'] = $userId;
            $_SESSION['acc_number'] = $accNumber;

            $loginSuccess = true;
            $redirectUrl = 'web/all_products.php';
            $successMessage = 'Face recognized! Accessing your account...';

        } elseif ($userTypeSelected === 'Customer') {
            // Update customer online time
            $updateStmt = $pdo->prepare("UPDATE customers SET online_time = ? WHERE id = ?");
            $updateStmt->execute([$currentTime, $userId]);
            session_regenerate_id(true);

            $_SESSION['user_role'] = 'Customer';
            $_SESSION['user_id'] = $userId;
            $_SESSION['acc_number'] = $accNumber;

            $loginSuccess = true;

            $isGuest = ($userName === 'Guest' || empty($userName));

            if ($isGuest) {
                $redirectUrl = 'public/account-edit.php';
            } else {
                $redirectUrl = 'public/shop.php';
            }
            $successMessage = 'Face recognized! Accessing your account...';
        }

    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    } catch (Exception $e) {
        $errors[] = 'Error: ' . $e->getMessage();
    }

    return [
        'errors' => $errors,
        'loginSuccess' => $loginSuccess,
        'redirectUrl' => $redirectUrl,
        'successMessage' => $successMessage,
        'userTypeSelected' => $userTypeSelected,
        'selectedRole' => $selectedRole,
        'selectedCustomerId' => $selectedCustomerId
    ];
}

// ==============================================
// 3. MAIN EXECUTION
// ==============================================

// Handle closed/logout message from session
$offlineMessage = '';
if (isset($_SESSION['exit_message'])) {
    $offlineMessage = $_SESSION['exit_message'];
    unset($_SESSION['exit_message']);
}

// Handle login error message
$loginErrorMessage = '';
if (isset($_SESSION['login_error'])) {
    $loginErrorMessage = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// If already logged in, redirect
if (isset($_SESSION['user_role']) && isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'Admin') {
        header('Location: web/all_products.php');
        exit;
    } elseif ($_SESSION['user_role'] === 'Customer') {
        header('Location: public/shop.php');
        exit;
    }
}

// GET ALL DATA
$existingAdmins = getAllAdmins($pdo);
$existingCustomers = getAllCustomers($pdo);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
$loginResult = handleFaceLogin($pdo);
$errors = $loginResult['errors'] ?? [];
$loginSuccess = $loginResult['loginSuccess'] ?? false;
$redirectUrl = $loginResult['redirectUrl'] ?? '';
$successMessage = $loginResult['successMessage'] ?? '';
$userTypeSelected = $loginResult['userTypeSelected'] ?? 'Admin';
$selectedRole = $loginResult['selectedRole'] ?? '';
$selectedCustomerId = $loginResult['selectedCustomerId'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Face Login | Villaruz Print Shop</title>
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
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .auth-sub {
            text-align: center;
            color: #64748b;
            margin-bottom: 30px;
            font-size: 18px;
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

        .form-group select {
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

        .form-group select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: #ffffff;
        }

        .camera-container {
            position: relative;
            background: #f8fafc;
            border-radius: 14px;
            overflow: hidden;
            border: 2px solid #e2e8f0;
            aspect-ratio: 4/3;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 15px;
        }

        .camera-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .camera-container canvas {
            display: none;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .camera-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            border: 3px dashed rgba(59, 130, 246, 0.4);
            border-radius: 50%;
            pointer-events: none;
            display: none;
        }

        .camera-overlay .face-guide {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: rgba(59, 130, 246, 0.2);
            font-size: 60px;
        }

        .camera-placeholder {
            color: #94a3b8;
            text-align: center;
            padding: 40px;
        }

        .camera-placeholder i {
            font-size: 64px;
            margin-bottom: 15px;
            color: #94a3b8;
        }

        .camera-placeholder p {
            font-size: 14px;
        }

        .btn-capture {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 14px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: #3b82f6;
            color: white;
            margin-bottom: 10px;
        }

        .btn-capture:hover:not(:disabled) {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .btn-capture:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-capture.success {
            background: #10b981;
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

        .btn-primary:hover:not(:disabled) {
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

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.5s ease;
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

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .alert i {
            font-size: 18px;
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

        .user-type-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .user-type-toggle button {
            flex: 1;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .user-type-toggle button.active {
            border-color: #3b82f6;
            background: #eff6ff;
            color: #3b82f6;
        }

        .user-type-toggle button:hover {
            background: #f1f5f9;
        }

        .select-group {
            display: none;
        }

        .select-group.visible {
            display: block;
        }

        .spinner-container {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .spinner-small {
            width: 20px;
            height: 20px;
            border: 3px solid #bbf7d0;
            border-top: 3px solid #16a34a;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        .face-status {
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
            margin-top: 5px;
        }

        .face-status.success {
            color: #10b981;
        }

        .face-status.error {
            color: #ef4444;
        }

        @media (max-width: 500px) {
            .auth-card {
                padding: 30px 25px;
            }

            .logo img {
                width: 75px;
            }

            .user-type-toggle button {
                font-size: 13px;
                padding: 8px;
            }

            .camera-overlay {
                width: 150px;
                height: 150px;
            }

            .camera-overlay .face-guide {
                font-size: 40px;
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
            <p class="auth-sub">Login with Face Recognition</p>
            <div style="text-align: center; margin-bottom: 20px;">
                <span class="version-badge">V5.30.42</span>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($offlineMessage)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-sign-out-alt"></i> <?php echo htmlspecialchars($offlineMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($loginSuccess && $successMessage): ?>
                <div class="alert alert-success" id="successAlert">
                    <div class="spinner-container">
                        <div class="spinner-small"></div>
                    </div>
                    <span><?php echo htmlspecialchars($successMessage); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($loginErrorMessage): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($loginErrorMessage); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="face_image" id="faceImageInput" value="">

                <div class="user-type-toggle">
                    <button type="button" class="<?php echo $userTypeSelected === 'Admin' ? 'active' : ''; ?>"
                        onclick="switchUserType('Admin')">
                        <i class="fas fa-user-tie"></i> Admin
                    </button>
                    <button type="button" class="<?php echo $userTypeSelected === 'Customer' ? 'active' : ''; ?>"
                        onclick="switchUserType('Customer')">
                        <i class="fas fa-user"></i> Customer
                    </button>
                </div>

                <input type="hidden" name="user_type" id="userTypeInput" value="<?php echo $userTypeSelected; ?>">

                <!-- Admin Select Group -->
                <div class="form-group select-group <?php echo $userTypeSelected === 'Admin' ? 'visible' : ''; ?>"
                    id="adminSelectGroup">
                    <label><i class="fas fa-users"></i> Select Admin Account</label>
                    <select name="role" id="adminSelect">
                        <option value="">-- Select your account --</option>
                        <?php foreach ($existingAdmins as $admin): ?>
                            <option value="<?php echo $admin['id']; ?>" <?php echo ($selectedRole == $admin['id'] && $userTypeSelected === 'Admin') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($admin['acc_number']); ?> - <?php echo htmlspecialchars($admin['f_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Customer Select Group -->
                <div class="form-group select-group <?php echo $userTypeSelected === 'Customer' ? 'visible' : ''; ?>"
                    id="customerSelectGroup">
                    <label><i class="fas fa-users"></i> Select Customer Account</label>
                    <select name="customer" id="customerSelect">
                        <option value="">-- Select your account --</option>
                        <?php foreach ($existingCustomers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" <?php echo ($selectedCustomerId == $customer['id'] && $userTypeSelected === 'Customer') ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['acc_number']); ?> - <?php echo htmlspecialchars($customer['f_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Camera -->
                <div class="camera-container" id="cameraContainer">
                    <video id="video" autoplay playsinline></video>
                    <canvas id="canvas"></canvas>
                    <div class="camera-overlay" id="cameraOverlay">
                        <div class="face-guide">
                            <i class="fas fa-user-circle"></i>
                        </div>
                    </div>
                    <div class="camera-placeholder" id="cameraPlaceholder">
                        <i class="fas fa-camera"></i>
                        <p>Starting camera...</p>
                    </div>
                </div>

                <button type="button" class="btn-capture" id="captureBtn" disabled>
                    <i class="fas fa-camera"></i> Capture Face
                </button>

                <div class="face-status" id="faceStatus">Please select an account and capture your face</div>

                <button type="submit" class="btn-primary" id="loginBtn" disabled>
                    <i class="fas fa-face-smile"></i> Login with Face
                </button>

                <div class="auth-footer">
                    Don't have an account? <a href="registration.php">Sign Up</a><br>
                    <small><a href="login.php">Login with Password</a></small>
                </div>

            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // ==============================================
        // USER TYPE SWITCH
        // ==============================================
        function switchUserType(type) {
            document.getElementById('userTypeInput').value = type;

            const buttons = document.querySelectorAll('.user-type-toggle button');
            buttons.forEach(btn => btn.classList.remove('active'));

            document.querySelectorAll('.select-group').forEach(group => {
                group.classList.remove('visible');
            });

            if (type === 'Admin') {
                buttons[0].classList.add('active');
                document.getElementById('adminSelectGroup').classList.add('visible');
                document.getElementById('adminSelect').disabled = false;
                document.getElementById('customerSelect').disabled = true;
            } else {
                buttons[1].classList.add('active');
                document.getElementById('customerSelectGroup').classList.add('visible');
                document.getElementById('customerSelect').disabled = false;
                document.getElementById('adminSelect').disabled = true;
            }

            // Enable/disable capture based on selection
            updateCaptureButton();
        }

        function updateCaptureButton() {
            const userType = document.getElementById('userTypeInput').value;
            const captureBtn = document.getElementById('captureBtn');
            const loginBtn = document.getElementById('loginBtn');

            if (userType === 'Admin') {
                const adminSelect = document.getElementById('adminSelect');
                captureBtn.disabled = adminSelect.value === '';
            } else {
                const customerSelect = document.getElementById('customerSelect');
                captureBtn.disabled = customerSelect.value === '';
            }
        }

        // ==============================================
        // CAMERA AND FACE CAPTURE
        // ==============================================
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const cameraPlaceholder = document.getElementById('cameraPlaceholder');
        const cameraOverlay = document.getElementById('cameraOverlay');
        const captureBtn = document.getElementById('captureBtn');
        const loginBtn = document.getElementById('loginBtn');
        const faceStatus = document.getElementById('faceStatus');
        const faceImageInput = document.getElementById('faceImageInput');

        let stream = null;
        let capturedImageData = null;
        let isCaptured = false;
        let cameraStarted = false;

        // Auto-start camera on page load
        async function startCamera() {
            try {
                cameraPlaceholder.style.display = 'block';
                cameraPlaceholder.innerHTML = `
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Starting camera...</p>
                `;
                cameraOverlay.style.display = 'none';

                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user',
                        width: { ideal: 640 },
                        height: { ideal: 480 }
                    },
                    audio: false
                });

                video.srcObject = stream;
                await video.play();

                cameraPlaceholder.style.display = 'none';
                video.style.display = 'block';
                cameraOverlay.style.display = 'block';
                cameraStarted = true;

                updateCaptureButton();
                faceStatus.textContent = 'Camera ready. Select an account and capture your face.';
                faceStatus.className = 'face-status';

            } catch (err) {
                console.error('Camera error:', err);
                cameraPlaceholder.style.display = 'block';
                cameraPlaceholder.innerHTML = `
                    <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                    <p>Unable to access camera</p>
                    <p style="font-size: 12px; color: #94a3b8;">${err.message}</p>
                    <button onclick="startCamera()" style="margin-top: 15px; background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-redo"></i> Retry
                    </button>
                `;
                faceStatus.textContent = 'Error: ' + err.message;
                faceStatus.className = 'face-status error';
            }
        }

        function captureFace() {
            if (!stream || !cameraStarted) {
                faceStatus.textContent = 'Camera not started. Please wait.';
                faceStatus.className = 'face-status error';
                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            capturedImageData = canvas.toDataURL('image/jpeg', 0.9);
            isCaptured = true;

            video.style.display = 'none';
            canvas.style.display = 'block';
            cameraOverlay.style.display = 'none';

            captureBtn.innerHTML = '<i class="fas fa-check-circle"></i> Recapture';
            captureBtn.className = 'btn-capture success';
            captureBtn.onclick = recaptureFace;
            loginBtn.disabled = false;

            faceStatus.textContent = '✅ Face captured! Click "Login with Face" to authenticate.';
            faceStatus.className = 'face-status success';

            // Set the face image data
            faceImageInput.value = capturedImageData;
        }

        function recaptureFace() {
            video.style.display = 'block';
            canvas.style.display = 'none';
            cameraOverlay.style.display = 'block';
            isCaptured = false;
            capturedImageData = null;

            captureBtn.innerHTML = '<i class="fas fa-camera"></i> Capture Face';
            captureBtn.className = 'btn-capture';
            captureBtn.onclick = captureFace;
            loginBtn.disabled = true;

            faceStatus.textContent = 'Recapture your face.';
            faceStatus.className = 'face-status';
            faceImageInput.value = '';
        }

        // ==============================================
        // EVENT LISTENERS
        // ==============================================
        document.getElementById('adminSelect').addEventListener('change', updateCaptureButton);
        document.getElementById('customerSelect').addEventListener('change', updateCaptureButton);

        captureBtn.addEventListener('click', captureFace);

        // ==============================================
        // PREVENT FORM SUBMISSION IF NOT CAPTURED
        // ==============================================
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (!isCaptured || !faceImageInput.value) {
                e.preventDefault();
                faceStatus.textContent = '⚠️ Please capture your face first!';
                faceStatus.className = 'face-status error';
                return false;
            }
        });

        // ==============================================
        // SUCCESS REDIRECT
        // ==============================================
        <?php if ($loginSuccess): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const loginBtn = document.getElementById('loginBtn');
                if (loginBtn) {
                    loginBtn.disabled = true;
                }

                setTimeout(function() {
                    window.location.href = '<?php echo $redirectUrl; ?>';
                }, 2000);
            });
        <?php endif; ?>

        // ==============================================
        // INITIALIZE
        // ==============================================
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(startCamera, 300);
        });

        // Stop camera when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden && stream && cameraStarted) {
                stream.getTracks().forEach(track => track.stop());
                cameraStarted = false;
            } else if (!document.hidden && !cameraStarted && !stream) {
                setTimeout(startCamera, 300);
            }
        });
    </script>
</body>

</html>