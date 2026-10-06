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
            background: #eef1f4;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            -webkit-font-smoothing: antialiased;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 24px 12px;
        }

        .wrapper {
            width: 100%;
            max-width: 800px;
        }

        /* buttons */
        .button-container {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }

        .download-btn {
            color: #4a3a00;
            border: none;
            padding: 11px 28px;
            font-size: 0.9rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            letter-spacing: 0.4px;
            transition: background 0.18s, transform 0.12s;
            background: #f5c518;
            border-radius: 4px;
            box-shadow: 0 2px 6px rgba(245, 197, 24, 0.35);
        }

        .download-btn:hover {
            background: #e3b414;
            transform: translateY(-1px);
        }

        /* RECEIPT CARD */
        .receipt {
            width: 100%;
            background: #ffffff;
            box-shadow: 0 12px 40px rgba(30, 40, 60, 0.08), 0 2px 8px rgba(30, 40, 60, 0.04);
            padding: 44px 48px 40px 48px;
            border-radius: 4px;
        }

        /* ---------- HEADER ---------- */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 28px;
        }

        .brand-block {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .brand-text .name {
            font-size: 1.05rem;
            font-weight: 800;
            color: #2a2a2a;
            letter-spacing: -0.2px;
        }

        .brand-text .tagline {
            font-size: 0.6rem;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            color: #a0a4a8;
            margin-top: 3px;
        }

        .brand-text .tin-line {
            font-size: 0.65rem;
            color: #909599;
            margin-top: 4px;
            letter-spacing: 0.2px;
        }

        .header-address {
            text-align: right;
            font-size: 0.72rem;
            color: #70757a;
            line-height: 1.5;
            max-width: 240px;
        }

        /* ---------- YELLOW BAR + TITLE ---------- */
        .title-row {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .title-bar {
            flex: 1;
            height: 12px;
            background: #f5c518;
            border-radius: 2px;
        }

        .title-text {
            font-size: 2.3rem;
            font-weight: 800;
            letter-spacing: 2.5px;
            color: #2a2a2a;
            padding: 0 24px;
            line-height: 1;
        }

        /* ---------- ORDERED BY / META ---------- */
        .bill-row {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 24px;
            margin-bottom: 30px;
        }

        .bill-to {
            flex: 1;
            min-width: 240px;
        }

        .bill-to .info-card {
            background: #fafbfc;
            border-left: 3px solid #f5c518;
            padding: 10px 14px;
            margin-bottom: 10px;
            border-radius: 0 4px 4px 0;
        }

        .bill-to .section-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #909599;
            margin-bottom: 4px;
        }

        .bill-to .customer-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2a2a2a;
        }

        .bill-to .customer-line {
            font-size: 0.85rem;
            color: #70757a;
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
            margin-bottom: 9px;
        }

        .meta-row .meta-label {
            font-weight: 700;
            color: #2a2a2a;
        }

        .meta-row .meta-value {
            color: #70757a;
        }

        /* ---------- ITEMS TABLE ---------- */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-bottom: 36px;
            border-radius: 4px;
            overflow: hidden;
        }

        .items-table th {
            background: #2f3136;
            color: #ffffff;
            font-weight: 700;
            text-align: left;
            padding: 11px 13px;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
            border: none;
        }

        .items-table th.center {
            text-align: center;
        }

        .items-table th.right {
            text-align: right;
        }

        .items-table td {
            padding: 12px 13px;
            color: #3a3d42;
            border-bottom: 1px solid #eef0f2;
            font-size: 0.85rem;
        }

        .items-table tbody tr:nth-child(even) td {
            background: #fafbfc;
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
            margin-bottom: 8px;
        }

        .bottom-left {
            flex: 1;
            min-width: 260px;
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
            color: #2a2a2a;
            margin-bottom: 10px;
            padding-right: 6px;
        }

        .subtotal-row .value {
            font-weight: 600;
            color: #70757a;
        }

        .total-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f5c518;
            padding: 15px 20px;
            margin-top: 18px;
            border-radius: 4px;
        }

        .total-bar .total-label {
            font-size: 1rem;
            font-weight: 800;
            color: #4a3a00;
            letter-spacing: 0.5px;
        }

        .total-bar .total-value {
            font-size: 1.05rem;
            font-weight: 800;
            color: #4a3a00;
        }

        /* ---------- SIGNATURE SECTION ---------- */
        .signature-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
            margin-top: 52px;
            padding-top: 32px;
            border-top: 1px solid #eef0f2;
        }

        .sign-block {
            display: flex;
            flex-direction: column;
        }

        .sign-block .sign-role {
            font-size: 0.75rem;
            font-weight: 700;
            color: #2a2a2a;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
        }

        /* underline exactly matches the width of the name inside */
        .sign-block .sign-name-line {
            border-bottom: 1px solid #2a2a2a;
            padding-bottom: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #2a2a2a;
            margin-bottom: 5px;
            min-height: 42px;
            padding-top: 22px;
            display: inline-block;
            width: fit-content;
            max-width: 100%;
        }

        .sign-block .sign-under-label {
            font-size: 0.68rem;
            color: #909599;
            font-style: italic;
            max-width: 340px;
        }

        /* split line: signature (long) / date (short) — extended width */
        .sign-block .sign-name-line.split-line {
            display: flex;
            align-items: flex-end;
            border-bottom: none;
            padding-bottom: 0;
            padding-top: 22px;
            min-height: 42px;
            width: 100%;
            max-width: 340px;
            gap: 6px;
        }

        /* signature half — takes the remaining space */
        .sign-block .sign-name-line.split-line .line-sig {
            flex: 1;
            border-bottom: 1px solid #2a2a2a;
            padding-bottom: 4px;
            min-height: 20px;
        }

        /* date half — short, faded placeholder */
        .sign-block .sign-name-line.split-line .line-date {
            flex: 0 0 62px;
            border-bottom: 1px solid #2a2a2a;
            padding-bottom: 4px;
            min-height: 20px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            font-size: 0.55rem;
            letter-spacing: 0.2px;
            color: #c4c8cc;
            font-weight: 500;
            font-style: italic;
        }

        .sign-block .sign-name-line.split-line .slash {
            font-size: 0.95rem;
            font-weight: 700;
            color: #2a2a2a;
            padding-bottom: 2px;
            line-height: 1;
        }

        /* label under the split line */
        .sign-block .sign-name-line.split-line + .sign-under-label {
            max-width: 340px;
        }

        /* QR block */
        .qr-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 4px;
        }

        .qr-wrap img {
            width: 72px;
            height: 72px;
            border: 1px solid #e6e8ea;
            padding: 4px;
            background: #fff;
            border-radius: 4px;
        }

        .qr-wrap .qr-text {
            font-size: 0.7rem;
            color: #70757a;
            line-height: 1.5;
        }

        .qr-wrap .qr-text strong {
            display: block;
            color: #2a2a2a;
            font-size: 0.78rem;
            margin-bottom: 2px;
        }

        /* ---------- SIGNATURE FOOTER ---------- */
        .sign-footer {
            display: flex;
            align-items: flex-end;
            gap: 14px;
            margin-top: 40px;
        }

        .sign-footer .line {
            flex: 1;
            height: 2px;
            background: #f5c518;
            border-radius: 1px;
        }

        .sign-footer .sign-label {
            font-size: 0.78rem;
            color: #70757a;
            white-space: nowrap;
            border-top: 1px solid #d4d8dc;
            padding-top: 5px;
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 720px) {
            .signature-section {
                grid-template-columns: 1fr;
                gap: 28px;
            }

            .sign-block .sign-name-line,
            .sign-block .sign-under-label,
            .sign-block .sign-name-line.split-line {
                max-width: 100%;
            }
        }

        @media (max-width: 620px) {
            .receipt {
                padding: 26px 20px;
            }

            .header {
                flex-direction: column;
                gap: 14px;
            }

            .header-address {
                text-align: left;
                max-width: 100%;
            }

            .title-text {
                font-size: 1.6rem;
                padding: 0 12px;
                letter-spacing: 1.5px;
            }

            .title-bar {
                height: 9px;
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
                background: #2f3136 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .items-table tbody tr:nth-child(even) td {
                background: #fafbfc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .bill-to .info-card {
                background: #fafbfc !important;
                border-left: 3px solid #f5c518 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .total-bar,
            .title-bar,
            .sign-footer .line {
                background: #f5c518 !important;
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

            <!-- HEADER -->
            <div class="header">
                <div class="brand-block">
                    <div class="brand-text">
                        <div class="name">VILLARUZ PRINTSHOP &</div>
                        <div class="tagline">General Merchandise</div>
                        <div class="tin-line">NON-VAT Reg. TIN: 257-630-627-00000</div>
                    </div>
                </div>
                <div class="header-address">
                    New Public Market, Poblacion, Dasol,<br>
                    Pangasinan, Philippines (2411)
                </div>
            </div>

            <!-- TITLE -->
            <div class="title-row">
                <div class="title-bar"></div>
                <div class="title-text">DELIVERY RECEIPT</div>
                <div class="title-bar"></div>
            </div>

            <!-- ORDERED BY / META -->
            <div class="bill-row">
                <div class="bill-to">
                    <div class="info-card">
                        <div class="section-label">Ordered By</div>
                        <div class="customer-name"><?php echo htmlspecialchars($order['ordered_by']); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="section-label">Delivery Address</div>
                        <div class="customer-line"><?php echo htmlspecialchars($order['delivery_address']); ?></div>
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
                </div>
            </div>

            <!-- ITEMS TABLE -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="center" style="width:70px;">QTY</th>
                        <th class="center" style="width:70px;">UNIT</th>
                        <th>Item Description</th>
                        <th class="right" style="width:110px;">Unit Price</th>
                        <th class="right" style="width:120px;">Amount</th>
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
                                <td class="center"><?php echo number_format(floatval($item['pieces'])); ?></td>
                                <td class="center"><?php echo htmlspecialchars($item['unit'] ?? 'Pcs'); ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="right">₱ <?php echo number_format(floatval($item['selling_price']), 2, '.', ','); ?></td>
                                <td class="right">₱ <?php echo number_format(floatval($item['total_amount']), 2, '.', ','); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- BOTTOM -->
            <div class="bottom-row">
                <div class="bottom-left">
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

                    <div class="total-bar">
                        <span class="total-label">Total :</span>
                        <span class="total-value">₱ <?php echo number_format($totalSales, 2, '.', ','); ?></span>
                    </div>
                </div>
            </div>

            <!-- SIGNATURE SECTION: 3 equal columns -->
            <div class="signature-section">
                <div class="sign-block">
                    <div class="sign-role">Prepared by:</div>
                    <div class="sign-name-line">Joseph M. Villaruz</div>
                    <div class="sign-under-label">(Signature over Printed Name)</div>
                </div>

                <div class="sign-block">
                    <div class="sign-role">Assigned Delivery:</div>
                    <div class="sign-name-line">Corina M. Verzosa</div>
                    <div class="sign-under-label">(Signature over Printed Name)</div>
                </div>

                <div class="sign-block">
                    <div class="sign-role">Received in good condition:</div>
                    <div class="sign-name-line split-line">
                        <span class="line-sig">&nbsp;</span>
                        <span class="slash">/</span>
                        <span class="line-date">MM-DD-YYYY</span>
                    </div>
                    <div class="sign-under-label">(Signature over Printed Name / date)</div>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="sign-footer">
                <div class="line"></div>
                <div class="sign-label">Villaruz Printshop & General Merchandise — System Developer: Norlie Jay Verzosa</div>
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