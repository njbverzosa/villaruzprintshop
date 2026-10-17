<?php
// API/add_product.php
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

// ==============================================
// 2. ROLE-BASED: TARGET TABLE + UPLOAD FOLDER
// ==============================================
$userName        = 'Unknown User';
$targetTable     = '';
$redirectUrl     = '';
$uploadFolder    = '';
$insertAccNumber = false;

if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT f_name FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userName = $user['f_name'];
    }
    $targetTable     = 'merchandise_inventory';
    $uploadFolder    = 'Products';
    $redirectUrl     = '../web/shop.php';
    $insertAccNumber = false;

} elseif ($userRole === 'Investor') {
    $stmt = $pdo->prepare("SELECT f_name, acc_number FROM investors WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userName  = $user['f_name'];
        $accNumber = $user['acc_number'];
    }
    $targetTable     = 'investors_inventory';
    $uploadFolder    = 'Inv_Products';
    $redirectUrl     = '../investors/investors_product.php';
    $insertAccNumber = true;

} else {
    echo json_encode(['success' => false, 'message' => 'Unauthorized role: ' . $userRole]);
    exit;
}

$firstName = explode(' ', trim($userName))[0] ?? 'User';

// ==============================================
// 3. VERIFY CSRF
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

    // ==============================================
    // 3b. READ + SANITIZE CATEGORY GROUP
    // ==============================================
    $categoryGroup = trim($_POST['category_group'] ?? '');

    if ($categoryGroup === '__new__') {
        $categoryGroup = trim($_POST['new_category_group'] ?? '');
    }

    if (mb_strlen($categoryGroup) > 50) {
        $categoryGroup = mb_substr($categoryGroup, 0, 50);
    }

    $categoryGroupDb = ($categoryGroup === '') ? null : $categoryGroup;

    // ==============================================
    // 4. READ + SANITIZE PRODUCT NAME
    // ==============================================
    $rawName     = $_POST['product_name'] ?? '';
    $productName = trim($rawName);
    $productName = preg_replace('/\.(jpeg|jpg|png)$/i', '', $productName);
    $productName = preg_replace('/\s+/', ' ', $productName);
    $productName = trim($productName);

    if (empty($productName)) {
        echo json_encode(['success' => false, 'message' => 'Product name is required.']);
        exit;
    }

    if (!preg_match('/^[A-Za-z0-9\s,\.\(\)\-]+$/', $productName)) {
        echo json_encode([
            'success' => false,
            'message' => 'Product name can only contain letters, numbers, spaces, and , . ( ) -'
        ]);
        exit;
    }

    // ==============================================
    // 5. RESOLVE UPLOAD DIRECTORY (role-based)
    // ==============================================
    $projectRoot = dirname(__DIR__);
    $uploadDir   = $projectRoot . '/' . $uploadFolder . '/';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode([
                'success' => false,
                'message' => 'Upload folder could not be created: ' . $uploadDir
            ]);
            exit;
        }
    }

    if (!is_writable($uploadDir)) {
        echo json_encode([
            'success' => false,
            'message' => 'Upload folder is not writable: ' . $uploadDir
        ]);
        exit;
    }

    // ==============================================
    // 6. HANDLE PRODUCT IMAGE  →  ALWAYS SAVED AS .jpg
    // ==============================================
    $imagePath = null;

    $allowedExtensions = ['jpeg', 'jpg', 'png'];
    $allowedMime       = ['image/jpeg', 'image/jpg', 'image/png'];
    $finalExt          = 'jpg';   // ✅ always .jpg now

    // ✅ Final filename = <product_name>.jpg
    $fileName = $productName . '.' . $finalExt;
    $destPath = $uploadDir . $fileName;

    $useDefaultImage = isset($_POST['use_default_image']) && $_POST['use_default_image'] === '1';

    /**
     * Convert raw image bytes to a real JPEG file at $destPath.
     * Returns true on success, false on failure.
     */
    function saveBytesAsJpeg($bytes, $destPath)
    {
        // Preferred path: GD re-encodes to true JPEG
        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $img = @imagecreatefromstring($bytes);
            if ($img !== false) {
                // Flatten transparency onto white (JPEG has no alpha)
                $w = imagesx($img);
                $h = imagesy($img);
                $canvas = imagecreatetruecolor($w, $h);
                $white  = imagecolorallocate($canvas, 255, 255, 255);
                imagefill($canvas, 0, 0, $white);
                imagecopy($canvas, $img, 0, 0, 0, 0, $w, $h);
                $ok = @imagejpeg($canvas, $destPath, 90);
                imagedestroy($canvas);
                imagedestroy($img);
                return (bool)$ok;
            }
        }
        // Fallback: no GD available — write bytes as-is
        return (file_put_contents($destPath, $bytes) !== false);
    }

    if ($useDefaultImage) {
        $imagePath = 'no-image.jpg';
    }
    // ---- Case A: standard file upload ----
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

        // If GD is available → convert & save as .jpg
        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $bytes = @file_get_contents($file['tmp_name']);
            if ($bytes === false) {
                echo json_encode(['success' => false, 'message' => 'Could not read uploaded image.']);
                exit;
            }
            if (!saveBytesAsJpeg($bytes, $destPath)) {
                echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image as JPG.']);
                exit;
            }
        } else {
            // Fallback: no GD → just move it. Rename to .jpg anyway so the DB/UI stay consistent.
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image.']);
                exit;
            }
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
        // accept jpeg/jpg/png (camera is usually jpeg)
        $okTypes = ['jpeg', 'jpg', 'png'];
        if (!in_array($type, $okTypes, true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPEG, JPG, or PNG camera images allowed.']);
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

        if (!saveBytesAsJpeg($data, $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save camera image as JPG.']);
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
    // 7. CHECK DUPLICATE PRODUCT NAME
    // ==============================================
    if ($insertAccNumber) {
        $checkStmt = $pdo->prepare("
            SELECT id FROM {$targetTable} 
            WHERE product_name = :product_name AND acc_number = :acc_number
        ");
        $checkStmt->execute([
            ':product_name' => $productName,
            ':acc_number'   => $accNumber
        ]);
    } else {
        $checkStmt = $pdo->prepare("
            SELECT id FROM {$targetTable} 
            WHERE product_name = :product_name
        ");
        $checkStmt->execute([':product_name' => $productName]);
    }

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

        if ($insertAccNumber) {
            $stmt = $pdo->prepare("
                INSERT INTO {$targetTable}
                    (product_number, product_name, unit, qty_on_hand, selling_price, description, product_image, last_restocked, acc_number, category_group)
                VALUES
                    (:product_number, :product_name, :unit, :qty_on_hand, :selling_price, :description, :product_image, :last_restocked, :acc_number, :category_group)
            ");

            $result = $stmt->execute([
                ':product_number' => $productNumber,
                ':product_name'   => $productName,
                ':unit'           => $unit,
                ':qty_on_hand'    => $quantity,
                ':selling_price'  => $sellingPrice,
                ':description'    => $description,
                ':product_image'  => $imagePath,
                ':last_restocked' => $last_restocked,
                ':acc_number'     => $accNumber,
                ':category_group' => $categoryGroupDb
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO {$targetTable}
                    (product_number, product_name, unit, qty_on_hand, selling_price, description, product_image, last_restocked, category_group)
                VALUES
                    (:product_number, :product_name, :unit, :qty_on_hand, :selling_price, :description, :product_image, :last_restocked, :category_group)
            ");

            $result = $stmt->execute([
                ':product_number' => $productNumber,
                ':product_name'   => $productName,
                ':unit'           => $unit,
                ':qty_on_hand'    => $quantity,
                ':selling_price'  => $sellingPrice,
                ':description'    => $description,
                ':product_image'  => $imagePath,
                ':last_restocked' => $last_restocked,
                ':category_group' => $categoryGroupDb
            ]);
        }

        if ($result) {
            $productId = $pdo->lastInsertId();

            $logDetails = "Added new product to {$targetTable}: {$productName} | Product #: {$productNumber} | Unit: {$unit} | Quantity: {$quantity} | Price: ₱{$sellingPrice} | Category: " . ($categoryGroupDb ?: 'N/A') . " | Image: " . ($imagePath ?: 'None') . " | Description: " . ($description ?: 'N/A');

            if ($insertAccNumber) {
                $logDetails .= " | Investor: {$accNumber}";
            }

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
                'category_group' => $categoryGroupDb,
                'table'          => $targetTable,
                'role'           => $userRole,
                'acc_number'     => $insertAccNumber ? $accNumber : null,
                'folder'         => $uploadFolder,
                'upload_dir'     => $uploadDir,
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
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($imagePath && $imagePath !== 'no-image.jpg' && file_exists($uploadDir . $imagePath)) {
            unlink($uploadDir . $imagePath);
        }
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);