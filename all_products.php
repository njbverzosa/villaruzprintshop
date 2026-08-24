<?php
// price.php
session_start();
require_once 'DB_Conn/config.php';

// Define your live domain base URL
$baseUrl = 'https://villaruz-print-shop-and-general-merchandise.shop/price?product_number=';

// Get product number from URL parameter
$productNumber = isset($_GET['product_number']) ? trim($_GET['product_number']) : '';

// If product number is provided, show single product
if (!empty($productNumber)) {
    // Fetch single product
    $stmt = $pdo->prepare("SELECT * FROM merchandise_inventory WHERE product_number = :product_number");
    $stmt->execute([':product_number' => $productNumber]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found, try with PRD- prefix
    if (!$product) {
        if (preg_match('/^PRD(\d+)$/', $productNumber, $matches)) {
            $formattedNumber = 'PRD-' . $matches[1];
            $stmt = $pdo->prepare("SELECT * FROM merchandise_inventory WHERE product_number = :product_number");
            $stmt->execute([':product_number' => $formattedNumber]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif (is_numeric($productNumber)) {
            $formattedNumber = 'PRD-' . str_pad($productNumber, 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("SELECT * FROM merchandise_inventory WHERE product_number = :product_number");
            $stmt->execute([':product_number' => $formattedNumber]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if ($product) {
        displaySingleProduct($product);
        exit;
    } else {
        die('<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif; color: #ef4444;">
            <i class="fas fa-exclamation-circle" style="font-size: 48px;"></i>
            <h2>Product Not Found</h2>
            <p>Product number: ' . htmlspecialchars($productNumber) . ' not found in inventory.</p>
        </div>');
    }
}

// Otherwise, display all products as QR codes
displayAllQRs($pdo, $baseUrl);

function displayAllQRs($pdo, $baseUrl) {
    $stmt = $pdo->prepare("SELECT id, product_name, product_number, selling_price FROM merchandise_inventory ORDER BY id ASC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
        <title>Scan to see price</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            *{margin:0;padding:0;box-sizing:border-box}body{background:#ffffff;padding:20px;min-height:100vh}.qr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:15px;max-width:1400px;margin:0 auto}.qr-item{background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:15px 10px 12px;text-align:center;transition:all 0.2s}.qr-item:hover{border-color:#1a1a2e;box-shadow:0 4px 12px rgba(0,0,0,0.08);transform:translateY(-2px)}.qr-item .qr-image{width:100%;max-width:120px;height:auto;margin:0 auto 8px;display:block;cursor:pointer}.qr-item .qr-image img{width:100%;height:auto;display:block}.qr-item .product-name{font-size:11px;font-weight:600;color:#1a1a2e;line-height:1.3;margin-bottom:2px;font-family:Arial,sans-serif}.qr-item .product-number{font-size:9px;color:#6b7280;font-family:'Courier New',monospace}.no-products{text-align:center;padding:60px 20px;color:#6b7280;grid-column:1/-1}.no-products i{font-size:48px;display:block;margin-bottom:15px;color:#d1d5db}.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);z-index:2000;justify-content:center;align-items:center}.modal.show{display:flex}.modal-content{background:white;border-radius:12px;padding:25px;max-width:380px;width:90%;text-align:center;animation:modalFadeIn 0.3s ease;box-shadow:0 20px 60px rgba(0,0,0,0.3)}@keyframes modalFadeIn{from{transform:scale(0.95) translateY(-20px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}.modal-content h3{color:#1a1a2e;margin-bottom:12px;font-size:18px;font-family:Arial,sans-serif}.modal-content .qr-large{width:200px;height:200px;margin:0 auto;border:2px solid #e5e7eb;border-radius:10px;padding:10px;background:white}.modal-content .qr-large img{width:100%;height:100%;object-fit:contain}.modal-content .product-info{margin-top:12px}.modal-content .product-info .name{font-size:16px;font-weight:700;color:#1a1a2e;font-family:Arial,sans-serif}.modal-content .product-info .number{font-size:12px;color:#6b7280;font-family:'Courier New',monospace}.modal-actions{margin-top:15px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap}.modal-actions button{padding:8px 24px;border:none;border-radius:6px;font-weight:600;font-size:13px;cursor:pointer;transition:all 0.3s;font-family:Arial,sans-serif}.modal-actions .btn-close{background:#e5e7eb;color:#4b5563}.modal-actions .btn-close:hover{background:#d1d5db}@media(max-width:768px){.qr-grid{grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px}.qr-item{padding:10px 8px}.qr-item .qr-image{max-width:90px}.qr-item .product-name{font-size:10px}}@media(max-width:480px){body{padding:10px}.qr-grid{grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px}.qr-item{padding:8px 6px;border-radius:6px}.qr-item .qr-image{max-width:70px}.qr-item .product-name{font-size:9px}.modal-content{padding:20px}.modal-content .qr-large{width:160px;height:160px}}@media print{body{background:white;padding:10px}.qr-item{break-inside:avoid;page-break-inside:avoid;border-color:#d1d5db!important}.qr-item:hover{transform:none!important;box-shadow:none!important}.modal{display:none!important}}
        </style>
    </head>
    <body>
        <div class="qr-grid">
            <?php if (empty($products)): ?>
                <div class="no-products"><i class="fas fa-box-open"></i><h3>No Products Found</h3><p>There are no products in the inventory.</p></div>
            <?php else: ?>
                <?php foreach ($products as $product): 
                    $productNum = $product['product_number'] ?? 'PRD' . str_pad($product['id'], 6, '0', STR_PAD_LEFT);
                    $qrUrl = $baseUrl . $productNum;
                    $qrImage = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrUrl);
                ?>
                    <div class="qr-item">
                        <h4>Scan to see price</h4>
                        <div class="qr-image" onclick="openQRModal('<?php echo $qrImage; ?>', '<?php echo htmlspecialchars($product['product_name']); ?>', '<?php echo htmlspecialchars($productNum); ?>')">
                            <img src="<?php echo $qrImage; ?>" alt="QR Code for <?php echo htmlspecialchars($product['product_name']); ?>" loading="lazy" onerror="this.style.display='none'; this.parentElement.innerHTML='<span style=\'font-size:10px;color:#9ca3af;\'>Error</span>'">
                        </div>
                        <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                        <div class="product-number"><?php echo htmlspecialchars($productNum); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="modal" id="qrModal">
            <div class="modal-content">
                <h3><i class="fas fa-qrcode" style="color:#1a1a2e;"></i> Scan to see price</h3>
                <div class="qr-large"><img id="modalQRImg" src="" alt="QR Code"></div>
                <div class="product-info">
                    <div class="name" id="modalProductName">-</div>
                    <div class="number" id="modalProductNumber">-</div>
                </div>
                <div class="modal-actions"><button class="btn-close" onclick="closeQRModal()">Close</button></div>
            </div>
        </div>
        <script>
            let currentQRImage='',currentQRData={};
            function openQRModal(qrImage,productName,productNumber){currentQRImage=qrImage;currentQRData={name:productName,number:productNumber,image:qrImage};document.getElementById('modalQRImg').src=qrImage;document.getElementById('modalProductName').textContent=productName;document.getElementById('modalProductNumber').textContent=productNumber;document.getElementById('qrModal').classList.add('show');document.body.style.overflow='hidden'}
            function closeQRModal(){document.getElementById('qrModal').classList.remove('show');document.body.style.overflow=''}
            document.getElementById('qrModal').addEventListener('click',function(e){if(e.target===this){closeQRModal()}});
            document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeQRModal()}});
            console.log('📱 Product QR Codes loaded');console.log('📦 Total QR Codes: <?php echo count($products); ?>');
        </script>
    </body>
    </html>
    <?php
}

function displaySingleProduct($product) {
    $price = number_format($product['selling_price'], 2);
    $productNumberDisplay = htmlspecialchars($product['product_number'] ?? 'N/A');
    $description = htmlspecialchars($product['description'] ?? '');
    $baseUrl = 'https://villaruz-print-shop-and-general-merchandise.shop/price?product_number=';
    $qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($baseUrl . $product['product_number']);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
        <title>Scan to see price</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            *{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif}body{background:#ffffff;min-height:100vh;display:flex;justify-content:center;align-items:center;padding:20px}.price-container{width:100%;max-width:400px;animation:fadeIn 0.5s ease}@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}.price-card{background:#ffffff;border-radius:12px;padding:35px 25px 30px;text-align:center;border:1px solid #d1d5db;box-shadow:0 4px 20px rgba(0,0,0,0.08)}.price-card::before{content:'';display:block;width:50px;height:4px;background:#1a1a2e;margin:0 auto 20px;border-radius:2px}.product-name{font-size:24px;font-weight:700;color:#1a1a2e;margin-bottom:8px}.product-description{font-size:14px;color:#6b7280;line-height:1.6;margin-bottom:16px;padding:0 5px}.product-description.empty{color:#9ca3af;font-style:italic;font-size:13px}.product-number-display{font-size:14px;color:#6b7280;font-family:'Courier New',monospace;margin-bottom:20px;padding:6px 16px;background:#f3f4f6;border-radius:20px;display:inline-block}.qr-code-section{margin-top:20px;padding-top:18px;border-top:1px solid #e5e7eb;display:flex;flex-direction:column;align-items:center}.qr-code-section .qr-label{font-size:14px;color:#6b7280;margin-bottom:10px;display:flex;align-items:center;gap:8px;font-weight:500}.qr-code-section .qr-label i{color:#1a1a2e;font-size:18px}.qr-code-section .qr-image{width:200px;height:200px;border:2px solid #d1d5db;border-radius:10px;padding:10px;background:#ffffff;transition:all 0.3s;cursor:pointer}.qr-code-section .qr-image:hover{border-color:#1a1a2e;transform:scale(1.05)}.qr-code-section .qr-image img{width:100%;height:100%;object-fit:contain}.footer-note{margin-top:16px;padding-top:14px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;display:flex;justify-content:center;gap:20px;flex-wrap:wrap}.footer-note span{display:inline-flex;align-items:center;gap:4px}.back-btn{display:inline-block;margin-top:15px;padding:8px 20px;background:#e5e7eb;color:#4b5563;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;transition:all 0.3s;font-family:'Segoe UI',Arial,sans-serif}.back-btn:hover{background:#d1d5db;transform:translateY(-2px)}@media(max-width:480px){.price-card{padding:25px 18px 22px}.product-name{font-size:20px}.qr-code-section .qr-image{width:160px;height:160px}.qr-code-section .qr-label{font-size:12px}}@media print{body{background:white;padding:0}.price-card{box-shadow:none;border:1px solid #d1d5db}.back-btn{display:none}}
        </style>
    </head>
    <body>
        <div class="price-container">
            <div class="price-card">
                <h1 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                <div class="product-description <?php echo empty($description) ? 'empty' : ''; ?>">
                    <?php if (!empty($description)): ?>
                        <?php echo nl2br($description); ?>
                    <?php else: ?>
                        <i class="fas fa-info-circle" style="font-size:12px;"></i> No description available
                    <?php endif; ?>
                </div>
                <div class="product-number-display"><i class="fas fa-hashtag" style="font-size:11px;color:#9ca3af;"></i> <?php echo $productNumberDisplay; ?></div>
                <div class="qr-code-section">
                    <div class="qr-label"><i class="fas fa-qrcode"></i> Scan to see price</div>
                    <div class="qr-image" onclick="window.open('<?php echo $qrImageUrl; ?>','_blank')" title="Scan to see price">
                        <img src="<?php echo $qrImageUrl; ?>" alt="QR Code" onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;color:#9ca3af;font-size:12px;\'>QR Error</div>'">
                    </div>
                </div>
                <div class="footer-note">
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['unit'] ?? 'Pcs'); ?></span>
                    <span><i class="fas fa-cubes"></i> Stock: <?php echo number_format($product['qty_on_hand']); ?></span>
                </div>
                <a href="price.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to QR Codes</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>