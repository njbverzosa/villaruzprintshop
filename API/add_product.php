<?php
// API/add_product.php
error_reporting(E_ALL);
ini_set('display_errors', 0);   // ✅ never leak warnings into the JSON response

// ✅ Force session cookie path to be shared across the whole domain
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
// 2. FETCH USER NAME + SET TARGET TABLE + UPLOAD FOLDER (based on role)
// ==============================================
$userName        = 'Unknown User';
$targetTable     = '';
$redirectUrl     = '';
$uploadFolder    = '';     // ✅ now role-based
$insertAccNumber = false;  // ✅ only insert acc_number for Investor

if ($userRole === 'Admin') {
    // ✅ Admin → merchandise_inventory + Products folder
    $stmt = $pdo->prepare("SELECT f_name FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userName = $user['f_name'];
    }
    $targetTable     = 'merchandise_inventory';
    $uploadFolder    = 'Products';                       // ✅ Admin folder
    $redirectUrl     = '../web/all_products.php';
    $insertAccNumber = false;

} elseif ($userRole === 'Investor') {
    // ✅ Investor → investors_inventory + Inv_Products folder
    $stmt = $pdo->prepare("SELECT f_name, acc_number FROM investors WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userName = $user['f_name'];
        // ✅ Always use the investor's acc_number from the DB (not from session)
        $accNumber = $user['acc_number'];
    }
    $targetTable     = 'investors_inventory';
    $uploadFolder    = 'Inv_Products';                   // ✅ Investor folder
    $redirectUrl     = '../investors/investors_product.php';
    $insertAccNumber = true;

} else {
    // ❌ Any other role is not allowed to add products
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
    // 4. READ + SANITIZE PRODUCT NAME (from FORM)
    // ==============================================
    $rawName     = $_POST['product_name'] ?? '';
    $productName = trim($rawName);

    // Strip any image extension that may have been typed in
    $productName = preg_replace('/\.(jpeg|jpg|png)$/i', '', $productName);

    // Collapse multiple spaces into a single space
    $productName = preg_replace('/\s+/', ' ', $productName);

    // Trim
    $productName = trim($productName);

    if (empty($productName)) {
        echo json_encode(['success' => false, 'message' => 'Product name is required.']);
        exit;
    }

    // Enforce allowed characters: letters, numbers, spaces, and , . ( ) -
    if (!preg_match('/^[A-Za-z0-9\s,\.\(\)\-]+$/', $productName)) {
        echo json_encode([
            'success' => false,
            'message' => 'Product name can only contain letters, numbers, spaces, and , . ( ) -'
        ]);
        exit;
    }

    // ==============================================
    // 5. RESOLVE THE UPLOAD DIRECTORY (role-based)
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
    // 6. HANDLE PRODUCT IMAGE
    //    ✅ Filename ALWAYS ends in .png
    // ==============================================
    $imagePath = null;

    $allowedExtensions = ['jpeg', 'jpg', 'png'];
    $allowedMime       = ['image/jpeg', 'image/jpg', 'image/png'];
    $finalExt          = 'png';

    // ✅ Case: use default image
    $useDefaultImage = isset($_POST['use_default_image']) && $_POST['use_default_image'] === '1';

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

        $fileName = $productName . '.' . $finalExt;
        $destPath = $uploadDir . $fileName;

        $counter = 1;
        while (file_exists($destPath)) {
            $fileName = $productName . ' (' . $counter . ').' . $finalExt;
            $destPath = $uploadDir . $fileName;
            $counter++;
        }

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
        if (!in_array($type, $allowedExtensions, true)) {
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

        $fileName = $productName . '.' . $finalExt;
        $destPath = $uploadDir . $fileName;

        $counter = 1;
        while (file_exists($destPath)) {
            $fileName = $productName . ' (' . $counter . ').' . $finalExt;
            $destPath = $uploadDir . $fileName;
            $counter++;
        }

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
    // 7. CHECK DUPLICATE PRODUCT NAME
    //    ✅ Scoped per investor (or global for admin)
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
        // 9. INSERT (with acc_number for Investor)
        // ==============================================
        $pdo->beginTransaction();

        if ($insertAccNumber) {
            // ✅ Investor: include acc_number
            $stmt = $pdo->prepare("
                INSERT INTO {$targetTable}
                    (product_number, product_name, unit, qty_on_hand, selling_price, description, product_image, last_restocked, acc_number)
                VALUES
                    (:product_number, :product_name, :unit, :qty_on_hand, :selling_price, :description, :product_image, :last_restocked, :acc_number)
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
                ':acc_number'     => $accNumber
            ]);
        } else {
            // ✅ Admin: original insert (no acc_number)
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
        }

        if ($result) {
            $productId = $pdo->lastInsertId();

            $logDetails = "Added new product to {$targetTable}: {$productName} | Product #: {$productNumber} | Unit: {$unit} | Quantity: {$quantity} | Price: ₱{$sellingPrice} | Image: " . ($imagePath ?: 'None') . " | Description: " . ($description ?: 'N/A');

            // ✅ Include acc_number in log for Investor
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
                'table'          => $targetTable,
                'role'           => $userRole,
                'acc_number'     => $insertAccNumber ? $accNumber : null,
                'folder'         => $uploadFolder,        // ✅ role-based folder in response
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