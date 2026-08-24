<?php
// update_delivery_date.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../DB_Conn/config.php';

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// Check authentication
if (!isset($_POST['acc_number']) || empty($_POST['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$accNumber = $_POST['acc_number'];
$deliveryNumber = $_POST['delivery_number'] ?? '';
$deliveryDate = $_POST['delivery_date'] ?? null;

if (empty($deliveryNumber)) {
    echo json_encode(['success' => false, 'message' => 'Delivery number is required']);
    exit();
}

// Verify ownership
$stmt = $pdo->prepare("SELECT id FROM for_deliveries WHERE delivery_number = ? AND acc_number = ?");
$stmt->execute([$deliveryNumber, $accNumber]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Function to format date to "Mon, 4 May 2026" format
function formatDeliveryDate($date) {
    if (empty($date)) return null;
    
    // Parse the date (supports YYYY-MM-DD format from date input)
    $timestamp = strtotime($date);
    if (!$timestamp) return null;
    
    // Format: "Mon, 4 May 2026"
    return date('D, j M Y', $timestamp);
}

try {
    // Format the date before saving
    $formattedDate = formatDeliveryDate($deliveryDate);
    
    $sql = "UPDATE for_deliveries SET delivery_date = :delivery_date WHERE delivery_number = :delivery_number AND acc_number = :acc_number";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':delivery_date' => $formattedDate,
        ':delivery_number' => $deliveryNumber,
        ':acc_number' => $accNumber
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Delivery date updated successfully',
        'formatted_date' => $formattedDate
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>