<?php
// biometric.php – Biometric enrollment page

session_start();
require_once __DIR__ . '/DB_Conn/config.php';

// Check if user is logged in (via temp session or actual session)
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_user_type'])) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        header('Location: login.php');
        exit;
    } else {
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

        setcookie('user_id', $userId, time() + (86400 * 365), "/");
        setcookie('user_type', $userType, time() + (86400 * 365), "/");

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        html,
        body {
            width: 100%;
            height: 100%;
        }

        body {
            background: white;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Header — only SKIP on the right */
        .page-header {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        /* SKIP link (top-right) */
        .skip-top {
            font-size: 0.9rem;
            font-weight: 600;
            color: #5f6f80;
            text-decoration: none;
            letter-spacing: 0.05em;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: color 0.15s, background 0.15s;
            background: transparent;
            border: none;
        }

        .skip-top:hover {
            color: #1e293b;
            background: #e2e8f0;
        }

        .skip-top:focus-visible {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }

        /* Main content */
        .container {
            flex: 1;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 24px;
        }

        .content {
            max-width: 480px;
            width: 100%;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Lock emoji BELOW header, larger */
        .icon {
            font-size: 140px;
            line-height: 1;
            display: block;
            margin-bottom: 28px;
        }

        .content h2 {
            font-size: 24px;
            color: #0f172a;
            margin-bottom: 12px;
        }

        .content p {
            color: #64748b;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        /* "Note:" paragraph */
        .content p.note {
            margin-top: 20px;
            margin-bottom: 190px;
        }

        /* ✅ Button group — centers the fingerprint button */
        .btn-group {
            margin-top: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
        }

        .btn {
            padding: 16px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            /* ✅ Fixed width for icon-only button */
            width: 60px;
            height: 60px;
            /* ✅ Center horizontally */
            margin: 0 auto;
        }

        .btn-primary {
            background: linear-gradient(145deg, #3b82f6, #6366f1);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        /* Fingerprint icon inside the button */
        .btn-primary i {
            font-size: 28px;
            line-height: 1;
        }

        .status {
            margin-top: 15px;
            padding: 12px;
            border-radius: 8px;
            display: none;
            width: 100%;
        }

        .status.show {
            display: block;
        }

        .status.success {
            background: #f0fdf4;
            color: #065f46;
            border: 1px solid #bbf7d0;
        }

        .status.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .status.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        @media (max-width: 480px) {
            .page-header {
                padding: 0.75rem 1rem;
            }

            .container {
                padding: 24px;
            }

            .icon {
                font-size: 110px;
                margin-bottom: 24px;
            }

            .content h2 {
                font-size: 20px;
            }

            .skip-top {
                font-size: 0.8125rem;
                padding: 0.4rem 0.75rem;
            }

            .btn-primary i {
                font-size: 24px;
            }

            .btn-group {
                margin-top: 80px;
            }

            .btn {
                width: 56px;
                height: 56px;
            }
        }
    </style>
</head>

<body>

    <!-- Header — only SKIP on the right -->
    <header class="page-header">
        <button type="button" class="skip-top" id="skipBtn">SKIP</button>
    </header>

    <!-- Main content with lock below header -->
    <div class="container">
        <div class="content">

            <!-- Lock emoji below header, larger -->
            <div class="icon">🔐</div>

            <h2>Secure Your Account</h2>

            <p>
                Register your fingerprint, PIN, or pattern for faster login next time.
            </p>

            <!-- "Note:" as its own paragraph with CSS margin -->
            <p class="note">
                <strong>Note:</strong> Your biometric data stays on your device.
            </p>

            <div class="btn-group">
                <!-- Only the fingerprint icon -->
                <button class="btn btn-primary" id="biometricBtn" aria-label="Register biometric">
                    <i class="fas fa-fingerprint"></i>
                </button>
            </div>

            <div id="status" class="status"></div>

            <form method="POST" id="biometricForm">
                <input type="hidden" name="biometric_id" id="biometricId">
                <input type="hidden" name="biometric_type" id="biometricType">
            </form>
        </div>
    </div>

    <script>
        const biometricBtn = document.getElementById('biometricBtn');
        const skipBtn = document.getElementById('skipBtn');
        const statusDiv = document.getElementById('status');

        biometricBtn.addEventListener('click', function () {
            if (window.AndroidBiometric) {
                showStatus('Authenticating...', 'info');
                window.AndroidBiometric.enroll();
            } else {
                showStatus('Biometric registration is only available in the app', 'error');
            }
        });

        skipBtn.addEventListener('click', function () {
            document.getElementById('biometricId').value = '';
            document.getElementById('biometricType').value = '';
            document.getElementById('biometricForm').submit();
        });

        function biometricEnrollSuccess(data) {
            showStatus('Biometric registered successfully!', 'success');
            document.getElementById('biometricId').value = data;
            document.getElementById('biometricType').value = 'FINGERPRINT';

            setTimeout(function () {
                document.getElementById('biometricForm').submit();
            }, 1000);
        }

        function biometricEnrollFailed() {
            showStatus('Registration failed. Please try again.', 'error');
        }

        function biometricEnrollCancel() {
            showStatus('Registration canceled.', 'info');
            setTimeout(() => {
                statusDiv.className = 'status';
                statusDiv.textContent = '';
            }, 3000);
        }

        function biometricEnrollError(error) {
            showStatus('Error: ' + error, 'error');
        }

        function showStatus(message, type) {
            statusDiv.textContent = message;
            statusDiv.className = 'status show ' + type;
        }
    </script>
</body>

</html>