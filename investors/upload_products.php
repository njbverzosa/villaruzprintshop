<?php
//investors/upload_products.php
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

if ($userRole === 'Investor') {
    $stmt = $pdo->prepare("
        SELECT id, acc_number, f_name, email, phone_number, user_name,
               business_name, business_permit, profile
        FROM investors
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$userData) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

$user = $userData;

// ==============================================
// 5. SET TIMEZONE
// ==============================================
date_default_timezone_set('Asia/Manila');
$timezone = new DateTimeZone('Asia/Manila');


// ===== FIXED QUERY - PROPER DATE SORTING =====
// Since last_restocked is stored as string (e.g., "10 August 2026 1:39 PM"),
// we need to convert it to a proper date for sorting
$stmt = $pdo->prepare("
    SELECT * FROM investors_inventory 
    ORDER BY 
        CASE 
            WHEN last_restocked IS NULL OR last_restocked = '' THEN 1 
            ELSE 0 
        END,
        STR_TO_DATE(last_restocked, '%d %M %Y %h:%i %p') DESC,
        id DESC
");
$stmt->execute();
$allProducts = $stmt->fetchAll();

// Group products by restock status for display
$recentlyRestocked = [];
$olderRestocked = [];
$neverRestocked = [];

foreach ($allProducts as $product) {
    if (empty($product['last_restocked'])) {
        $neverRestocked[] = $product;
    } else {
        try {
            // Parse the date string
            $restockDate = DateTime::createFromFormat('j M Y g:i A', $product['last_restocked']);
            if ($restockDate === false) {
                // If parsing fails, try alternative format
                $restockDate = new DateTime($product['last_restocked']);
            }
            $daysDiff = $restockDate->diff(new DateTime('now', $timezone))->days;

            if ($daysDiff <= 7) {
                $recentlyRestocked[] = $product;
            } else {
                $olderRestocked[] = $product;
            }
        } catch (Exception $e) {
            // If date parsing fails, treat as never restocked
            $neverRestocked[] = $product;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Product</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

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
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    h2 { margin-bottom: 20px; color: #333; }

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

    textarea { resize: vertical; min-height: 70px; }

    /* Square camera box */
    .camera-box {
      position: relative;
      width: 100%;
      aspect-ratio: 1 / 1;
      background: #000;
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 15px;
    }

    .camera-box video,
    .camera-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    /* Captured photo overlay — hidden until capture */
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

    .btn-capture {
      background: #28a745;
      width: 100%;
      padding: 12px;
      font-size: 16px;
      margin-bottom: 20px;
    }

    .btn-capture.retake {
      background: #f59e0b;
    }

    .btn-submit {
      background: #333;
      width: 100%;
      padding: 12px;
      font-size: 16px;
      margin-top: 10px;
    }

    button:hover { opacity: 0.9; }

    .divider {
      border-top: 1px solid #eee;
      margin: 10px 0 20px 0;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Add Product</h2>

    <form id="productForm" enctype="multipart/form-data">
      <!-- Required hidden fields for the backend -->
      <input type="hidden" name="action" value="add_product">
      <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
      <input type="hidden" name="product_image_base64" id="product_image_base64" value="">

      <!-- Camera section -->
      <div class="camera-box">
        <video id="camera" autoplay playsinline muted></video>
        <img id="capturedPhoto" alt="Captured product">
      </div>
      <button type="button" class="btn-capture" id="captureBtn">Capture Product</button>

      <div class="divider"></div>

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

  <script>
    const video = document.getElementById('camera');
    const capturedPhoto = document.getElementById('capturedPhoto');
    const captureBtn = document.getElementById('captureBtn');
    const base64Input = document.getElementById('product_image_base64');
    let stream = null;
    let isCaptured = false;

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
        isCaptured = false;
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
    }

    // ✅ Capture / Retake handler
    captureBtn.addEventListener('click', () => {
      // ---- RETAKE mode ----
      if (isCaptured) {
        base64Input.value = '';
        capturedPhoto.src = '';
        captureBtn.textContent = '📸 Capture Product';
        captureBtn.classList.remove('retake');
        startCamera();
        return;
      }

      // ---- CAPTURE mode ----
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

      // Show captured photo in place of video
      capturedPhoto.src = dataUrl;
      capturedPhoto.style.display = 'block';
      video.style.display = 'none';

      // Stop the live stream (no longer needed)
      stopCamera();

      // Change button to "Retake"
      captureBtn.textContent = '🔄 Retake Photo';
      captureBtn.classList.add('retake');
      isCaptured = true;
    });

    // ✅ Submit form
    document.getElementById('productForm').addEventListener('submit', async (e) => {
      e.preventDefault();

      if (!base64Input.value) {
        alert('Please capture the product image first.');
        return;
      }

      const formData = new FormData(e.target);

      try {
        const res = await fetch('../Inv_API/add_product.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        console.log(data);

        if (data.success) {
          alert('✅ ' + data.message + '\nProduct #: ' + data.product_number);
          e.target.reset();
          base64Input.value = '';
          capturedPhoto.src = '';
          capturedPhoto.style.display = 'none';
          isCaptured = false;
          captureBtn.textContent = 'Capture Product';
          captureBtn.classList.remove('retake');
          startCamera(); // restart for the next product
        } else {
          alert('❌ ' + data.message);
        }
      } catch (err) {
        alert('Request failed: ' + err.message);
      }
    });

    // Auto-start on page load
    window.addEventListener('load', startCamera);
  </script>
</body>
</html>