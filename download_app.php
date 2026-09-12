<?php
//download_app.php – standalone fallback page (same folder as login.php)
session_start();

require_once __DIR__ . '/DB_Conn/config.php';

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isInApp = (strpos($userAgent, 'SofiaApp') !== false);

// ✅ SKIP — set cookie for 1 day, then back to login.php
if (isset($_GET['skip']) && $_GET['skip'] === '1') {
    setcookie(
        'use_old_app',
        '1',
        [
            'expires' => time() + 86400,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
    header('Location: login.php');
    exit;
}

// Latest version — must match login.php and build.gradle
$latestVersion = '21.38.11';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sofia App · Update & Download</title>
    <style>
        /* ... same styles as the modal in login.php ... */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f1f5f9;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 24px 16px;
        }

        .update-card {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            margin: auto;
        }

        .update-header {
            padding: 24px 22px 18px 22px;
            border-bottom: 1px solid #edf2f7;
            text-align: center;
            position: relative;
        }

        .skip-link {
            position: absolute;
            top: 12px;
            right: 14px;
            font-size: 13px;
            font-weight: 700;
            color: #1d4ed8;
            text-decoration: none;
            padding: 6px 12px;
        }

        

        .update-icon {
            display: flex;
            justify-content: center;
            margin-bottom: 10px;
        }

        .update-icon img {
            max-width: 64px;
        }

        .update-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0b1e2e;
        }

        .update-recommended {
            font-size: 14px;
            color: #067bf8;
            margin-top: 12px;
            font-weight: 600;
        }

        .update-body {
            padding: 18px 22px 22px 22px;
        }

        .update-info-block {
            background: #f8fafd;
            border-left: 4px solid #2563eb;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 12px;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .update-info-block strong {
            color: #1e40af;
        }

        .update-info-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #5f6f80;
            margin-bottom: 3px;
        }

        .update-details {
            list-style: none;
            margin: 0 0 18px 0;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .update-details li {
            display: flex;
            justify-content: space-between;
            padding: 10px 14px;
            font-size: 0.83rem;
            border-bottom: 1px solid #edf2f7;
        }

        .update-details li:last-child {
            border-bottom: none;
        }

        .update-details .label {
            color: #5f6f80;
        }

        .update-details .value {
            color: #0b1e2e;
            font-weight: 500;
        }

        .update-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .update-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 13px 20px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            line-height: 1.2;
            width: 100%;
        }

        .update-btn-primary {
            background: #1d4ed8;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(29, 78, 216, 0.2);
        }

        .update-btn-primary:hover {
            background: #1e40af;
        }

        .update-btn-outline {
            background: #ffffff;
            color: #1e2b3c;
            border: 1px solid #cbd5e1;
        }

        .update-btn-outline:hover {
            background: #f8fafd;
        }
    </style>
</head>

<body>
    <div class="update-card">
        <div class="update-header">
            <a href="?skip=1" class="skip-link">SKIP</a>
            <div class="update-icon">
                <img src="logo/ic_launcher.png" alt="Sofia App Logo">
            </div>
            <div class="update-title">SofiaApp</div>
            <div class="update-recommended">Update available — please install the latest version.</div>
        </div>
        <div class="update-body">
            <div class="update-info-block">
                <div class="update-info-label">Update Notice</div>
                A new version of the <strong>Sofia App</strong> is now available. Update or download the latest release.
            </div>
            <ul class="update-details">
                <li>
                    <span class="label">New Release</span>
                    <span class="value"><?php echo htmlspecialchars($latestVersion); ?></span>
                </li>
                <li>
                    <span class="label">Requirements</span>
                    <span class="value">Android 6.0 and above.</span>
                </li>
            </ul>
            <div class="update-actions">
                <a href="https://villaruz-print-shop-and-general-merchandise.shop/APK/sofia_app.apk"
                    class="update-btn update-btn-primary">Download Sofia App</a>
                <a href="https://villaruz-print-shop-and-general-merchandise.shop/APK/sofia_app.apk"
                    class="update-btn update-btn-outline">Re-Install App</a>
            </div>
        </div>
    </div>
</body>

</html>