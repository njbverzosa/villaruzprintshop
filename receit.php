<?php
// delivery_receipt.php
session_start();
require_once 'DB_Conn/config.php';

// Get delivery number from URL parameter
$deliveryNumber = isset($_GET['delivery_number']) ? trim($_GET['delivery_number']) : '';

if (empty($deliveryNumber)) {
    die('<div style="text-align: center; padding: 50px; font-family: monospace;">Error: Delivery number is required.</div>');
}

// Fetch order details from for_deliveries
$stmt = $pdo->prepare("SELECT * FROM for_deliveries WHERE delivery_number = :delivery_number");
$stmt->execute([':delivery_number' => $deliveryNumber]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('<div style="text-align: center; padding: 50px; font-family: monospace;">Error: Order not found for delivery number: ' . htmlspecialchars($deliveryNumber) . '</div>');
}

// Fetch all items from order_status_history with the same delivery_number
$stmt = $pdo->prepare("
    SELECT 
        product_name,
        selling_price,
        unit,
        pieces,
        total_amount,
        qr_code
    FROM order_status_history 
    WHERE delivery_number = :delivery_number
    ORDER BY id ASC
");
$stmt->execute([':delivery_number' => $deliveryNumber]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==============================================
// GET TOTAL FROM FOR_DELIVERIES TABLE
// ==============================================
$totalSales = floatval($order['total_amount'] ?? 0);

// If total is 0 in for_deliveries, calculate from items
if ($totalSales == 0 && !empty($items)) {
    foreach ($items as $item) {
        $totalSales += floatval($item['total_amount'] ?? 0);
    }
}

// If still 0, calculate from selling_price * pieces
if ($totalSales == 0 && !empty($items)) {
    foreach ($items as $item) {
        $sellingPrice = floatval($item['selling_price'] ?? 0);
        $pieces = floatval($item['pieces'] ?? 0);
        $totalSales += $sellingPrice * $pieces;
    }
}

// Get customer name for the received by field
$customerName = htmlspecialchars($order['ordered_by'] ?? '');

// Get QR code URL from database (stored as URL string)
$qrCodeUrl = $order['qr_code'] ?? '';

// Generate QR code image using QR Server API
$qrImageUrl = '';
if (!empty($qrCodeUrl)) {
    // Using QR Server API (free, no API key needed)
    $qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrCodeUrl);
}

// Format date
$dateTimeSold = $order['date_time_sold'];
$cleanDateTime = str_replace(' at ', ' ', $dateTimeSold);
$orderDate = new DateTime($cleanDateTime);
$formattedDate = $orderDate->format('n/j/Y');
$formattedTime = $orderDate->format('g:i A');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Villaruz Print Shop - Delivery Receipt</title>
    <!-- Include html2pdf library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"
        integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f3f3f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: 'Courier New', 'Consolas', 'Monaco', 'Lucida Console', monospace;
            padding: 2rem 1rem;
        }

        /* Receipt card style */
        .receipt {
            max-width: 820px;
            width: 100%;
            background: #fffef7;
            background-image: radial-gradient(circle at 25% 40%, rgba(0, 0, 0, 0.008) 2%, transparent 2.5%);
            background-size: 28px 28px;
            border-radius: 4px 4px 12px 12px;
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.25), 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 1.8rem 2rem 2rem 2rem;
            transition: all 0.2s;
            border: 1px solid #ddd8c5;
        }

        .button-container {
            text-align: center;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .print-btn,
        .download-btn {
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: monospace;
            transition: 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .print-btn {
            background: #3b82f6;
        }

        .print-btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .download-btn {
            background: #10b981;
        }

        .download-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .header {
            text-align: center;
            border-bottom: 2px dashed #bcaea2;
            padding-bottom: 1rem;
            margin-bottom: 1.25rem;
        }

        .shop-name {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #2c2b28;
            font-family: 'Courier New', 'Trebuchet MS', monospace;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }

        .shop-address {
            font-size: 0.8rem;
            color: #4b3f2e;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .tin {
            font-size: 0.7rem;
            color: #5e5340;
            margin-top: 0.2rem;
            font-family: monospace;
        }

        .doc-title {
            text-align: center;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 3px;
            border-bottom: 1px dotted #d4c9b8;
            display: inline-block;
            width: 100%;
            color: #1e2a2f;
        }

        .info-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-bottom: 0.65rem;
            font-size: 0.9rem;
            border-bottom: 1px dotted #e2dbcf;
            padding-bottom: 0.4rem;
        }

        .info-label {
            font-weight: bold;
            min-width: 130px;
            color: #2d3e40;
        }

        .info-value {
            font-weight: 500;
            color: #1f2a2c;
            word-break: break-word;
            text-align: right;
            font-family: monospace;
        }

        .dr-invoice-line {
            display: flex;
            justify-content: space-between;
            margin: 0.5rem 0 1.5rem 0;
            font-size: 10px;
            border-bottom: 1px solid #e2dbcf;
            padding-bottom: 0.5rem;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #cdc2ae;
            padding: 4px 2px;
            vertical-align: top;
        }

        .items-table th {
            background-color: #f6f2ea;
            font-weight: bold;
            text-transform: uppercase;
            color: #2b3b3f;
            font-size: 10px;
            text-align: center;
        }

        .items-table td {
            color: #2f2e2b;
            font-family: 'Courier New', monospace;
        }

        .qty-cell,
        .unit-cell,
        .price-cell {
            text-align: center;
        }

        .article-cell {
            font-weight: 500;
        }

        .amount-cell {
            text-align: right;
            font-weight: 600;
            padding-right: 10px;
        }

        .total-row {
            border-top: 2px solid #bcaea2;
        }

        .total-label {
            text-align: right;
            font-weight: 800;
            font-size: 8px;
            text-transform: uppercase;
            border: none;
            padding-right: 12px;
        }

        .total-amount {
            text-align: right;
            font-weight: 800;
            font-size: 10px;
            border: none;
            padding-right: 12px;
            letter-spacing: 1px;
        }

        .footer-section {
            margin-top: 2rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 1.8rem;
            border-top: 1px dashed #bcaea2;
            padding-top: 1.5rem;
        }

        .sign-field {
            flex: 1;
            min-width: 180px;
        }

        .sign-field .label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #5a4c3a;
            border-bottom: 1px solid #cfc5b4;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .sign-line {
            margin-top: 0.5rem;
            border-bottom: 1px solid #b9aa96;
            padding-bottom: 0.25rem;
            min-width: 160px;
            font-size: 0.75rem;
            color: #6e6252;
            font-style: italic;
        }

        .prepaid-line {
            margin-top: 0.25rem;
            font-size: 0.7rem;
        }

        .manager-staff {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 2rem;
            border-top: 1px dashed #d4c9b8;
            padding-top: 1.2rem;
            font-size: 0.8rem;
        }

        .person {
            font-weight: 700;
            color: #1f2f32;
        }

        .title {
            font-size: 0.7rem;
            color: #6f5e48;
            margin-top: 3px;
        }

        .qr-code-section {
            text-align: center;
            margin-top: 8px;
        }

        .qr-code-section img {
            width: 50px;
            height: auto;
            border: 1px solid #ccc;
            padding: 4px;
            background: white;
            border-radius: 8px;
        }

        .qr-label {
            font-size: 11px;
            color: #6c5b46;
            margin-top: 4px;
        }

        .invoice-placeholder {
            font-family: monospace;
            border-bottom: 1px dotted #b9aa96;
            min-width: 130px;
            display: inline-block;
            text-align: right;
            color: #6c5b46;
        }

        @media (max-width: 650px) {
            .receipt {
                padding: 1rem;
            }

            .items-table th,
            .items-table td {
                padding: 4px 3px;
                font-size: 0.7rem;
            }

            .shop-name {
                font-size: 1.4rem;
            }

            .doc-title {
                font-size: 1.2rem;
            }

            .button-container {
                position: static;
                justify-content: center;
                margin-bottom: 1rem;
            }

            .manager-staff {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .qr-code-section img {
                max-width: 80px;
            }
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .button-container {
                display: none;
            }

            .receipt {
                box-shadow: none;
                border: 1px solid #ccc;
                padding: 0.5rem;
                max-width: 100%;
            }

            .qr-code-section img {
                border: 1px solid #aaa;
            }
        }
    </style>
</head>

<body>
    <div>
        <div class="button-container">
            <button class="download-btn" onclick="downloadReceipt()">📥 Download as PDF</button>
        </div>

        <div class="receipt" id="receipt-content">
            <div class="header">
                <div class="shop-name">VILLARUZ PRINTSHOP & GENERAL MERCHANDISE</div>
                <div class="shop-address">Poblacion 2411, Dasol, Pangasinan, Philippines</div>
                <div class="tin">VAT Reg. TIN: 257-630-627-00000</div>
            </div>

            <div class="doc-title">DELIVERY RECEIPT</div>

            <!-- Customer & Address -->
            <div class="info-row">
                <span class="info-label">Customer's Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($order['ordered_by']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Address:</span>
                <span class="info-value"><?php echo htmlspecialchars($order['delivery_address']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Delivery Date:</span>
                <span class="info-value"><?php echo htmlspecialchars($order['delivery_date']); ?></span>
            </div>

            <div class="dr-invoice-line">
                <span><strong>DR No.: _____________</strong></span>
                <span><strong>Cash Invoice No.</strong> <span class="invoice-placeholder">_____________</span></span>
            </div>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="font-weight:bold;">Qty</th>
                        <th style="font-weight:bold;">Unit</th>
                        <th style="font-weight:bold;">Article (s)</th>
                        <th style="font-weight:bold;">Unit Price</th>
                        <th style="font-weight:bold;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No items found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="qty-cell" style="font-weight:bold;"><?php echo number_format(floatval($item['pieces']), 0); ?></td>
                                <td class="unit-cell" style="font-weight:bold;"><?php echo htmlspecialchars($item['unit'] ?? 'Pcs'); ?></td>
                                <td class="article-cell" style="font-weight:bold;"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="price-cell" style="font-weight:bold;">₱ <?php echo number_format(floatval($item['selling_price']), 2, '.', ','); ?></td>
                                <td class="amount-cell" style="font-weight:bold;">₱ <?php echo number_format(floatval($item['total_amount'] ?? 0), 2, '.', ','); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <!-- TOTAL ROW - Using total from for_deliveries -->
                    <tr class="total-row">
                        <td colspan="4" class="total-label"><strong>TOTAL</strong></td>
                        <td class="total-amount"><strong>₱ <?php echo number_format($totalSales, 2, '.', ','); ?></strong></td>
                    </tr>
                </tfoot>
            </table>

            <!-- Prepaid & received signature row -->
            <div class="footer-section">
                <div class="sign-field">
                    <div class="label">PREPARED by:</div>
                    <div class="sign-line">JOSEPH M. VILLARUZ</div>
                    <div class="prepaid-line">(Signature over Printed Name)</div>
                </div>
                <div class="sign-field">
                    <div class="label">Received in good condition:</div>
                    <div class="sign-line" style="font-weight: bold; font-style: normal; color: #1f2a2c; border-bottom: 1px solid #b9aa96; padding-bottom: 0.25rem; min-width: 160px;">
                        <?php echo $customerName; ?>
                    </div>
                    <div class="prepaid-line">(Receiver's signature / date)</div>
                </div>
            </div>

            <!-- Manager & Administrative Officer with QR code -->
            <div class="manager-staff">
                <div>
                    <div class="person"> &nbsp;&nbsp;______________</div>
                    <div class="title">Assigned Delivery</div>
                </div>

                <div>
                    <div class="person"> &nbsp;&nbsp;_______________</div>
                    <div class="title">Received By</div>
                </div>
                <?php if (!empty($qrImageUrl)): ?>
                    <div class="qr-code-section">
                        <img src="<?php echo $qrImageUrl; ?>" alt="Delivery QR Code">
                        <div class="qr-label">Scan for details</div>
                    </div>
                <?php else: ?>
                    <div class="qr-code-section" style="margin-top: 8px;">
                        <span style="font-size:0.65rem; color:#999;">DO NOT ACCEPT IF NO QR CODE ATTACHED</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function downloadReceipt() {
            const element = document.getElementById('receipt-content');
            const opt = {
                margin: [0.5, 0.5, 0.5, 0.5],
                filename: 'Delivery_Receipt_<?php echo htmlspecialchars($deliveryNumber); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, letterRendering: true, useCORS: true },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };

            // Show loading indicator
            const downloadBtn = document.querySelector('.download-btn');
            const originalText = downloadBtn.innerHTML;
            downloadBtn.innerHTML = '⏳ Generating PDF...';
            downloadBtn.disabled = true;

            // Generate PDF
            html2pdf().set(opt).from(element).save().then(() => {
                downloadBtn.innerHTML = originalText;
                downloadBtn.disabled = false;
            }).catch((error) => {
                console.error('PDF generation error:', error);
                downloadBtn.innerHTML = originalText;
                downloadBtn.disabled = false;
                alert('Error generating PDF. Please try again.');
            });
        }
    </script>
</body>

</html>