<?php
//web/camera.php
session_start();

require_once __DIR__ . '/../DB_Conn/config.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>ID Photo — Background Removed</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: Arial, sans-serif;
        background: #0f172a;
        color: #f1f5f9;
        min-height: 100vh;
        padding: 20px;
        padding-bottom: 40px;
    }
    .card {
        max-width: 480px;
        margin: 0 auto;
        background: #1e293b;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.4);
    }
    h2 { font-size: 20px; margin-bottom: 6px; }
    .subtitle { font-size: 13px; color: #94a3b8; margin-bottom: 20px; }

    .size-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }
    .size-btn {
        padding: 14px 8px;
        background: #334155;
        border: 2px solid transparent;
        border-radius: 10px;
        color: #f1f5f9;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.15s;
    }
    .size-btn small {
        display: block;
        font-size: 10px;
        font-weight: 400;
        color: #94a3b8;
        margin-top: 3px;
    }
    .size-btn.active {
        background: #0ea5e9;
        border-color: #38bdf8;
    }
    .size-btn.active small { color: #e0f2fe; }

    .btn-primary {
        width: 100%;
        padding: 15px;
        background: #10b981;
        color: #ffffff;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
    }
    .btn-primary:active { transform: scale(0.98); }

    .preview-wrap {
        display: none;
        margin-top: 20px;
        text-align: center;
    }
    .preview-wrap.visible { display: block; }

    .preview-box {
        display: inline-block;
        padding: 8px;
        border: 2px dashed #475569;
        border-radius: 10px;
        background:
            repeating-conic-gradient(#334155 0% 25%, #475569 0% 50%)
            50% / 16px 16px;
        margin-bottom: 14px;
    }
    #previewImg {
        max-width: 100%;
        display: block;
        border-radius: 4px;
    }

    .actions { display: flex; gap: 10px; margin-top: 10px; }
    .actions button {
        flex: 1;
        padding: 13px;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
    }
    .btn-download { background: #3b82f6; color: #fff; }
    .btn-retake   { background: #64748b; color: #fff; }

    .spinner {
        width: 40px; height: 40px;
        border: 4px solid #334155;
        border-top-color: #0ea5e9;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 20px auto;
        display: none;
    }
    .spinner.visible { display: block; }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>
<div class="card">
    <h2>ID Photo</h2>
    <p class="subtitle">Background is removed automatically. Pick a size, then open the camera.</p>

    <div class="size-grid" id="sizeGrid">
        <button class="size-btn active" data-size="2x2" data-ratio="1.0">
            2×2<small>2 × 2 in</small>
        </button>
        <button class="size-btn" data-size="1x1" data-ratio="1.0">
            1×1<small>1 × 1 in</small>
        </button>
        <button class="size-btn" data-size="passport" data-ratio="0.7777778">
            Passport<small>35 × 45 mm</small>
        </button>
        <button class="size-btn" data-size="wallet" data-ratio="0.7142857">
            Wallet<small>2.5 × 3.5 in</small>
        </button>
        <button class="size-btn" data-size="1.5x1.5" data-ratio="1.0">
            1.5×1.5<small>1.5 × 1.5 in</small>
        </button>
    </div>

    <button class="btn-primary" id="openCameraBtn">📷 Open Camera</button>

    <div class="spinner" id="spinner"></div>

    <div class="preview-wrap" id="previewWrap">
        <div class="preview-box">
            <img id="previewImg" alt="Processed ID photo" />
        </div>
        <div class="actions">
            <button class="btn-retake" id="retakeBtn">Retake</button>
            <button class="btn-download" id="downloadBtn">Download</button>
        </div>
    </div>
</div>

<script>
    const sizeGrid       = document.getElementById('sizeGrid');
    const openCameraBtn  = document.getElementById('openCameraBtn');
    const spinner        = document.getElementById('spinner');
    const previewWrap    = document.getElementById('previewWrap');
    const previewImg     = document.getElementById('previewImg');
    const downloadBtn    = document.getElementById('downloadBtn');
    const retakeBtn      = document.getElementById('retakeBtn');

    let currentSize    = '2x2';
    let currentRatio   = 1.0;
    let currentDataUrl = '';
    let pendingCallbackId = '';

    // -------- Pick size --------
    sizeGrid.addEventListener('click', (e) => {
        const btn = e.target.closest('.size-btn');
        if (!btn) return;
        document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentSize  = btn.dataset.size;
        currentRatio = parseFloat(btn.dataset.ratio);

        // Live-update the native guide rectangle if the camera is already open
        if (window.AndroidNativeCamera
            && typeof window.AndroidNativeCamera.setGuideRatio === 'function') {
            try { window.AndroidNativeCamera.setGuideRatio(currentRatio); } catch (e) {}
        }
    });

    // -------- Open native camera --------
    openCameraBtn.addEventListener('click', () => {
        if (!window.AndroidNativeCamera) {
            alert('Native camera bridge not found. Open inside the Sofia app.');
            return;
        }
        pendingCallbackId = 'cap_' + Date.now();
        spinner.classList.add('visible');
        previewWrap.classList.remove('visible');
        window.AndroidNativeCamera.openCamera(pendingCallbackId, currentRatio);
    });

    // ====================================================
    // Native bridge callbacks
    // ====================================================

    // Fired first — small payload — safe
    function onNativeCameraResultReady(callbackId) {
        if (callbackId !== pendingCallbackId) return;
        // Image comes in the next call; nothing to do here.
    }

    // Fired second — the actual image data URL
    function onNativeCameraImage(dataUrl) {
        spinner.classList.remove('visible');
        if (!dataUrl) return;

        currentDataUrl = dataUrl;

        // Crop to the selected aspect ratio
        cropToRatio(dataUrl, currentRatio, (cropped) => {
            currentDataUrl = cropped;
            previewImg.src = cropped;
            previewWrap.classList.add('visible');
        });
    }

    function onNativeCameraError(msg) {
        spinner.classList.remove('visible');
        alert('Camera error: ' + msg);
    }

    function onNativeCameraCancelled(callbackId) {
        spinner.classList.remove('visible');
    }

    // ====================================================
    // Crop the transparent PNG to the chosen ratio
    // ====================================================
    function cropToRatio(dataUrl, ratio, cb) {
        const img = new Image();
        img.onload = () => {
            const w = img.width;
            const h = img.height;

            let cw, ch;
            if (w / h > ratio) {
                ch = h;
                cw = h * ratio;
            } else {
                cw = w;
                ch = w / ratio;
            }

            const cx = (w - cw) / 2;
            const cy = (h - ch) / 2;

            const canvas = document.createElement('canvas');
            canvas.width  = Math.round(cw);
            canvas.height = Math.round(ch);
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, cx, cy, cw, ch, 0, 0, canvas.width, canvas.height);

            cb(canvas.toDataURL('image/png'));
        };
        img.onerror = () => cb(dataUrl);
        img.src = dataUrl;
    }

    // ====================================================
    // Download
    // ====================================================
    downloadBtn.addEventListener('click', () => {
        if (!currentDataUrl) return;
        const fileName = `ID_${currentSize}_${Date.now()}.png`;

        if (window.AndroidNativeCamera
            && typeof window.AndroidNativeCamera.downloadImage === 'function') {
            const ok = window.AndroidNativeCamera.downloadImage(currentDataUrl, fileName);
            alert(ok
                ? 'Saved to your gallery: Pictures/SofiaIDPhoto'
                : 'Could not save the image.');
        } else {
            const a = document.createElement('a');
            a.download = fileName;
            a.href = currentDataUrl;
            a.click();
        }
    });

    retakeBtn.addEventListener('click', () => {
        previewWrap.classList.remove('visible');
        openCameraBtn.click();
    });
</script>
</body>
</html>