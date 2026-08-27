<?php
// public/PP_TAC.php - Privacy Policy and Terms & Conditions

session_start();

// ==============================================
// 1. FIX PATHS - config.php is in DB_Conn folder at root level
// ==============================================
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 2. CHECK LOGIN STATUS
// ==============================================
if (!isset($_SESSION['user_role']) || !isset($_SESSION['user_id']) || !isset($_SESSION['acc_number'])) {
    $_SESSION['login_error'] = 'Please login first.';
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 3. GET USER DATA FROM SESSION
// ==============================================
$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number FROM admins WHERE id = ?");
} else {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number FROM customers WHERE id = ?");
}
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Get cart count for bottom nav
$cartStmt = $pdo->prepare("SELECT SUM(pieces) as total_items FROM cart WHERE acc_number = ?");
$cartStmt->execute([$accNumber]);
$cartTotalItems = intval($cartStmt->fetch(PDO::FETCH_ASSOC)['total_items'] ?? 0);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Check if user is VIP
$isVip = isset($user['vip']) && $user['vip'] == 1;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Privacy Policy & Terms | Villaruz Print Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== RESET & BASE ========== */
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
            padding-bottom: 70px;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            padding: 20px;
            background: #f1f5f9;
        }

        /* ========== HEADER ========== */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: #ffffff;
            padding: 18px 25px;
            border-radius: 2px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .welcome h3 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }

        .welcome h3 i {
            color: #3b82f6;
            margin-right: 8px;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f1f5f9;
            padding: 6px 14px 6px 10px;
            border-radius: 5px;
        }

        .user-badge .avatar {
            width: 32px;
            height: 32px;
            border-radius: 20px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }

        .user-badge .name {
            font-size: 13px;
            font-weight: 500;
            color: #0f172a;
        }

        /* ========== POLICY CONTAINER ========== */
        .policy-container {
            max-width: 950px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 35px 40px;
        }

        .policy-container .policy-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }

        .policy-container .policy-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 5px;
        }

        .policy-container .policy-header h2 i {
            color: #3b82f6;
            margin-right: 10px;
        }

        .policy-container .policy-header .last-updated {
            color: #94a3b8;
            font-size: 13px;
        }

        .policy-container .policy-section {
            margin-bottom: 35px;
            padding-bottom: 30px;
            border-bottom: 1px solid #f1f5f9;
        }

        .policy-container .policy-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .policy-container .policy-section h4 {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .policy-container .policy-section h4 i {
            color: #3b82f6;
            font-size: 20px;
            width: 28px;
            text-align: center;
        }

        .policy-container .policy-section p {
            color: #475569;
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 12px;
        }

        .policy-container .policy-section ul {
            list-style: none;
            padding: 0;
            margin: 10px 0 15px 0;
        }

        .policy-container .policy-section ul li {
            color: #475569;
            font-size: 14px;
            line-height: 1.8;
            padding: 5px 0 5px 28px;
            position: relative;
        }

        .policy-container .policy-section ul li::before {
            content: '▸';
            position: absolute;
            left: 0;
            color: #3b82f6;
            font-weight: bold;
        }

        .policy-container .policy-section ul li strong {
            color: #0f172a;
        }

        .policy-container .highlight-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .policy-container .highlight-box p {
            color: #1e40af;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .policy-container .highlight-box i {
            color: #3b82f6;
            margin-right: 8px;
        }

        .policy-container .highlight-box.warning {
            background: #fef3c7;
            border-left-color: #f59e0b;
        }

        .policy-container .highlight-box.warning p {
            color: #92400e;
        }

        .policy-container .highlight-box.warning i {
            color: #f59e0b;
        }

        .policy-container .highlight-box.success {
            background: #d1fae5;
            border-left-color: #10b981;
        }

        .policy-container .highlight-box.success p {
            color: #065f46;
        }

        .policy-container .highlight-box.success i {
            color: #10b981;
        }

        .policy-container .highlight-box.danger {
            background: #fee2e2;
            border-left-color: #ef4444;
        }

        .policy-container .highlight-box.danger p {
            color: #991b1b;
        }

        .policy-container .highlight-box.danger i {
            color: #ef4444;
        }

        /* ========== BOTTOM NAV ========== */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 0 12px;
            z-index: 1000;
            box-shadow: 0 -2px 15px rgba(0, 0, 0, 0.06);
            height: 65px;
        }

        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            color: #525f70;
            text-decoration: none;
            transition: all 0.3s ease;
            padding: 4px 16px;
            position: relative;
            min-width: 56px;
        }

        .bottom-nav .nav-item i {
            font-size: 25px;
            transition: all 0.3s ease;
        }

        .bottom-nav .nav-item span {
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .bottom-nav .nav-item:hover,
        .bottom-nav .nav-item.active {
            color: #3b82f6;
        }

        .bottom-nav .nav-item .badge {
            position: absolute;
            top: 0;
            right: 4px;
            background: lightgreen;
            color: #020e20;
            font-size: 14px;
            font-weight: bold;
            padding: 1px 6px;
            border-radius: 20px;
            min-width: 12px;
            text-align: center;
            line-height: 14px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .dashboard-header {
                padding: 14px 18px;
            }

            .welcome h3 {
                font-size: 17px;
            }

            .policy-container {
                padding: 25px 20px;
            }

            .policy-container .policy-header h2 {
                font-size: 22px;
            }

            .policy-container .policy-section h4 {
                font-size: 16px;
            }

            .policy-container .policy-section p {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 12px;
            }

            body {
                padding-bottom: 60px;
            }

            .dashboard-header {
                padding: 12px 14px;
            }

            .welcome h3 {
                font-size: 15px;
            }

            .user-badge .avatar {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }

            .user-badge .name {
                font-size: 11px;
            }

            .policy-container {
                padding: 16px 12px;
                border-radius: 8px;
            }

            .policy-container .policy-header h2 {
                font-size: 18px;
            }

            .policy-container .policy-header .last-updated {
                font-size: 11px;
            }

            .policy-container .policy-section {
                margin-bottom: 25px;
                padding-bottom: 20px;
            }

            .policy-container .policy-section h4 {
                font-size: 14px;
            }

            .policy-container .policy-section h4 i {
                font-size: 16px;
                width: 22px;
            }

            .policy-container .policy-section p {
                font-size: 13px;
                line-height: 1.6;
            }

            .policy-container .policy-section ul li {
                font-size: 13px;
                line-height: 1.6;
                padding: 4px 0 4px 24px;
            }

            .policy-container .highlight-box {
                padding: 12px 14px;
            }

            .policy-container .highlight-box p {
                font-size: 13px;
            }

            .bottom-nav {
                padding: 4px 0 8px;
                height: 56px;
            }

            .bottom-nav .nav-item {
                padding: 2px 6px;
                min-width: 36px;
            }

            .bottom-nav .nav-item i {
                font-size: 18px;
            }

            .bottom-nav .nav-item span {
                font-size: 9px;
            }

            .bottom-nav .nav-item .badge {
                font-size: 10px;
                min-width: 14px;
                line-height: 14px;
                top: -2px;
                right: 0px;
                padding: 0 5px;
            }
        }

        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .bottom-nav {
                padding-bottom: calc(12px + env(safe-area-inset-bottom));
            }
        }
         /* VIP Avatar Styles */
        .user-badge .avatar.vip {
            background: linear-gradient(135deg, #f59e0b, #f97316) !important;
            font-size: 12px;
            font-weight: 700;
        }

        .user-badge .vip-badge i {
            font-size: 10px;
        }
    </style>
</head>

<body>

    <!-- ========== MAIN CONTENT ========== -->
    <main class="main-content">

        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome">
                <h3><i class="fas fa-file-contract"></i> Privacy Policy & Terms</h3>
            </div>
            <div class="user-badge">
                <div class="avatar <?php echo (isset($user['vip']) && $user['vip'] == 1) ? 'vip' : ''; ?>">
                    <?php
                    $isVip = isset($user['vip']) && $user['vip'] == 1;

                    if ($isVip):
                        ?>
                        <i class="fas fa-crown"></i>
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['f_name'] ?? 'G', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <span class="name"><?php echo htmlspecialchars($user['f_name'] ?? 'Guest'); ?></span>
            </div>
        </div>

        <!-- Policy Content -->
        <div class="policy-container">
            <div class="policy-header">
                <h2><i class="fas fa-shield-alt"></i> Privacy Policy & Terms</h2>
                <div class="last-updated">Last Updated: <?php echo date('F d, Y'); ?></div>
            </div>

            <!-- ========== SECTION 1: INTRODUCTION ========== -->
            <div class="policy-section">
                <h4><i class="fas fa-info-circle"></i> Introduction</h4>
                <p>Welcome to Villaruz Print Shop & General Merchandise. These Terms and Conditions govern your use of our website, mobile application, and services. By accessing or using our platform, you agree to be bound by these terms, our Privacy Policy, and all applicable laws and regulations.</p>
                
                <div class="highlight-box">
                    <p><i class="fas fa-check-circle"></i> Our platform provides legitimate e-commerce services for buying and selling products. All transactions are documented, verifiable, and transparent.</p>
                </div>
            </div>

            <!-- ========== SECTION 2: PRIVACY POLICY ========== -->
            <div class="policy-section">
                <h4><i class="fas fa-lock"></i> Privacy Policy</h4>
                
                <p><strong>Information We Collect</strong></p>
                <ul>
                    <li><strong>Personal Information:</strong> Full name, email address, phone number, delivery address, and date of birth</li>
                    <li><strong>Account Information:</strong> Username, password, account number, and account activity</li>
                    <li><strong>Transaction Data:</strong> Purchase history, order details, payment records, and delivery information</li>
                    <li><strong>Payment Information:</strong> Payment method details, transaction IDs, and billing information</li>
                    <li><strong>Device Information:</strong> IP address, browser type, device type, and access times</li>
                    <li><strong>Communication Records:</strong> Customer service interactions, chat logs, and email correspondence</li>
                </ul>

                <p><strong>How We Use Your Information</strong></p>
                <ul>
                    <li><strong>Order Processing:</strong> To process, fulfill, and deliver your orders</li>
                    <li><strong>Account Management:</strong> To create and manage your account, verify identity, and prevent fraud</li>
                    <li><strong>Communication:</strong> To send order confirmations, updates, and promotional materials (with consent)</li>
                    <li><strong>Service Improvement:</strong> To analyze usage patterns and improve our products and services</li>
                    <li><strong>Legal Compliance:</strong> To comply with applicable laws, regulations, and legal requests</li>
                </ul>

                <div class="highlight-box">
                    <p><i class="fas fa-shield-alt"></i> Your personal information is stored securely and protected against unauthorized access. We use industry-standard encryption and security protocols.</p>
                </div>

                <p><strong>Information Sharing</strong></p>
                <ul>
                    <li>We do not sell, rent, or trade your personal information to third parties</li>
                    <li>We may share information with trusted partners to fulfill orders (e.g., delivery services)</li>
                    <li>Information may be disclosed if required by law or to protect our legal rights</li>
                    <li>All third-party partners are bound by confidentiality agreements</li>
                </ul>

                <p><strong>Data Security</strong></p>
                <ul>
                    <li>All sensitive data is encrypted using SSL/TLS technology</li>
                    <li>We implement strict access controls and authentication measures</li>
                    <li>Regular security audits and vulnerability assessments are conducted</li>
                    <li>You can request to delete your account and personal data at any time</li>
                </ul>

                <div class="highlight-box success">
                    <p><i class="fas fa-check-circle"></i> Legitimate proof of transaction is provided for every purchase, ensuring transparency and trust between buyers and sellers.</p>
                </div>
            </div>

            <!-- ========== SECTION 3: E-COMMERCE TERMS ========== -->
            <div class="policy-section">
                <h4><i class="fas fa-shopping-cart"></i> E-Commerce & Selling Terms</h4>

                <p><strong>Buying Products</strong></p>
                <ul>
                    <li>All products listed on our platform are subject to availability</li>
                    <li>Prices are displayed in Philippine Peso (₱) and are subject to change without notice</li>
                    <li>You must provide accurate and complete information when placing orders</li>
                    <li>Orders are considered final once payment has been confirmed</li>
                    <li>Delivery timelines are estimates and may vary based on location</li>
                </ul>

                <p><strong>Selling Products</strong></p>
                <ul>
                    <li>Sellers must provide accurate product descriptions and images</li>
                    <li>Product prices must be fair and clearly indicated</li>
                    <li>Inventory levels must be updated regularly to prevent overselling</li>
                    <li>All products sold must be legitimate and meet quality standards</li>
                    <li>Sellers must process orders promptly and communicate with buyers</li>
                </ul>

                <div class="highlight-box warning">
                    <p><i class="fas fa-exclamation-triangle"></i> No adjustments or modifications related to gambling or pandaraya (fraud/deception) are permitted on this platform. All transactions must be legitimate and verifiable.</p>
                </div>

                <p><strong>Payments & Pricing</strong></p>
                <ul>
                    <li>All payments must be made through approved payment methods</li>
                    <li>Prices are inclusive of applicable taxes unless stated otherwise</li>
                    <li>Promotional discounts and vouchers are subject to specific terms</li>
                    <li>Refunds are processed according to our refund policy</li>
                    <li>Proof of payment and transaction receipts are provided for all purchases</li>
                </ul>

                <p><strong>Delivery & Shipping</strong></p>
                <ul>
                    <li>Delivery fees are calculated based on location and order value</li>
                    <li>Estimated delivery times are provided at checkout</li>
                    <li>You must provide accurate delivery information</li>
                    <li>We are not responsible for delays caused by external factors</li>
                    <li>Proof of delivery is provided for all shipped orders</li>
                </ul>
            </div>

            <!-- ========== SECTION 4: MONEY & TRANSACTIONS ========== -->
            <div class="policy-section">
                <h4><i class="fas fa-money-bill-wave"></i> Money & Transaction Policies</h4>

                <p><strong>Payment Security</strong></p>
                <ul>
                    <li>All financial transactions are processed through secure and encrypted systems</li>
                    <li>We use trusted payment gateways to ensure your financial information is protected</li>
                    <li>Your payment details are never stored on our servers</li>
                    <li>Transaction records are maintained for auditing and dispute resolution</li>
                </ul>

                <div class="highlight-box success">
                    <p><i class="fas fa-file-invoice"></i> The system provides legitimate services with proper proof of transaction. All purchases and services rendered are documented and verifiable.</p>
                </div>

                <p><strong>Refund Policy</strong></p>
                <ul>
                    <li>Refund requests must be submitted within 7 days of receiving the product</li>
                    <li>Products must be in their original condition for refund eligibility</li>
                    <li>Processing fees may apply for canceled orders</li>
                    <li>Refunds are processed within 5-7 business days</li>
                    <li>All refunds are documented and tracked</li>
                </ul>

                <p><strong>Dispute Resolution</strong></p>
                <ul>
                    <li>Any disputes must be reported within 7 days of transaction</li>
                    <li>Both parties must provide evidence and documentation</li>
                    <li>We act as impartial mediators in disputes</li>
                    <li>Resolution decisions are final and binding</li>
                    <li>All dispute records are maintained for reference</li>
                </ul>

                <div class="highlight-box">
                    <p><i class="fas fa-balance-scale"></i> All terms and conditions must be followed by both Admin and Customers to ensure a fair, transparent, and trustworthy environment for everyone.</p>
                </div>
            </div>

            <!-- ========== SECTION 5: LEGAL & COMPLIANCE ========== -->
            <div class="policy-section">
                <h4><i class="fas fa-gavel"></i> Legal & Compliance</h4>

                <p><strong>User Responsibilities</strong></p>
                <ul>
                    <li><strong>Admins:</strong> Must ensure fair and transparent operations, maintain data accuracy, and comply with all regulations</li>
                    <li><strong>Customers:</strong> Must provide accurate information, pay for purchased items, and use the platform responsibly</li>
                    <li>All users must follow the rules and guidelines set forth on this website</li>
                    <li>Users must not engage in fraudulent, deceptive, or illegal activities</li>
                    <li>All terms must be followed by both Admin and Customers</li>
                </ul>

                <p><strong>Prohibited Activities</strong></p>
                <ul>
                    <li><strong>No Gambling:</strong> Gambling or betting activities are strictly prohibited on this platform</li>
                    <li><strong>No Fraud:</strong> Pandaraya (fraudulent or deceptive activities) are not permitted</li>
                    <li><strong>No Unauthorized Access:</strong> Accessing other users' accounts without permission is prohibited</li>
                    <li><strong>No Malicious Content:</strong> Distributing harmful, defamatory, or malicious content is not allowed</li>
                    <li><strong>No Price Manipulation:</strong> Manipulating prices or product availability is forbidden</li>
                </ul>

                <div class="highlight-box danger">
                    <p><i class="fas fa-ban"></i> Violation of these terms may result in immediate account suspension or termination. We reserve the right to take legal action against fraudulent activities.</p>
                </div>

                <p><strong>Intellectual Property</strong></p>
                <ul>
                    <li>All content on this website is protected by copyright and intellectual property laws</li>
                    <li>You may not reproduce, distribute, or modify content without permission</li>
                    <li>Product images and descriptions are owned by their respective sellers</li>
                    <li>Brand names and logos are registered trademarks</li>
                </ul>

                <p><strong>Data Privacy Compliance</strong></p>
                <ul>
                    <li>We comply with the Data Privacy Act of 2012 (Republic Act No. 10173)</li>
                    <li>You have the right to access, correct, and delete your personal data</li>
                    <li>You can opt-out of marketing communications at any time</li>
                    <li>We implement measures to protect your data from breaches</li>
                </ul>

                <div class="highlight-box">
                    <p><i class="fas fa-handshake"></i> We are committed to maintaining your trust by protecting your personal information and ensuring fair business practices.</p>
                </div>
            </div>

            <!-- ========== SECTION 6: PERMISSIONS & CONSENT ========== -->
            <div class="policy-section">
                <h4><i class="fas fa-hand-peace"></i> Permissions & Consent</h4>

                <p><strong>By using our platform, you consent to:</strong></p>
                <ul>
                    <li>Providing accurate and truthful information during registration and transactions</li>
                    <li>Receiving order confirmations, updates, and promotional communications</li>
                    <li>Storing your account information for future transactions</li>
                    <li>Processing your personal data as described in our Privacy Policy</li>
                    <li>Adhering to all terms and conditions outlined in this document</li>
                    <li>Respecting the rights and property of other users and the platform</li>
                </ul>

                <p><strong>We have permission to:</strong></p>
                <ul>
                    <li>Verify your identity and account information for security purposes</li>
                    <li>Process transactions and maintain records of your activity</li>
                    <li>Communicate with you regarding your orders and account</li>
                    <li>Suspend or terminate accounts that violate these terms</li>
                    <li>Update and modify these terms with appropriate notice</li>
                </ul>

                <div class="highlight-box success">
                    <p><i class="fas fa-check-double"></i> By continuing to use our platform, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions and our Privacy Policy.</p>
                </div>
            </div>

            <!-- ========== SECTION 7: ACCOUNT & TERMINATION ========== -->
            <div class="policy-section">
                <h4><i class="fas fa-user-shield"></i> Account & Termination</h4>

                <p><strong>Account Registration</strong></p>
                <ul>
                    <li>You must be at least 18 years old to create an account</li>
                    <li>All information provided must be accurate and complete</li>
                    <li>You are responsible for maintaining the confidentiality of your credentials</li>
                    <li>You are liable for all activities conducted under your account</li>
                    <li>Immediate notification is required for any unauthorized access</li>
                </ul>

                <p><strong>Account Suspension & Termination</strong></p>
                <ul>
                    <li>Accounts may be suspended for violations of these terms</li>
                    <li>Fraudulent or deceptive activities will result in immediate termination</li>
                    <li>You may request account deletion at any time</li>
                    <li>Terminated accounts will have their data retained for legal compliance</li>
                    <li>Appeals can be submitted through our support channels</li>
                </ul>

                <div class="highlight-box warning">
                    <p><i class="fas fa-exclamation-circle"></i> All terms and conditions must be followed by both Admin and Customers. Any violation may result in account suspension or termination without prior notice.</p>
                </div>
            </div>

            <!-- ========== SECTION 8: CHANGES & UPDATES ========== -->
            <div class="policy-section">
                <h4><i class="fas fa-sync-alt"></i> Changes & Updates</h4>

                <p>We reserve the right to update or modify these Terms and Conditions at any time without prior notice. Changes will be effective immediately upon posting on this page. Your continued use of the platform constitutes acceptance of the updated terms.</p>
                
                <ul>
                    <li>Significant changes will be communicated via email or website notification</li>
                    <li>It is your responsibility to review these terms periodically</li>
                    <li>Disagreement with any changes may result in account termination</li>
                    <li>Last updated date will be displayed at the top of this page</li>
                </ul>

                <div class="highlight-box">
                    <p><i class="fas fa-info-circle"></i> We encourage you to review this page regularly to stay informed about our policies and practices.</p>
                </div>
            </div>

            <!-- ========== SECTION 9: CONTACT ========== -->
            <div class="policy-section">
                <h4><i class="fas fa-envelope"></i> Contact Us</h4>
                <p>If you have any questions, concerns, or feedback regarding these Terms and Conditions, Privacy Policy, or any other matter, please don't hesitate to contact us:</p>
                <ul>
                    <li><strong>📍 Store Address:</strong> New Public Market, Dasol, Pangasinan</li>
                    <li><strong>📞 Phone Number:</strong> (123) 456-7890</li>
                    <li><strong>✉️ Email:</strong> info@villaruzprintshop.com</li>
                    <li><strong>🕐 Business Hours:</strong> Monday - Friday, 8:00 AM - 5:00 PM</li>
                </ul>

                <div class="highlight-box success">
                    <p><i class="fas fa-check-circle"></i> Thank you for trusting Villaruz Print Shop & General Merchandise. We are committed to providing you with legitimate, secure, and reliable services.</p>
                </div>
            </div>

        </div>

    </main>

    <!-- ========== BOTTOM NAVIGATION ========== -->
    <nav class="bottom-nav">
        <a href="shop.php" class="nav-item">
            <i class="fas fa-store"></i>
            <span>Shop</span>
        </a>
        <a href="cart.php" class="nav-item">
            <i class="fas fa-shopping-cart"></i>
            <span>Cart</span>
            <?php if ($cartTotalItems > 0): ?>
                <span class="badge"><?php echo $cartTotalItems; ?></span>
            <?php endif; ?>
        </a>
        <a href="orders.php" class="nav-item">
            <i class="fas fa-truck"></i>
            <span>Orders</span>
        </a>
        
        <a href="account.php" class="nav-item">
            <i class="fas fa-th-large"></i>
            <span>Services</span>
        </a>
        <a href="closed.php" class="nav-item" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <script>
        console.log('📜 Privacy Policy & Terms page loaded');
        console.log('👤 User:', '<?php echo htmlspecialchars($user['f_name'] ?? 'Guest'); ?>');
        console.log('📧 Account:', '<?php echo htmlspecialchars($accNumber); ?>');
        console.log('📅 Last Updated:', '<?php echo date('F d, Y'); ?>');
    </script>

</body>

</html>