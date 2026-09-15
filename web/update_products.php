<?php
//web/update_products.php
session_start();

require_once __DIR__ . '/../DB_Conn/config.php';

if (isset($userData['f_name']) && !isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = $userData['f_name'];
}

function isLoggedIn()
{
    return isset($_SESSION['user_role']) &&
        isset($_SESSION['user_id']) &&
        isset($_SESSION['acc_number']);
}

if (!isLoggedIn()) {
    $_SESSION['login_error'] = 'Please login first to access the shop.';
    header('Location: ../login.php');
    exit;
}

$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$accNumber = $_SESSION['acc_number'];

$userData = null;
if ($userRole === 'Admin') {
    $stmt = $pdo->prepare("SELECT id, acc_number, f_name, email, phone_number, role, user_name, authorize_access FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$user = $userData;

date_default_timezone_set('Asia/Manila');
$timezone = new DateTimeZone('Asia/Manila');

// ==============================================
// FETCH PRODUCT BY product_number (from URL)
// ==============================================
$productNumber = $_GET['product_number'] ?? '';

if (empty($productNumber)) {
    header('Location: all_products.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM merchandise_inventory WHERE product_number = ? LIMIT 1");
$stmt->execute([$productNumber]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: all_products.php');
    exit;
}

// ==============================================
// BUILD THE IMAGE URL + RESOLVE THE IMAGE PATH
// ==============================================
$imageUrl   = '';
$imageExists = false;

if (!empty($product['product_image'])) {
    // Web URL (for <img src="">)
    $imageUrl = '../Products/' . htmlspecialchars($product['product_image']);

    // Absolute filesystem path (for deletion check)
    $absoluteImagePath = dirname(__DIR__) . '/Products/' . $product['product_image'];
    $imageExists = file_exists($absoluteImagePath);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Product — <?php echo htmlspecialchars($product['product_name']); ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            margin-bottom: 5px;
            color: #333;
        }

        .product-number {
            font-size: 13px;
            color: #777;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }

        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        textarea {
            resize: vertical;
            min-height: 70px;
        }

        /* ✅ Centered product image display */
        .product-image-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            margin-bottom: 12px;
        }

        .product-image-wrapper img {
            max-width: 260px;
            width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            object-fit: cover;
            display: block;
        }

        /* ✅ Square camera box (only shown when capturing a new photo) */
        .camera-box {
            position: relative;
            width: 100%;
            max-width: 260px;
            margin: 0 auto 12px auto;
            aspect-ratio: 1 / 1;
            background: #000;
            border-radius: 10px;
            overflow: hidden;
            display: none;
        }

        .camera-box.visible {
            display: block;
        }

        .camera-box video,
        .camera-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        #capturedPhoto {
            display: none;
        }

        button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            color: white;
        }

        /* ✅ Retake button — sits right below the image */
        .btn-retake {
            background: #f59e0b;
            width: 100%;
            padding: 12px;
            font-size: 15px;
            margin-bottom: 20px;
            display: block;
        }

        .btn-retake:hover {
            background: #d97706;
        }

        .btn-capture {
            background: #28a745;
            width: 100%;
            padding: 12px;
            font-size: 15px;
            margin-bottom: 20px;
            display: none;
        }

        .btn-capture.visible {
            display: block;
        }

        .btn-submit {
            background: #333;
            width: 100%;
            padding: 12px;
            font-size: 16px;
            margin-top: 10px;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #475569;
            width: 100%;
            padding: 12px;
            font-size: 16px;
            margin-top: 8px;
            text-decoration: none;
            display: block;
            text-align: center;
            border-radius: 5px;
        }

        button:hover {
            opacity: 0.9;
        }

        .btn-cancel:hover {
            background: #cbd5e1;
        }

        .divider {
            border-top: 1px solid #eee;
            margin: 10px 0 20px 0;
        }

        .capture-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }

        .capture-actions button {
            flex: 1;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Update Product</h2>
        <div class="product-number">Product #: <?php echo htmlspecialchars($product['product_number']); ?></div>

        <form id="productForm" enctype="multipart/form-data">
            <!-- Hidden fields for the backend -->
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
            <input type="hidden" name="product_number"
                value="<?php echo htmlspecialchars($product['product_number']); ?>">
            <input type="hidden" name="csrf_token" id="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
            <input type="hidden" name="product_image_base64" id="product_image_base64" value="">

            <!-- ✅ Flag: tells the API that the user actively chose to replace the image -->
            <input type="hidden" name="replace_image" id="replace_image" value="0">

            <!-- ✅ Centered existing image -->
            <div class="product-image-wrapper" id="imageWrapper">
                <img src="<?php echo $imageUrl; ?>"
                     alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                     id="productImage"
                     onclick="openImageModal('<?php echo $imageUrl; ?>', '<?php echo htmlspecialchars($product['product_name']); ?>')"
                     style="cursor: pointer; <?php echo $imageUrl ? '' : 'display:none;'; ?>">
            </div>

            <!-- ✅ Square camera box (shown only when retaking) -->
            <div class="camera-box" id="cameraBox">
                <video id="camera" autoplay playsinline muted></video>
                <img id="capturedPhoto" alt="Captured product">
            </div>

            <!-- ✅ Retake button — always visible when an image exists -->
            <button type="button" class="btn-retake" id="retakeBtn" style="<?php echo $imageUrl ? '' : 'display:none;'; ?>">
                Retake Photo
            </button>

            <!-- ✅ Capture button — shown only when camera is active -->
            <button type="button" class="btn-capture" id="captureBtn">
                Capture Product
            </button>

            <div class="divider"></div>

            <label for="product_name">Product Name</label>
            <input type="text" id="product_name" name="product_name"
                value="<?php echo htmlspecialchars($product['product_name']); ?>" required>

            <label for="unit">Unit</label>
            <input type="text" id="unit" name="unit" placeholder="pcs, kg, box..."
                value="<?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?>" required>

            <label for="quantity">Product Quantity</label>
            <input type="number" id="quantity" name="quantity" min="0"
                value="<?php echo (int) $product['qty_on_hand']; ?>" required>

            <label for="selling_price">Selling Price (₱)</label>
            <input type="number" id="selling_price" name="selling_price" step="0.01" min="0.01"
                value="<?php echo number_format($product['selling_price'], 2, '.', ''); ?>" required>

            <label for="description">Product Description</label>
            <textarea id="description"
                name="description"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>

            <button type="submit" class="btn-submit">Save Changes</button>
            <a href="all_products.php" class="btn-cancel">Cancel</a>
        </form>
    </div>

    <script>
        const video = document.getElementById('camera');
        const capturedPhoto = document.getElementById('capturedPhoto');
        const captureBtn = document.getElementById('captureBtn');
        const retakeBtn = document.getElementById('retakeBtn');
        const cameraBox = document.getElementById('cameraBox');
        const imageWrapper = document.getElementById('imageWrapper');
        const base64Input = document.getElementById('product_image_base64');
        const replaceImageInput = document.getElementById('replace_image');
        let stream = null;
        let cameraActive = false;

        // ✅ Start BACK camera
        async function startCamera() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1280 },
                        height: { ideal: 1280 }
                    },
                    audio: false
                });
                video.srcObject = stream;
                video.style.display = 'block';
                capturedPhoto.style.display = 'none';

                cameraBox.classList.add('visible');
                imageWrapper.style.display = 'none';
                retakeBtn.style.display = 'none';
                captureBtn.classList.add('visible');
                cameraActive = true;
            } catch (err) {
                console.error('Camera error:', err);
                alert('Camera not available: ' + err.message);
            }
        }

        // ✅ Stop camera tracks
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(t => t.stop());
                stream = null;
            }
            cameraActive = false;
        }

        // ✅ Retake button — discards current image and opens camera
        //    We mark replace_image=1 so the API knows to delete the old file.
        retakeBtn.addEventListener('click', () => {
            base64Input.value = '';
            replaceImageInput.value = '1';   // 🆕 flag: user wants to replace
            startCamera();
        });

        // ✅ Capture button — takes a photo from the live feed
        captureBtn.addEventListener('click', () => {
            if (!stream) {
                alert('Camera is not ready yet.');
                return;
            }

            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
            base64Input.value = dataUrl;

            // Show captured photo inside the camera box
            capturedPhoto.src = dataUrl;
            capturedPhoto.style.display = 'block';
            video.style.display = 'none';

            // Hide capture button, show retake button again
            captureBtn.classList.remove('visible');
            retakeBtn.style.display = 'block';

            stopCamera();
        });

        // ✅ Submit form
        document.getElementById('productForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);

            try {
                const res = await fetch('../API/update_product.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                console.log(data);

                if (data.success) {
                    alert('✅ ' + data.message);
                    window.location.href = 'all_products.php';
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
            }
        });

        // ✅ No auto-start — camera opens only when Retake is clicked
        window.addEventListener('load', () => {
            if (!<?php echo $imageUrl ? 'true' : 'false'; ?>) {
                startCamera();
            }
        });
    </script>
</body>

</html>