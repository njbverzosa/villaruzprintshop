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

// Format date - FIXED: Remove "at" from the date string
$dateTimeSold = $order['date_time_sold'];
// Remove the word "at" from the date string for proper parsing
$cleanDateTime = str_replace(' at ', ' ', $dateTimeSold);
$orderDate = new DateTime($cleanDateTime);
$formattedDate = $orderDate->format('n/j/Y');
$formattedTime = $orderDate->format('g:i A');

// Calculate total sales
$totalSales = 0;
foreach ($items as $item) {
    $totalSales += floatval($item['total_amount']);
}

// FIXED QR CODE URL - always points to the APK download
$qrCodeUrl = "https://villaruz-print-shop-and-general-merchandise.shop/APK/sofia.apk";

// Generate QR code image using QR Server API
$qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrCodeUrl);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            background: #f0f2f5;
            font-family: 'Courier New', Courier, monospace;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 12px;
        }

        .wrapper {
            width: 100%;
            max-width: 780px;
        }

        /* RECEIPT CARD */
        .receipt {
            width: 100%;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 16px 20px;
        }

        /* buttons */
        .button-container {
            display: flex;
            justify-content: center;
            margin-bottom: 10px;
        }

        .download-btn {
            color: black;
            border: none;
            padding: 10px 26px;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: background 0.15s;
            background: #14f3e8;
            border-radius: 5px;
        }

        .download-btn:hover {
            background: #0fd4c9;
        }

        /* HEADER */
        .header {
            text-align: center;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .shop-name {
            font-size: 1.4rem;
            font-weight: 800;
            color: #1e1e1e;
            letter-spacing: -0.3px;
            text-transform: uppercase;
            background: #a8eca8;
        }

        .shop-address {
            font-size: 0.95rem;
            color: #3e3e3e;
            font-weight: 500;
            margin-top: 2px;
        }

        .tin {
            font-size: 0.85rem;
            color: #5e5e5e;
            margin-top: 3px;
        }

        .doc-title {
            font-size: 1.3rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 4px;
            color: green;
            display: inline-block;
            padding: 5px 22px;
            margin: 8px 0;
            background: white;
            border: solid 1px #50f379;
        }

        /* info rows */
        .info-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            font-size: 1rem;
            margin-bottom: 6px;
            border-bottom: 1px dotted #ddd;
            padding-bottom: 4px;
            color: #1f1f1f;
        }

        .info-label {
            font-weight: 700;
        }

        .info-value {
            font-weight: 600;
            text-align: right;
            word-break: break-word;
            max-width: 65%;
        }

        /* DR / invoice line */
        .dr-invoice-line {
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
            font-weight: 600;
            color: #333;
            margin: 8px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }

        .invoice-placeholder {
            min-width: 100px;
            display: inline-block;
            text-align: right;
        }

        /* items table with solid 1px green border */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
            margin: 8px 0 6px;
            border: 1px solid green;
        }

        /* all th extra bold */
        .items-table th {
            background: #a8eca8;
            color: #1e1e1e;
            font-weight: 900;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            padding: 8px 5px;
            border: 1px solid #81c784;
            -webkit-text-stroke: 0.3px #1e1e1e;
        }

        .items-table td {
            padding: 8px 5px;
            border: 1px solid #d0d0d0;
            color: #1e1e1e;
            font-weight: 600;
            border-bottom: 1px solid #d0d0d0;
        }

        .qty-cell,
        .unit-cell,
        .price-cell {
            text-align: center;
        }

        .amount-cell {
            text-align: right;
            padding-right: 10px;
            font-weight: 700;
            color: #b22222;
        }

        .article-cell {
            font-weight: 600;
            color: #1e1e1e;
        }

        /* total row */
        .total-row td {
            border-top: 3px solid #b22222;
            border-bottom: none;
            background: #a8eca8;
        }

        .total-label {
            text-align: right;
            font-weight: 800;
            font-size: 1.1rem;
            text-transform: uppercase;
            color: #2e7d32;
            padding-right: 12px;
            letter-spacing: 1px;
        }

        .total-amount {
            text-align: right;
            font-weight: 800;
            font-size: 1.15rem;
            padding-right: 12px;
            color: #b22222;
        }

        /* footer signatures */
        .footer-section {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            margin-top: 18px;
            border-top: 1px dashed #b22222;
            padding-top: 12px;
        }

        .sign-field {
            flex: 1;
            min-width: 150px;
        }

        .sign-field .label {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #66bb6a;
            border-bottom: 1px solid #c8e6c9;
            display: inline-block;
            margin-bottom: 6px;
        }

        .sign-line {
            margin-top: 6px;
            border-bottom: 2px solid #b22222;
            padding-bottom: 3px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e1e1e;
        }

        .prepaid-line {
            font-size: 0.7rem;
            color: #5e5e5e;
            margin-top: 3px;
        }

        /* manager / staff + QR */
        .manager-staff {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-top: 18px;
            padding-top: 12px;
            border-top: 1px dashed #b22222;
            font-size: 0.9rem;
            gap: 12px;
        }

        .person {
            font-weight: 700;
            color: #1e1e1e;
        }

        .title {
            font-size: 0.75rem;
            color: #66bb6a;
            font-weight: 600;
            margin-top: 3px;
        }

        .qr-code-section {
            text-align: center;
        }

        .qr-code-section img {
            width: 75px;
            height: auto;
            border: 2px solid #a5d6a7;
            padding: 3px;
            background: white;
        }

        .qr-label {
            font-size: 0.65rem;
            color: #b22222;
            font-weight: 600;
            margin-top: 3px;
        }

        .no-qr {
            font-size: 0.65rem;
            color: #b22222;
            font-weight: 700;
            border: 1px dashed #b22222;
            padding: 5px 10px;
            background: #a8eca8;
        }

        /* small screen adjustments */
        @media (max-width: 600px) {
            .receipt {
                padding: 12px 14px;
            }

            .shop-name {
                font-size: 1.1rem;
            }

            .doc-title {
                font-size: 1rem;
                letter-spacing: 2px;
                padding: 4px 14px;
            }

            .info-row {
                font-size: 0.85rem;
            }

            .items-table th,
            .items-table td {
                padding: 6px 3px;
                font-size: 0.8rem;
            }

            .total-label {
                font-size: 0.9rem;
            }

            .total-amount {
                font-size: 0.95rem;
            }

            .manager-staff {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
        }

        /* print styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .button-container {
                display: none;
            }

            .receipt {
                box-shadow: none;
                border: 1px solid #ccc;
                padding: 12px;
                max-width: 100%;
            }

            .items-table {
                border: 1px solid green;
            }

            .items-table th {
                background: #a8eca8;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .total-row td {
                background: #a8eca8;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="button-container">
            <button class="download-btn" onclick="downloadReceipt()"><i class="fas fa-download"></i></button>
        </div>

        <div class="receipt" id="receipt-content">
            <div class="header">
                <div class="shop-name">VILLARUZ PRINTSHOP & GENERAL MERCHANDISE</div>
                <div class="shop-address">Poblacion 2411, Dasol, Pangasinan, Philippines</div>
                <div class="tin">VAT Reg. TIN: 257-630-627-00000</div>
            </div>

            <!-- Bordered content block -->
            <div style="border: 1px solid #66bb6a; padding: 12px;">

                <div style="text-align: center;">
                    <div class="doc-title">DELIVERY RECEIPT</div>
                </div>

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
                    <span><strong>DR No.:</strong> _____________</span>
                    <span><strong>Cash Invoice No.</strong> <span
                            class="invoice-placeholder">_____________</span></span>
                </div>

                <!-- Items Table -->
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="font-weight: 900;">Qty</th>
                            <th style="font-weight: 900;">Unit</th>
                            <th style="font-weight: 900;">Item Description</th>
                            <th style="font-weight: 900;">Unit Price</th>
                            <th style="font-weight: 900;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 16px;">No items found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="qty-cell"><?php echo number_format(floatval($item['pieces'])); ?></td>
                                    <td class="unit-cell"><?php echo htmlspecialchars($item['unit'] ?? 'Pcs'); ?></td>
                                    <td class="article-cell"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td class="price-cell">₱
                                        <?php echo number_format(floatval($item['selling_price']), 2, '.', ','); ?>
                                    </td>
                                    <td class="amount-cell">₱
                                        <?php echo number_format(floatval($item['total_amount']), 2, '.', ','); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="4" class="total-label">TOTAL</td>
                            <td class="total-amount">₱
                                <?php echo number_format($totalSales, 2, '.', ','); ?>
                            </td>
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
                        <div class="sign-line">_________________________</div>
                        <div class="prepaid-line">(Receiver's signature / date)</div>
                    </div>
                </div>

                <!-- Manager & Administrative Officer with QR code -->
                <div class="manager-staff">
                    <div>
                        <div class="person">______________</div>
                        <div class="title">Assigned Delivery</div>
                    </div>

                    <div>
                        <div class="person">_______________</div>
                        <div class="title">Received By</div>
                    </div>
                    <?php if (!empty($qrImageUrl)): ?>
                        <div class="qr-code-section">
                            <img src="<?php echo $qrImageUrl; ?>" alt="Delivery QR Code">
                            <div class="qr-label">Download App</div>
                        </div>
                    <?php else: ?>
                        <div class="qr-code-section">
                            <div class="no-qr">No QR Code Available</div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
            <!-- End bordered content block -->
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

            const downloadBtn = document.querySelector('.download-btn');
            const originalText = downloadBtn.innerHTML;
            downloadBtn.innerHTML = 'Generating PDF...';
            downloadBtn.disabled = true;

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