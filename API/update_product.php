<?php
// API/update_product.php
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

$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// ==============================================
// 2. FETCH USER NAME
// ==============================================
$userName = 'Unknown User';

if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT f_name FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userName = $user['f_name'];
    }
}

$firstName = explode(' ', trim($userName))[0] ?? 'User';

// ==============================================
// 3. TARGET TABLE + UPLOAD FOLDER
// ==============================================
$targetTable  = 'merchandise_inventory';
$uploadFolder = 'Products';

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

// ==============================================
// 5. HANDLE UPDATE PRODUCT
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
    // 5a. SANITIZE + VALIDATE PRODUCT NAME
    // ==============================================
    // Strip any image extension that may have been typed in
    $productName = preg_replace('/\.(jpeg|jpg|png|webp|gif|bmp|heic|heif|avif|tiff|tif|svg)$/i', '', $productName);

    // Collapse multiple spaces
    $productName = preg_replace('/\s+/', ' ', $productName);

    // Trim
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
    // 5b. FETCH OLD PRODUCT
    // ==============================================
    $oldStmt = $pdo->prepare("SELECT * FROM {$targetTable} WHERE id = :id");
    $oldStmt->execute([':id' => $productId]);
    $oldProduct = $oldStmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldProduct) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    // ==============================================
    // 5c. MORE VALIDATION
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
    // 5d. RESOLVE THE UPLOAD DIRECTORY
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
    // 5e. HANDLE NEW IMAGE
    //    - New image is saved as: <product_name>.<ext>
    //    - The OLD file is deleted from disk AFTER a successful DB update.
    //    - If no new image is sent, image is left completely untouched.
    // ==============================================
    $imagePath        = $oldProduct['product_image'];  // default: keep existing
    $newFileWritten   = null;   // path of the newly written file (for rollback)
    $oldImageToDelete = null;   // old file to delete after commit

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

        // Normalize a few aliases
        if ($type === 'jpeg')     $type = 'jpg';
        if ($type === 'svg+xml')  $type = 'svg';
        if ($type === 'x-ms-bmp') $type = 'bmp';

        // Block SVG by default (can carry scripts)
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

        // ✅ Clean filename: <product_name>.<ext> — always overwrites
        $ext       = $type;
        $fileName  = $productName . '.' . $ext;
        $destPath  = $uploadDir . $fileName;

        // ✅ Delete OLD file if it has a different name
        if (!empty($oldProduct['product_image']) && $oldProduct['product_image'] !== $fileName) {
            $oldImageToDelete = $oldProduct['product_image'];
        }

        // If the new file has the SAME name as the old one, we overwrite it —
        // no need to mark it for deletion.
        if ($oldImageToDelete === null && !empty($oldProduct['product_image']) && $oldProduct['product_image'] === $fileName) {
            // Same name → overwrite in place (no separate delete needed)
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

        // ✅ Clean filename: <product_name>.<ext> — always overwrites
        $ext      = $sourceExt;
        $fileName = $productName . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        // ✅ Mark OLD file for deletion if it has a different name
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
    // ---- Case C: Retake clicked but no new capture ----
    //    → user explicitly wants to REMOVE the image
    elseif ($replaceImage) {
        $imagePath        = null;
        $oldImageToDelete = !empty($oldProduct['product_image']) ? $oldProduct['product_image'] : null;
    }
    // ---- Case D: no image change → keep existing, do nothing ----
    else {
        $imagePath = $oldProduct['product_image'];
    }

    // ==============================================
    // 5f. PERFORM THE UPDATE
    // ==============================================
    try {
        date_default_timezone_set('Asia/Manila');
        $formattedDate = date('j F Y g:i A');

        $pdo->beginTransaction();

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

            try {
                $logStmt = $pdo->prepare("INSERT INTO logs (name, action, details, created_at) VALUES (?, ?, ?, ?)");
                $logStmt->execute([$firstName, "Updated Product", $logDetails, $formattedDate]);
            } catch (PDOException $logError) {
                error_log("Log insertion failed: " . $logError->getMessage());
            }

            $pdo->commit();

            // ==============================================
            // ✅ POST-COMMIT: delete the OLD image file
            //    (only if a new one was written or the image was cleared)
            // ==============================================
            if ($oldImageToDelete !== null) {
                $oldPath = $uploadDir . $oldImageToDelete;
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $redirectUrl = '../web/all_products.php';

            echo json_encode([
                'success'       => true,
                'message'       => 'Product updated successfully',
                'product_id'    => $productId,
                'product_image' => $imagePath,
                'table'         => $targetTable,
                'folder'        => $uploadFolder,
                'upload_dir'    => $uploadDir,
                'last_updated'  => $formattedDate,
                'redirect'      => $redirectUrl
            ]);
        } else {
            $pdo->rollBack();

            // ✅ DB failed → remove the NEW file if it has a different name than the old one.
            //    If the names are identical, the file was overwritten in place, and we can't restore it.
            if ($newFileWritten && $newFileWritten !== $oldProduct['product_image']) {
                if (file_exists($uploadDir . $newFileWritten)) {
                    @unlink($uploadDir . $newFileWritten);
                }
            }

            echo json_encode(['success' => false, 'message' => 'Failed to update product']);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();

        // ✅ Only remove the NEW file if it differs from the old one.
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