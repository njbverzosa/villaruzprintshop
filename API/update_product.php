<?php
// API/update_product.php

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
// 1. CHECK LOGIN
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
$userName      = 'Unknown User';
$targetTable   = '';
$redirectUrl   = '';
$uploadFolder  = '';
$scopeByAccNum = false;

if ($userRole === 'Admin') {
    // ✅ Admin → merchandise_inventory + Products folder
    $stmt = $pdo->prepare("SELECT f_name FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userName = $user['f_name'];
    }
    $targetTable   = 'merchandise_inventory';
    $uploadFolder  = 'Products';
    $redirectUrl   = '../web/all_products.php';
    $scopeByAccNum = false;

} elseif ($userRole === 'Investor') {
    // ✅ Investor → investors_inventory + Inv_Products folder
    $stmt = $pdo->prepare("SELECT f_name, acc_number FROM investors WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userName  = $user['f_name'];
        // ✅ Refresh acc_number directly from DB
        $accNumber = $user['acc_number'];
    }
    $targetTable   = 'investors_inventory';
    $uploadFolder  = 'Inv_Products';
    $redirectUrl   = '../investors/investors_product.php';
    $scopeByAccNum = true;

} else {
    // ❌ Any other role is not allowed to update products
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

// ==============================================
// 4. HANDLE UPDATE PRODUCT
// ==============================================
if ($action === 'update_product') {

    if (!isset($_POST['product_id']) || !isset($_POST['product_name']) || !isset($_POST['selling_price'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $productId    = intval($_POST['product_id']);
    $productName  = trim($_POST['product_name']);
    $unit         = trim($_POST['unit'] ?? 'Pcs');
    $quantity     = intval($_POST['quantity'] ?? 0);
    $sellingPrice = floatval($_POST['selling_price'] ?? 0);
    $description  = isset($_POST['description']) ? trim($_POST['description']) : '';

    $replaceImage = isset($_POST['replace_image']) && $_POST['replace_image'] === '1';

    // ==============================================
    // 4a. SANITIZE + VALIDATE PRODUCT NAME
    // ==============================================
    $productName = preg_replace('/\.(jpeg|jpg|png|webp|gif|bmp|heic|heif|avif|tiff|tif|svg)$/i', '', $productName);
    $productName = preg_replace('/\s+/', ' ', $productName);
    $productName = trim($productName);

    if (empty($productName)) {
        echo json_encode(['success' => false, 'message' => 'Product name is required']);
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
    // 4b. FETCH OLD PRODUCT (scoped by acc_number for Investor)
    // ==============================================
    if ($scopeByAccNum) {
        $oldStmt = $pdo->prepare("
            SELECT * FROM {$targetTable} 
            WHERE id = :id AND acc_number = :acc_number
        ");
        $oldStmt->execute([
            ':id'         => $productId,
            ':acc_number' => $accNumber
        ]);
    } else {
        $oldStmt = $pdo->prepare("SELECT * FROM {$targetTable} WHERE id = :id");
        $oldStmt->execute([':id' => $productId]);
    }

    $oldProduct = $oldStmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldProduct) {
        $msg = $scopeByAccNum ? 'Product not found or does not belong to you' : 'Product not found';
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    // ==============================================
    // 4c. MORE VALIDATION
    // ==============================================
    if ($sellingPrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'Selling price must be greater than 0']);
        exit;
    }
    if ($quantity < 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity cannot be negative']);
        exit;
    }

    // ==============================================
    // 4d. RESOLVE THE UPLOAD DIRECTORY
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
    // 4e. HANDLE NEW IMAGE
    //     ✅ Filename = <product_name>.<ext>   ← NO acc_number prefix
    // ==============================================
    $imagePath        = $oldProduct['product_image'];
    $newFileWritten   = null;
    $oldImageToDelete = null;

    $allowedSourceExt  = ['jpeg', 'jpg', 'png', 'webp', 'gif', 'bmp', 'heic', 'heif', 'avif', 'tiff', 'tif', 'svg'];
    $allowedSourceMime = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/webp',
        'image/gif', 'image/bmp', 'image/x-ms-bmp',
        'image/heic', 'image/heif', 'image/avif',
        'image/tiff', 'image/svg+xml'
    ];

    // ---- Case A: base64 camera image ----
    if (!empty($_POST['product_image_base64'])) {

        $dataUri = $_POST['product_image_base64'];

        if (!preg_match('/^data:image\/([\w\+\-\.]+);base64,/', $dataUri, $m)) {
            echo json_encode(['success' => false, 'message' => 'Invalid camera image format.']);
            exit;
        }

        $type = strtolower($m[1]);

        if ($type === 'jpeg')     $type = 'jpg';
        if ($type === 'svg+xml')  $type = 'svg';
        if ($type === 'x-ms-bmp') $type = 'bmp';

        if ($type === 'svg') {
            echo json_encode(['success' => false, 'message' => 'SVG images are not allowed.']);
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

        // ✅ Filename = just the product name
        $ext      = $type;
        $fileName = $productName . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        if (!empty($oldProduct['product_image']) && $oldProduct['product_image'] !== $fileName) {
            $oldImageToDelete = $oldProduct['product_image'];
        }

        if (file_put_contents($destPath, $data) === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to save camera image.']);
            exit;
        }

        $imagePath      = $fileName;
        $newFileWritten = $fileName;
    }
    // ---- Case B: standard file upload ----
    elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {

        $file = $_FILES['product_image'];

        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Image too large. Max 5MB.']);
            exit;
        }

        $sourceExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($sourceExt, $allowedSourceExt, true)) {
            echo json_encode([
                'success' => false,
                'message' => 'Unsupported image type. Allowed: ' . implode(', ', $allowedSourceExt)
            ]);
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedSourceMime, true)) {
            echo json_encode([
                'success' => false,
                'message' => 'Unsupported image MIME type: ' . $mime
            ]);
            exit;
        }

        // ✅ Filename = just the product name
        $ext      = $sourceExt;
        $fileName = $productName . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        if (!empty($oldProduct['product_image']) && $oldProduct['product_image'] !== $fileName) {
            $oldImageToDelete = $oldProduct['product_image'];
        }

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image.']);
            exit;
        }

        $imagePath      = $fileName;
        $newFileWritten = $fileName;
    }
    // ---- Case C: use default image (no-image.jpg) ----
    elseif (isset($_POST['use_default_image']) && $_POST['use_default_image'] === '1') {
        $imagePath = 'no-image.jpg';

        if (!empty($oldProduct['product_image']) && $oldProduct['product_image'] !== 'no-image.jpg') {
            $oldImageToDelete = $oldProduct['product_image'];
        }
    }
    // ---- Case D: Retake clicked but no new capture → remove image ----
    elseif ($replaceImage) {
        $imagePath        = null;
        $oldImageToDelete = !empty($oldProduct['product_image']) ? $oldProduct['product_image'] : null;
    }
    // ---- Case E: no image change → keep existing ----
    else {
        $imagePath = $oldProduct['product_image'];
    }

    // ==============================================
    // 4f. PERFORM THE UPDATE
    // ==============================================
    try {
        date_default_timezone_set('Asia/Manila');
        $formattedDate = date('j F Y g:i A');

        $pdo->beginTransaction();

        if ($scopeByAccNum) {
            // ✅ Investor: WHERE id = ? AND acc_number = ?
            $stmt = $pdo->prepare("
                UPDATE {$targetTable}
                SET product_name   = :product_name,
                    unit           = :unit,
                    qty_on_hand    = :qty_on_hand,
                    selling_price  = :selling_price,
                    description    = :description,
                    product_image  = :product_image,
                    last_restocked = :last_restocked
                WHERE id = :id AND acc_number = :acc_number
            ");

            $result = $stmt->execute([
                ':product_name'   => $productName,
                ':unit'           => $unit,
                ':qty_on_hand'    => $quantity,
                ':selling_price'  => $sellingPrice,
                ':description'    => $description,
                ':product_image'  => $imagePath,
                ':last_restocked' => $formattedDate,
                ':id'             => $productId,
                ':acc_number'     => $accNumber
            ]);
        } else {
            // ✅ Admin: original UPDATE
            $stmt = $pdo->prepare("
                UPDATE {$targetTable}
                SET product_name   = :product_name,
                    unit           = :unit,
                    qty_on_hand    = :qty_on_hand,
                    selling_price  = :selling_price,
                    description    = :description,
                    product_image  = :product_image,
                    last_restocked = :last_restocked
                WHERE id = :id
            ");

            $result = $stmt->execute([
                ':product_name'   => $productName,
                ':unit'           => $unit,
                ':qty_on_hand'    => $quantity,
                ':selling_price'  => $sellingPrice,
                ':description'    => $description,
                ':product_image'  => $imagePath,
                ':last_restocked' => $formattedDate,
                ':id'             => $productId
            ]);
        }

        // ✅ Double-check: if scoped, ensure a row was actually affected
        if ($result && $scopeByAccNum && $stmt->rowCount() === 0) {
            $pdo->rollBack();
            if ($newFileWritten && $newFileWritten !== $oldProduct['product_image']) {
                if (file_exists($uploadDir . $newFileWritten)) {
                    @unlink($uploadDir . $newFileWritten);
                }
            }
            echo json_encode(['success' => false, 'message' => 'Update failed: product does not belong to you']);
            exit;
        }

        if ($result) {
            // ---- Build change log ----
            $changes = [];
            if ($oldProduct['product_name'] != $productName)   $changes[] = "Name: '{$oldProduct['product_name']}' → '{$productName}'";
            if ($oldProduct['unit'] != $unit)                  $changes[] = "Unit: '{$oldProduct['unit']}' → '{$unit}'";
            if ($oldProduct['qty_on_hand'] != $quantity)       $changes[] = "Quantity: {$oldProduct['qty_on_hand']} → {$quantity}";
            if ($oldProduct['selling_price'] != $sellingPrice) $changes[] = "Price: ₱{$oldProduct['selling_price']} → ₱{$sellingPrice}";
            if ($imagePath !== $oldProduct['product_image'])   $changes[] = "Image updated";
            if ($imagePath === null && !empty($oldProduct['product_image'])) $changes[] = "Image removed (no replacement)";

            $logDetails = "Updated product in {$targetTable}: {$oldProduct['product_name']} (ID: {$productId}) | Changes: " . (empty($changes) ? "No changes" : implode(", ", $changes));

            // ✅ Include acc_number in log for Investor
            if ($scopeByAccNum) {
                $logDetails .= " | Investor: {$accNumber}";
            }

            try {
                $logStmt = $pdo->prepare("INSERT INTO logs (name, action, details, created_at) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$firstName, "Updated Product", $logDetails, $formattedDate]);
            } catch (PDOException $logError) {
                error_log("Log insertion failed: " . $logError->getMessage());
            }

            $pdo->commit();

            // ✅ POST-COMMIT: delete the OLD image file
            if ($oldImageToDelete !== null && $oldImageToDelete !== 'no-image.jpg') {
                $oldPath = $uploadDir . $oldImageToDelete;
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            echo json_encode([
                'success'       => true,
                'message'       => 'Product updated successfully',
                'product_id'    => $productId,
                'product_image' => $imagePath,
                'table'         => $targetTable,
                'role'          => $userRole,
                'acc_number'    => $scopeByAccNum ? $accNumber : null,
                'folder'        => $uploadFolder,
                'upload_dir'    => $uploadDir,
                'last_updated'  => $formattedDate,
                'redirect'      => $redirectUrl
            ]);
        } else {
            $pdo->rollBack();

            if ($newFileWritten && $newFileWritten !== $oldProduct['product_image']) {
                if (file_exists($uploadDir . $newFileWritten)) {
                    @unlink($uploadDir . $newFileWritten);
                }
            }

            echo json_encode(['success' => false, 'message' => 'Failed to update product']);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();

        if ($newFileWritten && $newFileWritten !== $oldProduct['product_image']) {
            if (file_exists($uploadDir . $newFileWritten)) {
                @unlink($uploadDir . $newFileWritten);
            }
        }

        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);