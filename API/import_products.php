<?php
// API/import_products.php
session_start();
require_once '../DB_Conn/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['acc_number'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userName = $_SESSION['fullname'];  
$firstName = explode(' ', trim($userName))[0];  
 
function capitalizeUnit($unit)
{
    $unit = strtolower(trim($unit));
    return ucfirst($unit);
}

function cleanDescription($description)
{
    if (empty($description)) {
        return null;
    }
    return trim($description);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Use POST.']);
    exit;
}

if (!isset($_POST['products']) || empty($_POST['products'])) {
    echo json_encode(['success' => false, 'message' => 'No products data received.']);
    exit;
}

$products = json_decode($_POST['products'], true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format: ' . json_last_error_msg()]);
    exit;
}

if (!is_array($products) || count($products) === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or empty products data']);
    exit;
}

if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$existingProducts = [];
$newProducts = [];
$duplicateList = [];

foreach ($products as $index => $product) {
    $unit = capitalizeUnit($product['unit'] ?? '');
    $product_name = trim($product['product_name'] ?? '');
    $quantity = isset($product['quantity']) ? floatval($product['quantity']) : 0;
    $unit_cost = isset($product['unit_cost']) ? floatval($product['unit_cost']) : 0;
    $description = cleanDescription($product['description'] ?? '');

    if (empty($unit)) {
        $duplicateList[] = [
            'row' => $index + 1,
            'product_name' => $product_name,
            'unit' => $unit,
            'existing_unit' => 'N/A',
            'quantity' => $quantity,
            'unit_cost' => $unit_cost,
            'description' => $description,
            'error' => 'Unit is required'
        ];
        continue;
    }

    if (empty($product_name)) {
        $duplicateList[] = [
            'row' => $index + 1,
            'product_name' => 'EMPTY',
            'unit' => $unit,
            'existing_unit' => 'N/A',
            'quantity' => $quantity,
            'unit_cost' => $unit_cost,
            'description' => $description,
            'error' => 'Product name is required'
        ];
        continue;
    }

    if ($quantity <= 0) {
        $duplicateList[] = [
            'row' => $index + 1,
            'product_name' => $product_name,
            'unit' => $unit,
            'existing_unit' => 'N/A',
            'quantity' => $quantity,
            'unit_cost' => $unit_cost,
            'description' => $description,
            'error' => 'Quantity must be greater than 0'
        ];
        continue;
    }

    if ($unit_cost <= 0) {
        $duplicateList[] = [
            'row' => $index + 1,
            'product_name' => $product_name,
            'unit' => $unit,
            'existing_unit' => 'N/A',
            'quantity' => $quantity,
            'unit_cost' => $unit_cost,
            'description' => $description,
            'error' => 'Unit cost must be greater than 0'
        ];
        continue;
    }

    $checkStmt = $pdo->prepare("SELECT id, product_name, unit FROM merchandise_inventory WHERE product_name = ? AND unit = ?");
    $checkStmt->execute([$product_name, $unit]);

    if ($checkStmt->rowCount() > 0) {
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $duplicateList[] = [
            'row' => $index + 1,
            'product_name' => $product_name,
            'unit' => $unit,
            'existing_unit' => $existing['unit'],
            'quantity' => $quantity,
            'unit_cost' => $unit_cost,
            'description' => $description,
            'error' => 'Product with same unit already exists'
        ];
    } else {
        $newProducts[] = [
            'index' => $index,
            'unit' => $unit,
            'product_name' => $product_name,
            'quantity' => $quantity,
            'unit_cost' => $unit_cost,
            'description' => $description
        ];
    }
}

if (count($duplicateList) > 0) {
    echo json_encode([
        'success' => false,
        'has_duplicates' => true,
        'duplicates' => $duplicateList,
        'new_products_count' => count($newProducts),
        'duplicate_count' => count($duplicateList),
        'message' => 'Some products already exist in the inventory. Please review.'
    ]);
    exit;
}

$imported = 0;
$errors = [];
$successList = [];

try {
    $pdo->beginTransaction();

    date_default_timezone_set('Asia/Manila');
     $last_restocked = date('j F Y g:i A');

    $stmt = $pdo->prepare("INSERT INTO merchandise_inventory (unit, product_name, qty_on_hand, selling_price, description, last_restocked) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($newProducts as $product) {
        $unit = $product['unit'];
        $product_name = $product['product_name'];
        $quantity = $product['quantity'];
        $unit_cost = $product['unit_cost'];
        $description = $product['description'];

        if ($stmt->execute([$unit, $product_name, $quantity, $unit_cost, $description, $last_restocked])) {
            $imported++;
            $successList[] = $product_name . ' (' . $unit . ')';
        } else {
            $errorInfo = $stmt->errorInfo();
            $errors[] = "Row " . ($product['index'] + 1) . ": Database error for $product_name - " . ($errorInfo[2] ?? 'Unknown error');
        }
    }

    // Insert into logs
    if ($imported > 0) {
        $logDetails = "Imported {$imported} products via Excel: " . implode(", ", $successList);
        $logStmt = $pdo->prepare("INSERT INTO logs (name, action, details, created_at) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$firstName, "Imported Products", $logDetails, $last_restocked]);
    }

    $pdo->commit();

    $response = [
        'success' => true,
        'imported_count' => $imported,
        'total_rows' => count($newProducts),
        'error_count' => count($errors),
        'errors' => $errors,
        'imported_products' => $successList,
        'message' => "Successfully imported $imported out of " . count($newProducts) . " products."
    ];

    if ($imported === 0) {
        $response['success'] = false;
        $response['message'] = "No products were imported. Please check your data.";
    }

    echo json_encode($response);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>