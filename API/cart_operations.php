<?php
// Customer_API/cart_operations.php
session_start();
require_once __DIR__ . '/../DB_Conn/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Verify CSRF token
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

                echo json_encode(['success' => true, 'message' => 'Quantity decreased']);
            } else {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ?");
                $stmt->execute([$cartId]);

                echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
            }
            break;

        case 'remove':
            $cartId = $_POST['cart_id'] ?? 0;

            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ?");
            $stmt->execute([$cartId]);

            echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
            break;

        case 'clear_cart':
            $userAccNumber = $_POST['acc_number'] ?? '';

            if (!empty($userAccNumber)) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE acc_number = ?");
                $stmt->execute([$userAccNumber]);
                $deletedCount = $stmt->rowCount();
                echo json_encode(['success' => true, 'message' => "$deletedCount item(s) cleared from cart"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Account number is required']);
            }
            break;

        case 'validate_delivery_number':
            $deliveryNumber = $_POST['delivery_number'] ?? '';

            if (empty($deliveryNumber)) {
                echo json_encode(['success' => false, 'message' => 'Delivery number is required']);
                exit();
            }

            // Check if delivery number exists in for_deliveries
            $stmt = $pdo->prepare("SELECT id, ordered_by, delivery_address, total_amount, status FROM for_deliveries WHERE delivery_number = ?");
            $stmt->execute([$deliveryNumber]);
            $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($delivery) {
                echo json_encode([
                    'success' => true,
                    'exists' => true,
                    'message' => 'Valid delivery number',
                    'delivery_data' => $delivery
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'exists' => false,
                    'message' => 'Delivery number not found. Please check and try again.'
                ]);
            }
            break;

        case 'add_to_existing_delivery':
            $userAccNumber = $_POST['acc_number'] ?? '';
            $existingDeliveryNumber = $_POST['delivery_number'] ?? '';

            if (empty($userAccNumber)) {
                echo json_encode(['success' => false, 'message' => 'User not authenticated']);
                exit();
            }

            if (empty($existingDeliveryNumber)) {
                echo json_encode(['success' => false, 'message' => 'Delivery number is required']);
                exit();
            }

            // Get cart items for this user
            $stmt = $pdo->prepare("SELECT * FROM cart WHERE acc_number = ?");
            $stmt->execute([$userAccNumber]);
            $cartItems = $stmt->fetchAll();

            if (empty($cartItems)) {
                echo json_encode(['success' => false, 'message' => 'Cart is empty']);
                exit();
            }

            // Get existing delivery details
            $stmt = $pdo->prepare("SELECT id, ordered_by, delivery_address, total_amount, charge, city, barangay FROM for_deliveries WHERE delivery_number = ?");
            $stmt->execute([$existingDeliveryNumber]);
            $existingDelivery = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingDelivery) {
                echo json_encode(['success' => false, 'message' => 'Delivery number not found']);
                exit();
            }

            $deliveryId = $existingDelivery['id'];

            // Calculate subtotal from cart items
            $subtotalAmount = 0;
            foreach ($cartItems as $item) {
                $subtotalAmount += $item['total_amount'];
            }

            // Calculate new total amount
            $newTotalAmount = $existingDelivery['total_amount'] + $subtotalAmount;

            $dateTimeSold = date('D, j M Y g:i A');
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $baseUrl = $protocol . $host;
            $qrCodeLink = $baseUrl . '/delivery_receipt.php?delivery_number=' . urlencode($existingDeliveryNumber);

            // Start transaction
            $pdo->beginTransaction();

            // Update for_deliveries total amount
            $stmt = $pdo->prepare("UPDATE for_deliveries SET total_amount = ? WHERE id = ?");
            $stmt->execute([$newTotalAmount, $deliveryId]);

            // Insert items into order_status_history
            $successCount = 0;
            foreach ($cartItems as $item) {
                $stmt = $pdo->prepare("INSERT INTO order_status_history (acc_number, order_id, delivery_address, delivery_number, product_name, selling_price, status, pieces, unit, total_amount, date_time_sold, delivery_date, qr_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([
                    $userAccNumber,
                    $deliveryId,
                    $existingDelivery['delivery_address'],
                    $existingDeliveryNumber,
                    $item['product_name'],
                    $item['selling_price'],
                    'PENDING',
                    $item['pieces'],
                    $item['unit'],
                    $item['total_amount'],
                    $dateTimeSold,
                    NULL,
                    $qrCodeLink
                ]);

                if ($result) {
                    $successCount++;
                }
            }

            if ($successCount === 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'No items were inserted']);
                exit();
            }

            // Clear cart
            $stmt = $pdo->prepare("DELETE FROM cart WHERE acc_number = ?");
            $stmt->execute([$userAccNumber]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => $successCount . ' item(s) added successfully to Delivery #: ' . $existingDeliveryNumber,
                'delivery_number' => $existingDeliveryNumber,
                'delivery_id' => $deliveryId,
                'items_count' => $successCount,
                'subtotal_added' => $subtotalAmount,
                'previous_total' => $existingDelivery['total_amount'],
                'new_total' => $newTotalAmount
            ]);
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
                // Ensure we're using proper decimal values
                $itemTotal = floatval($item['total_amount']);
                $subtotalAmount += $itemTotal;
            }

            // Round subtotal to 2 decimal places
            $subtotalAmount = round($subtotalAmount, 2);

            // ============================================================
            // CALCULATE DELIVERY CHARGE BASED ON BARANGAY (FREE FOR ₱500+)
            // ============================================================
            $deliveryCharge = 0;

            // Check if subtotal is ₱500 or more -> FREE DELIVERY
            if ($subtotalAmount >= 500) {
                $deliveryCharge = 0;
            } else {
                $barangayLower = strtolower(trim($barangay));

                // Barangays with ₱15 delivery fee
                $barangay15 = ['poblacion'];

                // Barangays with ₱30 delivery fee
                $barangay30 = ['bobonot', 'amalbalan', 'gais-guipe', 'gaisguipe', 'hermosa', 'petal'];

                if (in_array($barangayLower, $barangay15)) {
                    $deliveryCharge = 15;
                } elseif (in_array($barangayLower, $barangay30)) {
                    $deliveryCharge = 30;
                } else {
                    // Default charge for other barangays
                    $deliveryCharge = 50;
                }
            }

            // Format delivery date
            $deliveryDateFormatted = '';
            if (!empty($deliveryDateRaw)) {
                $timestamp = strtotime($deliveryDateRaw);
                $deliveryDateFormatted = date('j F Y', $timestamp);
            }

            // Calculate total amount with delivery charge
            $totalAmount = $subtotalAmount + $deliveryCharge;
            // Round total to 2 decimal places
            $totalAmount = round($totalAmount, 2);

            date_default_timezone_set('Asia/Manila');
            $dateTimeSold = date('j F Y');
            $MYD = date('F Y');

            // ============================================================
            // UPDATE CUSTOMER'S ADDRESS IN customers TABLE
            // ============================================================
            try {
                // Update the customer's complete_delivery_address with the combined address
                if (!empty($fullAddress)) {
                    $updateCustomerStmt = $pdo->prepare("UPDATE customers SET complete_delivery_address = ? WHERE acc_number = ?");
                    $updateCustomerStmt->execute([$fullAddress, $userAccNumber]);
                }
            } catch (PDOException $e) {
                // Log error but don't stop the checkout process
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

                    // Start transaction
                    $pdo->beginTransaction();

                    // Get the highest order_id for this delivery
                    $stmt = $pdo->prepare("SELECT MAX(order_id) as max_id FROM order_status_history WHERE delivery_number = ?");
                    $stmt->execute([$deliveryNumber]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $nextOrderId = ($result['max_id'] ?? 0) + 1;

                    // Insert new items into order_status_history with existing delivery_number
                    $successCount = 0;
                    foreach ($cartItems as $item) {
                        // Ensure proper decimal handling for item total
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

                    // Recalculate total amount for the delivery
                    $totalStmt = $pdo->prepare("SELECT SUM(total_amount) as new_total FROM order_status_history WHERE delivery_number = ?");
                    $totalStmt->execute([$deliveryNumber]);
                    $newTotalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
                    $newTotalAmount = floatval($newTotalResult['new_total'] ?? 0);

                    // Add delivery charge to the new total (if not free)
                    $newTotalAmount += $deliveryCharge;
                    $newTotalAmount = round($newTotalAmount, 2);

                    // Update for_deliveries table with new total amount including delivery charge
                    $updateDeliveryStmt = $pdo->prepare("UPDATE for_deliveries SET total_amount = ?, charge = ? WHERE delivery_number = ?");
                    $updateDeliveryStmt->execute([$newTotalAmount, $deliveryCharge, $deliveryNumber]);

                    // Clear cart
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
                        'free_delivery' => ($deliveryCharge == 0 && $subtotalAmount >= 500)
                    ]);
                    exit();
                }
            }

            // If not existing order, create new order
            function generateUniqueDeliveryNumber($pdo)
            {
                $maxAttempts = 100;
                $attempt = 0;

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

            // Start transaction
            $pdo->beginTransaction();

            // Insert into for_deliveries with total amount including delivery charge
            $stmt = $pdo->prepare("INSERT INTO for_deliveries (acc_number, ordered_by, delivery_number, delivery_address, total_amount, charge, status, date_time_sold, qr_code, delivery_date, delivery_m_y) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?)");
            $result1 = $stmt->execute([
                $userAccNumber,
                $customerName,
                $deliveryNumber,
                $fullAddress,
                $totalAmount,  // Now includes delivery charge
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

            // Insert items into order_status_history
            $successCount = 0;
            foreach ($cartItems as $item) {
                // Ensure proper decimal handling for item total
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

            // Clear cart
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
                'existing_order' => false,
                'address_updated' => true,
                'street' => $street,
                'barangay' => $barangay,
                'land_mark' => $landMark,
                'full_address' => $fullAddress,
                'free_delivery' => ($deliveryCharge == 0 && $subtotalAmount >= 500)
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