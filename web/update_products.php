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
$imageUrl = '';
$imageExists = false;

if (!empty($product['product_image'])) {
    $imageUrl = '../Products/' . htmlspecialchars($product['product_image']);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            padding-bottom: 120px;   /* space for the floating buttons */
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

        /* ============================================================
           IMAGE STAGE
           ============================================================ */
        .image-stage {
            position: relative;
            width: 260px;
            max-width: 100%;
            margin: 0 auto 12px auto;
        }

        .product-image-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
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

        .camera-box {
            position: relative;
            width: 100%;
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

        /* Empty placeholder */
        .image-placeholder {
            display: none;
            width: 100%;
            aspect-ratio: 1 / 1;
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 8px;
            color: #94a3b8;
            font-size: 13px;
        }

        .image-placeholder.visible {
            display: flex;
        }

        .image-placeholder i {
            font-size: 36px;
            color: #cbd5e1;
        }

        /* ============================================================
           TRASH BUTTON — top-right of image
           ============================================================ */
        .cancel-x-btn {
            position: absolute;
            top: -3px;
            right: -3px;
            display: none;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            padding: 0;
            background: #ffffff;
            color: black;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 5;
            line-height: 1;
            font-family: inherit;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .cancel-x-btn.visible {
            display: inline-flex;
        }

        .cancel-x-btn:hover {
            background: #ef4444;
            color: #ffffff;
        }

        .cancel-x-btn i {
            font-size: 13px;
            pointer-events: none;
        }

        /* ============================================================
           FLOATING BUTTONS — bottom-center of screen
           Shared base class .btn-floating
           ============================================================ */
        .btn-floating {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            width: 68px;
            height: 68px;
            border-radius: 50%;
            color: #ffffff;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            transition: all 0.2s ease;
            z-index: 1000;
            font-family: inherit;
        }

        .btn-floating.visible {
            display: inline-flex;
        }

        .btn-floating:active {
            transform: translateX(-50%) scale(0.96);
        }

        .btn-floating i {
            pointer-events: none;
        }

        /* Floating camera (blue) — idle state, no image */
        .btn-floating-camera {
            background: blue;
            box-shadow: 0 6px 20px rgba(0, 0, 255, 0.35);
        }

        /* Floating capture (green shutter) — camera live */
        .btn-floating-capture {
            background: #28a745;
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.45);
            border: 4px solid #ffffff;
        }

        .btn-floating-capture::after {
            content: "";
            position: absolute;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.5);
            pointer-events: none;
        }

        /* ========== OTHER BUTTONS ========== */
        button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            color: white;
            font-family: inherit;
        }

        .btn-upload {
            background: #0ea5e9;
            width: 100%;
            padding: 12px;
            font-size: 15px;
            margin-bottom: 8px;
            display: none;      /* hidden by default — JS toggles */
        }

        .btn-upload:hover {
            background: #0284c7;
        }

        .btn-submit {
            background: #333;
            width: 100%;
            padding: 12px;
            font-size: 16px;
            margin-top: 10px;
        }

        button:hover {
            opacity: 0.9;
        }

        .divider {
            border-top: 1px solid #eee;
            margin: 10px 0 20px 0;
        }

        #productImageFile {
            display: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Update Product</h2>
        <div class="product-number">Product #: <?php echo htmlspecialchars($product['product_number']); ?></div>

        <form id="productForm" enctype="multipart/form-data">
            <!-- Hidden fields -->
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
            <input type="hidden" name="product_number"
                value="<?php echo htmlspecialchars($product['product_number']); ?>">
            <input type="hidden" name="csrf_token" id="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
            <input type="hidden" name="product_image_base64" id="product_image_base64" value="">

            <!-- 0 = keep original, 1 = replace with new image, 2 = delete entirely -->
            <input type="hidden" name="replace_image" id="replace_image" value="0">

            <!-- Hidden file input -->
            <input type="file" id="productImageFile" name="product_image"
                accept="image/jpeg,image/jpg,image/png,image/webp">

            <!-- ============================================================
                 IMAGE STAGE
                 ============================================================ -->
            <div class="image-stage" id="imageStage">

                <!-- Trash button — top-right of image -->
                <button type="button" class="cancel-x-btn" id="cancelUploadBtn" title="Remove image">
                    <i class="fas fa-trash"></i>
                </button>

                <!-- Image preview -->
                <div class="product-image-wrapper" id="imageWrapper">
                    <img src="<?php echo $imageUrl; ?>"
                        alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                        id="productImage"
                        style="<?php echo $imageUrl ? '' : 'display:none;'; ?>">
                </div>

                <!-- Empty placeholder -->
                <div class="image-placeholder" id="imagePlaceholder">
                    <i class="fas fa-image"></i>
                    <span>No image</span>
                </div>

                <!-- Camera box (live feed + captured preview) -->
                <div class="camera-box" id="cameraBox">
                    <video id="camera" autoplay playsinline muted></video>
                    <img id="capturedPhoto" alt="Captured product">
                </div>
            </div>

            <!-- Upload Photo — shown only when no image -->
            <button type="button" class="btn-upload" id="uploadBtn">
                Upload Photo
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
        </form>
    </div>

    <!-- ============================================================
         FLOATING CAMERA BUTTON (idle — blue camera)
         ============================================================ -->
    <button type="button" class="btn-floating btn-floating-camera" id="retakeBtn" title="Take a photo">
        <i class="fas fa-camera"></i>
    </button>

    <!-- ============================================================
         FLOATING CAPTURE BUTTON (camera live — green shutter)
         ============================================================ -->
    <button type="button" class="btn-floating btn-floating-capture" id="captureBtn" title="Capture photo">
        <i class="fas fa-camera"></i>
    </button>

    <script>
        const video = document.getElementById('camera');
        const capturedPhoto = document.getElementById('capturedPhoto');
        const captureBtn = document.getElementById('captureBtn');
        const retakeBtn = document.getElementById('retakeBtn');
        const uploadBtn = document.getElementById('uploadBtn');
        const cancelUploadBtn = document.getElementById('cancelUploadBtn');
        const cameraBox = document.getElementById('cameraBox');
        const imageWrapper = document.getElementById('imageWrapper');
        const imagePlaceholder = document.getElementById('imagePlaceholder');
        const productImage = document.getElementById('productImage');
        const fileInput = document.getElementById('productImageFile');
        const base64Input = document.getElementById('product_image_base64');
        const replaceImageInput = document.getElementById('replace_image');

        const originalImageSrc = '<?php echo $imageUrl; ?>';
        const originalImageExists = <?php echo $imageUrl ? 'true' : 'false'; ?>;

        let stream = null;
        let cameraActive = false;

        // ============================================================
        // SHOW / HIDE helpers
        // ============================================================
        function showCancelButton() { cancelUploadBtn.classList.add('visible'); }
        function hideCancelButton() { cancelUploadBtn.classList.remove('visible'); }

        function showFloatingCamera() { retakeBtn.classList.add('visible'); }
        function hideFloatingCamera() { retakeBtn.classList.remove('visible'); }

        function showFloatingCapture() { captureBtn.classList.add('visible'); }
        function hideFloatingCapture() { captureBtn.classList.remove('visible'); }

        function showUploadButton() { uploadBtn.style.display = 'block'; }
        function hideUploadButton() { uploadBtn.style.display = 'none'; }

        function showPlaceholder() { imagePlaceholder.classList.add('visible'); }
        function hidePlaceholder() { imagePlaceholder.classList.remove('visible'); }

        // ============================================================
        // VIEW STATES
        // ============================================================
        function viewHasImage() {
            // Image present → show ONLY trash
            hideFloatingCamera();
            hideFloatingCapture();
            hideUploadButton();
            showCancelButton();
            hidePlaceholder();
        }

        function viewNoImage() {
            // No image & camera idle → show floating camera + Upload
            hideCancelButton();
            showFloatingCamera();
            hideFloatingCapture();
            showUploadButton();
            showPlaceholder();
        }

        function viewCameraActive() {
            // Live camera → show ONLY floating capture
            hideCancelButton();
            hideFloatingCamera();
            showFloatingCapture();
            hideUploadButton();
            hidePlaceholder();
        }

        // ============================================================
        // START BACK CAMERA
        // ============================================================
        async function startCamera() {
            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Camera not supported in this browser. Please use HTTPS or a modern browser.');
                    return;
                }

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

                viewCameraActive();

                cameraActive = true;
            } catch (err) {
                console.error('Camera error:', err);
                alert('Camera not available: ' + (err.message || err.name || 'Unknown error'));
                // Reset back to no-image state so the user can retry
                if (originalImageExists) {
                    viewHasImage();
                } else {
                    viewNoImage();
                }
            }
        }

        // ============================================================
        // STOP CAMERA
        // ============================================================
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(t => t.stop());
                stream = null;
            }
            cameraActive = false;
            cameraBox.classList.remove('visible');
        }

        // ============================================================
        // FLOATING CAMERA BUTTON — start retake
        // ============================================================
        retakeBtn.addEventListener('click', () => {
            base64Input.value = '';
            fileInput.value = '';
            replaceImageInput.value = '1';
            startCamera();
        });

        // ============================================================
        // FLOATING CAPTURE BUTTON — take snapshot
        // ============================================================
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
            replaceImageInput.value = '1';

            capturedPhoto.src = dataUrl;
            capturedPhoto.style.display = 'block';
            video.style.display = 'none';

            stopCamera();

            // ✅ Re-show the camera box with the captured preview
            cameraBox.classList.add('visible');

            // ✅ Now image exists → show ONLY trash
            viewHasImage();
        });

        // ============================================================
        // UPLOAD PHOTO
        // ============================================================
        uploadBtn.addEventListener('click', () => {
            fileInput.click();
        });

        // ============================================================
        // FILE CHOSEN
        // ============================================================
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (!file) return;

            const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) {
                alert('Please choose a JPG, PNG, or WebP image.');
                fileInput.value = '';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('Image is too large. Max 5MB.');
                fileInput.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const dataUrl = e.target.result;

                base64Input.value = dataUrl;
                replaceImageInput.value = '1';

                // Show as preview
                capturedPhoto.src = dataUrl;
                capturedPhoto.style.display = 'block';
                video.style.display = 'none';
                cameraBox.classList.add('visible');

                imageWrapper.style.display = 'none';

                // ✅ Now image exists → show ONLY trash
                viewHasImage();
            };
            reader.readAsDataURL(file);
        });

        // ============================================================
        // TRASH BUTTON — remove current image
        // ============================================================
        cancelUploadBtn.addEventListener('click', () => {
            // Clear any new selection
            base64Input.value = '';
            fileInput.value = '';

            // Mark image for deletion on save
            replaceImageInput.value = '2';

            // Hide the image, hide camera preview
            productImage.src = '';
            productImage.style.display = 'none';
            imageWrapper.style.display = 'none';
            capturedPhoto.style.display = 'none';
            capturedPhoto.src = '';
            cameraBox.classList.remove('visible');

            // ✅ Now no image → show floating camera + Upload Photo
            viewNoImage();
        });

        // ============================================================
        // FORM SUBMIT
        // ============================================================
        document.getElementById('productForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const hasNewImage = base64Input.value !== '';
            const hasFile = fileInput.files.length > 0;
            const isDeleting = replaceImageInput.value === '2';

            if (!hasNewImage && !hasFile && !originalImageExists && !isDeleting) {
                alert('Please capture or upload a product image.');
                return;
            }

            if (hasFile && !hasNewImage) {
                await new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        base64Input.value = ev.target.result;
                        resolve();
                    };
                    reader.readAsDataURL(fileInput.files[0]);
                });
            }

            const formData = new FormData(e.target);
            formData.delete('product_image');

            try {
                const res = await fetch('../API/update_product.php', {
                    method: 'POST',
                    body: formData
                });

                const text = await res.text();
                console.log('HTTP status:', res.status);
                console.log('Raw response:', text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (jsonErr) {
                    alert(
                        'Server did not return JSON.\n\n' +
                        'HTTP status: ' + res.status + '\n\n' +
                        'Response preview:\n' + text.substring(0, 500)
                    );
                    return;
                }

                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.message);
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
            }
        });

        // ============================================================
        // ON PAGE LOAD
        // ============================================================
        window.addEventListener('load', () => {
            if (originalImageExists) {
                // ✅ Image exists → show ONLY trash
                viewHasImage();
            } else {
                // ✅ No image → show floating camera + Upload Photo
                viewNoImage();
            }
        });
    </script>
</body>

</html>