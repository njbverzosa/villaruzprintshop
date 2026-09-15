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

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$accNumber = $_SESSION['acc_number'];

// ==============================================
// 2. FETCH USER NAME + authorize_access
// ==============================================
$userName = 'Unknown User';
$authorizeAccess = 0;

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
// 3. DECIDE TARGET TABLE
// ==============================================
$allowedInv = [0, 1, 2];

if ($authorizeAccess === 3) {
    $targetTable = 'investors_product';
} elseif (in_array($authorizeAccess, $allowedInv, true)) {
    $targetTable = 'merchandise_inventory';
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

    $productName = trim($_POST['product_name']);
    $unit = trim($_POST['unit']);
    $quantity = intval($_POST['quantity']);
    $sellingPrice = floatval($_POST['selling_price']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if (empty($productName)) {
        echo json_encode(['success' => false, 'message' => 'Product name is required']);
        exit;
    }
    if ($sellingPrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Unit cost must be greater than 0']);
        exit;
    }
    if ($quantity < 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity cannot be negative']);
        exit;
    }

    // ==============================================
    // 5. HANDLE PRODUCT IMAGE
    // Saves into /Products/ and stores filename in `product_image`
    // Priority: 1) uploaded file  2) base64 camera image  3) none
    // ==============================================
    $imagePath = null;

    $uploadDir = __DIR__ . '/../Products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Case A: standard file upload
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

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = 'prd_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image.']);
            exit;
        }

        // Store only the filename (image sits in /Products/)
        $imagePath = $fileName;
    }
    // Case B: camera capture (base64 image data URI)
    elseif (!empty($_POST['product_image_base64'])) {
        $dataUri = $_POST['product_image_base64'];

        if (!preg_match('/^data:image\/(\w+);base64,/', $dataUri, $m)) {
            echo json_encode(['success' => false, 'message' => 'Invalid camera image format.']);
            exit;
        }

        $type = strtolower($m[1]); // png, jpeg, jpg, webp
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

        $ext = ($type === 'jpeg') ? 'jpg' : $type;
        $fileName = 'prd_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        if (file_put_contents($destPath, $data) === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to save camera image.']);
            exit;
        }

        // Store only the filename (image sits in /Products/)
        $imagePath = $fileName;
    }

    // ==============================================
    // 6. CHECK DUPLICATE
    // ==============================================
    $checkStmt = $pdo->prepare("SELECT id FROM {$targetTable} WHERE product_name = :product_name");
    $checkStmt->execute([':product_name' => $productName]);
    if ($checkStmt->fetch()) {
        // clean up orphan image if any
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
        // 7. GENERATE PRODUCT NUMBER
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
        // 8. INSERT  (column: product_image)
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
                'product_image' => $imagePath,
                'table' => $targetTable
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