<?php
// faceLogin.php – Face Recognition Login for Villaruz Print Shop

// Set session lifetime BEFORE session_start()
$sessionLifetime = 604800; // 7 days

ini_set('session.cookie_lifetime', $sessionLifetime);
ini_set('session.gc_maxlifetime', $sessionLifetime);

session_start();
require_once __DIR__ . '/DB_Conn/config.php';

// ==============================================
// HANDLE FACE LOGIN
// ==============================================
function handleFaceLogin($pdo)
{
    $errors = [];
    $loginSuccess = false;
    $redirectUrl = '';
    $successMessage = '';
    $matchedUser = null;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [
            'errors' => $errors,
            'loginSuccess' => $loginSuccess,
            'matchedUser' => $matchedUser
        ];
    }

    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    $faceImage = $_POST['face_image'] ?? '';

    if (empty($faceImage)) {
        $errors[] = 'No face image captured.';
        return [
            'errors' => $errors,
            'loginSuccess' => $loginSuccess,
            'matchedUser' => $matchedUser
        ];
    }

    // Process the captured face image
    $capturedImageData = base64_decode(preg_replace('#^data:image/[^;]+;base64,#', '', $faceImage));
    if ($capturedImageData === false) {
        $errors[] = 'Invalid image data.';
        return [
            'errors' => $errors,
            'loginSuccess' => $loginSuccess,
            'matchedUser' => $matchedUser
        ];
    }

    // Get all registered faces
    $stmt = $pdo->prepare("SELECT id, acc_number, user_id, user_name, face_image, face_descriptor FROM face_recognition WHERE status = 'active'");
    $stmt->execute();
    $registeredFaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($registeredFaces)) {
        $errors[] = 'No registered faces found. Please register your face first.';
        return [
            'errors' => $errors,
            'loginSuccess' => $loginSuccess,
            'matchedUser' => $matchedUser
        ];
    }

    // For demo purposes, we'll do a simple comparison
    // In production, use a proper face recognition library
    // For now, we'll check if the captured image matches any registered face
    // This is a placeholder - actual face recognition would use proper algorithms
    
    $matched = false;
    $matchedAccNumber = null;
    $matchedUserId = null;
    $matchedUserName = null;

    // For demonstration, we're using a simple check
    // In production, replace this with actual face matching (OpenCV, FaceNet, etc.)
    // Since we don't have a proper face recognition library installed,
    // we'll use a basic approach: check if the image is similar enough
    
    // Create temp directory for comparison
    $tempDir = __DIR__ . '/../faceVerification/';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // Save captured image temporarily
    $tempFile = $tempDir . 'temp_capture_' . time() . '.jpg';
    file_put_contents($tempFile, $capturedImageData);
    
    // Get image hash for comparison (simple approach - NOT secure for production)
    $capturedHash = md5_file($tempFile);
    
    // Compare with registered faces
    foreach ($registeredFaces as $face) {
        $registeredImagePath = __DIR__ . '/faceVerification/' . $face['face_image'];
        if (file_exists($registeredImagePath)) {
            $registeredHash = md5_file($registeredImagePath);
            // For demo, we'll use a very loose comparison
            // In production, use proper face recognition
            if ($capturedHash === $registeredHash) {
                $matched = true;
                $matchedAccNumber = $face['acc_number'];
                $matchedUserId = $face['user_id'];
                $matchedUserName = $face['user_name'];
                break;
            }
        }
    }
    
    // Clean up temp file
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }

    if (!$matched) {
        $errors[] = 'Face not recognized. Please try again or register your face first.';
        return [
            'errors' => $errors,
            'loginSuccess' => $loginSuccess,
            'matchedUser' => $matchedUser
        ];
    }

    // Find user in admins or customers table
    $userRole = null;
    $userData = null;

    // Check in admins
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, phone_number FROM admins WHERE acc_number = ?");
    $stmt->execute([$matchedAccNumber]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $userRole = 'Admin';
        $userData = $admin;
    } else {
        // Check in customers
        $stmt = $pdo->prepare("SELECT id, acc_number, f_name, phone_number FROM customers WHERE acc_number = ?");
        $stmt->execute([$matchedAccNumber]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($customer) {
            $userRole = 'Customer';
            $userData = $customer;
        }
    }

    if (!$userData) {
        $errors[] = 'User account not found.';
        return [
            'errors' => $errors,
            'loginSuccess' => $loginSuccess,
            'matchedUser' => $matchedUser
        ];
    }

    // ==============================================
    // LOGIN SUCCESS - AUTHENTICATE USER
    // ==============================================
    date_default_timezone_set('Asia/Manila');
    $currentTime = date('M j, g:i A');

    if ($userRole === 'Admin') {
        session_regenerate_id(true);
        $_SESSION['user_role'] = 'Admin';
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['acc_number'] = $userData['acc_number'];

        $loginSuccess = true;
        $redirectUrl = 'web/all_products.php';
        $successMessage = 'Welcome back, ' . $userData['f_name'] . '!';

    } elseif ($userRole === 'Customer') {
        // Update customer online time
        $updateStmt = $pdo->prepare("UPDATE customers SET online_time = ? WHERE id = ?");
        $updateStmt->execute([$currentTime, $userData['id']]);
        session_regenerate_id(true);

        $_SESSION['user_role'] = 'Customer';
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['acc_number'] = $userData['acc_number'];

        $loginSuccess = true;
        $redirectUrl = 'public/shop.php';
        $successMessage = 'Welcome back, ' . $userData['f_name'] . '!';
    }

    $matchedUser = [
        'name' => $userData['f_name'],
        'acc_number' => $userData['acc_number'],
        'role' => $userRole
    ];

    return [
        'errors' => $errors,
        'loginSuccess' => $loginSuccess,
        'redirectUrl' => $redirectUrl,
        'successMessage' => $successMessage,
        'matchedUser' => $matchedUser
    ];
}

// ==============================================
// MAIN EXECUTION
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
$matchedUser = $loginResult['matchedUser'] ?? null;
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
            transform: scaleX(-1);
            -webkit-transform: scaleX(-1);
            -moz-transform: scaleX(-1);
            -ms-transform: scaleX(-1);
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
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #94a3b8;
            text-align: center;
            padding: 20px;
            width: 100%;
        }

        .camera-placeholder i {
            font-size: 64px;
            margin-bottom: 15px;
            color: #94a3b8;
            display: block;
        }

        .camera-placeholder p {
            font-size: 14px;
            margin: 0;
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
            font-size: 14px;
            text-align: center;
            margin-top: 10px;
            padding: 10px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .face-status.success {
            background: #f0fdf4;
            color: #065f46;
            border-color: #bbf7d0;
        }

        .face-status.error {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .face-status.info {
            background: #eff6ff;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .face-status .matched-user {
            font-weight: 600;
            color: #3b82f6;
        }

        .scanning-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.1);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10;
        }

        .scanning-overlay.active {
            display: flex;
        }

        .scanning-overlay .scan-text {
            color: white;
            font-weight: 600;
            background: rgba(0, 0, 0, 0.7);
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 16px;
        }

        .scanning-overlay .scan-text i {
            margin-right: 10px;
            animation: pulse 1s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        @media (max-width: 500px) {
            .auth-card {
                padding: 30px 25px;
            }

            .logo img {
                width: 75px;
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
            <p class="auth-sub">Face Recognition Login</p>
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
                    <?php if ($matchedUser): ?>
                        <br><small>Logged in as: <?php echo htmlspecialchars($matchedUser['name']); ?> (<?php echo htmlspecialchars($matchedUser['acc_number']); ?>)</small>
                    <?php endif; ?>
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
                    <div class="scanning-overlay" id="scanningOverlay">
                        <div class="scan-text">
                            <i class="fas fa-spinner fa-spin"></i> Scanning for face...
                        </div>
                    </div>
                </div>

                <div class="face-status" id="faceStatus">
                    <i class="fas fa-info-circle"></i> Looking for your face...
                </div>

                <div class="auth-footer">
                    Don't have a registered face? <a href="faceRegister.php">Register Now</a><br>
                    <small><a href="login.php">Login with Password</a></small>
                </div>

            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // ==============================================
        // CAMERA AND FACE DETECTION
        // ==============================================
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const cameraPlaceholder = document.getElementById('cameraPlaceholder');
        const cameraOverlay = document.getElementById('cameraOverlay');
        const scanningOverlay = document.getElementById('scanningOverlay');
        const faceStatus = document.getElementById('faceStatus');
        const faceImageInput = document.getElementById('faceImageInput');
        const loginForm = document.getElementById('loginForm');

        let stream = null;
        let cameraStarted = false;
        let isScanning = false;
        let scanInterval = null;

        // ==============================================
        // START CAMERA
        // ==============================================
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

                faceStatus.className = 'face-status info';
                faceStatus.innerHTML = '<i class="fas fa-eye"></i> Camera ready. Looking for your face...';
                
                // Start scanning after camera is ready
                setTimeout(startScanning, 1000);

            } catch (err) {
                console.error('Camera error:', err);
                cameraPlaceholder.style.display = 'block';
                cameraPlaceholder.innerHTML = `
                    <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                    <p>Unable to access camera</p>
                    <p style="font-size: 12px; color: #94a3b8; margin-top: 5px;">${err.message}</p>
                    <button onclick="startCamera()" style="margin-top: 15px; background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-redo"></i> Retry
                    </button>
                `;
                faceStatus.className = 'face-status error';
                faceStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error: ' + err.message;
            }
        }

        // ==============================================
        // START SCANNING
        // ==============================================
        function startScanning() {
            if (isScanning || !cameraStarted) return;
            
            isScanning = true;
            scanningOverlay.classList.add('active');
            
            // Capture and verify face every 2 seconds
            scanInterval = setInterval(captureAndVerify, 2000);
            
            // First capture immediately
            setTimeout(captureAndVerify, 500);
        }

        // ==============================================
        // STOP SCANNING
        // ==============================================
        function stopScanning() {
            isScanning = false;
            scanningOverlay.classList.remove('active');
            if (scanInterval) {
                clearInterval(scanInterval);
                scanInterval = null;
            }
        }

        // ==============================================
        // CAPTURE AND VERIFY FACE
        // ==============================================
        async function captureAndVerify() {
            if (!cameraStarted || !stream) return;

            try {
                // Capture current frame
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                
                // Apply mirror effect
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                ctx.setTransform(1, 0, 0, 1, 0, 0);

                const imageData = canvas.toDataURL('image/jpeg', 0.9);
                
                // Send to server for verification
                const formData = new FormData();
                formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                formData.append('face_image', imageData);

                const response = await fetch('faceLogin.php', {
                    method: 'POST',
                    body: formData
                });

                const html = await response.text();
                
                // Check if login was successful by looking for success indicators
                // If the response contains redirect or success message, it means face matched
                if (html.includes('loginSuccess') || html.includes('Welcome back')) {
                    // Success - stop scanning and redirect
                    stopScanning();
                    faceStatus.className = 'face-status success';
                    faceStatus.innerHTML = '<i class="fas fa-check-circle"></i> Face recognized! Redirecting...';
                    
                    // Extract redirect URL from response
                    const match = html.match(/window\.location\.href\s*=\s*['"]([^'"]+)['"]/);
                    if (match) {
                        setTimeout(() => {
                            window.location.href = match[1];
                        }, 1500);
                    } else {
                        // Reload page to process login
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }
                    return;
                }

                // Check for errors
                if (html.includes('alert-error')) {
                    // Face not recognized - keep scanning
                    faceStatus.className = 'face-status error';
                    faceStatus.innerHTML = '<i class="fas fa-times-circle"></i> Face not recognized. Keep looking at the camera...';
                } else {
                    // Still scanning
                    faceStatus.className = 'face-status info';
                    faceStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scanning for your face...';
                }

            } catch (err) {
                console.error('Error during face verification:', err);
            }
        }

        // ==============================================
        // INITIALIZE
        // ==============================================
        document.addEventListener('DOMContentLoaded', function() {
            // Check if already logged in (success response)
            <?php if ($loginSuccess): ?>
                // Already logged in, will redirect via PHP
            <?php else: ?>
                setTimeout(startCamera, 300);
            <?php endif; ?>
        });

        // Stop camera and scanning when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopScanning();
                if (stream && cameraStarted) {
                    stream.getTracks().forEach(track => track.stop());
                    cameraStarted = false;
                }
            } else if (!cameraStarted && !stream) {
                setTimeout(startCamera, 300);
                setTimeout(startScanning, 1500);
            }
        });

        // Handle page unload
        window.addEventListener('beforeunload', function() {
            stopScanning();
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        });
    </script>

    <?php if ($loginSuccess): ?>
        <script>
            // Auto redirect after successful login
            setTimeout(function() {
                window.location.href = '<?php echo $redirectUrl; ?>';
            }, 2000);
        </script>
    <?php endif; ?>

</body>

</html>