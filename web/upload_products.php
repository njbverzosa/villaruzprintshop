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
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            padding: 20px;
            padding-bottom: 120px;
        }

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

        .form-column { flex: 1; min-width: 0; }

        @media (max-width: 700px) {
            .container { flex-direction: column; }
            .image-column { flex: 1 1 auto; min-height: 350px; }
        }

        h2 { margin-bottom: 5px; color: #333; }

        .subtitle { font-size: 13px; color: #777; margin-bottom: 20px; }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }

        input[type="text"], input[type="number"], textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        textarea { resize: vertical; min-height: 70px; }

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

        .camera-box.visible { display: block; }

        .camera-box video, .camera-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        #capturedPhoto { display: none; }

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

        .image-placeholder.visible { display: flex; }
        .image-placeholder i { font-size: 48px; color: #cbd5e1; }

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

        .cancel-x-btn.visible { display: inline-flex; }
        .cancel-x-btn:hover { background: #ef4444; color: #ffffff; }
        .cancel-x-btn i { font-size: 13px; pointer-events: none; }

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

        .btn-floating.visible { display: inline-flex; }
        .btn-floating:active { transform: translateX(-50%) scale(0.96); }
        .btn-floating i { pointer-events: none; }

        .btn-floating-camera {
            background: blue;
            box-shadow: 0 6px 20px rgba(0, 0, 255, 0.35);
        }

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

        .btn-upload:hover { background: #0284c7; }

        .btn-submit {
            background: #333;
            width: 100%;
            padding: 12px;
            font-size: 16px;
            margin-top: 10px;
        }

        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

        button:hover { opacity: 0.9; }

        #productImageFile { display: none; }

        @media (max-width: 700px) {
            .image-stage { min-height: 300px; }
        }

        /* ============================================================
           ✅ SUCCESS MODAL — loader → check
           ============================================================ */
        .success-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .success-overlay.visible {
            display: flex;
            animation: fadeIn 0.2s ease-out;
        }

        .success-card {
            background: #ffffff;
            border-radius: 20px;
            width: 100%;
            max-width: 340px;
            padding: 40px 24px 32px 24px;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            animation: popIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Container for both loader and check */
        .success-icon-wrap {
            width: 100px;
            height: 100px;
            margin: 0 auto 22px auto;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* -------- Spinner -------- */
        .success-spinner {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 5px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-right-color: #3b82f6;
            animation: spin 0.9s linear infinite;
            transition: opacity 0.25s ease, transform 0.25s ease;
        }

        /* -------- Checkmark SVG (hidden initially) -------- */
        .success-check {
            position: absolute;
            top: 0;
            left: 0;
            width: 100px;
            height: 100px;
            opacity: 0;
            transform: scale(0.8);
            transition: opacity 0.25s ease, transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .success-check svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* Circle */
        .success-check circle {
            fill: none;
            stroke: #10b981;
            stroke-width: 3;
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
        }

        /* Tick */
        .success-check path {
            fill: none;
            stroke: #10b981;
            stroke-width: 4;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
        }

        /* ---- State: loading ---- */
        .success-card.loading .success-spinner {
            opacity: 1;
            transform: scale(1);
        }

        .success-card.loading .success-check {
            opacity: 0;
            transform: scale(0.8);
        }

        /* ---- State: done ---- */
        .success-card.done .success-spinner {
            opacity: 0;
            transform: scale(0.6);
        }

        .success-card.done .success-check {
            opacity: 1;
            transform: scale(1);
        }

        /* Trigger the SVG drawing animation when in "done" state */
        .success-card.done .success-check circle {
            animation: drawCircle 0.5s ease-out forwards;
        }

        .success-card.done .success-check path {
            animation: drawCheck 0.35s 0.4s ease-out forwards;
        }

        .success-title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
            transition: color 0.3s ease;
        }

        .success-message {
            font-size: 14px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 4px;
            min-height: 42px;
        }

        .success-redirect-hint {
            font-size: 12px;
            color: #94a3b8;
            opacity: 0;
            transition: opacity 0.35s ease;
        }

        .success-card.done .success-redirect-hint {
            opacity: 1;
        }

        /* ---- Animations ---- */
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes popIn {
            from { opacity: 0; transform: scale(0.9) translateY(12px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes drawCircle {
            to { stroke-dashoffset: 0; }
        }

        @keyframes drawCheck {
            to { stroke-dashoffset: 0; }
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

                <button type="button" class="cancel-x-btn" id="cancelUploadBtn" title="Remove image">
                    <i class="fas fa-trash"></i>
                </button>

                <div class="product-image-wrapper" id="imageWrapper" style="display:none;">
                    <img src="" alt="Product" id="productImage">
                </div>

                <div class="image-placeholder" id="imagePlaceholder">
                    <i class="fas fa-image"></i>
                    <span>No image</span>
                </div>

                <div class="camera-box" id="cameraBox">
                    <video id="camera" autoplay playsinline muted></video>
                    <img id="capturedPhoto" alt="Captured product">
                </div>
            </div>

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

                <button type="submit" class="btn-submit" id="saveBtn">Save Product</button>
            </form>
        </div>
    </div>

    <button type="button" class="btn-floating btn-floating-camera" id="retakeBtn" title="Take a photo">
        <i class="fas fa-camera"></i>
    </button>

    <button type="button" class="btn-floating btn-floating-capture" id="captureBtn" title="Capture photo">
        <i class="fas fa-camera"></i>
    </button>

    <!-- ============================================================
         ✅ SUCCESS MODAL — loader then check
         ============================================================ -->
    <div class="success-overlay" id="successOverlay">
        <div class="success-card loading" id="successCard">
            <div class="success-icon-wrap">

                <!-- Spinner (shown first) -->
                <div class="success-spinner"></div>

                <!-- Green check (fades in after) -->
                <div class="success-check">
                    <svg viewBox="0 0 52 52">
                        <circle cx="26" cy="26" r="24" />
                        <path d="M14 27 L23 36 L39 18" />
                    </svg>
                </div>
            </div>

            <div class="success-title" id="successTitle">Saving Product…</div>
            <div class="success-message" id="successMessage">Please wait while we process your upload.</div>
        </div>
    </div>

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
        var saveBtn = document.getElementById('saveBtn');

        var successOverlay = document.getElementById('successOverlay');
        var successCard    = document.getElementById('successCard');
        var successTitle   = document.getElementById('successTitle');
        var successMessage = document.getElementById('successMessage');

        var stream = null;
        var cameraActive = false;

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
            hideFloatingCamera();
            hideFloatingCapture();
            hideUploadButton();
            showCancelButton();
            hidePlaceholder();
        }

        function viewNoImage() {
            hideCancelButton();
            showFloatingCamera();
            hideFloatingCapture();
            showUploadButton();
            showPlaceholder();
        }

        function viewCameraActive() {
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
                viewNoImage();
            }
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(function (t) { t.stop(); });
                stream = null;
            }
            cameraActive = false;
            cameraBox.classList.remove('visible');
        }

        retakeBtn.addEventListener('click', function () {
            base64Input.value = '';
            fileInput.value = '';
            startCamera();
        });

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

            stopCamera();
            cameraBox.classList.add('visible');

            viewHasImage();
        });

        uploadBtn.addEventListener('click', function () {
            fileInput.click();
        });

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
        // FORM SUBMIT
        // ============================================================
        document.getElementById('productForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            if (!base64Input.value) {
                alert('Please capture or upload a product image first.');
                return;
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            var formData = new FormData(e.target);
            formData.delete('product_image');

            try {
                var res = await fetch('../API/add_product.php', {
                    method: 'POST',
                    body: formData
                });

                var text = await res.text();
                console.log('HTTP status:', res.status);
                console.log('Raw response:', text);

                var data;
                try {
                    data = JSON.parse(text);
                } catch (jsonErr) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Product';
                    alert(
                        'Server did not return JSON.\n\n' +
                        'HTTP status: ' + res.status + '\n\n' +
                        'Response preview:\n' + text.substring(0, 500)
                    );
                    return;
                }

                if (data.success) {
                    // ✅ Show loader → check → redirect
                    showSuccessLoaderThenCheck(
                        data.redirect,
                        'Upload Successful!',
                        'Your new product has been added.'
                    );
                } else {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Product';
                    alert(data.message);
                }
            } catch (err) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Product';
                alert('Request failed: ' + err.message);
            }
        });

        // ============================================================
        // SUCCESS MODAL — loader → check → redirect
        // ============================================================
        function showSuccessLoaderThenCheck(redirectUrl, doneTitle, doneMessage) {
            // Reset to loading state
            successCard.classList.remove('done');
            successCard.classList.add('loading');
            successTitle.textContent = 'Saving Product…';
            successMessage.textContent = 'Please wait while we process your upload.';

            // Show the overlay
            successOverlay.classList.add('visible');

            // Phase 1: spinner runs for 1 second
            setTimeout(function () {
                // Phase 2: switch to green check
                successCard.classList.remove('loading');
                successCard.classList.add('done');

                successTitle.textContent = doneTitle || 'Upload Successful!';
                successMessage.textContent = doneMessage || 'Your new product has been added.';

                // Phase 3: after the check draws, redirect
                setTimeout(function () {
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    }
                }, 1200);
            }, 1000);
        }

        // ============================================================
        // ON PAGE LOAD
        // ============================================================
        window.addEventListener('load', function () {
            viewNoImage();
        });
    </script>
</body>

</html>