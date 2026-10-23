<?php
// delivery_receipt.php
require_once __DIR__ . '/bots.php';
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
    SELECT *
    FROM order_status_history 
    WHERE delivery_number = :delivery_number
    ORDER BY order_id ASC
");
$stmt->execute([':delivery_number' => $deliveryNumber]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format date - FIXED: Remove "at" from the date string
$dateTimeSold = $order['date_time_sold'];
$cleanDateTime = str_replace(' at ', ' ', $dateTimeSold);
$orderDate = new DateTime($cleanDateTime);
$formattedDate = $orderDate->format('n/j/Y');
$formattedTime = $orderDate->format('g:i A');

// ✅ SUB TOTAL — sum of all item rows
$subTotal = 0;
foreach ($items as $item) {
    $subTotal += floatval($item['total_amount'] ?? 0);
}

// ✅ TOTAL — pulled from for_deliveries.total_amount (authoritative total)
$totalSales = floatval($order['total_amount'] ?? 0);

// Fallback: if for_deliveries.total_amount is empty/zero, use sub total
if ($totalSales <= 0) {
    $totalSales = $subTotal;
}

// ✅ Detect discount: compare sub total vs stored total
$discountAmount = 0;
$discountPercent = 0;

if ($totalSales > 0 && $subTotal > 0 && $totalSales < $subTotal - 0.001) {
    $discountAmount  = $subTotal - $totalSales;
    $discountPercent = ($discountAmount / $subTotal) * 100;
}

// ✅ Format discount percent without trailing ".00"
$discountPercentDisplay = rtrim(rtrim(number_format($discountPercent, 2, '.', ''), '0'), '.');
if ($discountPercentDisplay === '' || $discountPercentDisplay === '.') {
    $discountPercentDisplay = '0';
}

// FIXED QR CODE URL - always points to the APK download
$qrCodeUrl = "https://villaruz-print-shop-and-general-merchandise.shop/APK/customer_app.apk";

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
            padding: 8px 13px;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
            border: none;
            white-space: nowrap;
        }

        .items-table th.center {
            text-align: center;
        }

        .items-table th.right {
            text-align: right;
        }

        /* --- THIN ROWS --- */
        .items-table td {
            padding: 6px 13px;
            color: #3a3d42;
            border-bottom: 1px solid #eef0f2;
            font-size: 0.85rem;
            line-height: 1.2;
            vertical-align: middle;
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

        /* ✅ Discount line — red, slightly indented */
        .discount-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 600;
            color: #dc2626;
            margin-bottom: 10px;
            padding-right: 6px;
        }

        .discount-row .value {
            font-weight: 600;
            color: #dc2626;
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

        /* ---------- CUSTOM CONTEXT MENU MODAL ---------- */
        .pdf-tip {
            position: absolute;
            z-index: 9999;
            background: #ffffff;
            color: #2a2a2a;
            border: 1px solid #e6e8ea;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(30, 40, 60, 0.18);
            padding: 12px 16px;
            font-size: 0.78rem;
            line-height: 1.5;
            max-width: 260px;
            display: none;
        }

        .pdf-tip::before {
            content: "";
            position: absolute;
            top: -7px;
            left: 20px;
            width: 12px;
            height: 12px;
            background: #ffffff;
            border-left: 1px solid #e6e8ea;
            border-top: 1px solid #e6e8ea;
            transform: rotate(45deg);
        }

        .pdf-tip .tip-icon {
            color: #f5c518;
            margin-right: 6px;
            font-size: 0.9rem;
        }

        .pdf-tip strong {
            display: block;
            font-size: 0.82rem;
            margin-bottom: 4px;
            color: #2a2a2a;
        }

        .pdf-tip .tip-action {
            color: #4a3a00;
            background: #f5c518;
            border-radius: 3px;
            padding: 2px 7px;
            font-weight: 700;
            font-size: 0.72rem;
            display: inline-block;
            margin-top: 4px;
        }

        /* ---------- TOAST (for Ctrl + P) ---------- */
        .pdf-toast {
            position: fixed;
            top: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(-140%);
            background: #2f3136;
            color: #ffffff;
            padding: 14px 22px;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            font-size: 0.85rem;
            line-height: 1.5;
            z-index: 10000;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            max-width: 480px;
            transition: transform 0.35s ease, opacity 0.35s ease;
            opacity: 0;
        }

        .pdf-toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .pdf-toast .toast-icon {
            color: #f5c518;
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .pdf-toast .toast-body strong {
            display: block;
            font-size: 0.9rem;
            margin-bottom: 3px;
        }

        .pdf-toast .toast-body span {
            color: #c4c8cc;
        }

        .pdf-toast .toast-close {
            margin-left: 8px;
            color: #909599;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            flex-shrink: 0;
        }

        .pdf-toast .toast-close:hover {
            color: #ffffff;
        }

        /* ============================================ */
        /* ============ RESPONSIVE — TABLET =========== */
        /* ============================================ */
        @media (max-width: 900px) {
            .receipt {
                padding: 36px 32px 34px 32px;
            }

            .title-text {
                font-size: 2rem;
                letter-spacing: 2px;
                padding: 0 18px;
            }

            .signature-section {
                gap: 24px;
            }

            .items-table th {
                padding: 7px 10px;
                font-size: 0.8rem;
            }

            .items-table td {
                padding: 4px 10px;
                font-size: 0.8rem;
            }
        }

        /* ============================================ */
        /* ============ RESPONSIVE — MOBILE =========== */
        /* ============================================ */
        @media (max-width: 720px) {
            .signature-section {
                grid-template-columns: 1fr;
                gap: 26px;
            }

            .sign-block .sign-name-line,
            .sign-block .sign-under-label,
            .sign-block .sign-name-line.split-line {
                max-width: 100%;
            }

            .sign-block .sign-name-line {
                min-height: 38px;
                padding-top: 18px;
            }

            .sign-block .sign-name-line.split-line {
                min-height: 38px;
                padding-top: 18px;
                max-width: 100%;
            }

            .sign-block .sign-under-label {
                max-width: 100%;
            }
        }

        @media (max-width: 640px) {
            body {
                padding: 14px 8px;
            }

            .receipt {
                padding: 22px 18px 24px 18px;
                border-radius: 3px;
            }

            .header {
                flex-direction: column;
                gap: 12px;
                margin-bottom: 22px;
            }

            .header-address {
                text-align: left;
                max-width: 100%;
                font-size: 0.7rem;
            }

            .brand-text .name {
                font-size: 0.98rem;
            }

            .brand-text .tin-line {
                font-size: 0.6rem;
            }

            .title-row {
                margin-bottom: 22px;
            }

            .title-text {
                font-size: 1.35rem;
                letter-spacing: 1.5px;
                padding: 0 12px;
            }

            .title-bar {
                height: 9px;
            }

            .bill-row {
                flex-direction: column;
                gap: 14px;
                margin-bottom: 22px;
            }

            .bill-to,
            .meta-table {
                min-width: 100%;
                width: 100%;
            }

            .meta-row {
                font-size: 0.8rem;
                margin-bottom: 7px;
            }

            /* Table — smaller font, tighter padding */
            .items-table {
                font-size: 0.72rem;
                margin-bottom: 24px;
            }

            .items-table th {
                padding: 6px 5px;
                font-size: 0.6rem;
                letter-spacing: 0.3px;
            }

            .items-table td {
                padding: 4px 5px;
                font-size: 0.72rem;
            }

            .bottom-row {
                flex-direction: column;
                gap: 14px;
            }

            .bottom-left,
            .bottom-right {
                min-width: 100%;
                max-width: 100%;
            }

            .subtotal-row {
                font-size: 0.82rem;
            }

            .discount-row {
                font-size: 0.78rem;
            }

            .total-bar {
                padding: 12px 14px;
                margin-top: 12px;
            }

            .total-bar .total-label,
            .total-bar .total-value {
                font-size: 0.9rem;
            }

            .signature-section {
                margin-top: 32px;
                padding-top: 22px;
                gap: 22px;
            }

            .sign-block .sign-role {
                font-size: 0.72rem;
            }

            .sign-block .sign-name-line {
                font-size: 0.82rem;
                min-height: 36px;
                padding-top: 16px;
                max-width: 100%;
            }

            .sign-block .sign-under-label {
                font-size: 0.64rem;
            }

            .qr-wrap img {
                width: 60px;
                height: 60px;
            }

            .qr-wrap .qr-text {
                font-size: 0.66rem;
            }

            .sign-footer {
                margin-top: 30px;
                gap: 10px;
            }

            .sign-footer .sign-label {
                font-size: 0.66rem;
                white-space: normal;
                text-align: center;
                line-height: 1.4;
            }

            /* Tip modal on mobile: smaller, wraps inside screen */
            .pdf-tip {
                max-width: calc(100vw - 32px);
                font-size: 0.72rem;
                padding: 10px 13px;
            }

            .pdf-tip::before {
                left: 16px;
            }

            /* Toast on mobile: full width minus margins */
            .pdf-toast {
                top: 12px;
                max-width: calc(100vw - 20px);
                padding: 12px 16px;
                font-size: 0.78rem;
            }

            .pdf-toast .toast-body strong {
                font-size: 0.82rem;
            }
        }

        /* Extra small phones */
        @media (max-width: 400px) {
            .receipt {
                padding: 18px 14px 20px 14px;
            }

            .title-text {
                font-size: 1.15rem;
                letter-spacing: 1px;
                padding: 0 8px;
            }

            .title-bar {
                height: 7px;
            }

            .items-table th {
                font-size: 0.55rem;
                padding: 5px 3px;
            }

            .items-table td {
                font-size: 0.66rem;
                padding: 3px 3px;
            }

            .sign-block .sign-name-line {
                font-size: 0.78rem;
            }

            .sign-block .sign-name-line.split-line .line-date {
                flex: 0 0 52px;
                font-size: 0.5rem;
            }

            .qr-wrap img {
                width: 54px;
                height: 54px;
            }

            .sign-footer .sign-label {
                font-size: 0.6rem;
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

            .pdf-tip,
            .pdf-toast {
                display: none !important;
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

            .discount-row,
            .discount-row .value {
                color: #dc2626 !important;
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
                        <div class="name">LZK</div>
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
                        <th class="center" style="width:52px;">QTY</th>
                        <th class="center" style="width:56px;">UNIT</th>
                        <th>Item Description</th>
                        <th class="right" style="width:110px;">Unit Price</th>
                        <th class="right" style="width:100px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 16px;">No items found</td>
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
                    <!-- ✅ SUB TOTAL — sum of all item rows -->
                    <div class="subtotal-row">
                        <span>Sub Total :</span>
                        <span class="value">₱ <?php echo number_format($subTotal, 2, '.', ','); ?></span>
                    </div>

                    <!-- ✅ DISCOUNT ROW — only shown when a discount exists -->
                    <?php if ($discountAmount > 0.001): ?>
                        <div class="discount-row">
                            <span>Discounted <?php echo htmlspecialchars($discountPercentDisplay); ?>% :</span>
                            <span class="value">− ₱ <?php echo number_format($discountAmount, 2, '.', ','); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- ✅ TOTAL BAR -->
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

    <!-- Custom right-click tip modal -->
    <div class="pdf-tip" id="pdfTip">
        <i class="fas fa-file-pdf tip-icon"></i>
        <strong>For best print quality</strong>
        Please use the
        <span class="tip-action">📥 Download PDF</span>
        button above instead of printing directly. Your receipt will print clearer and cleaner.
    </div>

    <!-- Toast notification (for Ctrl + P) -->
    <div class="pdf-toast" id="pdfToast">
        <i class="fas fa-print toast-icon"></i>
        <div class="toast-body">
            <strong>Better print quality available</strong>
            <span>Please click the "Download PDF" button above for a cleaner print. Direct printing (Ctrl + P) may reduce print quality.</span>
        </div>
        <span class="toast-close" onclick="hideToast()">&times;</span>
    </div>

    <script>
        /* ---------- PDF DOWNLOAD ---------- */
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

        /* ---------- RIGHT-CLICK TIP MODAL ---------- */
        const pdfTip = document.getElementById('pdfTip');
        let tipTimeout = null;

        document.addEventListener('contextmenu', function (e) {
            // Allow right-click inside the tip itself
            if (pdfTip.contains(e.target)) return;

            e.preventDefault();

            // Position the tip near the cursor
            const tipWidth = Math.min(260, window.innerWidth - 32);
            const tipHeight = 120;
            let left = e.pageX + 12;
            let top = e.pageY + 12;

            // Keep inside viewport
            if (left + tipWidth > window.innerWidth + window.scrollX) {
                left = e.pageX - tipWidth - 12;
            }
            if (left < window.scrollX + 8) {
                left = window.scrollX + 8;
            }
            if (top + tipHeight > window.innerHeight + window.scrollY) {
                top = e.pageY - tipHeight - 12;
            }
            if (top < window.scrollY + 8) {
                top = window.scrollY + 8;
            }

            pdfTip.style.left = left + 'px';
            pdfTip.style.top = top + 'px';
            pdfTip.style.display = 'block';

            // Auto-hide after 3.5 seconds
            clearTimeout(tipTimeout);
            tipTimeout = setTimeout(() => {
                pdfTip.style.display = 'none';
            }, 3500);
        });

        // Hide the tip when clicking anywhere or scrolling
        document.addEventListener('click', function (e) {
            if (!pdfTip.contains(e.target)) {
                pdfTip.style.display = 'none';
            }
        });
        document.addEventListener('scroll', function () {
            pdfTip.style.display = 'none';
        }, true);

        /* ---------- CTRL + P TOAST ---------- */
        const pdfToast = document.getElementById('pdfToast');
        let toastTimeout = null;

        document.addEventListener('keydown', function (e) {
            // Ctrl + P (or Cmd + P on macOS)
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'p') {
                e.preventDefault();
                showToast();
            }
        });

        function showToast() {
            pdfToast.classList.add('show');
            clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => {
                hideToast();
            }, 5000);
        }

        function hideToast() {
            pdfToast.classList.remove('show');
        }
    </script>
</body>

</html>