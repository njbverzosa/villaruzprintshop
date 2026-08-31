<?php
// Customer_API/cart_operations.php

session_start();
require_once __DIR__ . '/../DB_Conn/config.php';
header('Content-Type: application/json');

// ==============================================
// 0. DEFINE THE UPDATE ONLINE TIME FUNCTION
// ==============================================
function updateOnlineTime($pdo, $userRole, $userAccNumber)
{
    $currentDateTime = date('D, j M Y g:i A');
    try {
        if ($userRole === 'Customer') {
            $stmt = $pdo->prepare("UPDATE customers SET last_login_date = ? WHERE acc_number = ?");
            $stmt->execute([$currentDateTime, $userAccNumber]);
        } elseif ($userRole === 'Admin') {
            $stmt = $pdo->prepare("UPDATE admins SET last_login_date = ? WHERE acc_number = ?");
            $stmt->execute([$currentDateTime, $userAccNumber]);
        }
    } catch (Exception $e) {
        // Silently fail - don't break the main operation
        error_log("Failed to update online time: " . $e->getMessage());
    }
}

// ==============================================
// 1. CHECK IF USER IS LOGGED IN
// ==============================================
if (!isset($_SESSION['user_role']) || !isset($_SESSION['user_id']) || !isset($_SESSION['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$userAccNumber = $_SESSION['acc_number'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// ==============================================
// 2. VERIFY CSRF TOKEN
// ==============================================
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'increment':
            $cartId = $_POST['cart_id'] ?? 0;

            $stmt = $pdo->prepare("SELECT * FROM cart WHERE id = ?");
            $stmt->execute([$cartId]);
            $cartItem = $stmt->fetch();

            if (!$cartItem) {
                echo json_encode(['success' => false, 'message' => 'Cart item not found']);
                exit();
            }

            $newPieces = $cartItem['pieces'] + 1;
            $newTotal = $cartItem['selling_price'] * $newPieces;

            $stmt = $pdo->prepare("UPDATE cart SET pieces = ?, total_amount = ? WHERE id = ?");
            $stmt->execute([$newPieces, $newTotal, $cartId]);

            updateOnlineTime($pdo, $userRole, $userAccNumber);

            echo json_encode(['success' => true, 'message' => 'Quantity increased']);
            break;

        case 'decrement':
            $cartId = $_POST['cart_id'] ?? 0;

            $stmt = $pdo->prepare("SELECT * FROM cart WHERE id = ?");
            $stmt->execute([$cartId]);
            $cartItem = $stmt->fetch();

            if (!$cartItem) {
                echo json_encode(['success' => false, 'message' => 'Cart item not found']);
                exit();
            }

            if ($cartItem['pieces'] > 1) {
                $newPieces = $cartItem['pieces'] - 1;
                $newTotal = $cartItem['selling_price'] * $newPieces;

                $stmt = $pdo->prepare("UPDATE cart SET pieces = ?, total_amount = ? WHERE id = ?");
                $stmt->execute([$newPieces, $newTotal, $cartId]);

                updateOnlineTime($pdo, $userRole, $userAccNumber);

                echo json_encode(['success' => true, 'message' => 'Quantity decreased']);
            } else {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ?");
                $stmt->execute([$cartId]);

                updateOnlineTime($pdo, $userRole, $userAccNumber);

                echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
            }
            break;

        case 'remove':
            $cartId = $_POST['cart_id'] ?? 0;

            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ?");
            $stmt->execute([$cartId]);

            updateOnlineTime($pdo, $userRole, $userAccNumber);

            echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
            break;

        case 'clear_cart':
            $userAccNumber = $_POST['acc_number'] ?? '';

            if (!empty($userAccNumber)) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE acc_number = ?");
                $stmt->execute([$userAccNumber]);
                $deletedCount = $stmt->rowCount();

                // Update online time
                updateOnlineTime($pdo, $userRole, $userAccNumber);

                echo json_encode(['success' => true, 'message' => "$deletedCount item(s) cleared from cart"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Account number is required']);
            }
            break;

        case 'validate_delivery':
            $deliveryNumber = $_POST['delivery_number'] ?? '';
            $userAccNumber = $_POST['acc_number'] ?? '';

            if (empty($deliveryNumber)) {
                echo json_encode(['success' => false, 'exists' => false, 'message' => 'Delivery number is required']);
                exit();
            }

            if (empty($userAccNumber)) {
                echo json_encode(['success' => false, 'exists' => false, 'message' => 'User account number is required']);
                exit();
            }

            // Update online time for validation action
            updateOnlineTime($pdo, $userRole, $userAccNumber);

            // Check if delivery exists in for_deliveries AND belongs to the user
            $stmt = $pdo->prepare("SELECT delivery_number, ordered_by, total_amount, status, delivery_address, delivery_date, acc_number FROM for_deliveries WHERE delivery_number = ?");
            $stmt->execute([$deliveryNumber]);
            $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($delivery) {
                // Check if the delivery belongs to the user
                if ($delivery['acc_number'] !== $userAccNumber) {
                    echo json_encode([
                        'success' => false,
                        'exists' => false,
                        'message' => 'Delivery number not found in your account. This will be processed as a new order.'
                    ]);
                    exit();
                }

                // Only allow PENDING status for adding items
                $currentStatus = strtoupper($delivery['status']);

                // Check if the order status is NOT PENDING (cannot add items)
                if ($currentStatus !== 'PENDING') {
                    $statusMessages = [
                        'PAID' => 'paid/delivered',
                        'CANCELLED' => 'cancelled',
                        'CREDIT' => 'on credit',
                        'PACKING' => 'being packed',
                        'SHIPPED' => 'already shipped',
                        'OFD' => 'out for delivery',
                        'DELIVERED' => 'already delivered'
                    ];

                    $statusText = $statusMessages[$currentStatus] ?? strtolower($currentStatus);

                    echo json_encode([
                        'success' => false,
                        'exists' => false,
                        'message' => "You cannot add items to this order. This will be processed as a new order."
                    ]);
                    exit();
                }

                echo json_encode([
                    'success' => true,
                    'exists' => true,
                    'delivery_number' => $delivery['delivery_number'],
                    'ordered_by' => $delivery['ordered_by'],
                    'total_amount' => floatval($delivery['total_amount']),
                    'status' => $delivery['status'],
                    'delivery_address' => $delivery['delivery_address'],
                    'delivery_date' => $delivery['delivery_date']
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'exists' => false,
                    'message' => 'Delivery number not found'
                ]);
            }
            break;

        case 'checkout':
            $customerName = $_POST['customer_name'] ?? '';
            $userAccNumber = $_POST['acc_number'] ?? '';
            $deliveryDateRaw = $_POST['delivery_date'] ?? '';
            $existingDeliveryNumber = $_POST['existing_delivery_number'] ?? '';

            if (empty($customerName) || empty($deliveryDateRaw)) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit();
            }

            if (empty($userAccNumber)) {
                echo json_encode(['success' => false, 'message' => 'User not authenticated']);
                exit();
            }

            // Update online time for checkout
            updateOnlineTime($pdo, $userRole, $userAccNumber);

            // Get user's address details from customers table
            $stmt = $pdo->prepare("SELECT street, barangay, land_mark FROM customers WHERE acc_number = ?");
            $stmt->execute([$userAccNumber]);
            $userAddressData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userAddressData) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit();
            }

            $street = $userAddressData['street'] ?? '';
            $barangay = $userAddressData['barangay'] ?? '';
            $landMark = $userAddressData['land_mark'] ?? '';

            // Check if user has complete address
            if (empty($street) || empty($barangay)) {
                echo json_encode(['success' => false, 'message' => 'Please complete your address (Street and Barangay are required)']);
                exit();
            }

            // Combine address fields
            $fullAddress = trim($street . ', ' . $barangay);
            if (!empty($landMark)) {
                $fullAddress .= ', ' . $landMark;
            }

            // Get cart items for this user
            $stmt = $pdo->prepare("SELECT * FROM cart WHERE acc_number = ?");
            $stmt->execute([$userAccNumber]);
            $cartItems = $stmt->fetchAll();

            if (empty($cartItems)) {
                echo json_encode(['success' => false, 'message' => 'Cart is empty']);
                exit();
            }

            // Calculate subtotal amount from cart items with proper decimal handling
            $subtotalAmount = 0;
            foreach ($cartItems as $item) {
                $itemTotal = floatval($item['total_amount']);
                $subtotalAmount += $itemTotal;
            }

            $subtotalAmount = round($subtotalAmount, 2);

            // ============================================================
            // DELIVERY DATE VALIDATION
            // ============================================================
            date_default_timezone_set('Asia/Manila');
            $currentDateTime = new DateTime();
            $currentTime = $currentDateTime->format('H:i'); // 24-hour format
            $currentDate = $currentDateTime->format('Y-m-d');

            // Parse the delivery date
            $deliveryDateObj = DateTime::createFromFormat('Y-m-d', $deliveryDateRaw);
            if (!$deliveryDateObj) {
                echo json_encode(['success' => false, 'message' => 'Invalid delivery date format']);
                exit();
            }

            $deliveryDateStr = $deliveryDateObj->format('Y-m-d');
            $cutoffTime = '15:00'; // 3:00 PM cutoff

            // Check if delivery date is today
            if ($deliveryDateStr === $currentDate) {
                // If current time is after 5:00 PM (17:00), move delivery to tomorrow
                if ($currentTime >= $cutoffTime) {
                    $deliveryDateObj->modify('+1 day');
                    $deliveryDateFormatted = $deliveryDateObj->format('j F Y');
                    $deliveryDateRaw = $deliveryDateObj->format('Y-m-d');

                    // Log the change
                    error_log("Delivery date moved to tomorrow due to cutoff time. Original: {$deliveryDateStr}, New: {$deliveryDateRaw}");
                } else {
                    $deliveryDateFormatted = $deliveryDateObj->format('j F Y');
                }
            } else {
                $deliveryDateFormatted = $deliveryDateObj->format('j F Y');
            }

            // ============================================================
            // CALCULATE DELIVERY CHARGE BASED ON BARANGAY (FREE FOR ₱500+)
            // ============================================================
            $deliveryCharge = 0;

            if ($subtotalAmount >= 500) {
                $deliveryCharge = 0;
            } else {
                $barangayLower = strtolower(trim($barangay));
                $barangay15 = ['poblacion'];
                $barangay30 = ['bobonot', 'amalbalan', 'gais-guipe', 'gaisguipe', 'hermosa', 'petal'];

                if (in_array($barangayLower, $barangay15)) {
                    $deliveryCharge = 25;
                } elseif (in_array($barangayLower, $barangay30)) {
                    $deliveryCharge = 35;
                } else {
                    $deliveryCharge = 50;
                }
            }

            $totalAmount = round($subtotalAmount + $deliveryCharge, 2);

            $dateTimeSold = date('j F Y');
            $MYD = date('F Y');

            // ============================================================
            // UPDATE CUSTOMER'S ADDRESS IN customers TABLE
            // ============================================================
            try {
                if (!empty($fullAddress)) {
                    $updateCustomerStmt = $pdo->prepare("UPDATE customers SET complete_delivery_address = ? WHERE acc_number = ?");
                    $updateCustomerStmt->execute([$fullAddress, $userAccNumber]);
                }
            } catch (PDOException $e) {
                error_log("Failed to update customer address: " . $e->getMessage());
            }

            // Check if adding to existing order
            $isExistingOrder = false;
            $deliveryNumber = '';

            if (!empty($existingDeliveryNumber)) {
                $stmt = $pdo->prepare("SELECT delivery_number, total_amount FROM for_deliveries WHERE delivery_number = ?");
                $stmt->execute([$existingDeliveryNumber]);
                $existingDelivery = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existingDelivery) {
                    $isExistingOrder = true;
                    $deliveryNumber = $existingDeliveryNumber;

                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("SELECT MAX(order_id) as max_id FROM order_status_history WHERE delivery_number = ?");
                    $stmt->execute([$deliveryNumber]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $nextOrderId = ($result['max_id'] ?? 0) + 1;

                    $successCount = 0;
                    foreach ($cartItems as $item) {
                        $itemTotalAmount = floatval($item['total_amount']);
                        $itemSellingPrice = floatval($item['selling_price']);

                        $stmt = $pdo->prepare("INSERT INTO order_status_history (acc_number, order_id, delivery_address, delivery_number, product_name, selling_price, status, pieces, unit, total_amount, date_time_sold, delivery_date) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?, ?)");
                        $result2 = $stmt->execute([
                            $userAccNumber,
                            $nextOrderId,
                            $fullAddress,
                            $deliveryNumber,
                            $item['product_name'],
                            $itemSellingPrice,
                            $item['pieces'],
                            $item['unit'],
                            $itemTotalAmount,
                            $dateTimeSold,
                            $deliveryDateFormatted
                        ]);

                        if ($result2) {
                            $successCount++;
                        }
                    }

                    if ($successCount === 0) {
                        $pdo->rollBack();
                        echo json_encode(['success' => false, 'message' => 'No items were inserted']);
                        exit();
                    }

                    $totalStmt = $pdo->prepare("SELECT SUM(total_amount) as new_total FROM order_status_history WHERE delivery_number = ?");
                    $totalStmt->execute([$deliveryNumber]);
                    $newTotalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
                    $newTotalAmount = floatval($newTotalResult['new_total'] ?? 0) + $deliveryCharge;
                    $newTotalAmount = round($newTotalAmount, 2);

                    $updateDeliveryStmt = $pdo->prepare("UPDATE for_deliveries SET total_amount = ?, charge = ?, delivery_date = ? WHERE delivery_number = ?");
                    $updateDeliveryStmt->execute([$newTotalAmount, $deliveryCharge, $deliveryDateFormatted, $deliveryNumber]);

                    $stmt = $pdo->prepare("DELETE FROM cart WHERE acc_number = ?");
                    $stmt->execute([$userAccNumber]);

                    $pdo->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => 'Items added to existing order successfully!',
                        'delivery_number' => $deliveryNumber,
                        'new_total_amount' => $newTotalAmount,
                        'existing_order' => true,
                        'items_added' => $successCount,
                        'address_updated' => true,
                        'delivery_charge' => $deliveryCharge,
                        'subtotal_amount' => $subtotalAmount,
                        'barangay' => $barangay,
                        'free_delivery' => ($deliveryCharge == 0 && $subtotalAmount >= 500),
                        'delivery_date' => $deliveryDateFormatted,
                        'delivery_date_raw' => $deliveryDateRaw
                    ]);
                    exit();
                }
            }

            // If not existing order, create new order
            function generateUniqueDeliveryNumber($pdo)
            {
                $maxAttempts = 100;
                $stmt = $pdo->prepare("SELECT delivery_number FROM for_deliveries WHERE delivery_number LIKE 'VPSGM%'");
                $stmt->execute();
                $existingDeliveries = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $stmt2 = $pdo->prepare("SELECT delivery_number FROM order_status_history WHERE delivery_number LIKE 'VPSGM%'");
                $stmt2->execute();
                $existingHistory = $stmt2->fetchAll(PDO::FETCH_COLUMN);

                $allExistingNumbers = array_merge($existingDeliveries, $existingHistory);

                $sequences = [];
                foreach ($allExistingNumbers as $existingNumber) {
                    if (preg_match('/VPSGM(\d+)/', $existingNumber, $matches)) {
                        $sequences[] = intval($matches[1]);
                    }
                }

                if (empty($sequences)) {
                    $nextSequence = 1;
                } else {
                    sort($sequences);
                    $nextSequence = end($sequences) + 1;
                }

                $formattedSequence = str_pad($nextSequence, 6, '0', STR_PAD_LEFT);
                $proposedNumber = 'VPSGM' . $formattedSequence;

                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM for_deliveries WHERE delivery_number = :number");
                $checkStmt->execute([':number' => $proposedNumber]);
                $countInDeliveries = $checkStmt->fetchColumn();

                $checkStmt2 = $pdo->prepare("SELECT COUNT(*) FROM order_status_history WHERE delivery_number = :number");
                $checkStmt2->execute([':number' => $proposedNumber]);
                $countInHistory = $checkStmt2->fetchColumn();

                if ($countInDeliveries == 0 && $countInHistory == 0) {
                    return $proposedNumber;
                }

                $attempt = $nextSequence + 1;
                while ($attempt < $nextSequence + $maxAttempts) {
                    $formattedAttempt = str_pad($attempt, 6, '0', STR_PAD_LEFT);
                    $testNumber = 'VPSGM' . $formattedAttempt;

                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM for_deliveries WHERE delivery_number = :number");
                    $checkStmt->execute([':number' => $testNumber]);
                    $countInDeliveries = $checkStmt->fetchColumn();

                    $checkStmt2 = $pdo->prepare("SELECT COUNT(*) FROM order_status_history WHERE delivery_number = :number");
                    $checkStmt2->execute([':number' => $testNumber]);
                    $countInHistory = $checkStmt2->fetchColumn();

                    if ($countInDeliveries == 0 && $countInHistory == 0) {
                        return $testNumber;
                    }
                    $attempt++;
                }

                return 'VPSGM' . time();
            }

            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $baseUrl = $protocol . $host;

            $deliveryNumber = generateUniqueDeliveryNumber($pdo);
            $qrCodeLink = $baseUrl . '/delivery_receipt.php?delivery_number=' . urlencode($deliveryNumber);

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO for_deliveries (acc_number, ordered_by, delivery_number, delivery_address, total_amount, charge, status, date_time_sold, qr_code, delivery_date, delivery_m_y) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?)");
            $result1 = $stmt->execute([
                $userAccNumber,
                $customerName,
                $deliveryNumber,
                $fullAddress,
                $totalAmount,
                $deliveryCharge,
                $dateTimeSold,
                $qrCodeLink,
                $deliveryDateFormatted,
                $MYD
            ]);

            if (!$result1) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to create delivery record']);
                exit();
            }

            $orderId = $pdo->lastInsertId();

            $successCount = 0;
            foreach ($cartItems as $item) {
                $itemTotalAmount = floatval($item['total_amount']);
                $itemSellingPrice = floatval($item['selling_price']);

                $stmt = $pdo->prepare("INSERT INTO order_status_history (acc_number, order_id, delivery_address, delivery_number, product_name, selling_price, status, pieces, unit, total_amount, date_time_sold, delivery_date, qr_code) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?, ?, ?)");
                $result2 = $stmt->execute([
                    $userAccNumber,
                    $orderId,
                    $fullAddress,
                    $deliveryNumber,
                    $item['product_name'],
                    $itemSellingPrice,
                    $item['pieces'],
                    $item['unit'],
                    $itemTotalAmount,
                    $dateTimeSold,
                    $deliveryDateFormatted,
                    $qrCodeLink
                ]);

                if ($result2) {
                    $successCount++;
                }
            }

            if ($successCount === 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'No items were inserted']);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM cart WHERE acc_number = ?");
            $stmt->execute([$userAccNumber]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Order placed successfully! Delivery #: ' . $deliveryNumber,
                'delivery_number' => $deliveryNumber,
                'order_id' => $orderId,
                'items_count' => $successCount,
                'subtotal_amount' => $subtotalAmount,
                'delivery_charge' => $deliveryCharge,
                'total_amount' => $totalAmount,
                'delivery_date' => $deliveryDateFormatted,
                'delivery_date_raw' => $deliveryDateRaw,
                'existing_order' => false,
                'address_updated' => true,
                'street' => $street,
                'barangay' => $barangay,
                'land_mark' => $landMark,
                'full_address' => $fullAddress,
                'free_delivery' => ($deliveryCharge == 0 && $subtotalAmount >= 500),
                'cutoff_applied' => ($deliveryDateRaw !== $_POST['delivery_date'] && $currentTime >= $cutoffTime)
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Cart Operations Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>