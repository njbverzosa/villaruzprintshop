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

$imageUrl = '';
$imageExists = false;

if (!empty($product['product_image'])) {
    $imageUrl = '../Products/' . htmlspecialchars($product['product_image']);
    $absoluteImagePath = dirname(__DIR__) . '/Products/' . $product['product_image'];
    $imageExists = file_exists($absoluteImagePath);
}

// ✅ Fetch distinct category groups for the dropdown
$catStmt = $pdo->query("
    SELECT DISTINCT category_group
    FROM merchandise_inventory
    WHERE category_group IS NOT NULL AND category_group <> ''
    ORDER BY category_group ASC
");
$allCategoryGroups = $catStmt->fetchAll(PDO::FETCH_COLUMN);

$currentCategory = $product['category_group'] ?? '';
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
            padding-bottom: 120px;
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
        input[type="url"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }

        select {
            background: #fff;
            cursor: pointer;
        }

        textarea {
            resize: vertical;
            min-height: 70px;
        }

        /* ✅ Product name + search button row */
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
            transition: background 0.2s, transform 0.1s;
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

        /* ============================================================
           ✅ Image Link input (auto-load, no button)
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

        .divider {
            border-top: 1px solid #eee;
            margin: 10px 0 20px 0;
        }

        #productImageFile {
            display: none;
        }

        /* ============================================================
           ✅ SUCCESS MODAL
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes popIn {
            from { opacity: 0; transform: scale(0.9) translateY(12px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
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
        <h2>Update Product</h2>
        <div class="product-number">Product #: <?php echo htmlspecialchars($product['product_number']); ?> - <?php echo htmlspecialchars($product['category_group']); ?></div>

        <form id="productForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
            <input type="hidden" name="product_number"
                value="<?php echo htmlspecialchars($product['product_number']); ?>">
            <input type="hidden" name="csrf_token" id="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
            <input type="hidden" name="product_image_base64" id="product_image_base64" value="">
            <input type="hidden" name="replace_image" id="replace_image" value="0">

            <!-- CATEGORY GROUP -->
            <label for="category_group">Category Group</label>
            <select name="category_group" id="category_group">
                <option value="">— None —</option>
                <?php foreach ($allCategoryGroups as $cg): ?>
                    <option value="<?php echo htmlspecialchars($cg); ?>"
                        <?php echo ($cg === $currentCategory) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cg); ?>
                    </option>
                <?php endforeach; ?>
                <?php if ($currentCategory !== '' && !in_array($currentCategory, $allCategoryGroups, true)): ?>
                    <option value="<?php echo htmlspecialchars($currentCategory); ?>" selected>
                        <?php echo htmlspecialchars($currentCategory); ?>
                    </option>
                <?php endif; ?>
                <option value="__new__">+ Add new category…</option>
            </select>

            <div id="newCategoryWrap" style="display:none;">
                <label for="new_category_group">New Category Name</label>
                <input type="text" id="new_category_group" name="new_category_group"
                    placeholder="e.g. Printer Accessories">
            </div>

            <!-- Hidden file input (kept for backend compatibility — not triggered by any UI now) -->
            <input type="file" id="productImageFile" accept="image/jpeg,image/jpg,image/png,image/webp">

            <div class="image-stage" id="imageStage">
                <button type="button" class="cancel-x-btn" id="cancelUploadBtn" title="Remove image">
                    <i class="fas fa-trash"></i>
                </button>

                <div class="product-image-wrapper" id="imageWrapper">
                    <img src="<?php echo $imageUrl; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                        id="productImage" style="<?php echo $imageUrl ? '' : 'display:none;'; ?>">
                </div>

                <div class="image-placeholder" id="imagePlaceholder">
                    <i class="fas fa-image"></i>
                    <span>No image — use the camera or paste a link</span>
                </div>

                <div class="camera-box" id="cameraBox">
                    <video id="camera" autoplay playsinline muted></video>
                    <img id="capturedPhoto" alt="Captured product">
                </div>
            </div>

            <div class="divider"></div>

            <!-- ============================================================
                 ✅ Image Link input — auto-load, hidden when an image exists
                 ============================================================ -->
            <div id="imageLinkBlock">
                <label for="image_link">Image Link</label>
                <div class="image-link-row">
                    <input type="url" id="image_link"
                        placeholder="Paste image URL here — it loads automatically"
                        autocomplete="off" spellcheck="false">
                </div>
                <div class="image-link-status" id="imageLinkStatus"></div>
                <div class="image-link-hint">
                    Paste a direct image URL (ending in .jpg, .png, .webp…). The image will load and preview automatically.
                </div>
            </div>

            <!-- Product Name + Google Search button -->
            <label for="product_name">Product Name</label>
            <div class="name-search-row">
                <input type="text" id="product_name" name="product_name"
                    value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                <button type="button" class="btn-search-google" id="searchGoogleBtn"
                    title="Search this product on Google">
                    <i class="fas fa-search"></i>
                    <span>Search</span>
                </button>
            </div>

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

            <button type="submit" class="btn-submit" id="saveBtn">Save Changes</button>
        </form>
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
            <div class="success-title" id="successTitle">Saving Changes…</div>
            <div class="success-message" id="successMessage">Please wait while we update the product.</div>
        </div>
    </div>

    <script>
        const video = document.getElementById('camera');
        const capturedPhoto = document.getElementById('capturedPhoto');
        const captureBtn = document.getElementById('captureBtn');
        const retakeBtn = document.getElementById('retakeBtn');
        const cancelUploadBtn = document.getElementById('cancelUploadBtn');
        const cameraBox = document.getElementById('cameraBox');
        const imageWrapper = document.getElementById('imageWrapper');
        const imagePlaceholder = document.getElementById('imagePlaceholder');
        const productImage = document.getElementById('productImage');
        const base64Input = document.getElementById('product_image_base64');
        const replaceImageInput = document.getElementById('replace_image');
        const saveBtn = document.getElementById('saveBtn');

        const successOverlay = document.getElementById('successOverlay');
        const successCard = document.getElementById('successCard');
        const successTitle = document.getElementById('successTitle');
        const successMessage = document.getElementById('successMessage');

        const originalImageSrc = '<?php echo $imageUrl; ?>';
        const originalImageExists = <?php echo $imageUrl ? 'true' : 'false'; ?>;

        let stream = null;

        // ============================================================
        // ✅ CATEGORY GROUP — show/hide "new category" input
        // ============================================================
        const categorySelect = document.getElementById('category_group');
        const newCategoryWrap = document.getElementById('newCategoryWrap');
        const newCategoryInput = document.getElementById('new_category_group');

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
        // ✅ IMAGE LINK INPUT (auto-load, no button)
        // ============================================================
        const imageLinkBlock = document.getElementById('imageLinkBlock');
        const imageLinkInput = document.getElementById('image_link');
        const imageLinkStatus = document.getElementById('imageLinkStatus');

        function setLinkStatus(msg, type) {
            if (!imageLinkStatus) return;
            imageLinkStatus.className = 'image-link-status ' + (type || '');
            imageLinkStatus.textContent = msg || '';
        }

        function showImageLinkBlock() {
            if (imageLinkBlock) imageLinkBlock.style.display = 'block';
        }

        function hideImageLinkBlock() {
            if (imageLinkBlock) imageLinkBlock.style.display = 'none';
        }

        // Basic URL sanity check
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
                const res = await fetch(url, { mode: 'cors', credentials: 'omit' });
                if (!res.ok) throw new Error('HTTP ' + res.status);

                const blob = await res.blob();
                if (!blob.type || blob.type.indexOf('image/') !== 0) {
                    throw new Error('URL is not an image');
                }
                if (blob.size > 5 * 1024 * 1024) {
                    throw new Error('Image is larger than 5MB');
                }

                const dataUrl = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);
                });

                base64Input.value = dataUrl;
                replaceImageInput.value = '1';

                capturedPhoto.src = dataUrl;
                capturedPhoto.style.display = 'block';
                video.style.display = 'none';
                cameraBox.classList.add('visible');

                imageWrapper.style.display = 'none';
                viewHasImage();

                setLinkStatus('✔ Image loaded and ready to save.', 'ok');

                setTimeout(function () {
                    hideImageLinkBlock();
                    setLinkStatus('', '');
                }, 600);
            } catch (err) {
                console.warn('Image link fetch failed:', err);
                const previewImg = new Image();
                previewImg.onload = () => {
                    productImage.src = url;
                    productImage.style.display = 'block';
                    imageWrapper.style.display = 'flex';
                    cameraBox.classList.remove('visible');
                    capturedPhoto.style.display = 'none';
                    imagePlaceholder.classList.remove('visible');

                    setLinkStatus('Preview only — this host blocks cross-origin download. Download the image manually and upload it.', 'error');
                };
                previewImg.onerror = () => {
                    setLinkStatus('Failed to load image. ' + (err.message || ''), 'error');
                };
                previewImg.src = url;
            }
        }

        if (imageLinkInput) {
            imageLinkInput.addEventListener('paste', function () {
                setTimeout(() => {
                    const v = (imageLinkInput.value || '').trim();
                    if (v && looksLikeImageUrl(v)) loadImageFromLink(v);
                }, 30);
            });

            imageLinkInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const v = (imageLinkInput.value || '').trim();
                    if (v) loadImageFromLink(v);
                }
            });

            imageLinkInput.addEventListener('blur', function () {
                const v = (imageLinkInput.value || '').trim();
                if (v && looksLikeImageUrl(v)) loadImageFromLink(v);
            });
        }

        // ============================================================
        // ✅ GOOGLE SEARCH BUTTON
        // ============================================================
        const searchGoogleBtn = document.getElementById('searchGoogleBtn');

        if (searchGoogleBtn) {
            searchGoogleBtn.addEventListener('click', function () {
                const nameInput = document.getElementById('product_name');
                const name = (nameInput.value || '').trim();

                if (!name) {
                    alert('Please enter a product name first.');
                    nameInput.focus();
                    return;
                }

                const url = 'https://www.google.com/search?q=' + encodeURIComponent(name);

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

        // ============================================================
        // VIEW STATES
        // ============================================================
        function viewHasImage() {
            hideFloatingCamera();
            hideFloatingCapture();
            showCancelButton();
            hidePlaceholder();
        }

        function viewNoImage() {
            hideCancelButton();
            showFloatingCamera();
            hideFloatingCapture();
            showPlaceholder();
            showImageLinkBlock();
        }

        function viewCameraActive() {
            hideCancelButton();
            hideFloatingCamera();
            showFloatingCapture();
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
            } catch (err) {
                console.error('Camera error:', err);
                alert('Camera not available: ' + (err.message || err.name || 'Unknown error'));
                if (originalImageExists) viewHasImage(); else viewNoImage();
            }
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(t => t.stop());
                stream = null;
            }
            cameraBox.classList.remove('visible');
        }

        retakeBtn.addEventListener('click', () => {
            base64Input.value = '';
            replaceImageInput.value = '1';
            startCamera();
        });

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
            cameraBox.classList.add('visible');
            viewHasImage();

            hideImageLinkBlock();
        });

        // ============================================================
        // TRASH BUTTON
        // ============================================================
        cancelUploadBtn.addEventListener('click', () => {
            base64Input.value = '';
            replaceImageInput.value = '2';

            productImage.src = '';
            productImage.style.display = 'none';
            imageWrapper.style.display = 'none';
            capturedPhoto.style.display = 'none';
            capturedPhoto.src = '';
            cameraBox.classList.remove('visible');

            if (imageLinkInput) imageLinkInput.value = '';
            setLinkStatus('', '');

            showImageLinkBlock();
            viewNoImage();
        });

        // ============================================================
        // FORM SUBMIT
        // ============================================================
        document.getElementById('productForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const hasNewImage = base64Input.value !== '';
            const isDeleting = replaceImageInput.value === '2';

            if (!hasNewImage && !originalImageExists && !isDeleting) {
                alert('Please capture a photo or paste an image link first.');
                return;
            }

            if (categorySelect && categorySelect.value === '__new__') {
                const newName = (newCategoryInput.value || '').trim();
                if (!newName) {
                    alert('Please enter a name for the new category.');
                    newCategoryInput.focus();
                    return;
                }
                categorySelect.value = newName;

                const opt = document.createElement('option');
                opt.value = newName;
                opt.textContent = newName;
                opt.selected = true;
                categorySelect.appendChild(opt);

                newCategoryWrap.style.display = 'none';
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            const formData = new FormData(e.target);
            formData.delete('new_category_group');

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
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';
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
                        'Update Successful!',
                        'Your product changes have been saved.'
                    );
                } else {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';
                    alert(data.message);
                }
            } catch (err) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
                alert('Request failed: ' + err.message);
            }
        });

        // ============================================================
        // SUCCESS MODAL
        // ============================================================
        function showSuccessLoaderThenCheck(redirectUrl, doneTitle, doneMessage) {
            successCard.classList.remove('done');
            successCard.classList.add('loading');
            successTitle.textContent = 'Saving Changes…';
            successMessage.textContent = 'Please wait while we update the product.';

            successOverlay.classList.add('visible');

            setTimeout(function () {
                successCard.classList.remove('loading');
                successCard.classList.add('done');

                successTitle.textContent = doneTitle || 'Update Successful!';
                successMessage.textContent = doneMessage || 'Your product changes have been saved.';

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
        window.addEventListener('load', () => {
            if (originalImageExists) {
                viewHasImage();
                hideImageLinkBlock();
            } else {
                viewNoImage();
                showImageLinkBlock();
            }
        });
    </script>
</body>

</html>