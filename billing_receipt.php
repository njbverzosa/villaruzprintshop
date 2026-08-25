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

// Format date
$dateTimeSold = $order['date_time_sold'];
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
    $qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=" . $qrDataEncoded;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Villaruz Printshop - Delivery Receipt & Billing Statement</title>
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
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .button-container {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 1000;
            display: flex;
            gap: 8px;
        }
        
        .download-btn {
            color: white;
            border: none;
            padding: 8px 18px;
            font-size: 0.75rem;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            border-radius: 30px;
            cursor: pointer;
            box-shadow: 2px 2px 6px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
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

        .bill-container {
            max-width: 650px;
            width: 100%;
            background: white;
            border-radius: 16px;
            box-shadow: 0 15px 25px -10px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            transition: all 0.2s;
            margin-bottom: 1.5rem;
        }

        .bill-paper {
            padding: 1rem 1.2rem 0.8rem 1.2rem;
        }

        .shop-header {
            text-align: center;
            border-bottom: 1.5px solid #1e3a5f;
            margin-bottom: 0.6rem;
            padding-bottom: 0.5rem;
        }

        .shop-name {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: -0.2px;
            color: #0a2f44;
            text-transform: uppercase;
            margin-bottom: 0.15rem;
        }

        .shop-address {
            font-size: 0.6rem;
            color: #2c3e4e;
            font-weight: 500;
            background: #f1f5f9;
            display: inline-block;
            padding: 0.05rem 0.6rem;
            border-radius: 30px;
            margin-bottom: 0.15rem;
        }

        .vat-row {
            font-size: 0.55rem;
            color: #4a627a;
            margin-top: 0.1rem;
            font-weight: 500;
        }

        .doc-title {
            text-align: center;
            margin: 0.3rem 0 0.6rem 0;
        }

        .doc-title h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #1e3a5f;
            letter-spacing: 0.5px;
            border-bottom: 1.5px dotted #cbd5e1;
            display: inline-block;
            padding-bottom: 0.15rem;
        }

        .info-grid {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.4rem;
            background: #f8fafc;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            margin-bottom: 0.6rem;
            border: 1px solid #e2edf2;
        }

        .client-box p, .date-box p {
            margin: 0.1rem 0;
            line-height: 1.2;
        }

        .client-label, .date-label {
            font-weight: 700;
            color: #0f3b5c;
            font-size: 0.55rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .client-name {
            font-weight: 700;
            font-size: 0.75rem;
            color: #1e293b;
        }

        .client-address {
            font-size: 0.6rem;
            color: #334155;
        }

        .date-value {
            font-weight: 600;
            font-size: 0.7rem;
            background: white;
            padding: 0.1rem 0.4rem;
            border-radius: 30px;
            display: inline-block;
            margin-top: 0.1rem;
        }

        /* ========== COMPACT TABLE ========== */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            margin-bottom: 0.6rem;
        }

        .items-table th {
            background-color: #1e3a5f;
            color: white;
            font-weight: 600;
            padding: 3px 3px;
            text-align: center;
            border: 1px solid #2c5282;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .items-table td {
            border: 1px solid #cbd5e6;
            padding: 3px 4px;
            text-align: center;
            vertical-align: middle;
            background-color: white;
            font-size: 8px;
        }

        .items-table td:first-child, .items-table th:first-child {
            text-align: left;
            padding-left: 5px;
        }

        .items-table tr:hover td {
            background-color: #fefce8;
        }

        .amount-cell {
            font-weight: 600;
            font-family: 'Courier New', 'SF Mono', monospace;
            letter-spacing: 0.1px;
            font-size: 8px;
        }

        /* ========== COMPACT TOTALS ========== */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin: 0.3rem 0 0.6rem 0;
        }

        .totals-card {
            width: 220px;
            background: #fef9e3;
            border-radius: 3px;
            padding: 3px 6px;
            border: 1px solid #fdebb3;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.2rem 0;
            font-size: 8px;
            border-bottom: 1px dashed #e2d5b6;
        }

        .total-row:last-child {
            border-bottom: none;
            font-weight: 800;
            font-size: 0.75rem;
            margin-top: 0.1rem;
            padding-top: 0.25rem;
            color: #0f2c3d;
        }

        .total-amount {
            font-weight: 700;
            font-family: monospace;
            letter-spacing: 0.2px;
            font-size: 9px;
        }

        .tax-line {
            color: #b45309;
        }

        .signature-block {
            margin-top: 0.6rem;
            text-align: right;
            border-top: 1px solid #e2e8f0;
            padding-top: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .owner-info {
            text-align: center;
            width: 150px;
        }

        .owner-name {
            font-weight: 800;
            font-size: 7px;
            letter-spacing: 0.3px;
            margin-top: 0.15rem;
            color: #1e3a5f;
            border-top: 1.5px dotted #9bb6c9;
            display: inline-block;
            padding-top: 0.15rem;
        }

        .sign-label {
            font-size: 0.5rem;
            color: #4a5568;
            text-transform: uppercase;
            font-weight: 500;
        }

        .qr-code {
            margin-top: 4px;
            text-align: center;
        }
        .qr-code img {
            width: 40px;
            height: 40px;
            border: 1px solid #ddd;
            padding: 2px;
            background: white;
            border-radius: 4px;
        }
        .qr-label {
            font-size: 9px;
            color: #4a5568;
            margin-top: 1px;
        }

        .receiver-info {
            text-align: center;
            width: 150px;
        }

        .receiver-line {
            margin-top: 0.15rem;
            border-bottom: 1px solid #9bb6c9;
            width: 100%;
            padding-bottom: 0.1rem;
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
                padding: 0.15in;
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
        }

        @media (max-width: 640px) {
            .bill-paper {
                padding: 0.8rem;
            }
            .shop-name {
                font-size: 11px;
            }
            .items-table th, .items-table td {
                font-size: 6.5px;
                padding: 2px 2px;
            }
            .totals-section {
                justify-content: center;
            }
            .totals-card {
                width: 100%;
            }
            .info-grid {
                flex-direction: column;
                gap: 0.2rem;
            }
            .button-container {
                position: static;
                justify-content: center;
                margin-bottom: 0.8rem;
                display: flex;
                gap: 8px;
            }
            .signature-block {
                flex-direction: column;
                gap: 0.8rem;
                align-items: center;
            }
            .bill-container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- BUTTON CONTAINER -->
<div class="button-container">
    <button class="download-btn" onclick="downloadReceipt()">📥 Download PDF</button>
</div>

<!-- BILLING STATEMENT SECTION -->
<div class="bill-container" id="receipt-content">
    <div class="bill-paper">
        
        <div class="shop-header">
            <div class="shop-name">VILLARUZ PRINTSHOP & GENERAL MERCHANDISE</div>
            <div class="shop-address">Poblacion 2411, Dasol, Pangasinan, Philippines</div>
            <div class="vat-row">VAT Reg. TIN: 257-630-627-00000</div>
        </div>

        <div class="doc-title">
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
                <div style="font-size: 0.55rem; color: #64748b; margin-top: 1px;">
                    <?php echo $formattedTime; ?>
                </div>
            </div>
        </div>

        <!-- COMPACT TABLE -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 38%;">Description</th>
                    <th style="width: 10%;">Qty</th>
                    <th style="width: 12%;">Unit</th>
                    <th style="width: 20%;">Unit Price</th>
                    <th style="width: 20%;">Amount</th>
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

        <!-- COMPACT TOTALS -->
        <div class="totals-section">
            <div class="totals-card">
                <div class="total-row">
                    <strong>Sub Total</strong>
                    <span class="total-amount">₱ <?php echo number_format($totalSales); ?></span>
                </div>
                <div class="total-row tax-line">
                    <strong>Withholding Tax (4%)</strong>
                    <span class="total-amount">- ₱ <?php echo number_format($withholdingTax, 2); ?></span>
                </div>
                <div class="total-row">
                    <span style="font-weight: 700; font-size: 10px;">TOTAL AMOUNT DUE</span>
                    <span style="font-weight: 700; font-size: 10px; color: #1e3a5f;">₱ <?php echo number_format($amountDue, 2); ?></span>
                </div>
            </div>
        </div>

        <div class="signature-block">
            <div class="owner-info">
                <div class="sign-label">Prepared by:</div>
                <div class="owner-name">JOSEPH M. VILLARUZ</div>
                <div class="sign-label" style="margin-top: 2px; font-size: 0.45rem;">Owner</div>
                <?php if (!empty($qrImageUrl)): ?>
                    <div class="qr-code">
                        <img src="<?php echo $qrImageUrl; ?>" alt="Delivery QR Code">
                        <div class="qr-label">Scan QR</div>
                    </div>
                <?php else: ?>
                    <div class="qr-code">
                        <div class="qr-label" style="color:#999; font-size: 8px;">(QR not available)</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="receiver-info">
                <div class="sign-label">Received by:</div><br>
                <div class="receiver-line"></div>
                <div class="sign-label" style="margin-top: 2px; font-size: 0.45rem;">Signature over printed name</div>
            </div>
        </div>
    </div>
</div>

<script>
    function downloadReceipt() {
        const element = document.getElementById('receipt-content');
        const downloadBtn = document.querySelector('.download-btn');
        const originalText = downloadBtn.innerHTML;
        
        const opt = {
            margin: [0.3, 0.3, 0.3, 0.3],
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
        
        downloadBtn.innerHTML = '⏳ Generating...';
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
    
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            downloadReceipt();
        }
    });
</script>

</body>
</html>