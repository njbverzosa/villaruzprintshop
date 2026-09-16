<?php
// API/delete_product.php
// Handles product deletion (admin only) — removes DB row + image file.

session_start();
require_once __DIR__ . '/../DB_Conn/config.php';

// ==============================================
// 1. CHECK LOGIN
// ==============================================
function isLoggedIn()
{
    return isset($_SESSION['user_role']) &&
        isset($_SESSION['user_id']) &&
        isset($_SESSION['acc_number']);
}

if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Please login first to access this page.';
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 2. VERIFY ROLE (Admin only)
// ==============================================
$userRole = $_SESSION['user_role'];
$userId   = $_SESSION['user_id'];

if ($userRole !== 'Admin') {
    $_SESSION['error'] = 'You do not have permission to delete products.';
    header('Location: ../web/all_products.php');
    exit;
}

// ==============================================
// 3. VERIFY ADMIN EXISTS & GET AUTHORIZE_ACCESS
// ==============================================
$stmt = $pdo->prepare("SELECT id, acc_number, f_name, authorize_access FROM admins WHERE id = ?");
$stmt->execute([$userId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ==============================================
// 4. READ product_number FROM URL
// ==============================================
$productNumber = isset($_GET['product_number']) ? trim($_GET['product_number']) : '';

if ($productNumber === '') {
    $_SESSION['error'] = 'No product specified.';
    header('Location: ../web/all_products.php');
    exit;
}

// ==============================================
// 5. FETCH THE PRODUCT (so we can delete its image)
// ==============================================
$stmt = $pdo->prepare("SELECT id, product_number, product_name, product_image FROM merchandise_inventory WHERE product_number = ? LIMIT 1");
$stmt->execute([$productNumber]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['error'] = 'Product not found.';
    header('Location: ../web/all_products.php');
    exit;
}

// ==============================================
// 6. DELETE THE IMAGE FILE FROM DISK
// ==============================================
$imageFolder = dirname(__DIR__) . '/Products/';
$imageDeleted = false;

if (!empty($product['product_image'])) {
    // ✅ basename() strips any directory components from the DB value
    $safeName  = basename($product['product_image']);
    $imagePath = $imageFolder . $safeName;

    if (file_exists($imagePath) && is_file($imagePath)) {
        if (@unlink($imagePath)) {
            $imageDeleted = true;
        }
    }
}

// ==============================================
// 7. DELETE THE DB ROW
// ==============================================
try {
    $pdo->beginTransaction();

    $deleteStmt = $pdo->prepare("DELETE FROM merchandise_inventory WHERE product_number = ? LIMIT 1");
    $deleteStmt->execute([$productNumber]);

    $pdo->commit();

    $_SESSION['success'] = "Product \"{$product['product_name']}\" deleted successfully.";
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Delete product error: ' . $e->getMessage());
    $_SESSION['error'] = 'Failed to delete the product. Please try again.';
}

// ==============================================
// 8. REDIRECT BACK
// ==============================================
header('Location: ../web/all_products.php');
exit;