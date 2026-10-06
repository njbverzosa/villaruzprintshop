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
            background: #e9e9e9;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 20px 12px;
        }

        .wrapper {
            width: 100%;
            max-width: 800px;
        }

        /* buttons */
        .button-container {
            display: flex;
            justify-content: center;
            margin-bottom: 14px;
        }

        .download-btn {
            color: #1e1e1e;
            border: none;
            padding: 10px 26px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            letter-spacing: 0.4px;
            transition: background 0.15s;
            background: #f7c600;
            border-radius: 3px;
        }

        .download-btn:hover {
            background: #e0b300;
        }

        /* RECEIPT / INVOICE CARD */
        .receipt {
            width: 100%;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
            padding: 40px 44px 36px 44px;
            border-radius: 2px;
        }

        /* ---------- HEADER ---------- */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 26px;
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-mark {
            width: 26px;
            height: 26px;
            position: relative;
        }

        .brand-mark::before,
        .brand-mark::after {
            content: "";
            position: absolute;
            width: 14px;
            height: 20px;
            background: #e02020;
            transform: skewX(-18deg);
        }

        .brand-mark::before {
            left: 0;
            top: 3px;
        }

        .brand-mark::after {
            right: 0;
            top: 0;
            opacity: 0.75;
        }

        .brand-text .name {
            font-size: 1.05rem;
            font-weight: 800;
            color: #2b2b2b;
            letter-spacing: -0.2px;
        }

        .brand-text .tagline {
            font-size: 0.6rem;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            color: #9a9a9a;
            margin-top: 2px;
        }

        /* ---------- YELLOW BAR + INVOICE TITLE ---------- */
        .title-row {
            display: flex;
            align-items: center;
            margin-bottom: 22px;
        }

        .title-bar {
            flex: 1;
            height: 14px;
            background: #f7c600;
        }

        .title-text {
            font-size: 2.4rem;
            font-weight: 800;
            letter-spacing: 2px;
            color: #2b2b2b;
            padding: 0 22px;
            line-height: 1;
        }

        /* ---------- BILL TO / META ---------- */
        .bill-row {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 26px;
        }

        .bill-to {
            flex: 1;
            min-width: 220px;
        }

        .bill-to .section-label {
            font-size: 1rem;
            font-weight: 700;
            color: #2b2b2b;
            margin-bottom: 12px;
        }

        .bill-to .customer-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2b2b2b;
            margin-bottom: 4px;
        }

        .bill-to .customer-line {
            font-size: 0.85rem;
            color: #6b6b6b;
            line-height: 1.5;
        }

        .meta-table {
            min-width: 240px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .meta-row .meta-label {
            font-weight: 700;
            color: #2b2b2b;
        }

        .meta-row .meta-value {
            color: #6b6b6b;
        }

        /* ---------- ITEMS TABLE ---------- */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-bottom: 34px;
        }

        .items-table th {
            background: #1f1f1f;
            color: #ffffff;
            font-weight: 700;
            text-align: left;
            padding: 10px 12px;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
            border: none;
        }

        .items-table th.center {
            text-align: center;
        }

        .items-table td {
            padding: 12px;
            color: #2b2b2b;
            border-bottom: 1px solid #ececec;
            font-size: 0.85rem;
        }

        /* striped rows */
        .items-table tbody tr:nth-child(even) td {
            background: #f7f7f7;
        }

        .items-table td.center {
            text-align: center;
        }

        .items-table td.right {
            text-align: right;
        }

        /* ---------- BOTTOM SECTION ---------- */
        .bottom-row {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 30px;
        }

        .bottom-left {
            flex: 1;
            min-width: 260px;
        }

        .bottom-left h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #2b2b2b;
            margin-bottom: 10px;
        }

        .terms-text {
            font-size: 0.75rem;
            color: #7a7a7a;
            line-height: 1.5;
            margin-bottom: 22px;
            max-width: 320px;
        }

        .payment-info .pay-row {
            font-size: 0.78rem;
            color: #4a4a4a;
            margin-bottom: 4px;
        }

        .payment-info .pay-row span {
            font-weight: 700;
            color: #2b2b2b;
            display: inline-block;
            min-width: 90px;
        }

        .bottom-right {
            flex: 1;
            min-width: 280px;
            max-width: 340px;
        }

        .subtotal-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            font-weight: 700;
            color: #2b2b2b;
            margin-bottom: 10px;
            padding-right: 6px;
        }

        .subtotal-row .value {
            font-weight: 600;
            color: #6b6b6b;
        }

        .total-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f7c600;
            padding: 14px 18px;
            margin-top: 18px;
        }

        .total-bar .total-label {
            font-size: 1rem;
            font-weight: 800;
            color: #2b2b2b;
            letter-spacing: 0.5px;
        }

        .total-bar .total-value {
            font-size: 1.05rem;
            font-weight: 800;
            color: #2b2b2b;
        }

        /* ---------- SIGNATURE FOOTER ---------- */
        .sign-footer {
            display: flex;
            align-items: flex-end;
            gap: 14px;
            margin-top: 60px;
        }

        .sign-footer .line {
            flex: 1;
            height: 2px;
            background: #f7c600;
        }

        .sign-footer .sign-label {
            font-size: 0.78rem;
            color: #2b2b2b;
            white-space: nowrap;
            border-top: 1px solid #bdbdbd;
            padding-top: 4px;
        }

        /* QR block (kept, styled minimal) */
        .qr-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 26px;
        }

        .qr-wrap img {
            width: 70px;
            height: 70px;
            border: 1px solid #e0e0e0;
            padding: 3px;
            background: #fff;
        }

        .qr-wrap .qr-text {
            font-size: 0.7rem;
            color: #6b6b6b;
            line-height: 1.4;
        }

        .qr-wrap .qr-text strong {
            display: block;
            color: #2b2b2b;
            font-size: 0.78rem;
            margin-bottom: 2px;
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 620px) {
            .receipt {
                padding: 24px 18px;
            }

            .title-text {
                font-size: 1.6rem;
                padding: 0 12px;
            }

            .title-bar {
                height: 10px;
            }

            .items-table th,
            .items-table td {
                padding: 8px 6px;
                font-size: 0.72rem;
            }

            .bottom-left,
            .bottom-right {
                min-width: 100%;
            }
        }

        /* ---------- PRINT ---------- */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .button-container {
                display: none;
            }

            .receipt {
                box-shadow: none;
                padding: 20px;
                max-width: 100%;
            }

            .items-table th {
                background: #1f1f1f !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .items-table tbody tr:nth-child(even) td {
                background: #f7f7f7 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .total-bar,
            .title-bar,
            .sign-footer .line {
                background: #f7c600 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="button-container">
            <button class="download-btn" onclick="downloadReceipt()"><i class="fas fa-download"></i> &nbsp;Download PDF</button>
        </div>

        <div class="receipt" id="receipt-content">

            <!-- HEADER: brand + logo -->
            <div class="header">
                <div class="brand-block">
                    <div class="brand-text">
                        <div class="name">VILLARUZ PRINTSHOP</div>
                        <div class="tagline">General Merchandise</div>
                    </div>
                </div>
            </div>

            <!-- YELLOW BAR + TITLE -->
            <div class="title-row">
                <div class="title-bar"></div>
                <div class="title-text">DELIVERY RECEIPT</div>
                <div class="title-bar"></div>
            </div>

            <!-- BILL TO + META -->
            <div class="bill-row">
                <div class="bill-to">
                    <div class="section-label">Deliver To:</div>
                    <div class="customer-name"><?php echo htmlspecialchars($order['ordered_by']); ?></div>
                    <div class="customer-line">
                        <?php echo htmlspecialchars($order['delivery_address']); ?>
                    </div>
                </div>
                <div class="meta-table">
                    <div class="meta-row">
                        <span class="meta-label">DR No.</span>
                        <span class="meta-value">_____________</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Invoice No.</span>
                        <span class="meta-value"><?php echo htmlspecialchars($deliveryNumber); ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Delivery Date</span>
                        <span class="meta-value"><?php echo htmlspecialchars($order['delivery_date']); ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">TIN</span>
                        <span class="meta-value">257-630-627-00000</span>
                    </div>
                </div>
            </div>

            <!-- ITEMS TABLE -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:50px;">SL</th>
                        <th>Item Description</th>
                        <th class="center" style="width:70px;">Unit</th>
                        <th class="center" style="width:60px;">Qty</th>
                        <th class="right" style="width:110px;">Price</th>
                        <th class="right" style="width:120px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 16px;">No items found</td>
                        </tr>
                    <?php else: ?>
                        <?php $sl = 1; foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo $sl++; ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="center"><?php echo htmlspecialchars($item['unit'] ?? 'Pcs'); ?></td>
                                <td class="center"><?php echo number_format(floatval($item['pieces'])); ?></td>
                                <td class="right">₱ <?php echo number_format(floatval($item['selling_price']), 2, '.', ','); ?></td>
                                <td class="right">₱ <?php echo number_format(floatval($item['total_amount']), 2, '.', ','); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- BOTTOM: left info + right totals -->
            <div class="bottom-row">
                <div class="bottom-left">
                    <h3>Thank You For Your Business</h3>

                    <h3 style="margin-top:18px;">Terms &amp; Conditions</h3>
                    <p class="terms-text">
                        Goods received in good order and condition. Please inspect items upon delivery.
                        Any discrepancy must be reported within 24 hours.
                    </p>

                    <div class="payment-info">
                        <h3>Payment Info</h3>
                        <div class="pay-row"><span>Account #:</span> 1210 5210 8542</div>
                        <div class="pay-row"><span>A/C Name:</span> Villaruz Printshop</div>
                        <div class="pay-row"><span>Bank Details:</span> Dasol, Pangasinan Branch</div>
                    </div>

                    <?php if (!empty($qrImageUrl)): ?>
                        <div class="qr-wrap">
                            <img src="<?php echo $qrImageUrl; ?>" alt="Delivery QR Code">
                            <div class="qr-text">
                                <strong>Download App</strong>
                                Scan to get the Sofia app.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bottom-right">
                    <div class="subtotal-row">
                        <span>Sub Total :</span>
                        <span class="value">₱ <?php echo number_format($totalSales, 2, '.', ','); ?></span>
                    </div>
                    <div class="subtotal-row">
                        <span>Tax :</span>
                        <span class="value">0.00%</span>
                    </div>

                    <div class="total-bar">
                        <span class="total-label">Total :</span>
                        <span class="total-value">₱ <?php echo number_format($totalSales, 2, '.', ','); ?></span>
                    </div>
                </div>
            </div>

            <!-- SIGNATURE FOOTER -->
            <div class="sign-footer">
                <div class="line"></div>
                <div class="sign-label">Authorized Sign</div>
                <div class="line"></div>
            </div>

        </div>
    </div>

    <script>
        function downloadReceipt() {
            const element = document.getElementById('receipt-content');
            const opt = {
                margin: [0.4, 0.4, 0.4, 0.4],
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