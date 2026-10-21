<?php
// web/pending_orders.php
session_start();

require_once __DIR__ . '/../DB_Conn/config.php';

function isLoggedIn()
{
    return isset($_SESSION['user_role']) &&
        isset($_SESSION['user_id']) &&
        isset($_SESSION['acc_number']);
}

if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Please login first to access the shop.';
    header('Location: ../admins-portal.php');
    exit;
}

$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

$userData = null;
if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, role, user_name, authorize_access FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../admins-portal.php');
    exit;
}

$user = $userData;

$selectedDeliveryNumber = isset($_GET['delivery_number']) ? $_GET['delivery_number'] : '';

if (empty($selectedDeliveryNumber)) {
    exit;
}

$stmt = $pdo->prepare("
    SELECT * FROM order_status_history 
    WHERE delivery_number = ? 
    ORDER BY order_id ASC, id ASC
");
$stmt->execute([$selectedDeliveryNumber]);
$orderItems = $stmt->fetchAll();

if (empty($orderItems)) {
    $stmt = $pdo->query("SELECT * FROM order_status_history LIMIT 1");
    $columns = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($columns) {
        $availableColumns = array_keys($columns);
        $possibleColumns = ['delivery_num', 'delivery_id', 'order_number', 'order_id'];
        foreach ($possibleColumns as $col) {
            if (in_array($col, $availableColumns)) {
                $stmt = $pdo->prepare("SELECT * FROM order_status_history WHERE $col = ? ORDER BY order_id ASC, id ASC");
                $stmt->execute([$selectedDeliveryNumber]);
                $orderItems = $stmt->fetchAll();
                if (!empty($orderItems)) break;
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM for_deliveries WHERE delivery_number = ?");
$stmt->execute([$selectedDeliveryNumber]);
$deliveryInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deliveryInfo && !empty($orderItems)) {
    $firstItem = $orderItems[0];
    $deliveryInfo = [
        'ordered_by' => $firstItem['ordered_by'] ?? ($firstItem['customer_name'] ?? 'Unknown Customer'),
        'delivery_m_y' => $firstItem['delivery_m_y'] ?? '',
        'status' => $firstItem['status'] ?? 'PENDING',
        'delivery_number' => $selectedDeliveryNumber,
        'delivery_date' => $firstItem['delivery_date'] ?? null,
        'total_amount' => $firstItem['total_amount'] ?? 0
    ];
}

if (!$deliveryInfo) {
    $deliveryInfo = [
        'ordered_by' => 'Customer Information Not Found',
        'delivery_m_y' => '',
        'status' => 'PENDING',
        'delivery_number' => $selectedDeliveryNumber,
        'delivery_date' => null,
        'total_amount' => 0
    ];
}

$customerName = $deliveryInfo['ordered_by'] ?? '';
if (empty($customerName) && !empty($orderItems)) {
    if (isset($orderItems[0]['ordered_by'])) {
        $customerName = $orderItems[0]['ordered_by'];
    } elseif (isset($orderItems[0]['customer_name'])) {
        $customerName = $orderItems[0]['customer_name'];
    } else {
        $customerName = 'Unknown Customer';
    }
}

$monthYear = $deliveryInfo['delivery_m_y'] ?? '';
$deliveryNumber = $deliveryInfo['delivery_number'] ?? $selectedDeliveryNumber;
$currentStatus = $deliveryInfo['status'] ?? 'PENDING';
$deliveryAddress = $deliveryInfo['delivery_address'] ?? '';

$totalFromRows = 0;
foreach ($orderItems as $item) {
    $totalFromRows += floatval($item['total_amount'] ?? 0);
}

$totalAmount = isset($deliveryInfo['total_amount']) && floatval($deliveryInfo['total_amount']) > 0
    ? floatval($deliveryInfo['total_amount'])
    : $totalFromRows;

$subTotalFromRows = $totalFromRows;
$discountAmount = 0;
$discountPercent = 0;

if ($totalAmount > 0 && $subTotalFromRows > 0 && $totalAmount < $subTotalFromRows - 0.001) {
    $discountAmount  = $subTotalFromRows - $totalAmount;
    $discountPercent = ($discountAmount / $subTotalFromRows) * 100;
}

$discountPercentDisplay = rtrim(rtrim(number_format($discountPercent, 2, '.', ''), '0'), '.');
if ($discountPercentDisplay === '' || $discountPercentDisplay === '.') {
    $discountPercentDisplay = '0';
}

$encodedMonthYear = urlencode($monthYear);

function formatDeliveryDate($date)
{
    if (empty($date) || $date === '0000-00-00' || $date === '1970-01-01') {
        return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false || $timestamp <= 0) {
        return '';
    }

    return date('j F Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Pending Orders | <?= htmlspecialchars($selectedDeliveryNumber) ?> | Villaruz Print Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow-x: hidden;

            /* ✅ Soft modern mesh gradient background */
            background-color: #eef2ff;
            background-image:
                radial-gradient(at 8% 12%, rgba(139, 92, 246, 0.22) 0px, transparent 55%),
                radial-gradient(at 92% 6%,  rgba(59, 130, 246, 0.20) 0px, transparent 55%),
                radial-gradient(at 78% 88%, rgba(236, 72, 153, 0.14) 0px, transparent 55%),
                radial-gradient(at 12% 92%, rgba(16, 185, 129, 0.14) 0px, transparent 55%),
                linear-gradient(135deg, #f8fafc 0%, #eef2ff 45%, #f5f3ff 100%);
            background-attachment: fixed;
        }

        /* ✅ Subtle floating blobs for depth */
        body::before,
        body::after {
            content: "";
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            z-index: 0;
            pointer-events: none;
            opacity: 0.55;
        }

        body::before {
            width: 480px;
            height: 480px;
            top: -160px;
            left: -140px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.45), transparent 70%);
            animation: floatBlob 18s ease-in-out infinite;
        }

        body::after {
            width: 520px;
            height: 520px;
            bottom: -200px;
            right: -180px;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.32), transparent 70%);
            animation: floatBlob 22s ease-in-out infinite reverse;
        }

        @keyframes floatBlob {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50%      { transform: translate(30px, -30px) scale(1.08); }
        }




        .app-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* ========== SIDEBAR ========== */
        .sidebar-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            z-index: 1000;
            transition: transform 0.3s ease;
            transform: translateX(0);
        }

        .side-menu {
            width: 280px;
            height: 100vh;
            background: #ffffff;
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e2e8f0;
            overflow-y: auto;
            position: relative;
        }

        @media (max-width: 768px) {
            .sidebar-wrapper {
                transform: translateX(-100%);
            }

            .sidebar-wrapper.open {
                transform: translateX(0);
            }
        }

        @media (min-width: 769px) {
            .sidebar-wrapper {
                transform: translateX(0) !important;
            }

            .main-content {
                margin-left: 280px;
                padding: 30px;
            }

            .burger-btn {
                display: none !important;
            }

            .menu-overlay {
                display: none !important;
            }

            .sidebar-close-btn {
                display: none !important;
            }
        }

        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
            z-index: 999;
            display: none;
        }

        .menu-overlay.active {
            display: block;
        }

        .burger-btn {
            background: none;
            border: none;
            color: #3b82f6;
            font-size: 24px;
            cursor: pointer;
            padding: 5px 10px;
            display: none;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .burger-btn:hover {
            color: #2563eb;
            transform: scale(1.05);
        }

        .burger-btn i {
            font-size: 24px;
        }

        @media (max-width: 768px) {
            .burger-btn {
                display: flex;
            }
        }

        .sidebar-close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: #64748b;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s;
            display: none;
            z-index: 10;
        }

        .sidebar-close-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        @media (max-width: 768px) {
            .sidebar-close-btn {
                display: block;
            }
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
                margin-left: 0 !important;
                padding-top: 20px;
            }
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: #ffffff;
            padding: 20px 30px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .welcome h4 {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
        }

        .welcome h4 a {
            text-decoration: none;
            color: #0f172a;
            transition: color 0.3s;
        }

        .welcome h4 a:hover {
            color: #f59e0b;
        }

        .menu-header {
            padding: 25px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            flex-shrink: 0;
            padding-right: 50px;
        }

        .menu-header .user-name {
            font-weight: 700;
            font-size: 18px;
            color: #0f172a;
            margin-top: 8px;
        }

        .menu-header .user-greeting {
            font-size: 13px;
            color: #64748b;
        }

        .menu-header i {
            font-size: 40px;
            color: #3b82f6;
        }

        .menu-nav {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        /* ========== ORDERS ========== */
        .orders-container {
            background: #ffffff;
            border-radius: 5px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .delivery-group {
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            overflow: hidden;
        }

        /* ✅ Scroll container */
        .orders-table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
        }

        /* ✅ Inner wrapper — width = widest child (table), never smaller than viewport.
              inline-flex so it hugs content; max-content so it grows with the table.
              This makes the header and table share EXACTLY the same width. */
        .orders-scroll-inner {
            display: inline-flex;
            flex-direction: column;
            min-width: 100%;
            width: max-content;
            vertical-align: top;
        }

        /* ✅ Header fills the wrapper exactly — same width as table */
        .delivery-header {
            background: black;
            color: white;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: nowrap;
            gap: 20px;
            width: 100%;
            box-sizing: border-box;
        }

        /* ✅ DISPLAY MODE — inline, single line */
        .delivery-header .customer-info {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            gap: 15px;
            align-items: center;
            min-width: 0;
        }

        /* ✅ EDIT MODE — stack vertically so Address goes UNDER Ordered By */
        body.edit-active .delivery-header .customer-info {
            flex-direction: column;
            flex-wrap: nowrap;
            align-items: flex-start;
            gap: 8px;
        }

        .delivery-header .info-row {
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .delivery-header .delivery-date-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex-shrink: 0;
        }

        .delivery-header i {
            margin-right: 5px;
        }

        /* ✅ EDIT INPUTS */
        .edit-input {
            background: white;
            color: #1e293b;
            padding: 4px 8px;
            border: 2px solid #8b5cf6;
            border-radius: 6px;
            font-size: 13px;
            width: 100%;
            max-width: 300px;
            display: none;
        }

        .edit-input:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        #deliveryDateInput {
            background: white;
            color: #1e293b;
            padding: 4px 8px;
            border: 2px solid #8b5cf6;
            border-radius: 6px;
            font-size: 13px;
            display: none;
        }

        #deliveryDateInput:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        /* ✅ Table fills wrapper; keeps min-width so it doesn't collapse on desktop */
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 780px;
        }

        .orders-table tr {
            border-bottom: 1px solid #f1f5f9;
        }

        .orders-table tr:last-child {
            border-bottom: none;
        }

        .orders-table td {
            padding: 12px 15px;
            color: #1e293b;
            font-size: 13px;
            white-space: nowrap;
            vertical-align: middle;
        }

        .orders-table tr:hover {
            background: #f8fafc;
        }

        .subtotal-row {
            background: #f8fafc;
            font-weight: 500;
            font-size: 12.5px;
        }

        .subtotal-row td {
            padding: 6px 15px;
            border-top: 1px dashed #e2e8f0;
        }

        .subtotal-row:first-of-type td {
            border-top: 1px solid #e2e8f0;
        }

        .discount-row {
            background: #fef2f2;
        }

        .discount-row td {
            color: #dc2626;
        }

        .total-row {
            font-weight: 600;
        }

        .total-row td {
            padding: 12px 15px;
        }

        .orders-table td.order-id {
            width: 50px;
            text-align: center;
            color: #64748b;
            font-weight: 600;
            font-size: 12px;
        }

        .orders-table td.drag-handle {
            width: 36px;
            padding: 12px 6px !important;
            color: #94a3b8;
            user-select: none;
            text-align: center;
        }

        .orders-table td.drag-handle i {
            font-size: 14px;
        }

        .orders-table tr[draggable="true"] {
            cursor: grab;
        }

        .orders-table tr.dragging {
            opacity: 0.4;
            background: #f0f9ff;
        }

        .orders-table tr.drag-over {
            border-top: 2px solid #3b82f6 !important;
            background: #eff6ff;
        }

        .orders-table tr.drag-over-bottom {
            border-bottom: 2px solid #3b82f6 !important;
            background: #eff6ff;
        }

        .orders-table tr.dragging:hover {
            background: #f0f9ff;
        }

        @media (max-width: 480px) {
            .delivery-header {
                padding: 10px 15px;
                gap: 12px;
            }

            .delivery-header .customer-info {
                flex-direction: row;
                flex-wrap: nowrap;
                align-items: center;
            }

            body.edit-active .delivery-header .customer-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .delivery-header .info-row {
                font-size: 12px;
                white-space: nowrap;
                flex-shrink: 0;
            }

            .delivery-header i {
                font-size: 12px;
                width: 16px;
                margin-right: 3px;
            }

            .edit-input {
                max-width: 100%;
            }

            .orders-table td.drag-handle {
                width: 28px;
                padding: 4px 4px !important;
            }

            .orders-table td.drag-handle i {
                font-size: 12px;
            }

            .orders-table td.order-id {
                width: 36px;
                font-size: 10px;
            }
        }

        /* ========== BUTTONS ========== */
        .receipt-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: white;
            color: black;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid #ccc;
            font-size: 14px;
            white-space: nowrap;
        }

        .receipt-btn:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        .receipt-btn i {
            font-size: 14px;
        }

        .receipt-btn.active-copy,
        .receipt-btn.active-copy:hover {
            background: transparent !important;
            border-color: transparent !important;
            color: #16a34a !important;
            transform: none !important;
        }

        .status-wrapper {
            position: relative;
        }

        .status-select {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: white;
            color: black;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        .status-select:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        .status-select option {
            background: white;
            color: #1e293b;
            padding: 10px;
        }

        .status-wrapper i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            pointer-events: none;
            font-size: 14px;
        }

        .receipt-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .receipt-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                padding: 15px;
            }

            .receipt-actions .receipt-btn {
                width: 100%;
                justify-content: center;
                margin: 0;
            }

            .status-wrapper,
            .status-select {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .receipt-actions {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                padding: 12px;
            }

            .receipt-btn,
            .status-select {
                padding: 8px 12px;
                font-size: 11px;
            }

            .receipt-btn i {
                font-size: 12px;
            }
        }

        @media (max-width: 380px) {
            .receipt-actions {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }

        /* ========== EDIT MODE — SLIDE-IN ANIMATION ========== */
        @keyframes rowSlideIn {
            from {
                transform: translateX(80px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes inputSlideIn {
            from {
                transform: translateX(30px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .orders-table tr.edit-row {
            animation: rowSlideIn 0.3s ease forwards;
            background: #fffbeb;
        }

        .orders-table tr.edit-row td {
            padding: 10px 10px;
            vertical-align: middle;
            white-space: nowrap;
        }

        .orders-table tr.edit-row .editable-input {
            animation: inputSlideIn 0.35s ease forwards;
            min-width: 110px;
            white-space: nowrap;
        }

        .orders-table tr.edit-row td:nth-child(3) .editable-input {
            min-width: 240px;
        }

        .orders-table tr.edit-row td:nth-child(3) .editable-input { animation-delay: 0.03s; }
        .orders-table tr.edit-row td:nth-child(4) .editable-input { animation-delay: 0.06s; }
        .orders-table tr.edit-row td:nth-child(5) .editable-input { animation-delay: 0.09s; }
        .orders-table tr.edit-row td:nth-child(6) .editable-input { animation-delay: 0.12s; }
        .orders-table tr.edit-row td:nth-child(7) .editable-input { animation-delay: 0.15s; }

        .editable-input,
        .editable-select {
            width: 100%;
            padding: 6px 10px;
            border: 2px solid #8b5cf6;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .editable-input:focus,
        .editable-select:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        @media (max-width: 768px) {
            .orders-table tr.edit-row td:nth-child(3) .editable-input {
                min-width: 180px;
            }
            .orders-table tr.edit-row .editable-input {
                min-width: 90px;
                font-size: 12px;
            }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }

        .empty-state i {
            font-size: 80px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 16px;
            color: #64748b;
        }

        #total-amount-display {
            font-weight: 700;
            display: inline-block;
        }

        #total-amount-input {
            display: none;
            width: 100%;
            padding: 6px 10px;
            border: 2px solid #8b5cf6;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            background: #fff;
            color: #1e293b;
            transition: border-color 0.2s, box-shadow 0.2s;
            min-width: 110px;
        }

        #total-amount-input:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
        }

        body.edit-active #total-amount-display {
            display: none;
        }

        body.edit-active #total-amount-input {
            display: block;
            animation: inputSlideIn 0.35s ease forwards;
        }

        /* ========== SYSTEM MODAL ========== */
        .system-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', 'Poppins', system-ui, monospace;
        }

        .system-modal {
            background: #f0f0f0;
            min-width: 380px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.5);
            border: 2px solid #3a6ea5;
            border-radius: 0;
            overflow: hidden;
            animation: modalAppear 0.2s ease;
        }

        @keyframes modalAppear {
            from {
                transform: scale(0.95);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .system-modal-header {
            background: #3a6ea5;
            color: white;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        .system-modal-header i {
            font-size: 14px;
        }

        .system-modal-header span {
            flex: 1;
        }

        .system-modal-content {
            padding: 25px 20px;
            background: white;
            border-bottom: 1px solid #ccc;
        }

        .system-modal-message {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            color: #000;
            line-height: 1.5;
        }

        .system-modal-message i {
            font-size: 32px;
        }

        .system-modal-message.warning i {
            color: #ff9800;
        }

        .system-modal-message.error i {
            color: #f44336;
        }

        .system-modal-message.success i {
            color: #4caf50;
        }

        .system-modal-message.info i {
            color: #2196f3;
        }

        .system-modal-message .message-text {
            flex: 1;
        }

        .system-modal-footer {
            background: #e0e0e0;
            padding: 10px 15px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .system-modal-btn {
            padding: 6px 20px;
            font-family: 'Segoe UI', monospace;
            font-size: 12px;
            font-weight: 600;
            background: #e0e0e0;
            border: 1px solid #8a8a8a;
            cursor: pointer;
            min-width: 70px;
        }

        .system-modal-btn:hover {
            background: #c0c0c0;
            border-color: #666;
        }

        .system-modal-btn.primary {
            background: #3a6ea5;
            border-color: #2a4d73;
            color: white;
        }

        .system-modal-btn.danger {
            background: #d32f2f;
            border-color: #9a1a1a;
            color: white;
        }

        .xampp-btn {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            font-size: 12px;
            padding: 4px 12px;
            border: 1px solid #707070;
            border-radius: 2px;
            cursor: pointer;
            min-width: 70px;
            background: linear-gradient(to bottom, #f0f0f0 0%, #e1e1e1 100%);
            box-shadow: inset 1px 1px 0px #fff;
        }

        .xampp-btn:hover {
            background: linear-gradient(to bottom, #e5f1fb 0%, #e5f1fb 100%);
            border-color: #0078d7;
        }

        @media (max-width: 480px) {
            .system-modal {
                min-width: 300px;
            }

            .system-modal-message {
                font-size: 13px;
            }

            .system-modal-btn {
                padding: 5px 15px;
                min-width: 60px;
            }
        }

        @media (max-width: 768px) {
            .orders-table td {
                padding: 4px 5px;
                font-size: 10px;
            }

            .welcome h4 {
                font-size: 14px;
            }

            .dashboard-header {
                padding: 15px 20px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
                padding-top: 15px;
            }

            .dashboard-header {
                padding: 12px 15px;
                border-radius: 10px;
            }

            .welcome h4 {
                font-size: 13px;
            }
        }
    </style>
</head>

<body>
    <div class="app-wrapper">
        <div class="menu-overlay" id="menuOverlay"></div>

        <div class="sidebar-wrapper" id="sidebarWrapper">
            <div class="side-menu" id="sideMenu">
                <?php include 'sidebar.php'; ?>
            </div>
        </div>

        <main class="main-content">
            <div class="dashboard-header">
                <div class="header-left">
                    <button class="burger-btn" id="burgerBtn" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="welcome">
                        <h4>
                            <a href="pending_folder.php"><i class="fas fa-folder-open"></i> Pending Folders </a>
                            <?php if ($monthYear): ?>
                                <i class="fas fa-chevron-right"></i>
                                <a href="pending_folder_with.php?month=<?= urlencode($monthYear) ?>">
                                    <i class="fas fa-folder-open"></i> <?= htmlspecialchars($monthYear) ?>
                                </a>
                            <?php endif; ?>
                            <i class="fas fa-chevron-right"></i>
                            <i class="fas fa-folder-open"></i>
                            <span id="deliveryNumber"><?= htmlspecialchars($deliveryNumber) ?></span>
                            <i class="fas fa-copy" style="cursor: pointer; color: #3b82f6;"
                                onclick="copyDeliveryNumber()" title="Copy delivery number"></i>
                        </h4>
                    </div>
                </div>
            </div>

            <?php if (empty($orderItems)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No order items found for this delivery.</p>
                </div>
            <?php else: ?>
                <div class="orders-container">
                    <div class="delivery-group">
                        <div class="orders-table-container">
                            <div class="orders-scroll-inner">
                                <div class="delivery-header">
                                    <div class="customer-info">
                                        <div class="info-row">
                                            <span><i class="fas fa-user"></i> Ordered By:</span>
                                            <span id="customerNameDisplay"><?= htmlspecialchars($customerName) ?></span>
                                            <input type="text" id="customerNameInput" class="edit-input" value="<?= htmlspecialchars($customerName) ?>">
                                        </div>
                                        <div class="info-row" id="deliveryAddressWrapper" style="display: none;">
                                            <span><i class="fas fa-map-marker-alt"></i> Delivery Address:</span>
                                            <input type="text" id="deliveryAddressInput" class="edit-input" value="<?= htmlspecialchars($deliveryAddress) ?>">
                                        </div>
                                    </div>
                                    <div class="delivery-date-info" id="deliveryDateWrapper">
                                        <div class="info-row">
                                            <span><i class="fas fa-calendar"></i> Delivery Date:</span>
                                            <span id="deliveryDateDisplay">
                                                <?php
                                                $formattedDate = formatDeliveryDate($deliveryInfo['delivery_date'] ?? '');
                                                echo htmlspecialchars($formattedDate);
                                                ?>
                                            </span>
                                            <input type="date" id="deliveryDateInput"
                                                value="<?= htmlspecialchars($deliveryInfo['delivery_date'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                <table class="orders-table" id="orders-table">
                                    <tbody>
                                        <?php foreach ($orderItems as $index => $item): ?>
                                            <tr data-id="<?= htmlspecialchars($item['id'] ?? $index) ?>"
                                                data-product="<?= htmlspecialchars($item['product_name'] ?? '') ?>"
                                                data-pieces="<?= htmlspecialchars($item['pieces'] ?? '0') ?>"
                                                data-unit="<?= htmlspecialchars($item['unit'] ?? 'N/A') ?>"
                                                data-selling-price="<?= floatval($item['selling_price']) ?>"
                                                data-total="<?= floatval($item['total_amount'] ?? 0) ?>">
                                                <td class="order-id"><?= htmlspecialchars($item['order_id'] ?? '') ?></td>
                                                <td class="drag-handle" title="Drag to reorder">
                                                    <i class="fas fa-grip-vertical"></i>
                                                </td>
                                                <td class="product-name"><?= htmlspecialchars($item['product_name'] ?? 'N/A') ?></td>
                                                <td class="pieces"><?= htmlspecialchars($item['pieces'] ?? '0') ?></td>
                                                <td class="unit"><?= htmlspecialchars($item['unit'] ?? 'N/A') ?></td>
                                                <td class="selling_price">₱ <?= htmlspecialchars($item['selling_price'] ?? 'N/A') ?></td>
                                                <td class="total-amount">₱ <?= number_format(floatval($item['total_amount'] ?? 0), 2) ?></td>
                                                <td style="text-align: center;">
                                                    <button class="xampp-btn remove-btn" data-row-id="<?= $index ?>">Remove</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <tr class="subtotal-row" id="subtotal-row"
                                            style="<?= $discountAmount > 0.001 ? '' : 'display: none;' ?>">
                                            <td></td>
                                            <td></td>
                                            <td colspan="3" style="text-align: right; font-weight: 600; color: #64748b;">
                                                Sub Total:</td>
                                            <td colspan="1" style="text-align: right; font-weight: 600; color: #64748b;">
                                                ₱</td>
                                            <td style="font-weight: 600; color: #64748b;" id="subtotal-value">
                                                <?= number_format($subTotalFromRows, 2) ?>
                                            </td>
                                            <td></td>
                                        </tr>

                                        <tr class="subtotal-row discount-row" id="discount-row"
                                            style="<?= $discountAmount > 0.001 ? '' : 'display: none;' ?>">
                                            <td></td>
                                            <td></td>
                                            <td colspan="3" style="text-align: right; font-weight: 600; color: #dc2626;">
                                                Discount (<span id="discount-percent"><?= $discountPercentDisplay ?></span>%):
                                            </td>
                                            <td colspan="1" style="text-align: right; font-weight: 600; color: #dc2626;">
                                                − ₱</td>
                                            <td style="font-weight: 600; color: #dc2626;" id="discount-value">
                                                <?= number_format($discountAmount, 2) ?>
                                            </td>
                                            <td></td>
                                        </tr>

                                        <tr class="total-row">
                                            <td></td>
                                            <td></td>
                                            <td colspan="2" style="text-align: right; font-weight: 600;">Total Items: <span
                                                    id="totalItemsDisplay"><?php echo array_sum(array_column($orderItems, 'pieces')); ?></span>
                                            </td>
                                            <td></td>
                                            <td colspan="1" style="text-align: right; font-weight: 600;">TOTAL:</td>
                                            <td style="font-weight: bold; min-width: 130px;">
                                                <span id="total-amount-display">₱
                                                    <?= number_format($totalAmount, 2) ?>
                                                </span>
                                                <input type="text" id="total-amount-input"
                                                    value="<?= number_format($totalAmount, 2, '.', '') ?>"
                                                    inputmode="decimal" autocomplete="off">
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="receipt-actions">
                            <button class="receipt-btn delivery-receipt" id="delivery-receipt-btn" onclick="handleReceiptClick('delivery', this)">
                                Delivery Receipt
                            </button>
                            <button class="receipt-btn billing-receipt" id="billing-receipt-btn" onclick="handleReceiptClick('billing', this)">
                                Billing Receipt
                            </button>
                            <button class="receipt-btn edit-mode-btn" id="edit-mode-btn">
                                Edit
                            </button>

                            <div class="status-wrapper">
                                <select class="status-select" id="status-select"
                                    data-delivery-number="<?= htmlspecialchars($deliveryNumber) ?>">
                                    <option value="PENDING" <?= $currentStatus == 'PENDING' ? 'selected' : '' ?>>PENDING</option>
                                    <option value="PAID" <?= $currentStatus == 'PAID' ? 'selected' : '' ?>>PAID</option>
                                    <option value="CANCELLED" <?= $currentStatus == 'CANCELLED' ? 'selected' : '' ?>>CANCELLED</option>
                                    <option value="CREDIT" <?= $currentStatus == 'CREDIT' ? 'selected' : '' ?>>CREDIT</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <?php include '../footer.php'; ?>

    <script>
        // ========== SIDEBAR ==========
        const burgerBtn = document.getElementById('burgerBtn');
        const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
        const sidebarWrapper = document.getElementById('sidebarWrapper');
        const menuOverlay = document.getElementById('menuOverlay');
        let isSidebarOpen = false;

        function openSidebar() {
            sidebarWrapper.classList.add('open');
            menuOverlay.classList.add('active');
            isSidebarOpen = true;
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebarWrapper.classList.remove('open');
            menuOverlay.classList.remove('active');
            isSidebarOpen = false;
            document.body.style.overflow = '';
        }

        function toggleSidebar() {
            if (isSidebarOpen) closeSidebar();
            else openSidebar();
        }

        if (burgerBtn) burgerBtn.addEventListener('click', e => { e.stopPropagation(); toggleSidebar(); });
        if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', e => { e.stopPropagation(); closeSidebar(); });
        if (menuOverlay) menuOverlay.addEventListener('click', closeSidebar);

        document.querySelectorAll('.side-menu .nav-item, .side-menu .nav-dropdown-item').forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 768 && !this.closest('.nav-dropdown-toggle')) {
                    closeSidebar();
                }
            });
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 768 && isSidebarOpen) closeSidebar();
        });

        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            const arrowId = dropdownId.replace('Dropdown', 'Arrow');
            const arrow = document.getElementById(arrowId);
            if (dropdown && arrow) {
                dropdown.classList.toggle('show');
                arrow.classList.toggle('rotated');
            }
        }

        // ========== CONSTANTS ==========
        const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        const deliveryNumber = '<?= htmlspecialchars($selectedDeliveryNumber) ?>';
        const monthYear = '<?= htmlspecialchars($monthYear) ?>';
        const encodedMonthYear = '<?= $encodedMonthYear ?>';
        const originalDeliveryDate = '<?= htmlspecialchars($deliveryInfo['delivery_date'] ?? '') ?>';
        const originalDeliveryAddress = '<?= htmlspecialchars($deliveryAddress) ?>';
        const originalCustomerName = '<?= htmlspecialchars($customerName) ?>';

        const isInApp = typeof window.AndroidBiometric !== 'undefined';

        let isEditMode = false;
        let originalData = new Map();
        let isProcessing = false;

        let baseGrandTotal = 0;
        let currentGrandTotal = 0;
        let isManuallyAdjusted = false;

        // ========== HANDLE RECEIPT CLICK ==========
        function handleReceiptClick(type, btnElement) {
            const baseUrl = 'https://villaruz-print-shop-and-general-merchandise.shop/';
            const receiptUrl = type === 'delivery'
                ? baseUrl + 'delivery_receipt.php?delivery_number=' + encodeURIComponent(deliveryNumber)
                : baseUrl + 'billing_receipt.php?delivery_number=' + encodeURIComponent(deliveryNumber);

            if (isInApp) {
                copyToClipboard(receiptUrl, btnElement);
            } else {
                window.open(receiptUrl, '_blank');
            }
        }

        async function copyToClipboard(text, btnElement) {
            const originalText = btnElement.innerHTML;

            try {
                await navigator.clipboard.writeText(text);
                
                btnElement.innerHTML = 'Link Copied!';
                btnElement.classList.add('active-copy');

                setTimeout(() => {
                    btnElement.innerHTML = originalText;
                    btnElement.classList.remove('active-copy');
                }, 2000);

            } catch (err) {
                const tempInput = document.createElement('input');
                tempInput.value = text;
                document.body.appendChild(tempInput);
                tempInput.select();
                tempInput.setSelectionRange(0, 99999);
                try {
                    document.execCommand('copy');
                    
                    btnElement.innerHTML = 'Link Copied!';
                    btnElement.classList.add('active-copy');

                    setTimeout(() => {
                        btnElement.innerHTML = originalText;
                        btnElement.classList.remove('active-copy');
                    }, 2000);

                } catch (fallbackErr) {
                    prompt('Copy this link manually:', text);
                } finally {
                    document.body.removeChild(tempInput);
                }
            }
        }

        // ========== MODAL: CONFIRMATION ==========
        function showConfirmModal(title, message, onConfirm, onCancel = null) {
            return new Promise((resolve) => {
                const existingModal = document.querySelector('.system-modal-overlay');
                if (existingModal) existingModal.remove();

                const overlay = document.createElement('div');
                overlay.className = 'system-modal-overlay';

                overlay.innerHTML = `
                    <div class="system-modal">
                        <div class="system-modal-header">
                            <i class="fas fa-shop"></i>
                            <span>${escapeHtml(title)}</span>
                        </div>
                        <div class="system-modal-content">
                            <div class="system-modal-message warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div class="message-text">${message}</div>
                            </div>
                        </div>
                        <div class="system-modal-footer">
                            <button class="system-modal-btn" data-action="cancel">CANCEL</button>
                            <button class="system-modal-btn primary" data-action="confirm">CONFIRM</button>
                        </div>
                    </div>
                `;

                document.body.appendChild(overlay);

                overlay.querySelectorAll('.system-modal-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const action = btn.dataset.action;
                        overlay.remove();
                        resolve(action);
                        if (action === 'confirm' && onConfirm) onConfirm();
                        else if (action === 'cancel' && onCancel) onCancel();
                    });
                });
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, m => {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        // ============================================================
        // ✅ DRAG-AND-DROP
        // ============================================================
        (function initDragAndDrop() {
            const tbody = document.querySelector('#orders-table tbody');
            if (!tbody) return;

            let draggedRow = null;

            tbody.querySelectorAll('tr:not(.total-row):not(.subtotal-row)').forEach(row => {
                row.setAttribute('draggable', 'true');
                row.addEventListener('dragstart', handleDragStart);
                row.addEventListener('dragend', handleDragEnd);
                row.addEventListener('dragover', handleDragOver);
                row.addEventListener('dragleave', handleDragLeave);
                row.addEventListener('drop', handleDrop);
            });

            function handleDragStart(e) {
                if (isEditMode) { e.preventDefault(); return; }
                draggedRow = this;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.innerHTML);
            }

            function handleDragEnd() {
                this.classList.remove('dragging');
                tbody.querySelectorAll('tr').forEach(r => {
                    r.classList.remove('drag-over', 'drag-over-bottom');
                });
                draggedRow = null;
            }

            function handleDragOver(e) {
                if (!draggedRow || this === draggedRow) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                const rect = this.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;

                tbody.querySelectorAll('tr').forEach(r => {
                    r.classList.remove('drag-over', 'drag-over-bottom');
                });

                if (e.clientY < midpoint) {
                    this.classList.add('drag-over');
                } else {
                    this.classList.add('drag-over-bottom');
                }
            }

            function handleDragLeave() {
                this.classList.remove('drag-over', 'drag-over-bottom');
            }

            function handleDrop(e) {
                e.preventDefault();
                e.stopPropagation();

                if (!draggedRow || this === draggedRow) return;

                const rect = this.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;
                const dropBefore = e.clientY < midpoint;

                if (dropBefore) {
                    tbody.insertBefore(draggedRow, this);
                } else {
                    this.parentNode.insertBefore(draggedRow, this.nextSibling);
                }

                this.classList.remove('drag-over', 'drag-over-bottom');
                draggedRow.classList.remove('dragging');

                saveRowOrder();
            }

            async function saveRowOrder() {
                const newOrder = [];
                tbody.querySelectorAll('tr:not(.total-row):not(.subtotal-row)').forEach((row, index) => {
                    newOrder.push({
                        id: row.dataset.id,
                        sort_order: index
                    });
                });

                try {
                    const formData = new FormData();
                    formData.append('action', 'reorder_items');
                    formData.append('delivery_number', deliveryNumber);
                    formData.append('csrf_token', csrfToken);
                    formData.append('order', JSON.stringify(newOrder));

                    const response = await fetch('../API/update_delivery_status.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        window.location.reload();
                    }
                } catch (err) {
                    console.error('Reorder error:', err);
                }
            }
        })();

        function redirectToPendingFolderWith() {
            if (monthYear) {
                window.location.href = 'pending_folder_with.php?month=' + encodeURIComponent(monthYear);
            } else {
                window.location.href = 'pending_folder.php';
            }
        }

        // ========== PAID / CANCELLED ==========
        async function processPaidStatus() {
            if (isProcessing) return;
            isProcessing = true;
            try {
                const formData = new FormData();
                formData.append('action', 'update_order_status');
                formData.append('delivery_number', deliveryNumber);
                formData.append('status', 'PAID');
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/update_delivery_status.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    window.location.reload();
                }
            } catch (err) {
                console.error(err);
            } finally {
                isProcessing = false;
            }
        }

        async function processCancelledStatus() {
            if (isProcessing) return;
            isProcessing = true;
            try {
                const formData = new FormData();
                formData.append('action', 'delete_order');
                formData.append('delivery_number', deliveryNumber);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/update_delivery_status.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    redirectToPendingFolderWith();
                }
            } catch (err) {
                console.error(err);
            } finally {
                isProcessing = false;
            }
        }

        // ============================================================
        // ✅ TOTAL INPUT LOGIC
        // ============================================================
        const totalAmountDisplay = document.getElementById('total-amount-display');
        const totalAmountInput = document.getElementById('total-amount-input');

        function formatPeso(n) {
            const num = parseFloat(n) || 0;
            return '₱ ' + num.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function getTotalInputValue() {
            if (!totalAmountInput) return baseGrandTotal;
            const raw = (totalAmountInput.value || '0').toString().replace(/,/g, '').trim();
            const num = parseFloat(raw);
            return isNaN(num) ? 0 : num;
        }

        function setTotalValue(n) {
            const safe = parseFloat(n) || 0;
            currentGrandTotal = safe;

            if (totalAmountDisplay) {
                totalAmountDisplay.textContent = formatPeso(safe);
            }
            if (totalAmountInput) {
                totalAmountInput.value = safe.toFixed(2);
            }
        }

        function setEditModeUI(active) {
            if (active) document.body.classList.add('edit-active');
            else document.body.classList.remove('edit-active');
        }

        function updateDiscountRows() {
            const subtotalRow = document.getElementById('subtotal-row');
            const discountRow = document.getElementById('discount-row');
            const subtotalValueEl = document.getElementById('subtotal-value');
            const discountValueEl = document.getElementById('discount-value');
            const discountPercentEl = document.getElementById('discount-percent');

            if (!subtotalRow || !discountRow) return;

            const subtotal = baseGrandTotal;
            const finalTotal = getTotalInputValue();

            if (subtotal > 0.001 && finalTotal > 0.001 && finalTotal < subtotal - 0.001) {
                const discountAmt = subtotal - finalTotal;
                const discountPct = (discountAmt / subtotal) * 100;

                if (subtotalValueEl) subtotalValueEl.textContent = subtotal.toLocaleString('en-US', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                });
                if (discountValueEl) discountValueEl.textContent = discountAmt.toLocaleString('en-US', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                });

                if (discountPercentEl) {
                    discountPercentEl.textContent = parseFloat(discountPct.toFixed(2)).toString();
                }

                subtotalRow.style.display = '';
                discountRow.style.display = '';
            } else {
                subtotalRow.style.display = 'none';
                discountRow.style.display = 'none';
            }
        }

        if (totalAmountInput) {
            totalAmountInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9.,]/g, '');
                const raw = this.value.replace(/,/g, '');
                if (!isNaN(parseFloat(raw)) && isFinite(raw)) {
                    currentGrandTotal = parseFloat(raw);
                    isManuallyAdjusted = true;
                    if (totalAmountDisplay) totalAmountDisplay.textContent = formatPeso(currentGrandTotal);
                    updateDiscountRows();
                }
            });

            totalAmountInput.addEventListener('blur', function () {
                const val = getTotalInputValue();
                this.value = val.toFixed(2);
                currentGrandTotal = val;
                updateDiscountRows();
            });
        }

        // ============================================================
        // ✅ EDIT MODE
        // ============================================================
        const editModeBtn = document.getElementById('edit-mode-btn');

        function enableEditMode() {
            isEditMode = true;
            editModeBtn.innerHTML = '<i class="fas fa-save"></i> UPDATE';
            editModeBtn.classList.remove('edit-mode-btn');
            editModeBtn.classList.add('update-mode-btn');

            setEditModeUI(true);

            isManuallyAdjusted = false;

            const dateDisplay = document.getElementById('deliveryDateDisplay');
            const dateInput = document.getElementById('deliveryDateInput');
            if (dateDisplay && dateInput) {
                dateDisplay.style.display = 'none';
                dateInput.style.display = 'inline-block';
            }

            const customerDisplay = document.getElementById('customerNameDisplay');
            const customerInput = document.getElementById('customerNameInput');
            if (customerDisplay && customerInput) {
                customerDisplay.style.display = 'none';
                customerInput.style.display = 'inline-block';
            }

            const addressWrapper = document.getElementById('deliveryAddressWrapper');
            const addressInput = document.getElementById('deliveryAddressInput');
            if (addressWrapper && addressInput) {
                addressWrapper.style.display = 'flex';
                addressInput.style.display = 'inline-block';
            }

            const rows = document.querySelectorAll('#orders-table tbody tr:not(.total-row):not(.subtotal-row)');

            rows.forEach((row, index) => {
                row.classList.add('edit-row');

                const originalProduct = row.cells[2].innerText;
                const originalPieces = row.cells[3].innerText;
                const originalUnit = row.cells[4].innerText;
                const originalSellingPrice = row.cells[5].innerText.replace('₱', '').replace(/,/g, '');
                const originalTotal = row.cells[6].innerText.replace('₱', '').replace(/,/g, '');

                originalData.set(index, {
                    product: originalProduct,
                    pieces: originalPieces,
                    unit: originalUnit,
                    selling_price: originalSellingPrice,
                    total: originalTotal
                });

                row.cells[2].innerHTML = `<input type="text" class="editable-input" value="${escapeHtml(originalProduct)}" data-field="product">`;
                row.cells[3].innerHTML = `<input type="text" class="editable-input" value="${originalPieces}" data-field="pieces">`;
                row.cells[4].innerHTML = `<input type="text" class="editable-input" value="${escapeHtml(originalUnit)}" data-field="unit">`;
                row.cells[5].innerHTML = `<input type="text" class="editable-input" value="${originalSellingPrice}" data-field="selling_price">`;
                row.cells[6].innerHTML = `<input type="text" class="editable-input" value="${originalTotal}" data-field="total">`;

                const piecesInput = row.cells[3].querySelector('input');
                const sellingPriceInput = row.cells[5].querySelector('input');
                const totalInput = row.cells[6].querySelector('input');

                function calculateRowTotal() {
                    const pieces = parseFloat(piecesInput.value) || 0;
                    const price = parseFloat(sellingPriceInput.value) || 0;
                    totalInput.value = (pieces * price).toFixed(2);
                    recalcBaseGrandTotalFromRows();
                }

                if (piecesInput && sellingPriceInput && totalInput) {
                    piecesInput.addEventListener('input', calculateRowTotal);
                    sellingPriceInput.addEventListener('input', calculateRowTotal);

                    piecesInput.addEventListener('blur', function () {
                        if (this.value === '' || isNaN(this.value)) this.value = 0;
                        calculateRowTotal();
                    });

                    sellingPriceInput.addEventListener('blur', function () {
                        if (this.value === '' || isNaN(this.value)) this.value = 0;
                        calculateRowTotal();
                    });
                }
            });

            recalcBaseGrandTotalFromRows();
        }

        function recalcBaseGrandTotalFromRows() {
            const rows = document.querySelectorAll('#orders-table tbody tr:not(.total-row):not(.subtotal-row)');
            let sum = 0;
            let totalItems = 0;

            rows.forEach(row => {
                const piecesInput = row.cells[3]?.querySelector('input');
                const totalInput = row.cells[6]?.querySelector('input');
                totalItems += parseFloat(piecesInput?.value) || 0;
                sum += parseFloat(totalInput?.value) || 0;
            });

            baseGrandTotal = sum;

            if (!isManuallyAdjusted) {
                setTotalValue(sum);
            }

            const totalItemsDisplay = document.getElementById('totalItemsDisplay');
            if (totalItemsDisplay) totalItemsDisplay.textContent = totalItems;

            updateDiscountRows();
        }

        async function disableEditMode() {
            const updatedItems = [];
            const rows = document.querySelectorAll('#orders-table tbody tr:not(.total-row):not(.subtotal-row)');
            let isValid = true;

            const dateInput = document.getElementById('deliveryDateInput');
            const updatedDeliveryDate = dateInput ? dateInput.value : '';

            const addressInput = document.getElementById('deliveryAddressInput');
            const updatedAddress = addressInput ? addressInput.value : '';

            const customerInput = document.getElementById('customerNameInput');
            const updatedCustomerName = customerInput ? customerInput.value : '';

            recalcBaseGrandTotalFromRows();

            rows.forEach((row, index) => {
                const productInput = row.cells[2].querySelector('input');
                const piecesInput = row.cells[3].querySelector('input');
                const unitInput = row.cells[4].querySelector('input');
                const sellingPriceInput = row.cells[5].querySelector('input');
                const totalInput = row.cells[6].querySelector('input');

                if (!productInput || !piecesInput || !unitInput || !sellingPriceInput || !totalInput) {
                    isValid = false;
                    return;
                }

                const newProduct = productInput.value.trim();
                const newPieces = piecesInput.value;
                const newUnit = unitInput.value.trim();
                const newSellingPrice = sellingPriceInput.value;
                const newTotal = totalInput.value;

                if (!newProduct || !newPieces || newPieces <= 0 || newSellingPrice < 0 || newTotal <= 0) {
                    isValid = false;
                    return;
                }

                updatedItems.push({
                    rowId: row.dataset.id,
                    original: originalData.get(index),
                    updated: {
                        product: newProduct,
                        pieces: newPieces,
                        unit: newUnit,
                        selling_price: newSellingPrice,
                        total: newTotal
                    }
                });
            });

            if (!isValid) return;

            const dateChanged = updatedDeliveryDate && updatedDeliveryDate !== originalDeliveryDate;
            const addressChanged = updatedAddress && updatedAddress !== originalDeliveryAddress;
            const customerChanged = updatedCustomerName && updatedCustomerName !== originalCustomerName;

            let hasChanges = false;
            for (const item of updatedItems) {
                if (item.original.product !== item.updated.product ||
                    item.original.pieces !== item.updated.pieces ||
                    item.original.unit !== item.updated.unit ||
                    parseFloat(item.original.selling_price) !== parseFloat(item.updated.selling_price) ||
                    parseFloat(item.original.total) !== parseFloat(item.updated.total)) {
                    hasChanges = true;
                    break;
                }
            }

            const finalTotal = getTotalInputValue();
            if (Math.abs(finalTotal - baseGrandTotal) > 0.001) hasChanges = true;

            if (!hasChanges && !dateChanged && !addressChanged && !customerChanged) {
                window.location.reload();
                return;
            }

            let confirmMessage = 'Are you sure you want to update the order?';
            if (dateChanged) confirmMessage += '<br><br>📅 Delivery Date will be changed to: ' + updatedDeliveryDate;
            if (addressChanged) confirmMessage += '<br><br>📍 Delivery Address will be changed to: ' + updatedAddress;
            if (customerChanged) confirmMessage += '<br><br>👤 Customer Name will be changed to: ' + updatedCustomerName;
            if (Math.abs(finalTotal - baseGrandTotal) > 0.001) {
                confirmMessage += '<br><br>💰 Grand Total will be saved as: ' + formatPeso(finalTotal);
            }

            showConfirmModal('UPDATE ORDER ITEMS', confirmMessage, async () => {
                await updateOrderItems(updatedItems, updatedDeliveryDate, dateChanged, finalTotal, updatedAddress, addressChanged, updatedCustomerName, customerChanged);
            });
        }

        async function updateOrderItems(updatedItems, updatedDeliveryDate, dateChanged, finalTotal, updatedAddress, addressChanged, updatedCustomerName, customerChanged) {
            try {
                const formData = new FormData();
                formData.append('action', 'update_order_items');
                formData.append('delivery_number', deliveryNumber);
                formData.append('csrf_token', csrfToken);
                formData.append('items', JSON.stringify(updatedItems.map(item => ({
                    id: item.rowId,
                    product_name: item.updated.product,
                    pieces: item.updated.pieces,
                    unit: item.updated.unit,
                    selling_price: item.updated.selling_price,
                    total_amount: item.updated.total
                }))));

                if (dateChanged && updatedDeliveryDate) {
                    formData.append('delivery_date', updatedDeliveryDate);
                }

                if (addressChanged && updatedAddress) {
                    formData.append('delivery_address', updatedAddress);
                }

                if (customerChanged && updatedCustomerName) {
                    formData.append('customer_name', updatedCustomerName);
                }

                formData.append('grand_total', finalTotal.toFixed(2));

                const response = await fetch('../API/update_delivery_status.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    window.location.reload();
                }
            } catch (err) {
                console.error(err);
            }
        }

        if (editModeBtn) {
            editModeBtn.addEventListener('click', () => {
                if (!isEditMode) enableEditMode();
                else disableEditMode();
            });
        }

        // ========== STATUS UPDATE ==========
        const statusSelect = document.getElementById('status-select');
        if (statusSelect) {
            let previousStatus = statusSelect.value;

            statusSelect.addEventListener('change', async function () {
                const newStatus = this.value;

                if (newStatus === 'PAID') {
                    showConfirmModal(
                        '⚠️ PAYMENT CONFIRMATION',
                        'This action will mark this order as PAID and deduct stock from inventory.<br><br><strong>Are you sure?</strong>',
                        () => processPaidStatus(),
                        () => { statusSelect.value = previousStatus; }
                    );
                    statusSelect.value = previousStatus;
                } else if (newStatus === 'CANCELLED') {
                    showConfirmModal(
                        '⚠️ CANCELLATION CONFIRMATION',
                        `<strong>Delivery #${deliveryNumber}</strong><br>Customer: <?= htmlspecialchars($customerName) ?><br>
                        Total: <strong>₱ <?= number_format($totalAmount, 2) ?></strong><br><br>
                        <strong style="color: #dc2626;">WARNING:</strong> This will <strong>DELETE</strong> all data!`,
                        () => processCancelledStatus(),
                        () => { statusSelect.value = previousStatus; }
                    );
                    statusSelect.value = previousStatus;
                } else {
                    await updateStatusDirect(newStatus, previousStatus);
                }
            });
        }

        async function updateStatusDirect(newStatus, previousStatus) {
            const statusSelectEl = document.getElementById('status-select');

            try {
                if (statusSelectEl) statusSelectEl.disabled = true;

                const formData = new FormData();
                formData.append('action', 'update_order_status');
                formData.append('delivery_number', deliveryNumber);
                formData.append('status', newStatus);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/update_delivery_status.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    window.location.reload();
                } else {
                    if (statusSelectEl) statusSelectEl.value = previousStatus;
                }
            } catch (err) {
                console.error(err);
                if (statusSelectEl) statusSelectEl.value = previousStatus;
            } finally {
                if (statusSelectEl) statusSelectEl.disabled = false;
            }
        }

        // ========== REMOVE ITEM ==========
        document.querySelectorAll('.remove-btn').forEach(button => {
            button.addEventListener('click', async function () {
                if (isEditMode) return;

                const row = this.closest('tr');
                const productName = row.cells[2]?.innerText || '';
                const pieces = row.cells[3]?.innerText || '0';
                const totalAmount = row.cells[6]?.innerText.replace('₱', '').replace(/,/g, '') || '0';

                showConfirmModal(
                    'REMOVE ITEM',
                    `Are you sure you want to remove "${productName}" (${pieces} pcs) from this order?<br><br>This action cannot be undone.`,
                    async () => { await removeOrderItem(productName, pieces, totalAmount, row); }
                );
            });
        });

        async function removeOrderItem(productName, pieces, totalAmount, row) {
            try {
                const formData = new FormData();
                formData.append('action', 'remove_order_item');
                formData.append('delivery_number', deliveryNumber);
                formData.append('product_name', productName);
                formData.append('pieces', pieces);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../API/update_delivery_status.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    const remainingRows = document.querySelectorAll('#orders-table tbody tr:not(.total-row):not(.subtotal-row)').length;
                    if (remainingRows - 1 === 0) {
                        redirectToPendingFolderWith();
                    } else {
                        window.location.reload();
                    }
                }
            } catch (err) {
                console.error(err);
            }
        }

        function copyDeliveryNumber() {
            const deliveryNumberEl = document.getElementById('deliveryNumber');
            const deliveryNumberText = deliveryNumberEl.textContent;

            const tempInput = document.createElement('input');
            tempInput.value = deliveryNumberText;
            document.body.appendChild(tempInput);
            tempInput.select();
            tempInput.setSelectionRange(0, 99999);
            document.execCommand('copy');
            document.body.removeChild(tempInput);

            const copyIcon = document.querySelector('.fa-copy');
            copyIcon.className = 'fas fa-check-circle';
            copyIcon.style.color = '#16a34a';
            setTimeout(() => {
                copyIcon.className = 'fas fa-copy';
                copyIcon.style.color = '#3b82f6';
            }, 2000);
        }

        // Initialize base grand total
        (function init() {
            const rows = document.querySelectorAll('#orders-table tbody tr:not(.total-row):not(.subtotal-row)');
            let sum = 0;
            rows.forEach(row => {
                const cell = row.cells[6];
                if (cell) {
                    const raw = cell.innerText.replace('₱', '').replace(/,/g, '').trim();
                    sum += parseFloat(raw) || 0;
                }
            });
            baseGrandTotal = sum;
            currentGrandTotal = parseFloat(getTotalInputValue()) || 0;

            updateDiscountRows();
        })();

        console.log('📱 Sidebar menu loaded');
        console.log('💰 Total editable + saved to for_deliveries');
        console.log('🏷️ Discount note auto-detects when total < subtotal');
    </script>
</body>

</html>