<?php
// API/add_product.php
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

// Setted from login
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$accNumber = $_SESSION['acc_number'];

if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT f_name, authorize_access FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userName = $user['f_name'];
        $authorizeAccess = (int) ($user['authorize_access'] ?? 0);
    }
}

$firstName = explode(' ', trim($userName))[0] ?? 'User';

// ==============================================
// 3. DECIDE TARGET TABLE + UPLOAD FOLDER
//    based on authorize_access
// ==============================================
$allowedInv = [0, 1, 2];

if ($authorizeAccess === 3) {
    // Investors → /Inv_Products/
    $targetTable = 'investors_product';
    $uploadFolder = 'Inv_Products';
} elseif (in_array($authorizeAccess, $allowedInv, true)) {
    // Merchandise inventory → /Products/
    $targetTable = 'merchandise_inventory';
    $uploadFolder = 'Products';
} else {
    echo json_encode(['success' => false, 'message' => 'Your account is not allowed to add products.']);
    exit;
}

// ==============================================
// 4. VERIFY CSRF
// ==============================================
if (!isset($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'CSRF token missing from request']);
    exit;
}
if (!isset($_SESSION['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'CSRF token not found in session']);
    exit;
}
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page and try again.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add_product') {

    $unit = trim($_POST['unit']);
    $quantity = intval($_POST['quantity']);
    $sellingPrice = floatval($_POST['selling_price']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if ($sellingPrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Selling price must be greater than 0']);
        exit;
    }
    if ($quantity < 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity cannot be negative']);
        exit;
    }

    // ==============================================
    // 5. HANDLE PRODUCT IMAGE
    //    Product name will be derived from the image filename
    // ==============================================
    $imagePath = null;
    $productName = null;

    $uploadDir = __DIR__ . '/../' . $uploadFolder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // ---- Case A: standard file upload ----
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];

        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Image too large. Max 5MB.']);
            exit;
        }

        $allowedMime = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMime, true)) {
            echo json_encode(['success' => false, 'message' => 'Only PNG, JPG, JPEG, or WEBP images allowed.']);
            exit;
        }

        // Extract filename WITHOUT extension → becomes the product name
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $productName = sanitizeProductName($originalName);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = 'prd_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image.']);
            exit;
        }

        $imagePath = $fileName;
    }
    // ---- Case B: camera capture (base64 data URI) ----
    elseif (!empty($_POST['product_image_base64'])) {
        $dataUri = $_POST['product_image_base64'];

        if (!preg_match('/^data:image\/(\w+);base64,/', $dataUri, $m)) {
            echo json_encode(['success' => false, 'message' => 'Invalid camera image format.']);
            exit;
        }

        $type = strtolower($m[1]);
        if (!in_array($type, ['png', 'jpeg', 'jpg', 'webp'], true)) {
            echo json_encode(['success' => false, 'message' => 'Unsupported camera image type.']);
            exit;
        }

        $data = substr($dataUri, strpos($dataUri, ',') + 1);
        $data = base64_decode($data, true);

        if ($data === false) {
            echo json_encode(['success' => false, 'message' => 'Could not decode camera image.']);
            exit;
        }

        if (strlen($data) > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Camera image too large. Max 5MB.']);
            exit;
        }

        // For camera captures: derive product name from the user-submitted product_name
        $rawName = $_POST['product_name'] ?? 'product';
        $productName = sanitizeProductName($rawName);

        $ext = ($type === 'jpeg') ? 'jpg' : $type;
        $fileName = 'prd_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        if (file_put_contents($destPath, $data) === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to save camera image.']);
            exit;
        }

        $imagePath = $fileName;
    }
    // ---- Case C: no image ----
    else {
        echo json_encode(['success' => false, 'message' => 'Product image is required.']);
        exit;
    }

    // ==============================================
    // 6. FINAL PRODUCT NAME VALIDATION
    // ==============================================
    if (empty($productName)) {
        if ($imagePath && file_exists($uploadDir . $imagePath)) {
            unlink($uploadDir . $imagePath);
        }
        echo json_encode(['success' => false, 'message' => 'Product name could not be determined from image.']);
        exit;
    }

    // ==============================================
    // 7. CHECK DUPLICATE PRODUCT NAME
    // ==============================================
    $checkStmt = $pdo->prepare("SELECT id FROM {$targetTable} WHERE product_name = :product_name");
    $checkStmt->execute([':product_name' => $productName]);
    if ($checkStmt->fetch()) {
        if ($imagePath && file_exists($uploadDir . $imagePath)) {
            unlink($uploadDir . $imagePath);
        }
        echo json_encode(['success' => false, 'message' => 'Product already exists!']);
        exit;
    }

    try {
        date_default_timezone_set('Asia/Manila');
        $last_restocked = date('j F Y g:i A');

        // ==============================================
        // 8. GENERATE PRODUCT NUMBER
        // ==============================================
        $stmt = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING(product_number, 4) AS UNSIGNED)) AS max_num
            FROM {$targetTable}
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNumber = ($result['max_num'] ?? 0) + 1;
        $productNumber = 'PRD' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        // ==============================================
        // 9. INSERT
        // ==============================================
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO {$targetTable}
                (product_number, product_name, unit, qty_on_hand, selling_price, description, product_image, last_restocked)
            VALUES
                (:product_number, :product_name, :unit, :qty_on_hand, :selling_price, :description, :product_image, :last_restocked)
        ");

        $result = $stmt->execute([
            ':product_number' => $productNumber,
            ':product_name' => $productName,
            ':unit' => $unit,
            ':qty_on_hand' => $quantity,
            ':selling_price' => $sellingPrice,
            ':description' => $description,
            ':product_image' => $imagePath,
            ':last_restocked' => $last_restocked
        ]);

        if ($result) {
            $productId = $pdo->lastInsertId();

            $logDetails = "Added new product to {$targetTable}: {$productName} | Product #: {$productNumber} | Unit: {$unit} | Quantity: {$quantity} | Price: ₱{$sellingPrice} | Image: " . ($imagePath ?: 'None') . " | Description: " . ($description ?: 'N/A');

            $logStmt = $pdo->prepare("INSERT INTO logs (name, action, details, created_at) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$firstName, "Added New Product", $logDetails, $last_restocked]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Product added successfully',
                'product_id' => $productId,
                'product_number' => $productNumber,
                'product_name' => $productName,
                'product_image' => $imagePath,
                'table' => $targetTable,
                'folder' => $uploadFolder
            ]);
        } else {
            $pdo->rollBack();
            if ($imagePath && file_exists($uploadDir . $imagePath))
                unlink($uploadDir . $imagePath);
            echo json_encode(['success' => false, 'message' => 'Failed to add product']);
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        if ($imagePath && file_exists($uploadDir . $imagePath))
            unlink($uploadDir . $imagePath);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

// ==============================================
// HELPER: Sanitize product name (from image filename)
// ==============================================
function sanitizeProductName($name)
{
    // Remove extension if present
    $name = pathinfo($name, PATHINFO_FILENAME);

    // Replace underscores, hyphens, and multiple spaces with single space
    $name = preg_replace('/[_\-\s]+/', ' ', $name);

    // Remove characters that aren't letters, numbers, spaces, or common symbols
    $name = preg_replace('/[^A-Za-z0-9\s\.\,\&\'\(\)]/', '', $name);

    // Trim and collapse multiple spaces
    $name = trim(preg_replace('/\s+/', ' ', $name));

    // Capitalize each word
    $name = ucwords(strtolower($name));

    return $name;
}