<?php
// faceRegister.php – Face Recognition Registration for Villaruz Print Shop

// ============================================================
// 1. CONFIGURATION & REQUIREMENTS
// ============================================================
require_once __DIR__ . '/DB_Conn/config.php';
session_start();

// ============================================================
// 2. CREATE FACE_RECOGNITION TABLE IF NOT EXISTS
// ============================================================
try {
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS face_recognition (
            id INT AUTO_INCREMENT PRIMARY KEY,
            acc_number VARCHAR(50) NOT NULL,
            user_id INT NOT NULL,
            user_name VARCHAR(100) NOT NULL,
            face_descriptor TEXT NOT NULL,
            face_image VARCHAR(255) NOT NULL,
            registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            status ENUM('active', 'inactive') DEFAULT 'active',
            INDEX idx_acc_number (acc_number),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($createTableSQL);
} catch (PDOException $e) {
    error_log("Table creation error: " . $e->getMessage());
}

// ============================================================
// 3. CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ============================================================
// 4. GET SESSION USER (if logged in)
// ============================================================
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['acc_number']);
$userName = '';
$accNumber = '';
$userId = '';

if ($isLoggedIn) {
    $userId = $_SESSION['user_id'];
    $accNumber = $_SESSION['acc_number'];

    if ($_SESSION['user_role'] === 'Customer') {
        $stmt = $pdo->prepare("SELECT f_name FROM customers WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $userName = $user['f_name'];
        }
    } elseif ($_SESSION['user_role'] === 'Admin') {
        $stmt = $pdo->prepare("SELECT f_name FROM admins WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $userName = $user['f_name'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Face Registration | Villaruz Print Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .auth-sub {
            text-align: center;
            color: #64748b;
            margin-bottom: 30px;
            font-size: 25px;
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

        .user-info {
            background: #f8fafc;
            border-radius: 5px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .user-info .label {
            color: #64748b;
            font-size: 12px;
        }

        .user-info .value {
            color: #0f172a;
            font-weight: 600;
            font-size: 16px;
        }

        .camera-container {
            position: relative;
            background: #0f172a;
            border-radius: 5px;
            overflow: hidden;
            border: 2px solid #e2e8f0;
            aspect-ratio: 4/3;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 15px;
        }

        .camera-container::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(ellipse at center, transparent 30%, rgba(0, 0, 0, 0.2) 70%);
            pointer-events: none;
            z-index: 3;
        }

        .camera-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
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

        /* Face Detection Box Overlay */
        .face-detection-box {
            position: absolute;
            border: 3px solid #10b981;
            border-radius: 8px;
            z-index: 6;
            display: none;
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.3);
            animation: box-pulse 1.5s ease-in-out infinite;
            pointer-events: none;
        }

        .face-detection-box .corner-label {
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: #10b981;
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 4px;
            white-space: nowrap;
        }

        @keyframes box-pulse {

            0%,
            100% {
                border-color: rgba(16, 185, 129, 0.6);
                box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
            }

            50% {
                border-color: rgba(16, 185, 129, 1);
                box-shadow: 0 0 40px rgba(16, 185, 129, 0.4);
            }
        }

        /* Registered Face Overlay */
        .registered-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 10px;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: none;
            min-width: 100px;
        }

        .registered-overlay.active {
            display: block;
        }

        .registered-overlay .label {
            color: #94a3b8;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
            margin-bottom: 5px;
        }

        .registered-overlay .face-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #10b981;
            display: block;
            margin: 0 auto;
        }

        .registered-overlay .face-name {
            color: white;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            margin-top: 5px;
        }

        /* Enhanced Camera Overlay */
        .camera-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            display: none;
            z-index: 5;
        }

        .camera-overlay.active {
            display: block;
        }

        /* Face Guide Circle */
        .face-guide {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 280px;
            height: 280px;
            border: 4px solid rgba(59, 130, 246, 0.5);
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1), inset 0 0 40px rgba(59, 130, 246, 0.05), 0 0 60px rgba(59, 130, 246, 0.1);
            animation: pulse-guide 2s ease-in-out infinite;
            overflow: hidden;
        }

        .face-guide .scanner {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.8), #3b82f6, rgba(59, 130, 246, 0.8), transparent);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5), 0 0 60px rgba(59, 130, 246, 0.2);
            animation: scan-line 2.5s ease-in-out infinite;
            z-index: 6;
            border-radius: 2px;
        }

        .face-guide .scanner::after {
            content: '';
            position: absolute;
            top: -10px;
            left: 0;
            right: 0;
            height: 20px;
            background: radial-gradient(ellipse at center, rgba(59, 130, 246, 0.3), transparent 70%);
            filter: blur(10px);
        }

        .face-guide .scan-lines {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 5;
            pointer-events: none;
            opacity: 0.15;
        }

        .face-guide .scan-lines::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(0deg, transparent 0px, transparent 8px, rgba(59, 130, 246, 0.3) 8px, rgba(59, 130, 246, 0.3) 9px);
            animation: scan-lines 1s linear infinite;
        }

        .face-guide .corner {
            position: absolute;
            width: 30px;
            height: 30px;
            border-color: rgba(59, 130, 246, 0.6);
            border-style: solid;
            border-width: 0;
            z-index: 7;
            transition: all 0.3s;
        }

        .face-guide .corner.tl {
            top: -3px;
            left: -3px;
            border-top-width: 4px;
            border-left-width: 4px;
            border-radius: 4px 0 0 0;
        }

        .face-guide .corner.tr {
            top: -3px;
            right: -3px;
            border-top-width: 4px;
            border-right-width: 4px;
            border-radius: 0 4px 0 0;
        }

        .face-guide .corner.bl {
            bottom: -3px;
            left: -3px;
            border-bottom-width: 4px;
            border-left-width: 4px;
            border-radius: 0 0 0 4px;
        }

        .face-guide .corner.br {
            bottom: -3px;
            right: -3px;
            border-bottom-width: 4px;
            border-right-width: 4px;
            border-radius: 0 0 4px 0;
        }

        .face-guide::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 180px;
            height: 180px;
            border: 2px dashed rgba(59, 130, 246, 0.2);
            border-radius: 50%;
            z-index: 4;
        }

        .face-guide .face-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: rgba(59, 130, 246, 0.15);
            font-size: 100px;
            pointer-events: none;
            z-index: 4;
            animation: pulse-icon 3s ease-in-out infinite;
        }

        .face-guide .guide-text {
            position: absolute;
            bottom: -50px;
            left: 50%;
            transform: translateX(-50%);
            color: #94a3b8;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            background: rgba(0, 0, 0, 0.6);
            padding: 6px 16px;
            border-radius: 20px;
            backdrop-filter: blur(4px);
            letter-spacing: 0.5px;
            z-index: 10;
        }

        .face-guide .guide-text i {
            margin-right: 6px;
            color: #3b82f6;
        }

        @keyframes pulse-guide {

            0%,
            100% {
                border-color: rgba(59, 130, 246, 0.5);
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1), inset 0 0 40px rgba(59, 130, 246, 0.05);
            }

            50% {
                border-color: rgba(59, 130, 246, 0.8);
                box-shadow: 0 0 20px 4px rgba(59, 130, 246, 0.15), inset 0 0 60px rgba(59, 130, 246, 0.08);
            }
        }

        @keyframes scan-line {
            0% {
                top: 0%;
                opacity: 1;
            }

            50% {
                top: 100%;
                opacity: 1;
            }

            51% {
                opacity: 0;
            }

            52% {
                top: 0%;
                opacity: 0;
            }

            53% {
                opacity: 1;
            }

            100% {
                top: 100%;
                opacity: 1;
            }
        }

        @keyframes scan-lines {
            0% {
                transform: translateY(0);
            }

            100% {
                transform: translateY(9px);
            }
        }

        @keyframes pulse-icon {

            0%,
            100% {
                opacity: 0.15;
                transform: translate(-50%, -50%) scale(1);
            }

            50% {
                opacity: 0.2;
                transform: translate(-50%, -50%) scale(1.05);
            }
        }

        .face-guide.detected {
            border-color: rgba(16, 185, 129, 0.6) !important;
            animation: none;
        }

        .face-guide.detected::before {
            border-color: rgba(16, 185, 129, 0.3) !important;
        }

        .face-guide.detected .guide-text {
            color: #10b981;
        }

        .face-guide.detected .guide-text i {
            color: #10b981;
        }

        /* Countdown Overlay */
        .countdown-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 20;
            backdrop-filter: blur(4px);
        }

        .countdown-overlay.active {
            display: flex;
        }

        .countdown-number {
            font-size: 120px;
            font-weight: 800;
            color: white;
            text-shadow: 0 0 60px rgba(59, 130, 246, 0.5);
            animation: countdown-pop 0.8s ease;
        }

        @keyframes countdown-pop {
            0% {
                transform: scale(0.3);
                opacity: 0;
            }

            50% {
                transform: scale(1.3);
                opacity: 1;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .countdown-number.ready {
            color: #10b981;
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
            z-index: 2;
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

        .btn-retry {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-retry:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .controls {
            display: flex;
            gap: 12px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-width: 100px;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover:not(:disabled) {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover:not(:disabled) {
            background: #cbd5e1;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover:not(:disabled) {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .status-message {
            margin-top: 15px;
            padding: 12px 16px;
            border-radius: 5px;
            font-size: 14px;
            display: none;
            align-items: center;
            gap: 10px;
        }

        .status-message.success {
            display: flex;
            background: #f0fdf4;
            color: #065f46;
            border: 1px solid #bbf7d0;
        }

        .status-message.error {
            display: flex;
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .status-message.info {
            display: flex;
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .status-message i {
            font-size: 18px;
        }

        .status-message.quality {
            display: flex;
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid #ffffff;
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

        /* Quality Indicators */
        .quality-indicators {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            justify-content: center;
        }

        .quality-indicator {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 12px;
            background: #f1f5f9;
            color: #64748b;
        }

        .quality-indicator.good {
            background: #d1fae5;
            color: #065f46;
        }

        .quality-indicator.bad {
            background: #fee2e2;
            color: #991b1b;
        }

        .quality-indicator i {
            font-size: 12px;
        }

        @media (max-width: 480px) {
            .auth-card {
                padding: 20px;
            }

            .auth-sub {
                font-size: 20px;
            }

            .controls {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .face-guide {
                width: 200px;
                height: 200px;
            }

            .face-guide::before {
                width: 130px;
                height: 130px;
            }

            .face-guide .face-icon {
                font-size: 70px;
            }

            .face-guide .corner {
                width: 20px;
                height: 20px;
            }

            .face-guide .guide-text {
                font-size: 11px;
                padding: 4px 12px;
                bottom: -42px;
            }

            .btn-retry {
                width: 100%;
                justify-content: center;
            }

            .countdown-number {
                font-size: 80px;
            }

            .registered-overlay .face-preview {
                width: 60px;
                height: 60px;
            }
        }

        @media (max-width: 380px) {
            .face-guide {
                width: 160px;
                height: 160px;
            }

            .face-guide::before {
                width: 100px;
                height: 100px;
            }

            .face-guide .face-icon {
                font-size: 50px;
            }

            .countdown-number {
                font-size: 60px;
            }
        }
    </style>
</head>

<body>

    <div class="auth-container">
        <div class="auth-card">
            <p class="auth-sub">Face Registration</p>
            <div style="text-align: center; margin-bottom: 20px;">
                <span class="version-badge">V5.30.42</span>
            </div>

            <!-- User Info -->
            <div class="user-info">
                <div>
                    <div class="label">Account Number</div>
                    <div class="value"><?php echo htmlspecialchars($accNumber ?: 'Not logged in'); ?></div>
                </div>
                <div>
                    <div class="label">Name</div>
                    <div class="value"><?php echo htmlspecialchars($userName ?: 'Guest'); ?></div>
                </div>
            </div>

            <!-- Camera Container -->
            <div class="camera-container" id="cameraContainer">
                <video id="video" autoplay playsinline></video>
                <canvas id="canvas"></canvas>

                <!-- Face Detection Box -->
                <div class="face-detection-box" id="faceDetectionBox">
                    <span class="corner-label">Face Detected</span>
                </div>

                <!-- Registered Face Overlay -->
                <div class="registered-overlay" id="registeredOverlay">
                    <div class="label"><i class="fas fa-check-circle" style="color: #10b981;"></i> Registered</div>
                    <img class="face-preview" id="registeredFacePreview" src="" alt="Registered Face">
                    <div class="face-name" id="registeredFaceName">Your Face</div>
                </div>

                <div class="camera-overlay active" id="cameraOverlay">
                    <div class="face-guide" id="faceGuide">
                        <div class="scanner" id="scannerLine"></div>
                        <div class="scan-lines"></div>
                        <div class="corner tl"></div>
                        <div class="corner tr"></div>
                        <div class="corner bl"></div>
                        <div class="corner br"></div>
                        <div class="face-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="guide-text" id="guideText">
                            <i class="fas fa-arrow-up"></i> Position your face here
                        </div>
                    </div>
                </div>

                <!-- Countdown Overlay -->
                <div class="countdown-overlay" id="countdownOverlay">
                    <div class="countdown-number" id="countdownNumber">3</div>
                </div>

                <div class="camera-placeholder" id="cameraPlaceholder">
                    <i class="fas fa-camera"></i>
                    <p>Starting camera...</p>
                </div>
            </div>

            <!-- Quality Indicators -->
            <div class="quality-indicators" id="qualityIndicators">
                <span class="quality-indicator" id="brightnessIndicator">
                    Lighting: --
                </span>
                <span class="quality-indicator" id="clarityIndicator">
                    Clarity: --
                </span>
                <span class="quality-indicator" id="faceSizeIndicator">
                    Face: --
                </span>
            </div>

            <!-- Status Message -->
            <div class="status-message" id="statusMessage">
                <i class="fas fa-info-circle"></i>
                <span id="statusText">Ready</span>
            </div>

            <!-- Retry Button -->
            <div style="display: flex; justify-content: center; margin-top: 15px; gap: 12px;">
                <button onclick="reload()" class="btn-retry">
                    <i class="fas fa-sync-alt"></i> Retry
                </button>
            </div>

            <!-- Controls -->
            <div class="controls">
                <button class="btn btn-primary" id="captureBtn" disabled>
                    <i class="fas fa-camera"></i> Auto Capture
                </button>
                <button class="btn btn-success" id="registerBtn" disabled>
                    <i class="fas fa-save"></i> Register Face
                </button>
                <button class="btn btn-secondary" id="restartBtn">
                    <i class="fas fa-sync"></i> Restart
                </button>
            </div>

            <div class="auth-footer">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        // ============================================================
        // CONFIGURATION
        // ============================================================
        const csrfToken = '<?php echo $csrfToken; ?>';
        const accNumber = '<?php echo htmlspecialchars($accNumber); ?>';
        const userId = '<?php echo htmlspecialchars($userId); ?>';
        const userName = '<?php echo htmlspecialchars($userName); ?>';
        const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;

        // ============================================================
        // DOM ELEMENTS
        // ============================================================
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const cameraPlaceholder = document.getElementById('cameraPlaceholder');
        const cameraOverlay = document.getElementById('cameraOverlay');
        const captureBtn = document.getElementById('captureBtn');
        const registerBtn = document.getElementById('registerBtn');
        const restartBtn = document.getElementById('restartBtn');
        const statusMessage = document.getElementById('statusMessage');
        const statusText = document.getElementById('statusText');
        const faceGuide = document.getElementById('faceGuide');
        const guideText = document.getElementById('guideText');
        const scannerLine = document.getElementById('scannerLine');
        const faceDetectionBox = document.getElementById('faceDetectionBox');
        const countdownOverlay = document.getElementById('countdownOverlay');
        const countdownNumber = document.getElementById('countdownNumber');
        const registeredOverlay = document.getElementById('registeredOverlay');
        const registeredFacePreview = document.getElementById('registeredFacePreview');
        const registeredFaceName = document.getElementById('registeredFaceName');
        const brightnessIndicator = document.getElementById('brightnessIndicator');
        const clarityIndicator = document.getElementById('clarityIndicator');
        const faceSizeIndicator = document.getElementById('faceSizeIndicator');

        let stream = null;
        let capturedImageData = null;
        let isCaptured = false;
        let cameraStarted = false;
        let isCountdownActive = false;
        let qualityCheckInterval = null;

        // ============================================================
        // FACE GUIDE CONTROL
        // ============================================================
        function updateGuideStatus(status) {
            const guide = document.getElementById('faceGuide');
            const guideText = document.getElementById('guideText');

            guide.classList.remove('detected', 'error');

            if (status === 'scanning') {
                guideText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scanning...';
                guideText.style.color = '#94a3b8';
            } else if (status === 'detected') {
                guide.classList.add('detected');
                guideText.innerHTML = 'Face Detected!';
                guideText.style.color = '#10b981';
            } else if (status === 'error') {
                guide.classList.add('error');
                guideText.innerHTML = 'Try again';
                guideText.style.color = '#ef4444';
            } else {
                guideText.innerHTML = '<i class="fas fa-arrow-up"></i> Position your face here';
                guideText.style.color = '#94a3b8';
            }
        }

        // ============================================================
        // FACE DETECTION & QUALITY CHECK
        // ============================================================
        function checkImageQuality(imageData) {
            const canvas = document.createElement('canvas');
            canvas.width = 200;
            canvas.height = 200;
            const ctx = canvas.getContext('2d');
            const img = new Image();
            img.onload = function () {
                ctx.drawImage(img, 0, 0, 200, 200);
                const imageData = ctx.getImageData(0, 0, 200, 200);
                const data = imageData.data;

                // Check brightness
                let totalBrightness = 0;
                for (let i = 0; i < data.length; i += 4) {
                    const luminance = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                    totalBrightness += luminance;
                }
                const avgBrightness = totalBrightness / (data.length / 4);
                const isWellLit = avgBrightness > 60 && avgBrightness < 200;

                // Check contrast (simple variance)
                let mean = avgBrightness;
                let variance = 0;
                let pixelCount = 0;
                for (let i = 0; i < data.length; i += 4) {
                    const luminance = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                    variance += Math.pow(luminance - mean, 2);
                    pixelCount++;
                }
                variance /= pixelCount;
                const isClear = variance > 500;

                // Check face size (using center region)
                let centerPixels = 0;
                let centerCount = 0;
                for (let y = 60; y < 140; y++) {
                    for (let x = 60; x < 140; x++) {
                        const idx = (y * 200 + x) * 4;
                        const luminance = 0.299 * data[idx] + 0.587 * data[idx + 1] + 0.114 * data[idx + 2];
                        centerPixels += luminance;
                        centerCount++;
                    }
                }
                const centerAvg = centerPixels / centerCount;
                const faceSize = centerAvg > 80;

                // Update indicators
                updateQualityIndicators(isWellLit, isClear, faceSize);

                // Show status
                if (!isWellLit) {
                    showStatus('Too dark or too bright. Adjust lighting.', 'quality');
                    return false;
                }
                if (!isClear) {
                    showStatus('Image is blurry. Please hold steady.', 'quality');
                    return false;
                }
                if (!faceSize) {
                    showStatus('Face too small. Move closer to camera.', 'quality');
                    return false;
                }

                return true;
            };
            img.src = imageData;
            return true;
        }

        function reload() {
            location.reload();
        }

        function updateQualityIndicators(isWellLit, isClear, faceSize) {
            brightnessIndicator.className = 'quality-indicator ' + (isWellLit ? 'good' : 'bad');
            brightnessIndicator.innerHTML = `Lighting: ${isWellLit ? 'Good' : 'Poor'}`;

            clarityIndicator.className = 'quality-indicator ' + (isClear ? 'good' : 'bad');
            clarityIndicator.innerHTML = `Clarity: ${isClear ? 'Clear' : 'Blurry'}`;

            faceSizeIndicator.className = 'quality-indicator ' + (faceSize ? 'good' : 'bad');
            faceSizeIndicator.innerHTML = `Face: ${faceSize ? 'Good Size' : 'Too Small'}`;
        }

        // ============================================================
        // FACE DETECTION PREVIEW
        // ============================================================
        function detectFace(frame) {
            // Simple face detection using canvas analysis
            // In production, use a proper face detection library
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = 200;
            tempCanvas.height = 200;
            const ctx = tempCanvas.getContext('2d');
            const img = new Image();
            img.onload = function () {
                ctx.drawImage(img, 0, 0, 200, 200);
                const imageData = ctx.getImageData(0, 0, 200, 200);
                const data = imageData.data;

                // Simple detection: check for face-like region in center
                let centerBrightness = 0;
                let edgeBrightness = 0;
                let centerCount = 0;
                let edgeCount = 0;

                // Center region (face area)
                for (let y = 50; y < 150; y++) {
                    for (let x = 50; x < 150; x++) {
                        const idx = (y * 200 + x) * 4;
                        const luminance = 0.299 * data[idx] + 0.587 * data[idx + 1] + 0.114 * data[idx + 2];
                        centerBrightness += luminance;
                        centerCount++;
                    }
                }

                // Edge region (background)
                for (let y = 0; y < 200; y++) {
                    for (let x = 0; x < 200; x++) {
                        if (x < 30 || x > 170 || y < 30 || y > 170) {
                            const idx = (y * 200 + x) * 4;
                            const luminance = 0.299 * data[idx] + 0.587 * data[idx + 1] + 0.114 * data[idx + 2];
                            edgeBrightness += luminance;
                            edgeCount++;
                        }
                    }
                }

                const centerAvg = centerBrightness / centerCount;
                const edgeAvg = edgeBrightness / edgeCount;
                const diff = Math.abs(centerAvg - edgeAvg);

                // If there's significant difference, assume face detected
                const faceDetected = diff > 20 && centerAvg > 60 && centerAvg < 200;

                if (faceDetected) {
                    faceDetectionBox.style.display = 'block';
                    faceDetectionBox.style.width = '60%';
                    faceDetectionBox.style.height = '60%';
                    faceDetectionBox.style.top = '20%';
                    faceDetectionBox.style.left = '20%';
                    updateGuideStatus('detected');
                } else {
                    faceDetectionBox.style.display = 'none';
                    updateGuideStatus('default');
                }
            };
            img.src = frame;
        }

        // ============================================================
        // COUNTDOWN TIMER
        // ============================================================
        function startCountdown() {
            if (isCountdownActive || !cameraStarted) return;

            isCountdownActive = true;
            let count = 3;
            countdownOverlay.classList.add('active');
            countdownNumber.textContent = count;
            countdownNumber.className = 'countdown-number';

            const interval = setInterval(() => {
                count--;
                if (count > 0) {
                    countdownNumber.textContent = count;
                    countdownNumber.className = 'countdown-number';
                    // Play vibration effect
                    countdownNumber.style.animation = 'none';
                    setTimeout(() => {
                        countdownNumber.style.animation = 'countdown-pop 0.8s ease';
                    }, 10);
                } else {
                    clearInterval(interval);
                    countdownNumber.textContent = '📸';
                    countdownNumber.className = 'countdown-number ready';
                    setTimeout(() => {
                        countdownOverlay.classList.remove('active');
                        isCountdownActive = false;
                        // Auto capture
                        captureFace();
                    }, 600);
                }
            }, 1000);

            captureBtn.disabled = true;
            captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Capturing...';
        }

        // ============================================================
        // START CAMERA - AUTO ON PAGE LOAD
        // ============================================================
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

                captureBtn.disabled = false;
                registerBtn.disabled = true;
                isCaptured = false;
                cameraStarted = true;

                updateGuideStatus('default');

                // Start face detection interval
                qualityCheckInterval = setInterval(() => {
                    if (cameraStarted && !isCountdownActive) {
                        // Capture frame for detection
                        const tempCanvas = document.createElement('canvas');
                        tempCanvas.width = video.videoWidth;
                        tempCanvas.height = video.videoHeight;
                        const ctx = tempCanvas.getContext('2d');
                        ctx.drawImage(video, 0, 0);
                        detectFace(tempCanvas.toDataURL('image/jpeg', 0.7));
                    }
                }, 1000);

                showStatus('Camera is ready. Click "Auto Capture" or wait for countdown.', 'info');

                // Auto start countdown after 3 seconds
                setTimeout(() => {
                    if (cameraStarted && !isCaptured) {
                        startCountdown();
                    }
                }, 3000);

            } catch (err) {
                console.error('Camera error:', err);
                cameraPlaceholder.style.display = 'block';
                cameraPlaceholder.innerHTML = `
                <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                <p>Unable to access camera</p>
                <p style="font-size: 12px; color: #94a3b8; margin-top: 5px;">${err.message}</p>
                <button onclick="startCamera()" style="margin-top: 15px; background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                    <i class="fas fa-redo"></i>
                </button>
            `;
                showStatus('Error: ' + err.message, 'error');
            }
        }

        // ============================================================
        // CAPTURE FACE
        // ============================================================
        function captureFace() {
            if (!stream || !cameraStarted) {
                showStatus('Camera not started. Please wait or restart the camera.', 'error');
                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');

            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            ctx.setTransform(1, 0, 0, 1, 0, 0);

            const imageData = canvas.toDataURL('image/jpeg', 0.9);

            // Check image quality
            const qualityPassed = checkImageQuality(imageData);
            if (!qualityPassed) {
                // Retry countdown after quality check
                captureBtn.disabled = false;
                captureBtn.innerHTML = '<i class="fas fa-camera"></i> Retry Capture';
                setTimeout(() => {
                    if (!isCaptured && cameraStarted) {
                        startCountdown();
                    }
                }, 2000);
                return;
            }

            capturedImageData = imageData;
            isCaptured = true;

            video.style.display = 'none';
            canvas.style.display = 'block';
            cameraOverlay.style.display = 'none';
            faceDetectionBox.style.display = 'none';

            captureBtn.innerHTML = '<i class="fas fa-redo"></i> Recapture';
            captureBtn.onclick = recaptureFace;
            registerBtn.disabled = false;
            updateGuideStatus('detected');

            // Show registered overlay
            registeredOverlay.classList.add('active');
            registeredFacePreview.src = capturedImageData;
            registeredFaceName.textContent = userName || 'Your Face';

            showStatus('Face captured. Click "Register Face" to save.', 'success');
        }

        // ============================================================
        // RECAPTURE FACE
        // ============================================================
        function recaptureFace() {
            video.style.display = 'block';
            canvas.style.display = 'none';
            cameraOverlay.style.display = 'block';
            isCaptured = false;
            capturedImageData = null;
            registeredOverlay.classList.remove('active');

            captureBtn.innerHTML = '<i class="fas fa-camera"></i> Auto Capture';
            captureBtn.onclick = captureFace;
            registerBtn.disabled = true;
            updateGuideStatus('default');

            showStatus('Camera resumed. Click "Auto Capture" or wait for countdown.', 'info');

            // Auto start countdown again
            setTimeout(() => {
                if (cameraStarted && !isCaptured && !isCountdownActive) {
                    startCountdown();
                }
            }, 3000);
        }

        // ============================================================
        // REGISTER FACE
        // ============================================================
        async function registerFace() {
            if (!isLoggedIn) {
                showStatus('Please login first before registering your face.', 'error');
                return;
            }

            if (!capturedImageData) {
                showStatus('Please capture your face first.', 'error');
                return;
            }

            registerBtn.disabled = true;
            captureBtn.disabled = true;
            registerBtn.innerHTML = '<span class="spinner"></span> Registering...';
            updateGuideStatus('scanning');

            try {
                const formData = new FormData();
                formData.append('action', 'register_face');
                formData.append('acc_number', accNumber);
                formData.append('user_id', userId);
                formData.append('user_name', userName);
                formData.append('face_image', capturedImageData);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/face_recognition_api.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showStatus('✅ ' + data.message, 'success');
                    registerBtn.innerHTML = '<i class="fas fa-check-circle"></i> Registered!';
                    updateGuideStatus('detected');
                    setTimeout(() => {
                        recaptureFace();
                        registerBtn.innerHTML = '<i class="fas fa-save"></i> Register Face';
                        registerBtn.disabled = true;
                    }, 2000);
                } else {
                    showStatus('❌ ' + data.message, 'error');
                    registerBtn.innerHTML = '<i class="fas fa-save"></i> Register Face';
                    registerBtn.disabled = false;
                    captureBtn.disabled = false;
                    updateGuideStatus('error');
                    setTimeout(() => {
                        updateGuideStatus('default');
                    }, 2000);
                }

            } catch (err) {
                console.error('Registration error:', err);
                showStatus('Error: ' + err.message, 'error');
                registerBtn.innerHTML = '<i class="fas fa-save"></i> Register Face';
                registerBtn.disabled = false;
                captureBtn.disabled = false;
                updateGuideStatus('error');
                setTimeout(() => {
                    updateGuideStatus('default');
                }, 2000);
            }
        }

        // ============================================================
        // SHOW STATUS
        // ============================================================
        function showStatus(message, type = 'info') {
            statusMessage.className = 'status-message ' + type;
            statusText.textContent = message;
            statusMessage.style.display = 'flex';
        }

        // ============================================================
        // EVENT LISTENERS
        // ============================================================
        captureBtn.addEventListener('click', startCountdown);
        registerBtn.addEventListener('click', registerFace);
        restartBtn.addEventListener('click', function () {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            if (qualityCheckInterval) {
                clearInterval(qualityCheckInterval);
            }
            cameraStarted = false;
            isCountdownActive = false;
            video.style.display = 'block';
            canvas.style.display = 'none';
            cameraOverlay.style.display = 'none';
            faceDetectionBox.style.display = 'none';
            countdownOverlay.classList.remove('active');
            registeredOverlay.classList.remove('active');
            captureBtn.innerHTML = '<i class="fas fa-camera"></i> Auto Capture';
            captureBtn.onclick = startCountdown;
            registerBtn.disabled = true;
            registerBtn.innerHTML = '<i class="fas fa-save"></i> Register Face';
            isCaptured = false;
            capturedImageData = null;
            updateGuideStatus('default');
            showStatus('Restarting camera...', 'info');
            setTimeout(startCamera, 300);
        });

        // ============================================================
        // INITIALIZE - AUTO START ON PAGE LOAD
        // ============================================================
        document.addEventListener('DOMContentLoaded', function () {
            if (isLoggedIn) {
                setTimeout(startCamera, 300);
            } else {
                showStatus('Please login to register your face.', 'info');
                cameraPlaceholder.style.display = 'block';
                cameraPlaceholder.innerHTML = `
                <i class="fas fa-lock" style="color: #f59e0b;"></i>
                <p>Please login first</p>
                <a href="login.php" style="color: #3b82f6; text-decoration: none; font-weight: 600; display: inline-block; margin-top: 10px;">Login here</a>
            `;
            }
        });

        // Stop camera when page is hidden to save resources
        document.addEventListener('visibilitychange', function () {
            if (document.hidden && stream && cameraStarted) {
                stream.getTracks().forEach(track => track.stop());
                cameraStarted = false;
            } else if (!document.hidden && isLoggedIn && !cameraStarted && !stream) {
                setTimeout(startCamera, 300);
            }
        });
    </script>

</body>

</html>