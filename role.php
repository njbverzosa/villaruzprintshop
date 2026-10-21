<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Select Role - Villaruz Print Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        html,
        body {
            height: 100%;
            overflow-x: hidden;
        }

        body {
            background: #ffffff;
            color: #1e293b;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
        }

        /* ========== APP CONTAINER ========== */
        .app-shell {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            background: #ffffff;
        }

        /* ========== CONTENT ========== */
        .auth-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            padding: 24px 24px 40px 24px;
        }

        .auth-card {
            width: 100%;
        }

        .auth-logo {
            display: flex;
            justify-content: center;
            margin-top: 10px;
            margin-bottom: 24px;
        }

        .auth-logo img {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }

        .auth-title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
            text-align: center;
            color: #0f172a;
            line-height: 1.2;
        }

        .auth-title span {
            background: linear-gradient(145deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .auth-sub {
            text-align: center;
            color: #64748b;
            margin-bottom: 28px;
            font-size: 14px;
            line-height: 1.5;
            padding: 0 10px;
        }

        /* ========== OPTION LIST ========== */
        .options-list {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* ✅ No hover/active transform or shadow */
        .option-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 18px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            color: #1e293b;
        }

        .option-card-main {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
            min-width: 0;
            text-decoration: none;
            color: inherit;
        }

        .option-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(145deg, #dbeafe, #eff6ff);
            color: #3b82f6;
            font-size: 22px;
            flex-shrink: 0;
        }

        .option-text {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }

        .option-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.2px;
        }

        .option-desc {
            font-size: 13px;
            color: #64748b;
            line-height: 1.4;
        }

        /* ========== VERTICAL DIVIDER ========== */
        .option-divider {
            width: 1px;
            align-self: stretch;
            background: linear-gradient(to bottom,
                    transparent 0%,
                    #cbd5e1 20%,
                    #cbd5e1 80%,
                    transparent 100%);
            flex-shrink: 0;
            margin: 0 4px;
        }

        /* ========== ACTION BUTTONS (Continue Login / Download App) ========== */
        .option-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-shrink: 0;
        }

        /* ✅ No hover/active transform, shadow, or color shift */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            border: 1px solid transparent;
            line-height: 1;
        }

        .action-btn i {
            font-size: 12px;
        }

        .action-login {
            background: linear-gradient(145deg, #3b82f6, #6366f1);
            color: #ffffff;
            border-color: transparent;
        }

        .action-download {
            background: #ffffff;
            color: #3b82f6;
            border-color: #bfdbfe;
        }

        /* ========== DESKTOP VIEW ========== */
        @media (min-width: 600px) {
            body {
                background: #f1f5f9;
                padding-top: 0;
                padding-bottom: 0;
            }

            .app-shell {
                min-height: auto;
                margin: 40px auto;
                border-radius: 5px;
                box-shadow: 0 20px 45px rgba(0, 0, 0, 0.08);
                border: 1px solid #e2e8f0;
                overflow: hidden;
            }

            .auth-container {
                padding: 30px 40px 50px 40px;
            }
        }

        /* ========== SMALL PHONES ========== */
        @media (max-width: 380px) {
            .auth-title {
                font-size: 24px;
            }

            .auth-container {
                padding: 20px 18px 30px 18px;
            }

            .auth-logo img {
                width: 84px;
                height: 84px;
            }

            .option-card {
                padding: 14px 14px;
                gap: 10px;
            }

            .option-icon {
                width: 46px;
                height: 46px;
                font-size: 20px;
            }

            .option-title {
                font-size: 15px;
            }

            .option-desc {
                font-size: 12px;
            }

            .action-btn {
                padding: 7px 10px;
                font-size: 11px;
            }

            .action-btn i {
                font-size: 11px;
            }
        }

        /* ========== SHORT SCREENS ========== */
        @media (max-height: 700px) {
            .auth-logo {
                margin-top: 0;
                margin-bottom: 16px;
            }

            .auth-logo img {
                width: 80px;
                height: 80px;
            }

            .auth-sub {
                margin-bottom: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="app-shell">

        <div class="auth-container">
            <div class="auth-card">

                <div class="auth-logo">
                    <img src="logo/ic_launcher.png" alt="Villaruz Print Shop Logo">
                </div>

                <h2 class="auth-title">Select <span>Role</span></h2>
                <p class="auth-sub">Choose an account type to continue</p>

                <div class="options-list">

                    <!-- ✅ ADMIN -->
                    <div class="option-card">
                        <a href="admins-portal.php" class="option-card-main">
                            <div class="option-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="option-text">
                                <div class="option-title">Admin</div>
                                <div class="option-desc">Manage the system and users</div>
                            </div>
                        </a>

                        <div class="option-divider" aria-hidden="true"></div>

                        <div class="option-actions">
                            <a href="admins-portal.php" class="action-btn action-login">
                                <i class="fas fa-right-to-bracket"></i> Continue Login
                            </a>
                            <a href="https://villaruz-print-shop-and-general-merchandise.shop/APK/admin_app.apk"
                                download class="action-btn action-download">
                                <i class="fas fa-download"></i> Download App
                            </a>
                        </div>
                    </div>

                    <!-- ✅ INVESTORS -->
                    <div class="option-card">
                        <div class="option-card-main">
                            <div class="option-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="option-text">
                                <div class="option-title">Investors</div>
                                <div class="option-desc">Download the app and register to view investments and reports</div>
                            </div>
                        </div>

                        <div class="option-divider" aria-hidden="true"></div>

                        <div class="option-actions">
                            <a href="investors-portal.php" class="action-btn action-login">
                                <i class="fas fa-right-to-bracket"></i> Continue Login
                            </a>
                            <a href="https://villaruz-print-shop-and-general-merchandise.shop/APK/investor_app.apk"
                                download class="action-btn action-download">
                                <i class="fas fa-download"></i> Download App
                            </a>
                        </div>
                    </div>

                    <!-- ✅ CUSTOMERS -->
                    <div class="option-card">
                        <div class="option-card-main">
                            <div class="option-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="option-text">
                                <div class="option-title">Customers</div>
                                <div class="option-desc">Download the app and register to browse our shop</div>
                            </div>
                        </div>

                        <div class="option-divider" aria-hidden="true"></div>

                        <div class="option-actions">
                            <a href="customers-portal.php" class="action-btn action-login">
                                <i class="fas fa-right-to-bracket"></i> Continue Login
                            </a>
                            <a href="https://villaruz-print-shop-and-general-merchandise.shop/APK/customer_app.apk"
                                download class="action-btn action-download">
                                <i class="fas fa-download"></i> Download App
                            </a>
                        </div>
                    </div>

                </div>

            </div>
        </div>

    </div>
</body>

</html>