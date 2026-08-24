<?php
// delivery_receipt.php
session_start();
require_once 'DB_Conn/config.php';

// Get delivery number from URL parameter
$deliveryNumber = isset($_GET['delivery_number']) ? trim($_GET['delivery_number']) : '';

if (empty($deliveryNumber)) {
    die('<div style="text-align: center; padding: 50px; font-family: monospace;">Error: Delivery number is required.</div>');
}

// Fetch order details from for_deliveries (including qr_code)
$stmt = $pdo->prepare("SELECT * FROM for_deliveries WHERE delivery_number = :delivery_number");
$stmt->execute([':delivery_number' => $deliveryNumber]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('<div style="text-align: center; padding: 50px; font-family: monospace;">Error: Order not found for delivery number: ' . htmlspecialchars($deliveryNumber) . '</div>');
}

 $stmt = $pdo->prepare("
    SELECT 
        product_name,
        unit,
        pieces,
        selling_price,
        total_amount,
        date_time_sold
    FROM order_status_history 
    WHERE delivery_number = :delivery_number
    ORDER BY id ASC
");
$stmt->execute([':delivery_number' => $deliveryNumber]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

 $totalSales = 0;
foreach ($items as $item) {
    $totalSales += floatval($item['total_amount']);
}

 $withholdingTax = $totalSales * 0.04;
$amountDue = $totalSales - $withholdingTax;

// Format date - FIXED: Remove "at" from the date string
$dateTimeSold = $order['date_time_sold'];
// Remove the word "at" from the date string for proper parsing
$cleanDateTime = str_replace(' at ', ' ', $dateTimeSold);
$orderDate = new DateTime($cleanDateTime);
$formattedDate = $orderDate->format('n/j/Y');
$fullFormattedDate = $order['delivery_date'];
$formattedTime = $orderDate->format('g:i A');

// QR code generation
$qrImageUrl = '';
$qrData = $order['qr_code'] ?? '';
if (!empty($qrData)) {
    $qrDataEncoded = urlencode($qrData);
    $qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . $qrDataEncoded;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Villaruz Printshop - Delivery Receipt & Billing Statement</title>
    <!-- Include html2pdf library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #e2e8f0;
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, 'Roboto', sans-serif;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* Print button styling */
        .button-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }
        
        .print-btn, .download-btn {
            color: white;
            border: none;
            padding: 10px 24px;
            font-size: 0.9rem;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            border-radius: 40px;
            cursor: pointer;
            box-shadow: 2px 2px 6px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
        }
        
        .print-btn {
            background-color: #2c3e2f;
        }
        
        .print-btn:hover {
            background-color: #1e2a21;
            transform: scale(0.98);
        }
        
        .download-btn {
            background-color: #10b981;
        }
        
        .download-btn:hover {
            background-color: #059669;
            transform: scale(0.98);
        }
        
        .download-btn:disabled {
            background-color: #6b7280;
            cursor: not-allowed;
            transform: none;
        }
        
        .close-btn {
            background-color: #6c5b46;
        }
        
        .close-btn:hover {
            background-color: #5a4c3a;
        }

        /* main bill card */
        .bill-container {
            max-width: 860px;
            width: 100%;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transition: all 0.2s;
            margin-bottom: 2rem;
        }

        /* inner content with padding */
        .bill-paper {
            padding: 2rem 2rem 2rem 2rem;
        }

        /* header section */
        .shop-header {
            text-align: center;
            border-bottom: 2px solid #1e3a5f;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
        }

        .shop-name {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.3px;
            color: #0a2f44;
            text-transform: uppercase;
            margin-bottom: 0.4rem;
        }

        .shop-address {
            font-size: 0.85rem;
            color: #2c3e4e;
            font-weight: 500;
            background: #f1f5f9;
            display: inline-block;
            padding: 0.2rem 1rem;
            border-radius: 40px;
            margin-bottom: 0.5rem;
        }

        .vat-row {
            font-size: 0.75rem;
            color: #4a627a;
            margin-top: 0.25rem;
            font-weight: 500;
        }

        /* document title */
        .doc-title {
            text-align: center;
            margin: 1rem 0 1.5rem 0;
        }

        .doc-title h2 {
            font-size: 1.9rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e3a5f, #2c5282);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            letter-spacing: 1px;
            border-bottom: 3px dotted #cbd5e1;
            display: inline-block;
            padding-bottom: 0.3rem;
        }

        /* client & meta info */
        .info-grid {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.2rem;
            background: #f8fafc;
            padding: 1rem 1.2rem;
            border-radius: 20px;
            margin-bottom: 1.8rem;
            border: 1px solid #e2edf2;
        }

        .client-box p, .date-box p {
            margin: 0.35rem 0;
            line-height: 1.4;
        }

        .client-label, .date-label {
            font-weight: 700;
            color: #0f3b5c;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .client-name {
            font-weight: 700;
            font-size: 1.05rem;
            color: #1e293b;
        }

        .client-address {
            font-size: 0.85rem;
            color: #334155;
        }

        .date-value {
            font-weight: 600;
            font-size: 1rem;
            background: white;
            padding: 0.2rem 0.8rem;
            border-radius: 40px;
            display: inline-block;
            margin-top: 0.2rem;
        }

        .delivery-number {
            font-size: 0.8rem;
            color: #4a627a;
            margin-top: 0.3rem;
        }

        /* table styling */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 1.8rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }

        .items-table th {
            background-color: #1e3a5f;
            color: white;
            font-weight: bold;
            padding: 8px 4px;
            text-align: center;
            border: 1px solid #2c5282;
            font-size: 13px;
        }

        .items-table td {
            border: 1px solid #cbd5e6;
            padding: 10px 8px;
            text-align: center;
            vertical-align: middle;
            background-color: white;
        }

        .items-table td:first-child, .items-table th:first-child {
            text-align: left;
            padding-left: 12px;
        }

        .items-table tr:hover td {
            background-color: #fefce8;
        }

        /* amount formatting */
        .amount-cell {
            font-weight: 600;
            font-family: 'Courier New', 'SF Mono', monospace;
            letter-spacing: 0.3px;
        }

        /* totals section */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin: 1rem 0 1.8rem 0;
        }

        .totals-card {
            width: 320px;
            background: #fef9e3;
            border-radius: 5px;
            padding: 5px 10px;
            border: 1px solid #fdebb3;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.45rem 0;
            font-size: 10px;
            border-bottom: 1px dashed #e2d5b6;
        }

        .total-row:last-child {
            border-bottom: none;
            font-weight: 800;
            font-size: 1.2rem;
            margin-top: 0.25rem;
            padding-top: 0.6rem;
            color: #0f2c3d;
        }

        .total-amount {
            font-weight: 700;
            font-family: monospace;
            letter-spacing: 0.5px;
        }

        .tax-line {
            color: #b45309;
        }

        /* prepared by */
        .signature-block {
            margin-top: 2rem;
            text-align: right;
            border-top: 1px solid #e2e8f0;
            padding-top: 1.8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .owner-info {
            text-align: center;
            width: 240px;
        }

        .owner-name {
            font-weight: 800;
            font-size: 10px;
            letter-spacing: 1px;
            margin-top: 0.5rem;
            color: #1e3a5f;
            border-top: 2px dotted #9bb6c9;
            display: inline-block;
            padding-top: 0.4rem;
        }

        .sign-label {
            font-size: 0.75rem;
            color: #4a5568;
            text-transform: uppercase;
            font-weight: 500;
        }

        /* QR code styling */
        .qr-code {
            margin-top: 12px;
            text-align: center;
        }
        .qr-code img {
            width: 70px;
            height: 70px;
            border: 1px solid #ddd;
            padding: 4px;
            background: white;
            border-radius: 8px;
        }
        .qr-label {
            font-size: 15px;
            color: #4a5568;
            margin-top: 4px;
        }

        /* receiver signature */
        .receiver-info {
            text-align: center;
            width: 240px;
        }

        .receiver-line {
            margin-top: 0.5rem;
            border-bottom: 1px solid #9bb6c9;
            width: 100%;
            padding-bottom: 0.3rem;
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
            .bill-container {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
                margin-bottom: 0;
                page-break-after: avoid;
                break-inside: avoid;
            }
            .bill-paper {
                padding: 0.3in;
            }
            .items-table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .totals-card {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .info-grid {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .qr-code img {
                border: 1px solid #ccc;
                print-color-adjust: exact;
            }
        }

        @media (max-width: 640px) {
            .bill-paper {
                padding: 1.2rem;
            }
            .shop-name {
                font-size: 1.4rem;
            }
            .items-table th, .items-table td {
                font-size: 0.75rem;
                padding: 6px 4px;
            }
            .totals-section {
                justify-content: center;
            }
            .totals-card {
                width: 100%;
            }
            .info-grid {
                flex-direction: column;
                gap: 0.5rem;
            }
            .button-container {
                position: static;
                justify-content: center;
                margin-bottom: 1rem;
                display: flex;
                gap: 10px;
            }
            .signature-block {
                flex-direction: column;
                gap: 1.5rem;
                align-items: center;
            }
        }
    </style>
</head>
<body>

<!-- BUTTON CONTAINER -->
<div class="button-container">
    <button class="download-btn" onclick="downloadReceipt()">📥 Download as PDF</button>
</div>

<!-- BILLING STATEMENT SECTION -->
<div class="bill-container" id="receipt-content">
    <div class="bill-paper">
        
        <div class="shop-header">
            <div class="shop-name">VILLARUZ PRINTSHOP & GENERAL MERCHANDISE</div>
            <div class="shop-address">Poblacion 2411, Dasol, Pangasinan, Philippines</div>
            <div class="vat-row">VAT Reg. TIN: 257-630-627-00000</div>
        </div>

        <!-- <div class="shop-header">
            <div class="shop-name">Sophielisetakated Office & School Supplies</div>
            <div class="shop-address">Gais-Guipe, Dasol, Pangasinan, Philippines</div>
            <div class="vat-row">TIN: 487-060-298</div>
        </div> -->

        <div style="text-align:center;">
            <h3>BILLING RECEIPT</h3>
        </div>

        <div class="info-grid">
            <div class="client-box">
                <div class="client-label">CUSTOMER</div>
                <div class="client-name"><?php echo htmlspecialchars($order['ordered_by']); ?></div>
                <div class="client-address"><?php echo htmlspecialchars($order['delivery_address']); ?></div>
            </div>
            <div class="date-box">
                <div class="date-label">DATE & TIME</div>
                <div class="date-value"><?php echo $fullFormattedDate; ?></div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Unit Price</th>
                    <th>Amount</th>
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
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo intval($item['pieces']); ?></td>
                            <td><?php echo htmlspecialchars($item['unit'] ?? 'Pcs'); ?></td>
                            <td class="amount-cell">₱ <?php echo number_format($item['selling_price']); ?></td>
                            <td class="amount-cell">₱ <?php echo number_format($item['total_amount']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <div class="totals-card">
                <div class="total-row">
                    <strong>Sub Total</strong>
                    <span style="font-size: 15px;" class="total-amount">₱ <?php echo number_format($totalSales); ?></span>
                </div>
                <div class="total-row tax-line">
                    <strong>Withholding Tax (4%)</strong>
                    <span style="font-size: 15px;" class="total-amount">- ₱ <?php echo number_format($withholdingTax, 2); ?></span>
                </div>
                <div class="total-row">
                    <p style="font-size: 15px;" class="total-amount">TOTAL AMOUNT DUE</p>
                    <p style="font-size: 15px;" class="total-amount">  ₱ <?php echo number_format($amountDue, 2); ?></p>
                </div>
            </div>
        </div>

        <div class="signature-block">
            <div class="owner-info">
                <div class="sign-label">Prepared by:</div>
                <div class="owner-name">JOSEPH M. VILLARUZ</div>
                <div class="sign-label" style="margin-top: 5px;">Owner</div>
                <?php if (!empty($qrImageUrl)): ?>
                    <div class="qr-code">
                        <img src="<?php echo $qrImageUrl; ?>" alt="Delivery QR Code">
                        <div class="qr-label">Scan QR for verification</div>
                    </div>
                <?php else: ?>
                    <div class="qr-code">
                        <div class="qr-label" style="color:#999;">(QR code not available)</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="receiver-info">
                <div class="sign-label">Received by:</div><br>
                <div class="receiver-line"></div>
                <div class="sign-label" style="margin-top: 5px;">Signature over printed name</div>
             </div>
        </div>
    </div>
</div>

<script>
    function downloadReceipt() {
        const element = document.getElementById('receipt-content');
        const downloadBtn = document.querySelector('.download-btn');
        const originalText = downloadBtn.innerHTML;
        
        // Configure PDF options
        const opt = {
            margin: [0.5, 0.5, 0.5, 0.5],
            filename: 'Billing_Receipt_<?php echo htmlspecialchars($deliveryNumber); ?>_<?php echo date('Ymd'); ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                letterRendering: true, 
                useCORS: true,
                logging: false
            },
            jsPDF: { 
                unit: 'in', 
                format: 'letter', 
                orientation: 'portrait' 
            }
        };
        
        // Show loading state
        downloadBtn.innerHTML = '⏳ Generating PDF...';
        downloadBtn.disabled = true;
        
        // Generate and download PDF
        html2pdf().set(opt).from(element).save().then(() => {
            // Reset button after successful download
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
        }).catch((error) => {
            console.error('PDF generation error:', error);
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
            alert('Error generating PDF. Please try again or use Print option.');
        });
    }
    
    // Optional: Add keyboard shortcut (Ctrl+P for print, Ctrl+S for download)
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            downloadReceipt();
        } else if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });
</script>

</body>
</html>