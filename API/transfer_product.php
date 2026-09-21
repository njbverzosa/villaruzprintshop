<?php
// API/transfer_product.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 1. CHECK LOGIN STATUS
// ==============================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['acc_number']) || !isset($_SESSION['user_role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId    = $_SESSION['user_id'];
$userRole  = $_SESSION['user_role'];
$accNumber = $_SESSION['acc_number'];

$action = $_POST['action'] ?? '';

// ==============================================
// 2. ROLE
// ==============================================
if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT f_name FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userName = $user ? $user['f_name'] : 'Unknown User';
} else {
    echo json_encode(['success' => false, 'message' => 'Unauthorized role: ' . $userRole]);
    exit;
}

$firstName = explode(' ', trim($userName))[0] ?? 'User';

// ==============================================
// 3. VERIFY CSRF
// ==============================================
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'CSRF token missing.']);
    exit;
}
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page and try again.']);
    exit;
}

// ==============================================
// HELPERS
// ==============================================
function sanitizeProductName(string $raw): string
{
    $name = trim($raw);
    $name = preg_replace('/\.(jpeg|jpg|png)$/i', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

function resolveUploadDir(string $projectRoot, string $folder): string
{
    $dir = $projectRoot . '/' . $folder . '/';
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return '';
        }
    }
    return is_writable($dir) ? $dir : '';
}

function generateProductNumber(PDO $pdo, string $table): string
{
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(product_number, 4) AS UNSIGNED)) AS max_num
        FROM {$table}
    ");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $next = ($row['max_num'] ?? 0) + 1;
    return 'PRD' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

function productNumberExists(PDO $pdo, string $table, string $productNumber): bool
{
    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE product_number = ? LIMIT 1");
    $stmt->execute([$productNumber]);
    return (bool) $stmt->fetch();
}

// ==============================================
// TARGET TABLE for both actions
// ==============================================
$targetTable  = 'merchandise_inventory';
$uploadFolder = 'Products';
$redirectUrl  = '../web/all_products.php';

// ==============================================
// ACTION: add_product (unchanged)
// ==============================================
if ($action === 'add_product') {

    $unit         = trim($_POST['unit'] ?? 'Pcs');
    $quantity     = intval($_POST['quantity'] ?? 0);
    $sellingPrice = floatval($_POST['selling_price'] ?? 0);
    $description  = isset($_POST['description']) ? trim($_POST['description']) : '';

    if ($sellingPrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Selling price must be greater than 0']);
        exit;
    }
    if ($quantity < 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity cannot be negative']);
        exit;
    }

    $productName = sanitizeProductName($_POST['product_name'] ?? '');
    if (empty($productName)) {
        echo json_encode(['success' => false, 'message' => 'Product name is required.']);
        exit;
    }
    if (!preg_match('/^[A-Za-z0-9\s,\.\(\)\-]+$/', $productName)) {
        echo json_encode(['success' => false, 'message' => 'Product name can only contain letters, numbers, spaces, and , . ( ) -']);
        exit;
    }

    $projectRoot = dirname(__DIR__);
    $uploadDir   = resolveUploadDir($projectRoot, $uploadFolder);
    if ($uploadDir === '') {
        echo json_encode(['success' => false, 'message' => 'Upload folder is not available or writable.']);
        exit;
    }

    $imagePath = null;
    $allowedExtensions = ['jpeg', 'jpg', 'png'];
    $allowedMime       = ['image/jpeg', 'image/jpg', 'image/png'];
    $fileName          = $productName . '.png';
    $destPath          = $uploadDir . $fileName;

    $useDefaultImage = isset($_POST['use_default_image']) && $_POST['use_default_image'] === '1';

    if ($useDefaultImage) {
        $imagePath = 'no-image.jpg';
    }
    elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Image too large. Max 5MB.']);
            exit;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPEG, JPG, or PNG images allowed.']);
            exit;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowedMime, true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPEG, JPG, or PNG images allowed.']);
            exit;
        }
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image.']);
            exit;
        }
        $imagePath = $fileName;
    }
    elseif (!empty($_POST['product_image_base64'])) {
        $dataUri = $_POST['product_image_base64'];
        if (!preg_match('/^data:image\/(\w+);base64,/', $dataUri, $m)) {
            echo json_encode(['success' => false, 'message' => 'Invalid camera image format.']);
            exit;
        }
        $type = strtolower($m[1]);
        if (!in_array($type, $allowedExtensions, true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPEG, JPG, or PNG camera images allowed.']);
            exit;
        }
        $data = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1), true);
        if ($data === false) {
            echo json_encode(['success' => false, 'message' => 'Could not decode camera image.']);
            exit;
        }
        if (strlen($data) > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Camera image too large. Max 5MB.']);
            exit;
        }
        if (file_put_contents($destPath, $data) === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to save camera image.']);
            exit;
        }
        $imagePath = $fileName;
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Product image is required.']);
        exit;
    }

    $checkStmt = $pdo->prepare("SELECT id FROM {$targetTable} WHERE product_name = :product_name");
    $checkStmt->execute([':product_name' => $productName]);
    if ($checkStmt->fetch()) {
        if ($imagePath && $imagePath !== 'no-image.jpg' && file_exists($uploadDir . $imagePath)) {
            unlink($uploadDir . $imagePath);
        }
        echo json_encode(['success' => false, 'message' => 'Product already exists!']);
        exit;
    }

    try {
        date_default_timezone_set('Asia/Manila');
        $last_restocked = date('j F Y g:i A');

        $productNumber = generateProductNumber($pdo, $targetTable);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO {$targetTable}
                (product_number, product_name, unit, qty_on_hand, selling_price, description, product_image, last_restocked)
            VALUES
                (:product_number, :product_name, :unit, :qty_on_hand, :selling_price, :description, :product_image, :last_restocked)
        ");

        $result = $stmt->execute([
            ':product_number' => $productNumber,
            ':product_name'   => $productName,
            ':unit'           => $unit,
            ':qty_on_hand'    => $quantity,
            ':selling_price'  => $sellingPrice,
            ':description'    => $description,
            ':product_image'  => $imagePath,
            ':last_restocked' => $last_restocked
        ]);

        if ($result) {
            $productId = $pdo->lastInsertId();

            $logDetails = "Added new product to {$targetTable}: {$productName} | Product #: {$productNumber} | Unit: {$unit} | Quantity: {$quantity} | Price: ₱{$sellingPrice} | Image: " . ($imagePath ?: 'None') . " | Description: " . ($description ?: 'N/A');

            $logStmt = $pdo->prepare("INSERT INTO logs (name, action, details, created_at) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$firstName, "Added New Product", $logDetails, $last_restocked]);

            $pdo->commit();

            echo json_encode([
                'success'        => true,
                'message'        => 'Product added successfully',
                'product_id'     => $productId,
                'product_number' => $productNumber,
                'product_name'   => $productName,
                'product_image'  => $imagePath,
                'table'          => $targetTable,
                'folder'         => $uploadFolder,
                'redirect'       => $redirectUrl
            ]);
        } else {
            $pdo->rollBack();
            if ($imagePath && $imagePath !== 'no-image.jpg' && file_exists($uploadDir . $imagePath)) {
                unlink($uploadDir . $imagePath);
            }
            echo json_encode(['success' => false, 'message' => 'Failed to add product']);
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($imagePath && $imagePath !== 'no-image.jpg' && file_exists($uploadDir . $imagePath)) {
            unlink($uploadDir . $imagePath);
        }
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==============================================
// ACTION: update_product
//   → INSERT into merchandise_inventory (admin price + business_name)
//   → INSERT into investors_sales (investor's original price)
// ==============================================
if ($action === 'update_product') {

    // Source (investors_inventory) info
    $sourceProductId = intval($_POST['product_id'] ?? 0);

    // Product fields (from the form)
    $unit          = trim($_POST['unit'] ?? 'Pcs');
    $quantity      = intval($_POST['quantity'] ?? 0);
    $sellingPrice  = floatval($_POST['selling_price'] ?? 0);   // admin's selling price
    $description   = isset($_POST['description']) ? trim($_POST['description']) : '';
    $replaceImage  = $_POST['replace_image'] ?? '0';

    $productName = sanitizeProductName($_POST['product_name'] ?? '');

    // ✅ Business name from the form (auto-filled from investors table)
    $businessName = trim($_POST['business_name'] ?? '');

    if ($sourceProductId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Missing source product ID.']);
        exit;
    }
    if (empty($productName)) {
        echo json_encode(['success' => false, 'message' => 'Product name is required.']);
        exit;
    }
    if (!preg_match('/^[A-Za-z0-9\s,\.\(\)\-]+$/', $productName)) {
        echo json_encode(['success' => false, 'message' => 'Product name can only contain letters, numbers, spaces, and , . ( ) -']);
        exit;
    }
    if ($sellingPrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Selling price must be greater than 0']);
        exit;
    }
    if ($quantity < 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity cannot be negative']);
        exit;
    }

    // ----- Fetch source investor product -----
    $src = $pdo->prepare("SELECT * FROM investors_inventory WHERE id = ? LIMIT 1");
    $src->execute([$sourceProductId]);
    $source = $src->fetch(PDO::FETCH_ASSOC);

    if (!$source) {
        echo json_encode(['success' => false, 'message' => "Source product id {$sourceProductId} not found in investors_inventory."]);
        exit;
    }

    $investorAccNumber = $source['acc_number'];
    $investorPrice     = (float) $source['selling_price'];
    $investorUnit      = $source['unit'] ?? $unit;

    // ✅ If the form didn't send a business_name, fall back to the investors table
    if ($businessName === '') {
        $bizStmt = $pdo->prepare("SELECT business_name FROM investors WHERE acc_number = ? LIMIT 1");
        $bizStmt->execute([$investorAccNumber]);
        $bizRow = $bizStmt->fetch(PDO::FETCH_ASSOC);
        if ($bizRow) {
            $businessName = $bizRow['business_name'] ?? '';
        }
    }

    // ----- Duplicate name check -----
    $dup = $pdo->prepare("SELECT id FROM {$targetTable} WHERE product_name = :product_name");
    $dup->execute([':product_name' => $productName]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Product already exists in merchandise_inventory!']);
        exit;
    }

    // ----- Resolve upload folder -----
    $projectRoot = dirname(__DIR__);
    $uploadDir   = resolveUploadDir($projectRoot, $uploadFolder);
    if ($uploadDir === '') {
        echo json_encode(['success' => false, 'message' => 'Upload folder is not available or writable.']);
        exit;
    }

    // ----- Handle image -----
    $imagePath = null;
    $fileName  = $productName . '.png';
    $destPath  = $uploadDir . $fileName;

    if ($replaceImage === '1') {
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['product_image'];

            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'Image too large. Max 5MB.']);
                exit;
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpeg', 'jpg', 'png'], true)) {
                echo json_encode(['success' => false, 'message' => 'Only JPEG, JPG, or PNG images allowed.']);
                exit;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
                echo json_encode(['success' => false, 'message' => 'Only JPEG, JPG, or PNG images allowed.']);
                exit;
            }
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image.']);
                exit;
            }
            $imagePath = $fileName;
        }
        elseif (!empty($_POST['product_image_base64'])) {
            $dataUri = $_POST['product_image_base64'];
            if (!preg_match('/^data:image\/(\w+);base64,/', $dataUri, $m)) {
                echo json_encode(['success' => false, 'message' => 'Invalid camera image format.']);
                exit;
            }
            $data = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1), true);
            if ($data === false) {
                echo json_encode(['success' => false, 'message' => 'Could not decode camera image.']);
                exit;
            }
            if (strlen($data) > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'Camera image too large. Max 5MB.']);
                exit;
            }
            if (file_put_contents($destPath, $data) === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to save camera image.']);
                exit;
            }
            $imagePath = $fileName;
        }
        else {
            echo json_encode(['success' => false, 'message' => 'No image provided for replacement.']);
            exit;
        }
    }
    elseif ($replaceImage === '2') {
        $imagePath = null;
    }
    elseif ($replaceImage === '0') {
        if (!empty($source['product_image'])) {
            $oldName = $source['product_image'];
            $oldFile = dirname(__DIR__) . '/Inv_Products/' . $oldName;

            if (file_exists($oldFile) && copy($oldFile, $destPath)) {
                $imagePath = $fileName;
            } else {
                $imagePath = $oldName;
            }
        }
    }

    // ----- Unique product number -----
    $requestedNumber = trim($_POST['product_number'] ?? '');
    if ($requestedNumber !== '' && !productNumberExists($pdo, $targetTable, $requestedNumber)) {
        $productNumber = $requestedNumber;
    } else {
        $productNumber = generateProductNumber($pdo, $targetTable);
    }

    // ----- Total amount owed to the investor -----
    $investorTotalAmount = $investorPrice * $quantity;

    // ----- Date/time -----
    date_default_timezone_set('Asia/Manila');
    $last_restocked = date('j F Y g:i A');
    $dateTimeSold   = date('j F Y g:i A');

    try {
        $pdo->beginTransaction();

        // ============================
        // INSERT 1 → merchandise_inventory (admin price + business_name)
        // ============================
        $stmt = $pdo->prepare("
            INSERT INTO {$targetTable}
                (product_number, product_name, business_name, unit, qty_on_hand, selling_price, description, product_image, last_restocked)
            VALUES
                (:product_number, :product_name, :business_name, :unit, :qty_on_hand, :selling_price, :description, :product_image, :last_restocked)
        ");

        $stmt->execute([
            ':product_number' => $productNumber,
            ':product_name'   => $productName,
            ':business_name'  => $businessName,
            ':unit'           => $unit,
            ':qty_on_hand'    => $quantity,
            ':selling_price'  => $sellingPrice,
            ':description'    => $description,
            ':product_image'  => $imagePath,
            ':last_restocked' => $last_restocked
        ]);

        $newId = $pdo->lastInsertId();

        // ============================
        // INSERT 2 → investors_sales
        // ============================
        $orderId = $newId;

        $salesStmt = $pdo->prepare("
            INSERT INTO investors_sales
                (order_id, acc_number, product_name, selling_price, status,
                 pieces, unit, total_amount, date_time_sold)
            VALUES
                (:order_id, :acc_number, :product_name, :selling_price, :status,
                 :pieces, :unit, :total_amount, :date_time_sold)
        ");

        $salesStmt->execute([
            ':order_id'        => $orderId,
            ':acc_number'      => $investorAccNumber,
            ':product_name'    => $productName,
            ':selling_price'   => number_format($investorPrice, 2, '.', ''),
            ':status'          => 'PAID',
            ':pieces'          => $quantity,
            ':unit'            => $investorUnit,
            ':total_amount'    => number_format($investorTotalAmount, 2, '.', ''),
            ':date_time_sold'  => $dateTimeSold
        ]);

        // ============================
        // LOG
        // ============================
        $logDetails = "Transferred product to {$targetTable}: {$productName} | Business: {$businessName} | Product #: {$productNumber} | Unit: {$unit} | Qty: {$quantity} | Admin Price: ₱{$sellingPrice} | Investor Price: ₱{$investorPrice} | Investor Total: ₱{$investorTotalAmount} | Investor: {$investorAccNumber} | Image: " . ($imagePath ?: 'None');

        $logStmt = $pdo->prepare("INSERT INTO logs (name, action, details, created_at) VALUES (?, ?, ?, ?)");
        $logStmt->execute([$firstName, "Transferred Product", $logDetails, $last_restocked]);

        $pdo->commit();

        echo json_encode([
            'success'        => true,
            'message'        => 'Product transferred and sale recorded',
            'product_id'     => $newId,
            'product_number' => $productNumber,
            'product_name'   => $productName,
            'business_name'  => $businessName,
            'product_image'  => $imagePath,
            'table'          => $targetTable,
            'folder'         => $uploadFolder,
            'investor_price' => number_format($investorPrice, 2, '.', ''),
            'investor_total' => number_format($investorTotalAmount, 2, '.', ''),
            'investor_acc'   => $investorAccNumber,
            'redirect'       => $redirectUrl
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($imagePath && $imagePath !== 'no-image.jpg' && file_exists($uploadDir . $imagePath)) {
            unlink($uploadDir . $imagePath);
        }
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==============================================
// FALLBACK
// ==============================================
echo json_encode(['success' => false, 'message' => 'Invalid action: ' . htmlspecialchars($action)]);