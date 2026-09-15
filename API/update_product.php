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

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

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
// 3. DECIDE TARGET TABLE + UPLOAD FOLDER
// ==============================================
$allowedInv = [0, 1, 2];

if ($authorizeAccess === 3) {
    $targetTable  = 'investors_product';
    $uploadFolder = 'Inv_Products';
} elseif (in_array($authorizeAccess, $allowedInv, true)) {
    $targetTable  = 'merchandise_inventory';
    $uploadFolder = 'Products';
} else {
    echo json_encode(['success' => false, 'message' => 'Your account is not allowed to update products.']);
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

// ==============================================
// 5. HANDLE UPDATE PRODUCT
// ==============================================
if ($action === 'update_product') {

    if (!isset($_POST['product_id']) || !isset($_POST['product_name']) || !isset($_POST['selling_price'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $productId     = intval($_POST['product_id']);
    $productName   = trim($_POST['product_name']);
    $unit          = trim($_POST['unit'] ?? 'Pcs');
    $quantity      = intval($_POST['quantity'] ?? 0);
    $sellingPrice  = floatval($_POST['selling_price'] ?? 0);
    $description   = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Flag from frontend when the user clicks Retake
    $replaceImage  = isset($_POST['replace_image']) && $_POST['replace_image'] === '1';

    // ==============================================
    // 5a. SANITIZE + VALIDATE PRODUCT NAME
    // ==============================================
    $productName = sanitizeProductName($productName);

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
    //    Allowed extensions: jpeg, jpg, png
    // ==============================================
    $imagePath        = null;
    $oldImageToDelete = null;
    $newFileWritten   = null;

    // Allowed extensions + MIME types
    $allowedExtensions = ['jpeg', 'jpg', 'png'];
    $allowedMime       = ['image/jpeg', 'image/jpg', 'image/png'];

    // Helper: build a safe filename from the product name
    $buildFileName = function (string $name, string $ext) {
        // 1. Remove extension if present
        $name = pathinfo($name, PATHINFO_FILENAME);

        // 2. Replace spaces, underscores, hyphens with hyphens
        $name = preg_replace('/[\s_\-]+/', '-', $name);

        // 3. Keep letters, numbers, dashes, and dots (for "2.0", "1.5L", etc.)
        $name = preg_replace('/[^A-Za-z0-9\-\.]/', '', $name);

        // 4. Collapse multiple dashes and dots
        $name = preg_replace('/-+/', '-', $name);
        $name = preg_replace('/\.+/', '.', $name);

        // 5. Trim leading/trailing dashes and dots
        $name = trim($name, '-.');

        // 6. Fallback if empty
        if ($name === '') {
            $name = 'product-' . time();
        }

        // 7. Limit length
        $name = substr($name, 0, 100);

        return $name . '.' . strtolower($ext);
    };

    // ---- Case A: base64 camera image ----
    if (!empty($_POST['product_image_base64'])) {
        $dataUri = $_POST['product_image_base64'];

        if (!preg_match('/^data:image\/(\w+);base64,/', $dataUri, $m)) {
            echo json_encode(['success' => false, 'message' => 'Invalid camera image format.']);
            exit;
        }

        $type = strtolower($m[1]);   // jpeg, jpg, png
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

        $ext      = ($type === 'jpeg') ? 'jpg' : $type;
        $fileName = $buildFileName($productName, $ext);
        $destPath = $uploadDir . $fileName;

        // DELETE the OLD image file FIRST if the target name matches it
        if (!empty($oldProduct['product_image'])) {
            $oldFilePath = $uploadDir . $oldProduct['product_image'];
            if (file_exists($oldFilePath)) {
                @unlink($oldFilePath);
            }
            $oldImageToDelete = $oldProduct['product_image'];
        }

        // Check if the target name still conflicts (with other products)
        $counter = 1;
        while (file_exists($destPath)) {
            $fileName = $buildFileName($productName . '-' . $counter, $ext);
            $destPath = $uploadDir . $fileName;
            $counter++;
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

        // ✅ Check extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPEG, JPG, or PNG images allowed.']);
            exit;
        }

        // ✅ Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMime, true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPEG, JPG, or PNG images allowed.']);
            exit;
        }

        $fileName = $buildFileName($productName, $ext);
        $destPath = $uploadDir . $fileName;

        // DELETE old image FIRST
        if (!empty($oldProduct['product_image'])) {
            $oldFilePath = $uploadDir . $oldProduct['product_image'];
            if (file_exists($oldFilePath)) {
                @unlink($oldFilePath);
            }
            $oldImageToDelete = $oldProduct['product_image'];
        }

        $counter = 1;
        while (file_exists($destPath)) {
            $fileName = $buildFileName($productName . '-' . $counter, $ext);
            $destPath = $uploadDir . $fileName;
            $counter++;
        }

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image.']);
            exit;
        }

        $imagePath      = $fileName;
        $newFileWritten = $fileName;
    }
    // ---- Case C: user clicked Retake but didn't capture ----
    elseif ($replaceImage) {
        $imagePath = null;

        // Delete the old image from disk right now
        if (!empty($oldProduct['product_image'])) {
            $oldFilePath = $uploadDir . $oldProduct['product_image'];
            if (file_exists($oldFilePath)) {
                @unlink($oldFilePath);
            }
            $oldImageToDelete = $oldProduct['product_image'];
        }
    }
    // ---- Case D: no image change → keep existing ----
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
            // Log changes
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

            echo json_encode([
                'success'       => true,
                'message'       => 'Product updated successfully',
                'product_id'    => $productId,
                'product_image' => $imagePath,
                'table'         => $targetTable,
                'folder'        => $uploadFolder,
                'upload_dir'    => $uploadDir,
                'last_updated'  => $formattedDate
            ]);
        } else {
            $pdo->rollBack();

            if ($newFileWritten && file_exists($uploadDir . $newFileWritten)) {
                @unlink($uploadDir . $newFileWritten);
            }

            echo json_encode(['success' => false, 'message' => 'Failed to update product']);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();

        if ($newFileWritten && file_exists($uploadDir . $newFileWritten)) {
            @unlink($uploadDir . $newFileWritten);
        }

        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Invalid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);

// ==============================================
// HELPER: Sanitize product name
// Allows: letters, numbers, spaces, and , . ( ) -
// Strips: image extensions (.jpeg, .jpg, .png)
// Preserves user's casing (does NOT lowercase/ucwords)
// ==============================================
function sanitizeProductName($name)
{
    // 1. Strip the file extension
    $name = pathinfo($name, PATHINFO_FILENAME);

    // 2. Strip any lingering image extensions (defense in depth)
    $imageExtensions = ['jpeg', 'jpg', 'png'];
    foreach ($imageExtensions as $ext) {
        $name = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '', $name);
    }

    // 3. Collapse underscores and whitespace into single spaces
    $name = preg_replace('/[_\s]+/', ' ', $name);

    // 4. Keep ONLY: letters, numbers, spaces, and , . ( ) -
    $name = preg_replace('/[^A-Za-z0-9\s,\.\(\)\-]/', '', $name);

    // 5. Trim whitespace and stray dots from both ends
    $name = trim($name, " \t\n\r\0\x0B.");

    // 6. Collapse multiple spaces
    $name = preg_replace('/\s+/', ' ', $name);

    // 7. ✅ PRESERVE user's casing — no ucwords, no strtolower

    return $name;
}