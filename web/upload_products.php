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
      width: 100%;
      aspect-ratio: 1 / 1;
      background: #000;
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 15px;
    }

    video {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
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
      </div>
      <button type="button" class="btn-capture" id="captureBtn">📸 Capture Product</button>

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
    const captureBtn = document.getElementById('captureBtn');
    const base64Input = document.getElementById('product_image_base64');
    let stream = null;

    // ✅ Auto-start BACK camera (environment = rear camera)
    window.addEventListener('load', async () => {
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: { ideal: 'environment' },  // 👈 back camera
            width: { ideal: 1280 },
            height: { ideal: 1280 }
          },
          audio: false
        });
        video.srcObject = stream;
      } catch (err) {
        console.error('Camera error:', err);
        alert('Camera not available: ' + err.message);
      }
    });

    // Capture photo → convert to base64 → store in hidden input
    captureBtn.addEventListener('click', () => {
      if (!stream) {
        alert('Camera is not ready yet.');
        return;
      }

      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext('2d').drawImage(video, 0, 0);

      // Send as base64 data URI (backend expects product_image_base64)
      const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
      base64Input.value = dataUrl;

      // Visual feedback
      captureBtn.textContent = '✅ Photo Captured';
      captureBtn.style.background = '#155724';
      setTimeout(() => {
        captureBtn.textContent = '📸 Capture Product';
        captureBtn.style.background = '#28a745';
      }, 1500);
    });

    // Submit form via fetch (matches backend JSON response)
    document.getElementById('productForm').addEventListener('submit', async (e) => {
      e.preventDefault();

      if (!base64Input.value) {
        alert('Please capture the product image first.');
        return;
      }

      const formData = new FormData(e.target);

      try {
        const res = await fetch('../API/add_product.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        console.log(data);

        if (data.success) {
          alert('✅ ' + data.message + '\nProduct #: ' + data.product_number);
          e.target.reset();
          base64Input.value = '';
        } else {
          alert('❌ ' + data.message);
        }
      } catch (err) {
        alert('Request failed: ' + err.message);
      }
    });
  </script>
</body>
</html>