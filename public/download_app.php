<?php
//public/download_app.php

session_start();

// ==============================================
// 1. FIX PATHS
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';
include __DIR__ . '/../update_version.php';

// ==============================================
// 2. CHECK LOGIN STATUS
// ==============================================
function isLoggedIn()
{
    return isset($_SESSION['user_role']) &&
        isset($_SESSION['user_id']) &&
        isset($_SESSION['acc_number']);
}

if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Please login first to access the shop.';
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 3. GET USER DATA
// ==============================================
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

$userData = null;
if ($userRole === 'Customer') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, vip FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, role FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 4. UPDATE ONLINE TIME (Customers only)
// ==============================================
date_default_timezone_set('Asia/Manila');
$currentTime = date('M j, g:i A');

if ($userRole === 'Customer') {
    $updateStmt = $pdo->prepare("UPDATE customers SET online_time = ? WHERE id = ?");
    $updateStmt->execute([$currentTime, $userData['id']]);
}

$user = $userData;

// ==============================================
// 5. DETERMINE REDIRECT TARGET
// ==============================================
function getRedirectTarget($userRole, $user)
{
    if ($userRole === 'Admin') {
        return '../web/all_products.php';
    }
    $isGuest = (isset($user['f_name']) && ($user['f_name'] === 'Guest' || empty($user['f_name'])));
    return $isGuest ? '../public/account-edit.php' : '../public/shop.php';
}

// ==============================================
// 6. HANDLE "SKIP" CLICK
// ==============================================
if (isset($_GET['skip']) && $_GET['skip'] === '1') {
    setcookie(
        'update_reminded_version',
        $latestVersion,
        time() + 86400,  // 1 day
        '/'
    );
    header('Location: ' . getRedirectTarget($userRole, $user));
    exit;
}

// ==============================================
// 7. HANDLE "I ALREADY HAVE THE APP" CLICK
// ==============================================
if (isset($_GET['have_app']) && $_GET['have_app'] === '1') {
    setcookie(
        'update_reminded_version',
        $latestVersion,
        time() + (86400 * 365),  // 1 year
        '/'
    );
    header('Location: ' . getRedirectTarget($userRole, $user));
    exit;
}

// ==============================================
// 8. HANDLE AUTO-CONFIRM FROM JS (version matched)
// ==============================================
if (isset($_GET['version_ok']) && $_GET['version_ok'] === '1') {
    setcookie(
        'update_reminded_version',
        $latestVersion,
        time() + (86400 * 365),  // 1 year
        '/'
    );
    header('Location: ' . getRedirectTarget($userRole, $user));
    exit;
}

?>
<!DOCTYPE html>
<html lang='en'>

<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Sofia App · Update & Download</title>
    <style>
        /* RESET & BASE */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            overflow-x: hidden;
        }

        body {
            background: #ffffff;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            line-height: 1.5;
            color: #1e2b3c;
            min-height: 100vh;
        }

        .app-card {
            width: 100%;
            min-height: 100vh;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .app-header {
            padding: 2rem 1.5rem 1.5rem 1.5rem;
            border-bottom: 1px solid #edf2f7;
            text-align: center;
            position: relative;
            background: #ffffff;
        }

        .skip-link {
            position: absolute;
            top: 1rem;
            right: 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #5f6f80;
            text-decoration: none;
            letter-spacing: 0.02em;
            padding: 0.4rem 0.75rem;
            border-radius: 8px;
            transition: color 0.15s, background 0.15s;
            z-index: 10;
        }

        .skip-link:hover {
            color: #1e2b3c;
            background: #e2e8f0;
        }

        .skip-link:focus-visible {
            outline: 2px solid #2563eb;
            outline-offset: 2px;
        }

        .app-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.75rem;
            letter-spacing: -0.02em;
            margin: 0 auto 1rem auto;
        }

        .app-icon img {
            max-width: 72px;
            height: auto;
        }

        .app-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0b1e2e;
            letter-spacing: -0.015em;
        }

        /* ✅ Installed version badge below title */
        .app-installed-version {
            display: inline-block;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            background: #f1f5f9;
            padding: 0.3rem 0.85rem;
            border-radius: 100px;
            border: 1px solid #e2e8f0;
            letter-spacing: 0.02em;
        }

        .app-installed-version.match {
            background: #f0fdf4;
            color: #065f46;
            border-color: #bbf7d0;
        }

        .app-installed-version.mismatch {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .app-subtitle {
            font-size: 0.875rem;
            color: #5f6f80;
            margin-top: 0.5rem;
        }

        .app-recommended {
            font-size: 15px;
            color: #067bf8;
            margin-top: 0.5rem;
        }
        .app-body {
            flex: 1;
            padding: 2rem 1.5rem;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            max-width: 720px;
            margin: 0 auto;
            width: 100%;
        }

        .info-block {
            background: #f8fafd;
            border-left: 4px solid #2563eb;
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9375rem;
            color: #1e2b3c;
        }

        .info-block strong {
            color: #1e40af;
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #5f6f80;
            margin-bottom: 0.25rem;
        }

        .details-list {
            list-style: none;
            margin: 0 0 2rem 0;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .details-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 1rem;
            font-size: 0.875rem;
            border-bottom: 1px solid #edf2f7;
        }

        .details-list li:last-child {
            border-bottom: none;
        }

        .details-list .label {
            color: #5f6f80;
        }

        .details-list .value {
            color: #0b1e2e;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.25rem;
            border-radius: 40px;
            font-weight: 500;
            font-size: 1rem;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            line-height: 1.2;
            transition: background 0.15s, box-shadow 0.15s;
            width: 100%;
        }

        .btn-primary {
            background: #1d4ed8;
            color: #ffffff;
            border-color: #1d4ed8;
            box-shadow: 0 2px 6px rgba(29, 78, 216, 0.2);
        }

        .btn-primary:hover {
            background: #1e40af;
        }

        .btn-outline {
            background: #ffffff;
            color: #1e2b3c;
            border-color: #cbd5e1;
        }

        .btn-outline:hover {
            background: #f8fafd;
        }

        .btn-success {
            background: #16a34a;
            color: #ffffff;
            border-color: #16a34a;
            box-shadow: 0 2px 6px rgba(22, 163, 74, 0.2);
        }

        .btn-success:hover {
            background: #15803d;
        }

        .btn:focus-visible {
            outline: 2px solid #2563eb;
            outline-offset: 2px;
        }

        .app-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid #edf2f7;
            text-align: center;
            font-size: 0.75rem;
            color: #5f6f80;
            background: #ffffff;
        }

        @media (max-width: 480px) {
            .app-header {
                padding: 1.75rem 1.25rem 1.25rem 1.25rem;
            }

            .app-body {
                padding: 1.5rem 1.25rem;
            }

            .app-title {
                font-size: 1.25rem;
            }

            .app-icon img {
                max-width: 64px;
            }

            .skip-link {
                top: 0.75rem;
                right: 0.75rem;
                font-size: 0.8125rem;
                padding: 0.3rem 0.6rem;
            }
        }
    </style>
</head>

<body>

    <div class='app-card'>

        <!-- HEADER -->
        <div class='app-header'>
            <a href="?skip=1" class="skip-link">SKIP</a>

            <div class='app-icon'><img src="logo/ic_launcher.png" alt="Sofia App Logo"></div>
            <div class='app-title'>SofiaApp</div>

            <!-- ✅ Installed version of the user's app -->
            <div class="app-installed-version" id="installedVersionBadge">
                Detecting installed version...
            </div>
            <div class='app-recommended'>Recommended to use</div>
            <div class='app-subtitle'>Villaruz Print Shop &amp; General Merchandise</div>
        </div>

        <!-- BODY -->
        <div class='app-body'>

            <div class='info-block'>
                <div class='info-label'>Update Notice</div>
                A new version of the <strong>Sofia App</strong> is now available. Update or download the latest release
                for
                improved performance, a hassle-free application process, and password-free login using your device
                security.
                Access your account and orders anytime, anywhere — in just one touch. Get the latest features now.
            </div>

            <div class='info-block'>
                <div class='info-label'>Options</div>
                You can also download the latest version of the Sofia App directly from the Login page. If you prefer to
                update later, click the <strong>SKIP</strong> link at the top-right corner — you'll be reminded to
                update
                again next time.
            </div>

            <div class='info-block'>
                <div class='info-label'>How to use it</div>
                After installing the Sofia App, log in once using your account credentials and password to activate
                biometric login. Once enabled, you can sign in with just your fingerprint or PIN.
            </div>

            <ul class='details-list'>
                <li>
                    <span class='label'>New Release</span>
                    <span class='value'><?php echo $latestVersion; ?></span>
                </li>
                <li>
                    <span class='label'>Old Version</span>
                    <span class='value' id="installedVersionValue">—</span>
                </li>
                <li>
                    <span class='label'>Release Date</span>
                    <span class='value'>March 2026</span>
                </li>
                <li>
                    <span class='label'>Requirements</span>
                    <span class='value'>Android 6.0 and above.</span>
                </li>
            </ul>

            <div class='action-buttons'>
                <a href='http://villaruz-print-shop-and-general-merchandise.shop/APK/sofia_app.apk'
                    class='btn btn-primary'>Download Sofia App</a>
                <a href='http://villaruz-print-shop-and-general-merchandise.shop/APK/sofia_app.apk'
                    class='btn btn-outline'>Re-Install App</a>

                <!-- Manual fallback -->
                <a href='?have_app=1' class='btn btn-success'>
                    I Already Have the App
                </a>
            </div>

        </div>

        <!-- FOOTER -->
        <div class='app-footer'>
            &copy; 2026 Villaruz Print Shop &amp; General Merchandise. All rights reserved.
        </div>

    </div>

    <!-- ✅ JS: Read installed version + display + auto-confirm -->
    <script>
        (function () {
            var latestVersion = <?php echo json_encode($latestVersion); ?>;
            var badge = document.getElementById('installedVersionBadge');
            var versionValue = document.getElementById('installedVersionValue');
            var isInApp = typeof window.AndroidBiometric !== 'undefined';

            function updateBadge(installed, matched) {
                if (badge) {
                    badge.textContent = 'Installed: ' + installed;
                    badge.classList.remove('match', 'mismatch');
                    badge.classList.add(matched ? 'match' : 'mismatch');
                }
                if (versionValue) {
                    versionValue.textContent = '' + installed;
                }
            }

            // If not in app → show message
            if (!isInApp) {
                if (badge) {
                    badge.textContent = 'Not running in the app';
                    badge.classList.add('mismatch');
                }
                if (versionValue) {
                    versionValue.textContent = 'N/A (web browser)';
                }
                console.log('🌐 Not in app — no auto-confirm');
                return;
            }

            // In app → read version
            var installed = '';
            try {
                if (window.AndroidBiometric && window.AndroidBiometric.getAppVersion) {
                    installed = window.AndroidBiometric.getAppVersion();
                }
            } catch (e) {
                console.warn('Could not read app version:', e);
            }

            if (!installed || installed === 'unknown') {
                if (badge) {
                    badge.textContent = 'Version unknown';
                    badge.classList.add('mismatch');
                }
                if (versionValue) {
                    versionValue.textContent = 'Unknown';
                }
                console.log('⚠️ Could not read app version');
                return;
            }

            var matched = (installed === latestVersion);
            updateBadge(installed, matched);
            console.log('📱 Installed:', installed, '| Latest:', latestVersion, '| Match:', matched);

            // ✅ Auto-confirm if version matches → set cookie + redirect
            if (matched) {
                console.log('✅ Version matches — auto-confirming in 1s...');
                setTimeout(function () {
                    window.location.href = '?version_ok=1';
                }, 1000);
            }
        })();
    </script>

</body>

</html>