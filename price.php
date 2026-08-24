<?php
// price.php
session_start();
require_once 'DB_Conn/config.php';

// Get product number from URL parameter
$productNumber = isset($_GET['product_number']) ? trim($_GET['product_number']) : '';

if (empty($productNumber)) {
    die('<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif; color: #ef4444;">
        <i class="fas fa-exclamation-circle" style="font-size: 48px;"></i>
        <h2>Error: Product number is required.</h2>
        <p>Please provide a valid product number.</p>
    </div>');
}

// Fetch product details from merchandise_inventory
$stmt = $pdo->prepare("SELECT * FROM merchandise_inventory WHERE product_number = :product_number");
$stmt->execute([':product_number' => $productNumber]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

// If not found, try with PRD- prefix (for backward compatibility)
if (!$product) {
    if (preg_match('/^PRD(\d+)$/', $productNumber, $matches)) {
        $formattedNumber = 'PRD-' . $matches[1];
        $stmt = $pdo->prepare("SELECT * FROM merchandise_inventory WHERE product_number = :product_number");
        $stmt->execute([':product_number' => $formattedNumber]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (is_numeric($productNumber)) {
        $formattedNumber = 'PRD-' . str_pad($productNumber, 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT * FROM merchandise_inventory WHERE product_number = :product_number");
        $stmt->execute([':product_number' => $formattedNumber]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!$product) {
    die('<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif; color: #ef4444;">
        <i class="fas fa-exclamation-circle" style="font-size: 48px;"></i>
        <h2>Product Not Found</h2>
        <p>Product number: ' . htmlspecialchars($productNumber) . ' not found in inventory.</p>
    </div>');
}

// Format the price for display
$price = number_format($product['selling_price'], 2);
// FIX: Remove number_format() from unit - it's a string, not a number
$unit = htmlspecialchars($product['unit'] ?? '');
$productNumberDisplay = htmlspecialchars($product['product_number'] ?? 'N/A');
$description = htmlspecialchars($product['description'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($product['product_name']); ?> | Price</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        body {
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .price-container {
            width: 100%;
            max-width: 380px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .price-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 40px 30px 35px;
            text-align: center;
            border: 1px solid #e8ecf0;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        }

        /* Top accent line */
        .price-card::before {
            content: '';
            display: block;
            width: 40px;
            height: 3px;
            background: #3b82f6;
            margin: 0 auto 25px;
            border-radius: 2px;
        }

        /* Product Image */
        .product-image {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            border: 2px solid #f1f5f9;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image i {
            font-size: 28px;
            color: #94a3b8;
        }

        /* Product Name */
        .product-name {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 4px;
            letter-spacing: -0.3px;
        }

        /* Product Description - Now displays the unit */
        .product-description {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 12px;
            padding: 0 5px;
            max-width: 100%;
            word-wrap: break-word;
        }

        .product-description.empty {
            color: #94a3b8;
            font-style: italic;
            font-size: 12px;
        }

        /* Product Number - Copyable */
        .product-number {
            font-size: 13px;
            color: #64748b;
            font-family: 'Courier New', monospace;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            padding: 4px 12px 4px 16px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: all;
            position: relative;
        }

        .product-number:hover {
            background: #e2e8f0;
            transform: scale(1.02);
        }

        .product-number:active {
            transform: scale(0.98);
        }

        .product-number .copy-icon {
            font-size: 12px;
            color: #94a3b8;
            transition: color 0.2s;
        }

        .product-number:hover .copy-icon {
            color: #3b82f6;
        }

        .product-number .copy-tooltip {
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            background: #1a1a2e;
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }

        .product-number .copy-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: #1a1a2e;
        }

        .product-number .copy-tooltip.show {
            opacity: 1;
        }

        .product-number .copy-feedback {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            background: rgba(16, 185, 129, 0.15);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            transition: transform 0.3s ease;
        }

        .product-number .copy-feedback.show {
            transform: translate(-50%, -50%) scale(1);
        }

        .product-number .copy-feedback i {
            color: #10b981;
            font-size: 16px;
        }

        /* Divider */
        .price-divider {
            width: 30px;
            height: 2px;
            background: #e2e8f0;
            margin: 0 auto 16px;
        }

        /* Currency and Price */
        .price-display {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 2px;
        }

        .price-currency {
            font-size: 22px;
            font-weight: 600;
            color: #3b82f6;
        }

        .price-amount {
            font-size: 52px;
            font-weight: 700;
            color: #1a1a2e;
            letter-spacing: -1px;
            line-height: 1;
        }

        .price-decimal {
            font-size: 24px;
            font-weight: 600;
            color: #94a3b8;
        }

        /* Price Label */
        .price-label {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 500;
        }

        /* Footer */
        .footer-note {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
            font-size: 11px;
            color: #94a3b8;
        }

        .footer-note i {
            color: #3b82f6;
            margin-right: 4px;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #1a1a2e;
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease, transform 0.3s ease;
            transform: translateX(-50%) translateY(10px);
            z-index: 9999;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 20px;
            }

            .price-card {
                border: 1px solid #e2e8f0;
                box-shadow: none;
            }

            .footer-note {
                display: none;
            }

            .product-number {
                cursor: default;
            }

            .product-number .copy-icon,
            .product-number .copy-tooltip,
            .product-number .copy-feedback {
                display: none !important;
            }
        }

        /* Mobile */
        @media (max-width: 480px) {
            .price-card {
                padding: 30px 20px 25px;
                border-radius: 12px;
            }

            .price-amount {
                font-size: 40px;
            }

            .price-currency {
                font-size: 18px;
            }

            .price-decimal {
                font-size: 20px;
            }

            .product-name {
                font-size: 18px;
            }

            .product-description {
                font-size: 12px;
            }

            .product-image {
                width: 56px;
                height: 56px;
            }

            .product-image i {
                font-size: 24px;
            }

            .product-number {
                font-size: 12px;
                padding: 3px 10px 3px 14px;
            }
        }

        @media (max-width: 360px) {
            .price-amount {
                font-size: 32px;
            }

            .price-currency {
                font-size: 16px;
            }

            .price-decimal {
                font-size: 16px;
            }

            .product-description {
                font-size: 11px;
            }
        }

        /* Prevent all text from being copied on the entire page */
        body {
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }

        /* Allow specific elements to be copyable if needed (e.g., input fields) */
        input,
        textarea,
        [contenteditable="true"] {
            user-select: text;
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
        }
    </style>
</head>

<body>
    <div class="price-container">
        <div class="price-card">
            <!-- Product Image -->
            <div class="product-image">
                <?php if (!empty($product['product_image']) && file_exists('Products/' . $product['product_image'])): ?>
                    <img src="Products/<?php echo htmlspecialchars($product['product_image']); ?>"
                        alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                        onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-box\'></i>'">
                <?php else: ?>
                    <i class="fas fa-box"></i>
                <?php endif; ?>
            </div>

            <!-- Product Name -->
            <h1 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h1>

            <!-- Product Description - Displays the unit -->
            <div class="product-description <?php echo empty($unit) ? 'empty' : ''; ?>">
                <?php if (!empty($unit)): ?>
                    <?php echo nl2br($unit); ?>
                <?php else: ?>
                    <i class="fas fa-info-circle" style="font-size: 11px;"></i> No unit specified
                <?php endif; ?>
            </div>

            <!-- Product Number - Click to Copy -->
            <div class="product-number" onclick="copyProductNumber()" title="Click to copy product number">
                <span class="copy-tooltip" id="copyTooltip">Click to copy</span>
                <span class="copy-feedback" id="copyFeedback"><i class="fas fa-check"></i></span>
                <span id="productNumberText"><?php echo $productNumberDisplay; ?></span>
                <i class="fas fa-copy copy-icon"></i>
            </div>

            <div class="price-divider"></div>

            <!-- Price Display -->
            <div class="price-display">
                <span class="price-currency">₱</span>
                <span class="price-amount"><?php echo number_format(floor($product['selling_price']), 0); ?></span>
                <span class="price-decimal">.<?php echo str_pad(round(($product['selling_price'] - floor($product['selling_price'])) * 100), 2, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="price-label">Selling Price</div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">Copied to clipboard!</div>

    <script>
        function copyProductNumber() {
            const productNumber = document.getElementById('productNumberText').textContent;
            const tooltip = document.getElementById('copyTooltip');
            const feedback = document.getElementById('copyFeedback');
            const toast = document.getElementById('toast');

            // Copy to clipboard
            if (navigator.clipboard) {
                navigator.clipboard.writeText(productNumber).then(() => {
                    showCopyFeedback(tooltip, feedback, toast);
                }).catch(() => {
                    fallbackCopy(productNumber, tooltip, feedback, toast);
                });
            } else {
                fallbackCopy(productNumber, tooltip, feedback, toast);
            }
        }

        function fallbackCopy(text, tooltip, feedback, toast) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showCopyFeedback(tooltip, feedback, toast);
            } catch (err) {
                // Fallback: show alert with the text
                alert('Product Number: ' + text + '\n\nPlease copy it manually.');
            }
            document.body.removeChild(textarea);
        }

        function showCopyFeedback(tooltip, feedback, toast) {
            // Show tooltip feedback
            tooltip.textContent = 'Copied! ✓';
            tooltip.classList.add('show');

            // Show checkmark feedback
            feedback.classList.add('show');

            // Show toast
            toast.textContent = 'Product number copied!';
            toast.classList.add('show');

            // Reset after 2 seconds
            setTimeout(() => {
                tooltip.textContent = 'Click to copy';
                tooltip.classList.remove('show');
                feedback.classList.remove('show');
                toast.classList.remove('show');
            }, 2000);
        }

        // Optional: Show "Click to copy" tooltip on hover
        document.querySelector('.product-number').addEventListener('mouseenter', function() {
            const tooltip = document.getElementById('copyTooltip');
            if (!tooltip.classList.contains('show')) {
                tooltip.textContent = 'Click to copy';
                tooltip.classList.add('show');
            }
        });

        document.querySelector('.product-number').addEventListener('mouseleave', function() {
            const tooltip = document.getElementById('copyTooltip');
            if (tooltip.textContent === 'Click to copy') {
                setTimeout(() => {
                    tooltip.classList.remove('show');
                }, 500);
            }
        });
    </script>
</body>

</html>