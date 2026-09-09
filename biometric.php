<?php
// biometric.php – Biometric enrollment page

session_start();
require_once __DIR__ . '/DB_Conn/config.php';

// Check if user is logged in (via temp session or actual session)
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_user_type'])) {
    // Check if user is already logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        header('Location: login.php');
        exit;
    } else {
        // Already logged in, use session data
        $userId = $_SESSION['user_id'];
        $userType = $_SESSION['user_role'];
        $isExistingUser = true;
    }
} else {
    $userId = $_SESSION['temp_user_id'];
    $userType = $_SESSION['temp_user_type'];
    $isExistingUser = false;
}

// Determine redirect URL based on user type
if ($userType === 'Admin') {
    $redirectUrl = 'web/all_products.php';
} else {
    $redirectUrl = 'public/shop.php';
}

// Handle biometric registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $biometric_id = trim($_POST['biometric_id'] ?? '');
    $biometric_type = trim($_POST['biometric_type'] ?? 'FINGERPRINT');
    
    if (empty($biometric_id)) {
        // ✅ SKIP: User chose to skip biometric registration
        // Clear temp session and log user in
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_user_type']);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $userType;
        
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        // ✅ Save biometric to database
        $table = ($userType === 'Admin') ? 'admins' : 'customers';
        $stmt = $pdo->prepare("UPDATE $table SET biometric_id = ?, biometric_enrolled = 1 WHERE id = ?");
        $stmt->execute([$biometric_id, $userId]);
        
        // Set cookie for auto-login
        setcookie('user_id', $userId, time() + (86400 * 365), "/");
        setcookie('user_type', $userType, time() + (86400 * 365), "/");
        
        // Clear temp session and log user in
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_user_type']);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $userType;
        
        header('Location: ' . $redirectUrl);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometric Registration</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body {
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            text-align: center;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h2 {
            font-size: 24px;
            color: #0f172a;
            margin-bottom: 8px;
        }
        p {
            color: #64748b;
            font-size: 15px;
            line-height: 1.6;
        }
        .btn-group {
            margin-top: 25px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn {
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-primary {
            background: linear-gradient(145deg, #3b82f6, #6366f1);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .btn-success {
            background: linear-gradient(145deg, #22c55e, #16a34a);
            color: white;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        .status {
            margin-top: 15px;
            padding: 12px;
            border-radius: 8px;
            display: none;
        }
        .status.show { display: block; }
        .status.success { background: #f0fdf4; color: #065f46; border: 1px solid #bbf7d0; }
        .status.error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .status.info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .skip {
            display: block;
            margin-top: 15px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 14px;
        }
        .skip:hover {
            color: #64748b;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔐</div>
        <h2>Secure Your Account</h2>
        <p>
            Register your fingerprint, PIN, or pattern for faster login next time.
            <br><br>
            <strong>Note:</strong> Your biometric data stays on your device.
        </p>

        <div class="btn-group">
            <button class="btn btn-primary" id="biometricBtn">
                <i class="fas fa-fingerprint"></i> Register Fingerprint/PIN
            </button>
            <button class="btn btn-success" id="skipBtn">
                <i class="fas fa-arrow-right"></i> Skip for Now
            </button>
        </div>

        <div id="status" class="status"></div>

        <form method="POST" id="biometricForm">
            <input type="hidden" name="biometric_id" id="biometricId">
            <input type="hidden" name="biometric_type" id="biometricType">
        </form>
    </div>

    <script>
        const biometricBtn = document.getElementById('biometricBtn');
        const skipBtn = document.getElementById('skipBtn');
        const statusDiv = document.getElementById('status');

        // ==========================================
        // REGISTER BIOMETRIC
        // ==========================================

        biometricBtn.addEventListener('click', function() {
            if (window.AndroidBiometric) {
                showStatus('🔐 Authenticating...', 'info');
                window.AndroidBiometric.enroll();
            } else {
                showStatus('❌ Biometric registration is only available in the app', 'error');
            }
        });

        // ==========================================
        // SKIP BUTTON - Redirect to dashboard
        // ==========================================

        skipBtn.addEventListener('click', function() {
            // Submit form with empty biometric_id to trigger skip
            document.getElementById('biometricId').value = '';
            document.getElementById('biometricType').value = '';
            document.getElementById('biometricForm').submit();
        });

        // ==========================================
        // BIOMETRIC CALLBACKS
        // ==========================================

        function biometricEnrollSuccess(data) {
            showStatus('✅ Biometric registered successfully!', 'success');
            document.getElementById('biometricId').value = data;
            document.getElementById('biometricType').value = 'FINGERPRINT';
            
            setTimeout(function() {
                document.getElementById('biometricForm').submit();
            }, 1000);
        }

        function biometricEnrollFailed() {
            showStatus('❌ Registration failed. Please try again.', 'error');
        }

        function biometricEnrollCancel() {
            showStatus('⏹️ Registration canceled.', 'info');
            setTimeout(() => {
                statusDiv.className = 'status';
                statusDiv.textContent = '';
            }, 3000);
        }

        function biometricEnrollError(error) {
            showStatus('❌ Error: ' + error, 'error');
        }

        function showStatus(message, type) {
            statusDiv.textContent = message;
            statusDiv.className = 'status show ' + type;
        }
    </script>
</body>
</html>