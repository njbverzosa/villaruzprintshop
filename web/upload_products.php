<?php
//web/upload_products.php
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product — Villaruz Print Shop</title>
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
            padding-bottom: 100px;
        }

        /* ============================================================
           CARD LAYOUT — image left (50%), form right
           ============================================================ */
        .container {
            display: flex;
            gap: 20px;
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            align-items: stretch;
        }

        .image-column {
            flex: 0 0 50%;
            display: flex;
            flex-direction: column;
            min-height: 500px;
        }

        .form-column {
            flex: 1;
            min-width: 0;
        }

        @media (max-width: 700px) {
            .container {
                flex-direction: column;
            }

            .image-column {
                flex: 1 1 auto;
                min-height: 350px;
            }
        }

        h2 {
            margin-bottom: 5px;
            color: #333;
        }

        .subtitle {
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
            flex: 1;
            width: 100%;
            min-height: 400px;
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
        }

        .product-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 10px;
            display: block;
            background: #f8fafc;
        }

        .camera-box {
            position: absolute;
            inset: 0;
            background: #000;
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
            position: absolute;
            inset: 0;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 8px;
            color: #94a3b8;
            font-size: 13px;
            background: #f8fafc;
        }

        .image-placeholder.visible {
            display: flex;
        }

        .image-placeholder i {
            font-size: 48px;
            color: #cbd5e1;
        }

        /* ============================================================
           TRASH BUTTON — top-right of image
           ============================================================ */
        .cancel-x-btn {
            position: absolute;
            top: 8px;
            right: 8px;
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
           FLOATING CAMERA BUTTON — bottom-center of screen
           ============================================================ */
        .btn-floating-camera {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: blue;
            color: #ffffff;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            transition: all 0.2s ease;
            z-index: 1000;
            font-family: inherit;
            box-shadow: 0 6px 20px rgba(0, 0, 255, 0.35);
        }

        .btn-floating-camera.visible {
            display: inline-flex;
        }

        .btn-floating-camera:active {
            transform: translateX(-50%) scale(0.96);
        }

        .btn-floating-camera i {
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
            margin-top: 10px;
            margin-bottom: 8px;
            display: none;
        }

        .btn-upload:hover {
            background: #0284c7;
        }

        .btn-capture {
            background: #28a745;
            width: 100%;
            padding: 12px;
            font-size: 15px;
            margin-top: 10px;
            margin-bottom: 8px;
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

        button:hover {
            opacity: 0.9;
        }

        #productImageFile {
            display: none;
        }

        @media (max-width: 700px) {
            .image-stage {
                min-height: 300px;
            }
        }
    </style>
</head>

<body>
    <div class="container">

        <!-- ============================================================
             LEFT COLUMN — IMAGE
             ============================================================ -->
        <div class="image-column">
            <div class="image-stage" id="imageStage">

                <!-- Trash button -->
                <button type="button" class="cancel-x-btn" id="cancelUploadBtn" title="Remove image">
                    <i class="fas fa-trash"></i>
                </button>

                <!-- Image preview -->
                <div class="product-image-wrapper" id="imageWrapper" style="display:none;">
                    <img src="" alt="Product" id="productImage">
                </div>

                <!-- Empty placeholder -->
                <div class="image-placeholder" id="imagePlaceholder">
                    <i class="fas fa-image"></i>
                    <span>No image</span>
                </div>

                <!-- Camera box -->
                <div class="camera-box" id="cameraBox">
                    <video id="camera" autoplay playsinline muted></video>
                    <img id="capturedPhoto" alt="Captured product">
                </div>
            </div>

            <!-- Capture Product -->
            <button type="button" class="btn-capture" id="captureBtn">
                Capture Product
            </button>

            <!-- Upload Photo -->
            <button type="button" class="btn-upload" id="uploadBtn">
                Upload Photo
            </button>
        </div>

        <!-- ============================================================
             RIGHT COLUMN — FORM
             ============================================================ -->
        <div class="form-column">
            <h2>Add Product</h2>
            <div class="subtitle">Fill in the product details below</div>

            <form id="productForm" enctype="multipart/form-data">
                <!-- ✅ Same action as the OLD file — backend untouched -->
                <input type="hidden" name="action" value="add_product">
                <input type="hidden" name="csrf_token" id="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
                <input type="hidden" name="product_image_base64" id="product_image_base64" value="">

                <input type="file" id="productImageFile" name="product_image"
                    accept="image/jpeg,image/jpg,image/png,image/webp">

                <label for="product_name">Product Name</label>
                <input type="text" id="product_name" name="product_name" required>

                <label for="unit">Unit</label>
                <input type="text" id="unit" name="unit" placeholder="pcs, kg, box..." required>

                <label for="quantity">Product Quantity</label>
                <input type="number" id="quantity" name="quantity" min="0" required>

                <label for="selling_price">Selling Price (₱)</label>
                <input type="number" id="selling_price" name="selling_price" step="0.01" min="0.01" required>

                <label for="description">Product Description</label>
                <textarea id="description" name="description"></textarea>

                <button type="submit" class="btn-submit">Save Product</button>
            </form>
        </div>
    </div>

    <!-- FLOATING CAMERA BUTTON -->
    <button type="button" class="btn-floating-camera" id="retakeBtn" title="Take a photo">
        <i class="fas fa-camera"></i>
    </button>

    <script>
        var video = document.getElementById('camera');
        var capturedPhoto = document.getElementById('capturedPhoto');
        var captureBtn = document.getElementById('captureBtn');
        var retakeBtn = document.getElementById('retakeBtn');
        var uploadBtn = document.getElementById('uploadBtn');
        var cancelUploadBtn = document.getElementById('cancelUploadBtn');
        var cameraBox = document.getElementById('cameraBox');
        var imageWrapper = document.getElementById('imageWrapper');
        var imagePlaceholder = document.getElementById('imagePlaceholder');
        var productImage = document.getElementById('productImage');
        var fileInput = document.getElementById('productImageFile');
        var base64Input = document.getElementById('product_image_base64');

        var stream = null;
        var cameraActive = false;

        // ============================================================
        // SHOW / HIDE helpers
        // ============================================================
        function showCancelButton() { cancelUploadBtn.classList.add('visible'); }
        function hideCancelButton() { cancelUploadBtn.classList.remove('visible'); }

        function showFloatingCamera() { retakeBtn.classList.add('visible'); }
        function hideFloatingCamera() { retakeBtn.classList.remove('visible'); }

        function showUploadButton() { uploadBtn.style.display = 'block'; }
        function hideUploadButton() { uploadBtn.style.display = 'none'; }

        function showPlaceholder() { imagePlaceholder.classList.add('visible'); }
        function hidePlaceholder() { imagePlaceholder.classList.remove('visible'); }

        // ============================================================
        // VIEW STATES
        // ============================================================
        function viewHasImage() {
            hideFloatingCamera();
            hideUploadButton();
            showCancelButton();
            hidePlaceholder();
        }

        function viewNoImage() {
            hideCancelButton();
            showFloatingCamera();
            showUploadButton();
            showPlaceholder();
        }

        function viewCameraActive() {
            hideCancelButton();
            hideFloatingCamera();
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
                captureBtn.classList.add('visible');

                viewCameraActive();

                cameraActive = true;
            } catch (err) {
                console.error('Camera error:', err);
                alert('Camera not available: ' + (err.message || err.name || 'Unknown error'));
            }
        }

        // ============================================================
        // STOP CAMERA
        // ============================================================
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(function (t) { t.stop(); });
                stream = null;
            }
            cameraActive = false;
            cameraBox.classList.remove('visible');
        }

        // ============================================================
        // FLOATING CAMERA BUTTON
        // ============================================================
        retakeBtn.addEventListener('click', function () {
            base64Input.value = '';
            fileInput.value = '';
            startCamera();
        });

        // ============================================================
        // CAPTURE
        // ============================================================
        captureBtn.addEventListener('click', function () {
            if (!stream) {
                alert('Camera is not ready yet.');
                return;
            }

            var canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            var dataUrl = canvas.toDataURL('image/jpeg', 0.9);
            base64Input.value = dataUrl;

            capturedPhoto.src = dataUrl;
            capturedPhoto.style.display = 'block';
            video.style.display = 'none';

            captureBtn.classList.remove('visible');

            stopCamera();
            cameraBox.classList.add('visible');

            viewHasImage();
        });

        // ============================================================
        // UPLOAD PHOTO
        // ============================================================
        uploadBtn.addEventListener('click', function () {
            fileInput.click();
        });

        // ============================================================
        // FILE CHOSEN
        // ============================================================
        fileInput.addEventListener('change', function () {
            var file = fileInput.files[0];
            if (!file) return;

            var allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (allowed.indexOf(file.type) === -1) {
                alert('Please choose a JPG, PNG, or WebP image.');
                fileInput.value = '';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('Image is too large. Max 5MB.');
                fileInput.value = '';
                return;
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                var dataUrl = e.target.result;

                base64Input.value = dataUrl;

                capturedPhoto.src = dataUrl;
                capturedPhoto.style.display = 'block';
                video.style.display = 'none';
                cameraBox.classList.add('visible');

                imageWrapper.style.display = 'none';
                viewHasImage();
            };
            reader.readAsDataURL(file);
        });

        // ============================================================
        // TRASH BUTTON
        // ============================================================
        cancelUploadBtn.addEventListener('click', function () {
            base64Input.value = '';
            fileInput.value = '';

            productImage.src = '';
            productImage.style.display = 'none';
            imageWrapper.style.display = 'none';
            capturedPhoto.style.display = 'none';
            capturedPhoto.src = '';
            cameraBox.classList.remove('visible');

            viewNoImage();
        });

        // ============================================================
        // FORM SUBMIT — same endpoint, same action as before
        // ============================================================
        document.getElementById('productForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            if (!base64Input.value) {
                alert('Please capture or upload a product image first.');
                return;
            }

            var formData = new FormData(e.target);
            formData.delete('product_image');   // we send base64, not the raw file

            try {
                var res = await fetch('../API/add_product.php', {
                    method: 'POST',
                    body: formData
                });

                // ✅ Read as TEXT first so we can see non-JSON responses (useful for debugging)
                var text = await res.text();
                console.log('HTTP status:', res.status);
                console.log('Raw response:', text);

                var data;
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
        // ON PAGE LOAD — no image yet → show floating camera + Upload
        // ============================================================
        window.addEventListener('load', function () {
            viewNoImage();
        });
    </script>
</body>

</html>