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
    header('Location: ../admins-portal.php');
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
    header('Location: ../admins-portal.php');
    exit;
}

$user = $userData;

date_default_timezone_set('Asia/Manila');
$timezone = new DateTimeZone('Asia/Manila');

$defaultImageUrl = '../Products/no-image.jpg';

$catStmt = $pdo->query("
    SELECT DISTINCT category_group
    FROM merchandise_inventory
    WHERE category_group IS NOT NULL AND category_group <> ''
    ORDER BY category_group ASC
");
$allCategoryGroups = $catStmt->fetchAll(PDO::FETCH_COLUMN);
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
        input[type="url"],
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            background: #fff;
            font-family: inherit;
            cursor: pointer;
        }

        textarea {
            resize: vertical;
            min-height: 70px;
        }

        /* ✅ Product Name + Search button row */
        .name-search-row {
            display: flex;
            gap: 8px;
            align-items: stretch;
            margin-bottom: 15px;
        }

        .name-search-row input[type="text"] {
            flex: 1;
            margin-bottom: 0;
        }

        .btn-search-google {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0 14px;
            background: #4285f4;
            color: #ffffff;
            border: none;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s, transform 0.1s, opacity 0.2s;
        }

        .btn-search-google:hover {
            background: #3367d6;
        }

        .btn-search-google:active {
            transform: scale(0.97);
        }

        .btn-search-google i {
            font-size: 13px;
        }

        /* ✅ Hidden by default; shown only when Product Name has content */
        .btn-search-google.hidden {
            display: none;
        }

        /* ============================================================
           ✅ Image Link input (auto-load)
           ============================================================ */
        .image-link-row {
            display: flex;
            gap: 8px;
            align-items: stretch;
            margin-bottom: 15px;
        }

        .image-link-row input[type="url"] {
            flex: 1;
            margin-bottom: 0;
        }

        .image-link-hint {
            font-size: 11px;
            color: #64748b;
            margin-top: -10px;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .image-link-status {
            font-size: 12px;
            margin-top: -8px;
            margin-bottom: 12px;
            min-height: 16px;
            display: none;
        }

        .image-link-status.loading { color: #0ea5e9; display: block; }
        .image-link-status.ok      { color: #10b981; display: block; }
        .image-link-status.error   { color: #ef4444; display: block; }

        /* ============================================================
           ✅ SEARCHABLE UNIT DROPDOWN (no ✕ button)
           ============================================================ */
        .unit-search-wrap {
            position: relative;
            margin-bottom: 15px;
        }

        .unit-search-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            background: #fff;
            cursor: text;
        }

        .unit-search-input:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
        }

        .unit-options {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            max-height: 240px;
            overflow-y: auto;
            z-index: 500;
            display: none;
        }

        .unit-options.open {
            display: block;
        }

        .unit-option {
            padding: 10px 14px;
            cursor: pointer;
            font-size: 14px;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .unit-option:last-child {
            border-bottom: none;
        }

        .unit-option:hover,
        .unit-option.active {
            background: #eff6ff;
            color: #0ea5e9;
        }

        .unit-option.selected {
            background: #e0f2fe;
            font-weight: 600;
        }

        .unit-option .unit-code {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .unit-option.no-match {
            cursor: default;
            color: #94a3b8;
            justify-content: center;
            font-style: italic;
        }

        .unit-option.no-match:hover {
            background: #fff;
            color: #94a3b8;
        }

        .unit-hint {
            font-size: 11px;
            color: #94a3b8;
            margin-top: -10px;
            margin-bottom: 12px;
        }

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
            transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
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

        .btn-torch {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.55);
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            z-index: 20;
            transition: all 0.2s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .btn-torch.visible {
            display: flex;
        }

        .btn-torch:active {
            transform: scale(0.92);
        }

        .btn-torch.torch-on {
            background: #f59e0b;
            color: #000;
            border-color: #fbbf24;
            box-shadow: 0 0 14px rgba(245, 158, 11, 0.9);
        }

        .btn-torch i {
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

        .btn-submit {
            background: #333;
            width: 100%;
            padding: 12px;
            font-size: 16px;
            margin-top: 10px;
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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

        /* ============================================================
           ✅ DRAG & DROP HIGHLIGHT
           ============================================================ */
        .image-stage.dragover {
            border-color: #0ea5e9;
            background: #eff6ff;
            box-shadow: inset 0 0 0 4px rgba(14, 165, 233, 0.15);
        }

        .image-stage.dragover::after {
            content: "📸 Drop image here";
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
            color: #0ea5e9;
            background: rgba(239, 246, 255, 0.85);
            z-index: 10;
            pointer-events: none;
            border-radius: 8px;
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

        .success-icon-wrap {
            width: 100px;
            height: 100px;
            margin: 0 auto 22px auto;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

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

        .success-check circle {
            fill: none;
            stroke: #10b981;
            stroke-width: 3;
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
        }

        .success-check path {
            fill: none;
            stroke: #10b981;
            stroke-width: 4;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
        }

        .success-card.loading .success-spinner {
            opacity: 1;
            transform: scale(1);
        }

        .success-card.loading .success-check {
            opacity: 0;
            transform: scale(0.8);
        }

        .success-card.done .success-spinner {
            opacity: 0;
            transform: scale(0.6);
        }

        .success-card.done .success-check {
            opacity: 1;
            transform: scale(1);
        }

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

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes popIn { from { opacity: 0; transform: scale(0.9) translateY(12px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes drawCircle { to { stroke-dashoffset: 0; } }
        @keyframes drawCheck { to { stroke-dashoffset: 0; } }
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
                    <span>No image — drag & drop, paste, or use the camera</span>
                </div>

                <div class="camera-box" id="cameraBox">
                    <video id="camera" autoplay playsinline muted></video>
                    <img id="capturedPhoto" alt="Captured product">
                    <button type="button" class="btn-torch" id="torchBtn" title="Toggle flashlight">
                        <i class="fas fa-lightbulb"></i>
                    </button>
                </div>
            </div>

            <!-- ============================================================
                 ✅ Image Link input (auto-load)
                 ============================================================ -->
            <label for="image_link" style="margin-top: 12px;">Image Link</label>
            <div class="image-link-row">
                <input type="url" id="image_link"
                    placeholder="Paste image URL here — it loads automatically"
                    autocomplete="off" spellcheck="false">
            </div>
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
                <input type="hidden" name="use_default_image" id="use_default_image" value="0">

                <input type="file" id="productImageFile" name="product_image"
                    accept="image/jpeg,image/jpg,image/png,image/webp">

                <!-- ✅ CATEGORY GROUP -->
                <label for="category_group">Category Group</label>
                <select name="category_group" id="category_group">
                    <option value="">— None —</option>
                    <?php foreach ($allCategoryGroups as $cg): ?>
                        <option value="<?php echo htmlspecialchars($cg); ?>">
                            <?php echo htmlspecialchars($cg); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__new__">+ Add new category…</option>
                </select>

                <div id="newCategoryWrap" style="display:none;">
                    <label for="new_category_group">New Category Name</label>
                    <input type="text" id="new_category_group" name="new_category_group"
                        placeholder="e.g. Printer Accessories">
                </div>

                <!-- ✅ Product Name + Search button -->
                <label for="product_name">Product Name</label>
                <div class="name-search-row">
                    <input type="text" id="product_name" name="product_name" required>
                    <button type="button" class="btn-search-google hidden" id="searchGoogleBtn"
                        title="Search this product on Google">
                        <i class="fas fa-search"></i>
                        <span>Search</span>
                    </button>
                </div>

                <!-- ✅ SEARCHABLE UNIT DROPDOWN -->
                <label for="unitSearch">Unit</label>
                <div class="unit-search-wrap">
                    <input type="text" id="unitSearch" class="unit-search-input"
                        placeholder="Type to search unit (e.g. B → Bottle, Box…)"
                        autocomplete="off" spellcheck="false">
                    <div class="unit-options" id="unitOptions"></div>
                </div>
                <input type="hidden" name="unit" id="unit" value="pcs">

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

    <div class="success-overlay" id="successOverlay">
        <div class="success-card loading" id="successCard">
            <div class="success-icon-wrap">
                <div class="success-spinner"></div>
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
        var cancelUploadBtn = document.getElementById('cancelUploadBtn');
        var cameraBox = document.getElementById('cameraBox');
        var imageWrapper = document.getElementById('imageWrapper');
        var imagePlaceholder = document.getElementById('imagePlaceholder');
        var productImage = document.getElementById('productImage');
        var fileInput = document.getElementById('productImageFile');
        var base64Input = document.getElementById('product_image_base64');
        var useDefaultImageInput = document.getElementById('use_default_image');
        var saveBtn = document.getElementById('saveBtn');
        var imageStage = document.getElementById('imageStage');

        var torchBtn = document.getElementById('torchBtn');
        var torchOn = false;
        var torchTrack = null;

        var successOverlay = document.getElementById('successOverlay');
        var successCard = document.getElementById('successCard');
        var successTitle = document.getElementById('successTitle');
        var successMessage = document.getElementById('successMessage');

        var defaultImageUrl = '<?php echo $defaultImageUrl; ?>';

        var stream = null;
        var cameraActive = false;
        var isSaving = false;

        // ============================================================
        // ✅ GOOGLE SEARCH BUTTON — shows only when Product Name has text
        // ============================================================
        var productNameInput = document.getElementById('product_name');
        var searchGoogleBtn = document.getElementById('searchGoogleBtn');

        function refreshSearchButton() {
            if (!searchGoogleBtn || !productNameInput) return;
            var v = (productNameInput.value || '').trim();
            if (v.length > 0) {
                searchGoogleBtn.classList.remove('hidden');
            } else {
                searchGoogleBtn.classList.add('hidden');
            }
        }

        if (productNameInput) {
            productNameInput.addEventListener('input', refreshSearchButton);
            // Initial state
            refreshSearchButton();
        }

        if (searchGoogleBtn) {
            searchGoogleBtn.addEventListener('click', function () {
                var name = (productNameInput.value || '').trim();
                if (!name) {
                    alert('Please enter a product name first.');
                    productNameInput.focus();
                    return;
                }

                var url = 'https://www.google.com/search?q=' + encodeURIComponent(name);

                if (window.AndroidBiometric && typeof window.AndroidBiometric.openGoogleSearch === 'function') {
                    try {
                        window.AndroidBiometric.openGoogleSearch(name);
                        return;
                    } catch (e) {
                        console.warn('openGoogleSearch bridge failed:', e);
                    }
                }

                if (window.AndroidBiometric && typeof window.AndroidBiometric.openInBrowser === 'function') {
                    try {
                        window.AndroidBiometric.openInBrowser(url);
                        return;
                    } catch (e) {
                        console.warn('openInBrowser bridge failed:', e);
                    }
                }

                window.open(url, '_blank');
            });
        }

        // ============================================================
        // ✅ CATEGORY GROUP
        // ============================================================
        var categorySelect = document.getElementById('category_group');
        var newCategoryWrap = document.getElementById('newCategoryWrap');
        var newCategoryInput = document.getElementById('new_category_group');

        if (categorySelect) {
            categorySelect.addEventListener('change', function () {
                if (this.value === '__new__') {
                    newCategoryWrap.style.display = 'block';
                    newCategoryInput.focus();
                } else {
                    newCategoryWrap.style.display = 'none';
                    newCategoryInput.value = '';
                }
            });
        }

        // ============================================================
        // ✅ IMAGE LINK INPUT
        // ============================================================
        var imageLinkInput = document.getElementById('image_link');
        var imageLinkStatus = document.getElementById('imageLinkStatus');

        function setLinkStatus(msg, type) {
            if (!imageLinkStatus) return;
            imageLinkStatus.className = 'image-link-status ' + (type || '');
            imageLinkStatus.textContent = msg || '';
        }

        function looksLikeImageUrl(u) {
            if (!u) return false;
            if (!/^https?:\/\//i.test(u)) return false;
            return /\.(jpe?g|png|webp|gif|bmp|avif)(\?|#|$)/i.test(u)
                || /(images?|photos?|cdn|imgur|unsplash|cloudinary|googleusercontent)/i.test(u);
        }

        async function loadImageFromLink(url) {
            if (!url) {
                setLinkStatus('Please paste an image URL first.', 'error');
                return;
            }
            if (!looksLikeImageUrl(url)) {
                setLinkStatus('That does not look like an image URL.', 'error');
                return;
            }

            setLinkStatus('Loading image…', 'loading');

            try {
                var res = await fetch(url, { mode: 'cors', credentials: 'omit' });
                if (!res.ok) throw new Error('HTTP ' + res.status);

                var blob = await res.blob();
                if (!blob.type || blob.type.indexOf('image/') !== 0) {
                    throw new Error('URL is not an image');
                }
                if (blob.size > 5 * 1024 * 1024) {
                    throw new Error('Image is larger than 5MB');
                }

                var dataUrl = await new Promise(function (resolve, reject) {
                    var reader = new FileReader();
                    reader.onload = function () { resolve(reader.result); };
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);
                });

                base64Input.value = dataUrl;
                useDefaultImageInput.value = '0';

                capturedPhoto.src = dataUrl;
                capturedPhoto.style.display = 'block';
                video.style.display = 'none';
                cameraBox.classList.add('visible');

                imageWrapper.style.display = 'none';
                viewHasImage();

                setLinkStatus('✔ Image loaded and ready to save.', 'ok');

                setTimeout(function () {
                    setLinkStatus('', '');
                }, 600);
            } catch (err) {
                console.warn('Image link fetch failed:', err);
                var previewImg = new Image();
                previewImg.onload = function () {
                    productImage.src = url;
                    productImage.style.display = 'block';
                    imageWrapper.style.display = 'flex';
                    cameraBox.classList.remove('visible');
                    capturedPhoto.style.display = 'none';
                    imagePlaceholder.classList.remove('visible');

                    setLinkStatus('Preview only — this host blocks cross-origin download. Download the image manually and upload it.', 'error');
                };
                previewImg.onerror = function () {
                    setLinkStatus('Failed to load image. ' + (err.message || ''), 'error');
                };
                previewImg.src = url;
            }
        }

        if (imageLinkInput) {
            imageLinkInput.addEventListener('paste', function () {
                setTimeout(function () {
                    var v = (imageLinkInput.value || '').trim();
                    if (v && looksLikeImageUrl(v)) loadImageFromLink(v);
                }, 30);
            });

            imageLinkInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation(); // don't bubble to global Enter-to-save
                    var v = (imageLinkInput.value || '').trim();
                    if (v) loadImageFromLink(v);
                }
            });

            imageLinkInput.addEventListener('blur', function () {
                var v = (imageLinkInput.value || '').trim();
                if (v && looksLikeImageUrl(v)) loadImageFromLink(v);
            });
        }

        // ============================================================
        // ✅ SEARCHABLE UNIT DROPDOWN
        // ============================================================
        var UNIT_OPTIONS = [
            { value: 'pcs', label: 'Pcs' },
            { value: 'piece', label: 'Piece' },
            { value: 'pieces', label: 'Pieces' },
            { value: 'unit', label: 'Unit' },
            { value: 'set', label: 'Set' },
            { value: 'pair', label: 'Pair' },
            { value: 'dozen', label: 'Dozen' },
            { value: 'box', label: 'Box' },
            { value: 'carton', label: 'Carton' },
            { value: 'case', label: 'Case' },
            { value: 'crate', label: 'Crate' },
            { value: 'pack', label: 'Pack' },
            { value: 'packet', label: 'Packet' },
            { value: 'sachet', label: 'Sachet' },
            { value: 'pouch', label: 'Pouch' },
            { value: 'bag', label: 'Bag' },
            { value: 'sack', label: 'Sack' },
            { value: 'bundle', label: 'Bundle' },
            { value: 'ream', label: 'Ream' },
            { value: 'roll', label: 'Roll' },
            { value: 'rolls', label: 'Rolls' },
            { value: 'pad', label: 'Pad' },
            { value: 'pads', label: 'Pads' },
            { value: 'sheet', label: 'Sheet' },
            { value: 'sheets', label: 'Sheets' },
            { value: 'bottle', label: 'Bottle' },
            { value: 'can', label: 'Can' },
            { value: 'tin', label: 'Tin' },
            { value: 'jar', label: 'Jar' },
            { value: 'tube', label: 'Tube' },
            { value: 'drum', label: 'Drum' },
            { value: 'barrel', label: 'Barrel' },
            { value: 'gallon', label: 'Gallon' },
            { value: 'liter', label: 'Liter' },
            { value: 'ml', label: 'Milliliter (mL)' },
            { value: 'gram', label: 'Gram (g)' },
            { value: 'kg', label: 'Kilogram (kg)' },
            { value: 'ton', label: 'Ton' },
            { value: 'lb', label: 'Pound (lb)' },
            { value: 'oz', label: 'Ounce (oz)' },
            { value: 'meter', label: 'Meter (m)' },
            { value: 'cm', label: 'Centimeter (cm)' },
            { value: 'ft', label: 'Foot (ft)' },
            { value: 'in', label: 'Inch (in)' },
            { value: 'yard', label: 'Yard' },
            { value: 'hour', label: 'Hour' },
            { value: 'day', label: 'Day' },
            { value: 'month', label: 'Month' },
            { value: 'session', label: 'Session' }
        ];

        var unitSearchInput = document.getElementById('unitSearch');
        var unitOptionsBox = document.getElementById('unitOptions');
        var unitHidden = document.getElementById('unit');

        var activeOptionIndex = -1;
        var currentMatches = [];

        unitHidden.value = 'pcs';
        unitSearchInput.value = 'Pcs';

        function renderUnitOptions(filterText) {
            var term = (filterText || '').trim().toLowerCase();

            currentMatches = UNIT_OPTIONS.filter(function (opt) {
                if (term === '') return true;
                return opt.label.toLowerCase().indexOf(term) === 0 ||
                       opt.value.toLowerCase().indexOf(term) === 0;
            });

            if (currentMatches.length === 0) {
                unitOptionsBox.innerHTML = '<div class="unit-option no-match">No matching unit</div>';
                activeOptionIndex = -1;
                return;
            }

            var html = '';
            for (var j = 0; j < currentMatches.length; j++) {
                var o = currentMatches[j];
                var selectedClass = (o.value === unitHidden.value) ? ' selected' : '';
                var activeClass = (j === activeOptionIndex) ? ' active' : '';
                html += '<div class="unit-option' + selectedClass + activeClass + '"'
                      + ' data-value="' + o.value + '" data-label="' + o.label + '">'
                      + '<span class="unit-label">' + o.label + '</span>'
                      + '<span class="unit-code">' + o.value + '</span>'
                      + '</div>';
            }
            unitOptionsBox.innerHTML = html;
        }

        function openUnitOptions() {
            activeOptionIndex = -1;
            renderUnitOptions(unitSearchInput.value);
            unitOptionsBox.classList.add('open');
        }

        function closeUnitOptions() {
            unitOptionsBox.classList.remove('open');
            activeOptionIndex = -1;
        }

        function selectUnit(value, label) {
            unitHidden.value = value;
            unitSearchInput.value = label;
            closeUnitOptions();
        }

        unitSearchInput.addEventListener('focus', openUnitOptions);
        unitSearchInput.addEventListener('click', function () {
            if (!unitOptionsBox.classList.contains('open')) openUnitOptions();
        });

        unitSearchInput.addEventListener('input', function () {
            activeOptionIndex = -1;
            renderUnitOptions(unitSearchInput.value);
            unitOptionsBox.classList.add('open');
        });

        unitSearchInput.addEventListener('keydown', function (e) {
            var opts = unitOptionsBox.querySelectorAll('.unit-option:not(.no-match)');
            if (!opts.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeOptionIndex = (activeOptionIndex + 1) % opts.length;
                updateActiveOption(opts);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeOptionIndex = (activeOptionIndex - 1 + opts.length) % opts.length;
                updateActiveOption(opts);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation(); // prevent global Enter-to-save while picking a unit
                if (activeOptionIndex >= 0 && currentMatches[activeOptionIndex]) {
                    var c = currentMatches[activeOptionIndex];
                    selectUnit(c.value, c.label);
                } else if (currentMatches.length === 1) {
                    selectUnit(currentMatches[0].value, currentMatches[0].label);
                }
            } else if (e.key === 'Escape') {
                closeUnitOptions();
                unitSearchInput.blur();
            }
        });

        function updateActiveOption(opts) {
            for (var i = 0; i < opts.length; i++) {
                opts[i].classList.toggle('active', i === activeOptionIndex);
            }
            if (opts[activeOptionIndex]) {
                opts[activeOptionIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        unitOptionsBox.addEventListener('click', function (e) {
            var target = e.target.closest('.unit-option');
            if (!target || target.classList.contains('no-match')) return;
            e.preventDefault();
            e.stopPropagation();
            selectUnit(target.getAttribute('data-value'), target.getAttribute('data-label'));
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.unit-search-wrap')) {
                closeUnitOptions();
            }
        });

        // ============================================================
        // SHOW / HIDE helpers
        // ============================================================
        function showCancelButton() { cancelUploadBtn.classList.add('visible'); }
        function hideCancelButton() { cancelUploadBtn.classList.remove('visible'); }

        function showFloatingCamera() { retakeBtn.classList.add('visible'); }
        function hideFloatingCamera() { retakeBtn.classList.remove('visible'); }

        function showFloatingCapture() { captureBtn.classList.add('visible'); }
        function hideFloatingCapture() { captureBtn.classList.remove('visible'); }

        function showPlaceholder() { imagePlaceholder.classList.add('visible'); }
        function hidePlaceholder() { imagePlaceholder.classList.remove('visible'); }

        function showTorchButton() {
            if (torchTrack && typeof torchTrack.getCapabilities === 'function') {
                var caps = torchTrack.getCapabilities();
                if (caps.torch) {
                    torchBtn.classList.add('visible');
                    return;
                }
            }
            torchBtn.classList.add('visible');
        }

        function hideTorchButton() {
            torchBtn.classList.remove('visible');
            torchOn = false;
            torchBtn.classList.remove('torch-on');
        }

        // ============================================================
        // VIEW STATES
        // ============================================================
        function viewHasImage() {
            hideFloatingCamera();
            hideFloatingCapture();
            showCancelButton();
            hidePlaceholder();
            hideTorchButton();
        }

        function viewNoImage() {
            hideCancelButton();
            showFloatingCamera();
            hideFloatingCapture();
            showPlaceholder();
            hideTorchButton();
        }

        function viewCameraActive() {
            hideCancelButton();
            hideFloatingCamera();
            showFloatingCapture();
            hidePlaceholder();
            if (torchTrack) {
                showTorchButton();
            }
        }

        // ============================================================
        // TORCH
        // ============================================================
        async function toggleTorch() {
            if (!torchTrack) {
                alert('Torch not available on this device/camera.');
                return;
            }

            try {
                torchOn = !torchOn;
                await torchTrack.applyConstraints({
                    advanced: [{ torch: torchOn }]
                });
                if (torchOn) {
                    torchBtn.classList.add('torch-on');
                } else {
                    torchBtn.classList.remove('torch-on');
                }
            } catch (err) {
                console.error('Torch error:', err);
                torchOn = !torchOn;
                alert('Could not toggle torch: ' + (err.message || err.name));
                torchBtn.classList.remove('torch-on');
            }
        }

        torchBtn.addEventListener('click', toggleTorch);

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

                torchTrack = stream.getVideoTracks()[0] || null;
                torchOn = false;
                torchBtn.classList.remove('torch-on');

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
            torchTrack = null;
            torchOn = false;
            torchBtn.classList.remove('torch-on');
            hideTorchButton();

            cameraActive = false;
            cameraBox.classList.remove('visible');
        }

        retakeBtn.addEventListener('click', function () {
            base64Input.value = '';
            fileInput.value = '';
            useDefaultImageInput.value = '0';
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
            useDefaultImageInput.value = '0';

            capturedPhoto.src = dataUrl;
            capturedPhoto.style.display = 'block';
            video.style.display = 'none';

            stopCamera();
            cameraBox.classList.add('visible');

            viewHasImage();
        });

        // ============================================================
        // ✅ SHARED — apply an image file to the preview
        // ============================================================
        function applyImageFile(file) {
            if (!file) return;

            var allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (allowed.indexOf(file.type) === -1) {
                alert('Please use a JPG, PNG, or WebP image.');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('Image is too large. Max 5MB.');
                return;
            }

            var reader = new FileReader();
            reader.onload = function (ev) {
                var dataUrl = ev.target.result;

                base64Input.value = dataUrl;
                useDefaultImageInput.value = '0';
                fileInput.value = '';

                capturedPhoto.src = dataUrl;
                capturedPhoto.style.display = 'block';
                video.style.display = 'none';
                cameraBox.classList.add('visible');

                imageWrapper.style.display = 'none';
                viewHasImage();
            };
            reader.readAsDataURL(file);
        }

        // ============================================================
        // ✅ DRAG & DROP IMAGE
        // ============================================================
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (evt) {
            document.body.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        ['dragenter', 'dragover'].forEach(function (evt) {
            imageStage.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                imageStage.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(function (evt) {
            imageStage.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (evt === 'dragleave' && imageStage.contains(e.relatedTarget)) return;
                imageStage.classList.remove('dragover');
            }, false);
        });

        imageStage.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            imageStage.classList.remove('dragover');

            var dt = e.dataTransfer;
            if (!dt) return;

            var file = null;
            if (dt.files && dt.files.length > 0) {
                file = dt.files[0];
            } else if (dt.items && dt.items.length > 0) {
                for (var i = 0; i < dt.items.length; i++) {
                    if (dt.items[i].kind === 'file') {
                        file = dt.items[i].getAsFile();
                        break;
                    }
                }
            }

            if (!file) {
                alert('No image file detected in drop.');
                return;
            }

            applyImageFile(file);
        });

        // ============================================================
        // ✅ GLOBAL PASTE — image file + URL text
        // ============================================================
        function isTextField(el) {
            if (!el) return false;
            var tag = (el.tagName || '').toLowerCase();
            return tag === 'input' || tag === 'textarea' || el.isContentEditable;
        }

        document.addEventListener('paste', function (e) {
            var items = e.clipboardData && e.clipboardData.items;
            if (!items) return;

            // (A) pasted image file → preview + save
            for (var i = 0; i < items.length; i++) {
                if (items[i].type && items[i].type.indexOf('image') !== -1) {
                    var file = items[i].getAsFile();
                    if (!file) continue;

                    applyImageFile(file);
                    e.preventDefault();
                    return;
                }
            }

            // (B) pasted URL text → fill Image Link + auto-load
            var active = document.activeElement;

            if (active === imageLinkInput) return;
            if (isTextField(active)) return;

            var text = (e.clipboardData && e.clipboardData.getData('text')) || '';
            var trimmed = text.trim();

            if (trimmed && looksLikeImageUrl(trimmed)) {
                if (imageLinkInput) {
                    imageLinkInput.value = trimmed;
                    loadImageFromLink(trimmed);
                }
                e.preventDefault();
            }
        });

        // ============================================================
        // TRASH BUTTON
        // ============================================================
        cancelUploadBtn.addEventListener('click', function () {
            base64Input.value = '';
            fileInput.value = '';
            useDefaultImageInput.value = '0';

            productImage.src = '';
            productImage.style.display = 'none';
            imageWrapper.style.display = 'none';
            capturedPhoto.style.display = 'none';
            capturedPhoto.src = '';
            cameraBox.classList.remove('visible');

            if (imageLinkInput) imageLinkInput.value = '';
            setLinkStatus('', '');

            viewNoImage();
        });

        // ============================================================
        // FORM SUBMIT (Save button + Enter key both funnel here)
        // ============================================================
        var productFormEl = document.getElementById('productForm');

        productFormEl.addEventListener('submit', async function (e) {
            e.preventDefault();

            if (isSaving) return;
            isSaving = true;

            if (!unitHidden.value) {
                unitHidden.value = 'pcs';
            }

            var isDefault = useDefaultImageInput.value === '1';

            if (!base64Input.value && !isDefault) {
                alert('Please capture, drag-drop, paste an image, or paste an image link first.');
                isSaving = false;
                return;
            }

            if (categorySelect && categorySelect.value === '__new__') {
                var newName = (newCategoryInput.value || '').trim();
                if (!newName) {
                    alert('Please enter a name for the new category.');
                    newCategoryInput.focus();
                    isSaving = false;
                    return;
                }
                categorySelect.value = newName;
                var opt = document.createElement('option');
                opt.value = newName;
                opt.textContent = newName;
                opt.selected = true;
                categorySelect.appendChild(opt);
                newCategoryWrap.style.display = 'none';
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            var formData = new FormData(e.target);
            formData.delete('product_image');
            formData.delete('new_category_group');

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
                    isSaving = false;
                    alert(
                        'Server did not return JSON.\n\n' +
                        'HTTP status: ' + res.status + '\n\n' +
                        'Response preview:\n' + text.substring(0, 500)
                    );
                    return;
                }

                if (data.success) {
                    showSuccessLoaderThenCheck(
                        data.redirect,
                        'Upload Successful!',
                        'Your new product has been added.'
                    );
                } else {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Product';
                    isSaving = false;
                    alert(data.message);
                }
            } catch (err) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Product';
                isSaving = false;
                alert('Request failed: ' + err.message);
            }
        });

        // ============================================================
        // ✅ AUTO-SAVE ON ENTER (works from any single-line field)
        // ============================================================
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.keyCode !== 13) return;
            if (isSaving) return;

            var el = e.target;
            if (!el) return;

            var tag = (el.tagName || '').toLowerCase();

            if (tag === 'textarea') return;
            if (el.isContentEditable) return;
            if (el.id === 'image_link') return;
            if (el.id === 'unitSearch') return; // handled by unit dropdown
            if (tag === 'select') return;
            if (tag === 'button') return;

            if (successOverlay && successOverlay.classList.contains('visible')) return;

            e.preventDefault();

            if (typeof productFormEl.requestSubmit === 'function') {
                productFormEl.requestSubmit();
            } else {
                if (saveBtn) saveBtn.click();
            }
        });

        // ============================================================
        // SUCCESS MODAL
        // ============================================================
        function showSuccessLoaderThenCheck(redirectUrl, doneTitle, doneMessage) {
            successCard.classList.remove('done');
            successCard.classList.add('loading');
            successTitle.textContent = 'Saving Product…';
            successMessage.textContent = 'Please wait while we process your upload.';

            successOverlay.classList.add('visible');

            setTimeout(function () {
                successCard.classList.remove('loading');
                successCard.classList.add('done');

                successTitle.textContent = doneTitle || 'Upload Successful!';
                successMessage.textContent = doneMessage || 'Your new product has been added.';

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